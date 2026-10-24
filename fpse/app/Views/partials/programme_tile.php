<?php
$optionCode = trim((string) ($programme->option_code ?? ''));
$years = isset($years) ? trim((string) $years) : '';
?>
<a href="<?= site_url('formations/' . $programme->slug) ?>" class="programme-tile">
    <span class="d-flex align-items-center gap-1">
        <span class="badge-level"><?= esc(site_level_label($programme->level)) ?></span>
        <?php if ($optionCode !== ''): ?>
            <span class="option-code"><?= esc($optionCode) ?></span>
        <?php endif ?>
    </span>
    <span class="programme-tile-title"><?= esc($programme->title) ?></span>
    <span class="programme-tile-meta">
        <?php if ($years !== ''): ?><i class="bi bi-calendar-range" aria-hidden="true"></i><?= esc($years) ?><?php elseif (! empty($programme->duration)): ?><i class="bi bi-clock" aria-hidden="true"></i><?= esc($programme->duration) ?><?php endif ?>
        <i class="bi bi-arrow-right ms-auto" aria-hidden="true"></i>
    </span>
</a>
