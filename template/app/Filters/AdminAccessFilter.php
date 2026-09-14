<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminAccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): RedirectResponse|ResponseInterface|null
    {
        $access = service('adminAccess');
        $user = auth()->user();

        if ($access->isCentralAdminHost($request)) {
            if ($access->canAccessCentralAdmin($user)) {
                return null;
            }

            return service('response')
                ->setStatusCode(403)
                ->setBody(view('errors/html/error_403', [
                    'message' => 'Seul un superadministrateur peut accéder à cette administration centrale.',
                ]));
        }

        $hostSite = service('siteResolver')->instanceBoundSite($request);

        if ($access->canAccessFacultyAdmin((int) $hostSite->id, $user)) {
            return null;
        }

        return redirect()->to(site_url('/'))->with('error', 'Vous ne pouvez pas accéder à l’administration de cette faculté.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
