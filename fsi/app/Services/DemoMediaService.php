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
     * Copy pack photos for this slug only. Reuse photographic assets for staff/posts.
     * Logo is the shared static crest (not a generated tile).
     *
     * @return array{heroes: list<string>, banner: ?string, logo: string, staff: list<string>, posts: list<string>}
     */
    public function materializeSiteGallery(string $slug, string $facultyLabel, string $primaryColor = '#0D9B49'): array
    {
        $slug = $this->normalizeSlug($slug);
        $packDir = $this->packDirectory($slug);

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
        if ($banner === null && $heroes !== []) {
            $banner = $heroes[0];
        }

        $photoPool = array_values(array_filter(array_merge($heroes, $banner !== null ? [$banner] : [])));
        if ($photoPool === []) {
            $photoPool = [self::SHARED_LOGO];
        }

        $staff = [];
        for ($i = 0; $i < 12; $i++) {
            $staff[] = $photoPool[$i % count($photoPool)];
        }

        $posts = [];
        for ($i = 0; $i < 12; $i++) {
            $posts[] = $photoPool[($i + 1) % count($photoPool)];
        }

        return [
            'heroes' => $heroes,
            'banner' => $banner,
            'logo'   => self::SHARED_LOGO,
            'staff'  => $staff,
            'posts'  => $posts,
        ];
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

    private function truncate(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, $max - 1) . '…';
    }
}
