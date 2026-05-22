<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = Illuminate\Support\Facades\DB::select("SELECT company_id, COUNT(*) AS total FROM centros_de_costo GROUP BY company_id ORDER BY company_id");
foreach ($rows as $r) {
  echo ($r->company_id ?? '(null)') . ' => ' . $r->total . PHP_EOL;
}
foreach (['168','169','todas'] as $emp) {
  $count = App\Models\CentroDeCosto::query()->empresa($emp)->count();
  echo 'scope_'.$emp.' => '.$count.PHP_EOL;
}
