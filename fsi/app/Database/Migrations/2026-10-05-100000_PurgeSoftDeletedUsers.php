<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Accounts deleted before user deletion became permanent were soft-deleted:
 * their username stayed reserved and the same person could not be created
 * again. Remove those rows and anything still attached to them.
 */
class PurgeSoftDeletedUsers extends Migration
{
    public function up(): void
    {
        $tables = config('Auth')->tables;
        $usersTable = $tables['users'];

        if (! $this->db->tableExists($usersTable) || ! $this->db->fieldExists('deleted_at', $usersTable)) {
            return;
        }

        $ids = array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->db->table($usersTable)->select('id')->where('deleted_at IS NOT NULL')->get()->getResultArray(),
        );

        if ($ids === []) {
            return;
        }

        $dependent = [
            $tables['groups_users'] ?? null,
            $tables['permissions_users'] ?? null,
            $tables['identities'] ?? null,
            $tables['logins'] ?? null,
            $tables['token_logins'] ?? null,
            $tables['remember_tokens'] ?? null,
            'user_sites',
        ];

        foreach (array_filter($dependent) as $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('user_id', $table)) {
                $this->db->table($table)->whereIn('user_id', $ids)->delete();
            }
        }

        $this->db->table($usersTable)->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // Purged accounts cannot be restored.
    }
}
