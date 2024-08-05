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
         !isset($Data['mev_id_type']) OR 
         !isset($Data['mev_card_code']) OR 
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
                      mev_dpj_pj_code, mev_dun_un_code, mev_id_sal_per, mev_whs_code, business, branch,  mev_id_type, mev_card_code, mev_account_cm)
                      VALUES (:mev_names, :mev_position, :mev_phone, :mev_cel_phone, :mev_mail, :mev_enabled, :mev_prc_code, :mev_dpj_pj_code,
                      :mev_dun_un_code, :mev_id_sal_per, :mev_whs_code, :business, :branch,  :mev_id_type, :mev_card_code, :mev_account_cm)";


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
              ':branch' =>  $Data['branch'],
              ':mev_id_type' =>  $Data['mev_id_type'],
              ':mev_card_code' =>  $Data['mev_card_code'],
              ':mev_account_cm' => $Data['mev_account_cm']
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

              $respuesta = array(
                'error'		=> false,
                'data' 		=> $resInsert,
                'mensaje' =>'Empleado registrado con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje'	=> 'No se pudo registrar el empleado'
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
         !isset($Data['mev_id_type']) OR 
         !isset($Data['mev_card_code']) OR 
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
                   mev_dun_un_code = :mev_dun_un_code, mev_id_sal_per = :mev_id_sal_per, mev_whs_code = :mev_whs_code, mev_id_type = :mev_id_type, 
                   mev_card_code = :mev_card_code, mev_account_cm = :mev_account_cm WHERE mev_id =:mev_id";


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
            ':mev_id_type' =>  $Data['mev_id_type'],
            ':mev_card_code' =>  $Data['mev_card_code'],
            ':mev_account_cm' => $Data['mev_account_cm'],
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

  public function createRelationEmployesUser_post()
  {
    $Data = $this->post();
    $respuesta = [];
    //validar que vengan los datos
    if(!isset($Data['asg_employed']) or !isset($Data['asg_user'])){
      $respuesta = array(
        'error' => true,
        'data' => [],
        'mensaje' => 'Informacion invalida'
      );
    }

    //validar si la relacion en la empresa existe
    $sqlValid = "SELECT * FROM treu WHERE reu_user = :reu_user AND business = :business AND reu_status = 1";
    $resValid = $this->pedeo->queryTable($sqlValid,array(
      ':reu_user' => $Data['asg_user'],
      ':business' => $Data['business']
    ));

    if(isset($resValid[0])){
      $this->pedeo->trans_begin();
      $update = "UPDATE treu SET reu_status = :reu_status WHERE reu_id = :reu_id";
      $resUpdate = $this->pedeo->updateRow($update,array(
        ':reu_status' => 0,
        ':reu_id' => $resValid[0]['reu_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate > 0){
        //proceso para insertar el nuevo dato
        $insert = "INSERT INTO treu(reu_employed,reu_user,reu_status,business) VALUES(:reu_employed,:reu_user,:reu_status,:business)";
        $resInsert = $this->pedeo->insertRow($insert,array(
          ':reu_employed' => $Data['asg_employed'],
          ':reu_user' => $Data['asg_user'],
          ':reu_status' => 1,
          ':business' => $Data['business']
        ));

        if(is_numeric($resInsert) && $resInsert > 0){
          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Empleado de ventas asignado con exito'
          );

        }else{
          $this->pedeo->trans_rollback();
          $respuesta = array(
            'error' => true,
            'data' => $resInsert,
            'mensaje' => 'No se pudo asignar el empleado de venta'
          );
          return $this->response($respuesta,REST_Controller::HTTP_BAD_REQUEST);
        }
      }else{
        $this->pedeo->trans_rollback();
          $respuesta = array(
            'error' => true,
            'data' => $resInsert,
            'mensaje' => 'No se pudo asignar el empleado de venta'
          );
          return $this->response($respuesta,REST_Controller::HTTP_BAD_REQUEST);
      }
      $this->pedeo->trans_commit();
    }else{
        //proceso para insertar el nuevo dato
        $this->pedeo->trans_begin();
        $insert = "INSERT INTO treu(reu_employed,reu_user,reu_status,business) VALUES(:reu_employed,:reu_user,:reu_status,:business)";
        $resInsert = $this->pedeo->insertRow($insert,array(
          ':reu_employed' => $Data['asg_employed'],
          ':reu_user' => $Data['asg_user'],
          ':reu_status' => 1,
          ':business' => $Data['business']
        ));

        if(is_numeric($resInsert) && $resInsert > 0){
          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Empleado de ventas asignado con exito'
          );

        }else{
          $this->pedeo->trans_rollback();
          $respuesta = array(
            'error' => true,
            'data' => $resInsert,
            'mensaje' => 'No se pudo asignar el empleado de venta'
          );
          return $this->response($respuesta,REST_Controller::HTTP_BAD_REQUEST);
      }
      $this->pedeo->trans_commit();
    }

    $this->response($respuesta);
  }

}
