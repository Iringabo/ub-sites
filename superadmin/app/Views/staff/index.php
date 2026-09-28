<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'       => site_text_or_placeholder((string) ($content['banner_title'] ?? ''), $pageTitle ?? lang('Site.pageTitles.staff')),
    'breadcrumbTitle' => $pageTitle ?? lang('Site.pageTitles.staff'),
    'pageSubtitle'    => $content['banner_subtitle'] ?? '',
    'bannerImage'     => $content['banner_image'] ?? '',
]) ?>

<section class="section-pad bg-white">
    <div class="container">
        <?php
        $teacherCount = count(array_filter($staff, static fn ($member): bool => ($member->category ?? '') === 'enseignant'));
        $adminCount = count(array_filter($staff, static fn ($member): bool => ($member->category ?? '') === 'administratif'));
        ?>
        <div class="d-flex flex-wrap justify-content-center gap-2 mb-5" role="group" aria-label="<?= esc(lang('Site.pageTitles.staff'), 'attr') ?>">
            <button class="btn btn-outline-green rounded-pill active" data-filter="tous"><?= esc(lang('Site.staff.all')) ?> <span class="tab-count" aria-hidden="true"><?= count($staff) ?></span></button>
            <button class="btn btn-outline-green rounded-pill" data-filter="enseignant"><?= esc(lang('Site.staff.teachers')) ?> <span class="tab-count" aria-hidden="true"><?= $teacherCount ?></span></button>
            <button class="btn btn-outline-green rounded-pill" data-filter="administratif"><?= esc(lang('Site.staff.administrative')) ?> <span class="tab-count" aria-hidden="true"><?= $adminCount ?></span></button>
        </div>

        <div class="row g-4">
            <?php foreach ($staff as $member): ?>
                <div class="col-lg-3 col-md-4 col-6">
                    <article class="staff-card h-100" data-category="<?= esc($member->category, 'attr') ?>">
                        <?php $staffPhoto = site_person_media($member->photo ?? null); ?>
                        <?php if ($staffPhoto !== null): ?>
                            <img src="<?= esc(site_media_url($staffPhoto), 'attr') ?>" alt="<?= esc($member->name, 'attr') ?>" class="staff-photo">
                        <?php else: ?>
                            <div class="card-icon mx-auto" aria-hidden="true"><?= esc(site_initials($member->name)) ?></div>
                        <?php endif ?>
                        <h2 class="staff-name mb-0"><?= esc($member->name) ?></h2>
                        <p class="staff-grade mb-1"><?= esc($member->grade ?? site_staff_category_label($member->category)) ?></p>
                        <p class="staff-specialty mb-1"><?= esc($member->specialty ?? '') ?></p>
                        <p class="small text-muted mb-2"><?= esc($member->role ?? '') ?></p>
                        <?php if ($member->email): ?>
                            <a href="mailto:<?= esc($member->email, 'attr') ?>" class="staff-email"><i class="bi bi-envelope me-1"></i><?= esc($member->email) ?></a>
                        <?php endif ?>
                        <a href="<?= site_url('corps-enseignant/' . $member->slug) ?>" class="d-block small fw-semibold text-brand mt-2"><?= esc(lang('Site.common.viewProfile')) ?></a>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
