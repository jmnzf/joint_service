<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Puestodetrabajo extends REST_Controller {

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

if( !isset($Data['awp_empleado']) OR 
!isset($Data['awp_positionwork']) OR 
!isset($Data['awp_area']) OR 
!isset($Data['awp_sede']) OR 
!isset($Data['awp_costcenter']) OR 
!isset($Data['awp_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO nawp(awp_empleado, awp_positionwork, awp_area, awp_sede, awp_costcenter, awp_status) VALUES(:awp_empleado, :awp_positionwork, :awp_area, :awp_sede, :awp_costcenter, :awp_status)', array(
':awp_empleado' => $Data['awp_empleado'], 
':awp_positionwork' => $Data['awp_positionwork'], 
':awp_area' => $Data['awp_area'], 
':awp_sede' => $Data['awp_sede'], 
':awp_costcenter' => $Data['awp_costcenter'], 
':awp_status' => $Data['awp_status']));

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

if( !isset($Data['awp_empleado']) OR 
!isset($Data['awp_positionwork']) OR 
!isset($Data['awp_area']) OR 
!isset($Data['awp_sede']) OR 
!isset($Data['awp_costcenter']) OR 
!isset($Data['awp_status']) OR 
!isset($Data['awp_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE nawp SET awp_empleado = :awp_empleado , awp_positionwork = :awp_positionwork , awp_area = :awp_area , awp_sede = :awp_sede , awp_costcenter = :awp_costcenter , awp_status = :awp_status  WHERE awp_id = :awp_id', array(
'awp_empleado' => $Data['awp_empleado'], 
'awp_positionwork' => $Data['awp_positionwork'], 
'awp_area' => $Data['awp_area'], 
'awp_sede' => $Data['awp_sede'], 
'awp_costcenter' => $Data['awp_costcenter'], 
'awp_status' => $Data['awp_status'], 
'awp_id' => $Data['awp_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT napp.app_docnum AS awp_empleado, napn.apn_name AS awp_positionwork, naan.aan_name AS awp_area, nasn.asn_name AS awp_sede, awp_costcenter , awp_id , CASE  WHEN awp_status::numeric = 1 THEN 'Activo' WHEN awp_status::numeric = 0 THEN 'Inactivo' END AS awp_status FROM nawp LEFT JOIN napp ON nawp.awp_empleado::numeric = napp.app_id LEFT JOIN napn ON nawp.awp_positionwork::numeric = napn.apn_id LEFT JOIN naan ON nawp.awp_area::numeric = naan.aan_id LEFT JOIN nasn ON nawp.awp_sede::numeric = nasn.asn_id ",array());

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