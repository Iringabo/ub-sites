<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SiteHelperTest extends CIUnitTestCase
{
    public function testMediaUrlDoesNotExposeDataUriDirectly(): void
    {
        helper('site');

        $url = site_media_url('data:image/svg+xml,<svg onload=alert(1)>');

        $this->assertFalse(str_starts_with($url, 'data:'));
        $this->assertStringContainsString('data:image', $url);
    }

    public function testPublicUrlDoesNotExposeJavascriptSchemeDirectly(): void
    {
        helper('site');

        $url = site_public_url('javascript:alert(1)', '#');

        $this->assertFalse(str_starts_with($url, 'javascript:'));
        $this->assertStringContainsString('javascript:alert', $url);
    }

    public function testCssUrlEscapesQuotedStyleBreakout(): void
    {
        helper('site');

        $url = site_css_url("https://example.test/banner.jpg');background-image:url(javascript:alert(1))");

        $this->assertStringContainsString("\\'", $url);
        $this->assertStringNotContainsString("\n", $url);
        $this->assertStringNotContainsString("\r", $url);
    }
}
