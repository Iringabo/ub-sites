<?php

use App\Services\DemoMediaService;
use App\Services\MediaService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DemoMediaServiceTest extends CIUnitTestCase
{
    private string $tempPublic = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempPublic = sys_get_temp_dir() . '/demo-media-' . bin2hex(random_bytes(4));
        mkdir($this->tempPublic . '/uploads', 0755, true);
    }

    protected function tearDown(): void
    {
        if ($this->tempPublic !== '' && is_dir($this->tempPublic)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->tempPublic, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
            @rmdir($this->tempPublic);
        }
        parent::tearDown();
    }

    public function testMaterializeWritesOnlyTargetSlugUnderUploads(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension required');
        }

        $media = new MediaService($this->tempPublic . '/', 'fseg');
        $service = new DemoMediaService($media);
        $gallery = $service->materializeSiteGallery('fseg', 'Faculté de test', '#0D9B49');

        $this->assertNotEmpty($gallery['heroes']);
        $this->assertStringStartsWith('uploads/sites/fseg/', $gallery['heroes'][0]);
        $this->assertSame(DemoMediaService::SHARED_LOGO, $gallery['logo']);
        $this->assertLessThanOrEqual(3, count($gallery['heroes']));
        $this->assertNotEmpty($gallery['staff']);
        $this->assertStringStartsWith('uploads/sites/fseg/', $gallery['staff'][0]);
        $this->assertDirectoryExists($this->tempPublic . '/uploads/sites/fseg');
        $this->assertDirectoryDoesNotExist($this->tempPublic . '/uploads/sites/fsi');
        $this->assertDirectoryDoesNotExist($this->tempPublic . '/uploads/sites/med');
    }
}
