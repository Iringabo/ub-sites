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
}
