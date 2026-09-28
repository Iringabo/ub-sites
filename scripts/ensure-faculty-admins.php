<?php

/**
 * One-off local helper: create/reset faculty site_admin accounts.
 * Password from PLATFORM_ADMIN_PASSWORD only.
 *
 *   PLATFORM_ADMIN_PASSWORD='...' php scripts/ensure-faculty-admins.php
 */

declare(strict_types=1);

use CodeIgniter\Boot;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\Database;
use Config\Paths;

$password = getenv('PLATFORM_ADMIN_PASSWORD');
if (! is_string($password) || strlen($password) < 8) {
    fwrite(STDERR, "PLATFORM_ADMIN_PASSWORD must be set (min 8 chars).\n");
    exit(1);
}

$root = dirname(__DIR__) . '/fseg';

define('FCPATH', $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);

require FCPATH . '../app/Config/Paths.php';
$paths = new Paths();
require $paths->systemDirectory . '/Boot.php';

// bootConsole assumes ENVIRONMENT is already defined (as in util_bootstrap.php).
if (! defined('ENVIRONMENT')) {
    $env = $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'development';
    define('ENVIRONMENT', $env);
}

Boot::bootConsole($paths);

$db    = Database::connect();
$users = model(UserModel::class);

$accounts = [
    ['email' => 'fseg-admin@ub.local', 'username' => 'fsegadmin', 'slug' => 'fseg'],
    ['email' => 'fsi-admin@ub.local', 'username' => 'fsiadmin', 'slug' => 'fsi'],
    ['email' => 'med-admin@ub.local', 'username' => 'medadmin', 'slug' => 'med'],
    ['email' => 'fabi-admin@ub.local', 'username' => 'fabiadmin', 'slug' => 'fabi'],
    ['email' => 'flsh-admin@ub.local', 'username' => 'flshadmin', 'slug' => 'flsh'],
];

foreach ($accounts as $account) {
    $site = $db->table('sites')->where('slug', $account['slug'])->get()->getRowArray();
    if ($site === null) {
        fwrite(STDERR, "Site missing: {$account['slug']}\n");
        continue;
    }
    $siteId = (int) $site['id'];

    $identity = $db->table('auth_identities')
        ->where('type', 'email_password')
        ->where('secret', $account['email'])
        ->get()
        ->getRowArray();

    if ($identity === null) {
        $user = new User([
            'username' => $account['username'],
            'email'    => $account['email'],
            'password' => $password,
            'active'   => 1,
        ]);
        if (! $users->save($user)) {
            fwrite(STDERR, "Create failed for {$account['email']}: " . implode('; ', $users->errors()) . "\n");
            continue;
        }
        $user = $users->findById((int) $users->getInsertID());
        echo "Created {$account['email']}\n";
    } else {
        $user = $users->findById((int) $identity['user_id']);
        $user->password = $password;
        $users->save($user);
        echo "Reset password for {$account['email']}\n";
    }

    $user->addGroup('admin');
    $user->activate();

    service('siteResolver')->syncUserSites((int) $user->id, [$siteId], 'site_admin');
    echo "  assigned site_admin on {$account['slug']} (site_id={$siteId})\n";
}

// Reset known local superadmin if present
$supIdentity = $db->table('auth_identities')
    ->where('type', 'email_password')
    ->where('secret', 'sup@test.com')
    ->get()
    ->getRowArray();
if ($supIdentity !== null) {
    $sup = $users->findById((int) $supIdentity['user_id']);
    $sup->password = $password;
    $users->save($sup);
    $sup->addGroup('superadmin');
    $sup->activate();
    echo "Reset password for sup@test.com\n";
}

$db->table('sites')->where('slug', 'fsi')->update([
    'name' => 'Faculté des Sciences et Ingénierie',
]);

echo "Done.\n";
