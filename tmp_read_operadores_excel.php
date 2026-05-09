<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$path = 'C:/drive/OneDrive/PROYECTO JOSELITO/operadores.xlsx';
$spreadsheet = IOFactory::load($path);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);
foreach ($rows as $idx => $row) {
    if ($idx > 40) break;
    $vals = [];
    foreach ($row as $k => $v) {
        $v = trim((string)$v);
        if ($v !== '') $vals[] = $k.':'.$v;
    }
    if (!empty($vals)) {
        echo $idx.'|'.implode(' | ', $vals).PHP_EOL;
    }
}
