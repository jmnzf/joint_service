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


      $resSelect = $this->pedeo->queryTable("SELECT 5 AS tipo,
                                            t0.dvf_cardcode codigo_proveedor,
                                            t0.dvf_docentry AS id_origen,
                                            t0.dvf_docdate AS fecha_doc,
                                            t0.dvf_duedate fecha_ven,
                                            CURRENT_DATE - t0.dvf_duedate AS dias_atrasado,
                                            t0.dvf_doctotal AS total_doc,
                                            t1.ret_vlret AS retencion,
                                            t0.dvf_doctotal - t0.dvf_paytoday AS saldo_venc,
                                            t2.tsa_value tasa_dia,
                                            t0.dvf_currency,
                                            t0.dvf_comment comentario
                                           FROM dvfv t0
                                           LEFT JOIN dret t1 ON t0.dvf_docentry = t1.ret_absentry
                                           left join tasa t2 on t0.dvf_currency = t2.tsa_curro and t0.dvf_createat = t2.tsa_date
                                           WHERE t0.dvf_cardcode = :cardcode

                                           UNION ALL

                                           SELECT 6 AS tipo,
                                            t0.vnd_cardcode codigo_proveedor,
                                            t0.vnd_docentry AS id_origen,
                                            t0.vnd_docdate AS fecha_doc,
                                            t0.vnd_duedate fecha_ven,
                                            CURRENT_DATE - t0.vnd_duedate AS dias_atrasado,
                                            t0.vnd_doctotal AS total_doc,
                                            t1.ret_vlret AS retencion,
                                            0 AS saldo_venc,
                                            t2.tsa_value tasa_dia,
                                            t0.vnd_currency,
                                            t0.vnd_comment comentario
                                           FROM dvnd t0
                                           LEFT JOIN dret t1 ON t0.vnd_docentry = t1.ret_absentry
                                           left join tasa t2 on t0.vnd_currency = t2.tsa_curro and t0.vnd_createat = t2.tsa_date
                                           WHERE t0.vnd_cardcode = :cardcode
                                           UNION ALL

                                           SELECT 7 AS tipo,
                                           t0.vnc_cardcode codigo_proveedor,
                                           t0.vnc_docentry AS id_origen,
                                           t0.vnc_docdate AS fecha_doc,
                                           t0.vnc_duedate fecha_ven,
                                           CURRENT_DATE - t0.vnc_duedate AS dias_atrasado,
                                           t0.vnc_doctotal AS total_doc,
                                           t1.ret_vlret AS retencion,
                                           0 AS saldo_venc,
                                           t2.tsa_value tasa_dia,
                                           t0.vnc_currency,
                                           t0.vnc_comment comentario
                                           FROM dvnc t0
                                           LEFT JOIN dret t1 ON t0.vnc_docentry = t1.ret_absentry
                                          left join tasa t2 on t0.vnc_currency = t2.tsa_curro and t0.vnc_createat = t2.tsa_date
                                           WHERE t0.vnc_cardcode = :cardcode",
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
