<?php

namespace App\Commands;

use App\Services\ProductionReadinessService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ProductionCheck extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'app:production-check';
    protected $description = 'Vérifie les réglages essentiels avant une mise en production.';
    protected $usage       = 'app:production-check [--strict]';
    protected $options     = [
        '--strict' => 'Traiter les avertissements comme bloquants.',
    ];

    public function run(array $params): int
    {
        $strict = CLI::getOption('strict') !== null;
        $service = new ProductionReadinessService();
        $checks = $service->checks();

        foreach ($checks as $check) {
            $color = match ($check['status']) {
                'ok'      => 'green',
                'warning' => 'yellow',
                default   => 'red',
            };

            CLI::write(sprintf('[%s] %s - %s', strtoupper($check['status']), $check['label'], $check['message']), $color);
        }

        CLI::newLine();
        CLI::write($service->summary($checks), $service->hasBlockingIssues($checks, $strict) ? 'red' : 'green');

        if ($service->hasBlockingIssues($checks, $strict)) {
            CLI::error($strict
                ? 'La configuration n’est pas prête pour la production stricte.'
                : 'La configuration contient des erreurs bloquantes pour la production.');

            return EXIT_ERROR;
        }

        CLI::write('Configuration prête pour une vérification finale de production.', 'green');

        return EXIT_SUCCESS;
    }
}
