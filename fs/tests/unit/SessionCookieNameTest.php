<?php

use App\Support\InstanceCookieNames;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Auth;
use Config\Session;

/**
 * @internal
 */
final class SessionCookieNameTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        unset(
            $_ENV['session.cookieName'],
            $_SERVER['session.cookieName'],
            $_ENV['session.rememberCookieName'],
            $_SERVER['session.rememberCookieName'],
            $_ENV['app.centralAdminMode'],
            $_SERVER['app.centralAdminMode'],
            $_ENV['app.siteSlug'],
            $_SERVER['app.siteSlug'],
        );
        putenv('session.cookieName');
        putenv('session.rememberCookieName');
        putenv('app.centralAdminMode');
        putenv('app.siteSlug');
        parent::tearDown();
    }

    public function testCookieNameUsesEnvWhenValid(): void
    {
        $_ENV['session.cookieName'] = 'ci_session_fseg';
        $_SERVER['session.cookieName'] = 'ci_session_fseg';
        putenv('session.cookieName=ci_session_fseg');

        $session = new Session();

        $this->assertSame('ci_session_fseg', $session->cookieName);
    }

    public function testCentralInstanceFallsBackToCentralCookieName(): void
    {
        putenv('session.cookieName');
        unset($_ENV['session.cookieName'], $_SERVER['session.cookieName']);
        $_ENV['app.centralAdminMode'] = 'true';
        $_SERVER['app.centralAdminMode'] = 'true';
        putenv('app.centralAdminMode=true');

        $session = new Session();

        $this->assertSame('ci_session_central', $session->cookieName);
    }

    public function testRememberCookieUsesEnvWhenValid(): void
    {
        $_ENV['session.rememberCookieName'] = 'remember_fseg';
        $_SERVER['session.rememberCookieName'] = 'remember_fseg';
        putenv('session.rememberCookieName=remember_fseg');

        $auth = new Auth();

        $this->assertSame('remember_fseg', $auth->sessionConfig['rememberCookieName']);
    }

    public function testRememberCookieFallsBackToFacultySlug(): void
    {
        putenv('session.rememberCookieName');
        unset($_ENV['session.rememberCookieName'], $_SERVER['session.rememberCookieName']);
        $_ENV['app.centralAdminMode'] = 'false';
        $_SERVER['app.centralAdminMode'] = 'false';
        putenv('app.centralAdminMode=false');
        $_ENV['app.siteSlug'] = 'fsi';
        $_SERVER['app.siteSlug'] = 'fsi';
        putenv('app.siteSlug=fsi');

        $this->assertSame('remember_fsi', InstanceCookieNames::remember());
        $this->assertSame('remember_fsi', (new Auth())->sessionConfig['rememberCookieName']);
    }

    public function testRememberCookieFallsBackToCentral(): void
    {
        putenv('session.rememberCookieName');
        unset($_ENV['session.rememberCookieName'], $_SERVER['session.rememberCookieName']);
        $_ENV['app.centralAdminMode'] = 'true';
        $_SERVER['app.centralAdminMode'] = 'true';
        putenv('app.centralAdminMode=true');

        $this->assertSame('remember_central', InstanceCookieNames::remember());
        $this->assertSame('remember_central', (new Auth())->sessionConfig['rememberCookieName']);
    }

    public function testLocaleCookieIsPerInstance(): void
    {
        $_ENV['app.centralAdminMode'] = 'false';
        $_SERVER['app.centralAdminMode'] = 'false';
        putenv('app.centralAdminMode=false');
        $_ENV['app.siteSlug'] = 'fseg';
        $_SERVER['app.siteSlug'] = 'fseg';
        putenv('app.siteSlug=fseg');

        $this->assertSame('site_locale_fseg', InstanceCookieNames::locale());

        $_ENV['app.centralAdminMode'] = 'true';
        $_SERVER['app.centralAdminMode'] = 'true';
        putenv('app.centralAdminMode=true');

        $this->assertSame('site_locale_central', InstanceCookieNames::locale());
    }

    public function testInvalidCookieNamesFallBackToInstanceDefaults(): void
    {
        $_ENV['session.cookieName'] = 'Not Valid!';
        $_SERVER['session.cookieName'] = 'Not Valid!';
        putenv('session.cookieName=Not Valid!');
        $_ENV['session.rememberCookieName'] = 'also invalid';
        $_SERVER['session.rememberCookieName'] = 'also invalid';
        putenv('session.rememberCookieName=also invalid');
        $_ENV['app.centralAdminMode'] = 'false';
        $_SERVER['app.centralAdminMode'] = 'false';
        putenv('app.centralAdminMode=false');
        $_ENV['app.siteSlug'] = 'fseg';
        $_SERVER['app.siteSlug'] = 'fseg';
        putenv('app.siteSlug=fseg');

        $this->assertNull(InstanceCookieNames::valid('Not Valid!'));
        $this->assertSame('ci_session_fseg', InstanceCookieNames::session());
        $this->assertSame('remember_fseg', InstanceCookieNames::remember());
        $this->assertSame('ci_session_fseg', (new Session())->cookieName);
        $this->assertSame('remember_fseg', (new Auth())->sessionConfig['rememberCookieName']);
    }

    public function testCentralInstanceDisablesRememberMe(): void
    {
        $_ENV['app.centralAdminMode'] = 'true';
        $_SERVER['app.centralAdminMode'] = 'true';
        putenv('app.centralAdminMode=true');

        $auth = new Auth();

        $this->assertFalse($auth->sessionConfig['allowRemembering']);
    }
}
