<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Ubicaciones extends REST_Controller {

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

if( !isset($Data['ubc_type']) OR 
!isset($Data['ubc_code']) OR 
!isset($Data['ubc_alto_cm']) OR 
!isset($Data['ubc_ancho_cm']) OR 
!isset($Data['ubc_largo_cm']) OR 
!isset($Data['ubc_resistencia_kg']) OR 
!isset($Data['ubc_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO tubc(ubc_type, ubc_code, ubc_alto_cm, ubc_ancho_cm, ubc_largo_cm, ubc_resistencia_kg, ubc_status) VALUES(:ubc_type, :ubc_code, :ubc_alto_cm, :ubc_ancho_cm, :ubc_largo_cm, :ubc_resistencia_kg, :ubc_status)', array(
':ubc_type' => $Data['ubc_type'], 
':ubc_code' => $Data['ubc_code'], 
':ubc_alto_cm' => $Data['ubc_alto_cm'], 
':ubc_ancho_cm' => $Data['ubc_ancho_cm'], 
':ubc_largo_cm' => $Data['ubc_largo_cm'], 
':ubc_resistencia_kg' => $Data['ubc_resistencia_kg'], 
':ubc_status' => $Data['ubc_status']));

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

if( !isset($Data['ubc_type']) OR 
!isset($Data['ubc_code']) OR 
!isset($Data['ubc_alto_cm']) OR 
!isset($Data['ubc_ancho_cm']) OR 
!isset($Data['ubc_largo_cm']) OR 
!isset($Data['ubc_resistencia_kg']) OR 
!isset($Data['ubc_status']) OR 
!isset($Data['ubc_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE tubc SET ubc_type = :ubc_type , ubc_code = :ubc_code , ubc_alto_cm = :ubc_alto_cm , ubc_ancho_cm = :ubc_ancho_cm , ubc_largo_cm = :ubc_largo_cm , ubc_resistencia_kg = :ubc_resistencia_kg , ubc_status = :ubc_status  WHERE ubc_id = :ubc_id', array(
'ubc_type' => $Data['ubc_type'], 
'ubc_code' => $Data['ubc_code'], 
'ubc_alto_cm' => $Data['ubc_alto_cm'], 
'ubc_ancho_cm' => $Data['ubc_ancho_cm'], 
'ubc_largo_cm' => $Data['ubc_largo_cm'], 
'ubc_resistencia_kg' => $Data['ubc_resistencia_kg'], 
'ubc_status' => $Data['ubc_status'], 
'ubc_id' => $Data['ubc_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT ubc_type , ubc_code , ubc_alto_cm , ubc_ancho_cm , ubc_largo_cm , ubc_resistencia_kg , ubc_id , CASE  WHEN ubc_status::numeric = 1 THEN 'Activo' WHEN ubc_status::numeric = 0 THEN 'Inactivo' END AS ubc_status FROM tubc ",array());

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