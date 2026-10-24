<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RepairSiteScopedUniqueIndexes extends Migration
{
    /**
     * @var list<string>
     */
    private array $siteOwnedTables = [
        'home_content',
        'home_highlights',
        'home_hero_slides',
        'posts',
        'programmes',
        'staff',
        'laboratories',
        'publications',
        'research_projects',
        'timeline_items',
        'alumni_profiles',
        'testimonials',
        'site_stats',
        'pages',
        'contact_messages',
        'settings',
        'content_translations',
    ];

    /**
     * @var array<string, list<array{old: list<string>, new: list<string>, name: string}>>
     */
    private array $uniqueDefinitions = [
        'home_content' => [
            ['old' => ['singleton_key'], 'new' => ['site_id', 'singleton_key'], 'name' => 'home_content_site_singleton_unique'],
        ],
        'posts' => [
            ['old' => ['slug'], 'new' => ['site_id', 'slug'], 'name' => 'posts_site_slug_unique'],
        ],
        'programmes' => [
            ['old' => ['slug'], 'new' => ['site_id', 'slug'], 'name' => 'programmes_site_slug_unique'],
        ],
        'staff' => [
            ['old' => ['slug'], 'new' => ['site_id', 'slug'], 'name' => 'staff_site_slug_unique'],
        ],
        'laboratories' => [
            ['old' => ['slug'], 'new' => ['site_id', 'slug'], 'name' => 'laboratories_site_slug_unique'],
            ['old' => ['abbreviation'], 'new' => ['site_id', 'abbreviation'], 'name' => 'laboratories_site_abbreviation_unique'],
        ],
        'research_projects' => [
            ['old' => ['code'], 'new' => ['site_id', 'code'], 'name' => 'research_projects_site_code_unique'],
        ],
        'pages' => [
            ['old' => ['key'], 'new' => ['site_id', 'key'], 'name' => 'pages_site_key_unique'],
            ['old' => ['slug'], 'new' => ['site_id', 'slug'], 'name' => 'pages_site_slug_unique'],
        ],
        'settings' => [
            ['old' => ['key'], 'new' => ['site_id', 'key'], 'name' => 'settings_site_key_unique'],
        ],
        'content_translations' => [
            ['old' => ['resource_type', 'resource_id', 'locale', 'field'], 'new' => ['site_id', 'resource_type', 'resource_id', 'locale', 'field'], 'name' => 'content_translations_site_resource_locale_field'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->siteOwnedTables as $table) {
            $this->makeSiteIdRequired($table);
        }

        foreach ($this->uniqueDefinitions as $table => $definitions) {
            if (! $this->tableExists($table) || ! $this->fieldExists($table, 'site_id')) {
                continue;
            }

            foreach ($definitions as $definition) {
                $this->dropUniqueIndexForColumns($table, $definition['old']);
                $this->addUniqueIndex($table, $definition['name'], $definition['new']);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->uniqueDefinitions as $table => $definitions) {
            foreach ($definitions as $definition) {
                $this->dropIndexByName($table, $definition['name']);
            }
        }
    }

    private function makeSiteIdRequired(string $table): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->tableExists($table) || ! $this->fieldExists($table, 'site_id')) {
            return;
        }

        $this->db->query('UPDATE ' . $this->tableSql($table) . ' SET site_id = 1 WHERE site_id IS NULL');
        $this->db->query(
            'ALTER TABLE ' . $this->tableSql($table)
            . ' MODIFY ' . $this->db->escapeIdentifiers('site_id')
            . ' INT(11) UNSIGNED NOT NULL DEFAULT 1',
        );
    }

    /**
     * @param list<string> $columns
     */
    private function dropUniqueIndexForColumns(string $table, array $columns): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }

        foreach ($this->mysqlIndexes($table) as $indexName => $index) {
            if ($indexName === 'PRIMARY' || ! $index['unique'] || $index['columns'] !== $columns) {
                continue;
            }

            $this->dropIndexByName($table, $indexName);
        }
    }

    /**
     * @param list<string> $columns
     */
    private function addUniqueIndex(string $table, string $indexName, array $columns): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || $this->indexExists($table, $indexName)) {
            return;
        }

        $columnSql = implode(', ', array_map(
            fn (string $column): string => $this->db->escapeIdentifiers($column),
            $columns,
        ));

        $this->db->query(
            'ALTER TABLE ' . $this->tableSql($table)
            . ' ADD UNIQUE KEY ' . $this->db->escapeIdentifiers($indexName)
            . ' (' . $columnSql . ')',
        );
    }

    private function dropIndexByName(string $table, string $indexName): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->tableExists($table) || ! $this->indexExists($table, $indexName)) {
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
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->tableExists($table)) {
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
        if ($this->db->DBDriver !== 'MySQLi') {
            return $this->db->tableExists($table);
        }

        $row = $this->db->query(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->db->getDatabase(), $this->physicalTable($table)],
        )->getRowArray();

        return (int) ($row['total'] ?? 0) > 0;
    }

    private function fieldExists(string $table, string $field): bool
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return $this->db->fieldExists($field, $table);
        }

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
