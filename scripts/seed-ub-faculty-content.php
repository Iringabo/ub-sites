<?php

/**
 * One-off local seeder: replace starter placeholders with research-based
 * content for Université du Burundi faculties (fseg, fsi, med, fabi, flsh).
 *
 * Sources include ub.edu.bi, fseg.ub.edu.bi, fsi.ub.edu.bi, WHED/IAU, CHUK,
 * historical FLSH pages, and FABI/CRAVE publications.
 *
 * Usage: php scripts/seed-ub-faculty-content.php
 */

declare(strict_types=1);

function loadEnvValue(string $envPath, string $key): ?string
{
    if (! is_file($envPath)) {
        return null;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k === $key) {
            return trim($v, " \t\"'");
        }
    }

    return null;
}

$envFile = dirname(__DIR__) . '/fseg/.env';
$dbHost = loadEnvValue($envFile, 'database.default.hostname') ?? '127.0.0.1';
$dbName = loadEnvValue($envFile, 'database.default.database') ?? 'ub_shared';
$dbUser = loadEnvValue($envFile, 'database.default.username') ?? 'root';
$dbPass = loadEnvValue($envFile, 'database.default.password') ?? '';
$dbPort = (int) (loadEnvValue($envFile, 'database.default.port') ?? '3306');

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($mysqli->connect_error) {
    fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

$now = date('Y-m-d H:i:s');

function siteId(mysqli $db, string $slug): int
{
    $stmt = $db->prepare('SELECT id FROM sites WHERE slug = ? LIMIT 1');
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row === null) {
        throw new RuntimeException("Site introuvable: {$slug}");
    }

    return (int) $row['id'];
}

function upsertSetting(mysqli $db, int $siteId, string $key, string $value, string $type, string $context, string $now): void
{
    $stmt = $db->prepare('SELECT id FROM settings WHERE site_id = ? AND `key` = ? LIMIT 1');
    $stmt->bind_param('is', $siteId, $key);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if ($existing) {
        $id = (int) $existing['id'];
        $u = $db->prepare('UPDATE settings SET value = ?, type = ?, context = ?, updated_at = ? WHERE id = ?');
        $u->bind_param('ssssi', $value, $type, $context, $now, $id);
        $u->execute();
    } else {
        $class = 'App\\Settings\\Site';
        $i = $db->prepare('INSERT INTO settings (site_id, class, `key`, value, type, context, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)');
        $i->bind_param('isssssss', $siteId, $class, $key, $value, $type, $context, $now, $now);
        $i->execute();
    }
}

function updateHome(mysqli $db, int $siteId, array $data, string $now): int
{
    $fields = [];
    $types = '';
    $values = [];
    foreach ($data as $k => $v) {
        $fields[] = "`{$k}` = ?";
        $types .= 's';
        $values[] = $v;
    }
    $fields[] = 'updated_at = ?';
    $types .= 's';
    $values[] = $now;
    $types .= 'i';
    $values[] = $siteId;
    $sql = 'UPDATE home_content SET ' . implode(', ', $fields) . ' WHERE site_id = ? AND singleton_key = 1';
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();

    $q = $db->prepare('SELECT id FROM home_content WHERE site_id = ? AND singleton_key = 1');
    $q->bind_param('i', $siteId);
    $q->execute();

    return (int) $q->get_result()->fetch_assoc()['id'];
}

function replaceTranslations(mysqli $db, int $siteId, string $resourceType, int $resourceId, array $fields, string $now): void
{
    $del = $db->prepare('DELETE FROM content_translations WHERE site_id = ? AND resource_type = ? AND resource_id = ? AND locale = ?');
    $locale = 'en';
    $rid = (string) $resourceId;
    $del->bind_param('isss', $siteId, $resourceType, $rid, $locale);
    $del->execute();

    $ins = $db->prepare('INSERT INTO content_translations (site_id, resource_type, resource_id, locale, field, value, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($fields as $field => $value) {
        $ins->bind_param('isisssss', $siteId, $resourceType, $resourceId, $locale, $field, $value, $now, $now);
        $ins->execute();
    }
}

function clearAndInsert(mysqli $db, string $table, int $siteId, array $rows, string $now): void
{
    $del = $db->prepare("DELETE FROM {$table} WHERE site_id = ?");
    $del->bind_param('i', $siteId);
    $del->execute();

    foreach ($rows as $row) {
        $row['site_id'] = $siteId;
        if (! isset($row['created_at'])) {
            $row['created_at'] = $now;
        }
        if (! isset($row['updated_at'])) {
            $row['updated_at'] = $now;
        }
        $cols = array_keys($row);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . $placeholders . ')';
        $stmt = $db->prepare($sql);
        $types = '';
        $vals = [];
        foreach ($row as $v) {
            if (is_int($v)) {
                $types .= 'i';
            } elseif (is_float($v)) {
                $types .= 'd';
            } elseif ($v === null) {
                $types .= 's';
                $v = null;
            } else {
                $types .= 's';
                $v = (string) $v;
            }
            $vals[] = $v;
        }
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
    }
}

function updatePage(mysqli $db, int $siteId, string $key, string $title, array $content, string $seoTitle, string $seoDesc, string $now): void
{
    $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = $db->prepare('UPDATE pages SET title = ?, content = ?, seo_title = ?, seo_description = ?, updated_at = ? WHERE site_id = ? AND `key` = ?');
    $stmt->bind_param('sssssis', $title, $json, $seoTitle, $seoDesc, $now, $siteId, $key);
    $stmt->execute();
}

function updateContentBlock(mysqli $db, int $siteId, string $pageKey, string $type, string $title, string $content, string $now): void
{
    $stmt = $db->prepare('UPDATE content_blocks SET title = ?, content = ?, updated_at = ? WHERE site_id = ? AND page_key = ? AND type = ?');
    $stmt->bind_param('sssiss', $title, $content, $now, $siteId, $pageKey, $type);
    $stmt->execute();
}

function updateSiteMeta(mysqli $db, int $siteId, array $meta, string $now): void
{
    $stmt = $db->prepare('UPDATE sites SET contact_email = ?, phone = ?, address = ?, primary_color = ?, secondary_color = ?, updated_at = ? WHERE id = ?');
    $stmt->bind_param(
        'ssssssi',
        $meta['email'],
        $meta['phone'],
        $meta['address'],
        $meta['primary'],
        $meta['secondary'],
        $now,
        $siteId
    );
    $stmt->execute();
}

function updateThemeHero(mysqli $db, int $siteId, string $slug, string $now): void
{
    $hero = 'assets/images/faculties/' . $slug . '/hero-1.jpg';
    $config = json_encode([
        'layout' => 'classic',
        'hero_image' => $hero,
    ], JSON_UNESCAPED_SLASHES);
    $stmt = $db->prepare('UPDATE sites SET theme_config = ?, updated_at = ? WHERE id = ?');
    $stmt->bind_param('ssi', $config, $now, $siteId);
    $stmt->execute();
}

function applyLocalImages(array &$data, string $slug): void
{
    $base = 'assets/images/faculties/' . $slug;
    $data['home']['hero_media_type'] = 'image';
    $data['home']['hero_media_path'] = $base . '/hero-1.jpg';
    foreach (['faculty', 'formations', 'research', 'alumni', 'contact'] as $pageKey) {
        if (! isset($data['pages'][$pageKey]['content']) || ! is_array($data['pages'][$pageKey]['content'])) {
            continue;
        }
        $data['pages'][$pageKey]['content']['banner_image'] = $base . '/banner.jpg';
    }
}

$faculties = [];

$extra = require __DIR__ . '/seed-ub-faculty-data-fseg-fsi.php';
foreach ($extra as $slug => $payload) {
    $faculties[$slug] = $payload;
}

/* -------------------------------------------------------------------------- */
/* MED — Faculté de Médecine                                                  */
/* -------------------------------------------------------------------------- */
$faculties['med'] = [
    'meta' => [
        'email' => 'medecine@ub.edu.bi',
        'phone' => '(+257) 22 23 20 74',
        'address' => 'Centre Hospitalo-Universitaire de Kamenge (CHUK), Boulevard de Mwewi Gisabo, B.P. 2210, Bujumbura, Burundi',
        'primary' => '#0B6E99',
        'secondary' => '#084B6B',
    ],
    'home' => [
        'hero_badge' => 'Faculté de Médecine — Université du Burundi',
        'hero_title' => 'Former les médecins au service de la santé publique',
        'hero_text' => 'Hébergée au Centre Hospitalo-Universitaire de Kamenge (CHUK), la Faculté de Médecine forme des médecins généralistes et des spécialistes capables de répondre aux besoins sanitaires du Burundi et de la sous-région.',
        'hero_primary_label' => 'Découvrir les formations',
        'hero_primary_url' => '/formations',
        'hero_secondary_label' => 'Contacter la faculté',
        'hero_secondary_url' => '/contact',
        'about_label' => 'Présentation',
        'about_title' => 'Une faculté hospitalo-universitaire',
        'about_body' => "Depuis l'ouverture du CHUK, la Faculté de Médecine de l'Université du Burundi y assure la formation théorique et clinique des futurs médecins.\n\nL'enseignement, principalement en français, mène au Doctorat en Médecine, complété par des Diplômes d'Études Spécialisées (DES) en médecine et en chirurgie. Les stages se déroulent dans les services cliniques du CHUK : médecine interne, pédiatrie, chirurgie, gynécologie-obstétrique, anesthésie-réanimation, imagerie et médecine communautaire.",
        'about_button_label' => 'En savoir plus',
        'about_button_url' => '/faculte',
        'research_label' => 'Recherche',
        'research_title' => 'Recherche clinique et santé publique',
        'research_body' => 'Les travaux portent sur la santé maternelle et infantile, les maladies infectieuses, la médecine communautaire et l’amélioration de la qualité des soins au CHUK, en lien avec les priorités nationales de santé.',
        'research_button_label' => 'Explorer la recherche',
        'research_button_url' => '/recherche',
        'programmes_label' => 'Formations',
        'programmes_title' => 'Cursus médical et spécialisations',
        'programmes_text' => 'Doctorat en Médecine et DES dans les spécialités médicales et chirurgicales reconnues à l’Université du Burundi.',
        'programmes_button_label' => 'Voir les formations',
        'programmes_button_url' => '/formations',
        'posts_label' => 'Actualités',
        'posts_title' => 'Vie de la faculté et du CHUK',
        'posts_text' => 'Annonces pédagogiques, soutenances de thèses et activités hospitalo-universitaires.',
        'posts_button_label' => 'Voir tout',
        'posts_button_url' => '/actualites',
        'seo_title' => 'Faculté de Médecine | Université du Burundi',
        'seo_description' => 'Faculté de Médecine de l’Université du Burundi : Doctorat en Médecine, DES, formation clinique au CHUK Kamenge.',
    ],
    'home_en' => [
        'hero_title' => 'Training physicians for public health',
        'hero_text' => 'Based at Kamenge University Hospital (CHUK), the Faculty of Medicine trains general practitioners and specialists to serve Burundi and the region.',
        'about_title' => 'A university teaching hospital faculty',
        'about_body' => 'The Faculty of Medicine delivers medical training leading to the Doctorate in Medicine and specialized diplomas (DES), with clinical placements across CHUK departments.',
        'seo_title' => 'Faculty of Medicine | University of Burundi',
        'seo_description' => 'University of Burundi Faculty of Medicine: MD programme, DES specialties, clinical training at CHUK Kamenge.',
    ],
    'settings' => [
        ['institution.faculty_name', 'Faculté de Médecine', 'string', 'institution'],
        ['institution.short_name', 'MED', 'string', 'institution'],
        ['institution.university', 'Université du Burundi', 'string', 'institution'],
        ['contact.address', 'CHUK Kamenge, Boulevard de Mwewi Gisabo, B.P. 2210, Bujumbura', 'string', 'contact'],
        ['contact.phone', '(+257) 22 23 20 74', 'string', 'contact'],
        ['contact.email', 'medecine@ub.edu.bi', 'email', 'contact'],
        ['contact.hours', 'Lundi–Vendredi, 8h00–16h00', 'string', 'contact'],
        ['footer.text', 'Faculté de Médecine — Université du Burundi. Formation hospitalo-universitaire au CHUK.', 'text', 'footer'],
        ['footer.copyright', '© ' . date('Y') . ' Faculté de Médecine — Université du Burundi', 'string', 'footer'],
        ['seo.default_title', 'Faculté de Médecine | Université du Burundi', 'string', 'seo'],
        ['seo.default_description', 'Doctorat en Médecine et spécialisations DES à l’Université du Burundi (CHUK Kamenge).', 'text', 'seo'],
        ['seo.theme_color', '#0B6E99', 'color', 'seo'],
    ],
    'stats' => [
        ['home_main', 'Étudiants en médecine', 850, '+', 1],
        ['home_main', 'Programmes (MD & DES)', 8, '', 2],
        ['home_main', 'Services cliniques partenaires', 9, '', 3],
        ['home_research', 'Thèses et mémoires / an', 40, '+', 1],
        ['home_research', 'Axes de recherche clinique', 5, '', 2],
        ['alumni', 'Médecins formés', 2500, '+', 1],
    ],
    'programmes' => [
        [
            'level' => 'doctorat',
            'title' => 'Doctorat en Médecine',
            'slug' => 'doctorat-en-medecine',
            'duration' => '7 ans',
            'summary' => 'Formation complète de médecin généraliste, associant sciences fondamentales, stages cliniques et thèse de fin d’études.',
            'description' => "Le Doctorat en Médecine prépare les étudiants à l'exercice de la médecine générale et à l'accès aux spécialités. Le parcours combine enseignements précliniques et immersion progressive dans les services du CHUK (médecine interne, pédiatrie, chirurgie, gynécologie-obstétrique, anesthésie-réanimation, imagerie, médecine communautaire).\n\nL'admission repose sur le Certificat d'Humanités complètes ou un équivalent reconnu. L'enseignement est principalement dispensé en français.",
            'admission_conditions' => 'Certificat d’Humanités complètes (ou équivalent) ; sélection selon les modalités académiques de l’Université du Burundi.',
            'career_outcomes' => json_encode(['Médecin généraliste', 'Candidat aux DES / spécialités', 'Médecine communautaire et santé publique', 'Recherche clinique'], JSON_UNESCAPED_UNICODE),
            'display_order' => 1,
            'featured_on_home' => 1,
            'home_order' => 1,
            'is_published' => 1,
        ],
        [
            'level' => 'master',
            'title' => 'DES — Spécialités médicales',
            'slug' => 'des-specialites-medicales',
            'duration' => '3 à 5 ans selon la spécialité',
            'summary' => 'Diplômes d’Études Spécialisées : médecine interne, pédiatrie, anesthésie-réanimation.',
            'description' => 'Les DES médicaux forment des spécialistes hospitaliers dans les domaines de la médecine interne, de la pédiatrie et de l’anesthésie-réanimation, en appui sur les départements cliniques du CHUK.',
            'admission_conditions' => 'Doctorat en Médecine (ou titre reconnu) et sélection par la faculté / collège de spécialité.',
            'career_outcomes' => json_encode(['Médecin interniste', 'Pédiatre', 'Anesthésiste-réanimateur', 'Praticien hospitalier'], JSON_UNESCAPED_UNICODE),
            'display_order' => 2,
            'featured_on_home' => 1,
            'home_order' => 2,
            'is_published' => 1,
        ],
        [
            'level' => 'master',
            'title' => 'DES — Spécialités chirurgicales',
            'slug' => 'des-specialites-chirurgicales',
            'duration' => '4 à 5 ans selon la spécialité',
            'summary' => 'Diplômes d’Études Spécialisées : chirurgie générale, gynécologie-obstétrique, ORL, radiodiagnostic et imagerie médicale.',
            'description' => 'Les DES chirurgicaux et d’imagerie préparent les spécialistes aux plateaux techniques du CHUK et aux besoins nationaux en chirurgie, santé de la mère et de l’enfant, ORL et radiodiagnostic.',
            'admission_conditions' => 'Doctorat en Médecine (ou titre reconnu) et sélection selon les spécialités ouvertes.',
            'career_outcomes' => json_encode(['Chirurgien généraliste', 'Gynécologue-obstétricien', 'ORL', 'Radiologue'], JSON_UNESCAPED_UNICODE),
            'display_order' => 3,
            'featured_on_home' => 1,
            'home_order' => 3,
            'is_published' => 1,
        ],
    ],
    'staff' => [
        [
            'category' => 'enseignant',
            'name' => 'Dr Jean Claude Niyindiko',
            'slug' => 'jean-claude-niyindiko',
            'grade' => 'Doyen',
            'specialty' => 'Médecine',
            'role' => 'Doyen de la Faculté de Médecine',
            'email' => 'medecine@ub.edu.bi',
            'biography' => 'Doyen de la Faculté de Médecine de l’Université du Burundi (selon l’annuaire officiel des doyens de l’UB).',
            'display_order' => 1,
            'is_published' => 1,
            'photo' => null,
        ],
        [
            'category' => 'administratif',
            'name' => 'Secrétariat de la Faculté de Médecine',
            'slug' => 'secretariat-faculte-medecine',
            'grade' => null,
            'specialty' => null,
            'role' => 'Accueil et scolarité',
            'email' => 'medecine@ub.edu.bi',
            'biography' => 'Point de contact administratif de la faculté pour les inscriptions, attestations et informations pédagogiques.',
            'display_order' => 2,
            'is_published' => 1,
            'photo' => null,
        ],
    ],
    'labs' => [[
        'abbreviation' => 'CHUK-FM',
        'name' => 'Plateforme hospitalo-universitaire du CHUK',
        'slug' => 'plateforme-chuk',
        'icon' => 'bi-hospital',
        'description' => 'Cadre clinique de formation et de recherche de la Faculté de Médecine : départements de médecine interne, pédiatrie, chirurgie, gynécologie-obstétrique, anesthésie-réanimation, imagerie, laboratoires et médecine communautaire.',
        'themes' => json_encode(['Santé maternelle et infantile', 'Maladies infectieuses', 'Qualité des soins', 'Médecine communautaire'], JSON_UNESCAPED_UNICODE),
        'researcher_count' => 45,
        'display_order' => 1,
        'featured_on_home' => 1,
        'home_order' => 1,
        'is_published' => 1,
    ]],
    'publications' => [[
        'year' => 2024,
        'title' => 'Travaux cliniques et thèses de Doctorat en Médecine au CHUK',
        'authors' => 'Enseignants et doctorants de la Faculté de Médecine',
        'journal' => 'Repository de l’Université du Burundi / CHUK',
        'url' => 'https://repository.ub.edu.bi/',
        'display_order' => 1,
        'is_published' => 1,
    ]],
    'projects' => [[
        'code' => 'MED-SANTE-COMM',
        'title' => 'Formation médicale et santé communautaire',
        'description' => 'Renforcement de la formation clinique et de la recherche en médecine communautaire en partenariat avec le CHUK, hôpital de référence nationale.',
        'funder' => 'Université du Burundi / partenaires santé',
        'period_start' => 2022,
        'period_end' => null,
        'icon' => 'bi-heart-pulse',
        'display_order' => 1,
        'is_published' => 1,
    ]],
    'timeline' => [
        ['year' => 1984, 'title' => 'Création du CHUK', 'description' => 'Le Centre Hospitalo-Universitaire de Kamenge est créé (décret n°100/121 du 28/12/1984) et héberge la Faculté de Médecine.', 'display_order' => 1, 'is_published' => 1],
        ['year' => 2006, 'title' => 'Tutelle universitaire confirmée', 'description' => 'Ordonnance ministérielle précisant le fonctionnement du CHUK sous tutelle du Recteur de l’Université du Burundi.', 'display_order' => 2, 'is_published' => 1],
        ['year' => 2024, 'title' => 'Offre MD et DES', 'description' => 'Poursuite du Doctorat en Médecine et des DES médicaux et chirurgicaux reconnus dans les bases internationales (WHED/IAU).', 'display_order' => 3, 'is_published' => 1],
    ],
    'alumni' => [
        'profile' => [
            'name' => 'Dr Alumni Médecine',
            'slug' => 'alumni-medecine',
            'photo' => null,
            'promotion' => 'Promotion type',
            'role' => 'Médecin hospitalier',
            'organization' => 'CHUK / réseau de santé publique',
            'biography' => 'Profil illustratif du parcours des médecins formés à la Faculté de Médecine de l’UB.',
            'display_order' => 1,
            'is_published' => 1,
        ],
        'testimonial' => [
            'person_name' => 'Ancien·ne de la Faculté de Médecine',
            'photo' => null,
            'promotion' => 'Doctorat en Médecine',
            'quote' => 'La formation au CHUK m’a préparé·e à la réalité des services de référence et à l’engagement pour la santé des communautés.',
            'display_order' => 1,
            'is_published' => 1,
        ],
    ],
    'posts' => [
        [
            'type' => 'news',
            'title' => 'Rentrée académique à la Faculté de Médecine',
            'slug' => 'rentree-faculte-medecine',
            'excerpt' => 'Ouverture de l’année académique pour les étudiants en Doctorat en Médecine et les inscrits en DES.',
            'body' => "La Faculté de Médecine accueille les nouvelles promotions sur le site du CHUK. Les étudiants sont informés du calendrier des stages cliniques, des enseignements théoriques et des modalités de suivi pédagogique.\n\nPour toute information, contactez le secrétariat au (+257) 22 23 20 74.",
            'status' => 'published',
            'published_at' => $now,
            'featured' => 1,
            'home_order' => 1,
            'event_starts_at' => null,
            'event_ends_at' => null,
            'event_location' => null,
            'registration_url' => null,
            'seo_title' => 'Rentrée — Faculté de Médecine',
            'seo_description' => 'Rentrée académique de la Faculté de Médecine de l’Université du Burundi.',
            'cover_image' => null,
            'created_by' => null,
            'updated_by' => null,
        ],
        [
            'type' => 'event',
            'title' => 'Journée scientifique hospitalo-universitaire',
            'slug' => 'journee-scientifique-chuk',
            'excerpt' => 'Présentation de travaux cliniques et de thèses au CHUK.',
            'body' => 'Une journée scientifique réunira enseignants, internes et doctorants autour de la qualité des soins, de la santé maternelle et infantile et des pathologies infectieuses prioritaires.',
            'status' => 'published',
            'published_at' => $now,
            'featured' => 1,
            'home_order' => 2,
            'event_starts_at' => date('Y-m-d 09:00:00', strtotime('+45 days')),
            'event_ends_at' => date('Y-m-d 16:00:00', strtotime('+45 days')),
            'event_location' => 'CHUK Kamenge, Bujumbura',
            'registration_url' => null,
            'seo_title' => 'Journée scientifique CHUK',
            'seo_description' => 'Événement scientifique de la Faculté de Médecine au CHUK.',
            'cover_image' => null,
            'created_by' => null,
            'updated_by' => null,
        ],
    ],
    'highlights' => [
        ['bi-hospital', 'Formation clinique au CHUK', 'Immersion dans les services de référence nationale dès le cursus de Doctorat en Médecine.', 1],
        ['bi-award', 'DES médicaux et chirurgicaux', 'Spécialisations en médecine interne, pédiatrie, anesthésie, chirurgie, gynéco-obstétrique, ORL et imagerie.', 2],
        ['bi-people', 'Engagement santé publique', 'Orientation vers la médecine communautaire et les priorités sanitaires du Burundi.', 3],
    ],
    'pages' => [
        'faculty' => [
            'title' => 'La Faculté',
            'content' => [
                'banner_subtitle' => 'Faculté de Médecine — Université du Burundi',
                'dean' => [
                    'label' => 'Mot du doyen',
                    'title' => 'Former pour soigner',
                    'photo' => '',
                    'name' => 'Dr Jean Claude Niyindiko',
                    'role' => 'Doyen',
                    'specialty' => 'Médecine',
                    'signature' => 'Dr Jean Claude Niyindiko',
                    'paragraphs' => [
                        'Chères étudiantes, chers étudiants, chers partenaires,',
                        'La Faculté de Médecine de l’Université du Burundi, adossée au CHUK, a pour mission de former des médecins compétents, éthiques et engagés au service de la population.',
                        'Nous poursuivons le renforcement du Doctorat en Médecine et des DES, en lien étroit avec les départements cliniques et les besoins du système de santé national.',
                    ],
                ],
                'mission_label' => 'Mission et vision',
                'mission_title' => 'Excellence médicale et service à la société',
                'mission' => [
                    'icon' => 'bi-bullseye',
                    'title' => 'Mission',
                    'paragraphs' => ['Former des médecins et spécialistes capables d’assurer des soins de qualité, de mener des recherches cliniques utiles et de contribuer à la santé publique burundaise.'],
                ],
                'vision' => [
                    'icon' => 'bi-eye',
                    'title' => 'Vision',
                    'paragraphs' => ['Être une référence hospitalo-universitaire en Afrique des Grands Lacs pour la formation médicale et l’amélioration des soins.'],
                ],
                'values' => [
                    ['icon' => 'bi-heart', 'title' => 'Humanisme', 'description' => 'Respect du patient et éthique médicale.'],
                    ['icon' => 'bi-book', 'title' => 'Rigueur scientifique', 'description' => 'Formation fondée sur les preuves et la pratique clinique.'],
                    ['icon' => 'bi-globe', 'title' => 'Service public', 'description' => 'Réponse aux priorités sanitaires nationales.'],
                ],
                'history' => [
                    'label' => 'Historique',
                    'title' => 'Une faculté liée au CHUK',
                    'text' => 'Le CHUK, créé en 1984, héberge depuis son ouverture la Faculté de Médecine. Il constitue le principal terrain de stages et de recherche clinique des étudiants et enseignants.',
                ],
            ],
            'seo_title' => 'La Faculté de Médecine | UB',
            'seo_description' => 'Présentation de la Faculté de Médecine de l’Université du Burundi.',
        ],
        'formations' => [
            'title' => 'Formations',
            'content' => [
                'banner_subtitle' => 'Doctorat en Médecine et Diplômes d’Études Spécialisées',
                'offer_label' => 'Offre académique',
                'offer_title' => 'Cursus médical',
                'offer_text' => 'L’offre comprend le Doctorat en Médecine et des DES en spécialités médicales et chirurgicales, conformément aux programmes reconnus de l’Université du Burundi.',
                'cta_title' => 'Besoin d’informations sur les admissions ?',
                'cta_text' => 'Contactez le secrétariat de la faculté pour le calendrier et les conditions d’inscription.',
                'cta_label' => 'Contacter la faculté',
                'cta_url' => '/contact',
            ],
            'seo_title' => 'Formations — Faculté de Médecine',
            'seo_description' => 'Programmes de la Faculté de Médecine de l’UB.',
        ],
        'research' => [
            'title' => 'Recherche',
            'content' => [
                'banner_subtitle' => 'Recherche clinique et santé communautaire',
                'labs_label' => 'Cadres de recherche',
                'labs_title' => 'CHUK et équipes cliniques',
                'labs_text' => 'La recherche s’organise autour des départements hospitaliers et des thèses de Doctorat en Médecine.',
                'publications_label' => 'Publications',
                'publications_title' => 'Travaux et thèses',
                'publications_text' => 'Les thèses et travaux sont progressivement déposés dans le repository de l’Université du Burundi.',
                'projects_label' => 'Projets',
                'projects_title' => 'Projets prioritaires',
            ],
            'seo_title' => 'Recherche — Faculté de Médecine',
            'seo_description' => 'Axes de recherche de la Faculté de Médecine.',
        ],
        'alumni' => [
            'title' => 'Alumni',
            'content' => [
                'banner_subtitle' => 'Réseau des médecins formés à l’UB',
                'intro_label' => 'Communauté',
                'intro_title' => 'Anciens de la Faculté de Médecine',
                'intro_paragraphs' => ['Les diplômés exercent dans les hôpitaux publics, le secteur privé, les ONG de santé et les programmes de santé publique.'],
                'profiles_label' => 'Profils',
                'profiles_title' => 'Parcours alumni',
                'profiles_text' => 'Découvrez des exemples de trajectoires professionnelles.',
                'testimonials_label' => 'Témoignages',
                'testimonials_title' => 'Ils témoignent',
                'cta_title' => 'Rejoindre le réseau',
                'cta_text' => 'Faites part de votre parcours au secrétariat de la faculté.',
                'cta_label' => 'Nous contacter',
                'cta_url' => '/contact',
            ],
            'seo_title' => 'Alumni — Faculté de Médecine',
            'seo_description' => 'Réseau alumni de la Faculté de Médecine.',
        ],
        'contact' => [
            'title' => 'Contact',
            'content' => [
                'banner_subtitle' => 'Secrétariat de la Faculté de Médecine',
                'contact_label' => 'Coordonnées',
                'contact_title' => 'Contacter la Faculté de Médecine',
                'form_title' => 'Envoyer un message',
                'form_help' => 'Indiquez votre demande (inscription, stage, partenariat). Réponse sous quelques jours ouvrables.',
                'map_url' => 'https://www.google.com/maps?q=-3.355472%2C29.385669%20(Facult%C3%A9%20de%20M%C3%A9decine%20%E2%80%94%20CHUK%20Kamenge)&hl=fr&z=17&output=embed',
            ],
            'seo_title' => 'Contact — Faculté de Médecine',
            'seo_description' => 'Contacter la Faculté de Médecine de l’UB.',
        ],
    ],
    'dean_block' => 'Message du doyen Dr Jean Claude Niyindiko : former des médecins compétents au service de la santé publique, en lien étroit avec le CHUK.',
];

/* -------------------------------------------------------------------------- */
/* FABI — Faculté d'Agronomie et de Bioingénierie                             */
/* -------------------------------------------------------------------------- */
$faculties['fabi'] = [
    'meta' => [
        'email' => 'fabi@ub.edu.bi',
        'phone' => '(+257) 22 40 25 00',
        'address' => 'Campus Zege (Gitega) — Faculté d’Agronomie et de Bio-Ingénierie ; CRAVE B.P. 2940, Bujumbura, Burundi',
        'primary' => '#2E7D32',
        'secondary' => '#1B5E20',
    ],
    'home' => [
        'hero_badge' => 'FABI — Université du Burundi',
        'hero_title' => 'Agronomie et bio-ingénierie pour le développement durable',
        'hero_text' => 'Héritière de l’institut agronomique fondé en 1958, la FABI forme des ingénieurs et chercheurs capables de moderniser les systèmes agricoles, alimentaires et environnementaux du Burundi.',
        'hero_primary_label' => 'Découvrir les formations',
        'hero_primary_url' => '/formations',
        'hero_secondary_label' => 'Contacter la faculté',
        'hero_secondary_url' => '/contact',
        'about_label' => 'Présentation',
        'about_title' => 'Une faculté ancrée dans le monde rural',
        'about_body' => "La Faculté d’Agronomie et de Bio-Ingénierie (FABI) est l’une des plus anciennes composantes de l’Université du Burundi. Installée notamment sur le campus de Zege (Gitega), elle regroupe des départements couvrant les productions végétales et animales, les technologies agroalimentaires, l’environnement, la socio-économie rurale et les sciences du sol / agro-environnement.\n\nLa recherche s’appuie notamment sur le Centre de Recherche en Sciences des Productions Animales, Végétales et Environnementales (CRAVE).",
        'about_button_label' => 'En savoir plus',
        'about_button_url' => '/faculte',
        'research_label' => 'Recherche',
        'research_title' => 'CRAVE et axes agro-environnementaux',
        'research_body' => 'Fertilité des sols, systèmes de production, biodiversité, forêts et innovations agroalimentaires : la FABI publie et collabore aux réseaux régionaux de recherche agricole.',
        'research_button_label' => 'Explorer la recherche',
        'research_button_url' => '/recherche',
        'programmes_label' => 'Formations',
        'programmes_title' => 'Filières agronomiques et bio-ingénierie',
        'programmes_text' => 'Licences et masters orientés productions végétales/animales, agroalimentaire, environnement et développement rural.',
        'programmes_button_label' => 'Voir les formations',
        'programmes_button_url' => '/formations',
        'posts_label' => 'Actualités',
        'posts_title' => 'Vie du campus Zege',
        'posts_text' => 'Annonces académiques, activités terrain et vie associative des étudiants FABI.',
        'posts_button_label' => 'Voir tout',
        'posts_button_url' => '/actualites',
        'seo_title' => 'FABI | Université du Burundi',
        'seo_description' => 'Faculté d’Agronomie et de Bio-Ingénierie (FABI) : formations, CRAVE, campus Zege.',
    ],
    'home_en' => [
        'hero_title' => 'Agronomy and bio-engineering for sustainable development',
        'hero_text' => 'Tracing its roots to 1958, FABI trains engineers and researchers to modernize Burundi’s agricultural, food and environmental systems.',
        'about_title' => 'A faculty rooted in rural development',
        'about_body' => 'Based notably at Zege campus (Gitega), FABI covers crop and animal production, food technology, environment, rural socio-economics and soil/agro-environmental sciences, with research through CRAVE.',
        'seo_title' => 'FABI | University of Burundi',
        'seo_description' => 'Faculty of Agronomy and Bio-Engineering: programmes, CRAVE research, Zege campus.',
    ],
    'settings' => [
        ['institution.faculty_name', 'Faculté d’Agronomie et de Bioingénierie', 'string', 'institution'],
        ['institution.short_name', 'FABI', 'string', 'institution'],
        ['institution.university', 'Université du Burundi', 'string', 'institution'],
        ['contact.address', 'Campus Zege, Gitega — FABI ; CRAVE B.P. 2940 Bujumbura', 'string', 'contact'],
        ['contact.phone', '(+257) 22 40 25 00', 'string', 'contact'],
        ['contact.email', 'fabi@ub.edu.bi', 'email', 'contact'],
        ['contact.hours', 'Lundi–Vendredi, 8h00–16h00', 'string', 'contact'],
        ['footer.text', 'FABI — Université du Burundi. Agronomie, bio-ingénierie et développement rural.', 'text', 'footer'],
        ['footer.copyright', '© ' . date('Y') . ' FABI — Université du Burundi', 'string', 'footer'],
        ['seo.default_title', 'FABI | Université du Burundi', 'string', 'seo'],
        ['seo.default_description', 'Faculté d’Agronomie et de Bio-Ingénierie de l’Université du Burundi (campus Zege).', 'text', 'seo'],
        ['seo.theme_color', '#2E7D32', 'color', 'seo'],
    ],
    'stats' => [
        ['home_main', 'Étudiants FABI', 1200, '+', 1],
        ['home_main', 'Départements', 6, '', 2],
        ['home_main', 'Enseignants-chercheurs', 80, '+', 3],
        ['home_research', 'Publications / an', 35, '+', 1],
        ['home_research', 'Projets terrain', 12, '', 2],
        ['alumni', 'Ingénieurs agronomes formés', 4000, '+', 1],
    ],
    'programmes' => [
        [
            'level' => 'licence',
            'title' => 'Baccalauréat en Sciences agronomiques et productions végétales',
            'slug' => 'bac-productions-vegetales',
            'duration' => '3 ans (LMD)',
            'summary' => 'Filière orientée productions végétales, protection des cultures et systèmes de culture durables (département SPV).',
            'description' => "Cette formation prépare aux métiers de la production végétale, de la vulgarisation agricole et de l'appui aux exploitations familiales. Elle s'appuie sur les enseignements du département des Sciences et Productions Végétales (SPV) et sur des travaux pratiques de terrain.",
            'admission_conditions' => 'Certificat d’Humanités scientifiques ou équivalent ; modalités UB.',
            'career_outcomes' => json_encode(['Ingénieur agronome junior', 'Conseiller agricole', 'Technicien productions végétales', 'Projets de développement rural'], JSON_UNESCAPED_UNICODE),
            'display_order' => 1,
            'featured_on_home' => 1,
            'home_order' => 1,
            'is_published' => 1,
        ],
        [
            'level' => 'licence',
            'title' => 'Baccalauréat en Productions animales et agroalimentaire',
            'slug' => 'bac-productions-animales-agroalimentaire',
            'duration' => '3 ans (LMD)',
            'summary' => 'Élevage, santé animale de base, transformation et technologies alimentaires (PA / STA).',
            'description' => 'Parcours combinant sciences animales et technologies agroalimentaires pour accompagner la chaîne de valeur agricole, de l’élevage à la transformation des denrées.',
            'admission_conditions' => 'Certificat d’Humanités scientifiques ou équivalent ; modalités UB.',
            'career_outcomes' => json_encode(['Technicien élevage', 'Qualité agroalimentaire', 'Entreprenariat agricole', 'Services vétérinaires / vulgarisation'], JSON_UNESCAPED_UNICODE),
            'display_order' => 2,
            'featured_on_home' => 1,
            'home_order' => 2,
            'is_published' => 1,
        ],
        [
            'level' => 'master',
            'title' => 'Master en Sciences et technologies de l’environnement',
            'slug' => 'master-sciences-technologies-environnement',
            'duration' => '2 ans',
            'summary' => 'Environnement, sols, gestion durable des ressources (STE / SAE) — appuyé par le CRAVE.',
            'description' => "Le master approfondit l'analyse des sols, la gestion de l'environnement et les systèmes agro-environnementaux. Les enseignants du département STE et du CRAVE y développent des recherches sur la fertilité, la biodiversité et les peuplements forestiers.",
            'admission_conditions' => 'Licence/baccalauréat agronomique ou sciences apparentées ; sélection FABI.',
            'career_outcomes' => json_encode(['Expert environnement', 'Gestion des ressources naturelles', 'Recherche (CRAVE)', 'ONG / projets climat & agriculture'], JSON_UNESCAPED_UNICODE),
            'display_order' => 3,
            'featured_on_home' => 1,
            'home_order' => 3,
            'is_published' => 1,
        ],
    ],
    'staff' => [
        [
            'category' => 'enseignant',
            'name' => 'Prof. Dr Ir Séverin Nijimbere',
            'slug' => 'severin-nijimbere',
            'grade' => 'Doyen',
            'specialty' => 'Sciences agro-environnementales',
            'role' => 'Doyen de la FABI',
            'email' => 'severin.nijimbere@ub.edu.bi',
            'biography' => 'Doyen de la Faculté d’Agronomie et de Bio-Ingénierie ; enseignant notamment en évaluation de l’aptitude des terres (département SAE).',
            'display_order' => 1,
            'is_published' => 1,
            'photo' => null,
        ],
        [
            'category' => 'enseignant',
            'name' => 'Prof. Bernadette Habonimana',
            'slug' => 'bernadette-habonimana',
            'grade' => 'Professeur',
            'specialty' => 'Environnement et biodiversité',
            'role' => 'Enseignante-chercheure — CRAVE',
            'email' => 'bernadette.habonimana@ub.edu.bi',
            'biography' => 'Enseignante-chercheure FABI/CRAVE, active dans les publications sur l’environnement et la biodiversité.',
            'display_order' => 2,
            'is_published' => 1,
            'photo' => null,
        ],
    ],
    'labs' => [[
        'abbreviation' => 'CRAVE',
        'name' => 'Centre de Recherche en Sciences des Productions Animales, Végétales et Environnementales',
        'slug' => 'crave',
        'icon' => 'bi-tree',
        'description' => 'Centre de recherche de la FABI (B.P. 2940 Bujumbura) travaillant sur les productions animales et végétales, les sols, les forêts et l’environnement.',
        'themes' => json_encode(['Productions végétales', 'Productions animales', 'Sols et fertilité', 'Biodiversité et forêts'], JSON_UNESCAPED_UNICODE),
        'researcher_count' => 30,
        'display_order' => 1,
        'featured_on_home' => 1,
        'home_order' => 1,
        'is_published' => 1,
    ]],
    'publications' => [[
        'year' => 2023,
        'title' => 'Impacts socio-économiques et écologiques des peuplements d’eucalyptus au Burundi',
        'authors' => 'A. Nduwimana, R. Habonayo, B. Habonimana, et al.',
        'journal' => 'Bois & Forêts des Tropiques',
        'url' => 'https://revues.cirad.fr/index.php/BFT/article/view/37103',
        'display_order' => 1,
        'is_published' => 1,
    ]],
    'projects' => [[
        'code' => 'FABI-SOLS',
        'title' => 'Fertilité des sols et systèmes de culture familiaux',
        'description' => 'Caractérisation de la variabilité des sols et appui aux exploitations agricoles familiales, en lien avec le département STE.',
        'funder' => 'Université du Burundi / partenaires recherche',
        'period_start' => 2021,
        'period_end' => null,
        'icon' => 'bi-moisture',
        'display_order' => 1,
        'is_published' => 1,
    ]],
    'timeline' => [
        ['year' => 1958, 'title' => 'Institut agronomique', 'description' => 'Création de l’institut d’agronomie (Astrida puis transfert à Bujumbura), origine historique de la FABI.', 'display_order' => 1, 'is_published' => 1],
        ['year' => 1964, 'title' => 'Intégration à l’UOB', 'description' => 'Intégration à l’Université officielle de Bujumbura, devenue Université du Burundi.', 'display_order' => 2, 'is_published' => 1],
        ['year' => 2021, 'title' => 'Campus Zege', 'description' => 'Vie académique et associative active sur le campus Zege (Gitega), siège actuel de nombreuses activités FABI.', 'display_order' => 3, 'is_published' => 1],
    ],
    'alumni' => [
        'profile' => [
            'name' => 'Ingénieur Agronome Alumni',
            'slug' => 'alumni-fabi',
            'photo' => null,
            'promotion' => 'Promotion type',
            'role' => 'Conseiller en développement rural',
            'organization' => 'Projets agricoles / institutions publiques',
            'biography' => 'Exemple de parcours d’un lauréat FABI engagé dans l’appui aux filières agricoles.',
            'display_order' => 1,
            'is_published' => 1,
        ],
        'testimonial' => [
            'person_name' => 'Ancien·ne de la FABI',
            'photo' => null,
            'promotion' => 'Sciences agronomiques',
            'quote' => 'Les stages sur le terrain et l’encadrement du CRAVE m’ont préparé·e à travailler avec les communautés rurales.',
            'display_order' => 1,
            'is_published' => 1,
        ],
    ],
    'posts' => [
        [
            'type' => 'news',
            'title' => 'Activités académiques sur le campus Zege',
            'slug' => 'activites-campus-zege',
            'excerpt' => 'La FABI poursuit ses enseignements et travaux pratiques sur le campus de Zege.',
            'body' => "Le campus Zege accueille les étudiants de la Faculté d’Agronomie et de Bio-Ingénierie pour les cours, travaux pratiques et activités associatives. Pour les contacts administratifs : (+257) 22 40 25 00.",
            'status' => 'published',
            'published_at' => $now,
            'featured' => 1,
            'home_order' => 1,
            'event_starts_at' => null,
            'event_ends_at' => null,
            'event_location' => null,
            'registration_url' => null,
            'seo_title' => 'Campus Zege — FABI',
            'seo_description' => 'Actualité de la FABI sur le campus Zege.',
            'cover_image' => null,
            'created_by' => null,
            'updated_by' => null,
        ],
        [
            'type' => 'event',
            'title' => 'Journée environnement et biodiversité',
            'slug' => 'journee-environnement-fabi',
            'excerpt' => 'Sensibilisation et échanges scientifiques autour de l’environnement au campus Zege.',
            'body' => 'Étudiants et enseignants du département STE organisent une journée dédiée à la protection de l’environnement et à la vulgarisation des résultats de recherche du CRAVE.',
            'status' => 'published',
            'published_at' => $now,
            'featured' => 1,
            'home_order' => 2,
            'event_starts_at' => date('Y-m-d 08:30:00', strtotime('+35 days')),
            'event_ends_at' => date('Y-m-d 15:00:00', strtotime('+35 days')),
            'event_location' => 'Campus Zege — FABI',
            'registration_url' => null,
            'seo_title' => 'Journée environnement FABI',
            'seo_description' => 'Événement environnemental de la FABI.',
            'cover_image' => null,
            'created_by' => null,
            'updated_by' => null,
        ],
    ],
    'highlights' => [
        ['bi-flower1', 'Six départements', 'SPV, STA, STE, SER, PA, SA — couverture complète des sciences agronomiques et environnementales.', 1],
        ['bi-geo-alt', 'Campus Zege', 'Formation et vie étudiante sur le campus de Zege (Gitega), au cœur des enjeux agricoles nationaux.', 2],
        ['bi-journal-richtext', 'Recherche CRAVE', 'Centre de recherche en productions animales, végétales et environnementales.', 3],
    ],
    'pages' => [
        'faculty' => [
            'title' => 'La Faculté',
            'content' => [
                'banner_subtitle' => 'FABI — Faculté d’Agronomie et de Bio-Ingénierie',
                'dean' => [
                    'label' => 'Mot du doyen',
                    'title' => 'Servir le monde rural',
                    'photo' => '',
                    'name' => 'Prof. Dr Ir Séverin Nijimbere',
                    'role' => 'Doyen',
                    'specialty' => 'Sciences agro-environnementales',
                    'signature' => 'Prof. Dr Ir Séverin Nijimbere',
                    'paragraphs' => [
                        'La FABI forme des cadres capables d’accompagner la transformation agricole du Burundi.',
                        'Entre enseignement, recherche au CRAVE et ancrage sur le campus Zege, nous invitons étudiants et partenaires à construire des solutions durables pour les sols, les cultures, l’élevage et l’environnement.',
                    ],
                ],
                'mission_label' => 'Mission et vision',
                'mission_title' => 'Agriculture, innovation et durabilité',
                'mission' => [
                    'icon' => 'bi-bullseye',
                    'title' => 'Mission',
                    'paragraphs' => ['Former des bio-ingénieurs et agronomes, produire des connaissances utiles et soutenir le développement rural.'],
                ],
                'vision' => [
                    'icon' => 'bi-eye',
                    'title' => 'Vision',
                    'paragraphs' => ['Une faculté de référence en agronomie et bio-ingénierie pour l’Afrique des Grands Lacs.'],
                ],
                'values' => [
                    ['icon' => 'bi-tree', 'title' => 'Durabilité', 'description' => 'Gestion responsable des ressources naturelles.'],
                    ['icon' => 'bi-people', 'title' => 'Proximité rurale', 'description' => 'Écoute des exploitations familiales et des territoires.'],
                    ['icon' => 'bi-lightbulb', 'title' => 'Innovation', 'description' => 'Technologies agricoles et agroalimentaires adaptées.'],
                ],
                'history' => [
                    'label' => 'Historique',
                    'title' => 'Des origines de 1958 à la FABI',
                    'text' => 'Née de l’institut agronomique de 1958, intégrée à l’université en 1964, la faculté est aujourd’hui organisée en départements (SPV, STA, STE, SER, PA, SA) avec un ancrage fort à Zege et une recherche structurée au CRAVE.',
                ],
            ],
            'seo_title' => 'La FABI | Université du Burundi',
            'seo_description' => 'Présentation de la Faculté d’Agronomie et de Bio-Ingénierie.',
        ],
        'formations' => [
            'title' => 'Formations',
            'content' => [
                'banner_subtitle' => 'Filières agronomiques et bio-ingénierie',
                'offer_label' => 'Offre académique',
                'offer_title' => 'Programmes FABI',
                'offer_text' => 'Les programmes couvrent les productions végétales et animales, l’agroalimentaire, l’environnement et le développement rural, en cohérence avec les départements de la faculté.',
                'cta_title' => 'Candidatures et informations',
                'cta_text' => 'Adressez-vous au secrétariat FABI (campus Zege) pour le calendrier académique.',
                'cta_label' => 'Contacter la faculté',
                'cta_url' => '/contact',
            ],
            'seo_title' => 'Formations — FABI',
            'seo_description' => 'Programmes de la FABI.',
        ],
        'research' => [
            'title' => 'Recherche',
            'content' => [
                'banner_subtitle' => 'CRAVE et recherche agro-environnementale',
                'labs_label' => 'Centres',
                'labs_title' => 'CRAVE',
                'labs_text' => 'Le CRAVE regroupe les travaux sur les productions animales et végétales et l’environnement.',
                'publications_label' => 'Publications',
                'publications_title' => 'Revues et articles',
                'publications_text' => 'Les enseignants publient dans des revues régionales et internationales (sols, forêts, biodiversité).',
                'projects_label' => 'Projets',
                'projects_title' => 'Projets de recherche',
            ],
            'seo_title' => 'Recherche — FABI',
            'seo_description' => 'Recherche à la FABI / CRAVE.',
        ],
        'alumni' => [
            'title' => 'Alumni',
            'content' => [
                'banner_subtitle' => 'Réseau des ingénieurs agronomes',
                'intro_label' => 'Communauté',
                'intro_title' => 'Anciens de la FABI',
                'intro_paragraphs' => ['Les diplômés travaillent dans les ministères, projets de développement, ONG, entreprises agroalimentaires et la recherche.'],
                'profiles_label' => 'Profils',
                'profiles_title' => 'Parcours alumni',
                'profiles_text' => 'Exemples de trajectoires professionnelles.',
                'testimonials_label' => 'Témoignages',
                'testimonials_title' => 'Ils témoignent',
                'cta_title' => 'Rejoindre le réseau',
                'cta_text' => 'Contactez la faculté pour actualiser votre profil alumni.',
                'cta_label' => 'Nous contacter',
                'cta_url' => '/contact',
            ],
            'seo_title' => 'Alumni — FABI',
            'seo_description' => 'Alumni FABI.',
        ],
        'contact' => [
            'title' => 'Contact',
            'content' => [
                'banner_subtitle' => 'Secrétariat FABI — Campus Zege',
                'contact_label' => 'Coordonnées',
                'contact_title' => 'Contacter la FABI',
                'form_title' => 'Envoyer un message',
                'form_help' => 'Précisez votre demande (admission, partenariat recherche, visite du campus).',
                'map_url' => 'https://www.google.com/maps?q=-3.408500%2C29.934000%20(FABI%20%E2%80%94%20Campus%20Zege%2C%20Gitega)&hl=fr&z=15&output=embed',
            ],
            'seo_title' => 'Contact — FABI',
            'seo_description' => 'Contacter la FABI.',
        ],
    ],
    'dean_block' => 'Message du doyen Prof. Dr Ir Séverin Nijimbere : former des agronomes et bio-ingénieurs au service du développement rural durable.',
];

/* -------------------------------------------------------------------------- */
/* FLSH — Faculté des Lettres et des Sciences Humaines                        */
/* -------------------------------------------------------------------------- */
$faculties['flsh'] = [
    'meta' => [
        'email' => 'flsh@ub.edu.bi',
        'phone' => '(+257) 22 22 52 28',
        'address' => 'Campus universitaire, Avenue de l’UNESCO / Bujumbura — Faculté des Lettres et des Sciences Humaines, Université du Burundi, B.P. 1550',
        'primary' => '#8B2942',
        'secondary' => '#5C1A2E',
    ],
    'home' => [
        'hero_badge' => 'FLSH — Université du Burundi',
        'hero_title' => 'Lettres, langues et sciences humaines',
        'hero_text' => 'La Faculté des Lettres et des Sciences Humaines forme aux langues et littératures (africaines, anglaises, françaises), à l’histoire et à la géographie, et anime la vie culturelle universitaire, notamment via le Prix littéraire Rumuri.',
        'hero_primary_label' => 'Découvrir les formations',
        'hero_primary_url' => '/formations',
        'hero_secondary_label' => 'Contacter la faculté',
        'hero_secondary_url' => '/contact',
        'about_label' => 'Présentation',
        'about_title' => 'Une faculté humaniste au cœur de l’UB',
        'about_body' => "Historiquement l’une des plus importantes facultés de l’Université du Burundi, la FLSH regroupe les départements de Langues et Littératures Africaines, de Langue et Littérature Anglaises, de Langue et Littérature Françaises, de Géographie et d’Histoire.\n\nSes lauréats s’orientent vers l’enseignement, la presse, l’administration et le secteur culturel. La faculté héberge également le Prix littéraire Rumuri, qui valorise la création en français, anglais, kirundi et kiswahili.",
        'about_button_label' => 'En savoir plus',
        'about_button_url' => '/faculte',
        'research_label' => 'Recherche',
        'research_title' => 'Langues, territoires et mémoires',
        'research_body' => 'Unités et centres historiques : études de langues et littératures en contact (CEBELCO), aménagement (CERAM), archéologie — ainsi que les recherches départementales en histoire et géographie.',
        'research_button_label' => 'Explorer la recherche',
        'research_button_url' => '/recherche',
        'programmes_label' => 'Formations',
        'programmes_title' => 'Langues, lettres, histoire, géographie',
        'programmes_text' => 'Parcours de licence et de master en lettres et sciences humaines, ouverts sur l’enseignement et les métiers de la culture et de l’information.',
        'programmes_button_label' => 'Voir les formations',
        'programmes_button_url' => '/formations',
        'posts_label' => 'Actualités',
        'posts_title' => 'Vie littéraire et scientifique',
        'posts_text' => 'Annonces académiques, Prix Rumuri et conférences de la FLSH.',
        'posts_button_label' => 'Voir tout',
        'posts_button_url' => '/actualites',
        'seo_title' => 'FLSH | Université du Burundi',
        'seo_description' => 'Faculté des Lettres et des Sciences Humaines : langues, littératures, histoire, géographie, Prix Rumuri.',
    ],
    'home_en' => [
        'hero_title' => 'Arts, languages and humanities',
        'hero_text' => 'The Faculty of Arts and Humanities offers African, English and French language & literature tracks, as well as history and geography, and hosts the Rumuri Literary Prize.',
        'about_title' => 'A humanities faculty at the heart of UB',
        'about_body' => 'FLSH brings together African, English and French literary studies with history and geography. Graduates often move into teaching, media, administration and cultural professions.',
        'seo_title' => 'FLSH | University of Burundi',
        'seo_description' => 'Faculty of Arts and Humanities: languages, literature, history, geography, Rumuri Prize.',
    ],
    'settings' => [
        ['institution.faculty_name', 'Faculté des Lettres et des Sciences Humaines', 'string', 'institution'],
        ['institution.short_name', 'FLSH', 'string', 'institution'],
        ['institution.university', 'Université du Burundi', 'string', 'institution'],
        ['contact.address', 'Campus UB, Bujumbura — FLSH, B.P. 1550', 'string', 'contact'],
        ['contact.phone', '(+257) 22 22 52 28', 'string', 'contact'],
        ['contact.email', 'flsh@ub.edu.bi', 'email', 'contact'],
        ['contact.hours', 'Lundi–Vendredi, 8h00–16h00', 'string', 'contact'],
        ['footer.text', 'FLSH — Université du Burundi. Langues, littératures et sciences humaines.', 'text', 'footer'],
        ['footer.copyright', '© ' . date('Y') . ' FLSH — Université du Burundi', 'string', 'footer'],
        ['seo.default_title', 'FLSH | Université du Burundi', 'string', 'seo'],
        ['seo.default_description', 'Faculté des Lettres et des Sciences Humaines de l’Université du Burundi.', 'text', 'seo'],
        ['seo.theme_color', '#8B2942', 'color', 'seo'],
    ],
    'stats' => [
        ['home_main', 'Étudiants FLSH', 2500, '+', 1],
        ['home_main', 'Départements', 5, '', 2],
        ['home_main', 'Enseignants', 50, '+', 3],
        ['home_research', 'Centres / unités', 3, '', 1],
        ['home_research', 'Langues du Prix Rumuri', 4, '', 2],
        ['alumni', 'Diplômés en lettres & SHS', 8000, '+', 1],
    ],
    'programmes' => [
        [
            'level' => 'licence',
            'title' => 'Licence en Langues et Littératures (africaines / française / anglaise)',
            'slug' => 'licence-langues-litteratures',
            'duration' => '3 ans (LMD)',
            'summary' => 'Parcours littéraires et linguistiques dans les départements de littératures africaines, françaises et anglaises.',
            'description' => "La licence en langues et littératures développe compétences linguistiques, analyse littéraire et ouverture culturelle. Elle prépare à l'enseignement, à la traduction, à la communication et aux métiers du livre et des médias.",
            'admission_conditions' => 'Certificat d’Humanités (section lettres ou équivalent) ; modalités UB.',
            'career_outcomes' => json_encode(['Enseignement', 'Journalisme / communication', 'Traduction', 'Administration culturelle'], JSON_UNESCAPED_UNICODE),
            'display_order' => 1,
            'featured_on_home' => 1,
            'home_order' => 1,
            'is_published' => 1,
        ],
        [
            'level' => 'licence',
            'title' => 'Licence en Histoire',
            'slug' => 'licence-histoire',
            'duration' => '3 ans (LMD)',
            'summary' => 'Histoire du Burundi, de la région des Grands Lacs et enjeux contemporains.',
            'description' => 'Le département d’Histoire forme à la recherche documentaire, à l’analyse critique des sources et à la compréhension des dynamiques politiques et sociales.',
            'admission_conditions' => 'Certificat d’Humanités ou équivalent ; modalités UB.',
            'career_outcomes' => json_encode(['Enseignement', 'Archives / patrimoine', 'Administration', 'Recherche en sciences humaines'], JSON_UNESCAPED_UNICODE),
            'display_order' => 2,
            'featured_on_home' => 1,
            'home_order' => 2,
            'is_published' => 1,
        ],
        [
            'level' => 'licence',
            'title' => 'Licence en Géographie',
            'slug' => 'licence-geographie',
            'duration' => '3 ans (LMD)',
            'summary' => 'Géographie humaine et aménagement des territoires.',
            'description' => 'Le département de Géographie forme à l’analyse des territoires, de l’environnement humain et de l’aménagement, en lien avec les enjeux de développement du Burundi.',
            'admission_conditions' => 'Certificat d’Humanités ou équivalent ; modalités UB.',
            'career_outcomes' => json_encode(['Aménagement', 'Collectivités / projets territoriaux', 'Enseignement', 'Études environnementales'], JSON_UNESCAPED_UNICODE),
            'display_order' => 3,
            'featured_on_home' => 1,
            'home_order' => 3,
            'is_published' => 1,
        ],
        [
            'level' => 'master',
            'title' => 'Master en Lettres et Sciences Humaines',
            'slug' => 'master-lettres-sciences-humaines',
            'duration' => '2 ans',
            'summary' => 'Approfondissement disciplinaire (littératures, langues, histoire ou géographie) et initiation à la recherche.',
            'description' => 'Le master consolide l’expertise disciplinaire et méthodologique, en s’appuyant sur les centres de recherche de la faculté (langues en contact, aménagement, archéologie) et sur les séminaires départementaux.',
            'admission_conditions' => 'Licence dans la discipline ou apparentée ; sélection FLSH.',
            'career_outcomes' => json_encode(['Recherche / doctorat', 'Enseignement supérieur', 'Expertise culturelle', 'Fonction publique'], JSON_UNESCAPED_UNICODE),
            'display_order' => 4,
            'featured_on_home' => 0,
            'home_order' => null,
            'is_published' => 1,
        ],
    ],
    'staff' => [
        [
            'category' => 'enseignant',
            'name' => 'Dr Gélase Nimbona',
            'slug' => 'gelase-nimbona',
            'grade' => 'Doyen',
            'specialty' => 'Lettres et sciences humaines',
            'role' => 'Doyen de la FLSH',
            'email' => 'flsh@ub.edu.bi',
            'biography' => 'Doyen de la Faculté des Lettres et des Sciences Humaines (annuaire officiel des doyens de l’UB).',
            'display_order' => 1,
            'is_published' => 1,
            'photo' => null,
        ],
        [
            'category' => 'administratif',
            'name' => 'Secrétariat de la FLSH',
            'slug' => 'secretariat-flsh',
            'grade' => null,
            'specialty' => null,
            'role' => 'Accueil et scolarité',
            'email' => 'flsh@ub.edu.bi',
            'biography' => 'Point de contact pour inscriptions, attestations et informations sur les départements de la FLSH.',
            'display_order' => 2,
            'is_published' => 1,
            'photo' => null,
        ],
    ],
    'labs' => [
        [
            'abbreviation' => 'CEBELCO',
            'name' => 'Centre burundais d’études et de recherche sur les Langues et Littératures en contact',
            'slug' => 'cebelco',
            'icon' => 'bi-chat-quote',
            'description' => 'Centre de recherche historique de la FLSH sur les langues et littératures en contact.',
            'themes' => json_encode(['Sociolinguistique', 'Littératures en contact', 'Kirundi et langues régionales'], JSON_UNESCAPED_UNICODE),
            'researcher_count' => 12,
            'display_order' => 1,
            'featured_on_home' => 1,
            'home_order' => 1,
            'is_published' => 1,
        ],
        [
            'abbreviation' => 'CERAM',
            'name' => 'Centre d’études et de recherches en Aménagement',
            'slug' => 'ceram',
            'icon' => 'bi-map',
            'description' => 'Centre associé aux travaux de géographie et d’aménagement du territoire.',
            'themes' => json_encode(['Aménagement', 'Territoires', 'Géographie humaine'], JSON_UNESCAPED_UNICODE),
            'researcher_count' => 8,
            'display_order' => 2,
            'featured_on_home' => 1,
            'home_order' => 2,
            'is_published' => 1,
        ],
    ],
    'publications' => [[
        'year' => 2024,
        'title' => 'Prix littéraire Rumuri — valorisation de la création littéraire au Burundi',
        'authors' => 'Faculté des Lettres et des Sciences Humaines',
        'journal' => 'Université du Burundi',
        'url' => 'https://prixlitterairerumuri.ub.edu.bi/',
        'display_order' => 1,
        'is_published' => 1,
    ]],
    'projects' => [[
        'code' => 'FLSH-RUMURI',
        'title' => 'Prix littéraire Rumuri',
        'description' => 'Prix littéraire organisé au sein de la FLSH pour encourager la création en français, anglais, kirundi et kiswahili.',
        'funder' => 'Université du Burundi / partenaires culturels',
        'period_start' => 2022,
        'period_end' => null,
        'icon' => 'bi-pen',
        'display_order' => 1,
        'is_published' => 1,
    ]],
    'timeline' => [
        ['year' => 1963, 'title' => 'Ouverture des Lettres', 'description' => 'Ouverture de facultés universitaires de Philosophie, Lettres et Économie au collège du Saint-Esprit à Bujumbura.', 'display_order' => 1, 'is_published' => 1],
        ['year' => 1964, 'title' => 'Université officielle de Bujumbura', 'description' => 'Intégration des facultés dans l’UOB, devenue Université du Burundi.', 'display_order' => 2, 'is_published' => 1],
        ['year' => 2022, 'title' => 'Prix Rumuri multilingue', 'description' => 'Le Prix littéraire Rumuri s’ouvre à quatre langues : français, anglais, kirundi et kiswahili.', 'display_order' => 3, 'is_published' => 1],
    ],
    'alumni' => [
        'profile' => [
            'name' => 'Alumni Lettres',
            'slug' => 'alumni-flsh',
            'photo' => null,
            'promotion' => 'Promotion type',
            'role' => 'Enseignant / journaliste',
            'organization' => 'Éducation nationale / médias',
            'biography' => 'Exemple de parcours d’un lauréat de la FLSH dans l’enseignement ou la presse.',
            'display_order' => 1,
            'is_published' => 1,
        ],
        'testimonial' => [
            'person_name' => 'Ancien·ne de la FLSH',
            'photo' => null,
            'promotion' => 'Langues et littératures',
            'quote' => 'La FLSH m’a donné des outils d’analyse, de rédaction et d’ouverture culturelle qui me servent chaque jour.',
            'display_order' => 1,
            'is_published' => 1,
        ],
    ],
    'posts' => [
        [
            'type' => 'news',
            'title' => 'Prix littéraire Rumuri : appel aux talents',
            'slug' => 'prix-litteraire-rumuri',
            'excerpt' => 'La FLSH poursuit la promotion de la création littéraire en quatre langues.',
            'body' => "Le Prix littéraire Rumuri, hébergé à la Faculté des Lettres et des Sciences Humaines, encourage la lecture et l'écriture en français, anglais, kirundi et kiswahili. Informations : prixlitterairerumuri.ub.edu.bi",
            'status' => 'published',
            'published_at' => $now,
            'featured' => 1,
            'home_order' => 1,
            'event_starts_at' => null,
            'event_ends_at' => null,
            'event_location' => null,
            'registration_url' => null,
            'seo_title' => 'Prix Rumuri — FLSH',
            'seo_description' => 'Actualité du Prix littéraire Rumuri.',
            'cover_image' => null,
            'created_by' => null,
            'updated_by' => null,
        ],
        [
            'type' => 'event',
            'title' => 'Conférence : langues et sociétés des Grands Lacs',
            'slug' => 'conference-langues-grands-lacs',
            'excerpt' => 'Séminaire ouvert organisé par les départements de langues et d’histoire.',
            'body' => 'Enseignants et étudiants débattent des dynamiques linguistiques et historiques de la région des Grands Lacs.',
            'status' => 'published',
            'published_at' => $now,
            'featured' => 1,
            'home_order' => 2,
            'event_starts_at' => date('Y-m-d 14:00:00', strtotime('+28 days')),
            'event_ends_at' => date('Y-m-d 17:00:00', strtotime('+28 days')),
            'event_location' => 'FLSH — Campus UB, Bujumbura',
            'registration_url' => null,
            'seo_title' => 'Conférence FLSH',
            'seo_description' => 'Conférence langues et sociétés à la FLSH.',
            'cover_image' => null,
            'created_by' => null,
            'updated_by' => null,
        ],
    ],
    'highlights' => [
        ['bi-translate', 'Cinq départements', 'Littératures africaines, anglaises et françaises ; géographie ; histoire.', 1],
        ['bi-award', 'Prix Rumuri', 'Promotion de la création littéraire en quatre langues.', 2],
        ['bi-book', 'Centres de recherche', 'CEBELCO, CERAM et recherches en archéologie / sciences humaines.', 3],
    ],
    'pages' => [
        'faculty' => [
            'title' => 'La Faculté',
            'content' => [
                'banner_subtitle' => 'FLSH — Lettres et Sciences Humaines',
                'dean' => [
                    'label' => 'Mot du doyen',
                    'title' => 'Penser et transmettre',
                    'photo' => '',
                    'name' => 'Dr Gélase Nimbona',
                    'role' => 'Doyen',
                    'specialty' => 'Lettres et sciences humaines',
                    'signature' => 'Dr Gélase Nimbona',
                    'paragraphs' => [
                        'La FLSH accueille celles et ceux qui veulent comprendre les langues, les textes, les territoires et les mémoires.',
                        'Nous cultivons l’excellence académique et la vie culturelle — du séminaire de recherche au Prix littéraire Rumuri.',
                    ],
                ],
                'mission_label' => 'Mission et vision',
                'mission_title' => 'Humanités pour la société',
                'mission' => [
                    'icon' => 'bi-bullseye',
                    'title' => 'Mission',
                    'paragraphs' => ['Former des spécialistes des langues et des sciences humaines, produire de la recherche et animer le débat culturel.'],
                ],
                'vision' => [
                    'icon' => 'bi-eye',
                    'title' => 'Vision',
                    'paragraphs' => ['Une faculté humaniste de référence, ouverte sur la région des Grands Lacs et le monde.'],
                ],
                'values' => [
                    ['icon' => 'bi-book-half', 'title' => 'Esprit critique', 'description' => 'Analyse des textes et des sociétés.'],
                    ['icon' => 'bi-translate', 'title' => 'Plurilinguisme', 'description' => 'Valorisation du français, de l’anglais, du kirundi et du kiswahili.'],
                    ['icon' => 'bi-people', 'title' => 'Ouverture', 'description' => 'Dialogue interdisciplinaire et culturel.'],
                ],
                'history' => [
                    'label' => 'Historique',
                    'title' => 'Des Lettres de 1963 à la FLSH',
                    'text' => 'Ouverte en 1963 au collège du Saint-Esprit, la faculté de Lettres s’intègre en 1964 à l’université. Elle compte aujourd’hui cinq départements majeurs et anime la vie littéraire nationale via le Prix Rumuri.',
                ],
            ],
            'seo_title' => 'La FLSH | Université du Burundi',
            'seo_description' => 'Présentation de la FLSH.',
        ],
        'formations' => [
            'title' => 'Formations',
            'content' => [
                'banner_subtitle' => 'Langues, littératures, histoire, géographie',
                'offer_label' => 'Offre académique',
                'offer_title' => 'Programmes FLSH',
                'offer_text' => 'Licences et masters dans les départements de langues et littératures, d’histoire et de géographie.',
                'cta_title' => 'Informations d’inscription',
                'cta_text' => 'Contactez le secrétariat FLSH au (+257) 22 22 52 28.',
                'cta_label' => 'Contacter la faculté',
                'cta_url' => '/contact',
            ],
            'seo_title' => 'Formations — FLSH',
            'seo_description' => 'Programmes de la FLSH.',
        ],
        'research' => [
            'title' => 'Recherche',
            'content' => [
                'banner_subtitle' => 'Centres et unités de recherche',
                'labs_label' => 'Centres',
                'labs_title' => 'CEBELCO, CERAM…',
                'labs_text' => 'La recherche s’organise dans les départements et centres (langues en contact, aménagement, archéologie).',
                'publications_label' => 'Publications',
                'publications_title' => 'Revues et ouvrages',
                'publications_text' => 'Travaux disciplinaires et initiatives culturelles telles que le Prix Rumuri.',
                'projects_label' => 'Projets',
                'projects_title' => 'Projets structurants',
            ],
            'seo_title' => 'Recherche — FLSH',
            'seo_description' => 'Recherche à la FLSH.',
        ],
        'alumni' => [
            'title' => 'Alumni',
            'content' => [
                'banner_subtitle' => 'Réseau des diplômés FLSH',
                'intro_label' => 'Communauté',
                'intro_title' => 'Anciens de la FLSH',
                'intro_paragraphs' => ['Enseignement, presse, administration et culture : les débouchés historiques de la faculté restent d’actualité.'],
                'profiles_label' => 'Profils',
                'profiles_title' => 'Parcours alumni',
                'profiles_text' => 'Exemples de trajectoires.',
                'testimonials_label' => 'Témoignages',
                'testimonials_title' => 'Ils témoignent',
                'cta_title' => 'Rejoindre le réseau',
                'cta_text' => 'Écrivez au secrétariat pour rejoindre le réseau alumni.',
                'cta_label' => 'Nous contacter',
                'cta_url' => '/contact',
            ],
            'seo_title' => 'Alumni — FLSH',
            'seo_description' => 'Alumni FLSH.',
        ],
        'contact' => [
            'title' => 'Contact',
            'content' => [
                'banner_subtitle' => 'Secrétariat FLSH',
                'contact_label' => 'Coordonnées',
                'contact_title' => 'Contacter la FLSH',
                'form_title' => 'Envoyer un message',
                'form_help' => 'Précisez le département concerné (langues, histoire, géographie) si possible.',
                'map_url' => 'https://www.google.com/maps?q=-3.376061%2C29.383330%20(FLSH%20%E2%80%94%20Campus%20Mutanga%2C%20Universit%C3%A9%20du%20Burundi)&hl=fr&z=17&output=embed',
            ],
            'seo_title' => 'Contact — FLSH',
            'seo_description' => 'Contacter la FLSH.',
        ],
    ],
    'dean_block' => 'Message du doyen Dr Gélase Nimbona : transmettre les humanités et soutenir la création littéraire et la recherche en sciences humaines.',
];

foreach ($faculties as $slug => $data) {
    echo "=== Seeding {$slug} ===\n";
    applyLocalImages($data, $slug);
    $siteId = siteId($mysqli, $slug);
    updateSiteMeta($mysqli, $siteId, $data['meta'], $now);
    updateThemeHero($mysqli, $siteId, $slug, $now);

    foreach ($data['settings'] as [$key, $value, $type, $context]) {
        upsertSetting($mysqli, $siteId, $key, $value, $type, $context, $now);
    }

    $homeId = updateHome($mysqli, $siteId, $data['home'], $now);
    replaceTranslations($mysqli, $siteId, 'home_content', $homeId, $data['home_en'], $now);

    $statRows = [];
    foreach ($data['stats'] as [$section, $label, $value, $suffix, $order]) {
        $statRows[] = [
            'section' => $section,
            'label' => $label,
            'value' => $value,
            'suffix' => $suffix,
            'display_order' => $order,
            'is_published' => 1,
        ];
    }
    clearAndInsert($mysqli, 'site_stats', $siteId, $statRows, $now);

    $imgBase = 'assets/images/faculties/' . $slug;
    clearAndInsert($mysqli, 'home_hero_slides', $siteId, [
        ['image_path' => $imgBase . '/hero-1.jpg', 'alt_text' => 'Vue principale — ' . strtoupper($slug), 'display_order' => 1, 'is_published' => 1],
        ['image_path' => $imgBase . '/hero-2.jpg', 'alt_text' => 'Campus / formation — ' . strtoupper($slug), 'display_order' => 2, 'is_published' => 1],
        ['image_path' => $imgBase . '/hero-3.jpg', 'alt_text' => 'Recherche / communauté — ' . strtoupper($slug), 'display_order' => 3, 'is_published' => 1],
    ], $now);

    clearAndInsert($mysqli, 'programmes', $siteId, $data['programmes'], $now);
    clearAndInsert($mysqli, 'staff', $siteId, $data['staff'], $now);
    clearAndInsert($mysqli, 'laboratories', $siteId, $data['labs'], $now);
    clearAndInsert($mysqli, 'publications', $siteId, $data['publications'], $now);
    clearAndInsert($mysqli, 'research_projects', $siteId, $data['projects'], $now);
    clearAndInsert($mysqli, 'timeline_items', $siteId, $data['timeline'], $now);

    $mysqli->query('DELETE FROM testimonials WHERE site_id = ' . (int) $siteId);
    $mysqli->query('DELETE FROM alumni_profiles WHERE site_id = ' . (int) $siteId);
    clearAndInsert($mysqli, 'alumni_profiles', $siteId, [$data['alumni']['profile']], $now);
    $alumniId = (int) $mysqli->insert_id;
    $t = $data['alumni']['testimonial'];
    $t['alumni_profile_id'] = $alumniId;
    clearAndInsert($mysqli, 'testimonials', $siteId, [$t], $now);

    clearAndInsert($mysqli, 'posts', $siteId, $data['posts'], $now);

    $hl = [];
    foreach ($data['highlights'] as [$icon, $title, $description, $order]) {
        $hl[] = [
            'icon' => $icon,
            'title' => $title,
            'description' => $description,
            'display_order' => $order,
            'is_published' => 1,
        ];
    }
    clearAndInsert($mysqli, 'home_highlights', $siteId, $hl, $now);

    foreach ($data['pages'] as $key => $page) {
        updatePage(
            $mysqli,
            $siteId,
            $key,
            $page['title'],
            $page['content'],
            $page['seo_title'],
            $page['seo_description'],
            $now
        );
    }

    updateContentBlock($mysqli, $siteId, 'home', 'dean_message', 'Mot du doyen', $data['dean_block'], $now);
    updateContentBlock($mysqli, $siteId, 'home', 'hero', 'Héros principal', $data['home']['hero_text'], $now);

    echo "  site_id={$siteId} OK\n";
}

echo "Done.\n";
