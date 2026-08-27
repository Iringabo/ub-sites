<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Connexion | Administration<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card-faculte bg-white">
            <div class="text-center mb-4">
                <img src="<?= base_url('assets/images/logo-placeholder.png') ?>" alt="Logo Université du Burundi" width="72" height="72" class="mb-3">
                <h1 class="h4 mb-1">Connexion à l’administration</h1>
                <p class="text-muted small mb-0">Plateforme des facultés de l’Université du Burundi</p>
            </div>

            <?php if (session('error') !== null): ?>
                <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
            <?php elseif (session('errors') !== null): ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ((array) session('errors') as $error): ?>
                        <div><?= esc($error) ?></div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <?php if (session('message') !== null): ?>
                <div class="alert alert-success" role="alert"><?= esc(session('message')) ?></div>
            <?php endif ?>

            <form action="<?= url_to('login') ?>" method="post">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold small">Adresse électronique</label>
                    <input type="email" class="form-control" id="email" name="email" autocomplete="email" value="<?= old('email') ?>" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold small">Mot de passe</label>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                </div>

                <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
                    <div class="form-check mb-4">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember" <?php if (old('remember')): ?>checked<?php endif ?>>
                        <label class="form-check-label small" for="remember">Se souvenir de moi</label>
                    </div>
                <?php endif ?>

                <button type="submit" class="btn btn-primary-green w-100">Se connecter</button>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
