<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class EstadoCuentaPro extENDs REST_Controller {

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


	public function EstadoCuentaPro_post(){

				$DECI_MALES =  $this->generic->getDecimals();

        $Data = $this->post();
				// $Data = $Data['fecha'];
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

				$sqlestadocuenta = "SELECT distinct dmdt.mdt_docname,
                mac1.ac1_font_key,
                mac1.ac1_legal_num                                    as codigoproveedor,
                dmsn.dms_card_name                                       nombreproveedor,
				dcfc.cfc_tax_control_num                              AS refT,     
                dcfc.cfc_currency                                        monedadocumento,
                '".$Data['fecha']."'                                            fechacorte,
                '".$Data['fecha']."'- cfc_duedate                               dias,
                dcfc.cfc_comment,
                dcfc.cfc_currency,
                mac1.ac1_font_key                                     as cfc_docentry,
                dcfc.cfc_docnum,
                dcfc.cfc_docdate                                      as FechaDocumento,
                dcfc.cfc_duedate                                      as FechaVencimiento,
                dcfc.cfc_docnum                                       as NumeroDocumento,
                mac1.ac1_font_type                                    as numtype,
                mdt_docname                                           as tipo,
                get_dynamic_conversion(:currency,dcfc.cfc_currency,dcfc.cfc_docdate,dcfc.cfc_doctotal ,get_localcur()) as totalfactura,
                get_dynamic_conversion(:currency,get_localcur(),dcfc.cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())    as saldo,
                ''                                                       retencion,
                get_tax_currency(dcfc.cfc_currency, dcfc.cfc_docdate) as tasa_dia,
				CASE
                    WHEN current_date <= dcfc.cfc_duedate THEN get_dynamic_conversion(:currency, get_localcur(), cfc_duedate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
                    ELSE 0 END sin_vencer,
                CASE
                    WHEN '".$Data['fecha']."' > dcfc.cfc_duedate  and '".$Data['fecha']."' <= dcfc.cfc_duedate + INTERVAL '30 DAY' 
					then get_dynamic_conversion(:currency,get_localcur(),dcfc.cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
                    ELSE 0 END uno_treinta,
                CASE
                    WHEN '".$Data['fecha']."' > dcfc.cfc_duedate + INTERVAL '30 DAY' and '".$Data['fecha']."' <=  dcfc.cfc_duedate + INTERVAL '60 DAY'
					then get_dynamic_conversion(:currency,get_localcur(),dcfc.cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0 END treinta_uno_secenta,
                CASE
					WHEN '".$Data['fecha']."' > dcfc.cfc_duedate + INTERVAL '60 DAY' and '".$Data['fecha']."' <=  dcfc.cfc_duedate + INTERVAL '90 DAY'
					then get_dynamic_conversion(:currency,get_localcur(),dcfc.cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
                    ELSE 0 END secenta_uno_noventa,
                CASE
                    WHEN '".$Data['fecha']."' > dcfc.cfc_duedate + INTERVAL '90 DAY'
                    then get_dynamic_conversion(:currency,get_localcur(),dcfc.cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
                    ELSE 0 END mayor_noventa,
				'' comentario_asiento
				from mac1
				inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
				inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
				inner join dcfc on dcfc.cfc_doctype = mac1.ac1_font_type and dcfc.cfc_docentry = mac1.ac1_font_key
				inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
				where mac1.ac1_legal_num = '".$Data['cardcode']."'
				and dmsn.dms_card_type = '2'
				
				GROUP BY dmdt.mdt_docname,
					mac1.ac1_font_key,
					mac1.ac1_legal_num,
					dmsn.dms_card_name,
					dcfc.cfc_tax_control_num,
					dcfc.cfc_currency,
					dcfc.cfc_comment,
					dcfc.cfc_currency,
					mac1.ac1_font_key,
					dcfc.cfc_docnum,
					dcfc.cfc_docdate,
					dcfc.cfc_duedate,
					dcfc.cfc_docnum,
					mac1.ac1_font_type,
					mdt_docname,
					dcfc.cfc_doctotal,
					mac1.ac1_ven_debit,
					mac1.ac1_ven_credit
				HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0
				union all
				select distinct dmdt.mdt_docname,
				mac1.ac1_font_key,
				mac1.ac1_legal_num                                    as codigoproveedor,
				dmsn.dms_card_name                                       nombreproveedor,
				''                                                      AS refT, 
				gbpe.bpe_currency                                        monedadocumento,
				'".$Data['fecha']."'                                            fechacorte,
				'".$Data['fecha']."'- gbpe.bpe_docdate                       as dias,
				gbpe.bpe_comments                                     as bpe_comment,
				gbpe.bpe_currency,
				mac1.ac1_font_key                                     as cfc_docentry,
				gbpe.bpe_docnum,
				gbpe.bpe_docdate                                      as FechaDocumento,
				gbpe.bpe_docdate                                      as FechaVencimiento,
				gbpe.bpe_docnum                                       as NumeroDocumento,
				mac1.ac1_font_type                                    as numtype,
				'ANTICIPO'                                            as tipo,
				case
					when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,sum(mac1.ac1_debit) ,get_localcur())
					else get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,sum(mac1.ac1_debit) ,get_localcur())
					end                                               as totalfactura,
					get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())    as saldo,
				''                                                       retencion,
				get_tax_currency(gbpe.bpe_currency, gbpe.bpe_docdate) as tasa_dia,
				CASE
					WHEN current_date <= gbpe.bpe_docdate THEN get_dynamic_conversion(:currency, get_localcur(), bpe_docdate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
					ELSE 0 END sin_vencer,
				CASE
					WHEN '".$Data['fecha']."' > gbpe.bpe_docdate and '".$Data['fecha']."' <= gbpe.bpe_docdate + INTERVAL '30 DAY' then
					get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0 END uno_treinta,
				CASE
					WHEN '".$Data['fecha']."' > gbpe.bpe_docdate + INTERVAL '30 DAY' and '".$Data['fecha']."' <= gbpe.bpe_docdate + INTERVAL '60 DAY' 
						then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0 END                                           treinta_uno_secenta,
				CASE
					WHEN '".$Data['fecha']."' > gbpe.bpe_docdate + INTERVAL '60 DAY' and '".$Data['fecha']."' <= gbpe.bpe_docdate + INTERVAL '90 DAY'
						then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0 END secenta_uno_noventa,
				CASE
					WHEN '".$Data['fecha']."' > gbpe.bpe_docdate + INTERVAL '90 DAY'
						then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0
					END mayor_noventa,
					'' comentario_asiento
				from mac1
					inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
					inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
					inner join gbpe on gbpe.bpe_doctype = mac1.ac1_font_type and gbpe.bpe_docentry = mac1.ac1_font_key
					inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
				where mac1.ac1_legal_num = '".$Data['cardcode']."'
					and dmsn.dms_card_type = '2'
					
				GROUP BY dmdt.mdt_docname,
					mac1.ac1_font_key,
					mac1.ac1_legal_num,
					dmsn.dms_card_name,
					gbpe.bpe_currency,
					gbpe.bpe_comments,
					gbpe.bpe_currency,
					mac1.ac1_font_key,
					gbpe.bpe_docnum,
					gbpe.bpe_docdate,
					gbpe.bpe_docdate,
					gbpe.bpe_docnum,
					mac1.ac1_font_type,
					mac1.ac1_ven_debit,
					mac1.ac1_ven_credit
				HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0
				union all
				select distinct dmdt.mdt_docname,
				mac1.ac1_font_key,
				mac1.ac1_legal_num                                    as codigoproveedor,
				dmsn.dms_card_name                                       nombreproveedor,
				''                                                      AS refT, 
				dcnc.cnc_currency                                        monedadocumento,
				'".$Data['fecha']."'                                            fechacorte,
				'".$Data['fecha']."'- dcnc.cnc_docdate                       as dias,
				dcnc.cnc_comment                                      as bpe_comment,
				dcnc.cnc_currency,
				mac1.ac1_font_key                                     as cfc_docentry,
				dcnc.cnc_docnum,
				dcnc.cnc_docdate                                      as FechaDocumento,
				dcnc.cnc_duedate                                      as FechaVencimiento,
				dcnc.cnc_docnum                                       as NumeroDocumento,
				mac1.ac1_font_type                                    as numtype,
				mdt_docname                                           as tipo,
				case
					when mac1.ac1_font_type = dcnc.cnc_doctype then  get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,sum(mac1.ac1_debit) ,get_localcur())
					else get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,sum(mac1.ac1_debit) ,get_localcur())
					end                                               as totalfactura,
					get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur()) as saldo,
				''                                                       retencion,
				get_tax_currency(dcnc.cnc_currency, dcnc.cnc_docdate) as tasa_dia,
				CASE
					WHEN current_date <= dcnc.cnc_duedate THEN get_dynamic_conversion(:currency, get_localcur(), cnc_duedate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
					ELSE 0 END sin_vencer,
				CASE
					WHEN '".$Data['fecha']."' > dcnc.cnc_duedate  and '".$Data['fecha']."' <= dcnc.cnc_duedate + INTERVAL '30 DAY' 
					then get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0 END uno_treinta,
				CASE
					WHEN '".$Data['fecha']."' > dcnc.cnc_duedate + INTERVAL '30 DAY' and '".$Data['fecha']."' <= dcnc.cnc_duedate + INTERVAL '60 DAY'  
					then get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0 END treinta_uno_secenta,
				CASE
					WHEN '".$Data['fecha']."' > dcnc.cnc_duedate + INTERVAL '60 DAY' and '".$Data['fecha']."' <= dcnc.cnc_duedate + INTERVAL '90 DAY' 
					then get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0 END secenta_uno_noventa,
				CASE
					WHEN '".$Data['fecha']."' > dcnc.cnc_duedate + INTERVAL '90 DAY'
					then get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0
					END mayor_noventa,
					'' comentario_asiento

				from mac1
					inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
					inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
					inner join dcnc on dcnc.cnc_doctype = mac1.ac1_font_type and dcnc.cnc_docentry = mac1.ac1_font_key
					inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
				where mac1.ac1_legal_num = '".$Data['cardcode']."'
					and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
					and dmsn.dms_card_type = '2'
					
				GROUP BY dmdt.mdt_docname,
					mac1.ac1_font_key,
					mac1.ac1_legal_num,
					dmsn.dms_card_name,
					dcnc.cnc_currency,
					dcnc.cnc_comment,
					dcnc.cnc_currency,
					mac1.ac1_font_key,
					dcnc.cnc_docnum,
					dcnc.cnc_docdate,
					dcnc.cnc_duedate,
					dcnc.cnc_docnum,
					mac1.ac1_font_type,
					mdt_docname,dcnc.cnc_doctype,
					mac1.ac1_ven_debit,
					mac1.ac1_ven_credit
				HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0
				union all
				select distinct dmdt.mdt_docname,
				mac1.ac1_font_key,
				mac1.ac1_legal_num                                    as CodigoCliente,
				dmsn.dms_card_name                                       NombreCliente,
				''                                                      AS refT, 
				dcnd.cnd_currency                                        monedadocumento,
				'".$Data['fecha']."'                                            fechacorte,
				'".$Data['fecha']."'- dcnd.cnd_docdate                       as dias,
				dcnd.cnd_comment                                      as bpe_comment,
				dcnd.cnd_currency,
				mac1.ac1_font_key                                     as cfc_docentry,
				dcnd.cnd_docnum,
				dcnd.cnd_docdate                                      as FechaDocumento,
				dcnd.cnd_duedate                                      as FechaVencimiento,
				dcnd.cnd_docnum                                       as NumeroDocumento,
				mac1.ac1_font_type                                    as numtype,
				mdt_docname                                           as tipo,
				case
					when mac1.ac1_font_type = dcnd.cnd_doctype then get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate, sum(mac1.ac1_debit) ,get_localcur())
					else get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate, sum(mac1.ac1_credit) ,get_localcur())
					end                                               as totalfactura,
				get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate, sum((mac1.ac1_ven_credit) - (mac1.ac1_credit)) ,get_localcur())     as saldo,
				''                                                       retencion,
				get_tax_currency(dcnd.cnd_currency, dcnd.cnd_docdate) as tasa_dia,
				CASE
					WHEN current_date <= dcnd.cnd_duedate THEN get_dynamic_conversion(:currency, get_localcur(), cnd_duedate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
					ELSE 0 END sin_vencer,
				CASE
					WHEN '".$Data['fecha']."' > dcnd.cnd_duedate  and '".$Data['fecha']."' <= dcnd.cnd_duedate + INTERVAL '30 DAY' 
					then  get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate, SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0 END uno_treinta,
				CASE
					WHEN '".$Data['fecha']."' > dcnd.cnd_duedate + INTERVAL '30 DAY' and '".$Data['fecha']."' <= dcnd.cnd_duedate + INTERVAL '60 DAY'
					then  get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate, SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0 END  treinta_uno_secenta,
				CASE
					WHEN '".$Data['fecha']."' > dcnd.cnd_duedate + INTERVAL '60 DAY' and '".$Data['fecha']."' <= dcnd.cnd_duedate + INTERVAL '90 DAY'
					then  get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate, SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0 END secenta_uno_noventa,
				CASE
					WHEN '".$Data['fecha']."' > dcnd.cnd_duedate + INTERVAL '90 DAY'
					then  get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate, SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())
					ELSE 0 END mayor_noventa,
					'' comentario_asiento
				from mac1
					inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
					inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
					inner join dcnd on dcnd.cnd_doctype = mac1.ac1_font_type and dcnd.cnd_docentry = mac1.ac1_font_key
					inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
				where mac1.ac1_legal_num = '".$Data['cardcode']."'
					and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
					and dmsn.dms_card_type = '2' 
				group by dmdt.mdt_docname,
					mac1.ac1_font_key,
					mac1.ac1_legal_num,
					dmsn.dms_card_name,
					dcnd.cnd_currency,
					dcnd.cnd_comment,
					dcnd.cnd_currency,
					mac1.ac1_font_key,
					dcnd.cnd_docnum,
					dcnd.cnd_docdate,
					dcnd.cnd_duedate,
					dcnd.cnd_docnum,
					mac1.ac1_font_type,
					mdt_docname,dcnd.cnd_doctype,
					mac1.ac1_ven_debit,
					mac1.ac1_ven_credit
				HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0
				union all
				select  dmdt.mdt_docname,
				mac1.ac1_font_key,
				case
					when ac1_card_type = '1'
						then mac1.ac1_legal_num
					when ac1_card_type = '2'
						then mac1.ac1_legal_num
					end                                                as codigoproveedor,
				dmsn.dms_card_name                                        NombreCliente,
				''                                                      AS refT, 
				tmac.mac_currency,
				'".$Data['fecha']."'                                             fechacorte,
				CURRENT_DATE - tmac.mac_doc_duedate                       dias_atrasado,
				tmac.mac_comments,
				tmac.mac_currency,
				mac_trans_id                                           as dvf_docentry,
				0                                                      as docnum,
				tmac.mac_doc_date                                      as fecha_doc,
				tmac.mac_doc_duedate                                   as fecha_ven,
				mac_trans_id                                           as id_origen,
				18                                                     as numtype,
				mdt_docname                                            as tipo,
				case
					when mac1.ac1_cord = 0
						then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_date, sum(mac1.ac1_debit),get_localcur())
					when mac1.ac1_cord = 1
						then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_date, sum(mac1.ac1_credit),get_localcur())
					end                                                as total_doc,
				get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_date, SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())    as saldo_venc,
				''                                                        retencion,
				get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia,
				CASE
					WHEN current_date <= tmac.mac_doc_duedate THEN get_dynamic_conversion(:currency, get_localcur(), mac_doc_duedate, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur())
					ELSE 0 END sin_vencer,
				CASE
					WHEN '".$Data['fecha']."' > tmac.mac_doc_duedate  and '".$Data['fecha']."' <= tmac.mac_doc_duedate + INTERVAL '30 DAY'
					then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_date, SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
					ELSE 0 END uno_treinta,
				CASE
					WHEN '".$Data['fecha']."' > tmac.mac_doc_duedate + INTERVAL '30 DAY' and '".$Data['fecha']."' <= tmac.mac_doc_duedate + INTERVAL '60 DAY'
					then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_date, SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
					ELSE 0 END treinta_uno_secenta,
				CASE
					WHEN '".$Data['fecha']."' > tmac.mac_doc_duedate + INTERVAL '60 DAY' and '".$Data['fecha']."' <= tmac.mac_doc_duedate + INTERVAL '90 DAY'
					then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_date, SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
					ELSE 0 END secenta_uno_noventa,
				CASE
					WHEN '".$Data['fecha']."' > tmac.mac_doc_duedate + INTERVAL '90 DAY'
					then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_date, SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
					ELSE 0 END mayor_noventa,
					ac1_comments comentario_asiento
				from mac1
				inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
				inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
				inner join tmac on tmac.mac_trans_id = mac1.ac1_font_key and tmac.mac_doctype = mac1.ac1_font_type
				inner join dmsn on mac1.ac1_card_type = dmsn.dms_card_type
				and mac1.ac1_legal_num = dmsn.dms_card_code
				where dmsn.dms_card_type = '2' 
				and mac1.ac1_legal_num = '".$Data['cardcode']."'
				group by dmdt.mdt_docname,
				mac1.ac1_font_key,
				case
					when ac1_card_type = '1'
						then mac1.ac1_legal_num
					when ac1_card_type = '2'
						then mac1.ac1_legal_num
					end,
				dmsn.dms_card_name,
				tmac.mac_currency,
				tmac.mac_comments,
				tmac.mac_currency,
				mac_trans_id,
				tmac.mac_doc_date,
				tmac.mac_doc_duedate,
				mac_trans_id,
				mdt_docname, mac1.ac1_cord,ac1_comments,
				mac1.ac1_ven_debit,
				mac1.ac1_ven_credit
				HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0";

				$contenidoestadocuenta = $this->pedeo->queryTable($sqlestadocuenta,array(":currency" => $Data['currency']));
        //   print_r($sqlestadocuenta);exit();die();
				// if(!isset($contenidoestadocuenta[0])){
				// 		$respuesta = array(
				// 			 'error' => true,
				// 			 'data'  => $contenidoestadocuenta,
				// 			 'mensaje' =>'No tiene pagos realizados'
				// 		);

				// 		$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
				// 		var_dump($contenidoestadocuenta);exit();die();
				// 		return;
				// }
				if(isset($contenidoestadocuenta[0])){
				$totaldetalle = '';
				$detail_0_30 = 0;
				$detail_30_60 = 0;
				$detail_60_90 = 0;
				$detail_mayor_90 = 0;
				$detail_sinvencer = 0;
				$total_valores = '';
				foreach ($contenidoestadocuenta as $key => $value) {
					// code...
					$detalle = '
								<td style="border-bottom: dotted;padding-top: 10px; text-align:justify;">'.$value['tipo'].'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$value['numerodocumento'].'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.(isset($value1['reft'])? $value1['reft'] :  '').'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$this->dateformat->Date($value['fechadocumento']).'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$Data['currency']." ".number_format($value['totalfactura'], $DECI_MALES, ',', '.').'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$this->dateformat->Date($value['fechavencimiento']).'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$this->dateformat->Date($value['fechacorte']).'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$value['comentario_asiento'].'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$value['dias'].'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$Data['currency']." ".number_format($value['sin_vencer'], $DECI_MALES, ',', '.').'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$Data['currency']." ".number_format($value['uno_treinta'], $DECI_MALES, ',', '.').'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$Data['currency']." ".number_format($value['treinta_uno_secenta'], $DECI_MALES, ',', '.').'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$Data['currency']." ".number_format($value['secenta_uno_noventa'], $DECI_MALES, ',', '.').'</td>
								<td style="border-bottom: dotted;padding-top: 10px; text-align:center;">'.$Data['currency']." ".number_format($value['mayor_noventa'], $DECI_MALES, ',', '.').'</td>';
					$totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
					// $totalfactura = ($totalfactura + $value['totalfactura']);

					$detail_0_30 =  $detail_0_30 + ($value['uno_treinta']);
					$detail_30_60 =  $detail_30_60 + ($value['treinta_uno_secenta']);
					$detail_60_90 =  $detail_60_90 + ($value['secenta_uno_noventa']);
					$detail_mayor_90 =  $detail_mayor_90 + ($value['mayor_noventa']);
					$detail_sinvencer = $detail_sinvencer + $value['sin_vencer'];

					$total_saldo = $detail_sinvencer+$detail_0_30+$detail_30_60+$detail_60_90+$detail_mayor_90;

					$total_valores = '
								<tr>
								<th style="white-space: nowrap; text-align:justify;">&nbsp;</th>
								<th style="white-space: nowrap; text-align:justify;">&nbsp;</th>								
								<th style="white-space: nowrap; text-align:justify;">&nbsp;</th>								
								<th style="white-space: nowrap; text-align:justify;"><b>Total</b></th>
								<th class=" centro" style=" white-space: nowrap;text-align:justify;"><b style="font-size: 10px;">'.$Data['currency'].' '.number_format(($total_saldo), $DECI_MALES, ',', '.').'</b></th>
								<th style="white-space: nowrap; text-align:justify;">&nbsp;</th>
								<th style="white-space: nowrap; text-align:justify;">&nbsp;</th>
								<th style="white-space: nowrap; text-align:justify;">&nbsp;</th>
								<th style="white-space: nowrap; text-align:justify;">&nbsp;</th>
								<th class=" centro" style=" white-space: nowrap;text-align:justify;"><b>'.$Data['currency'].' '.number_format($detail_sinvencer, $DECI_MALES, ',', '.').'</b></th>
								<th class=" centro" style=" white-space: nowrap;text-align:justify;"><b>'.$Data['currency'].' '.number_format($detail_0_30, $DECI_MALES, ',', '.').'</b></th>
								<th class=" centro" style=" white-space: nowrap;text-align:justify;"><b>'.$Data['currency'].' '.number_format($detail_30_60, $DECI_MALES, ',', '.').'</b></th>
								<th class=" centro" style=" white-space: nowrap;text-align:justify;"><b>'.$Data['currency'].' '.number_format($detail_60_90, $DECI_MALES, ',', '.').'</b></th>
								<th class=" centro" style=" white-space: nowrap;text-align:justify;"><b>'.$Data['currency'].' '.number_format($detail_mayor_90, $DECI_MALES, ',', '.').'</b></th>
								</tr>';

					$totalfactura = ($total_saldo);

				}


        $header = '
        <table width="100%" style="text-align: left;">
        <tr>
            <th style="text-align: left;" width ="60"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="185" height ="120"></img></th>
            <th style="text-align: center; margin-left: 600px;">
                <p><b>INFORME ESTADO DE CUENTA PROVEEDORES</b></p>

            </th>
			<th>
				&nbsp;
				&nbsp;
			</th>

        </tr>

        </table>';

        $footer = '

        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="" width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';


				$html = '

				<table class="" style="width:100%">
			 <tr>
				 <th>
					 <p class="" style="text-align: left;"><b>NIT:</b></p>
				 </th>
				 <th style="text-align: left;">
					 <p>'.$contenidoestadocuenta[0]['codigoproveedor'].'</p>
				 </th>
				</tr>
				<tr>
				 <th >
					 <p class="" ><b>Nombre Proveedor</b></p>
	 			 <th style="text-align: left;">

					 <p style="text-align: left;">'.$contenidoestadocuenta[0]['nombreproveedor'].'</p>

	 			 </th>
			 	</tr>
			 <tr>
				 <th>
					 <p class=""><b>Saldo</b></p>
				 </th>
				 <th style="text-align: left;">
					 <p>'.$Data['currency']." ".number_format($totalfactura, $DECI_MALES, ',', '.').'</p>
				 </th>

			 </tr>

			 </table>
			 <table width="100%" style="vertical-align: bottom; font-family: serif;
					 font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
					 <tr>
							 <th class="fondo">
									 <p></p>
							 </th>
					 </tr>
			 </table>

        <br>

        <table class="borde" style="width:100%">
        <tr>
			<th class=""><b>Tipo Doc.</b></th>
          	<th class=""><b># Doc.</b></th>
			<th class=""><b># Ref. Fiscal.</b></th>
          	<th class=""><b>F. Doc.</b></th>
			<th class=""><b>T. Doc.</b></th>
          	<th class=""><b>F.V. Doc.</b></th>
          	<th class=""><b>F. Corte</b></th>
		  	<th class=""><b>Ref.</b></th>
			<th class=""><b>Dias V.</b></th>
			<th class=""><b>SV</b></th>
          	<th class=""><b>0-30</b></th>
          	<th class=""><b>31-60</b></th>
          	<th class=""><b>61-90</b></th>
          	<th class=""><b>+90</b></th>
        </tr>
      	'.$totaldetalle.$total_valores.'
        </table>
        <br>
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="fondo">
                    <p></p>
                </th>
            </tr>
        </table>

        <br>
        ';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

		
            $mpdf->SetHTMLHeader($header);
            $mpdf->SetHTMLFooter($footer);
            $mpdf->SetDefaultBodyCSS('background', "url('/var/www/html/".$company[0]['company']."/assets/img/W-background.png')");
    
            $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
            $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);
    
    
            $mpdf->Output('EstadoCartera_'.$contenidoestadocuenta[0]['fechacorte'].'.pdf', 'D');
    
            header('Content-type: application/force-download');
            header('Content-Disposition: attachment; filename='.$filename);
        }else{
			$stylesheet = file_get_contents(APPPATH . '/asset/vendor/style.css');
			$header = '
        <table width="100%" style="text-align: left;">
        <tr>
            <th style="text-align: left;" width ="60"><img src="/var/www/html/' . $company[0]['company'] . '/' . $empresa[0]['pge_logo'] . '" width ="185" height ="120"></img></th>
            <th style="text-align: center; margin-left: 600px;">
                <p><b>INFORME ESTADO DE CUENTA PROVEEDOR</b></p>
            </th>
			<th>
				&nbsp;
				&nbsp;
			</th>

        </tr>

        </table>';

        $footer = '<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
        <tr>
            <th  width="33%" style="font-size: 8px;"> Documento generado por <span style="color: #fcc038; font-weight: bolder;"> JOINTERP  </span> para: ' . $empresa[0]['pge_small_name'] . '. Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
        </tr>
    	</table>';
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

	public function getEStadoDeCuenta_post(){
		$DECI_MALES =  $this->generic->getDecimals();
		$Data = $this->post();
		$sqlestadocuenta = "SELECT
							'NotaCredito' as tipo,
                        	t0.cfc_cardcode CodigoProveedor,
                        	t0.cfc_cardname NombreProveedor,
                        	t0.cfc_docnum NumeroDocumento,
                        	t0.cfc_docdate FechaDocumento,
                        	t0.cfc_duedate FechaVencimiento,
													t0.cfc_doctotal totalfactura,
													trim(:currency FROM t0.cfc_currency) MonedaDocumento,
                        	CURRENT_DATE FechaCorte,
													(CURRENT_DATE - t0.cfc_duedate) dias,
                        	CASE
                        		WHEN ( CURRENT_DATE - t0.cfc_duedate) >=0 and ( CURRENT_DATE - t0.cfc_duedate) <=30
                        			then (t0.cfc_doctotal - COALESCE(t0.cfc_paytoday,0))
															ELSE 0
                        	END uno_treinta,
                        	CASE
                        		WHEN ( CURRENT_DATE - t0.cfc_duedate) >=31 and ( CURRENT_DATE - t0.cfc_duedate) <=60
                        			then (t0.cfc_doctotal - COALESCE(t0.cfc_paytoday,0))
															ELSE 0
                        	END treinta_uno_secenta,
                        	CASE
                        		WHEN ( CURRENT_DATE - t0.cfc_duedate) >=61 and ( CURRENT_DATE - t0.cfc_duedate) <=90
                        			then (t0.cfc_doctotal - COALESCE(t0.cfc_paytoday,0))
															ELSE 0
                        	END secenta_uno_noventa,
                        	CASE
                        		WHEN ( CURRENT_DATE - t0.cfc_duedate) >=91
                        			then (t0.cfc_doctotal - COALESCE(t0.cfc_paytoday,0))
													ELSE 0
                        	END mayor_noventa

                        FROM dcfc t0
                        WHERE CURRENT_DATE >= t0.cfc_duedate  and t0.cfc_cardcode = :cardcode
												ORDER BY NumeroDocumento";

					$contenidoestadocuenta = $this->pedeo->queryTable($sqlestadocuenta, array(
						":cardcode" => $Data['cardcode']
					));

					$totalSaldo =	array_sum(array_column($contenidoestadocuenta,'uno_treinta'));
					$totalSaldo +=  array_sum(array_column($contenidoestadocuenta,'treinta_uno_secenta'));
					$totalSaldo +=  array_sum(array_column($contenidoestadocuenta,'secenta_uno_noventa'));
					$totalSaldo +=  array_sum(array_column($contenidoestadocuenta,'mayor_noventa'));

					// $contenidoestadocuenta['total_saldo'] = round($totalSaldo,2);
					// array_push($contenidoestadocuenta,['totalSaldo'=>$totalSaldo]);
					if (isset($contenidoestadocuenta[0])) {
						$respuesta = array(
							'error' => false,
							'data' => $contenidoestadocuenta,
							'totalSaldos' => round($totalSaldo, $DECI_MALES),
							'mensaje' => ''
						);
					} else {
						$respuesta = array(
							'error' => true,
							'data'  => [],
							'mensaje' => 'Datos no encontrados'
						);
					}
					$this->response($respuesta);
	}



}
