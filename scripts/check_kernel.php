<?php
require __DIR__ . '/../vendor/autoload.php';

echo 'class_exists App\\Http\\Kernel: ' . (class_exists('App\\Http\\Kernel') ? 'true' : 'false') . PHP_EOL;
if (class_exists('App\\Http\\Kernel')) {
    $r = new ReflectionClass('App\\Http\\Kernel');
    echo 'is instantiable: ' . ($r->isInstantiable() ? 'yes' : 'no') . PHP_EOL;
    if ($r->hasProperty('routeMiddleware')) {
        $p = $r->getProperty('routeMiddleware');
        $p->setAccessible(true);
        $instance = $r->newInstanceWithoutConstructor();
        $val = $p->getValue($instance);
        echo "routeMiddleware:\n";
        print_r($val);
    }
}
