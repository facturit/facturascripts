<?php
namespace FacturaScripts\Plugins\Webhooks;

use FacturaScripts\Core\DbUpdater;
use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Model\Base\ModelClass());
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
