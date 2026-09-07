<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$currentUser = $currentUser ?? auth()->user();
$currentActorIsSuperAdmin = (bool) ($currentActorIsSuperAdmin ?? false);
$groupTitles = array_map(static fn (array $group): string => $group['title'], $groups);
$permissionTitles = $permissions;
$siteLabelsByUserId = $siteLabelsByUserId ?? [];
$editableUserIds = array_map('intval', $editableUserIds ?? []);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label">Sécurité</span>
        <h1 class="h3 mb-1">Utilisateurs</h1>
        <p class="text-muted mb-0">Gérez les comptes Shield, leurs groupes, leurs permissions et leurs accès.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= site_url('admin/users/new') ?>" class="btn btn-primary-green"><i class="bi bi-person-plus me-1"></i>Créer un utilisateur</a>
        <a href="<?= site_url('admin') ?>" class="btn btn-outline-secondary">Tableau de bord</a>
    </div>
</div>

<form class="card-faculte mb-4" method="get" action="<?= site_url('admin/users') ?>">
    <div class="row g-3 align-items-end">
        <div class="col-md-8">
            <label for="q" class="form-label small fw-semibold">Recherche</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Nom d’utilisateur ou adresse électronique">
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label small fw-semibold">État</label>
            <select class="form-select" id="status" name="status">
                <option value="">Tous</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Actifs</option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary-green">Filtrer</button>
        </div>
    </div>
</form>

<div class="card-faculte p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Compte</th>
                    <th>État</th>
                    <th>Groupes</th>
                    <th>Permissions</th>
                    <th>Sites</th>
                    <th>Dernière activité</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= esc($user->username ?? '—') ?></div>
                            <div class="small text-muted"><?= esc($user->getEmail() ?? '—') ?></div>
                        </td>
                        <td>
                            <span class="badge <?= ! empty($user->active) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= ! empty($user->active) ? 'Actif' : 'Inactif' ?>
                            </span>
                        </td>
                        <td>
                            <?php $userGroups = $user->getGroups() ?? []; ?>
                            <?php if ($userGroups !== []): ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($userGroups as $group): ?>
                                        <span class="badge text-bg-light"><?= esc($groupTitles[$group] ?? $group) ?></span>
                                    <?php endforeach ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">Aucun groupe</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php $userPermissions = $user->getPermissions() ?? []; ?>
                            <?php if ($userPermissions !== []): ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($userPermissions as $permission): ?>
                                        <span class="badge text-bg-light"><?= esc($permissionTitles[$permission] ?? $permission) ?></span>
                                    <?php endforeach ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">Aucune permission directe</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php $siteLabels = $siteLabelsByUserId[(int) $user->id] ?? []; ?>
                            <?php if ($siteLabels !== []): ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($siteLabels as $siteLabel): ?>
                                        <span class="badge text-bg-light"><?= esc($siteLabel) ?></span>
                                    <?php endforeach ?>
                                </div>
                            <?php elseif (in_array('superadmin', $userGroups, true)): ?>
                                <span class="text-muted small">Tous les sites</span>
                            <?php else: ?>
                                <span class="text-muted small">Site courant</span>
                            <?php endif ?>
                        </td>
                        <td class="small text-muted">
                            <?= esc(site_format_date($user->last_active ?? $user->updated_at ?? $user->created_at, true) ?: '—') ?>
                        </td>
                        <td class="text-end">
                            <?php
                            $canEditUser = in_array((int) $user->id, $editableUserIds, true);
                            $isProtectedSuperAdmin = in_array('superadmin', $userGroups, true) && ! $currentActorIsSuperAdmin;
                            ?>
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                <?php if (! $canEditUser || $isProtectedSuperAdmin): ?>
                                    <span class="text-muted small">Hors périmètre de modification</span>
                                <?php else: ?>
                                    <a href="<?= site_url('admin/users/' . $user->id . '/edit') ?>" class="btn btn-primary-green btn-sm">Modifier</a>
                                    <a href="<?= site_url('admin/users/' . $user->id . '/password') ?>" class="btn btn-outline-green btn-sm">Mot de passe</a>
                                <?php endif ?>
                                <?php if ($canEditUser && ! $isProtectedSuperAdmin && (int) $user->id !== (int) ($currentUser?->id ?? 0)): ?>
                                    <?php if (! empty($user->active)): ?>
                                        <form method="post" action="<?= site_url('admin/users/' . $user->id . '/deactivate') ?>" data-confirm="Voulez-vous vraiment désactiver ce compte ?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Désactiver</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= site_url('admin/users/' . $user->id . '/activate') ?>" data-confirm="Voulez-vous vraiment activer ce compte ?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-outline-success btn-sm">Activer</button>
                                        </form>
                                    <?php endif ?>
                                <?php endif ?>
                                <?php if ($currentActorIsSuperAdmin && (int) $user->id !== (int) ($currentUser?->id ?? 0)): ?>
                                    <form method="post" action="<?= site_url('admin/users/' . $user->id . '/delete') ?>" data-confirm="Voulez-vous vraiment supprimer définitivement ce compte ? Cette action est irréversible.">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer</button>
                                    </form>
                                <?php endif ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($users === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Aucun utilisateur ne correspond aux filtres.</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<?= $pager->links('admin_users', 'site_full') ?>
<?= $this->endSection() ?>
