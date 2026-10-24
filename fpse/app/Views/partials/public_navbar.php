<?php
$siteSettings = $siteSettings ?? service('settingsService')->all();
$activePage   = $activePage ?? '';
$logo         = site_media_url($siteSettings['assets.logo'] ?? null);
$brandTitle   = site_text_or_placeholder($siteSettings['institution.short_name'] ?? null);
$brandSub     = site_text_or_placeholder($siteSettings['institution.university'] ?? null);
$currentLocale = site_current_locale();
$localeUrls   = service('localeUrls');
$items = [
    'home'       => ['label' => lang('Site.nav.home'), 'url' => site_url('/')],
    'faculty'    => ['label' => lang('Site.nav.faculty'), 'url' => site_url('faculte'), 'children' => [
        'faculty'     => ['label' => lang('Site.nav.aboutFaculty'), 'url' => site_url('faculte'), 'icon' => 'bi-bank'],
        'departments' => ['label' => lang('Site.nav.departments'), 'url' => site_url('departements'), 'icon' => 'bi-diagram-2'],
    ]],
    'programmes' => ['label' => lang('Site.nav.programmes'), 'url' => site_url('formations'), 'children' => [
        'programmes' => ['label' => lang('Site.nav.allProgrammes'), 'url' => site_url('formations'), 'icon' => 'bi-mortarboard'],
        'admissions' => ['label' => lang('Site.nav.admissions'), 'url' => site_url('admissions'), 'icon' => 'bi-door-open'],
        'faq'        => ['label' => lang('Site.nav.faq'), 'url' => site_url('faq'), 'icon' => 'bi-question-circle'],
    ]],
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
                        <?php if (isset($item['children'])): ?>
                            <?php $groupActive = array_key_exists($activePage, $item['children']); ?>
                            <li class="nav-item dropdown">
                                <button class="nav-link dropdown-toggle<?= $groupActive ? ' active' : '' ?>" type="button" id="nav-<?= esc($key, 'attr') ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?= esc($item['label']) ?>
                                </button>
                                <ul class="dropdown-menu nav-dropdown" aria-labelledby="nav-<?= esc($key, 'attr') ?>">
                                    <?php foreach ($item['children'] as $childKey => $child): ?>
                                        <li>
                                            <a class="dropdown-item<?= $activePage === $childKey ? ' active' : '' ?>" href="<?= esc($child['url'], 'attr') ?>"<?= $activePage === $childKey ? ' aria-current="page"' : '' ?>>
                                                <i class="bi <?= esc($child['icon'], 'attr') ?>" aria-hidden="true"></i><?= esc($child['label']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach ?>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link<?= $activePage === $key ? ' active' : '' ?>" href="<?= esc($item['url'], 'attr') ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>>
                                    <?= esc($item['label']) ?>
                                </a>
                            </li>
                        <?php endif ?>
                    <?php endforeach ?>
                    <li class="nav-item navbar-search-item">
                        <form class="navbar-search" action="<?= site_url('trouver') ?>" method="get" role="search" data-search>
                            <button type="button" class="navbar-search-toggle" aria-expanded="false" aria-controls="navbarSearchPanel" aria-label="<?= esc(lang('Site.common.search'), 'attr') ?>" data-search-toggle><i class="bi bi-search" aria-hidden="true"></i></button>
                            <div class="navbar-search-panel" id="navbarSearchPanel">
                                <label class="visually-hidden" for="siteSearch"><?= esc(lang('Site.common.search'), 'attr') ?></label>
                                <input class="form-control form-control-sm" type="search" id="siteSearch" name="q" value="<?= esc((string) (service('request')->getGet('q') ?? ''), 'attr') ?>" placeholder="<?= esc(lang('Site.common.search'), 'attr') ?>">
                                <button type="submit" class="navbar-search-btn" aria-label="<?= esc(lang('Site.common.search'), 'attr') ?>"><i class="bi bi-search" aria-hidden="true"></i></button>
                            </div>
                        </form>
                    </li>
                    <li class="nav-item ms-xl-1">
                        <div class="language-switcher" aria-label="<?= esc(lang('Site.language.switcherLabel'), 'attr') ?>">
                            <?php foreach (['fr', 'en'] as $locale): ?>
                                <a
                                    class="language-switcher-link<?= $currentLocale === $locale ? ' active' : '' ?>"
                                    href="<?= esc($localeUrls->switchUrl($locale), 'attr') ?>"
                                    hreflang="<?= esc($locale, 'attr') ?>"
                                    lang="<?= esc($locale, 'attr') ?>"
                                    <?= $currentLocale === $locale ? 'aria-current="true"' : '' ?>
                                ><?= esc(strtoupper($locale)) ?></a>
                            <?php endforeach ?>
                        </div>
                    </li>
                    <li class="nav-item ms-xl-2">
                        <a class="btn btn-primary-green navbar-contact" href="<?= site_url('contact') ?>"><i class="bi bi-envelope" aria-hidden="true"></i><?= esc(lang('Site.nav.contact')) ?></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>
