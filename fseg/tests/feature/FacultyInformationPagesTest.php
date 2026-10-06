<?php

use App\Database\Seeds\TemplateStarterSeeder;
use App\Models\SiteModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Admissions, departments per cycle, FAQ, partners marquee, event ICS, posts
 * (author, attachment, plain paragraphs), homepage rails, legal page, sitemap,
 * robots, hreflang, JSON-LD, wider search, staff filters and analytics.
 *
 * @internal
 */
final class FacultyInformationPagesTest extends CIUnitTestCase
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
        service('siteResolver')->reset();
        service('contentTranslationService')->reset();
        service('settingsService')->reset();
        Services::language()->setLocale('fr');
    }

    public function testNewPublicPagesRespondOnFreshSite(): void
    {
        foreach (['admissions', 'departements', 'faq', 'mentions-legales'] as $path) {
            $result = $this->get('/' . $path);
            $result->assertOK();
        }

        $this->get('/departements/inexistant')->assertStatus(404);

        foreach (['partenaires', 'agenda', 'agenda.ics'] as $removed) {
            $this->get('/' . $removed)->assertStatus(404);
        }
    }

    public function testDepartmentDetailListsItsProgrammesAndStaffOnly(): void
    {
        $departmentId = $this->insertDepartment(1, 'Département de Test', 'dept-test');
        $programmeId = $this->insertProgramme(1, 'Licence rattachée au département', 'licence-rattachee', ['department_id' => $departmentId]);
        $this->insertProgramme(1, 'Licence hors département', 'licence-hors-departement');
        $staffId = $this->insertStaff(1, 'Chef Département Test', 'chef-dept-test', ['department_id' => $departmentId]);
        $this->db->table('departments')->where('id', $departmentId)->update(['head_staff_id' => $staffId]);

        $result = $this->get('/departements/dept-test');
        $result->assertOK();
        $result->assertSee('Département de Test');
        $result->assertSee('Licence rattachée au département');
        $result->assertDontSee('Licence hors département');
        $result->assertSee('Chef Département Test');

        $list = $this->get('/departements');
        $list->assertSee('Département de Test');

        $filtered = $this->get('/formations?departement=dept-test');
        $filtered->assertSee('Licence rattachée au département');
        $filtered->assertDontSee('Licence hors département');

        $this->assertGreaterThan(0, $programmeId);
    }

    public function testDepartmentOfAnotherSiteIsNotReachable(): void
    {
        $siteId = $this->createSecondSite();
        $this->insertDepartment($siteId, 'Département Droit privé', 'droit-prive');

        $this->get('/departements/droit-prive')->assertStatus(404);
        $this->get('/departements')->assertDontSee('Département Droit privé');
        $this->assertStringNotContainsString('droit-prive', (string) $this->get('/sitemap.xml')->response()->getBody());
    }

    public function testProgrammeDetailShowsFactsCurriculumCoordinatorAndCourseJsonLd(): void
    {
        $coordinatorId = $this->insertStaff(1, 'Coordinatrice Programme', 'coordinatrice-programme');
        $this->insertProgramme(1, 'Master riche en détails', 'master-riche', [
            'level'                => 'master',
            'credits'              => 120,
            'language'             => 'Français',
            'entry_requirement'    => 'Licence en économie et sélection sur dossier',
            'application_deadline' => date('Y') + 1 . '-06-30',
            'curriculum'           => json_encode(['Semestre 1 | Cours de méthodes | 6', 'Semestre 2 | Mémoire | 30'], JSON_UNESCAPED_UNICODE),
            'coordinator_staff_id' => $coordinatorId,
            'apply_url'            => 'https://candidature.example.test/master',
        ]);

        $result = $this->get('/formations/master-riche');
        $result->assertOK();
        $body = html_entity_decode((string) $result->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString('facts-bar', $body);
        $this->assertStringContainsString('120 crédits', $body);
        $this->assertStringContainsString('Conditions d’accès', $body);
        $this->assertStringContainsString('Licence en économie et sélection sur dossier', $body);
        $this->assertStringContainsString('semester-badge', $body);
        $this->assertStringContainsString('Semestre 1', $body);
        $this->assertStringContainsString('Cours de méthodes', $body);
        $this->assertStringContainsString('Coordinatrice Programme', $body);
        $this->assertStringContainsString('https://candidature.example.test/master', $body);
        $this->assertStringContainsString('"@type":"Course"', $body);
        $this->assertStringContainsString('"numberOfCredits":120', $body);
    }

    public function testAdmissionsListsDeadlinesAndAdmissionsFaq(): void
    {
        $this->insertProgramme(1, 'Licence avec date limite', 'licence-date-limite', ['application_deadline' => date('Y') + 1 . '-07-31']);
        $this->insertFaq(1, 'admissions', 'Question admission test ?', 'Réponse admission test.');
        $this->insertFaq(1, 'studies', 'Question études test ?', 'Réponse études test.');

        $result = $this->get('/admissions');
        $result->assertOK();
        $result->assertSee('Licence avec date limite');
        $result->assertSee('Question admission test ?');
        $result->assertDontSee('Question études test ?');
        $body = html_entity_decode((string) $result->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('class="page-intro"', $body);
        $this->assertStringContainsString('class="cta-band"', $body);
        $this->assertStringContainsString('data-label="Date limite de candidature"', $body);
        $this->assertStringNotContainsString('tuition', $body);
    }

    public function testFaqGroupsByCategoryAndEmitsFaqPageJsonLd(): void
    {
        $this->insertFaq(1, 'fees', 'Combien coûte la licence test ?', 'Voir la fiche.');

        $result = $this->get('/faq');
        $result->assertOK();
        $body = html_entity_decode((string) $result->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString('id="faq-fees"', $body);
        $this->assertStringContainsString('Frais et gratuité', $body);
        $this->assertStringContainsString('Encore une question ?', $body);
        $this->assertStringContainsString('"@type":"FAQPage"', $body);
        $this->assertStringContainsString('Combien coûte la licence test ?', $body);
    }

    public function testFacultyPageShowsEveryPartnerInTheMarquee(): void
    {
        for ($i = 1; $i <= 14; $i++) {
            $this->insertPartner(1, sprintf('Partenaire Défilant %02d', $i), $i % 2 === 0 ? 'industry' : 'academic', $i === 1 ? 'https://partenaire.example.test' : null);
        }

        $body = html_entity_decode((string) $this->get('/faculte')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString('data-marquee', $body);
        for ($i = 1; $i <= 14; $i++) {
            $this->assertStringContainsString(sprintf('Partenaire Défilant %02d', $i), $body);
        }
        $this->assertMatchesRegularExpression('#<a[^>]+href="https://partenaire\.example\.test"[^>]+rel="noopener"#', $body);
        $this->assertStringNotContainsString('/partenaires"', $body);
    }

    public function testSitemapIsValidXmlWithHreflangAlternates(): void
    {
        $this->insertProgramme(1, 'Programme du plan du site', 'programme-plan-site');

        $result = $this->get('/sitemap.xml');
        $result->assertOK();
        $this->assertStringContainsString('application/xml', $result->response()->getHeaderLine('Content-Type'));

        $xml = simplexml_load_string((string) $result->response()->getBody());
        $this->assertNotFalse($xml);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('/formations/programme-plan-site</loc>', $body);
        $this->assertStringContainsString('hreflang="en"', $body);
        $this->assertStringContainsString('?lang=en', $body);
        $this->assertStringContainsString('hreflang="x-default"', $body);
        $this->assertStringContainsString('/mentions-legales</loc>', $body);
    }

    public function testRobotsTxtIsGeneratedWithAbsoluteSitemapUrl(): void
    {
        $this->assertFileDoesNotExist(FCPATH . 'robots.txt');

        $result = $this->get('/robots.txt');
        $result->assertOK();
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('text/plain', $result->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("User-agent: *\n", $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertMatchesRegularExpression('#^Sitemap: https?://[^\s]+/sitemap\.xml$#m', $body);
    }

    public function testCanonicalAndHreflangFollowTheQueryParameterScheme(): void
    {
        $french = html_entity_decode((string) $this->get('/formations')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertMatchesRegularExpression('#<link rel="canonical" href="https?://[^"]+/formations">#', $french);
        $this->assertMatchesRegularExpression('#hreflang="en" href="https?://[^"]+/formations\?lang=en"#', $french);
        $this->assertStringContainsString('hreflang="x-default"', $french);
        $this->assertStringContainsString('"@type":"CollegeOrUniversity"', $french);

        $english = html_entity_decode((string) $this->get('/formations?lang=en')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('<html lang="en">', $english);
        $this->assertMatchesRegularExpression('#<link rel="canonical" href="https?://[^"]+/formations\?lang=en">#', $english);
        $this->assertMatchesRegularExpression('#class="language-switcher-link[^"]*"\s+href="https?://[^"]+/formations\?lang=fr"#', $english);
    }

    public function testEventIcsDownloadIsValidAndNewsHasNoIcs(): void
    {
        $this->insertPost(1, 'event', 'Soutenance, publique; test', 'soutenance-test', date('Y-m-d H:i:s', strtotime('+10 days')));
        $this->insertPost(1, 'news', 'Actualité simple test', 'actualite-simple-test', null);

        $result = $this->get('/actualites/soutenance-test/ics');
        $result->assertOK();
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('text/calendar', $result->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment; filename="soutenance-test.ics"', $result->response()->getHeaderLine('Content-Disposition'));
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $body);
        $this->assertStringContainsString("SUMMARY:Soutenance\\, publique\\; test\r\n", $body);
        $this->assertMatchesRegularExpression('/DTSTART:\d{8}T\d{6}Z\r\n/', $body);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $body);

        $this->get('/actualites/actualite-simple-test/ics')->assertStatus(404);

        $detail = $this->get('/actualites/soutenance-test');
        $detail->assertSee('Ajouter à mon agenda');
        $this->assertStringContainsString('"@type":"Event"', html_entity_decode((string) $detail->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function testDepartmentsAreGroupedByCycleAndFilterProgrammesByLevel(): void
    {
        $this->insertDepartment(1, 'Département Cycle Licence', 'cycle-licence-test', 'licence');
        $this->insertDepartment(1, 'Département Cycle Master', 'cycle-master-test', 'master');
        $this->insertDepartment(1, 'Département Tous Cycles', 'cycle-both-test', 'both');

        $body = html_entity_decode((string) $this->get('/departements')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $licenceStart = strpos($body, 'id="cycle-licence"');
        $masterStart = strpos($body, 'id="cycle-master"');
        $this->assertNotFalse($licenceStart);
        $this->assertNotFalse($masterStart);
        $licence = substr($body, $licenceStart, $masterStart - $licenceStart);
        $master = substr($body, $masterStart);

        $this->assertStringContainsString('Département Cycle Licence', $licence);
        $this->assertStringContainsString('Département Tous Cycles', $licence);
        $this->assertStringNotContainsString('Département Cycle Master', $licence);
        $this->assertStringContainsString('Département Cycle Master', $master);
        $this->assertStringContainsString('Département Tous Cycles', $master);
        $this->assertStringNotContainsString('Département Cycle Licence', $master);

        $programmes = html_entity_decode((string) $this->get('/formations?niveau=master')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('niveau=master&departement=cycle-master-test', $programmes);
        $this->assertStringNotContainsString('niveau=master&departement=cycle-licence-test', $programmes);
    }

    public function testPostShowsAuthorAttachmentAndPlainParagraphs(): void
    {
        $slug = (string) $this->db->table('sites')->where('id', 1)->get()->getRow()->slug;
        $path = service('mediaService')->storeBinaryForSite($slug, 'posts-files', "%PDF-1.4\n% test\n", 'pdf');
        $this->assertNotNull($path);

        $this->insertPost(1, 'news', 'Actualité avec pièce jointe', 'actualite-piece-jointe', null, [
            'body'            => '<p>Premier paragraphe.</p><p>Second paragraphe.</p>',
            'author_name'     => 'Service communication',
            'attachment_path' => $path,
            'attachment_name' => 'programme-test.pdf',
        ]);

        try {
            $body = html_entity_decode((string) $this->get('/actualites/actualite-piece-jointe')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $this->assertStringContainsString('Par Service communication', $body);
            $this->assertStringContainsString('attachment-card', $body);
            $this->assertStringContainsString('programme-test.pdf', $body);
            $this->assertStringContainsString('<p>Premier paragraphe.</p>', $body);
            $this->assertStringNotContainsString('<p><p>', $body);
            $this->assertStringNotContainsString('&lt;p&gt;', (string) $this->get('/actualites/actualite-piece-jointe')->response()->getBody());

            $list = html_entity_decode((string) $this->get('/actualites')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $this->assertStringContainsString('Par Service communication', $list);
        } finally {
            service('mediaService')->deletePublicPath($path);
        }
    }

    public function testHomepageSplitsNewsAndEventsIntoRails(): void
    {
        $this->insertPost(1, 'news', 'Actualité du rail test', 'actualite-rail-test', null, ['author_name' => 'Décanat test']);
        $this->insertPost(1, 'event', 'Événement du rail test', 'evenement-rail-test', date('Y-m-d H:i:s', strtotime('+5 days')));

        $body = html_entity_decode((string) $this->get('/')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $news = strpos($body, 'id="rail-news"');
        $events = strpos($body, 'id="rail-events"');
        $this->assertNotFalse($news);
        $this->assertNotFalse($events);
        $this->assertStringContainsString('data-rail-prev', $body);
        $this->assertStringContainsString('data-rail-next', $body);
        $this->assertStringContainsString('actualites?type=actualite"', $body);
        $this->assertStringContainsString('actualites?type=evenement"', $body);
        $this->assertStringContainsString('Par Décanat test', $body);

        $newsBlock = substr($body, $news, $events - $news);
        $this->assertStringContainsString('Actualité du rail test', $newsBlock);
        $this->assertStringNotContainsString('Événement du rail test', $newsBlock);
        $this->assertStringContainsString('Événement du rail test', substr($body, $events));
    }

    public function testStaffDirectoryFiltersAndPaginates(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->insertStaff(1, sprintf('Enseignant Pagination %02d', $i), 'enseignant-pagination-' . $i, ['display_order' => 100 + $i, 'grade' => 'Assistant']);
        }
        $this->insertStaff(1, 'Agent Administratif Filtre', 'agent-administratif-filtre', ['category' => 'administratif', 'specialty' => 'Scolarité']);

        $first = (string) $this->get('/corps-enseignant?categorie=enseignant')->response()->getBody();
        $this->assertSame(24, substr_count($first, 'class="staff-card'));
        $this->assertStringNotContainsString('Agent Administratif Filtre', $first);
        $this->assertStringContainsString('page_staff=2', html_entity_decode($first));

        $admin = $this->get('/corps-enseignant?categorie=administratif');
        $admin->assertSee('Agent Administratif Filtre');
        $admin->assertDontSee('Enseignant Pagination 01');

        $search = $this->get('/corps-enseignant?q=Scolarit');
        $search->assertSee('Agent Administratif Filtre');
        $search->assertDontSee('Enseignant Pagination 01');

        $this->get('/corps-enseignant?categorie=inconnu&grade=%3Cscript%3E')->assertOK();
    }

    public function testSiteSearchCoversDepartmentsFaqAndPartners(): void
    {
        $this->insertDepartment(1, 'Département Zéphyrologie', 'zephyrologie');
        $this->insertFaq(1, 'other', 'Où trouver la zéphyrologie ?', 'Au bâtiment B.');
        $this->insertPartner(1, 'Institut Zéphyr', 'institution');

        $result = $this->get('/trouver?q=' . rawurlencode('Zéphyr'));
        $result->assertOK();
        $result->assertSee('Département Zéphyrologie');
        $result->assertSee('Où trouver la zéphyrologie ?');
        $result->assertSee('Institut Zéphyr');
        $this->assertStringContainsString('noindex', (string) $result->response()->getBody());
    }

    public function testAnalyticsIsOffByDefaultAndAllowsOnlyTheConfiguredHttpsOrigin(): void
    {
        $this->assertStringNotContainsString('analytics.js', (string) $this->get('/')->response()->getBody());

        $this->setSetting('analytics.provider', 'matomo');
        $this->setSetting('analytics.url', 'http://stats.example.test');
        $this->setSetting('analytics.site_id', '7');
        service('settingsService')->reset();
        $this->assertStringNotContainsString('analytics.js', (string) $this->get('/')->response()->getBody());

        $this->setSetting('analytics.url', 'https://stats.example.test');
        service('settingsService')->reset();
        $result = $this->get('/');
        $body = html_entity_decode((string) $result->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('assets/js/analytics.js', $body);
        $this->assertStringContainsString('data-provider="matomo"', $body);
        $this->assertStringContainsString('data-url="https://stats.example.test/"', $body);

        $response = $result->response();
        $response->getCSP()->finalize($response);
        $this->assertStringContainsString('https://stats.example.test', $response->getHeaderLine('Content-Security-Policy'));

        service('settingsService')->reset();
        $this->get('/mentions-legales')->assertSee('Matomo');

        $this->db->table('settings')->where('site_id', 1)->like('key', 'analytics.', 'after')->delete();
        service('settingsService')->reset();
    }

    public function testNavigationDropdownsAndFooterLegalLinks(): void
    {
        $body = html_entity_decode((string) $this->get('/')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString('data-bs-toggle="dropdown"', $body);
        foreach (['/departements"', '/admissions"', '/faq"', '/mentions-legales"'] as $link) {
            $this->assertStringContainsString($link, $body);
        }
        foreach (['/partenaires"', '/agenda"', '/sitemap.xml"'] as $link) {
            $this->assertStringNotContainsString($link, $body);
        }
        $this->assertMatchesRegularExpression('#href="https?://[^"]+/admin" rel="nofollow"#', $body);
        $this->assertStringContainsString('Administration', $body);
        $this->assertStringContainsString('/mentions-legales#donnees-personnelles"', (string) html_entity_decode((string) $this->get('/contact')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function testNestedTrunksBuildTheStudyPathStepper(): void
    {
        $departmentId = $this->insertDepartment(1, 'Département Options Test', 'dept-options', 'both', 'TICX');
        $core = ['department_id' => $departmentId, 'duration' => '4 ans', 'common_core' => 'Tronc commun Test TIC', 'common_core_years' => 1];
        $this->insertProgramme(1, 'Licence Option GI', 'licence-option-gi', $core + ['option_code' => 'GI', 'branch_core' => 'Tronc commun Test GI-GL', 'display_order' => 1]);
        $this->insertProgramme(1, 'Licence Option GL', 'licence-option-gl', $core + ['option_code' => 'GL', 'branch_core' => 'Tronc commun Test GI-GL', 'display_order' => 2]);
        $this->insertProgramme(1, 'Licence Option RT', 'licence-option-rt', $core + ['option_code' => 'RT', 'branch_core' => 'Tronc commun Test SE-RT', 'display_order' => 3]);
        $this->insertProgramme(1, 'Licence Option SE', 'licence-option-se', $core + ['option_code' => 'SE', 'branch_core' => 'Tronc commun Test SE-RT', 'display_order' => 4]);
        $this->insertProgramme(1, 'Licence Option Seule', 'licence-option-seule', ['department_id' => $departmentId, 'option_code' => 'SO', 'display_order' => 5]);
        $this->insertProgramme($this->createSecondSite(), 'Licence Intruse', 'licence-intruse', ['common_core' => 'Tronc commun Test TIC', 'option_code' => 'XX']);

        $decode = static fn ($result): string => html_entity_decode((string) $result->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $programme = $this->get('/formations/licence-option-gi');
        $programme->assertOK();
        $body = $decode($programme);
        $this->assertStringContainsString('common-core-card', $body);
        $this->assertStringContainsString('study-stage-core', $body);
        $this->assertStringContainsString('study-stage-branch', $body);
        $this->assertStringContainsString('study-stage-option', $body);
        $this->assertMatchesRegularExpression('#study-stage-years">Bac I</span>.*Tronc commun Test TIC#s', $body);
        $this->assertMatchesRegularExpression('#study-stage-years">Bac II</span>.*Tronc commun Test GI-GL#s', $body);
        $this->assertStringContainsString('study-stage-years">Bac III–IV</span>', $body);
        $this->assertStringContainsString('/formations/licence-option-se"', $body);
        $this->assertStringNotContainsString('Licence Intruse', $body);

        $this->assertStringNotContainsString('common-core-card', $decode($this->get('/formations/licence-option-seule')));

        $list = $decode($this->get('/formations'));
        $this->assertStringContainsString('Tronc commun Test TIC · option dès le Bac III', $list);
        $this->assertStringNotContainsString('Licence Intruse', $list);

        $department = $decode($this->get('/departements/dept-options'));
        $this->assertStringContainsString('study-path-section', $department);
        $this->assertStringContainsString('Organisation des études', $department);
        $this->assertSame(2, substr_count($department, 'study-step-branch"'));
        $this->assertStringContainsString('Tronc commun Test GI-GL', $department);
        $this->assertStringContainsString('Tronc commun Test SE-RT', $department);
        $this->assertStringContainsString('Licence Option Seule', $department);
        $this->assertStringNotContainsString('maquette-card', $department);

        $departments = $decode($this->get('/departements'));
        $this->assertStringContainsString('option-code-list', $departments);
        $this->assertStringContainsString('<span class="option-code">RT</span>', $departments);
    }

    public function testTrunkSharedAcrossDepartmentsLinksTheOtherDepartments(): void
    {
        $em = $this->insertDepartment(1, 'Département Électromécanique Test', 'dept-em-test', 'licence', 'EMX');
        $gc = $this->insertDepartment(1, 'Département Génie civil Test', 'dept-gc-test', 'licence', 'GCX');
        $shared = ['duration' => '4 ans', 'common_core' => 'Tronc commun Test FSI', 'common_core_years' => 1];
        $this->insertProgramme(1, 'Licence Génie mécanique Test', 'licence-gme-test', $shared + ['department_id' => $em, 'option_code' => 'GMX']);
        $this->insertProgramme(1, 'Licence Bâtiments Test', 'licence-bi-test', $shared + ['department_id' => $gc, 'option_code' => 'BIX', 'branch_core' => 'Tronc commun Test GC']);
        $this->insertProgramme(1, 'Licence Hydraulique Test', 'licence-gh-test', $shared + ['department_id' => $gc, 'option_code' => 'GHX', 'branch_core' => 'Tronc commun Test GC']);

        $decode = fn (string $path): string => html_entity_decode((string) $this->get($path)->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $emPage = $decode('/departements/dept-em-test');
        $this->assertStringContainsString('Tronc commun Test FSI', $emPage);
        $this->assertStringContainsString('Partagé avec', $emPage);
        $this->assertMatchesRegularExpression('#class="dept-chip" href="[^"]*/departements/dept-gc-test"[^>]*>GCX</a>#', $emPage);
        $this->assertStringContainsString('Bac II–IV', $emPage);

        $gcPage = $decode('/departements/dept-gc-test');
        $this->assertMatchesRegularExpression('#class="dept-chip" href="[^"]*/departements/dept-em-test"#', $gcPage);
        $this->assertStringContainsString('Tronc commun Test GC', $gcPage);
        $this->assertStringContainsString('Bac III–IV', $gcPage);

        $programme = $decode('/formations/licence-gme-test');
        $this->assertStringContainsString('study-stage-core', $programme);
        $this->assertStringContainsString('/formations/licence-bi-test"', $programme);
        $this->assertStringContainsString('Partagé avec : Département Génie civil Test', $programme);
    }

    public function testDepartmentMaquetteIsOfferedOnlyWhenTheFileExists(): void
    {
        $path = 'uploads/tests/maquette-' . bin2hex(random_bytes(4)) . '.pdf';
        $this->insertDepartment(1, 'Département Maquette Test', 'dept-maquette-test');
        $this->db->table('departments')->where('slug', 'dept-maquette-test')->update(['maquette_path' => $path, 'maquette_title' => 'Maquettes test 2024-2025']);

        $decode = static fn (string $page): string => html_entity_decode($page, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $missing = $decode((string) $this->get('/departements/dept-maquette-test')->response()->getBody());
        $this->assertStringNotContainsString('maquette-card', $missing);
        $this->assertStringNotContainsString($path, $missing);

        if (! is_dir(FCPATH . 'uploads/tests')) {
            mkdir(FCPATH . 'uploads/tests', 0775, true);
        }
        file_put_contents(FCPATH . $path, "%PDF-1.4\n" . str_repeat('0', 4096));

        try {
            $body = $decode((string) $this->get('/departements/dept-maquette-test')->response()->getBody());
            $this->assertStringContainsString('maquette-card', $body);
            $this->assertStringContainsString('Maquettes test 2024-2025', $body);
            $this->assertStringContainsString('PDF, 4 Ko', $body);
            $this->assertMatchesRegularExpression('#href="[^"]*' . preg_quote($path, '#') . '" class="[^"]*" download>#', $body);
            $this->assertMatchesRegularExpression('#href="[^"]*' . preg_quote($path, '#') . '" class="[^"]*" target="_blank" rel="noopener">#', $body);
        } finally {
            @unlink(FCPATH . $path);
        }
    }

    public function testFormationsListNoDoctorateAndLinkTheDoctoralSiteOnlyOnceSet(): void
    {
        $this->insertProgramme(1, 'Doctorat Hérité Test', 'doctorat-herite-test', ['level' => 'doctorat']);

        $body = html_entity_decode((string) $this->get('/formations')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringNotContainsString('Doctorat Hérité Test', $body);
        $this->assertStringNotContainsString('doctoral-callout', $body);
        $this->assertStringNotContainsString('Études doctorales', $body);
        $this->get('/formations/doctorat-herite-test')->assertStatus(404);

        $this->setSetting('links.doctoral_studies_url', 'http://doctorat.example.test');
        service('settingsService')->reset();
        $this->assertStringNotContainsString('doctoral-callout', (string) $this->get('/formations')->response()->getBody());

        $this->setSetting('links.doctoral_studies_url', 'https://doctorat.example.test');
        service('settingsService')->reset();
        $body = html_entity_decode((string) $this->get('/formations')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('doctoral-callout', $body);
        $this->assertStringContainsString('href="https://doctorat.example.test" class="btn btn-outline-green btn-sm ms-md-auto" rel="noopener" target="_blank"', $body);
        $this->assertStringContainsString('<a href="https://doctorat.example.test" rel="noopener">Études doctorales', $body);
        $this->assertStringNotContainsString('Doctorat Hérité Test', $body);

        $home = html_entity_decode((string) $this->get('/')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('<a href="https://doctorat.example.test" rel="noopener">Études doctorales', $home);
        $this->assertStringNotContainsString('doctoral-callout', $home);

        $this->db->table('settings')->where('site_id', 1)->where('key', 'links.doctoral_studies_url')->delete();
        service('settingsService')->reset();
    }

    public function testRemoveDoctoralProgrammesMigrationHidesExistingRows(): void
    {
        $id = $this->insertProgramme(1, 'Doctorat Migré Test', 'doctorat-migre-test', ['level' => 'doctorat', 'featured_on_home' => 1]);
        $licenceId = $this->insertProgramme(1, 'Licence Conservée Test', 'licence-conservee-test');

        (new App\Database\Migrations\RemoveDoctoralProgrammes(Config\Database::forge($this->db)))->up();

        $row = $this->db->table('programmes')->where('id', $id)->get()->getRowArray();
        $this->assertNotNull($row['deleted_at']);
        $this->assertSame(0, (int) $row['is_published']);
        $this->assertSame(0, (int) $row['featured_on_home']);

        $kept = $this->db->table('programmes')->where('id', $licenceId)->get()->getRowArray();
        $this->assertNull($kept['deleted_at']);
        $this->assertSame(1, (int) $kept['is_published']);
    }

    private function createSecondSite(): int
    {
        $existing = $this->db->table('sites')->where('slug', 'droit')->get()->getRowArray();
        if (is_array($existing)) {
            return (int) $existing['id'];
        }

        return (int) model(SiteModel::class, false)->insert([
            'identifier'      => 'droit',
            'name'            => 'Faculté de Droit',
            'slug'            => 'droit',
            'hostnames'       => ['droit.test'],
            'status'          => 'active',
            'default_locale'  => 'fr',
            'logo'            => 'assets/images/logo-placeholder.png',
            'primary_color'   => '#0D9B49',
            'secondary_color' => '#0B6F38',
        ], true);
    }

    private function insertDepartment(int $siteId, string $name, string $slug, string $cycle = 'both', string $abbreviation = 'TST'): int
    {
        $this->db->table('departments')->insert([
            'site_id' => $siteId, 'name' => $name, 'slug' => $slug, 'abbreviation' => $abbreviation, 'cycle' => $cycle,
            'description' => 'Description de ' . $name, 'display_order' => 1, 'is_published' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function insertProgramme(int $siteId, string $title, string $slug, array $extra = []): int
    {
        $this->db->table('programmes')->insert($extra + [
            'site_id' => $siteId, 'level' => 'licence', 'title' => $title, 'slug' => $slug, 'duration' => '3 ans',
            'summary' => 'Résumé ' . $title, 'description' => 'Description ' . $title, 'admission_conditions' => 'Diplôme requis.',
            'career_outcomes' => json_encode([]), 'display_order' => 50, 'featured_on_home' => 0, 'is_published' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function insertStaff(int $siteId, string $name, string $slug, array $extra = []): int
    {
        $this->db->table('staff')->insert($extra + [
            'site_id' => $siteId, 'category' => 'enseignant', 'name' => $name, 'slug' => $slug, 'grade' => 'Professeur',
            'specialty' => 'Spécialité test', 'role' => 'Enseignant', 'display_order' => 90, 'featured_on_home' => 0,
            'is_published' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertFaq(int $siteId, string $category, string $question, string $answer): void
    {
        $this->db->table('faq_items')->insert([
            'site_id' => $siteId, 'category' => $category, 'question' => $question, 'answer' => $answer,
            'display_order' => 1, 'is_published' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertPartner(int $siteId, string $name, string $type, ?string $url = null): void
    {
        $this->db->table('partners')->insert([
            'site_id' => $siteId, 'name' => $name, 'type' => $type, 'country' => 'Burundi', 'url' => $url,
            'display_order' => 1, 'is_published' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function insertPost(int $siteId, string $type, string $title, string $slug, ?string $startsAt, array $extra = []): void
    {
        $this->db->table('posts')->insert($extra + [
            'site_id' => $siteId, 'type' => $type, 'title' => $title, 'slug' => $slug,
            'excerpt' => 'Extrait ' . $title, 'body' => 'Corps ' . $title, 'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'featured' => 0,
            'event_starts_at' => $startsAt, 'event_location' => $startsAt === null ? null : 'Amphithéâtre A',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function setSetting(string $key, string $value): void
    {
        $this->db->table('settings')->where('site_id', 1)->where('key', $key)->delete();
        $this->db->table('settings')->insert([
            'site_id' => 1, 'class' => 'App\\Settings\\Site', 'key' => $key, 'value' => $value,
            'type' => 'string', 'context' => 'analytics', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
