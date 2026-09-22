<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AlumniProfileModel;
use App\Models\ContentBlockModel;
use App\Models\HomeContentModel;
use App\Models\HomeHeroSlideModel;
use App\Models\HomeHighlightModel;
use App\Models\LaboratoryModel;
use App\Models\PageModel;
use App\Models\ProgrammeModel;
use App\Models\PublicationModel;
use App\Models\ResearchProjectModel;
use App\Models\SettingModel;
use App\Models\SiteModel;
use App\Models\SiteStatModel;
use App\Models\StaffModel;
use App\Models\TestimonialModel;
use App\Models\TimelineItemModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;
use ReflectionClass;
use Throwable;

class ResourceController extends BaseController
{
    public function index(string $resource): string|ResponseInterface
    {
        $config = $this->resource($resource);
        if ($config === null) {
            return $this->notFound('Module d’administration introuvable.');
        }

        if ($redirect = $this->retiredResourceGuard($resource)) {
            return $redirect;
        }

        if ($redirect = $this->centralSiteSelectionGuard($resource)) {
            return $redirect;
        }

        if ($redirect = $this->centralContentGuard($resource)) {
            return $redirect;
        }

        $model = $this->model($config);
        $supportsTrash = $this->modelUsesSoftDeletes($model);
        $query = trim((string) $this->request->getGet('q'));
        $published = (string) $this->request->getGet('published');
        $trash = $supportsTrash && (string) $this->request->getGet('trash') === '1';

        if ($trash) {
            $model->onlyDeleted();
        }

        if ($query !== '' && ($config['search'] ?? []) !== []) {
            $model->groupStart();
            foreach ($config['search'] as $index => $field) {
                $index === 0 ? $model->like($field, $query) : $model->orLike($field, $query);
            }
            $model->groupEnd();
        }

        if ($published !== '' && in_array($published, ['0', '1'], true) && ($config['publishedField'] ?? null) !== null) {
            $model->where((string) $config['publishedField'], (int) $published);
        }

        foreach ($config['orderBy'] ?? ['id' => 'DESC'] as $field => $direction) {
            $model->orderBy((string) $field, (string) $direction);
        }

        return view('admin/resources/index', [
            'title'       => $config['title'] . ' | Administration',
            'activeAdmin' => $resource,
            'resource'    => $resource,
            'config'      => $config,
            'items'       => $model->paginate((int) ($config['perPage'] ?? 12), 'admin_' . str_replace('-', '_', $resource)),
            'pager'       => $model->pager,
            'fieldOptions' => $this->fieldOptions($config),
            'filters'     => [
                'q'         => $query,
                'published' => $published,
                'trash'     => $trash ? '1' : '',
            ],
            'supportsTrash' => $supportsTrash,
            'trash'         => $trash,
            ...($resource === 'home-hero-slides' ? [
                'heroIndicatorSize'    => $this->heroIndicatorSizeValue(),
                'heroIndicatorOptions' => $this->heroIndicatorSizeOptions(),
            ] : []),
        ]);
    }

    public function saveHeroIndicatorSize(): RedirectResponse|ResponseInterface
    {
        if ($redirect = $this->centralContentGuard('home-hero-slides')) {
            return $redirect;
        }

        $size = trim((string) $this->request->getPost('indicator_size'));
        $options = $this->heroIndicatorSizeOptions();

        if (! array_key_exists($size, $options)) {
            return redirect()->to('/admin/home-hero-slides')->with('error', 'Taille de pastille non autorisée.');
        }

        $siteId = (int) service('siteResolver')->activeSiteId();
        $model = model(SettingModel::class);
        $existing = $model->forSite($siteId)->where('key', 'home.hero_indicator_size')->first();
        $saved = false;

        try {
            if ($existing === null) {
                $saved = $model->skipValidation(true)->insert([
                    'site_id' => $siteId,
                    'class'   => 'App\\Settings\\Site',
                    'key'     => 'home.hero_indicator_size',
                    'value'   => $size,
                    'type'    => 'string',
                    'context' => 'home',
                ]);
            } else {
                $saved = $model->skipValidation(true)->update((int) $existing->id, ['value' => $size]);
            }
        } catch (Throwable) {
            $model->skipValidation(false);

            return redirect()->to('/admin/home-hero-slides')->with('error', 'Impossible d’enregistrer la taille des pastilles.');
        } finally {
            $model->skipValidation(false);
        }

        if ($saved === false) {
            return redirect()->to('/admin/home-hero-slides')->with('error', 'Impossible d’enregistrer la taille des pastilles.');
        }

        service('settingsService')->reset();

        return redirect()->to('/admin/home-hero-slides')->with('message', 'La taille des pastilles du carrousel a été enregistrée.');
    }

    public function new(string $resource): string|RedirectResponse|ResponseInterface
    {
        $config = $this->resource($resource);
        if ($config === null) {
            return $this->notFound('Module d’administration introuvable.');
        }

        if ($redirect = $this->centralContentGuard($resource)) {
            return $redirect;
        }


        if (($config['creationDisabled'] ?? false)) {
            return redirect()->to('/admin/' . $resource)->with('error', (string) ($config['creationDisabledMessage'] ?? 'Ce module contient des pages prédéfinies. Modifiez les contenus existants.'));
        }

        if (($config['singleton'] ?? false) && $this->model($config)->countAllResults() > 0) {
            return redirect()->to('/admin/' . $resource)->with('error', 'Ce contenu unique existe déjà.');
        }

        return view('admin/resources/form', [
            'title'       => 'Créer — ' . $config['title'] . ' | Administration',
            'activeAdmin' => $resource,
            'resource'    => $resource,
            'config'      => $config,
            'item'        => $this->defaults($config),
            'translations' => [],
            'translationFields' => $this->translationFields($config),
            'fieldOptions' => $this->fieldOptions($config),
            'action'      => site_url('admin/' . $resource),
            'isNew'       => true,
        ]);
    }

    public function create(string $resource): RedirectResponse|ResponseInterface
    {
        $config = $this->resource($resource);
        if ($config === null) {
            return $this->notFound('Module d’administration introuvable.');
        }

        if ($redirect = $this->centralContentGuard($resource)) {
            return $redirect;
        }


        if (($config['creationDisabled'] ?? false)) {
            return redirect()->to('/admin/' . $resource)->with('error', (string) ($config['creationDisabledMessage'] ?? 'Ce module contient des pages prédéfinies. Modifiez les contenus existants.'));
        }

        if (($config['singleton'] ?? false) && $this->model($config)->countAllResults() > 0) {
            return redirect()->to('/admin/' . $resource)->with('error', 'Ce contenu unique existe déjà.');
        }

        return $this->save($resource, $config);
    }

    public function edit(string $resource, int $id): string|RedirectResponse|ResponseInterface
    {
        $config = $this->resource($resource);
        if ($config === null) {
            return $this->notFound('Module d’administration introuvable.');
        }

        if ($redirect = $this->centralContentGuard($resource)) {
            return $redirect;
        }


        $item = $this->model($config)->find($id);
        if ($item === null) {
            return $this->notFound('Contenu introuvable.');
        }

        return view('admin/resources/form', [
            'title'       => 'Modifier — ' . $config['title'] . ' | Administration',
            'activeAdmin' => $resource,
            'resource'    => $resource,
            'config'      => $config,
            'item'        => $this->itemData($item),
            'translations' => service('contentTranslationService')->values($this->translationResourceType($resource, $config), $id, 'en'),
            'translationFields' => $this->translationFields($config),
            'fieldOptions' => $this->fieldOptions($config),
            'action'      => site_url('admin/' . $resource . '/' . $id),
            'isNew'       => false,
        ]);
    }

    public function update(string $resource, int $id): RedirectResponse|ResponseInterface
    {
        $config = $this->resource($resource);
        if ($config === null) {
            return $this->notFound('Module d’administration introuvable.');
        }

        if ($redirect = $this->centralContentGuard($resource)) {
            return $redirect;
        }


        if ($this->model($config)->find($id) === null) {
            return $this->notFound('Contenu introuvable.');
        }

        return $this->save($resource, $config, $id);
    }

    public function delete(string $resource, int $id): RedirectResponse|ResponseInterface
    {
        $config = $this->resource($resource);
        if ($config === null) {
            return $this->notFound('Module d’administration introuvable.');
        }

        if ($redirect = $this->centralContentGuard($resource)) {
            return $redirect;
        }


        if (($config['singleton'] ?? false) || ($config['deletionDisabled'] ?? false)) {
            return redirect()->to('/admin/' . $resource)->with('error', 'Ce contenu unique ne peut pas être supprimé.');
        }

        $model = $this->model($config);
        $item = $model->find($id);

        if ($item === null) {
            return $this->notFound('Contenu introuvable.');
        }

        if (! $model->delete($id)) {
            return redirect()->to('/admin/' . $resource)->with('error', 'La suppression n’a pas pu être effectuée.');
        }

        if (! $this->modelUsesSoftDeletes($model)) {
            foreach ($config['fields'] as $field) {
                if (($field['type'] ?? null) === 'image') {
                    service('mediaService')->deletePublicPath($this->itemData($item)[$field['name']] ?? null);
                }
            }
        }

        return redirect()->to('/admin/' . $resource)->with('message', 'Le contenu a été supprimé.');
    }

    public function restore(string $resource, int $id): RedirectResponse|ResponseInterface
    {
        $config = $this->resource($resource);
        if ($config === null) {
            return $this->notFound('Module d’administration introuvable.');
        }

        if ($redirect = $this->centralContentGuard($resource)) {
            return $redirect;
        }


        $model = $this->model($config);
        if (! $this->modelUsesSoftDeletes($model)) {
            return redirect()->to('/admin/' . $resource)->with('error', 'Ce module ne prend pas en charge la restauration.');
        }

        $item = $model->withDeleted()->find($id);
        if ($item === null) {
            return $this->notFound('Contenu introuvable.');
        }

        $this->siteScopedBuilder($model, $config)->where('id', $id)->update([
            'deleted_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/admin/' . $resource . '?trash=1')->with('message', 'Le contenu a été restauré.');
    }

    public function purge(string $resource, int $id): RedirectResponse|ResponseInterface
    {
        $config = $this->resource($resource);
        if ($config === null) {
            return $this->notFound('Module d’administration introuvable.');
        }

        if ($redirect = $this->centralContentGuard($resource)) {
            return $redirect;
        }


        $model = $this->model($config);
        if (! $this->modelUsesSoftDeletes($model)) {
            return redirect()->to('/admin/' . $resource)->with('error', 'Ce module ne prend pas en charge la suppression définitive.');
        }

        $item = $model->withDeleted()->find($id);
        if ($item === null) {
            return $this->notFound('Contenu introuvable.');
        }

        if (! $model->delete($id, true)) {
            return redirect()->to('/admin/' . $resource . '?trash=1')->with('error', 'La suppression définitive n’a pas pu être effectuée.');
        }

        foreach ($config['fields'] as $field) {
            if (($field['type'] ?? null) === 'image') {
                service('mediaService')->deletePublicPath($this->itemData($item)[$field['name']] ?? null);
            }
        }

        return redirect()->to('/admin/' . $resource . '?trash=1')->with('message', 'Le contenu a été supprimé définitivement.');
    }

    /**
     * Actions groupées : archiver, supprimer ou publier/masquer plusieurs
     * contenus d'un module en une seule opération.
     */
    public function bulk(string $resource): RedirectResponse|ResponseInterface
    {
        $config = $this->resource($resource);
        if ($config === null) {
            return $this->notFound('Module d’administration introuvable.');
        }

        if ($redirect = $this->centralContentGuard($resource)) {
            return $redirect;
        }

        $action = (string) $this->request->getPost('bulk_action');
        $ids = $this->request->getPost('ids');
        $ids = is_array($ids) ? array_values(array_map('intval', $ids)) : [];
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        if ($ids === []) {
            return redirect()->to('/admin/' . $resource)->with('error', 'Aucun contenu n’a été sélectionné.');
        }

        $model = $this->model($config);
        $supportsTrash = $this->modelUsesSoftDeletes($model);
        $owned = $this->ownedItemsByIds($model, $config, $ids);
        $ownedIds = array_map(static fn (array $row): int => $row['id'], $owned);

        if ($ownedIds === []) {
            return redirect()->to('/admin/' . $resource)->with('error', 'Aucun contenu n’a été sélectionné.');
        }

        $count = count($ownedIds);

        if ($action === 'publish' || $action === 'unpublish') {
            if (($config['publishedField'] ?? null) === null) {
                return redirect()->to('/admin/' . $resource)->with('error', 'Ce module ne prend pas en charge la publication groupée.');
            }

            $this->siteScopedBuilder($model, $config)->whereIn('id', $ownedIds)->update([
                (string) $config['publishedField'] => $action === 'publish' ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $label = $action === 'publish' ? 'contenus publiés' : 'contenus masqués';

            return redirect()->to('/admin/' . $resource)->with('message', $count . ' ' . $label . '.');
        }

        if ($action === 'delete') {
            if (($config['singleton'] ?? false) || ($config['deletionDisabled'] ?? false)) {
                return redirect()->to('/admin/' . $resource)->with('error', 'Ce module ne permet pas la suppression groupée.');
            }

            if (! $supportsTrash) {
                foreach ($owned as $row) {
                    foreach ($config['fields'] as $field) {
                        if (($field['type'] ?? null) === 'image') {
                            service('mediaService')->deletePublicPath($this->itemData($row['item'])[$field['name']] ?? null);
                        }
                    }
                }
            }

            $deleter = $this->model($config);
            try {
                $deleter->skipValidation(true);
                if ($this->isSiteScopedResource($config)) {
                    $deleter->where('site_id', service('siteResolver')->activeSiteId());
                }
                $deleter->whereIn('id', $ownedIds)->delete();
            } finally {
                $deleter->skipValidation(false);
            }

            return redirect()->to('/admin/' . $resource)->with('message', $count . ' contenus ' . ($supportsTrash ? 'archivés' : 'supprimés') . '.');
        }

        return redirect()->to('/admin/' . $resource)->with('error', 'Action groupée inconnue.');
    }

    /**
     * Duplique un contenu en copiant ses valeurs et en garantissant l’unicité
     * des slug. Les traductions anglaises sont également recopiées.
     */
    public function duplicate(string $resource, int $id): RedirectResponse|ResponseInterface
    {
        $config = $this->resource($resource);
        if ($config === null) {
            return $this->notFound('Module d’administration introuvable.');
        }

        if ($redirect = $this->centralContentGuard($resource)) {
            return $redirect;
        }

        if (($config['singleton'] ?? false) || ($config['creationDisabled'] ?? false)) {
            return redirect()->to('/admin/' . $resource)->with('error', 'Ce contenu ne peut pas être dupliqué.');
        }

        $model = $this->model($config);
        $source = $model->find($id);
        if ($source === null) {
            return $this->notFound('Contenu introuvable.');
        }

        $data = $this->itemData($source);

        foreach (['id', 'created_at', 'updated_at'] as $drop) {
            unset($data[$drop]);
        }
        if (isset($data['deleted_at'])) {
            $data['deleted_at'] = null;
        }

        if (($config['publishedField'] ?? null) !== null) {
            $data[(string) $config['publishedField']] = 0;
        }

        foreach (($config['unique'] ?? []) as $field) {
            if ($field === 'id' || ! isset($data[$field])) {
                continue;
            }
            if ($field !== 'slug') {
                $data[$field] = $data[$field] . '-copie';
                continue;
            }
            $data[$field] = service('slugService')->unique($model, (string) $data[$field], null);
        }

        try {
            $model->skipValidation(true);
            $newId = $model->insert($data, true);
            $model->skipValidation(false);
        } catch (Throwable) {
            $model->skipValidation(false);

            return redirect()->to('/admin/' . $resource)->with('error', 'La duplication a échoué.');
        }

        if ($newId === false) {
            $model->skipValidation(false);

            return redirect()->to('/admin/' . $resource)->with('error', 'La duplication a échoué.');
        }

        $newId = (int) $newId;

        $translationName = $this->translationResourceType($resource, $config);
        $translations = service('contentTranslationService')->values($translationName, $id, 'en');

        if ($translations !== []) {
            service('contentTranslationService')->save($translationName, $newId, 'en', $translations);
        }
        service('contentTranslationService')->reset();

        return redirect()
            ->to('/admin/' . $resource . '/' . $newId . '/edit')
            ->with('message', 'Le contenu a été dupliqué. Vérifiez et publiez la copie.');
    }

    /**
     * Réordonne les contenus d'un module (drag & drop). Endpoint AJAX qui met
     * à jour display_order d'après la séquence reçue.
     */
    public function reorder(string $resource): ResponseInterface
    {
        $config = $this->resource($resource);
        if ($config === null) {
            return $this->response->setStatusCode(404);
        }

        if ($redirect = $this->centralContentGuard($resource)) {
            return $redirect;
        }

        $ordered = $this->request->getPost('ids');
        if (is_string($ordered)) {
            $ordered = array_filter(array_map('intval', explode(',', $ordered)));
        }
        if (! is_array($ordered)) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false]);
        }

        $ordered = array_values(array_map('intval', $ordered));

        if (($config['orderBy'] ?? null) !== null) {
            $firstOrderField = array_key_first($config['orderBy']);
            $orderField = $firstOrderField === 'display_order' ? 'display_order' : $this->reorderSortField($config);
        } else {
            $orderField = $this->reorderSortField($config);
        }

        if ($orderField === null) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false]);
        }

        $model = $this->model($config);

        foreach ($ordered as $index => $id) {
            if ($id <= 0) {
                continue;
            }

            $this->siteScopedBuilder($model, $config)->where('id', $id)->update([
                $orderField  => $index + 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Fresh query builder so CI4 update() resetWrite() cannot drop site_id
     * between loop iterations.
     *
     * @param array<string, mixed> $config
     */
    private function siteScopedBuilder(Model $model, array $config): \CodeIgniter\Database\BaseBuilder
    {
        $builder = db_connect()->table($model->getTable());
        if ($this->isSiteScopedResource($config)) {
            $builder->where('site_id', service('siteResolver')->activeSiteId());
        }

        return $builder;
    }

    /**
     * @param list<int> $ids
     * @param array<string, mixed> $config
     *
     * @return list<array{id: int, item: object|array}>
     */
    private function ownedItemsByIds(Model $model, array $config, array $ids): array
    {
        $owned = [];
        foreach ($ids as $id) {
            $item = $this->model($config)->find($id);
            if ($item === null) {
                continue;
            }

            $owned[] = ['id' => $id, 'item' => $item];
        }

        return $owned;
    }

    private function reorderSortField(array $config): ?string
    {
        return in_array('display_order', array_map(
            static fn (array $f): string => (string) ($f['name'] ?? ''),
            $config['fields'] ?? [],
        ), true) ? 'display_order' : null;
    }

    /**
     * When display_order is drag-managed, assign the next order on create and
     * keep the existing value on update if the form omits it.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $data
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $current
     */
    private function applyManagedDisplayOrder(array $config, Model $model, array &$data, array &$raw, array $current, ?int $id): void
    {
        foreach ($config['fields'] as $field) {
            if (($field['name'] ?? null) !== 'display_order' || empty($field['managedByDrag'])) {
                continue;
            }

            $posted = $data['display_order'] ?? null;
            if ($posted !== null && $posted !== '') {
                continue;
            }

            if ($id !== null) {
                $data['display_order'] = (int) ($current['display_order'] ?? 0);
                $raw['display_order'] = (string) $data['display_order'];
                continue;
            }

            $builder = $this->siteScopedBuilder($model, $config);
            $max = $builder->selectMax('display_order')->get()->getRowArray();
            $next = (int) ($max['display_order'] ?? 0) + 1;
            $data['display_order'] = $next;
            $raw['display_order'] = (string) $next;
        }
    }

    /**
     * @return array<string, string>
     */
    private function heroIndicatorSizeOptions(): array
    {
        return [
            '0.75' => 'Petite (0,75 rem)',
            '1'    => 'Moyenne (1 rem)',
            '1.25' => 'Grande (1,25 rem)',
            '1.5'  => 'Très grande (1,5 rem)',
        ];
    }

    private function heroIndicatorSizeValue(): string
    {
        $value = (string) service('settingsService')->get('home.hero_indicator_size', '0.75');

        return array_key_exists($value, $this->heroIndicatorSizeOptions()) ? $value : '0.75';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function save(string $resource, array $config, ?int $id = null): RedirectResponse|ResponseInterface
    {
        $model = $this->model($config);
        $item = $id === null ? null : $model->find($id);
        $current = $item === null ? [] : $this->itemData($item);

        [$data, $raw] = $this->dataFromRequest($config, $model, $id);
        $this->applyManagedDisplayOrder($config, $model, $data, $raw, $current, $id);
        $this->applyDerivedData($resource, $data, $raw);
        $translationFields = $this->translationFields($config);
        $translations = $this->translationsFromRequest($resource, $config, $translationFields, $data);
        $errors = $this->validateResourceData($config, $model, $data, $raw, $id);
        $errors = array_merge($errors, $this->validateTranslations($translationFields, $translations));

        if ($resource === 'pages') {
            $errors = array_merge($errors, $this->validatePageContentRequest((string) ($data['key'] ?? ''), 'translation_en_page_content', false));
        }
        $storedImages = [];
        $oldImages = [];

        foreach ($config['fields'] as $field) {
            if (($field['type'] ?? null) !== 'image') {
                continue;
            }

            $name = (string) $field['name'];
            $file = $this->request->getFile($name);

            if ($this->request->getPost('remove_' . $name) === '1') {
                $data[$name] = null;
                $oldImages[] = $current[$name] ?? null;
            }

            if (! service('mediaService')->hasFile($file)) {
                $nextValue = $data[$name] ?? ($current[$name] ?? null);
                if (($field['required'] ?? false) && trim((string) $nextValue) === '') {
                    $errors[$name] = ((string) ($field['label'] ?? $name)) . ' est obligatoire.';
                }

                continue;
            }

            $error = null;
            $path = service('mediaService')->storePublicImage($file, (string) ($field['folder'] ?? $resource), $error);

            if ($path === null) {
                $errors[$name] = $error ?? 'L’image téléversée est invalide.';
                continue;
            }

            $data[$name] = $path;
            $storedImages[] = $path;
            $oldImages[] = $current[$name] ?? null;
        }

        if ($resource === 'settings' && (($this->settingDefinitions()[(string) ($data['key'] ?? '')]['type'] ?? null) === 'path')) {
            $file = $this->request->getFile('setting_value_file');

            if (service('mediaService')->hasFile($file)) {
                $error = null;
                $path = service('mediaService')->storePublicImage($file, 'settings', $error);

                if ($path === null) {
                    $errors['value'] = $error ?? 'L’image téléversée est invalide.';
                } else {
                    $data['value'] = $path;
                    $raw['value'] = $path;
                    $storedImages[] = $path;
                    $oldImages[] = $current['value'] ?? null;
                }
            }
        }

        if ($errors !== []) {
            foreach ($storedImages as $path) {
                service('mediaService')->deletePublicPath($path);
            }

            return redirect()->back()->withInput()->with('errors', $errors);
        }

        if ($resource === 'home-content') {
            $data['singleton_key'] = 1;
            $data['updated_by'] = auth()->id();

            if ($id === null) {
                $data['hero_media_type'] = 'image';
                $data['hero_media_path'] = 'assets/images/logo-placeholder.png';
                $data['hero_badge'] ??= 'Faculté';
                $data['hero_title'] ??= 'Titre à personnaliser';
                $data['hero_text'] ??= 'Texte à personnaliser via les slides du héros.';
                $data['hero_primary_label'] ??= 'En savoir plus';
                $data['hero_primary_url'] ??= '/formations';
                $data['hero_secondary_label'] ??= 'Contact';
                $data['hero_secondary_url'] ??= '/contact';
            }
        }

        try {
            if ($resource === 'sites' && $id === null) {
                $saved = service('facultySiteProvisioning')->createFaculty($data);
            } else {
                $model->skipValidation(true);
                $saved = $id === null ? $model->insert($data, true) : $model->update($id, $data);
                $model->skipValidation(false);
            }
        } catch (Throwable) {
            $model->skipValidation(false);

            foreach ($storedImages as $path) {
                service('mediaService')->deletePublicPath($path);
            }

            return redirect()->back()->withInput()->with('errors', ['form' => 'L’enregistrement a échoué.']);
        }

        if ($saved === false) {
            foreach ($storedImages as $path) {
                service('mediaService')->deletePublicPath($path);
            }

            return redirect()->back()->withInput()->with('errors', $model->errors() ?: ['form' => 'L’enregistrement a échoué.']);
        }

        foreach ($oldImages as $path) {
            service('mediaService')->deletePublicPath($path);
        }

        if ($resource === 'settings') {
            service('settingsService')->reset();
        }

        if ($resource === 'sites') {
            service('siteResolver')->reset();
            service('settingsService')->reset();
        }

        $successMessage = $id === null ? 'Le contenu a été créé.' : 'Le contenu a été mis à jour.';

        $targetId = $id ?? (int) $saved;
        service('contentTranslationService')->save($this->translationResourceType($resource, $config), $targetId, 'en', $translations);
        service('contentTranslationService')->reset();

        return redirect()
            ->to('/admin/' . $resource)
            ->with('message', $successMessage);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function dataFromRequest(array $config, Model $model, ?int $id): array
    {
        $data = [];
        $raw = [];

        foreach ($config['fields'] as $field) {
            $name = (string) $field['name'];
            $type = (string) ($field['type'] ?? 'text');

            if ($type === 'image') {
                continue;
            }

            if ($type === 'boolean') {
                $data[$name] = $this->request->getPost($name) === '1' ? 1 : 0;
                $raw[$name] = (string) $data[$name];
                continue;
            }

            if ($type === 'slug') {
                $source = trim((string) $this->request->getPost($name));
                if ($source === '') {
                    $source = trim((string) $this->request->getPost((string) ($field['source'] ?? 'title')));
                }

                $data[$name] = service('slugService')->unique($model, $source, $id);
                $raw[$name] = $data[$name];
                continue;
            }

            $value = $this->request->getPost($name);
            $value = is_array($value) ? '' : trim((string) $value);
            $raw[$name] = $value;

            if ($type === 'json_list') {
                $data[$name] = array_values(array_filter(
                    array_map('trim', preg_split('/\R/u', $value) ?: []),
                    static fn (string $line): bool => $line !== '',
                ));
                continue;
            }

            if ($type === 'json_text') {
                $data[$name] = $value === '' && ($field['nullable'] ?? false) ? null : $value;
                continue;
            }

            if ($type === 'page_content') {
                $pageKey = (string) ($data['key'] ?? $this->request->getPost('key'));
                $data[$name] = json_encode($this->pageContentFromRequest($pageKey, 'page_content'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $raw[$name] = (string) $data[$name];
                continue;
            }

            if ($type === 'integer' || $type === 'relation') {
                $data[$name] = $value === '' ? null : (int) $value;
                continue;
            }

            $data[$name] = $value === '' && ($field['nullable'] ?? false) ? null : $value;
        }

        foreach ($config['defaults'] ?? [] as $name => $value) {
            if (! array_key_exists($name, $data)) {
                // Do not reset site design/menu/sections on update when those fields are hidden from the form.
                if ($id !== null && in_array($name, ['theme', 'theme_config', 'menu_config', 'enabled_sections'], true)) {
                    continue;
                }

                $data[$name] = $value;
                $raw[$name] = is_array($value) ? json_encode($value) : (string) $value;
            }
        }

        if (($config['key'] ?? '') === 'sites') {
            $data['theme'] = 'default';
            $raw['theme'] = 'default';
            if ($id === null && empty($data['theme_config'])) {
                $data['theme_config'] = '{"layout":"classic","hero_image":"assets/images/logo-placeholder.png"}';
            }
        }

        return [$data, $raw];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $raw
     */
    private function applyDerivedData(string $resource, array &$data, array &$raw): void
    {
        if ($resource !== 'settings') {
            return;
        }

        $definition = $this->settingDefinitions()[(string) ($data['key'] ?? '')] ?? null;
        if ($definition === null) {
            return;
        }

        $data['class'] = 'App\\Settings\\Site';
        $data['context'] = $definition['context'];
        $data['type'] = $definition['type'];
        $raw['class'] = $data['class'];
        $raw['context'] = $data['context'];
        $raw['type'] = $data['type'];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $data
     * @param array<string, mixed> $raw
     *
     * @return array<string, string>
     */
    private function validateResourceData(array $config, Model $model, array $data, array $raw, ?int $id): array
    {
        $errors = [];

        foreach ($config['fields'] as $field) {
            if (($field['type'] ?? null) === 'image') {
                continue;
            }

            $name = (string) $field['name'];
            $label = (string) ($field['label'] ?? $name);
            $type = (string) ($field['type'] ?? 'text');
            $value = $data[$name] ?? null;
            $rawValue = $raw[$name] ?? $value;
            $isEmpty = $value === null || $value === '' || $value === [];

            if (($field['required'] ?? false) && $isEmpty) {
                $errors[$name] = $label . ' est obligatoire.';
                continue;
            }

            if ($isEmpty) {
                continue;
            }

            if (isset($field['max']) && is_string($rawValue) && mb_strlen($rawValue) > (int) $field['max']) {
                $errors[$name] = $label . ' ne peut pas dépasser ' . (int) $field['max'] . ' caractères.';
            }

            if (($type === 'integer' || $type === 'relation') && filter_var((string) $rawValue, FILTER_VALIDATE_INT) === false) {
                $errors[$name] = $label . ' doit être un nombre entier.';
            }

            if ($type === 'json_text') {
                json_decode((string) $rawValue, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[$name] = $label . ' doit contenir un JSON valide.';
                }
            }

            if ($type === 'email' && ! filter_var((string) $rawValue, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = $label . ' doit être une adresse électronique valide.';
            }

            if ($type === 'external_url' && ! $this->isHttpUrl((string) $rawValue)) {
                $errors[$name] = $label . ' doit être une URL HTTP ou HTTPS valide.';
            }

            if ($type === 'slug' && preg_match('/^[a-z0-9-]+$/', (string) $value) !== 1) {
                $errors[$name] = $label . ' doit contenir uniquement des lettres minuscules, chiffres et tirets.';
            }

            if (isset($field['pattern']) && preg_match((string) $field['pattern'], (string) $value) !== 1) {
                $errors[$name] = (string) ($field['patternMessage'] ?? ($label . ' contient des caractères non autorisés.'));
            }

            if (in_array($type, ['select', 'readonly_select', 'icon'], true)) {
                $options = $this->optionsForField($field);
                if (! array_key_exists((string) $value, $options)) {
                    $errors[$name] = $label . ' contient une valeur non autorisée.';
                }
            }

            if ($type === 'relation' && $value !== null) {
                $relationModel = model((string) $field['model'], false);
                if (method_exists($relationModel, 'forSite')) {
                    $relationModel->forSite();
                }

                if ($relationModel->find((int) $value) === null) {
                    $errors[$name] = $label . ' fait référence à un contenu introuvable.';
                }
            }
        }

        foreach ($config['unique'] ?? [] as $field) {
            $value = $data[$field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $builder = $model->builder()->where((string) $field, $value);
            if ($this->isSiteScopedResource($config)) {
                $builder->where('site_id', service('siteResolver')->activeSiteId());
            }

            if ($id !== null) {
                $builder->where('id !=', $id);
            }

            if ($builder->countAllResults() > 0) {
                $errors[(string) $field] = 'Cette valeur est déjà utilisée.';
            }
        }

        if (($config['key'] ?? '') === 'settings') {
            $key = strtolower((string) ($data['key'] ?? ''));
            $definition = $this->settingDefinitions()[$key] ?? null;

            if ($definition === null) {
                $errors['key'] = 'Ce paramètre ne peut pas être modifié depuis cette interface.';
            }

            if (preg_match('/(password|secret|token|api[_-]?key|private|credential)/', $key) === 1) {
                $errors['key'] = 'Les secrets techniques ne doivent pas être enregistrés dans les paramètres.';
            }

            $value = trim((string) ($raw['value'] ?? ''));
            if ($value !== '' && $definition !== null) {
                if (($definition['type'] ?? '') === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors['value'] = 'La valeur doit être une adresse électronique valide.';
                }

                if (($definition['type'] ?? '') === 'color' && preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) {
                    $errors['value'] = 'La couleur doit utiliser le format #RRGGBB.';
                }

                if (($definition['type'] ?? '') === 'path' && preg_match('#^(assets|uploads)/[-a-zA-Z0-9_./]+\\.(jpg|jpeg|png|webp)$#', $value) !== 1) {
                    $errors['value'] = 'L’image doit provenir des médias publics autorisés.';
                }
            }
        }

        if (($config['key'] ?? '') === 'pages') {
            $errors = array_merge($errors, $this->validatePageContentRequest((string) ($data['key'] ?? ''), 'page_content'));
        }

        return $errors;
    }

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, string>
     */
    private function translationsFromRequest(string $resource, array $config, array $fields, array $data): array
    {
        $translations = [];

        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $value = $this->request->getPost('translation_en_' . $name);
            $translations[$name] = is_array($value) ? '' : trim((string) $value);
        }

        if ($resource === 'pages') {
            $pageKey = (string) ($data['key'] ?? '');
            $partial = $this->pageContentFromRequest($pageKey, 'translation_en_page_content', true);

            if ($partial === []) {
                $translations['content'] = '';
            } else {
                $baseContent = site_decode_page_content((string) ($data['content'] ?? ''));
                $translations['content'] = (string) json_encode(
                    array_replace_recursive($baseContent, $partial),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );
            }
        }

        return $translations;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, string>      $translations
     *
     * @return array<string, string>
     */
    private function validateTranslations(array $fields, array $translations): array
    {
        $errors = [];

        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $value = $translations[$name] ?? '';
            if ($value === '') {
                continue;
            }

            if (isset($field['max']) && mb_strlen($value) > (int) $field['max']) {
                $errors['translation_en_' . $name] = 'La traduction anglaise de ' . mb_strtolower((string) ($field['label'] ?? $name)) . ' ne peut pas dépasser ' . (int) $field['max'] . ' caractères.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<array<string, mixed>>
     */
    private function translationFields(array $config): array
    {
        $fieldNames = $this->translationFieldNames((string) ($config['key'] ?? ''));
        if ($fieldNames === []) {
            return [];
        }

        $fieldsByName = [];
        foreach ($config['fields'] as $field) {
            $fieldsByName[(string) $field['name']] = $field;
        }

        $fields = [];
        foreach ($fieldNames as $name) {
            if (! isset($fieldsByName[$name])) {
                continue;
            }

            $field = $fieldsByName[$name];
            if (! in_array((string) ($field['type'] ?? 'text'), ['text', 'textarea'], true)) {
                continue;
            }

            $field['required'] = false;
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function translationFieldNames(string $resource): array
    {
        return [
            'home-content' => [
                'hero_badge',
                'hero_title',
                'hero_text',
                'hero_primary_label',
                'hero_secondary_label',
                'about_label',
                'about_title',
                'about_body',
                'about_button_label',
                'research_label',
                'research_title',
                'research_body',
                'research_button_label',
                'programmes_label',
                'programmes_title',
                'programmes_text',
                'programmes_button_label',
                'posts_label',
                'posts_title',
                'posts_text',
                'posts_button_label',
                'seo_title',
                'seo_description',
            ],
            'home-hero-slides' => ['alt_text', 'badge', 'title', 'text', 'primary_cta_label', 'secondary_cta_label'],
            'home-highlights' => ['title', 'description'],
            'site-stats' => ['label', 'suffix'],
            'programmes' => ['title', 'duration', 'summary', 'description', 'admission_conditions'],
            'staff' => ['grade', 'specialty', 'role', 'biography'],
            'laboratories' => ['name', 'description'],
            'publications' => ['title', 'authors', 'journal'],
            'research-projects' => ['title', 'description', 'funder'],
            'timeline-items' => ['title', 'description'],
            'alumni-profiles' => ['role', 'organization', 'biography'],
            'testimonials' => ['promotion', 'quote'],
            'pages' => ['title', 'seo_title', 'seo_description'],
            'settings' => ['value'],
            'content-blocks' => ['title', 'content'],
            'sites' => [],
        ][$resource] ?? [];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function defaults(array $config): array
    {
        $defaults = $config['defaults'] ?? [];

        foreach ($config['fields'] as $field) {
            $name = (string) $field['name'];
            if (array_key_exists($name, $defaults)) {
                continue;
            }

            $defaults[$name] = match ($field['type'] ?? 'text') {
                'boolean' => (int) ($field['default'] ?? 1),
                'integer', 'relation' => $field['default'] ?? null,
                'json_list' => [],
                default => $field['default'] ?? null,
            };
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    private function itemData(mixed $item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if (is_object($item) && method_exists($item, 'toArray')) {
            return $item->toArray();
        }

        return (array) $item;
    }

    /**
     * La superadministration édite le contenu de la faculté sélectionnée
     * dans la même application (site_id actif). Les facultés elles-mêmes
     * ne se listent et ne se configurent que depuis l’instance centrale.
     */
    private function centralContentGuard(string $resource): ?RedirectResponse
    {
        if ($resource === 'sites' && ! service('adminAccess')->isCentralAdminHost()) {
            return redirect()->to('/admin')->with('error', 'La liste des facultés se gère depuis la superadministration.');
        }

        return null;
    }

    private function retiredResourceGuard(string $resource): ?RedirectResponse
    {
        if (in_array($resource, service('adminNavigation')->retiredResourceKeys(), true)) {
            return redirect()->to('/admin')->with('error', 'Ce module n’est plus disponible dans l’administration.');
        }

        return null;
    }

    private function centralSiteSelectionGuard(string $resource): ?RedirectResponse
    {
        if ($resource === 'sites') {
            return null;
        }

        if (service('siteResolver')->requiresExplicitAdminSiteSelection()) {
            return redirect()->to('/admin')->with('error', 'Choisissez d’abord une faculté dans le sélecteur en haut de page.');
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function heroCtaTargetOptions(): array
    {
        return [
            'none'            => 'Aucun bouton',
            'programmes'      => 'Formations',
            'contact'         => 'Contact',
            'news'            => 'Actualités',
            'about'           => 'Présentation (accueil)',
            'faculty_profile' => 'Présentation & mot du doyen',
            'custom'          => 'Lien personnalisé',
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function model(array $config): Model
    {
        /** @var Model $model */
        $model = model((string) $config['model'], false);

        if ($this->isSiteScopedResource($config) && method_exists($model, 'forSite')) {
            $model->forSite();
        }

        return $model;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function isSiteScopedResource(array $config): bool
    {
        return ($config['siteScoped'] ?? true) !== false;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function translationResourceType(string $resource, array $config): string
    {
        return (string) ($config['translationResource'] ?? str_replace('-', '_', $resource));
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, string>
     */
    private function optionsForField(array $field): array
    {
        if (isset($field['options'])) {
            return $field['options'];
        }

        if (($field['type'] ?? null) !== 'relation') {
            return [];
        }

        $model = model((string) $field['model'], false);
        if (method_exists($model, 'forSite')) {
            $model->forSite();
        }

        $labelField = (string) ($field['labelField'] ?? 'title');
        $options = [];

        foreach ($model->orderBy($labelField, 'ASC')->findAll() as $item) {
            $data = $this->itemData($item);
            $options[(string) $data['id']] = (string) ($data[$labelField] ?? ('#' . $data['id']));
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, array<string, string>>
     */
    private function fieldOptions(array $config): array
    {
        $options = [];

        foreach ($config['fields'] as $field) {
            if (in_array($field['type'] ?? '', ['select', 'readonly_select', 'relation', 'icon'], true)) {
                $options[(string) $field['name']] = $this->optionsForField($field);
            }
        }

        return $options;
    }

    /**
     * @return array<string, array{label: string, type: string, required?: bool}>
     */
    private function pageContentSchema(string $pageKey): array
    {
        return $this->pageContentSchemas()[$pageKey] ?? [];
    }

    /**
     * @return array<string, array<string, array{label: string, type: string, required?: bool}>>
     */
    private function pageContentSchemas(): array
    {
        return [
            'faculty' => [
                'banner_subtitle'      => ['label' => 'Sous-titre du bandeau', 'type' => 'text', 'required' => true],
                'dean.label'           => ['label' => 'Libellé du mot du Doyen', 'type' => 'text', 'required' => true],
                'dean.title'           => ['label' => 'Titre du mot du Doyen', 'type' => 'text', 'required' => true],
                'dean.photo'           => ['label' => 'Photo du Doyen', 'type' => 'external_url'],
                'dean.name'            => ['label' => 'Nom du Doyen', 'type' => 'text'],
                'dean.role'            => ['label' => 'Fonction du Doyen', 'type' => 'text'],
                'dean.specialty'       => ['label' => 'Spécialité du Doyen', 'type' => 'text'],
                'dean.signature'       => ['label' => 'Signature', 'type' => 'text'],
                'dean.paragraphs'      => ['label' => 'Paragraphes du mot du Doyen', 'type' => 'list', 'required' => true],
                'mission_label'        => ['label' => 'Libellé mission et vision', 'type' => 'text', 'required' => true],
                'mission_title'        => ['label' => 'Titre mission et vision', 'type' => 'text', 'required' => true],
                'mission.icon'         => ['label' => 'Icône mission', 'type' => 'icon'],
                'mission.title'        => ['label' => 'Titre mission', 'type' => 'text', 'required' => true],
                'mission.paragraphs'   => ['label' => 'Paragraphes mission', 'type' => 'list', 'required' => true],
                'vision.icon'          => ['label' => 'Icône vision', 'type' => 'icon'],
                'vision.title'         => ['label' => 'Titre vision', 'type' => 'text', 'required' => true],
                'vision.paragraphs'    => ['label' => 'Paragraphes vision', 'type' => 'list', 'required' => true],
                'values.0.icon'        => ['label' => 'Icône valeur 1', 'type' => 'icon'],
                'values.0.title'       => ['label' => 'Titre valeur 1', 'type' => 'text'],
                'values.0.description' => ['label' => 'Description valeur 1', 'type' => 'textarea'],
                'values.1.icon'        => ['label' => 'Icône valeur 2', 'type' => 'icon'],
                'values.1.title'       => ['label' => 'Titre valeur 2', 'type' => 'text'],
                'values.1.description' => ['label' => 'Description valeur 2', 'type' => 'textarea'],
                'values.2.icon'        => ['label' => 'Icône valeur 3', 'type' => 'icon'],
                'values.2.title'       => ['label' => 'Titre valeur 3', 'type' => 'text'],
                'values.2.description' => ['label' => 'Description valeur 3', 'type' => 'textarea'],
                'history.label'        => ['label' => 'Libellé historique', 'type' => 'text', 'required' => true],
                'history.title'        => ['label' => 'Titre historique', 'type' => 'text', 'required' => true],
                'history.text'         => ['label' => 'Texte historique', 'type' => 'textarea', 'required' => true],
            ],
            'formations' => [
                'banner_subtitle' => ['label' => 'Sous-titre du bandeau', 'type' => 'text', 'required' => true],
                'banner_image'    => ['label' => 'Image du bandeau', 'type' => 'external_url'],
                'offer_label'     => ['label' => 'Libellé de l’offre', 'type' => 'text', 'required' => true],
                'offer_title'     => ['label' => 'Titre de l’offre', 'type' => 'text', 'required' => true],
                'offer_text'      => ['label' => 'Texte de l’offre', 'type' => 'textarea', 'required' => true],
                'cta_title'       => ['label' => 'Titre de l’appel à l’action', 'type' => 'text'],
                'cta_text'        => ['label' => 'Texte de l’appel à l’action', 'type' => 'textarea'],
                'cta_label'       => ['label' => 'Bouton de l’appel à l’action', 'type' => 'text'],
                'cta_url'         => ['label' => 'Lien de l’appel à l’action', 'type' => 'text'],
            ],
            'research' => [
                'banner_subtitle'    => ['label' => 'Sous-titre du bandeau', 'type' => 'text', 'required' => true],
                'labs_label'         => ['label' => 'Libellé des laboratoires', 'type' => 'text', 'required' => true],
                'labs_title'         => ['label' => 'Titre des laboratoires', 'type' => 'text', 'required' => true],
                'labs_text'          => ['label' => 'Texte des laboratoires', 'type' => 'textarea', 'required' => true],
                'publications_label' => ['label' => 'Libellé des publications', 'type' => 'text', 'required' => true],
                'publications_title' => ['label' => 'Titre des publications', 'type' => 'text', 'required' => true],
                'publications_text'  => ['label' => 'Texte des publications', 'type' => 'textarea'],
                'projects_label'     => ['label' => 'Libellé des projets', 'type' => 'text', 'required' => true],
                'projects_title'     => ['label' => 'Titre des projets', 'type' => 'text', 'required' => true],
            ],
            'staff' => [
                'banner_subtitle' => ['label' => 'Sous-titre du bandeau', 'type' => 'text', 'required' => true],
                'banner_image'    => ['label' => 'Image du bandeau', 'type' => 'external_url'],
            ],
            'posts' => [
                'banner_subtitle' => ['label' => 'Sous-titre du bandeau', 'type' => 'text', 'required' => true],
            ],
            'alumni' => [
                'banner_subtitle'    => ['label' => 'Sous-titre du bandeau', 'type' => 'text', 'required' => true],
                'banner_image'       => ['label' => 'Image du bandeau', 'type' => 'external_url'],
                'intro_label'        => ['label' => 'Libellé introduction', 'type' => 'text', 'required' => true],
                'intro_title'        => ['label' => 'Titre introduction', 'type' => 'text', 'required' => true],
                'intro_paragraphs'   => ['label' => 'Paragraphes introduction', 'type' => 'list', 'required' => true],
                'intro_button_label' => ['label' => 'Bouton introduction', 'type' => 'text'],
                'intro_button_url'   => ['label' => 'Lien introduction', 'type' => 'text'],
                'profiles_label'     => ['label' => 'Libellé profils', 'type' => 'text', 'required' => true],
                'profiles_title'     => ['label' => 'Titre profils', 'type' => 'text', 'required' => true],
                'profiles_text'      => ['label' => 'Texte profils', 'type' => 'textarea'],
                'testimonials_label' => ['label' => 'Libellé témoignages', 'type' => 'text', 'required' => true],
                'testimonials_title' => ['label' => 'Titre témoignages', 'type' => 'text', 'required' => true],
                'cta_title'          => ['label' => 'Titre final', 'type' => 'text'],
                'cta_text'           => ['label' => 'Texte final', 'type' => 'textarea'],
                'cta_label'          => ['label' => 'Bouton final', 'type' => 'text'],
                'cta_url'            => ['label' => 'Lien final', 'type' => 'text'],
            ],
            'contact' => [
                'banner_subtitle' => ['label' => 'Sous-titre du bandeau', 'type' => 'text', 'required' => true],
                'contact_label'   => ['label' => 'Libellé coordonnées', 'type' => 'text', 'required' => true],
                'contact_title'   => ['label' => 'Titre coordonnées', 'type' => 'text', 'required' => true],
                'form_title'      => ['label' => 'Titre du formulaire', 'type' => 'text', 'required' => true],
                'form_help'       => ['label' => 'Texte d’aide du formulaire', 'type' => 'textarea'],
                'map_url'         => ['label' => 'Carte intégrée', 'type' => 'external_url'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function pageOptions(): array
    {
        return [
            'faculty'    => 'La Faculté',
            'formations' => 'Formations',
            'research'   => 'Recherche',
            'staff'      => 'Corps enseignant',
            'posts'      => 'Actualités et événements',
            'alumni'     => 'Alumni',
            'contact'    => 'Contact',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function blockTypeOptions(): array
    {
        return [
            'hero'                => 'Héros',
            'dean_message'        => 'Mot du doyen',
            'programmes_preview'  => 'Aperçu des formations',
            'news_preview'        => 'Actualités récentes',
            'research_labs'       => 'Laboratoires de recherche',
            'staff_preview'       => 'Aperçu du personnel',
            'statistics'          => 'Statistiques',
            'custom_text'         => 'Texte libre',
            'image_gallery'       => 'Galerie d’images',
            'contact_cta'         => 'Appel à l’action contact',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function themeOptions(): array
    {
        return [
            'default' => 'Défaut',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function iconOptions(): array
    {
        return [
            'bi-award'          => 'Distinction',
            'bi-bank'           => 'Institution financière',
            'bi-bar-chart'      => 'Statistiques',
            'bi-bar-chart-line' => 'Analyse',
            'bi-book'           => 'Livre',
            'bi-briefcase'      => 'Projet',
            'bi-building'       => 'Institution',
            'bi-building-gear'  => 'Organisation',
            'bi-bullseye'       => 'Mission',
            'bi-calendar-event' => 'Événement',
            'bi-cash-stack'     => 'Finance',
            'bi-chat-quote'     => 'Témoignage',
            'bi-compass'        => 'Orientation',
            'bi-diagram-3'      => 'Réseau',
            'bi-eye'            => 'Vision',
            'bi-globe'          => 'Monde',
            'bi-globe2'         => 'International',
            'bi-graph-up-arrow' => 'Croissance',
            'bi-journal-text'   => 'Publication',
            'bi-lightbulb'      => 'Innovation',
            'bi-mortarboard'    => 'Formation',
            'bi-people'         => 'Communauté',
            'bi-receipt'        => 'Comptabilité',
            'bi-rocket'         => 'Lancement',
            'bi-search'         => 'Recherche',
            'bi-shield-check'   => 'Intégrité',
            'bi-star'           => 'Excellence',
            'bi-stars'          => 'Atout',
            'bi-tree'           => 'Développement durable',
        ];
    }

    /**
     * @return array<string, array{label: string, type: string, context: string}>
     */
    private function settingDefinitions(): array
    {
        return [
            'institution.faculty_name' => ['label' => 'Nom complet de la faculté', 'type' => 'string', 'context' => 'institution'],
            'institution.short_name'   => ['label' => 'Sigle de la faculté', 'type' => 'string', 'context' => 'institution'],
            'institution.university'   => ['label' => 'Université', 'type' => 'string', 'context' => 'institution'],
            'contact.address_line'     => ['label' => 'Avenue / quartier', 'type' => 'string', 'context' => 'contact'],
            'contact.address_commune'  => ['label' => 'Commune', 'type' => 'string', 'context' => 'contact'],
            'contact.address_province' => ['label' => 'Province', 'type' => 'string', 'context' => 'contact'],
            'contact.address_country'  => ['label' => 'Pays', 'type' => 'string', 'context' => 'contact'],
            'contact.phone'            => ['label' => 'Téléphone', 'type' => 'string', 'context' => 'contact'],
            'contact.email'            => ['label' => 'Adresse électronique', 'type' => 'email', 'context' => 'contact'],
            'contact.hours'            => ['label' => 'Horaires', 'type' => 'string', 'context' => 'contact'],
            'footer.text'              => ['label' => 'Texte du pied de page', 'type' => 'text', 'context' => 'footer'],
            'footer.copyright'         => ['label' => 'Copyright', 'type' => 'string', 'context' => 'footer'],
            'assets.logo'              => ['label' => 'Logo', 'type' => 'path', 'context' => 'assets'],
            'seo.default_title'        => ['label' => 'Titre SEO par défaut', 'type' => 'string', 'context' => 'seo'],
            'seo.default_description'  => ['label' => 'Description SEO par défaut', 'type' => 'text', 'context' => 'seo'],
            'seo.theme_color'          => ['label' => 'Couleur du thème', 'type' => 'color', 'context' => 'seo'],
            'seo.og_image'             => ['label' => 'Image de partage par défaut', 'type' => 'path', 'context' => 'seo'],
            'home.hero_indicator_size' => ['label' => 'Taille des pastilles du carrousel d’accueil', 'type' => 'string', 'context' => 'home'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function settingOptions(): array
    {
        $options = [];

        foreach ($this->settingDefinitions() as $key => $definition) {
            $options[$key] = $definition['label'];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function pageContentFromRequest(string $pageKey, string $prefix, bool $skipEmpty = false): array
    {
        $content = [];

        foreach ($this->pageContentSchema($pageKey) as $path => $definition) {
            $inputName = $prefix . '_' . $this->fieldPathInputName($path);
            $value = $this->request->getPost($inputName);
            $value = is_array($value) ? '' : trim((string) $value);

            if (($definition['type'] ?? 'text') === 'list') {
                $parsed = array_values(array_filter(
                    array_map('trim', preg_split('/\R/u', $value) ?: []),
                    static fn (string $line): bool => $line !== '',
                ));

                if ($skipEmpty && $parsed === []) {
                    continue;
                }

                $this->setNestedContentValue($content, $path, $parsed);
                continue;
            }

            if ($skipEmpty && $value === '') {
                continue;
            }

            $this->setNestedContentValue($content, $path, $value);
        }

        return $content;
    }

    /**
     * @return array<string, string>
     */
    private function validatePageContentRequest(string $pageKey, string $prefix, bool $enforceRequired = true): array
    {
        $errors = [];

        foreach ($this->pageContentSchema($pageKey) as $path => $definition) {
            $inputName = $prefix . '_' . $this->fieldPathInputName($path);
            $value = $this->request->getPost($inputName);
            $value = is_array($value) ? '' : trim((string) $value);
            $label = $definition['label'];
            $type = $definition['type'] ?? 'text';

            if ($enforceRequired && ($definition['required'] ?? false) && $value === '') {
                $errors[$inputName] = $label . ' est obligatoire.';
                continue;
            }

            if ($value === '') {
                continue;
            }

            if ($type === 'external_url' && ! $this->isHttpUrl($value)) {
                $errors[$inputName] = $label . ' doit être une URL HTTP ou HTTPS valide.';
            }

            if ($type === 'icon' && ! array_key_exists($value, $this->iconOptions())) {
                $errors[$inputName] = $label . ' contient une icône non autorisée.';
            }
        }

        return $errors;
    }

    private function fieldPathInputName(string $path): string
    {
        return preg_replace('/[^a-zA-Z0-9]+/', '_', $path) ?: $path;
    }

    /**
     * @param array<string, mixed> $content
     */
    private function setNestedContentValue(array &$content, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $target = &$content;

        foreach ($segments as $index => $segment) {
            $isLast = $index === array_key_last($segments);
            $key = ctype_digit($segment) ? (int) $segment : $segment;

            if ($isLast) {
                $target[$key] = $value;
                return;
            }

            if (! isset($target[$key]) || ! is_array($target[$key])) {
                $target[$key] = [];
            }

            $target = &$target[$key];
        }
    }

    private function notFound(string $message): ResponseInterface
    {
        return $this->response
            ->setStatusCode(404)
            ->setBody(view('errors/html/error_404', ['message' => $message]));
    }

    private function isHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function modelUsesSoftDeletes(Model $model): bool
    {
        $reflection = new ReflectionClass($model);

        if (! $reflection->hasProperty('useSoftDeletes')) {
            return false;
        }

        $property = $reflection->getProperty('useSoftDeletes');
        $property->setAccessible(true);

        return (bool) $property->getValue($model);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resource(string $resource): ?array
    {
        if ($resource === 'sites' && ! service('adminAccess')->isSuperAdmin(auth()->user())) {
            return null;
        }

        $resources = $this->resources();

        if (! isset($resources[$resource])) {
            return null;
        }

        $config = $resources[$resource];
        $config['key'] = $resource;
        $config['iconOptions'] = $this->iconOptions();

        if ($resource === 'sites') {
            $config = $this->normalizeSitesAdminConfig($config);
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function normalizeSitesAdminConfig(array $config): array
    {
        $hideHostnames = service('adminAccess')->isCentralAdminInstance();
        $hidden = ['theme', 'theme_config', 'menu_config', 'enabled_sections', 'address'];
        if ($hideHostnames) {
            $hidden[] = 'hostnames';
        }

        $config['sections'] = array_values(array_filter(array_map(
            static function (array $section) use ($hidden): ?array {
                $section['fields'] = array_values(array_filter(
                    $section['fields'],
                    static fn (string $field): bool => ! in_array($field, $hidden, true),
                ));

                return $section['fields'] === [] ? null : $section;
            },
            $config['sections'] ?? [],
        )));

        $config['fields'] = array_values(array_filter(
            $config['fields'] ?? [],
            static fn (array $field): bool => ! in_array((string) ($field['name'] ?? ''), $hidden, true),
        ));

        return $config;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function resources(): array
    {
        return [
            'home-content' => [
                'title'          => 'Textes des sections',
                'singular'       => 'contenu d’accueil',
                'model'          => HomeContentModel::class,
                'permission'     => 'home.manage',
                'singleton'      => true,
                'unique'         => ['singleton_key'],
                'defaults'       => ['singleton_key' => 1],
                'search'         => ['about_title', 'research_title', 'programmes_title', 'programmes_text', 'posts_title', 'posts_text'],
                'orderBy'        => ['id' => 'ASC'],
                'previewField'   => ['name' => 'about_body', 'label' => 'Présentation'],
                'sections'       => [
                    ['title' => 'Présentation', 'fields' => ['about_label', 'about_title', 'about_body', 'about_button_label', 'about_button_url']],
                    ['title' => 'Recherche', 'fields' => ['research_label', 'research_title', 'research_body', 'research_button_label', 'research_button_url']],
                    ['title' => 'Formations', 'fields' => ['programmes_label', 'programmes_title', 'programmes_text', 'programmes_button_label', 'programmes_button_url']],
                    ['title' => 'Actualités et événements', 'fields' => ['posts_label', 'posts_title', 'posts_text', 'posts_button_label', 'posts_button_url']],
                    ['title' => 'Référencement', 'fields' => ['seo_title', 'seo_description']],
                ],
                'fields'         => [
                    ['name' => 'about_label', 'label' => 'Libellé de présentation', 'type' => 'text', 'required' => true, 'max' => 255],
                    ['name' => 'about_title', 'label' => 'Titre de présentation', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'about_body', 'label' => 'Texte de présentation', 'type' => 'textarea', 'required' => true],
                    ['name' => 'about_button_label', 'label' => 'Libellé du bouton de présentation', 'type' => 'text', 'nullable' => true, 'max' => 255],
                    ['name' => 'about_button_url', 'label' => 'Lien du bouton de présentation', 'type' => 'text', 'nullable' => true, 'max' => 500],
                    ['name' => 'research_label', 'label' => 'Libellé recherche', 'type' => 'text', 'required' => true, 'max' => 255],
                    ['name' => 'research_title', 'label' => 'Titre recherche', 'type' => 'text', 'required' => true, 'max' => 255],
                    ['name' => 'research_body', 'label' => 'Texte recherche', 'type' => 'textarea', 'required' => true],
                    ['name' => 'research_button_label', 'label' => 'Libellé du bouton recherche', 'type' => 'text', 'nullable' => true, 'max' => 255],
                    ['name' => 'research_button_url', 'label' => 'Lien du bouton recherche', 'type' => 'text', 'nullable' => true, 'max' => 500],
                    ['name' => 'programmes_label', 'label' => 'Libellé des formations', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'programmes_title', 'label' => 'Titre des formations', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'programmes_text', 'label' => 'Texte des formations', 'type' => 'textarea', 'required' => true],
                    ['name' => 'programmes_button_label', 'label' => 'Libellé du bouton des formations', 'type' => 'text', 'nullable' => true, 'max' => 255],
                    ['name' => 'programmes_button_url', 'label' => 'Lien du bouton des formations', 'type' => 'text', 'nullable' => true, 'max' => 500],
                    ['name' => 'posts_label', 'label' => 'Libellé des actualités', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'posts_title', 'label' => 'Titre des actualités', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'posts_text', 'label' => 'Texte des actualités', 'type' => 'textarea', 'required' => true],
                    ['name' => 'posts_button_label', 'label' => 'Libellé du bouton des actualités', 'type' => 'text', 'nullable' => true, 'max' => 255],
                    ['name' => 'posts_button_url', 'label' => 'Lien du bouton des actualités', 'type' => 'text', 'nullable' => true, 'max' => 500],
                    ['name' => 'seo_title', 'label' => 'Titre SEO', 'type' => 'text', 'required' => true, 'max' => 255],
                    ['name' => 'seo_description', 'label' => 'Description SEO', 'type' => 'textarea', 'required' => true],
                ],
            ],
            'home-hero-slides' => [
                'title'          => 'Héros (slides)',
                'singular'       => 'slide du héros',
                'model'          => HomeHeroSlideModel::class,
                'permission'     => 'home.manage',
                'publishedField' => 'is_published',
                'search'         => ['alt_text', 'title', 'badge', 'text'],
                'orderBy'        => ['display_order' => 'ASC', 'id' => 'ASC'],
                'previewField'   => ['name' => 'text', 'label' => 'Texte'],
                'sections'       => [
                    ['title' => 'Image', 'fields' => ['image_path', 'alt_text']],
                    ['title' => 'Textes du slide', 'fields' => ['badge', 'title', 'text']],
                    ['title' => 'Bouton principal', 'fields' => ['primary_cta_target', 'primary_cta_label', 'primary_cta_url']],
                    ['title' => 'Bouton secondaire', 'fields' => ['secondary_cta_target', 'secondary_cta_label', 'secondary_cta_url']],
                ],
                'fields'         => $this->orderedPublishedFields([
                    ['name' => 'image_path', 'label' => 'Image', 'type' => 'image', 'folder' => 'home-hero-slides', 'required' => true, 'list' => true],
                    ['name' => 'alt_text', 'label' => 'Texte alternatif', 'type' => 'text', 'required' => true, 'max' => 255],
                    ['name' => 'badge', 'label' => 'Badge', 'type' => 'text', 'nullable' => true, 'max' => 255, 'list' => true],
                    ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'text', 'label' => 'Texte', 'type' => 'textarea', 'required' => true],
                    ['name' => 'primary_cta_target', 'label' => 'Lien du bouton principal', 'type' => 'select', 'required' => true, 'options' => $this->heroCtaTargetOptions(), 'list' => true],
                    ['name' => 'primary_cta_label', 'label' => 'Libellé du bouton principal', 'type' => 'text', 'nullable' => true, 'max' => 255, 'showWhen' => ['primary_cta_target' => '!none']],
                    ['name' => 'primary_cta_url', 'label' => 'URL personnalisée (principal)', 'type' => 'text', 'nullable' => true, 'max' => 500, 'showWhen' => ['primary_cta_target' => 'custom']],
                    ['name' => 'secondary_cta_target', 'label' => 'Lien du bouton secondaire', 'type' => 'select', 'required' => true, 'options' => $this->heroCtaTargetOptions()],
                    ['name' => 'secondary_cta_label', 'label' => 'Libellé du bouton secondaire', 'type' => 'text', 'nullable' => true, 'max' => 255, 'showWhen' => ['secondary_cta_target' => '!none']],
                    ['name' => 'secondary_cta_url', 'label' => 'URL personnalisée (secondaire)', 'type' => 'text', 'nullable' => true, 'max' => 500, 'showWhen' => ['secondary_cta_target' => 'custom']],
                ]),
            ],
            'home-highlights' => [
                'title'          => 'Atouts de l’accueil',
                'singular'       => 'atout',
                'model'          => HomeHighlightModel::class,
                'permission'     => 'home.manage',
                'publishedField' => 'is_published',
                'search'         => ['title', 'description'],
                'orderBy'        => ['display_order' => 'ASC', 'id' => 'ASC'],
                'previewField'   => ['name' => 'description', 'label' => 'Description'],
                'fields'         => $this->orderedPublishedFields([
                    ['name' => 'icon', 'label' => 'Icône', 'type' => 'icon', 'required' => true, 'options' => $this->iconOptions(), 'list' => true],
                    ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'nullable' => true],
                ]),
            ],
            'programmes' => [
                'title'          => 'Formations',
                'singular'       => 'formation',
                'model'          => ProgrammeModel::class,
                'permission'     => 'programmes.manage',
                'publishedField' => 'is_published',
                'unique'         => ['slug'],
                'search'         => ['title', 'summary', 'description'],
                'orderBy'        => ['display_order' => 'ASC', 'id' => 'ASC'],
                'previewField'   => ['name' => 'summary', 'label' => 'Résumé'],
                'fields'         => [
                    ['name' => 'level', 'label' => 'Niveau', 'type' => 'select', 'required' => true, 'options' => ['licence' => 'Licence', 'master' => 'Master', 'doctorat' => 'Doctorat'], 'list' => true],
                    ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'slug', 'label' => 'Slug', 'type' => 'slug', 'source' => 'title', 'required' => true, 'max' => 180, 'list' => true],
                    ['name' => 'duration', 'label' => 'Durée', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'summary', 'label' => 'Résumé', 'type' => 'textarea', 'required' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => true],
                    ['name' => 'admission_conditions', 'label' => 'Conditions d’admission', 'type' => 'textarea', 'required' => true],
                    ['name' => 'career_outcomes', 'label' => 'Débouchés', 'type' => 'json_list', 'required' => true],
                    ...$this->homeFeaturedFields(),
                ],
            ],
            'staff' => [
                'title'          => 'Personnel',
                'singular'       => 'membre du personnel',
                'model'          => StaffModel::class,
                'permission'     => 'staff.manage',
                'publishedField' => 'is_published',
                'unique'         => ['slug'],
                'search'         => ['name', 'grade', 'specialty', 'role', 'email'],
                'orderBy'        => ['display_order' => 'ASC', 'id' => 'ASC'],
                'previewField'   => ['name' => 'biography', 'label' => 'Biographie'],
                'fields'         => [
                    ['name' => 'category', 'label' => 'Catégorie', 'type' => 'select', 'required' => true, 'options' => ['enseignant' => 'Enseignant-chercheur', 'administratif' => 'Personnel administratif'], 'list' => true],
                    ['name' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'slug', 'label' => 'Slug', 'type' => 'slug', 'source' => 'name', 'required' => true, 'max' => 180, 'list' => true],
                    ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'folder' => 'staff', 'nullable' => true],
                    ['name' => 'grade', 'label' => 'Grade', 'type' => 'text', 'nullable' => true, 'max' => 255],
                    ['name' => 'specialty', 'label' => 'Spécialité', 'type' => 'text', 'nullable' => true, 'max' => 255],
                    ['name' => 'role', 'label' => 'Rôle', 'type' => 'text', 'nullable' => true, 'max' => 255],
                    ['name' => 'email', 'label' => 'Adresse électronique', 'type' => 'email', 'nullable' => true, 'max' => 255],
                    ['name' => 'biography', 'label' => 'Biographie', 'type' => 'textarea', 'nullable' => true],
                    ...$this->homeFeaturedFields(),
                ],
            ],
            'laboratories' => [
                'title'          => 'Laboratoires',
                'singular'       => 'laboratoire',
                'model'          => LaboratoryModel::class,
                'permission'     => 'research.manage',
                'publishedField' => 'is_published',
                'unique'         => ['abbreviation', 'slug'],
                'search'         => ['abbreviation', 'name', 'description'],
                'orderBy'        => ['display_order' => 'ASC', 'id' => 'ASC'],
                'previewField'   => ['name' => 'description', 'label' => 'Description'],
                'fields'         => [
                    ['name' => 'abbreviation', 'label' => 'Sigle', 'type' => 'text', 'required' => true, 'max' => 40, 'list' => true],
                    ['name' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'slug', 'label' => 'Slug', 'type' => 'slug', 'source' => 'name', 'required' => true, 'max' => 180, 'list' => true],
                    ['name' => 'icon', 'label' => 'Icône', 'type' => 'icon', 'nullable' => true, 'options' => $this->iconOptions()],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => true],
                    ['name' => 'themes', 'label' => 'Thèmes de recherche', 'type' => 'json_list', 'required' => true],
                    ['name' => 'researcher_count', 'label' => 'Nombre de chercheurs', 'type' => 'integer', 'required' => true, 'list' => true],
                    ...$this->homeFeaturedFields(),
                ],
            ],
            'publications' => [
                'title'          => 'Publications scientifiques',
                'singular'       => 'publication',
                'model'          => PublicationModel::class,
                'permission'     => 'research.manage',
                'publishedField' => 'is_published',
                'search'         => ['title', 'authors', 'journal'],
                'orderBy'        => ['year' => 'DESC', 'display_order' => 'ASC'],
                'fields'         => [
                    ['name' => 'year', 'label' => 'Année', 'type' => 'integer', 'required' => true, 'list' => true],
                    ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true, 'max' => 500, 'list' => true],
                    ['name' => 'authors', 'label' => 'Auteurs', 'type' => 'textarea', 'required' => true],
                    ['name' => 'journal', 'label' => 'Revue', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'url', 'label' => 'Lien externe', 'type' => 'external_url', 'nullable' => true, 'max' => 500],
                    ...$this->orderedPublishedFields(),
                ],
            ],
            'research-projects' => [
                'title'          => 'Projets de recherche',
                'singular'       => 'projet de recherche',
                'model'          => ResearchProjectModel::class,
                'permission'     => 'research.manage',
                'publishedField' => 'is_published',
                'unique'         => ['code'],
                'search'         => ['code', 'title', 'description', 'funder'],
                'orderBy'        => ['display_order' => 'ASC', 'id' => 'ASC'],
                'previewField'   => ['name' => 'description', 'label' => 'Description'],
                'fields'         => [
                    ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'max' => 80, 'list' => true],
                    ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => true],
                    ['name' => 'funder', 'label' => 'Bailleur', 'type' => 'text', 'nullable' => true, 'max' => 255],
                    ['name' => 'period_start', 'label' => 'Année de début', 'type' => 'integer', 'nullable' => true, 'list' => true],
                    ['name' => 'period_end', 'label' => 'Année de fin', 'type' => 'integer', 'nullable' => true],
                    ['name' => 'icon', 'label' => 'Icône', 'type' => 'icon', 'nullable' => true, 'options' => $this->iconOptions()],
                    ...$this->orderedPublishedFields(),
                ],
            ],
            'timeline-items' => [
                'title'          => 'Historique',
                'singular'       => 'repère historique',
                'model'          => TimelineItemModel::class,
                'permission'     => 'pages.manage',
                'publishedField' => 'is_published',
                'search'         => ['title', 'description'],
                'orderBy'        => ['display_order' => 'ASC', 'year' => 'ASC'],
                'previewField'   => ['name' => 'description', 'label' => 'Description'],
                'fields'         => [
                    ['name' => 'year', 'label' => 'Année', 'type' => 'integer', 'required' => true, 'list' => true],
                    ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => true],
                    ...$this->orderedPublishedFields(),
                ],
            ],
            'alumni-profiles' => [
                'title'          => 'Profils alumni',
                'singular'       => 'profil alumni',
                'model'          => AlumniProfileModel::class,
                'permission'     => 'alumni.manage',
                'publishedField' => 'is_published',
                'unique'         => ['slug'],
                'search'         => ['name', 'role', 'organization', 'biography'],
                'orderBy'        => ['display_order' => 'ASC', 'id' => 'ASC'],
                'previewField'   => ['name' => 'biography', 'label' => 'Biographie'],
                'fields'         => [
                    ['name' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'slug', 'label' => 'Slug', 'type' => 'slug', 'source' => 'name', 'required' => true, 'max' => 180, 'list' => true],
                    ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'folder' => 'alumni', 'nullable' => true],
                    ['name' => 'promotion', 'label' => 'Promotion', 'type' => 'text', 'nullable' => true, 'max' => 80],
                    ['name' => 'role', 'label' => 'Fonction', 'type' => 'text', 'nullable' => true, 'max' => 255],
                    ['name' => 'organization', 'label' => 'Organisation', 'type' => 'text', 'nullable' => true, 'max' => 255],
                    ['name' => 'biography', 'label' => 'Biographie', 'type' => 'textarea', 'nullable' => true],
                    ...$this->orderedPublishedFields(),
                ],
            ],
            'testimonials' => [
                'title'          => 'Témoignages',
                'singular'       => 'témoignage',
                'model'          => TestimonialModel::class,
                'permission'     => 'alumni.manage',
                'publishedField' => 'is_published',
                'search'         => ['person_name', 'promotion', 'quote'],
                'orderBy'        => ['display_order' => 'ASC', 'id' => 'ASC'],
                'previewField'   => ['name' => 'quote', 'label' => 'Témoignage'],
                'fields'         => [
                    ['name' => 'alumni_profile_id', 'label' => 'Profil alumni lié', 'type' => 'relation', 'model' => AlumniProfileModel::class, 'labelField' => 'name', 'nullable' => true],
                    ['name' => 'person_name', 'label' => 'Nom affiché', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'folder' => 'testimonials', 'nullable' => true],
                    ['name' => 'promotion', 'label' => 'Promotion', 'type' => 'text', 'nullable' => true, 'max' => 80],
                    ['name' => 'quote', 'label' => 'Témoignage', 'type' => 'textarea', 'required' => true, 'list' => true],
                    ...$this->orderedPublishedFields(),
                ],
            ],
            'site-stats' => [
                'title'          => 'Statistiques',
                'singular'       => 'statistique',
                'model'          => SiteStatModel::class,
                'permission'     => 'home.manage',
                'publishedField' => 'is_published',
                'search'         => ['section', 'label'],
                'orderBy'        => ['display_order' => 'ASC', 'section' => 'ASC', 'id' => 'ASC'],
                'fields'         => [
                    ['name' => 'section', 'label' => 'Section', 'type' => 'text', 'required' => true, 'max' => 80, 'list' => true],
                    ['name' => 'label', 'label' => 'Libellé', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'value', 'label' => 'Valeur', 'type' => 'integer', 'required' => true, 'list' => true],
                    ['name' => 'suffix', 'label' => 'Suffixe', 'type' => 'text', 'nullable' => true, 'max' => 20],
                    ...$this->orderedPublishedFields(),
                ],
            ],
            'pages' => [
                'title'          => 'Pages modifiables',
                'singular'       => 'page',
                'model'          => PageModel::class,
                'permission'     => 'pages.manage',
                'publishedField' => 'is_published',
                'creationDisabled' => true,
                'deletionDisabled' => true,
                'unique'         => ['key', 'slug'],
                'search'         => ['key', 'title', 'content'],
                'orderBy'        => ['title' => 'ASC'],
                'sections'       => [
                    ['title' => 'Identification', 'fields' => ['key', 'title', 'slug']],
                    ['title' => 'Contenu de la page', 'fields' => ['content']],
                    ['title' => 'Référencement et publication', 'fields' => ['seo_title', 'seo_description', 'is_published']],
                ],
                'pageContentSchemas' => $this->pageContentSchemas(),
                'fields'         => [
                    ['name' => 'key', 'label' => 'Page', 'type' => 'readonly_select', 'required' => true, 'options' => $this->pageOptions(), 'list' => true],
                    ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'slug', 'label' => 'Slug', 'type' => 'slug', 'source' => 'title', 'required' => true, 'max' => 180, 'list' => true],
                    ['name' => 'content', 'label' => 'Contenu', 'type' => 'page_content', 'required' => true],
                    ['name' => 'seo_title', 'label' => 'Titre SEO', 'type' => 'text', 'nullable' => true, 'max' => 255],
                    ['name' => 'seo_description', 'label' => 'Description SEO', 'type' => 'textarea', 'nullable' => true],
                    ['name' => 'is_published', 'label' => 'Publié', 'type' => 'boolean', 'default' => 1, 'list' => true],
                ],
            ],
            'sites' => [
                'title'            => 'Sites facultaires',
                'singular'         => 'site facultaire',
                'model'            => SiteModel::class,
                'permission'       => 'sites.manage',
                'siteScoped'       => false,
                'creationDisabled' => true,
                'creationDisabledMessage' => 'Les nouveaux sites facultaires se créent à partir du dossier modèle, pas depuis cette administration.',
                'deletionDisabled' => true,
                'unique'           => ['identifier', 'slug'],
                'search'           => ['identifier', 'name', 'slug', 'hostnames'],
                'orderBy'          => ['name' => 'ASC'],
                'sections'         => [
                    ['title' => 'Identification', 'fields' => ['identifier', 'name', 'slug', 'status', 'default_locale']],
                    ['title' => 'Domaines', 'fields' => ['hostnames']],
                    ['title' => 'Identité visuelle', 'fields' => ['logo']],
                    ['title' => 'Coordonnées', 'fields' => ['contact_email', 'phone']],
                ],
                'defaults'         => [
                    'status'           => 'active',
                    'default_locale'   => 'fr',
                    'primary_color'    => '#0D9B49',
                    'secondary_color'  => '#0B6F38',
                    'theme'            => 'default',
                    'theme_config'     => '{"layout":"classic","hero_image":"assets/images/logo-placeholder.png"}',
                    'menu_config'      => '{"items":["faculte","formations","recherche","corps-enseignant","actualites","alumni","contact"]}',
                    'enabled_sections' => ['hero', 'statistics', 'about', 'programmes_preview', 'research_labs', 'news_preview', 'dean_message', 'staff_preview', 'custom_text', 'contact_cta'],
                ],
                'fields'           => [
                    ['name' => 'identifier', 'label' => 'Identifiant interne', 'type' => 'text', 'required' => true, 'max' => 80, 'pattern' => '/^[a-z0-9_.-]+$/', 'patternMessage' => 'L’identifiant contient uniquement minuscules, chiffres, points, tirets et underscores.', 'list' => true],
                    ['name' => 'name', 'label' => 'Nom du site', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true],
                    ['name' => 'slug', 'label' => 'Slug', 'type' => 'slug', 'source' => 'name', 'required' => true, 'max' => 120, 'list' => true],
                    ['name' => 'hostnames', 'label' => 'Domaines autorisés', 'type' => 'json_list', 'nullable' => true],
                    ['name' => 'status', 'label' => 'État', 'type' => 'select', 'required' => true, 'options' => ['active' => 'Actif', 'inactive' => 'Inactif'], 'default' => 'active', 'list' => true],
                    ['name' => 'default_locale', 'label' => 'Langue par défaut', 'type' => 'select', 'required' => true, 'options' => ['fr' => 'Français', 'en' => 'Anglais'], 'default' => 'fr'],
                    ['name' => 'logo', 'label' => 'Logo', 'type' => 'image', 'folder' => 'sites', 'nullable' => true],
                    ['name' => 'contact_email', 'label' => 'Adresse électronique', 'type' => 'email', 'nullable' => true, 'max' => 255],
                    ['name' => 'phone', 'label' => 'Téléphone', 'type' => 'text', 'nullable' => true, 'max' => 80],
                ],
            ],
            'settings' => [
                'title'            => 'Paramètres publics',
                'singular'         => 'paramètre public',
                'model'            => SettingModel::class,
                'permission'       => 'settings.manage',
                'creationDisabled' => true,
                'deletionDisabled' => true,
                'unique'           => ['key'],
                'search'           => ['key', 'value'],
                'orderBy'          => ['context' => 'ASC', 'key' => 'ASC'],
                'defaults'         => ['class' => 'App\\Settings\\Site'],
                'settingDefinitions' => $this->settingDefinitions(),
                'fields'           => [
                    ['name' => 'key', 'label' => 'Paramètre', 'type' => 'readonly_select', 'required' => true, 'options' => $this->settingOptions(), 'list' => true],
                    ['name' => 'value', 'label' => 'Valeur', 'type' => 'setting_value', 'nullable' => true, 'list' => true],
                ],
            ],
            'content-blocks' => [
                'title'          => 'Blocs de contenu',
                'singular'       => 'bloc de contenu',
                'model'          => ContentBlockModel::class,
                'permission'     => 'pages.manage',
                'publishedField' => 'is_published',
                'search'         => ['page_key', 'type', 'title', 'content'],
                'orderBy'        => ['page_key' => 'ASC', 'display_order' => 'ASC', 'id' => 'ASC'],
                'previewField'   => ['name' => 'content', 'label' => 'Contenu'],
                'sections'       => [
                    ['title' => 'Placement', 'fields' => ['page_key', 'type', 'display_order', 'is_published']],
                    ['title' => 'Contenu', 'fields' => ['title', 'content', 'settings']],
                ],
                'fields'         => [
                    ['name' => 'page_key', 'label' => 'Page', 'type' => 'select', 'required' => true, 'options' => $this->pageOptions(), 'list' => true],
                    ['name' => 'type', 'label' => 'Type de bloc', 'type' => 'select', 'required' => true, 'options' => $this->blockTypeOptions(), 'list' => true],
                    ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'nullable' => true, 'max' => 255, 'list' => true],
                    ['name' => 'content', 'label' => 'Contenu', 'type' => 'textarea', 'nullable' => true],
                    ['name' => 'settings', 'label' => 'Paramètres JSON', 'type' => 'json_text', 'nullable' => true, 'default' => '{}'],
                    ...$this->orderedPublishedFields(),
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $extra
     *
     * @return list<array<string, mixed>>
     */
    private function orderedPublishedFields(array $extra = []): array
    {
        return [
            ...$extra,
            ['name' => 'display_order', 'label' => 'Ordre d’affichage', 'type' => 'integer', 'required' => true, 'default' => 0, 'list' => true, 'managedByDrag' => true],
            ['name' => 'is_published', 'label' => 'Publié', 'type' => 'boolean', 'default' => 1, 'list' => true],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function homeFeaturedFields(): array
    {
        return [
            ['name' => 'display_order', 'label' => 'Ordre d’affichage', 'type' => 'integer', 'required' => true, 'default' => 0, 'list' => true, 'managedByDrag' => true],
            ['name' => 'featured_on_home', 'label' => 'Mis en avant sur l’accueil', 'type' => 'boolean', 'default' => 0, 'list' => true],
            ['name' => 'home_order', 'label' => 'Ordre sur l’accueil', 'type' => 'integer', 'nullable' => true, 'list' => true],
            ['name' => 'is_published', 'label' => 'Publié', 'type' => 'boolean', 'default' => 1, 'list' => true],
        ];
    }
}
