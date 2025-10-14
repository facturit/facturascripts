<?php
namespace FacturaScripts\Plugins\CbdClinic\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class ConsentimientoPaciente extends ModelClass
{
    use ModelTrait;

    public const TIPOS = [
        'tratamiento datos salud',
        'comunicaciones seguimiento',
        'cesión a terceros'
    ];

    public $id_consentimiento;
    public $idpaciente;
    public $tipo;
    public $fecha_otorgado;
    public $medio;
    public $documento;
    public $revocado;
    public $fecha_revocacion;
    public $observaciones;
    public $created_at;
    public $updated_at;

    public function clear(): void
    {
        parent::clear();
        $this->fecha_otorgado = Tools::dateTime();
        $this->revocado = false;
    }

    public static function primaryColumn(): string
    {
        return 'id_consentimiento';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'tipo';
    }

    public static function tableName(): string
    {
        return 'fs_med_consentimientos';
    }

    public function test(): bool
    {
        $this->tipo = $this->filterEnum($this->tipo, self::TIPOS, self::TIPOS[0]);
        $this->medio = Tools::noHtml($this->medio);
        $this->documento = Tools::noHtml($this->documento);
        $this->observaciones = Tools::noHtml($this->observaciones);
        $this->revocado = Tools::boolval($this->revocado);

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

    private function filterEnum(?string $value, array $options, ?string $default): ?string
    {
        $value = Tools::noHtml($value);
        if (null === $value) {
            return $default;
        }

        return in_array($value, $options, true) ? $value : $default;
    }
}
