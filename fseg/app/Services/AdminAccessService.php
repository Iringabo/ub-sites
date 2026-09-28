<?php

namespace App\Services;

use App\Support\TrustedProxies;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Shield\Entities\User;

class AdminAccessService
{
    /**
     * @return list<string>
     */
    public function centralAdminHosts(): array
    {
        $configured = trim((string) env('app.centralAdminHosts', ''));
        if ($configured === '') {
            $configured = trim((string) env('app.centralAdminHost', ''));
        }

        $hosts = $configured === ''
            ? ['admin.ub.edu.bi', 'admin.ub.local']
            : preg_split('/[\s,]+/', $configured);

        return array_values(array_unique(array_filter(array_map(
            fn (string $host): string => $this->normalizedHost($host),
            $hosts ?: [],
        ))));
    }

    public function isCentralAdminHost(?RequestInterface $request = null): bool
    {
        // Folder-based deployments (one physical instance per faculty, plus a
        // dedicated "admin" instance) cannot rely on hostname matching, since
        // every instance may sit behind the same domain or none at all. In
        // that case the instance's own .env declares itself as the central
        // admin instance directly, regardless of host.
        if ($this->isCentralAdminInstance()) {
            return true;
        }

        $request ??= service('request');

        return in_array($this->requestHost($request), $this->centralAdminHosts(), true);
    }

    /**
     * True when this physical instance (this copy of the codebase, i.e. this
     * folder on disk) is dedicated to the central superadmin app, as opposed
     * to a single faculty's public/admin site. Set via `app.centralAdminMode`
     * in that instance's own .env file. See docs/ARCHITECTURE.md.
     */
    public function isCentralAdminInstance(): bool
    {
        return filter_var((string) env('app.centralAdminMode', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public function isSuperAdmin(?User $user = null): bool
    {
        $user ??= $this->currentUser();

        return $user instanceof User && in_array('superadmin', $user->getGroups() ?? [], true);
    }

    public function canAccessCentralAdmin(?User $user = null): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function canAccessFacultyAdmin(int $siteId, ?User $user = null): bool
    {
        $user ??= $this->currentUser();
        if (! $user instanceof User || $siteId <= 0) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (! ($user->can('admin.access') ?? false)) {
            return false;
        }

        return in_array($this->siteRole((int) $user->id, $siteId), ['site_admin', 'editor'], true);
    }

    public function canManageFacultyUsers(int $siteId, ?User $user = null): bool
    {
        $user ??= $this->currentUser();
        if (! $user instanceof User || $siteId <= 0) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return ($user->can('users.manage') ?? false)
            && $this->siteRole((int) $user->id, $siteId) === 'site_admin';
    }

    public function canEditUserForSite(User $target, int $siteId, ?User $actor = null): bool
    {
        $actor ??= $this->currentUser();
        if (! $actor instanceof User || $siteId <= 0) {
            return false;
        }

        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        if (! $this->canManageFacultyUsers($siteId, $actor)) {
            return false;
        }

        $targetGroups = $target->getGroups() ?? [];
        if (in_array('superadmin', $targetGroups, true)) {
            return false;
        }

        return in_array($this->siteRole((int) $target->id, $siteId), ['site_admin', 'editor'], true);
    }

    /**
     * @return list<int>
     */
    public function visibleUserIdsForFaculty(int $siteId): array
    {
        if ($siteId <= 0 || ! db_connect()->tableExists('user_sites')) {
            return [];
        }

        $db = db_connect();
        $rows = $db->table('user_sites')
            ->select('user_id')
            ->where('site_id', $siteId)
            ->get()
            ->getResultArray();

        $ids = array_map(
            static fn (array $row): int => (int) $row['user_id'],
            $rows,
        );

        // Faculty managers must see platform superadmins in the list as
        // out-of-scope rows; those accounts are not in user_sites.
        $groupsTable = config('Auth')->tables['groups_users'] ?? 'auth_groups_users';
        if ($db->tableExists($groupsTable)) {
            $superRows = $db->table($groupsTable)
                ->select('user_id')
                ->where('group', 'superadmin')
                ->get()
                ->getResultArray();
            foreach ($superRows as $row) {
                $ids[] = (int) $row['user_id'];
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    public function siteRole(int $userId, int $siteId): ?string
    {
        if ($userId <= 0 || $siteId <= 0 || ! db_connect()->tableExists('user_sites')) {
            return null;
        }

        $row = db_connect()->table('user_sites')
            ->select('role')
            ->where('user_id', $userId)
            ->where('site_id', $siteId)
            ->get()
            ->getRowArray();

        return is_array($row) ? (string) ($row['role'] ?? '') : null;
    }

    /**
     * @param list<string> $groups
     */
    public function roleForGroups(array $groups): string
    {
        return in_array('admin', $groups, true) ? 'site_admin' : 'editor';
    }

    public function currentUser(): ?User
    {
        try {
            $user = function_exists('auth') ? auth()->user() : service('auth')->user();

            return $user instanceof User ? $user : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function requestHost(RequestInterface $request): string
    {
        return TrustedProxies::hostCandidates($request)[0] ?? '';
    }

    private function normalizedHost(string $host): string
    {
        return TrustedProxies::normalizedHost($host);
    }
}
