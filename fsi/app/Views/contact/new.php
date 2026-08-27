<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; ?>
<?= view('partials/page_banner', [
    'pageTitle'    => $pageTitle ?? lang('Site.pageTitles.contact'),
    'pageSubtitle' => $content['banner_subtitle'] ?? lang('Site.contact.defaultSubtitle'),
]) ?>

<section class="section-pad bg-white">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <span class="section-label"><?= esc($content['contact_label'] ?? lang('Site.contact.detailsLabel')) ?></span>
                <h2 class="section-title"><?= esc($content['contact_title'] ?? lang('Site.contact.detailsTitle')) ?></h2>
                <div class="divider-green"></div>

                <address>
                    <div class="d-flex gap-3 mb-4">
                        <div class="card-icon mb-0"><i class="bi bi-geo-alt"></i></div>
                        <div>
                            <p class="fw-bold mb-0"><?= esc(lang('Site.contact.address')) ?></p>
                            <p class="text-muted small mb-0"><?= esc(site_text_or_placeholder($siteSettings['contact.address'] ?? null)) ?></p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-4">
                        <div class="card-icon mb-0"><i class="bi bi-telephone"></i></div>
                        <div>
                            <p class="fw-bold mb-0"><?= esc(lang('Site.contact.phone')) ?></p>
                            <p class="text-muted small mb-0"><?= esc(site_text_or_placeholder($siteSettings['contact.phone'] ?? null)) ?></p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-4">
                        <div class="card-icon mb-0"><i class="bi bi-envelope"></i></div>
                        <div>
                            <p class="fw-bold mb-0"><?= esc(lang('Site.contact.email')) ?></p>
                            <a href="mailto:<?= esc(site_text_or_placeholder($siteSettings['contact.email'] ?? null), 'attr') ?>" class="small text-success"><?= esc(site_text_or_placeholder($siteSettings['contact.email'] ?? null)) ?></a>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-4">
                        <div class="card-icon mb-0"><i class="bi bi-clock"></i></div>
                        <div>
                            <p class="fw-bold mb-0"><?= esc(lang('Site.contact.hours')) ?></p>
                            <p class="text-muted small mb-0"><?= esc(site_text_or_placeholder($siteSettings['contact.hours'] ?? null)) ?></p>
                        </div>
                    </div>
                </address>
            </div>

            <div class="col-lg-8">
                <div class="card-faculte p-4">
                    <h2 class="h5 fw-bold"><?= esc($content['form_title'] ?? lang('Site.contact.formTitle')) ?></h2>
                    <p class="text-muted small mb-4"><?= esc($content['form_help'] ?? '') ?></p>

                    <form id="contactForm" action="<?= site_url('contact') ?>" method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="visually-hidden">
                            <label for="honeypot" class="form-label"><?= esc(lang('Site.contact.honeypot')) ?></label>
                            <input type="text" id="honeypot" name="<?= esc(config('Honeypot')->name, 'attr') ?>" value="" autocomplete="off" tabindex="-1">
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label small fw-semibold"><?= esc(lang('Site.contact.fullName')) ?></label>
                                <input type="text" class="form-control<?= array_key_exists('name', $errors) ? ' is-invalid' : '' ?>" id="name" name="name" value="<?= esc(old('name'), 'attr') ?>" maxlength="<?= esc($limits['nameMaxLength'], 'attr') ?>" autocomplete="name" required aria-describedby="name-error">
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback" id="name-error"><?= esc($errors['name']) ?></div>
                                <?php endif ?>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label small fw-semibold"><?= esc(lang('Site.contact.email')) ?></label>
                                <input type="email" class="form-control<?= array_key_exists('email', $errors) ? ' is-invalid' : '' ?>" id="email" name="email" value="<?= esc(old('email'), 'attr') ?>" maxlength="<?= esc($limits['emailMaxLength'], 'attr') ?>" autocomplete="email" required aria-describedby="email-error">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback" id="email-error"><?= esc($errors['email']) ?></div>
                                <?php endif ?>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label small fw-semibold"><?= esc(lang('Site.contact.phone')) ?></label>
                                <input type="tel" class="form-control<?= array_key_exists('phone', $errors) ? ' is-invalid' : '' ?>" id="phone" name="phone" value="<?= esc(old('phone'), 'attr') ?>" maxlength="<?= esc($limits['phoneMaxLength'], 'attr') ?>" autocomplete="tel" aria-describedby="phone-error">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback" id="phone-error"><?= esc($errors['phone']) ?></div>
                                <?php endif ?>
                            </div>
                            <div class="col-md-6">
                                <label for="subject" class="form-label small fw-semibold"><?= esc(lang('Site.contact.subject')) ?></label>
                                <input type="text" class="form-control<?= array_key_exists('subject', $errors) ? ' is-invalid' : '' ?>" id="subject" name="subject" value="<?= esc(old('subject'), 'attr') ?>" maxlength="<?= esc($limits['subjectMaxLength'], 'attr') ?>" autocomplete="off" required aria-describedby="subject-error">
                                <?php if (isset($errors['subject'])): ?>
                                    <div class="invalid-feedback" id="subject-error"><?= esc($errors['subject']) ?></div>
                                <?php endif ?>
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label small fw-semibold"><?= esc(lang('Site.contact.message')) ?></label>
                                <textarea class="form-control<?= array_key_exists('message', $errors) ? ' is-invalid' : '' ?>" id="message" name="message" rows="6" maxlength="<?= esc($limits['messageMaxLength'], 'attr') ?>" required aria-describedby="message-help message-error"><?= esc(old('message')) ?></textarea>
                                <div id="message-help" class="form-text"><?= esc(lang('Site.contact.messageHelp')) ?></div>
                                <?php if (isset($errors['message'])): ?>
                                    <div class="invalid-feedback d-block" id="message-error"><?= esc($errors['message']) ?></div>
                                <?php endif ?>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary-green px-5" data-contact-submit>
                                    <i class="bi bi-send me-1"></i><?= esc(lang('Site.contact.submit')) ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (! empty($content['map_url'])): ?>
    <section>
        <iframe
            src="<?= esc($content['map_url'], 'attr') ?>"
            width="100%" height="350" style="border:0;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?= esc(lang('Site.pages.contact'), 'attr') ?>">
        </iframe>
    </section>
<?php endif ?>
<?= $this->endSection() ?>
