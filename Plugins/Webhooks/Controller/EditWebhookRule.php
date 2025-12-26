<?php
namespace FacturaScripts\Plugins\Webhooks\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
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
}
