<?php
require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$ref = new ReflectionClass($kernel);
if ($ref->hasProperty('routeMiddleware')) {
    $p = $ref->getProperty('routeMiddleware');
    $p->setAccessible(true);
    $val = $p->getValue($kernel);
    echo "routeMiddleware from running kernel:\n";
    print_r($val);
}
if ($ref->hasProperty('middlewareGroups')){
    $p = $ref->getProperty('middlewareGroups');
    $p->setAccessible(true);
    $val = $p->getValue($kernel);
    echo "middlewareGroups:\n";
    print_r($val);
}
echo "Kernel class: " . get_class($kernel) . PHP_EOL;

echo "getMiddlewareAliases():\n";
print_r($kernel->getMiddlewareAliases());
