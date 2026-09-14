<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class DashboardController extends BaseController
{
    public function index(): string
    {
        if (service('adminAccess')->isCentralAdminHost($this->request)) {
            return view('admin/central/dashboard', [
                'title'       => 'Superadministration | Université du Burundi',
                'activeAdmin' => 'dashboard',
                'dashboard'   => service('adminDashboardService')->centralData(),
            ]);
        }

        return $this->facultyWorkspace();
    }

    public function site(): string
    {
        return $this->facultyWorkspace('site');
    }

    private function facultyWorkspace(string $activeAdmin = 'dashboard'): string
    {
        return view('admin/dashboard', [
            'title'       => 'Tableau de bord | Administration',
            'activeAdmin' => $activeAdmin,
            'dashboard'   => service('adminDashboardService')->data(),
            'onboarding'  => service('adminDashboardService')->onboarding(),
        ]);
    }
}
