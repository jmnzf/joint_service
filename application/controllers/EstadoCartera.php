<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class EstadoCartera extENDs REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    	$this->load->library('pedeo', [$this->pdo]);
		$this->load->library('DateFormat');
		$this->load->library('generic');

	}


	public function EstadoCartera_post(){

		$DECI_MALES =  $this->generic->getDecimals();

    	$Data = $this->post();
		$totalfactura = 0;

		$formatter = new NumeroALetras();

        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch','orientation' => 'L']);

		//RUTA DE CARPETA EMPRESA
        $company = $this->pedeo->queryTable("SELECT main_folder company FROM PARAMS",array());

        if(!isset($company[0])){
			$respuesta = array(
	           'error' => true,
	           'data'  => $company,
	           'mensaje' =>'no esta registrada la ruta de la empresa'
		    );

          	$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          	return;
		}

        //INFORMACION DE LA EMPRESA

		$empresa = $this->pedeo->queryTable("SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
													pge_cou_soc, CONCAT(pge_id_type,' ',pge_id_soc) AS pge_id_type , pge_web_site, pge_logo,
													CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
													pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type
													FROM pgem WHERE pge_id = :pge_id", array(':pge_id' => $Data['business']));

		if(!isset($empresa[0])){
			$respuesta = array(
	           'error' => true,
	           'data'  => $empresa,
	           'mensaje' =>'no esta registrada la información de la empresa'
	        );

          	$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	        return;
		}

		$grupo = "";
		if ( isset($Data['groupsn']) && $Data['groupsn'] != 0 ){
			$grupo = " AND dmsn.dms_group_num::numeric = ".$Data['groupsn']."::numeric";
		}

		$sqlestadocuenta = "SELECT distinct
		dmdt.mdt_docname,
		mac1.ac1_font_key,
		mac1.ac1_legal_num as CodigoCliente,
		dmsn.dms_card_name NombreCliente,
		mac1.ac1_account as cuenta,
		dvfv.dvf_currency monedadocumento,
		'".$Data['fecha']."' fechacorte,
		'".$Data['fecha']."' - dvf_duedate dias,
		dvfv.dvf_comment,
		dvfv.dvf_currency,
		mac1.ac1_font_key as dvf_docentry,
		dvfv.dvf_docnum,
		dvfv.dvf_docdate as FechaDocumento,
		dvfv.dvf_duedate as FechaVencimiento,
		dvf_docnum as NumeroDocumento,
		mac1.ac1_font_type as numtype,
		mdt_docname as tipo,
		case
		when mac1.ac1_font_type = 5 then get_dynamic_conversion(:currency,get_localcur(),dvf_docdate,mac1.ac1_debit
		,get_localcur())
		else get_dynamic_conversion(:currency,get_localcur(),dvf_docdate,mac1.ac1_credit ,get_localcur())
		end as totalfactura,
		get_dynamic_conversion(:currency,get_localcur(),dvf_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
		,get_localcur()) as saldo,
		'' retencion,
		get_tax_currency(dvfv.dvf_currency,dvfv.dvf_docdate) as tasa_dia,
		CASE
		WHEN '".$Data['fecha']."' <= dvfv.dvf_duedate THEN get_dynamic_conversion(:currency, get_localcur(), dvf_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS sin_vencer,
		CASE
		WHEN '".$Data['fecha']."' > dvfv.dvf_duedate AND '".$Data['fecha']."' <= dvfv.dvf_duedate + INTERVAL '30 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), dvf_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS uno_treinta,
		CASE
		WHEN '".$Data['fecha']."' > dvfv.dvf_duedate + INTERVAL '30 DAY' AND '".$Data['fecha']."' <= dvfv.dvf_duedate + INTERVAL '60 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), dvf_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS treinta_uno_secenta,
		CASE
		WHEN '".$Data['fecha']."' > dvfv.dvf_duedate + INTERVAL '60 DAY' AND '".$Data['fecha']."' <= dvfv.dvf_duedate + INTERVAL '90 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), dvf_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS secenta_uno_noventa,
		CASE
		WHEN '".$Data['fecha']."' > dvfv.dvf_duedate + INTERVAL '90 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), dvf_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS mayor_noventa,
		'' as comentario_asiento
		from mac1
		inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
		inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
		inner join dvfv on dvfv.dvf_doctype = mac1.ac1_font_type and dvfv.dvf_docentry = mac1.ac1_font_key
		inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
		where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ) > 0 and dmsn.dms_card_type = '1'
		".$grupo."
		and dvf_docdate <= '".$Data['fecha']."'
		union all
		select distinct
		dmdt.mdt_docname,
		mac1.ac1_font_key,
		mac1.ac1_legal_num as CodigoCliente,
		dmsn.dms_card_name NombreCliente,
		mac1.ac1_account as cuenta,
		gbpr.bpr_currency monedadocumento,
		'".$Data['fecha']."' fechacorte,
		'".$Data['fecha']."' - gbpr.bpr_docdate as dias,
		gbpr.bpr_comments as bpr_comment,
		gbpr.bpr_currency,
		mac1.ac1_font_key as dvf_docentry,
		gbpr.bpr_docnum,
		gbpr.bpr_docdate as FechaDocumento,
		gbpr.bpr_docdate as FechaVencimiento,
		gbpr.bpr_docnum as NumeroDocumento,
		mac1.ac1_font_type as numtype,
		mdt_docname as tipo,
		get_dynamic_conversion(:currency,bpr_currency,bpr_docdate,gbpr.bpr_doctotal ,get_localcur()) as
		totalfactura,
		get_dynamic_conversion(:currency,get_localcur(),bpr_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
		,get_localcur()) as saldo,
		'' retencion,
		get_tax_currency(gbpr.bpr_currency,gbpr.bpr_docdate) as tasa_dia,
		CASE
		WHEN '".$Data['fecha']."' <= gbpr.bpr_docdate THEN get_dynamic_conversion(:currency, get_localcur(), bpr_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS sin_vencer,
		CASE
		WHEN '".$Data['fecha']."' > gbpr.bpr_docdate AND '".$Data['fecha']."' <= gbpr.bpr_docdate + INTERVAL '30 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), bpr_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS uno_treinta,
		CASE
		WHEN '".$Data['fecha']."' > gbpr.bpr_docdate + INTERVAL '30 DAY' AND '".$Data['fecha']."' <= gbpr.bpr_docdate + INTERVAL '60 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), bpr_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS treinta_uno_secenta,
		CASE
		WHEN '".$Data['fecha']."' > gbpr.bpr_docdate + INTERVAL '60 DAY' AND '".$Data['fecha']."' <= gbpr.bpr_docdate + INTERVAL '90 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), bpr_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS secenta_uno_noventa,
		CASE
		WHEN '".$Data['fecha']."' > gbpr.bpr_docdate + INTERVAL '90 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), bpr_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS mayor_noventa,
		'' as comentario_asiento
		from mac1
		inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
		inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
		inner join gbpr on gbpr.bpr_doctype = mac1.ac1_font_type and gbpr.bpr_docentry = mac1.ac1_font_key
		inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
		where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0 and dmsn.dms_card_type = '1'
		".$grupo."
		and bpr_docdate <= '".$Data['fecha']."'
		union all
		select distinct
		dmdt.mdt_docname,
		mac1.ac1_font_key,
		mac1.ac1_legal_num as CodigoCliente,
		dmsn.dms_card_name NombreCliente,
		mac1.ac1_account as cuenta,
		dvnc.vnc_currency monedadocumento,
		'".$Data['fecha']."' fechacorte,
		'".$Data['fecha']."' - dvnc.vnc_docdate as dias,
		dvnc.vnc_comment as bpr_comment,
		dvnc.vnc_currency,
		mac1.ac1_font_key as dvf_docentry,
		dvnc.vnc_docnum,
		dvnc.vnc_docdate as FechaDocumento,
		dvnc.vnc_duedate as FechaVencimiento,
		dvnc.vnc_docnum as NumeroDocumento,
		mac1.ac1_font_type as numtype,
		mdt_docname as tipo,
		case
		when mac1.ac1_font_type = dvnc.vnc_doctype then
		get_dynamic_conversion(:currency,get_localcur(),vnc_docdate,mac1.ac1_debit,get_localcur())
		else get_dynamic_conversion(:currency,get_localcur(),vnc_docdate,mac1.ac1_credit,get_localcur())
		end * -1 as totalfactura,
		(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo,
		'' retencion,
		get_tax_currency(dvnc.vnc_currency,dvnc.vnc_docdate) as tasa_dia,
		CASE
			WHEN '".$Data['fecha']."' <= dvnc.vnc_duedate THEN get_dynamic_conversion(:currency, get_localcur(), vnc_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
			ELSE 0
		END AS sin_vencer,
		CASE
			WHEN '".$Data['fecha']."' > dvnc.vnc_duedate AND '".$Data['fecha']."' <= dvnc.vnc_duedate + INTERVAL '30 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vnc_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
			ELSE 0
		END AS uno_treinta,
		CASE
			WHEN '".$Data['fecha']."' > dvnc.vnc_duedate + INTERVAL '30 DAY' AND '".$Data['fecha']."' <= dvnc.vnc_duedate + INTERVAL '60 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vnc_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
			ELSE 0
		END AS treinta_uno_secenta,
		CASE
			WHEN '".$Data['fecha']."' > dvnc.vnc_duedate + INTERVAL '60 DAY' AND '".$Data['fecha']."' <= dvnc.vnc_duedate + INTERVAL '90 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vnc_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
			ELSE 0
		END AS secenta_uno_noventa,
		CASE
			WHEN '".$Data['fecha']."' > dvnc.vnc_duedate + INTERVAL '90 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vnc_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
			ELSE 0
		END AS mayor_noventa,
		'' comentario_asiento
		from mac1
		inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
		inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
		inner join dvnc on dvnc.vnc_doctype = mac1.ac1_font_type and dvnc.vnc_docentry =
		mac1.ac1_font_key
		inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
		where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0 and dmsn.dms_card_type = '1'
		".$grupo."
		and vnc_docdate <= '".$Data['fecha']."'
		union all
		select distinct
		dmdt.mdt_docname,
		mac1.ac1_font_key,
		mac1.ac1_legal_num as CodigoCliente,
		dmsn.dms_card_name NombreCliente,
		mac1.ac1_account as cuenta,
		dvnd.vnd_currency monedadocumento,
		'".$Data['fecha']."' fechacorte,
		'".$Data['fecha']."' - dvnd.vnd_docdate as dias,
		dvnd.vnd_comment as bpr_comment,
		dvnd.vnd_currency,
		mac1.ac1_font_key as dvf_docentry,
		dvnd.vnd_docnum,
		dvnd.vnd_docdate as FechaDocumento,
		dvnd.vnd_duedate as FechaVencimiento,
		dvnd.vnd_docnum as NumeroDocumento,
		mac1.ac1_font_type as numtype,
		mdt_docname as tipo,
		case
		when mac1.ac1_font_type = dvnd.vnd_doctype then
		get_dynamic_conversion(:currency,get_localcur(),vnd_docdate,mac1.ac1_debit,get_localcur())
		else
		get_dynamic_conversion(:currency,get_localcur(),vnd_docdate,mac1.ac1_credit,get_localcur())
		end as totalfactura,
		get_dynamic_conversion(:currency,get_localcur(),vnd_docdate,(mac1.ac1_ven_debit) -	(mac1.ac1_ven_credit),get_localcur()) as saldo,
		'' retencion,
		get_tax_currency(dvnd.vnd_currency,dvnd.vnd_docdate) as tasa_dia,
		CASE
			WHEN '".$Data['fecha']."' <= dvnd.vnd_duedate THEN get_dynamic_conversion(:currency, get_localcur(), vnd_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
			ELSE 0
		END AS sin_vencer,
		CASE
			WHEN '".$Data['fecha']."' > dvnd.vnd_duedate AND '".$Data['fecha']."' <= dvnd.vnd_duedate + INTERVAL '30 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vnd_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
			ELSE 0
		END AS uno_treinta,
		CASE
			WHEN '".$Data['fecha']."' > dvnd.vnd_duedate + INTERVAL '30 DAY' AND '".$Data['fecha']."' <= dvnd.vnd_duedate + INTERVAL '60 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vnd_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
			ELSE 0
		END AS treinta_uno_secenta,
		CASE
			WHEN '".$Data['fecha']."' > dvnd.vnd_duedate + INTERVAL '60 DAY' AND '".$Data['fecha']."' <= dvnd.vnd_duedate + INTERVAL '90 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vnd_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
			ELSE 0
		END AS secenta_uno_noventa,
		CASE
			WHEN '".$Data['fecha']."' > dvnd.vnd_duedate + INTERVAL '90 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vnd_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
			ELSE 0
		END AS mayor_noventa,
		'' as comentario_asiento
		from mac1
		inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
		inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
		inner join dvnd on dvnd.vnd_doctype = mac1.ac1_font_type and
		dvnd.vnd_docentry = mac1.ac1_font_key
		inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
		where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ) > 0 and
		dmsn.dms_card_type = '1'
		".$grupo."
		and vnd_docdate <= '".$Data['fecha']."'
		union all
		select
		dmdt.mdt_docname,
		mac1.ac1_font_key,
		case
		when ac1_card_type = '1'
		then mac1.ac1_legal_num
		when ac1_card_type = '2'
		then mac1.ac1_legal_num
		end as codigoproveedor,
		dmsn.dms_card_name NombreCliente,
		mac1.ac1_account as cuenta,
		tmac.mac_currency,
		'".$Data['fecha']."' fechacorte,
		'".$Data['fecha']."' - tmac.mac_doc_duedate dias_atrasado,
		tmac.mac_comments,
		tmac.mac_currency,
		mac_trans_id as dvf_docentry,
		0 as docnum,
		tmac.mac_doc_date as fecha_doc,
		tmac.mac_doc_duedate as fecha_ven,
		mac_trans_id as id_origen,
		18 as numtype,
		mdt_docname as tipo,
		case
		when mac1.ac1_cord = 0
		then
		get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_debit
		,get_localcur())
		when mac1.ac1_cord = 1
		then
		get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_credit
		,get_localcur())
		end as total_doc,
		get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,(mac1.ac1_ven_debit)
		- (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
		'' retencion,
		get_tax_currency(tmac.mac_currency,tmac.mac_doc_date) as tasa_dia,
		CASE
		WHEN '".$Data['fecha']."' <= tmac.mac_doc_duedate THEN get_dynamic_conversion(:currency, get_localcur(), mac_doc_duedate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS sin_vencer,
		CASE
		WHEN '".$Data['fecha']."' > tmac.mac_doc_duedate AND '".$Data['fecha']."' <= tmac.mac_doc_duedate + INTERVAL '30 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), tmac.mac_doc_duedate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS uno_treinta,
		CASE
		WHEN '".$Data['fecha']."' > tmac.mac_doc_duedate + INTERVAL '30 DAY' AND '".$Data['fecha']."' <= tmac.mac_doc_duedate + INTERVAL '60 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), tmac.mac_doc_duedate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS treinta_uno_secenta,
		CASE
		WHEN '".$Data['fecha']."' > tmac.mac_doc_duedate + INTERVAL '60 DAY' AND '".$Data['fecha']."' <= tmac.mac_doc_duedate + INTERVAL '90 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), tmac.mac_doc_duedate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS secenta_uno_noventa,
		CASE
		WHEN '".$Data['fecha']."' > tmac.mac_doc_duedate + INTERVAL '90 DAY' AND '".$Data['fecha']."' <= tmac.mac_doc_duedate + INTERVAL '120 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), tmac.mac_doc_duedate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
		ELSE 0
		END AS mayor_noventa,
		ac1_comments as comentario_asiento
		from mac1
		inner join dacc on mac1.ac1_account = dacc.acc_code and
		acc_businessp = '1'
		inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
		inner join tmac on tmac.mac_trans_id = mac1.ac1_font_key and
		tmac.mac_doctype = mac1.ac1_font_type
		inner join dmsn on mac1.ac1_card_type = dmsn.dms_card_type
		and mac1.ac1_legal_num = dmsn.dms_card_code
		where dmsn.dms_card_type = '1'
		and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
		".$grupo."
		and mac_doc_date <= '".$Data['fecha']."'
		union all
		select
		dmdt.mdt_docname,
		dvrc.vrc_docentry as ac1_font_key,
		dvrc.vrc_cardcode as codigoproveedor,
		dvrc.vrc_cardname as NombreCliente,
		dacc.acc_code as cuenta,
		dvrc.vrc_currency ,
		'".$Data['fecha']."' fechacorte,
		'".$Data['fecha']."' - dvrc.vrc_docdate dias_atrasado,
		dvrc.vrc_comment ,
		dvrc.vrc_currency,
		dvrc.vrc_docentry  as dvf_docentry,
		dvrc.vrc_docnum  as docnum,
		dvrc.vrc_docdate  as fecha_doc,
		dvrc.vrc_duedate  as fecha_ven,
		dvrc.vrc_docnum as id_origen,
		47 as numtype,
		mdt_docname as tipo,
		dvrc.vrc_total_c as  total_doc,
		get_dynamic_conversion(:currency,get_localcur(),dvrc.vrc_docdate, dvrc.vrc_total_c,get_localcur()) as saldo_venc,
		'' retencion,
		get_tax_currency(dvrc.vrc_currency  ,dvrc.vrc_docdate) as tasa_dia,
		CASE
		WHEN '".$Data['fecha']."' <= dvrc.vrc_duedate THEN get_dynamic_conversion(:currency, get_localcur(), vrc_docdate, dvrc.vrc_total_c, get_localcur())
		ELSE 0
		END AS sin_vencer,
		CASE
		WHEN '".$Data['fecha']."' > dvrc.vrc_duedate AND '".$Data['fecha']."' <= dvrc.vrc_duedate + INTERVAL '30 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vrc_docdate, dvrc.vrc_total_c , get_localcur())
		ELSE 0
		END AS uno_treinta,
		CASE
		WHEN '".$Data['fecha']."' > dvrc.vrc_duedate + INTERVAL '30 DAY' AND '".$Data['fecha']."' <= dvrc.vrc_duedate + INTERVAL '60 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vrc_docdate, dvrc.vrc_total_c, get_localcur())
		ELSE 0
		END AS treinta_uno_secenta,
		CASE
		WHEN '".$Data['fecha']."' > dvrc.vrc_duedate + INTERVAL '60 DAY' AND '".$Data['fecha']."' <= dvrc.vrc_duedate + INTERVAL '90 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vrc_docdate, dvrc.vrc_total_c , get_localcur())
		ELSE 0
		END AS secenta_uno_noventa,
		CASE
		WHEN '".$Data['fecha']."' > dvrc.vrc_duedate + INTERVAL '90 DAY' THEN get_dynamic_conversion(:currency, get_localcur(), vrc_docdate, dvrc.vrc_total_c, get_localcur())
		ELSE 0
		END AS mayor_noventa,
		dvrc.vrc_comment as comentario_asiento
		from dvrc
		inner join dmdt on dvrc.vrc_doctype = dmdt.mdt_doctype
		inner join dmsn on trim(dvrc.vrc_cardcode) = trim(dmsn.dms_card_code) 
		inner join dmgs on dmsn.dms_group_num = dmgs.mgs_id::text 
		inner join dacc on dmgs.mgs_acct = dacc.acc_code and acc_businessp = '1'
		inner join responsestatus on dvrc.vrc_docentry = responsestatus.id and dvrc.vrc_doctype = responsestatus.tipo 
		where dvrc.vrc_docdate <= '".$Data['fecha']."'
		".$grupo."
		and responsestatus.estado = 'Abierto'
		and dmsn.dms_card_type = '1'
		order by NombreCliente asc";

		$contenidoestadocuenta = $this->pedeo->queryTable($sqlestadocuenta, array(":currency" => $Data['currency']));

		// print_r($sqlestadocuenta);exit();die();
		// if(!isset($contenidoestadocuenta[0])){
		// 	$respuesta = array(
		// 		 'error' => true,
		// 		 'data'  => $contenidoestadocuenta,
		// 		 'mensaje' =>'No tiene pagos realizados'
		// 	);

		// 	$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		// 	return;
		// }

		if ( isset($Data['formato']) && $Data['formato']  == 'EXCEL') {

			$query = str_replace(":currency", "'".$Data['currency']."'", $sqlestadocuenta);

			$respuesta = array(

				'error' => false,
				'data'  => ['sql' => $query, 'equivalence' => array(
					"mdt_docname"=> "Nombre Documento",
					"codigocliente"=> "Codigo Cliente",
					"nombrecliente"=> "Nombre Cliente",
					"cuenta"=> "Cuenta Contable",
					"monedadocumento"=> "Moneda Documento",
					"fechacorte"=> "Fecha de Corte",
					"dias"=> "Dias",
					"dvf_comment"=> "Comentario",
					"dvf_docnum"=> "Numero de Documento",
					"fechadocumento"=> "Fecha de Documento",
					"fechavencimiento"=> "Fecha de Vencimiento",
					"tipo"=> "Nombre Documento",
					"totalfactura"=> "Valor total del Documento",
					"saldo"=> "Saldo",
					"retencion"=> "Valor Retencion",
					"tasa_dia"=> "Tasa del Dia",
					"sin_vencer"=> "Valor sin Vencer",
					"uno_treinta"=> "Valor a 30 dias",
					"treinta_uno_secenta"=> "Valor 31 a 60 dias",
					"secenta_uno_noventa"=> "Valor 61 a 90 dias",
					"mayor_noventa"=> "Valor mayor a 90 dias",
					"ac1_font_key"=> "Id Documento",
					"dvf_currency"=> "Simbolo Moneda",
					"dvf_docentry"=> "Id Autoincremental",
					"numerodocumento"=> "# Doc",
					"numtype"=> "Id Tipo",
					"comentario_asiento"=> "Comentario Asiento"
				)],
				'mensaje' =>''
			);

			return $this->response($respuesta);
		}

        $cliente = "";
        $encabezado = "";
        $detalle = "";
        $totaldetalle = "";
        $cuerpo = "";
        $cabecera = "";
		$gCliente  = "";
		$hacer = true;


		$total_sin_vencer = 0;
		$total_0_30 = 0;
		$total_30_60 = 0;
		$total_60_90 = 0;
		$total_mayor_90 = 0;
		$monedadocumento = '';

		$contenidoestadocuenta1 = array_map(function($card){
            $temp = array(
                'codigocliente' => $card['codigocliente'],
                'nombrecliente' => $card['nombrecliente'],
                'fechacorte' => $card['fechacorte']
            );
            return $temp;},$contenidoestadocuenta);

        $contenidoestadocuenta1 = array_unique($contenidoestadocuenta1,SORT_REGULAR); // se filtran los cardcode para que no esten repetidos


        foreach ($contenidoestadocuenta1 as $key => $value) {
			// print_r($value['codigocliente']);
			if( trim($cliente) == trim($value['codigocliente']) ){
					$hacer = false;
			}else{
				$cliente = $value['codigocliente'];

				$gCliente = '<th class=""><b>NIT:</b> '.$value['codigocliente'].'</th>
										 <th class=""><b>CLIENTE:</b> '.$value['nombrecliente'].'</th>
										 <th class=""><b>FECHA CORTE:</b> '.$this->dateformat->Date($value['fechacorte']).'</th>

										 ';
				$hacer = true;
			}

			$encabezado = '<table width="100%"><tr>'.$gCliente.'</tr></table>
			<table width="100%" style="vertical-align: bottom; font-family: serif;
					font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
					<tr>
							<th class="fondo">
									<p></p>
							</th>
					</tr>
			</table>';

			$totaldetalle = "";
			$detail_sin_vencer = 0;
			$detail_0_30 = 0;
			$detail_30_60 = 0;
			$detail_60_90 = 0;
			$detail_mayor_90 = 0;

			if($hacer){

				$cabecera = '
									<th class=""><b>Tipo Doc.</b></th>
									<th class=""><b># Doc.</b></th>
									<th class=""><b>F. Doc.</b></th>
									<th class=""><b>T. Doc.</b></th>
									<th class=""><b>F. V. Doc.</b></th>
									<th class=""><b>F. Corte</b></th>
									<th class=""><b>Ref.</b></th>
									<th class=""><b>Dias V.</b></th>
									<th class=""><b>S.V.</b></th>
									<th class=""><b>0-30</b></th>
									<th class=""><b>31-60</b></th>
									<th class=""><b>61-90</b></th>
									<th class=""><b>+90</b></th>';
				foreach ($contenidoestadocuenta as $key => $value1) {

					if( $cliente == $value1['codigocliente']){
						//
						$monedadocumento = $Data['currency'];


						$detalle = '
									<td  style="border-bottom: dotted;padding-top: 10px; width: 18%;">'.$value1['mdt_docname'].'</td>
									<td  style="border-bottom: dotted;padding-top: 10px;">'.$value1['numerodocumento'].'</td>
									<td  style="border-bottom: dotted;padding-top: 10px;">'.$this->dateformat->Date($value1['fechadocumento']).'</td>
									<td  style="border-bottom: dotted;padding-top: 10px;">'.$Data['currency']." ".number_format($value1['totalfactura'], $DECI_MALES, ',', '.').'</td>
									<td  style="border-bottom: dotted;padding-top: 10px;">'.$this->dateformat->Date($value1['fechavencimiento']).'</td>
									<td  style="border-bottom: dotted;padding-top: 10px;">'.$this->dateformat->Date($value['fechacorte']).'</td>
									<td  style="border-bottom: dotted;padding-top: 10px;">'.$value1['comentario_asiento'].'</td>
									<td  style="border-bottom: dotted;padding-top: 10px; width: 5%;">'.$value1['dias'].'</td>
									<td  style="border-bottom: dotted;padding-top: 10px;">'.$Data['currency']." ".number_format($value1['sin_vencer'], $DECI_MALES, ',', '.').'</td>
									<td  style="border-bottom: dotted;padding-top: 10px;">'.$Data['currency']." ".number_format($value1['uno_treinta'], $DECI_MALES, ',', '.').'</td>
									<td  style="border-bottom: dotted;padding-top: 10px;">'.$Data['currency']." ".number_format($value1['treinta_uno_secenta'], $DECI_MALES, ',', '.').'</td>
									<td  style="border-bottom: dotted;padding-top: 10px;">'.$Data['currency']." ".number_format($value1['secenta_uno_noventa'], $DECI_MALES, ',', '.').'</td>
									<td  style="border-bottom: dotted;padding-top: 10px;">'.$Data['currency']." ".number_format($value1['mayor_noventa'], $DECI_MALES, ',', '.').'</td>';



						$totaldetalle .= '<tr>'.$detalle.'</tr>';

						$detail_sin_vencer = $detail_sin_vencer + $value1['sin_vencer'];
						$detail_0_30 =  $detail_0_30 + $value1['uno_treinta'];
 					   	$detail_30_60 =  $detail_30_60 + ($value1['treinta_uno_secenta']);
 						$detail_60_90 =  $detail_60_90 + ($value1['secenta_uno_noventa']);
 						$detail_mayor_90 =  $detail_mayor_90 + ($value1['mayor_noventa']);
					}
				}

				$total_sin_vencer = $total_sin_vencer + $detail_sin_vencer;
				$total_0_30 =  $total_0_30 + $detail_0_30;
			   	$total_30_60 =  $total_30_60 + $detail_30_60;
				$total_60_90 =  $total_60_90 + $detail_60_90;
				$total_mayor_90 =  $total_mayor_90 + $detail_mayor_90;
				// print_r($detail_0_30);exit;
				$detail_total = '
							<tr>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
							<th><b>Total</b></th>
							<th style="width: 10%;" class=""><b>'.$monedadocumento.' '.number_format(($detail_sin_vencer+$detail_0_30+$detail_30_60+$detail_60_90+$detail_mayor_90), $DECI_MALES, ',', '.').'</b></th>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
							<th class=""><b>'.$monedadocumento.' '.number_format($detail_sin_vencer, $DECI_MALES, ',', '.').'</b></th>
							<th class=""><b>'.$monedadocumento.' '.number_format($detail_0_30, $DECI_MALES, ',', '.').'</b></th>
							<th class=""><b>'.$monedadocumento.' '.number_format($detail_30_60, $DECI_MALES, ',', '.').'</b></th>
							<th class=""><b>'.$monedadocumento.' '.number_format($detail_60_90, $DECI_MALES, ',', '.').'</b></th>
							<th class=""><b>'.$monedadocumento.' '.number_format($detail_mayor_90, $DECI_MALES, ',', '.').'</b></th>
							</tr>';

				$cuerpo .= $encabezado."<table width='100%'><thead><tr>".$cabecera."</tr></thead>".$totaldetalle.$detail_total."</table><br><br>";
			}
        }

        // $cuerpo .= '
        // 			<table width="100%">
		// 				<tr>
		// 					<th style="width: 10%;">&nbsp;</th>
		// 					<th style="width: 10%;">&nbsp;</th>
		// 					<th style="width: 10%;">&nbsp;</th>
		// 					<th style="width: 24%;">&nbsp;</th>
		// 					<th><b>Total</th>
		// 					<th class=" centro"><b>'.$monedadocumento.' '.number_format(($total_sin_vencer+$total_0_30+$total_30_60+$total_60_90+$total_mayor_90), $DECI_MALES, ',', '.').'</b></th>
		// 					<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_0_30, $DECI_MALES, ',', '.').'</b></th>
		// 					<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_30_60, $DECI_MALES, ',', '.').'</b></th>
		// 					<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_60_90, $DECI_MALES, ',', '.').'</b></th>
		// 					<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_mayor_90, $DECI_MALES, ',', '.').'</b></th>
		// 					</tr>
		// 			</table>';

        $header = '
        <table width="100%" style="text-align: left;">
	        <tr>
	            <th style="text-align: left;" width ="60"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="185" height ="120"></img></th>
	            <th >
	                <p><b>INFORME ESTADO DE CUENTA CLIENTE</b></p>
	            </th>
				<th>
					&nbsp;
					&nbsp;
				</th>
	        </tr>
        </table>';
		
		$generalTotalTable = "
		<br>
		<div style='width:100%;margin-left:85%;'>
		<table>
			<tbody>
				<tr>
					<th style='text-align:right; width:20%;' ><b>S.V: </b></th>
					<td class='centro' style='text-align: left; white-space: normal; '><b>".$monedadocumento.' '.number_format(($total_sin_vencer+$total_0_30+$total_30_60+$total_60_90+$total_mayor_90), $DECI_MALES, ',', '.')."</b></td>
				</tr>
				<tr>
					<th style='text-align:right;'><b>0-30: </b></th>
					<td class='centro' style='text-align: left; white-space: normal; '><b>".$monedadocumento.' '.number_format($total_0_30, $DECI_MALES, ',', '.')."</b></td>
				</tr>
				<tr>
					<th style='text-align:right;'><b>31-60: </b></th>
					<td class='centro' style='text-align: left; white-space: normal; '><b>".$monedadocumento.' '.number_format($total_30_60, $DECI_MALES, ',', '.')."</b></td>
				</tr>
				<tr>
					<th style='text-align:right;'><b>61-90: </b></th>
					<td class='centro' style='text-align: left; white-space: normal; '><b>".$monedadocumento.' '.number_format($total_60_90, $DECI_MALES, ',', '.')."</b></td>
				</tr>
				<tr>
					<th style='text-align:right;'><b>+90: </b></th>
					<td class='centro' style='text-align: left; white-space: normal; '><b>".$monedadocumento.' '.number_format($total_mayor_90, $DECI_MALES, ',', '.')."</b></td>
				</tr>
				<tr >
					<th style='text-align:right; border-top: 1px solid black;'><b>Total: </b></th>
					<td class='centro' style='text-align: left; white-space: normal; border-top: 1px solid black;'><b>".$monedadocumento.' '.number_format(($total_sin_vencer+$total_0_30+$total_30_60+$total_60_90+$total_mayor_90)+$total_0_30+$total_30_60+$total_60_90+$total_mayor_90 , $DECI_MALES, ',', '.')."</b></td>
				</tr>
			</tbody>
			</table>
		</div>
		";

			$cuerpo.= $generalTotalTable;

        $footer = '<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
			<tr>
				<th  width="33%" style="font-size: 8px;"> Documento generado por <span style="color: #fcc038; font-weight: bolder;"> JOINTERP  </span> para: '.$empresa[0]['pge_small_name'].'. Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
			</tr>
		</table>';

		$html = ''.$cuerpo.'';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        if(isset($contenidoestadocuenta[0])){
			
            $mpdf->SetHTMLHeader($header);
            $mpdf->SetHTMLFooter($footer);
            $mpdf->SetDefaultBodyCSS('background', "url('/var/www/html/".$company[0]['company']."/assets/img/W-background.png')");
    
            $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);

            $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);
    
    
            $mpdf->Output('EstadoCartera_'.$contenidoestadocuenta[0]['fechacorte'].'.pdf', 'D');
    
            header('Content-type: application/force-download');
            header('Content-Disposition: attachment; filename='.$filename);
        }else{
            // Crea una instancia de mPDF con configuraciones personalizadas
            $mpdf1 = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => [140,216] // Establece el formato en media carta
            ]);
            $mpdf1->SetHTMLHeader($header);
            $mpdf1->SetHTMLFooter($footer);
            $found = '<br><br><br><br><br><br><table width="100%"> <tr> <th style="text-align: center;"> <h1>No se encontraron datos</h1></th></tr></table>';
            $mpdf1->WriteHTML($found);
            $mpdf1->Output();
        }
	}

}
