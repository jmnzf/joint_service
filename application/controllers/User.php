<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class User extends REST_Controller {

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
	// Ingresar nuevo  Usuario
	public function insertUser_post(){

				$DataUser = $this->post();

				if(!isset($DataUser['Pgu_CodeUser']) OR
				   !isset($DataUser['Pgu_Pass']) OR
				   !isset($DataUser['Pgu_NameUser']) OR
				   !isset($DataUser['Pgu_LnameUser']) OR
					 !isset($DataUser['pgu_id_vendor']) OR
				   !isset($DataUser['Pgu_Branch'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM pgus WHERE pgu_code_user = :Pgu_CodeUser";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(':Pgu_CodeUser' => $DataUser['Pgu_CodeUser']));

				if(isset($resSelect[0])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' => 'ya esta en uso el usuario ingresado');

						 $this->response($respuesta);
						 return;
				}

				$sqlInsert = "INSERT INTO pgus(pgu_code_user,pgu_pass,pgu_name_user,pgu_lname_user,pgu_email,pgu_phone,pgu_branch,pgu_role,pgu_curr,pgu_id_vendor)
										  VALUES(:Pgu_CodeUser,:Pgu_Pass,:Pgu_NameUser,:Pgu_LnameUser,:Pgu_Email,:Pgu_Phone,:Pgu_Branch,:Pgu_Role,:Pgu_Curr,:pgu_id_vendor)";


				$resInsert = $this->pedeo->insertRow($sqlInsert, array(

							':Pgu_CodeUser' => $DataUser['Pgu_CodeUser'],
							':Pgu_Pass' => password_hash($DataUser['Pgu_Pass'], PASSWORD_DEFAULT),
							':Pgu_NameUser' => $DataUser['Pgu_NameUser'],
							':Pgu_LnameUser' => $DataUser['Pgu_LnameUser'],
							':Pgu_Email' => isset($DataUser['Pgu_Email'])?$DataUser['Pgu_Email']:"",
							':Pgu_Phone' => isset($DataUser['Pgu_Phone'])?$DataUser['Pgu_Phone']:"",
							':Pgu_Branch' => $DataUser['Pgu_Branch'],
							':Pgu_Role' => $DataUser['Pgu_Role'],
							':Pgu_Curr' => $DataUser['Pgu_Curr'],
							':pgu_id_vendor' => $DataUser['pgu_id_vendor']

				));


				if($resInsert > 0 ){

							$respuesta = array(
								'error' => false,
								'data' => $resInsert,
								'mensaje' =>'Usuario registrado con exito'
							);


				}else{

							$respuesta = array(
								'error'   => true,
								'data' => array(),
				        'mensaje'	=> 'No se pudo ingresar el usuario'
							);

				}

				 $this->response($respuesta);
	}

	// Actualizar usuario
	public function updateUser_post(){

				$DataUser = $this->post();

				if(!isset($DataUser['Pgu_IdUsuario'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlUpdate = "UPDATE pgus SET pgu_name_user = :Pgu_NameUser,
				              pgu_lname_user = :Pgu_LnameUser, pgu_email = :Pgu_Email, pgu_phone = :Pgu_Phone, pgu_branch = :Pgu_Branch,
											pgu_role = :Pgu_Role, pgu_curr = :Pgu_Curr, pgu_id_vendor = :pgu_id_vendor WHERE pgu_id_usuario = :Pgu_IdUsuario";


				$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':Pgu_NameUser' => $DataUser['Pgu_NameUser'],
							':Pgu_LnameUser' => $DataUser['Pgu_LnameUser'],
							':Pgu_Email' => $DataUser['Pgu_Email'],
							':Pgu_Phone' => $DataUser['Pgu_Phone'],
							':Pgu_Branch' => $DataUser['Pgu_Branch'],
							':Pgu_Role' => $DataUser['Pgu_Role'],
							':Pgu_Curr' => $DataUser['Pgu_Curr'],
							':Pgu_IdUsuario' => $DataUser['Pgu_IdUsuario'],
							':pgu_id_vendor' => $DataUser['pgu_id_vendor']
				));


				if(is_numeric($resUpdate) && $resUpdate == 1){

							$respuesta = array(
								'error'   => false,
								'data'    => $resUpdate,
								'mensaje' =>'Usuario actualizado con exito'
							);


				}else{

							$respuesta = array(
								'error'   => true,
								'data'    => $resUpdate,
								'mensaje'	=> 'No se pudo actualizar el usuario'
							);

				}

				 $this->response($respuesta);
	}

	// Actualizar contraseña usuario
	public function updatePass_post(){

		$DataUser = $this->post();

		if(!isset($DataUser['Pgu_IdUsuario'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE pgus SET pgu_pass = :Pgu_Pass WHERE pgu_id_usuario = :Pgu_IdUsuario";

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':Pgu_Pass' => password_hash($DataUser['Pgu_Pass'], PASSWORD_DEFAULT),
			':Pgu_IdUsuario' => $DataUser['Pgu_IdUsuario']

		));


		if(is_numeric($resUpdate) && $resUpdate == 1){

			$respuesta = array(
				'error'   => false,
				'data'    => $resUpdate,
				'mensaje' =>'Usuario actualizado con exito'
			);
		}else{
			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar el usuario'
			);
		}

		$this->response($respuesta);
	}

  // Obtener lista de usuarios
	public function getUser_get(){

				$sqlSelect = " SELECT pgus.*, rol.rol_nombre FROM pgus INNER JOIN rol ON rol.rol_id = pgus.pgu_role";

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


	// Obtener Solo usuario por nombre de usuario o codigo de usuario
	public function getfilterUser_get(){

			  $Data = $this->get();

				if(!isset($Data['param'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM pgus WHERE (pgu_id_usuario = :Pgu_IdUsuario OR  pgu_code_user = :Pgu_CodeUser)";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(
						':Pgu_IdUsuario' => $Data['param'],
						':Pgu_CodeUser' => $Data['param']

				));

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

	//Valida las credenciales de sesion  de un usuario
	public function login_post(){

				$DataUser = $this->post();

				if(!isset($DataUser['Pgu_CodeUser']) OR !isset($DataUser['Pgu_Pass'])){

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'La informacion enviada no es valida'
						);

						$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
						return;

		  	}

				$sqlExiste = " SELECT * FROM pgus WHERE pgu_code_user = :Pgu_CodeUser";

				$resExiste = $this->pedeo->queryTable($sqlExiste, array(':Pgu_CodeUser' => $DataUser['Pgu_CodeUser']));

				if(!isset($resExiste[0])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' => 'este usuario no esta registrado');

						 $this->response($respuesta);
						 return;
				}

				$sqlSelect = "SELECT pgu_id_usuario,pgu_name_user,pgu_lname_user,
											pgu_name_user || ' ' || pgu_lname_user AS NameC ,
											pgu_email,pgu_role,pgu_pass,pgu_id_vendor
											FROM pgus WHERE pgu_code_user = :Pgu_CodeUser AND pgu_enabled = :pgu_enabled";


				$resSelect = $this->pedeo->queryTable($sqlSelect, array(':Pgu_CodeUser' => $DataUser['Pgu_CodeUser'],':pgu_enabled' => 1));

				if( isset($resSelect[0]) ){

					if(password_verify($DataUser['Pgu_Pass'], $resSelect[0]['pgu_pass'])){
							unset($resSelect[0]['Pgu_Pass']);
							$respuesta = array(
								'error'   => false,
								'data'    => $resSelect,
								'mensaje' => ''
							);
					}else{

							$respuesta = array(
								'error'   => true,
								'data' => array(),
								'mensaje'	=> 'usuario y/o password invalidos'
							);
					}

				} else {

						$respuesta = array(
							'error'   => true,
							'data' => array(),
							'mensaje'	=> 'usuario y/o password invalidos'
						);

				}

				$this->response($respuesta);
 }

//Actualiza el estado de un usuario
 public function updateStatus_post(){
	 			$DataUser = $this->post();

				if(!isset($DataUser['Pgu_IdUsuario']) OR !isset($DataUser['Pgu_Enabled'])){

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'La informacion enviada no es valida'
						);

						$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
						return;

				}

				$sqlUpdate = "UPDATE  pgus SET pgu_enabled = :Pgu_Enabled WHERE pgu_id_usuario = :Pgu_IdUsuario";


				$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

											':Pgu_Enabled' => $DataUser['Pgu_Enabled'],
											':Pgu_IdUsuario' => $DataUser['Pgu_IdUsuario']

				));


				if(is_numeric($resUpdate) && $resUpdate == 1){

							$respuesta = array(
								'error'   => false,
								'data'    => $resUpdate,
								'mensaje' =>'Usuario actualizado con exito'
							);


				}else{

							$respuesta = array(
								'error'   => true,
								'data'    => $resUpdate,
								'mensaje'	=> 'No se pudo actualizar el usuario'
							);

				}

				 $this->response($respuesta);



 		}



}
