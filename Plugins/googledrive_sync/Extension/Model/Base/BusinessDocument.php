<?php

namespace FacturaScripts\Plugins\googledrive_sync\Extension\Model\Base;

use Closure;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\googledrive_sync\Lib\HashUtil;
use FacturaScripts\Plugins\googledrive_sync\Lib\SyncWorker;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveCompanyConfig;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveFileMap;

class BusinessDocument
{
    public function clear(): Closure
    {
        return function (): bool {
            $this->google_file_id = null;
            $this->google_web_link = null;
            $this->google_folder_id = null;
            $this->content_hash = null;
            $this->sync_status = 'queued';
            $this->sync_error = null;
            $this->_googleDriveFileMap = null;
            return true;
        };
    }

    public function reload(): Closure
    {
        return function (): bool {
            $map = $this->googleDriveFileMap();
            \FacturaScripts\Plugins\googledrive_sync\Extension\Model\Base\BusinessDocument::applyDriveMap($this, $map);
            return true;
        };
    }

    public function googleDriveFileMap(): Closure
    {
        return function (bool $create = false): ?GoogleDriveFileMap {
            if (isset($this->_googleDriveFileMap) && $this->_googleDriveFileMap instanceof GoogleDriveFileMap) {
                return $this->_googleDriveFileMap;
            }

            $map = \FacturaScripts\Plugins\googledrive_sync\Extension\Model\Base\BusinessDocument::loadMapForDocument($this, $create);
            $this->_googleDriveFileMap = $map;
            return $map;
        };
    }

    public function saveBefore(): Closure
    {
        return function (): bool {
            $map = $this->googleDriveFileMap();
            \FacturaScripts\Plugins\googledrive_sync\Extension\Model\Base\BusinessDocument::applyDriveMap($this, $map);
            $this->content_hash = HashUtil::fromDocument($this);
            return true;
        };
    }

    public function save(): Closure
    {
        return function (): bool {
            if (empty($this->id())) {
                return true;
            }

            $map = $this->googleDriveFileMap(true);
            if (!$map instanceof GoogleDriveFileMap) {
                return true;
            }

            $config = GoogleDriveCompanyConfig::forCompany((int)($this->idempresa ?? 0));
            $oldHash = $map->content_hash;

            $map->model = $this->modelClassName();
            $map->iddocument = (int)$this->id();
            $map->idempresa = (int)($this->idempresa ?? 0);
            $map->content_hash = $this->content_hash;

            $needsSync = empty($map->google_file_id) || HashUtil::hasChanged($oldHash, $this->content_hash);

            if (false === $config->isConfigured()) {
                $map->sync_status = 'skipped';
                $map->sync_error = 'not-configured';
                $map->save();
                \FacturaScripts\Plugins\googledrive_sync\Extension\Model\Base\BusinessDocument::applyDriveMap($this, $map);
                return true;
            }

            if ($needsSync) {
                $map->sync_status = 'queued';
                $map->sync_error = null;
                $map->save();

                SyncWorker::enqueueDocument($this, 'sync', [
                    'map_id' => $map->id,
                    'content_hash' => $this->content_hash,
                ]);
            } else {
                $map->sync_status = 'done';
                $map->save();
            }

            \FacturaScripts\Plugins\googledrive_sync\Extension\Model\Base\BusinessDocument::applyDriveMap($this, $map);
            return true;
        };
    }

    public function delete(): Closure
    {
        return function (): bool {
            $map = $this->googleDriveFileMap();
            if (!$map instanceof GoogleDriveFileMap || empty($map->google_file_id)) {
                return true;
            }

            $config = GoogleDriveCompanyConfig::forCompany((int)($this->idempresa ?? 0));
            if (false === $config->isConfigured() || !$config->delete_on_remove) {
                return true;
            }

            $map->sync_status = 'queued';
            $map->sync_error = null;
            $map->save();

            SyncWorker::enqueueDocument($this, 'delete', [
                'map_id' => $map->id,
                'google_file_id' => $map->google_file_id,
            ]);

            return true;
        };
    }

    private static function loadMapForDocument($document, bool $create): ?GoogleDriveFileMap
    {
        if (empty($document->id())) {
            return null;
        }

        $where = [
            Where::eq('model', $document->modelClassName()),
            Where::eq('iddocument', (int)$document->id()),
            Where::eq('idempresa', (int)($document->idempresa ?? 0)),
        ];
        $map = GoogleDriveFileMap::findWhere($where);
        if ($map instanceof GoogleDriveFileMap || false === $create) {
            return $map;
        }

        $map = new GoogleDriveFileMap();
        $map->model = $document->modelClassName();
        $map->iddocument = (int)$document->id();
        $map->idempresa = (int)($document->idempresa ?? 0);
        $map->sync_status = 'queued';
        $map->save();

        return $map;
    }

    private static function applyDriveMap($document, ?GoogleDriveFileMap $map): void
    {
        $document->google_file_id = $map?->google_file_id;
        $document->google_web_link = $map?->google_web_link;
        $document->google_folder_id = $map?->google_folder_id;
        $document->content_hash = $map?->content_hash;
        $document->sync_status = $map?->sync_status ?? 'queued';
        $document->sync_error = $map?->sync_error;
    }
}
