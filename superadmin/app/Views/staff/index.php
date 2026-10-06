<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'       => site_text_or_placeholder((string) ($content['banner_title'] ?? ''), $pageTitle ?? lang('Site.pageTitles.staff')),
    'breadcrumbTitle' => $pageTitle ?? lang('Site.pageTitles.staff'),
    'pageSubtitle'    => $content['banner_subtitle'] ?? '',
    'bannerImage'     => $content['banner_image'] ?? '',
]) ?>

<?php
$staffUrl = static function (array $overrides) use ($filters): string {
    $params = array_filter(array_replace($filters, $overrides), static fn ($value): bool => $value !== '' && $value !== null);

    return site_url('corps-enseignant') . ($params === [] ? '' : '?' . http_build_query($params));
};
$categoryChips = [
    ''              => lang('Site.staff.all'),
    'enseignant'    => lang('Site.staff.teachers'),
    'administratif' => lang('Site.staff.administrative'),
];
$hasFilters = $filters['q'] !== '' || $filters['departement'] !== '' || $filters['grade'] !== '';
$departmentById = [];
foreach ($departments as $department) {
    $departmentById[(int) $department->id] = $department;
}
?>
<section class="section-pad bg-white">
    <div class="container">
        <nav class="filter-bar justify-content-center mb-4" aria-label="<?= esc(lang('Site.pageTitles.staff'), 'attr') ?>">
            <?php foreach ($categoryChips as $category => $label): ?>
                <?php $isActive = $filters['categorie'] === $category; ?>
                <a class="filter-chip<?= $isActive ? ' active' : '' ?>" href="<?= esc($staffUrl(['categorie' => $category]), 'attr') ?>"<?= $isActive ? ' aria-current="true"' : '' ?>>
                    <?= esc($label) ?> <span class="tab-count" aria-hidden="true"><?= (int) ($counts[$category] ?? 0) ?></span>
                </a>
            <?php endforeach ?>
        </nav>

        <form action="<?= site_url('corps-enseignant') ?>" method="get" class="staff-filters filter-panel row g-2 justify-content-center align-items-end mb-5" role="search">
            <?php if ($filters['categorie'] !== ''): ?>
                <input type="hidden" name="categorie" value="<?= esc($filters['categorie'], 'attr') ?>">
            <?php endif ?>
            <div class="col-md-4">
                <label for="staffSearch" class="form-label small fw-semibold"><?= esc(lang('Site.staff.searchLabel')) ?></label>
                <input type="search" class="form-control" id="staffSearch" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="<?= esc(lang('Site.staff.searchPlaceholder'), 'attr') ?>">
            </div>
            <?php if ($departments !== []): ?>
                <div class="col-md-3">
                    <label for="staffDepartment" class="form-label small fw-semibold"><?= esc(lang('Site.departments.label')) ?></label>
                    <select class="form-select" id="staffDepartment" name="departement">
                        <option value=""><?= esc(lang('Site.programmes.allDepartments')) ?></option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= esc($department->slug, 'attr') ?>"<?= $filters['departement'] === $department->slug ? ' selected' : '' ?>><?= esc($department->name) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            <?php endif ?>
            <?php if ($grades !== []): ?>
                <div class="col-md-3">
                    <label for="staffGrade" class="form-label small fw-semibold"><?= esc(lang('Site.staff.grade')) ?></label>
                    <select class="form-select" id="staffGrade" name="grade">
                        <option value=""><?= esc(lang('Site.staff.allGrades')) ?></option>
                        <?php foreach ($grades as $grade): ?>
                            <option value="<?= esc($grade, 'attr') ?>"<?= $filters['grade'] === $grade ? ' selected' : '' ?>><?= esc($grade) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            <?php endif ?>
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary-green"><?= esc(lang('Site.common.search')) ?></button>
                <?php if ($hasFilters): ?>
                    <a href="<?= esc($staffUrl(['q' => '', 'departement' => '', 'grade' => '']), 'attr') ?>" class="btn btn-outline-green"><?= esc(lang('Site.staff.resetFilters')) ?></a>
                <?php endif ?>
            </div>
        </form>

        <p class="visually-hidden" role="status"><?= esc(lang('Site.staff.resultCount', [$pager->getTotal('staff')])) ?></p>

        <?php if ($staff !== []): ?>
            <div class="row g-4">
                <?php foreach ($staff as $member): ?>
                    <div class="col-lg-3 col-md-4 col-6">
                        <article class="staff-card h-100" data-category="<?= esc($member->category, 'attr') ?>">
                            <?php $staffPhoto = site_person_media($member->photo ?? null); ?>
                            <?php if ($staffPhoto !== null): ?>
                                <img src="<?= esc(site_media_url($staffPhoto), 'attr') ?>" alt="<?= esc($member->name, 'attr') ?>" class="staff-photo" loading="lazy">
                            <?php else: ?>
                                <div class="card-icon mx-auto" aria-hidden="true"><?= esc(site_initials($member->name)) ?></div>
                            <?php endif ?>
                            <h2 class="staff-name mb-0"><?= esc($member->name) ?></h2>
                            <p class="staff-grade mb-1"><?= esc($member->grade ?? site_staff_category_label($member->category)) ?></p>
                            <p class="staff-specialty mb-1"><?= esc($member->specialty ?? '') ?></p>
                            <?php $memberDepartment = $departmentById[(int) ($member->department_id ?? 0)] ?? null; ?>
                            <?php if ($memberDepartment !== null): ?>
                                <p class="small text-brand mb-1"><?= esc($memberDepartment->abbreviation ?: $memberDepartment->name) ?></p>
                            <?php endif ?>
                            <p class="small text-muted mb-2"><?= esc($member->role ?? '') ?></p>
                            <?php if ($member->email): ?>
                                <a href="mailto:<?= esc($member->email, 'attr') ?>" class="staff-email"><i class="bi bi-envelope me-1"></i><?= esc($member->email) ?></a>
                            <?php endif ?>
                            <a href="<?= site_url('corps-enseignant/' . $member->slug) ?>" class="d-block small fw-semibold text-brand mt-2"><?= esc(lang('Site.common.viewProfile')) ?></a>
                        </article>
                    </div>
                <?php endforeach ?>
            </div>
        <?php else: ?>
            <p class="empty-state" role="status"><i class="bi bi-person-x" aria-hidden="true"></i><?= esc(lang('Site.staff.noResults')) ?></p>
        <?php endif ?>
        <?= $pager->links('staff', 'site_full') ?>
    </div>
</section>
<?= $this->endSection() ?>
