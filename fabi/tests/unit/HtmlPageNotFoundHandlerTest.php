<?php

use App\Exceptions\HtmlPageNotFoundHandler;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class HtmlPageNotFoundHandlerTest extends CIUnitTestCase
{
    public function testPageNotFoundAlwaysRendersHtmlForHttpClients(): void
    {
        $request = new IncomingRequest(config('App'), new URI('http://example.com/missing'), null, new UserAgent());
        $request->setHeader('Accept', 'application/json');
        $response = service('response');

        (new HtmlPageNotFoundHandler(config('Exceptions')))->handle(
            PageNotFoundException::forPageNotFound('Page manquante.'),
            $request,
            $response,
            404,
            EXIT_ERROR,
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Page manquante.', $body);
        $this->assertStringContainsString(lang('Errors.notFound'), $body);
        $this->assertStringNotContainsString('"exception"', $body);
    }
}
