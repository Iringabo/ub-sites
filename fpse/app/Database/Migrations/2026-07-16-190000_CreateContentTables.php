<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateContentTables extends Migration
{
    public function up(): void
    {
        $this->createHomeContent();
        $this->createHomeHighlights();
        $this->createPosts();
        $this->createProgrammes();
        $this->createStaff();
        $this->createLaboratories();
        $this->createPublications();
        $this->createResearchProjects();
        $this->createTimelineItems();
        $this->createAlumniProfiles();
        $this->createTestimonials();
        $this->createSiteStats();
        $this->createPages();
        $this->createContactMessages();
    }

    public function down(): void
    {
        foreach ([
            'contact_messages',
            'pages',
            'site_stats',
            'testimonials',
            'alumni_profiles',
            'timeline_items',
            'research_projects',
            'publications',
            'laboratories',
            'staff',
            'programmes',
            'posts',
            'home_highlights',
            'home_content',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function createHomeContent(): void
    {
        $this->forge->addField([
            'id'                   => $this->idField(),
            'singleton_key'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'hero_badge'           => $this->stringField(),
            'hero_title'           => $this->stringField(),
            'hero_text'            => $this->textField(),
            'hero_media_type'      => $this->stringField(40),
            'hero_media_path'      => $this->stringField(500),
            'hero_primary_label'   => $this->stringField(),
            'hero_primary_url'     => $this->stringField(500),
            'hero_secondary_label' => $this->stringField(),
            'hero_secondary_url'   => $this->stringField(500),
            'about_label'          => $this->stringField(),
            'about_title'          => $this->stringField(),
            'about_body'           => $this->textField(),
            'about_button_label'   => $this->nullableStringField(),
            'about_button_url'     => $this->nullableStringField(500),
            'research_label'       => $this->stringField(),
            'research_title'       => $this->stringField(),
            'research_body'        => $this->textField(),
            'research_button_label' => $this->nullableStringField(),
            'research_button_url'  => $this->nullableStringField(500),
            'seo_title'            => $this->stringField(),
            'seo_description'      => $this->textField(),
            'updated_by'           => $this->foreignIdField(),
            'created_at'           => $this->datetimeField(),
            'updated_at'           => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('singleton_key');
        $this->forge->createTable('home_content', true, $this->tableAttributes());
    }

    private function createHomeHighlights(): void
    {
        $this->forge->addField([
            'id'            => $this->idField(),
            'icon'          => $this->stringField(80),
            'title'         => $this->stringField(),
            'description'   => $this->nullableTextField(),
            'display_order' => $this->intField(false, 0),
            'is_published'  => $this->boolField(true),
            'created_at'    => $this->datetimeField(),
            'updated_at'    => $this->datetimeField(),
            'deleted_at'    => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['is_published', 'display_order']);
        $this->forge->createTable('home_highlights', true, $this->tableAttributes());
    }

    private function createPosts(): void
    {
        $this->forge->addField([
            'id'               => $this->idField(),
            'type'             => $this->stringField(20),
            'title'            => $this->stringField(),
            'slug'             => $this->stringField(180),
            'excerpt'          => $this->textField(),
            'body'             => $this->textField(),
            'cover_image'      => $this->nullableStringField(500),
            'status'           => $this->stringField(20, 'draft'),
            'published_at'     => $this->datetimeField(),
            'featured'         => $this->boolField(false),
            'home_order'       => $this->intField(true),
            'event_starts_at'  => $this->datetimeField(),
            'event_ends_at'    => $this->datetimeField(),
            'event_location'   => $this->nullableStringField(),
            'registration_url' => $this->nullableStringField(500),
            'seo_title'        => $this->nullableStringField(),
            'seo_description'  => $this->nullableTextField(),
            'created_by'       => $this->foreignIdField(),
            'updated_by'       => $this->foreignIdField(),
            'created_at'       => $this->datetimeField(),
            'updated_at'       => $this->datetimeField(),
            'deleted_at'       => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['type', 'status', 'published_at']);
        $this->forge->addKey(['featured', 'home_order']);
        $this->forge->addKey('event_starts_at');
        $this->forge->createTable('posts', true, $this->tableAttributes());
    }

    private function createProgrammes(): void
    {
        $this->forge->addField([
            'id'                   => $this->idField(),
            'level'                => $this->stringField(40),
            'title'                => $this->stringField(),
            'slug'                 => $this->stringField(180),
            'duration'             => $this->stringField(80),
            'summary'              => $this->textField(),
            'description'          => $this->textField(),
            'admission_conditions' => $this->textField(),
            'career_outcomes'      => $this->textField(),
            'display_order'        => $this->intField(false, 0),
            'featured_on_home'     => $this->boolField(false),
            'home_order'           => $this->intField(true),
            'is_published'         => $this->boolField(true),
            'created_at'           => $this->datetimeField(),
            'updated_at'           => $this->datetimeField(),
            'deleted_at'           => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['level', 'is_published', 'display_order']);
        $this->forge->addKey(['featured_on_home', 'home_order']);
        $this->forge->createTable('programmes', true, $this->tableAttributes());
    }

    private function createStaff(): void
    {
        $this->forge->addField([
            'id'            => $this->idField(),
            'category'      => $this->stringField(40),
            'name'          => $this->stringField(),
            'slug'          => $this->stringField(180),
            'photo'         => $this->nullableStringField(500),
            'grade'         => $this->nullableStringField(),
            'specialty'     => $this->nullableStringField(),
            'role'          => $this->nullableStringField(),
            'email'         => $this->nullableStringField(),
            'biography'        => $this->nullableTextField(),
            'display_order'    => $this->intField(false, 0),
            'featured_on_home' => $this->boolField(false),
            'home_order'       => $this->intField(true),
            'is_published'     => $this->boolField(true),
            'created_at'       => $this->datetimeField(),
            'updated_at'       => $this->datetimeField(),
            'deleted_at'       => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['category', 'is_published', 'display_order']);
        $this->forge->addKey(['featured_on_home', 'home_order']);
        $this->forge->createTable('staff', true, $this->tableAttributes());
    }

    private function createLaboratories(): void
    {
        $this->forge->addField([
            'id'               => $this->idField(),
            'abbreviation'     => $this->stringField(40),
            'name'             => $this->stringField(),
            'slug'             => $this->stringField(180),
            'icon'             => $this->nullableStringField(80),
            'description'      => $this->textField(),
            'themes'           => $this->textField(),
            'researcher_count' => $this->intField(false, 0),
            'display_order'    => $this->intField(false, 0),
            'featured_on_home' => $this->boolField(false),
            'home_order'       => $this->intField(true),
            'is_published'     => $this->boolField(true),
            'created_at'       => $this->datetimeField(),
            'updated_at'       => $this->datetimeField(),
            'deleted_at'       => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addUniqueKey('abbreviation');
        $this->forge->addKey(['is_published', 'display_order']);
        $this->forge->addKey(['featured_on_home', 'home_order']);
        $this->forge->createTable('laboratories', true, $this->tableAttributes());
    }

    private function createPublications(): void
    {
        $this->forge->addField([
            'id'            => $this->idField(),
            'year'          => $this->intField(false),
            'title'         => $this->stringField(500),
            'authors'       => $this->textField(),
            'journal'       => $this->stringField(),
            'url'           => $this->nullableStringField(500),
            'display_order' => $this->intField(false, 0),
            'is_published'  => $this->boolField(true),
            'created_at'    => $this->datetimeField(),
            'updated_at'    => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['is_published', 'year', 'display_order']);
        $this->forge->createTable('publications', true, $this->tableAttributes());
    }

    private function createResearchProjects(): void
    {
        $this->forge->addField([
            'id'            => $this->idField(),
            'code'          => $this->stringField(80),
            'title'         => $this->stringField(),
            'description'   => $this->textField(),
            'funder'        => $this->nullableStringField(),
            'period_start'  => $this->intField(true),
            'period_end'    => $this->intField(true),
            'icon'          => $this->nullableStringField(80),
            'display_order' => $this->intField(false, 0),
            'is_published'  => $this->boolField(true),
            'created_at'    => $this->datetimeField(),
            'updated_at'    => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addKey(['is_published', 'display_order']);
        $this->forge->createTable('research_projects', true, $this->tableAttributes());
    }

    private function createTimelineItems(): void
    {
        $this->forge->addField([
            'id'            => $this->idField(),
            'year'          => $this->intField(false),
            'title'         => $this->stringField(),
            'description'   => $this->textField(),
            'display_order' => $this->intField(false, 0),
            'is_published'  => $this->boolField(true),
            'created_at'    => $this->datetimeField(),
            'updated_at'    => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['is_published', 'display_order']);
        $this->forge->createTable('timeline_items', true, $this->tableAttributes());
    }

    private function createAlumniProfiles(): void
    {
        $this->forge->addField([
            'id'            => $this->idField(),
            'name'          => $this->stringField(),
            'slug'          => $this->stringField(180),
            'photo'         => $this->nullableStringField(500),
            'promotion'     => $this->nullableStringField(80),
            'role'          => $this->nullableStringField(),
            'organization'  => $this->nullableStringField(),
            'biography'     => $this->nullableTextField(),
            'display_order' => $this->intField(false, 0),
            'is_published'  => $this->boolField(true),
            'created_at'    => $this->datetimeField(),
            'updated_at'    => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['is_published', 'display_order']);
        $this->forge->createTable('alumni_profiles', true, $this->tableAttributes());
    }

    private function createTestimonials(): void
    {
        $this->forge->addField([
            'id'                => $this->idField(),
            'alumni_profile_id' => $this->foreignIdField(),
            'person_name'       => $this->stringField(),
            'photo'             => $this->nullableStringField(500),
            'promotion'         => $this->nullableStringField(80),
            'quote'             => $this->textField(),
            'display_order'     => $this->intField(false, 0),
            'is_published'      => $this->boolField(true),
            'created_at'        => $this->datetimeField(),
            'updated_at'        => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['is_published', 'display_order']);
        $this->forge->addForeignKey('alumni_profile_id', 'alumni_profiles', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('testimonials', true, $this->tableAttributes());
    }

    private function createSiteStats(): void
    {
        $this->forge->addField([
            'id'            => $this->idField(),
            'section'       => $this->stringField(80),
            'label'         => $this->stringField(),
            'value'         => $this->intField(false, 0),
            'suffix'        => $this->nullableStringField(20),
            'display_order' => $this->intField(false, 0),
            'is_published'  => $this->boolField(true),
            'created_at'    => $this->datetimeField(),
            'updated_at'    => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['section', 'is_published', 'display_order']);
        $this->forge->createTable('site_stats', true, $this->tableAttributes());
    }

    private function createPages(): void
    {
        $this->forge->addField([
            'id'              => $this->idField(),
            'key'             => $this->stringField(120),
            'title'           => $this->stringField(),
            'slug'            => $this->stringField(180),
            'content'         => $this->textField(),
            'seo_title'       => $this->nullableStringField(),
            'seo_description' => $this->nullableTextField(),
            'is_published'    => $this->boolField(true),
            'created_at'      => $this->datetimeField(),
            'updated_at'      => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('key');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('is_published');
        $this->forge->createTable('pages', true, $this->tableAttributes());
    }

    private function createContactMessages(): void
    {
        $this->forge->addField([
            'id'           => $this->idField(),
            'name'         => $this->stringField(),
            'email'        => $this->stringField(),
            'phone'        => $this->nullableStringField(80),
            'subject'      => $this->stringField(),
            'message'      => $this->textField(),
            'status'       => $this->stringField(20, 'new'),
            'ip_address'   => $this->nullableStringField(64),
            'user_agent'   => $this->nullableStringField(500),
            'read_at'      => $this->datetimeField(),
            'processed_by' => $this->foreignIdField(),
            'created_at'   => $this->datetimeField(),
            'updated_at'   => $this->datetimeField(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->createTable('contact_messages', true, $this->tableAttributes());
    }

    /**
     * @return array<string, mixed>
     */
    private function idField(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function foreignIdField(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function stringField(int $constraint = 255, ?string $default = null): array
    {
        $field = ['type' => 'VARCHAR', 'constraint' => $constraint];

        if ($default !== null) {
            $field['default'] = $default;
        }

        return $field;
    }

    /**
     * @return array<string, mixed>
     */
    private function nullableStringField(int $constraint = 255): array
    {
        return ['type' => 'VARCHAR', 'constraint' => $constraint, 'null' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function textField(): array
    {
        return ['type' => 'TEXT'];
    }

    /**
     * @return array<string, mixed>
     */
    private function nullableTextField(): array
    {
        return ['type' => 'TEXT', 'null' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function intField(bool $nullable, ?int $default = null): array
    {
        $field = ['type' => 'INT', 'constraint' => 11, 'null' => $nullable];

        if ($default !== null) {
            $field['default'] = $default;
        }

        return $field;
    }

    /**
     * @return array<string, mixed>
     */
    private function boolField(bool $default): array
    {
        return ['type' => 'TINYINT', 'constraint' => 1, 'default' => $default ? 1 : 0];
    }

    /**
     * @return array<string, mixed>
     */
    private function datetimeField(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
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
