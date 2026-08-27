<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEnglishFieldsToPosts extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('posts')) {
            return;
        }

        $fields = [];

        foreach ([
            'title_en'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'title'],
            'excerpt_en'         => ['type' => 'TEXT', 'null' => true, 'after' => 'excerpt'],
            'body_en'            => ['type' => 'TEXT', 'null' => true, 'after' => 'body'],
            'event_location_en'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'event_location'],
            'seo_title_en'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'seo_title'],
            'seo_description_en' => ['type' => 'TEXT', 'null' => true, 'after' => 'seo_description'],
        ] as $field => $definition) {
            if (! $this->db->fieldExists($field, 'posts')) {
                $fields[$field] = $definition;
            }
        }

        if ($fields !== []) {
            $this->forge->addColumn('posts', $fields);
        }

        $this->backfillEnglishContent();
    }

    public function down(): void
    {
        if (! $this->db->tableExists('posts')) {
            return;
        }

        foreach ([
            'seo_description_en',
            'seo_title_en',
            'event_location_en',
            'body_en',
            'excerpt_en',
            'title_en',
        ] as $field) {
            if ($this->db->fieldExists($field, 'posts')) {
                $this->forge->dropColumn('posts', $field);
            }
        }
    }

    private function backfillEnglishContent(): void
    {
        foreach ($this->translations() as $slug => $translation) {
            $this->db->table('posts')
                ->where('slug', $slug)
                ->update([
                    'title_en'           => $translation['title'],
                    'excerpt_en'         => $translation['excerpt'],
                    'body_en'            => $translation['excerpt'] . "\n\nDemo content translated for the English version; please review before publication.",
                    'event_location_en'  => $translation['event_location'] ?? null,
                    'seo_title_en'       => $translation['title'],
                    'seo_description_en' => $translation['excerpt'],
                ]);
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function translations(): array
    {
        return [
            'journee-portes-ouvertes' => [
                'title'          => 'FSEG Open Day 2026',
                'excerpt'        => 'The Faculty of Economics and Management is organizing its annual open day. Prospective students and families are invited to discover the programmes, meet lecturers, and ask questions about admissions.',
                'event_location' => 'University of Burundi',
            ],
            'conference-internationale' => [
                'title'          => 'International Conference - Economic Development in Africa',
                'excerpt'        => 'FSEG is hosting an international conference bringing together researchers and practitioners working on economic development in Sub-Saharan Africa, with two days of panels, papers, and workshops.',
                'event_location' => 'University of Burundi',
            ],
            'resultats-examens' => [
                'title'   => 'First Semester 2025-2026 Exam Results',
                'excerpt' => 'The results for the first semester of the 2025-2026 academic year are now available. Students can consult them through the University of Burundi online academic portal.',
            ],
            'partenariat-liege' => [
                'title'   => 'Partnership Agreement Signed with the University of Liege',
                'excerpt' => 'FSEG has officially signed an academic cooperation agreement with the University of Liege in Belgium. The partnership will support master student exchanges, thesis co-supervision, and joint research missions.',
            ],
            'revue-fseg-7' => [
                'title'   => 'Issue 7 of the FSEG Review Published',
                'excerpt' => 'The seventh issue of the FSEG Scientific Review has been published, with nine original articles on finance, management, and economic development in Burundi and across Africa.',
            ],
            'prix-excellence-2025' => [
                'title'   => '2025 Academic Excellence Awards Announced',
                'excerpt' => 'The FSEG academic excellence awards were presented during an official ceremony. Twenty students were recognized for outstanding performance during the 2024-2025 academic year.',
            ],
            'seminaire-doctoral' => [
                'title'          => 'Doctoral Seminar - Quantitative Research Methods',
                'excerpt'        => 'A two-day seminar for FSEG doctoral candidates focused on advanced quantitative methods in economics and management sciences, led by Prof. Ndayishimiye and Dr. Dusabimana.',
                'event_location' => 'University of Burundi',
            ],
            'rentree-academique' => [
                'title'          => '2025-2026 Academic Year Opening Ceremony',
                'excerpt'        => 'FSEG welcomed its new students during the official opening ceremony for the 2025-2026 academic year, with remarks from the Dean, programme presentations, and academic agenda distribution.',
                'event_location' => 'University of Burundi',
            ],
            'lancement-innov-emploi' => [
                'title'   => 'Launch of the INNOV-EMPLOI Research Project',
                'excerpt' => 'FSEG has officially launched INNOV-EMPLOI, an African Union-funded research project studying the links between innovation, entrepreneurship, and job creation among young graduates in Africa.',
            ],
        ];
    }
}
