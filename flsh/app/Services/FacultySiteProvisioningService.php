<?php

namespace App\Services;

use App\Models\SiteModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Models\UserModel;
use RuntimeException;

class FacultySiteProvisioningService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @param array<string, mixed> $siteData
     */
    public function createFaculty(array $siteData, ?int $firstAdminUserId = null, bool $withStarterContent = true): int
    {
        $siteData += [
            'status'           => 'active',
            'default_locale'   => 'fr',
            'primary_color'    => '#0D9B49',
            'secondary_color'  => '#0B6F38',
            'theme'            => 'default',
            'theme_config'     => json_encode([
                'layout'     => 'classic',
                'hero_image' => 'assets/images/logo-placeholder.png',
            ], JSON_UNESCAPED_SLASHES),
            'menu_config'      => json_encode([
                'items' => ['faculte', 'formations', 'recherche', 'corps-enseignant', 'actualites', 'alumni', 'contact'],
            ], JSON_UNESCAPED_SLASHES),
            'enabled_sections' => FacultyDemoDataService::DEFAULT_SECTION_ORDER,
        ];

        // Single public theme; ignore any legacy theme key from callers.
        $siteData['theme'] = 'default';
        if (empty($siteData['theme_config'])) {
            $siteData['theme_config'] = json_encode([
                'layout'     => 'classic',
                'hero_image' => 'assets/images/logo-placeholder.png',
            ], JSON_UNESCAPED_SLASHES);
        }

        if (! isset($siteData['hostnames']) || $siteData['hostnames'] === null || $siteData['hostnames'] === []) {
            $siteData['hostnames'] = $this->hostnamesForSlug((string) ($siteData['slug'] ?? ''));
        }

        $siteModel = model(SiteModel::class, false);
        $siteModel->skipValidation(false);

        $siteId = $siteModel->insert($siteData, true);
        if ($siteId === false) {
            throw new RuntimeException('La création du site facultaire a échoué.');
        }

        $siteId = (int) $siteId;

        if ($withStarterContent) {
            $this->provisionStarterContent($siteId);
        }

        if ($firstAdminUserId !== null && $firstAdminUserId > 0) {
            $this->assignFacultyAdmin($siteId, $firstAdminUserId);
        }

        return $siteId;
    }

    /**
     * Public hostnames for a faculty slug. Nothing is invented in code:
     * set app.publicHostPattern in .env, with {slug} replaced by the faculty
     * slug. Examples: "{slug}.test" locally, "{slug}.account.alwaysdata.net"
     * on Alwaysdata, "{slug}.ub.edu.bi" on a university domain.
     * An empty pattern stores no hostname; the folder still serves its
     * faculty through app.siteSlug.
     *
     * @return list<string>
     */
    public function hostnamesForSlug(string $slug): array
    {
        $pattern = trim((string) env('app.publicHostPattern', ''));
        $slug = strtolower(trim($slug));
        if ($pattern === '' || $slug === '') {
            return [];
        }

        $hosts = [];
        foreach (preg_split('/[\s,]+/', $pattern) ?: [] as $part) {
            $host = strtolower(trim(str_replace('{slug}', $slug, (string) $part)));
            if ($host === '' || in_array($host, $hosts, true)) {
                continue;
            }
            $hosts[] = $host;
        }

        return $hosts;
    }

    public function provisionStarterContent(int $siteId): void
    {
        $site = model(SiteModel::class, false)->find($siteId);
        if ($site === null) {
            throw new RuntimeException('Le site facultaire est introuvable.');
        }

        $name = trim((string) ($site->name ?? '')) ?: 'Faculté à configurer';
        $short = strtoupper(trim((string) ($site->identifier ?? ''))) ?: 'FAC';
        $slug = strtolower(trim((string) ($site->slug ?? 'faculte')));

        $this->db->transStart();

        $homeId = $this->homeContent($siteId, $name, $short);
        $this->heroSlides($siteId);
        $this->highlights($siteId);
        $this->settings($siteId, $name, $short, (array) $site->toArray());
        $this->stats($siteId);
        $this->programmes($siteId);
        $this->staff($siteId);
        $this->research($siteId, $short);
        $this->timeline($siteId);
        $this->alumni($siteId, $slug);
        $this->pages($siteId, $name, $short);
        $this->posts($siteId, $name, $slug);
        $this->contentBlocks($siteId);
        $this->starterTranslations($siteId, $homeId, $name, $short);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('La génération des contenus de départ a échoué.');
        }
    }

    public function assignFacultyAdmin(int $siteId, int $userId): void
    {
        if ($siteId <= 0 || $userId <= 0) {
            return;
        }

        $user = model(UserModel::class)->findById($userId);
        if ($user !== null) {
            $user->addGroup('admin');
        }

        service('siteResolver')->syncUserSites($userId, [$siteId], 'site_admin');
    }

    private function homeContent(int $siteId, string $name, string $short): int
    {
        $existing = $this->row('home_content', $siteId, ['singleton_key' => 1]);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return $this->insert('home_content', [
            'site_id'                  => $siteId,
            'singleton_key'            => 1,
            'hero_badge'               => $short . ' · Université du Burundi',
            'hero_title'               => 'Bienvenue sur le site de ' . $short,
            'hero_text'                => 'Texte de présentation à remplacer par l’équipe de la faculté.',
            'hero_media_type'          => 'image',
            'hero_media_path'          => 'assets/images/logo-placeholder.png',
            'hero_primary_label'       => 'Découvrir les formations',
            'hero_primary_url'         => '/formations',
            'hero_secondary_label'     => 'Contacter la faculté',
            'hero_secondary_url'       => '/contact',
            'about_label'              => 'Présentation',
            'about_title'              => 'Une faculté à présenter',
            'about_body'               => 'Texte de présentation à remplacer. Décrivez ici la mission, les filières et les priorités de la faculté.',
            'about_button_label'       => 'En savoir plus',
            'about_button_url'         => '/faculte',
            'research_label'           => 'Recherche',
            'research_title'           => 'Axes de recherche à compléter',
            'research_body'            => 'Décrivez ici les laboratoires, équipes et projets de recherche de la faculté.',
            'research_button_label'    => 'Explorer la recherche',
            'research_button_url'      => '/recherche',
            'programmes_label'         => 'Formations',
            'programmes_title'         => 'Programmes à compléter',
            'programmes_text'          => 'Présentez ici les programmes académiques proposés par la faculté.',
            'programmes_button_label'  => 'Voir les formations',
            'programmes_button_url'    => '/formations',
            'posts_label'              => 'Actualités',
            'posts_title'              => 'Actualités et événements',
            'posts_text'               => 'Publiez ici les annonces, communiqués et événements de la faculté.',
            'posts_button_label'       => 'Toutes les actualités',
            'posts_button_url'         => '/actualites',
            'seo_title'                => $short . ' | Université du Burundi',
            'seo_description'          => 'Site facultaire à compléter pour ' . $name . '.',
            'updated_by'               => null,
            'created_at'               => $this->now(),
            'updated_at'               => $this->now(),
        ]);
    }

    private function heroSlides(int $siteId): void
    {
        if ($this->db->table('home_hero_slides')->where('site_id', $siteId)->countAllResults() > 0) {
            return;
        }

        // Minimal starter until `php spark site:seed-demo --force` materializes site-owned uploads.
        foreach ([
            ['assets/images/logo-placeholder.png', 'Image de départ (remplacée par le seed démo)', 1],
        ] as [$image, $alt, $order]) {
            $this->insertIfMissing('home_hero_slides', $siteId, ['image_path' => $image], [
                'image_path' => $image,
                'alt_text' => $alt,
                'display_order' => $order,
                'is_published' => 1,
            ]);
        }
    }

    private function highlights(int $siteId): void
    {
        foreach ([
            ['bi-mortarboard', 'Formations à compléter', 'Présentez les forces pédagogiques de la faculté.', 1],
            ['bi-search', 'Recherche à compléter', 'Présentez les laboratoires et axes de recherche.', 2],
            ['bi-people', 'Communauté à compléter', 'Présentez les étudiants, enseignants et partenaires.', 3],
        ] as [$icon, $title, $description, $order]) {
            $this->insertIfMissing('home_highlights', $siteId, ['title' => $title], [
                'icon' => $icon,
                'title' => $title,
                'description' => $description,
                'display_order' => $order,
                'is_published' => 1,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $site
     */
    private function settings(int $siteId, string $name, string $short, array $site): void
    {
        foreach ([
            ['institution.faculty_name', $name, 'string', 'institution'],
            ['institution.short_name', $short, 'string', 'institution'],
            ['institution.university', 'Université du Burundi', 'string', 'institution'],
            ['contact.address_line', (string) ($site['address'] ?? 'Adresse à compléter'), 'string', 'contact'],
            ['contact.address_commune', 'Commune à compléter', 'string', 'contact'],
            ['contact.address_province', 'Province à compléter', 'string', 'contact'],
            ['contact.address_country', 'Burundi', 'string', 'contact'],
            ['contact.phone', (string) ($site['phone'] ?? 'Téléphone à compléter'), 'string', 'contact'],
            ['contact.email', (string) ($site['contact_email'] ?? 'contact@example.test'), 'email', 'contact'],
            ['contact.hours', 'Horaires à compléter', 'string', 'contact'],
            ['footer.text', 'Texte de pied de page à remplacer par la faculté.', 'text', 'footer'],
            ['footer.copyright', '© ' . date('Y') . ' ' . $name . ' — Université du Burundi', 'string', 'footer'],
            ['assets.logo', (string) ($site['logo'] ?? 'assets/images/logo-placeholder.png'), 'path', 'assets'],
            ['seo.default_title', $short . ' | Université du Burundi', 'string', 'seo'],
            ['seo.default_description', 'Description SEO à compléter pour ' . $name . '.', 'text', 'seo'],
            ['seo.theme_color', (string) ($site['primary_color'] ?? '#0D9B49'), 'color', 'seo'],
            ['seo.og_image', 'assets/images/logo-placeholder.png', 'path', 'seo'],
            ['social.links', json_encode([], JSON_UNESCAPED_UNICODE), 'json', 'social'],
        ] as [$key, $value, $type, $context]) {
            $this->insertIfMissing('settings', $siteId, ['key' => $key], [
                'class' => 'App\\Settings\\Site',
                'key' => $key,
                'value' => $value,
                'type' => $type,
                'context' => $context,
            ]);
        }
    }

    private function stats(int $siteId): void
    {
        foreach ([
            ['home_main', 'Étudiants à compléter', 0, '+', 1],
            ['home_main', 'Programmes à compléter', 0, '', 2],
            ['home_main', 'Enseignants à compléter', 0, '', 3],
            ['home_research', 'Publications à compléter', 0, '+', 1],
            ['home_research', 'Projets à compléter', 0, '', 2],
            ['alumni', 'Alumni à compléter', 0, '+', 1],
        ] as [$section, $label, $value, $suffix, $order]) {
            $this->insertIfMissing('site_stats', $siteId, ['section' => $section, 'label' => $label], [
                'section' => $section,
                'label' => $label,
                'value' => $value,
                'suffix' => $suffix,
                'display_order' => $order,
                'is_published' => 1,
            ]);
        }
    }

    private function programmes(int $siteId): void
    {
        foreach ([
            ['licence', 'Licence exemple à modifier', 'licence-exemple', 1],
            ['master', 'Master exemple à modifier', 'master-exemple', 2],
            ['doctorat', 'Doctorat exemple à modifier', 'doctorat-exemple', 3],
        ] as [$level, $title, $slug, $order]) {
            $this->insertIfMissing('programmes', $siteId, ['slug' => $slug], [
                'level' => $level,
                'title' => $title,
                'slug' => $slug,
                'duration' => 'Durée à compléter',
                'summary' => 'Résumé du programme à remplacer.',
                'description' => 'Description détaillée du programme à compléter.',
                'admission_conditions' => 'Conditions d’admission à compléter.',
                'career_outcomes' => json_encode(['Débouché à compléter'], JSON_UNESCAPED_UNICODE),
                'display_order' => $order,
                'featured_on_home' => 1,
                'home_order' => $order,
                'is_published' => 1,
            ]);
        }
    }

    private function staff(int $siteId): void
    {
        foreach ([
            ['enseignant', 'Enseignant exemple', 'enseignant-exemple', 'Grade à compléter', 'Spécialité à compléter', 'Responsabilité à compléter', 1],
            ['administratif', 'Personnel administratif exemple', 'personnel-administratif-exemple', null, null, 'Fonction à compléter', 2],
        ] as [$category, $name, $slug, $grade, $specialty, $role, $order]) {
            $this->insertIfMissing('staff', $siteId, ['slug' => $slug], [
                'category' => $category,
                'name' => $name,
                'slug' => $slug,
                'photo' => null,
                'grade' => $grade,
                'specialty' => $specialty,
                'role' => $role,
                'email' => null,
                'biography' => 'Biographie à compléter.',
                'display_order' => $order,
                'featured_on_home' => 1,
                'home_order' => $order,
                'is_published' => 1,
            ]);
        }
    }

    private function research(int $siteId, string $short): void
    {
        $this->insertIfMissing('laboratories', $siteId, ['slug' => 'laboratoire-exemple'], [
            'abbreviation' => 'LAB-' . substr($short, 0, 8),
            'name' => 'Laboratoire exemple à modifier',
            'slug' => 'laboratoire-exemple',
            'icon' => 'bi-diagram-3',
            'description' => 'Description du laboratoire à compléter.',
            'themes' => json_encode(['Thème de recherche à compléter'], JSON_UNESCAPED_UNICODE),
            'researcher_count' => 0,
            'display_order' => 1,
            'featured_on_home' => 1,
            'home_order' => 1,
            'is_published' => 1,
        ]);

        $this->insertIfMissing('publications', $siteId, ['title' => 'Publication exemple à modifier'], [
            'year' => (int) date('Y'),
            'title' => 'Publication exemple à modifier',
            'authors' => 'Auteurs à compléter',
            'journal' => 'Revue ou support à compléter',
            'url' => null,
            'display_order' => 1,
            'is_published' => 1,
        ]);

        $this->insertIfMissing('research_projects', $siteId, ['code' => 'PROJET-' . $short . '-EXEMPLE'], [
            'code' => 'PROJET-' . $short . '-EXEMPLE',
            'title' => 'Projet de recherche exemple à modifier',
            'description' => 'Description du projet à compléter.',
            'funder' => 'Bailleur à compléter',
            'period_start' => (int) date('Y'),
            'period_end' => null,
            'icon' => 'bi-briefcase',
            'display_order' => 1,
            'is_published' => 1,
        ]);
    }

    private function timeline(int $siteId): void
    {
        $this->insertIfMissing('timeline_items', $siteId, ['title' => 'Repère historique à compléter'], [
            'year' => (int) date('Y'),
            'title' => 'Repère historique à compléter',
            'description' => 'Ajoutez ici un événement important de l’histoire de la faculté.',
            'display_order' => 1,
            'is_published' => 1,
        ]);
    }

    private function alumni(int $siteId, string $slug): void
    {
        $profileId = (int) ($this->row('alumni_profiles', $siteId, ['slug' => 'alumni-exemple'])['id'] ?? 0);
        if ($profileId <= 0) {
            $profileId = $this->insert('alumni_profiles', [
                'site_id' => $siteId,
                'name' => 'Alumni exemple',
                'slug' => 'alumni-exemple',
                'photo' => null,
                'promotion' => 'Promotion à compléter',
                'role' => 'Fonction à compléter',
                'organization' => 'Organisation à compléter',
                'biography' => 'Biographie à compléter.',
                'display_order' => 1,
                'is_published' => 1,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }

        $this->insertIfMissing('testimonials', $siteId, ['person_name' => 'Témoignage exemple'], [
            'alumni_profile_id' => $profileId,
            'person_name' => 'Témoignage exemple',
            'photo' => null,
            'promotion' => 'Promotion à compléter',
            'quote' => 'Témoignage à remplacer par un contenu réel.',
            'display_order' => 1,
            'is_published' => 1,
        ]);
    }

    private function pages(int $siteId, string $name, string $short): void
    {
        foreach ($this->pageDefinitions($name, $short) as $key => $page) {
            $this->insertIfMissing('pages', $siteId, ['key' => $key], [
                'key' => $key,
                'title' => $page['title'],
                'slug' => $page['slug'],
                'content' => json_encode($page['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'seo_title' => $page['title'] . ' | ' . $short,
                'seo_description' => 'Page à compléter pour ' . $name . '.',
                'is_published' => 1,
            ]);
        }
    }

    private function posts(int $siteId, string $name, string $slug): void
    {
        $date = date('Y-m-d H:i:s');
        foreach ([
            ['news', 'Actualité exemple à modifier', 'actualite-exemple-' . $slug, 1],
            ['event', 'Événement exemple à modifier', 'evenement-exemple-' . $slug, 2],
        ] as [$type, $title, $postSlug, $order]) {
            $this->insertIfMissing('posts', $siteId, ['slug' => $postSlug], [
                'type' => $type,
                'title' => $title,
                'slug' => $postSlug,
                'excerpt' => 'Résumé à remplacer par une annonce réelle.',
                'body' => 'Contenu de démonstration à remplacer par l’équipe de la faculté.',
                'cover_image' => null,
                'status' => 'published',
                'published_at' => $date,
                'featured' => 1,
                'home_order' => $order,
                'event_starts_at' => $type === 'event' ? date('Y-m-d 09:00:00', strtotime('+30 days')) : null,
                'event_ends_at' => $type === 'event' ? date('Y-m-d 12:00:00', strtotime('+30 days')) : null,
                'event_location' => $type === 'event' ? 'Lieu à compléter' : null,
                'registration_url' => null,
                'seo_title' => $title . ' | ' . $name,
                'seo_description' => 'Description SEO à compléter.',
                'created_by' => null,
                'updated_by' => null,
            ]);
        }
    }

    private function contentBlocks(int $siteId): void
    {
        if (! $this->db->tableExists('content_blocks')) {
            return;
        }

        foreach ([
            ['home', 'hero', 'Héros principal', 'Bloc de héros à personnaliser.', 1],
            ['home', 'dean_message', 'Mot du doyen', 'Message du doyen à compléter.', 2],
            ['home', 'programmes_preview', 'Aperçu des formations', 'Liste automatique des formations mises en avant.', 3],
            ['home', 'news_preview', 'Actualités récentes', 'Liste automatique des actualités mises en avant.', 4],
            ['contact', 'contact_cta', 'Contact', 'Appel à l’action de contact à personnaliser.', 1],
        ] as [$pageKey, $type, $title, $content, $order]) {
            $this->insertIfMissing('content_blocks', $siteId, ['page_key' => $pageKey, 'type' => $type], [
                'page_key' => $pageKey,
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'settings' => json_encode([], JSON_UNESCAPED_UNICODE),
                'display_order' => $order,
                'is_published' => 1,
            ]);
        }
    }

    private function starterTranslations(int $siteId, int $homeId, string $name, string $short): void
    {
        foreach ([
            'hero_title' => 'Welcome to ' . $short,
            'hero_text' => 'Introductory text to be replaced by the faculty team.',
            'about_title' => 'A faculty profile to complete',
            'about_body' => 'Replace this text with the faculty presentation.',
            'seo_title' => $short . ' | University of Burundi',
            'seo_description' => 'Starter faculty website for ' . $name . '.',
        ] as $field => $value) {
            $this->translation($siteId, 'home_content', $homeId, $field, $value);
        }
    }

    /**
     * @return array<string, array{title: string, slug: string, content: array<string, mixed>}>
     */
    private function pageDefinitions(string $name, string $short): array
    {
        $missing = 'Texte à remplacer par l’équipe de la faculté.';

        return [
            'faculty' => [
                'title' => 'La Faculté',
                'slug' => 'faculte',
                'content' => [
                    'banner_title'    => '',
                    'banner_subtitle' => 'Présentation à compléter pour ' . $name,
                    'dean' => [
                        'label' => 'Mot du doyen',
                        'title' => 'Message à compléter',
                        'photo' => '',
                        'name' => 'Nom du doyen à compléter',
                        'role' => 'Doyen',
                        'specialty' => 'Domaine à compléter',
                        'signature' => 'Signature à compléter',
                        'paragraphs' => [$missing],
                    ],
                    'mission_label' => 'Mission et vision',
                    'mission_title' => 'Notre mission à compléter',
                    'mission' => ['icon' => 'bi-bullseye', 'title' => 'Mission', 'paragraphs' => [$missing]],
                    'vision' => ['icon' => 'bi-eye', 'title' => 'Vision', 'paragraphs' => [$missing]],
                    'values' => [
                        ['icon' => 'bi-stars', 'title' => 'Valeur 1', 'description' => $missing],
                        ['icon' => 'bi-people', 'title' => 'Valeur 2', 'description' => $missing],
                        ['icon' => 'bi-lightbulb', 'title' => 'Valeur 3', 'description' => $missing],
                    ],
                    'history' => ['label' => 'Historique', 'title' => 'Histoire à compléter', 'text' => $missing],
                ],
            ],
            'formations' => [
                'title' => 'Formations',
                'slug' => 'formations',
                'content' => [
                    'banner_title'    => '',
                    'banner_subtitle' => 'Offre de formation à compléter.',
                    'offer_label' => 'Offre académique',
                    'offer_title' => 'Programmes à compléter',
                    'offer_text' => $missing,
                    'cta_title' => 'Besoin d’informations ?',
                    'cta_text' => $missing,
                    'cta_label' => 'Contacter la faculté',
                    'cta_url' => '/contact',
                ],
            ],
            'research' => [
                'title' => 'Recherche',
                'slug' => 'recherche',
                'content' => [
                    'banner_title'    => '',
                    'banner_subtitle' => 'Recherche à compléter.',
                    'labs_label' => 'Laboratoires',
                    'labs_title' => 'Laboratoires à compléter',
                    'labs_text' => $missing,
                    'publications_label' => 'Publications',
                    'publications_title' => 'Publications à compléter',
                    'publications_text' => $missing,
                    'projects_label' => 'Projets',
                    'projects_title' => 'Projets à compléter',
                ],
            ],
            'staff' => ['title' => 'Corps enseignant', 'slug' => 'corps-enseignant', 'content' => ['banner_title' => '', 'banner_subtitle' => 'Personnel à compléter.']],
            'posts' => ['title' => 'Actualités', 'slug' => 'actualites', 'content' => ['banner_title' => '', 'banner_subtitle' => 'Actualités et événements à compléter.']],
            'alumni' => [
                'title' => 'Alumni',
                'slug' => 'alumni',
                'content' => [
                    'banner_title'    => '',
                    'banner_subtitle' => 'Réseau alumni à compléter.',
                    'intro_label' => 'Communauté',
                    'intro_title' => 'Alumni à compléter',
                    'intro_paragraphs' => [$missing],
                    'profiles_label' => 'Profils',
                    'profiles_title' => 'Profils à compléter',
                    'profiles_text' => $missing,
                    'testimonials_label' => 'Témoignages',
                    'testimonials_title' => 'Témoignages à compléter',
                    'cta_title' => 'Rejoindre le réseau',
                    'cta_text' => $missing,
                    'cta_label' => 'Contacter la faculté',
                    'cta_url' => '/contact',
                ],
            ],
            'contact' => [
                'title' => 'Contact',
                'slug' => 'contact',
                'content' => [
                    'banner_title'    => '',
                    'banner_subtitle' => 'Coordonnées à compléter.',
                    'contact_label' => 'Coordonnées',
                    'contact_title' => 'Contacter ' . $short,
                    'form_title' => 'Envoyer un message',
                    'form_help' => $missing,
                    'map_url' => '',
                ],
            ],
        ];
    }

    /**
     * @param array<string, string> $where
     * @param array<string, mixed>  $data
     */
    private function insertIfMissing(string $table, int $siteId, array $where, array $data): ?int
    {
        if (! $this->db->tableExists($table)) {
            return null;
        }

        $existing = $this->row($table, $siteId, $where);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return $this->insert($table, ['site_id' => $siteId] + $data);
    }

    /**
     * @param array<string, string> $where
     */
    private function row(string $table, int $siteId, array $where): ?array
    {
        if (! $this->db->tableExists($table)) {
            return null;
        }

        $builder = $this->db->table($table)->where('site_id', $siteId);
        foreach ($where as $field => $value) {
            $builder->where($field, $value);
        }

        $row = $builder->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insert(string $table, array $data): int
    {
        $now = $this->now();
        $data += ['created_at' => $now, 'updated_at' => $now];
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    private function translation(int $siteId, string $resourceType, int $resourceId, string $field, string $value): void
    {
        $this->insertIfMissing('content_translations', $siteId, [
            'resource_type' => $resourceType,
            'resource_id' => (string) $resourceId,
            'locale' => 'en',
            'field' => $field,
        ], [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'locale' => 'en',
            'field' => $field,
            'value' => $value,
        ]);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
