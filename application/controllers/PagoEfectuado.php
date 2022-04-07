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

      if(!isset($request['cardcode'])){

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
	when mac1.ac1_font_type = 15 then mac1.ac1_credit
	else mac1.ac1_debit
end	 as total_doc,
(mac1.ac1_ven_debit) - (mac1.ac1_credit)  as saldo_venc,
'' retencion,
tasa.tsa_value as tasa_dia
from  mac1
inner join dacc
on mac1.ac1_account = dacc.acc_code
and acc_businessp = '1'
inner join dmdt
on mac1.ac1_font_type = dmdt.mdt_doctype
inner join dcfc
on dcfc.cfc_doctype = mac1.ac1_font_type
and dcfc.cfc_docentry = mac1.ac1_font_key
inner join  tasa on dcfc.cfc_currency = tasa.tsa_curro and dcfc.cfc_docdate = tasa.tsa_date and tasa.tsa_curro != tasa.tsa_currd
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
	when mac1.ac1_font_type = 15 then mac1.ac1_debit
	else mac1.ac1_debit
end	 as total_doc,
(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo_venc,
'' retencion,
tasa.tsa_value as tasa_dia
from  mac1
inner join dacc
on mac1.ac1_account = dacc.acc_code
and acc_businessp = '1'
inner join dmdt
on mac1.ac1_font_type = dmdt.mdt_doctype
inner join gbpe
on gbpe.bpe_doctype = mac1.ac1_font_type
and gbpe.bpe_docentry = mac1.ac1_font_key
inner join  tasa on gbpe.bpe_currency = tasa.tsa_curro and gbpe.bpe_docdate = tasa.tsa_date and tasa.tsa_curro != tasa.tsa_currd
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
	when mac1.ac1_font_type = 15 then mac1.ac1_debit
	else mac1.ac1_debit
end	 as total_doc,
(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) as saldo_venc,
'' retencion,
tasa.tsa_value as tasa_dia
from  mac1
inner join dacc
on mac1.ac1_account = dacc.acc_code
and acc_businessp = '1'
inner join dmdt
on mac1.ac1_font_type = dmdt.mdt_doctype
inner join dcnc
on dcnc.cnc_doctype = mac1.ac1_font_type
and dcnc.cnc_docentry = mac1.ac1_font_key
inner join  tasa on dcnc.cnc_currency = tasa.tsa_curro and dcnc.cnc_docdate = tasa.tsa_date and tasa.tsa_curro != tasa.tsa_currd
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
	when mac1.ac1_font_type = 15 then mac1.ac1_debit
	else mac1.ac1_credit
end	 as total_doc,
(mac1.ac1_ven_credit) - (mac1.ac1_debit) as saldo_venc,
'' retencion,
tasa.tsa_value as tasa_dia
from  mac1
inner join dacc
on mac1.ac1_account = dacc.acc_code
and acc_businessp = '1'
inner join dmdt
on mac1.ac1_font_type = dmdt.mdt_doctype
inner join dcnd
on dcnd.cnd_doctype = mac1.ac1_font_type
and dcnd.cnd_docentry = mac1.ac1_font_key
inner join  tasa on dcnd.cnd_currency = tasa.tsa_curro and dcnd.cnd_docdate = tasa.tsa_date and tasa.tsa_curro != tasa.tsa_currd
where mac1.ac1_legal_num = :cardcode
and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0",array(':cardcode' => $request['cardcode']));

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
