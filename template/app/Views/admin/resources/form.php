<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$translations = $translations ?? [];
$translationFields = $translationFields ?? [];
$settingDefinitions = $config['settingDefinitions'] ?? [];
$pageSchemas = $config['pageContentSchemas'] ?? [];

$normalize = static fn (string $value): string => preg_replace('/[^a-zA-Z0-9]+/', '_', $value) ?: $value;

$itemValue = static function (array $item, string $name): mixed {
    return $item[$name] ?? null;
};

$fieldValue = static function (array $item, array $field) use ($itemValue): string {
    $name = (string) $field['name'];
    $old = old($name);

    if ($old !== null) {
        return is_array($old) ? implode("\n", $old) : (string) $old;
    }

    $value = $itemValue($item, $name);

    if (($field['type'] ?? null) === 'json_text' && is_array($value)) {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (is_array($value)) {
        return implode("\n", array_map(static fn (mixed $entry): string => (string) $entry, $value));
    }

    return (string) ($value ?? '');
};

$translationValue = static function (array $translations, string $name): string {
    $old = old('translation_en_' . $name);

    return $old !== null ? (string) $old : (string) ($translations[$name] ?? '');
};

$isChecked = static function (array $item, array $field) use ($itemValue): bool {
    $name = (string) $field['name'];
    $old = old($name);

    if ($old !== null) {
        return (string) $old === '1';
    }

    return ! empty($itemValue($item, $name));
};

$nestedValue = static function (array $source, string $path): mixed {
    $value = $source;

    foreach (explode('.', $path) as $segment) {
        $key = ctype_digit($segment) ? (int) $segment : $segment;
        if (! is_array($value) || ! array_key_exists($key, $value)) {
            return null;
        }

        $value = $value[$key];
    }

    return $value;
};

$pageKey = old('key', $item['key'] ?? '');
$pageContent = site_decode_page_content((string) ($item['content'] ?? ''));
$translatedPageContent = site_decode_page_content((string) ($translations['content'] ?? ''));
$pageContentValue = static function (string $prefix, string $path, array $source, string $type) use ($nestedValue, $normalize): string {
    $input = $prefix . '_' . $normalize($path);
    $old = old($input);

    if ($old !== null) {
        return (string) $old;
    }

    $value = $nestedValue($source, $path);

    if ($type === 'list' && is_array($value)) {
        return implode("\n", array_map(static fn (mixed $entry): string => (string) $entry, $value));
    }

    return is_array($value) ? '' : (string) ($value ?? '');
};

$fieldsByName = [];
foreach ($config['fields'] as $field) {
    $fieldsByName[(string) $field['name']] = $field;
}

$usedFields = [];
$fieldGroups = [];
foreach ($config['sections'] ?? [] as $section) {
    $sectionFields = [];
    foreach ($section['fields'] ?? [] as $fieldName) {
        if (isset($fieldsByName[$fieldName])) {
            $sectionFields[] = $fieldsByName[$fieldName];
            $usedFields[] = $fieldName;
        }
    }

    if ($sectionFields !== []) {
        $fieldGroups[] = [
            'title'  => (string) ($section['title'] ?? 'Section'),
            'fields' => $sectionFields,
        ];
    }
}

$remainingFields = array_values(array_filter(
    $config['fields'],
    static fn (array $field): bool => ! in_array((string) $field['name'], $usedFields, true),
));
if ($remainingFields !== []) {
    $fieldGroups[] = [
        'title'  => $fieldGroups === [] ? 'Contenu' : 'Autres informations',
        'fields' => $remainingFields,
    ];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label"><?= $isNew ? 'Création' : 'Modification' ?></span>
        <h1 class="h3 mb-1"><?= esc($config['title']) ?></h1>
        <p class="text-muted mb-0">Le français est la version principale. Les champs anglais sont facultatifs.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/' . $resource) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Retour
        </a>
    </div>
</div>

<form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data" class="card-faculte" data-unsaved-guard>
    <?= csrf_field() ?>

    <?php foreach ($fieldGroups as $groupIndex => $group): ?>
        <section class="admin-form-section" aria-labelledby="resource-section-<?= esc((string) $groupIndex, 'attr') ?>">
            <div class="admin-form-section-title">
                <h2 id="resource-section-<?= esc((string) $groupIndex, 'attr') ?>"><?= esc($group['title']) ?></h2>
            </div>

            <div class="row g-4">
                <?php foreach ($group['fields'] as $field): ?>
                    <?php
                    $name = (string) $field['name'];
                    $type = (string) ($field['type'] ?? 'text');
                    $label = (string) ($field['label'] ?? $name);
                    $required = ! empty($field['required']);
                    $value = $fieldValue($item, $field);
                    $inputId = 'field_' . $normalize($name);
                    $max = isset($field['max']) ? (int) $field['max'] : null;
                    $counterId = $max !== null ? $inputId . '_counter' : null;
                    $wideTypes = ['textarea', 'json_list', 'json_text', 'image', 'icon', 'page_content', 'setting_value'];
                    $showWhenAttrs = '';
                    if (! empty($field['showWhen']) && is_array($field['showWhen'])) {
                        foreach ($field['showWhen'] as $depField => $depValue) {
                            $showWhenAttrs = ' data-show-when-field="' . esc((string) $depField, 'attr') . '" data-show-when-value="' . esc((string) $depValue, 'attr') . '"';
                            break;
                        }
                    }
                    ?>

                    <div class="<?= in_array($type, $wideTypes, true) ? 'col-12' : 'col-md-6' ?> admin-field-wrap"<?= $showWhenAttrs ?>>
                        <?php if (! empty($field['managedByDrag'])): ?>
                            <div class="form-label fw-semibold"><?= esc($label) ?></div>
                            <p class="form-text mb-0">
                                <?php if ($isNew): ?>
                                    L’ordre est attribué automatiquement à la création. Réordonnez ensuite la liste par glisser-déposer.
                                <?php else: ?>
                                    Ordre actuel : <strong><?= esc($value !== '' ? $value : '0') ?></strong>.
                                    Modifiez-le en glissant les lignes dans la liste du module.
                                <?php endif ?>
                            </p>
                            <?php if (! $isNew && $value !== ''): ?>
                                <input type="hidden" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>">
                            <?php endif ?>
                        <?php elseif ($type === 'boolean'): ?>
                            <div class="form-check mt-4">
                                <input type="hidden" name="<?= esc($name, 'attr') ?>" value="0">
                                <input class="form-check-input" type="checkbox" value="1" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($name, 'attr') ?>" <?= $isChecked($item, $field) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="<?= esc($inputId, 'attr') ?>"><?= esc($label) ?></label>
                                <?php if (isset($errors[$name])): ?>
                                    <div class="invalid-feedback d-block"><?= esc($errors[$name]) ?></div>
                                <?php endif ?>
                            </div>
                        <?php elseif ($type === 'textarea' || $type === 'json_list' || $type === 'json_text'): ?>
                            <div class="d-flex justify-content-between gap-2">
                                <label for="<?= esc($inputId, 'attr') ?>" class="form-label fw-semibold"><?= esc($label) ?><?= $required ? ' *' : '' ?></label>
                                <?php if ($counterId !== null): ?>
                                    <span class="admin-field-counter" id="<?= esc($counterId, 'attr') ?>"></span>
                                <?php endif ?>
                            </div>
                            <textarea class="form-control<?= isset($errors[$name]) ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($name, 'attr') ?>" rows="<?= $type === 'json_list' ? '5' : '7' ?>" <?= $required ? 'required' : '' ?> <?= $max !== null ? 'maxlength="' . esc((string) $max, 'attr') . '" data-character-counter="' . esc((string) $counterId, 'attr') . '"' : '' ?>><?= esc($value) ?></textarea>
                            <?php if ($type === 'json_list'): ?>
                                <p class="form-text">Un élément par ligne.</p>
                            <?php elseif ($type === 'json_text'): ?>
                                <p class="form-text">JSON contrôlé par l’application. N’ajoutez ni CSS ni code exécutable.</p>
                            <?php endif ?>
                            <?php if (isset($errors[$name])): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors[$name]) ?></div>
                            <?php endif ?>
                        <?php elseif ($type === 'color'): ?>
                            <label for="<?= esc($inputId, 'attr') ?>" class="form-label fw-semibold"><?= esc($label) ?><?= $required ? ' *' : '' ?></label>
                            <div class="d-flex align-items-center gap-3">
                                <input
                                    type="color"
                                    class="form-control form-control-color<?= isset($errors[$name]) ? ' is-invalid' : '' ?>"
                                    id="<?= esc($inputId, 'attr') ?>"
                                    name="<?= esc($name, 'attr') ?>"
                                    value="<?= esc($value !== '' ? $value : '#0D9B49', 'attr') ?>"
                                    <?= $required ? 'required' : '' ?>
                                    title="<?= esc($label, 'attr') ?>"
                                >
                                <span class="text-muted small font-monospace" data-color-hex-for="<?= esc($inputId, 'attr') ?>"><?= esc($value !== '' ? $value : '#0D9B49') ?></span>
                            </div>
                            <?php if (isset($errors[$name])): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors[$name]) ?></div>
                            <?php endif ?>
                        <?php elseif ($type === 'select' || $type === 'relation' || $type === 'readonly_select'): ?>
                            <label for="<?= esc($inputId, 'attr') ?>" class="form-label fw-semibold"><?= esc($label) ?><?= $required ? ' *' : '' ?></label>
                            <?php if ($type === 'readonly_select'): ?>
                                <input type="hidden" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>">
                            <?php endif ?>
                            <select class="form-select<?= isset($errors[$name]) ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($name, 'attr') ?>" <?= $required ? 'required' : '' ?> <?= $type === 'readonly_select' ? 'disabled' : '' ?>>
                                <?php if (! $required || $type === 'relation'): ?>
                                    <option value="">Aucune sélection</option>
                                <?php endif ?>
                                <?php foreach ($fieldOptions[$name] ?? [] as $optionValue => $optionLabel): ?>
                                    <option value="<?= esc($optionValue, 'attr') ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>>
                                        <?= esc($optionLabel) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                            <?php if (isset($errors[$name])): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors[$name]) ?></div>
                            <?php endif ?>
                        <?php elseif ($type === 'icon'): ?>
                            <div class="form-label fw-semibold"><?= esc($label) ?><?= $required ? ' *' : '' ?></div>
                            <div class="admin-icon-picker" role="radiogroup" aria-label="<?= esc($label, 'attr') ?>">
                                <?php if (! $required): ?>
                                    <div class="admin-icon-choice">
                                        <input type="radio" id="<?= esc($inputId, 'attr') ?>_none" name="<?= esc($name, 'attr') ?>" value="" <?= $value === '' ? 'checked' : '' ?>>
                                        <label for="<?= esc($inputId, 'attr') ?>_none"><i class="bi bi-slash-circle" aria-hidden="true"></i><span>Aucune</span></label>
                                    </div>
                                <?php endif ?>
                                <?php foreach ($fieldOptions[$name] ?? [] as $optionValue => $optionLabel): ?>
                                    <div class="admin-icon-choice">
                                        <input type="radio" id="<?= esc($inputId . '_' . $normalize((string) $optionValue), 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= esc($optionValue, 'attr') ?>" <?= $value === (string) $optionValue ? 'checked' : '' ?> <?= $required ? 'required' : '' ?>>
                                        <label for="<?= esc($inputId . '_' . $normalize((string) $optionValue), 'attr') ?>"><i class="bi <?= esc((string) $optionValue, 'attr') ?>" aria-hidden="true"></i><span><?= esc($optionLabel) ?></span></label>
                                    </div>
                                <?php endforeach ?>
                            </div>
                            <?php if (isset($errors[$name])): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors[$name]) ?></div>
                            <?php endif ?>
                        <?php elseif ($type === 'image'): ?>
                            <label for="<?= esc($inputId, 'attr') ?>" class="form-label fw-semibold"><?= esc($label) ?></label>
                            <?php if ($value !== ''): ?>
                                <div class="mb-2">
                                    <img src="<?= esc(site_media_url($value), 'attr') ?>" alt="<?= esc($label, 'attr') ?>" class="rounded border" style="max-width: 220px; max-height: 150px; object-fit: cover;">
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="remove_<?= esc($inputId, 'attr') ?>" name="remove_<?= esc($name, 'attr') ?>">
                                    <label class="form-check-label" for="remove_<?= esc($inputId, 'attr') ?>">Retirer l’image actuelle</label>
                                </div>
                            <?php endif ?>
                            <input type="file" class="form-control<?= isset($errors[$name]) ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($name, 'attr') ?>" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <p class="form-text">JPG, PNG ou WebP, 2 Mo maximum. Le nom original n’est jamais conservé.</p>
                            <?php if (isset($errors[$name])): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors[$name]) ?></div>
                            <?php endif ?>
                        <?php elseif ($type === 'page_content'): ?>
                            <?php $schema = $pageSchemas[(string) $pageKey] ?? []; ?>
                            <?php if ($schema === []): ?>
                                <div class="alert alert-warning mb-0">Cette page n’a pas encore de formulaire structuré.</div>
                            <?php else: ?>
                                <div class="admin-page-content-grid">
                                    <?php foreach ($schema as $path => $definition): ?>
                                        <?php
                                        $contentType = (string) ($definition['type'] ?? 'text');
                                        $contentLabel = (string) $definition['label'];
                                        $contentRequired = ! empty($definition['required']);
                                        $contentInputName = 'page_content_' . $normalize((string) $path);
                                        $contentInputId = 'field_' . $contentInputName;
                                        $contentValue = $pageContentValue('page_content', (string) $path, $pageContent, $contentType);
                                        ?>
                                        <div>
                                            <?php if ($contentType === 'textarea' || $contentType === 'list'): ?>
                                                <label for="<?= esc($contentInputId, 'attr') ?>" class="form-label fw-semibold"><?= esc($contentLabel) ?><?= $contentRequired ? ' *' : '' ?></label>
                                                <textarea class="form-control<?= isset($errors[$contentInputName]) ? ' is-invalid' : '' ?>" id="<?= esc($contentInputId, 'attr') ?>" name="<?= esc($contentInputName, 'attr') ?>" rows="<?= $contentType === 'list' ? '4' : '5' ?>" <?= $contentRequired ? 'required' : '' ?>><?= esc($contentValue) ?></textarea>
                                                <?php if ($contentType === 'list'): ?>
                                                    <p class="form-text">Un élément par ligne.</p>
                                                <?php endif ?>
                                            <?php elseif ($contentType === 'icon'): ?>
                                                <div class="form-label fw-semibold"><?= esc($contentLabel) ?></div>
                                                <div class="admin-icon-picker" role="radiogroup" aria-label="<?= esc($contentLabel, 'attr') ?>">
                                                    <div class="admin-icon-choice">
                                                        <input type="radio" id="<?= esc($contentInputId, 'attr') ?>_none" name="<?= esc($contentInputName, 'attr') ?>" value="" <?= $contentValue === '' ? 'checked' : '' ?>>
                                                        <label for="<?= esc($contentInputId, 'attr') ?>_none"><i class="bi bi-slash-circle" aria-hidden="true"></i><span>Aucune</span></label>
                                                    </div>
                                                    <?php foreach (($config['iconOptions'] ?? []) as $optionValue => $optionLabel): ?>
                                                        <div class="admin-icon-choice">
                                                            <input type="radio" id="<?= esc($contentInputId . '_' . $normalize((string) $optionValue), 'attr') ?>" name="<?= esc($contentInputName, 'attr') ?>" value="<?= esc($optionValue, 'attr') ?>" <?= $contentValue === (string) $optionValue ? 'checked' : '' ?>>
                                                            <label for="<?= esc($contentInputId . '_' . $normalize((string) $optionValue), 'attr') ?>"><i class="bi <?= esc((string) $optionValue, 'attr') ?>" aria-hidden="true"></i><span><?= esc($optionLabel) ?></span></label>
                                                        </div>
                                                    <?php endforeach ?>
                                                </div>
                                            <?php else: ?>
                                                <?php
                                                $htmlType = $contentType === 'external_url' ? 'url' : 'text';
                                                ?>
                                                <label for="<?= esc($contentInputId, 'attr') ?>" class="form-label fw-semibold"><?= esc($contentLabel) ?><?= $contentRequired ? ' *' : '' ?></label>
                                                <input type="<?= esc($htmlType, 'attr') ?>" class="form-control<?= isset($errors[$contentInputName]) ? ' is-invalid' : '' ?>" id="<?= esc($contentInputId, 'attr') ?>" name="<?= esc($contentInputName, 'attr') ?>" value="<?= esc($contentValue, 'attr') ?>" <?= $contentRequired ? 'required' : '' ?>>
                                            <?php endif ?>
                                            <?php if (isset($errors[$contentInputName])): ?>
                                                <div class="invalid-feedback d-block"><?= esc($errors[$contentInputName]) ?></div>
                                            <?php endif ?>
                                        </div>
                                    <?php endforeach ?>
                                </div>
                            <?php endif ?>
                        <?php elseif ($type === 'setting_value'): ?>
                            <?php
                            $settingKey = (string) old('key', $item['key'] ?? '');
                            $settingDefinition = $settingDefinitions[$settingKey] ?? ['type' => 'string', 'label' => $label];
                            $settingType = (string) ($settingDefinition['type'] ?? 'string');
                            $settingLabel = (string) ($settingDefinition['label'] ?? $label);
                            $settingInputType = match ($settingType) {
                                'email'   => 'email',
                                'color'   => 'color',
                                'integer' => 'number',
                                default   => 'text',
                            };
                            ?>
                            <label for="<?= esc($inputId, 'attr') ?>" class="form-label fw-semibold"><?= esc($settingLabel) ?></label>
                            <?php if ($settingType === 'path'): ?>
                                <?php if ($value !== ''): ?>
                                    <div class="mb-2">
                                        <img src="<?= esc(site_media_url($value), 'attr') ?>" alt="<?= esc($settingLabel, 'attr') ?>" class="rounded border" style="max-width: 220px; max-height: 150px; object-fit: contain; background: #fff;">
                                    </div>
                                <?php endif ?>
                                <input type="hidden" name="value" value="<?= esc($value, 'attr') ?>">
                                <input type="file" class="form-control<?= isset($errors[$name]) ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="setting_value_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <p class="form-text">JPG, PNG ou WebP, 2 Mo maximum.</p>
                            <?php elseif ($settingType === 'text'): ?>
                                <textarea class="form-control<?= isset($errors[$name]) ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="value" rows="5"><?= esc($value) ?></textarea>
                            <?php else: ?>
                                <input type="<?= esc($settingInputType, 'attr') ?>" class="form-control<?= isset($errors[$name]) ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="value" value="<?= esc($value, 'attr') ?>">
                            <?php endif ?>
                            <?php if (isset($errors[$name])): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors[$name]) ?></div>
                            <?php endif ?>
                        <?php else: ?>
                            <?php
                            $htmlType = match ($type) {
                                'integer' => 'number',
                                'email' => 'email',
                                'external_url' => 'url',
                                default => 'text',
                            };
                            ?>
                            <div class="d-flex justify-content-between gap-2">
                                <label for="<?= esc($inputId, 'attr') ?>" class="form-label fw-semibold"><?= esc($label) ?><?= $required ? ' *' : '' ?></label>
                                <?php if ($counterId !== null): ?>
                                    <span class="admin-field-counter" id="<?= esc($counterId, 'attr') ?>"></span>
                                <?php endif ?>
                            </div>
                            <input type="<?= esc($htmlType, 'attr') ?>" class="form-control<?= isset($errors[$name]) ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>" <?= $required ? 'required' : '' ?> <?= $max !== null ? 'maxlength="' . esc((string) $max, 'attr') . '" data-character-counter="' . esc((string) $counterId, 'attr') . '"' : '' ?>>
                            <?php if ($type === 'slug'): ?>
                                <p class="form-text">Laissez vide pour générer automatiquement un slug unique.</p>
                            <?php endif ?>
                            <?php if (isset($errors[$name])): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors[$name]) ?></div>
                            <?php endif ?>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        </section>
    <?php endforeach ?>

    <?php if ($translationFields !== [] || ($resource === 'pages' && ($pageSchemas[(string) $pageKey] ?? []) !== [])): ?>
        <section class="admin-form-section" aria-labelledby="english-translation-title">
            <div class="admin-form-section-title">
                <div>
                    <span class="section-label">Traduction</span>
                    <h2 id="english-translation-title">Version anglaise</h2>
                </div>
            </div>

            <?php if ($translationFields !== []): ?>
                <div class="row g-4">
                    <?php foreach ($translationFields as $field): ?>
                        <?php
                        $name = (string) $field['name'];
                        $type = (string) ($field['type'] ?? 'text');
                        $label = (string) ($field['label'] ?? $name);
                        $errorKey = 'translation_en_' . $name;
                        $value = $translationValue($translations, $name);
                        $inputId = 'translation_en_' . $normalize($name);
                        ?>
                        <div class="<?= $type === 'textarea' ? 'col-12' : 'col-md-6' ?>">
                            <label for="<?= esc($inputId, 'attr') ?>" class="form-label fw-semibold"><?= esc($label) ?> en anglais</label>
                            <?php if ($type === 'textarea'): ?>
                                <textarea class="form-control<?= isset($errors[$errorKey]) ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($errorKey, 'attr') ?>" rows="5"><?= esc($value) ?></textarea>
                            <?php else: ?>
                                <input type="text" class="form-control<?= isset($errors[$errorKey]) ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($errorKey, 'attr') ?>" value="<?= esc($value, 'attr') ?>">
                            <?php endif ?>
                            <?php if (isset($errors[$errorKey])): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors[$errorKey]) ?></div>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <?php if ($resource === 'pages' && ($pageSchemas[(string) $pageKey] ?? []) !== []): ?>
                <div class="admin-page-content-grid mt-4">
                    <?php foreach ($pageSchemas[(string) $pageKey] as $path => $definition): ?>
                        <?php
                        $contentType = (string) ($definition['type'] ?? 'text');
                        $contentLabel = (string) $definition['label'];
                        $contentInputName = 'translation_en_page_content_' . $normalize((string) $path);
                        $contentInputId = 'field_' . $contentInputName;
                        $contentValue = $pageContentValue('translation_en_page_content', (string) $path, $translatedPageContent, $contentType);
                        ?>
                        <div>
                            <?php if ($contentType === 'textarea' || $contentType === 'list'): ?>
                                <label for="<?= esc($contentInputId, 'attr') ?>" class="form-label fw-semibold"><?= esc($contentLabel) ?> en anglais</label>
                                <textarea class="form-control" id="<?= esc($contentInputId, 'attr') ?>" name="<?= esc($contentInputName, 'attr') ?>" rows="<?= $contentType === 'list' ? '4' : '5' ?>"><?= esc($contentValue) ?></textarea>
                                <?php if ($contentType === 'list'): ?>
                                    <p class="form-text">Un élément par ligne. Laissez vide pour conserver la version française.</p>
                                <?php endif ?>
                            <?php elseif ($contentType === 'icon'): ?>
                                <div class="form-label fw-semibold"><?= esc($contentLabel) ?> en anglais</div>
                                <div class="admin-icon-picker" role="radiogroup" aria-label="<?= esc($contentLabel, 'attr') ?> en anglais">
                                    <div class="admin-icon-choice">
                                        <input type="radio" id="<?= esc($contentInputId, 'attr') ?>_same" name="<?= esc($contentInputName, 'attr') ?>" value="" <?= $contentValue === '' ? 'checked' : '' ?>>
                                        <label for="<?= esc($contentInputId, 'attr') ?>_same"><i class="bi bi-slash-circle" aria-hidden="true"></i><span>Comme en français</span></label>
                                    </div>
                                    <?php foreach (($config['iconOptions'] ?? []) as $optionValue => $optionLabel): ?>
                                        <div class="admin-icon-choice">
                                            <input type="radio" id="<?= esc($contentInputId . '_' . $normalize((string) $optionValue), 'attr') ?>" name="<?= esc($contentInputName, 'attr') ?>" value="<?= esc($optionValue, 'attr') ?>" <?= $contentValue === (string) $optionValue ? 'checked' : '' ?>>
                                            <label for="<?= esc($contentInputId . '_' . $normalize((string) $optionValue), 'attr') ?>"><i class="bi <?= esc((string) $optionValue, 'attr') ?>" aria-hidden="true"></i><span><?= esc($optionLabel) ?></span></label>
                                        </div>
                                    <?php endforeach ?>
                                </div>
                            <?php else: ?>
                                <?php $htmlType = $contentType === 'external_url' ? 'url' : 'text'; ?>
                                <label for="<?= esc($contentInputId, 'attr') ?>" class="form-label fw-semibold"><?= esc($contentLabel) ?> en anglais</label>
                                <input type="<?= esc($htmlType, 'attr') ?>" class="form-control" id="<?= esc($contentInputId, 'attr') ?>" name="<?= esc($contentInputName, 'attr') ?>" value="<?= esc($contentValue, 'attr') ?>">
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </section>
    <?php endif ?>

    <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
        <button type="submit" class="btn btn-primary-green">
            <i class="bi bi-check2 me-1" aria-hidden="true"></i>Enregistrer
        </button>
        <a href="<?= site_url('admin/' . $resource) ?>" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
<?= $this->section('scripts') ?>
<script>
(function () {
    const wraps = document.querySelectorAll('.admin-field-wrap[data-show-when-field]');
    if (!wraps.length) return;
    const sync = () => {
        wraps.forEach((wrap) => {
            const field = wrap.getAttribute('data-show-when-field');
            const expected = wrap.getAttribute('data-show-when-value') || '';
            const control = document.getElementById('field_' + String(field).replace(/[^a-zA-Z0-9_]/g, '_'))
                || document.querySelector('[name="' + field + '"]');
            if (!control) return;
            const actual = control.value || '';
            let visible = actual === expected;
            if (expected.startsWith('!')) {
                visible = actual !== expected.slice(1);
            }
            wrap.classList.toggle('d-none', !visible);
            wrap.querySelectorAll('input, select, textarea').forEach((el) => {
                if (visible) {
                    el.removeAttribute('disabled');
                } else if (el.type !== 'hidden') {
                    el.setAttribute('disabled', 'disabled');
                }
            });
        });
    };
    document.querySelectorAll('select, input').forEach((el) => el.addEventListener('change', sync));
    sync();
})();
</script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
