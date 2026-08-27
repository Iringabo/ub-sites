<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

class SiteController extends BaseController
{
    public function select(): RedirectResponse
    {
        if (! service('adminAccess')->isSuperAdmin(auth()->user())) {
            return redirect()->back()->with('error', 'Seul un superadministrateur peut changer de site.');
        }

        $siteId = (int) $this->request->getPost('site_id');

        if (! service('siteResolver')->selectAdminSite($siteId, auth()->user())) {
            return redirect()->back()->with('error', 'Vous ne pouvez pas accéder à ce site.');
        }

        service('settingsService')->reset();
        service('contentTranslationService')->reset();

        return redirect()->to('/admin')->with('message', 'Le site d’administration a été changé.');
    }
}
