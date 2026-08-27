<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Entities\Post;
use App\Models\PostModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class PostController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $filters = [
            'q'      => trim((string) $this->request->getGet('q')),
            'type'   => (string) $this->request->getGet('type'),
            'status' => (string) $this->request->getGet('status'),
        ];

        $allowedTypes = $this->allowedTypes();
        if ($allowedTypes === []) {
            return redirect()->to('/admin')->with('error', 'Vous n’avez pas la permission de gérer ces contenus.');
        }

        $model = model(PostModel::class, false)->forSite();

        if (count($allowedTypes) === 1) {
            $model->where('type', $allowedTypes[0]);
        } elseif (in_array($filters['type'], array_keys($this->types()), true)) {
            $model->where('type', $filters['type']);
        }

        if (in_array($filters['status'], array_keys($this->statuses()), true)) {
            $model->where('status', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $model->groupStart()
                ->like('title', $filters['q'])
                ->orLike('excerpt', $filters['q'])
                ->orLike('body', $filters['q'])
                ->groupEnd();
        }

        return view('admin/posts/index', [
            'title'        => 'Actualités et événements | Administration',
            'activeAdmin'  => 'posts',
            'posts'        => $model
                ->orderBy('created_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->paginate(10, 'admin_posts'),
            'pager'        => $model->pager,
            'filters'      => $filters,
            'types'        => $this->types(),
            'statuses'     => $this->statuses(),
            'allowedTypes' => $allowedTypes,
        ]);
    }

    public function new(): string|RedirectResponse
    {
        $type = $this->requestedTypeOrDefault();

        if (! $this->canManageType($type)) {
            return redirect()->to('/admin/posts')->with('error', 'Vous n’avez pas la permission de créer ce type de contenu.');
        }

        return view('admin/posts/form', [
            'title'       => 'Nouveau contenu | Administration',
            'activeAdmin' => 'posts',
            'post'        => new Post([
                'type'      => $type,
                'status'    => 'draft',
                'featured'  => false,
                'home_order' => null,
            ]),
            'types'       => $this->types(),
            'statuses'    => $this->statuses(),
            'allowedTypes' => $this->allowedTypes(),
            'translations' => [],
            'action'      => site_url('admin/posts'),
            'isNew'       => true,
        ]);
    }

    public function create(): RedirectResponse
    {
        $result = $this->validatedData();
        $translations = $this->postTranslationsFromRequest();
        $result['errors'] = array_merge($result['errors'], $this->validatePostTranslations($translations));

        if ($result['errors'] !== []) {
            return redirect()->back()->withInput()->with('errors', $result['errors']);
        }

        $data = $result['data'];
        if (! $this->canManageType($data['type'])) {
            return redirect()->to('/admin/posts')->with('error', 'Vous n’avez pas la permission de créer ce type de contenu.');
        }

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $newCover = $this->storeCover();
        if (is_array($newCover)) {
            return redirect()->back()->withInput()->with('errors', $newCover);
        }

        if ($newCover !== null) {
            $data['cover_image'] = $newCover;
        }

        $posts = $this->postModel();
        $posts->skipValidation(true);
        $id = $posts->insert($data, true);
        $posts->skipValidation(false);

        if ($id === false) {
            if ($newCover !== null) {
                service('mediaService')->deletePublicPath($newCover);
            }

            return redirect()->back()->withInput()->with('errors', $posts->errors());
        }

        service('contentTranslationService')->save('posts', (int) $id, 'en', $translations);

        return redirect()->to('/admin/posts/' . $id . '/edit')->with('message', 'Le contenu a été créé.');
    }

    public function edit(int $id): string|RedirectResponse|ResponseInterface
    {
        $post = $this->findPost($id);

        if ($post === null) {
            return $this->notFound('Contenu introuvable.');
        }

        if (! $this->canManageType($post->type)) {
            return redirect()->to('/admin/posts')->with('error', 'Vous n’avez pas la permission de modifier ce contenu.');
        }

        return view('admin/posts/form', [
            'title'       => 'Modifier le contenu | Administration',
            'activeAdmin' => 'posts',
            'post'        => $post,
            'types'       => $this->types(),
            'statuses'    => $this->statuses(),
            'allowedTypes' => $this->allowedTypes(),
            'translations' => service('contentTranslationService')->values('posts', (int) $post->id, 'en'),
            'action'      => site_url('admin/posts/' . $post->id),
            'isNew'       => false,
        ]);
    }

    public function update(int $id): RedirectResponse|ResponseInterface
    {
        $post = $this->findPost($id);

        if ($post === null) {
            return $this->notFound('Contenu introuvable.');
        }

        if (! $this->canManageType($post->type)) {
            return redirect()->to('/admin/posts')->with('error', 'Vous n’avez pas la permission de modifier ce contenu.');
        }

        $result = $this->validatedData($post);
        $translations = $this->postTranslationsFromRequest();
        $result['errors'] = array_merge($result['errors'], $this->validatePostTranslations($translations));

        if ($result['errors'] !== []) {
            return redirect()->back()->withInput()->with('errors', $result['errors']);
        }

        $data = $result['data'];
        if (! $this->canManageType($data['type'])) {
            return redirect()->back()->withInput()->with('error', 'Vous n’avez pas la permission de choisir ce type de contenu.');
        }

        $data['updated_by'] = auth()->id();
        $oldCover = $post->cover_image;
        $newCover = $this->storeCover();

        if (is_array($newCover)) {
            return redirect()->back()->withInput()->with('errors', $newCover);
        }

        if ($newCover !== null) {
            $data['cover_image'] = $newCover;
        } elseif ($this->request->getPost('remove_cover') === '1') {
            $data['cover_image'] = null;
        }

        $posts = $this->postModel();
        $posts->skipValidation(true);
        $saved = $posts->update($id, $data);
        $posts->skipValidation(false);

        if ($saved === false) {
            if ($newCover !== null) {
                service('mediaService')->deletePublicPath($newCover);
            }

            return redirect()->back()->withInput()->with('errors', $posts->errors());
        }

        if ($newCover !== null || $this->request->getPost('remove_cover') === '1') {
            service('mediaService')->deletePublicPath($oldCover);
        }

        service('contentTranslationService')->save('posts', $id, 'en', $translations);

        return redirect()->to('/admin/posts/' . $id . '/edit')->with('message', 'Le contenu a été mis à jour.');
    }

    public function preview(int $id): string|RedirectResponse|ResponseInterface
    {
        $post = $this->findPost($id);

        if ($post === null) {
            return $this->notFound('Contenu introuvable.');
        }

        if (! $this->canManageType($post->type)) {
            return redirect()->to('/admin/posts')->with('error', 'Vous n’avez pas la permission de prévisualiser ce contenu.');
        }

        return view('admin/posts/preview', [
            'title'       => 'Aperçu | Administration',
            'activeAdmin' => 'posts',
            'post'        => $post,
        ]);
    }

    public function publish(int $id): RedirectResponse|ResponseInterface
    {
        return $this->setStatus($id, 'published', 'Le contenu a été publié.');
    }

    public function schedule(int $id): RedirectResponse|ResponseInterface
    {
        return $this->setStatus($id, 'scheduled', 'Le contenu a été programmé.');
    }

    public function archive(int $id): RedirectResponse|ResponseInterface
    {
        return $this->setStatus($id, 'archived', 'Le contenu a été archivé.');
    }

    public function draft(int $id): RedirectResponse|ResponseInterface
    {
        return $this->setStatus($id, 'draft', 'Le contenu est repassé en brouillon.');
    }

    private function setStatus(int $id, string $status, string $message): RedirectResponse|ResponseInterface
    {
        $post = $this->findPost($id);

        if ($post === null) {
            return $this->notFound('Contenu introuvable.');
        }

        if (! $this->canManageType($post->type)) {
            return redirect()->to('/admin/posts')->with('error', 'Vous n’avez pas la permission de changer l’état de ce contenu.');
        }

        $data = [
            'status'     => $status,
            'updated_by' => auth()->id(),
        ];

        if ($status === 'published') {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        if ($status === 'scheduled' && ($post->published_at === null || $post->published_at === '')) {
            return redirect()->to('/admin/posts/' . $id . '/edit')
                ->with('error', 'Indiquez une date de publication avant de programmer ce contenu.');
        }

        if ($status === 'scheduled' && strtotime((string) $post->published_at) <= time()) {
            return redirect()->to('/admin/posts/' . $id . '/edit')
                ->with('error', 'La date de publication programmée doit être dans le futur.');
        }

        $posts = $this->postModel();
        $posts->skipValidation(true);
        $saved = $posts->update($id, $data);
        $posts->skipValidation(false);

        if ($saved === false) {
            return redirect()->to('/admin/posts/' . $id . '/edit')
                ->with('errors', $posts->errors() ?: ['status' => 'L’état du contenu n’a pas pu être modifié.']);
        }

        return redirect()->to('/admin/posts')->with('message', $message);
    }

    /**
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    private function validatedData(?Post $post = null): array
    {
        $id   = $post?->id !== null ? (int) $post->id : null;
        $type = (string) $this->request->getPost('type');
        $title = trim((string) $this->request->getPost('title'));
        $slugSource = trim((string) $this->request->getPost('slug'));
        $slug = service('slugService')->unique($this->postModel(), $slugSource !== '' ? $slugSource : $title, $id);
        $status = (string) $this->request->getPost('status');
        $publishedAt = $this->datetimeFromInput((string) $this->request->getPost('published_at'));
        $eventStartsAt = $this->datetimeFromInput((string) $this->request->getPost('event_starts_at'));
        $eventEndsAt = $this->datetimeFromInput((string) $this->request->getPost('event_ends_at'));
        $homeOrder = trim((string) $this->request->getPost('home_order'));

        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $data = [
            'type'             => $type,
            'title'            => $title,
            'slug'             => $slug,
            'excerpt'          => trim((string) $this->request->getPost('excerpt')),
            'body'             => trim((string) $this->request->getPost('body')),
            'status'           => $status,
            'published_at'     => $publishedAt,
            'featured'         => $this->request->getPost('featured') === '1' ? 1 : 0,
            'home_order'       => $homeOrder === '' ? null : $homeOrder,
            'event_starts_at'  => $type === 'event' ? $eventStartsAt : null,
            'event_ends_at'    => $type === 'event' ? $eventEndsAt : null,
            'event_location'   => $type === 'event' ? trim((string) $this->request->getPost('event_location')) : null,
            'registration_url' => $type === 'event' ? trim((string) $this->request->getPost('registration_url')) : null,
            'seo_title'        => trim((string) $this->request->getPost('seo_title')) ?: null,
            'seo_description'  => trim((string) $this->request->getPost('seo_description')) ?: null,
        ];

        $validation = service('validation');
        $validation->setRules($this->validationRules($type), $this->validationMessages());
        $valid = $validation->run($data);
        $errors = $valid ? [] : $validation->getErrors();

        if ($status === 'scheduled') {
            if ($publishedAt === null) {
                $errors['published_at'] = 'La date de publication est obligatoire pour programmer un contenu.';
            } elseif (strtotime($publishedAt) <= time()) {
                $errors['published_at'] = 'La date de publication programmée doit être dans le futur.';
            }
        }

        if ($status === 'published' && $publishedAt !== null && strtotime($publishedAt) > time()) {
            $errors['published_at'] = 'Une publication immédiate ne peut pas utiliser une date future. Choisissez le statut programmé.';
        }

        if ($type === 'event') {
            if ($eventStartsAt === null) {
                $errors['event_starts_at'] = 'La date et l’heure de début sont obligatoires pour un événement.';
            }

            if ($eventEndsAt !== null && $eventStartsAt !== null && strtotime($eventEndsAt) <= strtotime($eventStartsAt)) {
                $errors['event_ends_at'] = 'La date de fin doit être postérieure à la date de début.';
            }

            if ($data['registration_url'] !== null && $data['registration_url'] !== '' && ! $this->isHttpUrl((string) $data['registration_url'])) {
                $errors['registration_url'] = 'Le lien d’inscription doit être une URL HTTP ou HTTPS valide.';
            }
        }

        if ($errors === [] && $data['home_order'] !== null) {
            $data['home_order'] = (int) $data['home_order'];
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /**
     * @return array<string, string>
     */
    private function validationRules(string $type): array
    {
        $rules = [
            'type'            => 'required|in_list[news,event]',
            'title'           => 'required|max_length[255]',
            'slug'            => 'required|max_length[180]|regex_match[/^[a-z0-9-]+$/]',
            'excerpt'         => 'required|max_length[1000]',
            'body'            => 'required',
            'status'          => 'required|in_list[draft,scheduled,published,archived]',
            'home_order'      => 'permit_empty|integer',
            'seo_title'       => 'permit_empty|max_length[255]',
            'seo_description' => 'permit_empty|max_length[500]',
        ];

        if ($type === 'event') {
            $rules['event_location'] = 'required|max_length[255]';
            $rules['registration_url'] = 'permit_empty|valid_url_strict|max_length[500]';
        }

        return $rules;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function validationMessages(): array
    {
        return [
            'type' => [
                'required' => 'Le type de contenu est obligatoire.',
                'in_list'  => 'Le type de contenu doit être une actualité ou un événement.',
            ],
            'title' => [
                'required'   => 'Le titre est obligatoire.',
                'max_length' => 'Le titre ne peut pas dépasser 255 caractères.',
            ],
            'slug' => [
                'required'    => 'Le slug est obligatoire.',
                'regex_match' => 'Le slug doit contenir uniquement des lettres minuscules, des chiffres et des tirets.',
            ],
            'excerpt' => [
                'required' => 'Le résumé est obligatoire.',
            ],
            'body' => [
                'required' => 'Le contenu est obligatoire.',
            ],
            'status' => [
                'required' => 'Le statut est obligatoire.',
                'in_list'  => 'Le statut doit être brouillon, programmé, publié ou archivé.',
            ],
            'home_order' => [
                'integer' => 'L’ordre sur l’accueil doit être un nombre entier.',
            ],
            'event_location' => [
                'required' => 'Le lieu est obligatoire pour un événement.',
            ],
            'registration_url' => [
                'valid_url_strict' => 'Le lien d’inscription doit être une URL valide.',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function postTranslationsFromRequest(): array
    {
        $fields = ['title', 'excerpt', 'body', 'event_location', 'seo_title', 'seo_description'];
        $translations = [];

        foreach ($fields as $field) {
            $value = $this->request->getPost('translation_en_' . $field);
            $translations[$field] = is_array($value) ? '' : trim((string) $value);
        }

        return $translations;
    }

    /**
     * @param array<string, string> $translations
     *
     * @return array<string, string>
     */
    private function validatePostTranslations(array $translations): array
    {
        $limits = [
            'title'           => 255,
            'excerpt'         => 1000,
            'event_location'  => 255,
            'seo_title'       => 255,
            'seo_description' => 500,
        ];

        $errors = [];

        foreach ($limits as $field => $limit) {
            if (($translations[$field] ?? '') !== '' && mb_strlen($translations[$field]) > $limit) {
                $errors['translation_en_' . $field] = 'La traduction anglaise ne peut pas dépasser ' . $limit . ' caractères.';
            }
        }

        return $errors;
    }

    /**
     * @return string|array<string, string>|null
     */
    private function storeCover(): string|array|null
    {
        $file = $this->request->getFile('cover_image');

        if (! service('mediaService')->hasFile($file)) {
            return null;
        }

        $error = null;
        $path  = service('mediaService')->storePostCover($file, $error);

        return $path ?? ['cover_image' => $error ?? 'L’image de couverture est invalide.'];
    }

    private function findPost(int $id): ?Post
    {
        /** @var Post|null $post */
        $post = model(PostModel::class, false)->forSite()->find($id);

        return $post;
    }

    private function postModel(): PostModel
    {
        return model(PostModel::class, false)->forSite();
    }

    private function notFound(string $message): ResponseInterface
    {
        return $this->response
            ->setStatusCode(404)
            ->setBody(view('errors/html/error_404', ['message' => $message]));
    }

    private function datetimeFromInput(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = str_replace('T', ' ', $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
            $value .= ':00';
        }

        return strtotime($value) === false ? null : date('Y-m-d H:i:s', strtotime($value));
    }

    private function isHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function requestedTypeOrDefault(): string
    {
        $requested = (string) $this->request->getGet('type');

        if (array_key_exists($requested, $this->types()) && $this->canManageType($requested)) {
            return $requested;
        }

        return $this->allowedTypes()[0] ?? 'news';
    }

    /**
     * @return list<string>
     */
    private function allowedTypes(): array
    {
        $user = auth()->user();
        $types = [];

        if ($user?->can('news.manage')) {
            $types[] = 'news';
        }

        if ($user?->can('events.manage')) {
            $types[] = 'event';
        }

        return $types;
    }

    private function canManageType(?string $type): bool
    {
        $permission = $type === 'event' ? 'events.manage' : 'news.manage';

        return auth()->user()?->can($permission) ?? false;
    }

    /**
     * @return array<string, string>
     */
    private function types(): array
    {
        return service('postVisibilityService')->types();
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return service('postVisibilityService')->statuses();
    }
}
