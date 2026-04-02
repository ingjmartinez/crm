<?php
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

function distinctProductos($tabla, $ini = null, $fin = null) {
    return DB::table($tabla)
        ->selectRaw("TRIM(CAST(producto_id AS CHAR)) AS producto_id")
        ->whereNotNull('producto_id')
        ->whereRaw("TRIM(CAST(producto_id AS CHAR)) <> ''")
        ->when($ini && $fin, function ($q) use ($ini, $fin) {
            $q->whereBetween('fecha', [$ini, $fin]);
        })
        ->distinct()
        ->pluck('producto_id')
        ->map(fn($v) => trim((string)$v))
        ->filter()
        ->values()
        ->all();
}

$catalogo = DB::table('catalogo_juegos')->pluck('producto_id')->map(fn($v)=>trim((string)$v))->filter()->unique()->values()->all();
$catalogoLookup = array_flip($catalogo);

$hoy = now();
$anio = (int)$hoy->year;
$mes = (int)$hoy->month;
$ini = Carbon::create($anio,$mes,1)->startOfMonth()->toDateString();
$fin = Carbon::create($anio,$mes,1)->endOfMonth()->toDateString();

$netMes = distinctProductos('vt_usuarios_net', $ini, $fin);
$betMes = distinctProductos('vt_usuarios_bet', $ini, $fin);
$allMes = array_values(array_unique(array_merge($netMes, $betMes)));
$nuevosMes = array_values(array_filter($allMes, fn($id)=>!isset($catalogoLookup[$id])));

echo "Periodo actual {$anio}-{$mes}\n";
echo "net mes: ".count($netMes)." | bet mes: ".count($betMes)." | union mes: ".count($allMes)." | nuevos mes: ".count($nuevosMes)."\n";

$maxNet = DB::table('vt_usuarios_net')->max('fecha');
$maxBet = DB::table('vt_usuarios_bet')->max('fecha');
echo "max fecha net: {$maxNet} | max fecha bet: {$maxBet}\n";

$netAll = distinctProductos('vt_usuarios_net');
$betAll = distinctProductos('vt_usuarios_bet');
$all = array_values(array_unique(array_merge($netAll, $betAll)));
$nuevosAll = array_values(array_filter($all, fn($id)=>!isset($catalogoLookup[$id])));

echo "net all: ".count($netAll)." | bet all: ".count($betAll)." | union all: ".count($all)." | nuevos all: ".count($nuevosAll)."\n";

echo "muestra nuevos all: ".implode(', ', array_slice($nuevosAll,0,20))."\n";
