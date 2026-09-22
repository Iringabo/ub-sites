<?php

namespace App\Commands;

use App\Models\SiteModel;
use App\Services\FacultyDemoDataService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Fill faculty sites with rich demo content and site-owned images only.
 *
 * Prefer running from the superadmin instance so app.uploadMirrors copies
 * files into each faculty public/uploads/sites/{slug}/ folder.
 */
class SeedFacultyDemoData extends BaseCommand
{
    protected $group       = 'Administration';
    protected $name        = 'site:seed-demo';
    protected $description = 'Remplit chaque faculté avec un contenu démo riche et des images propres au site (uploads/sites/{slug}/).';
    protected $usage       = 'site:seed-demo [--slug fseg] --force';
    protected $options     = [
        '--slug'  => 'Limiter à une faculté (slug). Par défaut : toutes les facultés actives.',
        '--force' => 'Obligatoire : remplace le contenu démo existant du site.',
    ];

    public function run(array $params): int
    {
        $force = CLI::getOption('force') !== null;
        if (! $force) {
            CLI::error('Ajoutez --force pour confirmer le remplacement du contenu démo.');

            return EXIT_ERROR;
        }

        $slugFilter = CLI::getOption('slug');
        $slugFilter = is_string($slugFilter) && $slugFilter !== '' ? strtolower(trim($slugFilter)) : null;

        $sites = model(SiteModel::class, false)
            ->where('status', 'active')
            ->orderBy('id', 'ASC')
            ->findAll();

        if ($slugFilter !== null) {
            $sites = array_values(array_filter(
                $sites,
                static fn ($site): bool => strtolower((string) $site->slug) === $slugFilter,
            ));
        }

        if ($sites === []) {
            CLI::error('Aucune faculté à peupler.');

            return EXIT_ERROR;
        }

        $service = new FacultyDemoDataService();
        $ok = 0;

        foreach ($sites as $site) {
            $slug = (string) $site->slug;
            CLI::write('→ Seed démo : ' . $slug . ' (#' . $site->id . ')…', 'yellow');

            try {
                $result = $service->seedSite((int) $site->id, true);
                CLI::write(
                    '  OK — ' . $result['images'] . ' images, modules : ' . implode(', ', $result['modules']),
                    'green',
                );
                $ok++;
            } catch (Throwable $e) {
                CLI::error('  Échec ' . $slug . ' : ' . $e->getMessage());

                return EXIT_ERROR;
            }
        }

        CLI::newLine();
        CLI::write("Terminé : {$ok} faculté(s) peuplée(s).", 'green');
        CLI::write('Vérifiez que chaque dossier public/uploads/sites/ ne contient que son propre slug.', 'white');

        return EXIT_SUCCESS;
    }
}
