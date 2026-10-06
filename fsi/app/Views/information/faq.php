<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?php
$faqIcons = ['admissions' => 'bi-door-open', 'fees' => 'bi-award', 'studies' => 'bi-journal-text', 'other' => 'bi-chat-dots'];
?>
<?= view('partials/page_banner', [
    'pageTitle'       => site_text_or_placeholder((string) ($content['banner_title'] ?? ''), $pageTitle),
    'breadcrumbTitle' => $pageTitle,
    'pageSubtitle'    => $content['banner_subtitle'] ?? '',
    'bannerImage'     => $content['banner_image'] ?? '',
]) ?>

<?php if (count($grouped) > 1): ?>
    <nav class="sticky-chips" aria-label="<?= esc(lang('Site.faq.categories'), 'attr') ?>">
        <div class="container">
            <div class="filter-bar">
                <?php foreach ($grouped as $category => $items): ?>
                    <a class="filter-chip" href="#faq-<?= esc($category, 'attr') ?>">
                        <i class="bi <?= esc($faqIcons[$category] ?? 'bi-question-circle', 'attr') ?> me-2" aria-hidden="true"></i><?= esc(lang('Site.faq.category.' . $category)) ?>
                        <span class="tab-count" aria-hidden="true"><?= count($items) ?></span>
                    </a>
                <?php endforeach ?>
            </div>
        </div>
    </nav>
<?php endif ?>

<section class="section-pad bg-white">
    <div class="container">
        <div class="text-center mx-auto mb-5 section-intro">
            <?php if (trim((string) ($content['intro_label'] ?? '')) !== ''): ?>
                <span class="section-label"><?= esc($content['intro_label']) ?></span>
            <?php endif ?>
            <h2 class="section-title"><?= esc($content['intro_title'] ?? lang('Site.faq.defaultTitle')) ?></h2>
            <?php if (trim((string) ($content['intro_text'] ?? '')) !== ''): ?>
                <p class="section-subtitle mx-auto"><?= esc($content['intro_text']) ?></p>
            <?php endif ?>
        </div>

        <?php if ($grouped === []): ?>
            <p class="empty-state"><i class="bi bi-chat-square-text" aria-hidden="true"></i><?= esc(lang('Site.faq.empty')) ?></p>
        <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <?php foreach ($grouped as $category => $items): ?>
                        <section id="faq-<?= esc($category, 'attr') ?>" class="faq-group" aria-labelledby="faq-<?= esc($category, 'attr') ?>-title">
                            <header class="faq-group-head">
                                <span class="faq-group-icon" aria-hidden="true"><i class="bi <?= esc($faqIcons[$category] ?? 'bi-question-circle', 'attr') ?>"></i></span>
                                <div>
                                    <h2 class="h5 faq-group-title mb-0" id="faq-<?= esc($category, 'attr') ?>-title"><?= esc(lang('Site.faq.category.' . $category)) ?></h2>
                                    <span class="small text-muted"><?= esc(lang('Site.faq.count', [count($items)])) ?></span>
                                </div>
                            </header>
                            <?= view('information/_faq_accordion', ['items' => $items, 'accordionId' => 'faq-' . $category . '-list']) ?>
                        </section>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</section>

<section class="section-pad-sm section-alt">
    <div class="container">
        <div class="cta-band">
            <div class="d-flex align-items-center gap-3">
                <span class="cta-band-icon" aria-hidden="true"><i class="bi bi-question-diamond"></i></span>
                <div>
                    <h2 class="h4 fw-bold mb-1"><?= esc(lang('Site.faq.contactTitle')) ?></h2>
                    <p class="mb-0"><?= esc(lang('Site.faq.contactText')) ?></p>
                </div>
            </div>
            <div class="cta-band-actions">
                <a href="<?= site_url('contact') ?>" class="btn btn-light-brand"><i class="bi bi-envelope me-1" aria-hidden="true"></i><?= esc(lang('Site.programmes.contactUs')) ?></a>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
