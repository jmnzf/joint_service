<?php
// MODELO DE APROBACIONES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Payers extends REST_Controller {

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

  	public function getPayers_get() {

        $sqlSelect = "SELECT t1.dms_card_name as consumidor,
                    t1.dms_card_code as codigo_consumidor,
                    t2.dms_card_name as pagador,
                    t2.dms_card_code as codigo_pagador,
                    CASE 
                        WHEN t0.bcp_status = 1 THEN 'Activo'
                        WHEN t0.bcp_status = 0 THEN 'Inactivo'
                    END AS estado,
                    t0.bcp_status,
                    t0.bcp_id
                    FROM tbcp t0
                    INNER JOIN dmsn t1 ON t0.bcp_cardcode_consumer = t1.dms_card_code AND t1.dms_card_type = '1'
                    INNER JOIN dmsn t2 ON t0.bcp_cardcode_payer = t2.dms_card_code AND t2.dms_card_type = '1'";

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

    public function getPayersById_post() {

        $Data = $this->post();

        if (!isset($Data['bcp_cardcode_consumer'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

        $sqlSelect = "SELECT bcp_cardcode_payer FROM tbcp WHERE bcp_cardcode_consumer = :bcp_cardcode_consumer";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":bcp_cardcode_consumer" => $Data['bcp_cardcode_consumer']));

            if(isset($resSelect[0])) {

                $respuesta = array(
                'error' => false,
                'data'  => $resSelect,
                'mensaje' => '');

            } else {

                $respuesta = array(
                    'error'   => true,
                    'data' => array(),
                    'mensaje'	=> 'busqueda sin resultados'
                );

            }

        $this->response($respuesta);
	}


    public function setPayers_post() {

        $Data = $this->post();

        if (!isset($Data['bcp_cardcode_consumer']) OR
            !isset($Data['bcp_cardcode_payer']) Or
            !isset($Data['bcp_status'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

        // VALIDAR SI EXISTE LA RELACION
        $sqlSearch = "SELECT * FROM tbcp WHERE bcp_cardcode_consumer = :bcp_cardcode_consumer AND bcp_cardcode_payer = :bcp_cardcode_payer";
        $resSearch = $this->pedeo->queryTable($sqlSearch, array(
            ':bcp_cardcode_consumer' => $Data['bcp_cardcode_consumer'],
            ':bcp_cardcode_payer' => $Data['bcp_cardcode_payer'],
        ));


        if (isset($resSearch[0])){

            $respuesta = array(
                'error'   => true,
                'data' 	  => $resSearch,
                'mensaje' => 'El consumidor ya tiene el pagador '.$Data['bcp_cardcode_payer'].' registrado.'
            );

            return $this->response($respuesta);
        }
        //

        // INSERTAR RELACION
        $sqlInsert = "INSERT INTO tbcp(bcp_cardcode_consumer,bcp_cardcode_payer,bcp_status)VALUES(:bcp_cardcode_consumer,:bcp_cardcode_payer,:bcp_status)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':bcp_cardcode_consumer' => $Data['bcp_cardcode_consumer'],
            ':bcp_cardcode_payer' => $Data['bcp_cardcode_payer'],
            ':bcp_status' => $Data['bcp_status']
        ));


        if ( is_numeric($resInsert) && $resInsert > 0 ){


            $respuesta = array(
                'error' 	 => false,
                'data' 	 => $resInsert,
                'mensaje' => 'Relación registrada con exito'
            );

        }else{

            $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje' => 'No se pudo registrar la relación entre el consumidor y el pagador'
            );
        }

        $this->response($respuesta);

    }

    public function updatePayers_post() {

        $Data = $this->post();

        if (!isset($Data['bcp_cardcode_consumer']) OR 
            !isset($Data['bcp_cardcode_payer']) OR
            !isset($Data['bcp_status']) OR
            !isset($Data['bcp_id'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

        // VALIDAR SI EXISTE LA RELACION
        $sqlSearch = "SELECT * FROM tbcp WHERE bcp_cardcode_consumer = :bcp_cardcode_consumer AND bcp_cardcode_payer = :bcp_cardcode_payer AND bcp_status = :bcp_status";
        $resSearch = $this->pedeo->queryTable($sqlSearch, array(
            ':bcp_cardcode_consumer' => $Data['bcp_cardcode_consumer'],
            ':bcp_cardcode_payer' => $Data['bcp_cardcode_payer'],
            ':bcp_status' => $Data['bcp_status'],
        ));


        if (isset($resSearch[0])){

            $respuesta = array(
                'error'   => true,
                'data' 	  => $resSearch,
                'mensaje' => 'El consumidor ya tiene el pagador '.$Data['bcp_cardcode_payer'].' registrado.'
            );

            return $this->response($respuesta);
        }
        //

        // ACTUALIZAR RELACION
        $sqlUpdate = "UPDATE tbcp SET
                                 bcp_cardcode_consumer = :bcp_cardcode_consumer,
                                 bcp_cardcode_payer = :bcp_cardcode_payer,
                                 bcp_status  = :bcp_status
                                 WHERE bcp_id = :bcp_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            
            ':bcp_cardcode_consumer' => $Data['bcp_cardcode_consumer'],
            ':bcp_cardcode_payer' => $Data['bcp_cardcode_payer'],
            ':bcp_status' => $Data['bcp_status'],
            ':bcp_id' => $Data['bcp_id']
        ));


        if (is_numeric($resUpdate) && $resUpdate == 1 ){


            $respuesta = array(
                'error' 	 => false,
                'data' 	 => $resUpdate,
                'mensaje' => 'Relación actualizada con exito'
            );

        }else{

            $respuesta = array(
                'error'   => true,
                'data' 		=> $resUpdate,
                'mensaje' => 'No se pudo actualizar la relación entre el consumidor y el pagador'
            );
        }

        $this->response($respuesta);

    }
}