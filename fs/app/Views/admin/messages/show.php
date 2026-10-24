<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$isReadable = in_array($message->status, ['new', 'read'], true);
$isArchivable = $message->status !== 'archived';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <span class="section-label">Messagerie</span>
        <h1 class="h3 mb-1"><?= esc($message->subject) ?></h1>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge <?= esc(site_message_status_badge_class($message->status), 'attr') ?>">
                <?= esc(site_message_status_label($message->status)) ?>
            </span>
            <span class="text-muted small"><?= esc(site_format_date($message->created_at, true)) ?></span>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= site_url('admin/messages') ?>" class="btn btn-outline-secondary">Retour à la liste</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <article class="card-faculte h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">Message</h2>
                    <p class="text-muted mb-0">Le contenu est affiché comme texte échappé.</p>
                </div>
            </div>

            <div class="border rounded-3 bg-white p-3" style="white-space: pre-wrap; word-break: break-word;">
                <?= esc($message->message) ?>
            </div>
        </article>
    </div>

    <div class="col-lg-4">
        <article class="card-faculte h-100">
            <h2 class="h5 mb-3">Informations</h2>

            <dl class="mb-0">
                <dt class="small text-muted">Nom</dt>
                <dd class="fw-semibold mb-3"><?= esc($message->name) ?></dd>

                <dt class="small text-muted">Adresse électronique</dt>
                <dd class="fw-semibold mb-3"><a href="mailto:<?= esc($message->email, 'attr') ?>"><?= esc($message->email) ?></a></dd>

                <dt class="small text-muted">Téléphone</dt>
                <dd class="fw-semibold mb-3"><?= esc($message->phone ?: '—') ?></dd>

                <dt class="small text-muted">Objet</dt>
                <dd class="fw-semibold mb-3"><?= esc($message->subject) ?></dd>

                <dt class="small text-muted">Reçu le</dt>
                <dd class="fw-semibold mb-3"><?= esc(site_format_date($message->created_at, true)) ?></dd>

                <dt class="small text-muted">Lu le</dt>
                <dd class="fw-semibold mb-3"><?= esc(site_format_date($message->read_at, true) ?: '—') ?></dd>

                <dt class="small text-muted">Traité par</dt>
                <dd class="fw-semibold mb-0">
                    <?php if ($processedBy !== null): ?>
                        <?= esc($processedBy->username ?: $processedBy->getEmail()) ?>
                    <?php else: ?>
                        —
                    <?php endif ?>
                </dd>
            </dl>
        </article>
    </div>
</div>

<div class="card-faculte mt-4">
    <h2 class="h5 mb-3">Actions</h2>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($isReadable): ?>
            <form method="post" action="<?= site_url('admin/messages/' . $message->id . '/read') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-green">Marquer comme lu</button>
            </form>
        <?php endif ?>
        <?php if ($message->status !== 'handled'): ?>
            <form method="post" action="<?= site_url('admin/messages/' . $message->id . '/handled') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary-green">Marquer comme traité</button>
            </form>
        <?php endif ?>
        <?php if ($isArchivable): ?>
            <form method="post" action="<?= site_url('admin/messages/' . $message->id . '/archive') ?>" data-confirm="Voulez-vous vraiment archiver ce message ?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-secondary">Archiver</button>
            </form>
        <?php endif ?>
        <form method="post" action="<?= site_url('admin/messages/' . $message->id . '/delete') ?>" data-confirm="Voulez-vous vraiment supprimer ce message ? Cette action est irréversible.">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger">Supprimer</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
