<?php

namespace App\Services;

use App\Entities\Site;
use App\Models\SiteModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Shield\Entities\User;
use Throwable;

class SiteResolverService
{
    private ?Site $activeSite = null;
    private ?string $activeSiteContext = null;

    public function activeSite(?RequestInterface $request = null): Site
    {
        $request ??= service('request');
        $context = $this->contextKey($request);

        if ($this->activeSite instanceof Site && $this->activeSiteContext === $context) {
            return $this->activeSite;
        }

        if ($this->isAdminRequest($request)) {
            $this->activeSite = $this->adminSite($request);
            $this->activeSiteContext = $context;

            return $this->activeSite;
        }

        $this->activeSite = $this->publicSite($request);
        $this->activeSiteContext = $context;

        return $this->activeSite;
    }

    public function activeSiteId(?RequestInterface $request = null): int
    {
        return (int) $this->activeSite($request)->id;
    }

    public function siteFromRequestHost(RequestInterface $request): ?Site
    {
        return $this->siteForRequestHost($request);
    }

    /**
     * @return list<Site>
     */
    public function availableSitesForUser(?User $user = null): array
    {
        $user ??= $this->currentUser();

        if (! $user instanceof User) {
            return [$this->publicSite(service('request'))];
        }

        if ($this->isSuperAdmin($user)) {
            $sites = $this->activeSites();

            return $sites !== [] ? $sites : [$this->defaultSite()];
        }

        $sites = $this->assignedSites((int) $user->id);
        if ($sites !== []) {
            return $sites;
        }

        return [];
    }

    public function canUserAccessSite(int $siteId, ?User $user = null): bool
    {
        $siteId = (int) $siteId;
        if ($siteId <= 0) {
            return false;
        }

        $user ??= $this->currentUser();
        if (! $user instanceof User) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        foreach ($this->assignedSites((int) $user->id) as $site) {
            if ((int) $site->id === $siteId) {
                return true;
            }
        }

        return false;
    }

    public function selectAdminSite(int $siteId, ?User $user = null): bool
    {
        if (! $this->canUserAccessSite($siteId, $user)) {
            return false;
        }

        service('session')->set('active_admin_site_id', $siteId);
        $this->reset();

        return true;
    }

    /**
     * @param list<int> $siteIds
     */
    public function syncUserSites(int $userId, array $siteIds, string $role = 'site_admin'): void
    {
        $role = $this->normalizeUserSiteRole($role);
        $rolesBySite = [];

        foreach ($siteIds as $siteId) {
            $rolesBySite[(int) $siteId] = $role;
        }

        $this->syncUserSiteRoles($userId, $rolesBySite);
    }

    /**
     * @param array<int, string> $rolesBySite
     */
    public function syncUserSiteRoles(int $userId, array $rolesBySite): void
    {
        $normalizedRoles = [];

        foreach ($rolesBySite as $siteId => $role) {
            $siteId = (int) $siteId;
            if ($siteId <= 0) {
                continue;
            }

            $normalizedRoles[$siteId] = $this->normalizeUserSiteRole((string) $role);
        }

        $siteIds = array_keys($normalizedRoles);

        $model = model(\App\Models\UserSiteModel::class, false);
        $existing = $model->where('user_id', $userId)->findAll();
        $existingBySite = [];

        foreach ($existing as $row) {
            $existingBySite[(int) $row->site_id] = (int) $row->id;
        }

        foreach ($existingBySite as $siteId => $rowId) {
            if (! in_array($siteId, $siteIds, true)) {
                $model->delete($rowId);
            }
        }

        foreach ($siteIds as $siteId) {
            if (isset($existingBySite[$siteId])) {
                $model->update($existingBySite[$siteId], [
                    'role' => $normalizedRoles[$siteId],
                ]);
                continue;
            }

            $model->insert([
                'user_id' => $userId,
                'site_id' => $siteId,
                'role'    => $normalizedRoles[$siteId],
            ]);
        }
    }

    /**
     * @return list<int>
     */
    public function siteIdsFromRequest(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $value),
            static fn (int $siteId): bool => $siteId > 0,
        )));
    }

    /**
     * @return list<int>
     */
    public function assignedSiteIds(int $userId): array
    {
        return array_map(
            static fn (Site $site): int => (int) $site->id,
            $this->assignedSites($userId),
        );
    }

    public function reset(): void
    {
        $this->activeSite = null;
        $this->activeSiteContext = null;
    }

    private function contextKey(RequestInterface $request): string
    {
        if ($this->isAdminRequest($request)) {
            $selected = service('session')->get('active_admin_site_id');
            $hostKey = implode('|', $this->hostCandidates($request)) ?: 'default';

            return 'admin:' . (string) ($selected ?? $hostKey);
        }

        return 'public:' . implode('|', $this->hostCandidates($request));
    }

    private function adminSite(RequestInterface $request): Site
    {
        $user = $this->currentUser();
        $selected = (int) (service('session')->get('active_admin_site_id') ?? 0);

        if ($selected > 0 && $this->canUserAccessSite($selected, $user)) {
            return $this->siteById($selected) ?? $this->defaultSite();
        }

        $hostSite = $this->siteForRequestHost($request);
        if ($hostSite instanceof Site && $this->canUserAccessSite((int) $hostSite->id, $user)) {
            return $hostSite;
        }

        $default = $this->defaultSite();
        if (! $user instanceof User || $this->canUserAccessSite((int) $default->id, $user)) {
            return $default;
        }

        $available = $this->availableSitesForUser($user);

        return $available[0] ?? $default;
    }

    private function publicSite(RequestInterface $request): Site
    {
        if (service('adminAccess')->isCentralAdminHost($request)) {
            return $this->defaultSite();
        }

        if ($request instanceof CLIRequest) {
            return $this->configuredOrDefaultSite($request);
        }

        if ($this->shouldPreferConfiguredSiteSlug($request)) {
            return $this->configuredOrDefaultSite($request);
        }

        $hostSite = $this->siteForRequestHost($request);
        if ($hostSite instanceof Site) {
            return $hostSite;
        }

        if ($this->requiresKnownPublicHostname() && $this->hasExplicitHttpHost($request)) {
            throw PageNotFoundException::forPageNotFound('Aucun site actif ne correspond à cet hôte.');
        }

        return $this->configuredOrDefaultSite($request);
    }

    private function shouldPreferConfiguredSiteSlug(RequestInterface $request): bool
    {
        if ($this->requiresKnownPublicHostname()) {
            return false;
        }

        if (trim((string) env('app.siteSlug', '')) === '') {
            return false;
        }

        foreach ($this->hostCandidates($request) as $host) {
            if (in_array($host, ['localhost', '127.0.0.1'], true)) {
                return true;
            }
        }

        return false;
    }

    private function configuredOrDefaultSite(?RequestInterface $request = null): Site
    {
        $configuredId = (int) env('app.siteId', 0);
        if ($configuredId > 0) {
            $site = $this->siteById($configuredId);
            if ($site instanceof Site) {
                return $site;
            }
        }

        $configuredSlug = strtolower(trim((string) env('app.defaultSiteSlug', env('app.siteSlug', ''))));
        if ($configuredSlug !== '') {
            $site = $this->siteBySlug($configuredSlug);
            if ($site instanceof Site) {
                return $site;
            }
        }

        // Sécurité multi-dossiers : un dossier facultaire dont le site
        // configuré est introuvable doit échouer visiblement (404 propre),
        // jamais servir silencieusement le contenu d'une autre faculté.
        if (
            $request !== null
            && ! $request instanceof CLIRequest
            && service('adminAccess')->isCentralAdminInstance() === false
            && trim((string) env('app.siteSlug', '')) !== ''
        ) {
            throw PageNotFoundException::forPageNotFound(
                'Cette instance est liée à un site facultaire introuvable ou inactif. '
                . 'Vérifiez app.siteSlug et créez le site depuis l’administration centrale.',
            );
        }

        return $this->defaultSite();
    }

    /**
     * Site auquel cette instance de dossier est liée : site correspondant à
     * l'hôte de la requête, sinon le site configuré via l'environnement.
     * Aucune dépendance à la session ou à l'utilisateur courant : sert à
     * vérifier l'accès à l'administration d'un dossier facultaire.
     */
    public function instanceBoundSite(RequestInterface $request): Site
    {
        if ($request instanceof CLIRequest) {
            return $this->configuredOrDefaultSite();
        }

        $hostSite = $this->siteForRequestHost($request);

        return $hostSite ?? $this->configuredOrDefaultSite();
    }

    private function siteForRequestHost(RequestInterface $request): ?Site
    {
        foreach ($this->hostCandidates($request) as $host) {
            foreach ($this->activeSites() as $site) {
                if ($this->siteHasHostname($site, $host)) {
                    return $site;
                }
            }
        }

        return null;
    }

    private function requiresKnownPublicHostname(): bool
    {
        return filter_var((string) env('app.requireKnownHostname', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    private function hasExplicitHttpHost(RequestInterface $request): bool
    {
        foreach ([
            $request->getHeaderLine('Host'),
            $request->getHeaderLine('HTTP_HOST'),
            (string) ($request->getServer('HTTP_HOST') ?? ''),
            $request->getHeaderLine('X-Forwarded-Host'),
        ] as $candidate) {
            if ($this->normalizedHost((string) $candidate) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function hostCandidates(RequestInterface $request): array
    {
        $candidates = [
            $request->getHeaderLine('Host'),
            $request->getHeaderLine('HTTP_HOST'),
            (string) ($request->getServer('HTTP_HOST') ?? ''),
            (string) ($request->getServer('SERVER_NAME') ?? ''),
        ];

        $forwardedHost = trim($request->getHeaderLine('X-Forwarded-Host'));
        if ($forwardedHost !== '') {
            $candidates[] = explode(',', $forwardedHost)[0];
        }

        $normalized = [];
        foreach ($candidates as $candidate) {
            $host = $this->normalizedHost((string) $candidate);
            if ($host !== '' && ! in_array($host, $normalized, true)) {
                $normalized[] = $host;
            }
        }

        return $normalized;
    }

    /**
     * @return list<Site>
     */
    private function activeSites(): array
    {
        try {
            return model(SiteModel::class, false)
                ->where('status', 'active')
                ->orderBy('name', 'ASC')
                ->findAll();
        } catch (Throwable) {
            return [$this->fallbackSite()];
        }
    }

    /**
     * @return list<Site>
     */
    private function assignedSites(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $rows = db_connect()->table('user_sites')
                ->select('sites.*')
                ->join('sites', 'sites.id = user_sites.site_id')
                ->where('user_sites.user_id', $userId)
                ->where('sites.status', 'active')
                ->orderBy('sites.name', 'ASC')
                ->get()
                ->getResultArray();
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn (array $row): Site => new Site($row), $rows);
    }

    private function siteById(int $siteId): ?Site
    {
        try {
            $site = model(SiteModel::class, false)->find($siteId);

            return $site instanceof Site ? $site : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function siteBySlug(string $slug): ?Site
    {
        try {
            $site = model(SiteModel::class, false)
                ->where('slug', $slug)
                ->where('status', 'active')
                ->first();

            return $site instanceof Site ? $site : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function defaultSite(): Site
    {
        $defaultSlug = strtolower(trim((string) env('app.defaultSiteSlug', '')));
        if ($defaultSlug !== '') {
            $site = $this->siteBySlug($defaultSlug);
            if ($site instanceof Site) {
                return $site;
            }
        }

        try {
            $first = model(SiteModel::class, false)
                ->where('status', 'active')
                ->orderBy('id', 'ASC')
                ->first();

            if ($first instanceof Site) {
                return $first;
            }
        } catch (Throwable) {
            // Base indisponible ou vide : repli neutre ci-dessous.
        }

        return $this->fallbackSite();
    }

    private function normalizeUserSiteRole(string $role): string
    {
        $role = strtolower(trim($role));

        return in_array($role, ['site_admin', 'editor'], true) ? $role : 'site_admin';
    }

    private function fallbackSite(): Site
    {
        $slug = strtolower(trim((string) env('app.siteSlug', 'faculte')));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?: 'faculte';

        return new Site([
            'id'              => 1,
            'identifier'      => 'site',
            'name'            => 'Site à configurer',
            'slug'            => $slug,
            'hostnames'       => ['localhost', '127.0.0.1'],
            'status'          => 'active',
            'default_locale'  => 'fr',
            'logo'            => 'assets/images/logo-placeholder.png',
            'primary_color'   => '#0D9B49',
            'secondary_color' => '#0B6F38',
        ]);
    }

    private function siteHasHostname(Site $site, string $host): bool
    {
        $hostnames = $site->hostnames;

        if (is_string($hostnames)) {
            $decoded = json_decode($hostnames, true);
            $hostnames = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $hostnames);
        }

        if (! is_array($hostnames)) {
            return false;
        }

        foreach ($hostnames as $hostname) {
            if ($this->normalizedHost((string) $hostname) === $host) {
                return true;
            }
        }

        return false;
    }

    private function normalizedHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return trim($host, '.');
    }

    private function isAdminRequest(RequestInterface $request): bool
    {
        try {
            return trim((string) $request->getUri()->getSegment(1), '/') === 'admin';
        } catch (Throwable) {
            return false;
        }
    }

    private function currentUser(): ?User
    {
        try {
            $user = function_exists('auth') ? auth()->user() : service('auth')->user();

            return $user instanceof User ? $user : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function isSuperAdmin(User $user): bool
    {
        return in_array('superadmin', $user->getGroups() ?? [], true);
    }
}
