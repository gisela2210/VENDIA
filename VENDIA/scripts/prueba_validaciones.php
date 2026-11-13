<?php
/** Pruebas locales sin MySQL: php scripts/prueba_validaciones.php */
if (PHP_SAPI!=='cli') {http_response_code(403);exit('Solo terminal.');}
require dirname(__DIR__).'/includes/validaciones.php';
$pruebas=[
  'Nombre con acentos' => nombreValido('María Ángeles') === true,
  'Nombre rechaza números' => nombreValido('María12') === false,
  'Nombre rechaza símbolos' => nombreValido('Pedro#') === false,
  'Teléfono de 10 dígitos' => telefonoValido('2291234567') === true,
  'Gmail admitido' => correoPermitido('demo@gmail.com') === true,
  'Hotmail admitido' => correoPermitido('demo@hotmail.com') === true,
  'Outlook admitido' => correoPermitido('demo@outlook.com') === true,
  'Otros dominios rechazados' => correoPermitido('demo@yahoo.com') === false,
  'Cliente de 18 años' => edadClienteValida('18') === true,
  'Cliente de 17 años rechazado' => edadClienteValida('17') === false,
  'Usuario demo' => usuarioValido('superlissy') === true,
];
$fallas=0;
foreach($pruebas as $nombre=>$ok){echo ($ok?'OK  ':'FAIL  ').$nombre.PHP_EOL;if(!$ok)$fallas++;}
exit($fallas ? 1:0);
