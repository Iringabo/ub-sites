<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Settings\Config\Settings as BaseSettings;
use CodeIgniter\Settings\Handlers\ArrayHandler;
use CodeIgniter\Settings\Handlers\DatabaseHandler;
use CodeIgniter\Settings\Handlers\FileHandler;

class Settings extends BaseSettings
{
    /**
     * @var list<string>
     */
    public $handlers = ENVIRONMENT === 'testing' ? ['array'] : ['database'];

    public $array = [
        'class'     => ArrayHandler::class,
        'writeable' => true,
    ];

    public $database = [
        'class'       => DatabaseHandler::class,
        'table'       => 'settings',
        'group'       => null,
        'writeable'   => true,
        'deferWrites' => false,
    ];

    public $file = [
        'class'       => FileHandler::class,
        'path'        => WRITEPATH . 'settings',
        'writeable'   => true,
        'deferWrites' => false,
    ];
}
