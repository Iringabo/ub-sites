<?php

namespace App\Database\Seeds;

use App\Models\SiteModel;
use App\Services\FacultySiteProvisioningService;
use CodeIgniter\Database\Seeder;

/**
 * Seeder neutre du modèle : garantit qu'il existe au moins un site
 * facultaire et lui génère son contenu de départ (« Texte à remplacer »)
 * via le service de provisionnement. Aucune donnée réelle n'est figée ici.
 */
class TemplateStarterSeeder extends Seeder
{
    public function run(): void
    {
        $service = new FacultySiteProvisioningService($this->db);

        $sites = model(SiteModel::class, false, $this->db)->findAll();

        if ($sites === []) {
            $slug = strtolower(trim((string) env('app.siteSlug', 'faculte')));
            $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?: 'faculte';

            $service->createFaculty([
                'identifier' => $slug,
                'name'       => 'Faculté de démonstration',
                'slug'       => $slug,
            ]);

            return;
        }

        foreach ($sites as $site) {
            $hasHomeContent = (int) $this->db
                ->table('home_content')
                ->where('site_id', (int) $site->id)
                ->countAllResults() > 0;

            if (! $hasHomeContent) {
                $service->provisionStarterContent((int) $site->id);
            }
        }
    }
}
