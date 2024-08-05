<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class ResponsabilidadFiscal extends REST_Controller {

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

if( !isset($Data['brf_codigo']) OR 
!isset($Data['brf_nombre']) OR 
!isset($Data['brf_fechainicio']) OR 
!isset($Data['brf_fechafin']) OR 
!isset($Data['brf_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO tbrf(brf_codigo, brf_nombre, brf_fechainicio, brf_fechafin, brf_status) VALUES(:brf_codigo, :brf_nombre, :brf_fechainicio, :brf_fechafin, :brf_status)', array(
':brf_codigo' => $Data['brf_codigo'], 
':brf_nombre' => $Data['brf_nombre'], 
':brf_fechainicio' => $Data['brf_fechainicio'], 
':brf_fechafin' => $Data['brf_fechafin'], 
':brf_status' => $Data['brf_status']));

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

if( !isset($Data['brf_codigo']) OR 
!isset($Data['brf_nombre']) OR 
!isset($Data['brf_fechainicio']) OR 
!isset($Data['brf_fechafin']) OR 
!isset($Data['brf_status']) OR 
!isset($Data['brf_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE tbrf SET brf_codigo = :brf_codigo , brf_nombre = :brf_nombre , brf_fechainicio = :brf_fechainicio , brf_fechafin = :brf_fechafin , brf_status = :brf_status  WHERE brf_id = :brf_id', array(
'brf_codigo' => $Data['brf_codigo'], 
'brf_nombre' => $Data['brf_nombre'], 
'brf_fechainicio' => $Data['brf_fechainicio'], 
'brf_fechafin' => $Data['brf_fechafin'], 
'brf_status' => $Data['brf_status'], 
'brf_id' => $Data['brf_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT brf_codigo , brf_nombre , brf_fechainicio , brf_fechafin , brf_id , CASE  WHEN brf_status::numeric = 1 THEN 'Activo' WHEN brf_status::numeric = 0 THEN 'Inactivo' END AS brf_status FROM tbrf ",array());

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