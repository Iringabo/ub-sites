<?php
$locale  = service('request')->getLocale() ?: 'fr';
$message = $message ?? lang('Errors.notFoundMessage');
?>
<!doctype html>
<html lang="<?= esc($locale, 'attr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc(lang('Errors.notFound')) ?></title>
    <meta name="description" content="<?= esc(lang('Errors.notFoundMeta')) ?>">
    <link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.css') ?>" rel="stylesheet">
</head>
<body>
    <main class="min-vh-100 d-flex align-items-center bg-white">
        <section class="container text-center py-5">
            <div class="card-faculte mx-auto" style="max-width: 620px;">
                <div class="card-icon mx-auto"><i class="bi bi-compass"></i></div>
                <p class="section-label mb-2"><?= esc(lang('Errors.error404')) ?></p>
                <h1 class="section-title"><?= esc(lang('Errors.notFound')) ?></h1>
                <p class="text-muted"><?= esc($message) ?></p>
                <a href="<?= site_url('/') ?>" class="btn btn-primary-green"><?= esc(lang('Errors.backHome')) ?></a>
            </div>
        </section>
    </main>
</body>
</html>
