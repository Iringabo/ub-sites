<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Home extends BaseController
{
    public function index(): string|RedirectResponse
    {
        if (service('adminAccess')->isCentralAdminHost($this->request)) {
            return redirect()->to('/admin');
        }

        $data        = service('homePageService')->data();
        $homeContent = $data['homeContent'];

        return view('home/index', [
            ...$data,
            'title'        => site_text_or_placeholder($homeContent?->seo_title ?? null, site_text_or_placeholder($this->siteSettings['seo.default_title'] ?? null)),
            'description'  => site_text_or_placeholder($homeContent?->seo_description ?? null, site_text_or_placeholder($this->siteSettings['seo.default_description'] ?? null)),
            'activePage'   => 'home',
            'siteSettings' => $this->siteSettings,
        ]);
    }
}
