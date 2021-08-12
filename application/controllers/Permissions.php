<?php
	defined('BASEPATH') OR exit('No direct script access allowed');

	require_once(APPPATH.'/libraries/REST_Controller.php');
	Use Restserver\Libraries\REST_Controller;

	class Permissions extends REST_Controller
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
			// 
			$request = $this->get();

			if( !isset($request['rol_id']) ){

				$this->response(array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			// RESPUESTA POR DEFECTO.
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
            );

	        $result = $this->pedeo->queryTable("SELECT * FROM menu_rol WHERE mno_id_rol = :rol", array(':rol' => $request['rol_id']));
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

			if( !isset($request['Mno_Idrol']) OR
			    !isset($request['Mno_Idmenu'])){

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
			// VALOR POR DEFECTO.
			$insertId = false;
			//
			$menus = json_decode($request['Mno_Idmenu'], true);
			// VALIDAR.
			if (isset($menus[0])) {
				// OBTENER PERMISOS DEL MENU.
				$result = $this->pedeo->queryTable("SELECT mno_id_menu FROM menu_rol WHERE mno_id_rol = :mno_id_rol", array(':mno_id_rol' => $request['Mno_Idrol']));
				// INSERT POR DEFECTO.
				$insert = $menus;
				// DELETE POR DEFECTO
				$delete = [];
				// VALIDAR SI EL MENU YA TIENE PERMISOS.
				if (isset($result[0])) {
					// OBTENER VALORES.
					$permissions = [];
					//
					foreach ($result as $key => $value) $permissions[$key] = $value['mno_id_menu'];
					// OBTENER NUEVOS VALORES A INSERTAR.
					$insert = array_diff($menus, $permissions);
					// OBTENER PERMISOS A ELIMINAR DE LA DB.
					$delete = array_diff($permissions, $menus);
				}
				// VALID
				if (count($insert) > 0) {
					// RECORRER DATOS A INSERTAR.
					foreach ($insert as $key => $idmenu) {
						//
						$insertId = $this->pedeo->insertRow("INSERT INTO menu_rol (mno_id_menu,mno_id_rol) VALUES (:Mno_Idmenu,:Mno_Idrol)",
							array(
								':Mno_Idmenu'=> $idmenu,
								':Mno_Idrol' => $request['Mno_Idrol']
							)
						);
					}
				} else {
					// VALOR POR DEFECTO.
					$insertId = true;
				}
				// RECORRER DATOS A ELIMINAR.
				foreach ($delete as $key => $idmenu) {
					//
					$this->pedeo->queryTable("DELETE FROM menu_rol WHERE mno_id_menu = :Mno_Idmenu AND mno_id_rol = :Mno_Idrol",
						array(
							':Mno_Idmenu'=> $idmenu,
							':Mno_Idrol' => $request['Mno_Idrol']
						)
					);
				}
			}
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
	}
