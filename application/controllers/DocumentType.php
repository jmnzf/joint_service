<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class DocumentType extends REST_Controller {

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

  //Crear nuevo documento
	public function createDocumentType_post(){

      $DataDocument = $this->post();

      if(!isset($DataDocument['mdt_doctype']) OR
         !isset($DataDocument['mdt_docname'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

			$sqlDocument = " SELECT * FROM dmdt WHERE mdt_doctype = :mdt_doctype";
			$resDocument = $this->pedeo->queryTable($sqlDocument, array(':mdt_doctype' => $DataDocument['mdt_doctype']));

			if(isset($resDocument[0])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'Ya existe un documento con este tipo documento '.$DataDocument['mdt_doctype']
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

      $sqlInsert = "INSERT INTO dmdt(mdt_doctype, mdt_docname, mdt_enabled)
                    VALUES(:mdt_doctype, :mdt_docname, :mdt_enabled)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':mdt_doctype' => $DataDocument['mdt_doctype'],
            ':mdt_docname' => $DataDocument['mdt_docname'],
						':mdt_enabled' => 1

      ));

      if(is_numeric($resInsert) && $resInsert > 0){

            $respuesta = array(
              'error' 	=> false,
              'data' 		=> $resInsert,
              'mensaje' =>'Documento registrado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' 		=> $resInsert,
              'mensaje'	=> 'No se pudo registrar el documento'
            );

      }

       $this->response($respuesta);
	}

  //Actualizar moneda
  public function updateDocumentType_post(){

      $DataDocument = $this->post();

      if(!isset($DataDocument['mdt_doctype']) OR
         !isset($DataDocument['mdt_docname']) OR
				 !isset($DataDocument['mdt_id']) OR
			 	 !isset($DataDocument['mdt_enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

			$sqlDocument = " SELECT * FROM dmdt WHERE mdt_doctype = :mdt_doctype AND mdt_id = :mdt_id";
			$resDocument = $this->pedeo->queryTable($sqlDocument, array(
									':mdt_doctype' => $DataDocument['mdt_doctype'],
									':mdt_id' => $DataDocument['mdt_id']
			));


			if(isset($resDocument[0])){

			}else{
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se puede actualizar un documento con un tipo de documento que ya existe en otro.'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

      $sqlUpdate = "UPDATE dmdt SET mdt_doctype = :mdt_doctype, mdt_docname = :mdt_docname,
										mdt_enabled = :mdt_enabled
                    WHERE mdt_id = :mdt_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':mdt_doctype' => $DataDocument['mdt_doctype'],
            ':mdt_docname' => $DataDocument['mdt_docname'],
						':mdt_enabled' => $DataDocument['mdt_enabled'],
            ':mdt_id'      => $DataDocument['mdt_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Documento actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la informacion del documento'
            );

      }

       $this->response($respuesta);
  }

  // Obtener tipo de Documentos
  public function getDocumentType_get(){

        $sqlSelect = "SELECT * FROM dmdt WHERE mdt_enabled = 1";

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
  public function getDocumentTypeById_get(){

        $Data = $this->get();

        if(!isset($Data['Pgs_Id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmdt WHERE mdt_id = :mdt_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':mdt_id' => $Data['mdt_id']));

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

  				if(!isset($Data['mdt_id']) OR !isset($Data['mdt_enabled'])){

  						$respuesta = array(
  							'error' => true,
  							'data'  => array(),
  							'mensaje' =>'La informacion enviada no es valida'
  						);

  						$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
  						return;
  				}

  				$sqlUpdate = "UPDATE dmdt SET mdt_enabled = :mdt_enabled WHERE mdt_id = :mdt_id";


  				$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

  											':mdt_enabled' => $Data['mdt_enabled'],
  											':mdt_id'      => $Data['mdt_id']

  				));


  				if(is_numeric($resUpdate) && $resUpdate == 1){

  							$respuesta = array(
  								'error'   => false,
  								'data'    => $resUpdate,
  								'mensaje' =>'Documento actualizado con exito'
  							);


  				}else{

  							$respuesta = array(
  								'error'   => true,
  								'data'    => $resUpdate,
  								'mensaje'	=> 'No se pudo actualizar la informacion del documento'
  							);

  				}

  				 $this->response($respuesta);

   }





}
