<?php
namespace FacturaScripts\Plugins\CbdClinic\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class DolenciaReferida extends ModelClass
{
    use ModelTrait;

    public const FRECUENCIAS = ['diaria', 'semanal', 'esporádica'];

    public $id_dolencia;
    public $idpaciente;
    public $tipo;
    public $intensidad;
    public $frecuencia;
    public $fecha_inicio_aprox;
    public $observaciones;
    public $alergias;
    public $medicacion_actual;
    public $contraindicaciones;
    public $objetivos;
    public $activo;
    public $created_at;
    public $updated_at;

    public function clear(): void
    {
        parent::clear();
        $this->intensidad = 0;
        $this->activo = true;
    }

    public static function primaryColumn(): string
    {
        return 'id_dolencia';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'tipo';
    }

    public static function tableName(): string
    {
        return 'fs_med_dolencias';
    }

    public function test(): bool
    {
        $this->tipo = Tools::noHtml($this->tipo);
        $this->frecuencia = $this->filterEnum($this->frecuencia, self::FRECUENCIAS);
        $this->observaciones = Tools::noHtml($this->observaciones);
        $this->alergias = Tools::noHtml($this->alergias);
        $this->medicacion_actual = Tools::noHtml($this->medicacion_actual);
        $this->contraindicaciones = Tools::noHtml($this->contraindicaciones);
        $this->objetivos = Tools::noHtml($this->objetivos);
        $this->intensidad = max(0, min(10, (int) $this->intensidad));
        $this->activo = Tools::boolval($this->activo);

        return parent::test();
    }

    public function paciente(): ?Paciente
    {
        if (empty($this->idpaciente)) {
            return null;
        }

        $paciente = new Paciente();
        return $paciente->load($this->idpaciente) ? $paciente : null;
    }

    public static function catalogoDolencias(): array
    {
        return [
            'dolor crónico',
            'ansiedad',
            'insomnio',
            'migraña',
            'inflamación',
            'estrés',
            'otros'
        ];
    }

    private function filterEnum(?string $value, array $options): ?string
    {
        $value = Tools::noHtml($value);
        return in_array($value, $options, true) ? $value : null;
    }
}
