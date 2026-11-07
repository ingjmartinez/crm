document.addEventListener('DOMContentLoaded', function () {
        const modalImpresion = document.getElementById('modalImpresionV11');
        if (modalImpresion) document.body.appendChild(modalImpresion);
        const formularioImpresion = document.getElementById('formImpresionV11');
        if (formularioImpresion) {
            let generando = false;
            formularioImpresion.addEventListener('submit', async event => {
                event.preventDefault();
                if (generando) return;
                const salida = event.submitter?.value || 'pdf';
                const estado = document.getElementById('estadoImpresionV11');
                const botones = formularioImpresion.querySelectorAll('button[type="submit"]');
                const controlador = new AbortController();
                let ventana;
                let tiempo;
                generando = true;
                botones.forEach(boton => { boton.disabled = true; });
                estado.className = 'alert alert-info mt-3';
                estado.textContent = salida === 'pdf' ? 'Generando PDF... La descarga comenzará al terminar.' : 'Preparando documento para imprimir...';
                try {
                    if (salida === 'imprimir') {
                        ventana = window.open('', '_blank');
                        if (!ventana) throw new Error('El navegador bloqueó la ventana de impresión. Permite las ventanas emergentes e intenta de nuevo.');
                        ventana.opener = null;
                        ventana.document.body.textContent = 'Preparando impresión...';
                    }
                    const parametros = new URLSearchParams(new FormData(formularioImpresion));
                    parametros.set('salida', salida);
                    const url = new URL(formularioImpresion.action, window.location.href);
                    url.search = parametros.toString();
                    tiempo = setTimeout(() => controlador.abort(), 180000);
                    const respuesta = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controlador.signal });
                    if (!respuesta.ok || respuesta.redirected) {
                        let mensaje = 'No se pudo generar el documento. Revisa la sesión e intenta de nuevo.';
                        if (respuesta.headers.get('Content-Type')?.includes('application/json')) {
                            const error = await respuesta.json();
                            mensaje = Object.values(error.errors || {}).flat()[0] || error.message || mensaje;
                        }
                        throw new Error(mensaje);
                    }
                    if (salida === 'imprimir') {
                        if (!respuesta.headers.get('Content-Type')?.includes('text/html')) throw new Error('No se recibió el documento para imprimir.');
                        const html = await respuesta.text();
                        if (ventana.closed) throw new Error('La ventana de impresión fue cerrada. Intenta de nuevo.');
                        ventana.document.open();
                        ventana.document.write(html);
                        ventana.document.close();
                    } else {
                        if (!respuesta.headers.get('Content-Type')?.includes('application/pdf')) throw new Error('No se recibió un PDF válido. Vuelve a consultar el reporte e intenta de nuevo.');
                        const archivo = await respuesta.blob();
                        const enlace = document.createElement('a');
                        const urlArchivo = URL.createObjectURL(archivo);
                        enlace.href = urlArchivo;
                        enlace.download = 'rentabilidad_agencia_v11.pdf';
                        document.body.appendChild(enlace);
                        enlace.click();
                        enlace.remove();
                        setTimeout(() => URL.revokeObjectURL(urlArchivo), 60000);
                    }
                    estado.className = 'alert alert-success mt-3';
                    estado.textContent = salida === 'pdf' ? 'PDF generado. Revisa las descargas de tu navegador.' : 'Documento preparado en la ventana de impresión.';
                } catch (error) {
                    ventana?.close();
                    estado.className = 'alert alert-danger mt-3';
                    estado.textContent = error.name === 'AbortError' ? 'La generación superó el tiempo de espera. Intenta con una sola ruta.' : error.message;
                } finally {
                    clearTimeout(tiempo);
                    botones.forEach(boton => { boton.disabled = false; });
                    generando = false;
                }
            });
        }
});
