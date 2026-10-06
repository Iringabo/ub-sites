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
    /**
     * Default brand colour per faculty slug. Every value is a shade of the
     * green or red found in the Université du Burundi logo; unknown slugs
     * fall back to the institutional green.
     *
     * @var array<string, string>
     */
    public const BRAND_COLORS = [
        'fseg' => '#0D9B49', // emerald (UB green)
        'fsi'  => '#0B6F38', // forest green
        'fabi' => '#4E8F2F', // leaf green
        'med'  => '#C8102E', // crimson (UB red)
        'flsh' => '#8B1E2D', // burgundy
    ];

    /**
     * Home section order applied by the demo seed (visitor-first reading order).
     *
     * @var list<string>
     */
    public const DEFAULT_SECTION_ORDER = [
        'hero',
        'quick_links',
        'statistics',
        'about',
        'programmes_preview',
        'news_preview',
        'research_labs',
        'dean_message',
        'staff_preview',
        'custom_text',
        'contact_cta',
    ];

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
        $color = $this->brandColorForSlug($slug);
        $secondary = service('siteTheme')->tokens($color)['brand-dark'];
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
            'enabled_sections' => json_encode(self::DEFAULT_SECTION_ORDER, JSON_UNESCAPED_UNICODE),
            'updated_at'       => $this->now(),
        ]);

        $this->seedHome($siteId, $name, $short, $theme, $heroPath);
        $this->seedHeroes($siteId, $gallery['heroes'], $theme);
        $this->seedStats($siteId, $theme);
        $this->seedProgrammes($siteId, $theme);
        $deanPhoto = $this->seedStaff($siteId, $gallery['staff'], $theme, $slug, $name, $color);
        $this->seedPosts($siteId, $gallery['posts'], $name, $slug, $theme);
        $this->seedResearch($siteId, $short, $theme);
        $this->seedTimeline($siteId, $theme);
        $this->seedAlumni($siteId, $slug, $theme, $gallery['alumni'] ?? [], $name, $color);
        $this->seedPages($siteId, $name, $short, $gallery['banner'], $theme, $deanPhoto);
        $this->seedSettings($siteId, $name, $short, $color, $logoPath, $site, $theme);
        $this->seedContentBlocks($siteId, $theme);
        $this->seedEnglishTranslations($siteId, $theme);
        $this->informationSeeder()->seed($siteId, $slug, (string) ($theme['key'] ?? 'eco'), $name, $short, $gallery['banner']);

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
            'modules'  => ['home', 'heroes', 'stats', 'programmes', 'staff', 'posts', 'research', 'timeline', 'alumni', 'pages', 'settings', 'departments', 'partners', 'faq'],
        ];
    }

    private function clearSiteContent(int $siteId): void
    {
        // Hard delete so unique (site_id, slug) indexes can be reused by the demo seed.
        // Does not touch uploads/sites/{slug}/faculty-deans/ files on disk.
        $this->informationSeeder()->clear($siteId);
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
                'social.links', 'home.hero_indicator_size', 'institution.university_url',
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
        // Each slide gets a primary + secondary call to action so visitors always
        // have a direct path to programmes, news, research or contact.
        $ctas = [
            [['programmes', 'Découvrir les formations'], ['contact', 'Nous contacter']],
            [['programmes', 'Voir les formations'], ['news', 'Actualités & événements']],
            [['research', 'Explorer la recherche'], ['contact', 'Nous contacter']],
            [['news', 'Actualités & événements'], ['programmes', 'Voir les formations']],
            [['contact', 'Rejoindre la faculté'], ['programmes', 'Voir les formations']],
        ];
        foreach ($heroes as $i => $path) {
            $cap = $captions[$i] ?? ['badge' => 'Campus', 'title' => $theme['tagline'], 'text' => $theme['about']];
            [$primary, $secondary] = $ctas[$i % count($ctas)];
            $badge = $i === 0 ? ($cap['badge'] . ' · Université du Burundi') : $cap['badge'];
            $this->db->table('home_hero_slides')->insert([
                'site_id'              => $siteId,
                'image_path'           => $path,
                'alt_text'             => $cap['title'],
                'badge'                => $badge,
                'title'                => $cap['title'],
                'text'                 => $cap['text'],
                'primary_cta_target'   => $primary[0],
                'primary_cta_label'    => $primary[1],
                'primary_cta_url'      => null,
                'secondary_cta_target' => $secondary[0],
                'secondary_cta_label'  => $secondary[1],
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
            'hero_badge'               => $short . ' · Université du Burundi',
            'hero_title'               => $theme['hero_captions'][0]['title'] ?? $name,
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
            'research_button_label'    => 'Découvrir la recherche',
            'research_button_url'      => '/recherche',
            'programmes_label'         => 'Formations',
            'programmes_title'         => $theme['programmes_title'] ?? 'Parcours académiques',
            'programmes_text'          => $theme['programmes_intro'],
            'programmes_button_label'  => 'Toutes les formations',
            'programmes_button_url'    => '/formations',
            'posts_label'              => 'Actualités & événements',
            'posts_title'              => $theme['posts_title'] ?? 'Vie de la faculté',
            'posts_text'               => $theme['posts_intro'] ?? 'Retrouvez les dernières nouvelles, événements et opportunités.',
            'posts_button_label'       => 'Toutes les actualités',
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
        $hasOptions = $this->db->fieldExists('option_code', 'programmes');
        $hasPaths = $this->db->fieldExists('branch_core', 'programmes');

        foreach ($theme['programmes'] as $i => $row) {
            $options = $hasOptions ? [
                'option_code' => $row['option_code'] ?? null,
                'common_core' => $row['common_core'] ?? null,
            ] : [];
            if ($hasPaths) {
                $options += [
                    'common_core_years' => $row['common_core_years'] ?? null,
                    'branch_core'       => $row['branch_core'] ?? null,
                    'branch_core_years' => $row['branch_core_years'] ?? null,
                ];
            }

            $this->db->table('programmes')->insert($options + [
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

            $programmeId = (int) $this->db->insertID();
            if ($hasOptions && isset($row['common_core_en'])) {
                $this->insertTranslation($siteId, 'programmes', $programmeId, 'common_core', $row['common_core_en']);
            }
            if ($hasPaths && isset($row['branch_core_en'])) {
                $this->insertTranslation($siteId, 'programmes', $programmeId, 'branch_core', $row['branch_core_en']);
            }
        }
    }

    /**
     * @param list<string> $photos
     * @param array<string, mixed> $theme
     */
    private function seedStaff(int $siteId, array $photos, array $theme, string $slug, string $facultyLabel, string $color): ?string
    {
        $deanPhoto = null;

        foreach ($theme['staff'] as $i => $row) {
            $photo = $this->media->portraitJpeg(
                $slug,
                'staff',
                $facultyLabel,
                (string) $row['name'],
                $this->demoPortraitColor($color, $i),
            ) ?? ($photos[$i] ?? null);

            if ($deanPhoto === null && ($row['category'] ?? '') === 'enseignant' && is_string($photo) && $photo !== '') {
                $deanPhoto = $photo;
            }

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

        return $deanPhoto;
    }

    private const POST_AUTHORS = ['Service de communication', 'Décanat', 'Secrétariat académique'];

    /**
     * @param list<string> $covers
     * @param array<string, mixed> $theme
     */
    private function seedPosts(int $siteId, array $covers, string $name, string $slug, array $theme): void
    {
        helper('site');

        foreach ($theme['posts'] as $i => $row) {
            $published = date('Y-m-d H:i:s', strtotime('-' . ($i * 3) . ' days'));
            $cover = $covers === [] ? null : ($covers[$i % count($covers)] ?? null);
            $this->db->table('posts')->insert([
                'site_id'         => $siteId,
                'type'            => $row['type'],
                'title'           => $row['title'],
                'slug'            => $row['slug'],
                'excerpt'         => $row['excerpt'],
                'body'            => site_plain_paragraphs((string) $row['body']),
                'author_name'     => self::POST_AUTHORS[$i % count(self::POST_AUTHORS)] . ' — ' . strtoupper($slug),
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
                'url'           => $row['url'] ?? null,
                'display_order' => $i + 1,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
        }

        foreach ($theme['projects'] as $i => $row) {
            $this->db->table('research_projects')->insert([
                'site_id'       => $siteId,
                'code'          => $row['code'] ?? (strtoupper($short) . '-P' . ($i + 1)),
                'title'         => $row['title'],
                'description'   => $row['description'],
                'funder'        => $row['funder'],
                'period_start'  => $row['start'],
                'period_end'    => $row['end'],
                'icon'          => $row['icon'] ?? 'bi-lightbulb',
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
    private function seedPages(int $siteId, string $name, string $short, ?string $banner, array $theme, ?string $deanPhoto = null): void
    {
        $deanParagraphs = $this->paragraphsFromText((string) ($theme['dean_message'] ?? ''));
        $mission = is_array($theme['mission'] ?? null) ? $theme['mission'] : [
            'icon' => 'bi-bullseye',
            'title' => 'Mission',
            'paragraphs' => $this->paragraphsFromText((string) ($theme['mission'] ?? '')),
        ];
        $vision = is_array($theme['vision'] ?? null) ? $theme['vision'] : [
            'icon' => 'bi-eye',
            'title' => 'Vision',
            'paragraphs' => $this->paragraphsFromText((string) ($theme['vision'] ?? '')),
        ];
        $values = [];
        foreach (($theme['values'] ?? []) as $value) {
            if (! is_array($value)) {
                continue;
            }
            $values[] = [
                'icon'        => (string) ($value['icon'] ?? 'bi-star'),
                'title'       => (string) ($value['title'] ?? ''),
                'description' => (string) ($value['description'] ?? $value['text'] ?? ''),
            ];
        }

        $campus = is_array($theme['campus'] ?? null) ? $theme['campus'] : [];
        $pages = [
            'faculty' => [
                'title' => 'La Faculté',
                'content' => [
                    'banner_title'    => '',
                    'banner_subtitle' => $theme['faculty_banner'] ?? ('Présentation de ' . $short),
                    'banner_image'    => $banner,
                    'dean'            => [
                        'label'      => $theme['dean_label'] ?? 'Mot du Doyen',
                        'title'      => $theme['dean_title'] ?? ('Bienvenue à ' . $short),
                        'photo'      => $deanPhoto,
                        'name'       => $theme['dean_name'] ?? '',
                        'role'       => $theme['dean_role'] ?? 'Doyen de la Faculté',
                        'specialty'  => $theme['dean_specialty'] ?? '',
                        'signature'  => $theme['dean_signature'] ?? ($theme['dean_name'] ?? ''),
                        'paragraphs' => $deanParagraphs,
                    ],
                    'mission_label' => $theme['mission_label'] ?? 'Nos valeurs fondamentales',
                    'mission_title' => $theme['mission_title'] ?? 'Mission & Vision',
                    'mission'       => $mission,
                    'vision'        => $vision,
                    'values'        => $values,
                    'history'       => $theme['history'] ?? [
                        'label' => 'Notre parcours',
                        'title' => 'Historique de ' . $short,
                        'text'  => 'Une trajectoire académique au service du développement national.',
                    ],
                ],
            ],
            'posts' => [
                'title' => 'Actualités et événements',
                'content' => [
                    'banner_title'    => '',
                    'banner_subtitle' => $theme['posts_banner'] ?? ('Suivez la vie de ' . $short),
                    'banner_image'    => $banner,
                ],
            ],
            'research' => [
                'title' => 'Recherche',
                'content' => [
                    'banner_title'         => '',
                    'banner_subtitle'      => $theme['research_banner'] ?? 'Laboratoires, publications et projets',
                    'banner_image'         => $banner,
                    'labs_label'           => 'Laboratoires',
                    'labs_title'           => $theme['research_title'],
                    'labs_text'            => $theme['research_body'],
                    'publications_label'   => 'Publications',
                    'publications_title'   => $theme['publications_title'] ?? 'Travaux récents',
                    'publications_text'    => $theme['publications_intro'] ?? ('Sélection de publications de ' . $short),
                    'projects_label'       => 'Projets',
                    'projects_title'       => $theme['projects_title'] ?? 'Projets en cours',
                    'projects_text'        => $theme['projects_intro'] ?? ('Projets financés et partenariats de ' . $short),
                ],
            ],
            'staff' => [
                'title' => 'Corps enseignant et personnel',
                'content' => [
                    'banner_title'    => '',
                    'banner_subtitle' => $theme['staff_banner'] ?? 'Une équipe engagée au service de la formation',
                    'banner_image'    => $banner,
                ],
            ],
            'formations' => [
                'title' => 'Formations',
                'content' => [
                    'banner_title'    => '',
                    'banner_subtitle' => $theme['formations_banner'] ?? 'Parcours de licence et de master',
                    'banner_image'    => $banner,
                    'offer_label'     => $theme['offer_label'] ?? 'Offre académique',
                    'offer_title'     => $theme['offer_title'] ?? 'Des programmes adaptés à chaque ambition',
                    'offer_text'      => $theme['offer_text'] ?? $theme['programmes_intro'],
                    'cta_title'       => $theme['cta_title'] ?? 'Vous souhaitez postuler ?',
                    'cta_text'        => $theme['cta_text'] ?? 'Contactez notre service des admissions pour plus d’informations sur les procédures d’inscription.',
                    'cta_label'       => $theme['cta_label'] ?? 'Nous contacter',
                    'cta_url'         => '/contact',
                ],
            ],
            'alumni' => [
                'title' => 'Alumni',
                'content' => [
                    'banner_title'         => '',
                    'banner_subtitle'      => $theme['alumni_banner'] ?? 'Le réseau des diplômés',
                    'banner_image'         => $banner,
                    'intro_label'          => 'Communauté',
                    'intro_title'          => 'Les alumni de ' . $short,
                    'intro_paragraphs'     => $theme['alumni_intro'] ?? [
                        'Les diplômés de ' . $name . ' forment un réseau actif dans l’administration, le secteur privé, la recherche et l’entrepreneuriat.',
                        'Cette page présente quelques parcours illustratifs. Les fiches peuvent être enrichies depuis l’administration.',
                    ],
                    'intro_button_label'   => 'Rejoindre le réseau',
                    'intro_button_url'     => '/contact',
                    'profiles_label'       => 'Profils',
                    'profiles_title'       => 'Parcours de diplômés',
                    'profiles_text'        => $theme['alumni_profiles_text'] ?? 'Une sélection de profils pour illustrer la diversité des débouchés.',
                    'testimonials_label'   => 'Témoignages',
                    'testimonials_title'   => 'Ce que la faculté leur a apporté',
                    'cta_title'            => 'Vous êtes diplômé de ' . $short . ' ?',
                    'cta_text'             => $theme['alumni_cta'] ?? ('Contactez le secrétariat pour actualiser votre profil ou rejoindre les activités du réseau alumni.'),
                    'cta_label'            => 'Nous écrire',
                    'cta_url'              => '/contact',
                ],
            ],
            'contact' => [
                'title' => 'Contact',
                'content' => [
                    'banner_title'    => '',
                    'banner_subtitle' => $theme['contact_banner'] ?? ($campus['label'] ?? ('Nous sommes à votre écoute — ' . $short)),
                    'contact_label'   => $theme['contact_label'] ?? 'Nos coordonnées',
                    'contact_title'   => $theme['contact_title'] ?? 'Prenez contact',
                    'form_title'      => $theme['form_title'] ?? 'Formulaire de contact',
                    'form_help'       => $theme['form_help'] ?? 'Tous les champs marqués comme obligatoires doivent être remplis.',
                    'map_url'         => $campus['map_url'] ?? '',
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
                'content'         => json_encode($page['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_published'    => 1,
                'created_at'      => $this->now(),
                'updated_at'      => $this->now(),
            ]);
        }
    }

    /**
     * @param object|array<string, mixed> $site
     * @param array<string, mixed> $theme
     */
    private function seedSettings(int $siteId, string $name, string $short, string $color, ?string $logo, $site, array $theme = []): void
    {
        $logoPath = $logo ?: 'assets/images/logo-placeholder.png';
        $campus = is_array($theme['campus'] ?? null) ? $theme['campus'] : [];
        $emailLocal = (string) ($campus['email_local'] ?? strtolower($short));
        $rows = [
            ['institution.faculty_name', $name, 'string', 'institution'],
            ['institution.short_name', $short, 'string', 'institution'],
            ['institution.university', 'Université du Burundi', 'string', 'institution'],
            ['institution.university_url', 'https://ub.edu.bi', 'string', 'institution'],
            ['contact.address_line', (string) ($campus['address_line'] ?? 'Avenue de l’Université'), 'string', 'contact'],
            ['contact.address_commune', (string) ($campus['commune'] ?? 'Mukaza'), 'string', 'contact'],
            ['contact.address_province', (string) ($campus['province'] ?? 'Bujumbura Mairie'), 'string', 'contact'],
            ['contact.address_country', (string) ($campus['country'] ?? 'Burundi'), 'string', 'contact'],
            ['contact.phone', (string) ($campus['phone'] ?? '+257 22 22 00 00'), 'string', 'contact'],
            ['contact.email', $emailLocal . '@ub.edu.bi', 'email', 'contact'],
            ['contact.hours', (string) ($campus['hours'] ?? 'Lun–Ven 8h–16h'), 'string', 'contact'],
            ['footer.text', $name . ' — Université du Burundi.', 'text', 'footer'],
            ['footer.copyright', '© ' . date('Y') . ' ' . $name, 'string', 'footer'],
            ['assets.logo', $logoPath, 'path', 'assets'],
            ['seo.default_title', $short . ' | Université du Burundi', 'string', 'seo'],
            ['seo.default_description', $theme['seo_description'] ?? ('Site officiel — ' . $name), 'text', 'seo'],
            ['seo.theme_color', $color, 'color', 'seo'],
            ['seo.og_image', $logoPath, 'path', 'seo'],
            ['social.links', json_encode(new \stdClass(), JSON_FORCE_OBJECT), 'json', 'social'],
            ['home.hero_indicator_size', '0.75', 'string', 'home'],
        ];

        foreach ($rows as [$key, $value, $type, $context]) {
            $this->db->table('settings')->insert([
                'site_id'    => $siteId,
                'class'      => 'App\\Settings\\Site',
                'key'        => $key,
                'value'      => $value,
                'type'       => $type,
                'context'    => $context,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function seedContentBlocks(int $siteId, array $theme): void
    {
        $blocks = [
            ['home', 'custom_text', 'Mot d’accueil', $theme['welcome'] ?? ('Bienvenue sur le site de ' . ($theme['short'] ?? 'la faculté') . '. Découvrez nos formations, notre recherche et la vie du campus.'), 1, []],
            ['home', 'contact_cta', 'Une question ? Contactez-nous', $theme['contact_cta'] ?? 'Admissions, stages, partenariats ou simple demande d’information : le secrétariat de la faculté vous répond pendant les heures d’ouverture.', 2, ['label' => 'Écrire à la faculté', 'url' => '/contact']],
        ];
        foreach ($blocks as [$pageKey, $type, $title, $content, $order, $settings]) {
            $this->db->table('content_blocks')->insert([
                'site_id'       => $siteId,
                'page_key'      => $pageKey,
                'type'          => $type,
                'title'         => $title,
                'content'       => $content,
                'settings'      => json_encode($settings === [] ? new \stdClass() : $settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
    /**
     * Brand colour used by the demo seed for a faculty slug (UB green by default).
     */
    public function brandColorForSlug(string $slug): string
    {
        return self::BRAND_COLORS[strtolower(trim($slug))] ?? SiteThemeService::UB_GREEN;
    }

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
            'programmes_title' => 'Licences et masters',
            'programmes_intro' => 'Des parcours en économie, gestion, finance et entrepreneuriat, conçus pour l’insertion professionnelle comme pour la poursuite d’études en master.',
            'posts_title' => 'Actualités et vie académique',
            'posts_intro' => 'Annonces pédagogiques, résultats, partenariats internationaux et événements scientifiques de la FSEG.',
            'dean_name' => 'Prof. Jean-Baptiste Ndayishimiye',
            'dean_message' => "C’est avec un immense plaisir que je vous souhaite la bienvenue sur le site de la Faculté des Sciences Économiques et de Gestion de l’Université du Burundi. Depuis sa création en 1973, notre faculté s’est imposée comme un pilier de l’enseignement supérieur au Burundi.\n\nNotre mission est claire : former des cadres compétents, éthiques et innovants, capables de contribuer au développement économique et social de notre nation. Nous offrons une formation de qualité, alliant rigueur académique et pertinence pratique.\n\nJe vous invite à explorer nos programmes, à découvrir nos activités de recherche et à rejoindre notre communauté académique dynamique.",
            'welcome' => 'Bienvenue à la FSEG. Explorez les formations, la recherche, les actualités et le réseau des diplômés — puis contactez le secrétariat pour toute question d’admission ou de partenariat.',
            'hero_captions' => [
                ['badge' => 'FSEG', 'title' => 'Former les décideurs économiques de demain', 'text' => 'Économie, finance, management et entrepreneuriat ancrés dans les réalités du Burundi et de la région des Grands Lacs.'],
                ['badge' => 'Formations', 'title' => 'Licences et masters', 'text' => 'Gestion, économie politique et économie rurale : une spécialisation progressive à partir du Bac II.'],
                ['badge' => 'Recherche', 'title' => 'Quatre laboratoires actifs', 'text' => 'LEA, CRGD, LFC et URPP produisent des analyses utiles aux politiques publiques et aux entreprises.'],
                ['badge' => 'Campus', 'title' => 'Une vie étudiante engagée', 'text' => 'Conférences, journées portes ouvertes, séminaires de recherche et accompagnement pédagogique tout au long du cursus.'],
                ['badge' => 'Alumni', 'title' => 'Plus de 15 000 diplômés', 'text' => 'Un réseau présent dans la banque, l’administration, le conseil et l’entrepreneuriat, au Burundi et à l’étranger.'],
            ],
            'highlights' => [
                ['icon' => 'bi-mortarboard', 'title' => 'Formation d’excellence', 'description' => 'Curricula modernisés, stages encadrés et exigence académique de la licence au master.'],
                ['icon' => 'bi-graph-up-arrow', 'title' => 'Recherche appliquée', 'description' => 'Travaux sur la croissance, la finance inclusive, la gouvernance des PME et les politiques fiscales.'],
                ['icon' => 'bi-people', 'title' => 'Communauté et partenariats', 'description' => 'Échanges internationaux, réseau alumni et liens étroits avec le secteur public et privé.'],
                ['icon' => 'bi-globe2', 'title' => 'Ouverture internationale', 'description' => 'Mobilités, cotutelles et partenariats universitaires en Afrique et en Europe.'],
            ],
            'dean_role' => 'Doyen de la Faculté',
            'dean_specialty' => 'Économie & Développement',
            'dean_title' => 'Bienvenue à la FSEG',
            'dean_label' => 'Mot du Doyen',
            'history' => [
                'label' => 'Notre parcours',
                'title' => 'Historique de la FSEG',
                'text'  => 'Plus de cinq décennies d’excellence académique et d’engagement au service du développement national.',
            ],
            'offer_label' => 'Offre académique',
            'offer_title' => 'Des programmes adaptés à chaque ambition',
            'offer_text' => 'Licences et masters en gestion, économie politique et économie rurale, après un tronc commun FSEG en Bac I.',
            'cta_title' => 'Vous souhaitez postuler ?',
            'cta_text' => 'Contactez notre service des admissions pour plus d’informations sur les procédures d’inscription.',
            'cta_label' => 'Nous contacter',
            'contact_label' => 'Nos coordonnées',
            'contact_title' => 'Prenez contact',
            'form_title' => 'Formulaire de contact',
            'form_help' => 'Tous les champs marqués comme obligatoires doivent être remplis.',
            'faculty_banner' => 'Faculté des Sciences Économiques et de Gestion',
            'contact_banner' => 'Nous sommes à votre écoute — Campus Mutanga',
            'projects_intro' => 'Projets financés avec l’UE, la Banque mondiale, la FAO et les partenaires bilatéraux.',
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
            'seo_description' => 'La Faculté des Sciences de l’Ingénieur forme des ingénieurs en électromécanique, génie civil, aménagement et urbanisme et technologies de l’information et de la communication.',
            'about_title' => 'Former des scientifiques et ingénieurs responsables',
            'about' => 'La Faculté des Sciences de l’Ingénieur de l’Université du Burundi, au campus Kiriri, prépare des ingénieurs capables de résoudre des problèmes complexes. Ses départements d’Électromécanique, de Génie civil, d’Aménagement et urbanisme et des Technologies de l’information et de la communication (TIC) articulent enseignement fondamental, laboratoires et projets appliqués aux besoins du pays.',
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
            'programmes_intro' => 'Licences en quatre ans en électromécanique, génie civil, aménagement et urbanisme et TIC — dont les options Génie informatique, Génie logiciel, Réseaux et télécommunications et Systèmes embarqués — et masters en génie logiciel et en réseaux.',
            'posts_title' => 'Actualités scientifiques',
            'posts_intro' => 'Soutenances, hackathons, projets étudiants et annonces de laboratoires de la FSI.',
            'dean_name' => 'Pr. Eric Habonimana',
            'dean_message' => "Bienvenue à la Faculté des Sciences de l’Ingénieur.\n\nIci, la curiosité scientifique rencontre la rigueur expérimentale. Nos étudiants apprennent à modéliser, à programmer, à mesurer et à concevoir — toujours avec le souci d’apporter des réponses utiles au Burundi.\n\nJe vous invite à découvrir nos départements, nos laboratoires et les opportunités de stages et de recherche qui jalonnent le parcours.",
            'welcome' => 'Bienvenue à la FSI. Découvrez les départements scientifiques, les laboratoires et les projets numériques qui forment la prochaine génération d’ingénieurs et de chercheurs.',
            'hero_captions' => [
                ['badge' => 'FSI', 'title' => 'Sciences et ingénierie pour le pays', 'text' => 'Électromécanique, génie civil, aménagement et urbanisme et TIC au service des transitions numérique et énergétique.'],
                ['badge' => 'Laboratoires', 'title' => 'Apprendre en expérimentant', 'text' => 'Travaux pratiques, projets tutorés et plateformes numériques pour ancrer la théorie dans le réel.'],
                ['badge' => 'Numérique', 'title' => 'Données, logiciels et réseaux', 'text' => 'Des cursus qui préparent aux métiers du développement, de la cybersécurité et de la data science.'],
                ['badge' => 'Énergie', 'title' => 'Ingénierie durable', 'text' => 'Recherche et formation sur les énergies renouvelables et les systèmes efficaces.'],
                ['badge' => 'Communauté', 'title' => 'Ouverts sur l’industrie', 'text' => 'Partenariats avec opérateurs télécoms, PME technologiques et centres de recherche régionaux.'],
            ],
            'highlights' => [
                ['icon' => 'bi-cpu', 'title' => 'Informatique et data', 'description' => 'Génie logiciel, systèmes embarqués et science des données pour l’économie numérique.'],
                ['icon' => 'bi-lightning-charge', 'title' => 'Énergies et physique', 'description' => 'Formation et recherche tournées vers l’efficacité énergétique et les renouvelables.'],
                ['icon' => 'bi-buildings', 'title' => 'Génie civil et urbanisme', 'description' => 'Bâtiments, ouvrages hydrauliques et aménagement pour des villes et des infrastructures durables.'],
                ['icon' => 'bi-gear-wide-connected', 'title' => 'Ingénierie de terrain', 'description' => 'Projets tutorés, prototypes et stages en entreprise technologique.'],
            ],
            'dean_role' => 'Doyen de la Faculté',
            'dean_specialty' => 'Informatique & Systèmes',
            'dean_title' => 'Bienvenue à la FSI',
            'history' => [
                'label' => 'Notre parcours',
                'title' => 'Historique de la FSI',
                'text'  => 'Une faculté scientifique engagée dans la formation d’ingénieurs et de chercheurs pour le Burundi.',
            ],
            'offer_title' => 'Sciences fondamentales et technologiques',
            'offer_text' => 'Licences et masters en électromécanique, génie civil, aménagement et urbanisme et TIC, avec une forte composante expérimentale.',
            'faculty_banner' => 'Faculté des Sciences de l’Ingénieur',
            'contact_banner' => 'Campus Kiriri — Sciences de l’Ingénieur',
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
            'formations_banner' => 'Électromécanique, génie civil, aménagement et urbanisme, TIC',
            'research_banner' => 'Laboratoires numériques, énergie et modélisation',
            'staff_banner' => 'Enseignants-chercheurs et techniciens de laboratoire',
            'alumni_banner' => 'Ingénieurs et scientifiques dans le numérique et l’industrie',
            'posts_banner' => 'Hackathons, soutenances et annonces de laboratoires',
            'publications_title' => 'Publications scientifiques de la FSI',
            'publications_intro' => 'Travaux en TIC, génie civil, électromécanique et énergies renouvelables.',
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
            'programmes_title' => 'Formations médicales',
            'programmes_intro' => 'Baccalauréat en médecine en six ans, diplômes d’études spécialisées et master en santé publique — ancrés dans la pratique hospitalière du CHUK et le terrain communautaire.',
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
                ['icon' => 'bi-people', 'title' => 'Communauté hospitalière', 'description' => 'Partenariats avec le CHUK et les formations sanitaires du pays.'],
            ],
            'dean_role' => 'Doyenne de la Faculté',
            'dean_specialty' => 'Santé publique & épidémiologie',
            'dean_title' => 'Bienvenue à la Faculté de Médecine',
            'history' => [
                'label' => 'Notre parcours',
                'title' => 'Historique de la Faculté de Médecine',
                'text'  => 'Une tradition hospitalo-universitaire au service de la santé des populations.',
            ],
            'offer_title' => 'Des cursus médicaux exigeants',
            'offer_text' => 'Médecine générale, spécialisations cliniques et santé publique ancrées dans la pratique hospitalière.',
            'faculty_banner' => 'Faculté de Médecine',
            'contact_banner' => 'CHUK Kamenge — Faculté de Médecine',
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
            'formations_banner' => 'Médecine, spécialisations cliniques et santé publique',
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
            'alumni_profiles_text' => 'Parcours de médecins généralistes, spécialistes et experts en santé publique.',
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
            'programmes_intro' => 'Six options de licence après un tronc commun FABI de deux ans — production végétale, production animale, agroalimentaire, environnement, socio-économie rurale et agro-environnement — et des masters avec une forte dimension de terrain.',
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
                ['icon' => 'bi-flower1', 'title' => 'Innovation rurale', 'description' => 'Stations expérimentales et projets de terrain avec les communautés.'],
            ],
            'dean_role' => 'Doyenne de la Faculté',
            'dean_specialty' => 'Agroécologie & productions végétales',
            'dean_title' => 'Bienvenue à la FABI',
            'history' => [
                'label' => 'Notre parcours',
                'title' => 'Historique de la FABI',
                'text'  => 'Former des ingénieurs agronomes engagés pour la sécurité alimentaire et le monde rural.',
            ],
            'offer_title' => 'Agronomie, environnement et bioingénierie',
            'offer_text' => 'Des parcours de terrain alliant sciences du vivant, innovation rurale et gestion durable des ressources.',
            'faculty_banner' => 'Faculté d’Agronomie et de Bioingénierie',
            'contact_banner' => 'Campus Zege (Gitega) — FABI',
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
            'seo_description' => 'La FLSH cultive l’esprit critique, les langues et la compréhension des sociétés à travers les littératures, l’histoire, la géographie et la socio-anthropologie.',
            'about_title' => 'Comprendre les sociétés, transmettre les cultures',
            'about' => 'La Faculté des Lettres et des Sciences Humaines forme des esprits critiques capables d’analyser les sociétés, de maîtriser les langues et de transmettre le patrimoine culturel. Langues et cultures africaines, lettres françaises et anglaises, histoire, géographie et socio-anthropologie y dialoguent pour éclairer le présent.',
            'mission' => 'Former des citoyens cultivés, des enseignants, des communicants et des chercheurs capables de comprendre les dynamiques sociales et de contribuer au débat public.',
            'vision' => 'Faire de la FLSH un pôle régional d’excellence en sciences humaines, reconnu pour la qualité de ses formations linguistiques, littéraires et historiques.',
            'values' => [
                ['title' => 'Esprit critique', 'text' => 'Analyser les textes, les discours et les sociétés avec méthode et ouverture.'],
                ['title' => 'Pluralité', 'text' => 'Valoriser les langues, les cultures et les mémoires du Burundi et d’ailleurs.'],
                ['title' => 'Transmission', 'text' => 'Préparer des enseignants et médiateurs culturels engagés.'],
            ],
            'research_title' => 'Recherche en sciences humaines',
            'research_body' => 'Projets sur le patrimoine, les langues nationales, l’éducation, la communication et les dynamiques sociales contemporaines, en lien avec les communautés et les institutions culturelles.',
            'programmes_title' => 'Parcours en lettres et sciences humaines',
            'programmes_intro' => 'Licences et masters en langues et cultures africaines, lettres françaises, anglais, histoire, géographie et socio-anthropologie — ouverts sur l’enseignement, la traduction et les métiers de la culture.',
            'posts_title' => 'Actualités culturelles et académiques',
            'posts_intro' => 'Colloques, publications, activités étudiantes et vie littéraire de la FLSH.',
            'dean_name' => 'Pr. Diane Nkurunziza',
            'dean_message' => "Chères étudiantes, chers étudiants,\n\nLes humanités éclairent le présent et préparent des citoyens responsables. À la FLSH, vous apprendrez à lire le monde, à argumenter, à transmettre et à créer.\n\nJe vous invite à découvrir nos départements, nos activités de recherche et les débouchés ouverts par les lettres et les sciences humaines.",
            'welcome' => 'Bienvenue à la FLSH. Explorez les formations en lettres, langues et sciences humaines, ainsi que la vie culturelle du campus.',
            'hero_captions' => [
                ['badge' => 'FLSH', 'title' => 'Humanités pour éclairer le présent', 'text' => 'Lettres, langues, histoire, géographie et socio-anthropologie au service d’une société éclairée.'],
                ['badge' => 'Langues', 'title' => 'Maîtriser et transmettre', 'text' => 'Formations linguistiques et littéraires pour l’enseignement, la traduction et la culture.'],
                ['badge' => 'Société', 'title' => 'Comprendre les dynamiques sociales', 'text' => 'Histoire, géographie et socio-anthropologie pour analyser le monde contemporain.'],
                ['badge' => 'Culture', 'title' => 'Patrimoine et création', 'text' => 'Recherche et médiation culturelle autour des mémoires et des expressions artistiques.'],
                ['badge' => 'Communauté', 'title' => 'Ouverts sur le débat public', 'text' => 'Colloques, publications et partenariats avec les médias et les institutions culturelles.'],
            ],
            'highlights' => [
                ['icon' => 'bi-book', 'title' => 'Lettres et langues', 'description' => 'Littératures, linguistique et traduction pour transmettre le goût des textes.'],
                ['icon' => 'bi-clock-history', 'title' => 'Histoire et société', 'description' => 'Comprendre les trajectoires du Burundi et de la région.'],
                ['icon' => 'bi-globe-europe-africa', 'title' => 'Territoires et sociétés', 'description' => 'Géographie, sociologie et anthropologie au plus près des réalités du Burundi.'],
                ['icon' => 'bi-chat-quote', 'title' => 'Débat public', 'description' => 'Colloques, publications et engagement citoyen des étudiants.'],
            ],
            'dean_role' => 'Doyenne de la Faculté',
            'dean_specialty' => 'Littératures francophones',
            'dean_title' => 'Bienvenue à la FLSH',
            'history' => [
                'label' => 'Notre parcours',
                'title' => 'Historique de la FLSH',
                'text'  => 'Cultiver l’esprit critique et transmettre les humanités au Burundi depuis plusieurs décennies.',
            ],
            'offer_title' => 'Lettres, langues et sciences humaines',
            'offer_text' => 'Des parcours ouverts sur l’enseignement, la culture, les médias et la recherche en sciences humaines.',
            'faculty_banner' => 'Faculté des Lettres et des Sciences Humaines',
            'contact_banner' => 'Campus Mutanga — FLSH',
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
            'formations_banner' => 'Langues, lettres, histoire, géographie et socio-anthropologie',
            'research_banner' => 'Patrimoine, langues et dynamiques sociales',
            'staff_banner' => 'Enseignants-chercheurs en sciences humaines',
            'alumni_banner' => 'Enseignants, journalistes et médiateurs culturels',
            'posts_banner' => 'Colloques, publications et vie littéraire',
            'publications_title' => 'Publications en sciences humaines',
            'publications_intro' => 'Travaux sur la littérature, l’histoire, la géographie et les langues nationales.',
            'projects_title' => 'Projets culturels et éducatifs',
            'alumni_intro' => [
                'Les diplômés de la FLSH enseignent, écrivent, traduisent, animent des médias et conduisent des projets culturels ou éducatifs.',
                'Les profils présentés illustrent la richesse des débouchés ouverts par les humanités.',
            ],
            'alumni_profiles_text' => 'Parcours de journalistes, enseignants, traducteurs et chercheurs en sciences humaines.',
            'alumni_cta' => 'Contactez le secrétariat pour rejoindre le réseau alumni de la FLSH.',
            'contact_intro' => 'Pour les admissions, les stages ou les partenariats culturels, écrivez à la FLSH.',
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
        $meta = $this->normalizeThemeMeta($meta, $key);
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

    /**
     * Departments and options of the University of Burundi faculties. FSI
     * follows its official maquettes (licence in four years, Bac I shared by
     * EM, GC and AU, nested TIC trunks). Doctoral studies are not listed:
     * they are run by a separate website.
     *
     * @return list<array<string, mixed>>
     */
    private function programmeSet(string $key): array
    {
        $p = static fn (string $level, string $slug, string $title, string $summary, string $description, array $careers, array $extra = []): array => $extra + [
            'level'       => $level,
            'slug'        => $slug,
            'title'       => $title,
            'summary'     => $summary,
            'description' => $description,
            'careers'     => $careers,
        ];
        $core = static fn (string $code, string $fr, string $en, int $years, ?string $branch = null, ?string $branchEn = null, int $branchYears = 1): array => array_filter([
            'option_code'       => $code,
            'common_core'       => $fr,
            'common_core_en'    => $en,
            'common_core_years' => $years,
            'branch_core'       => $branch,
            'branch_core_en'    => $branchEn,
            'branch_core_years' => $branch === null ? null : $branchYears,
        ], static fn ($value): bool => $value !== null);

        $sets = [
            'eco' => [
                $p('licence', 'licence-comptabilite-finance', 'Licence en Gestion — Comptabilité et finance', 'Comptabilité générale et analytique, finance d’entreprise, fiscalité et audit.', 'Option du département de Gestion. Après le tronc commun FSEG en Bac I et le tronc commun Gestion en Bac II, l’option forme aux métiers du chiffre : comptabilité, contrôle de gestion, finance d’entreprise, fiscalité burundaise et audit. Les études de cas et le stage de Bac IV se déroulent dans des cabinets, des banques et des administrations.', ['Comptable', 'Assistant d’audit', 'Analyste financier', 'Poursuite en Master'], $core('CF', 'Tronc commun FSEG', 'FSEG common core', 1, 'Tronc commun Gestion', 'Management common core')),
                $p('licence', 'licence-marketing-management', 'Licence en Gestion — Marketing et management', 'Management des organisations, marketing, ressources humaines et entrepreneuriat.', 'Option du département de Gestion, qui partage le tronc commun FSEG en Bac I puis le tronc commun Gestion en Bac II avec l’option Comptabilité et finance. À partir du Bac III : marketing, gestion des ressources humaines, stratégie, entrepreneuriat et projet de création d’entreprise.', ['Chargé de marketing', 'Responsable des ressources humaines', 'Entrepreneur', 'Poursuite en Master'], $core('MM', 'Tronc commun FSEG', 'FSEG common core', 1, 'Tronc commun Gestion', 'Management common core')),
                $p('licence', 'licence-economie-politique', 'Licence en Économie politique', 'Microéconomie, macroéconomie, économétrie, économie publique et politique économique.', 'Option du département d’Économie politique. Après le tronc commun FSEG en Bac I, la spécialisation commence dès le Bac II : théorie économique, économétrie, économie monétaire, finances publiques et politiques de développement, avec un travail de fin de cycle sur les réalités du Burundi.', ['Économiste', 'Chargé d’études', 'Analyste de politiques publiques', 'Poursuite en Master'], $core('EP', 'Tronc commun FSEG', 'FSEG common core', 1)),
                $p('licence', 'licence-economie-rurale', 'Licence en Économie rurale et entreprises agroalimentaires', 'Économie agricole, filières agroalimentaires, développement rural et gestion des coopératives.', 'Option du département d’Économie rurale et entreprises agroalimentaires. Après le tronc commun FSEG en Bac I, les étudiants étudient dès le Bac II l’économie agricole, les filières (café, thé, riz, lait), la gestion des coopératives et l’évaluation de projets de développement rural.', ['Économiste rural', 'Gestionnaire de coopérative', 'Chargé de projets de développement', 'Poursuite en Master'], $core('ER', 'Tronc commun FSEG', 'FSEG common core', 1)),
                $p('master', 'master-sciences-gestion', 'Master en Sciences de gestion', 'Management stratégique, finance, contrôle de gestion et recherche en gestion.', 'Master du département de Gestion : stratégie, finance d’entreprise, contrôle de gestion, gouvernance des organisations et méthodes de recherche. Le mémoire s’appuie sur une organisation burundaise ou régionale.', ['Cadre financier', 'Contrôleur de gestion', 'Consultant', 'Enseignant-chercheur'], ['option_code' => 'MSG']),
                $p('master', 'master-analyse-economique-developpement', 'Master en Analyse économique et développement', 'Macroéconomie appliquée, économétrie, économie du développement et évaluation des politiques.', 'Master du département d’Économie politique : modélisation macroéconomique, économétrie appliquée, économie du développement et évaluation d’impact des politiques publiques dans la région des Grands Lacs.', ['Économiste à la Banque centrale', 'Analyste en institution internationale', 'Chargé d’évaluation', 'Enseignant-chercheur'], ['option_code' => 'AED']),
                $p('master', 'master-economie-rurale-sociale-environnement', 'Master en Économie rurale, sociale et de l’environnement', 'Économie de l’environnement, développement rural, sécurité alimentaire et politiques sociales.', 'Master du département d’Économie rurale et entreprises agroalimentaires : économie de l’environnement et des ressources naturelles, sécurité alimentaire, protection sociale et évaluation de projets ruraux.', ['Économiste de l’environnement', 'Chargé de programmes agricoles', 'Expert en développement rural', 'Enseignant-chercheur'], ['option_code' => 'ERSE']),
            ],
            'sci' => [
                $p('licence', 'licence-genie-mecanique-energetique', 'Licence en Génie mécanique et énergétique', 'Mécanique, thermodynamique, machines thermiques et hydrauliques, énergies renouvelables.', 'Filière du département d’Électromécanique (EM). Après le tronc commun FSI en Bac I, partagé avec le Génie civil et l’Aménagement et urbanisme, la filière se spécialise dès le Bac II : résistance des matériaux, mécanique des fluides, thermodynamique, turbomachines, fabrication mécanique et énergies renouvelables. Le Bac IV se termine par un stage en entreprise et un projet de fin de cycle.', ['Ingénieur de maintenance', 'Technicien en énergie', 'Responsable d’atelier mécanique', 'Poursuite en Master'], $core('GME', 'Tronc commun FSI', 'FSI common core', 1)),
                $p('licence', 'licence-genie-electrique', 'Licence en Génie électrique', 'Circuits et machines électriques, installations, production et réseaux d’énergie électrique.', 'Filière du département d’Électromécanique (EM). Après le tronc commun FSI en Bac I, la filière se spécialise dès le Bac II : circuits électriques, appareillages, électronique industrielle, installations électriques, production d’énergie, réseaux haute tension, photovoltaïque et éolien.', ['Électrotechnicien', 'Ingénieur réseaux électriques', 'Chargé d’installations solaires', 'Poursuite en Master'], $core('GEL', 'Tronc commun FSI', 'FSI common core', 1)),
                $p('licence', 'licence-batiments-infrastructures', 'Licence en Génie civil — Bâtiments et infrastructures', 'Structures, béton armé, mécanique des sols, ponts et organisation des chantiers.', 'Option du département de Génie civil. Le Bac I est le tronc commun FSI ; le Bac II est commun aux deux options du génie civil (matériaux, résistance des matériaux, dessin du bâtiment, géologie et hydrologie de l’ingénieur). L’option Bâtiments et infrastructures commence au Bac III : béton armé et précontraint, mécanique des structures, ponts, métré et conduite de chantier.', ['Ingénieur structures', 'Conducteur de travaux', 'Métreur', 'Poursuite en Master'], $core('BI', 'Tronc commun FSI', 'FSI common core', 1, 'Tronc commun Génie civil', 'Civil engineering common core')),
                $p('licence', 'licence-genie-hydraulique', 'Licence en Génie civil — Génie hydraulique', 'Hydraulique appliquée, ressources en eau, barrages et ouvrages hydrauliques.', 'Option du département de Génie civil. Après le tronc commun FSI en Bac I et le tronc commun Génie civil en Bac II, l’option Génie hydraulique forme dès le Bac III à la gestion intégrée des ressources en eau, à l’hydraulique appliquée, aux barrages et ouvrages connexes et aux systèmes d’information géographique.', ['Ingénieur hydraulicien', 'Chargé d’adduction d’eau', 'Gestionnaire de ressources en eau', 'Poursuite en Master'], $core('GH', 'Tronc commun FSI', 'FSI common core', 1, 'Tronc commun Génie civil', 'Civil engineering common core')),
                $p('licence', 'licence-amenagement-urbanisme', 'Licence en Aménagement et urbanisme', 'Organisation de l’espace, économie urbaine, droit foncier et projets d’aménagement.', 'Formation du département d’Aménagement et urbanisme. Après le tronc commun FSI en Bac I, elle aborde dès le Bac II l’économie, la démographie, le développement durable et la construction, puis l’organisation de l’espace, le droit foncier, l’économie urbaine, la route et l’architecture.', ['Urbaniste', 'Chargé d’aménagement du territoire', 'Technicien du cadastre', 'Poursuite en Master'], $core('AMU', 'Tronc commun FSI', 'FSI common core', 1)),
                $p('licence', 'licence-genie-informatique', 'Licence en Génie informatique', 'Systèmes parallèles et répartis, sécurité, cloud, intelligence artificielle.', 'Option du département TIC. Tronc commun TIC en Bac I (mathématiques, électronique, architecture des ordinateurs, algorithmique, programmation C), puis tronc commun GI-GL en Bac II (bases de données, génie logiciel, réseaux, programmation objet). Dès le Bac III : systèmes répartis, cybersécurité, cryptologie, compilation, cloud computing, intelligence artificielle et robotique.', ['Administrateur systèmes', 'Ingénieur sécurité', 'Ingénieur infrastructure', 'Poursuite en Master'], $core('GI', 'Tronc commun TIC', 'ICT common core', 1, 'Tronc commun GI-GL', 'GI-GL common core')),
                $p('licence', 'licence-genie-logiciel', 'Licence en Génie logiciel', 'Conception de systèmes (MERISE, UML), données, data mining et développement d’applications.', 'Option du département TIC. Elle partage le tronc commun TIC en Bac I et le tronc commun GI-GL en Bac II avec le Génie informatique. Dès le Bac III : ingénierie des systèmes avec MERISE et UML, entrepôts de données, data mining, programmation mobile et événementielle, traitement d’images.', ['Développeur', 'Analyste-programmeur', 'Ingénieur données', 'Poursuite en Master'], $core('GL', 'Tronc commun TIC', 'ICT common core', 1, 'Tronc commun GI-GL', 'GI-GL common core')),
                $p('licence', 'licence-reseaux-telecommunications', 'Licence en Réseaux et télécommunications', 'Réseaux WAN, commutation, communications et transmissions numériques.', 'Option du département TIC. Après le tronc commun TIC en Bac I et le tronc commun SE-RT en Bac II (signal numérique, systèmes de communication, réseaux mobiles, antennes et hyperfréquences), l’option aborde dès le Bac III les réseaux WAN et Internet, la commutation, les communications et réseaux de transmission numériques.', ['Technicien réseaux', 'Ingénieur télécoms', 'Administrateur réseau', 'Poursuite en Master'], $core('RT', 'Tronc commun TIC', 'ICT common core', 1, 'Tronc commun SE-RT', 'SE-RT common core')),
                $p('licence', 'licence-systemes-embarques', 'Licence en Systèmes embarqués', 'Internet des objets, microcontrôleurs, circuits intégrés et applications embarquées.', 'Option du département TIC. Elle partage le tronc commun TIC en Bac I et le tronc commun SE-RT en Bac II avec Réseaux et télécommunications. Dès le Bac III : Internet des objets, semi-conducteurs et circuits intégrés, conception et développement de systèmes embarqués, langages machines.', ['Ingénieur systèmes embarqués', 'Développeur IoT', 'Technicien en électronique', 'Poursuite en Master'], $core('SE', 'Tronc commun TIC', 'ICT common core', 1, 'Tronc commun SE-RT', 'SE-RT common core')),
                $p('master', 'master-genie-logiciel', 'Master en Génie logiciel', 'Architecture logicielle, qualité, cloud et conduite de projets.', 'Master du département TIC : architectures logicielles, méthodes agiles, qualité et tests, cloud, sécurité applicative et mémoire en lien avec une entreprise ou un laboratoire.', ['Architecte logiciel', 'Chef de projet informatique', 'Ingénieur DevOps', 'Enseignant-chercheur'], ['option_code' => 'GL']),
                $p('master', 'master-reseaux-telecommunications', 'Master en Réseaux et télécommunications', 'Réseaux avancés, radiocommunications, sécurité et ingénierie des opérateurs.', 'Master du département TIC : réseaux haut débit, radiocommunications, virtualisation des réseaux, sécurité et ingénierie des opérateurs, avec un mémoire appliqué.', ['Ingénieur réseaux', 'Expert télécoms', 'Responsable sécurité', 'Enseignant-chercheur'], ['option_code' => 'RT']),
            ],
            'med' => [
                $p('licence', 'baccalaureat-medecine', 'Baccalauréat en Médecine', 'Six années : sciences fondamentales, sémiologie, pathologies et stages cliniques au CHUK.', 'Le premier cycle des études médicales dure six ans dans le système BMD. Les premières années couvrent les sciences fondamentales et biomédicales ; les suivantes, la sémiologie, la pathologie et la thérapeutique, avec des stages cliniques au Centre Hospitalo-Universitaire de Kamenge (CHUK) et dans les hôpitaux partenaires. La formation se termine par un mémoire.', ['Médecin généraliste', 'Médecin de district sanitaire', 'Poursuite en spécialisation (DES)'], ['option_code' => 'MED']),
                $p('master', 'des-medecine-interne', 'DES en Médecine interne', 'Spécialisation en médecine interne au CHUK.', 'Diplôme d’études spécialisées du département de Médecine interne : la première année est commune aux spécialités médicales (pédiatrie et médecine interne) ; viennent ensuite les stages hospitaliers, les gardes et la recherche clinique.', ['Médecin interniste', 'Chef de service hospitalier', 'Enseignant clinicien'], $core('MI', 'Tronc commun des spécialités médicales', 'Medical specialties common core', 1)),
                $p('master', 'des-pediatrie', 'DES en Pédiatrie', 'Spécialisation en santé de l’enfant et néonatologie.', 'Diplôme d’études spécialisées du département de Pédiatrie, qui partage la première année avec la médecine interne, puis se consacre à la néonatologie, aux urgences pédiatriques et à la santé de l’enfant.', ['Pédiatre', 'Néonatologiste', 'Enseignant clinicien'], $core('PED', 'Tronc commun des spécialités médicales', 'Medical specialties common core', 1)),
                $p('master', 'des-chirurgie-generale', 'DES en Chirurgie générale', 'Spécialisation chirurgicale : bloc opératoire, traumatologie et urgences.', 'Diplôme d’études spécialisées du département de Chirurgie. La première année est commune aux spécialités chirurgicales (chirurgie, gynécologie-obstétrique, anesthésie-réanimation) ; suivent la chirurgie viscérale, la traumatologie et les urgences.', ['Chirurgien', 'Chef de bloc opératoire', 'Enseignant clinicien'], $core('CHIR', 'Tronc commun des spécialités chirurgicales', 'Surgical specialties common core', 1)),
                $p('master', 'des-gynecologie-obstetrique', 'DES en Gynécologie-obstétrique', 'Santé de la femme, suivi de grossesse et chirurgie gynécologique.', 'Diplôme d’études spécialisées du département de Gynécologie-obstétrique, qui partage la première année avec les autres spécialités chirurgicales, puis se consacre à l’obstétrique, à la chirurgie gynécologique et à la santé maternelle.', ['Gynécologue-obstétricien', 'Responsable de maternité', 'Enseignant clinicien'], $core('GO', 'Tronc commun des spécialités chirurgicales', 'Surgical specialties common core', 1)),
                $p('master', 'des-anesthesie-reanimation', 'DES en Anesthésie-réanimation', 'Anesthésie, réanimation et médecine d’urgence.', 'Diplôme d’études spécialisées du département d’Anesthésie-réanimation, qui partage la première année avec les spécialités chirurgicales, puis forme à l’anesthésie, à la réanimation et à la prise en charge des urgences vitales.', ['Anesthésiste-réanimateur', 'Médecin urgentiste', 'Enseignant clinicien'], $core('AR', 'Tronc commun des spécialités chirurgicales', 'Surgical specialties common core', 1)),
                $p('master', 'master-sante-publique', 'Master en Santé publique', 'Épidémiologie, biostatistique, systèmes et politiques de santé.', 'Master du département de Santé communautaire : épidémiologie, biostatistique, gestion des programmes et des systèmes de santé, promotion de la santé et mémoire de terrain.', ['Épidémiologiste', 'Gestionnaire de programme de santé', 'Médecin de santé publique'], ['option_code' => 'MSP']),
            ],
            'agro' => [
                $p('licence', 'licence-production-vegetale', 'Licence en Sciences de la production végétale', 'Phytotechnie, amélioration et protection des plantes, cultures vivrières et industrielles.', 'Option du département des Sciences de la production végétale (SPV). Le tronc commun FABI couvre le Bac I et le Bac II (biologie, chimie, mathématiques, pédologie, économie rurale) ; la spécialisation commence au Bac III : phytotechnie, amélioration variétale, défense des cultures et production de semences.', ['Agronome', 'Chef de projet agricole', 'Technicien semencier', 'Poursuite en Master'], $core('SPV', 'Tronc commun FABI', 'FABI common core', 2)),
                $p('licence', 'licence-production-animale', 'Licence en Sciences de la production animale', 'Zootechnie, nutrition et santé animales, aquaculture.', 'Option du département des Sciences de la production animale (SPA). Après le tronc commun FABI en Bac I et Bac II, les étudiants étudient la zootechnie, l’alimentation et l’amélioration animales, l’aquaculture et la gestion d’élevage.', ['Zootechnicien', 'Gestionnaire d’élevage', 'Conseiller en production animale', 'Poursuite en Master'], $core('SPA', 'Tronc commun FABI', 'FABI common core', 2)),
                $p('licence', 'licence-technologies-agroalimentaires', 'Licence en Sciences et technologies agroalimentaires', 'Transformation, conservation et qualité des produits agricoles.', 'Option du département des Sciences et technologies agroalimentaires (STA). Après le tronc commun FABI, la formation porte sur la transformation et la conservation des produits, le contrôle de qualité, la sécurité sanitaire des aliments et la nutrition.', ['Technologue agroalimentaire', 'Responsable qualité', 'Entrepreneur agroalimentaire', 'Poursuite en Master'], $core('STA', 'Tronc commun FABI', 'FABI common core', 2)),
                $p('licence', 'licence-environnement', 'Licence en Sciences et technologies de l’environnement', 'Écologie, gestion des ressources naturelles, eau et changement climatique.', 'Option du département des Sciences et technologies de l’environnement (STE). Après le tronc commun FABI, la formation traite de l’écologie, de la gestion des eaux et des forêts, de l’agroforesterie et de l’adaptation au changement climatique.', ['Environnementaliste', 'Chargé de conservation', 'Consultant en études d’impact', 'Poursuite en Master'], $core('STE', 'Tronc commun FABI', 'FABI common core', 2)),
                $p('licence', 'licence-socio-economie-rurale', 'Licence en Socio-économie rurale', 'Économie des exploitations, filières, vulgarisation et développement rural.', 'Option du département de Socio-économie rurale (SER). Après le tronc commun FABI, la formation porte sur l’économie des exploitations familiales, les filières agricoles, la vulgarisation, la sociologie rurale et la gestion de projets.', ['Agroéconomiste', 'Vulgarisateur', 'Chargé de développement rural', 'Poursuite en Master'], $core('SER', 'Tronc commun FABI', 'FABI common core', 2)),
                $p('licence', 'licence-sciences-agro-environnementales', 'Licence en Sciences agro-environnementales', 'Sciences du sol, aptitude des terres, conservation des eaux et des sols.', 'Option du département des Sciences agro-environnementales (SAE). Après le tronc commun FABI, la formation aborde la science du sol, l’évaluation de l’aptitude des terres, la fertilité, la conservation des eaux et des sols et l’aménagement des bassins versants.', ['Pédologue', 'Chargé d’aménagement des terres', 'Conseiller en fertilité des sols', 'Poursuite en Master'], $core('SAE', 'Tronc commun FABI', 'FABI common core', 2)),
                $p('master', 'master-environnement', 'Master en Sciences et technologies de l’environnement', 'Gestion durable des écosystèmes, évaluation environnementale et climat.', 'Master du département STE : gestion durable des écosystèmes, évaluation environnementale, changement climatique et mémoire de recherche appliquée au Burundi.', ['Expert environnemental', 'Chargé de programmes climat', 'Enseignant-chercheur'], ['option_code' => 'STE']),
                $p('master', 'master-socio-economie-rurale', 'Master en Socio-économie rurale', 'Politiques agricoles, filières, sécurité alimentaire et évaluation de projets.', 'Master du département SER : politiques agricoles, analyse des filières, sécurité alimentaire, évaluation de projets et méthodes de recherche en sciences sociales.', ['Économiste agricole', 'Chargé de suivi-évaluation', 'Enseignant-chercheur'], ['option_code' => 'SER']),
                $p('master', 'master-sciences-agro-environnementales', 'Master en Sciences agro-environnementales', 'Fertilité des sols, agroécologie et gestion des terres.', 'Master du département SAE : fertilité et gestion des sols, agroécologie, systèmes d’information géographique et aménagement des terres.', ['Expert en gestion des terres', 'Agroécologue', 'Enseignant-chercheur'], ['option_code' => 'SAE']),
            ],
            'hum' => [
                $p('licence', 'licence-etudes-litteratures-africaines', 'Licence en Études et littératures africaines', 'Linguistique africaine, kirundi, littératures et cultures africaines.', 'Option du département de Langues et cultures africaines (LCA). Le Bac I et le Bac II sont communs aux deux options du département : langues et linguistique africaines, littératures orales et écrites, anthropologie culturelle du Burundi. La spécialisation commence au Bac III.', ['Enseignant de kirundi', 'Traducteur', 'Chargé de patrimoine culturel', 'Poursuite en Master'], $core('ELA', 'Tronc commun Langues et cultures africaines', 'African languages and cultures common core', 2)),
                $p('licence', 'licence-entrepreneuriat-culturel', 'Licence en Entrepreneuriat culturel', 'Industries culturelles, gestion de projets artistiques et valorisation du patrimoine.', 'Filière du département de Langues et cultures africaines, qui partage le tronc commun du département en Bac I et Bac II. Dès le Bac III : industries culturelles et créatives, gestion de projets artistiques, médiation et valorisation du patrimoine burundais.', ['Entrepreneur culturel', 'Gestionnaire de projets artistiques', 'Médiateur culturel', 'Poursuite en Master'], $core('EC', 'Tronc commun Langues et cultures africaines', 'African languages and cultures common core', 2)),
                $p('licence', 'licence-lettres-francaises', 'Licence en Langue et littérature françaises', 'Littératures francophones, grammaire, linguistique et didactique.', 'Option du département de Langue et littérature françaises (LLF). Le Bac I et le Bac II sont communs aux deux options du département (grammaire, phonétique, histoire littéraire, littératures francophones) ; la spécialisation commence au Bac III.', ['Enseignant de français', 'Journaliste', 'Rédacteur', 'Poursuite en Master'], $core('LF', 'Tronc commun Lettres françaises', 'French studies common core', 2)),
                $p('licence', 'licence-sciences-langage-creation-litteraire', 'Licence en Sciences du langage et création littéraire', 'Linguistique, analyse du discours, écriture créative et édition.', 'Option du département de Langue et littérature françaises, qui partage le tronc commun du département en Bac I et Bac II. Dès le Bac III : sciences du langage, sociolinguistique, analyse du discours, ateliers d’écriture et édition — en lien avec le prix littéraire Rumuri.', ['Écrivain', 'Éditeur', 'Correcteur', 'Poursuite en Master'], $core('SLCL', 'Tronc commun Lettres françaises', 'French studies common core', 2)),
                $p('licence', 'licence-anglais', 'Licence en Langue et littérature anglaises', 'English linguistics, literatures in English, translation and teaching.', 'Formation du département de Langue et littérature anglaises (LLA) : phonologie et grammaire de l’anglais, littératures anglophones et africaines d’expression anglaise, traduction et didactique de l’anglais.', ['Enseignant d’anglais', 'Traducteur-interprète', 'Chargé de communication', 'Poursuite en Master'], ['option_code' => 'LLA']),
                $p('licence', 'licence-histoire-patrimoine', 'Licence en Histoire et patrimoine', 'Histoire du Burundi et de l’Afrique des Grands Lacs, archéologie et patrimoine.', 'Filière du département d’Histoire : histoire ancienne et contemporaine du Burundi et de l’Afrique des Grands Lacs, critique historique, archives, archéologie et valorisation du patrimoine.', ['Enseignant d’histoire', 'Archiviste', 'Guide du patrimoine', 'Poursuite en Master'], ['option_code' => 'HP']),
                $p('licence', 'licence-geographie', 'Licence en Géographie', 'Géographie physique et humaine, cartographie, SIG et aménagement.', 'Formation du département de Géographie : géographie physique et humaine, cartographie et systèmes d’information géographique, aménagement du territoire et environnement.', ['Enseignant de géographie', 'Cartographe', 'Chargé d’aménagement', 'Poursuite en Master'], ['option_code' => 'GEO']),
                $p('licence', 'licence-anthropologie', 'Licence en Anthropologie', 'Anthropologie sociale et culturelle, enquêtes de terrain et patrimoine immatériel.', 'Filière du département de Socio-anthropologie. Le Bac I et le Bac II sont communs avec la sociologie ; dès le Bac III : anthropologie sociale et culturelle, parenté, religions, patrimoine immatériel et enquêtes de terrain.', ['Anthropologue', 'Chargé d’études sociales', 'Médiateur culturel', 'Poursuite en Master'], $core('ANT', 'Tronc commun Socio-anthropologie', 'Socio-anthropology common core', 2)),
                $p('licence', 'licence-sociologie', 'Licence en Sociologie', 'Sociologie générale, méthodes d’enquête, développement et cohésion sociale.', 'Filière du département de Socio-anthropologie, qui partage le tronc commun du département en Bac I et Bac II. Dès le Bac III : sociologie du développement, de la famille et du travail, méthodes quantitatives et qualitatives.', ['Sociologue', 'Chargé de projets sociaux', 'Analyste en suivi-évaluation', 'Poursuite en Master'], $core('SOC', 'Tronc commun Socio-anthropologie', 'Socio-anthropology common core', 2)),
                $p('master', 'master-societes-pouvoirs-territoires', 'Master Sociétés, pouvoirs, territoires et développement durable', 'Histoire et géographie des sociétés, gouvernance des territoires et développement durable.', 'Master porté par le département d’Histoire avec la Géographie : sociétés et pouvoirs dans l’Afrique des Grands Lacs, dynamiques territoriales, gouvernance et développement durable.', ['Chargé d’études territoriales', 'Expert en gouvernance', 'Enseignant-chercheur'], ['option_code' => 'SPTDD']),
                $p('master', 'master-socio-anthropologie', 'Master en Socio-anthropologie', 'Recherche en sociologie et anthropologie du Burundi contemporain.', 'Master du département de Socio-anthropologie : théories sociologiques et anthropologiques, méthodes de recherche, développement, conflits et cohésion sociale.', ['Chercheur en sciences sociales', 'Consultant', 'Enseignant-chercheur'], ['option_code' => 'SOA']),
                $p('master', 'master-communication-interculturelle-traduction', 'Master en Communication interculturelle et traduction', 'Traduction, interprétation et communication entre langues et cultures.', 'Master du département de Langue et littérature anglaises : théorie et pratique de la traduction (anglais, français, kirundi, kiswahili), interprétation et communication interculturelle.', ['Traducteur', 'Interprète', 'Chargé de communication internationale'], ['option_code' => 'CIT']),
                $p('master', 'master-didactique-francais', 'Master en Didactique du français', 'Enseignement du français langue seconde, ingénierie de formation et évaluation.', 'Master du département de Langue et littérature françaises : didactique du français langue seconde, conception de programmes, évaluation et recherche en éducation.', ['Formateur d’enseignants', 'Conseiller pédagogique', 'Enseignant-chercheur'], ['option_code' => 'DF']),
            ],
        ];

        $admission = [
            'eco'  => ['licence' => 'Diplôme d’État (toutes sections) avec les points fixés par le ministère ; orientation par le Bureau des admissions.', 'master' => 'Baccalauréat (licence) en économie ou en gestion et sélection sur dossier.'],
            'sci'  => ['licence' => 'Diplôme d’État scientifique ou technique avec les points fixés par le ministère ; orientation vers la FSI.', 'master' => 'Baccalauréat (licence) en ingénierie ou en informatique et sélection sur dossier.'],
            'med'  => ['licence' => 'Diplôme d’État scientifique (biologie-chimie ou mathématiques-physique) avec les points fixés par le ministère.', 'master' => 'Baccalauréat en médecine, inscription à l’Ordre des médecins et concours d’entrée en spécialisation.'],
            'agro' => ['licence' => 'Diplôme d’État scientifique avec les points fixés par le ministère ; orientation vers la FABI.', 'master' => 'Baccalauréat (licence) en agronomie, environnement ou économie rurale et sélection sur dossier.'],
            'hum'  => ['licence' => 'Diplôme d’État (toutes sections) avec les points fixés par le ministère ; orientation vers la FLSH.', 'master' => 'Baccalauréat (licence) dans le domaine et sélection sur dossier.'],
        ];
        $key = isset($sets[$key]) ? $key : 'eco';

        return array_map(static function (array $row) use ($key, $admission): array {
            $years = $row['level'] === 'master'
                ? (str_starts_with($row['slug'], 'des-') ? 4 : 2)
                : ($key === 'med' ? 6 : 4);

            return $row + ['duration' => $years . ' ans', 'admission' => $admission[$key][$row['level']]];
        }, $sets[$key]);
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
                'bio' => 'Prof. Jean-Baptiste Ndayishimiye est Doyen de la Faculté à la FSEG. Spécialisé en Économie & Développement, il contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Il publie régulièrement et accompagne les projets de fin d’études.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Marie-Claire Hakizimana',
                'slug' => 'hakizimana',
                'grade' => 'Maître de Conférences',
                'specialty' => 'Finance & Comptabilité',
                'role' => 'Cheffe du département de Gestion',
                'email' => 'mc.hakizimana@ub.edu.bi',
                'bio' => 'Dr. Marie-Claire Hakizimana est cheffe du département de Gestion à la FSEG. Spécialisée en Finance & Comptabilité, elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Elle publie régulièrement et accompagne les projets de fin d’études.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Pierre Nkurunziza',
                'slug' => 'nkurunziza',
                'grade' => 'Maître de Conférences',
                'specialty' => 'Gestion des Entreprises',
                'role' => 'Responsable Master Management',
                'email' => 'p.nkurunziza@ub.edu.bi',
                'bio' => 'Dr. Pierre Nkurunziza est Responsable Master Management à la FSEG. Spécialisé en Gestion des Entreprises, il contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Il publie régulièrement et accompagne les projets de fin d’études.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Aline Ntakarutimana',
                'slug' => 'ntakarutimana',
                'grade' => 'Maître Assistant',
                'specialty' => 'Économie du Développement',
                'role' => 'Directrice URPP',
                'email' => 'a.ntakarutimana@ub.edu.bi',
                'bio' => 'Dr. Aline Ntakarutimana est Directrice URPP à la FSEG. Spécialisée en Économie du Développement, elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Elle publie régulièrement et accompagne les projets de fin d’études.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Faustin Bigirimana',
                'slug' => 'bigirimana',
                'grade' => 'Maître Assistant',
                'specialty' => 'Audit & Contrôle de Gestion',
                'role' => 'Directeur LFC',
                'email' => 'f.bigirimana@ub.edu.bi',
                'bio' => 'Dr. Faustin Bigirimana est Directeur LFC à la FSEG. Spécialisé en Audit & Contrôle de Gestion, il contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Il publie régulièrement et accompagne les projets de fin d’études.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Rose Manirakiza',
                'slug' => 'manirakiza',
                'grade' => 'Assistante',
                'specialty' => 'Économie rurale',
                'role' => 'Cheffe du département d’Économie rurale et entreprises agroalimentaires',
                'email' => 'r.manirakiza@ub.edu.bi',
                'bio' => 'Elle enseigne l’économie des filières agroalimentaires et accompagne les coopératives dans l’évaluation de leurs projets.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Cyprien Dusabimana',
                'slug' => 'dusabimana',
                'grade' => 'Maître Assistant',
                'specialty' => 'Économétrie',
                'role' => 'Chef du département d’Économie politique, directeur LEA',
                'email' => 'c.dusabimana@ub.edu.bi',
                'bio' => 'Dr. Cyprien Dusabimana est Directeur LEA à la FSEG. Spécialisé en Économétrie, il contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Il publie régulièrement et accompagne les projets de fin d’études.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Geneviève Niyonkuru',
                'slug' => 'niyonkuru',
                'grade' => 'Assistante',
                'specialty' => 'Ressources Humaines',
                'role' => 'Enseignante-Chercheure',
                'email' => 'g.niyonkuru@ub.edu.bi',
                'bio' => 'Dr. Geneviève Niyonkuru est Enseignante-Chercheure à la FSEG. Spécialisée en Ressources Humaines, elle contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Elle publie régulièrement et accompagne les projets de fin d’études.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'M. Etienne Minani',
                'slug' => 'minani',
                'grade' => 'Assistant',
                'specialty' => 'Droit des Affaires',
                'role' => 'Enseignant',
                'email' => 'e.minani@ub.edu.bi',
                'bio' => 'M. Etienne Minani est Enseignant à la FSEG. Spécialisé en Droit des Affaires, il contribue à l’enseignement, à l’encadrement des étudiants et à la vie scientifique de la faculté. Il publie régulièrement et accompagne les projets de fin d’études.',
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
                'role' => 'Chef du département TIC',
                'email' => 'nadia.baraka@ub.edu.bi',
                'bio' => 'Spécialiste du génie logiciel et des architectures distribuées, elle encadre des projets étudiants avec les entreprises technologiques de Bujumbura.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Joseph Niyonkuru',
                'slug' => 'joseph-niyonkuru',
                'grade' => 'Maître de conférences',
                'specialty' => 'Génie logiciel et données',
                'role' => 'Responsable du Master Génie logiciel',
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
                'specialty' => 'Électrotechnique et énergie',
                'role' => 'Chef du département d’Électromécanique',
                'email' => 'aime.nsabimana@ub.edu.bi',
                'bio' => 'Ses recherches concernent le solaire photovoltaïque et l’efficacité énergétique dans les bâtiments publics.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Linda Muco',
                'slug' => 'linda-muco',
                'grade' => 'Professeur',
                'specialty' => 'Génie civil et matériaux',
                'role' => 'Cheffe du département de Génie civil',
                'email' => 'linda.muco@ub.edu.bi',
                'bio' => 'Elle développe des méthodes d’analyse pour le contrôle qualité industriel et environnemental.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Kevin Bizimana',
                'slug' => 'kevin-bizimana',
                'grade' => 'Chargé de cours',
                'specialty' => 'Génie électrique et systèmes embarqués',
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
                'specialty' => 'Urbanisme et aménagement',
                'role' => 'Cheffe du département d’Aménagement et urbanisme',
                'email' => 'solange.uwimana@ub.edu.bi',
                'bio' => 'Ses enseignements et publications portent sur les statistiques appliquées et la biostatistique.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Thierry Manirakiza',
                'slug' => 'thierry-manirakiza',
                'grade' => 'Chargé de cours',
                'specialty' => 'Génie hydraulique',
                'role' => 'Enseignant-chercheur',
                'email' => 'thierry.mani@ub.edu.bi',
                'bio' => 'Il enseigne l’hydraulique appliquée et les ouvrages hydrauliques, et encadre les projets de fin de cycle de l’option Génie hydraulique.',
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
                'role' => 'Cheffe du département de Médecine interne',
                'email' => 'olga.nibigira@ub.edu.bi',
                'bio' => 'Clinicienne expérimentée, elle encadre les stages hospitaliers et publie sur les maladies chroniques non transmissibles.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Placide Nduwimana',
                'slug' => 'placide-nduwimana',
                'grade' => 'Maître de conférences',
                'specialty' => 'Pédiatrie',
                'role' => 'Chef du département de Pédiatrie',
                'email' => 'placide.ndu@ub.edu.bi',
                'bio' => 'Ses travaux portent sur la nutrition infantile et la néonatalogie dans les contextes à ressources limitées.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Rachel Bigirimana',
                'slug' => 'rachel-bigirimana',
                'grade' => 'Chargée de cours',
                'specialty' => 'Épidémiologie',
                'role' => 'Cheffe du département de Santé communautaire',
                'email' => 'rachel.bigi@ub.edu.bi',
                'bio' => 'Elle forme les étudiants aux enquêtes épidémiologiques et collabore avec le ministère de la Santé.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Serge Niyonkuru',
                'slug' => 'serge-niyonkuru',
                'grade' => 'Maître-assistant',
                'specialty' => 'Chirurgie',
                'role' => 'Chef du département de Chirurgie',
                'email' => 'serge.niyo@ub.edu.bi',
                'bio' => 'Chirurgien hospitalier, il assure l’enseignement du geste technique et la simulation chirurgicale.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Alice Hakizimana',
                'slug' => 'alice-hakizimana',
                'grade' => 'Chargée de cours',
                'specialty' => 'Gynécologie-obstétrique',
                'role' => 'Cheffe du département de Gynécologie-obstétrique',
                'email' => 'alice.haki@ub.edu.bi',
                'bio' => 'Elle coordonne la formation en santé maternelle et les urgences obstétricales au CHUK.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Yves Manirakiza',
                'slug' => 'yves-manirakiza',
                'grade' => 'Professeur',
                'specialty' => 'Anesthésie-réanimation',
                'role' => 'Chef du département d’Anesthésie-réanimation',
                'email' => 'yves.mani@ub.edu.bi',
                'bio' => 'Il dirige le service de réanimation et la formation des résidents à l’anesthésie et aux urgences vitales.',
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
                'specialty' => 'Sciences biomédicales',
                'role' => 'Cheffe du département des Sciences biomédicales',
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
                'role' => 'Cheffe du département des Sciences de la production végétale',
                'email' => 'diane.niyo@ub.edu.bi',
                'bio' => 'Agronome de terrain, elle travaille sur les itinéraires techniques du café et des cultures vivrières.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Ir. Pacifique Habiyaremye',
                'slug' => 'pacifique-habiya',
                'grade' => 'Maître de conférences',
                'specialty' => 'Environnement',
                'role' => 'Chef du département des Sciences et technologies de l’environnement',
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
                'role' => 'Chef du département des Sciences agro-environnementales',
                'email' => 'c.nsa@ub.edu.bi',
                'bio' => 'Pédologue, il développe des protocoles d’analyse de fertilité adaptés aux sols d’altitude.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Ir. Solange Uwimana',
                'slug' => 'solange-uwi-agro',
                'grade' => 'Chargée de cours',
                'specialty' => 'Élevage',
                'role' => 'Cheffe du département des Sciences de la production animale',
                'email' => 's.uwi@ub.edu.bi',
                'bio' => 'Spécialiste des productions animales, elle anime les stages en fermes modèles.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Ir. Thierry Bizimana',
                'slug' => 'thierry-bizi',
                'grade' => 'Maître de conférences',
                'specialty' => 'Technologies agroalimentaires',
                'role' => 'Chef du département des Sciences et technologies agroalimentaires',
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
                'specialty' => 'Socio-économie rurale',
                'role' => 'Cheffe du département de Socio-économie rurale',
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
                'specialty' => 'Linguistique anglaise',
                'role' => 'Cheffe du département de Langue et littérature anglaises',
                'email' => 'g.nku@ub.edu.bi',
                'bio' => 'Elle enseigne la linguistique anglaise et la traduction, et coordonne le master en communication interculturelle et traduction.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Benjamin Hakizimana',
                'slug' => 'benjamin-haki',
                'grade' => 'Maître de conférences',
                'specialty' => 'Lettres modernes',
                'role' => 'Chef du département de Langue et littérature françaises',
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
                'role' => 'Chef du département d’Histoire',
                'email' => 'o.mani@ub.edu.bi',
                'bio' => 'Ses recherches portent sur l’histoire contemporaine du Burundi et les archives orales.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Diane Irakoze',
                'slug' => 'diane-ira-hum',
                'grade' => 'Chargée de cours',
                'specialty' => 'Socio-anthropologie',
                'role' => 'Cheffe du département de Socio-anthropologie',
                'email' => 'd.ira@ub.edu.bi',
                'bio' => 'Elle conduit des enquêtes ethnographiques sur les dynamiques sociales urbaines et rurales.',
            ],
            [
                'category' => 'enseignant',
                'name' => 'Dr. Samuel Ndayizeye',
                'slug' => 'samuel-ndayi',
                'grade' => 'Professeur',
                'specialty' => 'Géographie',
                'role' => 'Chef du département de Géographie',
                'email' => 's.ndayi@ub.edu.bi',
                'bio' => 'Il enseigne la géographie humaine et les systèmes d’information géographique, avec des terrains dans les provinces du pays.',
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
                'specialty' => 'Langues et cultures africaines',
                'role' => 'Cheffe du département de Langues et cultures africaines',
                'email' => 'l.muco@ub.edu.bi',
                'bio' => 'Elle enseigne la littérature orale et les langues nationales, et anime des projets de valorisation du patrimoine immatériel.',
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
                'title' => 'Séminaire de recherche — Méthodes quantitatives',
                'slug' => 'seminaire-methodes-quantitatives',
                'excerpt' => 'Un séminaire de deux jours destiné aux enseignants-chercheurs et aux étudiants de master de la FSEG a été organisé sur les méthodes quantitatives avancées en sciences économiques et de gestion. Animé par le Prof. Ndayishimiye et le Dr. Dusabimana.',
                'body' => '<p>Un séminaire de deux jours destiné aux enseignants-chercheurs et aux étudiants de master de la FSEG a été organisé sur les méthodes quantitatives avancées en sciences économiques et de gestion. Animé par le Prof. Ndayishimiye et le Dr. Dusabimana.</p><p>Cette publication illustre la vie académique de la FSEG : échanges avec les partenaires, mobilisation des enseignants et implication des étudiants. Les détails pratiques (horaires, inscriptions, contacts) sont mis à jour par le secrétariat au fur et à mesure de l’organisation.</p><p>Pour toute question, adressez-vous au secrétariat de la faculté ou consultez les annonces affichées sur le campus.</p>',
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
                'body' => '<p>Le hackathon annuel de la Faculté des Sciences de l’Ingénieur réunit des équipes pluridisciplinaires autour de défis proposés par des administrations et des PME technologiques.</p><p>Mentorat, ateliers techniques et jury final permettent de valoriser les meilleurs prototypes et d’ouvrir des stages.</p>',
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
                'url' => 'https://doi.org/10.1000/demo.eco.1',
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
                'url' => 'https://doi.org/10.1000/demo.eco.2',
            ],
            [
                'year' => 2023,
                'title' => 'Pratiques de contrôle de gestion dans les entreprises publiques burundaises',
                'authors' => 'Bigirimana F.',
                'journal' => 'Comptabilité, Contrôle, Audit — Afrique',
                'url' => 'https://doi.org/10.1000/demo.eco.3',
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
                'url' => 'https://doi.org/10.1000/demo.sci.1',
            ],
            [
                'year' => 2025,
                'title' => 'Architectures microservices pour les services publics numériques',
                'authors' => 'Barakamfitiye N., Bizimana K.',
                'journal' => 'Journal of African Software Engineering',
                'url' => 'https://doi.org/10.1000/demo.sci.2',
            ],
            [
                'year' => 2024,
                'title' => 'Apprentissage automatique appliqué aux données de consommation électrique',
                'authors' => 'Niyonkuru J., Uwimana S.',
                'journal' => 'African Data Science Review',
                'url' => 'https://doi.org/10.1000/demo.sci.3',
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
                'url' => 'https://doi.org/10.1000/demo.med.1',
            ],
            [
                'year' => 2025,
                'title' => 'Impact d’un module de simulation sur la confiance des étudiants infirmiers',
                'authors' => 'Hakizimana A., Nibigira O.',
                'journal' => 'African Journal of Nursing Education',
                'url' => 'https://doi.org/10.1000/demo.med.2',
            ],
            [
                'year' => 2024,
                'title' => 'Suivi prénatal et accès aux soins en milieu rural',
                'authors' => 'Habiyaremye P., Ndayizeye I.',
                'journal' => 'Santé et Développement',
                'url' => 'https://doi.org/10.1000/demo.med.3',
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
                'url' => 'https://doi.org/10.1000/demo.agro.1',
            ],
            [
                'year' => 2025,
                'title' => 'Traçabilité et qualité du café de spécialité au Burundi',
                'authors' => 'Ndayishimiye E., Niyongabo D.',
                'journal' => 'Journal of African Coffee Studies',
                'url' => 'https://doi.org/10.1000/demo.agro.2',
            ],
            [
                'year' => 2024,
                'title' => 'Restauration de bassins versants : enseignements d’un projet pilote',
                'authors' => 'Habiyaremye P., Bizimana T.',
                'journal' => 'Environnement et Développement',
                'url' => 'https://doi.org/10.1000/demo.agro.3',
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
                'url' => 'https://doi.org/10.1000/demo.hum.1',
            ],
            [
                'year' => 2025,
                'title' => 'Archives orales et écriture de l’histoire contemporaine',
                'authors' => 'Manirakiza O., Nsabimana A.',
                'journal' => 'Cahiers d’Histoire Régionale',
                'url' => 'https://doi.org/10.1000/demo.hum.2',
            ],
            [
                'year' => 2024,
                'title' => 'Pratiques journalistiques et éthique des médias numériques',
                'authors' => 'Nkurunziza G., Niyonkuru C.',
                'journal' => 'Communication et Société',
                'url' => 'https://doi.org/10.1000/demo.hum.3',
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
                'code' => 'DEVECO-BI',
                'icon' => 'bi-graph-up',
            ],
            [
                'title' => 'Inclusion financière en milieu rural',
                'description' => 'Inclusion financière et accès aux services bancaires pour les populations rurales Le projet mobilise enseignants-chercheurs et doctorants de la FSEG en partenariat avec les bailleurs.',
                'funder' => 'Banque Mondiale',
                'start' => 2024,
                'end' => 2026,
                'code' => 'FINCL-RUR',
                'icon' => 'bi-cash-coin',
            ],
            [
                'title' => 'Impact des politiques agricoles sur la sécurité alimentaire',
                'description' => 'Évaluation de l\'impact des politiques agricoles sur la sécurité alimentaire Le projet mobilise enseignants-chercheurs et doctorants de la FSEG en partenariat avec les bailleurs.',
                'funder' => 'FAO – Burundi',
                'start' => 2022,
                'end' => 2025,
                'code' => 'AGRI-SEC',
                'icon' => 'bi-basket',
            ],
            [
                'title' => 'Gouvernance et accompagnement des PME',
                'description' => 'Renforcement de la gouvernance des PME burundaises par la formation et l\'accompagnement Le projet mobilise enseignants-chercheurs et doctorants de la FSEG en partenariat avec les bailleurs.',
                'funder' => 'Coopération belge (ARES)',
                'start' => 2023,
                'end' => 2027,
                'code' => 'PME-GOV',
                'icon' => 'bi-building',
            ],
            [
                'title' => 'Réforme fiscale et développement durable',
                'description' => 'Réforme fiscale et financement du développement durable au Burundi Le projet mobilise enseignants-chercheurs et doctorants de la FSEG en partenariat avec les bailleurs.',
                'funder' => 'PNUD Burundi',
                'start' => 2024,
                'end' => 2026,
                'code' => 'TAX-SDG',
                'icon' => 'bi-percent',
            ],
            [
                'title' => 'Innovation, entrepreneuriat et emploi des jeunes',
                'description' => 'Innovation, entrepreneuriat et création d\'emplois chez les jeunes diplômés Le projet mobilise enseignants-chercheurs et doctorants de la FSEG en partenariat avec les bailleurs.',
                'funder' => 'Union Africaine',
                'start' => 2025,
                'end' => 2028,
                'code' => 'YOUTH-ENT',
                'icon' => 'bi-rocket-takeoff',
            ],
            ],
            'sci' => [
            [
                'title' => 'Campus solaire intelligent',
                'description' => 'Déploiement et suivi de micro-réseaux solaires pour alimenter laboratoires et salles de cours.',
                'funder' => 'Coopération belgo-burundaise',
                'start' => 2023,
                'end' => 2026,
                'code' => 'SOLAR-CAMP',
                'icon' => 'bi-sun',
            ],
            [
                'title' => 'Open Data administration',
                'description' => 'Appui à l’ouverture et à la visualisation de données publiques pour les services municipaux.',
                'funder' => 'Banque Mondiale',
                'start' => 2024,
                'end' => 2027,
                'code' => 'OPEN-DATA',
                'icon' => 'bi-database',
            ],
            [
                'title' => 'IoT rural',
                'description' => 'Capteurs bas coût pour l’agriculture et le suivi hydrologique en province.',
                'funder' => 'Fonds innovation UB',
                'start' => 2024,
                'end' => 2026,
                'code' => 'IOT-RURAL',
                'icon' => 'bi-broadcast-pin',
            ],
            [
                'title' => 'Cybersécurité campus',
                'description' => 'Renforcement des capacités de détection et de réponse aux incidents sur le réseau universitaire.',
                'funder' => 'Partenaire télécoms',
                'start' => 2025,
                'end' => 2027,
                'code' => 'CYBER-UB',
                'icon' => 'bi-shield-lock',
            ],
            ],
            'med' => [
            [
                'title' => 'Surveillance épidémiologique de district',
                'description' => 'Renforcement des outils de collecte et d’analyse pour trois districts sanitaires.',
                'funder' => 'OMS / Ministère de la Santé',
                'start' => 2023,
                'end' => 2026,
                'code' => 'EPI-DIST',
                'icon' => 'bi-clipboard-pulse',
            ],
            [
                'title' => 'Simulation clinique étendue',
                'description' => 'Équipement et formation des formateurs du centre de simulation.',
                'funder' => 'Coopération française',
                'start' => 2024,
                'end' => 2027,
                'code' => 'SIM-CLIN',
                'icon' => 'bi-heart-pulse',
            ],
            [
                'title' => 'Santé maternelle rurale',
                'description' => 'Évaluation d’interventions communautaires de suivi prénatal.',
                'funder' => 'UNICEF Burundi',
                'start' => 2022,
                'end' => 2025,
                'code' => 'MAT-RUR',
                'icon' => 'bi-hospital',
            ],
            [
                'title' => 'One Health zoonoses',
                'description' => 'Surveillance intégrée des zoonoses à l’interface homme-animal-environnement.',
                'funder' => 'FAO / OMS',
                'start' => 2025,
                'end' => 2028,
                'code' => 'ONE-HEALTH',
                'icon' => 'bi-virus',
            ],
            ],
            'agro' => [
            [
                'title' => 'Agroécologie paysanne',
                'description' => 'Accompagnement de coopératives vers des pratiques agroécologiques durables.',
                'funder' => 'Union Européenne',
                'start' => 2023,
                'end' => 2026,
                'code' => 'AGRO-ECO',
                'icon' => 'bi-flower1',
            ],
            [
                'title' => 'Bassins versants vivants',
                'description' => 'Restauration hydrologique et antiérosion avec des communes partenaires.',
                'funder' => 'PNUD Burundi',
                'start' => 2024,
                'end' => 2027,
                'code' => 'WATERSHED',
                'icon' => 'bi-water',
            ],
            [
                'title' => 'Café de spécialité',
                'description' => 'Amélioration de la qualité post-récolte et de la traçabilité.',
                'funder' => 'ARES / partenaires',
                'start' => 2022,
                'end' => 2025,
                'code' => 'COFFEE-Q',
                'icon' => 'bi-cup-hot',
            ],
            [
                'title' => 'Eau agricole',
                'description' => 'Diffusion de techniques d’irrigation économes pour petites exploitations.',
                'funder' => 'Coopération néerlandaise',
                'start' => 2025,
                'end' => 2028,
                'code' => 'IRRIG-EFF',
                'icon' => 'bi-droplet',
            ],
            ],
            'hum' => [
            [
                'title' => 'Archives orales des Grands Lacs',
                'description' => 'Collecte, numérisation et valorisation de témoignages oraux.',
                'funder' => 'UNESCO',
                'start' => 2023,
                'end' => 2026,
                'code' => 'ORAL-ARCH',
                'icon' => 'bi-mic',
            ],
            [
                'title' => 'Médias et citoyenneté',
                'description' => 'Formation de journalistes étudiants à l’éthique et au fact-checking.',
                'funder' => 'Fondation médias',
                'start' => 2024,
                'end' => 2026,
                'code' => 'MEDIA-CIT',
                'icon' => 'bi-newspaper',
            ],
            [
                'title' => 'Littératures en classe',
                'description' => 'Ressources didactiques pour l’enseignement des lettres au secondaire.',
                'funder' => 'Ministère de l’Éducation',
                'start' => 2022,
                'end' => 2025,
                'code' => 'LIT-CLASS',
                'icon' => 'bi-book',
            ],
            [
                'title' => 'Patrimoine photographique',
                'description' => 'Inventaire et exposition d’archives photographiques nationales.',
                'funder' => 'Coopération culturelle',
                'start' => 2025,
                'end' => 2027,
                'code' => 'PHOTO-HER',
                'icon' => 'bi-camera',
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
                'title' => 'Laboratoires de recherche',
                'description' => 'Création des laboratoires de recherche LEA, CRGD, LFC et URPP, consacrant la FSEG comme centre de recherche reconnu. Cette étape marque un jalon dans la construction de l’identité académique de la faculté.',
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
                'description' => 'Réorganisation des parcours selon le système Baccalauréat-Master : licence en quatre ans et options à partir du Bac II.',
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
                'title' => 'Spécialisations cliniques et santé publique',
                'description' => 'Ouverture des premiers diplômes d’études spécialisées et des formations en santé des populations.',
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
                'title' => 'Langues et littératures',
                'description' => 'Développement des filières de langues africaines, françaises et anglaises.',
            ],
            [
                'year' => 1996,
                'title' => 'Recherche en histoire régionale',
                'description' => 'Structuration des travaux sur l’histoire des Grands Lacs et les archives.',
            ],
            [
                'year' => 2008,
                'title' => 'LMD humanités',
                'description' => 'Réforme des parcours de licence et de master en lettres et sciences humaines.',
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

    /**
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    private function normalizeThemeMeta(array $meta, string $key): array
    {
        $shortLabels = [
            'eco' => 'FSEG',
            'sci' => 'FSI',
            'med' => 'MED',
            'agro' => 'FABI',
            'hum' => 'FLSH',
        ];
        $short = $shortLabels[$key] ?? strtoupper($key);

        if (is_string($meta['mission'] ?? null)) {
            $meta['mission'] = [
                'icon'       => 'bi-bullseye',
                'title'      => 'Mission',
                'paragraphs' => $this->paragraphsFromText((string) $meta['mission']),
            ];
        }
        if (is_string($meta['vision'] ?? null)) {
            $meta['vision'] = [
                'icon'       => 'bi-eye',
                'title'      => 'Vision',
                'paragraphs' => $this->paragraphsFromText((string) $meta['vision']),
            ];
        }

        $valueIcons = ['bi-shield-check', 'bi-star', 'bi-lightbulb'];
        $normalizedValues = [];
        foreach (($meta['values'] ?? []) as $i => $value) {
            if (! is_array($value)) {
                continue;
            }
            $normalizedValues[] = [
                'icon'        => (string) ($value['icon'] ?? $valueIcons[$i % count($valueIcons)]),
                'title'       => (string) ($value['title'] ?? ''),
                'description' => (string) ($value['description'] ?? $value['text'] ?? ''),
            ];
        }
        $meta['values'] = $normalizedValues;

        $highlights = is_array($meta['highlights'] ?? null) ? $meta['highlights'] : [];
        if (count($highlights) < 4) {
            $highlights[] = [
                'icon'        => 'bi-award',
                'title'       => 'Engagement académique',
                'description' => 'Une communauté universitaire engagée pour la réussite des étudiants.',
            ];
        }
        $meta['highlights'] = $highlights;

        $meta['mission_label'] ??= 'Nos valeurs fondamentales';
        $meta['mission_title'] ??= 'Mission & Vision';
        $meta['dean_label'] ??= 'Mot du Doyen';
        $meta['dean_role'] ??= 'Doyen de la Faculté';
        $meta['dean_title'] ??= 'Bienvenue à ' . $short;
        $meta['dean_specialty'] ??= '';
        $meta['dean_signature'] ??= $meta['dean_name'] ?? '';
        $meta['offer_label'] ??= 'Offre académique';
        $meta['offer_title'] ??= 'Des programmes adaptés à chaque ambition';
        $meta['offer_text'] ??= $meta['programmes_intro'] ?? '';
        $meta['cta_title'] ??= 'Vous souhaitez postuler ?';
        $meta['cta_text'] ??= 'Contactez notre service des admissions pour plus d’informations sur les procédures d’inscription.';
        $meta['cta_label'] ??= 'Nous contacter';
        $meta['contact_label'] ??= 'Nos coordonnées';
        $meta['contact_title'] ??= 'Prenez contact';
        $meta['form_title'] ??= 'Formulaire de contact';
        $meta['form_help'] ??= 'Tous les champs marqués comme obligatoires doivent être remplis.';
        $meta['history'] ??= [
            'label' => 'Notre parcours',
            'title' => 'Historique de ' . $short,
            'text'  => 'Une trajectoire académique au service du développement national.',
        ];
        $meta['campus'] ??= $this->campusForKey($key);
        $meta['en'] ??= $this->englishPackForKey($key, $short);

        return $meta;
    }

    /**
     * @return array<string, string>
     */
    private function campusForKey(string $key): array
    {
        $maps = [
            'eco' => [
                'label' => 'FSEG — Campus Mutanga, Université du Burundi',
                'address_line' => 'Campus Mutanga, Avenue de l’UNESCO',
                'commune' => 'Mukaza',
                'province' => 'Bujumbura Mairie',
                'country' => 'Burundi',
                'phone' => '+257 22 22 25 55',
                'email_local' => 'fseg',
                'hours' => 'Lun–Ven 07:30–16:30',
                'lat' => -3.376061,
                'lon' => 29.383330,
                'zoom' => 17,
            ],
            'sci' => [
                'label' => 'FSI — Campus Kiriri',
                'address_line' => '164, Chaussée Prince Louis Rwagasore, Campus Kiriri',
                'commune' => 'Mukaza',
                'province' => 'Bujumbura Mairie',
                'country' => 'Burundi',
                'phone' => '+257 22 22 61 10',
                'email_local' => 'fsi',
                'hours' => 'Lun–Ven 07:30–16:30',
                'lat' => -3.389031,
                'lon' => 29.375322,
                'zoom' => 17,
            ],
            'med' => [
                'label' => 'Faculté de Médecine — CHUK Kamenge',
                'address_line' => 'CHUK Kamenge, Boulevard de Mwewi Gisabo',
                'commune' => 'Ntahangwa',
                'province' => 'Bujumbura Mairie',
                'country' => 'Burundi',
                'phone' => '+257 22 23 45 67',
                'email_local' => 'med',
                'hours' => 'Lun–Ven 07:30–16:30',
                'lat' => -3.355472,
                'lon' => 29.385669,
                'zoom' => 17,
            ],
            'agro' => [
                'label' => 'FABI — Campus Zege, Gitega',
                'address_line' => 'Campus Zege (RN15), Gitega',
                'commune' => 'Gitega',
                'province' => 'Gitega',
                'country' => 'Burundi',
                'phone' => '+257 22 40 21 30',
                'email_local' => 'fabi',
                'hours' => 'Lun–Ven 07:30–16:30',
                'lat' => -3.408500,
                'lon' => 29.934000,
                'zoom' => 15,
            ],
            'hum' => [
                'label' => 'FLSH — Campus Mutanga, Université du Burundi',
                'address_line' => 'Campus Mutanga, Université du Burundi',
                'commune' => 'Mukaza',
                'province' => 'Bujumbura Mairie',
                'country' => 'Burundi',
                'phone' => '+257 22 22 27 80',
                'email_local' => 'flsh',
                'hours' => 'Lun–Ven 07:30–16:30',
                'lat' => -3.376061,
                'lon' => 29.383330,
                'zoom' => 17,
            ],
        ];

        $campus = $maps[$key] ?? $maps['eco'];
        $q = rawurlencode(sprintf('%.6f,%.6f (%s)', $campus['lat'], $campus['lon'], $campus['label']));
        $campus['map_url'] = "https://www.google.com/maps?q={$q}&hl=fr&z={$campus['zoom']}&output=embed";

        return $campus;
    }

    /**
     * @return array<string, mixed>
     */
    private function englishPackForKey(string $key, string $short): array
    {
        $packs = [
            'eco' => [
                'tagline' => 'Training economists, managers and finance professionals since 1973',
                'about_title' => 'More than fifty years serving development',
                'about' => 'The Faculty of Economics and Management of the University of Burundi trains competent, ethical and innovative professionals.',
                'hero_title' => 'Training tomorrow’s economic decision-makers',
                'dean_title' => 'Welcome to FSEG',
                'dean_role' => 'Dean of the Faculty',
                'dean_specialty' => 'Economics & Development',
                'dean_paragraphs' => [
                    'It is with great pleasure that I welcome you to the Faculty of Economics and Management of the University of Burundi.',
                    'Our mission is clear: to train competent, ethical and innovative professionals who contribute to national development.',
                    'I invite you to explore our programmes, discover our research and join our academic community.',
                ],
                'mission_label' => 'Our core values',
                'mission_title' => 'Mission & Vision',
                'mission' => [
                    'icon' => 'bi-bullseye',
                    'title' => 'Mission',
                    'paragraphs' => [
                        'Train high-level economists, managers and finance professionals able to analyse economic issues and support sustainable development.',
                    ],
                ],
                'vision' => [
                    'icon' => 'bi-eye',
                    'title' => 'Vision',
                    'paragraphs' => [
                        'Become a leading faculty in Central Africa for economics and management education and research.',
                    ],
                ],
                'offer_title' => 'Programmes tailored to every ambition',
                'offer_text' => 'Bachelor’s and master’s degrees in management, political economy and rural economics, after a common first year.',
                'cta_title' => 'Ready to apply?',
                'cta_text' => 'Contact our admissions office for information on enrolment procedures.',
                'contact_title' => 'Get in touch',
                'form_title' => 'Contact form',
                'form_help' => 'All required fields must be completed.',
                'hours' => 'Mon–Fri, 07:30–16:30',
                'footer' => 'Faculty of Economics and Management — University of Burundi.',
                'seo_title' => 'FSEG | University of Burundi',
                'seo_description' => 'Official website of the Faculty of Economics and Management.',
            ],
            'sci' => [
                'tagline' => 'Science, technology and engineering for Burundi',
                'about_title' => 'Training responsible scientists and engineers',
                'about' => 'The Faculty of Engineering Sciences prepares graduates to solve complex problems for national development.',
                'hero_title' => 'Science and engineering for the country',
                'dean_title' => 'Welcome to FSI',
                'dean_role' => 'Dean of the Faculty',
                'dean_specialty' => 'Computer Science & Systems',
                'dean_paragraphs' => [
                    'Welcome to the Faculty of Engineering Sciences.',
                    'Here scientific curiosity meets experimental rigor to deliver useful answers for Burundi.',
                ],
                'mission_label' => 'Our core values',
                'mission_title' => 'Mission & Vision',
                'mission' => ['icon' => 'bi-bullseye', 'title' => 'Mission', 'paragraphs' => ['Transmit scientific culture and train professionals able to design adapted technological solutions.']],
                'vision' => ['icon' => 'bi-eye', 'title' => 'Vision', 'paragraphs' => ['Be a recognised science and engineering faculty in the Great Lakes region.']],
                'offer_title' => 'Scientific and technological pathways',
                'offer_text' => 'Bachelor’s and master’s programmes in electromechanics, civil engineering, planning and ICT.',
                'cta_title' => 'Ready to apply?',
                'cta_text' => 'Contact admissions for enrolment information.',
                'contact_title' => 'Get in touch',
                'form_title' => 'Contact form',
                'form_help' => 'All required fields must be completed.',
                'hours' => 'Mon–Fri, 07:30–16:30',
                'footer' => 'Faculty of Engineering Sciences — University of Burundi.',
                'seo_title' => 'FSI | University of Burundi',
                'seo_description' => 'Official website of the Faculty of Engineering Sciences.',
            ],
            'med' => [
                'tagline' => 'Training competent and humane health professionals',
                'about_title' => 'Clinical excellence and community service',
                'about' => 'The Faculty of Medicine trains physicians and health professionals with competence and humanity.',
                'hero_title' => 'Care with competence and humanity',
                'dean_title' => 'Welcome to the Faculty of Medicine',
                'dean_role' => 'Dean of the Faculty',
                'dean_specialty' => 'Public health & epidemiology',
                'dean_paragraphs' => [
                    'Our mission is to train dedicated caregivers able to meet Burundi’s health challenges with competence and compassion.',
                ],
                'mission_label' => 'Our core values',
                'mission_title' => 'Mission & Vision',
                'mission' => ['icon' => 'bi-bullseye', 'title' => 'Mission', 'paragraphs' => ['Train dedicated caregivers from frontline care to biomedical research.']],
                'vision' => ['icon' => 'bi-eye', 'title' => 'Vision', 'paragraphs' => ['Contribute to a resilient health system through excellent clinical training.']],
                'offer_title' => 'Medical and paramedical pathways',
                'offer_text' => 'General medicine, clinical specialisations and public health rooted in hospital practice.',
                'cta_title' => 'Ready to apply?',
                'cta_text' => 'Contact the faculty secretariat for admissions information.',
                'contact_title' => 'Get in touch',
                'form_title' => 'Contact form',
                'form_help' => 'All required fields must be completed.',
                'hours' => 'Mon–Fri, 07:30–16:30',
                'footer' => 'Faculty of Medicine — University of Burundi.',
                'seo_title' => 'MED | University of Burundi',
                'seo_description' => 'Official website of the Faculty of Medicine.',
            ],
            'agro' => [
                'tagline' => 'Agriculture, environment and rural development',
                'about_title' => 'Agronomic sciences for food security',
                'about' => 'FABI trains engineers able to modernise agricultural systems and protect natural resources.',
                'hero_title' => 'Acting for food security',
                'dean_title' => 'Welcome to FABI',
                'dean_role' => 'Dean of the Faculty',
                'dean_specialty' => 'Agroecology & crop production',
                'dean_paragraphs' => [
                    'Together, let us cultivate sustainable solutions for food security.',
                ],
                'mission_label' => 'Our core values',
                'mission_title' => 'Mission & Vision',
                'mission' => ['icon' => 'bi-bullseye', 'title' => 'Mission', 'paragraphs' => ['Train professionals able to design sustainable agricultural systems.']],
                'vision' => ['icon' => 'bi-eye', 'title' => 'Vision', 'paragraphs' => ['Be a regional reference in agroecology and rural innovation.']],
                'offer_title' => 'Agronomy, environment and bioengineering',
                'offer_text' => 'Field-oriented programmes combining life sciences and sustainable resource management.',
                'cta_title' => 'Ready to apply?',
                'cta_text' => 'Contact the secretariat for admissions and field placements.',
                'contact_title' => 'Get in touch',
                'form_title' => 'Contact form',
                'form_help' => 'All required fields must be completed.',
                'hours' => 'Mon–Fri, 07:30–16:30',
                'footer' => 'Faculty of Agronomy and Bioengineering — University of Burundi.',
                'seo_title' => 'FABI | University of Burundi',
                'seo_description' => 'Official website of the Faculty of Agronomy and Bioengineering.',
            ],
            'hum' => [
                'tagline' => 'Letters, languages and humanities',
                'about_title' => 'Understanding societies, transmitting cultures',
                'about' => 'FLSH trains critical minds able to analyse societies and transmit cultural heritage.',
                'hero_title' => 'Humanities to illuminate the present',
                'dean_title' => 'Welcome to FLSH',
                'dean_role' => 'Dean of the Faculty',
                'dean_specialty' => 'Francophone literatures',
                'dean_paragraphs' => [
                    'The humanities illuminate the present and prepare responsible citizens.',
                ],
                'mission_label' => 'Our core values',
                'mission_title' => 'Mission & Vision',
                'mission' => ['icon' => 'bi-bullseye', 'title' => 'Mission', 'paragraphs' => ['Train educated citizens, teachers, communicators and researchers.']],
                'vision' => ['icon' => 'bi-eye', 'title' => 'Vision', 'paragraphs' => ['Make FLSH a regional centre of excellence in the humanities.']],
                'offer_title' => 'Letters, languages and humanities',
                'offer_text' => 'Pathways open to teaching, culture, media and research.',
                'cta_title' => 'Ready to apply?',
                'cta_text' => 'Contact the secretariat for admissions information.',
                'contact_title' => 'Get in touch',
                'form_title' => 'Contact form',
                'form_help' => 'All required fields must be completed.',
                'hours' => 'Mon–Fri, 07:30–16:30',
                'footer' => 'Faculty of Arts and Humanities — University of Burundi.',
                'seo_title' => 'FLSH | University of Burundi',
                'seo_description' => 'Official website of the Faculty of Arts and Humanities.',
            ],
        ];

        return $packs[$key] ?? $packs['eco'];
    }

    /**
     * @return list<string>
     */
    private function paragraphsFromText(string $value): array
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/\n{2,}/', $value) ?: [];
        if (count($parts) === 1 && str_contains($value, '. ')) {
            $sentences = preg_split('/(?<=\.)\s+/', $value) ?: [$value];
            if (count($sentences) > 2) {
                $mid = (int) ceil(count($sentences) / 2);
                $parts = [
                    implode(' ', array_slice($sentences, 0, $mid)),
                    implode(' ', array_slice($sentences, $mid)),
                ];
            }
        }

        return array_values(array_filter(
            array_map(
                static fn (string $paragraph): string => preg_replace('/[ \t]*\n[ \t]*/', ' ', trim($paragraph)) ?? trim($paragraph),
                $parts,
            ),
            static fn (string $paragraph): bool => $paragraph !== '',
        ));
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function seedEnglishTranslations(int $siteId, array $theme): void
    {
        if (! $this->db->tableExists('content_translations')) {
            return;
        }

        $en = is_array($theme['en'] ?? null) ? $theme['en'] : [];
        if ($en === []) {
            return;
        }

        $home = $this->db->table('home_content')->where('site_id', $siteId)->get()->getRowArray();
        if (is_array($home)) {
            $homeId = (int) $home['id'];
            $map = [
                'hero_title'       => $en['hero_title'] ?? null,
                'hero_text'        => $en['tagline'] ?? null,
                'about_title'      => $en['about_title'] ?? null,
                'about_body'       => $en['about'] ?? null,
                'research_title'   => $en['research_title'] ?? null,
                'research_body'    => $en['research_body'] ?? null,
                'programmes_title' => $en['offer_title'] ?? null,
                'programmes_text'  => $en['offer_text'] ?? null,
                'seo_title'        => $en['seo_title'] ?? null,
                'seo_description'  => $en['seo_description'] ?? null,
            ];
            foreach ($map as $field => $value) {
                if (is_string($value) && $value !== '') {
                    $this->insertTranslation($siteId, 'home_content', $homeId, $field, $value);
                }
            }
        }

        $pages = $this->db->table('pages')->where('site_id', $siteId)->get()->getResultArray();
        foreach ($pages as $page) {
            $key = (string) $page['key'];
            $content = json_decode((string) $page['content'], true);
            if (! is_array($content)) {
                $content = [];
            }
            $translated = $content;

            if ($key === 'faculty') {
                $translated['banner_subtitle'] = $en['faculty_banner'] ?? ($en['seo_title'] ?? ($translated['banner_subtitle'] ?? ''));
                $translated['mission_label'] = $en['mission_label'] ?? ($translated['mission_label'] ?? '');
                $translated['mission_title'] = $en['mission_title'] ?? ($translated['mission_title'] ?? '');
                if (isset($en['mission'])) {
                    $translated['mission'] = $en['mission'];
                }
                if (isset($en['vision'])) {
                    $translated['vision'] = $en['vision'];
                }
                if (isset($translated['dean']) && is_array($translated['dean'])) {
                    $translated['dean']['title'] = $en['dean_title'] ?? $translated['dean']['title'];
                    $translated['dean']['role'] = $en['dean_role'] ?? $translated['dean']['role'];
                    $translated['dean']['specialty'] = $en['dean_specialty'] ?? $translated['dean']['specialty'];
                    $translated['dean']['label'] = 'Dean’s message';
                    if (isset($en['dean_paragraphs']) && is_array($en['dean_paragraphs'])) {
                        $translated['dean']['paragraphs'] = $en['dean_paragraphs'];
                    }
                }
                if (isset($translated['history']) && is_array($translated['history'])) {
                    $translated['history']['label'] = 'Our journey';
                    $translated['history']['title'] = $en['history_title'] ?? ($translated['history']['title'] ?? '');
                    $translated['history']['text'] = $en['history_text'] ?? ($translated['history']['text'] ?? '');
                }
            }

            if ($key === 'formations') {
                $translated['offer_title'] = $en['offer_title'] ?? ($translated['offer_title'] ?? '');
                $translated['offer_text'] = $en['offer_text'] ?? ($translated['offer_text'] ?? '');
                $translated['offer_label'] = 'Academic offer';
                $translated['cta_title'] = $en['cta_title'] ?? ($translated['cta_title'] ?? '');
                $translated['cta_text'] = $en['cta_text'] ?? ($translated['cta_text'] ?? '');
                $translated['cta_label'] = 'Contact us';
            }

            if ($key === 'contact') {
                $translated['contact_label'] = 'Our details';
                $translated['contact_title'] = $en['contact_title'] ?? ($translated['contact_title'] ?? '');
                $translated['form_title'] = $en['form_title'] ?? ($translated['form_title'] ?? '');
                $translated['form_help'] = $en['form_help'] ?? ($translated['form_help'] ?? '');
                $translated['banner_subtitle'] = $en['contact_banner'] ?? ($translated['banner_subtitle'] ?? '');
            }

            $json = json_encode($translated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                $this->insertTranslation($siteId, 'pages', (int) $page['id'], 'content', $json);
            }
        }

        $programmes = $this->db->table('programmes')->where('site_id', $siteId)->get()->getResultArray();
        foreach ($programmes as $programme) {
            $this->insertTranslation($siteId, 'programmes', (int) $programme['id'], 'title', (string) $programme['title']);
            $this->insertTranslation($siteId, 'programmes', (int) $programme['id'], 'summary', (string) $programme['summary']);
        }

        $posts = $this->db->table('posts')->where('site_id', $siteId)->orderBy('id', 'ASC')->limit(6)->get()->getResultArray();
        foreach ($posts as $post) {
            $this->insertTranslation($siteId, 'posts', (int) $post['id'], 'title', (string) $post['title']);
            $this->insertTranslation($siteId, 'posts', (int) $post['id'], 'excerpt', (string) $post['excerpt']);
        }

        $settings = $this->db->table('settings')->where('site_id', $siteId)->whereIn('key', [
            'footer.text', 'seo.default_title', 'seo.default_description', 'contact.hours',
        ])->get()->getResultArray();
        foreach ($settings as $setting) {
            $key = (string) $setting['key'];
            $value = match ($key) {
                'footer.text' => $en['footer'] ?? null,
                'seo.default_title' => $en['seo_title'] ?? null,
                'seo.default_description' => $en['seo_description'] ?? null,
                'contact.hours' => $en['hours'] ?? null,
                default => null,
            };
            if (is_string($value) && $value !== '') {
                $this->insertTranslation($siteId, 'settings', (int) $setting['id'], 'value', $value);
            }
        }
    }

    private function informationSeeder(): FacultyDemoInformationSeeder
    {
        return new FacultyDemoInformationSeeder($this->db, service('mediaService'));
    }

    private function insertTranslation(int $siteId, string $resourceType, int $resourceId, string $field, string $value): void
    {
        $this->db->table('content_translations')->insert([
            'site_id'       => $siteId,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'locale'        => 'en',
            'field'         => $field,
            'value'         => $value,
            'created_at'    => $this->now(),
            'updated_at'    => $this->now(),
        ]);
    }
}
