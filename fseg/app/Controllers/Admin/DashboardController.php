<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class DashboardController extends BaseController
{
    public function index(): string
    {
        if (service('adminAccess')->isCentralAdminHost($this->request)) {
            return view('admin/central/dashboard', [
                'title'       => 'Administration centrale | Université du Burundi',
                'activeAdmin' => 'dashboard',
                'dashboard'   => service('adminDashboardService')->centralData(),
            ]);
        }

        return view('admin/dashboard', [
            'title'      => 'Tableau de bord | Administration',
            'activeAdmin'=> 'dashboard',
            'dashboard'  => service('adminDashboardService')->data(),
        ]);
    }
}
