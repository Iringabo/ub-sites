<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

// PHPUnit applies <env force="true"> with putenv(). Names that contain a
// dot, such as app.centralAdminMode, do not survive that call: getenv()
// returns false, and DotEnv then replaces the value with the instance .env.
// Re-apply the suite settings as real strings so every folder, including
// superadmin, runs the faculty-site cases the suite declares.
foreach ([
    'app.centralAdminMode' => 'false',
    'app.siteSlug'         => 'fseg',
] as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name]    = $value;
    $_SERVER[$name] = $value;
}

resetTestDatabase();

/**
 * Recreates the dedicated test database before each PHPUnit process so
 * repeated runs always start from a clean schema state.
 */
function resetTestDatabase(): void
{
    if (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing') {
        return;
    }

    if (! extension_loaded('mysqli')) {
        throw new RuntimeException('L’extension mysqli est requise pour réinitialiser la base de test.');
    }

    $driver = (string) env('database.tests.DBDriver', env('database.default.DBDriver', 'MySQLi'));
    if ($driver !== 'MySQLi') {
        return;
    }

    $hostname = (string) env('database.tests.hostname', env('database.default.hostname', 'localhost'));
    $username  = (string) env('database.tests.username', env('database.default.username', ''));
    $password  = (string) env('database.tests.password', env('database.default.password', ''));
    $database  = (string) env('database.tests.database', 'platform_test');
    $port      = (int) env('database.tests.port', env('database.default.port', 3306));
    $collation = (string) env('database.tests.DBCollat', env('database.default.DBCollat', 'utf8mb4_general_ci'));

    $connection = mysqli_init();

    if ($connection === false || ! $connection->real_connect($hostname, $username, $password, '', $port)) {
        throw new RuntimeException('Impossible de réinitialiser la base de test.');
    }

    $safeDatabase = str_replace('`', '``', $database);
    $safeCollation = str_replace('`', '``', $collation);

    $connection->query('DROP DATABASE IF EXISTS `' . $safeDatabase . '`');
    $connection->query('CREATE DATABASE `' . $safeDatabase . '` CHARACTER SET utf8mb4 COLLATE ' . $safeCollation);
    $connection->close();
}
