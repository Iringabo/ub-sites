<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<?= view('partials/page_banner', [
    'pageTitle'    => $pageTitle ?? $programme->title,
    'pageSubtitle' => site_level_label($programme->level) . ' · ' . $programme->duration,
    'breadcrumbs'  => [
        ['label' => lang('Site.common.home'), 'url' => site_url('/')],
        ['label' => lang('Site.pageTitles.programmes'), 'url' => site_url('formations')],
        ['label' => $programme->title],
    ],
]) ?>

<section class="section-pad bg-white">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <span class="section-label"><?= esc(site_level_label($programme->level)) ?></span>
                <h2 class="section-title"><?= esc($programme->title) ?></h2>
                <div class="divider-green"></div>
                <?php foreach (site_paragraphs($programme->description) as $paragraph): ?>
                    <p><?= esc($paragraph) ?></p>
                <?php endforeach ?>
                <h3 class="h5 mt-4"><?= esc(lang('Site.programmes.professionalOutcomes')) ?></h3>
                <ul>
                    <?php foreach ((is_array($programme->career_outcomes) ? $programme->career_outcomes : []) as $outcome): ?>
                        <li><?= esc($outcome) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
            <aside class="col-lg-4">
                <article class="card-faculte">
                    <h3 class="h5"><?= esc(lang('Site.programmes.keyInformation')) ?></h3>
                    <p class="mb-2"><strong><?= esc(lang('Site.programmes.level')) ?> :</strong> <?= esc(site_level_label($programme->level)) ?></p>
                    <p class="mb-3"><strong><?= esc(lang('Site.programmes.duration')) ?> :</strong> <?= esc($programme->duration) ?></p>
                    <p class="small fw-semibold mb-1"><?= esc(lang('Site.programmes.admissionConditions')) ?></p>
                    <p class="small text-muted mb-0"><?= esc($programme->admission_conditions) ?></p>
                </article>
            </aside>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
