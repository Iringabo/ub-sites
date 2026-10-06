<?php
/**
 * One tronc commun of a department: the shared years, the optional narrower
 * trunks, then the options.
 *
 * @var array<string, mixed> $trunk
 */
$sharedWith = static function (array $departments): string {
    if ($departments === []) {
        return '';
    }

    $chips = array_map(
        static fn (object $department): string => '<a class="dept-chip" href="' . esc(site_url('departements/' . $department->slug), 'attr') . '" title="' . esc($department->name, 'attr') . '">' . esc($department->abbreviation ?: $department->name) . '</a>',
        $departments,
    );

    return '<p class="study-shared"><span class="study-shared-label">' . esc(lang('Site.programmes.sharedWith')) . '</span> ' . implode(' ', $chips) . '</p>';
};
?>
<section class="study-path" aria-label="<?= esc($trunk['name'], 'attr') ?>">
    <ol class="study-path-steps">
        <li class="study-step">
            <span class="study-step-years"><?= esc($trunk['years']) ?></span>
            <div class="study-step-card study-step-core">
                <span class="study-step-kicker"><i class="bi bi-signpost-split me-1" aria-hidden="true"></i><?= esc(lang('Site.programmes.commonCore')) ?></span>
                <h5 class="study-step-title"><?= esc($trunk['name']) ?></h5>
                <?= $sharedWith($trunk['departments']) ?>
            </div>
        </li>
        <?php if ($trunk['branches'] !== []): ?>
            <li class="study-step">
                <span class="study-step-years"><?= esc($trunk['branches'][0]['years']) ?></span>
                <div class="study-branches">
                    <?php foreach ($trunk['branches'] as $branch): ?>
                        <div class="study-step-card study-step-branch">
                            <span class="study-step-kicker"><i class="bi bi-diagram-2 me-1" aria-hidden="true"></i><?= esc(lang('Site.programmes.branchCore')) ?><?= $branch['years'] !== $trunk['branches'][0]['years'] ? ' · ' . esc($branch['years']) : '' ?></span>
                            <h5 class="study-step-title"><?= esc($branch['name']) ?></h5>
                            <?= $sharedWith($branch['departments']) ?>
                            <div class="study-options">
                                <?php foreach ($branch['options'] as $item): ?>
                                    <?= view('partials/programme_tile', ['programme' => $item['programme'], 'years' => $item['years']]) ?>
                                <?php endforeach ?>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </li>
        <?php endif ?>
        <?php if ($trunk['options'] !== []): ?>
            <li class="study-step">
                <span class="study-step-years"><?= esc((string) ($trunk['options'][0]['years'] ?? lang('Site.programmes.optionStage'))) ?></span>
                <div class="study-options study-options-grid">
                    <?php foreach ($trunk['options'] as $item): ?>
                        <?= view('partials/programme_tile', ['programme' => $item['programme'], 'years' => $item['years']]) ?>
                    <?php endforeach ?>
                </div>
            </li>
        <?php endif ?>
    </ol>
</section>
