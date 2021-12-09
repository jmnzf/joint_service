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


      $resSelect = $this->pedeo->queryTable("SELECT
       dvf_docnum id_origen,
       ac1_legal_num codigo_proveedor,
       ac1_account cuenta,
       ac1_font_key ,
       CURRENT_DATE - mac_doc_duedate AS dias_atrasado,
       ac1_font_type numType,
       mdt_docname tipo,
       mac_doc_date fecha_doc,
       ' ' retencion,
        mac_doc_duedate fecha_ven,
       tsa_value tasa_dia,
       dvf_currency,
       mac_comments comentario,
       dvf_doctotal total_doc,
       sum(ac1_debit) - sum(ac1_credit)  as saldo_venc
			from mac1
			join dacc on ac1_account = acc_code
			left join dmdt
			on ac1_font_type = mdt_doctype
			left join dvfv
			on dvf_doctype = ac1_font_type and dvf_docentry = ac1_font_key
			left join dcfc
			on cfc_doctype = ac1_font_type and cfc_docentry = ac1_font_key
			left join dvnd
			on vnd_doctype = ac1_font_type and vnd_docentry = ac1_font_key
			left join dvnc
			on vnc_doctype = ac1_font_type and vnc_docentry = ac1_font_key
			left join tmac
			on mac_base_type = ac1_font_type and mac_base_entry = ac1_font_key
			left join tasa
			on tsa_date = CURRENT_DATE
			where acc_businessp = '1' and ac1_font_type <> 0 and ac1_font_type = 5 and ac1_legal_num = :cardcode
			group by ac1_account, ac1_font_key, ac1_font_type, ac1_legal_num, mdt_docname, dvf_docnum, dvf_cardcode, mac_doc_duedate, mac_doc_date, tsa_value, dvf_currency, mac_comments, dvf_doctotal
			having  sum(ac1_debit) - sum(ac1_credit)  <> 0",
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
