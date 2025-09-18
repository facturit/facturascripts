<?php

namespace FacturaScripts\Plugins\googledrive_sync;

use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Model\Base\BusinessDocument());
    }

    public function uninstall(): void
    {
        // Nothing to clean for now.
    }

    public function update(): void
    {
        // No specific update tasks yet.
    }
}
