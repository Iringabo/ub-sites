<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Studies at the University of Burundi are free for holders of the state
 * exam threshold, so programmes carry an entry requirement instead of fees.
 * Departments belong to a cycle, and posts gain an author and an attachment.
 */
class AdjustInformationModules extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('programmes')) {
            if ($this->db->fieldExists('tuition_fees', 'programmes')) {
                $this->forge->dropColumn('programmes', 'tuition_fees');
            }

            $this->addMissing('programmes', [
                'entry_requirement' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'credits'],
            ]);
        }

        if ($this->db->tableExists('content_translations')) {
            $this->db->table('content_translations')
                ->where('resource_type', 'programmes')
                ->where('field', 'tuition_fees')
                ->delete();
        }

        $this->addMissing('departments', [
            'cycle' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'both', 'after' => 'abbreviation'],
        ]);

        $this->addMissing('posts', [
            'author_name'     => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true, 'after' => 'body'],
            'attachment_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'cover_image'],
            'attachment_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'attachment_path'],
        ]);
    }

    public function down(): void
    {
        $this->dropExisting('posts', ['author_name', 'attachment_path', 'attachment_name']);
        $this->dropExisting('departments', ['cycle']);
        $this->dropExisting('programmes', ['entry_requirement']);

        if ($this->db->tableExists('programmes') && ! $this->db->fieldExists('tuition_fees', 'programmes')) {
            $this->forge->addColumn('programmes', [
                'tuition_fees' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'credits'],
            ]);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    private function addMissing(string $table, array $fields): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $fields = array_filter($fields, fn (string $name): bool => ! $this->db->fieldExists($name, $table), ARRAY_FILTER_USE_KEY);
        if ($fields !== []) {
            $this->forge->addColumn($table, $fields);
        }
    }

    /**
     * @param list<string> $fields
     */
    private function dropExisting(string $table, array $fields): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        foreach ($fields as $field) {
            if ($this->db->fieldExists($field, $table)) {
                $this->forge->dropColumn($table, $field);
            }
        }
    }
}
