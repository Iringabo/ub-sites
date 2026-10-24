<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$labels = $labels ?? [];
$available = $available ?? [];
$enabled = $enabled ?? [];
$enabledSet = array_fill_keys($enabled, true);
// Show enabled first (in order), then disabled.
$orderedKeys = $enabled;
foreach ($available as $key) {
    if (! in_array($key, $orderedKeys, true)) {
        $orderedKeys[] = $key;
    }
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label">Accueil</span>
        <h1 class="h3 mb-1">Sections &amp; ordre</h1>
        <p class="text-muted mb-0">Choisissez ce qui apparaît sur la page d’accueil et glissez pour définir l’ordre d’affichage.</p>
    </div>
    <a href="<?= site_url('admin') ?>" class="btn btn-outline-secondary">Tableau de bord</a>
</div>

<form method="post" action="<?= site_url('admin/home-sections') ?>" id="homeSectionsForm" class="card-faculte">
    <?= csrf_field() ?>
    <input type="hidden" name="order" id="homeSectionsOrder" value="<?= esc(json_encode(array_values($enabled)), 'attr') ?>">

    <ul class="list-group list-group-flush home-sections-list" id="homeSectionsList" data-home-sections-sortable>
        <?php foreach ($orderedKeys as $key): ?>
            <li class="list-group-item d-flex align-items-center gap-3" data-section-key="<?= esc($key, 'attr') ?>">
                <span class="text-muted home-sections-handle" title="Glisser pour réordonner" role="button" tabindex="0">
                    <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                </span>
                <div class="form-check flex-grow-1 mb-0">
                    <input class="form-check-input" type="checkbox" name="sections[]" value="<?= esc($key, 'attr') ?>" id="section-<?= esc($key, 'attr') ?>" <?= isset($enabledSet[$key]) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="section-<?= esc($key, 'attr') ?>">
                        <?= esc($labels[$key] ?? $key) ?>
                    </label>
                </div>
                <span class="badge text-bg-light"><?= esc($key) ?></span>
            </li>
        <?php endforeach ?>
    </ul>

    <div class="p-3 border-top d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-primary-green">
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Enregistrer
        </button>
    </div>
</form>

<?= $this->section('scripts') ?>
<script src="<?= site_asset_url('assets/vendor/sortablejs/Sortable.min.js') ?>"></script>
<script>
(function () {
    const list = document.getElementById('homeSectionsList');
    const orderInput = document.getElementById('homeSectionsOrder');
    const form = document.getElementById('homeSectionsForm');
    if (!list || !orderInput || !form || typeof Sortable === 'undefined') return;

    const syncOrder = () => {
        const keys = Array.from(list.querySelectorAll('[data-section-key]')).map((el) => el.getAttribute('data-section-key'));
        orderInput.value = JSON.stringify(keys);
    };

    Sortable.create(list, {
        handle: '.home-sections-handle',
        animation: 150,
        onSort: syncOrder,
    });

    form.addEventListener('submit', syncOrder);
})();
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
