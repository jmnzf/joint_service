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
      //  !isset($Data['acc_cost_center']) OR
      //  !isset($Data['acc_bus_unit']) OR
      //  !isset($Data['acc_project']) OR
       !isset($Data['acc_block_manual']) OR
       !isset($Data['acc_concept']) OR
       !isset($Data['acc_enabled'])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }
    //VALIDAR QUE LA CUENTA PERTENEZCA AL NIVEL SELECCIONADO
    //VALIDAR SI LA CUENTA YA ESXISTE EN EL SISTEMA

    $validAccount = "SELECT * FROM dacc WHERE dacc.acc_code = :acc_code";
    $resValidAccount = $this->pedeo->queryTable($validAccount,array(':acc_code' => trim($Data['acc_code'])));

    if(isset($resValidAccount[0])){
      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La cuenta '.$Data['acc_code'].' ya existe en el sistema'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    
    // VALIDAR NIVELES DE LA CUENTA
    $acc = substr($Data['acc_code'],0,6);
    $acc1 = substr($Data['acc_code'],0,10);
    $digit = strlen($acc);
    $digit1 = strlen($acc1);
    $digit = intval($digit);
    $digit1 = intval($digit1);

    if ( $Data['acc_level'] == 6 ) {

      $l6 = $Data['acc_code'];
      $l5 = $Data['acc_sup'];
      $l4 = $this->AccountSup( $Data['acc_sup'] );
      $l3 = $this->AccountSup( $l4 );
      $l2 = $this->AccountSup( $l3 );
      $l1 = $this->AccountSup( $l2 );

    } else if ( $Data['acc_level'] == 5 ) {

      $l6 = $Data['acc_code'];
      $l5 = $Data['acc_code'];
      $l4 = $Data['acc_sup'];
      $l3 = $this->AccountSup( $Data['acc_sup'] );
      $l2 = $this->AccountSup( $l3 );
      $l1 = $this->AccountSup( $l2 );

    }else if ( $Data['acc_level'] == 4 ) {

      $l6 = $Data['acc_code'];
      $l5 = $Data['acc_code'];
      $l4 = $Data['acc_code']; 
      $l3 = $Data['acc_sup'];
      $l2 = $this->AccountSup( $l3 );
      $l1 = $this->AccountSup( $l2 );

    }else if ( $Data['acc_level'] == 3 ) {

      $l6 = $Data['acc_code'];
      $l5 = $Data['acc_code'];
      $l4 = $Data['acc_code'];
      $l3 = $Data['acc_code'];
      $l2 = $Data['acc_sup'];
      $l1 = $this->AccountSup( $Data['acc_sup'] );

    }else if ( $Data['acc_level'] == 2 ) {

      $l6 = $Data['acc_code'];
      $l5 = $Data['acc_code'];
      $l4 = $Data['acc_code'];
      $l3 = $Data['acc_code'];
      $l2 = $Data['acc_code'];
      $l1 = $Data['acc_sup'];

    }else if ( $Data['acc_level'] == 1 ) {

      $l6 = $Data['acc_code'];
      $l5 = $Data['acc_code'];
      $l4 = $Data['acc_code'];
      $l3 = $Data['acc_code'];
      $l2 = $Data['acc_code'];
      $l1 = $Data['acc_code'];

    }

    //   echo $l6;
    //   echo '\n';
    //   echo $l5;
    //   echo '\n';
    //   echo $l4;
    //   echo '\n';
    //   echo $l3;
    //   echo '\n';
    //   echo $l2;
    //   echo '\n';
    //   echo $l1;
    //  exit;
    if ( $l5 == 0 || $l4 == 0 || $l3 == 0 || $l2 == 0 || $l1 == 0){

      $respuesta = array(
        'error'   => true,
        'data'    => array(),
        'mensaje' =>'La cuenta superior de uno de los niveles no existe'
      );

      $this->response($respuesta);

      return;
    }
    //



    $sqlInsert = "INSERT INTO dacc(acc_code, acc_name, acc_level, acc_cash, acc_cash_flow, acc_budget, acc_sup, acc_type, acc_tax_edef, acc_cost_center, 
    acc_bus_unit, acc_project, acc_block_manual, acc_enabled, acc_businessp, acc_concept,acc_l1,acc_l2,acc_l3,acc_l4,acc_l5,acc_digit)
    VALUES (:acc_code, :acc_name, :acc_level, :acc_cash, :acc_cash_flow, :acc_budget, :acc_sup, :acc_type, :acc_tax_edef, :acc_cost_center, :acc_bus_unit, 
    :acc_project, :acc_block_manual, :acc_enabled, :acc_businessp, :acc_concept,:acc_l1,:acc_l2,:acc_l3,:acc_l4,:acc_l5,:acc_digit)";


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
          ':acc_concept' => $Data['acc_concept'],
          ':acc_enabled' => $Data['acc_enabled'],
          ':acc_businessp' => $Data['acc_businessp'],
          ':acc_l1' => $l1,
          ':acc_l2' => $l2,
          ':acc_l3' => $l3,
          ':acc_l4' => $l4,
          ':acc_l5' => $l5,
          ':acc_digit' => ($digit == 6 && $digit1 == 10) ? $digit1 : $digit
    ));

    if(is_numeric($resInsert) && $resInsert > 0 ){

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Cuenta registrada con exito'
          );


    }else{

          $respuesta = array(
            'error'   => true,
            'data' => array(),
            'mensaje'	=> 'No se pudo registrar el la cuenta'
          );

    }

    $this->response($respuesta);
}

  //ACTUALIZAR CUENTA CONTABLE
  public function updateAccountingAccounts_post(){

      $Data = $this->post();


        if(!isset($Data['acc_name']) OR
           !isset($Data['acc_level']) OR
           !isset($Data['acc_cash']) OR
           !isset($Data['acc_cash_flow']) OR
           !isset($Data['acc_budget']) OR
           !isset($Data['acc_sup']) OR
           !isset($Data['acc_type']) OR
           !isset($Data['acc_tax_edef']) OR
           !isset($Data['acc_block_manual']) OR
           !isset($Data['acc_concept']) OR
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

      //VALIDAR SI LA CUENTA A EDITAR TIENE MOVIMIENTOS
      // $sqlAccount = "SELECT dacc.acc_code as cuenta FROM dacc WHERE dacc.acc_id = :acc_id";//CONSULTAR CODIGO DE CUENTA EN CASO DADO SEA MODIFICADA
      // $resAccount = $this->pedeo->queryTable($sqlAccount,array(':acc_id' => $Data['acc_id']));//OBTENER DATOS DE CONSULTA
      // $cuenta = 0;
      // if (isset($resAccount[0])) {
      //   $cuenta = $resAccount[0]['cuenta'];
      // } else {

      //   $respuesta = array(
      //     'error' => true,
      //     'data'  => array(),
      //     'mensaje' =>'No se encontro la cuenta'
      //   );

      //   return $this->response($respuesta);
      // }
      
      // //CONSULTA PARA OBTENER EL SALDO DE LA CUENTA A MODIFICAR
      // $sqlValidBalance = "SELECT coalesce(abs(sum(mac1.ac1_debit - mac1.ac1_credit)), 0) as saldo FROM mac1 where ac1_account = :ac1_account";
      // //OBTENER REGISTROS
      // $resValidBalance = $this->pedeo->queryTable($sqlValidBalance,array(':ac1_account' => $cuenta));
      // //VALIDAR SI LA CONSULTA RETRONA UN DATO
      // if ( isset($resValidBalance[0]) && $resValidBalance[0]['saldo'] > 0 ) {
      //   $respuesta = array(
      //     'error' => true,
      //     'data' => [],
      //     'mensaje' => 'No se puede actualizar la cuenta  '.$cuenta.', ya que tiene transacciones realizadas'
      //   );
      //   //RETORNAR MENSAJE
      //   return $this->response($respuesta,REST_Controller::HTTP_BAD_REQUEST);
      // }


      $sqlUpdate = "UPDATE dacc	SET  acc_name=:acc_name, acc_level=:acc_level, acc_cash=:acc_cash,
                    acc_cash_flow=:acc_cash_flow, acc_budget=:acc_budget, acc_sup=:acc_sup, acc_type=:acc_type, acc_tax_edef=:acc_tax_edef,
                    acc_cost_center=:acc_cost_center, acc_bus_unit=:acc_bus_unit, acc_project=:acc_project, acc_block_manual=:acc_block_manual,
                    acc_enabled=:acc_enabled ,acc_businessp = :acc_businessp, acc_concept = :acc_concept WHERE acc_id = :acc_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

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
              ':acc_concept' => $Data['acc_concept'],
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
														    get_localcur()||' '||to_char((SELECT SUM(t1.ac1_debit-t1.ac1_credit) FROM mac1 t1 WHERE t1.ac1_account = acc_code AND EXTRACT(YEAR FROM ac1_doc_date) = EXTRACT(YEAR FROM current_date)),'999G999G999G999G999D'||lpad('9',get_decimals(),'9')) as saldo,
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
                                tcdc.cdc_name as acc_concept,
														    acc_exptype,
                                acc_concept as acc_concept_id
                                from dacc
                                LEFT JOIN tcdc
                                ON tcdc.cdc_id = dacc.acc_concept
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


  public function AccountSup($cuenta){

    $sqlCuenta = "SELECT * FROM dacc WHERE acc_code = :acc_code";
    $resCuenta = $this->pedeo->queryTable($sqlCuenta, array(
      ':acc_code' => $cuenta
    ));

    if (isset($resCuenta[0])) {
      
      return $resCuenta[0]['acc_sup'];

    } else {

      return 0;

    }
  }



}
