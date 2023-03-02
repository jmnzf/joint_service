<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class EstadoCarteraPro extENDs REST_Controller {

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


	public function EstadoCarteraPro_post(){
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

        $grupo = "";
		if ( isset($Data['groupsn']) && $Data['groupsn'] != 0 ){
			$grupo = " AND dmsn.dms_group_num::numeric = ".$Data['groupsn']."::numeric";
		}

		$sqlestadocuenta = "SELECT distinct dmdt.mdt_docname,
                mac1.ac1_font_key,
                mac1.ac1_legal_num                                    as codigocliente,
                dmsn.dms_card_name                                       nombrecliente,

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
                get_dynamic_conversion(:currency,cfc_currency,cfc_docdate,dcfc.cfc_doctotal ,get_localcur())  as totalfactura,
                get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())  as saldo,
                ''                                                       retencion,
                get_tax_currency(dcfc.cfc_currency, dcfc.cfc_docdate) as tasa_dia,
                CASE
                    WHEN ('".$Data['fecha']."'- dcfc.cfc_duedate) >= 0 and ('".$Data['fecha']."'- dcfc.cfc_duedate) <= 30
                        then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))  ,get_localcur())
                    ELSE 0 END                                           uno_treinta,
                CASE
                    WHEN ('".$Data['fecha']."'- dcfc.cfc_duedate) >= 31 and ('".$Data['fecha']."'-
                                                                      dcfc.cfc_duedate) <= 60
                        then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))  ,get_localcur())
                    ELSE 0 END                                           treinta_uno_secenta,
                CASE
                    WHEN
                                ('".$Data['fecha']."'- dcfc.cfc_duedate) >= 61 and ('".$Data['fecha']."'- dcfc.cfc_duedate) <= 90
                        then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))  ,get_localcur())
                    ELSE 0 END                                           secenta_uno_noventa,
                CASE
                    WHEN ('".$Data['fecha']."'- dcfc.cfc_duedate) >= 91
                        then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))  ,get_localcur())
                    ELSE 0
                    END                                                  mayor_noventa,
                    '' as comentario_asiento
from mac1
         inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
         inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
         inner join dcfc on dcfc.cfc_doctype = mac1.ac1_font_type and dcfc.cfc_docentry = mac1.ac1_font_key
         inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
where dmsn.dms_card_type = '2'
".$grupo."
and dcfc.cfc_docdate <= '{$Data['fecha']}'
GROUP BY dmdt.mdt_docname,
         mac1.ac1_font_key,
         mac1.ac1_legal_num,
         dmsn.dms_card_name,

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
         dcfc.cfc_doctotal
HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0

union all
select distinct dmdt.mdt_docname,
                mac1.ac1_font_key,
                mac1.ac1_legal_num                                    as codigoproveedor,
                dmsn.dms_card_name                                       nombreproveedor,

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
                    when mac1.ac1_font_type = gbpe.bpe_doctype then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,sum(mac1.ac1_debit)  ,get_localcur())
                    else get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,sum(mac1.ac1_debit),get_localcur())
                    end                                               as totalfactura,
                 get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())     as saldo,
                ''                                                       retencion,
                get_tax_currency(gbpe.bpe_currency, gbpe.bpe_docdate) as tasa_dia,
                CASE
                    WHEN ('".$Data['fecha']."'- gbpe.bpe_docdate) >= 0 and ('".$Data['fecha']."'- gbpe.bpe_docdate) <= 30 then
                       get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0 END                                           uno_treinta,
                CASE
                    WHEN ('".$Data['fecha']."'- gbpe.bpe_docdate) >= 31 and ('".$Data['fecha']."'- gbpe.bpe_docdate) <= 60
                        then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0 END                                           treinta_uno_secenta,
                CASE
                    WHEN ('".$Data['fecha']."'- gbpe.bpe_docdate) >= 61 and ('".$Data['fecha']."'- gbpe.bpe_docdate) <= 90
                        then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0 END                                           secenta_uno_noventa,
                CASE
                    WHEN ('".$Data['fecha']."'- gbpe.bpe_docdate) >= 91
                        then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0
                    END                                                  mayor_noventa,
                    '' as comentario_asiento

from mac1
         inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
         inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
         inner join gbpe on gbpe.bpe_doctype = mac1.ac1_font_type and gbpe.bpe_docentry =
                                                                      mac1.ac1_font_key
         inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
where  dmsn.dms_card_type = '2'
".$grupo."
and gbpe.bpe_docdate <= '{$Data['fecha']}'
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
				 gbpe.bpe_doctype
HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0
union all
select distinct dmdt.mdt_docname,
                mac1.ac1_font_key,
                mac1.ac1_legal_num                                    as codigoproveedor,
                dmsn.dms_card_name                                       nombreproveedor,

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
                    when mac1.ac1_font_type = dcnc.cnc_doctype then get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,sum(mac1.ac1_debit),get_localcur())
                    else get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,sum(mac1.ac1_debit),get_localcur())
                    end                                               as totalfactura,
                get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())    as saldo,
                ''                                                       retencion,
                get_tax_currency(dcnc.cnc_currency, dcnc.cnc_docdate) as tasa_dia,
                CASE
                    WHEN ('".$Data['fecha']."'- dcnc.cnc_duedate) >= 0 and ('".$Data['fecha']."'- dcnc.cnc_duedate) <= 30 then
                        get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0 END                                           uno_treinta,
                CASE
                    WHEN
                                ('".$Data['fecha']."'- dcnc.cnc_duedate) >= 31 and ('".$Data['fecha']."'- dcnc.cnc_duedate) <= 60 then
                        get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0 END                                           treinta_uno_secenta,
                CASE
                    WHEN
                                ('".$Data['fecha']."'- dcnc.cnc_duedate) >= 61 and ('".$Data['fecha']."'- dcnc.cnc_duedate) <= 90 then
                        get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0 END                                           secenta_uno_noventa,
                CASE
                    WHEN ('".$Data['fecha']."'- dcnc.cnc_duedate) >= 91
                        then get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0
                    END                                                  mayor_noventa,
                    '' as comentario_asiento

from mac1
         inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
         inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
         inner join dcnc on dcnc.cnc_doctype = mac1.ac1_font_type and dcnc.cnc_docentry = mac1.ac1_font_key
         inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
where dmsn.dms_card_type = '2'
".$grupo."
and dcnc.cnc_docdate <= '{$Data['fecha']}'
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
         mdt_docname,
				 dcnc.cnc_doctype
HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0

union all

select distinct dmdt.mdt_docname,
                mac1.ac1_font_key,
                mac1.ac1_legal_num                                    as CodigoCliente,
                dmsn.dms_card_name                                       NombreCliente,

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
                    when mac1.ac1_font_type = dcnd.cnd_doctype then  get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,sum(mac1.ac1_debit),get_localcur())
                    else get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,sum(mac1.ac1_credit),get_localcur())
                    end                                               as totalfactura,
                get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,sum((mac1.ac1_ven_credit) - (mac1.ac1_credit)),get_localcur())     as saldo,
                ''                                                       retencion,
                get_tax_currency(dcnd.cnd_currency, dcnd.cnd_docdate) as tasa_dia,
                CASE
                    WHEN ('".$Data['fecha']."'- dcnd.cnd_duedate) >= 0 and ('".$Data['fecha']."'- dcnd.cnd_duedate)
                        <= 30 then get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0 END                                           uno_treinta,
                CASE
                    WHEN ('".$Data['fecha']."'- dcnd.cnd_duedate) >= 31 and ('".$Data['fecha']."'-
                                                                      dcnd.cnd_duedate) <= 60
                        then get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0
                    END                                                  treinta_uno_secenta,
                CASE
                    WHEN ('".$Data['fecha']."'- dcnd.cnd_duedate) >= 61
                        and ('".$Data['fecha']."'- dcnd.cnd_duedate) <= 90
                        then get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0 END                                           secenta_uno_noventa,
                CASE
                    WHEN
                        ('".$Data['fecha']."'- dcnd.cnd_duedate) >= 91
                        then get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0
                    END                                                  mayor_noventa,
                    '' as comentario_asiento
from mac1
         inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
         inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
         inner join dcnd on dcnd.cnd_doctype = mac1.ac1_font_type and
                            dcnd.cnd_docentry = mac1.ac1_font_key
         inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
where dmsn.dms_card_type = '2'
".$grupo."
and dcnd.cnd_docdate <= '{$Data['fecha']}'
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
         mdt_docname,
				 dcnd.cnd_doctype
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
                        then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,sum(mac1.ac1_debit),get_localcur())
                    when mac1.ac1_cord = 1
                        then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,sum(mac1.ac1_credit),get_localcur())
                    end                                                as total_doc,
                get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())  as saldo_venc,
                ''                                                        retencion,
                get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia,
                CASE
                    WHEN ('".$Data['fecha']."'- tmac.mac_doc_duedate) >= 0 and ('".$Data['fecha']."'-
                                                                         tmac.mac_doc_duedate) <= 30
                        then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0 END                                            uno_treinta,
                CASE
                    WHEN
                                ('".$Data['fecha']."'- tmac.mac_doc_duedate) >= 31 and ('".$Data['fecha']."'-
                                                                                 tmac.mac_doc_duedate) <= 60
                        then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0 END                                            treinta_uno_secenta,
                CASE
                    WHEN
                                ('".$Data['fecha']."'- tmac.mac_doc_duedate) >= 61 and ('".$Data['fecha']."'-
                                                                                 tmac.mac_doc_duedate) <= 90
                        then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0 END                                            secenta_uno_noventa,
                CASE
                    WHEN ('".$Data['fecha']."'- tmac.mac_doc_duedate) >= 91
                        then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
                    ELSE 0
                    END                                                   mayor_noventa,
                    ac1_comments as comentario_asiento
from mac1
         inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
         inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
         inner join tmac on tmac.mac_trans_id = mac1.ac1_font_key and
         tmac.mac_doctype = mac1.ac1_font_type
         inner join dmsn on mac1.ac1_card_type = dmsn.dms_card_type
    and mac1.ac1_legal_num = dmsn.dms_card_code
where dmsn.dms_card_type = '2'
".$grupo."
and tmac.mac_doc_date <= '{$Data['fecha']}'
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
         mdt_docname, mac1.ac1_cord,ac1_comments
				 HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0";

		$contenidoestadocuenta = $this->pedeo->queryTable($sqlestadocuenta,array(":currency" => $Data['currency']));
		if(!isset($contenidoestadocuenta[0])){
			$respuesta = array(
				 'error' => true,
				 'data'  => $contenidoestadocuenta,
				 'mensaje' =>'No tiene pagos realizados'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

        $cliente = "";
        $encabezado = "";
        $detalle = "";
        $totaldetalle = "";
        $cuerpo = "";
        $cabecera = "";
		$gCliente  = "";
		$hacer = true;

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
			if( trim($cliente) == trim($value['codigocliente']) ){
					$hacer = false;
			}else{
				$cliente = $value['codigocliente'];

				$gCliente = '<th class=""><b>RIF:</b> '.$value['codigocliente'].'</th>
										 <th class=""><b>PROVEEDOR:</b> '.$value['nombrecliente'].'</th>
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
			$detail_0_30 = 0;
			$detail_30_60 = 0;
			$detail_60_90 = 0;
			$detail_mayor_90 = 0;

			if($hacer){

				$cabecera = '
									<th class=""><b>Tipo Documento</b></th>
									<th class=""><b>Numero Documento</b></th>
									<th class=""><b>F. Documento</b></th>
									<th class=""><b>Total Documento</b></th>
									<th class=""><b>F. Ven Documento</b></th>
									<th class=""><b>F. Corte</b></th>
                                    <th class=""><b>Referencia</b></th>
									<th class=""><b>Dias Vencidos</b></th>
									<th class=""><b>0-30</b></th>
									<th class=""><b>31-60</b></th>
									<th class=""><b>61-90</b></th>
									<th class=""><b>+90</b></th>';
				foreach ($contenidoestadocuenta as $key => $value1) {

					if( $cliente == $value1['codigocliente']){
						//
						$monedadocumento = $Data['currency'];


						$detalle = '
									<td style="border-bottom: dotted;padding-top: 10px;">'.$value1['mdt_docname'].'</td>
									<td style="border-bottom: dotted;padding-top: 10px;">'.$value1['numerodocumento'].'</td>
									<td style="border-bottom: dotted;padding-top: 10px;">'.$this->dateformat->Date($value1['fechadocumento']).'</td>
									<td style="border-bottom: dotted;padding-top: 10px;">'.$Data['currency']." ".number_format($value1['totalfactura'], $DECI_MALES, ',', '.').'</td>
									<td style="border-bottom: dotted;padding-top: 10px;">'.$this->dateformat->Date($value1['fechavencimiento']).'</td>
									<td style="border-bottom: dotted;padding-top: 10px;">'.$this->dateformat->Date($value1['fechacorte']).'</td>
                                    <td style="border-bottom: dotted;padding-top: 10px;">'.$value1['comentario_asiento'].'</td>
									<td style="border-bottom: dotted;padding-top: 10px;">'.$value1['dias'].'</td>
									<td style="border-bottom: dotted;padding-top: 10px;">'.$Data['currency']." ".number_format($value1['uno_treinta'], $DECI_MALES, ',', '.').'</td>
									<td style="border-bottom: dotted;padding-top: 10px;">'.$Data['currency']." ".number_format($value1['treinta_uno_secenta'], $DECI_MALES, ',', '.').'</td>
									<td style="border-bottom: dotted;padding-top: 10px;">'.$Data['currency']." ".number_format($value1['secenta_uno_noventa'], $DECI_MALES, ',', '.').'</td>
									<td style="border-bottom: dotted;padding-top: 10px;">'.$Data['currency']." ".number_format($value1['mayor_noventa'], $DECI_MALES, ',', '.').'</td>';



						$totaldetalle .= '<tr>'.$detalle.'</tr>';

						$detail_0_30 =  $detail_0_30 + ($value1['uno_treinta']);
 					   	$detail_30_60 =  $detail_30_60 + ($value1['treinta_uno_secenta']);
 						$detail_60_90 =  $detail_60_90 + ($value1['secenta_uno_noventa']);
 						$detail_mayor_90 =  $detail_mayor_90 + ($value1['mayor_noventa']);
					}
				}

				$total_0_30 =  $total_0_30 + $detail_0_30;
			   	$total_30_60 =  $total_30_60 + $detail_30_60;
				$total_60_90 =  $total_60_90 + $detail_60_90;
				$total_mayor_90 =  $total_mayor_90 + $detail_mayor_90;

				$detail_total = '
							<tr>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
							<th><b>Total</b></th>
							<th style="width: 10%;" class=" centro"><b>'.$monedadocumento.' '.number_format(($detail_0_30+$detail_30_60+$detail_60_90+$detail_mayor_90), $DECI_MALES, ',', '.').'</b></th>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($detail_0_30, $DECI_MALES, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($detail_30_60, $DECI_MALES, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($detail_60_90, $DECI_MALES, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($detail_mayor_90, $DECI_MALES, ',', '.').'</b></th>
							</tr>';

				$cuerpo .= $encabezado."<table width='100%'><tr>".$cabecera."</tr>".$totaldetalle.$detail_total."</table><br><br>";
			}
        }

        $cuerpo .= '
        			<table width="100%">
						<tr>
							<th style="width: 10%;">&nbsp;</th>
							<th style="width: 10%;">&nbsp;</th>
							<th style="width: 10%;">&nbsp;</th>
							<th style="width: 24%;">&nbsp;</th>
							<th><b>Total</th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format(($total_0_30+$total_30_60+$total_60_90+$total_mayor_90), $DECI_MALES, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_0_30, $DECI_MALES, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_30_60, $DECI_MALES, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_60_90, $DECI_MALES, ',', '.').'</b></th>
							<th class=" centro"><b>'.$monedadocumento.' '.number_format($total_mayor_90, $DECI_MALES, ',', '.').'</b></th>
							</tr>
					</table>';

        $header = '
        <table width="100%" style="text-align: left;">
	        <tr>
	            <th style="text-align: left;" width ="60"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="185" height ="120"></img></th>
	            <th >
	                <p><b>INFORME ESTADO DE CUENTA PROVEEDORES</b></p>
	            </th>
				<th>
					&nbsp;
					&nbsp;
				</th>
	        </tr>
        </table>';

        $footer = '<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
        <tr>
            <th  width="33%" style="font-size: 8px;"> Documento generado por <span style="color: orange; font-weight: bolder;"> Joint ERP  </span> para: '.$empresa[0]['pge_small_name'].'. Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
        </tr>
    </table>';

		$html = ''.$cuerpo.'';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);
        $mpdf->SetDefaultBodyCSS('background', "url('/var/www/html/".$company[0]['company']."/assets/img/W-background.png')");

        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);


        $mpdf->Output('EstadoCartera_'.$contenidoestadocuenta[0]['fechacorte'].'.pdf', 'D');

		header('Content-type: application/force-download');
		header('Content-Disposition: attachment; filename='.$filename);
	}

}
