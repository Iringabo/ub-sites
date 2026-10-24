<div class="accordion faq-accordion" id="<?= esc($accordionId, 'attr') ?>">
    <?php foreach ($items as $item): ?>
        <?php $itemId = $accordionId . '-' . (int) $item->id; ?>
        <div class="accordion-item">
            <h3 class="accordion-header" id="<?= esc($itemId, 'attr') ?>-q">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($itemId, 'attr') ?>-a" aria-expanded="false" aria-controls="<?= esc($itemId, 'attr') ?>-a">
                    <?= esc($item->question) ?>
                </button>
            </h3>
            <div id="<?= esc($itemId, 'attr') ?>-a" class="accordion-collapse collapse" aria-labelledby="<?= esc($itemId, 'attr') ?>-q">
                <div class="accordion-body">
                    <?php foreach (site_paragraphs((string) $item->answer) as $paragraph): ?>
                        <p><?= esc($paragraph) ?></p>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>
