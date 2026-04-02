<?php
$base = __DIR__;
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$ini='2026-03-01'; $fin='2026-03-31';
$net = DB::table('vt_usuarios_net')->select('producto_id')->whereBetween('fecha',[$ini,$fin])->whereNotNull('producto_id')->distinct()->pluck('producto_id')->map(fn($v)=>trim((string)$v))->filter()->unique()->values();
$bet = DB::table('vt_usuarios_bet')->select('producto_id')->whereBetween('fecha',[$ini,$fin])->whereNotNull('producto_id')->distinct()->pluck('producto_id')->map(fn($v)=>trim((string)$v))->filter()->unique()->values();
$cat = DB::table('catalogo_juegos')->select('producto_id')->whereNotNull('producto_id')->distinct()->pluck('producto_id')->map(fn($v)=>trim((string)$v))->filter()->unique()->values();
$base = $net->merge($bet)->unique()->values();
$lookup = array_flip($cat->all());
$faltan = $base->filter(fn($id)=>!isset($lookup[$id]))->values();

echo 'net='.count($net).' bet='.count($bet).' base='.count($base).' cat='.count($cat).' faltan='.count($faltan).PHP_EOL;
echo implode(', ', $faltan->take(30)->all()).PHP_EOL;
