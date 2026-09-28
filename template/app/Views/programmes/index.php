<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'       => site_text_or_placeholder((string) ($content['banner_title'] ?? ''), $pageTitle ?? lang('Site.pageTitles.programmes')),
    'breadcrumbTitle' => $pageTitle ?? lang('Site.pageTitles.programmes'),
    'pageSubtitle'    => $content['banner_subtitle'] ?? '',
    'bannerImage'     => $content['banner_image'] ?? '',
]) ?>

<section id="offre" class="section-pad-sm bg-white">
    <div class="container">
        <div class="text-center mx-auto mb-4" style="max-width: 640px;">
            <span class="section-label"><?= esc($content['offer_label'] ?? lang('Site.programmes.offerLabel')) ?></span>
            <h2 class="section-title"><?= esc($content['offer_title'] ?? '') ?></h2>
            <p class="section-subtitle mx-auto"><?= esc($content['offer_text'] ?? '') ?></p>
        </div>

        <ul class="nav nav-pills-green justify-content-center mb-5" id="formationTabs" role="tablist">
            <?php foreach (['licence', 'master', 'doctorat'] as $index => $level): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?= $index === 0 ? ' active' : '' ?>" id="<?= esc($level, 'attr') ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= esc($level, 'attr') ?>" type="button" role="tab" aria-controls="<?= esc($level, 'attr') ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                        <?= esc(site_level_label($level)) ?>
                        <span class="tab-count" aria-hidden="true"><?= count($programmes[$level] ?? []) ?></span>
                    </button>
                </li>
            <?php endforeach ?>
        </ul>

        <div class="tab-content" id="formationTabsContent">
            <?php foreach (['licence', 'master', 'doctorat'] as $index => $level): ?>
                <div class="tab-pane fade<?= $index === 0 ? ' show active' : '' ?>" id="<?= esc($level, 'attr') ?>" role="tabpanel" aria-labelledby="<?= esc($level, 'attr') ?>-tab">
                    <div class="row g-4 <?= $level === 'doctorat' ? 'justify-content-center' : '' ?>">
                        <?php foreach ($programmes[$level] ?? [] as $programme): ?>
                            <div class="<?= $level === 'doctorat' ? 'col-lg-5' : 'col-lg-4' ?>">
                                <article class="formation-card h-100 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                                        <span class="badge-level"><?= esc(site_level_label($level)) ?></span>
                                        <span class="small text-muted"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= esc($programme->duration) ?></span>
                                    </div>
                                    <h3 class="h6 fw-bold mb-2"><?= esc($programme->title) ?></h3>
                                    <p class="small text-muted"><?= esc($programme->summary) ?></p>
                                    <?php $outcomes = is_array($programme->career_outcomes) ? $programme->career_outcomes : []; ?>
                                    <?php if ($outcomes !== []): ?>
                                        <p class="small fw-semibold mb-1"><?= esc(lang('Site.programmes.professionalOutcomes')) ?></p>
                                        <ul class="small text-muted ps-3 mb-3">
                                            <?php foreach ($outcomes as $outcome): ?>
                                                <li><?= esc($outcome) ?></li>
                                            <?php endforeach ?>
                                        </ul>
                                    <?php endif ?>
                                    <div class="bg-light rounded p-2 small mb-3">
                                        <strong><?= esc(lang('Site.programmes.admissionConditions')) ?> :</strong> <?= esc($programme->admission_conditions) ?>
                                    </div>
                                    <a href="<?= site_url('formations/' . $programme->slug) ?>" class="btn btn-outline-green btn-sm mt-auto align-self-start"><?= esc(lang('Home.viewProgramme')) ?></a>
                                </article>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<section id="appel" class="section-pad-sm section-alt">
    <div class="container text-center">
        <h2 class="h4 fw-bold"><?= esc($content['cta_title'] ?? lang('Site.programmes.applyQuestion')) ?></h2>
        <p class="section-subtitle mx-auto mb-4"><?= esc($content['cta_text'] ?? '') ?></p>
        <a href="<?= esc(site_public_url($content['cta_url'] ?? '/contact'), 'attr') ?>" class="btn btn-primary-green">
            <?= esc($content['cta_label'] ?? lang('Site.programmes.contactUs')) ?>
        </a>
    </div>
</section>
<?= $this->endSection() ?>
