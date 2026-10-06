<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Doctoral studies are run by a separate website: faculty sites only list
 * licence and master programmes. Existing doctorate records are hidden
 * (soft-deleted) rather than erased.
 */
class RemoveDoctoralProgrammes extends Migration
{
    private const LEGACY_HOME_TEXT = 'Des cursus complets de la licence au doctorat pour former les experts économiques et financiers de demain.';

    public function up(): void
    {
        if ($this->db->tableExists('programmes')) {
            $changes = ['is_published' => 0, 'deleted_at' => date('Y-m-d H:i:s')];
            if ($this->db->fieldExists('featured_on_home', 'programmes')) {
                $changes['featured_on_home'] = 0;
            }

            $this->db->table('programmes')
                ->where('level', 'doctorat')
                ->where('deleted_at', null)
                ->update($changes);
        }

        if ($this->db->tableExists('home_content') && $this->db->fieldExists('programmes_text', 'home_content')) {
            $this->db->table('home_content')
                ->where('programmes_text', self::LEGACY_HOME_TEXT)
                ->update(['programmes_text' => 'Des cursus de licence et de master pour former les experts économiques et financiers de demain.']);
        }
    }

    public function down(): void
    {
        // Hidden doctorate records stay hidden: the level no longer exists.
    }
}
