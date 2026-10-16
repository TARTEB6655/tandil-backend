<?php

/**
 * HTTP smoke: same URL as Postman (base_url + /api/admin/services/{id}/convert-to-category).
 * Usage: php scripts/smoke_convert_http.php [base_url]
 */

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$baseUrl = rtrim($argv[1] ?? getenv('SMOKE_BASE_URL') ?: 'http://127.0.0.1:8765', '/');

$admin = User::factory()->create(['role' => 'admin']);
$token = $admin->createToken('smoke-http')->plainTextToken;

$service = Service::factory()->create([
    'name' => 'HTTP Smoke Service',
    'slug' => 'http-smoke-'.uniqid(),
]);
$cat = Category::factory()->create();
$product = Product::factory()->create([
    'name' => 'HTTP Smoke Product',
    'category_id' => $cat->id,
    'type' => 'service',
    'requires_shipping' => false,
    'status' => 'active',
]);
$product->services()->attach($service->id);

$postmanPath = '/api/admin/services/'.$service->id.'/convert-to-category';
$url = $baseUrl.$postmanPath;

$healthUrl = $baseUrl.'/api/health';

$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Accept: application/json\r\n",
        'ignore_errors' => true,
    ],
]);
$healthRaw = @file_get_contents($healthUrl, false, $ctx);
$healthStatus = $http_response_header[0] ?? 'unknown';
$health = json_decode((string) $healthRaw, true);

$ctxPost = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", [
            'Authorization: Bearer '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
        ])."\r\n",
        'content' => '{}',
        'ignore_errors' => true,
    ],
]);
$convertRaw = @file_get_contents($url, false, $ctxPost);
$convertStatus = $http_response_header[0] ?? 'unknown';
$convert = json_decode((string) $convertRaw, true);

$out = [
    'postman_collection' => [
        'request_name' => '06. Services - Convert to Category',
        'method' => 'POST',
        'raw_url' => '{{base_url}}/api/admin/services/{{service_id}}/convert-to-category',
        'body' => '{}',
        'headers' => ['Authorization' => 'Bearer {{token}}', 'Accept' => 'application/json'],
    ],
    'smoke' => [
        'base_url' => $baseUrl,
        'service_id' => $service->id,
        'health' => ['status_line' => $healthStatus, 'body' => $health],
        'convert' => [
            'url_hit' => $url,
            'status_line' => $convertStatus,
            'body' => $convert,
        ],
    ],
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
