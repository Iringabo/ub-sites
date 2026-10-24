<?php

namespace App\Controllers\Concerns;

use App\Models\PageModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Shared helpers for public controllers that read an editable page row
 * (`pages.content` JSON, translated) and fall back to neutral texts.
 */
trait LoadsPublicPages
{
    protected function page(string $key): ?object
    {
        $page = model(PageModel::class, false)
            ->forSite()
            ->where('key', $key)
            ->where('is_published', 1)
            ->first();

        return $page === null ? null : service('contentTranslationService')->record('pages', $page);
    }

    /**
     * @param array<string, mixed> $fallback
     *
     * @return array<string, mixed>
     */
    protected function pageContent(?object $page, array $fallback): array
    {
        if ($page === null) {
            return $fallback;
        }

        return service('contentTranslationService')->pageContent($page, $fallback);
    }

    /**
     * Pages without their own banner image reuse the staff page banner.
     *
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    protected function withSharedBanner(array $content): array
    {
        if ($this->hasBannerImage(trim((string) ($content['banner_image'] ?? '')))) {
            return $content;
        }

        $staffContent = $this->pageContent($this->page('staff'), []);
        $shared = trim((string) ($staffContent['banner_image'] ?? ''));
        if ($this->hasBannerImage($shared)) {
            $content['banner_image'] = $shared;
        }

        return $content;
    }

    protected function hasBannerImage(string $path): bool
    {
        return $path !== '' && ! str_contains(strtolower($path), 'logo-placeholder');
    }

    protected function notFound(string $message): ResponseInterface
    {
        return $this->response
            ->setStatusCode(404)
            ->setBody(view('errors/html/error_404', ['message' => $message]));
    }
}
