<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;

/**
 * Demo content for the "prospective students & partners" modules:
 * departments, programme facts and curriculum, brochures, partners, FAQ,
 * and the admissions / departments / FAQ / legal pages.
 * Runs after the main demo seed, inside the same transaction.
 */
class FacultyDemoInformationSeeder
{
    public const TABLES = ['faq_items', 'partners', 'departments'];

    public const PAGE_KEYS = ['admissions', 'departments', 'faq', 'legal'];

    private const RETIRED_PAGE_KEYS = ['partners', 'agenda'];

    private const BROCHURES_PER_SITE = 3;

    private const FSI_MAQUETTE = APPPATH . 'Database/DemoAssets/maquettes/fsi.pdf';

    public function __construct(
        private readonly BaseConnection $db,
        private readonly MediaService $media,
    ) {
    }

    public function clear(int $siteId): void
    {
        foreach (self::TABLES as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->where('site_id', $siteId)->delete();
            }
        }

        $this->db->table('pages')->where('site_id', $siteId)->whereIn('key', self::RETIRED_PAGE_KEYS)->delete();
    }

    public function seed(int $siteId, string $siteSlug, string $themeKey, string $name, string $short, ?string $banner): void
    {
        if (! $this->db->tableExists('departments')) {
            return;
        }

        $set = $this->departmentSet($themeKey);
        $programmes = $this->idsBySlug('programmes', $siteId);
        $staff = $this->idsBySlug('staff', $siteId);
        $departmentIds = [];

        foreach ($set as $i => $department) {
            $this->db->table('departments')->insert([
                'site_id'       => $siteId,
                'name'          => $department['name'],
                'slug'          => $department['slug'],
                'abbreviation'  => $department['abbreviation'],
                'cycle'         => $department['cycle'],
                'description'   => $department['description'],
                'head_staff_id' => $staff[$department['staff'][0] ?? ''] ?? null,
                'email'         => $department['slug'] . '.' . strtolower($short) . '@ub.edu.bi',
                'phone'         => null,
                'display_order' => $i + 1,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
            $departmentId = (int) $this->db->insertID();
            $departmentIds[$department['slug']] = $departmentId;
            $this->translate($siteId, 'departments', $departmentId, ['name' => $department['en_name'], 'description' => $department['en_description']]);

            foreach ($department['programmes'] as $slug) {
                if (isset($programmes[$slug])) {
                    $this->db->table('programmes')->where('id', $programmes[$slug])->update(['department_id' => $departmentId]);
                }
            }
            foreach ($department['staff'] as $slug) {
                if (isset($staff[$slug])) {
                    $this->db->table('staff')->where('id', $staff[$slug])->update(['department_id' => $departmentId]);
                }
            }
        }

        $this->seedProgrammeFacts($siteId, $siteSlug, $themeKey, $staff, $set);
        $this->seedMaquettes($siteId, $siteSlug, $themeKey, $set, $departmentIds);
        $this->seedPartners($siteId, $themeKey);
        $this->seedFaq($siteId, $short);
        $this->seedPages($siteId, $name, $short, $banner);
        $this->seedPostAttachment($siteId, $siteSlug);
    }

    private function seedPostAttachment(int $siteId, string $siteSlug): void
    {
        if (! $this->db->fieldExists('attachment_path', 'posts')) {
            return;
        }

        $event = $this->db->table('posts')->where('site_id', $siteId)->where('type', 'event')->orderBy('id', 'ASC')->get(1)->getRowArray();
        if ($event === null) {
            return;
        }

        $path = $this->media->storeBinaryForSite(
            $siteSlug,
            'posts-files',
            $this->brochurePdf('Programme — ' . $event['title'], (string) $event['excerpt']),
            'pdf',
        );

        if ($path !== null) {
            $this->db->table('posts')->where('id', (int) $event['id'])->update([
                'attachment_path' => $path,
                'attachment_name' => 'programme-' . $event['slug'] . '.pdf',
            ]);
        }
    }

    /**
     * @param array<string, int> $staff
     * @param list<array<string, mixed>> $departments
     */
    private function seedProgrammeFacts(int $siteId, string $siteSlug, string $themeKey, array $staff, array $departments): void
    {
        $coordinators = [];
        foreach ($departments as $department) {
            foreach ($department['programmes'] as $index => $slug) {
                $members = $department['staff'];
                $coordinators[$slug] = $staff[$members[$index % max(1, count($members))] ?? ''] ?? null;
            }
        }

        $rows = $this->db->table('programmes')->where('site_id', $siteId)->orderBy('display_order', 'ASC')->get()->getResultArray();
        foreach ($rows as $i => $row) {
            $facts = $this->levelFacts((string) $row['level'], (string) $row['slug']);
            $years = $this->studyYears($row);
            $brochure = $i < self::BROCHURES_PER_SITE
                ? $this->media->storeBinaryForSite($siteSlug, 'brochures', $this->brochurePdf((string) $row['title'], (string) $row['summary']), 'pdf')
                : null;

            $this->db->table('programmes')->where('id', (int) $row['id'])->update([
                'language'             => str_contains((string) $row['slug'], 'anglais') ? 'Anglais, français' : 'Français',
                'credits'              => $years * 60,
                'entry_requirement'    => $facts['entry'],
                'application_deadline' => $this->nextDate($facts['deadline']),
                'curriculum'           => json_encode($this->curriculum($row, $years), JSON_UNESCAPED_UNICODE),
                'coordinator_staff_id' => $coordinators[(string) $row['slug']] ?? null,
                'brochure_path'        => $brochure,
                'apply_url'            => null,
            ]);

            $this->translate($siteId, 'programmes', (int) $row['id'], [
                'language'          => str_contains((string) $row['slug'], 'anglais') ? 'English, French' : 'French',
                'entry_requirement' => $facts['entry_en'],
            ]);
        }
    }

    /**
     * FSI departments share the real faculty maquette; the others get a
     * generated PDF listing the curriculum of their programmes.
     *
     * @param list<array<string, mixed>> $departments
     * @param array<string, int> $departmentIds
     */
    private function seedMaquettes(int $siteId, string $siteSlug, string $themeKey, array $departments, array $departmentIds): void
    {
        if (! $this->db->fieldExists('maquette_path', 'departments')) {
            return;
        }

        $shared = null;
        if ($themeKey === 'sci' && is_file(self::FSI_MAQUETTE)) {
            $shared = $this->media->storeBinaryForSite($siteSlug, 'maquettes', (string) file_get_contents(self::FSI_MAQUETTE), 'pdf');
        }

        foreach ($departments as $department) {
            $departmentId = $departmentIds[$department['slug']] ?? null;
            if ($departmentId === null) {
                continue;
            }

            $path = $shared;
            if ($path === null) {
                $path = $this->media->storeBinaryForSite($siteSlug, 'maquettes', $this->maquettePdf($siteId, $departmentId, $department['name']), 'pdf');
            }
            if ($path === null) {
                continue;
            }

            $this->db->table('departments')->where('id', $departmentId)->update([
                'maquette_path'  => $path,
                'maquette_title' => $shared !== null ? 'Maquettes des cours FSI 2024-2025' : 'Maquette des cours 2024-2025',
            ]);
            $this->translate($siteId, 'departments', $departmentId, [
                'maquette_title' => $shared !== null ? 'FSI curriculum maps 2024-2025' : 'Curriculum map 2024-2025',
            ]);
        }
    }

    private function maquettePdf(int $siteId, int $departmentId, string $departmentName): string
    {
        $rows = $this->db->table('programmes')
            ->where('site_id', $siteId)
            ->where('department_id', $departmentId)
            ->orderBy('level', 'ASC')
            ->orderBy('display_order', 'ASC')
            ->get()->getResultArray();

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = '# ' . $row['title'] . ' (' . (int) $row['credits'] . ' crédits)';
            $semester = '';
            foreach (json_decode((string) $row['curriculum'], true) ?: [] as $entry) {
                [$label, $course, $credits] = array_map('trim', explode('|', (string) $entry) + ['', '', '']);
                if ($label !== $semester) {
                    $semester = $label;
                    $lines[] = $label;
                }
                $lines[] = '      ' . $course . ' — ' . $credits . ' crédits';
            }
        }

        return $this->documentPdf('Maquette des cours — ' . $departmentName, $lines, 'Maquette de démonstration');
    }

    private function seedPartners(int $siteId, string $themeKey): void
    {
        foreach ($this->partnerSet($themeKey) as $i => $partner) {
            $this->db->table('partners')->insert([
                'site_id'       => $siteId,
                'name'          => $partner[0],
                'type'          => $partner[1],
                'logo'          => null,
                'country'       => $partner[2],
                'url'           => null,
                'description'   => $partner[3],
                'display_order' => $i + 1,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
            $this->translate($siteId, 'partners', (int) $this->db->insertID(), ['name' => $partner[4], 'description' => $partner[5]]);
        }
    }

    private function seedFaq(int $siteId, string $short): void
    {
        $items = [
            ['admissions', 'Quels diplômes faut-il pour s’inscrire en licence ?', 'Il faut avoir réussi l’Examen d’État avec au moins le nombre de points fixé par le ministre de l’Éducation, puis remplir les conditions propres à la formation choisie, indiquées sur sa fiche.', 'What qualifications do I need to enrol in a bachelor’s programme?', 'You must pass the State Exam with at least the score set by the Minister of Education, then meet the specific requirements listed on each programme page.'],
            ['admissions', 'Quand les candidatures sont-elles ouvertes ?', 'Les candidatures ouvrent généralement en mai. La date limite de chaque formation figure sur la page Admissions et sur la fiche de la formation.', 'When are applications open?', 'Applications usually open in May. Each programme’s deadline is shown on the Admissions page and on the programme page.'],
            ['admissions', 'Les candidats étrangers peuvent-ils postuler ?', 'Oui. Les candidats titulaires d’un diplôme étranger doivent joindre une équivalence délivrée par le ministère en charge de l’enseignement supérieur.', 'Can international applicants apply?', 'Yes. Applicants with a foreign diploma must include an equivalence certificate issued by the ministry in charge of higher education.'],
            ['fees', 'Les études sont-elles payantes ?', 'Non. Les études à l’Université du Burundi sont gratuites pour les lauréats de l’Examen d’État qui ont obtenu le nombre de points fixé par le ministre de l’Éducation.', 'Do I have to pay tuition?', 'No. Studies at the University of Burundi are free for State Exam graduates who reach the score set by the Minister of Education.'],
            ['fees', 'Combien de points faut-il à l’Examen d’État ?', 'Le seuil est fixé par le ministre de l’Éducation. Il peut varier selon la session et la filière : consultez le communiqué officiel et la fiche de la formation qui vous intéresse.', 'What State Exam score do I need?', 'The threshold is set by the Minister of Education and may vary by session and field: check the official announcement and the page of the programme you are interested in.'],
            ['studies', 'Dans quelle langue les cours sont-ils donnés ?', 'La plupart des enseignements sont donnés en français. Certains cours, notamment en master, sont proposés en anglais.', 'What language are courses taught in?', 'Most courses are taught in French. Some courses, especially at master’s level, are offered in English.'],
            ['studies', 'Le stage est-il obligatoire ?', 'Oui, un stage encadré est prévu en dernière année de licence et de master. La faculté aide les étudiants à trouver une structure d’accueil.', 'Is an internship compulsory?', 'Yes, a supervised internship is part of the final year of bachelor’s and master’s programmes. The faculty helps students find a host organisation.'],
            ['other', 'Comment obtenir une attestation d’inscription ?', 'Adressez votre demande au service de la scolarité avec votre numéro d’étudiant. L’attestation est délivrée sous trois jours ouvrables.', 'How do I get an enrolment certificate?', 'Send your request to the registrar office with your student number. The certificate is issued within three working days.'],
        ];

        foreach ($items as $i => [$category, $question, $answer, $enQuestion, $enAnswer]) {
            $this->db->table('faq_items')->insert([
                'site_id'       => $siteId,
                'category'      => $category,
                'question'      => $question,
                'answer'        => $answer,
                'display_order' => $i + 1,
                'is_published'  => 1,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
            $this->translate($siteId, 'faq_items', (int) $this->db->insertID(), ['question' => $enQuestion, 'answer' => $enAnswer]);
        }
    }

    private function seedPages(int $siteId, string $name, string $short, ?string $banner): void
    {
        $pages = [
            'admissions' => [
                'Admissions',
                [
                    'banner_title'    => '',
                    'banner_subtitle' => 'Rejoindre ' . $short . ' : conditions d’accès, pièces à fournir et dates limites',
                    'banner_image'    => $banner,
                    'intro_label'     => 'Candidater',
                    'intro_title'     => 'Comment candidater à ' . $short,
                    'intro_text'      => 'La procédure est la même pour toutes les formations. Les conditions d’accès et les dates limites propres à chaque formation sont indiquées plus bas.',
                    'steps'           => [
                        'Choisissez votre formation et vérifiez les conditions d’admission sur sa fiche.',
                        'Préparez les pièces du dossier listées ci-contre.',
                        'Déposez votre dossier au service de la scolarité ou envoyez-le par e-mail avant la date limite.',
                        'Consultez la liste des candidats admis, publiée sur le site et affichée à la faculté.',
                        'Confirmez votre inscription au service de la scolarité et retirez votre carte d’étudiant.',
                    ],
                    'documents_title' => 'Pièces à fournir',
                    'documents'       => [
                        'Copie certifiée du diplôme d’État ou du diplôme donnant accès à la formation',
                        'Relevés de notes des deux dernières années',
                        'Copie de la carte d’identité ou du passeport',
                        'Deux photos d’identité récentes',
                        'Lettre de motivation (master)',
                    ],
                    'fees_title'      => 'Des études gratuites',
                    'fees_text'       => 'Les études à l’Université du Burundi sont gratuites pour les lauréats de l’Examen d’État qui ont obtenu le nombre de points fixé par le ministre de l’Éducation.' . "\n\n" . 'Le seuil de points peut varier selon la session et la filière : vérifiez-le sur la fiche de chaque formation.',
                    'portal_title'    => 'Prêt·e à rejoindre ' . $short . ' ?',
                    'portal_text'     => 'Le service de la scolarité répond à vos questions et reçoit les dossiers du lundi au vendredi.',
                    'portal_label'    => 'Contacter la scolarité',
                    'portal_url'      => '',
                ],
                [
                    'banner_subtitle' => 'Join ' . $short . ': entry requirements, required documents and deadlines',
                    'intro_label'     => 'Apply',
                    'intro_title'     => 'How to apply to ' . $short,
                    'intro_text'      => 'The procedure is the same for every programme. Entry requirements and deadlines for each programme are listed below.',
                    'steps'           => [
                        'Choose your programme and check the admission requirements on its page.',
                        'Prepare the documents listed alongside.',
                        'Submit your application to the registrar office or by email before the deadline.',
                        'Check the list of admitted applicants, published on the website and at the faculty.',
                        'Confirm your enrolment at the registrar office and collect your student card.',
                    ],
                    'documents_title' => 'Required documents',
                    'documents'       => [
                        'Certified copy of the State diploma or the qualification giving access to the programme',
                        'Transcripts for the last two years',
                        'Copy of your identity card or passport',
                        'Two recent passport photos',
                        'Cover letter (master’s programmes)',
                    ],
                    'fees_title'      => 'Tuition-free studies',
                    'fees_text'       => 'Studies at the University of Burundi are free for State Exam graduates who reach the score set by the Minister of Education.' . "\n\n" . 'The score threshold may vary by session and field: check it on each programme page.',
                    'portal_title'    => 'Ready to join ' . $short . '?',
                    'portal_text'     => 'The registrar office answers your questions and receives applications Monday to Friday.',
                    'portal_label'    => 'Contact the registrar',
                ],
            ],
            'departments' => [
                'Départements',
                ['banner_title' => '', 'banner_subtitle' => 'Les unités d’enseignement et de recherche de ' . $short, 'banner_image' => $banner, 'intro_label' => 'Organisation', 'intro_title' => 'Nos départements', 'intro_text' => 'Chaque département regroupe des enseignants, des formations et des axes de recherche autour d’une discipline.'],
                ['banner_subtitle' => $short . '’s teaching and research units', 'intro_label' => 'Organisation', 'intro_title' => 'Our departments', 'intro_text' => 'Each department brings together teaching staff, programmes and research around one discipline.'],
            ],
            'faq' => [
                'Questions fréquentes',
                ['banner_title' => '', 'banner_subtitle' => 'Admissions, gratuité, études : les réponses aux questions les plus posées', 'banner_image' => $banner, 'intro_label' => 'Aide', 'intro_title' => 'Vos questions, nos réponses', 'intro_text' => 'Vous ne trouvez pas votre réponse ? Écrivez-nous depuis la page Contact.'],
                ['banner_subtitle' => 'Admissions, free tuition, studies: answers to the most common questions', 'intro_label' => 'Help', 'intro_title' => 'Your questions answered', 'intro_text' => 'Can’t find your answer? Write to us from the Contact page.'],
            ],
            'legal' => [
                'Mentions légales & confidentialité',
                [
                    'publisher_title'      => 'Éditeur du site',
                    'publisher_paragraphs' => [
                        'Ce site est édité par ' . $name . ', Université du Burundi.',
                        'Responsable de la publication : le Doyen de la faculté.',
                    ],
                    'host_title'           => 'Hébergement',
                    'host_text'            => 'Site hébergé par l’Université du Burundi (démonstration). Les coordonnées de l’hébergeur définitif seront précisées lors de la mise en ligne.',
                    'privacy_title'        => 'Données personnelles',
                    'privacy_paragraphs'   => [
                        'Le formulaire de contact recueille votre nom, votre adresse électronique, éventuellement votre téléphone, ainsi que votre message. Ces informations servent uniquement à vous répondre.',
                        'Les messages sont conservés douze mois au maximum, puis supprimés. Ils ne sont ni vendus ni transmis à des tiers.',
                        'Vous pouvez demander l’accès, la rectification ou la suppression de vos données en écrivant au secrétariat de la faculté.',
                    ],
                    'cookies_title'        => 'Cookies et mesure d’audience',
                    'cookies_text'         => 'Le site utilise uniquement des cookies techniques : session, sécurité des formulaires et mémorisation de la langue. Aucun cookie publicitaire n’est déposé.',
                ],
                [
                    'publisher_title'      => 'Publisher',
                    'publisher_paragraphs' => [
                        'This website is published by ' . $name . ', University of Burundi.',
                        'Publication director: the Dean of the faculty.',
                    ],
                    'host_title'           => 'Hosting',
                    'host_text'            => 'Hosted by the University of Burundi (demonstration). Final hosting details will be provided at launch.',
                    'privacy_title'        => 'Personal data',
                    'privacy_paragraphs'   => [
                        'The contact form collects your name, email address, optionally your phone number, and your message. This information is only used to reply to you.',
                        'Messages are kept for at most twelve months, then deleted. They are never sold or shared with third parties.',
                        'You may request access to, correction or deletion of your data by writing to the faculty office.',
                    ],
                    'cookies_title'        => 'Cookies and audience measurement',
                    'cookies_text'         => 'This website only uses technical cookies: session, form security and language preference. No advertising cookie is set.',
                ],
            ],
        ];

        foreach ($pages as $key => [$title, $content, $english]) {
            $this->db->table('pages')->where('site_id', $siteId)->where('key', $key)->delete();
            $this->db->table('pages')->insert([
                'site_id'         => $siteId,
                'key'             => $key,
                'title'           => $title,
                'slug'            => $key,
                'seo_title'       => $title . ' | ' . $short,
                'seo_description' => $content['banner_subtitle'] ?? $title,
                'content'         => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_published'    => 1,
                'created_at'      => $this->now(),
                'updated_at'      => $this->now(),
            ]);
            $this->translate($siteId, 'pages', (int) $this->db->insertID(), [
                'content' => (string) json_encode(array_replace($content, $english), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function idsBySlug(string $table, int $siteId): array
    {
        $ids = [];
        foreach ($this->db->table($table)->select('id, slug')->where('site_id', $siteId)->get()->getResultArray() as $row) {
            $ids[(string) $row['slug']] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * @param array<string, string> $values
     */
    private function translate(int $siteId, string $resourceType, int $resourceId, array $values): void
    {
        if (! $this->db->tableExists('content_translations')) {
            return;
        }

        foreach ($values as $field => $value) {
            $this->db->table('content_translations')->insert([
                'site_id'       => $siteId,
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'locale'        => 'en',
                'field'         => $field,
                'value'         => $value,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ]);
        }
    }

    /**
     * @return array{entry: string, entry_en: string, deadline: string}
     */
    private function levelFacts(string $level, string $slug = ''): array
    {
        if (str_starts_with($slug, 'des-')) {
            return ['entry' => 'Baccalauréat en médecine et concours d’accès aux spécialisations', 'entry_en' => 'Medical degree and the specialisation entrance examination', 'deadline' => '06-30'];
        }

        return match ($level) {
            'master' => ['entry' => 'Licence dans le domaine et sélection sur dossier', 'entry_en' => 'Bachelor’s degree in the field and selection on file', 'deadline' => '06-30'],
            default  => ['entry' => 'Examen d’État : points fixés par le ministre de l’Éducation', 'entry_en' => 'State Exam: score set by the Minister of Education', 'deadline' => '07-31'],
        };
    }

    private function nextDate(string $monthDay): string
    {
        $today = new DateTimeImmutable('today');
        $date = new DateTimeImmutable($today->format('Y') . '-' . $monthDay);

        return ($date < $today ? $date->modify('+1 year') : $date)->format('Y-m-d');
    }

    private function domain(string $title): string
    {
        $title = preg_replace('/^.*\s—\s/u', '', $title) ?? $title;

        return preg_replace('/^(Licence|Master|Baccalauréat|DES|Diplôme d’études spécialisées)\s+(en|de|d’)\s+/iu', '', $title) ?? $title;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function studyYears(array $row): int
    {
        if (preg_match('/\d+/', (string) ($row['duration'] ?? ''), $match) === 1 && (int) $match[0] > 0) {
            return (int) $match[0];
        }

        return $row['level'] === 'master' ? 2 : 4;
    }

    /**
     * "Semester | Course | Credits" lines, the format edited in the admin.
     * Programmes sharing a trunk get identical courses during the trunk
     * years; each semester carries 30 credits.
     *
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    private function curriculum(array $row, int $years): array
    {
        $level = (string) $row['level'];
        $core = trim((string) ($row['common_core'] ?? ''));
        $branch = trim((string) ($row['branch_core'] ?? ''));
        $coreYears = $core === '' ? 0 : max(1, (int) ($row['common_core_years'] ?? 0) ?: ($level === 'master' ? 1 : 2));
        $branchYears = $core === '' || $branch === '' ? 0 : max(1, (int) ($row['branch_core_years'] ?? 0));
        $option = strtoupper(trim((string) ($row['option_code'] ?? '')));
        $domain = $this->domain((string) $row['title']);
        $semesters = $years * 2;

        $segments = [];
        for ($semester = 1; $semester < $semesters; $semester++) {
            $year = (int) ceil($semester / 2);
            $segments[$semester] = match (true) {
                $year <= $coreYears                => ['core', $core],
                $year <= $coreYears + $branchYears => ['branch', $branch],
                default                            => ['option', $option],
            };
        }

        $used = [];
        $offsets = [];
        $lines = [];
        foreach ($segments as $semester => [$kind, $key]) {
            $pool = array_merge(
                $kind === 'option' ? ($this->optionCourses()[($level === 'master' && ! str_starts_with((string) $row['slug'], 'des-') ? 'M:' : '') . $key] ?? []) : ($this->trunkCourses()[mb_strtolower($key)] ?? []),
                $this->transversalCourses(),
                $this->genericCourses($kind === 'option' ? $domain : $key, $level),
            );
            $poolKey = $kind . '|' . $key;
            $offsets[$poolKey] ??= 0;
            foreach ([7, 6, 6, 6, 5] as $credits) {
                while ($offsets[$poolKey] < count($pool) && isset($used[$pool[$offsets[$poolKey]]])) {
                    $offsets[$poolKey]++;
                }
                $course = $pool[$offsets[$poolKey]] ?? $domain . ' : module ' . (count($lines) + 1);
                $used[$course] = true;
                $offsets[$poolKey]++;
                $lines[] = 'Semestre ' . $semester . ' | ' . $course . ' | ' . $credits;
            }
        }

        foreach ($this->finalSemester($row, $option) as [$course, $credits]) {
            $lines[] = 'Semestre ' . $semesters . ' | ' . $course . ' | ' . $credits;
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array{0: string, 1: int}>
     */
    private function finalSemester(array $row, string $option): array
    {
        $slug = (string) $row['slug'];

        return match (true) {
            in_array($option, ['GME', 'GEL', 'BI', 'GH', 'AMU', 'GI', 'GL', 'RT', 'SE'], true) && $row['level'] === 'licence' => [
                ['Entrepreneuriat 2', 2], ['Éthique 2', 2], ['Organisation et gestion 2', 2], ['Projet technique 2', 6], ['Stage en entreprise', 10], ['Projet de fin de cycle', 8],
            ],
            $slug === 'baccalaureat-medecine' => [['Stages cliniques rotatoires', 12], ['Santé communautaire : stage de terrain', 6], ['Mémoire de fin d’études', 12]],
            str_starts_with($slug, 'des-')    => [['Stage hospitalier de responsabilité', 12], ['Mémoire de spécialisation', 18]],
            $row['level'] === 'master'        => [['Séminaire de recherche', 4], ['Stage professionnel', 10], ['Mémoire de master', 16]],
            default                           => [['Éthique et déontologie professionnelles', 4], ['Méthodologie du mémoire', 4], ['Stage', 10], ['Mémoire de fin d’études', 12]],
        };
    }

    /**
     * Trunk courses, keyed by lower-case trunk name. FSI trunks follow MAQ-FSI.pdf.
     *
     * @return array<string, list<string>>
     */
    private function trunkCourses(): array
    {
        return [
            'tronc commun fsi' => ['Algèbre', 'Analyse mathématique 1', 'Géométrie analytique', 'Physique générale 1', 'Électricité et magnétisme', 'Chimie générale', 'Informatique', 'Techniques d’expression orale et écrite', 'Analyse mathématique 2', 'Probabilités et statistiques', 'Technologie des matériaux', 'Statique', 'Géométrie descriptive', 'Dessin industriel'],
            'tronc commun tic' => ['Physique générale', 'Algèbre linéaire', 'Analyse mathématique', 'Électronique de base', 'Circuits électriques', 'Logique mathématique', 'Architecture des ordinateurs', 'Algorithmique', 'Programmation C', 'Traitement du signal analogique', 'Électronique analogique', 'Probabilités et statistique'],
            'tronc commun gi-gl' => ['Recherche opérationnelle', 'Théorie des automates', 'Programmation Web', 'Systèmes d’exploitation Windows et Linux', 'Structures de données linéaires', 'Théorie des bases de données', 'Ingénierie des logiciels', 'Design des réseaux informatiques', 'Programmation orientée objet C++/C#', 'Langage Java', 'Administration de bases de données', 'Microprocesseurs'],
            'tronc commun se-rt' => ['Traitement du signal numérique', 'Électronique numérique', 'Systèmes de communications', 'Bases de données', 'Introduction aux réseaux', 'Réseaux mobiles', 'Propagation des ondes électromagnétiques', 'Antennes et hyperfréquences', 'Microprocesseurs et microcontrôleurs', 'Programmation orientée objet'],
            'tronc commun génie civil' => ['Cinématique et dynamique', 'Résistance des matériaux', 'Chimie des matériaux de construction', 'Matériaux de construction', 'Dessin du bâtiment', 'Géométrie cotée', 'Analyse numérique et applications', 'Dessin assisté par ordinateur', 'Hydrologie de l’ingénieur', 'Géologie de l’ingénieur'],
            'tronc commun fseg' => ['Introduction à la microéconomie', 'Introduction à la macroéconomie', 'Comptabilité générale', 'Mathématiques générales', 'Introduction au droit', 'Statistique descriptive', 'Introduction à la gestion', 'Histoire des faits économiques', 'Informatique de gestion', 'Sociologie économique'],
            'tronc commun gestion' => ['Comptabilité analytique', 'Mathématiques financières', 'Marketing fondamental', 'Gestion des ressources humaines', 'Droit des affaires', 'Statistique inférentielle', 'Théorie des organisations', 'Fiscalité burundaise', 'Gestion de la production', 'Recherche opérationnelle'],
            'tronc commun des spécialités médicales' => ['Sémiologie médicale avancée', 'Pharmacologie clinique', 'Urgences médicales', 'Imagerie médicale', 'Biologie clinique et interprétation', 'Épidémiologie clinique', 'Médecine fondée sur les preuves', 'Éthique médicale', 'Soins palliatifs et prise en charge de la douleur', 'Recherche clinique'],
            'tronc commun des spécialités chirurgicales' => ['Anatomie chirurgicale', 'Techniques opératoires de base', 'Asepsie et hygiène hospitalière', 'Urgences chirurgicales', 'Anesthésie pour chirurgiens', 'Imagerie et chirurgie', 'Traumatologie de base', 'Éthique médicale', 'Chirurgie ambulatoire', 'Recherche clinique'],
            'tronc commun fabi' => ['Biologie végétale', 'Biologie animale', 'Chimie générale', 'Chimie organique', 'Mathématiques', 'Physique', 'Statistique', 'Botanique systématique', 'Pédologie générale', 'Biochimie', 'Microbiologie générale', 'Génétique', 'Agrométéorologie', 'Économie générale', 'Zoologie', 'Écologie générale', 'Topographie', 'Informatique', 'Physiologie végétale', 'Sociologie rurale'],
            'tronc commun langues et cultures africaines' => ['Introduction à la linguistique africaine', 'Kirundi : phonologie et morphologie', 'Littérature orale africaine', 'Histoire des cultures africaines', 'Kiswahili 1', 'Introduction aux sciences de la culture', 'Ethnolinguistique', 'Kiswahili 2', 'Littératures africaines écrites', 'Patrimoine culturel immatériel', 'Sociologie de la culture', 'Kirundi : syntaxe', 'Arts du spectacle africains', 'Méthodes d’enquête de terrain', 'Traduction kirundi-français', 'Anthropologie culturelle', 'Kiswahili 3', 'Grammaire comparée des langues bantoues', 'Médias et cultures', 'Industries culturelles'],
            'tronc commun lettres françaises' => ['Grammaire du français', 'Introduction à la linguistique', 'Littérature française du Moyen Âge', 'Histoire de la langue française', 'Analyse du discours', 'Littérature française du XVIe siècle', 'Phonétique et phonologie', 'Littérature française du XVIIe siècle', 'Stylistique', 'Littératures francophones', 'Lexicologie', 'Littérature française du XVIIIe siècle', 'Sémantique', 'Narratologie', 'Littérature française du XIXe siècle', 'Sociolinguistique', 'Poétique', 'Latin', 'Théorie littéraire', 'Littérature comparée'],
            'tronc commun socio-anthropologie' => ['Introduction à la sociologie', 'Introduction à l’anthropologie', 'Histoire de la pensée sociale', 'Méthodes de recherche qualitative', 'Démographie', 'Statistique sociale', 'Anthropologie de la parenté', 'Sociologie de la famille', 'Méthodes de recherche quantitative', 'Sociologie rurale', 'Anthropologie religieuse', 'Sociologie urbaine', 'Anthropologie politique', 'Sociologie du travail', 'Théories sociologiques contemporaines', 'Sociologie du développement', 'Anthropologie économique', 'Psychologie sociale', 'Enquête de terrain', 'Sociologie des organisations'],
        ];
    }

    /**
     * Option courses keyed by option code. FSI options follow MAQ-FSI.pdf.
     *
     * @return array<string, list<string>>
     */
    private function optionCourses(): array
    {
        return [
            'GME' => ['Métrologie dimensionnelle', 'Machines électriques', 'Mécanique des fluides', 'Résistance des matériaux', 'Thermodynamique technique', 'Turbomachines', 'Énergies renouvelables', 'Machines thermiques', 'Fabrication mécanique', 'Organes des machines', 'Asservissement et régulation', 'Technologie de l’automobile', 'Climatisation et réfrigération', 'Robotique', 'Machines hydrauliques'],
            'GEL' => ['Circuits électriques', 'Appareillages électriques', 'Électronique industrielle', 'Installations électriques', 'Production de l’énergie électrique', 'Réseaux haute tension', 'Photovoltaïque et éolien', 'Biomasse', 'Isolation et surtension', 'Machines électriques', 'Asservissement et régulation', 'Électronique de puissance'],
            'BI'  => ['Architecture II', 'Physique du bâtiment', 'Mécanique des sols II', 'Mécanique des structures', 'Béton armé I', 'Béton armé II', 'Systèmes d’information géographique', 'Étude d’impact environnemental', 'Organisation des chantiers', 'Béton précontraint', 'Ponts', 'Métré'],
            'GH'  => ['Gestion intégrée des ressources en eau', 'Hydraulique appliquée', 'Barrages et ouvrages connexes', 'Mécanique des sols II', 'Béton armé', 'Systèmes d’information géographique', 'Équipement de construction', 'Hydraulique urbaine', 'Irrigation et drainage'],
            'AMU' => ['Principes de l’économie', 'Développement durable', 'Démographie', 'Technologie de construction', 'Droit foncier', 'Économie urbaine', 'Analyse socio-économique', 'Géotechnique', 'Route', 'Organisation de l’espace', 'Architecture', 'Cartographie et SIG', 'Urbanisme opérationnel'],
            'GI'  => ['Systèmes parallèles et répartis', 'Sécurité des réseaux', 'Cryptologie', 'Cybersécurité', 'Compilation', 'Cloud computing', 'Intelligence artificielle', 'Machine learning', 'Audit informatique', 'Robotique'],
            'GL'  => ['MERISE', 'UML', 'Traitement d’images', 'Codage et compression', 'Aide à la décision', 'Entrepôt de données', 'Data mining', 'Java mobile', 'Programmation événementielle'],
            'RT'  => ['Réseaux WAN', 'Programmation des services réseaux', 'Réseaux IEEE 802.X', 'Théorie de l’information', 'Commutation', 'Communications numériques', 'Réseaux de transmission numérique'],
            'SE'  => ['Internet des objets', 'Semi-conducteurs et circuits intégrés', 'Conception des systèmes embarqués', 'Applications des systèmes embarqués', 'Langages machines', 'Systèmes temps réel'],
            'CF'  => ['Comptabilité des sociétés', 'Contrôle de gestion', 'Finance d’entreprise', 'Audit et contrôle interne', 'Fiscalité des entreprises', 'Comptabilité bancaire', 'Normes comptables OHADA et IFRS', 'Analyse financière', 'Gestion de trésorerie', 'Évaluation d’entreprises'],
            'MM'  => ['Marketing opérationnel', 'Comportement du consommateur', 'Management stratégique', 'Gestion des ressources humaines avancée', 'Entrepreneuriat et création d’entreprise', 'Marketing digital', 'Communication commerciale', 'Logistique et chaîne d’approvisionnement', 'Négociation commerciale', 'Management de projet'],
            'EP'  => ['Microéconomie approfondie', 'Macroéconomie approfondie', 'Économétrie', 'Économie monétaire', 'Finances publiques', 'Économie internationale', 'Économie du développement', 'Histoire de la pensée économique', 'Croissance économique', 'Économie de l’environnement', 'Politique économique', 'Économie régionale des Grands Lacs'],
            'ER'  => ['Économie agricole', 'Économie des filières agroalimentaires', 'Gestion des coopératives', 'Évaluation de projets de développement rural', 'Marketing agroalimentaire', 'Économie des ressources naturelles', 'Politiques agricoles', 'Microfinance rurale', 'Gestion des exploitations agricoles', 'Sécurité alimentaire', 'Économie foncière', 'Commerce des produits agricoles'],
            'MED' => ['Anatomie humaine', 'Histologie', 'Biologie cellulaire', 'Biophysique', 'Chimie médicale', 'Physiologie humaine', 'Biochimie', 'Embryologie', 'Génétique médicale', 'Psychologie médicale', 'Microbiologie', 'Parasitologie', 'Immunologie', 'Anatomie pathologique', 'Pharmacologie', 'Sémiologie médicale', 'Sémiologie chirurgicale', 'Physiopathologie', 'Imagerie médicale', 'Hématologie', 'Cardiologie', 'Pneumologie', 'Gastro-entérologie', 'Néphrologie', 'Neurologie', 'Endocrinologie', 'Maladies infectieuses', 'Dermatologie', 'Pédiatrie', 'Gynécologie-obstétrique', 'Chirurgie générale', 'Orthopédie-traumatologie', 'Urologie', 'ORL', 'Ophtalmologie', 'Psychiatrie', 'Anesthésie-réanimation', 'Médecine légale', 'Santé publique', 'Épidémiologie', 'Nutrition', 'Médecine communautaire', 'Urgences médicales', 'Urgences chirurgicales', 'Gériatrie'],
            'MI'  => ['Cardiologie clinique', 'Pneumologie clinique', 'Gastro-entérologie et hépatologie', 'Néphrologie clinique', 'Endocrinologie et diabétologie', 'Maladies infectieuses et VIH', 'Hématologie clinique', 'Rhumatologie', 'Neurologie clinique', 'Gériatrie'],
            'PED' => ['Néonatologie', 'Nutrition et croissance de l’enfant', 'Infectiologie pédiatrique', 'Cardiologie pédiatrique', 'Pneumologie pédiatrique', 'Urgences pédiatriques', 'Neurologie pédiatrique', 'Hémato-oncologie pédiatrique', 'Santé de l’adolescent', 'Vaccinologie'],
            'CHIR' => ['Chirurgie digestive', 'Chirurgie viscérale d’urgence', 'Traumatologie et orthopédie', 'Urologie', 'Chirurgie thoracique', 'Chirurgie pédiatrique', 'Neurochirurgie de base', 'Chirurgie plastique et brûlures', 'Cancérologie chirurgicale', 'Cœlioscopie'],
            'GO'  => ['Obstétrique normale', 'Grossesses à risque', 'Urgences obstétricales', 'Gynécologie médicale', 'Chirurgie gynécologique', 'Planification familiale', 'Infertilité', 'Cancers gynécologiques', 'Échographie obstétricale', 'Santé maternelle et néonatale'],
            'AR'  => ['Pharmacologie des anesthésiques', 'Anesthésie générale', 'Anesthésie locorégionale', 'Réanimation médicale', 'Réanimation chirurgicale', 'Prise en charge de la douleur', 'Anesthésie pédiatrique et obstétricale', 'Urgences vitales', 'Ventilation mécanique', 'Transfusion sanguine'],
            'SPV' => ['Phytotechnie des cultures vivrières', 'Amélioration des plantes', 'Protection des cultures', 'Cultures industrielles : café et thé', 'Horticulture', 'Semences et pépinières', 'Malherbologie', 'Fertilisation des sols'],
            'SPA' => ['Zootechnie générale', 'Nutrition animale', 'Santé animale', 'Production laitière', 'Aviculture', 'Pisciculture et aquaculture', 'Reproduction animale', 'Pastoralisme'],
            'STA' => ['Technologie des céréales', 'Technologie laitière', 'Conservation des aliments', 'Microbiologie alimentaire', 'Contrôle de qualité', 'Génie des procédés alimentaires', 'Emballage des produits alimentaires', 'Nutrition humaine'],
            'STE' => ['Écologie appliquée', 'Gestion des ressources naturelles', 'Changements climatiques', 'Évaluation environnementale', 'Gestion des déchets', 'Hydrologie', 'Foresterie et agroforesterie', 'Biodiversité et conservation'],
            'SER' => ['Économie des exploitations', 'Vulgarisation agricole', 'Sociologie rurale', 'Gestion des projets agricoles', 'Commercialisation des produits agricoles', 'Politiques agricoles', 'Organisations paysannes', 'Développement local'],
            'SAE' => ['Science du sol', 'Aptitude des terres', 'Conservation des eaux et des sols', 'Agroécologie', 'Cartographie des sols', 'Irrigation et drainage', 'Fertilité des sols', 'Agroforesterie'],
            'ELA' => ['Littératures africaines contemporaines', 'Kirundi : littérature écrite', 'Critique littéraire africaine', 'Oralité et écriture', 'Littérature swahilie', 'Théâtre africain', 'Contes et mythes', 'Lexicographie kirundi'],
            'EC'  => ['Économie de la culture', 'Gestion de projets culturels', 'Marketing culturel', 'Droit de la propriété intellectuelle', 'Tourisme culturel', 'Médiation culturelle', 'Production audiovisuelle', 'Financement de la culture'],
            'LF'  => ['Littérature française du XXe siècle', 'Littérature française contemporaine', 'Critique littéraire', 'Roman francophone', 'Poésie française', 'Théâtre français', 'Histoire des idées', 'Atelier d’analyse littéraire'],
            'SLCL' => ['Linguistique textuelle', 'Pragmatique', 'Atelier d’écriture créative', 'Psycholinguistique', 'Écriture journalistique', 'Édition et métiers du livre', 'Didactique de l’écrit', 'Écriture de scénario'],
            'LLA' => ['English Phonetics and Phonology', 'English Grammar', 'Introduction to Linguistics', 'British Literature', 'American Literature', 'Oral Communication', 'Academic Writing', 'Morphology and Syntax', 'African Literature in English', 'Semantics', 'Sociolinguistics', 'Translation Studies', 'Discourse Analysis', 'Literary Criticism', 'Postcolonial Literatures', 'History of the English Language', 'Pragmatics', 'Teaching English as a Foreign Language', 'Creative Writing', 'Interpreting Skills'],
            'HP'  => ['Introduction à l’histoire', 'Préhistoire et archéologie', 'Histoire ancienne', 'Histoire du Burundi précolonial', 'Histoire de l’Afrique', 'Histoire médiévale', 'Histoire moderne', 'Histoire coloniale', 'Histoire contemporaine', 'Archivistique', 'Muséologie', 'Patrimoine culturel', 'Historiographie', 'Paléographie', 'Histoire des Grands Lacs', 'Histoire économique et sociale', 'Histoire des religions', 'Sources orales', 'Gestion du patrimoine', 'Histoire des relations internationales'],
            'GEO' => ['Géographie physique', 'Géographie humaine', 'Cartographie', 'Climatologie', 'Géomorphologie', 'Hydrologie', 'Biogéographie', 'Géographie de la population', 'Géographie rurale', 'Géographie urbaine', 'Télédétection', 'Systèmes d’information géographique', 'Aménagement du territoire', 'Géographie économique', 'Géographie du Burundi', 'Géographie des risques', 'Pédologie', 'Géographie régionale de l’Afrique', 'Statistique spatiale', 'Travaux de terrain'],
            'ANT' => ['Anthropologie de la santé', 'Anthropologie du développement', 'Anthropologie visuelle', 'Ethnographie', 'Anthropologie des conflits', 'Patrimoine et mémoire', 'Anthropologie juridique', 'Anthropologie urbaine'],
            'SOC' => ['Sociologie de l’éducation', 'Sociologie politique', 'Sociologie des conflits', 'Sociologie du genre', 'Sociologie de la santé', 'Sociologie des médias', 'Évaluation de programmes sociaux', 'Sociologie de la jeunesse'],
            'M:GL' => ['Architecture logicielle', 'Génie logiciel avancé', 'Méthodes agiles', 'Tests et qualité logicielle', 'Systèmes distribués', 'DevOps', 'Big data', 'Sécurité des applications'],
            'M:RT' => ['Réseaux de nouvelle génération', 'Réseaux mobiles 4G/5G', 'Optimisation des réseaux', 'Sécurité des réseaux avancée', 'Virtualisation des réseaux', 'Communications par satellite', 'Internet des objets', 'Ingénierie du trafic'],
        ];
    }

    /**
     * @return list<string>
     */
    private function genericCourses(string $subject, string $level): array
    {
        $suffixes = $level === 'master'
            ? ['théories avancées', 'méthodes quantitatives', 'méthodes qualitatives', 'études de cas', 'politiques et pratiques', 'séminaire thématique', 'projet appliqué', 'questions contemporaines']
            : ['fondamentaux', 'méthodes et outils', 'approfondissement', 'travaux pratiques', 'études de cas', 'projet tutoré', 'séminaire', 'questions contemporaines'];

        return array_map(static fn (string $suffix): string => $subject . ' : ' . $suffix, $suffixes);
    }

    /**
     * @return list<string>
     */
    private function transversalCourses(): array
    {
        return ['Anglais', 'Techniques d’expression écrite et orale', 'Méthodologie de la recherche', 'Entrepreneuriat', 'Informatique appliquée', 'Statistique appliquée', 'Droit et législation', 'Communication professionnelle', 'Stage d’initiation', 'Éthique'];
    }

    /**
     * Minimal one-page PDF (Helvetica, WinAnsi) with a title and a summary.
     */
    private function brochurePdf(string $title, string $summary): string
    {
        return $this->documentPdf($title, explode("\n", wordwrap($summary, 85)), 'Brochure de démonstration');
    }

    /**
     * Minimal multi-page PDF (Helvetica, WinAnsi). Lines starting with "# "
     * are printed as bold headings.
     *
     * @param list<string> $lines
     */
    private function documentPdf(string $title, array $lines, string $footer): string
    {
        $encode = static fn (string $text): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) iconv('UTF-8', 'Windows-1252//TRANSLIT', $text));

        $pages = [];
        $current = ['BT /F2 20 Tf 60 770 Td (' . $encode($title) . ') Tj ET'];
        $y = 735;
        foreach ($lines as $line) {
            $heading = str_starts_with($line, '# ');
            if ($y < ($heading ? 110 : 80)) {
                $pages[] = $current;
                $current = [];
                $y = 780;
            }
            if ($heading) {
                $y -= 8;
                $current[] = 'BT /F2 12 Tf 60 ' . $y . ' Td (' . $encode(substr($line, 2)) . ') Tj ET';
                $y -= 18;
            } else {
                $current[] = 'BT /F1 10 Tf 60 ' . $y . ' Td (' . $encode($line) . ') Tj ET';
                $y -= 14;
            }
        }
        $pages[] = $current;

        $count = count($pages);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        foreach ($pages as $index => $content) {
            $content[] = 'BT /F1 9 Tf 60 50 Td (' . $encode($footer . ' — ' . ($index + 1) . '/' . $count) . ') Tj ET';
            $stream = implode("\n", $content);
            $pageId = 5 + $index * 2;
            $kids[] = $pageId . ' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($pageId + 1) . ' 0 R >>';
            $objects[$pageId + 1] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $count . ' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        return $pdf . 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    }

    /**
     * Departments of the University of Burundi faculties. The first staff
     * slug heads the department; staff index i coordinates programme i.
     *
     * @return list<array{name: string, slug: string, abbreviation: string, cycle: string, description: string, en_name: string, en_description: string, programmes: list<string>, staff: list<string>}>
     */
    private function departmentSet(string $key): array
    {
        $d = static fn (string $abbr, string $slug, string $cycle, string $name, string $description, string $enName, string $enDescription, array $programmes, array $staff): array => [
            'abbreviation' => $abbr, 'slug' => $slug, 'cycle' => $cycle, 'name' => $name, 'description' => $description,
            'en_name' => $enName, 'en_description' => $enDescription, 'programmes' => $programmes, 'staff' => $staff,
        ];

        $sets = [
            'eco' => [
                $d('GEST', 'gestion', 'both', 'Département de Gestion', 'Deux options de licence — Comptabilité et finance, Marketing et management — qui suivent le tronc commun FSEG en Bac I puis le tronc commun Gestion en Bac II, et le Master en Sciences de gestion.', 'Department of Management', 'Two bachelor options — Accounting and Finance, Marketing and Management — following the FSEG common core in year 1 and the management common core in year 2, plus the Master’s in Management Sciences.', ['licence-comptabilite-finance', 'licence-marketing-management', 'master-sciences-gestion'], ['hakizimana', 'bigirimana', 'nkurunziza']),
                $d('ECOPO', 'economie-politique', 'both', 'Département d’Économie politique', 'Théorie économique, économétrie, économie publique et politiques de développement ; spécialisation dès le Bac II et Master en Analyse économique et développement.', 'Department of Political Economy', 'Economic theory, econometrics, public economics and development policy; specialisation from year 2 and the Master’s in Economic Analysis and Development.', ['licence-economie-politique', 'master-analyse-economique-developpement'], ['dusabimana', 'ndayishimiye']),
                $d('ERA', 'economie-rurale-agroalimentaire', 'both', 'Département d’Économie rurale et entreprises agroalimentaires', 'Économie agricole, filières agroalimentaires et développement rural ; Master en Économie rurale, sociale et de l’environnement.', 'Department of Rural Economics and Agri-food Enterprises', 'Agricultural economics, agri-food value chains and rural development; Master’s in Rural, Social and Environmental Economics.', ['licence-economie-rurale', 'master-economie-rurale-sociale-environnement'], ['manirakiza', 'ntakarutimana', 'niyonkuru', 'minani']),
            ],
            'sci' => [
                $d('EM', 'electromecanique', 'licence', 'Département d’Électromécanique', 'Deux filières — Génie mécanique et énergétique (GME) et Génie électrique (GEL) — après le tronc commun FSI du Bac I, partagé avec le Génie civil et l’Aménagement et urbanisme.', 'Department of Electromechanical Engineering', 'Two tracks — Mechanical and Energy Engineering (GME) and Electrical Engineering (GEL) — after the FSI common core in year 1, shared with Civil Engineering and Planning.', ['licence-genie-mecanique-energetique', 'licence-genie-electrique'], ['aime-nsabimana', 'kevin-bizimana']),
                $d('GC', 'genie-civil', 'licence', 'Département de Génie civil', 'Tronc commun FSI en Bac I, Bac II commun aux deux options, puis Bâtiments et infrastructures (BI) ou Génie hydraulique (GH) à partir du Bac III.', 'Department of Civil Engineering', 'FSI common core in year 1, a shared year 2, then Buildings and Infrastructure (BI) or Hydraulic Engineering (GH) from year 3.', ['licence-batiments-infrastructures', 'licence-genie-hydraulique'], ['linda-muco', 'thierry-manirakiza']),
                $d('AU', 'amenagement-urbanisme', 'licence', 'Département d’Aménagement et urbanisme', 'Organisation de l’espace, droit foncier, économie urbaine et projets d’aménagement, après le tronc commun FSI du Bac I.', 'Department of Planning and Urbanism', 'Spatial organisation, land law, urban economics and planning projects, after the FSI common core in year 1.', ['licence-amenagement-urbanisme'], ['solange-uwimana']),
                $d('TIC', 'tic', 'both', 'Département des Technologies de l’information et de la communication', 'Tronc commun TIC en Bac I pour les quatre options, puis Bac II commun à GI et GL d’une part, à SE et RT d’autre part ; spécialisation à partir du Bac III. Masters en Génie logiciel et en Réseaux et télécommunications.', 'Department of Information and Communication Technologies', 'ICT common core in year 1 for all four options, then a year 2 shared by GI and GL on one side and by SE and RT on the other; specialisation from year 3. Master’s degrees in Software Engineering and in Networks and Telecommunications.', ['licence-genie-informatique', 'licence-genie-logiciel', 'licence-reseaux-telecommunications', 'licence-systemes-embarques', 'master-genie-logiciel', 'master-reseaux-telecommunications'], ['nadia-baraka', 'florence-irakoze', 'eric-habonimana', 'kevin-bizimana', 'joseph-niyonkuru', 'nadia-baraka']),
            ],
            'med' => [
                $d('SBM', 'sciences-biomedicales', 'licence', 'Département des Sciences biomédicales', 'Sciences fondamentales et biomédicales des premières années du Baccalauréat en médecine : anatomie, physiologie, biochimie, microbiologie.', 'Department of Biomedical Sciences', 'Basic and biomedical sciences of the first years of the medical degree: anatomy, physiology, biochemistry, microbiology.', ['baccalaureat-medecine'], ['esther-ndayi', 'pacifique-habi']),
                $d('MI', 'medecine-interne', 'master', 'Département de Médecine interne', 'Services de médecine interne du CHUK et DES de Médecine interne.', 'Department of Internal Medicine', 'Internal medicine wards at the university hospital and the specialist degree in internal medicine.', ['des-medecine-interne'], ['olga-nibigira']),
                $d('PED', 'pediatrie', 'master', 'Département de Pédiatrie', 'Pédiatrie, néonatologie et urgences pédiatriques ; DES de Pédiatrie.', 'Department of Paediatrics', 'Paediatrics, neonatology and paediatric emergencies; specialist degree in paediatrics.', ['des-pediatrie'], ['placide-nduwimana']),
                $d('CHIR', 'chirurgie', 'master', 'Département de Chirurgie', 'Chirurgie générale, traumatologie et urgences chirurgicales ; DES de Chirurgie générale.', 'Department of Surgery', 'General surgery, traumatology and surgical emergencies; specialist degree in general surgery.', ['des-chirurgie-generale'], ['serge-niyonkuru']),
                $d('GO', 'gynecologie-obstetrique', 'master', 'Département de Gynécologie-obstétrique', 'Santé de la femme, maternité et chirurgie gynécologique ; DES de Gynécologie-obstétrique.', 'Department of Obstetrics and Gynaecology', 'Women’s health, maternity and gynaecological surgery; specialist degree in obstetrics and gynaecology.', ['des-gynecologie-obstetrique'], ['alice-hakizimana']),
                $d('AR', 'anesthesie-reanimation', 'master', 'Département d’Anesthésie-réanimation', 'Anesthésie, réanimation et médecine d’urgence ; DES d’Anesthésie-réanimation.', 'Department of Anaesthesia and Intensive Care', 'Anaesthesia, intensive care and emergency medicine; specialist degree in anaesthesia and intensive care.', ['des-anesthesie-reanimation'], ['yves-manirakiza']),
                $d('SC', 'sante-communautaire', 'master', 'Département de Santé communautaire', 'Épidémiologie, santé publique et médecine communautaire ; Master en Santé publique.', 'Department of Community Health', 'Epidemiology, public health and community medicine; Master’s in Public Health.', ['master-sante-publique'], ['rachel-bigirimana', 'immaculee-ndayizeye']),
            ],
            'agro' => [
                $d('SPV', 'production-vegetale', 'both', 'Département des Sciences de la production végétale', 'Phytotechnie, amélioration et protection des plantes, après le tronc commun FABI du Bac I et du Bac II.', 'Department of Plant Production Sciences', 'Crop science, plant breeding and protection, after the FABI common core in years 1 and 2.', ['licence-production-vegetale'], ['diane-niyongabo']),
                $d('SPA', 'production-animale', 'both', 'Département des Sciences de la production animale', 'Zootechnie, nutrition et santé animales, aquaculture.', 'Department of Animal Production Sciences', 'Animal science, nutrition and health, aquaculture.', ['licence-production-animale'], ['solange-uwi-agro']),
                $d('STA', 'technologies-agroalimentaires', 'both', 'Département des Sciences et technologies agroalimentaires', 'Transformation, conservation et qualité des produits agricoles.', 'Department of Food Science and Technology', 'Processing, preservation and quality of farm produce.', ['licence-technologies-agroalimentaires'], ['thierry-bizi']),
                $d('STE', 'environnement', 'both', 'Département des Sciences et technologies de l’environnement', 'Écologie, gestion des ressources naturelles et climat ; Master en Sciences et technologies de l’environnement.', 'Department of Environmental Science and Technology', 'Ecology, natural resource management and climate; Master’s in Environmental Science and Technology.', ['licence-environnement', 'master-environnement'], ['pacifique-habiya', 'eric-ndayi-agro']),
                $d('SER', 'socio-economie-rurale', 'both', 'Département de Socio-économie rurale', 'Économie des exploitations, filières, vulgarisation et développement rural.', 'Department of Rural Socio-economics', 'Farm economics, value chains, extension and rural development.', ['licence-socio-economie-rurale', 'master-socio-economie-rurale'], ['chantal-haki-agro', 'apollinaire-ndayi']),
                $d('SAE', 'agro-environnement', 'both', 'Département des Sciences agro-environnementales', 'Sciences du sol, aptitude des terres, conservation des eaux et des sols.', 'Department of Agro-environmental Sciences', 'Soil science, land suitability, soil and water conservation.', ['licence-sciences-agro-environnementales', 'master-sciences-agro-environnementales'], ['claude-nsa-agro', 'esther-ndayi-agro']),
            ],
            'hum' => [
                $d('LCA', 'langues-cultures-africaines', 'both', 'Département de Langues et cultures africaines', 'Études et littératures africaines et filière Entrepreneuriat culturel, avec un tronc commun en Bac I et Bac II.', 'Department of African Languages and Cultures', 'African studies and literatures and the Cultural Entrepreneurship track, with a common core in years 1 and 2.', ['licence-etudes-litteratures-africaines', 'licence-entrepreneuriat-culturel'], ['linda-muco-hum', 'carine-niyo-hum']),
                $d('LLF', 'langue-litterature-francaises', 'both', 'Département de Langue et littérature françaises', 'Lettres françaises et option Sciences du langage et création littéraire, avec un tronc commun en Bac I et Bac II ; Master en Didactique du français.', 'Department of French Language and Literature', 'French studies and the Language Sciences and Creative Writing option, with a common core in years 1 and 2; Master’s in French Didactics.', ['licence-lettres-francaises', 'licence-sciences-langage-creation-litteraire', 'master-didactique-francais'], ['benjamin-haki', 'beatrice-nku', 'beatrice-nku']),
                $d('LLA', 'langue-litterature-anglaises', 'both', 'Département de Langue et littérature anglaises', 'Linguistique et littératures anglophones ; Master en Communication interculturelle et traduction.', 'Department of English Language and Literature', 'English linguistics and literatures; Master’s in Intercultural Communication and Translation.', ['licence-anglais', 'master-communication-interculturelle-traduction'], ['grace-nku-hum']),
                $d('HIST', 'histoire', 'both', 'Département d’Histoire', 'Filière Histoire et patrimoine ; Master Sociétés, pouvoirs, territoires et développement durable.', 'Department of History', 'History and Heritage track; Master’s in Societies, Powers, Territories and Sustainable Development.', ['licence-histoire-patrimoine', 'master-societes-pouvoirs-territoires'], ['olivier-mani', 'aime-nsa-hum']),
                $d('GEO', 'geographie', 'both', 'Département de Géographie', 'Géographie physique et humaine, cartographie, SIG et aménagement du territoire.', 'Department of Geography', 'Physical and human geography, cartography, GIS and land-use planning.', ['licence-geographie'], ['samuel-ndayi']),
                $d('SOA', 'socio-anthropologie', 'both', 'Département de Socio-anthropologie', 'Filières Anthropologie et Sociologie avec un tronc commun en Bac I et Bac II ; Master en Socio-anthropologie.', 'Department of Socio-anthropology', 'Anthropology and Sociology tracks with a common core in years 1 and 2; Master’s in Socio-anthropology.', ['licence-anthropologie', 'licence-sociologie', 'master-socio-anthropologie'], ['diane-ira-hum']),
            ],
        ];

        return $sets[$key] ?? $sets['eco'];
    }

    /**
     * [name, type, country, description, English name, English description]
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>
     */
    private function partnerSet(string $key): array
    {
        $common = [
            ['Réseau universitaire des Grands Lacs', 'academic', 'Région des Grands Lacs', 'Échanges d’enseignants et d’étudiants, co-encadrement de thèses.', 'Great Lakes University Network', 'Staff and student exchanges, joint thesis supervision.'],
            ['Ministère de l’Enseignement supérieur', 'institution', 'Burundi', 'Accréditation des programmes et appui aux réformes pédagogiques.', 'Ministry of Higher Education', 'Programme accreditation and support for teaching reforms.'],
            ['Agence de coopération universitaire', 'institution', 'International', 'Financement de projets de recherche et de mobilité.', 'University Cooperation Agency', 'Funding for research projects and mobility.'],
        ];
        $specific = [
            'eco'  => [['Chambre de commerce et d’industrie', 'industry', 'Burundi', 'Stages en entreprise et conférences de professionnels.', 'Chamber of Commerce and Industry', 'Company internships and talks by professionals.'], ['Association des microfinances', 'ngo', 'Burundi', 'Études de terrain sur l’inclusion financière.', 'Microfinance Association', 'Field studies on financial inclusion.']],
            'sci'  => [['Opérateur de télécommunications partenaire', 'industry', 'Burundi', 'Stages, laboratoire réseaux et hackathons.', 'Partner telecom operator', 'Internships, networking lab and hackathons.'], ['Centre régional d’énergie solaire', 'institution', 'Afrique de l’Est', 'Projets communs sur les mini-réseaux solaires.', 'Regional Solar Energy Centre', 'Joint projects on solar mini-grids.']],
            'med'  => [['Centre hospitalo-universitaire', 'institution', 'Burundi', 'Terrain de stage clinique pour les étudiants.', 'University Teaching Hospital', 'Clinical training site for students.'], ['ONG de santé communautaire', 'ngo', 'Burundi', 'Campagnes de prévention et recherche en santé communautaire.', 'Community health NGO', 'Prevention campaigns and community health research.']],
            'agro' => [['Coopérative agricole régionale', 'industry', 'Burundi', 'Parcelles d’essai et stages en exploitation.', 'Regional farming cooperative', 'Trial plots and on-farm internships.'], ['Institut de recherche agronomique', 'institution', 'Burundi', 'Programmes communs d’amélioration variétale.', 'Agricultural Research Institute', 'Joint crop breeding programmes.']],
            'hum'  => [['Archives nationales', 'institution', 'Burundi', 'Accès aux fonds d’archives et stages de documentation.', 'National Archives', 'Access to archive collections and documentation internships.'], ['Radio communautaire partenaire', 'ngo', 'Burundi', 'Ateliers de journalisme et émissions produites par les étudiants.', 'Partner community radio', 'Journalism workshops and student-produced programmes.']],
        ];

        return array_merge($common, $specific[$key] ?? $specific['eco']);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
