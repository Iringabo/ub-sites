<?php

namespace App\Controllers;

use App\Models\AlumniProfileModel;
use App\Models\LaboratoryModel;
use App\Models\PageModel;
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
        ]);
    }

    public function programmes(): string
    {
        $page    = $this->page('formations');
        $content = $this->withSharedBanner($this->pageContent($page, $this->programmesFallback()));
        $grouped = ['licence' => [], 'master' => [], 'doctorat' => []];

        $programmes = service('contentTranslationService')->records('programmes', model(ProgrammeModel::class, false)
            ->forSite()
            ->where('is_published', 1)
            ->orderBy('display_order', 'ASC')
            ->findAll());

        foreach ($programmes as $programme) {
            $level = (string) $programme->level;

            // Robustesse : ignorer silencieusement un niveau hors référentiel
            // plutôt que lever une erreur sur la page publique.
            if (! isset($grouped[$level])) {
                continue;
            }

            $grouped[$level][] = $programme;
        }

        return view('programmes/index', [
            'title'        => site_text_or_placeholder($page?->seo_title ?? null),
            'description'  => site_text_or_placeholder($page?->seo_description ?? null),
            'activePage'   => 'programmes',
            'pageTitle'    => lang('Site.pageTitles.programmes'),
            'siteSettings' => $this->siteSettings,
            'content'      => $content,
            'programmes'   => $grouped,
        ]);
    }

    public function programmeDetail(string $slug): string|ResponseInterface
    {
        $programme = model(ProgrammeModel::class, false)
            ->forSite()
            ->where('slug', $slug)
            ->where('is_published', 1)
            ->first();

        if ($programme === null) {
            return $this->notFound(lang('Site.programmes.notFound'));
        }

        $programme = service('contentTranslationService')->record('programmes', $programme);

        return view('programmes/show', [
            'title'        => site_text_or_placeholder($programme->title) . ' | ' . site_text_or_placeholder($this->siteSettings['institution.short_name'] ?? null),
            'description'  => $programme->summary,
            'activePage'   => 'programmes',
            'pageTitle'    => $programme->title,
            'siteSettings' => $this->siteSettings,
            'programme'    => $programme,
        ]);
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

        return view('staff/index', [
            'title'        => site_text_or_placeholder($page?->seo_title ?? null),
            'description'  => site_text_or_placeholder($page?->seo_description ?? null),
            'activePage'   => 'staff',
            'pageTitle'    => lang('Site.pageTitles.staff'),
            'siteSettings' => $this->siteSettings,
            'content'      => $content,
            'staff'        => service('contentTranslationService')->records('staff', model(StaffModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->orderBy('display_order', 'ASC')
                ->findAll()),
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

        return view('staff/show', [
            'title'        => site_text_or_placeholder($member->name) . ' | ' . site_text_or_placeholder($this->siteSettings['institution.short_name'] ?? null),
            'description'  => trim((string) ($member->role . ' ' . $member->specialty)),
            'activePage'   => 'staff',
            'pageTitle'    => $member->name,
            'siteSettings' => $this->siteSettings,
            'member'       => $member,
        ]);
    }

    public function posts(): string
    {
        $page    = $this->page('posts');
        $content = $this->withSharedBanner($this->pageContent($page, $this->postsFallback()));
        $type    = $this->postTypeFromRequest((string) $this->request->getGet('type'));
        $query   = trim((string) $this->request->getGet('q'));
        $now     = date('Y-m-d H:i:s');
        $translator = service('contentTranslationService');
        $upcoming = [];

        if ($type === null) {
            $upcomingModel = $this->visiblePosts()
                ->where('type', 'event')
                ->where('event_starts_at >=', $now);
            $this->applyPostSearch($upcomingModel, $query);
            $upcoming = $translator->records('posts', $upcomingModel
                ->orderBy('event_starts_at', 'ASC')
                ->findAll(12));
        }

        $model = $this->visiblePosts();
        if ($type === 'event') {
            $model->where('type', 'event')->orderBy('event_starts_at', 'ASC');
        } else {
            $model->where('type', $type ?? 'news')->orderBy('published_at', 'DESC')->orderBy('id', 'DESC');
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
            'upcomingPosts'  => $upcoming,
            'pager'          => $model->pager,
            'selectedType'   => $type,
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

        return view('posts/show', [
            'title'        => site_text_or_placeholder($post->seo_title ?? null, site_text_or_placeholder($post->title ?? null)) . ' | ' . site_text_or_placeholder($this->siteSettings['institution.short_name'] ?? null),
            'description'  => site_text_or_placeholder($post->seo_description ?? null, site_text_or_placeholder($post->excerpt ?? null)),
            'activePage'   => 'posts',
            'pageTitle'    => $post->title,
            'siteSettings' => $this->siteSettings,
            'post'         => $post,
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

    /**
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    private function withSharedBanner(array $content): array
    {
        $current = trim((string) ($content['banner_image'] ?? ''));
        if ($this->isUploadBanner($current)) {
            return $content;
        }

        $staff = $this->page('staff');
        $staffContent = $this->pageContent($staff, []);
        $shared = trim((string) ($staffContent['banner_image'] ?? ''));
        if ($this->isUploadBanner($shared)) {
            $content['banner_image'] = $shared;
        }

        return $content;
    }

    private function isUploadBanner(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        $lower = strtolower($path);

        return ! str_contains($lower, 'logo-placeholder') && str_starts_with($lower, 'uploads/');
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

    private function notFound(string $message): ResponseInterface
    {
        return $this->response
            ->setStatusCode(404)
            ->setBody(view('errors/html/error_404', ['message' => $message]));
    }

    private function page(string $key): ?object
    {
        $page = model(PageModel::class, false)
            ->forSite()
            ->where('key', $key)
            ->where('is_published', 1)
            ->first();

        return $page === null ? null : service('contentTranslationService')->record('pages', $page);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageContent(?object $page, array $fallback): array
    {
        if ($page === null) {
            return $fallback;
        }

        return service('contentTranslationService')->pageContent($page, $fallback);
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
