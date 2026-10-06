<?php

namespace App\Services;

use App\Entities\Site;
use App\Models\DepartmentModel;
use App\Models\SiteModel;
use App\Models\StaffModel;
use Throwable;

/**
 * Where things live: public URLs of the faculty sites, the sister faculties
 * shown in the footer, and the departments / staff of the active site.
 */
class FacultyDirectoryService
{
    /**
     * Local multi-folder ports when hostnames are empty.
     *
     * @var array<string, int>
     */
    private const LOCAL_PORTS = [
        'fseg' => 8101,
        'fsi'  => 8102,
        'med'  => 8104,
        'fabi' => 8105,
        'flsh' => 8106,
    ];

    /**
     * @param Site|array<string, mixed>|null $site
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

        $appBase = (string) (config('App')->baseURL ?? '');
        $isLocalApp = str_contains($appBase, 'localhost') || str_contains($appBase, '127.0.0.1');
        if ($host === '' && $isLocalApp && isset(self::LOCAL_PORTS[$slug])) {
            return 'http://localhost:' . self::LOCAL_PORTS[$slug] . '/';
        }

        if ($host === '') {
            return null;
        }

        $hostName = strtolower((string) explode(':', $host)[0]);
        $hostIsDev = in_array($hostName, ['localhost', '127.0.0.1'], true) || str_ends_with($hostName, '.test');

        if ($isLocalApp && $hostIsDev) {
            return 'http://' . $host . '/';
        }

        return 'https://' . $host;
    }

    /**
     * Other active faculty sites with a reachable public URL.
     *
     * @return list<array{name: string, short: string, url: string}>
     */
    public function sisterFaculties(): array
    {
        try {
            $current = (int) service('siteResolver')->activeSiteId();
            $sites = model(SiteModel::class, false)
                ->where('status', 'active')
                ->orderBy('name', 'ASC')
                ->findAll();
        } catch (Throwable) {
            return [];
        }

        $resolver = service('siteResolver');
        $faculties = [];
        foreach ($sites as $site) {
            if ((int) $site->id === $current || $resolver->isSkeletonSite($site)) {
                continue;
            }

            $url = $this->publicUrlForSite($site);
            if ($url === null) {
                continue;
            }

            $faculties[] = [
                'name'  => (string) $site->name,
                'short' => strtoupper((string) ($site->identifier ?? $site->slug)),
                'url'   => $url,
            ];
        }

        return $faculties;
    }

    /**
     * Published departments of the active site, translated, keyed by id.
     * With a level, only departments whose cycle hosts that level.
     *
     * @return array<int, object>
     */
    public function departments(?string $level = null): array
    {
        if ($level !== null) {
            return array_filter($this->departments(), static fn (object $department): bool => DepartmentModel::cycleAccepts($department->cycle ?? null, $level));
        }

        try {
            $rows = model(DepartmentModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->orderBy('display_order', 'ASC')
                ->orderBy('name', 'ASC')
                ->findAll();
        } catch (Throwable) {
            $rows = [];
        }

        $departments = [];
        foreach (service('contentTranslationService')->records('departments', $rows) as $department) {
            $departments[(int) $department->id] = $department;
        }

        return $departments;
    }

    /**
     * A published staff member of the active site, translated.
     */
    public function staffMember(?int $id): ?object
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        $member = model(StaffModel::class, false)
            ->forSite()
            ->where('is_published', 1)
            ->find($id);

        return $member === null ? null : service('contentTranslationService')->record('staff', $member);
    }
}
