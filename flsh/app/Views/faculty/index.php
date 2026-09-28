<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'       => site_text_or_placeholder((string) ($content['banner_title'] ?? ''), $pageTitle ?? lang('Site.pageTitles.faculty')),
    'breadcrumbTitle' => $pageTitle ?? lang('Site.pageTitles.faculty'),
    'pageSubtitle'    => $content['banner_subtitle'] ?? '',
    'bannerImage'     => $content['banner_image'] ?? '',
]) ?>

<section id="mot-du-doyen" class="section-pad bg-white">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-3 text-center">
                <?php if (! empty($content['dean']['photo'])): ?>
                    <img src="<?= esc(site_media_url($content['dean']['photo']), 'attr') ?>" alt="<?= esc($content['dean']['name'] ?? lang('Site.faculty.deanPhotoAlt'), 'attr') ?>" class="dean-photo">
                <?php endif ?>
                <p class="fw-bold mb-0"><?= esc($content['dean']['name'] ?? '') ?></p>
                <p class="text-brand fw-semibold small mb-0"><?= esc($content['dean']['role'] ?? '') ?></p>
                <p class="text-muted small"><?= esc($content['dean']['specialty'] ?? '') ?></p>
            </div>
            <div class="col-lg-9">
                <span class="section-label"><?= esc($content['dean']['label'] ?? lang('Site.faculty.deanLabelFallback')) ?></span>
                <h2 class="section-title"><?= esc($content['dean']['title'] ?? lang('Home.configurationMissing')) ?></h2>
                <div class="divider-green"></div>
                <blockquote class="dean-quote">
                    <?php foreach (($content['dean']['paragraphs'] ?? []) as $paragraph): ?>
                        <p><?= esc($paragraph) ?></p>
                    <?php endforeach ?>
                </blockquote>
                <?php if (! empty($content['dean']['signature'])): ?>
                    <p class="dean-signature fw-bold mb-0"><?= esc($content['dean']['signature']) ?></p>
                <?php endif ?>
            </div>
        </div>
    </div>
</section>

<section id="mission-vision" class="section-pad section-alt">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 640px;">
            <span class="section-label"><?= esc($content['mission_label'] ?? 'Nos valeurs fondamentales') ?></span>
            <h2 class="section-title"><?= esc($content['mission_title'] ?? 'Mission & Vision') ?></h2>
        </div>
        <div class="row g-4 mb-4">
            <?php foreach (['mission', 'vision'] as $key): ?>
                <?php $block = $content[$key] ?? []; ?>
                <div class="col-md-6">
                    <article class="mv-card h-100">
                        <div class="card-icon"><i class="bi <?= esc($block['icon'] ?? 'bi-compass', 'attr') ?>"></i></div>
                        <h3 class="h5"><?= esc($block['title'] ?? '') ?></h3>
                        <?php foreach (($block['paragraphs'] ?? []) as $index => $paragraph): ?>
                            <p class="<?= $index > 0 ? 'small text-muted mb-0' : '' ?>"><?= esc($paragraph) ?></p>
                        <?php endforeach ?>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
        <div id="valeurs" class="row g-4">
            <?php foreach (($content['values'] ?? []) as $value): ?>
                <div class="col-md-4">
                    <article class="card-faculte h-100 text-center">
                        <div class="card-icon mx-auto"><i class="bi <?= esc($value['icon'] ?? 'bi-star', 'attr') ?>"></i></div>
                        <h3 class="h6 fw-bold"><?= esc($value['title'] ?? '') ?></h3>
                        <p class="small text-muted mb-0"><?= esc($value['description'] ?? '') ?></p>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<section id="historique" class="section-pad bg-white">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <span class="section-label"><?= esc($content['history']['label'] ?? 'Notre parcours') ?></span>
                <h2 class="section-title"><?= esc($content['history']['title'] ?? lang('Home.configurationMissing')) ?></h2>
                <div class="divider-green"></div>
                <p class="text-muted"><?= esc($content['history']['text'] ?? '') ?></p>
            </div>
            <div class="col-lg-8">
                <div class="timeline">
                    <?php foreach ($timeline as $item): ?>
                        <article class="timeline-item">
                            <div class="timeline-year"><?= esc((string) $item->year) ?></div>
                            <h3 class="h6 fw-bold mb-1"><?= esc($item->title) ?></h3>
                            <p class="small text-muted mb-0"><?= esc($item->description) ?></p>
                        </article>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
