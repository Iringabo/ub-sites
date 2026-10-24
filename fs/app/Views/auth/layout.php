<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($this->renderSection('title')) ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/images/logo-placeholder.png') ?>">
    <link href="<?= site_asset_url('assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= site_asset_url('assets/vendor/bootstrap-icons/bootstrap-icons.css') ?>" rel="stylesheet">
    <link href="<?= site_asset_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="bg-light">
    <a class="skip-link" href="#contenu">Aller au contenu principal</a>
    <main id="contenu" tabindex="-1" class="container py-5">
        <?= $this->renderSection('main') ?>
    </main>
</body>
</html>
