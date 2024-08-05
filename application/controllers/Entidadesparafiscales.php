<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Entidadesparafiscales extends REST_Controller {

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

if( !isset($Data['aee_empleado']) OR 
!isset($Data['aee_eps']) OR 
!isset($Data['aee_fpensiones']) OR 
!isset($Data['aee_fcensatias']) OR 
!isset($Data['aee_boxcompensation']) OR 
!isset($Data['aee_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO naee(aee_empleado, aee_eps, aee_fpensiones, aee_fcensatias, aee_boxcompensation, aee_status) VALUES(:aee_empleado, :aee_eps, :aee_fpensiones, :aee_fcensatias, :aee_boxcompensation, :aee_status)', array(
':aee_empleado' => $Data['aee_empleado'], 
':aee_eps' => $Data['aee_eps'], 
':aee_fpensiones' => $Data['aee_fpensiones'], 
':aee_fcensatias' => $Data['aee_fcensatias'], 
':aee_boxcompensation' => $Data['aee_boxcompensation'], 
':aee_status' => $Data['aee_status']));

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

if( !isset($Data['aee_empleado']) OR 
!isset($Data['aee_eps']) OR 
!isset($Data['aee_fpensiones']) OR 
!isset($Data['aee_fcensatias']) OR 
!isset($Data['aee_boxcompensation']) OR 
!isset($Data['aee_status']) OR 
!isset($Data['aee_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE naee SET aee_empleado = :aee_empleado , aee_eps = :aee_eps , aee_fpensiones = :aee_fpensiones , aee_fcensatias = :aee_fcensatias , aee_boxcompensation = :aee_boxcompensation , aee_status = :aee_status  WHERE aee_id = :aee_id', array(
'aee_empleado' => $Data['aee_empleado'], 
'aee_eps' => $Data['aee_eps'], 
'aee_fpensiones' => $Data['aee_fpensiones'], 
'aee_fcensatias' => $Data['aee_fcensatias'], 
'aee_boxcompensation' => $Data['aee_boxcompensation'], 
'aee_status' => $Data['aee_status'], 
'aee_id' => $Data['aee_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT napp.app_docnum AS aee_empleado, naes.aes_name AS aee_eps, naep.aep_name AS aee_fpensiones, naec.aec__name AS aee_fcensatias, nabc.abc_name AS aee_boxcompensation, aee_id , CASE  WHEN aee_status::numeric = 1 THEN 'Activo' WHEN aee_status::numeric = 0 THEN 'Inactivo' END AS aee_status FROM naee LEFT JOIN napp ON naee.aee_empleado::numeric = napp.app_id LEFT JOIN naes ON naee.aee_eps::numeric = naes.aes_id LEFT JOIN naep ON naee.aee_fpensiones::numeric = naep.aep_id LEFT JOIN naec ON naee.aee_fcensatias::numeric = naec.aec_id LEFT JOIN nabc ON naee.aee_boxcompensation::numeric = nabc.abc_id ",array());

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