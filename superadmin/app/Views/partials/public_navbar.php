<?php
$siteSettings = $siteSettings ?? service('settingsService')->all();
$activePage   = $activePage ?? '';
$logo         = site_media_url($siteSettings['assets.logo'] ?? null);
$brandTitle   = site_text_or_placeholder($siteSettings['institution.short_name'] ?? null);
$brandSub     = site_text_or_placeholder($siteSettings['institution.university'] ?? null);
$currentLocale = site_current_locale();
$redirectUrl = site_current_url_for_redirect();
$items = [
    'home'       => ['label' => lang('Site.nav.home'), 'url' => site_url('/')],
    'faculty'    => ['label' => lang('Site.nav.faculty'), 'url' => site_url('faculte')],
    'programmes' => ['label' => lang('Site.nav.programmes'), 'url' => site_url('formations')],
    'research'   => ['label' => lang('Site.nav.research'), 'url' => site_url('recherche')],
    'staff'      => ['label' => lang('Site.nav.staff'), 'url' => site_url('corps-enseignant')],
    'posts'      => ['label' => lang('Site.nav.posts'), 'url' => site_url('actualites')],
    'alumni'     => ['label' => lang('Site.nav.alumni'), 'url' => site_url('alumni')],
];
?>
<header>
    <nav class="navbar navbar-expand-xl navbar-light sticky-top bg-white" aria-label="<?= esc(lang('Site.nav.main'), 'attr') ?>">
        <div class="container">
            <a class="navbar-brand" href="<?= site_url('/') ?>">
                <img src="<?= esc($logo, 'attr') ?>" alt="Logo <?= esc($brandTitle, 'attr') ?>">
                <div class="navbar-brand-text">
                    <span class="brand-title"><?= esc($brandTitle) ?></span>
                    <span class="brand-subtitle"><?= esc($brandSub) ?></span>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="<?= esc(lang('Site.nav.open'), 'attr') ?>">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-xl-center">
                    <?php foreach ($items as $key => $item): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $activePage === $key ? ' active' : '' ?>" href="<?= esc($item['url'], 'attr') ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>>
                                <?= esc($item['label']) ?>
                            </a>
                        </li>
                    <?php endforeach ?>
                    <li class="nav-item ms-xl-2">
                        <div class="language-switcher" aria-label="<?= esc(lang('Site.language.switcherLabel'), 'attr') ?>">
                            <?php foreach (['fr', 'en'] as $locale): ?>
                                <a
                                    class="language-switcher-link<?= $currentLocale === $locale ? ' active' : '' ?>"
                                    href="<?= esc(site_url('language/' . $locale) . '?redirect=' . rawurlencode($redirectUrl), 'attr') ?>"
                                    hreflang="<?= esc($locale, 'attr') ?>"
                                    lang="<?= esc($locale, 'attr') ?>"
                                    <?= $currentLocale === $locale ? 'aria-current="true"' : '' ?>
                                ><?= esc(strtoupper($locale)) ?></a>
                            <?php endforeach ?>
                        </div>
                    </li>
                    <li class="nav-item ms-xl-2">
                        <a class="btn btn-primary-green" href="<?= site_url('contact') ?>"><?= esc(lang('Site.nav.contact')) ?></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>
