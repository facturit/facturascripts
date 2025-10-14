<?php
namespace FacturaScripts\Plugins\CbdClinic\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\CbdClinic\Model\Paciente;

class ListPaciente extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'sales';
        $data['title'] = 'Pacientes CBD';
        $data['icon'] = 'fa-solid fa-leaf';
        return $data;
    }

    protected function createViews()
    {
        $view = $this->addView('ListPaciente', 'Paciente', 'Pacientes CBD', 'fa-solid fa-leaf')
            ->addSearchFields(['codigo_paciente', 'nombre', 'apellidos', 'telefono', 'email', 'banderas_alerta']);

        $this->addFilterSelect($view->getViewName(), 'estado', 'Estado', 'estado', $this->buildEnumOptions(Paciente::ESTADOS));
        $this->addFilterSelect($view->getViewName(), 'metodo', 'Método contacto', 'metodo_contacto_preferido', $this->buildEnumOptions(Paciente::CONTACTO_PREFERIDO));
        $profesionales = $this->codeModel->all(Paciente::tableName(), 'profesional_asignado', 'profesional_asignado', true);
        if (count($profesionales) > 0) {
            $this->addFilterSelect($view->getViewName(), 'profesional', 'Profesional', 'profesional_asignado', $profesionales);
        }
    }

    private function buildEnumOptions(array $values): array
    {
        $options = [];
        foreach ($values as $value) {
            $options[] = ['code' => $value, 'description' => $value];
        }

        return $options;
    }
}
