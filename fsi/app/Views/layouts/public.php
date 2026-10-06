<?php
$siteSettings = $siteSettings ?? service('settingsService')->all();
$pageTitle    = site_text_or_placeholder($title ?? null, site_text_or_placeholder($siteSettings['seo.default_title'] ?? null));
$pageDesc     = site_text_or_placeholder($description ?? null, site_text_or_placeholder($siteSettings['seo.default_description'] ?? null));
$ogImage      = site_media_url($ogImage ?? ($siteSettings['seo.og_image'] ?? 'assets/images/logo-placeholder.png'));
$localeUrls   = service('localeUrls');
$locale       = site_current_locale();
$canonical    = $localeUrls->canonical($locale);
$alternates   = $localeUrls->alternates();
$activeSite   = service('siteResolver')->activeSite();
$brandTokens  = service('siteTheme')->tokensForSite($activeSite);
$themeColor   = service('siteTheme')->normalizeHex($siteSettings['seo.theme_color'] ?? null) ?? $brandTokens['brand'];
$themeName    = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($activeSite->theme ?? 'default'))) ?: 'default';
$themeConfig  = json_decode((string) ($activeSite->theme_config ?? ''), true);
$themeLayout  = is_array($themeConfig) ? preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($themeConfig['layout'] ?? 'classic'))) : 'classic';
$themeLayout  = $themeLayout ?: 'classic';
$jsonLd       = service('structuredData')->render(array_merge(
    [service('structuredData')->organization($siteSettings)],
    array_values(array_filter($structuredData ?? [])),
));
$analytics    = service('analytics')->config($siteSettings);
if ($analytics !== null) {
    service('analytics')->allowInCsp($analytics);
}
?>
<!doctype html>
<html lang="<?= esc($locale, 'attr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?></title>
    <meta name="description" content="<?= esc($pageDesc) ?>">
    <meta name="theme-color" content="<?= esc($themeColor, 'attr') ?>">
    <?php if (! empty($noindex)): ?>
        <meta name="robots" content="noindex, follow">
    <?php endif ?>
    <link rel="canonical" href="<?= esc($canonical, 'attr') ?>">
    <?php foreach ($alternates as $hreflang => $alternateUrl): ?>
        <link rel="alternate" hreflang="<?= esc($hreflang, 'attr') ?>" href="<?= esc($alternateUrl, 'attr') ?>">
    <?php endforeach ?>
    <link rel="icon" type="image/png" href="<?= esc(site_media_url($siteSettings['assets.logo'] ?? null), 'attr') ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="<?= esc(site_og_locale(), 'attr') ?>">
    <meta property="og:locale:alternate" content="<?= esc($locale === 'en' ? 'fr_FR' : 'en_US', 'attr') ?>">
    <meta property="og:site_name" content="<?= esc(site_text_or_placeholder($siteSettings['institution.faculty_name'] ?? null), 'attr') ?>">
    <meta property="og:title" content="<?= esc($pageTitle) ?>">
    <meta property="og:description" content="<?= esc($pageDesc) ?>">
    <meta property="og:url" content="<?= esc($canonical, 'attr') ?>">
    <meta property="og:image" content="<?= esc($ogImage, 'attr') ?>">
    <meta name="twitter:card" content="summary">
    <link href="<?= site_asset_url('assets/vendor/inter/inter.css') ?>" rel="stylesheet">
    <link href="<?= site_asset_url('assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= site_asset_url('assets/vendor/bootstrap-icons/bootstrap-icons.css') ?>" rel="stylesheet">
    <link href="<?= site_asset_url('assets/css/style.css') ?>" rel="stylesheet">
    <style {csp-style-nonce}><?= service('siteTheme')->cssVariables($brandTokens) ?></style>
    <script type="application/ld+json" {csp-script-nonce}><?= $jsonLd ?></script>
</head>
<body class="theme-<?= esc($themeName, 'attr') ?> layout-<?= esc($themeLayout, 'attr') ?>">
    <a class="skip-link" href="#contenu"><?= esc(lang('Site.common.skipContent')) ?></a>
    <?= $this->include('partials/public_navbar') ?>

    <main id="contenu" tabindex="-1">
        <?= $this->include('partials/flash') ?>
        <?= $this->renderSection('content') ?>
    </main>

    <?= $this->include('partials/public_footer') ?>

    <script src="<?= site_asset_url('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= site_asset_url('assets/js/main.js') ?>"></script>
    <?php if ($analytics !== null): ?>
        <script src="<?= site_asset_url('assets/js/analytics.js') ?>" data-provider="<?= esc($analytics['provider'], 'attr') ?>" data-url="<?= esc($analytics['url'], 'attr') ?>" data-site-id="<?= esc($analytics['site_id'], 'attr') ?>" defer></script>
    <?php endif ?>
</body>
</html>
