<?php
// MODELO DE APROBACIONES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class ProductionReceipt extends REST_Controller
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
    public function getProductionReceipt_get()
    {

        $respuesta = array(
            'error'  => true,
            'data'   => [],
            'mensaje' => 'busqueda sin resultados'
        );

        $sqlSelect = "SELECT * from tbrp";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if (isset($resSelect[0])) {
            $respuesta = array(
                'error'  => false,
                'data'   => $resSelect,
                'mensaje' => ''
            );
        }

        $this->response($respuesta);
    }

    public function setProductionReceipt_post()
    {
        $Data = $this->post();

        if (
            !isset($Data['brp_doctype']) or
            !isset($Data['brp_docnum']) or
            !isset($Data['brp_cardcode']) or
            !isset($Data['brp_duedev']) or
            !isset($Data['brp_docdate']) or
            !isset($Data['brp_ref'])
        ) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        // SE VERIFCA QUE EL DOCUMENTO TENGA DETALLE
        $ContenidoDetalle = json_decode($Data['detail'], true);

        if (!is_array($ContenidoDetalle)) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'No se encontro el detalle'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        // SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
        if (!intval(count($ContenidoDetalle)) > 0) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'Documento sin detalle'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        // print_r($ContenidoDetalle);exit;

        $sqlInsert = "INSERT INTO tbrp ( brp_doctype, brp_docnum, brp_cardcode, brp_cardname, brp_duedev, brp_docdate, brp_ref, brp_baseentry, brp_basetype, brp_description, brp_createby) VALUES(:brp_doctype, :brp_docnum, :brp_cardcode, :brp_cardname, :brp_duedev, :brp_docdate, :brp_ref, :brp_baseentry, :brp_basetype, :brp_description, :brp_createby)";

        $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":brp_doctype" => $Data['brp_doctype'],
            ":brp_docnum" => $Data['brp_docnum'],
            ":brp_cardcode" => $Data['brp_cardcode'],
            ":brp_cardname" => $Data['brp_cardname'],
            ":brp_duedev" => $Data['brp_duedev'],
            ":brp_docdate" => $Data['brp_docdate'],
            ":brp_ref" => $Data['brp_ref'],
            ":brp_baseentry" => isset($Data['brp_baseentry']) ? $Data['brp_baseentry'] : 0,
            ":brp_basetype" => isset($Data['brp_basetype']) ? $Data['brp_basetype'] : 0,
            ":brp_description" => isset($Data['brp_description']) ? $Data['brp_description'] : null,
            ":brp_createby" => $Data['brp_createby'],
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {
            $sqlInsert2 = "INSERT INTO brp1 (rp1_item_description, rp1_quantity, rp1_itemcost, rp1_im, rp1_ccost, rp1_ubusiness, rp1_item_code, rp1_listmat, rp1_baseentry, rp1_plan) values (:rp1_item_description, :rp1_quantity, :rp1_itemcost, :rp1_im, :rp1_ccost, :rp1_ubusiness, :rp1_item_code, :rp1_listmat, :rp1_baseentry, :rp1_plan)";

            foreach ($ContenidoDetalle as $key => $detail) {
                $resInsert2 = $this->pedeo->insertRow($sqlInsert2, array(
                    ":rp1_item_description" => $detail['rp1_item_description'],
                    ":rp1_quantity" => $detail['rp1_quantity'],
                    ":rp1_itemcost" => is_numeric($detail['rp1_itemcost'])?$detail['rp1_itemcost']:0,
                    ":rp1_im" => $detail['rp1_im'],
                    ":rp1_ccost" => $detail['rp1_ccost'],
                    ":rp1_ubusiness" => $detail['rp1_ubusiness'],
                    ":rp1_item_code" => $detail['rp1_item_code'],
                    ":rp1_listmat" => $detail['rp1_listmat'],
                    ":rp1_plan" => is_numeric($detail['rp1_plan'])?$detail['rp1_plan']:0,
                    ":rp1_baseentry" => $resInsert
                ));

                if (is_numeric($resInsert2) and $resInsert2 > 0) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsert2,
                        'mensaje' => 'No se pudo realizar operación'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }

            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' => 'Recepción de producción registrada con exito'
            );
        } else {
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo realizar operación'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $this->response($respuesta);
    }

    public function updateProductionReceipt_post(){
        $Data = $this->post();
        $respuesta = array();
        if (
            !isset($Data['brp_doctype']) or
            !isset($Data['brp_docnum']) or
            !isset($Data['brp_cardcode']) or
            !isset($Data['brp_cardname']) or
            !isset($Data['brp_duedev']) or
            !isset($Data['brp_docdate']) or
            !isset($Data['brp_ref'])
        ) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        // SE VERIFCA QUE EL DOCUMENTO TENGA DETALLE
        $ContenidoDetalle = json_decode($Data['detail'], true);

        if (!is_array($ContenidoDetalle)) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'No se encontro el detalle de la recepción'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        // SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
        if (!intval(count($ContenidoDetalle)) > 0) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'Documento sin detalle'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlUpdate = "UPDATE tbrp SET 
         brp_doctype = :brp_doctype,
         brp_docnum = :brp_docnum,
         brp_cardcode = :brp_cardcode,
         brp_cardname = :brp_cardname,
         brp_duedev = :brp_duedev,
         brp_docdate = :brp_docdate,
         brp_ref = :brp_ref,
         brp_baseentry = :brp_baseentry,
         brp_basetype = :brp_basetype,
         brp_description = :brp_description
         WHERE brp_docentry = :brp_docentry ";

         $this->pedeo->trans_begin();

         $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
         ":brp_doctype" => $Data['brp_doctype'],
         ":brp_docnum" => $Data['brp_docnum'],
         ":brp_cardcode" => $Data['brp_cardcode'],
         ":brp_cardname" => $Data['brp_cardname'],
         ":brp_duedev" => $Data['brp_duedev'],
         ":brp_docdate" => $Data['brp_docdate'],
         ":brp_ref" => $Data['brp_ref'],
         ":brp_baseentry" => isset($Data['brp_baseentry']) ? $Data['brp_baseentry'] : 0,
         ":brp_basetype" => isset($Data['brp_basetype']) ? $Data['brp_basetype'] : 0,
         ":brp_description" => isset($Data['brp_description']) ? $Data['brp_description'] : null,
         ":brp_docentry" => $Data['brp_docentry']));

         if(is_numeric($resUpdate) and $resUpdate > 0){

            $this->pedeo->queryTable("DELETE FROM brp1 WHERE rp1_baseentry = :brp_docentry", array(':brp_docentry' => $Data['brp_docentry']));


            $sqlInsert2 = "INSERT INTO brp1 (rp1_item_description, rp1_quantity, rp1_itemcost, rp1_im, rp1_ccost, rp1_ubusiness, rp1_item_code, rp1_listmat, rp1_baseentry, rp1_plan) values (:rp1_item_description, :rp1_quantity, :rp1_itemcost, :rp1_im, :rp1_ccost, :rp1_ubusiness, :rp1_item_code, :rp1_listmat, :rp1_baseentry, :rp1_plan)";

            foreach ($ContenidoDetalle as $key => $detail) {
                $resInsert2 = $this->pedeo->insertRow($sqlInsert2, array(
                    ":rp1_item_description" => $detail['rp1_item_description'],
                    ":rp1_quantity" => $detail['rp1_quantity'],
                    ":rp1_itemcost" => $detail['rp1_itemcost'],
                    ":rp1_im" => $detail['rp1_im'],
                    ":rp1_ccost" => $detail['rp1_ccost'],
                    ":rp1_ubusiness" => $detail['rp1_ubusiness'],
                    ":rp1_item_code" => $detail['rp1_item_code'],
                    ":rp1_listmat" => $detail['rp1_listmat'],
                    ":rp1_plan" => $detail['rp1_plan'],
                    ":rp1_baseentry" => $Data['brp_docentry']
                ));

                if (is_numeric($resInsert2) and $resInsert2 > 0) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsert2,
                        'mensaje' => 'No se pudo realizar operación'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }

            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resUpdate,
                'mensaje' => 'Recepción de producción actualizada con exito'
            );
         }else{
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
         }

         $this->response($respuesta);
    }
}
