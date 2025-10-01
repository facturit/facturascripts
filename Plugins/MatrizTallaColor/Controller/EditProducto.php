<?php
namespace FacturaScripts\Plugins\MatrizTallaColor\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Controller\EditProducto as BaseEditProducto;
use FacturaScripts\Core\Model\Atributo;
use FacturaScripts\Core\Model\Producto;
use FacturaScripts\Core\Model\Stock;
use FacturaScripts\Core\Model\Variante;
use FacturaScripts\Core\Tools;

class EditProducto extends BaseEditProducto
{
    /** @var string */
    public $matrixAttributeX = '';

    /** @var string */
    public $matrixAttributeY = '';

    /** @var array<int, array<string, mixed>> */
    public $matrixValuesX = [];

    /** @var array<int, array<string, mixed>> */
    public $matrixValuesY = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    public $matrixGrid = [];

    /** @var string */
    public $matrixWarehouse = '';

    /** @var bool */
    public $matrixHasData = false;

    /** @var Variante|null */
    private $matrixBaseVariant = null;

    protected function createViews()
    {
        parent::createViews();
        $this->createMatrixView();
    }

    protected function createMatrixView(string $viewName = 'MatrixAttribute'): void
    {
        $view = $this->addHtmlView($viewName, 'Tab/MatrixAttribute', 'Producto', 'size-color-matrix', 'fa-solid fa-table');
        $view->settings['btnDelete'] = false;
        $view->settings['btnNew'] = false;
        $view->settings['btnOptions'] = false;
        $view->settings['btnSave'] = false;
        $view->settings['btnUndo'] = false;
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'matrix-generate':
                return $this->handleMatrixGenerate();

            case 'matrix-save':
                return $this->handleMatrixSave();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getMatrixAttributes(): array
    {
        $attribute = new Atributo();
        $attributes = [];
        foreach ($attribute->all([], ['nombre' => 'ASC'], 0, 0) as $item) {
            $attributes[] = [
                'code' => $item->codatributo,
                'name' => $item->nombre,
                'selector' => (int)$item->num_selector,
            ];
        }

        return $attributes;
    }

    private function handleMatrixGenerate(): bool
    {
        $this->active = 'MatrixAttribute';
        $attributeX = Tools::noHtml($this->request->inputOrQuery('matrix_attribute_x', ''));
        $attributeY = Tools::noHtml($this->request->inputOrQuery('matrix_attribute_y', ''));
        $warehouse = Tools::noHtml($this->request->inputOrQuery('matrix_codalmacen', ''));

        return $this->prepareMatrix($attributeX, $attributeY, $warehouse);
    }

    private function handleMatrixSave(): bool
    {
        $this->active = 'MatrixAttribute';
        $attributeX = Tools::noHtml($this->request->inputOrQuery('matrix_attribute_x', ''));
        $attributeY = Tools::noHtml($this->request->inputOrQuery('matrix_attribute_y', ''));
        $warehouse = Tools::noHtml($this->request->inputOrQuery('matrix_codalmacen', ''));

        if (false === $this->prepareMatrix($attributeX, $attributeY, $warehouse)) {
            return false;
        }

        $stocks = $this->request->request->getArray('matrix_stock', true) ?? [];
        foreach ($this->matrixValuesY as $row) {
            $idY = (int)$row['id'];
            $rowStocks = $stocks[$idY] ?? [];
            foreach ($this->matrixValuesX as $column) {
                $idX = (int)$column['id'];
                $value = $rowStocks[$idX] ?? '';
                $quantity = max(0.0, (float)str_replace(',', '.', (string)$value));
                $this->updateVariantStock($idX, $idY, $quantity);
            }
        }

        Tools::log()->notice('matrix-stock-updated');
        return true;
    }

    private function prepareMatrix(string $attributeX, string $attributeY, string $warehouse): bool
    {
        $this->matrixAttributeX = $attributeX;
        $this->matrixAttributeY = $attributeY;
        $this->matrixWarehouse = empty($warehouse) ? Tools::settings('default', 'codalmacen') : $warehouse;
        $this->matrixHasData = false;
        $this->matrixValuesX = [];
        $this->matrixValuesY = [];
        $this->matrixGrid = [];
        $this->matrixBaseVariant = null;

        if (empty($attributeX) || empty($attributeY)) {
            Tools::log()->warning('matrix-select-two-attributes');
            return false;
        }
        if ($attributeX === $attributeY) {
            Tools::log()->warning('matrix-attributes-must-differ');
            return false;
        }

        $product = $this->loadMatrixProduct();
        if (null === $product) {
            return false;
        }

        $axisX = $this->loadAttribute($attributeX);
        $axisY = $this->loadAttribute($attributeY);
        if (null === $axisX || null === $axisY) {
            return false;
        }

        $fieldX = $this->getVariantFieldForAttribute($axisX);
        $fieldY = $this->getVariantFieldForAttribute($axisY);
        if (empty($fieldX) || empty($fieldY)) {
            return false;
        }

        $this->matrixValuesX = $this->getAttributeValues($axisX);
        $this->matrixValuesY = $this->getAttributeValues($axisY);

        if (empty($this->matrixValuesX) || empty($this->matrixValuesY)) {
            Tools::log()->warning('matrix-attributes-need-values');
            return false;
        }

        foreach ($this->matrixValuesY as $row) {
            $rowId = (int)$row['id'];
            $this->matrixGrid[$rowId] = [];
            foreach ($this->matrixValuesX as $column) {
                $colId = (int)$column['id'];
                $variant = $this->ensureVariantExists($product, [
                    $fieldX => $colId,
                    $fieldY => $rowId,
                ]);

                if (null === $variant) {
                    continue;
                }

                $stock = $this->loadVariantStock($variant, $this->matrixWarehouse);
                $this->matrixGrid[$rowId][$colId] = [
                    'variant' => $variant,
                    'stock' => $stock,
                ];
            }
        }

        $this->matrixHasData = true;
        return true;
    }

    private function loadMatrixProduct(): ?Producto
    {
        $idProduct = $this->request->inputOrQuery('idproducto', '');
        $product = $this->getModel();
        if (!empty($idProduct) && (!$product->exists() || (string)$product->idproducto !== (string)$idProduct)) {
            $product->loadFromCode($idProduct);
        }

        if (false === $product->exists()) {
            Tools::log()->warning('record-not-found');
            return null;
        }

        return $product;
    }

    private function loadAttribute(string $code): ?Atributo
    {
        $attribute = new Atributo();
        if (false === $attribute->load($code)) {
            Tools::log()->warning('matrix-attribute-not-found', ['%code%' => $code]);
            return null;
        }

        return $attribute;
    }

    /**
     * @param Atributo $attribute
     * @return array<int, array<string, mixed>>
     */
    private function getAttributeValues(Atributo $attribute): array
    {
        $values = [];
        foreach ($attribute->getValues() as $value) {
            $values[] = [
                'id' => (int)$value->id,
                'label' => $value->valor,
            ];
        }

        return $values;
    }

    private function getVariantFieldForAttribute(Atributo $attribute): string
    {
        $selector = (int)$attribute->num_selector;
        if ($selector < 1 || $selector > 4) {
            Tools::log()->warning('matrix-selector-required', ['%attribute%' => $attribute->nombre]);
            return '';
        }

        return 'idatributovalor' . $selector;
    }

    /**
     * @param Producto $product
     * @param array<string, int> $values
     */
    private function ensureVariantExists(Producto $product, array $values): ?Variante
    {
        $variant = new Variante();
        $where = [new DataBaseWhere('idproducto', $product->idproducto)];
        foreach ($values as $field => $valueId) {
            $where[] = new DataBaseWhere($field, $valueId);
        }

        if (true === $variant->loadWhere($where)) {
            return $variant;
        }

        $variant->idproducto = $product->idproducto;
        foreach ($values as $field => $valueId) {
            $variant->{$field} = $valueId;
        }

        $baseVariant = $this->getBaseVariant($product);
        if (null !== $baseVariant) {
            $variant->precio = $baseVariant->precio;
            $variant->coste = $baseVariant->coste;
        }
        $variant->referencia = '';

        if (false === $variant->save()) {
            Tools::log()->error('matrix-variant-create-error');
            return null;
        }

        return $variant;
    }

    private function loadVariantStock(Variante $variant, string $warehouse): float
    {
        $stock = new Stock();
        $where = [
            new DataBaseWhere('referencia', $variant->referencia),
            new DataBaseWhere('codalmacen', $warehouse),
        ];

        if (true === $stock->loadWhere($where)) {
            return (float)$stock->cantidad;
        }

        return 0.0;
    }

    private function updateVariantStock(int $valueX, int $valueY, float $quantity): void
    {
        if (false === isset($this->matrixGrid[$valueY][$valueX])) {
            return;
        }

        /** @var Variante $variant */
        $variant = $this->matrixGrid[$valueY][$valueX]['variant'];
        $stock = new Stock();
        $where = [
            new DataBaseWhere('referencia', $variant->referencia),
            new DataBaseWhere('codalmacen', $this->matrixWarehouse),
        ];

        if (true === $stock->loadWhere($where)) {
            $stock->cantidad = $quantity;
        } else {
            $stock->codalmacen = $this->matrixWarehouse;
            $stock->idproducto = $variant->idproducto;
            $stock->referencia = $variant->referencia;
            $stock->cantidad = $quantity;
        }

        $stock->save();
        $this->matrixGrid[$valueY][$valueX]['stock'] = $quantity;
    }

    private function getBaseVariant(Producto $product): ?Variante
    {
        if ($this->matrixBaseVariant instanceof Variante) {
            return $this->matrixBaseVariant;
        }

        $variant = new Variante();
        $where = [
            new DataBaseWhere('idproducto', $product->idproducto),
            new DataBaseWhere('referencia', $product->referencia),
        ];

        if (true === $variant->loadWhere($where)) {
            $this->matrixBaseVariant = $variant;
            return $this->matrixBaseVariant;
        }

        return null;
    }
}
