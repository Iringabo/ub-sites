<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureHeroCarouselDefaults extends Migration
{
    public function up(): void
    {
        $this->ensureSlides();
        $this->replaceRemainingVideoFallback();
        $this->ensureOverlaySetting();
    }

    public function down(): void
    {
        if (! $this->db->tableExists('settings')) {
            return;
        }

        $this->db->table('settings')
            ->where('key', 'home.hero_overlay_opacity')
            ->where('value', '0.88')
            ->delete();
    }

    private function ensureSlides(): void
    {
        if (
            ! $this->db->tableExists('home_hero_slides')
            || (int) $this->db->table('home_hero_slides')->countAllResults() > 0
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

    private function replaceRemainingVideoFallback(): void
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

    private function ensureOverlaySetting(): void
    {
        if (
            ! $this->db->tableExists('settings')
            || (int) $this->db->table('settings')->where('key', 'home.hero_overlay_opacity')->countAllResults() > 0
        ) {
            return;
        }

        $data = [
            'class' => 'App\\Settings\\Site',
            'key'   => 'home.hero_overlay_opacity',
            'value' => '0.88',
            'type'  => 'string',
        ];

        if ($this->db->fieldExists('context', 'settings')) {
            $data['context'] = 'home';
        }

        $now = date('Y-m-d H:i:s');

        if ($this->db->fieldExists('created_at', 'settings')) {
            $data['created_at'] = $now;
        }

        if ($this->db->fieldExists('updated_at', 'settings')) {
            $data['updated_at'] = $now;
        }

        $this->db->table('settings')->insert($data);
    }
}
