<?php
namespace FacturaScripts\Plugins\Webhooks\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListWebhookRule extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'webhook-rules';
        $data['menu'] = 'admin';
        $data['icon'] = 'fa-solid fa-plug';
        $data['showonmenu'] = true;
        return $data;
    }

    protected function createViews(): void
    {
        $this->createWebhookRuleView();
    }

    protected function createWebhookRuleView(string $viewName = 'ListWebhookRule'): void
    {
        $this->addView($viewName, 'WebhookRule', 'webhook-rules', 'fa-solid fa-plug')
            ->addSearchFields(['model', 'endpoint', 'description'])
            ->addOrderBy(['model', 'endpoint'], 'model')
            ->addOrderBy(['active', 'model'], 'status');

        $this->addFilterCheckbox($viewName, 'only_active', 'status', 'active', '=', true, [new DataBaseWhere('active', true)]);
    }
}
