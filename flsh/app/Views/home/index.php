<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?php
$homeContent = $homeContent ?? null;
$missingText = lang('Home.configurationMissing');
$activeSite = site_current_site();
$contentBlocks = array_values($contentBlocks ?? []);

$configList = static function (mixed $value): array {
    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
    }

    if (! is_array($value)) {
        return [];
    }

    $items = [];
    foreach ($value as $entry) {
        $entry = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string) $entry))) ?: '';
        if ($entry !== '' && ! in_array($entry, $items, true)) {
            $items[] = $entry;
        }
    }

    return $items;
};

$enabledSections = $configList($activeSite->enabled_sections ?? null);
$hasConfiguredSections = $enabledSections !== [];
$sectionEnabled = static function (string $section) use ($enabledSections): bool {
    return $enabledSections === [] || in_array($section, $enabledSections, true);
};

$blockValue = static function (mixed $block, string $field): mixed {
    if (is_array($block)) {
        return $block[$field] ?? null;
    }

    return is_object($block) && isset($block->{$field}) ? $block->{$field} : null;
};

$blocksByType = [];
foreach ($contentBlocks as $block) {
    $type = (string) $blockValue($block, 'type');
    if ($type !== '') {
        $blocksByType[$type][] = $block;
    }
}

$firstBlock = static fn (string $type): mixed => $blocksByType[$type][0] ?? null;
$blockSettings = static function (mixed $block) use ($blockValue): array {
    $settings = $blockValue($block, 'settings');
    if (is_string($settings)) {
        $decoded = json_decode($settings, true);
        $settings = is_array($decoded) ? $decoded : [];
    }

    return is_array($settings) ? $settings : [];
};

$galleryImages = static function (mixed $block) use ($blockSettings, $blockValue): array {
    $settings = $blockSettings($block);
    $images = $settings['images'] ?? null;

    if (! is_array($images)) {
        $images = preg_split('/\R+/', trim((string) $blockValue($block, 'content'))) ?: [];
    }

    $normalized = [];
    foreach ($images as $image) {
        $path = is_array($image) ? (string) ($image['path'] ?? $image['src'] ?? '') : (string) $image;
        $alt = is_array($image) ? (string) ($image['alt'] ?? $image['title'] ?? '') : '';
        $path = trim($path);

        if ($path !== '') {
            $normalized[] = ['path' => $path, 'alt' => trim($alt)];
        }
    }

    return $normalized;
};

$blockText = static function (string $type, string $field, ?string $fallback = null) use ($firstBlock, $blockValue): string {
    $block = $firstBlock($type);
    $value = $block === null ? null : $blockValue($block, $field);

    return site_text_or_placeholder(is_scalar($value) ? (string) $value : null, $fallback);
};

$optionalSectionEnabled = static function (string $section, ?string $blockType = null) use ($sectionEnabled, $hasConfiguredSections, $blocksByType): bool {
    if (! $sectionEnabled($section)) {
        return false;
    }

    return $hasConfiguredSections || ($blockType !== null && isset($blocksByType[$blockType]));
};

$heroBadge = site_text_or_placeholder($homeContent?->hero_badge ?? null, $missingText);
$heroTitle = site_text_or_placeholder($homeContent?->hero_title ?? null, $missingText);
$heroText = site_text_or_placeholder($homeContent?->hero_text ?? null, $missingText);
$heroPrimaryLabel = site_text_or_placeholder($homeContent?->hero_primary_label ?? null, $missingText);
$heroSecondaryLabel = site_text_or_placeholder($homeContent?->hero_secondary_label ?? null, $missingText);
$heroPrimaryUrl = site_public_url(site_text_or_placeholder($homeContent?->hero_primary_url ?? null, '/formations'));
$heroSecondaryUrl = site_public_url(site_text_or_placeholder($homeContent?->hero_secondary_url ?? null, '/faculte'));
$aboutLabel = site_text_or_placeholder($homeContent?->about_label ?? null, $missingText);
$aboutTitle = site_text_or_placeholder($homeContent?->about_title ?? null, $missingText);
$aboutBody = site_text_or_placeholder($homeContent?->about_body ?? null, $missingText);
$aboutButtonLabel = site_text_or_placeholder($homeContent?->about_button_label ?? null, $missingText);
$aboutButtonUrl = site_public_url(site_text_or_placeholder($homeContent?->about_button_url ?? null, '/faculte'));
$researchLabel = site_text_or_placeholder($homeContent?->research_label ?? null, $missingText);
$researchTitle = $blockText('research_labs', 'title', $homeContent?->research_title ?? null);
$researchBody = $blockText('research_labs', 'content', $homeContent?->research_body ?? null);
$researchButtonLabel = site_text_or_placeholder($homeContent?->research_button_label ?? null, $missingText);
$researchButtonUrl = site_public_url(site_text_or_placeholder($homeContent?->research_button_url ?? null, '/recherche'));
$programmesLabel = site_text_or_placeholder($homeContent?->programmes_label ?? null, $missingText);
$programmesTitle = $blockText('programmes_preview', 'title', $homeContent?->programmes_title ?? null);
$programmesText = $blockText('programmes_preview', 'content', $homeContent?->programmes_text ?? null);
$programmesButtonLabel = site_text_or_placeholder($homeContent?->programmes_button_label ?? null, lang('Home.viewProgramme'));
$programmesButtonUrl = site_public_url(site_text_or_placeholder($homeContent?->programmes_button_url ?? null, '/formations'));
$postsLabel = site_text_or_placeholder($homeContent?->posts_label ?? null, $missingText);
$postsTitle = $blockText('news_preview', 'title', $homeContent?->posts_title ?? null);
$postsText = $blockText('news_preview', 'content', $homeContent?->posts_text ?? null);
$postsButtonLabel = site_text_or_placeholder($homeContent?->posts_button_label ?? null, lang('Home.viewAll'));
$postsButtonUrl = site_public_url(site_text_or_placeholder($homeContent?->posts_button_url ?? null, '/actualites'));
$deanMessage = is_array($deanMessage ?? null) ? $deanMessage : [];
$deanParagraphs = array_values(array_filter(
    array_map(static fn (mixed $paragraph): string => trim((string) $paragraph), (array) ($deanMessage['paragraphs'] ?? [])),
    static fn (string $paragraph): bool => $paragraph !== '',
));
$deanBlockText = $blockText('dean_message', 'content', $deanParagraphs === [] ? null : implode("\n\n", $deanParagraphs));
$deanTitle = $blockText('dean_message', 'title', (string) ($deanMessage['title'] ?? lang('Site.faculty.deanLabelFallback')));
$contactCta = $firstBlock('contact_cta');
$contactCtaSettings = $contactCta === null ? [] : $blockSettings($contactCta);
$contactCtaTitle = $blockText('contact_cta', 'title', lang('Site.home.contactCtaTitle'));
$contactCtaText = $blockText('contact_cta', 'content', lang('Site.home.contactCtaText'));
$contactCtaLabel = site_text_or_placeholder((string) ($contactCtaSettings['label'] ?? $contactCtaSettings['button_label'] ?? ''), lang('Site.common.write'));
$contactCtaUrl = site_public_url((string) ($contactCtaSettings['url'] ?? $contactCtaSettings['button_url'] ?? '/contact'));
$staffPreviewTitle = $blockText('staff_preview', 'title', lang('Site.pageTitles.staff'));
$staffPreviewText = $blockText('staff_preview', 'content', '');
$heroSlides = array_values($heroSlides ?? []);
$slideValue = static function (mixed $slide, string $field): ?string {
    if (is_array($slide)) {
        return isset($slide[$field]) ? (string) $slide[$field] : null;
    }

    return isset($slide->{$field}) ? (string) $slide->{$field} : null;
};

if ($heroSlides === []) {
    $heroSlides = [[
        'image_path' => 'assets/images/hero/campus-walkway.jpg',
        'alt_text'   => lang('Home.defaultHeroAlt'),
    ]];
}

$heroOverlayOpacity = (float) ($siteSettings['home.hero_overlay_opacity'] ?? 0.88);
if ($heroOverlayOpacity > 1) {
    $heroOverlayOpacity /= 100;
}

$heroOverlayOpacity = max(0.35, min(0.95, $heroOverlayOpacity));
$hasCarouselControls = count($heroSlides) > 1;
?>
<?php if ($sectionEnabled('hero')): ?>
<section class="hero" style="--hero-overlay-opacity: <?= esc(number_format($heroOverlayOpacity, 2, '.', ''), 'attr') ?>">
    <div
        id="homeHeroCarousel"
        class="hero-carousel carousel slide carousel-fade"
        data-site-hero-carousel
        data-bs-interval="7000"
        data-bs-keyboard="true"
        data-bs-touch="true"
        <?php if ($hasCarouselControls): ?>
            data-bs-ride="carousel"
        <?php endif ?>
        aria-label="<?= esc(lang('Home.heroCarouselLabel'), 'attr') ?>"
    >
        <?php if ($hasCarouselControls): ?>
            <div class="carousel-indicators">
                <?php foreach ($heroSlides as $index => $slide): ?>
                    <button
                        type="button"
                        data-bs-target="#homeHeroCarousel"
                        data-bs-slide-to="<?= esc((string) $index, 'attr') ?>"
                        class="<?= $index === 0 ? 'active' : '' ?>"
                        aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                        aria-label="<?= esc(lang('Home.showHeroSlide', [$index + 1]), 'attr') ?>"
                    ></button>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <div class="carousel-inner">
            <?php foreach ($heroSlides as $index => $slide): ?>
                <?php
                $imagePath = site_media_url($slideValue($slide, 'image_path'), 'assets/images/hero/campus-walkway.jpg');
                $altText = site_text_or_placeholder($slideValue($slide, 'alt_text'), lang('Home.heroImageFallbackAlt'));
                ?>
                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                    <img
                        class="hero-slide-image"
                        src="<?= esc($imagePath, 'attr') ?>"
                        alt="<?= esc($altText, 'attr') ?>"
                        loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                        <?= $index === 0 ? 'fetchpriority="high"' : '' ?>
                        decoding="async"
                    >
                </div>
            <?php endforeach ?>
        </div>
        <?php if ($hasCarouselControls): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#homeHeroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden"><?= esc(lang('Home.previousHeroSlide')) ?></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#homeHeroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden"><?= esc(lang('Home.nextHeroSlide')) ?></span>
            </button>
        <?php endif ?>
    </div>

    <div class="hero-shape hero-shape-lg"></div>
    <div class="hero-shape hero-shape-sm"></div>
    <div class="container position-relative">
        <div class="row">
            <div class="col-lg-7">
                <span class="hero-badge"><?= esc($heroBadge) ?></span>
                <h1><?= nl2br(esc($heroTitle)) ?></h1>
                <p class="lead"><?= esc($heroText) ?></p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= esc($heroPrimaryUrl, 'attr') ?>" class="btn-hero-primary">
                        <?= esc($heroPrimaryLabel) ?>
                    </a>
                    <a href="<?= esc($heroSecondaryUrl, 'attr') ?>" class="btn-hero-outline">
                        <?= esc($heroSecondaryLabel) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif ?>

<?php if ($sectionEnabled('statistics') && $mainStats !== []): ?>
    <section class="stats-bar">
        <div class="container">
            <div class="row text-center">
                <?php foreach ($mainStats as $index => $stat): ?>
                    <div class="col-lg-3 col-6 <?= $index > 0 ? 'd-flex align-items-center justify-content-center' : '' ?>">
                        <?php if ($index > 0): ?>
                            <div class="stat-divider d-none d-lg-block"></div>
                        <?php endif ?>
                        <div>
                            <div class="stat-number" data-count="<?= esc((string) $stat->value, 'attr') ?>">
                                <span class="stat-value">0</span><span class="stat-suffix"><?= esc($stat->suffix ?? '') ?></span>
                            </div>
                            <div class="stat-label"><?= esc($stat->label) ?></div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </section>
<?php endif ?>

<?php if (! $hasConfiguredSections || $sectionEnabled('about')): ?>
<section class="section-pad bg-white">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="section-label"><?= esc($aboutLabel) ?></span>
                <h2 class="section-title"><?= esc($aboutTitle) ?></h2>
                <div class="divider-green"></div>
                <?php foreach (site_paragraphs($aboutBody) as $paragraph): ?>
                    <p><?= esc($paragraph) ?></p>
                <?php endforeach ?>
                <?php if ($homeContent !== null): ?>
                    <a href="<?= esc($aboutButtonUrl, 'attr') ?>" class="btn btn-primary-green">
                        <?= esc($aboutButtonLabel) ?>
                    </a>
                <?php endif ?>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <?php foreach ($highlights as $highlight): ?>
                        <div class="col-6">
                            <article class="card-faculte text-center p-3 h-100">
                                <div class="card-icon mx-auto">
                                    <i class="bi <?= esc($highlight->icon, 'attr') ?>"></i>
                                </div>
                                <p class="mb-0 fw-semibold small"><?= esc($highlight->title) ?></p>
                                <?php if ($highlight->description): ?>
                                    <p class="small text-muted mb-0 mt-2"><?= esc($highlight->description) ?></p>
                                <?php endif ?>
                            </article>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif ?>

<?php if ($optionalSectionEnabled('dean_message', 'dean_message')): ?>
<section class="section-pad section-alt">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-4 text-center">
                <?php if (! empty($deanMessage['photo'])): ?>
                    <img src="<?= esc(site_media_url((string) $deanMessage['photo']), 'attr') ?>" alt="<?= esc((string) ($deanMessage['name'] ?? lang('Site.faculty.deanPhotoAlt')), 'attr') ?>" class="dean-photo">
                <?php else: ?>
                    <div class="card-icon mx-auto mb-3"><i class="bi bi-person-vcard"></i></div>
                <?php endif ?>
                <?php if (! empty($deanMessage['name'])): ?>
                    <p class="fw-bold mb-1"><?= esc((string) $deanMessage['name']) ?></p>
                <?php endif ?>
                <?php if (! empty($deanMessage['role']) || ! empty($deanMessage['specialty'])): ?>
                    <p class="text-muted small mb-0"><?= esc(trim((string) ($deanMessage['role'] ?? '') . ' · ' . (string) ($deanMessage['specialty'] ?? ''), " ·")) ?></p>
                <?php endif ?>
            </div>
            <div class="col-lg-8">
                <span class="section-label"><?= esc((string) ($deanMessage['label'] ?? lang('Site.faculty.deanLabelFallback'))) ?></span>
                <h2 class="section-title"><?= esc($deanTitle) ?></h2>
                <div class="divider-green"></div>
                <?php foreach (site_paragraphs($deanBlockText) as $paragraph): ?>
                    <p><?= esc($paragraph) ?></p>
                <?php endforeach ?>
                <?php if (! empty($deanMessage['signature'])): ?>
                    <p class="fw-semibold mb-0"><?= esc((string) $deanMessage['signature']) ?></p>
                <?php endif ?>
            </div>
        </div>
    </div>
</section>
<?php endif ?>

<?php if ($sectionEnabled('programmes_preview') && $programmeGroups !== []): ?>
<section class="section-pad section-alt">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 640px">
            <span class="section-label"><?= esc($programmesLabel) ?></span>
            <h2 class="section-title"><?= esc($programmesTitle) ?></h2>
            <p class="section-subtitle mx-auto">
                <?= esc($programmesText) ?>
            </p>
        </div>
        <div class="row g-4">
            <?php foreach ($programmeGroups as $group): ?>
                <div class="col-lg-4">
                    <article class="card-faculte h-100">
                        <div class="card-icon"><i class="bi <?= esc($group['icon'], 'attr') ?>"></i></div>
                        <h3 class="h5"><?= esc($group['label']) ?></h3>
                        <p class="text-muted small mb-2">
                            <?= esc(($group['duration'] ?? '') . ' · ' . $group['orientation']) ?>
                        </p>
                        <ul class="small text-body mb-3 ps-3">
                            <?php foreach ($group['programmes'] as $programme): ?>
                                <li><?= esc($programme->title) ?></li>
                            <?php endforeach ?>
                        </ul>
                        <a href="<?= esc($programmesButtonUrl, 'attr') ?>" class="btn btn-outline-green btn-sm">
                            <?= esc($programmesButtonLabel) ?>
                        </a>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?php endif ?>

<?php if ($sectionEnabled('research_labs')): ?>
<section class="section-pad bg-white">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <span class="section-label"><?= esc($researchLabel) ?></span>
                <h2 class="section-title"><?= esc($researchTitle) ?></h2>
                <div class="divider-green"></div>
                <p><?= esc($researchBody) ?></p>
                <div class="d-flex flex-wrap gap-4 mb-4">
                    <?php foreach ($researchStats as $stat): ?>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-text text-success fs-4"></i>
                            <div>
                                <div class="fw-bold"><?= esc((string) $stat->value . ($stat->suffix ?? '')) ?></div>
                                <div class="small text-muted"><?= esc($stat->label) ?></div>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
                <?php if ($homeContent !== null): ?>
                    <a href="<?= esc($researchButtonUrl, 'attr') ?>" class="btn btn-primary-green">
                        <?= esc($researchButtonLabel) ?>
                    </a>
                <?php endif ?>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <?php foreach ($featuredLaboratories as $lab): ?>
                        <div class="col-md-6">
                            <article class="lab-card h-100">
                                <i class="bi <?= esc($lab->icon ?? 'bi-diagram-3', 'attr') ?> text-success fs-4 mb-2 d-block"></i>
                                <p class="fw-bold mb-0"><?= esc($lab->abbreviation) ?></p>
                                <p class="small text-muted mb-0"><?= esc($lab->name) ?></p>
                            </article>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif ?>

<?php if ($optionalSectionEnabled('staff_preview', 'staff_preview') && ($featuredStaff ?? []) !== []): ?>
<section class="section-pad section-alt">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 640px">
            <span class="section-label"><?= esc(lang('Site.pageTitles.staff')) ?></span>
            <h2 class="section-title"><?= esc($staffPreviewTitle) ?></h2>
            <?php if ($staffPreviewText !== ''): ?>
                <p class="section-subtitle mx-auto"><?= esc($staffPreviewText) ?></p>
            <?php endif ?>
        </div>
        <div class="row g-4">
            <?php foreach ($featuredStaff as $member): ?>
                <div class="col-md-6 col-xl-3">
                    <article class="staff-card h-100">
                        <?php if ($member->photo): ?>
                            <img src="<?= esc(site_media_url($member->photo), 'attr') ?>" alt="<?= esc($member->name, 'attr') ?>" class="staff-photo">
                        <?php else: ?>
                            <div class="card-icon mx-auto"><i class="bi bi-person-badge"></i></div>
                        <?php endif ?>
                        <h3 class="staff-name"><?= esc($member->name) ?></h3>
                        <p class="staff-grade mb-1"><?= esc($member->grade ?? site_staff_category_label($member->category)) ?></p>
                        <?php if ($member->specialty): ?>
                            <p class="staff-specialty mb-0"><?= esc($member->specialty) ?></p>
                        <?php endif ?>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?php endif ?>

<?php if ($sectionEnabled('news_preview') && $featuredPosts !== []): ?>
<section class="section-pad section-alt">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-5 gap-3">
            <div>
                <span class="section-label"><?= esc($postsLabel) ?></span>
                <h2 class="section-title mb-0"><?= esc($postsTitle) ?></h2>
                <p class="section-subtitle mb-0 mt-2">
                    <?= esc($postsText) ?>
                </p>
            </div>
            <a href="<?= esc($postsButtonUrl, 'attr') ?>" class="btn btn-outline-green d-none d-md-inline-block">
                <?= esc($postsButtonLabel) ?>
            </a>
        </div>
        <div class="row g-4">
            <?php foreach ($featuredPosts as $post): ?>
                <div class="col-lg-4">
                    <article class="news-card h-100">
                        <?php if ($post->cover_image): ?>
                            <img src="<?= esc(site_media_url($post->cover_image), 'attr') ?>" class="news-card-cover" alt="<?= esc($post->title, 'attr') ?>">
                        <?php endif ?>
                        <div class="news-card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                                <span class="badge-news badge-<?= esc(site_post_category($post->type), 'attr') ?>"><?= esc(site_post_type_label($post->type)) ?></span>
                                <span class="small text-muted"><?= esc(site_format_date($post->published_at)) ?></span>
                            </div>
                            <h3 class="h6 fw-bold"><?= esc($post->title) ?></h3>
                            <p class="small text-muted"><?= esc($post->excerpt) ?></p>
                            <a href="<?= site_url('actualites/' . $post->slug) ?>" class="small fw-semibold text-success"><?= esc(lang('Home.readMore')) ?></a>
                        </div>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?php endif ?>

<?php $customTextBlocks = array_values($blocksByType['custom_text'] ?? []); ?>
<?php if ($sectionEnabled('custom_text') && $customTextBlocks !== []): ?>
<section class="section-pad bg-white">
    <div class="container">
        <div class="row g-4">
            <?php foreach ($customTextBlocks as $block): ?>
                <?php
                $customTitle = site_text_or_placeholder(is_scalar($blockValue($block, 'title')) ? (string) $blockValue($block, 'title') : null, '');
                $customContent = site_text_or_placeholder(is_scalar($blockValue($block, 'content')) ? (string) $blockValue($block, 'content') : null, '');
                ?>
                <div class="col-lg-6">
                    <article class="h-100">
                        <?php if ($customTitle !== ''): ?>
                            <h2 class="section-title h3"><?= esc($customTitle) ?></h2>
                            <div class="divider-green"></div>
                        <?php endif ?>
                        <?php foreach (site_paragraphs($customContent) as $paragraph): ?>
                            <p><?= esc($paragraph) ?></p>
                        <?php endforeach ?>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?php endif ?>

<?php $galleryBlocks = array_values($blocksByType['image_gallery'] ?? []); ?>
<?php if ($sectionEnabled('image_gallery') && $galleryBlocks !== []): ?>
    <?php foreach ($galleryBlocks as $block): ?>
        <?php $images = $galleryImages($block); ?>
        <?php if ($images !== []): ?>
            <section class="section-pad section-alt">
                <div class="container">
                    <?php $galleryTitle = site_text_or_placeholder(is_scalar($blockValue($block, 'title')) ? (string) $blockValue($block, 'title') : null, lang('Site.home.gallery')); ?>
                    <div class="text-center mx-auto mb-5" style="max-width: 640px">
                        <span class="section-label"><?= esc(lang('Site.home.gallery')) ?></span>
                        <h2 class="section-title"><?= esc($galleryTitle) ?></h2>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($images as $image): ?>
                            <div class="col-md-6 col-xl-4">
                                <img src="<?= esc(site_media_url($image['path']), 'attr') ?>" alt="<?= esc($image['alt'] ?: $galleryTitle, 'attr') ?>" class="rounded w-100 h-100 object-fit-cover" loading="lazy" decoding="async" style="min-height: 220px; max-height: 280px;">
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </section>
        <?php endif ?>
    <?php endforeach ?>
<?php endif ?>

<?php if ($optionalSectionEnabled('contact_cta', 'contact_cta')): ?>
<section class="section-pad bg-white">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <span class="section-label">Contact</span>
                <h2 class="section-title"><?= esc($contactCtaTitle) ?></h2>
                <?php foreach (site_paragraphs($contactCtaText) as $paragraph): ?>
                    <p class="section-subtitle mx-auto"><?= esc($paragraph) ?></p>
                <?php endforeach ?>
                <a href="<?= esc($contactCtaUrl, 'attr') ?>" class="btn btn-primary-green">
                    <?= esc($contactCtaLabel) ?>
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif ?>
<?= $this->endSection() ?>
