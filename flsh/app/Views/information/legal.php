<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?php
$lines = static fn (mixed $value): array => is_array($value)
    ? array_values(array_filter(array_map(static fn ($line): string => trim((string) $line), $value), static fn (string $line): bool => $line !== ''))
    : site_paragraphs((string) $value);
$institution = site_text_or_placeholder($siteSettings['institution.faculty_name'] ?? null);
$address = site_contact_address($siteSettings);
$email = trim((string) ($siteSettings['contact.email'] ?? ''));

$publisher = $lines($content['publisher_paragraphs'] ?? []);
if ($publisher === []) {
    $publisher = array_values(array_filter([
        lang('Site.legal.defaultPublisher', [$institution, site_text_or_placeholder($siteSettings['institution.university'] ?? null, '')]),
        $address !== '' ? lang('Site.legal.address', [$address]) : '',
        $email !== '' ? lang('Site.legal.contactEmail', [$email]) : '',
    ]));
}
$hostText = trim((string) ($content['host_text'] ?? ''));
$privacy = $lines($content['privacy_paragraphs'] ?? []);
if ($privacy === []) {
    $privacy = [lang('Site.legal.defaultPrivacyCollected'), lang('Site.legal.defaultPrivacyRetention'), lang('Site.legal.defaultPrivacyRights', [$email !== '' ? $email : site_url('contact')])];
}
$cookiesText = trim((string) ($content['cookies_text'] ?? ''));
$sections = [
    'editeur'              => [site_text_or_placeholder($content['publisher_title'] ?? null, lang('Site.legal.publisherTitle')), $publisher],
    'hebergement'          => [site_text_or_placeholder($content['host_title'] ?? null, lang('Site.legal.hostTitle')), $hostText !== '' ? site_paragraphs($hostText) : [lang('Site.legal.defaultHost')]],
    'donnees-personnelles' => [site_text_or_placeholder($content['privacy_title'] ?? null, lang('Site.legal.privacyTitle')), $privacy],
    'cookies'              => [site_text_or_placeholder($content['cookies_title'] ?? null, lang('Site.legal.cookiesTitle')), $cookiesText !== '' ? site_paragraphs($cookiesText) : [lang('Site.legal.defaultCookies')]],
];
?>
<?= view('partials/page_banner', [
    'pageTitle'       => $pageTitle,
    'breadcrumbTitle' => $pageTitle,
]) ?>

<section class="section-pad section-alt">
    <div class="container">
        <div class="row g-5">
            <nav class="col-lg-4 col-xl-3" aria-label="<?= esc(lang('Site.legal.contents'), 'attr') ?>">
                <div class="legal-toc">
                    <p class="legal-toc-title"><i class="bi bi-list-ol me-2" aria-hidden="true"></i><?= esc(lang('Site.legal.contents')) ?></p>
                    <ol class="list-unstyled mb-0">
                        <?php foreach ($sections as $anchor => [$title]): ?>
                            <li><a href="#<?= esc($anchor, 'attr') ?>"><?= esc($title) ?></a></li>
                        <?php endforeach ?>
                    </ol>
                </div>
            </nav>
            <div class="col-lg-8 col-xl-9 legal-body">
                <?php foreach ($sections as $anchor => [$title, $paragraphs]): ?>
                    <section id="<?= esc($anchor, 'attr') ?>" class="legal-section" aria-labelledby="<?= esc($anchor, 'attr') ?>-title">
                        <h2 class="h4 legal-heading" id="<?= esc($anchor, 'attr') ?>-title"><?= esc($title) ?></h2>
                        <?php foreach ($paragraphs as $paragraph): ?>
                            <p><?= esc($paragraph) ?></p>
                        <?php endforeach ?>
                        <?php if ($anchor === 'cookies' && $analytics !== null): ?>
                            <p id="mesure-audience"><?= esc(lang('Site.legal.analyticsNotice', [lang('Site.legal.analyticsProvider.' . $analytics['provider'])])) ?></p>
                        <?php endif ?>
                    </section>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
