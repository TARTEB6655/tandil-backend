<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaThumbServingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_query_w_returns_resized_smaller_image_with_cache_headers(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD required');
        }

        Storage::fake('public');

        // Real filesystem path needed for MediaThumbCache GD + response()->file()
        Storage::disk('public')->makeDirectory('products');
        $rel = 'products/perf-source.jpg';
        $full = Storage::disk('public')->path($rel);

        $img = imagecreatetruecolor(1200, 800);
        $bg = imagecolorallocate($img, 40, 120, 200);
        imagefilledrectangle($img, 0, 0, 1199, 799, $bg);
        imagejpeg($img, $full, 95);
        imagedestroy($img);

        $originalSize = filesize($full);
        $this->assertGreaterThan(10_000, $originalSize);

        $response = $this->get('/media/'.$rel.'?w=192');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('Cache-Control');
        $this->assertStringContainsString('max-age=31536000', (string) $response->headers->get('Cache-Control'));
        $this->assertTrue($response->headers->has('Accept-Ranges'));

        $servedPath = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($servedPath);
        $body = file_get_contents($servedPath);
        $this->assertNotEmpty($body);
        $this->assertLessThan($originalSize, strlen($body), 'Thumb body must be smaller than full-size source');

        $info = getimagesizefromstring($body);
        $this->assertNotFalse($info);
        $this->assertSame(192, $info[0]);
    }

    public function test_media_path_based_thumb_url_also_resizes(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD required');
        }

        Storage::disk('public')->makeDirectory('banners');
        $rel = 'banners/path-thumb.jpg';
        $full = Storage::disk('public')->path($rel);
        $img = imagecreatetruecolor(900, 500);
        imagejpeg($img, $full, 90);
        imagedestroy($img);

        $response = $this->get('/media/cache/thumbs/w256/'.$rel);
        $response->assertOk();
        $servedPath = $response->baseResponse->getFile()->getPathname();
        $info = getimagesize($servedPath);
        $this->assertNotFalse($info);
        $this->assertSame(256, $info[0]);
    }

    public function test_shop_list_and_banners_expose_thumb_query_urls(): void
    {
        $category = \App\Models\Category::factory()->create(['is_active' => true]);
        $product = \App\Models\Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'is_featured' => true,
        ]);
        \App\Models\ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'list-thumb.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        \App\Models\Banner::query()->create([
            'title' => 'Home',
            'image' => 'banners/home.jpg',
            'is_active' => true,
            'priority' => 1,
        ]);

        $list = $this->getJson('/api/shop/products')->assertOk();
        $item = collect($list->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($item);
        $this->assertStringContainsString('w=384', (string) ($item['image_url'] ?? ''));
        $this->assertSame([], $item['gallery_images'] ?? ['x']);

        $featured = $this->getJson('/api/shop/products/featured')->assertOk();
        $fItem = collect($featured->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($fItem);
        $this->assertStringContainsString('w=384', (string) ($fItem['image_url'] ?? ''));

        $banners = $this->getJson('/api/banners')->assertOk();
        $bannerUrl = (string) data_get($banners->json(), 'data.0.image_url', data_get($banners->json(), 'data.0.image', ''));
        if ($bannerUrl !== '') {
            $this->assertTrue(
                str_contains($bannerUrl, 'w=') || str_contains($bannerUrl, '/media/'),
                'Banner image should be media URL'
            );
        }
    }
}
