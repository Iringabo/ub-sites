<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\MigrationRunner;
use Config\Migrations;

class MigrateTestDatabaseCommand extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'testdb:migrate';
    protected $description = 'Exécute les migrations sur la base dédiée aux tests.';

    public function run(array $params): int
    {
        $runner = new MigrationRunner(config(Migrations::class), 'tests');
        $runner->clearCliMessages();
        $runner->setNamespace(null);

        if (! $runner->latest('tests')) {
            CLI::error('Les migrations de la base de test ont échoué.');

            return EXIT_ERROR;
        }

        foreach ($runner->getCliMessages() as $message) {
            CLI::write($message);
        }

        CLI::write('Base de test migrée.', 'green');

        return EXIT_SUCCESS;
    }
}
