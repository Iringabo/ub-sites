<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label">Réglages</span>
        <h1 class="h3 mb-1">Coordonnées & identité</h1>
        <p class="text-muted mb-0">Modifiez en une seule page les informations affichées sur le site public.</p>
    </div>
    <a href="<?= site_url('admin') ?>" class="btn btn-outline-secondary">Tableau de bord</a>
</div>

<?php
$errors = session('errors') ?? [];
$inputKey = static fn (string $key): string => 'setting_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $key);
$oldValue = static function (string $key, ?string $current) use ($errors, $inputKey): string {
    $old = old($inputKey($key));

    return $old !== null ? (string) $old : (string) ($current ?? '');
};
?>

<form method="post" action="<?= site_url('admin/settings/global') ?>" enctype="multipart/form-data" class="card-faculte">
    <?= csrf_field() ?>

    <?php foreach ($groups as $group): ?>
        <section class="admin-form-section" aria-labelledby="settings-section-<?= esc($group['context'], 'attr') ?>">
            <div class="admin-form-section-title">
                <h2 id="settings-section-<?= esc($group['context'], 'attr') ?>"><?= esc($group['title']) ?></h2>
            </div>

            <div class="row g-4">
                <?php foreach ($group['fields'] as $field): ?>
                    <?php
                    $key = $field['key'];
                    $name = $inputKey($key);
                    $id = 'field_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $key);
                    $value = $oldValue($key, $field['value']);
                    $type = $field['type'];
                    ?>
                    <div class="<?= in_array($type, ['text', 'path'], true) ? 'col-12' : 'col-md-6' ?>">
                        <label for="<?= esc($id, 'attr') ?>" class="form-label fw-semibold"><?= esc($field['label']) ?></label>

                        <?php if ($type === 'path'): ?>
                            <?php if ($value !== ''): ?>
                                <div class="mb-2">
                                    <img src="<?= esc(site_media_url($value), 'attr') ?>" alt="<?= esc($field['label'], 'attr') ?>" class="rounded border" style="max-width: 220px; max-height: 120px; object-fit: contain; background: #fff;">
                                </div>
                            <?php endif ?>
                            <input type="file" class="form-control" name="setting_file_<?= esc(preg_replace('/[^a-zA-Z0-9_]+/', '_', $key), 'attr') ?>" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <p class="form-text">JPG, PNG ou WebP, 2 Mo maximum.</p>
                        <?php elseif ($key === 'home.hero_overlay_opacity'): ?>
                            <div class="d-flex align-items-center gap-3">
                                <input type="range" class="form-range flex-grow-1" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" min="0.45" max="0.95" step="0.01" value="<?= esc($value !== '' ? $value : '0.88', 'attr') ?>" data-range-output="<?= esc($id . '_output', 'attr') ?>">
                                <output id="<?= esc($id . '_output', 'attr') ?>" class="fw-semibold"></output>
                            </div>
                        <?php elseif ($type === 'text'): ?>
                            <textarea class="form-control<?= isset($errors[$key]) ? ' is-invalid' : '' ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" rows="4"><?= esc($value) ?></textarea>
                        <?php elseif ($type === 'email'): ?>
                            <input type="email" class="form-control<?= isset($errors[$key]) ? ' is-invalid' : '' ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>">
                        <?php elseif ($type === 'color'): ?>
                            <input type="color" class="form-control form-control-color<?= isset($errors[$key]) ? ' is-invalid' : '' ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= esc($value !== '' ? $value : '#0D9B49', 'attr') ?>">
                        <?php else: ?>
                            <input type="text" class="form-control<?= isset($errors[$key]) ? ' is-invalid' : '' ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>">
                        <?php endif ?>

                        <?php if (isset($errors[$key])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors[$key]) ?></div>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        </section>
    <?php endforeach ?>

    <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
        <button type="submit" class="btn btn-primary-green">
            <i class="bi bi-check2 me-1" aria-hidden="true"></i>Enregistrer
        </button>
    </div>
</form>

<?= $this->endSection() ?>
