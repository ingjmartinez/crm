<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$rows = Illuminate\Support\Facades\DB::select("SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='agencias' ORDER BY ORDINAL_POSITION");
foreach ($rows as $c) {
    echo $c->COLUMN_NAME.'|'.$c->IS_NULLABLE.'|'.($c->COLUMN_DEFAULT===null?'NULL':$c->COLUMN_DEFAULT).'|'.$c->COLUMN_TYPE.PHP_EOL;
}
