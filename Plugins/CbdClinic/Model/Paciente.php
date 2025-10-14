<?php
namespace FacturaScripts\Plugins\CbdClinic\Model;

use DateInterval;
use DateTime;
use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\User;

class Paciente extends ModelClass
{
    use ModelTrait;

    public const ESTADOS = ['Activo', 'Inactivo', 'Archivado'];
    public const SEXOS = ['F', 'M', 'No indica', 'Otro'];
    public const CONTACTO_PREFERIDO = ['Tel', 'Email', 'WhatsApp', 'SMS'];

    public $idpaciente;
    public $idcliente;
    public $codcliente;
    public $codigo_paciente;
    public $estado;
    public $fecha_alta;
    public $profesional_asignado;
    public $notas_internas;
    public $banderas_alerta;
    public $nombre;
    public $apellidos;
    public $nif;
    public $fecha_nacimiento;
    public $sexo;
    public $telefono;
    public $telefono_secundario;
    public $email;
    public $direccion;
    public $cp;
    public $ciudad;
    public $provincia;
    public $pais;
    public $metodo_contacto_preferido;
    public $privacidad_no_marketing;
    public $privacidad_solo_seguimiento;
    public $fecha_creacion;
    public $fecha_actualizacion;

    public function clear(): void
    {
        parent::clear();
        $this->estado = 'Activo';
        $this->fecha_alta = Tools::date();
        $this->privacidad_no_marketing = false;
        $this->privacidad_solo_seguimiento = false;
    }

    public function getEdad(): ?int
    {
        if (empty($this->fecha_nacimiento)) {
            return null;
        }

        try {
            $birth = new DateTime($this->fecha_nacimiento);
            $today = new DateTime();
            $diff = $today->diff($birth);
            return $diff instanceof DateInterval ? (int) $diff->y : null;
        } catch (Exception $exception) {
            Tools::log()->warning('invalid-date', ['%value%' => $this->fecha_nacimiento, 'exception' => $exception->getMessage()]);
            return null;
        }
    }

    public function getCliente(): Cliente
    {
        $cliente = new Cliente();
        if (!empty($this->codcliente)) {
            $cliente->loadFromCode($this->codcliente);
        } elseif (!empty($this->idcliente)) {
            $cliente->load($this->idcliente);
        }

        return $cliente;
    }

    public function getProfesional(): ?User
    {
        if (empty($this->profesional_asignado)) {
            return null;
        }

        $user = new User();
        return $user->loadFromCode($this->profesional_asignado) ? $user : null;
    }

    public function getDolenciasActivas(): array
    {
        $where = [
            new DataBaseWhere('idpaciente', $this->idpaciente),
            new DataBaseWhere('activo', true)
        ];

        return DolenciaReferida::all($where, ['tipo' => 'ASC']);
    }

    public function getPlanActivo(): ?PlanCbd
    {
        $where = [
            new DataBaseWhere('idpaciente', $this->idpaciente),
            new DataBaseWhere('activo', true)
        ];

        return PlanCbd::findWhere($where, ['fecha_inicio' => 'DESC']);
    }

    public function getVisitasRecientes(int $limit = 5): array
    {
        $where = [new DataBaseWhere('idpaciente', $this->idpaciente)];
        return VisitaPaciente::all($where, ['fecha_hora' => 'DESC'], 0, $limit);
    }

    public static function primaryColumn(): string
    {
        return 'idpaciente';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'nombre';
    }

    public static function tableName(): string
    {
        return 'fs_med_pacientes';
    }

    public function test(): bool
    {
        $this->codigo_paciente = Tools::noHtml($this->codigo_paciente ?? '');
        if (empty($this->codigo_paciente)) {
            $baseName = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) ($this->nombre ?? '')));
            $prefix = substr($baseName, 0, 3);
            $prefix = empty($prefix) ? 'CBD' : $prefix;
            $this->codigo_paciente = $prefix . Tools::randomString(5);
        }

        $this->estado = $this->filterEnum($this->estado, self::ESTADOS, 'Activo');
        $this->sexo = $this->filterEnum($this->sexo, self::SEXOS, null);
        $this->metodo_contacto_preferido = $this->filterEnum(
            $this->metodo_contacto_preferido,
            self::CONTACTO_PREFERIDO,
            null
        );

        $this->email = Tools::noHtml($this->email);
        if (!empty($this->email) && false === filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            Tools::log()->warning('invalid-email');
            return false;
        }

        $this->nif = Tools::noHtml($this->nif);
        $this->nombre = Tools::noHtml($this->nombre);
        $this->apellidos = Tools::noHtml($this->apellidos);
        $this->telefono = Tools::noHtml($this->telefono);
        $this->telefono_secundario = Tools::noHtml($this->telefono_secundario);
        $this->direccion = Tools::noHtml($this->direccion);
        $this->cp = Tools::noHtml($this->cp);
        $this->ciudad = Tools::noHtml($this->ciudad);
        $this->provincia = Tools::noHtml($this->provincia);
        $this->pais = Tools::noHtml($this->pais);
        $this->notas_internas = Tools::noHtml($this->notas_internas);

        $this->privacidad_no_marketing = Tools::boolval($this->privacidad_no_marketing);
        $this->privacidad_solo_seguimiento = Tools::boolval($this->privacidad_solo_seguimiento);

        return parent::test();
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
