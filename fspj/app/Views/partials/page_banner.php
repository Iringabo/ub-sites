<?php
$pageTitle       = site_text_or_placeholder($pageTitle ?? null, lang('Site.pageTitles.default'));
$breadcrumbTitle = site_text_or_placeholder($breadcrumbTitle ?? null, $pageTitle);
$pageSubtitle    = trim((string) ($pageSubtitle ?? ''));
$bannerImage     = trim((string) ($bannerImage ?? ''));
$isCrest         = $bannerImage === ''
    || str_contains($bannerImage, 'logo-placeholder.png')
    || str_contains($bannerImage, 'logo-placeholder.svg');
$usePhoto        = ! $isCrest;
$breadcrumbs     = $breadcrumbs ?? [
    ['label' => lang('Site.common.home'), 'url' => site_url('/')],
    ['label' => $breadcrumbTitle],
];
$style = $usePhoto
    ? ' style="' . esc("background-image: url('" . site_css_url($bannerImage) . "');", 'attr') . '"'
    : '';
?>
<section class="page-banner<?= $usePhoto ? ' page-banner-photo' : '' ?>"<?= $style ?>>
    <div class="container">
        <nav aria-label="<?= esc(lang('Site.common.breadcrumbs'), 'attr') ?>">
            <ol class="breadcrumb mb-2">
                <?php foreach ($breadcrumbs as $index => $breadcrumb): ?>
                    <?php $isLast = $index === array_key_last($breadcrumbs); ?>
                    <?php if (! $isLast && isset($breadcrumb['url'])): ?>
                        <li class="breadcrumb-item"><a href="<?= esc($breadcrumb['url'], 'attr') ?>"><?= esc($breadcrumb['label'] ?? '') ?></a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item active" aria-current="page"><?= esc($breadcrumb['label'] ?? $breadcrumbTitle) ?></li>
                    <?php endif ?>
                <?php endforeach ?>
            </ol>
        </nav>
        <h1><?= esc($pageTitle) ?></h1>
        <?php if ($pageSubtitle !== ''): ?>
            <p class="subtitle mb-0"><?= esc($pageSubtitle) ?></p>
        <?php endif ?>
    </div>
</section>
