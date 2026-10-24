<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Split contact.address into avenue/commune/province/country settings.
 */
class SplitContactAddressSettings extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('settings')) {
            return;
        }

        $rows = $this->db->table('settings')
            ->where('key', 'contact.address')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $siteId = isset($row['site_id']) ? (int) $row['site_id'] : null;
            $value  = trim((string) ($row['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            $this->insertIfMissing($siteId, 'contact.address_line', $value, 'string', 'contact');
            $this->insertIfMissing($siteId, 'contact.address_commune', '', 'string', 'contact');
            $this->insertIfMissing($siteId, 'contact.address_province', '', 'string', 'contact');
            $this->insertIfMissing($siteId, 'contact.address_country', 'Burundi', 'string', 'contact');
        }
    }

    public function down(): void
    {
        // Keep segmented keys; legacy contact.address rows remain untouched.
    }

    private function insertIfMissing(?int $siteId, string $key, string $value, string $type, string $context): void
    {
        $builder = $this->db->table('settings')->where('key', $key);
        if ($siteId !== null && $this->db->fieldExists('site_id', 'settings')) {
            $builder->where('site_id', $siteId);
        }

        if ($builder->countAllResults() > 0) {
            return;
        }

        $payload = [
            'class'      => 'App\\Settings\\Site',
            'key'        => $key,
            'value'      => $value,
            'type'       => $type,
            'context'    => $context,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($siteId !== null && $this->db->fieldExists('site_id', 'settings')) {
            $payload['site_id'] = $siteId;
        }

        $this->db->table('settings')->insert($payload);
    }
}
