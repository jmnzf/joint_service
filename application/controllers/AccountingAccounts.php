<?php
// DATOS MAESTROS CUENTAS CONTABLES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class AccountingAccounts extends REST_Controller {

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

  //CREAR NUEVA CUENTA CONTABLE
	public function createAccountingAccounts_post(){
      $Data = $this->post();

      if(!isset($Data['acc_code']) OR
         !isset($Data['acc_name']) OR
         !isset($Data['acc_level']) OR
         !isset($Data['acc_cash']) OR
         !isset($Data['acc_cash_flow']) OR
         !isset($Data['acc_budget']) OR
         !isset($Data['acc_sup']) OR
         !isset($Data['acc_type']) OR
         !isset($Data['acc_tax_edef']) OR
         !isset($Data['acc_cost_center']) OR
         !isset($Data['acc_bus_unit']) OR
         !isset($Data['acc_project']) OR
         !isset($Data['acc_block_manual']) OR
         !isset($Data['acc_enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlInsert = "INSERT INTO dacc(acc_code, acc_name, acc_level, acc_cash, acc_cash_flow, acc_budget, acc_sup, acc_type, acc_tax_edef, acc_cost_center, acc_bus_unit, acc_project, acc_block_manual, acc_enabled, acc_businessp)
	                    VALUES (:acc_code, :acc_name, :acc_level, :acc_cash, :acc_cash_flow, :acc_budget, :acc_sup, :acc_type, :acc_tax_edef, :acc_cost_center, :acc_bus_unit, :acc_project, :acc_block_manual, :acc_enabled, :acc_businessp)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

              ':acc_code' => $Data['acc_code'],
              ':acc_name' => $Data['acc_name'],
              ':acc_level' => $Data['acc_level'],
              ':acc_cash' => $Data['acc_cash'],
              ':acc_cash_flow' => $Data['acc_cash_flow'],
              ':acc_budget' => $Data['acc_budget'],
              ':acc_sup' => $Data['acc_sup'],
              ':acc_type' => $Data['acc_type'],
              ':acc_tax_edef' => $Data['acc_tax_edef'],
              ':acc_cost_center' => $Data['acc_cost_center'],
              ':acc_bus_unit' => $Data['acc_bus_unit'],
              ':acc_project' => $Data['acc_project'],
              ':acc_block_manual' => $Data['acc_block_manual'],
              ':acc_enabled' => $Data['acc_enabled'],
							':acc_businessp' => $Data['acc_businessp']
        ));

        if(is_numeric($resInsert) && $resInsert > 0 ){

              $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' =>'Almacen registrado con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar el almacen'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR CUENTA CONTABLE
  public function updateAccountingAccounts_post(){

      $Data = $this->post();


        if(!isset($Data['acc_code']) OR
           !isset($Data['acc_name']) OR
           !isset($Data['acc_level']) OR
           !isset($Data['acc_cash']) OR
           !isset($Data['acc_cash_flow']) OR
           !isset($Data['acc_budget']) OR
           !isset($Data['acc_sup']) OR
           !isset($Data['acc_type']) OR
           !isset($Data['acc_tax_edef']) OR
           !isset($Data['acc_cost_center']) OR
           !isset($Data['acc_bus_unit']) OR
           !isset($Data['acc_project']) OR
           !isset($Data['acc_block_manual']) OR
           !isset($Data['acc_enabled']) OR
           !isset($Data['acc_id'])){


        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE dacc	SET acc_code=:acc_code, acc_name=:acc_name, acc_level=:acc_level, acc_cash=:acc_cash,
                    acc_cash_flow=:acc_cash_flow, acc_budget=:acc_budget, acc_sup=:acc_sup, acc_type=:acc_type, acc_tax_edef=:acc_tax_edef,
                    acc_cost_center=:acc_cost_center, acc_bus_unit=:acc_bus_unit, acc_project=:acc_project, acc_block_manual=:acc_block_manual,
                    acc_enabled=:acc_enabled ,acc_businessp = :acc_businessp WHERE acc_id = :acc_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

              ':acc_code' => $Data['acc_code'],
              ':acc_name' => $Data['acc_name'],
              ':acc_level' => $Data['acc_level'],
              ':acc_cash' => $Data['acc_cash'],
              ':acc_cash_flow' => $Data['acc_cash_flow'],
              ':acc_budget' => $Data['acc_budget'],
              ':acc_sup' => $Data['acc_sup'],
              ':acc_type' => $Data['acc_type'],
              ':acc_tax_edef' => $Data['acc_tax_edef'],
              ':acc_cost_center' => $Data['acc_cost_center'],
              ':acc_bus_unit' => $Data['acc_bus_unit'],
              ':acc_project' => $Data['acc_project'],
              ':acc_block_manual' => $Data['acc_block_manual'],
              ':acc_enabled' => $Data['acc_enabled'],
              ':acc_id' => $Data['acc_id'],
							':acc_businessp' => $Data['acc_businessp']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Cuenta contable actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la cuenta contable'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER CUENTAS CONTABLES
  public function getAccountingAccounts_get(){

        $sqlSelect = "SELECT
														    acc_id,
														    cast(acc_code as varchar) ,
														    acc_name,
														    acc_level,
														    round((select sum(t1.ac1_debit-t1.ac1_credit) from mac1 t1 where t1.ac1_account = acc_code), get_decimals()) as saldo,
														    acc_cash ,
														    acc_cash_flow,
														    acc_budget,
														    acc_sup  ,
														    acc_type ,
														    acc_tax_edef ,
														    acc_bus_unit   ,
														    acc_project,
														    acc_block_manual ,
														    acc_businessp  ,
														    acc_enabled,
														    acc_cost_center,
														    acc_shortb ,
														    acc_jobcap ,
														    acc_exptype from dacc
														order by cast(acc_code as varchar)";

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

  // OBTENER CUENTA CONTABLE POR ID
  public function getAccountingAccountsById_get(){

        $Data = $this->get();

        if(!isset($Data['acc_id'])){


          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dacc WHERE acc_id = :acc_id ";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':acc_id' => $Data['acc_id']));

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
