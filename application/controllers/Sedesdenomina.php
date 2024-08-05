<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Sedesdenomina extends REST_Controller {

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

if( !isset($Data['asn_name']) OR 
!isset($Data['asn_departmentsede']) OR 
!isset($Data['asn_city']) OR 
!isset($Data['asn_adress']) OR 
!isset($Data['asn_workcenter']) OR 
!isset($Data['asn_prefixne']) OR 
!isset($Data['asn_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO nasn(asn_name, asn_departmentsede, asn_city, asn_adress, asn_workcenter, asn_prefixne, asn_status) VALUES(:asn_name, :asn_departmentsede, :asn_city, :asn_adress, :asn_workcenter, :asn_prefixne, :asn_status)', array(
':asn_name' => $Data['asn_name'], 
':asn_departmentsede' => $Data['asn_departmentsede'], 
':asn_city' => $Data['asn_city'], 
':asn_adress' => $Data['asn_adress'], 
':asn_workcenter' => $Data['asn_workcenter'], 
':asn_prefixne' => $Data['asn_prefixne'], 
':asn_status' => $Data['asn_status']));

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

if( !isset($Data['asn_name']) OR 
!isset($Data['asn_departmentsede']) OR 
!isset($Data['asn_city']) OR 
!isset($Data['asn_adress']) OR 
!isset($Data['asn_workcenter']) OR 
!isset($Data['asn_prefixne']) OR 
!isset($Data['asn_status']) OR 
!isset($Data['asn_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE nasn SET asn_name = :asn_name , asn_departmentsede = :asn_departmentsede , asn_city = :asn_city , asn_adress = :asn_adress , asn_workcenter = :asn_workcenter , asn_prefixne = :asn_prefixne , asn_status = :asn_status  WHERE asn_id = :asn_id', array(
'asn_name' => $Data['asn_name'], 
'asn_departmentsede' => $Data['asn_departmentsede'], 
'asn_city' => $Data['asn_city'], 
'asn_adress' => $Data['asn_adress'], 
'asn_workcenter' => $Data['asn_workcenter'], 
'asn_prefixne' => $Data['asn_prefixne'], 
'asn_status' => $Data['asn_status'], 
'asn_id' => $Data['asn_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT asn_name , nadn.adn_name AS asn_departmentsede, nacn.acn_name AS asn_city, asn_adress , nawc.awc_name AS asn_workcenter, asn_prefixne , asn_id , CASE  WHEN asn_status::numeric = 1 THEN 'Activo' WHEN asn_status::numeric = 0 THEN 'Inactivo' END AS asn_status FROM nasn LEFT JOIN nadn ON nasn.asn_departmentsede::numeric = nadn.adn_id LEFT JOIN nacn ON nasn.asn_city::numeric = nacn.acn_id LEFT JOIN nawc ON nasn.asn_workcenter::numeric = nawc.awc_id ",array());

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