<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'    => lang('Site.common.search'),
    'pageSubtitle' => $query !== '' ? $query : lang('Site.common.searchPrompt'),
]) ?>

<section class="section-pad bg-white">
    <div class="container">
        <form action="<?= site_url('trouver') ?>" method="get" class="row justify-content-center mb-5" role="search">
            <div class="col-md-8 col-lg-6">
                <label for="siteSearchPage" class="visually-hidden"><?= esc(lang('Site.common.search'), 'attr') ?></label>
                <div class="input-group">
                    <input type="search" class="form-control" id="siteSearchPage" name="q" value="<?= esc($query, 'attr') ?>" placeholder="<?= esc(lang('Site.common.searchPrompt'), 'attr') ?>">
                    <button type="submit" class="btn btn-primary-green"><?= esc(lang('Site.common.search')) ?></button>
                </div>
            </div>
        </form>

        <?php if ($query === ''): ?>
            <p class="text-center text-muted mb-0"><?= esc(lang('Site.common.searchEmpty')) ?></p>
        <?php elseif ($programmes === [] && $staff === [] && $posts === []): ?>
            <p class="text-center text-muted mb-0"><?= esc(lang('Site.common.searchNone')) ?></p>
        <?php else: ?>
            <?php if ($programmes !== []): ?>
                <h2 class="h4 mb-3"><?= esc(lang('Site.nav.programmes')) ?></h2>
                <ul class="list-unstyled mb-5">
                    <?php foreach ($programmes as $programme): ?>
                        <li class="mb-2"><a href="<?= site_url('formations/' . $programme->slug) ?>"><?= esc($programme->title) ?></a></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
            <?php if ($staff !== []): ?>
                <h2 class="h4 mb-3"><?= esc(lang('Site.nav.staff')) ?></h2>
                <ul class="list-unstyled mb-5">
                    <?php foreach ($staff as $member): ?>
                        <li class="mb-2"><a href="<?= site_url('corps-enseignant/' . $member->slug) ?>"><?= esc($member->name) ?></a></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
            <?php if ($posts !== []): ?>
                <h2 class="h4 mb-3"><?= esc(lang('Site.nav.posts')) ?></h2>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($posts as $post): ?>
                        <li class="mb-2"><a href="<?= site_url('actualites/' . $post->slug) ?>"><?= esc($post->title) ?></a></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        <?php endif ?>
    </div>
</section>
<?= $this->endSection() ?>
