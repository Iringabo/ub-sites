<?php

use App\Database\Seeds\TemplateStarterSeeder;
use App\Support\InstanceCookieNames;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Consulter le site public depuis l’administration (aperçu) ne détruit
 * plus la session. La déconnexion reste l’action explicite.
 *
 * @internal
 */
final class AdminSessionLifecycleTest extends CIUnitTestCase
{
    use AuthenticationTesting;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAuthState();
    }

    protected function tearDown(): void
    {
        $this->resetAuthState();
        parent::tearDown();
    }

    private function resetAuthState(): void
    {
        try {
            auth('session')->getAuthenticator()->logout();
        } catch (Throwable) {
        }

        $_SESSION = [];
        $this->withSession([]);
    }

    private function facultyAdmin(): User
    {
        $users = model(UserModel::class);
        $existing = $users->where('username', 'lifecycle-admin')->first();
        if ($existing instanceof User) {
            return $existing;
        }

        $users->save(new User([
            'username' => 'lifecycle-admin',
            'email'    => 'lifecycle-admin@example.test',
            'password' => 'MotDePasseLifecycle!2026',
            'active'   => 1,
        ]));

        $user = $users->findById((int) $users->getInsertID());
        $user->addGroup('admin');
        $user->activate();
        service('siteResolver')->syncUserSites((int) $user->id, [1], 'site_admin');

        return $user;
    }

    public function testPreviewingPublicSiteKeepsAdminSession(): void
    {
        $this->actingAs($this->facultyAdmin());

        $this->get('/admin')->assertOK();
        $this->get('/')->assertOK();
        $this->get('/admin')->assertOK();
    }

    public function testExplicitLogoutEndsAdminSession(): void
    {
        $this->actingAs($this->facultyAdmin());

        $this->get('/admin')->assertOK();
        $this->get('/logout');
        $this->get('/admin')->assertRedirectTo(site_url('login'));
    }

    public function testHealthzProbeDoesNotEndTheAdminSession(): void
    {
        $this->actingAs($this->facultyAdmin());

        $this->get('/admin')->assertOK();
        $this->get('/healthz')->assertStatus(204);
        $this->get('/admin')->assertOK();
    }

    public function testGuestBrowsingPublicSiteIsUnaffected(): void
    {
        $this->get('/')->assertOK();
        $this->get('/admin')->assertRedirectTo(site_url('login'));
    }

    public function testRememberMeSurvivesSessionLossAndClearsOnLogout(): void
    {
        $user = $this->facultyAdmin();
        $cookieName = InstanceCookieNames::remember();

        $login = $this->post('/login', $this->withCsrf([
            'email'    => (string) $user->getEmail(),
            'password' => 'MotDePasseLifecycle!2026',
            'remember' => '1',
        ]));
        $login->assertRedirect();

        $cookie = $login->response()->getCookie($cookieName);
        $this->assertNotNull($cookie);
        $this->assertNotSame('', (string) $cookie->getValue());

        $token = (string) $cookie->getValue();

        $_SESSION = [];
        $this->withSession([]);
        service('superglobals')->setCookie($cookieName, $token);
        $_COOKIE[$cookieName] = $token;

        $this->get('/admin')->assertOK();

        $this->get('/logout');

        $_SESSION = [];
        $this->withSession([]);
        service('superglobals')->setCookie($cookieName, $token);
        $_COOKIE[$cookieName] = $token;
        $this->get('/admin')->assertRedirectTo(site_url('login'));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function withCsrf(array $data = []): array
    {
        $security = service('security');
        $token = $security->getTokenName();
        $hash = $security->generateHash();

        $this->withSession(array_replace($_SESSION, [$token => $hash]));

        return array_replace([$token => $hash], $data);
    }
}
