<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A department offers several options (each one a programme). Options of the
 * same department and level can share a tronc commun before they split.
 */
class AddProgrammeOptions extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('programmes')) {
            return;
        }

        $fields = [
            'option_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'department_id'],
            'common_core' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'option_code'],
        ];

        $fields = array_filter($fields, fn (string $name): bool => ! $this->db->fieldExists($name, 'programmes'), ARRAY_FILTER_USE_KEY);
        if ($fields !== []) {
            $this->forge->addColumn('programmes', $fields);
            $this->db->resetDataCache();
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('programmes')) {
            return;
        }

        foreach (['common_core', 'option_code'] as $field) {
            if ($this->db->fieldExists($field, 'programmes')) {
                $this->forge->dropColumn('programmes', $field);
            }
        }

        if ($this->db->tableExists('content_translations')) {
            $this->db->table('content_translations')
                ->where('resource_type', 'programmes')
                ->where('field', 'common_core')
                ->delete();
        }
    }
}
