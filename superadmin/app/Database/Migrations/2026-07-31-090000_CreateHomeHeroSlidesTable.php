<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHomeHeroSlidesTable extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('home_hero_slides')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'image_path'    => ['type' => 'VARCHAR', 'constraint' => 500],
                'alt_text'      => ['type' => 'VARCHAR', 'constraint' => 255],
                'display_order' => ['type' => 'INT', 'constraint' => 10, 'default' => 0],
                'is_published'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['is_published', 'display_order']);
            $this->forge->createTable('home_hero_slides', true, ['ENGINE' => 'InnoDB', 'CHARACTER SET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_general_ci']);
        }

        $this->backfillSlides();
        $this->replaceVideoFallback();
    }

    public function down(): void
    {
        $this->forge->dropTable('home_hero_slides', true);
    }

    private function backfillSlides(): void
    {
        if ((int) $this->db->table('home_hero_slides')->countAllResults() > 0) {
            return;
        }

        if (
            ! $this->db->tableExists('home_content')
            || (int) $this->db->table('home_content')->countAllResults() === 0
        ) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->db->table('home_hero_slides')->insertBatch([
            [
                'image_path'    => 'assets/images/hero/campus-walkway.jpg',
                'alt_text'      => 'Étudiants marchant devant un bâtiment universitaire moderne',
                'display_order' => 1,
                'is_published'  => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'image_path'    => 'assets/images/hero/economics-classroom.jpg',
                'alt_text'      => 'Cours universitaire en économie dans un amphithéâtre',
                'display_order' => 2,
                'is_published'  => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'image_path'    => 'assets/images/hero/research-team.jpg',
                'alt_text'      => 'Équipe de recherche analysant des documents économiques',
                'display_order' => 3,
                'is_published'  => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ]);
    }

    private function replaceVideoFallback(): void
    {
        if (! $this->db->tableExists('home_content')) {
            return;
        }

        $this->db->table('home_content')
            ->where('hero_media_type', 'video')
            ->update([
                'hero_media_type' => 'image',
                'hero_media_path' => 'assets/images/hero/campus-walkway.jpg',
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
    }
}
