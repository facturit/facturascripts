<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2017-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Core\Lib\Widget;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\AssetManager;

/**
 * Widget that allows selecting multiple values from a datasource and shows
 * them as tags. It uses the autocomplete endpoint in order to fetch the
 * available options while typing, and stores the selected values as a comma
 * separated list in the model just like a multiselect widget does.
 */
class WidgetTag extends WidgetSelect
{
    /**
     * Indicates whether the widget must restrict the values to the datasource
     * results or allow free text values.
     *
     * @var bool
     */
    protected $strict = true;

    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->multiple = true;
        $this->strict = isset($data['strict']) ? strtolower($data['strict']) === 'true' : true;
    }

    protected function assets(): void
    {
        $route = Tools::config('route');
        AssetManager::addCss($route . '/node_modules/select2/dist/css/select2.min.css?v=5');
        AssetManager::addCss($route . '/node_modules/select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css?v=5');
        AssetManager::addJs($route . '/node_modules/select2/dist/js/select2.min.js?v=5', 2);
        AssetManager::addJs($route . '/Dinamic/Assets/JS/WidgetTag.js?v=1');
    }

    protected function inputHtml($type = 'text', $extraClass = 'widget-tag-select')
    {
        $class = $this->combineClasses($this->css('form-select'), $this->class, $extraClass);
        if ($this->parent) {
            $class .= ' parentSelect';
        }

        $html = '';
        $name = '';
        if ($this->readonly()) {
            $html .= '<input type="hidden" name="' . $this->fieldname . '" value="' . $this->value . '">';
        } else {
            $name = ' name="' . $this->fieldname . '[]"';
        }

        $html .= '<select'
            . $name
            . ' id="' . $this->id . '"'
            . ' class="' . $class . '"'
            . $this->inputHtmlExtraParams()
            . ' parent="' . $this->parent . '"'
            . ' value="' . $this->value . '"'
            . ' data-field="' . $this->fieldname . '"'
            . ' data-source="' . $this->source . '"'
            . ' data-fieldcode="' . $this->fieldcode . '"'
            . ' data-fieldtitle="' . $this->fieldtitle . '"'
            . ' data-fieldfilter="' . $this->fieldfilter . '"'
            . ' data-strict="' . ($this->strict ? '1' : '0') . '"'
            . '>';

        $selectedValues = $this->selectedValues();
        $printed = [];
        foreach ($this->values as $option) {
            $value = (string)($option['value'] ?? '');
            $title = empty($option['title']) ? $value : (string)$option['title'];
            $isSelected = in_array($value, $selectedValues, true);
            $printed[$value] = true;
            $html .= '<option value="' . $value . '"' . ($isSelected ? ' selected' : '') . '>'
                . $title . '</option>';
        }

        foreach ($selectedValues as $value) {
            if ($value === '' || isset($printed[$value])) {
                continue;
            }

            $title = $this->source
                ? static::$codeModel->getDescription($this->source, $this->fieldcode, $value, $this->fieldtitle)
                : $value;
            $html .= '<option value="' . $value . '" selected>' . $title . '</option>';
        }

        $html .= '</select>';
        return $html;
    }

    protected function selectedValues(): array
    {
        if (null === $this->value) {
            return [];
        }

        if (is_array($this->value)) {
            return array_values(array_filter(array_map('strval', $this->value), 'strlen'));
        }

        if (is_string($this->value)) {
            return array_values(array_filter(array_map('trim', explode(',', $this->value)), 'strlen'));
        }

        return [(string)$this->value];
    }
}
