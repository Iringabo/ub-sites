<?php

declare(strict_types=1);

$translations = require ROOTPATH . 'vendor/codeigniter4/shield/src/Language/fr/Auth.php';

return array_replace($translations, [
    'invalidEmail'      => 'Impossible de vérifier que l’adresse électronique "{0}" correspond à celle enregistrée.',
    'invalidJWT'        => 'Le jeton est invalide.',
    'expiredJWT'        => 'Le jeton a expiré.',
    'beforeValidJWT'    => 'Le jeton n’est pas encore disponible.',
    'token'             => 'Jeton',
    'magicLinkDisabled' => 'La connexion par lien magique n’est pas autorisée actuellement.',
]);
