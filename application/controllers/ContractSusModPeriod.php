<?php
	defined('BASEPATH') OR exit('No direct script access allowed');

	require_once(APPPATH.'/libraries/REST_Controller.php');
	Use Restserver\Libraries\REST_Controller;

	class ContractSusModPeriod extends REST_Controller
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
            $sql = "SELECT * FROM csmp";
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

            $sql = "SELECT * FROM csmp WHERE smp_status = :smp_status";

			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
            );

	        $result = $this->pedeo->queryTable($sql, array(':smp_status' => 1));
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

			if( !isset($request['smp_description'])){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//

            $sql = "INSERT INTO csmp (smp_description, smp_discountrate,smp_status,smp_day) 
                                VALUES (:smp_description,:smp_discountrate, :smp_status,:smp_day)";
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'No se pudo guardar los datos'
			);
			//
			$insertId = $this->pedeo->insertRow($sql,
				array(
					':smp_description'   => $request['smp_description'],
                    ':smp_discountrate' => $request['smp_discountrate'],
					':smp_day' => $request['smp_day'],
					':smp_status'  => 1
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

			if( !isset($request['smp_description']) OR
			    !isset($request['smp_id'])){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//

            $sql = "UPDATE csmp 
                    SET smp_description=:smp_description,
                    smp_discountrate = :smp_discountrate,
					smp_day = :smp_day,
                    smp_status=:smp_status
                    WHERE smp_id = :smp_id";
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'No se pudo guardar los datos'
			);
			//
			$insertId = $this->pedeo->updateRow($sql,
				array(
					':smp_description'   => $request['smp_description'],
                    ':smp_discountrate' => $request['smp_discountrate'],
					':smp_day' => $request['smp_day'],
					':smp_status'    => $request['smp_status'],
					':smp_id'        => $request['smp_id']
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
