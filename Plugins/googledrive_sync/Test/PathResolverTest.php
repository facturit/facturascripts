<?php

namespace FacturaScripts\Plugins\googledrive_sync\Test;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Plugins\googledrive_sync\Lib\PathResolver;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveCompanyConfig;
use PHPUnit\Framework\TestCase;

final class PathResolverTest extends TestCase
{
    public function testResolveUsesDefaultTemplates(): void
    {
        $config = $this->createConfig();
        $config->path_template = '{year}/{doctype}/{third.nif} - {third.name}';
        $config->filename_template = '{date:YYYYMMDD}-{doctype}-{doc.serie}-{doc.number}-{third.nif}-{third.name}.pdf';

        $document = $this->createDocument();
        $resolver = new PathResolver($config);

        $result = $resolver->resolve($document);

        $this->assertSame(['2024', 'factura', 'ES12345678 - Example S.L.'], $result['segments']);
        $this->assertSame('2024/factura/ES12345678 - Example S.L.', $result['path']);
        $this->assertSame('20240510-factura-A-123-ES12345678-Example S.L..pdf', $result['filename']);
    }

    public function testResolveAnonymizesWhenEnabled(): void
    {
        $config = $this->createConfig();
        $config->anonymize_routes = true;

        $resolver = new PathResolver($config);
        $result = $resolver->resolve($this->createDocument());

        $this->assertStringContainsString('ES12345678 - ES12345678', $result['path']);
        $this->assertStringContainsString('ES12345678-ES12345678', str_replace([' ', '/'], '', $result['filename']));
    }

    private function createConfig(): GoogleDriveCompanyConfig
    {
        return new class extends GoogleDriveCompanyConfig {
            public function __construct()
            {
                // bypass parent constructor to avoid database connections in tests
            }
        };
    }

    private function createDocument(): BusinessDocument
    {
        $document = $this->getMockBuilder(BusinessDocument::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['modelClassName', 'getSubject', 'subjectColumnValue'])
            ->getMockForAbstractClass();

        $document->method('modelClassName')->willReturn('FacturaCliente');
        $document->method('getSubject')->willReturn(null);
        $document->method('subjectColumnValue')->willReturn(null);

        $document->fecha = '2024-05-10';
        $document->codserie = 'A';
        $document->numero = '123';
        $document->cifnif = 'ES12345678';
        $document->nombrecliente = 'Example S.L.';
        $document->idempresa = 7;

        return $document;
    }
}
