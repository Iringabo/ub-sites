<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Departments, partners and FAQ, plus the programme facts prospective
 * students look for (language, credits, fees, deadline, curriculum...).
 */
class AddFacultyInformationModules extends Migration
{
    private const TABLE_ATTRIBUTES = ['ENGINE' => 'InnoDB', 'CHARACTER SET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];

    public function up(): void
    {
        $this->createDepartments();
        $this->createPartners();
        $this->createFaqItems();
        $this->addProgrammeFields();
        $this->addStaffFields();
    }

    public function down(): void
    {
        foreach (['department_id'] as $field) {
            if ($this->db->tableExists('staff') && $this->db->fieldExists($field, 'staff')) {
                $this->forge->dropColumn('staff', $field);
            }
        }

        foreach (array_keys($this->programmeFields()) as $field) {
            if ($this->db->tableExists('programmes') && $this->db->fieldExists($field, 'programmes')) {
                $this->forge->dropColumn('programmes', $field);
            }
        }

        $this->forge->dropTable('faq_items', true);
        $this->forge->dropTable('partners', true);
        $this->forge->dropTable('departments', true);
    }

    private function createDepartments(): void
    {
        if ($this->db->tableExists('departments')) {
            return;
        }

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'site_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'slug'          => ['type' => 'VARCHAR', 'constraint' => 180],
            'abbreviation'  => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'description'   => ['type' => 'TEXT', 'null' => true],
            'head_staff_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'phone'         => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            ...$this->orderingFields(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['site_id', 'slug'], 'departments_site_slug_unique');
        $this->forge->addKey(['site_id', 'is_published', 'display_order'], false, false, 'departments_site_published_order');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('departments', true, self::TABLE_ATTRIBUTES);
    }

    private function createPartners(): void
    {
        if ($this->db->tableExists('partners')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'site_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'type'        => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'academic'],
            'logo'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'country'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'url'         => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            ...$this->orderingFields(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['site_id', 'is_published', 'display_order'], false, false, 'partners_site_published_order');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('partners', true, self::TABLE_ATTRIBUTES);
    }

    private function createFaqItems(): void
    {
        if ($this->db->tableExists('faq_items')) {
            return;
        }

        $this->forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'site_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'category' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'studies'],
            'question' => ['type' => 'VARCHAR', 'constraint' => 500],
            'answer'   => ['type' => 'TEXT'],
            ...$this->orderingFields(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['site_id', 'is_published', 'category', 'display_order'], false, false, 'faq_items_site_published_category');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('faq_items', true, self::TABLE_ATTRIBUTES);
    }

    private function addProgrammeFields(): void
    {
        if (! $this->db->tableExists('programmes')) {
            return;
        }

        $fields = [];
        foreach ($this->programmeFields() as $name => $definition) {
            if (! $this->db->fieldExists($name, 'programmes')) {
                $fields[$name] = $definition;
            }
        }

        if ($fields !== []) {
            $this->forge->addColumn('programmes', $fields);
        }
    }

    private function addStaffFields(): void
    {
        if (! $this->db->tableExists('staff') || $this->db->fieldExists('department_id', 'staff')) {
            return;
        }

        $this->forge->addColumn('staff', [
            'department_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'category'],
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function programmeFields(): array
    {
        return [
            'department_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'level'],
            'language'             => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'duration'],
            'credits'              => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'language'],
            'tuition_fees'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'credits'],
            'application_deadline' => ['type' => 'DATE', 'null' => true, 'after' => 'tuition_fees'],
            'curriculum'           => ['type' => 'TEXT', 'null' => true, 'after' => 'career_outcomes'],
            'coordinator_staff_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'curriculum'],
            'brochure_path'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'coordinator_staff_id'],
            'apply_url'            => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'brochure_path'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function orderingFields(): array
    {
        return [
            'display_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_published'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ];
    }
}
