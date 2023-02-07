<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PagoRecibido extends REST_Controller {

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

  //Crear nueva moneda
	public function PagoRecibido_post(){

      $request = $this->post();

      if(!isset($request['cardcode']) OR
	     !isset($request['currency'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $resSelect = $this->pedeo->queryTable("SELECT distinct
	  mac1.ac1_font_key,
	  mac1.ac1_legal_num as codigo_proveedor,
	  mac1.ac1_account as cuenta,
	  CURRENT_DATE - dvf_duedate dias_atrasado,
	  dvfv.dvf_comment,
	  dvfv.dvf_currency,
	  mac1.ac1_font_key as dvf_docentry,
	  dvfv.dvf_docnum,
	  dvfv.dvf_docdate as fecha_doc,
	  dvfv.dvf_duedate as fecha_ven,
	  dvf_docnum as id_origen,
	  mac1.ac1_font_type as numtype,
	  mdt_docname as tipo,
	  case
	  when mac1.ac1_font_type = 5 then get_dynamic_conversion(:currency,dvf_currency,dvf_docdate,mac1.ac1_debit, get_localcur())
	  else get_dynamic_conversion(:currency,dvf_currency,dvf_docdate,mac1.ac1_credit, get_localcur())
	  end	 as total_doc,
	  get_dynamic_conversion(:currency,dvf_currency,dvf_docdate,(mac1.ac1_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
	  '' retencion,
	  get_tax_currency(dvfv.dvf_currency, dvfv.dvf_docdate) as tasa_dia,
	  ac1_line_num,
	  ac1_cord
	  from  mac1
	  inner join dacc
	  on mac1.ac1_account = dacc.acc_code
	  and acc_businessp = '1'
	  inner join dmdt
	  on mac1.ac1_font_type = dmdt.mdt_doctype
	  inner join dvfv
	  on dvfv.dvf_doctype = mac1.ac1_font_type
	  and dvfv.dvf_docentry = mac1.ac1_font_key
	  where mac1.ac1_legal_num = :cardcode
	  and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
	  -- primer q
	  union all
	  select distinct
	  mac1.ac1_font_key,
	  mac1.ac1_legal_num as codigo_proveedor,
	  mac1.ac1_account as cuenta,
	  CURRENT_DATE - gbpr.bpr_docdate as dias_atrasado,
	  gbpr.bpr_comments as bpr_comment,
	  gbpr.bpr_currency,
	  mac1.ac1_font_key as dvf_docentry,
	  gbpr.bpr_docnum,
	  gbpr.bpr_docdate as fecha_doc,
	  gbpr.bpr_docdate as fecha_ven,
	  gbpr.bpr_docnum as id_origen,
	  mac1.ac1_font_type as numtype,
	  mdt_docname as tipo,
	  case
		  when mac1.ac1_font_type = 5 then get_dynamic_conversion(:currency,bpr_currency,bpr_docdate,mac1.ac1_debit, get_localcur())
		  else get_dynamic_conversion(:currency,bpr_currency,bpr_docdate,mac1.ac1_credit, get_localcur())
	  end	 as total_doc,
	  get_dynamic_conversion(:currency,bpr_currency,bpr_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
	  '' retencion,
	  get_tax_currency(gbpr.bpr_currency,gbpr.bpr_docdate) as tasa_dia,
	  ac1_line_num,
	  ac1_cord
	  from  mac1
	  inner join dacc
	  on mac1.ac1_account = dacc.acc_code
	  and acc_businessp = '1'
	  inner join dmdt
	  on mac1.ac1_font_type = dmdt.mdt_doctype
	  inner join gbpr
	  on gbpr.bpr_doctype = mac1.ac1_font_type
	  and gbpr.bpr_docentry = mac1.ac1_font_key
	  where mac1.ac1_legal_num = :cardcode
	  and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
	  -- 2 query
	  union all
	  select distinct
	  mac1.ac1_font_key,
	  mac1.ac1_legal_num as codigo_proveedor,
	  mac1.ac1_account as cuenta,
	  CURRENT_DATE - dvnc.vnc_docdate as dias_atrasado,
	  dvnc.vnc_comment as bpr_comment,
	  dvnc.vnc_currency,
	  mac1.ac1_font_key as dvf_docentry,
	  dvnc.vnc_docnum,
	  dvnc.vnc_docdate as fecha_doc,
	  dvnc.vnc_duedate as fecha_ven,
	  dvnc.vnc_docnum as id_origen,
	  mac1.ac1_font_type as numtype,
	  mdt_docname as tipo,
	  case
		  when mac1.ac1_font_type = 5 then get_dynamic_conversion(:currency,vnc_currency,vnc_docdate,mac1.ac1_debit, get_localcur())
		  else get_dynamic_conversion(:currency,vnc_currency,vnc_docdate,mac1.ac1_credit, get_localcur())
	  end	 as total_doc,
	  get_dynamic_conversion(:currency,vnc_currency,vnc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
	  '' retencion,
	  get_tax_currency(dvnc.vnc_currency, dvnc.vnc_docdate) as tasa_dia,
	  ac1_line_num,
	  ac1_cord
	  from  mac1
	  inner join dacc
	  on mac1.ac1_account = dacc.acc_code
	  and acc_businessp = '1'
	  inner join dmdt
	  on mac1.ac1_font_type = dmdt.mdt_doctype
	  inner join dvnc
	  on dvnc.vnc_doctype = mac1.ac1_font_type
	  and dvnc.vnc_docentry = mac1.ac1_font_key
	  where mac1.ac1_legal_num = :cardcode
	  and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
	  -- 3 query
	  union all
	  select distinct
	  mac1.ac1_font_key,
	  mac1.ac1_legal_num as codigo_proveedor,
	  mac1.ac1_account as cuenta,
	  CURRENT_DATE - dvnd.vnd_docdate as dias_atrasado,
	  dvnd.vnd_comment as bpr_comment,
	  dvnd.vnd_currency,
	  mac1.ac1_font_key as dvf_docentry,
	  dvnd.vnd_docnum,
	  dvnd.vnd_docdate as fecha_doc,
	  dvnd.vnd_duedate as fecha_ven,
	  dvnd.vnd_docnum as id_origen,
	  mac1.ac1_font_type as numtype,
	  mdt_docname as tipo,
	  case
		  when mac1.ac1_font_type = 5 then get_dynamic_conversion(:currency,vnd_currency,vnd_docdate,mac1.ac1_debit, get_localcur())
		  else get_dynamic_conversion(:currency,vnd_currency,vnd_docdate,mac1.ac1_credit, get_localcur())
	  end	 as total_doc,
	  get_dynamic_conversion(:currency,vnd_currency,vnd_docdate,(mac1.ac1_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
	  '' retencion,
	  get_tax_currency(dvnd.vnd_currency, dvnd.vnd_docdate) as tasa_dia,
	  ac1_line_num,
	  ac1_cord
	  from  mac1
	  inner join dacc
	  on mac1.ac1_account = dacc.acc_code
	  and acc_businessp = '1'
	  inner join dmdt
	  on mac1.ac1_font_type = dmdt.mdt_doctype
	  inner join dvnd
	  on dvnd.vnd_doctype = mac1.ac1_font_type
	  and dvnd.vnd_docentry = mac1.ac1_font_key
	  where mac1.ac1_legal_num = :cardcode
	  and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
	  --ASIENTOS MANUALES
	  union all
	  select distinct
	  mac1.ac1_font_key,
	  case
		  when ac1_card_type = '1' then concat('C',mac1.ac1_legal_num)
		  when ac1_card_type = '2' then concat('P',mac1.ac1_legal_num)
	  end as codigoproveedor,
	  mac1.ac1_account as cuenta,
	  CURRENT_DATE - tmac.mac_doc_duedate dias_atrasado,
	  tmac.mac_comments,
	  tmac.mac_currency,
	  0 as dvf_docentry,
	  0 as docnum,
	  tmac.mac_doc_date as fecha_doc,
	  tmac.mac_doc_duedate as fecha_ven,
	  0 as id_origen,
	  18 as numtype,
	  mdt_docname as tipo,
	  case
		  when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_debit, get_localcur())
		  when mac1.ac1_cord = 1 then get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_credit, get_localcur())
	  end	 as total_doc,
	  get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
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
	  where mac1.ac1_legal_num = :cardcode
	  and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0", array(
		  ':cardcode' => $request['cardcode'],
		  ':currency' => $request['currency']));

  		if(isset($resSelect[0])){

  			$respuesta = array(
  				'error'  => false,
  				'data'   => $resSelect,
  				'mensaje'=> 'ok'
  			);
  		}else{
				$respuesta = array(
	  			'error'  => true,
	  			'data'   => false,
	  			'mensaje'=> 'No se encontraron registro para pagar'
	  		);
			}

  		$this->response($respuesta);

    }
}
