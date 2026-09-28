<?php

use App\Services\FacultyDemoDataService;
use App\Services\SiteThemeService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SiteThemeServiceTest extends CIUnitTestCase
{
    private SiteThemeService $theme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->theme = new SiteThemeService();
    }

    public function testDefaultsToUbGreenWhenColourIsMissingOrInvalid(): void
    {
        foreach ([null, '', 'not-a-colour', '#12', 'rgb(1,2,3)'] as $value) {
            $tokens = $this->theme->tokens($value);
            $this->assertSame(SiteThemeService::UB_GREEN, $tokens['brand'], var_export($value, true));
        }

        $this->assertSame('#0D9B49', $this->theme->normalizeHex('0d9b49'));
        $this->assertSame('#AABBCC', $this->theme->normalizeHex('#abc'));
        $this->assertNull($this->theme->normalizeHex('#zzz'));
    }

    public function testEveryFacultyPaletteDerivesAccessibleShades(): void
    {
        foreach (FacultyDemoDataService::BRAND_COLORS as $slug => $hex) {
            $tokens = $this->theme->tokens($hex);

            $this->assertSame(strtoupper($hex), $tokens['brand'], $slug);
            foreach (['brand-strong', 'brand-text', 'brand-dark', 'brand-deep', 'brand-tint', 'brand-soft', 'brand-border', 'accent', 'accent-text', 'accent-soft'] as $key) {
                $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $tokens[$key], $slug . ' ' . $key);
            }
            $this->assertMatchesRegularExpression('/^\d{1,3}, \d{1,3}, \d{1,3}$/', $tokens['brand-rgb'], $slug);
            $this->assertSame('#FFFFFF', $tokens['on-brand']);

            // Text and button surfaces must reach WCAG AA against white / on-brand.
            $this->assertGreaterThanOrEqual(SiteThemeService::MIN_CONTRAST, $this->theme->contrastRatio($tokens['brand-strong'], '#FFFFFF'), $slug . ' strong');
            $this->assertGreaterThanOrEqual(SiteThemeService::MIN_CONTRAST, $this->theme->contrastRatio($tokens['brand-text'], '#FFFFFF'), $slug . ' text');
            $this->assertGreaterThanOrEqual(SiteThemeService::MIN_CONTRAST, $this->theme->contrastRatio($tokens['brand-dark'], '#FFFFFF'), $slug . ' dark');
            $this->assertGreaterThanOrEqual(SiteThemeService::MIN_CONTRAST, $this->theme->contrastRatio($tokens['accent-text'], '#FFFFFF'), $slug . ' accent text');
            $this->assertGreaterThanOrEqual(7.0, $this->theme->contrastRatio($tokens['brand-deep'], '#FFFFFF'), $slug . ' deep');

            // Tints stay light so dark body text remains readable on them.
            $this->assertGreaterThanOrEqual(SiteThemeService::MIN_CONTRAST, $this->theme->contrastRatio(SiteThemeService::TEXT_DARK, $tokens['brand-tint']), $slug . ' tint');
            $this->assertGreaterThanOrEqual(SiteThemeService::MIN_CONTRAST, $this->theme->contrastRatio(SiteThemeService::TEXT_DARK, $tokens['brand-soft']), $slug . ' soft');
        }
    }

    public function testAccentIsTheOtherLogoColour(): void
    {
        $this->assertSame(SiteThemeService::UB_RED, $this->theme->tokens('#0D9B49')['accent'], 'green faculties accent with UB red');
        $this->assertSame(SiteThemeService::UB_RED, $this->theme->tokens('#4E8F2F')['accent']);
        $this->assertSame(SiteThemeService::UB_GREEN, $this->theme->tokens('#C8102E')['accent'], 'red faculties accent with UB green');
        $this->assertSame(SiteThemeService::UB_GREEN, $this->theme->tokens('#8B1E2D')['accent']);
        $this->assertSame(SiteThemeService::UB_GREEN, $this->theme->tokens('#1D4ED8')['accent'], 'non-green hues fall back to green accent');
    }

    public function testSecondaryColourOverridesDarkShadeOnlyWhenAccessible(): void
    {
        $withValidSecondary = $this->theme->tokens('#0D9B49', '#0B6F38');
        $this->assertSame('#0B6F38', $withValidSecondary['brand-dark']);

        $withTooLightSecondary = $this->theme->tokens('#0D9B49', '#9AE6B4');
        $this->assertNotSame('#9AE6B4', $withTooLightSecondary['brand-dark']);
        $this->assertSame($this->theme->tokens('#0D9B49')['brand-dark'], $withTooLightSecondary['brand-dark']);
    }

    public function testTokensForSiteAcceptsEntitiesAndArrays(): void
    {
        $fromArray = $this->theme->tokensForSite(['primary_color' => '#C8102E', 'secondary_color' => '#880B1F']);
        $this->assertSame('#C8102E', $fromArray['brand']);
        $this->assertSame('#880B1F', $fromArray['brand-dark']);

        $entity = new class () {
            public string $primary_color = '#4E8F2F';
            public ?string $secondary_color = null;
        };
        $this->assertSame('#4E8F2F', $this->theme->tokensForSite($entity)['brand']);
        $this->assertSame(SiteThemeService::UB_GREEN, $this->theme->tokensForSite(null)['brand']);
    }

    public function testCssVariablesEmitsSanitizedRootBlock(): void
    {
        $css = $this->theme->cssVariables($this->theme->tokens('#C8102E'));

        $this->assertStringStartsWith(':root {', trim($css));
        $this->assertStringContainsString('--brand: #C8102E;', $css);
        $this->assertStringContainsString('--brand-rgb: 200, 16, 46;', $css);
        $this->assertStringContainsString('--on-brand: #FFFFFF;', $css);
        $this->assertStringNotContainsString('<', $css);
        $this->assertStringNotContainsString('}', substr($css, 0, strrpos($css, '}')));
    }
}
