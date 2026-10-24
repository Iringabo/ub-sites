<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$listFields = array_values(array_filter(
    $config['fields'],
    static fn (array $field): bool => ! empty($field['list']),
));
$previewField = $config['previewField'] ?? null;

$itemValue = static function (mixed $item, string $name): mixed {
    if (is_array($item)) {
        return $item[$name] ?? null;
    }

    return $item->{$name} ?? null;
};

$displayValue = static function (mixed $value, array $field, array $fieldOptions = []): string {
    if (($field['type'] ?? null) === 'boolean') {
        return ! empty($value) ? 'Oui' : 'Non';
    }

    if (in_array($field['type'] ?? null, ['select', 'readonly_select', 'relation', 'icon'], true)) {
        return $fieldOptions[(string) $value] ?? trim((string) $value);
    }

    if (is_array($value)) {
        return implode(', ', array_map(static fn (mixed $entry): string => (string) $entry, $value));
    }

    return trim((string) $value);
};

$canBulk = ! $trash
    && (($config['publishedField'] ?? null) !== null || ! ($config['singleton'] ?? false) && ! ($config['deletionDisabled'] ?? false));

$orderBy = $config['orderBy'] ?? null;
$orderable = ! $trash
    && empty($filters['q'])
    && $orderBy !== null
    && array_key_first($orderBy) === 'display_order';
$heroIndicatorSize = $heroIndicatorSize ?? '0.75';
$heroIndicatorOptions = $heroIndicatorOptions ?? [
    '0.75' => 'Petite (0,75 rem)',
    '1'    => 'Moyenne (1 rem)',
    '1.25' => 'Grande (1,25 rem)',
    '1.5'  => 'Très grande (1,5 rem)',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label"><?= $trash ? 'Corbeille' : 'Administration' ?></span>
        <h1 class="h3 mb-1"><?= esc($config['title']) ?></h1>
        <p class="text-muted mb-0">
            <?php if ($trash): ?>
                Restaurez un contenu archivé ou confirmez sa suppression définitive.
            <?php elseif ($orderable): ?>
                Créez et modifiez les contenus. Glissez les lignes pour changer l’ordre d’affichage — les numéros se mettent à jour automatiquement.
            <?php else: ?>
                Créez, modifiez et ordonnez les contenus affichés sur le site public.
            <?php endif ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (! ($config['creationDisabled'] ?? false) && (! ($config['singleton'] ?? false) || $items === [])): ?>
            <a href="<?= site_url('admin/' . $resource . '/new') ?>" class="btn btn-primary-green">
                <i class="bi bi-plus-lg me-1"></i>Créer
            </a>
        <?php endif ?>
        <?php if ($supportsTrash): ?>
            <?php if ($trash): ?>
                <a href="<?= site_url('admin/' . $resource) ?>" class="btn btn-outline-green">
                    <i class="bi bi-list-ul me-1" aria-hidden="true"></i>Contenus actifs
                </a>
            <?php else: ?>
                <a href="<?= site_url('admin/' . $resource . '?trash=1') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-archive me-1" aria-hidden="true"></i>Corbeille
                </a>
            <?php endif ?>
        <?php endif ?>
        <a href="<?= site_url('admin') ?>" class="btn btn-outline-secondary">Tableau de bord</a>
    </div>
</div>

<?php if ($resource === 'home-hero-slides' && ! $trash): ?>
    <form method="post" action="<?= site_url('admin/home-hero-slides/indicator-size') ?>" class="card-faculte mb-4">
        <?= csrf_field() ?>
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                <label for="heroIndicatorSize" class="form-label small fw-semibold">Taille des pastilles du carrousel</label>
                <select class="form-select" id="heroIndicatorSize" name="indicator_size" required>
                    <?php foreach ($heroIndicatorOptions as $value => $label): ?>
                        <option value="<?= esc((string) $value, 'attr') ?>" <?= (string) $heroIndicatorSize === (string) $value ? 'selected' : '' ?>>
                            <?= esc($label) ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <p class="form-text mb-0">Ces pastilles indiquent quelle carte du héros est affichée sur la page d’accueil.</p>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-green w-100">
                    <i class="bi bi-check2 me-1" aria-hidden="true"></i>Enregistrer la taille
                </button>
            </div>
        </div>
    </form>
<?php endif ?>

<form class="card-faculte mb-4" method="get" action="<?= site_url('admin/' . $resource) ?>">
    <?php if ($trash): ?>
        <input type="hidden" name="trash" value="1">
    <?php endif ?>
    <div class="row g-3 align-items-end">
        <div class="<?= isset($config['publishedField']) ? 'col-md-7' : 'col-md-10' ?>">
            <label for="q" class="form-label small fw-semibold">Recherche</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Rechercher dans ce module">
        </div>
        <?php if (isset($config['publishedField'])): ?>
            <div class="col-md-3">
                <label for="published" class="form-label small fw-semibold">Publication</label>
                <select class="form-select" id="published" name="published">
                    <option value="">Tous les états</option>
                    <option value="1" <?= $filters['published'] === '1' ? 'selected' : '' ?>>Publiés</option>
                    <option value="0" <?= $filters['published'] === '0' ? 'selected' : '' ?>>Masqués</option>
                </select>
            </div>
        <?php endif ?>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary-green">Filtrer</button>
        </div>
    </div>
</form>

<div class="card-faculte p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0 admin-resource-table">
            <thead class="table-light">
                <tr>
                    <?php if ($orderable): ?>
                        <th class="admin-drag-col"></th>
                    <?php endif ?>
                    <?php if ($canBulk): ?>
                        <th class="admin-bulk-col">
                            <input class="form-check-input" type="checkbox" id="adminBulkToggle" form="adminBulkForm" aria-label="Tout sélectionner">
                        </th>
                    <?php endif ?>
                    <?php foreach ($listFields as $field): ?>
                        <th><?= esc($field['label']) ?></th>
                    <?php endforeach ?>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody <?= $orderable ? 'data-sortable data-sortable-url="' . esc(site_url('admin/' . $resource . '/reorder'), 'attr') . '"' : '' ?>>
                <?php foreach ($items as $index => $item): ?>
                    <?php $itemId = $itemValue($item, 'id'); ?>
                    <tr class="admin-resource-row" data-id="<?= esc((string) $itemId, 'attr') ?>">
                        <?php if ($orderable): ?>
                            <td class="admin-drag-col">
                                <span class="admin-drag-handle" title="Glisser pour réordonner" aria-label="Réordonner">
                                    <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                                </span>
                            </td>
                        <?php endif ?>
                        <?php if ($canBulk): ?>
                            <td class="admin-bulk-col">
                                <input class="form-check-input admin-bulk-check" type="checkbox" form="adminBulkForm" name="ids[]" value="<?= esc((string) $itemId, 'attr') ?>" aria-label="Sélectionner <?= esc((string) ($index + 1), 'attr') ?>">
                            </td>
                        <?php endif ?>
                        <?php foreach ($listFields as $field): ?>
                            <?php $value = $itemValue($item, $field['name']); ?>
                            <td<?= ($field['name'] ?? '') === 'display_order' ? ' data-order-cell' : '' ?>>
                                <?php if (($field['type'] ?? null) === 'boolean'): ?>
                                    <span class="badge <?= ! empty($value) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= esc($displayValue($value, $field, $fieldOptions[$field['name']] ?? [])) ?>
                                    </span>
                                <?php elseif (($field['type'] ?? null) === 'icon' && trim((string) $value) !== ''): ?>
                                    <span class="admin-table-icon">
                                        <i class="bi <?= esc((string) $value, 'attr') ?>" aria-hidden="true"></i>
                                        <?= esc($displayValue($value, $field, $fieldOptions[$field['name']] ?? [])) ?>
                                    </span>
                                <?php else: ?>
                                    <?= esc(mb_strimwidth($displayValue($value, $field, $fieldOptions[$field['name']] ?? []), 0, 90, '…')) ?>
                                <?php endif ?>
                            </td>
                        <?php endforeach ?>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                <?php if ($trash): ?>
                                    <?= view('admin/partials/row_actions', ['actions' => [
                                        ['type' => 'form', 'action' => site_url('admin/' . $resource . '/' . $itemId . '/restore'), 'icon' => 'arrow-counterclockwise', 'label' => 'Restaurer', 'class' => 'outline'],
                                        ['type' => 'form', 'action' => site_url('admin/' . $resource . '/' . $itemId . '/purge'), 'icon' => 'trash', 'label' => 'Supprimer définitivement', 'class' => 'danger', 'confirm' => 'Supprimer définitivement ce contenu et ses médias téléversés ? Cette action ne pourra pas être annulée.'],
                                    ]]) ?>
                                <?php else: ?>
                                    <?php
                                    $rowActions = [];
                                    if ($previewField !== null && ! empty($itemValue($item, $previewField['name']))) {
                                        $rowActions[] = [
                                            'type' => 'button',
                                            'icon' => 'eye',
                                            'label' => 'Aperçu',
                                            'class' => 'secondary',
                                            'extraClass' => 'admin-preview-toggle',
                                            'attrs' => [
                                                'data-target' => 'preview-' . $itemId,
                                                'aria-expanded' => 'false',
                                            ],
                                        ];
                                    }
                                    $rowActions[] = [
                                        'type' => 'link',
                                        'href' => site_url('admin/' . $resource . '/' . $itemId . '/edit'),
                                        'icon' => 'pencil',
                                        'label' => 'Modifier',
                                        'class' => 'primary',
                                    ];
                                    if (! ($config['singleton'] ?? false) && ! ($config['creationDisabled'] ?? false)) {
                                        $rowActions[] = [
                                            'type' => 'form',
                                            'action' => site_url('admin/' . $resource . '/' . $itemId . '/duplicate'),
                                            'icon' => 'copy',
                                            'label' => 'Dupliquer',
                                            'class' => 'secondary',
                                            'confirm' => 'Dupliquer ce contenu ? Une copie (non publiée) sera créée.',
                                        ];
                                    }
                                    if (! ($config['singleton'] ?? false) && ! ($config['deletionDisabled'] ?? false)) {
                                        $rowActions[] = [
                                            'type' => 'form',
                                            'action' => site_url('admin/' . $resource . '/' . $itemId . '/delete'),
                                            'icon' => $supportsTrash ? 'archive' : 'trash',
                                            'label' => $supportsTrash ? 'Archiver' : 'Supprimer',
                                            'class' => 'danger',
                                            'confirm' => $supportsTrash ? 'Archiver ce contenu ? Il pourra être restauré depuis la corbeille.' : 'Voulez-vous vraiment supprimer ce contenu ? Cette action est irréversible.',
                                        ];
                                    }
                                    echo view('admin/partials/row_actions', ['actions' => $rowActions]);
                                    ?>
                                <?php endif ?>
                            </div>
                        </td>
                    </tr>
                    <?php if ($previewField !== null && ! empty($itemValue($item, $previewField['name']))): ?>
                        <tr class="admin-preview-row d-none" id="preview-<?= esc((string) $itemId, 'attr') ?>">
                            <td colspan="<?= count($listFields) + ($canBulk ? 1 : 0) + ($orderable ? 1 : 0) + 1 ?>">
                                <div class="p-3">
                                    <span class="small fw-semibold text-muted d-block mb-1"><?= esc($previewField['label']) ?></span>
                                    <div class="admin-preview-content"><?= nl2br(esc((string) $itemValue($item, $previewField['name']))) ?></div>
                                </div>
                            </td>
                        </tr>
                    <?php endif ?>
                <?php endforeach ?>
                <?php if ($items === []): ?>
                    <tr>
                        <td colspan="<?= count($listFields) + ($canBulk ? 1 : 0) + ($orderable ? 1 : 0) + 1 ?>" class="text-center text-muted py-4"><?= $trash ? 'La corbeille est vide.' : 'Aucun contenu ne correspond aux filtres.' ?></td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canBulk): ?>
    <form id="adminBulkForm" class="card-faculte mt-3 admin-bulk-bar" method="post" action="<?= site_url('admin/' . $resource . '/bulk') ?>" data-confirm-inline>
        <?= csrf_field() ?>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="small text-muted me-1"><span class="admin-bulk-count">0</span> sélectionné(s)</span>
            <select class="form-select form-select-sm w-auto" name="bulk_action" aria-label="Action groupée">
                <?php if (isset($config['publishedField'])): ?>
                    <option value="publish">Publier</option>
                    <option value="unpublish">Masquer</option>
                <?php endif ?>
                <?php if (! ($config['singleton'] ?? false) && ! ($config['deletionDisabled'] ?? false)): ?>
                    <option value="delete"><?= $supportsTrash ? 'Archiver' : 'Supprimer' ?></option>
                <?php endif ?>
            </select>
            <button type="submit" class="btn btn-outline-green btn-sm">Appliquer</button>
        </div>
    </form>
<?php endif ?>

<?php if ($orderable): ?>
    <form id="adminReorderForm" method="post" action="<?= esc(site_url('admin/' . $resource . '/reorder'), 'attr') ?>" class="d-none">
        <?= csrf_field() ?>
        <input type="hidden" name="ids" value="">
    </form>
<?php endif ?>

<?= $this->section('scripts') ?>
<?php if ($orderable): ?>
    <script src="<?= site_asset_url('assets/vendor/sortablejs/Sortable.min.js') ?>"></script>
<?php endif ?>
<?= $this->endSection() ?>

<?= $pager->links('admin_' . str_replace('-', '_', $resource), 'site_full') ?>
<?= $this->endSection() ?>
