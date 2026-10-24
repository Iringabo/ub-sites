<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Throwable;

/**
 * Idempotent repair: the CodeIgniter Settings table may exist without site_id
 * even when earlier site-scope migrations are marked as already run.
 */
class EnsureSettingsSiteId extends Migration
{
    public function up(): void
    {
        $table = $this->settingsTableName();
        if ($table === null) {
            return;
        }

        if (! $this->columnExists($table, 'site_id')) {
            try {
                $this->forge->addColumn('settings', [
                    'site_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'unsigned'   => true,
                        'null'       => false,
                        'default'    => 1,
                        'after'      => 'id',
                    ],
                ]);
            } catch (Throwable $exception) {
                if (! str_contains($exception->getMessage(), 'Duplicate column')) {
                    throw $exception;
                }
            }
        }

        if ($this->db->DBDriver === 'MySQLi' && $this->columnExists($table, 'site_id')) {
            $this->db->query('UPDATE ' . $this->db->escapeIdentifiers($table) . ' SET site_id = 1 WHERE site_id IS NULL OR site_id = 0');
        }

        $this->dropUniqueOnKeyOnly($table);
        $this->addSiteKeyUnique($table);
    }

    public function down(): void
    {
        // Repair only. Rolling back site_id would collapse (site_id, key)
        // uniqueness onto key and fail when several sites share the same keys.
    }

    private function settingsTableName(): ?string
    {
        $prefixed = $this->db->prefixTable('settings');
        foreach (array_unique([$prefixed, 'settings']) as $table) {
            if ($this->rawTableExists($table)) {
                return $table;
            }
        }

        return null;
    }

    private function rawTableExists(string $table): bool
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return $this->db->tableExists($table);
        }

        $result = $this->db->query('SHOW TABLES LIKE ' . $this->db->escape($table));

        return $result !== false && $result->getFirstRow() !== null;
    }

    private function columnExists(string $table, string $column): bool
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return $this->db->fieldExists($column, $table);
        }

        $result = $this->db->query(
            'SHOW COLUMNS FROM ' . $this->db->escapeIdentifiers($table)
            . ' LIKE ' . $this->db->escape($column),
        );

        return $result !== false && $result->getFirstRow() !== null;
    }

    private function dropUniqueOnKeyOnly(string $table): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }

        $rows = $this->db->query('SHOW INDEX FROM ' . $this->db->escapeIdentifiers($table))->getResultArray();
        $indexes = [];

        foreach ($rows as $row) {
            $name = (string) $row['Key_name'];
            $indexes[$name] ??= [
                'unique'  => (int) $row['Non_unique'] === 0,
                'columns' => [],
            ];
            $indexes[$name]['columns'][(int) $row['Seq_in_index']] = (string) $row['Column_name'];
        }

        foreach ($indexes as $name => $index) {
            ksort($index['columns']);
            $columns = array_values($index['columns']);
            if ($name === 'PRIMARY' || ! $index['unique'] || $columns !== ['key']) {
                continue;
            }

            $this->db->query(
                'ALTER TABLE ' . $this->db->escapeIdentifiers($table)
                . ' DROP INDEX ' . $this->db->escapeIdentifiers($name),
            );
        }
    }

    private function addSiteKeyUnique(string $table): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->columnExists($table, 'site_id')) {
            return;
        }

        $rows = $this->db->query('SHOW INDEX FROM ' . $this->db->escapeIdentifiers($table))->getResultArray();

        foreach ($rows as $row) {
            if ((string) $row['Key_name'] === 'settings_site_key_unique') {
                return;
            }
        }

        $this->db->query(
            'ALTER TABLE ' . $this->db->escapeIdentifiers($table)
            . ' ADD UNIQUE KEY ' . $this->db->escapeIdentifiers('settings_site_key_unique')
            . ' (site_id, ' . $this->db->escapeIdentifiers('key') . ')',
        );
    }
}
