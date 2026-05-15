<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source = 'crm_v2';
$target = 'crm_v3';

$exists = DB::selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$target]);
if ($exists) {
    $count = DB::selectOne('SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$target])->c;
    if ((int) $count > 0) {
        fwrite(STDERR, "$target already exists and is not empty. Aborting.\n");
        exit(1);
    }
} else {
    DB::statement("CREATE DATABASE {$target} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

$pdo = DB::connection()->getPdo();
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

$tables = DB::select("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME", [$source]);
foreach ($tables as $table) {
    $name = $table->TABLE_NAME;
    if ($table->TABLE_TYPE === 'BASE TABLE') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$target}`.`{$name}` LIKE `{$source}`.`{$name}`");
    }
}

foreach ($tables as $table) {
    $name = $table->TABLE_NAME;
    if ($table->TABLE_TYPE === 'VIEW') {
        $row = DB::selectOne("SHOW CREATE VIEW `{$source}`.`{$name}`");
        $create = $row->{'Create View'};
        $create = preg_replace('/CREATE ALGORITHM=.*? VIEW /i', 'CREATE VIEW ', $create);
        $create = str_replace("`{$source}`.", "`{$target}`.", $create);
        $create = str_replace("CREATE VIEW `{$name}`", "CREATE VIEW `{$target}`.`{$name}`", $create);
        $pdo->exec("DROP VIEW IF EXISTS `{$target}`.`{$name}`");
        $pdo->exec($create);
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
echo "cloned\n";
