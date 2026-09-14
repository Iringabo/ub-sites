<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AccessibilityResponsiveTest extends CIUnitTestCase
{
    public function testLayoutsExposeSkipLinksAndAccessibleNavigation(): void
    {
        $publicLayout = file_get_contents(ROOTPATH . 'app/Views/layouts/public.php');
        $adminLayout  = file_get_contents(ROOTPATH . 'app/Views/layouts/admin.php');
        $authLayout   = file_get_contents(ROOTPATH . 'app/Views/auth/layout.php');
        $navbar       = file_get_contents(ROOTPATH . 'app/Views/partials/public_navbar.php');

        $this->assertStringContainsString('skip-link', $publicLayout);
        $this->assertStringContainsString('id="contenu"', $publicLayout);
        $this->assertStringContainsString('skip-link', $adminLayout);
        $this->assertStringContainsString('admin-sidebar', $adminLayout);
        $this->assertStringContainsString('aria-expanded="<?= $isGroupActive ? \'true\' : \'false\' ?>"', $adminLayout);
        $this->assertStringContainsString('id="contenu"', $adminLayout);
        $this->assertStringContainsString('skip-link', $authLayout);
        $this->assertStringContainsString('id="contenu"', $authLayout);
        $this->assertStringContainsString('navbar-light', $navbar);
        $this->assertStringContainsString("lang('Site.nav.main')", $navbar);
        $this->assertStringContainsString('language-switcher', $navbar);
    }

    public function testResponsiveStylesDeclareFocusVisibleAndReducedMotion(): void
    {
        $css = file_get_contents(ROOTPATH . 'public/assets/css/style.css');
        $js  = file_get_contents(ROOTPATH . 'public/assets/js/main.js');

        $this->assertStringContainsString('.skip-link', $css);
        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('.navbar-light .navbar-toggler', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $js);
        $this->assertStringContainsString('scrollIntoView({ behavior: prefersReducedMotion ? \'auto\' : \'smooth\'', $js);
        $this->assertStringContainsString('data-site-hero-carousel', $js);
        $this->assertStringContainsString('window.FacultySite', $js);
        $this->assertStringNotContainsString('window.FSEG', $js);
        $this->assertStringNotContainsString('heroVideo.pause()', $js);
        $this->assertStringNotContainsString('querySelector(\'.hero-video\')', $js);
    }

    public function testVisibleEnglishLabelsAreTranslatedInPublicAndErrorPages(): void
    {
        $contactView = file_get_contents(ROOTPATH . 'app/Views/contact/new.php');
        $loginView   = file_get_contents(ROOTPATH . 'app/Views/auth/login.php');
        $htmlErrors  = file_get_contents(ROOTPATH . 'app/Views/errors/html/error_exception.php');
        $cliErrors   = file_get_contents(ROOTPATH . 'app/Views/errors/cli/error_exception.php');
        $authLang    = file_get_contents(ROOTPATH . 'app/Language/fr/Auth.php');
        $validation  = file_get_contents(ROOTPATH . 'app/Language/fr/Validation.php');

        $this->assertStringContainsString("lang('Site.contact.email')", $contactView);
        $this->assertStringNotContainsString('<p class="fw-bold mb-0">Adresse électronique</p>', $contactView);
        $this->assertStringNotContainsString('<p class="fw-bold mb-0">Email</p>', $contactView);
        $this->assertStringContainsString('Adresse électronique', $loginView);
        $this->assertStringNotContainsString('Adresse email', $loginView);
        $this->assertStringContainsString('Affiché à', $htmlErrors);
        $this->assertStringContainsString('Pile d\'appels', $htmlErrors);
        $this->assertStringContainsString('Méthode HTTP', $htmlErrors);
        $this->assertStringNotContainsString('Backtrace', $htmlErrors);
        $this->assertStringContainsString("Pile d\\'appels :", $cliErrors);
        $this->assertStringContainsString('Cause :', $cliErrors);
        $this->assertStringNotContainsString('Backtrace', $cliErrors);
        $this->assertStringContainsString('adresse électronique', $authLang);
        $this->assertStringContainsString('adresse électronique', $validation);
        $this->assertStringNotContainsString('adresse email', $authLang);
        $this->assertStringNotContainsString('adresse email', $validation);
    }
}
