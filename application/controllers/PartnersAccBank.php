<?php
//  Socio de Direcciones
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PartnersAccBank extends REST_Controller {

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

  //AGREGAR CUENTA DE BANCOA SN
	public function createPartnersAccBank_post(){

      $Data = $this->post();

  
      if(!isset($Data['dmb_bank']) OR
         !isset($Data['dmb_bank_type']) OR
         !isset($Data['dmb_card_type']) OR
         !isset($Data['dmb_trans_type']) OR
         !isset($Data['dmb_num_acc']) OR
         !isset($Data['dmb_status'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlSelect = "SELECT dmb_num_acc FROM dmsb WHERE dmb_card_code = :dmb_card_code AND dmb_card_type = :dmb_card_type AND dmb_num_acc = :dmb_num_acc";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
        ':dmb_card_code' => $Data['dmb_card_code'],
        ':dmb_card_type' => $Data['dmb_card_type'],
			  ':dmb_num_acc'   => $Data['dmb_num_acc']
        ));
        // print_r($resSelect);exit;die;
        if (isset($resSelect[0])) {
        $respuesta = array(
        'error' => true,
        'data'  => [],
        'mensaje' => 'Cuenta de banco ya existente, no es posible agregar. '
        );
        $this->response($respuesta);
        return;
        }

      $sqlInsert = "INSERT INTO dmsb(dmb_card_code,dmb_bank, dmb_bank_type, dmb_num_acc, dmb_major, dmb_status, dmb_account, dmb_card_type, dmb_trans_type)VALUES(
        :dmb_card_code, :dmb_bank, :dmb_bank_type, :dmb_num_acc, :dmb_major, :dmb_status, :dmb_account, :dmb_card_type, :dmb_trans_type)";

      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':dmb_bank' => $Data['dmb_bank'],
            ':dmb_bank_type' => $Data['dmb_bank_type'],
            ':dmb_num_acc' => $Data['dmb_num_acc'],
            ':dmb_major' => 0,
            ':dmb_status' => $Data['dmb_status'],
            ':dmb_card_code' => $Data['dmb_card_code'],
            ':dmb_account' => $Data['dmb_account'],
            ':dmb_card_type' => $Data['dmb_card_type'],
            ':dmb_trans_type' => $Data['dmb_trans_type']
      ));

      if(is_numeric($resInsert) && $resInsert > 0){


            $respuesta = array(
              'error' 	=> false,
              'data' 		=> $resInsert,
              'mensaje' =>'Cuenta de banco registrada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' 		=> $resInsert,
              'mensaje'	=> 'No se pudo registrar la Cuenta de banco'
            );

      }

       $this->response($respuesta);
	}

  //ACTUALIZAR CUENTA DE BANCO DE SN
  public function updatePartnersAccBank_post(){

      $Data = $this->post();

      if(!isset($Data['dmb_bank']) OR
         !isset($Data['dmb_bank_type']) OR
         !isset($Data['dmb_num_acc']) OR
         !isset($Data['dmb_major']) OR
         !isset($Data['dmb_status']) OR
         !isset($Data['dmb_card_code']) OR
         !isset($Data['dmb_card_type']) OR
         !isset($Data['dmb_trans_type']) OR
         !isset($Data['dmb_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlUpdate = "UPDATE dmsb
                  	SET dmb_card_code = :dmb_card_code, dmb_bank = :dmb_bank,
                    dmb_bank_type = :dmb_bank_type, dmb_num_acc = :dmb_num_acc,
                    dmb_major = :dmb_major, dmb_status = :dmb_status, dmb_account = :dmb_account,
                    dmb_card_type = :dmb_card_type, dmb_trans_type = :dmb_trans_type
                  	WHERE dmb_id = :dmb_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

        ':dmb_bank' => $Data['dmb_bank'],
        ':dmb_bank_type' => $Data['dmb_bank_type'],
        ':dmb_num_acc' => $Data['dmb_num_acc'],
        ':dmb_major' => $Data['dmb_major'],
        ':dmb_status' => $Data['dmb_status'],
        ':dmb_card_code' => $Data['dmb_card_code'],
        ':dmb_id' => $Data['dmb_id'],
        ':dmb_account' => $Data['dmb_account'],
        ':dmb_card_type' => $Data['dmb_card_type'],
        ':dmb_trans_type' => $Data['dmb_trans_type']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Cuenta de banco actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la cuenta de banco'
            );

      }

       $this->response($respuesta);
  }

  // OBTENER CUENTAS DE BANCO POR SOCIO DE NEGOCIO
  public function getPartnersAccBankById_get(){

        $Data = $this->get();

        if(!isset($Data['dmb_card_code']) AND
          !isset($Data['dmb_card_type'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = "SELECT natc.atc_name AS acc_type_name, dmbk.mbk_name AS bank_name, dmsb.* 
                      FROM dmsb 
                      INNER JOIN natc
                      ON natc.atc_code = dmsb.dmb_bank_type
                      INNER JOIN dmbk
                      ON dmbk.mbk_code = dmsb.dmb_bank
                      WHERE dmb_card_code = :dmb_card_code and dmb_card_type = :dmb_card_type";

        $resSelect = $this->pedeo->queryTable($sqlSelect,
         array(
          ':dmb_card_code' => $Data['dmb_card_code'],
          ':dmb_card_type' => $Data['dmb_card_type']
        ));
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