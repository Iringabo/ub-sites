<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?php
$cycle = (string) ($department->cycle ?: 'both');
$studyPaths = $studyPaths ?? [];
$maquetteUrl = $maquetteUrl ?? null;
?>
<?= view('partials/page_banner', [
    'pageTitle'    => $department->name,
    'pageSubtitle' => trim(($department->abbreviation ? $department->abbreviation . ' · ' : '') . lang('Site.departments.cycle.' . $cycle)),
    'breadcrumbs'  => [
        ['label' => lang('Site.common.home'), 'url' => site_url('/')],
        ['label' => lang('Site.pageTitles.departments'), 'url' => site_url('departements')],
        ['label' => $department->name],
    ],
]) ?>

<div class="stat-strip">
    <div class="container">
        <ul class="stat-strip-list">
            <li><i class="bi bi-diagram-2" aria-hidden="true"></i><span><span class="stat-strip-label"><?= esc(lang('Site.departments.cycleLabel')) ?></span><span class="stat-strip-value"><?= esc(lang('Site.departments.cycle.' . $cycle)) ?></span></span></li>
            <li><i class="bi bi-journal-bookmark" aria-hidden="true"></i><span><span class="stat-strip-label"><?= esc(lang('Site.departments.programmes')) ?></span><span class="stat-strip-value"><?= count($programmes) ?></span></span></li>
            <li><i class="bi bi-people" aria-hidden="true"></i><span><span class="stat-strip-label"><?= esc(lang('Site.departments.team')) ?></span><span class="stat-strip-value"><?= count($staff) ?></span></span></li>
        </ul>
    </div>
</div>

<section class="section-pad bg-white">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <span class="section-label"><?= esc(lang('Site.departments.label')) ?></span>
                <h2 class="section-title"><?= esc($department->name) ?></h2>
                <div class="divider-green"></div>
                <?php foreach (site_paragraphs((string) ($department->description ?? '')) as $paragraph): ?>
                    <p class="lead-text"><?= esc($paragraph) ?></p>
                <?php endforeach ?>

                <ul class="nav nav-pills-green mt-5 mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="dept-programmes-tab" data-bs-toggle="tab" data-bs-target="#dept-programmes" type="button" role="tab" aria-controls="dept-programmes" aria-selected="true">
                            <?= esc(lang('Site.departments.programmes')) ?> <span class="tab-count" aria-hidden="true"><?= count($programmes) ?></span>
                        </button>
                    </li>
                    <?php if ($staff !== []): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="dept-team-tab" data-bs-toggle="tab" data-bs-target="#dept-team" type="button" role="tab" aria-controls="dept-team" aria-selected="false">
                                <?= esc(lang('Site.departments.team')) ?> <span class="tab-count" aria-hidden="true"><?= count($staff) ?></span>
                            </button>
                        </li>
                    <?php endif ?>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="dept-programmes" role="tabpanel" aria-labelledby="dept-programmes-tab">
                        <?php if ($programmes === [] && $maquetteUrl === null): ?>
                            <p class="empty-state"><i class="bi bi-journal-x" aria-hidden="true"></i><?= esc(lang('Site.departments.noProgrammes')) ?></p>
                        <?php endif ?>
                        <?php if ($programmes !== [] || $maquetteUrl !== null): ?>
                            <section class="study-path-section" aria-labelledby="study-path-title">
                                <h3 class="study-path-heading" id="study-path-title"><?= esc(lang('Site.programmes.studyPath')) ?></h3>
                                <p class="small text-muted"><?= esc(lang('Site.programmes.studyPathIntro')) ?></p>
                                <?php if ($maquetteUrl !== null): ?>
                                    <article class="maquette-card">
                                        <span class="maquette-icon" aria-hidden="true"><i class="bi bi-file-earmark-spreadsheet"></i></span>
                                        <div class="maquette-body">
                                            <h4 class="maquette-title"><?= esc(trim((string) ($department->maquette_title ?? '')) ?: lang('Site.departments.maquette')) ?></h4>
                                            <p class="small text-muted mb-0"><?= esc(lang('Site.departments.maquetteText')) ?> <span class="text-nowrap">(<?= esc(lang('Site.departments.maquetteSize', [max(1, (int) round(($maquetteSize ?? 0) / 1024))])) ?>)</span></p>
                                        </div>
                                        <div class="maquette-actions">
                                            <a href="<?= esc($maquetteUrl, 'attr') ?>" class="btn btn-primary-green btn-sm" download><i class="bi bi-download me-1" aria-hidden="true"></i><?= esc(lang('Site.departments.maquetteDownload')) ?></a>
                                            <a href="<?= esc($maquetteUrl, 'attr') ?>" class="btn btn-outline-green btn-sm" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i><?= esc(lang('Site.departments.maquetteOpen')) ?></a>
                                        </div>
                                    </article>
                                <?php endif ?>
                                <?php foreach (['licence', 'master'] as $level): ?>
                                    <?php if (! isset($studyPaths[$level])) {
                                        continue;
                                    } ?>
                                    <div class="programme-level-group">
                                        <h4 class="programme-level-title"><?= esc(site_level_label($level)) ?></h4>
                                        <?php foreach ($studyPaths[$level]['trunks'] as $trunk): ?>
                                            <?= view('partials/study_path', ['trunk' => $trunk]) ?>
                                        <?php endforeach ?>
                                        <?php if ($studyPaths[$level]['singles'] !== []): ?>
                                            <div class="row g-3">
                                                <?php foreach ($studyPaths[$level]['singles'] as $item): ?>
                                                    <div class="col-md-6"><?= view('partials/programme_tile', ['programme' => $item['programme'], 'years' => $item['years']]) ?></div>
                                                <?php endforeach ?>
                                            </div>
                                        <?php endif ?>
                                    </div>
                                <?php endforeach ?>
                            </section>
                        <?php endif ?>
                    </div>
                    <?php if ($staff !== []): ?>
                        <div class="tab-pane fade" id="dept-team" role="tabpanel" aria-labelledby="dept-team-tab">
                            <div class="row g-3">
                                <?php foreach ($staff as $member): ?>
                                    <div class="col-sm-6">
                                        <a href="<?= site_url('corps-enseignant/' . $member->slug) ?>" class="person-row">
                                            <?= view('partials/person_avatar', ['person' => $member]) ?>
                                            <span>
                                                <span class="d-block fw-semibold"><?= esc($member->name) ?></span>
                                                <span class="d-block small text-muted"><?= esc($member->grade ?? site_staff_category_label($member->category)) ?></span>
                                            </span>
                                        </a>
                                    </div>
                                <?php endforeach ?>
                            </div>
                            <a href="<?= site_url('corps-enseignant?departement=' . rawurlencode($department->slug)) ?>" class="btn btn-link text-brand px-0 mt-3"><?= esc(lang('Site.departments.allStaff')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        </div>
                    <?php endif ?>
                </div>
            </div>
            <aside class="col-lg-4 d-flex flex-column gap-4">
                <?php if ($head !== null): ?>
                    <article class="card-faculte side-card">
                        <h3 class="side-card-title"><?= esc(lang('Site.departments.head')) ?></h3>
                        <a href="<?= site_url('corps-enseignant/' . $head->slug) ?>" class="person-row person-row-lg">
                            <?= view('partials/person_avatar', ['person' => $head, 'class' => 'person-avatar-lg']) ?>
                            <span>
                                <span class="d-block fw-semibold"><?= esc($head->name) ?></span>
                                <span class="d-block small text-muted"><?= esc($head->grade ?? '') ?></span>
                            </span>
                        </a>
                    </article>
                <?php endif ?>
                <?php if (! empty($department->email) || ! empty($department->phone)): ?>
                    <article class="card-faculte side-card">
                        <h3 class="side-card-title"><?= esc(lang('Site.departments.contact')) ?></h3>
                        <?php if (! empty($department->email)): ?>
                            <a class="contact-line" href="mailto:<?= esc($department->email, 'attr') ?>"><i class="bi bi-envelope" aria-hidden="true"></i><span><?= esc($department->email) ?></span></a>
                        <?php endif ?>
                        <?php if (! empty($department->phone)): ?>
                            <a class="contact-line" href="tel:<?= esc(preg_replace('/[^0-9+]/', '', (string) $department->phone), 'attr') ?>"><i class="bi bi-telephone" aria-hidden="true"></i><span><?= esc($department->phone) ?></span></a>
                        <?php endif ?>
                    </article>
                <?php endif ?>
                <a href="<?= site_url('departements') ?>" class="btn btn-outline-green align-self-start"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Site.departments.back')) ?></a>
            </aside>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
