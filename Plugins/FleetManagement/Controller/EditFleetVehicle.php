<?php

namespace FacturaScripts\Plugins\FleetManagement\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditFleetVehicle extends EditController
{
    public function getModelClassName(): string
    {
        return 'FleetVehicle';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'fleet-vehicle';
        $data['icon'] = 'fa-solid fa-car-side';
        $data['menu'] = 'admin';
        $data['submenu'] = 'fleet';
        return $data;
    }
}
