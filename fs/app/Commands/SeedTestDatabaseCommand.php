<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use Config\Database as DatabaseConfig;

class SeedTestDatabaseCommand extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'testdb:seed';
    protected $description = 'Exécute un seeder sur la base dédiée aux tests.';
    protected $usage       = 'testdb:seed [seeder_name]';

    public function run(array $params): int
    {
        $seederName = (string) ($params[0] ?? 'TemplateStarterSeeder');
        $config = config(DatabaseConfig::class);
        $previousGroup = $config->defaultGroup;
        $config->defaultGroup = 'tests';

        service('siteResolver')->reset();
        service('settingsService')->reset();
        service('contentTranslationService')->reset();

        try {
            $seeder = new Seeder($config, db_connect('tests'));
            $seeder->call($seederName);
        } finally {
            $config->defaultGroup = $previousGroup;
        }

        CLI::write('Base de test seedée.', 'green');

        return EXIT_SUCCESS;
    }
}
