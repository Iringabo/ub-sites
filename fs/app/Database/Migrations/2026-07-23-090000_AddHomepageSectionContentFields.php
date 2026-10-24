<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Throwable;

class AddHomepageSectionContentFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('home_content')) {
            return;
        }

        $fields = [];

        foreach ([
            'programmes_label' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'programmes_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'programmes_text' => ['type' => 'TEXT', 'null' => true],
            'programmes_button_label' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'programmes_button_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'posts_label' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'posts_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'posts_text' => ['type' => 'TEXT', 'null' => true],
            'posts_button_label' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'posts_button_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
        ] as $field => $definition) {
            if (! $this->db->fieldExists($field, 'home_content')) {
                $fields[$field] = $definition;
            }
        }

        if ($fields !== []) {
            $this->forge->addColumn('home_content', $fields);
        }

        if (
            $this->db->fieldExists('programmes_label', 'home_content')
            && $this->db->fieldExists('posts_label', 'home_content')
        ) {
            try {
                $this->db->table('home_content')
                    ->where('singleton_key', 1)
                    ->update([
                        'programmes_label'        => 'Programmes académiques',
                        'programmes_title'        => 'Nos Formations',
                        'programmes_text'         => 'Des cursus complets de la licence au doctorat pour former les experts économiques et financiers de demain.',
                        'programmes_button_label' => 'Voir le programme',
                        'programmes_button_url'   => '/formations',
                        'posts_label'             => 'Restez informé',
                        'posts_title'             => 'Actualités & Événements',
                        'posts_text'              => 'Découvrez les dernières actualités, annonces et événements de la faculté.',
                        'posts_button_label'      => 'Voir tout',
                        'posts_button_url'        => '/actualites',
                    ]);
            } catch (Throwable) {
                // La migration reste reproductible même si le backfill ne peut pas être appliqué.
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('home_content')) {
            return;
        }

        foreach ([
            'posts_button_url',
            'posts_button_label',
            'posts_text',
            'posts_title',
            'posts_label',
            'programmes_button_url',
            'programmes_button_label',
            'programmes_text',
            'programmes_title',
            'programmes_label',
        ] as $field) {
            if ($this->db->fieldExists($field, 'home_content')) {
                try {
                    $this->forge->dropColumn('home_content', $field);
                } catch (Throwable) {
                    // Certaines bases de test peuvent déjà avoir été nettoyées partiellement.
                }
            }
        }
    }
}
