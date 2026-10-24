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

        <?php $activeLevel ??= 'licence'; ?>
        <ul class="nav nav-pills-green justify-content-center mb-4" id="formationTabs" role="tablist">
            <?php foreach (['licence', 'master'] as $level): ?>
                <?php $isActiveLevel = $level === $activeLevel; ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?= $isActiveLevel ? ' active' : '' ?>" id="<?= esc($level, 'attr') ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= esc($level, 'attr') ?>" type="button" role="tab" aria-controls="<?= esc($level, 'attr') ?>" aria-selected="<?= $isActiveLevel ? 'true' : 'false' ?>">
                        <?= esc(site_level_label($level)) ?>
                        <span class="tab-count" aria-hidden="true"><?= count($programmes[$level] ?? []) ?></span>
                    </button>
                </li>
            <?php endforeach ?>
        </ul>

        <div class="tab-content" id="formationTabsContent">
            <?php foreach (['licence', 'master'] as $level): ?>
                <?php $levelDepartments = array_filter($departments, static fn (object $department): bool => \App\Models\DepartmentModel::cycleAccepts($department->cycle ?? null, $level)); ?>
                <div class="tab-pane fade<?= $level === $activeLevel ? ' show active' : '' ?>" id="<?= esc($level, 'attr') ?>" role="tabpanel" aria-labelledby="<?= esc($level, 'attr') ?>-tab">
                    <?php if ($levelDepartments !== []): ?>
                        <nav class="filter-bar justify-content-center mb-4" aria-label="<?= esc(lang('Site.programmes.filterByDepartment'), 'attr') ?>">
                            <a class="filter-chip<?= $selectedDepartment === null ? ' active' : '' ?>" href="<?= site_url('formations?niveau=' . $level) ?>"<?= $selectedDepartment === null ? ' aria-current="true"' : '' ?>><?= esc(lang('Site.programmes.allDepartments')) ?></a>
                            <?php foreach ($levelDepartments as $department): ?>
                                <?php $isSelected = $selectedDepartment !== null && (int) $selectedDepartment->id === (int) $department->id; ?>
                                <a class="filter-chip<?= $isSelected ? ' active' : '' ?>" href="<?= site_url('formations?niveau=' . $level . '&departement=' . rawurlencode($department->slug)) ?>" title="<?= esc($department->name, 'attr') ?>"<?= $isSelected ? ' aria-current="true"' : '' ?>><?= esc($department->abbreviation ?: $department->name) ?></a>
                            <?php endforeach ?>
                        </nav>
                    <?php endif ?>
                    <div class="row g-4">
                        <?php foreach ($programmes[$level] ?? [] as $programme): ?>
                            <div class="col-lg-4">
                                <article class="formation-card h-100 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                                        <span class="d-inline-flex align-items-center gap-1">
                                            <span class="badge-level"><?= esc(site_level_label($level)) ?></span>
                                            <?php if (trim((string) ($programme->option_code ?? '')) !== ''): ?>
                                                <span class="option-code" title="<?= esc(lang('Site.programmes.option'), 'attr') ?>"><?= esc((string) $programme->option_code) ?></span>
                                            <?php endif ?>
                                        </span>
                                        <span class="small text-muted"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= esc($programme->duration) ?></span>
                                    </div>
                                    <h3 class="h6 fw-bold mb-2"><?= esc($programme->title) ?></h3>
                                    <?php $programmeDepartment = $departments[(int) ($programme->department_id ?? 0)] ?? null; ?>
                                    <?php if ($programmeDepartment !== null): ?>
                                        <p class="small text-brand mb-2"><i class="bi bi-diagram-3 me-1" aria-hidden="true"></i><?= esc($programmeDepartment->name) ?></p>
                                    <?php endif ?>
                                    <?php $studySummary = ($studySummaries ?? [])[(int) $programme->id] ?? null; ?>
                                    <?php if ($studySummary !== null): ?>
                                        <p class="small common-core-line mb-2">
                                            <i class="bi bi-signpost-split me-1" aria-hidden="true"></i><?= esc($studySummary['core']) ?> · <?= esc(lang('Site.programmes.optionFrom', [lang('Site.programmes.yearFrom', [$studySummary['from']])])) ?>
                                        </p>
                                    <?php endif ?>
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
                        <?php if (($programmes[$level] ?? []) === []): ?>
                            <p class="text-center text-muted"><?= esc(lang('Site.programmes.noneForLevel')) ?></p>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
        <?php if (! empty($doctoralUrl)): ?>
            <aside class="doctoral-callout mt-4" aria-labelledby="doctoral-callout-title">
                <span class="doctoral-callout-icon" aria-hidden="true"><i class="bi bi-mortarboard"></i></span>
                <div>
                    <h3 class="h6 mb-1" id="doctoral-callout-title"><?= esc(lang('Site.programmes.doctoralTitle')) ?></h3>
                    <p class="small text-muted mb-0"><?= esc(lang('Site.programmes.doctoralText')) ?></p>
                </div>
                <a href="<?= esc($doctoralUrl, 'attr') ?>" class="btn btn-outline-green btn-sm ms-md-auto" rel="noopener" target="_blank"><?= esc(lang('Site.programmes.doctoralLink')) ?> <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a>
            </aside>
        <?php endif ?>
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
