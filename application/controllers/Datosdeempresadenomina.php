<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Datosdeempresadenomina extends REST_Controller {

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

if( !isset($Data['aen_bussinesname']) OR 
!isset($Data['aen_mail']) OR 
!isset($Data['aen_doctype']) OR 
!isset($Data['aen_docnum']) OR 
!isset($Data['aen_phone']) OR 
!isset($Data['aen_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO naen(aen_bussinesname, aen_mail, aen_doctype, aen_docnum, aen_phone, aen_status) VALUES(:aen_bussinesname, :aen_mail, :aen_doctype, :aen_docnum, :aen_phone, :aen_status)', array(
':aen_bussinesname' => $Data['aen_bussinesname'], 
':aen_mail' => $Data['aen_mail'], 
':aen_doctype' => $Data['aen_doctype'], 
':aen_docnum' => $Data['aen_docnum'], 
':aen_phone' => $Data['aen_phone'], 
':aen_status' => $Data['aen_status']));

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

if( !isset($Data['aen_bussinesname']) OR 
!isset($Data['aen_mail']) OR 
!isset($Data['aen_doctype']) OR 
!isset($Data['aen_docnum']) OR 
!isset($Data['aen_phone']) OR 
!isset($Data['aen_status']) OR 
!isset($Data['aen_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE naen SET aen_bussinesname = :aen_bussinesname , aen_mail = :aen_mail , aen_doctype = :aen_doctype , aen_docnum = :aen_docnum , aen_phone = :aen_phone , aen_status = :aen_status  WHERE aen_id = :aen_id', array(
'aen_bussinesname' => $Data['aen_bussinesname'], 
'aen_mail' => $Data['aen_mail'], 
'aen_doctype' => $Data['aen_doctype'], 
'aen_docnum' => $Data['aen_docnum'], 
'aen_phone' => $Data['aen_phone'], 
'aen_status' => $Data['aen_status'], 
'aen_id' => $Data['aen_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT aen_bussinesname , aen_mail , nadc.adc_name AS aen_doctype, aen_docnum , aen_phone , aen_id , CASE  WHEN aen_status::numeric = 1 THEN 'Activo' WHEN aen_status::numeric = 0 THEN 'Inactivo' END AS aen_status FROM naen LEFT JOIN nadc ON naen.aen_doctype::numeric = nadc.adc_id ",array());

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