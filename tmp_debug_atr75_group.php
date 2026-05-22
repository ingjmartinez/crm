<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::select("SELECT company_id, COUNT(*) total, SUM(JSON_EXTRACT(atributos, '$.Atr75') IS NOT NULL) con_atr75 FROM centros_de_costo GROUP BY company_id ORDER BY company_id");
foreach ($rows as $r) {
    echo "company={$r->company_id}, total={$r->total}, con_atr75={$r->con_atr75}" . PHP_EOL;
}
