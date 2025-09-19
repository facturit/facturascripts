<?php
/**
 * Extension for the Variante model to copy sale prices between variants.
 */

namespace FacturaScripts\Plugins\CopyVariantPrice\Extension\Model;

use FacturaScripts\Core\Cache;

class Variante
{
    public function clear(): \Closure
    {
        return function (): void {
            $this->copyprice = false;
        };
    }

    public function save(): \Closure
    {
        return function () {
            if (empty($this->copyprice) || empty($this->idproducto)) {
                return null;
            }

            $price = (float)$this->precio;
            $margin = (float)$this->margen;

            $where = ' WHERE idproducto = ' . self::$dataBase->var2str($this->idproducto);
            if (!empty($this->idvariante)) {
                $where .= ' AND idvariante <> ' . self::$dataBase->var2str($this->idvariante);
            }

            $sql = 'UPDATE ' . static::tableName()
                . ' SET precio = ' . self::$dataBase->var2str($price)
                . ', margen = ' . self::$dataBase->var2str($margin)
                . $where . ';';

            if (false !== self::$dataBase->exec($sql)) {
                Cache::deleteMulti('model-' . $this->modelClassName() . '-');
                Cache::deleteMulti('join-model-');
                Cache::deleteMulti('table-' . static::tableName() . '-');
            }

            $this->copyprice = false;

            return null;
        };
    }
}
