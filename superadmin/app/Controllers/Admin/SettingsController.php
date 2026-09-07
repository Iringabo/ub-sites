<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Écran unique « Coordonnées & identité » : tous les paramètres publics
 * sont regroupés par contexte et modifiés en une seule page.
 */
class SettingsController extends BaseController
{
    /**
     * @return array<string, array<string, array{label: string, type: string, context: string}>>
     */
    private function definitionsByContext(): array
    {
        $grouped = [];

        foreach ($this->definitions() as $key => $definition) {
            $grouped[$definition['context']][] = ['key' => $key] + $definition;
        }

        return $grouped;
    }

    /**
     * @return array<int, array{title: string, context: string, keys: list<string>}>
     */
    private function contextOrder(): array
    {
        return [
            ['title' => 'Institution', 'context' => 'institution'],
            ['title' => 'Coordonnées', 'context' => 'contact'],
            ['title' => 'Pied de page', 'context' => 'footer'],
            ['title' => 'Identité visuelle', 'context' => 'assets'],
            ['title' => 'Référencement (SEO)', 'context' => 'seo'],
            ['title' => 'Accueil', 'context' => 'home'],
        ];
    }

    public function index(): string|ResponseInterface
    {
        $siteId = (int) service('siteResolver')->activeSiteId();

        $rows = model(SettingModel::class, false)
            ->forSite($siteId)
            ->findAll();

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[(string) $row->key] = (string) $row->value;
        }

        $values = [];
        foreach ($this->definitions() as $key => $definition) {
            $values[$key] = $byKey[$key] ?? null;
        }

        $groups = [];
        foreach ($this->contextOrder() as $group) {
            $context = $group['context'];
            if (! isset($this->definitionsByContext()[$context])) {
                continue;
            }

            $fields = [];
            foreach ($this->definitionsByContext()[$context] as $item) {
                $fields[] = [
                    'key'  => $item['key'],
                    'label' => $item['label'],
                    'type' => $item['type'],
                    'value' => $values[$item['key']] ?? '',
                ];
            }

            $groups[] = ['title' => $group['title'], 'context' => $context, 'fields' => $fields];
        }

        return view('admin/settings/overview', [
            'title'       => 'Coordonnées & identité | Administration',
            'activeAdmin' => 'settings/global',
            'isCentralAdmin' => service('adminAccess')->isCentralAdminHost(),
            'groups'      => $groups,
        ]);
    }

    public function update(): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/admin')->with('error', 'Accès refusé.');
        }

        $siteId = (int) service('siteResolver')->activeSiteId();
        $model = model(SettingModel::class, false);
        $errors = [];
        $hasError = false;

        foreach ($this->definitions() as $key => $definition) {
            $inputName = 'setting_' . $this->inputKey($key);
            $type = $definition['type'] ?? 'string';

            if ($type === 'path') {
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

            if ($key === 'home.hero_overlay_opacity' && $raw !== '') {
                $opacity = filter_var($raw, FILTER_VALIDATE_FLOAT);
                if ($opacity === false || $opacity < 0.45 || $opacity > 0.95) {
                    $errors[$key] = 'L’opacité doit être comprise entre 0,45 et 0,95.';
                    $hasError = true;
                    continue;
                }
            }

            $this->upsert($model, $siteId, $key, $raw, $type, $definition['context']);
        }

        foreach ([['key' => 'assets.logo', 'file' => 'setting_file_assets_logo'], ['key' => 'seo.og_image', 'file' => 'setting_file_og_image']] as $pathDef) {
            $key = $pathDef['key'];
            $file = $this->request->getFile($pathDef['file']);

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

            $this->upsert($model, $siteId, $key, $path, 'path', $this->definitions()[$key]['context']);
        }

        if ($hasError) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        service('settingsService')->reset();

        return redirect()->to('/admin/settings/global')->with('message', 'Les paramètres ont été enregistrés.');
    }

    /**
     * @param array<string, string> $definition
     */
    private function upsert(SettingModel $model, int $siteId, string $key, string $value, string $type, string $context): void
    {
        $existing = $model->forSite($siteId)->where('key', $key)->first();

        try {
            if ($existing === null) {
                $model->skipValidation(true)->insert([
                    'site_id' => $siteId,
                    'class'   => 'App\\Settings\\Site',
                    'key'     => $key,
                    'value'   => $value,
                    'type'    => $type,
                    'context' => $context,
                ]);
            } else {
                $model->skipValidation(true)->update((int) $existing->id, ['value' => $value]);
            }
            $model->skipValidation(false);
        } catch (Throwable) {
            $model->skipValidation(false);
        }
    }

    /**
     * @return array<string, array{label: string, type: string, context: string}>
     */
    private function definitions(): array
    {
        return [
            'institution.faculty_name' => ['label' => 'Nom complet de la faculté', 'type' => 'string', 'context' => 'institution'],
            'institution.short_name'   => ['label' => 'Sigle de la faculté', 'type' => 'string', 'context' => 'institution'],
            'institution.university'   => ['label' => 'Université', 'type' => 'string', 'context' => 'institution'],
            'contact.address'          => ['label' => 'Adresse', 'type' => 'string', 'context' => 'contact'],
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
            'home.hero_overlay_opacity' => ['label' => 'Opacité du voile du carrousel', 'type' => 'string', 'context' => 'home'],
        ];
    }

    private function inputKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_]+/', '_', $key) ?: $key;
    }
}
