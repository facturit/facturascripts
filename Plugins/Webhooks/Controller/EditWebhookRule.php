<?php
namespace FacturaScripts\Plugins\Webhooks\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

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
}
