<?php

namespace FacturaScripts\Plugins\googledrive_sync\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use JsonException;
use RuntimeException;

/**
 * Configuration model per company for the Google Drive synchronisation plugin.
 */
class GoogleDriveCompanyConfig extends ModelClass
{
    use ModelTrait;

    /**
     * Cached decoded credentials to avoid repeated json_decode operations.
     *
     * @var array<string, mixed>|null
     */
    private ?array $credentialsCache = null;

    /**
     * Cached decoded token payload.
     *
     * @var array<string, mixed>|null
     */
    private ?array $tokenCache = null;

    /** @var int|null */
    public $id;

    /** @var int|null */
    public $idempresa;

    /** @var string */
    public $credentials_mode;

    /** @var string|null */
    public $credentials_json;

    /** @var string|null */
    public $token_json;

    /** @var string|null */
    public $token_expires_at;

    /** @var string|null */
    public $token_updated_at;

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
        $this->credentials_json = null;
        $this->token_json = null;
        $this->token_expires_at = null;
        $this->token_updated_at = null;
        $this->credentialsCache = null;
        $this->tokenCache = null;
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

    public function loadFromData(array $data = [], array $exclude = []): void
    {
        parent::loadFromData($data, $exclude);

        $this->credentials_json = $this->decryptValue($this->credentials_json);
        $this->token_json = $this->decryptValue($this->token_json);
        $this->credentialsCache = null;
        $this->tokenCache = null;
    }

    public function save(): bool
    {
        $credentialsPlain = $this->normalizePlainText($this->credentials_json);
        $tokenPlain = $this->normalizePlainText($this->token_json);

        $this->credentials_json = $this->encryptValue($credentialsPlain);
        $this->token_json = $this->encryptValue($tokenPlain);

        $saved = parent::save();

        $this->credentials_json = $credentialsPlain;
        $this->token_json = $tokenPlain;

        if ($saved) {
            $this->credentialsCache = null;
            $this->tokenCache = null;
        }

        return $saved;
    }

    public function isConfigured(): bool
    {
        $credentials = $this->getCredentialsArray();

        if ('service' === $this->credentials_mode) {
            return !empty($credentials['client_email']) && !empty($credentials['private_key']);
        }

        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            return false;
        }

        return null !== $this->getRefreshToken();
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
     * Returns the decoded credentials array, caching the result between calls.
     *
     * @return array<string, mixed>
     */
    public function getCredentialsArray(): array
    {
        if ($this->credentialsCache !== null) {
            return $this->credentialsCache;
        }

        $json = trim((string)$this->credentials_json);
        if ($json === '') {
            $this->credentialsCache = [];
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->credentialsCache = [];
            return [];
        }

        $this->credentialsCache = is_array($decoded) ? $decoded : [];
        return $this->credentialsCache;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function setCredentialsArray(array $credentials): void
    {
        $this->credentialsCache = $credentials;
        $this->credentials_json = json_encode($credentials, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTokenArray(): array
    {
        if ($this->tokenCache !== null) {
            return $this->tokenCache;
        }

        $json = trim((string)$this->token_json);
        if ($json === '') {
            $this->tokenCache = [];
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->tokenCache = [];
            return [];
        }

        $this->tokenCache = is_array($decoded) ? $decoded : [];
        return $this->tokenCache;
    }

    /**
     * @param array<string, mixed> $token
     */
    public function setTokenArray(array $token): void
    {
        $this->tokenCache = $token;
        $this->token_json = json_encode($token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $expires = $this->resolveTokenExpiry($token);
        $this->token_expires_at = $expires ? date('Y-m-d H:i:s', $expires) : null;
        $this->token_updated_at = Tools::dateTime();
    }

    public function clearToken(): void
    {
        $this->tokenCache = [];
        $this->token_json = null;
        $this->token_expires_at = null;
        $this->token_updated_at = null;
    }

    public function getRefreshToken(): ?string
    {
        $token = $this->getTokenArray();
        if (!empty($token['refresh_token'])) {
            return (string)$token['refresh_token'];
        }

        $credentials = $this->getCredentialsArray();
        if (!empty($credentials['refresh_token'])) {
            return (string)$credentials['refresh_token'];
        }

        return null;
    }

    public function hasValidAccessToken(): bool
    {
        if (empty($this->token_json) || empty($this->token_expires_at)) {
            return false;
        }

        return strtotime($this->token_expires_at) > (time() + 60);
    }

    public function shouldRefreshToken(): bool
    {
        if ('service' === $this->credentials_mode) {
            return false;
        }

        if (false === $this->hasValidAccessToken()) {
            return true;
        }

        return strtotime($this->token_expires_at) <= (time() + 300);
    }

    /**
     * @param array<string, mixed> $token
     */
    private function resolveTokenExpiry(array $token): ?int
    {
        if (isset($token['expiry_date'])) {
            return (int)$token['expiry_date'];
        }

        if (isset($token['created'], $token['expires_in'])) {
            return (int)$token['created'] + (int)$token['expires_in'];
        }

        if (isset($token['expires_in'])) {
            return time() + (int)$token['expires_in'];
        }

        return null;
    }

    private function normalizePlainText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function encryptValue(?string $value): ?string
    {
        $value = $this->normalizePlainText($value);
        if ($value === null) {
            return null;
        }

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('openssl-extension-required');
        }

        $key = self::encryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new RuntimeException('unable-to-encrypt');
        }

        return base64_encode($iv . $encrypted);
    }

    private function decryptValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = base64_decode($trimmed, true);
        if ($decoded === false || strlen($decoded) <= 16) {
            return $trimmed;
        }

        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException('openssl-extension-required');
        }

        $iv = substr($decoded, 0, 16);
        $cipherText = substr($decoded, 16);
        $plain = openssl_decrypt($cipherText, 'AES-256-CBC', self::encryptionKey(), OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            return $trimmed;
        }

        return $plain;
    }

    private static function encryptionKey(): string
    {
        $stored = (string)Tools::settings('googledrive_sync', 'secret_key', '');
        if ($stored === '') {
            $raw = random_bytes(32);
            $stored = base64_encode($raw);
            Tools::settingsSet('googledrive_sync', 'secret_key', $stored);
            Tools::settingsSave();
        }

        $decoded = base64_decode($stored, true);
        if ($decoded === false || strlen($decoded) < 16) {
            $decoded = hash('sha256', $stored, true);
        }

        return substr(hash('sha256', $decoded, true), 0, 32);
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
