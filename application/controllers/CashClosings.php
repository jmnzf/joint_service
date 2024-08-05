<?php
// CIERRES DE CAJA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class CashClosings extends REST_Controller {

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
        $this->load->library('Inventory');
        $this->load->library('DocumentNumbering');
        $this->load->library('account');

	}

  	public function getCashClosings_get() {

        $Data = $this->get();

        $sqlSelect = "SELECT bco_docnum, bco_date, 
                        concat(bco_currency,to_char(round(get_dynamic_conversion(bco_currency,bco_currency,bco_date,bco_total,get_localcur()), get_decimals()), '999,999,999,999.00' )) bco_total,
                        concat(bco_currency,to_char(round(get_dynamic_conversion(bco_currency,bco_currency,bco_date,(bco_amount - bco_total),get_localcur()), get_decimals()), '999,999,999,999.00' )) base,
                        concat(bco_currency,to_char(round(get_dynamic_conversion(bco_currency,bco_currency,bco_date,bco_amount,get_localcur()), get_decimals()), '999,999,999,999.00' )) total,
                        bco_amount,
                        bco_total as bco_total1,
                        (bco_amount - bco_total) as base1,
                        bcc_description,
                        estado,
                        bco_id,
                        bco_doctype,
                        bco_bank,
                        bco_series,
                        bco_account,
                        bco_boxid,
                        bco_currency,
                        mdt_docname
                        FROM tbco 
                        INNER JOIN responsestatus  ON bco_id = responsestatus.id AND bco_doctype = responsestatus.tipo
                        INNER JOIN tbcc ON bco_boxid = bcc_id
                        INNER JOIN dmdt ON mdt_doctype = bco_doctype
                        where bco_status = :bco_status
                        and bco_boxid = :bco_boxid
                        and tbco.business = :business
                        and tbco.branch = :branch";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ':bco_status' => 0,
            ':bco_boxid' => $Data['bcc_id'],  
            ':business' => $Data['business'],  
            ':branch' => $Data['branch']
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

    public function getBoxByBusiness_post() {

        $Data = $this->post();

        $sqlSelect = "SELECT * FROM tbcc WHERE business = :business AND branch = :branch";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ':business' => $Data['business'],
            ':branch' => $Data['branch']
        ));

        if( isset($resSelect[0]) ) {

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

    public function cancelDocument_post(){

        $Data = $this->post();

        $DECI_MALES =  $this->generic->getDecimals();

        if (!isset($Data['bco_id']) OR !isset($Data['bco_doctype']) OR !isset($Data['business'])){

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'Faltan campos requeridos');

            return $this->response($respuesta);
        }


        // Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
        ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
        ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
        ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, business, branch)VALUES (:ac1_trans_id, :ac1_account,
        :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
        :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
        :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
        :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_line, :business, :branch)";
        //

        $AC1LINE = 0;

        $sqlDoc = "SELECT * FROM tbco WHERE  bco_id = :bco_id AND business = :business AND branch = :branch";
        $resDoc = $this->pedeo->queryTable($sqlDoc, array(
            ':bco_id' => $Data['bco_id'],
            ':business' => $Data['business'],
            ':branch' => $Data['branch']
        ));


        if ( isset($resDoc[0]) ){

            // BUSCANDO LA NUMERACION DEL DOCUMENTO
            $DocNumVerificado = $this->documentnumbering->NumberDoc($Data['bco_series'], $resDoc[0]['bco_date'], $resDoc[0]['bco_date']);
            
            if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

            }else if ($DocNumVerificado['error']){

                return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
            }
            //
            $this->pedeo->trans_begin();

            // SE INSERTA LA ANULACION
            $sqlInsert = "INSERT INTO tbco(bco_boxid, bco_date, bco_time, bco_status, bco_amount, business, branch, bco_total, bco_bank, bco_createdat, bco_doctype, bco_series, bco_currency, bco_account, bco_docnum)VALUES(:bco_boxid, :bco_date, :bco_time, :bco_status, :bco_amount, :business, :branch, :bco_total, :bco_bank, :bco_createdat, :bco_doctype, :bco_series, :bco_currency, :bco_account, :bco_docnum)";

            $resSqlInsert = $this->pedeo->insertRow($sqlInsert, array (
                
                ':bco_boxid' => $resDoc[0]['bco_boxid'], 
                ':bco_date' => $resDoc[0]['bco_date'], 
                ':bco_time' => $resDoc[0]['bco_time'],  
                ':bco_status' => 0, 
                ':bco_amount' => $resDoc[0]['bco_amount'], 
                ':business' => $resDoc[0]['business'], 
                ':branch' => $resDoc[0]['branch'], 
                ':bco_total' => $resDoc[0]['bco_total'],
                ':bco_bank' => $resDoc[0]['bco_bank'],
                ':bco_createdat' => date('Y-m-d'),
                ':bco_doctype' => 49, // TIPO DE DOCUMENTO ANULACION
                ':bco_series' => $Data['bco_series'],
                ':bco_currency' => $resDoc[0]['bco_currency'],
                ':bco_account' => $resDoc[0]['bco_account'],
                ':bco_docnum' => $DocNumVerificado,
            ));

            //

            if (is_numeric($resSqlInsert) && $resSqlInsert > 0) {

                //VALIDANDO PERIODO CONTABLE
                $periodo = $this->generic->ValidatePeriod($resDoc[0]['bco_date'], $resDoc[0]['bco_date'], $resDoc[0]['bco_date'], 1);

                if (isset($periodo['error']) && $periodo['error'] == false) {
                } else {
                    $respuesta = array(
                        'error'   => true,
                        'data'    => [],
                        'mensaje' => isset($periodo['mensaje']) ? $periodo['mensaje'] : 'no se pudo validar el periodo contable'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                    return;
                }
                //PERIODO CONTABLE

                //PROCESO DE TASA
                $dataTasa = $this->tasa->Tasa($Data['bco_currency'], $resDoc[0]['bco_date']);

                if(isset($dataTasa['tasaLocal'])){

                    $TasaDocLoc = $dataTasa['tasaLocal'];
                    $TasaLocSys = $dataTasa['tasaSys'];
                    $MONEDALOCAL = $dataTasa['curLocal'];
                    $MONEDASYS = $dataTasa['curSys'];
                    
                }else if($dataTasa['error'] == true){

                    $this->response($dataTasa, REST_Controller::HTTP_BAD_REQUEST);

                    return;
                }
                //FIN DE PROCESO DE TASA

                // Se actualiza la serie de la numeracion del documento
                $sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum WHERE pgs_id = :pgs_id";
                $resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
                        ':pgs_nextnum' => $DocNumVerificado,
                        ':pgs_id'      => $Data['bco_series']
                ));


                if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'    => $resActualizarNumeracion,
                        'mensaje'	=> 'No se pudo actualizar la numeración'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                    return;
                }
                // Fin de la actualizacion de la numeracion del documento

                // SE INSERTA EL ESTADO DEL DOCUMENTO
                // CIERRE DE CAJA  PARA QUE CAMBIE EL ESTADO
                // PARA ANULADO
                $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

                $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array (
                    ':bed_docentry' => $resDoc[0]['bco_id'],
                    ':bed_doctype' => $resDoc[0]['bco_doctype'],
                    ':bed_status' => 2, // ESTADO ANULADO
                    ':bed_createby' => $Data['bco_createby'],
                    ':bed_date' => date('Y-m-d'),
                    ':bed_baseentry' => $resSqlInsert,
                    ':bed_basetype' => 49
                ));


                if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
                } else {

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                    'error'   => true,
                    'data' => $resInsertEstado,
                    'mensaje'	=> 'No fue posible guardar el estado del documento'
                    );
                    $this->response($respuesta);

                    return;
                }
                //FIN PROCESO ESTADO DEL DOCUMENTO

                // SE INSERTA EL ESTADO DEL DOCUMENTO
                // ANULACION DE CIERRE DE CAJA  PARA QUE CAMBIE EL ESTADO
                // PARA CERRADO
                $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

                $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array (
                    ':bed_docentry' => $resSqlInsert,
                    ':bed_doctype' => 49,
                    ':bed_status' => 3, // ESTADO CERRADO
                    ':bed_createby' => $Data['bco_createby'],
                    ':bed_date' => date('Y-m-d'),
                    ':bed_baseentry' => NULL,
                    ':bed_basetype' => NULL
                ));


                if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
                } else {

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                    'error'   => true,
                    'data' => $resInsertEstado,
                    'mensaje'	=> 'No fue posible guardar el estado del documento'
                    );
                    $this->response($respuesta);

                    return;
                }
                //FIN PROCESO ESTADO DEL DOCUMENTO

                // SE INSERTA LA CABECERA DE LA CONTABILIDAD

                $sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, mac_accperiod, business, branch)
                VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :mac_accperiod, :business, :branch)";


                $resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

                    ':mac_doc_num' => 1,
                    ':mac_status' => 1,
                    ':mac_base_type' => 49,
                    ':mac_base_entry' => $resSqlInsert,
                    ':mac_doc_date' => $resDoc[0]['bco_date'],
                    ':mac_doc_duedate' => $resDoc[0]['bco_date'],
                    ':mac_legal_date' => $resDoc[0]['bco_date'],
                    ':mac_ref1' => 49,
                    ':mac_ref2' => "",
                    ':mac_ref3' => "",
                    ':mac_loc_total' => $resDoc[0]['bco_total'],
                    ':mac_fc_total' => $resDoc[0]['bco_total'],
                    ':mac_sys_total' => $resDoc[0]['bco_total'],
                    ':mac_trans_dode' => 1,
                    ':mac_beline_nume' => 1,
                    ':mac_vat_date' =>$resDoc[0]['bco_date'],
                    ':mac_serie' => 1,
                    ':mac_number' => 1,
                    ':mac_bammntsys' => 0,
                    ':mac_bammnt' =>  0,
                    ':mac_wtsum' => 1,
                    ':mac_vatsum' =>  0,
                    ':mac_comments' =>  NULL,
                    ':mac_create_date' => date("Y-m-d"),
                    ':mac_made_usuer' => isset($Data['bco_createby']) ? $Data['bco_createby'] : NULL,
                    ':mac_update_date' => date("Y-m-d"),
                    ':mac_update_user' => isset($Data['bco_createby']) ? $Data['bco_createby'] : NULL,
                    ':mac_accperiod' => $periodo['data'],
                    ':business'	  => $Data['business'],
                    ':branch' 	  => $Data['branch'],
                ));


                if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {

                } else {

                    
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                    'error'   => true,
                    'data'	  => $resInsertAsiento,
                    'mensaje'	=> 'No se pudo realizar el cierre, error al insertar la cabecera del asiento'
                    );

                    $this->response($respuesta);

                    return;
                }
                //

                // ASIENTO DE BANCO
                $montoTotal = $resDoc[0]['bco_total'];
                $montoSys = $montoTotal;
                $montoOrg = $montoTotal;

                if (trim($Data['bco_currency']) != $MONEDALOCAL) {
        
                    $montoTotal = ($montoTotal * $TasaLocSys);
                }
        
                if (trim($Data['bco_currency']) != $MONEDASYS) {
                    $montoSys = ($montoTotal / $TasaLocSys);
                } else {
                    $montoSys = $montoOrg;
                }

                // SE AGREGA AL BALANCE
               
                $BALANCE = $this->account->addBalance($periodo['data'], round($montoTotal, $DECI_MALES), $resDoc[0]['bco_bank'], 2, $resDoc[0]['bco_date'], $Data['business'], $Data['branch']);
                if (isset($BALANCE['error']) && $BALANCE['error'] == true){

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $BALANCE,
                        'mensaje' => $BALANCE['mensaje']
                    );

                    return $this->response($respuesta);
                }	

                //

                $AC1LINE = $AC1LINE + 1;
                $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(
        
                    ':ac1_trans_id' => $resInsertAsiento,
                    ':ac1_account' => $resDoc[0]['bco_bank'],
                    ':ac1_debit' => 0,
                    ':ac1_credit' => round($montoTotal, $DECI_MALES),
                    ':ac1_debit_sys' => 0,
                    ':ac1_credit_sys' => round($montoSys, $DECI_MALES),
                    ':ac1_currex' => 0,
                    ':ac1_doc_date' => $resDoc[0]['bco_date'],
                    ':ac1_doc_duedate' => $resDoc[0]['bco_date'],
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resSqlInsert,
                    ':ac1_font_line' => 1,
                    ':ac1_font_type' => 49,
                    ':ac1_accountvs' => 1,
                    ':ac1_doctype' => 18,
                    ':ac1_ref1' => "",
                    ':ac1_ref2' => "",
                    ':ac1_ref3' => "",
                    ':ac1_prc_code' => NULL,
                    ':ac1_uncode' => NULL,
                    ':ac1_prj_code' => NULL,
                    ':ac1_rescon_date' => NULL,
                    ':ac1_recon_total' => 0,
                    ':ac1_made_user' => isset($Data['bco_createby']) ? $Data['bco_createby'] : NULL,
                    ':ac1_accperiod' => $periodo['data'],
                    ':ac1_close' => 0,
                    ':ac1_cord' => 0,
                    ':ac1_ven_debit' => 0,
                    ':ac1_ven_credit' => 0,
                    ':ac1_fiscal_acct' => 0,
                    ':ac1_taxid' => 0,
                    ':ac1_isrti' => 0,
                    ':ac1_basert' => 0,
                    ':ac1_mmcode' => 0,
                    ':ac1_legal_num' => NULL,
                    ':ac1_codref' => 1,
                    ':ac1_line'   => $AC1LINE,
                    ':business'	  => $Data['business'],
                    ':branch' 	  => $Data['branch']
                ));
        
                if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
                } else {
                    $this->pedeo->trans_rollback();
        
                    $respuesta = array(
                        'error'   => true,
                        'data'	  => $resDetalleAsiento,
                        'mensaje'	=> 'No se pudo registrar el asiento para el banco'
                    );
        
                    $this->response($respuesta);
        
                    return;
                }

                // ASIENTO DE CAJA
        
                $montoTotal = $resDoc[0]['bco_total'];
                $montoSys = $montoTotal;
                $montoOrg = $montoTotal;

                if (trim($Data['bco_currency']) != $MONEDALOCAL) {
        
                    $montoTotal = ($montoTotal * $TasaLocSys);
                }
        
                if (trim($Data['bco_currency']) != $MONEDASYS) {
                    $montoSys = ($montoTotal / $TasaLocSys);
                } else {
                    $montoSys = $montoOrg;
                }
                // SE AGREGA AL BALANCE

                $BALANCE = $this->account->addBalance($periodo['data'], round($montoTotal, $DECI_MALES), $resDoc[0]['bco_account'], 1, $resDoc[0]['bco_date'], $Data['business'], $Data['branch']);
                if (isset($BALANCE['error']) && $BALANCE['error'] == true){

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $BALANCE,
                        'mensaje' => $BALANCE['mensaje']
                    );

                    return $this->response($respuesta);
                }	

                //
                $AC1LINE = $AC1LINE + 1;
                $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(
        
                    ':ac1_trans_id' => $resInsertAsiento,
                    ':ac1_account' => $resDoc[0]['bco_account'],
                    ':ac1_debit' => round($montoTotal, $DECI_MALES),
                    ':ac1_credit' => 0,
                    ':ac1_debit_sys' => round($montoSys, $DECI_MALES),
                    ':ac1_credit_sys' => 0,
                    ':ac1_currex' => 0,
                    ':ac1_doc_date' => $resDoc[0]['bco_date'],
                    ':ac1_doc_duedate' => $resDoc[0]['bco_date'],
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resSqlInsert,
                    ':ac1_font_line' => 1,
                    ':ac1_font_type' => 49,
                    ':ac1_accountvs' => 1,
                    ':ac1_doctype' => 18,
                    ':ac1_ref1' => "",
                    ':ac1_ref2' => "",
                    ':ac1_ref3' => "",
                    ':ac1_prc_code' => NULL,
                    ':ac1_uncode' => NULL,
                    ':ac1_prj_code' => NULL,
                    ':ac1_rescon_date' => NULL,
                    ':ac1_recon_total' => 0,
                    ':ac1_made_user' => isset($Data['bco_createby']) ? $Data['bco_createby'] : NULL,
                    ':ac1_accperiod' => $periodo['data'],
                    ':ac1_close' => 0,
                    ':ac1_cord' => 0,
                    ':ac1_ven_debit' => 0,
                    ':ac1_ven_credit' => 0,
                    ':ac1_fiscal_acct' => 0,
                    ':ac1_taxid' => 0,
                    ':ac1_isrti' => 0,
                    ':ac1_basert' => 0,
                    ':ac1_mmcode' => 0,
                    ':ac1_legal_num' => NULL,
                    ':ac1_codref' => 1,
                    ':ac1_line'   => $AC1LINE,
                    ':business'	  => $Data['business'],
                    ':branch' 	  => $Data['branch']
                ));
        
                if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
                } else {
                    $this->pedeo->trans_rollback();
        
                    $respuesta = array(
                        'error'   => true,
                        'data'	  => $resDetalleAsiento,
                        'mensaje'	=> 'No se pudo registrar el asiento para la caja'
                    );
        
                    $this->response($respuesta);
        
                    return;
                }


                $this->pedeo->trans_commit();

                $respuesta = array(
                    'error' => false,
                    'data'  => [],
                    'mensaje' => 'Cierre anulado con exito');


            }else{

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error' => true,
                    'data'  => $resSqlInsert,
                    'mensaje' => 'No se pudo insertar la anulación del cierre');
            }

        
        }else{

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'No se encontro la caja para anular');

            return $this->response($respuesta);
        }

       
        $this->response($respuesta);

    }


	
}