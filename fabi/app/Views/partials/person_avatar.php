<?php
$avatarPhoto = site_person_media($person->photo ?? null);
$avatarClass = trim('person-avatar ' . ($class ?? ''));
?>
<?php if ($avatarPhoto !== null): ?>
    <img src="<?= esc(site_media_url($avatarPhoto), 'attr') ?>" alt="" class="<?= esc($avatarClass, 'attr') ?>" loading="lazy">
<?php else: ?>
    <span class="<?= esc($avatarClass . ' person-avatar-initials', 'attr') ?>" aria-hidden="true"><?= esc(site_initials($person->name ?? '')) ?></span>
<?php endif ?>
