<?php
/**
 * This file is part of FacturaScripts
 *
 * Plugin to allow embedding FacturaScripts in an iframe from configured domains.
 */

namespace FacturaScripts\Plugins\IframeEmbed;

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;

class Init extends InitClass
{
    public function init(): void
    {
        $origins = $this->getAllowedOrigins();
        if (empty($origins)) {
            return;
        }

        header_register_callback(static function () use ($origins) {
            header_remove('X-Frame-Options');

            $frameAncestors = implode(' ', array_merge(["'self'"], $origins));
            header('Content-Security-Policy: frame-ancestors ' . $frameAncestors, true);
        });
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
    }

    private function getAllowedOrigins(): array
    {
        $configured = trim((string)Tools::config('iframe_allowed_origins', ''));
        if ($configured === '') {
            return [];
        }

        $origins = preg_split('/[\s,;]+/', $configured);
        $validOrigins = [];

        foreach ($origins as $origin) {
            $origin = trim($origin);
            if ($origin === '' || !$this->isValidOrigin($origin)) {
                continue;
            }

            $validOrigins[] = $origin;
        }

        return array_values(array_unique($validOrigins));
    }

    private function isValidOrigin(string $origin): bool
    {
        return (bool)preg_match('/^https?:\/\/[a-z0-9.-]+(?::\d+)?$/i', $origin);
    }
}
