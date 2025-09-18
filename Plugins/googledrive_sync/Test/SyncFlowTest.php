<?php

namespace FacturaScripts\Plugins\googledrive_sync\Test;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Plugins\googledrive_sync\Lib\SyncWorker;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveCompanyConfig;
use PHPUnit\Framework\TestCase;

final class SyncFlowTest extends TestCase
{
    public function testShareRecipientsMergesConfiguredAndSubjectEmails(): void
    {
        $config = $this->createConfig();
        $config->auto_share = true;
        $config->share_emails = 'extra@example.com;duplicated@example.com,EXTRA@example.com';

        $subject = (object) [
            'email' => 'client@example.com',
            'emailfacturacion' => 'billing@example.com',
        ];

        $document = $this->getMockBuilder(BusinessDocument::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['modelClassName', 'getSubject'])
            ->getMockForAbstractClass();

        $document->method('modelClassName')->willReturn('FacturaCliente');
        $document->method('getSubject')->willReturn($subject);
        $document->email = 'document@example.com';
        $document->emailcliente = 'second@example.com';

        $recipients = $config->shareRecipients($document);

        $this->assertEqualsCanonicalizing([
            'extra@example.com',
            'duplicated@example.com',
            'client@example.com',
            'billing@example.com',
            'document@example.com',
            'second@example.com',
        ], $recipients);
    }

    public function testEnqueueDocumentReturnsFalseForUnsavedDocument(): void
    {
        $document = $this->getMockBuilder(BusinessDocument::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['id', 'modelClassName'])
            ->getMockForAbstractClass();

        $document->method('id')->willReturn(0);
        $document->method('modelClassName')->willReturn('FacturaCliente');

        $this->assertFalse(SyncWorker::enqueueDocument($document));
    }

    public function testExcludedModelsAreNormalised(): void
    {
        $config = $this->createConfig();
        $config->setExcludedModels([
            'FacturaCliente',
            '\\FacturaScripts\\Dinamic\\Model\\PedidoCliente',
            'facturacliente',
        ]);

        $this->assertTrue($config->isModelExcluded('FacturaCliente'));
        $this->assertTrue($config->isModelExcluded('\\FacturaScripts\\Dinamic\\Model\\PedidoCliente'));
        $this->assertEqualsCanonicalizing(['FacturaCliente', 'PedidoCliente'], $config->getExcludedModels());
    }

    private function createConfig(): GoogleDriveCompanyConfig
    {
        return new class extends GoogleDriveCompanyConfig {
            public function __construct()
            {
                // bypass parent constructor
            }
        };
    }
}
