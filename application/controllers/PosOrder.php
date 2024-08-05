<?php
// PARAMETRIZACION DE MODULARES CLASIFICACION DE PAGINA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PosOrder extends REST_Controller {

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


	// CREAR PEDIDO
	public function createPosOrder_post() {

		$Data = $this->post();

        $ContenidoDetalle = json_decode($Data['detail'], true);

        if (!is_array($ContenidoDetalle)) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro el detalle del documento'
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

        $sqlInsert = "INSERT INTO tppm (ppm_docnum, ppm_docdate, ppm_duedate, ppm_duedev, ppm_pricelist, ppm_cardcode, ppm_cardname, ppm_contacid, ppm_slpcode, ppm_empid, ppm_comment, ppm_doctotal, ppm_baseamnt,
        ppm_taxtotal, ppm_discprofit, ppm_discount, ppm_createat, ppm_baseentry, ppm_basetype, ppm_doctype, ppm_idadd, ppm_adress, ppm_paytype, ppm_attch, ppm_series, ppm_createby, ppm_currency,
        ppm_origen, ppm_canceled, business, branch, ppm_internal_comments, ppm_monto_dv, ppm_monto_v, ppm_monto_a, ppm_pasanaku, ppm_total_d, ppm_total_c, ppm_paymentmethod, ppm_cardcode2, ppm_cardname2, ppm_mesa)
        VALUES(:ppm_docnum, :ppm_docdate, :ppm_duedate, :ppm_duedev, :ppm_pricelist, :ppm_cardcode, :ppm_cardname, :ppm_contacid, :ppm_slpcode, :ppm_empid, :ppm_comment, :ppm_doctotal, :ppm_baseamnt,
        :ppm_taxtotal, :ppm_discprofit, :ppm_discount, :ppm_createat, :ppm_baseentry, :ppm_basetype, :ppm_doctype, :ppm_idadd, :ppm_adress, :ppm_paytype, :ppm_attch, :ppm_series, :ppm_createby, :ppm_currency,
        :ppm_origen, :ppm_canceled, :business, :branch, :ppm_internal_comments, :ppm_monto_dv, :ppm_monto_v, :ppm_monto_a, :ppm_pasanaku, :ppm_total_d, :ppm_total_c, :ppm_paymentmethod, :ppm_cardcode2, :ppm_cardname2,
        :ppm_mesa)";

        $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':ppm_docnum' => 0,
            ':ppm_docdate' => isset($Data['ppm_docdate']) ? $Data['ppm_docdate'] : null, 
            ':ppm_duedate' => isset($Data['ppm_duedate']) ? $Data['ppm_duedate'] : null, 
            ':ppm_duedev' => isset($Data['ppm_duedev']) ? $Data['ppm_duedev'] : null, 
            ':ppm_pricelist' => is_numeric($Data['ppm_pricelist']) ? $Data['ppm_pricelist'] : 0, 
            ':ppm_cardcode' => isset($Data['ppm_cardcode']) ? $Data['ppm_cardcode'] : null, 
            ':ppm_cardname' => isset($Data['ppm_cardname']) ? $Data['ppm_cardname'] : null, 
            ':ppm_contacid' => isset($Data['ppm_contacid']) ? $Data['ppm_contacid'] : null, 
            ':ppm_slpcode' => isset($Data['ppm_slpcode']) ? $Data['ppm_slpcode'] : null, 
            ':ppm_empid' => is_numeric($Data['ppm_empid']) ? $Data['ppm_empid'] : 0, 
            ':ppm_comment' => isset($Data['ppm_comment']) ? $Data['ppm_comment'] : null, 
            ':ppm_doctotal' => is_numeric($Data['ppm_doctotal']) ? $Data['ppm_doctotal'] : 0, 
            ':ppm_baseamnt' => is_numeric($Data['ppm_baseamnt']) ? $Data['ppm_baseamnt'] : 0, 
            ':ppm_taxtotal' => is_numeric($Data['ppm_taxtotal']) ? $Data['ppm_taxtotal'] : 0,  
            ':ppm_discprofit' => is_numeric($Data['ppm_discprofit']) ? $Data['ppm_discprofit'] : 0,  
            ':ppm_discount' => is_numeric($Data['ppm_discount']) ? $Data['ppm_discount'] : 0,  
            ':ppm_createat' => isset($Data['ppm_createat']) ? $Data['ppm_createat'] : null,  
            ':ppm_baseentry' => is_numeric($Data['ppm_baseentry']) ? $Data['ppm_baseentry'] : null,  
            ':ppm_basetype' => is_numeric($Data['ppm_basetype']) ? $Data['ppm_basetype'] : null,  
            ':ppm_doctype' => is_numeric($Data['ppm_doctype']) ? $Data['ppm_doctype'] : null,  
            ':ppm_idadd' => isset($Data['ppm_idadd']) ? $Data['ppm_idadd'] : null,  
            ':ppm_adress' => isset($Data['ppm_adress']) ? $Data['ppm_adress'] : null,  
            ':ppm_paytype' => isset($Data['ppm_paytype']) ? $Data['ppm_paytype'] : null,  
            ':ppm_attch' => isset($Data['ppm_attch']) ? $Data['ppm_attch'] : null,  
            ':ppm_series' => is_numeric($Data['ppm_series']) ? $Data['ppm_series'] : null,  
            ':ppm_createby' => isset($Data['ppm_createby']) ? $Data['ppm_createby'] : null,  
            ':ppm_currency' => isset($Data['ppm_currency']) ? $Data['ppm_currency'] : null, 
            ':ppm_origen' => is_numeric($Data['ppm_origen']) ? $Data['ppm_origen'] : null,  
            ':ppm_canceled' => 0,  
            ':business' => isset($Data['business']) ? $Data['business'] : null,  
            ':branch' =>  isset($Data['branch']) ? $Data['branch'] : null,  
            ':ppm_internal_comments' => isset($Data['ppm_internal_comments']) ? $Data['ppm_internal_comments'] : null,  
            ':ppm_monto_dv' => is_numeric($Data['ppm_monto_dv']) ? $Data['ppm_monto_dv'] : null,  
            ':ppm_monto_v' => is_numeric($Data['ppm_monto_v']) ? $Data['ppm_monto_v'] : null,  
            ':ppm_monto_a' => is_numeric($Data['ppm_monto_a']) ? $Data['ppm_monto_a'] : null,  
            ':ppm_pasanaku' => is_numeric($Data['ppm_pasanaku']) ? $Data['ppm_pasanaku'] : null,  
            ':ppm_total_d' => is_numeric($Data['ppm_total_d']) ? $Data['ppm_total_d'] : null,   
            ':ppm_total_c' => is_numeric($Data['ppm_total_c']) ? $Data['ppm_total_c'] : null,
            ':ppm_paymentmethod' => is_numeric($Data['ppm_paymentmethod']) ? $Data['ppm_paymentmethod'] : null,
            ':ppm_cardcode2' => isset($Data['ppm_cardcode2']) ? $Data['ppm_cardcode2'] : null,  
            ':ppm_cardname2' => isset($Data['ppm_cardname2']) ? $Data['ppm_cardname2'] : null,
            ':ppm_mesa' => isset($Data['ppm_mesa']) ? $Data['ppm_mesa'] : null,

            ':ppm_taxtotal_ad' => isset($Data['ppm_taxtotal_ad']) && is_numeric($Data['ppm_taxtotal_ad']) ? $Data['ppm_taxtotal_ad'] : 0

        ));


        if ( is_numeric($resInsert) && $resInsert > 0 ){
        }else{

            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $resInsert,
                'mensaje' => 'No se pudo registrar el pedido'
            );

            $this->response($respuesta);

            return;
        }

        foreach ($ContenidoDetalle as $key => $detail) {

            $sqlInsertDetail = "INSERT INTO ppm1(pm1_docentry, pm1_itemcode, pm1_itemname, pm1_quantity, pm1_uom, pm1_whscode, pm1_price, pm1_vat, pm1_vatsum, pm1_discount, pm1_linetotal, pm1_costcode, pm1_ubusiness,
            pm1_project, pm1_acctcode, pm1_basetype, pm1_doctype, pm1_avprice, pm1_inventory, pm1_linenum, pm1_acciva, pm1_codimp, business, branch, pm1_ubication, pm1_lote, pm1_baseline,
            ote_code, pm1_whscode_dest, pm1_ubication2, detalle_modular, pm1_baseentry, pm1_tax_base, pm1_itemdev, pm1_clean_quantity, pm1_status, pm1_accimp_ad,
            pm1_codimp_ad, pm1_vat_ad, pm1_vatsum_ad)
            VALUES(:pm1_docentry, :pm1_itemcode, :pm1_itemname, :pm1_quantity, :pm1_uom, :pm1_whscode, :pm1_price, :pm1_vat, :pm1_vatsum, :pm1_discount, :pm1_linetotal, :pm1_costcode, :pm1_ubusiness,
            :pm1_project, :pm1_acctcode, :pm1_basetype, :pm1_doctype, :pm1_avprice, :pm1_inventory, :pm1_linenum, :pm1_acciva, :pm1_codimp, :business, :branch, :pm1_ubication, :pm1_lote, :pm1_baseline,
            :ote_code, :pm1_whscode_dest, :pm1_ubication2, :detalle_modular, :pm1_baseentry, :pm1_tax_base, :pm1_itemdev,:pm1_clean_quantity, :pm1_status, :pm1_accimp_ad,
            :pm1_codimp_ad, :pm1_vat_ad, :pm1_vatsum_ad)";


            $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                ':pm1_docentry' => $resInsert,
                ':pm1_itemcode' => isset($detail['pm1_itemcode']) ? $detail['pm1_itemcode'] : null, 
                ':pm1_itemname' => isset($detail['pm1_itemname']) ? $detail['pm1_itemname'] : null, 
                ':pm1_quantity' => is_numeric($detail['pm1_quantity']) ? $detail['pm1_quantity'] : null, 
                ':pm1_uom' => isset($detail['pm1_uom']) ? $detail['pm1_uom'] : null, 
                ':pm1_whscode' => isset($detail['pm1_whscode']) ? $detail['pm1_whscode'] : null, 
                ':pm1_price' => is_numeric($detail['pm1_price']) ? $detail['pm1_price'] : null, 
                ':pm1_vat' => is_numeric($detail['pm1_vat']) ? $detail['pm1_vat'] : null, 
                ':pm1_vatsum' => is_numeric($detail['pm1_vatsum']) ? $detail['pm1_vatsum'] : null, 
                ':pm1_discount' => is_numeric($detail['pm1_discount']) ? $detail['pm1_discount'] : null, 
                ':pm1_linetotal' => is_numeric($detail['pm1_linetotal']) ? $detail['pm1_linetotal'] : null, 
                ':pm1_costcode' => isset($detail['pm1_costcode']) ? $detail['pm1_costcode'] : null, 
                ':pm1_ubusiness' => isset($detail['pm1_ubusiness']) ? $detail['pm1_ubusiness'] : null,
                ':pm1_project' => isset($detail['pm1_project']) ? $detail['pm1_project'] : null, 
                ':pm1_acctcode' => isset($detail['pm1_acctcode']) ? $detail['pm1_acctcode'] : null, 
                ':pm1_basetype' => is_numeric($detail['pm1_basetype']) ? $detail['pm1_basetype'] : null, 
                ':pm1_doctype' => is_numeric($detail['pm1_doctype']) ? $detail['pm1_doctype'] : null, 
                ':pm1_avprice' => is_numeric($detail['pm1_avprice']) ? $detail['pm1_avprice'] : null, 
                ':pm1_inventory' => is_numeric($detail['pm1_inventory']) ? $detail['pm1_inventory'] : null, 
                ':pm1_linenum' => is_numeric($detail['pm1_linenum']) ? $detail['pm1_linenum'] : null, 
                ':pm1_acciva' => is_numeric($detail['pm1_acciva']) ? $detail['pm1_acciva'] : null, 
                ':pm1_codimp' => isset($detail['pm1_codimp']) ? $detail['pm1_codimp'] : null, 
                ':business' => is_numeric($Data['business']) ? $Data['business'] : null, 
                ':branch' => is_numeric($Data['branch']) ? $Data['branch'] : null, 
                ':pm1_ubication' => isset($detail['pm1_ubication']) ? $detail['pm1_ubication'] : null, 
                ':pm1_lote' => isset($detail['pm1_lote']) ? $detail['pm1_lote'] : null, 
                ':pm1_baseline' => isset($detail['pm1_baseline']) ? $detail['pm1_baseline'] : null,
                ':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : null, 
                ':pm1_whscode_dest' => isset($detail['pm1_whscode_dest']) ? $detail['pm1_whscode_dest'] : null, 
                ':pm1_ubication2' => isset($detail['pm1_ubication2']) ? $detail['pm1_ubication2'] : null, 
                ':detalle_modular' => null,
                ':pm1_baseentry' => is_numeric($detail['pm1_baseentry']) ? $detail['pm1_baseentry'] : null, 
                ':pm1_tax_base' => is_numeric($detail['pm1_tax_base']) ? $detail['pm1_tax_base'] : null,
                ':pm1_itemdev' => is_numeric($detail['pm1_itemdev']) ? $detail['pm1_itemdev'] : 0,
                ':pm1_clean_quantity' => isset($detail['pm1_clean_quantity']) && is_numeric($detail['pm1_clean_quantity']) ? $detail['pm1_clean_quantity'] : null, 
                ':pm1_status' => is_numeric($detail['pm1_status']) ? $detail['pm1_status'] : null,


                'pm1_accimp_ad' => isset($detail['pm1_accimp_ad']) && is_numeric($detail['pm1_accimp_ad']) ? $detail['pm1_accimp_ad'] : 0,
                'pm1_codimp_ad' => isset($detail['pm1_codimp_ad']) ? $detail['pm1_codimp_ad'] : null,
                'pm1_vat_ad'    => isset($detail['pm1_vat_ad']) && is_numeric($detail['pm1_vat_ad']) ? $detail['pm1_vat_ad'] : 0,
                'pm1_vatsum_ad' => isset($detail['pm1_vatsum_ad']) && is_numeric($detail['pm1_vatsum_ad']) ? $detail['pm1_vatsum_ad'] : 0
            ));


            if ( is_numeric($resInsertDetail) && $resInsertDetail > 0 ) {
            // Se verifica que el detalle no de error insertando //
            } else {

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'    => $resInsertDetail,
                    'mensaje' => 'No se pudo registrar el pedido'
                );

                $this->response($respuesta);

                return;
            }
        }

        $this->pedeo->trans_commit();

        $respuesta = array(
            'error'   => false,
            'data'    => [],
            'mensaje' => 'Pedido creado con exito'
        );

		$this->response($respuesta);

	}


    // ACTUALIZAR PEDIDO
	public function updatePosOrder_post() {

		$Data = $this->post();

        $ContenidoDetalle = json_decode($Data['detail'], true);

        if (!is_array($ContenidoDetalle)) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro el detalle del documento'
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

        $sqlUpdate = "UPDATE tppm set ppm_docnum=:ppm_docnum,ppm_docdate=:ppm_docdate,ppm_duedate=:ppm_duedate,
        ppm_duedev=:ppm_duedev,ppm_pricelist=:ppm_pricelist,ppm_cardcode=:ppm_cardcode,ppm_cardname=:ppm_cardname,
        ppm_contacid=:ppm_contacid,ppm_slpcode=:ppm_slpcode,ppm_empid=:ppm_empid,ppm_comment=:ppm_comment,
        ppm_doctotal=:ppm_doctotal,ppm_baseamnt=:ppm_baseamnt,ppm_taxtotal=:ppm_taxtotal,ppm_discprofit=:ppm_discprofit,
        ppm_discount=:ppm_discount,ppm_createat=:ppm_createat,ppm_baseentry=:ppm_baseentry,ppm_basetype=:ppm_basetype,
        ppm_doctype=:ppm_doctype,ppm_idadd=:ppm_idadd,ppm_adress=:ppm_adress,ppm_paytype=:ppm_paytype,ppm_attch=:ppm_attch,
        ppm_series=:ppm_series,ppm_createby=:ppm_createby,ppm_currency=:ppm_currency,ppm_origen=:ppm_origen,
        ppm_canceled=:ppm_canceled,business=:business,branch=:branch,ppm_internal_comments=:ppm_internal_comments,
        ppm_monto_dv=:ppm_monto_dv,ppm_monto_v=:ppm_monto_v,ppm_monto_a=:ppm_monto_a,ppm_pasanaku=:ppm_pasanaku,
        ppm_total_d=:ppm_total_d,ppm_total_c=:ppm_total_c,ppm_paymentmethod=:ppm_paymentmethod,ppm_cardcode2=:ppm_cardcode2,
        ppm_cardname2=:ppm_cardname2,ppm_mesa=:ppm_mesa, ppm_taxtotal_ad = :ppm_taxtotal_ad WHERE ppm_docentry = :ppm_docentry";

        $this->pedeo->trans_begin();

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':ppm_docnum' => 0,
            ':ppm_docdate' => isset($Data['ppm_docdate']) ? $Data['ppm_docdate'] : null, 
            ':ppm_duedate' => isset($Data['ppm_duedate']) ? $Data['ppm_duedate'] : null, 
            ':ppm_duedev' => isset($Data['ppm_duedev']) ? $Data['ppm_duedev'] : null, 
            ':ppm_pricelist' => is_numeric($Data['ppm_pricelist']) ? $Data['ppm_pricelist'] : 0, 
            ':ppm_cardcode' => isset($Data['ppm_cardcode']) ? $Data['ppm_cardcode'] : null, 
            ':ppm_cardname' => isset($Data['ppm_cardname']) ? $Data['ppm_cardname'] : null, 
            ':ppm_contacid' => isset($Data['ppm_contacid']) ? $Data['ppm_contacid'] : null, 
            ':ppm_slpcode' => isset($Data['ppm_slpcode']) ? $Data['ppm_slpcode'] : null, 
            ':ppm_empid' => is_numeric($Data['ppm_empid']) ? $Data['ppm_empid'] : 0, 
            ':ppm_comment' => isset($Data['ppm_comment']) ? $Data['ppm_comment'] : null, 
            ':ppm_doctotal' => is_numeric($Data['ppm_doctotal']) ? $Data['ppm_doctotal'] : 0, 
            ':ppm_baseamnt' => is_numeric($Data['ppm_baseamnt']) ? $Data['ppm_baseamnt'] : 0, 
            ':ppm_taxtotal' => is_numeric($Data['ppm_taxtotal']) ? $Data['ppm_taxtotal'] : 0,  
            ':ppm_discprofit' => is_numeric($Data['ppm_discprofit']) ? $Data['ppm_discprofit'] : 0,  
            ':ppm_discount' => is_numeric($Data['ppm_discount']) ? $Data['ppm_discount'] : 0,  
            ':ppm_createat' => isset($Data['ppm_createat']) ? $Data['ppm_createat'] : null,  
            ':ppm_baseentry' => is_numeric($Data['ppm_baseentry']) ? $Data['ppm_baseentry'] : null,  
            ':ppm_basetype' => is_numeric($Data['ppm_basetype']) ? $Data['ppm_basetype'] : null,  
            ':ppm_doctype' => is_numeric($Data['ppm_doctype']) ? $Data['ppm_doctype'] : null,  
            ':ppm_idadd' => isset($Data['ppm_idadd']) ? $Data['ppm_idadd'] : null,  
            ':ppm_adress' => isset($Data['ppm_adress']) ? $Data['ppm_adress'] : null,  
            ':ppm_paytype' => isset($Data['ppm_paytype']) ? $Data['ppm_paytype'] : null,  
            ':ppm_attch' => isset($Data['ppm_attch']) ? $Data['ppm_attch'] : null,  
            ':ppm_series' => is_numeric($Data['ppm_series']) ? $Data['ppm_series'] : null,  
            ':ppm_createby' => isset($Data['ppm_createby']) ? $Data['ppm_createby'] : null,  
            ':ppm_currency' => isset($Data['ppm_currency']) ? $Data['ppm_currency'] : null, 
            ':ppm_origen' => is_numeric($Data['ppm_origen']) ? $Data['ppm_origen'] : null,  
            ':ppm_canceled' => isset($Data['ppm_canceled']) ? $Data['ppm_canceled'] : null,  
            ':business' => isset($Data['business']) ? $Data['business'] : null,  
            ':branch' =>  isset($Data['branch']) ? $Data['branch'] : null,  
            ':ppm_internal_comments' => isset($Data['ppm_internal_comments']) ? $Data['ppm_internal_comments'] : null,  
            ':ppm_monto_dv' => is_numeric($Data['ppm_monto_dv']) ? $Data['ppm_monto_dv'] : null,  
            ':ppm_monto_v' => is_numeric($Data['ppm_monto_v']) ? $Data['ppm_monto_v'] : null,  
            ':ppm_monto_a' => is_numeric($Data['ppm_monto_a']) ? $Data['ppm_monto_a'] : null,  
            ':ppm_pasanaku' => is_numeric($Data['ppm_pasanaku']) ? $Data['ppm_pasanaku'] : null,  
            ':ppm_total_d' => is_numeric($Data['ppm_total_d']) ? $Data['ppm_total_d'] : null,   
            ':ppm_total_c' => is_numeric($Data['ppm_total_c']) ? $Data['ppm_total_c'] : null,
            ':ppm_paymentmethod' => is_numeric($Data['ppm_paymentmethod']) ? $Data['ppm_paymentmethod'] : null,
            ':ppm_cardcode2' => isset($Data['ppm_cardcode2']) ? $Data['ppm_cardcode2'] : null,  
            ':ppm_cardname2' => isset($Data['ppm_cardname2']) ? $Data['ppm_cardname2'] : null,
            ':ppm_mesa' => isset($Data['ppm_mesa']) ? $Data['ppm_mesa'] : null,
            ':ppm_docentry' => $Data['ppm_docentry'],

            ':ppm_taxtotal_ad' => isset($Data['ppm_taxtotal_ad']) && is_numeric($Data['ppm_taxtotal_ad']) ? $Data['ppm_taxtotal_ad'] : 0

        ));


        if ( is_numeric($resUpdate) && $resUpdate == 1 ){
        }else{

            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $resUpdate,
                'mensaje' => 'No se pudo actualizar el pedido'
            );

            $this->response($respuesta);

            return;
        }

        $this->pedeo->deleteRow("DELETE FROM ppm1 WHERE pm1_docentry = :ppm_docentry", array(":ppm_docentry" => $Data['ppm_docentry']));

        foreach ($ContenidoDetalle as $key => $detail) {

            $sqlInsertDetail = "INSERT INTO ppm1(pm1_docentry, pm1_itemcode, pm1_itemname, pm1_quantity, pm1_uom, pm1_whscode, pm1_price, pm1_vat, pm1_vatsum, pm1_discount, pm1_linetotal, pm1_costcode, pm1_ubusiness,
            pm1_project, pm1_acctcode, pm1_basetype, pm1_doctype, pm1_avprice, pm1_inventory, pm1_linenum, pm1_acciva, pm1_codimp, business, branch, pm1_ubication, pm1_lote, pm1_baseline,
            ote_code, pm1_whscode_dest, pm1_ubication2, detalle_modular, pm1_baseentry, pm1_tax_base, pm1_itemdev, pm1_clean_quantity, pm1_status, pm1_accimp_ad,
            pm1_codimp_ad, pm1_vat_ad, pm1_vatsum_ad)
            VALUES(:pm1_docentry, :pm1_itemcode, :pm1_itemname, :pm1_quantity, :pm1_uom, :pm1_whscode, :pm1_price, :pm1_vat, :pm1_vatsum, :pm1_discount, :pm1_linetotal, :pm1_costcode, :pm1_ubusiness,
            :pm1_project, :pm1_acctcode, :pm1_basetype, :pm1_doctype, :pm1_avprice, :pm1_inventory, :pm1_linenum, :pm1_acciva, :pm1_codimp, :business, :branch, :pm1_ubication, :pm1_lote, :pm1_baseline,
            :ote_code, :pm1_whscode_dest, :pm1_ubication2, :detalle_modular, :pm1_baseentry, :pm1_tax_base, :pm1_itemdev,:pm1_clean_quantity, :pm1_status, :pm1_accimp_ad,
            :pm1_codimp_ad, :pm1_vat_ad, :pm1_vatsum_ad)";


            $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                ':pm1_docentry' => $Data['ppm_docentry'],
                ':pm1_itemcode' => isset($detail['pm1_itemcode']) ? $detail['pm1_itemcode'] : null, 
                ':pm1_itemname' => isset($detail['pm1_itemname']) ? $detail['pm1_itemname'] : null, 
                ':pm1_quantity' => is_numeric($detail['pm1_quantity']) ? $detail['pm1_quantity'] : null, 
                ':pm1_uom' => isset($detail['pm1_uom']) ? $detail['pm1_uom'] : null, 
                ':pm1_whscode' => isset($detail['pm1_whscode']) ? $detail['pm1_whscode'] : null, 
                ':pm1_price' => is_numeric($detail['pm1_price']) ? $detail['pm1_price'] : null, 
                ':pm1_vat' => is_numeric($detail['pm1_vat']) ? $detail['pm1_vat'] : null, 
                ':pm1_vatsum' => is_numeric($detail['pm1_vatsum']) ? $detail['pm1_vatsum'] : null, 
                ':pm1_discount' => is_numeric($detail['pm1_discount']) ? $detail['pm1_discount'] : null, 
                ':pm1_linetotal' => is_numeric($detail['pm1_linetotal']) ? $detail['pm1_linetotal'] : null, 
                ':pm1_costcode' => isset($detail['pm1_costcode']) ? $detail['pm1_costcode'] : null, 
                ':pm1_ubusiness' => isset($detail['pm1_ubusiness']) ? $detail['pm1_ubusiness'] : null,
                ':pm1_project' => isset($detail['pm1_project']) ? $detail['pm1_project'] : null, 
                ':pm1_acctcode' => isset($detail['pm1_acctcode']) ? $detail['pm1_acctcode'] : null, 
                ':pm1_basetype' => is_numeric($detail['pm1_basetype']) ? $detail['pm1_basetype'] : null, 
                ':pm1_doctype' => is_numeric($detail['pm1_doctype']) ? $detail['pm1_doctype'] : null, 
                ':pm1_avprice' => is_numeric($detail['pm1_avprice']) ? $detail['pm1_avprice'] : null, 
                ':pm1_inventory' => is_numeric($detail['pm1_inventory']) ? $detail['pm1_inventory'] : null, 
                ':pm1_linenum' => is_numeric($detail['pm1_linenum']) ? $detail['pm1_linenum'] : null, 
                ':pm1_acciva' => is_numeric($detail['pm1_acciva']) ? $detail['pm1_acciva'] : null, 
                ':pm1_codimp' => isset($detail['pm1_codimp']) ? $detail['pm1_codimp'] : null, 
                ':business' => is_numeric($Data['business']) ? $Data['business'] : null, 
                ':branch' => is_numeric($Data['branch']) ? $Data['branch'] : null, 
                ':pm1_ubication' => isset($detail['pm1_ubication']) ? $detail['pm1_ubication'] : null, 
                ':pm1_lote' => isset($detail['pm1_lote']) ? $detail['pm1_lote'] : null, 
                ':pm1_baseline' => isset($detail['pm1_baseline']) ? $detail['pm1_baseline'] : null,
                ':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : null, 
                ':pm1_whscode_dest' => isset($detail['pm1_whscode_dest']) ? $detail['pm1_whscode_dest'] : null, 
                ':pm1_ubication2' => isset($detail['pm1_ubication2']) ? $detail['pm1_ubication2'] : null, 
                ':detalle_modular' => null,
                ':pm1_baseentry' => is_numeric($detail['pm1_baseentry']) ? $detail['pm1_baseentry'] : null, 
                ':pm1_tax_base' => is_numeric($detail['pm1_tax_base']) ? $detail['pm1_tax_base'] : null,
                ':pm1_itemdev' => is_numeric($detail['pm1_itemdev']) ? $detail['pm1_itemdev'] : 0,
                ':pm1_clean_quantity' => isset($detail['pm1_clean_quantity']) && is_numeric($detail['pm1_clean_quantity']) ? $detail['pm1_clean_quantity'] : null, 
                ':pm1_status' => is_numeric($detail['pm1_status']) ? $detail['pm1_status'] : null,
                
                'pm1_accimp_ad' => isset($detail['pm1_accimp_ad']) && is_numeric($detail['pm1_accimp_ad']) ? $detail['pm1_accimp_ad'] : 0,
                'pm1_codimp_ad' => isset($detail['pm1_codimp_ad']) ? $detail['pm1_codimp_ad'] : null,
                'pm1_vat_ad'    => isset($detail['pm1_vat_ad']) && is_numeric($detail['pm1_vat_ad']) ? $detail['pm1_vat_ad'] : 0,
                'pm1_vatsum_ad' => isset($detail['pm1_vatsum_ad']) && is_numeric($detail['pm1_vatsum_ad']) ? $detail['pm1_vatsum_ad'] : 0
            ));


            if ( is_numeric($resInsertDetail) && $resInsertDetail > 0 ) {
            // Se verifica que el detalle no de error insertando //
            } else {

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'    => $resInsertDetail,
                    'mensaje' => 'No se pudo registrar el pedido'
                );

                $this->response($respuesta);

                return;
            }
        }

        $this->pedeo->trans_commit();

        $respuesta = array(
            'error'   => false,
            'data'    => [],
            'mensaje' => 'Pedido actualizado con exito'
        );

		$this->response($respuesta);

	}

    // OBTENER PEDIDO POR MESA
    public function getPosOrder_post() {

        $Data = $this->post();

        $sqlSelect = " SELECT * FROM tppm WHERE ppm_mesa = :ppm_mesa AND trim(ppm_canceled) = :ppm_canceled AND business = :business AND branch =:branch";
        
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":ppm_mesa" => $Data['mesa'], ":ppm_canceled" => '0', ":business" => $Data['business'], ":branch" => $Data['branch']));

        if ( isset($resSelect[0]) ){

            $respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);

        }else{

            $respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'Busqueda sin resultados'
			);
        }

        $this->response($respuesta);
    }

    // OBTENER EL DETALLE DEL PEDIDO

    public function getPosOrderById_post(){
        
        $Data = $this->post();

        $sqlSelect = " SELECT * FROM ppm1 WHERE pm1_docentry = :ppm_docentry";
        
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":ppm_docentry" => $Data['ppm_docentry']));

        if ( isset($resSelect[0]) ){

            $respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);

        }else{

            $respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'Busqueda sin resultados'
			);
        }

        $this->response($respuesta);
    }


}
