<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Marque la session comme active dans /admin. Consulter le site public
 * (aperçu) ne déconnecte plus : la déconnexion reste explicite.
 */
class AdminExitGuardFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): null
    {
        $path = trim((string) $request->getUri()->getPath(), '/');

        if ($path === 'healthz') {
            return null;
        }

        $isAdminArea = $path === 'admin' || str_starts_with($path, 'admin');

        if ($isAdminArea && auth()->user() !== null) {
            service('session')->set('admin_session_active', true);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
