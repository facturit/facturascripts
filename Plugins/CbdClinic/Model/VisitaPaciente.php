<?php
namespace FacturaScripts\Plugins\CbdClinic\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use JsonException;

class VisitaPaciente extends ModelClass
{
    use ModelTrait;

    public const TIPOS = ['Primera', 'Seguimiento', 'Incidencia', 'Telefónica', 'Online', 'Presencial'];

    public $id_visita;
    public $idpaciente;
    public $fecha_hora;
    public $tipo_visita;
    public $canal;
    public $profesional;
    public $id_dolencia;
    public $escala_dolor;
    public $escala_ansiedad;
    public $escala_sueno;
    public $peso;
    public $imc;
    public $notas_visita;
    public $ajustes_plan;
    public $recomendaciones;
    public $adjuntos;
    public $proxima_cita;
    public $recordatorios_enviados;
    public $id_plan;
    public $venta_origen;
    public $lote_utilizado;
    public $created_at;
    public $updated_at;

    public function clear(): void
    {
        parent::clear();
        $this->fecha_hora = Tools::dateTime();
        $this->tipo_visita = 'Seguimiento';
        $this->recordatorios_enviados = false;
    }

    public function paciente(): ?Paciente
    {
        if (empty($this->idpaciente)) {
            return null;
        }

        $paciente = new Paciente();
        return $paciente->load($this->idpaciente) ? $paciente : null;
    }

    public function dolencia(): ?DolenciaReferida
    {
        if (empty($this->id_dolencia)) {
            return null;
        }

        $dolencia = new DolenciaReferida();
        return $dolencia->load($this->id_dolencia) ? $dolencia : null;
    }

    public function plan(): ?PlanCbd
    {
        if (empty($this->id_plan)) {
            return null;
        }

        $plan = new PlanCbd();
        return $plan->load($this->id_plan) ? $plan : null;
    }

    public static function primaryColumn(): string
    {
        return 'id_visita';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'fecha_hora';
    }

    public static function tableName(): string
    {
        return 'fs_med_visitas';
    }

    public function test(): bool
    {
        $this->tipo_visita = $this->filterEnum($this->tipo_visita, self::TIPOS, 'Seguimiento');
        $this->canal = Tools::noHtml($this->canal);
        $this->profesional = Tools::noHtml($this->profesional);
        $this->notas_visita = Tools::noHtml($this->notas_visita);
        $this->recomendaciones = Tools::noHtml($this->recomendaciones);
        $this->venta_origen = Tools::noHtml($this->venta_origen);
        $this->lote_utilizado = Tools::noHtml($this->lote_utilizado);
        $this->adjuntos = Tools::noHtml($this->adjuntos);
        $this->ajustes_plan = empty($this->ajustes_plan) ? null : $this->encodeJson($this->ajustes_plan);
        $this->escala_dolor = $this->normalizeScale($this->escala_dolor);
        $this->escala_ansiedad = $this->normalizeScale($this->escala_ansiedad);
        $this->escala_sueno = $this->normalizeScale($this->escala_sueno);
        $this->peso = $this->normalizeNumeric($this->peso);
        $this->imc = $this->normalizeNumeric($this->imc, 1);
        $this->recordatorios_enviados = Tools::boolval($this->recordatorios_enviados);

        return parent::test();
    }

    private function normalizeScale($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(10, (int) $value));
    }

    private function normalizeNumeric($value, int $decimals = 2): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, $decimals);
    }

    private function encodeJson($value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            Tools::log()->warning('json-encode-error', ['exception' => $exception->getMessage()]);
            return null;
        }

        return $encoded ?: null;
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
