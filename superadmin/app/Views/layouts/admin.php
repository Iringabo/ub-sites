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
<?php
$nav = service('adminNavigation');
$isCentralAdmin = $nav->isCentral();
$adminUser = auth()->user();
$activeSite = service('siteResolver')->activeSite();
$availableSites = $nav->availableSites($adminUser);
$brand = $nav->brand();
$navGroups = $nav->groups($adminUser);
$previewUrl = $nav->previewUrl();
$brandTitle = $brand['title'];
$brandSubtitle = $brand['subtitle'];
?>
<body class="bg-light admin-body<?= $isCentralAdmin ? ' admin-central' : '' ?>">
    <a class="skip-link" href="#contenu">Aller au contenu principal</a>
    <div id="adminSiteLoading" class="admin-site-loading" hidden aria-live="assertive" aria-busy="false">
        <div class="admin-site-loading-card" role="status">
            <div class="spinner-border text-success" aria-hidden="true"></div>
            <p class="mb-0 fw-semibold">Chargement de la faculté…</p>
            <p class="mb-0 small text-muted">Les contenus sont mis à jour pour le site sélectionné.</p>
        </div>
    </div>
    <div class="admin-shell">
        <aside class="admin-sidebar collapse d-lg-flex" id="adminSidebar" aria-label="Navigation d’administration">
            <div class="admin-sidebar-inner">
                <a class="navbar-brand admin-brand" href="<?= $nav->dashboardUrl() ?>">
                    <img src="<?= base_url('assets/images/logo-placeholder.png') ?>" alt="Logo Université du Burundi">
                    <span class="navbar-brand-text">
                        <span class="brand-title"><?= esc($brandTitle) ?></span>
                        <span class="brand-subtitle"><?= esc($brandSubtitle) ?></span>
                    </span>
                </a>

                <nav class="admin-nav" aria-label="Modules">
                    <a class="admin-nav-link <?= ($activeAdmin ?? 'dashboard') === 'dashboard' ? 'active' : '' ?>" href="<?= $nav->dashboardUrl() ?>" <?= ($activeAdmin ?? 'dashboard') === 'dashboard' ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-speedometer2" aria-hidden="true"></i>
                        <span><?= $isCentralAdmin ? 'Tableau de bord plateforme' : 'Tableau de bord' ?></span>
                    </a>

                    <?php foreach ($navGroups as $group): ?>
                        <?php
                        $visibleLinks = $group['links'];
                        $activeKeys = array_map(static fn (array $link): string => (string) $link['key'], $visibleLinks);
                        $isGroupActive = in_array((string) ($activeAdmin ?? ''), $activeKeys, true);
                        $groupId = 'adminNavGroup' . preg_replace('/[^A-Za-z0-9]+/', '', (string) $group['id']);
                        ?>
                        <section class="admin-nav-section">
                            <button class="admin-nav-group-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($groupId, 'attr') ?>" aria-expanded="<?= $isGroupActive ? 'true' : 'false' ?>" aria-controls="<?= esc($groupId, 'attr') ?>">
                                <span>
                                    <i class="bi <?= esc((string) $group['icon'], 'attr') ?>" aria-hidden="true"></i>
                                    <?= esc((string) $group['label']) ?>
                                </span>
                                <i class="bi bi-chevron-down admin-nav-chevron" aria-hidden="true"></i>
                            </button>
                            <div id="<?= esc($groupId, 'attr') ?>" class="collapse <?= $isGroupActive ? 'show' : '' ?>">
                                <ul class="admin-nav-list">
                                    <?php foreach ($visibleLinks as $link): ?>
                                        <?php $isActive = ($activeAdmin ?? '') === $link['key']; ?>
                                        <li>
                                            <a class="admin-nav-link <?= $isActive ? 'active' : '' ?>" href="<?= site_url('admin/' . $link['key']) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                                                <i class="bi <?= esc((string) $link['icon'], 'attr') ?>" aria-hidden="true"></i>
                                                <span><?= esc((string) $link['label']) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach ?>
                                </ul>
                            </div>
                        </section>
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
                    <span class="admin-current-site<?= $isCentralAdmin ? ' admin-current-site-central' : '' ?>">
                        <i class="bi <?= $isCentralAdmin ? 'bi-diagram-2' : 'bi-building' ?>" aria-hidden="true"></i>
                        <?= esc($nav->editingLabel()) ?>
                    </span>
                </div>
                <div class="admin-topbar-actions">
                    <button type="button" class="btn btn-outline-green btn-sm" id="adminPaletteHint" data-admin-palette-open title="Rechercher un module (Ctrl+K)">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <span class="d-none d-xl-inline">Rechercher</span>
                        <kbd class="d-none d-xl-inline">Ctrl+K</kbd>
                    </button>
                    <?php if (count($availableSites) > 1): ?>
                        <?php
                        $hasExplicitSite = service('siteResolver')->hasExplicitAdminSiteSelection();
                        $currentPath = site_url(uri_string());
                        ?>
                        <form
                            class="admin-site-switcher"
                            method="post"
                            action="<?= site_url('admin/site-selection') ?>"
                            hx-post="<?= site_url('admin/site-selection') ?>"
                            hx-swap="none"
                            data-admin-site-switcher
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="<?= esc($currentPath, 'attr') ?>">
                            <label class="visually-hidden" for="adminSiteSwitcher">Site à administrer</label>
                            <select class="form-select form-select-sm" id="adminSiteSwitcher" name="site_id" required>
                                <?php if ($isCentralAdmin && ! $hasExplicitSite): ?>
                                    <option value="" selected disabled>Choisir une faculté…</option>
                                <?php endif ?>
                                <?php foreach ($availableSites as $site): ?>
                                    <option value="<?= esc((string) $site->id, 'attr') ?>" <?= $hasExplicitSite && (int) $site->id === (int) ($activeSite->id ?? 0) ? 'selected' : '' ?>>
                                        <?= esc($site->name) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                            <noscript><button class="btn btn-outline-green btn-sm" type="submit">Changer</button></noscript>
                        </form>
                    <?php endif ?>
                    <?php if ($previewUrl !== null): ?>
                        <a href="<?= esc($previewUrl, 'attr') ?>" class="btn btn-outline-green btn-sm" target="_blank" rel="noopener">
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
    <script src="<?= site_asset_url('assets/vendor/htmx/htmx.min.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
    <script src="<?= site_asset_url('assets/js/admin.js') ?>"></script>
</body>
</html>
