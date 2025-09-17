<?php

namespace FacturaScripts\Plugins\FleetManagement\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\User;

class FleetVehicleAssignment extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var int */
    public $vehicle_id;

    /** @var string */
    public $assignment_date;

    /** @var string */
    public $user_nick;

    /** @var string */
    public $notes;

    public function clear()
    {
        parent::clear();
        $this->assignment_date = Tools::date();
    }

    public function primaryDescription()
    {
        $label = [$this->assignment_date];
        $vehicle = $this->vehicle();
        if ($vehicle) {
            $label[] = $vehicle->license_plate;
        }

        return trim(implode(' · ', array_filter($label)));
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'fleet_vehicle_assignments';
    }

    public function test(): bool
    {
        $this->assignment_date = Tools::date($this->assignment_date ?? '');
        $this->user_nick = Tools::noHtml($this->user_nick ?? '');
        $this->notes = Tools::noHtml($this->notes ?? '');

        if (empty($this->vehicle_id) || $this->vehicle_id <= 0) {
            Tools::log()->warning('fleet-assignment-invalid-vehicle');
            return false;
        }

        if (null === $this->vehicle()) {
            Tools::log()->warning('fleet-assignment-invalid-vehicle');
            return false;
        }

        if (empty($this->user_nick)) {
            Tools::log()->warning('fleet-assignment-invalid-user');
            return false;
        }

        if (null === $this->user()) {
            Tools::log()->warning('fleet-assignment-invalid-user');
            return false;
        }

        if (strlen($this->notes) > 255) {
            Tools::log()->warning('invalid-column-lenght', ['%column%' => 'notes', '%min%' => '0', '%max%' => '255']);
            return false;
        }

        $where = [
            new DataBaseWhere('vehicle_id', $this->vehicle_id),
            new DataBaseWhere('assignment_date', $this->assignment_date),
        ];
        if ($this->id) {
            $where[] = new DataBaseWhere('id', $this->id, '!=');
        }
        if ($this->count($where) > 0) {
            Tools::log()->warning('fleet-assignment-duplicate');
            return false;
        }

        return parent::test();
    }

    public function user(): ?User
    {
        if (empty($this->user_nick)) {
            return null;
        }

        $user = new User();
        return $user->loadFromCode($this->user_nick) ? $user : null;
    }

    public function vehicle(): ?FleetVehicle
    {
        if (empty($this->vehicle_id)) {
            return null;
        }

        $vehicle = new FleetVehicle();
        return $vehicle->loadFromCode($this->vehicle_id) ? $vehicle : null;
    }
}
