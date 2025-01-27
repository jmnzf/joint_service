<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;



class Ubicaciones extends REST_Controller
{

    private $pdo;
    public function __construct()
    {
        header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding');
        header('Access-Control-Allow-Origin: *');
        parent::__construct();
        $this->load->database();
        $this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
    }



    public function create_post()
    {

        $Data = $this->post();

        if (
            !isset($Data['ubc_type']) or
            !isset($Data['ubc_code']) or
            !isset($Data['ubc_alto_cm']) or
            !isset($Data['ubc_ancho_cm']) or
            !isset($Data['ubc_largo_cm']) or
            !isset($Data['ubc_resistencia_kg']) or
            !isset($Data['ubc_status']) or
            !isset($Data['ubc_warehouse'])or
            !isset($Data['business'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resInsert = $this->pedeo->insertRow('INSERT INTO tubc(ubc_type, ubc_code, ubc_alto_cm, ubc_ancho_cm, ubc_largo_cm, ubc_resistencia_kg, ubc_status, ubc_warehouse, business) VALUES(:ubc_type, :ubc_code, :ubc_alto_cm, :ubc_ancho_cm, :ubc_largo_cm, :ubc_resistencia_kg, :ubc_status, :ubc_warehouse, :business)', array(
            ':ubc_type' => $Data['ubc_type'],
            ':ubc_code' => $Data['ubc_code'],
            ':ubc_alto_cm' => $Data['ubc_alto_cm'],
            ':ubc_ancho_cm' => $Data['ubc_ancho_cm'],
            ':ubc_largo_cm' => $Data['ubc_largo_cm'],
            ':ubc_resistencia_kg' => $Data['ubc_resistencia_kg'],
            ':ubc_status' => $Data['ubc_status'],
            ':ubc_warehouse' => $Data['ubc_warehouse'],
            ':business' => $Data['business']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {

            $respuesta = array(
                'error' => false, 'data' => $resInsert, 'mensaje' => 'Registro insertado con exito'
            );

            return $this->response($respuesta);
        } else {

            $respuesta = array(
                'error' => true, 'data' => $resInsert, 'mensaje' => 'Error al insertar el registro'
            );

            return $this->response($respuesta);
        }
    }



    public function update_post()
    {

        $Data = $this->post();

        if (
            !isset($Data['ubc_type']) or
            !isset($Data['ubc_code']) or
            !isset($Data['ubc_alto_cm']) or
            !isset($Data['ubc_ancho_cm']) or
            !isset($Data['ubc_largo_cm']) or
            !isset($Data['ubc_resistencia_kg']) or
            !isset($Data['ubc_status']) or
            !isset($Data['ubc_warehouse']) or
            !isset($Data['ubc_id'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resUpdate = $this->pedeo->updateRow('UPDATE tubc SET ubc_type = :ubc_type , ubc_code = :ubc_code , ubc_alto_cm = :ubc_alto_cm , ubc_ancho_cm = :ubc_ancho_cm , ubc_largo_cm = :ubc_largo_cm , ubc_resistencia_kg = :ubc_resistencia_kg , ubc_status = :ubc_status, ubc_warehouse = :ubc_warehouse  WHERE ubc_id = :ubc_id', array(
            'ubc_type' => $Data['ubc_type'],
            'ubc_code' => $Data['ubc_code'],
            'ubc_alto_cm' => $Data['ubc_alto_cm'],
            'ubc_ancho_cm' => $Data['ubc_ancho_cm'],
            'ubc_largo_cm' => $Data['ubc_largo_cm'],
            'ubc_resistencia_kg' => $Data['ubc_resistencia_kg'],
            'ubc_status' => $Data['ubc_status'],
            'ubc_warehouse' => $Data['ubc_warehouse'],
            'ubc_id' => $Data['ubc_id']
        ));

        if (is_numeric($resUpdate) && $resUpdate == 1) {

            $respuesta = array(
                'error' => false, 'data' => $resUpdate, 'mensaje' => 'Registro actualizado con exito'
            );

            return $this->response($respuesta);
        } else {

            $respuesta = array(
                'error' => true, 'data' => $resUpdate, 'mensaje' => 'Error al actualizar el registro'
            );

            return $this->response($respuesta);
        }
    }



    public function index_get()
    {
        $Data = $this->get();

        if ( !isset($Data['business']) or empty($Data['business'])) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }
     
        $sql = "SELECT
                    ubc_type ,
                    ubc_code ,
                    trim(to_char(ubc_alto_cm, '999G999G999G999G999D'||lpad('9',get_decimals(),'9'))) as ubc_alto_cm ,
                    trim(to_char(ubc_ancho_cm, '999G999G999G999G999D'||lpad('9',get_decimals(),'9'))) as ubc_ancho_cm,
                    trim(to_char(ubc_largo_cm, '999G999G999G999G999D'||lpad('9',get_decimals(),'9'))) as ubc_largo_cm,
                    trim(to_char(ubc_resistencia_kg, '999G999G999G999G999D'||lpad('9',get_decimals(),'9'))) as ubc_resistencia_kg,
                    ubc_id ,
                    CASE
                        WHEN ubc_status::numeric = 1
                            THEN 'Activo'
                        WHEN ubc_status::numeric = 0
                            THEN 'Inactivo'
                    END AS ubc_status,
                    ubc_warehouse,
                    dmws.dws_name AS nombre_almacen,
                    tdub.dub_name AS nombre_tipo
                FROM tubc
                LEFT JOIN dmws ON dmws.dws_code = tubc.ubc_warehouse and tubc.business = dmws.business
                LEFT JOIN tdub ON tdub.dub_code = tubc.ubc_type
                WHERE tubc.business = :business";
        $resSelect = $this->pedeo->queryTable($sql, array(':business' => $Data['business']));
        
        if (isset($resSelect[0])) {

            $respuesta = array(
                'error' => false, 'data' => $resSelect, 'mensaje' => ''
            );

            return $this->response($respuesta);
        } else {

            $respuesta = array(
                'error' => true, 'data' => $resSelect, 'mensaje' => 'Busqueda sin resultados'
            );

            return $this->response($respuesta);
        }
    }

    // OBTENER UBICACIONES POR ALMACEN
    public function getUbicationByWarehouse_get()
    {
        $Data = $this->get();
        
        if(!isset($Data['whscode']) OR !isset($Data['business'])){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlSelect = "SELECT tbdi.bdi_lote as ote_duedate ,tbdi.* FROM tbdi
                        WHERE bdi_itemcode = :itemcode
                        AND bdi_whscode = :codewarehouse
                        AND tbdi.business = :business
                        AND bdi_quantity > 0";
        // $sqlSelect = "SELECT concat(ubc_code, ' - ', tdub.dub_name) AS nombrecode, ubc_type , ubc_code , ubc_alto_cm , ubc_ancho_cm , ubc_largo_cm , ubc_resistencia_kg , ubc_id , CASE  WHEN ubc_status::numeric = 1 THEN 'Activo' WHEN ubc_status::numeric = 0 THEN 'Inactivo' END AS ubc_status, ubc_warehouse, dmws.dws_name AS nombre_almacen, tdub.dub_name AS nombre_tipo FROM tubc LEFT JOIN dmws ON dmws.whscode = tubc.ubc_warehouse LEFT JOIN tdub ON tdub.dub_code = tubc.ubc_type WHERE ubc_warehouse = :codewarehouse AND ubc_status = 1 AND tubc.business = :business ";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':codewarehouse' => $Data['whscode'], ':business' => $Data['business'], ':itemcode' => $Data['itemcode']));

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
    public function getUbicationByWarehouseId_get()
    {
        $Data = $this->get();
        
        if(!isset($Data['whscode']) OR !isset($Data['business'])){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        // $sqlSelect = "SELECT tbdi.bdi_lote as ote_duedate ,tbdi.* FROM tbdi
        //                 WHERE bdi_itemcode = :itemcode
        //                 AND bdi_whscode = :codewarehouse
        //                 AND tbdi.business = :business
        //                 AND bdi_quantity > 0";
        $sqlSelect = "SELECT concat(ubc_code, ' - ', tdub.dub_name) AS nombrecode, ubc_type , ubc_code , ubc_alto_cm , ubc_ancho_cm , ubc_largo_cm , ubc_resistencia_kg , ubc_id , CASE  WHEN ubc_status::numeric = 1 THEN 'Activo' WHEN ubc_status::numeric = 0 THEN 'Inactivo' END AS ubc_status, ubc_warehouse, dmws.dws_name AS nombre_almacen, tdub.dub_name AS nombre_tipo FROM tubc LEFT JOIN dmws ON dmws.whscode = tubc.ubc_warehouse LEFT JOIN tdub ON tdub.dub_code = tubc.ubc_type WHERE ubc_warehouse = :codewarehouse AND ubc_status = 1 AND tubc.business = :business ";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':codewarehouse' => $Data['whscode'], ':business' => $Data['business']));

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

    // OBTENER TIPOS DE UBICACIONES
    public function getTypeUbication_get()
    {
   
        $sqlSelect = " SELECT dub_code AS id, dub_name AS text FROM tdub WHERE dub_status = :dub_status ";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dub_status' => 1));

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
    // OBTENER UBICACIONES POR ALMACEN
    public function UbicationByWhsCode_get()
    {
        $Data = $this->get();
        
        if(!isset($Data['whscode']) OR !isset($Data['business'])){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlSelect = "SELECT concat(tubc.ubc_code, ' - ', tdub.dub_name) AS nombrecode, tubc.ubc_code FROM tubc INNER JOIN tdub ON tdub.dub_code = tubc.ubc_type WHERE tubc.ubc_warehouse = :ubc_warehouse AND tubc.ubc_status = 1 AND tubc.business = :business";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':ubc_warehouse' => $Data['whscode'], ':business' => $Data['business']));

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
}