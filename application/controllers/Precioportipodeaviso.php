<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Precioportipodeaviso extends REST_Controller {

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

if( !isset($Data['pta_tipoaviso']) OR 
!isset($Data['pta_precio']) OR 
!isset($Data['pta_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO tpta(pta_tipoaviso, pta_precio, pta_status) VALUES(:pta_tipoaviso, :pta_precio, :pta_status)', array(
':pta_tipoaviso' => $Data['pta_tipoaviso'], 
':pta_precio' => $Data['pta_precio'], 
':pta_status' => $Data['pta_status']));

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

if( !isset($Data['pta_tipoaviso']) OR 
!isset($Data['pta_precio']) OR 
!isset($Data['pta_status']) OR 
!isset($Data['pta_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE tpta SET pta_tipoaviso = :pta_tipoaviso , pta_precio = :pta_precio , pta_status = :pta_status  WHERE pta_id = :pta_id', array(
'pta_tipoaviso' => $Data['pta_tipoaviso'], 
'pta_precio' => $Data['pta_precio'], 
'pta_status' => $Data['pta_status'], 
'pta_id' => $Data['pta_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT ttav.tav_name AS pta_tipoaviso, pta_precio , pta_id , CASE  WHEN pta_status::numeric = 1 THEN 'Activo' WHEN pta_status::numeric = 0 THEN 'Inactivo' END AS pta_status FROM tpta LEFT JOIN ttav ON tpta.pta_tipoaviso::numeric = ttav.tav_id ",array());

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