<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$excelPath = 'C:/drive/OneDrive/PROYECTO JOSELITO/operadores.xlsx';
$bladePath = 'resources/views/incentivos/reporte-nuevo-incentivo-v3.blade.php';

$sheet = IOFactory::load($excelPath)->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);
$data = [];
foreach ($rows as $idx => $row) {
    if ($idx === 1) {
        continue;
    }

    $grupo = trim((string)($row['A'] ?? ''));
    $nombre = trim((string)($row['B'] ?? ''));
    $empresa = trim((string)($row['C'] ?? ''));
    $pct = trim((string)($row['D'] ?? ''));

    if ($grupo === '' && $nombre === '' && $empresa === '' && $pct === '') {
        continue;
    }

    $pctNum = is_numeric(str_replace(',', '.', $pct)) ? (float)str_replace(',', '.', $pct) : 0.0;

    $data[] = [
        'grupo' => $grupo !== '' ? preg_replace('/\s+/u', ' ', $grupo) : '4. Operadores',
        'nombre' => preg_replace('/\s+/u', ' ', $nombre),
        'empresa' => preg_replace('/\s+/u', ' ', $empresa),
        'pct' => $pctNum,
    ];
}

$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
$function = "function getDefaultOperatorRows() {\n        return {$json};\n    }";

$blade = file_get_contents($bladePath);
$pattern = '/function getDefaultOperatorRows\(\) \{[\s\S]*?\n\s*\}/';
$updated = preg_replace($pattern, $function, $blade, 1);

if ($updated === null) {
    fwrite(STDERR, "No se pudo reemplazar la funcion getDefaultOperatorRows.\n");
    exit(1);
}

file_put_contents($bladePath, $updated);
echo "ok\n";
