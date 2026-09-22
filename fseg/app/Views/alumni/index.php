<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'    => $pageTitle ?? lang('Site.pageTitles.alumni'),
    'pageSubtitle' => $content['banner_subtitle'] ?? '',
    'bannerImage'  => $content['banner_image'] ?? '',
]) ?>

<section class="section-pad bg-white">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="section-label"><?= esc($content['intro_label'] ?? lang('Site.alumni.networkLabel')) ?></span>
                <h2 class="section-title"><?= esc($content['intro_title'] ?? '') ?></h2>
                <div class="divider-green"></div>
                <?php foreach (($content['intro_paragraphs'] ?? []) as $paragraph): ?>
                    <p><?= esc($paragraph) ?></p>
                <?php endforeach ?>
                <a href="<?= esc(site_public_url($content['intro_button_url'] ?? '/contact'), 'attr') ?>" class="btn btn-primary-green">
                    <?= esc($content['intro_button_label'] ?? lang('Site.alumni.joinNetwork')) ?>
                </a>
            </div>
            <div class="col-lg-6">
                <div class="row g-3 text-center">
                    <?php foreach ($stats as $stat): ?>
                        <div class="col-4">
                            <article class="card-faculte h-100 p-3">
                                <div class="stat-number text-success" style="font-size:1.9rem;" data-count="<?= esc((string) $stat->value, 'attr') ?>">0</div>
                                <p class="small text-muted mb-0"><?= esc($stat->label) ?></p>
                            </article>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-pad section-alt">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 640px;">
            <span class="section-label"><?= esc($content['profiles_label'] ?? lang('Site.alumni.profilesLabel')) ?></span>
            <h2 class="section-title"><?= esc($content['profiles_title'] ?? lang('Site.alumni.profilesTitle')) ?></h2>
            <p class="section-subtitle mx-auto"><?= esc($content['profiles_text'] ?? '') ?></p>
        </div>
        <div class="row g-4">
            <?php foreach ($profiles as $profile): ?>
                <div class="col-lg-3 col-md-6">
                    <article class="staff-card h-100">
                        <?php if ($profile->photo): ?>
                            <img src="<?= esc(site_media_url($profile->photo), 'attr') ?>" alt="<?= esc($profile->name, 'attr') ?>" class="staff-photo">
                        <?php endif ?>
                        <h3 class="staff-name mb-0"><?= esc($profile->name) ?></h3>
                        <p class="staff-grade mb-1"><?= esc($profile->promotion ?? '') ?></p>
                        <p class="staff-specialty mb-0"><?= esc($profile->role ?? '') ?></p>
                        <p class="small text-muted mb-0"><?= esc($profile->organization ?? '') ?></p>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<section class="section-pad bg-white">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 640px;">
            <span class="section-label"><?= esc($content['testimonials_label'] ?? lang('Site.alumni.testimonialsLabel')) ?></span>
            <h2 class="section-title"><?= esc($content['testimonials_title'] ?? lang('Site.alumni.testimonialsTitle')) ?></h2>
        </div>
        <div class="row g-4">
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="col-lg-4">
                    <article class="card-faculte h-100">
                        <i class="bi bi-quote text-success fs-3 d-block mb-2"></i>
                        <p class="small text-muted fst-italic">« <?= esc($testimonial->quote) ?> »</p>
                        <div class="d-flex align-items-center gap-2 mt-3">
                            <?php if ($testimonial->photo): ?>
                                <img src="<?= esc(site_media_url($testimonial->photo), 'attr') ?>" alt="<?= esc($testimonial->person_name, 'attr') ?>" class="rounded-circle" width="42" height="42">
                            <?php endif ?>
                            <div>
                                <p class="fw-bold small mb-0"><?= esc($testimonial->person_name) ?></p>
                                <p class="text-muted small mb-0"><?= esc($testimonial->promotion ?? '') ?></p>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<section class="section-pad-sm section-alt">
    <div class="container text-center">
        <h2 class="h4 fw-bold"><?= esc($content['cta_title'] ?? lang('Site.alumni.ctaTitle')) ?></h2>
        <p class="section-subtitle mx-auto mb-4"><?= esc($content['cta_text'] ?? '') ?></p>
        <a href="<?= esc(site_public_url($content['cta_url'] ?? '/contact'), 'attr') ?>" class="btn btn-primary-green">
            <?= esc($content['cta_label'] ?? lang('Site.alumni.ctaLabel')) ?>
        </a>
    </div>
</section>
<?= $this->endSection() ?>
