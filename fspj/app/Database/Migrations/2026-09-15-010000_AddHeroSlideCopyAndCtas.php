<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHeroSlideCopyAndCtas extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('home_hero_slides')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('badge', 'home_hero_slides')) {
            $fields['badge'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }
        if (! $this->db->fieldExists('title', 'home_hero_slides')) {
            $fields['title'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }
        if (! $this->db->fieldExists('text', 'home_hero_slides')) {
            $fields['text'] = ['type' => 'TEXT', 'null' => true];
        }
        if (! $this->db->fieldExists('primary_cta_target', 'home_hero_slides')) {
            $fields['primary_cta_target'] = ['type' => 'VARCHAR', 'constraint' => 40, 'null' => false, 'default' => 'none'];
        }
        if (! $this->db->fieldExists('primary_cta_label', 'home_hero_slides')) {
            $fields['primary_cta_label'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }
        if (! $this->db->fieldExists('primary_cta_url', 'home_hero_slides')) {
            $fields['primary_cta_url'] = ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true];
        }
        if (! $this->db->fieldExists('secondary_cta_target', 'home_hero_slides')) {
            $fields['secondary_cta_target'] = ['type' => 'VARCHAR', 'constraint' => 40, 'null' => false, 'default' => 'none'];
        }
        if (! $this->db->fieldExists('secondary_cta_label', 'home_hero_slides')) {
            $fields['secondary_cta_label'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }
        if (! $this->db->fieldExists('secondary_cta_url', 'home_hero_slides')) {
            $fields['secondary_cta_url'] = ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true];
        }

        if ($fields !== []) {
            $this->forge->addColumn('home_hero_slides', $fields);
        }

        // Copy singleton hero copy onto the first published slide per site when empty.
        $homes = $this->db->table('home_content')->get()->getResultArray();
        foreach ($homes as $home) {
            $siteId = (int) ($home['site_id'] ?? 0);
            if ($siteId <= 0) {
                continue;
            }

            $slide = $this->db->table('home_hero_slides')
                ->where('site_id', $siteId)
                ->where('deleted_at', null)
                ->orderBy('is_published', 'DESC')
                ->orderBy('display_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getRowArray();

            if ($slide === null || trim((string) ($slide['title'] ?? '')) !== '') {
                continue;
            }

            $this->db->table('home_hero_slides')->where('id', (int) $slide['id'])->update([
                'badge'                => $home['hero_badge'] ?? null,
                'title'                => $home['hero_title'] ?? null,
                'text'                 => $home['hero_text'] ?? null,
                'primary_cta_target'   => 'custom',
                'primary_cta_label'    => $home['hero_primary_label'] ?? null,
                'primary_cta_url'      => $home['hero_primary_url'] ?? null,
                'secondary_cta_target' => 'custom',
                'secondary_cta_label'  => $home['hero_secondary_label'] ?? null,
                'secondary_cta_url'    => $home['hero_secondary_url'] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('home_hero_slides')) {
            return;
        }

        foreach ([
            'badge', 'title', 'text',
            'primary_cta_target', 'primary_cta_label', 'primary_cta_url',
            'secondary_cta_target', 'secondary_cta_label', 'secondary_cta_url',
        ] as $column) {
            if ($this->db->fieldExists($column, 'home_hero_slides')) {
                $this->forge->dropColumn('home_hero_slides', $column);
            }
        }
    }
}
