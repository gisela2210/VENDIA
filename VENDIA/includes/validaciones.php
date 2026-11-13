<?php
/** Validaciones del servidor. No confiar únicamente en HTML/JavaScript. */
function nombreValido(string $valor): bool {
    return preg_match('/^[\p{L}]+(?: +[\p{L}]+)*$/uD', $valor)===1 &&
           (function_exists('mb_strlen')?mb_strlen($valor,'UTF-8'):strlen($valor))<=100;
}
function telefonoValido(string $valor): bool { return preg_match('/^[0-9]{10}$/D',$valor)===1; }
function usuarioValido(string $valor): bool { return preg_match('/^[a-zA-Z0-9._]{4,40}$/D',$valor)===1; }
function validarFechaEntrega(string $valor): ?string {
    if (trim($valor)==='') return null;
    $f=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$valor);
    if (!$f || $f->format('Y-m-d\TH:i')!==$valor) return null;
    return $f->format('Y-m-d H:i:s');
}

/** Solo correos personales admitidos por Vendia (se valida en PHP y en el navegador). */
function correoPermitido(string $valor): bool {
    if (strlen($valor)>150 || !filter_var($valor, FILTER_VALIDATE_EMAIL)) return false;
    $dominio=strtolower(substr(strrchr($valor, '@'), 1));
    return in_array($dominio, ['gmail.com','hotmail.com','outlook.com'],true);
}
function edadClienteValida($edad): bool {
    return filter_var($edad,FILTER_VALIDATE_INT,['options'=>['min_range'=>18,'max_range'=>120]])!==false;
}
