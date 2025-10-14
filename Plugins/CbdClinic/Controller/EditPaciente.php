<?php
namespace FacturaScripts\Plugins\CbdClinic\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\DocFilesTrait;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\CbdClinic\Model\Paciente;

class EditPaciente extends EditController
{
    use DocFilesTrait;

    public function getModelClassName(): string
    {
        return 'Paciente';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'sales';
        $data['title'] = 'Paciente';
        $data['icon'] = 'fa-solid fa-leaf';
        return $data;
    }

    protected function createViews()
    {
        $this->setTabsPosition('top');
        parent::createViews();

        $mainView = $this->getMainViewName();
        $this->views[$mainView]->title = 'Datos personales';
        $this->views[$mainView]->icon = 'fa-solid fa-id-card';

        $this->addHtmlView('ResumenPaciente', 'ResumenPaciente', 'Paciente', 'Resumen', 'fa-solid fa-gauge');
        $this->createDolenciasView();
        $this->createPlanesView();
        $this->createVisitasView();
        $this->createConsentimientosView();
        $this->createViewDocFiles('PacienteDocumentos');
        $this->createFacturacionView();
        $this->addHtmlView('AlertasPaciente', 'AlertasPaciente', 'Paciente', 'Alertas y tareas', 'fa-solid fa-bell');
    }

    protected function createDolenciasView(string $viewName = 'ListDolenciaReferida'): void
    {
        $this->addEditListView($viewName, 'DolenciaReferida', 'Dolencias y perfil terapéutico', 'fa-solid fa-heart-pulse')
            ->addSearchFields(['tipo', 'observaciones', 'objetivos']);
    }

    protected function createPlanesView(string $viewName = 'ListPlanCbd'): void
    {
        $this->addEditListView($viewName, 'PlanCbd', 'Plan/Productos CBD', 'fa-solid fa-bottle-droplet')
            ->addSearchFields(['codarticulo', 'lote', 'espectro']);
    }

    protected function createVisitasView(string $viewName = 'ListVisitaPaciente'): void
    {
        $this->addEditListView($viewName, 'VisitaPaciente', 'Visitas y seguimiento', 'fa-solid fa-stethoscope')
            ->addSearchFields(['tipo_visita', 'notas_visita', 'recomendaciones']);
    }

    protected function createConsentimientosView(string $viewName = 'ListConsentimientoPaciente'): void
    {
        $this->addEditListView($viewName, 'ConsentimientoPaciente', 'Consentimientos', 'fa-solid fa-file-signature')
            ->addSearchFields(['tipo', 'observaciones']);
    }

    protected function createFacturacionView(string $viewName = 'ListVentasPaciente'): void
    {
        $this->addListView($viewName, 'FacturaCliente', 'Facturación/CRM', 'fa-solid fa-file-invoice-dollar')
            ->addOrderBy(['fecha', 'hora'], 'date', 2)
            ->addOrderBy(['pvptotal'], 'total')
            ->addSearchFields(['codigo', 'codserie', 'observaciones', 'nombrecliente']);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'checkBoxes', false);
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-file':
                return $this->addFileAction();
            case 'delete-file':
                return $this->deleteFileAction();
            case 'edit-file':
                return $this->editFileAction();
            case 'unlink-file':
                return $this->unlinkFileAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function loadData($viewName, $view)
    {
        $mainView = $this->getMainViewName();
        if ($viewName === $mainView) {
            parent::loadData($viewName, $view);
            return;
        }

        /** @var Paciente $paciente */
        $paciente = $this->views[$mainView]->model;
        if (false === $paciente->exists()) {
            return;
        }

        switch ($viewName) {
            case 'ResumenPaciente':
                $this->loadResumenData($view, $paciente);
                break;
            case 'ListDolenciaReferida':
                $where = [new DataBaseWhere('idpaciente', $paciente->idpaciente)];
                $view->loadData('', $where, ['updated_at' => 'DESC']);
                break;
            case 'ListPlanCbd':
                $where = [new DataBaseWhere('idpaciente', $paciente->idpaciente)];
                $view->loadData('', $where, ['activo' => 'DESC', 'fecha_inicio' => 'DESC']);
                break;
            case 'ListVisitaPaciente':
                $where = [new DataBaseWhere('idpaciente', $paciente->idpaciente)];
                $view->loadData('', $where, ['fecha_hora' => 'DESC']);
                break;
            case 'ListConsentimientoPaciente':
                $where = [new DataBaseWhere('idpaciente', $paciente->idpaciente)];
                $view->loadData('', $where, ['fecha_otorgado' => 'DESC']);
                break;
            case 'PacienteDocumentos':
                $this->loadDataDocFiles($view, $this->getModelClassName(), (string) $paciente->idpaciente);
                break;
            case 'ListVentasPaciente':
                $this->loadFacturacionData($view, $paciente);
                break;
            case 'AlertasPaciente':
                $view->model = $paciente;
                $view->cursor = $this->buildAlertCursor($paciente);
                break;
        }
    }

    private function loadResumenData(BaseView $view, Paciente $paciente): void
    {
        $view->model = $paciente;
        $dolencias = $paciente->getDolenciasActivas();
        $plan = $paciente->getPlanActivo();
        $visitas = $paciente->getVisitasRecientes(10);
        $proximaCita = $this->getProximaCita($visitas);
        $ultimaCompra = $this->getUltimaFactura($paciente);
        $alertas = $this->decodeAlertas($paciente->banderas_alerta);

        $timeline = [];
        foreach ($visitas as $visita) {
            $timeline[] = [
                'fecha' => $visita->fecha_hora,
                'tipo' => 'visita',
                'descripcion' => $visita->tipo_visita . ': ' . trim((string) $visita->notas_visita)
            ];
            if (!empty($visita->proxima_cita)) {
                $timeline[] = [
                    'fecha' => $visita->proxima_cita,
                    'tipo' => 'cita',
                    'descripcion' => 'Próxima cita: ' . $visita->proxima_cita
                ];
            }
        }

        if ($plan) {
            $timeline[] = [
                'fecha' => $plan->fecha_inicio,
                'tipo' => 'plan',
                'descripcion' => 'Inicio de plan: ' . ($plan->codarticulo ?? '')
            ];
        }

        usort($timeline, static function ($a, $b) {
            return strcmp((string) $b['fecha'], (string) $a['fecha']);
        });

        $view->cursor = [
            'dolencias' => $dolencias,
            'plan' => $plan,
            'visitas' => $visitas,
            'proximaCita' => $proximaCita,
            'ultimaCompra' => $ultimaCompra,
            'alertas' => $alertas,
            'timeline' => $timeline
        ];
    }

    private function loadFacturacionData(BaseView $view, Paciente $paciente): void
    {
        if (empty($paciente->codcliente)) {
            $view->cursor = [];
            return;
        }

        $where = [new DataBaseWhere('codcliente', $paciente->codcliente)];
        $view->loadData('', $where, ['fecha' => 'DESC', 'hora' => 'DESC']);
    }

    private function buildAlertCursor(Paciente $paciente): array
    {
        return [
            'alertas' => $this->decodeAlertas($paciente->banderas_alerta),
            'notas' => $paciente->notas_internas,
            'preferencias' => [
                'noMarketing' => (bool) $paciente->privacidad_no_marketing,
                'soloSeguimiento' => (bool) $paciente->privacidad_solo_seguimiento,
                'metodo' => $paciente->metodo_contacto_preferido
            ]
        ];
    }

    private function decodeAlertas(?string $json): array
    {
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getProximaCita(array $visitas): ?string
    {
        $future = [];
        foreach ($visitas as $visita) {
            if (!empty($visita->proxima_cita) && $visita->proxima_cita >= Tools::dateTime()) {
                $future[] = $visita->proxima_cita;
            }
        }

        sort($future);
        return $future[0] ?? null;
    }

    private function getUltimaFactura(Paciente $paciente): ?FacturaCliente
    {
        if (empty($paciente->codcliente)) {
            return null;
        }

        $where = [new DataBaseWhere('codcliente', $paciente->codcliente)];
        return FacturaCliente::findWhere($where, ['fecha' => 'DESC', 'hora' => 'DESC']);
    }
}
