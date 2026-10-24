<?php if (session('message') !== null): ?>
    <div class="container mt-4">
        <div class="alert alert-success mb-0" role="alert"><?= esc(session('message')) ?></div>
    </div>
<?php endif ?>

<?php if (session('error') !== null): ?>
    <div class="container mt-4">
        <div class="alert alert-danger mb-0" role="alert"><?= esc(session('error')) ?></div>
    </div>
<?php endif ?>

<?php if (session('errors') !== null && is_array(session('errors'))): ?>
    <div class="container mt-4">
        <div class="alert alert-danger mb-0" role="alert">
            <p class="fw-semibold mb-2">Veuillez corriger les champs indiqués.</p>
            <ul class="mb-0 ps-3">
                <?php foreach (session('errors') as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
<?php endif ?>
