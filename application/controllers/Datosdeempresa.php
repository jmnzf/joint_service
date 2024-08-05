<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Datosdeempresa extends REST_Controller {

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

if( !isset($Data['aem_bussinesname']) OR 
!isset($Data['aem_mail']) OR 
!isset($Data['aem_doctype']) OR 
!isset($Data['aem_docnum']) OR 
!isset($Data['aem_phone']) OR 
!isset($Data['aem_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO naem(aem_bussinesname, aem_mail, aem_doctype, aem_docnum, aem_phone, aem_status) VALUES(:aem_bussinesname, :aem_mail, :aem_doctype, :aem_docnum, :aem_phone, :aem_status)', array(
':aem_bussinesname' => $Data['aem_bussinesname'], 
':aem_mail' => $Data['aem_mail'], 
':aem_doctype' => $Data['aem_doctype'], 
':aem_docnum' => $Data['aem_docnum'], 
':aem_phone' => $Data['aem_phone'], 
':aem_status' => $Data['aem_status']));

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

if( !isset($Data['aem_bussinesname']) OR 
!isset($Data['aem_mail']) OR 
!isset($Data['aem_doctype']) OR 
!isset($Data['aem_docnum']) OR 
!isset($Data['aem_phone']) OR 
!isset($Data['aem_status']) OR 
!isset($Data['aem_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE naem SET aem_bussinesname = :aem_bussinesname , aem_mail = :aem_mail , aem_doctype = :aem_doctype , aem_docnum = :aem_docnum , aem_phone = :aem_phone , aem_status = :aem_status  WHERE aem_id = :aem_id', array(
'aem_bussinesname' => $Data['aem_bussinesname'], 
'aem_mail' => $Data['aem_mail'], 
'aem_doctype' => $Data['aem_doctype'], 
'aem_docnum' => $Data['aem_docnum'], 
'aem_phone' => $Data['aem_phone'], 
'aem_status' => $Data['aem_status'], 
'aem_id' => $Data['aem_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT aem_bussinesname , aem_mail , nadc.adc_name AS aem_doctype, aem_docnum , aem_phone , aem_id , CASE  WHEN aem_status::numeric = 1 THEN 'Activo' WHEN aem_status::numeric = 0 THEN 'Inactivo' END AS aem_status FROM naem LEFT JOIN nadc ON naem.aem_doctype = nadc.adc_id ",array());

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