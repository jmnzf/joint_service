<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;



class TipodeAnuncios extends REST_Controller
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
            !isset($Data['bta_name']) or
            !isset($Data['bta_status'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resInsert = $this->pedeo->insertRow('INSERT INTO tbta(bta_name, bta_combo, bta_libre, bta_dias_cons, bta_dias, bta_precio, bta_palabras, bta_letras, bta_cantidad, bta_status) VALUES(:bta_name, :bta_combo, :bta_libre, :bta_dias_cons, :bta_dias, :bta_precio, :bta_palabras, :bta_letras, :bta_cantidad, :bta_status)', array(
            ':bta_name' => $Data['bta_name'],
            ':bta_combo' => $Data['bta_combo'],
            ':bta_libre' => $Data['bta_libre'],
            ':bta_dias_cons' => $Data['bta_dias_cons'],
            ':bta_dias' => isset($Data['bta_dias']) ? $Data['bta_dias']: 0,
            ':bta_precio' => isset($Data['bta_precio']) ? $Data['bta_precio']: 0,
            ':bta_palabras' => isset($Data['bta_palabras']) ? $Data['bta_palabras']: 0,
            ':bta_letras' => isset($Data['bta_letras']) ? $Data['bta_letras']: 0,
            ':bta_cantidad' => isset($Data['bta_cantidad']) ? $Data['bta_cantidad']: 0,
            ':bta_status' => $Data['bta_status']
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
            !isset($Data['bta_name']) or
            !isset($Data['bta_status']) or
            !isset($Data['bta_id'])
        ) {

            $respuesta = array(
                'error' => true, 'data' => array(), 'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resUpdate = $this->pedeo->updateRow('UPDATE tbta SET bta_name = :bta_name , bta_combo = :bta_combo , bta_libre = :bta_libre , bta_dias_cons = :bta_dias_cons , bta_cantidad = :bta_cantidad , bta_dias = :bta_dias , bta_precio = :bta_precio , bta_palabras = :bta_palabras , bta_letras = :bta_letras , bta_status = :bta_status  WHERE bta_id = :bta_id', array(
            ':bta_name' => $Data['bta_name'],
            ':bta_combo' => $Data['bta_combo'],
            ':bta_libre' => $Data['bta_libre'],
            ':bta_dias_cons' => $Data['bta_dias_cons'],
            ':bta_dias' => isset($Data['bta_dias']) ? $Data['bta_dias']: 0,
            ':bta_precio' => isset($Data['bta_precio']) ? $Data['bta_precio']: 0,
            ':bta_palabras' => isset($Data['bta_palabras']) ? $Data['bta_palabras']: 0,
            ':bta_letras' => isset($Data['bta_letras']) ? $Data['bta_letras']: 0,
            ':bta_cantidad' => isset($Data['bta_cantidad']) ? $Data['bta_cantidad']: 0,
            ':bta_status' => $Data['bta_status'],
            ':bta_id' => $Data['bta_id']
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
        $resSelect = $this->pedeo->queryTable("SELECT * FROM tbta", array());

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
}
