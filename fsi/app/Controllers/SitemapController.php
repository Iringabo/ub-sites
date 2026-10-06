<?php

namespace App\Controllers;

use App\Models\DepartmentModel;
use App\Models\FaqItemModel;
use App\Models\PostModel;
use App\Models\ProgrammeModel;
use App\Models\StaffModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * sitemap.xml and robots.txt for the active faculty site only, with fr/en
 * alternates on every URL.
 */
class SitemapController extends BaseController
{
    private const STATIC_PATHS = [
        ''                 => ['weekly', '1.0'],
        'faculte'          => ['monthly', '0.8'],
        'departements'     => ['monthly', '0.7'],
        'formations'       => ['monthly', '0.9'],
        'admissions'       => ['monthly', '0.9'],
        'recherche'        => ['monthly', '0.7'],
        'corps-enseignant' => ['monthly', '0.7'],
        'actualites'       => ['daily', '0.8'],
        'alumni'           => ['monthly', '0.5'],
        'contact'          => ['yearly', '0.6'],
        'mentions-legales' => ['yearly', '0.2'],
    ];

    public function index(): ResponseInterface
    {
        $entries = [];
        foreach (self::STATIC_PATHS as $path => [$frequency, $priority]) {
            $entries[] = ['path' => $path, 'lastmod' => null, 'changefreq' => $frequency, 'priority' => $priority];
        }

        if ($this->hasPublished(FaqItemModel::class)) {
            $entries[] = ['path' => 'faq', 'lastmod' => null, 'changefreq' => 'monthly', 'priority' => '0.6'];
        }

        foreach ($this->published(ProgrammeModel::class) as $programme) {
            $entries[] = ['path' => 'formations/' . $programme->slug, 'lastmod' => $programme->updated_at, 'changefreq' => 'monthly', 'priority' => '0.8'];
        }
        foreach ($this->published(DepartmentModel::class) as $department) {
            $entries[] = ['path' => 'departements/' . $department->slug, 'lastmod' => $department->updated_at, 'changefreq' => 'monthly', 'priority' => '0.6'];
        }
        foreach ($this->published(StaffModel::class) as $member) {
            $entries[] = ['path' => 'corps-enseignant/' . $member->slug, 'lastmod' => $member->updated_at, 'changefreq' => 'monthly', 'priority' => '0.4'];
        }
        foreach (model(PostModel::class, false)->visible()->select('slug, updated_at')->orderBy('published_at', 'DESC')->findAll(1000) as $post) {
            $entries[] = ['path' => 'actualites/' . $post->slug, 'lastmod' => $post->updated_at, 'changefreq' => 'monthly', 'priority' => '0.5'];
        }

        $locales = service('localeUrls');
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        foreach ($entries as $entry) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . $this->xml($locales->canonical($locales->defaultLocale(), $entry['path'])) . '</loc>';
            foreach ($locales->alternates($entry['path']) as $hreflang => $url) {
                $xml[] = '    <xhtml:link rel="alternate" hreflang="' . $this->xml($hreflang) . '" href="' . $this->xml($url) . '"/>';
            }
            $lastmod = $this->lastmod($entry['lastmod']);
            if ($lastmod !== null) {
                $xml[] = '    <lastmod>' . $lastmod . '</lastmod>';
            }
            $xml[] = '    <changefreq>' . $entry['changefreq'] . '</changefreq>';
            $xml[] = '    <priority>' . $entry['priority'] . '</priority>';
            $xml[] = '  </url>';
        }
        $xml[] = '</urlset>';

        return $this->response
            ->setContentType('application/xml', 'UTF-8')
            ->setBody(implode("\n", $xml) . "\n");
    }

    public function robots(): ResponseInterface
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /trouver',
            '',
            'Sitemap: ' . site_url('sitemap.xml'),
        ];

        return $this->response
            ->setContentType('text/plain', 'UTF-8')
            ->setBody(implode("\n", $lines) . "\n");
    }

    /**
     * @param class-string $modelClass
     *
     * @return list<object>
     */
    private function published(string $modelClass): array
    {
        return model($modelClass, false)
            ->forSite()
            ->select('slug, updated_at')
            ->where('is_published', 1)
            ->orderBy('display_order', 'ASC')
            ->findAll(2000);
    }

    /**
     * @param class-string $modelClass
     */
    private function hasPublished(string $modelClass): bool
    {
        return model($modelClass, false)->forSite()->where('is_published', 1)->countAllResults() > 0;
    }

    private function lastmod(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = $value instanceof \DateTimeInterface ? $value->getTimestamp() : strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
