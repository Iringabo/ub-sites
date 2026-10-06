<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'    => lang('Site.common.search'),
    'pageSubtitle' => $query !== '' ? $query : lang('Site.common.searchPrompt'),
]) ?>

<section class="section-pad bg-white">
    <div class="container">
        <form action="<?= site_url('trouver') ?>" method="get" class="row justify-content-center mb-4" role="search">
            <div class="col-md-8 col-lg-6">
                <label for="siteSearchPage" class="visually-hidden"><?= esc(lang('Site.common.search'), 'attr') ?></label>
                <div class="input-group">
                    <input type="search" class="form-control" id="siteSearchPage" name="q" value="<?= esc($query, 'attr') ?>" placeholder="<?= esc(lang('Site.common.searchPrompt'), 'attr') ?>">
                    <button type="submit" class="btn btn-primary-green"><?= esc(lang('Site.common.search')) ?></button>
                </div>
            </div>
        </form>

        <?php if ($query === ''): ?>
            <p class="empty-state"><i class="bi bi-search" aria-hidden="true"></i><?= esc(lang('Site.common.searchEmpty')) ?></p>
        <?php elseif ($groups === []): ?>
            <p class="empty-state" role="status"><i class="bi bi-emoji-neutral" aria-hidden="true"></i><?= esc(lang('Site.common.searchNone')) ?></p>
        <?php else: ?>
            <p class="text-center text-muted mb-4" role="status"><?= esc(lang('Site.search.resultCount', [$total])) ?></p>
            <nav class="filter-bar justify-content-center mb-5" aria-label="<?= esc(lang('Site.search.jumpTo'), 'attr') ?>">
                <?php foreach ($groups as $key => $group): ?>
                    <a class="filter-chip" href="#resultats-<?= esc($key, 'attr') ?>"><?= esc($group['label']) ?> <span class="tab-count" aria-hidden="true"><?= (int) $group['total'] ?></span></a>
                <?php endforeach ?>
            </nav>
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <?php foreach ($groups as $key => $group): ?>
                        <section id="resultats-<?= esc($key, 'attr') ?>" class="search-group" aria-labelledby="resultats-<?= esc($key, 'attr') ?>-title">
                            <h2 class="h5 search-group-title" id="resultats-<?= esc($key, 'attr') ?>-title">
                                <i class="bi <?= esc($group['icon'], 'attr') ?> me-2 text-brand" aria-hidden="true"></i><?= esc($group['label']) ?>
                                <span class="tab-count"><?= (int) $group['total'] ?></span>
                            </h2>
                            <ul class="search-results list-unstyled">
                                <?php foreach ($group['items'] as $item): ?>
                                    <li>
                                        <a href="<?= esc($item['url'], 'attr') ?>" class="search-result-title"><?= esc($item['title']) ?></a>
                                        <?php if ($item['snippet'] !== ''): ?>
                                            <p class="small text-muted mb-0"><?= esc($item['snippet']) ?></p>
                                        <?php endif ?>
                                    </li>
                                <?php endforeach ?>
                            </ul>
                            <?php if ($group['more'] !== null && $group['total'] > count($group['items'])): ?>
                                <a href="<?= esc($group['more'], 'attr') ?>" class="small fw-semibold text-brand"><?= esc(lang('Site.search.seeAll', [$group['total']])) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                            <?php endif ?>
                        </section>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</section>
<?= $this->endSection() ?>
