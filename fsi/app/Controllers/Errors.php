<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Cible de set404Override : une URL sans route rend toujours error_404.html,
 * y compris depuis les tests et les clients sans Accept: text/html.
 */
class Errors extends Controller
{
    public function show404(?string $message = null): ResponseInterface
    {
        $message = trim((string) $message);
        if ($message === '') {
            $message = lang('Errors.notFoundMessage');
        }

        return $this->response
            ->setStatusCode(404)
            ->setHeader('Content-Type', 'text/html; charset=UTF-8')
            ->setBody(view('errors/html/error_404', ['message' => $message]));
    }
}
