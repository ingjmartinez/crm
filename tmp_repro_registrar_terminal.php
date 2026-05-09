<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AgenciaController;

DB::beginTransaction();
try {
    $controller = app(AgenciaController::class);
    $request = Request::create('/agencias-no-registradas-registrar-terminal', 'POST', ['terminal' => '09999998']);
    $response = $controller->registrarTerminalNoRegistrada($request);
    echo $response->getContent().PHP_EOL;
    DB::rollBack();
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERROR: '.get_class($e).PHP_EOL;
    echo $e->getMessage().PHP_EOL;
    echo $e->getFile().':'.$e->getLine().PHP_EOL;
}
