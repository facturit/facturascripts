<?php

namespace FacturaScripts\Plugins\googledrive_sync\Lib;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveCompanyConfig;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveFileMap;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveFoldersMap;

/**
 * Lightweight wrapper that simulates the interaction with the Google Drive API
 * while persisting folder cache information and file metadata. The goal is to
 * keep the synchronisation flow testable without hitting the real API.
 */
class GoogleDriveClient
{
    private GoogleDriveCompanyConfig $config;

    public function __construct(GoogleDriveCompanyConfig $config)
    {
        $this->config = $config;
    }

    public function ensureFolder(int $companyId, string $path): string
    {
        $path = trim($path, '/');
        if ($path === '') {
            return $this->config->root_folder_id ?: 'root';
        }

        $segments = explode('/', $path);
        $current = '';
        $parentId = $this->config->root_folder_id ?: 'root';

        foreach ($segments as $segment) {
            $current = $current === '' ? $segment : $current . '/' . $segment;
            $folderId = $this->findFolderId($companyId, $current);
            if ($folderId === null) {
                $folderId = $this->createFolder($companyId, $current, $parentId);
            }
            $parentId = $folderId;
        }

        return $parentId;
    }

    /**
     * @param string[] $shareWith
     */
    public function upload(GoogleDriveFileMap $map, string $folderId, string $filename, string $content, array $shareWith = []): array
    {
        $fileId = $map->google_file_id ?: $this->generateFileId($map, $filename);
        $webLink = 'https://drive.google.com/file/d/' . $fileId . '/view';

        $map->google_file_id = $fileId;
        $map->google_folder_id = $folderId;
        $map->google_web_link = $webLink;
        $map->filename = $filename;
        $map->last_synced_at = Tools::dateTime();
        $map->sync_status = 'done';
        $map->sync_error = null;
        $map->save();

        $shared = $this->shareFile($fileId, $shareWith);

        return [
            'file_id' => $fileId,
            'web_link' => $webLink,
            'shared_with' => $shared,
        ];
    }

    public function moveToTrash(GoogleDriveFileMap $map): void
    {
        if (empty($map->google_file_id)) {
            return;
        }

        $map->sync_status = 'done';
        $map->sync_error = null;
        $map->google_web_link = null;
        $map->last_synced_at = Tools::dateTime();
        $map->save();
    }

    /**
     * @param string[] $emails
     * @return string[]
     */
    private function shareFile(string $fileId, array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        $recipients = [];
        foreach ($emails as $email) {
            $valid = filter_var(trim((string)$email), FILTER_VALIDATE_EMAIL);
            if ($valid === false) {
                continue;
            }

            $recipients[strtolower($valid)] = strtolower($valid);
        }

        // In this stub client we simply return the list of recipients to
        // simulate successful sharing with read-only permissions.
        return array_values($recipients);
    }

    private function findFolderId(int $companyId, string $path): ?string
    {
        $hash = hash('sha256', $companyId . '|' . strtolower($path));
        $where = [
            Where::eq('path_hash', $hash),
            Where::eq('idempresa', $companyId),
        ];

        $folder = GoogleDriveFoldersMap::findWhere($where);
        return $folder?->folder_id;
    }

    private function createFolder(int $companyId, string $path, string $parentId): string
    {
        $folderId = 'fld_' . substr(hash('sha256', $parentId . '/' . $path), 0, 16);

        $map = new GoogleDriveFoldersMap();
        $map->idempresa = $companyId;
        $map->resolved_path = $path;
        $map->folder_id = $folderId;
        $map->save();

        return $folderId;
    }

    private function generateFileId(GoogleDriveFileMap $map, string $filename): string
    {
        $seed = $map->model . '|' . $map->iddocument . '|' . $filename . '|' . microtime(true);
        return 'file_' . substr(hash('sha256', $seed), 0, 24);
    }
}
