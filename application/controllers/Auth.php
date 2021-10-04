<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Auth extends REST_Controller {

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

	public function getSiteMenu_get(){

		$request = $this->get();

		if( !isset($request['Pgu_Role']) ){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$result = $this->pedeo->queryTable("SELECT menu.men_id,menu.men_nombre,menu.men_icon,menu.men_controller,menu.men_action,menu.men_sub_menu,menu.men_id_menu FROM menu INNER JOIN menu_rol ON menu_rol.mno_id_menu = menu.men_id WHERE menu.men_id_menu = :idmenu AND menu.men_id_estado = :idestado AND menu_rol.mno_id_rol = :idrol ORDER BY menu.men_id", array(':idmenu' => 0,':idestado' => 1,':idrol' => $request['Pgu_Role']));
		//
		$controller = $this->pedeo->queryTable("SELECT menu.men_nombre, menu.men_controller, menu.men_action FROM menu INNER JOIN menu_rol ON menu_rol.mno_id_menu = menu.men_id WHERE menu.men_id_estado = :idestado AND menu_rol.mno_id_rol = :idrol", array(':idestado' => 1, ':idrol' => $request['Pgu_Role']));
		//
		$resultSet = array();
		// RESPUESTA POR DEFECTO.
		$respuesta = array(
			'error'  => true,
			'data'   => array(),
    		'mensaje'=> 'El usuatio no tiene permisos asignados.'
		);
		//
		if (isset($result[0])) {
			//
			foreach ($result as $index => $data) {
				//
				$data['SubMenu'] = array();
				//
				if ( $data['men_sub_menu'] == 1 && $data['men_id_menu'] == 0 ) {
					//
					$data['SubMenu'] = $this->pedeo->queryTable("SELECT menu.men_id,menu.men_nombre,menu.men_icon,menu.men_controller,menu.men_action,menu.men_sub_menu,menu.men_id_menu FROM menu INNER JOIN menu_rol ON menu_rol.mno_id_menu = menu.men_id WHERE menu.men_id_menu = :idmenu AND menu.men_id_estado = :idestado AND menu_rol.mno_id_rol = :idrol ORDER BY menu.men_nombre ASC",array(':idmenu' => $data['men_id'],':idestado' => 1,':idrol' => $request['Pgu_Role']));
					//
					foreach ($data['SubMenu'] as $key => $value) {

						if ( $value['men_sub_menu'] == 1 && $value['men_id_menu'] != 0 ) {
							//
							$data['SubMenu'][$key]['SubMenu'] = $this->pedeo->queryTable("SELECT menu.men_id,menu.men_nombre,menu.men_icon,menu.men_controller,menu.men_action FROM menu INNER JOIN menu_rol ON menu_rol.mno_id_menu = menu.men_id WHERE menu.men_id_menu = :idmenu AND menu.men_id_estado = :idestado AND menu_rol.mno_id_rol = :idrol ORDER BY menu.men_nombre ASC",array(':idmenu' => $value['men_id'],':idestado' => 1,':idrol' => $request['Pgu_Role']));
						}
					}
				}
				//
				$resultSet[$index] = $data;
			}
			//
			$respuesta = array(
				'error'  => false,
				'data'   => $resultSet,
				'ctr'    => $controller,
	    		'mensaje'=> ''
			);
		}

		$this->response($respuesta);
	}

	public function session_post() {

		$request = $this->post();

		if(!isset($request['sessionId'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$resSelect = $this->pedeo->queryTable("SELECT log_id_usuario FROM logeo WHERE log_id = :log_id AND log_id_estado = :statusId", array(
			':log_id'   => $request['sessionId'],
			':statusId' => 1
		));

		$respuesta = array(
			'error'  => true,
			'data'   => false,
			'mensaje'=> ''
		);

		if(isset($resSelect[0])){

			$respuesta = array(
				'error'  => false,
				'data'   => true,
				'mensaje'=> ''
			);
		}

		$this->response($respuesta);
	}
}
