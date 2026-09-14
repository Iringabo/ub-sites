<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label">Aperçu</span>
        <h1 class="h3 mb-1"><?= esc($post->title) ?></h1>
        <p class="text-muted mb-0">
            <?= esc(site_post_type_label($post->type)) ?> · <?= esc(site_post_status_label($post->status)) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/posts/' . $post->id . '/edit') ?>" class="btn btn-primary-green">Modifier</a>
        <a href="<?= site_url('admin/posts') ?>" class="btn btn-outline-secondary">Retour</a>
    </div>
</div>

<article class="card-faculte bg-white">
    <?php if ($post->cover_image): ?>
        <img src="<?= esc(site_media_url($post->cover_image), 'attr') ?>" alt="<?= esc($post->title, 'attr') ?>" class="rounded mb-4 w-100" style="max-height: 420px; object-fit: cover;">
    <?php endif ?>

    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <span class="badge-news badge-<?= esc(site_post_category($post->type), 'attr') ?>"><?= esc(site_post_type_label($post->type)) ?></span>
        <span class="badge text-bg-light"><?= esc(site_post_status_label($post->status)) ?></span>
        <?php if ($post->published_at): ?>
            <span class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?= esc(site_format_date($post->published_at, true)) ?></span>
        <?php endif ?>
    </div>

    <p class="lead"><?= esc($post->excerpt) ?></p>

    <?php if ($post->type === 'event'): ?>
        <div class="card-faculte mb-4">
            <h2 class="h5">Informations sur l’événement</h2>
            <?php if ($post->event_starts_at): ?>
                <p class="mb-2"><strong>Début :</strong> <?= esc(site_format_date($post->event_starts_at, true)) ?></p>
            <?php endif ?>
            <?php if ($post->event_ends_at): ?>
                <p class="mb-2"><strong>Fin :</strong> <?= esc(site_format_date($post->event_ends_at, true)) ?></p>
            <?php endif ?>
            <?php if ($post->event_location): ?>
                <p class="mb-2"><strong>Lieu :</strong> <?= esc($post->event_location) ?></p>
            <?php endif ?>
            <?php if ($post->registration_url): ?>
                <a href="<?= esc($post->registration_url, 'attr') ?>" class="btn btn-outline-green btn-sm">Lien d’inscription</a>
            <?php endif ?>
        </div>
    <?php endif ?>

    <?php foreach (site_paragraphs($post->body) as $paragraph): ?>
        <p><?= esc($paragraph) ?></p>
    <?php endforeach ?>
</article>
<?= $this->endSection() ?>
