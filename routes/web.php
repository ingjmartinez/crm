<?php

use App\Http\Controllers\Api;
use Illuminate\Validation\Rules\In;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\PremioController;
use App\Http\Controllers\VentasController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\RecargasController;
use App\Http\Controllers\FaltantesController;
use App\Http\Controllers\PaqueticoController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\IncentivosController;
use App\Http\Controllers\VentaFlashController;
use App\Http\Controllers\VentasProductosController;
use App\Http\Controllers\FinanceDashboardController;
use App\Http\Controllers\PagoAOtraEmpresaController;
use App\Http\Controllers\PagoMismaEmpresaController;
use App\Http\Controllers\RegistroEmpleadoController;
use App\Http\Controllers\PagoPorOtraEmpresaController;

Route::get('/', function () {
    return view('contabilidad');
});

Route::get('/api-cuentas', [Api::class, 'getCuentas']);
Route::get('/api-entradas', [Api::class, 'getEntradas']);

Route::get('/generar-token', [TokenController::class, 'generateToken']);
Route::get('/iniciar-session', [TokenController::class, 'iniciarSession']);
Route::get('/login-flash', [TokenController::class, 'loginFlash']);

Route::get('/ventas-por-usuario-lotobet', fn() => view('lotobet.ventas-usuario'));
Route::get('/faltantes-lotobet', fn() => view('lotobet.faltantes'));
Route::get('/faltantes-lotonet', fn() => view('lotonet.faltantes'));
Route::get('/ventas-por-producto-lotobet', fn() => view('lotobet.ventas-productos'));
Route::get('/recargas-lotobet', fn() => view('lotobet.recargas'));
Route::get('/premios-lotobet', fn() => view('lotobet.premios'));
Route::get('/pagos-misma-empresa-lotobet', fn() => view('lotobet.pagos-misma-empresa'));
Route::get('/pagos-aotra-empresa-lotobet', fn() => view('lotobet.pagos-aotra-empresa'));
Route::get('/pagos-porotra-empresa-lotobet', fn() => view('lotobet.pagos-porotra-empresa'));
Route::get('/asistencias-lotobet', fn() => view('lotobet.asistencias'));

Route::get('/ventas-usuarios-lotobet', [VentasController::class, 'getVentasUsuariosLotobet']);
Route::get('/save-ventas-usuarios-lotobet', [VentasController::class, 'saveVentasUsuariosLotobet']);
Route::get('/delete-ventas-usuarios-lotobet', [VentasController::class, 'deleteVentasUsuariosLotobet']);

Route::get('/get-faltantes-lotobet', [FaltantesController::class, 'getFaltantesLotobet']);
Route::get('/save-faltantes-lotobet', [FaltantesController::class, 'saveFaltantesLotobet']);
Route::get('/delete-faltantes-lotobet', [FaltantesController::class, 'deleteFaltantesLotobet']);

Route::get('/ventas-producto-lotobet', [VentasProductosController::class, 'getVentasProductosLotobet']);
Route::get('/save-ventas-producto-lotobet', [VentasProductosController::class, 'saveVentasProductosLotobet']);
Route::get('/delete-ventas-producto-lotobet', [VentasProductosController::class, 'deleteVentasProductosLotobet']);

Route::get('/get-recargas-lotobet', [RecargasController::class, 'getRecargasLotobet']);
Route::get('/save-recargas-lotobet', [RecargasController::class, 'saveRecargasLotobet']);
Route::get('/delete-recargas-lotobet', [RecargasController::class, 'deleteRecargasLotobet']);

Route::get('/get-premios-lotobet', [PremioController::class, 'getPremiosLotobet']);
Route::get('/save-premios-lotobet', [PremioController::class, 'savePremiosLotobet']);
Route::get('/delete-premios-lotobet', [PremioController::class, 'deletePremiosLotobet']);

Route::get('/get-pagos-misma-empresa-lotobet', [PagoMismaEmpresaController::class, 'getPagosMismaEmpresaLotobet']);
Route::get('/save-pagos-misma-empresa-lotobet', [PagoMismaEmpresaController::class, 'savePagosMismaEmpresaLotobet']);
Route::get('/delete-pagos-misma-empresa-lotobet', [PagoMismaEmpresaController::class, 'deletePagosMismaEmpresaLotobet']);

Route::get('/get-pagos-aotra-empresa-lotobet', [PagoAOtraEmpresaController::class, 'getPagosLotobet']);
Route::get('/save-pagos-aotra-empresa-lotobet', [PagoAOtraEmpresaController::class, 'savePagosLotobet']);
Route::get('/delete-pagos-aotra-empresa-lotobet', [PagoAOtraEmpresaController::class, 'deletePagosLotobet']);

Route::get('/get-pagos-porotra-empresa-lotobet', [PagoPorOtraEmpresaController::class, 'getPagosLotobet']);
Route::get('/save-pagos-porotra-empresa-lotobet', [PagoPorOtraEmpresaController::class, 'savePagosLotobet']);
Route::get('/delete-pagos-porotra-empresa-lotobet', [PagoPorOtraEmpresaController::class, 'deletePagosLotobet']);

Route::get('/get-asistencias-lotobet', [AsistenciaController::class, 'getAsistenciasLotobet']);
Route::get('/save-asistencias-lotobet', [AsistenciaController::class, 'saveAsistenciasLotobet']);
Route::get('/delete-asistencias-lotobet', [AsistenciaController::class, 'deleteAsistenciasLotobet']);

Route::get('/ventas-por-usuario-lotonet', fn() => view('lotonet.ventas-usuario'));
Route::get('/faltantes-lotonet', fn() => view('lotonet.faltantes'));
Route::get('/paquetico-lotonet', fn() => view('lotonet.paquetico'));
Route::get('/recargas-lotonet', fn() => view('lotonet.recargas'));
Route::get('/ventas-por-producto-lotonet', fn() => view('lotonet.ventas-productos'));
Route::get('/premios-lotonet', fn() => view('lotonet.premios'));
Route::get('/pagos-misma-empresa-lotonet', fn() => view('lotonet.pagos-misma-empresa'));
Route::get('/pagos-aotra-empresa-lotonet', fn() => view('lotonet.pagos-aotra-empresa'));
Route::get('/pagos-porotra-empresa-lotonet', fn() => view('lotonet.pagos-porotra-empresa'));
Route::get('/asistencias-lotonet', fn() => view('lotonet.asistencias'));

Route::get('/ventas-usuarios-lotonet', [VentasController::class, 'getVentasUsuariosLotonet']);
Route::get('/save-ventas-usuarios-lotonet', [VentasController::class, 'saveVentasUsuariosLotonet']);
Route::get('/delete-ventas-usuarios-lotonet', [VentasController::class, 'deleteVentasUsuariosLotonet']);

Route::get('/get-faltantes-lotonet', [FaltantesController::class, 'getFaltantesLotonet']);
Route::get('/save-faltantes-lotonet', [FaltantesController::class, 'saveFaltantesLotonet']);
Route::get('/delete-faltantes-lotonet', [FaltantesController::class, 'deleteFaltantesLotonet']);

Route::get('/get-paquetico-lotonet', [PaqueticoController::class, 'get']);
Route::get('/save-paquetico-lotonet', [PaqueticoController::class, 'save']);
Route::get('/delete-paquetico-lotonet', [PaqueticoController::class, 'delete']);

Route::get('/get-recargas-lotonet', [RecargasController::class, 'getRecargasLotonet']);
Route::get('/save-recargas-lotonet', [RecargasController::class, 'saveRecargasLotonet']);
Route::get('/delete-recargas-lotonet', [RecargasController::class, 'deleteRecargasLotonet']);

Route::get('/ventas-producto-lotonet', [VentasProductosController::class, 'getVentasProductosLotonet']);
Route::get('/save-ventas-producto-lotonet', [VentasProductosController::class, 'saveVentasProductosLotonet']);
Route::get('/delete-ventas-producto-lotonet', [VentasProductosController::class, 'deleteVentasProductosLotonet']);

Route::get('/get-premios-lotonet', [PremioController::class, 'getPremiosLotonet']);
Route::get('/save-premios-lotonet', [PremioController::class, 'savePremiosLotonet']);
Route::get('/delete-premios-lotonet', [PremioController::class, 'deletePremiosLotonet']);

Route::get('/get-pagos-misma-empresa-lotonet', [PagoMismaEmpresaController::class, 'getPagosLotonet']);
Route::get('/save-pagos-misma-empresa-lotonet', [PagoMismaEmpresaController::class, 'savePagosLotonet']);
Route::get('/delete-pagos-misma-empresa-lotonet', [PagoMismaEmpresaController::class, 'deletePagosLotonet']);

Route::get('/get-pagos-aotra-empresa-lotonet', [PagoAOtraEmpresaController::class, 'getPagosLotonet']);
Route::get('/save-pagos-aotra-empresa-lotonet', [PagoAOtraEmpresaController::class, 'savePagosLotonet']);
Route::get('/delete-pagos-aotra-empresa-lotonet', [PagoAOtraEmpresaController::class, 'deletePagosLotonet']);

Route::get('/get-pagos-porotra-empresa-lotonet', [PagoPorOtraEmpresaController::class, 'getPagosLotonet']);
Route::get('/save-pagos-porotra-empresa-lotonet', [PagoPorOtraEmpresaController::class, 'savePagosLotonet']);
Route::get('/delete-pagos-porotra-empresa-lotonet', [PagoPorOtraEmpresaController::class, 'deletePagosLotonet']);

Route::get('/get-asistencias-lotonet', [AsistenciaController::class, 'getAsistenciasLotonet']);
Route::get('/save-asistencias-lotonet', [AsistenciaController::class, 'saveAsistenciasLotonet']);
Route::get('/delete-asistencias-lotonet', [AsistenciaController::class, 'deleteAsistenciasLotonet']);

Route::get('/mar-ventas', fn() => view('mar.ventas'));

Route::get('/get-mar-ventas', [MarController::class, 'getVentas']);
Route::get('/save-mar-ventas', [MarController::class, 'saveVentas']);
Route::get('/delete-mar-ventas', [MarController::class, 'deleteVentas']);

Route::get('/empleados', [EmpleadoController::class, 'index']);
Route::get('/empleados/list', [EmpleadoController::class, 'list']);
Route::get('/empleados/show/{id}', [EmpleadoController::class, 'show']);
Route::post('/empleados/store', [EmpleadoController::class, 'store']);
Route::get('/empleados/destroy/{id}', [EmpleadoController::class, 'destroy']);
Route::get('/empleados/sincronizar', [EmpleadoController::class, 'sincronizar']);

Route::get('/empleados-no-regularizados', [EmpleadoController::class, 'noRegularizados']);
Route::get('/empleados-no-regularizados/list', [EmpleadoController::class, 'listNoRegularizados']);
Route::get('/ventas-sin-empleado', [EmpleadoController::class, 'ventasSinEmpleado']);
Route::get('/ventas-sin-empleado/list', [EmpleadoController::class, 'listVentasSinEmpleado']);

Route::get('/reportes-ventas-usuario-bet', [ReporteController::class, 'ventasUsuarioBet']);
Route::get('/reportes-ventas-usuario-bet/list', [ReporteController::class, 'listVentasUsuarioBet']);
Route::get('/reportes-ventas-usuario-bet/excel', [ReporteController::class, 'excelVentasUsuarioBet']);
Route::get('/reportes-ventas-usuario-bet/pdf', [ReporteController::class, 'pdfVentasUsuarioBet']);

Route::get('/reportes-faltantes-bet', [ReporteController::class, 'faltantesBet']);
Route::get('/reportes-faltantes-bet/list', [ReporteController::class, 'listFaltantesBet']);
Route::get('/reportes-faltantes-bet/excel', [ReporteController::class, 'excelFaltantesBet']);
Route::get('/reportes-faltantes-bet/pdf', [ReporteController::class, 'pdfFaltantesBet']);

Route::resource('registro-empleados', RegistroEmpleadoController::class);

Route::get('/reportes-bi/resumen-ventas', fn() => view('reportes-bi.resumen-ventas'));
Route::get('/reportes-bi/ventas-usuarios', fn() => view('reportes-bi.ventas-usuarios'));
Route::get('/reportes-bi/faltantes', fn() => view('reportes-bi.faltantes'));

Route::get('/incentivos', [IncentivosController::class, 'index']);
Route::get('/incentivos/generar', [IncentivosController::class, 'generar']);
Route::get('/incentivos/status/{id}', [IncentivosController::class, 'status']);
Route::get('/incentivos/list', [IncentivosController::class, 'list']);
Route::post('/incentivos/save', [IncentivosController::class, 'save']);
Route::get('/incentivos/list/plan-agencia', [IncentivosController::class, 'listPlanAgencia']);
Route::post('/incentivos/save/plan-agencia', [IncentivosController::class, 'savePlanAgencia']);
Route::get('/incentivos/list/efectividad-usuario', [IncentivosController::class, 'listEfectividad']);
Route::post('/incentivos/save/efectividad', [IncentivosController::class, 'saveEfectividad']);
Route::get('/incentivos/list/pago-incentivos-agente', [IncentivosController::class, 'listPagoAgente']);
Route::post('/incentivos/save/pago-incentivos-agente', [IncentivosController::class, 'savePagoAgente']);
Route::get('/incentivos/list/pago-incentivos-coordinador', [IncentivosController::class, 'listPagoCoordinador']);
Route::post('/incentivos/save/pago-incentivos-coordinador', [IncentivosController::class, 'savePagoCoordinador']);
Route::get('/incentivos/list/pago-incentivos-coordinador-detalle', [IncentivosController::class, 'listPagoCoordinadorDetalle']);
Route::get('/incentivos/list/pago-incentivos-admin', [IncentivosController::class, 'listPagoAdmin']);
Route::get('/incentivos/list/pago-incentivos-admin-detalle', [IncentivosController::class, 'listPagoAdminDetalle']);
Route::post('/incentivos/save/pago-incentivos-admin', [IncentivosController::class, 'savePagoAdmin']);

Route::get('/incentivos/reporte-pagos', [IncentivosController::class, 'reportePagos']);
Route::get('/incentivos/reporte-pago-incentivos', [IncentivosController::class, 'reportePagoIncentivos']);

Route::get('/incentivos/empleados', [EmpleadoController::class, 'incentivos']);
Route::get('/incentivos/empleados/list', [EmpleadoController::class, 'listEmpleados']);
Route::post('/incentivos/empleados/update', [EmpleadoController::class, 'updateEmpleadoIncentivo']);

Route::get('/generar-lotobet', fn() => view('lotobet.index'));
Route::get('/generar-lotonet', fn() => view('lotonet.index'));

Route::get('/ventas-flash-lotobet', [VentaFlashController::class, 'ventasFlashLotobet']);
Route::get('/get-ventas-flash-lotobet', [VentaFlashController::class, 'getVentasLotobet']);
Route::post('/save-ventas-flash-lotobet', [VentaFlashController::class, 'saveVentasLotobet']);
Route::get('/delete-ventas-flash-lotobet', [VentaFlashController::class, 'deleteVentasLotobet']);

Route::get('/ventas-flash-lotonet', [VentaFlashController::class, 'ventasFlashLotonet']);
Route::get('/get-ventas-flash-lotonet', [VentaFlashController::class, 'getVentasFlashLotonet']);
Route::post('/save-ventas-flash-lotonet', [VentaFlashController::class, 'saveVentasFlashLotonet']);
Route::get('/delete-ventas-flash-lotonet', [VentaFlashController::class, 'deleteVentasFlashLotonet']);

// dashboard finanzas lotobet
Route::get('/ventas-lotobet-dashboard', [FinanceDashboardController::class, 'indexLotobet']);
Route::get('/ventas-lotobet-dashboard/data', [FinanceDashboardController::class, 'data']);

// dashboard finanzas lotonet
Route::get('/ventas-lotonet-dashboard', [FinanceDashboardController::class, 'indexLotonet']);
Route::get('/ventas-lotonet-dashboard/data', [FinanceDashboardController::class, 'data']);