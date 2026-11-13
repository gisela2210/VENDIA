<?php
/** Referencia opcional del producto solicitado; no permite ejecutar archivos. */
function guardarFotoPedido(?array $archivo): ?string {
    if (!$archivo || (int)($archivo['error']??UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ((int)$archivo['error']!==UPLOAD_ERR_OK || !is_uploaded_file((string)$archivo['tmp_name']) ||
        (int)$archivo['size']<1 || (int)$archivo['size']>3145728) {
        throw new RuntimeException('La fotografía del pedido debe ser JPG, PNG o WEBP y pesar hasta 3 MB.');
    }
    $info=@getimagesize((string)$archivo['tmp_name']);
    $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime=$info['mime']??'';
    if (!isset($types[$mime]) || ($info[0]??0)>6000 || ($info[1]??0)>6000) {
        throw new RuntimeException('La imagen del producto no es válida.');
    }
    $relative='uploads/pedido_'.bin2hex(random_bytes(16)).'.'.$types[$mime];
    if(!move_uploaded_file($archivo['tmp_name'], dirname(__DIR__).'/'.$relative))
        throw new RuntimeException('No se pudo guardar la fotografía del pedido. Revisa la carpeta uploads.');
    return $relative;
}
