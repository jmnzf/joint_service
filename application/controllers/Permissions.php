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

		// OBTIENE lOS PERMISOS POR USUARIO Y TIPO DOCUMENTO
		public function actionUser_post() {
			//
			$request = $this->post();

			if( !isset($request['dmu_user']) OR !isset($request['dmu_doc_type'])){

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

					$result = $this->pedeo->queryTable("SELECT dmpt.dmt_id, dmpt.dmt_name, dmpu.dmu_permission
																							FROM dmpt
																							LEFT JOIN dmpu
																							ON dmpt.dmt_id =  dmpu.dmu_permission
																							AND dmpu.dmu_user = :dmu_user
																							AND dmpu.dmu_doc_type = :dmu_doc_type", array(':dmu_user' => $request['dmu_user'], ':dmu_doc_type' => $request['dmu_doc_type']));
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

		// GUARDA lOS PERMISOS POR USUARIO Y TIPO DOCUMENTO
		public function savePermissions_post() {
			//
			$request = $this->post();

			if( !isset($request['user']) OR !isset($request['doc']) OR !isset($request['permiss'])){

				$this->response(array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}

			$Permisos = json_decode($request['permiss'], true);

			if(isset($Permisos[0])){
					$sql = "DELETE FROM dmpu WHERE dmu_user = ".$request['user']." AND dmu_doc_type = ".$request['doc'];

					$resDelete = $this->pedeo->deleteRow($sql, array());
					// Se Inicia la transaccion,
					// Todas las consultas de modificacion siguientes
					// aplicaran solo despues que se confirme la transaccion,
					// de lo contrario no se aplicaran los cambios y se devolvera
					// la base de datos a su estado original.
				  $this->pedeo->trans_begin();

					for ($i=0; $i < count($Permisos); $i++) {

							$result = $this->pedeo->insertRow("INSERT INTO dmpu(dmu_permission, dmu_user, dmu_doc_type)
																									VALUES(:dmu_permission, :dmu_user, :dmu_doc_type)",
																									array(
																									':dmu_permission' => $Permisos[$i],
																									':dmu_user' => $request['user'],
																									':dmu_doc_type' => $request['doc']
																								 ));


						  if(is_numeric($result) && $result > 0){

							}else{

						    // si falla un solo insert
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$response = array(
										'error' => true,
										'data' => $result,
										'mensaje' =>'no se aplicaron los permisos al usuario, favor intente nuevamente'
									);

									$this->response($response);

									return;

							}
					}


					// Si todo sale bien despues de insertar el detalle de la orden de compra
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

					$response = array(
						'error' => false,
						'data' => '',
						'mensaje' =>'operación realizada con exito'
					);

			}else{

					$sql = "DELETE FROM dmpu WHERE dmu_user = ".$request['user']." AND dmu_doc_type = ".$request['doc'];
					$resDelete = $this->pedeo->deleteRow($sql, array());
					if(is_numeric($resUpdate) && $resUpdate == 1){
							$response = array(
								'error' => false,
								'data' =>  '',
								'mensaje' => 'operación realizada con exito'
							);
					}else{
						$response = array(
							'error' => true,
							'data' =>  '',
							'mensaje' => 'no se encontraron permisos para aplicar'
						);
					}

			}

			$this->response($response);
		}

		// COPIA LOS PERMISOS DE DOCUMENTOS DE UN USUARIO Y LOS ASIGNA A OTROS
		public function copyPermissions_post() {

				$request = $this->post();

				if( !isset($request['dmu_user']) OR !isset($request['dmu_users'])){

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

				$Usuarios = json_decode($request['dmu_users'], true);

				if(!isset($Usuarios)){

					$response = array(
						'error'   => true,
						'data'    => [],
						'mensaje' => 'no hay usuarios para modificar'
								);

						$this->response($response, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				$result = $this->pedeo->queryTable("SELECT dmu_permission, dmu_user, dmu_doc_type
																						FROM dmpu
																						WHERE dmu_user = :dmu_user", array(':dmu_user' => $request['dmu_user']));

				if(isset($result[0])){

						// Se Inicia la transaccion,
						// Todas las consultas de modificacion siguientes
						// aplicaran solo despues que se confirme la transaccion,
						// de lo contrario no se aplicaran los cambios y se devolvera
						// la base de datos a su estado original.
						$this->pedeo->trans_begin();

						for ($i=0; $i < count($Usuarios); $i++) {

							$sql = "DELETE FROM dmpu WHERE dmu_user = ".$Usuarios[$i]."";
							$resDelete = $this->pedeo->deleteRow($sql, array());

							foreach ($result as $key => $value) {

								$resresult = $this->pedeo->insertRow("INSERT INTO dmpu(dmu_permission, dmu_user, dmu_doc_type)
																											VALUES(:dmu_permission, :dmu_user, :dmu_doc_type)",
																											array(
																											':dmu_permission' => $value['dmu_permission'],
																											':dmu_user' 			=> $Usuarios[$i],
																											':dmu_doc_type' 	=> $value['dmu_doc_type']
																										 ));


								if(is_numeric($resresult) && $resresult > 0){

								}else{

									// si falla un solo insert
									// se retorna el error y se detiene la ejecucion del codigo restante.
										$this->pedeo->trans_rollback();

										$response = array(
											'error' => true,
											'data' => $resresult,
											'mensaje' =>'no se aplicaron los permisos al usuario, favor intente nuevamente'
										);

										$this->response($response);

										return;

								}
							}

						}


						// se confirma la trasaccion  para que los cambios apliquen permanentemente
						// en la base de datos y se confirma la operacion exitosa.
						$this->pedeo->trans_commit();

						$response = array(
							'error' => false,
							'data' => '',
							'mensaje' =>'operación realizada con exito'
						);

				}else{

					$response = array(
						'error'   => true,
						'data'    => [],
						'mensaje' => 'el usuario a copiar no tiene permisos asignados'
								);
				}

				$this->response($response);
		}

		// COPIA LOS PERMISOS DE DOCUMENTOS DE UN USUARIO Y LOS USUARIOS DEL MISMO ROL
		public function copyPermissionsByUsersRol_post() {

				$request = $this->post();

				if( !isset($request['dmu_user']) ){

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

				$sql = " SELECT * FROM pgus WHERE pgu_id_usuario = :pgu_id_usuario";

				$resSql = $this->pedeo->queryTable($sql, array(':pgu_id_usuario' => $request['dmu_user']));

				if(isset($resSql[0])){

						$sqlUsuarios = "SELECT * FROM pgus
														WHERE pgu_id_usuario != :pgu_id_usuario
														AND pgu_role = :pgu_role";

						$Usuarios = $this->pedeo->queryTable($sqlUsuarios, array(
										':pgu_id_usuario' => $request['dmu_user'],
										':pgu_role'				=> $resSql[0]['pgu_role']
						));

						if(!isset($Usuarios)){

							$response = array(
								'error'   => true,
								'data'    => [],
								'mensaje' => 'no hay usuarios para modificar'
										);

								$this->response($response, REST_Controller::HTTP_BAD_REQUEST);

								return;
						}


						$result = $this->pedeo->queryTable("SELECT dmu_permission, dmu_user, dmu_doc_type
																								FROM dmpu
																								WHERE dmu_user = :dmu_user", array(':dmu_user' => $request['dmu_user']));

						if(isset($result[0])){

								// Se Inicia la transaccion,
								// Todas las consultas de modificacion siguientes
								// aplicaran solo despues que se confirme la transaccion,
								// de lo contrario no se aplicaran los cambios y se devolvera
								// la base de datos a su estado original.
								$this->pedeo->trans_begin();

								for ($i=0; $i < count($Usuarios); $i++) {

									$sql = "DELETE FROM dmpu WHERE dmu_user = ".$Usuarios[$i]['pgu_id_usuario']."";
									$resDelete = $this->pedeo->deleteRow($sql, array());

									foreach ($result as $key => $value) {

										$resresult = $this->pedeo->insertRow("INSERT INTO dmpu(dmu_permission, dmu_user, dmu_doc_type)
																													VALUES(:dmu_permission, :dmu_user, :dmu_doc_type)",
																													array(
																													':dmu_permission' => $value['dmu_permission'],
																													':dmu_user' 			=> $Usuarios[$i]['pgu_id_usuario'],
																													':dmu_doc_type' 	=> $value['dmu_doc_type']
																												 ));


										if(is_numeric($resresult) && $resresult > 0){

										}else{

											// si falla un solo insert
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$response = array(
													'error' => true,
													'data' => $resresult,
													'mensaje' =>'no se aplicaron los permisos al usuario, favor intente nuevamente'
												);

												$this->response($response);

												return;

										}
									}

								}


								// se confirma la trasaccion  para que los cambios apliquen permanentemente
								// en la base de datos y se confirma la operacion exitosa.
								$this->pedeo->trans_commit();

								$response = array(
									'error' => false,
									'data' => '',
									'mensaje' =>'operación realizada con exito'
								);

						}else{

							$response = array(
								'error'   => true,
								'data'    => [],
								'mensaje' => 'el usuario a copiar no tiene permisos asignados'
										);
						}

				}	else {

					$response = array(
						'error'   => true,
						'data'    => [],
						'mensaje' => 'no se encontro al usuario'
								);

					$this->response($response, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$this->response($response);
		}
	}
