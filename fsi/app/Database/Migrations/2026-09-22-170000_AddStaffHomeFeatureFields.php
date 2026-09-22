<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Allow choosing which staff members appear on the homepage preview.
 */
class AddStaffHomeFeatureFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('staff')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('featured_on_home', 'staff')) {
            $fields['featured_on_home'] = [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'display_order',
            ];
        }
        if (! $this->db->fieldExists('home_order', 'staff')) {
            $fields['home_order'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'featured_on_home',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('staff', $fields);
        }

        // Preserve current homepage behaviour: first 4 published members per site,
        // but only when none are featured yet (idempotent re-runs).
        $siteIds = $this->db->table('staff')
            ->select('site_id')
            ->where('deleted_at', null)
            ->groupBy('site_id')
            ->get()
            ->getResultArray();

        foreach ($siteIds as $row) {
            $siteId = (int) ($row['site_id'] ?? 0);
            if ($siteId <= 0) {
                continue;
            }

            $alreadyFeatured = (int) $this->db->table('staff')
                ->where('site_id', $siteId)
                ->where('is_published', 1)
                ->where('deleted_at', null)
                ->where('featured_on_home', 1)
                ->countAllResults();

            if ($alreadyFeatured > 0) {
                continue;
            }

            $members = $this->db->table('staff')
                ->select('id')
                ->where('site_id', $siteId)
                ->where('is_published', 1)
                ->where('deleted_at', null)
                ->orderBy('display_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->limit(4)
                ->get()
                ->getResultArray();

            $order = 1;
            foreach ($members as $member) {
                $this->db->table('staff')->where('id', (int) $member['id'])->update([
                    'featured_on_home' => 1,
                    'home_order'       => $order,
                ]);
                $order++;
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('staff')) {
            return;
        }

        if ($this->db->fieldExists('home_order', 'staff')) {
            $this->forge->dropColumn('staff', 'home_order');
        }
        if ($this->db->fieldExists('featured_on_home', 'staff')) {
            $this->forge->dropColumn('staff', 'featured_on_home');
        }
    }
}
