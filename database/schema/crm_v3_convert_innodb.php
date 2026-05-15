<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$schema = 'crm_v3';
$pdo = DB::connection()->getPdo();
$rows = DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE=? AND ENGINE <> ?', [$schema, 'BASE TABLE', 'InnoDB']);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($rows as $r) {
    $table = $r->TABLE_NAME;
    $pdo->exec("ALTER TABLE `{$schema}`.`{$table}` ENGINE=InnoDB");
    echo "converted {$table}\n";
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
echo 'converted_count=' . count($rows) . "\n";
