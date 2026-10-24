<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Throwable;

class RemoveForbiddenModuleDependencies extends Migration
{
    /**
     * Supprime les relations héritées qui contredisent le modèle métier :
     * projets, laboratoires, formations et personnel sont des modules indépendants.
     */
    public function up(): void
    {
        $this->removeForbiddenColumns('research_projects', ['laboratory_id', 'lab_id']);
        $this->removeForbiddenColumns('programmes', ['staff_id', 'coordinator_id', 'responsible_staff_id']);
    }

    public function down(): void
    {
        // Intentionnellement vide : ces dépendances sont interdites par les règles métier.
    }

    /**
     * @param list<string> $columns
     */
    private function removeForbiddenColumns(string $table, array $columns): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! $this->db->fieldExists($column, $table)) {
                continue;
            }

            $this->dropForeignKeysForColumn($table, $column);
            $this->forge->dropColumn($table, $column);
        }
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        foreach ($this->foreignKeyNames($table, $column) as $foreignKeyName) {
            try {
                $this->forge->dropForeignKey($table, $foreignKeyName);
            } catch (Throwable) {
                // La contrainte peut ne pas exister selon l'historique local de la base.
            }
        }
    }

    /**
     * @return list<string>
     */
    private function foreignKeyNames(string $table, string $column): array
    {
        $names = [
            $table . '_' . $column . '_foreign',
            $this->db->DBPrefix . $table . '_' . $column . '_foreign',
            'fk_' . $table . '_' . $column,
            'fk_' . $this->db->DBPrefix . $table . '_' . $column,
        ];

        if ($this->db->DBDriver === 'MySQLi') {
            $prefixedTable = $this->db->DBPrefix . $table;
            $query = $this->db->query(
                'SELECT CONSTRAINT_NAME
                   FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                    AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$prefixedTable, $column],
            );

            foreach ($query->getResultArray() as $row) {
                if (isset($row['CONSTRAINT_NAME'])) {
                    $names[] = (string) $row['CONSTRAINT_NAME'];
                }
            }
        }

        return array_values(array_unique(array_filter($names)));
    }
}
