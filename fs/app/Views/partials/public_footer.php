<?php
$siteSettings = $siteSettings ?? service('settingsService')->all();
$logo         = site_media_url($siteSettings['assets.logo'] ?? null);
$brandTitle   = site_text_or_placeholder($siteSettings['institution.short_name'] ?? null);
$brandSub     = site_text_or_placeholder($siteSettings['institution.university'] ?? null);
$socialLinks  = is_array($siteSettings['social.links'] ?? null) ? $siteSettings['social.links'] : [];
$socialIcons  = [
    'facebook' => ['label' => 'Facebook', 'icon' => 'bi-facebook'],
    'x'        => ['label' => 'Twitter/X', 'icon' => 'bi-twitter-x'],
    'linkedin' => ['label' => 'LinkedIn', 'icon' => 'bi-linkedin'],
    'youtube'  => ['label' => 'YouTube', 'icon' => 'bi-youtube'],
];
$footerItems = [
    ['label' => lang('Site.nav.faculty'), 'url' => site_url('faculte')],
    ['label' => lang('Site.nav.programmes'), 'url' => site_url('formations')],
    ['label' => lang('Site.nav.admissions'), 'url' => site_url('admissions')],
    ['label' => lang('Site.nav.research'), 'url' => site_url('recherche')],
    ['label' => lang('Site.nav.staff'), 'url' => site_url('corps-enseignant')],
    ['label' => lang('Site.nav.faq'), 'url' => site_url('faq')],
];
$universityUrl   = trim((string) ($siteSettings['institution.university_url'] ?? ''));
$universityUrl   = str_starts_with($universityUrl, 'https://') || str_starts_with($universityUrl, 'http://') ? $universityUrl : '';
$doctoralUrl     = trim((string) ($siteSettings['links.doctoral_studies_url'] ?? ''));
$doctoralUrl     = str_starts_with($doctoralUrl, 'https://') && filter_var($doctoralUrl, FILTER_VALIDATE_URL) !== false ? $doctoralUrl : '';
$sisterFaculties = service('facultyDirectory')->sisterFaculties();
$hasUniversityColumn = $universityUrl !== '' || $doctoralUrl !== '' || $sisterFaculties !== [];
?>
<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3">
                <div class="footer-logo">
                    <img src="<?= esc($logo, 'attr') ?>" alt="Logo <?= esc($brandTitle, 'attr') ?>">
                    <div>
                        <div class="footer-brand-title"><?= esc($brandTitle) ?></div>
                        <div class="footer-brand-subtitle"><?= esc($brandSub) ?></div>
                    </div>
                </div>
                <p class="small"><?= esc(site_text_or_placeholder($siteSettings['footer.text'] ?? null)) ?></p>
                <p class="small footer-university mb-0"><i class="bi bi-mortarboard me-1" aria-hidden="true"></i><?= esc(lang('Site.footer.university', [$brandSub])) ?></p>
            </div>
            <div class="col-lg-2 col-6">
                <h6><?= esc(lang('Site.footer.quickLinks')) ?></h6>
                <nav aria-label="<?= esc(lang('Site.footer.navigation'), 'attr') ?>">
                    <ul>
                        <?php foreach ($footerItems as $item): ?>
                            <li><a href="<?= esc($item['url'], 'attr') ?>"><?= esc($item['label']) ?></a></li>
                        <?php endforeach ?>
                    </ul>
                </nav>
            </div>
            <?php if ($hasUniversityColumn): ?>
                <div class="col-lg-2 col-6">
                    <h6><?= esc(lang('Site.footer.universityColumn')) ?></h6>
                    <nav aria-label="<?= esc(lang('Site.footer.universityColumn'), 'attr') ?>">
                        <ul>
                            <?php if ($universityUrl !== ''): ?>
                                <li><a href="<?= esc($universityUrl, 'attr') ?>" rel="noopener"><?= esc(site_text_or_placeholder($siteSettings['institution.university'] ?? null, lang('Site.footer.universitySite'))) ?> <i class="bi bi-box-arrow-up-right small" aria-hidden="true"></i></a></li>
                            <?php endif ?>
                            <?php if ($doctoralUrl !== ''): ?>
                                <li><a href="<?= esc($doctoralUrl, 'attr') ?>" rel="noopener"><?= esc(lang('Site.programmes.doctoralTitle')) ?> <i class="bi bi-box-arrow-up-right small" aria-hidden="true"></i></a></li>
                            <?php endif ?>
                            <?php foreach ($sisterFaculties as $faculty): ?>
                                <li><a href="<?= esc($faculty['url'], 'attr') ?>" title="<?= esc($faculty['name'], 'attr') ?>"><?= esc($faculty['name']) ?></a></li>
                            <?php endforeach ?>
                        </ul>
                    </nav>
                </div>
            <?php endif ?>
            <div class="col-lg-3 col-6">
                <h6><?= esc(lang('Site.footer.contact')) ?></h6>
                <address>
                    <div class="footer-contact-item"><i class="bi bi-geo-alt"></i><span><?= esc(site_text_or_placeholder(site_contact_address($siteSettings))) ?></span></div>
                    <div class="footer-contact-item"><i class="bi bi-telephone"></i><span><?= esc(site_text_or_placeholder($siteSettings['contact.phone'] ?? null)) ?></span></div>
                    <div class="footer-contact-item"><i class="bi bi-envelope"></i><span><?= esc(site_text_or_placeholder($siteSettings['contact.email'] ?? null)) ?></span></div>
                    <div class="footer-contact-item"><i class="bi bi-clock"></i><span><?= esc(site_text_or_placeholder($siteSettings['contact.hours'] ?? null)) ?></span></div>
                </address>
            </div>
            <div class="<?= $hasUniversityColumn ? 'col-lg-2' : 'col-lg-4' ?>">
                <h6><?= esc(lang('Site.footer.followUs')) ?></h6>
                <nav class="footer-social" aria-label="<?= esc(lang('Site.footer.followUs'), 'attr') ?>">
                    <?php foreach ($socialIcons as $key => $meta): ?>
                        <?php if (! empty($socialLinks[$key])): ?>
                            <a href="<?= esc(site_public_url((string) $socialLinks[$key], '#'), 'attr') ?>" aria-label="<?= esc($meta['label'], 'attr') ?>">
                                <i class="bi <?= esc($meta['icon'], 'attr') ?>"></i>
                            </a>
                        <?php endif ?>
                    <?php endforeach ?>
                </nav>
                <p class="small mb-0"><?= esc(lang('Home.footerCallout')) ?></p>
            </div>
        </div>
        <hr>
        <div class="footer-bottom">
            <div><?= esc(site_text_or_placeholder($siteSettings['footer.copyright'] ?? null)) ?></div>
            <nav class="footer-legal" aria-label="<?= esc(lang('Site.footer.legalNavigation'), 'attr') ?>">
                <a href="<?= site_url('mentions-legales') ?>"><?= esc(lang('Site.footer.legal')) ?></a>
                <a href="<?= site_url('mentions-legales#donnees-personnelles') ?>"><?= esc(lang('Site.footer.privacy')) ?></a>
                <a href="<?= site_url('admin') ?>" rel="nofollow"><i class="bi bi-lock me-1" aria-hidden="true"></i><?= esc(lang('Site.footer.admin')) ?></a>
                <span><?= esc(lang('Site.common.allRightsReserved')) ?></span>
            </nav>
        </div>
    </div>
</footer>
