<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tot = DB::selectOne("SELECT COUNT(*) AS total, SUM(JSON_EXTRACT(atributos, '$.Atr75') IS NOT NULL) AS con_atr75 FROM centros_de_costo");
echo "total={$tot->total}, con_atr75={$tot->con_atr75}" . PHP_EOL;

$rows = DB::select("SELECT id_centro_costo, company_id, JSON_UNQUOTE(JSON_EXTRACT(atributos, '$.Atr75')) AS Atr75, JSON_KEYS(atributos) AS keys_json FROM centros_de_costo WHERE atributos IS NOT NULL LIMIT 10");
foreach ($rows as $r) {
    echo "id={$r->id_centro_costo}, company={$r->company_id}, atr75=" . ($r->Atr75 ?? 'NULL') . PHP_EOL;
    echo "keys={$r->keys_json}" . PHP_EOL;
}
