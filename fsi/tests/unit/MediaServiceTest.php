<?php

use App\Services\MediaService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class MediaServiceTest extends CIUnitTestCase
{
    public function testPostCoverValidationAcceptsSmallPng(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'site_png_');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));

        $file = new UploadedFile($path, 'cover.png', 'image/png', filesize($path), UPLOAD_ERR_OK);
        $error = null;

        $this->assertTrue((new MediaService())->validatePostCover($file, $error));
        $this->assertNull($error);

        @unlink($path);
    }

    public function testPostCoverValidationRejectsExecutableExtension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'site_php_');
        file_put_contents($path, '<?php echo "dangereux";');

        $file = new UploadedFile($path, 'shell.php', 'application/x-php', filesize($path), UPLOAD_ERR_OK);
        $error = null;

        $this->assertFalse((new MediaService())->validatePostCover($file, $error));
        $this->assertSame('Le fichier doit être une image JPG, PNG ou WebP.', $error);

        @unlink($path);
    }

    public function testPostCoverValidationRejectsSpoofedImageContent(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'site_spoof_');
        file_put_contents($path, '<?php echo "faux jpg";');

        $file = new UploadedFile($path, 'cover.jpg', 'image/jpeg', filesize($path), UPLOAD_ERR_OK);
        $error = null;

        $this->assertFalse((new MediaService())->validatePostCover($file, $error));
        $this->assertNotNull($error);

        @unlink($path);
    }

    public function testGenericImageStorageRejectsInvalidFolderName(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'site_png_');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));

        $file = new UploadedFile($path, 'photo.png', 'image/png', filesize($path), UPLOAD_ERR_OK);
        $error = null;

        $this->assertNull((new MediaService())->storePublicImage($file, '../danger', $error));
        $this->assertSame('Le dossier de destination est invalide.', $error);

        @unlink($path);
    }

    public function testDeletePublicPathRefusesTraversalOutsideUploads(): void
    {
        $outside = FCPATH . 'outside-delete-guard.txt';
        file_put_contents($outside, 'ne pas supprimer');

        (new MediaService())->deletePublicPath('uploads/../outside-delete-guard.txt');

        $this->assertFileExists($outside);

        @unlink($outside);
    }

    public function testUploadsDirectoryBlocksScriptExecution(): void
    {
        $rules = file_get_contents(FCPATH . 'uploads/.htaccess') ?: '';

        $this->assertStringContainsString('Options -Indexes', $rules);
        $this->assertStringContainsString('Require all denied', $rules);
        $this->assertStringContainsString('php_flag engine off', $rules);

        $nginx = file_get_contents(ROOTPATH . 'deploy/nginx/uploads-security.conf') ?: '';

        $this->assertStringContainsString('/uploads/', $nginx);
        $this->assertStringContainsString('return 404', $nginx);
        $this->assertStringContainsString('try_files $uri =404', $nginx);
    }

    public function testStoreCopiesIntoConfiguredFacultyPublicRootAndDeleteRemovesBoth(): void
    {
        $central = sys_get_temp_dir() . '/media-central-' . uniqid('', true);
        $faculty = sys_get_temp_dir() . '/media-faculty-' . uniqid('', true);
        mkdir($central, 0755, true);
        mkdir($faculty, 0755, true);
        file_put_contents($faculty . '/index.php', '<?php');

        $previous = getenv('app.uploadMirrors');
        $_ENV['app.uploadMirrors'] = 'fseg:' . $faculty;
        $_SERVER['app.uploadMirrors'] = 'fseg:' . $faculty;
        putenv('app.uploadMirrors=fseg:' . $faculty);

        $png = tempnam(sys_get_temp_dir(), 'site_png_');
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $file = new UploadedFile($png, 'logo.png', 'image/png', filesize($png), UPLOAD_ERR_OK);
        $error = null;

        try {
            $service = new MediaService($central, 'fseg');
            $relative = $service->storePublicImage($file, 'settings', $error);

            $this->assertNull($error);
            $this->assertIsString($relative);
            $this->assertFileExists($central . '/' . $relative);
            $this->assertFileExists($faculty . '/' . $relative);

            $service->deletePublicPath($relative);
            $this->assertFileDoesNotExist($central . '/' . $relative);
            $this->assertFileDoesNotExist($faculty . '/' . $relative);
        } finally {
            @unlink($png);
            $this->removeDirectory($central);
            $this->removeDirectory($faculty);
            unset($_ENV['app.uploadMirrors'], $_SERVER['app.uploadMirrors']);
            if ($previous === false) {
                putenv('app.uploadMirrors');
            } else {
                putenv('app.uploadMirrors=' . $previous);
            }
        }
    }

    public function testTemplateSlugIsNotMirrored(): void
    {
        $this->assertNull((new MediaService())->resolveMirrorPublicRoot('template'));
        $this->assertNull((new MediaService())->resolveMirrorPublicRoot('demo'));
    }

    public function testSiblingPublicRootIsUsedWhenUploadMirrorsAreEmpty(): void
    {
        $previous = getenv('app.uploadMirrors');
        unset($_ENV['app.uploadMirrors'], $_SERVER['app.uploadMirrors']);
        putenv('app.uploadMirrors');

        try {
            $root = (new MediaService())->resolveMirrorPublicRoot('fseg');
            $this->assertNotNull($root);
            $this->assertFileExists($root . '/index.php');
        } finally {
            if ($previous === false) {
                putenv('app.uploadMirrors');
            } else {
                putenv('app.uploadMirrors=' . $previous);
                $_ENV['app.uploadMirrors'] = $previous;
                $_SERVER['app.uploadMirrors'] = $previous;
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
