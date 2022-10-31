<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Seguridadsocial extends REST_Controller {

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

if( !isset($Data['ass_operator']) OR 
!isset($Data['ass_riskcompany']) OR 
!isset($Data['ass_dateconst']) OR 
!isset($Data['ass_ley1607']) OR 
!isset($Data['ass_ley590']) OR 
!isset($Data['ass_ley1429']) OR 
!isset($Data['ass_ley558']) OR 
!isset($Data['ass_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO nass(ass_operator, ass_riskcompany, ass_dateconst, ass_ley1607, ass_ley590, ass_ley1429, ass_ley558, ass_status) VALUES(:ass_operator, :ass_riskcompany, :ass_dateconst, :ass_ley1607, :ass_ley590, :ass_ley1429, :ass_ley558, :ass_status)', array(
':ass_operator' => $Data['ass_operator'], 
':ass_riskcompany' => $Data['ass_riskcompany'], 
':ass_dateconst' => $Data['ass_dateconst'], 
':ass_ley1607' => $Data['ass_ley1607'], 
':ass_ley590' => $Data['ass_ley590'], 
':ass_ley1429' => $Data['ass_ley1429'], 
':ass_ley558' => $Data['ass_ley558'], 
':ass_status' => $Data['ass_status']));

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

if( !isset($Data['ass_operator']) OR 
!isset($Data['ass_riskcompany']) OR 
!isset($Data['ass_dateconst']) OR 
!isset($Data['ass_ley1607']) OR 
!isset($Data['ass_ley590']) OR 
!isset($Data['ass_ley1429']) OR 
!isset($Data['ass_ley558']) OR 
!isset($Data['ass_status']) OR 
!isset($Data['ass_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE nass SET ass_operator = :ass_operator , ass_riskcompany = :ass_riskcompany , ass_dateconst = :ass_dateconst , ass_ley1607 = :ass_ley1607 , ass_ley590 = :ass_ley590 , ass_ley1429 = :ass_ley1429 , ass_ley558 = :ass_ley558 , ass_status = :ass_status  WHERE ass_id = :ass_id', array(
'ass_operator' => $Data['ass_operator'], 
'ass_riskcompany' => $Data['ass_riskcompany'], 
'ass_dateconst' => $Data['ass_dateconst'], 
'ass_ley1607' => $Data['ass_ley1607'], 
'ass_ley590' => $Data['ass_ley590'], 
'ass_ley1429' => $Data['ass_ley1429'], 
'ass_ley558' => $Data['ass_ley558'], 
'ass_status' => $Data['ass_status'], 
'ass_id' => $Data['ass_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT naop.aop_name AS ass_operator, narc.arc_name AS ass_riskcompany, ass_dateconst , CASE  WHEN ass_ley1607::numeric = 1 THEN 'Si ' WHEN ass_ley1607::numeric = 0 THEN 'No' END AS ass_ley1607, CASE  WHEN ass_ley590::numeric = 1 THEN 'Si' WHEN ass_ley590::numeric = 0 THEN 'No' END AS ass_ley590, CASE  WHEN ass_ley1429::numeric = 1 THEN 'Si' WHEN ass_ley1429::numeric = 0 THEN 'No' END AS ass_ley1429, CASE  WHEN ass_ley558::numeric = 1 THEN 'Si' WHEN ass_ley558::numeric = 0 THEN 'No' END AS ass_ley558, ass_id , CASE  WHEN ass_status::numeric = 1 THEN 'Activo' WHEN ass_status::numeric = 0 THEN 'Inactivo' END AS ass_status FROM nass LEFT JOIN naop ON nass.ass_operator::numeric = naop.aop_id LEFT JOIN narc ON nass.ass_riskcompany::numeric = narc.arc_id ",array());

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