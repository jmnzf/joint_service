<?php
	defined('BASEPATH') OR exit('No direct script access allowed');

	require_once(APPPATH.'/libraries/REST_Controller.php');
	Use Restserver\Libraries\REST_Controller;

	class ContractSusModDay extends REST_Controller
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
            $sql = "SELECT smd_id, smd_description, 
						get_localcur()||' '||to_char(smd_price, '999G999G999G999G999D'||lpad('9',get_decimals(),'9')) as smd_price, 
						smd_status, business, branch  
					FROM csmd";
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

		public function SusModDay_get() {
			// RESPUESTA POR DEFECTO.

            $sql = "SELECT * 
					FROM csmd
			 		WHERE smd_status = :smd_status";

			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
            );

	        $result = $this->pedeo->queryTable($sql, array(':smd_status' => 1));
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

			if( !isset($request['smd_description'])){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//

            $sql = "INSERT INTO csmd (smd_description, smd_price,smd_status) 
                                VALUES (:smd_description,:smd_price, :smd_status)";
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'No se pudo guardar los datos'
			);
			//
			$insertId = $this->pedeo->insertRow($sql,
				array(
					':smd_description'   => $request['smd_description'],
                    ':smd_price' => $request['smd_price'],
					':smd_status'  => 1
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

			if( !isset($request['smd_description']) OR
			    !isset($request['smd_id'])){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//

            $sql = "UPDATE csmd 
                    SET smd_description=:smd_description,
                    smd_price = :smd_price,
                    smd_status=:smd_status
                    WHERE smd_id = :smd_id";
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'No se pudo guardar los datos'
			);
			//
			$insertId = $this->pedeo->updateRow($sql,
				array(
					':smd_description'   => $request['smd_description'],
                    ':smd_price' => $request['smd_price'],
					':smd_status'    => $request['smd_status'],
					':smd_id'        => $request['smd_id']
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
