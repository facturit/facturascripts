<?php

namespace FacturaScripts\Plugins\googledrive_sync\Lib;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveCompanyConfig;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveFileMap;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveFoldersMap;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Google\Service\Exception as GoogleServiceException;
use RuntimeException;

/**
 * Production-grade client that integrates FacturaScripts with Google Drive
 * using the official API, supporting both service accounts and OAuth 2.0
 * credentials with automatic token persistence.
 */
class GoogleDriveClient
{
    private GoogleDriveCompanyConfig $config;

    private GoogleClient $client;

    private Drive $driveService;

    public function __construct(GoogleDriveCompanyConfig $config, ?Drive $driveService = null, ?GoogleClient $client = null)
    {
        $this->config = $config;

        if ($client === null) {
            $client = $this->buildClient();
        }

        $this->client = $client;
        $this->driveService = $driveService ?? new Drive($client);

        $this->persistOauthToken($client);
    }

    public function ensureFolder(int $companyId, string $path): string
    {
        $path = trim($path, '/');
        $segments = $path === '' ? [] : array_values(array_filter(explode('/', $path)));

        $prefix = trim((string)$this->config->root_folder_path, '/');
        if ($prefix !== '') {
            $segments = array_merge(array_values(array_filter(explode('/', $prefix))), $segments);
        }

        $parentId = $this->resolveBaseParent();
        $currentPath = '';

        foreach ($segments as $segment) {
            $sanitised = $this->sanitiseSegment($segment);
            if ($sanitised === '') {
                continue;
            }

            $currentPath = $currentPath === '' ? $sanitised : $currentPath . '/' . $sanitised;

            $folderId = $this->findFolderId($companyId, $currentPath);
            if ($folderId === null) {
                $folderId = $this->findFolderInDrive($sanitised, $parentId);
            }

            if ($folderId === null) {
                $folderId = $this->createFolder($companyId, $currentPath, $parentId, $sanitised);
            } else {
                $this->persistFolderMap($companyId, $currentPath, $folderId);
            }

            $parentId = $folderId;
        }

        return $parentId;
    }

    /**
     * @param string[] $shareWith
     */
    public function upload(GoogleDriveFileMap $map, string $folderId, string $filename, string $content, string $mimeType, array $shareWith = []): array
    {
        $filename = $this->sanitiseFilename($filename);

        $fileMetadata = new DriveFile([
            'name' => $filename,
        ]);

        $options = [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'supportsAllDrives' => true,
            'fields' => 'id, webViewLink, parents',
        ];

        if ($map->google_file_id) {
            if (!empty($folderId) && $map->google_folder_id !== $folderId) {
                $options['addParents'] = $folderId;
                if (!empty($map->google_folder_id)) {
                    $options['removeParents'] = $map->google_folder_id;
                }
            }
        } elseif (!empty($folderId)) {
            $fileMetadata->setParents([$folderId]);
        }

        try {
            if ($map->google_file_id) {
                $file = $this->driveService->files->update($map->google_file_id, $fileMetadata, $options);
            } else {
                $file = $this->driveService->files->create($fileMetadata, $options);
            }
        } catch (GoogleServiceException $exception) {
            throw new RuntimeException('drive-upload-failed: ' . $exception->getMessage(), 0, $exception);
        }

        $fileId = $file->getId();
        if (empty($fileId)) {
            throw new RuntimeException('drive-upload-missing-id');
        }

        $webLink = $file->getWebViewLink();
        if (empty($webLink)) {
            try {
                $details = $this->driveService->files->get($fileId, [
                    'supportsAllDrives' => true,
                    'fields' => 'webViewLink, parents',
                ]);
                $webLink = $details->getWebViewLink();
                if ($file->getParents() === null) {
                    $file->setParents($details->getParents());
                }
            } catch (GoogleServiceException $exception) {
                Tools::log()->warning('googledrive-fetch-weblink-error', ['%error%' => $exception->getMessage()]);
            }
        }

        $parents = $file->getParents();
        if (!empty($parents)) {
            $folderId = $parents[0];
        }

        $map->google_file_id = $fileId;
        $map->google_folder_id = $folderId;
        $map->google_web_link = $webLink;
        $map->filename = $filename;
        $map->last_synced_at = Tools::dateTime();
        $map->sync_status = 'done';
        $map->sync_error = null;
        $map->save();

        $shared = $this->shareFile($fileId, $shareWith);

        $this->persistOauthToken($this->client);

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

        $driveFile = new DriveFile();
        $driveFile->setTrashed(true);

        try {
            $this->driveService->files->update($map->google_file_id, $driveFile, [
                'supportsAllDrives' => true,
            ]);
        } catch (GoogleServiceException $exception) {
            throw new RuntimeException('drive-trash-failed: ' . $exception->getMessage(), 0, $exception);
        }

        $map->sync_status = 'done';
        $map->sync_error = null;
        $map->google_web_link = null;
        $map->last_synced_at = Tools::dateTime();
        $map->save();

        $this->persistOauthToken($this->client);
    }

    private function buildClient(): GoogleClient
    {
        $credentials = $this->config->getCredentialsArray();
        if (empty($credentials)) {
            throw new RuntimeException('missing-credentials');
        }

        $client = new GoogleClient();
        $client->setApplicationName('FacturaScripts Google Drive Sync');
        $client->setScopes([Drive::DRIVE]);
        $client->setAccessType('offline');
        $client->setPrompt('none');

        if ('service' === $this->config->credentials_mode) {
            $client->setAuthConfig($credentials);
            if (!empty($credentials['subject'])) {
                $client->setSubject($credentials['subject']);
            }
            $client->refreshTokenWithAssertion();
            return $client;
        }

        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            throw new RuntimeException('invalid-oauth-credentials');
        }

        $client->setClientId($credentials['client_id']);
        $client->setClientSecret($credentials['client_secret']);
        if (!empty($credentials['redirect_uri'])) {
            $client->setRedirectUri($credentials['redirect_uri']);
        }

        $token = $this->config->getTokenArray();
        if (!empty($token)) {
            $client->setAccessToken($token);
        }

        $refreshToken = $this->config->getRefreshToken();
        if ($refreshToken !== null) {
            $client->setRefreshToken($refreshToken);
        }

        if ($client->isAccessTokenExpired()) {
            $this->refreshAccessToken($client, $refreshToken);
        }

        return $client;
    }

    private function refreshAccessToken(GoogleClient $client, ?string $refreshToken): void
    {
        if ('service' === $this->config->credentials_mode) {
            $client->refreshTokenWithAssertion();
            return;
        }

        if ($refreshToken === null) {
            throw new RuntimeException('missing-refresh-token');
        }

        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (isset($token['error'])) {
            throw new RuntimeException('oauth-refresh-failed: ' . $token['error']);
        }

        if (!isset($token['refresh_token'])) {
            $token['refresh_token'] = $refreshToken;
        }

        $client->setAccessToken($token);
        $this->config->setTokenArray($token);
        $this->config->save();
    }

    private function findFolderId(int $companyId, string $path): ?string
    {
        $hash = hash('sha256', $companyId . '|' . strtolower($path));
        $where = [
            Where::eq('path_hash', $hash),
            Where::eq('idempresa', $companyId),
        ];

        $map = GoogleDriveFoldersMap::findWhere($where);
        return $map?->folder_id;
    }

    private function persistFolderMap(int $companyId, string $path, string $folderId): void
    {
        $hash = hash('sha256', $companyId . '|' . strtolower($path));
        $where = [
            Where::eq('path_hash', $hash),
            Where::eq('idempresa', $companyId),
        ];

        $map = GoogleDriveFoldersMap::findWhere($where);
        if (!$map instanceof GoogleDriveFoldersMap) {
            $map = new GoogleDriveFoldersMap();
            $map->idempresa = $companyId;
            $map->resolved_path = $path;
        }

        $map->folder_id = $folderId;
        $map->save();
    }

    private function findFolderInDrive(string $name, string $parentId): ?string
    {
        $params = $this->baseListParams();
        $query = [
            "name = '" . $this->escapeQueryValue($name) . "'",
            "mimeType = 'application/vnd.google-apps.folder'",
            'trashed = false',
        ];

        if ($parentId !== '' && $parentId !== 'root') {
            $query[] = "'" . $this->escapeQueryValue($parentId) . "' in parents";
        } elseif (empty($this->config->shared_drive_id)) {
            $query[] = "'root' in parents";
        }

        $params['q'] = implode(' and ', $query);
        $params['pageSize'] = 1;
        $params['fields'] = 'files(id, name)';

        try {
            $result = $this->driveService->files->listFiles($params);
        } catch (GoogleServiceException $exception) {
            Tools::log()->warning('googledrive-folder-query-error', ['%error%' => $exception->getMessage()]);
            return null;
        }

        $files = $result->getFiles();
        if (empty($files)) {
            return null;
        }

        return $files[0]->getId();
    }

    private function createFolder(int $companyId, string $path, string $parentId, string $name): string
    {
        $metadata = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if ($parentId !== '') {
            $metadata->setParents([$parentId]);
        }

        $options = [
            'supportsAllDrives' => true,
            'fields' => 'id',
        ];

        try {
            $folder = $this->driveService->files->create($metadata, $options);
        } catch (GoogleServiceException $exception) {
            throw new RuntimeException('folder-create-failed: ' . $exception->getMessage(), 0, $exception);
        }

        $folderId = $folder->getId();
        if (empty($folderId)) {
            throw new RuntimeException('folder-create-missing-id');
        }

        $this->persistFolderMap($companyId, $path, $folderId);

        return $folderId;
    }

    /**
     * @param string[] $emails
     * @return string[]
     */
    private function shareFile(string $fileId, array $emails): array
    {
        $recipients = $this->filterRecipients($emails);
        if (empty($recipients)) {
            return [];
        }

        $granted = [];
        foreach ($recipients as $email) {
            try {
                $permission = new Permission();
                $permission->setType('user');
                $permission->setRole('reader');
                $permission->setEmailAddress($email);

                $this->driveService->permissions->create($fileId, $permission, [
                    'supportsAllDrives' => true,
                    'sendNotificationEmail' => false,
                ]);

                $granted[] = $email;
            } catch (GoogleServiceException $exception) {
                Tools::log()->warning('googledrive-share-error', ['%email%' => $email, '%error%' => $exception->getMessage()]);
            }
        }

        return $granted;
    }

    /**
     * @param string[] $emails
     * @return string[]
     */
    private function filterRecipients(array $emails): array
    {
        $filtered = [];
        foreach ($emails as $email) {
            $sanitised = filter_var(trim((string)$email), FILTER_VALIDATE_EMAIL);
            if ($sanitised === false) {
                continue;
            }

            $filtered[strtolower($sanitised)] = strtolower($sanitised);
        }

        return array_values($filtered);
    }

    private function persistOauthToken(GoogleClient $client): void
    {
        if ('oauth' !== $this->config->credentials_mode) {
            return;
        }

        $token = $client->getAccessToken();
        if (empty($token)) {
            return;
        }

        if (!isset($token['refresh_token'])) {
            $refresh = $this->config->getRefreshToken();
            if ($refresh !== null) {
                $token['refresh_token'] = $refresh;
            }
        }

        $current = $this->config->getTokenArray();
        if ($current === $token) {
            return;
        }

        $this->config->setTokenArray($token);
        $this->config->save();
    }

    private function baseListParams(): array
    {
        $params = [
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ];

        if (!empty($this->config->shared_drive_id)) {
            $params['corpora'] = 'drive';
            $params['driveId'] = $this->config->shared_drive_id;
        } else {
            $params['corpora'] = 'user';
            $params['spaces'] = 'drive';
        }

        return $params;
    }

    private function resolveBaseParent(): string
    {
        if (!empty($this->config->root_folder_id)) {
            return $this->config->root_folder_id;
        }

        if (!empty($this->config->shared_drive_id)) {
            return $this->config->shared_drive_id;
        }

        return 'root';
    }

    private function sanitiseSegment(string $segment): string
    {
        $segment = trim($segment);
        $segment = str_replace(['\\', '/'], '-');
        return mb_substr($segment, 0, 250);
    }

    private function sanitiseFilename(string $filename): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            $filename = 'document.pdf';
        }

        $filename = str_replace(['\\', '/'], '-');
        return mb_substr($filename, 0, 250);
    }

    private function escapeQueryValue(string $value): string
    {
        return str_replace("'", "\\'", $value);
    }
}

