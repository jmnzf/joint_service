<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/libraries/REST_Controller.php');



use Restserver\libraries\REST_Controller;


class Recicler extends REST_Controller {

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

	//PROCEDIMIENTO PARA ELIMINAR LOS ARTICULOS DUPLICADOS EN LISTA DE PRECIOS//
	public function ClearMpl1_post(){

		$sqlSelect = "SELECT count(pl1_item_code), pl1_item_code
						FROM mpl1
						GROUP BY pl1_item_code
						HAVING (count(pl1_item_code) > 1)";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());


		if isset( $resSelect[0] ){

			foreach ($resSelect as $key => $value) {
				// code...
			}

		}

		$this->response("Proceso finalizado");

	}




}
