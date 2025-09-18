<?php

namespace FacturaScripts\Plugins\googledrive_sync\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;

/**
 * Configuration model per company for the Google Drive synchronisation plugin.
 */
class GoogleDriveCompanyConfig extends ModelClass
{
    use ModelTrait;

    /** @var int|null */
    public $id;

    /** @var int|null */
    public $idempresa;

    /** @var string */
    public $credentials_mode;

    /** @var string|null */
    public $credentials_json;

    /** @var string|null */
    public $shared_drive_id;

    /** @var string|null */
    public $root_folder_id;

    /** @var string|null */
    public $root_folder_path;

    /** @var string */
    public $path_template;

    /** @var string */
    public $filename_template;

    /** @var bool */
    public $upload_pdf;

    /** @var bool */
    public $auto_share;

    /** @var string|null */
    public $share_emails;

    /** @var bool */
    public $delete_on_remove;

    /** @var bool */
    public $reverse_sync;

    /** @var bool */
    public $anonymize_routes;

    /** @var string|null */
    public $created_at;

    /** @var string|null */
    public $updated_at;

    public function clear(): void
    {
        parent::clear();

        $this->credentials_mode = 'service';
        $this->path_template = '{year}/{doctype}/{third.nif} - {third.name}';
        $this->filename_template = '{date:YYYYMMDD}-{doctype}-{doc.serie}-{doc.number}-{third.nif}-{third.name}.pdf';
        $this->upload_pdf = true;
        $this->auto_share = false;
        $this->delete_on_remove = false;
        $this->reverse_sync = false;
        $this->anonymize_routes = false;
    }

    public function test(): bool
    {
        $this->credentials_mode = Tools::noHtml(strtolower($this->credentials_mode ?? 'service'));
        if (!in_array($this->credentials_mode, ['service', 'oauth'], true)) {
            $this->credentials_mode = 'service';
        }

        $this->shared_drive_id = Tools::noHtml($this->shared_drive_id);
        $this->root_folder_id = Tools::noHtml($this->root_folder_id);
        $this->root_folder_path = Tools::noHtml($this->root_folder_path);
        $this->path_template = trim((string)$this->path_template);
        if ($this->path_template === '') {
            $this->path_template = '{year}/{doctype}/{third.nif} - {third.name}';
        }

        $this->filename_template = trim((string)$this->filename_template);
        if ($this->filename_template === '') {
            $this->filename_template = '{date:YYYYMMDD}-{doctype}-{doc.serie}-{doc.number}-{third.nif}-{third.name}.pdf';
        }

        $this->share_emails = Tools::noHtml($this->share_emails);

        return parent::test();
    }

    public function isConfigured(): bool
    {
        if (!empty($this->credentials_json)) {
            return true;
        }

        return !empty($this->root_folder_id);
    }

    public static function forCompany(int $companyId): self
    {
        $where = [Where::eq('idempresa', $companyId)];
        $config = self::findWhere($where);
        if ($config instanceof self) {
            return $config;
        }

        $config = new self();
        $config->idempresa = $companyId;
        return $config;
    }

    public static function tableName(): string
    {
        return 'gd_company_cfg';
    }
}
