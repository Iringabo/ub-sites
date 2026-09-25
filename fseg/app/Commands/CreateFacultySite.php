<?php

namespace App\Commands;

use App\Services\FacultySiteProvisioningService;
use App\Models\SiteModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Database half of adding a faculty (see docs/CREER_UN_SITE.md): inserts the
 * `sites` row and provisions editable starter content. There is no "Nouveau
 * site" screen anymore — run this from the copied folder after migrate --all.
 *
 * All instances share the same database. Prefer running site:create from the
 * new faculty folder or from template/, then create the first admin from
 * superadmin Comptes.
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
        '--hostname'   => 'Hôte public de cette faculté (ex. fseg.ub.edu.bi). Plusieurs hôtes : liste séparée par des virgules. Sinon, app.publicHostPattern dans .env.',
    ];

    public function run(array $params): int
    {
        $identifier = $this->optionString('identifier', $params) ?? CLI::prompt(
            'Identifiant court (ex: fsi)',
            null,
            'required|max_length[80]|regex_match[/^[a-z0-9_.-]+$/]'
        );
        $slug   = $this->optionString('slug', $params) ?? $identifier;
        $name   = $this->optionString('name', $params) ?? CLI::prompt('Nom complet de la faculté', null, 'required|max_length[255]');
        $locale = $this->optionString('locale', $params) ?? 'fr';

        $service = new FacultySiteProvisioningService();
        $siteModel = model(SiteModel::class, false);
        $hostnames = $this->hostnamesFromOption($params);
        if ($hostnames === []) {
            $hostnames = $service->hostnamesForSlug($slug);
        }

        try {
            $site = $siteModel->where('identifier', $identifier)
                ->orWhere('slug', $slug)
                ->first();

            if ($site !== null) {
                $siteId = (int) $site->id;
                $update = [
                    'identifier'     => $identifier,
                    'slug'           => $slug,
                    'name'           => $name,
                    'default_locale' => in_array($locale, ['fr', 'en'], true) ? $locale : 'fr',
                    'status'         => 'active',
                ];
                if ($hostnames !== []) {
                    $update['hostnames'] = $hostnames;
                }
                // Fresh model: a previous where()/orWhere() on $siteModel must not
                // stick to this update. Skip re-validation of identifier/slug uniqueness
                // — those values are unchanged and is_unique can fail on update.
                $updater = model(SiteModel::class, false);
                $updater->skipValidation(true);
                if ($updater->update($siteId, $update) === false) {
                    $messages = $updater->errors();
                    throw new \RuntimeException(
                        'La mise à jour de la faculté a échoué'
                        . ($messages !== [] ? ' : ' . implode(' ', $messages) : '.'),
                    );
                }
                $updater->skipValidation(false);
                $service->provisionStarterContent($siteId);
            } else {
                $payload = [
                    'identifier'     => $identifier,
                    'slug'           => $slug,
                    'name'           => $name,
                    'default_locale' => in_array($locale, ['fr', 'en'], true) ? $locale : 'fr',
                ];
                if ($hostnames !== []) {
                    $payload['hostnames'] = $hostnames;
                }
                $siteId = $service->createFaculty($payload);
            }
        } catch (\Throwable $exception) {
            CLI::error('Échec de la création : ' . $exception->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('Faculté créée avec succès (site_id = ' . $siteId . ').', 'green');
        if ($hostnames !== []) {
            CLI::write('Hôtes publics : ' . implode(', ', $hostnames), 'green');
        } else {
            CLI::write('Aucun hôte public enregistré. Renseignez app.publicHostPattern ou --hostname.', 'yellow');
        }
        CLI::newLine();
        CLI::write('Prochaine étape : copier le dossier modèle vers un nouveau dossier pour cette faculté,');
        CLI::write('puis, dans le .env de la copie, régler :');
        CLI::write('  app.siteSlug = ' . $slug, 'yellow');
        CLI::write('(mêmes identifiants de base de données que les autres dossiers).');
        CLI::newLine();
        CLI::write('Voir docs/CREER_UN_SITE.md. Copie optionnelle :', 'white');
        CLI::write('  ./scripts/new-faculty-instance.sh ' . $slug . ' "' . $name . '" /chemin/vers/' . $slug, 'yellow');

        return EXIT_SUCCESS;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<string>
     */
    private function hostnamesFromOption(array $params = []): array
    {
        $raw = $this->optionString('hostname', $params);
        if ($raw === null) {
            return [];
        }

        $hosts = [];
        foreach (preg_split('/[\s,]+/', $raw) ?: [] as $part) {
            $host = strtolower(trim((string) $part));
            if ($host !== '' && ! in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function optionString(string $name, array $params = []): ?string
    {
        $value = $params[$name] ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $value = CLI::getOption($name);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
