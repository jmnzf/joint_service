<?php
// DATOS LISTA DE PRECIOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class ItemsGroup extends REST_Controller {

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

  //CREAR NUEVA LISTA DE PRECIO
	public function createItemsGroup_post(){

      $Data = $this->post();

      if(!isset($Data['mga_code']) OR
         !isset($Data['mga_name']) OR
         !isset($Data['mga_acctin']) OR
         !isset($Data['mga_acct_out']) OR
         !isset($Data['mga_acct_inv']) OR
         !isset($Data['mga_acct_stockp']) OR
         !isset($Data['mga_acct_stockn']) OR
         !isset($Data['mga_acct_redu']) OR
         !isset($Data['mga_acct_amp']) OR
         !isset($Data['mga_acct_cost']) OR
         !isset($Data['mga_enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlSelect = "SELECT mga_code FROM dmga WHERE mga_code = :mga_code";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(

          ':mga_code' => $Data['mga_code']

      ));


      if(isset($resSelect[0])){

        $respuesta = array(
          'error' => true,
          'data'  => array($Data['mga_code'], $Data['mga_code']),
          'mensaje' => 'ya existe un grupo con ese código');

        $this->response($respuesta);

        return;

    }
      

        $sqlInsert = "INSERT INTO dmgamga_code, mga_name, mga_acctin, mga_acct_out, mga_acct_inv, mga_acct_stockn, mga_acct_stockp, mga_acct_redu, mga_acct_amp, mga_acct_cost, mga_enabled)
                      VALUES(:mga_code, :mga_name, :mga_acctin, :mga_acct_out, :mga_acct_inv, :mga_acct_stockn, :mga_acct_stockp, :mga_acct_redu, :mga_acct_amp, :mga_acct_cost, :mga_enabled)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

              ':mga_code'    => $Data['mga_code'],
              ':mga_name'    => $Data['mga_name'],
              ':mga_acctin'    => $Data['mga_acctin'],
              ':mga_acct_out'    => $Data['mga_acct_out'],
              ':mga_acct_inv'    => $Data['mga_acct_inv'],
              ':mga_acct_stockn'    => $Data['mga_acct_stockn'],
              ':mga_acct_stockp'    => $Data['mga_acct_stockp'],
              ':mga_acct_redu'    => $Data['mga_acct_redu'],
              ':mga_acct_amp'  => $Data['mga_acct_amp'],
              ':mga_acct_cost'  => $Data['mga_acct_cost'],
              ':mga_enabled'  => $Data['mga_enabled']
        ));

        if($resInsert > 0 ){
            $respuesta = array(
              'error' => false,
              'data' => $resInsert,
              'mensaje' =>'Grupo de articulo registrado con exito'
            );
         }else{
   
           $respuesta = array(
             'error'   => true,
             'data' => array(),
             'mensaje' => 'No se pudo registrar el grupo de articulo'
           );
   
         }
         $this->response($respuesta);
        }

        
	

  //ACTUALIZAR LISTA DE PRECIOS
  public function updateItemGroup_post(){

      $Data = $this->post();

      if(!isset($Data['mga_code']) OR
         !isset($Data['mga_name']) OR
         !isset($Data['mga_acctin']) OR
         !isset($Data['mga_acct_out']) OR
         !isset($Data['mga_acct_inv']) OR
         !isset($Data['mga_acct_stockp']) OR
         !isset($Data['mga_acct_stockn']) OR
         !isset($Data['mga_acct_redu']) OR
         !isset($Data['mga_acct_amp']) OR
         !isset($Data['mga_acct_cost']) OR
         !isset($Data['mga_id']) OR
         !isset($Data['mga_enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlUpdate = "UPDATE dmga SET mga_code = :mga_code, 
                                    mga_name = :mga_name, 
                                    mga_acctin = :mga_acctin,
                                    mga_acct_out = :mga_acct_out,
                                    mga_acct_inv = :mga_acct_inv,
                                    mga_acct_stockn = :mga_acct_stockn,
                                    mga_acct_stockp = :mga_acct_stockp,
                                    mga_acct_redu = :mga_acct_redu,
                                    mga_acct_amp = :mga_acct_amp,
                                    mga_acct_cost = :mga_acct_cost,
                                    mga_enabled = :mga_enabled
                                    WHERE mga_id = :mga_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(


        ':mga_code'    => $Data['mga_code'],
        ':mga_name'    => $Data['mga_name'],
        ':mga_acctin'    => $Data['mga_acctin'],
        ':mga_acct_out'    => $Data['mga_acct_out'],
        ':mga_acct_inv'    => $Data['mga_acct_inv'],
        ':mga_acct_stockn'    => $Data['mga_acct_stockn'],
        ':mga_acct_stockp'    => $Data['mga_acct_stockp'],
        ':mga_acct_redu'    => $Data['mga_acct_redu'],
        ':mga_acct_amp'  => $Data['mga_acct_amp'],
        ':mga_acct_cost'  => $Data['mga_acct_cost'],
        ':mga_enabled'  => $Data['mga_enabled'],
        ':mga_id'  => $Data['mga_id']
      ));

      
      if(is_numeric($resUpdate) && $resUpdate == 1){

        $respuesta = array(
          'error' => false,
          'data' => $resUpdate,
          'mensaje' =>'Grupo de artículo actualizado con exito'
        );


  }else{

        $respuesta = array(
          'error'   => true,
          'data' => $resUpdate,
          'mensaje'	=> 'No se pudo actualizar el Grupo de artículo'
        );

  }

   $this->response($respuesta);
  
  }


  // OBTENER LISTA DE PRECIOS
  public function getItemsGroup_get(){

        $sqlSelect = " SELECT * FROM dmga";

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

  // OBTENER LISTA DE PRECIOS POR ID
  public function getItemsGroupById_get(){

        $Data = $this->get();

        if(!isset($Data['mga_id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmga WHERE mga_id = :mga_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':mga_id' => $Data['mga_id']));

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
