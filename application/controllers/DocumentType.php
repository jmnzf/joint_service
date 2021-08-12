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

      if(!isset($DataDocument['Pgs_NumName']) OR
         !isset($DataDocument['Pgs_Enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }
      
      $sqlInsert = "INSERT INTO gdct(pgs_num_name, pgs_enabled)
                   VALUES(:Pgs_NumName, :Pgs_Enabled)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':Pgs_NumName' => $DataDocument['Pgs_NumName'],
            ':Pgs_Enabled' => 1,

      ));

      if($resInsert > 0 ){

            $respuesta = array(
              'error' => false,
              'data' => $resInsert,
              'mensaje' =>'Documento registrado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' => array(),
              'mensaje'	=> 'No se pudo registrar el documento'
            );

      }

       $this->response($respuesta);
	}

  //Actualizar moneda
  public function updateDocumentType_post(){

      $DataDocument = $this->post();

      if(!isset($DataDocument['Pgs_NumName']) OR
         !isset($DataDocument['Pgs_Enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE gdct SET pgs_num_name = :Pgs_NumName, pgs_enabled = :Pgs_Enabled
                    WHERE pgs_id = :Pgs_Id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':Pgs_NumName' => $DataDocument['Pgs_NumName'],
            ':Pgs_Enabled' => $DataDocument['Pgs_Enabled'],
            ':Pgs_Id'      => $DataDocument['Pgs_Id']
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

        $sqlSelect = "SELECT * FROM gdct";

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

        $sqlSelect = " SELECT * FROM gdct WHERE pgs_id = :Pgs_Id";

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

  				if(!isset($Data['Pgs_Id']) OR !isset($Data['Pgs_Enabled'])){

  						$respuesta = array(
  							'error' => true,
  							'data'  => array(),
  							'mensaje' =>'La informacion enviada no es valida'
  						);

  						$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
  						return;
  				}

  				$sqlUpdate = "UPDATE gdct SET pgs_enabled = :Pgs_Enabled WHERE pgs_id = :Pgs_Id";


  				$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

  											':Pgs_Enabled' => $Data['Pgs_Enabled'],
  											':Pgs_Id'      => $Data['Pgs_Id']

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
