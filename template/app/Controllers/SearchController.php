<?php

namespace App\Controllers;

use App\Models\DepartmentModel;
use App\Models\FaqItemModel;
use App\Models\LaboratoryModel;
use App\Models\PartnerModel;
use App\Models\PostModel;
use App\Models\ProgrammeModel;
use App\Models\PublicationModel;
use App\Models\ResearchProjectModel;
use App\Models\StaffModel;
use CodeIgniter\Model;

class SearchController extends BaseController
{
    private const PER_GROUP = 8;

    public function index(): string
    {
        $query = mb_substr(trim((string) $this->request->getGet('q')), 0, 80);
        $groups = [];

        if ($query !== '') {
            foreach ($this->groups() as $key => $group) {
                [$items, $total] = $this->search($group, $query);
                if ($items === []) {
                    continue;
                }

                $groups[$key] = [
                    'label' => $group['label'],
                    'icon'  => $group['icon'],
                    'total' => $total,
                    'more'  => isset($group['more']) ? $group['more']($query) : null,
                    'items' => array_map(static fn (object $item): array => [
                        'title'   => (string) $item->{$group['title']},
                        'url'     => $group['url']($item),
                        'snippet' => $group['snippet']($item),
                    ], $items),
                ];
            }
        }

        return view('search/index', [
            'title'        => lang('Site.common.search'),
            'description'  => lang('Site.common.searchPrompt'),
            'activePage'   => '',
            'siteSettings' => $this->siteSettings,
            'query'        => $query,
            'groups'       => $groups,
            'total'        => array_sum(array_column($groups, 'total')),
            'noindex'      => true,
        ]);
    }

    /**
     * @param array<string, mixed> $group
     *
     * @return array{0: list<object>, 1: int}
     */
    private function search(array $group, string $query): array
    {
        $translator = service('contentTranslationService');
        $translatedIds = $translator->matchingResourceIds($group['translation'], $query, $group['fields']);

        $build = function () use ($group, $query, $translatedIds): Model {
            /** @var Model $model */
            $model = $group['model']();
            $model->groupStart();
            foreach ($group['fields'] as $index => $field) {
                $index === 0 ? $model->like($field, $query) : $model->orLike($field, $query);
            }
            if ($translatedIds !== []) {
                $model->orWhereIn('id', $translatedIds);
            }
            $model->groupEnd();

            return $model;
        };

        $total = $build()->countAllResults();
        if ($total === 0) {
            return [[], 0];
        }

        $model = $build();
        foreach ($group['order'] as $column => $direction) {
            $model->orderBy($column, $direction);
        }

        return [$translator->records($group['translation'], $model->findAll(self::PER_GROUP)), $total];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function groups(): array
    {
        $published = static fn (string $class): \Closure => static fn (): Model => model($class, false)->forSite()->where('is_published', 1);
        $excerpt = static fn (?string $text): string => mb_strimwidth(trim(preg_replace('/\s+/', ' ', site_plain_paragraphs($text)) ?? ''), 0, 180, '…');

        return [
            'programmes' => [
                'label'       => lang('Site.nav.programmes'),
                'icon'        => 'bi-mortarboard',
                'model'       => $published(ProgrammeModel::class),
                'translation' => 'programmes',
                'fields'      => ['title', 'summary', 'description', 'admission_conditions'],
                'order'       => ['display_order' => 'ASC'],
                'title'       => 'title',
                'url'         => static fn (object $item): string => site_url('formations/' . $item->slug),
                'snippet'     => static fn (object $item): string => trim(site_level_label($item->level) . ' · ' . $excerpt($item->summary), ' ·'),
            ],
            'departments' => [
                'label'       => lang('Site.nav.departments'),
                'icon'        => 'bi-diagram-2',
                'model'       => $published(DepartmentModel::class),
                'translation' => 'departments',
                'fields'      => ['name', 'abbreviation', 'description'],
                'order'       => ['display_order' => 'ASC'],
                'title'       => 'name',
                'url'         => static fn (object $item): string => site_url('departements/' . $item->slug),
                'snippet'     => static fn (object $item): string => $excerpt($item->description),
            ],
            'staff' => [
                'label'       => lang('Site.nav.staff'),
                'icon'        => 'bi-person-badge',
                'model'       => $published(StaffModel::class),
                'translation' => 'staff',
                'fields'      => ['name', 'grade', 'specialty', 'role', 'biography'],
                'order'       => ['display_order' => 'ASC'],
                'title'       => 'name',
                'url'         => static fn (object $item): string => site_url('corps-enseignant/' . $item->slug),
                'snippet'     => static fn (object $item): string => trim(implode(' · ', array_filter([(string) $item->grade, (string) $item->specialty]))),
                'more'        => static fn (string $query): string => site_url('corps-enseignant?q=' . rawurlencode($query)),
            ],
            'posts' => [
                'label'       => lang('Site.nav.posts'),
                'icon'        => 'bi-newspaper',
                'model'       => static fn (): Model => model(PostModel::class, false)->visible(),
                'translation' => 'posts',
                'fields'      => ['title', 'excerpt', 'body', 'event_location'],
                'order'       => ['published_at' => 'DESC'],
                'title'       => 'title',
                'url'         => static fn (object $item): string => site_url('actualites/' . $item->slug),
                'snippet'     => static fn (object $item): string => trim(site_post_type_label($item->type) . ' · ' . site_format_date($item->type === 'event' && $item->event_starts_at ? $item->event_starts_at : $item->published_at), ' ·'),
                'more'        => static fn (string $query): string => site_url('actualites?q=' . rawurlencode($query)),
            ],
            'laboratories' => [
                'label'       => lang('Site.search.laboratories'),
                'icon'        => 'bi-diagram-3',
                'model'       => $published(LaboratoryModel::class),
                'translation' => 'laboratories',
                'fields'      => ['name', 'abbreviation', 'description'],
                'order'       => ['display_order' => 'ASC'],
                'title'       => 'name',
                'url'         => static fn (object $item): string => site_url('recherche') . '#laboratoires',
                'snippet'     => static fn (object $item): string => $excerpt($item->description),
            ],
            'publications' => [
                'label'       => lang('Site.search.publications'),
                'icon'        => 'bi-journal-text',
                'model'       => $published(PublicationModel::class),
                'translation' => 'publications',
                'fields'      => ['title', 'authors', 'journal'],
                'order'       => ['year' => 'DESC'],
                'title'       => 'title',
                'url'         => static fn (object $item): string => site_url('recherche') . '#publications',
                'snippet'     => static fn (object $item): string => trim(implode(' · ', array_filter([(string) $item->authors, (string) $item->journal, (string) $item->year]))),
            ],
            'projects' => [
                'label'       => lang('Site.search.projects'),
                'icon'        => 'bi-briefcase',
                'model'       => $published(ResearchProjectModel::class),
                'translation' => 'research_projects',
                'fields'      => ['title', 'code', 'description', 'funder'],
                'order'       => ['display_order' => 'ASC'],
                'title'       => 'title',
                'url'         => static fn (object $item): string => site_url('recherche') . '#projets',
                'snippet'     => static fn (object $item): string => $excerpt($item->description),
            ],
            'faq' => [
                'label'       => lang('Site.nav.faq'),
                'icon'        => 'bi-question-circle',
                'model'       => $published(FaqItemModel::class),
                'translation' => 'faq_items',
                'fields'      => ['question', 'answer'],
                'order'       => ['display_order' => 'ASC'],
                'title'       => 'question',
                'url'         => static fn (object $item): string => site_url('faq') . '#faq-' . $item->category,
                'snippet'     => static fn (object $item): string => $excerpt($item->answer),
            ],
            'partners' => [
                'label'       => lang('Site.partners.searchGroup'),
                'icon'        => 'bi-globe2',
                'model'       => $published(PartnerModel::class),
                'translation' => 'partners',
                'fields'      => ['name', 'country', 'description'],
                'order'       => ['display_order' => 'ASC'],
                'title'       => 'name',
                'url'         => static fn (object $item): string => site_url('faculte') . '#partenaires',
                'snippet'     => static fn (object $item): string => trim(lang('Site.partners.type.' . $item->type) . ' · ' . (string) $item->country, ' ·'),
            ],
        ];
    }
}
