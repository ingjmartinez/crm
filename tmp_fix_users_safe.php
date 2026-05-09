<?php
$f='resources/views/usuarios/index.blade.php';
$c=file_get_contents($f);
$repl=[
'Correo ElectrÃ³nico'=>'Correo Electr&oacute;nico',
'</script> Â© CRM.'=>'</script> &copy; CRM.',
'Confirmar eliminaciÃ³n'=>'Confirmar eliminaci&oacute;n',
'Â¿EstÃ¡ seguro que desea eliminar este usuario? Esta acciÃ³n no se puede deshacer.'=>'&iquest;Est&aacute; seguro que desea eliminar este usuario? Esta acci&oacute;n no se puede deshacer.',
'// Manejar eliminaciÃ³n'=>'// Manejar eliminacion',
'// Mostrar mensaje de Ã©xito si existe'=>'// Mostrar mensaje de exito si existe',
"title: 'Â¡Ã‰xito!'"=>"title: '\\u00A1\\u00C9xito!'",
"title: 'Â¡Ã?xito!'"=>"title: '\\u00A1\\u00C9xito!'",
'// Mostrar errores de validaciÃ³n'=>'// Mostrar errores de validacion',
"title: 'Error de validaciÃ³n'"=>"title: 'Error de validacion'",
];
$c=strtr($c,$repl);
file_put_contents($f,$c);
