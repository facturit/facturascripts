<?php
namespace FacturaScripts\Plugins\CbdClinic;

use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
        // Nothing to do on every request for now.
    }

    public function uninstall(): void
    {
        // Plugin tables are not removed automatically to avoid losing clinical data.
    }

    public function update(): void
    {
        // The plugin relies on XML table definitions, so no runtime update logic is required.
    }
}
