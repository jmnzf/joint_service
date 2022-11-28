<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class RelationBBU extends REST_Controller
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

    // Ingresar nueva relacion
    public function insertBBU_post()
    {

        $DataRelation = $this->post();

        if (
            !isset($DataRelation['bbu_business']) or
            !isset($DataRelation['bbu_branch']) or
            !isset($DataRelation['bbu_user']) or
            !isset($DataRelation['bbu_status']) 
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlSelect = " SELECT * FROM pgus WHERE bbu_business = :bbu_business AND bbu_branch = :bbu_branch AND bbu_user = :bbu_user";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':bbu_business' => $DataRelation['bbu_business'], ':bbu_branch' => $DataRelation['bbu_branch'], ':bbu_user' => $DataRelation['bbu_user']));

        if (isset($resSelect[0])) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'Esta relacion ya existe'
            );

            $this->response($respuesta);
            return;
        }

        $sqlInsert = "INSERT INTO rbbu(bbu_business, bbu_branch, bbu_user, bbu_status) VALUES(:bbu_business, :bbu_branch, :bbu_user, :bbu_status)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ':bbu_business' => $DataRelation['bbu_business'],
            ':bbu_branch' => $DataRelation['bbu_branch'],
            ':bbu_user' => $DataRelation['bbu_user'],
            ':bbu_status' => $DataRelation['bbu_status'],
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {

            $respuesta = array(
                'error'     => false,
                'data'         => $resInsert,
                'mensaje' => 'Relacion creada con exito'
            );
        } else {

            $respuesta = array(
                'error'   => true,
                'data'         => $resInsert,
                'mensaje'    => 'No se pudo crear la relacion'
            );
        }

        $this->response($respuesta);
    }

    // Actualizar relacion
    public function updateBBU_post()
    {

        $DataRelation = $this->post();

        if (!isset($DataRelation['bbu_business'])) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlUpdate = "UPDATE rbbu SET bbu_business = :bbu_business, bbu_branch = :bbu_branch, bbu_user = :bbu_user, bbu_status = :bbu_status WHERE bbu_id = :bbu_id";


        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ':bbu_business' => $DataRelation['bbu_business'],
            ':bbu_branch' => $DataRelation['bbu_branch'],
            ':bbu_user' => $DataRelation['bbu_user'],
            ':bbu_status' => $DataRelation['bbu_status'],
            ':bbu_id' => $DataRelation['bbu_id']
        ));


        if (is_numeric($resUpdate) && $resUpdate == 1) {

            $respuesta = array(
                'error'   => false,
                'data'    => $resUpdate,
                'mensaje' => 'Relación actualizada con exito'
            );
        } else {

            $respuesta = array(
                'error'   => true,
                'data'    => $resUpdate,
                'mensaje'    => 'No se pudo actualizar la relación'
            );
        }

        $this->response($respuesta);
    }

    // Obtener lista de relaciones Empresa/Sucursal/Usuario
    public function getRelation_get()
    {

        $sqlSelect = "SELECT pgem.pge_small_name AS empresa, pges.pgs_small_name AS sucursal, concat(pgus.pgu_name_user, ' ', pgus.pgu_lname_user) AS usuario, rbbu.* FROM rbbu
        INNER JOIN pgem
        ON pgem.pge_id = rbbu.bbu_business
        INNER JOIN pges
        ON pges.pgs_id = rbbu.bbu_branch
        INNER JOIN pgus
        ON pgus.pgu_id_usuario = rbbu.bbu_user";

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

}
