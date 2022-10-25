<?php
// FACTURA DE COMPRAS
defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class ImportSettings extends REST_Controller {

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

    public  function getImportSettings_get(){
        $sqlSelect = "SELECT * FROM tbif";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		if (isset($resSelect[0])) {
			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		}
		$this->response($respuesta);
    }

    public function createImportSetting_post(){
        $Data = $this->post();

      if(!isset($Data['bif_table']) OR
         !isset($Data['bif_description']) OR
         !isset($Data['bif_status'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlInsert = "INSERT INTO tbif (bif_table,bif_description,bif_status)
                      VALUES(:bif_table, :bif_description, :bif_status)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':bif_table'    => $Data['bif_table'],
              ':bif_description'    => $Data['bif_description'],
              ':bif_status'    => $Data['bif_status']
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

            $respuesta = array(
              'error'		=> false,
              'data' 		=> $resInsert,
              'mensaje' =>'Configuración registrada con exito'
            );

         }else{

           $respuesta = array(
             'error'   => true,
             'data' 	 => $resInsert,
             'mensaje' => 'No se pudo registrar la cConfiguración'
           );

         }

         $this->response($respuesta);
    }

    public function updateImportSetting_post(){
        $Data = $this->post();

      if(!isset($Data['bif_table']) OR
         !isset($Data['bif_description']) OR
         !isset($Data['bif_id']) OR
         !isset($Data['bif_status'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlUpdate = "UPDATE tbif SET
                bif_table = :bif_table,
                bif_description = :bif_description,
                bif_status = :bif_status
                where bif_id = :bif_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':bif_table'    => $Data['bif_table'],
              ':bif_description'    => $Data['bif_description'],
              ':bif_status'    => $Data['bif_status'],
              ':bif_id'    => $Data['bif_id']
        ));

        if(is_numeric($resUpdate) && $resUpdate > 0){

            $respuesta = array(
              'error'		=> false,
              'data' 		=> $resUpdate,
              'mensaje' =>'Configuración actualizada con exito'
            );

         }else{

           $respuesta = array(
             'error'   => true,
             'data' 	 => $resUpdate,
             'mensaje' => 'No se pudo actualizar la cConfiguración'
           );

         }

         $this->response($respuesta);
    }


}