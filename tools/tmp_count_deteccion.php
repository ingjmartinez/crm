<?php
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

function scalar($sql) {
    $row = DB::selectOne($sql);
    return (array)$row;
}

$hoy = now();
$anio = (int)$hoy->year;
$mes = (int)$hoy->month;
$ini = \Carbon\Carbon::create($anio, $mes, 1)->startOfMonth()->toDateString();
$fin = \Carbon\Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();

echo "Periodo actual: {$anio}-{$mes} ({$ini} a {$fin})\n";

print_r(scalar("SELECT COUNT(DISTINCT TRIM(CAST(producto_id AS CHAR))) c FROM vt_usuarios_net WHERE producto_id IS NOT NULL AND TRIM(CAST(producto_id AS CHAR)) <> ''"));
print_r(scalar("SELECT COUNT(DISTINCT TRIM(CAST(producto_id AS CHAR))) c FROM vt_usuarios_bet WHERE producto_id IS NOT NULL AND TRIM(CAST(producto_id AS CHAR)) <> ''"));
print_r(scalar("SELECT COUNT(DISTINCT producto_id) c FROM catalogo_juegos"));

print_r(scalar("SELECT COUNT(DISTINCT TRIM(CAST(producto_id AS CHAR))) c FROM vt_usuarios_net WHERE fecha BETWEEN '{$ini}' AND '{$fin}' AND producto_id IS NOT NULL AND TRIM(CAST(producto_id AS CHAR)) <> ''"));
print_r(scalar("SELECT COUNT(DISTINCT TRIM(CAST(producto_id AS CHAR))) c FROM vt_usuarios_bet WHERE fecha BETWEEN '{$ini}' AND '{$fin}' AND producto_id IS NOT NULL AND TRIM(CAST(producto_id AS CHAR)) <> ''"));

$sqlTodo = "
SELECT COUNT(*) AS nuevos
FROM (
  SELECT DISTINCT TRIM(CAST(producto_id AS CHAR)) AS producto_id
  FROM vt_usuarios_net
  WHERE producto_id IS NOT NULL AND TRIM(CAST(producto_id AS CHAR)) <> ''
  UNION
  SELECT DISTINCT TRIM(CAST(producto_id AS CHAR)) AS producto_id
  FROM vt_usuarios_bet
  WHERE producto_id IS NOT NULL AND TRIM(CAST(producto_id AS CHAR)) <> ''
) t
LEFT JOIN catalogo_juegos c ON TRIM(c.producto_id) = t.producto_id
WHERE c.id IS NULL
";
print_r(scalar($sqlTodo));

$sqlMes = "
SELECT COUNT(*) AS nuevos
FROM (
  SELECT DISTINCT TRIM(CAST(producto_id AS CHAR)) AS producto_id
  FROM vt_usuarios_net
  WHERE fecha BETWEEN '{$ini}' AND '{$fin}'
    AND producto_id IS NOT NULL AND TRIM(CAST(producto_id AS CHAR)) <> ''
  UNION
  SELECT DISTINCT TRIM(CAST(producto_id AS CHAR)) AS producto_id
  FROM vt_usuarios_bet
  WHERE fecha BETWEEN '{$ini}' AND '{$fin}'
    AND producto_id IS NOT NULL AND TRIM(CAST(producto_id AS CHAR)) <> ''
) t
LEFT JOIN catalogo_juegos c ON TRIM(c.producto_id) = t.producto_id
WHERE c.id IS NULL
";
print_r(scalar($sqlMes));
