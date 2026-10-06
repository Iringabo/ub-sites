<?php

namespace App\Controllers;

use App\Controllers\Concerns\LoadsPublicPages;
use App\Models\DepartmentModel;
use App\Models\FaqItemModel;
use App\Models\ProgrammeModel;
use App\Models\StaffModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pages prospective students look for: admissions, departments and FAQ,
 * plus the legal notice.
 */
class InformationPageController extends BaseController
{
    use LoadsPublicPages;

    public function admissions(): string
    {
        $page = $this->page('admissions');
        $content = $this->withSharedBanner($this->pageContent($page, $this->fallback('admissions')));
        $translator = service('contentTranslationService');

        $programmes = $translator->records('programmes', model(ProgrammeModel::class, false)
            ->forSite()
            ->where('is_published', 1)
            ->orderBy('application_deadline IS NULL', 'ASC', false)
            ->orderBy('application_deadline', 'ASC')
            ->orderBy('display_order', 'ASC')
            ->findAll());

        $faq = $this->faqItems('admissions');

        return view('information/admissions', [
            'title'          => $this->seoTitle($page, lang('Site.pageTitles.admissions')),
            'description'    => $this->seoDescription($page, (string) ($content['intro_text'] ?? '')),
            'activePage'     => 'admissions',
            'pageTitle'      => lang('Site.pageTitles.admissions'),
            'siteSettings'   => $this->siteSettings,
            'content'        => $content,
            'programmes'     => $programmes,
            'faq'            => $faq,
            'structuredData' => $faq === [] ? [] : [service('structuredData')->faqPage($faq)],
        ]);
    }

    public function departments(): string
    {
        $page = $this->page('departments');
        $content = $this->withSharedBanner($this->pageContent($page, $this->fallback('departments')));
        $departments = service('facultyDirectory')->departments();

        $programmeCounts = $this->countsByDepartment(ProgrammeModel::class);
        $staffCounts = $this->countsByDepartment(StaffModel::class);
        $groups = [
            'licence' => array_filter($departments, static fn (object $department): bool => ($department->cycle ?? 'both') !== 'master'),
            'master'  => array_filter($departments, static fn (object $department): bool => ($department->cycle ?? 'both') !== 'licence'),
        ];

        return view('information/departments', [
            'title'           => $this->seoTitle($page, lang('Site.pageTitles.departments')),
            'description'     => $this->seoDescription($page, (string) ($content['intro_text'] ?? '')),
            'activePage'      => 'departments',
            'pageTitle'       => lang('Site.pageTitles.departments'),
            'siteSettings'    => $this->siteSettings,
            'content'         => $content,
            'departments'     => $departments,
            'groups'          => array_filter($groups),
            'staffCounts'     => $staffCounts,
            'heads'           => $this->staffByIds(array_map(static fn (object $department): ?int => $department->head_staff_id, $departments)),
            'programmeCounts' => $programmeCounts,
            'optionCodes'     => $this->optionCodesByDepartment(),
        ]);
    }

    /**
     * Department id => cycle group (licence / master) => option codes.
     *
     * @return array<int, array<string, list<string>>>
     */
    private function optionCodesByDepartment(): array
    {
        $rows = model(ProgrammeModel::class, false)->forSite()
            ->select('department_id, level, option_code')
            ->where('is_published', 1)
            ->where('department_id IS NOT NULL', null, false)
            ->where('option_code IS NOT NULL', null, false)
            ->where('option_code !=', '')
            ->orderBy('display_order', 'ASC')
            ->asArray()
            ->findAll();

        $codes = [];
        foreach ($rows as $row) {
            $group = $row['level'] === 'licence' ? 'licence' : 'master';
            $code = trim((string) $row['option_code']);
            $list = $codes[(int) $row['department_id']][$group] ?? [];
            if (! in_array($code, $list, true)) {
                $codes[(int) $row['department_id']][$group][] = $code;
            }
        }

        return $codes;
    }

    public function departmentDetail(string $slug): string|ResponseInterface
    {
        $department = model(DepartmentModel::class, false)
            ->forSite()
            ->where('slug', $slug)
            ->where('is_published', 1)
            ->first();

        if ($department === null) {
            return $this->notFound(lang('Site.departments.notFound'));
        }

        $translator = service('contentTranslationService');
        $department = $translator->record('departments', $department);

        $catalogue = service('studyPaths')->catalogue();
        $programmes = array_values(array_filter($catalogue['display'], static fn (object $programme): bool => (int) ($programme->department_id ?? 0) === (int) $department->id));
        $studyPaths = service('studyPaths')->forDepartment((int) $department->id, $catalogue['stored'], $catalogue['display'], service('facultyDirectory')->departments());
        $maquette = trim((string) ($department->maquette_path ?? ''));
        $maquetteSize = $maquette === '' ? null : service('mediaService')->publicFileSize($maquette);

        $staff = $translator->records('staff', model(StaffModel::class, false)
            ->forSite()
            ->where('is_published', 1)
            ->where('department_id', (int) $department->id)
            ->orderBy('display_order', 'ASC')
            ->findAll());

        return view('information/department_show', [
            'title'        => site_text_or_placeholder($department->name) . ' | ' . site_text_or_placeholder($this->siteSettings['institution.short_name'] ?? null),
            'description'  => mb_substr(trim((string) ($department->description ?? '')), 0, 300),
            'activePage'   => 'departments',
            'pageTitle'    => $department->name,
            'siteSettings' => $this->siteSettings,
            'department'   => $department,
            'head'         => service('facultyDirectory')->staffMember($department->head_staff_id),
            'programmes'   => $programmes,
            'studyPaths'   => $studyPaths,
            'maquetteUrl'  => $maquetteSize === null ? null : site_media_url($maquette),
            'maquetteSize' => $maquetteSize,
            'staff'        => $staff,
        ]);
    }

    public function faq(): string
    {
        $page = $this->page('faq');
        $content = $this->withSharedBanner($this->pageContent($page, $this->fallback('faq')));
        $items = $this->faqItems();

        $grouped = [];
        foreach (FaqItemModel::CATEGORIES as $category) {
            $inCategory = array_values(array_filter($items, static fn (object $item): bool => $item->category === $category));
            if ($inCategory !== []) {
                $grouped[$category] = $inCategory;
            }
        }

        return view('information/faq', [
            'title'          => $this->seoTitle($page, lang('Site.pageTitles.faq')),
            'description'    => $this->seoDescription($page, (string) ($content['intro_text'] ?? '')),
            'activePage'     => 'faq',
            'pageTitle'      => lang('Site.pageTitles.faq'),
            'siteSettings'   => $this->siteSettings,
            'content'        => $content,
            'grouped'        => $grouped,
            'structuredData' => $items === [] ? [] : [service('structuredData')->faqPage($items)],
        ]);
    }

    public function legal(): string
    {
        $page = $this->page('legal');
        $content = $this->pageContent($page, $this->fallback('legal'));

        return view('information/legal', [
            'title'            => $this->seoTitle($page, lang('Site.pageTitles.legal')),
            'description'      => $this->seoDescription($page, lang('Site.legal.description')),
            'activePage'       => 'legal',
            'pageTitle'        => lang('Site.pageTitles.legal'),
            'siteSettings'     => $this->siteSettings,
            'content'          => $content,
            'analytics'        => service('analytics')->config($this->siteSettings),
        ]);
    }

    /**
     * @return list<object>
     */
    private function faqItems(?string $category = null): array
    {
        $model = model(FaqItemModel::class, false)
            ->forSite()
            ->where('is_published', 1);

        if ($category !== null) {
            $model->where('category', $category);
        }

        return service('contentTranslationService')->records('faq_items', $model
            ->orderBy('display_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll());
    }

    /**
     * @param class-string $modelClass
     *
     * @return array<int, int>
     */
    private function countsByDepartment(string $modelClass): array
    {
        $counts = [];
        $rows = model($modelClass, false)->forSite()
            ->select('department_id, COUNT(*) AS total')
            ->where('is_published', 1)
            ->where('department_id IS NOT NULL', null, false)
            ->groupBy('department_id')
            ->asArray()
            ->findAll();
        foreach ($rows as $row) {
            $counts[(int) $row['department_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param list<int|null> $ids
     *
     * @return array<int, object>
     */
    private function staffByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null && $id > 0)));
        if ($ids === []) {
            return [];
        }

        $members = [];
        foreach (service('contentTranslationService')->records('staff', model(StaffModel::class, false)->forSite()->where('is_published', 1)->whereIn('id', $ids)->findAll()) as $member) {
            $members[(int) $member->id] = $member;
        }

        return $members;
    }

    private function seoTitle(?object $page, string $fallback): string
    {
        $title = site_text_or_placeholder($page?->seo_title ?? null, $fallback);

        return $title . ' | ' . site_text_or_placeholder($this->siteSettings['institution.short_name'] ?? null);
    }

    private function seoDescription(?object $page, string $fallback): string
    {
        return site_text_or_placeholder($page?->seo_description ?? null, $fallback !== '' ? $fallback : null);
    }

    /**
     * Neutral texts shown until the faculty edits the page.
     *
     * @return array<string, mixed>
     */
    private function fallback(string $page): array
    {
        $fallbacks = [
            'admissions' => [
                'intro_title'     => lang('Site.admissions.defaultTitle'),
                'steps'           => [],
                'documents_title' => lang('Site.admissions.documentsTitle'),
                'documents'       => [],
                'fees_title'      => lang('Site.admissions.feesTitle'),
                'portal_label'    => lang('Site.admissions.applyButton'),
            ],
            'departments' => ['intro_title' => lang('Site.departments.defaultTitle')],
            'faq'         => ['intro_title' => lang('Site.faq.defaultTitle')],
            'legal'       => [],
        ];

        return ($fallbacks[$page] ?? []) + ['banner_subtitle' => '', 'banner_image' => null];
    }
}
