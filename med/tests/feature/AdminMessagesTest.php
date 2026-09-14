<?php

use App\Database\Seeds\TemplateStarterSeeder;
use App\Models\ContactMessageModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AdminMessagesTest extends CIUnitTestCase
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
        auth('session')->getAuthenticator()->logout();
        $this->deleteTestMessages();
    }

    protected function tearDown(): void
    {
        auth('session')->getAuthenticator()->logout();
        $this->deleteTestMessages();
        parent::tearDown();
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->get('/admin/messages')->assertRedirectTo(site_url('login'));
    }

    public function testUserWithoutPermissionCannotAccessMessagesModule(): void
    {
        $this->actingAs($this->directPermissionUser(
            'messages-access-only@example.test',
            'messagesaccessonly',
            ['admin.access'],
        ));

        $this->get('/admin/messages')->assertRedirectTo(rtrim(base_url(), '/'));
    }

    public function testAuthorizedAdminCanListMessagesAndPaginate(): void
    {
        $this->actingAs($this->messageManagerUser());

        for ($index = 1; $index <= 16; $index++) {
            $this->createMessage([
                'subject' => sprintf('Message %02d', $index),
                'message' => 'Contenu ' . $index,
            ]);
        }

        $pageOne = $this->get('/admin/messages');
        $pageOne->assertOK();
        $pageOne->assertSee('Message 16');
        $pageOne->assertDontSee('Message 01');

        \Config\Services::resetSingle('pager');

        $pageTwo = $this->get('/admin/messages?page_admin_messages=2');
        $pageTwo->assertOK();
        $pageTwo->assertSee('Message 01');
        $pageTwo->assertDontSee('Message 16');
    }

    public function testSearchAndStatusFiltersWork(): void
    {
        $this->actingAs($this->messageManagerUser());

        $newId = $this->createMessage([
            'subject' => 'Recherche visible',
            'message' => 'Corps visible',
            'status'  => 'new',
        ]);

        $handledId = $this->createMessage([
            'subject' => 'Recherche traitée',
            'message' => 'Corps traité',
            'status'  => 'handled',
        ]);

        $this->createMessage([
            'subject' => 'Recherche archivée',
            'message' => 'Corps archivé',
            'status'  => 'archived',
        ]);

        $search = $this->get('/admin/messages?q=visible');
        $search->assertOK();
        $search->assertSee('Recherche visible');
        $search->assertDontSee('Recherche traitée');

        $handled = $this->get('/admin/messages?status=handled');
        $handled->assertOK();
        $handled->assertSee('Recherche traitée');
        $handled->assertDontSee('Recherche visible');

        $today = Time::now()->toDateString();
        $range = $this->get('/admin/messages?from=' . $today . '&to=' . $today);
        $range->assertOK();
        $range->assertSee('Recherche visible');
        $range->assertSee('Recherche traitée');

        $this->assertNotEmpty($newId);
        $this->assertNotEmpty($handledId);
    }

    public function testPeriodFilterExcludesOlderMessages(): void
    {
        $this->actingAs($this->messageManagerUser());

        $yesterday = Time::now()->subDays(1);
        $today = Time::now();

        $this->insertMessage([
            'subject'     => 'Message ancien',
            'message'     => 'Ancien',
            'created_at'   => $yesterday->toDateTimeString(),
            'updated_at'   => $yesterday->toDateTimeString(),
        ]);

        $this->insertMessage([
            'subject'     => 'Message récent',
            'message'     => 'Récent',
            'created_at'   => $today->toDateTimeString(),
            'updated_at'   => $today->toDateTimeString(),
        ]);

        $result = $this->get('/admin/messages?from=' . $today->toDateString());
        $result->assertOK();
        $result->assertSee('Message récent');
        $result->assertDontSee('Message ancien');
    }

    public function testConsultationAndStatusChangesTrackAdministrator(): void
    {
        $admin = $this->messageManagerUser('messages-trace@example.test', 'messagestrace');
        $this->actingAs($admin);

        $id = $this->createMessage([
            'subject' => 'Trace administrative',
            'message' => 'Contenu de suivi',
            'status'  => 'new',
        ]);

        $detail = $this->get('/admin/messages/' . $id);
        $detail->assertOK();
        $detail->assertSee('Trace administrative');
        $detail->assertSee('Contenu de suivi');

        $read = $this->post('/admin/messages/' . $id . '/read', $this->withCsrf());
        $read->assertRedirect();

        $row = $this->messageRow($id);
        $this->assertSame('read', $row['status']);
        $this->assertNotEmpty($row['read_at']);

        $handled = $this->post('/admin/messages/' . $id . '/handled', $this->withCsrf());
        $handled->assertRedirect();

        $row = $this->messageRow($id);
        $this->assertSame('handled', $row['status']);
        $this->assertSame((string) $admin->id, (string) $row['processed_by']);
        $this->assertNotEmpty($row['read_at']);

        $archived = $this->post('/admin/messages/' . $id . '/archive', $this->withCsrf());
        $archived->assertRedirect();

        $row = $this->messageRow($id);
        $this->assertSame('archived', $row['status']);
        $this->assertSame((string) $admin->id, (string) $row['processed_by']);
    }

    public function testSubmissionWithoutCsrfIsBlocked(): void
    {
        $this->actingAs($this->messageManagerUser());
        $id = $this->createMessage([
            'subject' => 'CSRF message',
            'message' => 'Corps',
            'status'  => 'new',
        ]);

        try {
            $this->post('/admin/messages/' . $id . '/archive');
            $this->fail('Une exception CSRF était attendue.');
        } catch (SecurityException $exception) {
            $this->assertInstanceOf(SecurityException::class, $exception);
        }
    }

    public function testMessageContentIsEscapedInListAndDetail(): void
    {
        $this->actingAs($this->messageManagerUser());

        $id = $this->createMessage([
            'subject' => 'Alerte <script>alert(1)</script>',
            'message' => 'Corps <img src=x onerror=alert(1)>',
            'status'  => 'new',
        ]);

        $index = $this->get('/admin/messages');
        $index->assertOK();
        $body = (string) $index->response()->getBody();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);

        $show = $this->get('/admin/messages/' . $id);
        $show->assertOK();
        $detailBody = (string) $show->response()->getBody();
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $detailBody);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $detailBody);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createMessage(array $overrides = []): int
    {
        return $this->insertMessage($overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function insertMessage(array $overrides = []): int
    {
        $data = array_replace([
            'name'         => 'Marie Dupont',
            'email'        => 'marie.dupont@example.test',
            'phone'        => '0123 456 789',
            'subject'      => 'Demande de contact',
            'message'      => 'Bonjour, voici un message de test.',
            'status'       => 'new',
            'ip_address'   => '127.0.0.1',
            'user_agent'   => 'PHPUnit',
            'read_at'      => null,
            'processed_by' => null,
            'created_at'   => Time::now()->toDateTimeString(),
            'updated_at'   => Time::now()->toDateTimeString(),
        ], $overrides);

        $db = db_connect('tests');
        $db->table('contact_messages')->insert($data);

        return (int) $db->insertID();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function messageRow(int $id): ?array
    {
        return db_connect('tests')->table('contact_messages')->where('id', $id)->get()->getRowArray() ?: null;
    }

    private function deleteTestMessages(): void
    {
        db_connect('tests')->table('contact_messages')->truncate();
    }

    private function messageManagerUser(string $email = 'messages-manager@example.test', string $username = 'messagesmanager'): User
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);

        $existing = $users->findByCredentials(['email' => $email]);
        if ($existing instanceof User) {
            return $existing;
        }

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => 'MotDePassePhase6!2026',
            'active'   => 1,
        ]);

        $users->save($user);
        /** @var User $created */
        $created = $users->findById($users->getInsertID());
        $created->removeGroup('editor', 'admin', 'superadmin');
        $created->addPermission('admin.access', 'messages.manage');
        $created->activate();
        service('siteResolver')->syncUserSites((int) $created->id, [1], 'site_admin');

        return $created;
    }

    private function directPermissionUser(string $email, string $username, array $permissions): User
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);

        $existing = $users->findByCredentials(['email' => $email]);
        if ($existing instanceof User) {
            return $existing;
        }

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => 'MotDePassePhase6!2026',
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
        $token = $security->getTokenName();
        $hash = $security->generateHash();

        $this->withSession(array_replace($_SESSION, [$token => $hash]));

        return array_replace([$token => $hash], $data);
    }
}
