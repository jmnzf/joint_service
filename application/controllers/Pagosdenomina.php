<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Pagosdenomina extends REST_Controller {

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

if( !isset($Data['app_empleado']) OR 
!isset($Data['app_frequency']) OR 
!isset($Data['app_paymethod']) OR 
!isset($Data['app_bankorigin']) OR 
!isset($Data['app_typeaccounto']) OR 
!isset($Data['app_numacoounto']) OR 
!isset($Data['app_bankdestiny']) OR 
!isset($Data['app_typeaccountd']) OR 
!isset($Data['app_numaccountd']) OR 
!isset($Data['app_status'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resInsert = $this->pedeo->insertRow('INSERT INTO napp1(app_empleado, app_frequency, app_paymethod, app_bankorigin, app_typeaccounto, app_numacoounto, app_bankdestiny, app_typeaccountd, app_numaccountd, app_status) VALUES(:app_empleado, :app_frequency, :app_paymethod, :app_bankorigin, :app_typeaccounto, :app_numacoounto, :app_bankdestiny, :app_typeaccountd, :app_numaccountd, :app_status)', array(
':app_empleado' => $Data['app_empleado'], 
':app_frequency' => $Data['app_frequency'], 
':app_paymethod' => $Data['app_paymethod'], 
':app_bankorigin' => $Data['app_bankorigin'], 
':app_typeaccounto' => $Data['app_typeaccounto'], 
':app_numacoounto' => $Data['app_numacoounto'], 
':app_bankdestiny' => $Data['app_bankdestiny'], 
':app_typeaccountd' => $Data['app_typeaccountd'], 
':app_numaccountd' => $Data['app_numaccountd'], 
':app_status' => $Data['app_status']));

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

if( !isset($Data['app_empleado']) OR 
!isset($Data['app_frequency']) OR 
!isset($Data['app_paymethod']) OR 
!isset($Data['app_bankorigin']) OR 
!isset($Data['app_typeaccounto']) OR 
!isset($Data['app_numacoounto']) OR 
!isset($Data['app_bankdestiny']) OR 
!isset($Data['app_typeaccountd']) OR 
!isset($Data['app_numaccountd']) OR 
!isset($Data['app_status']) OR 
!isset($Data['app_id'])){

$respuesta = array( 
'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
);

return $this->response($respuesta);
}

$resUpdate = $this->pedeo->updateRow('UPDATE napp1 SET app_empleado = :app_empleado , app_frequency = :app_frequency , app_paymethod = :app_paymethod , app_bankorigin = :app_bankorigin , app_typeaccounto = :app_typeaccounto , app_numacoounto = :app_numacoounto , app_bankdestiny = :app_bankdestiny , app_typeaccountd = :app_typeaccountd , app_numaccountd = :app_numaccountd , app_status = :app_status  WHERE app_id = :app_id', array(
'app_empleado' => $Data['app_empleado'], 
'app_frequency' => $Data['app_frequency'], 
'app_paymethod' => $Data['app_paymethod'], 
'app_bankorigin' => $Data['app_bankorigin'], 
'app_typeaccounto' => $Data['app_typeaccounto'], 
'app_numacoounto' => $Data['app_numacoounto'], 
'app_bankdestiny' => $Data['app_bankdestiny'], 
'app_typeaccountd' => $Data['app_typeaccountd'], 
'app_numaccountd' => $Data['app_numaccountd'], 
'app_status' => $Data['app_status'], 
'app_id' => $Data['app_id']));

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
$resSelect = $this->pedeo->queryTable("SELECT napp.app_docnum AS app_empleado, nafp.afp_name AS app_frequency, namp.amp_name AS app_paymethod, nabo.abo_name AS app_bankorigin, natc.atc_name AS app_typeaccounto,
app_numacoounto , nabd.abd_name AS app_bankdestiny, natcd.atc_name AS app_typeaccountd, app_numaccountd , napp1.app_id , 
CASE 
WHEN app_status::numeric = 1 THEN 'Activo' 
WHEN app_status::numeric = 0 THEN 'Inactivo' END AS app_status 
FROM napp1 
LEFT JOIN napp ON napp1.app_empleado::numeric = napp.app_id 
LEFT JOIN nafp ON napp1.app_frequency::numeric = nafp.afp_id 
LEFT JOIN namp ON napp1.app_paymethod::numeric = namp.amp_id 
LEFT JOIN nabo ON napp1.app_bankorigin::numeric = nabo.abo_id 
LEFT JOIN natc ON napp1.app_typeaccounto::numeric = natc.atc_id 
LEFT JOIN nabd ON napp1.app_bankdestiny::numeric = nabd.abd_id 
LEFT JOIN natc natcd ON napp1.app_typeaccountd::numeric = natcd.atc_id",array());

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