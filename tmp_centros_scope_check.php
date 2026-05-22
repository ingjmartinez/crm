<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (['168','169','todas'] as $emp) {
    $count = App\Models\CentroDeCosto::query()->empresa($emp)->count();
    echo $emp . ' => ' . $count . PHP_EOL;
}
