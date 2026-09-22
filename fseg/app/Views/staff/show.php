<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'    => $pageTitle ?? $member->name,
    'pageSubtitle' => site_staff_category_label($member->category),
    'breadcrumbs'  => [
        ['label' => lang('Site.common.home'), 'url' => site_url('/')],
        ['label' => lang('Site.pageTitles.staff'), 'url' => site_url('corps-enseignant')],
        ['label' => $member->name],
    ],
]) ?>

<section class="section-pad bg-white">
    <div class="container">
        <div class="row g-5 align-items-start">
            <aside class="col-lg-4 text-center">
                <article class="card-faculte">
                    <?php $staffPhoto = site_person_media($member->photo ?? null); ?>
                    <?php if ($staffPhoto !== null): ?>
                        <img src="<?= esc(site_media_url($staffPhoto), 'attr') ?>" alt="<?= esc($member->name, 'attr') ?>" class="dean-photo">
                    <?php else: ?>
                        <div class="card-icon mx-auto" aria-hidden="true"><?= esc(site_initials($member->name)) ?></div>
                    <?php endif ?>
                    <h2 class="h5 mb-1"><?= esc($member->name) ?></h2>
                    <p class="text-success fw-semibold small mb-1"><?= esc($member->grade ?? '') ?></p>
                    <p class="text-muted small mb-2"><?= esc($member->specialty ?? '') ?></p>
                    <?php if ($member->email): ?>
                        <a href="mailto:<?= esc($member->email, 'attr') ?>" class="btn btn-outline-green btn-sm">
                            <i class="bi bi-envelope me-1"></i><?= esc(lang('Site.common.write')) ?>
                        </a>
                    <?php endif ?>
                </article>
            </aside>
            <div class="col-lg-8">
                <span class="section-label"><?= esc(site_staff_category_label($member->category)) ?></span>
                <h2 class="section-title"><?= esc($member->role ?? lang('Site.staff.profile')) ?></h2>
                <div class="divider-green"></div>
                <?php if ($member->biography): ?>
                    <?php foreach (site_paragraphs($member->biography) as $paragraph): ?>
                        <p><?= esc($paragraph) ?></p>
                    <?php endforeach ?>
                <?php else: ?>
                    <p><?= esc(lang('Site.staff.fallbackBio', [$member->name])) ?></p>
                <?php endif ?>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
