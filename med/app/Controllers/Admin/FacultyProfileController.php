<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Entities\Page;
use App\Models\PageModel;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

class FacultyProfileController extends BaseController
{
    private const PAGE_KEY = 'faculty';
    private const TRANSLATION_LOCALE = 'en';
    private const IMAGE_FOLDER = 'faculty-deans';

    public function edit(): string
    {
        $page = $this->facultyPage();

        return view('admin/faculty/profile', [
            'title'       => 'Présentation / Mot du doyen | Administration',
            'activeAdmin' => 'faculty/profile',
            'page'        => $page,
            'content'     => site_decode_page_content((string) $page->content),
            'translations' => $this->translatedContent($page),
            'action'      => site_url('admin/faculty/profile'),
            'activeSite'  => $this->activeSite,
        ]);
    }

    public function update(): RedirectResponse
    {
        $page = $this->facultyPage();
        $content = site_decode_page_content((string) $page->content);
        $currentDean = $this->deanContent($content);
        $input = $this->deanFromRequest();
        $english = $this->englishDeanFromRequest();
        $errors = array_merge(
            $this->validateDean($input, ''),
            $this->validateDean($english, 'translation_en_', false),
        );

        $storedImage = null;
        $oldImage = null;
        $file = $this->request->getFile('dean_photo');

        if ($this->request->getPost('remove_dean_photo') === '1') {
            $input['photo'] = '';
            $oldImage = $currentDean['photo'] ?? null;
        } else {
            $input['photo'] = (string) ($currentDean['photo'] ?? '');
        }

        if (service('mediaService')->hasFile($file)) {
            $error = null;
            $path = service('mediaService')->storePublicImage($file, self::IMAGE_FOLDER, $error);

            if ($path === null) {
                $errors['dean_photo'] = $error ?? 'La photo du doyen est invalide.';
            } else {
                $input['photo'] = $path;
                $storedImage = $path;
                $oldImage = $currentDean['photo'] ?? null;
            }
        }

        if ($errors !== []) {
            if ($storedImage !== null) {
                service('mediaService')->deletePublicPath($storedImage);
            }

            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $content['dean'] = $this->normalizedDean($input);
        $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return redirect()->back()->withInput()->with('errors', ['form' => 'Le contenu n’a pas pu être sérialisé.']);
        }

        $pages = model(PageModel::class, false);
        $pages->skipValidation(true);
        $saved = $pages->forSite()->update((int) $page->id, ['content' => $json]);
        $pages->skipValidation(false);

        if ($saved === false) {
            if ($storedImage !== null) {
                service('mediaService')->deletePublicPath($storedImage);
            }

            return redirect()->back()->withInput()->with('errors', $pages->errors() ?: ['form' => 'Le mot du doyen n’a pas pu être enregistré.']);
        }

        $this->saveEnglishContent($page, $content, $english);

        if ($oldImage !== null && $oldImage !== $input['photo']) {
            service('mediaService')->deletePublicPath((string) $oldImage);
        }

        service('contentTranslationService')->reset();

        return redirect()
            ->to('/admin/faculty/profile')
            ->with('message', 'Le mot du doyen a été mis à jour.');
    }

    private function facultyPage(): Page
    {
        /** @var Page|null $page */
        $page = model(PageModel::class, false)
            ->forSite()
            ->where('key', self::PAGE_KEY)
            ->first();

        if ($page instanceof Page) {
            return $page;
        }

        $content = json_encode($this->defaultFacultyContent(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($content === false) {
            throw new RuntimeException('Le contenu de page par défaut est invalide.');
        }

        $pages = model(PageModel::class, false);
        $id = $pages->insert([
            'key'             => self::PAGE_KEY,
            'title'           => lang('Site.pages.faculty'),
            'slug'            => 'faculte',
            'content'         => $content,
            'seo_title'       => lang('Site.pages.faculty'),
            'seo_description' => '',
            'is_published'    => 1,
        ], true);

        if ($id === false) {
            throw new RuntimeException('La page Faculté du site actif est introuvable et n’a pas pu être créée.');
        }

        /** @var Page $created */
        $created = model(PageModel::class, false)->find((int) $id);

        return $created;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFacultyContent(): array
    {
        return [
            'banner_subtitle' => '',
            'dean'            => [
                'label'      => lang('Site.faculty.deanLabelFallback'),
                'title'      => '',
                'photo'      => '',
                'name'       => '',
                'role'       => '',
                'specialty'  => '',
                'signature'  => '',
                'paragraphs' => [],
            ],
            'mission' => ['title' => '', 'paragraphs' => []],
            'vision'  => ['title' => '', 'paragraphs' => []],
            'values'  => [],
            'history' => ['label' => '', 'title' => '', 'text' => ''],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function translatedContent(Page $page): array
    {
        $translations = service('contentTranslationService')->values('pages', (int) $page->id, self::TRANSLATION_LOCALE);

        return site_decode_page_content((string) ($translations['content'] ?? ''));
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    private function deanContent(array $content): array
    {
        return isset($content['dean']) && is_array($content['dean']) ? $content['dean'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function deanFromRequest(): array
    {
        return [
            'label'      => trim((string) $this->request->getPost('dean_label')),
            'title'      => trim((string) $this->request->getPost('dean_title')),
            'name'       => trim((string) $this->request->getPost('dean_name')),
            'role'       => trim((string) $this->request->getPost('dean_role')),
            'specialty'  => trim((string) $this->request->getPost('dean_specialty')),
            'signature'  => trim((string) $this->request->getPost('dean_signature')),
            'paragraphs' => $this->paragraphsFromText((string) $this->request->getPost('dean_message')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function englishDeanFromRequest(): array
    {
        return [
            'label'      => trim((string) $this->request->getPost('translation_en_dean_label')),
            'title'      => trim((string) $this->request->getPost('translation_en_dean_title')),
            'role'       => trim((string) $this->request->getPost('translation_en_dean_role')),
            'specialty'  => trim((string) $this->request->getPost('translation_en_dean_specialty')),
            'signature'  => trim((string) $this->request->getPost('translation_en_dean_signature')),
            'paragraphs' => $this->paragraphsFromText((string) $this->request->getPost('translation_en_dean_message')),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function validateDean(array $data, string $prefix, bool $required = true): array
    {
        $errors = [];
        $limits = [
            'label'     => 120,
            'title'     => 255,
            'name'      => 255,
            'role'      => 255,
            'specialty' => 255,
            'signature' => 255,
        ];

        foreach ($limits as $field => $max) {
            $value = (string) ($data[$field] ?? '');
            if ($required && in_array($field, ['label', 'title', 'name', 'role'], true) && $value === '') {
                $errors[$prefix . 'dean_' . $field] = $this->fieldLabel($field) . ' est obligatoire.';
                continue;
            }

            if ($value !== '' && mb_strlen($value) > $max) {
                $errors[$prefix . 'dean_' . $field] = $this->fieldLabel($field) . ' ne peut pas dépasser ' . $max . ' caractères.';
            }
        }

        $message = implode("\n\n", array_map(static fn (mixed $paragraph): string => (string) $paragraph, (array) ($data['paragraphs'] ?? [])));
        if ($required && trim($message) === '') {
            $errors[$prefix . 'dean_message'] = 'Message du doyen est obligatoire.';
        }

        if ($message !== '' && mb_strlen($message) > 10000) {
            $errors[$prefix . 'dean_message'] = 'Message du doyen ne peut pas dépasser 10000 caractères.';
        }

        return $errors;
    }

    private function fieldLabel(string $field): string
    {
        return [
            'label'     => 'Libellé',
            'title'     => 'Titre',
            'name'      => 'Nom complet',
            'role'      => 'Fonction',
            'specialty' => 'Domaine ou département',
            'signature' => 'Signature',
        ][$field] ?? $field;
    }

    /**
     * @return list<string>
     */
    private function paragraphsFromText(string $value): array
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($value === '') {
            return [];
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
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizedDean(array $data): array
    {
        return [
            'label'      => (string) ($data['label'] ?? ''),
            'title'      => (string) ($data['title'] ?? ''),
            'photo'      => (string) ($data['photo'] ?? ''),
            'name'       => (string) ($data['name'] ?? ''),
            'role'       => (string) ($data['role'] ?? ''),
            'specialty'  => (string) ($data['specialty'] ?? ''),
            'signature'  => (string) ($data['signature'] ?? ''),
            'paragraphs' => array_values(array_map(static fn (mixed $paragraph): string => (string) $paragraph, (array) ($data['paragraphs'] ?? []))),
        ];
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $english
     */
    private function saveEnglishContent(Page $page, array $content, array $english): void
    {
        $existing = $this->translatedContent($page);
        $translated = array_replace_recursive($content, $existing);
        $translated['dean'] = $content['dean'] ?? [];

        foreach (['label', 'title', 'role', 'specialty', 'signature'] as $field) {
            $value = trim((string) ($english[$field] ?? ''));
            if ($value !== '') {
                $translated['dean'][$field] = $value;
            }
        }

        if (($english['paragraphs'] ?? []) !== []) {
            $translated['dean']['paragraphs'] = $english['paragraphs'];
        }

        $json = json_encode($translated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            service('contentTranslationService')->save('pages', (int) $page->id, self::TRANSLATION_LOCALE, ['content' => $json]);
        }
    }
}
