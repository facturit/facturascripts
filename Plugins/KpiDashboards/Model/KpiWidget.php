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
 * Defines a KPI widget inside a dashboard.
 */
class KpiWidget extends ModelClass
{
    use Base\ModelTrait;

    /** @var array */
    public $config;

    /** @var int */
    public $iddashboard;

    /** @var int */
    public $id;

    /** @var int */
    public $position;

    /** @var string */
    public $title;

    /** @var string */
    public $type;

    public function clear()
    {
        parent::clear();
        $this->config = [];
        $this->position = 0;
        $this->type = 'numeric';
    }

    public function loadFromData(array $data = [], array $exclude = [])
    {
        array_push($exclude, 'config');
        parent::loadFromData($data, $exclude);

        $decodedConfig = isset($data['config']) ? json_decode($data['config'], true) : null;
        $this->config = is_array($decodedConfig) ? $decodedConfig : [];
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'kpi_widgets';
    }

    public function test(): bool
    {
        $this->title = Tools::noHtml($this->title);
        $this->type = Tools::noHtml($this->type);

        if (!in_array($this->type, static::allowedTypes(), true)) {
            Tools::log()->warning('kpi-widget-type-invalid');
            return false;
        }

        return parent::test();
    }

    public static function allowedTypes(): array
    {
        return [
            'chart',
            'table',
            'donut',
            'funnel',
            'numeric',
            'percentage'
        ];
    }

    protected function saveInsert(array $values = []): bool
    {
        return parent::saveInsert($this->getEncodedValues());
    }

    protected function saveUpdate(array $values = []): bool
    {
        return parent::saveUpdate($this->getEncodedValues());
    }

    private function getEncodedValues(): array
    {
        if (is_string($this->config)) {
            return ['config' => $this->config];
        }

        return [
            'config' => json_encode($this->config ?? [])
        ];
    }
}
