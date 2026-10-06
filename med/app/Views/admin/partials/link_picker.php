<?php
/**
 * Searchable list of the site's public pages; the stored value stays a URL.
 *
 * @var string                                                   $name
 * @var string                                                   $id
 * @var string                                                   $value
 * @var list<array{group: string, label: string, url: string}>  $options
 * @var bool|null                                                $invalid
 * @var bool|null                                                $required
 * @var string|null                                              $describedBy
 * @var int|null                                                 $max
 */
$invalid ??= false;
$required ??= false;
$describedBy ??= '';
$max ??= 500;
$listId = $id . '-options';
$group = null;
?>
<div class="link-picker" data-link-picker>
    <div class="input-group">
        <input type="text" class="form-control link-picker-input<?= $invalid ? ' is-invalid' : '' ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>" maxlength="<?= esc((string) $max, 'attr') ?>" autocomplete="off" spellcheck="false" placeholder="Tapez le nom d’une page (ex. Formations) ou collez https://…"<?= $required ? ' required' : '' ?><?= $describedBy !== '' ? ' aria-describedby="' . esc($describedBy, 'attr') . '"' : '' ?> data-link-picker-input>
        <button type="button" class="btn btn-outline-secondary link-picker-button" data-link-picker-clear aria-label="Vider le lien" hidden><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        <button type="button" class="btn btn-outline-secondary link-picker-button" data-link-picker-toggle aria-label="Afficher la liste des pages" aria-controls="<?= esc($listId, 'attr') ?>" aria-expanded="false" tabindex="-1" hidden><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
    </div>
    <ul class="link-picker-list list-unstyled mb-0" id="<?= esc($listId, 'attr') ?>" role="listbox" aria-label="Pages du site" hidden>
        <?php foreach ($options as $index => $option): ?>
            <?php if ($option['group'] !== $group): ?>
                <?php $group = $option['group']; ?>
                <li class="link-picker-group" role="presentation" data-link-picker-group="<?= esc($group, 'attr') ?>"><?= esc($group) ?></li>
            <?php endif ?>
            <li class="link-picker-option" role="option" aria-selected="false" id="<?= esc($id . '-option-' . $index, 'attr') ?>" data-value="<?= esc($option['url'], 'attr') ?>" data-label="<?= esc($option['label'], 'attr') ?>" data-group="<?= esc($option['group'], 'attr') ?>">
                <span class="link-picker-option-label"><?= esc($option['label']) ?></span>
                <span class="link-picker-option-url"><?= esc($option['url']) ?></span>
            </li>
        <?php endforeach ?>
    </ul>
    <p class="form-text link-picker-address mb-0" data-link-picker-address hidden></p>
</div>
