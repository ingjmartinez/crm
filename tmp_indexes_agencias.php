<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$rows = Illuminate\Support\Facades\DB::select("SHOW INDEX FROM agencias");
foreach ($rows as $r) {
    echo $r->Key_name.'|'.$r->Non_unique.'|'.$r->Column_name.PHP_EOL;
}
