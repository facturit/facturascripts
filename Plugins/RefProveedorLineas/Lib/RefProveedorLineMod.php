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

namespace FacturaScripts\Plugins\RefProveedorLineas\Lib;

use FacturaScripts\Core\Contract\PurchasesLineModInterface;
use FacturaScripts\Core\Model\Base\PurchaseDocument;
use FacturaScripts\Core\Model\Base\PurchaseDocumentLine;
use FacturaScripts\Core\Tools;

class RefProveedorLineMod implements PurchasesLineModInterface
{
    public function apply(PurchaseDocument &$model, array &$lines, array $formData): void
    {
    }

    public function applyToLine(array $formData, PurchaseDocumentLine &$line, string $id): void
    {
        $value = $formData['refproveedor_' . $id] ?? null;
        if ($value !== null) {
            $line->refproveedor = Tools::noHtml($value);
        }
    }

    public function assets(): void
    {
    }

    public function getFastLine(PurchaseDocument $model, array $formData): ?PurchaseDocumentLine
    {
        return null;
    }

    public function map(array $lines, PurchaseDocument $model): array
    {
        $map = [];
        foreach ($lines as $line) {
            $idlinea = $line->idlinea;
            if ($idlinea === null) {
                continue;
            }

            $map['refproveedor_' . $idlinea] = $line->refproveedor;
        }

        return $map;
    }

    public function newFields(): array
    {
        return ['refproveedor'];
    }

    public function newModalFields(): array
    {
        return [];
    }

    public function newTitles(): array
    {
        return ['refproveedor'];
    }

    public function renderField(string $idlinea, PurchaseDocumentLine $line, PurchaseDocument $model, string $field): ?string
    {
        if ($field !== 'refproveedor') {
            return null;
        }

        $value = Tools::fixHtml($line->refproveedor ?? '');
        $label = Tools::lang()->trans('supplier-reference');
        $attributes = $model->editable ?
            'name="refproveedor_' . $idlinea . '" maxlength="30"' :
            'disabled=""';

        return '<div class="col-sm-2 col-lg-2 order-4">'
            . '<div class="d-lg-none mt-2 small">' . $label . '</div>'
            . '<input type="text" ' . $attributes . ' value="' . $value
            . '" class="form-control form-control-sm border-0"/>'
            . '</div>';
    }

    public function renderTitle(PurchaseDocument $model, string $field): ?string
    {
        if ($field !== 'refproveedor') {
            return null;
        }

        return '<div class="col-lg-2">' . Tools::lang()->trans('supplier-reference') . '</div>';
    }
}
