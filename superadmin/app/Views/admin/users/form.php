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
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label"><?= $isNew ? 'Création' : 'Modification' ?></span>
        <h1 class="h3 mb-1"><?= $isNew ? 'Créer un compte' : esc($user->username ?? 'Utilisateur') ?></h1>
        <p class="text-muted mb-0">
            <?php if ($isNew): ?>
                <?= $currentActorIsSuperAdmin
                    ? 'Attribuez un administrateur, un éditeur ou un superadministrateur, puis choisissez la faculté concernée.'
                    : 'Créez un administrateur ou un éditeur pour cette faculté.' ?>
            <?php else: ?>
                Modifiez le compte, son rôle et son accès à la faculté.
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
            <div class="form-text" id="password-help">Le mot de passe n’est jamais affiché ni journalisé.</div>
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
            <div class="form-check">
                <input type="hidden" name="active" value="0">
                <input class="form-check-input<?= isset($errors['active']) ? ' is-invalid' : '' ?>" type="checkbox" value="1" id="active" name="active" <?= old('active', ! empty($user->active) ? '1' : '0') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="active">Compte actif</label>
                <?php if (isset($errors['active'])): ?>
                    <div class="invalid-feedback d-block" id="active-error"><?= esc($errors['active']) ?></div>
                <?php endif ?>
            </div>
        </div>
        <?php if ($currentActorIsSuperAdmin): ?>
            <div class="col-lg-6">
                <fieldset class="border rounded-3 p-3 h-100">
                    <legend class="float-none w-auto px-2 small fw-semibold">Groupe plateforme</legend>
                    <?php if (isset($errors['groups'])): ?>
                        <div class="text-danger small mb-2" id="groups-error"><?= esc($errors['groups']) ?></div>
                    <?php endif ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($groups as $key => $group): ?>
                            <?php $checked = in_array($key, $selectedGroups, true); ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="<?= esc($key, 'attr') ?>" id="group_<?= esc($key, 'attr') ?>" name="groups[]" <?= $checked ? 'checked' : '' ?>>
                                <label class="form-check-label" for="group_<?= esc($key, 'attr') ?>">
                                    <span class="fw-semibold"><?= esc($group['title']) ?></span>
                                    <span class="text-muted small d-block"><?= esc($group['description']) ?></span>
                                </label>
                            </div>
                        <?php endforeach ?>
                    </div>
                </fieldset>
            </div>
            <div class="col-lg-6">
                <fieldset class="border rounded-3 p-3 h-100">
                    <legend class="float-none w-auto px-2 small fw-semibold">Permissions directes</legend>
                    <?php if (isset($errors['permissions'])): ?>
                        <div class="text-danger small mb-2" id="permissions-error"><?= esc($errors['permissions']) ?></div>
                    <?php endif ?>
                    <div class="d-grid gap-2">
                        <?php if ($permissions === []): ?>
                            <p class="text-muted small mb-0">Les permissions de ce compte sont déterminées par son groupe et son rôle facultaire.</p>
                        <?php else: ?>
                            <?php foreach ($permissions as $key => $label): ?>
                                <?php $checked = in_array($key, $selectedPermissions, true); ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="<?= esc($key, 'attr') ?>" id="permission_<?= esc($key, 'attr') ?>" name="permissions[]" <?= $checked ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="permission_<?= esc($key, 'attr') ?>"><?= esc($label) ?></label>
                                </div>
                            <?php endforeach ?>
                        <?php endif ?>
                    </div>
                </fieldset>
            </div>
        <?php endif ?>
        <div class="col-12">
            <fieldset class="border rounded-3 p-3">
                <legend class="float-none w-auto px-2 small fw-semibold">
                    <?= $currentActorIsSuperAdmin ? 'Faculté et rôle' : 'Rôle sur cette faculté' ?>
                </legend>
                <?php if (isset($errors['site_ids'])): ?>
                    <div class="text-danger small mb-2" id="site-ids-error"><?= esc($errors['site_ids']) ?></div>
                <?php endif ?>
                <?php if (isset($errors['groups']) && ! $currentActorIsSuperAdmin): ?>
                    <div class="text-danger small mb-2" id="groups-error"><?= esc($errors['groups']) ?></div>
                <?php endif ?>
                <?php if (! $currentActorIsSuperAdmin): ?>
                    <?php $onlySite = $sites[0] ?? null; ?>
                    <?php if ($onlySite !== null): ?>
                        <?php
                        $onlySiteId = (string) $onlySite->id;
                        $selectedRole = (string) ($selectedSiteRoles[(int) $onlySite->id] ?? 'editor');
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
                                        <input class="form-check-input ms-0 me-2" type="radio" name="site_roles[<?= esc($onlySiteId, 'attr') ?>]" id="site_role_<?= esc($roleKey, 'attr') ?>" value="<?= esc($roleKey, 'attr') ?>" <?= $selectedRole === $roleKey ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="site_role_<?= esc($roleKey, 'attr') ?>">
                                            <span class="fw-semibold"><?= esc($roleLabel) ?></span>
                                            <span class="text-muted small d-block"><?= esc($roleDescriptions[$roleKey] ?? '') ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($sites as $site): ?>
                        <?php $checked = in_array((int) $site->id, $selectedSiteIds, true); ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="form-check border rounded-3 p-3 h-100">
                                <input class="form-check-input ms-0 me-2" type="checkbox" value="<?= esc((string) $site->id, 'attr') ?>" id="site_<?= esc((string) $site->id, 'attr') ?>" name="site_ids[]" <?= $checked ? 'checked' : '' ?>>
                                <label class="form-check-label" for="site_<?= esc((string) $site->id, 'attr') ?>">
                                    <span class="fw-semibold"><?= esc($site->name) ?></span>
                                    <span class="text-muted small d-block"><?= esc($site->slug) ?></span>
                                </label>
                                <label for="site_role_<?= esc((string) $site->id, 'attr') ?>" class="form-label small fw-semibold mt-3">Rôle sur cette faculté</label>
                                <?php $selectedRole = (string) ($selectedSiteRoles[(int) $site->id] ?? 'editor'); ?>
                                <select class="form-select form-select-sm" id="site_role_<?= esc((string) $site->id, 'attr') ?>" name="site_roles[<?= esc((string) $site->id, 'attr') ?>]">
                                    <?php foreach ($siteRoleOptions as $roleKey => $roleLabel): ?>
                                        <option value="<?= esc($roleKey, 'attr') ?>" <?= $selectedRole === $roleKey ? 'selected' : '' ?>><?= esc($roleLabel) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
                <p class="form-text mb-0 mt-2">Cochez la faculté active par défaut. Un administrateur gère comptes et réglages ; un éditeur rédige les contenus.</p>
                <?php endif ?>
            </fieldset>
        </div>
    </div>

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
