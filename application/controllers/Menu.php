<?php
	defined('BASEPATH') OR exit('No direct script access allowed');

	require_once(APPPATH.'/libraries/REST_Controller.php');
	Use Restserver\Libraries\REST_Controller;

	class Menu extends REST_Controller
	{
		protected $menu;

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

	        $result = $this->pedeo->queryTable("SELECT * FROM menu", array());
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

		public function subMenu_get() {
			// RESPUESTA POR DEFECTO.
			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
            );

	        $result = $this->pedeo->queryTable("SELECT men_id, men_nombre FROM menu WHERE men_sub_menu = :submenu",array(':submenu' => 1));
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
		public function permisoMenuId_get() {

			$request = $this->get();

			if( !isset($request['Mno_Idmenu']) ){

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

	        $result = $this->pedeo->queryTable("SELECT mno_id_rol FROM menu_rol WHERE mno_id_menu = :idmenu",array(':idmenu' => $request['Mno_Idmenu']));
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

			if( !isset($request['Men_Nombre']) OR
			    !isset($request['Men_Submenu'])){

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
			$insertId = $this->pedeo->insertRow("INSERT INTO menu (men_nombre, men_icon, men_controller, men_action, men_sub_menu, men_id_menu, men_id_estado) VALUES (:Men_Nombre, :Men_Icon, :Men_Controller, :Men_Action, :Men_Submenu, :Men_Idmenu, :Men_Idestado)",
				array(
					':Men_Nombre'    => $request['Men_Nombre'],
					':Men_Icon'      => $request['Men_Icon'],
					':Men_Controller'=> $request['Men_Controller'],
					':Men_Action'    => $request['Men_Action'],
					':Men_Submenu'   => $request['Men_Submenu'],
					':Men_Idmenu'    => $request['Men_Idmenu'],
					':Men_Idestado'  => 1
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

			if( !isset($request['Men_Nombre']) OR
			    !isset($request['Men_Submenu']) OR
			    !isset($request['Men_Id'])){

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
			$insertId = $this->pedeo->updateRow("UPDATE menu SET men_nombre=:men_nombre, men_icon=:men_icon, men_controller=:men_controller, men_action=:men_action, men_sub_menu=:men_sub_menu, men_id_menu=:men_id_menu, men_id_estado=:men_id_estado WHERE men_id = :men_id",
				array(
					':men_nombre'    => $request['Men_Nombre'],
					':men_icon'      => $request['Men_Icon'],
					':men_controller'=> $request['Men_Controller'],
					':men_action'    => $request['Men_Action'],
					':men_sub_menu'   => $request['Men_Submenu'],
					':men_id_menu'    => $request['Men_Idmenu'],
					':men_id_estado'  => $request['Men_Idestado'],
					':men_id'        => $request['Men_Id']
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
			}else{
				$response = array(
					'error'   => true,
					'data'    => $insertId,
					'mensaje' => 'No se pudo guardar los datos'
				);
			}

         	$this->response($response);
		}
		/**
		 *
		 */
		public function menuPermission_post() {
			// OBTENER DATOS REQUEST.
			$request = $this->post();

			if( !isset($request['Men_Nombre']) OR
			    !isset($request['Men_Submenu']) OR
			    !isset($request['Men_Id'])){

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
		}
		/**
		 *
		 */
		public function createPermission_post() {
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
			$roles = json_decode($request['Mno_Idrol'], true);
			// VALIDAR.
			if (isset($roles[0])) {
				// OBTENER PERMISOS DEL MENU.
				$result = $this->pedeo->queryTable("SELECT mno_id_rol FROM menu_rol WHERE mno_id_menu = :mno_id_menu", array(':mno_id_menu' => $request['Mno_Idmenu']));
				// INSERT POR DEFECTO.
				$insert = $roles;
				// DELETE POR DEFECTO
				$delete = [];
				// VALIDAR SI EL MENU YA TIENE PERMISOS.
				if (isset($result[0])) {
					// OBTENER VALORES.
					$permissions = [];
					//
					foreach ($result as $key => $value) $permissions[$key] = $value['Mno_Idrol'];
					// OBTENER NUEVOS VALORES A INSERTAR.
					$insert = array_diff($roles, $permissions);
					// OBTENER PERMISOS A ELIMINAR DE LA DB.
					$delete = array_diff($permissions, $roles);
				}
				// VALID
				if (count($insert) > 0) {
					// RECORRER DATOS A INSERTAR.
					foreach ($insert as $key => $idrol) {
						//
						$insertId = $this->pedeo->insertRow("INSERT INTO menu_rol (mno_id_menu,mno_id_rol) VALUES (:Mno_Idmenu,:Mno_Idrol)",
							array(
								':Mno_Idmenu'=> $request['Mno_Idmenu'],
								':Mno_Idrol' => $idrol
							)
						);
					}
				} else {
					// VALOR POR DEFECTO.
					$insertId = true;
				}
				// RECORRER DATOS A ELIMINAR.
				foreach ($delete as $key => $idrol) {
					//
					$this->pedeo->queryTable("DELETE FROM menu_rol WHERE mno_id_menu = :Mno_Idmenu AND mno_id_rol = :Mno_Idrol",
						array(
							':Mno_Idmenu'=> $request['Mno_Idmenu'],
							':Mno_Idrol' => $idrol
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
