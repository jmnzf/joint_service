<?php
// DATOS MAESTROS ACIENTOS CONTABLES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class AccountingAccent extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);

	}

  //CREAR NUEVO ACIENTO CONTABLE
	public function createAccountingAccent_post(){
      $Data = $this->post();
      
      if(!isset($Data['mac_doc_num']) OR
         !isset($Data['mac_status']) OR
         !isset($Data['mac_base_type']) OR
         !isset($Data['mac_base_entry']) OR
         !isset($Data['mac_doc_date']) OR
         !isset($Data['mac_doc_duedate']) OR
         !isset($Data['mac_legal_date']) OR
         !isset($Data['mac_ref1']) OR
         !isset($Data['mac_ref2']) OR
         !isset($Data['mac_ref3']) OR
         !isset($Data['mac_loc_total']) OR
         !isset($Data['mac_fc_total']) OR
         !isset($Data['mac_sys_total']) OR
         !isset($Data['mac_trans_dode']) OR 
         !isset($Data['mac_beline_nume']) OR
         !isset($Data['mac_vat_date']) OR
         !isset($Data['mac_serie']) OR
         !isset($Data['mac_number']) OR
         !isset($Data['mac_bammntsys']) OR
         !isset($Data['mac_bammnt']) OR
         !isset($Data['mac_wtsum']) OR
         !isset($Data['mac_vatsum']) OR
         !isset($Data['mac_create_date']) OR
         !isset($Data['mac_made_usuer']) OR
         !isset($Data['mac_update_date']) OR 
         !isset($Data['mac_update_user']) OR 
         !isset($Data['detail'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $ContenidoDetalle = json_decode($Data['detail'], true);


        if(!is_array($ContenidoDetalle)){
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'No se encontro el detalle de la cuenta'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }



        $sqlInsert = "INSERT INTO tmac(mac_doc_num, mac_trans_id, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user)
                      VALUES (:mac_doc_num, :mac_trans_id, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':mac_doc_num' => $Data['mac_doc_num'],
            ':mac_trans_id' => $Data['mac_trans_id'],
            ':mac_status' => $Data['mac_status'],
            ':mac_base_type' => $Data['mac_base_type'],
            ':mac_base_entry' => $Data['mac_base_entry'],
            ':mac_doc_date' => $Data['mac_doc_date'],
            ':mac_doc_duedate' => $Data['mac_doc_duedate'],
            ':mac_legal_date' => $Data['mac_legal_date'],
            ':mac_ref1' => $Data['mac_ref1'],
            ':mac_ref2' => $Data['mac_ref2'],
            ':mac_ref3' => $Data['mac_ref3'],
            ':mac_loc_total' => $Data['mac_loc_total'],
            ':mac_fc_total' => $Data['mac_fc_total'],
            ':mac_sys_total' => $Data['mac_sys_total'],
            ':mac_trans_dode' => $Data['mac_trans_dode'],
            ':mac_beline_nume' => $Data['mac_beline_nume'],
            ':mac_vat_date' => $Data['mac_vat_date'],
            ':mac_serie' => $Data['mac_serie'],
            ':mac_number' => $Data['mac_number'],
            ':mac_bammntsys' => $Data['mac_bammntsys'],
            ':mac_bammnt' => $Data['mac_bammnt'],
            ':mac_wtsum' => $Data['mac_wtsum'],
            ':mac_vatsum' => $Data['mac_vatsum'],
            ':mac_comments' => $Data['mac_comments'],
            ':mac_create_date' => $Data['mac_create_date'],
            ':mac_made_usuer' => $Data['mac_made_usuer'],
            ':mac_update_date' => $Data['mac_update_date'],
            ':mac_update_user' => $Data['mac_update_user']     
        ));

        if($resInsert > 0 ){

            foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate, ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype, ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord, ac1_ven_debit, ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref)
                                    VALUES (:ac1_trans_id, :ac1_account, :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys, :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode, :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct, :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                   
                    ':ac1_trans_id' => $resInsert,
                    ':ac1_account' => $Data['ac1_account'],
                    ':ac1_debit' => $Data['ac1_debit'],
                    ':ac1_credit' => $Data['ac1_credit'],
                    ':ac1_debit_sys' => $Data['ac1_debit_sys'],
                    ':ac1_credit_sys' => $Data['ac1_credit_sys'],
                    ':ac1_currex' => $Data['ac1_currex'],
                    ':ac1_doc_date' => $Data['ac1_doc_date'],
                    ':ac1_doc_duedate' => $Data['ac1_doc_duedate'],
                    ':ac1_debit_import' => $Data['ac1_debit_import'],
                    ':ac1_credit_import' => $Data['ac1_credit_import'],
                    ':ac1_debit_importsys' => $Data['ac1_debit_importsys'],
                    ':ac1_credit_importsys' => $Data['ac1_credit_importsys'],
                    ':ac1_font_key' => $Data['ac1_font_key'],
                    ':ac1_font_line' => $Data['ac1_font_line'],
                    ':ac1_font_type' => $Data['ac1_font_type'],
                    ':ac1_accountvs' => $Data['ac1_accountvs'],
                    ':ac1_doctype' => $Data['ac1_doctype'],
                    ':ac1_ref1' => $Data['ac1_ref1'],
                    ':ac1_ref2' => $Data['ac1_ref2'],
                    ':ac1_ref3' => $Data['ac1_ref3'],
                    ':ac1_prc_code' => $Data['ac1_prc_code'],
                    ':ac1_uncode' => $Data['ac1_uncode'],
                    ':ac1_prj_code' => $Data['ac1_prj_code'],
                    ':ac1_rescon_date' => $Data['ac1_rescon_date'],
                    ':ac1_recon_total' => $Data['ac1_recon_total'],
                    ':ac1_made_user' => $Data['ac1_made_user'],
                    ':ac1_accperiod' => $Data['ac1_accperiod'],
                    ':ac1_close' => $Data['ac1_close'],
                    ':ac1_cord' => $Data['ac1_cord'],
                    ':ac1_ven_debit' => $Data['ac1_ven_debit'],
                    ':ac1_ven_credit' => $Data['ac1_ven_credit'],
                    ':ac1_fiscal_acct' => $Data['ac1_fiscal_acct'],
                    ':ac1_taxid' => $Data['ac1_taxid'],
                    ':ac1_isrti' => $Data['ac1_isrti'],
                    ':ac1_basert' => $Data['ac1_basert'],
                    ':ac1_mmcode' => $Data['ac1_mmcode'],
                    ':ac1_legal_num' => $Data['ac1_legal_num'],
                    ':ac1_codref' => $Data['ac1_codref']
              ));
            }

            $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Aciento contable registrado con exito'
            );

        }else{

              $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'No se pudo registrar el aciento contable'
              );

        }

         $this->response($respuesta);
	}

 



}
