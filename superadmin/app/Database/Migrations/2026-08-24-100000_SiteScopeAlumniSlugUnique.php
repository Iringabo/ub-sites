<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rend le slug des profils alumni unique PAR SITE (et non global).
 *
 * La table `alumni_profiles` est la seule table à contenu par site dont
 * l'index unique initial sur `slug` n'a pas été converti en index
 * `(site_id, slug)` : sans correction, provisionner un second site
 * échoue avec une entrée dupliquée « alumni-exemple ».
 */
class SiteScopeAlumniSlugUnique extends Migration
{
    public function up(): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->tableExists('alumni_profiles') || ! $this->fieldExists('alumni_profiles', 'site_id')) {
            return;
        }

        foreach ($this->mysqlIndexes('alumni_profiles') as $indexName => $index) {
            if ($indexName !== 'PRIMARY' && $index['unique'] && $index['columns'] === ['slug']) {
                $this->dropIndexByName('alumni_profiles', $indexName);
            }
        }

        if (! $this->indexExists('alumni_profiles', 'alumni_profiles_site_slug_unique')) {
            $this->db->query(
                'ALTER TABLE ' . $this->tableSql('alumni_profiles')
                . ' ADD UNIQUE KEY ' . $this->db->escapeIdentifiers('alumni_profiles_site_slug_unique')
                . ' (' . $this->db->escapeIdentifiers('site_id') . ', ' . $this->db->escapeIdentifiers('slug') . ')',
            );
        }
    }

    public function down(): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->tableExists('alumni_profiles')) {
            return;
        }

        $this->dropIndexByName('alumni_profiles', 'alumni_profiles_site_slug_unique');

        if (! $this->indexExists('alumni_profiles', 'alumni_profiles_slug_unique')) {
            $this->db->query(
                'ALTER TABLE ' . $this->tableSql('alumni_profiles')
                . ' ADD UNIQUE KEY ' . $this->db->escapeIdentifiers('alumni_profiles_slug_unique')
                . ' (' . $this->db->escapeIdentifiers('slug') . ')',
            );
        }
    }

    private function dropIndexByName(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        $this->db->query(
            'ALTER TABLE ' . $this->tableSql($table)
            . ' DROP INDEX ' . $this->db->escapeIdentifiers($indexName),
        );
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return array_key_exists($indexName, $this->mysqlIndexes($table));
    }

    /**
     * @return array<string, array{unique: bool, columns: list<string>}>
     */
    private function mysqlIndexes(string $table): array
    {
        if (! $this->tableExists($table)) {
            return [];
        }

        $rows = $this->db->query('SHOW INDEX FROM ' . $this->tableSql($table))->getResultArray();
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
            ksort($indexes[$name]['columns']);
            $indexes[$name]['columns'] = array_values($indexes[$name]['columns']);
        }

        return $indexes;
    }

    private function tableExists(string $table): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->db->getDatabase(), $this->physicalTable($table)],
        )->getRowArray();

        return (int) ($row['total'] ?? 0) > 0;
    }

    private function fieldExists(string $table, string $field): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->db->getDatabase(), $this->physicalTable($table), $field],
        )->getRowArray();

        return (int) ($row['total'] ?? 0) > 0;
    }

    private function tableSql(string $table): string
    {
        return $this->db->escapeIdentifiers($this->physicalTable($table));
    }

    private function physicalTable(string $table): string
    {
        $prefix = $this->db->DBPrefix;

        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            return $table;
        }

        return $prefix . $table;
    }
}
