<?php
	defined('BASEPATH') OR exit('No direct script access allowed');

	require_once(APPPATH.'/libraries/REST_Controller.php');
	Use Restserver\Libraries\REST_Controller;

	class ContractShiMethod extends REST_Controller
	{
		protected $rol;

		public function __construct(){

			header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
			header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
			header("Access-Control-Allow-Origin: *");

			parent::__construct();
			$this->load->database();
			$this->pdo = $this->load->database('pdo', true)->conn_id;
	    	$this->load->library('pedeo', [$this->pdo]);
		}

		public function index_get() {
			// RESPUESTA POR DEFECTO.
            $sql = "SELECT * FROM cfev";
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
            );

	        $result = $this->pedeo->queryTable($sql, array());
	        // VALIDAR RETORNO DE DATOS DE LA CONSULTA.
	        if(isset($result[0])){
	        	//
	          	$response = array(
		            'error'  => false,
		            'data'   => $result,
		            'mensaje'=> ''
	        	);
	        }
	        //
         	$this->response($response);
		}

		public function ShiMethod_get() {
			// RESPUESTA POR DEFECTO.

            $sql = "SELECT fev_id, fev_description FROM cfev WHERE fev_status = :fev_status";

			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
            );

	        $result = $this->pedeo->queryTable($sql, array(':fev_status' => 1));
	        // VALIDAR RETORNO DE DATOS DE LA CONSULTA.
	        if(isset($result[0])){
	        	//
	          	$response = array(
		            'error'  => false,
		            'data'   => $result,
		            'mensaje'=> ''
	        	);
	        }
	        //
         	$this->response($response);
		}
		/**
		 *
		 */
		public function create_post() {
			// OBTENER DATOS REQUEST.
			$request = $this->post();

			if( !isset($request['fev_description'])){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//

            $sql = "INSERT INTO cfev (fev_description, fev_status) 
                                VALUES (:fev_description, :fev_status)";
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'No se pudo guardar los datos'
			);
			//
			$insertId = $this->pedeo->insertRow($sql,
				array(
					':fev_description'   => $request['fev_description'],
					':fev_status'  => 1
				)
			);
			//
			if ($insertId) {
				//
				$response = array(
					'error'   => false,
					'data'    => [],
					'mensaje' => 'Datos guardados exitosamente'
				);
			}
	        //
         	$this->response($response);
		}
		/**
		 *
		 */
		public function update_post() {
			// OBTENER DATOS REQUEST.
			$request = $this->post();

			if( !isset($request['fev_description']) OR
			    !isset($request['fev_id'])){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//

            $sql = "UPDATE cfev 
                    SET fev_description=:fev_description, 
                    fev_status=:fev_status
                    WHERE fev_id = :fev_id";
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'No se pudo guardar los datos'
			);
			//
			$insertId = $this->pedeo->updateRow($sql,
				array(
					':fev_description'   => $request['fev_description'],
					':fev_status'    => $request['fev_status'],
					':fev_id'        => $request['fev_id']
				)
			);
			//
			if (is_numeric($insertId) && $insertId == 1) {
				//
				$response = array(
					'error'   => false,
					'data'    => [],
					'mensaje' => 'Datos guardados exitosamente'
				);
			}
	        //
         	$this->response($response);
		}
	}
