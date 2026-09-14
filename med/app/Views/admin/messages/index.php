<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$statusLabels = $statuses ?? [];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="section-label">Messagerie</span>
        <h1 class="h3 mb-1">Messages de contact</h1>
        <p class="text-muted mb-0">Consultez, triez et traitez les messages reçus via le formulaire public.</p>
    </div>
    <div>
        <a href="<?= site_url('admin') ?>" class="btn btn-outline-secondary">Retour au tableau de bord</a>
    </div>
</div>

<form class="card-faculte mb-4" method="get" action="<?= site_url('admin/messages') ?>">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="q" class="form-label small fw-semibold">Recherche</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q'] ?? '', 'attr') ?>" placeholder="Nom, courriel, objet ou contenu">
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label small fw-semibold">Statut</label>
            <select class="form-select" id="status" name="status">
                <option value="">Tous les statuts</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= esc($value, 'attr') ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="from" class="form-label small fw-semibold">Du</label>
            <input type="date" class="form-control" id="from" name="from" value="<?= esc($filters['from'] ?? '', 'attr') ?>">
        </div>
        <div class="col-md-2">
            <label for="to" class="form-label small fw-semibold">Au</label>
            <input type="date" class="form-control" id="to" name="to" value="<?= esc($filters['to'] ?? '', 'attr') ?>">
        </div>
        <div class="col-md-2 d-grid gap-2">
            <button type="submit" class="btn btn-primary-green">Filtrer</button>
        </div>
    </div>
</form>

<div class="card-faculte p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Expéditeur</th>
                    <th>Objet</th>
                    <th>Statut</th>
                    <th>Reçu le</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $message): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= esc($message->name) ?></div>
                            <div class="small text-muted"><?= esc($message->email) ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= esc(mb_strimwidth((string) $message->subject, 0, 80, '…')) ?></div>
                            <div class="small text-muted"><?= esc(mb_strimwidth((string) $message->message, 0, 110, '…')) ?></div>
                        </td>
                        <td>
                            <span class="badge <?= esc(site_message_status_badge_class($message->status), 'attr') ?>">
                                <?= esc(site_message_status_label($message->status)) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= esc(site_format_date($message->created_at, true)) ?></td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                <a href="<?= site_url('admin/messages/' . $message->id) ?>" class="btn btn-primary-green btn-sm">Consulter</a>
                                <?php if ($message->status === 'new'): ?>
                                    <form method="post" action="<?= site_url('admin/messages/' . $message->id . '/read') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-green btn-sm">Marquer comme lu</button>
                                    </form>
                                <?php endif ?>
                                <?php if ($message->status !== 'handled'): ?>
                                    <form method="post" action="<?= site_url('admin/messages/' . $message->id . '/handled') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-green btn-sm">Marquer comme traité</button>
                                    </form>
                                <?php endif ?>
                                <?php if ($message->status !== 'archived'): ?>
                                    <form method="post" action="<?= site_url('admin/messages/' . $message->id . '/archive') ?>" data-confirm="Voulez-vous vraiment archiver ce message ?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Archiver</button>
                                    </form>
                                <?php endif ?>
                                <form method="post" action="<?= site_url('admin/messages/' . $message->id . '/delete') ?>" data-confirm="Voulez-vous vraiment supprimer ce message ? Cette action est irréversible.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($messages === []): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Aucun message ne correspond aux filtres.</td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<?= $pager->links('admin_messages', 'site_full') ?>
<?= $this->endSection() ?>
