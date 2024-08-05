<?php
// DATOS MAESTROS ALMACEN
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Warehouse extends REST_Controller {

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

  //CREAR NUEVO ALMACEN
	public function createWarehouse_post(){

      $Data = $this->post();

      if(!isset($Data['dws_code']) OR
         !isset($Data['dws_name']) OR
         !isset($Data['dws_ubication']) OR
         !isset($Data['dws_acctin']) OR
         !isset($Data['dws_acct_out']) OR
         !isset($Data['dws_acct_stockn']) OR
         !isset($Data['dws_acct_stockp']) OR
         !isset($Data['dws_acct_redu']) OR
         !isset($Data['dws_acct_amp']) OR
         !isset($Data['dws_acct_cost']) OR
         !isset($Data['dws_enabled']) OR
         !isset($Data['dws_acct_return']) OR
         !isset($Data['business']) OR
         !isset($Data['dws_acct_inv'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlInsert = "INSERT INTO dmws(dws_code, dws_name, dws_ubication, dws_acctin, dws_acct_out, dws_acct_stockn, dws_acct_stockp, dws_acct_redu,
                      dws_acct_amp, dws_acct_cost, dws_enabled, dws_acct_return, dws_acct_inv, business,dws_acct_invproc,deductible_spent,taxable_income)VALUES (:dws_code, :dws_name, :dws_ubication, :dws_acctin, :dws_acct_out,
                      :dws_acct_stockn, :dws_acct_stockp, :dws_acct_redu, :dws_acct_amp, :dws_acct_cost, :dws_enabled, :dws_acct_return, :dws_acct_inv, :business,:dws_acct_invproc,:deductible_spent,:taxable_income)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

              ':dws_code' => $Data['dws_code'],
              ':dws_name' => $Data['dws_name'],
              ':dws_ubication' => $Data['dws_ubication'],
              ':dws_acctin' => $Data['dws_acctin'],
              ':dws_acct_out' => $Data['dws_acct_out'],
              ':dws_acct_stockn' => $Data['dws_acct_stockn'],
              ':dws_acct_stockp' => $Data['dws_acct_stockp'],
              ':dws_acct_redu' => $Data['dws_acct_redu'],
              ':dws_acct_amp' => $Data['dws_acct_amp'],
              ':dws_acct_cost' => $Data['dws_acct_cost'],
              ':dws_enabled' => $Data['dws_enabled'],
              ':dws_acct_return' => $Data['dws_acct_return'],
              ':business' => $Data['business'],
              ':dws_acct_inv' => $Data['dws_acct_inv'],
              ':dws_acct_invproc' => $Data['dws_acct_invproc'],
              ':deductible_spent' => isset($Data['deductible_spent']) ? $Data['deductible_spent'] : 0,
              ':taxable_income' => isset($Data['taxable_income']) ? $Data['taxable_income'] : 0
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

              $respuesta = array(
                'error' 	=> false,
                'data' 		=> $resInsert,
                'mensaje' =>'Almacen registrado con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje'	=> 'No se pudo registrar el almacen'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR ALMACEN
  public function updateWarehouse_post(){

      $Data = $this->post();

      if(!isset($Data['dws_code']) OR
         !isset($Data['dws_name']) OR
         !isset($Data['dws_acctin']) OR
         !isset($Data['dws_acct_out']) OR
         !isset($Data['dws_acct_inv']) OR
         !isset($Data['dws_acct_stockn']) OR
         !isset($Data['dws_acct_stockp']) OR
         !isset($Data['dws_acct_redu']) OR
         !isset($Data['dws_acct_amp']) OR
         !isset($Data['dws_acct_cost']) OR
         !isset($Data['dws_enabled']) OR
         !isset($Data['dws_acct_return']) OR
         !isset($Data['business']) OR
         !isset($Data['dws_id'])){



        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE dmws SET dws_id =:dws_id, dws_code = :dws_code, dws_name = :dws_name, dws_acctin = :dws_acctin,
                    dws_acct_out = :dws_acct_out, dws_acct_stockn = :dws_acct_stockn, dws_acct_stockp = :dws_acct_stockp,
                    dws_acct_redu = :dws_acct_redu, dws_acct_amp = :dws_acct_amp, dws_acct_cost = :dws_acct_cost, dws_enabled = :dws_enabled,
                    dws_acct_return = :dws_acct_return, dws_acct_inv = :dws_acct_inv, business = :business,dws_acct_invproc = :dws_acct_invproc ,
                    deductible_spent = :deductible_spent , taxable_income = :taxable_income
                    WHERE dws_id = :dws_id";

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':dws_code' => $Data['dws_code'],
              ':dws_name' => $Data['dws_name'],
              ':dws_acctin' => $Data['dws_acctin'],
              ':dws_acct_out' => $Data['dws_acct_out'],
              ':dws_acct_stockn' => $Data['dws_acct_stockn'],
              ':dws_acct_stockp' => $Data['dws_acct_stockp'],
              ':dws_acct_redu' => $Data['dws_acct_redu'],
              ':dws_acct_amp' => $Data['dws_acct_amp'],
              ':dws_acct_cost' => $Data['dws_acct_cost'],
              ':dws_enabled' => $Data['dws_enabled'],
              ':dws_acct_return' => $Data['dws_acct_return'],
              ':dws_acct_inv' => $Data['dws_acct_inv'],
              ':business' => $Data['business'],
              ':dws_id' => $Data['dws_id'],
              ':dws_acct_invproc' => $Data['dws_acct_invproc'],
              ':deductible_spent' => isset($Data['deductible_spent']) ? $Data['deductible_spent'] : 0,
              ':taxable_income' => isset($Data['taxable_income']) ? $Data['taxable_income'] : 0
      ));
      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Almacen actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el almacen'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER ALMACENES
  public function getWarehouse_get(){

        $Data = $this->get();

        if(!isset($Data['business'])){
          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT dmws.*, pgem.pge_small_name AS empresanombre FROM dmws LEFT JOIN pgem ON pgem.pge_id = business WHERE business = :business";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':business' => $Data['business']));

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

  // OBTENER ALMACENEN POR ID
  public function getWarehouseById_get(){

        $Data = $this->get();

        if( !isset($Data['dws_id']) OR !isset($Data['dws_id']) ){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmws WHERE dws_id = :dws_id ";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dws_id' => $Data['dws_id']));

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
  // OBTENER ALMACENEN QUE MANEJAN UBICACION
  public function getWarehouseByUbication_get(){

        $Data = $this->get();

        if( !isset($Data['business']) ){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }


        $sqlSelect = " SELECT dws_code AS id, dws_name AS text FROM dmws WHERE dws_ubication = 1 AND dws_enabled = 1 AND business = :business";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':business' => $Data['business']));
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
