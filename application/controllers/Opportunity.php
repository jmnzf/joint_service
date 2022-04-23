<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') or exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH . '/asset/vendor/autoload.php');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Opportunity extends REST_Controller
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

    public function CreateOpportunity_post()
    {

        $Data = $this->post();
        $respuesta = array();

        if (
            !isset($Data['bop_type'])    or
            !isset($Data['bop_invamount'])  or
            !isset($Data['bop_slpcode_']) or
            !isset($Data['bop_agent_'])     or
            !isset($Data['bop_balance']) or
            !isset($Data['bop_name'])    or
            !isset($Data['bop_status'])    or
            !isset($Data['bop_date'])    or
            !isset($Data['bop_duedate'])  or
            !isset($Data['bop_days'])    or
            !isset($Data['bop_dateprev'])    or
            !isset($Data['bop_pvalue'])    or
            !isset($Data['bop_interestl'])  or
            !isset($Data['bop_rstatus'])
        ) {

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
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
                'mensaje' => 'No se encontro el detalle de la entrada de oportunidad'
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

        $sqlInsert = "INSERT INTO tbop( bop_type, bop_invamount, bop_slpcode, bop_agent, bop_balance, bop_name, bop_docnum, bop_status, bop_date, bop_duedate, bop_days, bop_dateprev, bop_pvalue, bop_interestl, bop_rstatus,bop_cardcode, bop_cardcode_name, bop_reason) 
                        VALUES ( :bop_type, :bop_invamount, :bop_slpcode, :bop_agent, :bop_balance, :bop_name, :bop_docnum, :bop_status, :bop_date, :bop_duedate, :bop_days, :bop_dateprev, :bop_pvalue, :bop_interestl, :bop_rstatus, :bop_cardcode, :bop_cardcode_name, :bop_reason)";
        $this->pedeo->trans_begin();
        $resInsert = $this->pedeo->insertRow(
            $sqlInsert,
            array(
                ":bop_type" => $Data['bop_type'],
                ":bop_invamount" => $Data['bop_invamount'],
                ":bop_slpcode" => $Data['bop_slpcode_'],
                ":bop_agent" => (!empty($Data['bop_agent_'])) ? $Data['bop_agent_'] : 0,
                ":bop_balance" => (!empty($Data['bop_balance'])) ? $Data['bop_balance'] : 0,
                ":bop_name" => $Data['bop_name'],
                ":bop_docnum" => (!empty($Data['docnum'])) ? $Data['bop_docnum'] : 0,
                ":bop_status" => $Data['bop_status'],
                ":bop_date" => $Data['bop_date'],
                ":bop_duedate" => $Data['bop_duedate'],
                ":bop_days" => $Data['bop_days'],
                ":bop_dateprev" => $Data['bop_dateprev'],
                ":bop_pvalue" => $Data['bop_pvalue'],
                ":bop_interestl" => $Data['bop_interestl'],
                ":bop_rstatus" => $Data['bop_rstatus'],
                ":bop_cardcode" => $Data['bop_cardcode'],
                ":bop_cardcode_name" => $Data['bop_cardcode_name'],
                ":bop_reason" => (isset($Data['bop_reason'])) ? $Data['bop_reason'] : null
                )
        );

        if (is_numeric($resInsert) and $resInsert > 0) {

            $sqlInsert2 = "INSERT INTO bop1 ( op1_centerc, op1_comments, op1_content, op1_contact_tel, op1_date, op1_dclass, op1_duedate,op1_duracion, op1_issue, op1_num, op1_priority, op1_sn, op1_sn_name, op1_type, op1_userfrom, op1_userto,op1_opportunity)
                VALUES(:op1_centerc, :op1_comments, :op1_content, :op1_contact_tel, :op1_date, :op1_dclass, :op1_duedate,:op1_duracion, :op1_issue, :op1_num, :op1_priority, :op1_sn, :op1_sn_name, :op1_type, :op1_userfrom,:op1_userto,:op1_opportunity)";

            foreach ($ContenidoDetalle as $key => $detail) {
                $duracion = (!empty($detail['op1_duracion'])) ? explode(' ', $detail['op1_duracion']) : 0;
                $resInsert2 = $this->pedeo->insertRow($sqlInsert2, array(
                    ":op1_centerc"  => $detail['op1_centerc'],
                    ":op1_comments" => $detail['op1_comments'],
                    ":op1_contact_tel" => $detail['op1_contact_tel'],
                    ":op1_content" => $detail['op1_content'],
                    ":op1_date" => $detail['op1_date'],
                    ":op1_dclass" => $detail['op1_dclass'],
                    ":op1_duedate" => $detail['op1_duedate'],
                    ":op1_duracion" => (!empty($detail['op1_duracion'])) ? $duracion[0] : 0,
                    ":op1_issue" => $detail['op1_issue'],
                    ":op1_num" => (!empty($detail['op1_num'])) ? $detail['op1_num'] : 0,
                    ":op1_priority" => (!empty($detail['op1_priority'])) ? $detail['op1_priority'] : 1,
                    ":op1_sn" => $detail['op1_sn'],
                    ":op1_sn_name" => $detail['op1_sn_name'],
                    ":op1_type" => $detail['op1_type'],
                    ":op1_userfrom" => $detail['op1_userfrom'],
                    ":op1_userto" => $detail['op1_userto'],
                    ':op1_opportunity' => $resInsert
                ));

                if (is_numeric($resInsert2) && $resInsert2 > 0) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsert2,
                        'mensaje' => 'No se puso realizar operacion'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }

            // GUARDA LOS CAMBIOS SI TODO VA BIEN
            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' => 'Oportunidad registrada con exito'
            );
        } else {
            $this->pedeo->trans_rollback();
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se puso realizar operacion'
            );
        }

        $this->response($respuesta);
    }

    public function getOpportunity_get()
    {
        $sqlSelect = "SELECT * FROM tbop";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());
        $respuesta = array();

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

    public function createStage_post(){
        $Data = $this->post();

        if (!isset($Data['op1_centerc']) OR
            !isset($Data['op1_comments']) OR
            !isset($Data['op1_contact_tel']) OR
            !isset($Data['op1_content']) OR
            !isset($Data['op1_date']) OR
            !isset($Data['op1_dclass']) OR
            !isset($Data['op1_duedate']) OR
            !isset($Data['op1_duracion']) OR
            !isset($Data['op1_issue']) OR
            !isset($Data['op1_num']) OR
            !isset($Data['op1_priority']) OR
            !isset($Data['op1_sn']) OR
            !isset($Data['op1_sn_name']) OR
            !isset($Data['op1_type']) OR
            !isset($Data['op1_userfrom']) OR
            !isset($Data['op1_userto']) OR
            !isset($Data['op1_opportunity'])) 
            {
            
                $respuesta = array(
                    'error' => true,
                    'data'  => array(),
                    'mensaje' =>'La informacion enviada no es valida'
                );
    
                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
    
                return;
        }

        $sqlInsert = "INSERT INTO bop1 ( op1_centerc, op1_comments, op1_content, op1_contact_tel, op1_date, op1_dclass, op1_duedate,op1_duracion, op1_issue, op1_num, op1_priority, op1_sn, op1_sn_name, op1_type, op1_userfrom, op1_userto,op1_opportunity)
                VALUES(:op1_centerc, :op1_comments, :op1_content, :op1_contact_tel, :op1_date, :op1_dclass, :op1_duedate,:op1_duracion, :op1_issue, :op1_num, :op1_priority, :op1_sn, :op1_sn_name, :op1_type, :op1_userfrom,:op1_userto,:op1_opportunity)";

        $duracion = (!empty($Data['op1_duracion'])) ? explode(' ', $Data['op1_duracion']) : 0;  
        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":op1_centerc"  => $Data['op1_centerc'],
            ":op1_comments" => $Data['op1_comments'],
            ":op1_contact_tel" => $Data['op1_contact_tel'],
            ":op1_content" => $Data['op1_content'],
            ":op1_date" => $Data['op1_date'],
            ":op1_dclass" => $Data['op1_dclass'],
            ":op1_duedate" => $Data['op1_duedate'],
            ":op1_duracion" => (!empty($Data['op1_duracion'])) ? $duracion[0] : 0,
            ":op1_issue" => $Data['op1_issue'],
            ":op1_num" => (!empty($Data['op1_num'])) ? $Data['op1_num'] : 0,
            ":op1_priority" => (!empty($Data['op1_priority'])) ? $Data['op1_priority'] : 1,
            ":op1_sn" => $Data['op1_sn'],
            ":op1_sn_name" => $Data['op1_sn_name'],
            ":op1_type" => $Data['op1_type'],
            ":op1_userfrom" => $Data['op1_userfrom'],
            ":op1_userto" => $Data['op1_userto'],
            ':op1_opportunity' => $Data['op1_opportunity']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {
            $respuesta = array(
                'error' => false,
                'data'  => array(),
                'mensaje' =>'Operacion exitosa'
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'No se pudo agregar etapa a esta oportunidad'
            );
        }

        $this->response($respuesta);
        
    }

    public function getOpportunityById_get()
    {
        $sqlSelect = "SELECT * FROM tbop WHERE bop_id = :bop_id";
        $Data = $this->get();
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":bop_id" => $Data['bop_id']));
        $respuesta = array();

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
    public function getOpportunityDetail_get()
    {
        $sqlSelect = "SELECT * FROM bop1 WHERE op1_opportunity = :op1_opportunity and op1_status is null ";
        $Data = $this->get();
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":op1_opportunity" => $Data['op1_opportunity']));
        $respuesta = array();

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

    public function updateOpportunity_post(){
        $Data = $this->post();

        
        // SE VERIFCA QUE EL DOCUMENTO TENGA DETALLE
        $ContenidoDetalle = json_decode($Data['detail'], true);
       
        $respuesta = array();
        if (!is_array($ContenidoDetalle)) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'No se encontro el detalle de la entrada de oportunidad'
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


        $sqlUpdate = "UPDATE tbop SET 
         bop_type = :bop_type,
         bop_invamount = :bop_invamount,
         bop_slpcode = :bop_slpcode,
         bop_agent = :bop_agent,
         bop_balance = :bop_balance,
         bop_name = :bop_name,
         bop_docnum = :bop_docnum,
         bop_status = :bop_status,
         bop_date = :bop_date,
         bop_duedate = :bop_duedate,
         bop_days = :bop_days,
         bop_dateprev = :bop_dateprev,
         bop_pvalue = :bop_pvalue,
         bop_interestl = :bop_interestl,
         bop_rstatus = :bop_rstatus,
         bop_cardcode = :bop_cardcode,
         bop_cardcode_name = :bop_cardcode_name,
         bop_reason = :bop_reason,
         bop_stage = :bop_stage
        WHERE bop_id = :bop_id";
        
        $this->pedeo->trans_begin();

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
        ":bop_type" =>$Data['bop_type'],
        ":bop_invamount" =>$Data['bop_invamount'],
        ":bop_slpcode" =>$Data['bop_slpcode_'],
        ":bop_agent" =>$Data['bop_agent_'],
        ":bop_balance" =>$Data['bop_balance'],
        ":bop_name" =>$Data['bop_name'],
        ":bop_docnum" =>$Data['bop_docnum'],
        ":bop_status" =>$Data['bop_status'],
        ":bop_date" =>$Data['bop_date'],
        ":bop_duedate" =>$Data['bop_duedate'],
        ":bop_days" =>$Data['bop_days'],
        ":bop_dateprev" =>$Data['bop_dateprev'],
        ":bop_pvalue" =>$Data['bop_pvalue'],
        ":bop_interestl" =>$Data['bop_interestl'],
        ":bop_rstatus" =>$Data['bop_rstatus'],
        ":bop_cardcode" =>$Data['bop_cardcode'],
        ":bop_cardcode_name" =>$Data['bop_cardcode_name'],
        ":bop_reason" =>(isset($Data['bop_reason'])) ? $Data['bop_reason'] : null,
        ":bop_stage" =>$Data['bop_stage'],
        ":bop_id" => $Data['bop_id']));

        if (is_numeric($resUpdate) && $resUpdate > 0) {
            // se le cambia el estado de los detalles de la oportunidad
            $this->pedeo->updateRow('UPDATE bop1 set op1_status = 0 WHERE op1_opportunity = :op1_opportunity',array(":op1_opportunity" => $Data['bop_id']));


            $sqlInsert2 = "INSERT INTO bop1 ( op1_centerc, op1_comments, op1_content, op1_contact_tel, op1_date, op1_dclass, op1_duedate,op1_duracion, op1_issue, op1_num, op1_priority, op1_sn, op1_sn_name, op1_type, op1_userfrom, op1_userto,op1_opportunity, op1_stage, op1_dnumber)
            VALUES(:op1_centerc, :op1_comments, :op1_content, :op1_contact_tel, :op1_date, :op1_dclass, :op1_duedate,:op1_duracion, :op1_issue, :op1_num, :op1_priority, :op1_sn, :op1_sn_name, :op1_type, :op1_userfrom, :op1_userto, :op1_opportunity, :op1_stage, :op1_dnumber )";
        
        foreach ($ContenidoDetalle as $key => $detail) {
            $duracion = (!empty($detail['op1_duracion'])) ? explode(' ', $detail['op1_duracion']) : 0;
            $resInsert2 = $this->pedeo->insertRow($sqlInsert2, array(
                ":op1_centerc"  => $detail['op1_centerc'],
                ":op1_comments" => $detail['op1_comments'],
                ":op1_contact_tel" => $detail['op1_contact_tel'],
                ":op1_content" => $detail['op1_content'],
                ":op1_date" => $detail['op1_date'],
                ":op1_dclass" => $detail['op1_dclass'],
                ":op1_duedate" => $detail['op1_duedate'],
                ":op1_duracion" => (!empty($detail['op1_duracion'])) ? $duracion[0] : 0,
                ":op1_issue" => $detail['op1_issue'],
                ":op1_num" => (!empty($detail['op1_num'])) ? $detail['op1_num'] : 0,
                ":op1_priority" => (!empty($detail['op1_priority'])) ? $detail['op1_priority'] : 1,
                ":op1_sn" => $detail['op1_sn'],
                ":op1_sn_name" => $detail['op1_sn_name'],
                ":op1_type" => $detail['op1_type'],
                ":op1_userfrom" => $detail['op1_userfrom'],
                ":op1_userto" => $detail['op1_userto'],
                ":op1_opportunity" => $Data['bop_id'],
                ":op1_stage" => $detail['op1_stage'],
                ":op1_dnumber" => $detail['op1_dnumber']
                
            ));

            if (is_numeric($resInsert2) && $resInsert2 > 0) {
            } else {
                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error' => true,
                    'data' => $resInsert2,
                    'mensaje' => 'No se puso realizar operacion'
                );

                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                return;
            }
        }

        // $this->pedeo->updateRow('UPDATE tbop set bop_stage = :bop_stage',array(":bop_stage" => $progress));

        $this->pedeo->trans_commit();

        $respuesta = array(
            'error' => false,
            'data' => $resUpdate,
            'mensaje' => 'Oportunidad registrada con exito'
        );

        }else{
            $this->pedeo->trans_rollback();
            $respuesta = array(
                'error' => true,
                'data' => $resUpdate,
                'mensaje' => 'No se puso realizar operacion'
            );
        }

        $this->response($respuesta);
    }
}
