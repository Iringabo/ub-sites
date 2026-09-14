<?php

namespace App\Exceptions;

use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Les 404 publics rendent toujours la page HTML, y compris pour curl
 * et les clients sans Accept: text/html. Les autres exceptions gardent
 * le gestionnaire CodeIgniter (traces JSON en développement).
 */
class HtmlPageNotFoundHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        $message = trim($exception->getMessage());
        if ($message === '') {
            $message = lang('Errors.notFoundMessage');
        }

        $statusCode = $statusCode >= 400 && $statusCode < 600 ? $statusCode : 404;

        if ($request instanceof IncomingRequest) {
            $response->setStatusCode($statusCode);
            $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
            $response->setBody(view('errors/html/error_404', ['message' => $message]));

            if (ENVIRONMENT !== 'testing' && ! headers_sent()) {
                $response->send();
                exit($exitCode);
            }

            return;
        }

        echo $message . PHP_EOL;

        if (ENVIRONMENT !== 'testing') {
            exit($exitCode);
        }
    }
}
