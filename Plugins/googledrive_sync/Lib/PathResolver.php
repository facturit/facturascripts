<?php

namespace FacturaScripts\Plugins\googledrive_sync\Lib;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveCompanyConfig;

/**
 * Resolves Drive folder paths and filenames based on user configurable
 * templates and document data.
 */
class PathResolver
{
    private GoogleDriveCompanyConfig $config;

    /** @var array<int, string> */
    private array $companyNameCache = [];

    public function __construct(GoogleDriveCompanyConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @return array{path: string, segments: string[], filename: string}
     */
    public function resolve(BusinessDocument $document): array
    {
        $pathTemplate = $this->config->path_template ?: '{year}/{doctype}/{third.nif} - {third.name}';
        $fileTemplate = $this->config->filename_template ?: '{date:YYYYMMDD}-{doctype}-{doc.serie}-{doc.number}-{third.nif}-{third.name}.pdf';

        $resolvedPath = $this->applyTemplate($pathTemplate, $document, true);
        $segments = array_values(array_filter(explode('/', $resolvedPath)));

        $filename = $this->applyTemplate($fileTemplate, $document, false);
        $filename = $this->enforceExtension($filename, '.pdf');

        return [
            'path' => implode('/', $segments),
            'segments' => $segments,
            'filename' => $filename,
        ];
    }

    private function applyTemplate(string $template, BusinessDocument $document, bool $isPath): string
    {
        $template = $this->replaceDates($template, $document);

        $replacements = [
            '{year}' => $this->resolveYear($document),
            '{doctype}' => $this->resolveDoctype($document),
            '{third.nif}' => $this->sanitize((string)($document->cifnif ?? ''), $isPath),
            '{third.name}' => $this->resolveThirdName($document, $isPath),
            '{doc.number}' => $this->sanitize((string)($document->numero ?? ''), $isPath),
            '{doc.serie}' => $this->sanitize((string)($document->codserie ?? ''), $isPath),
            '{company.name}' => $this->resolveCompanyName($document, $isPath),
        ];

        foreach ($replacements as $placeholder => $value) {
            $template = str_replace($placeholder, $value, $template);
        }

        // Remove unresolved placeholders gracefully
        $template = preg_replace('/\{[^}]+\}/', '', $template) ?? $template;

        if ($isPath) {
            $segments = array_map(fn (string $segment) => $this->sanitize($segment, true), explode('/', $template));
            $segments = array_filter($segments, fn (string $segment) => $segment !== '');
            return implode('/', $segments);
        }

        return $this->sanitize($template, false);
    }

    private function replaceDates(string $template, BusinessDocument $document): string
    {
        $date = $document->fecha ?: Tools::date();
        $timestamp = strtotime($date) ?: time();

        return preg_replace_callback('/\{date(?::([^}]+))?\}/i', function (array $matches) use ($timestamp) {
            $format = $matches[1] ?? 'Y-m-d';
            $format = str_replace(['YYYY', 'YY', 'MM', 'DD'], ['Y', 'y', 'm', 'd'], $format);
            $format = str_replace(['hh', 'HH', 'mm', 'ss'], ['H', 'H', 'i', 's'], $format);
            return date($format, $timestamp);
        }, $template) ?? $template;
    }

    private function resolveYear(BusinessDocument $document): string
    {
        $date = $document->fecha ?: Tools::date();
        $timestamp = strtotime($date) ?: time();
        return date('Y', $timestamp);
    }

    private function resolveDoctype(BusinessDocument $document): string
    {
        $map = [
            'facturacliente' => 'factura',
            'facturaproveedor' => 'factura',
            'albarancliente' => 'albaran',
            'albaranproveedor' => 'albaran',
            'pedidocliente' => 'pedido',
            'pedidoproveedor' => 'pedido',
            'presupuestocliente' => 'presupuesto',
            'presupuestoproveedor' => 'presupuesto',
            'abonoscliente' => 'abono',
            'abonosproveedor' => 'abono',
        ];

        $key = strtolower($document->modelClassName());
        $label = $map[$key] ?? $key;
        return Tools::slug($label, '-', 0);
    }

    private function resolveThirdName(BusinessDocument $document, bool $isPath): string
    {
        if ($this->config->anonymize_routes) {
            return $this->sanitize((string)($document->cifnif ?? ''), $isPath);
        }

        $name = '';
        if (!empty($document->nombrecliente)) {
            $name = (string)$document->nombrecliente;
        } elseif (!empty($document->nombre)) {
            $name = (string)$document->nombre;
        } elseif (!empty($document->subjectColumnValue())) {
            $name = (string)$document->subjectColumnValue();
        }

        return $this->sanitize($name, $isPath);
    }

    private function resolveCompanyName(BusinessDocument $document, bool $isPath): string
    {
        $companyId = (int)($document->idempresa ?? 0);
        if ($companyId <= 0) {
            return $this->sanitize('empresa', $isPath);
        }

        if (!isset($this->companyNameCache[$companyId])) {
            $empresa = new Empresa();
            $name = $empresa->load($companyId) ? (string)$empresa->nombre : 'empresa';
            $this->companyNameCache[$companyId] = $name;
        }

        return $this->sanitize($this->companyNameCache[$companyId], $isPath);
    }

    private function enforceExtension(string $filename, string $extension): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            $filename = 'document';
        }

        if (!str_ends_with(strtolower($filename), strtolower($extension))) {
            $filename .= $extension;
        }

        return mb_substr($filename, 0, 250);
    }

    private function sanitize(string $value, bool $isPath): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = Tools::ascii($value);
        $value = str_replace(['\\', '/'], '-');
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        $pattern = $isPath ? '/[^A-Za-z0-9 _.,-]/' : '/[^A-Za-z0-9_. -]/';
        $value = preg_replace($pattern, '', $value) ?? '';

        return mb_substr($value, 0, 250);
    }
}
