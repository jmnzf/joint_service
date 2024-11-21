<?php
// RETENCIONES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class ExternalSuppliers extends REST_Controller
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
    public function getExternalSuppliers_get()
    {
        $sqlSelect = "SELECT * FROM dmpe";

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
    public function getExternalSuppliersById_get()
    {
        $Data =   $this->get();

        if (!isset($Data['mpe_id'])) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlSelect = "SELECT * FROM dmpe where mpe_id = :mpe_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":mpe_id" => $Data['mpe_id']));

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
    public function createExternalSuppliers_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['mpe_cardcode']) OR 
            !isset($Data['mpe_cardname']) OR
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
        $sqlInsert = "INSERT INTO dmpe (mpe_cardcode, mpe_cardname, business, branch)
        VALUES(:mpe_cardcode, :mpe_cardname, :business, :branch);";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":mpe_cardcode" => $Data['mpe_cardcode'], 
            ":mpe_cardname" => $Data['mpe_cardname'],
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
                'mensaje' => 'No se pudo crear la Proveedor externo'
            );
        }

        $this->response($respuesta);
    }
// METODO PARA ACTUALIZAR PROPIEDAD
    public function updateExternalSuppliers_post()
    {
        $Data = $this->post();

        if (            
            !isset($Data['mpe_cardcode']) OR 
            !isset($Data['mpe_cardname']) OR
            !isset($Data['business']) OR
            !isset($Data['branch']) OR
            !isset($Data['mpe_id'])
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlUpdate = "UPDATE dmpe SET
                         mpe_cardcode= :mpe_cardcode,
                         mpe_cardname= :mpe_cardname,
                         business = :business,
                         branch = :branch
                        WHERE mpe_id= :mpe_id";

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ":mpe_cardcode" => $Data['mpe_cardcode'], 
            ":mpe_cardname" => $Data['mpe_cardname'], 
            ":business" => $Data['business'],
            ":branch" => $Data['branch'],            
            ":mpe_id" => $Data['mpe_id']          
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
                'mensaje' => 'No se pudo crear el proveedor externo'
            );
        }

        $this->response($respuesta);
    }

    public function deleteExternalSuppliers_post(){

        $Data = $this->post();

        if (!isset($Data['mpe_id'])) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlDelete =  "DELETE FROM dmpe WHERE mpe_id = :mpe_id";

        $resDelete = $this->pedeo->deleteRow($sqlDelete, array(":mpe_id" => $Data['mpe_id']));
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
