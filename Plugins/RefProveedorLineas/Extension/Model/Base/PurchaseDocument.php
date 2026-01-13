<?php
/**
 * This file is part of FacturaScripts
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

namespace FacturaScripts\Plugins\RefProveedorLineas\Extension\Model\Base;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Variante;

class PurchaseDocument
{
    public function getNewProductLine(): callable
    {
        return function ($newLine, Variante $variant, $product): void {
            if (empty($newLine->referencia) || empty($this->codproveedor)) {
                return;
            }

            $refProveedor = $this->findSupplierReference($newLine->referencia);
            if ($refProveedor !== null) {
                $newLine->refproveedor = $refProveedor;
            }
        };
    }

    private function findSupplierReference(string $referencia): ?string
    {
        $supplierProd = new ProductoProveedor();
        $where = [
            new DataBaseWhere('codproveedor', $this->codproveedor),
            new DataBaseWhere('referencia', $referencia),
        ];
        $orderBy = ['coddivisa' => 'DESC'];
        foreach ($supplierProd->all($where, $orderBy) as $prod) {
            if ($prod->coddivisa === $this->coddivisa || $prod->coddivisa === null) {
                return $prod->refproveedor;
            }
        }

        return null;
    }
}
