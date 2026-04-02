<?php
$base = __DIR__;
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$anio = (int) now()->year;
$mes = (int) now()->month;
$ini = Carbon::create($anio, $mes, 1)->startOfMonth()->toDateString();
$fin = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();

$net = DB::table('vt_usuarios_net')
  ->selectRaw("TRIM(CAST(producto_id AS CHAR)) AS producto_id")
  ->whereNotNull('producto_id')
  ->whereRaw("TRIM(CAST(producto_id AS CHAR)) <> ''")
  ->distinct()
  ->pluck('producto_id')
  ->map(fn($x)=>trim((string)$x))->filter()->values()->all();

$bet = DB::table('vt_usuarios_bet')
  ->selectRaw("TRIM(CAST(producto_id AS CHAR)) AS producto_id")
  ->whereNotNull('producto_id')
  ->whereRaw("TRIM(CAST(producto_id AS CHAR)) <> ''")
  ->distinct()
  ->pluck('producto_id')
  ->map(fn($x)=>trim((string)$x))->filter()->values()->all();

$union = array_values(array_unique(array_merge($net, $bet)));
$catalogo = DB::table('catalogo_juegos')->pluck('producto_id')->map(fn($x)=>trim((string)$x))->filter()->values()->all();
$catalogoLookup = array_flip($catalogo);
$nuevosAll = array_values(array_filter($union, fn($id)=>!isset($catalogoLookup[$id])));

$netMes = DB::table('vt_usuarios_net')
  ->selectRaw("TRIM(CAST(producto_id AS CHAR)) AS producto_id")
  ->whereNotNull('producto_id')
  ->whereRaw("TRIM(CAST(producto_id AS CHAR)) <> ''")
  ->whereBetween('fecha', [$ini, $fin])
  ->distinct()
  ->pluck('producto_id')
  ->map(fn($x)=>trim((string)$x))->filter()->values()->all();

$betMes = DB::table('vt_usuarios_bet')
  ->selectRaw("TRIM(CAST(producto_id AS CHAR)) AS producto_id")
  ->whereNotNull('producto_id')
  ->whereRaw("TRIM(CAST(producto_id AS CHAR)) <> ''")
  ->whereBetween('fecha', [$ini, $fin])
  ->distinct()
  ->pluck('producto_id')
  ->map(fn($x)=>trim((string)$x))->filter()->values()->all();

$unionMes = array_values(array_unique(array_merge($netMes, $betMes)));
$nuevosMes = array_values(array_filter($unionMes, fn($id)=>!isset($catalogoLookup[$id])));

echo "Periodo actual: {$anio}-{$mes} ({$ini}..{$fin})\n";
echo "All-time -> net:".count($net)." bet:".count($bet)." union:".count($union)." catalogo:".count($catalogo)." nuevos:".count($nuevosAll)."\n";
echo "Mes actual -> net:".count($netMes)." bet:".count($betMes)." union:".count($unionMes)." nuevos:".count($nuevosMes)."\n";
echo "Muestra nuevos all-time: ".implode(', ', array_slice($nuevosAll,0,30))."\n";
echo "Max fecha net: ".(DB::table('vt_usuarios_net')->max('fecha') ?? 'NULL')."\n";
echo "Max fecha bet: ".(DB::table('vt_usuarios_bet')->max('fecha') ?? 'NULL')."\n";
