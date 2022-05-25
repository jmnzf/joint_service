<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PagoEfectuado extends REST_Controller {

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
	public function PagoEfectuado_post(){

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
	  CURRENT_DATE - cfc_duedate dias_atrasado,
	  dcfc.cfc_comment,
	  dcfc.cfc_currency,
	  mac1.ac1_font_key as dvf_docentry,
	  dcfc.cfc_docnum,
	  dcfc.cfc_docdate as fecha_doc,
	  dcfc.cfc_duedate as fecha_ven,
	  cfc_docnum as id_origen,
	  mac1.ac1_font_type as numtype,
	  mdt_docname as tipo,
	  case
	  when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,mac1.ac1_credit, get_localcur())
	  else get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,mac1.ac1_debit, get_localcur())
	  end	 as total_doc,
	  get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_credit) , get_localcur()) as saldo_venc,
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
	  where mac1.ac1_legal_num = :cardcode
	  and ABS((mac1.ac1_ven_credit) - (mac1.ac1_ven_debit)) > 0
	  --PAGO EFECTUADO
	  union all
	  select distinct
	  mac1.ac1_font_key,
	  mac1.ac1_legal_num as codigo_proveedor,
	  mac1.ac1_account as cuenta,
	  CURRENT_DATE - gbpe.bpe_docdate as dias_atrasado,
	  gbpe.bpe_comments as bpr_comment,
	  gbpe.bpe_currency,
	  mac1.ac1_font_key as dvf_docentry,
	  gbpe.bpe_docnum,
	  gbpe.bpe_docdate as fecha_doc,
	  gbpe.bpe_docdate as fecha_ven,
	  gbpe.bpe_docnum as id_origen,
	  mac1.ac1_font_type as numtype,
	  mdt_docname as tipo,
	  case
	  when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,mac1.ac1_debit, get_localcur())
	  else get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,mac1.ac1_debit, get_localcur())
	  end	 as total_doc,
	  get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) , get_localcur()) as saldo_venc,
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
	  where mac1.ac1_legal_num = :cardcode
	  and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
	  --NOTA CREDITO
	  union all
	  select distinct
	  mac1.ac1_font_key,
	  mac1.ac1_legal_num as codigo_proveedor,
	  mac1.ac1_account as cuenta,
	  CURRENT_DATE - dcnc.cnc_docdate as dias_atrasado,
	  dcnc.cnc_comment as bpr_comment,
	  dcnc.cnc_currency,
	  mac1.ac1_font_key as dvf_docentry,
	  dcnc.cnc_docnum,
	  dcnc.cnc_docdate as fecha_doc,
	  dcnc.cnc_duedate as fecha_ven,
	  dcnc.cnc_docnum as id_origen,
	  mac1.ac1_font_type as numtype,
	  mdt_docname as tipo,
	  case
	  when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,mac1.ac1_debit, get_localcur())
	  else get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,mac1.ac1_debit, get_localcur())
	  end	 as total_doc,
	  get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) , get_localcur()) as saldo_venc,
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
	  where mac1.ac1_legal_num = :cardcode
	  and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
	  --NOTA DEBITO
	  union all
	  select distinct
	  mac1.ac1_font_key,
	  mac1.ac1_legal_num as codigo_proveedor,
	  mac1.ac1_account as cuenta,
	  CURRENT_DATE - dcnd.cnd_docdate as dias_atrasado,
	  dcnd.cnd_comment as bpr_comment,
	  dcnd.cnd_currency,
	  mac1.ac1_font_key as dvf_docentry,
	  dcnd.cnd_docnum,
	  dcnd.cnd_docdate as fecha_doc,
	  dcnd.cnd_duedate as fecha_ven,
	  dcnd.cnd_docnum as id_origen,
	  mac1.ac1_font_type as numtype,
	  mdt_docname as tipo,
	  case
	  when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,mac1.ac1_debit, get_localcur())
	  else get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,mac1.ac1_credit, get_localcur())
	  end	 as total_doc,
	  get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,(mac1.ac1_ven_credit) - (mac1.ac1_debit), get_localcur()) as saldo_venc,
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
	  where mac1.ac1_legal_num = :cardcode
	  and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
	  --ASIENTOS MANUALES
	  union all
	  select distinct
	  mac1.ac1_font_key,
	  case
		  when ac1_card_type = '1' then mac1.ac1_legal_num
		  when ac1_card_type = '2' then mac1.ac1_legal_num
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
	  and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0",array(
		 ':cardcode' => $request['cardcode'],
		 ':currency' => $request['currency']));

  		$respuesta = array(
  			'error'  => true,
  			'data'   => false,
  			'mensaje'=> 'No se encontraron registro para pagar'
  		);

  		if(isset($resSelect[0])){

  			$respuesta = array(
  				'error'  => false,
  				'data'   => $resSelect,
  				'mensaje'=> 'ok'
  			);
  		}

  		$this->response($respuesta);

    }
}
