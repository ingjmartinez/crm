<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$path = 'C:/drive/OneDrive/PROYECTO JOSELITO/operadores.xlsx';
$sheet = IOFactory::load($path)->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);
$data = [];
foreach ($rows as $idx => $row) {
    if ($idx === 1) continue;
    $grupo = trim((string)($row['A'] ?? ''));
    $nombre = trim((string)($row['B'] ?? ''));
    $empresa = trim((string)($row['C'] ?? ''));
    $pct = trim((string)($row['D'] ?? ''));
    if ($grupo === '' && $nombre === '' && $empresa === '' && $pct === '') continue;
    $pctNum = is_numeric(str_replace(',', '.', $pct)) ? (float)str_replace(',', '.', $pct) : 0.0;
    $data[] = [
        'grupo' => $grupo !== '' ? $grupo : '4. Operadores',
        'nombre' => preg_replace('/\s+/u', ' ', $nombre),
        'empresa' => $empresa,
        'pct' => $pctNum,
    ];
}
echo json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
