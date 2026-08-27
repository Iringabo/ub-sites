<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class FoundationRoutesTest extends CIUnitTestCase
{
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

        try {
            auth('session')->getAuthenticator()->logout();
        } catch (Throwable) {
        }

        $_SESSION = [];
        $_COOKIE = [];
        service('superglobals')->setCookieArray([]);
        $this->withSession([]);
    }

    public function testHomePageIsDisplayedInFrench(): void
    {
        $home = $this->db->table('home_content')
            ->where('singleton_key', 1)
            ->get()
            ->getRowArray();

        $this->assertIsArray($home);

        $result = $this->get('/');

        $result->assertOK();
        $result->assertSee('Bienvenue sur le site de');
        $result->assertSee($home['programmes_label']);
        $result->assertSee('Aperçu des formations');
        $result->assertSee('Liste automatique des formations mises en avant.');
        $result->assertSee($home['posts_label']);
        $result->assertSee('Actualités récentes');
        $result->assertSee('Liste automatique des actualités mises en avant.');
        $result->assertSee($home['posts_button_label']);
        $result->assertSee('Lire la suite');
        $result->assertDontSee('Fondation CodeIgniter prête');

        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('id="homeHeroCarousel"', $body);
        $this->assertStringContainsString('data-site-hero-carousel', $body);
        $this->assertStringContainsString('campus-walkway.jpg', $body);
        $this->assertStringContainsString('economics-classroom.jpg', $body);
        $this->assertStringContainsString('Image précédente', $body);
        $this->assertStringContainsString('Image suivante', $body);
        $this->assertStringNotContainsString('<video class="hero-video"', $body);
        $this->assertStringContainsString('<span class="stat-value">0</span><span class="stat-suffix">+</span>', $body);
    }

    public function testBrowserLanguageCanSelectEnglishOnFirstPublicVisit(): void
    {
        $result = $this
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9,fr;q=0.3'])
            ->get('/');

        $result->assertOK();
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('<html lang="en">', $body);
        $this->assertStringContainsString('content="en_US"', $body);
        $result->assertSee('Welcome to FSEG');
        $result->assertDontSee('Bienvenue sur le site de');
        $this->assertStringNotContainsString('please review before publication', strtolower($body));
    }

    public function testManualLanguageSelectionPersistsAndRejectsOpenRedirects(): void
    {
        $switch = $this->get('/language/en?redirect=' . rawurlencode('/actualites'));
        $switch->assertRedirectTo(site_url('actualites'));

        $unsafe = $this->get('/language/en?redirect=' . rawurlencode('https://evil.example/path'));
        $unsafe->assertRedirectTo(site_url('/'));

        // Régression audit : un hôte voisin du baseURL ne doit pas passer le
        // contrôle de préfixe (https://host:port.malveillant.example).
        $base = rtrim(config('App')->baseURL, '/');
        $lookalike = $this->get('/language/en?redirect=' . rawurlencode($base . '.evil.example/path'));
        $lookalike->assertRedirectTo(site_url('/'));

        // Une URL absolue légitime sur le même hôte reste autorisée.
        $absolute = $this->get('/language/en?redirect=' . rawurlencode($base . '/actualites'));
        $absolute->assertRedirectTo(site_url('actualites'));

        $result = $this
            ->withLocaleCookie('en')
            ->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->get('/actualites');

        $result->assertOK();
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('<html lang="en">', $body);
        $result->assertSee('Search for news or an event');
        $result->assertSee('Actualité exemple à modifier');
    }

    public function testEnglishPostDetailFallsBackToFrenchWithoutTranslation(): void
    {
        $post = $this->db->table('posts')
            ->select('slug, title')
            ->where('status', 'published')
            ->orderBy('published_at', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertIsArray($post);

        $result = $this
            ->withLocaleCookie('en')
            ->get('/actualites/' . $post['slug']);

        $result->assertOK();
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('<html lang="en">', $body);
        $result->assertSee($post['title']);
        $this->assertStringNotContainsString('please review before publication', strtolower($body));
    }

    public function testAdministrationRemainsFrenchWithEnglishCookie(): void
    {
        $result = $this
            ->withLocaleCookie('en')
            ->get('/login');

        $result->assertOK();
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('<html lang="fr">', $body);
        $result->assertSee('Connexion à l’administration');
        $result->assertDontSee('Sign in');
    }

    public function testMainPublicPagesAreDisplayedInFrench(): void
    {
        foreach ([
            '/faculte'          => 'Mot du doyen',
            '/formations'       => 'Offre académique',
            '/recherche'        => 'Laboratoires',
            '/corps-enseignant' => 'Personnel à compléter',
            '/actualites'       => 'Rechercher une actualité',
            '/alumni'           => 'Communauté',
            '/contact'          => 'Envoyer un message',
        ] as $uri => $expectedText) {
            $result = $this->get($uri);

            $result->assertOK();
            $result->assertSee($expectedText);
        }
    }

    public function testPublicDetailRoutesUseDatabaseRecords(): void
    {
        $programme = $this->db->table('programmes')->select('slug, title')->orderBy('display_order')->get()->getRowArray();
        $staff     = $this->db->table('staff')->select('slug, name')->orderBy('display_order')->get()->getRowArray();
        $post      = $this->db->table('posts')->select('slug, title')->where('status', 'published')->orderBy('published_at', 'DESC')->get()->getRowArray();

        $this->assertIsArray($programme);
        $this->assertIsArray($staff);
        $this->assertIsArray($post);

        $programmeResult = $this->get('/formations/' . $programme['slug']);
        $staffResult     = $this->get('/corps-enseignant/' . $staff['slug']);
        $postResult      = $this->get('/actualites/' . $post['slug']);

        $programmeResult->assertOK();
        $programmeResult->assertSee($programme['title']);
        $staffResult->assertOK();
        $staffResult->assertSee($staff['name']);
        $postResult->assertOK();
        $postResult->assertSee($post['title']);
    }

    public function testApplicationJavaScriptDoesNotFetchReferenceJson(): void
    {
        $script = file_get_contents(ROOTPATH . 'public/assets/js/main.js') ?: '';

        $this->assertStringNotContainsString("fetch('data/", $script);
        $this->assertStringNotContainsString('fetch(`data/', $script);
    }

    public function testHealthAndHeadRequestsAreHandled(): void
    {
        $health = $this->get('/healthz');
        $health->assertStatus(204);
        $this->assertStringContainsString('no-store', $health->response()->getHeaderLine('Cache-Control'));

        $homeHead = $this->call('head', '/');
        $homeHead->assertOK();

        $adminHead = $this->call('head', '/admin');
        $adminHead->assertRedirectTo(site_url('login'));
    }

    public function testMissingContentReturnsFrenchNotFoundPage(): void
    {
        $result = $this->get('/formations/contenu-inexistant');

        $result->assertStatus(404);
        $result->assertSee('Page introuvable');
        $result->assertSee('Formation introuvable.');
    }

    public function testLoginPageIsDisplayedInFrench(): void
    {
        $result = $this->get('/login');

        $result->assertOK();
        $result->assertSee('Connexion à l’administration');
        $result->assertSee('Mot de passe');
    }

    public function testPublicLayoutEscapesConfigurableFaviconPath(): void
    {
        $this->db->table('settings')->where('key', 'assets.logo')->update([
            'value'      => 'https://example.test/logo.png" onerror="alert(1)',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        service('settingsService')->reset();

        $result = $this->get('/');

        $result->assertOK();
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('logo.png&quot;&#x20;onerror&#x3D;&quot;alert&#x28;1&#x29;', $body);
        $this->assertStringNotContainsString('logo.png" onerror="alert(1)', $body);
    }

    public function testAdminRedirectsGuestToLogin(): void
    {
        $result = $this->get('/admin');

        $result->assertRedirectTo(site_url('login'));
    }

    private function withLocaleCookie(string $locale): self
    {
        service('superglobals')->setCookie('site_locale', $locale);

        return $this;
    }
}
