<?php
// CONTROLADOR DE ALERTAS DINAMICAS
// defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Alerts extends REST_Controller {

	private $pdo;

	public function __construct() {

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);

	}

    public function createAlerts_post() {


        $Data = $this->post();


        $sqlInsert = "INSERT INTO talt(alt_name,alt_title,alt_message,alt_type,alt_sql,alt_autoclose,alt_duration,alt_enabled,alt_users,alt_url,alt_trigger,alt_owner)
                VALUES(:alt_name,:alt_title,:alt_message,:alt_type,:alt_sql,:alt_autoclose,:alt_duration,:alt_enabled,:alt_users,:alt_url,:alt_trigger,:alt_owner)";
        
        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':alt_name' => $Data['alt_name'],
            ':alt_title' => $Data['alt_title'],
            ':alt_message' => $Data['alt_message'],
            ':alt_type' => $Data['alt_type'],
            ':alt_sql' => $Data['alt_sql'],
            ':alt_autoclose' => $Data['alt_autoclose'],
            ':alt_duration' => isset($Data['alt_duration']) && is_numeric($Data['alt_duration']) ? $Data['alt_duration'] : 0,
            ':alt_enabled' => isset($Data['alt_enabled']) && is_numeric($Data['alt_enabled']) ? $Data['alt_enabled'] : 0,
            ':alt_url' => $Data['alt_url'],
            ':alt_users' => $Data['alt_users'],
            ':alt_trigger' => $Data['alt_trigger'],
            ':alt_owner' => isset($Data['alt_owner']) && is_numeric($Data['alt_owner']) ? $Data['alt_owner'] : 0
        ));

        if ( is_numeric($resInsert) && $resInsert > 0 ) {

            $respuesta = array(
                'error'   => false,
                'data'    => [],
                'mensaje' => 'Alerta creada con exito'
            );

        } else {

            $respuesta = array(
                'error'   => true,
                'data'    => $resInsert,
                'mensaje' => 'No se pudo crear la alerta'
            );

        }

        $this->response($respuesta);
       
    }

    public function updateAlerts_post() {


        $Data = $this->post();


        $sqlUpdate = "UPDATE talt SET alt_name = :alt_name, alt_title = :alt_title, alt_message = :alt_message,
                    alt_type = :alt_type, alt_sql = :alt_sql, alt_autoclose = :alt_autoclose, alt_duration = :alt_duration,
                    alt_enabled = :alt_enabled, alt_users = :alt_users, alt_url = :alt_url, alt_trigger = :alt_trigger,
                    alt_owner = :alt_owner WHERE alt_id = :alt_id";
        
        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            
            ':alt_name' => $Data['alt_name'],
            ':alt_title' => $Data['alt_title'],
            ':alt_message' => $Data['alt_message'],
            ':alt_type' => $Data['alt_type'],
            ':alt_sql' => $Data['alt_sql'],
            ':alt_autoclose' => $Data['alt_autoclose'],
            ':alt_duration' => isset($Data['alt_duration']) && is_numeric($Data['alt_duration']) ? $Data['alt_duration'] : 0,
            ':alt_enabled' => isset($Data['alt_enabled']) && is_numeric($Data['alt_enabled']) ? $Data['alt_enabled'] : 0,
            ':alt_users' => $Data['alt_users'],
            ':alt_url' => $Data['alt_url'],
            ':alt_trigger' => $Data['alt_trigger'],
            ':alt_owner' => isset($Data['alt_owner']) && is_numeric($Data['alt_owner']) ? $Data['alt_owner'] : 0,
            ':alt_id' => $Data['alt_id']
        ));

        if ( is_numeric($resUpdate) && $resUpdate == 1 ) {

            $respuesta = array(
                'error'   => false,
                'data'    => [],
                'mensaje' => 'Alerta actualizada con exito'
            );

        } else {

            $respuesta = array(
                'error'   => true,
                'data'    => $resUpdate,
                'mensaje' => 'No se pudo actualizar la alerta'
            );

        }

        $this->response($respuesta);
       
    }

    public function getAlerts_get(){

        $sqlSelect = "SELECT * FROM talt";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if ( isset($resSelect[0]) ){
            $respuesta = array(
                'error'   => false,
                'data'    => $resSelect,
                'mensaje' => ''
            );
        }else{
            $respuesta = array(
                'error'   => true,
                'data'    => [],
                'mensaje' => 'Busqueda sin resultados'
            );
        }

        $this->response($respuesta);
    }

}
