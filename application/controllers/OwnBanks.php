<?php
// BANCOS PROPIOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class OwnBanks extends REST_Controller {

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

  	public function getOwnBanks_get() {
	  $sqlSelect = "SELECT boa_id, boa_name, 
                    case  when boa_accounttype = '1'  then 'Cuenta Corriente' when boa_accounttype = '2' then 'Cuenta Ahorro' end as boa_nameaccounttype , 
                    boa_accounttype, boa_branch, boa_accountnumber, boa_accountingaccount, boa_swiftcode 
                    FROM tboa";

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

	public function createOwnBanks_post() {

        $Data = $this->post();


        $sqlInsert = "INSERT INTO tboa(boa_name, boa_accounttype, boa_branch, boa_accountnumber, boa_accountingaccount, boa_swiftcode)
                   VALUES(:boa_name, :boa_accounttype, :boa_branch, :boa_accountnumber, :boa_accountingaccount, :boa_swiftcode)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':boa_name' =>  isset($Data['boa_name']) ? $Data['boa_name']: null ,
            ':boa_accounttype' =>  isset($Data['boa_accounttype']) ? $Data['boa_accounttype']: null ,
            ':boa_branch' =>  isset($Data['boa_branch']) ? $Data['boa_branch']: null ,
            ':boa_accountnumber' =>  isset($Data['boa_accountnumber']) ? $Data['boa_accountnumber']: null ,
            ':boa_accountingaccount' => isset($Data['boa_accountingaccount']) && is_numeric($Data['boa_accountingaccount']) ? $Data['boa_accountingaccount'] : null, 
            ':boa_swiftcode' => isset($Data['boa_swiftcode']) ? $Data['boa_swiftcode']: null 
        ));


        if ( is_numeric($resInsert) && $resInsert > 0 ){
            $respuesta = array(
	            'error'   => false,
	            'data'    => $resInsert,
	            'mensaje' => 'Cuenta creada con exito'
	        );

        }else{
            $respuesta = array(
	            'error'   => true,
	            'data'    => $resInsert,
	            'mensaje' => 'No se pudo crear la cuenta'
	        );
        }

        $this->response($respuesta);
    }

    public function updateOwnBanks_post() {

        $Data = $this->post();


        $sqlUpdate = "UPDATE tboa set boa_name = :boa_name, boa_accounttype = :boa_accounttype, boa_branch = :boa_branch, boa_accountnumber = :boa_accountnumber, boa_accountingaccount = :boa_accountingaccount, boa_swiftcode = :boa_swiftcode WHERE boa_id = :boa_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            
            ':boa_name' =>  isset($Data['boa_name']) ? $Data['boa_name']: null ,
            ':boa_accounttype' =>  isset($Data['boa_accounttype']) ? $Data['boa_accounttype']: null ,
            ':boa_branch' =>  isset($Data['boa_branch']) ? $Data['boa_branch']: null ,
            ':boa_accountnumber' =>  isset($Data['boa_accountnumber']) ? $Data['boa_accountnumber']: null ,
            ':boa_accountingaccount' => isset($Data['boa_accountingaccount']) && is_numeric($Data['boa_accountingaccount']) ? $Data['boa_accountingaccount'] : null, 
            ':boa_swiftcode' => isset($Data['boa_swiftcode']) ? $Data['boa_swiftcode']: null,
            ':boa_id' =>  isset($Data['boa_id']) && is_numeric($Data['boa_id']) ? $Data['boa_id'] : null, 
        ));


        if ( is_numeric($resUpdate) && $resUpdate == 1 ){
            $respuesta = array(
	            'error'   => false,
	            'data'    => $resUpdate,
	            'mensaje' => 'Cuenta actualizada con exito'
	        );

        }else{
            $respuesta = array(
	            'error'   => true,
	            'data'    => $resUpdate,
	            'mensaje' => 'No se pudo actualizar la cuenta'
	        );
        }

        $this->response($respuesta);
    }
}