<?php

namespace App\Commands;

use App\Services\FacultySiteProvisioningService;
use App\Models\SiteModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Companion to the "copy the folder" step of the multi-folder deployment
 * (see docs/MULTI_FOLDER_DEPLOYMENT.md). This command does the *database*
 * half of adding a new faculty: it inserts the `sites` row and provisions
 * editable starter content, exactly like clicking "Nouveau site" in
 * /admin/sites, but scriptable from the machine you're deploying on.
 *
 * Run this once per new faculty, from ANY instance (they all share the same
 * database), then copy the faculty folder and point its .env at the
 * resulting slug.
 */
class CreateFacultySite extends BaseCommand
{
    protected $group       = 'Administration';
    protected $name        = 'site:create';
    protected $description = "Crée une nouvelle faculté (ligne 'sites' + contenu de démarrage éditable) dans la base partagée.";
    protected $usage       = 'site:create --identifier <fsi> --slug <fsi> --name "Faculté des Sciences et Ingénierie"';
    protected $options     = [
        '--identifier' => 'Identifiant court unique (ex: fsi). Utilisé aussi comme app.siteSlug du dossier copié.',
        '--slug'       => 'Slug unique pour les URLs (ex: fsi). Par défaut identique à --identifier.',
        '--name'       => 'Nom complet affiché de la faculté.',
        '--locale'     => 'Langue par défaut (fr ou en). Par défaut: fr.',
    ];

    public function run(array $params): int
    {
        $identifier = $this->optionString('identifier') ?? CLI::prompt(
            'Identifiant court (ex: fsi)',
            null,
            'required|max_length[80]|regex_match[/^[a-z0-9_.-]+$/]'
        );
        $slug   = $this->optionString('slug') ?? $identifier;
        $name   = $this->optionString('name') ?? CLI::prompt('Nom complet de la faculté', null, 'required|max_length[255]');
        $locale = $this->optionString('locale') ?? 'fr';

        $service = new FacultySiteProvisioningService();
        $siteModel = model(SiteModel::class, false);

        try {
            $site = $siteModel->where('identifier', $identifier)
                ->orWhere('slug', $slug)
                ->first();

            if ($site !== null) {
                $siteId = (int) $site->id;
                $siteModel->update($siteId, [
                    'identifier'     => $identifier,
                    'slug'           => $slug,
                    'name'           => $name,
                    'default_locale' => in_array($locale, ['fr', 'en'], true) ? $locale : 'fr',
                    'hostnames'      => [$slug . '.test'],
                    'status'         => 'active',
                ]);
                $service->provisionStarterContent($siteId);
            } else {
                $siteId = $service->createFaculty([
                    'identifier'     => $identifier,
                    'slug'           => $slug,
                    'name'           => $name,
                    'default_locale' => in_array($locale, ['fr', 'en'], true) ? $locale : 'fr',
                ]);
            }
        } catch (\Throwable $exception) {
            CLI::error('Échec de la création : ' . $exception->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('Faculté créée avec succès (site_id = ' . $siteId . ').', 'green');
        CLI::newLine();
        CLI::write('Prochaine étape : copier le dossier modèle vers un nouveau dossier pour cette faculté,');
        CLI::write('puis, dans le .env de la copie, régler :');
        CLI::write('  app.siteSlug = ' . $slug, 'yellow');
        CLI::write('(mêmes identifiants de base de données que les autres dossiers).');
        CLI::newLine();
        CLI::write('Voir docs/MULTI_FOLDER_DEPLOYMENT.md ou lancer :', 'white');
        CLI::write('  ./scripts/new-faculty-instance.sh ' . $slug . ' "' . $name . '" /chemin/vers/' . $slug, 'yellow');

        return EXIT_SUCCESS;
    }

    private function optionString(string $name): ?string
    {
        $value = CLI::getOption($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
