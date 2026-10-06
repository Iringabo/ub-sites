<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'       => site_text_or_placeholder((string) ($content['banner_title'] ?? ''), $pageTitle),
    'breadcrumbTitle' => $pageTitle,
    'pageSubtitle'    => $content['banner_subtitle'] ?? '',
    'bannerImage'     => $content['banner_image'] ?? '',
]) ?>

<section class="section-pad bg-white">
    <div class="container">
        <div class="page-intro">
            <div>
                <?php if (trim((string) ($content['intro_label'] ?? '')) !== ''): ?>
                    <span class="section-label"><?= esc($content['intro_label']) ?></span>
                <?php endif ?>
                <h2 class="section-title"><?= esc($content['intro_title'] ?? lang('Site.departments.defaultTitle')) ?></h2>
                <div class="divider-green"></div>
                <?php if (trim((string) ($content['intro_text'] ?? '')) !== ''): ?>
                    <p class="lead-text mb-0"><?= esc($content['intro_text']) ?></p>
                <?php endif ?>
            </div>
            <?php if ($groups !== []): ?>
                <nav class="page-intro-jump" aria-label="<?= esc(lang('Site.pageTitles.departments'), 'attr') ?>">
                    <?php foreach ($groups as $cycle => $groupDepartments): ?>
                        <a href="#cycle-<?= esc($cycle, 'attr') ?>" class="jump-tile">
                            <span class="jump-tile-icon" aria-hidden="true"><i class="bi <?= $cycle === 'licence' ? 'bi-mortarboard' : 'bi-journal-richtext' ?>"></i></span>
                            <span>
                                <span class="jump-tile-title"><?= esc(lang('Site.departments.' . $cycle . 'Group')) ?></span>
                                <span class="jump-tile-meta"><?= esc(lang('Site.departments.count', [count($groupDepartments)])) ?></span>
                            </span>
                            <i class="bi bi-arrow-down-short ms-auto" aria-hidden="true"></i>
                        </a>
                    <?php endforeach ?>
                </nav>
            <?php endif ?>
        </div>

        <?php if ($departments === []): ?>
            <p class="empty-state"><i class="bi bi-diagram-2" aria-hidden="true"></i><?= esc(lang('Site.departments.empty')) ?></p>
        <?php endif ?>

        <?php foreach ($groups as $cycle => $groupDepartments): ?>
            <section id="cycle-<?= esc($cycle, 'attr') ?>" class="cycle-group" aria-labelledby="cycle-<?= esc($cycle, 'attr') ?>-title">
                <header class="cycle-group-head">
                    <span class="cycle-group-icon" aria-hidden="true"><i class="bi <?= $cycle === 'licence' ? 'bi-mortarboard' : 'bi-journal-richtext' ?>"></i></span>
                    <div>
                        <h2 class="h4 mb-1" id="cycle-<?= esc($cycle, 'attr') ?>-title"><?= esc(lang('Site.departments.' . $cycle . 'Group')) ?></h2>
                        <p class="text-muted mb-0"><?= esc(lang('Site.departments.' . $cycle . 'Intro')) ?></p>
                    </div>
                </header>
                <div class="row g-4">
                    <?php foreach ($groupDepartments as $department): ?>
                        <?php
                        $head = $heads[(int) ($department->head_staff_id ?? 0)] ?? null;
                        $programmeCount = (int) ($programmeCounts[(int) $department->id] ?? 0);
                        $staffCount = (int) ($staffCounts[(int) $department->id] ?? 0);
                        $summary = site_paragraphs((string) ($department->description ?? ''))[0] ?? '';
                        ?>
                        <div class="col-md-6 col-xl-4">
                            <article class="department-card h-100">
                                <div class="department-card-top">
                                    <span class="department-badge" aria-hidden="true"><?= esc($department->abbreviation ?: site_initials($department->name)) ?></span>
                                    <?php if (($department->cycle ?? 'both') === 'both'): ?>
                                        <span class="cycle-pill"><?= esc(lang('Site.departments.cycle.both')) ?></span>
                                    <?php endif ?>
                                </div>
                                <h3 class="department-card-title">
                                    <a href="<?= site_url('departements/' . $department->slug) ?>" class="stretched-link"><?= esc($department->name) ?></a>
                                </h3>
                                <?php if ($summary !== ''): ?>
                                    <p class="department-card-text"><?= esc(mb_strimwidth($summary, 0, 180, '…')) ?></p>
                                <?php endif ?>
                                <ul class="stat-pills" aria-label="<?= esc($department->name, 'attr') ?>">
                                    <li><i class="bi bi-journal-bookmark" aria-hidden="true"></i><?= esc(lang('Site.departments.programmeCount', [$programmeCount])) ?></li>
                                    <?php if ($staffCount > 0): ?>
                                        <li><i class="bi bi-people" aria-hidden="true"></i><?= esc(lang('Site.departments.staffCount', [$staffCount])) ?></li>
                                    <?php endif ?>
                                </ul>
                                <?php $codes = ($optionCodes ?? [])[(int) $department->id][$cycle] ?? []; ?>
                                <?php if ($codes !== []): ?>
                                    <p class="option-code-list">
                                        <span class="small text-muted me-1"><?= esc(lang('Site.departments.options')) ?> :</span>
                                        <?php foreach ($codes as $code): ?>
                                            <span class="option-code"><?= esc($code) ?></span>
                                        <?php endforeach ?>
                                    </p>
                                <?php endif ?>
                                <?php if ($head !== null): ?>
                                    <div class="department-card-head">
                                        <?= view('partials/person_avatar', ['person' => $head, 'class' => 'person-avatar-sm']) ?>
                                        <span>
                                            <span class="d-block small text-muted"><?= esc(lang('Site.departments.head')) ?></span>
                                            <span class="d-block fw-semibold small"><?= esc($head->name) ?></span>
                                        </span>
                                    </div>
                                <?php endif ?>
                            </article>
                        </div>
                    <?php endforeach ?>
                </div>
            </section>
        <?php endforeach ?>
    </div>
</section>
<?= $this->endSection() ?>
