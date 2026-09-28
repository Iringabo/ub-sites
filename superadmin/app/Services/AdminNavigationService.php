<?php

namespace App\Services;

use App\Controllers\Admin\SettingsController;
use App\Entities\Site;
use App\Models\ContactMessageModel;
use CodeIgniter\Shield\Entities\User;
use Throwable;

class AdminNavigationService
{
    /**
     * @return array{title: string, subtitle: string}
     */
    public function brand(): array
    {
        if ($this->isCentral()) {
            return [
                'title'    => 'Superadministration',
                'subtitle' => 'Pilotage des facultés',
            ];
        }

        $site = service('siteResolver')->activeSite();
        $short = strtoupper(trim((string) ($site->identifier ?? ''))) ?: 'UB';

        return [
            'title'    => 'Administration ' . $short,
            'subtitle' => 'Université du Burundi',
        ];
    }

    /**
     * Sidebar structure, ordered by how often editors need each area:
     * daily tasks, then one dropdown per public page (navbar order), then
     * site-wide settings, then administration. Links the user cannot open are removed.
     *
     * A section holds items of type "link" or "page". A page item holds
     * "sections" (text categories) and "lists" (add/remove modules).
     *
     * @return list<array<string, mixed>>
     */
    public function sections(?User $user = null): array
    {
        $user ??= service('adminAccess')->currentUser();
        $central = $this->isCentral();
        $sections = [];

        if ($central) {
            $sections[] = [
                'id'    => 'platform',
                'label' => 'Plateforme',
                'items' => [
                    $this->link('', 'Tableau de bord plateforme', 'bi-speedometer2', 'admin.access', ['accueil', 'statistiques']),
                    $this->link('sites', 'Facultés', 'bi-bank', 'sites.manage', ['sites', 'instances']),
                    $this->link('users', 'Comptes & rôles', 'bi-people', 'users.manage', ['utilisateurs', 'administrateurs', 'éditeurs', 'mot de passe']),
                ],
            ];
        }

        if ($central && ! $this->facultyContentSelected($user)) {
            $sections[] = [
                'id'    => 'pages',
                'label' => 'Pages du site',
                'hint'  => 'Choisissez une faculté en haut de l’écran pour afficher ses pages.',
                'items' => [],
            ];

            return $this->visibleSections($sections, $user);
        }

        $daily = [];
        if (! $central) {
            $daily[] = $this->link('', 'Tableau de bord', 'bi-speedometer2', 'admin.access', ['accueil admin', 'résumé']);
        } else {
            $daily[] = $this->link('site', 'Aperçu de la faculté', 'bi-building', 'admin.access', ['tableau de bord faculté']);
        }
        $daily[] = $this->link('messages', 'Messages reçus', 'bi-envelope-paper', 'messages.manage', ['contact', 'boîte de réception', 'non lus'])
            + ['badge' => $this->unreadMessages($user)];

        $sections[] = ['id' => 'daily', 'label' => 'Au quotidien', 'items' => $daily];
        $sections[] = ['id' => 'pages', 'label' => 'Pages du site', 'items' => $this->pageItems()];

        $settingsLinks = [];
        foreach (SettingsController::PAGES as $slug => $page) {
            $settingsLinks[] = $this->link(
                'settings/' . $slug,
                $page['label'],
                $page['icon'],
                'settings.manage',
                array_merge($page['keywords'], SettingsController::fieldLabels($slug)),
            );
        }
        $sections[] = ['id' => 'settings', 'label' => 'Paramètres du site', 'items' => $settingsLinks];

        if (! $central) {
            $sections[] = [
                'id'    => 'admin',
                'label' => 'Administration',
                'items' => [
                    $this->link('users', 'Comptes & rôles', 'bi-people', 'users.manage', ['utilisateurs', 'administrateurs', 'éditeurs', 'mot de passe']),
                ],
            ];
        }

        return $this->visibleSections($sections, $user);
    }

    /**
     * Admin keys covered by a page dropdown, used to open the right one.
     *
     * @param array<string, mixed> $page
     *
     * @return list<string>
     */
    public function pageItemKeys(array $page): array
    {
        return array_map(
            static fn (array $link): string => (string) $link['key'],
            array_merge($page['sections'] ?? [], $page['lists'] ?? []),
        );
    }

    /**
     * Resources retired from the admin UI (routes stay guarded).
     *
     * @return list<string>
     */
    public function retiredResourceKeys(): array
    {
        return ['pages', 'content-blocks', 'home-highlights', 'home-content'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pageItems(): array
    {
        $catalog = service('pageTextCatalog');
        $items = [];

        foreach ($catalog->pages() as $pageSlug => $page) {
            $sectionLinks = [];
            foreach ($page['segments'] as $segmentId => $segment) {
                $fieldLabels = array_map(static fn (array $field): string => (string) $field['label'], $segment['fields'] ?? []);
                $link = $this->link(
                    $catalog->activeKey($pageSlug, $segmentId),
                    (string) $segment['label'],
                    (string) ($segment['icon'] ?? 'bi-fonts'),
                    isset($segment['link']) ? 'home.manage' : (string) $page['permission'],
                    array_merge($segment['keywords'] ?? [], array_values($fieldLabels)),
                );
                $link['url'] = $catalog->segmentUrl($pageSlug, $segmentId);
                $sectionLinks[] = $link;
            }

            $listLinks = [];
            foreach ($page['lists'] as $list) {
                $listLinks[] = $this->link($list['key'], $list['label'], $list['icon'], $list['permissions'], $list['keywords'] ?? []);
            }

            $items[] = [
                'type'       => 'page',
                'id'         => $pageSlug,
                'label'      => (string) $page['label'],
                'icon'       => (string) $page['icon'],
                'public_url' => $this->publicPageUrl((string) $page['public_path']),
                'sections'   => $sectionLinks,
                'lists'      => $listLinks,
            ];
        }

        return $items;
    }

    /**
     * @param string|list<string> $permissions
     * @param list<string>        $keywords
     *
     * @return array<string, mixed>
     */
    private function link(string $key, string $label, string $icon, string|array $permissions, array $keywords = []): array
    {
        return [
            'type'        => 'link',
            'key'         => $key,
            'url'         => 'admin' . ($key === '' ? '' : '/' . $key),
            'label'       => $label,
            'icon'        => $icon,
            'permissions' => $permissions,
            'keywords'    => $keywords,
        ];
    }

    /**
     * Public URL of a page for the site being edited; null on the central host without a faculty.
     */
    private function publicPageUrl(string $path): ?string
    {
        $base = $this->previewUrl();
        if ($base === null) {
            return null;
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    private function facultyContentSelected(?User $user): bool
    {
        return service('siteResolver')->hasExplicitAdminSiteSelection() || count($this->availableSites($user)) <= 1;
    }

    /**
     * Contact messages still marked "new" for the active site (0 without messages.manage).
     */
    public function unreadMessages(?User $user): int
    {
        if (! $this->userCanLink($user, 'messages.manage')) {
            return 0;
        }

        try {
            return model(ContactMessageModel::class, false)->forSite()->where('status', 'new')->countAllResults();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return list<Site>
     */
    public function availableSites(?User $user = null): array
    {
        $user ??= service('adminAccess')->currentUser();
        if ($user === null || ! service('adminAccess')->isSuperAdmin($user)) {
            return [];
        }

        return service('siteResolver')->availableSitesForUser($user);
    }

    public function editingLabel(): string
    {
        if ($this->isCentral()) {
            if (! service('siteResolver')->hasExplicitAdminSiteSelection()) {
                return 'Choisissez une faculté pour éditer son contenu';
            }

            $site = service('siteResolver')->activeSite();
            $name = trim((string) ($site->name ?? ''));

            return $name !== '' ? 'Vous modifiez : ' . $name : 'Superadministration';
        }

        return (string) (service('siteResolver')->activeSite()->name ?? 'Site courant');
    }

    public function previewUrl(): ?string
    {
        if (! $this->isCentral()) {
            return site_url('/');
        }

        if (! service('siteResolver')->hasExplicitAdminSiteSelection()) {
            return null;
        }

        return $this->publicUrlForSite(service('siteResolver')->activeSite());
    }

    /**
     * @param \App\Entities\Site|array<string, mixed>|null $site
     */
    public function publicUrlForSite(mixed $site): ?string
    {
        if ($site === null) {
            return null;
        }

        $slug = strtolower(trim(is_array($site) ? (string) ($site['slug'] ?? '') : (string) ($site->slug ?? '')));
        $hosts = is_array($site) ? ($site['hostnames'] ?? []) : ($site->hostnames ?? []);
        if (is_string($hosts)) {
            $decoded = json_decode($hosts, true);
            $hosts = is_array($decoded) ? $decoded : [];
        }

        $host = '';
        if (is_array($hosts) && $hosts !== []) {
            $host = trim((string) ($hosts[0] ?? ''));
        }

        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $override = trim((string) env('app.previewBase.' . $slug, ''));
        if ($override !== '') {
            return rtrim($override, '/') . '/';
        }

        // Local multi-folder ports when hostnames are empty.
        $localPorts = [
            'fseg' => 8101,
            'fsi'  => 8102,
            'med'  => 8104,
            'fabi' => 8105,
            'flsh' => 8106,
        ];
        $appBase = (string) (config('App')->baseURL ?? '');
        $isLocalApp = str_contains($appBase, 'localhost') || str_contains($appBase, '127.0.0.1');
        if ($host === '' && $isLocalApp && isset($localPorts[$slug])) {
            return 'http://localhost:' . $localPorts[$slug] . '/';
        }

        $hostName = strtolower((string) explode(':', $host)[0]);
        $hostIsDev = in_array($hostName, ['localhost', '127.0.0.1'], true) || str_ends_with($hostName, '.test');

        if ($host === '') {
            return null;
        }

        if ($isLocalApp && $hostIsDev) {
            return 'http://' . $host . '/';
        }

        return 'https://' . $host;
    }

    public function dashboardUrl(): string
    {
        return site_url('admin');
    }

    public function isCentral(): bool
    {
        return service('adminAccess')->isCentralAdminHost();
    }

    public function userCanLink(?User $user, string|array $permissions): bool
    {
        foreach ((array) $permissions as $permission) {
            if (($user?->can($permission) ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $sections
     *
     * @return list<array<string, mixed>>
     */
    private function visibleSections(array $sections, ?User $user): array
    {
        $canSee = fn (array $link): bool => $this->userCanLink($user, $link['permissions']);
        $visible = [];

        foreach ($sections as $section) {
            $items = [];
            foreach ($section['items'] as $item) {
                if ($item['type'] === 'link') {
                    if ($canSee($item)) {
                        $items[] = $item;
                    }

                    continue;
                }

                $item['sections'] = array_values(array_filter($item['sections'], $canSee));
                $item['lists'] = array_values(array_filter($item['lists'], $canSee));
                if ($item['sections'] !== [] || $item['lists'] !== []) {
                    $items[] = $item;
                }
            }

            if ($items === [] && empty($section['hint'])) {
                continue;
            }

            $section['items'] = $items;
            $visible[] = $section;
        }

        return $visible;
    }
}
