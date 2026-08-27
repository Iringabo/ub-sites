<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$listFields = array_values(array_filter(
    $config['fields'],
    static fn (array $field): bool => ! empty($field['list']),
));

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
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label"><?= $trash ? 'Corbeille' : 'Administration' ?></span>
        <h1 class="h3 mb-1"><?= esc($config['title']) ?></h1>
        <p class="text-muted mb-0"><?= $trash ? 'Restaurez un contenu archivé ou confirmez sa suppression définitive.' : 'Créez, modifiez et ordonnez les contenus affichés sur le site public.' ?></p>
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
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <?php foreach ($listFields as $field): ?>
                        <th><?= esc($field['label']) ?></th>
                    <?php endforeach ?>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <?php foreach ($listFields as $field): ?>
                            <?php $value = $itemValue($item, $field['name']); ?>
                            <td>
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
                                    <form method="post" action="<?= site_url('admin/' . $resource . '/' . $itemValue($item, 'id') . '/restore') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-green btn-sm">Restaurer</button>
                                    </form>
                                    <form method="post" action="<?= site_url('admin/' . $resource . '/' . $itemValue($item, 'id') . '/purge') ?>" data-confirm="Supprimer définitivement ce contenu et ses médias téléversés ? Cette action ne pourra pas être annulée.">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer définitivement</button>
                                    </form>
                                <?php else: ?>
                                    <a href="<?= site_url('admin/' . $resource . '/' . $itemValue($item, 'id') . '/edit') ?>" class="btn btn-primary-green btn-sm">Modifier</a>
                                    <?php if (! ($config['singleton'] ?? false) && ! ($config['deletionDisabled'] ?? false)): ?>
                                        <form method="post" action="<?= site_url('admin/' . $resource . '/' . $itemValue($item, 'id') . '/delete') ?>" data-confirm="<?= $supportsTrash ? 'Archiver ce contenu ? Il pourra être restauré depuis la corbeille.' : 'Voulez-vous vraiment supprimer ce contenu ? Cette action est irréversible.' ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><?= $supportsTrash ? 'Archiver' : 'Supprimer' ?></button>
                                        </form>
                                    <?php endif ?>
                                <?php endif ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($items === []): ?>
                    <tr>
                        <td colspan="<?= count($listFields) + 1 ?>" class="text-center text-muted py-4"><?= $trash ? 'La corbeille est vide.' : 'Aucun contenu ne correspond aux filtres.' ?></td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<?= $pager->links('admin_' . str_replace('-', '_', $resource), 'site_full') ?>
<?= $this->endSection() ?>
