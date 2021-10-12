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
	public function createPeriods_post(){

      $MESES = ['01','02','03','04','05','06','07','08','09','10','11','12'];

      $Data = $this->post();




      $sqlInsert = "INSERT INTO tbpc(bpc_code, bpc_name, bpc_type, bpc_period, bpc_fip, bpc_ffp, bpc_fid, bpc_ffd, bpc_fic, bpc_ffc, bpc_fiv, bpc_ffv)
            	      VALUES (:bpc_code, :bpc_name, :bpc_type, :bpc_period, :bpc_fip, :bpc_ffp, :bpc_fid, :bpc_ffd, :bpc_fic, :bpc_ffc, :bpc_fiv, :bpc_ffv)";

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
               ':bpc_ffv' => $this->validateDate($Data['bpc_ffv'])?$Data['bpc_ffv']:NULL
      ));


      if(is_numeric($resInsert) && $resInsert > 0){

          $sqlInsertDetail = "INSERT INTO bpc1(pc1_id, pc1_subperiod, pc1_fid, pc1_ffd, pc1_fic, pc1_ffc, pc1_fiv, pc1_ffv, pc1_status)
                              VALUES (:pc1_id, :pc1_subperiod, :pc1_fid, :pc1_ffd, :pc1_fic, :pc1_ffc, :pc1_fiv, :pc1_ffv, :pc1_status)";

          for ($i=0; $i < count($MESES); $i++) {

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

                    ':pc1_id' => $resInsert,
                    ':pc1_subperiod' => $Data['bpc_period'].'-'.$MESES[$i],
                    ':pc1_fid' => $Data['bpc_fid'],
                    ':pc1_ffd' => $Data['bpc_ffd'],
                    ':pc1_fic' => $Data['bpc_fic'],
                    ':pc1_ffc' => $Data['bpc_ffc'],
                    ':pc1_fiv' => $Data['bpc_fiv'],
                    ':pc1_ffv' => $Data['bpc_ffv'],
                    ':pc1_status' => 1

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

  //Actualizar Datos de Empresa
  public function updateCompany_post(){

      $DataCompany = $this->post();

      if(!isset($DataCompany['Pge_Id'])){

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
              'mensaje' =>'Empresa actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la empresa'
            );

      }

       $this->response($respuesta);
  }

  // OBTENER PERIODOS CONTABLES
  public function getPeriods_get(){

        $sqlSelect = "SELECT * FROM bpc1";

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



  private function validateDate($fecha){
      if(strlen($fecha) == 10 OR strlen($fecha) > 10){
        return true;
      }else{
        return false;
      }
  }


}
