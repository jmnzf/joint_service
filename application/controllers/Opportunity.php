<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Opportunity extends REST_Controller {

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

    public function CreateOpportunity_post(){

        $Data = $this->post();
        $respuesta = array();
        print_r($Data);exit;

        
      $ContenidoDetalle = json_decode($Data['detail'], true);


      if(!is_array($ContenidoDetalle)){
          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'No se encontro el detalle de la entrada de compra'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }
			// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
			if(!intval(count($ContenidoDetalle)) > 0 ){
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'Documento sin detalle'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

        $sqlInsert = "INSERT INTO tbop(bop_id, bop_type, bop_contact, bop_slpcode, bop_agent, bop_balance, bop_name, bop_docnum, bop_status, bop_date, bop_duedate, bop_days, bop_dateprev, bop_pvalue, bop_interestl) 
                        VALUES (:bop_id, :bop_type, :bop_contact, :bop_slpcode, :bop_agent, :bop_balance, :bop_name, :bop_docnum, :bop_status, :bop_date, :bop_duedate, :bop_days, :bop_dateprev, :bop_pvalue, :bop_interestl)";
        
        $resInsert = $this->pedeo->insertRow($sqlInsert, 
                                            array(":bop_id"=> $Data['bop_id'],
                                                  ":bop_type"=> $Data['bop_type'],
                                                  ":bop_contact"=> $Data['bop_contact'],
                                                  ":bop_slpcode"=> $Data['bop_slpcode'],
                                                  ":bop_agent"=> $Data['bop_agent'],
                                                  ":bop_balance"=> $Data['bop_balance'],
                                                  ":bop_name"=> $Data['bop_name'],
                                                  ":bop_docnum"=> $Data['bop_docnum'],
                                                  ":bop_status"=> $Data['bop_status'],
                                                  ":bop_date"=> $Data['bop_date'],
                                                  ":bop_duedate"=> $Data['bop_duedate'],
                                                  ":bop_days"=> $Data['bop_days'],
                                                  ":bop_dateprev"=> $Data['bop_dateprev'],
                                                  ":bop_pvalue"=> $Data['bop_pvalue'],
                                                  ":bop_interestl"=> $Data['bop_interestl'])
    );

        if(is_numeric($resInsert) AND $resInsert > 0){
            
        }else{
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se puso realizar operacion'
            ); 
        }
    } 
}