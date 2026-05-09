<?php
$f='resources/views/agencias/index.blade.php';
$c=file_get_contents($f);
$repl=[
'</script> Â© CRM.'=>'</script> &copy; CRM.',
'Confirmar eliminaciÃ³n'=>'Confirmar eliminaci&oacute;n',
'Â¿EstÃ¡ seguro que desea eliminar esta agencia?'=>'&iquest;Est&aacute; seguro que desea eliminar esta agencia?',
'<!-- Modal para actualizaciÃ³n masiva -->'=>'<!-- Modal para actualizacion masiva -->',
'ActualizaciÃ³n masiva de Agencias'=>'Actualizacion masiva de Agencias',
'Descargar plantilla de actualizaciÃ³n'=>'Descargar plantilla de actualizacion',
'Reglas de actualizaciÃ³n:'=>'Reglas de actualizacion:',
'Si una celda viene vacÃ­a, ese campo no se modifica.'=>'Si una celda viene vacia, ese campo no se modifica.',
'Puede actualizar 1, 2 o mÃ¡s campos en el mismo archivo.'=>'Puede actualizar 1, 2 o mas campos en el mismo archivo.',
'// ConfiguraciÃ³n responsive de DataTables'=>'// Configuracion responsive de DataTables',
'// En mÃ³vil, ocultar mÃ¡s columnas'=>'// En movil, ocultar mas columnas',
"<span class=\"badge bg-success\">SÃ­</span>"=>"<span class=\"badge bg-success\">Si</span>",
'// Carga secuencial: primero DataTable, luego no registradas para evitar sensaciÃ³n de bloqueo.'=>'// Carga secuencial: primero DataTable, luego no registradas para evitar sensacion de bloqueo.',
'// Manejar eliminaciÃ³n'=>'// Manejar eliminacion',
'// Mostrar mensaje de Ã©xito si existe'=>'// Mostrar mensaje de exito si existe',
"title: 'Â¡Ã‰xito!'"=>"title: '\\u00A1\\u00C9xito!'",
"title: 'Â¡Ã?xito!'"=>"title: '\\u00A1\\u00C9xito!'",
'// Resumen de actualizaciÃ³n masiva con conteo'=>'// Resumen de actualizacion masiva con conteo',
"title: 'ActualizaciÃ³n masiva finalizada'"=>"title: 'Actualizacion masiva finalizada'",
'Filas invÃ¡lidas'=>'Filas invalidas',
'// Mostrar errores de validaciÃ³n'=>'// Mostrar errores de validacion',
"title: 'Error de validaciÃ³n'"=>"title: 'Error de validacion'",
'Todas las terminales leÃ­das existen en la tabla de agencias.'=>'Todas las terminales leidas existen en la tabla de agencias.',
'Terminales leÃ­das:'=>'Terminales leidas:',
'Terminales Ãºnicas:'=>'Terminales unicas:',
'Primero debes reconocer terminales antes de ejecutar la actualizaciÃ³n masiva.'=>'Primero debes reconocer terminales antes de ejecutar la actualizacion masiva.',
"title: 'Procesando actualizaciÃ³n masiva'"=>"title: 'Procesando actualizacion masiva'",
'Procesando actualizaciÃ³n... '=>'Procesando actualizacion... ',
];
$c=strtr($c,$repl);
file_put_contents($f,$c);
