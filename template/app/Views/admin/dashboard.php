<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$newsCounts = $dashboard['newsStatusCounts'] ?? [];
$events = $dashboard['upcomingEvents'] ?? [];
$recentChanges = $dashboard['recentChanges'] ?? [];
$messageCounts = $dashboard['messageStatusCounts'] ?? [];
$onboarding = $onboarding ?? null;
$currentUser = auth()->user();
?>

<?php if (is_array($onboarding) && ($onboarding['done'] ?? 0) < ($onboarding['total'] ?? 0) && ($onboarding['total'] ?? 0) > 0): ?>
    <div class="card-faculte mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h5 mb-0"><i class="bi bi-list-check me-2"></i>Mettre votre site en ligne</h2>
            <span class="badge text-bg-success"><?= (int) $onboarding['done'] ?> / <?= (int) $onboarding['total'] ?> étapes</span>
        </div>
        <div class="progress mb-3" style="height: 6px;" role="progressbar" aria-label="Progression de configuration">
            <div class="progress-bar bg-success" style="width: <?= (int) round(100 * $onboarding['done'] / max(1, $onboarding['total'])) ?>%"></div>
        </div>
        <div class="row g-2">
            <?php foreach ($onboarding['items'] as $item): ?>
                <div class="col-md-6">
                    <a href="<?= esc($item['url'], 'attr') ?>" class="d-flex align-items-center gap-2 text-decoration-none link-dark">
                        <i class="bi bi-<?= $item['done'] ? 'check-circle-fill text-success' : 'circle text-secondary' ?>"></i>
                        <span class="<?= $item['done'] ? 'text-decoration-line-through text-muted' : '' ?>"><?= esc($item['label']) ?></span>
                    </a>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <span class="section-label">Administration</span>
        <h1 class="h3 mb-1">Tableau de bord</h1>
        <p class="text-muted mb-0">Bienvenue<?= $currentUser?->getEmail() ? ', ' . esc($currentUser->getEmail()) : '' ?>.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($currentUser?->can('news.manage')): ?>
            <a href="<?= site_url('admin/posts/new?type=news') ?>" class="btn btn-primary-green">Nouvelle actualité</a>
        <?php endif ?>
        <?php if ($currentUser?->can('events.manage')): ?>
            <a href="<?= site_url('admin/posts/new?type=event') ?>" class="btn btn-outline-green">Nouvel événement</a>
        <?php endif ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <article class="card-faculte h-100">
            <div class="card-icon"><i class="bi bi-newspaper"></i></div>
            <h2 class="h5">Actualités par statut</h2>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php foreach (service('postVisibilityService')->statuses() as $status => $label): ?>
                    <span class="badge text-bg-light">
                        <?= esc($label) ?> : <?= esc((string) ($newsCounts[$status] ?? 0)) ?>
                    </span>
                <?php endforeach ?>
            </div>
        </article>
    </div>
    <div class="col-lg-4">
        <article class="card-faculte h-100">
            <div class="card-icon"><i class="bi bi-calendar-event"></i></div>
            <h2 class="h5">Événements à venir</h2>
            <p class="display-6 fw-bold mb-1"><?= esc((string) count($events)) ?></p>
            <p class="text-muted small mb-0">Événements visibles et planifiés pour les prochains jours.</p>
            <?php if ($events !== []): ?>
                <ul class="list-unstyled mt-3 mb-0">
                    <?php foreach (array_slice($events, 0, 3) as $event): ?>
                        <li class="mb-2">
                            <a href="<?= esc($event['url'], 'attr') ?>" class="fw-semibold text-decoration-none"><?= esc($event['title']) ?></a>
                            <div class="small text-muted">
                                <?= esc(site_format_date($event['starts_at'], true)) ?>
                                <?= $event['location'] ? ' · ' . esc($event['location']) : '' ?>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </article>
    </div>
    <div class="col-lg-4">
        <article class="card-faculte h-100">
            <div class="card-icon"><i class="bi bi-bell"></i></div>
            <h2 class="h5">Messages de contact</h2>
            <p class="display-6 fw-bold mb-1"><?= esc((string) ($dashboard['newMessages'] ?? 0)) ?></p>
            <p class="text-muted small mb-0">Messages nouveaux à traiter.</p>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php foreach (['new', 'read', 'handled', 'archived'] as $status): ?>
                    <span class="badge text-bg-light">
                        <?= esc(site_message_status_label($status)) ?> : <?= esc((string) ($messageCounts[$status] ?? 0)) ?>
                    </span>
                <?php endforeach ?>
            </div>
            <div class="mt-3">
                <a href="<?= site_url('admin/messages') ?>" class="small text-decoration-none">Ouvrir la boîte de réception</a>
            </div>
        </article>
    </div>
    <div class="col-md-6 col-lg-3">
        <article class="card-faculte h-100">
            <div class="card-icon"><i class="bi bi-mortarboard"></i></div>
            <h2 class="h6">Formations publiées</h2>
            <p class="display-6 fw-bold mb-1"><?= esc((string) ($dashboard['publishedProgrammes'] ?? 0)) ?></p>
        </article>
    </div>
    <div class="col-md-6 col-lg-3">
        <article class="card-faculte h-100">
            <div class="card-icon"><i class="bi bi-people"></i></div>
            <h2 class="h6">Personnel publié</h2>
            <p class="display-6 fw-bold mb-1"><?= esc((string) ($dashboard['publishedStaff'] ?? 0)) ?></p>
        </article>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <article class="card-faculte h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0">Derniers contenus modifiés</h2>
                <a href="<?= site_url('admin/posts') ?>" class="small text-decoration-none">Voir les contenus</a>
            </div>
            <?php if ($recentChanges !== []): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentChanges as $change): ?>
                        <a href="<?= esc($change['url'], 'attr') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold">
                                    <i class="bi <?= esc($change['icon'], 'attr') ?> me-1"></i>
                                    <?= esc($change['title']) ?>
                                </div>
                                <div class="small text-muted">
                                    <?= esc($change['label']) ?><?= $change['detail'] ? ' · ' . esc($change['detail']) : '' ?>
                                </div>
                            </div>
                            <div class="small text-muted text-nowrap">
                                <?= esc(site_format_date($change['updated_at'], true)) ?>
                            </div>
                        </a>
                    <?php endforeach ?>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">Aucun contenu récent à afficher.</p>
            <?php endif ?>
        </article>
    </div>
    <div class="col-lg-5">
        <article class="card-faculte h-100">
            <h2 class="h5">Raccourcis utiles</h2>
            <div class="d-grid gap-2">
                <a href="<?= site_url('admin/posts/new?type=news') ?>" class="btn btn-outline-green">Créer une actualité</a>
                <a href="<?= site_url('admin/posts/new?type=event') ?>" class="btn btn-outline-green">Créer un événement</a>
                <a href="<?= site_url('admin/users') ?>" class="btn btn-outline-secondary">Gérer les utilisateurs</a>
            </div>
        </article>
    </div>
</div>
<?= $this->endSection() ?>
