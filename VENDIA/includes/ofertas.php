<?php
/** Reglas de las promociones, siempre calculadas de nuevo en el servidor. */
function etiquetaOferta(array $o): string {
    if (($o['tipo_beneficio'] ?? 'descuento') === 'promocion') {
        return (int)($o['unidades_llevas'] ?? 2).'x'.(int)($o['unidades_pagas'] ?? 1);
    }
    return number_format((float)($o['discount_percent'] ?? $o['porcentaje_descuento'] ?? 0), 2).'% de descuento';
}
/** Cada item es [precio_unitario, cantidad]. Los grupos N×M se forman por precio
 * descendente, cobrando los M más caros en cada grupo completo. Un grupo
 * incompleto se cobra a precio normal. Resultado en centavos para no perder precisión. */
function descuentoOfertaCentavos(array $oferta, array $items): int {
    $lineas=[]; $elegible=0;
    foreach ($items as $item) {
        $p=(int)round((float)$item[0]*100); $q=(int)$item[1];
        if ($p<0 || $q<1) continue;
        $lineas[]=[$p,$q]; $elegible += $p*$q;
    }
    if (!$lineas) return 0;
    if (($oferta['tipo_beneficio'] ?? 'descuento') !== 'promocion') {
        $porcentaje=(float)($oferta['discount_percent'] ?? $oferta['porcentaje_descuento'] ?? 0);
        return max(0,min($elegible,(int)round($elegible*$porcentaje/100)));
    }
    $n=(int)($oferta['unidades_llevas'] ?? 0);$m=(int)($oferta['unidades_pagas'] ?? 0);
    if ($n<2 || $n>10 || $m<1 || $m>=$n) return 0;
    usort($lineas,static fn($a,$b)=>$b[0]<=>$a[0]);
    $gratisAntes=static function(int $cuantas) use($n,$m):int {
        return intdiv($cuantas,$n)*($n-$m)+max(0,($cuantas%$n)-$m);
    };
    $offset=0;$ahorro=0;
    foreach($lineas as [$precio,$cantidad]) {
        $gratis=$gratisAntes($offset+$cantidad)-$gratisAntes($offset);
        $ahorro += $gratis*$precio; $offset += $cantidad;
    }
    // La última tanda incompleta no obtiene gratis aunque atraviese varios SKU.
    // Como gratisAntes() solo acredita unidades más allá de M, corregir ese tramo.
    $completas=intdiv($offset,$n)*$n;
    if ($completas<$offset) {
        $lineasR=$lineas;$pos=0;
        foreach($lineasR as [$precio,$cantidad]) {
            $a=max($completas,$pos);$b=min($offset,$pos+$cantidad);
            if($a<$b){$gratis=$gratisAntes($b)-$gratisAntes($a);$ahorro-=$precio*$gratis;}
            $pos+=$cantidad;
        }
    }
    return max(0,min($elegible,$ahorro));
}

/** Cotiza un pedido nuevo con los precios vigentes y la MEJOR oferta válida, sin acumular.
 * $lineas: arrays ['id'=>id_producto, 'precio'=>precio, 'cantidad'=>n].
 * Regresa montos en centavos para guardarlos como DECIMAL, sin depender del navegador.
 */
function cotizarPedido(PDO $pdo,int $negocioId,array $lineas): array {
    $subtotal=0; $porId=[];
    foreach($lineas as $l) {
        $id=(int)$l['id'];$qty=(int)$l['cantidad'];
        $price=(int)round((float)$l['precio']*100);
        if($id<1 || $qty<1 || $price<0)continue;
        $subtotal+=$price*$qty;
        $porId[$id]=[$price/100,$qty];
    }
    $mejor=['id_oferta'=>null,'subtotal_centavos'=>$subtotal,'descuento_centavos'=>0,'total_centavos'=>$subtotal,'nombre_oferta'=>'Sin oferta'];
    if(!$porId)return $mejor;
    $q=$pdo->prepare("SELECT p.id,p.nombre,p.tipo_beneficio,p.porcentaje_descuento,p.unidades_llevas,p.unidades_pagas,
       pp.id_producto FROM promociones p INNER JOIN promocion_productos pp ON pp.id_promocion=p.id
       INNER JOIN productos prod ON prod.id=pp.id_producto AND prod.id_negocio=p.id_negocio AND prod.activo=1
       WHERE p.id_negocio=? AND p.activo=1 AND p.autorizada=1
         AND p.fecha_inicio<=CURDATE() AND p.fecha_fin>=CURDATE()");
    $q->execute([$negocioId]);$ofertas=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $id=(int)$fila['id'];
        if(!isset($ofertas[$id]))$ofertas[$id]=['datos'=>$fila,'ids'=>[]];
        $ofertas[$id]['ids'][]=(int)$fila['id_producto'];
    }
    foreach($ofertas as $oferta) {
        $aplican=[];
        foreach($oferta['ids'] as $id)if(isset($porId[$id]))$aplican[]=$porId[$id];
        if(!$aplican)continue;
        $ahorro=descuentoOfertaCentavos($oferta['datos'],$aplican);
        if($ahorro>$mejor['descuento_centavos']) {
            $mejor['id_oferta']=(int)$oferta['datos']['id'];
            $mejor['descuento_centavos']=$ahorro;
            $mejor['total_centavos']=$subtotal-$ahorro;
            $mejor['nombre_oferta']=(string)$oferta['datos']['nombre'];
        }
    }
    return $mejor;
}
