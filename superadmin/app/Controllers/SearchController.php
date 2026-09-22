<?php

namespace App\Controllers;

use App\Models\PostModel;
use App\Models\ProgrammeModel;
use App\Models\StaffModel;

class SearchController extends BaseController
{
    public function index(): string
    {
        $query = trim((string) $this->request->getGet('q'));
        $query = mb_substr($query, 0, 80);
        $programmes = [];
        $staff = [];
        $posts = [];

        if ($query !== '') {
            $translator = service('contentTranslationService');
            $programmeIds = $translator->matchingResourceIds('programmes', $query, ['title', 'summary', 'description']);
            $staffIds = $translator->matchingResourceIds('staff', $query, ['name', 'specialty', 'role', 'biography']);
            $postIds = $translator->matchingResourceIds('posts', $query, ['title', 'excerpt', 'body']);

            $programmeQuery = model(ProgrammeModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->groupStart()
                    ->like('title', $query);
            if ($programmeIds !== []) {
                $programmeQuery->orWhereIn('id', $programmeIds);
            }
            $programmes = $translator->records('programmes', $programmeQuery
                ->groupEnd()
                ->orderBy('display_order', 'ASC')
                ->findAll(8));

            $staffQuery = model(StaffModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->groupStart()
                    ->like('name', $query);
            if ($staffIds !== []) {
                $staffQuery->orWhereIn('id', $staffIds);
            }
            $staff = $translator->records('staff', $staffQuery
                ->groupEnd()
                ->orderBy('display_order', 'ASC')
                ->findAll(8));

            $postQuery = model(PostModel::class, false)
                ->visible()
                ->groupStart()
                    ->like('title', $query)
                    ->orLike('excerpt', $query);
            if ($postIds !== []) {
                $postQuery->orWhereIn('id', $postIds);
            }
            $posts = $translator->records('posts', $postQuery
                ->groupEnd()
                ->orderBy('published_at', 'DESC')
                ->findAll(8));
        }

        return view('search/index', [
            'title'        => lang('Site.common.search'),
            'description'  => lang('Site.common.searchPrompt'),
            'activePage'   => '',
            'siteSettings' => $this->siteSettings,
            'query'        => $query,
            'programmes'   => $programmes,
            'staff'        => $staff,
            'posts'        => $posts,
        ]);
    }
}
