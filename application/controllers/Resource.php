<?php
// RETENCIONES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Resource extends REST_Controller
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
    // METODO PARA OBTENER LISTADO DE RECURSOS
    public function getResource_get()
    {
        $sqlSelect = "SELECT * FROM dmrp";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if (isset($resSelect[0])) {
            $sqlTopics = "SELECT rrp_idproperty from trrp where rrp_idresource = :rrp_idresource";
            $sqlCost = "SELECT * from trrc where \"rrc_resourceId\" = :rrc_resourceId";
            foreach ($resSelect as $key => $resource) {
                $resTopics = $this->pedeo->queryTable($sqlTopics, array(":rrp_idresource" => $resource['mrp_id']));
                $resCost = $this->pedeo->queryTable($sqlCost, array(":rrc_resourceId" => $resource['mrp_id']));
                $resSelect[$key]['topics'] = $resTopics;
                $resSelect[$key]['cost'] = $resCost;
            }

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

    // METODO PARA OBTENER RECURSOS POR ID 
    public function getRourceById_get()
    {
        $Data =   $this->get();

        if (!isset($Data['mrp_id'])) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlSelect = "SELECT * FROM dmrp where mrp_id = :mrp_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":mrp_id" => $Data['mrp_id']));

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

    // METODO PAR CREAR RECURSOS
    public function createResource_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['mrp_serie']) or
            !isset($Data['mrp_description']) or
            !isset($Data['mrp_foringname']) or
            !isset($Data['mrp_type']) or
            !isset($Data['mrp_group']) or
            !isset($Data['mrp_barcode']) or
            !isset($Data['mrp_method']) or
            !isset($Data['mrp_um']) or 
            !isset($Data['mrp_quantity']) 
           // !isset($Data['mrp_comments'])
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlInsert = "INSERT INTO dmrp (mrp_serie,mrp_description,mrp_foringname,mrp_type,mrp_group,mrp_barcode,mrp_method,mrp_assing,mrp_relationart,mrp_um,mrp_quantity,mrp_comments) 
        values (:mrp_serie,:mrp_description,:mrp_foringname,:mrp_type,:mrp_group,:mrp_barcode,:mrp_method,:mrp_assing,:mrp_relationart,:mrp_um,:mrp_quantity,:mrp_comments)";

        $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":mrp_serie" => $Data['mrp_serie'],
            ":mrp_description" => $Data['mrp_description'],
            ":mrp_foringname" => $Data['mrp_foringname'],
            ":mrp_type" => $Data['mrp_type'],
            ":mrp_group" => $Data['mrp_group'],
            ":mrp_barcode" => $Data['mrp_barcode'],
            ":mrp_method" => $Data['mrp_method'],
            ":mrp_assing" => isset($Data['mrp_assing']) ? $Data['mrp_assing'] : null,
            ":mrp_relationart" => isset($Data['mrp_relationart']) ? $Data['mrp_relationart'] : null,
            ":mrp_um" => $Data['mrp_um'],
            ":mrp_quantity" => $Data['mrp_quantity'],
            ":mrp_comments" => $Data['mrp_comments']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {

            $sqlInsertRelation = "INSERT INTO trrp (rrp_idresource,rrp_idproperty) VALUES(:rrp_idresource, :rrp_idproperty)";
            foreach ($Data['topics'] as $key => $topic) {
                $resInsertRelation = $this->pedeo->insertRow(
                    $sqlInsertRelation,
                    array(':rrp_idresource' => $resInsert, ':rrp_idproperty' => $topic)
                );

                if (is_numeric($resInsertRelation) && $resInsertRelation > 0) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data'  => $resInsertRelation,
                        'mensaje' => 'No se pudo crear el recurso'
                    );
                    $this->response($respuesta);

                    return;
                }
            }
            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data'  => $resInsert,
                'mensaje' => 'Operacion exitosa'
            );
        } else {
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error' => true,
                'data'  => $resInsert,
                'mensaje' => 'No se pudo crear el recurso'
            );
        }

        $this->response($respuesta);
    }
    // METODO PARA ACTUALIZAR RECURSO
    public function updateResource_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['mrp_id']) or
            !isset($Data['mrp_serie']) or
            !isset($Data['mrp_description']) or
            !isset($Data['mrp_foringname']) or
            !isset($Data['mrp_type']) or
            !isset($Data['mrp_group']) or
            !isset($Data['mrp_barcode']) or
            !isset($Data['mrp_method']) or
            !isset($Data['mrp_um']) or
            !isset($Data['mrp_quantity']) or
            !isset($Data['mrp_comments'])
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        $sqlUpdate = "UPDATE dmrp SET mrp_serie = :mrp_serie,
        mrp_description = :mrp_description,
        mrp_foringname = :mrp_foringname,
        mrp_type = :mrp_type,
        mrp_group = :mrp_group,
        mrp_barcode = :mrp_barcode,
        mrp_method = :mrp_method,
        mrp_assing = :mrp_assing,
        mrp_relationart = :mrp_relationart,
        mrp_um = :mrp_um,
        mrp_quantity = :mrp_quantity,
        mrp_comments = :mrp_comments
        WHERE mrp_id = :mrp_id";

        $this->pedeo->trans_begin();

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ":mrp_serie" => $Data['mrp_serie'],
            ":mrp_description" => $Data['mrp_description'],
            ":mrp_foringname" => $Data['mrp_foringname'],
            ":mrp_type" => $Data['mrp_type'],
            ":mrp_group" => $Data['mrp_group'],
            ":mrp_barcode" => $Data['mrp_barcode'],
            ":mrp_method" => $Data['mrp_method'],
            ":mrp_assing" => isset($Data['mrp_assing']) ? $Data['mrp_assing'] : null,
            ":mrp_relationart" => isset($Data['mrp_relationart']) ? $Data['mrp_relationart'] : null,
            ":mrp_um" => $Data['mrp_um'],
            ":mrp_quantity" => $Data['mrp_quantity'],
            ":mrp_comments" => $Data['mrp_comments'],
            ":mrp_id" => $Data['mrp_id']
        ));

        if (is_numeric($resUpdate) && $resUpdate == 1) {

            $this->pedeo->queryTable("DELETE FROM trrp WHERE rrp_idresource = :rrp_idresource", array($Data['mrp_id']));

            $sqlInsertRelation = "INSERT INTO trrp (rrp_idresource,rrp_idproperty) VALUES(:rrp_idresource, :rrp_idproperty)";
            foreach ($Data['topics'] as $key => $topic) {
                $resInsertRelation = $this->pedeo->insertRow(
                    $sqlInsertRelation,
                    array(':rrp_idresource' => $Data['mrp_id'], ':rrp_idproperty' => $topic)
                );

                if (is_numeric($resInsertRelation) && $resInsertRelation > 0) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data'  => $resInsertRelation,
                        'mensaje' => 'No se pudo crear el recurso'
                    );
                    $this->response($respuesta);

                    return;
                }
            }

            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data'  => $resUpdate,
                'mensaje' => 'Operacion exitosa'
            );
        } else {
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error' => true,
                'data'  => $resUpdate,
                'mensaje' => 'No se pudo actualizar el recurso'
            );
        }

        $this->response($respuesta);
    }

    public function getResourcesLM_get ()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = 'SELECT 
                    d.mrp_id ,
                    d.mrp_serie ,
                    d.mrp_description ,
                    d.mrp_um ,
                    d.mrp_quantity ,
                    coalesce(sum(t.rrc_cost),0) as costo
                from dmrp d 
                left join trrc t on d.mrp_id = t."rrc_resourceId" 
                group by 
                    d.mrp_id ,
                    d.mrp_serie ,
                    d.mrp_description ,
                    d.mrp_um ,
                    d.mrp_quantity 
                order by mrp_id asc';
        $resSql = $this->pedeo->queryTable($sql,array());

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }

        $this->response($respuesta);
    }
}
