<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class DocumentNumbering extends REST_Controller {

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

  //Crear nueva numeracion
	public function createDocumentNumbering_post(){

      $Data = $this->post();

      if(!isset($Data['pgs_num_name']) OR
         !isset($Data['pgs_first_num']) OR
         !isset($Data['pgs_last_num']) OR
         !isset($Data['pgs_pref_num']) OR
         !isset($Data['pgs_cancel']) OR
         !isset($Data['pgs_is_due']) OR
         !isset($Data['pgs_doc_date']) OR
         !isset($Data['pgs_doc_due_date']) OR
         !isset($Data['pgs_enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      if(is_numeric($Data['pgs_first_num']) && is_numeric($Data['pgs_last_num'])){

        if($Data['pgs_first_num'] > $Data['pgs_last_num']){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'El numero inicial no puede ser mayor que el numero final'
              );

              $this->response($respuesta);

              return;

        }

      }else{
        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'Se requiere que el numero inicial y final sean validos'
          );

          $this->response($respuesta);

          return;
      }

      $sqlInsert = "INSERT INTO pgdn(pgs_id_doc_type, pgs_num_name, pgs_first_num, pgs_last_num, pgs_pref_num, pgs_cancel, pgs_is_due, pgs_doc_date,  pgs_doc_due_date, pgs_enabled, pgs_nextnum, pgs_doctype)
                    VALUES(:Pgs_IdDocType,  :Pgs_NumName,  :Pgs_FirstNum,  :Pgs_LastNum,  :Pgs_PrefNum,  :Pgs_Cancel,  :Pgs_IsDue,  :Pgs_DocDate,  :Pgs_DocDueDate,  :Pgs_Enabled, :pgs_nextnum, :pgs_doctype)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':Pgs_IdDocType'  => $Data['pgs_id_doc_type'],
            ':Pgs_NumName'    => $Data['pgs_num_name'],
            ':Pgs_FirstNum'   => $Data['pgs_first_num'],
            ':Pgs_LastNum'    => $Data['pgs_last_num'],
            ':Pgs_PrefNum'    => $Data['pgs_pref_num'],
            ':Pgs_Cancel'     => $Data['pgs_cancel'],
            ':Pgs_IsDue'      => $Data['pgs_is_due'],
            ':Pgs_DocDate'    => $Data['pgs_doc_date'],
            ':Pgs_DocDueDate' => $Data['pgs_doc_due_date'],
            ':Pgs_Enabled'    => $Data['pgs_enabled'],
						':pgs_nextnum'	  => 0,
						':pgs_doctype'		=> $Data['pgs_id_doc_type']

      ));

      if(is_numeric($resInsert) && $resInsert > 0){

            $respuesta = array(
              'error' 	=> false,
              'data' 		=> $resInsert,
              'mensaje' =>'Numeracion registrada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' 		=> $resInsert,
              'mensaje'	=> 'No se pudo registrar la numeracion'
            );

      }

       $this->response($respuesta);
	}

  //Actualizar numeracion de documento
  public function updateDocumentNumbering_post(){

      $Data = $this->post();

      if(!isset($Data['pgs_id_doc_type']) OR
         !isset($Data['pgs_num_name']) OR
         !isset($Data['pgs_first_num']) OR
         !isset($Data['pgs_last_num']) OR
         !isset($Data['pgs_pref_num']) OR
         !isset($Data['pgs_cancel']) OR
         !isset($Data['pgs_is_due']) OR
         !isset($Data['pgs_doc_date']) OR
         !isset($Data['pgs_doc_due_date']) OR
         !isset($Data['pgs_enabled']) OR
         !isset($Data['pgs_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      if(is_numeric($Data['pgs_first_num']) && is_numeric($Data['pgs_last_num'])){

        if($Data['pgs_first_num'] > $Data['pgs_last_num']){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'El numero inicial no puede ser mayor que el numero final'
              );

              $this->response($respuesta);

              return;

        }

      }else{
        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'Se requiere que el numero inicial y final sean validos'
          );

          $this->response($respuesta);

          return;
      }

      $sqlUpdate = "UPDATE pgdn SET pgs_id_doc_type = :Pgs_IdDocType, pgs_num_name = :Pgs_NumName, pgs_first_num = :Pgs_FirstNum,
                    pgs_last_num = :Pgs_LastNum, pgs_pref_num = :Pgs_PrefNum, pgs_cancel = :Pgs_Cancel, pgs_is_due = :Pgs_IsDue,
                    pgs_doc_date = :Pgs_DocDate, pgs_doc_due_date = :Pgs_DocDueDate, pgs_enabled = :Pgs_Enabled, pgs_doctype = :pgs_doctype
                    WHERE pgs_id = :Pgs_Id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':Pgs_IdDocType'  => $Data['pgs_id_doc_type'],
            ':Pgs_NumName'    => $Data['pgs_num_name'],
            ':Pgs_FirstNum'   => $Data['pgs_first_num'],
            ':Pgs_LastNum'    => $Data['pgs_last_num'],
            ':Pgs_PrefNum'    => $Data['pgs_pref_num'],
            ':Pgs_Cancel'     => $Data['pgs_cancel'],
            ':Pgs_IsDue'      => $Data['pgs_is_due'],
            ':Pgs_DocDate'    => $Data['pgs_doc_date'],
            ':Pgs_DocDueDate' => $Data['pgs_doc_due_date'],
            ':Pgs_Enabled'    => $Data['pgs_enabled'],
            ':Pgs_Id'         => $Data['pgs_id'],
						':pgs_doctype'		=> $Data['pgs_id_doc_type']

      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Numeracion actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la informacion de la numeracion'
            );

      }

       $this->response($respuesta);
  }

  // Obtener numeracion de documento
  public function getDocumentNumbering_get(){

        // $sqlSelect = " SELECT * FROM pgdn";
        $sqlSelect = "SELECT pgs_id, pgs_id_doc_type, pgs_num_name, pgs_first_num, pgs_last_num, pgs_pref_num, pgs_cancel,
        pgs_is_due, pgs_doc_date, pgs_doc_due_date, pgs_enabled, coalesce((select max(dvc_docnum)+1 ultimo_numero from dvct t0 where t0.dvc_series = pgs_id), pgs_first_num) as ultimo_numero FROM pgdn";

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

  // Obtener Tipo de Documento por Id
  public function getDocumentNumberingById_get(){

        $Data = $this->get();

        if(!isset($Data['pgs_id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM pgdn WHERE pgs_id = :Pgs_Id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':Pgs_Id' => $Data['Pgs_Id']));

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

  //Actualiza el estado de una moneda
   public function updateStatus_post(){

  	 			$Data = $this->post();

  				if(!isset($Data['pgs_id']) OR !isset($Data['pgs_enabled'])){

  						$respuesta = array(
  							'error' => true,
  							'data'  => array(),
  							'mensaje' =>'La informacion enviada no es valida'
  						);

  						$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
  						return;

  				}

  				$sqlUpdate = "UPDATE pgdn SET pgs_enabled = :Pgs_Enabled WHERE pgs_id = :Pgs_Id";


  				$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

  											':Pgs_Enabled' => $Data['pgs_enabled'],
  											':Pgs_Id'      => $Data['pgs_id']

  				));


  				if(is_numeric($resUpdate) && $resUpdate == 1){

  							$respuesta = array(
  								'error'   => false,
  								'data'    => $resUpdate,
  								'mensaje' =>'Numeracion actualizada con exito'
  							);


  				}else{

  							$respuesta = array(
  								'error'   => true,
  								'data'    => $resUpdate,
  								'mensaje'	=> 'No se pudo actualizar la informacion de la numeracion'
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
