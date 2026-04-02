<?php
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

function out($title, $data) {
    echo "\n=== {$title} ===\n";
    foreach ($data as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

out('net.fecha column', DB::select("SHOW COLUMNS FROM vt_usuarios_net LIKE 'fecha'"));
out('bet.fecha column', DB::select("SHOW COLUMNS FROM vt_usuarios_bet LIKE 'fecha'"));
out('net sample', DB::select("SELECT fecha, producto_id FROM vt_usuarios_net WHERE fecha IS NOT NULL LIMIT 10"));
out('bet sample', DB::select("SELECT fecha, producto_id FROM vt_usuarios_bet WHERE fecha IS NOT NULL LIMIT 10"));
