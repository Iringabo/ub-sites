<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * « Paramètres du site » : informations affichées sur toutes les pages,
 * réparties en petites pages (une par thème) pour être faciles à trouver.
 */
class SettingsController extends BaseController
{
    /**
     * @var array<string, array{label: string, icon: string, description: string, contexts: list<string>, keywords: list<string>}>
     */
    public const PAGES = [
        'identity' => [
            'label'       => 'Identité & logo',
            'icon'        => 'bi-building',
            'description' => 'Nom de la faculté, sigle, université et logo affichés dans le menu et le pied de page.',
            'contexts'    => ['institution', 'assets'],
            'keywords'    => ['nom de la faculté', 'sigle', 'université', 'logo', 'institution'],
        ],
        'contact' => [
            'label'       => 'Coordonnées',
            'icon'        => 'bi-telephone',
            'description' => 'Adresse, téléphone, e-mail et horaires affichés sur la page Contact et dans le pied de page.',
            'contexts'    => ['contact'],
            'keywords'    => ['adresse', 'téléphone', 'e-mail', 'email', 'horaires', 'commune', 'province', 'pays'],
        ],
        'social' => [
            'label'       => 'Réseaux sociaux',
            'icon'        => 'bi-share',
            'description' => 'Liens vers les pages Facebook, X, LinkedIn et YouTube affichés dans le pied de page.',
            'contexts'    => ['social'],
            'keywords'    => ['facebook', 'twitter', 'x', 'linkedin', 'youtube'],
        ],
        'footer' => [
            'label'       => 'Pied de page',
            'icon'        => 'bi-layout-text-window-reverse',
            'description' => 'Texte et mention de copyright en bas de toutes les pages.',
            'contexts'    => ['footer'],
            'keywords'    => ['copyright', 'bas de page', 'mentions'],
        ],
        'seo' => [
            'label'       => 'Référencement & partage',
            'icon'        => 'bi-search',
            'description' => 'Titre et description par défaut dans Google, couleur du navigateur et image affichée lors d’un partage.',
            'contexts'    => ['seo'],
            'keywords'    => ['google', 'seo', 'référencement', 'couleur du thème', 'image de partage', 'réseaux'],
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const CONTEXT_TITLES = [
        'institution' => 'Institution',
        'assets'      => 'Logo',
        'contact'     => 'Coordonnées',
        'social'      => 'Réseaux sociaux',
        'footer'      => 'Pied de page',
        'seo'         => 'Référencement & partage',
    ];

    public function index(string $page = 'identity'): string|ResponseInterface
    {
        if (! isset(self::PAGES[$page])) {
            return redirect()->to('/admin/settings/identity');
        }

        $meta = self::PAGES[$page];
        $siteId = (int) service('siteResolver')->activeSiteId();

        $rows = model(SettingModel::class, false)
            ->forSite($siteId)
            ->findAll();

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[(string) $row->key] = (string) $row->value;
        }

        $values = [];
        foreach ($this->definitionsFor($meta['contexts']) as $key => $definition) {
            $raw = $byKey[$key] ?? null;
            if (($definition['type'] ?? '') === 'social_links') {
                $decoded = is_string($raw) ? json_decode($raw, true) : [];
                $values[$key] = is_array($decoded) ? $decoded : [];
                continue;
            }
            $values[$key] = $raw;
        }

        $groups = [];
        foreach ($meta['contexts'] as $context) {
            $fields = [];
            foreach ($this->definitionsFor([$context]) as $key => $item) {
                $fields[] = [
                    'key'     => $key,
                    'label'   => $item['label'],
                    'type'    => $item['type'],
                    'value'   => $values[$key] ?? '',
                    'options' => $item['options'] ?? [],
                ];
            }

            if ($fields !== []) {
                $groups[] = ['title' => self::CONTEXT_TITLES[$context] ?? $context, 'context' => $context, 'fields' => $fields];
            }
        }

        return view('admin/settings/overview', [
            'title'          => $meta['label'] . ' | Administration',
            'activeAdmin'    => 'settings/' . $page,
            'isCentralAdmin' => service('adminAccess')->isCentralAdminHost(),
            'page'           => $page,
            'pageMeta'       => $meta,
            'groups'         => $groups,
            'showGroupTitles' => count($groups) > 1,
        ]);
    }

    public function update(string $page = 'identity'): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/admin')->with('error', 'Accès refusé.');
        }

        if (! isset(self::PAGES[$page])) {
            return redirect()->to('/admin/settings/identity');
        }

        $definitions = $this->definitionsFor(self::PAGES[$page]['contexts']);
        $siteId = (int) service('siteResolver')->activeSiteId();
        $model = model(SettingModel::class);
        $errors = [];
        $hasError = false;

        foreach ($definitions as $key => $definition) {
            $inputName = 'setting_' . $this->inputKey($key);
            $type = $definition['type'] ?? 'string';

            if ($type === 'path') {
                continue;
            }

            if ($type === 'social_links') {
                $links = [
                    'facebook'  => trim((string) $this->request->getPost($inputName . '_facebook')),
                    'twitter'   => trim((string) $this->request->getPost($inputName . '_twitter')),
                    'linkedin'  => trim((string) $this->request->getPost($inputName . '_linkedin')),
                    'youtube'   => trim((string) $this->request->getPost($inputName . '_youtube')),
                ];
                foreach ($links as $network => $url) {
                    if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                        $errors[$key] = $definition['label'] . ' (' . $network . ') doit être une URL valide.';
                        $hasError = true;
                        break;
                    }
                }
                if (! isset($errors[$key])) {
                    $encoded = json_encode(array_filter($links, static fn (string $url): bool => $url !== ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $this->upsert($model, $siteId, $key, $encoded === false ? '{}' : $encoded, 'json', $definition['context'], $errors, $hasError);
                }
                continue;
            }

            $raw = trim((string) $this->request->getPost($inputName));

            if ($type === 'email' && $raw !== '' && ! filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                $errors[$key] = $definition['label'] . ' doit être une adresse électronique valide.';
                $hasError = true;
                continue;
            }

            if ($type === 'color' && $raw !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $raw) !== 1) {
                $errors[$key] = $definition['label'] . ' doit utiliser le format #RRGGBB.';
                $hasError = true;
                continue;
            }

            if ($type === 'select') {
                $options = $definition['options'] ?? [];
                if ($raw === '') {
                    $raw = array_key_first($options) ?? '';
                }
                if ($raw === '' || ! array_key_exists($raw, $options)) {
                    $errors[$key] = $definition['label'] . ' contient une valeur non autorisée.';
                    $hasError = true;
                    continue;
                }
            }

            $this->upsert($model, $siteId, $key, $raw, $type === 'select' ? 'string' : $type, $definition['context'], $errors, $hasError);
        }

        foreach ($definitions as $key => $definition) {
            if (($definition['type'] ?? '') !== 'path') {
                continue;
            }

            $file = $this->request->getFile('setting_file_' . $this->inputKey($key));

            if (! service('mediaService')->hasFile($file)) {
                continue;
            }

            $error = null;
            $path = service('mediaService')->storePublicImage($file, 'settings', $error);

            if ($path === null) {
                $errors[$key] = $error ?? 'L’image téléversée est invalide.';
                $hasError = true;
                continue;
            }

            $this->upsert($model, $siteId, $key, $path, 'path', $definition['context'], $errors, $hasError);
        }

        if ($hasError) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        service('settingsService')->reset();

        return redirect()->to('/admin/settings/' . $page)->with('message', '« ' . self::PAGES[$page]['label'] . ' » a été enregistré.');
    }

    /**
     * @param list<string> $contexts
     *
     * @return array<string, array<string, mixed>>
     */
    private function definitionsFor(array $contexts): array
    {
        return array_filter(
            $this->definitions(),
            static fn (array $definition): bool => in_array($definition['context'], $contexts, true),
        );
    }

    /**
     * @param array<string, string> $errors
     */
    private function upsert(SettingModel $model, int $siteId, string $key, string $value, string $type, string $context, array &$errors, bool &$hasError): void
    {
        $existing = $model->forSite($siteId)->where('key', $key)->first();

        try {
            if ($existing === null) {
                $saved = $model->skipValidation(true)->insert([
                    'site_id' => $siteId,
                    'class'   => 'App\\Settings\\Site',
                    'key'     => $key,
                    'value'   => $value,
                    'type'    => $type,
                    'context' => $context,
                ]);
            } else {
                $saved = $model->skipValidation(true)->update((int) $existing->id, ['value' => $value]);
            }

            if ($saved === false) {
                $hasError = true;
                $errors[$key] = 'L’enregistrement de ce paramètre a échoué.';
                log_message('error', 'Settings upsert failed for {0}', [$key]);
            }
        } catch (Throwable $exception) {
            $hasError = true;
            $errors[$key] = 'L’enregistrement de ce paramètre a échoué.';
            log_message('error', 'Settings upsert failed for {0}: {1}', [$key, $exception->getMessage()]);
        } finally {
            $model->skipValidation(false);
        }
    }

    /**
     * Field labels of one settings page, used as search keywords in the admin menu.
     *
     * @return list<string>
     */
    public static function fieldLabels(string $page): array
    {
        $contexts = self::PAGES[$page]['contexts'] ?? [];

        return array_values(array_map(
            static fn (array $definition): string => $definition['label'],
            array_filter(self::definitions(), static fn (array $definition): bool => in_array($definition['context'], $contexts, true)),
        ));
    }

    /**
     * @return array<string, array{label: string, type: string, context: string}>
     */
    private static function definitions(): array
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
            'social.links'             => ['label' => 'Liens des réseaux sociaux', 'type' => 'social_links', 'context' => 'social'],
            'footer.text'              => ['label' => 'Texte du pied de page', 'type' => 'text', 'context' => 'footer'],
            'footer.copyright'         => ['label' => 'Copyright', 'type' => 'string', 'context' => 'footer'],
            'assets.logo'              => ['label' => 'Logo', 'type' => 'path', 'context' => 'assets'],
            'seo.default_title'        => ['label' => 'Titre SEO par défaut', 'type' => 'string', 'context' => 'seo'],
            'seo.default_description'  => ['label' => 'Description SEO par défaut', 'type' => 'text', 'context' => 'seo'],
            'seo.theme_color'          => ['label' => 'Couleur du thème', 'type' => 'color', 'context' => 'seo'],
            'seo.og_image'             => ['label' => 'Image de partage par défaut', 'type' => 'path', 'context' => 'seo'],
        ];
    }

    private function inputKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_]+/', '_', $key) ?: $key;
    }
}
