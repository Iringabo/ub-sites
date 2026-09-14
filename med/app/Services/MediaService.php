<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use Throwable;

class MediaService
{
    private const MAX_SIZE = 2_097_152;

    public function __construct(
        private readonly ?string $primaryPublicRoot = null,
        private readonly ?string $forcedSiteSlug = null,
    ) {
    }

    /**
     * @var list<string>
     */
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * @var list<string>
     */
    private array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

    public function hasFile(?UploadedFile $file): bool
    {
        return $file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    public function validatePostCover(UploadedFile $file, ?string &$error = null): bool
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $error = 'Le fichier téléversé est invalide.';

            return false;
        }

        $extension = strtolower($file->getClientExtension());
        if (! in_array($extension, $this->allowedExtensions, true)) {
            $error = 'Le fichier doit être une image JPG, PNG ou WebP.';

            return false;
        }

        if (($file->getSize() ?: 0) > self::MAX_SIZE) {
            $error = 'L’image de couverture ne doit pas dépasser 2 Mo.';

            return false;
        }

        $mimeType = $file->getMimeType();
        if (! in_array($mimeType, $this->allowedMimeTypes, true)) {
            $error = 'Le type MIME de l’image n’est pas autorisé.';

            return false;
        }

        if (@getimagesize($file->getTempName()) === false) {
            $error = 'Le fichier téléversé n’est pas une image valide.';

            return false;
        }

        return true;
    }

    public function storePostCover(UploadedFile $file, ?string &$error = null): ?string
    {
        return $this->storePublicImage($file, 'posts', $error);
    }

    public function storePublicImage(UploadedFile $file, string $folder, ?string &$error = null): ?string
    {
        if (! $this->validatePostCover($file, $error)) {
            return null;
        }

        $folder = trim($folder, '/');
        if (preg_match('/^[a-z0-9_-]+$/', $folder) !== 1) {
            $error = 'Le dossier de destination est invalide.';

            return null;
        }

        $extension = strtolower($file->getClientExtension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $fileName  = bin2hex(random_bytes(16)) . '.' . $extension;
        $siteSlug  = $this->activeSiteSlug();

        if ($siteSlug === '') {
            $error = 'Aucun site actif ne permet de déterminer le dossier de téléversement.';

            return null;
        }

        $relativeDirectory = 'uploads/sites/' . $siteSlug . '/' . $folder;
        $relativePath = $relativeDirectory . '/' . $fileName;
        $primaryDirectory = $this->primaryPublicRoot() . $relativeDirectory;

        if (! is_dir($primaryDirectory) && ! mkdir($primaryDirectory, 0755, true) && ! is_dir($primaryDirectory)) {
            $error = 'Le dossier de téléversement est indisponible.';

            return null;
        }

        try {
            if (ENVIRONMENT === 'testing' && ! is_uploaded_file($file->getTempName())) {
                if (! @copy($file->getTempName(), $primaryDirectory . '/' . $fileName)) {
                    $error = 'L’image n’a pas pu être enregistrée.';

                    return null;
                }
            } else {
                $file->move($primaryDirectory, $fileName);
            }
        } catch (Throwable) {
            $error = 'L’image n’a pas pu être enregistrée.';

            return null;
        }

        $this->mirrorRelativeFile($relativePath, $siteSlug);

        return $relativePath;
    }

    public function deletePublicPath(?string $path): void
    {
        $path = trim((string) $path);

        if ($path === '' || ! str_starts_with($path, 'uploads/') || str_contains($path, "\0")) {
            return;
        }

        foreach ($this->publicRootsForSlug($this->slugFromUploadPath($path) ?: $this->activeSiteSlug()) as $root) {
            $this->deleteFromRoot($root, $path);
        }
    }

    /**
     * @return list<string>
     */
    public function publicRootsForSlug(string $slug): array
    {
        $roots = [rtrim($this->primaryPublicRoot(), '/\\')];
        $mirror = $this->resolveMirrorPublicRoot($slug);
        if ($mirror === null) {
            return $roots;
        }

        $mirror = rtrim($mirror, '/\\');
        $primary = realpath(rtrim($this->primaryPublicRoot(), '/\\')) ?: rtrim($this->primaryPublicRoot(), '/\\');
        $mirrorReal = realpath($mirror) ?: $mirror;
        if ($mirrorReal === $primary) {
            return $roots;
        }

        $roots[] = $mirror;

        return $roots;
    }

    public function resolveMirrorPublicRoot(string $slug): ?string
    {
        $slug = trim(preg_replace('/[^a-z0-9-]/', '-', strtolower($slug)) ?? '', '-');
        if ($slug === '' || in_array($slug, ['template', 'demo'], true)) {
            return null;
        }

        $mapped = $this->uploadMirrors()[$slug] ?? null;
        if (is_string($mapped) && $mapped !== '') {
            return $this->publicRootFromPath($mapped);
        }

        $sibling = dirname(ROOTPATH) . DIRECTORY_SEPARATOR . $slug;
        if (is_file($sibling . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php')) {
            return $sibling . DIRECTORY_SEPARATOR . 'public';
        }

        return null;
    }

    private function mirrorRelativeFile(string $relativePath, string $slug): void
    {
        $source = $this->primaryPublicRoot() . $relativePath;
        if (! is_file($source)) {
            return;
        }

        foreach ($this->publicRootsForSlug($slug) as $root) {
            $primary = realpath(rtrim($this->primaryPublicRoot(), '/\\')) ?: rtrim($this->primaryPublicRoot(), '/\\');
            $rootReal = realpath($root) ?: $root;
            if ($rootReal === $primary) {
                continue;
            }

            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $directory = dirname($target);
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                log_message('warning', 'Impossible de créer le dossier miroir des médias : {0}', [$directory]);
                continue;
            }

            if (! @copy($source, $target)) {
                log_message('warning', 'Impossible de copier le média vers {0}', [$target]);
            }
        }
    }

    private function deleteFromRoot(string $root, string $relativePath): void
    {
        $uploadsRoot = realpath($root . DIRECTORY_SEPARATOR . 'uploads');
        if ($uploadsRoot === false) {
            return;
        }

        $fullPath = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($fullPath === false || ! str_starts_with($fullPath, $uploadsRoot . DIRECTORY_SEPARATOR)) {
            return;
        }

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * @return array<string, string>
     */
    private function uploadMirrors(): array
    {
        $raw = trim((string) env('app.uploadMirrors', ''));
        if ($raw === '') {
            return [];
        }

        $map = [];
        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || ! str_contains($pair, ':')) {
                continue;
            }

            [$slug, $path] = explode(':', $pair, 2);
            $slug = trim(strtolower($slug));
            $path = trim($path);
            if ($slug !== '' && $path !== '') {
                $map[$slug] = $path;
            }
        }

        return $map;
    }

    private function publicRootFromPath(string $path): ?string
    {
        $path = rtrim($path, '/\\');
        if (is_file($path . DIRECTORY_SEPARATOR . 'index.php')) {
            return $path;
        }

        if (is_file($path . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php')) {
            return $path . DIRECTORY_SEPARATOR . 'public';
        }

        return is_dir($path) ? $path : null;
    }

    private function primaryPublicRoot(): string
    {
        return rtrim($this->primaryPublicRoot ?? FCPATH, '/\\') . '/';
    }

    private function activeSiteSlug(): string
    {
        if ($this->forcedSiteSlug !== null && $this->forcedSiteSlug !== '') {
            return trim(preg_replace('/[^a-z0-9-]/', '-', strtolower($this->forcedSiteSlug)) ?? '', '-');
        }

        try {
            $activeSlug = (string) (service('siteResolver')->activeSite()->slug ?? '');
        } catch (Throwable) {
            $activeSlug = (string) env('app.siteSlug', '');
        }

        return trim(preg_replace('/[^a-z0-9-]/', '-', strtolower($activeSlug)) ?? '', '-');
    }

    private function slugFromUploadPath(string $path): string
    {
        if (preg_match('#^uploads/sites/([a-z0-9-]+)/#', $path, $matches) !== 1) {
            return '';
        }

        return (string) $matches[1];
    }
}
