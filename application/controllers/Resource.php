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
        
        $Data = $this->get();

        $sqlSelect ="SELECT * from dmrp WHERE dmrp.business = :business";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":business" => $Data['business']));

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

        $sqlSelect ="SELECT cdrs.*, dcpr.*
                    FROM dmrp
                    INNER JOIN dcpr ON dmrp.mrp_id = dcpr.crp_id_recurso
                    INNER JOIN cdrs ON dcpr.crp_id_concepto = cdrs.drs_id WHERE dmrp.business = :business AND mrp_id = :mrp_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":business" => $Data['business'], ":mrp_id" => $Data['mrp_id']));

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
        $sqlInsert = "INSERT INTO dmrp (mrp_serie,mrp_description,mrp_foringname,mrp_type,mrp_group,mrp_barcode,mrp_method,mrp_assing,mrp_relationart,mrp_um,mrp_quantity,mrp_comments, business) 
        values (:mrp_serie,:mrp_description,:mrp_foringname,:mrp_type,:mrp_group,:mrp_barcode,:mrp_method,:mrp_assing,:mrp_relationart,:mrp_um,:mrp_quantity,:mrp_comments, :business)";

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
            ":mrp_comments" => $Data['mrp_comments'],
            ":business" => $Data['business']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {

            $sqlConcepto = "INSERT INTO dcpr (crp_id_recurso,crp_id_concepto,crp_valor) VALUES(:crp_id_recurso,:crp_id_concepto,:crp_valor)";

            if (isset($Data['concepto']) && is_array($Data['concepto'])) {

            foreach ($Data['concepto'] as $key => $concepto) {

                    $resConcepto = $this->pedeo->insertRow($sqlConcepto, array(
                        
                            ':crp_id_recurso' => $resInsert,
                            ':crp_id_concepto' => $concepto['idConcepto'],
                            ':crp_valor' => $concepto['valorConcepto']
                        )
                );

                if (is_numeric($resConcepto) && $resConcepto > 0) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data'  => $resConcepto,
                        'mensaje' => 'No se pudo crear el recurso'
                    );
                    $this->response($respuesta);

                    return;
                }
            }

            }else{

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error' => true,
                    'data'  => [],
                    'mensaje' => 'No se encontro el concepto del recurso'
                );

                $this->response($respuesta);
    
                return;
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

            $this->pedeo->queryTable("DELETE FROM dcpr WHERE crp_id_recurso = :crp_id_recurso", array($Data['mrp_id']));

            $sqlConcepto = "INSERT INTO dcpr (crp_id_recurso,crp_id_concepto,crp_valor) VALUES(:crp_id_recurso,:crp_id_concepto,:crp_valor)";

            if (isset($Data['concepto']) && is_array($Data['concepto'])) {

            foreach ($Data['concepto'] as $key => $concepto) {

                    $resConcepto = $this->pedeo->insertRow($sqlConcepto, array(
                        
                            ':crp_id_recurso'  => $Data['mrp_id'],
                            ':crp_id_concepto' => $concepto['idConcepto'],
                            ':crp_valor'       => $concepto['valorConcepto']
                        )
                );

                if (is_numeric($resConcepto) && $resConcepto > 0) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data'  => $resConcepto,
                        'mensaje' => 'No se pudo crear el recurso'
                    );
                    $this->response($respuesta);

                    return;
                }
            }

        }else{

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error' => true,
                    'data'  => [],
                    'mensaje' => 'No se encontro el concepto del recurso'
                );

                $this->response($respuesta);
    
                return;
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
                    coalesce(sum(t.rrc_cost),0) as costo,
                    d.mrp_method
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
