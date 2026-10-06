<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?php
$author = trim((string) ($post->author_name ?? ''));
$attachment = trim((string) ($post->attachment_path ?? ''));
$hasAttachment = $attachment !== '' && ($attachmentSize ?? null) !== null;
?>
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
                    <img src="<?= esc(site_media_url($cover), 'attr') ?>" alt="<?= esc($post->title, 'attr') ?>" class="post-cover mb-4">
                <?php endif ?>

                <div class="post-meta mb-4">
                    <span class="badge-news badge-<?= esc(site_post_category($post->type), 'attr') ?>"><?= esc(site_post_type_label($post->type)) ?></span>
                    <span class="post-meta-item"><i class="bi bi-calendar3" aria-hidden="true"></i><?= esc(site_format_date($post->published_at)) ?></span>
                    <?php if ($author !== ''): ?>
                        <span class="post-meta-item post-author"><i class="bi bi-person" aria-hidden="true"></i><?= esc(lang('Site.posts.by', [$author])) ?></span>
                    <?php endif ?>
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
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($post->registration_url): ?>
                                <a href="<?= esc($post->registration_url, 'attr') ?>" class="btn btn-outline-green btn-sm"><?= esc(lang('Site.posts.register')) ?></a>
                            <?php endif ?>
                            <?php if (! empty($icsUrl)): ?>
                                <a href="<?= esc($icsUrl, 'attr') ?>" class="btn btn-outline-green btn-sm" download><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i><?= esc(lang('Site.posts.addToCalendar')) ?></a>
                            <?php endif ?>
                        </div>
                    </div>
                <?php endif ?>

                <div class="post-body">
                    <?php foreach (site_paragraphs($post->body) as $paragraph): ?>
                        <p><?= esc($paragraph) ?></p>
                    <?php endforeach ?>
                </div>

                <?php if ($hasAttachment): ?>
                    <aside class="attachment-card mt-4" aria-label="<?= esc(lang('Site.posts.attachmentTitle'), 'attr') ?>">
                        <span class="attachment-card-icon" aria-hidden="true"><i class="bi bi-file-earmark-pdf"></i></span>
                        <span class="attachment-card-text">
                            <span class="attachment-card-label"><?= esc(lang('Site.posts.attachmentTitle')) ?></span>
                            <span class="attachment-card-name"><?= esc($post->attachment_name ?: 'document.pdf') ?></span>
                            <span class="attachment-card-size"><?= esc(lang('Site.posts.attachmentSize', [max(1, (int) round($attachmentSize / 1024))])) ?></span>
                        </span>
                        <a href="<?= esc(site_media_url($attachment), 'attr') ?>" class="btn btn-primary-green" download><i class="bi bi-download me-1" aria-hidden="true"></i><?= esc(lang('Site.posts.download')) ?></a>
                    </aside>
                <?php endif ?>
            </div>
        </article>
    </div>
</section>
<?= $this->endSection() ?>
