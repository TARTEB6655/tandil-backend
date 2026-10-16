<?php

/**
 * Hit convert API in-process (same stack as Postman) and print JSON.
 * Usage: php scripts/smoke_convert_api_response.php
 */

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$admin = User::factory()->create(['role' => 'admin']);
$token = $admin->createToken('smoke-convert')->plainTextToken;

$service = Service::factory()->create([
    'name' => 'Smoke Test Service Catalog',
    'slug' => 'smoke-service-'.uniqid(),
]);
$cat = Category::factory()->create();
$product = Product::factory()->create([
    'name' => 'Smoke Service Product',
    'category_id' => $cat->id,
    'type' => 'service',
    'requires_shipping' => false,
    'status' => 'active',
]);
$product->services()->attach($service->id);

$path = '/api/admin/services/'.$service->id.'/convert-to-category';
$request = Request::create($path, 'POST', [], [], [], [
    'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
], '{}');

$response = $app->handle($request);
$body = json_decode($response->getContent(), true);

$out = [
    'request' => [
        'method' => 'POST',
        'url' => $path,
        'service_id' => $service->id,
        'body' => (object) [],
    ],
    'http_status' => $response->getStatusCode(),
    'response' => $body,
];

$json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents(storage_path('app/smoke_convert_service_to_category.json'), $json);
echo $json.PHP_EOL;
