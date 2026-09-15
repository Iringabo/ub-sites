<?php

namespace App\Services;

use App\Entities\Site;
use CodeIgniter\Shield\Entities\User;

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
     * @return list<array{id: string, label: string, icon: string, links: list<array{key: string, label: string, permissions: string|list<string>, icon: string}>}>
     */
    public function groups(?User $user = null): array
    {
        $user ??= service('adminAccess')->currentUser();
        $groups = [];

        if ($this->isCentral()) {
            $groups[] = [
                'id'    => 'platform',
                'label' => 'Plateforme',
                'icon'  => 'bi-diagram-2',
                'links' => [
                    ['key' => 'site', 'label' => 'Aperçu du site', 'permissions' => 'admin.access', 'icon' => 'bi-building'],
                    ['key' => 'sites', 'label' => 'Facultés', 'permissions' => 'sites.manage', 'icon' => 'bi-bank'],
                    ['key' => 'users', 'label' => 'Comptes & accès', 'permissions' => 'users.manage', 'icon' => 'bi-people'],
                ],
            ];
        }

        $groups[] = [
            'id'    => 'home',
            'label' => 'Accueil',
            'icon'  => 'bi-house-door',
            'links' => [
                ['key' => 'home-hero-slides', 'label' => 'Héros (slides)', 'permissions' => 'home.manage', 'icon' => 'bi-images'],
                ['key' => 'home-sections', 'label' => 'Sections & ordre', 'permissions' => 'home.manage', 'icon' => 'bi-list-ol'],
                ['key' => 'home-highlights', 'label' => 'Points forts', 'permissions' => 'home.manage', 'icon' => 'bi-stars'],
                ['key' => 'site-stats', 'label' => 'Chiffres clés', 'permissions' => 'home.manage', 'icon' => 'bi-bar-chart'],
                ['key' => 'home-content', 'label' => 'Textes des sections', 'permissions' => 'home.manage', 'icon' => 'bi-layout-text-window'],
            ],
        ];

        $groups[] = [
            'id'    => 'site-pages',
            'label' => 'Pages du site',
            'icon'  => 'bi-layout-text-sidebar-reverse',
            'links' => [
                ['key' => 'posts', 'label' => 'Actualités & événements', 'permissions' => ['news.manage', 'events.manage'], 'icon' => 'bi-megaphone'],
                ['key' => 'programmes', 'label' => 'Formations', 'permissions' => 'programmes.manage', 'icon' => 'bi-journal-bookmark'],
                ['key' => 'faculty/profile', 'label' => 'Présentation & mot du doyen', 'permissions' => 'pages.manage', 'icon' => 'bi-person-vcard'],
                ['key' => 'timeline-items', 'label' => 'Historique', 'permissions' => 'pages.manage', 'icon' => 'bi-clock-history'],
                ['key' => 'staff', 'label' => 'Personnel', 'permissions' => 'staff.manage', 'icon' => 'bi-person-badge'],
                ['key' => 'alumni-profiles', 'label' => 'Alumni', 'permissions' => 'alumni.manage', 'icon' => 'bi-award'],
                ['key' => 'testimonials', 'label' => 'Témoignages', 'permissions' => 'alumni.manage', 'icon' => 'bi-chat-quote'],
                ['key' => 'laboratories', 'label' => 'Laboratoires', 'permissions' => 'research.manage', 'icon' => 'bi-diagram-3'],
                ['key' => 'publications', 'label' => 'Publications', 'permissions' => 'research.manage', 'icon' => 'bi-journal-text'],
                ['key' => 'research-projects', 'label' => 'Projets', 'permissions' => 'research.manage', 'icon' => 'bi-briefcase'],
            ],
        ];

        $groups[] = [
            'id'    => 'messages',
            'label' => 'Messages',
            'icon'  => 'bi-envelope',
            'links' => [
                ['key' => 'messages', 'label' => 'Messages de contact', 'permissions' => 'messages.manage', 'icon' => 'bi-envelope-paper'],
            ],
        ];

        $accountLinks = [];
        if (! $this->isCentral()) {
            $accountLinks[] = ['key' => 'users', 'label' => 'Comptes & accès', 'permissions' => 'users.manage', 'icon' => 'bi-people'];
        }
        if ($accountLinks !== []) {
            $groups[] = [
                'id'    => 'accounts',
                'label' => 'Comptes & accès',
                'icon'  => 'bi-people',
                'links' => $accountLinks,
            ];
        }

        $groups[] = [
            'id'    => 'identity',
            'label' => 'Identité',
            'icon'  => 'bi-gear',
            'links' => [
                ['key' => 'settings/global', 'label' => 'Coordonnées & identité', 'permissions' => 'settings.manage', 'icon' => 'bi-gear'],
            ],
        ];

        return $this->visibleGroups($groups, $user);
    }

    /**
     * Resources retired from the admin UI (routes stay guarded).
     *
     * @return list<string>
     */
    public function retiredResourceKeys(): array
    {
        return ['pages', 'content-blocks'];
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
     * @param list<array{id: string, label: string, icon: string, links: list<array{key: string, label: string, permissions: string|list<string>, icon: string}>}> $groups
     *
     * @return list<array{id: string, label: string, icon: string, links: list<array{key: string, label: string, permissions: string|list<string>, icon: string}>}>
     */
    private function visibleGroups(array $groups, ?User $user): array
    {
        $visible = [];

        foreach ($groups as $group) {
            $links = array_values(array_filter(
                $group['links'],
                fn (array $link): bool => $this->userCanLink($user, $link['permissions']),
            ));

            if ($links === []) {
                continue;
            }

            $group['links'] = $links;
            $visible[] = $group;
        }

        return $visible;
    }
}
