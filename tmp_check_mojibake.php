<?php
$files=['resources/views/agencias/index.blade.php','resources/views/usuarios/index.blade.php'];
foreach($files as $f){
  $c=file_get_contents($f);
  $countA=substr_count($c,'Ã');
  $countB=substr_count($c,'Â');
  echo $f."|Ã=".$countA."|Â=".$countB.PHP_EOL;
}
