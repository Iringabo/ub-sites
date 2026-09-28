<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$valueFor = static function (string $inputName, string $stored): string {
    $old = old($inputName);

    return $old !== null ? (string) $old : $stored;
};
$fieldCol = static fn (array $field): string => in_array($field['type'] ?? 'text', ['textarea', 'paragraphs', 'image', 'embed'], true) ? 'col-12' : 'col-md-6';
$rows = static fn (array $field): int => ($field['type'] ?? '') === 'paragraphs' ? 8 : 4;
?>

<nav aria-label="Fil d’Ariane" class="mb-2">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><span class="text-muted">Pages du site</span></li>
        <li class="breadcrumb-item"><?= esc($pageMeta['label']) ?></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($segment['label']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= esc($segment['label']) ?></h1>
        <?php if (! empty($segment['description'])): ?>
            <p class="text-muted mb-0"><?= esc($segment['description']) ?></p>
        <?php endif ?>
    </div>
    <a href="<?= esc($publicUrl, 'attr') ?>" class="btn btn-outline-green" target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Voir sur le site
    </a>
</div>

<?php if (! empty($segment['note'])): ?>
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
        <div>
            <?= esc($segment['note']['text']) ?>
            <?php if (! empty($segment['note']['link']) && (auth()->user()?->can('settings.manage') ?? false)): ?>
                <a href="<?= site_url('admin/' . $segment['note']['link']) ?>" class="alert-link ms-1">Y aller</a>
            <?php endif ?>
        </div>
    </div>
<?php endif ?>

<form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data" class="card-faculte" data-unsaved-guard>
    <?= csrf_field() ?>

    <?php if (isset($errors['form'])): ?>
        <div class="alert alert-danger"><?= esc($errors['form']) ?></div>
    <?php endif ?>

    <?php if ($hasEnglish): ?>
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="segment-fr-tab" data-bs-toggle="tab" data-bs-target="#segment-fr" type="button" role="tab" aria-controls="segment-fr" aria-selected="true">Français</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="segment-en-tab" data-bs-toggle="tab" data-bs-target="#segment-en" type="button" role="tab" aria-controls="segment-en" aria-selected="false">
                    English
                    <?php if (array_filter(array_keys($errors), static fn (string $key): bool => str_starts_with($key, 'en_')) !== []): ?>
                        <i class="bi bi-exclamation-circle text-danger ms-1" aria-label="Erreurs"></i>
                    <?php endif ?>
                </button>
            </li>
        </ul>
    <?php endif ?>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="segment-fr" role="tabpanel" aria-labelledby="segment-fr-tab" tabindex="0">
            <div class="row g-4">
                <?php foreach ($fields as $field): ?>
                    <?php
                    $input = $field['input'];
                    $type = $field['type'] ?? 'text';
                    $errorKey = $type === 'image' ? 'file_' . $input : $input;
                    $hasError = isset($errors[$errorKey]);
                    $helpId = $input . '-help';
                    ?>
                    <div class="<?= $fieldCol($field) ?>">
                        <?php if ($type === 'image'): ?>
                            <span class="form-label fw-semibold d-block"><?= esc($field['label']) ?></span>
                            <div class="d-flex flex-wrap align-items-start gap-3">
                                <?php if ($field['value'] !== ''): ?>
                                    <img src="<?= esc(site_media_url($field['value']), 'attr') ?>" alt="Image actuelle" class="rounded border" style="max-width: 280px; max-height: 160px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded border bg-light text-muted small d-flex align-items-center justify-content-center" style="width: 280px; height: 120px;">Aucune image</div>
                                <?php endif ?>
                                <div class="flex-grow-1" style="min-width: 240px;">
                                    <label for="file_<?= esc($input, 'attr') ?>" class="form-label small fw-semibold"><?= $field['value'] !== '' ? 'Remplacer l’image' : 'Choisir une image' ?></label>
                                    <input type="file" class="form-control<?= $hasError ? ' is-invalid' : '' ?>" id="file_<?= esc($input, 'attr') ?>" name="file_<?= esc($input, 'attr') ?>" accept="image/jpeg,image/png,image/webp" aria-describedby="<?= esc($helpId, 'attr') ?>">
                                    <?php if (! empty($field['help'])): ?>
                                        <div class="form-text" id="<?= esc($helpId, 'attr') ?>"><?= esc($field['help']) ?></div>
                                    <?php endif ?>
                                    <?php if ($field['value'] !== ''): ?>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" value="1" id="remove_<?= esc($input, 'attr') ?>" name="remove_<?= esc($input, 'attr') ?>">
                                            <label class="form-check-label" for="remove_<?= esc($input, 'attr') ?>">Retirer l’image</label>
                                        </div>
                                    <?php endif ?>
                                    <?php if ($hasError): ?>
                                        <div class="invalid-feedback d-block"><?= esc($errors[$errorKey]) ?></div>
                                    <?php endif ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <label for="<?= esc($input, 'attr') ?>" class="form-label fw-semibold">
                                <?= esc($field['label']) ?><?= ! empty($field['required']) ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?>
                            </label>
                            <?php $value = $valueFor($input, $field['value']); ?>
                            <?php if ($type === 'textarea' || $type === 'paragraphs'): ?>
                                <textarea class="form-control<?= $hasError ? ' is-invalid' : '' ?>" id="<?= esc($input, 'attr') ?>" name="<?= esc($input, 'attr') ?>" rows="<?= $rows($field) ?>"<?= ! empty($field['required']) ? ' required' : '' ?> aria-describedby="<?= esc($helpId, 'attr') ?>"><?= esc($value) ?></textarea>
                            <?php elseif ($type === 'icon'): ?>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi <?= esc($value !== '' ? $value : 'bi-star', 'attr') ?>" data-icon-preview="<?= esc($input, 'attr') ?>" aria-hidden="true"></i></span>
                                    <input type="text" class="form-control<?= $hasError ? ' is-invalid' : '' ?>" id="<?= esc($input, 'attr') ?>" name="<?= esc($input, 'attr') ?>" value="<?= esc($value, 'attr') ?>" list="segment-icon-choices" data-icon-input aria-describedby="<?= esc($helpId, 'attr') ?>">
                                </div>
                            <?php else: ?>
                                <input type="text" class="form-control<?= $hasError ? ' is-invalid' : '' ?>" id="<?= esc($input, 'attr') ?>" name="<?= esc($input, 'attr') ?>" value="<?= esc($value, 'attr') ?>"<?= ! empty($field['required']) ? ' required' : '' ?> aria-describedby="<?= esc($helpId, 'attr') ?>"<?= $type === 'url' ? ' placeholder="/contact ou https://…"' : '' ?>>
                            <?php endif ?>
                            <div class="form-text" id="<?= esc($helpId, 'attr') ?>">
                                <?php if (! empty($field['help'])): ?>
                                    <?= esc($field['help']) ?>
                                <?php elseif ($type === 'paragraphs'): ?>
                                    Laissez une ligne vide entre deux paragraphes.
                                <?php elseif ($type === 'icon'): ?>
                                    Choisissez une icône dans la liste (aperçu à gauche).
                                <?php elseif ($type === 'url'): ?>
                                    Page du site (ex. /contact) ou adresse complète.
                                <?php endif ?>
                            </div>
                            <?php if ($hasError): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors[$errorKey]) ?></div>
                            <?php endif ?>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        </div>

        <?php if ($hasEnglish): ?>
            <div class="tab-pane fade" id="segment-en" role="tabpanel" aria-labelledby="segment-en-tab" tabindex="0">
                <p class="text-muted small">Facultatif. Si un champ reste vide, le texte français est affiché sur la version anglaise. Les images, liens et icônes sont communs aux deux langues.</p>
                <div class="row g-4">
                    <?php foreach ($fields as $field): ?>
                        <?php if (! $field['translatable']) {
                            continue;
                        } ?>
                        <?php
                        $input = 'en_' . $field['input'];
                        $type = $field['type'] ?? 'text';
                        $hasError = isset($errors[$input]);
                        $value = $valueFor($input, $field['en_value']);
                        $placeholder = mb_strimwidth(str_replace("\n", ' ', $field['value']), 0, 120, '…');
                        ?>
                        <div class="<?= $fieldCol($field) ?>">
                            <label for="<?= esc($input, 'attr') ?>" class="form-label fw-semibold"><?= esc($field['label']) ?> (EN)</label>
                            <?php if ($type === 'textarea' || $type === 'paragraphs'): ?>
                                <textarea class="form-control<?= $hasError ? ' is-invalid' : '' ?>" id="<?= esc($input, 'attr') ?>" name="<?= esc($input, 'attr') ?>" rows="<?= $rows($field) ?>" placeholder="<?= esc($placeholder, 'attr') ?>"><?= esc($value) ?></textarea>
                            <?php else: ?>
                                <input type="text" class="form-control<?= $hasError ? ' is-invalid' : '' ?>" id="<?= esc($input, 'attr') ?>" name="<?= esc($input, 'attr') ?>" value="<?= esc($value, 'attr') ?>" placeholder="<?= esc($placeholder, 'attr') ?>">
                            <?php endif ?>
                            <?php if ($hasError): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors[$input]) ?></div>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>

    <datalist id="segment-icon-choices">
        <?php foreach ($iconChoices as $icon): ?>
            <option value="<?= esc($icon, 'attr') ?>"></option>
        <?php endforeach ?>
    </datalist>

    <div class="d-flex flex-wrap align-items-center gap-2 mt-4 pt-3 border-top">
        <button type="submit" class="btn btn-primary-green">
            <i class="bi bi-check2 me-1" aria-hidden="true"></i>Enregistrer
        </button>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <?php if ($previous !== null): ?>
                <a href="<?= esc($previous['url'], 'attr') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc($previous['label']) ?>
                </a>
            <?php endif ?>
            <?php if ($next !== null): ?>
                <a href="<?= esc($next['url'], 'attr') ?>" class="btn btn-outline-secondary btn-sm">
                    <?= esc($next['label']) ?><i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                </a>
            <?php endif ?>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
