<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2024
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Dinamic\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Controller to edit KPI dashboards.
 */
class EditKpiDashboard extends EditController
{
    public function getModelClassName(): string
    {
        return 'KpiDashboard';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'kpi-dashboard';
        $data['icon'] = 'fa-solid fa-chart-pie';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('top');

        $this->addEditListView('EditKpiWidget', 'KpiWidget', 'kpi-widgets', 'fa-solid fa-chart-line')
            ->setInLine(true)
            ->disableColumn('dashboard', true);

        $this->addEditListView('EditKpiDashboardShare', 'KpiDashboardShare', 'kpi-sharing', 'fa-solid fa-share-nodes')
            ->setInLine(true)
            ->disableColumn('dashboard', true);
    }

    protected function loadData($viewName, $view)
    {
        $mainViewName = $this->getMainViewName();

        switch ($viewName) {
            case 'EditKpiWidget':
                $dashboardId = $this->getViewModelValue($mainViewName, 'id');
                if (empty($dashboardId)) {
                    $view->setSettings('active', false);
                    break;
                }
                $view->loadData('', [new DataBaseWhere('iddashboard', $dashboardId)]);
                break;

            case 'EditKpiDashboardShare':
                $dashboardId = $this->getViewModelValue($mainViewName, 'id');
                if (empty($dashboardId)) {
                    $view->setSettings('active', false);
                    break;
                }
                $view->loadData('', [new DataBaseWhere('iddashboard', $dashboardId)]);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    protected function insertAction(): bool
    {
        $mainViewName = $this->getMainViewName();
        if (empty($this->views[$mainViewName]->model->owner)) {
            $this->views[$mainViewName]->model->owner = $this->user->nick;
        }

        return parent::insertAction();
    }
}
