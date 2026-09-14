<?php

use App\Services\AdminAccessService;
use App\Support\TrustedProxies;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

/**
 * @internal
 */
final class TrustedProxyHostTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        config('App')->proxyIPs = [];
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    public function testUntrustedForwardedHostIsIgnoredForAdminHost(): void
    {
        config('App')->proxyIPs = [];
        $request = $this->requestWithoutHost(['X-Forwarded-Host' => 'admin.ub.edu.bi']);

        $this->assertSame([], TrustedProxies::hostCandidates($request));
        $this->assertFalse((new AdminAccessService())->isCentralAdminHost($request));
    }

    public function testTrustedForwardedHostIsUsedForAdminHost(): void
    {
        config('App')->proxyIPs = ['127.0.0.1' => 'X-Forwarded-For'];
        $request = $this->requestWithoutHost(['X-Forwarded-Host' => 'admin.ub.edu.bi']);

        $this->assertSame(['admin.ub.edu.bi'], TrustedProxies::hostCandidates($request));
        $this->assertTrue((new AdminAccessService())->isCentralAdminHost($request));
    }

    public function testProxyIpsAreLoadedFromCommaSeparatedEnv(): void
    {
        $previous = getenv('app.proxyIPs');
        $_ENV['app.proxyIPs'] = '10.0.0.1, 10.0.0.2';
        $_SERVER['app.proxyIPs'] = '10.0.0.1, 10.0.0.2';
        putenv('app.proxyIPs=10.0.0.1, 10.0.0.2');

        try {
            $app = new App();
            $this->assertSame([
                '10.0.0.1' => 'X-Forwarded-For',
                '10.0.0.2' => 'X-Forwarded-For',
            ], $app->proxyIPs);
        } finally {
            unset($_ENV['app.proxyIPs'], $_SERVER['app.proxyIPs']);
            if ($previous === false) {
                putenv('app.proxyIPs');
            } else {
                putenv('app.proxyIPs=' . $previous);
            }
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function requestWithoutHost(array $headers): IncomingRequest
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['SERVER_NAME']);

        foreach ($headers as $name => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $request = new IncomingRequest(config('App'), new URI('http://example.com/admin'), null, new UserAgent());
        $request->removeHeader('Host');

        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }

        return $request;
    }
}
