<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Administration') ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/images/logo-placeholder.png') ?>">
    <link href="<?= site_asset_url('assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= site_asset_url('assets/vendor/bootstrap-icons/bootstrap-icons.css') ?>" rel="stylesheet">
    <link href="<?= site_asset_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="bg-light admin-body">
    <a class="skip-link" href="#contenu">Aller au contenu principal</a>
    <?php
    $adminUser = auth()->user();
    $adminAccess = service('adminAccess');
    $siteResolver = service('siteResolver');
    $isCentralAdmin = $adminAccess->isCentralAdminHost();
    $activeSite = $siteResolver->activeSite();
    $availableSites = $adminUser !== null && ! $isCentralAdmin && $adminAccess->isSuperAdmin($adminUser) ? $siteResolver->availableSitesForUser($adminUser) : [];
    $siteShortName = strtoupper(trim((string) ($activeSite->identifier ?? ''))) ?: 'UB';
    $brandTitle = $isCentralAdmin ? 'Administration UB' : 'Administration ' . $siteShortName;
    $brandSubtitle = $isCentralAdmin ? 'Plateforme multi-facultés' : 'Université du Burundi';
    $navGroups = [
        'Accueil' => [
            'icon'  => 'bi-house-door',
            'links' => [
                ['home-content', 'Contenu', 'home.manage', 'bi-layout-text-window'],
                ['home-hero-slides', 'Carrousel', 'home.manage', 'bi-images'],
                ['home-highlights', 'Atouts', 'home.manage', 'bi-stars'],
                ['site-stats', 'Statistiques', 'home.manage', 'bi-bar-chart'],
            ],
        ],
        'Contenus' => [
            'icon'  => 'bi-files',
            'links' => [
                ['posts', 'Actualités et événements', ['news.manage', 'events.manage'], 'bi-newspaper'],
                ['programmes', 'Formations', 'programmes.manage', 'bi-mortarboard'],
                ['pages', 'Pages', 'pages.manage', 'bi-file-earmark-text'],
                ['content-blocks', 'Blocs de contenu', 'pages.manage', 'bi-ui-checks-grid'],
                ['timeline-items', 'Historique', 'pages.manage', 'bi-clock-history'],
            ],
        ],
        'Faculté' => [
            'icon'  => 'bi-bank',
            'links' => [
                ['faculty/profile', 'Présentation / Mot du doyen', 'pages.manage', 'bi-person-vcard'],
            ],
        ],
        'Messagerie' => [
            'icon'  => 'bi-inbox',
            'links' => [
                ['messages', 'Messages de contact', 'messages.manage', 'bi-envelope-paper'],
            ],
        ],
        'Sécurité' => [
            'icon'  => 'bi-shield-lock',
            'links' => [
                ['users', 'Utilisateurs', 'users.manage', 'bi-people'],
            ],
        ],
        'Recherche' => [
            'icon'  => 'bi-search',
            'links' => [
                ['laboratories', 'Laboratoires', 'research.manage', 'bi-diagram-3'],
                ['publications', 'Publications', 'research.manage', 'bi-journal-text'],
                ['research-projects', 'Projets', 'research.manage', 'bi-briefcase'],
            ],
        ],
        'Communauté' => [
            'icon'  => 'bi-person-hearts',
            'links' => [
                ['staff', 'Personnel', 'staff.manage', 'bi-person-badge'],
                ['alumni-profiles', 'Alumni', 'alumni.manage', 'bi-award'],
                ['testimonials', 'Témoignages', 'alumni.manage', 'bi-chat-quote'],
            ],
        ],
        'Configuration' => [
            'icon'  => 'bi-sliders',
            'links' => [
                ['settings', 'Paramètres', 'settings.manage', 'bi-gear'],
                ['sites', 'Sites facultaires', 'sites.manage', 'bi-diagram-2'],
            ],
        ],
    ];

    $canUseLink = static function (?object $user, string|array $permissions): bool {
        foreach ((array) $permissions as $permission) {
            if (($user?->can($permission) ?? false) === true) {
                return true;
            }
        }

        return false;
    };
    ?>
    <div class="admin-shell">
        <aside class="admin-sidebar collapse d-lg-flex" id="adminSidebar" aria-label="Navigation d’administration">
            <div class="admin-sidebar-inner">
                <a class="navbar-brand admin-brand" href="<?= site_url('admin') ?>">
                    <img src="<?= base_url('assets/images/logo-placeholder.png') ?>" alt="Logo Université du Burundi">
                    <span class="navbar-brand-text">
                        <span class="brand-title"><?= esc($brandTitle) ?></span>
                        <span class="brand-subtitle"><?= esc($brandSubtitle) ?></span>
                    </span>
                </a>

                <nav class="admin-nav" aria-label="Modules">
                    <a class="admin-nav-link <?= ($activeAdmin ?? 'dashboard') === 'dashboard' ? 'active' : '' ?>" href="<?= site_url('admin') ?>" <?= ($activeAdmin ?? 'dashboard') === 'dashboard' ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-speedometer2" aria-hidden="true"></i>
                        <span>Tableau de bord</span>
                    </a>

                    <?php foreach ($navGroups as $groupLabel => $group): ?>
                        <?php
                        $visibleLinks = array_values(array_filter(
                            $group['links'],
                            static fn (array $link): bool => $canUseLink($adminUser, $link[2]),
                        ));
                        $activeKeys = array_map(static fn (array $link): string => (string) $link[0], $visibleLinks);
                        $isGroupActive = in_array((string) ($activeAdmin ?? ''), $activeKeys, true);
                        $groupId = 'adminNavGroup' . preg_replace('/[^A-Za-z0-9]+/', '', $groupLabel);
                        ?>
                        <?php if ($visibleLinks !== []): ?>
                            <section class="admin-nav-section">
                                <button class="admin-nav-group-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($groupId, 'attr') ?>" aria-expanded="<?= $isGroupActive ? 'true' : 'false' ?>" aria-controls="<?= esc($groupId, 'attr') ?>">
                                    <span>
                                        <i class="bi <?= esc((string) $group['icon'], 'attr') ?>" aria-hidden="true"></i>
                                        <?= esc($groupLabel) ?>
                                    </span>
                                    <i class="bi bi-chevron-down admin-nav-chevron" aria-hidden="true"></i>
                                </button>
                                <div id="<?= esc($groupId, 'attr') ?>" class="collapse <?= $isGroupActive ? 'show' : '' ?>">
                                    <ul class="admin-nav-list">
                                        <?php foreach ($visibleLinks as [$key, $label, $permissions, $icon]): ?>
                                            <?php $isActive = ($activeAdmin ?? '') === $key; ?>
                                            <li>
                                                <a class="admin-nav-link <?= $isActive ? 'active' : '' ?>" href="<?= site_url('admin/' . $key) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                                                    <i class="bi <?= esc((string) $icon, 'attr') ?>" aria-hidden="true"></i>
                                                    <span><?= esc($label) ?></span>
                                                </a>
                                            </li>
                                        <?php endforeach ?>
                                    </ul>
                                </div>
                            </section>
                        <?php endif ?>
                    <?php endforeach ?>
                </nav>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <button class="btn btn-outline-green admin-menu-toggle d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-expanded="false" aria-label="Ouvrir le menu d’administration">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <div class="admin-topbar-title">
                    <span class="section-label mb-0">Administration</span>
                    <strong><?= esc($title ?? 'Administration') ?></strong>
                    <span class="admin-current-site">
                        <i class="bi <?= $isCentralAdmin ? 'bi-diagram-2' : 'bi-building' ?>" aria-hidden="true"></i>
                        <?= esc($isCentralAdmin ? 'Administration centrale' : ($activeSite->name ?? 'Site courant')) ?>
                    </span>
                </div>
                <div class="admin-topbar-actions">
                    <?php if (count($availableSites) > 1): ?>
                        <form class="admin-site-switcher" method="post" action="<?= site_url('admin/site-selection') ?>">
                            <?= csrf_field() ?>
                            <label class="visually-hidden" for="adminSiteSwitcher">Site à administrer</label>
                            <select class="form-select form-select-sm" id="adminSiteSwitcher" name="site_id" onchange="this.form.submit()">
                                <?php foreach ($availableSites as $site): ?>
                                    <option value="<?= esc((string) $site->id, 'attr') ?>" <?= (int) $site->id === (int) ($activeSite->id ?? 0) ? 'selected' : '' ?>>
                                        <?= esc($site->name) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                            <noscript><button class="btn btn-outline-green btn-sm" type="submit">Changer</button></noscript>
                        </form>
                    <?php endif ?>
                    <?php if (! $isCentralAdmin): ?>
                        <a href="<?= site_url('/') ?>" class="btn btn-outline-green btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Voir le site
                        </a>
                    <?php endif ?>
                    <a href="<?= site_url('logout') ?>" class="btn btn-primary-green btn-sm">
                        <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Déconnexion
                    </a>
                </div>
            </header>

            <main id="contenu" tabindex="-1" class="admin-content container-fluid py-4">
                <?= $this->include('partials/flash') ?>
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>

    <script src="<?= site_asset_url('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= site_asset_url('assets/js/admin.js') ?>"></script>
</body>
</html>
