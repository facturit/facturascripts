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
    public $excluded_models;

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
        $this->excluded_models = '';
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
        $this->excluded_models = Tools::noHtml($this->excluded_models);

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

    /**
     * @return string[]
     */
    public function getExcludedModels(): array
    {
        if (empty($this->excluded_models)) {
            return [];
        }

        $raw = preg_split('/[\s,;]+/', (string)$this->excluded_models, -1, PREG_SPLIT_NO_EMPTY);
        if (false === $raw) {
            return [];
        }

        $normalised = [];
        foreach ($raw as $model) {
            $model = trim($model, "\\ ");
            if ($model === '') {
                continue;
            }

            $shortName = strrpos($model, '\\') !== false ? substr($model, strrpos($model, '\\') + 1) : $model;
            $normalised[strtolower($shortName)] = $shortName;
        }

        return array_values($normalised);
    }

    /**
     * @param string[] $models
     */
    public function setExcludedModels(array $models): void
    {
        $normalised = [];
        foreach ($models as $model) {
            $model = trim((string)$model, "\\ ");
            if ($model === '') {
                continue;
            }

            $shortName = strrpos($model, '\\') !== false ? substr($model, strrpos($model, '\\') + 1) : $model;
            $normalised[strtolower($shortName)] = $shortName;
        }

        $this->excluded_models = implode(',', $normalised);
    }

    public function isModelExcluded(string $model): bool
    {
        $model = trim($model, "\\ ");
        $model = strrpos($model, '\\') !== false ? substr($model, strrpos($model, '\\') + 1) : $model;
        $model = strtolower($model);

        foreach ($this->getExcludedModels() as $candidate) {
            if (strtolower($candidate) === $model) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function getShareEmailList(): array
    {
        if (empty($this->share_emails)) {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', (string)$this->share_emails, -1, PREG_SPLIT_NO_EMPTY);
        if (false === $parts) {
            return [];
        }

        $emails = [];
        foreach ($parts as $email) {
            $sanitised = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
            if (false === $sanitised) {
                continue;
            }

            $emails[strtolower($sanitised)] = strtolower($sanitised);
        }

        return array_values($emails);
    }

    /**
     * @return string[]
     */
    public function shareRecipients(\FacturaScripts\Core\Model\Base\BusinessDocument $document): array
    {
        $emails = $this->getShareEmailList();

        if ($this->auto_share) {
            $subjectEmails = $this->extractSubjectEmails($document);
            foreach ($subjectEmails as $email) {
                $emails[strtolower($email)] = strtolower($email);
            }
        }

        return array_values($emails);
    }

    /**
     * @return string[]
     */
    private function extractSubjectEmails(\FacturaScripts\Core\Model\Base\BusinessDocument $document): array
    {
        $emails = [];

        try {
            $subject = $document->getSubject();
        } catch (\Throwable $exception) {
            $subject = null;
        }

        if (is_object($subject)) {
            foreach (['email', 'emailfacturacion', 'emailpedido', 'emailcontacto'] as $field) {
                if (!empty($subject->{$field}) && filter_var($subject->{$field}, FILTER_VALIDATE_EMAIL)) {
                    $emails[strtolower($subject->{$field})] = strtolower($subject->{$field});
                }
            }
        }

        foreach (['email', 'emailcliente', 'emailproveedor'] as $field) {
            if (property_exists($document, $field) && !empty($document->{$field}) && filter_var($document->{$field}, FILTER_VALIDATE_EMAIL)) {
                $emails[strtolower($document->{$field})] = strtolower($document->{$field});
            }
        }

        return array_values($emails);
    }

    public static function tableName(): string
    {
        return 'gd_company_cfg';
    }
}
