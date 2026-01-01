<?php
namespace FacturaScripts\Plugins\Webhooks\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Webhooks\Lib\ModelInspector;

class EditWebhookRule extends EditController
{
    public function getModelClassName(): string
    {
        return 'WebhookRule';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'webhook-rule';
        $data['menu'] = 'admin';
        $data['icon'] = 'fa-solid fa-plug';
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();

        $view = $this->views[$this->getMainViewName()] ?? null;
        if (null === $view) {
            return;
        }

        $column = $view->columnForField('model');
        if (null === $column || false === method_exists($column->widget, 'setValuesFromArrayKeys')) {
            return;
        }

        $options = [];
        foreach (ModelInspector::extendableModels() as $shortName => $className) {
            $options[$shortName] = $shortName;
        }

        $column->widget->setValuesFromArrayKeys($options, false, false);
    }

    protected function execPreviousAction($action)
    {
        if ('copy' === $action) {
            return $this->copyAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName !== $this->getMainViewName() || false === $view->model->exists()) {
            return;
        }

        $this->addButton($viewName, [
            'action' => 'copy',
            'color' => 'info',
            'icon' => 'fa-solid fa-copy',
            'label' => 'clone',
            'title' => 'clone',
        ]);
    }

    private function copyAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return false;
        }

        if (false === $this->validateFormToken()) {
            return false;
        }

        $mainView = $this->getMainViewName();
        $view = $this->views[$mainView] ?? null;
        if (null === $view) {
            return false;
        }

        $primaryKey = $view->model->primaryColumn();
        $code = $this->request->input($primaryKey, '');
        if (false === $view->model->loadFromCode($code)) {
            Tools::log()->error('record-not-found');
            return false;
        }

        $modelClass = get_class($view->model);
        $data = $view->model->toArray();
        unset($data[$primaryKey]);

        $copy = new $modelClass();
        $copy->loadFromData($data);

        if (false === $copy->save()) {
            Tools::log()->error('record-save-error');
            return false;
        }

        $this->redirect($copy->url() . '&action=save-ok');
        return false;
    }
}
