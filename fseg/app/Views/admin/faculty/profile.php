<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$dean = isset($content['dean']) && is_array($content['dean']) ? $content['dean'] : [];
$englishDean = isset($translations['dean']) && is_array($translations['dean']) ? $translations['dean'] : [];

$paragraphText = static function (array $source): string {
    $paragraphs = $source['paragraphs'] ?? [];

    return is_array($paragraphs)
        ? implode("\n\n", array_map(static fn (mixed $paragraph): string => (string) $paragraph, $paragraphs))
        : '';
};

$fieldValue = static function (array $source, string $field, string $inputName, string $default = ''): string {
    $old = old($inputName);

    return $old !== null ? (string) $old : (string) ($source[$field] ?? $default);
};

$deanMessage = old('dean_message');
$deanMessage = $deanMessage !== null ? (string) $deanMessage : $paragraphText($dean);
$englishMessage = old('translation_en_dean_message');
$englishMessage = $englishMessage !== null ? (string) $englishMessage : $paragraphText($englishDean);
$photo = (string) ($dean['photo'] ?? '');
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label">Faculté</span>
        <h1 class="h3 mb-1">Présentation / Mot du doyen</h1>
        <p class="text-muted mb-0">
            Site modifié : <strong><?= esc($activeSite->name ?? 'Site courant') ?></strong>
        </p>
    </div>
    <a href="<?= site_url('faculte') ?>" class="btn btn-outline-green" target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Voir la page Faculté
    </a>
</div>

<form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data" class="card-faculte">
    <?= csrf_field() ?>

    <?php if (isset($errors['form'])): ?>
        <div class="alert alert-danger"><?= esc($errors['form']) ?></div>
    <?php endif ?>

    <section class="admin-form-section" aria-labelledby="dean-identity-title">
        <div class="admin-form-section-title">
            <h2 id="dean-identity-title">Identité</h2>
        </div>

        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <label for="dean_photo" class="form-label fw-semibold">Photo du doyen</label>
                <?php if ($photo !== ''): ?>
                    <div class="mb-3">
                        <img src="<?= esc(site_media_url($photo), 'attr') ?>" alt="Photo actuelle du doyen" class="rounded border bg-white" style="width: 180px; height: 180px; object-fit: cover;">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="remove_dean_photo" name="remove_dean_photo">
                        <label class="form-check-label" for="remove_dean_photo">Retirer la photo actuelle</label>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-3">Aucune photo n’est enregistrée pour ce site.</p>
                <?php endif ?>
                <input type="file" class="form-control<?= isset($errors['dean_photo']) ? ' is-invalid' : '' ?>" id="dean_photo" name="dean_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                <p class="form-text">JPG, PNG ou WebP, 2 Mo maximum. La photo est commune aux versions française et anglaise.</p>
                <?php if (isset($errors['dean_photo'])): ?>
                    <div class="invalid-feedback d-block"><?= esc($errors['dean_photo']) ?></div>
                <?php endif ?>
            </div>

            <div class="col-12 col-lg-8">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="dean_name" class="form-label fw-semibold">Nom complet *</label>
                        <input type="text" class="form-control<?= isset($errors['dean_name']) ? ' is-invalid' : '' ?>" id="dean_name" name="dean_name" value="<?= esc($fieldValue($dean, 'name', 'dean_name'), 'attr') ?>" maxlength="255" required>
                        <?php if (isset($errors['dean_name'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['dean_name']) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="col-md-6">
                        <label for="dean_signature" class="form-label fw-semibold">Signature</label>
                        <input type="text" class="form-control<?= isset($errors['dean_signature']) ? ' is-invalid' : '' ?>" id="dean_signature" name="dean_signature" value="<?= esc($fieldValue($dean, 'signature', 'dean_signature'), 'attr') ?>" maxlength="255">
                        <?php if (isset($errors['dean_signature'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['dean_signature']) ?></div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-form-section" aria-labelledby="dean-content-title">
        <div class="admin-form-section-title">
            <div>
                <span class="section-label">Contenu</span>
                <h2 id="dean-content-title">Versions française et anglaise</h2>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" id="deanLanguageTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="dean-fr-tab" data-bs-toggle="tab" data-bs-target="#dean-fr" type="button" role="tab" aria-controls="dean-fr" aria-selected="true">Français</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="dean-en-tab" data-bs-toggle="tab" data-bs-target="#dean-en" type="button" role="tab" aria-controls="dean-en" aria-selected="false">English</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="dean-fr" role="tabpanel" aria-labelledby="dean-fr-tab" tabindex="0">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="dean_label" class="form-label fw-semibold">Petit libellé *</label>
                        <input type="text" class="form-control<?= isset($errors['dean_label']) ? ' is-invalid' : '' ?>" id="dean_label" name="dean_label" value="<?= esc($fieldValue($dean, 'label', 'dean_label', lang('Site.faculty.deanLabelFallback')), 'attr') ?>" maxlength="120" required>
                        <?php if (isset($errors['dean_label'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['dean_label']) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="col-md-6">
                        <label for="dean_title" class="form-label fw-semibold">Titre principal *</label>
                        <input type="text" class="form-control<?= isset($errors['dean_title']) ? ' is-invalid' : '' ?>" id="dean_title" name="dean_title" value="<?= esc($fieldValue($dean, 'title', 'dean_title'), 'attr') ?>" maxlength="255" required>
                        <?php if (isset($errors['dean_title'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['dean_title']) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="col-md-6">
                        <label for="dean_role" class="form-label fw-semibold">Fonction *</label>
                        <input type="text" class="form-control<?= isset($errors['dean_role']) ? ' is-invalid' : '' ?>" id="dean_role" name="dean_role" value="<?= esc($fieldValue($dean, 'role', 'dean_role'), 'attr') ?>" maxlength="255" required>
                        <?php if (isset($errors['dean_role'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['dean_role']) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="col-md-6">
                        <label for="dean_specialty" class="form-label fw-semibold">Domaine / département</label>
                        <input type="text" class="form-control<?= isset($errors['dean_specialty']) ? ' is-invalid' : '' ?>" id="dean_specialty" name="dean_specialty" value="<?= esc($fieldValue($dean, 'specialty', 'dean_specialty'), 'attr') ?>" maxlength="255">
                        <?php if (isset($errors['dean_specialty'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['dean_specialty']) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="col-12">
                        <label for="dean_message" class="form-label fw-semibold">Message du doyen *</label>
                        <textarea class="form-control<?= isset($errors['dean_message']) ? ' is-invalid' : '' ?>" id="dean_message" name="dean_message" rows="9" required><?= esc($deanMessage) ?></textarea>
                        <p class="form-text">Séparez les paragraphes par une ligne vide.</p>
                        <?php if (isset($errors['dean_message'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['dean_message']) ?></div>
                        <?php endif ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="dean-en" role="tabpanel" aria-labelledby="dean-en-tab" tabindex="0">
                <p class="text-muted small">Les champs anglais sont facultatifs. Un champ vide utilise la version française sur le site public.</p>

                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="translation_en_dean_label" class="form-label fw-semibold">Petit libellé en anglais</label>
                        <input type="text" class="form-control<?= isset($errors['translation_en_dean_label']) ? ' is-invalid' : '' ?>" id="translation_en_dean_label" name="translation_en_dean_label" value="<?= esc($fieldValue($englishDean, 'label', 'translation_en_dean_label'), 'attr') ?>" maxlength="120">
                        <?php if (isset($errors['translation_en_dean_label'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['translation_en_dean_label']) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="col-md-6">
                        <label for="translation_en_dean_title" class="form-label fw-semibold">Titre principal en anglais</label>
                        <input type="text" class="form-control<?= isset($errors['translation_en_dean_title']) ? ' is-invalid' : '' ?>" id="translation_en_dean_title" name="translation_en_dean_title" value="<?= esc($fieldValue($englishDean, 'title', 'translation_en_dean_title'), 'attr') ?>" maxlength="255">
                        <?php if (isset($errors['translation_en_dean_title'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['translation_en_dean_title']) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="col-md-6">
                        <label for="translation_en_dean_role" class="form-label fw-semibold">Fonction en anglais</label>
                        <input type="text" class="form-control<?= isset($errors['translation_en_dean_role']) ? ' is-invalid' : '' ?>" id="translation_en_dean_role" name="translation_en_dean_role" value="<?= esc($fieldValue($englishDean, 'role', 'translation_en_dean_role'), 'attr') ?>" maxlength="255">
                        <?php if (isset($errors['translation_en_dean_role'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['translation_en_dean_role']) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="col-md-6">
                        <label for="translation_en_dean_specialty" class="form-label fw-semibold">Domaine / département en anglais</label>
                        <input type="text" class="form-control<?= isset($errors['translation_en_dean_specialty']) ? ' is-invalid' : '' ?>" id="translation_en_dean_specialty" name="translation_en_dean_specialty" value="<?= esc($fieldValue($englishDean, 'specialty', 'translation_en_dean_specialty'), 'attr') ?>" maxlength="255">
                        <?php if (isset($errors['translation_en_dean_specialty'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['translation_en_dean_specialty']) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="col-md-6">
                        <label for="translation_en_dean_signature" class="form-label fw-semibold">Signature en anglais</label>
                        <input type="text" class="form-control<?= isset($errors['translation_en_dean_signature']) ? ' is-invalid' : '' ?>" id="translation_en_dean_signature" name="translation_en_dean_signature" value="<?= esc($fieldValue($englishDean, 'signature', 'translation_en_dean_signature'), 'attr') ?>" maxlength="255">
                        <?php if (isset($errors['translation_en_dean_signature'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['translation_en_dean_signature']) ?></div>
                        <?php endif ?>
                    </div>

                    <div class="col-12">
                        <label for="translation_en_dean_message" class="form-label fw-semibold">Message du doyen en anglais</label>
                        <textarea class="form-control<?= isset($errors['translation_en_dean_message']) ? ' is-invalid' : '' ?>" id="translation_en_dean_message" name="translation_en_dean_message" rows="9"><?= esc($englishMessage) ?></textarea>
                        <p class="form-text">Séparez les paragraphes par une ligne vide. Laissez vide pour utiliser le message français.</p>
                        <?php if (isset($errors['translation_en_dean_message'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['translation_en_dean_message']) ?></div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
        <button type="submit" class="btn btn-primary-green">
            <i class="bi bi-check2 me-1" aria-hidden="true"></i>Enregistrer
        </button>
        <a href="<?= site_url('admin') ?>" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
<?= $this->endSection() ?>
