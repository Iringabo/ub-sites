<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'    => $pageTitle ?? $post->title,
    'pageSubtitle' => site_post_type_label($post->type) . ' · ' . site_format_date($post->published_at),
    'breadcrumbs'  => [
        ['label' => lang('Site.common.home'), 'url' => site_url('/')],
        ['label' => lang('Site.pageTitles.posts'), 'url' => site_url('actualites')],
        ['label' => $post->title],
    ],
]) ?>

<section class="section-pad bg-white">
    <div class="container">
        <article class="row justify-content-center">
            <div class="col-lg-9">
                <?php $cover = site_person_media($post->cover_image ?? null); ?>
                <?php if ($cover !== null): ?>
                    <img src="<?= esc(site_media_url($cover), 'attr') ?>" alt="<?= esc($post->title, 'attr') ?>" class="rounded mb-4 w-100" style="max-height: 420px; object-fit: cover;">
                <?php endif ?>

                <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                    <span class="badge-news badge-<?= esc(site_post_category($post->type), 'attr') ?>"><?= esc(site_post_type_label($post->type)) ?></span>
                    <span class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?= esc(site_format_date($post->published_at)) ?></span>
                </div>

                <?php if ($post->type === 'event'): ?>
                    <div class="card-faculte mb-4">
                        <h2 class="h5"><?= esc(lang('Site.posts.eventInfo')) ?></h2>
                        <?php if ($post->event_starts_at): ?>
                            <p class="mb-2"><strong><?= esc(lang('Site.posts.start')) ?> :</strong> <?= esc(site_format_date($post->event_starts_at, true)) ?></p>
                        <?php endif ?>
                        <?php if ($post->event_ends_at): ?>
                            <p class="mb-2"><strong><?= esc(lang('Site.posts.end')) ?> :</strong> <?= esc(site_format_date($post->event_ends_at, true)) ?></p>
                        <?php endif ?>
                        <?php if ($post->event_location): ?>
                            <p class="mb-2"><strong><?= esc(lang('Site.posts.location')) ?> :</strong> <?= esc($post->event_location) ?></p>
                        <?php endif ?>
                        <?php if ($post->registration_url): ?>
                            <a href="<?= esc($post->registration_url, 'attr') ?>" class="btn btn-outline-green btn-sm"><?= esc(lang('Site.posts.register')) ?></a>
                        <?php endif ?>
                    </div>
                <?php endif ?>

                <?php foreach (site_paragraphs($post->body) as $paragraph): ?>
                    <p><?= esc($paragraph) ?></p>
                <?php endforeach ?>
            </div>
        </article>
    </div>
</section>
<?= $this->endSection() ?>
