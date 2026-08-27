<?php

namespace Config;

use App\Services\HomePageService;
use App\Services\ContactNotificationService;
use App\Services\AdminAccessService;
use App\Services\AdminDashboardService;
use App\Services\ContentTranslationService;
use App\Services\FacultySiteProvisioningService;
use App\Services\MediaService;
use App\Services\PostVisibilityService;
use App\Services\SettingsService;
use App\Services\SiteResolverService;
use App\Services\UserAdministrationService;
use App\Services\SlugService;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function settingsService(bool $getShared = true): SettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('settingsService');
        }

        return new SettingsService();
    }

    public static function siteResolver(bool $getShared = true): SiteResolverService
    {
        if ($getShared) {
            return static::getSharedInstance('siteResolver');
        }

        return new SiteResolverService();
    }

    public static function adminAccess(bool $getShared = true): AdminAccessService
    {
        if ($getShared) {
            return static::getSharedInstance('adminAccess');
        }

        return new AdminAccessService();
    }

    public static function facultySiteProvisioning(bool $getShared = true): FacultySiteProvisioningService
    {
        if ($getShared) {
            return static::getSharedInstance('facultySiteProvisioning');
        }

        return new FacultySiteProvisioningService();
    }

    public static function contentTranslationService(bool $getShared = true): ContentTranslationService
    {
        if ($getShared) {
            return static::getSharedInstance('contentTranslationService');
        }

        return new ContentTranslationService();
    }

    public static function homePageService(bool $getShared = true): HomePageService
    {
        if ($getShared) {
            return static::getSharedInstance('homePageService');
        }

        return new HomePageService();
    }

    public static function contactNotificationService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('contactNotificationService');
        }

        return new ContactNotificationService();
    }

    public static function adminDashboardService(bool $getShared = true): AdminDashboardService
    {
        if ($getShared) {
            return static::getSharedInstance('adminDashboardService');
        }

        return new AdminDashboardService();
    }

    public static function userAdministrationService(bool $getShared = true): UserAdministrationService
    {
        if ($getShared) {
            return static::getSharedInstance('userAdministrationService');
        }

        return new UserAdministrationService();
    }

    public static function postVisibilityService(bool $getShared = true): PostVisibilityService
    {
        if ($getShared) {
            return static::getSharedInstance('postVisibilityService');
        }

        return new PostVisibilityService();
    }

    public static function slugService(bool $getShared = true): SlugService
    {
        if ($getShared) {
            return static::getSharedInstance('slugService');
        }

        return new SlugService();
    }

    public static function mediaService(bool $getShared = true): MediaService
    {
        if ($getShared) {
            return static::getSharedInstance('mediaService');
        }

        return new MediaService();
    }
}
