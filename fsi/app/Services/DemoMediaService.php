<?php

namespace App\Services;

/**
 * Builds unique per-site demo images under public/uploads/sites/{slug}/ only.
 */
class DemoMediaService
{
    public const SHARED_LOGO = 'assets/images/logo-placeholder.png';

    private MediaService $media;

    public function __construct(?MediaService $media = null)
    {
        $this->media = $media ?? new MediaService();
    }

    /**
     * Copy pack photos for heroes/banners/covers; generate people portraits into staff/alumni.
     * Logo is the shared static crest (not a generated tile).
     *
     * @return array{heroes: list<string>, banner: ?string, logo: string, staff: list<string>, posts: list<string>, alumni: list<string>}
     */
    public function materializeSiteGallery(string $slug, string $facultyLabel, string $primaryColor = '#0D9B49'): array
    {
        $slug = $this->normalizeSlug($slug);
        $packDir = $this->packDirectory($slug);
        $sharedHeroDir = $this->sharedHeroDirectory();

        $heroes = [];
        foreach (['hero-1.jpg', 'hero-2.jpg', 'hero-3.jpg'] as $file) {
            $source = $packDir . DIRECTORY_SEPARATOR . $file;
            if (! is_file($source)) {
                continue;
            }
            $path = $this->media->storeLocalFileForSite($slug, 'hero', $source);
            if ($path !== null) {
                $heroes[] = $path;
            }
        }

        $bannerSource = $packDir . DIRECTORY_SEPARATOR . 'banner.jpg';
        $banner = null;
        if (is_file($bannerSource)) {
            $banner = $this->media->storeLocalFileForSite($slug, 'banners', $bannerSource);
        }
        if ($banner === null) {
            foreach (['hero-1.jpg', 'hero-2.jpg', 'hero-3.jpg'] as $file) {
                $fallback = $packDir . DIRECTORY_SEPARATOR . $file;
                if (! is_file($fallback)) {
                    continue;
                }
                $banner = $this->media->storeLocalFileForSite($slug, 'banners', $fallback);
                if ($banner !== null) {
                    break;
                }
            }
        }

        $coverSources = $this->coverSources($packDir, $sharedHeroDir);
        $posts = [];
        for ($i = 0; $i < 12; $i++) {
            if ($coverSources === []) {
                break;
            }
            $source = $coverSources[$i % count($coverSources)];
            $path = $this->media->storeLocalFileForSite($slug, 'posts', $source);
            if ($path !== null) {
                $posts[] = $path;
            }
        }

        $staff = [];
        $alumni = [];

        return [
            'heroes' => $heroes,
            'banner' => $banner,
            'logo'   => self::SHARED_LOGO,
            'staff'  => $staff,
            'posts'  => $posts,
            'alumni' => $alumni,
        ];
    }

    /**
     * Square portrait with large initials — stored under staff/ or alumni/, never hero/banners.
     */
    public function portraitJpeg(
        string $slug,
        string $folder,
        string $facultyLabel,
        string $personName,
        string $hexColor,
        int $size = 640,
    ): ?string {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = imagecreatetruecolor($size, $size);
        if ($image === false) {
            return null;
        }

        [$r, $g, $b] = $this->hexToRgb($hexColor);
        $bg = imagecolorallocate($image, $r, $g, $b);
        $fg = imagecolorallocate($image, 255, 255, 255);
        $band = imagecolorallocate($image, max(0, $r - 18), max(0, $g - 18), max(0, $b - 18));
        imagefilledrectangle($image, 0, 0, $size, $size, $bg);
        imagefilledellipse($image, (int) ($size * 0.5), (int) ($size * 0.42), (int) ($size * 0.62), (int) ($size * 0.62), $band);

        $initials = $this->initials($personName);
        $font = 5;
        $charW = imagefontwidth($font);
        $charH = imagefontheight($font);
        $textW = $charW * strlen($initials);
        $x = (int) (($size - $textW) / 2);
        $y = (int) (($size * 0.42) - ($charH / 2));
        imagestring($image, $font, $x, $y, $initials, $fg);

        $caption = $this->truncate($personName, 28);
        $capW = imagefontwidth(3) * strlen($caption);
        imagestring($image, 3, (int) (($size - $capW) / 2), (int) ($size * 0.78), $caption, $fg);
        $faculty = $this->truncate($facultyLabel, 32);
        $facW = imagefontwidth(2) * strlen($faculty);
        imagestring($image, 2, (int) (($size - $facW) / 2), (int) ($size * 0.86), $faculty, $fg);

        ob_start();
        imagejpeg($image, null, 88);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        if ($binary === '') {
            return null;
        }

        return $this->media->storeBinaryForSite($this->normalizeSlug($slug), $folder, $binary, 'jpg');
    }

    public function labeledJpeg(
        string $slug,
        string $folder,
        string $facultyLabel,
        string $caption,
        string $hexColor,
        int $width,
        int $height,
    ): ?string {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            return null;
        }

        [$r, $g, $b] = $this->hexToRgb($hexColor);
        $bg = imagecolorallocate($image, $r, $g, $b);
        $fg = imagecolorallocate($image, 255, 255, 255);
        $muted = imagecolorallocate($image, 230, 240, 235);
        imagefilledrectangle($image, 0, 0, $width, $height, $bg);

        // Decorative band
        imagefilledrectangle($image, 0, (int) ($height * 0.72), $width, $height, $muted);

        $line1 = $this->truncate($facultyLabel, 42);
        $line2 = $this->truncate($caption, 48);
        $line3 = strtoupper($this->normalizeSlug($slug));

        imagestring($image, 5, 40, (int) ($height * 0.28), $line1, $fg);
        imagestring($image, 5, 40, (int) ($height * 0.38), $line2, $fg);
        imagestring($image, 3, 40, (int) ($height * 0.80), $line3 . ' · démo locale', $bg);

        ob_start();
        imagejpeg($image, null, 85);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        if ($binary === '') {
            return null;
        }

        return $this->media->storeBinaryForSite($this->normalizeSlug($slug), $folder, $binary, 'jpg');
    }

    public function packDirectory(string $slug): string
    {
        $slug = $this->normalizeSlug($slug);
        $candidates = [
            ROOTPATH . 'demo-media' . DIRECTORY_SEPARATOR . 'faculties' . DIRECTORY_SEPARATOR . $slug,
            dirname(ROOTPATH) . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'demo-media' . DIRECTORY_SEPARATOR . 'faculties' . DIRECTORY_SEPARATOR . $slug,
        ];

        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return $candidates[0];
    }

    private function sharedHeroDirectory(): string
    {
        $candidates = [
            ROOTPATH . 'demo-media' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'hero',
            dirname(ROOTPATH) . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'demo-media' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'hero',
        ];

        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return $candidates[0];
    }

    /**
     * Absolute pack files for news/event covers (copied into uploads/.../posts/).
     *
     * @return list<string>
     */
    private function coverSources(string $packDir, string $sharedHeroDir): array
    {
        $sources = [];
        foreach (['hero-1.jpg', 'hero-2.jpg', 'hero-3.jpg', 'banner.jpg'] as $file) {
            $path = $packDir . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                $sources[] = $path;
            }
        }

        if ($sources === [] && is_dir($sharedHeroDir)) {
            foreach (['campus-walkway.jpg', 'economics-classroom.jpg', 'research-team.jpg'] as $file) {
                $path = $sharedHeroDir . DIRECTORY_SEPARATOR . $file;
                if (is_file($path)) {
                    $sources[] = $path;
                }
            }
        }

        return $sources;
    }

    private function normalizeSlug(string $slug): string
    {
        return trim(preg_replace('/[^a-z0-9-]/', '-', strtolower($slug)) ?? '', '-');
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return [13, 155, 73];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function paletteColor(string $baseHex, int $index): string
    {
        $palette = [
            $baseHex,
            '#0B6F38',
            '#14532D',
            '#0F766E',
            '#1D4ED8',
            '#7C3AED',
            '#B45309',
            '#BE123C',
        ];

        return $palette[$index % count($palette)];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            $part = trim($part, ".\t\n\r ");
            if ($part === '') {
                continue;
            }
            // Skip titles
            if (in_array(mb_strtolower($part), ['dr', 'dr.', 'pr', 'pr.', 'm', 'm.', 'mme', 'mr', 'mrs'], true)) {
                continue;
            }
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : 'UB';
    }

    private function truncate(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, $max - 1) . '…';
    }
}
