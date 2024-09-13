<?php
// PRESUPUESTOS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use PhpOffice\PhpSpreadsheet\Calculation\TextData\Replace;
use Restserver\libraries\REST_Controller;

class bulkPayments extends REST_Controller
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
        $this->load->library('generic');
        $this->load->library('DocumentNumbering');
		$this->load->library('account');
		$this->load->helper('download');

    }

    public function getbulkPaymentsByFilters_post()
    {
        $Data = $this->post();

        if (!isset($Data['doc_startdate'], $Data['doc_enddate'])) {
            $this->response(
                array(
                    'error' => true,
                    'data' => [],
                    'mensaje' => 'La informacion enviada no es valida'
                ), REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $config = array(
            [
                "table" => "dcfc",
                "prefix" => "cfc",
                "rpltarget" => "dcfc_where"
            ],
            [
                "table" => "gbpe",
                "prefix" => "bpe",
                "rpltarget" => "gbpe_where"
            ],
            [
                "table" => "dcnc",
                "prefix" => "cnc",
                "rpltarget" => "dcnc_where"
            ],
            [
                "table" => "dcnd",
                "prefix" => "cnd",
                "rpltarget" => "dcnd_where"
            ],
            [
                "table" => "dcsa",
                "prefix" => "csa",
                "rpltarget" => "dcsa_where"
            ],
            [
                "table" => "tmac",
                "prefix" => "mac",
                "rpltarget" => "tmac_where"
            ]
        );

        $sqlSelect = "SELECT distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigocliente,
        dcfc.cfc_cardcode as nombrecliente,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - cfc_duedate dias,
        dcfc.cfc_comment,
        dcfc.cfc_currency,
        mac1.ac1_font_key as dvf_docentry,
        dcfc.cfc_docnum,
        dcfc.cfc_docdate as fechadocumento,
        dcfc.cfc_duedate as fechavencimiento,
        cfc_docnum as numerodocumento,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
		case
		when mac1.ac1_font_type = 15 OR mac1.ac1_font_type = 46 then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,mac1.ac1_credit, get_localcur())
		else get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,mac1.ac1_debit, get_localcur())
		end	 as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_credit) , get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(dcfc.cfc_currency,dcfc.cfc_docdate) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcfc
        on dcfc.cfc_doctype = mac1.ac1_font_type
        and dcfc.cfc_docentry = mac1.ac1_font_key
        where 1 = 1 {{dcfc_where}}
        and ABS((mac1.ac1_ven_credit) - (mac1.ac1_ven_debit)) > 0
        --PAGO EFECTUADO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigocliente,
        gbpe.bpe_cardcode as nombrecliente,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - gbpe.bpe_docdate as dias,
        gbpe.bpe_comments as bpr_comment,
        gbpe.bpe_currency,
        mac1.ac1_font_key as dvf_docentry,
        gbpe.bpe_docnum,
        gbpe.bpe_docdate as fechadocumento,
        gbpe.bpe_docdate as fechavencimiento,
        gbpe.bpe_docnum as numerodocumento,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
        case
        when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,mac1.ac1_debit, get_localcur())
        else get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,mac1.ac1_debit, get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) , get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(gbpe.bpe_currency,gbpe.bpe_docdate) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join gbpe
        on gbpe.bpe_doctype = mac1.ac1_font_type
        and gbpe.bpe_docentry = mac1.ac1_font_key
        where 1 = 1 {{gbpe_where}}
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --NOTA CREDITO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigocliente,
        dcnc.cnc_cardname as nombrecliente,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - dcnc.cnc_docdate as dias,
        dcnc.cnc_comment as bpr_comment,
        dcnc.cnc_currency,
        mac1.ac1_font_key as dvf_docentry,
        dcnc.cnc_docnum,
        dcnc.cnc_docdate as fechadocumento,
        dcnc.cnc_duedate as fechavencimiento,
        dcnc.cnc_docnum as numerodocumento,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
        case
        when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,mac1.ac1_debit, get_localcur())
        else get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,mac1.ac1_debit, get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) , get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(dcnc.cnc_currency,dcnc.cnc_docdate ) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcnc
        on dcnc.cnc_doctype = mac1.ac1_font_type
        and dcnc.cnc_docentry = mac1.ac1_font_key
        where 1 = 1 {{dcnc_where}}
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --NOTA DEBITO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigocliente,
        dcnd.cnd_cardname as nombrecliente,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - dcnd.cnd_docdate as dias,
        dcnd.cnd_comment as bpr_comment,
        dcnd.cnd_currency,
        mac1.ac1_font_key as dvf_docentry,
        dcnd.cnd_docnum,
        dcnd.cnd_docdate as fechadocumento,
        dcnd.cnd_duedate as fechavencimiento,
        dcnd.cnd_docnum as numerodocumento,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
        case
        when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,mac1.ac1_debit, get_localcur())
        else get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,mac1.ac1_credit, get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,(mac1.ac1_ven_credit) - (mac1.ac1_debit), get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(dcnd.cnd_currency, dcnd.cnd_docdate) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcnd
        on dcnd.cnd_doctype = mac1.ac1_font_type
        and dcnd.cnd_docentry = mac1.ac1_font_key
        where 1 = 1 {{dcnd_where}}
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --ASIENTOS MANUALES
        union all
        select distinct
        mac1.ac1_font_key,
        case
            when ac1_card_type = '1' then mac1.ac1_legal_num
            when ac1_card_type = '2' then mac1.ac1_legal_num
        end as codigocliente,
       '' as nombrecliente,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - tmac.mac_doc_duedate dias,
        tmac.mac_comments,
        tmac.mac_currency,
        0 as dvf_docentry,
        0 as docnum,
        tmac.mac_doc_date as fechadocumento,
        tmac.mac_doc_duedate as fechavencimiento,
        0 as numerodocumento,
        18 as numtype,
        mdt_docname as tipo,
        case
            when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_debit, get_localcur())
            when mac1.ac1_cord = 1 then get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_credit, get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join tmac
        on tmac.mac_trans_id = mac1.ac1_font_key
        and tmac.mac_doctype = mac1.ac1_font_type
        inner join dmsn
        on mac1.ac1_card_type = dmsn.dms_card_type
        and mac1.ac1_legal_num = dmsn.dms_card_code
        where 1 = 1 {{tmac_where}}
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --SOLICITUD DE ANTICIPO DE COMPRAS
        UNION ALL
        SELECT  
        dcsa.csa_docentry as ac1_font_key,
        dcsa.csa_cardcode as codigocliente,
        dcsa.csa_cardname as nombrecliente,
        csa1.sa1_acctcode as cuenta,
        CURRENT_DATE - dcsa.csa_duedate as dias,
        dcsa.csa_comment,
        dcsa.csa_currency,
        csa1.sa1_docentry as cfc_docentry,
        dcsa.csa_docnum as numerodocumento,
        dcsa.csa_docdate as fechadocumento,
        dcsa.csa_duedate as fechavencimiento,
        dcsa.csa_docnum as id_origen,
        csa1.sa1_doctype as numtype,
        mdt_docname as tipo,
        get_dynamic_conversion(:currency,get_localcur(),dcsa.csa_docdate,dcsa.csa_anticipate_total, get_localcur()) as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),dcsa.csa_docdate,(dcsa.csa_anticipate_total) - (dcsa.csa_paytoday) , get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(dcsa.csa_currency,dcsa.csa_docdate) as tasa_dia,
        sa1_linenum,
        0 as ac1_cord
        from dcsa
        inner join csa1 on dcsa.csa_docentry = csa1.sa1_docentry
        inner join dmdt on dmdt.mdt_doctype = dcsa.csa_doctype
        where 1 = 1 {{dcsa_where}}
		and ABS( get_dynamic_conversion(:currency,get_localcur(),dcsa.csa_docdate,(dcsa.csa_anticipate_total) - (dcsa.csa_paytoday) , get_localcur()) ) > 0";

        foreach ($config as $key => $value) {
            $where = "";
            $dateField = "";
            switch ($value['table']) {
                case 'tmac':
                    $dateField = ($Data['doc_filter'] == 1) ? "_doc_date" : "_doc_duedate";
                    $where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
                    $where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND ac1_legal_num in({$Data['doc_cardcode']})" : "";
                    $where .= " AND {$value['table']}.business = :business AND {$value['table']}.branch = :branch";
                    break;
                case 'tspm':
                    $dateField = ($Data['doc_filter'] == 1) ? "_docdate" : "_taxdate";
                    $where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
                    $where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND {$value['table']}.{$value['prefix']}_cardcode in ({$Data['doc_cardcode']})" : "";
                    $where .= " AND {$value['table']}.business = :business AND {$value['table']}.branch = :branch";
                    break;
                case 'dcsa':
                    $dateField = ($Data['doc_filter'] == 1) ? "_docdate" : "_duedate";
                    $where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
                    $where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND {$value['table']}.{$value['prefix']}_cardcode in ({$Data['doc_cardcode']})" : "";
                    $where .= " AND {$value['table']}.business = :business AND {$value['table']}.branch = :branch";
                    break;
				case 'gbpe':
					$dateField = ($Data['doc_filter'] == 1) ? "_docdate" : "_docdate";
					$where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
					$where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND {$value['table']}.{$value['prefix']}_cardcode in ({$Data['doc_cardcode']})" : "";
					$where .= " AND {$value['table']}.business = :business AND {$value['table']}.branch = :branch";
					break;
                default:
                    $dateField = ($Data['doc_filter'] == 1) ? "_docdate" : "_duedate";
                    $where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
                    $where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND {$value['table']}.{$value['prefix']}_cardcode in ({$Data['doc_cardcode']})" : "";
                    $where .= " AND {$value['table']}.business = :business AND {$value['table']}.branch = :branch";
                    break;
            }

            $sqlSelect = str_replace("{{" . $value['rpltarget'] . "}}", $where, $sqlSelect);
        }

        $fecha = $Data['doc_enddate'] ?? date("Y-m-d");
        $sqlSelect = str_replace('{fecha}', $fecha, $sqlSelect);
		// print_r($sqlSelect);exit;
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":currency" => $Data['doc_currency'], ":business" => $Data['business'], ":branch"=> $Data['branch']));
        if (isset($resSelect[0])) {

            $respuesta = array(
                'error' => false,
                'data' => $resSelect,
                'mensaje' => ''
            );

        } else {

            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'busqueda sin resultados'
            );

        }

        $this->response($respuesta);

    }

    public function createBulkPayments_post()
	{

		$DECI_MALES =  $this->generic->getDecimals();
		$Data = $this->post();
		$DocNumVerificado = 0;
		$DetalleAsientoCuentaTercero = new stdClass();
		$DetalleConsolidadoAsientoCuentaTercero = [];
		$llaveAsientoCuentaTercero = "";
		$posicionAsientoCuentaTercero = 0;
		$cuentaTercero = 0;
		$inArrayAsientoCuentaTercero = array();
		$VlrDiffP = 0;
		$VlrDiffN = 0;
		$VlrTotalOpc = 0; // valor total acumulado de la operacion
		$VlrDiff = 0; // valor diferencia total
		$VlrPagoEfectuado = 0;
		$DFPC = 0; // diferencia en peso credito
		$DFPD = 0; // diferencia en peso debito
		$DFPCS = 0; // del sistema
		$DFPDS = 0; // del sistema
		$TasaOrg = 0;

		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
							ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
							ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
							ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, business, branch)VALUES (:ac1_trans_id, :ac1_account,
							:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
							:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
							:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
							:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :business, :branch)";



		if (!isset($Data['detail']) OR !isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$ContenidoDetalle = json_decode($Data['detail'], true);


		if (!is_array($ContenidoDetalle)) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro el detalle del pago'
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
		//
		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['spm_docdate'], $Data['spm_docdate'], $Data['spm_docdate'], 0);

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
		//
		// //BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->documentnumbering->NumberDoc($Data['spm_series'],$Data['spm_docdate'],$Data['spm_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}

		//Obtener Carpeta Principal del Proyecto
		$sqlMainFolder = " SELECT * FROM params";
		$resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());

		if (!isset($resMainFolder[0])) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la caperta principal del proyecto'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
		// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
		$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
		$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

		if (isset($resMonedaLoc[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda local.'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

		// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
		$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
		$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

		if (isset($resMonedaSys[0])) {
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda de sistema.'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

		//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
		// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
		$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['spm_currency'], ':tsa_date' => $Data['spm_docdate']));

		if (isset($resBusTasa[0])) {
		} else {

			if (trim($Data['spm_currency']) != $MONEDALOCAL) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encrontro la tasa de cambio para la moneda: ' . $Data['spm_currency'] . ' en la actual fecha del documento: ' . $Data['spm_docdate'] . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}

		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['spm_docdate']));

		if (isset($resBusTasa2[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $Data['spm_docdate']
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
		$TasaLocSys = $resBusTasa2[0]['tsa_value'];

		// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO

		$sqlInsert = "INSERT INTO
                            	tspm (spm_cardcode,spm_doctype,spm_cardname,spm_address,spm_perscontact,spm_series,spm_docnum,spm_docdate,spm_taxdate,spm_ref,spm_transid,
                                    spm_comments,spm_memo,spm_acctransfer,spm_datetransfer,spm_reftransfer,spm_doctotal,spm_vlrpaid,spm_project,spm_createby,
                                    spm_createat,spm_payment,spm_currency,spm_originbank, business, branch)
                      VALUES (:spm_cardcode,:spm_doctype,:spm_cardname,:spm_address,:spm_perscontact,:spm_series,:spm_docnum,:spm_docdate,:spm_taxdate,:spm_ref,:spm_transid,
                              :spm_comments,:spm_memo,:spm_acctransfer,:spm_datetransfer,:spm_reftransfer,:spm_doctotal,:spm_vlrpaid,:spm_project,:spm_createby,
                              :spm_createat,:spm_payment,:spm_currency, :spm_originbank, :business, :branch)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':spm_cardcode' => isset($Data['spm_cardcode']) ? $Data['spm_cardcode'] : NULL,
			':spm_doctype' => is_numeric($Data['spm_doctype']) ? $Data['spm_doctype'] : 0,
			':spm_cardname' => isset($Data['spm_cardname']) ? $Data['spm_cardname'] : NULL,
			':spm_address' => isset($Data['spm_address']) ? $Data['spm_address'] : NULL,
			':spm_perscontact' => $Data['spm_perscontact'] ?: 0,
			':spm_series' => is_numeric($Data['spm_series']) ? $Data['spm_series'] : 0,
			':spm_docnum' => $DocNumVerificado,
			':spm_docdate' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
			':spm_taxdate' => $this->validateDate($Data['spm_taxdate']) ? $Data['spm_taxdate'] : NULL,
			':spm_ref' => isset($Data['spm_ref']) ? $Data['spm_ref'] : NULL,
			':spm_transid' => is_numeric($Data['spm_transid']) ? $Data['spm_transid'] : 0,
			':spm_comments' => isset($Data['spm_comments']) ? $Data['spm_comments'] : NULL,
			':spm_memo' => isset($Data['spm_memo']) ? $Data['spm_memo'] : NULL,
			':spm_acctransfer' => isset($Data['spm_acctransfer']) ? $Data['spm_acctransfer'] : NULL,
			':spm_datetransfer' => $this->validateDate($Data['spm_datetransfer']) ? $Data['spm_datetransfer'] : NULL,
			':spm_reftransfer' => isset($Data['spm_reftransfer']) ? $Data['spm_reftransfer'] : NULL,
			':spm_doctotal' => is_numeric($Data['spm_doctotal']) ? $Data['spm_doctotal'] : 0,
			':spm_vlrpaid' => is_numeric($Data['spm_vlrpaid']) ? $Data['spm_vlrpaid'] : 0,
			':spm_project' => isset($Data['spm_project']) ? $Data['spm_project'] : NULL,
			':spm_createby' => isset($Data['spm_createby']) ? $Data['spm_createby'] : NULL,
			':spm_createat' => $this->validateDate($Data['spm_createat']) ? $Data['spm_createat'] : NULL,
			':spm_payment' => isset($Data['spm_payment']) ? $Data['spm_payment'] : NULL,
			':spm_currency' => isset($Data['spm_currency']) ? $Data['spm_currency'] : NULL,
			':spm_originbank' => isset($Data['spm_originbank']) ? $Data['spm_originbank'] : null,
			':business' => $Data['business'], 
			':branch' => $Data['branch']
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['spm_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear el pago'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}

			// FIN PROCESO PARA ACTUALIZAR NUMERACION

			//SE INSERTA EL ESTADO DEL DOCUMENTO

			$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
			VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

			$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


				':bed_docentry' => $resInsert,
				':bed_doctype' => $Data['spm_doctype'],
				':bed_status' => 3, //ESTADO CERRADO
				':bed_createby' => $Data['spm_createby'],
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
				'mensaje'	=> 'No se pudo registrar el pago'
			);


				$this->response($respuesta);

				return;
			}

			//FIN PROCESO ESTADO DEL DOCUMENTO

			//Se agregan los asientos contables*/*******

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch, mac_accperiod)
								VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch, :mac_accperiod)";


			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['spm_doctype']) ? $Data['spm_doctype'] : 0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
				':mac_doc_duedate' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
				':mac_legal_date' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
				':mac_ref1' => is_numeric($Data['spm_doctype']) ? $Data['spm_doctype'] : 0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => is_numeric($Data['spm_doctotal']) ? $Data['spm_doctotal'] : 0,
				':mac_fc_total' => is_numeric($Data['spm_doctotal']) ? $Data['spm_doctotal'] : 0,
				':mac_sys_total' => is_numeric($Data['spm_doctotal']) ? $Data['spm_doctotal'] : 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => 0,
				':mac_bammnt' => 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => 0,
				':mac_comments' => isset($Data['spm_comments']) ? $Data['spm_comments'] : NULL,
				':mac_create_date' => $this->validateDate($Data['spm_createat']) ? $Data['spm_createat'] : NULL,
				':mac_made_usuer' => isset($Data['spm_createby']) ? $Data['spm_createby'] : NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['spm_createby']) ? $Data['spm_createby'] : NULL,
				':business' => $Data['business'], 
				':branch' => $Data['branch'],
				':mac_accperiod' => $periodo['data'],
			));


			if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
				// Se verifica que el detalle no de error insertando //
			} else {

				// si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
				// se retorna el error y se detiene la ejecucion del codigo restante.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'	  => $resInsertAsiento,
					'mensaje'	=> 'No se pudo registrar la el pago de ventas'
				);

				$this->response($respuesta);

				return;
			}

			// FINALIZA EL PROCESO PARA INSERTAR LA CABECERA DEL ASIENTO

			// INICIA INSERCION DEL DETALLE

			foreach ($ContenidoDetalle as $key => $detail) {
				$VrlPagoDetalleNormal  = 0;
				$Equiv = 0;
				//SE VALIDA SI ES UN ANTICIPO AL proveedor
				//SE OMITE LA VALIDACION DE LA FACTURA EN CASO DE ANTICIPO


				if ($Data['spm_billpayment'] == '0' || $Data['spm_billpayment'] == 0) {
					//VALIDAR EL VALOR QUE SE ESTA PAGANDO NO SEA MAYOR AL SALDO DE LA FACTURA
					if ($detail['pm1_doctype'] == 15 || $detail['pm1_doctype'] == 46 || $detail['pm1_doctype'] == 16 || $detail['pm1_doctype'] == 17 || $detail['pm1_doctype'] == 19 || $detail['pm1_doctype'] == 18 || $detail['pm1_doctype'] == 36 ) {


						$pf = "";
						$tb  = "";

						if ($detail['pm1_doctype'] == 15 || $detail['pm1_doctype'] == 46) {
							$pf = "cfc";
							$tb  = "dcfc";
						} else if ($detail['pm1_doctype'] == 16) {
							$pf = "cnc";
							$tb  = "dcnc";
						} else if ($detail['pm1_doctype'] == 17) {
						} else if ($detail['pm1_doctype'] == 19) {
							$pf = "bpe";
							$tb  = "gbpe";
						}else if ( $detail['pm1_doctype'] == 36 ){
							$pf = "csa";
							$tb  = "dcsa";
						}

						$resVlrPay = $this->generic->validateBalance($detail['pm1_docentry'], $detail['pm1_doctype'], $tb, $pf, $detail['pm1_vlrpaid'], $Data['spm_currency'], $Data['spm_docdate'], 2, isset($detail['ac1_line_num']) ? $detail['ac1_line_num'] : 0);

						if (isset($resVlrPay['error'])) {

							if ($resVlrPay['error'] == false) {


								$VlrTotalOpc = $resVlrPay['vlrop'];
								$VlrDiff     = ($VlrDiff + $resVlrPay['vlrdiff']);
								$TasaOrg     = $resVlrPay['tasadoc'];
								$Equiv       = $resVlrPay['equiv'];

								$VlrTotalOpc = ($VlrTotalOpc + $Equiv);



								// echo "\n ".$detail['pm1_docentry']." ".$VlrDiff;

							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => [],
									'mensaje'	=> $resVlrPay['mensaje']
								);

								return $this->response($respuesta);
							}
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'    => [],
								'mensaje'	=> 'No se pudo validar el saldo actual del documento ' . $detail['pm1_docentry']
							);

							$this->response($respuesta);

							return;
						}

						// FIN DE VALIDACION PAGO DE FACTURA
					}
				}
				// FIN VALIDACION ANTICIPO PROVEEDOR

				// SE INICIA INSERCION DEL DETALLE

				$sqlInsertDetail = "INSERT INTO
                                        	spm1 (pm1_docnum,pm1_docentry,pm1_numref,pm1_docdate,pm1_vlrtotal,pm1_vlrpaid,pm1_comments,pm1_porcdiscount,pm1_doctype,
                                            pm1_docduedate,pm1_daysbackw,pm1_vlrdiscount,pm1_ocrcode,pm1_line_num,pm1_basenum, pm1_cardcode, pm1_cuenta)
                                    VALUES (:pm1_docnum,:pm1_docentry,:pm1_numref,:pm1_docdate,:pm1_vlrtotal,:pm1_vlrpaid,:pm1_comments,:pm1_porcdiscount,
                                            :pm1_doctype,:pm1_docduedate,:pm1_daysbackw,:pm1_vlrdiscount,:pm1_ocrcode,:pm1_line_num,:pm1_basenum, :pm1_cardcode, :pm1_cuenta)";



				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':pm1_docnum' => $resInsert,
					':pm1_docentry' => is_numeric($detail['pm1_docentry']) ? $detail['pm1_docentry'] : 0,
					':pm1_numref' => isset($detail['pm1_numref']) ? $detail['pm1_numref'] : NULL,
					':pm1_docdate' =>  $this->validateDate($detail['pm1_docdate']) ? $detail['pm1_docdate'] : NULL,
					':pm1_vlrtotal' => is_numeric($detail['pm1_vlrtotal']) ? $detail['pm1_vlrtotal'] : 0,
					':pm1_vlrpaid' => is_numeric($detail['pm1_vlrpaid']) ? $detail['pm1_vlrpaid'] : 0,
					':pm1_comments' => isset($detail['pm1_comments']) ? $detail['pm1_comments'] : NULL,
					':pm1_porcdiscount' => is_numeric($detail['pm1_porcdiscount']) ? $detail['pm1_porcdiscount'] : 0,
					':pm1_doctype' => is_numeric($detail['pm1_doctype']) ? $detail['pm1_doctype'] : 0,
					':pm1_docduedate' => $this->validateDate($detail['pm1_docduedate']) ? $detail['pm1_docduedate'] : NULL,
					':pm1_daysbackw' => is_numeric($detail['pm1_daysbackw']) ? $detail['pm1_daysbackw'] : 0,
					':pm1_vlrdiscount' => is_numeric($detail['pm1_vlrdiscount']) ? $detail['pm1_vlrdiscount'] : 0,
					':pm1_ocrcode' => isset($detail['pm1_ocrcode']) ? $detail['pm1_ocrcode'] : NULL,
					':pm1_line_num' => isset($detail['pm1_line_num']) ? $detail['pm1_line_num']:NULL,
					':pm1_basenum' => is_numeric($detail['pm1_basenum']) ? $detail['pm1_basenum'] : 0,
					':pm1_cardcode' => isset($detail['pm1_cardcode']) ? $detail['pm1_cardcode']:NULL,
					':pm1_cuenta' => is_numeric($detail['pm1_cuenta']) ? $detail['pm1_cuenta'] : 0
				));


				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {

					//SE VALIDA SI ES UN ANTICIPO AL proveedor
					//SE OMITE LA ACTUALIZACION DE LA FACTURA
					if ($Data['spm_billpayment'] == '0' || $Data['spm_billpayment'] == 0) {


						//MOVIMIENTO DE DOCUMENTOS
						if ($detail['pm1_doctype'] == 15  || $detail['pm1_doctype'] == 46 || $detail['pm1_doctype'] == 16 || $detail['pm1_doctype'] == 17  || $detail['pm1_doctype'] == 36 ) {
							//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
							if (isset($detail['pm1_docentry']) && is_numeric($detail['pm1_docentry']) && isset($detail['pm1_doctype']) && is_numeric($detail['pm1_doctype'])) {

								$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
								$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
									':bmd_doctype' => $detail['pm1_doctype'],
									':bmd_docentry' => $detail['pm1_docentry']
								));


								if (isset($resDocInicio[0])) {

									$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
													bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
													VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
													:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

									$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

										':bmd_doctype' => is_numeric($Data['spm_doctype']) ? $Data['spm_doctype'] : 0,
										':bmd_docentry' => $resInsert,
										':bmd_createat' => $this->validateDate($Data['spm_createat']) ? $Data['spm_createat'] : NULL,
										':bmd_doctypeo' => is_numeric($detail['pm1_doctype']) ? $detail['pm1_doctype'] : 0, //ORIGEN
										':bmd_docentryo' => is_numeric($detail['pm1_docentry']) ? $detail['pm1_docentry'] : 0,  //ORIGEN
										':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
										':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
										':bmd_docnum' => $DocNumVerificado,
										':bmd_doctotal' => $VlrTotalOpc,
										':bmd_cardcode' => isset($detail['pm1_tercero']) ? $detail['pm1_tercero'] : NULL,
										':bmd_cardtype' => 2,
										':bmd_currency' => isset($Data['spm_currency'])?$Data['spm_currency']:NULL,
									));

									if (is_numeric($resInsertMD) && $resInsertMD > 0) {
									} else {

										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data' => $resInsertMD,
											'mensaje'	=> 'No se pudo registrar el movimiento del documento'
										);


										$this->response($respuesta);

										return;
									}
								}
							}
						}

						//FIN PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS


						//ACTUALIZAR VALOR PAGADO DE LA FACTURA DE COMPRA

						if ($detail['pm1_doctype'] == 15 || $detail['pm1_doctype'] == 46) {



							$sqlUpdateFactPay = "UPDATE  dcfc  SET cfc_paytoday = COALESCE(cfc_paytoday,0)+:cfc_paytoday WHERE cfc_docentry = :cfc_docentry and cfc_doctype = :cfc_doctype";

							$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay, array(

								':cfc_paytoday' => round($VlrTotalOpc, $DECI_MALES),
								':cfc_docentry' => $detail['pm1_docentry'],
								':cfc_doctype' =>  $detail['pm1_doctype']

							));

							if (is_numeric($resUpdateFactPay) && $resUpdateFactPay > 0) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pm1_docentry']
								);

								$this->response($respuesta);

								return;
							}
						}

						


						// SE ACTUALIZA EL VALOR DEL CAMPO PAY TODAY EN NOTA CREDITO
						if ($detail['pm1_doctype'] == 16) { // SOLO CUANDO ES UNA NOTA CREDITO

							$sqlUpdateFactPay = "UPDATE  dcnc  SET cnc_paytoday = COALESCE(cnc_paytoday,0)+:cnc_paytoday WHERE cnc_docentry = :cnc_docentry and cnc_doctype = :cnc_doctype";

							$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay, array(

								':cnc_paytoday' => round($VlrTotalOpc, $DECI_MALES),
								':cnc_docentry' => $detail['pm1_docentry'],
								':cnc_doctype'  => $detail['pm1_doctype']


							));

							if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la nota credito ' . $detail['pm1_docentry']
								);

								$this->response($respuesta);

								return;
							}
						}
						

						// ACTUALIZAR REFERENCIA DE PAGO EN ASIENTO CONTABLE DE LA FACTURA
						if ($detail['pm1_doctype'] == 15 || $detail['pm1_doctype'] == 46) { // SOLO CUANDO ES UNA FACTURA

							$slqUpdateVenDebit = "UPDATE mac1
										SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
										WHERE ac1_line_num = :ac1_line_num";

							$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven_debit'  => round($VlrTotalOpc, $DECI_MALES),
								':ac1_line_num'  => $detail['pm1_line_num'],

							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateVenDebit,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pm1_docentry']
								);

								$this->response($respuesta);

								return;
							}
						}


						// SE ACTUALIZA EL VALOR DEL ANTICIPO PARA IR DESCONTANDO LO USADO
						// O EN SU DEFECTO TAMBIEN LA NOTA CREDITO
						if ($detail['pm1_doctype'] == 19) {

							$slqUpdateVenDebit = "UPDATE mac1
											SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
											WHERE pm1_line_num = :pm1_line_num";
							$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven_credit' => round($VlrTotalOpc, $DECI_MALES),
								':pm1_line_num'  => $detail['pm1_line_num']

							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateVenDebit,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pm1_docentry']
								);

								$this->response($respuesta);

								return;
							}
						}

						if ($detail['pm1_doctype'] == 16) {

							$slqUpdateVenDebit = "UPDATE mac1
											SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
											WHERE ac1_line_num = :ac1_line_num";
							$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

								':ac1_ven_credit' => round($VlrTotalOpc, $DECI_MALES),
								':ac1_line_num'   => $detail['pm1_line_num'],
							));

							if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pm1_docentry']
								);

								$this->response($respuesta);

								return;
							}
						}


						// validar si se cierra el documento
						if ($detail['pm1_doctype'] == 15 || $detail['pm1_doctype'] == 46) {

							$resEstado = $this->generic->validateBalanceAndClose($detail['pm1_docentry'], $detail['pm1_doctype'], 'dcfc', 'cfc');


							if (isset($resEstado['error']) && $resEstado['error'] == true) {

								$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																								VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

								$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


									':bed_docentry' => $detail['pm1_docentry'],
									':bed_doctype' => $detail['pm1_doctype'],
									':bed_status' => 3, //ESTADO CERRADO
									':bed_createby' => $Data['spm_createby'],
									':bed_date' => date('Y-m-d'),
									':bed_baseentry' => $resInsert,
									':bed_basetype' => $Data['spm_doctype']
								));


								if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $resInsertEstado,
										'mensaje'	=> 'No se pudo registrar el pago'
									);


									$this->response($respuesta);

									return;
								}
							}
						}


						// se valida cerrar la nota credito
						if ($detail['pm1_doctype'] == 16) {


							$resEstado = $this->generic->validateBalanceAndClose($detail['pm1_docentry'], $detail['pm1_doctype'], 'dcnc', 'cnc');


							if (isset($resEstado['error']) && $resEstado['error'] == true) {
								$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																									VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

								$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


									':bed_docentry' => $detail['pm1_docentry'],
									':bed_doctype' => $detail['pm1_doctype'],
									':bed_status' => 3, //ESTADO CERRADO
									':bed_createby' => $Data['spm_createby'],
									':bed_date' => date('Y-m-d'),
									':bed_baseentry' => $resInsert,
									':bed_basetype' => $Data['spm_doctype']
								));


								if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $resInsertEstado,
										'mensaje'	=> 'No se pudo registrar el pago'
									);


									$this->response($respuesta);

									return;
								}
							}
						}

						//ASIENTO MANUALES
						if ($detail['pm1_doctype'] == 18) {

							if ($detail['ac1_cord'] == 1) {

								$slqUpdateVenDebit = "UPDATE mac1
												SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
												WHERE ac1_line_num = :ac1_line_num";

								$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

									':ac1_ven_debit' => round($VlrTotalOpc, $DECI_MALES),
									':ac1_line_num'  => $detail['ac1_line_num']
								));

								if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
								} else {
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $resUpdateVenDebit,
										'mensaje'	=> 'No se pudo actualizar el valor del pago' . $detail['pm1_docentry']
									);

									$this->response($respuesta);

									return;
								}
							} else if ($detail['ac1_cord'] == 0) {
								$slqUpdateVenDebit = "UPDATE mac1
																							SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
																							WHERE ac1_line_num = :ac1_line_num";

								$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

									':ac1_ven_credit' => round($VlrTotalOpc, $DECI_MALES),
									':ac1_line_num'   => $detail['ac1_line_num']
								));

								if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
								} else {
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' 		=> $resUpdateVenDebit,
										'mensaje'	=> 'No se pudo actualizar el valor del pago' . $detail['pm1_docentry']
									);

									$this->response($respuesta);

									return;
								}
							}
						}
						//ASIENTOS MANUALES


						// SE VALIDA CERRAR LA SOLICITUD DE ANTICIPO PROVEEDOR
						if ($detail['pm1_doctype'] == 36) {


							$resEstado = $this->generic->validateBalanceAndClose($detail['pm1_docentry'], $detail['pm1_doctype'], 'dcsa', 'csa');

							if (isset($resEstado['error']) && $resEstado['error'] == true) {
								$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
												VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

								$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


									':bed_docentry' => $detail['pm1_docentry'],
									':bed_doctype' => $detail['pm1_doctype'],
									':bed_status' => 3, //ESTADO CERRADO
									':bed_createby' => $Data['spm_createby'],
									':bed_date' => date('Y-m-d'),
									':bed_baseentry' => $resInsert,
									':bed_basetype' => $Data['spm_doctype']
								));


								if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' => $resInsertEstado,
										'mensaje'	=> 'No se pudo registrar el pago'
									);


									$this->response($respuesta);

									return;
								}
							}
						}
						//
                        //ACTUALIZAR VALOR PAGADO (PAY TO DAY) DE LA SOLICITUD DE ANTICIPO DE COMPRA
						if ($detail['pm1_doctype'] == 36) {



							$sqlUpdateFactPay = "UPDATE  dcsa  SET csa_paytoday = COALESCE(csa_paytoday,0)+:csa_paytoday WHERE csa_docentry = :csa_docentry and csa_doctype = :csa_doctype";

							$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay, array(

								':csa_paytoday' => round($VlrTotalOpc, $DECI_MALES),
								':csa_docentry' => $detail['pm1_docentry'],
								':csa_doctype' =>  $detail['pm1_doctype']

							));

							if (is_numeric($resUpdateFactPay) && $resUpdateFactPay > 0) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resUpdateFactPay,
									'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $detail['pm1_docentry']
								);

								$this->response($respuesta);

								return;
							}
						}


					}
				} else {

					// si falla algun insert del detalle del pago se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar el pago'
					);

					$this->response($respuesta);

					return;
				}

				// FIN PROCESO PARA ACTUALIZAR EL VALOR EN LA FACTURA


				// LLENANDO DETALLE ASIENTOS CONTABLES (AGRUPACION)


				$DetalleAsientoCuentaTercero = new stdClass();

				$DetalleAsientoCuentaTercero->spm_cardcode     = isset($Data['spm_cardcode']) ? $Data['spm_cardcode'] : NULL;
				$DetalleAsientoCuentaTercero->pm1_doctype      = is_numeric($detail['pm1_doctype']) ? $detail['pm1_doctype'] : 0;
				$DetalleAsientoCuentaTercero->pm1_docentry     = is_numeric($detail['pm1_docentry']) ? $detail['pm1_docentry'] : 0;
				// $DetalleAsientoCuentaTercero->cuentalinea      = is_numeric($detail['pm1_cuenta']) ? $detail['pm1_cuenta'] : 0;
				$DetalleAsientoCuentaTercero->cuentalinea      =  $detail['pm1_accountid'];
				$DetalleAsientoCuentaTercero->cuentaNaturaleza = substr($DetalleAsientoCuentaTercero->cuentalinea, 0, 1);
				$DetalleAsientoCuentaTercero->pm1_vlrpaid      = is_numeric($detail['pm1_vlrpaid']) ? $detail['pm1_vlrpaid'] : 0;
				$DetalleAsientoCuentaTercero->pm1_docdate	   = $this->validateDate($detail['pm1_docdate']) ? $detail['pm1_docdate'] : NULL;
				$DetalleAsientoCuentaTercero->cord	           = isset($detail['ac1_cord']) ? $detail['ac1_cord'] : NULL;
				$DetalleAsientoCuentaTercero->vlrpaiddesc	   = $VlrTotalOpc;
				$DetalleAsientoCuentaTercero->tasaoriginaldoc  = $TasaOrg;


				$llaveAsientoCuentaTercero = $DetalleAsientoCuentaTercero->spm_cardcode . $DetalleAsientoCuentaTercero->pm1_doctype . $DetalleAsientoCuentaTercero->tasaoriginaldoc;


				//********************
				if (in_array($llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero)) {

					$posicion = $this->buscarPosicion($llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero);
				} else {

					array_push($inArrayAsientoCuentaTercero, $llaveAsientoCuentaTercero);
					$posicionAsientoCuentaTercero = $this->buscarPosicion($llaveAsientoCuentaTercero, $inArrayAsientoCuentaTercero);
				}


				if (isset($DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero])) {

					if (!is_array($DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero])) {
						$DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero] = array();
					}
				} else {
					$DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero] = array();
				}

				array_push($DetalleConsolidadoAsientoCuentaTercero[$posicionAsientoCuentaTercero], $DetalleAsientoCuentaTercero);

				//*******************************************************\\

				//FIN LLENADO DETALLE ASIENTOS CONTABLE (AGRUPACION)

			}
			//FIN DE ACTUALIZACION DEL VALOR PAGADO EN LA FACTURA


			// SE INSERTA ASIENTO INGRESO


			$debito = 0;
			$credito = 0;
			$MontoSysDB = 0;
			$MontoSysCR = 0;
			$cuenta = $Data['spm_acctransfer'];
			$codigoCuentaIngreso = substr($cuenta, 0, 1);
			$granTotalIngreso = $Data['spm_vlrpaid'];
			$granTotalIngresoOriginal = $granTotalIngreso;

			if (trim($Data['spm_currency']) != $MONEDALOCAL) {
				$granTotalIngreso = ($granTotalIngreso * $TasaDocLoc);
			}

			switch ($codigoCuentaIngreso) {
				case 1: // ESTABLECIDO COMO CREDITO
					$credito = $granTotalIngreso;
					if (trim($Data['spm_currency']) != $MONEDASYS) {

						$MontoSysCR = ($credito / $TasaLocSys);
					} else {

						$MontoSysCR = $granTotalIngresoOriginal;
					}
					break;

				case 2:
					$credito = $granTotalIngreso;
					if (trim($Data['spm_currency']) != $MONEDASYS) {

						$MontoSysCR = ($credito / $TasaLocSys);
					} else {

						$MontoSysCR = $granTotalIngresoOriginal;
					}
					break;

				case 3:
					$credito = $granTotalIngreso;
					if (trim($Data['spm_currency']) != $MONEDASYS) {

						$MontoSysCR = ($credito / $TasaLocSys);
					} else {

						$MontoSysCR = $granTotalIngresoOriginal;
					}
					break;

				case 4:
					$credito = $granTotalIngreso;
					if (trim($Data['spm_currency']) != $MONEDASYS) {

						$MontoSysCR = ($credito / $TasaLocSys);
					} else {

						$MontoSysCR = $granTotalIngresoOriginal;
					}
					break;

				case 5:
					$credito = $granTotalIngreso;
					if (trim($Data['spm_currency']) != $MONEDASYS) {

						$MontoSysCR = ($credito / $TasaLocSys);
					} else {

						$MontoSysCR = $granTotalIngresoOriginal;
					}
					break;

				case 6:
					$credito = $granTotalIngreso;
					if (trim($Data['spm_currency']) != $MONEDASYS) {

						$MontoSysCR = ($credito / $TasaLocSys);
					} else {

						$MontoSysCR = $granTotalIngresoOriginal;
					}
					break;

				case 7:
					$credito = $granTotalIngreso;
					if (trim($Data['spm_currency']) != $MONEDASYS) {

						$MontoSysCR = ($credito / $TasaLocSys);
					} else {

						$MontoSysCR = $granTotalIngresoOriginal;
					}
					break;
			}

			$VlrPagoEfectuado = $credito;

			$DFPC  = $DFPC + round($credito, $DECI_MALES);
			$DFPD  = $DFPD + round($debito, $DECI_MALES);
			$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
			$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);

			// SE AGREGA AL BALANCE
			if ( $debito > 0 ) {
				$BALANCE = $this->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuenta, 1, $Data['spm_docdate'], $Data['business'], $Data['branch']);
			}else{
				$BALANCE = $this->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuenta, 2, $Data['spm_docdate'], $Data['business'], $Data['branch']);
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
				':ac1_account' => $cuenta,
				':ac1_debit' => round($debito, $DECI_MALES),
				':ac1_credit' => round($credito, $DECI_MALES),
				':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
				':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
				':ac1_currex' => 0,
				':ac1_doc_date' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
				':ac1_doc_duedate' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
				':ac1_debit_import' => 0,
				':ac1_credit_import' => 0,
				':ac1_debit_importsys' => 0,
				':ac1_credit_importsys' => 0,
				':ac1_font_key' => $resInsert,
				':ac1_font_line' => 1,
				':ac1_font_type' => 33,
				':ac1_accountvs' => 1,
				':ac1_doctype' => 18,
				':ac1_ref1' => "",
				':ac1_ref2' => "",
				':ac1_ref3' => "",
				':ac1_prc_code' => 0,
				':ac1_uncode' => 0,
				':ac1_prj_code' => 0,
				':ac1_rescon_date' => NULL,
				':ac1_recon_total' => 0,
				':ac1_made_user' => isset($Data['spm_createby']) ? $Data['spm_createby'] : NULL,
				':ac1_accperiod' => $periodo['data'],
				':ac1_close' => 0,
				':ac1_cord' => 0,
				':ac1_ven_debit' => 0,
				':ac1_ven_credit' => 0,
				':ac1_fiscal_acct' => 0,
				':ac1_taxid' => 1,
				':ac1_isrti' => 0,
				':ac1_basert' => 0,
				':ac1_mmcode' => 0,
				':ac1_legal_num' => isset($Data['spm_cardcode']) ? $Data['spm_cardcode'] : NULL,
				':ac1_codref' => 1,
				':business' => $Data['business'],
				':branch' => $Data['branch']

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
					'mensaje'	=> 'No se pudo registrar el pago realizado, ocurrio un error al ingresar el asiento del ingreso'
				);

				$this->response($respuesta);

				return;
			}

			// FIN PROCESO ASIENTO INGRESO
			//SE VALIDA SI ES UN ANTICIPO AL proveedor
			if ($Data['spm_billpayment'] == '0' || $Data['spm_billpayment'] == 0) {
				//Procedimiento para llenar ASIENTO CON CUENTA TERCERO SEGUN GRUPO DE CUENTAS

				foreach ($DetalleConsolidadoAsientoCuentaTercero as $key => $posicion) {
					$TotalPagoRecibido = 0;
					$TotalPagoRecibidoOriginal = 0;
					$TotalDiferencia = 0;
					$cuenta = 0;
					$docentry = 0;
					$cuentaLinea = 0;
					$doctype = 0;
					$fechaDocumento = '';
					$TasaOld = 0;
					$DireferenciaCambio = 0; // SOLO SE USA PARA SABER SI EXISTE UNA DIFERENCIA DE CAMBIO
					$CuentaDiferenciaCambio = 0;
					$ac1cord = null;
					$tasadoc = 0;

					foreach ($posicion as $key => $value) {

						$TotalPagoRecibido = ($TotalPagoRecibido + $value->vlrpaiddesc);


						$docentry = $value->pm1_docentry;
						$doctype  = $value->pm1_doctype;
						$cuenta   = $value->cuentaNaturaleza;
						$cuentaLinea = $value->cuentalinea;
						$fechaDocumento = $value->pm1_docdate;
						$ac1cord = $value->cord;
						$tasadoc = $value->tasaoriginaldoc;
					}

					$debito = 0;
					$credito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;
					$TotalPagoRecibidoOriginal = $TotalPagoRecibido;




					if ($doctype == 19 || $doctype == 16) {
						switch ($cuenta) {
							case 1:
								$credito = $TotalPagoRecibido;

								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $tasadoc);
								}

								break;

							case 2:
								$credito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $tasadoc);
								}
								break;

							case 3:
								$credito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $tasadoc);
								}
								break;

							case 4:
								$credito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $tasadoc);
								}
								break;

							case 5:
								$credito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $tasadoc);
								}
								break;

							case 6:
								$credito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $tasadoc);
								}
								break;

							case 7:
								$credito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysCR = ($credito / $TasaLocSys);
								} else {

									$MontoSysCR = ($credito / $tasadoc);
								}
								break;
						}
					} else if ($doctype == 18) {

						if ($ac1cord == 0) {
							$credito = $TotalPagoRecibido;

							if (trim($Data['spm_currency']) != $MONEDASYS) {

								$MontoSysCR = ($credito / $TasaLocSys);
							} else {

								$MontoSysCR = ($credito / $tasadoc);
							}
						} else if ($ac1cord == 1) {

							$debito = $TotalPagoRecibido;

							if (trim($Data['spm_currency']) != $MONEDASYS) {

								$MontoSysDB = ($debito / $TasaLocSys);
							} else {

								$MontoSysDB = ($debito / $tasadoc);
							}
						}
					} else {
						switch ($cuenta) {
							case 1:
								$debito = $TotalPagoRecibido;

								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $tasadoc);
								}

								break;

							case 2:
								$debito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $tasadoc);
								}
								break;

							case 3:
								$debito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $tasadoc);
								}
								break;

							case 4:
								$debito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $tasadoc);
								}
								break;

							case 5:
								$debito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $tasadoc);
								}
								break;

							case 6:
								$debito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $tasadoc);
								}
								break;

							case 7:
								$debito = $TotalPagoRecibido;
								if (trim($Data['spm_currency']) != $MONEDASYS) {

									$MontoSysDB = ($debito / $TasaLocSys);
								} else {

									$MontoSysDB = ($debito / $tasadoc);
								}
								break;
						}
					}

					$DFPC  = $DFPC + round($credito, $DECI_MALES);
					$DFPD  = $DFPD + round($debito, $DECI_MALES);
					$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
					$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);

					// SE AGREGA AL BALANCE
					if ( $debito > 0 ) {
						$BALANCE = $this->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuentaLinea, 1, $Data['spm_docdate'], $Data['business'], $Data['branch']);
					}else{
						$BALANCE = $this->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuentaLinea, 2, $Data['spm_docdate'], $Data['business'], $Data['branch']);
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
						':ac1_account' => $cuentaLinea,
						':ac1_debit' => round($debito, $DECI_MALES),
						':ac1_credit' => round($credito, $DECI_MALES),
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => 33,
						':ac1_accountvs' => 1,
						':ac1_doctype' => 18,
						':ac1_ref1' => "",
						':ac1_ref2' => "",
						':ac1_ref3' => "",
						':ac1_prc_code' => 0,
						':ac1_uncode' => 0,
						':ac1_prj_code' => isset($Data['spm_project']) ? $Data['spm_project'] : NULL,
						':ac1_rescon_date' => NULL,
						':ac1_recon_total' => 0,
						':ac1_made_user' => isset($Data['spm_createby']) ? $Data['spm_createby'] : NULL,
						':ac1_accperiod' => $periodo['data'],
						':ac1_close' => 0,
						':ac1_cord' => 0,
						':ac1_ven_debit' => round($credito, $DECI_MALES),
						':ac1_ven_credit' => round($credito, $DECI_MALES),
						':ac1_fiscal_acct' => 0,
						':ac1_taxid' => 1,
						':ac1_isrti' => 0,
						':ac1_basert' => 0,
						':ac1_mmcode' => 0,
						':ac1_legal_num' => isset($Data['spm_cardcode']) ? $Data['spm_cardcode'] : NULL,
						':ac1_codref' => 1,
						':business' => $Data['business'],
						':branch' => $Data['branch']
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
							'mensaje'	=> 'No se pudo registrar el pago realizado, occurio un error al insertar el detalle del asiento cuenta tercero'
						);

						$this->response($respuesta);

						return;
					}
				}
			} else {

				//SE AGREGA ASIENTO A CUENTA DE LA LINEA DE ANTICIPO

				foreach ($DetalleConsolidadoAsientoCuentaTercero as $key => $posicion) {
					$TotalPagoRecibido = 0;
					$TotalPagoRecibidoOriginal = 0;
					$TotalDiferencia = 0;
					$cuenta = 0;
					$cuentaLinea = 0;
					$docentry = 0;
					$doctype = 0;
					$fechaDocumento = '';
					$TasaOld = 0;
					$DireferenciaCambio = 0; // SOLO SE USA PARA SABER SI EXISTE UNA DIFERENCIA DE CAMBIO
					$CuentaDiferenciaCambio = 0;

					foreach ($posicion as $key => $value) {

						$TotalPagoRecibido = ($TotalPagoRecibido + $value->pm1_vlrpaid);


						$docentry = $value->pm1_docentry;
						$doctype  = $value->pm1_doctype;
						$cuenta   = $value->cuentaNaturaleza;
						$fechaDocumento = $value->pm1_docdate;
						$cuentaLinea = $value->cuentalinea;
					}


					$debito = 0;
					$credito = 0;
					$MontoSysDB = 0;
					$MontoSysCR = 0;

					$TotalPagoRecibidoOriginal = $TotalPagoRecibido;

					if (trim($Data['spm_currency']) != $MONEDALOCAL) {
						$TotalPagoRecibido = ($TotalPagoRecibido * $TasaDocLoc);
					}

					switch ($cuenta) {
						case 1:
							$debito = $TotalPagoRecibido;

							if (trim($Data['spm_currency']) != $MONEDASYS) {

								$MontoSysDB = ($debito / $TasaLocSys);
							} else {

								$MontoSysDB = $TotalPagoRecibidoOriginal;
							}

							break;

						case 2:
							$debito = $TotalPagoRecibido;
							if (trim($Data['spm_currency']) != $MONEDASYS) {

								$MontoSysDB = ($debito / $TasaLocSys);
							} else {

								$MontoSysDB = $TotalPagoRecibidoOriginal;
							}
							break;

						case 3:
							$debito = $TotalPagoRecibido;
							if (trim($Data['spm_currency']) != $MONEDASYS) {

								$MontoSysDB = ($debito / $TasaLocSys);
							} else {

								$MontoSysDB = $TotalPagoRecibidoOriginal;
							}
							break;

						case 4:
							$debito = $TotalPagoRecibido;
							if (trim($Data['spm_currency']) != $MONEDASYS) {

								$MontoSysDB = ($debito / $TasaLocSys);
							} else {

								$MontoSysDB = $TotalPagoRecibidoOriginal;
							}
							break;

						case 5:
							$debito = $TotalPagoRecibido;
							if (trim($Data['spm_currency']) != $MONEDASYS) {

								$MontoSysDB = ($debito / $TasaLocSys);
							} else {

								$MontoSysDB = $TotalPagoRecibidoOriginal;
							}
							break;

						case 6:
							$debito = $TotalPagoRecibido;
							if (trim($Data['spm_currency']) != $MONEDASYS) {

								$MontoSysDB = ($debito / $TasaLocSys);
							} else {

								$MontoSysDB = $TotalPagoRecibidoOriginal;
							}
							break;

						case 7:
							$debito = $TotalPagoRecibido;
							if (trim($Data['spm_currency']) != $MONEDASYS) {

								$MontoSysDB = ($debito / $TasaLocSys);
							} else {

								$MontoSysDB = $TotalPagoRecibidoOriginal;
							}
							break;
					}

					$DFPC  = $DFPC + round($credito, $DECI_MALES);
					$DFPD  = $DFPD + round($debito, $DECI_MALES);
					$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
					$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);

					// SE AGREGA AL BALANCE
					if ( $debito > 0 ) {
						$BALANCE = $this->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuentaLinea, 1, $Data['spm_docdate'], $Data['business'], $Data['branch']);
					}else{
						$BALANCE = $this->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuentaLinea, 2, $Data['spm_docdate'], $Data['business'], $Data['branch']);
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
						':ac1_account' => $cuentaLinea,
						':ac1_debit' => round($debito, $DECI_MALES),
						':ac1_credit' => round($credito, $DECI_MALES),
						':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
						':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
						':ac1_currex' => 0,
						':ac1_doc_date' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
						':ac1_doc_duedate' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resInsert,
						':ac1_font_line' => 1,
						':ac1_font_type' => 33,
						':ac1_accountvs' => 1,
						':ac1_doctype' => 18,
						':ac1_ref1' => "",
						':ac1_ref2' => "",
						':ac1_ref3' => "",
						':ac1_prc_code' => 0,
						':ac1_uncode' => 0,
						':ac1_prj_code' => isset($Data['spm_project']) ? $Data['spm_project'] : NULL,
						':ac1_rescon_date' => NULL,
						':ac1_recon_total' => 0,
						':ac1_made_user' => isset($Data['spm_createby']) ? $Data['spm_createby'] : NULL,
						':ac1_accperiod' => $periodo['data'],
						':ac1_close' => 0,
						':ac1_cord' => 0,
						':ac1_ven_debit' => round($debito, $DECI_MALES),
						':ac1_ven_credit' => round($credito, $DECI_MALES),
						':ac1_fiscal_acct' => 0,
						':ac1_taxid' => 1,
						':ac1_isrti' => 0,
						':ac1_basert' => 0,
						':ac1_mmcode' => 0,
						':ac1_legal_num' => isset($Data['spm_cardcode']) ? $Data['spm_cardcode'] : NULL,
						':ac1_codref' => 1,
						':business' => $Data['business'],
						':branch' => $Data['branch']
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
							'mensaje'	=> 'No se pudo registrar el pago realizado, occurio un error al insertar el detalle del asiento cuenta tercero'
						);

						$this->response($respuesta);

						return;
					}
				}
				//FIN DEL PROCESO PARA AGREGAR ASIENTO A CUENTA DE LA LINEA DE ANTICIPO
			}


			if (trim($Data['spm_currency']) != $MONEDALOCAL) {

				if ($Data['spm_billpayment'] == '0' || $Data['spm_billpayment'] == 0) {
					//se verifica si existe diferencia en cambio
					$sqlCuentaDiferenciaCambio = "SELECT pge_acc_dcp, pge_acc_dcn FROM pgem";
					$resCuentaDiferenciaCambio = $this->pedeo->queryTable($sqlCuentaDiferenciaCambio, array());

					$CuentaDiferenciaCambio = [];

					if (isset($resCuentaDiferenciaCambio[0])) {

						$CuentaDiferenciaCambio = $resCuentaDiferenciaCambio[0];
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' => 'No se encontro la cuenta para aplicar la diferencia en cambio'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}



					$VlrDiff = ($VlrDiff + $VlrPagoEfectuado);


					if ($VlrDiff  <  0) {

						$VlrDiffP = abs($VlrDiff);
					} else if ($VlrDiff > 0) {

						$VlrDiffN = abs($VlrDiff);
					} else if ($VlrDiff  == 0) {

						$VlrDiffN = 0;
						$VlrDiffP = 0;
					}


					if ($VlrDiffP > 0) {


						$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcp'];
						$credito    = $VlrDiffP;
						$MontoSysCR = ($credito / $TasaLocSys);

						$DFPC  = $DFPC + round($VlrDiffP, $DECI_MALES);
						$DFPD  = $DFPD + 0;
						$DFPCS = $DFPCS + round($MontoSysCR, $DECI_MALES);
						$DFPDS = $DFPDS + 0;


						// SE AGREGA AL BALANCE
						
						$BALANCE = $this->account->addBalance($periodo['data'], round($VlrDiffP, $DECI_MALES), $cuentaLinea, 2, $Data['spm_docdate'], $Data['business'], $Data['branch']);
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
							':ac1_account' => $cuentaD,
							':ac1_debit' => 0,
							':ac1_credit' => round($VlrDiffP, $DECI_MALES),
							':ac1_debit_sys' => 0,
							':ac1_credit_sys' => 0,
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => 33,
							':ac1_accountvs' => 1,
							':ac1_doctype' => 18,
							':ac1_ref1' => "",
							':ac1_ref2' => "",
							':ac1_ref3' => "",
							':ac1_prc_code' => 0,
							':ac1_uncode' => 0,
							':ac1_prj_code' => isset($Data['spm_project']) ? $Data['spm_project'] : NULL,
							':ac1_rescon_date' => NULL,
							':ac1_recon_total' => 0,
							':ac1_made_user' => isset($Data['spm_createby']) ? $Data['spm_createby'] : NULL,
							':ac1_accperiod' => $periodo['data'],
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => 0,
							':ac1_ven_credit' => 0,
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => 1,
							':ac1_isrti' => 0,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['spm_cardcode']) ? $Data['spm_cardcode'] : NULL,
							':ac1_codref' => 1,
							':business' => $Data['business'],
							':branch' => $Data['branch']
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
								'mensaje'	=> 'No se pudo registrar el pago realizado, occurio un error al insertar el detalle del asiento diferencia en cambio'
							);

							$this->response($respuesta);

							return;
						}
					}

					if ($VlrDiffN > 0) {


						$cuentaD    = $CuentaDiferenciaCambio['pge_acc_dcn'];
						$debito     =  $VlrDiffN;
						$MontoSysDB = ($debito / $TasaLocSys);


						$DFPC  = $DFPC + 0;
						$DFPD  = $DFPD + round($VlrDiffN, $DECI_MALES);
						$DFPCS = $DFPCS + 0;
						$DFPDS = $DFPDS + round($MontoSysDB, $DECI_MALES);

						// SE AGREGA AL BALANCE

						$BALANCE = $this->account->addBalance($periodo['data'], round($VlrDiffN, $DECI_MALES), $cuentaD, 1, $Data['spm_docdate'], $Data['business'], $Data['branch']);
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
							':ac1_account' => $cuentaD,
							':ac1_debit' =>  round($VlrDiffN, $DECI_MALES),
							':ac1_credit' => 0,
							':ac1_debit_sys' => 0,
							':ac1_credit_sys' => 0,
							':ac1_currex' => 0,
							':ac1_doc_date' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
							':ac1_doc_duedate' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resInsert,
							':ac1_font_line' => 1,
							':ac1_font_type' => 33,
							':ac1_accountvs' => 1,
							':ac1_doctype' => 18,
							':ac1_ref1' => "",
							':ac1_ref2' => "",
							':ac1_ref3' => "",
							':ac1_prc_code' => 0,
							':ac1_uncode' => 0,
							':ac1_prj_code' => isset($Data['spm_project']) ? $Data['spm_project'] : NULL,
							':ac1_rescon_date' => NULL,
							':ac1_recon_total' => 0,
							':ac1_made_user' => isset($Data['spm_createby']) ? $Data['spm_createby'] : NULL,
							':ac1_accperiod' => $periodo['data'],
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => 0,
							':ac1_ven_credit' => 0,
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => 1,
							':ac1_isrti' => 0,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => isset($Data['spm_cardcode']) ? $Data['spm_cardcode'] : NULL,
							':ac1_codref' => 1,
							':business' => $Data['business'],
							':branch' => $Data['branch']
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
								'mensaje'	=> 'No se pudo registrar el pago realizado, occurio un error al insertar el detalle del asiento diferencia en cambio'
							);

							$this->response($respuesta);

							return;
						}
					}
				}
				//
			}



			//VALIDANDDO DIFERENCIA EN PESO DE MONEDA DE SISTEMA

			$sqlDiffPeso = "SELECT sum(coalesce(ac1_debit_sys,0)) as debito, sum(coalesce(ac1_credit_sys,0)) as credito,
													sum(coalesce(ac1_debit,0)) as ldebito, sum(coalesce(ac1_credit,0)) as lcredito
													from mac1
													where ac1_trans_id = :ac1_trans_id";

			$resDiffPeso = $this->pedeo->queryTable($sqlDiffPeso, array(
				':ac1_trans_id' => $resInsertAsiento
			));

			if (isset($resDiffPeso[0]['debito']) && abs(($resDiffPeso[0]['debito'] - $resDiffPeso[0]['credito'])) > 0) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array());

				if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {
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

				$debito  = $resDiffPeso[0]['debito'];
				$credito = $resDiffPeso[0]['credito'];

				if ($debito > $credito) {
					$credito = abs(($debito - $credito));
					$debito = 0;
				} else {
					$debito = abs(($credito - $debito));
					$credito = 0;
				}

				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
					':ac1_debit' => 0,
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($debito, $DECI_MALES),
					':ac1_credit_sys' => round($credito, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => 33,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => 0,
					':ac1_uncode' => 0,
					':ac1_prj_code' => isset($Data['spm_project']) ? $Data['spm_project'] : NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['spm_createby']) ? $Data['spm_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 1,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['spm_cardcode']) ? $Data['spm_cardcode'] : NULL,
					':ac1_codref' => 1,
					':business' => $Data['business'],
					':branch' => $Data['branch']
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
						'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento diferencia en cambio'
					);

					$this->response($respuesta);

					return;
				}
			} else if (isset($resDiffPeso[0]['ldebito']) && abs(($resDiffPeso[0]['ldebito'] - $resDiffPeso[0]['lcredito'])) > 0) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array());

				if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {
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

				$ldebito  = $resDiffPeso[0]['ldebito'];
				$lcredito = $resDiffPeso[0]['lcredito'];

				if ($ldebito > $lcredito) {
					$lcredito = abs(($ldebito - $lcredito));
					$ldebito = 0;
				} else {
					$ldebito = abs(($lcredito - $ldebito));
					$lcredito = 0;
				}

				// SE AGREGA AL BALANCE
				if ( $ldebito > 0 ){
					$BALANCE = $this->account->addBalance($periodo['data'], round($ldebito, $DECI_MALES), $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], 1, $Data['spm_docdate'], $Data['business'], $Data['branch']);
				}else{
					$BALANCE = $this->account->addBalance($periodo['data'], round($lcredito, $DECI_MALES), $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'], 2, $Data['spm_docdate'], $Data['business'], $Data['branch']);
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
					':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
					':ac1_debit' => round($ldebito, $DECI_MALES),
					':ac1_credit' => round($lcredito, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['spm_docdate']) ? $Data['spm_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => 33,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => 0,
					':ac1_uncode' => 0,
					':ac1_prj_code' => isset($Data['spm_project']) ? $Data['spm_project'] : NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['spm_createby']) ? $Data['spm_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 1,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['spm_cardcode']) ? $Data['spm_cardcode'] : NULL,
					':ac1_codref' => 1,
					':business' => $Data['business'],
					':branch' => $Data['branch']
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
						'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento diferencia en cambio'
					);

					$this->response($respuesta);

					return;
				}
			}
		
			//Esto es para validar el resultado de la contabilidad
			// $sqlmac1 = "SELECT * FROM  mac1 order by ac1_line_num desc limit 6";
			// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array());
			// print_r(json_encode($ressqlmac1));
			// exit;


			//SE VALIDA LA CONTABILIDAD CREADA
			$validateCont = $this->generic->validateAccountingAccent($resInsertAsiento);


			if (isset($validateCont['error']) && $validateCont['error'] == false) {
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' 	 => '',
					'mensaje' => $validateCont['mensaje']
				);

				$this->response($respuesta);

				return;
			}
			//


			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Pago registrado con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar el pago'
			);
		}

		$this->response($respuesta);
	}

	public function getBulkPayments_get()
	{

		$Data = $this->get();

		$sqlSelect = "SELECT * FROM tspm";


		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

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
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	public function getBulkPaymentsDetail_get()
	{

		$Data = $this->get();

		if (!isset($Data['pm1_docnum'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT spm1.*, mdt_docname as pm1_docname FROM spm1 inner join dmdt on pm1_doctype = dmdt.mdt_doctype where pm1_docnum = :pm1_docnum";


		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":pm1_docnum" => $Data['pm1_docnum']));

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
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}


	public function getFileTxt_post(){

		$Data = $this->post();

		if (!isset($Data['spm_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		// SE BUSCA EL DETALLE DEL PAGO MASIVO

		$sqlDetalle = "SELECT * FROM spm1 WHERE pm1_docnum = :pm1_docnum";
		$resDetalle = $this->pedeo->queryTable($sqlDetalle, array(
			":pm1_docnum" => $Data['spm_docentry']
		));

		if (isset($resDetalle[0])) {

			$ComplementoBancolombia = new \stdClass();
            $DatosBeneficiario = new \stdClass();
            $MONTOTOTAL = 0;
            $DETAIL = "";
            $HEADER = "";

			foreach ($resDetalle as $key => $detail) {

				// SE FORMA EL DETALLE DEL ARCHIVO PLANO
				
				if ( $detail['pm1_doctype'] == 15 ) {

					// DATOS DEL SOCIO
					$sqlSocio = "SELECT 
					bti_codepab as tipo_documento,
					dmb_trans_type as tipo_trasaccion,
					dmb_num_acc as numero_cuenta,
					dmb_bank as codigo_banco,
					dms_card_code as identificacion_cliente,
					dms_email as correo,
					dms_cel  as celular,
					dms_card_name as nombre_proveedor
					FROM DMSN 
					inner join dmsb on dmb_card_code = dms_card_code and dmb_card_type = dms_card_type and dmb_major = 1
					inner join tbti on bti_id = dms_id_type 
					WHERE dms_card_code = :dms_card_code AND dms_card_type = :dms_card_type";

					$resSocio = $this->pedeo->queryTable($sqlSocio, array(
						":dms_card_code" => $detail['pm1_cardcode'],
						":dms_card_type" => '2'
					));
			
					if (isset($resSocio[0])) {

						$MONTOPAGAR = round($detail['pm1_vlrpaid'], 2);
					
						$DECI = explode("," , $MONTOPAGAR);
	
						if ( isset($DECIMALES[1]) ){
	
							$MONTOPAGAR = $DECI[0];
							$DECI  = $DECI[1];
	
						}else {
							$MONTOPAGAR = $DECI[0];
							$DECI  = "00";
						}
	
						$MONTOPAGAR = str_pad($MONTOPAGAR, 15, 0, STR_PAD_LEFT);
	
						$MONTOPAGAR = $MONTOPAGAR.$DECI;
	
						$DatosBeneficiario->TipoRegistro = 6;
						$DatosBeneficiario->NitBeneficiario = str_pad($resSocio[0]['identificacion_cliente'], 15, " ", STR_PAD_RIGHT);
						$DatosBeneficiario->Nombre = str_pad(substr($resSocio[0]['nombre_proveedor'], 0, 30), 30, " ", STR_PAD_RIGHT); ;
						$DatosBeneficiario->CodigoBancoDestino = str_pad($resSocio[0]['codigo_banco'], 9, 0, STR_PAD_LEFT); // FALTA EL BANCO
						$DatosBeneficiario->NumeroCuentaBeneficiario = str_pad($resSocio[0]['numero_cuenta'], 17, " ", STR_PAD_RIGHT); // FALTA LA CUENTA DE BANCO
						$DatosBeneficiario->IndicadorLugarPago = "S"; // FALTA EL INDICADOR DEL PAGO
						$DatosBeneficiario->TipoTrasaccion = $resSocio[0]['tipo_trasaccion']; // FALTA EL TIPO DE TRANSACCION
						$DatosBeneficiario->ValorTrasaccion = $MONTOPAGAR;
						$DatosBeneficiario->FechaAplicacion = str_replace('-','', $Data['spm_applidate']);
						$DatosBeneficiario->Referencia = str_pad($Data['spm_reference'], 21, " ", STR_PAD_RIGHT); // FALTA LA REFERENCIA
						$DatosBeneficiario->TipoDoc = $resSocio[0]['tipo_documento']; // TIPO DE DOCUMENTO
						$DatosBeneficiario->OficEntrega = str_pad(0, 5, '0', STR_PAD_LEFT); // OFICINA DE ENTREGA
						$DatosBeneficiario->Fax = str_pad("", 15, " ", STR_PAD_RIGHT); // FAX
						$DatosBeneficiario->mail = str_pad($resSocio[0]['correo'], 80, " ", STR_PAD_RIGHT); //
	
	
						$DETAIL .= $DatosBeneficiario->TipoRegistro.$DatosBeneficiario->NitBeneficiario.$DatosBeneficiario->Nombre
						.$DatosBeneficiario->CodigoBancoDestino.$DatosBeneficiario->NumeroCuentaBeneficiario.$DatosBeneficiario->IndicadorLugarPago
						.$DatosBeneficiario->TipoTrasaccion.$DatosBeneficiario->ValorTrasaccion.$DatosBeneficiario->FechaAplicacion
						.$DatosBeneficiario->Referencia.$DatosBeneficiario->TipoDoc.$DatosBeneficiario->OficEntrega.$DatosBeneficiario->Fax
						.$DatosBeneficiario->mail."\n";
	
	
						$MONTOTOTAL = ( $MONTOTOTAL + $detail['pm1_vlrpaid']);

					}
				}

			}


			if ($DETAIL == ""){

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'Sin datos para procesar'
				);

				return $this->response($respuesta);
			}


			//DATOS PARA ENCABEZADO DEL ARCHIVO TEXTO
			$MONTOTOTAL = round( $MONTOTOTAL, 2 );
                        
			$DECIMALES = explode("," , $MONTOTOTAL);

			if ( isset($DECIMALES[1]) ){

				$MONTOTOTAL = $DECIMALES[0];
				$DECIMALES  = $DECIMALES[1];

			}else {
				$MONTOTOTAL = $DECIMALES[0];
				$DECIMALES  = "00";
			}



			$MONTOTOTAL  = str_pad( $MONTOTOTAL, 15, 0, STR_PAD_LEFT );
		   

			$MONTOTOTAL = $MONTOTOTAL.$DECIMALES;


			$sqlEmpresa = "SELECT pge_id_soc, LEFT(pge_name_soc,30) as pge_name_soc FROM pgem WHERE pge_id = :pge_id";

			$resEmpresa = $this->pedeo->queryTable($sqlEmpresa, array(

				":pge_id" => $Data['business']

			));


			if (!isset($resEmpresa[0])){
				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encontraron los datos de la empresa'
				);

				return $this->response($respuesta);
			}
			

			$sec = date('Y-m-d H:i:s');


			$sec = str_replace(":","",$sec);
			$sec = str_replace("-","",$sec);
			$sec = str_replace("-","",$sec);
			
			$ComplementoBancolombia->TipoRegistro = 1; // EL TIPO DE REGISTRO SIEMPRE VA EN 1 VERIFICAR DE TODAS FORMAS
			$ComplementoBancolombia->NitFondeador = $resEmpresa[0]['pge_id_soc'];
			$ComplementoBancolombia->Nombre = substr($resEmpresa[0]['pge_name_soc'], 0, 30);
			$ComplementoBancolombia->Aplicacion = $Data['spm_application']; // FALTA LA APLICACION
			$ComplementoBancolombia->Filler15 = str_pad(" ", 15, " ", STR_PAD_LEFT);
			$ComplementoBancolombia->ClaseTransaccion = $Data['spm_paytype']; // FALTA LA CLASE DE TRANSACCION
			$ComplementoBancolombia->Descripcion = str_pad($Data['spm_reference'], 10, " ", STR_PAD_RIGHT); // FALTA DESCRIPCION DEL PAGO
			$ComplementoBancolombia->FechaTrasicion = str_replace('-','', $Data['spm_applidate']);
			$ComplementoBancolombia->SecuenciaEnvio = 'A1'; 
			$ComplementoBancolombia->FechaCreacion = str_replace('-','', $Data['spm_createdate']);
			$ComplementoBancolombia->CantidadRegistros = str_pad(count($resDetalle), 6, 0, STR_PAD_LEFT); 
			$ComplementoBancolombia->SumatoriaDebitos = str_pad(0, 17, 0, STR_PAD_LEFT);
			$ComplementoBancolombia->SumatoriaCreditos = $MONTOTOTAL;
			$ComplementoBancolombia->NumeroCuentaFondeador =  str_pad($Data['spm_account'], 11, 0, STR_PAD_LEFT); // FALTA EL NUMERO DE CUENTA
			$ComplementoBancolombia->TipoCuentaFondeador = $Data['spm_typeacc'];  // FALTA EL TIPO DE CUENTA AHORROS CORRIENTE
			$ComplementoBancolombia->Filler149 = str_pad("", 149, " ", STR_PAD_LEFT);


			// FORMAR ENCABEZADO DE ARCHIVO


			$fileName = $Data['business'].'_'.date('Y-m-d').' PAB.txt';
			$HEADER = "";

			$HEADER =  $ComplementoBancolombia->TipoRegistro.str_pad($ComplementoBancolombia->NitFondeador, 15, 0, STR_PAD_LEFT)
			.$ComplementoBancolombia->Aplicacion.$ComplementoBancolombia->Filler15.$ComplementoBancolombia->ClaseTransaccion
			.$ComplementoBancolombia->Descripcion.$ComplementoBancolombia->FechaTrasicion.$ComplementoBancolombia->SecuenciaEnvio
			.$ComplementoBancolombia->FechaCreacion.$ComplementoBancolombia->CantidadRegistros.$ComplementoBancolombia->SumatoriaDebitos
			.$ComplementoBancolombia->SumatoriaCreditos.$ComplementoBancolombia->NumeroCuentaFondeador.$ComplementoBancolombia->TipoCuentaFondeador
			.$ComplementoBancolombia->Filler149."\n";


			$content = $HEADER.$DETAIL;


			force_download($fileName, $content);



		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'Sin datos para procesar'
			);
		}


		$this->response($respuesta);

	}


    private function validateDate($fecha)
	{
		if (strlen($fecha) == 10 or strlen($fecha) > 10) {
			return true;
		} else {
			return false;
		}
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
}