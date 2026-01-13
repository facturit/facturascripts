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

namespace FacturaScripts\Plugins\RefProveedorLineas;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Lib\AjaxForms\PurchasesLineHTML;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Plugins\RefProveedorLineas\Extension\Model\Base\PurchaseDocument;
use FacturaScripts\Plugins\RefProveedorLineas\Lib\RefProveedorLineMod;

class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new PurchaseDocument());
        PurchasesLineHTML::addMod(new RefProveedorLineMod());
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        $dataBase = new DataBase();
        if (false === $dataBase->connect()) {
            return;
        }

        $tables = [
            'lineasalbaranesprov',
            'lineasfacturasprov',
            'lineaspedidosprov',
            'lineaspresupuestosprov',
        ];

        foreach ($tables as $tableName) {
            $this->ensureRefProveedorColumn($dataBase, $tableName);
        }
    }

    private function ensureRefProveedorColumn(DataBase $dataBase, string $tableName): void
    {
        if (false === $dataBase->tableExists($tableName)) {
            return;
        }

        $columns = $dataBase->getColumns($tableName);
        if (isset($columns['refproveedor'])) {
            return;
        }

        $sql = sprintf('ALTER TABLE %s ADD COLUMN refproveedor VARCHAR(30);', $tableName);
        $dataBase->exec($sql);
    }
}
