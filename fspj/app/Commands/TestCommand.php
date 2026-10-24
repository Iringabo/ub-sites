<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestCommand extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test';
    protected $description = 'Exécute la suite PHPUnit du projet.';

    public function run(array $params): int
    {
        chdir(ROOTPATH);

        $phpunit = ROOTPATH . 'vendor/bin/phpunit';

        if (! is_file($phpunit)) {
            CLI::error('PHPUnit est introuvable. Exécutez composer install.');

            return EXIT_ERROR;
        }

        $configuration = ROOTPATH . 'phpunit.dist.xml';
        $command       = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpunit);

        if (is_file($configuration)) {
            $command .= ' --configuration ' . escapeshellarg($configuration);
        }

        foreach ($params as $param) {
            $command .= ' ' . escapeshellarg((string) $param);
        }

        passthru($command, $exitCode);

        return (int) $exitCode;
    }
}
