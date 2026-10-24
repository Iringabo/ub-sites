<?php

declare(strict_types=1);

namespace App\Database\Migrations;

require_once __DIR__ . '/2026-08-04-041000_RepairSiteScopedUniqueIndexes.php';

class EnforcePhysicalSiteScopedIndexes extends RepairSiteScopedUniqueIndexes
{
    public function down(): void
    {
        // No-op: this migration repairs already-applied schema metadata without
        // changing the intended model. Rolling it back should not remove indexes
        // that earlier migrations may already rely on.
    }
}
