<?php

namespace FacturaScripts\Plugins\googledrive_sync\Lib;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveCompanyConfig;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveFileMap;
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
        if (false === $config->isConfigured()) {
            $job->state = 'failed';
            $job->last_error = 'Configuration missing';
            $job->save();
            return false;
        }

        $payload = $this->decodePayload($job->payload);

        try {
            switch ($job->action) {
                case 'delete':
                    $this->processDelete($job, $config, $payload);
                    break;
                case 'sync':
                default:
                    $this->processSync($job, $config, $payload);
                    break;
            }

            $payload = $this->updatePayload($job, $payload, [
                'last_result' => 'done',
                'last_run_at' => Tools::dateTime(),
                'duration_ms' => $this->elapsedMillis($startedAt),
            ]);

            $job->state = 'done';
            $job->available_at = null;
            $job->last_error = null;
            $job->locked_at = null;
            $job->save();
            return true;
        } catch (Throwable $exception) {
            $this->markMapAsFailed($job, $payload, $exception->getMessage());

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
                'duration_ms' => $this->elapsedMillis($startedAt),
                'last_error' => $exception->getMessage(),
            ]);

            $job->locked_at = null;
            $job->save();
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

        $payload = array_merge([
            'map_id' => $extra['map_id'] ?? null,
            'content_hash' => $extra['content_hash'] ?? null,
            'queued_at' => Tools::dateTime(),
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

    private function processSync(GoogleDriveQueue $job, GoogleDriveCompanyConfig $config, array $payload): void
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

        $result = $client->upload($map, $folderId, $map->filename, $render['content']);

        $job->google_file_id = $result['file_id'];
    }

    private function processDelete(GoogleDriveQueue $job, GoogleDriveCompanyConfig $config, array $payload): void
    {
        $map = $this->resolveFileMap($job, $payload, false);
        if (!$map instanceof GoogleDriveFileMap) {
            return;
        }

        $client = new GoogleDriveClient($config);
        $client->moveToTrash($map);
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

    private function markMapAsFailed(GoogleDriveQueue $job, array $payload, string $error): void
    {
        $map = $this->resolveFileMap($job, $payload, false);
        if (!$map instanceof GoogleDriveFileMap) {
            return;
        }

        $map->sync_status = 'failed';
        $map->sync_error = $error;
        $map->save();
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
