<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?php
$lines = static fn (mixed $value): array => is_array($value)
    ? array_values(array_filter(array_map(static fn ($line): string => trim((string) $line), $value), static fn (string $line): bool => $line !== ''))
    : site_paragraphs((string) $value);
$steps = $lines($content['steps'] ?? []);
$documents = $lines($content['documents'] ?? []);
$feesText = trim((string) ($content['fees_text'] ?? ''));
$portalUrl = trim((string) ($content['portal_url'] ?? ''));
$withDeadline = array_values(array_filter($programmes, static fn (object $programme): bool => ! empty($programme->application_deadline) || trim((string) ($programme->apply_url ?? '')) !== '' || trim((string) ($programme->entry_requirement ?? '')) !== ''));
$jumpTiles = array_filter([
    ['procedure', 'bi-signpost-split', lang('Site.admissions.stepsTitle'), $steps !== [] ? lang('Site.admissions.stepsCount', [count($steps)]) : ''],
    ['dossier', 'bi-folder2-open', site_text_or_placeholder($content['documents_title'] ?? null, lang('Site.admissions.documentsTitle')), $documents !== [] ? lang('Site.admissions.documentsCount', [count($documents)]) : ''],
    $withDeadline !== [] ? ['dates', 'bi-calendar-check', lang('Site.admissions.datesTitle'), lang('Site.admissions.datesCount', [count($withDeadline)])] : null,
]);
?>
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
                <h2 class="section-title"><?= esc($content['intro_title'] ?? lang('Site.admissions.defaultTitle')) ?></h2>
                <div class="divider-green"></div>
                <?php foreach (site_paragraphs((string) ($content['intro_text'] ?? '')) as $paragraph): ?>
                    <p class="lead-text"><?= esc($paragraph) ?></p>
                <?php endforeach ?>
            </div>
            <nav class="page-intro-jump" aria-label="<?= esc($pageTitle, 'attr') ?>">
                <?php foreach ($jumpTiles as [$anchor, $icon, $title, $meta]): ?>
                    <a href="#<?= esc($anchor, 'attr') ?>" class="jump-tile">
                        <span class="jump-tile-icon" aria-hidden="true"><i class="bi <?= esc($icon, 'attr') ?>"></i></span>
                        <span>
                            <span class="jump-tile-title"><?= esc($title) ?></span>
                            <?php if ($meta !== ''): ?><span class="jump-tile-meta"><?= esc($meta) ?></span><?php endif ?>
                        </span>
                        <i class="bi bi-arrow-down-short ms-auto" aria-hidden="true"></i>
                    </a>
                <?php endforeach ?>
            </nav>
        </div>
    </div>
</section>

<section id="procedure" class="section-pad section-alt">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7">
                <h2 class="h3 fw-bold mb-4"><?= esc(lang('Site.admissions.stepsTitle')) ?></h2>
                <?php if ($steps !== []): ?>
                    <ol class="steps">
                        <?php foreach ($steps as $i => $step): ?>
                            <li class="steps-item">
                                <span class="steps-marker" aria-hidden="true"><?= $i + 1 ?></span>
                                <div class="steps-card">
                                    <span class="steps-kicker"><?= esc(lang('Site.admissions.stepLabel', [$i + 1])) ?></span>
                                    <p class="mb-0"><?= esc($step) ?></p>
                                </div>
                            </li>
                        <?php endforeach ?>
                    </ol>
                <?php else: ?>
                    <p class="empty-state"><i class="bi bi-hourglass-split" aria-hidden="true"></i><?= esc(lang('Site.admissions.stepsMissing')) ?></p>
                <?php endif ?>
            </div>
            <aside class="col-lg-5 d-flex flex-column gap-4">
                <article id="dossier" class="feature-card">
                    <header class="feature-card-head">
                        <span class="feature-card-icon" aria-hidden="true"><i class="bi bi-folder2-open"></i></span>
                        <h2 class="h5 mb-0"><?= esc($content['documents_title'] ?? lang('Site.admissions.documentsTitle')) ?></h2>
                    </header>
                    <?php if ($documents !== []): ?>
                        <ul class="check-list mb-0">
                            <?php foreach ($documents as $document): ?>
                                <li><?= esc($document) ?></li>
                            <?php endforeach ?>
                        </ul>
                    <?php else: ?>
                        <p class="small text-muted mb-0"><?= esc(lang('Site.admissions.documentsMissing')) ?></p>
                    <?php endif ?>
                </article>
                <?php if ($feesText !== ''): ?>
                    <article id="frais" class="feature-card feature-card-accent">
                        <header class="feature-card-head">
                            <span class="feature-card-icon" aria-hidden="true"><i class="bi bi-award"></i></span>
                            <h2 class="h5 mb-0"><?= esc($content['fees_title'] ?? lang('Site.admissions.feesTitle')) ?></h2>
                        </header>
                        <?php foreach (site_paragraphs($feesText) as $paragraph): ?>
                            <p class="mb-2"><?= esc($paragraph) ?></p>
                        <?php endforeach ?>
                    </article>
                <?php endif ?>
            </aside>
        </div>
    </div>
</section>

<?php if ($withDeadline !== []): ?>
    <section id="dates" class="section-pad bg-white">
        <div class="container">
            <h2 class="h3 fw-bold mb-4"><?= esc(lang('Site.admissions.deadlinesTitle')) ?></h2>
            <table class="table admissions-table align-middle">
                <thead>
                    <tr>
                        <th scope="col"><?= esc(lang('Site.admissions.programme')) ?></th>
                        <th scope="col"><?= esc(lang('Site.programmes.level')) ?></th>
                        <th scope="col"><?= esc(lang('Site.programmes.deadline')) ?></th>
                        <th scope="col"><?= esc(lang('Site.programmes.entryRequirement')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= esc(lang('Site.admissions.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($withDeadline as $programme): ?>
                        <?php $applyUrl = trim((string) ($programme->apply_url ?? '')); ?>
                        <tr>
                            <th scope="row"><a href="<?= site_url('formations/' . $programme->slug) ?>" class="text-brand"><?= esc($programme->title) ?></a></th>
                            <td data-label="<?= esc(lang('Site.programmes.level'), 'attr') ?>"><span class="badge-level"><?= esc(site_level_label($programme->level)) ?></span></td>
                            <td data-label="<?= esc(lang('Site.programmes.deadline'), 'attr') ?>"><?= esc(empty($programme->application_deadline) ? '—' : site_format_date($programme->application_deadline)) ?></td>
                            <td data-label="<?= esc(lang('Site.programmes.entryRequirement'), 'attr') ?>"><?= esc(trim((string) ($programme->entry_requirement ?? '')) !== '' ? $programme->entry_requirement : '—') ?></td>
                            <td class="text-lg-end">
                                <?php if ($applyUrl !== ''): ?>
                                    <a href="<?= esc(site_public_url($applyUrl), 'attr') ?>" class="btn btn-sm btn-outline-green" rel="noopener"><?= esc(lang('Site.admissions.applyButton')) ?><span class="visually-hidden"> — <?= esc($programme->title) ?></span></a>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif ?>

<?php if ($faq !== []): ?>
    <section id="questions" class="section-pad-sm section-alt">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <h2 class="h3 fw-bold mb-4"><?= esc(lang('Site.admissions.faqTitle')) ?></h2>
                    <?= view('information/_faq_accordion', ['items' => $faq, 'accordionId' => 'admissionsFaq']) ?>
                    <a href="<?= site_url('faq') ?>" class="btn btn-link text-brand px-0 mt-3"><?= esc(lang('Site.faq.seeAll')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </div>
    </section>
<?php endif ?>

<section id="portail" class="section-pad-sm bg-white">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2 class="h3 fw-bold mb-2"><?= esc(site_text_or_placeholder($content['portal_title'] ?? null, lang('Site.admissions.portalTitle'))) ?></h2>
                <?php if (trim((string) ($content['portal_text'] ?? '')) !== ''): ?>
                    <p class="mb-0"><?= esc($content['portal_text']) ?></p>
                <?php endif ?>
            </div>
            <div class="cta-band-actions">
                <a href="<?= esc(site_public_url($portalUrl, '/contact'), 'attr') ?>" class="btn btn-light-brand"<?= $portalUrl !== '' ? ' rel="noopener"' : '' ?>>
                    <i class="bi bi-send me-1" aria-hidden="true"></i><?= esc(site_text_or_placeholder($content['portal_label'] ?? null, lang('Site.admissions.applyButton'))) ?>
                </a>
                <a href="<?= site_url('formations') ?>" class="btn btn-outline-light-brand"><?= esc(lang('Site.admissions.browseProgrammes')) ?></a>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
