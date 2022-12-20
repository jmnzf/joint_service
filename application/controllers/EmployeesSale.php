<?php
// DATOS MAESTROS EMPLEADOS DE VENTAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class EmployeesSale extends REST_Controller {

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

  //CREAR NUEVO EMPLEADO DE VENTA
	public function createEmployeesSale_post(){
      $Data = $this->post();

      if(!isset($Data['mev_names']) OR
         !isset($Data['mev_position']) OR
         !isset($Data['mev_phone']) OR
         !isset($Data['mev_cel_phone']) OR
         !isset($Data['mev_phone']) OR
         !isset($Data['mev_mail']) OR
         !isset($Data['mev_enabled']) OR
         !isset($Data['mev_prc_code']) OR
         !isset($Data['mev_dpj_pj_code']) OR
         !isset($Data['mev_dun_un_code']) OR
         !isset($Data['mev_id_sal_per']) OR
         !isset($Data['mev_whs_code']) OR 
         !isset($Data['business']) OR
         !isset($Data['branch'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlInsert = "INSERT INTO dmev(mev_names, mev_position, mev_phone, mev_cel_phone, mev_mail, mev_enabled, mev_prc_code,
                      mev_dpj_pj_code, mev_dun_un_code, mev_id_sal_per, mev_whs_code, business, branch)VALUES (:mev_names, :mev_position, :mev_phone,
                      :mev_cel_phone, :mev_mail, :mev_enabled, :mev_prc_code, :mev_dpj_pj_code, :mev_dun_un_code, :mev_id_sal_per, :mev_whs_code, :business, :branch)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':mev_names' => $Data['mev_names'],
              ':mev_position' => $Data['mev_position'],
              ':mev_phone' => $Data['mev_phone'],
              ':mev_cel_phone' => $Data['mev_cel_phone'],
              ':mev_mail' => $Data['mev_mail'],
              ':mev_enabled' => $Data['mev_enabled'],
              ':mev_prc_code' => $Data['mev_prc_code'],
              ':mev_dpj_pj_code' => $Data['mev_dpj_pj_code'],
              ':mev_dun_un_code' => $Data['mev_dun_un_code'],
              ':mev_id_sal_per' => $Data['mev_id_sal_per'],
              ':mev_whs_code' =>  $Data['mev_whs_code'],
              ':business' =>  $Data['business'],
              ':branch' =>  $Data['branch']

        ));

        if(is_numeric($resInsert) && $resInsert > 0){

              $respuesta = array(
                'error'		=> false,
                'data' 		=> $resInsert,
                'mensaje' =>'Centro de costo registrado con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje'	=> 'No se pudo registrar el centro de costo'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR EMPLEADO DE VENTA
  public function updateEmployeesSale_post(){

      $Data = $this->post();

      if(!isset($Data['mev_names']) OR
         !isset($Data['mev_position']) OR
         !isset($Data['mev_phone']) OR
         !isset($Data['mev_cel_phone']) OR
         !isset($Data['mev_phone']) OR
         !isset($Data['mev_mail']) OR
         !isset($Data['mev_enabled']) OR
         !isset($Data['mev_prc_code']) OR
         !isset($Data['mev_dpj_pj_code']) OR
         !isset($Data['mev_dun_un_code']) OR
         !isset($Data['mev_id_sal_per']) OR
         !isset($Data['mev_whs_code']) OR
         !isset($Data['mev_id'])){


        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE dmev SET mev_names = :mev_names, mev_position = :mev_position, mev_phone = :mev_phone, mev_cel_phone = :mev_cel_phone,
                   mev_mail = :mev_mail, mev_enabled = :mev_enabled, mev_prc_code = :mev_prc_code, mev_dpj_pj_code = :mev_dpj_pj_code,
                   mev_dun_un_code = :mev_dun_un_code, mev_id_sal_per = :mev_id_sal_per, mev_whs_code = :mev_whs_code	WHERE mev_id =:mev_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':mev_names' => $Data['mev_names'],
            ':mev_position' => $Data['mev_position'],
            ':mev_phone' => $Data['mev_phone'],
            ':mev_cel_phone' => $Data['mev_cel_phone'],
            ':mev_mail' => $Data['mev_mail'],
            ':mev_enabled' => $Data['mev_enabled'],
            ':mev_prc_code' => $Data['mev_prc_code'],
            ':mev_dpj_pj_code' => $Data['mev_dpj_pj_code'],
            ':mev_dun_un_code' => $Data['mev_dun_un_code'],
            ':mev_id_sal_per' => $Data['mev_id_sal_per'],
            ':mev_whs_code' =>  $Data['mev_whs_code'],
            ':mev_id' =>  $Data['mev_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Empleado actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el empleado'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER EMPLEADOS
  public function getEmployeesSale_get(){

        $Data = $this->get();

        if ( !isset($Data['business']) OR !isset($Data['branch']) ) {

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' => 'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }


        $sqlSelect = " SELECT * FROM dmev WHERE business = :business AND branch =:branch";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':business' => $Data['business'], ':branch' => $Data['branch']));

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

  // OBTENER EMPLEADOS POR ID
  public function getEmployeesSaleById_get(){

        $Data = $this->get();

        if(!isset($Data['mev_id'])){


          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmev WHERE mev_id = :mev_id ";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':mev_id' => $Data['mev_id']));

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
