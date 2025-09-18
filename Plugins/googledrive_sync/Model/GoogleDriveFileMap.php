<?php

namespace FacturaScripts\Plugins\googledrive_sync\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * Stores the Google Drive linkage metadata for printable documents.
 */
class GoogleDriveFileMap extends ModelClass
{
    use ModelTrait;

    /** @var int|null */
    public $id;

    /** @var int|null */
    public $idempresa;

    /** @var string */
    public $model;

    /** @var int|null */
    public $iddocument;

    /** @var string|null */
    public $google_file_id;

    /** @var string|null */
    public $google_folder_id;

    /** @var string|null */
    public $google_web_link;

    /** @var string|null */
    public $filename;

    /** @var string|null */
    public $content_hash;

    /** @var string */
    public $sync_status;

    /** @var string|null */
    public $sync_error;

    /** @var string|null */
    public $last_synced_at;

    /** @var string|null */
    public $created_at;

    /** @var string|null */
    public $updated_at;

    public function clear(): void
    {
        parent::clear();
        $this->sync_status = 'queued';
        $this->iddocument = 0;
    }

    public function test(): bool
    {
        $this->model = Tools::noHtml($this->model);
        $this->google_file_id = Tools::noHtml($this->google_file_id);
        $this->google_folder_id = Tools::noHtml($this->google_folder_id);
        $this->google_web_link = Tools::noHtml($this->google_web_link);
        $this->filename = Tools::noHtml($this->filename);
        $this->content_hash = Tools::noHtml($this->content_hash);

        $this->sync_status = strtolower(Tools::noHtml($this->sync_status ?? 'queued'));
        $allowedStates = ['queued', 'running', 'done', 'failed', 'skipped'];
        if (!in_array($this->sync_status, $allowedStates, true)) {
            $this->sync_status = 'queued';
        }

        if ($this->iddocument !== null) {
            $this->iddocument = (int)$this->iddocument;
        }

        return parent::test();
    }

    public static function tableName(): string
    {
        return 'gd_file_map';
    }
}
