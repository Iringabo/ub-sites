<?php
/**
 * @var CodeIgniter\Pager\PagerRenderer $pager
 */
$pager->setSurroundCount(2);
?>

<?php if ($pager->getPageCount() > 1): ?>
    <nav aria-label="<?= esc(lang('Site.pager.aria'), 'attr') ?>">
        <ul class="pagination justify-content-center mt-4">
            <?php if ($pager->hasPrevious()): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= esc($pager->getFirst(), 'attr') ?>" aria-label="<?= esc(lang('Site.pager.first'), 'attr') ?>"><?= esc(lang('Site.pager.first')) ?></a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="<?= esc($pager->getPrevious(), 'attr') ?>" aria-label="<?= esc(lang('Site.pager.previous'), 'attr') ?>"><?= esc(lang('Site.pager.previous')) ?></a>
                </li>
            <?php endif ?>

            <?php foreach ($pager->links() as $link): ?>
                <li class="page-item<?= $link['active'] ? ' active' : '' ?>">
                    <a class="page-link" href="<?= esc($link['uri'], 'attr') ?>">
                        <?= esc($link['title']) ?>
                    </a>
                </li>
            <?php endforeach ?>

            <?php if ($pager->hasNext()): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= esc($pager->getNext(), 'attr') ?>" aria-label="<?= esc(lang('Site.pager.next'), 'attr') ?>"><?= esc(lang('Site.pager.next')) ?></a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="<?= esc($pager->getLast(), 'attr') ?>" aria-label="<?= esc(lang('Site.pager.last'), 'attr') ?>"><?= esc(lang('Site.pager.last')) ?></a>
                </li>
            <?php endif ?>
        </ul>
    </nav>
<?php endif ?>
