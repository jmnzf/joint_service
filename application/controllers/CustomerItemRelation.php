<?php
// RETENCIONES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class CustomerItemRelation extends REST_Controller
{

    private $pdo;

    public function __construct()
    {

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        parent::__construct();
        $this->load->database();
        $this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
    }
    // METODO PARA OBTENER LISTADO DE PROPIEDADES
    public function getCustomerItemsRelation_get()
    {
        $sqlSelect = "SELECT * FROM trat";

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
                'mensaje'    => 'busqueda sin resultados'
            );
        }

        $this->response($respuesta);
    }

    // METODO PARA OBTENER PROPIEDAD POR ID 
    public function getCustomerItemsRelationd_get()
    {
        $Data =   $this->get();

        if (!isset($Data['rat_id'])) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlSelect = "SELECT * FROM trat where rat_id = :rat_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":rat_id" => $Data['rat_id']));

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
                'mensaje'    => 'busqueda sin resultados'
            );
        }

        $this->response($respuesta);
    }
    //  METODO PARA CREAR PROPIEDADES
    public function createCustomerItemsRelation_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['rat_item_code']) OR 
            !isset($Data['rat_relationed_item_code']) OR 
            !isset($Data['rat_cardcode']) OR 
            !isset($Data['rat_date']) OR
            !isset($Data['business']) OR
            !isset($Data['branch']) 

        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlInsert = "INSERT INTO trat (rat_item_code, rat_relationed_item_code, rat_cardcode, rat_date, business, branch)
        VALUES(:rat_item_code, :rat_relationed_item_code, :rat_cardcode, :rat_date, :business, :branch);";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":rat_item_code" => $Data['rat_item_code'], 
            ":rat_relationed_item_code" => $Data['rat_relationed_item_code'], 
            ":rat_cardcode" => $Data['rat_cardcode'],
            ":rat_date" => $Data['rat_date'],
            ":business" => $Data['business'],
            ":branch" => $Data['branch']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {
            $respuesta = array(
                'error' => false,
                'data'  => $resInsert,
                'mensaje' => 'Operacion exitosa'
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => $resInsert,
                'mensaje' => 'No se pudo crear la Relacion'
            );
        }

        $this->response($respuesta);
    }
// METODO PARA ACTUALIZAR PROPIEDAD
    public function updateCustomerItemsRelation_post()
    {
        $Data = $this->post();

        if (            
            !isset($Data['rat_item_code']) OR 
            !isset($Data['rat_relationed_item_code']) OR 
            !isset($Data['rat_cardcode']) OR 
            !isset($Data['rat_date']) OR
            !isset($Data['rat_id']) OR
            !isset($Data['business']) OR
            !isset($Data['branch']) 
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlUpdate = "UPDATE trat SET
                         rat_item_code= :rat_item_code,
                         rat_relationed_item_code= :rat_relationed_item_code,
                         rat_cardcode= :rat_cardcode,
                         rat_date= :rat_date,
                         business = :business,
                         branch = :branch
                        WHERE rat_id= :rat_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ":rat_item_code" => $Data['rat_item_code'], 
            ":rat_relationed_item_code" => $Data['rat_relationed_item_code'], 
            ":rat_cardcode" => $Data['rat_cardcode'],
            ":rat_date" => $Data['rat_date'],
            ":business" => $Data['business'],
            ":branch" => $Data['branch'],            
            ":rat_id" => $Data['rat_id']          
        ));

        if (is_numeric($resUpdate) && $resUpdate > 0) {
            $respuesta = array(
                'error' => false,
                'data'  => $resUpdate,
                'mensaje' => 'Operacion exitosa'
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => $resUpdate,
                'mensaje' => 'No se pudo crear la propiedad'
            );
        }

        $this->response($respuesta);
    }

    public function getCustomerItemsRelationFilter_post(){
        $Data = $this->post();

        $array = [];
        $sqlSelect = 'SELECT * FROM trat where 1= 1';

        if(isset($Data['rat_itemcode']) && !empty($Data['rat_itemcode'])) {
            $sqlSelect .= " AND rat_item_code = :rat_item_code"; 
            $array[':rat_itemcode'] = $Data['rat_itemcode'];
        }
        if(isset($Data['rat_relationed_item_code']) && !empty($Data['rat_relationed_item_code'])) {
            $sqlSelect .= " AND rat_relationed_item_code = :rat_relationed_item_code";
            $array[':rat_relationed_item_code'] = $Data['rat_relationed_item_code'];
        }
        if(isset($Data['rat_cardcode']) && !empty($Data['rat_cardcode'])){ 
            $sqlSelect .= " AND rat_cardcode = :rat_cardcode"; 
            $array[':rat_cardcode'] = $Data['rat_cardcode'];
        }
        // print_r($sqlSelect);exit;
        $resSelect = $this->pedeo->queryTable($sqlSelect,$array);

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
                'mensaje'    => 'busqueda sin resultados'
            );
        }

        $this->response($respuesta);
    }

    public function deleteCustomerItemsRelation_post(){

        $Data = $this->post();

        if (!isset($Data['rat_id'])) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlDelete =  "DELETE FROM trat WHERE rat_id = :rat_id";

        $resDelete = $this->pedeo->deleteRow($sqlDelete, array(":rat_id" => $Data['rat_id']));
        if ( is_numeric($resDelete) && $resDelete > 0 ){
            $respuesta = array(
                'error' => false,
                'data'  => $resDelete,
                'mensaje' => 'Operacion exitosa'
            );
		}else{

			$respuesta = array(
				'error'   => true,
				'data'    => $resDelete,
				'mensaje'	=> 'No se pudo eliminar el registro'
			);
			return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		}

        $this->response($respuesta);
    }
}
