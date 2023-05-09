<?php
// DATOS MAESTROS ACIENTOS CONTABLES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Tasa extends REST_Controller {

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

  //CREAR NUEVA TASA
	public function createTasa_post(){

        $Data = $this->post();
        if(empty($Data)){
          $Data = $_POST;
        }

        $sqlTasa = "SELECT * FROM tasa WHERE tsa_date = :tsa_date AND tsa_curro = :tsa_curro AND tsa_currd = :tsa_currd";

        $resTasa = $this->pedeo->queryTable($sqlTasa, array(
																':tsa_curro' => $Data['tsa_curro'],
																':tsa_currd' => $Data['tsa_currd'],
																':tsa_date'  => $Data['tsa_date'])
															);

        if(isset($resTasa[0])){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'Ya se encuentra un registro de tasa con esa moneda'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;

        }


        if ( $Data['tsa_curro'] == $Data['tsa_currd'] ) {

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' => 'No se puede crear una tasa con la misma moneda en origen y destino'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }


        $sqlInsert = "INSERT INTO tasa(tsa_eq, tsa_curro, tsa_value, tsa_currd, tsa_date, tsa_createby,tsa_enabled)
          	          VALUES (:tsa_eq, :tsa_curro, :tsa_value, :tsa_currd, :tsa_date, :tsa_createby,:tsa_enabled)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
                ':tsa_eq'    => isset($Data['tsa_eq']) ? $Data['tsa_eq'] : 0,
                ':tsa_curro' => isset($Data['tsa_curro']) ? $Data['tsa_curro'] : NULL,
                ':tsa_value' => isset($Data['tsa_value']) ? $Data['tsa_value'] : 0,
                ':tsa_currd' => isset($Data['tsa_currd']) ? $Data['tsa_currd'] : NULL,
                ':tsa_createby' => isset($Data['tsa_createby']) ? $Data['tsa_createby'] : NULL,
                ':tsa_date'  => isset($Data['tsa_date']) ? $Data['tsa_date']:NULL,
                'tsa_enabled' => 1
        ));

        if(is_numeric($resInsert) && $resInsert > 0 ){

              $respuesta = array(
              'error' => false,
              'data' => $resInsert,
              'mensaje' =>'Se registro la tasa con exito'
              );

        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar la tasa'
              );

        }

         $this->response($respuesta);
	}


  //ACTUALIZAR TASA
  public function updateTasa_post(){

      $Data = $this->post();

      $sqlTasa = "UPDATE tasa SET tsa_enabled = :tsa_enabled WHERE  tsa_id = :tsa_id";

      $resTasa = $this->pedeo->queryTable($sqlTasa, array( ':tsa_enabled' => isset($Data['tsa_enabled']) ? $Data['tsa_enabled'] : 0, ':tsa_id' => $Data['tsa_id']));


      if(isset($resTasa[0])){
      }else{
        $respuesta = array(

            'error' => true,
            'data'  => array(),
            'mensaje' => 'Ya se encuentra un registro de tasa con esa moneda'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE tasa	SET  tsa_value=:tsa_value WHERE tsa_id = :tsa_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

          ':tsa_value' => isset($Data['tsa_value']) ? $Data['tsa_value'] : 0,
          ':tsa_id' => $Data['tsa_id']

      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Valor de la tasa actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la tasa'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER CUENTAS CONTABLES
  public function getTasa_get(){

        $sqlSelect = " SELECT
											 	tsa_id,
												tsa_curro,
												tsa_currd,
												get_localcur()||' '||to_char(tsa_value, '999G999G999G999G999D'||lpad('9',get_decimals(),'9')) as tsa_value,
												tsa_date,
                        tsa_enabled
											 FROM tasa";

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
