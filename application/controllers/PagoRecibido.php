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
	       dvf_docnum id_origen,
	       dvf_cardcode codigo_proveedor,
	       dvf_docentry,
	       ac1_account cuenta,
	       dvf_docdate fecha_doc,
	       dvf_duedate fecha_ven,
	       CURRENT_DATE - dvf_duedate dias_atrasado,
	       dvf_doctotal total_doc,
	       saldo saldo_venc,
	       dvf_doctype numType,
	       tsa_value tasa_dia,
	       '' retencion,
	       dvf_currency,
				 ac1_font_key,
	       dvf_comment
						FROM dvfv
						JOIN SALDO_DOC on dvf_docentry = ac1_font_key and dvf_doctype = ac1_font_type
						join dmdt on dvf_doctype = mdt_doctype
						join tasa on dvf_docdate = tsa_date
						where dvf_cardcode = :cardcode
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
						       vnd_docnum id_origen,
						       vnd_cardcode codigo_proveedor,
						       vnd_docentry,
						       ac1_account cuenta,
						       vnd_docdate fecha_doc,
						       vnd_duedate fecha_ven,
						       CURRENT_DATE - vnd_duedate dias_atrasado,
						       vnd_doctotal total_doc,
						       saldo saldo_venc,
						       vnd_doctype numType,
						       tsa_value tasa_dia,
						       '' retencion,
						       vnd_currency,
									 ac1_font_key,
						       vnd_comment
						FROM dvnd
						JOIN SALDO_DOC on vnd_docentry = ac1_font_key and vnd_doctype = ac1_font_type
						join dmdt on vnd_doctype = mdt_doctype
						join tasa on vnd_docdate = tsa_date
						where  vnd_cardcode = :cardcode
						union all
						SELECT mdt_docname tipo,
						       bpr_docnum id_origen,
						       bpr_cardcode codigo_proveedor,
						       bpr_docentry,
						       ac1_account cuenta,
						       bpr_docdate fecha_doc,
						       bpr_docdate fecha_ven,
						       CURRENT_DATE - bpr_docdate dias_atrasado,
						       bpr_doctotal total_doc,
						       saldo saldo_venc,
						       bpr_doctype numType,
						       tsa_value tasa_dia,
						       '' retencion,
						       bpr_currency,
									 ac1_font_key,
						       bpr_comments
						FROM gbpr
						JOIN SALDO_DOC on bpr_docentry = ac1_font_key and bpr_doctype = ac1_font_type
						join dmdt on bpr_doctype = mdt_doctype
						join tasa on bpr_docdate = tsa_date
						where bpr_cardcode = :cardcode", array(':cardcode' => $request['cardcode']));

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
