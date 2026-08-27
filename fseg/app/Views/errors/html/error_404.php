<?php
$message = $message ?? 'La page demandée est introuvable ou n’est plus disponible.';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page introuvable</title>
    <meta name="description" content="La page demandée est introuvable.">
    <link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <main class="min-vh-100 d-flex align-items-center bg-white">
        <section class="container text-center py-5">
            <div class="card-faculte mx-auto" style="max-width: 620px;">
                <div class="card-icon mx-auto"><i class="bi bi-compass"></i></div>
                <p class="section-label mb-2">Erreur 404</p>
                <h1 class="section-title">Page introuvable</h1>
                <p class="text-muted"><?= esc($message) ?></p>
                <a href="<?= site_url('/') ?>" class="btn btn-primary-green">Retour à l’accueil</a>
            </div>
        </section>
    </main>
</body>
</html>
