<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Study paths: a first tronc commun of configurable length, optionally
 * followed by a narrower one before the option starts. Departments can
 * publish their course maquette as a PDF.
 */
class AddStudyPathsAndMaquettes extends Migration
{
    public function up(): void
    {
        $this->addColumns('programmes', [
            'common_core_years' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => true, 'after' => 'common_core'],
            'branch_core'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'common_core_years'],
            'branch_core_years' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => true, 'after' => 'branch_core'],
        ]);

        $this->addColumns('departments', [
            'maquette_path'  => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'phone'],
            'maquette_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'maquette_path'],
        ]);
    }

    public function down(): void
    {
        $this->dropColumns('programmes', ['branch_core_years', 'branch_core', 'common_core_years']);
        $this->dropColumns('departments', ['maquette_title', 'maquette_path']);

        if ($this->db->tableExists('content_translations')) {
            $this->db->table('content_translations')
                ->groupStart()
                    ->where('resource_type', 'programmes')
                    ->where('field', 'branch_core')
                ->groupEnd()
                ->orGroupStart()
                    ->where('resource_type', 'departments')
                    ->where('field', 'maquette_title')
                ->groupEnd()
                ->delete();
        }
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    private function addColumns(string $table, array $fields): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $fields = array_filter($fields, fn (string $name): bool => ! $this->db->fieldExists($name, $table), ARRAY_FILTER_USE_KEY);
        if ($fields !== []) {
            $this->forge->addColumn($table, $fields);
            $this->db->resetDataCache();
        }
    }

    /**
     * @param list<string> $fields
     */
    private function dropColumns(string $table, array $fields): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        foreach ($fields as $field) {
            if ($this->db->fieldExists($field, $table)) {
                $this->forge->dropColumn($table, $field);
            }
        }
        $this->db->resetDataCache();
    }
}
