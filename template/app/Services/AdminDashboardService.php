<?php

namespace App\Services;

use App\Models\ContactMessageModel;
use App\Models\HomeContentModel;
use App\Models\HomeHighlightModel;
use App\Models\PageModel;
use App\Models\PostModel;
use App\Models\ProgrammeModel;
use App\Models\SettingModel;
use App\Models\SiteStatModel;
use App\Models\StaffModel;
use CodeIgniter\I18n\Time;
use Throwable;

class AdminDashboardService
{
    /**
     * Liste de mise en route d'un site fraîchement provisionné : détecte les
     * contenus de départ (« à remplacer / exemple ») restants et propose les
     * écrans à compléter, pour une prise en main intuitive.
     *
     * @return array{items: list<array{label: string, url: string, done: bool}>, done: int, total: int}
     */
    public function onboarding(): array
    {
        $items   = [];
        $home    = model(HomeContentModel::class, false)->forSite()->first();
        $homeRaw = $home === null ? '' : strtolower(($home->hero_title ?? '') . ' ' . ($home->about_body ?? '') . ' ' . ($home->about_title ?? ''));
        $items[] = [
            'label' => 'Personnaliser les textes de l’accueil',
            'url'   => site_url('admin/home-content'),
            'done'  => $home !== null && ! str_contains($homeRaw, 'bienvenue sur le site de') && ! str_contains($homeRaw, 'à remplacer'),
        ];

        $slidesLeft = model(HomeHeroSlideModel::class, false)->forSite()->like('alt_text', 'remplacer')->countAllResults();
        $items[] = [
            'label' => 'Remplacer les images du carrousel',
            'url'   => site_url('admin/home-hero-slides'),
            'done'  => $slidesLeft === 0,
        ];

        $settings = service('settingsService')->all();
        $contactDone = isset($settings['contact.email'])
            && $settings['contact.email'] !== 'contact@example.test'
            && ! str_contains(strtolower((string) ($settings['contact.address'] ?? '')), 'compléter');
        $items[] = [
            'label' => 'Renseigner les coordonnées de la faculté',
            'url'   => site_url('admin/settings'),
            'done'  => $contactDone,
        ];

        $programmesLeft = model(ProgrammeModel::class, false)->forSite()->like('title', 'exemple à modifier')->countAllResults();
        $items[] = [
            'label' => 'Créer vos programmes de formation',
            'url'   => site_url('admin/programmes'),
            'done'  => $programmesLeft === 0,
        ];

        $staffLeft = model(StaffModel::class, false)->forSite()->like('name', 'exemple')->countAllResults();
        $items[] = [
            'label' => 'Présenter votre personnel',
            'url'   => site_url('admin/staff'),
            'done'  => $staffLeft === 0,
        ];

        $realPosts = model(PostModel::class, false)->forSite()->where('status', 'published')->notLike('title', 'exemple')->countAllResults();
        $items[] = [
            'label' => 'Publier une première actualité',
            'url'   => site_url('admin/posts'),
            'done'  => $realPosts > 0,
        ];

        $done  = count(array_filter($items, static fn (array $item): bool => $item['done']));
        $total = count($items);

        return ['items' => $items, 'done' => $done, 'total' => $total];
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'newsStatusCounts'      => $this->newsStatusCounts(),
            'upcomingEvents'        => $this->upcomingEvents(),
            'publishedProgrammes'   => $this->countPublished(ProgrammeModel::class),
            'publishedStaff'        => $this->countPublished(StaffModel::class),
            'newMessages'           => $this->countNewMessages(),
            'messageStatusCounts'   => $this->messageStatusCounts(),
            'recentChanges'         => $this->recentChanges(),
            'pendingMessagesLabel'  => $this->messagesLabel(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function centralData(): array
    {
        $db = db_connect();
        $sites = [];
        $siteRoles = [];

        try {
            if ($db->tableExists('sites')) {
                $sites = $db->table('sites')
                    ->orderBy('name', 'ASC')
                    ->get()
                    ->getResultArray();
            }

            if ($db->tableExists('user_sites')) {
                $roleRows = $db->table('user_sites')
                    ->select('site_id, role, COUNT(*) AS total')
                    ->groupBy(['site_id', 'role'])
                    ->get()
                    ->getResultArray();

                foreach ($roleRows as $row) {
                    $siteRoles[(int) $row['site_id']][(string) $row['role']] = (int) $row['total'];
                }
            }
        } catch (Throwable) {
            $sites = [];
            $siteRoles = [];
        }

        return [
            'sites'             => $sites,
            'siteRoles'         => $siteRoles,
            'totalSites'        => count($sites),
            'activeSites'       => count(array_filter($sites, static fn (array $site): bool => ($site['status'] ?? '') === 'active')),
            'totalUsers'        => $this->tableCount('users'),
            'newMessages'       => $this->tableCount('contact_messages', ['status' => 'new']),
            'siteContentCounts' => $this->siteContentCounts(),
        ];
    }

    /**
     * Compteurs de contenus publiés par site, pour le tableau de bord
     * superadministration.
     *
     * @return array<int, array<string, int>>
     */
    private function siteContentCounts(): array
    {
        $counts = [];

        try {
            $db = db_connect();

            foreach (['posts', 'programmes', 'staff'] as $table) {
                if (! $db->tableExists($table)) {
                    continue;
                }

                $builder = $db->table($table)->select('site_id, COUNT(*) AS total');

                if ($table === 'posts') {
                    $builder->where('status', 'published');
                } else {
                    $builder->where('is_published', 1);
                }

                $rows = $builder->groupBy('site_id')->get()->getResultArray();

                foreach ($rows as $row) {
                    $siteId = (int) $row['site_id'];
                    $counts[$siteId] ??= ['posts' => 0, 'programmes' => 0, 'staff' => 0];
                    $counts[$siteId][$table] = (int) $row['total'];
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function newsStatusCounts(): array
    {
        $counts = array_fill_keys(array_keys(service('postVisibilityService')->statuses()), 0);

        $rows = model(PostModel::class, false)
            ->forSite()
            ->select('status, COUNT(*) AS total')
            ->where('type', 'news')
            ->groupBy('status')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcomingEvents(): array
    {
        $now = Time::now()->toDateTimeString();

        $events = model(PostModel::class, false)->visible()
            ->where('type', 'event')
            ->where('event_starts_at >=', $now)
            ->orderBy('event_starts_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll(5);

        return array_map(
            static function ($event): array {
                return [
                    'id'            => $event->id,
                    'title'         => $event->title,
                    'slug'          => $event->slug,
                    'starts_at'     => $event->event_starts_at,
                    'location'      => $event->event_location,
                    'status_label'  => site_post_status_label($event->status),
                    'url'           => site_url('actualites/' . $event->slug),
                ];
            },
            $events,
        );
    }

    private function countPublished(string $modelClass): int
    {
        $model = model($modelClass, false);
        if (method_exists($model, 'forSite')) {
            $model->forSite();
        }

        return (int) $model->where('is_published', 1)->countAllResults();
    }

    private function countNewMessages(): int
    {
        return (int) model(ContactMessageModel::class, false)->forSite()->where('status', 'new')->countAllResults();
    }

    /**
     * @return array<string, int>
     */
    private function messageStatusCounts(): array
    {
        $counts = array_fill_keys(['new', 'read', 'handled', 'archived'], 0);

        $rows = model(ContactMessageModel::class, false)
            ->forSite()
            ->select('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['total'];
            }
        }

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentChanges(): array
    {
        $items = [];

        foreach ($this->latestItems() as $item) {
            $items[] = $item;
        }

        usort(
            $items,
            static fn (array $left, array $right): int => strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? '')),
        );

        return array_slice($items, 0, 6);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function latestItems(): array
    {
        $items = [];

        $home = model(HomeContentModel::class, false)->forSite()->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->first();
        if ($home !== null) {
            $items[] = [
                'label'      => 'Accueil',
                'title'      => $home->hero_title ?: 'Contenu d’accueil',
                'detail'     => $home->about_title ?: 'Héros, présentation et sections éditoriales',
                'updated_at' => $home->updated_at ?? $home->created_at ?? null,
                'url'        => site_url('admin/home-content/' . $home->id . '/edit'),
                'icon'       => 'bi-house',
            ];
        }

        $highlight = model(HomeHighlightModel::class, false)->forSite()->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->first();
        if ($highlight !== null) {
            $items[] = [
                'label'      => 'Atouts',
                'title'      => $highlight->title,
                'detail'     => $highlight->description ?: 'Atout mis en avant',
                'updated_at' => $highlight->updated_at ?? $highlight->created_at ?? null,
                'url'        => site_url('admin/home-highlights/' . $highlight->id . '/edit'),
                'icon'       => 'bi-star',
            ];
        }

        $programme = model(ProgrammeModel::class, false)->forSite()->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->first();
        if ($programme !== null) {
            $items[] = [
                'label'      => 'Formation',
                'title'      => $programme->title,
                'detail'     => site_level_label($programme->level),
                'updated_at' => $programme->updated_at ?? $programme->created_at ?? null,
                'url'        => site_url('admin/programmes/' . $programme->id . '/edit'),
                'icon'       => 'bi-mortarboard',
            ];
        }

        $staff = model(StaffModel::class, false)->forSite()->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->first();
        if ($staff !== null) {
            $items[] = [
                'label'      => 'Personnel',
                'title'      => $staff->name,
                'detail'     => $staff->role ?: site_staff_category_label($staff->category),
                'updated_at' => $staff->updated_at ?? $staff->created_at ?? null,
                'url'        => site_url('admin/staff/' . $staff->id . '/edit'),
                'icon'       => 'bi-people',
            ];
        }

        $post = model(PostModel::class, false)->forSite()->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->first();
        if ($post !== null) {
            $items[] = [
                'label'      => site_post_type_label($post->type),
                'title'      => $post->title,
                'detail'     => site_post_status_label($post->status),
                'updated_at' => $post->updated_at ?? $post->created_at ?? null,
                'url'        => site_url('admin/posts/' . $post->id . '/edit'),
                'icon'       => 'bi-newspaper',
            ];
        }

        $page = model(PageModel::class, false)->forSite()->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->first();
        if ($page !== null) {
            $items[] = [
                'label'      => 'Page',
                'title'      => $page->title,
                'detail'     => $page->key,
                'updated_at' => $page->updated_at ?? $page->created_at ?? null,
                'url'        => site_url('admin/pages/' . $page->id . '/edit'),
                'icon'       => 'bi-file-earmark-text',
            ];
        }

        $stat = model(SiteStatModel::class, false)->forSite()->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->first();
        if ($stat !== null) {
            $items[] = [
                'label'      => 'Statistique',
                'title'      => $stat->label,
                'detail'     => $stat->section,
                'updated_at' => $stat->updated_at ?? $stat->created_at ?? null,
                'url'        => site_url('admin/site-stats/' . $stat->id . '/edit'),
                'icon'       => 'bi-graph-up',
            ];
        }

        $setting = model(SettingModel::class, false)->forSite()->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->first();
        if ($setting !== null) {
            $items[] = [
                'label'      => 'Paramètre',
                'title'      => $setting->key,
                'detail'     => $setting->context ?: 'Configuration',
                'updated_at' => $setting->updated_at ?? $setting->created_at ?? null,
                'url'        => site_url('admin/settings/' . $setting->id . '/edit'),
                'icon'       => 'bi-gear',
            ];
        }

        return $items;
    }

    private function messagesLabel(): string
    {
        return 'Messages de contact';
    }

    /**
     * @param array<string, mixed> $where
     */
    private function tableCount(string $table, array $where = []): int
    {
        $db = db_connect();

        try {
            if (! $db->tableExists($table)) {
                return 0;
            }

            $builder = $db->table($table);
            foreach ($where as $field => $value) {
                $builder->where($field, $value);
            }

            return (int) $builder->countAllResults();
        } catch (Throwable) {
            return 0;
        }
    }
}
