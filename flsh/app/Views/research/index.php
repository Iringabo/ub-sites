<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'    => $pageTitle ?? lang('Site.pageTitles.research'),
    'pageSubtitle' => $content['banner_subtitle'] ?? '',
]) ?>

<section class="section-pad bg-white">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 640px;">
            <span class="section-label"><?= esc($content['labs_label'] ?? lang('Site.research.labsLabel')) ?></span>
            <h2 class="section-title"><?= esc($content['labs_title'] ?? lang('Site.research.labsTitle')) ?></h2>
            <p class="section-subtitle mx-auto"><?= esc($content['labs_text'] ?? '') ?></p>
        </div>
        <div class="row g-4">
            <?php foreach ($laboratories as $lab): ?>
                <div class="col-lg-6">
                    <article class="card-faculte h-100">
                        <div class="d-flex align-items-start gap-3 mb-2">
                            <div class="card-icon mb-0"><i class="bi <?= esc($lab->icon ?? 'bi-diagram-3', 'attr') ?>"></i></div>
                            <div>
                                <p class="fw-bold text-success mb-0"><?= esc($lab->abbreviation) ?></p>
                                <h3 class="h6 fw-semibold mb-0"><?= esc($lab->name) ?></h3>
                            </div>
                        </div>
                        <p class="small text-muted"><?= esc($lab->description) ?></p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php foreach ((is_array($lab->themes) ? $lab->themes : []) as $theme): ?>
                                <span class="badge-news badge-actualite"><?= esc($theme) ?></span>
                            <?php endforeach ?>
                        </div>
                        <p class="small text-muted mb-0"><i class="bi bi-people me-1"></i><?= esc((string) $lab->researcher_count) ?> <?= esc(lang('Site.research.researchers')) ?></p>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<section class="section-pad section-alt">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <span class="section-label"><?= esc($content['publications_label'] ?? lang('Site.research.publicationsLabel')) ?></span>
                <h2 class="section-title"><?= esc($content['publications_title'] ?? lang('Site.research.publicationsTitle')) ?></h2>
                <div class="divider-green"></div>
                <p class="text-muted"><?= esc($content['publications_text'] ?? '') ?></p>
                <div class="d-flex flex-wrap gap-4 mt-4">
                    <?php foreach ($stats as $stat): ?>
                        <div>
                            <div class="fw-bold fs-4 text-success"><?= esc($stat->value . ($stat->suffix ?? '')) ?></div>
                            <div class="small text-muted"><?= esc($stat->label) ?></div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
            <div class="col-lg-8">
                <?php foreach ($publications as $publication): ?>
                    <article class="publication-item">
                        <span class="pub-year mb-2"><?= esc((string) $publication->year) ?></span>
                        <h3 class="h6 fw-bold mt-2 mb-1"><?= esc($publication->title) ?></h3>
                        <p class="small text-muted mb-1"><?= esc($publication->authors) ?></p>
                        <p class="small text-success mb-0"><i class="bi bi-journal me-1"></i><?= esc($publication->journal) ?></p>
                    </article>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</section>

<section class="section-pad bg-white">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 640px;">
            <span class="section-label"><?= esc($content['projects_label'] ?? lang('Site.research.projectsLabel')) ?></span>
            <h2 class="section-title"><?= esc($content['projects_title'] ?? lang('Site.research.projectsTitle')) ?></h2>
        </div>
        <div class="row g-4">
            <?php foreach ($projects as $project): ?>
                <div class="col-lg-4 col-md-6">
                    <article class="card-faculte h-100">
                        <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                            <div class="card-icon mb-0"><i class="bi <?= esc($project->icon ?? 'bi-briefcase', 'attr') ?>"></i></div>
                            <span class="badge-news badge-actualite">
                                <?= esc(trim(($project->period_start ?? '') . '-' . ($project->period_end ?? ''), '-')) ?>
                            </span>
                        </div>
                        <h3 class="h6 fw-bold text-success"><?= esc($project->code) ?></h3>
                        <p class="small text-muted"><?= esc($project->description) ?></p>
                        <?php if ($project->funder): ?>
                            <p class="small text-muted mb-0"><i class="bi bi-cash me-1"></i><?= esc($project->funder) ?></p>
                        <?php endif ?>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
