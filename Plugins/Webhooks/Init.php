<?php
namespace FacturaScripts\Plugins\Webhooks;

use FacturaScripts\Core\DbUpdater;
use FacturaScripts\Core\Lib\ExtendedController\ListController as BaseListController;
use FacturaScripts\Core\Lib\ExtendedController\PanelController as BasePanelController;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Plugins\Webhooks\Lib\ModelInspector;

class Init extends InitClass
{
    public function init(): void
    {
        $extension = new Extension\Model\Base\ModelClass();
        foreach (ModelInspector::extendableModels() as $className) {
            $className::addExtension($extension);
        }

        BasePanelController::addExtension(new Extension\Controller\Lib\ExtendedController\PanelController());
        BaseListController::addExtension(new Extension\Controller\Lib\ExtendedController\ListController());
    }

    public function uninstall(): void
    {
        DbUpdater::dropTable('webhook_rules');
    }

    public function update(): void
    {
        new Model\WebhookRule();
        $this->updateTableData('webhook_rules');
    }
}
