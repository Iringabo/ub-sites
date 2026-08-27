<?php
$message = $message ?? lang('Errors.weHitASnag');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc(lang('Errors.whoops')) ?></title>
    <meta name="description" content="<?= esc(lang('Errors.weHitASnag')) ?>">
    <link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <main class="min-vh-100 d-flex align-items-center bg-white">
        <section class="container text-center py-5">
            <div class="card-faculte mx-auto" style="max-width: 620px;">
                <div class="card-icon mx-auto"><i class="bi bi-exclamation-triangle"></i></div>
                <p class="section-label mb-2">Erreur 500</p>
                <h1 class="section-title"><?= esc(lang('Errors.whoops')) ?></h1>
                <p class="text-muted"><?= esc($message) ?></p>
                <a href="<?= site_url('/') ?>" class="btn btn-primary-green">Retour à l’accueil</a>
            </div>
        </section>
    </main>
</body>
</html>
