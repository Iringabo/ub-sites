<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureSettingsTableExists extends Migration
{
    public function up(): void
    {
        // Désactivée: le package codeigniter4/settings crée et fait évoluer ce schéma.
    }

    public function down(): void
    {
        // Intentionnellement vide.
    }
}
