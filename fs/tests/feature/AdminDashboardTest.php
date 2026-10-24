<?php

use App\Database\Seeds\TemplateStarterSeeder;
use App\Models\ContactMessageModel;
use App\Models\PostModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AdminDashboardTest extends CIUnitTestCase
{
    use AuthenticationTesting;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    public function testDashboardShowsRealCounters(): void
    {
        $this->actingAs($this->superAdminUser());

        $newsCounts = $this->db->table('posts')
            ->select('status, COUNT(*) AS total')
            ->where('type', 'news')
            ->groupBy('status')
            ->get()
            ->getResultArray();

        $counts = [];
        foreach ($newsCounts as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        $upcomingEvents = model(PostModel::class)->visible()
            ->where('type', 'event')
            ->where('event_starts_at >=', date('Y-m-d H:i:s'))
            ->countAllResults();

        $result = $this->get('/admin');
        $result->assertOK();

        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Actualités par statut', $body);
        $this->assertStringContainsString('Messages de contact', $body);
        $this->assertStringContainsString('Formations publiées', $body);
        $this->assertStringContainsString('Personnel publié', $body);
        $this->assertStringContainsString('Événements à venir', $body);

        foreach ([
            'Brouillon : ' . ($counts['draft'] ?? 0),
            'Programmé : ' . ($counts['scheduled'] ?? 0),
            'Publié : ' . ($counts['published'] ?? 0),
            'Archivé : ' . ($counts['archived'] ?? 0),
            (string) $upcomingEvents,
            (string) $this->db->table('programmes')->where('is_published', 1)->countAllResults(),
            (string) $this->db->table('staff')->where('is_published', 1)->countAllResults(),
            (string) $this->db->table('contact_messages')->where('status', 'new')->countAllResults(),
            'Nouveau : ' . $this->messageStatusCount('new'),
            'Lu : ' . $this->messageStatusCount('read'),
            'Traité : ' . $this->messageStatusCount('handled'),
            'Archivé : ' . $this->messageStatusCount('archived'),
        ] as $needle) {
            $this->assertStringContainsString($needle, $body);
        }
    }

    private function superAdminUser(string $email = 'dashboard-super@example.test', string $username = 'dashboardsuper'): User
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
            'password' => 'MotDePassePhase5!2026',
            'active'   => 1,
        ]);

        $users->save($user);
        /** @var User $created */
        $created = $users->findById($users->getInsertID());
        $created->addGroup('superadmin');
        $created->activate();

        return $created;
    }

    private function messageStatusCount(string $status): int
    {
        /** @var ContactMessageModel $messages */
        $messages = model(ContactMessageModel::class);

        return (int) $messages->where('status', $status)->countAllResults();
    }
}
