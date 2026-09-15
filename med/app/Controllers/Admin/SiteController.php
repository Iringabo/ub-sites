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

        $returnTo = trim((string) $this->request->getPost('return_to'));
        if ($returnTo !== '' && str_starts_with($returnTo, site_url('admin'))) {
            $target = $returnTo;
        } else {
            $referer = (string) ($this->request->getHeaderLine('Referer') ?: '');
            $adminBase = site_url('admin');
            if ($referer !== '' && str_starts_with($referer, $adminBase)) {
                $target = $referer;
            } else {
                $target = service('adminAccess')->isCentralAdminHost($this->request)
                    ? site_url('admin/site')
                    : site_url('admin');
            }
        }

        return redirect()->to($target)->with('message', 'Le site d’administration a été changé.');
    }
}
