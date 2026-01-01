<?php
namespace FacturaScripts\Plugins\Webhooks\Model;

use FacturaScripts\Core\Template\ModelClass as BaseModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;

class WebhookRule extends BaseModelClass
{
    use ModelTrait;

    public $idwebhookrule;
    public $model;
    public $endpoint;
    public $description;
    public $condition_text;
    public $previous_condition_text;
    public $on_action;
    public $active;
    public $on_insert;
    public $on_update;

    public function clear(): void
    {
        parent::clear();
        $this->active = true;
        $this->on_insert = true;
        $this->on_update = true;
        $this->on_action = false;
    }

    public static function primaryColumn(): string
    {
        return 'idwebhookrule';
    }

    public static function tableName(): string
    {
        return 'webhook_rules';
    }

    public static function activeForModel(string $modelName, string $event): array
    {
        $where = [
            Where::eq('active', true),
            Where::eq('model', $modelName),
        ];

        if ('insert' === $event) {
            $where[] = Where::eq('on_insert', true);
        } elseif ('update' === $event) {
            $where[] = Where::eq('on_update', true);
        } elseif ('action' === $event) {
            $where[] = Where::eq('on_action', true);
        }

        return self::all($where, ['idwebhookrule' => 'ASC']);
    }

    public function install(): string
    {
        return parent::install();
    }

    public function shouldTrigger(BaseModelClass $model, array $original = [], string $event = ''): bool
    {
        $previousConditions = $this->parseConditionsFrom((string)$this->previous_condition_text);
        if (!empty($previousConditions)) {
            if ('update' !== $event) {
                return false;
            }

            if (empty($original)) {
                return false;
            }

            foreach ($previousConditions as $condition) {
                $previousValue = $original[$condition['field']] ?? null;
                $expected = $condition['value'];
                $operator = $condition['operator'];

                if (false === $this->compareValues($previousValue, $expected, $operator)) {
                    return false;
                }
            }
        }

        foreach ($this->parseConditionsFrom((string)$this->condition_text) as $condition) {
            $currentValue = $model->{$condition['field']} ?? null;
            $expected = $condition['value'];
            $operator = $condition['operator'];

            if (false === $this->compareValues($currentValue, $expected, $operator)) {
                return false;
            }
        }

        return true;
    }

    protected function parseConditionsFrom(string $raw): array
    {
        $conditions = [];
        $raw = trim($raw);
        if ($raw === '') {
            return $conditions;
        }

        $parts = preg_split('/[\r\n;]+/', $raw);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (!preg_match('/^(?P<field>[a-zA-Z0-9_]+)\s*(?P<operator>>=|<=|!=|=|>|<)\s*(?P<value>.+)$/', $part, $matches)) {
                Tools::log()->warning('invalid-webhook-condition', ['%condition%' => $part]);
                continue;
            }

            $conditions[] = [
                'field' => $matches['field'],
                'operator' => $matches['operator'],
                'value' => $this->normalizeValue($matches['value']),
            ];
        }

        return $conditions;
    }

    protected function normalizeValue(string $value)
    {
        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        $lower = strtolower($value);
        if (in_array($lower, ['true', 'false'], true)) {
            return $lower === 'true';
        }

        if ($lower === 'null') {
            return null;
        }

        return $value;
    }

    protected function compareValues($currentValue, $expected, string $operator): bool
    {
        switch ($operator) {
            case '=':
                return $this->normalizeComparison($currentValue) == $expected;
            case '!=':
                return $this->normalizeComparison($currentValue) != $expected;
            case '>':
                return $this->normalizeComparison($currentValue) > $expected;
            case '<':
                return $this->normalizeComparison($currentValue) < $expected;
            case '>=':
                return $this->normalizeComparison($currentValue) >= $expected;
            case '<=':
                return $this->normalizeComparison($currentValue) <= $expected;
        }

        return true;
    }

    protected function normalizeComparison($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return null === $value ? null : trim((string)$value);
    }
}
