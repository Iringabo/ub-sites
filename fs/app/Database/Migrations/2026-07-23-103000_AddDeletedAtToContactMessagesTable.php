<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Throwable;

class AddDeletedAtToContactMessagesTable extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('contact_messages')) {
            return;
        }

        if (! $this->db->fieldExists('deleted_at', 'contact_messages')) {
            $this->forge->addColumn('contact_messages', [
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('contact_messages') && $this->db->fieldExists('deleted_at', 'contact_messages')) {
            try {
                $this->forge->dropColumn('contact_messages', 'deleted_at');
            } catch (Throwable) {
                // La colonne peut déjà avoir été retirée par un retour arrière partiel.
            }
        }
    }
}
