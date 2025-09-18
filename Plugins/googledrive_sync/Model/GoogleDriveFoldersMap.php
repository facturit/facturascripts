<?php

namespace FacturaScripts\Plugins\googledrive_sync\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * Cache of Google Drive folder identifiers resolved from template paths.
 */
class GoogleDriveFoldersMap extends ModelClass
{
    use ModelTrait;

    /** @var int|null */
    public $id;

    /** @var int|null */
    public $idempresa;

    /** @var string */
    public $resolved_path;

    /** @var string */
    public $path_hash;

    /** @var string */
    public $folder_id;

    /** @var string|null */
    public $created_at;

    /** @var string|null */
    public $updated_at;

    public function clear(): void
    {
        parent::clear();
        $this->path_hash = '';
    }

    public function test(): bool
    {
        $this->resolved_path = trim((string)$this->resolved_path);
        $this->folder_id = Tools::noHtml($this->folder_id);

        $hashSource = (string)$this->idempresa . '|' . strtolower($this->resolved_path);
        $this->path_hash = hash('sha256', $hashSource);

        return parent::test();
    }

    public static function tableName(): string
    {
        return 'gd_folders_map';
    }
}
