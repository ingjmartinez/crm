        function formatearHoras(valor) {
            return valor === null || valor === undefined ? '—' : Number(valor).toLocaleString('es-DO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function escaparTexto(valor) {
            return String(valor ?? '').replace(/[&<>"']/g, caracter => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[caracter]));
        }

        function etiquetaRevision(revision) {
            return {conflicto: 'Revisar cruce', sin_transacciones: 'Sin transacciones', sin_cruce_detectado: 'Ver evidencia'}[revision?.estado] || 'Pendiente';
        }

        function renderizarJustificacion(revision, type) {
            const etiqueta = etiquetaRevision(revision);
            if (type !== 'display') return revision?.motivo || etiqueta;
            const estilo = revision?.estado === 'conflicto' ? 'btn-warning text-dark fw-semibold shadow-sm' : 'btn-outline-secondary';
            return `<button type="button" class="btn btn-sm ${estilo} btn-justificacion" aria-label="Ver justificación del horario">${etiqueta}</button>`;
        }

        function abrirJustificacion(fila) {
            const revision = fila.revision;
            const valores = {
                revisionEmpleado: `${fila.nombre} · ${fila.cedula}`,
                revisionFecha: `${fila.fecha} · ${fila.sistema}`,
                revisionMotivo: revision.motivo,
                revisionEntrada: fila.primer_login || 'Sin registro',
                revisionSalida: fila.salida_calculada || 'Sin registro',
                revisionPrimera: revision.primera_transaccion || 'Sin transacciones',
                revisionCalculada: formatearHoras(fila.hora_calculada),
                revisionEntradaCalculada: fila.entrada_calculada || 'Sin entrada disponible',
                revisionUltima: revision.ultima_transaccion || 'Sin transacciones',
                revisionMovimientos: revision.movimientos
            };
            Object.entries(valores).forEach(([id, valor]) => document.getElementById(id).textContent = valor);
            document.getElementById('revisionMotivo').className = revision.estado === 'conflicto' ? 'alert alert-warning' : 'alert alert-info';
            document.getElementById('revisionCoincidencias').innerHTML = revision.coincidencias.length
                ? '<h6 class="mt-3">Actividad coincidente en el terminal</h6>' + revision.coincidencias.map(actividad => `
                    <div class="border rounded p-2 mt-2 small">
                        <strong>Cédula ${escaparTexto(actividad.cedula)}</strong> · Terminal ${escaparTexto(actividad.terminal)}<br>
                        Primera: ${escaparTexto(actividad.primera)}<br>
                        Última: ${escaparTexto(actividad.ultima)}<br>
                        Movimientos: ${escaparTexto(actividad.cantidad)}
                    </div>`).join('')
                : '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalJustificacionFeriado')).show();
        }

