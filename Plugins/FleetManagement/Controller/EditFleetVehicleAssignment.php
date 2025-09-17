<?php

namespace FacturaScripts\Plugins\FleetManagement\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditFleetVehicleAssignment extends EditController
{
    public function getModelClassName(): string
    {
        return 'FleetVehicleAssignment';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'fleet-vehicle-assignment';
        $data['icon'] = 'fa-solid fa-route';
        $data['menu'] = 'admin';
        $data['submenu'] = 'fleet';
        return $data;
    }
}
