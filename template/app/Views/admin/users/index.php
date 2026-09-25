<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$currentUser = $currentUser ?? auth()->user();
$currentActorIsSuperAdmin = (bool) ($currentActorIsSuperAdmin ?? false);
$groupTitles = array_map(static fn (array $group): string => $group['title'], $groups);
$siteLabelsByUserId = $siteLabelsByUserId ?? [];
$editableUserIds = array_map('intval', $editableUserIds ?? []);
$filterSites = $filterSites ?? [];
$filters = $filters ?? ['q' => '', 'status' => '', 'site_id' => ''];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label">Comptes & accès</span>
        <h1 class="h3 mb-1">Comptes & accès</h1>
        <p class="text-muted mb-0">Ajoutez ou modifiez les personnes qui administrent ou rédigent le site.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= site_url('admin/users/new') ?>" class="btn btn-primary-green"><i class="bi bi-person-plus me-1"></i>Créer un compte</a>
        <a href="<?= site_url('admin') ?>" class="btn btn-outline-secondary">Tableau de bord</a>
    </div>
</div>

<form class="card-faculte mb-4" method="get" action="<?= site_url('admin/users') ?>">
    <div class="row g-3 align-items-end">
        <div class="<?= $currentActorIsSuperAdmin && $filterSites !== [] ? 'col-md-5' : 'col-md-8' ?>">
            <label for="q" class="form-label small fw-semibold">Recherche</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Nom d’utilisateur ou adresse électronique">
        </div>
        <?php if ($currentActorIsSuperAdmin && $filterSites !== []): ?>
            <div class="col-md-3">
                <label for="site_id" class="form-label small fw-semibold">Faculté</label>
                <select class="form-select" id="site_id" name="site_id">
                    <option value="">Toutes</option>
                    <?php foreach ($filterSites as $site): ?>
                        <option value="<?= esc((string) $site->id, 'attr') ?>" <?= (string) $filters['site_id'] === (string) $site->id ? 'selected' : '' ?>>
                            <?= esc($site->name) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
        <?php endif ?>
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
        <table class="table align-middle mb-0 admin-users-table">
            <thead class="table-light">
                <tr>
                    <th>Personne</th>
                    <th>État</th>
                    <th>Rôle</th>
                    <th>Faculté</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $userGroups = $user->getGroups() ?? []; ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-break"><?= esc($user->username ?? '—') ?></div>
                            <div class="small text-muted text-break"><?= esc($user->getEmail() ?? '—') ?></div>
                        </td>
                        <td>
                            <span class="badge <?= ! empty($user->active) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= ! empty($user->active) ? 'Actif' : 'Inactif' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($userGroups !== []): ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($userGroups as $group): ?>
                                        <span class="badge text-bg-light"><?= esc($groupTitles[$group] ?? $group) ?></span>
                                    <?php endforeach ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">Aucun rôle</span>
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
                                <span class="text-muted small">Toutes</span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif ?>
                        </td>
                        <td class="text-end">
                            <?php
                            $canEditUser = in_array((int) $user->id, $editableUserIds, true);
                            $isProtectedSuperAdmin = in_array('superadmin', $userGroups, true) && ! $currentActorIsSuperAdmin;
                            $actions = [];
                            if (! $canEditUser || $isProtectedSuperAdmin) {
                                echo '<span class="text-muted small">Hors périmètre de modification</span>';
                            } else {
                                $actions[] = [
                                    'type'  => 'link',
                                    'href'  => site_url('admin/users/' . $user->id . '/edit'),
                                    'icon'  => 'pencil',
                                    'label' => 'Modifier',
                                    'class' => 'primary',
                                ];
                                $actions[] = [
                                    'type'  => 'link',
                                    'href'  => site_url('admin/users/' . $user->id . '/password'),
                                    'icon'  => 'key',
                                    'label' => 'Mot de passe',
                                    'class' => 'outline',
                                ];
                                if ((int) $user->id !== (int) ($currentUser?->id ?? 0)) {
                                    if (! empty($user->active)) {
                                        $actions[] = [
                                            'type'    => 'form',
                                            'action'  => site_url('admin/users/' . $user->id . '/deactivate'),
                                            'icon'    => 'person-x',
                                            'label'   => 'Désactiver',
                                            'class'   => 'danger',
                                            'confirm' => 'Voulez-vous vraiment désactiver ce compte ?',
                                        ];
                                    } else {
                                        $actions[] = [
                                            'type'    => 'form',
                                            'action'  => site_url('admin/users/' . $user->id . '/activate'),
                                            'icon'    => 'person-check',
                                            'label'   => 'Activer',
                                            'class'   => 'success',
                                            'confirm' => 'Voulez-vous vraiment activer ce compte ?',
                                        ];
                                    }
                                }
                                if ($currentActorIsSuperAdmin && (int) $user->id !== (int) ($currentUser?->id ?? 0)) {
                                    $actions[] = [
                                        'type'    => 'form',
                                        'action'  => site_url('admin/users/' . $user->id . '/delete'),
                                        'icon'    => 'trash',
                                        'label'   => 'Supprimer',
                                        'class'   => 'danger',
                                        'confirm' => 'Voulez-vous vraiment supprimer définitivement ce compte ? Cette action est irréversible.',
                                    ];
                                }
                                echo view('admin/partials/row_actions', ['actions' => $actions]);
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($users === []): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Aucun compte ne correspond aux filtres. <a href="<?= site_url('admin/users/new') ?>">Créer le premier compte</a>.</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<?= $pager->links('admin_users', 'site_full') ?>
<?= $this->endSection() ?>
