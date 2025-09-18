<?php

namespace FacturaScripts\Plugins\googledrive_sync\Controller;

use BadMethodCallException;
use FacturaScripts\Core\Lib\ExtendedController\PanelController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\googledrive_sync\Lib\HashUtil;
use FacturaScripts\Plugins\googledrive_sync\Lib\SyncWorker;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveCompanyConfig;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveFileMap;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveQueue;
use JsonException;

class GoogleDriveSyncController extends PanelController
{
    private const DEFAULT_MODELS = [
        'FacturaCliente',
        'FacturaProveedor',
        'PresupuestoCliente',
        'PresupuestoProveedor',
        'PedidoCliente',
        'PedidoProveedor',
        'AlbaranCliente',
        'AlbaranProveedor',
        'AbonoCliente',
        'AbonoProveedor',
    ];

    private array $companyOptions = [];

    private ?int $selectedCompanyId = null;

    private array $queueFilters = [];

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['name'] = 'GoogleDriveSync';
        $pageData['title'] = 'google-drive-sync';
        $pageData['menu'] = 'admin';
        $pageData['submenu'] = 'plugins';
        $pageData['icon'] = 'fa-brands fa-google-drive';
        return $pageData;
    }

    public function getCompanyOptions(): array
    {
        if (empty($this->companyOptions)) {
            $model = new Empresa();
            foreach ($model->all([], ['nombre' => 'ASC']) as $company) {
                $this->companyOptions[(int)$company->idempresa] = $company->nombre;
            }
        }

        return $this->companyOptions;
    }

    public function getSelectedCompanyId(): int
    {
        if (null !== $this->selectedCompanyId) {
            return $this->selectedCompanyId;
        }

        $companyId = (int)$this->request->get('company_id', 0);
        if ($companyId <= 0) {
            $companyId = (int)$this->request->request->get('company_id', 0);
        }

        if ($companyId <= 0) {
            $options = $this->getCompanyOptions();
            $companyId = (int)(array_key_first($options) ?? 0);
        }

        $this->selectedCompanyId = $companyId;
        return $this->selectedCompanyId;
    }

    public function getCompanyConfig(?int $companyId = null): GoogleDriveCompanyConfig
    {
        $companyId ??= $this->getSelectedCompanyId();
        if ($companyId <= 0) {
            return new GoogleDriveCompanyConfig();
        }

        return GoogleDriveCompanyConfig::forCompany($companyId);
    }

    public function getQueueFilters(): array
    {
        if (empty($this->queueFilters)) {
            $state = (string)$this->request->get('queue_state', '');
            $company = (int)$this->request->get('queue_company', 0);
            $allowedStates = ['queued', 'running', 'done', 'failed'];
            if (!in_array($state, $allowedStates, true)) {
                $state = '';
            }

            $this->queueFilters = [
                'state' => $state,
                'company' => $company,
            ];
        }

        return $this->queueFilters;
    }

    public function getQueueStats(): array
    {
        $filters = $this->getQueueFilters();
        $query = GoogleDriveQueue::table()
            ->selectRaw('state, COUNT(*) AS total')
            ->groupBy('state');

        if ($filters['company'] > 0) {
            $query->whereEq('idempresa', $filters['company']);
        }

        $stats = [
            'queued' => 0,
            'running' => 0,
            'done' => 0,
            'failed' => 0,
        ];

        foreach ($query->get() as $row) {
            $state = $row['state'] ?? '';
            if (isset($stats[$state])) {
                $stats[$state] = (int)$row['total'];
            }
        }

        $stats['total'] = array_sum($stats);
        return $stats;
    }

    public function getYearlyBreakdown(): array
    {
        $filters = $this->getQueueFilters();
        $query = GoogleDriveQueue::table()
            ->selectRaw('payload, model')
            ->orderBy('updated_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(200);

        if ($filters['company'] > 0) {
            $query->whereEq('idempresa', $filters['company']);
        }

        $summary = [];
        foreach ($query->get() as $row) {
            $payload = $this->decodePayload($row['payload'] ?? null);
            $year = $payload['doc_year'] ?? null;
            if (empty($year)) {
                continue;
            }

            $type = $payload['doc_type'] ?? Tools::slug($row['model'] ?? '');
            $summary[$year][$type] = ($summary[$year][$type] ?? 0) + 1;
        }

        krsort($summary);
        foreach ($summary as &$types) {
            ksort($types);
        }

        return $summary;
    }

    public function getRecentJobs(int $limit = 25): array
    {
        $filters = $this->getQueueFilters();
        $query = GoogleDriveQueue::table()
            ->orderBy('updated_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($limit);

        if ($filters['company'] > 0) {
            $query->whereEq('idempresa', $filters['company']);
        }

        if ($filters['state'] !== '') {
            $query->whereEq('state', $filters['state']);
        }

        $jobs = [];
        foreach ($query->get() as $row) {
            $payload = $this->decodePayload($row['payload'] ?? null);
            $jobs[] = [
                'id' => (int)$row['id'],
                'model' => $row['model'],
                'iddocument' => (int)$row['iddocument'],
                'idempresa' => (int)$row['idempresa'],
                'action' => $row['action'],
                'state' => $row['state'],
                'priority' => (int)$row['priority'],
                'attempts' => (int)$row['attempts'],
                'max_attempts' => (int)$row['max_attempts'],
                'available_at' => $row['available_at'],
                'updated_at' => $row['updated_at'],
                'last_error' => $row['last_error'],
                'google_file_id' => $row['google_file_id'],
                'payload' => $payload,
                'state_class' => $this->stateClass($row['state']),
                'doc_label' => $this->formatJobLabel($row, $payload),
                'duration_ms' => $payload['duration_ms'] ?? null,
            ];
        }

        return $jobs;
    }

    public function getQueueStates(): array
    {
        return ['' => 'all', 'queued' => 'queued', 'running' => 'running', 'done' => 'done', 'failed' => 'failed'];
    }

    public function getAvailableModels(): array
    {
        $models = array_fill_keys(self::DEFAULT_MODELS, true);

        foreach (GoogleDriveFileMap::table()->selectRaw('DISTINCT model')->orderBy('model', 'ASC')->get() as $row) {
            if (!empty($row['model'])) {
                $models[$row['model']] = true;
            }
        }

        foreach (GoogleDriveQueue::table()->selectRaw('DISTINCT model')->orderBy('model', 'ASC')->get() as $row) {
            if (!empty($row['model'])) {
                $models[$row['model']] = true;
            }
        }

        $result = array_keys($models);
        sort($result);
        return $result;
    }

    public function getExercises(): array
    {
        $options = [];
        $model = new Ejercicio();
        foreach ($model->all([], ['codejercicio' => 'DESC']) as $exercise) {
            $options[$exercise->codejercicio] = $exercise->codejercicio . ' - ' . $exercise->nombre;
        }

        return $options;
    }

    protected function createViews()
    {
        $this->setTabsPosition('top');
        $this->addHtmlView('Dashboard', 'googledrive_sync/panel', 'GoogleDriveQueue', 'dashboard', 'fa-solid fa-chart-line');
        $this->addHtmlView('Config', 'googledrive_sync/config', 'GoogleDriveCompanyConfig', 'configuration', 'fa-solid fa-sliders');
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'retry-job':
                $this->retryJobAction();
                return true;

            case 'retry-selected':
                $this->retrySelectedAction();
                return true;

            case 'retry-failed':
                $this->retryFailedAction();
                return true;

            case 'enqueue-bulk':
                $this->enqueueBulkAction();
                return true;

            case 'save-config':
                $this->saveConfigAction();
                return true;
        }

        return parent::execPreviousAction($action);
    }

    private function retryJobAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $jobId = (int)$this->request->request->get('job_id', 0);
        if ($jobId <= 0) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $job = GoogleDriveQueue::find($jobId);
        if (!$job instanceof GoogleDriveQueue) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $job->state = 'queued';
        $job->available_at = Tools::dateTime();
        $job->locked_at = null;
        $job->last_error = null;
        $job->save();

        Tools::log()->notice('record-updated-correctly');
    }

    private function retrySelectedAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $jobIds = $this->request->request->getArray('job_ids') ?? [];
        if (empty($jobIds)) {
            Tools::log()->warning('no-data');
            return;
        }

        $updated = 0;
        foreach ($jobIds as $jobId) {
            $id = (int)$jobId;
            if ($id <= 0) {
                continue;
            }

            $job = GoogleDriveQueue::find($id);
            if (!$job instanceof GoogleDriveQueue) {
                continue;
            }

            $job->state = 'queued';
            $job->available_at = Tools::dateTime();
            $job->locked_at = null;
            $job->last_error = null;
            if ($job->save()) {
                $updated++;
            }
        }

        if ($updated > 0) {
            Tools::log()->notice('record-updated-correctly');
        } else {
            Tools::log()->warning('no-data');
        }
    }

    private function retryFailedAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $filters = $this->getQueueFilters();

        $batchSize = 200;
        $updated = 0;
        $lastId = 0;

        do {
            $query = GoogleDriveQueue::table()
                ->whereEq('state', 'failed')
                ->orderBy('id', 'ASC')
                ->limit($batchSize);

            if ($filters['company'] > 0) {
                $query->whereEq('idempresa', $filters['company']);
            }

            if ($lastId > 0) {
                $query->whereGt('id', $lastId);
            }

            $rows = $query->get();
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = max($lastId, (int)($row['id'] ?? 0));

                $job = new GoogleDriveQueue($row);
                $job->state = 'queued';
                $job->available_at = Tools::dateTime();
                $job->locked_at = null;
                $job->last_error = null;
                if ($job->save()) {
                    $updated++;
                }
            }
        } while (count($rows) === $batchSize);

        if ($updated <= 0) {
            Tools::log()->warning('no-data');
            return;
        }

        Tools::log()->notice('record-updated-correctly');
    }

    private function enqueueBulkAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $modelName = (string)$this->request->request->get('bulk_model', '');
        if ($modelName === '') {
            Tools::log()->warning('no-data');
            return;
        }

        $className = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName;
        if (!class_exists($className)) {
            Tools::log()->warning('model-not-found');
            return;
        }

        $companyId = (int)$this->request->request->get('bulk_company', 0);
        $limit = max(1, min(500, (int)$this->request->request->get('bulk_limit', 100)));
        $where = [];

        if ($companyId > 0) {
            $where[] = Where::eq('idempresa', $companyId);
        }

        $exercise = (string)$this->request->request->get('bulk_exercise', '');
        if ($exercise !== '') {
            $where[] = Where::eq('codejercicio', $exercise);
        }

        $fromDate = (string)$this->request->request->get('bulk_from', '');
        if ($fromDate !== '') {
            $where[] = Where::gte('fecha', $fromDate);
        }

        $toDate = (string)$this->request->request->get('bulk_to', '');
        if ($toDate !== '') {
            $where[] = Where::lte('fecha', $toDate);
        }

        $third = trim((string)$this->request->request->get('bulk_third', ''));
        if ($third !== '') {
            $pattern = '%' . $third . '%';
            $where[] = Where::like('cifnif|nombrecliente|nombreproveedor|razonsocial', $pattern);
        }

        /** @var array<int, \FacturaScripts\Core\Model\Base\BusinessDocument> $documents */
        $documents = $className::all($where, ['fecha' => 'ASC'], 0, $limit);
        if (empty($documents)) {
            Tools::log()->warning('no-data');
            return;
        }

        $queued = 0;
        foreach ($documents as $document) {
            try {
                $map = $document->googleDriveFileMap(true);
            } catch (BadMethodCallException $exception) {
                continue;
            }

            if (!$map instanceof GoogleDriveFileMap) {
                continue;
            }

            $map->sync_status = 'queued';
            $map->sync_error = null;
            $map->content_hash = HashUtil::fromDocument($document);
            $map->save();

            if (SyncWorker::enqueueDocument($document, 'sync', [
                'map_id' => $map->id,
                'content_hash' => $map->content_hash,
            ])) {
                $queued++;
            }
        }

        if ($queued > 0) {
            Tools::log()->notice('record-updated-correctly');
        } else {
            Tools::log()->warning('no-data');
        }
    }

    private function saveConfigAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $companyId = (int)$this->request->request->get('company_id', 0);
        if ($companyId <= 0) {
            Tools::log()->warning('no-data');
            return;
        }

        $config = GoogleDriveCompanyConfig::forCompany($companyId);
        $config->idempresa = $companyId;
        $data = [
            'credentials_mode' => (string)$this->request->request->get('credentials_mode', 'service'),
            'credentials_json' => trim((string)$this->request->request->get('credentials_json', '')),
            'shared_drive_id' => trim((string)$this->request->request->get('shared_drive_id', '')),
            'root_folder_id' => trim((string)$this->request->request->get('root_folder_id', '')),
            'root_folder_path' => trim((string)$this->request->request->get('root_folder_path', '')),
            'path_template' => (string)$this->request->request->get('path_template', ''),
            'filename_template' => (string)$this->request->request->get('filename_template', ''),
            'upload_pdf' => $this->request->request->has('upload_pdf'),
            'auto_share' => $this->request->request->has('auto_share'),
            'share_emails' => trim((string)$this->request->request->get('share_emails', '')),
            'delete_on_remove' => $this->request->request->has('delete_on_remove'),
            'reverse_sync' => $this->request->request->has('reverse_sync'),
            'anonymize_routes' => $this->request->request->has('anonymize_routes'),
        ];

        try {
            $config->loadFromData($data);
        } catch (JsonException $exception) {
            Tools::log()->error('record-save-error');
            return;
        }

        if ($config->save()) {
            Tools::log()->notice('record-updated-correctly');
        } else {
            Tools::log()->error('record-save-error');
        }
    }

    private function formatJobLabel(array $row, array $payload): string
    {
        $identifier = $payload['doc_identifier'] ?? '';
        if ($identifier === '') {
            $identifier = trim(($payload['doc_serie'] ?? '') . '-' . ($payload['doc_number'] ?? ''));
        }
        if ($identifier === '' && !empty($row['iddocument'])) {
            $identifier = $row['model'] . ' #' . $row['iddocument'];
        }

        $third = $payload['third_name'] ?? '';
        if ($third !== '' && !empty($payload['third_nif'])) {
            $third = $payload['third_nif'] . ' · ' . $third;
        } elseif (!empty($payload['third_nif'])) {
            $third = (string)$payload['third_nif'];
        }

        if ($third !== '') {
            return $identifier . ' — ' . $third;
        }

        return $identifier;
    }

    private function stateClass(?string $state): string
    {
        return match ($state) {
            'done' => 'table-success',
            'running' => 'table-info',
            'failed' => 'table-danger',
            'queued' => 'table-warning',
            default => '',
        };
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
}
