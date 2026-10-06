<?php

namespace App\Services;

use App\Models\DepartmentModel;
use App\Models\PostModel;
use App\Models\ProgrammeModel;
use App\Models\StaffModel;
use Throwable;

/**
 * Public pages of the active site that an editor can pick as a link target.
 *
 * URLs are stored relative ("/formations") so they follow the visitor's language.
 */
class SitePageCatalog
{
    private const POST_LIMIT = 50;

    /**
     * @var list<array{group: string, label: string, url: string}>|null
     */
    private ?array $options = null;

    /**
     * @return list<array{group: string, label: string, url: string}>
     */
    public function options(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $options = $this->mainPages();

        foreach ($this->contentGroups() as $group => $rows) {
            foreach ($rows as $row) {
                $options[] = ['group' => $group, 'label' => $row['label'], 'url' => $row['url']];
            }
        }

        return $this->options = $options;
    }

    public function reset(): void
    {
        $this->options = null;
    }

    public function labelFor(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $normalized = $this->normalize($url);
        foreach ($this->options() as $option) {
            if ($this->normalize($option['url']) === $normalized) {
                return $option['label'];
            }
        }

        return null;
    }

    /**
     * @return list<array{group: string, label: string, url: string}>
     */
    private function mainPages(): array
    {
        $group = 'Pages principales';
        $labels = [];
        foreach (service('pageTextCatalog')->pages() as $page) {
            if (isset($page['public_path'])) {
                $labels[trim((string) $page['public_path'], '/')] = (string) $page['label'];
            }
        }

        $pages = [
            ['', 'Accueil'],
            ['#presentation', 'Présentation (accueil)'],
            ['faculte', 'La Faculté'],
            ['departements', 'Départements'],
            ['formations', 'Formations'],
            ['admissions', 'Admissions'],
            ['faq', 'FAQ'],
            ['recherche', 'Recherche'],
            ['corps-enseignant', 'Corps enseignant'],
            ['actualites', 'Actualités & événements'],
            ['alumni', 'Alumni'],
            ['contact', 'Contact'],
            ['mentions-legales', 'Mentions légales & confidentialité'],
            ['trouver', 'Recherche sur le site'],
        ];

        $options = [];
        foreach ($pages as [$path, $fallback]) {
            $options[] = [
                'group' => $group,
                'label' => $labels[$path] ?? $fallback,
                'url'   => '/' . $path,
            ];
        }

        return $options;
    }

    /**
     * @return array<string, list<array{label: string, url: string}>>
     */
    private function contentGroups(): array
    {
        $groups = [
            'Départements'     => fn (): array => $this->rows(model(DepartmentModel::class, false)->forSite()->where('is_published', 1)->orderBy('name', 'ASC'), 'name', 'departements'),
            'Formations'       => fn (): array => $this->rows(model(ProgrammeModel::class, false)->forSite()->where('is_published', 1)->whereIn('level', ['licence', 'master'])->orderBy('title', 'ASC'), 'title', 'formations'),
            'Corps enseignant' => fn (): array => $this->rows(model(StaffModel::class, false)->forSite()->where('is_published', 1)->orderBy('name', 'ASC'), 'name', 'corps-enseignant'),
            'Actualités'       => fn (): array => $this->rows(service('postVisibilityService')->applyVisible(model(PostModel::class, false)->forSite())->orderBy('published_at', 'DESC'), 'title', 'actualites', self::POST_LIMIT),
        ];

        $result = [];
        foreach ($groups as $label => $loader) {
            try {
                $result[$label] = $loader();
            } catch (Throwable $e) {
                log_message('warning', 'SitePageCatalog: ' . $label . ' unavailable: ' . $e->getMessage());
                $result[$label] = [];
            }
        }

        return $result;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function rows(object $model, string $labelField, string $prefix, int $limit = 0): array
    {
        $rows = $model->select($labelField . ', slug')->asArray()->findAll($limit);

        $items = [];
        foreach ($rows as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            $label = trim((string) ($row[$labelField] ?? ''));
            if ($slug === '' || $label === '') {
                continue;
            }
            $items[] = ['label' => $label, 'url' => '/' . $prefix . '/' . $slug];
        }

        return $items;
    }

    private function normalize(string $url): string
    {
        $url = trim($url);
        if ($url === '/' || $url === '') {
            return '/';
        }

        if (str_starts_with($url, '/#')) {
            return substr($url, 1);
        }

        return str_starts_with($url, '#') ? $url : '/' . trim($url, '/');
    }
}
