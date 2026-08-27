<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Régressions de l'audit : limitation de débit du formulaire de contact
 * par adresse IP (complément à la limite par adresse électronique).
 *
 * @internal
 */
final class ContactIpRateLimitTest extends CIUnitTestCase
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
        $this->withSession([]);
    }

    protected function tearDown(): void
    {
        service('superglobals')->unsetServer('REMOTE_ADDR');
        parent::tearDown();
    }

    public function testBlocksSubmissionsWhenIpAddressFloodsTheForm(): void
    {
        service('superglobals')->setServer('REMOTE_ADDR', '203.0.113.10');
        $this->seedRecentMessages('203.0.113.10', 20);

        $before = $this->countMessages();

        $result = $this->post('/contact', $this->payload());

        $result->assertRedirectTo(site_url('contact'));
        $result->assertSessionHas('error');
        $this->assertSame($before, $this->countMessages());
    }

    public function testOtherVisitorsAreNotAffectedByAnotherIpFlood(): void
    {
        service('superglobals')->setServer('REMOTE_ADDR', '203.0.113.99');
        $this->seedRecentMessages('203.0.113.11', 20);

        $result = $this->post('/contact', $this->payload());

        $result->assertRedirectTo(site_url('contact'));
        $result->assertSessionHas('message');
        $this->assertSame(
            1,
            (int) $this->db->table('contact_messages')->where('ip_address', '203.0.113.99')->countAllResults(),
        );
    }

    private function seedRecentMessages(string $ip, int $count): void
    {
        $now = date('Y-m-d H:i:s');

        for ($i = 0; $i < $count; $i++) {
            $this->db->table('contact_messages')->insert([
                'name'       => 'Expéditeur ' . $i,
                'email'      => 'flood-' . $ip . '-' . $i . '@example.test',
                'phone'      => null,
                'subject'    => 'Flood ' . $ip . ' #' . $i,
                'message'    => 'Message de charge pour la limite par IP.',
                'status'     => 'new',
                'ip_address' => $ip,
                'user_agent' => 'FeatureTest',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function countMessages(): int
    {
        return (int) $this->db->table('contact_messages')->countAllResults();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $security = service('security');
        $token    = $security->getTokenName();
        $hash     = $security->generateHash();

        $this->withSession(array_replace($_SESSION, [$token => $hash]));

        return array_merge([$token => $hash], [
            'name'    => 'Visiteur de test',
            'email'   => 'visiteur-' . bin2hex(random_bytes(4)) . '@example.test',
            'phone'   => '',
            'subject' => 'Sujet valide ' . bin2hex(random_bytes(4)),
            'message' => 'Un message suffisamment long pour passer la validation.',
        ]);
    }

}
