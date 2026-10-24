<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$selectedGroups = old('groups');
$selectedPermissions = old('permissions');
$userEmail = old('email');

if (! is_array($selectedGroups)) {
    $selectedGroups = $user->getGroups() ?? [];
}

if (! is_array($selectedPermissions)) {
    $selectedPermissions = $user->getPermissions() ?? [];
}

$userEmail ??= $isNew ? '' : ($user->getEmail() ?? '');
$isCurrentUser = (int) ($user->id ?? 0) === (int) (auth()->id() ?? 0);
$currentActorIsSuperAdmin = (bool) ($currentActorIsSuperAdmin ?? false);
$sites = $sites ?? [];
$selectedSiteRoles = $selectedSiteRoles ?? [];
$siteRoleOptions = $siteRoleOptions ?? ['site_admin' => 'Administrateur de cette faculté', 'editor' => 'Éditeur de cette faculté'];
$postedSiteIds = old('site_ids');

if (is_array($postedSiteIds)) {
    $selectedSiteIds = $postedSiteIds;
} elseif (! is_array($selectedSiteIds ?? null)) {
    $selectedSiteIds = [];
}

$selectedSiteIds = array_map('intval', $selectedSiteIds);
$postedSiteRoles = old('site_roles');
if (is_array($postedSiteRoles)) {
    $selectedSiteRoles = $postedSiteRoles;
}

$roleCards = [
    'admin' => [
        'icon'  => 'bi-shield-check',
        'title' => 'Administrateur de faculté',
        'text'  => 'Gère les comptes, les paramètres et tous les contenus des facultés cochées.',
    ],
    'editor' => [
        'icon'  => 'bi-pencil-square',
        'title' => 'Éditeur de faculté',
        'text'  => 'Rédige et met à jour les pages, actualités et listes des facultés cochées.',
    ],
];
$selectedRole = in_array('admin', $selectedGroups, true) ? 'admin' : 'editor';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label"><?= $isNew ? 'Création' : 'Modification' ?></span>
        <h1 class="h3 mb-1"><?= $isNew ? 'Créer un compte' : esc($user->username ?? 'Utilisateur') ?></h1>
        <p class="text-muted mb-0">
            <?php if ($isNew): ?>
                <?= $currentActorIsSuperAdmin
                    ? 'Créez un administrateur ou un éditeur, puis cochez les facultés auxquelles il aura accès.'
                    : 'Créez un administrateur ou un éditeur pour cette faculté.' ?>
            <?php else: ?>
                Modifiez le compte, son rôle et ses facultés.
            <?php endif ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if (! $isNew && $passwordAction !== null): ?>
            <a href="<?= esc($passwordAction, 'attr') ?>" class="btn btn-outline-green">Réinitialiser le mot de passe</a>
        <?php endif ?>
        <a href="<?= site_url('admin/users') ?>" class="btn btn-outline-secondary">Retour</a>
    </div>
</div>

<form action="<?= esc($action, 'attr') ?>" method="post" class="card-faculte" data-unsaved-guard>
    <?= csrf_field() ?>

    <h2 class="h6 text-uppercase text-muted mb-3">Identité et connexion</h2>
    <div class="row g-4">
        <div class="col-lg-6">
            <label for="username" class="form-label fw-semibold">Nom d’utilisateur</label>
            <input type="text" class="form-control<?= isset($errors['username']) ? ' is-invalid' : '' ?>" id="username" name="username" value="<?= esc(old('username', $user->username ?? ''), 'attr') ?>" maxlength="30" required<?= isset($errors['username']) ? ' aria-describedby="username-error"' : '' ?>>
            <?php if (isset($errors['username'])): ?>
                <div class="invalid-feedback" id="username-error"><?= esc($errors['username']) ?></div>
            <?php endif ?>
        </div>
        <div class="col-lg-6">
            <label for="email" class="form-label fw-semibold">Adresse électronique</label>
            <input type="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" value="<?= esc($userEmail, 'attr') ?>" maxlength="254" required<?= isset($errors['email']) ? ' aria-describedby="email-error"' : '' ?>>
            <?php if (isset($errors['email'])): ?>
                <div class="invalid-feedback" id="email-error"><?= esc($errors['email']) ?></div>
            <?php endif ?>
        </div>
        <div class="col-lg-6">
            <label for="password" class="form-label fw-semibold"><?= $isNew ? 'Mot de passe' : 'Nouveau mot de passe' ?></label>
            <input type="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" maxlength="255" autocomplete="new-password" <?= $isNew ? 'required' : '' ?> aria-describedby="password-help<?= isset($errors['password']) ? ' password-error' : '' ?>">
            <div class="form-text" id="password-help">Au moins 12 caractères. Le mot de passe n’est jamais affiché ni journalisé.</div>
            <?php if (isset($errors['password'])): ?>
                <div class="invalid-feedback" id="password-error"><?= esc($errors['password']) ?></div>
            <?php endif ?>
        </div>
        <div class="col-lg-6">
            <label for="confirm" class="form-label fw-semibold">Confirmation du mot de passe</label>
            <input type="password" class="form-control<?= isset($errors['confirm']) ? ' is-invalid' : '' ?>" id="confirm" name="confirm" maxlength="255" autocomplete="new-password" <?= $isNew ? 'required' : '' ?><?= isset($errors['confirm']) ? ' aria-describedby="confirm-error"' : '' ?>>
            <?php if (isset($errors['confirm'])): ?>
                <div class="invalid-feedback" id="confirm-error"><?= esc($errors['confirm']) ?></div>
            <?php endif ?>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="active" value="0">
                <input class="form-check-input<?= isset($errors['active']) ? ' is-invalid' : '' ?>" type="checkbox" role="switch" value="1" id="active" name="active" <?= old('active', ! empty($user->active) ? '1' : '0') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="active">Compte actif</label>
                <?php if (isset($errors['active'])): ?>
                    <div class="invalid-feedback d-block" id="active-error"><?= esc($errors['active']) ?></div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <?php if ($currentActorIsSuperAdmin): ?>
        <h2 class="h6 text-uppercase text-muted mt-4 pt-3 border-top mb-3">Rôle</h2>
        <?php if (isset($errors['groups'])): ?>
            <div class="text-danger small mb-2" id="groups-error"><?= esc($errors['groups']) ?></div>
        <?php endif ?>
        <div class="row g-3 user-role-cards" role="radiogroup" aria-label="Rôle du compte"<?= isset($errors['groups']) ? ' aria-describedby="groups-error"' : '' ?>>
            <?php foreach ($roleCards as $roleKey => $card): ?>
                <div class="col-md-6">
                    <input class="btn-check" type="radio" name="groups[]" id="group_<?= esc($roleKey, 'attr') ?>" value="<?= esc($roleKey, 'attr') ?>" <?= $selectedRole === $roleKey ? 'checked' : '' ?>>
                    <label class="user-role-card" for="group_<?= esc($roleKey, 'attr') ?>">
                        <span class="user-role-card-icon" aria-hidden="true"><i class="bi <?= esc($card['icon'], 'attr') ?>"></i></span>
                        <span>
                            <span class="d-block fw-semibold"><?= esc($card['title']) ?></span>
                            <span class="d-block small text-muted"><?= esc($card['text']) ?></span>
                        </span>
                    </label>
                </div>
            <?php endforeach ?>
        </div>

        <h2 class="h6 text-uppercase text-muted mt-4 pt-3 border-top mb-1">Facultés</h2>
        <p class="small text-muted mb-3">Le rôle choisi s’applique à chaque faculté cochée.</p>
        <?php if (isset($errors['site_ids'])): ?>
            <div class="text-danger small mb-2" id="site-ids-error"><?= esc($errors['site_ids']) ?></div>
        <?php endif ?>
        <div class="row g-2">
            <?php foreach ($sites as $site): ?>
                <?php $checked = in_array((int) $site->id, $selectedSiteIds, true); ?>
                <div class="col-sm-6 col-xl-4">
                    <div class="form-check user-site-option">
                        <input class="form-check-input" type="checkbox" value="<?= esc((string) $site->id, 'attr') ?>" id="site_<?= esc((string) $site->id, 'attr') ?>" name="site_ids[]" <?= $checked ? 'checked' : '' ?>>
                        <label class="form-check-label" for="site_<?= esc((string) $site->id, 'attr') ?>">
                            <span class="fw-semibold d-block"><?= esc($site->name) ?></span>
                            <span class="text-muted small"><?= esc(strtoupper((string) $site->slug)) ?></span>
                        </label>
                    </div>
                </div>
            <?php endforeach ?>
        </div>

        <?php if ($permissions !== []): ?>
            <details class="mt-4 pt-3 border-top"<?= $selectedPermissions !== [] || isset($errors['permissions']) ? ' open' : '' ?>>
                <summary class="fw-semibold">Permissions supplémentaires (facultatif)</summary>
                <p class="small text-muted mt-2">À n’utiliser que pour un besoin précis : le rôle couvre déjà les accès habituels.</p>
                <?php if (isset($errors['permissions'])): ?>
                    <div class="text-danger small mb-2" id="permissions-error"><?= esc($errors['permissions']) ?></div>
                <?php endif ?>
                <div class="row g-2">
                    <?php foreach ($permissions as $key => $label): ?>
                        <?php $checked = in_array($key, $selectedPermissions, true); ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="<?= esc($key, 'attr') ?>" id="permission_<?= esc($key, 'attr') ?>" name="permissions[]" <?= $checked ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="permission_<?= esc($key, 'attr') ?>"><?= esc($label) ?></label>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </details>
        <?php endif ?>

        <div class="alert alert-light border mt-4 mb-0 small">
            <i class="bi bi-terminal me-1" aria-hidden="true"></i>
            Les comptes superadministrateur ne se créent pas ici : utilisez
            <code>php spark admin:create-superadmin</code> dans le dossier <code>superadmin</code>.
        </div>
    <?php else: ?>
        <fieldset class="border rounded-3 p-3 mt-4">
            <legend class="float-none w-auto px-2 small fw-semibold">Rôle sur cette faculté</legend>
            <?php if (isset($errors['site_ids'])): ?>
                <div class="text-danger small mb-2" id="site-ids-error"><?= esc($errors['site_ids']) ?></div>
            <?php endif ?>
            <?php if (isset($errors['groups'])): ?>
                <div class="text-danger small mb-2" id="groups-error"><?= esc($errors['groups']) ?></div>
            <?php endif ?>
            <?php $onlySite = $sites[0] ?? null; ?>
            <?php if ($onlySite !== null): ?>
                <?php
                $onlySiteId = (string) $onlySite->id;
                $selectedSiteRole = (string) ($selectedSiteRoles[(int) $onlySite->id] ?? 'editor');
                $roleDescriptions = [
                    'site_admin' => 'Gère les comptes, les paramètres et tous les contenus de cette faculté.',
                    'editor'     => 'Rédige et met à jour les contenus (pages, actualités, listes).',
                ];
                ?>
                <input type="hidden" name="site_ids[]" value="<?= esc($onlySiteId, 'attr') ?>">
                <p class="mb-3">Ce compte n’aura accès qu’à <strong><?= esc($onlySite->name) ?></strong>.</p>
                <div class="row g-2">
                    <?php foreach ($siteRoleOptions as $roleKey => $roleLabel): ?>
                        <div class="col-md-6">
                            <div class="form-check border rounded-3 p-3 h-100">
                                <input class="form-check-input ms-0 me-2" type="radio" name="site_roles[<?= esc($onlySiteId, 'attr') ?>]" id="site_role_<?= esc($roleKey, 'attr') ?>" value="<?= esc($roleKey, 'attr') ?>" <?= $selectedSiteRole === $roleKey ? 'checked' : '' ?>>
                                <label class="form-check-label" for="site_role_<?= esc($roleKey, 'attr') ?>">
                                    <span class="fw-semibold"><?= esc($roleLabel) ?></span>
                                    <span class="text-muted small d-block"><?= esc($roleDescriptions[$roleKey] ?? '') ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </fieldset>
    <?php endif ?>

    <?php if ($isCurrentUser): ?>
        <div class="alert alert-info mt-4 mb-0">
            Vous modifiez votre propre compte. La désactivation et la perte d’accès administrateur sont protégées.
        </div>
    <?php endif ?>

    <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
        <button type="submit" class="btn btn-primary-green">Enregistrer</button>
        <a href="<?= site_url('admin/users') ?>" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
<?= $this->endSection() ?>
