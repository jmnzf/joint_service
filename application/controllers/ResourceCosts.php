<?php
// COSTOS DE RECURSOS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class ResourceCosts extends REST_Controller
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


    public function getResourceCosts_post()
    {
        $Data =  $this->post();
        $sqlSelect = "SELECT * FROM trrc where rrc_id = :rrc_resouceId";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':rrc_resouceId' => $Data['rrc_resouceId']));

        if (isset($resSelect[0])) {
            $respuesta = array(
                'error' => false,
                'data'  => $resSelect,
                'mensaje' => ''
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => $resSelect,
                'mensaje' => ''
            );
        }

        $this->response($respuesta);
    }

    public function createResourceCost_post()
    {
        $Data = $this->post();
        if (empty($Data['cost'])) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $costs = json_decode($Data['cost'], true);

        // print_r($costs);exit;
        // if (count($costs) < 1) {
        //     $respuesta = array(
        //         'error' => true,
        //         'data'  => array(),
        //         'mensaje' => 'La informacion enviada no es valida'
        //     );

        //     $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        //     return;
        // }


        $sql = "SELECT * FROM trrc where \"rrc_resourceId\" = :rrc_resourceId";

        $resourceId = $Data['resourceId'];
        $resSelect = $this->pedeo->queryTable($sql,array(":rrc_resourceId" =>$resourceId));

        if(isset($resSelect[0])){
            $this->pedeo->queryTable("DELETE FROM trrc WHERE \"rrc_resourceId\" = :rrc_resourceId", array(":rrc_resourceId" => $resourceId));
            $respuesta = array(
                'error' => false,
                'data'  => [],
                'mensaje' => 'Operacion exitosa'
            );
        }

       if(count($costs) > 0){
        $sqlInsert =  "INSERT INTO trrc (rrc_reference,rrc_cost, \"rrc_resourceId\", rrc_currency) VALUES(:rrc_reference,:rrc_cost, :rrc_resourceId, :rrc_currency)";
        $this->pedeo->trans_begin();
        foreach ($costs as $key => $cost) {
            $resInsert = $this->pedeo->insertRow($sqlInsert, array(
                ":rrc_reference" => $cost['rrc_reference'],
                ":rrc_cost" => $cost['rrc_cost'],
                ":rrc_resourceId" => $cost['rrc_resourceId'],
                ":rrc_currency" => $cost['rrc_currency'],
            ));

            if (is_numeric($resInsert) && $resInsert > 0) {
            } else {
                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error' => true,
                    'data'  => $resInsert,
                    'mensaje' => 'No se pudo crear el costo para este recurso'
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
       }

        $this->response($respuesta);
    }
}
