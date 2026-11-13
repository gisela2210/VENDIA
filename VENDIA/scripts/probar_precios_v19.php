<?php
// Pruebas aisladas de cálculos sin abrir ni alterar la base de datos.
require dirname(__DIR__).'/includes/ofertas.php';
class VendiaTestStmt extends PDOStatement {
    private array $rows=[];
    public function __construct(array $rows){$this->rows=$rows;}
    public function execute(?array $params=null):bool{return true;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT, mixed ...$args):array{return $this->rows;}
}
class VendiaTestPdo extends PDO {
    public array $rows=[];
    public function __construct(array $rows){$this->rows=$rows;}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new VendiaTestStmt($this->rows);}
}
function check(int $actual,int $expected,string $msg):void {
    if ($actual!==$expected) throw new RuntimeException("{$msg}: obtenido {$actual}, esperado {$expected}");
    echo "OK {$msg}\n";
}
check(descuentoOfertaCentavos(['tipo_beneficio'=>'descuento','porcentaje_descuento'=>20],[[100,2]]),4000,'20% de dos artículos de 100');
check(descuentoOfertaCentavos(['tipo_beneficio'=>'promocion','unidades_llevas'=>2,'unidades_pagas'=>1],[[100,1],[30,1]]),3000,'2x1 cobra el más caro');
check(descuentoOfertaCentavos(['tipo_beneficio'=>'promocion','unidades_llevas'=>3,'unidades_pagas'=>1],[[100,1],[40,1],[20,1]]),6000,'3x1 cobra el más caro');
check(descuentoOfertaCentavos(['tipo_beneficio'=>'promocion','unidades_llevas'=>2,'unidades_pagas'=>1],[[30,1]]),0,'sin grupo completo no hay descuento');
$rows=[
 ['id'=>11,'nombre'=>'Rebaja 20','tipo_beneficio'=>'descuento','porcentaje_descuento'=>20,'unidades_llevas'=>null,'unidades_pagas'=>null,'id_producto'=>1],
 ['id'=>12,'nombre'=>'2x1','tipo_beneficio'=>'promocion','porcentaje_descuento'=>0,'unidades_llevas'=>2,'unidades_pagas'=>1,'id_producto'=>1],
];
$cot=cotizarPedido(new VendiaTestPdo($rows),1,[['id'=>1,'precio'=>100,'cantidad'=>2]]);
check($cot['subtotal_centavos'],20000,'subtotal cotizado');
check($cot['descuento_centavos'],10000,'elige la mejor promoción');
check($cot['total_centavos'],10000,'total cotizado se conserva');
$cot=cotizarPedido(new VendiaTestPdo($rows),1,[['id'=>1,'precio'=>100,'cantidad'=>1]]);
check($cot['descuento_centavos'],2000,'aplica 20% cuando el 2x1 está incompleto');
