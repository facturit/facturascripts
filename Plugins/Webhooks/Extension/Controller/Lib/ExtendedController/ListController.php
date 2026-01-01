<?php
namespace FacturaScripts\Plugins\Webhooks\Extension\Controller\Lib\ExtendedController;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Webhooks\Lib\WebhookDispatcher;
use FacturaScripts\Plugins\Webhooks\Model\WebhookRule;

class ListController
{
    protected function execPreviousAction(): Closure
    {
        return function ($action) {
            if ('webhook-action' !== $action) {
                return null;
            }

            if (false === $this->permissions->allowUpdate) {
                Tools::log()->warning('not-allowed-modify');
                return false;
            }

            if (false === $this->validateFormToken()) {
                return false;
            }

            $view = $this->views[$this->active] ?? null;
            if (null === $view || null === $view->model) {
                return false;
            }

            $codes = $this->request->request->getArray('codes');
            if (empty($codes)) {
                Tools::log()->warning('webhook-no-codes');
                return false;
            }

            WebhookDispatcher::dispatchActionForCodes($view->model->modelClassName(), $codes, get_class($view->model));
            return false;
        };
    }

    protected function loadData(): Closure
    {
        return function ($viewName, $view): void {
            if ($viewName !== $this->getMainViewName()) {
                return;
            }

            if (isset($this->webhookActionButtons[$viewName])) {
                return;
            }

            if (null === $view->model) {
                return;
            }

            if (empty(WebhookRule::activeForModel($view->model->modelClassName(), 'action'))) {
                return;
            }

            $this->webhookActionButtons[$viewName] = true;
            $this->addButton($viewName, [
                'action' => 'webhook-action',
                'color' => 'warning',
                'icon' => 'fa-solid fa-bolt',
                'label' => 'webhook-action',
                'title' => 'webhook-action',
            ]);
        };
    }
}
