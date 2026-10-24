<?php
/**
 * Icon-only row actions. Expects $actions as list of:
 * - type: link|button|form
 * - href / action
 * - icon (bootstrap icon class without bi- prefix or full bi-*)
 * - label (accessible title)
 * - class: primary|outline|danger|success|secondary
 * - confirm?: string
 * - method?: post (default for form)
 */
$actions = $actions ?? [];
$iconClass = static function (string $icon): string {
    $icon = trim($icon);
    return str_starts_with($icon, 'bi-') ? $icon : 'bi-' . $icon;
};
$btnClass = static function (string $variant): string {
    return match ($variant) {
        'primary' => 'btn btn-primary-green btn-sm btn-icon',
        'danger' => 'btn btn-outline-danger btn-sm btn-icon',
        'success' => 'btn btn-outline-success btn-sm btn-icon',
        'outline' => 'btn btn-outline-green btn-sm btn-icon',
        default => 'btn btn-outline-secondary btn-sm btn-icon',
    };
};
?>
<div class="d-inline-flex flex-nowrap justify-content-end gap-1 admin-row-actions">
    <?php foreach ($actions as $action): ?>
        <?php
        $type = (string) ($action['type'] ?? 'link');
        $label = (string) ($action['label'] ?? '');
        $icon = $iconClass((string) ($action['icon'] ?? 'circle'));
        $variant = (string) ($action['class'] ?? 'secondary');
        $class = $btnClass($variant);
        $confirm = isset($action['confirm']) ? (string) $action['confirm'] : null;
        ?>
        <?php if ($type === 'link'): ?>
            <a href="<?= esc((string) ($action['href'] ?? '#'), 'attr') ?>" class="<?= esc($class, 'attr') ?>" title="<?= esc($label, 'attr') ?>" aria-label="<?= esc($label, 'attr') ?>">
                <i class="bi <?= esc($icon, 'attr') ?>" aria-hidden="true"></i>
            </a>
        <?php elseif ($type === 'button'): ?>
            <button type="button" class="<?= esc($class, 'attr') ?> <?= esc((string) ($action['extraClass'] ?? ''), 'attr') ?>" title="<?= esc($label, 'attr') ?>" aria-label="<?= esc($label, 'attr') ?>"
                <?php foreach (($action['attrs'] ?? []) as $attr => $val): ?>
                    <?= esc((string) $attr, 'attr') ?>="<?= esc((string) $val, 'attr') ?>"
                <?php endforeach ?>
            >
                <i class="bi <?= esc($icon, 'attr') ?>" aria-hidden="true"></i>
            </button>
        <?php else: ?>
            <form method="post" action="<?= esc((string) ($action['action'] ?? '#'), 'attr') ?>" class="d-inline" <?= $confirm !== null ? 'data-confirm="' . esc($confirm, 'attr') . '"' : '' ?>>
                <?= csrf_field() ?>
                <button type="submit" class="<?= esc($class, 'attr') ?>" title="<?= esc($label, 'attr') ?>" aria-label="<?= esc($label, 'attr') ?>">
                    <i class="bi <?= esc($icon, 'attr') ?>" aria-hidden="true"></i>
                </button>
            </form>
        <?php endif ?>
    <?php endforeach ?>
</div>
