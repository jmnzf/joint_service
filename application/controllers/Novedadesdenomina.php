<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Novedadesdenomina extends REST_Controller {

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

if( !isset($Data['ann_personadenomina']) OR 
!isset($Data['ann_conceptodenovedad']) OR 
!isset($Data['ann_cantnovedad']) OR 
!isset($Data['ann_timenovedad']) OR 
!isset($Data['ann_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO nann(ann_personadenomina, ann_conceptodenovedad, ann_cantnovedad, ann_timenovedad, ann_status) VALUES(:ann_personadenomina, :ann_conceptodenovedad, :ann_cantnovedad, :ann_timenovedad, :ann_status)', array(
':ann_personadenomina' => $Data['ann_personadenomina'], 
':ann_conceptodenovedad' => $Data['ann_conceptodenovedad'], 
':ann_cantnovedad' => $Data['ann_cantnovedad'], 
':ann_timenovedad' => $Data['ann_timenovedad'], 
':ann_status' => $Data['ann_status']));

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

if( !isset($Data['ann_personadenomina']) OR 
!isset($Data['ann_conceptodenovedad']) OR 
!isset($Data['ann_cantnovedad']) OR 
!isset($Data['ann_timenovedad']) OR 
!isset($Data['ann_status']) OR 
!isset($Data['ann_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE nann SET ann_personadenomina = :ann_personadenomina , ann_conceptodenovedad = :ann_conceptodenovedad , ann_cantnovedad = :ann_cantnovedad , ann_timenovedad = :ann_timenovedad , ann_status = :ann_status  WHERE ann_id = :ann_id', array(
'ann_personadenomina' => $Data['ann_personadenomina'], 
'ann_conceptodenovedad' => $Data['ann_conceptodenovedad'], 
'ann_cantnovedad' => $Data['ann_cantnovedad'], 
'ann_timenovedad' => $Data['ann_timenovedad'], 
'ann_status' => $Data['ann_status'], 
'ann_id' => $Data['ann_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT napp.app_docnum AS ann_personadenomina, nalc.alc_name AS ann_conceptodenovedad, ann_cantnovedad , natn.atn_name AS ann_timenovedad, ann_id , CASE  WHEN ann_status::numeric = 1 THEN 'Activo' WHEN ann_status::numeric = 0 THEN 'Inactivo' END AS ann_status FROM nann LEFT JOIN napp ON nann.ann_personadenomina::numeric = napp.app_id LEFT JOIN nalc ON nann.ann_conceptodenovedad::numeric = nalc.alc_id LEFT JOIN natn ON nann.ann_timenovedad::numeric = natn.atn_id ",array());

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