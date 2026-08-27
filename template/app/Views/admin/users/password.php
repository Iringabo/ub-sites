<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label">Sécurité</span>
        <h1 class="h3 mb-1">Réinitialiser le mot de passe</h1>
        <p class="text-muted mb-0"><?= esc($user->username ?? 'Utilisateur') ?> · <?= esc($user->getEmail() ?? '—') ?></p>
    </div>
    <a href="<?= site_url('admin/users/' . $user->id . '/edit') ?>" class="btn btn-outline-secondary">Retour</a>
</div>

<form action="<?= esc($action, 'attr') ?>" method="post" class="card-faculte">
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <label for="password" class="form-label fw-semibold">Nouveau mot de passe</label>
            <input type="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" maxlength="255" required aria-describedby="password-help password-error">
            <div class="form-text" id="password-help">Le mot de passe n’est jamais affiché ni journalisé.</div>
            <?php if (isset($errors['password'])): ?>
                <div class="invalid-feedback" id="password-error"><?= esc($errors['password']) ?></div>
            <?php endif ?>
        </div>
        <div class="col-lg-6">
            <label for="confirm" class="form-label fw-semibold">Confirmation</label>
            <input type="password" class="form-control<?= isset($errors['confirm']) ? ' is-invalid' : '' ?>" id="confirm" name="confirm" maxlength="255" required aria-describedby="confirm-error">
            <?php if (isset($errors['confirm'])): ?>
                <div class="invalid-feedback" id="confirm-error"><?= esc($errors['confirm']) ?></div>
            <?php endif ?>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
        <button type="submit" class="btn btn-primary-green">Réinitialiser</button>
        <a href="<?= site_url('admin/users/' . $user->id . '/edit') ?>" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
<?= $this->endSection() ?>
