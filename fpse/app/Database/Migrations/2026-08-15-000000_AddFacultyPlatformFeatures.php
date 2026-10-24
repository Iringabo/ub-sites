<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFacultyPlatformFeatures extends Migration
{
    public function up(): void
    {
        $this->addSiteDesignFields();
        $this->createContentBlocks();
        $this->normalizeUserSiteRoles();
    }

    public function down(): void
    {
        $this->forge->dropTable('content_blocks', true);

        if ($this->db->tableExists('sites')) {
            foreach (['enabled_sections', 'menu_config', 'theme_config', 'theme'] as $field) {
                if ($this->db->fieldExists($field, 'sites')) {
                    $this->forge->dropColumn('sites', $field);
                }
            }
        }
    }

    private function addSiteDesignFields(): void
    {
        if (! $this->db->tableExists('sites')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('theme', 'sites')) {
            $fields['theme'] = [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'default'    => 'default',
                'after'      => 'secondary_color',
            ];
        }

        if (! $this->db->fieldExists('theme_config', 'sites')) {
            $fields['theme_config'] = [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'theme',
            ];
        }

        if (! $this->db->fieldExists('menu_config', 'sites')) {
            $fields['menu_config'] = [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'theme_config',
            ];
        }

        if (! $this->db->fieldExists('enabled_sections', 'sites')) {
            $fields['enabled_sections'] = [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'menu_config',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('sites', $fields);
        }
    }

    private function createContentBlocks(): void
    {
        if ($this->db->tableExists('content_blocks')) {
            return;
        }

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'site_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'page_key'      => ['type' => 'VARCHAR', 'constraint' => 120],
            'type'          => ['type' => 'VARCHAR', 'constraint' => 80],
            'title'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'content'       => ['type' => 'TEXT', 'null' => true],
            'settings'      => ['type' => 'TEXT', 'null' => true],
            'display_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_published'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['site_id', 'page_key', 'is_published', 'display_order'], false, false, 'content_blocks_site_page_published_order');
        $this->forge->addKey(['site_id', 'type'], false, false, 'content_blocks_site_type');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('content_blocks', true, ['ENGINE' => 'InnoDB', 'CHARACTER SET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);
    }

    private function normalizeUserSiteRoles(): void
    {
        if (! $this->db->tableExists('user_sites')) {
            return;
        }

        $this->db->table('user_sites')
            ->whereIn('role', ['', 'administrator'])
            ->update(['role' => 'site_admin']);
    }
}
