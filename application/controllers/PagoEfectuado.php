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


      $resSelect = $this->pedeo->queryTable("SELECT cfc_docnum id_origen,
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
       cfc_currency,
       mac_comments comentario,
       cfc_doctotal total_doc,
       sum(ac1_debit) - sum(ac1_credit)  as saldo_venc
			from mac1
			join dacc on ac1_account = acc_code
			left join dmdt
			on ac1_font_type = mdt_doctype
            left join dcfc
			on cfc_doctype = ac1_font_type and cfc_docentry = ac1_font_key
			left join dcnd
			on cnd_doctype = ac1_font_type and cnd_docentry = ac1_font_key
			left join dcnc
			on cnc_doctype = ac1_font_type and cnc_docentry = ac1_font_key
            left join tmac
			on mac_base_type = ac1_font_type and mac_base_entry = ac1_font_key
			left join tasa
			on tsa_date = CURRENT_DATE
where mdt_docname like 'Factura de Proveedores%' and acc_businessp = '1' and ac1_font_type in(15,16,17) and ac1_legal_num = :cardcode
group by ac1_account, ac1_font_key, ac1_font_type, ac1_legal_num, mdt_docname, cfc_docnum, cfc_cardcode, mac_doc_duedate, mac_doc_date, tsa_value, cfc_currency, mac_comments, cfc_doctotal
having  sum(ac1_debit) - sum(ac1_credit)  <> 0" ,
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
