<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'    => $pageTitle ?? lang('Site.pageTitles.posts'),
    'pageSubtitle' => $content['banner_subtitle'] ?? '',
]) ?>

<section class="section-pad bg-white">
    <div class="container">
        <form action="<?= site_url('actualites') ?>" method="get" class="row justify-content-center mb-4">
            <?php if ($selectedType !== null): ?>
                <input type="hidden" name="type" value="<?= esc(site_post_category($selectedType), 'attr') ?>">
            <?php endif ?>
            <div class="col-md-6 col-lg-5">
                <label for="newsSearch" class="visually-hidden"><?= esc(lang('Site.posts.searchLabel')) ?></label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control border-start-0 ps-0" id="newsSearch" name="q" value="<?= esc($query ?? '', 'attr') ?>" placeholder="<?= esc(lang('Site.posts.searchPlaceholder'), 'attr') ?>">
                </div>
            </div>
            <div class="col-auto mt-3 mt-md-0">
                <button type="submit" class="btn btn-primary-green"><?= esc(lang('Site.common.search')) ?></button>
            </div>
        </form>

        <div class="d-flex flex-wrap justify-content-center gap-2 mb-5">
            <a class="btn btn-outline-green<?= $selectedType === null ? ' active' : '' ?>" href="<?= site_url('actualites' . ($query !== '' ? '?q=' . urlencode($query) : '')) ?>" data-filter="tous"><?= esc(lang('Site.posts.all')) ?></a>
            <a class="btn btn-outline-green<?= $selectedType === 'news' ? ' active' : '' ?>" href="<?= site_url('actualites?type=actualite' . ($query !== '' ? '&q=' . urlencode($query) : '')) ?>" data-filter="actualite"><?= esc(lang('Site.posts.news')) ?></a>
            <a class="btn btn-outline-green<?= $selectedType === 'event' ? ' active' : '' ?>" href="<?= site_url('actualites?type=evenement' . ($query !== '' ? '&q=' . urlencode($query) : '')) ?>" data-filter="evenement"><?= esc(lang('Site.posts.events')) ?></a>
        </div>

        <div class="row g-4">
            <?php foreach ($posts as $post): ?>
                <div class="col-lg-6">
                    <article class="news-card h-100 d-flex flex-column flex-md-row" data-category="<?= esc(site_post_category($post->type), 'attr') ?>">
                        <?php if ($post->cover_image): ?>
                            <img src="<?= esc(site_media_url($post->cover_image), 'attr') ?>" class="news-card-thumb" alt="<?= esc($post->title, 'attr') ?>">
                        <?php endif ?>
                        <div class="news-card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                                <span class="badge-news badge-<?= esc(site_post_category($post->type), 'attr') ?>"><?= esc(site_post_type_label($post->type)) ?></span>
                                <span class="small text-muted"><?= esc(site_format_date($post->published_at)) ?></span>
                            </div>
                            <h2 class="h6 fw-bold"><?= esc($post->title) ?></h2>
                            <p class="small text-muted"><?= esc($post->excerpt) ?></p>
                            <?php if ($post->type === 'event' && $post->event_starts_at): ?>
                                <p class="small text-muted mb-2"><i class="bi bi-calendar-event me-1"></i><?= esc(site_format_date($post->event_starts_at, true)) ?></p>
                            <?php endif ?>
                            <a href="<?= site_url('actualites/' . $post->slug) ?>" class="small fw-semibold text-success"><?= esc(lang('Home.readMore')) ?></a>
                        </div>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
        <p id="newsNoResults" class="text-center text-muted mt-4 <?= $posts === [] ? '' : 'd-none' ?>"><?= esc(lang('Site.posts.noResults')) ?></p>
        <?= $pager->links('posts', 'site_full') ?>
    </div>
</section>
<?= $this->endSection() ?>
