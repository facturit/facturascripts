<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core\Model;

use FacturaScripts\Core\Lib\MyFilesToken;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * Stores biometric signatures associated with business documents.
 */
class DocumentSignature extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $iddocsignature;

    /** @var string */
    public $model;

    /** @var string */
    public $modelcode;

    /** @var int */
    public $modelid;

    /** @var string */
    public $signer_name;

    /** @var string */
    public $signer_email;

    /** @var string */
    public $signer_phone;

    /** @var string */
    public $signer_id;

    /** @var string */
    public $signer_notes;

    /** @var string */
    public $signed_at;

    /** @var string */
    public $ip_address;

    /** @var string */
    public $user_agent;

    /** @var string */
    public $nick;

    /** @var string */
    public $signature_path;

    public function clear(): void
    {
        parent::clear();
        $this->signed_at = Tools::dateTime();
    }

    public function delete(): bool
    {
        $fullPath = $this->getFullPath();
        if (!empty($fullPath) && file_exists($fullPath) && !@unlink($fullPath)) {
            Tools::log()->warning('cant-delete-file', ['%fileName%' => $this->signature_path]);
        }

        return parent::delete();
    }

    public function getFullPath(): string
    {
        return empty($this->signature_path) ? '' : FS_FOLDER . '/' . $this->signature_path;
    }

    public function getImageUrl(bool $permanent = false): string
    {
        if (empty($this->signature_path)) {
            return '';
        }

        return $this->signature_path . '?myft=' . MyFilesToken::get($this->signature_path, $permanent);
    }

    public static function primaryColumn(): string
    {
        return 'iddocsignature';
    }

    public static function tableName(): string
    {
        return 'document_signatures';
    }

    public function test(): bool
    {
        $this->model = Tools::noHtml($this->model);
        $this->modelcode = Tools::noHtml($this->modelcode);
        $this->signer_name = Tools::noHtml($this->signer_name);
        $this->signer_email = Tools::noHtml($this->signer_email);
        $this->signer_phone = Tools::noHtml($this->signer_phone);
        $this->signer_id = Tools::noHtml($this->signer_id);
        $this->signer_notes = Tools::noHtml($this->signer_notes);
        $this->ip_address = Tools::noHtml($this->ip_address);
        $this->user_agent = Tools::noHtml($this->user_agent);
        $this->nick = Tools::noHtml($this->nick);
        $this->signature_path = Tools::noHtml($this->signature_path);

        if (empty($this->signed_at)) {
            $this->signed_at = Tools::dateTime();
        }

        return parent::test();
    }
}
