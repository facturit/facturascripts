<?php

namespace FacturaScripts\Plugins\googledrive_sync\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * Queue of pending synchronisation jobs with Google Drive.
 */
class GoogleDriveQueue extends ModelClass
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
    public $state;

    /** @var int */
    public $priority;

    /** @var int */
    public $attempts;

    /** @var int */
    public $max_attempts;

    /** @var string|null */
    public $available_at;

    /** @var string|null */
    public $locked_at;

    /** @var string|null */
    public $last_error;

    /** @var string|null */
    public $google_file_id;

    /** @var string|null */
    public $payload;

    /** @var string|null */
    public $created_at;

    /** @var string|null */
    public $updated_at;

    public function clear(): void
    {
        parent::clear();
        $this->action = 'sync';
        $this->state = 'queued';
        $this->priority = 5;
        $this->attempts = 0;
        $this->max_attempts = 5;
    }

    public function test(): bool
    {
        $this->model = Tools::noHtml($this->model);
        $this->action = strtolower(Tools::noHtml($this->action ?? 'sync'));
        $this->state = strtolower(Tools::noHtml($this->state ?? 'queued'));
        $this->google_file_id = Tools::noHtml($this->google_file_id);
        $this->last_error = Tools::noHtml($this->last_error);

        if (!in_array($this->action, ['sync', 'delete', 'share', 'metadata'], true)) {
            $this->action = 'sync';
        }

        $allowedStates = ['queued', 'running', 'done', 'failed', 'scheduled'];
        if (!in_array($this->state, $allowedStates, true)) {
            $this->state = 'queued';
        }

        $this->priority = min(9, max(1, (int)$this->priority));
        $this->attempts = max(0, (int)$this->attempts);
        $this->max_attempts = max(1, (int)$this->max_attempts);

        if ($this->iddocument !== null) {
            $this->iddocument = (int)$this->iddocument;
        }

        return parent::test();
    }

    public static function tableName(): string
    {
        return 'gd_queue';
    }
}
