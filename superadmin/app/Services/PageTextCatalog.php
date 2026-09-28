<?php

namespace App\Services;

/**
 * Single source of truth for the small text categories editors see per public page.
 *
 * Pages store their texts either in `pages.content` JSON (storage "page") or in
 * `home_content` columns (storage "home"). Field keys use dot paths inside the JSON.
 *
 * Field types: text, textarea, paragraphs (blank-line separated list), icon, url,
 * image (upload), embed (map URL). Icons, URLs, images and fields flagged `shared`
 * are the same in every language.
 */
class PageTextCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function pages(): array
    {
        return [
            'accueil' => [
                'label'       => 'Accueil',
                'icon'        => 'bi-house-door',
                'public_path' => '/',
                'storage'     => 'home',
                'permission'  => 'home.manage',
                'segments'    => [
                    'carrousel' => [
                        'label'    => 'Carrousel d’images',
                        'icon'     => 'bi-images',
                        'link'     => 'home-hero-slides',
                        'keywords' => ['héros', 'slides', 'diaporama', 'image principale', 'bannière accueil', 'pastilles'],
                    ],
                    'presentation' => [
                        'label'       => 'Présentation',
                        'description' => 'Bloc « À propos » sous le carrousel.',
                        'keywords'    => ['à propos', 'about', 'introduction'],
                        'fields'      => [
                            'about_label'        => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text'],
                            'about_title'        => ['label' => 'Titre', 'type' => 'text'],
                            'about_body'         => ['label' => 'Texte', 'type' => 'textarea'],
                            'about_button_label' => ['label' => 'Texte du bouton', 'type' => 'text'],
                            'about_button_url'   => ['label' => 'Lien du bouton', 'type' => 'url'],
                        ],
                    ],
                    'bloc-recherche' => [
                        'label'       => 'Bloc recherche',
                        'description' => 'Bloc qui présente la recherche sur l’accueil.',
                        'keywords'    => ['laboratoires', 'recherche accueil'],
                        'fields'      => [
                            'research_label'        => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text'],
                            'research_title'        => ['label' => 'Titre', 'type' => 'text'],
                            'research_body'         => ['label' => 'Texte', 'type' => 'textarea'],
                            'research_button_label' => ['label' => 'Texte du bouton', 'type' => 'text'],
                            'research_button_url'   => ['label' => 'Lien du bouton', 'type' => 'url'],
                        ],
                    ],
                    'bloc-formations' => [
                        'label'       => 'Bloc formations',
                        'description' => 'Bloc qui présente les programmes sur l’accueil.',
                        'keywords'    => ['programmes', 'formations accueil', 'parcours'],
                        'fields'      => [
                            'programmes_label'        => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text'],
                            'programmes_title'        => ['label' => 'Titre', 'type' => 'text'],
                            'programmes_text'         => ['label' => 'Texte', 'type' => 'textarea'],
                            'programmes_button_label' => ['label' => 'Texte du bouton', 'type' => 'text'],
                            'programmes_button_url'   => ['label' => 'Lien du bouton', 'type' => 'url'],
                        ],
                    ],
                    'bloc-actualites' => [
                        'label'       => 'Bloc actualités',
                        'description' => 'Bloc des dernières actualités et événements sur l’accueil.',
                        'keywords'    => ['news', 'événements', 'actualités accueil'],
                        'fields'      => [
                            'posts_label'        => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text'],
                            'posts_title'        => ['label' => 'Titre', 'type' => 'text'],
                            'posts_text'         => ['label' => 'Texte', 'type' => 'textarea'],
                            'posts_button_label' => ['label' => 'Texte du bouton', 'type' => 'text'],
                            'posts_button_url'   => ['label' => 'Lien du bouton', 'type' => 'url'],
                        ],
                    ],
                    'ordre-des-blocs' => [
                        'label'    => 'Ordre des blocs',
                        'icon'     => 'bi-list-ol',
                        'link'     => 'home-sections',
                        'keywords' => ['sections', 'afficher', 'masquer', 'ordre', 'mise en page'],
                    ],
                    'referencement' => [
                        'label'       => 'Référencement Google',
                        'description' => 'Titre et description affichés dans les résultats Google pour la page d’accueil.',
                        'keywords'    => ['seo', 'google', 'moteur de recherche', 'meta'],
                        'fields'      => [
                            'seo_title'       => ['label' => 'Titre dans Google', 'type' => 'text', 'required' => true],
                            'seo_description' => ['label' => 'Description dans Google', 'type' => 'textarea', 'required' => true, 'max' => 500],
                        ],
                    ],
                ],
                'lists' => [
                    ['key' => 'site-stats', 'label' => 'Chiffres clés', 'permissions' => 'home.manage', 'icon' => 'bi-bar-chart', 'keywords' => ['statistiques', 'nombres', 'compteurs']],
                ],
            ],
            'faculte' => [
                'label'       => 'La Faculté',
                'icon'        => 'bi-bank2',
                'public_path' => 'faculte',
                'storage'     => 'page',
                'page_key'    => 'faculty',
                'permission'  => 'pages.manage',
                'segments'    => [
                    'bandeau' => $this->bannerSegment(),
                    'mot-du-doyen' => [
                        'label'       => 'Mot du doyen',
                        'description' => 'Photo, identité et message du doyen.',
                        'keywords'    => ['doyen', 'photo', 'message', 'signature', 'dean'],
                        'anchor'      => 'mot-du-doyen',
                        'fields'      => [
                            'dean.photo'      => ['label' => 'Photo du doyen', 'type' => 'image', 'folder' => 'faculty-deans', 'help' => 'Portrait, format carré ou vertical.'],
                            'dean.name'       => ['label' => 'Nom complet', 'type' => 'text', 'required' => true, 'shared' => true],
                            'dean.role'       => ['label' => 'Fonction', 'type' => 'text', 'required' => true],
                            'dean.specialty'  => ['label' => 'Domaine ou département', 'type' => 'text'],
                            'dean.label'      => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text', 'required' => true, 'max' => 120],
                            'dean.title'      => ['label' => 'Titre du message', 'type' => 'text', 'required' => true],
                            'dean.paragraphs' => ['label' => 'Message', 'type' => 'paragraphs', 'required' => true],
                            'dean.signature'  => ['label' => 'Signature', 'type' => 'text'],
                        ],
                    ],
                    'mission-vision' => [
                        'label'       => 'Mission & vision',
                        'description' => 'Les deux blocs Mission et Vision.',
                        'keywords'    => ['mission', 'vision', 'objectifs'],
                        'anchor'      => 'mission-vision',
                        'fields'      => [
                            'mission_label'      => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text', 'required' => true],
                            'mission_title'      => ['label' => 'Titre de la section', 'type' => 'text', 'required' => true],
                            'mission.icon'       => ['label' => 'Icône de la mission', 'type' => 'icon'],
                            'mission.title'      => ['label' => 'Titre de la mission', 'type' => 'text', 'required' => true],
                            'mission.paragraphs' => ['label' => 'Texte de la mission', 'type' => 'paragraphs', 'required' => true],
                            'vision.icon'        => ['label' => 'Icône de la vision', 'type' => 'icon'],
                            'vision.title'       => ['label' => 'Titre de la vision', 'type' => 'text', 'required' => true],
                            'vision.paragraphs'  => ['label' => 'Texte de la vision', 'type' => 'paragraphs', 'required' => true],
                        ],
                    ],
                    'valeurs' => [
                        'label'       => 'Valeurs',
                        'description' => 'Les trois cartes de valeurs sous Mission & vision. Laissez une carte vide pour la masquer.',
                        'keywords'    => ['valeurs', 'excellence', 'intégrité', 'cartes'],
                        'anchor'      => 'valeurs',
                        'compact'     => ['path' => 'values', 'keys' => ['title', 'description']],
                        'fields'      => [
                            'values.0.icon'        => ['label' => 'Icône de la valeur 1', 'type' => 'icon'],
                            'values.0.title'       => ['label' => 'Titre de la valeur 1', 'type' => 'text'],
                            'values.0.description' => ['label' => 'Description de la valeur 1', 'type' => 'textarea', 'max' => 600],
                            'values.1.icon'        => ['label' => 'Icône de la valeur 2', 'type' => 'icon'],
                            'values.1.title'       => ['label' => 'Titre de la valeur 2', 'type' => 'text'],
                            'values.1.description' => ['label' => 'Description de la valeur 2', 'type' => 'textarea', 'max' => 600],
                            'values.2.icon'        => ['label' => 'Icône de la valeur 3', 'type' => 'icon'],
                            'values.2.title'       => ['label' => 'Titre de la valeur 3', 'type' => 'text'],
                            'values.2.description' => ['label' => 'Description de la valeur 3', 'type' => 'textarea', 'max' => 600],
                        ],
                    ],
                    'historique' => [
                        'label'       => 'Historique',
                        'description' => 'Texte d’introduction au-dessus de la frise chronologique.',
                        'keywords'    => ['histoire', 'parcours', 'création', 'historique'],
                        'anchor'      => 'historique',
                        'fields'      => [
                            'history.label' => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text', 'required' => true],
                            'history.title' => ['label' => 'Titre', 'type' => 'text', 'required' => true],
                            'history.text'  => ['label' => 'Texte', 'type' => 'textarea', 'required' => true],
                        ],
                    ],
                ],
                'lists' => [
                    ['key' => 'timeline-items', 'label' => 'Frise chronologique', 'permissions' => 'pages.manage', 'icon' => 'bi-clock-history', 'keywords' => ['dates', 'historique', 'chronologie']],
                ],
            ],
            'formations' => [
                'label'       => 'Formations',
                'icon'        => 'bi-mortarboard',
                'public_path' => 'formations',
                'storage'     => 'page',
                'page_key'    => 'formations',
                'permission'  => 'pages.manage',
                'segments'    => [
                    'bandeau' => $this->bannerSegment(),
                    'presentation-offre' => [
                        'label'       => 'Présentation de l’offre',
                        'description' => 'Texte d’introduction au-dessus de la liste des programmes.',
                        'keywords'    => ['offre', 'programmes', 'introduction'],
                        'anchor'      => 'offre',
                        'fields'      => [
                            'offer_label' => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text', 'required' => true],
                            'offer_title' => ['label' => 'Titre', 'type' => 'text', 'required' => true],
                            'offer_text'  => ['label' => 'Texte', 'type' => 'textarea', 'required' => true],
                        ],
                    ],
                    'appel-candidater' => [
                        'label'       => 'Appel à candidater',
                        'description' => 'Encadré final invitant à postuler.',
                        'keywords'    => ['postuler', 'candidature', 'bouton', 'inscription'],
                        'anchor'      => 'appel',
                        'fields'      => [
                            'cta_title' => ['label' => 'Titre', 'type' => 'text'],
                            'cta_text'  => ['label' => 'Texte', 'type' => 'textarea'],
                            'cta_label' => ['label' => 'Texte du bouton', 'type' => 'text'],
                            'cta_url'   => ['label' => 'Lien du bouton', 'type' => 'url'],
                        ],
                    ],
                ],
                'lists' => [
                    ['key' => 'programmes', 'label' => 'Programmes', 'permissions' => 'programmes.manage', 'icon' => 'bi-journal-bookmark', 'keywords' => ['licence', 'master', 'doctorat', 'cursus']],
                ],
            ],
            'recherche' => [
                'label'       => 'Recherche',
                'icon'        => 'bi-diagram-3',
                'public_path' => 'recherche',
                'storage'     => 'page',
                'page_key'    => 'research',
                'permission'  => 'pages.manage',
                'segments'    => [
                    'bandeau' => $this->bannerSegment(),
                    'section-laboratoires' => [
                        'label'       => 'Section laboratoires',
                        'description' => 'Titre et texte au-dessus des laboratoires.',
                        'keywords'    => ['laboratoires', 'labos', 'équipes'],
                        'anchor'      => 'laboratoires',
                        'fields'      => [
                            'labs_label' => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text', 'required' => true],
                            'labs_title' => ['label' => 'Titre', 'type' => 'text', 'required' => true],
                            'labs_text'  => ['label' => 'Texte', 'type' => 'textarea', 'required' => true],
                        ],
                    ],
                    'section-publications' => [
                        'label'       => 'Section publications',
                        'description' => 'Titre et texte au-dessus des publications.',
                        'keywords'    => ['publications', 'articles scientifiques', 'revues'],
                        'anchor'      => 'publications',
                        'fields'      => [
                            'publications_label' => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text', 'required' => true],
                            'publications_title' => ['label' => 'Titre', 'type' => 'text', 'required' => true],
                            'publications_text'  => ['label' => 'Texte', 'type' => 'textarea'],
                        ],
                    ],
                    'section-projets' => [
                        'label'       => 'Section projets',
                        'description' => 'Titre au-dessus des projets de recherche.',
                        'keywords'    => ['projets', 'financements'],
                        'anchor'      => 'projets',
                        'fields'      => [
                            'projects_label' => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text', 'required' => true],
                            'projects_title' => ['label' => 'Titre', 'type' => 'text', 'required' => true],
                        ],
                    ],
                ],
                'lists' => [
                    ['key' => 'laboratories', 'label' => 'Laboratoires', 'permissions' => 'research.manage', 'icon' => 'bi-diagram-3', 'keywords' => ['labos', 'équipes']],
                    ['key' => 'publications', 'label' => 'Publications', 'permissions' => 'research.manage', 'icon' => 'bi-journal-text', 'keywords' => ['articles', 'doi']],
                    ['key' => 'research-projects', 'label' => 'Projets de recherche', 'permissions' => 'research.manage', 'icon' => 'bi-briefcase', 'keywords' => ['projets']],
                ],
            ],
            'corps-enseignant' => [
                'label'       => 'Corps enseignant',
                'icon'        => 'bi-person-badge',
                'public_path' => 'corps-enseignant',
                'storage'     => 'page',
                'page_key'    => 'staff',
                'permission'  => 'pages.manage',
                'segments'    => [
                    'bandeau' => $this->bannerSegment(),
                ],
                'lists' => [
                    ['key' => 'staff', 'label' => 'Membres du personnel', 'permissions' => 'staff.manage', 'icon' => 'bi-people', 'keywords' => ['enseignants', 'professeurs', 'personnel']],
                ],
            ],
            'actualites' => [
                'label'       => 'Actualités & événements',
                'icon'        => 'bi-megaphone',
                'public_path' => 'actualites',
                'storage'     => 'page',
                'page_key'    => 'posts',
                'permission'  => 'pages.manage',
                'segments'    => [
                    'bandeau' => $this->bannerSegment(),
                ],
                'lists' => [
                    ['key' => 'posts', 'label' => 'Articles et événements', 'permissions' => ['news.manage', 'events.manage'], 'icon' => 'bi-newspaper', 'keywords' => ['news', 'événements', 'publier', 'article']],
                ],
            ],
            'alumni' => [
                'label'       => 'Alumni',
                'icon'        => 'bi-award',
                'public_path' => 'alumni',
                'storage'     => 'page',
                'page_key'    => 'alumni',
                'permission'  => 'pages.manage',
                'segments'    => [
                    'bandeau' => $this->bannerSegment(),
                    'introduction' => [
                        'label'       => 'Introduction',
                        'description' => 'Présentation du réseau des diplômés.',
                        'keywords'    => ['réseau', 'diplômés', 'rejoindre'],
                        'anchor'      => 'introduction',
                        'fields'      => [
                            'intro_label'        => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text', 'required' => true],
                            'intro_title'        => ['label' => 'Titre', 'type' => 'text', 'required' => true],
                            'intro_paragraphs'   => ['label' => 'Texte', 'type' => 'paragraphs', 'required' => true],
                            'intro_button_label' => ['label' => 'Texte du bouton', 'type' => 'text'],
                            'intro_button_url'   => ['label' => 'Lien du bouton', 'type' => 'url'],
                        ],
                    ],
                    'section-profils' => [
                        'label'       => 'Section profils',
                        'description' => 'Titre et texte au-dessus des profils d’alumni.',
                        'keywords'    => ['profils', 'parcours'],
                        'anchor'      => 'profils',
                        'fields'      => [
                            'profiles_label' => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text', 'required' => true],
                            'profiles_title' => ['label' => 'Titre', 'type' => 'text', 'required' => true],
                            'profiles_text'  => ['label' => 'Texte', 'type' => 'textarea'],
                        ],
                    ],
                    'section-temoignages' => [
                        'label'       => 'Section témoignages',
                        'description' => 'Titre au-dessus des témoignages.',
                        'keywords'    => ['témoignages', 'citations', 'avis'],
                        'anchor'      => 'temoignages',
                        'fields'      => [
                            'testimonials_label' => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text', 'required' => true],
                            'testimonials_title' => ['label' => 'Titre', 'type' => 'text', 'required' => true],
                        ],
                    ],
                    'appel-final' => [
                        'label'       => 'Appel final',
                        'description' => 'Encadré final invitant à rejoindre le réseau.',
                        'keywords'    => ['bouton', 'rejoindre', 'contact'],
                        'anchor'      => 'appel',
                        'fields'      => [
                            'cta_title' => ['label' => 'Titre', 'type' => 'text'],
                            'cta_text'  => ['label' => 'Texte', 'type' => 'textarea'],
                            'cta_label' => ['label' => 'Texte du bouton', 'type' => 'text'],
                            'cta_url'   => ['label' => 'Lien du bouton', 'type' => 'url'],
                        ],
                    ],
                ],
                'lists' => [
                    ['key' => 'alumni-profiles', 'label' => 'Profils d’alumni', 'permissions' => 'alumni.manage', 'icon' => 'bi-person-vcard', 'keywords' => ['diplômés', 'anciens']],
                    ['key' => 'testimonials', 'label' => 'Témoignages', 'permissions' => 'alumni.manage', 'icon' => 'bi-chat-quote', 'keywords' => ['citations', 'avis']],
                ],
            ],
            'contact' => [
                'label'       => 'Contact',
                'icon'        => 'bi-geo-alt',
                'public_path' => 'contact',
                'storage'     => 'page',
                'page_key'    => 'contact',
                'permission'  => 'pages.manage',
                'segments'    => [
                    'bandeau' => $this->bannerSegment(),
                    'bloc-coordonnees' => [
                        'label'       => 'Bloc coordonnées',
                        'description' => 'Titres du bloc d’adresse.',
                        'keywords'    => ['adresse', 'coordonnées', 'téléphone'],
                        'anchor'      => 'coordonnees',
                        'note'        => ['text' => 'L’adresse, le téléphone, l’e-mail et les horaires se modifient dans Paramètres du site › Coordonnées.', 'link' => 'settings/contact'],
                        'fields'      => [
                            'contact_label' => ['label' => 'Petit libellé au-dessus du titre', 'type' => 'text', 'required' => true],
                            'contact_title' => ['label' => 'Titre', 'type' => 'text', 'required' => true],
                        ],
                    ],
                    'formulaire' => [
                        'label'       => 'Formulaire',
                        'description' => 'Titre et aide du formulaire de contact.',
                        'keywords'    => ['formulaire', 'message', 'écrire'],
                        'anchor'      => 'formulaire',
                        'fields'      => [
                            'form_title' => ['label' => 'Titre du formulaire', 'type' => 'text', 'required' => true],
                            'form_help'  => ['label' => 'Texte d’aide', 'type' => 'textarea'],
                        ],
                    ],
                    'carte' => [
                        'label'       => 'Carte',
                        'description' => 'Carte Google Maps en bas de la page.',
                        'keywords'    => ['carte', 'google maps', 'plan', 'localisation'],
                        'anchor'      => 'carte',
                        'fields'      => [
                            'map_url' => ['label' => 'Lien d’intégration Google Maps', 'type' => 'embed', 'help' => 'Dans Google Maps : Partager › Intégrer une carte, puis copiez l’adresse qui suit src="…". Laissez vide pour masquer la carte.'],
                        ],
                    ],
                ],
                'lists' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function page(string $pageSlug): ?array
    {
        return $this->pages()[$pageSlug] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function segment(string $pageSlug, string $segmentId): ?array
    {
        $segment = $this->pages()[$pageSlug]['segments'][$segmentId] ?? null;

        return is_array($segment) ? $segment : null;
    }

    /**
     * Segments editable through the text editor (excludes links to other modules).
     *
     * @return array<string, array<string, mixed>>
     */
    public function editableSegments(string $pageSlug): array
    {
        return array_filter(
            (array) ($this->pages()[$pageSlug]['segments'] ?? []),
            static fn (array $segment): bool => ! isset($segment['link']),
        );
    }

    public function segmentUrl(string $pageSlug, string $segmentId): string
    {
        $segment = $this->segment($pageSlug, $segmentId) ?? [];

        return isset($segment['link'])
            ? 'admin/' . $segment['link']
            : 'admin/textes/' . $pageSlug . '/' . $segmentId;
    }

    /**
     * Admin "active" key for a segment, matching `activeAdmin` in views.
     */
    public function activeKey(string $pageSlug, string $segmentId): string
    {
        $segment = $this->segment($pageSlug, $segmentId) ?? [];

        return isset($segment['link']) ? (string) $segment['link'] : 'textes/' . $pageSlug . '/' . $segmentId;
    }

    public function isTranslatable(array $field): bool
    {
        if (! empty($field['shared'])) {
            return false;
        }

        return in_array($field['type'] ?? 'text', ['text', 'textarea', 'paragraphs'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function bannerSegment(): array
    {
        return [
            'label'       => 'Bandeau',
            'icon'        => 'bi-card-image',
            'description' => 'Image de fond et texte affichés sous le menu, en haut de la page. Le fil d’Ariane (Accueil / Page) ne change pas.',
            'keywords'    => ['bannière', 'image de fond', 'titre de la page', 'sous-titre', 'en-tête', 'photo'],
            'fields'      => [
                'banner_image'    => ['label' => 'Image de fond', 'type' => 'image', 'folder' => 'banners', 'help' => 'Photo paysage large (au moins 1600 px de large). Sans image, un fond uni avec le logo est affiché.'],
                'banner_title'    => ['label' => 'Titre', 'type' => 'text', 'help' => 'Laissez vide pour afficher le nom habituel de la page.'],
                'banner_subtitle' => ['label' => 'Sous-titre', 'type' => 'text'],
            ],
        ];
    }
}
