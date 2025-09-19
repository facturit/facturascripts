<?php
/**
 * Plugin CopyVariantPrice for FacturaScripts.
 */

namespace FacturaScripts\Plugins\CopyVariantPrice;

use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Model\Variante());
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
    }
}
