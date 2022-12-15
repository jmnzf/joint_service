<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class TipoPersona extends REST_Controller {

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

if( !isset($Data['btp_codigo']) OR 
!isset($Data['btp_nombre']) OR 
!isset($Data['btp_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO tbtp(btp_codigo, btp_nombre, btp_status) VALUES(:btp_codigo, :btp_nombre, :btp_status)', array(
':btp_codigo' => $Data['btp_codigo'], 
':btp_nombre' => $Data['btp_nombre'], 
':btp_status' => $Data['btp_status']));

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

if( !isset($Data['btp_codigo']) OR 
!isset($Data['btp_nombre']) OR 
!isset($Data['btp_status']) OR 
!isset($Data['btp_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE tbtp SET btp_codigo = :btp_codigo , btp_nombre = :btp_nombre , btp_status = :btp_status  WHERE btp_id = :btp_id', array(
'btp_codigo' => $Data['btp_codigo'], 
'btp_nombre' => $Data['btp_nombre'], 
'btp_status' => $Data['btp_status'], 
'btp_id' => $Data['btp_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT btp_codigo , btp_nombre , btp_id , CASE  WHEN btp_status::numeric = 1 THEN 'Activo' WHEN btp_status::numeric = 0 THEN 'Inactivo' END AS btp_status FROM tbtp ",array());

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