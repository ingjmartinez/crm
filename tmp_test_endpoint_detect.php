<?php
$base = __DIR__;
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

$controller = app(\App\Http\Controllers\CatalogoJuegoController::class);

$r1 = Request::create('/mantenimiento/catalogo-juegos/detectar-nuevos', 'GET', [
    'todo' => '0',
    'anio' => '2026',
    'mes' => '3',
]);
$res1 = $controller->detectarNuevos($r1);
$d1 = $res1->getData(true);

echo "MARZO -> data=" . count($d1['data'] ?? []) . " | periodo=" . ($d1['periodo_texto'] ?? 'null') . "\n";

echo "MARZO sample IDs: ";
$ids1 = array_map(fn($x)=>$x['producto_id'] ?? '', array_slice($d1['data'] ?? [], 0, 10));
echo implode(', ', $ids1) . "\n";

$r2 = Request::create('/mantenimiento/catalogo-juegos/detectar-nuevos', 'GET', [
    'todo' => '1',
]);
$res2 = $controller->detectarNuevos($r2);
$d2 = $res2->getData(true);

echo "TODO -> data=" . count($d2['data'] ?? []) . " | periodo=" . ($d2['periodo_texto'] ?? 'null') . "\n";

echo "TODO sample IDs: ";
$ids2 = array_map(fn($x)=>$x['producto_id'] ?? '', array_slice($d2['data'] ?? [], 0, 10));
echo implode(', ', $ids2) . "\n";
