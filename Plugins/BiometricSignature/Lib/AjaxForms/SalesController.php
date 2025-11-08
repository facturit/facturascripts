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

namespace FacturaScripts\Plugins\BiometricSignature\Lib\AjaxForms;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\AjaxForms\SalesController as BaseSalesController;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\DocumentSignature;

/**
 * Adds biometric signature management to sales documents via plugin extension.
 */
abstract class SalesController extends BaseSalesController
{
    protected function createViews()
    {
        $this->setTabsPosition('top');
        $this->createViewsDoc();
        $this->createViewSignatures();
        $this->createViewDocFiles();
        $this->createViewLogAudit();
    }

    protected function createViewSignatures(string $viewName = 'signatures'): void
    {
        $this->addHtmlView($viewName, 'Tab/DocumentSignatures', 'DocumentSignature', 'signature', 'fa-solid fa-signature');
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'save-signature':
                return $this->saveSignatureAction();

            case 'delete-signature':
                return $this->deleteSignatureAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function loadData($viewName, $view)
    {
        if ('signatures' === $viewName) {
            $code = (string)$this->request->get('code');
            $this->loadDataDocumentSignatures($view, $this->getModelClassName(), $code);
            return;
        }

        parent::loadData($viewName, $view);
    }

    private function loadDataDocumentSignatures($view, string $model, string $code): void
    {
        if (empty($code)) {
            return;
        }

        $where = [new DataBaseWhere('model', $model)];
        if (is_numeric($code)) {
            $where[] = new DataBaseWhere('modelid|modelcode', $code);
        } else {
            $where[] = new DataBaseWhere('modelcode', $code);
        }

        $view->loadData('', $where, ['signed_at' => 'DESC']);
    }

    private function saveSignatureAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $model = $this->getModel();
        if (empty($model->id())) {
            Tools::log()->warning('record-not-found');
            return true;
        }

        $signerName = trim((string)$this->request->input('signer_name'));
        $signerEmail = trim((string)$this->request->input('signer_email'));
        $signerPhone = trim((string)$this->request->input('signer_phone'));
        $signerId = trim((string)$this->request->input('signer_id'));
        $signerNotes = trim((string)$this->request->input('signer_notes'));
        $signatureImage = (string)$this->request->input('signature_image');

        if (empty($signerName)) {
            Tools::log()->warning('field-required', ['%field%' => Tools::trans('name')]);
            return true;
        }

        if (!empty($signerEmail) && false === filter_var($signerEmail, FILTER_VALIDATE_EMAIL)) {
            Tools::log()->warning('not-valid-email', ['%email%' => $signerEmail]);
            return true;
        }

        if (empty($signatureImage)) {
            Tools::log()->warning('field-required', ['%field%' => Tools::trans('signature')]);
            return true;
        }

        if (strpos($signatureImage, ',') !== false) {
            $signatureImage = explode(',', $signatureImage, 2)[1];
        }

        $binarySignature = base64_decode($signatureImage);
        if (false === $binarySignature || empty($binarySignature)) {
            Tools::log()->error('record-save-error');
            return true;
        }

        $relativeFolder = 'MyFiles/Signatures/' . date('Y') . '/' . date('m');
        $folderPath = FS_FOLDER . '/' . $relativeFolder;
        if (false === Tools::folderCheckOrCreate($folderPath)) {
            Tools::log()->critical('cant-create-folder', ['%folderName%' => $relativeFolder]);
            return true;
        }

        $documentCode = $model->codigo ?? (string)$model->id();
        $normalizedCode = strtolower(preg_replace('/[^a-z0-9_-]+/i', '-', $documentCode));
        $normalizedCode = trim($normalizedCode, '-');
        if (empty($normalizedCode)) {
            $normalizedCode = (string)$model->id();
        }

        $fileName = strtolower($this->getModelClassName()) . '-' . $normalizedCode
            . '-' . date('YmdHis') . '-' . Tools::randomString(6) . '.png';
        $fullPath = $folderPath . '/' . $fileName;

        if (false === file_put_contents($fullPath, $binarySignature)) {
            Tools::log()->error('record-save-error');
            return true;
        }

        $relativePath = $relativeFolder . '/' . $fileName;

        $signature = new DocumentSignature();
        $signature->model = $this->getModelClassName();
        $signature->modelcode = $documentCode;
        $signature->modelid = (int)$model->id();
        $signature->signer_name = $signerName;
        $signature->signer_email = $signerEmail;
        $signature->signer_phone = $signerPhone;
        $signature->signer_id = $signerId;
        $signature->signer_notes = $signerNotes;
        $signature->signature_path = $relativePath;
        $signature->signed_at = Tools::dateTime();
        $signature->ip_address = (string)Session::getClientIp();
        $userAgent = (string)$this->request->headers->get('User-Agent');
        $signature->user_agent = substr($userAgent, 0, 255);
        $signature->nick = $this->user->nick;

        if ($signature->save()) {
            Tools::log()->notice('record-updated-correctly');
            return true;
        }

        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        Tools::log()->error('record-save-error');
        return true;
    }

    private function deleteSignatureAction(): bool
    {
        if (false === $this->permissions->allowDelete) {
            Tools::log()->warning('not-allowed-delete');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $signatureId = (int)$this->request->input('id');
        $signature = new DocumentSignature();
        if (false === $signature->load($signatureId)) {
            Tools::log()->warning('record-not-found');
            return true;
        }

        $model = $this->getModel();
        if (empty($model->id())) {
            Tools::log()->warning('record-not-found');
            return true;
        }

        $modelId = (int)$model->id();
        $modelCode = $model->codigo ?? (string)$modelId;

        $matchesId = $signature->modelid === $modelId;
        $matchesCode = in_array($signature->modelcode, [$modelCode, (string)$modelId], true);

        if ($signature->model !== $this->getModelClassName() || (!$matchesId && !$matchesCode)) {
            Tools::log()->warning('not-allowed-delete');
            return true;
        }

        if ($signature->delete()) {
            Tools::log()->notice('record-deleted-correctly');
            return true;
        }

        Tools::log()->warning('record-deleted-error');
        return true;
    }
}
