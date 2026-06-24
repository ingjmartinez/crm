@extends('app')

@section('content')
    <style>
        .empleado-maestro-pendiente {
            background: #fff7df;
            border: 1px solid #fde8ae;
            border-radius: 999px;
            color: #9a6a12;
            display: inline-block;
            font-size: .78rem;
            font-weight: 600;
            padding: .22rem .55rem;
        }

        #tableNuevoIncentivo th,
        #tableNuevoIncentivo td {
            vertical-align: middle;
        }

        #tableNuevoIncentivo thead th {
            font-size: .78rem;
            line-height: 1.2;
            white-space: nowrap !important;
        }

        #tableNuevoIncentivo td {
            white-space: nowrap;
        }

        #tableNuevoIncentivo .col-cedula {
            min-width: 105px;
        }

        #tableNuevoIncentivo .col-nombre {
            min-width: 190px;
        }

        #tableNuevoIncentivo .col-empresa {
            min-width: 120px;
        }

        #tableNuevoIncentivo .col-monto {
            min-width: 118px;
        }

        #tableNuevoIncentivo .col-dias {
            min-width: 68px;
        }

        #tableNuevoIncentivo .col-regla {
            min-width: 145px;
        }

        #tableNuevoIncentivo .cell-nombre {
            white-space: normal;
            min-width: 190px;
        }

        .ni-count-action {
            cursor: pointer;
            transition: transform .15s ease, color .15s ease;
        }

        .ni-count-action:hover {
            color: #d97706 !important;
            transform: translateY(-1px);
        }

        .agencia-formato-comparativa .agencia-nombre {
            color: #172033;
            font-weight: 600;
            line-height: 1.15;
        }

        .agencia-formato-comparativa .agencia-terminal {
            color: #64748b;
            display: block;
            font-size: .78rem;
            margin-top: .15rem;
        }
    </style>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Reporte Nuevo Incentivo V5 - Pruebas</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('inicio.index') }}">Inicio</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('incentivos.index') }}">Incentivos</a></li>
                                    <li class="breadcrumb-item active">Reporte Nuevo Incentivo V5 - Pruebas</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-4 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Vendido</p>
                                <h4 class="mb-0" id="ni_total_vendido">0.00</h4>
                            </div>
                        </div>
                        <div class="card card-animate mt-3">
                            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                <div>
                                    <p class="text-uppercase fw-medium text-muted mb-1">Usuarios que Cumplieron</p>
                                    <h4 class="mb-0 text-success" id="ni_count_cumplen">0</h4>
                                </div>
                                <div>
                                    <p class="text-uppercase fw-medium text-muted mb-1">Usuarios que No Cumplieron</p>
                                    <h4 class="mb-0 text-danger" id="ni_count_no_cumplen">0</h4>
                                </div>
                                <div>
                                    <p class="text-uppercase fw-medium text-muted mb-1">Usuarios por Actualizar</p>
                                    <h4 class="mb-0 text-warning ni-count-action" id="ni_count_por_actualizar" title="Ver cedulas por actualizar">0</h4>
                                </div>
                                <div>
                                    <p class="text-uppercase fw-medium text-muted mb-1">Agencias sin Empresa</p>
                                    <h4 class="mb-0 text-info ni-count-action" id="ni_count_agencias_sin_empresa" title="Ver terminales pendientes de empresa">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body text-start" style="min-height: 178px;">
                                <p class="text-uppercase fw-medium text-muted mb-1">Desglose de porcentajes</p>
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm table-bordered mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Categoria</th>
                                                <th class="text-end">% Configurado</th>
                                                <th class="text-end">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody id="ni_pct_puesto_resumen">
                                            <tr>
                                                <td>1 Gtes. y Encarg.</td>
                                                <td class="text-end">0.00%</td>
                                                <td class="text-end">0.00</td>
                                            </tr>
                                            <tr>
                                                <td>2 Monitoreo</td>
                                                <td class="text-end">0.00%</td>
                                                <td class="text-end">0.00</td>
                                            </tr>
                                            <tr>
                                                <td>4 Operadores + 5 Servs. Tecnicos</td>
                                                <td class="text-end">0.00%</td>
                                                <td class="text-end">0.00</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body text-start" style="min-height: 178px;">
                                <p class="text-uppercase fw-medium text-muted mb-1">Total Incentivo a Pagar</p>
                                <h4 class="mb-0" id="ni_total_incentivo">0.00</h4>
                                <div class="d-block mt-1 fw-semibold fs-5 text-primary text-start" id="ni_admin_resumen">
                                    <div>Porcentaje (0%): 0.00</div>
                                    <div>Administrativo: 0.00</div>
                                    <div>Coordinador: 0.00</div>
                                </div>
                                <div class="mt-2 fw-bold fs-4 text-success text-start" id="ni_total_con_admin">Total a Pagar Final: 0.00</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="card-title mb-0">Calculo por sistema y tipo de pago (V5 - Pruebas)</h5>
                                    <small class="text-muted">Configura tramos de venta mensual por pago a 60, 70 u 80.</small>
                                </div>
                                <div class="d-flex gap-3 align-items-end flex-wrap">
                                    <div>
                                        <label class="mb-0" for="ni_sistema">Sistema</label>
                                        <select id="ni_sistema" class="form-select">
                                            <option value="Todos">Todos</option>
                                            <option value="Lotobet">Lotobet</option>
                                            <option value="Lotonet">Lotonet</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-0" for="ni_filtro_cumplimiento">Cumplimiento</label>
                                        <select id="ni_filtro_cumplimiento" class="form-select">
                                            <option value="todos">Todos</option>
                                            <option value="cumplidos">Cumplidos</option>
                                            <option value="no_cumplidos">No cumplidos</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-0" for="ni_filtro_empresa">Empresa</label>
                                        <select id="ni_filtro_empresa" class="form-select">
                                            <option value="todos">Todas</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-0" for="ni_modo_calculo">Modo de calculo</label>
                                        <select id="ni_modo_calculo" class="form-select">
                                            <option value="general">General consolidado</option>
                                            <option value="separado_empresa">Separado por empresa</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-info" id="btnFiltrarCumplimiento">Filtrar</button>
                                    <div>
                                        <label class="mb-0" for="ni_fecha_ini">Fecha inicio</label>
                                        <input type="date" id="ni_fecha_ini" class="form-control">
                                    </div>
                                    <div>
                                        <label class="mb-0" for="ni_fecha_fin">Fecha fin</label>
                                        <input type="date" id="ni_fecha_fin" class="form-control">
                                    </div>
                                    <div>
                                        <label class="mb-0" for="ni_min_dias">Min. dias venta</label>
                                        <input type="number" id="ni_min_dias" class="form-control" value="1" min="1" step="1">
                                    </div>
                                    <div>
                                        <label class="mb-0" for="ni_tipo_pago">Tipo de pago</label>
                                        <select id="ni_tipo_pago" class="form-select">
                                            <option value="tramos_60">Pagos a 60</option>
                                            <option value="tramos_70">Pagos a 70</option>
                                            <option value="tramos_80">Pagos a 80</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-soft-secondary" id="btnConfigPct">Configurar Tipo de Pago</button>
                                    <button type="button" class="btn btn-soft-secondary" id="btnConfigPuestoPct">Configurar % de puesto</button>
                                    <button type="button" class="btn btn-soft-secondary" id="btnConfigAdminPct">Porcentaje</button>
                                    <button type="button" class="btn btn-soft-secondary" id="btnConfigAdministrativos">Administrativo</button>
                                    <button type="button" class="btn btn-soft-secondary" id="btnConfigCoordinadores">Coordinador</button>
                                    <button type="button" class="btn btn-primary" id="btnGenerarNuevoIncentivo">Generar Reporte</button>
                                    <button type="button" class="btn btn-dark" id="btnGenerarExcelPago">Generar Excel de pago</button>
                                    <button type="button" class="btn btn-warning" id="btnConsultarFaltantes">Faltantes</button>
                                    <button type="button" class="btn btn-success" id="btnConsultarRecargasPaqueticos">Recargas/Paqueticos</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-2 text-muted" id="ni_rango_evaluado"></div>
                                <table id="tableNuevoIncentivo" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th class="col-cedula">Cedula</th>
                                            <th>IdEmpleado</th>
                                            <th class="col-nombre">Nombre</th>
                                            <th class="col-empresa">Empresa</th>
                                            <th class="col-monto text-end">Ventas Ult. Mes</th>
                                            <th class="col-monto text-end">Ventas Mes Actual</th>
                                            <th class="col-dias text-center">Dias</th>
                                            <th class="col-regla">Cumple Regla</th>
                                            <th class="col-monto text-end">Total a Pagar</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="modalConfigPct" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalConfigPctTitle">Configurar Tramos de Pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">Desde 1,000,001 se aplica porcentaje sobre ventas con tope maximo de 50,000.</div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ventas Mensual Desde</th>
                                    <th>Hasta</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyTramosPago"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft-secondary" id="btnRestaurarTramos">Restaurar por defecto</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarPct">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modalConfigAdminPct" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Configurar Porcentaje</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="admin_pct_bruto">% bruto (ejemplo: 9 = 9%)</label>
                    <input type="number" id="admin_pct_bruto" class="form-control" value="0" min="0" step="0.01">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarAdminPct">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modalAdministrativos" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Desglose Administrativo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Bolsa administrativa total</small>
                                <strong id="admin_base_total">0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block" id="admin_distribuido_label">Monto por filtro activo</small>
                                <strong id="admin_distribuido_total">0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Total distribuido en tabla</small>
                                <strong id="admin_tabla_total">0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="border rounded p-2 h-100">
                                        <small class="text-muted d-block">1 Gtes. y Encarg.</small>
                                        <div class="fw-semibold" id="admin_cat_g1">27.00% | 0.00</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-2 h-100">
                                        <small class="text-muted d-block">2 Monitoreo</small>
                                        <div class="fw-semibold" id="admin_cat_g2">13.00% | 0.00</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-2 h-100">
                                        <small class="text-muted d-block">4 Operadores + 5 Servs. Tecnicos</small>
                                        <div class="fw-semibold" id="admin_cat_g45">60.00% | 0.00</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label mb-1 d-block">Filtrar grupo</label>
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-primary" id="btnAdminFiltroTodos">Todo</button>
                                <button type="button" class="btn btn-outline-primary" id="btnAdminFiltroG1">1 Gtes. y Encarg.</button>
                                <button type="button" class="btn btn-outline-primary" id="btnAdminFiltroG2">2 Monitoreo</button>
                                <button type="button" class="btn btn-outline-primary" id="btnAdminFiltroG45">4 Operadores + 5 Servs. Tecnicos</button>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height: 520px;">
                        <table class="table table-bordered table-sm align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="min-width: 180px;">Grupo</th>
                                    <th style="min-width: 260px;">Nombre</th>
                                    <th style="min-width: 140px;">Empresa</th>
                                    <th style="min-width: 120px;">% Total</th>
                                    <th style="min-width: 160px;" id="admin_monto_col_label">Monto</th>
                                    <th style="min-width: 110px;">Accion</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyAdministrativos"></tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="4" class="text-end">Total</th>
                                    <th class="text-end" id="admin_col_total">0.00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="btnExportAdministrativosExcel">Excel</button>
                    <button type="button" class="btn btn-primary" id="btnAgregarAdministrativoFila">Agregar fila</button>
                    <button type="button" class="btn btn-warning" id="btnGuardarAdministrativosPlantilla">Guardar cambios</button>
                    <button type="button" class="btn btn-soft-secondary" id="btnRestaurarAdministrativos">Restaurar plantilla</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modalConfigPuestoPct" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Configurar % de puesto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 260px;">Categoria</th>
                                    <th style="min-width: 160px;">% Manual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1 Gtes. y Encarg.</td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" id="puesto_pct_g1" class="form-control" value="27" min="0" step="0.01">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>2 Monitoreo</td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" id="puesto_pct_g2" class="form-control" value="13" min="0" step="0.01">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>4 Operadores + 5 Servs. Tecnicos</td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" id="puesto_pct_g45" class="form-control" value="60" min="0" step="0.01">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarPuestoPct">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modalCoordinadores" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Desglose Coordinador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Monto administrativo base</small>
                                <strong id="coord_base_total">0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Total distribuido</small>
                                <strong id="coord_distribuido_total">0.00</strong>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height: 520px;">
                        <table class="table table-bordered table-sm align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="min-width: 280px;">Nombre</th>
                                    <th style="min-width: 100px;">Agencias</th>
                                    <th style="min-width: 100px;">Validas</th>
                                    <th style="min-width: 160px;">Monto</th>
                                    <th style="min-width: 120px;">Detalle</th>
                                    <th style="min-width: 120px;">% Total</th>
                                    <th style="min-width: 160px;">Monto Coordinador</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyCoordinadores"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="btnExportCoordinadoresExcel">Excel</button>
                    <button type="button" class="btn btn-soft-secondary" id="btnRestaurarCoordinadores">Restaurar plantilla</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modalCoordinadorDetalle" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="coordinatorDetailTitle">Detalle de Usuarios</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive" style="max-height: 420px;">
                        <table class="table table-bordered table-sm align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="min-width: 140px;">Cedula</th>
                                    <th style="min-width: 260px;">Usuario</th>
                                    <th style="min-width: 160px;">Incentivo</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyCoordinadorDetalle"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft-secondary" id="btnBackToCoordinadores">Atras</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modalFaltantesIncentivo" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Faltantes de usuarios generados</h5>
                        <small class="text-muted" id="faltantesIncentivoRango">Consulta basada en las cedulas del reporte actual.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100 bg-light">
                                <small class="text-muted d-block text-uppercase fw-semibold">Total monto faltantes</small>
                                <strong class="fs-4 text-danger" id="faltantesIncentivoTotalMonto">0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100 bg-light">
                                <small class="text-muted d-block text-uppercase fw-semibold">Cantidad de faltantes</small>
                                <strong class="fs-4" id="faltantesIncentivoTotalCantidad">0</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100 bg-light">
                                <small class="text-muted d-block text-uppercase fw-semibold">Cedulas consultadas</small>
                                <strong class="fs-4" id="faltantesIncentivoTotalCedulas">0</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100 bg-light">
                                <small class="text-muted d-block text-uppercase fw-semibold">Monto incentivos con faltantes</small>
                                <strong class="fs-4 text-warning" id="faltantesIncentivoMontoIncentivos">0.00</strong>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="tableFaltantesIncentivo" class="table table-bordered table-striped align-middle" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Cedula</th>
                                    <th>Nombre</th>
                                    <th class="text-center">Cantidad de faltantes</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" id="btnAplicarFaltantesIncentivo">Aplicar</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modalRecargasPaqueticosIncentivo" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Ventas por recargas y paqueticos</h5>
                        <small class="text-muted" id="recargasPaqueticosIncentivoRango">Consulta basada en las cedulas del reporte actual.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100 bg-light">
                                <small class="text-muted d-block text-uppercase fw-semibold">Total ventas</small>
                                <strong class="fs-4 text-success" id="recargasPaqueticosTotalVentas">0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100 bg-light">
                                <small class="text-muted d-block text-uppercase fw-semibold">Recargas</small>
                                <strong class="fs-4" id="recargasPaqueticosTotalRecargas">0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100 bg-light">
                                <small class="text-muted d-block text-uppercase fw-semibold">Paqueticos</small>
                                <strong class="fs-4" id="recargasPaqueticosTotalPaqueticos">0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100 bg-light">
                                <small class="text-muted d-block text-uppercase fw-semibold">Monto incentivos con ventas</small>
                                <strong class="fs-4 text-warning" id="recargasPaqueticosMontoIncentivos">0.00</strong>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 bg-light">
                                <small class="text-muted d-block text-uppercase fw-semibold">Cantidad de ventas</small>
                                <strong class="fs-5" id="recargasPaqueticosCantidadVentas">0</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 bg-light">
                                <small class="text-muted d-block text-uppercase fw-semibold">Cedulas consultadas</small>
                                <strong class="fs-5" id="recargasPaqueticosTotalCedulas">0</strong>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="tableRecargasPaqueticosIncentivo" class="table table-bordered table-striped align-middle" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Cedula</th>
                                    <th>Nombre</th>
                                    <th class="text-end">Recargas</th>
                                    <th class="text-end">Paqueticos</th>
                                    <th class="text-end">Total ventas</th>
                                    <th class="text-center">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" id="btnAplicarRecargasPaqueticosIncentivo">Aplicar</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modalUsuariosActualizar" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Usuarios por actualizar en maestro de empleados</h5>
                        <small class="text-muted">Cedulas con ventas en el rango seleccionado que no tienen nombre asociado.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table id="tableUsuariosActualizar" class="table table-bordered table-striped align-middle" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Cedula</th>
                                    <th>Nombre</th>
                                    <th>Empresa</th>
                                    <th>Nombre agencia</th>
                                    <th>Ultimo dia con venta</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="btnExportUsuariosActualizarExcel">Excel</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modalAgenciasSinEmpresa" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Agencias por asignar empresa</h5>
                        <small class="text-muted">Terminales con ventas en el rango seleccionado que no tienen empresa asignada en agencias.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table id="tableAgenciasSinEmpresa" class="table table-bordered table-striped align-middle" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Terminal</th>
                                    <th>Nombre agencia</th>
                                    <th class="text-center">Usuarios</th>
                                    <th class="text-end">Ventas</th>
                                    <th>Ultimo dia con venta</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="btnExportAgenciasSinEmpresaExcel">Excel</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    const XML_DECL = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?' + '>';

    function buildRanges(percent, pagos) {
        return [
            { desde: 100001, hasta: 250000, pago: pagos[0], tipo: 'fijo' },
            { desde: 250001, hasta: 400000, pago: pagos[1], tipo: 'fijo' },
            { desde: 400001, hasta: 550000, pago: pagos[2], tipo: 'fijo' },
            { desde: 550001, hasta: 700000, pago: pagos[3], tipo: 'fijo' },
            { desde: 700001, hasta: 850000, pago: pagos[4], tipo: 'fijo' },
            { desde: 850001, hasta: 1000000, pago: pagos[5], tipo: 'fijo' },
            { desde: 1000001, hasta: 5000000, pago: percent, tipo: 'porcentaje' },
            { desde: 5000001, hasta: null, pago: percent, tipo: 'porcentaje' },
        ];
    }

    function getDefaultRanges() {
        return {
            tramos_60: buildRanges(1, [1000, 2000, 4000, 6000, 8000, 9000]),
            tramos_70: buildRanges(0.75, [750, 1500, 3000, 4500, 6000, 6750]),
            tramos_80: buildRanges(0.5, [500, 1000, 2000, 3000, 4000, 4500]),
        };
    }

    function normalizeAdministrativeRowsFromPayload(rows) {
        return (Array.isArray(rows) ? rows : []).map(function (row) {
            return {
                id: row?.id ?? null,
                grupo: String(row?.grupo ?? '').trim(),
                nombre: String(row?.nombre ?? '').trim(),
                empresa: String(row?.empresa ?? '').trim(),
                pct: Math.max(0, toNumber(row?.pct) / 100),
            };
        });
    }

    let administrativeRowsConfig = @json($administrativosConfig ?? []);

        function getAdministrativeRowsFromConfig() {
        return normalizeAdministrativeRowsFromPayload(administrativeRowsConfig);
    }

    function splitAdministrativeRowsByGroup(rows) {
        const allRows = Array.isArray(rows) ? rows : [];

        return {
            administrativos: allRows.filter((row) => normalizeAdministrativeGroup(row.grupo) !== '4. Operadores'),
            operadores: allRows.filter((row) => normalizeAdministrativeGroup(row.grupo) === '4. Operadores'),
        };
    }

    function getDefaultAdministrativeRows() {
        return splitAdministrativeRowsByGroup(getAdministrativeRowsFromConfig()).administrativos;
    }

    function getDefaultOperatorRows() {
        return splitAdministrativeRowsByGroup(getAdministrativeRowsFromConfig()).operadores;
    }

    function getDefaultCoordinatorRows() {
        return @json($coordinadores ?? []);
    }
    let payoutRangesByType = getDefaultRanges();
    let administrativeRows = getDefaultAdministrativeRows();
    let operatorRows = getDefaultOperatorRows();
    let coordinatorRows = getDefaultCoordinatorRows();
    let cachedRows = [];
    let currentFilteredRows = [];
    let cachedMeta = {};
    let cachedSistema = null;
    let cachedTipoPago = null;
    let cachedModoCalculo = null;
    let tableFaltantesIncentivo = null;
    let tableRecargasPaqueticosIncentivo = null;
    let tableUsuariosActualizar = null;
    let tableAgenciasSinEmpresa = null;
    let lastFaltantesCedulas = new Set();
    let excludedFaltantesCedulas = new Set();
    let lastRecargasPaqueticosCedulas = new Set();
    let recargasPaqueticosDescuentos = new Map();
    let adminPctBruto = 0;
    const ADMINISTRATIVE_SHARE = 0.40;
    const COORDINATOR_SHARE = 0.60;
    let administrativeGroupFilter = 'todos';
    const ADMIN_GROUP_OPTIONS = [
        '1. Gtes. Y Encarg.',
        '2. Monitoreo',
        '4. Operadores',
        '5. Servs. Tecnicos',
    ];
    const ADMIN_EMPRESA_OPTIONS = [
        'Consorcio Joselito',
        'Negosur',
    ];
    let puestoPctConfig = {
        g1: 27,
        g2: 13,
        g45: 60,
    };
    let currentDistributionBase = 0;
    let currentAdministrativePoolBase = 0;
    let currentAdministrativePoolByEmpresa = {};
    let currentAdministrativeBase = 0;
    let currentOperatorBase = 0;
    let currentCoordinatorBase = 0;
    let coordinatorUserDetailsByCoordinator = {};
    const META_MINIMA_VENTA = 100001;

    function toNumber(value) {
        if (value === null || value === undefined) return 0;
        return parseFloat(String(value).replace(/,/g, '')) || 0;
    }

    async function parseResponseAsJson(response, contexto) {
        const contentType = String(response.headers.get('content-type') || '').toLowerCase();
        const bodyText = await response.text();
        let payload = null;

        if (bodyText !== '') {
            try {
                payload = JSON.parse(bodyText);
            } catch (_parseError) {
                payload = null;
            }
        }

        if (!response.ok) {
            const serverMessage = payload?.message || payload?.error || '';
            const nonJsonHint = !contentType.includes('application/json')
                ? 'El servidor devolvio una respuesta no JSON.'
                : '';
            const detail = [serverMessage, nonJsonHint].filter(Boolean).join(' | ');
            throw new Error(`${contexto} (HTTP ${response.status})${detail ? ': ' + detail : ''}`);
        }

        if (!payload) {
            throw new Error(`${contexto}: Respuesta vacia o JSON invalido.`);
        }

        return payload;
    }

    function formatPercentDisplay(value) {
        const number = parseFloat(value);
        if (Number.isNaN(number)) return '0';
        return Number.isInteger(number) ? String(number) : String(number);
    }

    function formatMoney(value) {
        return toNumber(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderResumenEmpresas(meta) {
        const resumen = Array.isArray(meta?.resumen_empresas) ? meta.resumen_empresas : [];
        if (!resumen.length) {
            return '';
        }

        const resumenText = ' | ' + resumen
            .map((row) => {
                const empresa = normalizeEmpresaLabel(row?.empresa);
                const vendido = formatMoney(row?.total_vendido);
                const incentivo = formatMoney(row?.total_incentivo);
                return `${empresa}: ventas ${vendido}, incentivo ${incentivo}`;
            })
            .join(' | ');
        const aviso = String(meta?.aviso_agencias_por_asignar_empresa || '').trim();

        return aviso ? `${resumenText} | Nota: ${aviso}` : resumenText;
    }

    function evaluateMetaMinima(row) {
        const ventasMesActual = toNumber(row?.ventas_mes_actual);
        const cumplio = ventasMesActual >= META_MINIMA_VENTA;
        const faltante = cumplio ? 0 : (META_MINIMA_VENTA - ventasMesActual);
        const faltantePct = META_MINIMA_VENTA > 0
            ? Math.max((faltante / META_MINIMA_VENTA) * 100, 0)
            : 0;

        return {
            cumplio,
            ventasMesActual,
            faltante,
            faltantePct,
        };
    }

    function buildAdministrativePoolByEmpresa(data) {
        const resumenEmpresas = Array.isArray(cachedMeta?.resumen_empresas) ? cachedMeta.resumen_empresas : [];
        const source = resumenEmpresas.length
            ? resumenEmpresas.map(row => ({
                empresa: row?.empresa,
                incentivo: row?.total_incentivo,
            }))
            : (Array.isArray(data) ? data : []).map(row => ({
                empresa: row?.empresa,
                incentivo: row?.nuevo_incentivo,
            }));

        return source.reduce((acc, item) => {
            const key = normalizeAdministrativeEmpresaKey(item?.empresa);
            const incentivo = toNumber(item?.incentivo);
            if (!acc[key]) {
                acc[key] = 0;
            }
            acc[key] += incentivo * (adminPctBruto / 100) * ADMINISTRATIVE_SHARE;
            return acc;
        }, {});
    }

    function calcularIncentivoPorVenta(ventasMesActual, diasVentas) {
        const minDias = toNumber(document.getElementById('ni_min_dias')?.value || 1);
        const tipoPago = document.getElementById('ni_tipo_pago')?.value || cachedTipoPago || 'tramos_60';
        const rangosPago = payoutRangesByType[tipoPago] || [];
        const ventas = Math.max(0, toNumber(ventasMesActual));

        if (toNumber(diasVentas) < minDias) {
            return 0;
        }

        for (const rango of rangosPago) {
            const desde = toNumber(rango.desde);
            const hasta = rango.hasta === null || rango.hasta === undefined ? null : toNumber(rango.hasta);
            if (ventas >= desde && (hasta === null || ventas <= hasta)) {
                return rango.tipo === 'porcentaje'
                    ? ventas * (toNumber(rango.pago) / 100)
                    : toNumber(rango.pago);
            }
        }

        const ultimoRango = rangosPago[rangosPago.length - 1];
        if (ultimoRango && ventas >= toNumber(ultimoRango.desde)) {
            return ultimoRango.tipo === 'porcentaje'
                ? ventas * (toNumber(ultimoRango.pago) / 100)
                : toNumber(ultimoRango.pago);
        }

        return 0;
    }

    function aplicarDescuentoRecargasPaqueticos(row) {
        const cedula = String(row?.cedula ?? '').replace(/\D+/g, '');
        const descuento = toNumber(recargasPaqueticosDescuentos.get(cedula) || 0);

        if (!descuento) {
            return row;
        }

        const ventasAjustadas = Math.max(0, toNumber(row?.ventas_mes_actual) - descuento);
        const incentivoAjustado = calcularIncentivoPorVenta(ventasAjustadas, row?.dias_ventas_mes_actual);

        return {
            ...row,
            ventas_mes_actual_original: row?.ventas_mes_actual_original ?? row?.ventas_mes_actual,
            nuevo_incentivo_original: row?.nuevo_incentivo_original ?? row?.nuevo_incentivo,
            descuento_recargas_paqueticos: descuento,
            ventas_mes_actual: ventasAjustadas,
            nuevo_incentivo: incentivoAjustado,
        };
    }

    function normalizeEmpresaValue(value) {
        const text = String(value ?? '').trim().toLowerCase();
        return text === '' ? 'sin empresa' : text;
    }

    function normalizeEmpresaLabel(value) {
        const text = String(value ?? '').trim();
        return text === '' ? 'Sin empresa' : text;
    }

    function esAgenciaPorAsignarEmpresa(row) {
        return normalizeEmpresaValue(row?.empresa) === 'agencias por asignar empresa';
    }

    function normalizeAdministrativeEmpresaKey(value) {
        const text = String(value ?? '').trim().toLowerCase();
        if (text === '') return 'sin empresa';
        if (text.includes('joselito') || text.includes('cjoselito')) return 'consorcio joselito';
        if (text.includes('negosur')) return 'negosur';
        return text;
    }

    function normalizeAdministrativeEmpresaLabel(value) {
        const key = normalizeAdministrativeEmpresaKey(value);
        if (key === 'consorcio joselito') return 'Consorcio Joselito';
        if (key === 'negosur') return 'Negosur';
        if (key === 'sin empresa') return 'Sin empresa';
        return String(value ?? '').trim();
    }

    function getAdministrativeEmpresaFilterKey() {
        const selected = document.getElementById('ni_filtro_empresa')?.value || 'todos';
        if (selected === 'todos') return 'todos';
        if (selected.includes('joselito')) return 'consorcio joselito';
        if (selected.includes('negosur')) return 'negosur';
        if (selected === 'sin empresa') return 'sin empresa';
        return selected;
    }

    function populateEmpresaFilterOptions(rows) {
        const select = document.getElementById('ni_filtro_empresa');
        if (!select) return;

        const currentValue = select.value || 'todos';
        const optionsByKey = new Map();

        (Array.isArray(rows) ? rows : []).forEach((row) => {
            const label = normalizeEmpresaLabel(row?.empresa);
            const key = normalizeEmpresaValue(label);
            if (!optionsByKey.has(key)) {
                optionsByKey.set(key, label);
            }
        });

        const options = Array.from(optionsByKey.entries())
            .sort((a, b) => a[1].localeCompare(b[1], 'es', { sensitivity: 'base' }));

        select.innerHTML = '<option value="todos">Todas</option>' + options
            .map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`)
            .join('');

        select.value = optionsByKey.has(currentValue) ? currentValue : 'todos';
    }

    function updatePuestoPctSummaryCard() {
        const target = document.getElementById('ni_pct_puesto_resumen');
        if (!target) {
            return;
        }

        const montoBase = toNumber(currentAdministrativePoolBase);
        const montoG1 = montoBase * (toNumber(puestoPctConfig.g1) / 100);
        const montoG2 = montoBase * (toNumber(puestoPctConfig.g2) / 100);
        const montoG45 = montoBase * (toNumber(puestoPctConfig.g45) / 100);

        target.innerHTML = `
            <tr>
                <td>1 Gtes. y Encarg.</td>
                <td class="text-end">${toNumber(puestoPctConfig.g1).toFixed(2)}%</td>
                <td class="text-end">${formatMoney(montoG1)}</td>
            </tr>
            <tr>
                <td>2 Monitoreo</td>
                <td class="text-end">${toNumber(puestoPctConfig.g2).toFixed(2)}%</td>
                <td class="text-end">${formatMoney(montoG2)}</td>
            </tr>
            <tr>
                <td>4 Operadores + 5 Servs. Tecnicos</td>
                <td class="text-end">${toNumber(puestoPctConfig.g45).toFixed(2)}%</td>
                <td class="text-end">${formatMoney(montoG45)}</td>
            </tr>
        `;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderNombreEmpleado(value) {
        const nombre = String(value || '').trim() || 'Actualizar en maestro de empleados';

        if (nombre === 'Actualizar en maestro de empleados') {
            return `<span class="empleado-maestro-pendiente">${escapeHtml(nombre)}</span>`;
        }

        return escapeHtml(nombre);
    }

    function esNombrePendiente(row) {
        return String(row?.nombre || '').trim() === 'Actualizar en maestro de empleados';
    }

    function formatDateDisplay(value) {
        const text = String(value || '').trim();
        if (!text) return '-';
        const parts = text.split('-');
        return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : text;
    }

    function renderAgenciaFormato(row) {
        const nombreAgencia = String(row?.ultima_agencia_nombre || 'SIN AGENCIA').trim() || 'SIN AGENCIA';
        const terminal = String(row?.ultima_terminal || '-').trim() || '-';

        return `
            <div class="agencia-formato-comparativa">
                <div class="agencia-nombre">${escapeHtml(nombreAgencia)}</div>
                <small class="agencia-terminal">Terminal: ${escapeHtml(terminal)}</small>
            </div>
        `;
    }

    function toCsvValue(value) {
        const text = String(value ?? '');
        if (/[",\n]/.test(text)) {
            return `"${text.replace(/"/g, '""')}"`;
        }
        return text;
    }

    function exportRowsToCsv(rows, headers, mapper, filename) {
        if (!Array.isArray(rows) || !rows.length) {
            Swal.fire({ title: 'Sin datos', text: 'No hay registros para exportar.', icon: 'warning' });
            return;
        }

        const csvRows = [
            headers.map(toCsvValue).join(','),
            ...rows.map(row => mapper(row).map(toCsvValue).join(','))
        ];
        const blob = new Blob(['\ufeff' + csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function exportRowsToExcelCsv(filename, headers, rows) {
        const csvLines = [];
        csvLines.push(headers.map(toCsvValue).join(','));
        rows.forEach((row) => {
            csvLines.push(row.map(toCsvValue).join(','));
        });

        const csvContent = '\uFEFF' + csvLines.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function toTxtPagoValue(value) {
        return String(value ?? '')
            .replace(/\t/g, ' ')
            .replace(/\r?\n/g, ' ')
            .trim();
    }

    function getComentarioPagoIncentivo() {
        const fechaFin = document.getElementById('ni_fecha_fin')?.value || '';
        const fecha = fechaFin ? new Date(`${fechaFin}T00:00:00`) : new Date();
        const mes = fecha.toLocaleDateString('es-DO', { month: 'long', year: 'numeric' });

        return `pago de incentivo ${mes}`;
    }

    function getFechaFinPagoIncentivo() {
        return document.getElementById('ni_fecha_fin')?.value || new Date().toISOString().slice(0, 10);
    }

    function getPagoIncentivoExportData() {
        const rows = currentFilteredRows
            .map((row) => ({
                ...row,
                __importe: toNumber(row?.nuevo_incentivo),
                __idEmpleado: String(row?.empleadoid ?? '').trim(),
            }))
            .filter((row) => row.__importe > 0);

        if (!rows.length) {
            Swal.fire({ title: 'Sin datos', text: 'No hay incentivos con importe mayor a cero para generar el archivo de pago.', icon: 'warning' });
            return null;
        }

        const headers = ['IdEmpleado', 'IdNovedad', 'Importe', 'Aplicar A', 'IdSucursal', 'Id Doc', 'Comentario', 'IdViejo'];
        const comentario = getComentarioPagoIncentivo();

        return {
            headers,
            rows: rows.map((row) => [
                row.__idEmpleado,
                'INC',
                row.__importe.toFixed(2),
                '',
                '',
                '',
                comentario,
                '',
            ].map(toTxtPagoValue)),
            fechaFin: getFechaFinPagoIncentivo(),
        };
    }

    function downloadBlob(filename, blob) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function generarTxtPagoIncentivo() {
        const data = getPagoIncentivoExportData();
        if (!data) {
            return;
        }

        const lines = [
            data.headers.join(','),
            ...data.rows.map((row) => row.join(',')),
        ];
        const blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/plain;charset=utf-8;' });
        downloadBlob(`pago_incentivo_${data.fechaFin}.txt`, blob);
    }

    function escapeXml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&apos;');
    }

    function excelColumnName(index) {
        let name = '';
        let number = index + 1;
        while (number > 0) {
            const remainder = (number - 1) % 26;
            name = String.fromCharCode(65 + remainder) + name;
            number = Math.floor((number - 1) / 26);
        }
        return name;
    }

    function buildXlsxBlob(headers, rows) {
        const zip = new JSZip();
        const allRows = [headers, ...rows];
        const sheetRows = allRows.map((row, rowIndex) => {
            const rowNumber = rowIndex + 1;
            const cells = row.map((value, columnIndex) => {
                const cellRef = `${excelColumnName(columnIndex)}${rowNumber}`;
                return `<c r="${cellRef}" t="inlineStr"><is><t>${escapeXml(value)}</t></is></c>`;
            }).join('');

            return `<row r="${rowNumber}">${cells}</row>`;
        }).join('');

        zip.file('[Content_Types].xml', `${XML_DECL}
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>`);
        zip.folder('_rels').file('.rels', `${XML_DECL}
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>`);
        zip.folder('xl').file('workbook.xml', `${XML_DECL}
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Pago incentivo" sheetId="1" r:id="rId1"/></sheets></workbook>`);
        zip.folder('xl').folder('_rels').file('workbook.xml.rels', `${XML_DECL}
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>`);
        zip.folder('xl').folder('worksheets').file('sheet1.xml', `${XML_DECL}
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>${sheetRows}</sheetData></worksheet>`);

        return zip.generateAsync({ type: 'blob', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    }

    function buildExcelHtmlBlob(headers, rows) {
        const tableRows = [headers, ...rows].map((row) => (
            `<tr>${row.map((value) => `<td>${escapeHtml(value)}</td>`).join('')}</tr>`
        )).join('');
        const html = `
            <html>
                <head>
                    <meta charset="UTF-8">
                </head>
                <body>
                    <table>${tableRows}</table>
                </body>
            </html>
        `;

        return new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    }

    function generarXlsxPagoIncentivo() {
        const data = getPagoIncentivoExportData();
        if (!data) {
            return;
        }

        if (typeof JSZip === 'undefined') {
            const blob = buildExcelHtmlBlob(data.headers, data.rows);
            downloadBlob(`pago_incentivo_${data.fechaFin}.xls`, blob);
            return;
        }

        buildXlsxBlob(data.headers, data.rows).then((blob) => {
            downloadBlob(`pago_incentivo_${data.fechaFin}.xlsx`, blob);
        });
    }

    function exportAdministrativosExcel() {
        const rows = getAdministrativeDisplayRows().map((row) => [
            row.grupo,
            row.nombre,
            row.empresa,
            formatAdministrativePct(row.pct),
            formatMoney(getAdministrativeDisplayAmount(row)),
        ]);

        exportRowsToExcelCsv(
            'administrativos_v5_validacion.csv',
            ['Grupo', 'Nombre', 'Empresa', '% Total', 'Monto'],
            rows
        );
    }

    function exportCoordinadoresExcel() {
        const rows = coordinatorRows.map((row) => [
            row.nombre,
            toNumber(row.agencias),
            toNumber(row.agencias_validas),
            formatMoney(row.monto_usuarios),
            formatCoordinatorPct(row),
            formatMoney(getCoordinatorAmount(row)),
        ]);

        exportRowsToExcelCsv(
            'coordinadores_v5_validacion.csv',
            ['Nombre', 'Agencias', 'Validas', 'Monto', '% Total', 'Monto Coordinador'],
            rows
        );
    }

    function normalizeAdministrativeGroup(value) {
        const group = String(value ?? '').trim();
        if (group.includes('Servs. Tecnicos') || group.includes('Servs Tecnicos')) {
            return '5. Servs. Tecnicos';
        }

        return group;
    }

    function updateAdministrativeFilterButtons() {
        const active = administrativeGroupFilter;
        const buttonMap = [
            { id: 'btnAdminFiltroTodos', value: 'todos' },
            { id: 'btnAdminFiltroG1', value: '1. Gtes. Y Encarg.' },
            { id: 'btnAdminFiltroG2', value: '2. Monitoreo' },
            { id: 'btnAdminFiltroG45', value: '4_5' },
        ];

        buttonMap.forEach((item) => {
            const button = document.getElementById(item.id);
            if (!button) return;

            const isActive = active === item.value;
            button.classList.toggle('btn-primary', isActive);
            button.classList.toggle('btn-outline-primary', !isActive);
        });
    }

    function renderPuestoPctInputs() {
        const g1Input = document.getElementById('puesto_pct_g1');
        const g2Input = document.getElementById('puesto_pct_g2');
        const g45Input = document.getElementById('puesto_pct_g45');

        if (g1Input) g1Input.value = puestoPctConfig.g1;
        if (g2Input) g2Input.value = puestoPctConfig.g2;
        if (g45Input) g45Input.value = puestoPctConfig.g45;
    }

    function readPuestoPctInputs() {
        puestoPctConfig.g1 = Math.max(0, parseFloat(document.getElementById('puesto_pct_g1')?.value || 0) || 0);
        puestoPctConfig.g2 = Math.max(0, parseFloat(document.getElementById('puesto_pct_g2')?.value || 0) || 0);
        puestoPctConfig.g45 = Math.max(0, parseFloat(document.getElementById('puesto_pct_g45')?.value || 0) || 0);
    }

    function getPuestoPctByCategoryKey(categoryKey) {
        if (categoryKey === 'g1') return toNumber(puestoPctConfig.g1);
        if (categoryKey === 'g2') return toNumber(puestoPctConfig.g2);
        if (categoryKey === 'g45') return toNumber(puestoPctConfig.g45);
        return 0;
    }

    function getAdministrativePoolForEmpresa(empresaValue) {
        const key = normalizeAdministrativeEmpresaKey(empresaValue);
        return toNumber(currentAdministrativePoolByEmpresa[key] || 0);
    }

    function getPuestoCategoryBudget(categoryKey, empresaValue = null) {
        const base = empresaValue === null
            ? toNumber(currentAdministrativePoolBase)
            : getAdministrativePoolForEmpresa(empresaValue);

        return base * (getPuestoPctByCategoryKey(categoryKey) / 100);
    }

    function getAdministrativeCategoryKeyByGroup(groupValue) {
        const group = normalizeAdministrativeGroup(groupValue);
        if (group === '1. Gtes. Y Encarg.') return 'g1';
        if (group === '2. Monitoreo') return 'g2';
        if (group === '4. Operadores' || group === '5. Servs. Tecnicos') return 'g45';
        return null;
    }

    function getAdministrativeCategoryPctTotal(categoryKey, empresaValue = null) {
        const empresaKey = empresaValue === null ? null : normalizeAdministrativeEmpresaKey(empresaValue);
        const allRows = [
            ...administrativeRows.map((row) => ({
                ...row,
                grupo: normalizeAdministrativeGroup(row.grupo),
                __empresaKey: normalizeAdministrativeEmpresaKey(row.empresa),
            })),
            ...operatorRows.map((row) => ({
                ...row,
                grupo: normalizeAdministrativeGroup(row.grupo),
                __empresaKey: normalizeAdministrativeEmpresaKey(row.empresa),
            })),
        ];

        return allRows
            .filter((row) => getAdministrativeCategoryKeyByGroup(row.grupo) === categoryKey)
            .filter((row) => empresaKey === null || row.__empresaKey === empresaKey)
            .reduce((sum, row) => sum + toNumber(row.pct), 0);
    }

    function getAdministrativeAmountByRow(row) {
        const categoryKey = getAdministrativeCategoryKeyByGroup(row?.grupo);
        if (!categoryKey) {
            return 0;
        }

        const empresa = normalizeAdministrativeEmpresaLabel(row?.empresa);
        const categoryPctTotal = getAdministrativeCategoryPctTotal(categoryKey, empresa);
        if (categoryPctTotal <= 0) {
            return 0;
        }

        const categoryBudget = getPuestoCategoryBudget(categoryKey, empresa);
        return categoryBudget * (toNumber(row.pct) / categoryPctTotal);
    }

    function getAdministrativeAmount(row) {
        return getAdministrativeAmountByRow(row);
    }

    function getAdministrativeDisplayRows() {
        const adminItems = administrativeRows.map((row, idx) => ({
            ...row,
            grupo: normalizeAdministrativeGroup(row.grupo),
            empresa: normalizeAdministrativeEmpresaLabel(row.empresa),
            __empresaKey: normalizeAdministrativeEmpresaKey(row.empresa),
            __tipo: 'admin',
            __idx: idx,
        }));

        const operatorItems = operatorRows.map((row, idx) => ({
            ...row,
            grupo: normalizeAdministrativeGroup(row.grupo),
            empresa: normalizeAdministrativeEmpresaLabel(row.empresa),
            __empresaKey: normalizeAdministrativeEmpresaKey(row.empresa),
            __tipo: 'operador',
            __idx: idx,
        }));

        const allRows = [...adminItems, ...operatorItems];
        const empresaFilterKey = getAdministrativeEmpresaFilterKey();
        let filteredRows = allRows;

        if (administrativeGroupFilter === '4_5') {
            filteredRows = filteredRows.filter((row) => row.grupo === '4. Operadores' || row.grupo === '5. Servs. Tecnicos');
        } else if (administrativeGroupFilter !== 'todos') {
            filteredRows = filteredRows.filter((row) => row.grupo === administrativeGroupFilter);
        }

        if (empresaFilterKey !== 'todos') {
            filteredRows = filteredRows.filter((row) => row.__empresaKey === empresaFilterKey);
        }

        return filteredRows;
    }

    function getAdministrativeDisplayAmount(row) {
        if (row.__tipo === 'operador') {
            return getAdministrativeAmountByRow(operatorRows[row.__idx] || row);
        }

        return getAdministrativeAmountByRow(administrativeRows[row.__idx] || row);
    }

    function getCoordinatorPctTotal() {
        return getCoordinatorMontoUsuariosTotal();
    }

    function getCoordinatorAmount(row) {
        const pctTotal = getCoordinatorPctTotal();
        if (pctTotal <= 0) {
            return 0;
        }

        return currentCoordinatorBase * (toNumber(row.monto_usuarios) / pctTotal);
    }

    function formatCoordinatorPct(row) {
        const pctTotal = getCoordinatorPctTotal();
        if (pctTotal <= 0) {
            return '0.00';
        }

        return ((toNumber(row.monto_usuarios) / pctTotal) * 100).toFixed(2);
    }

    function getCoordinatorMontoUsuariosTotal() {
        return coordinatorRows.reduce((sum, row) => sum + toNumber(row.monto_usuarios), 0);
    }

    function getCoordinatorDetailUsers(row) {
        if (!row || row.id === null || row.id === undefined) {
            return [];
        }

        return coordinatorUserDetailsByCoordinator[String(row.id)] || [];
    }

    function formatAdministrativePct(value) {
        return (toNumber(value) * 100).toFixed(2);
    }

    function getAdministrativeFilteredBudget() {
        if (administrativeGroupFilter === '1. Gtes. Y Encarg.') return getPuestoCategoryBudget('g1');
        if (administrativeGroupFilter === '2. Monitoreo') return getPuestoCategoryBudget('g2');
        if (administrativeGroupFilter === '4_5') return getPuestoCategoryBudget('g45');

        return getPuestoCategoryBudget('g1') + getPuestoCategoryBudget('g2') + getPuestoCategoryBudget('g45');
    }

    function recalculateAdministrativeOperatorBases() {
        currentAdministrativeBase = getPuestoCategoryBudget('g1') + getPuestoCategoryBudget('g2');
        currentOperatorBase = getPuestoCategoryBudget('g45');
    }

    function getOperatorAmount(row) {
        return getAdministrativeAmountByRow(row);
    }

    function updateAdministrativeSummary() {
        const visibleRows = getAdministrativeDisplayRows();
        const totalDistribuido = visibleRows.reduce((sum, row) => sum + getAdministrativeDisplayAmount(row), 0);
        const montoG1 = visibleRows
            .filter(row => getAdministrativeCategoryKeyByGroup(row.grupo) === 'g1')
            .reduce((sum, row) => sum + getAdministrativeDisplayAmount(row), 0);
        const montoG2 = visibleRows
            .filter(row => getAdministrativeCategoryKeyByGroup(row.grupo) === 'g2')
            .reduce((sum, row) => sum + getAdministrativeDisplayAmount(row), 0);
        const montoG45 = visibleRows
            .filter(row => getAdministrativeCategoryKeyByGroup(row.grupo) === 'g45')
            .reduce((sum, row) => sum + getAdministrativeDisplayAmount(row), 0);
        const montoFiltro = totalDistribuido;

        document.getElementById('admin_base_total').textContent = formatMoney(currentAdministrativePoolBase);
        document.getElementById('admin_distribuido_total').textContent = formatMoney(montoFiltro);
        const tablaTotal = document.getElementById('admin_tabla_total');
        if (tablaTotal) {
            tablaTotal.textContent = formatMoney(totalDistribuido);
        }
        const catG1 = document.getElementById('admin_cat_g1');
        if (catG1) {
            catG1.textContent = `${toNumber(puestoPctConfig.g1).toFixed(2)}% | ${formatMoney(montoG1)}`;
        }
        const catG2 = document.getElementById('admin_cat_g2');
        if (catG2) {
            catG2.textContent = `${toNumber(puestoPctConfig.g2).toFixed(2)}% | ${formatMoney(montoG2)}`;
        }
        const catG45 = document.getElementById('admin_cat_g45');
        if (catG45) {
            catG45.textContent = `${toNumber(puestoPctConfig.g45).toFixed(2)}% | ${formatMoney(montoG45)}`;
        }
        const totalCol = document.getElementById('admin_col_total');
        if (totalCol) {
            totalCol.textContent = formatMoney(totalDistribuido);
        }
    }

    function updateCoordinatorSummary() {
        const totalDistribuido = coordinatorRows.reduce((sum, row) => sum + getCoordinatorAmount(row), 0);

        document.getElementById('coord_base_total').textContent = formatMoney(currentCoordinatorBase);
        document.getElementById('coord_distribuido_total').textContent = formatMoney(totalDistribuido);
    }

    function updateAdministrativeAmounts() {
        document.querySelectorAll('.admin-display-monto').forEach((cell) => {
            const idx = parseInt(cell.dataset.idx, 10);
            const tipo = cell.dataset.tipo;
            if (Number.isNaN(idx)) {
                return;
            }

            const amount = tipo === 'operador'
                ? getOperatorAmount(operatorRows[idx] || {})
                : getAdministrativeAmount(administrativeRows[idx] || {});

            cell.textContent = formatMoney(amount);
        });
        updateAdministrativeSummary();
    }

    function updateCoordinatorAmounts() {
        coordinatorRows.forEach((row, idx) => {
            const cell = document.querySelector(`.coord-monto[data-idx="${idx}"]`);
            if (cell) {
                cell.textContent = formatMoney(getCoordinatorAmount(row));
            }

            const pctInput = document.querySelector(`.coord-pct-input[data-idx="${idx}"]`);
            if (pctInput) {
                pctInput.value = formatCoordinatorPct(row);
            }
        });
        updateCoordinatorSummary();
    }

    function updateOperatorAmounts() {
        updateAdministrativeAmounts();
    }

    function updateAdministrativeAndOperatorAmounts() {
        recalculateAdministrativeOperatorBases();
        updateAdministrativeAmounts();
    }

    function getGrupoNuevoAdministrativo() {
        if (administrativeGroupFilter === '1. Gtes. Y Encarg.') return '1. Gtes. Y Encarg.';
        if (administrativeGroupFilter === '2. Monitoreo') return '2. Monitoreo';
        if (administrativeGroupFilter === '4_5') return '4. Operadores';

        return '4. Operadores';
    }

    function getEmpresaNuevaAdministrativa() {
        const selected = document.getElementById('ni_filtro_empresa')?.value || 'todos';
        if (selected === 'todos') {
            return '';
        }

        return normalizeAdministrativeEmpresaLabel(selected);
    }

    function buildAdminSelectOptions(options, selectedValue) {
        const selected = String(selectedValue || '').trim();
        const values = [...options];
        if (selected && !values.includes(selected)) {
            values.unshift(selected);
        }

        return values
            .map(value => `<option value="${escapeHtml(value)}" ${value === selected ? 'selected' : ''}>${escapeHtml(value)}</option>`)
            .join('');
    }

    function rebalanceAdministrativeRows() {
        const split = splitAdministrativeRowsByGroup([...administrativeRows, ...operatorRows]);
        administrativeRows = split.administrativos;
        operatorRows = split.operadores;
    }

    function scrollAdministrativeTableToBottom() {
        const tableWrapper = document.querySelector('#tbodyAdministrativos')?.closest('.table-responsive');
        if (tableWrapper) {
            tableWrapper.scrollTop = tableWrapper.scrollHeight;
        }
    }

    function agregarFilaAdministrativa() {
        const grupo = getGrupoNuevoAdministrativo();
        const row = {
            id: null,
            grupo,
            nombre: '',
            empresa: getEmpresaNuevaAdministrativa(),
            pct: 0,
        };

        if (normalizeAdministrativeGroup(grupo) === '4. Operadores') {
            operatorRows.push(row);
        } else {
            administrativeRows.push(row);
        }

        renderAdministrativeCategoryTable();
        updateAdministrativeAndOperatorAmounts();
        scrollAdministrativeTableToBottom();
    }

    function eliminarFilaAdministrativa(tipo, idx) {
        const index = parseInt(idx, 10);
        if (Number.isNaN(index)) {
            return;
        }

        if (tipo === 'operador') {
            operatorRows.splice(index, 1);
        } else {
            administrativeRows.splice(index, 1);
        }

        renderAdministrativeCategoryTable();
        updateAdministrativeAndOperatorAmounts();
    }

    function getAdministrativeRowsForSave() {
        return [
            ...administrativeRows,
            ...operatorRows,
        ].map((row) => ({
            id: row?.id || null,
            grupo: normalizeAdministrativeGroup(row?.grupo),
            nombre: String(row?.nombre || '').trim(),
            empresa: normalizeAdministrativeEmpresaLabel(row?.empresa),
            pct_total: toNumber(row?.pct) * 100,
        }));
    }

    function validateAdministrativeRowsForSave(rows) {
        if (!rows.length) {
            return 'Debe existir al menos una fila administrativa.';
        }

        const invalidIndex = rows.findIndex(row => !row.grupo || !row.nombre || !row.empresa);
        if (invalidIndex >= 0) {
            return `Completa grupo, nombre y empresa en la fila ${invalidIndex + 1} antes de guardar.`;
        }

        const keys = new Set();
        for (const row of rows) {
            const key = `${row.grupo}|${row.nombre}|${row.empresa}`.toLowerCase();
            if (keys.has(key)) {
                return 'Hay filas duplicadas por grupo, nombre y empresa. Ajustalas antes de guardar.';
            }
            keys.add(key);
        }

        return '';
    }

    function refreshAdministrativeRowsFromServer(rows) {
        administrativeRowsConfig = Array.isArray(rows) ? rows : [];
        const split = splitAdministrativeRowsByGroup(normalizeAdministrativeRowsFromPayload(rows));
        administrativeRows = split.administrativos;
        operatorRows = split.operadores;
        renderAdministrativeCategoryTable();
        updateAdministrativeAndOperatorAmounts();
    }

    function guardarAdministrativosPlantilla() {
        const rows = getAdministrativeRowsForSave();
        const error = validateAdministrativeRowsForSave(rows);
        if (error) {
            Swal.fire({ title: 'Validacion', text: error, icon: 'warning' });
            return;
        }

        Swal.fire({
            title: 'Guardar cambios',
            text: 'Esto actualizara permanentemente la tabla de administrativos del incentivo.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            Swal.fire({
                title: 'Guardando plantilla...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading(),
            });

            fetch('/incentivos/reporte-nuevo-incentivo-v5/administrativos/sincronizar', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ rows }),
            })
                .then(response => parseResponseAsJson(response, 'Error guardando plantilla administrativa'))
                .then(resp => {
                    refreshAdministrativeRowsFromServer(resp.data || []);
                    Swal.fire({
                        title: 'Guardado',
                        text: resp.message || 'Plantilla administrativa guardada correctamente.',
                        icon: 'success',
                    });
                })
                .catch(error => {
                    Swal.fire({ title: 'Error', text: error?.message || String(error), icon: 'warning' });
                });
        });
    }

    function renderAdministrativeCategoryTable() {
        const tbody = document.getElementById('tbodyAdministrativos');
        tbody.innerHTML = '';
        updateAdministrativeFilterButtons();

        const rows = getAdministrativeDisplayRows();

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay datos para este filtro.</td></tr>';
            updateAdministrativeSummary();
            return;
        }

        rows.forEach((row) => {
            const inputClass = row.__tipo === 'operador' ? 'op-input op-pct-input' : 'admin-input admin-pct-input';
            const rowClass = row.__tipo === 'operador' ? 'op-input' : 'admin-input';
            const amount = getAdministrativeDisplayAmount(row);

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select class="form-select form-select-sm ${rowClass} admin-grupo-select" data-field="grupo" data-idx="${row.__idx}">
                        ${buildAdminSelectOptions(ADMIN_GROUP_OPTIONS, row.grupo)}
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm ${rowClass}" data-field="nombre" data-idx="${row.__idx}" value="${escapeHtml(row.nombre)}"></td>
                <td>
                    <select class="form-select form-select-sm ${rowClass}" data-field="empresa" data-idx="${row.__idx}">
                        ${buildAdminSelectOptions(ADMIN_EMPRESA_OPTIONS, row.empresa)}
                    </select>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control ${inputClass}" data-field="pct" data-idx="${row.__idx}" min="0" step="0.01" value="${formatAdministrativePct(row.pct)}">
                        <span class="input-group-text">%</span>
                    </div>
                </td>
                <td class="text-end fw-semibold admin-display-monto" data-tipo="${row.__tipo}" data-idx="${row.__idx}">${formatMoney(amount)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-soft-danger btn-delete-admin-row" data-tipo="${row.__tipo}" data-idx="${row.__idx}">Eliminar</button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        updateAdministrativeSummary();
    }

    function renderCoordinatorTable() {
        const tbody = document.getElementById('tbodyCoordinadores');
        tbody.innerHTML = '';

        if (!coordinatorRows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No hay coordinadores registrados.</td></tr>';
            updateCoordinatorSummary();
            return;
        }

        coordinatorRows.forEach((row, idx) => {
            const detailUsers = getCoordinatorDetailUsers(row);
            const hasDetail = detailUsers.length > 0;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" class="form-control form-control-sm coord-input" data-field="nombre" data-idx="${idx}" value="${escapeHtml(row.nombre)}"></td>
                <td class="text-center fw-semibold">${toNumber(row.agencias)}</td>
                <td class="text-center fw-semibold text-success">${toNumber(row.agencias_validas)}</td>
                <td class="text-end fw-semibold">${formatMoney(row.monto_usuarios)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-ver-detalle-coord" data-idx="${idx}" ${hasDetail ? '' : 'disabled'}>Ver</button>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control coord-pct-input" data-idx="${idx}" min="0" step="0.01" value="${formatCoordinatorPct(row)}" readonly>
                        <span class="input-group-text">%</span>
                    </div>
                </td>
                <td class="text-end fw-semibold coord-monto" data-idx="${idx}">${formatMoney(getCoordinatorAmount(row))}</td>
            `;
            tbody.appendChild(tr);
        });

        updateCoordinatorSummary();
    }

    function renderCoordinatorDetailTable(row) {
        const tbody = document.getElementById('tbodyCoordinadorDetalle');
        const title = document.getElementById('coordinatorDetailTitle');
        const users = getCoordinatorDetailUsers(row);
        const coordinatorName = row?.nombre || 'Coordinador';

        title.textContent = `Detalle de Usuarios - ${coordinatorName}`;
        tbody.innerHTML = '';

        if (!users.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No hay usuarios para este coordinador.</td></tr>';
            return;
        }

        users.forEach((user) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-semibold">${escapeHtml(user.cedula)}</td>
                <td>${escapeHtml(user.usuario)}</td>
                <td class="text-end fw-semibold">${formatMoney(user.incentivo)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function updateCoordinatorValidAgencies(meta) {
        const validAgenciesByCoordinator = meta?.coordinador_agencias_validas || {};
        const userAmountsByCoordinator = meta?.coordinador_monto_usuarios || {};
        coordinatorUserDetailsByCoordinator = meta?.coordinador_detalle_usuarios || {};

        coordinatorRows = coordinatorRows.map((row) => ({
            ...row,
            agencias_validas: toNumber(validAgenciesByCoordinator[String(row.id)] || 0),
            monto_usuarios: toNumber(userAmountsByCoordinator[String(row.id)] || 0),
        }));
    }

    function renderRangesTable() {
        const tbody = document.getElementById('tbodyTramosPago');
        tbody.innerHTML = '';

        const tipoPago = document.getElementById('ni_tipo_pago').value;
        const tipoPagoLabel = document.getElementById('ni_tipo_pago').selectedOptions[0].textContent;
        document.getElementById('modalConfigPctTitle').textContent = `Configurar ${tipoPagoLabel}`;

        payoutRangesByType[tipoPago].forEach((row, idx) => {
            const hastaValue = row.hasta === null || row.hasta === undefined ? '' : row.hasta;
            const label = row.tipo === 'porcentaje' ? '% de ventas' : 'Pago fijo';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="number" class="form-control tramo-desde" data-idx="${idx}" min="0" step="1" value="${row.desde}"></td>
                <td><input type="number" class="form-control tramo-hasta" data-idx="${idx}" min="0" step="1" value="${hastaValue}" placeholder="Sin tope"></td>
                <td>
                    <div class="input-group">
                        <input type="number" class="form-control tramo-pago" data-idx="${idx}" min="0" step="0.01" value="${row.pago}">
                        <span class="input-group-text">${label}</span>
                    </div>
                    <input type="hidden" class="tramo-tipo" data-idx="${idx}" value="${row.tipo}">
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function readRangesFromTable() {
        const desdeInputs = document.querySelectorAll('.tramo-desde');
        const hastaInputs = document.querySelectorAll('.tramo-hasta');
        const pagoInputs = document.querySelectorAll('.tramo-pago');

        const ranges = [];
        for (let i = 0; i < desdeInputs.length; i++) {
            const desde = parseFloat(desdeInputs[i].value || 0);
            const hasta = hastaInputs[i].value === '' ? null : parseFloat(hastaInputs[i].value || 0);
            const pago = parseFloat(pagoInputs[i].value || 0);
            const tipo = document.querySelector(`.tramo-tipo[data-idx="${i}"]`)?.value || 'fijo';

            if (desde < 0 || (hasta !== null && hasta < 0) || pago < 0) {
                throw new Error(`Hay valores negativos en la fila ${i + 1}.`);
            }
            if (hasta !== null && desde > hasta) {
                throw new Error(`El valor Desde no puede ser mayor que Hasta en la fila ${i + 1}.`);
            }

            ranges.push({ desde, hasta, pago, tipo });
        }

        return ranges.sort((a, b) => a.desde - b.desde);
    }

    function updateCardsFromData(data) {
        const totalCumplen = data.filter(item => evaluateMetaMinima(item).cumplio).length;
        const totalNoCumplen = data.length - totalCumplen;
        const totalPorActualizar = data.filter(item => esNombrePendiente(item)).length;
        const totalAgenciasSinEmpresa = getAgenciasSinEmpresaRows(data).length;
        const totalVendido = data.reduce((sum, item) => sum + toNumber(item.ventas_mes_actual), 0);
        const totalIncentivo = data.reduce((sum, item) => sum + toNumber(item.nuevo_incentivo), 0);
        const adminValor = totalIncentivo * (adminPctBruto / 100);
        const adminDistribucion = adminValor * ADMINISTRATIVE_SHARE;
        const coordinadorDistribucion = adminValor * COORDINATOR_SHARE;
        const totalConAdmin = totalIncentivo + adminValor;
        currentAdministrativePoolByEmpresa = buildAdministrativePoolByEmpresa(data);
        currentDistributionBase = adminValor;
        currentAdministrativePoolBase = adminDistribucion;
        currentCoordinatorBase = coordinadorDistribucion;

        document.getElementById('ni_count_cumplen').textContent = totalCumplen;
        document.getElementById('ni_count_no_cumplen').textContent = totalNoCumplen;
        document.getElementById('ni_count_por_actualizar').textContent = totalPorActualizar;
        document.getElementById('ni_count_agencias_sin_empresa').textContent = totalAgenciasSinEmpresa;
        document.getElementById('ni_total_vendido').textContent = totalVendido.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('ni_total_incentivo').textContent = totalIncentivo.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('ni_admin_resumen').textContent =
            '';
        document.getElementById('ni_admin_resumen').innerHTML =
            `<div>Porcentaje (${formatPercentDisplay(adminPctBruto)}%): ${formatMoney(adminValor)}</div>
            <div>Administrativo: ${formatMoney(adminDistribucion)}</div>
            <div>Coordinador: ${formatMoney(coordinadorDistribucion)}</div>`;
        document.getElementById('ni_total_con_admin').textContent =
            `Total a Pagar Final: ${totalConAdmin.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        updateAdministrativeAndOperatorAmounts();
        updateCoordinatorAmounts();
        updatePuestoPctSummaryCard();
    }

    function renderTableFromData(data) {
        if ($.fn.DataTable.isDataTable('#tableNuevoIncentivo')) {
            $('#tableNuevoIncentivo').DataTable().destroy();
        }

        const tableBody = document.querySelector('#tableNuevoIncentivo tbody');
        tableBody.innerHTML = '';

        data.forEach(item => {
            const meta = evaluateMetaMinima(item);
            const cumpleBadge = meta.cumplio
                ? '<span class="badge bg-success">CUMPLIO</span>'
                : `<span class="badge bg-danger">NO CUMPLE | Faltan ${formatMoney(meta.faltante)} (${meta.faltantePct.toFixed(2)}%)</span>`;

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.cedula}</td>
                <td>${escapeHtml(item.empleadoid || '')}</td>
                <td class="cell-nombre">${renderNombreEmpleado(item.nombre)}</td>
                <td>${escapeHtml(normalizeEmpresaLabel(item.empresa))}</td>
                <td class="text-end">${formatMoney(item.ventas_ultimo_mes)}</td>
                <td class="text-end">${formatMoney(item.ventas_mes_actual)}</td>
                <td class="text-center">${item.dias_ventas_mes_actual ?? 0}</td>
                <td>${cumpleBadge}</td>
                <td class="text-end">${formatMoney(item.nuevo_incentivo)}</td>
            `;
            tableBody.appendChild(row);
        });

        $('#tableNuevoIncentivo').DataTable({
            responsive: true,
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            order: [[4, 'desc']],
            autoWidth: true,
            columnDefs: [
                { targets: 0, width: '7rem' },
                { targets: 1, width: '7rem' },
                { targets: 2, width: '13rem' },
                { targets: 3, width: '8rem' },
                { targets: [4, 5, 8], width: '8.4rem', className: 'text-end' },
                { targets: 6, width: '4.4rem', className: 'text-center' },
                { targets: 7, width: '9rem' },
            ],
            pageLength: 10000,
            scrollY: '500px',
            scrollX: true,
            scrollCollapse: true,
            language: {
                lengthMenu: 'Mostrar _MENU_ registros por pagina',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'No hay registros disponibles',
                infoFiltered: '(filtrado de _MAX_ registros totales)',
                search: 'Buscar:',
                paginate: {
                    first: 'Primero',
                    last: 'Ultimo',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        });
    }

    function renderFaltantesIncentivoTable(rows) {
        const data = Array.isArray(rows) ? rows : [];

        if (tableFaltantesIncentivo) {
            tableFaltantesIncentivo.destroy();
            $('#tableFaltantesIncentivo tbody').empty();
        }

        tableFaltantesIncentivo = $('#tableFaltantesIncentivo').DataTable({
            data: data,
            columns: [
                { data: 'cedula' },
                {
                    data: 'nombre',
                    render: function(data, type) {
                        return type === 'display' ? renderNombreEmpleado(data) : data;
                    }
                },
                {
                    data: 'cantidad_faltantes',
                    className: 'text-center',
                    render: function(data, type) {
                        const value = Number(data || 0);
                        return type === 'display' ? value.toLocaleString('en-US') : value;
                    }
                },
                {
                    data: 'monto',
                    className: 'text-end',
                    render: function(data, type) {
                        const value = Number(data || 0);
                        return type === 'display' ? formatMoney(value) : value;
                    }
                }
            ],
            autoWidth: false,
            responsive: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50],
            order: [[3, 'desc']],
            language: {
                lengthMenu: 'Mostrar _MENU_ registros por pagina',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'No hay registros disponibles',
                infoFiltered: '(filtrado de _MAX_ registros totales)',
                search: 'Buscar:',
                zeroRecords: 'No hay faltantes para las cedulas consultadas',
                paginate: {
                    first: 'Primero',
                    last: 'Ultimo',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        });
    }

    function renderRecargasPaqueticosIncentivoTable(rows) {
        const data = Array.isArray(rows) ? rows : [];

        if (tableRecargasPaqueticosIncentivo) {
            tableRecargasPaqueticosIncentivo.destroy();
            $('#tableRecargasPaqueticosIncentivo tbody').empty();
        }

        tableRecargasPaqueticosIncentivo = $('#tableRecargasPaqueticosIncentivo').DataTable({
            data: data,
            columns: [
                { data: 'cedula' },
                {
                    data: 'nombre',
                    render: function(data, type) {
                        return type === 'display' ? renderNombreEmpleado(data) : data;
                    }
                },
                {
                    data: 'total_recargas',
                    className: 'text-end',
                    render: function(data, type) {
                        const value = Number(data || 0);
                        return type === 'display' ? formatMoney(value) : value;
                    }
                },
                {
                    data: 'total_paqueticos',
                    className: 'text-end',
                    render: function(data, type) {
                        const value = Number(data || 0);
                        return type === 'display' ? formatMoney(value) : value;
                    }
                },
                {
                    data: 'total_ventas',
                    className: 'text-end',
                    render: function(data, type) {
                        const value = Number(data || 0);
                        return type === 'display' ? formatMoney(value) : value;
                    }
                },
                {
                    data: 'cantidad_ventas',
                    className: 'text-center',
                    render: function(data, type) {
                        const value = Number(data || 0);
                        return type === 'display' ? value.toLocaleString('en-US') : value;
                    }
                }
            ],
            autoWidth: false,
            responsive: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50],
            order: [[4, 'desc']],
            language: {
                lengthMenu: 'Mostrar _MENU_ registros por pagina',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'No hay registros disponibles',
                infoFiltered: '(filtrado de _MAX_ registros totales)',
                search: 'Buscar:',
                zeroRecords: 'No hay ventas de recargas o paqueticos para las cedulas consultadas',
                paginate: {
                    first: 'Primero',
                    last: 'Ultimo',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        });
    }

    function renderUsuariosActualizarTable(rows) {
        const data = Array.isArray(rows) ? rows : [];

        if (tableUsuariosActualizar) {
            tableUsuariosActualizar.destroy();
            $('#tableUsuariosActualizar tbody').empty();
        }

        tableUsuariosActualizar = $('#tableUsuariosActualizar').DataTable({
            data: data,
            columns: [
                { data: 'cedula' },
                {
                    data: 'nombre',
                    render: function(data, type) {
                        return type === 'display' ? renderNombreEmpleado(data) : data;
                    }
                },
                {
                    data: 'empresa',
                    render: function(data, type) {
                        return type === 'display' ? escapeHtml(normalizeEmpresaLabel(data)) : data;
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        return type === 'display' ? renderAgenciaFormato(row) : `${row?.ultima_agencia_nombre || ''} ${row?.ultima_terminal || ''}`;
                    }
                },
                {
                    data: 'ultimo_dia_venta',
                    render: function(data, type) {
                        return type === 'display' ? formatDateDisplay(data) : data;
                    }
                }
            ],
            autoWidth: false,
            responsive: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50],
            order: [[3, 'desc']],
            language: {
                lengthMenu: 'Mostrar _MENU_ registros por pagina',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'No hay registros disponibles',
                infoFiltered: '(filtrado de _MAX_ registros totales)',
                search: 'Buscar:',
                zeroRecords: 'No hay cedulas pendientes por actualizar',
                paginate: {
                    first: 'Primero',
                    last: 'Ultimo',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        });
    }

    function abrirUsuariosActualizarModal() {
        const pendientes = currentFilteredRows.filter(item => esNombrePendiente(item));

        if (!pendientes.length) {
            Swal.fire({
                title: 'Sin pendientes',
                text: 'No hay cedulas por actualizar en el maestro de empleados.',
                icon: 'info'
            });
            return;
        }

        renderUsuariosActualizarTable(pendientes);
        const modal = new bootstrap.Modal(document.getElementById('modalUsuariosActualizar'));
        modal.show();
    }

    function getAgenciasSinEmpresaRows(sourceRows = currentFilteredRows) {
        const rows = Array.isArray(sourceRows) ? sourceRows : [];
        const grouped = new Map();

        rows
            .filter(row => esAgenciaPorAsignarEmpresa(row))
            .forEach((row) => {
                const terminal = String(row?.ultima_terminal || 'SIN TERMINAL').trim() || 'SIN TERMINAL';
                const current = grouped.get(terminal) || {
                    terminal,
                    nombre_agencia: String(row?.ultima_agencia_nombre || 'SIN AGENCIA').trim() || 'SIN AGENCIA',
                    usuariosSet: new Set(),
                    ventas: 0,
                    ultimo_dia_venta: '',
                };
                const cedula = String(row?.cedula ?? '').replace(/\D+/g, '');
                if (cedula) {
                    current.usuariosSet.add(cedula);
                }
                current.ventas += toNumber(row?.ventas_mes_actual);
                const fecha = String(row?.ultimo_dia_venta || '').trim();
                if (fecha && (!current.ultimo_dia_venta || fecha > current.ultimo_dia_venta)) {
                    current.ultimo_dia_venta = fecha;
                    current.nombre_agencia = String(row?.ultima_agencia_nombre || current.nombre_agencia).trim() || current.nombre_agencia;
                }

                grouped.set(terminal, current);
            });

        return Array.from(grouped.values())
            .map(row => ({
                terminal: row.terminal,
                nombre_agencia: row.nombre_agencia,
                usuarios: row.usuariosSet.size,
                ventas: row.ventas,
                ultimo_dia_venta: row.ultimo_dia_venta,
            }))
            .sort((a, b) => toNumber(b.ventas) - toNumber(a.ventas));
    }

    function renderAgenciasSinEmpresaTable(rows) {
        const data = Array.isArray(rows) ? rows : [];

        if (tableAgenciasSinEmpresa) {
            tableAgenciasSinEmpresa.destroy();
            $('#tableAgenciasSinEmpresa tbody').empty();
        }

        tableAgenciasSinEmpresa = $('#tableAgenciasSinEmpresa').DataTable({
            data: data,
            columns: [
                { data: 'terminal' },
                { data: 'nombre_agencia' },
                {
                    data: 'usuarios',
                    className: 'text-center',
                    render: function(data, type) {
                        const value = Number(data || 0);
                        return type === 'display' ? value.toLocaleString('en-US') : value;
                    }
                },
                {
                    data: 'ventas',
                    className: 'text-end',
                    render: function(data, type) {
                        return type === 'display' ? formatMoney(data) : toNumber(data);
                    }
                },
                {
                    data: 'ultimo_dia_venta',
                    render: function(data, type) {
                        return type === 'display' ? formatDateDisplay(data) : data;
                    }
                }
            ],
            autoWidth: false,
            responsive: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50],
            order: [[3, 'desc']],
            language: {
                lengthMenu: 'Mostrar _MENU_ registros por pagina',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'No hay registros disponibles',
                infoFiltered: '(filtrado de _MAX_ registros totales)',
                search: 'Buscar:',
                zeroRecords: 'No hay agencias pendientes de empresa',
                paginate: {
                    first: 'Primero',
                    last: 'Ultimo',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        });
    }

    function abrirAgenciasSinEmpresaModal() {
        const pendientes = getAgenciasSinEmpresaRows();

        if (!pendientes.length) {
            Swal.fire({
                title: 'Sin pendientes',
                text: 'No hay agencias sin empresa en el reporte actual.',
                icon: 'info'
            });
            return;
        }

        renderAgenciasSinEmpresaTable(pendientes);
        const modal = new bootstrap.Modal(document.getElementById('modalAgenciasSinEmpresa'));
        modal.show();
    }

    function exportUsuariosActualizarExcel() {
        const pendientes = currentFilteredRows.filter(item => esNombrePendiente(item));

        exportRowsToCsv(
            pendientes,
            ['Cedula', 'Nombre', 'Empresa', 'Nombre agencia', 'Terminal', 'Ultimo dia con venta'],
            row => [
                row.cedula || '',
                row.nombre || 'Actualizar en maestro de empleados',
                normalizeEmpresaLabel(row.empresa),
                row.ultima_agencia_nombre || 'SIN AGENCIA',
                row.ultima_terminal || '',
                formatDateDisplay(row.ultimo_dia_venta),
            ],
            'usuarios_por_actualizar.csv'
        );
    }

    function exportAgenciasSinEmpresaExcel() {
        const pendientes = getAgenciasSinEmpresaRows();

        exportRowsToCsv(
            pendientes,
            ['Terminal', 'Nombre agencia', 'Usuarios', 'Ventas', 'Ultimo dia con venta'],
            row => [
                row.terminal || '',
                row.nombre_agencia || 'SIN AGENCIA',
                row.usuarios || 0,
                formatMoney(row.ventas),
                formatDateDisplay(row.ultimo_dia_venta),
            ],
            'agencias_por_asignar_empresa.csv'
        );
    }

    function getBaseFilteredRows() {
        const filtroCumplimiento = document.getElementById('ni_filtro_cumplimiento').value;
        const filtroEmpresa = document.getElementById('ni_filtro_empresa').value;

        let filtered = cachedRows.filter(item => toNumber(item?.ventas_mes_actual) > 0);
        if (filtroCumplimiento === 'cumplidos') {
            filtered = filtered.filter(item => evaluateMetaMinima(item).cumplio);
        } else if (filtroCumplimiento === 'no_cumplidos') {
            filtered = filtered.filter(item => !evaluateMetaMinima(item).cumplio);
        }

        if (filtroEmpresa !== 'todos') {
            filtered = filtered.filter(item => normalizeEmpresaValue(item?.empresa) === filtroEmpresa);
        }

        return filtered;
    }

    function getCedulasReporteActual() {
        const sourceRows = currentFilteredRows;

        return [...new Set(sourceRows
            .filter(row => toNumber(row?.ventas_mes_actual) > 0)
            .map(row => String(row?.cedula ?? '').replace(/\D+/g, ''))
            .filter(Boolean)
        )];
    }

    function calcularMontoIncentivosPorCedulas(cedulas) {
        const cedulasSet = new Set((Array.isArray(cedulas) ? cedulas : [...cedulas])
            .map(cedula => String(cedula ?? '').replace(/\D+/g, ''))
            .filter(Boolean));

        return currentFilteredRows
            .filter(row => cedulasSet.has(String(row?.cedula ?? '').replace(/\D+/g, '')))
            .reduce((sum, row) => sum + toNumber(row?.nuevo_incentivo), 0);
    }

    function aplicarFaltantesIncentivo() {
        if (!lastFaltantesCedulas.size) {
            Swal.fire({ title: 'Sin faltantes', text: 'No hay cedulas con faltantes para aplicar.', icon: 'info' });
            return;
        }

        Swal.fire({
            title: 'Aplicando faltantes...',
            text: 'Estamos ocultando las cedulas con faltantes y recalculando el reporte.',
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        setTimeout(() => {
            excludedFaltantesCedulas = new Set([...excludedFaltantesCedulas, ...lastFaltantesCedulas]);
            applyLocalFilters(false);
            bootstrap.Modal.getInstance(document.getElementById('modalFaltantesIncentivo'))?.hide();

            Swal.fire({
                title: 'Faltantes aplicados',
                text: `${lastFaltantesCedulas.size} cedulas fueron ocultadas del reporte principal.`,
                icon: 'success'
            });
        }, 120);
    }

    function consultarFaltantesIncentivo() {
        if (!cachedRows.length) {
            Swal.fire({ title: 'Informacion', text: 'Primero debes generar el reporte.', icon: 'warning' });
            return;
        }

        const fechaIni = document.getElementById('ni_fecha_ini').value;
        const fechaFin = document.getElementById('ni_fecha_fin').value;
        const cedulas = getCedulasReporteActual();

        if (!fechaIni || !fechaFin) {
            Swal.fire({ title: 'Informacion', text: 'Debe seleccionar fecha inicio y fecha fin.', icon: 'warning' });
            return;
        }

        if (!cedulas.length) {
            Swal.fire({ title: 'Sin cedulas', text: 'No hay cedulas disponibles en el reporte actual.', icon: 'warning' });
            return;
        }

        Swal.fire({
            title: 'Consultando faltantes...',
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('/incentivos/reporte-nuevo-incentivo-v5/faltantes', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({
                cedulas: cedulas,
                fecha_ini: fechaIni,
                fecha_fin: fechaFin,
            }),
        })
            .then(response => parseResponseAsJson(response, 'Error consultando faltantes del incentivo'))
            .then(resp => {
                Swal.close();
                const faltantesRows = Array.isArray(resp.data) ? resp.data : [];
                lastFaltantesCedulas = new Set(faltantesRows
                    .map(row => String(row?.cedula ?? '').replace(/\D+/g, ''))
                    .filter(Boolean));
                const montoIncentivosConFaltantes = calcularMontoIncentivosPorCedulas([...lastFaltantesCedulas]);

                document.getElementById('faltantesIncentivoTotalMonto').textContent = formatMoney(resp.total_monto || 0);
                document.getElementById('faltantesIncentivoTotalCantidad').textContent = Number(resp.total_faltantes || 0).toLocaleString('en-US');
                document.getElementById('faltantesIncentivoTotalCedulas').textContent = cedulas.length.toLocaleString('en-US');
                document.getElementById('faltantesIncentivoMontoIncentivos').textContent = formatMoney(montoIncentivosConFaltantes);
                document.getElementById('faltantesIncentivoRango').textContent = `Rango consultado: ${fechaIni} al ${fechaFin}`;
                renderFaltantesIncentivoTable(faltantesRows);

                const modal = new bootstrap.Modal(document.getElementById('modalFaltantesIncentivo'));
                modal.show();
            })
            .catch(error => {
                Swal.fire({ title: 'Error', text: error?.message || String(error), icon: 'warning' });
            });
    }

    function aplicarRecargasPaqueticosIncentivo() {
        if (!lastRecargasPaqueticosCedulas.size) {
            Swal.fire({ title: 'Sin ventas', text: 'No hay cedulas con ventas de recargas o paqueticos para aplicar.', icon: 'info' });
            return;
        }

        Swal.fire({
            title: 'Aplicando recargas y paqueticos...',
            text: 'Estamos ocultando las cedulas con esas ventas y recalculando el reporte.',
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        setTimeout(() => {
            const rows = tableRecargasPaqueticosIncentivo?.rows?.().data?.().toArray?.() || [];
            rows.forEach(row => {
                const cedula = String(row?.cedula ?? '').replace(/\D+/g, '');
                const monto = toNumber(row?.total_ventas);
                if (cedula && monto > 0) {
                    recargasPaqueticosDescuentos.set(cedula, monto);
                }
            });
            applyLocalFilters(false);
            bootstrap.Modal.getInstance(document.getElementById('modalRecargasPaqueticosIncentivo'))?.hide();

            Swal.fire({
                title: 'Recargas y paqueticos aplicados',
                text: `Se desconto el monto de recargas y paqueticos a ${lastRecargasPaqueticosCedulas.size} cedulas sin ocultarlas.`,
                icon: 'success'
            });
        }, 120);
    }

    function consultarRecargasPaqueticosIncentivo() {
        if (!cachedRows.length) {
            Swal.fire({ title: 'Informacion', text: 'Primero debes generar el reporte.', icon: 'warning' });
            return;
        }

        const fechaIni = document.getElementById('ni_fecha_ini').value;
        const fechaFin = document.getElementById('ni_fecha_fin').value;
        const sistema = document.getElementById('ni_sistema').value;
        const cedulas = getCedulasReporteActual();

        if (!fechaIni || !fechaFin) {
            Swal.fire({ title: 'Informacion', text: 'Debe seleccionar fecha inicio y fecha fin.', icon: 'warning' });
            return;
        }

        if (!cedulas.length) {
            Swal.fire({ title: 'Sin cedulas', text: 'No hay cedulas disponibles en el reporte actual.', icon: 'warning' });
            return;
        }

        Swal.fire({
            title: 'Consultando recargas y paqueticos...',
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('/incentivos/reporte-nuevo-incentivo-v5/recargas-paqueticos', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({
                cedulas: cedulas,
                fecha_ini: fechaIni,
                fecha_fin: fechaFin,
                sistema: sistema,
            }),
        })
            .then(response => parseResponseAsJson(response, 'Error consultando recargas y paqueticos del incentivo'))
            .then(resp => {
                Swal.close();
                const rows = Array.isArray(resp.data) ? resp.data : [];
                lastRecargasPaqueticosCedulas = new Set(rows
                    .map(row => String(row?.cedula ?? '').replace(/\D+/g, ''))
                    .filter(Boolean));
                const montoIncentivosConVentas = calcularMontoIncentivosPorCedulas([...lastRecargasPaqueticosCedulas]);

                document.getElementById('recargasPaqueticosTotalVentas').textContent = formatMoney(resp.total_ventas || 0);
                document.getElementById('recargasPaqueticosTotalRecargas').textContent = formatMoney(resp.total_recargas || 0);
                document.getElementById('recargasPaqueticosTotalPaqueticos').textContent = formatMoney(resp.total_paqueticos || 0);
                document.getElementById('recargasPaqueticosCantidadVentas').textContent = Number(resp.cantidad_ventas || 0).toLocaleString('en-US');
                document.getElementById('recargasPaqueticosTotalCedulas').textContent = cedulas.length.toLocaleString('en-US');
                document.getElementById('recargasPaqueticosMontoIncentivos').textContent = formatMoney(montoIncentivosConVentas);
                document.getElementById('recargasPaqueticosIncentivoRango').textContent = `Rango consultado: ${fechaIni} al ${fechaFin}`;
                renderRecargasPaqueticosIncentivoTable(rows);

                const modal = new bootstrap.Modal(document.getElementById('modalRecargasPaqueticosIncentivo'));
                modal.show();
            })
            .catch(error => {
                Swal.fire({ title: 'Error', text: error?.message || String(error), icon: 'warning' });
            });
    }

    function applyLocalFilters(showFilterAlert = false) {
        if (!cachedRows.length) {
            Swal.fire({ title: 'Informacion', text: 'Primero debes generar el reporte.', icon: 'warning' });
            return;
        }

        const sistema = document.getElementById('ni_sistema').value;
        const tipoPago = document.getElementById('ni_tipo_pago').value;
        const modoCalculo = document.getElementById('ni_modo_calculo').value;

        if (cachedSistema !== sistema || cachedTipoPago !== tipoPago || cachedModoCalculo !== modoCalculo) {
            Swal.fire({ title: 'Informacion', text: 'Cambiaste el sistema, tipo de pago o modo de calculo. Presiona \"Generar Reporte\" para recargar datos.', icon: 'info' });
            return;
        }

        let filtered = getBaseFilteredRows();

        if (excludedFaltantesCedulas.size) {
            filtered = filtered.filter(item => !excludedFaltantesCedulas.has(String(item?.cedula ?? '').replace(/\D+/g, '')));
        }

        if (recargasPaqueticosDescuentos.size) {
            filtered = filtered.map(item => aplicarDescuentoRecargasPaqueticos(item));
        }

        currentFilteredRows = filtered;
        renderTableFromData(filtered);
        updateCardsFromData(filtered);
        const modalAdministrativos = document.getElementById('modalAdministrativos');
        if (modalAdministrativos && modalAdministrativos.classList.contains('show')) {
            renderAdministrativeCategoryTable();
        }

        document.getElementById('ni_rango_evaluado').textContent =
            `Mes evaluado: ${cachedMeta.eval_ini || ''} al ${cachedMeta.eval_fin || ''} | Modo: ${cachedMeta.modo_calculo_label || 'General consolidado'}${renderResumenEmpresas(cachedMeta)}`;

        if (showFilterAlert) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Filtros aplicados en memoria',
                showConfirmButton: false,
                timer: 1800,
                timerProgressBar: true
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const fechaHoy = new Date();
        const yyyy = fechaHoy.getFullYear();
        const mm = String(fechaHoy.getMonth() + 1).padStart(2, '0');
        const dd = String(fechaHoy.getDate()).padStart(2, '0');

        document.getElementById('ni_fecha_fin').value = `${yyyy}-${mm}-${dd}`;
        document.getElementById('ni_fecha_ini').value = `${yyyy}-${mm}-01`;
        updatePuestoPctSummaryCard();
        populateEmpresaFilterOptions([]);

        document.querySelector('#btnConfigPct').addEventListener('click', function() {
            renderRangesTable();
            const modal = new bootstrap.Modal(document.getElementById('modalConfigPct'));
            modal.show();
        });
        document.querySelector('#btnConfigPuestoPct').addEventListener('click', function() {
            renderPuestoPctInputs();
            const modal = new bootstrap.Modal(document.getElementById('modalConfigPuestoPct'));
            modal.show();
        });
        document.querySelector('#btnConfigAdminPct').addEventListener('click', function() {
            document.getElementById('admin_pct_bruto').value = adminPctBruto;
            const modal = new bootstrap.Modal(document.getElementById('modalConfigAdminPct'));
            modal.show();
        });

        document.querySelector('#btnConfigAdministrativos').addEventListener('click', function() {
            renderAdministrativeCategoryTable();
            const modal = new bootstrap.Modal(document.getElementById('modalAdministrativos'));
            modal.show();
        });

        document.querySelector('#btnAdminFiltroTodos').addEventListener('click', function() {
            administrativeGroupFilter = 'todos';
            renderAdministrativeCategoryTable();
        });

        document.querySelector('#btnAdminFiltroG1').addEventListener('click', function() {
            administrativeGroupFilter = '1. Gtes. Y Encarg.';
            renderAdministrativeCategoryTable();
        });

        document.querySelector('#btnAdminFiltroG2').addEventListener('click', function() {
            administrativeGroupFilter = '2. Monitoreo';
            renderAdministrativeCategoryTable();
        });

        document.querySelector('#btnAdminFiltroG45').addEventListener('click', function() {
            administrativeGroupFilter = '4_5';
            renderAdministrativeCategoryTable();
        });

        document.querySelector('#btnConfigCoordinadores').addEventListener('click', function() {
            renderCoordinatorTable();
            const modal = new bootstrap.Modal(document.getElementById('modalCoordinadores'));
            modal.show();
        });

        document.querySelector('#btnConsultarFaltantes').addEventListener('click', function() {
            consultarFaltantesIncentivo();
        });

        document.querySelector('#btnConsultarRecargasPaqueticos').addEventListener('click', function() {
            consultarRecargasPaqueticosIncentivo();
        });

        document.querySelector('#btnAplicarFaltantesIncentivo').addEventListener('click', function() {
            aplicarFaltantesIncentivo();
        });

        document.querySelector('#btnAplicarRecargasPaqueticosIncentivo').addEventListener('click', function() {
            aplicarRecargasPaqueticosIncentivo();
        });

        document.querySelector('#ni_count_por_actualizar').addEventListener('click', function() {
            abrirUsuariosActualizarModal();
        });

        document.querySelector('#ni_count_agencias_sin_empresa').addEventListener('click', function() {
            abrirAgenciasSinEmpresaModal();
        });

        document.getElementById('modalFaltantesIncentivo').addEventListener('shown.bs.modal', function() {
            if (tableFaltantesIncentivo) {
                tableFaltantesIncentivo.columns.adjust().responsive.recalc();
            }
        });

        document.getElementById('modalRecargasPaqueticosIncentivo').addEventListener('shown.bs.modal', function() {
            if (tableRecargasPaqueticosIncentivo) {
                tableRecargasPaqueticosIncentivo.columns.adjust().responsive.recalc();
            }
        });

        document.getElementById('modalUsuariosActualizar').addEventListener('shown.bs.modal', function() {
            if (tableUsuariosActualizar) {
                tableUsuariosActualizar.columns.adjust().responsive.recalc();
            }
        });

        document.getElementById('modalAgenciasSinEmpresa').addEventListener('shown.bs.modal', function() {
            if (tableAgenciasSinEmpresa) {
                tableAgenciasSinEmpresa.columns.adjust().responsive.recalc();
            }
        });

        document.querySelector('#btnExportUsuariosActualizarExcel').addEventListener('click', function() {
            exportUsuariosActualizarExcel();
        });

        document.querySelector('#btnExportAgenciasSinEmpresaExcel').addEventListener('click', function() {
            exportAgenciasSinEmpresaExcel();
        });

        document.querySelector('#tbodyCoordinadores').addEventListener('click', function(event) {
            const button = event.target.closest('.btn-ver-detalle-coord');
            if (!button) {
                return;
            }

            const idx = parseInt(button.dataset.idx, 10);
            const row = coordinatorRows[idx];
            if (!row) {
                return;
            }

            renderCoordinatorDetailTable(row);
            bootstrap.Modal.getInstance(document.getElementById('modalCoordinadores'))?.hide();
            const detailModal = new bootstrap.Modal(document.getElementById('modalCoordinadorDetalle'));
            detailModal.show();
        });

        document.querySelector('#btnBackToCoordinadores').addEventListener('click', function() {
            bootstrap.Modal.getInstance(document.getElementById('modalCoordinadorDetalle'))?.hide();
            const modal = new bootstrap.Modal(document.getElementById('modalCoordinadores'));
            modal.show();
        });

        document.querySelector('#tbodyAdministrativos').addEventListener('input', function(event) {
            const input = event.target;
            if (input.tagName === 'SELECT') {
                return;
            }

            const idx = parseInt(input.dataset.idx, 10);
            const field = input.dataset.field;
            if (!field || Number.isNaN(idx)) return;

            if (input.classList.contains('admin-input')) {
                if (!administrativeRows[idx]) return;
                administrativeRows[idx][field] = field === 'pct'
                    ? (Math.max(0, parseFloat(input.value || 0) || 0) / 100)
                    : input.value;
                updateAdministrativeAndOperatorAmounts();
                return;
            }

            if (input.classList.contains('op-input')) {
                if (!operatorRows[idx]) return;
                operatorRows[idx][field] = field === 'pct'
                    ? (Math.max(0, parseFloat(input.value || 0) || 0) / 100)
                    : input.value;
                updateAdministrativeAndOperatorAmounts();
            }
        });

        document.querySelector('#tbodyAdministrativos').addEventListener('change', function(event) {
            const input = event.target;
            const idx = parseInt(input.dataset.idx, 10);
            const field = input.dataset.field;

            if (field && !Number.isNaN(idx) && (input.classList.contains('admin-input') || input.classList.contains('op-input'))) {
                const rows = input.classList.contains('op-input') ? operatorRows : administrativeRows;
                if (!rows[idx]) return;

                rows[idx][field] = field === 'pct'
                    ? (Math.max(0, parseFloat(input.value || 0) || 0) / 100)
                    : input.value;

                if (field === 'grupo') {
                    rebalanceAdministrativeRows();
                    renderAdministrativeCategoryTable();
                }

                updateAdministrativeAndOperatorAmounts();
            }

            if (input.classList.contains('admin-pct-input') || input.classList.contains('op-pct-input')) {
                input.value = (parseFloat(input.value || 0) || 0).toFixed(2);
            }
        });

        document.querySelector('#tbodyAdministrativos').addEventListener('click', function(event) {
            const button = event.target.closest('.btn-delete-admin-row');
            if (!button) {
                return;
            }

            eliminarFilaAdministrativa(button.dataset.tipo, button.dataset.idx);
        });

        document.querySelector('#btnAgregarAdministrativoFila').addEventListener('click', function() {
            agregarFilaAdministrativa();
        });

        document.querySelector('#btnGuardarAdministrativosPlantilla').addEventListener('click', function() {
            guardarAdministrativosPlantilla();
        });

        document.querySelector('#btnRestaurarAdministrativos').addEventListener('click', function() {
            administrativeRows = getDefaultAdministrativeRows();
            operatorRows = getDefaultOperatorRows();
            administrativeGroupFilter = 'todos';
            renderAdministrativeCategoryTable();
            updateAdministrativeAndOperatorAmounts();
        });

        document.querySelector('#tbodyCoordinadores').addEventListener('input', function(event) {
            const input = event.target;
            if (!input.classList.contains('coord-input')) {
                return;
            }

            const idx = parseInt(input.dataset.idx, 10);
            const field = input.dataset.field;
            if (!coordinatorRows[idx] || !field) {
                return;
            }

            coordinatorRows[idx][field] = field === 'pct'
                ? (Math.max(0, parseFloat(input.value || 0) || 0) / 100)
                : input.value;

            updateCoordinatorAmounts();
        });

        document.querySelector('#tbodyCoordinadores').addEventListener('change', function(event) {
            const input = event.target;
            if (input.classList.contains('coord-pct-input')) {
                input.value = (parseFloat(input.value || 0) || 0).toFixed(2);
            }
        });

        document.querySelector('#btnRestaurarCoordinadores').addEventListener('click', function() {
            coordinatorRows = getDefaultCoordinatorRows();
            coordinatorUserDetailsByCoordinator = {};
            renderCoordinatorTable();
        });

        document.querySelector('#btnExportAdministrativosExcel').addEventListener('click', function() {
            exportAdministrativosExcel();
        });

        document.querySelector('#btnExportCoordinadoresExcel').addEventListener('click', function() {
            exportCoordinadoresExcel();
        });

        document.querySelector('#btnRestaurarTramos').addEventListener('click', function() {
            const tipoPago = document.getElementById('ni_tipo_pago').value;
            payoutRangesByType[tipoPago] = getDefaultRanges()[tipoPago];
            renderRangesTable();
        });

        document.querySelector('#btnGuardarPct').addEventListener('click', function() {
            try {
                const tipoPago = document.getElementById('ni_tipo_pago').value;
                payoutRangesByType[tipoPago] = readRangesFromTable();
            } catch (e) {
                Swal.fire({ title: 'Validacion', text: e.message, icon: 'warning' });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('modalConfigPct'))?.hide();
            Swal.fire({ title: 'Configuracion guardada', text: 'Los tramos se aplicaran al generar el reporte.', icon: 'success' });
        });

        document.querySelector('#btnGuardarAdminPct').addEventListener('click', function() {
            adminPctBruto = parseFloat(document.getElementById('admin_pct_bruto').value || 0);
            bootstrap.Modal.getInstance(document.getElementById('modalConfigAdminPct'))?.hide();

            if (
                cachedRows.length
                && cachedSistema === document.getElementById('ni_sistema').value
                && cachedTipoPago === document.getElementById('ni_tipo_pago').value
                && cachedModoCalculo === document.getElementById('ni_modo_calculo').value
            ) {
                applyLocalFilters(false);
            } else {
                currentDistributionBase = 0;
                currentAdministrativePoolBase = 0;
                currentAdministrativePoolByEmpresa = {};
                currentAdministrativeBase = 0;
                currentOperatorBase = 0;
                currentCoordinatorBase = 0;
                document.getElementById('ni_admin_resumen').innerHTML =
                    `<div>Porcentaje (${formatPercentDisplay(adminPctBruto)}%): 0.00</div>
                    <div>Administrativo: 0.00</div>
                    <div>Coordinador: 0.00</div>`;
                document.getElementById('ni_total_con_admin').textContent = 'Total a Pagar Final: 0.00';
                updateAdministrativeAndOperatorAmounts();
                updateCoordinatorAmounts();
                updatePuestoPctSummaryCard();
            }

            Swal.fire({
                title: 'Configuracion guardada',
                text: 'El % administrativo se aplica sobre el Total Incentivo a Pagar.',
                icon: 'success'
            });
        });

        document.querySelector('#btnGuardarPuestoPct').addEventListener('click', function() {
            readPuestoPctInputs();
            updatePuestoPctSummaryCard();
            updateAdministrativeAndOperatorAmounts();
            bootstrap.Modal.getInstance(document.getElementById('modalConfigPuestoPct'))?.hide();

            Swal.fire({
                title: 'Configuracion guardada',
                text: '% por categoria guardado correctamente.',
                icon: 'success'
            });
        });
    });

    function listNuevoIncentivo(showFilterAlert = false) {
        const sistema = document.getElementById('ni_sistema').value;
        const fechaIni = document.getElementById('ni_fecha_ini').value;
        const fechaFin = document.getElementById('ni_fecha_fin').value;
        const minDias = document.getElementById('ni_min_dias').value;
        const tipoPago = document.getElementById('ni_tipo_pago').value;
        const modoCalculo = document.getElementById('ni_modo_calculo').value;

        if (!fechaIni || !fechaFin) {
            Swal.fire({ title: 'Informacion', text: 'Debe seleccionar fecha inicio y fecha fin.', icon: 'warning' });
            return;
        }

        Swal.fire({
            title: 'Procesando Informacion ...',
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            timerProgressBar: true,
            didOpen: () => Swal.showLoading()
        });

        $('#tableNuevoIncentivo tbody').empty();
        currentFilteredRows = [];
        lastFaltantesCedulas = new Set();
        excludedFaltantesCedulas = new Set();
        lastRecargasPaqueticosCedulas = new Set();
        recargasPaqueticosDescuentos = new Map();

        const params = new URLSearchParams({
            sistema: sistema,
            filtro_cumplimiento: 'todos',
            fecha_ini: fechaIni,
            fecha_fin: fechaFin,
            min_dias_venta: minDias,
            tipo_pago: tipoPago,
            modo_calculo: modoCalculo,
            rangos_pago: JSON.stringify(payoutRangesByType[tipoPago]),
        });

        fetch('/incentivos/reporte-nuevo-incentivo-v5?' + params.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(response => parseResponseAsJson(response, 'Error consultando reporte nuevo incentivo V5'))
            .then(resp => {
                if ('message' in resp) {
                    Swal.fire({ title: 'Informacion', text: resp.message, icon: 'warning' });
                    return;
                }

                const data = resp.data || [];
                cachedRows = data;
                cachedMeta = resp.meta || {};
                updateCoordinatorValidAgencies(cachedMeta);
                cachedSistema = sistema;
                cachedTipoPago = tipoPago;
                cachedModoCalculo = modoCalculo;
                populateEmpresaFilterOptions(cachedRows);

                Swal.close();
                applyLocalFilters(showFilterAlert);
            })
            .catch(error => {
                Swal.fire({ title: 'Error', text: error?.message || String(error), icon: 'warning' });
            });
    }

    function filtrarCumplimientoTabla() {
        applyLocalFilters(true);
    }

    document.querySelector('#btnGenerarNuevoIncentivo').addEventListener('click', function() {
        listNuevoIncentivo();
    });

    document.querySelector('#btnGenerarExcelPago').addEventListener('click', function() {
        generarXlsxPagoIncentivo();
    });

    document.querySelector('#btnFiltrarCumplimiento').addEventListener('click', function() {
        filtrarCumplimientoTabla();
    });
</script>
@endsection






