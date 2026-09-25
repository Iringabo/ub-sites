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
            'primary_color'    => $color,
            'secondary_color'  => $secondary,
            'logo'             => $logoPath,
            'enabled_sections' => json_encode([
                'hero',
                'statistics',
                'about',
                'programmes_preview',
                'research_labs',
                'news_preview',
                'dean_message',
                'staff_preview',
                'custom_text',
                'contact_cta',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'       => $this->now(),
        ]);

        $this->seedHome($siteId, $name, $short, $theme, $heroPath);
        $this->seedHeroes($siteId, $gallery['heroes'], $theme);
        $this->seedStats($siteId, $theme);
        $this->seedProgrammes($siteId, $theme);
        $this->seedStaff($siteId, $gallery['staff'], $theme, $slug, $name, $color);
        $this->seedPosts($siteId, $gallery['posts'], $name, $slug, $theme);
        $this->seedResearch($siteId, $short, $theme);
        $this->seedTimeline($siteId, $theme);
        $this->seedAlumni($siteId, $slug, $theme, $gallery['alumni'] ?? [], $name, $color);
        $this->seedPages($siteId, $name, $short, $gallery['banner'], $theme);
        $this->seedSettings($siteId, $name, $short, $color, $logoPath, $site);
        $this->seedContentBlocks($siteId, $theme);

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            throw new RuntimeException('Échec de la transaction de seed pour ' . $slug);
        }

        $imageCount = count($gallery['heroes']) + count($gallery['staff']) + count($gallery['posts'])
            + count($gallery['alumni'] ?? [])
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
            'about_title'              => $theme['about_title'] ?? 'Une faculté engagée',
            'about_body'               => $theme['about'],
            'about_button_label'       => 'En savoir plus',
            'about_button_url'         => '/faculte',
            'research_label'           => 'Recherche',
            'research_title'           => $theme['research_title'],
            'research_body'            => $theme['research_body'],
            'research_button_label'    => 'Laboratoires',
            'research_button_url'      => '/recherche',
            'programmes_label'         => 'Formations',
            'programmes_title'         => $theme['programmes_title'] ?? 'Parcours académiques',
            'programmes_text'          => $theme['programmes_intro'],
            'programmes_button_label'  => 'Toutes les formations',
            'programmes_button_url'    => '/formations',
            'posts_label'              => 'Actualités',
            'posts_title'              => $theme['posts_title'] ?? 'Vie de la faculté',
            'posts_text'               => $theme['posts_intro'] ?? 'Retrouvez les dernières nouvelles, événements et opportunités.',
            'posts_button_label'       => 'Voir tout',
            'posts_button_url'         => '/actualites',
            'seo_title'                => $short . ' | Université du Burundi',
            'seo_description'          => $theme['seo_description'] ?? $theme['tagline'],
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
    private function seedStaff(int $siteId, array $photos, array $theme, string $slug, string $facultyLabel, string $color): void
    {
        foreach ($theme['staff'] as $i => $row) {
            $photo = $this->media->portraitJpeg(
                $slug,
                'staff',
                $facultyLabel,
                (string) $row['name'],
                $this->demoPortraitColor($color, $i),
            ) ?? ($photos[$i] ?? null);

            $this->db->table('staff')->insert([
                'site_id'          => $siteId,
                'category'         => $row['category'],
                'name'             => $row['name'],
                'slug'             => $row['slug'],
                'photo'            => $photo,
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
            $cover = $covers === [] ? null : ($covers[$i % count($covers)] ?? null);
            $this->db->table('posts')->insert([
                'site_id'         => $siteId,
                'type'            => $row['type'],
                'title'           => $row['title'],
                'slug'            => $row['slug'],
                'excerpt'         => $row['excerpt'],
                'body'            => $row['body'],
                'cover_image'     => $cover,
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
     * @param list<string> $photos
     * @param array<string, mixed> $theme
     */
    private function seedAlumni(int $siteId, string $slug, array $theme, array $photos, string $facultyLabel, string $color): void
    {
        foreach ($theme['alumni'] as $i => $row) {
            $photo = $this->media->portraitJpeg(
                $slug,
                'alumni',
                $facultyLabel,
                (string) $row['name'],
                $this->demoPortraitColor($color, $i + 2),
            ) ?? ($photos[$i] ?? null);

            $this->db->table('alumni_profiles')->insert([
                'site_id'       => $siteId,
                'name'          => $row['name'],
                'slug'          => $row['slug'],
                'role'          => $row['role'],
                'organization'  => $row['organization'],
                'promotion'     => (string) $row['year'],
                'biography'     => $row['bio'],
                'photo'         => $photo,
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
                    'banner_subtitle' => 'Présentation de ' . $short,
                    'banner_image'    => $banner,
                    'intro' => $theme['about'],
                    'mission' => $theme['mission'] ?? null,
                    'vision'  => $theme['vision'] ?? null,
                    'values'  => $theme['values'] ?? [],
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
                'content' => [
                    'banner_subtitle' => $theme['posts_banner'] ?? ('Suivez la vie de ' . $short),
                    'banner_image'    => $banner,
                ],
            ],
            'research' => [
                'title' => 'Recherche',
                'content' => [
                    'banner_subtitle' => $theme['research_banner'] ?? 'Laboratoires, publications et projets',
                    'banner_image'    => $banner,
                    'labs_label'           => 'Laboratoires',
                    'labs_title'           => $theme['research_title'],
                    'labs_text'            => $theme['research_body'],
                    'publications_label'   => 'Publications',
                    'publications_title'   => $theme['publications_title'] ?? 'Travaux récents',
                    'publications_text'    => $theme['publications_intro'] ?? ('Sélection de publications de ' . $short),
                    'projects_label'       => 'Projets',
                    'projects_title'       => $theme['projects_title'] ?? 'Projets en cours',
                ],
            ],
            'staff' => [
                'title' => 'Corps enseignant et personnel',
                'content' => [
                    'banner_subtitle' => $theme['staff_banner'] ?? 'Une équipe engagée au service de la formation',
                    'banner_image'    => $banner,
                ],
            ],
            'formations' => [
                'title' => 'Formations',
                'content' => [
                    'banner_subtitle' => $theme['formations_banner'] ?? 'Parcours de la licence au doctorat',
                    'banner_image'    => $banner,
                ],
            ],
            'alumni' => [
                'title' => 'Alumni',
                'content' => [
                    'banner_subtitle' => $theme['alumni_banner'] ?? 'Le réseau des diplômés',
                    'banner_image'    => $banner,
                    'intro_label' => 'Communauté',
                    'intro_title' => 'Les alumni de ' . $short,
                    'intro_paragraphs' => $theme['alumni_intro'] ?? [
                        'Les diplômés de ' . $name . ' forment un réseau actif dans l’administration, le secteur privé, la recherche et l’entrepreneuriat.',
                        'Cette page présente quelques parcours illustratifs. Les fiches peuvent être enrichies depuis l’administration.',
                    ],
                    'intro_button_label' => 'Rejoindre le réseau',
                    'intro_button_url' => '/contact',
                    'profiles_label' => 'Profils',
                    'profiles_title' => 'Parcours de diplômés',
                    'profiles_text' => $theme['alumni_profiles_text'] ?? 'Une sélection de profils pour illustrer la diversité des débouchés.',
                    'testimonials_label' => 'Témoignages',
                    'testimonials_title' => 'Ce que la faculté leur a apporté',
                    'cta_title' => 'Vous êtes diplômé de ' . $short . ' ?',
                    'cta_text' => $theme['alumni_cta'] ?? ('Contactez le secrétariat pour actualiser votre profil ou rejoindre les activités du réseau alumni.'),
                    'cta_label' => 'Nous écrire',
                    'cta_url' => '/contact',
                ],
            ],
            'contact' => [
                'title' => 'Contact',
                'content' => [
                    'intro' => $theme['contact_intro'] ?? 'Écrivez-nous pour toute demande d’information sur les formations ou la recherche.',
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
            ['home', 'custom_text', 'Mot d’accueil', $theme['welcome'] ?? ('Bienvenue sur le site de ' . ($theme['short'] ?? 'la faculté') . '. Découvrez nos formations, notre recherche et la vie du campus.'), 1],
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
        return $this->buildTheme([
            'key' => 'eco',
            'tagline' => 'Former des économistes, gestionnaires et financiers depuis 1973',
            'seo_description' => 'La Faculté des Sciences Économiques et de Gestion de l’Université du Burundi forme des économistes, gestionnaires et financiers. Découvrez nos formations, notre recherche et nos actualités.',
            'about_title' => 'Plus de cinquante ans au service du développement',
            'about' => 'La Faculté des Sciences Économiques et de Gestion de l’Université du Burundi forme des cadres compétents, éthiques et innovants. Depuis 1973, elle combine rigueur académique et pertinence pratique pour préparer ses diplômés aux défis économiques du Burundi et de la région des Grands Lacs.',
            'mission' => 'Former des économistes, gestionnaires et financiers de haut niveau, capables d’analyser les enjeux économiques, de prendre des décisions éclairées et de contribuer activement au développement durable du Burundi et de la région des Grands Lacs. Nous formons des professionnels intègres, dotés d’une solide culture analytique et d’une forte aptitude à l’innovation.',
            'vision' => 'Devenir une faculté de référence en Afrique centrale dans les domaines des sciences économiques et de gestion, reconnue pour l’excellence de ses formations, la qualité de ses recherches et l’impact de ses diplômés sur la société, notamment à travers des partenariats stratégiques et une production scientifique de premier plan.',
            'values' => [
                ['title' => 'Intégrité', 'text' => 'Agir avec honnêteté, transparence et éthique dans toutes nos activités académiques et administratives.'],
                ['title' => 'Excellence', 'text' => 'Poursuivre les standards les plus élevés dans l’enseignement, la recherche et le service à la communauté.'],
                ['title' => 'Innovation', 'text' => 'Encourager la créativité, l’esprit critique et l’adaptation aux mutations économiques et technologiques.'],
            ],
            'research_title' => 'Laboratoires et équipes au service des politiques publiques',
            'research_body' => 'Nos unités travaillent sur le développement local, la finance inclusive, la gouvernance des entreprises et l’évaluation des politiques économiques. Les résultats nourrissent l’enseignement et éclairent les décideurs publics et privés.',
            'programmes_title' => 'De la licence au doctorat',
            'programmes_intro' => 'Des parcours en économie, gestion, finance et entrepreneuriat, conçus pour l’insertion professionnelle comme pour la poursuite d’études jusqu’au doctorat.',
            'posts_title' => 'Actualités et vie académique',
            'posts_intro' => 'Annonces pédagogiques, résultats, partenariats internationaux et événements scientifiques de la FSEG.',
            'dean_name' => 'Prof. Jean-Baptiste Ndayishimiye',
            'dean_message' => "C’est avec un immense plaisir que je vous souhaite la bienvenue sur le site de la Faculté des Sciences Économiques et de Gestion de l’Université du Burundi. Depuis sa création en 1973, notre faculté s’est imposée comme un pilier de l’enseignement supérieur au Burundi.\n\nNotre mission est claire : former des cadres compétents, éthiques et innovants, capables de contribuer au développement économique et social de notre nation. Nous offrons une formation de qualité, alliant rigueur académique et pertinence pratique.\n\nJe vous invite à explorer nos programmes, à découvrir nos activités de recherche et à rejoindre notre communauté académique dynamique.",
            'welcome' => 'Bienvenue à la FSEG. Explorez les formations, la recherche, les actualités et le réseau des diplômés — puis contactez le secrétariat pour toute question d’admission ou de partenariat.',
            'hero_captions' => [
                ['badge' => 'FSEG', 'title' => 'Former les décideurs économiques de demain', 'text' => 'Économie, finance, management et entrepreneuriat ancrés dans les réalités du Burundi et de la région des Grands Lacs.'],
                ['badge' => 'Formations', 'title' => 'Licence, master et doctorat', 'text' => 'Des filières professionnalisantes et académiques, de l’économie générale à l’audit et au contrôle de gestion.'],
                ['badge' => 'Recherche', 'title' => 'Quatre laboratoires actifs', 'text' => 'LEA, CRGD, LFC et URPP produisent des analyses utiles aux politiques publiques et aux entreprises.'],
                ['badge' => 'Campus', 'title' => 'Une vie étudiante engagée', 'text' => 'Conférences, journées portes ouvertes, séminaires doctoraux et accompagnement pédagogique tout au long du cursus.'],
                ['badge' => 'Alumni', 'title' => 'Plus de 15 000 diplômés', 'text' => 'Un réseau présent dans la banque, l’administration, le conseil et l’entrepreneuriat, au Burundi et à l’étranger.'],
            ],
            'highlights' => [
                ['icon' => 'bi-mortarboard', 'title' => 'Formation d’excellence', 'description' => 'Curricula modernisés, stages encadrés et exigence académique de la licence au doctorat.'],
                ['icon' => 'bi-graph-up-arrow', 'title' => 'Recherche appliquée', 'description' => 'Travaux sur la croissance, la finance inclusive, la gouvernance des PME et les politiques fiscales.'],
                ['icon' => 'bi-people', 'title' => 'Communauté et partenariats', 'description' => 'Échanges internationaux, réseau alumni et liens étroits avec le secteur public et privé.'],
            ],
            'stats' => null,
            'formations_banner' => 'Parcours en économie, gestion et finance',
            'research_banner' => 'Laboratoires LEA, CRGD, LFC et URPP',
            'staff_banner' => 'Enseignants-chercheurs et personnel administratif',
            'alumni_banner' => 'Un réseau de plus de 15 000 diplômés',
            'posts_banner' => 'Annonces, conférences et vie de campus',
            'publications_title' => 'Travaux récents de la FSEG',
            'publications_intro' => 'Articles publiés dans des revues africaines et internationales sur l’économie et la gestion.',
            'projects_title' => 'Projets de recherche financés',
            'alumni_intro' => [
                'Les diplômés de la FSEG occupent des responsabilités dans la banque, les institutions de développement, l’audit, l’administration publique et l’entrepreneuriat.',
                'Cette page présente quelques parcours représentatifs ; les fiches peuvent être enrichies depuis l’administration.',
            ],
            'alumni_profiles_text' => 'Des carrières qui illustrent la diversité des débouchés ouverts par les sciences économiques et de gestion.',
            'alumni_cta' => 'Contactez le secrétariat pour actualiser votre profil ou rejoindre les activités du réseau alumni FSEG.',
            'contact_intro' => 'Écrivez-nous pour toute demande d’information sur les admissions, les programmes ou les partenariats de recherche de la FSEG.',
        ], $this->programmeSet('eco'), $this->staffSet('eco'), $this->postSet('eco'), $this->labSet('eco'));
    }

    /** @return array<string, mixed> */
    private function themeSciences(): array
    {
        return $this->buildTheme([
            'key' => 'sci',
            'tagline' => 'Sciences, technologies et ingénierie au service du Burundi',
            'seo_description' => 'La Faculté des Sciences et Ingénierie forme mathématiciens, informaticiens, physiciens et ingénieurs capables d’innover pour le développement national.',
            'about_title' => 'Former des scientifiques et ingénieurs responsables',
            'about' => 'La Faculté des Sciences et Ingénierie de l’Université du Burundi prépare des scientifiques et des ingénieurs capables de résoudre des problèmes complexes. Les départements de mathématiques, informatique, physique et génie articulent enseignement fondamental, laboratoires et projets appliqués aux besoins énergétiques, numériques et industriels du pays.',
            'mission' => 'Transmettre une culture scientifique rigoureuse, développer l’esprit d’innovation et former des professionnels capables de concevoir, analyser et déployer des solutions technologiques adaptées au contexte burundais et régional.',
            'vision' => 'Être une faculté de sciences et d’ingénierie reconnue en Afrique des Grands Lacs pour la qualité de ses laboratoires, l’employabilité de ses diplômés et sa contribution aux transitions numérique et énergétique.',
            'values' => [
                ['title' => 'Rigueur', 'text' => 'Méthode scientifique, précision expérimentale et exigence dans les travaux pratiques.'],
                ['title' => 'Innovation', 'text' => 'Projets étudiants, prototypage et ouverture aux technologies émergentes.'],
                ['title' => 'Service', 'text' => 'Solutions concrètes pour l’énergie, les réseaux, les données et l’industrie locale.'],
            ],
            'research_title' => 'Laboratoires scientifiques et technologiques',
            'research_body' => 'Les équipes travaillent sur les énergies renouvelables, les systèmes d’information, la modélisation mathématique, la physique appliquée et les technologies numériques utiles aux administrations et aux entreprises.',
            'programmes_title' => 'Parcours scientifiques et technologiques',
            'programmes_intro' => 'Licences, masters et doctorats en mathématiques, informatique, physique, chimie, data science et génie — avec une forte composante expérimentale.',
            'posts_title' => 'Actualités scientifiques',
            'posts_intro' => 'Soutenances, hackathons, projets étudiants et annonces de laboratoires de la FSI.',
            'dean_name' => 'Pr. Eric Habonimana',
            'dean_message' => "Bienvenue à la Faculté des Sciences et Ingénierie.\n\nIci, la curiosité scientifique rencontre la rigueur expérimentale. Nos étudiants apprennent à modéliser, à programmer, à mesurer et à concevoir — toujours avec le souci d’apporter des réponses utiles au Burundi.\n\nJe vous invite à découvrir nos départements, nos laboratoires et les opportunités de stages et de recherche qui jalonnent le parcours.",
            'welcome' => 'Bienvenue à la FSI. Découvrez les départements scientifiques, les laboratoires et les projets numériques qui forment la prochaine génération d’ingénieurs et de chercheurs.',
            'hero_captions' => [
                ['badge' => 'FSI', 'title' => 'Sciences et ingénierie pour le pays', 'text' => 'Mathématiques, informatique, physique et génie au service des transitions numérique et énergétique.'],
                ['badge' => 'Laboratoires', 'title' => 'Apprendre en expérimentant', 'text' => 'Travaux pratiques, projets tutorés et plateformes numériques pour ancrer la théorie dans le réel.'],
                ['badge' => 'Numérique', 'title' => 'Données, logiciels et réseaux', 'text' => 'Des cursus qui préparent aux métiers du développement, de la cybersécurité et de la data science.'],
                ['badge' => 'Énergie', 'title' => 'Ingénierie durable', 'text' => 'Recherche et formation sur les énergies renouvelables et les systèmes efficaces.'],
                ['badge' => 'Communauté', 'title' => 'Ouverts sur l’industrie', 'text' => 'Partenariats avec opérateurs télécoms, PME technologiques et centres de recherche régionaux.'],
            ],
            'highlights' => [
                ['icon' => 'bi-cpu', 'title' => 'Informatique et data', 'description' => 'Génie logiciel, systèmes embarqués et science des données pour l’économie numérique.'],
                ['icon' => 'bi-lightning-charge', 'title' => 'Énergies et physique', 'description' => 'Formation et recherche tournées vers l’efficacité énergétique et les renouvelables.'],
                ['icon' => 'bi-calculator', 'title' => 'Mathématiques appliquées', 'description' => 'Modélisation, statistique et outils quantitatifs au service des autres sciences.'],
            ],
            'stats' => [
                ['section' => 'home_main', 'label' => 'Étudiants', 'value' => 1420, 'suffix' => '+'],
                ['section' => 'home_main', 'label' => 'Programmes', 'value' => 10, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Enseignants', 'value' => 54, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Laboratoires', 'value' => 5, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Publications', 'value' => 95, 'suffix' => '+'],
                ['section' => 'home_research', 'label' => 'Projets actifs', 'value' => 11, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Prototypes étudiants', 'value' => 40, 'suffix' => '+'],
                ['section' => 'alumni', 'label' => 'Diplômés', 'value' => 3100, 'suffix' => '+'],
                ['section' => 'alumni', 'label' => 'Entreprises partenaires', 'value' => 28, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Start-up incubées', 'value' => 15, 'suffix' => ''],
            ],
            'formations_banner' => 'Mathématiques, informatique, physique et génie',
            'research_banner' => 'Laboratoires numériques, énergie et modélisation',
            'staff_banner' => 'Enseignants-chercheurs et techniciens de laboratoire',
            'alumni_banner' => 'Ingénieurs et scientifiques dans le numérique et l’industrie',
            'posts_banner' => 'Hackathons, soutenances et annonces de laboratoires',
            'publications_title' => 'Publications scientifiques de la FSI',
            'publications_intro' => 'Travaux en informatique, physique appliquée, mathématiques et énergies renouvelables.',
            'projects_title' => 'Projets technologiques en cours',
            'alumni_intro' => [
                'Les diplômés de la FSI travaillent dans les télécoms, le développement logiciel, l’énergie, l’enseignement et la recherche appliquée.',
                'Les profils ci-dessous illustrent la diversité des débouchés scientifiques et techniques.',
            ],
            'alumni_profiles_text' => 'Parcours d’ingénieurs, data scientists et enseignants-chercheurs formés à la FSI.',
            'alumni_cta' => 'Écrivez au secrétariat pour rejoindre le réseau alumni sciences et ingénierie.',
            'contact_intro' => 'Contactez la FSI pour les admissions, les stages en laboratoire ou les partenariats technologiques.',
        ], $this->programmeSet('sci'), $this->staffSet('sci'), $this->postSet('sci'), $this->labSet('sci'));
    }

    /** @return array<string, mixed> */
    private function themeMedicine(): array
    {
        return $this->buildTheme([
            'key' => 'med',
            'tagline' => 'Former des professionnels de santé compétents et humains',
            'seo_description' => 'La Faculté de Médecine de l’Université du Burundi prépare médecins et professionnels de santé à servir les communautés avec excellence clinique et éthique.',
            'about_title' => 'Excellence clinique et service aux communautés',
            'about' => 'La Faculté de Médecine forme des médecins et des professionnels de santé capables de soigner avec compétence et humanité. L’enseignement s’appuie sur la pratique hospitalière, la santé publique et une éthique du soin adaptée aux réalités sanitaires du Burundi.',
            'mission' => 'Former des soignants dévoués, capables de relever les défis sanitaires nationaux — des soins de première ligne à la recherche biomédicale — dans le respect de la dignité des patients.',
            'vision' => 'Contribuer à un système de santé résilient en formant des professionnels reconnus pour leur excellence clinique, leur engagement communautaire et leur production scientifique.',
            'values' => [
                ['title' => 'Humanité', 'text' => 'Placer le patient et la communauté au centre de chaque décision clinique.'],
                ['title' => 'Compétence', 'text' => 'Stages hospitaliers exigeants et mise à jour permanente des connaissances.'],
                ['title' => 'Éthique', 'text' => 'Intégrité professionnelle et responsabilité dans l’exercice de la médecine.'],
            ],
            'research_title' => 'Recherche biomédicale et santé publique',
            'research_body' => 'Axes prioritaires : maladies infectieuses, santé maternelle et infantile, épidémiologie et organisation des systèmes de santé. Les travaux associent cliniciens, épidémiologistes et partenaires hospitaliers.',
            'programmes_title' => 'Formations médicales et paramédicales',
            'programmes_intro' => 'Médecine, sciences infirmières, pharmacie, santé publique et spécialités — ancrées dans la pratique hospitalière et le terrain communautaire.',
            'posts_title' => 'Actualités de la faculté de médecine',
            'posts_intro' => 'Annonces académiques, séminaires cliniques, campagnes de santé et vie hospitalo-universitaire.',
            'dean_name' => 'Pr. Dr. Claudine Niyonzima',
            'dean_message' => "Chères étudiantes, chers étudiants,\n\nNotre mission est de former des soignants dévoués, capables de relever les défis sanitaires du Burundi avec compétence et compassion.\n\nVous trouverez ici nos programmes, nos axes de recherche et les informations utiles pour rejoindre une communauté hospitalo-universitaire engagée au service de la population.",
            'welcome' => 'Bienvenue à la Faculté de Médecine. Découvrez les cursus cliniques, la recherche en santé publique et les opportunités de stages hospitaliers.',
            'hero_captions' => [
                ['badge' => 'Médecine', 'title' => 'Soigner avec compétence et humanité', 'text' => 'Une formation clinique exigeante, tournée vers les besoins sanitaires du Burundi.'],
                ['badge' => 'Stages', 'title' => 'Apprendre à l’hôpital', 'text' => 'Immersion dans les services de soins, encadrement de proximité et éthique du patient.'],
                ['badge' => 'Santé publique', 'title' => 'Agir pour les communautés', 'text' => 'Épidémiologie, prévention et organisation des systèmes de santé.'],
                ['badge' => 'Recherche', 'title' => 'Du laboratoire au lit du malade', 'text' => 'Travaux sur les maladies infectieuses et la santé maternelle et infantile.'],
                ['badge' => 'Communauté', 'title' => 'Au service de la population', 'text' => 'Campagnes de sensibilisation et partenariats avec les formations sanitaires.'],
            ],
            'highlights' => [
                ['icon' => 'bi-heart-pulse', 'title' => 'Formation clinique', 'description' => 'Stages hospitaliers structurés et enseignement au lit du malade.'],
                ['icon' => 'bi-hospital', 'title' => 'Santé publique', 'description' => 'Prévention, épidémiologie et organisation des soins de proximité.'],
                ['icon' => 'bi-emoji-smile', 'title' => 'Éthique du soin', 'description' => 'Une médecine attentive à la dignité et aux réalités des patients.'],
            ],
            'stats' => [
                ['section' => 'home_main', 'label' => 'Étudiants', 'value' => 980, 'suffix' => '+'],
                ['section' => 'home_main', 'label' => 'Programmes', 'value' => 10, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Enseignants', 'value' => 72, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Sites de stage', 'value' => 18, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Publications', 'value' => 110, 'suffix' => '+'],
                ['section' => 'home_research', 'label' => 'Projets actifs', 'value' => 9, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Essais / cohortes', 'value' => 6, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Diplômés', 'value' => 2800, 'suffix' => '+'],
                ['section' => 'alumni', 'label' => 'Hôpitaux partenaires', 'value' => 22, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Spécialistes formés', 'value' => 340, 'suffix' => '+'],
            ],
            'formations_banner' => 'Médecine, pharmacie, soins infirmiers et santé publique',
            'research_banner' => 'Biomédical, épidémiologie et systèmes de santé',
            'staff_banner' => 'Cliniciens, enseignants et personnel hospitalo-universitaire',
            'alumni_banner' => 'Médecins et soignants au service des communautés',
            'posts_banner' => 'Séminaires cliniques, résultats et vie hospitalière',
            'publications_title' => 'Publications biomédicales et de santé publique',
            'publications_intro' => 'Travaux cliniques et épidémiologiques issus des équipes de la faculté et de ses partenaires hospitaliers.',
            'projects_title' => 'Projets de santé en cours',
            'alumni_intro' => [
                'Les diplômés exercent dans les hôpitaux universitaires, les centres de santé, les organisations internationales et la recherche médicale.',
                'Les profils présentés montrent la diversité des carrières ouvertes par la formation médicale.',
            ],
            'alumni_profiles_text' => 'Parcours de médecins, infirmiers spécialisés et experts en santé publique.',
            'alumni_cta' => 'Contactez le secrétariat pour rejoindre le réseau des alumni de la Faculté de Médecine.',
            'contact_intro' => 'Pour les admissions, les stages cliniques ou les partenariats hospitaliers, écrivez au secrétariat de la faculté.',
        ], $this->programmeSet('med'), $this->staffSet('med'), $this->postSet('med'), $this->labSet('med'));
    }

    /** @return array<string, mixed> */
    private function themeAgro(): array
    {
        return $this->buildTheme([
            'key' => 'agro',
            'tagline' => 'Agriculture, environnement et développement rural',
            'seo_description' => 'La FABI forme des experts capables de moderniser l’agriculture, protéger les ressources naturelles et soutenir les communautés rurales au Burundi.',
            'about_title' => 'Sciences agronomiques pour la sécurité alimentaire',
            'about' => 'La Faculté d’Agronomie et de Bioingénierie forme des ingénieurs et techniciens capables de moderniser les filières agricoles, de protéger les sols et l’eau, et d’accompagner les producteurs. L’enseignement mêle sciences du vivant, terrain et innovation pour la sécurité alimentaire.',
            'mission' => 'Former des professionnels capables de concevoir des systèmes agricoles durables, de valoriser les ressources naturelles et de soutenir le développement rural inclusif.',
            'vision' => 'Être une référence régionale en agroécologie, gestion des ressources naturelles et innovation rurale, au service de la souveraineté alimentaire du Burundi.',
            'values' => [
                ['title' => 'Durabilité', 'text' => 'Préserver sols, eau et biodiversité dans chaque projet de formation et de recherche.'],
                ['title' => 'Proximité', 'text' => 'Travailler avec les coopératives, les stations expérimentales et les communautés rurales.'],
                ['title' => 'Innovation', 'text' => 'Mobiliser la science agronomique pour des filières résilientes et compétitives.'],
            ],
            'research_title' => 'Recherche agronomique et environnementale',
            'research_body' => 'Nos travaux portent sur les sols, les cultures vivrières, l’agroécologie, l’élevage et la gestion durable des bassins versants, en lien avec les besoins des producteurs et des politiques agricoles.',
            'programmes_title' => 'Parcours en agronomie et environnement',
            'programmes_intro' => 'Licences et masters en productions végétales, élevage, agroécologie et gestion des ressources naturelles — avec une forte dimension de terrain.',
            'posts_title' => 'Actualités agricoles et environnementales',
            'posts_intro' => 'Journées de terrain, résultats expérimentaux, partenariats ruraux et vie de la FABI.',
            'dean_name' => 'Pr. Ir. Sophie Ndayizeye',
            'dean_message' => "Chers étudiants, chers partenaires,\n\nEnsemble, cultivons des solutions durables pour la sécurité alimentaire. La FABI forme des ingénieurs capables d’écouter le terrain, de mesurer, d’expérimenter et d’accompagner les producteurs.\n\nJe vous invite à découvrir nos filières, nos stations et nos projets de recherche au service du monde rural.",
            'welcome' => 'Bienvenue à la FABI. Explorez les formations agronomiques, les laboratoires de terrain et les initiatives pour une agriculture durable.',
            'hero_captions' => [
                ['badge' => 'FABI', 'title' => 'Agir pour la sécurité alimentaire', 'text' => 'Formation et recherche pour moderniser l’agriculture et protéger les ressources naturelles.'],
                ['badge' => 'Terrain', 'title' => 'Apprendre auprès des producteurs', 'text' => 'Stages en coopératives, stations expérimentales et projets de développement rural.'],
                ['badge' => 'Agroécologie', 'title' => 'Produire autrement', 'text' => 'Des approches qui allient productivité, résilience climatique et respect des écosystèmes.'],
                ['badge' => 'Ressources', 'title' => 'Sols, eau et biodiversité', 'text' => 'Gestion durable des bassins versants et des systèmes de production.'],
                ['badge' => 'Communauté', 'title' => 'Au service du monde rural', 'text' => 'Partenariats avec les filières café, cultures vivrières et organisations paysannes.'],
            ],
            'highlights' => [
                ['icon' => 'bi-tree', 'title' => 'Agroécologie', 'description' => 'Pratiques agricoles durables adaptées aux systèmes de production locaux.'],
                ['icon' => 'bi-droplet', 'title' => 'Ressources naturelles', 'description' => 'Gestion des sols, de l’eau et des bassins versants.'],
                ['icon' => 'bi-people', 'title' => 'Développement rural', 'description' => 'Accompagnement des producteurs et des coopératives.'],
            ],
            'stats' => [
                ['section' => 'home_main', 'label' => 'Étudiants', 'value' => 760, 'suffix' => '+'],
                ['section' => 'home_main', 'label' => 'Programmes', 'value' => 10, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Enseignants', 'value' => 41, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Stations / sites', 'value' => 7, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Publications', 'value' => 70, 'suffix' => '+'],
                ['section' => 'home_research', 'label' => 'Projets actifs', 'value' => 12, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Parcelles expérimentales', 'value' => 25, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Diplômés', 'value' => 1900, 'suffix' => '+'],
                ['section' => 'alumni', 'label' => 'Coopératives partenaires', 'value' => 35, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Projets ruraux', 'value' => 48, 'suffix' => '+'],
            ],
            'formations_banner' => 'Agronomie, environnement et productions',
            'research_banner' => 'Sols, cultures, agroécologie et bassins versants',
            'staff_banner' => 'Agronomes, enseignants et techniciens de terrain',
            'alumni_banner' => 'Ingénieurs au service des filières agricoles',
            'posts_banner' => 'Terrain, expérimentation et vie de campus',
            'publications_title' => 'Publications agronomiques et environnementales',
            'publications_intro' => 'Travaux sur les sols, les cultures vivrières, l’élevage et la gestion durable des ressources.',
            'projects_title' => 'Projets ruraux et environnementaux',
            'alumni_intro' => [
                'Les diplômés de la FABI travaillent dans les projets de développement, les coopératives, la recherche agronomique et l’entrepreneuriat rural.',
                'Les profils ci-dessous montrent la diversité des carrières ouvertes par les sciences agronomiques.',
            ],
            'alumni_profiles_text' => 'Parcours d’agronomes, experts environnementaux et entrepreneurs ruraux.',
            'alumni_cta' => 'Contactez le secrétariat pour rejoindre le réseau alumni de la FABI.',
            'contact_intro' => 'Pour les admissions, les stages de terrain ou les partenariats agricoles, écrivez à la FABI.',
        ], $this->programmeSet('agro'), $this->staffSet('agro'), $this->postSet('agro'), $this->labSet('agro'));
    }

    /** @return array<string, mixed> */
    private function themeHumanities(): array
    {
        return $this->buildTheme([
            'key' => 'hum',
            'tagline' => 'Lettres, langues et sciences humaines',
            'seo_description' => 'La FLSH cultive l’esprit critique, les langues et la compréhension des sociétés à travers l’histoire, la philosophie, la littérature et la communication.',
            'about_title' => 'Comprendre les sociétés, transmettre les cultures',
            'about' => 'La Faculté des Lettres et des Sciences Humaines forme des esprits critiques capables d’analyser les sociétés, de maîtriser les langues et de transmettre le patrimoine culturel. Histoire, philosophie, lettres, langues et communication y dialoguent pour éclairer le présent.',
            'mission' => 'Former des citoyens cultivés, des enseignants, des communicants et des chercheurs capables de comprendre les dynamiques sociales et de contribuer au débat public.',
            'vision' => 'Faire de la FLSH un pôle régional d’excellence en sciences humaines, reconnu pour la qualité de ses formations linguistiques, historiques et communicationnelles.',
            'values' => [
                ['title' => 'Esprit critique', 'text' => 'Analyser les textes, les discours et les sociétés avec méthode et ouverture.'],
                ['title' => 'Pluralité', 'text' => 'Valoriser les langues, les cultures et les mémoires du Burundi et d’ailleurs.'],
                ['title' => 'Transmission', 'text' => 'Préparer des enseignants et médiateurs culturels engagés.'],
            ],
            'research_title' => 'Recherche en sciences humaines',
            'research_body' => 'Projets sur le patrimoine, les langues nationales, l’éducation, la communication et les dynamiques sociales contemporaines, en lien avec les communautés et les institutions culturelles.',
            'programmes_title' => 'Parcours en lettres et sciences humaines',
            'programmes_intro' => 'Licences et masters en lettres, histoire, philosophie, langues, communication et sciences de l’éducation — ouverts sur l’enseignement et les métiers de la culture.',
            'posts_title' => 'Actualités culturelles et académiques',
            'posts_intro' => 'Colloques, publications, activités étudiantes et vie littéraire de la FLSH.',
            'dean_name' => 'Pr. Diane Nkurunziza',
            'dean_message' => "Chères étudiantes, chers étudiants,\n\nLes humanités éclairent le présent et préparent des citoyens responsables. À la FLSH, vous apprendrez à lire le monde, à argumenter, à transmettre et à créer.\n\nJe vous invite à découvrir nos départements, nos activités de recherche et les débouchés ouverts par les lettres et les sciences humaines.",
            'welcome' => 'Bienvenue à la FLSH. Explorez les formations en lettres, langues et sciences humaines, ainsi que la vie culturelle du campus.',
            'hero_captions' => [
                ['badge' => 'FLSH', 'title' => 'Humanités pour éclairer le présent', 'text' => 'Lettres, histoire, philosophie, langues et communication au service d’une société éclairée.'],
                ['badge' => 'Langues', 'title' => 'Maîtriser et transmettre', 'text' => 'Formations linguistiques et littéraires pour l’enseignement, la traduction et la culture.'],
                ['badge' => 'Société', 'title' => 'Comprendre les dynamiques sociales', 'text' => 'Histoire, anthropologie et sciences de l’éducation pour analyser le monde contemporain.'],
                ['badge' => 'Culture', 'title' => 'Patrimoine et création', 'text' => 'Recherche et médiation culturelle autour des mémoires et des expressions artistiques.'],
                ['badge' => 'Communauté', 'title' => 'Ouverts sur le débat public', 'text' => 'Colloques, publications et partenariats avec les médias et les institutions culturelles.'],
            ],
            'highlights' => [
                ['icon' => 'bi-book', 'title' => 'Lettres et langues', 'description' => 'Littérature, linguistique et magister pour transmettre le goût des textes.'],
                ['icon' => 'bi-clock-history', 'title' => 'Histoire et société', 'description' => 'Comprendre les trajectoires du Burundi et de la région.'],
                ['icon' => 'bi-broadcast', 'title' => 'Communication', 'description' => 'Former des professionnels des médias et de la médiation culturelle.'],
            ],
            'stats' => [
                ['section' => 'home_main', 'label' => 'Étudiants', 'value' => 1680, 'suffix' => '+'],
                ['section' => 'home_main', 'label' => 'Programmes', 'value' => 10, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Enseignants', 'value' => 59, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Départements', 'value' => 6, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Publications', 'value' => 88, 'suffix' => '+'],
                ['section' => 'home_research', 'label' => 'Projets actifs', 'value' => 8, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Colloques / an', 'value' => 12, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Diplômés', 'value' => 3600, 'suffix' => '+'],
                ['section' => 'alumni', 'label' => 'Enseignants formés', 'value' => 920, 'suffix' => '+'],
                ['section' => 'alumni', 'label' => 'Médias / culture', 'value' => 140, 'suffix' => '+'],
            ],
            'formations_banner' => 'Lettres, histoire, langues et communication',
            'research_banner' => 'Patrimoine, langues et dynamiques sociales',
            'staff_banner' => 'Enseignants-chercheurs en sciences humaines',
            'alumni_banner' => 'Enseignants, journalistes et médiateurs culturels',
            'posts_banner' => 'Colloques, publications et vie littéraire',
            'publications_title' => 'Publications en sciences humaines',
            'publications_intro' => 'Travaux sur la littérature, l’histoire, les langues nationales et la communication.',
            'projects_title' => 'Projets culturels et éducatifs',
            'alumni_intro' => [
                'Les diplômés de la FLSH enseignent, écrivent, traduisent, animent des médias et conduisent des projets culturels ou éducatifs.',
                'Les profils présentés illustrent la richesse des débouchés ouverts par les humanités.',
            ],
            'alumni_profiles_text' => 'Parcours de journalistes, enseignants, traducteurs et chercheurs en sciences humaines.',
            'alumni_cta' => 'Contactez le secrétariat pour rejoindre le réseau alumni de la FLSH.',
            'contact_intro' => 'Pour les admissions, les stages en communication ou les partenariats culturels, écrivez à la FLSH.',
        ], $this->programmeSet('hum'), $this->staffSet('hum'), $this->postSet('hum'), $this->labSet('hum'));
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<array<string, mixed>> $programmes
     * @param list<array<string, mixed>> $staff
     * @param list<array<string, mixed>> $posts
     * @param list<array<string, mixed>> $labs
     * @return array<string, mixed>
     */
    private function buildTheme(array $meta, array $programmes, array $staff, array $posts, array $labs): array
    {
        $key = (string) $meta['key'];
        $defaultStats = [
            ['section' => 'home_main', 'label' => 'Étudiants', 'value' => 1850, 'suffix' => '+'],
            ['section' => 'home_main', 'label' => 'Programmes', 'value' => 10, 'suffix' => ''],
            ['section' => 'home_main', 'label' => 'Enseignants', 'value' => 68, 'suffix' => ''],
            ['section' => 'home_main', 'label' => 'Taux de réussite', 'value' => 82, 'suffix' => '%'],
            ['section' => 'home_research', 'label' => 'Publications', 'value' => 120, 'suffix' => '+'],
            ['section' => 'home_research', 'label' => 'Projets actifs', 'value' => 14, 'suffix' => ''],
            ['section' => 'home_research', 'label' => 'Laboratoires', 'value' => 3, 'suffix' => ''],
            ['section' => 'alumni', 'label' => 'Diplômés', 'value' => 4200, 'suffix' => '+'],
            ['section' => 'alumni', 'label' => 'Pays représentés', 'value' => 12, 'suffix' => ''],
            ['section' => 'alumni', 'label' => 'Entrepreneurs', 'value' => 180, 'suffix' => '+'],
        ];

        return array_merge($meta, [
            'stats' => $meta['stats'] ?? $defaultStats,
            'programmes' => $programmes,
            'staff' => $staff,
            'posts' => $posts,
            'labs' => $labs,
            'publications' => $this->publicationSet($key),
            'projects' => $this->projectSet($key),
            'timeline' => $this->timelineSet($key),
            'alumni' => $this->alumniSet($key),
            'testimonials' => $this->testimonialSet($key),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function programmeSet(string $key): array
    {
        $sets = [
            'eco' => [
            [
                'level' => 'licence',
                'title' => 'Licence en Économie',
                'slug' => 'licence-economie',
                'duration' => '3 ans',
                'summary' => 'Formation solide en théorie économique, microéconomie, macroéconomie, économétrie et politique économique.',
                'description' => 'Formation solide en théorie économique, microéconomie, macroéconomie, économétrie et politique économique. À la FSEG, ce parcours s’inscrit dans une tradition de formation des cadres économiques depuis 1973. Les enseignements allient cours magistraux, travaux dirigés, études de cas et stages encadrés. Les étudiants développent une culture professionnelle solide et une capacité d’analyse adaptée aux réalités du Burundi et de la région.',
                'admission' => 'Baccalauréat série scientifique, économique ou technique. Concours d\'entrée ou dossier de candidature.',
                'careers' => [
                    'Analyste économique',
                    'Conseiller en politiques publiques',
                    'Chargé d\'études',
                    'Poursuite en Master',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Gestion des Entreprises',
                'slug' => 'licence-gestion',
                'duration' => '3 ans',
                'summary' => 'Formation axée sur les fondamentaux du management, marketing, ressources humaines, organisation et stratégie d\'entreprise.',
                'description' => 'Formation axée sur les fondamentaux du management, marketing, ressources humaines, organisation et stratégie d\'entreprise. À la FSEG, ce parcours s’inscrit dans une tradition de formation des cadres économiques depuis 1973. Les enseignements allient cours magistraux, travaux dirigés, études de cas et stages encadrés. Les étudiants développent une culture professionnelle solide et une capacité d’analyse adaptée aux réalités du Burundi et de la région.',
                'admission' => 'Baccalauréat toutes séries. Priorité aux candidats avec mention.',
                'careers' => [
                    'Responsable RH',
                    'Chargé de marketing',
                    'Chef de projet',
                    'Manager opérationnel',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Finance et Comptabilité',
                'slug' => 'licence-finance',
                'duration' => '3 ans',
                'summary' => 'Cursus orienté vers la comptabilité générale et analytique, la fiscalité, la gestion financière, et l\'analyse des états financiers.',
                'description' => 'Cursus orienté vers la comptabilité générale et analytique, la fiscalité, la gestion financière, et l\'analyse des états financiers. À la FSEG, ce parcours s’inscrit dans une tradition de formation des cadres économiques depuis 1973. Les enseignements allient cours magistraux, travaux dirigés, études de cas et stages encadrés. Les étudiants développent une culture professionnelle solide et une capacité d’analyse adaptée aux réalités du Burundi et de la région.',
                'admission' => 'Baccalauréat série scientifique ou économique. Tests d\'aptitude mathématique.',
                'careers' => [
                    'Comptable',
                    'Contrôleur de gestion',
                    'Analyste financier',
                    'Auditeur junior',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Économie du Développement',
                'slug' => 'master-economie-developpement',
                'duration' => '2 ans',
                'summary' => 'Spécialisation en économie du développement, financement du développement, économie internationale et évaluation des politiques publiques.',
                'description' => 'Spécialisation en économie du développement, financement du développement, économie internationale et évaluation des politiques publiques. À la FSEG, ce parcours s’inscrit dans une tradition de formation des cadres économiques depuis 1973. Les enseignements allient cours magistraux, travaux dirigés, études de cas et stages encadrés. Les étudiants développent une culture professionnelle solide et une capacité d’analyse adaptée aux réalités du Burundi et de la région.',
                'admission' => 'Licence en économie ou équivalent. Dossier académique + entretien.',
                'careers' => [
                    'Économiste senior',
                    'Expert en développement',
                    'Consultant international',
                    'Chercheur',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Management des Organisations',
                'slug' => 'master-management',
                'duration' => '2 ans',
                'summary' => 'Formation de haut niveau en stratégie d\'entreprise, leadership, gestion du changement, gouvernance et management interculturel.',
                'description' => 'Formation de haut niveau en stratégie d\'entreprise, leadership, gestion du changement, gouvernance et management interculturel. À la FSEG, ce parcours s’inscrit dans une tradition de formation des cadres économiques depuis 1973. Les enseignements allient cours magistraux, travaux dirigés, études de cas et stages encadrés. Les étudiants développent une culture professionnelle solide et une capacité d’analyse adaptée aux réalités du Burundi et de la région.',
                'admission' => 'Licence en gestion, économie ou ingénierie. Expérience professionnelle appréciée.',
                'careers' => [
                    'Directeur général',
                    'Manager stratégique',
                    'Consultant en management',
                    'DRH',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Audit et Contrôle de Gestion',
                'slug' => 'master-audit-controle',
                'duration' => '2 ans',
                'summary' => 'Spécialisation en audit interne et externe, contrôle de gestion avancé, normes IFRS et gouvernance des organisations.',
                'description' => 'Spécialisation en audit interne et externe, contrôle de gestion avancé, normes IFRS et gouvernance des organisations. À la FSEG, ce parcours s’inscrit dans une tradition de formation des cadres économiques depuis 1973. Les enseignements allient cours magistraux, travaux dirigés, études de cas et stages encadrés. Les étudiants développent une culture professionnelle solide et une capacité d’analyse adaptée aux réalités du Burundi et de la région.',
                'admission' => 'Licence en finance, comptabilité ou gestion.',
                'careers' => [
                    'Auditeur senior',
                    'Contrôleur de gestion',
                    'Expert-comptable',
                    'Directeur financier',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Entrepreneuriat et Innovation',
                'slug' => 'master-entrepreneuriat',
                'duration' => '2 ans',
                'summary' => 'Création d’entreprise, innovation et financement de projets pour les porteurs d’idées.',
                'description' => 'Le master accompagne les étudiants dans la conception, le financement et le pilotage de projets entrepreneuriaux, avec un accent sur l’écosystème des start-up et des PME burundaises. Les incubations et pitchs sont organisés avec des partenaires du secteur privé. Les enseignements allient cours magistraux, travaux dirigés, études de cas et stages encadrés. Les étudiants développent une culture professionnelle solide et une capacité d’analyse adaptée aux réalités du Burundi et de la région.',
                'admission' => 'Licence en gestion, économie ou domaine connexe. Dossier et pitch de projet.',
                'careers' => [
                    'Fondateur de start-up',
                    'Chargé d’incubation',
                    'Consultant en innovation',
                    'Responsable développement',
                ],
            ],
            [
                'level' => 'doctorat',
                'title' => 'Doctorat en Sciences Économiques',
                'slug' => 'doctorat-economie',
                'duration' => '3 à 5 ans',
                'summary' => 'Programme doctoral en sciences économiques orienté vers la production d\'une thèse originale. Domaines : économie du développement, économétrie, économie internationale et politiques économiques.',
                'description' => 'Programme doctoral en sciences économiques orienté vers la production d\'une thèse originale. Domaines : économie du développement, économétrie, économie internationale et politiques économiques. À la FSEG, ce parcours s’inscrit dans une tradition de formation des cadres économiques depuis 1973. Les enseignements allient cours magistraux, travaux dirigés, études de cas et stages encadrés. Les étudiants développent une culture professionnelle solide et une capacité d’analyse adaptée aux réalités du Burundi et de la région.',
                'admission' => 'Master en économie avec mention. Projet de recherche + lettre de motivation + encadreur pressenti.',
                'careers' => [
                    'Enseignant-chercheur',
                    'Expert international',
                    'Conseiller économique de haut niveau',
                ],
            ],
            [
                'level' => 'doctorat',
                'title' => 'Doctorat en Sciences de Gestion',
                'slug' => 'doctorat-gestion',
                'duration' => '3 à 5 ans',
                'summary' => 'Doctorat en sciences de gestion permettant des recherches dans les domaines du management, de la finance, de la comptabilité et du marketing.',
                'description' => 'Doctorat en sciences de gestion permettant des recherches dans les domaines du management, de la finance, de la comptabilité et du marketing. À la FSEG, ce parcours s’inscrit dans une tradition de formation des cadres économiques depuis 1973. Les enseignements allient cours magistraux, travaux dirigés, études de cas et stages encadrés. Les étudiants développent une culture professionnelle solide et une capacité d’analyse adaptée aux réalités du Burundi et de la région.',
                'admission' => 'Master en gestion, finance ou management. Dossier complet + soutenance devant jury.',
                'careers' => [
                    'Professeur d\'université',
                    'Directeur de recherche',
                    'Expert conseil senior',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Certificat en Comptabilité',
                'slug' => 'certificat-comptabilite',
                'duration' => '1 an',
                'summary' => 'Parcours court pour consolider les bases comptables, fiscales et de reporting financier.',
                'description' => 'Formation courte destinée aux professionnels et diplômés souhaitant renforcer leurs compétences en comptabilité générale, fiscalité et reporting. Les modules sont conçus pour une mise en pratique immédiate dans les PME et cabinets. Les enseignements allient cours magistraux, travaux dirigés, études de cas et stages encadrés. Les étudiants développent une culture professionnelle solide et une capacité d’analyse adaptée aux réalités du Burundi et de la région.',
                'admission' => 'Baccalauréat ou expérience professionnelle en gestion. Dossier de candidature.',
                'careers' => [
                    'Aide-comptable',
                    'Assistant fiscal',
                    'Technicien de reporting',
                ],
            ],
            ],
            'sci' => [
            [
                'level' => 'licence',
                'title' => 'Licence en Mathématiques',
                'slug' => 'licence-mathematiques',
                'duration' => '3 ans',
                'summary' => 'Analyse, algèbre, probabilités et modélisation pour les métiers scientifiques et l’ingénierie.',
                'description' => 'Le programme développe une culture mathématique rigoureuse : analyse réelle et complexe, algèbre linéaire, probabilités, statistiques et introduction à la modélisation. Les étudiants apprennent à formaliser des problèmes issus de la physique, de l’informatique et de l’économie, avec des projets numériques et un stage en fin de cursus.',
                'admission' => 'Baccalauréat scientifique. Tests d’aptitude en mathématiques.',
                'careers' => [
                    'Enseignant de mathématiques',
                    'Analyste quantitatif',
                    'Statisticien',
                    'Poursuite en Master',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Informatique',
                'slug' => 'licence-informatique',
                'duration' => '3 ans',
                'summary' => 'Algorithmique, programmation, bases de données et réseaux pour construire des systèmes numériques fiables.',
                'description' => 'Formation complète en algorithmique, structures de données, programmation orientée objet, bases de données, réseaux et génie logiciel de base. Les projets tutorés et les stages en entreprise préparent à l’insertion dans le secteur numérique burundais et régional.',
                'admission' => 'Baccalauréat scientifique ou technique. Concours ou dossier.',
                'careers' => [
                    'Développeur',
                    'Administrateur systèmes',
                    'Analyste programmeur',
                    'Poursuite en Master',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Physique',
                'slug' => 'licence-physique',
                'duration' => '3 ans',
                'summary' => 'Physique fondamentale et appliquée, avec une forte composante expérimentale en laboratoire.',
                'description' => 'Cursus couvrant la mécanique, l’électromagnétisme, la thermodynamique, la physique quantique et l’optique. Les travaux pratiques renforcent la maîtrise des instruments de mesure et de l’analyse de données expérimentales, ouvrant sur l’énergie et l’instrumentation.',
                'admission' => 'Baccalauréat scientifique. Dossier et entretien.',
                'careers' => [
                    'Technicien de laboratoire',
                    'Enseignant de physique',
                    'Assistant de recherche',
                    'Poursuite en Master',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Chimie',
                'slug' => 'licence-chimie',
                'duration' => '3 ans',
                'summary' => 'Chimie générale, organique et analytique au service de l’industrie et de l’environnement.',
                'description' => 'Les étudiants acquièrent les bases de la chimie générale, organique, inorganique et analytique, avec des laboratoires dédiés à la synthèse, à l’analyse et à la sécurité chimique. Le programme ouvre sur l’industrie, l’environnement et la poursuite d’études.',
                'admission' => 'Baccalauréat scientifique. Tests de chimie et mathématiques.',
                'careers' => [
                    'Technicien chimiste',
                    'Analyste qualité',
                    'Assistant laboratoire',
                    'Poursuite en Master',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Génie logiciel',
                'slug' => 'master-genie-logiciel',
                'duration' => '2 ans',
                'summary' => 'Architecture logicielle, qualité, DevOps et conduite de projets informatiques complexes.',
                'description' => 'Spécialisation en conception d’architectures logicielles, tests, intégration continue, sécurité applicative et management de projets IT. Les étudiants réalisent un projet de fin d’études en partenariat avec des entreprises ou administrations.',
                'admission' => 'Licence en informatique ou équivalent. Dossier et entretien technique.',
                'careers' => [
                    'Ingénieur logiciel',
                    'Architecte applicatif',
                    'Chef de projet IT',
                    'Lead développeur',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Data Science',
                'slug' => 'master-data-science',
                'duration' => '2 ans',
                'summary' => 'Statistiques avancées, apprentissage automatique et visualisation pour valoriser les données.',
                'description' => 'Le master forme à la collecte, au nettoyage, à la modélisation et à la visualisation de données. Les modules couvrent le machine learning, les bases analytiques et l’éthique des données, avec des cas issus de l’administration et des entreprises locales.',
                'admission' => 'Licence en informatique, mathématiques ou statistiques.',
                'careers' => [
                    'Data scientist',
                    'Analyste de données',
                    'Ingénieur ML',
                    'Consultant BI',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Énergies renouvelables',
                'slug' => 'master-energies',
                'duration' => '2 ans',
                'summary' => 'Solarisation, hydroélectricité et efficacité énergétique pour la transition énergétique nationale.',
                'description' => 'Formation interdisciplinaire sur les technologies solaires, hydroélectriques et d’efficacité énergétique. Les projets de terrain et les études de faisabilité préparent à accompagner la transition énergétique du Burundi.',
                'admission' => 'Licence en physique, génie ou sciences appliquées.',
                'careers' => [
                    'Ingénieur énergie',
                    'Consultant ENR',
                    'Chargé de projet énergie',
                    'Chercheur',
                ],
            ],
            [
                'level' => 'doctorat',
                'title' => 'Doctorat en Informatique',
                'slug' => 'doctorat-informatique',
                'duration' => '3 à 5 ans',
                'summary' => 'Recherche doctorale en systèmes, IA, réseaux ou génie logiciel.',
                'description' => 'Le doctorat conduit à une thèse originale dans les domaines des systèmes d’information, de l’intelligence artificielle, des réseaux ou du génie logiciel, avec publications et collaborations internationales.',
                'admission' => 'Master en informatique avec mention. Projet de recherche et encadreur.',
                'careers' => [
                    'Enseignant-chercheur',
                    'Expert R&D',
                    'Architecte senior',
                ],
            ],
            [
                'level' => 'doctorat',
                'title' => 'Doctorat en Physique',
                'slug' => 'doctorat-physique',
                'duration' => '3 à 5 ans',
                'summary' => 'Thèse en physique de la matière, énergie ou instrumentation.',
                'description' => 'Programme doctoral orienté vers la physique de la matière, l’énergétique ou l’instrumentation scientifique, avec accès aux laboratoires de la faculté et à des partenariats régionaux.',
                'admission' => 'Master en physique. Projet de thèse validé.',
                'careers' => [
                    'Chercheur',
                    'Enseignant-chercheur',
                    'Expert technique',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Systèmes embarqués',
                'slug' => 'master-systemes-embarques',
                'duration' => '2 ans',
                'summary' => 'Microcontrôleurs, IoT et électronique embarquée pour l’industrie et l’agriculture connectée.',
                'description' => 'Le parcours forme à la conception de systèmes embarqués, à l’IoT et à l’électronique de contrôle, avec des applications pour l’industrie légère, l’agriculture de précision et les services urbains.',
                'admission' => 'Licence en informatique, électronique ou physique.',
                'careers' => [
                    'Ingénieur embarqué',
                    'Développeur IoT',
                    'Technicien R&D',
                ],
            ],
            ],
            'med' => [
            [
                'level' => 'licence',
                'title' => 'Médecine générale',
                'slug' => 'medecine-generale',
                'duration' => '6 ans',
                'summary' => 'Formation médicale complète alliant sciences fondamentales, clinique et stages hospitaliers.',
                'description' => 'Le cursus de médecine générale prépare des médecins capables de diagnostiquer, soigner et prévenir. Les années précliniques portent sur l’anatomie, la physiologie et la pathologie ; les années cliniques s’effectuent en stages hospitaliers encadrés dans les structures partenaires.',
                'admission' => 'Baccalauréat scientifique. Concours d’entrée très sélectif.',
                'careers' => [
                    'Médecin généraliste',
                    'Médecin de santé publique',
                    'Poursuite en spécialisation',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Sciences infirmières',
                'slug' => 'sciences-infirmieres',
                'duration' => '4 ans',
                'summary' => 'Soins infirmiers, éthique clinique et santé communautaire pour un exercice responsable.',
                'description' => 'Formation professionnalisante en soins infirmiers, pharmacologie de base, urgences et santé communautaire. Les stages en centres de santé et hôpitaux développent le geste technique et la relation de soin.',
                'admission' => 'Baccalauréat. Concours et entretien de motivation.',
                'careers' => [
                    'Infirmier(ère)',
                    'Cadre de santé',
                    'Infirmier communautaire',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Santé publique',
                'slug' => 'sante-publique',
                'duration' => '3 ans',
                'summary' => 'Épidémiologie, politiques de santé et promotion de la santé des populations.',
                'description' => 'Le programme forme à l’analyse des déterminants de la santé, à la planification sanitaire et à la promotion de la santé. Les étudiants réalisent des enquêtes de terrain et collaborent avec les districts sanitaires.',
                'admission' => 'Baccalauréat. Dossier académique.',
                'careers' => [
                    'Agent de santé publique',
                    'Chargé de programmes',
                    'Statisticien sanitaire',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Pharmacie',
                'slug' => 'pharmacie',
                'duration' => '5 ans',
                'summary' => 'Sciences pharmaceutiques, dispensation et assurance qualité des médicaments.',
                'description' => 'Cursus couvrant la chimie pharmaceutique, la pharmacologie, la galénique et la législation pharmaceutique. Les stages en officine et en hôpital préparent à la dispensation et à la gestion des stocks de médicaments.',
                'admission' => 'Baccalauréat scientifique. Concours.',
                'careers' => [
                    'Pharmacien',
                    'Responsable pharmacie hospitalière',
                    'Inspecteur pharmaceutique',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Laboratoire médical',
                'slug' => 'laboratoire-medical',
                'duration' => '3 ans',
                'summary' => 'Biologie médicale, analyses et contrôle qualité en laboratoire de diagnostic.',
                'description' => 'Les étudiants maîtrisent les techniques d’hématologie, biochimie, microbiologie et immunologie appliquées au diagnostic. Le programme insiste sur la biosécurité et le contrôle qualité.',
                'admission' => 'Baccalauréat scientifique.',
                'careers' => [
                    'Technicien de laboratoire',
                    'Biologiste junior',
                    'Assistant de recherche',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Épidémiologie',
                'slug' => 'master-epidemiologie',
                'duration' => '2 ans',
                'summary' => 'Méthodes épidémiologiques, biostatistique et surveillance des maladies.',
                'description' => 'Spécialisation en conception d’études épidémiologiques, biostatistique et surveillance des maladies transmissibles et non transmissibles. Les mémoires portent sur des enjeux sanitaires nationaux.',
                'admission' => 'Licence en santé publique, médecine ou sciences biomédicales.',
                'careers' => [
                    'Épidémiologiste',
                    'Analyste surveillance',
                    'Chercheur en santé',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Santé communautaire',
                'slug' => 'master-sante-communautaire',
                'duration' => '2 ans',
                'summary' => 'Intervention communautaire, nutrition et renforcement des systèmes de santé de proximité.',
                'description' => 'Le master prépare à concevoir et évaluer des interventions de santé communautaire, avec un accent sur la nutrition, la santé maternelle et infantile et la participation communautaire.',
                'admission' => 'Licence en santé ou sciences sociales appliquées à la santé.',
                'careers' => [
                    'Coordinateur de programmes',
                    'Conseiller communautaire',
                    'Cadre ONG santé',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Spécialisation en Pédiatrie',
                'slug' => 'specialisation-pediatrie',
                'duration' => '3 à 4 ans',
                'summary' => 'Formation spécialisée en médecine de l’enfant et de l’adolescent.',
                'description' => 'Parcours de spécialisation clinique en pédiatrie, avec stages prolongés en services pédiatriques, néonatalogie et urgences pédiatriques. L’éthique et la communication avec les familles sont au cœur de la formation.',
                'admission' => 'Diplôme de médecine générale. Concours de spécialisation.',
                'careers' => [
                    'Pédiatre',
                    'Médecin hospitalier',
                    'Enseignant clinicien',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Spécialisation en Chirurgie',
                'slug' => 'specialisation-chirurgie',
                'duration' => '4 à 5 ans',
                'summary' => 'Formation chirurgicale progressive, du geste technique à la responsabilité opératoire.',
                'description' => 'Spécialisation exigeante combinant anatomie chirurgicale, techniques opératoires et prise en charge péri-opératoire. Les résidents progressent sous supervision dans les blocs opératoires partenaires.',
                'admission' => 'Diplôme de médecine générale. Sélection sur concours.',
                'careers' => [
                    'Chirurgien',
                    'Médecin hospitalier',
                    'Formateur clinique',
                ],
            ],
            [
                'level' => 'doctorat',
                'title' => 'Doctorat en Sciences médicales',
                'slug' => 'doctorat-sciences-medicales',
                'duration' => '3 à 5 ans',
                'summary' => 'Recherche doctorale en sciences biomédicales ou santé des populations.',
                'description' => 'Le doctorat conduit à une thèse originale en sciences biomédicales, clinique ou santé des populations, avec publications dans des revues indexées et collaborations internationales.',
                'admission' => 'Master ou spécialisation médicale. Projet de recherche validé.',
                'careers' => [
                    'Enseignant-chercheur',
                    'Expert OMS/partenaires',
                    'Directeur de recherche',
                ],
            ],
            ],
            'agro' => [
            [
                'level' => 'licence',
                'title' => 'Licence en Agronomie',
                'slug' => 'licence-agronomie',
                'duration' => '3 ans',
                'summary' => 'Productions végétales, sols et systèmes agricoles durables pour le monde rural burundais.',
                'description' => 'Formation polyvalente en agronomie générale : sols, cultures, protection des plantes et économie agricole. Les sorties de terrain et les stages en exploitations ancrent les connaissances dans les systèmes de production locaux.',
                'admission' => 'Baccalauréat scientifique ou agricole.',
                'careers' => [
                    'Agronome',
                    'Conseiller agricole',
                    'Technicien de production',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Environnement',
                'slug' => 'licence-environnement',
                'duration' => '3 ans',
                'summary' => 'Écologie, gestion des ressources naturelles et évaluation environnementale.',
                'description' => 'Le programme traite de l’écologie, de la gestion des bassins versants, de la biodiversité et de l’évaluation d’impact environnemental. Les étudiants acquièrent des outils de diagnostic et de suivi des milieux naturels.',
                'admission' => 'Baccalauréat scientifique.',
                'careers' => [
                    'Conseiller environnement',
                    'Agent de suivi écologique',
                    'Chargé d’études EIA',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Productions végétales',
                'slug' => 'licence-productions-vegetales',
                'duration' => '3 ans',
                'summary' => 'Cultures vivrières et de rente, sélection variétale et itinéraires techniques.',
                'description' => 'Cursus centré sur les cultures vivrières et de rente (café, thé, cultures maraîchères), la sélection variétale et les itinéraires techniques adaptés aux conditions agroclimatiques du Burundi.',
                'admission' => 'Baccalauréat scientifique ou agricole.',
                'careers' => [
                    'Technicien cultures',
                    'Conseiller filière',
                    'Responsable production',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Élevage',
                'slug' => 'licence-elevage',
                'duration' => '3 ans',
                'summary' => 'Productions animales, santé du troupeau et valorisation des produits d’élevage.',
                'description' => 'Formation sur l’élevage bovin, caprin et avicole, la nutrition animale, la santé du troupeau et la valorisation des produits. Les stages en fermes et centres d’élevage complètent les enseignements.',
                'admission' => 'Baccalauréat scientifique ou agricole.',
                'careers' => [
                    'Technicien élevage',
                    'Conseiller pastoral',
                    'Responsable ferme',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Agroécologie',
                'slug' => 'master-agroecologie',
                'duration' => '2 ans',
                'summary' => 'Systèmes agroécologiques, fertilité des sols et résilience climatique.',
                'description' => 'Le master approfondit les principes de l’agroécologie, la gestion de la fertilité, la diversification des systèmes et l’adaptation au changement climatique. Les projets de terrain impliquent des coopératives et des ONG rurales.',
                'admission' => 'Licence en agronomie ou environnement.',
                'careers' => [
                    'Expert agroécologie',
                    'Chargé de projet rural',
                    'Chercheur',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Gestion des ressources naturelles',
                'slug' => 'master-ressources-naturelles',
                'duration' => '2 ans',
                'summary' => 'Gouvernance des ressources, bassins versants et conservation.',
                'description' => 'Spécialisation en gouvernance des ressources naturelles, restauration des bassins versants et conservation de la biodiversité, avec outils de cartographie et de suivi.',
                'admission' => 'Licence en environnement ou agronomie.',
                'careers' => [
                    'Gestionnaire de ressources',
                    'Expert conservation',
                    'Consultant',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Développement rural',
                'slug' => 'master-developpement-rural',
                'duration' => '2 ans',
                'summary' => 'Politiques rurales, filières agricoles et accompagnement des communautés.',
                'description' => 'Formation interdisciplinaire sur les politiques de développement rural, l’organisation des filières et l’accompagnement des communautés. Les mémoires s’appuient sur des enquêtes de terrain.',
                'admission' => 'Licence en agronomie, économie ou sciences sociales.',
                'careers' => [
                    'Chargé de développement',
                    'Coordinateur de projets',
                    'Conseiller filière',
                ],
            ],
            [
                'level' => 'doctorat',
                'title' => 'Doctorat en Sciences agronomiques',
                'slug' => 'doctorat-agronomie',
                'duration' => '3 à 5 ans',
                'summary' => 'Recherche doctorale en productions végétales, sols ou systèmes agricoles.',
                'description' => 'Le doctorat conduit à une thèse originale en productions végétales, science des sols ou systèmes agricoles, avec publications et partenariats de recherche régionaux.',
                'admission' => 'Master en sciences agronomiques. Projet de thèse.',
                'careers' => [
                    'Enseignant-chercheur',
                    'Expert ISABU/partenaires',
                    'Directeur de programme',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Sols et fertilité',
                'slug' => 'master-sols',
                'duration' => '2 ans',
                'summary' => 'Pédologie, analyse de sols et stratégies de fertilisation durable.',
                'description' => 'Parcours spécialisé en pédologie, analyses de laboratoire et stratégies de fertilisation organique et minérale adaptées aux sols tropicaux d’altitude.',
                'admission' => 'Licence en agronomie.',
                'careers' => [
                    'Expert sols',
                    'Conseiller fertilité',
                    'Chercheur',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Certificat en Irrigation',
                'slug' => 'certificat-irrigation',
                'duration' => '1 an',
                'summary' => 'Techniques d’irrigation, dimensionnement et gestion de l’eau agricole.',
                'description' => 'Formation courte sur les techniques d’irrigation, le dimensionnement des réseaux et la gestion collective de l’eau agricole, destinée aux techniciens et agents de terrain.',
                'admission' => 'Baccalauréat ou expérience en agronomie.',
                'careers' => [
                    'Technicien irrigation',
                    'Agent hydraulique agricole',
                ],
            ],
            ],
            'hum' => [
            [
                'level' => 'licence',
                'title' => 'Licence en Lettres modernes',
                'slug' => 'licence-lettres',
                'duration' => '3 ans',
                'summary' => 'Littératures francophones, stylistique et critique littéraire.',
                'description' => 'Le programme explore les littératures française et francophones, la stylistique et les méthodes de la critique littéraire. Les ateliers d’écriture et les séminaires développent l’expression écrite et orale.',
                'admission' => 'Baccalauréat littéraire ou équivalent.',
                'careers' => [
                    'Enseignant de lettres',
                    'Rédacteur',
                    'Critique littéraire',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Histoire',
                'slug' => 'licence-histoire',
                'duration' => '3 ans',
                'summary' => 'Histoire du Burundi, de l’Afrique et du monde, méthodes et sources.',
                'description' => 'Formation aux méthodes historiques, à l’histoire du Burundi et de la région des Grands Lacs, ainsi qu’à l’histoire contemporaine mondiale. Les étudiants travaillent sur archives et enquêtes orales.',
                'admission' => 'Baccalauréat. Dossier.',
                'careers' => [
                    'Enseignant d’histoire',
                    'Archiviste',
                    'Chargé de patrimoine',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Philosophie',
                'slug' => 'licence-philosophie',
                'duration' => '3 ans',
                'summary' => 'Histoire de la philosophie, éthique et esprit critique.',
                'description' => 'Cursus couvrant l’histoire de la philosophie, la logique, l’éthique et la philosophie politique. L’accent est mis sur la rigueur argumentative et le dialogue interdisciplinaire.',
                'admission' => 'Baccalauréat.',
                'careers' => [
                    'Enseignant de philosophie',
                    'Conseiller éthique',
                    'Rédacteur',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Langues',
                'slug' => 'licence-langues',
                'duration' => '3 ans',
                'summary' => 'Langues nationales et étrangères, traduction et interculturalité.',
                'description' => 'Le programme combine langues nationales (kirundi), français, anglais et initiation à la traduction. Les étudiants développent des compétences interculturelles utiles à l’enseignement, à la médiation et aux organisations internationales.',
                'admission' => 'Baccalauréat. Tests de langues.',
                'careers' => [
                    'Traducteur',
                    'Enseignant de langues',
                    'Médiateur culturel',
                ],
            ],
            [
                'level' => 'licence',
                'title' => 'Licence en Communication',
                'slug' => 'licence-communication',
                'duration' => '3 ans',
                'summary' => 'Médias, communication institutionnelle et production de contenus.',
                'description' => 'Formation aux médias, à la communication institutionnelle et à la production de contenus (écrit, radio, numérique). Les ateliers pratiques et les stages en médias locaux préparent à l’insertion professionnelle.',
                'admission' => 'Baccalauréat. Portfolio ou entretien.',
                'careers' => [
                    'Journaliste',
                    'Chargé de communication',
                    'Community manager',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Littérature',
                'slug' => 'master-litterature',
                'duration' => '2 ans',
                'summary' => 'Recherche littéraire, littératures africaines et théories critiques.',
                'description' => 'Le master approfondit la recherche littéraire, les littératures africaines et les théories critiques contemporaines. Les séminaires et le mémoire préparent au doctorat ou à l’édition culturelle.',
                'admission' => 'Licence en lettres ou équivalent.',
                'careers' => [
                    'Chercheur',
                    'Éditeur',
                    'Enseignant supérieur',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Sciences de l’éducation',
                'slug' => 'master-education',
                'duration' => '2 ans',
                'summary' => 'Didactique, politiques éducatives et innovation pédagogique.',
                'description' => 'Spécialisation en didactique, évaluation, politiques éducatives et innovation pédagogique. Destiné aux enseignants et cadres du système éducatif souhaitant renforcer leurs compétences.',
                'admission' => 'Licence et expérience pédagogique appréciée.',
                'careers' => [
                    'Cadre pédagogique',
                    'Formateur',
                    'Inspecteur',
                ],
            ],
            [
                'level' => 'master',
                'title' => 'Master en Anthropologie',
                'slug' => 'master-anthropologie',
                'duration' => '2 ans',
                'summary' => 'Anthropologie sociale, enquêtes de terrain et dynamiques culturelles.',
                'description' => 'Le master forme à l’anthropologie sociale et culturelle, aux enquêtes ethnographiques et à l’analyse des dynamiques sociales au Burundi et dans la région.',
                'admission' => 'Licence en sciences humaines.',
                'careers' => [
                    'Anthropologue',
                    'Chercheur',
                    'Conseiller social',
                ],
            ],
            [
                'level' => 'doctorat',
                'title' => 'Doctorat en Histoire',
                'slug' => 'doctorat-histoire',
                'duration' => '3 à 5 ans',
                'summary' => 'Thèse originale en histoire du Burundi, de l’Afrique ou comparée.',
                'description' => 'Programme doctoral conduisant à une thèse originale en histoire nationale, régionale ou comparée, avec exigence de publications et de contributions aux archives.',
                'admission' => 'Master en histoire. Projet de thèse.',
                'careers' => [
                    'Enseignant-chercheur',
                    'Expert patrimonial',
                    'Historien public',
                ],
            ],
            [
                'level' => 'doctorat',
                'title' => 'Doctorat en Lettres',
                'slug' => 'doctorat-lettres',
                'duration' => '3 à 5 ans',
                'summary' => 'Recherche doctorale en littératures et langues.',
                'description' => 'Le doctorat porte sur les littératures, la linguistique ou les études culturelles, avec une production scientifique reconnue et un ancrage dans les enjeux culturels contemporains.',
                'admission' => 'Master en lettres ou langues. Projet de recherche.',
                'careers' => [
                    'Enseignant-chercheur',
                    'Critique',
                    'Expert culturel',
                ],
            ],
            ],
        ];

        return $sets[$key] ?? $sets['eco'];
    }

    /** @return list<array<string, mixed>> */
    private function staffSet(string $key): array
    {
        $sets = [
            'eco' => [
            [
                'category' => 'enseignant',
                'name' => 'Prof. Jean-Baptiste Ndayishimiye',
                'slug' => 'ndayishimiye',
                'grade' => 'Professeur Ordinaire',
                'specialty' => 'Économie & Développement',
                'role' => 'Doyen de la Faculté',
                'email' => 'jb.ndayishimiye@ub.edu.bi',
                'bio' => 'Prof. Jean-Baptiste Ndayishimiye est Doyen de la Faculté à la FSEG. Spécialisé(e) en Économie & Développement, il/elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Son engagement illustre la diversité des parcours académiques au service de la communauté universitaire.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Marie-Claire Hakizimana',
                'slug' => 'hakizimana',
                'grade' => 'Maître de Conférences',
                'specialty' => 'Finance & Comptabilité',
                'role' => 'Chef de Département Finance',
                'email' => 'mc.hakizimana@ub.edu.bi',
                'bio' => 'Dr. Marie-Claire Hakizimana est Chef de Département Finance à la FSEG. Spécialisé(e) en Finance & Comptabilité, il/elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Son engagement illustre la diversité des parcours académiques au service de la communauté universitaire.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Pierre Nkurunziza',
                'slug' => 'nkurunziza',
                'grade' => 'Maître de Conférences',
                'specialty' => 'Gestion des Entreprises',
                'role' => 'Responsable Master Management',
                'email' => 'p.nkurunziza@ub.edu.bi',
                'bio' => 'Dr. Pierre Nkurunziza est Responsable Master Management à la FSEG. Spécialisé(e) en Gestion des Entreprises, il/elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Son engagement illustre la diversité des parcours académiques au service de la communauté universitaire.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Aline Ntakarutimana',
                'slug' => 'ntakarutimana',
                'grade' => 'Maître Assistant',
                'specialty' => 'Économie du Développement',
                'role' => 'Directrice URPP',
                'email' => 'a.ntakarutimana@ub.edu.bi',
                'bio' => 'Dr. Aline Ntakarutimana est Directrice URPP à la FSEG. Spécialisé(e) en Économie du Développement, il/elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Son engagement illustre la diversité des parcours académiques au service de la communauté universitaire.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Faustin Bigirimana',
                'slug' => 'bigirimana',
                'grade' => 'Maître Assistant',
                'specialty' => 'Audit & Contrôle de Gestion',
                'role' => 'Directeur LFC',
                'email' => 'f.bigirimana@ub.edu.bi',
                'bio' => 'Dr. Faustin Bigirimana est Directeur LFC à la FSEG. Spécialisé(e) en Audit & Contrôle de Gestion, il/elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Son engagement illustre la diversité des parcours académiques au service de la communauté universitaire.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Rose Manirakiza',
                'slug' => 'manirakiza',
                'grade' => 'Assistante',
                'specialty' => 'Marketing & Stratégie',
                'role' => 'Enseignante-Chercheure',
                'email' => 'r.manirakiza@ub.edu.bi',
                'bio' => 'Dr. Rose Manirakiza est Enseignante-Chercheure à la FSEG. Spécialisé(e) en Marketing & Stratégie, il/elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Son engagement illustre la diversité des parcours académiques au service de la communauté universitaire.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Cyprien Dusabimana',
                'slug' => 'dusabimana',
                'grade' => 'Maître Assistant',
                'specialty' => 'Économétrie',
                'role' => 'Directeur LEA',
                'email' => 'c.dusabimana@ub.edu.bi',
                'bio' => 'Dr. Cyprien Dusabimana est Directeur LEA à la FSEG. Spécialisé(e) en Économétrie, il/elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Son engagement illustre la diversité des parcours académiques au service de la communauté universitaire.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Geneviève Niyonkuru',
                'slug' => 'niyonkuru',
                'grade' => 'Assistante',
                'specialty' => 'Ressources Humaines',
                'role' => 'Enseignante-Chercheure',
                'email' => 'g.niyonkuru@ub.edu.bi',
                'bio' => 'Dr. Geneviève Niyonkuru est Enseignante-Chercheure à la FSEG. Spécialisé(e) en Ressources Humaines, il/elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Son engagement illustre la diversité des parcours académiques au service de la communauté universitaire.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'M. Etienne Minani',
                'slug' => 'minani',
                'grade' => 'Assistant',
                'specialty' => 'Droit des Affaires',
                'role' => 'Enseignant',
                'email' => 'e.minani@ub.edu.bi',
                'bio' => 'M. Etienne Minani est Enseignant à la FSEG. Spécialisé(e) en Droit des Affaires, il/elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Son engagement illustre la diversité des parcours académiques au service de la communauté universitaire.',
            ],
            [
                'category' => 'administratif',
                'name' => 'M. Alexis Butoyi',
                'slug' => 'butoyi',
                'grade' => 'Secrétaire Général',
                'specialty' => 'Administration universitaire',
                'role' => 'Secrétaire Général',
                'email' => 'a.butoyi@ub.edu.bi',
                'bio' => 'M. Alexis Butoyi assure des fonctions de Secrétaire Général à la FSEG. Son travail quotidien garantit le bon déroulement des inscriptions, du suivi pédagogique et de l’accueil des étudiants et partenaires.',
            ],
            [
                'category' => 'administratif',
                'name' => 'Mme Immaculée Nduwimana',
                'slug' => 'nduwimana',
                'grade' => 'Responsable Scolarité',
                'specialty' => 'Gestion académique',
                'role' => 'Service des Inscriptions',
                'email' => 'i.nduwimana@ub.edu.bi',
                'bio' => 'Mme Immaculée Nduwimana assure des fonctions de Service des Inscriptions à la FSEG. Son travail quotidien garantit le bon déroulement des inscriptions, du suivi pédagogique et de l’accueil des étudiants et partenaires.',
            ],
            [
                'category' => 'administratif',
                'name' => 'M. Joseph Miburo',
                'slug' => 'miburo',
                'grade' => 'Responsable Bibliothèque',
                'specialty' => 'Documentation',
                'role' => 'Bibliothécaire',
                'email' => 'j.miburo@ub.edu.bi',
                'bio' => 'M. Joseph Miburo assure des fonctions de Bibliothécaire à la FSEG. Son travail quotidien garantit le bon déroulement des inscriptions, du suivi pédagogique et de l’accueil des étudiants et partenaires.',
            ],
            ],
            'sci' => [
            [
                'category' => 'enseignant',
                'name' => 'Pr. Eric Habonimana',
                'slug' => 'eric-habonimana',
                'grade' => 'Professeur ordinaire',
                'specialty' => 'Mathématiques appliquées',
                'role' => 'Doyen de la Faculté',
                'email' => 'eric.habonimana@ub.edu.bi',
                'bio' => 'Le Pr. Habonimana dirige la FSI et anime des recherches en modélisation mathématique appliquée aux systèmes énergétiques. Il a formé plusieurs générations d’enseignants et de chercheurs.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Nadia Barakamfitiye',
                'slug' => 'nadia-baraka',
                'grade' => 'Maître de conférences',
                'specialty' => 'Génie logiciel',
                'role' => 'Chef de département Informatique',
                'email' => 'nadia.baraka@ub.edu.bi',
                'bio' => 'Spécialiste du génie logiciel et des architectures distribuées, elle encadre des projets étudiants avec les entreprises technologiques de Bujumbura.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Joseph Niyonkuru',
                'slug' => 'joseph-niyonkuru',
                'grade' => 'Maître de conférences',
                'specialty' => 'Data science',
                'role' => 'Responsable Master Data Science',
                'email' => 'joseph.niyonkuru@ub.edu.bi',
                'bio' => 'Ses travaux portent sur l’apprentissage automatique et l’analyse de données publiques. Il collabore avec des administrations pour moderniser leurs systèmes d’information.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Florence Irakoze',
                'slug' => 'florence-irakoze',
                'grade' => 'Chargée de cours',
                'specialty' => 'Réseaux et télécoms',
                'role' => 'Enseignante-chercheuse',
                'email' => 'florence.irakoze@ub.edu.bi',
                'bio' => 'Ingénieure de formation, elle enseigne les réseaux et accompagne les laboratoires de télécommunications de la faculté.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Aimé Nsabimana',
                'slug' => 'aime-nsabimana',
                'grade' => 'Maître-assistant',
                'specialty' => 'Physique de l’énergie',
                'role' => 'Responsable laboratoire ENR',
                'email' => 'aime.nsabimana@ub.edu.bi',
                'bio' => 'Ses recherches concernent le solaire photovoltaïque et l’efficacité énergétique dans les bâtiments publics.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Linda Muco',
                'slug' => 'linda-muco',
                'grade' => 'Professeur',
                'specialty' => 'Chimie analytique',
                'role' => 'Chef de département Chimie',
                'email' => 'linda.muco@ub.edu.bi',
                'bio' => 'Elle développe des méthodes d’analyse pour le contrôle qualité industriel et environnemental.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Kevin Bizimana',
                'slug' => 'kevin-bizimana',
                'grade' => 'Chargé de cours',
                'specialty' => 'Systèmes embarqués',
                'role' => 'Enseignant-chercheur',
                'email' => 'kevin.bizimana@ub.edu.bi',
                'bio' => 'Passionné par l’IoT et l’électronique embarquée, il anime des ateliers de prototypage pour les étudiants.',
            ],
            [
                'category' => 'administratif',
                'name' => 'Mme Claudine Ndayishimiye',
                'slug' => 'claudine-ndayishimiye',
                'grade' => null,
                'specialty' => null,
                'role' => 'Secrétaire académique',
                'email' => 'claudine.ndayi@ub.edu.bi',
                'bio' => 'Elle coordonne les inscriptions, les examens et l’accueil des étudiants de la FSI.',
            ],
            [
                'category' => 'administratif',
                'name' => 'M. Patrick Habonimana',
                'slug' => 'patrick-habonimana',
                'grade' => null,
                'specialty' => null,
                'role' => 'Responsable scolarité',
                'email' => 'patrick.habo@ub.edu.bi',
                'bio' => 'Garant du suivi des dossiers étudiants et de la planification des sessions d’examens.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Solange Uwimana',
                'slug' => 'solange-uwimana',
                'grade' => 'Maître de conférences',
                'specialty' => 'Statistiques',
                'role' => 'Enseignante-chercheuse',
                'email' => 'solange.uwimana@ub.edu.bi',
                'bio' => 'Ses enseignements et publications portent sur les statistiques appliquées et la biostatistique.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Thierry Manirakiza',
                'slug' => 'thierry-manirakiza',
                'grade' => 'Chargé de cours',
                'specialty' => 'Cybersécurité',
                'role' => 'Enseignant-chercheur',
                'email' => 'thierry.mani@ub.edu.bi',
                'bio' => 'Il sensibilise les étudiants aux enjeux de sécurité des systèmes d’information et anime des challenges Capture-the-Flag pédagogiques.',
            ],
            [
                'category' => 'administratif',
                'name' => 'Mme Grace Nkurunziza',
                'slug' => 'grace-nkurunziza',
                'grade' => null,
                'specialty' => null,
                'role' => 'Assistante administrative',
                'email' => 'grace.nku@ub.edu.bi',
                'bio' => 'Elle appuie le secrétariat et la communication interne de la faculté.',
            ],
            ],
            'med' => [
            [
                'category' => 'enseignant',
                'name' => 'Pr. Immaculée Ndayizeye',
                'slug' => 'immaculee-ndayizeye',
                'grade' => 'Professeur ordinaire',
                'specialty' => 'Santé publique',
                'role' => 'Doyenne de la Faculté',
                'email' => 'i.ndayizeye@ub.edu.bi',
                'bio' => 'La Pr. Ndayizeye pilote la stratégie académique de la faculté de médecine et mène des recherches sur les systèmes de santé de district.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Olga Nibigira',
                'slug' => 'olga-nibigira',
                'grade' => 'Maître de conférences',
                'specialty' => 'Médecine interne',
                'role' => 'Chef de clinique',
                'email' => 'olga.nibigira@ub.edu.bi',
                'bio' => 'Clinicienne expérimentée, elle encadre les stages hospitaliers et publie sur les maladies chroniques non transmissibles.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Placide Nduwimana',
                'slug' => 'placide-nduwimana',
                'grade' => 'Maître de conférences',
                'specialty' => 'Pédiatrie',
                'role' => 'Responsable spécialisation pédiatrie',
                'email' => 'placide.ndu@ub.edu.bi',
                'bio' => 'Ses travaux portent sur la nutrition infantile et la néonatalogie dans les contextes à ressources limitées.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Rachel Bigirimana',
                'slug' => 'rachel-bigirimana',
                'grade' => 'Chargée de cours',
                'specialty' => 'Épidémiologie',
                'role' => 'Enseignante-chercheuse',
                'email' => 'rachel.bigi@ub.edu.bi',
                'bio' => 'Elle forme les étudiants aux enquêtes épidémiologiques et collabore avec le ministère de la Santé.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Serge Niyonkuru',
                'slug' => 'serge-niyonkuru',
                'grade' => 'Maître-assistant',
                'specialty' => 'Chirurgie',
                'role' => 'Enseignant clinicien',
                'email' => 'serge.niyo@ub.edu.bi',
                'bio' => 'Chirurgien hospitalier, il assure l’enseignement du geste technique et la simulation chirurgicale.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Alice Hakizimana',
                'slug' => 'alice-hakizimana',
                'grade' => 'Chargée de cours',
                'specialty' => 'Sciences infirmières',
                'role' => 'Responsable sciences infirmières',
                'email' => 'alice.haki@ub.edu.bi',
                'bio' => 'Elle développe des modules de soins critiques et de santé communautaire pour les futurs infirmiers.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Yves Manirakiza',
                'slug' => 'yves-manirakiza',
                'grade' => 'Professeur',
                'specialty' => 'Pharmacologie',
                'role' => 'Chef de département Pharmacie',
                'email' => 'yves.mani@ub.edu.bi',
                'bio' => 'Ses recherches concernent l’usage rationnel des médicaments et la pharmacovigilance.',
            ],
            [
                'category' => 'administratif',
                'name' => 'Mme Diane Irakoze',
                'slug' => 'diane-irakoze',
                'grade' => null,
                'specialty' => null,
                'role' => 'Secrétaire académique',
                'email' => 'diane.ira@ub.edu.bi',
                'bio' => 'Elle gère les inscriptions médicales et le suivi des stages hospitaliers.',
            ],
            [
                'category' => 'administratif',
                'name' => 'M. Claude Nsabimana',
                'slug' => 'claude-nsabimana',
                'grade' => null,
                'specialty' => null,
                'role' => 'Responsable scolarité',
                'email' => 'claude.nsa@ub.edu.bi',
                'bio' => 'Il coordonne les calendriers cliniques et les examens de fin d’année.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Esther Ndayishimiye',
                'slug' => 'esther-ndayi',
                'grade' => 'Maître de conférences',
                'specialty' => 'Laboratoire médical',
                'role' => 'Responsable labo',
                'email' => 'esther.ndayi@ub.edu.bi',
                'bio' => 'Biologiste médicale, elle dirige les enseignements pratiques de diagnostic de laboratoire.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Pacifique Habiyaremye',
                'slug' => 'pacifique-habi',
                'grade' => 'Chargé de cours',
                'specialty' => 'Santé communautaire',
                'role' => 'Enseignant-chercheur',
                'email' => 'pacifique.habi@ub.edu.bi',
                'bio' => 'Il accompagne les projets de santé communautaire et les stages en districts sanitaires.',
            ],
            [
                'category' => 'administratif',
                'name' => 'Mme Carine Niyonkuru',
                'slug' => 'carine-niyo',
                'grade' => null,
                'specialty' => null,
                'role' => 'Assistante administrative',
                'email' => 'carine.niyo@ub.edu.bi',
                'bio' => 'Elle appuie l’accueil des étudiants et la communication avec les hôpitaux partenaires.',
            ],
            ],
            'agro' => [
            [
                'category' => 'enseignant',
                'name' => 'Pr. Apollinaire Ndayishimiye',
                'slug' => 'apollinaire-ndayi',
                'grade' => 'Professeur ordinaire',
                'specialty' => 'Agroécologie',
                'role' => 'Doyen de la Faculté',
                'email' => 'a.ndayi@ub.edu.bi',
                'bio' => 'Le Pr. Ndayishimiye oriente la FABI vers l’agroécologie et les partenariats avec les coopératives agricoles.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Ir. Diane Niyongabo',
                'slug' => 'diane-niyongabo',
                'grade' => 'Maître de conférences',
                'specialty' => 'Productions végétales',
                'role' => 'Chef de département Agronomie',
                'email' => 'diane.niyo@ub.edu.bi',
                'bio' => 'Agronome de terrain, elle travaille sur les itinéraires techniques du café et des cultures vivrières.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Ir. Pacifique Habiyaremye',
                'slug' => 'pacifique-habiya',
                'grade' => 'Maître de conférences',
                'specialty' => 'Environnement',
                'role' => 'Responsable Environnement',
                'email' => 'p.habiya@ub.edu.bi',
                'bio' => 'Ses recherches portent sur la restauration des bassins versants et la conservation des sols.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Ir. Esther Ndayishimiye',
                'slug' => 'esther-ndayi-agro',
                'grade' => 'Chargée de cours',
                'specialty' => 'Filières agricoles',
                'role' => 'Enseignante-chercheuse',
                'email' => 'e.ndayi@ub.edu.bi',
                'bio' => 'Elle étudie l’organisation des filières café et thé et accompagne les coopératives.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Ir. Claude Nsabimana',
                'slug' => 'claude-nsa-agro',
                'grade' => 'Maître-assistant',
                'specialty' => 'Science des sols',
                'role' => 'Responsable laboratoire sols',
                'email' => 'c.nsa@ub.edu.bi',
                'bio' => 'Pédologue, il développe des protocoles d’analyse de fertilité adaptés aux sols d’altitude.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Ir. Solange Uwimana',
                'slug' => 'solange-uwi-agro',
                'grade' => 'Chargée de cours',
                'specialty' => 'Élevage',
                'role' => 'Enseignante-chercheuse',
                'email' => 's.uwi@ub.edu.bi',
                'bio' => 'Spécialiste des productions animales, elle anime les stages en fermes modèles.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Ir. Thierry Bizimana',
                'slug' => 'thierry-bizi',
                'grade' => 'Maître de conférences',
                'specialty' => 'Ressources naturelles',
                'role' => 'Enseignant-chercheur',
                'email' => 't.bizi@ub.edu.bi',
                'bio' => 'Il travaille sur la gouvernance des ressources naturelles et les projets de bassins versants.',
            ],
            [
                'category' => 'administratif',
                'name' => 'Mme Sandrine Uwimana',
                'slug' => 'sandrine-uwi',
                'grade' => null,
                'specialty' => null,
                'role' => 'Secrétaire académique',
                'email' => 'sandrine.uwi@ub.edu.bi',
                'bio' => 'Elle assure le suivi des inscriptions et des stages de terrain de la FABI.',
            ],
            [
                'category' => 'administratif',
                'name' => 'M. David Manirakiza',
                'slug' => 'david-mani',
                'grade' => null,
                'specialty' => null,
                'role' => 'Responsable scolarité',
                'email' => 'david.mani@ub.edu.bi',
                'bio' => 'Il planifie les sorties pédagogiques et les examens pratiques.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Ir. Chantal Hakizimana',
                'slug' => 'chantal-haki-agro',
                'grade' => 'Chargée de cours',
                'specialty' => 'Irrigation',
                'role' => 'Enseignante',
                'email' => 'c.haki@ub.edu.bi',
                'bio' => 'Elle forme les étudiants aux techniques d’irrigation et à la gestion de l’eau agricole.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Ir. Eric Ndayisaba',
                'slug' => 'eric-ndayi-agro',
                'grade' => 'Maître-assistant',
                'specialty' => 'Développement rural',
                'role' => 'Enseignant-chercheur',
                'email' => 'e.ndayisaba@ub.edu.bi',
                'bio' => 'Ses séminaires portent sur les politiques rurales et l’accompagnement des communautés.',
            ],
            [
                'category' => 'administratif',
                'name' => 'Mme Aline Niyonzima',
                'slug' => 'aline-niyo-agro',
                'grade' => null,
                'specialty' => null,
                'role' => 'Assistante administrative',
                'email' => 'aline.niyo@ub.edu.bi',
                'bio' => 'Elle appuie le secrétariat et la relation avec les partenaires ruraux.',
            ],
            ],
            'hum' => [
            [
                'category' => 'enseignant',
                'name' => 'Pr. Béatrice Nkurunziza',
                'slug' => 'beatrice-nku',
                'grade' => 'Professeur ordinaire',
                'specialty' => 'Littérature francophone',
                'role' => 'Doyenne de la Faculté',
                'email' => 'b.nku@ub.edu.bi',
                'bio' => 'La Pr. Nkurunziza dirige la FLSH et publie sur les littératures de l’Afrique des Grands Lacs.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Grace Nkurunziza',
                'slug' => 'grace-nku-hum',
                'grade' => 'Maître de conférences',
                'specialty' => 'Communication',
                'role' => 'Chef de département Communication',
                'email' => 'g.nku@ub.edu.bi',
                'bio' => 'Journaliste de formation, elle anime les ateliers média et les partenariats avec la presse nationale.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Benjamin Hakizimana',
                'slug' => 'benjamin-haki',
                'grade' => 'Maître de conférences',
                'specialty' => 'Lettres modernes',
                'role' => 'Responsable Licence Lettres',
                'email' => 'b.haki@ub.edu.bi',
                'bio' => 'Il enseigne la stylistique et accompagne les ateliers d’écriture créative.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Carine Niyonkuru',
                'slug' => 'carine-niyo-hum',
                'grade' => 'Chargée de cours',
                'specialty' => 'Langues',
                'role' => 'Enseignante-chercheuse',
                'email' => 'c.niyo@ub.edu.bi',
                'bio' => 'Spécialiste de la traduction et du kirundi, elle développe des modules d’interculturalité.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Olivier Manirakiza',
                'slug' => 'olivier-mani',
                'grade' => 'Maître-assistant',
                'specialty' => 'Histoire',
                'role' => 'Responsable Histoire',
                'email' => 'o.mani@ub.edu.bi',
                'bio' => 'Ses recherches portent sur l’histoire contemporaine du Burundi et les archives orales.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Diane Irakoze',
                'slug' => 'diane-ira-hum',
                'grade' => 'Chargée de cours',
                'specialty' => 'Anthropologie',
                'role' => 'Enseignante-chercheuse',
                'email' => 'd.ira@ub.edu.bi',
                'bio' => 'Elle conduit des enquêtes ethnographiques sur les dynamiques sociales urbaines et rurales.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Samuel Ndayizeye',
                'slug' => 'samuel-ndayi',
                'grade' => 'Professeur',
                'specialty' => 'Sciences de l’éducation',
                'role' => 'Chef de département Éducation',
                'email' => 's.ndayi@ub.edu.bi',
                'bio' => 'Il forme les cadres pédagogiques et publie sur l’innovation dans l’enseignement secondaire.',
            ],
            [
                'category' => 'administratif',
                'name' => 'Mme Florence Barakamfitiye',
                'slug' => 'florence-bara',
                'grade' => null,
                'specialty' => null,
                'role' => 'Secrétaire académique',
                'email' => 'f.bara@ub.edu.bi',
                'bio' => 'Elle coordonne les inscriptions et l’accueil des étudiants de la FLSH.',
            ],
            [
                'category' => 'administratif',
                'name' => 'M. Kevin Bizimana',
                'slug' => 'kevin-bizi-hum',
                'grade' => null,
                'specialty' => null,
                'role' => 'Responsable scolarité',
                'email' => 'k.bizi@ub.edu.bi',
                'bio' => 'Il suit les examens et la planification des séminaires.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Linda Muco',
                'slug' => 'linda-muco-hum',
                'grade' => 'Maître de conférences',
                'specialty' => 'Philosophie',
                'role' => 'Enseignante-chercheuse',
                'email' => 'l.muco@ub.edu.bi',
                'bio' => 'Elle enseigne l’éthique et la philosophie politique, avec un intérêt pour les débats publics.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Aimé Nsabimana',
                'slug' => 'aime-nsa-hum',
                'grade' => 'Chargé de cours',
                'specialty' => 'Patrimoine',
                'role' => 'Enseignant-chercheur',
                'email' => 'a.nsa@ub.edu.bi',
                'bio' => 'Il collabore avec les institutions patrimoniales pour la valorisation de la mémoire collective.',
            ],
            [
                'category' => 'administratif',
                'name' => 'Mme Nadia Irakoze',
                'slug' => 'nadia-ira',
                'grade' => null,
                'specialty' => null,
                'role' => 'Assistante administrative',
                'email' => 'n.ira@ub.edu.bi',
                'bio' => 'Elle appuie le secrétariat et la communication culturelle de la faculté.',
            ],
            ],
        ];

        return $sets[$key] ?? $sets['eco'];
    }

    /** @return list<array<string, mixed>> */
    private function postSet(string $key): array
    {
        $sets = [
            'eco' => [
            [
                'type' => 'event',
                'title' => 'Journée portes ouvertes FSEG 2026',
                'slug' => 'journee-portes-ouvertes',
                'excerpt' => 'La Faculté des Sciences Économiques et de Gestion organise sa journée portes ouvertes annuelle. Venez découvrir nos programmes, rencontrer nos enseignants et poser vos questions sur les admissions. Ouvert à tous les bacheliers et leurs familles.',
                'body' => '<p>La Faculté des Sciences Économiques et de Gestion organise sa journée portes ouvertes annuelle. Venez découvrir nos programmes, rencontrer nos enseignants et poser vos questions sur les admissions. Ouvert à tous les bacheliers et leurs familles.</p><p>Cette publication illustre la vie académique de la FSEG : échanges avec les partenaires, mobilisation des enseignants et implication des étudiants. Les détails pratiques (horaires, inscriptions, contacts) sont mis à jour par le secrétariat au fur et à mesure de l’organisation.</p><p>Pour toute question, adressez-vous au secrétariat de la faculté ou consultez les annonces affichées sur le campus.</p>',
                'location' => 'Campus Mutanga — Amphithéâtre FSEG',
            ],
            [
                'type' => 'event',
                'title' => 'Conférence internationale — Développement économique en Afrique',
                'slug' => 'conference-internationale',
                'excerpt' => 'La FSEG accueille une conférence internationale réunissant des chercheurs et praticiens du développement économique en Afrique subsaharienne. Deux jours de panels, communications et ateliers.',
                'body' => '<p>La FSEG accueille une conférence internationale réunissant des chercheurs et praticiens du développement économique en Afrique subsaharienne. Deux jours de panels, communications et ateliers.</p><p>Cette publication illustre la vie académique de la FSEG : échanges avec les partenaires, mobilisation des enseignants et implication des étudiants. Les détails pratiques (horaires, inscriptions, contacts) sont mis à jour par le secrétariat au fur et à mesure de l’organisation.</p><p>Pour toute question, adressez-vous au secrétariat de la faculté ou consultez les annonces affichées sur le campus.</p>',
                'location' => 'Campus Mutanga — Amphithéâtre FSEG',
            ],
            [
                'type' => 'news',
                'title' => 'Résultats des examens du 1er semestre 2025–2026',
                'slug' => 'resultats-examens',
                'excerpt' => 'Les résultats des examens du premier semestre de l\'année académique 2025–2026 sont désormais disponibles. Les étudiants peuvent les consulter sur le portail académique en ligne de l\'Université du Burundi.',
                'body' => '<p>Les résultats des examens du premier semestre de l\'année académique 2025–2026 sont désormais disponibles. Les étudiants peuvent les consulter sur le portail académique en ligne de l\'Université du Burundi.</p><p>Cette publication illustre la vie académique de la FSEG : échanges avec les partenaires, mobilisation des enseignants et implication des étudiants. Les détails pratiques (horaires, inscriptions, contacts) sont mis à jour par le secrétariat au fur et à mesure de l’organisation.</p><p>Pour toute question, adressez-vous au secrétariat de la faculté ou consultez les annonces affichées sur le campus.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Signature d\'un partenariat avec l\'Université de Liège',
                'slug' => 'partenariat-liege',
                'excerpt' => 'La FSEG a officiellement signé un accord de coopération académique avec l\'Université de Liège (Belgique). Ce partenariat permettra des échanges d\'étudiants en master, des co-encadrements de thèses et des missions de recherche conjointes.',
                'body' => '<p>La FSEG a officiellement signé un accord de coopération académique avec l\'Université de Liège (Belgique). Ce partenariat permettra des échanges d\'étudiants en master, des co-encadrements de thèses et des missions de recherche conjointes.</p><p>Cette publication illustre la vie académique de la FSEG : échanges avec les partenaires, mobilisation des enseignants et implication des étudiants. Les détails pratiques (horaires, inscriptions, contacts) sont mis à jour par le secrétariat au fur et à mesure de l’organisation.</p><p>Pour toute question, adressez-vous au secrétariat de la faculté ou consultez les annonces affichées sur le campus.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Publication du numéro 7 de la Revue FSEG',
                'slug' => 'revue-fseg-7',
                'excerpt' => 'Le septième numéro de la Revue Scientifique de la FSEG est paru. Il contient 9 articles originaux portant sur la finance, la gestion et le développement économique au Burundi et en Afrique.',
                'body' => '<p>Le septième numéro de la Revue Scientifique de la FSEG est paru. Il contient 9 articles originaux portant sur la finance, la gestion et le développement économique au Burundi et en Afrique.</p><p>Cette publication illustre la vie académique de la FSEG : échanges avec les partenaires, mobilisation des enseignants et implication des étudiants. Les détails pratiques (horaires, inscriptions, contacts) sont mis à jour par le secrétariat au fur et à mesure de l’organisation.</p><p>Pour toute question, adressez-vous au secrétariat de la faculté ou consultez les annonces affichées sur le campus.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Prix d\'excellence académique 2025 décernés',
                'slug' => 'prix-excellence-2025',
                'excerpt' => 'Les prix d\'excellence académique de la FSEG ont été décernés lors d\'une cérémonie officielle. Vingt étudiants ont été récompensés pour leurs performances exceptionnelles au cours de l\'année académique 2024–2025.',
                'body' => '<p>Les prix d\'excellence académique de la FSEG ont été décernés lors d\'une cérémonie officielle. Vingt étudiants ont été récompensés pour leurs performances exceptionnelles au cours de l\'année académique 2024–2025.</p><p>Cette publication illustre la vie académique de la FSEG : échanges avec les partenaires, mobilisation des enseignants et implication des étudiants. Les détails pratiques (horaires, inscriptions, contacts) sont mis à jour par le secrétariat au fur et à mesure de l’organisation.</p><p>Pour toute question, adressez-vous au secrétariat de la faculté ou consultez les annonces affichées sur le campus.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Séminaire doctoral — Méthodes de recherche quantitative',
                'slug' => 'seminaire-doctoral',
                'excerpt' => 'Un séminaire de deux jours destiné aux doctorants de la FSEG a été organisé sur les méthodes quantitatives avancées en sciences économiques et de gestion. Animé par le Prof. Ndayishimiye et le Dr. Dusabimana.',
                'body' => '<p>Un séminaire de deux jours destiné aux doctorants de la FSEG a été organisé sur les méthodes quantitatives avancées en sciences économiques et de gestion. Animé par le Prof. Ndayishimiye et le Dr. Dusabimana.</p><p>Cette publication illustre la vie académique de la FSEG : échanges avec les partenaires, mobilisation des enseignants et implication des étudiants. Les détails pratiques (horaires, inscriptions, contacts) sont mis à jour par le secrétariat au fur et à mesure de l’organisation.</p><p>Pour toute question, adressez-vous au secrétariat de la faculté ou consultez les annonces affichées sur le campus.</p>',
                'location' => 'Campus Mutanga — Amphithéâtre FSEG',
            ],
            [
                'type' => 'event',
                'title' => 'Cérémonie de rentrée académique 2025–2026',
                'slug' => 'rentree-academique',
                'excerpt' => 'La FSEG a accueilli ses nouveaux étudiants lors de la cérémonie officielle de rentrée académique 2025–2026. Discours du Doyen, présentation des programmes et remise des agendas académiques.',
                'body' => '<p>La FSEG a accueilli ses nouveaux étudiants lors de la cérémonie officielle de rentrée académique 2025–2026. Discours du Doyen, présentation des programmes et remise des agendas académiques.</p><p>Cette publication illustre la vie académique de la FSEG : échanges avec les partenaires, mobilisation des enseignants et implication des étudiants. Les détails pratiques (horaires, inscriptions, contacts) sont mis à jour par le secrétariat au fur et à mesure de l’organisation.</p><p>Pour toute question, adressez-vous au secrétariat de la faculté ou consultez les annonces affichées sur le campus.</p>',
                'location' => 'Campus Mutanga — Amphithéâtre FSEG',
            ],
            [
                'type' => 'news',
                'title' => 'Lancement du projet de recherche INNOV-EMPLOI',
                'slug' => 'lancement-innov-emploi',
                'excerpt' => 'La FSEG a officiellement lancé le projet INNOV-EMPLOI, financé par l\'Union Africaine, qui vise à étudier le lien entre innovation, entrepreneuriat et création d\'emplois chez les jeunes diplômés en Afrique.',
                'body' => '<p>La FSEG a officiellement lancé le projet INNOV-EMPLOI, financé par l\'Union Africaine, qui vise à étudier le lien entre innovation, entrepreneuriat et création d\'emplois chez les jeunes diplômés en Afrique.</p><p>Cette publication illustre la vie académique de la FSEG : échanges avec les partenaires, mobilisation des enseignants et implication des étudiants. Les détails pratiques (horaires, inscriptions, contacts) sont mis à jour par le secrétariat au fur et à mesure de l’organisation.</p><p>Pour toute question, adressez-vous au secrétariat de la faculté ou consultez les annonces affichées sur le campus.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Atelier rédaction de CV et lettre de motivation',
                'slug' => 'atelier-cv-fseg',
                'excerpt' => 'Le service d’orientation organise un atelier pratique pour préparer les candidatures de stage.',
                'body' => '<p>Simulations d’entretiens, relecture de CV et conseils sur les attentes des employeurs du secteur bancaire et public.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Appel à contributions — Revue FSEG',
                'slug' => 'appel-revue-fseg',
                'excerpt' => 'Les enseignants et doctorants sont invités à soumettre des articles pour le prochain numéro.',
                'body' => '<p>Thématiques prioritaires : économie du développement, finance inclusive et gouvernance des PME. Date limite communiquée par le comité éditorial.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Forum alumni finance et banque',
                'slug' => 'forum-alumni-finance',
                'excerpt' => 'Rencontre entre diplômés du secteur financier et étudiants de master.',
                'body' => '<p>Tables rondes sur les métiers de la banque, de l’audit et de la microfinance, suivies d’un networking informel.</p>',
                'location' => 'Hall FSEG',
            ],
            ],
            'sci' => [
            [
                'type' => 'news',
                'title' => 'Hackathon Innovation FSI 2026',
                'slug' => 'hackathon-fsi-2026',
                'excerpt' => 'Les étudiants de la FSI s’affrontent 48 heures durant pour prototyper des solutions numériques utiles aux services publics.',
                'body' => '<p>Le hackathon annuel de la Faculté des Sciences et Ingénierie réunit des équipes pluridisciplinaires autour de défis proposés par des administrations et des PME technologiques.</p><p>Mentorat, ateliers techniques et jury final permettent de valoriser les meilleurs prototypes et d’ouvrir des stages.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Nouveau laboratoire de data science',
                'slug' => 'labo-data-science-fsi',
                'excerpt' => 'Inauguration d’un espace dédié à l’analyse de données, équipé de serveurs et de postes de calcul pour les masters.',
                'body' => '<p>Le nouveau laboratoire de data science offre aux étudiants un environnement de calcul adapté aux projets de machine learning et de visualisation.</p><p>Des séances encadrées et des accès libres sur réservation complètent l’offre pédagogique.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Journée portes ouvertes FSI',
                'slug' => 'portes-ouvertes-fsi',
                'excerpt' => 'Découvrez les départements, laboratoires et démonstrations d’étudiants. Ouvert aux bacheliers et familles.',
                'body' => '<p>Visites de laboratoires, démonstrations de robots et de prototypes IoT, échanges avec les enseignants : la journée portes ouvertes présente toute l’offre de la FSI.</p>',
                'location' => 'Hall principal FSI',
            ],
            [
                'type' => 'news',
                'title' => 'Partenariat télécoms et réseaux',
                'slug' => 'partenariat-telecoms-fsi',
                'excerpt' => 'Un opérateur national accueille des stagiaires et finance des équipements de laboratoire réseaux.',
                'body' => '<p>Ce partenariat renforce la formation pratique en réseaux et ouvre des opportunités de stage et d’emploi pour les diplômés.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Soutenances de master en génie logiciel',
                'slug' => 'soutenances-genie-logiciel',
                'excerpt' => 'Une session de soutenances met en avant des projets réalisés avec des partenaires locaux.',
                'body' => '<p>Les jurys évaluent la qualité technique, la documentation et l’impact potentiel des applications développées par les étudiants.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Séminaire énergies renouvelables',
                'slug' => 'seminaire-enr-fsi',
                'excerpt' => 'Experts et chercheurs présentent les avancées du solaire et de l’hydroélectricité au Burundi.',
                'body' => '<p>Conférences, posters et tables rondes autour des projets étudiants et des résultats des laboratoires ENR.</p>',
                'location' => 'Amphithéâtre sciences',
            ],
            [
                'type' => 'news',
                'title' => 'Atelier cybersécurité pour les L3',
                'slug' => 'atelier-cyber-fsi',
                'excerpt' => 'Initiation pratique à la sécurité des systèmes : bonnes pratiques, audits simples et challenges.',
                'body' => '<p>L’atelier vise à sensibiliser les étudiants aux risques courants et à les familiariser avec des outils d’analyse de vulnérabilités en environnement contrôlé.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Publication en physique appliquée',
                'slug' => 'publication-physique-fsi',
                'excerpt' => 'Une équipe de la FSI publie des résultats sur l’efficacité de panneaux solaires en altitude.',
                'body' => '<p>Les mesures réalisées sur le campus alimentent un article accepté dans une revue régionale d’énergies renouvelables.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Forum stages et emplois tech',
                'slug' => 'forum-emploi-fsi',
                'excerpt' => 'Entreprises et start-up rencontrent les étudiants pour des stages et premiers emplois.',
                'body' => '<p>Speed-meetings, ateliers CV et stands employeurs : un rendez-vous clé pour l’insertion des futurs ingénieurs et scientifiques.</p>',
                'location' => 'Cour intérieure FSI',
            ],
            [
                'type' => 'news',
                'title' => 'Olympiades de mathématiques',
                'slug' => 'olympiades-maths-fsi',
                'excerpt' => 'Compétition interne pour stimuler l’excellence en résolution de problèmes.',
                'body' => '<p>Les meilleurs candidats représentent ensuite la faculté dans des concours régionaux.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Projet IoT agriculture connectée',
                'slug' => 'projet-iot-agriculture',
                'excerpt' => 'Des étudiants conçoivent des capteurs d’humidité pour accompagner des coopératives agricoles.',
                'body' => '<p>Le projet illustre le lien entre sciences de l’ingénieur et besoins du monde rural, avec un déploiement pilote en province.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Conférence IA et éthique',
                'slug' => 'conference-ia-ethique',
                'excerpt' => 'Débat sur les usages responsables de l’intelligence artificielle dans l’administration publique.',
                'body' => '<p>Chercheurs, juristes et praticiens échangent sur la gouvernance des données et les biais algorithmiques.</p>',
                'location' => 'Salle multimédia',
            ],
            ],
            'med' => [
            [
                'type' => 'news',
                'title' => 'Campagne de vaccination étudiante',
                'slug' => 'campagne-vaccination-med',
                'excerpt' => 'La faculté et le CHU organisent une journée de vaccination et de sensibilisation sur le campus.',
                'body' => '<p>Étudiants en médecine et sciences infirmières participent à l’accueil, au conseil et au suivi des personnes vaccinées, sous supervision clinique.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Nouveau module de simulation clinique',
                'slug' => 'simulation-clinique-med',
                'excerpt' => 'Mannequins haute fidélité et scénarios d’urgence pour entraîner le geste avant le stage.',
                'body' => '<p>Le centre de simulation complète les stages hospitaliers et renforce la sécurité des patients lors des premières prises en charge.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Journée santé communautaire',
                'slug' => 'journee-sante-communaute',
                'excerpt' => 'Dépistages, conseils nutritionnels et sensibilisation dans un quartier partenaire.',
                'body' => '<p>Les étudiants de santé publique et de médecine générale mènent des activités de proximité encadrées par leurs enseignants.</p>',
                'location' => 'Centre de santé partenaire',
            ],
            [
                'type' => 'news',
                'title' => 'Résultats du concours de spécialisation',
                'slug' => 'concours-specialisation-med',
                'excerpt' => 'Publication des admis en pédiatrie, chirurgie et autres filières de spécialisation.',
                'body' => '<p>Le jury félicite les candidats et rappelle l’exigence des parcours hospitalo-universitaires.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Séminaire épidémiologie des maladies chroniques',
                'slug' => 'seminaire-epidemio-med',
                'excerpt' => 'Focus sur le diabète et l’hypertension dans les districts sanitaires.',
                'body' => '<p>Présentation de données locales et atelier de conception d’enquêtes pour les étudiants de master.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Colloque soins infirmiers',
                'slug' => 'colloque-infirmiers-med',
                'excerpt' => 'Échanges sur la qualité des soins et le rôle des infirmiers dans les urgences.',
                'body' => '<p>Communications, ateliers pratiques et témoignages de cadres de santé issus du réseau alumni.</p>',
                'location' => 'Amphithéâtre médecine',
            ],
            [
                'type' => 'news',
                'title' => 'Don de matériel de laboratoire',
                'slug' => 'don-labo-med',
                'excerpt' => 'Un partenaire international équipe le laboratoire d’analyses médicales de nouveaux automates.',
                'body' => '<p>Les étudiants bénéficieront d’une formation pratique modernisée en hématologie et biochimie.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Publication sur la santé maternelle',
                'slug' => 'publication-sante-maternelle',
                'excerpt' => 'Une équipe publie une étude sur le suivi prénatal en milieu rural.',
                'body' => '<p>Les résultats éclairent les priorités de formation et de déploiement des agents de santé communautaire.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Forum stages hospitaliers',
                'slug' => 'forum-stages-med',
                'excerpt' => 'Présentation des services d’accueil et des modalités d’évaluation des stages.',
                'body' => '<p>Chefs de service et étudiants échangent sur les attentes pédagogiques et professionnelles.</p>',
                'location' => 'Hall CHU',
            ],
            [
                'type' => 'news',
                'title' => 'Atelier pharmacovigilance',
                'slug' => 'atelier-pharmacovigilance',
                'excerpt' => 'Sensibilisation des étudiants en pharmacie au signalement des effets indésirables.',
                'body' => '<p>Cas cliniques et procédures nationales de déclaration sont présentés par des experts du ministère.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Renforcement du tutorat L1',
                'slug' => 'tutorat-l1-med',
                'excerpt' => 'Dispositif d’accompagnement pour les primo-entrants en médecine et sciences infirmières.',
                'body' => '<p>Des tuteurs seniors aident à l’organisation du travail et à la préparation des examens fondamentaux.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Conférence One Health',
                'slug' => 'conference-one-health',
                'excerpt' => 'Approche intégrée santé humaine, animale et environnementale.',
                'body' => '<p>Chercheurs de la faculté de médecine et partenaires agricoles discutent des zoonoses et de la surveillance.</p>',
                'location' => 'Auditorium santé',
            ],
            ],
            'agro' => [
            [
                'type' => 'news',
                'title' => 'Journée agroécologie paysanne',
                'slug' => 'journee-agroecologie-fabi',
                'excerpt' => 'Producteurs et étudiants échangent sur les pratiques agroécologiques et la fertilité des sols.',
                'body' => '<p>Ateliers de compostage, démonstrations de cultures associées et visite de parcelles expérimentales sur le campus.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Projet bassin versant pilote',
                'slug' => 'bassin-versant-fabi',
                'excerpt' => 'Lancement d’un projet de restauration hydrologique avec des communes partenaires.',
                'body' => '<p>Les étudiants de master contribuent aux diagnostics de terrain et au suivi des ouvrages antiérosifs.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Portes ouvertes FABI',
                'slug' => 'portes-ouvertes-fabi',
                'excerpt' => 'Découverte des filières agronomiques, des laboratoires et des fermes pédagogiques.',
                'body' => '<p>Visites guidées, dégustations de produits de la ferme universitaire et entretiens avec les enseignants.</p>',
                'location' => 'Ferme pédagogique',
            ],
            [
                'type' => 'news',
                'title' => 'Formation irrigation goutte-à-goutte',
                'slug' => 'formation-irrigation-fabi',
                'excerpt' => 'Session pratique destinée aux techniciens et étudiants sur la gestion économe de l’eau.',
                'body' => '<p>Dimensionnement, installation et maintenance des réseaux d’irrigation adaptés aux petites exploitations.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Partenariat coopérative café',
                'slug' => 'partenariat-cafe-fabi',
                'excerpt' => 'Une coopérative accueille des stagiaires pour le suivi qualité et la traçabilité.',
                'body' => '<p>Le partenariat renforce l’insertion professionnelle et nourrit les études de cas des masters filière.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Colloque sols et fertilité',
                'slug' => 'colloque-sols-fabi',
                'excerpt' => 'Chercheurs et praticiens présentent des résultats sur les sols d’altitude.',
                'body' => '<p>Sessions posters, analyses de laboratoire et recommandations pour les producteurs.</p>',
                'location' => 'Amphithéâtre FABI',
            ],
            [
                'type' => 'news',
                'title' => 'Élevage et santé animale',
                'slug' => 'elevage-sante-fabi',
                'excerpt' => 'Séminaire sur la prophylaxie et l’alimentation du troupeau en saison sèche.',
                'body' => '<p>Intervenants vétérinaires et enseignants partagent des protocoles adaptés aux petites fermes.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Publication sur le café de spécialité',
                'slug' => 'publication-cafe-fabi',
                'excerpt' => 'Étude sur la qualité organoleptique et les pratiques post-récolte au Burundi.',
                'body' => '<p>Les résultats orientent les modules de formation continue destinés aux coopératives.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Forum rural et emplois verts',
                'slug' => 'forum-emplois-verts-fabi',
                'excerpt' => 'ONG, projets et entreprises agricoles rencontrent les diplômés.',
                'body' => '<p>Offres de stages, présentations de carrières et ateliers sur l’entrepreneuriat agroécologique.</p>',
                'location' => 'Hall FABI',
            ],
            [
                'type' => 'news',
                'title' => 'Inventaire biodiversité campus',
                'slug' => 'biodiversite-campus-fabi',
                'excerpt' => 'Les étudiants d’environnement cartographient la flore et la faune du site universitaire.',
                'body' => '<p>L’inventaire servira de base à un sentier pédagogique et à des actions de sensibilisation.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Atelier transformation agroalimentaire',
                'slug' => 'atelier-transformation-fabi',
                'excerpt' => 'Initiation à la transformation des fruits et légumes pour réduire les pertes post-récolte.',
                'body' => '<p>Démonstrations pratiques et règles d’hygiène alimentaire pour les microentreprises rurales.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Conférence climat et agriculture',
                'slug' => 'conference-climat-fabi',
                'excerpt' => 'Impacts du changement climatique sur les calendriers culturaux et stratégies d’adaptation.',
                'body' => '<p>Panels avec climatologues, agronomes et représentants paysans.</p>',
                'location' => 'Salle de conférence',
            ],
            ],
            'hum' => [
            [
                'type' => 'news',
                'title' => 'Salon du livre universitaire',
                'slug' => 'salon-livre-flsh',
                'excerpt' => 'Auteurs, éditeurs et étudiants célèbrent la production littéraire nationale.',
                'body' => '<p>Dédicaces, tables rondes et ateliers d’écriture animent trois jours de programmation culturelle sur le campus.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Nouvelle revue étudiante de lettres',
                'slug' => 'revue-lettres-flsh',
                'excerpt' => 'Lancement d’une revue numérique pour publier essais, poèmes et critiques.',
                'body' => '<p>Le comité éditorial étudiant est accompagné par des enseignants de lettres et de communication.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Journée portes ouvertes FLSH',
                'slug' => 'portes-ouvertes-flsh',
                'excerpt' => 'Présentation des filières, lectures publiques et démonstrations de traduction.',
                'body' => '<p>Les familles découvrent les débouchés en enseignement, médias et médiation culturelle.</p>',
                'location' => 'Hall FLSH',
            ],
            [
                'type' => 'news',
                'title' => 'Atelier kirundi et traduction',
                'slug' => 'atelier-kirundi-flsh',
                'excerpt' => 'Renforcement des compétences en langues nationales pour les étudiants de licence.',
                'body' => '<p>Exercices de traduction littéraire et administrative, avec retours d’intervenants professionnels.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Colloque histoire des Grands Lacs',
                'slug' => 'colloque-histoire-flsh',
                'excerpt' => 'Historiens régionaux débattent des sources et des mémoires contemporaines.',
                'body' => '<p>Communications scientifiques et atelier archives orales pour les doctorants.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Nuit de la poésie',
                'slug' => 'nuit-poesie-flsh',
                'excerpt' => 'Lectures, performances et musique autour de la création contemporaine.',
                'body' => '<p>Étudiants et alumni partagent leurs textes dans une ambiance conviviale ouverte au public.</p>',
                'location' => 'Cour intérieure',
            ],
            [
                'type' => 'news',
                'title' => 'Partenariat médias nationaux',
                'slug' => 'partenariat-medias-flsh',
                'excerpt' => 'Rédactions accueillent des stagiaires en journalisme et communication.',
                'body' => '<p>Le dispositif combine immersion professionnelle et séances de debriefing pédagogique.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Publication en anthropologie urbaine',
                'slug' => 'publication-anthropologie-flsh',
                'excerpt' => 'Enquête sur les sociabilités des jeunes dans les quartiers de Bujumbura.',
                'body' => '<p>L’étude enrichit les séminaires de master et les débats sur les politiques culturelles.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Forum métiers des humanités',
                'slug' => 'forum-metiers-flsh',
                'excerpt' => 'Enseignement, culture, médias et coopération : panorama des carrières.',
                'body' => '<p>Alumni et recruteurs présentent leurs parcours et conseillent les étudiants.</p>',
                'location' => 'Amphithéâtre FLSH',
            ],
            [
                'type' => 'news',
                'title' => 'Formation didactique pour futurs enseignants',
                'slug' => 'formation-didactique-flsh',
                'excerpt' => 'Modules pratiques pour les étudiants se destinant à l’enseignement secondaire.',
                'body' => '<p>Gestion de classe, évaluation et usage du numérique éducatif sont au programme.</p>',
            ],
            [
                'type' => 'news',
                'title' => 'Exposition patrimoine photographique',
                'slug' => 'exposition-patrimoine-flsh',
                'excerpt' => 'Archives photographiques commentées par des historiens et étudiants.',
                'body' => '<p>L’exposition itinérante sensibilise le public à la préservation de la mémoire visuelle.</p>',
            ],
            [
                'type' => 'event',
                'title' => 'Conférence philosophie et cité',
                'slug' => 'conference-philosophie-flsh',
                'excerpt' => 'Débat public sur l’éthique, la citoyenneté et les médias.',
                'body' => '<p>Philosophes et journalistes interrogent le rôle des humanités dans l’espace public.</p>',
                'location' => 'Salle des actes',
            ],
            ],
        ];

        return $sets[$key] ?? $sets['eco'];
    }

    /** @return list<array<string, mixed>> */
    private function labSet(string $key): array
    {
        $sets = [
            'eco' => [
            [
                'abbr' => 'LEA',
                'name' => 'Laboratoire d\'Économie Appliquée',
                'slug' => 'lea',
                'icon' => 'bi-bar-chart-line',
                'description' => 'Le LEA se consacre à l\'analyse quantitative et qualitative des problématiques économiques burundaises et régionales. Il développe des modèles économétriques pour éclairer les décisions de politique économique. Les résultats nourrissent les enseignements de master et doctorat et sont diffusés auprès des décideurs.',
                'themes' => [
                    'Croissance économique',
                    'Commerce international',
                    'Pauvreté et inégalités',
                    'Économétrie appliquée',
                ],
                'researchers' => 8,
            ],
            [
                'abbr' => 'CRGD',
                'name' => 'Centre de Recherche en Gestion et Développement',
                'slug' => 'crgd',
                'icon' => 'bi-building',
                'description' => 'Le CRGD étudie les pratiques managériales, la gouvernance des organisations et les stratégies de développement des entreprises dans le contexte africain. Les résultats nourrissent les enseignements de master et doctorat et sont diffusés auprès des décideurs.',
                'themes' => [
                    'Gouvernance d\'entreprise',
                    'Entrepreneuriat',
                    'Management stratégique',
                    'RSE',
                ],
                'researchers' => 6,
            ],
            [
                'abbr' => 'LFC',
                'name' => 'Laboratoire de Finance et Comptabilité',
                'slug' => 'lfc',
                'icon' => 'bi-cash-stack',
                'description' => 'Le LFC mène des recherches en finance d\'entreprise, marchés financiers africains, normalisation comptable et gouvernance financière des organisations. Les résultats nourrissent les enseignements de master et doctorat et sont diffusés auprès des décideurs.',
                'themes' => [
                    'Finance d\'entreprise',
                    'Marchés financiers',
                    'Audit et contrôle',
                    'Normes IFRS',
                ],
                'researchers' => 7,
            ],
            [
                'abbr' => 'URPP',
                'name' => 'Unité de Recherche en Politiques Publiques',
                'slug' => 'urpp',
                'icon' => 'bi-briefcase',
                'description' => 'L\'URPP analyse les politiques publiques économiques, sociales et fiscales, et produit des recommandations pour les décideurs publics burundais. Les résultats nourrissent les enseignements de master et doctorat et sont diffusés auprès des décideurs.',
                'themes' => [
                    'Fiscalité et budget',
                    'Politiques sociales',
                    'Décentralisation',
                    'Évaluation des politiques',
                ],
                'researchers' => 5,
            ],
            ],
            'sci' => [
            [
                'abbr' => 'LIM',
                'name' => 'Laboratoire d’Informatique et Modélisation',
                'slug' => 'lim',
                'icon' => 'bi-cpu',
                'description' => 'Le LIM développe des modèles numériques, des prototypes logiciels et des outils de simulation pour les administrations et les entreprises. Les doctorants y conduisent des thèses en génie logiciel et data science.',
                'themes' => [
                    'Génie logiciel',
                    'Data science',
                    'Simulation',
                ],
                'researchers' => 14,
            ],
            [
                'abbr' => 'LENR',
                'name' => 'Laboratoire Énergies et Réseaux',
                'slug' => 'lenr',
                'icon' => 'bi-lightning',
                'description' => 'Le LENR étudie le solaire, l’hydroélectricité et l’efficacité énergétique, avec des bancs d’essai et des mesures de terrain sur le campus et en province.',
                'themes' => [
                    'Solaire',
                    'Hydroélectricité',
                    'Efficacité énergétique',
                ],
                'researchers' => 9,
            ],
            [
                'abbr' => 'LPA',
                'name' => 'Laboratoire de Physique Appliquée',
                'slug' => 'lpa',
                'icon' => 'bi-atom',
                'description' => 'Le LPA combine instrumentation, optique et caractérisation de matériaux pour soutenir l’enseignement expérimental et la recherche appliquée.',
                'themes' => [
                    'Instrumentation',
                    'Matériaux',
                    'Optique',
                ],
                'researchers' => 7,
            ],
            ],
            'med' => [
            [
                'abbr' => 'LESP',
                'name' => 'Laboratoire d’Épidémiologie et Santé Publique',
                'slug' => 'lesp',
                'icon' => 'bi-clipboard-pulse',
                'description' => 'Le LESP produit des analyses épidémiologiques et évalue des interventions de santé publique en collaboration avec les districts sanitaires.',
                'themes' => [
                    'Épidémiologie',
                    'Surveillance',
                    'Évaluation',
                ],
                'researchers' => 11,
            ],
            [
                'abbr' => 'LBM',
                'name' => 'Laboratoire de Biologie Médicale',
                'slug' => 'lbm',
                'icon' => 'bi-eyedropper',
                'description' => 'Le LBM forme aux techniques de diagnostic et mène des recherches sur les maladies infectieuses et le contrôle qualité des analyses.',
                'themes' => [
                    'Hématologie',
                    'Microbiologie',
                    'Biosécurité',
                ],
                'researchers' => 8,
            ],
            [
                'abbr' => 'CSC',
                'name' => 'Centre de Simulation Clinique',
                'slug' => 'csc',
                'icon' => 'bi-heart-pulse',
                'description' => 'Le centre entraîne les étudiants aux gestes d’urgence et aux scénarios cliniques avant les stages hospitaliers, avec mannequins et débriefing structuré.',
                'themes' => [
                    'Simulation',
                    'Urgences',
                    'Pédagogie clinique',
                ],
                'researchers' => 6,
            ],
            ],
            'agro' => [
            [
                'abbr' => 'LAE',
                'name' => 'Laboratoire d’Agroécologie Expérimentale',
                'slug' => 'lae',
                'icon' => 'bi-flower1',
                'description' => 'Le LAE expérimente des systèmes agroécologiques, la fertilité des sols et les associations culturales sur les parcelles du campus.',
                'themes' => [
                    'Agroécologie',
                    'Fertilité',
                    'Cultures associées',
                ],
                'researchers' => 10,
            ],
            [
                'abbr' => 'LRN',
                'name' => 'Laboratoire Ressources Naturelles',
                'slug' => 'lrn',
                'icon' => 'bi-water',
                'description' => 'Le LRN suit la restauration des bassins versants, la biodiversité et la gouvernance des ressources naturelles avec des partenaires communaux.',
                'themes' => [
                    'Bassins versants',
                    'Biodiversité',
                    'Gouvernance',
                ],
                'researchers' => 8,
            ],
            [
                'abbr' => 'LSA',
                'name' => 'Laboratoire Sciences Animales',
                'slug' => 'lsa',
                'icon' => 'bi-emoji-smile',
                'description' => 'Le LSA travaille sur la nutrition animale, la santé du troupeau et la valorisation des produits d’élevage pour les petites fermes.',
                'themes' => [
                    'Nutrition animale',
                    'Santé du troupeau',
                    'Valorisation',
                ],
                'researchers' => 6,
            ],
            ],
            'hum' => [
            [
                'abbr' => 'CRL',
                'name' => 'Centre de Recherche en Littératures',
                'slug' => 'crl',
                'icon' => 'bi-book',
                'description' => 'Le CRL anime des séminaires sur les littératures francophones et africaines, et accompagne les revues étudiantes et les ateliers d’écriture.',
                'themes' => [
                    'Littératures africaines',
                    'Critique',
                    'Écriture',
                ],
                'researchers' => 9,
            ],
            [
                'abbr' => 'LHGL',
                'name' => 'Laboratoire Histoire des Grands Lacs',
                'slug' => 'lhgl',
                'icon' => 'bi-hourglass',
                'description' => 'Le LHGL collecte archives et témoignages oraux pour documenter l’histoire contemporaine du Burundi et de la région.',
                'themes' => [
                    'Archives',
                    'Histoire orale',
                    'Mémoire',
                ],
                'researchers' => 7,
            ],
            [
                'abbr' => 'LCOM',
                'name' => 'Laboratoire Communication et Médias',
                'slug' => 'lcom',
                'icon' => 'bi-broadcast',
                'description' => 'Le LCOM forme aux pratiques médiatiques et étudie les dynamiques de l’information numérique et de la communication institutionnelle.',
                'themes' => [
                    'Médias',
                    'Communication',
                    'Numérique',
                ],
                'researchers' => 8,
            ],
            ],
        ];

        return $sets[$key] ?? $sets['eco'];
    }

    /** @return list<array<string, mixed>> */
    private function publicationSet(string $key): array
    {
        $sets = [
            'eco' => [
            [
                'year' => 2025,
                'title' => 'Dynamiques de la croissance économique au Burundi : une analyse économétrique 2000–2023',
                'authors' => 'Ndayishimiye J.-B., Hakizimana M.-C.',
                'journal' => 'Revue Africaine d\'Économie et de Gestion',
            ],
            [
                'year' => 2025,
                'title' => 'Gouvernance d\'entreprise et performance financière dans les PME burundaises',
                'authors' => 'Bigirimana F., Nkurunziza P.',
                'journal' => 'Journal of African Business Studies',
            ],
            [
                'year' => 2024,
                'title' => 'Impact des politiques fiscales sur l\'investissement privé au Burundi',
                'authors' => 'Ntakarutimana A., Butoyi A.',
                'journal' => 'Revue Économique d\'Afrique Centrale',
            ],
            [
                'year' => 2024,
                'title' => 'Inclusion financière et réduction de la pauvreté en Afrique subsaharienne',
                'authors' => 'Hakizimana M.-C., Ndayishimiye J.-B.',
                'journal' => 'African Finance Journal',
            ],
            [
                'year' => 2023,
                'title' => 'Pratiques de contrôle de gestion dans les entreprises publiques burundaises',
                'authors' => 'Bigirimana F.',
                'journal' => 'Comptabilité, Contrôle, Audit — Afrique',
            ],
            [
                'year' => 2023,
                'title' => 'Décentralisation budgétaire et développement local : cas du Burundi',
                'authors' => 'Ntakarutimana A.',
                'journal' => 'Revue d\'Économie du Développement',
            ],
            ],
            'sci' => [
            [
                'year' => 2025,
                'title' => 'Modélisation de la production solaire en altitude : cas du campus universitaire',
                'authors' => 'Habonimana E., Nsabimana A.',
                'journal' => 'Revue Africaine des Énergies',
            ],
            [
                'year' => 2025,
                'title' => 'Architectures microservices pour les services publics numériques',
                'authors' => 'Barakamfitiye N., Bizimana K.',
                'journal' => 'Journal of African Software Engineering',
            ],
            [
                'year' => 2024,
                'title' => 'Apprentissage automatique appliqué aux données de consommation électrique',
                'authors' => 'Niyonkuru J., Uwimana S.',
                'journal' => 'African Data Science Review',
            ],
            [
                'year' => 2024,
                'title' => 'Caractérisation optique de matériaux pour l’instrumentation pédagogique',
                'authors' => 'Nsabimana A., Muco L.',
                'journal' => 'Revue de Physique Appliquée',
            ],
            [
                'year' => 2023,
                'title' => 'Sécurisation des réseaux locaux universitaires : retour d’expérience',
                'authors' => 'Irakoze F., Manirakiza T.',
                'journal' => 'Cahiers de Cybersécurité Régionale',
            ],
            [
                'year' => 2023,
                'title' => 'Statistiques spatiales pour le suivi environnemental',
                'authors' => 'Uwimana S., Habonimana E.',
                'journal' => 'Revue de Statistique Appliquée',
            ],
            ],
            'med' => [
            [
                'year' => 2025,
                'title' => 'Surveillance des maladies chroniques dans trois districts sanitaires',
                'authors' => 'Ndayizeye I., Bigirimana R.',
                'journal' => 'Revue Burundaise de Santé Publique',
            ],
            [
                'year' => 2025,
                'title' => 'Impact d’un module de simulation sur la confiance des étudiants infirmiers',
                'authors' => 'Hakizimana A., Nibigira O.',
                'journal' => 'African Journal of Nursing Education',
            ],
            [
                'year' => 2024,
                'title' => 'Suivi prénatal et accès aux soins en milieu rural',
                'authors' => 'Habiyaremye P., Ndayizeye I.',
                'journal' => 'Santé et Développement',
            ],
            [
                'year' => 2024,
                'title' => 'Pharmacovigilance : analyse des signalements d’effets indésirables',
                'authors' => 'Manirakiza Y., Ndayishimiye E.',
                'journal' => 'Revue de Pharmacie Africaine',
            ],
            [
                'year' => 2023,
                'title' => 'Nutrition infantile et pratiques communautaires',
                'authors' => 'Nduwimana P., Bigirimana R.',
                'journal' => 'Cahiers de Pédiatrie Tropicale',
            ],
            [
                'year' => 2023,
                'title' => 'Qualité des analyses d’hématologie en laboratoire de district',
                'authors' => 'Ndayishimiye E., Nibigira O.',
                'journal' => 'Biologie Médicale Afrique',
            ],
            ],
            'agro' => [
            [
                'year' => 2025,
                'title' => 'Associations culturales et fertilité des sols d’altitude',
                'authors' => 'Ndayishimiye A., Nsabimana C.',
                'journal' => 'Revue Africaine d’Agroécologie',
            ],
            [
                'year' => 2025,
                'title' => 'Traçabilité et qualité du café de spécialité au Burundi',
                'authors' => 'Ndayishimiye E., Niyongabo D.',
                'journal' => 'Journal of African Coffee Studies',
            ],
            [
                'year' => 2024,
                'title' => 'Restauration de bassins versants : enseignements d’un projet pilote',
                'authors' => 'Habiyaremye P., Bizimana T.',
                'journal' => 'Environnement et Développement',
            ],
            [
                'year' => 2024,
                'title' => 'Irrigation goutte-à-goutte pour petites exploitations',
                'authors' => 'Hakizimana C., Niyongabo D.',
                'journal' => 'Cahiers d’Hydraulique Agricole',
            ],
            [
                'year' => 2023,
                'title' => 'Alimentation du troupeau en saison sèche',
                'authors' => 'Uwimana S., Ndayishimiye A.',
                'journal' => 'Revue des Productions Animales',
            ],
            [
                'year' => 2023,
                'title' => 'Gouvernance locale des ressources naturelles',
                'authors' => 'Bizimana T., Habiyaremye P.',
                'journal' => 'Études Rurales Africaines',
            ],
            ],
            'hum' => [
            [
                'year' => 2025,
                'title' => 'Voix francophones des Grands Lacs : corpus et lectures',
                'authors' => 'Nkurunziza B., Hakizimana B.',
                'journal' => 'Revue de Littératures Africaines',
            ],
            [
                'year' => 2025,
                'title' => 'Archives orales et écriture de l’histoire contemporaine',
                'authors' => 'Manirakiza O., Nsabimana A.',
                'journal' => 'Cahiers d’Histoire Régionale',
            ],
            [
                'year' => 2024,
                'title' => 'Pratiques journalistiques et éthique des médias numériques',
                'authors' => 'Nkurunziza G., Niyonkuru C.',
                'journal' => 'Communication et Société',
            ],
            [
                'year' => 2024,
                'title' => 'Didactique des langues nationales à l’université',
                'authors' => 'Niyonkuru C., Ndayizeye S.',
                'journal' => 'Revue des Sciences de l’Éducation',
            ],
            [
                'year' => 2023,
                'title' => 'Sociabilités urbaines des jeunes à Bujumbura',
                'authors' => 'Irakoze D., Nkurunziza B.',
                'journal' => 'Anthropologie Urbaine',
            ],
            [
                'year' => 2023,
                'title' => 'Philosophie et débat public : repères pour la cité',
                'authors' => 'Muco L., Nkurunziza B.',
                'journal' => 'Éthique et Société',
            ],
            ],
        ];

        return $sets[$key] ?? $sets['eco'];
    }

    /** @return list<array<string, mixed>> */
    private function projectSet(string $key): array
    {
        $sets = [
            'eco' => [
            [
                'title' => 'Développement économique inclusif au Burundi',
                'description' => 'Analyse des déterminants du développement économique inclusif au Burundi Le projet mobilise enseignants-chercheurs et doctorants de la FSEG en partenariat avec les bailleurs.',
                'funder' => 'Union Européenne',
                'start' => 2023,
                'end' => 2026,
            ],
            [
                'title' => 'Inclusion financière en milieu rural',
                'description' => 'Inclusion financière et accès aux services bancaires pour les populations rurales Le projet mobilise enseignants-chercheurs et doctorants de la FSEG en partenariat avec les bailleurs.',
                'funder' => 'Banque Mondiale',
                'start' => 2024,
                'end' => 2026,
            ],
            [
                'title' => 'Impact des politiques agricoles sur la sécurité alimentaire',
                'description' => 'Évaluation de l\'impact des politiques agricoles sur la sécurité alimentaire Le projet mobilise enseignants-chercheurs et doctorants de la FSEG en partenariat avec les bailleurs.',
                'funder' => 'FAO – Burundi',
                'start' => 2022,
                'end' => 2025,
            ],
            [
                'title' => 'Gouvernance et accompagnement des PME',
                'description' => 'Renforcement de la gouvernance des PME burundaises par la formation et l\'accompagnement Le projet mobilise enseignants-chercheurs et doctorants de la FSEG en partenariat avec les bailleurs.',
                'funder' => 'Coopération belge (ARES)',
                'start' => 2023,
                'end' => 2027,
            ],
            [
                'title' => 'Réforme fiscale et développement durable',
                'description' => 'Réforme fiscale et financement du développement durable au Burundi Le projet mobilise enseignants-chercheurs et doctorants de la FSEG en partenariat avec les bailleurs.',
                'funder' => 'PNUD Burundi',
                'start' => 2024,
                'end' => 2026,
            ],
            [
                'title' => 'Innovation, entrepreneuriat et emploi des jeunes',
                'description' => 'Innovation, entrepreneuriat et création d\'emplois chez les jeunes diplômés Le projet mobilise enseignants-chercheurs et doctorants de la FSEG en partenariat avec les bailleurs.',
                'funder' => 'Union Africaine',
                'start' => 2025,
                'end' => 2028,
            ],
            ],
            'sci' => [
            [
                'title' => 'Campus solaire intelligent',
                'description' => 'Déploiement et suivi de micro-réseaux solaires pour alimenter laboratoires et salles de cours.',
                'funder' => 'Coopération belgo-burundaise',
                'start' => 2023,
                'end' => 2026,
            ],
            [
                'title' => 'Open Data administration',
                'description' => 'Appui à l’ouverture et à la visualisation de données publiques pour les services municipaux.',
                'funder' => 'Banque Mondiale',
                'start' => 2024,
                'end' => 2027,
            ],
            [
                'title' => 'IoT rural',
                'description' => 'Capteurs bas coût pour l’agriculture et le suivi hydrologique en province.',
                'funder' => 'Fonds innovation UB',
                'start' => 2024,
                'end' => 2026,
            ],
            [
                'title' => 'Cybersécurité campus',
                'description' => 'Renforcement des capacités de détection et de réponse aux incidents sur le réseau universitaire.',
                'funder' => 'Partenaire télécoms',
                'start' => 2025,
                'end' => 2027,
            ],
            ],
            'med' => [
            [
                'title' => 'Surveillance épidémiologique de district',
                'description' => 'Renforcement des outils de collecte et d’analyse pour trois districts sanitaires.',
                'funder' => 'OMS / Ministère de la Santé',
                'start' => 2023,
                'end' => 2026,
            ],
            [
                'title' => 'Simulation clinique étendue',
                'description' => 'Équipement et formation des formateurs du centre de simulation.',
                'funder' => 'Coopération française',
                'start' => 2024,
                'end' => 2027,
            ],
            [
                'title' => 'Santé maternelle rurale',
                'description' => 'Évaluation d’interventions communautaires de suivi prénatal.',
                'funder' => 'UNICEF Burundi',
                'start' => 2022,
                'end' => 2025,
            ],
            [
                'title' => 'One Health zoonoses',
                'description' => 'Surveillance intégrée des zoonoses à l’interface homme-animal-environnement.',
                'funder' => 'FAO / OMS',
                'start' => 2025,
                'end' => 2028,
            ],
            ],
            'agro' => [
            [
                'title' => 'Agroécologie paysanne',
                'description' => 'Accompagnement de coopératives vers des pratiques agroécologiques durables.',
                'funder' => 'Union Européenne',
                'start' => 2023,
                'end' => 2026,
            ],
            [
                'title' => 'Bassins versants vivants',
                'description' => 'Restauration hydrologique et antiérosion avec des communes partenaires.',
                'funder' => 'PNUD Burundi',
                'start' => 2024,
                'end' => 2027,
            ],
            [
                'title' => 'Café de spécialité',
                'description' => 'Amélioration de la qualité post-récolte et de la traçabilité.',
                'funder' => 'ARES / partenaires',
                'start' => 2022,
                'end' => 2025,
            ],
            [
                'title' => 'Eau agricole',
                'description' => 'Diffusion de techniques d’irrigation économes pour petites exploitations.',
                'funder' => 'Coopération néerlandaise',
                'start' => 2025,
                'end' => 2028,
            ],
            ],
            'hum' => [
            [
                'title' => 'Archives orales des Grands Lacs',
                'description' => 'Collecte, numérisation et valorisation de témoignages oraux.',
                'funder' => 'UNESCO',
                'start' => 2023,
                'end' => 2026,
            ],
            [
                'title' => 'Médias et citoyenneté',
                'description' => 'Formation de journalistes étudiants à l’éthique et au fact-checking.',
                'funder' => 'Fondation médias',
                'start' => 2024,
                'end' => 2026,
            ],
            [
                'title' => 'Littératures en classe',
                'description' => 'Ressources didactiques pour l’enseignement des lettres au secondaire.',
                'funder' => 'Ministère de l’Éducation',
                'start' => 2022,
                'end' => 2025,
            ],
            [
                'title' => 'Patrimoine photographique',
                'description' => 'Inventaire et exposition d’archives photographiques nationales.',
                'funder' => 'Coopération culturelle',
                'start' => 2025,
                'end' => 2027,
            ],
            ],
        ];

        return $sets[$key] ?? $sets['eco'];
    }

    /** @return list<array<string, mixed>> */
    private function timelineSet(string $key): array
    {
        $sets = [
            'eco' => [
            [
                'year' => 1973,
                'title' => 'Création de la Faculté',
                'description' => 'Fondation de la Faculté des Sciences Économiques au sein de l\'Université du Burundi, avec les premières promotions en économie générale. Cette étape marque un jalon dans la construction de l’identité académique de la faculté.',
            ],
            [
                'year' => 1985,
                'title' => 'Expansion des programmes',
                'description' => 'Introduction des filières de gestion des entreprises et de comptabilité, répondant à la demande croissante du secteur privé burundais. Cette étape marque un jalon dans la construction de l’identité académique de la faculté.',
            ],
            [
                'year' => 1998,
                'title' => 'Ouverture du programme de Master',
                'description' => 'Lancement des premiers programmes de master permettant une spécialisation approfondie et le développement de la recherche scientifique. Cette étape marque un jalon dans la construction de l’identité académique de la faculté.',
            ],
            [
                'year' => 2010,
                'title' => 'Programme doctoral et laboratoires',
                'description' => 'Création des laboratoires de recherche et ouverture du programme de doctorat, consacrant la FSEG comme centre de recherche reconnu. Cette étape marque un jalon dans la construction de l’identité académique de la faculté.',
            ],
            [
                'year' => 2020,
                'title' => 'Réforme pédagogique et numérique',
                'description' => 'Modernisation des curricula, introduction des outils numériques et renforcement des partenariats internationaux avec des universités africaines et européennes. Cette étape marque un jalon dans la construction de l’identité académique de la faculté.',
            ],
            [
                'year' => 2024,
                'title' => '51 ans d\'excellence',
                'description' => 'La FSEG célèbre plus de cinq décennies de formation et de recherche, avec plus de 15 000 diplômés actifs dans les secteurs public et privé. Cette étape marque un jalon dans la construction de l’identité académique de la faculté.',
            ],
            ],
            'sci' => [
            [
                'year' => 1975,
                'title' => 'Création des départements scientifiques',
                'description' => 'Structuration des enseignements de mathématiques, physique et chimie au sein de l’Université du Burundi.',
            ],
            [
                'year' => 1992,
                'title' => 'Informatique et laboratoires',
                'description' => 'Ouverture de la filière informatique et renforcement des laboratoires expérimentaux.',
            ],
            [
                'year' => 2005,
                'title' => 'Masters technologiques',
                'description' => 'Lancement des premiers masters en génie et sciences appliquées.',
            ],
            [
                'year' => 2014,
                'title' => 'Alignement LMD',
                'description' => 'Réorganisation complète des parcours Licence-Master-Doctorat en sciences et ingénierie.',
            ],
            [
                'year' => 2021,
                'title' => 'Hub innovation',
                'description' => 'Création d’un espace prototypage et partenariats avec le secteur numérique.',
            ],
            [
                'year' => 2025,
                'title' => 'Data science et ENR',
                'description' => 'Consolidation des masters Data Science et Énergies renouvelables avec laboratoires dédiés.',
            ],
            ],
            'med' => [
            [
                'year' => 1968,
                'title' => 'Origines de la formation médicale',
                'description' => 'Premiers enseignements médicaux rattachés à l’université et aux hôpitaux de référence.',
            ],
            [
                'year' => 1980,
                'title' => 'Structuration hospitalo-universitaire',
                'description' => 'Organisation des stages cliniques et des départements fondamentaux.',
            ],
            [
                'year' => 1999,
                'title' => 'Sciences infirmières et santé publique',
                'description' => 'Ouverture de filières professionnalisantes en soins et santé des populations.',
            ],
            [
                'year' => 2011,
                'title' => 'Spécialisations cliniques',
                'description' => 'Renforcement des programmes de spécialisation en pédiatrie et chirurgie.',
            ],
            [
                'year' => 2018,
                'title' => 'Simulation clinique',
                'description' => 'Mise en place du centre de simulation pour préparer les gestes avant le stage.',
            ],
            [
                'year' => 2024,
                'title' => 'One Health et épidémiologie',
                'description' => 'Accélération des masters et projets sur la surveillance et l’approche One Health.',
            ],
            ],
            'agro' => [
            [
                'year' => 1977,
                'title' => 'École d’agronomie',
                'description' => 'Fondation des formations agronomiques pour accompagner le développement rural.',
            ],
            [
                'year' => 1988,
                'title' => 'Environnement et sols',
                'description' => 'Création d’unités dédiées à l’environnement et à la science des sols.',
            ],
            [
                'year' => 2003,
                'title' => 'Masters ruraux',
                'description' => 'Ouverture de masters en développement rural et ressources naturelles.',
            ],
            [
                'year' => 2013,
                'title' => 'Agroécologie',
                'description' => 'Intégration de l’agroécologie dans les curricula et les parcelles expérimentales.',
            ],
            [
                'year' => 2019,
                'title' => 'Ferme pédagogique',
                'description' => 'Modernisation de la ferme universitaire et des stages en coopératives.',
            ],
            [
                'year' => 2025,
                'title' => 'Climat et filières',
                'description' => 'Renforcement des projets climat, café de spécialité et irrigation économe.',
            ],
            ],
            'hum' => [
            [
                'year' => 1964,
                'title' => 'Lettres et sciences humaines',
                'description' => 'Premières chaires de lettres, histoire et philosophie à l’université.',
            ],
            [
                'year' => 1982,
                'title' => 'Langues et communication',
                'description' => 'Développement des filières langues et ouverture vers les métiers de l’information.',
            ],
            [
                'year' => 1996,
                'title' => 'Recherche en histoire régionale',
                'description' => 'Structuration des travaux sur l’histoire des Grands Lacs et les archives.',
            ],
            [
                'year' => 2008,
                'title' => 'LMD humanités',
                'description' => 'Réforme des parcours Licence-Master-Doctorat en sciences humaines.',
            ],
            [
                'year' => 2017,
                'title' => 'Médias numériques',
                'description' => 'Ateliers radio, web et fact-checking intégrés à la formation en communication.',
            ],
            [
                'year' => 2024,
                'title' => 'Patrimoine et création',
                'description' => 'Accélération des projets patrimoniaux, revues étudiantes et salons du livre.',
            ],
            ],
        ];

        return $sets[$key] ?? $sets['eco'];
    }

    /** @return list<array<string, mixed>> */
    private function alumniSet(string $key): array
    {
        $sets = [
            'eco' => [
            [
                'name' => 'Emmanuel Nzeyimana',
                'slug' => 'alumni-emmanuel-nzeyimana',
                'role' => 'Directeur Financier',
                'organization' => 'Banque de Crédit de Bujumbura (BCB)',
                'year' => 2008,
                'bio' => 'Emmanuel Nzeyimana (promotion 2008) occupe aujourd’hui le poste de Directeur Financier au sein de Banque de Crédit de Bujumbura (BCB). Son parcours illustre la diversité des débouchés ouverts par la FSEG dans la finance, le développement et l’entrepreneuriat.',
            ],
            [
                'name' => 'Claudine Irakoze',
                'slug' => 'alumni-claudine-irakoze',
                'role' => 'Économiste principale',
                'organization' => 'Banque Africaine de Développement',
                'year' => 2012,
                'bio' => 'Claudine Irakoze (promotion 2012) occupe aujourd’hui le poste de Économiste principale au sein de Banque Africaine de Développement. Son parcours illustre la diversité des débouchés ouverts par la FSEG dans la finance, le développement et l’entrepreneuriat.',
            ],
            [
                'name' => 'Désiré Ntibonera',
                'slug' => 'alumni-desire-ntibonera',
                'role' => 'Fondateur & CEO',
                'organization' => 'AgriBurundi SA',
                'year' => 2005,
                'bio' => 'Désiré Ntibonera (promotion 2005) occupe aujourd’hui le poste de Fondateur & CEO au sein de AgriBurundi SA. Son parcours illustre la diversité des débouchés ouverts par la FSEG dans la finance, le développement et l’entrepreneuriat.',
            ],
            [
                'name' => 'Chantal Nizigiyimana',
                'slug' => 'alumni-chantal-nizigiyimana',
                'role' => 'Responsable Audit',
                'organization' => 'PwC Rwanda-Burundi',
                'year' => 2015,
                'bio' => 'Chantal Nizigiyimana (promotion 2015) occupe aujourd’hui le poste de Responsable Audit au sein de PwC Rwanda-Burundi. Son parcours illustre la diversité des débouchés ouverts par la FSEG dans la finance, le développement et l’entrepreneuriat.',
            ],
            [
                'name' => 'Aline Niyonzima',
                'slug' => 'alumni-aline-niyonzima',
                'role' => 'Analyste financière',
                'organization' => 'Banque de la République du Burundi',
                'year' => 2018,
                'bio' => 'Aline Niyonzima (promotion 2018) a rejoint la banque centrale après un master en finance. Elle contribue à des analyses de stabilité financière et au suivi des indicateurs macroéconomiques.',
            ],
            [
                'name' => 'Patrick Habonimana',
                'slug' => 'alumni-patrick-habonimana',
                'role' => 'Consultant en management',
                'organization' => 'Cabinet Horizon Conseil',
                'year' => 2016,
                'bio' => 'Patrick Habonimana (promotion 2016) accompagne des PME et administrations dans leurs projets de transformation organisationnelle.',
            ],
            ],
            'sci' => [
            [
                'name' => 'Nadia Barakamfitiye',
                'slug' => 'alumni-nadia-baraka',
                'role' => 'Ingénieure logicielle',
                'organization' => 'TechHub Burundi',
                'year' => 2019,
                'bio' => 'Nadia Barakamfitiye (promotion 2019) conçoit des applications pour des clients publics et privés. La FSI lui a donné la rigueur algorithmique et l’expérience projet qu’elle mobilise au quotidien.',
            ],
            [
                'name' => 'Joseph Niyonkuru',
                'slug' => 'alumni-joseph-niyonkuru',
                'role' => 'Data scientist',
                'organization' => 'Centre de recherche appliquée',
                'year' => 2018,
                'bio' => 'Joseph Niyonkuru (promotion 2018) transforme des données administratives en tableaux de bord décisionnels. Son master à la FSI reste le socle de sa pratique.',
            ],
            [
                'name' => 'Florence Irakoze',
                'slug' => 'alumni-florence-irakoze',
                'role' => 'Ingénieure réseaux',
                'organization' => 'Opérateur télécoms',
                'year' => 2017,
                'bio' => 'Florence Irakoze (promotion 2017) pilote des déploiements réseau. Les laboratoires de télécoms de la FSI ont été déterminants dans son orientation.',
            ],
            [
                'name' => 'Aimé Nsabimana',
                'slug' => 'alumni-aime-nsabimana',
                'role' => 'Chef de projet digital',
                'organization' => 'Agence numérique',
                'year' => 2020,
                'bio' => 'Aimé Nsabimana (promotion 2020) conduit des projets de transformation numérique pour des PME. Il cite les hackathons FSI comme accélérateur de carrière.',
            ],
            [
                'name' => 'Linda Muco',
                'slug' => 'alumni-linda-muco',
                'role' => 'Enseignante-chercheuse',
                'organization' => 'Université partenaire',
                'year' => 2016,
                'bio' => 'Linda Muco (promotion 2016) enseigne la chimie analytique et collabore à des contrôles qualité industriels.',
            ],
            [
                'name' => 'Kevin Bizimana',
                'slug' => 'alumni-kevin-bizimana',
                'role' => 'Développeur full-stack',
                'organization' => 'PME technologique',
                'year' => 2021,
                'bio' => 'Kevin Bizimana (promotion 2021) développe des produits web et IoT. Les ateliers embarqués de la FSI ont confirmé sa vocation.',
            ],
            ],
            'med' => [
            [
                'name' => 'Dr. Olga Nibigira',
                'slug' => 'alumni-olga-nibigira',
                'role' => 'Médecin généraliste',
                'organization' => 'CHUK',
                'year' => 2017,
                'bio' => 'Le Dr Olga Nibigira (promotion 2017) exerce au CHUK. Elle souligne l’apport des stages cliniques et de l’encadrement hospitalo-universitaire.',
            ],
            [
                'name' => 'Dr. Placide Nduwimana',
                'slug' => 'alumni-placide-nduwimana',
                'role' => 'Interniste',
                'organization' => 'Hôpital Prince Régent',
                'year' => 2016,
                'bio' => 'Le Dr Placide Nduwimana (promotion 2016) prend en charge des patients chroniques et forme des internes.',
            ],
            [
                'name' => 'Dr. Rachel Bigirimana',
                'slug' => 'alumni-rachel-bigirimana',
                'role' => 'Pédiatre',
                'organization' => 'Centre de santé communautaire',
                'year' => 2019,
                'bio' => 'La Dr Rachel Bigirimana (promotion 2019) combine clinique pédiatrique et actions de prévention en communauté.',
            ],
            [
                'name' => 'Dr. Serge Niyonkuru',
                'slug' => 'alumni-serge-niyonkuru',
                'role' => 'Santé publique',
                'organization' => 'OMS / partenaire local',
                'year' => 2015,
                'bio' => 'Le Dr Serge Niyonkuru (promotion 2015) appuie des programmes de surveillance épidémiologique.',
            ],
            [
                'name' => 'Dr. Alice Hakizimana',
                'slug' => 'alumni-alice-hakizimana',
                'role' => 'Infirmière spécialisée',
                'organization' => 'Clinique universitaire',
                'year' => 2018,
                'bio' => 'Alice Hakizimana (promotion 2018) encadre des équipes de soins critiques et tutorat des étudiants infirmiers.',
            ],
            [
                'name' => 'Dr. Yves Manirakiza',
                'slug' => 'alumni-yves-manirakiza',
                'role' => 'Chirurgien',
                'organization' => 'Hôpital régional',
                'year' => 2014,
                'bio' => 'Le Dr Yves Manirakiza (promotion 2014) pratique la chirurgie générale et contribue à la formation des résidents.',
            ],
            ],
            'agro' => [
            [
                'name' => 'Ir. Diane Niyongabo',
                'slug' => 'alumni-diane-niyongabo',
                'role' => 'Agronome',
                'organization' => 'Projet développement rural',
                'year' => 2018,
                'bio' => 'Diane Niyongabo (promotion 2018) accompagne des producteurs sur les itinéraires techniques et la fertilité des sols.',
            ],
            [
                'name' => 'Ir. Pacifique Habiyaremye',
                'slug' => 'alumni-pacifique-habiya',
                'role' => 'Conseiller environnement',
                'organization' => 'ONG agricole',
                'year' => 2017,
                'bio' => 'Pacifique Habiyaremye (promotion 2017) conduit des diagnostics environnementaux et des plans de restauration.',
            ],
            [
                'name' => 'Ir. Esther Ndayishimiye',
                'slug' => 'alumni-esther-ndayi-agro',
                'role' => 'Responsable filière café',
                'organization' => 'Coopérative paysanne',
                'year' => 2019,
                'bio' => 'Esther Ndayishimiye (promotion 2019) pilote la qualité et la commercialisation du café de sa coopérative.',
            ],
            [
                'name' => 'Ir. Claude Nsabimana',
                'slug' => 'alumni-claude-nsa',
                'role' => 'Expert sols',
                'organization' => 'Institut de recherche agronomique',
                'year' => 2016,
                'bio' => 'Claude Nsabimana (promotion 2016) réalise des analyses de fertilité et forme des techniciens de terrain.',
            ],
            [
                'name' => 'Ir. Solange Uwimana',
                'slug' => 'alumni-solange-uwi',
                'role' => 'Entrepreneure agro',
                'organization' => 'Ferme modèle',
                'year' => 2020,
                'bio' => 'Solange Uwimana (promotion 2020) a créé une ferme diversifiée inspirée des enseignements d’agroécologie.',
            ],
            [
                'name' => 'Ir. Thierry Bizimana',
                'slug' => 'alumni-thierry-bizi',
                'role' => 'Gestion des ressources',
                'organization' => 'Projet bassin versant',
                'year' => 2015,
                'bio' => 'Thierry Bizimana (promotion 2015) coordonne des ouvrages antiérosifs et la gouvernance locale de l’eau.',
            ],
            ],
            'hum' => [
            [
                'name' => 'Grace Nkurunziza',
                'slug' => 'alumni-grace-nku',
                'role' => 'Journaliste',
                'organization' => 'Média national',
                'year' => 2018,
                'bio' => 'Grace Nkurunziza (promotion 2018) couvre l’actualité culturelle et sociale. La FLSH lui a donné méthode et éthique journalistique.',
            ],
            [
                'name' => 'Benjamin Hakizimana',
                'slug' => 'alumni-benjamin-haki',
                'role' => 'Enseignant de lettres',
                'organization' => 'Lycée de référence',
                'year' => 2016,
                'bio' => 'Benjamin Hakizimana (promotion 2016) transmet le goût des lettres et anime un club d’écriture au lycée.',
            ],
            [
                'name' => 'Carine Niyonkuru',
                'slug' => 'alumni-carine-niyo',
                'role' => 'Chargée de communication',
                'organization' => 'Organisation culturelle',
                'year' => 2019,
                'bio' => 'Carine Niyonkuru (promotion 2019) pilote la communication d’événements culturels et de campagnes éducatives.',
            ],
            [
                'name' => 'Olivier Manirakiza',
                'slug' => 'alumni-olivier-mani',
                'role' => 'Traducteur',
                'organization' => 'Bureau de traduction',
                'year' => 2017,
                'bio' => 'Olivier Manirakiza (promotion 2017) traduit des documents administratifs et littéraires entre le français, l’anglais et le kirundi.',
            ],
            [
                'name' => 'Diane Irakoze',
                'slug' => 'alumni-diane-ira',
                'role' => 'Chercheuse en histoire',
                'organization' => 'Centre patrimonial',
                'year' => 2015,
                'bio' => 'Diane Irakoze (promotion 2015) documente des archives et conçoit des expositions patrimoniales.',
            ],
            [
                'name' => 'Samuel Ndayizeye',
                'slug' => 'alumni-samuel-ndayi',
                'role' => 'Responsable pédagogique',
                'organization' => 'Institut de formation',
                'year' => 2020,
                'bio' => 'Samuel Ndayizeye (promotion 2020) forme des enseignants et conçoit des modules didactiques innovants.',
            ],
            ],
        ];

        return $sets[$key] ?? $sets['eco'];
    }

    /** @return list<array<string, mixed>> */
    private function testimonialSet(string $key): array
    {
        $sets = [
            'eco' => [
            [
                'name' => 'Emmanuel Nzeyimana',
                'promotion' => 'Promotion 2008',
                'quote' => 'La FSEG m\'a donné les bases analytiques et la rigueur qui font aujourd\'hui ma force dans le secteur bancaire international.',
            ],
            [
                'name' => 'Claudine Irakoze',
                'promotion' => 'Promotion 2012',
                'quote' => 'Grâce aux enseignants de la FSEG et à leur exigence, j\'ai pu intégrer une institution panafricaine de premier plan.',
            ],
            [
                'name' => 'Désiré Ntibonera',
                'promotion' => 'Promotion 2005',
                'quote' => 'Entreprendre après la FSEG a été naturel : la formation en gestion m\'a préparé à structurer et faire grandir mon entreprise.',
            ],
            ],
            'sci' => [
            [
                'name' => 'Nadia Barakamfitiye',
                'promotion' => 'Promotion 2019',
                'quote' => 'Les laboratoires et les projets numériques m’ont préparée aux défis technologiques réels, bien au-delà des cours théoriques.',
            ],
            [
                'name' => 'Joseph Niyonkuru',
                'promotion' => 'Promotion 2018',
                'quote' => 'La formation allie théorie solide et pratique : un atout décisif pour exercer en data science.',
            ],
            [
                'name' => 'Florence Irakoze',
                'promotion' => 'Promotion 2017',
                'quote' => 'J’ai appris à résoudre des problèmes complexes avec méthode, créativité et esprit d’équipe.',
            ],
            ],
            'med' => [
            [
                'name' => 'Dr. Olga Nibigira',
                'promotion' => 'Promotion 2017',
                'quote' => 'L’approche clinique et humaine de la faculté guide encore ma pratique au quotidien.',
            ],
            [
                'name' => 'Dr. Placide Nduwimana',
                'promotion' => 'Promotion 2016',
                'quote' => 'Stages hospitaliers et encadrement de qualité : une base indispensable pour soigner avec confiance.',
            ],
            [
                'name' => 'Dr. Rachel Bigirimana',
                'promotion' => 'Promotion 2019',
                'quote' => 'La faculté m’a donné les outils pour servir les communautés avec compétence et écoute.',
            ],
            ],
            'agro' => [
            [
                'name' => 'Ir. Diane Niyongabo',
                'promotion' => 'Promotion 2018',
                'quote' => 'Les enseignements de terrain m’aident chaque semaine à accompagner les producteurs ruraux.',
            ],
            [
                'name' => 'Ir. Pacifique Habiyaremye',
                'promotion' => 'Promotion 2017',
                'quote' => 'J’y ai découvert l’agroécologie comme levier concret de développement durable.',
            ],
            [
                'name' => 'Ir. Esther Ndayishimiye',
                'promotion' => 'Promotion 2019',
                'quote' => 'La faculté relie science agronomique et réalités des filières agricoles mieux que tout manuel.',
            ],
            ],
            'hum' => [
            [
                'name' => 'Grace Nkurunziza',
                'promotion' => 'Promotion 2018',
                'quote' => 'Esprit critique, langues et culture : des outils précieux pour le journalisme au quotidien.',
            ],
            [
                'name' => 'Benjamin Hakizimana',
                'promotion' => 'Promotion 2016',
                'quote' => 'La FLSH m’a préparé à transmettre le goût des lettres aux jeunes générations.',
            ],
            [
                'name' => 'Carine Niyonkuru',
                'promotion' => 'Promotion 2019',
                'quote' => 'Communication et sciences humaines : un duo parfait pour mon métier actuel.',
            ],
            ],
        ];

        return $sets[$key] ?? $sets['eco'];
    }


    private function demoPortraitColor(string $base, int $index): string
    {
        $palette = [$base, '#0B6F38', '#14532D', '#0F766E', '#1D4ED8', '#7C3AED', '#B45309', '#BE123C'];

        return $palette[$index % count($palette)];
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
