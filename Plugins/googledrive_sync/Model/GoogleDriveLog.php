<?php

namespace FacturaScripts\Plugins\googledrive_sync\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * Audit log for synchronisation events against Google Drive.
 */
class GoogleDriveLog extends ModelClass
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

    /** @var string */
    public $action;

    /** @var string */
    public $result;

    /** @var string|null */
    public $message;

    /** @var string|null */
    public $google_file_id;

    /** @var int|null */
    public $filesize;

    /** @var string|null */
    public $username;

    /** @var int|null */
    public $duration_ms;

    /** @var string|null */
    public $created_at;

    public function clear(): void
    {
        parent::clear();
        $this->result = 'ok';
        $this->filesize = 0;
        $this->duration_ms = 0;
    }

    public function test(): bool
    {
        $this->model = Tools::noHtml($this->model);
        $this->action = Tools::noHtml($this->action);
        $this->result = strtolower(Tools::noHtml($this->result ?? 'ok'));
        $this->message = Tools::noHtml($this->message);
        $this->google_file_id = Tools::noHtml($this->google_file_id);
        $this->username = Tools::noHtml($this->username);

        $allowedResults = ['ok', 'failed', 'retry', 'skipped'];
        if (!in_array($this->result, $allowedResults, true)) {
            $this->result = 'ok';
        }

        if ($this->iddocument !== null) {
            $this->iddocument = (int)$this->iddocument;
        }

        $this->filesize = $this->filesize === null ? null : max(0, (int)$this->filesize);
        $this->duration_ms = $this->duration_ms === null ? null : max(0, (int)$this->duration_ms);

        return parent::test();
    }

    public static function tableName(): string
    {
        return 'gd_log';
    }
}
