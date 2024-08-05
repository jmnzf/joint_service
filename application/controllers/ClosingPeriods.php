<?php
// CIERRES DE PERIODOS
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;



class ClosingPeriods extends REST_Controller
{

    private $pdo;
    public function __construct()
    {
        header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding');
        header('Access-Control-Allow-Origin: *');
        parent::__construct();
        $this->load->database();
        $this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
        $this->load->library('generic');
        $this->load->library('account');
        $this->load->library('Tasa');
        $this->load->library('DocumentNumbering');
    }

    public function getClosingPeriods_get() {

        $sql = "SELECT tmcp.*, estado
                FROM tmcp
                inner join responsestatus
                on mcp_docentry = id and mcp_doctype = tipo";
        
        $resSelect = $this->pedeo->queryTable($sql, array());


        if ( isset($resSelect[0]) ){
            $respuesta = array(
                'error' => false, 'data' => $resSelect, 'mensaje' => ''
            );
        }else{
            $respuesta = array(
                'error' => true, 'data' => $resSelect, 'mensaje' => 'Busqueda sin resultados'
            );
        }
        $this->response($respuesta);
    }


    public function getAcctFamily_get() {

        $sqlSelect = "SELECT * 
                        FROM tbfc 
                        WHERE bfc_code > :bfc_code AND bfc_enabled = :bfc_enabled";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ':bfc_code' => 3,
            ':bfc_enabled' => 1
        ));

        if ( isset($resSelect[0]) ) {

            $respuesta = array(
                'error' => false, 'data' => $resSelect, 'mensaje' => ''
            );

            return $this->response($respuesta);
        } else {

            $respuesta = array(
                'error' => true, 'data' => $resSelect, 'mensaje' => 'Busqueda sin resultados'
            );

            return $this->response($respuesta);
        }
    }

    public function getPeriod_get() {
        
        $sqlSelect = "SELECT bpc_id, bpc_name, bpc_period FROM tbpc";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if ( isset($resSelect[0]) ) {

            $respuesta = array(
                'error' => false, 'data' => $resSelect, 'mensaje' => ''
            );

            return $this->response($respuesta);

        } else {

            $respuesta = array(
                'error' => true, 'data' => $resSelect, 'mensaje' => 'Busqueda sin resultados'
            );

            return $this->response($respuesta);
        }
    }

    public function getSubPeriod_get() {

        $Data = $this->get();

        if ( !isset($Data['periodo']) ){

            $respuesta = array(
                'error' => true, 'data' => [], 'mensaje' => 'Falta información requerida'
            );

            return $this->response($respuesta);
        }
        
        $sqlSelect = "SELECT * FROM bpc1 WHERE pc1_period_id = :pc1_period_id order by pc1_fic, pc1_ffc asc";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ':pc1_period_id' => $Data['periodo'],
        ));

        if ( isset($resSelect[0]) ) {

            $respuesta = array(
                'error' => false, 'data' => $resSelect, 'mensaje' => ''
            );

            return $this->response($respuesta);

        } else {

            $respuesta = array(
                'error' => true, 'data' => $resSelect, 'mensaje' => 'Busqueda sin resultados'
            );

            return $this->response($respuesta);
        }
    }

    public function createClosingPeriod_post() {

        $Data = $this->post();

     
        if ( !isset($Data['detail']) OR !isset($Data['doc_datedrag']) OR !isset($Data['doc_dateclose']) ) {

            $respuesta = array(
                'error' => true, 'data' => [], 'mensaje' => 'Falta información requerida'
            );

            return $this->response($respuesta);
        }

        $DECI_MALES =  $this->generic->getDecimals();

        // Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
        ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
        ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
        ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, business, branch, ac1_closed_period)VALUES (:ac1_trans_id, :ac1_account,
        :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
        :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
        :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
        :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_line, :business, :branch, :ac1_closed_period)";


        $DocNumVerificado = 0;

        $AC1LINE = 0;

        $ContenidoDetalle = json_decode($Data['detail'], true);

        if (!is_array($ContenidoDetalle)) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro el detalle de las cuentas'
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

        //VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['mcp_docdate'], $Data['mcp_docdate'], $Data['mcp_docdate'], 1);

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

        //BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['mcp_series'],$Data['mcp_docdate'],$Data['mcp_docdate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}
        //

        //PROCESO DE TASA
		$dataTasa = $this->tasa->Tasa($Data['mcp_currency'],$Data['mcp_docdate']);

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

        $this->pedeo->trans_begin();
        
        $sqlInsert = "INSERT INTO tmcp(mcp_docnum, mcp_docdate, mcp_comment, mcp_createat, mcp_baseentry, mcp_basetype, mcp_doctype, mcp_series, mcp_createby, mcp_currency, business, branch, mcp_period, mcp_subperiodini, mcp_subperiodfin)
                      VALUES(:mcp_docnum, :mcp_docdate, :mcp_comment, :mcp_createat, :mcp_baseentry, :mcp_basetype, :mcp_doctype, :mcp_series, :mcp_createby, :mcp_currency, :business, :branch, :mcp_period, :mcp_subperiodini, :mcp_subperiodfin)";



        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':mcp_docnum' => $DocNumVerificado, 
            ':mcp_docdate' => isset($Data['mcp_docdate']) ? $Data['mcp_docdate'] :NULL, 
            ':mcp_comment' => isset($Data['mcp_comment']) ? $Data['mcp_comment'] :NULL, 
            ':mcp_createat' => isset($Data['mcp_createat']) ? $Data['mcp_createat'] :NULL, 
            ':mcp_baseentry' => is_numeric($Data['mcp_baseentry']) ? $Data['mcp_baseentry'] :NULL, 
            ':mcp_basetype' => is_numeric($Data['mcp_basetype']) ? $Data['mcp_basetype'] :NULL, 
            ':mcp_doctype' => is_numeric($Data['mcp_doctype']) ? $Data['mcp_doctype'] :NULL, 
            ':mcp_series' => is_numeric($Data['mcp_series']) ? $Data['mcp_series'] :NULL, 
            ':mcp_createby' => isset($Data['mcp_createby']) ? $Data['mcp_createby'] :NULL, 
            ':mcp_currency' => isset($Data['mcp_currency']) ? $Data['mcp_currency'] :NULL, 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'],
            ':mcp_period' => $Data['periodo'], 
            ':mcp_subperiodini' => $Data['fi'], 
            ':mcp_subperiodfin' => $Data['ff']
        ));


        if ( is_numeric($resInsert) && $resInsert > 0 ) {

            
            // CABECERA DE ASIENTO CONTABLE
            $sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch, mac_accperiod)
								VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch, :mac_accperiod)";


            $resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(
                ':mac_doc_num' => 1,
                ':mac_status' => 1,
                ':mac_base_type' => is_numeric($Data['mcp_doctype']) ? $Data['mcp_doctype'] : 0,
                ':mac_base_entry' => $resInsert,
                ':mac_doc_date' => $Data['doc_datedrag'],
                ':mac_doc_duedate' => $Data['doc_datedrag'],
                ':mac_legal_date' => $Data['doc_datedrag'],
                ':mac_ref1' => is_numeric($Data['mcp_doctype']) ? $Data['mcp_doctype'] : 0,
                ':mac_ref2' => "",
                ':mac_ref3' => "",
                ':mac_loc_total' => 0,
                ':mac_fc_total' => 0,
                ':mac_sys_total' => 0,
                ':mac_trans_dode' => 1,
                ':mac_beline_nume' => 1,
                ':mac_vat_date' => $Data['doc_datedrag'],
                ':mac_serie' => 1,
                ':mac_number' => 1,
                ':mac_bammntsys' =>  0,
                ':mac_bammnt' => 0,
                ':mac_wtsum' => 1,
                ':mac_vatsum' => 0,
                ':mac_comments' => isset($Data['mcp_comment']) ? $Data['mcp_comment'] : NULL,
                ':mac_create_date' => $this->validateDate($Data['mcp_createat']) ? $Data['mcp_createat'] : NULL,
                ':mac_made_usuer' => isset($Data['mcp_createby']) ? $Data['mcp_createby'] : NULL,
                ':mac_update_date' => date("Y-m-d"),
                ':mac_update_user' => isset($Data['mcp_createby']) ? $Data['mcp_createby'] : NULL,
                ':business' => $Data['business'],
                ':branch' 	=> $Data['branch'],
                ':mac_accperiod' => $periodo['data']
            ));

            if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
                $AgregarAsiento = false;
            } else {

               
                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'	  => $resInsertAsiento,
                    'mensaje'	=> 'No se pudo registrar el cierre'
                );

                $this->response($respuesta);

                return;
            }
            //

            // Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
                                    WHERE pgs_id = :pgs_id";
            $resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
                ':pgs_nextnum' => $DocNumVerificado,
                ':pgs_id'      => $Data['mcp_series']
            ));


            if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
            } else {
                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'    => $resActualizarNumeracion,
                    'mensaje'	=> 'No se pudo actualizar la numeración de cierre'
                );

                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                return;
            }
            // Fin de la actualizacion de la numeracion del documento

            $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
				                VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

			$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


					':bed_docentry' => $resInsert,
					':bed_doctype' => $Data['mcp_doctype'],
					':bed_status' => 3, //ESTADO CERRADO
					':bed_createby' => $Data['mcp_createby'],
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
                    'mensaje'	=> 'No se pudo realizar el cierre',
                    'proceso' => 'Insertar estado documento'
                );


                $this->response($respuesta);

                return;
			}

            // BUSCANDO LAS CUENTAS DE ARRASTRE
            $FAMILIAS = implode(',', $ContenidoDetalle);
            $SUBPERIODOS = implode(',', explode(",", $Data['subperiodos']));
            $SUBPERIODOS = str_replace('"','', $SUBPERIODOS);

            $sqlCuentaArrastre = "SELECT bap_id, bap_acount, sum(bap_debit - bap_credit) as saldo 
                                FROM abap a
                                inner join dacc on bap_acount = acc_code
                                where dacc.acc_type in(".$FAMILIAS.")
                                and bap_period  = ".$Data['periodo']."
                                and bap_subperiod in(".$SUBPERIODOS.")
                                and bap_closed = 0
                                and bap_closureid = 0
                                group by bap_acount, bap_id 
                                order by bap_acount asc";

            $resCuentaArrastre = $this->pedeo->queryTable($sqlCuentaArrastre, array());

            if (isset($resCuentaArrastre[0])){

            }else{

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data' => $resCuentaArrastre,
                    'mensaje'	=> 'No se encontraron cuentas de arrastre disponibles'
                );


                return $this->response($respuesta);

            }
            //
            $SumaDebito = 0;
            $SumaCredito = 0;
            // SE VOLTEAN LOS SALDOS EN EL NUEVO ASIENTO
            foreach ($resCuentaArrastre as $key => $cuenta) {

                $cuentaAr = $cuenta['bap_acount'];
                $saldo = $cuenta['saldo'];

                $debito = 0;
                $credito = 0;
                $MontoSysCR = 0;
                $MontoSysDB = 0;

                if ( $saldo > 0 ){

                    $credito = ( $saldo * 1 );
                    $MontoSysCR =  round(($credito / $TasaLocSys), $DECI_MALES);

                }else{

                    $debito = ( $saldo * -1 );
                    $MontoSysDB =  round(($debito / $TasaLocSys), $DECI_MALES);
                }

                $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

                    ':ac1_trans_id' => $resInsertAsiento, 
                    ':ac1_account' => $cuentaAr,
                    ':ac1_debit' => $debito, 
                    ':ac1_credit' => $credito, 
                    ':ac1_debit_sys' => $MontoSysDB, 
                    ':ac1_credit_sys' => $MontoSysCR, 
                    ':ac1_currex' => 0, 
                    ':ac1_doc_date' => $Data['doc_datedrag'], 
                    ':ac1_doc_duedate' => $Data['doc_datedrag'], 
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resInsert, 
                    ':ac1_font_line' => 1, 
                    ':ac1_font_type' => $Data['mcp_doctype'], 
                    ':ac1_accountvs' => 1, 
                    ':ac1_doctype' => 18, 
                    ':ac1_ref1' => '', 
                    ':ac1_ref2' => '', 
                    ':ac1_ref3' => '', 
                    ':ac1_prc_code' => NULL, 
                    ':ac1_uncode' => NULL,
                    ':ac1_prj_code' => NULL, 
                    ':ac1_rescon_date' => NULL, 
                    ':ac1_recon_total' => 0, 
                    ':ac1_made_user' =>  $Data['mcp_createby'], 
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
                    ':ac1_line' => 0, 
                    ':business' => $Data['business'], 
                    ':branch' => $Data['branch'], 
                    ':ac1_closed_period' => 0
                ));

                if ( is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0 ){

                }else{
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'    => $resDetalleAsiento,
                        'mensaje' => 'No se pudo registrar el asiento de la cuenta'.$cuentaAr
                    );


                    return $this->response($respuesta);
                }

                
                
                if ( $debito > 0 ){

                    $credito = $debito;
                    $debito = 0;
                    $MontoSysCR = $MontoSysDB;
                    $MontoSysDB = 0;
                    $SumaCredito = $SumaCredito + $credito; 
                }else{
                    $debito = $credito;
                    $credito = 0;
                    $MontoSysDB = $MontoSysCR;
                    $MontoSysCR = 0;
                    $SumaDebito = $SumaDebito + $debito;
                }

                // CONTRA PARTIDA DE ARRASTRE
                $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

                    ':ac1_trans_id' => $resInsertAsiento, 
                    ':ac1_account' => $Data['acc_balance'],
                    ':ac1_debit' => $debito, 
                    ':ac1_credit' => $credito, 
                    ':ac1_debit_sys' => $MontoSysDB, 
                    ':ac1_credit_sys' => $MontoSysCR, 
                    ':ac1_currex' => 0, 
                    ':ac1_doc_date' => $Data['doc_datedrag'], 
                    ':ac1_doc_duedate' => $Data['doc_datedrag'], 
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resInsert, 
                    ':ac1_font_line' => 1, 
                    ':ac1_font_type' => $Data['mcp_doctype'], 
                    ':ac1_accountvs' => 1, 
                    ':ac1_doctype' => 18, 
                    ':ac1_ref1' => '', 
                    ':ac1_ref2' => '', 
                    ':ac1_ref3' => '', 
                    ':ac1_prc_code' => NULL, 
                    ':ac1_uncode' => NULL,
                    ':ac1_prj_code' => NULL, 
                    ':ac1_rescon_date' => NULL, 
                    ':ac1_recon_total' => 0, 
                    ':ac1_made_user' =>  $Data['mcp_createby'], 
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
                    ':ac1_line' => 0, 
                    ':business' => $Data['business'], 
                    ':branch' => $Data['branch'], 
                    ':ac1_closed_period' => 0
                ));

                if ( is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0 ){

                }else{
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'    => $resDetalleAsiento,
                        'mensaje' => 'No se pudo registrar el asiento de la cuenta'.$cuentaAr
                    );


                    return $this->response($respuesta);
                }

                // SE ACTUALIZA LA LINEA DEL BALANCE CON EL CIERRE Y EL ESTADO CERRADO
                $sqlBalance = "UPDATE abap SET bap_closureid = :bap_closureid, bap_closed = :bap_closed WHERE bap_id = :bap_id";
                $resBalance = $this->pedeo->updateRow($sqlBalance, array(
                    ':bap_closureid' => $resInsert,
                    ':bap_closed'    => 1,
                    ':bap_id'        => $cuenta['bap_id']
                ));


                if (is_numeric($resBalance) && $resBalance == 1){

                }else{
                    
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                    'error'   => true,
                    'data'	  => $resBalance,
                    'mensaje'	=> 'No se pudo registrar el cierre'
                    );
    
                    $this->response($respuesta);
    
                    return;
                }
            }

            // CABECERA DE ASIENTO CONTABLE 2
            $sqlInsertAsiento2 = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch, mac_accperiod)
            VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch, :mac_accperiod)";


            $resInsertAsiento2 = $this->pedeo->insertRow($sqlInsertAsiento2, array(
                ':mac_doc_num' => 1,
                ':mac_status' => 1,
                ':mac_base_type' => is_numeric($Data['mcp_doctype']) ? $Data['mcp_doctype'] : 0,
                ':mac_base_entry' => $resInsert,
                ':mac_doc_date' => $Data['doc_dateclose'],
                ':mac_doc_duedate' => $Data['doc_dateclose'],
                ':mac_legal_date' => $Data['doc_dateclose'],
                ':mac_ref1' => is_numeric($Data['mcp_doctype']) ? $Data['mcp_doctype'] : 0,
                ':mac_ref2' => "",
                ':mac_ref3' => "",
                ':mac_loc_total' => 0,
                ':mac_fc_total' => 0,
                ':mac_sys_total' => 0,
                ':mac_trans_dode' => 1,
                ':mac_beline_nume' => 1,
                ':mac_vat_date' => $this->validateDate($Data['mcp_docdate']) ? $Data['mcp_docdate'] : NULL,
                ':mac_serie' => 1,
                ':mac_number' => 1,
                ':mac_bammntsys' =>  0,
                ':mac_bammnt' => 0,
                ':mac_wtsum' => 1,
                ':mac_vatsum' => 0,
                ':mac_comments' => isset($Data['mcp_comment']) ? $Data['mcp_comment'] : NULL,
                ':mac_create_date' => $this->validateDate($Data['mcp_createat']) ? $Data['mcp_createat'] : NULL,
                ':mac_made_usuer' => isset($Data['mcp_createby']) ? $Data['mcp_createby'] : NULL,
                ':mac_update_date' => date("Y-m-d"),
                ':mac_update_user' => isset($Data['mcp_createby']) ? $Data['mcp_createby'] : NULL,
                ':business' => $Data['business'],
                ':branch' 	=> $Data['branch'],
                ':mac_accperiod' => $periodo['data']
            ));

            if (is_numeric($resInsertAsiento2) && $resInsertAsiento2 > 0) {
               
            } else {


                $this->pedeo->trans_rollback();

                $respuesta = array(
                'error'   => true,
                'data'	  => $resInsertAsiento2,
                'mensaje'	=> 'No se pudo registrar el cierre'
                );

                $this->response($respuesta);

                return;
            }
            //

            $TotalCierre = ($SumaDebito - $SumaCredito);
            $TotalDebito = 0;
            $TotalCredito = 0;
            $MontoSysCR = 0;
            $MontoSysDB = 0;
            
            if ( $TotalCierre > 0 ) {   

                $TotalCredito = $TotalCierre;
                $TotalDebito = 0;
                $MontoSysCR = round(($TotalCredito / $TasaLocSys), $DECI_MALES);
                $MontoSysDB = 0;

            } else {

                $TotalDebito = ($TotalCierre * -1);
                $TotalCredito = 0;
                $MontoSysDB = round(($TotalDebito / $TasaLocSys), $DECI_MALES);
                $MontoSysCR = 0;
            }

            // ASIENTO DE CUENTA ARRASTRE 
            $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

                ':ac1_trans_id' => $resInsertAsiento2, 
                ':ac1_account' => $Data['acc_balance'],
                ':ac1_debit' => $TotalDebito, 
                ':ac1_credit' => $TotalCredito, 
                ':ac1_debit_sys' => $MontoSysDB, 
                ':ac1_credit_sys' => $MontoSysCR, 
                ':ac1_currex' => 0, 
                ':ac1_doc_date' => $Data['doc_dateclose'], 
                ':ac1_doc_duedate' => $Data['doc_dateclose'], 
                ':ac1_debit_import' => 0,
                ':ac1_credit_import' => 0,
                ':ac1_debit_importsys' => 0,
                ':ac1_credit_importsys' => 0,
                ':ac1_font_key' => $resInsert, 
                ':ac1_font_line' => 1, 
                ':ac1_font_type' => $Data['mcp_doctype'], 
                ':ac1_accountvs' => 1, 
                ':ac1_doctype' => 18, 
                ':ac1_ref1' => '', 
                ':ac1_ref2' => '', 
                ':ac1_ref3' => '', 
                ':ac1_prc_code' => NULL, 
                ':ac1_uncode' => NULL,
                ':ac1_prj_code' => NULL, 
                ':ac1_rescon_date' => NULL, 
                ':ac1_recon_total' => 0, 
                ':ac1_made_user' =>  $Data['mcp_createby'], 
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
                ':ac1_line' => 0, 
                ':business' => $Data['business'], 
                ':branch' => $Data['branch'], 
                ':ac1_closed_period' => 0
            ));

            if ( is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0 ){

            }else{
                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'    => $resDetalleAsiento,
                    'mensaje' => 'No se pudo registrar el asiento de la cuenta'
                );


                return $this->response($respuesta);
            }


            if ( $TotalDebito > 0 ){

                $TotalCredito = $TotalDebito;
                $TotalDebito = 0;
                $MontoSysCR = $MontoSysDB;
                $MontoSysDB = 0;
        
            }else{

                $TotalDebito = $TotalCredito;
                $TotalCredito = 0;
                $MontoSysDB = $MontoSysCR;
                $MontoSysCR = 0;
               
            }


            // ASIENTO DE CUENTA CIERRE 
            $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

                ':ac1_trans_id' => $resInsertAsiento2, 
                ':ac1_account' => $Data['acc_close'],
                ':ac1_debit' => $TotalDebito, 
                ':ac1_credit' => $TotalCredito, 
                ':ac1_debit_sys' => $MontoSysDB, 
                ':ac1_credit_sys' => $MontoSysCR, 
                ':ac1_currex' => 0, 
                ':ac1_doc_date' => $Data['doc_dateclose'], 
                ':ac1_doc_duedate' => $Data['doc_dateclose'], 
                ':ac1_debit_import' => 0,
                ':ac1_credit_import' => 0,
                ':ac1_debit_importsys' => 0,
                ':ac1_credit_importsys' => 0,
                ':ac1_font_key' => $resInsert, 
                ':ac1_font_line' => 1, 
                ':ac1_font_type' => $Data['mcp_doctype'], 
                ':ac1_accountvs' => 1, 
                ':ac1_doctype' => 18, 
                ':ac1_ref1' => '', 
                ':ac1_ref2' => '', 
                ':ac1_ref3' => '', 
                ':ac1_prc_code' => NULL, 
                ':ac1_uncode' => NULL,
                ':ac1_prj_code' => NULL, 
                ':ac1_rescon_date' => NULL, 
                ':ac1_recon_total' => 0, 
                ':ac1_made_user' =>  $Data['mcp_createby'], 
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
                ':ac1_line' => 0, 
                ':business' => $Data['business'], 
                ':branch' => $Data['branch'], 
                ':ac1_closed_period' => 0
            ));

            if ( is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0 ){

            }else{
                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'    => $resDetalleAsiento,
                    'mensaje' => 'No se pudo registrar el asiento de la cuenta'
                );


                return $this->response($respuesta);
            }
            //

            // SE ACTUALIZA EL ARRASTRE Y CIERRE EN LA CABECERA DEL CIERRE DEL PERIODO
            $sqlUpdateCP = "UPDATE tmcp SET mcp_drag = :mcp_drag,  mcp_closing = :mcp_closing WHERE mcp_docentry = :mcp_docentry";
            $resUpdateCP = $this->pedeo->insertRow($sqlUpdateCP, array(
                ':mcp_drag' => $resInsertAsiento,
                ':mcp_closing' => $resInsertAsiento2,
                ':mcp_docentry' => $resInsert
            ));

            if ( is_numeric($resUpdateCP) && $resUpdateCP > 0 ){
            }else{

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'    => $resUpdateCP,
                    'mensaje' => 'No se pudo actualizar el cierre'
                );


                return $this->response($respuesta);
            }


            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' => 'Cierre realizado con exito'
            );



        }else{

            $this->pedeo->trans_rollback();

            $respuesta = array(
				'error'   => true,
				'data'    => $resInsert,
				'mensaje' => 'No se pudo realizar el cierre'
			);

			$this->response($respuesta);

			return;
        }



        $this->response($respuesta);

    }

    public function cancelClosingPeriod_post(){

        $Data = $this->post();

        if (!isset($Data['mcp_drag']) OR !isset($Data['mcp_closing'])){

            $respuesta = array(
                'error' => true, 'data' => [], 'mensaje' => 'Falta información requerida'
            );

            return $this->response($respuesta);
        }

        $DECI_MALES =  $this->generic->getDecimals();

        // Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
        ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
        ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
        ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, business, branch, ac1_closed_period)VALUES (:ac1_trans_id, :ac1_account,
        :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
        :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
        :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
        :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_line, :business, :branch, :ac1_closed_period)";

        //BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['series_cancelclosingperiod'],$Data['mcp_docdate'],$Data['mcp_docdate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}
        //

        //VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['mcp_docdate'], $Data['mcp_docdate'], $Data['mcp_docdate'], 1);

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
		$dataTasa = $this->tasa->Tasa($Data['mcp_currency'],$Data['mcp_docdate']);

		if(isset($dataTasa['tasaLocal'])) {

			$TasaDocLoc  = $dataTasa['tasaLocal'];
			$TasaLocSys  = $dataTasa['tasaSys'];
			$MONEDALOCAL = $dataTasa['curLocal'];
			$MONEDASYS   = $dataTasa['curSys'];
			
		} else if($dataTasa['error'] == true){

			$this->response($dataTasa, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//FIN DE PROCESO DE TASA


        // INICIA LA TRANSACCION
        $this->pedeo->trans_begin();

        // SE CREA LA PRIMERA ANULACION
        $sqlInsertAnulacion = "INSERT INTO tban(ban_docnum,ban_docdate,ban_comment,ban_createat,ban_baseentry,ban_basetype,ban_doctype,ban_series,ban_createby,ban_currency,business,branch)
						VALUES(:ban_docnum,:ban_docdate,:ban_comment,:ban_createat,:ban_baseentry,:ban_basetype,:ban_doctype,:ban_series,:ban_createby,:ban_currency,:business,:branch)";

        $resInsertAnulacion = $this->pedeo->insertRow($sqlInsertAnulacion, array(

            ':ban_docnum' => $DocNumVerificado,
            ':ban_docdate' => $Data['mcp_docdate'],
            ':ban_comment' => $Data['comment_cancelclosingperiod'],
            ':ban_createat' => date('Y-m-d'),
            ':ban_baseentry' => NULL,
            ':ban_basetype' => NULL,
            ':ban_doctype' => 50,
            ':ban_series' => $Data['series_cancelclosingperiod'],
            ':ban_createby' => $Data['mcp_createby'],
            ':ban_currency' => $Data['mcp_currency'],
            ':business' => $Data['business'],
            ':branch' => $Data['branch']

        ));

        if ( is_numeric($resInsertAnulacion) && $resInsertAnulacion > 0 ) {

        } else {

            $respuesta = array(
                    'error'   => true,
                    'data'    => $resInsertAnulacion,
                    'mensaje' => 'No se pudo crear la anulación'
                );

            return $respuesta;
        }
        //
        // Se actualiza la serie de la numeracion del documento 1

        $sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
                                WHERE pgs_id = :pgs_id";
        $resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
            ':pgs_nextnum' => $DocNumVerificado,
            ':pgs_id'      => $Data['series_cancelclosingperiod']
        ));


        if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
        } else {
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $resActualizarNumeracion,
                'mensaje'	=> 'No se pudo actualizar la numeración de la anulación'
            );

            return $this->response($respuesta);
        }
        // Fin de la actualizacion de la numeracion del documento
        // CABECERA DE ASIENTO CONTABLE
        $sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch, mac_accperiod)
        VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch, :mac_accperiod)";


        $resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(
            ':mac_doc_num' => 1,
            ':mac_status' => 1,
            ':mac_base_type' => 50,
            ':mac_base_entry' => $resInsertAnulacion,
            ':mac_doc_date' => $this->validateDate($Data['mcp_docdate']) ? $Data['mcp_docdate'] : NULL,
            ':mac_doc_duedate' => $this->validateDate($Data['mcp_docdate']) ? $Data['mcp_docdate'] : NULL,
            ':mac_legal_date' => $this->validateDate($Data['mcp_docdate']) ? $Data['mcp_docdate'] : NULL,
            ':mac_ref1' => 50,
            ':mac_ref2' => "",
            ':mac_ref3' => "",
            ':mac_loc_total' => 0,
            ':mac_fc_total' => 0,
            ':mac_sys_total' => 0,
            ':mac_trans_dode' => 1,
            ':mac_beline_nume' => 1,
            ':mac_vat_date' => $this->validateDate($Data['mcp_docdate']) ? $Data['mcp_docdate'] : NULL,
            ':mac_serie' => 1,
            ':mac_number' => 1,
            ':mac_bammntsys' =>  0,
            ':mac_bammnt' => 0,
            ':mac_wtsum' => 1,
            ':mac_vatsum' => 0,
            ':mac_comments' => isset($Data['mcp_comment']) ? $Data['mcp_comment'] : NULL,
            ':mac_create_date' => $this->validateDate($Data['mcp_docdate']) ? $Data['mcp_docdate'] : NULL,
            ':mac_made_usuer' => isset($Data['mcp_createby']) ? $Data['mcp_createby'] : NULL,
            ':mac_update_date' => date("Y-m-d"),
            ':mac_update_user' => isset($Data['mcp_createby']) ? $Data['mcp_createby'] : NULL,
            ':business' => $Data['business'],
            ':branch' 	=> $Data['branch'],
            ':mac_accperiod' => $periodo['data']
        ));

        if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
 
        } else {

        
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'	  => $resInsertAsiento,
                'mensaje'	=> 'No la anulación del cierre'
            );

            $this->response($respuesta);

            return;
        }
        //


        // SE VOLTEAN LOS ASIENTOS DE ARRASTRE
        $sqlAsientoArrastre = "SELECT * FROM mac1 WHERE ac1_trans_id = :ac1_trans_id";
        $resAsientoArrastre = $this->pedeo->queryTable($sqlAsientoArrastre, array(
            ':ac1_trans_id' => $Data['mcp_drag']
        ));

        if (!isset($resAsientoArrastre[0])){
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $resAsientoArrastre,
                'mensaje' => 'No se encontro el asiento de arraste de saldos'
            );

            return $this->response($respuesta);
        }

        foreach ($resAsientoArrastre as $key => $detalle) {

            $sqlInsertDetalle = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate, ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype, ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord, ac1_ven_debit, ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_card_type, business, branch, ac1_codret, ac1_base_tax)
                                 VALUES (:ac1_trans_id, :ac1_account, :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys, :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode, :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct, :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_card_type, :business, :branch, :ac1_codret, :ac1_base_tax)";
            
            $debito = 0;
            $credito = 0;

            $debitosys = 0;
            $creditosys= 0;

            if ( $detalle['ac1_debit'] > 0 ){
            
                $debito = 0;
                $credito = $detalle['ac1_debit'];

             
                
            }

            if ( $detalle['ac1_credit'] > 0 ){
            
                $debito = $detalle['ac1_credit'];;
                $credito = 0;
                
              
            }

            if ( $detalle['ac1_debit_sys'] > 0 ){
            
                $debitosys = 0;
                $creditosys = $detalle['ac1_debit_sys'];
                
            }

            if ( $detalle['ac1_credit_sys'] > 0 ){
            
                $debitosys = $detalle['ac1_credit_sys'];
                $creditosys = 0;
                
            }
            
            $resInsertDetalle = $this->pedeo->insertRow($sqlInsertDetalle, array(

                ':ac1_trans_id' => $resInsertAsiento,
                ':ac1_account' => $detalle['ac1_account'], 
                ':ac1_debit' => $debito, 
                ':ac1_credit' => $credito, 
                ':ac1_debit_sys' => $debitosys, 
                ':ac1_credit_sys' => $creditosys, 
                ':ac1_currex' => $detalle['ac1_currex'], 
                ':ac1_doc_date' => $detalle['ac1_doc_date'], 
                ':ac1_doc_duedate' => $detalle['ac1_doc_date'],
                ':ac1_debit_import' => $detalle['ac1_debit_import'], 
                ':ac1_credit_import' => $detalle['ac1_credit_import'], 
                ':ac1_debit_importsys' => $detalle['ac1_debit_importsys'], 
                ':ac1_credit_importsys' => $detalle['ac1_credit_importsys'], 
                ':ac1_font_key' => $resInsertAsiento,
                ':ac1_font_line' => $detalle['ac1_font_line'], 
                ':ac1_font_type' => 18,
                ':ac1_accountvs' => $detalle['ac1_accountvs'], 
                ':ac1_doctype' => $detalle['ac1_doctype'],
                ':ac1_ref1' => $detalle['ac1_ref1'], 
                ':ac1_ref2' => $detalle['ac1_ref2'], 
                ':ac1_ref3' => $detalle['ac1_ref3'], 
                ':ac1_prc_code' => $detalle['ac1_prc_code'], 
                ':ac1_uncode' => $detalle['ac1_uncode'], 
                ':ac1_prj_code' => $detalle['ac1_prj_code'], 
                ':ac1_rescon_date' => $detalle['ac1_rescon_date'],
                ':ac1_recon_total' => $detalle['ac1_recon_total'], 
                ':ac1_made_user' => $detalle['ac1_made_user'], 
                ':ac1_accperiod' => $periodo['data'],
                ':ac1_close' => $detalle['ac1_close'], 
                ':ac1_cord' => $detalle['ac1_cord'], 
                ':ac1_ven_debit' => 0, 
                ':ac1_ven_credit' => 0, 
                ':ac1_fiscal_acct' => $detalle['ac1_fiscal_acct'], 
                ':ac1_taxid' => $detalle['ac1_taxid'], 
                ':ac1_isrti' => $detalle['ac1_isrti'],
                ':ac1_basert' => $detalle['ac1_basert'],
                ':ac1_mmcode' => $detalle['ac1_mmcode'],
                ':ac1_legal_num' => $detalle['ac1_legal_num'], 
                ':ac1_codref' => $detalle['ac1_codref'], 
                ':ac1_card_type' => $detalle['ac1_card_type'], 
                ':business' => $detalle['business'],
                ':branch'   => $detalle['branch'], 
                ':ac1_codret' => $detalle['ac1_codret'], 
                ':ac1_base_tax' => $detalle['ac1_base_tax'] 
            ));

            if (is_numeric($resInsertDetalle) && $resInsertDetalle > 0 ) {

            
            }else{

                $this->pedeo->trans_rollback();
                
                $respuesta = array(
                    'error' => true,
                    'data' => $resInsertDetalle,
                    'mensaje' => 'Error al insertar la copia del detalle del asiento'
                );

                return $respuesta;
            }
        }
        //
        // SE ACTUALIZA EL ESTADO DEL ASIENTO VIEJO
        $sqlUpdateMacStatus1 = "UPDATE tmac set mac_status = :mac_status WHERE mac_trans_id =:mac_trans_id";
        $resUpdateMacStatus1 = $this->pedeo->updateRow($sqlUpdateMacStatus1, array(
            ':mac_status' => 2,
            ':mac_trans_id' => $Data['mcp_drag']
        ));

        if(is_numeric($resUpdateMacStatus1) && $resUpdateMacStatus1 > 0) {

        }else{
            $this->pedeo->trans_rollback();
            $respuesta = array(
                'error' => true,
                'data' => $resUpdateMacStatus1,
                'mensaje' => 'Error al actualizar el estado del asiento'
            );

            return $respuesta;
        }
        //

        // CABECERA DE ASIENTO CONTABLE 2
        $sqlInsertAsiento2 = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch, mac_accperiod)
        VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch, :mac_accperiod)";


        $resInsertAsiento2 = $this->pedeo->insertRow($sqlInsertAsiento2, array(
            ':mac_doc_num' => 1,
            ':mac_status' => 1,
            ':mac_base_type' => 50,
            ':mac_base_entry' => $resInsertAnulacion,
            ':mac_doc_date' => $this->validateDate($Data['mcp_docdate']) ? $Data['mcp_docdate'] : NULL,
            ':mac_doc_duedate' => $this->validateDate($Data['mcp_docdate']) ? $Data['mcp_docdate'] : NULL,
            ':mac_legal_date' => $this->validateDate($Data['mcp_docdate']) ? $Data['mcp_docdate'] : NULL,
            ':mac_ref1' => 50,
            ':mac_ref2' => "",
            ':mac_ref3' => "",
            ':mac_loc_total' => 0,
            ':mac_fc_total' => 0,
            ':mac_sys_total' => 0,
            ':mac_trans_dode' => 1,
            ':mac_beline_nume' => 1,
            ':mac_vat_date' => $this->validateDate($Data['mcp_docdate']) ? $Data['mcp_docdate'] : NULL,
            ':mac_serie' => 1,
            ':mac_number' => 1,
            ':mac_bammntsys' =>  0,
            ':mac_bammnt' => 0,
            ':mac_wtsum' => 1,
            ':mac_vatsum' => 0,
            ':mac_comments' => isset($Data['mcp_comment']) ? $Data['mcp_comment'] : NULL,
            ':mac_create_date' => $this->validateDate($Data['mcp_docdate']) ? $Data['mcp_docdate'] : NULL,
            ':mac_made_usuer' => isset($Data['mcp_createby']) ? $Data['mcp_createby'] : NULL,
            ':mac_update_date' => date("Y-m-d"),
            ':mac_update_user' => isset($Data['mcp_createby']) ? $Data['mcp_createby'] : NULL,
            ':business' => $Data['business'],
            ':branch' 	=> $Data['branch'],
            ':mac_accperiod' => $periodo['data']
        ));

        if (is_numeric($resInsertAsiento2) && $resInsertAsiento2 > 0) {

        } else {

        
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'	  => $resInsertAsiento2,
                'mensaje'	=> 'No la anulación del cierre'
            );

            $this->response($respuesta);

            return;
        }
        //
        // SE VOLTEA EL ASIENTO DEL CIERRE PARA UTILIDAD O PERDIDA
        $sqlAsientoUtilidadPerdida = "SELECT * FROM mac1 WHERE ac1_trans_id = :ac1_trans_id";
        $resAsientoUtilidadPerdida = $this->pedeo->queryTable($sqlAsientoUtilidadPerdida, array(
            ':ac1_trans_id' => $Data['mcp_closing']
        ));

        if (!isset($resAsientoUtilidadPerdida[0])){
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $resAsientoUtilidadPerdida,
                'mensaje' => 'No se encontro el asiento de cierre para utilidad o perdida'
            );

            return $this->response($respuesta);
        }

        foreach ($resAsientoUtilidadPerdida as $key => $detalle) {

            $sqlInsertDetalle = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate, ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype, ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord, ac1_ven_debit, ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_card_type, business, branch, ac1_codret, ac1_base_tax)
                                 VALUES (:ac1_trans_id, :ac1_account, :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys, :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode, :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct, :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_card_type, :business, :branch, :ac1_codret, :ac1_base_tax)";
            
            $debito = 0;
            $credito = 0;

            $debitosys = 0;
            $creditosys= 0;

            if ( $detalle['ac1_debit'] > 0 ){
            
                $debito = 0;
                $credito = $detalle['ac1_debit'];

             
                
            }

            if ( $detalle['ac1_credit'] > 0 ){
            
                $debito = $detalle['ac1_credit'];;
                $credito = 0;
                
              
            }

            if ( $detalle['ac1_debit_sys'] > 0 ){
            
                $debitosys = 0;
                $creditosys = $detalle['ac1_debit_sys'];
                
            }

            if ( $detalle['ac1_credit_sys'] > 0 ){
            
                $debitosys = $detalle['ac1_credit_sys'];
                $creditosys = 0;
                
            }
            
            $resInsertDetalle = $this->pedeo->insertRow($sqlInsertDetalle, array(

                ':ac1_trans_id' => $resInsertAsiento2,
                ':ac1_account' => $detalle['ac1_account'], 
                ':ac1_debit' => $debito, 
                ':ac1_credit' => $credito, 
                ':ac1_debit_sys' => $debitosys, 
                ':ac1_credit_sys' => $creditosys, 
                ':ac1_currex' => $detalle['ac1_currex'], 
                ':ac1_doc_date' => $detalle['ac1_doc_date'], 
                ':ac1_doc_duedate' => $detalle['ac1_doc_date'],
                ':ac1_debit_import' => $detalle['ac1_debit_import'], 
                ':ac1_credit_import' => $detalle['ac1_credit_import'], 
                ':ac1_debit_importsys' => $detalle['ac1_debit_importsys'], 
                ':ac1_credit_importsys' => $detalle['ac1_credit_importsys'], 
                ':ac1_font_key' => $resInsertAsiento2,
                ':ac1_font_line' => $detalle['ac1_font_line'], 
                ':ac1_font_type' => 18,
                ':ac1_accountvs' => $detalle['ac1_accountvs'], 
                ':ac1_doctype' => $detalle['ac1_doctype'],
                ':ac1_ref1' => $detalle['ac1_ref1'], 
                ':ac1_ref2' => $detalle['ac1_ref2'], 
                ':ac1_ref3' => $detalle['ac1_ref3'], 
                ':ac1_prc_code' => $detalle['ac1_prc_code'], 
                ':ac1_uncode' => $detalle['ac1_uncode'], 
                ':ac1_prj_code' => $detalle['ac1_prj_code'], 
                ':ac1_rescon_date' => $detalle['ac1_rescon_date'],
                ':ac1_recon_total' => $detalle['ac1_recon_total'], 
                ':ac1_made_user' => $detalle['ac1_made_user'], 
                ':ac1_accperiod' => $periodo['data'],
                ':ac1_close' => $detalle['ac1_close'], 
                ':ac1_cord' => $detalle['ac1_cord'], 
                ':ac1_ven_debit' => 0, 
                ':ac1_ven_credit' => 0, 
                ':ac1_fiscal_acct' => $detalle['ac1_fiscal_acct'], 
                ':ac1_taxid' => $detalle['ac1_taxid'], 
                ':ac1_isrti' => $detalle['ac1_isrti'],
                ':ac1_basert' => $detalle['ac1_basert'],
                ':ac1_mmcode' => $detalle['ac1_mmcode'],
                ':ac1_legal_num' => $detalle['ac1_legal_num'], 
                ':ac1_codref' => $detalle['ac1_codref'], 
                ':ac1_card_type' => $detalle['ac1_card_type'], 
                ':business' => $detalle['business'],
                ':branch'   => $detalle['branch'], 
                ':ac1_codret' => $detalle['ac1_codret'], 
                ':ac1_base_tax' => $detalle['ac1_base_tax'] 
            ));

            if (is_numeric($resInsertDetalle) && $resInsertDetalle > 0 ) {
            
            }else{

                $this->pedeo->trans_rollback();
                
                $respuesta = array(
                    'error' => true,
                    'data' => $resInsertDetalle,
                    'mensaje' => 'Error al insertar la copia del detalle del asiento'
                );

                return $respuesta;
            }
        }
        //
        // SE ACTUALIZA EL ESTADO DEL ASIENTO VIEJO
        $sqlUpdateMacStatus2 = "UPDATE tmac set mac_status = :mac_status WHERE mac_trans_id =:mac_trans_id";
        $resUpdateMacStatus2 = $this->pedeo->updateRow($sqlUpdateMacStatus2, array(
            ':mac_status' => 2,
            ':mac_trans_id' => $Data['mcp_closing']
        ));

        if(is_numeric($resUpdateMacStatus2) && $resUpdateMacStatus2 > 0) {

        }else{
            $this->pedeo->trans_rollback();
            $respuesta = array(
                'error' => true,
                'data' => $resUpdateMacStatus2,
                'mensaje' => 'Error al actualizar el estado del asiento'
            );

            return $respuesta;
        }
        //

        //SE CAMBIA EL ESTADO DEL CIERRE
        $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
        VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

        $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


            ':bed_docentry' => $Data['mcp_docentry'],
            ':bed_doctype' => $Data['mcp_doctype'],
            ':bed_status' => 2, //ESTADO ANULADO
            ':bed_createby' => $Data['mcp_createby'],
            ':bed_date' => date('Y-m-d'),
            ':bed_baseentry' => $resInsertAnulacion,
            ':bed_basetype' => 50
        ));


        if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
        } else {
            $this->pedeo->trans_rollback();
            $respuesta = array(
                'error'   => true,
                'data' => $resInsertEstado,
                'mensaje'	=> 'No se pudo cambiar el estado del cierre'
            );

            return $this->response($respuesta);
            
        }
        //
        // SE LIBERAN LAS CUENTAS EN EL BALANCE AFECTADAS POR EL CIERRE
        $sqlBalance = "SELECT * from abap where bap_closureid = :bap_closureid";
        $resBalance = $this->pedeo->queryTable($sqlBalance, array(
            ':bap_closureid' => $Data['mcp_docentry']
        ));

        if (!isset($resBalance[0])){
            $this->pedeo->trans_rollback();
            $respuesta = array(
                'error' => true,
                'data' => $resBalance,
                'mensaje' => 'Error al actualizar el estado del asiento'
            );

            return $respuesta;
        }

        foreach ($resBalance as $key => $balance) {
            $sqlUpdateBalance = "UPDATE abap set bap_closed = :bap_closed, bap_closureid = :bap_closureid WHERE bap_id = :bap_id";
            $resUpdateBalance = $this->pedeo->updateRow($sqlUpdateBalance, array(
                ':bap_closed' => 0,
                ':bap_closureid' => 0,
                ':bap_id' => $balance['bap_id']
            ));

            if (is_numeric($resUpdateBalance) && $resUpdateBalance > 0){

            }else{
                $this->pedeo->trans_rollback();
                $respuesta = array(
                    'error' => true,
                    'data' => $resBalance,
                    'mensaje' => 'Error al actualizar el estado del asiento'
                );
    
                return $respuesta;
            }
        }
        //

        $this->pedeo->trans_commit();

        $respuesta = array(
            'error' => false,
            'data' => [],
            'mensaje' => 'Cierre de período anulado con exito'
        );

        $this->response($respuesta);

    }


    private function buscarPosicion($llave, $inArray)
	{
		$res = 0;
		for ($i = 0; $i < count($inArray); $i++) {
			if ($inArray[$i] == "$llave") {
				$res =  $i;
				break;
			}
		}

		return $res;
	}

	private function validateDate($fecha)
	{
		if (strlen($fecha) == 10 or strlen($fecha) > 10) {
			return true;
		} else {
			return false;
		}
	}



}
