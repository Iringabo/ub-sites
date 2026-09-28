<?php

namespace App\Services;

/**
 * Faculty palette derived from one brand colour.
 *
 * Every faculty picks a shade of a colour found in the Université du Burundi
 * crest (its green or its red). From `sites.primary_color` this service derives
 * the CSS tokens the public stylesheet consumes, so no public rule needs a
 * hard-coded hue. Text-bearing surfaces are darkened until white text reaches
 * WCAG AA (4.5:1).
 */
class SiteThemeService
{
    public const UB_GREEN = '#0D9B49';
    public const UB_RED   = '#EF1924';
    public const TEXT_DARK = '#1C1C1E';

    public const MIN_CONTRAST = 4.5;

    /**
     * @param \App\Entities\Site|array<string, mixed>|null $site
     *
     * @return array<string, string>
     */
    public function tokensForSite(mixed $site): array
    {
        $primary   = is_array($site) ? ($site['primary_color'] ?? null) : ($site?->primary_color ?? null);
        $secondary = is_array($site) ? ($site['secondary_color'] ?? null) : ($site?->secondary_color ?? null);

        return $this->tokens(is_string($primary) ? $primary : null, is_string($secondary) ? $secondary : null);
    }

    /**
     * @return array<string, string> token name (without `--`) => CSS value
     */
    public function tokens(?string $primary, ?string $secondary = null): array
    {
        $brand = $this->normalizeHex($primary) ?? self::UB_GREEN;
        [$h, $s, $l] = $this->hexToHsl($brand);

        $strong = $this->ensureContrastOnWhite($brand);

        $secondaryHex = $this->normalizeHex($secondary);
        $dark = $secondaryHex !== null && $this->contrastRatio($secondaryHex, '#FFFFFF') >= max(self::MIN_CONTRAST, $this->contrastRatio($brand, '#FFFFFF'))
            ? $secondaryHex
            : $this->hslToHex($h, $s, max(0.08, $l * 0.68));
        if ($this->contrastRatio($dark, '#FFFFFF') < self::MIN_CONTRAST) {
            $dark = $this->ensureContrastOnWhite($dark);
        }

        $deep   = $this->hslToHex($h, min(1.0, $s * 1.05), max(0.06, $l * 0.42));
        $tint   = $this->hslToHex($h, min($s, 0.40), 0.965);
        $soft   = $this->hslToHex($h, min($s, 0.55), 0.90);
        $border = $this->hslToHex($h, min($s, 0.30), 0.90);

        $accent     = $this->isGreenFamily($h) ? self::UB_RED : self::UB_GREEN;
        [$ah, $as]  = $this->hexToHsl($accent);
        $accentSoft = $this->hslToHex($ah, min($as, 0.70), 0.94);

        return [
            'brand'        => $brand,
            'brand-strong' => $strong,
            'brand-text'   => $strong,
            'brand-dark'   => $dark,
            'brand-deep'   => $deep,
            'brand-tint'   => $tint,
            'brand-soft'   => $soft,
            'brand-border' => $border,
            'brand-rgb'    => $this->rgbChannels($brand),
            'brand-deep-rgb' => $this->rgbChannels($deep),
            'on-brand'     => '#FFFFFF',
            'accent'       => $accent,
            'accent-text'  => $this->ensureContrastOnWhite($accent),
            'accent-soft'  => $accentSoft,
        ];
    }

    /**
     * Inline `:root { … }` block for the public layout.
     *
     * @param array<string, string> $tokens
     */
    public function cssVariables(array $tokens): string
    {
        $lines = [];
        foreach ($tokens as $name => $value) {
            $safeName  = preg_replace('/[^a-z0-9-]/', '', strtolower($name)) ?? '';
            $safeValue = preg_replace('/[^#0-9A-Fa-f, ]/', '', $value) ?? '';
            if ($safeName === '' || $safeValue === '') {
                continue;
            }
            $lines[] = '--' . $safeName . ': ' . $safeValue . ';';
        }

        return ':root { ' . implode(' ', $lines) . ' }';
    }

    /**
     * WCAG 2.x contrast ratio between two hex colours (1 to 21).
     */
    public function contrastRatio(string $a, string $b): float
    {
        $la = $this->relativeLuminance($this->normalizeHex($a) ?? '#000000');
        $lb = $this->relativeLuminance($this->normalizeHex($b) ?? '#FFFFFF');
        [$light, $darkL] = $la >= $lb ? [$la, $lb] : [$lb, $la];

        return round(($light + 0.05) / ($darkL + 0.05), 2);
    }

    public function normalizeHex(?string $hex): ?string
    {
        $hex = trim((string) $hex);
        if (preg_match('/^#?([0-9a-fA-F]{6})$/', $hex, $m) === 1) {
            return '#' . strtoupper($m[1]);
        }
        if (preg_match('/^#?([0-9a-fA-F]{3})$/', $hex, $m) === 1) {
            $short = $m[1];

            return '#' . strtoupper($short[0] . $short[0] . $short[1] . $short[1] . $short[2] . $short[2]);
        }

        return null;
    }

    public function isGreenFamily(float $hue): bool
    {
        return $hue >= 60 && $hue <= 200;
    }

    /**
     * Darken a colour (keeping hue and saturation) until white text reaches AA.
     */
    private function ensureContrastOnWhite(string $hex): string
    {
        [$h, $s, $l] = $this->hexToHsl($hex);
        $candidate = $hex;
        $guard = 0;
        while ($this->contrastRatio($candidate, '#FFFFFF') < self::MIN_CONTRAST && $l > 0.02 && $guard < 60) {
            $l -= 0.015;
            $candidate = $this->hslToHex($h, $s, $l);
            $guard++;
        }

        return $candidate;
    }

    private function rgbChannels(string $hex): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);

        return $r . ', ' . $g . ', ' . $b;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($this->normalizeHex($hex) ?? '#000000', '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * @return array{0: float, 1: float, 2: float} hue 0–360, saturation 0–1, lightness 0–1
     */
    private function hexToHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(static fn (int $c): float => $c / 255, $this->hexToRgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d < 0.00001) {
            return [0.0, 0.0, $l];
        }

        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match (true) {
            $max === $r => fmod(($g - $b) / $d, 6),
            $max === $g => ($b - $r) / $d + 2,
            default     => ($r - $g) / $d + 4,
        };
        $h *= 60;
        if ($h < 0) {
            $h += 360;
        }

        return [$h, $s, $l];
    }

    private function hslToHex(float $h, float $s, float $l): string
    {
        $s = max(0.0, min(1.0, $s));
        $l = max(0.0, min(1.0, $l));
        $c = (1 - abs(2 * $l - 1)) * $s;
        $hp = fmod($h, 360) / 60;
        $x = $c * (1 - abs(fmod($hp, 2) - 1));

        [$r1, $g1, $b1] = match (true) {
            $hp < 1 => [$c, $x, 0],
            $hp < 2 => [$x, $c, 0],
            $hp < 3 => [0, $c, $x],
            $hp < 4 => [0, $x, $c],
            $hp < 5 => [$x, 0, $c],
            default => [$c, 0, $x],
        };
        $m = $l - $c / 2;

        return sprintf(
            '#%02X%02X%02X',
            (int) round(($r1 + $m) * 255),
            (int) round(($g1 + $m) * 255),
            (int) round(($b1 + $m) * 255),
        );
    }

    private function relativeLuminance(string $hex): float
    {
        $channels = array_map(static function (int $c): float {
            $v = $c / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $this->hexToRgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
