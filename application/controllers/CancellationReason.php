<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class CancellationReason extends REST_Controller {

private $pdo;
public function __construct(){
header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding');
header('Access-Control-Allow-Origin: *');
parent::__construct();$this->load->database();
$this->pdo = $this->load->database('pdo', true)->conn_id;
$this->load->library('pedeo', [$this->pdo]);
}



public function createCancellationReason_post(){

$Data = $this->post();

if(!isset($Data['pma_code']) OR 
!isset($Data['pma_description']) OR 
!isset($Data['pma_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO tpma(pma_code, pma_description, pma_status) VALUES(:pma_code, :pma_description, :pma_status)', array(
':pma_code' => $Data['pma_code'], 
':pma_description' => $Data['pma_description'], 
':pma_status' => $Data['pma_status']));

if ( is_numeric($resInsert) && $resInsert > 0) { 

    $respuesta = array( 
    'error'=>false,
    'data'=>$resInsert,
    'mensaje'=>'Registro insertado con exito'
    );

    return $this->response($respuesta);

} else { 

    $respuesta = array( 
    'error'=>true,
    'data'=>$resInsert,
    'mensaje'=>'Error al insertar el registro'
    );

    return $this->response($respuesta);

}


}



public function updateCancellationReason_post(){

$Data = $this->post();

if( !isset($Data['pma_code']) OR 
!isset($Data['pma_description']) OR 
!isset($Data['pma_status']) OR 
!isset($Data['pma_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE tpma SET pma_code = :pma_code , pma_description = :pma_description, pma_status = :pma_status  WHERE pma_id = :pma_id', array(
':pma_code' => $Data['pma_code'], 
':pma_description' => $Data['pma_description'], 
':pma_status' => $Data['pma_status'], 
':pma_id' => $Data['pma_id']));

if ( is_numeric($resUpdate) && $resUpdate == 1 ) { 

    $respuesta = array( 
    'error'=>false,
    'data'=>$resUpdate,
    'mensaje'=>'Registro actualizado con exito'
    );

return $this->response($respuesta);
} else { 

    $respuesta = array( 
    'error'=>true,
    'data'=>$resUpdate,
    'mensaje'=>'Error al actualizar el registro'
    );

return $this->response($respuesta);

}


}



public function getCancellationReason_get(){
$resSelect = $this->pedeo->queryTable("SELECT * FROM tpma",array());

if ( isset($resSelect[0]) ) { 

    $respuesta = array( 
    'error'=>false,
    'data'=>$resSelect,
    'mensaje'=>''
    );

    return $this->response($respuesta);
} else { 

    $respuesta = array( 
    'error'=>true,
    'data'=>$resSelect,
    'mensaje'=>'Busqueda sin resultados'
    );

    return $this->response($respuesta);

}
}


public function getCancellationReasonById_get(){
    $Data = $this->get();

    if(!isset($Data['pma_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }
    $resSelect = $this->pedeo->queryTable("SELECT * FROM tpma where pma_id = :pma_id",
                                array(":pma_id" => $Data['pma_id']));
    
    if ( isset($resSelect[0]) ) { 
    
        $respuesta = array( 
        'error'=>false,
        'data'=>$resSelect,
        'mensaje'=>''
        );
    
        return $this->response($respuesta);
    } else { 
    
        $respuesta = array( 
        'error'=>true,
        'data'=>$resSelect,
        'mensaje'=>'Busqueda sin resultados'
        );
    
        return $this->response($respuesta);
    
    }


}



}