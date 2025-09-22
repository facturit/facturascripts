<?php

namespace FacturaScripts\Plugins\googledrive_sync\Test;

use PHPUnit\Framework\TestCase;

final class GoogleDriveClientTest extends TestCase
{
    public function testClientIntegrationRequiresLiveCredentials(): void
    {
        self::markTestSkipped('Google Drive client integration tests require valid API credentials and are not run in CI.');
    }
}

