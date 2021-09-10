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
      
      if(!isset($Data['detail'])){

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

            ':mac_doc_num' => isset($Data['mac_doc_num'])?$Data['mac_doc_num']:NULL,
            ':mac_trans_id' => isset($Data['mac_trans_id'])?$Data['mac_trans_id']:NULL,
            ':mac_status' => isset($Data['mac_status'])?$Data['mac_status']:NULL,
            ':mac_base_type' => isset($Data['mac_base_type'])?$Data['mac_base_type']:NULL,
            ':mac_base_entry' => isset($Data['mac_base_entry'])?$Data['mac_base_entry']:NULL,
            ':mac_doc_date' => isset($Data['mac_doc_date'])?$Data['mac_doc_date']:NULL,
            ':mac_doc_duedate' => isset($Data['mac_doc_duedate'])?$Data['mac_doc_duedate']:NULL,
            ':mac_legal_date' => isset($Data['mac_legal_date'])?$Data['mac_legal_date']:NULL,
            ':mac_ref1' => isset($Data['mac_ref1'])?$Data['mac_ref1']:NULL,
            ':mac_ref2' => isset($Data['mac_ref2'])?$Data['mac_ref2']:NULL,
            ':mac_ref3' => isset($Data['mac_ref3'])?$Data['mac_ref3']:NULL,
            ':mac_loc_total' => isset($Data['mac_loc_total'])?$Data['mac_loc_total']:NULL,
            ':mac_fc_total' => isset($Data['mac_fc_total'])?$Data['mac_fc_total']:NULL,
            ':mac_sys_total' => isset($Data['mac_sys_total'])?$Data['mac_sys_total']:NULL,
            ':mac_trans_dode' => isset($Data['mac_trans_dode'])?$Data['mac_trans_dode']:NULL,
            ':mac_beline_nume' => isset($Data['mac_beline_nume'])?$Data['mac_beline_nume']:NULL,
            ':mac_vat_date' => isset($Data['mac_vat_date'])?$Data['mac_vat_date']:NULL,
            ':mac_serie' => isset($Data['mac_serie'])?$Data['mac_serie']:NULL,
            ':mac_number' => isset($Data['mac_number'])?$Data['mac_number']:NULL,
            ':mac_bammntsys' => isset($Data['mac_bammntsys'])?$Data['mac_bammntsys']:NULL,
            ':mac_bammnt' => isset($Data['mac_bammnt'])?$Data['mac_bammnt']:NULL,
            ':mac_wtsum' => isset($Data['mac_wtsum'])?$Data['mac_wtsum']:NULL,
            ':mac_vatsum' => isset($Data['mac_vatsum'])?$Data['mac_vatsum']:NULL,
            ':mac_comments' => isset($Data['mac_comments'])?$Data['mac_comments']:NULL,
            ':mac_create_date' => isset($Data['mac_create_date'])?$Data['mac_create_date']:NULL,
            ':mac_made_usuer' => isset($Data['mac_made_usuer'])?$Data['mac_made_usuer']:NULL,
            ':mac_update_date' => isset($Data['mac_update_date'])?$Data['mac_update_date']:NULL,
            ':mac_update_user' => isset($Data['mac_update_user'])?$Data['mac_update_user']:NULL     
        ));

        if(is_numeric($resInsert) && $resInsert > 0 ){

            foreach ($ContenidoDetalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate, ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype, ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord, ac1_ven_debit, ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref)
                                    VALUES (:ac1_trans_id, :ac1_account, :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys, :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode, :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct, :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                   
                    ':ac1_trans_id' => $resInsert,
                    ':ac1_account' => isset($Data['ac1_account'])?$Data['ac1_account']:NULL,
                    ':ac1_debit' => isset($Data['ac1_debit'])?$Data['ac1_debit']:NULL,
                    ':ac1_credit' => isset($Data['ac1_credit'])?$Data['ac1_credit']:NULL,
                    ':ac1_debit_sys' => isset($Data['ac1_debit_sys'])?$Data['ac1_debit_sys']:NULL,
                    ':ac1_credit_sys' => isset($Data['ac1_credit_sys'])?$Data['ac1_credit_sys']:NULL,
                    ':ac1_currex' => isset($Data['ac1_currex'])?$Data['ac1_currex']:NULL,
                    ':ac1_doc_date' => isset($Data['ac1_doc_date'])?$Data['ac1_doc_date']:NULL,
                    ':ac1_doc_duedate' => isset($Data['ac1_doc_duedate'])?$Data['ac1_doc_duedate']:NULL,
                    ':ac1_debit_import' => isset($Data['ac1_debit_import'])?$Data['ac1_debit_import']:NULL,
                    ':ac1_credit_import' => isset($Data['ac1_credit_import'])?$Data['ac1_credit_import']:NULL,
                    ':ac1_debit_importsys' => isset($Data['ac1_debit_importsys'])?$Data['ac1_debit_importsys']:NULL,
                    ':ac1_credit_importsys' => isset($Data['ac1_credit_importsys'])?$Data['ac1_credit_importsys']:NULL,
                    ':ac1_font_key' => isset($Data['ac1_font_key'])?$Data['ac1_font_key']:NULL,
                    ':ac1_font_line' => isset($Data['ac1_font_line'])?$Data['ac1_font_line']:NULL,
                    ':ac1_font_type' => isset($Data['ac1_font_type'])?$Data['ac1_font_type']:NULL,
                    ':ac1_accountvs' => isset($Data['ac1_accountvs'])?$Data['ac1_accountvs']:NULL,
                    ':ac1_doctype' => isset($Data['ac1_doctype'])?$Data['ac1_doctype']:NULL,
                    ':ac1_ref1' => isset($Data['ac1_ref1'])?$Data['ac1_ref1']:NULL,
                    ':ac1_ref2' => isset($Data['ac1_ref2'])?$Data['ac1_ref2']:NULL,
                    ':ac1_ref3' => isset($Data['ac1_ref3'])?$Data['ac1_ref3']:NULL,
                    ':ac1_prc_code' => isset($Data['ac1_prc_code'])?$Data['ac1_prc_code']:NULL,
                    ':ac1_uncode' => isset($Data['ac1_uncode'])?$Data['ac1_uncode']:NULL,
                    ':ac1_prj_code' => isset($Data['ac1_prj_code'])?$Data['ac1_prj_code']:NULL,
                    ':ac1_rescon_date' => isset($Data['ac1_rescon_date'])?$Data['ac1_rescon_date']:NULL,
                    ':ac1_recon_total' => isset($Data['ac1_recon_total'])?$Data['ac1_recon_total']:NULL,
                    ':ac1_made_user' => isset($Data['ac1_made_user'])?$Data['ac1_made_user']:NULL,
                    ':ac1_accperiod' => isset($Data['ac1_accperiod'])?$Data['ac1_accperiod']:NULL,
                    ':ac1_close' => isset($Data['ac1_close'])?$Data['ac1_close']:NULL,
                    ':ac1_cord' => isset($Data['ac1_cord'])?$Data['ac1_cord']:NULL,
                    ':ac1_ven_debit' => isset($Data['ac1_ven_debit'])?$Data['ac1_ven_debit']:NULL,
                    ':ac1_ven_credit' => isset($Data['ac1_ven_credit'])?$Data['ac1_ven_credit']:NULL,
                    ':ac1_fiscal_acct' => isset($Data['ac1_fiscal_acct'])?$Data['ac1_fiscal_acct']:NULL,
                    ':ac1_taxid' => isset($Data['ac1_taxid'])?$Data['ac1_taxid']:NULL,
                    ':ac1_isrti' => isset($Data['ac1_isrti'])?$Data['ac1_isrti']:NULL,
                    ':ac1_basert' => isset($Data['ac1_basert'])?$Data['ac1_basert']:NULL,
                    ':ac1_mmcode' => isset($Data['ac1_mmcode'])?$Data['ac1_mmcode']:NULL,
                    ':ac1_legal_num' => isset($Data['ac1_legal_num'])?$Data['ac1_legal_num']:NULL,
                    ':ac1_codref' => isset($Data['ac1_codref'])?$Data['ac1_codref']:NULL
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
