<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class User extends REST_Controller
{

	private $pdo;

	public function __construct()
	{
		header("Access-Control-Allow-Origin: *");
		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Origin,X-Requested-With,Content-Type,Accept,Access-Control-Request-Method,Authorization,Cache-Control");
		// header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, Authorization,Accept, x-api-key, X-Requested-With");



		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
		$this->load->library('pedeo', [$this->pdo]);
	}
	// Ingresar nuevo  Usuario
	public function insertUser_post()
	{

		$DataUser = $this->post();

		if (
			!isset($DataUser['Pgu_CodeUser']) or
			!isset($DataUser['Pgu_Pass']) or
			!isset($DataUser['Pgu_NameUser']) or
			!isset($DataUser['Pgu_LnameUser']) or
			!isset($DataUser['Pgu_Branch'])
		) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM pgus WHERE UPPER(pgu_code_user) = UPPER(:Pgu_CodeUser)";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':Pgu_CodeUser' => $DataUser['Pgu_CodeUser']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'El usuario ingresado ya se encuentra registrado.'
			);

			$this->response($respuesta);
			return;
		}

		$sqlInsert = "INSERT INTO pgus(pgu_code_user,pgu_pass,pgu_name_user,pgu_lname_user,pgu_email,pgu_phone,pgu_branch,pgu_role,pgu_curr,pgu_id_vendor,pgu_enabled)
					VALUES(:Pgu_CodeUser,:Pgu_Pass,:Pgu_NameUser,:Pgu_LnameUser,:Pgu_Email,:Pgu_Phone,:Pgu_Branch,:Pgu_Role,:Pgu_Curr,:pgu_id_vendor,:pgu_enabled)";


		$resInsert = $this->pedeo->insertRow($sqlInsert, array(

			':Pgu_CodeUser' => $DataUser['Pgu_CodeUser'],
			':Pgu_Pass' => password_hash($DataUser['Pgu_Pass'], PASSWORD_DEFAULT),
			':Pgu_NameUser' => $DataUser['Pgu_NameUser'],
			':Pgu_LnameUser' => $DataUser['Pgu_LnameUser'],
			':Pgu_Email' => isset($DataUser['Pgu_Email']) ? $DataUser['Pgu_Email'] : "",
			':Pgu_Phone' => isset($DataUser['Pgu_Phone']) ? $DataUser['Pgu_Phone'] : "",
			':Pgu_Branch' => $DataUser['Pgu_Branch'],
			':Pgu_Role' => $DataUser['Pgu_Role'],
			':Pgu_Curr' => $DataUser['Pgu_Curr'],
			':pgu_id_vendor' => isset($DataUser['pgu_id_vendor']) ? $DataUser['pgu_id_vendor'] : NULL,
			':pgu_enabled' => 0

		));


		if (is_numeric($resInsert) && $resInsert > 0) {

			$respuesta = array(
				'error' 	=> false,
				'data' 		=> $resInsert,
				'mensaje' => 'Usuario registrado con exito'
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' 		=> $resInsert,
				'mensaje'	=> 'No se pudo ingresar el usuario'
			);
		}

		$this->response($respuesta);
	}

	// Actualizar usuario
	public function updateUser_post()
	{

		$DataUser = $this->post();

		if (!isset($DataUser['Pgu_IdUsuario'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
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


		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$respuesta = array(
				'error'   => false,
				'data'    => $resUpdate,
				'mensaje' => 'Usuario actualizado con exito'
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar el usuario'
			);
		}

		$this->response($respuesta);
	}

	// Actualizar contraseña usuario
	public function updatePass_post()
	{

		$DataUser = $this->post();

		if (!isset($DataUser['Pgu_IdUsuario'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE pgus SET pgu_pass = :Pgu_Pass WHERE pgu_id_usuario = :Pgu_IdUsuario";

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':Pgu_Pass' => password_hash($DataUser['Pgu_Pass'], PASSWORD_DEFAULT),
			':Pgu_IdUsuario' => $DataUser['Pgu_IdUsuario']

		));


		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$respuesta = array(
				'error'   => false,
				'data'    => $resUpdate,
				'mensaje' => 'Usuario actualizado con exito'
			);
		} else {
			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar el usuario'
			);
		}

		$this->response($respuesta);
	}

	// Obtener lista de usuarios
	public function getUser_get()
	{

		$sqlSelect = " SELECT pgus.*, rol.rol_nombre FROM pgus INNER JOIN rol ON rol.rol_id = pgus.pgu_role";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}


	// Obtener Solo usuario por nombre de usuario o codigo de usuario
	public function getfilterUser_get()
	{

		$Data = $this->get();

		if (!isset($Data['param'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM pgus WHERE (pgu_id_usuario = :Pgu_IdUsuario OR  pgu_code_user = :Pgu_CodeUser)";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			':Pgu_IdUsuario' => $Data['param'],
			':Pgu_CodeUser' => $Data['param']

		));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	//Valida las credenciales de sesion  de un usuario
	public function login_post()
	{

		$DataUser = $this->post();

		if (!isset($DataUser['Pgu_CodeUser']) or !isset($DataUser['Pgu_Pass'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
			return;
		}

		$sqlExiste = " SELECT * FROM pgus WHERE pgu_code_user = :Pgu_CodeUser";

		$resExiste = $this->pedeo->queryTable($sqlExiste, array(':Pgu_CodeUser' => $DataUser['Pgu_CodeUser']));

		if (!isset($resExiste[0])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'este usuario no esta registrado'
			);

			$this->response($respuesta);
			return;
		}

		$sqlkey = "SELECT * FROM keys WHERE date_created >= current_date AND id = 1";
		$reskey = $this->pedeo->queryTable($sqlkey, array());

		if (isset($reskey[0])) {
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => ["LICENSEEXPIRED", "LICENSEEXPIRED"],
				'mensaje' => 'Lo sentimos, su número de licencia a expirado. Favor contante a su ejecutivo de ventas.'
			);

			$this->response($respuesta);
			return;
		}

		$sqlEmpresaUser = "SELECT * FROM rbbu WHERE bbu_user = :bbu_user";
		$resEmpresaUser = $this->pedeo->queryTable($sqlEmpresaUser, array(':bbu_user' => $resExiste[0]['pgu_id_usuario']));

		if (isset($resEmpresaUser[0])){

		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'El usuario no esta asociado a una empresa, se debe agregar una empresa predeterminada antes de poder inicar sesión'
			);

			$this->response($respuesta);

			return;
		}


		$sqlSelect = "SELECT pgu_code_user, pgu_id_usuario,pgu_name_user,pgu_lname_user,
											pgu_name_user || ' ' || pgu_lname_user AS NameC ,
											pgu_email,pgu_role,pgu_pass,pgu_id_vendor, pgu_branch
											FROM pgus WHERE pgu_code_user = :Pgu_CodeUser AND pgu_enabled = :pgu_enabled";


		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':Pgu_CodeUser' => $DataUser['Pgu_CodeUser'], ':pgu_enabled' => 1));

		if (isset($resSelect[0])) {

			if (password_verify($DataUser['Pgu_Pass'], $resSelect[0]['pgu_pass'])) {
				//
				$this->pedeo->updateRow("UPDATE logeo SET log_id_estado = :log_id_estado, log_date_end = :log_date_end WHERE log_id_usuario = :log_id_usuario AND log_id_estado = :statusId", array(
					':log_id_estado' => 0,
					':log_date_end' => date('Y-m-d H:i:s'),
					':log_id_usuario' => $resSelect[0]['pgu_id_usuario'],
					':statusId' => 1
				));
				//
				$sessionId = $this->pedeo->insertRow(
					"INSERT INTO logeo (log_id_usuario, log_date_init, log_date_end, log_id_estado) VALUES (:log_id_usuario, :log_date_init, :log_date_end, :log_id_estado)",
					array(
						':log_id_usuario' => $resSelect[0]['pgu_id_usuario'],
						':log_date_init' => date('Y-m-d H:i:s'),
						':log_date_end'  => NULL,
						':log_id_estado' => 1
					)
				);

				unset($resSelect[0]['pgu_pass']);
				// VALOR POR DEFECTO DEL DATO DE LA EMPRESA.
				$company = [];
				// OBTENER DATOS DE LA EMPRESA RELACIONADA AL USUARIO.
				$sqlresultCompany = "SELECT pge_id,pge_small_name,pge_name_soc,pgs_id,pgs_small_name,pge_useigtf,pge_client_default
								 FROM rbbu
								 INNER JOIN pgem
								 ON bbu_business = pgem.pge_id 
								 INNER JOIN pges
								 ON bbu_branch = pgs_id
								 WHERE bbu_user = :bbu_user
								 AND bbu_main = :bbu_main";

				$resultCompany = $this->pedeo->queryTable($sqlresultCompany, array(':bbu_user' => $resSelect[0]['pgu_id_usuario'], ':bbu_main' => 1));
				// VALIDAR RETORNO DE DATOS.
				if (isset($resultCompany[0])) {
					# code...
					$company = $resultCompany[0];
				}

				$respuesta = array(
					'error'   => false,
					'data'    => $resSelect,
					'sessionId' => $sessionId,
					'company' => $company,
					'mensaje' => ''
				);
			} else {
				//
				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'usuario y/o password inválidos'
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
	public function updateStatus_post()
	{
		$DataUser = $this->post();

		if (!isset($DataUser['Pgu_IdUsuario']) or !isset($DataUser['Pgu_Enabled'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
			return;
		}



		$sqlUpdate = "UPDATE  pgus SET pgu_enabled = :Pgu_Enabled WHERE pgu_id_usuario = :Pgu_IdUsuario";


		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

			':Pgu_Enabled' => $DataUser['Pgu_Enabled'],
			':Pgu_IdUsuario' => $DataUser['Pgu_IdUsuario']

		));


		if (is_numeric($resUpdate) && $resUpdate == 1) {

			$respuesta = array(
				'error'   => false,
				'data'    => $resUpdate,
				'mensaje' => 'Usuario actualizado con exito'
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar el usuario'
			);
		}

		$this->response($respuesta);
	}


	// Obtener Solo usuarios autorizados
	public function getUserListAuth_get()
	{

		$Data = $this->get();

		if (!isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT aus_id,
					pgus.pgu_id_usuario as id, concat(pgus.pgu_name_user,' ', COALESCE(pgus.pgu_lname_user,''))as nombre,
					pgu_code_user as user,
					case
						when aus_required = 1 then 'Requerido'
						else 'NA'
					end as requerido,
					case
						when aus_status = 1 then 'Activo'
						else 'Inactivo'
					end as estado,
					tmau.mau_decription,
					tmau.mau_docentry
					FROM taus
					INNER JOIN pgus
					ON taus.aus_id_usuario = pgus.pgu_id_usuario
					INNER JOIN tmau
					ON tmau.mau_docentry = taus.aus_id_model
					WHERE tmau.business = :business ";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':business' => $Data['business']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => [],
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}


	// Obtener Solo usuarios autorizados por codigo de modelo
	public function getUserListAuthById_get()
	{

		$Data = $this->get();

		if (!isset($Data['modelo'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$sqlSelect = "SELECT aus_id,
					pgus.pgu_id_usuario as id, concat(pgus.pgu_name_user,' ', COALESCE(pgus.pgu_lname_user,''))as nombre,
					pgu_code_user as user,
					case
						when aus_required = 1 then 'Requerido'
						else 'NA'
					end as requerido,
					case
						when aus_status = 1 then 'Activo'
						else 'Inactivo'
					end as estado,
					tmau.mau_decription
					FROM taus
					INNER JOIN pgus
					ON taus.aus_id_usuario = pgus.pgu_id_usuario
					INNER JOIN tmau
					ON tmau.mau_docentry = taus.aus_id_model
					WHERE tmau.mau_docentry = :mau_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mau_docentry' => $Data['modelo']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => [],
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}



	// ESTABLECE LOS USUARIOS QUE NECESITAN SER AUTORIZADOS
	public function setUser_post()
	{

		$Data = $this->post();

		if (!isset($Data['id']) or !isset($Data['modelo'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}



		$sqlvalidar = " SELECT * FROM taus WHERE aus_id_usuario = :aus_id_usuario AND aus_id_model = :aus_id_model";
		$resvalidar = $this->pedeo->queryTable($sqlvalidar, array(':aus_id_usuario' => $Data['id'], ':aus_id_model' => $Data['modelo']));

		if (isset($resvalidar[0])) {

			$respuesta = array(
				'error'   => true,
				'data' 		=> $resvalidar,
				'mensaje'	=> 'El usuario ya se encuentra en la tabla'
			);

			$this->response($respuesta);
			return;
		}


		$sqlInsert = "INSERT INTO taus(aus_id_usuario, aus_required, aus_id_model, aus_status)
												VALUES (:aus_id_usuario, :aus_required, :aus_id_model, :aus_status)";

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(

			':aus_id_usuario' => $Data['id'],
			':aus_required'   => 1,
			':aus_id_model' 	=> $Data['modelo'],
			':aus_status'			=> 1

		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			$respuesta = array(
				'error' 	=> false,
				'data' 		=> $resInsert,
				'mensaje' => 'Usuario registrado con exito'
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' 		=> $resInsert,
				'mensaje'	=> 'No se pudo ingresar el usuario'
			);
		}

		$this->response($respuesta);
	}
}
