<?php
$cover = site_person_media($post->cover_image ?? null);
$isEvent = $post->type === 'event';
$postDate = $isEvent ? ($post->event_starts_at ?? $post->published_at) : $post->published_at;
$dateParts = $isEvent ? site_date_parts($postDate) : null;
$author = trim((string) ($post->author_name ?? ''));
?>
<article class="news-card rail-card h-100">
    <?php if ($cover !== null): ?>
        <img src="<?= esc(site_media_url($cover), 'attr') ?>" class="news-card-cover" alt="<?= esc($post->title, 'attr') ?>" loading="lazy">
    <?php endif ?>
    <div class="news-card-body">
        <div class="news-card-meta">
            <?php if ($dateParts !== null): ?>
                <time class="date-block" datetime="<?= esc($dateParts['iso'], 'attr') ?>">
                    <span class="date-block-day"><?= esc($dateParts['day']) ?></span>
                    <span class="date-block-month"><?= esc($dateParts['month']) ?></span>
                </time>
            <?php else: ?>
                <span class="small text-muted"><?= esc(site_format_date($postDate, false)) ?></span>
            <?php endif ?>
            <span class="badge-news badge-<?= esc(site_post_category($post->type), 'attr') ?>"><?= esc(site_post_type_label($post->type)) ?></span>
        </div>
        <h3 class="h6 fw-bold"><?= esc($post->title) ?></h3>
        <?php if ($author !== ''): ?>
            <p class="news-card-author"><i class="bi bi-person" aria-hidden="true"></i><?= esc(lang('Site.posts.by', [$author])) ?></p>
        <?php endif ?>
        <p class="small text-muted rail-card-excerpt"><?= esc($post->excerpt) ?></p>
        <a href="<?= site_url('actualites/' . $post->slug) ?>" class="stretched-link small fw-semibold text-brand text-decoration-none"><?= esc(lang('Home.readMore')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
</article>
