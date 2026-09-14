<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$sites = $dashboard['sites'] ?? [];
$siteRoles = $dashboard['siteRoles'] ?? [];
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <span class="section-label">Superadministration</span>
        <h1 class="h3 mb-1">Université du Burundi</h1>
        <p class="text-muted mb-0">Pilotage des sites facultaires, des domaines et des accès.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= site_url('admin/users') ?>" class="btn btn-outline-green">
            <i class="bi bi-people me-1"></i>Comptes & accès
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <article class="card-faculte h-100">
            <div class="card-icon"><i class="bi bi-diagram-2"></i></div>
            <h2 class="h6">Sites facultaires</h2>
            <p class="display-6 fw-bold mb-1"><?= esc((string) ($dashboard['totalSites'] ?? 0)) ?></p>
            <p class="text-muted small mb-0"><?= esc((string) ($dashboard['activeSites'] ?? 0)) ?> actifs</p>
        </article>
    </div>
    <div class="col-md-6 col-xl-3">
        <article class="card-faculte h-100">
            <div class="card-icon"><i class="bi bi-people"></i></div>
            <h2 class="h6">Utilisateurs</h2>
            <p class="display-6 fw-bold mb-1"><?= esc((string) ($dashboard['totalUsers'] ?? 0)) ?></p>
        </article>
    </div>
    <div class="col-md-6 col-xl-3">
        <article class="card-faculte h-100">
            <div class="card-icon"><i class="bi bi-envelope-paper"></i></div>
            <h2 class="h6">Messages nouveaux</h2>
            <p class="display-6 fw-bold mb-1"><?= esc((string) ($dashboard['newMessages'] ?? 0)) ?></p>
        </article>
    </div>
    <div class="col-md-6 col-xl-3">
        <article class="card-faculte h-100">
            <div class="card-icon"><i class="bi bi-palette"></i></div>
            <h2 class="h6">Thèmes</h2>
            <p class="display-6 fw-bold mb-1"><?= esc((string) ($dashboard['themeCount'] ?? count($dashboard['themeKeys'] ?? []))) ?></p>
            <p class="text-muted small mb-0"><?= esc(implode(', ', $dashboard['themeKeys'] ?? [])) ?></p>
        </article>
    </div>
</div>

<div class="card-faculte p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Faculté</th>
                    <th>Domaines</th>
                    <th>État</th>
                    <th>Contenus publiés</th>
                    <th>Accès</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sites as $site): ?>
                    <?php
                    $hostnames = json_decode((string) ($site['hostnames'] ?? ''), true);
                    $hostnames = is_array($hostnames) ? $hostnames : [];
                    $roles = $siteRoles[(int) $site['id']] ?? [];
                    $counts = $dashboard['siteContentCounts'][(int) $site['id']] ?? [];
                    $firstHost = $hostnames[0] ?? null;
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= esc($site['name'] ?? '—') ?></div>
                            <div class="small text-muted"><?= esc($site['slug'] ?? '—') ?></div>
                        </td>
                        <td class="small">
                            <?= esc($hostnames === [] ? 'À configurer' : implode(', ', $hostnames)) ?>
                        </td>
                        <td>
                            <span class="badge <?= ($site['status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= ($site['status'] ?? '') === 'active' ? 'Actif' : 'Inactif' ?>
                            </span>
                        </td>
                        <td class="small">
                            <?= esc((string) ($counts['posts'] ?? 0)) ?> actualités ·
                            <?= esc((string) ($counts['programmes'] ?? 0)) ?> formations ·
                            <?= esc((string) ($counts['staff'] ?? 0)) ?> personnels
                        </td>
                        <td class="small text-muted">
                            Admins : <?= esc((string) ($roles['site_admin'] ?? 0)) ?> · Éditeurs : <?= esc((string) ($roles['editor'] ?? 0)) ?>
                        </td>
                        <td class="text-end">
                            <?php $preview = service('adminNavigation')->publicUrlForSite($site); ?>
                            <?php if ($preview !== null): ?>
                                <a href="<?= esc($preview, 'attr') ?>" class="btn btn-outline-green btn-sm" target="_blank" rel="noopener">Voir le site</a>
                            <?php endif ?>
                            <form method="post" action="<?= site_url('admin/site-selection') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="site_id" value="<?= esc((string) $site['id'], 'attr') ?>">
                                <button type="submit" class="btn btn-outline-green btn-sm">Gérer le contenu</button>
                            </form>
                            <a href="<?= site_url('admin/sites/' . $site['id'] . '/edit') ?>" class="btn btn-primary-green btn-sm">Configurer</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($sites === []): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Aucun site facultaire n’est configuré.</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Les comptes des administrateurs et éditeurs se gèrent depuis
    <a href="<?= site_url('admin/users') ?>">Comptes &amp; accès</a>.
    « Gérer le contenu » ouvre l’administration de cette faculté ici, sans quitter la superadministration.
</p>

<?= $this->endSection() ?>
