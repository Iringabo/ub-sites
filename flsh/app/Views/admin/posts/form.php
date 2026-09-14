<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$type = old('type', $post->type ?? 'news');
$status = old('status', $post->status ?? 'draft');
$translations = $translations ?? [];
$translationValue = static function (array $translations, string $name): string {
    $old = old('translation_en_' . $name);

    return $old !== null ? (string) $old : (string) ($translations[$name] ?? '');
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label"><?= $isNew ? 'Création' : 'Modification' ?></span>
        <h1 class="h3 mb-1"><?= $isNew ? 'Nouveau contenu' : esc($post->title) ?></h1>
        <p class="text-muted mb-0">Le français reste la version principale. La version anglaise est facultative et utilisée sur le site public anglais.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (! $isNew): ?>
            <a href="<?= site_url('admin/posts/' . $post->id . '/preview') ?>" class="btn btn-outline-green">Aperçu</a>
        <?php endif ?>
        <a href="<?= site_url('admin/posts') ?>" class="btn btn-outline-secondary">Retour</a>
    </div>
</div>

<form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data" class="card-faculte" data-unsaved-guard>
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="mb-3">
                <label for="title" class="form-label fw-semibold">Titre</label>
                <input type="text" class="form-control<?= isset($errors['title']) ? ' is-invalid' : '' ?>" id="title" name="title" value="<?= esc(old('title', $post->title ?? ''), 'attr') ?>" required<?= isset($errors['title']) ? ' aria-describedby="title-error"' : '' ?>>
                <?php if (isset($errors['title'])): ?>
                    <div class="invalid-feedback" id="title-error"><?= esc($errors['title']) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-3">
                <label for="slug" class="form-label fw-semibold">Slug</label>
                <input type="text" class="form-control<?= isset($errors['slug']) ? ' is-invalid' : '' ?>" id="slug" name="slug" value="<?= esc(old('slug', $post->slug ?? ''), 'attr') ?>" placeholder="Généré automatiquement si laissé vide"<?= isset($errors['slug']) ? ' aria-describedby="slug-error"' : '' ?>>
                <p class="form-text">Utilisez des lettres minuscules, chiffres et tirets. Un slug unique sera généré si nécessaire.</p>
                <?php if (isset($errors['slug'])): ?>
                    <div class="invalid-feedback d-block" id="slug-error"><?= esc($errors['slug']) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-3">
                <label for="excerpt" class="form-label fw-semibold">Résumé</label>
                <textarea class="form-control<?= isset($errors['excerpt']) ? ' is-invalid' : '' ?>" id="excerpt" name="excerpt" rows="3" required<?= isset($errors['excerpt']) ? ' aria-describedby="excerpt-error"' : '' ?>><?= esc(old('excerpt', $post->excerpt ?? '')) ?></textarea>
                <?php if (isset($errors['excerpt'])): ?>
                    <div class="invalid-feedback d-block" id="excerpt-error"><?= esc($errors['excerpt']) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-3">
                <label for="body" class="form-label fw-semibold">Contenu</label>
                <textarea class="form-control<?= isset($errors['body']) ? ' is-invalid' : '' ?>" id="body" name="body" rows="10" required<?= isset($errors['body']) ? ' aria-describedby="body-error"' : '' ?>><?= esc(old('body', $post->body ?? '')) ?></textarea>
                <?php if (isset($errors['body'])): ?>
                    <div class="invalid-feedback d-block" id="body-error"><?= esc($errors['body']) ?></div>
                <?php endif ?>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="seo_title" class="form-label fw-semibold">Titre SEO</label>
                    <input type="text" class="form-control<?= isset($errors['seo_title']) ? ' is-invalid' : '' ?>" id="seo_title" name="seo_title" value="<?= esc(old('seo_title', $post->seo_title ?? ''), 'attr') ?>"<?= isset($errors['seo_title']) ? ' aria-describedby="seo-title-error"' : '' ?>>
                    <?php if (isset($errors['seo_title'])): ?>
                        <div class="invalid-feedback d-block" id="seo-title-error"><?= esc($errors['seo_title']) ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-6">
                    <label for="seo_description" class="form-label fw-semibold">Description SEO</label>
                    <input type="text" class="form-control<?= isset($errors['seo_description']) ? ' is-invalid' : '' ?>" id="seo_description" name="seo_description" value="<?= esc(old('seo_description', $post->seo_description ?? ''), 'attr') ?>"<?= isset($errors['seo_description']) ? ' aria-describedby="seo-description-error"' : '' ?>>
                    <?php if (isset($errors['seo_description'])): ?>
                        <div class="invalid-feedback d-block" id="seo-description-error"><?= esc($errors['seo_description']) ?></div>
                    <?php endif ?>
                </div>
            </div>

            <section class="border-top pt-4 mt-4" aria-labelledby="post-english-title">
                <span class="section-label">Traduction</span>
                <h2 class="h5 mb-1" id="post-english-title">Version anglaise</h2>
                <p class="text-muted small">Les champs vides utiliseront automatiquement le contenu français.</p>

                <div class="mb-3">
                    <label for="translation_en_title" class="form-label fw-semibold">Titre en anglais</label>
                    <input type="text" class="form-control<?= isset($errors['translation_en_title']) ? ' is-invalid' : '' ?>" id="translation_en_title" name="translation_en_title" value="<?= esc($translationValue($translations, 'title'), 'attr') ?>">
                    <?php if (isset($errors['translation_en_title'])): ?>
                        <div class="invalid-feedback d-block"><?= esc($errors['translation_en_title']) ?></div>
                    <?php endif ?>
                </div>

                <div class="mb-3">
                    <label for="translation_en_excerpt" class="form-label fw-semibold">Résumé en anglais</label>
                    <textarea class="form-control<?= isset($errors['translation_en_excerpt']) ? ' is-invalid' : '' ?>" id="translation_en_excerpt" name="translation_en_excerpt" rows="3"><?= esc($translationValue($translations, 'excerpt')) ?></textarea>
                    <?php if (isset($errors['translation_en_excerpt'])): ?>
                        <div class="invalid-feedback d-block"><?= esc($errors['translation_en_excerpt']) ?></div>
                    <?php endif ?>
                </div>

                <div class="mb-3">
                    <label for="translation_en_body" class="form-label fw-semibold">Contenu en anglais</label>
                    <textarea class="form-control<?= isset($errors['translation_en_body']) ? ' is-invalid' : '' ?>" id="translation_en_body" name="translation_en_body" rows="8"><?= esc($translationValue($translations, 'body')) ?></textarea>
                    <?php if (isset($errors['translation_en_body'])): ?>
                        <div class="invalid-feedback d-block"><?= esc($errors['translation_en_body']) ?></div>
                    <?php endif ?>
                </div>

                <div class="mb-3">
                    <label for="translation_en_event_location" class="form-label fw-semibold">Lieu de l’événement en anglais</label>
                    <input type="text" class="form-control<?= isset($errors['translation_en_event_location']) ? ' is-invalid' : '' ?>" id="translation_en_event_location" name="translation_en_event_location" value="<?= esc($translationValue($translations, 'event_location'), 'attr') ?>">
                    <?php if (isset($errors['translation_en_event_location'])): ?>
                        <div class="invalid-feedback d-block"><?= esc($errors['translation_en_event_location']) ?></div>
                    <?php endif ?>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="translation_en_seo_title" class="form-label fw-semibold">Titre SEO en anglais</label>
                        <input type="text" class="form-control<?= isset($errors['translation_en_seo_title']) ? ' is-invalid' : '' ?>" id="translation_en_seo_title" name="translation_en_seo_title" value="<?= esc($translationValue($translations, 'seo_title'), 'attr') ?>">
                        <?php if (isset($errors['translation_en_seo_title'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['translation_en_seo_title']) ?></div>
                        <?php endif ?>
                    </div>
                    <div class="col-md-6">
                        <label for="translation_en_seo_description" class="form-label fw-semibold">Description SEO en anglais</label>
                        <input type="text" class="form-control<?= isset($errors['translation_en_seo_description']) ? ' is-invalid' : '' ?>" id="translation_en_seo_description" name="translation_en_seo_description" value="<?= esc($translationValue($translations, 'seo_description'), 'attr') ?>">
                        <?php if (isset($errors['translation_en_seo_description'])): ?>
                            <div class="invalid-feedback d-block"><?= esc($errors['translation_en_seo_description']) ?></div>
                        <?php endif ?>
                    </div>
                </div>
            </section>
        </div>

        <aside class="col-lg-4">
            <div class="mb-3">
                <label for="type" class="form-label fw-semibold">Type</label>
                <select class="form-select<?= isset($errors['type']) ? ' is-invalid' : '' ?>" id="type" name="type"<?= isset($errors['type']) ? ' aria-describedby="type-error"' : '' ?>>
                    <?php foreach ($types as $value => $label): ?>
                        <?php if (in_array($value, $allowedTypes, true)): ?>
                            <option value="<?= esc($value, 'attr') ?>" <?= $type === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endif ?>
                    <?php endforeach ?>
                </select>
                <?php if (isset($errors['type'])): ?>
                    <div class="invalid-feedback d-block" id="type-error"><?= esc($errors['type']) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label fw-semibold">Statut</label>
                <select class="form-select<?= isset($errors['status']) ? ' is-invalid' : '' ?>" id="status" name="status"<?= isset($errors['status']) ? ' aria-describedby="status-error"' : '' ?>>
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= esc($value, 'attr') ?>" <?= $status === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
                <?php if (isset($errors['status'])): ?>
                    <div class="invalid-feedback d-block" id="status-error"><?= esc($errors['status']) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-3">
                <label for="published_at" class="form-label fw-semibold">Date de publication</label>
                <input type="datetime-local" class="form-control<?= isset($errors['published_at']) ? ' is-invalid' : '' ?>" id="published_at" name="published_at" value="<?= esc(old('published_at', site_datetime_input($post->published_at ?? null)), 'attr') ?>"<?= isset($errors['published_at']) ? ' aria-describedby="published-at-error"' : '' ?>>
                <p class="form-text">Un contenu programmé reste privé tant que cette date n’est pas atteinte.</p>
                <?php if (isset($errors['published_at'])): ?>
                    <div class="invalid-feedback d-block" id="published-at-error"><?= esc($errors['published_at']) ?></div>
                <?php endif ?>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input<?= isset($errors['featured']) ? ' is-invalid' : '' ?>" type="checkbox" value="1" id="featured" name="featured" <?= old('featured', ! empty($post->featured) ? '1' : '0') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="featured">Mettre en avant sur l’accueil</label>
                <?php if (isset($errors['featured'])): ?>
                    <div class="invalid-feedback d-block" id="featured-error"><?= esc($errors['featured']) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-3">
                <label for="home_order" class="form-label fw-semibold">Ordre sur l’accueil</label>
                <input type="number" min="1" class="form-control<?= isset($errors['home_order']) ? ' is-invalid' : '' ?>" id="home_order" name="home_order" value="<?= esc(old('home_order', $post->home_order ?? ''), 'attr') ?>"<?= isset($errors['home_order']) ? ' aria-describedby="home-order-error"' : '' ?>>
                <?php if (isset($errors['home_order'])): ?>
                    <div class="invalid-feedback d-block" id="home-order-error"><?= esc($errors['home_order']) ?></div>
                <?php endif ?>
            </div>

            <div class="mb-3">
                <label for="cover_image" class="form-label fw-semibold">Image de couverture</label>
                <input type="file" class="form-control<?= isset($errors['cover_image']) ? ' is-invalid' : '' ?>" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"<?= isset($errors['cover_image']) ? ' aria-describedby="cover-image-error"' : '' ?>>
                <p class="form-text">JPG, PNG ou WebP, 2 Mo maximum.</p>
                <?php if (isset($errors['cover_image'])): ?>
                    <div class="invalid-feedback d-block" id="cover-image-error"><?= esc($errors['cover_image']) ?></div>
                <?php endif ?>
                <?php if (! empty($post->cover_image)): ?>
                    <img src="<?= esc(site_media_url($post->cover_image), 'attr') ?>" alt="Image de couverture actuelle" class="rounded mb-2 w-100" style="max-height: 160px; object-fit: cover;">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="remove_cover" name="remove_cover">
                        <label class="form-check-label" for="remove_cover">Retirer l’image actuelle</label>
                    </div>
                <?php endif ?>
            </div>

            <div class="border-top pt-3 mt-4">
                <h2 class="h6 fw-bold">Champs de l’événement</h2>
                <div class="mb-3">
                    <label for="event_starts_at" class="form-label small fw-semibold">Début</label>
                    <input type="datetime-local" class="form-control<?= isset($errors['event_starts_at']) ? ' is-invalid' : '' ?>" id="event_starts_at" name="event_starts_at" value="<?= esc(old('event_starts_at', site_datetime_input($post->event_starts_at ?? null)), 'attr') ?>"<?= isset($errors['event_starts_at']) ? ' aria-describedby="event-starts-error"' : '' ?>>
                    <?php if (isset($errors['event_starts_at'])): ?>
                        <div class="invalid-feedback d-block" id="event-starts-error"><?= esc($errors['event_starts_at']) ?></div>
                    <?php endif ?>
                </div>
                <div class="mb-3">
                    <label for="event_ends_at" class="form-label small fw-semibold">Fin</label>
                    <input type="datetime-local" class="form-control<?= isset($errors['event_ends_at']) ? ' is-invalid' : '' ?>" id="event_ends_at" name="event_ends_at" value="<?= esc(old('event_ends_at', site_datetime_input($post->event_ends_at ?? null)), 'attr') ?>"<?= isset($errors['event_ends_at']) ? ' aria-describedby="event-ends-error"' : '' ?>>
                    <?php if (isset($errors['event_ends_at'])): ?>
                        <div class="invalid-feedback d-block" id="event-ends-error"><?= esc($errors['event_ends_at']) ?></div>
                    <?php endif ?>
                </div>
                <div class="mb-3">
                    <label for="event_location" class="form-label small fw-semibold">Lieu</label>
                    <input type="text" class="form-control<?= isset($errors['event_location']) ? ' is-invalid' : '' ?>" id="event_location" name="event_location" value="<?= esc(old('event_location', $post->event_location ?? ''), 'attr') ?>"<?= isset($errors['event_location']) ? ' aria-describedby="event-location-error"' : '' ?>>
                    <?php if (isset($errors['event_location'])): ?>
                        <div class="invalid-feedback d-block" id="event-location-error"><?= esc($errors['event_location']) ?></div>
                    <?php endif ?>
                </div>
                <div class="mb-3">
                    <label for="registration_url" class="form-label small fw-semibold">Lien d’inscription</label>
                    <input type="url" class="form-control<?= isset($errors['registration_url']) ? ' is-invalid' : '' ?>" id="registration_url" name="registration_url" value="<?= esc(old('registration_url', $post->registration_url ?? ''), 'attr') ?>"<?= isset($errors['registration_url']) ? ' aria-describedby="registration-url-error"' : '' ?>>
                    <?php if (isset($errors['registration_url'])): ?>
                        <div class="invalid-feedback d-block" id="registration-url-error"><?= esc($errors['registration_url']) ?></div>
                    <?php endif ?>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary-green">Enregistrer</button>
                <?php if (! $isNew): ?>
                    <a href="<?= site_url('admin/posts/' . $post->id . '/preview') ?>" class="btn btn-outline-green">Prévisualiser</a>
                <?php endif ?>
            </div>
        </aside>
    </div>
</form>
<?= $this->endSection() ?>
