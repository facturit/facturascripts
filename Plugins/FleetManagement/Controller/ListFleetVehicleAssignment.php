<?php

namespace FacturaScripts\Plugins\FleetManagement\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\FleetManagement\Model\FleetVehicle;

class ListFleetVehicleAssignment extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'fleet-vehicle-assignments';
        $data['icon'] = 'fa-solid fa-route';
        $data['menu'] = 'admin';
        $data['submenu'] = 'fleet';
        return $data;
    }

    protected function createViews()
    {
        $viewName = 'ListFleetVehicleAssignment';
        $this->addView($viewName, 'FleetVehicleAssignment', 'fleet-vehicle-assignments', 'fa-solid fa-route')
            ->addSearchFields(['user_nick', 'notes'])
            ->addOrderBy(['assignment_date'], 'fleet-assignment-date', 2)
            ->addOrderBy(['user_nick'], 'fleet-assignment-user')
            ->addOrderBy(['vehicle_id'], 'fleet-assignment-vehicle');

        $this->addFilterDatePicker($viewName, 'from', 'fleet-filter-from-date', 'assignment_date', '>=');
        $this->addFilterDatePicker($viewName, 'to', 'fleet-filter-to-date', 'assignment_date', '<=');
        $this->addFilterSelect($viewName, 'vehicle', 'fleet-assignment-vehicle', 'vehicle_id', $this->getVehicleFilterOptions());
        $this->addFilterAutocomplete($viewName, 'user', 'fleet-assignment-user', 'user_nick', 'users', 'nick', 'nick');
    }

    private function getVehicleFilterOptions(): array
    {
        $options = [];
        $vehicleModel = new FleetVehicle();
        foreach ($vehicleModel->all([], ['license_plate' => 'ASC'], 0, 0) as $vehicle) {
            $labelParts = array_filter([$vehicle->license_plate, trim($vehicle->brand . ' ' . $vehicle->model)]);
            if (empty($labelParts)) {
                $labelParts[] = '#' . $vehicle->id;
            }

            $options[$vehicle->id] = implode(' · ', $labelParts);
        }

        return $options;
    }
}
