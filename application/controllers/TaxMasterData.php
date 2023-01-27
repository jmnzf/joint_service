<?php
// DATOS MAESTROS DE IMPUESTOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class TaxMasterData extends REST_Controller {

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

  //CREAR NUEVO IMPUESTO
	public function createTaxMasterData_post(){

      $Data = $this->post();

      if(!isset($Data['dmi_code']) OR
         !isset($Data['dmi_name_tax']) OR
         !isset($Data['dmi_rate_tax']) OR
         !isset($Data['dmi_type']) OR
         !isset($Data['dmi_acctcode']) OR
         !isset($Data['dmi_enable'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlInsert = "INSERT INTO dmtx(dmi_code, dmi_name_tax, dmi_rate_tax, dmi_type, dmi_enable, dmi_acctcode, dmi_use_fc, dmi_rate_fc)
                      VALUES(:dmi_code, :dmi_name_tax, :dmi_rate_tax, :dmi_type, :dmi_enable, :dmi_acctcode, :dmi_use_fc, :dmi_rate_fc)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':dmi_code' => $Data['dmi_code'],
              ':dmi_name_tax' => $Data['dmi_name_tax'],
              ':dmi_rate_tax' => $Data['dmi_rate_tax'],
              ':dmi_type' => $Data['dmi_type'],
              ':dmi_enable' => $Data['dmi_enable'],
              ':dmi_use_fc' => $Data['dmi_use_fc'],
              ':dmi_rate_fc' => is_numeric($Data['dmi_rate_fc']) ? $Data['dmi_rate_fc'] : 0,
              ':dmi_acctcode' => $Data['dmi_acctcode']

        ));

        if(is_numeric($resInsert) && $resInsert > 0){

              $respuesta = array(
                'error' 	=> false,
                'data' 		=> $resInsert,
                'mensaje' =>'Impuesto registrado con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje'	=> 'No se pudo registrar el impuesto'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR IMPUESTO
  public function updateTaxMasterData_post(){

      $Data = $this->post();

      if(!isset($Data['dmi_code']) OR
         !isset($Data['dmi_name_tax']) OR
         !isset($Data['dmi_rate_tax']) OR
         !isset($Data['dmi_type']) OR
         !isset($Data['dmi_enable']) OR
         !isset($Data['dmi_acctcode']) OR
         !isset($Data['dmi_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE dmtx SET  dmi_acctcode = :dmi_acctcode, dmi_code = :dmi_code, dmi_name_tax = :dmi_name_tax, dmi_rate_tax = :dmi_rate_tax, dmi_type = :dmi_type,
                    dmi_enable = :dmi_enable, dmi_rate_fc = :dmi_rate_fc, dmi_use_fc = :dmi_use_fc WHERE dmi_id = :dmi_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ':dmi_code' => $Data['dmi_code'],
            ':dmi_name_tax' => $Data['dmi_name_tax'],
            ':dmi_rate_tax' => $Data['dmi_rate_tax'],
            ':dmi_type' => $Data['dmi_type'],
            ':dmi_enable' => $Data['dmi_enable'],
            ':dmi_acctcode' => $Data['dmi_acctcode'],
            ':dmi_use_fc' => $Data['dmi_use_fc'],
            ':dmi_rate_fc' => is_numeric($Data['dmi_rate_fc']) ? $Data['dmi_rate_fc'] : 0,
            ':dmi_id' => $Data['dmi_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Impuesto actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el impuesto'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER IMPUESTOS
  public function getTaxMasterData_get(){

        $sqlSelect = " SELECT * FROM dmtx";

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




}
