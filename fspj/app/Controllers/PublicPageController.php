<?php

namespace App\Controllers;

use App\Controllers\Concerns\LoadsPublicPages;
use App\Models\AlumniProfileModel;
use App\Models\LaboratoryModel;
use App\Models\PartnerModel;
use App\Models\PostModel;
use App\Models\ProgrammeModel;
use App\Models\PublicationModel;
use App\Models\ResearchProjectModel;
use App\Models\SiteStatModel;
use App\Models\StaffModel;
use App\Models\TestimonialModel;
use App\Models\TimelineItemModel;
use CodeIgniter\HTTP\ResponseInterface;

class PublicPageController extends BaseController
{
    use LoadsPublicPages;

    private const STAFF_PER_PAGE = 24;

    public function faculty(): string
    {
        $page    = $this->page('faculty');
        $content = $this->withSharedBanner($this->pageContent($page, $this->facultyFallback()));

        return view('faculty/index', [
            'title'        => site_text_or_placeholder($page?->seo_title ?? null),
            'description'  => site_text_or_placeholder($page?->seo_description ?? null),
            'activePage'   => 'faculty',
            'pageTitle'    => lang('Site.pageTitles.faculty'),
            'siteSettings' => $this->siteSettings,
            'page'         => $page,
            'content'      => $content,
            'timeline'     => service('contentTranslationService')->records('timeline_items', model(TimelineItemModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->orderBy('display_order', 'ASC')
                ->findAll()),
            'partners'     => service('contentTranslationService')->records('partners', model(PartnerModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->orderBy('display_order', 'ASC')
                ->orderBy('name', 'ASC')
                ->findAll()),
        ]);
    }

    public function programmes(): string
    {
        $page    = $this->page('formations');
        $content = $this->withSharedBanner($this->pageContent($page, $this->programmesFallback()));
        $grouped = ['licence' => [], 'master' => []];
        $departments = service('facultyDirectory')->departments();
        $selectedDepartment = null;
        $departmentSlug = trim((string) $this->request->getGet('departement'));
        foreach ($departments as $department) {
            if ($departmentSlug !== '' && $department->slug === $departmentSlug) {
                $selectedDepartment = $department;
            }
        }

        $model = model(ProgrammeModel::class, false)
            ->forSite()
            ->where('is_published', 1);
        if ($selectedDepartment !== null) {
            $model->where('department_id', (int) $selectedDepartment->id);
        }

        $stored = $model->orderBy('display_order', 'ASC')->findAll();
        $programmes = service('contentTranslationService')->records('programmes', $stored);
        $catalogue = service('studyPaths')->catalogue();
        $studySummaries = service('studyPaths')->summaries($catalogue['stored'], $catalogue['display']);

        foreach ($programmes as $programme) {
            $level = (string) $programme->level;

            // Robustesse : ignorer silencieusement un niveau hors référentiel
            // plutôt que lever une erreur sur la page publique.
            if (! isset($grouped[$level])) {
                continue;
            }

            $grouped[$level][] = $programme;
        }

        $activeLevel = (string) $this->request->getGet('niveau');
        if (! isset($grouped[$activeLevel])) {
            $activeLevel = ($selectedDepartment->cycle ?? null) === 'master' ? 'master' : 'licence';
        }

        return view('programmes/index', [
            'activeLevel'  => $activeLevel,
            'title'        => site_text_or_placeholder($page?->seo_title ?? null),
            'description'  => site_text_or_placeholder($page?->seo_description ?? null),
            'activePage'   => 'programmes',
            'pageTitle'    => lang('Site.pageTitles.programmes'),
            'siteSettings' => $this->siteSettings,
            'content'      => $content,
            'programmes'   => $grouped,
            'studySummaries' => $studySummaries,
            'doctoralUrl'  => $this->doctoralStudiesUrl(),
            'departments'  => $departments,
            'selectedDepartment' => $selectedDepartment,
        ]);
    }

    public function programmeDetail(string $slug): string|ResponseInterface
    {
        $programme = model(ProgrammeModel::class, false)
            ->forSite()
            ->where('slug', $slug)
            ->where('is_published', 1)
            ->whereIn('level', ['licence', 'master'])
            ->first();

        if ($programme === null) {
            return $this->notFound(lang('Site.programmes.notFound'));
        }

        $programme = service('contentTranslationService')->record('programmes', $programme);
        $directory = service('facultyDirectory');
        $departments = $directory->departments();
        $catalogue = service('studyPaths')->catalogue();
        $studyStages = service('studyPaths')->forProgramme((int) $programme->id, $catalogue['stored'], $catalogue['display'], $departments);
        $brochure = trim((string) ($programme->brochure_path ?? ''));
        $brochureSize = $brochure === '' ? null : service('mediaService')->publicFileSize($brochure);

        return view('programmes/show', [
            'title'          => site_text_or_placeholder($programme->title) . ' | ' . site_text_or_placeholder($this->siteSettings['institution.short_name'] ?? null),
            'description'    => $programme->summary,
            'activePage'     => 'programmes',
            'pageTitle'      => $programme->title,
            'siteSettings'   => $this->siteSettings,
            'programme'      => $programme,
            'department'     => $departments[(int) ($programme->department_id ?? 0)] ?? null,
            'studyStages'    => $studyStages,
            'coordinator'    => $directory->staffMember($programme->coordinator_staff_id ?? null),
            'curriculum'     => $this->curriculumGroups($programme->curriculum ?? null),
            'brochureUrl'    => $brochureSize === null ? null : site_media_url($brochure),
            'brochureSize'   => $brochureSize,
            'structuredData' => [service('structuredData')->course($programme, $programme->language ?? null)],
        ]);
    }

    /**
     * External site of the doctoral school, empty until it is configured.
     */
    private function doctoralStudiesUrl(): ?string
    {
        $url = trim((string) ($this->siteSettings['links.doctoral_studies_url'] ?? ''));

        return str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    public function research(): string
    {
        $page    = $this->page('research');
        $content = $this->withSharedBanner($this->pageContent($page, $this->researchFallback()));

        return view('research/index', [
            'title'        => site_text_or_placeholder($page?->seo_title ?? null),
            'description'  => site_text_or_placeholder($page?->seo_description ?? null),
            'activePage'   => 'research',
            'pageTitle'    => lang('Site.pageTitles.research'),
            'siteSettings' => $this->siteSettings,
            'content'      => $content,
            'laboratories' => service('contentTranslationService')->records('laboratories', model(LaboratoryModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->orderBy('display_order', 'ASC')
                ->findAll()),
            'publications' => service('contentTranslationService')->records('publications', model(PublicationModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->orderBy('year', 'DESC')
                ->orderBy('display_order', 'ASC')
                ->findAll()),
            'projects'     => service('contentTranslationService')->records('research_projects', model(ResearchProjectModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->orderBy('display_order', 'ASC')
                ->findAll()),
            'stats'        => service('contentTranslationService')->records('site_stats', model(SiteStatModel::class, false)
                ->forSite()
                ->where('section', 'home_research')
                ->where('is_published', 1)
                ->orderBy('display_order', 'ASC')
                ->findAll()),
        ]);
    }

    public function staff(): string
    {
        $page    = $this->page('staff');
        $content = $this->pageContent($page, $this->staffFallback());
        $translator = service('contentTranslationService');
        $departments = service('facultyDirectory')->departments();

        $filters = [
            'q'           => mb_substr(trim((string) $this->request->getGet('q')), 0, 120),
            'categorie'   => (string) $this->request->getGet('categorie'),
            'departement' => trim((string) $this->request->getGet('departement')),
            'grade'       => mb_substr(trim((string) $this->request->getGet('grade')), 0, 120),
        ];
        if (! in_array($filters['categorie'], StaffModel::CATEGORIES, true)) {
            $filters['categorie'] = '';
        }

        $departmentId = null;
        foreach ($departments as $department) {
            if ($filters['departement'] !== '' && $department->slug === $filters['departement']) {
                $departmentId = (int) $department->id;
            }
        }
        if ($departmentId === null) {
            $filters['departement'] = '';
        }

        $grades = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string) $row['grade']),
            db_connect()->table('staff')
                ->select('grade')
                ->where('site_id', service('siteResolver')->activeSiteId())
                ->where('is_published', 1)
                ->where('deleted_at', null)
                ->where('grade IS NOT NULL', null, false)
                ->where('grade !=', '')
                ->orderBy('grade', 'ASC')
                ->get()
                ->getResultArray(),
        ))));
        if ($filters['grade'] !== '' && ! in_array($filters['grade'], $grades, true)) {
            $filters['grade'] = '';
        }

        $counts = ['' => 0];
        foreach (StaffModel::CATEGORIES as $category) {
            $counts[$category] = 0;
        }
        foreach (model(StaffModel::class, false)->forSite()->select('category')->where('is_published', 1)->findAll() as $row) {
            $counts[''] = $counts[''] + 1;
            $counts[(string) $row->category] = ($counts[(string) $row->category] ?? 0) + 1;
        }

        $model = model(StaffModel::class, false)
            ->forSite()
            ->where('is_published', 1);
        if ($filters['categorie'] !== '') {
            $model->where('category', $filters['categorie']);
        }
        if ($departmentId !== null) {
            $model->where('department_id', $departmentId);
        }
        if ($filters['grade'] !== '') {
            $model->where('grade', $filters['grade']);
        }
        if ($filters['q'] !== '') {
            $translatedIds = $translator->matchingResourceIds('staff', $filters['q'], ['name', 'grade', 'specialty', 'role']);
            $model->groupStart()
                ->like('name', $filters['q'])
                ->orLike('specialty', $filters['q'])
                ->orLike('grade', $filters['q'])
                ->orLike('role', $filters['q']);
            if ($translatedIds !== []) {
                $model->orWhereIn('id', $translatedIds);
            }
            $model->groupEnd();
        }

        $staff = $translator->records('staff', $model
            ->orderBy('display_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->paginate(self::STAFF_PER_PAGE, 'staff'));

        return view('staff/index', [
            'title'        => site_text_or_placeholder($page?->seo_title ?? null),
            'description'  => site_text_or_placeholder($page?->seo_description ?? null),
            'activePage'   => 'staff',
            'pageTitle'    => lang('Site.pageTitles.staff'),
            'siteSettings' => $this->siteSettings,
            'content'      => $content,
            'staff'        => $staff,
            'pager'        => $model->pager,
            'filters'      => $filters,
            'counts'       => $counts,
            'grades'       => $grades,
            'departments'  => $departments,
        ]);
    }

    public function staffDetail(string $slug): string|ResponseInterface
    {
        $member = model(StaffModel::class, false)
            ->forSite()
            ->where('slug', $slug)
            ->where('is_published', 1)
            ->first();

        if ($member === null) {
            return $this->notFound(lang('Site.staff.notFound'));
        }

        $member = service('contentTranslationService')->record('staff', $member);
        $departments = service('facultyDirectory')->departments();

        return view('staff/show', [
            'title'          => site_text_or_placeholder($member->name) . ' | ' . site_text_or_placeholder($this->siteSettings['institution.short_name'] ?? null),
            'description'    => trim((string) ($member->role . ' ' . $member->specialty)),
            'activePage'     => 'staff',
            'pageTitle'      => $member->name,
            'siteSettings'   => $this->siteSettings,
            'member'         => $member,
            'department'     => $departments[(int) ($member->department_id ?? 0)] ?? null,
            'structuredData' => [service('structuredData')->person($member)],
        ]);
    }

    public function posts(): string
    {
        $page    = $this->page('posts');
        $content = $this->withSharedBanner($this->pageContent($page, $this->postsFallback()));
        $type    = $this->postTypeFromRequest((string) $this->request->getGet('type'));
        $query   = trim((string) $this->request->getGet('q'));
        $translator = service('contentTranslationService');

        $when    = $type === 'event' ? $this->eventWindowFromRequest((string) $this->request->getGet('quand')) : null;

        $model = $this->visiblePosts();
        if ($type === 'event' && $when === 'upcoming') {
            $model->where('type', 'event')
                ->where('COALESCE(event_ends_at, event_starts_at) >=', date('Y-m-d H:i:s'))
                ->orderBy('event_starts_at', 'ASC');
        } elseif ($type === 'event' && $when === 'past') {
            $model->where('type', 'event')
                ->where('COALESCE(event_ends_at, event_starts_at) <', date('Y-m-d H:i:s'))
                ->orderBy('event_starts_at', 'DESC');
        } elseif ($type === 'event') {
            $model->where('type', 'event')->orderBy('event_starts_at', 'ASC');
        } elseif ($type === 'news') {
            $model->where('type', 'news')->orderBy('published_at', 'DESC')->orderBy('id', 'DESC');
        } else {
            // "Tous": one stream of both types; an event is dated by its start, so upcoming ones lead.
            $model->whereIn('type', ['news', 'event'])
                ->orderBy('COALESCE(event_starts_at, published_at)', 'DESC', false)
                ->orderBy('id', 'DESC');
        }
        $this->applyPostSearch($model, $query);

        $posts = $translator->records('posts', $model->paginate(6, 'posts'));

        return view('posts/index', [
            'title'          => site_text_or_placeholder($page?->seo_title ?? null),
            'description'    => site_text_or_placeholder($page?->seo_description ?? null),
            'activePage'     => 'posts',
            'pageTitle'      => lang('Site.pageTitles.posts'),
            'siteSettings'   => $this->siteSettings,
            'content'        => $content,
            'posts'          => $posts,
            'pager'          => $model->pager,
            'selectedType'   => $type,
            'selectedWhen'   => $when,
            'query'          => $query,
        ]);
    }

    public function postDetail(string $slug): string|ResponseInterface
    {
        $post = $this->visiblePosts()
            ->where('slug', $slug)
            ->first();

        if ($post === null) {
            return $this->notFound(lang('Site.posts.notFound'));
        }

        $post = service('contentTranslationService')->record('posts', $post);
        $isDatedEvent = $post->type === 'event' && ! empty($post->event_starts_at);

        return view('posts/show', [
            'title'          => site_text_or_placeholder($post->seo_title ?? null, site_text_or_placeholder($post->title ?? null)) . ' | ' . site_text_or_placeholder($this->siteSettings['institution.short_name'] ?? null),
            'description'    => site_text_or_placeholder($post->seo_description ?? null, site_text_or_placeholder($post->excerpt ?? null)),
            'activePage'     => 'posts',
            'pageTitle'      => $post->title,
            'siteSettings'   => $this->siteSettings,
            'post'           => $post,
            'icsUrl'         => $isDatedEvent ? site_url('actualites/' . $post->slug . '/ics') : null,
            'attachmentSize' => service('mediaService')->publicFileSize($post->attachment_path ?? null),
            'structuredData' => $isDatedEvent ? [service('structuredData')->event($post)] : [],
        ]);
    }

    public function alumni(): string
    {
        $page    = $this->page('alumni');
        $content = $this->withSharedBanner($this->pageContent($page, $this->alumniFallback()));

        return view('alumni/index', [
            'title'        => site_text_or_placeholder($page?->seo_title ?? null),
            'description'  => site_text_or_placeholder($page?->seo_description ?? null),
            'activePage'   => 'alumni',
            'pageTitle'    => lang('Site.pageTitles.alumni'),
            'siteSettings' => $this->siteSettings,
            'content'      => $content,
            'stats'        => service('contentTranslationService')->records('site_stats', model(SiteStatModel::class, false)
                ->forSite()
                ->where('section', 'alumni')
                ->where('is_published', 1)
                ->orderBy('display_order', 'ASC')
                ->findAll()),
            'profiles'     => service('contentTranslationService')->records('alumni_profiles', model(AlumniProfileModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->orderBy('display_order', 'ASC')
                ->findAll()),
            'testimonials' => service('contentTranslationService')->records('testimonials', model(TestimonialModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->orderBy('display_order', 'ASC')
                ->findAll()),
        ]);
    }

    private function visiblePosts(): PostModel
    {
        return model(PostModel::class, false)->visible();
    }

    private function eventWindowFromRequest(string $when): ?string
    {
        return match ($when) {
            'a-venir' => 'upcoming',
            'passes'  => 'past',
            default   => null,
        };
    }

    /**
     * Curriculum lines are stored as "Year or semester | Course | Credits".
     *
     * @return array<string, list<array{title: string, credits: string}>>
     */
    private function curriculumGroups(mixed $lines): array
    {
        $groups = [];
        foreach (is_array($lines) ? $lines : [] as $line) {
            $parts = array_map('trim', explode('|', (string) $line));
            if (count($parts) === 1) {
                array_unshift($parts, '');
            }

            [$group, $title] = [$parts[0], $parts[1] ?? ''];
            if ($title === '') {
                continue;
            }

            $groups[$group][] = ['title' => $title, 'credits' => $parts[2] ?? ''];
        }

        return $groups;
    }

    private function postTypeFromRequest(string $type): ?string
    {
        return match ($type) {
            'actualite', 'news'    => 'news',
            'evenement', 'event'   => 'event',
            default                => null,
        };
    }

    private function applyPostSearch(PostModel $model, string $query): void
    {
        if ($query === '') {
            return;
        }

        $translatedIds = $this->translatedPostIdsForSearch($query);

        $model->groupStart()
            ->like('title', $query)
            ->orLike('excerpt', $query)
            ->orLike('body', $query);

        if ($translatedIds !== []) {
            $model->orWhereIn('id', $translatedIds);
        }

        $model->groupEnd();
    }

    /**
     * @return list<int>
     */
    private function translatedPostIdsForSearch(string $query): array
    {
        if (service('contentTranslationService')->isDefaultLocale()) {
            return [];
        }

        $rows = db_connect()->table('content_translations')
            ->select('resource_id')
            ->where('site_id', service('siteResolver')->activeSiteId())
            ->where('resource_type', 'posts')
            ->where('locale', site_current_locale())
            ->whereIn('field', ['title', 'excerpt', 'body', 'event_location', 'seo_title', 'seo_description'])
            ->like('value', $query)
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['resource_id'],
            $rows,
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function facultyFallback(): array
    {
        return [
            'banner_subtitle' => lang('Home.configurationMissing'),
            'banner_image'    => null,
            'dean'            => ['paragraphs' => [lang('Home.configurationMissing')]],
            'mission'         => ['title' => lang('Home.configurationMissing'), 'paragraphs' => [lang('Home.configurationMissing')]],
            'vision'          => ['title' => lang('Home.configurationMissing'), 'paragraphs' => [lang('Home.configurationMissing')]],
            'values'          => [],
            'history'         => ['label' => lang('Home.configurationMissing'), 'title' => lang('Home.configurationMissing'), 'text' => lang('Home.configurationMissing')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function programmesFallback(): array
    {
        return [
            'banner_subtitle' => lang('Home.configurationMissing'),
            'banner_image'    => null,
            'offer_label'     => lang('Home.configurationMissing'),
            'offer_title'     => lang('Home.configurationMissing'),
            'offer_text'      => lang('Home.configurationMissing'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function researchFallback(): array
    {
        return [
            'banner_subtitle'      => lang('Home.configurationMissing'),
            'banner_image'         => null,
            'labs_label'           => lang('Home.configurationMissing'),
            'labs_title'           => lang('Home.configurationMissing'),
            'labs_text'            => lang('Home.configurationMissing'),
            'publications_label'   => lang('Home.configurationMissing'),
            'publications_title'   => lang('Home.configurationMissing'),
            'publications_text'    => lang('Home.configurationMissing'),
            'projects_label'       => lang('Home.configurationMissing'),
            'projects_title'       => lang('Home.configurationMissing'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffFallback(): array
    {
        return [
            'banner_subtitle' => lang('Home.configurationMissing'),
            'banner_image'    => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postsFallback(): array
    {
        return [
            'banner_subtitle' => lang('Home.configurationMissing'),
            'banner_image'    => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function alumniFallback(): array
    {
        return [
            'banner_subtitle' => lang('Home.configurationMissing'),
            'banner_image'    => null,
            'intro'           => ['paragraphs' => [lang('Home.configurationMissing')]],
        ];
    }
}
