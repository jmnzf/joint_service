<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Conceptosdecajamenor extends REST_Controller {

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

if( !isset($Data['ccm_name']) OR 
!isset($Data['ccm_description']) OR 
!isset($Data['ccm_contableaccount']) OR 
!isset($Data['ccm_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO tccm(ccm_name, ccm_description, ccm_contableaccount, ccm_status) VALUES(:ccm_name, :ccm_description, :ccm_contableaccount, :ccm_status)', array(
':ccm_name' => $Data['ccm_name'], 
':ccm_description' => $Data['ccm_description'], 
':ccm_contableaccount' => $Data['ccm_contableaccount'], 
':ccm_status' => $Data['ccm_status']));

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

if( !isset($Data['ccm_name']) OR 
!isset($Data['ccm_description']) OR 
!isset($Data['ccm_contableaccount']) OR 
!isset($Data['ccm_status']) OR 
!isset($Data['ccm_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE tccm SET ccm_name = :ccm_name , ccm_description = :ccm_description , ccm_contableaccount = :ccm_contableaccount , ccm_status = :ccm_status  WHERE ccm_id = :ccm_id', array(
'ccm_name' => $Data['ccm_name'], 
'ccm_description' => $Data['ccm_description'], 
'ccm_contableaccount' => $Data['ccm_contableaccount'], 
'ccm_status' => $Data['ccm_status'], 
'ccm_id' => $Data['ccm_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT ccm_name , ccm_description , naop.aop_name AS ccm_contableaccount, ccm_id , CASE  WHEN ccm_status::numeric = 1 THEN 'Activo' WHEN ccm_status::numeric = 0 THEN 'Inactivo' END AS ccm_status FROM tccm LEFT JOIN naop ON tccm.ccm_contableaccount::numeric = naop.aop_id ",array());

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