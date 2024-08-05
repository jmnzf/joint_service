<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class RegimenTributario extends REST_Controller {

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

if( !isset($Data['brt_codigo']) OR 
!isset($Data['brt_nombre']) OR 
!isset($Data['brt_fechainicio']) OR 
!isset($Data['brt_fechafin']) OR 
!isset($Data['brt_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO tbrt(brt_codigo, brt_nombre, brt_fechainicio, brt_fechafin, brt_status) VALUES(:brt_codigo, :brt_nombre, :brt_fechainicio, :brt_fechafin, :brt_status)', array(
':brt_codigo' => $Data['brt_codigo'], 
':brt_nombre' => $Data['brt_nombre'], 
':brt_fechainicio' => $Data['brt_fechainicio'], 
':brt_fechafin' => $Data['brt_fechafin'], 
':brt_status' => $Data['brt_status']));

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

if( !isset($Data['brt_codigo']) OR 
!isset($Data['brt_nombre']) OR 
!isset($Data['brt_fechainicio']) OR 
!isset($Data['brt_fechafin']) OR 
!isset($Data['brt_status']) OR 
!isset($Data['brt_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE tbrt SET brt_codigo = :brt_codigo , brt_nombre = :brt_nombre , brt_fechainicio = :brt_fechainicio , brt_fechafin = :brt_fechafin , brt_status = :brt_status  WHERE brt_id = :brt_id', array(
'brt_codigo' => $Data['brt_codigo'], 
'brt_nombre' => $Data['brt_nombre'], 
'brt_fechainicio' => $Data['brt_fechainicio'], 
'brt_fechafin' => $Data['brt_fechafin'], 
'brt_status' => $Data['brt_status'], 
'brt_id' => $Data['brt_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT brt_codigo , brt_nombre , brt_fechainicio , brt_fechafin , brt_id , CASE  WHEN brt_status::numeric = 1 THEN 'Activo' WHEN brt_status::numeric = 0 THEN 'Inactivo' END AS brt_status FROM tbrt ",array());

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