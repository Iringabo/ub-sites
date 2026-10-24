<?php

namespace App\Services;

use App\Entities\Post;
use App\Models\PostModel;

class PostVisibilityService
{
    /**
     * @return array<string, string>
     */
    public function statuses(): array
    {
        return [
            'draft'     => 'Brouillon',
            'scheduled' => 'Programmé',
            'published' => 'Publié',
            'archived'  => 'Archivé',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function types(): array
    {
        return [
            'news'  => 'Actualité',
            'event' => 'Événement',
        ];
    }

    public function applyVisible(PostModel $model): PostModel
    {
        $now = date('Y-m-d H:i:s');

        return $model
            ->groupStart()
                ->where('status', 'published')
                ->orGroupStart()
                    ->where('status', 'scheduled')
                    ->where('published_at <=', $now)
                ->groupEnd()
            ->groupEnd()
            ->where('published_at <=', $now);
    }

    public function isVisible(Post $post): bool
    {
        if (! in_array($post->status, ['published', 'scheduled'], true)) {
            return false;
        }

        if ($post->published_at === null || $post->published_at === '') {
            return false;
        }

        return strtotime((string) $post->published_at) <= time();
    }
}
