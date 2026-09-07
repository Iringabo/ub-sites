<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sécurité de session : quitter la zone d'administration déconnecte.
 *
 * Tant qu'un utilisateur travaille dans /admin, la session est marquée
 * « admin_session_active ». Dès qu'il consulte une page publique du site,
 * la session est détruite : tout retour dans l'administration exige une
 * nouvelle connexion. La sonde /healthz reste neutre.
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
        $isAuthArea  = in_array($path, ['login', 'logout'], true)
            || str_starts_with($path, 'auth/');

        if ($isAdminArea) {
            if (auth()->user() !== null) {
                service('session')->set('admin_session_active', true);
            }

            return null;
        }

        if ($isAuthArea) {
            return null;
        }

        // Zone publique : quitter l'administration déconnecte.
        if ((bool) service('session')->get('admin_session_active') && auth()->user() !== null) {
            auth()->logout();
            service('session')->destroy();
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
