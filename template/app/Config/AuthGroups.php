<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'editor';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * An associative array of the available groups in the system, where the keys
     * are the group names and the values are arrays of the group info.
     *
     * Whatever value you assign as the key will be used to refer to the group
     * when using functions such as:
     *      $user->addGroup('superadmin');
     *
     * @var array<string, array<string, string>>
     *
     * @see https://codeigniter4.github.io/shield/quick_start_guide/using_authorization/#change-available-groups for more info
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Superadministrateur',
            'description' => 'Accès complet au site, aux utilisateurs et aux paramètres.',
        ],
        'admin' => [
            'title'       => 'Administrateur',
            'description' => 'Gestion quotidienne des contenus et des messages.',
        ],
        'editor' => [
            'title'       => 'Éditeur',
            'description' => 'Gestion éditoriale des contenus autorisés.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * The available permissions in the system.
     *
     * If a permission is not listed here it cannot be used.
     */
    public array $permissions = [
        'admin.access'      => 'Accéder à l’administration',
        'home.manage'       => 'Gérer la page d’accueil',
        'news.manage'       => 'Gérer les actualités',
        'events.manage'     => 'Gérer les événements',
        'programmes.manage' => 'Gérer les formations',
        'staff.manage'      => 'Gérer le personnel',
        'research.manage'   => 'Gérer la recherche',
        'alumni.manage'     => 'Gérer les alumni',
        'pages.manage'      => 'Gérer les pages institutionnelles',
        'messages.manage'   => 'Gérer les messages de contact',
        'users.manage'      => 'Gérer les utilisateurs',
        'settings.manage'   => 'Gérer les paramètres',
        'sites.manage'      => 'Gérer les sites facultaires',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Maps permissions to groups.
     *
     * This defines group-level permissions.
     */
    public array $matrix = [
        'superadmin' => [
            'admin.*',
            'home.*',
            'news.*',
            'events.*',
            'programmes.*',
            'staff.*',
            'research.*',
            'alumni.*',
            'pages.*',
            'messages.*',
            'users.*',
            'settings.*',
            'sites.*',
        ],
        'admin' => [
            'admin.access',
            'home.manage',
            'news.manage',
            'events.manage',
            'programmes.manage',
            'staff.manage',
            'research.manage',
            'alumni.manage',
            'pages.manage',
            'messages.manage',
            'users.manage',
        ],
        'editor' => [
            'admin.access',
            'home.manage',
            'news.manage',
            'events.manage',
            'programmes.manage',
            'staff.manage',
            'research.manage',
            'alumni.manage',
            'pages.manage',
        ],
    ];
}
