<?php

namespace App\Services;

use App\Entities\Programme;
use App\Models\ContentBlockModel;
use App\Models\HomeContentModel;
use App\Models\HomeHeroSlideModel;
use App\Models\HomeHighlightModel;
use App\Models\LaboratoryModel;
use App\Models\PageModel;
use App\Models\PostModel;
use App\Models\ProgrammeModel;
use App\Models\SiteStatModel;
use App\Models\StaffModel;

class HomePageService
{
    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $homeContent = model(HomeContentModel::class, false)
            ->forSite()
            ->where('singleton_key', 1)
            ->first();

        $featuredProgrammes = model(ProgrammeModel::class, false)
            ->forSite()
            ->where('is_published', 1)
            ->where('featured_on_home', 1)
            ->orderBy('home_order', 'ASC')
            ->orderBy('display_order', 'ASC')
            ->findAll();

        $translator = service('contentTranslationService');
        $featuredProgrammes = $translator->records('programmes', $featuredProgrammes);

        return [
            'homeContent'          => $translator->record('home_content', $homeContent),
            'heroSlides'           => $this->heroSlides(),
            'highlights'           => $this->publishedHighlights(),
            'mainStats'            => $this->stats('home_main'),
            'researchStats'        => $this->stats('home_research'),
            'programmeGroups'      => $this->programmeGroups($featuredProgrammes),
            'featuredLaboratories' => $this->featuredLaboratories(),
            'featuredStaff'        => $this->featuredStaff(),
            'featuredPosts'        => $this->featuredPosts(),
            'deanMessage'          => $this->deanMessage(),
            'contentBlocks'        => $this->contentBlocks('home'),
        ];
    }

    /**
     * @return list<object>
     */
    private function heroSlides(): array
    {
        return service('contentTranslationService')->records('home_hero_slides', model(HomeHeroSlideModel::class, false)
            ->forSite()
            ->where('is_published', 1)
            ->orderBy('display_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll());
    }

    /**
     * @return list<object>
     */
    private function publishedHighlights(): array
    {
        return service('contentTranslationService')->records('home_highlights', model(HomeHighlightModel::class, false)
            ->forSite()
            ->where('is_published', 1)
            ->orderBy('display_order', 'ASC')
            ->findAll());
    }

    /**
     * @return list<object>
     */
    private function stats(string $section): array
    {
        return service('contentTranslationService')->records('site_stats', model(SiteStatModel::class, false)
            ->forSite()
            ->where('section', $section)
            ->where('is_published', 1)
            ->orderBy('display_order', 'ASC')
            ->findAll());
    }

    /**
     * @param list<Programme> $programmes
     *
     * @return list<array<string, mixed>>
     */
    private function programmeGroups(array $programmes): array
    {
        $groups = [
            'licence' => [
                'level'       => 'licence',
                'label'       => site_level_label('licence'),
                'icon'        => 'bi-mortarboard',
                'orientation' => lang('Home.programmeOrientation.licence'),
                'programmes'  => [],
            ],
            'master' => [
                'level'       => 'master',
                'label'       => site_level_label('master'),
                'icon'        => 'bi-award',
                'orientation' => lang('Home.programmeOrientation.master'),
                'programmes'  => [],
            ],
            'doctorat' => [
                'level'       => 'doctorat',
                'label'       => site_level_label('doctorat'),
                'icon'        => 'bi-journals',
                'orientation' => lang('Home.programmeOrientation.doctorat'),
                'programmes'  => [],
            ],
        ];

        foreach ($programmes as $programme) {
            $level = (string) $programme->level;

            if (! isset($groups[$level])) {
                continue;
            }

            $groups[$level]['programmes'][] = $programme;
            $groups[$level]['duration'] ??= $programme->duration;
        }

        return array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['programmes'] !== [],
        ));
    }

    /**
     * @return list<object>
     */
    private function featuredLaboratories(): array
    {
        return service('contentTranslationService')->records('laboratories', model(LaboratoryModel::class, false)
            ->forSite()
            ->where('is_published', 1)
            ->where('featured_on_home', 1)
            ->orderBy('home_order', 'ASC')
            ->orderBy('display_order', 'ASC')
            ->findAll());
    }

    /**
     * @return list<object>
     */
    private function featuredPosts(): array
    {
        $now = date('Y-m-d H:i:s');

        return service('contentTranslationService')->records('posts', $this->visiblePosts()
            ->where('featured', 1)
            ->groupStart()
                ->where('type', 'news')
                ->orGroupStart()
                    ->where('type', 'event')
                    ->where('event_starts_at >=', $now)
                ->groupEnd()
            ->groupEnd()
            ->orderBy('home_order', 'ASC')
            ->orderBy('published_at', 'DESC')
            ->findAll(3));
    }

    /**
     * @return list<object>
     */
    private function featuredStaff(): array
    {
        return service('contentTranslationService')->records('staff', model(StaffModel::class, false)
            ->forSite()
            ->where('is_published', 1)
            ->orderBy('display_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll(4));
    }

    /**
     * @return array<string, mixed>
     */
    private function deanMessage(): array
    {
        $page = model(PageModel::class, false)
            ->forSite()
            ->where('key', 'faculty')
            ->where('is_published', 1)
            ->first();

        if ($page === null) {
            return [];
        }

        $content = service('contentTranslationService')->pageContent($page, []);
        $dean = $content['dean'] ?? [];

        return is_array($dean) ? $dean : [];
    }

    private function visiblePosts(): PostModel
    {
        return model(PostModel::class, false)->visible();
    }

    /**
     * @return list<object>
     */
    private function contentBlocks(string $pageKey): array
    {
        if (! db_connect()->tableExists('content_blocks')) {
            return [];
        }

        return service('contentTranslationService')->records('content_blocks', model(ContentBlockModel::class, false)
            ->forSite()
            ->where('page_key', $pageKey)
            ->where('is_published', 1)
            ->orderBy('display_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll());
    }

    /**
     * Resolve a hero CTA target key to a public URL (or null if none).
     */
    public function resolveCtaUrl(string $target, ?string $customUrl = null): ?string
    {
        $target = strtolower(trim($target));
        if ($target === '' || $target === 'none') {
            return null;
        }

        if ($target === 'custom') {
            $custom = trim((string) $customUrl);
            return $custom !== '' ? site_public_url($custom) : null;
        }

        return match ($target) {
            'programmes'      => site_url('formations'),
            'contact'         => site_url('contact'),
            'news'            => site_url('actualites'),
            'about'           => site_url('/#presentation'),
            'faculty_profile' => site_url('faculte'),
            default           => null,
        };
    }
}
