<?php
// INFORME PARA BALANCE Y ESTADO DE RESULTADOS

defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class BalancePYG extends REST_Controller {

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




    // OBTENER BALANCE GENERAL
    public function getBalance_post() {

        $Data = $this->post();

       

        $array = array( 
                ":level" => $Data['bpyg_level'], 
                ":from_date" => $Data['bpyg_from_date'], 
                ":to_date" => $Data['bpyg_to_date'], 
                ":type" => 3
        );




        $sqlSelect = "SELECT acc_sup, 
                acc_type, 
                acc_level, 
                acc_code,
                acc_name,
                coalesce(sum(cdoc.doc_debit), 0) as debito,
                coalesce(sum(cdoc.doc_credit), 0) as credito,
                coalesce(sum(cdoc.doc_debit - cdoc.doc_credit), 0) as saldo
                FROM dacc 
                LEFT JOIN cdoc ON cdoc.doc_account = dacc.acc_code 
                WHERE acc_type <= :type
                AND acc_level <= :level
                AND cdoc.doc_date BETWEEN :from_date AND :to_date
                GROUP BY acc_type, acc_code, acc_name, acc_sup, acc_level
                HAVING abs(coalesce(sum(cdoc.doc_debit - cdoc.doc_credit), 0)) > 0 OR acc_type = 3
                ORDER BY acc_code::text ASC";

     
        // print_r($sqlSelect);exit;
        $resSelect = $this->pedeo->queryTable($sqlSelect, $array);


        $sqlArrastre = str_replace('WHERE acc_type <= :type','WHERE acc_type >= :type',$sqlSelect);
        $sqlArrastre = str_replace('OR acc_type = 3','',$sqlArrastre);

        $resArrastreEjercicio = $this->pedeo->queryTable($sqlArrastre, array(":level" => $Data['bpyg_level'], ":from_date" => $Data['bpyg_from_date'], ":to_date" => $Data['bpyg_to_date'], ":type" => 4));

        
        if ( isset($resSelect[0]) ) {

            $respuesta = array(
                'error' => false,
                'data'  => $resSelect,
                'data2' => $resArrastreEjercicio,
                'mensaje' => ''
            );

        } else {

            $respuesta = array(
            'error'   => true,
            'data' => array(),
            'mensaje'	=> 'busqueda sin resultados'
            );

        }

        $this->response($respuesta);
    }

    // OBTENER ESTADOS DE RESULTADOS
    public function getStatement_post() {

        $Data = $this->post();

        $array = array( ":business" => $Data['business'], ":level" => $Data['bpyg_level'], ":from_date" => $Data['bpyg_from_date'], ":to_date" => $Data['bpyg_to_date'], ":type" => 4);

        
        $sqlAdicional = "";
 
        if ( isset($Data['bpyg_costcode']) && !empty($Data['bpyg_costcode']) ) {
            $sqlAdicional .= " AND dco_prc_code = :dco_prc_code";
            $array[':dco_prc_code'] = $Data['bpyg_costcode'];
        }

        if ( isset($Data['bpyg_ubusiness']) && !empty($Data['bpyg_ubusiness']) ) {
            $sqlAdicional .= " AND dco_uncode = :dco_uncode";
            $array[':dco_uncode'] = $Data['bpyg_ubusiness'];
        }

        if ( isset($Data['bpyg_project']) && !empty($Data['bpyg_project']) ) {
            $sqlAdicional .= " AND dco_prj_code = :dco_prj_code";
            $array[':dco_prj_code'] = $Data['bpyg_project'];
        }

        $sqlSelect = "SELECT acc_sup, 
                acc_type, 
                acc_level, 
                acc_code,
                acc_name,
                coalesce(sum(cdoc.doc_debit), 0) as debito,
                coalesce(sum(cdoc.doc_credit), 0) as credito,
                coalesce(sum(cdoc.doc_debit - cdoc.doc_credit), 0) as saldo
                FROM dacc 
                LEFT JOIN cdoc ON cdoc.doc_account = dacc.acc_code 
                WHERE acc_type >= :type
                AND acc_level <= :level
                AND cdoc.doc_date BETWEEN :from_date AND :to_date
                AND cdoc.business = :business
                ".$sqlAdicional."
                GROUP BY acc_type, acc_code, acc_name, acc_sup, acc_level
                HAVING abs(coalesce(sum(cdoc.doc_debit - cdoc.doc_credit), 0)) > 0 OR acc_type = 3
                ORDER BY acc_code::text ASC";

        $resSelect = $this->pedeo->queryTable($sqlSelect, $array);


     
        
        if ( isset($resSelect[0]) ) {

            $respuesta = array(
                'error' => false,
                'data'  => $resSelect,
                'mensaje' => ''
            );

        } else {

            $respuesta = array(
            'error'   => true,
            'data' => array(),
            'mensaje'	=> 'busqueda sin resultados'
            );

        }

        $this->response($respuesta);
    }
    
    // Obtener Sucursal por Id
    public function getBranchById_get(){

            $Data = $this->get();

            if(!isset($Data['Pgs_Id'])){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
            }

            $sqlSelect = " SELECT * FROM pges WHERE pgs_company_id = :Pgs_Id";

            $resSelect = $this->pedeo->queryTable($sqlSelect, array(':Pgs_Id' => $Data['Pgs_Id']));

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
