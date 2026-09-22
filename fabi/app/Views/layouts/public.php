<?php
$siteSettings = $siteSettings ?? service('settingsService')->all();
$pageTitle    = site_text_or_placeholder($title ?? null, site_text_or_placeholder($siteSettings['seo.default_title'] ?? null));
$pageDesc     = site_text_or_placeholder($description ?? null, site_text_or_placeholder($siteSettings['seo.default_description'] ?? null));
$themeColor   = $siteSettings['seo.theme_color'] ?? '#0D9B49';
$ogImage      = site_media_url($ogImage ?? ($siteSettings['seo.og_image'] ?? 'assets/images/logo-placeholder.png'));
$canonical    = current_url();
$locale       = site_current_locale();
$activeSite   = service('siteResolver')->activeSite();
$themeName    = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($activeSite->theme ?? 'default'))) ?: 'default';
$themeConfig  = json_decode((string) ($activeSite->theme_config ?? ''), true);
$themeLayout  = is_array($themeConfig) ? preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($themeConfig['layout'] ?? 'classic'))) : 'classic';
$themeLayout  = $themeLayout ?: 'classic';
?>
<!doctype html>
<html lang="<?= esc($locale, 'attr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?></title>
    <meta name="description" content="<?= esc($pageDesc) ?>">
    <meta name="theme-color" content="<?= esc($themeColor, 'attr') ?>">
    <link rel="canonical" href="<?= esc($canonical, 'attr') ?>">
    <link rel="icon" type="image/png" href="<?= esc(site_media_url($siteSettings['assets.logo'] ?? null), 'attr') ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="<?= esc(site_og_locale(), 'attr') ?>">
    <meta property="og:title" content="<?= esc($pageTitle) ?>">
    <meta property="og:description" content="<?= esc($pageDesc) ?>">
    <meta property="og:url" content="<?= esc($canonical, 'attr') ?>">
    <meta property="og:image" content="<?= esc($ogImage, 'attr') ?>">
    <meta name="twitter:card" content="summary">
    <link href="<?= site_asset_url('assets/vendor/inter/inter.css') ?>" rel="stylesheet">    <link href="<?= site_asset_url('assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= site_asset_url('assets/vendor/bootstrap-icons/bootstrap-icons.css') ?>" rel="stylesheet">
    <link href="<?= site_asset_url('assets/css/style.css') ?>" rel="stylesheet">
    <style nonce="{csp-style-nonce}">
        :root {
            --green: #0D9B49;
            --green-dark: #0B6F38;
        }
    </style>
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
</body>
</html>
