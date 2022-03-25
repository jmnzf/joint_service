<?php
	defined('BASEPATH') OR exit('No direct script access allowed');

	require_once(APPPATH.'/libraries/REST_Controller.php');
	Use Restserver\Libraries\REST_Controller;

	class KpiSettings extends REST_Controller
	{

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
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
            );

	        $result = $this->pedeo->queryTable("SELECT * FROM tkpi", array());
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

			if( !isset($request['kst_name']) OR
                !isset($request['kst_type']) OR
			    !isset($request['kst_kpi']) OR
                !isset($request['kst_config'])){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'No se pudo guardar los datos'
			);
			// 
			$config = json_encode($request['kst_config']); // CONFIGURACION DEL FRON DEL KPI.
			$status = 1; // ESTADO - POR DEFECTO EN 1(ACTIVO)
			// 
			$stmt = $this->pdo->prepare("INSERT INTO tkpi (kst_name, kst_type, kst_kpi, kst_config, kst_status) VALUES (:kst_name, :kst_type, :kst_kpi, :kst_config, :kst_status)");
			// 
			$stmt->bindParam(':kst_name', $request['kst_name']);
			$stmt->bindParam(':kst_type', $request['kst_type']);
			$stmt->bindParam(':kst_kpi', $request['kst_kpi']);
			$stmt->bindParam(':kst_config', $config, PDO::PARAM_STR);
			$stmt->bindParam(':kst_status', $status);
			//
			if ($stmt->execute()) {
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

			if( !isset($request['kst_name']) OR
                !isset($request['kst_type']) OR
			    !isset($request['kst_kpi']) OR
                !isset($request['kst_config']) OR
			    !isset($request['kst_id'])){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'No se pudo guardar los datos'
			);
			// 
			$config = json_encode($request['kst_config']); // CONFIGURACION DEL FRON DEL KPI.
			$status = 1; // ESTADO - POR DEFECTO EN 1(ACTIVO)
			// 
			$stmt = $this->pdo->prepare("UPDATE tkpi SET kst_name=:kst_name, kst_type=:kst_type, kst_kpi=:kst_kpi, kst_config=:kst_config, kst_status=:kst_status WHERE kst_id = :kst_id");
			// 
			$stmt->bindParam(':kst_name', $request['kst_name']);
			$stmt->bindParam(':kst_type', $request['kst_type']);
			$stmt->bindParam(':kst_kpi', $request['kst_kpi']);
			$stmt->bindParam(':kst_config', $config, PDO::PARAM_STR);
			$stmt->bindParam(':kst_status', $status);
			$stmt->bindParam(':kst_id', $request['kst_id']);
			//
			if ($stmt->execute()) {
				//
				$response = array(
					'error'   => false,
					'data'    => [],
					'mensaje' => 'Datos guardados exitosamente'
				);
			}else{
				$response = array(
					'error'   => true,
					'data'    => $insertId,
					'mensaje' => 'No se pudo guardar los datos'
				);
			}

         	$this->response($response);
		}

		public function config_get() {
			// RESPUESTA POR DEFECTO.
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
            );
			// 
	        $config = $this->pedeo->queryTable("SELECT tkpi.kst_type, tkpi.kst_config, csql.sql_query FROM tkpi INNER JOIN csql ON csql.sql_id = tkpi.kst_kpi WHERE kst_status = :kst_status", [':kst_status' => 1]);
	        // VALIDAR RETORNO DE DATOS DE LA CONSULTA.
	        if(isset($config[0])){
				// 
				$resultSet = [];
				// RECORRER CONFIGURACIÓN.
				foreach ($config as $key => $item) {
					// VALIDAR CAMPOS DEL QUERY
					if (!empty($item['sql_query'])) {
						// EXECUTAR QUERY DEL KPI.
						$result = $this->pedeo->queryTable($item['sql_query'], []);
						// ELIMINAR CAMPO DEL QUERY
						unset($item['sql_query']);
						// ASIGNAR DATOS
						$item['data'] = $result;
						// 
						$resultSet[] = $item;
					}
				}
	        	//
	          	$response = array(
		            'error'  => false,
		            'data'   => $resultSet,
		            'mensaje'=> ''
	        	);
	        }
	        //
         	$this->response($response);
		}
    }
?>