<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2024
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Dinamic\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;

/**
 * Defines sharing rules for KPI dashboards.
 */
class KpiDashboardShare extends ModelClass
{
    use Base\ModelTrait;

    /** @var bool */
    public $canedit;

    /** @var string */
    public $codrole;

    /** @var int */
    public $iddashboard;

    /** @var int */
    public $id;

    /** @var string */
    public $nick;

    /** @var string */
    public $sharetype;

    public function clear()
    {
        parent::clear();
        $this->sharetype = 'user';
        $this->canedit = false;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'kpi_dashboard_shares';
    }

    public function test(): bool
    {
        $this->sharetype = Tools::noHtml($this->sharetype);
        $this->nick = Tools::noHtml($this->nick);
        $this->codrole = Tools::noHtml($this->codrole);

        if (!in_array($this->sharetype, ['user', 'role'], true)) {
            Tools::log()->warning('kpi-share-type-invalid');
            return false;
        }

        if ($this->sharetype === 'user' && empty($this->nick)) {
            Tools::log()->warning('kpi-share-user-missing');
            return false;
        }

        if ($this->sharetype === 'role' && empty($this->codrole)) {
            Tools::log()->warning('kpi-share-role-missing');
            return false;
        }

        return parent::test();
    }
}
