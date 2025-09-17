<?php

namespace FacturaScripts\Plugins\FleetManagement\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\FleetManagement\Model\FleetVehicle;

class ListFleetVehicle extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'fleet-vehicles';
        $data['icon'] = 'fa-solid fa-car-side';
        $data['menu'] = 'admin';
        $data['submenu'] = 'fleet';
        return $data;
    }

    protected function createViews()
    {
        $viewName = 'ListFleetVehicle';
        $this->addView($viewName, 'FleetVehicle', 'fleet-vehicles', 'fa-solid fa-car-side')
            ->addSearchFields(['license_plate', 'brand', 'model'])
            ->addOrderBy(['license_plate'], 'fleet-license-plate', 1)
            ->addOrderBy(['vehicle_type'], 'fleet-vehicle-type')
            ->addOrderBy(['brand', 'model'], 'fleet-brand');

        $this->addFilterSelect($viewName, 'vehicle_type', 'fleet-vehicle-type', 'vehicle_type', $this->getVehicleTypeOptions());
    }

    private function getVehicleTypeOptions(): array
    {
        $options = [];
        foreach (FleetVehicle::vehicleTypeOptions() as $value => $label) {
            $options[$value] = $label;
        }

        return $options;
    }
}
