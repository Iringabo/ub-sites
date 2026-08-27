<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use Throwable;

class MediaService
{
    private const MAX_SIZE = 2_097_152;

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
        $activeSlug = (string) (service('siteResolver')->activeSite()->slug ?? '');
        $siteSlug   = trim(preg_replace('/[^a-z0-9-]/', '-', strtolower($activeSlug)) ?? '', '-');

        if ($siteSlug === '') {
            $error = 'Aucun site actif ne permet de déterminer le dossier de téléversement.';

            return null;
        }

        $relativeDirectory = 'uploads/sites/' . $siteSlug . '/' . $folder;
        $directory = FCPATH . $relativeDirectory;

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $error = 'Le dossier de téléversement est indisponible.';

            return null;
        }

        try {
            if (ENVIRONMENT === 'testing' && ! is_uploaded_file($file->getTempName())) {
                if (! @copy($file->getTempName(), $directory . '/' . $fileName)) {
                    $error = 'L’image n’a pas pu être enregistrée.';

                    return null;
                }
            } else {
                $file->move($directory, $fileName);
            }
        } catch (Throwable) {
            $error = 'L’image n’a pas pu être enregistrée.';

            return null;
        }

        return $relativeDirectory . '/' . $fileName;
    }

    public function deletePublicPath(?string $path): void
    {
        $path = trim((string) $path);

        if ($path === '' || ! str_starts_with($path, 'uploads/') || str_contains($path, "\0")) {
            return;
        }

        $uploadsRoot = realpath(FCPATH . 'uploads');
        if ($uploadsRoot === false) {
            return;
        }

        $fullPath = realpath(FCPATH . $path);
        if ($fullPath === false || ! str_starts_with($fullPath, $uploadsRoot . DIRECTORY_SEPARATOR)) {
            return;
        }

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
