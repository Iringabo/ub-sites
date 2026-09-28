<?php

use App\Database\Seeds\TemplateStarterSeeder;
use App\Models\PostModel;
use App\Support\InstanceCookieNames;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class PostManagementTest extends CIUnitTestCase
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

        service('siteResolver')->reset();
        service('settingsService')->reset();
        service('contentTranslationService')->reset();

        $_COOKIE = [];
        $_SESSION = [];
        $this->withSession([]);
    }

    protected function tearDown(): void
    {
        try {
            auth('session')->getAuthenticator()->logout();
        } catch (Throwable) {
        }

        $_SESSION = [];
        $this->withSession([]);

        parent::tearDown();
    }

    public function testScheduledFuturePostIsPrivateUntilPublicationDate(): void
    {
        $model = model(PostModel::class);
        $model->skipValidation(true);
        $model->insert($this->postData([
            'title'        => 'Annonce programmée privée',
            'slug'         => 'annonce-programmee-privee',
            'status'       => 'scheduled',
            'published_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
        ]));
        $model->skipValidation(false);

        $list = $this->get('/actualites');
        $list->assertOK();
        $list->assertDontSee('Annonce programmée privée');

        $detail = $this->get('/actualites/annonce-programmee-privee');
        $detail->assertStatus(404);
        $detail->assertSee('Page introuvable');
    }

    public function testScheduledPastPostIsVisibleWithoutCron(): void
    {
        $model = model(PostModel::class);
        $model->skipValidation(true);
        $model->insert($this->postData([
            'title'        => 'Annonce programmée visible',
            'slug'         => 'annonce-programmee-visible',
            'status'       => 'scheduled',
            'published_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]));
        $model->skipValidation(false);

        $detail = $this->get('/actualites/annonce-programmee-visible');
        $detail->assertOK();
        $detail->assertSee('Annonce programmée visible');
    }

    public function testPublishedPostWithoutPublicationDateIsPrivate(): void
    {
        $model = model(PostModel::class);
        $model->skipValidation(true);
        $model->insert($this->postData([
            'title'        => 'Actualité publiée sans date',
            'slug'         => 'actualite-publiee-sans-date',
            'status'       => 'published',
            'published_at' => null,
        ]));
        $model->skipValidation(false);

        $list = $this->get('/actualites');
        $list->assertOK();
        $list->assertDontSee('Actualité publiée sans date');

        $detail = $this->get('/actualites/actualite-publiee-sans-date');
        $detail->assertStatus(404);
    }

    public function testPublicListSupportsSearchAndTypeFilters(): void
    {
        $model = model(PostModel::class);
        $model->skipValidation(true);
        $model->insert($this->postData([
            'type'    => 'news',
            'title'   => 'Université de Liège — partenariat académique',
            'slug'    => 'universite-de-liege-partenariat-' . bin2hex(random_bytes(3)),
            'excerpt' => 'Une collaboration universitaire avec Liège.',
            'body'    => 'Contenu de recherche publique pour Liège.',
        ]));
        $model->insert($this->postData([
            'type'            => 'event',
            'title'           => 'Journée portes ouvertes FSEG 2026',
            'slug'            => 'journee-portes-ouvertes-filtre-' . bin2hex(random_bytes(3)),
            'excerpt'         => 'Journée de rencontre avec les futurs étudiants.',
            'body'            => 'Programme de la journée portes ouvertes.',
            'event_starts_at' => date('Y-m-d H:i:s', strtotime('+10 days')),
            'event_ends_at'   => date('Y-m-d H:i:s', strtotime('+10 days +3 hours')),
            'event_location'  => 'Campus Mutanga',
        ]));
        $model->skipValidation(false);

        $search = $this->get('/actualites?q=Li%C3%A8ge');
        $search->assertOK();
        $search->assertSee('Université de Liège');
        $search->assertDontSee('Journée portes ouvertes FSEG 2026');

        $eventFilter = $this->get('/actualites?type=evenement&q=Journ%C3%A9e');
        $eventFilter->assertOK();
        $eventFilter->assertSee('Journée portes ouvertes FSEG 2026');
        $eventFilter->assertSee('Événement');

        $newsFilter = $this->get('/actualites?type=actualite&q=Conf%C3%A9rence%20internationale');
        $newsFilter->assertOK();
        $newsFilter->assertDontSee('Conférence internationale — Développement économique en Afrique');
        $newsFilter->assertSee('Aucun résultat ne correspond à votre recherche.');
    }

    public function testAllTabMixesNewsAndEventsAndSearchesBothTypes(): void
    {
        $model = model(PostModel::class);
        $model->skipValidation(true);
        $model->insert($this->postData([
            'type'    => 'news',
            'title'   => 'Actualité mixte Rumonge',
            'slug'    => 'actualite-mixte-rumonge-' . bin2hex(random_bytes(3)),
            'excerpt' => 'Résumé de l’actualité mixte à Ngozi.',
        ]));
        $model->insert($this->postData([
            'type'            => 'event',
            'title'           => 'Colloque Gitega uniquement événement',
            'slug'            => 'colloque-gitega-' . bin2hex(random_bytes(3)),
            'excerpt'         => 'Un colloque organisé à Gitega avec des partenaires de Ngozi.',
            'published_at'    => date('Y-m-d H:i:s', strtotime('-3 days')),
            'event_starts_at' => date('Y-m-d H:i:s', strtotime('+5 days')),
            'event_ends_at'   => date('Y-m-d H:i:s', strtotime('+5 days +2 hours')),
            'event_location'  => 'Gitega',
        ]));
        $model->skipValidation(false);

        $all = $this->get('/actualites?q=Ngozi');
        $all->assertOK();
        $all->assertSee('Actualité mixte Rumonge');
        $all->assertSee('Colloque Gitega uniquement événement');

        $eventOnly = $this->get('/actualites?q=Gitega');
        $eventOnly->assertOK();
        $eventOnly->assertSee('Colloque Gitega uniquement événement');
        $eventOnly->assertDontSee('Actualité mixte Rumonge');
        $this->assertMatchesRegularExpression('/id="newsNoResults" class="[^"]*d-none/', (string) $eventOnly->response()->getBody());

        $nothing = $this->get('/actualites?q=zzz-aucun-contenu-zzz');
        $nothing->assertOK();
        $nothing->assertSee('Aucun résultat ne correspond à votre recherche.');
        $this->assertDoesNotMatchRegularExpression('/id="newsNoResults" class="[^"]*d-none/', (string) $nothing->response()->getBody());

        service('superglobals')->setCookie(InstanceCookieNames::locale(), 'en');
        $nothingEn = $this->get('/actualites?q=zzz-aucun-contenu-zzz');
        $nothingEn->assertOK();
        $nothingEn->assertSee('No results found.');
        service('superglobals')->setCookieArray([]);
    }

    public function testHomepageFeaturesNewsAndUpcomingEventsOnly(): void
    {
        $model = model(PostModel::class);
        $model->skipValidation(true);
        $model->insert($this->postData([
            'type'            => 'event',
            'title'           => 'Événement passé mis en avant',
            'slug'            => 'evenement-passe-mis-en-avant',
            'status'          => 'published',
            'published_at'    => date('Y-m-d H:i:s', strtotime('-2 days')),
            'featured'        => 1,
            'home_order'      => 0,
            'event_starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'event_ends_at'   => date('Y-m-d H:i:s', strtotime('-1 day +2 hours')),
            'event_location'  => 'Campus Kiriri',
        ]));
        $model->insert($this->postData([
            'type'            => 'event',
            'title'           => 'Événement futur mis en avant',
            'slug'            => 'evenement-futur-mis-en-avant',
            'status'          => 'published',
            'published_at'    => date('Y-m-d H:i:s', strtotime('-2 days')),
            'featured'        => 1,
            'home_order'      => 0,
            'event_starts_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'event_ends_at'   => date('Y-m-d H:i:s', strtotime('+2 days +2 hours')),
            'event_location'  => 'Campus Kiriri',
        ]));
        $model->skipValidation(false);

        $home = $this->get('/');

        $home->assertOK();
        $home->assertDontSee('Événement passé mis en avant');
        $home->assertSee('Événement futur mis en avant');
    }

    public function testAdminCanCreatePreviewPublishScheduleAndArchivePost(): void
    {
        $this->actingAs($this->adminUser());

        $create = $this->post('/admin/posts', $this->withCsrf([
            'type'            => 'event',
            'title'           => 'Forum économique de test',
            'slug'            => '',
            'excerpt'         => 'Résumé du forum économique de test.',
            'body'            => 'Contenu détaillé du forum économique de test.',
            'status'          => 'draft',
            'published_at'    => '',
            'featured'        => '1',
            'home_order'      => '5',
            'event_starts_at' => date('Y-m-d\TH:i', strtotime('+3 days')),
            'event_ends_at'   => date('Y-m-d\TH:i', strtotime('+3 days +2 hours')),
            'event_location'  => 'Campus Kiriri',
            'registration_url' => 'https://example.test/inscription',
        ]));
        $create->assertRedirect();

        $post = $this->db->table('posts')
            ->where('title', 'Forum économique de test')
            ->get()
            ->getRowArray();

        $this->assertIsArray($post);
        $this->assertSame('draft', $post['status']);
        $this->assertSame('forum-economique-de-test', $post['slug']);

        $preview = $this->get('/admin/posts/' . $post['id'] . '/preview');
        $preview->assertOK();
        $preview->assertSee('Forum économique de test');

        $this->post('/admin/posts/' . $post['id'] . '/publish', $this->withCsrf())->assertRedirect();
        $post = $this->db->table('posts')->where('id', $post['id'])->get()->getRowArray();
        $this->assertSame('published', $post['status']);
        $this->assertNotEmpty($post['published_at']);

        $scheduleDate = date('Y-m-d\TH:i', strtotime('+5 days'));
        $this->post('/admin/posts/' . $post['id'], $this->withCsrf([
            'type'             => 'event',
            'title'            => 'Forum économique de test',
            'slug'             => $post['slug'],
            'excerpt'          => 'Résumé mis à jour.',
            'body'             => 'Contenu mis à jour.',
            'status'           => 'scheduled',
            'published_at'     => $scheduleDate,
            'featured'         => '1',
            'home_order'       => '6',
            'event_starts_at'  => date('Y-m-d\TH:i', strtotime('+6 days')),
            'event_ends_at'    => date('Y-m-d\TH:i', strtotime('+6 days +2 hours')),
            'event_location'   => 'Campus Kiriri',
            'registration_url' => 'https://example.test/inscription',
        ]))->assertRedirect();

        $post = $this->db->table('posts')->where('id', $post['id'])->get()->getRowArray();
        $this->assertSame('scheduled', $post['status']);
        $this->assertStringStartsWith(date('Y-m-d', strtotime('+5 days')), $post['published_at']);

        $this->post('/admin/posts/' . $post['id'] . '/archive', $this->withCsrf())->assertRedirect();
        $post = $this->db->table('posts')->where('id', $post['id'])->get()->getRowArray();
        $this->assertSame('archived', $post['status']);
    }

    public function testAdminCreateGeneratesUniqueSlugs(): void
    {
        $this->actingAs($this->adminUser('slug-admin@example.test', 'slugadmin'));

        foreach ([1, 2] as $index) {
            $this->post('/admin/posts', $this->withCsrf([
                'type'         => 'news',
                'title'        => 'Titre dupliqué phase quatre',
                'slug'         => '',
                'excerpt'      => 'Résumé de test.',
                'body'         => 'Corps de test.',
                'status'       => 'draft',
                'published_at' => '',
                'featured'     => '0',
                'home_order'   => '',
            ]))->assertRedirect();
        }

        $slugs = $this->db->table('posts')
            ->select('slug')
            ->like('slug', 'titre-duplique-phase-quatre', 'after')
            ->orderBy('slug', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertSame(
            ['titre-duplique-phase-quatre', 'titre-duplique-phase-quatre-2'],
            array_column($slugs, 'slug'),
        );
    }

    public function testNewsManagerCannotCreateEvent(): void
    {
        $this->actingAs($this->directPermissionUser(
            'news-only-admin@example.test',
            'newsonlyadmin',
            ['admin.access', 'news.manage'],
        ));

        $create = $this->post('/admin/posts', $this->withCsrf([
            'type'             => 'event',
            'title'            => 'Événement non autorisé',
            'slug'             => '',
            'excerpt'          => 'Résumé de test.',
            'body'             => 'Contenu de test.',
            'status'           => 'draft',
            'published_at'     => '',
            'featured'         => '0',
            'home_order'       => '',
            'event_starts_at'  => date('Y-m-d\TH:i', strtotime('+4 days')),
            'event_ends_at'    => date('Y-m-d\TH:i', strtotime('+4 days +2 hours')),
            'event_location'   => 'Campus Kiriri',
            'registration_url' => '',
        ]));

        $create->assertRedirectTo(site_url('admin/posts'));

        $this->assertSame(
            0,
            $this->db->table('posts')->where('title', 'Événement non autorisé')->countAllResults(),
        );
    }

    public function testAdminRejectsInvalidTypeStatusAndHomeOrder(): void
    {
        $this->actingAs($this->adminUser('validation-admin@example.test', 'validationadmin'));

        $create = $this->post('/admin/posts', $this->withCsrf([
            'type'         => 'memo',
            'title'        => 'Contenu invalide phase quatre',
            'slug'         => '',
            'excerpt'      => 'Résumé de test.',
            'body'         => 'Corps de test.',
            'status'       => 'online',
            'published_at' => '',
            'featured'     => '0',
            'home_order'   => 'abc',
        ]));

        $create->assertRedirect();
        $create->assertSessionHas('errors');

        $this->assertSame(
            0,
            $this->db->table('posts')->where('title', 'Contenu invalide phase quatre')->countAllResults(),
        );
    }

    public function testAdminRejectsInconsistentPublicationDates(): void
    {
        $this->actingAs($this->adminUser('schedule-admin@example.test', 'scheduleadmin'));

        $scheduledPast = $this->post('/admin/posts', $this->withCsrf([
            'type'         => 'news',
            'title'        => 'Programmation passée refusée',
            'slug'         => '',
            'excerpt'      => 'Résumé de test.',
            'body'         => 'Corps de test.',
            'status'       => 'scheduled',
            'published_at' => date('Y-m-d\TH:i', strtotime('-1 hour')),
            'featured'     => '0',
            'home_order'   => '',
        ]));

        $scheduledPast->assertRedirect();
        $scheduledPast->assertSessionHas('errors');
        $this->assertSame(0, $this->db->table('posts')->where('title', 'Programmation passée refusée')->countAllResults());

        $publishedFuture = $this->post('/admin/posts', $this->withCsrf([
            'type'         => 'news',
            'title'        => 'Publication future refusée',
            'slug'         => '',
            'excerpt'      => 'Résumé de test.',
            'body'         => 'Corps de test.',
            'status'       => 'published',
            'published_at' => date('Y-m-d\TH:i', strtotime('+2 days')),
            'featured'     => '0',
            'home_order'   => '',
        ]));

        $publishedFuture->assertRedirect();
        $publishedFuture->assertSessionHas('errors');
        $this->assertSame(0, $this->db->table('posts')->where('title', 'Publication future refusée')->countAllResults());

        $sameEventDates = $this->post('/admin/posts', $this->withCsrf([
            'type'             => 'event',
            'title'            => 'Événement sans durée refusé',
            'slug'             => '',
            'excerpt'          => 'Résumé de test.',
            'body'             => 'Corps de test.',
            'status'           => 'draft',
            'published_at'     => '',
            'featured'         => '0',
            'home_order'       => '',
            'event_starts_at'  => date('Y-m-d\TH:i', strtotime('+4 days')),
            'event_ends_at'    => date('Y-m-d\TH:i', strtotime('+4 days')),
            'event_location'   => 'Campus Kiriri',
            'registration_url' => '',
        ]));

        $sameEventDates->assertRedirect();
        $sameEventDates->assertSessionHas('errors');
        $this->assertSame(0, $this->db->table('posts')->where('title', 'Événement sans durée refusé')->countAllResults());
    }

    public function testAdminRejectsUnsafeEventRegistrationUrl(): void
    {
        $this->actingAs($this->adminUser('unsafe-url-admin@example.test', 'unsafeurladmin'));

        $create = $this->post('/admin/posts', $this->withCsrf([
            'type'             => 'event',
            'title'            => 'Événement avec lien dangereux',
            'slug'             => '',
            'excerpt'          => 'Résumé de test.',
            'body'             => 'Corps de test.',
            'status'           => 'draft',
            'published_at'     => '',
            'featured'         => '0',
            'home_order'       => '',
            'event_starts_at'  => date('Y-m-d\TH:i', strtotime('+4 days')),
            'event_ends_at'    => date('Y-m-d\TH:i', strtotime('+4 days +2 hours')),
            'event_location'   => 'Campus Kiriri',
            'registration_url' => 'javascript://alert(1)',
        ]));

        $create->assertRedirect();
        $create->assertSessionHas('errors');

        $this->assertSame(
            0,
            $this->db->table('posts')->where('title', 'Événement avec lien dangereux')->countAllResults(),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function postData(array $overrides = []): array
    {
        return array_replace([
            'type'            => 'news',
            'title'           => 'Actualité de test',
            'slug'            => 'actualite-de-test-' . bin2hex(random_bytes(3)),
            'excerpt'         => 'Résumé de test',
            'body'            => 'Contenu de test',
            'cover_image'     => null,
            'status'          => 'published',
            'published_at'    => date('Y-m-d H:i:s', strtotime('-1 day')),
            'featured'        => 0,
            'home_order'      => null,
            'event_starts_at' => null,
            'event_ends_at'   => null,
            'event_location'  => null,
            'registration_url' => null,
            'seo_title'       => null,
            'seo_description' => null,
            'created_by'      => null,
            'updated_by'      => null,
        ], $overrides);
    }

    private function adminUser(string $email = 'phase4-admin@example.test', string $username = 'phase4admin'): User
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);
        $existing = $users->where('username', $username)->first();

        if ($existing instanceof User) {
            return $existing;
        }

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => 'MotDePassePhase4!2026',
            'active'   => 1,
        ]);

        $users->save($user);

        /** @var User $created */
        $created = $users->findById($users->getInsertID());
        $created->addGroup('admin');
        $created->activate();
        service('siteResolver')->syncUserSites((int) $created->id, [1], 'site_admin');

        return $created;
    }

    /**
     * @param list<string> $permissions
     */
    private function directPermissionUser(string $email, string $username, array $permissions): User
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);
        $existing = $users->where('username', $username)->first();

        if ($existing instanceof User) {
            return $existing;
        }

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => 'MotDePassePhase4!2026',
            'active'   => 1,
        ]);

        $users->save($user);

        /** @var User $created */
        $created = $users->findById($users->getInsertID());
        $created->removeGroup('editor', 'admin', 'superadmin');
        $created->addPermission(...$permissions);
        $created->activate();
        service('siteResolver')->syncUserSites((int) $created->id, [1], 'site_admin');

        return $created;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function withCsrf(array $data = []): array
    {
        $security = service('security');
        $token    = $security->getTokenName();
        $hash     = $security->generateHash();

        $this->withSession(array_replace($_SESSION, [$token => $hash]));

        return array_replace([$token => $hash], $data);
    }
}
