<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->setAutoRoute(false);

service('auth')->routes($routes, ['except' => ['register', 'login', 'magic-link']]);

$routes->get('login', '\CodeIgniter\Shield\Controllers\LoginController::loginView', ['as' => 'login', 'filter' => 'auth-rates']);
$routes->post('login', '\CodeIgniter\Shield\Controllers\LoginController::loginAction', ['filter' => 'auth-rates']);

$routes->match(['get', 'head'], 'healthz', 'HealthController::show');
$routes->match(['get', 'head'], '/', 'Home::index');
$routes->get('language/(:segment)', 'LanguageController::set/$1', ['as' => 'language.set']);
$routes->get('faculte', 'PublicPageController::faculty');
$routes->get('formations', 'PublicPageController::programmes');
$routes->get('formations/(:segment)', 'PublicPageController::programmeDetail/$1');
$routes->get('recherche', 'PublicPageController::research');
$routes->get('corps-enseignant', 'PublicPageController::staff');
$routes->get('corps-enseignant/(:segment)', 'PublicPageController::staffDetail/$1');
$routes->get('actualites', 'PublicPageController::posts');
$routes->get('actualites/(:segment)', 'PublicPageController::postDetail/$1');
$routes->get('alumni', 'PublicPageController::alumni');
$routes->get('contact', 'ContactController::new');
$routes->post('contact', 'ContactController::create');

$routes->group('admin', ['namespace' => 'App\Controllers\Admin', 'filter' => ['session', 'permission:admin.access', 'adminAccess']], static function (RouteCollection $routes): void {
    $routes->get('', 'DashboardController::index');
    $routes->head('', 'DashboardController::index');
    $routes->match(['get', 'head'], '/', 'DashboardController::index', ['as' => 'admin.dashboard']);
    $routes->post('site-selection', 'SiteController::select', ['as' => 'admin.sites.select']);

    $routes->group('faculty', ['filter' => 'permission:pages.manage'], static function (RouteCollection $routes): void {
        $routes->get('profile', 'FacultyProfileController::edit', ['as' => 'admin.faculty.profile']);
        $routes->post('profile', 'FacultyProfileController::update', ['as' => 'admin.faculty.profile.update']);
    });

    $routes->group('messages', ['filter' => 'permission:messages.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'MessageController::index', ['as' => 'admin.messages.index']);
        $routes->get('(:num)', 'MessageController::show/$1', ['as' => 'admin.messages.show']);
        $routes->post('(:num)/read', 'MessageController::markRead/$1', ['as' => 'admin.messages.read']);
        $routes->post('(:num)/handled', 'MessageController::markHandled/$1', ['as' => 'admin.messages.handled']);
        $routes->post('(:num)/archive', 'MessageController::archive/$1', ['as' => 'admin.messages.archive']);
        $routes->post('(:num)/delete', 'MessageController::delete/$1', ['as' => 'admin.messages.delete']);
    });

    $routes->group('users', ['filter' => 'permission:users.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'UserController::index', ['as' => 'admin.users.index']);
        $routes->get('new', 'UserController::new', ['as' => 'admin.users.new']);
        $routes->post('/', 'UserController::create', ['as' => 'admin.users.create']);
        $routes->get('(:num)/edit', 'UserController::edit/$1', ['as' => 'admin.users.edit']);
        $routes->post('(:num)', 'UserController::update/$1', ['as' => 'admin.users.update']);
        $routes->post('(:num)/activate', 'UserController::activate/$1', ['as' => 'admin.users.activate']);
        $routes->post('(:num)/deactivate', 'UserController::deactivate/$1', ['as' => 'admin.users.deactivate']);
        $routes->get('(:num)/password', 'UserController::password/$1', ['as' => 'admin.users.password']);
        $routes->post('(:num)/password', 'UserController::resetPassword/$1', ['as' => 'admin.users.password.update']);
    });

    $routes->group('posts', ['filter' => 'permission:news.manage,events.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'PostController::index', ['as' => 'admin.posts.index']);
        $routes->get('new', 'PostController::new', ['as' => 'admin.posts.new']);
        $routes->post('/', 'PostController::create', ['as' => 'admin.posts.create']);
        $routes->get('(:num)/edit', 'PostController::edit/$1', ['as' => 'admin.posts.edit']);
        $routes->post('(:num)', 'PostController::update/$1', ['as' => 'admin.posts.update']);
        $routes->get('(:num)/preview', 'PostController::preview/$1', ['as' => 'admin.posts.preview']);
        $routes->post('(:num)/publish', 'PostController::publish/$1', ['as' => 'admin.posts.publish']);
        $routes->post('(:num)/schedule', 'PostController::schedule/$1', ['as' => 'admin.posts.schedule']);
        $routes->post('(:num)/archive', 'PostController::archive/$1', ['as' => 'admin.posts.archive']);
        $routes->post('(:num)/draft', 'PostController::draft/$1', ['as' => 'admin.posts.draft']);
    });

    $adminResources = [
        'home-content'      => 'home.manage',
        'home-hero-slides'  => 'home.manage',
        'home-highlights'   => 'home.manage',
        'site-stats'        => 'home.manage',
        'programmes'        => 'programmes.manage',
        'staff'             => 'staff.manage',
        'laboratories'      => 'research.manage',
        'publications'      => 'research.manage',
        'research-projects' => 'research.manage',
        'timeline-items'    => 'pages.manage',
        'content-blocks'    => 'pages.manage',
        'alumni-profiles'   => 'alumni.manage',
        'testimonials'      => 'alumni.manage',
        'pages'             => 'pages.manage',
        'settings'          => 'settings.manage',
        'sites'             => 'sites.manage',
    ];

    foreach ($adminResources as $segment => $permission) {
        $routes->group($segment, ['filter' => 'permission:' . $permission], static function (RouteCollection $routes) use ($segment): void {
            $routes->get('/', 'ResourceController::index/' . $segment);
            $routes->get('new', 'ResourceController::new/' . $segment);
            $routes->post('/', 'ResourceController::create/' . $segment);
            $routes->get('(:num)/edit', 'ResourceController::edit/' . $segment . '/$1');
            $routes->post('(:num)', 'ResourceController::update/' . $segment . '/$1');
            $routes->post('(:num)/delete', 'ResourceController::delete/' . $segment . '/$1');
            $routes->post('(:num)/restore', 'ResourceController::restore/' . $segment . '/$1');
            $routes->post('(:num)/purge', 'ResourceController::purge/' . $segment . '/$1');
        });
    }
});
