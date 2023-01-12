<?php
	defined('BASEPATH') OR exit('No direct script access allowed');

	require_once(APPPATH.'/libraries/REST_Controller.php');
	Use Restserver\Libraries\REST_Controller;

	class ContractSusDesDel extends REST_Controller
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
            $sql = "SELECT * FROM csde";
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

		public function SusDesDel_get() {
			// RESPUESTA POR DEFECTO.

            $sql = "SELECT sde_id, sde_description, sde_itemcode FROM csde WHERE sde_status = :sde_status";

			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
            );

	        $result = $this->pedeo->queryTable($sql, array(':sde_status' => 1));
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

			if( !isset($request['sde_description'])){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//

            $sql = "INSERT INTO csde (sde_description, sde_status, sde_itemcode) 
                                VALUES (:sde_description, :sde_status, :sde_itemcode)";
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'No se pudo guardar los datos'
			);
			//
			$insertId = $this->pedeo->insertRow($sql,
				array(
					':sde_description'   => $request['sde_description'],
					':sde_itemcode' => $request['sde_itemcode'],
					':sde_status'  => 1
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

			if( !isset($request['sde_description']) OR
			    !isset($request['sde_id'])){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//

            $sql = "UPDATE csde 
                    SET sde_description=:sde_description, 
					sde_itemcode = :sde_itemcode,
                    sde_status=:sde_status
                    WHERE sde_id = :sde_id";
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'No se pudo guardar los datos'
			);
			//
			$insertId = $this->pedeo->updateRow($sql,
				array(
					':sde_description'  => $request['sde_description'],
					':sde_itemcode' 	=> $request['sde_itemcode'],
					':sde_status'    	=> $request['sde_status'],
					':sde_id'        	=> $request['sde_id']
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

		public function getItemSus_get(){

			$sql = "SELECT dma_item_code as id, dma_item_name as text 
						FROM dmar
						WHERE dma_item_inv = :dma_item_inv
						AND dma_item_sales = :dma_item_sales";

			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
			);

			$result = $this->pedeo->queryTable($sql, array(':dma_item_inv' => 1, ':dma_item_sales' => 1));
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

	}
