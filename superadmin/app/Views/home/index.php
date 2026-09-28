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
$defaultSectionOrder = [
    'hero',
    'quick_links',
    'statistics',
    'about',
    'programmes_preview',
    'news_preview',
    'research_labs',
    'dean_message',
    'staff_preview',
    'custom_text',
    'image_gallery',
    'contact_cta',
];
$hasConfiguredSections = $enabledSections !== [];
$sectionRenderOrder = $hasConfiguredSections
    ? array_values(array_filter(
        $enabledSections,
        static fn (string $key): bool => in_array($key, $defaultSectionOrder, true),
    ))
    : $defaultSectionOrder;
// Legacy: highlights used to ride inside about — treat as about if stored alone.
if (in_array('highlights', $enabledSections, true) && ! in_array('about', $sectionRenderOrder, true)) {
    $sectionRenderOrder[] = 'about';
}
$sectionEnabled = static function (string $section) use ($enabledSections, $hasConfiguredSections): bool {
    if (! $hasConfiguredSections) {
        return true;
    }

    if ($section === 'about') {
        return in_array('about', $enabledSections, true) || in_array('highlights', $enabledSections, true);
    }

    return in_array($section, $enabledSections, true);
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
$heroIndicatorSize = (string) ($siteSettings['home.hero_indicator_size'] ?? service('settingsService')->get('home.hero_indicator_size', '0.75'));
if (! in_array($heroIndicatorSize, ['0.75', '1', '1.25', '1.5'], true)) {
    $heroIndicatorSize = '0.75';
}
$slideValue = static function (mixed $slide, string $field): ?string {
    if (is_array($slide)) {
        return isset($slide[$field]) ? (string) $slide[$field] : null;
    }

    return isset($slide->{$field}) ? (string) $slide->{$field} : null;
};

$homePageService = service('homePageService');
$resolveSlideCta = static function (mixed $slide, string $which) use ($slideValue, $homePageService): ?array {
    $target = (string) ($slideValue($slide, $which . '_cta_target') ?? 'none');
    $label = trim((string) ($slideValue($slide, $which . '_cta_label') ?? ''));
    $url = $homePageService->resolveCtaUrl($target, $slideValue($slide, $which . '_cta_url'));
    if ($url === null || $label === '') {
        return null;
    }

    return ['label' => $label, 'url' => $url];
};

if ($heroSlides === []) {
    $heroSlides = [[
        'image_path'           => 'assets/images/logo-placeholder.png',
        'alt_text'             => lang('Home.defaultHeroAlt'),
        'badge'                => $homeContent?->hero_badge,
        'title'                => $homeContent?->hero_title,
        'text'                 => $homeContent?->hero_text,
        'primary_cta_target'   => 'custom',
        'primary_cta_label'    => $homeContent?->hero_primary_label,
        'primary_cta_url'      => $homeContent?->hero_primary_url ?? '/formations',
        'secondary_cta_target' => 'custom',
        'secondary_cta_label'  => $homeContent?->hero_secondary_label,
        'secondary_cta_url'    => $homeContent?->hero_secondary_url ?? '/faculte',
    ]];
}

$hasCarouselControls = count($heroSlides) > 1;
$sectionOrderIndex = array_flip($sectionRenderOrder);
$sectionCssOrder = static function (string $key) use ($sectionOrderIndex): int {
    return (int) ($sectionOrderIndex[$key] ?? 99);
};
$sectionBand = static function (string $key) use ($sectionCssOrder): string {
    return ($sectionCssOrder($key) % 2) === 0 ? 'bg-white' : 'section-alt';
};
$highlights = array_values($highlights ?? []);
$siteSettings = $siteSettings ?? service('settingsService')->all();
$contactAddress = trim((string) site_contact_address($siteSettings));
$contactHours = trim((string) ($siteSettings['contact.hours'] ?? ''));
$contactPhone = trim((string) ($siteSettings['contact.phone'] ?? ''));
$quickLinks = [
    ['key' => 'programmes', 'icon' => 'bi-mortarboard', 'url' => site_url('formations')],
    ['key' => 'research', 'icon' => 'bi-search', 'url' => site_url('recherche')],
    ['key' => 'posts', 'icon' => 'bi-calendar-event', 'url' => site_url('actualites')],
    ['key' => 'contact', 'icon' => 'bi-envelope', 'url' => site_url('contact')],
];
?>
<div class="home-sections-stack">
<?php if ($sectionEnabled('hero')): ?>
<section class="hero" style="order: <?= $sectionCssOrder('hero') ?>; --hero-overlay-opacity: 0.30; --hero-indicator-size: <?= esc($heroIndicatorSize, 'attr') ?>rem">
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
                $imagePath = site_media_url($slideValue($slide, 'image_path'), 'assets/images/logo-placeholder.png');
                $altText = site_text_or_placeholder($slideValue($slide, 'alt_text'), lang('Home.heroImageFallbackAlt'));
                $slideBadge = site_text_or_placeholder($slideValue($slide, 'badge'), site_text_or_placeholder($homeContent?->hero_badge ?? null, $missingText));
                $slideTitle = site_text_or_placeholder($slideValue($slide, 'title'), site_text_or_placeholder($homeContent?->hero_title ?? null, $missingText));
                $slideText = site_text_or_placeholder($slideValue($slide, 'text'), site_text_or_placeholder($homeContent?->hero_text ?? null, $missingText));
                $primaryCta = $resolveSlideCta($slide, 'primary');
                $secondaryCta = $resolveSlideCta($slide, 'secondary');
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
                    <div class="hero-slide-caption">
                        <div class="container position-relative">
                            <div class="row">
                                <div class="col-lg-7">
                                    <span class="hero-badge"><?= esc($slideBadge) ?></span>
                                    <h1><?= nl2br(esc($slideTitle)) ?></h1>
                                    <p class="lead"><?= esc($slideText) ?></p>
                                    <?php if ($primaryCta !== null || $secondaryCta !== null): ?>
                                        <div class="hero-actions">
                                            <?php if ($primaryCta !== null): ?>
                                                <a href="<?= esc($primaryCta['url'], 'attr') ?>" class="btn-hero-primary"><?= esc($primaryCta['label']) ?></a>
                                            <?php endif ?>
                                            <?php if ($secondaryCta !== null): ?>
                                                <a href="<?= esc($secondaryCta['url'], 'attr') ?>" class="btn-hero-outline"><?= esc($secondaryCta['label']) ?></a>
                                            <?php endif ?>
                                        </div>
                                    <?php endif ?>
                                </div>
                            </div>
                        </div>
                    </div>
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
</section>
<?php endif ?>

<?php if ($sectionEnabled('quick_links')): ?>
    <section class="quick-links" style="order: <?= $sectionCssOrder('quick_links') ?>" aria-label="<?= esc(lang('Site.home.quickLinksLabel'), 'attr') ?>">
        <div class="container">
            <div class="row g-3">
                <?php foreach ($quickLinks as $link): ?>
                    <div class="col-6 col-lg-3">
                        <a href="<?= esc($link['url'], 'attr') ?>" class="quick-link h-100">
                            <span class="quick-link-icon" aria-hidden="true"><i class="bi <?= esc($link['icon'], 'attr') ?>"></i></span>
                            <span>
                                <span class="quick-link-title"><?= esc(lang('Site.home.quickLinks.' . $link['key'] . '.title')) ?></span>
                                <span class="quick-link-text"><?= esc(lang('Site.home.quickLinks.' . $link['key'] . '.text')) ?></span>
                            </span>
                        </a>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </section>
<?php endif ?>

<?php if ($sectionEnabled('statistics') && $mainStats !== []): ?>
    <section class="stats-bar" style="order: <?= $sectionCssOrder('statistics') ?>">
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
<section id="presentation" class="section-pad <?= esc($sectionBand('about'), 'attr') ?>" style="order: <?= $sectionCssOrder('about') ?>">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="<?= $highlights === [] ? 'col-lg-8 mx-auto' : 'col-lg-6' ?>">
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
            <?php if ($highlights !== []): ?>
                <div class="col-lg-6">
                    <div class="row g-3">
                        <?php foreach ($highlights as $highlight): ?>
                            <div class="col-6">
                                <article class="card-faculte text-center p-3 h-100">
                                    <div class="card-icon mx-auto"><i class="bi <?= esc((string) ($highlight->icon ?? 'bi-star'), 'attr') ?>"></i></div>
                                    <p class="mb-0 fw-semibold small"><?= esc((string) ($highlight->title ?? '')) ?></p>
                                </article>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            <?php endif ?>
        </div>
    </div>
</section>
<?php endif ?>

<?php if ($optionalSectionEnabled('dean_message', 'dean_message')): ?>
<section class="section-pad <?= esc($sectionBand('dean_message'), 'attr') ?>" style="order: <?= $sectionCssOrder('dean_message') ?>">
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
                <blockquote class="dean-quote">
                    <?php foreach (site_paragraphs($deanBlockText) as $paragraph): ?>
                        <p><?= esc($paragraph) ?></p>
                    <?php endforeach ?>
                </blockquote>
                <?php if (! empty($deanMessage['signature'])): ?>
                    <p class="dean-signature fw-semibold mb-0"><?= esc((string) $deanMessage['signature']) ?></p>
                <?php endif ?>
            </div>
        </div>
    </div>
</section>
<?php endif ?>

<?php if ($sectionEnabled('programmes_preview') && $programmeGroups !== []): ?>
<section class="section-pad <?= esc($sectionBand('programmes_preview'), 'attr') ?>" style="order: <?= $sectionCssOrder('programmes_preview') ?>">
    <div class="container">
        <div class="section-head">
            <div class="text-measure">
                <span class="section-label"><?= esc($programmesLabel) ?></span>
                <h2 class="section-title"><?= esc($programmesTitle) ?></h2>
                <p class="section-subtitle">
                    <?= esc($programmesText) ?>
                </p>
            </div>
            <a href="<?= esc($programmesButtonUrl, 'attr') ?>" class="btn btn-outline-green d-none d-md-inline-flex">
                <?= esc($programmesButtonLabel) ?>
            </a>
        </div>
        <div class="row g-4">
            <?php foreach ($programmeGroups as $group): ?>
                <div class="col-md-6 col-lg-4">
                    <article class="card-faculte h-100 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div class="card-icon mb-0"><i class="bi <?= esc($group['icon'], 'attr') ?>"></i></div>
                            <span class="badge-level"><?= esc($group['label']) ?></span>
                        </div>
                        <h3 class="h5 mb-1"><?= esc($group['orientation']) ?></h3>
                        <p class="text-muted small mb-3">
                            <?= esc(trim(($group['duration'] ?? '') . ' · ' . lang('Site.home.programmesCount', [count($group['programmes'])]), ' ·')) ?>
                        </p>
                        <ul class="small text-body mb-3 ps-3">
                            <?php foreach (array_slice($group['programmes'], 0, 4) as $programme): ?>
                                <li><a href="<?= site_url('formations/' . $programme->slug) ?>" class="link-body-emphasis text-decoration-none"><?= esc($programme->title) ?></a></li>
                            <?php endforeach ?>
                        </ul>
                        <a href="<?= esc($programmesButtonUrl, 'attr') ?>" class="btn btn-outline-green btn-sm mt-auto align-self-start">
                            <?= esc(lang('Home.viewProgramme')) ?>
                        </a>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?php endif ?>

<?php if ($sectionEnabled('research_labs')): ?>
<section class="section-pad <?= esc($sectionBand('research_labs'), 'attr') ?>" style="order: <?= $sectionCssOrder('research_labs') ?>">
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
                            <i class="bi bi-file-earmark-text text-brand fs-4"></i>
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
                                <i class="bi <?= esc($lab->icon ?? 'bi-diagram-3', 'attr') ?> text-brand fs-4 mb-2 d-block"></i>
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
<section class="section-pad <?= esc($sectionBand('staff_preview'), 'attr') ?>" style="order: <?= $sectionCssOrder('staff_preview') ?>">
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
                        <?php $staffPhoto = site_person_media($member->photo ?? null); ?>
                        <?php if ($staffPhoto !== null): ?>
                            <img src="<?= esc(site_media_url($staffPhoto), 'attr') ?>" alt="<?= esc($member->name, 'attr') ?>" class="staff-photo">
                        <?php else: ?>
                            <div class="card-icon mx-auto" aria-hidden="true"><?= esc(site_initials($member->name)) ?></div>
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
<section class="section-pad <?= esc($sectionBand('news_preview'), 'attr') ?>" style="order: <?= $sectionCssOrder('news_preview') ?>">
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
                <?php $cover = site_person_media($post->cover_image ?? null); ?>
                <div class="col-md-6 col-lg-4">
                    <article class="news-card h-100">
                        <?php if ($cover !== null): ?>
                            <img src="<?= esc(site_media_url($cover), 'attr') ?>" class="news-card-cover" alt="<?= esc($post->title, 'attr') ?>">
                        <?php endif ?>
                        <div class="news-card-body">
                            <?php $isEvent = $post->type === 'event'; ?>
                            <?php $postDate = $isEvent ? ($post->event_starts_at ?? $post->published_at) : $post->published_at; ?>
                            <div class="news-card-meta">
                                <?php $dateParts = $isEvent ? site_date_parts($postDate) : null; ?>
                                <?php if ($dateParts !== null): ?>
                                    <time class="date-block" datetime="<?= esc($dateParts['iso'], 'attr') ?>">
                                        <span class="date-block-day"><?= esc($dateParts['day']) ?></span>
                                        <span class="date-block-month"><?= esc($dateParts['month']) ?></span>
                                    </time>
                                <?php else: ?>
                                    <span class="small text-muted"><?= esc(site_format_date($postDate, false)) ?></span>
                                <?php endif ?>
                                <span class="badge-news badge-<?= esc(site_post_category($post->type), 'attr') ?>"><?= esc(site_post_type_label($post->type)) ?></span>
                            </div>
                            <h3 class="h6 fw-bold"><?= esc($post->title) ?></h3>
                            <p class="small text-muted"><?= esc($post->excerpt) ?></p>
                            <a href="<?= site_url('actualites/' . $post->slug) ?>" class="stretched-link small fw-semibold text-brand text-decoration-none"><?= esc(lang('Home.readMore')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
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
<section class="section-pad <?= esc($sectionBand('custom_text'), 'attr') ?>" style="order: <?= $sectionCssOrder('custom_text') ?>">
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
            <section class="section-pad <?= esc($sectionBand('image_gallery'), 'attr') ?>" style="order: <?= $sectionCssOrder('image_gallery') ?>">
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
<section class="section-pad <?= esc($sectionBand('contact_cta'), 'attr') ?>" style="order: <?= $sectionCssOrder('contact_cta') ?>">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="section-label"><?= esc(lang('Site.home.contactLabel')) ?></span>
                <h2 class="section-title"><?= esc($contactCtaTitle) ?></h2>
                <div class="divider-green"></div>
                <?php foreach (site_paragraphs($contactCtaText) as $paragraph): ?>
                    <p class="section-subtitle"><?= esc($paragraph) ?></p>
                <?php endforeach ?>
                <a href="<?= esc($contactCtaUrl, 'attr') ?>" class="btn btn-primary-green">
                    <?= esc($contactCtaLabel) ?>
                </a>
            </div>
            <?php if ($contactAddress !== '' || $contactHours !== '' || $contactPhone !== ''): ?>
                <div class="col-lg-6">
                    <div class="d-grid gap-3">
                        <?php if ($contactAddress !== ''): ?>
                            <div class="info-card">
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                <div><strong><?= esc(lang('Site.contact.address')) ?></strong><span><?= esc($contactAddress) ?></span></div>
                            </div>
                        <?php endif ?>
                        <?php if ($contactHours !== ''): ?>
                            <div class="info-card">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                <div><strong><?= esc(lang('Site.contact.hours')) ?></strong><span><?= esc($contactHours) ?></span></div>
                            </div>
                        <?php endif ?>
                        <?php if ($contactPhone !== ''): ?>
                            <div class="info-card">
                                <i class="bi bi-telephone" aria-hidden="true"></i>
                                <div><strong><?= esc(lang('Site.contact.phone')) ?></strong><span><?= esc($contactPhone) ?></span></div>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            <?php endif ?>
        </div>
    </div>
</section>
<?php endif ?>
</div>
<?= $this->endSection() ?>
