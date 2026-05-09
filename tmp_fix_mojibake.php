<?php
$files=['resources/views/agencias/index.blade.php','resources/views/usuarios/index.blade.php'];
foreach($files as $f){
  $c=file_get_contents($f);
  $fixed=mb_convert_encoding($c,'ISO-8859-1','UTF-8');
  file_put_contents($f,$fixed);
  echo "fixed:$f\n";
}
