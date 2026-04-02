<?php
$base = __DIR__;
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo 'max net: ' . (DB::table('vt_usuarios_net')->max('fecha') ?? 'NULL') . PHP_EOL;
echo 'max bet: ' . (DB::table('vt_usuarios_bet')->max('fecha') ?? 'NULL') . PHP_EOL;

echo 'distinct net current 2026-04: ' . DB::table('vt_usuarios_net')->whereBetween('fecha', ['2026-04-01','2026-04-30'])->distinct()->count('producto_id') . PHP_EOL;
echo 'distinct bet current 2026-04: ' . DB::table('vt_usuarios_bet')->whereBetween('fecha', ['2026-04-01','2026-04-30'])->distinct()->count('producto_id') . PHP_EOL;
