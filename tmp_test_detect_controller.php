<?php
$base = __DIR__;
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = app(\App\Http\Controllers\CatalogoJuegoController::class);
$ref = new ReflectionClass($controller);
$method = $ref->getMethod('obtenerProductosNuevos');
$method->setAccessible(true);
$data = $method->invoke($controller, 2026, 3, false);
echo 'count marzo: '.count($data).PHP_EOL;
if (count($data) > 0) {
  $first = $data->first();
  echo 'sample: '.json_encode($first, JSON_UNESCAPED_UNICODE).PHP_EOL;
}
