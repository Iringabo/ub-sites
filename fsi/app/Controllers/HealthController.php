<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

final class HealthController extends Controller
{
    public function show(): ResponseInterface
    {
        return $this->response
            ->setStatusCode(204)
            ->setHeader('Cache-Control', 'no-store')
            ->setBody('');
    }
}
