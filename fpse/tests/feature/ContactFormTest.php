<?php

use App\Database\Seeds\TemplateStarterSeeder;
use Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Security\Exceptions\SecurityException;

/**
 * @internal
 */
final class ContactFormTest extends CIUnitTestCase
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

    public function testContactFormIsDisplayedInFrench(): void
    {
        $result = $this->get('/contact');

        $result->assertOK();
        $result->assertSee('Envoyer un message');
        $result->assertSee('Nom complet');
        $result->assertSee('Adresse électronique');
        $result->assertSee('Laisser ce champ vide');
        $result->assertSee('maxlength="5000"');
    }

    public function testValidSubmissionIsStoredAndShowsConfirmation(): void
    {
        $subject = $this->uniqueSubject('Demande valide');

        $result = $this->post('/contact', $this->withCsrf($this->payload([
            'subject' => $subject,
        ])));

        $result->assertRedirectTo(site_url('contact'));
        $this->assertSame('Votre message a bien été envoyé. Nous vous répondrons dans les meilleurs délais.', session('message'));

        $row = $this->messageBySubject($subject);

        $this->assertIsArray($row);
        $this->assertSame('new', $row['status']);
        $this->assertSame('Marie Dupont', $row['name']);
        $this->assertSame('marie.dupont@example.test', $row['email']);
        $this->assertSame('0123 456 789', $row['phone']);
        $this->assertSame($subject, $row['subject']);
        $this->assertSame("Bonjour,\nJe vous contacte pour un test.\nMerci.", $row['message']);
        $this->assertNull($row['read_at']);
        $this->assertNull($row['processed_by']);

        $this->deleteMessageBySubject($subject);
    }

    public function testRequiredFieldsAreRejected(): void
    {
        $result = $this->post('/contact', $this->withCsrf([
            'name'     => '',
            'email'    => '',
            'phone'    => '',
            'subject'  => '',
            'message'  => '',
            'honeypot' => '',
        ]));

        $result->assertRedirectTo(site_url('contact'));
        $this->assertIsArray(session('errors'));
        $this->assertArrayHasKey('name', session('errors'));
        $this->assertArrayHasKey('email', session('errors'));
        $this->assertArrayHasKey('subject', session('errors'));
        $this->assertArrayHasKey('message', session('errors'));
        $this->assertSame('Le nom complet est obligatoire.', session('errors')['name']);
        $this->assertSame("L'adresse email est obligatoire.", session('errors')['email']);
        $this->assertSame("L'objet est obligatoire.", session('errors')['subject']);
        $this->assertSame('Le message est obligatoire.', session('errors')['message']);
    }

    public function testInvalidEmailIsRejected(): void
    {
        $result = $this->post('/contact', $this->withCsrf($this->payload([
            'email' => 'adresse-invalide',
        ])));

        $result->assertRedirectTo(site_url('contact'));
        $this->assertIsArray(session('errors'));
        $this->assertArrayHasKey('email', session('errors'));
        $this->assertSame("L'adresse email doit être valide.", session('errors')['email']);
        $this->assertSame('Marie Dupont', session('_ci_old_input')['post']['name'] ?? null);
        $this->assertSame('adresse-invalide', session('_ci_old_input')['post']['email'] ?? null);
        $this->assertSame('Demande de contact', session('_ci_old_input')['post']['subject'] ?? null);
    }

    public function testMessageTooLongIsRejected(): void
    {
        $subject = $this->uniqueSubject('Message trop long');

        $result = $this->post('/contact', $this->withCsrf($this->payload([
            'subject' => $subject,
            'message' => str_repeat('a', 5001),
        ])));

        $result->assertRedirectTo(site_url('contact'));
        $this->assertIsArray(session('errors'));
        $this->assertArrayHasKey('message', session('errors'));
        $this->assertSame('Le message ne peut pas dépasser 5000 caractères.', session('errors')['message']);
        $this->assertSame('Marie Dupont', session('_ci_old_input')['post']['name'] ?? null);
        $this->assertSame($subject, session('_ci_old_input')['post']['subject'] ?? null);
        $this->assertSame(str_repeat('a', 5001), session('_ci_old_input')['post']['message'] ?? null);
    }

    public function testHoneypotFieldRejectsBotLikeSubmission(): void
    {
        $subject = $this->uniqueSubject('Honeypot');

        $result = $this->post('/contact', $this->withCsrf($this->payload([
            'subject'  => $subject,
            'honeypot' => 'https://spam.example',
        ])));

        $result->assertRedirectTo(site_url('contact'));
        $this->assertSame('Votre message a bien été reçu.', session('message'));
        $this->assertNull($this->messageBySubject($subject));
    }

    public function testRateLimitingBlocksRepeatedSubmission(): void
    {
        $subject = $this->uniqueSubject('Rythme');
        $payload = $this->withCsrf($this->payload([
            'subject' => $subject,
        ]));

        $first = $this->post('/contact', $payload);
        $first->assertRedirectTo(site_url('contact'));
        $this->assertNotNull($this->messageBySubject($subject));

        $second = $this->post('/contact', $this->withCsrf($this->payload([
            'subject' => $subject,
        ])));

        $second->assertRedirectTo(site_url('contact'));
        $this->assertSame('Veuillez patienter quelques instants avant d’envoyer un nouveau message.', session('error'));

        $this->deleteMessageBySubject($subject);
    }

    public function testRateLimitingAllowsDifferentVisitorBehindSharedProxy(): void
    {
        $existingSubject = $this->uniqueSubject('Proxy existant');
        $newSubject = $this->uniqueSubject('Proxy nouveau');
        $visitorEmail = 'visiteur-' . bin2hex(random_bytes(4)) . '@example.test';

        db_connect()->table('contact_messages')->insert([
            'name'       => 'Visiteur précédent',
            'email'      => 'precedent-' . bin2hex(random_bytes(4)) . '@example.test',
            'phone'      => null,
            'subject'    => $existingSubject,
            'message'    => 'Message récent depuis une adresse partagée.',
            'status'     => 'new',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'FeatureTest',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->post('/contact', $this->withCsrf($this->payload([
            'email'   => $visitorEmail,
            'subject' => $newSubject,
        ])));

        $result->assertRedirectTo(site_url('contact'));
        $this->assertSame('Votre message a bien été envoyé. Nous vous répondrons dans les meilleurs délais.', session('message'));
        $this->assertNotNull($this->messageBySubject($newSubject));

        $this->deleteMessageBySubject($existingSubject);
        $this->deleteMessageBySubject($newSubject);
    }

    public function testSubmissionWithoutCsrfIsBlocked(): void
    {
        $subject = $this->uniqueSubject('CSRF');

        try {
            $this->post('/contact', $this->payload([
                'subject' => $subject,
            ]));

            $this->fail('Une exception CSRF était attendue.');
        } catch (SecurityException $exception) {
            $this->assertInstanceOf(SecurityException::class, $exception);
        }

        $this->assertNull($this->messageBySubject($subject));
    }

    public function testValidSubmissionDoesNotRequireEmailSending(): void
    {
        $subject = $this->uniqueSubject('Sans courriel');

        $result = $this->post('/contact', $this->withCsrf($this->payload([
            'subject' => $subject,
        ])));

        $result->assertRedirectTo(site_url('contact'));
        $row = $this->messageBySubject($subject);

        $this->assertIsArray($row);
        $this->assertSame('new', $row['status']);
        $this->assertArrayNotHasKey('notification_sent_at', $row);

        $this->deleteMessageBySubject($subject);
    }

    public function testMessageIsStoredWhenNotificationServiceFails(): void
    {
        $subject = $this->uniqueSubject('Notification échouée');

        $notification = new class () {
            public int $calls = 0;

            public function notify(array $message): bool
            {
                $this->calls++;

                return false;
            }
        };

        Services::injectMock('contactNotificationService', $notification);

        $result = $this->post('/contact', $this->withCsrf($this->payload([
            'subject' => $subject,
        ])));

        $result->assertRedirectTo(site_url('contact'));
        $this->assertSame('Votre message a bien été envoyé. Nous vous répondrons dans les meilleurs délais.', session('message'));
        $this->assertSame(1, $notification->calls);
        $this->assertNotNull($this->messageBySubject($subject));

        $this->deleteMessageBySubject($subject);
        Services::reset();
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name'     => 'Marie Dupont',
            'email'    => 'marie.dupont@example.test',
            'phone'    => '0123 456 789',
            'subject'  => 'Demande de contact',
            'message'  => "Bonjour,\nJe vous contacte pour un test.\nMerci.",
            'honeypot' => '',
        ], $overrides);
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

    private function uniqueSubject(string $prefix): string
    {
        return $prefix . ' ' . bin2hex(random_bytes(4));
    }

    private function messageBySubject(string $subject): ?array
    {
        return db_connect()->table('contact_messages')
            ->where('subject', $subject)
            ->get()
            ->getRowArray() ?: null;
    }

    private function deleteMessageBySubject(string $subject): void
    {
        db_connect()->table('contact_messages')->where('subject', $subject)->delete();
    }
}
