<?php

namespace Tests\Unit;

use App\Services\ImageCompressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageCompressionServiceProductTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $paths = [
            storage_path('app/public/products/test-large.jpg'),
            storage_path('app/public/product-options/test-opt.jpg'),
            storage_path('app/public/vendors/logos/test-vendor-profile.jpg'),
        ];
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_optimize_product_gallery_resizes_and_compresses_large_jpeg(): void
    {

        $relative = 'products/test-large.jpg';
        $full = storage_path('app/public/'.$relative);
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }

        $img = imagecreatetruecolor(2400, 1800);
        for ($i = 0; $i < 400; $i++) {
            $color = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle($img, random_int(0, 2399), random_int(0, 1799), random_int(0, 2399), random_int(0, 1799), $color);
        }
        imagejpeg($img, $full, 100);
        imagedestroy($img);

        ImageCompressionService::optimizeProductGalleryFromPublicPath($relative);

        $after = filesize($full);
        $this->assertLessThanOrEqual(ImageCompressionService::PRODUCT_GALLERY_MAX_BYTES, $after);

        $info = getimagesize($full);
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(ImageCompressionService::PRODUCT_GALLERY_MAX_DIMENSION, max($info[0], $info[1]));
    }

    public function test_optimize_product_option_resizes_large_thumbnail(): void
    {
        $relative = 'product-options/test-opt.jpg';
        $full = storage_path('app/public/'.$relative);
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }

        $img = imagecreatetruecolor(1600, 1200);
        imagejpeg($img, $full, 92);
        imagedestroy($img);

        ImageCompressionService::optimizeProductOptionFromPublicPath($relative);

        $info = getimagesize($full);
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(ImageCompressionService::PRODUCT_OPTION_MAX_DIMENSION, max($info[0], $info[1]));
        $this->assertLessThanOrEqual(ImageCompressionService::PRODUCT_OPTION_MAX_BYTES, filesize($full));
    }

    public function test_optimize_vendor_profile_picture_resizes_and_compresses_large_jpeg(): void
    {
        $relative = 'vendors/logos/test-vendor-profile.jpg';
        $full = storage_path('app/public/'.$relative);
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }

        $img = imagecreatetruecolor(2400, 1800);
        for ($i = 0; $i < 400; $i++) {
            $color = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle($img, random_int(0, 2399), random_int(0, 1799), random_int(0, 2399), random_int(0, 1799), $color);
        }
        imagejpeg($img, $full, 100);
        imagedestroy($img);

        ImageCompressionService::optimizeVendorProfilePictureFromPublicPath($relative);

        $after = filesize($full);
        $this->assertLessThanOrEqual(ImageCompressionService::VENDOR_PROFILE_PICTURE_MAX_BYTES, $after);

        $info = getimagesize($full);
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(ImageCompressionService::VENDOR_PROFILE_PICTURE_MAX_DIMENSION, max($info[0], $info[1]));
    }
}
