<?php

namespace FacturaScripts\Plugins\FleetManagement\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;

class FleetVehicle extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $license_plate;

    /** @var string */
    public $vehicle_type;

    /** @var string */
    public $brand;

    /** @var string */
    public $model;

    public function clear()
    {
        parent::clear();
        $this->vehicle_type = 'coche';
    }

    public function primaryDescription()
    {
        return $this->license_plate ?? parent::primaryDescription();
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'fleet_vehicles';
    }

    public static function vehicleTypeOptions(): array
    {
        return [
            'coche' => Tools::lang()->trans('fleet-vehicle-type-car'),
            'furgoneta' => Tools::lang()->trans('fleet-vehicle-type-van'),
            'camion' => Tools::lang()->trans('fleet-vehicle-type-truck'),
        ];
    }

    public function test(): bool
    {
        $this->license_plate = Tools::noHtml(mb_strtoupper($this->license_plate ?? ''));
        $this->vehicle_type = Tools::noHtml($this->vehicle_type ?? '');
        $this->brand = Tools::noHtml($this->brand ?? '');
        $this->model = Tools::noHtml($this->model ?? '');

        if (empty($this->license_plate)) {
            Tools::log()->warning('fleet-vehicle-invalid-plate');
            return false;
        }

        if (strlen($this->license_plate) > 20) {
            Tools::log()->warning('invalid-column-lenght', ['%column%' => 'license_plate', '%min%' => '1', '%max%' => '20']);
            return false;
        }

        $typeOptions = array_keys(self::vehicleTypeOptions());
        if (false === in_array($this->vehicle_type, $typeOptions, true)) {
            Tools::log()->warning('fleet-vehicle-invalid-type');
            return false;
        }

        if (strlen($this->brand) > 100) {
            Tools::log()->warning('invalid-column-lenght', ['%column%' => 'brand', '%min%' => '0', '%max%' => '100']);
            return false;
        }

        if (strlen($this->model) > 100) {
            Tools::log()->warning('invalid-column-lenght', ['%column%' => 'model', '%min%' => '0', '%max%' => '100']);
            return false;
        }

        $where = [new DataBaseWhere('license_plate', $this->license_plate)];
        if ($this->id) {
            $where[] = new DataBaseWhere('id', $this->id, '!=');
        }
        if ($this->count($where) > 0) {
            Tools::log()->warning('fleet-vehicle-duplicate-plate');
            return false;
        }

        return parent::test();
    }
}
