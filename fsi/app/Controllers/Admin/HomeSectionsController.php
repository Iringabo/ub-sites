<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SiteModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Faculty homepage section visibility + display order (drag-drop).
 */
class HomeSectionsController extends BaseController
{
    /**
     * @return list<string>
     */
    public static function availableSections(): array
    {
        return [
            'hero',
            'statistics',
            'about',
            'highlights',
            'dean_message',
            'programmes_preview',
            'research_labs',
            'staff_preview',
            'news_preview',
            'contact_cta',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sectionLabels(): array
    {
        return [
            'hero'                => 'Héros (carrousel)',
            'statistics'          => 'Chiffres clés',
            'about'               => 'Présentation',
            'highlights'          => 'Points forts (dans présentation)',
            'dean_message'        => 'Mot du doyen',
            'programmes_preview'  => 'Aperçu des formations',
            'research_labs'       => 'Recherche & laboratoires',
            'staff_preview'       => 'Aperçu du personnel',
            'news_preview'        => 'Actualités',
            'contact_cta'         => 'Appel à l’action contact',
        ];
    }

    public function index(): string|RedirectResponse
    {
        if ($redirect = $this->guardHomeSections()) {
            return $redirect;
        }

        $site = service('siteResolver')->activeSite();
        $enabled = $this->normalizeEnabled($site->enabled_sections ?? null);
        if ($enabled === []) {
            $enabled = self::availableSections();
        }

        return view('admin/home_sections/index', [
            'title'       => 'Sections & ordre | Administration',
            'activeAdmin' => 'home-sections',
            'labels'      => self::sectionLabels(),
            'available'   => self::availableSections(),
            'enabled'     => $enabled,
            'site'        => $site,
        ]);
    }

    public function save(): RedirectResponse
    {
        if ($redirect = $this->guardHomeSections()) {
            return $redirect;
        }

        $order = $this->request->getPost('order');
        if (is_string($order)) {
            $decoded = json_decode($order, true);
            $order = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($order)) {
            $order = [];
        }

        $checked = $this->request->getPost('sections');
        if (! is_array($checked)) {
            $checked = [];
        }
        $checked = array_map('strval', $checked);

        $available = self::availableSections();
        $normalized = [];
        foreach ($order as $key) {
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key)) ?: '';
            if ($key === '' || ! in_array($key, $available, true) || ! in_array($key, $checked, true)) {
                continue;
            }
            if (! in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        // Append any checked keys missing from order.
        foreach ($checked as $key) {
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key)) ?: '';
            if ($key !== '' && in_array($key, $available, true) && ! in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        $siteId = service('siteResolver')->activeSiteId();
        model(SiteModel::class, false)->update($siteId, [
            'enabled_sections' => $normalized,
        ]);

        return redirect()->to(site_url('admin/home-sections'))->with('message', 'Les sections de l’accueil ont été enregistrées.');
    }

    private function guardHomeSections(): ?RedirectResponse
    {
        $user = auth()->user();
        if ($user === null || ! $user->can('home.manage')) {
            return redirect()->to('/admin')->with('error', 'Permission insuffisante.');
        }

        if (service('adminAccess')->isCentralAdminHost() && ! service('siteResolver')->hasExplicitAdminSiteSelection()) {
            return redirect()->to('/admin')->with('error', 'Choisissez d’abord une faculté à modifier.');
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function normalizeEnabled(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($value)) {
            return [];
        }

        $available = self::availableSections();
        $out = [];
        foreach ($value as $entry) {
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) $entry))) ?: '';
            if ($key !== '' && in_array($key, $available, true) && ! in_array($key, $out, true)) {
                $out[] = $key;
            }
        }

        return $out;
    }
}
