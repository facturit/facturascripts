<?php
namespace FacturaScripts\Plugins\CbdClinic\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class PlanCbd extends ModelClass
{
    use ModelTrait;

    public const ESTADOS = ['Activo', 'En prueba', 'Finalizado'];
    public const FRECUENCIAS = ['1/día', '2/día', '3/día', 'a demanda', 'otro'];
    public const VIAS = ['sublingual', 'tópico', 'cápsula', 'comestibles'];

    public $id_plan;
    public $idpaciente;
    public $estado_plan;
    public $fecha_inicio;
    public $fecha_fin;
    public $codarticulo;
    public $via_administracion;
    public $dosificacion;
    public $frecuencia;
    public $instrucciones;
    public $efectividad_percibida;
    public $efectos_adversos;
    public $notas_seguimiento;
    public $lote;
    public $espectro;
    public $porcentaje_cbd;
    public $porcentaje_cbg;
    public $thc_declarado;
    public $url_coa;
    public $activo;
    public $created_at;
    public $updated_at;

    public function clear(): void
    {
        parent::clear();
        $this->estado_plan = 'En prueba';
        $this->fecha_inicio = Tools::date();
        $this->activo = true;
    }

    public function paciente(): ?Paciente
    {
        if (empty($this->idpaciente)) {
            return null;
        }

        $paciente = new Paciente();
        return $paciente->load($this->idpaciente) ? $paciente : null;
    }

    public function getVisitasAsociadas(): array
    {
        $where = [new DataBaseWhere('id_plan', $this->id_plan)];
        return VisitaPaciente::all($where, ['fecha_hora' => 'DESC']);
    }

    public static function primaryColumn(): string
    {
        return 'id_plan';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'codarticulo';
    }

    public static function tableName(): string
    {
        return 'fs_med_planes_cbd';
    }

    public function test(): bool
    {
        $this->estado_plan = $this->filterEnum($this->estado_plan, self::ESTADOS, 'En prueba');
        $this->via_administracion = $this->filterEnum($this->via_administracion, self::VIAS, null);
        $this->frecuencia = $this->filterEnum($this->frecuencia, self::FRECUENCIAS, null);
        $this->codarticulo = Tools::noHtml($this->codarticulo);
        $this->dosificacion = Tools::noHtml($this->dosificacion);
        $this->instrucciones = Tools::noHtml($this->instrucciones);
        $this->efectos_adversos = Tools::noHtml($this->efectos_adversos);
        $this->notas_seguimiento = Tools::noHtml($this->notas_seguimiento);
        $this->lote = Tools::noHtml($this->lote);
        $this->espectro = Tools::noHtml($this->espectro);
        $this->url_coa = Tools::noHtml($this->url_coa);
        $this->porcentaje_cbd = $this->normalizePercentage($this->porcentaje_cbd);
        $this->porcentaje_cbg = $this->normalizePercentage($this->porcentaje_cbg);
        $this->thc_declarado = $this->normalizePercentage($this->thc_declarado);
        $this->efectividad_percibida = $this->normalizeScale($this->efectividad_percibida);
        $this->activo = Tools::boolval($this->activo);

        return parent::test();
    }

    private function normalizePercentage($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0.0, min(100.0, (float) $value));
    }

    private function normalizeScale($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(10, (int) $value));
    }

    private function filterEnum(?string $value, array $options, ?string $default): ?string
    {
        $value = Tools::noHtml($value);
        if (null === $value) {
            return $default;
        }

        return in_array($value, $options, true) ? $value : $default;
    }
}
