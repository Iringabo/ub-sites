<?php

namespace App\Services;

/**
 * Demo catalogs for the three faculties added after the first five.
 *
 * Theme keys (not the public URL slugs):
 * - nat  Faculté des Sciences, slug fs
 * - psy  Faculté de Psychologie et des Sciences de l’Éducation, slug fpse
 * - law  Faculté des Sciences politiques et juridiques, slug fspj
 *
 * Department names and degree titles come from public faculty or university
 * pages. People, news, partners, projects, alumni and statistics are fictional.
 * An unknown key returns null so another faculty’s text cannot be substituted.
 */
class FacultyDemoAddedFaculties
{
    /** @var array<string, string> */
    private const SLUGS = [
        'fs'   => 'nat',
        'fpse' => 'psy',
        'fspj' => 'law',
    ];

    public function themeKeyForSlug(string $slug): ?string
    {
        return self::SLUGS[strtolower(trim($slug))] ?? null;
    }

    public function has(string $key): bool
    {
        return in_array($key, self::SLUGS, true);
    }

    public function shortLabel(string $key): ?string
    {
        return match ($key) {
            'nat' => 'FS',
            'psy' => 'FPSE',
            'law' => 'FSPJ',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(string $key): ?array
    {
        return match ($key) {
            'nat' => $this->metaSciences(),
            'psy' => $this->metaPsychology(),
            'law' => $this->metaLaw(),
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function programmes(string $key): ?array
    {
        return match ($key) {
            'nat' => $this->programmesSciences(),
            'psy' => $this->programmesPsychology(),
            'law' => $this->programmesLaw(),
            default => null,
        };
    }

    /**
     * @return array{licence: string, master: string}|null
     */
    public function admission(string $key): ?array
    {
        return match ($key) {
            'nat' => [
                'licence' => 'Diplôme d’État de la section scientifique, avec les points fixés par le ministère ; orientation vers la Faculté des Sciences.',
                'master'  => 'Baccalauréat (licence) dans le département concerné et sélection sur dossier.',
            ],
            'psy' => [
                'licence' => 'Diplôme d’État avec les points fixés par le ministère ; orientation vers la Faculté de Psychologie et des Sciences de l’Éducation.',
                'master'  => 'Baccalauréat (licence) en psychologie ou en sciences de l’éducation et sélection sur dossier.',
            ],
            'law' => [
                'licence' => 'Diplôme d’État avec les points fixés par le ministère ; orientation vers la Faculté des Sciences politiques et juridiques.',
                'master'  => 'Baccalauréat (licence) en science politique ou en droit, selon le master, et sélection sur dossier.',
            ],
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function staff(string $key): ?array
    {
        return match ($key) {
            'nat' => $this->staffSciences(),
            'psy' => $this->staffPsychology(),
            'law' => $this->staffLaw(),
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function posts(string $key): ?array
    {
        return match ($key) {
            'nat' => $this->postsSciences(),
            'psy' => $this->postsPsychology(),
            'law' => $this->postsLaw(),
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function labs(string $key): ?array
    {
        return match ($key) {
            'nat' => [
                ['abbr' => 'CRSNE', 'name' => 'Centre de recherche en sciences naturelles et environnement', 'slug' => 'crsne', 'icon' => 'bi-tree', 'description' => 'Le CRSNE est un centre de recherche de la Faculté des Sciences. Il réunit des travaux de biologie et d’environnement autour des paysages, des organismes et de la gestion des milieux.', 'themes' => ['Biologie des organismes', 'Écologie', 'Environnement'], 'researchers' => 8],
                ['abbr' => 'CRMP', 'name' => 'Centre de recherche en mathématiques et physique', 'slug' => 'crmp', 'icon' => 'bi-calculator', 'description' => 'Le CRMP est un centre de recherche de la Faculté des Sciences. Il accueille les travaux des départements de Mathématiques et de Physique.', 'themes' => ['Mathématiques', 'Physique', 'Modélisation'], 'researchers' => 6],
                ['abbr' => 'ATP', 'name' => 'Atelier de travaux pratiques', 'slug' => 'atelier-tp-fs', 'icon' => 'bi-eyedropper', 'description' => 'Salle de travaux pratiques partagée par les enseignements de chimie, de biologie et de sciences de la Terre. Les groupes y préparent les solutions, les montages et les comptes rendus.', 'themes' => ['Travaux pratiques', 'Mesures', 'Sécurité de laboratoire'], 'researchers' => 4],
            ],
            'psy' => [
                ['abbr' => 'ATP', 'name' => 'Atelier de travaux pratiques en psychologie', 'slug' => 'atelier-psychologie', 'icon' => 'bi-people', 'description' => 'Espace de travaux dirigés pour les enseignements de psychologie. Les étudiants y préparent des jeux de rôle et des comptes rendus d’entretien.', 'themes' => ['Entretien', 'Observation', 'Méthodes'], 'researchers' => 4],
                ['abbr' => 'ASE', 'name' => 'Atelier de sciences de l’éducation', 'slug' => 'atelier-education', 'icon' => 'bi-journal-text', 'description' => 'Salle où les étudiants préparent des séquences de cours et analysent des situations de classe. On y prépare une leçon, puis on en discute le déroulement.', 'themes' => ['Didactique', 'Observation de classe', 'Mémoire'], 'researchers' => 3],
                ['abbr' => 'DOC', 'name' => 'Salle de documentation pédagogique', 'slug' => 'documentation-fpse', 'icon' => 'bi-book', 'description' => 'Fonds de manuels et de mémoires consultable par les étudiants des deux départements. Les mémoires de l’année s’y consultent sur place.', 'themes' => ['Mémoires', 'Manuels', 'Lecture'], 'researchers' => 2],
            ],
            'law' => [
                ['abbr' => 'ADJ', 'name' => 'Atelier de documentation juridique', 'slug' => 'documentation-juridique', 'icon' => 'bi-bank', 'description' => 'Salle de lecture pour les codes, les recueils et les mémoires de droit. On y prépare les fiches de lecture et les commentaires de décisions.', 'themes' => ['Droit judiciaire', 'Sources', 'Mémoires'], 'researchers' => 3],
                ['abbr' => 'ASP', 'name' => 'Atelier de science politique', 'slug' => 'atelier-science-politique', 'icon' => 'bi-globe', 'description' => 'Salle de séminaire pour les cours de science politique et de relations internationales. Les exposés du département s’y préparent.', 'themes' => ['Institutions', 'Relations internationales', 'Séminaire'], 'researchers' => 3],
                ['abbr' => 'SIM', 'name' => 'Salle de simulation', 'slug' => 'salle-simulation-fspj', 'icon' => 'bi-chat-square-text', 'description' => 'Salle où les étudiants s’exercent aux exposés et aux jeux de rôle de négociation. Les délégations y répètent une négociation avant le compte rendu.', 'themes' => ['Exposé', 'Négociation', 'Méthode'], 'researchers' => 2],
            ],
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function publications(string $key): ?array
    {
        $rows = [
            'nat' => [
                [2025, 'Relevés de terrain sur un paysage collinaire du Burundi', 'Bukuru A., Nibigira I.', 'Cahiers d’écologie'],
                [2025, 'Notes de cours sur la chimie des solutions aqueuses', 'Ntahomvukiye B.', 'Feuillets de travaux pratiques'],
                [2024, 'Lecture d’une carte géologique simplifiée pour un TP', 'Niyongabo F.', 'Bulletin pédagogique de sciences de la Terre'],
                [2024, 'Exercices corrigés d’analyse pour le baccalauréat', 'Kwizera G., Niyungeko L.', 'Cahier de mathématiques'],
                [2023, 'Montage d’une mesure de tension en salle de TP', 'Ndayiziga H.', 'Notes de physique expérimentale'],
                [2023, 'Compte rendu d’un stage d’observation en laboratoire', 'Nibigira I.', 'Carnets d’étudiants en biologie'],
            ],
            'psy' => [
                [2025, 'Grille d’observation d’un entretien pédagogique', 'Baranyanka C.', 'Cahiers de travaux dirigés'],
                [2025, 'Notes sur l’accueil d’un groupe en services communautaires', 'Uwimana S.', 'Feuillets de psychologie'],
                [2024, 'Préparation d’une séquence de cours au fondamental', 'Hakizimana P.', 'Atelier de sciences de l’éducation'],
                [2024, 'Lecture commentée d’un mémoire de master', 'Ndayisaba J.', 'Séminaire de recherche'],
                [2023, 'Repères pour conduire un exposé de psychologie', 'Kanuma E.', 'Guide d’étudiants'],
                [2023, 'Questions d’examen commentées en sciences de l’éducation', 'Ciza M.', 'Feuillets pédagogiques'],
            ],
            'law' => [
                [2025, 'Fiche de lecture sur une décision de justice fictive', 'Nahimana A.', 'Atelier de documentation juridique'],
                [2025, 'Plan de dissertation en relations internationales', 'Kagabo L.', 'Séminaire de science politique'],
                [2024, 'Commentaire d’un article de code pour un TD', 'Ciza D.', 'Feuillets de droit judiciaire'],
                [2024, 'Note de méthode pour un exposé de science politique', 'Ndayisenga E.', 'Cahier du département'],
                [2023, 'Relevé de sources pour un mémoire de master', 'Nduwimana C.', 'Guide de documentation'],
                [2023, 'Jeu de rôle : compte rendu d’une négociation simulée', 'Bukuru T.', 'Salle de simulation'],
            ],
        ];
        if (! isset($rows[$key])) {
            return null;
        }

        $out = [];
        foreach ($rows[$key] as [$year, $title, $authors, $journal]) {
            $out[] = ['year' => $year, 'title' => $title, 'authors' => $authors, 'journal' => $journal];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function projects(string $key): ?array
    {
        return match ($key) {
            'nat' => [
                ['title' => 'Carnet de paysages', 'description' => 'Projet pédagogique où des étudiants de biologie décrivent un milieu et en tirent une note de terrain.', 'funder' => 'Faculté', 'start' => 2024, 'end' => 2026, 'code' => 'PAYSAGE-TP', 'icon' => 'bi-tree'],
                ['title' => 'Mesures au laboratoire', 'description' => 'Renouvellement du petit matériel de l’atelier de travaux pratiques de chimie et de physique.', 'funder' => 'Faculté', 'start' => 2023, 'end' => 2025, 'code' => 'MESURE-TP', 'icon' => 'bi-eyedropper'],
                ['title' => 'Cartes pour le cours de sciences de la Terre', 'description' => 'Préparation de planches simplifiées utilisées en travaux dirigés.', 'funder' => 'Faculté', 'start' => 2025, 'end' => 2027, 'code' => 'CARTE-TD', 'icon' => 'bi-map'],
                ['title' => 'Club de problèmes', 'description' => 'Séances hebdomadaires de résolution de problèmes animées par le département de Mathématiques.', 'funder' => 'Faculté', 'start' => 2022, 'end' => 2026, 'code' => 'CLUB-MATH', 'icon' => 'bi-calculator'],
            ],
            'psy' => [
                ['title' => 'Ateliers d’écoute', 'description' => 'Entraînement à l’entretien pour les étudiants de l’option Services communautaires. Les situations sont préparées pour l’exercice.', 'funder' => 'Faculté', 'start' => 2024, 'end' => 2026, 'code' => 'ECOUTE-TD', 'icon' => 'bi-chat-dots'],
                ['title' => 'Séquences de classe', 'description' => 'Préparation et analyse de courtes leçons par les étudiants en sciences de l’éducation.', 'funder' => 'Faculté', 'start' => 2023, 'end' => 2026, 'code' => 'SEQUENCE', 'icon' => 'bi-easel'],
                ['title' => 'Séminaire de mémoire', 'description' => 'Rencontres mensuelles où les étudiants de master présentent l’état de leur travail.', 'funder' => 'Faculté', 'start' => 2025, 'end' => 2027, 'code' => 'MEMOIRE', 'icon' => 'bi-journal-richtext'],
                ['title' => 'Fonds de manuels', 'description' => 'Classement des ouvrages de la salle de documentation pédagogique.', 'funder' => 'Faculté', 'start' => 2022, 'end' => 2025, 'code' => 'MANUELS', 'icon' => 'bi-book'],
            ],
            'law' => [
                ['title' => 'Commentaires de décisions', 'description' => 'Série de travaux dirigés où les étudiants commentent des décisions d’école, rédigées pour l’exercice.', 'funder' => 'Faculté', 'start' => 2024, 'end' => 2026, 'code' => 'ARRET-TD', 'icon' => 'bi-bank'],
                ['title' => 'Simulation de négociation', 'description' => 'Jeu de rôle annuel des étudiants en relations internationales.', 'funder' => 'Faculté', 'start' => 2023, 'end' => 2026, 'code' => 'NEGO-SIM', 'icon' => 'bi-globe'],
                ['title' => 'Fiches de sources', 'description' => 'Constitution d’un classeur de références pour les mémoires de droit judiciaire.', 'funder' => 'Faculté', 'start' => 2025, 'end' => 2027, 'code' => 'SOURCES', 'icon' => 'bi-folder2-open'],
                ['title' => 'Exposés de science politique', 'description' => 'Cycle d’exposés étudiants sur les institutions, préparés en salle de séminaire.', 'funder' => 'Faculté', 'start' => 2022, 'end' => 2026, 'code' => 'EXPOSE-SP', 'icon' => 'bi-mic'],
            ],
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function timeline(string $key): ?array
    {
        return match ($key) {
            'nat' => [
                ['year' => 1961, 'title' => 'Création', 'description' => 'La Faculté des Sciences indique qu’elle a été créée en 1961-1962, alors que l’établissement s’appelait encore Centre universitaire d’Usumbura.'],
            ],
            'psy', 'law' => [],
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function alumni(string $key): ?array
    {
        return match ($key) {
            'nat' => [
                ['name' => 'Sandrine Niyonzima', 'slug' => 'alumni-sandrine-niyo', 'role' => 'Enseignante de sciences', 'organization' => 'Lycée', 'year' => 2016, 'bio' => 'Sandrine Niyonzima (promotion 2016) enseigne la biologie dans un lycée.'],
                ['name' => 'Pascal Habonimana', 'slug' => 'alumni-pascal-habo', 'role' => 'Technicien de laboratoire', 'organization' => 'Atelier municipal', 'year' => 2018, 'bio' => 'Pascal Habonimana (promotion 2018) prépare des solutions et tient le cahier de sécurité d’un atelier.'],
                ['name' => 'Eliane Ndarusanze', 'slug' => 'alumni-eliane-nda', 'role' => 'Chargée d’études', 'organization' => 'Bureau d’études', 'year' => 2014, 'bio' => 'Eliane Ndarusanze (promotion 2014) rédige des notes sur l’eau et les sols.'],
                ['name' => 'Justin Niyungeko', 'slug' => 'alumni-justin-niyu', 'role' => 'Professeur de mathématiques', 'organization' => 'École normale', 'year' => 2012, 'bio' => 'Justin Niyungeko (promotion 2012) prépare les élèves aux épreuves scientifiques.'],
            ],
            'psy' => [
                ['name' => 'Aline Niyonkuru', 'slug' => 'alumni-aline-niyon', 'role' => 'Conseillère d’orientation', 'organization' => 'Établissement scolaire', 'year' => 2017, 'bio' => 'Aline Niyonkuru (promotion 2017) reçoit des élèves et leurs familles.'],
                ['name' => 'Christian Ndikumana', 'slug' => 'alumni-christian-ndi', 'role' => 'Formateur d’enseignants', 'organization' => 'Centre de formation', 'year' => 2015, 'bio' => 'Christian Ndikumana (promotion 2015) anime des ateliers de préparation de leçons.'],
                ['name' => 'Josiane Nshimirimana', 'slug' => 'alumni-josiane-nshi', 'role' => 'Animatrice communautaire', 'organization' => 'Association de quartier', 'year' => 2019, 'bio' => 'Josiane Nshimirimana (promotion 2019) organise des groupes de parole.'],
                ['name' => 'Eric Ndayishimiye', 'slug' => 'alumni-eric-ndayi', 'role' => 'Enseignant', 'organization' => 'École fondamentale', 'year' => 2013, 'bio' => 'Eric Ndayishimiye (promotion 2013) enseigne au fondamental.'],
            ],
            'law' => [
                ['name' => 'Diane Niyongabo', 'slug' => 'alumni-diane-niyo', 'role' => 'Juriste', 'organization' => 'Cabinet', 'year' => 2016, 'bio' => 'Diane Niyongabo (promotion 2016) prépare des dossiers pour un cabinet.'],
                ['name' => 'Olivier Ntahondi', 'slug' => 'alumni-olivier-nta', 'role' => 'Chargé de programme', 'organization' => 'Association', 'year' => 2018, 'bio' => 'Olivier Ntahondi (promotion 2018) suit des dossiers de coopération.'],
                ['name' => 'Carine Hakizimana', 'slug' => 'alumni-carine-haki', 'role' => 'Rédactrice', 'organization' => 'Revue étudiante', 'year' => 2020, 'bio' => 'Carine Hakizimana (promotion 2020) relit des notes de science politique.'],
                ['name' => 'Bosco Nduwimana', 'slug' => 'alumni-bosco-ndu', 'role' => 'Greffier stagiaire', 'organization' => 'Juridiction', 'year' => 2014, 'bio' => 'Bosco Nduwimana (promotion 2014) apprend le suivi d’un rôle d’audience.'],
            ],
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function testimonials(string $key): ?array
    {
        return match ($key) {
            'nat' => [
                ['name' => 'Sandrine Niyonzima', 'promotion' => 'Promotion 2016', 'quote' => 'Les séances de travaux pratiques m’ont appris à noter une observation avant de conclure.'],
                ['name' => 'Pascal Habonimana', 'promotion' => 'Promotion 2018', 'quote' => 'J’ai gardé l’habitude du cahier de laboratoire, utile dès le premier emploi.'],
                ['name' => 'Eliane Ndarusanze', 'promotion' => 'Promotion 2014', 'quote' => 'Le mémoire m’a obligée à relier une question de terrain à des mesures simples.'],
            ],
            'psy' => [
                ['name' => 'Aline Niyonkuru', 'promotion' => 'Promotion 2017', 'quote' => 'Les jeux de rôle m’ont appris à écouter une phrase jusqu’au bout.'],
                ['name' => 'Christian Ndikumana', 'promotion' => 'Promotion 2015', 'quote' => 'Préparer une leçon devant les camarades m’a servi plus tard en formation d’adultes.'],
                ['name' => 'Josiane Nshimirimana', 'promotion' => 'Promotion 2019', 'quote' => 'Le séminaire de master m’a donné une méthode pour écrire sans me presser.'],
            ],
            'law' => [
                ['name' => 'Diane Niyongabo', 'promotion' => 'Promotion 2016', 'quote' => 'Commenter une décision à voix haute m’a appris à distinguer le fait et la règle.'],
                ['name' => 'Olivier Ntahondi', 'promotion' => 'Promotion 2018', 'quote' => 'Les exposés de relations internationales m’ont habituée à citer mes sources.'],
                ['name' => 'Bosco Nduwimana', 'promotion' => 'Promotion 2014', 'quote' => 'Le mémoire de droit judiciaire m’a appris à tenir un dossier dans l’ordre.'],
            ],
            default => null,
        };
    }

    /**
     * Finished campus block, including map_url. FPSE and FSPJ have no published
     * building, so the map stays empty rather than a guessed pin.
     *
     * @return array<string, mixed>|null
     */
    public function campus(string $key): ?array
    {
        if ($key === 'nat') {
            $label = 'FS — Campus Mutanga, Université du Burundi';
            $lat = -3.376061;
            $lon = 29.383330;
            $zoom = 17;
            $q = rawurlencode(sprintf('%.6f,%.6f (%s)', $lat, $lon, $label));

            return [
                'label' => $label,
                'address_line' => 'Campus Mutanga, B.P. 2700, Bujumbura',
                'commune' => 'Mukaza',
                'province' => 'Bujumbura Mairie',
                'country' => 'Burundi',
                'phone' => '+257 22 22 55 56',
                'email_local' => 'fs',
                'hours' => 'Lun–Ven 07:30–16:30',
                'lat' => $lat,
                'lon' => $lon,
                'zoom' => $zoom,
                'map_url' => "https://www.google.com/maps?q={$q}&hl=fr&z={$zoom}&output=embed",
            ];
        }

        if ($key === 'psy' || $key === 'law') {
            $name = $key === 'psy'
                ? 'Faculté de Psychologie et des Sciences de l’Éducation'
                : 'Faculté des Sciences politiques et juridiques';

            return [
                'label' => $name . ' — Université du Burundi',
                'address_line' => 'Université du Burundi, Bujumbura',
                'commune' => '',
                'province' => 'Bujumbura Mairie',
                'country' => 'Burundi',
                'phone' => '',
                'email_local' => $key === 'psy' ? 'fpse' : 'fspj',
                'hours' => 'Jours ouvrables, secrétariat de la faculté',
                'map_url' => '',
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function english(string $key): ?array
    {
        return match ($key) {
            'nat' => [
                'tagline' => 'Biology, chemistry, earth science, mathematics and physics',
                'about_title' => 'A faculty of sciences on the Mutanga campus',
                'about' => 'The Faculty of Sciences of the University of Burundi teaches bachelor’s degrees in its five departments and two master’s degrees in biology.',
                'hero_title' => 'Observe, measure, then explain',
                'dean_title' => 'Welcome to the Faculty of Sciences',
                'dean_role' => 'Dean of the Faculty',
                'dean_specialty' => 'Biology',
                'dean_paragraphs' => [
                    'The Faculty of Sciences has taught on the Mutanga campus since the university’s early years.',
                    'Students move from lectures to practical classes in biology, chemistry, earth science, mathematics and physics.',
                    'I invite you to read the departments and the two biology master’s programmes.',
                ],
                'mission_label' => 'What guides the teaching',
                'mission_title' => 'Mission and vision',
                'mission' => ['icon' => 'bi-bullseye', 'title' => 'Mission', 'paragraphs' => ['Train scientists who can observe, measure and write a clear account of what they find.']],
                'vision' => ['icon' => 'bi-eye', 'title' => 'Vision', 'paragraphs' => ['Keep a faculty where laboratory work and field notes stay part of every science degree.']],
                'offer_title' => 'Five departments',
                'offer_text' => 'Bachelor’s degrees in biology, chemistry, earth science, mathematics and physics, plus two master’s degrees in the Department of Biology.',
                'cta_title' => 'Questions about admission?',
                'cta_text' => 'Write to the faculty secretariat.',
                'contact_title' => 'Faculty of Sciences',
                'form_title' => 'Contact form',
                'form_help' => 'All required fields must be completed.',
                'hours' => 'Mon–Fri, 07:30–16:30',
                'footer' => 'Faculty of Sciences — University of Burundi.',
                'seo_title' => 'Faculty of Sciences | University of Burundi',
                'seo_description' => 'Departments, programmes and news of the Faculty of Sciences.',
                'research_title' => 'Research centres and a teaching workshop',
                'research_body' => 'CRSNE and CRMP are research centres of the faculty. The practical-work room is a teaching space.',
                'faculty_banner' => 'Faculty of Sciences',
                'history_title' => 'Since 1961-1962',
                'history_text' => 'The faculty states that it was created in 1961-1962, when the institution was still the University Centre of Usumbura.',
            ],
            'psy' => [
                'tagline' => 'Psychology and education, from the bachelor’s degree to the master’s',
                'about_title' => 'Two departments, one faculty',
                'about' => 'The Faculty of Psychology and Education has a Department of Psychology and a Department of Education. One master’s title covers three specialties.',
                'hero_title' => 'Listen, observe, then teach',
                'dean_title' => 'Welcome to the faculty',
                'dean_role' => 'Dean of the Faculty',
                'dean_specialty' => 'Psychology',
                'dean_paragraphs' => [
                    'Students here learn to listen, to watch a classroom and to write about what they see.',
                    'The bachelor’s cycle follows the two departments. The master’s degree has three specialties.',
                    'Come and read the departments before you write to the secretariat.',
                ],
                'mission_label' => 'What guides the teaching',
                'mission_title' => 'Mission and vision',
                'mission' => ['icon' => 'bi-bullseye', 'title' => 'Mission', 'paragraphs' => ['Train people who can work in psychology and in education with care and method.']],
                'vision' => ['icon' => 'bi-eye', 'title' => 'Vision', 'paragraphs' => ['A faculty where classroom practice and research training stay in the same building.']],
                'offer_title' => 'Psychology and education',
                'offer_text' => 'Bachelor’s pathways in the two departments and a master’s degree with three specialties.',
                'cta_title' => 'Questions about admission?',
                'cta_text' => 'Write to the faculty secretariat.',
                'contact_title' => 'Faculty of Psychology and Education',
                'form_title' => 'Contact form',
                'form_help' => 'All required fields must be completed.',
                'hours' => 'Weekdays, faculty secretariat',
                'footer' => 'Faculty of Psychology and Education — University of Burundi.',
                'seo_title' => 'Faculty of Psychology and Education | University of Burundi',
                'seo_description' => 'Departments and programmes in psychology and education.',
                'research_title' => 'Teaching workshops',
                'research_body' => 'The rooms listed here are classrooms and a reading room used for practical work.',
                'faculty_banner' => 'Faculty of Psychology and Education',
                'history_title' => 'From the bachelor’s degree to the master’s',
                'history_text' => 'Theses defended at the faculty describe training that runs from the bachelor’s degree through the master’s.',
            ],
            'law' => [
                'tagline' => 'Political science, international relations and law',
                'about_title' => 'Two departments',
                'about' => 'The Faculty of Political and Legal Sciences has a Department of Political Science and International Relations and a Department of Law.',
                'hero_title' => 'Read a text, then argue it',
                'dean_title' => 'Welcome to the faculty',
                'dean_role' => 'Dean of the Faculty',
                'dean_specialty' => 'Political science',
                'dean_paragraphs' => [
                    'Students learn to read a text closely and to say what follows from it.',
                    'Political science, international relations and judicial law are the pathways published by the faculty.',
                    'Start with the departments, then write to the secretariat if a point is still unclear.',
                ],
                'mission_label' => 'What guides the teaching',
                'mission_title' => 'Mission and vision',
                'mission' => ['icon' => 'bi-bullseye', 'title' => 'Mission', 'paragraphs' => ['Train students who can explain an institution, a judgment or a negotiation.']],
                'vision' => ['icon' => 'bi-eye', 'title' => 'Vision', 'paragraphs' => ['A faculty where political science and law share a library and a seminar room.']],
                'offer_title' => 'Political science and law',
                'offer_text' => 'A bachelor’s and two master’s pathways in political science and international relations, and law pathways in the Department of Law.',
                'cta_title' => 'Questions about admission?',
                'cta_text' => 'Write to the faculty secretariat.',
                'contact_title' => 'Faculty of Political and Legal Sciences',
                'form_title' => 'Contact form',
                'form_help' => 'All required fields must be completed.',
                'hours' => 'Weekdays, faculty secretariat',
                'footer' => 'Faculty of Political and Legal Sciences — University of Burundi.',
                'seo_title' => 'Faculty of Political and Legal Sciences | University of Burundi',
                'seo_description' => 'Departments and programmes in political science and law.',
                'research_title' => 'Reading rooms and a seminar',
                'research_body' => 'The rooms listed here are a legal reading room, a political-science seminar and a practice room.',
                'faculty_banner' => 'Faculty of Political and Legal Sciences',
                'history_title' => 'Political science and law',
                'history_text' => 'Public syllabi and theses name the two departments. Training runs from the bachelor’s degree through the master’s.',
            ],
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function departments(string $key): ?array
    {
        $d = static fn (string $abbr, string $slug, string $cycle, string $name, string $description, string $enName, string $enDescription, array $programmes, array $staff): array => [
            'abbreviation' => $abbr, 'slug' => $slug, 'cycle' => $cycle, 'name' => $name, 'description' => $description,
            'en_name' => $enName, 'en_description' => $enDescription, 'programmes' => $programmes, 'staff' => $staff,
        ];

        return match ($key) {
            'nat' => [
                $d('BIO', 'biologie', 'both', 'Département de Biologie', 'Baccalauréat en biologie, Master en Biologie des organismes et écologie (option Gestion des paysages et écosystèmes terrestres) et Master en Sciences et gestion intégrée de l’environnement.', 'Department of Biology', 'Bachelor’s degree in biology, Master’s in Organismal Biology and Ecology (Landscape and Terrestrial Ecosystem Management) and Master’s in Integrated Environmental Science and Management.', ['licence-biologie', 'master-biologie-organismes-ecologie', 'master-sciences-gestion-environnement'], ['fs-bukuru', 'fs-niyonizigiye', 'fs-nibigira']),
                $d('CHI', 'chimie', 'licence', 'Département de Chimie', 'Baccalauréat en chimie. La faculté organise les études de premier cycle par département.', 'Department of Chemistry', 'Bachelor’s degree in chemistry. The faculty organises first-cycle studies by department.', ['licence-chimie'], ['fs-ntahomvukiye', 'fs-ndayikengurukiye']),
                $d('STE', 'sciences-de-la-terre', 'licence', 'Département des Sciences de la Terre', 'Baccalauréat en sciences de la Terre. La faculté organise les études de premier cycle par département.', 'Department of Earth Sciences', 'Bachelor’s degree in earth sciences. The faculty organises first-cycle studies by department.', ['licence-sciences-terre'], ['fs-niyongabo']),
                $d('MAT', 'mathematiques', 'licence', 'Département de Mathématiques', 'Baccalauréat en mathématiques. La faculté organise les études de premier cycle par département.', 'Department of Mathematics', 'Bachelor’s degree in mathematics. The faculty organises first-cycle studies by department.', ['licence-mathematiques'], ['fs-kwizera', 'fs-niyungeko']),
                $d('PHY', 'physique', 'licence', 'Département de Physique', 'Baccalauréat en physique. La faculté organise les études de premier cycle par département.', 'Department of Physics', 'Bachelor’s degree in physics. The faculty organises first-cycle studies by department.', ['licence-physique'], ['fs-ndayiziga', 'fs-nshimirimana']),
            ],
            'psy' => [
                $d('PSY', 'psychologie', 'both', 'Département de Psychologie', 'Baccalauréat en psychologie et deux spécialités du Master en Sciences psychologiques et de l’éducation : Professionnel en services communautaires, et Recherche en sciences psychologiques.', 'Department of Psychology', 'Bachelor’s degree in psychology and two specialties of the Master’s in Psychological and Educational Sciences: professional community services, and research in psychological sciences.', ['licence-psychologie', 'master-psychologie-services-communautaires', 'master-recherche-sciences-psychologiques'], ['fpse-baranyanka', 'fpse-niyonzima', 'fpse-kanuma', 'fpse-ndayisaba', 'fpse-uwimana']),
                $d('SED', 'sciences-education', 'both', 'Département des Sciences de l’éducation', 'Baccalauréat en sciences de l’éducation et spécialité Recherche en sciences de l’éducation du Master en Sciences psychologiques et de l’éducation.', 'Department of Education', 'Bachelor’s degree in education and the research specialty in educational sciences of the Master’s in Psychological and Educational Sciences.', ['licence-sciences-education', 'master-recherche-sciences-education'], ['fpse-hakizimana', 'fpse-ciza', 'fpse-ndikumana', 'fpse-bigirimana']),
            ],
            'law' => [
                $d('SPRI', 'science-politique', 'both', 'Département de Science politique et Relations internationales', 'Baccalauréat en science politique, Master en Science politique et Master en Relations internationales.', 'Department of Political Science and International Relations', 'Bachelor’s degree in political science, Master’s in Political Science and Master’s in International Relations.', ['licence-science-politique', 'master-science-politique', 'master-relations-internationales'], ['fspj-ndayisenga', 'fspj-nshimirimana', 'fspj-bukuru', 'fspj-kagabo', 'fspj-niyongabo']),
                $d('DROIT', 'droit', 'both', 'Département de Droit', 'Baccalauréat en droit et Master en Droit judiciaire.', 'Department of Law', 'Bachelor’s degree in law and Master’s in Judicial Law.', ['licence-droit', 'master-droit-judiciaire'], ['fspj-nahimana', 'fspj-ciza', 'fspj-nduwimana', 'fspj-manirakiza']),
            ],
            default => null,
        };
    }

    /**
     * Two fictional partners, added to the three shared demo partners.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>|null
     */
    public function partners(string $key): ?array
    {
        return match ($key) {
            'nat' => [
                ['Club des sciences du campus', 'academic', 'Burundi', 'Ateliers du samedi animés par des étudiants.', 'Campus science club', 'Saturday workshops run by students.'],
                ['Imprimerie des cahiers de TP', 'industry', 'Burundi', 'Impression des polycopiés de travaux pratiques.', 'Practical-work print shop', 'Printing of practical-work handouts.'],
            ],
            'psy' => [
                ['Cercle de lecture pédagogique', 'academic', 'Burundi', 'Lectures mensuelles de mémoires.', 'Education reading circle', 'Monthly readings of dissertations.'],
                ['Association des anciens de psychologie', 'ngo', 'Burundi', 'Rencontres des diplômés de la faculté.', 'Psychology alumni association', 'Gatherings of graduates.'],
            ],
            'law' => [
                ['Revue étudiante de la faculté', 'academic', 'Burundi', 'Numéro annuel rédigé par des étudiants.', 'Student faculty review', 'Yearly issue written by students.'],
                ['Atelier d’éloquence', 'ngo', 'Burundi', 'Entraînement aux plaidoiries d’exercice.', 'Public-speaking workshop', 'Practice pleadings.'],
            ],
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function metaSciences(): array
    {
        return [
            'key' => 'nat',
            'tagline' => 'Biologie, chimie, sciences de la Terre, mathématiques et physique',
            'seo_description' => 'La Faculté des Sciences de l’Université du Burundi, au campus Mutanga, enseigne dans cinq départements et propose deux masters de biologie.',
            'about_title' => 'Cinq départements sur le campus Mutanga',
            'about' => 'La Faculté des Sciences de l’Université du Burundi est installée au campus Mutanga. Elle indique avoir été créée en 1961-1962. Elle organise l’enseignement de premier cycle dans les départements de Biologie, de Chimie, des Sciences de la Terre, de Mathématiques et de Physique, et deux masters au Département de Biologie.',
            'mission' => 'Apprendre à observer, à mesurer et à rédiger un compte rendu clair, dans les cinq départements de la faculté.',
            'vision' => 'Une faculté où les travaux pratiques et les sorties de terrain restent au centre des études scientifiques.',
            'values' => [
                ['title' => 'Observation', 'text' => 'Regarder le phénomène avant de le nommer.'],
                ['title' => 'Mesure', 'text' => 'Noter les conditions d’une expérience et les unités.'],
                ['title' => 'Compte rendu', 'text' => 'Écrire ce qui a été fait, pas ce que l’on espérait.'],
            ],
            'research_title' => 'Deux centres de recherche et un atelier de TP',
            'research_body' => 'Le CRSNE et le CRMP sont des centres de recherche de la faculté. L’atelier de travaux pratiques est une salle d’enseignement, distincte de ces centres.',
            'programmes_title' => 'Baccalauréats et masters',
            'programmes_intro' => 'Un baccalauréat dans chacun des cinq départements. Au Département de Biologie : Master en Biologie des organismes et écologie, option Gestion des paysages et écosystèmes terrestres, et Master en Sciences et gestion intégrée de l’environnement.',
            'posts_title' => 'Vie de la faculté',
            'posts_intro' => 'Annonces de travaux pratiques, de sorties et de séminaires.',
            'dean_name' => 'Prof. Chantal Niyonizigiye',
            'dean_specialty' => 'Biologie',
            'dean_message' => "Bienvenue à la Faculté des Sciences, au campus Mutanga.\n\nLes études commencent dans l’un des cinq départements : Biologie, Chimie, Sciences de la Terre, Mathématiques ou Physique. Les masters publiés sont ceux du Département de Biologie.\n\nLe secrétariat répond aux questions d’horaire et d’inscription. Le numéro de la faculté est le +257 22 22 55 56.",
            'welcome' => 'Bienvenue à la Faculté des Sciences. Cinq départements, deux masters de biologie, et des salles de travaux pratiques au campus Mutanga.',
            'hero_captions' => [
                ['badge' => 'FS', 'title' => 'Observer, mesurer, rendre compte', 'text' => 'Biologie, chimie, sciences de la Terre, mathématiques et physique au campus Mutanga.'],
                ['badge' => 'Départements', 'title' => 'Cinq départements', 'text' => 'Le premier cycle est organisé par département, comme l’indique la faculté.'],
                ['badge' => 'Masters', 'title' => 'Deux masters de biologie', 'text' => 'Biologie des organismes et écologie, et sciences et gestion intégrée de l’environnement.'],
            ],
            'highlights' => [
                ['icon' => 'bi-tree', 'title' => 'Biologie', 'description' => 'Un baccalauréat et deux masters au Département de Biologie.'],
                ['icon' => 'bi-eyedropper', 'title' => 'Chimie et physique', 'description' => 'Des travaux pratiques en salle, à côté des cours.'],
                ['icon' => 'bi-globe', 'title' => 'Sciences de la Terre', 'description' => 'Cartes, roches et lecture du relief dans le cursus du département.'],
                ['icon' => 'bi-calculator', 'title' => 'Mathématiques', 'description' => 'Un baccalauréat au Département de Mathématiques, lié au CRMP.'],
            ],
            'faculty_banner' => 'Faculté des Sciences',
            'contact_banner' => 'Campus Mutanga — +257 22 22 55 56',
            'history' => [
                'label' => 'Repère',
                'title' => 'Créée en 1961-1962',
                'text' => 'La faculté indique qu’elle a été créée en 1961-1962, à l’époque du Centre universitaire d’Usumbura.',
            ],
            'stats' => [
                ['section' => 'home_main', 'label' => 'Départements', 'value' => 5, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Formations publiées', 'value' => 7, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Enseignants', 'value' => 10, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Centres de recherche', 'value' => 2, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Centres nommés', 'value' => 2, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Atelier de TP', 'value' => 1, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Masters de biologie', 'value' => 2, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Parcours présentés', 'value' => 4, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Départements', 'value' => 5, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Campus', 'value' => 1, 'suffix' => ''],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metaPsychology(): array
    {
        return [
            'key' => 'psy',
            'tagline' => 'Psychologie et sciences de l’éducation, du baccalauréat au master',
            'seo_description' => 'La Faculté de Psychologie et des Sciences de l’Éducation forme en psychologie et en sciences de l’éducation, avec un master à trois spécialités.',
            'about_title' => 'Deux départements',
            'about' => 'La Faculté de Psychologie et des Sciences de l’Éducation comprend le Département de Psychologie et le Département des Sciences de l’éducation. Les mémoires soutenus à la faculté décrivent une formation qui va du baccalauréat au master. Le master publié s’intitule Master en Sciences psychologiques et de l’éducation.',
            'mission' => 'Former à l’écoute, à l’observation d’une situation et à la préparation d’une séquence d’enseignement.',
            'vision' => 'Une faculté où la psychologie et les sciences de l’éducation partagent les séminaires et la salle de documentation.',
            'values' => [
                ['title' => 'Écoute', 'text' => 'Laisser la personne finir sa phrase.'],
                ['title' => 'Observation', 'text' => 'Décrire une situation de classe avant de la juger.'],
                ['title' => 'Écriture', 'text' => 'Rendre un mémoire lisible par quelqu’un qui n’était pas dans la salle.'],
            ],
            'research_title' => 'Ateliers d’enseignement',
            'research_body' => 'Les étudiants travaillent dans un atelier de psychologie, un atelier de sciences de l’éducation et une salle de documentation.',
            'programmes_title' => 'Baccalauréats et master',
            'programmes_intro' => 'Baccalauréat en psychologie et baccalauréat en sciences de l’éducation. Master en Sciences psychologiques et de l’éducation, avec les spécialités Services communautaires, Recherche en sciences psychologiques et Recherche en sciences de l’éducation.',
            'posts_title' => 'Annonces de la faculté',
            'posts_intro' => 'Exemples de séminaires, de rendus de mémoire et de journées d’observation.',
            'dean_name' => 'Prof. Dieudonné Niyonzima',
            'dean_specialty' => 'Psychologie',
            'dean_message' => "Bienvenue à la Faculté de Psychologie et des Sciences de l’Éducation.\n\nDeux départements organisent les études : Psychologie, et Sciences de l’éducation. Le master commun s’intitule Master en Sciences psychologiques et de l’éducation.\n\nÉcrivez au secrétariat pour un rendez-vous ou une question d’inscription.",
            'welcome' => 'Bienvenue. Psychologie et sciences de l’éducation, du baccalauréat au master.',
            'hero_captions' => [
                ['badge' => 'FPSE', 'title' => 'Écouter, observer, enseigner', 'text' => 'Deux départements : Psychologie, et Sciences de l’éducation.'],
                ['badge' => 'Master', 'title' => 'Trois spécialités', 'text' => 'Services communautaires, recherche en sciences psychologiques, recherche en sciences de l’éducation.'],
                ['badge' => 'Mémoire', 'title' => 'Du baccalauréat au master', 'text' => 'Les thèses de la faculté décrivent ce parcours.'],
            ],
            'highlights' => [
                ['icon' => 'bi-people', 'title' => 'Psychologie', 'description' => 'Baccalauréat et deux spécialités de master au Département de Psychologie.'],
                ['icon' => 'bi-easel', 'title' => 'Sciences de l’éducation', 'description' => 'Baccalauréat et spécialité de recherche en sciences de l’éducation.'],
                ['icon' => 'bi-chat-dots', 'title' => 'Services communautaires', 'description' => 'Spécialité professionnelle du master, rattachée à la psychologie.'],
                ['icon' => 'bi-journal-text', 'title' => 'Mémoire', 'description' => 'Les spécialités de recherche se terminent par un mémoire.'],
            ],
            'faculty_banner' => 'Faculté de Psychologie et des Sciences de l’Éducation',
            'contact_banner' => 'Université du Burundi',
            'history' => [
                'label' => 'Parcours',
                'title' => 'Du baccalauréat au master',
                'text' => 'La faculté forme en psychologie et en sciences de l’éducation, du baccalauréat au master.',
            ],
            'stats' => [
                ['section' => 'home_main', 'label' => 'Départements', 'value' => 2, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Formations publiées', 'value' => 5, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Spécialités de master', 'value' => 3, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Enseignants', 'value' => 9, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Ateliers pédagogiques', 'value' => 2, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Salle de documentation', 'value' => 1, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Départements', 'value' => 2, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Parcours présentés', 'value' => 4, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Départements', 'value' => 2, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Spécialités de master', 'value' => 3, 'suffix' => ''],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metaLaw(): array
    {
        return [
            'key' => 'law',
            'tagline' => 'Science politique, relations internationales et droit',
            'seo_description' => 'La Faculté des Sciences politiques et juridiques enseigne la science politique, les relations internationales et le droit judiciaire.',
            'about_title' => 'Deux départements',
            'about' => 'La Faculté des Sciences politiques et juridiques comprend le Département de Science politique et Relations internationales et le Département de Droit. Les maquettes publiées pour 2024-2025 et 2025-2026 nomment le baccalauréat et les masters de science politique et de relations internationales. Des mémoires nomment le Master en Droit judiciaire.',
            'mission' => 'Apprendre à lire un texte, à en tirer une conséquence et à la dire dans l’ordre.',
            'vision' => 'Une faculté où la science politique et le droit partagent la documentation et les séminaires.',
            'values' => [
                ['title' => 'Lecture', 'text' => 'Citer le passage avant de le commenter.'],
                ['title' => 'Distinction', 'text' => 'Séparer le fait, la règle et l’opinion.'],
                ['title' => 'Ordre', 'text' => 'Tenir un dossier que quelqu’un d’autre peut reprendre.'],
            ],
            'research_title' => 'Salles de travail',
            'research_body' => 'Les étudiants travaillent dans une salle de documentation, un atelier de science politique et une salle de simulation.',
            'programmes_title' => 'Science politique et droit',
            'programmes_intro' => 'Baccalauréat en science politique, Master en Science politique, Master en Relations internationales, baccalauréat en droit et Master en Droit judiciaire.',
            'posts_title' => 'Annonces de la faculté',
            'posts_intro' => 'Exemples d’exposés, de simulations et de remises de mémoire.',
            'dean_name' => 'Prof. Espérance Nshimirimana',
            'dean_specialty' => 'Science politique',
            'dean_message' => "Bienvenue à la Faculté des Sciences politiques et juridiques.\n\nLe Département de Science politique et Relations internationales porte le baccalauréat en science politique, le Master en Science politique et le Master en Relations internationales. Le Département de Droit porte le Master en Droit judiciaire.\n\nLe secrétariat répond aux demandes de rendez-vous et aux questions d’inscription.",
            'welcome' => 'Bienvenue. Science politique, relations internationales et droit judiciaire.',
            'hero_captions' => [
                ['badge' => 'FSPJ', 'title' => 'Lire un texte, puis l’argumenter', 'text' => 'Science politique, relations internationales et droit.'],
                ['badge' => 'Départements', 'title' => 'Deux départements', 'text' => 'Science politique et Relations internationales, et Droit.'],
                ['badge' => 'Masters', 'title' => 'Trois masters publiés', 'text' => 'Science politique, relations internationales et droit judiciaire.'],
            ],
            'highlights' => [
                ['icon' => 'bi-bank', 'title' => 'Droit judiciaire', 'description' => 'Master du Département de Droit, nommé par des mémoires de la faculté.'],
                ['icon' => 'bi-globe', 'title' => 'Relations internationales', 'description' => 'Programme de master indiqué sur la maquette 2024-2025.'],
                ['icon' => 'bi-megaphone', 'title' => 'Science politique', 'description' => 'Baccalauréat et master au département de science politique.'],
                ['icon' => 'bi-journal-text', 'title' => 'Séminaire', 'description' => 'Exposés et commentaires de textes en travaux dirigés.'],
            ],
            'faculty_banner' => 'Faculté des Sciences politiques et juridiques',
            'contact_banner' => 'Université du Burundi',
            'history' => [
                'label' => 'Repères publiés',
                'title' => 'Maquettes et mémoires',
                'text' => 'La faculté enseigne la science politique, les relations internationales et le droit, du baccalauréat au master.',
            ],
            'stats' => [
                ['section' => 'home_main', 'label' => 'Départements', 'value' => 2, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Formations publiées', 'value' => 5, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Masters', 'value' => 3, 'suffix' => ''],
                ['section' => 'home_main', 'label' => 'Enseignants', 'value' => 9, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Salles de travail', 'value' => 3, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Départements', 'value' => 2, 'suffix' => ''],
                ['section' => 'home_research', 'label' => 'Masters', 'value' => 3, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Parcours présentés', 'value' => 4, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Départements', 'value' => 2, 'suffix' => ''],
                ['section' => 'alumni', 'label' => 'Masters', 'value' => 3, 'suffix' => ''],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function programme(string $level, string $slug, string $title, string $summary, string $description, array $careers, array $extra = []): array
    {
        return $extra + [
            'level' => $level,
            'slug' => $slug,
            'title' => $title,
            'summary' => $summary,
            'description' => $description,
            'careers' => $careers,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function programmesSciences(): array
    {
        $p = fn (...$args) => $this->programme(...$args);

        return [
            $p('licence', 'licence-biologie', 'Baccalauréat en Biologie', 'Quatre ans au Département de Biologie.', 'La Faculté des Sciences organise le premier cycle par département. Ce baccalauréat est celui du Département de Biologie. Les quatre années comptent 240 crédits et se terminent par un stage et un mémoire.', ['Enseignant de sciences', 'Technicien de laboratoire', 'Poursuite en master'], ['option_code' => 'BIO']),
            $p('licence', 'licence-chimie', 'Baccalauréat en Chimie', 'Quatre ans au Département de Chimie.', 'Premier cycle du Département de Chimie. Les travaux pratiques se font à l’atelier de la faculté. Les quatre années comptent 240 crédits.', ['Technicien de laboratoire', 'Enseignant de chimie', 'Poursuite en master'], ['option_code' => 'CHI']),
            $p('licence', 'licence-sciences-terre', 'Baccalauréat en Sciences de la Terre', 'Quatre ans au Département des Sciences de la Terre.', 'Premier cycle du Département des Sciences de la Terre : lecture de cartes, reconnaissance des roches et compte rendu de terrain, dans le cadre du baccalauréat du département.', ['Technicien géologue', 'Enseignant de sciences', 'Poursuite en master'], ['option_code' => 'STE']),
            $p('licence', 'licence-mathematiques', 'Baccalauréat en Mathématiques', 'Quatre ans au Département de Mathématiques.', 'Premier cycle du Département de Mathématiques. Le département est aussi lié au Centre de recherche en mathématiques et physique.', ['Enseignant de mathématiques', 'Chargé d’études', 'Poursuite en master'], ['option_code' => 'MAT']),
            $p('licence', 'licence-physique', 'Baccalauréat en Physique', 'Quatre ans au Département de Physique.', 'Premier cycle du Département de Physique, avec des mesures en salle de travaux pratiques. Le département est lié au Centre de recherche en mathématiques et physique.', ['Enseignant de physique', 'Technicien de mesure', 'Poursuite en master'], ['option_code' => 'PHY']),
            $p('master', 'master-biologie-organismes-ecologie', 'Master en Biologie des organismes et écologie', 'Option Gestion des paysages et écosystèmes terrestres. Deux ans, 120 crédits.', 'Master du Département de Biologie. L’intitulé et l’option Gestion des paysages et écosystèmes terrestres figurent sur des mémoires soutenus à l’Université du Burundi en 2022 et 2023. Le mémoire clôt les deux années.', ['Chargé d’études environnement', 'Enseignant-chercheur', 'Agent de conservation'], ['option_code' => 'BOE']),
            $p('master', 'master-sciences-gestion-environnement', 'Master en Sciences et gestion intégrée de l’environnement', 'Deux ans au Département de Biologie, 120 crédits.', 'Master du Département de Biologie. L’intitulé figure sur un rapport de terrain de 2024 associant l’Université du Burundi. Le cursus se termine par un mémoire.', ['Chargé d’études environnement', 'Gestionnaire de projet', 'Enseignant-chercheur'], ['option_code' => 'SGE']),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function programmesPsychology(): array
    {
        $p = fn (...$args) => $this->programme(...$args);

        return [
            $p('licence', 'licence-psychologie', 'Baccalauréat en Psychologie', 'Quatre ans au Département de Psychologie.', 'Les mémoires de la faculté indiquent une formation qui commence au baccalauréat. Ce parcours est celui du Département de Psychologie. Les quatre années comptent 240 crédits.', ['Conseiller', 'Enseignant', 'Poursuite en master'], ['option_code' => 'PSY']),
            $p('licence', 'licence-sciences-education', 'Baccalauréat en Sciences de l’éducation', 'Quatre ans au Département des Sciences de l’éducation.', 'Parcours de premier cycle du Département des Sciences de l’éducation. Les quatre années comptent 240 crédits.', ['Enseignant', 'Formateur', 'Poursuite en master'], ['option_code' => 'SED']),
            $p('master', 'master-psychologie-services-communautaires', 'Master en Sciences psychologiques et de l’éducation — Services communautaires', 'Spécialité professionnelle du Département de Psychologie. Deux ans, 120 crédits.', 'Spécialité Professionnel en services communautaires du Master en Sciences psychologiques et de l’éducation, rattachée au Département de Psychologie sur les mémoires de 2025. Les deux années comptent 120 crédits et se terminent par un mémoire.', ['Intervenant communautaire', 'Conseiller', 'Chargé de projet'], ['option_code' => 'PSC']),
            $p('master', 'master-recherche-sciences-psychologiques', 'Master en Sciences psychologiques et de l’éducation — Recherche en sciences psychologiques', 'Spécialité recherche du Département de Psychologie. Deux ans, 120 crédits.', 'Spécialité Recherche en sciences psychologiques du même master, au Département de Psychologie. Le travail se termine par un mémoire. Les deux années comptent 120 crédits et se terminent par un mémoire.', ['Chargé d’études', 'Enseignant-chercheur'], ['option_code' => 'RSP']),
            $p('master', 'master-recherche-sciences-education', 'Master en Sciences psychologiques et de l’éducation — Recherche en sciences de l’éducation', 'Spécialité du Département des Sciences de l’éducation. Deux ans, 120 crédits.', 'Spécialité Recherche en sciences de l’éducation du Master en Sciences psychologiques et de l’éducation, rattachée au Département des Sciences de l’éducation sur les mémoires de 2025.', ['Formateur d’enseignants', 'Chargé d’études', 'Enseignant-chercheur'], ['option_code' => 'RSE']),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function programmesLaw(): array
    {
        $p = fn (...$args) => $this->programme(...$args);

        return [
            $p('licence', 'licence-science-politique', 'Baccalauréat en Science politique', 'Quatre ans au Département de Science politique et Relations internationales.', 'Les maquettes 2025-2026 du département indiquent le BAC et le Bac I–II en science politique. La durée affichée est celle de la licence : quatre ans et 240 crédits. Les quatre années comptent 240 crédits.', ['Chargé d’études', 'Enseignant', 'Poursuite en master'], ['option_code' => 'SP']),
            $p('master', 'master-science-politique', 'Master en Science politique', 'Deux ans, 120 crédits.', 'Le Master I en science politique figure sur la maquette du Département de Science politique et Relations internationales. Le cursus se termine par un mémoire.', ['Chargé d’études', 'Enseignant-chercheur'], ['option_code' => 'MSP']),
            $p('master', 'master-relations-internationales', 'Master en Relations internationales', 'Deux ans, 120 crédits.', 'Programme de Master en Relations internationales indiqué sur la maquette 2024-2025 du Département de Science politique et Relations internationales.', ['Chargé de mission', 'Analyste', 'Enseignant-chercheur'], ['option_code' => 'MRI']),
            $p('licence', 'licence-droit', 'Baccalauréat en Droit', 'Quatre ans au Département de Droit.', 'Le Département de Droit est nommé par les mémoires de la faculté, qui parlent aussi d’un cycle de baccalauréat dans ce département. Les quatre années comptent 240 crédits.', ['Juriste', 'Greffier', 'Poursuite en master'], ['option_code' => 'DRO']),
            $p('master', 'master-droit-judiciaire', 'Master en Droit judiciaire', 'Deux ans au Département de Droit, 120 crédits.', 'Master en Droit judiciaire du Département de Droit, intitulé qui figure sur des mémoires soutenus en 2023 et 2024.', ['Juriste', 'Assistant de justice', 'Enseignant-chercheur'], ['option_code' => 'MDJ']),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staffSciences(): array
    {
        return [
            $this->person('enseignant', 'Prof. Chantal Niyonizigiye', 'fs-niyonizigiye', 'Professeure', 'Biologie', 'Doyenne de la Faculté', 'c.niyonizigiye@ub.edu.bi', 'Chantal Niyonizigiye est la doyenne de la Faculté des Sciences. Elle enseigne au Département de Biologie.'),
            $this->person('enseignant', 'Dr. Aimé Bukuru', 'fs-bukuru', 'Maître de conférences', 'Biologie des organismes', 'Chef du Département de Biologie', 'a.bukuru.fs@ub.edu.bi', 'Aimé Bukuru dirige le Département de Biologie. Il suit les mémoires des deux masters.'),
            $this->person('enseignant', 'Dr. Béatrice Ntahomvukiye', 'fs-ntahomvukiye', 'Maître de conférences', 'Chimie', 'Cheffe du Département de Chimie', 'b.ntahomvukiye@ub.edu.bi', 'Béatrice Ntahomvukiye organise les travaux pratiques de chimie.'),
            $this->person('enseignant', 'Dr. Fabrice Niyongabo', 'fs-niyongabo', 'Maître de conférences', 'Sciences de la Terre', 'Chef du Département des Sciences de la Terre', 'f.niyongabo.fs@ub.edu.bi', 'Fabrice Niyongabo prépare les sorties de lecture de cartes.'),
            $this->person('enseignant', 'Dr. Grâce Kwizera', 'fs-kwizera', 'Maître de conférences', 'Mathématiques', 'Cheffe du Département de Mathématiques', 'g.kwizera@ub.edu.bi', 'Grâce Kwizera anime le club de problèmes du département.'),
            $this->person('enseignant', 'Dr. Hervé Ndayiziga', 'fs-ndayiziga', 'Maître de conférences', 'Physique', 'Chef du Département de Physique', 'h.ndayiziga@ub.edu.bi', 'Hervé Ndayiziga tient le cahier des montages de physique.'),
            $this->person('enseignant', 'Mme Irène Nibigira', 'fs-nibigira', 'Assistante', 'Écologie', 'Enseignante', 'i.nibigira@ub.edu.bi', 'Irène Nibigira accompagne les comptes rendus de terrain en biologie.'),
            $this->person('enseignant', 'M. Jules Ndayikengurukiye', 'fs-ndayikengurukiye', 'Assistant', 'Chimie des solutions', 'Enseignant', 'j.ndayikengurukiye@ub.edu.bi', 'Jules Ndayikengurukiye prépare les solutions de l’atelier.'),
            $this->person('enseignant', 'Mme Liliane Niyungeko', 'fs-niyungeko', 'Assistante', 'Analyse', 'Enseignante', 'l.niyungeko@ub.edu.bi', 'Liliane Niyungeko corrige les devoirs d’analyse.'),
            $this->person('enseignant', 'M. Marc Nshimirimana', 'fs-nshimirimana', 'Assistant', 'Mesures physiques', 'Enseignant', 'm.nshimirimana.fs@ub.edu.bi', 'Marc Nshimirimana vérifie les appareils avant chaque séance.'),
            $this->person('administratif', 'Mme Nadine Nduwayo', 'fs-nduwayo', 'Secrétaire', 'Accueil', 'Secrétariat de la faculté', 'n.nduwayo.fs@ub.edu.bi', 'Nadine Nduwayo reçoit les demandes de rendez-vous et les relevés.'),
            $this->person('administratif', 'M. Oscar Niyukuri', 'fs-niyukuri', 'Responsable', 'Scolarité', 'Service des inscriptions', 'o.niyukuri@ub.edu.bi', 'Oscar Niyukuri suit les dossiers d’inscription de la faculté.'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staffPsychology(): array
    {
        return [
            $this->person('enseignant', 'Prof. Dieudonné Niyonzima', 'fpse-niyonzima', 'Professeur', 'Psychologie', 'Doyen de la Faculté', 'd.niyonzima@ub.edu.bi', 'Dieudonné Niyonzima est le doyen. Il enseigne au Département de Psychologie.'),
            $this->person('enseignant', 'Dr. Chantal Baranyanka', 'fpse-baranyanka', 'Maître de conférences', 'Psychologie', 'Cheffe du Département de Psychologie', 'c.baranyanka@ub.edu.bi', 'Chantal Baranyanka dirige le Département de Psychologie.'),
            $this->person('enseignant', 'Dr. Émile Kanuma', 'fpse-kanuma', 'Maître de conférences', 'Services communautaires', 'Enseignant', 'e.kanuma@ub.edu.bi', 'Émile Kanuma suit la spécialité Services communautaires.'),
            $this->person('enseignant', 'Dr. Jeanne Ndayisaba', 'fpse-ndayisaba', 'Maître de conférences', 'Méthodes', 'Enseignante', 'j.ndayisaba@ub.edu.bi', 'Jeanne Ndayisaba anime le séminaire de mémoire en psychologie.'),
            $this->person('enseignant', 'Mme Sylvie Uwimana', 'fpse-uwimana', 'Assistante', 'Entretien', 'Enseignante', 's.uwimana.fpse@ub.edu.bi', 'Sylvie Uwimana prépare les jeux de rôle d’entretien.'),
            $this->person('enseignant', 'Dr. Pascal Hakizimana', 'fpse-hakizimana', 'Maître de conférences', 'Sciences de l’éducation', 'Chef du Département des Sciences de l’éducation', 'p.hakizimana.fpse@ub.edu.bi', 'Pascal Hakizimana dirige le Département des Sciences de l’éducation.'),
            $this->person('enseignant', 'Dr. Marie Ciza', 'fpse-ciza', 'Maître de conférences', 'Didactique', 'Enseignante', 'm.ciza.fpse@ub.edu.bi', 'Marie Ciza relit les préparations de leçons.'),
            $this->person('enseignant', 'M. Léon Ndikumana', 'fpse-ndikumana', 'Assistant', 'Observation de classe', 'Enseignant', 'l.ndikumana@ub.edu.bi', 'Léon Ndikumana accompagne les stages d’observation.'),
            $this->person('enseignant', 'Mme Alice Bigirimana', 'fpse-bigirimana', 'Assistante', 'Mémoire', 'Enseignante', 'a.bigirimana.fpse@ub.edu.bi', 'Alice Bigirimana tient le calendrier des rendus de mémoire.'),
            $this->person('administratif', 'Mme Clarisse Ntahondi', 'fpse-ntahondi', 'Secrétaire', 'Accueil', 'Secrétariat', 'c.ntahondi.fpse@ub.edu.bi', 'Clarisse Ntahondi oriente les visiteurs vers le bon département.'),
            $this->person('administratif', 'M. Faustin Habonimana', 'fpse-habonimana', 'Responsable', 'Scolarité', 'Inscriptions', 'f.habonimana.fpse@ub.edu.bi', 'Faustin Habonimana vérifie les pièces des dossiers.'),
            $this->person('administratif', 'Mme Gloria Irankunda', 'fpse-irankunda', 'Documentaliste', 'Salle de lecture', 'Documentation', 'g.irankunda@ub.edu.bi', 'Gloria Irankunda classe les mémoires de la salle de documentation.'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staffLaw(): array
    {
        return [
            $this->person('enseignant', 'Prof. Espérance Nshimirimana', 'fspj-nshimirimana', 'Professeure', 'Science politique', 'Doyenne de la Faculté', 'e.nshimirimana.fspj@ub.edu.bi', 'Espérance Nshimirimana est la doyenne. Elle enseigne la science politique.'),
            $this->person('enseignant', 'Dr. Éric Ndayisenga', 'fspj-ndayisenga', 'Maître de conférences', 'Science politique', 'Chef du département', 'e.ndayisenga.fspj@ub.edu.bi', 'Éric Ndayisenga dirige le Département de Science politique et Relations internationales.'),
            $this->person('enseignant', 'Dr. Thierry Bukuru', 'fspj-bukuru', 'Maître de conférences', 'Relations internationales', 'Enseignant', 't.bukuru.fspj@ub.edu.bi', 'Thierry Bukuru suit le Master en Relations internationales.'),
            $this->person('enseignant', 'Mme Laure Kagabo', 'fspj-kagabo', 'Assistante', 'Dissertation', 'Enseignante', 'l.kagabo@ub.edu.bi', 'Laure Kagabo corrige les plans de dissertation.'),
            $this->person('enseignant', 'M. Patrick Niyongabo', 'fspj-niyongabo', 'Assistant', 'Institutions', 'Enseignant', 'p.niyongabo.fspj@ub.edu.bi', 'Patrick Niyongabo prépare les exposés sur les institutions.'),
            $this->person('enseignant', 'Dr. Alice Nahimana', 'fspj-nahimana', 'Maître de conférences', 'Droit judiciaire', 'Cheffe du Département de Droit', 'a.nahimana.fspj@ub.edu.bi', 'Alice Nahimana dirige le Département de Droit.'),
            $this->person('enseignant', 'Dr. Didier Ciza', 'fspj-ciza', 'Maître de conférences', 'Droit judiciaire', 'Enseignant', 'd.ciza.fspj@ub.edu.bi', 'Didier Ciza commente des décisions d’école en travaux dirigés.'),
            $this->person('enseignant', 'Mme Claire Nduwimana', 'fspj-nduwimana', 'Assistante', 'Documentation', 'Enseignante', 'c.nduwimana.fspj@ub.edu.bi', 'Claire Nduwimana aide à constituer les fiches de sources.'),
            $this->person('enseignant', 'M. Serge Manirakiza', 'fspj-manirakiza', 'Assistant', 'Procédure', 'Enseignant', 's.manirakiza.fspj@ub.edu.bi', 'Serge Manirakiza fait ranger les pièces d’un dossier d’exercice.'),
            $this->person('administratif', 'M. Jean Habimana', 'fspj-habimana', 'Secrétaire', 'Accueil', 'Secrétariat', 'j.habimana.fspj@ub.edu.bi', 'Jean Habimana tient le registre des rendez-vous.'),
            $this->person('administratif', 'Mme Inès Ndarusanze', 'fspj-ndarusanze', 'Responsable', 'Scolarité', 'Inscriptions', 'i.ndarusanze@ub.edu.bi', 'Inès Ndarusanze suit les inscriptions des deux départements.'),
            $this->person('administratif', 'M. Yves Nsengiyumva', 'fspj-nsengiyumva', 'Documentaliste', 'Salle de lecture', 'Documentation juridique', 'y.nsengiyumva@ub.edu.bi', 'Yves Nsengiyumva range les codes et les mémoires.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function person(string $category, string $name, string $slug, string $grade, string $specialty, string $role, string $email, string $bio): array
    {
        return compact('category', 'name', 'slug', 'grade', 'specialty', 'role', 'email', 'bio');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function postsSciences(): array
    {
        return [
            $this->post('news', 'Rentrée des travaux pratiques', 'fs-rentree-tp', 'Les groupes de chimie et de physique affichent leurs créneaux de salle.', "Les créneaux de l’atelier sont affichés au secrétariat et sur la porte de la salle.\n\nChaque étudiant apporte son cahier. Les blouses se rangent au vestiaire de l’atelier."),
            $this->post('event', 'Sortie de lecture de cartes', 'fs-sortie-cartes', 'Le Département des Sciences de la Terre emmène un groupe lire une coupe simplifiée.', "Le rendez-vous est devant le secrétariat. Le départ se fait à pied sur le campus.\n\nPrévoir de l’eau et un crayon. Le compte rendu se rend la semaine suivante.", 'Campus Mutanga'),
            $this->post('news', 'Club de problèmes', 'fs-club-problemes', 'Le Département de Mathématiques ouvre la séance du jeudi.', "La séance dure une heure. On y traite un exercice, puis on compare les rédactions.\n\nL’inscription se fait auprès de Grâce Kwizera. Le nombre de places suit la taille de la salle."),
            $this->post('news', 'Mémoires de biologie', 'fs-memoires-biologie', 'Les sujets des deux masters sont déposés au secrétariat du département.', "Les fiches indiquent le master : organismes et écologie, ou environnement.\n\nUn enseignant du département signe la fiche avant le début de la rédaction."),
            $this->post('event', 'Séminaire du CRSNE', 'fs-seminaire-crsne', 'Exposé d’étudiants sur un compte rendu de terrain.', "Trois étudiants présentent leurs notes. La discussion porte sur ce qui a été mesuré.\n\nLa salle est celle du Département de Biologie.", 'Département de Biologie'),
            $this->post('news', 'Vérification des appareils', 'fs-verification-appareils', 'Le Département de Physique ferme l’atelier un matin pour contrôler les multimètres.', "Les séances de l’après-midi sont maintenues.\n\nLes étudiants dont le créneau du matin est annulé sont prévenus au secrétariat."),
            $this->post('news', 'Polycopiés de chimie', 'fs-polycopies-chimie', 'Les fascicules du semestre sont à retirer au secrétariat.', "Un fascicule par étudiant inscrit. Le retrait se fait sur présentation de la carte.\n\nLes exemplaires abîmés se signalent le jour même."),
            $this->post('event', 'Portes ouvertes des cinq départements', 'fs-portes-ouvertes', 'Chaque département tient une table une matinée.', "Biologie, chimie, sciences de la Terre, mathématiques et physique présentent leur baccalauréat.\n\nLes masters de biologie ont une table à part. L’entrée est libre.", 'Campus Mutanga'),
            $this->post('news', 'Calendrier des examens', 'fs-calendrier-examens', 'Les dates du semestre sont affichées par département.', "Chaque département publie sa propre feuille.\n\nUne modification se signale au secrétariat, qui met à jour l’affichage."),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function postsPsychology(): array
    {
        return [
            $this->post('news', 'Groupes d’entretien', 'fpse-groupes-entretien', 'Le Département de Psychologie affiche les groupes de jeux de rôle.', "Chaque groupe a un créneau d’une heure.\n\nOn n’enregistre pas les séances. Les notes restent dans le cahier de l’étudiant."),
            $this->post('event', 'Séminaire de mémoire', 'fpse-seminaire-memoire', 'Les étudiants de master présentent l’état de leur plan.', "Cinq minutes par personne, puis des questions.\n\nLe texte d’une page est déposé la veille au secrétariat du département.", 'Salle de séminaire'),
            $this->post('news', 'Préparations de leçons', 'fpse-preparations', 'Le Département des Sciences de l’éducation ramasse les fiches de séquence.', "La fiche tient sur deux pages : objectif, déroulement, matériel.\n\nLes fiches incomplètes sont rendues pour un complément, pas notées en l’état."),
            $this->post('event', 'Journée d’observation', 'fpse-journee-observation', 'Les étudiants en sciences de l’éducation observent une séance préparée par leurs camarades.', "Chacun remplit une grille : ce qui est dit, ce qui est écrit au tableau, le temps de chaque étape.\n\nLa discussion a lieu l’après-midi, sans citer les prénoms dans le compte rendu commun.", 'Atelier de sciences de l’éducation'),
            $this->post('news', 'Spécialités du master', 'fpse-specialites-master', 'Rappel des trois spécialités du Master en Sciences psychologiques et de l’éducation.', "Services communautaires et recherche en sciences psychologiques relèvent du Département de Psychologie.\n\nLa recherche en sciences de l’éducation relève de l’autre département. Le choix se fait sur la fiche d’inscription au master."),
            $this->post('news', 'Salle de documentation', 'fpse-salle-documentation', 'Les mémoires de l’an passé sont consultables sur place.', "Le prêt à l’extérieur n’est pas ouvert.\n\nOn signe le cahier à l’entrée. Les sacs restent au vestiaire."),
            $this->post('event', 'Rencontre des deux départements', 'fpse-rencontre-departements', 'Psychologie et sciences de l’éducation présentent leurs baccalauréats.', "Chaque chef de département parle vingt minutes.\n\nLes questions s’écrivent sur un papier ramassé à la fin.", 'Hall de la faculté'),
            $this->post('news', 'Rendus de fin de semestre', 'fpse-rendus', 'Dates limites des dossiers de travaux dirigés.', "Psychologie : dossier d’entretien. Sciences de l’éducation : fiche de séquence commentée.\n\nUn retard se signale avant l’échéance, par écrit, au secrétariat."),
            $this->post('news', 'Accueil des nouveaux inscrits', 'fpse-accueil', 'Le secrétariat reçoit les nouveaux étudiants par département.', "Apporter la carte et la fiche d’orientation.\n\nLe créneau est affiché la veille. On ne passe pas sans créneau."),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function postsLaw(): array
    {
        return [
            $this->post('news', 'Plans de dissertation', 'fspj-plans-dissertation', 'Le département de science politique ramasse les plans du mois.', "Le plan tient sur une page. La question est recopiée en tête.\n\nLes copies sans question en tête sont rendues."),
            $this->post('event', 'Simulation de négociation', 'fspj-simulation', 'Les étudiants du Master en Relations internationales tiennent une séance d’exercice.', "Chaque délégation a une note d’une page, distribuée la veille.\n\nLe compte rendu cite les propositions, pas les personnes.", 'Salle de simulation'),
            $this->post('news', 'Fiches de sources', 'fspj-fiches-sources', 'Le Département de Droit demande une fiche par mémoire commencé.', "La fiche indique le titre, la question et cinq références lues.\n\nElle se dépose à la salle de documentation."),
            $this->post('event', 'Commentaire d’une décision d’école', 'fspj-commentaire-decision', 'Travaux dirigés ouverts aux étudiants du Master en Droit judiciaire.', "La décision a été rédigée pour l’exercice. Elle ne correspond pas à un jugement réel.\n\nOn commente les faits, puis la règle retenue par le texte.", 'Atelier de documentation juridique'),
            $this->post('news', 'Maquettes du département', 'fspj-maquettes', 'Rappel des intitulés publiés en science politique.', "Le baccalauréat, le Master en Science politique et le Master en Relations internationales sont ceux du Département de Science politique et Relations internationales.\n\nLe secrétariat ne distribue pas d’autre liste."),
            $this->post('news', 'Exposés sur les institutions', 'fspj-exposes', 'L’ordre de passage est affiché pour le baccalauréat en science politique.', "Dix minutes, puis des questions.\n\nLe support tient sur trois diapositives ou sur une feuille distribuée."),
            $this->post('event', 'Lecture de mémoires', 'fspj-lecture-memoires', 'Deux étudiants présentent leur plan de droit judiciaire.', "La salle est celle de documentation. On n’emporte pas les dossiers.\n\nLes remarques s’écrivent sur la fiche prévue à cet effet.", 'Salle de documentation'),
            $this->post('news', 'Inscriptions au master', 'fspj-inscriptions-master', 'Les fiches des trois masters sont au secrétariat.', "Une fiche par master. On ne dépose pas deux fiches.\n\nLes pièces demandées sont indiquées au verso."),
            $this->post('news', 'Horaires des séminaires', 'fspj-horaires', 'Les deux départements affichent leurs créneaux de la semaine.', "Science politique le matin, droit l’après-midi, cette semaine-là.\n\nUn changement est noté sur la même feuille, daté."),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function post(string $type, string $title, string $slug, string $excerpt, string $body, ?string $location = null): array
    {
        $row = compact('type', 'title', 'slug', 'excerpt', 'body');
        if ($location !== null) {
            $row['location'] = $location;
        }

        return $row;
    }
}
