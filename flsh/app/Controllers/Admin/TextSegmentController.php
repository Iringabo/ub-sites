<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Entities\Page;
use App\Models\HomeContentModel;
use App\Models\PageModel;
use App\Services\PageTextCatalog;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * Edits one small text category of a public page (see PageTextCatalog).
 * Only the category's own fields are written; everything else is preserved.
 */
class TextSegmentController extends BaseController
{
    private const LOCALE = 'en';

    private PageTextCatalog $catalog;

    public function __construct()
    {
        $this->catalog = service('pageTextCatalog');
    }

    public function page(string $pageSlug): RedirectResponse
    {
        $segments = $this->catalog->editableSegments($pageSlug);
        if ($segments === []) {
            return redirect()->to('/admin')->with('error', 'Page introuvable.');
        }

        return redirect()->to('/admin/textes/' . $pageSlug . '/' . array_key_first($segments));
    }

    public function edit(string $pageSlug, string $segmentId): string|RedirectResponse
    {
        $resolved = $this->resolve($pageSlug, $segmentId);
        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        [$pageMeta, $segment] = $resolved;
        [$source, $english] = $this->loadValues($pageMeta);

        $fields = [];
        foreach ($segment['fields'] as $key => $field) {
            $fr = $this->fieldValue($source, $key, $field);
            $en = $this->fieldValue($english, $key, $field);
            $fields[$key] = $field + [
                'input'        => $this->inputName($key),
                'value'        => $fr,
                'translatable' => $this->catalog->isTranslatable($field),
                'en_value'     => $en !== $fr ? $en : '',
            ];
        }

        $siblings = array_keys($this->catalog->editableSegments($pageSlug));
        $position = array_search($segmentId, $siblings, true);
        $previous = $position !== false && $position > 0 ? $siblings[$position - 1] : null;
        $next = $position !== false && isset($siblings[$position + 1]) ? $siblings[$position + 1] : null;

        $publicUrl = site_url(ltrim((string) $pageMeta['public_path'], '/'));
        if (! empty($segment['anchor'])) {
            $publicUrl .= '#' . $segment['anchor'];
        }

        return view('admin/textes/segment', [
            'title'        => $segment['label'] . ' · ' . $pageMeta['label'] . ' | Administration',
            'activeAdmin'  => $this->catalog->activeKey($pageSlug, $segmentId),
            'pageSlug'     => $pageSlug,
            'pageMeta'     => $pageMeta,
            'segmentId'    => $segmentId,
            'segment'      => $segment,
            'fields'       => $fields,
            'hasEnglish'   => array_filter($fields, static fn (array $field): bool => $field['translatable']) !== [],
            'action'       => site_url('admin/textes/' . $pageSlug . '/' . $segmentId),
            'publicUrl'    => $publicUrl,
            'previous'     => $previous === null ? null : ['url' => site_url('admin/textes/' . $pageSlug . '/' . $previous), 'label' => $this->catalog->segment($pageSlug, $previous)['label'] ?? ''],
            'next'         => $next === null ? null : ['url' => site_url('admin/textes/' . $pageSlug . '/' . $next), 'label' => $this->catalog->segment($pageSlug, $next)['label'] ?? ''],
            'iconChoices'  => $this->iconChoices(),
        ]);
    }

    public function update(string $pageSlug, string $segmentId): RedirectResponse
    {
        $resolved = $this->resolve($pageSlug, $segmentId);
        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        [$pageMeta, $segment] = $resolved;
        [$source, $english] = $this->loadValues($pageMeta);

        $errors = [];
        $french = [];
        $translations = [];
        $storedImages = [];
        $replacedImages = [];

        foreach ($segment['fields'] as $key => $field) {
            $input = $this->inputName($key);
            $type = (string) ($field['type'] ?? 'text');

            if ($type === 'image') {
                $current = (string) $this->fieldValue($source, $key, $field);
                $value = $this->request->getPost('remove_' . $input) === '1' ? '' : $current;
                $file = $this->request->getFile('file_' . $input);

                if (service('mediaService')->hasFile($file)) {
                    $error = null;
                    $path = service('mediaService')->storePublicImage($file, (string) ($field['folder'] ?? 'pages'), $error);
                    if ($path === null) {
                        $errors['file_' . $input] = $error ?? ($field['label'] . ' : image invalide.');
                    } else {
                        $value = $path;
                        $storedImages[] = $path;
                    }
                }

                if ($value !== $current && $current !== '') {
                    $replacedImages[] = $current;
                }
                $french[$key] = $value;

                continue;
            }

            $raw = trim(str_replace(["\r\n", "\r"], "\n", (string) $this->request->getPost($input)));
            if ($type === 'icon' && $raw === '') {
                $raw = (string) ($this->fieldValue($source, $key, $field) ?: 'bi-star');
            }

            $error = $this->validateValue($field, $raw, true);
            if ($error !== null) {
                $errors[$input] = $error;
            }
            $french[$key] = $raw;

            if ($this->catalog->isTranslatable($field)) {
                $enRaw = trim(str_replace(["\r\n", "\r"], "\n", (string) $this->request->getPost('en_' . $input)));
                $enError = $this->validateValue($field, $enRaw, false);
                if ($enError !== null) {
                    $errors['en_' . $input] = $enError;
                }
                $translations[$key] = $enRaw;
            }
        }

        if ($errors !== []) {
            foreach ($storedImages as $path) {
                service('mediaService')->deletePublicPath($path);
            }

            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $saved = $pageMeta['storage'] === 'home'
            ? $this->saveHome($segment, $french, $translations)
            : $this->savePage($pageMeta, $segment, $source, $english, $french, $translations);

        if (! $saved) {
            foreach ($storedImages as $path) {
                service('mediaService')->deletePublicPath($path);
            }

            return redirect()->back()->withInput()->with('errors', ['form' => 'Enregistrement impossible. Réessayez.']);
        }

        foreach ($replacedImages as $path) {
            service('mediaService')->deletePublicPath($path);
        }

        service('contentTranslationService')->reset();

        return redirect()
            ->to('/admin/textes/' . $pageSlug . '/' . $segmentId)
            ->with('message', '« ' . $segment['label'] . ' » a été enregistré.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|RedirectResponse
     */
    private function resolve(string $pageSlug, string $segmentId): array|RedirectResponse
    {
        $pageMeta = $this->catalog->page($pageSlug);
        $segment = $this->catalog->segment($pageSlug, $segmentId);

        if ($pageMeta === null || $segment === null) {
            return redirect()->to('/admin')->with('error', 'Section introuvable.');
        }

        if (isset($segment['link'])) {
            return redirect()->to('/admin/' . $segment['link']);
        }

        if (! (auth()->user()?->can((string) $pageMeta['permission']) ?? false)) {
            return redirect()->to('/admin')->with('error', 'Vous n’avez pas accès à cette section.');
        }

        return [$pageMeta, $segment];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function loadValues(array $pageMeta): array
    {
        if ($pageMeta['storage'] === 'home') {
            $row = $this->homeRow();

            return [$row, service('contentTranslationService')->values('home_content', (int) ($row['id'] ?? 0), self::LOCALE)];
        }

        $page = $this->ensurePage((string) $pageMeta['page_key'], (string) $pageMeta['label'], (string) $pageMeta['public_path']);
        $source = site_decode_page_content((string) $page->content);
        $english = $this->pageEnglish($page);

        return [$source + ['__page_id' => (int) $page->id], $english];
    }

    private function fieldValue(array $data, string $key, array $field): string
    {
        $value = $this->getPath($data, $key);
        if (($field['type'] ?? '') === 'paragraphs') {
            return implode("\n\n", array_map(static fn (mixed $item): string => (string) $item, (array) ($value ?? [])));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function validateValue(array $field, string $value, bool $isSource): ?string
    {
        $label = (string) $field['label'];
        $type = (string) ($field['type'] ?? 'text');

        if ($isSource && ! empty($field['required']) && $value === '') {
            return $label . ' est obligatoire.';
        }

        if ($value === '') {
            return null;
        }

        $max = (int) ($field['max'] ?? match ($type) {
            'textarea' => 2000,
            'paragraphs' => 10000,
            'url', 'embed' => 2000,
            'icon' => 60,
            default => 255,
        });

        if (mb_strlen($value) > $max) {
            return $label . ' ne peut pas dépasser ' . $max . ' caractères.';
        }

        if ($type === 'icon' && preg_match('/^bi-[a-z0-9-]+$/', $value) !== 1) {
            return $label . ' doit être un nom d’icône comme « bi-star ».';
        }

        if ($type === 'url' && ! str_starts_with($value, '/') && ! str_starts_with($value, '#') && filter_var($value, FILTER_VALIDATE_URL) === false) {
            return $label . ' doit être une adresse commençant par « / » (page du site) ou « https:// ».';
        }

        if ($type === 'embed' && (! str_starts_with($value, 'https://') || filter_var($value, FILTER_VALIDATE_URL) === false)) {
            return $label . ' doit être une adresse commençant par « https:// ».';
        }

        return null;
    }

    /**
     * @param array<string, string> $french
     * @param array<string, string> $translations
     */
    private function savePage(array $pageMeta, array $segment, array $source, array $english, array $french, array $translations): bool
    {
        $pageId = (int) ($source['__page_id'] ?? 0);
        unset($source['__page_id']);

        $content = $source;
        foreach ($segment['fields'] as $key => $field) {
            $this->setPath($content, $key, $this->storedValue($field, $french[$key] ?? ''));
        }

        $kept = null;
        if (isset($segment['compact'])) {
            $kept = $this->compactIndexes($content, $segment['compact']);
            $this->applyCompact($content, (string) $segment['compact']['path'], $kept);
        }

        $translated = $english === [] ? $source : $this->fillMissing($english, $source);
        foreach ($segment['fields'] as $key => $field) {
            $value = $this->catalog->isTranslatable($field) && ($translations[$key] ?? '') !== ''
                ? $translations[$key]
                : ($french[$key] ?? '');
            $this->setPath($translated, $key, $this->storedValue($field, $value));
        }
        if ($kept !== null) {
            $this->applyCompact($translated, (string) $segment['compact']['path'], $kept);
        }

        $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $enJson = json_encode($translated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || $enJson === false || $pageId <= 0) {
            return false;
        }

        $pages = model(PageModel::class, false);
        $pages->skipValidation(true);
        $saved = $pages->forSite()->update($pageId, ['content' => $json]);
        $pages->skipValidation(false);

        if ($saved === false) {
            return false;
        }

        service('contentTranslationService')->save('pages', $pageId, self::LOCALE, ['content' => $enJson]);

        return true;
    }

    /**
     * @param array<string, string> $french
     * @param array<string, string> $translations
     */
    private function saveHome(array $segment, array $french, array $translations): bool
    {
        $row = $this->homeRow();
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }

        $model = model(HomeContentModel::class, false);
        $model->skipValidation(true);
        $saved = $model->forSite()->update($id, $french);
        $model->skipValidation(false);

        if ($saved === false) {
            return false;
        }

        $english = [];
        foreach ($segment['fields'] as $key => $field) {
            if ($this->catalog->isTranslatable($field)) {
                $english[$key] = $translations[$key] ?? '';
            }
        }
        if ($english !== []) {
            service('contentTranslationService')->save('home_content', $id, self::LOCALE, $english);
        }

        return true;
    }

    private function storedValue(array $field, string $value): mixed
    {
        if (($field['type'] ?? '') !== 'paragraphs') {
            return $value;
        }

        return array_values(array_filter(
            array_map(
                static fn (string $paragraph): string => preg_replace('/[ \t]*\n[ \t]*/', ' ', trim($paragraph)) ?? trim($paragraph),
                preg_split('/\n{2,}/', $value) ?: [],
            ),
            static fn (string $paragraph): bool => $paragraph !== '',
        ));
    }

    /**
     * @param array{path: string, keys: list<string>} $compact
     *
     * @return list<int>
     */
    private function compactIndexes(array $content, array $compact): array
    {
        $kept = [];
        foreach ((array) ($this->getPath($content, $compact['path']) ?? []) as $index => $item) {
            foreach ($compact['keys'] as $key) {
                if (trim((string) (is_array($item) ? ($item[$key] ?? '') : '')) !== '') {
                    $kept[] = (int) $index;
                    break;
                }
            }
        }

        return $kept;
    }

    /**
     * @param list<int> $kept
     */
    private function applyCompact(array &$content, string $path, array $kept): void
    {
        $items = (array) ($this->getPath($content, $path) ?? []);
        $filtered = [];
        foreach ($kept as $index) {
            if (isset($items[$index])) {
                $filtered[] = $items[$index];
            }
        }
        $this->setPath($content, $path, $filtered);
    }

    /**
     * Adds keys the translation lacks without merging lists item by item.
     */
    private function fillMissing(array $translated, array $source): array
    {
        foreach ($source as $key => $value) {
            if (! array_key_exists($key, $translated)) {
                $translated[$key] = $value;
            } elseif (is_array($value) && is_array($translated[$key]) && ! array_is_list($value)) {
                $translated[$key] = $this->fillMissing($translated[$key], $value);
            }
        }

        return $translated;
    }

    private function getPath(array $data, string $path): mixed
    {
        $current = $data;
        foreach (explode('.', $path) as $part) {
            if (! is_array($current) || ! array_key_exists(ctype_digit($part) ? (int) $part : $part, $current)) {
                return null;
            }
            $current = $current[ctype_digit($part) ? (int) $part : $part];
        }

        return $current;
    }

    private function setPath(array &$data, string $path, mixed $value): void
    {
        $parts = explode('.', $path);
        $current = &$data;
        foreach ($parts as $i => $part) {
            $key = ctype_digit($part) ? (int) $part : $part;
            if ($i === count($parts) - 1) {
                $current[$key] = $value;

                return;
            }
            if (! isset($current[$key]) || ! is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
    }

    private function inputName(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /**
     * @return array<string, mixed>
     */
    private function homeRow(): array
    {
        $row = model(HomeContentModel::class, false)->forSite()->asArray()->first();

        return is_array($row) ? $row : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageEnglish(Page $page): array
    {
        $values = service('contentTranslationService')->values('pages', (int) $page->id, self::LOCALE);

        return site_decode_page_content((string) ($values['content'] ?? ''));
    }

    private function ensurePage(string $key, string $title, string $slug): Page
    {
        /** @var Page|null $page */
        $page = model(PageModel::class, false)->forSite()->where('key', $key)->first();
        if ($page instanceof Page) {
            return $page;
        }

        $pages = model(PageModel::class, false);
        $id = $pages->insert([
            'key'             => $key,
            'title'           => $title,
            'slug'            => trim($slug, '/') ?: $key,
            'content'         => '{}',
            'seo_title'       => $title,
            'seo_description' => '',
            'is_published'    => 1,
        ], true);

        if ($id === false) {
            throw new RuntimeException('La page « ' . $key . ' » est introuvable et n’a pas pu être créée.');
        }

        /** @var Page $created */
        $created = model(PageModel::class, false)->find((int) $id);

        return $created;
    }

    /**
     * @return list<string>
     */
    private function iconChoices(): array
    {
        return [
            'bi-star', 'bi-bullseye', 'bi-eye', 'bi-award', 'bi-lightbulb', 'bi-people', 'bi-heart',
            'bi-shield-check', 'bi-globe', 'bi-book', 'bi-mortarboard', 'bi-graph-up', 'bi-gem',
            'bi-hand-thumbs-up', 'bi-compass', 'bi-flag', 'bi-trophy', 'bi-briefcase', 'bi-tree', 'bi-rocket',
        ];
    }
}
