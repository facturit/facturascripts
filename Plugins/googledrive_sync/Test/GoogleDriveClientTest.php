<?php

namespace FacturaScripts\Plugins\googledrive_sync\Test;

use FacturaScripts\Plugins\googledrive_sync\Lib\GoogleDriveClient;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveCompanyConfig;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveFileMap;
use PHPUnit\Framework\TestCase;

final class GoogleDriveClientTest extends TestCase
{
    public function testUploadStoresMetadataAndSharesWithValidRecipients(): void
    {
        $config = $this->createConfig();
        $client = new GoogleDriveClient($config);

        $map = new class extends GoogleDriveFileMap {
            public bool $saved = false;

            public function __construct()
            {
                // avoid parent constructor
            }

            public function save(...$args): bool
            {
                $this->saved = true;
                return true;
            }
        };

        $map->model = 'FacturaCliente';
        $map->iddocument = 10;

        $result = $client->upload($map, 'folder-123', 'document.pdf', '%PDF%', [
            'USER@example.com',
            'invalid-email',
            'user@example.com',
        ]);

        $this->assertTrue($map->saved);
        $this->assertNotEmpty($result['file_id']);
        $this->assertSame($result['file_id'], $map->google_file_id);
        $this->assertSame('folder-123', $map->google_folder_id);
        $this->assertSame('document.pdf', $map->filename);
        $this->assertSame('done', $map->sync_status);
        $this->assertNull($map->sync_error);
        $this->assertSame(['user@example.com'], $result['shared_with']);
        $this->assertSame('https://drive.google.com/file/d/' . $map->google_file_id . '/view', $result['web_link']);
    }

    public function testUploadKeepsExistingFileId(): void
    {
        $config = $this->createConfig();
        $client = new GoogleDriveClient($config);

        $map = new class extends GoogleDriveFileMap {
            public bool $saved = false;

            public function __construct()
            {
                // avoid parent constructor
            }

            public function save(...$args): bool
            {
                $this->saved = true;
                return true;
            }
        };

        $map->model = 'FacturaCliente';
        $map->iddocument = 11;
        $map->google_file_id = 'file_existing';

        $result = $client->upload($map, 'folder-xyz', 'signed.pdf', 'binary', []);

        $this->assertTrue($map->saved);
        $this->assertSame('file_existing', $result['file_id']);
        $this->assertSame('file_existing', $map->google_file_id);
        $this->assertSame('folder-xyz', $map->google_folder_id);
        $this->assertSame('signed.pdf', $map->filename);
    }

    private function createConfig(): GoogleDriveCompanyConfig
    {
        return new class extends GoogleDriveCompanyConfig {
            public function __construct()
            {
                // bypass parent constructor to avoid database requirements
            }
        };
    }
}
