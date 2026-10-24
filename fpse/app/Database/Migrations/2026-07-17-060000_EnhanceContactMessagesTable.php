<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Throwable;

class EnhanceContactMessagesTable extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('contact_messages')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('read_at', 'contact_messages')) {
            $fields['read_at'] = [
                'type' => 'DATETIME',
                'null' => true,
            ];
        }

        if (! $this->db->fieldExists('processed_by', 'contact_messages')) {
            $fields['processed_by'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('contact_messages', $fields);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('contact_messages')) {
            return;
        }

        foreach (['processed_by', 'read_at'] as $field) {
            if ($this->db->fieldExists($field, 'contact_messages')) {
                try {
                    $this->forge->dropColumn('contact_messages', $field);
                } catch (Throwable) {
                    // La base de test peut déjà avoir été partiellement remontée/abaissée.
                }
            }
        }
    }
}
