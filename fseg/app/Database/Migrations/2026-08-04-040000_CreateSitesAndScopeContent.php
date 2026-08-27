<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSitesAndScopeContent extends Migration
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

    public function up(): void
    {
        $this->createSites();
        $this->createUserSites();
        $this->addSiteIdToContentTables();
        $this->replaceGlobalUniqueIndexes();
        $this->backfillUserSiteAssignments();
    }

    public function down(): void
    {
        $this->dropSiteScopedUniqueIndexes();

        foreach (array_reverse($this->siteOwnedTables) as $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('site_id', $table)) {
                $this->forge->dropColumn($table, 'site_id');
            }
        }

        $this->forge->dropTable('user_sites', true);
        $this->forge->dropTable('sites', true);
    }

    private function createSites(): void
    {
        if (! $this->db->tableExists('sites')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'identifier'      => ['type' => 'VARCHAR', 'constraint' => 80],
                'name'            => ['type' => 'VARCHAR', 'constraint' => 255],
                'slug'            => ['type' => 'VARCHAR', 'constraint' => 120],
                'hostnames'       => ['type' => 'TEXT', 'null' => true],
                'status'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
                'default_locale'  => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'fr'],
                'logo'            => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'primary_color'   => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => '#0D9B49'],
                'secondary_color' => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => '#0B6F38'],
                'contact_email'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'phone'           => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'address'         => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('identifier', 'sites_identifier_unique');
            $this->forge->addUniqueKey('slug', 'sites_slug_unique');
            $this->forge->addKey(['status', 'slug'], false, false, 'sites_status_slug');
            $this->forge->createTable('sites', true, $this->tableAttributes());
        }

        // Aucun site n'est créé ici : le modèle reste neutre. Les sites
        // facultaires sont créés par provisionnement (php spark site:create,
        // bouton « Nouveau site » du tableau de bord superadmin ou seeder
        // TemplateStarterSeeder).
    }

    private function createUserSites(): void
    {
        if ($this->db->tableExists('user_sites')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'site_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'role'       => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'administrator'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['user_id', 'site_id'], 'user_sites_user_site_unique');
        $this->forge->addKey('site_id', false, false, 'user_sites_site');
        $this->forge->addForeignKey('site_id', 'sites', 'id', 'CASCADE', 'CASCADE');

        if ($this->db->tableExists('users')) {
            $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        }

        $this->forge->createTable('user_sites', true, $this->tableAttributes());
    }

    private function addSiteIdToContentTables(): void
    {
        foreach ($this->siteOwnedTables as $table) {
            if (! $this->db->tableExists($table) || $this->db->fieldExists('site_id', $table)) {
                continue;
            }

            $this->forge->addColumn($table, [
                'site_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 1,
                    'after'      => 'id',
                ],
            ]);
            $this->forge->addKey('site_id', false, false, $table . '_site_id');
            $this->forge->processIndexes($table);
        }
    }

    private function replaceGlobalUniqueIndexes(): void
    {
        foreach ([
            'home_content'          => [['singleton_key'], ['site_id', 'singleton_key'], 'home_content_site_singleton_unique'],
            'posts'                 => [['slug'], ['site_id', 'slug'], 'posts_site_slug_unique'],
            'programmes'            => [['slug'], ['site_id', 'slug'], 'programmes_site_slug_unique'],
            'staff'                 => [['slug'], ['site_id', 'slug'], 'staff_site_slug_unique'],
            'laboratories_slug'     => ['laboratories', ['slug'], ['site_id', 'slug'], 'laboratories_site_slug_unique'],
            'laboratories_abbr'     => ['laboratories', ['abbreviation'], ['site_id', 'abbreviation'], 'laboratories_site_abbreviation_unique'],
            'research_projects'     => [['code'], ['site_id', 'code'], 'research_projects_site_code_unique'],
            'pages_key'             => ['pages', ['key'], ['site_id', 'key'], 'pages_site_key_unique'],
            'pages_slug'            => ['pages', ['slug'], ['site_id', 'slug'], 'pages_site_slug_unique'],
            'settings'              => [['key'], ['site_id', 'key'], 'settings_site_key_unique'],
            'content_translations'  => [['resource_type', 'resource_id', 'locale', 'field'], ['site_id', 'resource_type', 'resource_id', 'locale', 'field'], 'content_translations_site_resource_locale_field'],
        ] as $tableOrKey => $definition) {
            [$table, $oldColumns, $newColumns, $newIndex] = is_string($definition[0])
                ? $definition
                : [$tableOrKey, $definition[0], $definition[1], $definition[2]];

            if (! $this->db->tableExists((string) $table)) {
                continue;
            }

            $this->dropUniqueIndexForColumns((string) $table, $oldColumns);
            $this->addUniqueIndexIfMissing((string) $table, $newColumns, (string) $newIndex);
        }
    }

    private function dropSiteScopedUniqueIndexes(): void
    {
        foreach ([
            'home_content'         => 'home_content_site_singleton_unique',
            'posts'                => 'posts_site_slug_unique',
            'programmes'           => 'programmes_site_slug_unique',
            'staff'                => 'staff_site_slug_unique',
            'laboratories'         => 'laboratories_site_slug_unique',
            'laboratories_abbr'    => 'laboratories_site_abbreviation_unique',
            'research_projects'    => 'research_projects_site_code_unique',
            'pages'                => 'pages_site_key_unique',
            'pages_slug'           => 'pages_site_slug_unique',
            'settings'             => 'settings_site_key_unique',
            'content_translations' => 'content_translations_site_resource_locale_field',
        ] as $table => $index) {
            $table = $table === 'laboratories_abbr' ? 'laboratories' : ($table === 'pages_slug' ? 'pages' : $table);
            $this->dropIndexByName($table, $index);
        }
    }

    private function backfillUserSiteAssignments(): void
    {
        if (! $this->db->tableExists('users') || ! $this->db->tableExists('user_sites')) {
            return;
        }

        // Le rattachement des utilisateurs existants au site 1 n'a de sens
        // que si ce site existe déjà (bases antérieures au modèle multi-sites).
        $siteExists = $this->db->table('sites')->where('id', 1)->countAllResults() > 0;

        if (! $siteExists) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $users = $this->db->table('users')->select('id')->get()->getResultArray();

        foreach ($users as $user) {
            $userId = (int) $user['id'];
            $exists = $this->db->table('user_sites')
                ->where('user_id', $userId)
                ->where('site_id', 1)
                ->countAllResults() > 0;

            if ($exists) {
                continue;
            }

            $this->db->table('user_sites')->insert([
                'user_id'    => $userId,
                'site_id'    => 1,
                'role'       => 'administrator',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param list<string> $columns
     */
    private function dropUniqueIndexForColumns(string $table, array $columns): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }

        $indexes = $this->mysqlIndexes($table);
        foreach ($indexes as $indexName => $index) {
            if ($indexName === 'PRIMARY' || ! $index['unique'] || $index['columns'] !== $columns) {
                continue;
            }

            $this->dropIndexByName($table, $indexName);
        }
    }

    /**
     * @param list<string> $columns
     */
    private function addUniqueIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $this->forge->addUniqueKey($columns, $indexName);
        $this->forge->processIndexes($table);
    }

    private function dropIndexByName(string $table, string $indexName): void
    {
        if (! $this->db->tableExists($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query(
                'ALTER TABLE ' . $this->db->protectIdentifiers($table, true)
                . ' DROP INDEX ' . $this->db->escapeIdentifiers($indexName),
            );
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if ($this->db->DBDriver === 'MySQLi') {
            return array_key_exists($indexName, $this->mysqlIndexes($table));
        }

        foreach ($this->db->getIndexData($table) as $index) {
            if (($index->name ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array{unique: bool, columns: list<string>}>
     */
    private function mysqlIndexes(string $table): array
    {
        $rows = $this->db->query('SHOW INDEX FROM ' . $this->db->protectIdentifiers($table, true))->getResultArray();
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

    /**
     * @return array<string, string>
     */
    private function tableAttributes(): array
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return [];
        }

        return [
            'ENGINE'        => 'InnoDB',
            'CHARACTER SET' => 'utf8mb4',
            'COLLATE'       => 'utf8mb4_unicode_ci',
        ];
    }
}
