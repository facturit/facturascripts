<?php

namespace FacturaScripts\Plugins\googledrive_sync\Lib;

use FacturaScripts\Core\Model\Base\BusinessDocument;

/**
 * Helper responsible for generating and comparing SHA-256 hashes used during
 * the synchronisation workflow. Hashes are based on either raw binary content
 * or on a normalised representation of the document to detect changes without
 * needing to regenerate files eagerly.
 */
class HashUtil
{
    public static function fromString(string $content): string
    {
        return hash('sha256', $content);
    }

    public static function fromFile(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        return hash_file('sha256', $path) ?: '';
    }

    public static function fromDocument(BusinessDocument $document): string
    {
        $data = $document->toArray();
        ksort($data);

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            $json = serialize($data);
        }

        return self::fromString($json);
    }

    public static function hasChanged(?string $previous, ?string $current): bool
    {
        if (empty($previous)) {
            return true;
        }

        if (empty($current)) {
            return true;
        }

        return !hash_equals($previous, $current);
    }
}
