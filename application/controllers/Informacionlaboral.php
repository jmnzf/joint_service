<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Informacionlaboral extends REST_Controller {

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

if( !isset($Data['ail_empleado']) OR 
!isset($Data['ail_contracttype']) OR 
!isset($Data['ail_contractterm']) OR 
!isset($Data['ail_temporality']) OR 
!isset($Data['ail_datein']) OR 
!isset($Data['ail_basicsalary']) OR 
!isset($Data['ail_subsidy']) OR 
!isset($Data['ail_dayslicence']) OR 
!isset($Data['ail_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO nail(ail_empleado, ail_contracttype, ail_contractterm, ail_temporality, ail_datein, ail_basicsalary, ail_subsidy, ail_dayslicence, ail_status) VALUES(:ail_empleado, :ail_contracttype, :ail_contractterm, :ail_temporality, :ail_datein, :ail_basicsalary, :ail_subsidy, :ail_dayslicence, :ail_status)', array(
':ail_empleado' => $Data['ail_empleado'], 
':ail_contracttype' => $Data['ail_contracttype'], 
':ail_contractterm' => $Data['ail_contractterm'], 
':ail_temporality' => $Data['ail_temporality'], 
':ail_datein' => $Data['ail_datein'], 
':ail_basicsalary' => $Data['ail_basicsalary'], 
':ail_subsidy' => $Data['ail_subsidy'], 
':ail_dayslicence' => $Data['ail_dayslicence'], 
':ail_status' => $Data['ail_status']));

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

if( !isset($Data['ail_empleado']) OR 
!isset($Data['ail_contracttype']) OR 
!isset($Data['ail_contractterm']) OR 
!isset($Data['ail_temporality']) OR 
!isset($Data['ail_datein']) OR 
!isset($Data['ail_basicsalary']) OR 
!isset($Data['ail_subsidy']) OR 
!isset($Data['ail_dayslicence']) OR 
!isset($Data['ail_status']) OR 
!isset($Data['ail_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE nail SET ail_empleado = :ail_empleado , ail_contracttype = :ail_contracttype , ail_contractterm = :ail_contractterm , ail_temporality = :ail_temporality , ail_datein = :ail_datein , ail_basicsalary = :ail_basicsalary , ail_subsidy = :ail_subsidy , ail_dayslicence = :ail_dayslicence , ail_status = :ail_status  WHERE ail_id = :ail_id', array(
'ail_empleado' => $Data['ail_empleado'], 
'ail_contracttype' => $Data['ail_contracttype'], 
'ail_contractterm' => $Data['ail_contractterm'], 
'ail_temporality' => $Data['ail_temporality'], 
'ail_datein' => $Data['ail_datein'], 
'ail_basicsalary' => $Data['ail_basicsalary'], 
'ail_subsidy' => $Data['ail_subsidy'], 
'ail_dayslicence' => $Data['ail_dayslicence'], 
'ail_status' => $Data['ail_status'], 
'ail_id' => $Data['ail_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT napp.app_docnum AS ail_empleado, nact.act_contractype AS ail_contracttype, natc1.atc_name AS ail_contractterm, natp.atp_name AS ail_temporality, ail_datein , ail_basicsalary , CASE  WHEN ail_subsidy::numeric = 1 THEN 'Si' WHEN ail_subsidy::numeric = 0 THEN 'No' END AS ail_subsidy, CASE  WHEN ail_dayslicence::numeric = 1 THEN 'Lunes' WHEN ail_dayslicence::numeric = 2 THEN 'Martes' WHEN ail_dayslicence::numeric = 3 THEN 'Miercoles' WHEN ail_dayslicence::numeric = 4 THEN 'Jueves' WHEN ail_dayslicence::numeric = 5 THEN 'Viernes' WHEN ail_dayslicence::numeric = 6 THEN 'Sabado' WHEN ail_dayslicence::numeric = 7 THEN 'Domingo' END AS ail_dayslicence, ail_id , CASE  WHEN ail_status::numeric = 1 THEN 'Activo' WHEN ail_status::numeric = 0 THEN 'Inactivo' END AS ail_status FROM nail LEFT JOIN napp ON nail.ail_empleado::numeric = napp.app_id LEFT JOIN nact ON nail.ail_contracttype::numeric = nact.act_id LEFT JOIN natc1 ON nail.ail_contractterm::numeric = natc1.atc_id LEFT JOIN natp ON nail.ail_temporality::numeric = natp.atp_id ",array());

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