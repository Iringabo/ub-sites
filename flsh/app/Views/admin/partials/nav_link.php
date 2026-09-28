<?php
/**
 * @var array<string, mixed> $link
 * @var string               $group
 * @var bool                 $isActive
 */
$badge = (int) ($link['badge'] ?? 0);
$keywords = implode(' ', array_map('strval', (array) ($link['keywords'] ?? [])));
?>
<li>
    <a class="admin-nav-link <?= $isActive ? 'active' : '' ?>" href="<?= site_url((string) $link['url']) ?>" data-nav-group="<?= esc($group, 'attr') ?>" data-keywords="<?= esc($keywords, 'attr') ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
        <i class="bi <?= esc((string) $link['icon'], 'attr') ?>" aria-hidden="true"></i>
        <span class="admin-nav-label"><?= esc((string) $link['label']) ?></span>
        <?php if ($badge > 0): ?>
            <span class="admin-nav-badge" title="<?= esc($badge . ' message(s) non lu(s)', 'attr') ?>">
                <?= $badge > 99 ? '99+' : esc((string) $badge) ?>
                <span class="visually-hidden">non lu(s)</span>
            </span>
        <?php endif ?>
    </a>
</li>
