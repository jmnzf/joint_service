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
		$sql = "SELECT
					menu.men_id,
					menu.men_nombre,
					menu.men_icon,
					menu.men_controller,
					menu.men_action,
					menu.men_sub_menu,
					menu.men_id_menu
				FROM menu
				INNER JOIN menu_rol ON menu_rol.mno_id_menu = menu.men_id
				WHERE menu.men_id_estado = :idestado AND menu_rol.mno_id_rol = :idrol
				ORDER BY menu.men_id, menu.men_nombre asc";
		$result = $this->pedeo->queryTable($sql, array(':idestado' => 1,':idrol' => $request['Pgu_Role']));
		// OBTENER PERMISO DEL USUARIO.
		$resultSet = self::getpermissions($result);
		// RESPUESTA POR DEFECTO.
		$respuesta = array(
			'error'  => true,
			'data'   => array(),
    		'mensaje'=> 'El usuatio no tiene permisos asignados.'
		);
		//
		if (isset($result[0])) {
			//
			$respuesta = array(
				'error'  => false,
				'data'   => $resultSet['permissions'],
				'ctr'    => $resultSet['ctr'],
	    		'mensaje'=> ''
			);
		}

		$this->response($respuesta);
	}
    /**
     * MÉTODO PARA OBTENER PERMISOS DEL USUARIO.
     */
    private static function getpermissions($permissions) {
        // 
        $newpermissions = [];
        $ctr = [];
        // 
        foreach ($permissions as $key => $menu) {
            // VALIDAR SINO ES UN SUBMENU.
            if ( $menu['men_id_menu'] == 0 && $menu['men_sub_menu'] == 0 ) {
                // ASIGNAR DATOS DEL ITEM ACTUAL AL NUEVO ARRAY.
                $newpermissions[] = $menu;
            };
            // VALIDAR SI ES UN SUBMNENU.
            if ( $menu['men_id_menu'] == 0 && $menu['men_sub_menu'] == 1 ) {
                // FILTRAR SI EL ITEM ACTUAL TIENE SUBMENU.
                $menu['submenu'] = self::filterCrt($menu, $permissions);
                // ASIGNAR DATOS DEL ITEM ACTUAL AL NUEVO ARRAY.
                $newpermissions[] = $menu;
            }
            // VALIDAR SI TIENE CONTROLADOR
            if (!empty($menu['men_controller'])) {
                // ASIGNAR CONTROLADORES DEL MENU.
                $ctr[] = $menu;
            }
        }
        // 
        return [
            'ctr' => $ctr,
            'permissions' => $newpermissions
        ];
    }
    /**
     * 
     */
    private static function filterCrt($item, $params) {
        // VAR NEW RESULT.
        $result = [];
        // VAR PARA ALMACENAR PARAMETROS ORIGINALES.
        $newParams = $params;
        // 
        foreach ($params as $key => $menu) {
            // VALIDAR SI EL MENU ES HIJO DEL ITEM ACTUAL.
            if ($item['men_id'] == $menu['men_id_menu']) {
                // ASIGNAR DATOS DEL ITEM ACTUAL AL NUEVO ARRAY.
                $result[] = $menu;
                // ELIMINAR ITEM DEL ARRAY.
                unset($newParams[$key]);
            };
        }
        // 
        foreach ($result as $key => $_menu) {
            # code...
            $newData = [];
            // VALIDAR SI ES UN SUBMENU.
            if ( $_menu['men_sub_menu'] == 1 ) {
                // ASIGNAR DATOS DEL FILTRO.
                $newData = self::filterCrt($_menu, $newParams);
            };
            // ASIGNAR DATOS DEL FILTRO.
            $result[$key]['submenu'] = $newData;
        }

        return $result;
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
