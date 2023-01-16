<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class TipodeAnuncios extends REST_Controller {

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

if( !isset($Data['bta_name']) OR 
!isset($Data['bta_combo']) OR 
!isset($Data['bta_dias']) OR 
!isset($Data['bta_precio']) OR 
!isset($Data['bta_palabras']) OR 
!isset($Data['bta_letras']) OR 
!isset($Data['bta_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO tbta(bta_name, bta_combo, bta_dias, bta_precio, bta_palabras, bta_letras, bta_status) VALUES(:bta_name, :bta_combo, :bta_dias, :bta_precio, :bta_palabras, :bta_letras, :bta_status)', array(
':bta_name' => $Data['bta_name'], 
':bta_combo' => $Data['bta_combo'], 
':bta_dias' => $Data['bta_dias'], 
':bta_precio' => $Data['bta_precio'], 
':bta_palabras' => $Data['bta_palabras'], 
':bta_letras' => $Data['bta_letras'], 
':bta_status' => $Data['bta_status']));

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

if( !isset($Data['bta_name']) OR 
!isset($Data['bta_combo']) OR 
!isset($Data['bta_dias']) OR 
!isset($Data['bta_precio']) OR 
!isset($Data['bta_palabras']) OR 
!isset($Data['bta_letras']) OR 
!isset($Data['bta_status']) OR 
!isset($Data['bta_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE tbta SET bta_name = :bta_name , bta_combo = :bta_combo , bta_dias = :bta_dias , bta_precio = :bta_precio , bta_palabras = :bta_palabras , bta_letras = :bta_letras , bta_status = :bta_status  WHERE bta_id = :bta_id', array(
'bta_name' => $Data['bta_name'], 
'bta_combo' => $Data['bta_combo'], 
'bta_dias' => $Data['bta_dias'], 
'bta_precio' => $Data['bta_precio'], 
'bta_palabras' => $Data['bta_palabras'], 
'bta_letras' => $Data['bta_letras'], 
'bta_status' => $Data['bta_status'], 
'bta_id' => $Data['bta_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT bta_name , CASE  WHEN bta_combo::numeric = 1 THEN 'Si' WHEN bta_combo::numeric = 0 THEN 'No' END AS bta_combo, bta_dias , bta_precio , bta_palabras , bta_letras , bta_id , CASE  WHEN bta_status::numeric = 1 THEN 'Activo' WHEN bta_status::numeric = 0 THEN 'Inactivo' END AS bta_status FROM tbta ",array());

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