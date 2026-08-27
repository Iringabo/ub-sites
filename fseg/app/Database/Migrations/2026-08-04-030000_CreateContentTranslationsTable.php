<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateContentTranslationsTable extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('content_translations')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'resource_type' => ['type' => 'VARCHAR', 'constraint' => 80],
                'resource_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'locale'        => ['type' => 'VARCHAR', 'constraint' => 8],
                'field'         => ['type' => 'VARCHAR', 'constraint' => 120],
                'value'         => ['type' => 'MEDIUMTEXT', 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['resource_type', 'resource_id', 'locale', 'field'], 'content_translations_resource_locale_field');
            $this->forge->addKey(['resource_type', 'resource_id', 'locale'], false, false, 'content_translations_resource_locale');
            $this->forge->createTable('content_translations', true, $this->tableAttributes());
        }

        $this->backfillPostTranslations();
        $this->cleanDemoEnglishPostBodies();
    }

    public function down(): void
    {
        $this->forge->dropTable('content_translations', true);
    }

    private function backfillPostTranslations(): void
    {
        if (! $this->db->tableExists('posts')) {
            return;
        }

        $fieldMap = [
            'title_en'           => 'title',
            'excerpt_en'         => 'excerpt',
            'body_en'            => 'body',
            'event_location_en'  => 'event_location',
            'seo_title_en'       => 'seo_title',
            'seo_description_en' => 'seo_description',
        ];

        $select = ['id'];
        foreach (array_keys($fieldMap) as $legacyField) {
            if ($this->db->fieldExists($legacyField, 'posts')) {
                $select[] = $legacyField;
            }
        }

        if (count($select) === 1) {
            return;
        }

        $posts = $this->db->table('posts')
            ->select($select)
            ->get()
            ->getResultArray();

        foreach ($posts as $post) {
            foreach ($fieldMap as $legacyField => $translationField) {
                if (! array_key_exists($legacyField, $post)) {
                    continue;
                }

                $value = trim((string) $post[$legacyField]);
                if ($value === '') {
                    continue;
                }

                if ($legacyField === 'body_en' && stripos($value, 'please review before publication') !== false) {
                    continue;
                }

                $this->insertTranslation('posts', (int) $post['id'], 'en', $translationField, $value);
            }
        }
    }

    private function cleanDemoEnglishPostBodies(): void
    {
        if (! $this->db->tableExists('posts') || ! $this->db->fieldExists('body_en', 'posts')) {
            return;
        }

        $this->db->table('posts')
            ->like('body_en', 'please review before publication')
            ->update(['body_en' => null]);
    }

    private function insertTranslation(string $resourceType, int $resourceId, string $locale, string $field, string $value): void
    {
        $exists = $this->db->table('content_translations')
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('locale', $locale)
            ->where('field', $field)
            ->countAllResults() > 0;

        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('content_translations')->insert([
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'locale'        => $locale,
            'field'         => $field,
            'value'         => $value,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
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
