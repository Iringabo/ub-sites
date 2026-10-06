<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?php
$studyStages = $studyStages ?? [];
$facts = array_filter([
    ['icon' => 'bi-mortarboard', 'label' => lang('Site.programmes.level'), 'value' => site_level_label($programme->level)],
    ['icon' => 'bi-bookmark-star', 'label' => lang('Site.programmes.option'), 'value' => (string) ($programme->option_code ?? '')],
    ['icon' => 'bi-clock', 'label' => lang('Site.programmes.duration'), 'value' => (string) ($programme->duration ?? '')],
    ['icon' => 'bi-award', 'label' => lang('Site.programmes.credits'), 'value' => empty($programme->credits) ? '' : lang('Site.programmes.creditsValue', [(int) $programme->credits])],
    ['icon' => 'bi-translate', 'label' => lang('Site.programmes.language'), 'value' => (string) ($programme->language ?? '')],
    ['icon' => 'bi-calendar-check', 'label' => lang('Site.programmes.deadline'), 'value' => empty($programme->application_deadline) ? '' : site_format_date($programme->application_deadline)],
    ['icon' => 'bi-patch-check', 'label' => lang('Site.programmes.entryRequirement'), 'value' => (string) ($programme->entry_requirement ?? '')],
], static fn (array $fact): bool => trim($fact['value']) !== '');
$outcomes = is_array($programme->career_outcomes) ? $programme->career_outcomes : [];
$applyUrl = trim((string) ($programme->apply_url ?? ''));
?>
<?= view('partials/page_banner', [
    'pageTitle'    => $pageTitle ?? $programme->title,
    'pageSubtitle' => trim(site_level_label($programme->level) . ' · ' . $programme->duration, ' ·'),
    'breadcrumbs'  => [
        ['label' => lang('Site.common.home'), 'url' => site_url('/')],
        ['label' => lang('Site.pageTitles.programmes'), 'url' => site_url('formations')],
        ['label' => $programme->title],
    ],
]) ?>

<?php if ($facts !== []): ?>
    <section class="facts-bar" aria-label="<?= esc(lang('Site.programmes.keyInformation'), 'attr') ?>">
        <div class="container">
            <dl class="facts-bar-list">
                <?php foreach ($facts as $fact): ?>
                    <div class="facts-bar-item<?= mb_strlen($fact['value']) > 28 ? ' facts-bar-item-wide' : '' ?>">
                        <i class="bi <?= esc($fact['icon'], 'attr') ?>" aria-hidden="true"></i>
                        <dt><?= esc($fact['label']) ?></dt>
                        <dd><?= esc($fact['value']) ?></dd>
                    </div>
                <?php endforeach ?>
            </dl>
        </div>
    </section>
<?php endif ?>

<section class="section-pad bg-white">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <span class="section-label"><?= esc(site_level_label($programme->level)) ?></span>
                <h2 class="section-title"><?= esc($programme->title) ?></h2>
                <div class="divider-green"></div>
                <?php if ($department !== null): ?>
                    <p class="programme-department">
                        <i class="bi bi-diagram-3 me-1" aria-hidden="true"></i><?= esc(lang('Site.departments.label')) ?> :
                        <a href="<?= site_url('departements/' . $department->slug) ?>" class="text-brand fw-semibold"><?= esc($department->name) ?></a>
                    </p>
                <?php endif ?>
                <?php if ($studyStages !== []): ?>
                    <aside class="common-core-card" aria-labelledby="common-core-title">
                        <span class="common-core-icon" aria-hidden="true"><i class="bi bi-signpost-split"></i></span>
                        <div class="flex-grow-1">
                            <span class="common-core-kicker"><?= esc(lang('Site.programmes.studyPath')) ?></span>
                            <h3 class="h6 mb-3" id="common-core-title"><?= esc($studyStages[0]['name']) ?></h3>
                            <ol class="study-stages">
                                <?php foreach ($studyStages as $stage): ?>
                                    <li class="study-stage study-stage-<?= esc($stage['kind'], 'attr') ?>">
                                        <span class="study-stage-years"><?= esc((string) ($stage['years'] ?? '')) ?></span>
                                        <span class="study-stage-body">
                                            <span class="study-stage-label"><?= esc($stage['kind'] === 'option' ? lang('Site.programmes.optionStage') : ($stage['kind'] === 'branch' ? lang('Site.programmes.branchCore') : lang('Site.programmes.commonCore'))) ?></span>
                                            <strong class="d-block"><?= esc($stage['name']) ?></strong>
                                            <?php if ($stage['peers'] !== []): ?>
                                                <span class="common-core-peers">
                                                    <span class="small text-muted me-1"><?= esc(lang('Site.programmes.sharedWithOptions')) ?></span>
                                                    <?php foreach ($stage['peers'] as $peer): ?>
                                                        <a href="<?= site_url('formations/' . $peer->slug) ?>" title="<?= esc($peer->title, 'attr') ?>"><?= esc(trim((string) ($peer->option_code ?? '')) ?: $peer->title) ?></a>
                                                    <?php endforeach ?>
                                                </span>
                                            <?php endif ?>
                                            <?php if ($stage['departments'] !== []): ?>
                                                <span class="d-block small text-muted mt-1"><?= esc(lang('Site.programmes.sharedWith')) ?> : <?= esc(implode(', ', array_map(static fn (object $item): string => (string) $item->name, $stage['departments']))) ?></span>
                                            <?php endif ?>
                                        </span>
                                    </li>
                                <?php endforeach ?>
                            </ol>
                        </div>
                    </aside>
                <?php endif ?>
                <?php foreach (site_paragraphs($programme->description) as $paragraph): ?>
                    <p><?= esc($paragraph) ?></p>
                <?php endforeach ?>

                <?php if ($curriculum !== []): ?>
                    <h3 class="h5 mt-5" id="programme-cours"><?= esc(lang('Site.programmes.curriculum')) ?></h3>
                    <?php foreach ($curriculum as $group => $courses): ?>
                        <?php $groupCredits = array_sum(array_map(static fn (array $course): int => (int) $course['credits'], $courses)); ?>
                        <div class="curriculum-group">
                            <?php if ($group !== ''): ?>
                                <h4 class="curriculum-group-title">
                                    <span class="semester-badge" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
                                    <?= esc((string) $group) ?>
                                    <?php if ($groupCredits > 0): ?>
                                        <span class="curriculum-credits"><?= esc(lang('Site.programmes.creditsValue', [$groupCredits])) ?></span>
                                    <?php endif ?>
                                </h4>
                            <?php endif ?>
                            <div class="table-responsive">
                                <table class="table table-striped curriculum-table">
                                    <thead>
                                        <tr>
                                            <th scope="col"><?= esc(lang('Site.programmes.course')) ?></th>
                                            <th scope="col" class="text-end"><?= esc(lang('Site.programmes.credits')) ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td><?= esc($course['title']) ?></td>
                                                <td class="text-end"><?= esc($course['credits'] !== '' ? $course['credits'] : '—') ?></td>
                                            </tr>
                                        <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>

                <?php if ($outcomes !== []): ?>
                    <h3 class="h5 mt-5"><?= esc(lang('Site.programmes.professionalOutcomes')) ?></h3>
                    <ul class="check-list outcome-list">
                        <?php foreach ($outcomes as $outcome): ?>
                            <li><?= esc($outcome) ?></li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
            <aside class="col-lg-4 d-flex flex-column gap-4">
                <article class="card-faculte programme-apply-card">
                    <h3 class="h5"><?= esc(lang('Site.programmes.admissionConditions')) ?></h3>
                    <?php foreach (site_paragraphs($programme->admission_conditions) as $paragraph): ?>
                        <p class="small text-muted"><?= esc($paragraph) ?></p>
                    <?php endforeach ?>
                    <?php if (! empty($programme->application_deadline)): ?>
                        <p class="small mb-3"><strong><?= esc(lang('Site.programmes.deadline')) ?> :</strong> <?= esc(site_format_date($programme->application_deadline)) ?></p>
                    <?php endif ?>
                    <div class="d-grid gap-2">
                        <?php if ($applyUrl !== ''): ?>
                            <a href="<?= esc(site_public_url($applyUrl), 'attr') ?>" class="btn btn-primary-green" rel="noopener"><i class="bi bi-send me-1" aria-hidden="true"></i><?= esc(lang('Site.admissions.applyButton')) ?></a>
                        <?php endif ?>
                        <a href="<?= site_url('admissions') ?>" class="btn btn-outline-green"><?= esc(lang('Site.admissions.howToApply')) ?></a>
                        <?php if ($brochureUrl !== null): ?>
                            <a href="<?= esc($brochureUrl, 'attr') ?>" class="btn btn-outline-green" download>
                                <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><?= esc(lang('Site.programmes.brochure')) ?>
                                <span class="d-block small fw-normal"><?= esc(lang('Site.programmes.brochureSize', [max(1, (int) round($brochureSize / 1024))])) ?></span>
                            </a>
                        <?php endif ?>
                    </div>
                </article>
                <?php if ($coordinator !== null): ?>
                    <article class="card-faculte programme-coordinator">
                        <h3 class="h6 text-uppercase small text-muted mb-3"><?= esc(lang('Site.programmes.coordinator')) ?></h3>
                        <div class="d-flex align-items-center gap-3">
                            <?php $coordinatorPhoto = site_person_media($coordinator->photo ?? null); ?>
                            <?php if ($coordinatorPhoto !== null): ?>
                                <img src="<?= esc(site_media_url($coordinatorPhoto), 'attr') ?>" alt="" class="person-avatar">
                            <?php else: ?>
                                <span class="person-avatar person-avatar-initials" aria-hidden="true"><?= esc(site_initials($coordinator->name)) ?></span>
                            <?php endif ?>
                            <div>
                                <a href="<?= site_url('corps-enseignant/' . $coordinator->slug) ?>" class="fw-semibold text-brand"><?= esc($coordinator->name) ?></a>
                                <p class="small text-muted mb-0"><?= esc($coordinator->grade ?? '') ?></p>
                                <?php if (! empty($coordinator->email)): ?>
                                    <a href="mailto:<?= esc($coordinator->email, 'attr') ?>" class="small"><?= esc($coordinator->email) ?></a>
                                <?php endif ?>
                            </div>
                        </div>
                    </article>
                <?php endif ?>
            </aside>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
