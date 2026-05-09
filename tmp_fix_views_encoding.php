<?php
$files = [
    'resources/views/agencias/index.blade.php',
    'resources/views/usuarios/index.blade.php',
];

$map = [
    'Â¿' => '¿',
    'Â¡' => '¡',
    'Â©' => '©',
    'Ã‰' => 'É',
    'Ã“' => 'Ó',
    'Ãš' => 'Ú',
    'Ã‘' => 'Ñ',
    'Ã¡' => 'á',
    'Ã©' => 'é',
    'Ã­' => 'í',
    'Ã³' => 'ó',
    'Ãº' => 'ú',
    'Ã±' => 'ñ',
];

foreach ($files as $f) {
    $c = file_get_contents($f);
    $c = strtr($c, $map);
    if (str_starts_with($c, '?@extends')) {
        $c = substr($c, 1);
    }
    file_put_contents($f, $c);
    echo "ok:$f\n";
}
