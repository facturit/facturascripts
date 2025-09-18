<?php

namespace FacturaScripts\Plugins\googledrive_sync\Lib;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveCompanyConfig;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveFileMap;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveLog;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveQueue;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Handles queue orchestration and delegates the heavy lifting to the helper
 * classes in charge of rendering and interacting with Google Drive.
 */
class SyncWorker
{
    private PdfRenderer $pdfRenderer;

    public function __construct(?PdfRenderer $pdfRenderer = null)
    {
        $this->pdfRenderer = $pdfRenderer ?? new PdfRenderer();
    }

    public function processNext(?int $companyId = null): ?GoogleDriveQueue
    {
        $query = GoogleDriveQueue::table()
            ->whereEq('state', 'queued')
            ->orderBy('priority', 'DESC')
            ->orderBy('id', 'ASC')
            ->limit(10);

        if (null !== $companyId) {
            $query->whereEq('idempresa', $companyId);
        }

        foreach ($query->get() as $row) {
            $job = new GoogleDriveQueue($row);
            if (!empty($job->available_at) && strtotime($job->available_at) > time()) {
                continue;
            }

            $this->process($job);
            return $job;
        }

        return null;
    }

    public function process(GoogleDriveQueue $job): bool
    {
        if ($job->attempts >= $job->max_attempts && $job->state === 'failed') {
            return false;
        }

        $startedAt = microtime(true);
        $job->attempts++;
        $job->state = 'running';
        $job->locked_at = Tools::dateTime();
        $job->save();

        $config = GoogleDriveCompanyConfig::forCompany((int)$job->idempresa);
        $payload = $this->decodePayload($job->payload);

        if (false === $config->isConfigured()) {
            $job->state = 'failed';
            $job->available_at = null;
            $job->last_error = 'configuration-missing';
            $job->locked_at = null;
            $job->save();

            $this->logJob($job, 'failed', [
                'message' => 'configuration-missing',
                'duration_ms' => $this->elapsedMillis($startedAt),
                'file_id' => $payload['google_file_id'] ?? null,
                'payload_user' => $payload['triggered_by'] ?? null,
            ]);
            return false;
        }

        try {
            $result = match ($job->action) {
                'delete' => $this->processDelete($job, $config, $payload),
                default => $this->processSync($job, $config, $payload),
            };

            $status = $result['status'] ?? 'ok';
            $duration = $this->elapsedMillis($startedAt);

            $payload = $this->updatePayload($job, $payload, [
                'last_result' => $status === 'skipped' ? 'skipped' : 'done',
                'last_run_at' => Tools::dateTime(),
                'duration_ms' => $duration,
                'last_error' => null,
            ]);

            if (!empty($result['file_id'])) {
                $job->google_file_id = $result['file_id'];
            }

            $job->state = 'done';
            $job->available_at = null;
            $job->last_error = null;
            $job->locked_at = null;
            $job->save();

            $logResult = $status === 'skipped' ? 'skipped' : 'ok';
            $this->logJob($job, $logResult, array_merge($result, [
                'duration_ms' => $duration,
                'payload_user' => $payload['triggered_by'] ?? null,
            ]));

            return true;
        } catch (Throwable $exception) {
            $duration = $this->elapsedMillis($startedAt);
            $map = $this->markMapAsFailed($job, $payload, $exception->getMessage());

            $job->last_error = $exception->getMessage();
            if ($job->attempts >= $job->max_attempts) {
                $job->state = 'failed';
                $job->available_at = null;
            } else {
                $job->state = 'queued';
                $backoff = min(3600, (int)pow(2, $job->attempts));
                $job->available_at = date('Y-m-d H:i:s', time() + $backoff);
            }
            $payload = $this->updatePayload($job, $payload, [
                'last_result' => 'failed',
                'last_run_at' => Tools::dateTime(),
                'duration_ms' => $duration,
                'last_error' => $exception->getMessage(),
            ]);

            $job->locked_at = null;
            $job->save();

            $this->logJob($job, 'failed', [
                'message' => $exception->getMessage(),
                'duration_ms' => $duration,
                'file_id' => $map?->google_file_id ?? ($payload['google_file_id'] ?? null),
                'payload_user' => $payload['triggered_by'] ?? null,
            ]);
            return false;
        }
    }

    public static function enqueueDocument(BusinessDocument $document, string $action = 'sync', array $extra = []): bool
    {
        $documentId = (int)$document->id();
        if ($documentId <= 0) {
            return false;
        }

        $modelClass = $document->modelClassName();
        $companyId = (int)($document->idempresa ?? 0);

        $where = [
            Where::eq('model', $modelClass),
            Where::eq('iddocument', $documentId),
            Where::eq('action', $action),
            Where::in('state', ['queued', 'scheduled', 'running']),
        ];

        $queue = GoogleDriveQueue::findWhere($where);
        if (!$queue instanceof GoogleDriveQueue) {
            $queue = new GoogleDriveQueue();
            $queue->model = $modelClass;
            $queue->iddocument = $documentId;
            $queue->idempresa = $companyId;
            $queue->action = $action;
        }

        $user = Session::user();
        $triggeredBy = '';
        if (is_object($user) && !empty($user->nick)) {
            $triggeredBy = (string)$user->nick;
        }

        $payload = array_merge([
            'map_id' => $extra['map_id'] ?? null,
            'content_hash' => $extra['content_hash'] ?? null,
            'queued_at' => Tools::dateTime(),
            'triggered_by' => $triggeredBy ?: 'system',
        ], self::documentMetadata($document), $extra);

        try {
            $queue->payload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            $queue->payload = '{}';
        }

        $queue->state = 'queued';
        $queue->available_at = Tools::dateTime();
        return $queue->save();
    }

    public static function releaseStaleJobs(int $olderThanSeconds = 900): int
    {
        $threshold = date('Y-m-d H:i:s', time() - max(60, $olderThanSeconds));
        $released = 0;

        $rows = GoogleDriveQueue::table()
            ->whereEq('state', 'running')
            ->whereLt('locked_at', $threshold)
            ->get();

        foreach ($rows as $row) {
            $queue = new GoogleDriveQueue($row);
            $queue->state = 'queued';
            $queue->locked_at = null;
            $queue->available_at = Tools::dateTime();
            if (empty($queue->last_error)) {
                $queue->last_error = 'released-stale-job';
            }
            $queue->save();
            $released++;
        }

        return $released;
    }

    private function logJob(GoogleDriveQueue $job, string $result, array $context = []): void
    {
        try {
            $log = new GoogleDriveLog();
            $log->idempresa = $job->idempresa ? (int)$job->idempresa : null;
            $log->model = $job->model ?? '';
            $log->iddocument = (int)$job->iddocument;
            $log->action = $job->action ?? 'sync';
            $log->result = $result;
            $log->message = $context['message'] ?? null;
            $log->google_file_id = $context['file_id'] ?? $job->google_file_id;
            if (isset($context['filesize'])) {
                $log->filesize = (int)$context['filesize'];
            }
            if (isset($context['duration_ms'])) {
                $log->duration_ms = (int)$context['duration_ms'];
            }

            $log->username = $this->resolveUsername($context['payload_user'] ?? null);

            $metadata = $context['metadata'] ?? [];
            if (!is_array($metadata)) {
                $metadata = [];
            }

            if (!empty($context['shared_with']) && is_array($context['shared_with'])) {
                $metadata['shared_with'] = array_values(array_unique(array_map('strval', $context['shared_with'])));
            }

            if (!empty($metadata)) {
                $log->metadata = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $log->save();
        } catch (Throwable $exception) {
            Tools::log()->warning('googledrive-log-error', ['%error%' => $exception->getMessage()]);
        }
    }

    private function resolveUsername(?string $payloadUser): string
    {
        $candidate = trim((string)$payloadUser);
        if ($candidate !== '') {
            return $candidate;
        }

        $user = Session::user();
        if (is_object($user)) {
            if (!empty($user->nick)) {
                return (string)$user->nick;
            }
            if (!empty($user->email)) {
                return (string)$user->email;
            }
        }

        return 'system';
    }

    private static function documentMetadata(BusinessDocument $document): array
    {
        $docDate = $document->fecha ?? '';
        $docYear = null;
        if (!empty($docDate)) {
            $timestamp = strtotime($docDate);
            if ($timestamp !== false) {
                $docYear = (int)date('Y', $timestamp);
            }
        }

        $thirdName = null;
        if (property_exists($document, 'nombrecliente') && !empty($document->nombrecliente)) {
            $thirdName = $document->nombrecliente;
        } elseif (property_exists($document, 'nombreproveedor') && !empty($document->nombreproveedor)) {
            $thirdName = $document->nombreproveedor;
        } elseif (property_exists($document, 'razonsocial') && !empty($document->razonsocial)) {
            $thirdName = $document->razonsocial;
        } elseif (property_exists($document, 'nombre') && !empty($document->nombre)) {
            $thirdName = $document->nombre;
        }

        $identifier = $document->codigo ?? trim((string)($document->codserie ?? '') . '-' . (string)($document->numero ?? ''));

        return [
            'doc_type' => Tools::slug($document->modelClassName()),
            'doc_model' => $document->modelClassName(),
            'doc_identifier' => trim($identifier, '-'),
            'doc_number' => $document->numero ?? null,
            'doc_serie' => $document->codserie ?? null,
            'doc_date' => $docDate ?: null,
            'doc_year' => $docYear,
            'third_nif' => $document->cifnif ?? null,
            'third_name' => $thirdName,
            'company_id' => (int)($document->idempresa ?? 0),
        ];
    }

    private function processSync(GoogleDriveQueue $job, GoogleDriveCompanyConfig $config, array $payload): array
    {
        $map = $this->resolveFileMap($job, $payload, true);
        if (!$map instanceof GoogleDriveFileMap) {
            throw new RuntimeException('file-map-not-found');
        }

        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $job->model;
        if (!class_exists($modelClass)) {
            throw new RuntimeException('model-not-found: ' . $job->model);
        }

        $document = new $modelClass();
        if (false === $document->load($job->iddocument)) {
            throw new RuntimeException('document-not-found');
        }

        if ($config->isModelExcluded($job->model) || $config->isModelExcluded($document->modelClassName())) {
            $map->sync_status = 'skipped';
            $map->sync_error = 'excluded-by-config';
            $map->save();

            return [
                'status' => 'skipped',
                'file_id' => $map->google_file_id,
                'message' => 'excluded-by-config',
                'metadata' => [
                    'reason' => 'excluded-by-config',
                ],
            ];
        }

        $resolver = new PathResolver($config);
        $pathData = $resolver->resolve($document);

        $client = new GoogleDriveClient($config);
        $folderId = $client->ensureFolder((int)$job->idempresa, $pathData['path']);

        $render = $this->pdfRenderer->render($document);
        $contentHash = $payload['content_hash'] ?? HashUtil::fromString($render['content']);

        $map->content_hash = $contentHash;
        $map->sync_status = 'running';
        $map->sync_error = null;
        $map->filename = $pathData['filename'] ?: $render['filename'];
        $map->idempresa = (int)$job->idempresa;
        $map->model = $job->model;
        $map->iddocument = (int)$job->iddocument;
        $map->save();

        $shareWith = $config->shareRecipients($document);
        $result = $client->upload($map, $folderId, $map->filename, $render['content'], $shareWith);

        $job->google_file_id = $result['file_id'];

        return [
            'status' => 'ok',
            'file_id' => $result['file_id'],
            'filesize' => strlen($render['content']),
            'shared_with' => $result['shared_with'] ?? [],
            'message' => 'sync-completed',
            'metadata' => [
                'path' => $pathData['path'],
                'filename' => $map->filename,
                'shared_with' => $result['shared_with'] ?? [],
            ],
        ];
    }

    private function processDelete(GoogleDriveQueue $job, GoogleDriveCompanyConfig $config, array $payload): array
    {
        $map = $this->resolveFileMap($job, $payload, false);
        if (!$map instanceof GoogleDriveFileMap) {
            return [
                'status' => 'ok',
                'message' => 'no-file',
            ];
        }

        $client = new GoogleDriveClient($config);
        $client->moveToTrash($map);

        return [
            'status' => 'ok',
            'file_id' => $map->google_file_id,
            'message' => 'moved-to-trash',
        ];
    }

    private function resolveFileMap(GoogleDriveQueue $job, array $payload, bool $create): ?GoogleDriveFileMap
    {
        if (!empty($payload['map_id'])) {
            $map = GoogleDriveFileMap::find((int)$payload['map_id']);
            if ($map instanceof GoogleDriveFileMap) {
                return $map;
            }
        }

        $where = [
            Where::eq('model', $job->model),
            Where::eq('iddocument', (int)$job->iddocument),
            Where::eq('idempresa', (int)$job->idempresa),
        ];
        $map = GoogleDriveFileMap::findWhere($where);
        if ($map instanceof GoogleDriveFileMap) {
            return $map;
        }

        if (false === $create) {
            return null;
        }

        $map = new GoogleDriveFileMap();
        $map->model = $job->model;
        $map->iddocument = (int)$job->iddocument;
        $map->idempresa = (int)$job->idempresa;
        $map->sync_status = 'queued';
        $map->save();

        return $map;
    }

    private function decodePayload(?string $payload): array
    {
        if (empty($payload)) {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function markMapAsFailed(GoogleDriveQueue $job, array $payload, string $error): ?GoogleDriveFileMap
    {
        $map = $this->resolveFileMap($job, $payload, false);
        if (!$map instanceof GoogleDriveFileMap) {
            return null;
        }

        $map->sync_status = 'failed';
        $map->sync_error = $error;
        $map->save();

        return $map;
    }

    private function updatePayload(GoogleDriveQueue $job, array $payload, array $overrides): array
    {
        $payload = array_merge($payload, $overrides);

        try {
            $job->payload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            $job->payload = '{}';
        }

        return $payload;
    }

    private function elapsedMillis(float $startedAt): int
    {
        return (int)round((microtime(true) - $startedAt) * 1000);
    }
}
