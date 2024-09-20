<?php
// PARAMETRIZACION DE MODULARES CLASIFICACION DE PAGINA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class CashOperation extends REST_Controller {

	private $pdo;

	public function __construct() {

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
                        LEFT JOIN responsestatus  ON bco_id = responsestatus.id and bco_doctype = responsestatus.tipo
                        WHERE business = :business 
                        AND branch = :branch 
                        AND bco_boxid = :bco_boxid
                        AND coalesce(bco_doctype, 0) between 0 and 48 
                        AND coalesce(estado, 'Abierto') <> 'Anulado'
                        ORDER BY bco_id DESC LIMIT 1";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ':business'    => $Data['business'],
            ':branch'      => $Data['branch'],
            ':bco_boxid'   => $Data['bcc_id']
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
    //
    public function getInfoCierre_get(){

        $Data = $this->get();

        $sqlSelect = "SELECT bco_id, bco_boxid, bco_date, bco_time, bco_status,
                        bco_amount, bco_total, current_date as factual,
                        ( coalesce(get_monto_caja(:bco_fechac, bco_account), 0) + bco_total) as monto
                        FROM tbco
                        LEFT JOIN responsestatus  ON bco_id = responsestatus.id and bco_doctype = responsestatus.tipo
                        WHERE business = :business 
                        AND branch = :branch 
                        AND bco_boxid = :bco_boxid
                        AND coalesce(bco_doctype, 0) between 0 and 48 
                        AND coalesce(estado, 'Abierto') <> 'Anulado'
                        ORDER BY bco_id DESC LIMIT 1";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ':business'   => $Data['business'],
            ':branch'     => $Data['branch'],
            ':bco_boxid'  => $Data['bcc_id'],
            ':bco_fechac' => $Data['bco_fechac']
        ));


        $fechaAc = null;
        $resUser = [];

        if ( isset($resSelect[0]) ) {

            $fechaAc = $resSelect[0]['bco_date'].' '.$resSelect[0]['bco_time'];

            // $fechaAc = $resSelect[0]['bco_date'].' 08:00:00';

            $resUser = $this->pedeo->queryTable("SELECT bcc_user FROM tbcc WHERE bcc_id = :bcc_id", array(":bcc_id" => $resSelect[0]['bco_boxid']));

            if (!isset($resUser[0])) {


                $respuesta = array(
                    'error'   => true,
                    'data' => array(),
                    'mensaje'	=> 'Falta el usuario de la caja'
                );

                return $this->response($respuesta);

            }
        }

       

        $sqlSelect2 = "SELECT sum(vrc_total_c),mdp_name
                        FROM dvrc
                        inner join tmdp on vrc_paymentmethod = mdp_id
                        inner join responsestatus on dvrc.vrc_docentry = responsestatus.id and dvrc.vrc_doctype = responsestatus.tipo 
                        where mdp_local = 0
                        and tmdp.mdp_multiple = 0
                        and responsestatus.estado = 'Cerrado'
                        and vrc_createat  >= :fecha
                        and vrc_docdate = :fecha2
                        and dvrc.vrc_createby = :vrc_createby
                        and dvrc.business = :business
                        and dvrc.branch = :branch
                        group by mdp_id,mdp_name
                        union all
                        SELECT sum(vmpp.mpp_valor), mdp_name
                        FROM dvrc
                        inner join vmpp on vrc_docentry = mpp_docentry
                        inner join tmdp on vmpp.mpp_medio::int = tmdp.mdp_id
                        inner join responsestatus on dvrc.vrc_docentry = responsestatus.id and dvrc.vrc_doctype = responsestatus.tipo 
                        where mdp_local = 0
                        and responsestatus.estado = 'Cerrado'
                        and vrc_createat  >= :fecha
                        and vrc_docdate = :fecha2
                        and dvrc.vrc_createby = :vrc_createby
                        and dvrc.business = :business
                        and dvrc.branch = :branch
                        group by mdp_id,mdp_name";

        $resSelect2 = $this->pedeo->queryTable($sqlSelect2, array(
            ':fecha'        => $fechaAc,
            ':business'     => $Data['business'],
            ':branch'       => $Data['branch'],
            ':fecha2'       => $Data['bco_fechac'],
            ':vrc_createby' => $resUser[0]['bcc_user']
        ));

        if(isset($resSelect[0])){

            $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'otros' => $resSelect2, 
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
    //

    // Aperturar Caja
    public function openBox_post() {

        $Data = $this->post();

        if (!isset($Data['bco_boxid']) OR !isset($Data['bco_amount']) OR !isset($Data['bco_total'])){

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'Faltan campos requeridos');

            return $this->response($respuesta);
        }


        $sql = "SELECT * 
                FROM tbco 
                LEFT JOIN responsestatus  ON bco_id = responsestatus.id AND bco_doctype = responsestatus.tipo
                WHERE bco_boxid = :bco_boxid 
                AND coalesce(bco_doctype, 0) between 0 and 48 
                AND coalesce(estado, 'Abierto') <> 'Anulado'
                ORDER BY bco_id DESC LIMIT 1";
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

        $sqlInsert = "INSERT INTO tbco(bco_boxid, bco_date, bco_time, bco_status, bco_amount, business, branch, bco_total, bco_account)VALUES(:bco_boxid, :bco_date, :bco_time, :bco_status, :bco_amount, :business, :branch, :bco_total, :bco_account)";

        $resSqlInsert = $this->pedeo->insertRow($sqlInsert, array(
            
            ':bco_boxid' => $Data['bco_boxid'], 
            ':bco_date' => date('Y-m-d'), 
            ':bco_time' => date('H:i:s'), 
            ':bco_status' => 1, 
            ':bco_amount' => $Data['bco_amount'], 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'], 
            ':bco_total' => $Data['bco_total'],
            ':bco_account' => $Data['bco_account']

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


    public function closeBox_post() {

        $Data = $this->post();

        $DECI_MALES =  $this->generic->getDecimals();

        $DocNumVerificado = 0;

        $AC1LINE = 0;

        if (!isset($Data['bco_boxid']) OR !isset($Data['bco_amount']) OR !isset($Data['bco_total'])){

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'Faltan campos requeridos');

            return $this->response($respuesta);
        }

        $sql = "SELECT * 
                FROM tbco 
                LEFT JOIN responsestatus  ON bco_id = responsestatus.id and bco_doctype = responsestatus.tipo
                WHERE bco_boxid = :bco_boxid 
                AND coalesce(bco_doctype, 0) between 0 and 48 
                AND coalesce(estado, 'Abierto') <> 'Anulado'
                ORDER BY bco_id DESC LIMIT 1";

        $resSql = $this->pedeo->queryTable($sql, array(
            ':bco_boxid'  => $Data['bco_boxid'],
        ));

        if (isset($resSql[0])) {

            if ( $resSql[0]['bco_status'] == 0 ) {

                $respuesta = array(
                    'error' => true,
                    'data'  => [],
                    'mensaje' => 'La caja ya se encuentra cerrada');
    
                return $this->response($respuesta);
            }

            if ( $resSql[0]['bco_status'] == 1 && $resSql[0]['bco_date'] != $Data['bco_fechac'] ) {

                $respuesta = array(
                    'error' => true,
                    'data'  => [],
                    'mensaje' => 'La fecha de cierre no coincide con la fecha de apertura: '.$resSql[0]['bco_date']);
    
                return $this->response($respuesta);
            }


        } else {

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'No hay registros de apertura');

            return $this->response($respuesta);
        }

        // BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['bco_series'],$Data['bco_fechac'],$Data['bco_fechac']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}
        //
        //VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['bco_fechac'], $Data['bco_fechac'], $Data['bco_fechac'], 1);

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
		$dataTasa = $this->tasa->Tasa($Data['bco_currency'],$Data['bco_fechac']);

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

        $this->pedeo->trans_begin();

        $hora = '23:00:00';

        if ( date('Y-m-d') == $Data['bco_fechac'] ) {

            $hora = date('H:i:s');
        }

        $sqlInsert = "INSERT INTO tbco(bco_boxid, bco_date, bco_time, bco_status, bco_amount, business, branch, bco_total, bco_bank, bco_createdat, bco_doctype, bco_series, bco_currency, bco_account, bco_docnum)VALUES(:bco_boxid, :bco_date, :bco_time, :bco_status, :bco_amount, :business, :branch, :bco_total, :bco_bank, :bco_createdat, :bco_doctype, :bco_series, :bco_currency, :bco_account, :bco_docnum)";

        $resSqlInsert = $this->pedeo->insertRow($sqlInsert, array (
            
            ':bco_boxid' => $Data['bco_boxid'], 
            ':bco_date' => $Data['bco_fechac'], 
            ':bco_time' => $hora, 
            ':bco_status' => 0, 
            ':bco_amount' => $Data['bco_amount'], 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'], 
            ':bco_total' => $Data['bco_total'],
            ':bco_bank' => $Data['bco_bank'],
            ':bco_createdat' => date('Y-m-d'),
            ':bco_doctype' => $Data['bco_doctype'],
            ':bco_series' => $Data['bco_series'],
            ':bco_currency' => $Data['bco_currency'],
            ':bco_account' => $Data['bco_account'],
            ':bco_docnum' => $DocNumVerificado,
        ));

        if (is_numeric($resSqlInsert) && $resSqlInsert > 0) {

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
                    'mensaje'	=> 'No se pudo actualizar la numeracion'
                );

                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                return;
            }
            // Fin de la actualizacion de la numeracion del documento

            //SE INSERTA EL ESTADO DEL DOCUMENTO

            $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
            VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

            $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array (
                ':bed_docentry' => $resSqlInsert,
                ':bed_doctype' => $Data['bco_doctype'],
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
            if ( $Data['bco_total'] > 0 ){ 

                $sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, mac_accperiod, business, branch)
                VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :mac_accperiod, :business, :branch)";
    
    
                $resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(
    
                    ':mac_doc_num' => 1,
                    ':mac_status' => 1,
                    ':mac_base_type' => is_numeric($Data['bco_doctype']) ? $Data['bco_doctype'] : 0,
                    ':mac_base_entry' => $resSqlInsert,
                    ':mac_doc_date' => $this->validateDate($Data['bco_fechac']) ? $Data['bco_fechac'] : NULL,
                    ':mac_doc_duedate' => $this->validateDate($Data['bco_fechac']) ? $Data['bco_fechac'] : NULL,
                    ':mac_legal_date' => $this->validateDate($Data['bco_fechac']) ? $Data['bco_fechac'] : NULL,
                    ':mac_ref1' => is_numeric($Data['bco_doctype']) ? $Data['bco_doctype'] : 0,
                    ':mac_ref2' => "",
                    ':mac_ref3' => "",
                    ':mac_loc_total' => is_numeric($Data['bco_total']) ? $Data['bco_total'] : 0,
                    ':mac_fc_total' => is_numeric($Data['bco_total']) ? $Data['bco_total'] : 0,
                    ':mac_sys_total' => is_numeric($Data['bco_total']) ? $Data['bco_total'] : 0,
                    ':mac_trans_dode' => 1,
                    ':mac_beline_nume' => 1,
                    ':mac_vat_date' => $this->validateDate($Data['bco_fechac']) ? $Data['bco_fechac'] : NULL,
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
                    ':branch' 	  => $Data['branch']
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
            }
            //

            // ASIENTO DE BANCO
            if ( $Data['bco_total'] > 0 ){

                $montoTotal = $Data['bco_total'];
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
               
                $BALANCE = $this->account->addBalance($periodo['data'], round($montoTotal, $DECI_MALES),  $Data['bco_bank'], 1, $Data['bco_fechac'], $Data['business'], $Data['branch']);
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
                    ':ac1_account' => $Data['bco_bank'],
                    ':ac1_debit' => round($montoTotal, $DECI_MALES),
                    ':ac1_credit' => 0,
                    ':ac1_debit_sys' => round($montoSys, $DECI_MALES),
                    ':ac1_credit_sys' => 0,
                    ':ac1_currex' => 0,
                    ':ac1_doc_date' => $this->validateDate($Data['bco_fechac']) ? $Data['bco_fechac'] : NULL,
                    ':ac1_doc_duedate' => $this->validateDate($Data['bco_fechac']) ? $Data['bco_fechac'] : NULL,
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resSqlInsert,
                    ':ac1_font_line' => 1,
                    ':ac1_font_type' => is_numeric($Data['bco_doctype']) ? $Data['bco_doctype'] : 0,
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
            
                $montoTotal = $Data['bco_total'];
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

                $BALANCE = $this->account->addBalance($periodo['data'], round($montoTotal, $DECI_MALES),  $Data['bco_account'], 2, $Data['bco_fechac'], $Data['business'], $Data['branch']);
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
                    ':ac1_account' => $Data['bco_account'],
                    ':ac1_debit' => 0,
                    ':ac1_credit' => round($montoTotal, $DECI_MALES),
                    ':ac1_debit_sys' => 0,
                    ':ac1_credit_sys' => round($montoSys, $DECI_MALES),
                    ':ac1_currex' => 0,
                    ':ac1_doc_date' => $this->validateDate($Data['bco_fechac']) ? $Data['bco_fechac'] : NULL,
                    ':ac1_doc_duedate' => $this->validateDate($Data['bco_fechac']) ? $Data['bco_fechac'] : NULL,
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resSqlInsert,
                    ':ac1_font_line' => 1,
                    ':ac1_font_type' => is_numeric($Data['bco_doctype']) ? $Data['bco_doctype'] : 0,
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
            }



            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data'  => [],
                'mensaje' => 'Caja cerrada con exito');

        } else {

            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error' => true,
                'data'  => $resSqlInsert,
                'mensaje' => 'No se pudo cerrar la caja');

        }

        $this->response($respuesta);


    }

    public function createRcd_post() {

        $Data = $this->post();

        $DECI_MALES =  $this->generic->getDecimals();
        $PARA_MTRS = $this->generic->getParams();

        $DetalleCostoCosto = new stdClass();
        $DetalleCostoInventario = new stdClass();
        $DetalleIngresoVenta = new stdClass();
        $DetalleIngresoDevolucion = new stdClass();
        $DetalleCostoCostoDev = new stdClass();
        $DetalleCostoInventarioDev = new stdClass();
        $DetalleIvaVenta = new stdClass();
        $DetalleIvaDev = new stdClass();
        $DetalleAsientoImpMultiVenta = new stdClass();
        $DetalleAsientoImpMultiDev = new stdClass();

        $DetalleConsolidadoCostoInventario = [];
		$DetalleConsolidadoCostoCosto = [];
        $DetalleConsolidadoIngresoVenta = [];
        $DetalleConsolidadoIngresoDevolucion = [];
        $DetalleConsolidadoCostoInventarioDev = [];
		$DetalleConsolidadoCostoCostoDev = [];
        $DetalleConsolidadoIvaVenta = [];
        $DetalleConsolidadoIvaDev = [];
        $DetalleConsolidadoImpMultiVenta = [];
        $DetalleConsolidadoImpMultiDev = [];

        $inArrayCostoInventario = array();
		$inArrayCostoCosto = array();
        $inArrayIngresoVenta = array();
		$inArrayIngresoDevolucion = array();
        $inArrayCostoInventarioDev = array();
		$inArrayCostoCostoDev = array();
        $inArrayIvaVenta = array();
        $inArrayIvaDev = array();
        $inArrayImpMultiVenta = array();
        $inArrayImpMultiDev = array();

        $llaveCostoInventario = "";
		$llaveCostoCosto = "";
        $llaveIngresoVenta = "";
		$llaveIngresoDevolucion = "";
        $llaveCostoInventarioDev = "";
		$llaveCostoCostoDev = "";
        $llaveIvaVenta = "";
        $llaveIvaDev = "";
        $llaveImpMultiVenta = "";
        $llaveImpMultiDev = "";

        $posicionCostoInventario = 0;
		$posicionCostoCosto = 0;
        $posicionIngresoVenta = 0;
		$posicionIngresoDevolucion = 0;
        $posicionCostoInventarioDev = 0;
		$posicionCostoCostoDev = 0;
        $posicionIvaVenta = 0;
        $posicionIvaDev = 0;
        $posicionImpMultiVenta = 0;
        $posicionImpMultiDev = 0;

        $montoCajaLocal = 0; // SI EL PAGO VA A LA CAJA MENOR
        $montoMultiple  = 0; // SI EXISTEN VARIAS MEDIO DE PAGO

        $ManejaInvetario = 0;

        $AC1LINE = 0;

        $totalVatSum = 0;
        $totalLineTotal = 0;
        $soloServicio = true;

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
        $FacturaComsumidor = 0;
        // VERIFICAR EL TIPO DE FACTURA SEGUN EL MEDIO DE PAGO
        if ( !isset($Data['vrc_cardcode2']) || empty($Data['vrc_cardcode2']) || $Data['vrc_cardcode2'] == 'undefined' &&
          !isset($Data['vrc_cardname2']) || empty($Data['vrc_cardname2']) || $Data['vrc_cardname2'] == 'undefined' ) {
            $FacturaComsumidor = 0;
        }else{
            $FacturaComsumidor = 1;
        }
        //
        // VERIFICAR QUE NO SE HAGA UNA FACTURA DE CONSUMIDOR CON EL MEDIO DE PAGO CONTADO
        if ( isset($Data['paytype_days']) && $Data['paytype_days'] == 0 && $FacturaComsumidor == 1 ) {

            $respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se puede generar una factura con la relación consumidor y pagador, si la forma de pago es Contado'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;

        }

        // VERIFICAR ESTADO DE LA CAJA
        $fechaActual = date('Y-m-d');
        $sqlCaja = "SELECT * FROM tbcc WHERE bcc_user = :bcc_user";
        $resCaja = $this->pedeo->queryTable($sqlCaja, array(
            ':bcc_user' => $Data['vrc_createby']
        ));

        if ( isset( $resCaja[0] ) ) {

            $sqlEstadoCaja = "SELECT * 
                            FROM tbco 
                            LEFT JOIN responsestatus  ON bco_id = responsestatus.id and bco_doctype = responsestatus.tipo
                            WHERE bco_boxid = :bco_boxid  
                            AND coalesce(bco_doctype, 0) between 0 and 48 
                            AND coalesce(estado, 'Abierto') <> 'Anulado'
                            ORDER BY bco_id DESC LIMIT 1";

            $resEstadoCaja = $this->pedeo->queryTable($sqlEstadoCaja, array(
                ':bco_boxid' => $resCaja[0]['bcc_id']
            ));

            if ( isset($resEstadoCaja[0]) ){

                $DatosCaja = $resEstadoCaja[0];

                if ( $DatosCaja['bco_status'] == 0 ){
                    
                    $respuesta = array(
                        'error' => true,
                        'data'  => array(),
                        'mensaje' => 'Debe realizar la apertura de la caja'
                    );
        
                    return $this->response($respuesta);
                }


                if ( $DatosCaja['bco_status'] == 1 && $DatosCaja['bco_date'] != $fechaActual ){

                    $respuesta = array(
                        'error' => true,
                        'data'  => array(),
                        'mensaje' => 'La fecha de apertura de la caja no es igual a la fecha actual, debe cerrar la caja y volver a realizar la apertura de la misma.'
                    );
        
                    return $this->response($respuesta);
                }


            }else{

                $respuesta = array(
                    'error' => true,
                    'data'  => array(),
                    'mensaje' => 'Debe realizar la apertura de la caja'
                );
    
                return $this->response($respuesta);
            }

        } else {

            $respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la caja del usuario actual'
			);

            return $this->response($respuesta);
        }
        //
        // FIN VALIDACION ESTADO DE CAJA

        // SE VERIFICA SI EL RECIBO ES A CREDITO
        if ( isset($Data['paytype_days']) && $Data['paytype_days'] > 0 && $FacturaComsumidor == 1 ) {

            if ( isset($resCaja[0]['bcc_series4']) && is_numeric($resCaja[0]['bcc_series4']) && $resCaja[0]['bcc_series4'] > 0 ) {

                $Data['vrc_series'] = $resCaja[0]['bcc_series4'];

            } else {

                $respuesta = array(
                    'error' => true,
                    'data'  => array(),
                    'mensaje' => 'No se encontro la numeración para los recibos a crédito'
                );
    
                return $this->response($respuesta);
            }

        }
        // FIN DE VALIDACION

        // BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['vrc_series'],$Data['vrc_docdate'],$Data['vrc_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0) {

		} else if ($DocNumVerificado['error']) {

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}

        //VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['vrc_duedev'], $Data['vrc_docdate'], $Data['vrc_duedate'], 1);

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
		$dataTasa = $this->tasa->Tasa($Data['vrc_currency'],$Data['vrc_docdate']);

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

        // SE VALIDA EL MEDIO DE PAGO
        $sqlMedioP = "";
        $resMedioP = [];

        if ( isset($Data['vrc_paymentmethod']) && is_numeric($Data['vrc_paymentmethod'] ) ){

            $sqlMedioP = "SELECT * FROM tmdp WHERE mdp_id = :mdp_id";
            $resMedioP = $this->pedeo->queryTable($sqlMedioP, array(":mdp_id" => $Data['vrc_paymentmethod']));
        }



        if (isset($Data['paytype_days']) && $Data['paytype_days'] > 0 ){

            $montoCajaLocal = 0;
            $montoMultiple  = 0;

        }else{

            if (isset($resMedioP[0])) {
            
                $montoCajaLocal = $resMedioP[0]['mdp_local'];
                $montoMultiple  = $resMedioP[0]['mdp_multiple'];
    
            } else {
    
                $respuesta = array(
                    'error'   => true,
                    'data'    => [],
                    'mensaje' => 'No se encontro el medio de pago'
                );
    
                $this->response($respuesta);
    
                return;
            }

        }
        //
        
      


        $contenidoMultiple = [];

        if ( $montoMultiple == 1 ) {

            if (isset($Data['medios'])) {

                $contenidoMultiple = json_decode($Data['medios']);
            }


            if (!is_array($contenidoMultiple) || !isset($contenidoMultiple[0])) {

                $respuesta = array(
                    'error'   => true,
                    'data'    => [],
                    'mensaje' => 'Faltan los medios de pago'
                );
    
                $this->response($respuesta);
    
                return;
            }
        }

       
        //

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

        $sqlInsert = "INSERT INTO dvrc (vrc_docnum, vrc_docdate, vrc_duedate, vrc_duedev, vrc_pricelist, vrc_cardcode, vrc_cardname, vrc_contacid, vrc_slpcode, vrc_empid, vrc_comment, vrc_doctotal, vrc_baseamnt,
                     vrc_taxtotal, vrc_discprofit, vrc_discount, vrc_createat, vrc_baseentry, vrc_basetype, vrc_doctype, vrc_idadd, vrc_adress, vrc_paytype, vrc_attch, vrc_series, vrc_createby, vrc_currency,
                     vrc_origen, vrc_canceled, business, branch, vrc_internal_comments, vrc_monto_dv, vrc_monto_v, vrc_monto_a, vrc_pasanaku, vrc_total_d, vrc_total_c, vrc_paymentmethod, vrc_cardcode2, vrc_cardname2, vrc_medios,vrc_taxtotal_ad)
                     VALUES(:vrc_docnum, :vrc_docdate, :vrc_duedate, :vrc_duedev, :vrc_pricelist, :vrc_cardcode, :vrc_cardname, :vrc_contacid, :vrc_slpcode, :vrc_empid, :vrc_comment, :vrc_doctotal, :vrc_baseamnt,
                     :vrc_taxtotal, :vrc_discprofit, :vrc_discount, :vrc_createat, :vrc_baseentry, :vrc_basetype, :vrc_doctype, :vrc_idadd, :vrc_adress, :vrc_paytype, :vrc_attch, :vrc_series, :vrc_createby, :vrc_currency,
                     :vrc_origen, :vrc_canceled, :business, :branch, :vrc_internal_comments, :vrc_monto_dv, :vrc_monto_v, :vrc_monto_a, :vrc_pasanaku, :vrc_total_d, :vrc_total_c, :vrc_paymentmethod, :vrc_cardcode2, :vrc_cardname2, :vrc_medios,:vrc_taxtotal_ad)";

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
            ':vrc_createat' => date("Y-m-d H:i:s"),  
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
            ':vrc_total_c' => is_numeric($Data['vrc_total_c']) ? $Data['vrc_total_c'] : null,
            ':vrc_paymentmethod' => is_numeric($Data['vrc_paymentmethod']) ? $Data['vrc_paymentmethod'] : 0,
            ':vrc_cardcode2' => isset($Data['vrc_cardcode2']) ? $Data['vrc_cardcode2'] : null,  
            ':vrc_cardname2' => isset($Data['vrc_cardname2']) ? $Data['vrc_cardname2'] : null,
            ':vrc_medios' => isset($Data['vrc_medios']) ? json_decode($Data['vrc_medios']) : null,
            ':vrc_taxtotal_ad' =>  isset($Data['vrc_taxtotal_ad']) && is_numeric($Data['vrc_taxtotal_ad']) ? $Data['vrc_taxtotal_ad'] : 0

            
        ));


        if (is_numeric($resInsert) && $resInsert > 0) {

        }else {

            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $resInsert,
                'mensaje' => 'NO se pudo registrar el recibo 1'
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
                'mensaje'	=> 'No se pudo actualizar la numeracion'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        // Fin de la actualizacion de la numeracion del documento

        //SE INSERTA EL ESTADO DEL DOCUMENTO

        $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
        VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

        $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array (
            ':bed_docentry' => $resInsert,
            ':bed_doctype' => $Data['vrc_doctype'],
            ':bed_status' => 1, // ESTADO ABIERTO
            ':bed_createby' => $Data['vrc_createby'],
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
            ':mac_base_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
            ':mac_base_entry' => $resInsert,
            ':mac_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
            ':mac_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
            ':mac_legal_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
            ':mac_ref1' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
            ':mac_ref2' => "",
            ':mac_ref3' => "",
            ':mac_loc_total' => is_numeric($Data['vrc_doctotal']) ? $Data['vrc_doctotal'] : 0,
            ':mac_fc_total' => is_numeric($Data['vrc_doctotal']) ? $Data['vrc_doctotal'] : 0,
            ':mac_sys_total' => is_numeric($Data['vrc_doctotal']) ? $Data['vrc_doctotal'] : 0,
            ':mac_trans_dode' => 1,
            ':mac_beline_nume' => 1,
            ':mac_vat_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
            ':mac_serie' => 1,
            ':mac_number' => 1,
            ':mac_bammntsys' => is_numeric($Data['vrc_baseamnt']) ? $Data['vrc_baseamnt'] : 0,
            ':mac_bammnt' => is_numeric($Data['vrc_baseamnt']) ? $Data['vrc_baseamnt'] : 0,
            ':mac_wtsum' => 1,
            ':mac_vatsum' => is_numeric($Data['vrc_taxtotal']) ? $Data['vrc_taxtotal'] : 0,
            ':mac_comments' => isset($Data['vrc_comment']) ? $Data['vrc_comment'] : NULL,
            ':mac_create_date' => $this->validateDate($Data['vrc_createat']) ? $Data['vrc_createat'] : NULL,
            ':mac_made_usuer' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
            ':mac_update_date' => date("Y-m-d"),
            ':mac_update_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
            ':mac_accperiod' => $periodo['data'],
            ':business'	  => $Data['business'],
            ':branch' 	  => $Data['branch']
        ));


        if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
          
        } else {

            // si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
            // se retorna el error y se detiene la ejecucion del codigo restante.
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'	  => $resInsertAsiento,
                'mensaje'	=> 'No se pudo registrar el recibo 2'
            );

            $this->response($respuesta);

            return;
        }
        //


        foreach ($ContenidoDetalle as $key => $detail) {

            $sqlInsertDetail = "INSERT INTO vrc1(rc1_docentry, rc1_itemcode, rc1_itemname, rc1_quantity, rc1_uom, rc1_whscode, rc1_price, rc1_vat, rc1_vatsum, rc1_discount, rc1_linetotal, rc1_costcode, rc1_ubusiness,
                                rc1_project, rc1_acctcode, rc1_basetype, rc1_doctype, rc1_avprice, rc1_inventory, rc1_linenum, rc1_acciva, rc1_codimp, business, branch, rc1_ubication, rc1_lote, rc1_baseline,
                                ote_code, rc1_whscode_dest, rc1_ubication2, detalle_modular, rc1_baseentry, rc1_tax_base, rc1_itemdev, rc1_clean_quantity,
                                rc1_vat_ad,rc1_vatsum_ad,rc1_accimp_ad,rc1_codimp_ad)
                                VALUES(:rc1_docentry, :rc1_itemcode, :rc1_itemname, :rc1_quantity, :rc1_uom, :rc1_whscode, :rc1_price, :rc1_vat, :rc1_vatsum, :rc1_discount, :rc1_linetotal, :rc1_costcode, :rc1_ubusiness,
                                :rc1_project, :rc1_acctcode, :rc1_basetype, :rc1_doctype, :rc1_avprice, :rc1_inventory, :rc1_linenum, :rc1_acciva, :rc1_codimp, :business, :branch, :rc1_ubication, :rc1_lote, :rc1_baseline,
                                :ote_code, :rc1_whscode_dest, :rc1_ubication2, :detalle_modular, :rc1_baseentry, :rc1_tax_base, :rc1_itemdev,:rc1_clean_quantity,
                                :rc1_vat_ad,:rc1_vatsum_ad,:rc1_accimp_ad,:rc1_codimp_ad)";


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
                ':rc1_tax_base' => is_numeric($detail['rc1_tax_base']) ? $detail['rc1_tax_base'] : null,
                ':rc1_itemdev' => is_numeric($detail['rc1_itemdev']) ? $detail['rc1_itemdev'] : 0,
                ':rc1_clean_quantity' => isset($detail['rc1_clean_quantity']) && is_numeric($detail['rc1_clean_quantity']) ? $detail['rc1_clean_quantity'] : null, 

                ':rc1_vat_ad'    => isset($detail['rc1_vat_ad']) && is_numeric($detail['rc1_vat_ad']) ? $detail['rc1_vat_ad'] : null, 
                ':rc1_vatsum_ad' => isset($detail['rc1_vatsum_ad']) && is_numeric($detail['rc1_vatsum_ad']) ? $detail['rc1_vatsum_ad'] : null, 
                ':rc1_accimp_ad' => isset($detail['rc1_accimp_ad']) && is_numeric($detail['rc1_accimp_ad']) ? $detail['rc1_accimp_ad'] : null, 
                ':rc1_codimp_ad' => isset($detail['rc1_codimp_ad']) ? $detail['rc1_codimp_ad'] : null
            ));


            if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
                // Se verifica que el detalle no de error insertando //
            } else {

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'    => $resInsertDetail,
                    'mensaje' => 'No se pudo registrar el recibo 3'
                );

                $this->response($respuesta);

                return;
            }

            // SE ACUMULA MEL TOTAL DEL IMPUESTO Y SUB TOTAL
            $totalVatSum += $detail['rc1_vatsum'];
            $totalLineTotal += $detail['rc1_linetotal'];
            //

            // SE BUSCA SI EL ARTICULO ES LISTA DE MATERIAL
            // Y SI LA LISTA DE MATERIAL ES DE TIPO VENTAS
            $esListaVentas = 0;
            $costoCompuesto = 0;
            $sqlItemLista = "SELECT dma_item_code, coalesce(rlm_id, 0) as rlm_id
                            FROM dmar
                            left join prlm on dmar.dma_item_code = prlm.rlm_item_code 
                            WHERE dma_item_mat = 1
                            AND rlm_bom_type = 3
                            and prlm.rlm_item_code  = :itemcode";

            $resItemLista = $this->pedeo->queryTable($sqlItemLista, array(":itemcode" => $detail['rc1_itemcode']));

            if ( isset($resItemLista[0]) && $resItemLista[0]['rlm_id'] > 0 ) {

                $sqlDetalleListaMat = "SELECT lm1_itemcode,
                                    lm1_itemname,
                                    lm1_quantity, 
                                    lm1_uom,
                                    lm1_whscode,
                                    costo_por_almacen(lm1_itemcode, :lm1_whscode) as costo
                                    FROM rlm1 
                                    WHERE lm1_iddoc = :lm1_iddoc
                                    AND lm1_type = :lm1_type";

                $resDetalleListaMat = $this->pedeo->queryTable($sqlDetalleListaMat, array(":lm1_iddoc" => $resItemLista[0]['rlm_id'], ":lm1_type" => '2', ":lm1_whscode" => $detail['rc1_whscode']));

                if (isset($resDetalleListaMat[0])) {

                    $esListaVentas = 1;
                    $DetalleListaMat = [];
                   
                    foreach ($resDetalleListaMat as $key => $item) {

                        $DetalleListaMat = array (

                            "rc1_itemcode"  => $item['lm1_itemcode'],
                            "rc1_quantity"  => ($item['lm1_quantity'] * $detail['rc1_quantity']),
                            "rc1_whscode"   => $detail['rc1_whscode'],
                            "rc1_ubication" => $detail['rc1_ubication'],
                            "ote_code"      => $detail['ote_code'],
                            "rc1_price"     => 0
                        );

                        $costoCompuesto = $costoCompuesto + ($item['costo'] * $DetalleListaMat['rc1_quantity']);

                        if ( $detail['rc1_itemdev'] == 1 ) {
                
                            $entryInv = $this->inventory->InventoryEntry($Data, $DetalleListaMat, $resInsert, $DocNumVerificado);
            
                            if ( isset($entryInv['error']) && $entryInv['error'] == false){
            
                            }else{
                                $this->pedeo->trans_rollback();
            
                                $respuesta = array(
                                    'error'   => true,
                                    'data'    => [],
                                    'mensaje' => $entryInv['mensaje']
                                );
                
                                return $this->response($respuesta);
                
                                
                            }
                        // EN CASO DE VENTA    
                        // SALIDA DE INVENTARIO
                        } else if ( $detail['rc1_itemdev'] == 0 ) {
            
                            $exitInv = $this->inventory->InventoryOutput($Data,  $DetalleListaMat, $resInsert, $DocNumVerificado);
            
                            if ( isset($exitInv['error']) && $exitInv['error'] == false){
            
                            }else{
                                $this->pedeo->trans_rollback();
            
                                $respuesta = array(
                                    'error'   => true,
                                    'data'    => [],
                                    'mensaje' => $exitInv['mensaje']
                                );
                
                                return $this->response($respuesta);
                            }
            
                        }
                        
                    }
         
                    if ( $costoCompuesto == 0 ) {

                        $this->pedeo->trans_rollback();

                        $respuesta = array(
                            'error'   => true,
                            'data'    => [],
                            'mensaje' => 'No se encontro el costo del articulo'. $detail['rc1_itemcode']
                        );
    
                        $this->response($respuesta);
    
                        return;
                    }


                } else {

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'    => $resInsertDetail,
                        'mensaje' => 'No se pudo registrar el recibo, no hay datos de la lista'
                    );

                    $this->response($respuesta);

                    return;

                }

            // PROCESO NORMAL
            }


            // MANEJA INVENTARIO
            $ManejaInvetario = 0;
            $sqlItemINV = "SELECT coalesce(dma_item_inv, '0') as dma_item_inv,coalesce(dma_use_tbase,0) as dma_use_tbase, coalesce(dma_tasa_base,0) as dma_tasa_base FROM dmar WHERE dma_item_code = :dma_item_code";
            $resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

                ':dma_item_code' => $detail['rc1_itemcode']
            ));

            if ( isset($resItemINV[0]) && $resItemINV[0]['dma_item_inv'] == '1' ) {
                $ManejaInvetario = 1;

                $soloServicio = false;
            }

                
            //

            // SI ES LISTA DE VENTAS NO SE MUEVE EL INVENTARIO DEL ITEM DE LA LINEA
            //
            if ( $esListaVentas == 0 ) {

                if ($ManejaInvetario == 1){
                    // OPERACIONES DE INVENTARIO 
                    // EN CASO DE DEVOLUCIÓN DE INVENTARIO
                    // ENTRADA DE INVENTARIO
                    if ( $detail['rc1_itemdev'] == 1 ) {
                        
                        $entryInv = $this->inventory->InventoryEntry($Data, $detail, $resInsert, $DocNumVerificado);

                        if ( isset($entryInv['error']) && $entryInv['error'] == false){

                        }else{
                            $this->pedeo->trans_rollback();

                            $respuesta = array(
                                'error'   => true,
                                'data'    => [],
                                'mensaje' => $entryInv['mensaje']
                            );
            
                            return $this->response($respuesta);
            
                            
                        }
                    // EN CASO DE VENTA    
                    // SALIDA DE INVENTARIO
                    } else if ( $detail['rc1_itemdev'] == 0 ) {

                        $exitInv = $this->inventory->InventoryOutput($Data, $detail, $resInsert, $DocNumVerificado);

                        if ( isset($exitInv['error']) && $exitInv['error'] == false){

                        }else{
                            $this->pedeo->trans_rollback();

                            $respuesta = array(
                                'error'   => true,
                                'data'    => [],
                                'mensaje' => $exitInv['mensaje']
                            );
            
                            return $this->response($respuesta);
                        }

                    }
                }
            }
            //

            $ManejaLote = 0; // SI EL ARTICULO MANEJA LOTE

            // SI EL ARTICULO MANEJA LOTE
            $sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
            $resLote = $this->pedeo->queryTable($sqlLote, array(
                ':dma_item_code'   => $detail['rc1_itemcode'],
                ':dma_lotes_code'  => 1
            ));
    
            if (isset($resLote[0])) {
                $ManejaLote = 1;
            } else {
                $ManejaLote = 0;
            }

            if ($detail['rc1_itemdev'] == 0) {

                $DetalleCostoInventario = new stdClass();
                $DetalleCostoCosto = new stdClass();
                $DetalleIngresoVenta = new stdClass();
                $DetalleIvaVenta = new stdClass();
                $DetalleAsientoImpMultiVenta = new stdClass();
                
                if ($ManejaInvetario == 1){

                    $DetalleCostoInventario->ccosto   = isset($detail['rc1_costcode']) ? $detail['rc1_costcode'] : NULL;
                    $DetalleCostoInventario->itemcode = isset($detail['rc1_itemcode']) ? $detail['rc1_itemcode'] : NULL;
                    $DetalleCostoInventario->lote     = isset($detail['ote_code']) ? $detail['ote_code'] : 0;
                    $DetalleCostoInventario->malote   = $ManejaLote;
                    $DetalleCostoInventario->whscode  = isset($detail['rc1_whscode']) ? $detail['rc1_whscode'] : NULL;
                    $DetalleCostoInventario->cantidad  = isset($detail['rc1_quantity']) ? $detail['rc1_quantity'] : NULL;
                    $DetalleCostoInventario->cuentaiva = isset($detail['rc1_acciva']) && is_numeric($detail['rc1_acciva']) ? $detail['rc1_acciva'] : NULL;
                    $DetalleCostoInventario->costoart = $costoCompuesto; 
                    $DetalleCostoInventario->tipolista = $esListaVentas;
                    
                    $DetalleCostoCosto->ccosto   = isset($detail['rc1_costcode']) ? $detail['rc1_costcode'] : NULL;
                    $DetalleCostoCosto->itemcode = isset($detail['rc1_itemcode']) ? $detail['rc1_itemcode'] : NULL;
                    $DetalleCostoCosto->lote     = isset($detail['ote_code']) ? $detail['ote_code'] : 0;
                    $DetalleCostoCosto->malote   = $ManejaLote;
                    $DetalleCostoCosto->whscode  = isset($detail['rc1_whscode']) ? $detail['rc1_whscode'] : NULL;
                    $DetalleCostoCosto->cantidad  = isset($detail['rc1_quantity']) ? $detail['rc1_quantity'] : NULL;
                    $DetalleCostoCosto->cuentaiva = isset($detail['rc1_acciva']) && is_numeric($detail['rc1_acciva']) ? $detail['rc1_acciva'] : NULL; 
                    $DetalleCostoCosto->costoart = $costoCompuesto; 
                    $DetalleCostoCosto->tipolista = $esListaVentas;
                }
    
            
                
            

                $DetalleIngresoVenta->itemcode = isset($detail['rc1_itemcode']) ? $detail['rc1_itemcode'] : NULL;
                $DetalleIngresoVenta->whscode  = isset($detail['rc1_whscode']) ? $detail['rc1_whscode'] : NULL;
                $DetalleIngresoVenta->cantidad = isset($detail['rc1_quantity']) ? $detail['rc1_quantity'] : NULL;
                $DetalleIngresoVenta->precio   = isset($detail['rc1_price']) ? $detail['rc1_price'] : NULL;
                $DetalleIngresoVenta->impuesto = isset($detail['rc1_vatsum']) ? $detail['rc1_vatsum'] : NULL;
                $DetalleIngresoVenta->cuentaiva = isset($detail['rc1_acciva']) && is_numeric($detail['rc1_acciva']) ? $detail['rc1_acciva'] : NULL; 

            
                $DetalleIvaVenta->impuesto = isset($detail['rc1_vatsum']) ? $detail['rc1_vatsum'] : NULL;
                $DetalleIvaVenta->cuentaiva = isset($detail['rc1_acciva']) && is_numeric($detail['rc1_acciva']) ? $detail['rc1_acciva'] : NULL;
                
                
                // IMPUESTO MULTIPLE
                if ( is_numeric($detail['rc1_vatsum_ad']) && $detail['rc1_vatsum_ad'] > 0 ) {

                    $DetalleAsientoImpMultiVenta->impuesto = isset($detail['rc1_vatsum_ad']) ? $detail['rc1_vatsum_ad'] : NULL;
                    $DetalleAsientoImpMultiVenta->cuentaiva = isset($detail['rc1_accimp_ad']) && is_numeric($detail['rc1_accimp_ad']) ? $detail['rc1_accimp_ad'] : NULL;
                    

                    $llaveImpMultiVenta = $DetalleAsientoImpMultiVenta->cuentaiva;

                    if (in_array($llaveImpMultiVenta, $inArrayImpMultiVenta)) {
    
                        $posicionImpMultiVenta = $this->buscarPosicion($llaveImpMultiVenta, $inArrayImpMultiVenta);
                    } else {
        
                        array_push($inArrayImpMultiVenta, $llaveImpMultiVenta);
                        $posicionImpMultiVenta = $this->buscarPosicion($llaveIvaVenta, $inArrayImpMultiVenta);
                    }


                    if (isset($DetalleConsolidadoImpMultiVenta[$posicionImpMultiVenta])) {
    
                        if (!is_array($DetalleConsolidadoImpMultiVenta[$posicionImpMultiVenta])) {
                            $DetalleConsolidadoImpMultiVenta[$posicionImpMultiVenta] = array();
                        }
                    } else {
                        $DetalleConsolidadoImpMultiVenta[$posicionImpMultiVenta] = array();
                    }
        
                    array_push($DetalleConsolidadoImpMultiVenta[$posicionImpMultiVenta], $DetalleAsientoImpMultiVenta);
                }
            

                $CUENTASINV = $this->account->getAccountItem($DetalleIngresoVenta->itemcode, $DetalleIngresoVenta->whscode);
            

                if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

                    $DetalleIngresoVenta->cuenta = $CUENTASINV['data']['acct_in'];;

                }else{
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'    => $CUENTASINV,
                        'mensaje' => 'No se encontro la cuenta contable para el item '.$DetalleIngresoVenta->itemcode
                    );

                    return $this->response($respuesta);
                }
                

                
                $llaveIngresoVenta = $DetalleIngresoVenta->cuenta;
                $llaveIvaVenta =  $DetalleIvaVenta->cuentaiva;

                if ($ManejaInvetario == 1){

                    $llaveCostoInventario = $DetalleCostoInventario->ccosto;
                    $llaveCostoCosto = $DetalleCostoCosto->ccosto;


                    // INVENTARIO VENTA

                    if (in_array($llaveCostoInventario, $inArrayCostoInventario)) {
        
                        $posicionCostoInventario = $this->buscarPosicion($llaveCostoInventario, $inArrayCostoInventario);
                    } else {
        
                        array_push($inArrayCostoInventario, $llaveCostoInventario);
                        $posicionCostoInventario = $this->buscarPosicion($llaveCostoInventario, $inArrayCostoInventario);
                    }
        
                    // COSTO VENTA
        
        
                    if (in_array($llaveCostoCosto, $inArrayCostoCosto)) {
        
                        $posicionCostoCosto = $this->buscarPosicion($llaveCostoCosto, $inArrayCostoCosto);
                    } else {
        
                        array_push($inArrayCostoCosto, $llaveCostoCosto);
                        $posicionCostoCosto = $this->buscarPosicion($llaveCostoCosto, $inArrayCostoCosto);
                    }

                }
                

                
                
              
                // INGRESO VENTA

                if (in_array($llaveIngresoVenta, $inArrayIngresoVenta)) {
    
                    $posicionIngresoVenta = $this->buscarPosicion($llaveIngresoVenta, $inArrayIngresoVenta);
                } else {
    
                    array_push($inArrayIngresoVenta, $llaveIngresoVenta);
                    $posicionIngresoVenta = $this->buscarPosicion($llaveIngresoVenta, $inArrayIngresoVenta);
                }

                 
                // IVA VENTA

                if (in_array($llaveIvaVenta, $inArrayIvaVenta)) {
    
                    $posicionIvaVenta = $this->buscarPosicion($llaveIvaVenta, $inArrayIvaVenta);
                } else {
    
                    array_push($inArrayIvaVenta, $llaveIvaVenta);
                    $posicionIvaVenta = $this->buscarPosicion($llaveIvaVenta, $inArrayIvaVenta);
                }


                //******************************SEGUNDA PARTE */
    
                if ($ManejaInvetario == 1){

                    // INVENTARIO VENTA
                    if (isset($DetalleConsolidadoCostoInventario[$posicionCostoInventario])) {
        
                        if (!is_array($DetalleConsolidadoCostoInventario[$posicionCostoInventario])) {
                            $DetalleConsolidadoCostoInventario[$posicionCostoInventario] = array();
                        }
                    } else {
                        $DetalleConsolidadoCostoInventario[$posicionCostoInventario] = array();
                    }
        
                    array_push($DetalleConsolidadoCostoInventario[$posicionCostoInventario], $DetalleCostoInventario);
        
        
        
                    // COSTO VENTA
                    if (isset($DetalleConsolidadoCostoCosto[$posicionCostoCosto])) {
        
                        if (!is_array($DetalleConsolidadoCostoCosto[$posicionCostoCosto])) {
                            $DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
                        }
                    } else {
                        $DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
                    }
        
                    array_push($DetalleConsolidadoCostoCosto[$posicionCostoCosto], $DetalleCostoCosto);

                }
                
                // INGRESO VENTA
                if (isset($DetalleConsolidadoIngresoVenta[$posicionIngresoVenta])) {
    
                    if (!is_array($DetalleConsolidadoIngresoVenta[$posicionIngresoVenta])) {
                        $DetalleConsolidadoIngresoVenta[$posicionIngresoVenta] = array();
                    }
                } else {
                    $DetalleConsolidadoIngresoVenta[$posicionIngresoVenta] = array();
                }
    
                array_push($DetalleConsolidadoIngresoVenta[$posicionIngresoVenta], $DetalleIngresoVenta);


                // IVA VENTA
                if (isset($DetalleConsolidadoIvaVenta[$posicionIvaVenta])) {
    
                    if (!is_array($DetalleConsolidadoIvaVenta[$posicionIvaVenta])) {
                        $DetalleConsolidadoIvaVenta[$posicionIvaVenta] = array();
                    }
                } else {
                    $DetalleConsolidadoIvaVenta[$posicionIvaVenta] = array();
                }
    
                array_push($DetalleConsolidadoIvaVenta[$posicionIvaVenta], $DetalleIvaVenta);

            } else if ($detail['rc1_itemdev'] == 1) {

                $DetalleIngresoDevolucion = new stdClass();
                $DetalleCostoCostoDev = new stdClass();
                $DetalleCostoInventarioDev = new stdClass();
                $DetalleIvaDev = new stdClass();
                $DetalleAsientoImpMultiDev = new stdClass();
        

                $DetalleIngresoDevolucion->itemcode = isset($detail['rc1_itemcode']) ? $detail['rc1_itemcode'] : NULL;
                $DetalleIngresoDevolucion->whscode  = isset($detail['rc1_whscode']) ? $detail['rc1_whscode'] : NULL;
                $DetalleIngresoDevolucion->cantidad = isset($detail['rc1_quantity']) ? $detail['rc1_quantity'] : NULL;
                $DetalleIngresoDevolucion->precio = isset($detail['rc1_price']) ? $detail['rc1_price'] : NULL;
                $DetalleIngresoDevolucion->impuesto = isset($detail['rc1_vatsum']) ? $detail['rc1_vatsum'] : NULL;
                $DetalleIngresoDevolucion->cuentaiva = isset($detail['rc1_acciva']) && is_numeric($detail['rc1_acciva']) ? $detail['rc1_acciva'] : NULL; 
                

                $DetalleIvaDev->impuesto = isset($detail['rc1_vatsum']) ? $detail['rc1_vatsum'] : NULL;
                $DetalleIvaDev->cuentaiva = isset($detail['rc1_acciva']) && is_numeric($detail['rc1_acciva']) ? $detail['rc1_acciva'] : NULL; 
                

                $CUENTASINV = $this->account->getAccountItem($DetalleIngresoDevolucion->itemcode, $DetalleIngresoDevolucion->whscode);
            

                if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

                    $DetalleIngresoDevolucion->cuenta = $CUENTASINV['data']['acct_in'];;

                }else{
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'    => $CUENTASINV,
                        'mensaje' => 'No se encontro la cuenta contable para el item '.$DetalleIngresoDevolucion->itemcode
                    );

                    return $this->response($respuesta);
                }

                if ($ManejaInvetario == 1) {

                    $DetalleCostoInventarioDev->ccosto   = isset($detail['rc1_costcode']) ? $detail['rc1_costcode'] : NULL;
                    $DetalleCostoInventarioDev->itemcode = isset($detail['rc1_itemcode']) ? $detail['rc1_itemcode'] : NULL;
                    $DetalleCostoInventarioDev->lote     = isset($detail['ote_code']) ? $detail['ote_code'] : 0;
                    $DetalleCostoInventarioDev->malote   = $ManejaLote;
                    $DetalleCostoInventarioDev->whscode  = isset($detail['rc1_whscode']) ? $detail['rc1_whscode'] : NULL;
                    $DetalleCostoInventarioDev->cantidad  = isset($detail['rc1_quantity']) ? $detail['rc1_quantity'] : NULL;
                    $DetalleCostoInventarioDev->cuentaiva = isset($detail['rc1_acciva']) && is_numeric($detail['rc1_acciva']) ? $detail['rc1_acciva'] : NULL; 
                    $DetalleCostoInventarioDev->costoart = $costoCompuesto; 
                    $DetalleCostoInventarioDev->tipolista = $esListaVentas;
                    
                    $DetalleCostoCostoDev->ccosto   = isset($detail['rc1_costcode']) ? $detail['rc1_costcode'] : NULL;
                    $DetalleCostoCostoDev->itemcode = isset($detail['rc1_itemcode']) ? $detail['rc1_itemcode'] : NULL;
                    $DetalleCostoCostoDev->lote     = isset($detail['ote_code']) ? $detail['ote_code'] : 0;
                    $DetalleCostoCostoDev->malote   = $ManejaLote;
                    $DetalleCostoCostoDev->whscode  = isset($detail['rc1_whscode']) ? $detail['rc1_whscode'] : NULL;
                    $DetalleCostoCostoDev->cantidad  = isset($detail['rc1_quantity']) ? $detail['rc1_quantity'] : NULL;
                    $DetalleCostoCostoDev->cuentaiva = isset($detail['rc1_acciva']) && is_numeric($detail['rc1_acciva']) ? $detail['rc1_acciva'] : NULL; 
                    $DetalleCostoCostoDev->costoart = $costoCompuesto; 
                    $DetalleCostoCostoDev->tipolista = $esListaVentas;
    
                }


                // IMPUESTO MULTIPLE
                if ( is_numeric($detail['rc1_vatsum_ad']) && $detail['rc1_vatsum_ad'] > 0 ) {

                    $DetalleAsientoImpMultiDev->impuesto = isset($detail['rc1_vatsum_ad']) ? $detail['rc1_vatsum_ad'] : NULL;
                    $DetalleAsientoImpMultiDev->cuentaiva = isset($detail['rc1_accimp_ad']) && is_numeric($detail['rc1_accimp_ad']) ? $detail['rc1_accimp_ad'] : NULL;
                    

                    $llaveImpMultiDev = $DetalleAsientoImpMultiDev->cuentaiva;

                    if (in_array($llaveImpMultiDev, $inArrayImpMultiDev)) {
    
                        $posicionImpMultiDev = $this->buscarPosicion($llaveImpMultiDev, $inArrayImpMultiDev);
                    } else {
        
                        array_push($inArrayImpMultiDev, $llaveImpMultiDev);
                        $posicionImpMultiDev = $this->buscarPosicion($llaveIvaDev, $inArrayImpMultiDev);
                    }


                    if (isset($DetalleConsolidadoImpMultiDev[$posicionImpMultiDev])) {
    
                        if (!is_array($DetalleConsolidadoImpMultiDev[$posicionImpMultiDev])) {
                            $DetalleConsolidadoImpMultiDev[$posicionImpMultiDev] = array();
                        }
                    } else {
                        $DetalleConsolidadoImpMultiDev[$posicionImpMultiDev] = array();
                    }
        
                    array_push($DetalleConsolidadoImpMultiDev[$posicionImpMultiDev], $DetalleAsientoImpMultiDev);
                }
                
                if ($ManejaInvetario == 1){

                    $llaveCostoInventarioDev = $DetalleCostoInventarioDev->ccosto;
                    $llaveCostoCostoDev = $DetalleCostoCostoDev->ccosto;


                    // INVENTARIO DEVOLUCION

                    if (in_array($llaveCostoInventarioDev, $inArrayCostoInventarioDev)) {

                        $posicionCostoInventarioDev = $this->buscarPosicion($llaveCostoInventarioDev, $inArrayCostoInventarioDev);
                    } else {
        
                        array_push($inArrayCostoInventarioDev, $llaveCostoInventarioDev);
                        $posicionCostoInventarioDev = $this->buscarPosicion($llaveCostoInventarioDev, $inArrayCostoInventarioDev);
                    }


                    // COSTO DEVOLUCION
                    if (in_array($llaveCostoCostoDev, $inArrayCostoCostoDev)) {
        
                        $posicionCostoCostoDev = $this->buscarPosicion($llaveCostoCostoDev, $inArrayCostoCostoDev);
                    } else {
        
                        array_push($inArrayCostoCostoDev, $llaveCostoCostoDev);
                        $posicionCostoCostoDev = $this->buscarPosicion($llaveCostoCostoDev, $inArrayCostoCostoDev);
                    }

                }


                $llaveIngresoDevolucion = $DetalleIngresoDevolucion->cuenta;
                $llaveIvaDev = $DetalleIvaDev->cuentaiva;


   

                //INGRESO DEVOLUCION

                if (in_array($llaveIngresoDevolucion, $inArrayIngresoDevolucion)) {

                    $posicionIngresoDevolucion = $this->buscarPosicion($llaveIngresoDevolucion, $inArrayIngresoDevolucion);
                } else {
    
                    array_push($inArrayIngresoDevolucion, $llaveIngresoDevolucion);
                    $posicionIngresoDevolucion = $this->buscarPosicion($llaveIngresoDevolucion, $inArrayIngresoDevolucion);
                }


                //IVA DEVOLUCION

                if (in_array($llaveIvaDev, $inArrayIvaDev)) {

                    $posicionIvaDev = $this->buscarPosicion($llaveIvaDev, $inArrayIvaDev);
                } else {
    
                    array_push($inArrayIvaDev, $llaveIvaDev);
                    $posicionIvaDev = $this->buscarPosicion($llaveIvaDev, $inArrayIvaDev);
                }


                //**************************SEGUNDA PARTE */

                if ($ManejaInvetario == 1){
                    // INVENTARIO DEVOLUCION
                    if (isset($DetalleConsolidadoCostoInventarioDev[$posicionCostoInventarioDev])) {
                        if (!is_array($DetalleConsolidadoCostoInventarioDev[$posicionCostoInventarioDev])) {
                            $DetalleConsolidadoCostoInventarioDev[$posicionCostoInventarioDev] = array();
                        }
                    } else {
                        $DetalleConsolidadoCostoInventarioDev[$posicionCostoInventarioDev] = array();
                    }

                    array_push($DetalleConsolidadoCostoInventarioDev[$posicionCostoInventarioDev], $DetalleCostoInventarioDev);



                    // COSTO DEVOLUCION
                    if (isset($DetalleConsolidadoCostoCostoDev[$posicionCostoCostoDev])) {

                        if (!is_array($DetalleConsolidadoCostoCostoDev[$posicionCostoCostoDev])) {
                            $DetalleConsolidadoCostoCostoDev[$posicionCostoCostoDev] = array();
                        }
                    } else {
                        $DetalleConsolidadoCostoCostoDev[$posicionCostoCostoDev] = array();
                    }

                    array_push($DetalleConsolidadoCostoCostoDev[$posicionCostoCostoDev], $DetalleCostoCostoDev);

                }

               
                // INGRESO DEVOLUCION
                if (isset($DetalleConsolidadoIngresoDevolucion[$posicionIngresoDevolucion])) {

                    if (!is_array($DetalleConsolidadoIngresoDevolucion[$posicionIngresoDevolucion])) {
                        $DetalleConsolidadoIngresoDevolucion[$posicionIngresoDevolucion] = array();
                    }
                } else {
                    $DetalleConsolidadoIngresoDevolucion[$posicionIngresoDevolucion] = array();
                }
    
                array_push($DetalleConsolidadoIngresoDevolucion[$posicionIngresoDevolucion], $DetalleIngresoDevolucion);


                // IVA DEVOLUCION
                if (isset($DetalleConsolidadoIvaDev[$posicionIvaDev])) {

                    if (!is_array($DetalleConsolidadoIvaDev[$posicionIvaDev])) {
                        $DetalleConsolidadoIvaDev[$posicionIvaDev] = array();
                    }
                } else {
                    $DetalleConsolidadoIvaDev[$posicionIvaDev] = array();
                }
    
                array_push($DetalleConsolidadoIvaDev[$posicionIvaDev], $DetalleIvaDev);
                    
            }
            
            //
        }

        // SE VALIDA SE ES NECESARIO HACER UNA COPIA DE FACTURA DE VENTAS
        if ( $PARA_MTRS['factura_de_pos'] == 1) {


            // SOLO SI EL RECIBO POS NO VIENE DE UN CONSUMIDOR
            if ( $FacturaComsumidor == 0 ) {

                $sqlInsertFv = "INSERT INTO dvfv(dvf_series, dvf_docnum, dvf_docdate, dvf_duedate, dvf_duedev, dvf_pricelist, dvf_cardcode,
                dvf_cardname, dvf_currency, dvf_contacid, dvf_slpcode, dvf_empid, dvf_comment, dvf_doctotal, dvf_baseamnt, dvf_taxtotal,
                dvf_discprofit, dvf_discount, dvf_createat, dvf_baseentry, dvf_basetype, dvf_doctype, dvf_idadd, dvf_adress, dvf_paytype,
                dvf_createby, dvf_correl,dvf_transport,dvf_sub_transport,dvf_ci,dvf_t_vehiculo,dvf_guia,dvf_placa,dvf_precinto,dvf_placav,
                dvf_modelv,dvf_driverv,dvf_driverid,dvf_igtf,dvf_taxigtf,dvf_igtfapplyed,dvf_igtfcode,business,branch,dvf_totalret,dvf_totalretiva,
                dvf_bankable,dvf_internal_comments,dvf_taxtotal_ad, dvf_paytoday)
                VALUES(:dvf_series, :dvf_docnum, :dvf_docdate, :dvf_duedate, :dvf_duedev, :dvf_pricelist, :dvf_cardcode, :dvf_cardname,
                :dvf_currency, :dvf_contacid, :dvf_slpcode, :dvf_empid, :dvf_comment, :dvf_doctotal, :dvf_baseamnt, :dvf_taxtotal, :dvf_discprofit, :dvf_discount,
                :dvf_createat, :dvf_baseentry, :dvf_basetype, :dvf_doctype, :dvf_idadd, :dvf_adress, :dvf_paytype, :dvf_createby,:dvf_correl,:dvf_transport,:dvf_sub_transport,:dvf_ci,:dvf_t_vehiculo,
                :dvf_guia,:dvf_placa,:dvf_precinto,:dvf_placav,:dvf_modelv,:dvf_driverv,:dvf_driverid,:dvf_igtf,:dvf_taxigtf,:dvf_igtfapplyed,
                :dvf_igtfcode,:business,:branch,:dvf_totalret,:dvf_totalretiva,:dvf_bankable,:dvf_internal_comments,:dvf_taxtotal_ad, :dvf_paytoday)";

                $resInsertFv = $this->pedeo->insertRow($sqlInsertFv, array(
                    ':dvf_docnum' => $DocNumVerificado,
                    ':dvf_series' => is_numeric($Data['vrc_series']) ? $Data['vrc_series'] : 0,
                    ':dvf_docdate' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                    ':dvf_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                    ':dvf_duedev' => $this->validateDate($Data['vrc_duedev']) ? $Data['vrc_duedev'] : NULL,
                    ':dvf_pricelist' => is_numeric($Data['vrc_pricelist']) ? $Data['vrc_pricelist'] : 0,
                    ':dvf_cardcode' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
                    ':dvf_cardname' => isset($Data['vrc_cardname']) ? $Data['vrc_cardname'] : NULL,
                    ':dvf_currency' => isset($Data['vrc_currency']) ? $Data['vrc_currency'] : NULL,
                    ':dvf_contacid' => isset($Data['vrc_contacid']) ? $Data['vrc_contacid'] : NULL,
                    ':dvf_slpcode' => is_numeric($Data['vrc_slpcode']) ? $Data['vrc_slpcode'] : 0,
                    ':dvf_empid' => is_numeric($Data['vrc_empid']) ? $Data['vrc_empid'] : 0,
                    ':dvf_comment' => isset($Data['vrc_comment']) ? $Data['vrc_comment'] : NULL,

                    ':dvf_doctotal'   => is_numeric($Data['vrc_total_c']) ? $Data['vrc_total_c'] : 0,
                    ':dvf_baseamnt'   => ($totalLineTotal - $totalVatSum),
                    ':dvf_taxtotal'   => $totalVatSum,
                    ':dvf_discprofit' => 0,
                    ':dvf_discount'   => 0,
                    ':dvf_paytoday'   => is_numeric($Data['vrc_total_c']) ? $Data['vrc_total_c'] : 0,

                    ':dvf_createat' => $this->validateDate($Data['vrc_createat']) ? $Data['vrc_createat'] : NULL,
                    ':dvf_baseentry' => $resInsert,
                    ':dvf_basetype' => 47,
                    ':dvf_doctype' => 5,
                    ':dvf_idadd' => isset($Data['vrc_idadd']) ? $Data['vrc_idadd'] : NULL,
                    ':dvf_adress' => isset($Data['vrc_adress']) ? $Data['vrc_adress'] : NULL,
                    ':dvf_paytype' => is_numeric($Data['vrc_paytype']) ? $Data['vrc_paytype'] : 0,
                    ':dvf_createby' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
                    ':dvf_correl' => isset($Data['vrc_correl']) ? $Data['vrc_correl'] : 0,
                    ':dvf_transport' => isset($Data['vrc_transport']) ? $Data['vrc_transport'] : NULL,
                    ':dvf_sub_transport' => isset($Data['vrc_sub_transport']) ? $Data['vrc_sub_transport'] : NULL,
                    ':dvf_ci' => isset($Data['vrc_ci']) ? $Data['vrc_ci'] : NULL,
                    ':dvf_t_vehiculo' => isset($Data['vrc_t_vehiculo']) ? $Data['vrc_t_vehiculo'] : NULL,
                    ':dvf_guia' => isset($Data['vrc_guia']) ? $Data['vrc_guia'] : NULL,
                    ':dvf_placa' => isset($Data['vrc_placa']) ? $Data['vrc_placa'] : NULL,
                    ':dvf_precinto' => isset($Data['vrc_precinto']) ? $Data['vrc_precinto'] : NULL,
                    ':dvf_placav' => isset($Data['vrc_placav']) ? $Data['vrc_placav'] : NULL,
                    ':dvf_modelv' => isset($Data['vrc_modelv']) ? $Data['vrc_modelv'] : NULL,
                    ':dvf_driverv' => isset($Data['vrc_driverv']) ? $Data['vrc_driverv'] : NULL,
                    ':dvf_driverid'  => isset($Data['vrc_driverid']) ? $Data['vrc_driverid'] : NULL,
                    ':dvf_igtf'  =>  isset($Data['vrc_igtf']) ? $Data['vrc_igtf'] : NULL,
                    ':dvf_taxigtf' => isset($Data['vrc_taxigtf']) ? $Data['vrc_taxigtf'] : NULL,
                    ':dvf_igtfapplyed' => isset($Data['vrc_igtfapplyed']) ? $Data['vrc_igtfapplyed'] : NULL,
                    ':dvf_igtfcode' => isset($Data['vrc_igtfcode']) ? $Data['vrc_igtfcode'] : NULL,
                    ':business' => isset($Data['business']) ? $Data['business'] : NULL,
                    ':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
                    ':dvf_totalret' => isset($Data['vrc_totalret']) && is_numeric($Data['vrc_totalret']) ? $Data['vrc_totalret'] : 0,
                    ':dvf_totalretiva' => isset($Data['vrc_totalretiva']) && is_numeric($Data['vrc_totalretiva']) ? $Data['vrc_totalretiva'] : 0,
                    ':dvf_bankable' => 0,
                    ':dvf_internal_comments'  => isset($Data['vrc_internal_comments']) ? $Data['vrc_internal_comments'] : NULL,
                    ':dvf_taxtotal_ad' => is_numeric($Data['vrc_taxtotal_ad']) ? $Data['vrc_taxtotal_ad'] : 0
                ));

                if ( is_numeric($resInsertFv) && $resInsertFv > 0 ) {

                    foreach ($ContenidoDetalle as $key => $detail) {

                        $sqlInsertDetailFv = "INSERT INTO vfv1(fv1_docentry, fv1_itemcode, fv1_itemname, fv1_quantity, fv1_uom, fv1_whscode,
                                        fv1_price, fv1_vat, fv1_vatsum, fv1_discount, fv1_linetotal, fv1_costcode, fv1_ubusiness, fv1_project,
                                        fv1_acctcode, fv1_basetype, fv1_doctype, fv1_avprice, fv1_inventory, fv1_acciva, fv1_fixrate, fv1_codimp,fv1_ubication,
                                        fv1_linenum,fv1_baseline,ote_code,fv1_gift,detalle_modular,fv1_tax_base,detalle_anuncio,imponible,fv1_clean_quantity,
                                        fv1_vat_ad,fv1_vatsum_ad,fv1_accimp_ad,fv1_codimp_ad)VALUES(:fv1_docentry, :fv1_itemcode, :fv1_itemname, :fv1_quantity,:fv1_uom, :fv1_whscode,:fv1_price, :fv1_vat, 
                                        :fv1_vatsum, :fv1_discount, :fv1_linetotal, :fv1_costcode, :fv1_ubusiness, :fv1_project,:fv1_acctcode, :fv1_basetype, 
                                        :fv1_doctype, :fv1_avprice, :fv1_inventory, :fv1_acciva, :fv1_fixrate, :fv1_codimp,:fv1_ubication,:fv1_linenum,
                                        :fv1_baseline,:ote_code,:fv1_gift,:detalle_modular,:fv1_tax_base,:detalle_anuncio,:imponible,:fv1_clean_quantity,
                                        :fv1_vat_ad,:fv1_vatsum_ad,:fv1_accimp_ad,:fv1_codimp_ad)";

                        $resInsertDetailFv = $this->pedeo->insertRow($sqlInsertDetailFv, array(
                            ':fv1_docentry' => $resInsertFv,
                            ':fv1_itemcode' => isset($detail['rc1_itemcode']) ? $detail['rc1_itemcode'] : NULL,
                            ':fv1_itemname' => isset($detail['rc1_itemname']) ? $detail['rc1_itemname'] : NULL,
                            ':fv1_quantity' => is_numeric($detail['rc1_quantity']) ? $detail['rc1_quantity'] : 0,
                            ':fv1_uom' => isset($detail['rc1_uom']) ? $detail['rc1_uom'] : NULL,
                            ':fv1_whscode' => isset($detail['rc1_whscode']) ? $detail['rc1_whscode'] : NULL,
                            ':fv1_price' => is_numeric($detail['rc1_price']) ? $detail['rc1_price'] : 0,
                            ':fv1_vat' => is_numeric($detail['rc1_vat']) ? $detail['rc1_vat'] : 0,
                            ':fv1_vatsum' => is_numeric($detail['rc1_vatsum']) ? $detail['rc1_vatsum'] : 0,
                            ':fv1_discount' => is_numeric($detail['rc1_discount']) ? $detail['rc1_discount'] : 0,
                            ':fv1_linetotal' => is_numeric($detail['rc1_linetotal']) ? $detail['rc1_linetotal'] : 0,
                            ':fv1_costcode' => isset($detail['rc1_costcode']) ? $detail['rc1_costcode'] : NULL,
                            ':fv1_ubusiness' => isset($detail['rc1_ubusiness']) ? $detail['rc1_ubusiness'] : NULL,
                            ':fv1_project' => isset($detail['rc1_project']) ? $detail['rc1_project'] : NULL,
                            ':fv1_acctcode' => is_numeric($detail['rc1_acctcode']) ? $detail['rc1_acctcode'] : 0,
                            ':fv1_basetype' => is_numeric($detail['rc1_basetype']) ? $detail['rc1_basetype'] : 0,
                            ':fv1_doctype' => is_numeric($detail['rc1_doctype']) ? $detail['rc1_doctype'] : 0,
                            ':fv1_avprice' => is_numeric($detail['rc1_avprice']) ? $detail['rc1_avprice'] : 0,
                            ':fv1_inventory' => is_numeric($detail['rc1_inventory']) ? $detail['rc1_inventory'] : NULL,
                            ':fv1_acciva'  => is_numeric($detail['rc1_acciva']) ? $detail['rc1_acciva'] : 0,
                            ':fv1_fixrate' => 0,
                            ':fv1_codimp' => isset($detail['rc1_codimp']) ? $detail['rc1_codimp'] : 0,
                            ':fv1_ubication' => isset($detail['rc1_ubication']) ? $detail['rc1_ubication'] : NULL,
                            ':fv1_linenum' => isset($detail['rc1_linenum']) && is_numeric($detail['rc1_linenum']) ? $detail['rc1_linenum'] : 0,
                            ':fv1_baseline' => isset($detail['rc1_baseline']) && is_numeric($detail['rc1_baseline']) ? $detail['rc1_baseline'] : 0,
                            ':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
                            ':fv1_gift' => isset($detail['rc1_gift']) && is_numeric($detail['rc1_gift']) ? $detail['rc1_gift'] : 0,
                            ':detalle_modular' => NULL,
                            ':fv1_tax_base' =>  is_numeric($detail['rc1_tax_base']) ? $detail['rc1_tax_base'] : 0,
                            ':detalle_anuncio' => NULL,
                            ':imponible' => isset($detail['imponible']) ? $detail['imponible'] : NULL,
                            ':fv1_clean_quantity' => isset($detail['rc1_clean_quantity']) && is_numeric($detail['rc1_clean_quantity']) ? $detail['rc1_clean_quantity'] : NULL,

                            ':fv1_vat_ad' => is_numeric($detail['rc1_vat_ad']) ? $detail['rc1_vat_ad'] : 0,
                            ':fv1_vatsum_ad' => is_numeric($detail['rc1_vatsum_ad']) ? $detail['rc1_vatsum_ad'] : 0,
                            ':fv1_accimp_ad'  => is_numeric($detail['rc1_accimp_ad']) ? $detail['rc1_accimp_ad'] : 0,
                            ':fv1_codimp_ad' => isset($detail['rc1_codimp_ad']) ? $detail['rc1_codimp_ad'] : 0
                        ));

                        if (is_numeric($resInsertDetailFv) && $resInsertDetailFv > 0) {
                        } else {

                            $this->pedeo->trans_rollback();

                            $respuesta = array(
                                'error'   => true,
                                'data'    => $resInsertDetailFv,
                                'mensaje' => 'No se pudo registrar la factura'
                            );
                
                            return $this->response($respuesta);
                        }
                    }

                } else { 

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'    => $resInsertFv,
                        'mensaje' => 'No se pudo registrar la factura'
                    );
        
                    return $this->response($respuesta);
                }


                // SE INSERTA EL ESTADO DE LA FACTURA
                // SEGUN EL MEDIO DE PAGO
                // ABIERTA SI A CREDITO
                // CERRADA SI ES CONTADO
                $EstadoFactura = 0;
                if (isset($Data['paytype_days']) && $Data['paytype_days'] == 0 ) { 
                    $EstadoFactura = 3;

                } else {
                    $EstadoFactura = 1;
                }
                $sqlInsertEstadoFv = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                                VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

                $resInsertEstadoFv = $this->pedeo->insertRow($sqlInsertEstadoFv, array(
                    ':bed_docentry' => $resInsertFv,
                    ':bed_doctype' => 5,
                    ':bed_status' => $EstadoFactura, //ESTADO
                    ':bed_createby' => $Data['vrc_createby'],
                    ':bed_date' => date('Y-m-d'),
                    ':bed_baseentry' => NULL,
                    ':bed_basetype' => NULL
                ));


                if (is_numeric($resInsertEstadoFv) && $resInsertEstadoFv > 0) {
                } else {

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data' => $resInsertEstadoFv,
                        'mensaje'	=> 'No se pudo registrar la Factura'
                    );


                    $this->response($respuesta);

                    return;
                }
                //
                // SE HACE EL MOVIMIENTO DE DOCUMENTO
                // RECIBO DE CAJA
                $sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
                bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
                VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
                :bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

                $resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

                    ':bmd_doctype' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                    ':bmd_docentry' => $resInsert,
                    ':bmd_createat' => $this->validateDate($Data['vrc_createat']) ? $Data['vrc_createat'] : NULL,
                    ':bmd_doctypeo' => 0, //ORIGEN
                    ':bmd_docentryo' => 0,  //ORIGEN
                    ':bmd_tdi' => 47, // DOCUMENTO INICIAL
                    ':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
                    ':bmd_docnum' => $DocNumVerificado,
                    ':bmd_doctotal' => is_numeric($Data['vrc_total_c']) ? $Data['vrc_total_c'] : 0,
                    ':bmd_cardcode' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
                    ':bmd_cardtype' => 1,
                    ':bmd_currency' => isset($Data['vrc_currency'])?$Data['vrc_currency']:NULL,
                    ':business' => isset($Data['business']) ? $Data['business'] : NULL
                ));

                if (is_numeric($resInsertMD) && $resInsertMD > 0) {
                } else {
                $this->pedeo->trans_rollback();

                    $respuesta = array(
                    'error'   => true,
                    'data' => $resInsertEstado,
                    'mensaje'	=> 'No se pudo registrar el movimiento del documento'
                    );


                    $this->response($respuesta);

                    return;
                }
                // FACTURA DE VENTAS

                $sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
                bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency,business)
                VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
                :bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency,:business)";

                $resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

                    ':bmd_doctype' => 5,
                    ':bmd_docentry' => $resInsertFv,
                    ':bmd_createat' => $this->validateDate($Data['vrc_createat']) ? $Data['vrc_createat'] : NULL,
                    ':bmd_doctypeo' => 47, //ORIGEN
                    ':bmd_docentryo' => $resInsert,  //ORIGEN
                    ':bmd_tdi' => 47, // DOCUMENTO INICIAL
                    ':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
                    ':bmd_docnum' => $DocNumVerificado,
                    ':bmd_doctotal' => is_numeric($Data['vrc_total_c']) ? $Data['vrc_total_c'] : 0,
                    ':bmd_cardcode' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
                    ':bmd_cardtype' => 1,
                    ':bmd_currency' => isset($Data['vrc_currency'])?$Data['vrc_currency']:NULL,
                    ':business' => isset($Data['business']) ? $Data['business'] : NULL
                ));

                if (is_numeric($resInsertMD) && $resInsertMD > 0) {
                } else {
                $this->pedeo->trans_rollback();

                    $respuesta = array(
                    'error'   => true,
                    'data' => $resInsertEstado,
                    'mensaje'	=> 'No se pudo registrar el movimiento del documento'
                    );


                    $this->response($respuesta);

                    return;
                }
                //
            }           
        }
        //
        
        // SE BUSCA EL IMPUESTO CONFIGURADO
        $codImpuest = "SELECT coalesce(bcc_codimp, '0') as imp, bcc_cccode, bcc_acount FROM tbcc where bcc_user = :bcc_user";
        $resCodImpuest = $this->pedeo->queryTable($codImpuest, array(
            ':bcc_user' => $Data['vrc_createby']
        ));

        if ( isset($resCodImpuest[0]) && $resCodImpuest[0]['imp'] != '0' ){

        }else{

            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $resCodImpuest,
                'mensaje' => 'No se encontro el impuesto'
            );

            return $this->response($respuesta);
        }
        // SE BUSCA EL IMPUESTO DE LA OPERACIÓN
        $opImpuest = "SELECT * FROM dmtx WHERE dmi_code = :dmi_code";
        $resOpImpuest = $this->pedeo->queryTable($opImpuest, array(
            ':dmi_code' => $resCodImpuest[0]['imp']
        ));

        if (isset($resOpImpuest[0])){

        }else{
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $resOpImpuest,
                'mensaje' => 'No se pudo registrar el recibo'
            );

            return $this->response($respuesta);
        }

        // ASIENTO DE IVA 
        if ( $FacturaComsumidor == 0) {
            if ( $Data['vrc_monto_v'] > 0 ) {

                // IMPUESTO
                foreach ($DetalleConsolidadoIvaVenta as $key => $posicion) {

                    $monto = 0;
                    $cdito = 0;
                    $MontoSysCR = 0;
                    $MontoSysOrg = 0;
                    $cuenta = "";

                    foreach ($posicion as $key => $value) {

                        $monto = $monto + $value->impuesto;
                        $cuenta = $value->cuentaiva;

                    }

                    $cdito = $monto;
                    $MontoSysCR = $cdito;
                    $MontoSysOrg = $cdito;


                    if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
        
                        $cdito = ($cdito * $TasaLocSys);
                    }
            
                    if (trim($Data['vrc_currency']) != $MONEDASYS) {
                        $MontoSysCR = ($cdito / $TasaLocSys);
                    } else {
                        $MontoSysCR = $MontoSysOrg;
                    }

                    // SE AGREGA AL BALANCE
            
                    $BALANCE = $this->account->addBalance($periodo['data'], round($cdito, $DECI_MALES), $cuenta, 2, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                        ':ac1_account' => $cuenta,
                        ':ac1_debit' => 0,
                        ':ac1_credit' => round($cdito, $DECI_MALES),
                        ':ac1_debit_sys' => 0,
                        ':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
                        ':ac1_currex' => 0,
                        ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                        ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                        ':ac1_debit_import' => 0,
                        ':ac1_credit_import' => 0,
                        ':ac1_debit_importsys' => 0,
                        ':ac1_credit_importsys' => 0,
                        ':ac1_font_key' => $resInsert,
                        ':ac1_font_line' => 1,
                        ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                        ':ac1_accountvs' => 1,
                        ':ac1_doctype' => 18,
                        ':ac1_ref1' => "",
                        ':ac1_ref2' => "",
                        ':ac1_ref3' => "",
                        ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                        ':ac1_uncode' => NULL,
                        ':ac1_prj_code' => NULL,
                        ':ac1_rescon_date' => NULL,
                        ':ac1_recon_total' => 0,
                        ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                        ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                            'mensaje'	=> 'No se pudo registrar el recibo'
                        );
            
                        $this->response($respuesta);
            
                        return;
                    }
                    
                }

                // IMPUESTO MULTIPLE
                foreach ($DetalleConsolidadoImpMultiVenta as $key => $posicion) {

                    $monto = 0;
                    $cdito = 0;
                    $MontoSysCR = 0;
                    $MontoSysOrg = 0;
                    $cuenta = "";

                    foreach ($posicion as $key => $value) {

                        $monto = $monto + $value->impuesto;
                        $cuenta = $value->cuentaiva;

                    }

                    $cdito = $monto;
                    $MontoSysCR = $cdito;
                    $MontoSysOrg = $cdito;


                    if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
        
                        $cdito = ($cdito * $TasaLocSys);
                    }
            
                    if (trim($Data['vrc_currency']) != $MONEDASYS) {
                        $MontoSysCR = ($cdito / $TasaLocSys);
                    } else {
                        $MontoSysCR = $MontoSysOrg;
                    }

                    // SE AGREGA AL BALANCE
            
                    $BALANCE = $this->account->addBalance($periodo['data'], round($cdito, $DECI_MALES), $cuenta, 2, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                        ':ac1_account' => $cuenta,
                        ':ac1_debit' => 0,
                        ':ac1_credit' => round($cdito, $DECI_MALES),
                        ':ac1_debit_sys' => 0,
                        ':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
                        ':ac1_currex' => 0,
                        ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                        ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                        ':ac1_debit_import' => 0,
                        ':ac1_credit_import' => 0,
                        ':ac1_debit_importsys' => 0,
                        ':ac1_credit_importsys' => 0,
                        ':ac1_font_key' => $resInsert,
                        ':ac1_font_line' => 1,
                        ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                        ':ac1_accountvs' => 1,
                        ':ac1_doctype' => 18,
                        ':ac1_ref1' => "",
                        ':ac1_ref2' => "",
                        ':ac1_ref3' => "",
                        ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                        ':ac1_uncode' => NULL,
                        ':ac1_prj_code' => NULL,
                        ':ac1_rescon_date' => NULL,
                        ':ac1_recon_total' => 0,
                        ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                        ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                            'mensaje'	=> 'No se pudo registrar el recibo'
                        );
            
                        $this->response($respuesta);
            
                        return;
                    }
                    
                }
            }
        }
        //
        // ASIENTO DE INGRESO
        if ( $FacturaComsumidor == 0 ) {
            if ( $Data['vrc_monto_v'] > 0 ) {

                foreach ($DetalleConsolidadoIngresoVenta as $key => $posicion) {
                    $monto = 0;
                    $montoSys = 0;
                    $montoOrg = 0;
                    $cuenta = "";
                    foreach ($posicion as $key => $value) {

                        $monto = $monto + ( $value->cantidad * $value->precio );
                        $cuenta = $value->cuenta;

                    }
                    
                    $monto = round($monto, $DECI_MALES);
                    $montoOrg = $monto;
                    $montoSys = $monto;

                    if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
        
                        $monto = ($monto * $TasaLocSys);
                    }
            
                    if (trim($Data['vrc_currency']) != $MONEDASYS) {
                        $montoSys = ($monto / $TasaLocSys);
                    } else {
                        $montoSys = $montoOrg;
                    }

                                
                    // SE AGREGA AL BALANCE
                
                    $BALANCE =  $this->account->addBalance($periodo['data'], round($monto, $DECI_MALES), $cuenta, 2, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                        ':ac1_account' => $cuenta,
                        ':ac1_debit' => 0,
                        ':ac1_credit' => round($monto, $DECI_MALES),
                        ':ac1_debit_sys' => 0,
                        ':ac1_credit_sys' => round($montoSys, $DECI_MALES),
                        ':ac1_currex' => 0,
                        ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                        ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                        ':ac1_debit_import' => 0,
                        ':ac1_credit_import' => 0,
                        ':ac1_debit_importsys' => 0,
                        ':ac1_credit_importsys' => 0,
                        ':ac1_font_key' => $resInsert,
                        ':ac1_font_line' => 1,
                        ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                        ':ac1_accountvs' => 1,
                        ':ac1_doctype' => 18,
                        ':ac1_ref1' => "",
                        ':ac1_ref2' => "",
                        ':ac1_ref3' => "",
                        ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                        ':ac1_uncode' => NULL,
                        ':ac1_prj_code' => NULL,
                        ':ac1_rescon_date' => NULL,
                        ':ac1_recon_total' => 0,
                        ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                        ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                            'mensaje'	=> 'No se pudo registrar el recibo 7'
                        );
            
                        $this->response($respuesta);
            
                        return;
                    }
        
                }    
            }
        }
        //
        //ASIENTO DEVOLUCION DE IVA
        if ( $Data['vrc_monto_dv'] > 0 ) {

            // IMPUESTO
            foreach ($DetalleConsolidadoIvaDev as $key => $posicion) {

                $monto = 0;
                $MontoSysDB = 0;
                $MontoSysOrg = 0;
                $cuenta = "";

                foreach ($posicion as $key => $value) {

                    $monto = $monto + $value->impuesto;
                    $cuenta = $value->cuentaiva;

                }

                $dbito = $monto;
                $MontoSysDB = $dbito;
                $MontoSysOrg = $dbito;

                if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
    
                    $dbito = ($dbito * $TasaLocSys);
                }
        
                if (trim($Data['vrc_currency']) != $MONEDASYS) {
                    $MontoSysDB = ($dbito / $TasaLocSys);
                } else {
                    $MontoSysDB = $MontoSysOrg;
                }
    
                // SE AGREGA AL BALANCE
    
                $BALANCE = $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES),$cuenta, 1, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                    ':ac1_account' => $cuenta,
                    ':ac1_debit' => round($dbito, $DECI_MALES),
                    ':ac1_credit' => 0,
                    ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
                    ':ac1_credit_sys' => 0,
                    ':ac1_currex' => 0,
                    ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                    ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resInsert,
                    ':ac1_font_line' => 1,
                    ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                    ':ac1_accountvs' => 1,
                    ':ac1_doctype' => 18,
                    ':ac1_ref1' => "",
                    ':ac1_ref2' => "",
                    ':ac1_ref3' => "",
                    ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                    ':ac1_uncode' => NULL,
                    ':ac1_prj_code' => NULL,
                    ':ac1_rescon_date' => NULL,
                    ':ac1_recon_total' => 0,
                    ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                    ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                        'mensaje'	=> 'No se pudo registrar el recibo 8'
                    );
        
                    $this->response($respuesta);
        
                    return;
                }
              
            }

            // IMPUESTO MULTIPLE
            foreach ($DetalleConsolidadoImpMultiDev as $key => $posicion) {

                $monto = 0;
                $MontoSysDB = 0;
                $MontoSysOrg = 0;
                $cuenta = "";

                foreach ($posicion as $key => $value) {

                    $monto = $monto + $value->impuesto;
                    $cuenta = $value->cuentaiva;

                }

                $dbito = $monto;
                $MontoSysDB = $dbito;
                $MontoSysOrg = $dbito;

                if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
    
                    $dbito = ($dbito * $TasaLocSys);
                }
        
                if (trim($Data['vrc_currency']) != $MONEDASYS) {
                    $MontoSysDB = ($dbito / $TasaLocSys);
                } else {
                    $MontoSysDB = $MontoSysOrg;
                }
    
                // SE AGREGA AL BALANCE
    
                $BALANCE = $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES),$cuenta, 1, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                    ':ac1_account' => $cuenta,
                    ':ac1_debit' => round($dbito, $DECI_MALES),
                    ':ac1_credit' => 0,
                    ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
                    ':ac1_credit_sys' => 0,
                    ':ac1_currex' => 0,
                    ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                    ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resInsert,
                    ':ac1_font_line' => 1,
                    ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                    ':ac1_accountvs' => 1,
                    ':ac1_doctype' => 18,
                    ':ac1_ref1' => "",
                    ':ac1_ref2' => "",
                    ':ac1_ref3' => "",
                    ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                    ':ac1_uncode' => NULL,
                    ':ac1_prj_code' => NULL,
                    ':ac1_rescon_date' => NULL,
                    ':ac1_recon_total' => 0,
                    ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                    ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                        'mensaje'	=> 'No se pudo registrar el recibo 8'
                    );
        
                    $this->response($respuesta);
        
                    return;
                }
              
            }
        }
        //
        // ASIENTO DE DEVOLUCIÓN DE INGRESO
        if ( $Data['vrc_monto_dv'] > 0 ) {

            foreach ($DetalleConsolidadoIngresoDevolucion as $key => $posicion) {
				$monto = 0;
                $montoSys = 0;
                $montoOrg = 0;
				$cuenta = "";
				foreach ($posicion as $key => $value) {

                    $monto = $monto + ( $value->cantidad * $value->precio );
                    $cuenta = $value->cuenta;

                }
                $monto = round( $monto, $DECI_MALES );
                $montoOrg = $monto;
                $montoSys = $monto;

                if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
    
                    $monto = ($monto * $TasaLocSys);
                }
        
                if (trim($Data['vrc_currency']) != $MONEDASYS) {
                    $montoSys = ($monto / $TasaLocSys);
                } else {
                    $montoSys = $montoOrg;
                }

                // SE AGREGA AL BALANCE

                $BALANCE = $this->account->addBalance($periodo['data'], round($monto, $DECI_MALES), $cuenta, 1, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                    ':ac1_account' => $cuenta,
                    ':ac1_debit' => round($monto, $DECI_MALES),
                    ':ac1_credit' => 0,
                    ':ac1_debit_sys' => round($montoSys, $DECI_MALES),
                    ':ac1_credit_sys' => 0,
                    ':ac1_currex' => 0,
                    ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                    ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resInsert,
                    ':ac1_font_line' => 1,
                    ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                    ':ac1_accountvs' => 1,
                    ':ac1_doctype' => 18,
                    ':ac1_ref1' => "",
                    ':ac1_ref2' => "",
                    ':ac1_ref3' => "",
                    ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                    ':ac1_uncode' => NULL,
                    ':ac1_prj_code' => NULL,
                    ':ac1_rescon_date' => NULL,
                    ':ac1_recon_total' => 0,
                    ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                    ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                        'mensaje'	=> 'No se pudo registrar el recibo 9'
                    );
        
                    $this->response($respuesta);
        
                    return;
                }

            }
         
        }
        //
        // ASIENTO COSTO VENTA
        if ( $FacturaComsumidor == 0 ) {
            foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
                $dbito = 0;
                $cdito = 0;
                $MontoSysDB = 0;
                $MontoSysCR = 0;
                $grantotalCostoCosto = 0;
                $grantotalCostoCostoOrg = 0;
                foreach ($posicion as $key => $value) {

                    $CUENTASINV = $this->account->getAccountItem($value->itemcode, $value->whscode);

                    if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

                        $sqlCosto = '';
                        $resCosto = [];

                        if ( $value->tipolista == 1 ) {

                            $cuentaCosto = $CUENTASINV['data']['acct_cost'];

                            $grantotalCostoCosto = $grantotalCostoCosto + $value->costoart;

                        } else {

                            if ( $value->malote == 1 ){
                                $sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND bdi_lote = :bdi_lote";
                                $resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->itemcode, ':bdi_whscode' => $value->whscode , ':bdi_lote' => $value->lote));    
                            }else{
                                $sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode";
                                $resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->itemcode, ':bdi_whscode' => $value->whscode));
                            }
    
    
                            if (isset($resCosto[0])) {
                                    $cuentaCosto = $CUENTASINV['data']['acct_cost'];
    
                                    $costoArticulo = $resCosto[0]['bdi_avgprice'];
                                    $cantidadArticulo = $value->cantidad;
                                    $grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));
                            } else {
    
                                $this->pedeo->trans_rollback();
    
                                $respuesta = array(
                                    'error'   => true,
                                    'data'	  => '',
                                    'mensaje'	=> 'No se encontro el costo para el item: ' . $value->itemcode
                                );
    
                                $this->response($respuesta);
    
                                return;
                            }
                        }

                    }else{
                        $this->pedeo->trans_rollback();

                        $respuesta = array(
                            'error'   => true,
                            'data'	  => '',
                            'mensaje'	=> 'No se encontro la cuenta para el item: ' . $value->itemcode
                        );

                        $this->response($respuesta);

                        return;
                    }
                }

                $dbito = $grantotalCostoCosto;
                $grantotalCostoCostoOrg = $grantotalCostoCosto;

                if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
        
                    $dbito = ($dbito * $TasaLocSys);
                }
        
                if (trim($Data['vrc_currency']) != $MONEDASYS) {
                    $MontoSysDB = ($dbito / $TasaLocSys);
                } else {
                    $MontoSysDB = $grantotalCostoCostoOrg;
                }

                // SE AGREGA AL BALANCE

                $BALANCE = $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES), $cuentaCosto, 1, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                    ':ac1_account' => $cuentaCosto,
                    ':ac1_debit' => round($dbito, $DECI_MALES),
                    ':ac1_credit' => 0,
                    ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
                    ':ac1_credit_sys' => 0,
                    ':ac1_currex' => 0,
                    ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                    ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resInsert,
                    ':ac1_font_line' => 1,
                    ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                    ':ac1_accountvs' => 1,
                    ':ac1_doctype' => 18,
                    ':ac1_ref1' => "",
                    ':ac1_ref2' => "",
                    ':ac1_ref3' => "",
                    ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                    ':ac1_uncode' => NULL,
                    ':ac1_prj_code' => NULL,
                    ':ac1_rescon_date' => NULL,
                    ':ac1_recon_total' => 0,
                    ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                    ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                        'mensaje'	=> 'No se pudo registrar el recibo 10'
                    );
        
                    $this->response($respuesta);
        
                    return;
                }


            }
        }
        //
       
        // ASIENTO INVENTARIO VENTA
        foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
            $dbito = 0;
            $cdito = 0;
            $MontoSysDB = 0;
            $MontoSysCR = 0;
            $grantotalCostoInv = 0;
            $grantotalCostoInvOrg = 0;
            foreach ($posicion as $key => $value) {
                $CUENTASINV = $this->account->getAccountItem($value->itemcode, $value->whscode);

                if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

                    $sqlCosto = '';
                    $resCosto = [];

                    if ( $value->tipolista == 1 ) {

                        $cuentaCosto = $CUENTASINV['data']['acct_inv'];

                        $grantotalCostoInv = $grantotalCostoInv + $value->costoart;

                    } else {

                        if ( $value->malote == 1 ){
                            $sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND bdi_lote = :bdi_lote";
                            $resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->itemcode, ':bdi_whscode' => $value->whscode , ':bdi_lote' => $value->lote));    
                        }else{
                            $sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode";
                            $resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->itemcode, ':bdi_whscode' => $value->whscode));
                        }
    
    
                        if (isset($resCosto[0])) {
                                $cuentaCosto = $CUENTASINV['data']['acct_inv'];
    
                                $costoArticulo = $resCosto[0]['bdi_avgprice'];
                                $cantidadArticulo = $value->cantidad;
                                $grantotalCostoInv = ($grantotalCostoInv + ($costoArticulo * $cantidadArticulo));
                        } else {
    
                            $this->pedeo->trans_rollback();
    
                            $respuesta = array(
                                'error'   => true,
                                'data'	  => '',
                                'mensaje'	=> 'No se encontro el costo para el item: ' . $value->itemcode
                            );
    
                            $this->response($respuesta);
    
                            return;
                        }
                    }


                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'	  => '',
                        'mensaje'	=> 'No se encontro la cuenta para el item: ' . $value->itemcode
                    );

                    $this->response($respuesta);

                    return;
                }
            }

            $cdito = $grantotalCostoInv;
            $grantotalCostoInvOrg = $grantotalCostoInv;

            if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
    
                $cdito = ($cdito * $TasaLocSys);
            }
    
            if (trim($Data['vrc_currency']) != $MONEDASYS) {
                $MontoSysCR = ($cdito / $TasaLocSys);
            } else {
                $MontoSysCR = $grantotalCostoInvOrg;
            }   

            // SE AGREGA AL BALANCE

            $BALANCE =  $this->account->addBalance($periodo['data'], round($cdito, $DECI_MALES), $cuentaCosto, 2, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                ':ac1_account' => $cuentaCosto,
                ':ac1_debit' => 0,
                ':ac1_credit' => round($cdito, $DECI_MALES),
                ':ac1_debit_sys' => 0,
                ':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
                ':ac1_currex' => 0,
                ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                ':ac1_debit_import' => 0,
                ':ac1_credit_import' => 0,
                ':ac1_debit_importsys' => 0,
                ':ac1_credit_importsys' => 0,
                ':ac1_font_key' => $resInsert,
                ':ac1_font_line' => 1,
                ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                ':ac1_accountvs' => 1,
                ':ac1_doctype' => 18,
                ':ac1_ref1' => "",
                ':ac1_ref2' => "",
                ':ac1_ref3' => "",
                ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                ':ac1_uncode' => NULL,
                ':ac1_prj_code' => NULL,
                ':ac1_rescon_date' => NULL,
                ':ac1_recon_total' => 0,
                ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                    'mensaje'	=> 'No se pudo registrar el recibo 11'
                );
    
                $this->response($respuesta);
    
                return;
            }


        }

        // ASIENTO COSTO VENTA CREDITO
        if ( $FacturaComsumidor == 1 ) {
            foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
                $dbito = 0;
                $cdito = 0;
                $MontoSysDB = 0;
                $MontoSysCR = 0;
                $grantotalCostoInv = 0;
                $grantotalCostoInvOrg = 0;
                foreach ($posicion as $key => $value) {


                    $sqlCuentaArticulo = "SELECT pge_bridge_inv FROM pgem WHERE pge_id = :business";

					$resCuentaArticulo = $this->pedeo->queryTable($sqlCuentaArticulo, array(':business' => $Data['business']));

					

                    if ( isset($resCuentaArticulo[0]) ){

                        $sqlCosto = '';
                        $resCosto = [];

                        if ( $value->tipolista == 1 ) {

                            $cuentaCosto = $resCuentaArticulo[0]['pge_bridge_inv'];
    
                            $grantotalCostoInv = $grantotalCostoInv + $value->costoart;
    
                        } else {

                            if ( $value->malote == 1 ){
                                $sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND bdi_lote = :bdi_lote";
                                $resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->itemcode, ':bdi_whscode' => $value->whscode , ':bdi_lote' => $value->lote));    
                            }else{
                                $sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode";
                                $resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->itemcode, ':bdi_whscode' => $value->whscode));
                            }
    
    
                            if (isset($resCosto[0])) {
    
                                    $cuentaCosto = $resCuentaArticulo[0]['pge_bridge_inv'];
    
                                    $costoArticulo = $resCosto[0]['bdi_avgprice'];
                                    $cantidadArticulo = $value->cantidad;
                                    $grantotalCostoInv = ($grantotalCostoInv + ($costoArticulo * $cantidadArticulo));
    
                            } else {
    
                                $this->pedeo->trans_rollback();
    
                                $respuesta = array(
                                    'error'   => true,
                                    'data'	  => '',
                                    'mensaje'	=> 'No se encontro el costo para el item: ' . $value->itemcode
                                );
    
                                $this->response($respuesta);
    
                                return;
                            }
                        }

                    }else{

                        $this->pedeo->trans_rollback();

                        $respuesta = array(
                            'error'   => true,
                            'data'	  => '',
                            'mensaje'	=> 'No se encontro la cuenta para el item: ' . $value->itemcode
                        );

                        $this->response($respuesta);

                        return;
                    }
                }

                $dbito = $grantotalCostoInv;
                $grantotalCostoInvOrg = $grantotalCostoInv;

                if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
        
                    $dbito = ($dbito * $TasaLocSys);
                }
        
                if (trim($Data['vrc_currency']) != $MONEDASYS) {
                    $MontoSysDB = ($dbito / $TasaLocSys);
                } else {
                    $MontoSysDB = $grantotalCostoInvOrg;
                }   

                // SE AGREGA AL BALANCE

                $BALANCE =  $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES), $cuentaCosto, 2, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                    ':ac1_account' => $cuentaCosto,
                    ':ac1_debit' => round($dbito, $DECI_MALES),
                    ':ac1_credit' => 0,
                    ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
                    ':ac1_credit_sys' => 0, 
                    ':ac1_currex' => 0,
                    ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                    ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resInsert,
                    ':ac1_font_line' => 1,
                    ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                    ':ac1_accountvs' => 1,
                    ':ac1_doctype' => 18,
                    ':ac1_ref1' => "",
                    ':ac1_ref2' => "",
                    ':ac1_ref3' => "",
                    ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                    ':ac1_uncode' => NULL,
                    ':ac1_prj_code' => NULL,
                    ':ac1_rescon_date' => NULL,
                    ':ac1_recon_total' => 0,
                    ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                    ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                        'mensaje'	=> 'No se pudo registrar el recibo 11'
                    );
        
                    $this->response($respuesta);
        
                    return;
                }


            }
        }
        //
        // ASIENTO COSTO DEVOLUVION
        foreach ($DetalleConsolidadoCostoCostoDev as $key => $posicion) {
            $dbito = 0;
            $cdito = 0;
            $MontoSysDB = 0;
            $MontoSysCR = 0;
            $grantotalCostoCosto = 0;
            $grantotalCostoCostoOrg = 0;
            foreach ($posicion as $key => $value) {
                $CUENTASINV = $this->account->getAccountItem($value->itemcode, $value->whscode);

                if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

                    $sqlCosto = '';
                    $resCosto = [];

                    if ( $value->tipolista == 1 ) {

                        $cuentaCosto = $CUENTASINV['data']['acct_cost'];

                        $grantotalCostoCosto = $grantotalCostoCosto + $value->costoart;

                    } else {

                        if ( $value->malote == 1 ){
                            $sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND bdi_lote = :bdi_lote";
                            $resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->itemcode, ':bdi_whscode' => $value->whscode , ':bdi_lote' => $value->lote));    
                        }else{
                            $sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode";
                            $resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->itemcode, ':bdi_whscode' => $value->whscode));
                        }
    
    
                        if (isset($resCosto[0])) {
                                $cuentaCosto = $CUENTASINV['data']['acct_cost'];
    
                                $costoArticulo = $resCosto[0]['bdi_avgprice'];
                                $cantidadArticulo = $value->cantidad;
                                $grantotalCostoCosto = ($grantotalCostoCosto + ($costoArticulo * $cantidadArticulo));
                        } else {
    
                            $this->pedeo->trans_rollback();
    
                            $respuesta = array(
                                'error'   => true,
                                'data'	  => '',
                                'mensaje'	=> 'No se encontro el costo para el item: ' . $value->itemcode
                            );
    
                            $this->response($respuesta);
    
                            return;
                        }
    
                    }


                }else{
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'	  => '',
                        'mensaje'	=> 'No se encontro la cuenta para el item: ' . $value->itemcode
                    );

                    $this->response($respuesta);

                    return;
                }
            }

            $cdito = $grantotalCostoCosto;
            $grantotalCostoCostoOrg = $grantotalCostoCosto;

            if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
    
                $cdito = ($cdito * $TasaLocSys);
            }
    
            if (trim($Data['vrc_currency']) != $MONEDASYS) {
                $MontoSysCR = ($cdito / $TasaLocSys);
            } else {
                $MontoSysCR = $grantotalCostoCostoOrg;
            }

            // SE AGREGA AL BALANCE

            $BALANCE = $this->account->addBalance($periodo['data'], round($cdito, $DECI_MALES), $cuentaCosto, 2, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                ':ac1_account' => $cuentaCosto,
                ':ac1_debit' => 0,
                ':ac1_credit' => round($cdito, $DECI_MALES),
                ':ac1_debit_sys' => 0,
                ':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
                ':ac1_currex' => 0,
                ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                ':ac1_debit_import' => 0,
                ':ac1_credit_import' => 0,
                ':ac1_debit_importsys' => 0,
                ':ac1_credit_importsys' => 0,
                ':ac1_font_key' => $resInsert,
                ':ac1_font_line' => 1,
                ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                ':ac1_accountvs' => 1,
                ':ac1_doctype' => 18,
                ':ac1_ref1' => "",
                ':ac1_ref2' => "",
                ':ac1_ref3' => "",
                ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                ':ac1_uncode' => NULL,
                ':ac1_prj_code' => NULL,
                ':ac1_rescon_date' => NULL,
                ':ac1_recon_total' => 0,
                ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                    'mensaje'	=> 'No se pudo registrar el recibo 10'
                );
    
                $this->response($respuesta);
    
                return;
            }


        }
        //
        //
        // ASIENTO INVENTARIO DEVOLUCION
        foreach ($DetalleConsolidadoCostoInventarioDev as $key => $posicion) {
            $dbito = 0;
            $cdito = 0;
            $MontoSysDB = 0;
            $MontoSysCR = 0;
            $grantotalCostoInv = 0;
            $grantotalCostoInvOrg = 0;
            foreach ($posicion as $key => $value) {
                $CUENTASINV = $this->account->getAccountItem($value->itemcode, $value->whscode);

                if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

                    $sqlCosto = '';
                    $resCosto = [];

                    if ( $value->tipolista == 1 ) {

                        $cuentaCosto = $CUENTASINV['data']['acct_inv'];

                        $grantotalCostoInv = $grantotalCostoInv + $value->costoart;

                    } else {

                        if ( $value->malote == 1 ){
                            $sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND bdi_lote = :bdi_lote";
                            $resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->itemcode, ':bdi_whscode' => $value->whscode , ':bdi_lote' => $value->lote));    
                        }else{
                            $sqlCosto = "SELECT bdi_itemcode, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode";
                            $resCosto = $this->pedeo->queryTable($sqlCosto, array(":bdi_itemcode" => $value->itemcode, ':bdi_whscode' => $value->whscode));
                        }
    
    
                        if (isset($resCosto[0])) {
                                $cuentaCosto = $CUENTASINV['data']['acct_inv'];
    
                                $costoArticulo = $resCosto[0]['bdi_avgprice'];
                                $cantidadArticulo = $value->cantidad;
                                $grantotalCostoInv = ($grantotalCostoInv + ($costoArticulo * $cantidadArticulo));
                        } else {
    
                            $this->pedeo->trans_rollback();
    
                            $respuesta = array(
                                'error'   => true,
                                'data'	  => '',
                                'mensaje'	=> 'No se encontro el costo para el item: ' . $value->itemcode
                            );
    
                            $this->response($respuesta);
    
                            return;
                        }
                    }

                }else{
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'	  => '',
                        'mensaje'	=> 'No se encontro la cuenta para el item: ' . $value->itemcode
                    );

                    $this->response($respuesta);

                    return;
                }
            }

            $dbito = $grantotalCostoInv;
            $grantotalCostoInvOrg = $grantotalCostoInv;

            if (trim($Data['vrc_currency']) != $MONEDALOCAL) {

                $dbito = ($dbito * $TasaLocSys);
            }

            if (trim($Data['vrc_currency']) != $MONEDASYS) {
                $MontoSysDB = ($dbito / $TasaLocSys);
            } else {
                $MontoSysDB = $grantotalCostoInvOrg;
            }

            // SE AGREGA AL BALANCE

            $BALANCE = $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES), $cuentaCosto, 1, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                ':ac1_account' => $cuentaCosto,
                ':ac1_debit' => round($dbito, $DECI_MALES),
                ':ac1_credit' => 0,
                ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
                ':ac1_credit_sys' => 0,
                ':ac1_currex' => 0,
                ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                ':ac1_debit_import' => 0,
                ':ac1_credit_import' => 0,
                ':ac1_debit_importsys' => 0,
                ':ac1_credit_importsys' => 0,
                ':ac1_font_key' => $resInsert,
                ':ac1_font_line' => 1,
                ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                ':ac1_accountvs' => 1,
                ':ac1_doctype' => 18,
                ':ac1_ref1' => "",
                ':ac1_ref2' => "",
                ':ac1_ref3' => "",
                ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                ':ac1_uncode' => NULL,
                ':ac1_prj_code' => NULL,
                ':ac1_rescon_date' => NULL,
                ':ac1_recon_total' => 0,
                ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                    'mensaje'	=> 'No se pudo registrar el recibo 11'
                );

                $this->response($respuesta);

                return;
            }


        }
        //
        // ASIENTO CUENTA POR COBRAR DEBITO
        if ( $FacturaComsumidor == 0 ) {
            if ( $Data['vrc_total_c'] > 0 ){

                $sqlcuentaCxC = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
                                JOIN dmgs  AS f2
                                ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
                                WHERE  f1.dms_card_code = :dms_card_code
                                AND f1.dms_card_type = '1'"; //1 para clientes";

                $rescuentaCxC = $this->pedeo->queryTable($sqlcuentaCxC, array(":dms_card_code" => $Data['vrc_cardcode']));

                if (isset($rescuentaCxC[0])){
                    $dbito = $Data['vrc_total_c'];
                    $MontoSysDB = $dbito;
                    $MontoSysOrg = $dbito;
                

                    if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
        
                        $dbito = ($dbito * $TasaLocSys);
                    }
            
                    if (trim($Data['vrc_currency']) != $MONEDASYS) {
                        $MontoSysDB = ($dbito / $TasaLocSys);
                    } else {
                        $MontoSysDB = $MontoSysOrg;
                    }

                
                    $cdito = 0;
                    

                    if (isset($Data['paytype_days']) && $Data['paytype_days'] == 0 ) {
                        $cdito = $dbito;
                    }

                    // SE AGREGA AL BALANCE

                    $BALANCE = $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES), $rescuentaCxC[0]['mgs_acct'], 1, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                        ':ac1_account' => $rescuentaCxC[0]['mgs_acct'],
                        ':ac1_debit' => round($dbito, $DECI_MALES),
                        ':ac1_credit' => 0,
                        ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
                        ':ac1_credit_sys' => 0,
                        ':ac1_currex' => 0,
                        ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                        ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                        ':ac1_debit_import' => 0,
                        ':ac1_credit_import' => 0,
                        ':ac1_debit_importsys' => 0,
                        ':ac1_credit_importsys' => 0,
                        ':ac1_font_key' => $resInsert,
                        ':ac1_font_line' => 1,
                        ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                        ':ac1_accountvs' => 1,
                        ':ac1_doctype' => 18,
                        ':ac1_ref1' => "",
                        ':ac1_ref2' => "",
                        ':ac1_ref3' => "",
                        ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                        ':ac1_uncode' => NULL,
                        ':ac1_prj_code' => NULL,
                        ':ac1_rescon_date' => NULL,
                        ':ac1_recon_total' => 0,
                        ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
                        ':ac1_accperiod' => $periodo['data'],
                        ':ac1_close' => 0,
                        ':ac1_cord' => 0,
                        ':ac1_ven_debit' => round($dbito, $DECI_MALES),
                        ':ac1_ven_credit' => round($cdito, $DECI_MALES),
                        ':ac1_fiscal_acct' => 0,
                        ':ac1_taxid' => 0,
                        ':ac1_isrti' => 0,
                        ':ac1_basert' => 0,
                        ':ac1_mmcode' => 0,
                        ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                            'mensaje'	=> 'No se pudo registrar el recibo 12'
                        );
            
                        $this->response($respuesta);
            
                        return;
                    }


                }else{
                    $this->pedeo->trans_rollback();
        
                    $respuesta = array(
                        'error'   => true,
                        'data'	  => $rescuentaCxC,
                        'mensaje'	=> 'No se pudo registrar el recibo 13'
                    );
        
                    $this->response($respuesta);
        
                    return;
                }

            }
        }
        //
        // ASIENTO CUENTA POR COBRAR CREDITO
        if (isset($Data['paytype_days']) && $Data['paytype_days'] == 0 ) {
            if ( $Data['vrc_total_c'] > 0 ){
                $sqlcuentaCxC = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
                                JOIN dmgs  AS f2
                                ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
                                WHERE  f1.dms_card_code = :dms_card_code
                                AND f1.dms_card_type = '1'"; //1 para clientes";
    
                $rescuentaCxC = $this->pedeo->queryTable($sqlcuentaCxC, array(":dms_card_code" => $Data['vrc_cardcode']));
    
                if (isset($rescuentaCxC[0])){
                    $cdito = $Data['vrc_total_c'];
                    $MontoSysCR = $cdito;
                    $MontoSysOrg = $cdito;
    
                    if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
        
                        $cdito = ($cdito * $TasaLocSys);
                    }
            
                    if (trim($Data['vrc_currency']) != $MONEDASYS) {
                        $MontoSysCR = ($cdito / $TasaLocSys);
                    } else {
                        $MontoSysCR = $MontoSysOrg;
                    }

                    // SE AGREGA AL BALANCE

                    $BALANCE = $this->account->addBalance($periodo['data'], round($cdito, $DECI_MALES), $rescuentaCxC[0]['mgs_acct'], 2, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                        ':ac1_account' => $rescuentaCxC[0]['mgs_acct'],
                        ':ac1_debit' => 0,
                        ':ac1_credit' => round($cdito, $DECI_MALES),
                        ':ac1_debit_sys' => 0,
                        ':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
                        ':ac1_currex' => 0,
                        ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                        ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                        ':ac1_debit_import' => 0,
                        ':ac1_credit_import' => 0,
                        ':ac1_debit_importsys' => 0,
                        ':ac1_credit_importsys' => 0,
                        ':ac1_font_key' => $resInsert,
                        ':ac1_font_line' => 1,
                        ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                        ':ac1_accountvs' => 1,
                        ':ac1_doctype' => 18,
                        ':ac1_ref1' => "",
                        ':ac1_ref2' => "",
                        ':ac1_ref3' => "",
                        ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                        ':ac1_uncode' => NULL,
                        ':ac1_prj_code' => NULL,
                        ':ac1_rescon_date' => NULL,
                        ':ac1_recon_total' => 0,
                        ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                        ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                            'mensaje'	=> 'No se pudo registrar el recibo 14'
                        );
            
                        $this->response($respuesta);
            
                        return;
                    }
    
    
                }else{
                    $this->pedeo->trans_rollback();
        
                    $respuesta = array(
                        'error'   => true,
                        'data'	  => $rescuentaCxC,
                        'mensaje'	=> 'No se pudo registrar el recibo 15'
                    );
        
                    $this->response($respuesta);
        
                    return;
                }
    
            }
        }
        //
        // ASIENTO PARA CAJA (BANCO)
        if (isset($Data['paytype_days']) && $Data['paytype_days'] == 0 ) {
            if ( $montoCajaLocal == 1 && $montoMultiple == 0 ) {
                if ( $Data['vrc_total_c'] > 0 ){
                    
                    $dbito = $Data['vrc_total_c'];
                    $MontoSysDB = $dbito;
                    $MontoSysOrg = $dbito;
        
                    if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
        
                        $dbito = ($dbito * $TasaLocSys);
                    }
            
                    if (trim($Data['vrc_currency']) != $MONEDASYS) {
                        $MontoSysDB = ($dbito / $TasaLocSys);
                    } else {
                        $MontoSysDB = $MontoSysOrg;
                    }
    
                    // SE AGREGA AL BALANCE
    
                    $BALANCE = $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES), $resCodImpuest[0]['bcc_acount'], 1, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                        ':ac1_account' => $resCodImpuest[0]['bcc_acount'],
                        ':ac1_debit' => round($dbito, $DECI_MALES),
                        ':ac1_credit' => 0,
                        ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
                        ':ac1_credit_sys' => 0,
                        ':ac1_currex' => 0,
                        ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                        ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                        ':ac1_debit_import' => 0,
                        ':ac1_credit_import' => 0,
                        ':ac1_debit_importsys' => 0,
                        ':ac1_credit_importsys' => 0,
                        ':ac1_font_key' => $resInsert,
                        ':ac1_font_line' => 1,
                        ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                        ':ac1_accountvs' => 1,
                        ':ac1_doctype' => 18,
                        ':ac1_ref1' => "",
                        ':ac1_ref2' => "",
                        ':ac1_ref3' => "",
                        ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                        ':ac1_uncode' => NULL,
                        ':ac1_prj_code' => NULL,
                        ':ac1_rescon_date' => NULL,
                        ':ac1_recon_total' => 0,
                        ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                        ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                            'mensaje'	=> 'No se pudo registrar el recibo 16'
                        );
            
                        $this->response($respuesta);
            
                        return;
                    }
        
                }
            }else if( $montoCajaLocal == 0 && $montoMultiple == 1 ){
    
                if ( $Data['vrc_total_c'] > 0 ){
                    
                    foreach ($contenidoMultiple as $key => $medio) {
    
                        $dbito = $medio->value;
                        $MontoSysDB = $dbito;
                        $MontoSysOrg = $dbito;
    
                        $cuentaMedio = "SELECT * from tmdp WHERE mdp_id = :mdp_id";
                        $rescuentaMedio = $this->pedeo->queryTable($cuentaMedio, array(":mdp_id" => $medio->id));
        
                        if (!isset($rescuentaMedio[0])){
        
                            $this->pedeo->trans_rollback();
        
                            $respuesta = array(
                                'error'   => true,
                                'data'    => [],
                                'mensaje' => "No se encontro la cuenta del medio de pago"
                            );
        
                            return $this->response($respuesta);
                        }

                        // REGISTRAR VALORES
                        $sqlInsertMP = "INSERT INTO vmpp(mpp_medio,mpp_valor,mpp_docentry,mpp_doctype,business,branch)VALUES(:mpp_medio,:mpp_valor,:mpp_docentry,:mpp_doctype,:business,:branch)";
                        $resInsertMP = $this->pedeo->insertRow($sqlInsertMP, array(
                            
                            ':mpp_medio'    => $medio->id,
                            ':mpp_valor'    => $medio->value,
                            ':mpp_docentry' => $resInsert,
                            ':mpp_doctype'  => $Data['vrc_doctype'],
                            ':business'     => $Data['business'],
                            ':branch'       => $Data['branch']
                        ));

                        if (!is_numeric($resInsertMP) || !$resInsertMP > 0 ){
        
                            $this->pedeo->trans_rollback();
        
                            $respuesta = array(
                                'error'   => true,
                                'data'    => [],
                                'mensaje' => "No se pudo registrar los metodos de pago"
                            );
        
                            return $this->response($respuesta);
                        }
                        //
    
                        $cuenta = "";
    
                        if ( $rescuentaMedio[0]['mdp_local'] == 1 ) {
    
                            $cuenta = $resCodImpuest[0]['bcc_acount'];
    
                        } else {
    
                            $cuenta = $rescuentaMedio[0]['mdp_account'];
                        }
            
                        if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
            
                            $dbito = ($dbito * $TasaLocSys);
                        }
                
                        if (trim($Data['vrc_currency']) != $MONEDASYS) {
                            $MontoSysDB = ($dbito / $TasaLocSys);
                        } else {
                            $MontoSysDB = $MontoSysOrg;
                        }
        
                        // SE AGREGA AL BALANCE
        
                        $BALANCE = $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES), $cuenta, 1, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                            ':ac1_account' => $cuenta,
                            ':ac1_debit' => round($dbito, $DECI_MALES),
                            ':ac1_credit' => 0,
                            ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
                            ':ac1_credit_sys' => 0,
                            ':ac1_currex' => 0,
                            ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                            ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                            ':ac1_debit_import' => 0,
                            ':ac1_credit_import' => 0,
                            ':ac1_debit_importsys' => 0,
                            ':ac1_credit_importsys' => 0,
                            ':ac1_font_key' => $resInsert,
                            ':ac1_font_line' => 1,
                            ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                            ':ac1_accountvs' => 1,
                            ':ac1_doctype' => 18,
                            ':ac1_ref1' => "",
                            ':ac1_ref2' => "",
                            ':ac1_ref3' => "",
                            ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                            ':ac1_uncode' => NULL,
                            ':ac1_prj_code' => NULL,
                            ':ac1_rescon_date' => NULL,
                            ':ac1_recon_total' => 0,
                            ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                            ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                                'mensaje'	=> 'No se pudo registrar el recibo'
                            );
                
                            $this->response($respuesta);
                
                            return;
                        }
                      
                    }
                    
                }
    
            }else if ( $montoCajaLocal == 0 && $montoMultiple == 0 ){
    
                if ( $Data['vrc_total_c'] > 0 ){
                    
                    $dbito = $Data['vrc_total_c'];
                    $MontoSysDB = $dbito;
                    $MontoSysOrg = $dbito;

                    $cuentaMedio = "SELECT * from tmdp WHERE mdp_id = :mdp_id";
                    $rescuentaMedio = $this->pedeo->queryTable($cuentaMedio, array(":mdp_id" => $Data['vrc_paymentmethod']));
    
                    if (!isset($rescuentaMedio[0])){
    
                        $this->pedeo->trans_rollback();
    
                        $respuesta = array(
                            'error'   => true,
                            'data'    => [],
                            'mensaje' => "No se encontro la cuenta del medio de pago"
                        );
    
                        return $this->response($respuesta);
                    }
        
                    if (trim($Data['vrc_currency']) != $MONEDALOCAL) {
        
                        $dbito = ($dbito * $TasaLocSys);
                    }
            
                    if (trim($Data['vrc_currency']) != $MONEDASYS) {
                        $MontoSysDB = ($dbito / $TasaLocSys);
                    } else {
                        $MontoSysDB = $MontoSysOrg;
                    }
    
                    // SE AGREGA AL BALANCE
    
                    $BALANCE = $this->account->addBalance($periodo['data'], round($dbito, $DECI_MALES), $rescuentaMedio[0]['mdp_account'], 1, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                        ':ac1_account' => $rescuentaMedio[0]['mdp_account'],
                        ':ac1_debit' => round($dbito, $DECI_MALES),
                        ':ac1_credit' => 0,
                        ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
                        ':ac1_credit_sys' => 0,
                        ':ac1_currex' => 0,
                        ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                        ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                        ':ac1_debit_import' => 0,
                        ':ac1_credit_import' => 0,
                        ':ac1_debit_importsys' => 0,
                        ':ac1_credit_importsys' => 0,
                        ':ac1_font_key' => $resInsert,
                        ':ac1_font_line' => 1,
                        ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                        ':ac1_accountvs' => 1,
                        ':ac1_doctype' => 18,
                        ':ac1_ref1' => "",
                        ':ac1_ref2' => "",
                        ':ac1_ref3' => "",
                        ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                        ':ac1_uncode' => NULL,
                        ':ac1_prj_code' => NULL,
                        ':ac1_rescon_date' => NULL,
                        ':ac1_recon_total' => 0,
                        ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                        ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                            'mensaje'	=> 'No se pudo registrar el recibo 16'
                        );
            
                        $this->response($respuesta);
            
                        return;
                    }
        
                }
            } 
        }

        //
        // ASIENTO PARA ANTICIPO
        if ( $Data['vrc_pasanaku'] > 0 ) {
            
            $sqlcuentaCxC = "SELECT  f1.dms_card_code, f2.mgs_acctp FROM dmsn AS f1
                            JOIN dmgs  AS f2
                            ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
                            WHERE  f1.dms_card_code = :dms_card_code
                            AND f1.dms_card_type = '1'"; //1 para clientes";

            $rescuentaCxC = $this->pedeo->queryTable($sqlcuentaCxC, array(":dms_card_code" => $Data['vrc_cardcode']));

            if (isset($rescuentaCxC[0])){
                $cdito = $Data['vrc_pasanaku'];
                $MontoSysCR = $cdito;
                $MontoSysOrg = $cdito;

                if (trim($Data['vrc_currency']) != $MONEDALOCAL) {

                    $cdito = ($cdito * $TasaLocSys);
                }

                if (trim($Data['vrc_currency']) != $MONEDASYS) {
                    $MontoSysCR = ($cdito / $TasaLocSys);
                } else {
                    $MontoSysCR = $MontoSysOrg;
                }
                // SE AGREGA AL BALANCE

                $BALANCE = $this->account->addBalance($periodo['data'], round($cdito, $DECI_MALES), $rescuentaCxC[0]['mgs_acctp'], 2, $Data['vrc_docdate'], $Data['business'], $Data['branch']);
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
                    ':ac1_account' => $rescuentaCxC[0]['mgs_acctp'],
                    ':ac1_debit' => 0,
                    ':ac1_credit' => round($cdito, $DECI_MALES),
                    ':ac1_debit_sys' => 0,
                    ':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
                    ':ac1_currex' => 0,
                    ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                    ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resInsert,
                    ':ac1_font_line' => 1,
                    ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                    ':ac1_accountvs' => 1,
                    ':ac1_doctype' => 18,
                    ':ac1_ref1' => "",
                    ':ac1_ref2' => "",
                    ':ac1_ref3' => "",
                    ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                    ':ac1_uncode' => NULL,
                    ':ac1_prj_code' => NULL,
                    ':ac1_rescon_date' => NULL,
                    ':ac1_recon_total' => 0,
                    ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
                    ':ac1_accperiod' => $periodo['data'],
                    ':ac1_close' => 0,
                    ':ac1_cord' => 0,
                    ':ac1_ven_debit' => 0,
                    ':ac1_ven_credit' => round($cdito, $DECI_MALES),
                    ':ac1_fiscal_acct' => 0,
                    ':ac1_taxid' => 0,
                    ':ac1_isrti' => 0,
                    ':ac1_basert' => 0,
                    ':ac1_mmcode' => 0,
                    ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
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
                        'mensaje'	=> 'No se pudo registrar el recibo 17'
                    );

                    $this->response($respuesta);

                    return;
                }


            }else{

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'	  => $rescuentaCxC,
                    'mensaje'	=> 'No se pudo registrar el recibo 18'
                );

                $this->response($respuesta);

                return;
            }

        }
        //

        // ACTUALIZA EL VALOR DEL PAY TODAY
        // SI EL RECIBO ES CONTADO
        if (isset($Data['paytype_days']) && $Data['paytype_days'] == 0 ) {
            if ( $Data['vrc_total_c'] > 0 ) {

                $updatePaytoDay = "UPDATE dvrc set vrc_paytoday = :vrc_paytoday WHERE vrc_docentry = :vrc_docentry";
                $resUpdatePaytoDay = $this->pedeo->updateRow($updatePaytoDay, array(
                    ':vrc_paytoday' => $Data['vrc_total_c'],
                    ':vrc_docentry' => $resInsert
                ));

                if ( is_numeric($resUpdatePaytoDay) && $resUpdatePaytoDay == 1 ) {

                } else {

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data' 	  => $resUpdatePaytoDay,
                        'mensaje' => 'No se pudo actualizar el valor del documento (pay today)'
                        
                    );

                    return $this->response($respuesta);
                }

                // SE CIERRA EL DOCUMENTO
                $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";
        
                $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array (
                    ':bed_docentry' => $resInsert,
                    ':bed_doctype' => $Data['vrc_doctype'],
                    ':bed_status' => 3, // ESTADO ABIERTO
                    ':bed_createby' => $Data['vrc_createby'],
                    ':bed_date' => date('Y-m-d'),
                    ':bed_baseentry' =>  $resInsert,
                    ':bed_basetype' => $Data['vrc_doctype']
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
                //
            }
        }
        //


        if ( !$soloServicio && $FacturaComsumidor == 0 ) {

            //SE VALIDA LA CONTABILIDAD CREADA
            $validateCont = $this->generic->validateAccountingAccent($resInsertAsiento);
            
            // SE VALIDA DIFERENCIA POR DECIMALES
            // Y SE AGREGA UN ASIENTO DE DIFERENCIA EN DECIMALES
            // AJUSTE AL PESO
            // SEGUN SEA EL CASO
            ///


            if (isset($validateCont['error']) && $validateCont['error'] == false) {

            }else{


                $debito  = 0;
                $credito = 0;
                if ( $validateCont['data'][0]['debit_sys'] != $validateCont['data'][0]['credit_sys'] ) {

                    $sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
                    $resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array( ':business' => $Data['business'] ));

                    if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {

                        if ($validateCont['data'][0]['credit_sys'] > $validateCont['data'][0]['debit_sys']) { // DIFERENCIA EN CREDITO EL VALOR SE COLOCA EN DEBITO

                            $debito = ($validateCont['data'][0]['credit_sys'] - $validateCont['data'][0]['debit_sys']);
                        } else { // DIFERENCIA EN DEBITO EL VALOR SE COLOCA EN CREDITO

                            $credito = ($validateCont['data'][0]['debit_sys'] - $validateCont['data'][0]['credit_sys']);
                        }

                        if (round($debito + $credito, $DECI_MALES) > 0) {
                            $AC1LINE = $AC1LINE + 1;
                            $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

                                ':ac1_trans_id' => $resInsertAsiento,
                                ':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
                                ':ac1_debit' => 0,
                                ':ac1_credit' => 0,
                                ':ac1_debit_sys' => round($debito, $DECI_MALES),
                                ':ac1_credit_sys' => round($credito, $DECI_MALES),
                                ':ac1_currex' => 0,
                                ':ac1_doc_date' => $this->validateDate($Data['vrc_docdate']) ? $Data['vrc_docdate'] : NULL,
                                ':ac1_doc_duedate' => $this->validateDate($Data['vrc_duedate']) ? $Data['vrc_duedate'] : NULL,
                                ':ac1_debit_import' => 0,
                                ':ac1_credit_import' => 0,
                                ':ac1_debit_importsys' => 0,
                                ':ac1_credit_importsys' => 0,
                                ':ac1_font_key' => $resInsert,
                                ':ac1_font_line' => 1,
                                ':ac1_font_type' => is_numeric($Data['vrc_doctype']) ? $Data['vrc_doctype'] : 0,
                                ':ac1_accountvs' => 1,
                                ':ac1_doctype' => 18,
                                ':ac1_ref1' => "",
                                ':ac1_ref2' => "",
                                ':ac1_ref3' => "",
                                ':ac1_prc_code' => $resCodImpuest[0]['bcc_cccode'],
                                ':ac1_uncode' => NULL,
                                ':ac1_prj_code' => NULL,
                                ':ac1_rescon_date' => NULL,
                                ':ac1_recon_total' => 0,
                                ':ac1_made_user' => isset($Data['vrc_createby']) ? $Data['vrc_createby'] : NULL,
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
                                ':ac1_legal_num' => isset($Data['vrc_cardcode']) ? $Data['vrc_cardcode'] : NULL,
                                ':ac1_codref' => 1,
                                ':ac1_line'   => $AC1LINE,
                                ':business' => $Data['business'],
                                ':branch' 	=> $Data['branch']
                            ));

                            if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
                                // Se verifica que el detalle no de error insertando //
                            } else {

                                // si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
                                // se retorna el error y se detiene la ejecucion del codigo restante.
                                $this->pedeo->trans_rollback();

                                $respuesta = array(
                                    'error'   => true,
                                    'data'	  => $resDetalleAsiento,
                                    'mensaje'	=> 'No se pudo registrar la factura de ventas'
                                );

                                $this->response($respuesta);

                                return;
                            }
                        }
                    } else {

                        $this->pedeo->trans_rollback();

                        $respuesta = array(
                            'error'   => true,
                            'data'	  => $resCuentaDiferenciaDecimal,
                            'mensaje'	=> 'No se encontro la cuenta para adicionar la diferencia en decimales'
                        );

                        $this->response($respuesta);

                        return;
                    }
                }

            }

            // FIN VALIDACION DIFERENCIA EN DECIMALES
        }


        // $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
        // $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
        // print_r(json_encode($ressqlmac1));
        // exit;
        // print_r( $this->pedeo->queryTable("select *  from dvrc order by vrc_docentry desc", array()));
        // exit;


        if ( !$soloServicio ) {

            //SE VALIDA LA CONTABILIDAD CREADA
            $validateCont2 = $this->generic->validateAccountingAccent2($resInsertAsiento);



            if (isset($validateCont2['error']) && $validateCont2['error'] == false) {

            } else {

                $ressqlmac1 = [];
                $sqlmac1 = "SELECT acc_name, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
                $ressqlmac1['contabilidad'] = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data' 	  => $ressqlmac1,
                    'mensaje' => $validateCont2['mensaje'],
                    
                );

                $this->response($respuesta);

                return;
            }
            //

        } else {

            if ( isset($Data['paytype_days']) && $Data['paytype_days'] > 0 && $FacturaComsumidor == 1 ) {

                $resDell = $this->pedeo->deleteRow( "DELETE FROM tmac WHERE mac_trans_id = :mac_trans_id", array( ":mac_trans_id" => $resInsertAsiento ) );

                if ( is_numeric($resDell) && $resDell == 1 ){
    
                }else{
    
                    $this->pedeo->trans_rollback();
    
                    $respuesta = array(
                        'error'   => true,
                        'data' 	  => [],
                        'mensaje' => 'Error en el registro de asiento venta solo servicio'
                        
                    );
    
                    return $this->response($respuesta);

                }

            } else {

                //SE VALIDA LA CONTABILIDAD CREADA
                $validateCont2 = $this->generic->validateAccountingAccent2($resInsertAsiento);



                if (isset($validateCont2['error']) && $validateCont2['error'] == false) {

                } else {

                    $ressqlmac1 = [];
                    $sqlmac1 = "SELECT acc_name, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
                    $ressqlmac1['contabilidad'] = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data' 	  => $ressqlmac1,
                        'mensaje' => $validateCont2['mensaje'],
                        
                    );

                    $this->response($respuesta);

                    return;
                }
                //


            }
           
        }
  


        $this->pedeo->trans_commit();

        $respuesta = array(
            'error'   => false,
            'data'    => $resInsert,
            'mensaje' => 'Recibo registrado con exito #'.$DocNumVerificado,
            'docnum'  => $DocNumVerificado
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
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_total_d,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_total_d,
                        estado,
                        mdp_name  
                        FROM dvrc
                        left join tmdp on mdp_id = vrc_paymentmethod
                        INNER JOIN responsestatus  ON vrc_docentry = responsestatus.id and vrc_doctype = responsestatus.tipo";

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
                        concat(:vrc_currency,to_char(round(get_dynamic_conversion(:vrc_currency,:vrc_currency,:vrc_docdate,rc1_linetotal::numeric,get_localcur()), get_decimals()), '999,999,999,999.00' )) rc1_linetotal,
                        rc1_itemdev,
                        rc1_vat,
                        rc1_vatsum
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

    public function cancelDocument_post(){

        $Data = $this->post();

        $DECI_MALES =  $this->generic->getDecimals();

        $totalVatSum = 0;
        $totalLineTotal = 0;

        $ManejaInvetario = 0;

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

        if (!isset($Data['vrc_docentry']) OR !isset($Data['vrc_doctype']) OR !isset($Data['business'])){

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'Faltan campos requeridos');

            return $this->response($respuesta);
        }


        $sqlMac1 = " SELECT * FROM mac1
                    WHERE ac1_font_key = :ac1_font_key
                    AND ac1_font_type = :ac1_font_type
                    AND business  = :business";
        
        $resMac1 = $this->pedeo->queryTable($sqlMac1, array(
            ':ac1_font_key' => $Data['vrc_docentry'],
            ':ac1_font_type' => $Data['vrc_doctype'],
            ':business' => $Data['business']
        ));

        if ( isset($resMac1[0]) ) {
            $AC1_TRANS_ID = $resMac1[0]['ac1_trans_id'];
        }

          

        $sqlDoc = "SELECT * FROM dvrc WHERE  vrc_docentry = :vrc_docentry AND business = :business";
        $resDoc = $this->pedeo->queryTable($sqlDoc, array(
            ':vrc_docentry' => $Data['vrc_docentry'],
            ':business' => $Data['business']
        ));

        if ( isset($resDoc[0]) ) {

            $FacturaComsumidor = 0;
            // VERIFICAR EL TIPO DE FACTURA SEGUN EL MEDIO DE PAGO
            if ( !isset($resDoc[0]['vrc_cardcode2']) || empty($resDoc[0]['vrc_cardcode2']) || $resDoc[0]['vrc_cardcode2'] == 'undefined' &&
              !isset($resDoc[0]['vrc_cardname2']) || empty($resDoc[0]['vrc_cardname2']) || $resDoc[0]['vrc_cardname2'] == 'undefined' ) {
                $FacturaComsumidor = 0;
            }else{
                $FacturaComsumidor = 1;
            }

            //VALIDANDO PERIODO CONTABLE
            $periodo = $this->generic->ValidatePeriod($resDoc[0]['vrc_duedev'], $resDoc[0]['vrc_docdate'], $resDoc[0]['vrc_duedate'], 1);

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

            // INICIO DE TRANSACCION
            $this->pedeo->trans_begin();

            //SE INSERTA EL ESTADO DEL DOCUMENTO

            $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
            VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

            $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array (
                ':bed_docentry' => $Data['vrc_docentry'],
                ':bed_doctype' => $Data['vrc_doctype'],
                ':bed_status' => 2, // ESTADO ANULADO
                ':bed_createby' => $Data['vrc_createby'],
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

            // SE BUSCA EL DETALLE DEL DOCUMENTO
            $sqlDetalle = "SELECT * FROM vrc1 WHERE rc1_docentry = :rc1_docentry";
            $resDetalle = $this->pedeo->queryTable($sqlDetalle, array(
                ':rc1_docentry' => $Data['vrc_docentry']
            ));

            if ( isset($resDetalle[0]) ){

                $ContenidoDetalle = $resDetalle;

                foreach ($ContenidoDetalle as $key => $detail) {

                    // SE BUSCA SI EL ARTICULO ES LISTA DE MATERIAL
                    // Y SI LA LISTA DE MATERIAL ES DE TIPO VENTAS
                    $esListaVentas = 0;
                    $costoCompuesto = 0;
                    $sqlItemLista = "SELECT dma_item_code, coalesce(rlm_id, 0) as rlm_id
                                    FROM dmar
                                    left join prlm on dmar.dma_item_code = prlm.rlm_item_code 
                                    WHERE dma_item_mat = 1
                                    AND rlm_bom_type = 3
                                    and prlm.rlm_item_code  = :itemcode";

                    $resItemLista = $this->pedeo->queryTable($sqlItemLista, array(":itemcode" => $detail['rc1_itemcode']));

                    if ( isset($resItemLista[0]) && $resItemLista[0]['rlm_id'] > 0 ) {

                        $sqlDetalleListaMat = "SELECT lm1_itemcode,
                                            lm1_itemname,
                                            lm1_quantity, 
                                            lm1_uom,
                                            lm1_whscode,
                                            costo_por_almacen(lm1_itemcode, :lm1_whscode) as costo
                                            FROM rlm1 
                                            WHERE lm1_iddoc = :lm1_iddoc
                                            AND lm1_type = :lm1_type";

                        $resDetalleListaMat = $this->pedeo->queryTable($sqlDetalleListaMat, array(":lm1_iddoc" => $resItemLista[0]['rlm_id'], ":lm1_type" => '2', ":lm1_whscode" => $detail['rc1_whscode']));

                        

                        if (isset($resDetalleListaMat[0])) {

                            $esListaVentas = 1;
                            $DetalleListaMat = [];
                        
                            foreach ($resDetalleListaMat as $key => $item) {

                                $DetalleListaMat = array (

                                    "rc1_itemcode"  => $item['lm1_itemcode'],
                                    "rc1_quantity"  => ($item['lm1_quantity'] * $detail['rc1_quantity']),
                                    "rc1_whscode"   => $detail['rc1_whscode'],
                                    "rc1_ubication" => $detail['rc1_ubication'],
                                    "ote_code"      => $detail['ote_code'],
                                    "rc1_price"     => 0
                                );

                                $costoCompuesto = $costoCompuesto + ($item['costo'] * $DetalleListaMat['rc1_quantity']);
                                // OPERACIONES DE INVENTARIO 
                                // EN CASO DE DEVOLUCIÓN DE INVENTARIO
                                // SALIDA DE INVENTARIO QUE ES LA OPERACION CONTRARIA
                                if ( $detail['rc1_itemdev'] == 1 ) {
                        
                                    $entryInv = $this->inventory->InventoryOutput($resDoc[0], $DetalleListaMat, $Data['vrc_docentry'], $resDoc[0]['vrc_docnum']);
                    
                                    if ( isset($entryInv['error']) && $entryInv['error'] == false){
                    
                                    }else{
                                        $this->pedeo->trans_rollback();
                    
                                        $respuesta = array(
                                            'error'   => true,
                                            'data'    => [],
                                            'mensaje' => $entryInv['mensaje']
                                        );
                        
                                        return $this->response($respuesta);
                        
                                        
                                    }
                                // EN CASO DE VENTA    
                                // ENTRADA DE INVENTARIO QUE ES LA OPERACION CONTRARIA
                                } else if ( $detail['rc1_itemdev'] == 0 ) {
                    
                                    $exitInv = $this->inventory->InventoryEntry($resDoc[0],  $DetalleListaMat, $Data['vrc_docentry'], $resDoc[0]['vrc_docnum']);
                    
                                    if ( isset($exitInv['error']) && $exitInv['error'] == false){
                    
                                    }else{
                                        $this->pedeo->trans_rollback();
                    
                                        $respuesta = array(
                                            'error'   => true,
                                            'data'    => [],
                                            'mensaje' => $exitInv['mensaje']
                                        );
                        
                                        return $this->response($respuesta);
                                    }
                    
                                }
                                
                            }
                
                            if ( $costoCompuesto == 0 ) {

                                $this->pedeo->trans_rollback();

                                $respuesta = array(
                                    'error'   => true,
                                    'data'    => [],
                                    'mensaje' => 'No se encontro el costo del articulo'. $detail['rc1_itemcode']
                                );
            
                                $this->response($respuesta);
            
                                return;
                            }


                        } else {

                            $this->pedeo->trans_rollback();

                            $respuesta = array(
                                'error'   => true,
                                'data'    => $resDetalleListaMat,
                                'mensaje' => 'No se pudo anular el recibo'
                            );

                            $this->response($respuesta);

                            return;

                        }

                    
                    }


                    // MANEJA INVENTARIO
                    $ManejaInvetario = 0;
                    $sqlItemINV = "SELECT coalesce(dma_item_inv, '0') as dma_item_inv,coalesce(dma_use_tbase,0) as dma_use_tbase, coalesce(dma_tasa_base,0) as dma_tasa_base FROM dmar WHERE dma_item_code = :dma_item_code";
                    $resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

                        ':dma_item_code' => $detail['rc1_itemcode']
                    ));

                    if ( isset($resItemINV[0]) && $resItemINV[0]['dma_item_inv'] == '1' ) {
                        $ManejaInvetario = 1;
                    }
                    //
                    // PROCESO NORMAL
                    // SI ES LISTA DE VENTAS NO SE MUEVE EL INVENTARIO DEL ITEM DE LA LINEA
                    if ( $esListaVentas == 0 ) {
                        if ( $ManejaInvetario == 1 ) {
                            // OPERACIONES DE INVENTARIO 
                            // EN CASO DE DEVOLUCIÓN DE INVENTARIO
                            // SALIDA DE INVENTARIO QUE ES LA OPERACION CONTRARIA
                            if ( $detail['rc1_itemdev'] == 1 ) {
                                
                                $entryInv = $this->inventory->InventoryOutput($resDoc[0], $detail, $Data['vrc_docentry'], $resDoc[0]['vrc_docnum']);

                                if ( isset($entryInv['error']) && $entryInv['error'] == false){

                                }else{
                                    $this->pedeo->trans_rollback();

                                    $respuesta = array(
                                        'error'   => true,
                                        'data'    => [],
                                        'mensaje' => $entryInv['mensaje']
                                    );
                    
                                    return $this->response($respuesta);
                    
                                    
                                }
                            // EN CASO DE VENTA    
                            // ENTRADA DE INVENTARIO QUE ES LA OPERACION CONTRARIA
                            } else if ( $detail['rc1_itemdev'] == 0 ) {

                                $exitInv = $this->inventory->InventoryEntry($resDoc[0], $detail, $Data['vrc_docentry'], $resDoc[0]['vrc_docnum']);

                                if ( isset($exitInv['error']) && $exitInv['error'] == false){

                                }else{
                                    $this->pedeo->trans_rollback();

                                    $respuesta = array(
                                        'error'   => true,
                                        'data'    => [],
                                        'mensaje' => $exitInv['mensaje']
                                    );
                    
                                    return $this->response($respuesta);
                                }

                            }
                        }
                    }

                    
                    // SE ACUMULA MEL TOTAL DEL IMPUESTO Y SUB TOTAL
                    $totalVatSum += $detail['rc1_vatsum'];
                    $totalLineTotal += $detail['rc1_linetotal'];
                    //
                }
                //
                

                if ( isset($resMac1[0]) ) {
                    // SE INSERTA LA CABECERA DE LA CONTABILIDAD

                    $sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, mac_accperiod, business, branch)
                    VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :mac_accperiod, :business, :branch)";
    
    
                    $resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(
                        ':mac_doc_num' => 1,
                        ':mac_status' => 1,
                        ':mac_base_type' => is_numeric($resDoc[0]['vrc_doctype']) ? $resDoc[0]['vrc_doctype'] : 0,
                        ':mac_base_entry' => $Data['vrc_docentry'],
                        ':mac_doc_date' => $this->validateDate($resDoc[0]['vrc_docdate']) ? $resDoc[0]['vrc_docdate'] : NULL,
                        ':mac_doc_duedate' => $this->validateDate($resDoc[0]['vrc_duedate']) ? $resDoc[0]['vrc_duedate'] : NULL,
                        ':mac_legal_date' => $this->validateDate($resDoc[0]['vrc_docdate']) ? $resDoc[0]['vrc_docdate'] : NULL,
                        ':mac_ref1' => is_numeric($resDoc[0]['vrc_doctype']) ? $resDoc[0]['vrc_doctype'] : 0,
                        ':mac_ref2' => "",
                        ':mac_ref3' => "",
                        ':mac_loc_total' => is_numeric($resDoc[0]['vrc_doctotal']) ? $resDoc[0]['vrc_doctotal'] : 0,
                        ':mac_fc_total' => is_numeric($resDoc[0]['vrc_doctotal']) ? $resDoc[0]['vrc_doctotal'] : 0,
                        ':mac_sys_total' => is_numeric($resDoc[0]['vrc_doctotal']) ? $resDoc[0]['vrc_doctotal'] : 0,
                        ':mac_trans_dode' => 1,
                        ':mac_beline_nume' => 1,
                        ':mac_vat_date' => $this->validateDate($resDoc[0]['vrc_docdate']) ? $resDoc[0]['vrc_docdate'] : NULL,
                        ':mac_serie' => 1,
                        ':mac_number' => 1,
                        ':mac_bammntsys' => is_numeric($resDoc[0]['vrc_baseamnt']) ? $resDoc[0]['vrc_baseamnt'] : 0,
                        ':mac_bammnt' => is_numeric($resDoc[0]['vrc_baseamnt']) ? $resDoc[0]['vrc_baseamnt'] : 0,
                        ':mac_wtsum' => 1,
                        ':mac_vatsum' => is_numeric($resDoc[0]['vrc_taxtotal']) ? $resDoc[0]['vrc_taxtotal'] : 0,
                        ':mac_comments' => isset($resDoc[0]['vrc_comment']) ? $resDoc[0]['vrc_comment'] : NULL,
                        ':mac_create_date' => $this->validateDate($resDoc[0]['vrc_createat']) ? $resDoc[0]['vrc_createat'] : NULL,
                        ':mac_made_usuer' => isset($resDoc[0]['vrc_createby']) ? $resDoc[0]['vrc_createby'] : NULL,
                        ':mac_update_date' => date("Y-m-d"),
                        ':mac_update_user' => isset($resDoc[0]['vrc_createby']) ? $resDoc[0]['vrc_createby'] : NULL,
                        ':mac_accperiod' => $periodo['data'],
                        ':business'	  => $resDoc[0]['business'],
                        ':branch' 	  => $resDoc[0]['branch']
                    ));
    
    
                    if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
    
                    } else {
    
                        $this->pedeo->trans_rollback();
    
                        $respuesta = array(
                        'error'   => true,
                        'data'	  => $resInsertAsiento,
                        'mensaje'	=> 'No se pudo registrar la contabilidad del documento'
                        );
    
                        return $this->response($respuesta);
                    }
                    //

                    // SE REGISTRAN LAS LINEAS DE ASIENTO
                    foreach ( $resMac1 as $key => $item ) {

                        $montoDB = $item['ac1_debit'] ;
                        $montoCD = $item['ac1_credit'];
                        $montoSysDB = $item['ac1_debit_sys'];
                        $montoSysCD = $item['ac1_credit_sys'];

                        if ( $montoSysDB > 0 || $montoDB > 0 ){
                            $montoCD = $item['ac1_debit'];
                            $montoSysCD = $item['ac1_debit_sys'];

                            $montoDB = 0;
                            $montoSysDB = 0;
                        }else if ( $montoCD > 0 || $montoSysCD > 0 ){
                            $montoDB = $item['ac1_credit'];
                            $montoSysDB = $item['ac1_credit_sys'];

                            $montoCD = 0;
                            $montoSysCD = 0;
                        }

                        // SE AGREGA AL BALANCE
                        if ( $montoDB > 0 ){
                            $BALANCE = $this->account->addBalance($periodo['data'], round($montoDB, $DECI_MALES), $item['ac1_account'], 1, $item['ac1_doc_date'], $Data['business'], $Data['branch']);
                        }else{
                            $BALANCE = $this->account->addBalance($periodo['data'], round($montoCD, $DECI_MALES), $item['ac1_account'], 2, $item['ac1_doc_date'], $Data['business'], $Data['branch']);
                        }
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

                        $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(
                
                            ':ac1_trans_id' => $resInsertAsiento,
                            ':ac1_account' => $item['ac1_account'],
                            ':ac1_debit' => $montoDB,
                            ':ac1_credit' => $montoCD,
                            ':ac1_debit_sys' => $montoSysDB,
                            ':ac1_credit_sys' => $montoSysCD,
                            ':ac1_currex' => 0,
                            ':ac1_doc_date' => $item['ac1_doc_date'],
                            ':ac1_doc_duedate' => $item['ac1_doc_duedate'],
                            ':ac1_debit_import' => 0,
                            ':ac1_credit_import' => 0,
                            ':ac1_debit_importsys' => 0,
                            ':ac1_credit_importsys' => 0,
                            ':ac1_font_key' => $item['ac1_font_key'],
                            ':ac1_font_line' => 1,
                            ':ac1_font_type' => $item['ac1_font_type'],
                            ':ac1_accountvs' => 1,
                            ':ac1_doctype' => 18,
                            ':ac1_ref1' => "",
                            ':ac1_ref2' => "",
                            ':ac1_ref3' => "",
                            ':ac1_prc_code' => $item['ac1_prc_code'],
                            ':ac1_uncode' => NULL,
                            ':ac1_prj_code' => NULL,
                            ':ac1_rescon_date' => NULL,
                            ':ac1_recon_total' => 0,
                            ':ac1_made_user' => $item['ac1_made_user'],
                            ':ac1_accperiod' => $item['ac1_accperiod'],
                            ':ac1_close' => 0,
                            ':ac1_cord' => 0,
                            ':ac1_ven_debit' => 0,
                            ':ac1_ven_credit' => 0,
                            ':ac1_fiscal_acct' => 0,
                            ':ac1_taxid' => 0,
                            ':ac1_isrti' => 0,
                            ':ac1_basert' => 0,
                            ':ac1_mmcode' => 0,
                            ':ac1_legal_num' => $item['ac1_legal_num'],
                            ':ac1_codref' => 1,
                            ':ac1_line'   => $item['ac1_line'],
                            ':business'	  => $item['business'],
                            ':branch' 	  => $item['branch']
                        ));
                
                        if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
                        } else {
                            $this->pedeo->trans_rollback();
                
                            $respuesta = array(
                                'error'   => true,
                                'data'	  => $resDetalleAsiento,
                                'mensaje'	=> 'No se pudo ingresar el detalle del asiento'
                            );
                
                            $this->response($respuesta);
                
                            return;
                        }

                    }
                    //

                    // SE ACTUALIZAN LA VEN DEBIT y VEN CREDIT
                    $updateVenDebitCredit = "UPDATE mac1 set ac1_ven_debit = :ac1_ven_debit , ac1_ven_credit = :ac1_ven_credit WHERE ac1_trans_id = :ac1_trans_id";
                    $resUpdateVenDebitCredit = $this->pedeo->updateRow($updateVenDebitCredit, array(
                        ':ac1_ven_credit' => 0,
                        ':ac1_ven_debit'  => 0,
                        ':ac1_trans_id'   => $AC1_TRANS_ID
                    ));

                    if ( is_numeric($resUpdateVenDebitCredit) &&  $resUpdateVenDebitCredit > 0 ){

                    }else{
                        $this->pedeo->trans_rollback();
                
                        $respuesta = array(
                            'error'   => true,
                            'data'	  => $resUpdateVenDebitCredit,
                            'mensaje' => 'No se pudo actualizar el credito y debito de los asientos anteriores'
                        );
            
                        return $this->response($respuesta);
                        
                    }
                    //
                }

                // VERIFICAR SI EL RECIBO ES A CREDITO
                $formaPago = $this->pedeo->queryTable("SELECT * FROM dmpf WHERE mpf_id = :mpf_id ", array( ":mpf_id" => $resDoc[0]['vrc_paytype'] ));

                if ( isset($formaPago[0]) ) {

                    if ( $FacturaComsumidor == 0 ) {
                        // BUSCO LA NUMERACON PARA NOTA CREDITO
                        // PRIMERO BUSCO CUAL ES LA SERIE QUE TIENE LA CAJA DEL USUARIO DEL RECIBO
                        $resSeriesCaja = $this->pedeo->queryTable("SELECT bcc_series5 FROM tbcc WHERE bcc_user = :bcc_user AND bcc_status = :bcc_status", array(":bcc_user" => $resDoc[0]['vrc_createby'], ":bcc_status" => 1));

                        if (!isset($resSeriesCaja[0]) || isset($resSeriesCaja[1])) {

                            $this->pedeo->trans_rollback();
            
                            $respuesta = array(
                                'error'   => true,
                                'data'	  => $resSeriesCaja,
                                'mensaje' => 'No es posible seleccionar la numeración correcta para aplicar la nota credito'
                            );
                
                            return $this->response($respuesta);
                        }
                        

                        if ( empty($resSeriesCaja[0]['bcc_series5']) ) {

                            $this->pedeo->trans_rollback();
            
                            $respuesta = array(
                                'error'   => true,
                                'data'	  => $resSeriesCaja,
                                'mensaje' => 'No se encontro la numeración para nota credito'
                            );
                
                            return $this->response($respuesta);
                        }
                        // BUSCANDO LA NUMERACION DEL DOCUMENTO
                        $DocNumVerificado = $this->documentnumbering->NumberDoc($resSeriesCaja[0]['bcc_series5'], $resDoc[0]['vrc_docdate'], $resDoc[0]['vrc_duedate']);

                        if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

                        }else if ($DocNumVerificado['error']){
                            $this->pedeo->trans_rollback();
                            return $this->response($DocNumVerificado);
                        }

                        $sqlInsertNc = "INSERT INTO dvnc(vnc_series, vnc_docnum, vnc_docdate, vnc_duedate, vnc_duedev, vnc_pricelist, vnc_cardcode,
                            vnc_cardname, vnc_currency, vnc_contacid, vnc_slpcode, vnc_empid, vnc_comment, vnc_doctotal, vnc_baseamnt, vnc_taxtotal,
                            vnc_discprofit, vnc_discount, vnc_createat, vnc_baseentry, vnc_basetype, vnc_doctype, vnc_idadd, vnc_adress, vnc_paytype,
                            vnc_createby, vnc_igtf, vnc_taxigtf, vnc_igtfapplyed, vnc_igtfcode,business,branch,vnc_internal_comments,vnc_taxtotal_ad)
                            VALUES(:vnc_series, :vnc_docnum, :vnc_docdate, :vnc_duedate, :vnc_duedev, :vnc_pricelist, :vnc_cardcode, :vnc_cardname,
                            :vnc_currency, :vnc_contacid, :vnc_slpcode, :vnc_empid, :vnc_comment, :vnc_doctotal, :vnc_baseamnt, :vnc_taxtotal, :vnc_discprofit, :vnc_discount,
                            :vnc_createat, :vnc_baseentry, :vnc_basetype, :vnc_doctype, :vnc_idadd, :vnc_adress, :vnc_paytype,:vnc_createby,:vnc_igtf, 
                            :vnc_taxigtf, :vnc_igtfapplyed, :vnc_igtfcode,:business,:branch, :vnc_internal_comments,:vnc_taxtotal_ad)";

                        $resInsertNc = $this->pedeo->insertRow($sqlInsertNc, array(
                            ':vnc_docnum' => $DocNumVerificado,
                            ':vnc_series' => $resSeriesCaja[0]['bcc_series5'],
                            ':vnc_docdate' => date('Y-m-d'),
                            ':vnc_duedate' => date('Y-m-d'),
                            ':vnc_duedev' => date('Y-m-d'),
                            ':vnc_pricelist' => is_numeric($resDoc[0]['vrc_pricelist']) ? $resDoc[0]['vrc_pricelist'] : 0,
                            ':vnc_cardcode' => isset($resDoc[0]['vrc_cardcode']) ? $resDoc[0]['vrc_cardcode'] : NULL,
                            ':vnc_cardname' => isset($resDoc[0]['vrc_cardname']) ? $resDoc[0]['vrc_cardname'] : NULL,
                            ':vnc_currency' => isset($resDoc[0]['vrc_currency']) ? $resDoc[0]['vrc_currency'] : NULL,
                            ':vnc_contacid' => isset($resDoc[0]['vrc_contacid']) ? $resDoc[0]['vrc_contacid'] : NULL,
                            ':vnc_slpcode' => is_numeric($resDoc[0]['vrc_slpcode']) ? $resDoc[0]['vrc_slpcode'] : 0,
                            ':vnc_empid' => is_numeric($resDoc[0]['vrc_empid']) ? $resDoc[0]['vrc_empid'] : 0,
                            ':vnc_comment' => isset($resDoc[0]['vrc_comment']) ? $resDoc[0]['vrc_comment'] : NULL,
                            ':vnc_doctotal' => is_numeric($resDoc[0]['vrc_total_c']) ? $resDoc[0]['vrc_total_c'] : 0,
                            ':vnc_baseamnt' => ($totalLineTotal - $totalVatSum),
                            ':vnc_taxtotal' => $totalVatSum,
                            ':vnc_discprofit' => is_numeric($resDoc[0]['vrc_discprofit']) ? $resDoc[0]['vrc_discprofit'] : 0,
                            ':vnc_discount' => is_numeric($resDoc[0]['vrc_discount']) ? $resDoc[0]['vrc_discount'] : 0,
                            ':vnc_createat' => date("Y-m-d"),
                            ':vnc_baseentry' => is_numeric($resDoc[0]['vrc_docentry']) ? $resDoc[0]['vrc_docentry'] : 0,
                            ':vnc_basetype' => is_numeric($resDoc[0]['vrc_doctype']) ? $resDoc[0]['vrc_doctype'] : 0,
                            ':vnc_doctype' => 6,
                            ':vnc_idadd' => isset($resDoc[0]['vrc_idadd']) ? $resDoc[0]['vrc_idadd'] : NULL,
                            ':vnc_adress' => isset($resDoc[0]['vrc_adress']) ? $resDoc[0]['vrc_adress'] : NULL,
                            ':vnc_paytype' => is_numeric($resDoc[0]['vrc_paytype']) ? $resDoc[0]['vrc_paytype'] : 0,
                            ':vnc_createby' => isset($resDoc[0]['vrc_createby']) ? $resDoc[0]['vrc_createby'] : NULL,
                            ':vnc_igtf'  =>  isset($resDoc[0]['vrc_igtf']) ? $resDoc[0]['vrc_igtf'] : NULL,
                            ':vnc_taxigtf' => isset($resDoc[0]['vrc_taxigtf']) ? $resDoc[0]['vrc_taxigtf'] : NULL,
                            ':vnc_igtfapplyed' => isset($resDoc[0]['vrc_igtfapplyed']) ? $resDoc[0]['vrc_igtfapplyed'] : NULL,
                            ':vnc_igtfcode' => isset($resDoc[0]['vrc_igtfcode']) ? $resDoc[0]['vrc_igtfcode'] : NULL,
                            ':business' => isset($resDoc[0]['business']) ? $resDoc[0]['business'] : NULL,
                            ':branch' => isset($resDoc[0]['branch']) ? $resDoc[0]['branch'] : NULL,
                            ':vnc_internal_comments' => isset($resDoc[0]['vrc_internal_comments']) ? $resDoc[0]['vrc_internal_comments'] : NULL,
                            ':vnc_taxtotal_ad' => is_numeric($resDoc[0]['vrc_taxtotal_ad']) ? $resDoc[0]['vrc_taxtotal_ad'] : 0
                        ));

                        if (is_numeric($resInsertNc) && $resInsertNc > 0) {

                            // Se actualiza la serie de la numeracion del documento

                            $sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum WHERE pgs_id = :pgs_id";

                            $resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
                                ':pgs_nextnum' => $DocNumVerificado,
                                ':pgs_id'      => $resSeriesCaja[0]['bcc_series5']
                            ));


                            if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
                            } else {

                                $this->pedeo->trans_rollback();

                                $respuesta = array(
                                'error'   => true,
                                'data'    => $resActualizarNumeracion,
                                'mensaje'	=> 'No se pudo crear la nota crédito de clientes'
                                );

                                return $this->response($respuesta);
                            }
                            // Fin de la actualizacion de la numeracion del documento

                            //SE INSERTA EL ESTADO DEL DOCUMENTO
                            $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                            VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

                            $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


                                ':bed_docentry'  => $resInsertNc,
                                ':bed_doctype'   => 6,
                                ':bed_status'    => 3, //ESTADO CERRADO
                                ':bed_createby'  => $resDoc[0]['vrc_createby'],
                                ':bed_date'      => date('Y-m-d'),
                                ':bed_baseentry' => $resDoc[0]['vrc_docentry'],
                                ':bed_basetype'  => $resDoc[0]['vrc_doctype']
                            ));


                            if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
                            } else {

                                $this->pedeo->trans_rollback();

                                $respuesta = array(
                                    'error'   => true,
                                    'data' => $resInsertEstado,
                                    'mensaje'	=> 'No se pudo registrar la nota credito de ventas'
                                );


                                return $this->response($respuesta);
                            }
                            //FIN PROCESO ESTADO DEL DOCUMENTO


                            // SE INSERTA EL ESTADO DE LA FACTURA SI APLICA

                            $resFacturaCC = $this->pedeo->queryTable("SELECT * FROM dvfv WHERE dvf_docnum = :dvf_docnum and dvf_series  = :dvf_series", array(

                                ':dvf_docnum' => $resDoc[0]['vrc_docnum'],
                                ':dvf_series' => $resDoc[0]['vrc_series']
                            ));

                            if ( isset($resFacturaCC[0]) ) {

                                $sqlInsertEstado2 = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                                VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";
    
                                $resInsertEstado2 = $this->pedeo->insertRow($sqlInsertEstado2, array(
    
                                    ':bed_docentry'  => $resFacturaCC[0]['dvf_docentry'],
                                    ':bed_doctype'   => 5,
                                    ':bed_status'    => 3, //ESTADO CERRADO
                                    ':bed_createby'  => $resDoc[0]['vrc_createby'],
                                    ':bed_date'      => date('Y-m-d'),
                                    ':bed_baseentry' => $resInsertEstado,
                                    ':bed_basetype'  => 6
                                ));
    
    
                                if (is_numeric($resInsertEstado2) && $resInsertEstado2 > 0) {
                                } else {
    
                                    $this->pedeo->trans_rollback();
    
                                    $respuesta = array(
                                        'error'   => true,
                                        'data' => $resInsertEstado2,
                                        'mensaje'	=> 'No se pudo registrar la nota credito de ventas'
                                    );
    
    
                                    return $this->response($respuesta);
                                }

                            }
                            
                            // FIN ESTADO FACTURA
                            

                            foreach ($ContenidoDetalle as $key => $detail) {

                                $sqlInsertDetailNc = "INSERT INTO vnc1(nc1_docentry, nc1_itemcode, nc1_itemname, nc1_quantity, nc1_uom, nc1_whscode,
                                nc1_price, nc1_vat, nc1_vatsum, nc1_discount, nc1_linetotal, nc1_costcode, nc1_ubusiness, nc1_project,
                                nc1_acctcode, nc1_basetype, nc1_doctype, nc1_avprice, nc1_inventory,nc1_acciva,nc1_linenum,nc1_codimp,
                                nc1_ubication,nc1_baseline,ote_code,nc1_tax_base,imponible,nc1_clean_quantity,
                                nc1_vat_ad,nc1_vatsum_ad,nc1_accimp_ad,nc1_codimp_ad)VALUES(:nc1_docentry, :nc1_itemcode, :nc1_itemname, :nc1_quantity,:nc1_uom, :nc1_whscode,:nc1_price, 
                                :nc1_vat, :nc1_vatsum, :nc1_discount, :nc1_linetotal, :nc1_costcode, :nc1_ubusiness, :nc1_project,:nc1_acctcode, 
                                :nc1_basetype, :nc1_doctype, :nc1_avprice, :nc1_inventory, :nc1_acciva,:nc1_linenum,:nc1_codimp,
                                :nc1_ubication,:nc1_baseline,:ote_code,:nc1_tax_base,:imponible,:nc1_clean_quantity,
                                :nc1_vat_ad,:nc1_vatsum_ad,:nc1_accimp_ad,:nc1_codimp_ad)";

                                $resInsertDetailNc = $this->pedeo->insertRow($sqlInsertDetailNc, array(
                                    ':nc1_docentry' => $resInsertNc,
                                    ':nc1_itemcode' => isset($detail['rc1_itemcode']) ? $detail['rc1_itemcode'] : NULL,
                                    ':nc1_itemname' => isset($detail['rc1_itemname']) ? $detail['rc1_itemname'] : NULL,
                                    ':nc1_quantity' => is_numeric($detail['rc1_quantity']) ? $detail['rc1_quantity'] : 0,
                                    ':nc1_uom' => isset($detail['rc1_uom']) ? $detail['rc1_uom'] : NULL,
                                    ':nc1_whscode' => isset($detail['rc1_whscode']) ? $detail['rc1_whscode'] : NULL,
                                    ':nc1_price' => is_numeric($detail['rc1_price']) ? $detail['rc1_price'] : 0,
                                    ':nc1_vat' => is_numeric($detail['rc1_vat']) ? $detail['rc1_vat'] : 0,
                                    ':nc1_vatsum' => is_numeric($detail['rc1_vatsum']) ? $detail['rc1_vatsum'] : 0,
                                    ':nc1_discount' => is_numeric($detail['rc1_discount']) ? $detail['rc1_discount'] : 0,
                                    ':nc1_linetotal' => is_numeric($detail['rc1_linetotal']) ? $detail['rc1_linetotal'] : 0,
                                    ':nc1_costcode' => isset($detail['rc1_costcode']) ? $detail['rc1_costcode'] : NULL,
                                    ':nc1_ubusiness' => isset($detail['rc1_ubusiness']) ? $detail['rc1_ubusiness'] : NULL,
                                    ':nc1_project' => isset($detail['rc1_project']) ? $detail['rc1_project'] : NULL,
                                    ':nc1_acctcode' => is_numeric($detail['rc1_acctcode']) ? $detail['rc1_acctcode'] : 0,
                                    ':nc1_basetype' => is_numeric($detail['rc1_basetype']) ? $detail['rc1_basetype'] : 0,
                                    ':nc1_doctype' => is_numeric($detail['rc1_doctype']) ? $detail['rc1_doctype'] : 0,
                                    ':nc1_avprice' => is_numeric($detail['rc1_avprice']) ? $detail['rc1_avprice'] : 0,
                                    ':nc1_inventory' => is_numeric($detail['rc1_inventory']) ? $detail['rc1_inventory'] : NULL,
                                    ':nc1_acciva' => is_numeric($detail['rc1_acciva']) ? $detail['rc1_acciva'] : 0,
                                    ':nc1_linenum' => is_numeric($detail['rc1_linenum']) ? $detail['rc1_linenum'] : 0,
                                    ':nc1_codimp'  => isset($detail['rc1_codimp']) ? $detail['rc1_codimp'] : NULL,
                                    ':nc1_ubication'  => isset($detail['rc1_ubication']) ? $detail['rc1_ubication'] : NULL,
                                    ':nc1_baseline' => isset($detail['rc1_baseline']) && is_numeric($detail['rc1_baseline']) ? $detail['rc1_baseline'] : 0,
                                    ':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
                                    ':nc1_tax_base' => is_numeric($detail['rc1_tax_base']) ? $detail['rc1_tax_base'] : 0,
                                    ':imponible' => isset($detail['imponible']) ? $detail['imponible'] : NULL,
                                    ':nc1_clean_quantity' => isset($detail['rc1_clean_quantity']) && is_numeric($detail['rc1_clean_quantity']) ? $detail['rc1_clean_quantity'] : NULL,

                                    
                                    ':nc1_vat_ad' => is_numeric($detail['rc1_vat_ad']) ? $detail['rc1_vat_ad'] : 0,
                                    ':nc1_vatsum_ad' => is_numeric($detail['rc1_vatsum_ad']) ? $detail['rc1_vatsum_ad'] : 0,
                                    ':nc1_accimp_ad' => is_numeric($detail['rc1_accimp_ad']) ? $detail['rc1_accimp_ad'] : 0,
                                    ':nc1_codimp_ad'  => isset($detail['rc1_codimp_ad']) ? $detail['rc1_codimp_ad'] : NULL,
                                ));

                                if (is_numeric($resInsertDetailNc) && $resInsertDetailNc > 0) {

                                }else{

                                    $this->pedeo->trans_rollback();
            
                                    $respuesta = array(
                                        'error'   => true,
                                        'data'	  => $resInsertDetailNc,
                                        'mensaje' => 'No se pudo registrar la nota credito'
                                    );
                        
                                    return $this->response($respuesta);
                                }

                            }

                        } else {

                            $this->pedeo->trans_rollback();
            
                            $respuesta = array(
                                'error'   => true,
                                'data'	  => $resInsertNc,
                                'mensaje' => 'No se pudo registrar la nota credito'
                            );
                
                            return $this->response($respuesta);
                        }

                    }

                } else {

                    $this->pedeo->trans_rollback();
            
                    $respuesta = array(
                        'error'   => true,
                        'data'	  => $resUpdateVenDebitCredit,
                        'mensaje' => 'No se encontro la forma de pago'
                    );
        
                    return $this->response($respuesta);

                }

                //
                $this->pedeo->trans_commit();

                $respuesta = array(
                    'error' => false,
                    'data' => [],
                    'mensaje' => 'Operación realizada con exito'
                );



            }else{

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error' => true,
                    'data'  => [],
                    'mensaje' => 'No se encontro el detalle del documento');
    
                return $this->response($respuesta);
            }


        }else{

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' => 'No se encontro el documento');

            return $this->response($respuesta);
        }

        

        $this->response($respuesta);

    }


    public function getRecibosBySn_get() {

        $Data = $this->get();

        $sqlSelect = "SELECT  
            round(sum(rc1_price * rc1_quantity)::numeric, get_decimals()) as base,
            sum(rc1_vatsum) as impuesto,
            sum(rc1_linetotal) as total
        FROM dvrc
        INNER JOIN vrc1 ON vrc_docentry = rc1_docentry
        INNER JOIN responsestatus  ON vrc_docentry = responsestatus.id AND vrc_doctype = responsestatus.tipo
        AND responsestatus.estado = :estado
        AND dvrc.business = :business
        AND dvrc.vrc_cardcode = :vrc_cardcode
        AND dvrc.vrc_docdate BETWEEN :fi AND :ff";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ":vrc_cardcode" => $Data['dms_card_code'],
            ":estado"       => "Abierto",
            ":business"     => $Data['business'],
            ":fi"     => $Data['fi'],
            ":ff"     => $Data['ff']
        ));

        if(isset($resSelect[0])){

            $respuesta = array(

                'error' => false,
                'data'  => $resSelect,
                'mensaje' => '');

        } else {

            $respuesta = array(

                'error'   => true,
                'data' => array(),
                'mensaje'	=> 'busqueda sin resultados'
                );
        }

        $this->response($respuesta);
    }

    public function getCashOperationDetail_post(){
        $Data = $this->post();

        if(!isset($Data['vrc_cardcode']) OR
            !isset($Data['fi']) OR 
            !isset($Data['ff'])){

                $this->response(array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
        }

        $sqlSelect = "SELECT
		rc1_itemcode,
		rc1_itemname,
		rc1_quantity,
		rc1_price,
		rc1_vatsum,
		rc1_linetotal,
		rc1_whscode
        FROM dvrc
        INNER JOIN vrc1 ON vrc_docentry = rc1_docentry
        INNER JOIN responsestatus  ON vrc_docentry = responsestatus.id AND vrc_doctype = responsestatus.tipo
        AND responsestatus.estado = :estado
        AND dvrc.business = :business
        AND dvrc.vrc_cardcode = :vrc_cardcode
        AND dvrc.vrc_docdate BETWEEN :fi AND :ff";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ":vrc_cardcode" => $Data['vrc_cardcode'],
            ":estado"       => "Abierto",
            ":business"     => $Data['business'],
            ":fi"     => $Data['fi'],
            ":ff"     => $Data['ff']
        ));

        $sqlSelect = str_replace(":vrc_cardcode", "'".$Data['vrc_cardcode']."'", $sqlSelect);
        $sqlSelect = str_replace(":estado", "'Abierto'", $sqlSelect);
        $sqlSelect = str_replace(":business", $Data['business'], $sqlSelect);
        $sqlSelect = str_replace(":fi", "'".$Data['fi']."'", $sqlSelect);
        $sqlSelect = str_replace(":ff", "'".$Data['ff']."'", $sqlSelect);

			$respuesta = array(

				'error' => false,
				'data'  => ['sql' => $sqlSelect, 'equivalence' => array(
					'rc1_itemcode' => 'Codigo' ,          
                    'rc1_itemname' => 'descripción' ,    
                    'rc1_quantity' => 'Cantidad'  ,   
                    'rc1_price' => 'Precio'   , 
                    'rc1_vatsum'          =>   'Impuesto',                                                     
                    'rc1_linetotal' => 'Total'  ,
                    'Almacen' => 'rc1_whscode'     

				)],
				'mensaje' =>''
			);

        return $this->response($respuesta);

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
