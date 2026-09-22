<?php

namespace App\Services;

use App\Models\SiteModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Seeds rich French demo content + site-owned images for one faculty.
 */
class FacultyDemoDataService
{
    private BaseConnection $db;
    private DemoMediaService $media;

    public function __construct(?BaseConnection $db = null, ?DemoMediaService $media = null)
    {
        $this->db = $db ?? db_connect();
        $this->media = $media ?? new DemoMediaService();
    }

    /**
     * @return array{site_id:int,slug:string,images:int,modules:list<string>}
     */
    public function seedSite(int $siteId, bool $force = false): array
    {
        $site = model(SiteModel::class, false)->find($siteId);
        if ($site === null) {
            throw new RuntimeException('Site introuvable: ' . $siteId);
        }

        $slug = strtolower(trim((string) $site->slug));
        $name = trim((string) $site->name) ?: ('Faculté ' . strtoupper($slug));
        $short = strtoupper(trim((string) ($site->identifier ?: $slug)));
        $color = '#0D9B49';
        $secondary = '#0B6F38';
        $theme = $this->themeForSlug($slug);

        if (! $force) {
            throw new RuntimeException('Le remplacement du contenu démo exige --force.');
        }

        $this->clearSiteContent($siteId);
        $gallery = $this->media->materializeSiteGallery($slug, $name, $color);
        $heroPath = $gallery['heroes'][0] ?? DemoMediaService::SHARED_LOGO;
        $logoPath = $gallery['logo'] ?: DemoMediaService::SHARED_LOGO;

        $this->db->transStart();

        $this->db->table('sites')->where('id', $siteId)->update([
            'primary_color'   => $color,
            'secondary_color' => $secondary,
            'logo'            => $logoPath,
            'updated_at'      => $this->now(),
        ]);

        $this->seedHome($siteId, $name, $short, $theme, $heroPath);
        $this->seedHeroes($siteId, $gallery['heroes'], $theme);
        $this->seedStats($siteId, $theme);
        $this->seedProgrammes($siteId, $theme);
        $this->seedStaff($siteId, $gallery['staff'], $theme);
        $this->seedPosts($siteId, $gallery['posts'], $name, $slug, $theme);
        $this->seedResearch($siteId, $short, $theme);
        $this->seedTimeline($siteId, $theme);
        $this->seedAlumni($siteId, $slug, $theme);
        $this->seedPages($siteId, $name, $short, $gallery['banner'], $theme);
        $this->seedSettings($siteId, $name, $short, $color, $logoPath, $site);
        $this->seedContentBlocks($siteId, $theme);

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            throw new RuntimeException('Échec de la transaction de seed pour ' . $slug);
        }

        $imageCount = count($gallery['heroes']) + count($gallery['staff']) + count($gallery['posts'])
            + ($gallery['banner'] ? 1 : 0) + ($gallery['logo'] ? 1 : 0);

        return [
            'site_id'  => $siteId,
            'slug'     => $slug,
            'images'   => $imageCount,
            'modules'  => ['home', 'heroes', 'stats', 'programmes', 'staff', 'posts', 'research', 'timeline', 'alumni', 'pages', 'settings'],
        ];
    }

    private function clearSiteContent(int $siteId): void
    {
        // Hard delete so unique (site_id, slug) indexes can be reused by the demo seed.
        // Does not touch uploads/sites/{slug}/faculty-deans/ files on disk.
        foreach ([
            'posts', 'staff', 'programmes', 'home_hero_slides', 'home_highlights',
            'laboratories', 'content_blocks', 'publications', 'research_projects',
            'timeline_items', 'alumni_profiles', 'testimonials', 'site_stats', 'pages',
            'home_content',
        ] as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->where('site_id', $siteId)->delete();
            }
        }

        if ($this->db->tableExists('settings')) {
            $keys = [
                'institution.faculty_name', 'institution.short_name', 'institution.university',
                'contact.address_line', 'contact.address_commune', 'contact.address_province', 'contact.address_country',
                'contact.phone', 'contact.email', 'contact.hours',
                'footer.text', 'footer.copyright',
                'assets.logo', 'seo.default_title', 'seo.default_description', 'seo.theme_color', 'seo.og_image',
                'social.links', 'home.hero_indicator_size',
            ];
            $this->db->table('settings')->where('site_id', $siteId)->whereIn('key', $keys)->delete();
        }

        if ($this->db->tableExists('content_translations')) {
            $this->db->table('content_translations')->where('site_id', $siteId)->delete();
        }
    }

    /**
     * @param list<string> $heroes
     * @param array<string, mixed> $theme
     */
    private function seedHeroes(int $siteId, array $heroes, array $theme): void
    {
        $captions = $theme['hero_captions'];
        foreach ($heroes as $i => $path) {
            $cap = $captions[$i] ?? ['badge' => 'Campus', 'title' => $theme['tagline'], 'text' => $theme['about']];
            $this->db->table('home_hero_slides')->insert([
                'site_id'              => $siteId,
                'image_path'           => $path,
                'alt_text'             => $cap['title'],
                'badge'                => $cap['badge'],
                'title'                => $cap['title'],
                'text'                 => $cap['text'],
                'primary_cta_target'   => $i === 0 ? 'programmes' : ($i === 1 ? 'news' : 'contact'),
                'primary_cta_label'    => $i === 0 ? 'Nos formations' : ($i === 1 ? 'Actualités' : 'Nous contacter'),
                'primary_cta_url'      => null,
                'secondary_cta_target' => 'none',
                'secondary_cta_label'  => null,
                'secondary_cta_url'     => null,
                'display_order'        => $i + 1,
                'is_published'         => 1,
                'created_at'           => $this->now(),
                'updated_at'           => $this->now(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function seedHome(int $siteId, string $name, string $short, array $theme, string $heroPath): void
    {
        $this->db->table('home_content')->insert([
            'site_id'                  => $siteId,
            'singleton_key'            => 1,
            'hero_media_type'          => 'image',
            'hero_media_path'          => $heroPath,
            'hero_badge'               => $short,
            'hero_title'               => $name,
            'hero_text'                => $theme['tagline'],
            'hero_primary_label'       => 'Découvrir les formations',
            'hero_primary_url'         => '/formations',
            'hero_secondary_label'     => 'Nous contacter',
            'hero_secondary_url'       => '/contact',
            'about_label'              => 'Présentation',
            'about_title'              => 'Une faculté engagée',
            'about_body'               => $theme['about'],
            'about_button_label'       => 'En savoir plus',
            'about_button_url'         => '/faculte',
            'research_label'           => 'Recherche',
            'research_title'           => $theme['research_title'],
            'research_body'            => $theme['research_body'],
            'research_button_label'    => 'Laboratoires',
            'research_button_url'      => '/recherche',
            'programmes_label'         => 'Formations',
            'programmes_title'         => 'Parcours académiques',
            'programmes_text'          => $theme['programmes_intro'],
            'programmes_button_label'  => 'Toutes les formations',
            'programmes_button_url'    => '/formations',
            'posts_label'              => 'Actualités',
            'posts_title'              => 'Vie de la faculté',
            'posts_text'               => 'Retrouvez les dernières nouvelles, événements et opportunités.',
            'posts_button_label'       => 'Voir tout',
            'posts_button_url'         => '/actualites',
            'seo_title'                => $short . ' | Université du Burundi',
            'seo_description'          => $theme['tagline'],
            'updated_by'               => null,
            'created_at'               => $this->now(),
            'updated_at'               => $this->now(),
        ]);

        foreach ($theme['highlights'] as $i => $row) {
            $this->db->table('home_highlights')->insert([
                'site_id'       => $siteId,
                'icon'          => $row['icon'],
                'title'         => $row['title'],
                'description'   => $row['description'],
                'display_order' => $i + 1,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function seedStats(int $siteId, array $theme): void
    {
        foreach ($theme['stats'] as $i => $row) {
            $this->db->table('site_stats')->insert([
                'site_id'       => $siteId,
                'section'       => $row['section'],
                'label'         => $row['label'],
                'value'         => $row['value'],
                'suffix'        => $row['suffix'],
                'display_order' => $i + 1,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function seedProgrammes(int $siteId, array $theme): void
    {
        foreach ($theme['programmes'] as $i => $row) {
            $this->db->table('programmes')->insert([
                'site_id'              => $siteId,
                'level'                => $row['level'],
                'title'                => $row['title'],
                'slug'                 => $row['slug'],
                'duration'             => $row['duration'],
                'summary'              => $row['summary'],
                'description'          => $row['description'],
                'admission_conditions' => $row['admission'],
                'career_outcomes'      => json_encode($row['careers'], JSON_UNESCAPED_UNICODE),
                'display_order'        => $i + 1,
                'featured_on_home'     => $i < 6 ? 1 : 0,
                'home_order'           => $i < 6 ? $i + 1 : null,
                'is_published'         => 1,
                'created_at'           => $this->now(),
                'updated_at'           => $this->now(),
            ]);
        }
    }

    /**
     * @param list<string> $photos
     * @param array<string, mixed> $theme
     */
    private function seedStaff(int $siteId, array $photos, array $theme): void
    {
        foreach ($theme['staff'] as $i => $row) {
            $this->db->table('staff')->insert([
                'site_id'          => $siteId,
                'category'         => $row['category'],
                'name'             => $row['name'],
                'slug'             => $row['slug'],
                'photo'            => $photos[$i] ?? null,
                'grade'            => $row['grade'],
                'specialty'        => $row['specialty'],
                'role'             => $row['role'],
                'email'            => $row['email'],
                'biography'        => $row['bio'],
                'display_order'    => $i + 1,
                'featured_on_home' => $i < 4 ? 1 : 0,
                'home_order'       => $i < 4 ? $i + 1 : null,
                'is_published'     => 1,
                'created_at'       => $this->now(),
                'updated_at'       => $this->now(),
            ]);
        }
    }

    /**
     * @param list<string> $covers
     * @param array<string, mixed> $theme
     */
    private function seedPosts(int $siteId, array $covers, string $name, string $slug, array $theme): void
    {
        foreach ($theme['posts'] as $i => $row) {
            $published = date('Y-m-d H:i:s', strtotime('-' . ($i * 3) . ' days'));
            $this->db->table('posts')->insert([
                'site_id'         => $siteId,
                'type'            => $row['type'],
                'title'           => $row['title'],
                'slug'            => $row['slug'],
                'excerpt'         => $row['excerpt'],
                'body'            => $row['body'],
                'cover_image'     => $covers[$i] ?? null,
                'status'          => 'published',
                'published_at'    => $published,
                'featured'        => $i < 3 ? 1 : 0,
                'home_order'      => $i < 3 ? $i + 1 : null,
                'event_starts_at' => $row['type'] === 'event' ? date('Y-m-d H:i:s', strtotime('+' . (7 + $i) . ' days')) : null,
                'event_ends_at'   => $row['type'] === 'event' ? date('Y-m-d H:i:s', strtotime('+' . (8 + $i) . ' days')) : null,
                'event_location'  => $row['type'] === 'event' ? ($row['location'] ?? $name) : null,
                'created_at'      => $this->now(),
                'updated_at'      => $this->now(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function seedResearch(int $siteId, string $short, array $theme): void
    {
        foreach ($theme['labs'] as $i => $row) {
            $this->db->table('laboratories')->insert([
                'site_id'          => $siteId,
                'abbreviation'     => $row['abbr'],
                'name'             => $row['name'],
                'slug'             => $row['slug'],
                'icon'             => $row['icon'],
                'description'      => $row['description'],
                'themes'           => json_encode($row['themes'], JSON_UNESCAPED_UNICODE),
                'researcher_count' => $row['researchers'],
                'display_order'    => $i + 1,
                'featured_on_home' => 1,
                'home_order'       => $i + 1,
                'is_published'     => 1,
                'created_at'       => $this->now(),
                'updated_at'       => $this->now(),
            ]);
        }

        foreach ($theme['publications'] as $i => $row) {
            $this->db->table('publications')->insert([
                'site_id'       => $siteId,
                'year'          => $row['year'],
                'title'         => $row['title'],
                'authors'       => $row['authors'],
                'journal'       => $row['journal'],
                'url'           => null,
                'display_order' => $i + 1,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
        }

        foreach ($theme['projects'] as $i => $row) {
            $this->db->table('research_projects')->insert([
                'site_id'       => $siteId,
                'code'          => strtoupper($short) . '-P' . ($i + 1),
                'title'         => $row['title'],
                'description'   => $row['description'],
                'funder'        => $row['funder'],
                'period_start'  => $row['start'],
                'period_end'    => $row['end'],
                'icon'          => 'bi-lightbulb',
                'display_order' => $i + 1,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function seedTimeline(int $siteId, array $theme): void
    {
        foreach ($theme['timeline'] as $i => $row) {
            $this->db->table('timeline_items')->insert([
                'site_id'       => $siteId,
                'year'          => $row['year'],
                'title'         => $row['title'],
                'description'   => $row['description'],
                'display_order' => $i + 1,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function seedAlumni(int $siteId, string $slug, array $theme): void
    {
        foreach ($theme['alumni'] as $i => $row) {
            $this->db->table('alumni_profiles')->insert([
                'site_id'       => $siteId,
                'name'          => $row['name'],
                'slug'          => $row['slug'],
                'role'          => $row['role'],
                'organization'  => $row['organization'],
                'promotion'     => (string) $row['year'],
                'biography'     => $row['bio'],
                'photo'         => null,
                'display_order' => $i + 1,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
        }

        foreach ($theme['testimonials'] as $i => $row) {
            $this->db->table('testimonials')->insert([
                'site_id'       => $siteId,
                'person_name'   => $row['name'],
                'promotion'     => $row['promotion'],
                'quote'         => $row['quote'],
                'display_order' => $i + 1,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function seedPages(int $siteId, string $name, string $short, ?string $banner, array $theme): void
    {
        $pages = [
            'faculty' => [
                'title' => 'Présentation de la faculté',
                'content' => [
                    'intro' => $theme['about'],
                    'dean'  => [
                        'name'    => $theme['dean_name'],
                        'title'   => 'Doyen',
                        'message' => $theme['dean_message'],
                        'photo'   => null,
                    ],
                ],
            ],
            'posts' => [
                'title' => 'Actualités et événements',
                'content' => ['banner_subtitle' => 'Suivez la vie de ' . $short],
            ],
            'staff' => [
                'title' => 'Corps enseignant et personnel',
                'content' => [
                    'banner_subtitle' => 'Une équipe engagée au service de la formation',
                    'banner_image'    => $banner,
                ],
            ],
            'contact' => [
                'title' => 'Contact',
                'content' => [
                    'intro' => 'Écrivez-nous pour toute demande d’information sur les formations ou la recherche.',
                ],
            ],
        ];

        foreach ($pages as $key => $page) {
            $this->db->table('pages')->insert([
                'site_id'         => $siteId,
                'key'             => $key,
                'title'           => $page['title'],
                'slug'            => $key,
                'seo_title'       => $page['title'] . ' | ' . $short,
                'seo_description' => $theme['tagline'],
                'content'         => json_encode($page['content'], JSON_UNESCAPED_UNICODE),
                'is_published'    => 1,
                'created_at'      => $this->now(),
                'updated_at'      => $this->now(),
            ]);
        }
    }

    /**
     * @param object|array<string, mixed> $site
     */
    private function seedSettings(int $siteId, string $name, string $short, string $color, ?string $logo, $site): void
    {
        $logoPath = $logo ?: 'assets/images/logo-placeholder.png';
        $rows = [
            ['institution.faculty_name', $name, 'string', 'institution'],
            ['institution.short_name', $short, 'string', 'institution'],
            ['institution.university', 'Université du Burundi', 'string', 'institution'],
            ['contact.address_line', 'Avenue de l’Université', 'string', 'contact'],
            ['contact.address_commune', 'Mukaza', 'string', 'contact'],
            ['contact.address_province', 'Bujumbura Mairie', 'string', 'contact'],
            ['contact.address_country', 'Burundi', 'string', 'contact'],
            ['contact.phone', '+257 22 22 00 00', 'string', 'contact'],
            ['contact.email', strtolower($short) . '@ub.edu.bi', 'email', 'contact'],
            ['contact.hours', 'Lun–Ven 8h–16h', 'string', 'contact'],
            ['footer.text', $name . ' — Université du Burundi. Contenu de démonstration locale.', 'text', 'footer'],
            ['footer.copyright', '© ' . date('Y') . ' ' . $name, 'string', 'footer'],
            ['assets.logo', $logoPath, 'path', 'assets'],
            ['seo.default_title', $short . ' | Université du Burundi', 'string', 'seo'],
            ['seo.default_description', 'Site officiel de démonstration — ' . $name, 'text', 'seo'],
            ['seo.theme_color', '#0D9B49', 'color', 'seo'],
            ['seo.og_image', $logoPath, 'path', 'seo'],
            ['social.links', json_encode(['facebook' => 'https://facebook.com', 'twitter' => 'https://x.com'], JSON_UNESCAPED_UNICODE), 'json', 'social'],
            ['home.hero_indicator_size', '0.75', 'string', 'home'],
        ];

        foreach ($rows as [$key, $value, $type, $context]) {
            $this->db->table('settings')->insert([
                'site_id' => $siteId,
                'class'   => 'App\\Settings\\Site',
                'key'     => $key,
                'value'   => $value,
                'type'    => $type,
                'context' => $context,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function seedContentBlocks(int $siteId, array $theme): void
    {
        $blocks = [
            ['home', 'custom_text', 'Mot d’accueil', $theme['about'], 1],
            ['home', 'contact_cta', 'Rejoignez-nous', 'Contactez le secrétariat pour vos démarches d’admission.', 2],
        ];
        foreach ($blocks as [$pageKey, $type, $title, $content, $order]) {
            $this->db->table('content_blocks')->insert([
                'site_id'       => $siteId,
                'page_key'      => $pageKey,
                'type'          => $type,
                'title'         => $title,
                'content'       => $content,
                'settings'      => json_encode(new \stdClass(), JSON_FORCE_OBJECT),
                'display_order' => $order,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function themeForSlug(string $slug): array
    {
        $catalog = [
            'fseg' => $this->themeEconomics(),
            'fsi'  => $this->themeSciences(),
            'med'  => $this->themeMedicine(),
            'fabi' => $this->themeAgro(),
            'flsh' => $this->themeHumanities(),
        ];

        return $catalog[$slug] ?? $this->themeEconomics();
    }

    /** @return array<string, mixed> */
    private function themeEconomics(): array
    {
        return $this->buildTheme(
            'Former les décideurs économiques de demain',
            'La Faculté des Sciences Économiques et de Gestion offre des cursus solides en économie, finance, management et entrepreneuriat, ancrés dans les réalités du Burundi et de la région des Grands Lacs.',
            'Recherche en économie et management',
            'Nos équipes travaillent sur le développement local, la finance inclusive, la gouvernance des entreprises et l’analyse des politiques publiques.',
            'Du premier cycle au doctorat, des parcours professionnalisants et académiques.',
            'Pr. Jeanne Ndayishimiye',
            'Chères étudiantes, chers étudiants, notre faculté vous accompagne vers l’excellence académique et l’engagement citoyen.',
            $this->programmeSet('eco'),
            $this->staffSet('eco'),
            $this->postSet('eco'),
            $this->labSet('eco'),
        );
    }

    /** @return array<string, mixed> */
    private function themeSciences(): array
    {
        return $this->buildTheme(
            'Sciences, technologies et ingénierie au service du pays',
            'La Faculté des Sciences et Ingénierie forme des scientifiques et ingénieurs capables d’innover dans les domaines des mathématiques, de l’informatique, de la physique et du génie.',
            'Laboratoires scientifiques',
            'Les projets portent sur les énergies renouvelables, les systèmes d’information, la modélisation et les technologies numériques.',
            'Parcours scientifiques et technologiques pour répondre aux besoins du marché.',
            'Pr. Eric Habonimana',
            'Bienvenue à la FSI : ici, la curiosité scientifique rencontre la rigueur et l’innovation.',
            $this->programmeSet('sci'),
            $this->staffSet('sci'),
            $this->postSet('sci'),
            $this->labSet('sci'),
        );
    }

    /** @return array<string, mixed> */
    private function themeMedicine(): array
    {
        return $this->buildTheme(
            'Former des professionnels de santé compétents et humains',
            'La Faculté de Médecine prépare médecins et professionnels de santé à servir les communautés avec excellence clinique et éthique.',
            'Recherche biomédicale et santé publique',
            'Axes prioritaires : maladies infectieuses, santé maternelle et infantile, épidémiologie et systèmes de santé.',
            'Formations médicales et paramédicales ancrées dans la pratique hospitalière.',
            'Pr. Dr. Claudine Niyonzima',
            'Notre mission est de former des soignants dévoués, capables de relever les défis sanitaires du Burundi.',
            $this->programmeSet('med'),
            $this->staffSet('med'),
            $this->postSet('med'),
            $this->labSet('med'),
        );
    }

    /** @return array<string, mixed> */
    private function themeAgro(): array
    {
        return $this->buildTheme(
            'Agriculture, environnement et développement rural',
            'La FABI forme des experts capables de moderniser l’agriculture, protéger les ressources naturelles et soutenir les communautés rurales.',
            'Recherche agronomique',
            'Nos travaux portent sur les sols, les cultures vivrières, l’agroécologie et la gestion durable des bassins versants.',
            'Parcours en sciences agronomiques, environnement et productions végétales.',
            'Pr. Ir. Sophie Ndayizeye',
            'Ensemble, cultivons des solutions durables pour la sécurité alimentaire.',
            $this->programmeSet('agro'),
            $this->staffSet('agro'),
            $this->postSet('agro'),
            $this->labSet('agro'),
        );
    }

    /** @return array<string, mixed> */
    private function themeHumanities(): array
    {
        return $this->buildTheme(
            'Lettres, langues et sciences humaines',
            'La FLSH cultive l’esprit critique, les langues et la compréhension des sociétés à travers l’histoire, la philosophie, la littérature et la communication.',
            'Recherche en sciences humaines',
            'Projets sur le patrimoine, les langues nationales, l’éducation et les dynamiques sociales contemporaines.',
            'Formations ouvertes sur la culture, l’enseignement et les métiers de la communication.',
            'Pr. Diane Nkurunziza',
            'Les humanités éclairent le présent et préparent des citoyens responsables.',
            $this->programmeSet('hum'),
            $this->staffSet('hum'),
            $this->postSet('hum'),
            $this->labSet('hum'),
        );
    }

    /**
     * @param list<array<string, mixed>> $programmes
     * @param list<array<string, mixed>> $staff
     * @param list<array<string, mixed>> $posts
     * @param list<array<string, mixed>> $labs
     * @return array<string, mixed>
     */
    private function buildTheme(
        string $tagline,
        string $about,
        string $researchTitle,
        string $researchBody,
        string $programmesIntro,
        string $deanName,
        string $deanMessage,
        array $programmes,
        array $staff,
        array $posts,
        array $labs,
    ): array {
        return [
            'tagline' => $tagline,
            'about' => $about,
            'research_title' => $researchTitle,
            'research_body' => $researchBody,
            'programmes_intro' => $programmesIntro,
            'dean_name' => $deanName,
            'dean_message' => $deanMessage,
            'hero_captions' => [
                ['badge' => 'Accueil', 'title' => $tagline, 'text' => $about],
                ['badge' => 'Formations', 'title' => 'Des parcours d’excellence', 'text' => $programmesIntro],
                ['badge' => 'Recherche', 'title' => $researchTitle, 'text' => $researchBody],
                ['badge' => 'Campus', 'title' => 'Une vie étudiante riche', 'text' => 'Associations, conférences, stages et accompagnement pédagogique.'],
                ['badge' => 'Communauté', 'title' => 'Ouverts sur la société', 'text' => 'Partenariats académiques, alumni et services à la communauté.'],
            ],
            'highlights' => [
                ['icon' => 'bi-mortarboard', 'title' => 'Formation', 'description' => 'Cursus structurés et encadrement de proximité.'],
                ['icon' => 'bi-search', 'title' => 'Recherche', 'description' => 'Laboratoires actifs et projets collaboratifs.'],
                ['icon' => 'bi-people', 'title' => 'Communauté', 'description' => 'Étudiants, enseignants et partenaires engagés.'],
            ],
            'stats' => [
                ['section' => 'home_main', 'label' => 'Étudiants', 'value' => 1850, 'suffix' => '+'],
                ['section' => 'home_main', 'label' => 'Programmes', 'value' => 10, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Enseignants', 'value' => 68, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Taux de réussite', 'value' => 82, 'suffix' => '%'],
                ['section' => 'home_research', 'label' => 'Publications', 'value' => 120, 'suffix' => '+'],
                ['section' => 'home_research', 'label' => 'Projets actifs', 'value' => 14, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Laboratoires', 'value' => 3, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Diplômés', 'value' => 4200, 'suffix' => '+'],
            ],
            'programmes' => $programmes,
            'staff' => $staff,
            'posts' => $posts,
            'labs' => $labs,
            'publications' => $this->publicationSet(),
            'projects' => $this->projectSet(),
            'timeline' => $this->timelineSet(),
            'alumni' => $this->alumniSet(),
            'testimonials' => $this->testimonialSet(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function programmeSet(string $key): array
    {
        $sets = [
            'eco' => [
                ['Licence en Économie', 'licence-economie'],
                ['Licence en Gestion', 'licence-gestion'],
                ['Licence en Finance', 'licence-finance'],
                ['Master en Économie du développement', 'master-economie-developpement'],
                ['Master en Management', 'master-management'],
                ['Master en Finance d’entreprise', 'master-finance-entreprise'],
                ['Master en Entrepreneuriat', 'master-entrepreneuriat'],
                ['Doctorat en Économie', 'doctorat-economie'],
                ['Doctorat en Sciences de gestion', 'doctorat-gestion'],
                ['Certificat en Comptabilité', 'certificat-comptabilite'],
            ],
            'sci' => [
                ['Licence en Mathématiques', 'licence-mathematiques'],
                ['Licence en Informatique', 'licence-informatique'],
                ['Licence en Physique', 'licence-physique'],
                ['Licence en Chimie', 'licence-chimie'],
                ['Master en Génie logiciel', 'master-genie-logiciel'],
                ['Master en Data Science', 'master-data-science'],
                ['Master en Énergies renouvelables', 'master-energies'],
                ['Doctorat en Informatique', 'doctorat-informatique'],
                ['Doctorat en Physique', 'doctorat-physique'],
                ['Master en Systèmes embarqués', 'master-systemes-embarques'],
            ],
            'med' => [
                ['Médecine générale', 'medecine-generale'],
                ['Sciences infirmières', 'sciences-infirmieres'],
                ['Santé publique', 'sante-publique'],
                ['Pharmacie', 'pharmacie'],
                ['Laboratoire médical', 'laboratoire-medical'],
                ['Master en Epidémiologie', 'master-epidemiologie'],
                ['Master en Santé communautaire', 'master-sante-communautaire'],
                ['Spécialisation en Pédiatrie', 'specialisation-pediatrie'],
                ['Spécialisation en Chirurgie', 'specialisation-chirurgie'],
                ['Doctorat en Sciences médicales', 'doctorat-sciences-medicales'],
            ],
            'agro' => [
                ['Licence en Agronomie', 'licence-agronomie'],
                ['Licence en Environnement', 'licence-environnement'],
                ['Licence en Productions végétales', 'licence-productions-vegetales'],
                ['Licence en Élevage', 'licence-elevage'],
                ['Master en Agroécologie', 'master-agroecologie'],
                ['Master en Gestion des ressources naturelles', 'master-ressources-naturelles'],
                ['Master en Développement rural', 'master-developpement-rural'],
                ['Doctorat en Sciences agronomiques', 'doctorat-agronomie'],
                ['Master en Sols et fertilité', 'master-sols'],
                ['Certificat en Irrigation', 'certificat-irrigation'],
            ],
            'hum' => [
                ['Licence en Lettres modernes', 'licence-lettres'],
                ['Licence en Histoire', 'licence-histoire'],
                ['Licence en Philosophie', 'licence-philosophie'],
                ['Licence en Langues', 'licence-langues'],
                ['Licence en Communication', 'licence-communication'],
                ['Master en Littérature', 'master-litterature'],
                ['Master en Sciences de l’éducation', 'master-education'],
                ['Master en Anthropologie', 'master-anthropologie'],
                ['Doctorat en Histoire', 'doctorat-histoire'],
                ['Doctorat en Lettres', 'doctorat-lettres'],
            ],
        ];

        $levels = ['licence', 'licence', 'licence', 'licence', 'master', 'master', 'master', 'doctorat', 'doctorat', 'master'];
        $out = [];
        foreach ($sets[$key] as $i => [$title, $slug]) {
            $level = $levels[$i] ?? 'licence';
            $out[] = [
                'level' => $level,
                'title' => $title,
                'slug' => $slug,
                'duration' => $level === 'doctorat' ? '3 à 5 ans' : ($level === 'master' ? '2 ans' : '3 ans'),
                'summary' => 'Parcours structuré préparant aux métiers et à la poursuite d’études dans le domaine.',
                'description' => "Le programme « {$title} » combine enseignements fondamentaux, travaux pratiques et stages. Les étudiants développent des compétences analytiques, professionnelles et citoyennes.",
                'admission' => 'Réussite du niveau précédent ou équivalent reconnu, dossier académique et, le cas échéant, entretien.',
                'careers' => ['Cadre sectoriel', 'Chercheur / enseignant', 'Consultant', 'Entrepreneur'],
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function staffSet(string $key): array
    {
        $names = [
            ['Dr. Alice N.', 'enseignant', 'Professeur', 'Recherche appliquée'],
            ['Dr. Bruno K.', 'enseignant', 'Maître de conférences', 'Pédagogie universitaire'],
            ['Dr. Clarisse M.', 'enseignant', 'Chargée de cours', 'Encadrement de stages'],
            ['Pr. Daniel H.', 'enseignant', 'Professeur ordinaire', 'Direction de laboratoire'],
            ['Dr. Emma S.', 'enseignant', 'Maître-assistant', 'Innovation pédagogique'],
            ['M. Fabrice T.', 'administratif', null, null],
            ['Mme Grace U.', 'administratif', null, null],
            ['Dr. Henri V.', 'enseignant', 'Professeur', 'Coopération internationale'],
            ['Dr. Irène W.', 'enseignant', 'Chargée de cours', 'Tutorat'],
            ['Dr. Jean X.', 'enseignant', 'Maître de conférences', 'Publications'],
            ['M. Kevin Y.', 'administratif', null, null],
            ['Dr. Léa Z.', 'enseignant', 'Assistante', 'Projets étudiants'],
        ];

        $roles = [
            'enseignant' => 'Enseignant-chercheur',
            'administratif' => 'Secrétariat académique',
        ];

        $out = [];
        foreach ($names as $i => [$name, $cat, $grade, $spec]) {
            $slug = 'membre-' . ($i + 1) . '-' . $key;
            $out[] = [
                'category' => $cat,
                'name' => $name,
                'slug' => $slug,
                'grade' => $grade,
                'specialty' => $spec,
                'role' => $roles[$cat],
                'email' => 'membre' . ($i + 1) . '@ub.edu.bi',
                'bio' => 'Profil de démonstration : engagement pédagogique et contribution à la vie de la faculté.',
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function postSet(string $key): array
    {
        $items = [];
        for ($i = 1; $i <= 12; $i++) {
            $isEvent = $i % 3 === 0;
            $items[] = [
                'type' => $isEvent ? 'event' : 'news',
                'title' => $isEvent
                    ? ('Conférence ouverte #' . $i . ' — ' . strtoupper($key))
                    : ('Nouvelle académique #' . $i . ' — ' . strtoupper($key)),
                'slug' => ($isEvent ? 'evenement' : 'actualite') . '-' . $i . '-' . $key,
                'excerpt' => 'Résumé de démonstration pour illustrer le flux d’actualités de la faculté.',
                'body' => "<p>Contenu démo détaillé pour l’entrée #{$i}. Cette publication montre l’affichage public, les listes et la mise en avant sur l’accueil.</p><p>Elle peut être remplacée librement depuis l’administration.</p>",
                'location' => 'Amphithéâtre principal',
            ];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function labSet(string $key): array
    {
        return [
            [
                'abbr' => strtoupper($key) . '-L1',
                'name' => 'Laboratoire principal ' . strtoupper($key),
                'slug' => 'laboratoire-principal-' . $key,
                'icon' => 'bi-diagram-3',
                'description' => 'Unité de recherche phare de la faculté, ouverte aux collaborations nationales et régionales.',
                'themes' => ['Thème A', 'Thème B', 'Innovation'],
                'researchers' => 12,
            ],
            [
                'abbr' => strtoupper($key) . '-L2',
                'name' => 'Laboratoire appliqué ' . strtoupper($key),
                'slug' => 'laboratoire-applique-' . $key,
                'icon' => 'bi-cpu',
                'description' => 'Travaux tournés vers les besoins des communautés et des partenaires socio-économiques.',
                'themes' => ['Applications', 'Terrain'],
                'researchers' => 8,
            ],
            [
                'abbr' => strtoupper($key) . '-L3',
                'name' => 'Laboratoire pédagogique ' . strtoupper($key),
                'slug' => 'laboratoire-pedagogique-' . $key,
                'icon' => 'bi-book',
                'description' => 'Soutien à l’enseignement expérimental et à la formation continue.',
                'themes' => ['Pédagogie', 'Formation'],
                'researchers' => 5,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function publicationSet(): array
    {
        $out = [];
        for ($i = 1; $i <= 8; $i++) {
            $out[] = [
                'year' => 2019 + ($i % 6),
                'title' => 'Publication scientifique de démonstration n°' . $i,
                'authors' => 'Équipe facultaire et collaborateurs',
                'journal' => 'Revue académique régionale',
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function projectSet(): array
    {
        return [
            ['title' => 'Projet d’appui pédagogique', 'description' => 'Renforcement des capacités numériques des enseignants.', 'funder' => 'Partenaire académique', 'start' => 2023, 'end' => 2026],
            ['title' => 'Observatoire sectoriel', 'description' => 'Collecte et analyse de données pour éclairer les politiques.', 'funder' => 'Coopération bilatérale', 'start' => 2022, 'end' => 2025],
            ['title' => 'Innovation locale', 'description' => 'Prototypes et services issus des laboratoires étudiants.', 'funder' => 'Fonds interne', 'start' => 2024, 'end' => 2027],
            ['title' => 'Réseau alumni-recherche', 'description' => 'Mobilisation des diplômés autour de projets collaboratifs.', 'funder' => 'Fondation universitaire', 'start' => 2021, 'end' => 2024],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function timelineSet(): array
    {
        return [
            ['year' => 1964, 'title' => 'Origines', 'description' => 'Premières formations rattachées à l’université.'],
            ['year' => 1985, 'title' => 'Structuration', 'description' => 'Organisation en départements et programmes distincts.'],
            ['year' => 2000, 'title' => 'Modernisation', 'description' => 'Renforcement des laboratoires et de la bibliothèque.'],
            ['year' => 2012, 'title' => 'LMD', 'description' => 'Alignement sur le système Licence-Master-Doctorat.'],
            ['year' => 2019, 'title' => 'Numérique', 'description' => 'Déploiement d’outils pédagogiques en ligne.'],
            ['year' => 2024, 'title' => 'Ouverture', 'description' => 'Nouveaux partenariats et offre de formation enrichie.'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function alumniSet(): array
    {
        $out = [];
        for ($i = 1; $i <= 5; $i++) {
            $out[] = [
                'name' => 'Alumni Démo ' . $i,
                'slug' => 'alumni-demo-' . $i,
                'role' => 'Cadre / entrepreneur',
                'organization' => 'Organisation partenaire',
                'year' => 2015 + $i,
                'bio' => 'Parcours illustratif d’un diplômé engagé dans son secteur.',
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function testimonialSet(): array
    {
        $out = [];
        for ($i = 1; $i <= 5; $i++) {
            $out[] = [
                'name' => 'Témoin ' . $i,
                'promotion' => 'Promotion ' . (2016 + $i),
                'quote' => 'La faculté m’a donné des bases solides et un réseau professionnel durable.',
            ];
        }

        return $out;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
