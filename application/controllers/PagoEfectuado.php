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


      $resSelect = $this->pedeo->queryTable("SELECT mdt_docname tipo,
       cfc_docnum id_origen,
       cfc_cardcode codigo_proveedor,
       cfc_docentry,
       ac1_account cuenta,
       cfc_docdate fecha_doc,
       cfc_duedate fecha_ven,
       CURRENT_DATE - cfc_duedate dias_atrasado,
       cfc_doctotal total_doc,
       saldo saldo_venc,
       cfc_doctype numType,
       tsa_value tasa_dia,
       '' retencion,
       cfc_currency,
	ac1_font_key,
       cfc_comment
FROM dcfc
JOIN SALDO_DOC on cfc_docentry = ac1_font_key and cfc_doctype = ac1_font_type
join dmdt on cfc_doctype = mdt_doctype
join tasa on cfc_docdate = tsa_date
where  cfc_cardcode = :cardcode
union all
SELECT mdt_docname tipo,
       vnc_docnum id_origen,
       vnc_cardcode codigo_proveedor,
       vnc_docentry,
       ac1_account cuenta,
       vnc_docdate fecha_doc,
       vnc_duedate fecha_ven,
       CURRENT_DATE - vnc_duedate dias_atrasado,
       vnc_doctotal total_doc,
       saldo saldo_venc,
       vnc_doctype numType,
       tsa_value tasa_dia,
       '' retencion,
       vnc_currency,
       ac1_font_key,
       vnc_comment
FROM dvnc
JOIN SALDO_DOC on vnc_docentry = ac1_font_key and vnc_doctype = ac1_font_type
join dmdt on vnc_doctype = mdt_doctype
join tasa on vnc_docdate = tsa_date
where  vnc_cardcode = :cardcode
union all
SELECT mdt_docname tipo,
       cnd_docnum id_origen,
       cnd_cardcode codigo_proveedor,
       cnd_docentry,
       ac1_account cuenta,
       cnd_docdate fecha_doc,
       cnd_duedate fecha_ven,
       CURRENT_DATE - cnd_duedate dias_atrasado,
       cnd_doctotal total_doc,
       saldo saldo_venc,
       cnd_doctype numType,
       tsa_value tasa_dia,
       '' retencion,
       cnd_currency,
       ac1_font_key,
       cnd_comment
FROM dcnd
JOIN SALDO_DOC on cnd_docentry = ac1_font_key and cnd_doctype = ac1_font_type
join dmdt on cnd_doctype = mdt_doctype
join tasa on cnd_docdate = tsa_date
where  cnd_cardcode = :cardcode
union all
SELECT mdt_docname tipo,
       bpe_docnum id_origen,
       bpe_cardcode codigo_proveedor,
       bpe_docentry,
       ac1_account cuenta,
       bpe_docdate fecha_doc,
       bpe_docdate fecha_ven,
       CURRENT_DATE - bpe_docdate dias_atrasado,
       bpe_doctotal total_doc,
       saldo saldo_venc,
       bpe_doctype numType,
       tsa_value tasa_dia,
       '' retencion,
       bpe_currency,
       ac1_font_key,
       bpe_comments
FROM gbpe
JOIN SALDO_DOC on bpe_docentry = ac1_font_key and bpe_doctype = ac1_font_type
join dmdt on bpe_doctype = mdt_doctype
join tasa on bpe_docdate = tsa_date
where bpe_cardcode = :cardcode" ,
      array(':cardcode' => $request['cardcode']));

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
