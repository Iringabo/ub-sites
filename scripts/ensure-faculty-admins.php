<?php

/**
 * One-off local helper (not a committed spark command): create/reset
 * faculty site_admin accounts. Password from PLATFORM_ADMIN_PASSWORD only.
 *
 *   PLATFORM_ADMIN_PASSWORD='...' php scripts/ensure-faculty-admins.php
 */

declare(strict_types=1);

$password = getenv('PLATFORM_ADMIN_PASSWORD');
if (! is_string($password) || strlen($password) < 8) {
    fwrite(STDERR, "PLATFORM_ADMIN_PASSWORD must be set (min 8 chars).\n");
    exit(1);
}

$root = dirname(__DIR__) . '/fseg';
chdir($root);

define('FCPATH', $root . '/public/');
define('COMPOSER_PATH', $root . '/vendor/autoload.php');
define('SECONDARY_ROOT_PATH_SYMLINKED', true);

require $root . '/vendor/autoload.php';
require $root . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/bootstrap.php';

$app = Config\Services::codeigniter();
$app->initialize();
$app->setContext('cli');

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\Database;

$db = Database::connect();

$accounts = [
    ['email' => 'med-admin@ub.local', 'username' => 'medadmin', 'slug' => 'med'],
    ['email' => 'fabi-admin@ub.local', 'username' => 'fabiadmin', 'slug' => 'fabi'],
    ['email' => 'flsh-admin@ub.local', 'username' => 'flshadmin', 'slug' => 'flsh'],
];

$users = model(UserModel::class);

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

// Proper French name for FSI if still ASCII
$db->table('sites')->where('slug', 'fsi')->update([
    'name' => 'Faculté des Sciences et Ingénierie',
]);

echo "Done.\n";
