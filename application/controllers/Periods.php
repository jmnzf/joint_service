<?php
// PERIODOS CONTABLES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Periods extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);
	}

  //CREAR NUEVO PERIODO CONTABLE
	public function createPeriods_post()
  {
    $Data = $this->post();
    //VALIDAR QUE EL PERIODO YA EXISTA EN LA BD
    $sqlValidate = "SELECT * FROM tbpc WHERE bpc_code = :bpc_code or (bpc_fip = :bpc_fip or bpc_ffp = :bpc_ffp)";
      $resSqlValidate = $this->pedeo->queryTable($sqlValidate,array(
        ':bpc_code' => $Data['bpc_code'],
        ':bpc_fip' => $Data['bpc_fip'],
        ':bpc_ffp' => $Data['bpc_ffp']
      ));

      if(count($resSqlValidate) > 0){
        $respuesta = array(
          'error'   => true,
          'data' 	 => array(),
          'mensaje' => 'El periodo contable ('.$resSqlValidate[0]['bpc_code'].' <-> '.$resSqlValidate[0]['bpc_fip'].' al '.$resSqlValidate[0]['bpc_ffp'].') ya existe'
        );
        return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);;

      }

    $typePeriod = 0;
    if($Data['bpc_type'] == 1){
      $typePeriod = 1;
    }else if($Data['bpc_type'] == 2){
      $typePeriod = 3;
    }else if($Data['bpc_type'] == 3){
      $typePeriod = 12;
    }
    $sqlInsert = "INSERT INTO tbpc(bpc_code, bpc_name, bpc_type, bpc_period, bpc_fip, bpc_ffp, bpc_fid, bpc_ffd, bpc_fic, bpc_ffc, bpc_fiv, bpc_ffv, bpc_createdby, bpc_createdat)
    VALUES (:bpc_code, :bpc_name, :bpc_type, :bpc_period, :bpc_fip, :bpc_ffp, :bpc_fid, :bpc_ffd, :bpc_fic, :bpc_ffc, :bpc_fiv, :bpc_ffv, :bpc_createdby, :bpc_createdat)";

    $this->pedeo->trans_begin();

    $resInsert = $this->pedeo->insertRow($sqlInsert, array(
      ':bpc_code' => isset($Data['bpc_code'])?$Data['bpc_code']:NULL,
      ':bpc_name' => isset($Data['bpc_name'])?$Data['bpc_name']:NULL,
      ':bpc_type' => is_numeric($Data['bpc_type'])?$Data['bpc_type']:0,
      ':bpc_period' => is_numeric($Data['bpc_period'])?$Data['bpc_period']:0,
      ':bpc_fip' => $this->validateDate($Data['bpc_fip'])?$Data['bpc_fip']:NULL,
      ':bpc_ffp' => $this->validateDate($Data['bpc_ffp'])?$Data['bpc_ffp']:NULL,
      ':bpc_fid' => $this->validateDate($Data['bpc_fid'])?$Data['bpc_fid']:NULL,
      ':bpc_ffd' => $this->validateDate($Data['bpc_ffd'])?$Data['bpc_ffd']:NULL,
      ':bpc_fic' => $this->validateDate($Data['bpc_fic'])?$Data['bpc_fic']:NULL,
      ':bpc_ffc' => $this->validateDate($Data['bpc_ffc'])?$Data['bpc_ffc']:NULL,
      ':bpc_fiv' => $this->validateDate($Data['bpc_fiv'])?$Data['bpc_fiv']:NULL,
      ':bpc_ffv' => $this->validateDate($Data['bpc_ffv'])?$Data['bpc_ffv']:NULL,
      ':bpc_createdby' => isset($Data['bpc_createdby'])?$Data['bpc_createdby']:NULL,
      ':bpc_createdat' => date('Y-m-d')
    ));


    if(is_numeric($resInsert) && $resInsert > 0){

      $sqlInsertDetail = "INSERT INTO bpc1(pc1_subperiod, pc1_fid, pc1_ffd, pc1_fic, pc1_ffc, pc1_fiv, pc1_ffv, pc1_status,pc1_period_id)
      VALUES (:pc1_subperiod, :pc1_fid, :pc1_ffd, :pc1_fic, :pc1_ffc, :pc1_fiv, :pc1_ffv, :pc1_status, :pc1_period_id)";

    for ($i=0; $i <= $typePeriod - 1; $i++) {
      $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
      ':pc1_subperiod' => date('Y-m',strtotime($Data['bpc_fid']."+ {$i} month")),
      ':pc1_fid' => $this->incrementarFecha("{$i} month",$Data['bpc_fid'],true) ,
      ':pc1_ffd' =>  $this->incrementarFecha("{$i} month",$Data['bpc_fid'],false),
      ':pc1_fic' => $this->incrementarFecha("{$i} month",$Data['bpc_fid'],true),
      ':pc1_ffc' => $this->incrementarFecha("{$i} month",$Data['bpc_fid'],false),
      ':pc1_fiv' => $this->incrementarFecha("{$i} month",$Data['bpc_fid'],true),
      ':pc1_ffv' => $this->incrementarFecha("{$i} month",$Data['bpc_fid'],false),
      ':pc1_status' => 1,
      ':pc1_period_id' => $resInsert
    ));

    if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
     }else{
      
      $this->pedeo->trans_rollback();

      $respuesta = array(
        'error'   => true,
        'data' 	 => $resInsertDetail,
        'mensaje' => 'No se pudo registrar el periodo contable'
      );

      $this->response($respuesta);

      return;

    }
  }

  $this->pedeo->trans_commit();

  $respuesta = array(
    'error'	 => false,
    'data' 	 => $resInsert,
    'mensaje' =>'Periodo contable registrado con exito'
  );
  }else{
    
    $this->pedeo->trans_rollback();

		$respuesta = array(
		  'error'   => true,
			'data' 		=> $resInsert,
			'mensaje' => 'No se pudo registrar el periodo contable'
    );

	}
  $this->response($respuesta);
	}

  //Actualizar Periodo contable
  public function updatePeriods_post(){

      $Data = $this->post();

      if(!isset($Data['bpc_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE tbpc	SET  bpc_code = :bpc_code, bpc_name = :bpc_name, bpc_type = :bpc_type,
                    bpc_period = :bpc_period, bpc_fip = :bpc_fip, bpc_ffp = :bpc_ffp, bpc_fid = :bpc_fid,
                    bpc_ffd = :bpc_ffd, bpc_fic = :bpc_fic, bpc_ffc = :bpc_ffc, bpc_fiv = :bpc_fiv,
                    bpc_ffv = :bpc_ffv	WHERE bpc_id =:bpc_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':bpc_code' => isset($Data['bpc_code'])?$Data['bpc_code']:NULL,
              ':bpc_name' => isset($Data['bpc_name'])?$Data['bpc_name']:NULL,
              ':bpc_type' => is_numeric($Data['bpc_type'])?$Data['bpc_type']:0,
              ':bpc_period' => is_numeric($Data['bpc_period'])?$Data['bpc_period']:0,
              ':bpc_fip' => $this->validateDate($Data['bpc_fip'])?$Data['bpc_fip']:NULL,
              ':bpc_ffp' => $this->validateDate($Data['bpc_ffp'])?$Data['bpc_ffp']:NULL,
              ':bpc_fid' => $this->validateDate($Data['bpc_fid'])?$Data['bpc_fid']:NULL,
              ':bpc_ffd' => $this->validateDate($Data['bpc_ffd'])?$Data['bpc_ffd']:NULL,
              ':bpc_fic' => $this->validateDate($Data['bpc_fic'])?$Data['bpc_fic']:NULL,
              ':bpc_ffc' => $this->validateDate($Data['bpc_ffc'])?$Data['bpc_ffc']:NULL,
              ':bpc_fiv' => $this->validateDate($Data['bpc_fiv'])?$Data['bpc_fiv']:NULL,
              ':bpc_ffv' => $this->validateDate($Data['bpc_ffv'])?$Data['bpc_ffv']:NULL,
              ':bpc_id'  => $Data['bpc_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Periodo actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el periodo'
            );

      }

       $this->response($respuesta);
  }

  // OBTENER CABECERA DE PERIDO CONTABLE POR ID
  public function getPeriodById_get(){

    $Data = $this->get();
    if(!isset($Data['bpc_id'])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = " SELECT * FROM tbpc WHERE bpc_id = :bpc_id";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':bpc_id' => $Data['bpc_id']));

    if(isset($resSelect[0])){

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    }else{

        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );

    }

     $this->response($respuesta);
  }

  // OBTENER PERIODOS CONTABLES
  public function getPeriods_get(){

        $sqlSelect = "SELECT * FROM tbpc";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if(isset($resSelect[0])){

          $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => '');

        }else{

            $respuesta = array(
              'error'   => true,
              'data' => array(),
              'mensaje'	=> 'busqueda sin resultados'
            );

        }

         $this->response($respuesta);
  }

  public function updateStatus_post()
  {
    $Data = $this->post();

      if(!isset($Data['pc1_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

    $sqlUpdate = "UPDATE bpc1	SET pc1_status = :pc1_status,
                  pc1_fid = :pc1_fid,
                  pc1_ffd = :pc1_ffd,
                  pc1_fic = :pc1_fic,
                  pc1_ffc = :pc1_ffc,
                  pc1_fiv = :pc1_fiv,
                  pc1_ffv = :pc1_ffv
                  WHERE pc1_id = :pc1_id";

    $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
                                      ":pc1_fid" => $Data['pc1_fid'],
                                      ":pc1_ffd" => $Data['pc1_ffd'],
                                      ":pc1_fic" => $Data['pc1_fic'],
                                      ":pc1_ffc" => $Data['pc1_ffc'],
                                      ":pc1_fiv" => $Data['pc1_fiv'],
                                      ":pc1_ffv" => $Data['pc1_ffv'],
                                      ":pc1_status"  => $Data['pc1_status'],
                                      ":pc1_id" =>$Data['pc1_id']));

    if (is_numeric($resUpdate) && $resUpdate == 1) {

      $respuesta = array(
        'error' => false,
        'data' => $resUpdate,
        'mensaje' => 'Periodo actualizado con exito'
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data' => $resUpdate,
        'mensaje'  => 'No se pudo actualizar el periodo'
      );
    }

    $this->response($respuesta);
  }

  // CREAR SUBPERIODO SOLO

  public function createSubPeriod_post() {

    $Data = $this->post();


    $sqlInsert = "INSERT INTO bpc1(pc1_subperiod, pc1_fid, pc1_ffd, pc1_fic, pc1_ffc, pc1_fiv, pc1_ffv, pc1_status,pc1_period_id)
                  VALUES (:pc1_subperiod, :pc1_fid, :pc1_ffd, :pc1_fic, :pc1_ffc, :pc1_fiv, :pc1_ffv, :pc1_status, :pc1_period_id)";


    $resInsert = $this->pedeo->insertRow($sqlInsert, array(
      
      ':pc1_subperiod'  => $Data['pc1_subperiod'],
      ':pc1_fid'  => $Data['pc1_fid'],
      ':pc1_ffd'  => $Data['pc1_ffd'],
      ':pc1_fic'  => $Data['pc1_fic'],
      ':pc1_ffc'  => $Data['pc1_ffc'],
      ':pc1_fiv'  => $Data['pc1_fiv'],
      ':pc1_ffv'  => $Data['pc1_ffv'],
      ':pc1_status'  => $Data['pc1_status'],
      ':pc1_period_id' => $Data['pc1_period_id']
    ));


    if ( is_numeric($resInsert) && $resInsert > 0 ) {

      $respuesta = array(
        'error'   => false,
        'data'    => $resInsert,
        'mensaje' => 'Subperiodo creado con exito'
      );

    } else {

      $respuesta = array(
        'error' => true,
        'data' => $resInsert,
        'mensaje' => 'No se pudo crear el subperiodo'
      );

    }

    $this->response($respuesta);
  }

  private function validateDate($fecha){
      if(strlen($fecha) == 10 OR strlen($fecha) > 10){
        return true;
      }else{
        return false;
      }
  }

  private function incrementarFecha($add,$startdate,$bool){

    $date = !empty($startdate) ? $startdate : date('Y-m-d');
    $newDate = strtotime($add,strtotime($date)) ;
    $newDate = ($bool) ? date('Y-m-d',$newDate) : date('Y-m-t',$newDate);

    return $newDate;
  }


}
