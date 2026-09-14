<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label">Contenus éditoriaux</span>
        <h1 class="h3 mb-1">Actualités et événements</h1>
        <p class="text-muted mb-0">Gérez les brouillons, publications, contenus programmés et archives.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (in_array('news', $allowedTypes, true)): ?>
            <a href="<?= site_url('admin/posts/new?type=news') ?>" class="btn btn-primary-green">
                <i class="bi bi-plus-lg me-1"></i>Nouvelle actualité
            </a>
        <?php endif ?>
        <?php if (in_array('event', $allowedTypes, true)): ?>
            <a href="<?= site_url('admin/posts/new?type=event') ?>" class="btn btn-outline-green">
                <i class="bi bi-calendar-plus me-1"></i>Nouvel événement
            </a>
        <?php endif ?>
    </div>
</div>

<form class="card-faculte mb-4" method="get" action="<?= site_url('admin/posts') ?>">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="q" class="form-label small fw-semibold">Recherche</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Titre, résumé ou contenu">
        </div>
        <div class="col-md-3">
            <label for="type" class="form-label small fw-semibold">Type</label>
            <select class="form-select" id="type" name="type">
                <option value="">Tous les types</option>
                <?php foreach ($types as $value => $label): ?>
                    <?php if (in_array($value, $allowedTypes, true)): ?>
                        <option value="<?= esc($value, 'attr') ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endif ?>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="status" class="form-label small fw-semibold">Statut</label>
            <select class="form-select" id="status" name="status">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= esc($value, 'attr') ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
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
                    <th>Titre</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Publication</th>
                    <th>Accueil</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= esc($post->title) ?></div>
                            <div class="small text-muted"><?= esc($post->slug) ?></div>
                        </td>
                        <td><?= esc(site_post_type_label($post->type)) ?></td>
                        <td><span class="badge text-bg-light"><?= esc(site_post_status_label($post->status)) ?></span></td>
                        <td class="small text-muted"><?= esc(site_format_date($post->published_at, true) ?: 'Non définie') ?></td>
                        <td>
                            <?php if ($post->featured): ?>
                                <span class="badge text-bg-success">Mis en avant<?= $post->home_order ? ' #' . esc((string) $post->home_order) : '' ?></span>
                            <?php else: ?>
                                <span class="small text-muted">Non</span>
                            <?php endif ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                <a href="<?= site_url('admin/posts/' . $post->id . '/preview') ?>" class="btn btn-outline-green btn-sm">Aperçu</a>
                                <a href="<?= site_url('admin/posts/' . $post->id . '/edit') ?>" class="btn btn-primary-green btn-sm">Modifier</a>
                                <?php if ($post->status !== 'published'): ?>
                                    <form method="post" action="<?= site_url('admin/posts/' . $post->id . '/publish') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-green btn-sm">Publier</button>
                                    </form>
                                <?php endif ?>
                                <?php if ($post->status !== 'archived'): ?>
                                    <form method="post" action="<?= site_url('admin/posts/' . $post->id . '/archive') ?>" data-confirm="Voulez-vous vraiment archiver ce contenu ?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Archiver</button>
                                    </form>
                                <?php endif ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($posts === []): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Aucun contenu ne correspond aux filtres.</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<?= $pager->links('admin_posts', 'site_full') ?>
<?= $this->endSection() ?>
