<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = Illuminate\Support\Facades\DB::select("SELECT TRIM(COALESCE(company_id,'')) AS company_id, COUNT(*) AS total FROM centros_de_costo GROUP BY TRIM(COALESCE(company_id,'')) ORDER BY company_id");
foreach ($rows as $r) {
    echo ($r->company_id === '' ? '(vacio)' : $r->company_id) . ' => ' . $r->total . PHP_EOL;
}

$tot = Illuminate\Support\Facades\DB::selectOne("SELECT COUNT(*) AS total, COUNT(DISTINCT id_centro_costo) AS distintos_id_centro, COUNT(DISTINCT CONCAT(TRIM(COALESCE(company_id,'')),'|',id_centro_costo)) AS distintos_company_centro FROM centros_de_costo");
echo 'total=' . $tot->total . ', id_centro_unicos=' . $tot->distintos_id_centro . ', company_id_centro_unicos=' . $tot->distintos_company_centro . PHP_EOL;
