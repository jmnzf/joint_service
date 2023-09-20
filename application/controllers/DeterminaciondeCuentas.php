<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class DeterminaciondeCuentas extends REST_Controller {

private $pdo;
public function __construct(){
header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding');
header('Access-Control-Allow-Origin: *');
parent::__construct();$this->load->database();
$this->pdo = $this->load->database('pdo', true)->conn_id;
$this->load->library('pedeo', [$this->pdo]);
}



public function create_post(){

$Data = $this->post();

if( !isset($Data['dct_descripcion']) OR 
!isset($Data['dct_acc_balance']) OR 
!isset($Data['dct_acc_compesacion_adquisicion']) OR 
!isset($Data['dct_acc_reserva']) OR 
!isset($Data['dct_compesacion_ingreso_rev']) OR 
!isset($Data['dct_amortizacion_normal']) OR 
!isset($Data['dct_amortizacion_acumulada']) OR 
!isset($Data['dct_amortizacion_no_plan']) OR 
!isset($Data['dct_amortizacion_no_prev']) OR 
!isset($Data['dct_amortizacion_especial']) OR 
!isset($Data['dct_amortizacion_especial_ac']) OR 
!isset($Data['dct_ingreso_baja_activos']) OR 
!isset($Data['dct_baja_gastos']) OR 
!isset($Data['dct_baja_ingreso']) OR 
!isset($Data['dct_gastos_baja_valor_contable']) OR 
!isset($Data['dct_cuenta_ingreso_baja']) OR 
!isset($Data['dct_cuenta_compesacion_ingreso'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO ddct(dct_descripcion, dct_acc_balance, dct_acc_compesacion_adquisicion, dct_acc_reserva, dct_compesacion_ingreso_rev, dct_amortizacion_normal, dct_amortizacion_acumulada, dct_amortizacion_no_plan, dct_amortizacion_no_prev, dct_amortizacion_especial, dct_amortizacion_especial_ac, dct_ingreso_baja_activos, dct_baja_gastos, dct_baja_ingreso, dct_gastos_baja_valor_contable, dct_cuenta_ingreso_baja, dct_cuenta_compesacion_ingreso) VALUES(:dct_descripcion, :dct_acc_balance, :dct_acc_compesacion_adquisicion, :dct_acc_reserva, :dct_compesacion_ingreso_rev, :dct_amortizacion_normal, :dct_amortizacion_acumulada, :dct_amortizacion_no_plan, :dct_amortizacion_no_prev, :dct_amortizacion_especial, :dct_amortizacion_especial_ac, :dct_ingreso_baja_activos, :dct_baja_gastos, :dct_baja_ingreso, :dct_gastos_baja_valor_contable, :dct_cuenta_ingreso_baja, :dct_cuenta_compesacion_ingreso)', array(
':dct_descripcion' => $Data['dct_descripcion'], 
':dct_acc_balance' => $Data['dct_acc_balance'], 
':dct_acc_compesacion_adquisicion' => $Data['dct_acc_compesacion_adquisicion'], 
':dct_acc_reserva' => $Data['dct_acc_reserva'], 
':dct_compesacion_ingreso_rev' => $Data['dct_compesacion_ingreso_rev'], 
':dct_amortizacion_normal' => $Data['dct_amortizacion_normal'], 
':dct_amortizacion_acumulada' => $Data['dct_amortizacion_acumulada'], 
':dct_amortizacion_no_plan' => $Data['dct_amortizacion_no_plan'], 
':dct_amortizacion_no_prev' => $Data['dct_amortizacion_no_prev'], 
':dct_amortizacion_especial' => $Data['dct_amortizacion_especial'], 
':dct_amortizacion_especial_ac' => $Data['dct_amortizacion_especial_ac'], 
':dct_ingreso_baja_activos' => $Data['dct_ingreso_baja_activos'], 
':dct_baja_gastos' => $Data['dct_baja_gastos'], 
':dct_baja_ingreso' => $Data['dct_baja_ingreso'], 
':dct_gastos_baja_valor_contable' => $Data['dct_gastos_baja_valor_contable'], 
':dct_cuenta_ingreso_baja' => $Data['dct_cuenta_ingreso_baja'], 
':dct_cuenta_compesacion_ingreso' => $Data['dct_cuenta_compesacion_ingreso']));

if ( is_numeric($resInsert) && $resInsert > 0) { 

$respuesta = array( 
'error'=>false,'data'=>$resInsert,'mensaje'=>'Registro insertado con exito'
);

return $this->response($respuesta);
} else { 

$respuesta = array( 
'error'=>true,'data'=>$resInsert,'mensaje'=>'Error al insertar el registro'
);

return $this->response($respuesta);

}


}



public function update_post(){

$Data = $this->post();

if( !isset($Data['dct_descripcion']) OR 
!isset($Data['dct_acc_balance']) OR 
!isset($Data['dct_acc_compesacion_adquisicion']) OR 
!isset($Data['dct_acc_reserva']) OR 
!isset($Data['dct_compesacion_ingreso_rev']) OR 
!isset($Data['dct_amortizacion_normal']) OR 
!isset($Data['dct_amortizacion_acumulada']) OR 
!isset($Data['dct_amortizacion_no_plan']) OR 
!isset($Data['dct_amortizacion_no_prev']) OR 
!isset($Data['dct_amortizacion_especial']) OR 
!isset($Data['dct_amortizacion_especial_ac']) OR 
!isset($Data['dct_ingreso_baja_activos']) OR 
!isset($Data['dct_baja_gastos']) OR 
!isset($Data['dct_baja_ingreso']) OR 
!isset($Data['dct_gastos_baja_valor_contable']) OR 
!isset($Data['dct_cuenta_ingreso_baja']) OR 
!isset($Data['dct_cuenta_compesacion_ingreso']) OR 
!isset($Data['dct_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE ddct SET dct_descripcion = :dct_descripcion , dct_acc_balance = :dct_acc_balance , dct_acc_compesacion_adquisicion = :dct_acc_compesacion_adquisicion , dct_acc_reserva = :dct_acc_reserva , dct_compesacion_ingreso_rev = :dct_compesacion_ingreso_rev , dct_amortizacion_normal = :dct_amortizacion_normal , dct_amortizacion_acumulada = :dct_amortizacion_acumulada , dct_amortizacion_no_plan = :dct_amortizacion_no_plan , dct_amortizacion_no_prev = :dct_amortizacion_no_prev , dct_amortizacion_especial = :dct_amortizacion_especial , dct_amortizacion_especial_ac = :dct_amortizacion_especial_ac , dct_ingreso_baja_activos = :dct_ingreso_baja_activos , dct_baja_gastos = :dct_baja_gastos , dct_baja_ingreso = :dct_baja_ingreso , dct_gastos_baja_valor_contable = :dct_gastos_baja_valor_contable , dct_cuenta_ingreso_baja = :dct_cuenta_ingreso_baja , dct_cuenta_compesacion_ingreso = :dct_cuenta_compesacion_ingreso  WHERE dct_id = :dct_id', array(
'dct_descripcion' => $Data['dct_descripcion'], 
'dct_acc_balance' => $Data['dct_acc_balance'], 
'dct_acc_compesacion_adquisicion' => $Data['dct_acc_compesacion_adquisicion'], 
'dct_acc_reserva' => $Data['dct_acc_reserva'], 
'dct_compesacion_ingreso_rev' => $Data['dct_compesacion_ingreso_rev'], 
'dct_amortizacion_normal' => $Data['dct_amortizacion_normal'], 
'dct_amortizacion_acumulada' => $Data['dct_amortizacion_acumulada'], 
'dct_amortizacion_no_plan' => $Data['dct_amortizacion_no_plan'], 
'dct_amortizacion_no_prev' => $Data['dct_amortizacion_no_prev'], 
'dct_amortizacion_especial' => $Data['dct_amortizacion_especial'], 
'dct_amortizacion_especial_ac' => $Data['dct_amortizacion_especial_ac'], 
'dct_ingreso_baja_activos' => $Data['dct_ingreso_baja_activos'], 
'dct_baja_gastos' => $Data['dct_baja_gastos'], 
'dct_baja_ingreso' => $Data['dct_baja_ingreso'], 
'dct_gastos_baja_valor_contable' => $Data['dct_gastos_baja_valor_contable'], 
'dct_cuenta_ingreso_baja' => $Data['dct_cuenta_ingreso_baja'], 
'dct_cuenta_compesacion_ingreso' => $Data['dct_cuenta_compesacion_ingreso'], 
'dct_id' => $Data['dct_id']));

if ( is_numeric($resUpdate) && $resUpdate == 1 ) { 

$respuesta = array( 
'error'=>false,'data'=>$resUpdate,'mensaje'=>'Registro actualizado con exito'
);

return $this->response($respuesta);
} else { 

$respuesta = array( 
'error'=>true,'data'=>$resUpdate,'mensaje'=>'Error al actualizar el registro'
);

return $this->response($respuesta);

}


}



public function index_get(){
$resSelect = $this->pedeo->queryTable("SELECT dct_descripcion , dct_acc_balance , dct_acc_compesacion_adquisicion , dct_acc_reserva , dct_compesacion_ingreso_rev , dct_amortizacion_normal , dct_amortizacion_acumulada , dct_amortizacion_no_plan , dct_amortizacion_no_prev , dct_amortizacion_especial , dct_amortizacion_especial_ac , dct_ingreso_baja_activos , dct_baja_gastos , dct_baja_ingreso , dct_gastos_baja_valor_contable , dct_cuenta_ingreso_baja , dct_cuenta_compesacion_ingreso , dct_id  FROM ddct ",array());

if ( isset($resSelect[0]) ) { 

$respuesta = array( 
'error'=>false,'data'=>$resSelect,'mensaje'=>''
);

return $this->response($respuesta);
} else { 

$respuesta = array( 
'error'=>true,'data'=>$resSelect,'mensaje'=>'Busqueda sin resultados'
);

return $this->response($respuesta);

}


}



}