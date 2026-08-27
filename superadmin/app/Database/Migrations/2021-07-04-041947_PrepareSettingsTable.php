<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PrepareSettingsTable extends Migration
{
    public function up(): void
    {
        // Le schéma Settings est désormais la propriété du package codeigniter4/settings.
        // Cette migration reste en place pour l'historique, mais elle ne crée plus la table.
    }

    public function down(): void
    {
        // Intentionnellement vide.
    }
}
