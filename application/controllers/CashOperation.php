<?php
// PARAMETRIZACION DE MODULARES CLASIFICACION DE PAGINA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class CashOperation extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
        $this->load->library('generic');
        $this->load->library('Tasa');

	}

    // Lista las cajas creadas por usuario
    public function getPosBox_get(){

        $Data = $this->get();

        $sqlSelect = "SELECT * FROM tbcc WHERE business = :business AND branch = :branch AND bcc_user = :bcc_user AND bcc_status = :bcc_status";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ':business' => $Data['business'],
            ':branch' => $Data['branch'],
            ':bcc_user' => $Data['bcc_user'],
            ':bcc_status' => 1
        ));

        if(isset($resSelect[0])){

        $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => '');

        }else{

            $respuesta = array(
            'error'   => true,
            'data' => array(),
            'mensaje'	=> 'busqueda sin resultados'
            );

        }

        $this->response($respuesta);
    }

    // Lista la informacion de la operacion de la caja en el dia actual
    public function getInfoApertura_get(){

        $Data = $this->get();

        $sqlSelect = "SELECT bco_id, bco_boxid, bco_date, bco_time, bco_status, bco_amount, bco_total, current_date as factual
                        FROM tbco
                        WHERE business = :business 
                        AND branch = :branch 
                        AND bco_boxid = :bco_boxid
                        ORDER BY bco_id DESC LIMIT 1";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ':business'   => $Data['business'],
            ':branch'     => $Data['branch'],
            ':bco_boxid'  => $Data['bcc_id']
        ));

        if(isset($resSelect[0])){

            $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => '');

        }else{

            $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'busqueda sin resultados'
            );

        }

        $this->response($respuesta);
    }

    // Aperturar Caja
    public function openBox_post(){

        $Data = $this->post();

        if (!isset($Data['bco_boxid']) OR !isset($Data['bco_amount']) OR !isset($Data['bco_total'])){

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'Faltan campos requeridos');

            return $this->response($respuesta);
        }


        $sql = "SELECT * FROM tbco WHERE bco_boxid = :bco_boxid ORDER BY bco_id DESC LIMIT 1";
        $resSql = $this->pedeo->queryTable($sql, array(
            ':bco_boxid'  => $Data['bco_boxid'],
        ));

        if (isset($resSql[0])){

            if ( $resSql[0]['bco_status'] == 1 ){

                $respuesta = array(
                    'error' => true,
                    'data'  => [],
                    'mensaje' => 'La caja ya se encuentra en estado aperturado');
    
                return $this->response($respuesta);
            }

        }

        $sqlInsert = "INSERT INTO tbco(bco_boxid, bco_date, bco_time, bco_status, bco_amount, business, branch, bco_total)VALUES(:bco_boxid, :bco_date, :bco_time, :bco_status, :bco_amount, :business, :branch, :bco_total)";

        $resSqlInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':bco_boxid' => $Data['bco_boxid'], 
            ':bco_date' => date('Y-m-d'), 
            ':bco_time' => date('H:i:s'), 
            ':bco_status' => 1, 
            ':bco_amount' => $Data['bco_amount'], 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'], 
            ':bco_total' => $Data['bco_total']
        ));

        if (is_numeric($resSqlInsert) && $resSqlInsert > 0){

            $respuesta = array(
                'error' => false,
                'data'  => [],
                'mensaje' => 'Caja aperturada');

        }else {
            $respuesta = array(
                'error' => true,
                'data'  => $resSqlInsert,
                'mensaje' => 'No se pudo aperturar la caja');

        }

        $this->response($respuesta);

    }


    public function closeBox_post(){

        $Data = $this->post();

        if (!isset($Data['bco_boxid']) OR !isset($Data['bco_amount']) OR !isset($Data['bco_total'])){

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'Faltan campos requeridos');

            return $this->response($respuesta);
        }

        $sql = "SELECT * FROM tbco WHERE bco_boxid = :bco_boxid ORDER BY bco_id DESC LIMIT 1";
        $resSql = $this->pedeo->queryTable($sql, array(
            ':bco_boxid'  => $Data['bco_boxid'],
        ));

        if (isset($resSql[0])){

            if ( $resSql[0]['bco_status'] == 0 ){

                $respuesta = array(
                    'error' => true,
                    'data'  => [],
                    'mensaje' => 'La caja ya se encuentra cerrada');
    
                return $this->response($respuesta);
            }

        }

        $sqlInsert = "INSERT INTO tbco(bco_boxid, bco_date, bco_time, bco_status, bco_amount, business, branch, bco_total)VALUES(:bco_boxid, :bco_date, :bco_time, :bco_status, :bco_amount, :business, :branch, :bco_total)";

        $resSqlInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':bco_boxid' => $Data['bco_boxid'], 
            ':bco_date' => date('Y-m-d'), 
            ':bco_time' => date('H:i:s'), 
            ':bco_status' => 0, 
            ':bco_amount' => $Data['bco_amount'], 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'], 
            ':bco_total' => $Data['bco_total']
        ));

        if (is_numeric($resSqlInsert) && $resSqlInsert > 0){

            $respuesta = array(
                'error' => false,
                'data'  => [],
                'mensaje' => 'Caja cerrada con exito');

        }else {
            $respuesta = array(
                'error' => true,
                'data'  => $resSqlInsert,
                'mensaje' => 'No se pudo cerrar la caja');

        }

        $this->response($respuesta);


    }

    public function createRcd_post() {

        $Data = $this->post();

        if (!isset($Data['business']) OR
				!isset($Data['branch'])) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'La informacion enviada no es valida'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
		}

        $DocNumVerificado = 0;
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
		//

        	// BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['vrc_series'],$Data['vrc_docdate'],$Data['vrc_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}

        $sqlInsert = "INSERT INTO dvrc (vrc_docnum, vrc_docdate, vrc_duedate, vrc_duedev, vrc_pricelist, vrc_cardcode, vrc_cardname, vrc_contacid, vrc_slpcode, vrc_empid, vrc_comment, vrc_doctotal, vrc_baseamnt,
                     vrc_taxtotal, vrc_discprofit, vrc_discount, vrc_createat, vrc_baseentry, vrc_basetype, vrc_doctype, vrc_idadd, vrc_adress, vrc_paytype, vrc_attch, vrc_series, vrc_createby, vrc_currency,
                     vrc_origen, vrc_canceled, business, branch, vrc_internal_comments, vrc_monto_dv, vrc_monto_v, vrc_monto_a, vrc_pasanaku, vrc_total_d, vrc_total_c)
                    VALUES(:vrc_docnum, :vrc_docdate, :vrc_duedate, :vrc_duedev, :vrc_pricelist, :vrc_cardcode, :vrc_cardname, :vrc_contacid, :vrc_slpcode, :vrc_empid, :vrc_comment, :vrc_doctotal, :vrc_baseamnt,
                     :vrc_taxtotal, :vrc_discprofit, :vrc_discount, :vrc_createat, :vrc_baseentry, :vrc_basetype, :vrc_doctype, :vrc_idadd, :vrc_adress, :vrc_paytype, :vrc_attch, :vrc_series, :vrc_createby, :vrc_currency,
                     :vrc_origen, :vrc_canceled, :business, :branch, :vrc_internal_comments, :vrc_monto_dv, :vrc_monto_v, :vrc_monto_a, :vrc_pasanaku, :vrc_total_d, :vrc_total_c)";

        $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':vrc_docnum' => $DocNumVerificado,
            ':vrc_docdate' => isset($Data['vrc_docdate']) ? $Data['vrc_docdate'] : null, 
            ':vrc_duedate' => isset($Data['vrc_duedate']) ? $Data['vrc_duedate'] : null, 
            ':vrc_duedev' => isset($Data['vrc_duedev']) ? $Data['vrc_duedev'] : null, 
            ':vrc_pricelist' => is_numeric($Data['vrc_pricelist']) ? $Data['vrc_pricelist'] : 0, 
            ':vrc_cardcode' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : null, 
            ':vrc_cardname' => isset($Data['vrc_cardname']) ? $Data['vrc_cardname'] : null, 
            ':vrc_contacid' => isset($Data['vrc_contacid']) ? $Data['vrc_contacid'] : null, 
            ':vrc_slpcode' => isset($Data['vrc_slpcode']) ? $Data['vrc_slpcode'] : null, 
            ':vrc_empid' => is_numeric($Data['vrc_empid']) ? $Data['vrc_empid'] : 0, 
            ':vrc_comment' => isset($Data['vrc_comment']) ? $Data['vrc_comment'] : null, 
            ':vrc_doctotal' => is_numeric($Data['vrc_doctotal']) ? $Data['vrc_doctotal'] : 0, 
            ':vrc_baseamnt' => is_numeric($Data['vrc_baseamnt']) ? $Data['vrc_baseamnt'] : 0, 
            ':vrc_taxtotal' => is_numeric($Data['vrc_taxtotal']) ? $Data['vrc_taxtotal'] : 0,  
            ':vrc_discprofit' => is_numeric($Data['vrc_discprofit']) ? $Data['vrc_discprofit'] : 0,  
            ':vrc_discount' => is_numeric($Data['vrc_discount']) ? $Data['vrc_discount'] : 0,  
            ':vrc_createat' => isset($Data['vrc_createat']) ? $Data['vrc_createat'] : null,  
            ':vrc_baseentry' => is_numeric($Data['vrc_baseentry']) ? $Data['vrc_baseentry'] : null,  
            ':vrc_basetype' => is_numeric($Data['vrc_basetype']) ? $Data['vrc_basetype'] : null,  
            ':vrc_doctype' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : null,  
            ':vrc_idadd' => isset($Data['vrc_idadd']) ? $Data['vrc_idadd'] : null,  
            ':vrc_adress' => isset($Data['vrc_adress']) ? $Data['vrc_adress'] : null,  
            ':vrc_paytype' => isset($Data['vrc_paytype']) ? $Data['vrc_paytype'] : null,  
            ':vrc_attch' => isset($Data['vrc_attch']) ? $Data['vrc_attch'] : null,  
            ':vrc_series' => is_numeric($Data['vrc_series']) ? $Data['vrc_series'] : null,  
            ':vrc_createby' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : null,  
            ':vrc_currency' => isset($Data['vrc_currency']) ? $Data['vrc_currency'] : null, 
            ':vrc_origen' => is_numeric($Data['vrc_origen']) ? $Data['vrc_origen'] : null,  
            ':vrc_canceled' => isset($Data['vrc_canceled']) ? $Data['vrc_canceled'] : null,  
            ':business' => isset($Data['business']) ? $Data['business'] : null,  
            ':branch' =>  isset($Data['branch']) ? $Data['branch'] : null,  
            ':vrc_internal_comments' => isset($Data['vrc_internal_comments']) ? $Data['vrc_internal_comments'] : null,  
            ':vrc_monto_dv' => is_numeric($Data['vrc_monto_dv']) ? $Data['vrc_monto_dv'] : null,  
            ':vrc_monto_v' => is_numeric($Data['vrc_monto_v']) ? $Data['vrc_monto_v'] : null,  
            ':vrc_monto_a' => is_numeric($Data['vrc_monto_a']) ? $Data['vrc_monto_a'] : null,  
            ':vrc_pasanaku' => is_numeric($Data['vrc_pasanaku']) ? $Data['vrc_pasanaku'] : null,  
            ':vrc_total_d' => is_numeric($Data['vrc_total_d']) ? $Data['vrc_total_d'] : null,   
            ':vrc_total_c' => is_numeric($Data['vrc_total_c']) ? $Data['vrc_total_c'] : null
        ));


        if (is_numeric($resInsert) && $resInsert > 0) {

        }else {

            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $resInsert,
                'mensaje' => 'NO se pudo registrar el recibo'
            );


            $this->response($respuesta);

            return;
        }

        // Se actualiza la serie de la numeracion del documento
        $sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['vrc_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la cotización'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			// Fin de la actualizacion de la numeracion del documento


        foreach ($ContenidoDetalle as $key => $detail) {

            $sqlInsertDetail = "INSERT INTO vrc1(rc1_docentry, rc1_itemcode, rc1_itemname, rc1_quantity, rc1_uom, rc1_whscode, rc1_price, rc1_vat, rc1_vatsum, rc1_discount, rc1_linetotal, rc1_costcode, rc1_ubusiness,
                                rc1_project, rc1_acctcode, rc1_basetype, rc1_doctype, rc1_avprice, rc1_inventory, rc1_linenum, rc1_acciva, rc1_codimp, business, branch, rc1_ubication, rc1_lote, rc1_baseline,
                                ote_code, rc1_whscode_dest, rc1_ubication2, detalle_modular, rc1_baseentry, rc1_tax_base)
                                VALUES(:rc1_docentry, :rc1_itemcode, :rc1_itemname, :rc1_quantity, :rc1_uom, :rc1_whscode, :rc1_price, :rc1_vat, :rc1_vatsum, :rc1_discount, :rc1_linetotal, :rc1_costcode, :rc1_ubusiness,
                                :rc1_project, :rc1_acctcode, :rc1_basetype, :rc1_doctype, :rc1_avprice, :rc1_inventory, :rc1_linenum, :rc1_acciva, :rc1_codimp, :business, :branch, :rc1_ubication, :rc1_lote, :rc1_baseline,
                                :ote_code, :rc1_whscode_dest, :rc1_ubication2, :detalle_modular, :rc1_baseentry, :rc1_tax_base)";


            $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                
                ':rc1_docentry' => $resInsert,
                ':rc1_itemcode' => isset($detail['rc1_itemcode']) ? $detail['rc1_itemcode'] : null, 
                ':rc1_itemname' => isset($detail['rc1_itemname']) ? $detail['rc1_itemname'] : null, 
                ':rc1_quantity' => is_numeric($detail['rc1_quantity']) ? $detail['rc1_quantity'] : null, 
                ':rc1_uom' => isset($detail['rc1_uom']) ? $detail['rc1_uom'] : null, 
                ':rc1_whscode' => isset($detail['rc1_whscode']) ? $detail['rc1_whscode'] : null, 
                ':rc1_price' => is_numeric($detail['rc1_price']) ? $detail['rc1_price'] : null, 
                ':rc1_vat' => is_numeric($detail['rc1_vat']) ? $detail['rc1_vat'] : null, 
                ':rc1_vatsum' => is_numeric($detail['rc1_vatsum']) ? $detail['rc1_vatsum'] : null, 
                ':rc1_discount' => is_numeric($detail['rc1_discount']) ? $detail['rc1_discount'] : null, 
                ':rc1_linetotal' => is_numeric($detail['rc1_linetotal']) ? $detail['rc1_linetotal'] : null, 
                ':rc1_costcode' => isset($detail['rc1_costcode']) ? $detail['rc1_costcode'] : null, 
                ':rc1_ubusiness' => isset($detail['rc1_ubusiness']) ? $detail['rc1_ubusiness'] : null,
                ':rc1_project' => isset($detail['rc1_project']) ? $detail['rc1_project'] : null, 
                ':rc1_acctcode' => isset($detail['rc1_acctcode']) ? $detail['rc1_acctcode'] : null, 
                ':rc1_basetype' => is_numeric($detail['rc1_basetype']) ? $detail['rc1_basetype'] : null, 
                ':rc1_doctype' => is_numeric($detail['rc1_doctype']) ? $detail['rc1_doctype'] : null, 
                ':rc1_avprice' => is_numeric($detail['rc1_avprice']) ? $detail['rc1_avprice'] : null, 
                ':rc1_inventory' => is_numeric($detail['rc1_inventory']) ? $detail['rc1_inventory'] : null, 
                ':rc1_linenum' => is_numeric($detail['rc1_linenum']) ? $detail['rc1_linenum'] : null, 
                ':rc1_acciva' => is_numeric($detail['rc1_acciva']) ? $detail['rc1_acciva'] : null, 
                ':rc1_codimp' => isset($detail['rc1_codimp']) ? $detail['rc1_codimp'] : null, 
                ':business' => is_numeric($Data['business']) ? $Data['business'] : null, 
                ':branch' => is_numeric($Data['branch']) ? $Data['branch'] : null, 
                ':rc1_ubication' => isset($detail['rc1_ubication']) ? $detail['rc1_ubication'] : null, 
                ':rc1_lote' => isset($detail['rc1_lote']) ? $detail['rc1_lote'] : null, 
                ':rc1_baseline' => isset($detail['rc1_baseline']) ? $detail['rc1_baseline'] : null,
                ':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : null, 
                ':rc1_whscode_dest' => isset($detail['rc1_whscode_dest']) ? $detail['rc1_whscode_dest'] : null, 
                ':rc1_ubication2' => isset($detail['rc1_ubication2']) ? $detail['rc1_ubication2'] : null, 
                ':detalle_modular' => null,
                ':rc1_baseentry' => is_numeric($detail['rc1_baseentry']) ? $detail['rc1_baseentry'] : null, 
                ':rc1_tax_base' => is_numeric($detail['rc1_tax_base']) ? $detail['rc1_tax_base'] : null
            ));


            if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
                // Se verifica que el detalle no de error insertando //
            } else {

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data' => $resInsertDetail,
                    'mensaje'	=> 'No se pudo registrar el recibo'
                );

                $this->response($respuesta);

                return;
            }
        }


        $this->pedeo->trans_commit();

        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Recibo registrado con exito #'.$DocNumVerificado
        );

        $this->response($respuesta);
    }

    public function getcashRegister_get(){
        $sqlSelect = " SELECT  
                        vrc_docdate,
                        vrc_docnum,
                        vrc_cardname,
                        vrc_cardcode,
                        vrc_docentry,
                        vrc_currency,
                        vrc_monto_dv,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_total_c,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_total_c,
                        vrc_createby,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_monto_dv,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_monto_dv,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_monto_v,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_monto_v,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_monto_a,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_monto_a,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_pasanaku,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_pasanaku,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_total_d,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_total_d
                        FROM dvrc";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if(isset($resSelect[0])){

            $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => '');

        }else{

            $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'busqueda sin resultados'
            );

        }

        $this->response($respuesta);
    }

    public function getcashRegisterDetail_post(){

        $Data = $this->post();

        $sqlSelect = " SELECT  rc1_itemcode, 
                        rc1_itemname, 
                        rc1_quantity, 
                        rc1_uom, 
                        rc1_whscode, 
                        concat(:vrc_currency,to_char(round(get_dynamic_conversion(:vrc_currency,:vrc_currency,:vrc_docdate,rc1_price::numeric,get_localcur()), get_decimals()), '999,999,999,999.00' )) rc1_price,
                        concat(:vrc_currency,to_char(round(get_dynamic_conversion(:vrc_currency,:vrc_currency,:vrc_docdate,rc1_linetotal::numeric,get_localcur()), get_decimals()), '999,999,999,999.00' )) rc1_linetotal
                        FROM vrc1
                        where rc1_docentry = :rc1_docentry";

        $sqlSelect = str_replace(':vrc_currency', "'".$Data['currency']."'", $sqlSelect);
        $sqlSelect = str_replace(':vrc_docdate', "'".$Data['date']."'", $sqlSelect);


        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ':rc1_docentry' => $Data['docentry']
        ));

        if(isset($resSelect[0])){

            $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => '');

        }else{

            $respuesta = array(
                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'busqueda sin resultados'
            );

        }

        $this->response($respuesta);
    }
}
