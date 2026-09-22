<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class SiteController extends BaseController
{
    public function select(): RedirectResponse|ResponseInterface
    {
        if (! service('adminAccess')->isSuperAdmin(auth()->user())) {
            return $this->selectionFailure('Seul un superadministrateur peut changer de site.');
        }

        $siteId = (int) $this->request->getPost('site_id');

        if (! service('siteResolver')->selectAdminSite($siteId, auth()->user())) {
            return $this->selectionFailure('Vous ne pouvez pas accéder à ce site.');
        }

        service('settingsService')->reset();
        service('contentTranslationService')->reset();

        $target = $this->safeAdminLandingUrl(
            trim((string) $this->request->getPost('return_to')),
            (string) ($this->request->getHeaderLine('Referer') ?: ''),
        );

        if ($this->isHtmxRequest()) {
            // Full navigation to the module index so scoped lists reload for the new site_id.
            return $this->response
                ->setHeader('HX-Redirect', $target)
                ->setStatusCode(204);
        }

        return redirect()->to($target)->with('message', 'Le site d’administration a été changé.');
    }

    private function selectionFailure(string $message): RedirectResponse|ResponseInterface
    {
        if ($this->isHtmxRequest()) {
            return $this->response
                ->setHeader('HX-Redirect', site_url('admin'))
                ->setStatusCode(204);
        }

        return redirect()->back()->with('error', $message);
    }

    private function isHtmxRequest(): bool
    {
        return strtolower($this->request->getHeaderLine('HX-Request')) === 'true';
    }

    /**
     * Never land on an edit-by-id URL after a site switch: lists/settings reload for the new site.
     * Accepts absolute URLs or admin-relative paths so a mismatched baseURL host still works.
     */
    private function safeAdminLandingUrl(string $returnTo, string $referer): string
    {
        $adminPath = rtrim((string) (parse_url(site_url('admin'), PHP_URL_PATH) ?: '/admin'), '/');
        $path = $this->adminPathFromCandidate($returnTo, $adminPath)
            ?: $this->adminPathFromCandidate($referer, $adminPath);

        if ($path === '') {
            return service('adminAccess')->isCentralAdminHost($this->request)
                ? site_url('admin/site')
                : site_url('admin');
        }

        $relative = trim((string) preg_replace('#^' . preg_quote($adminPath, '#') . '#', '', $path), '/');

        if ($relative === '') {
            return site_url('admin');
        }

        $segments = explode('/', $relative);

        // /admin/{resource}/{id}/edit → /admin/{resource}
        if (count($segments) >= 3 && ctype_digit($segments[1]) && $segments[2] === 'edit') {
            return site_url('admin/' . $segments[0]);
        }

        // /admin/{resource}/{id} → /admin/{resource}
        if (count($segments) === 2 && ctype_digit($segments[1])) {
            return site_url('admin/' . $segments[0]);
        }

        // /admin/users/{id}/edit|password → /admin/users
        if (($segments[0] ?? '') === 'users' && isset($segments[1]) && ctype_digit($segments[1])) {
            return site_url('admin/users');
        }

        // /admin/settings/global stays; other deep paths collapse to first segment when numeric id present
        return site_url('admin/' . $segments[0] . (isset($segments[1]) && ! ctype_digit($segments[1]) ? '/' . $segments[1] : ''));
    }

    private function adminPathFromCandidate(string $candidate, string $adminPath): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }

        $path = str_starts_with($candidate, '/')
            ? (parse_url($candidate, PHP_URL_PATH) ?: $candidate)
            : (string) (parse_url($candidate, PHP_URL_PATH) ?: '');

        if ($path === $adminPath || str_starts_with($path, $adminPath . '/')) {
            return $path;
        }

        return '';
    }
}
