    <div class="modal fade" id="modalJustificacionFeriado" tabindex="-1" aria-labelledby="tituloJustificacionFeriado" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloJustificacionFeriado">Justificación del horario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-1" id="revisionEmpleado"></p>
                    <p class="text-muted small" id="revisionFecha"></p>
                    <div class="alert alert-warning" id="revisionMotivo"></div>
                    <dl class="row small mb-0">
                        <dt class="col-6">Entrada del ponche</dt><dd class="col-6" id="revisionEntrada"></dd>
                        <dt class="col-6">Salida calculada</dt><dd class="col-6" id="revisionSalida"></dd>
                        <dt class="col-6">Primera venta</dt><dd class="col-6" id="revisionPrimera"></dd>
                        <dt class="col-6">Entrada calculada</dt><dd class="col-6" id="revisionEntradaCalculada"></dd>
                        <dt class="col-6">Hora calculada</dt><dd class="col-6" id="revisionCalculada"></dd>
                        <dt class="col-6">Última transacción</dt><dd class="col-6" id="revisionUltima"></dd>
                        <dt class="col-6">Movimientos</dt><dd class="col-6" id="revisionMovimientos"></dd>
                    </dl>
                    <div id="revisionCoincidencias"></div>
                    <p class="text-muted small mt-3 mb-0">Las transacciones identifican actividad de la cuenta. La primera venta no confirma la hora exacta de entrada ni la presencia física. El motivo describe una inconsistencia posible, no una causa comprobada.</p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
            </div>
        </div>
    </div>
