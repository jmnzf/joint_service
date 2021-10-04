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


      $resSelect = $this->pedeo->queryTable("SELECT 15 AS tipo,
                                           	  t0.cfc_cardcode codigo_proveedor,
                                              t0.cfc_docentry AS id_origen,
                                              t0.cfc_docdate AS fecha_doc,
																							t0.cfc_duedate fecha_ven,
                                              CURRENT_DATE - t0.cfc_duedate AS dias_atrasado,
																							t0.cfc_doctotal AS total_doc,
                                              t1.ret_vlret AS retencion,
                                              t0.cfc_doctotal - t0.cfc_paytoday AS saldo_venc,
                                          	  t2.tsa_value tasa_dia,
                                          	  t0.cfc_currency,
																							t0.cfc_comment comentario
                                             FROM dcfc t0
                                             LEFT JOIN dret t1 ON t0.cfc_docentry = t1.ret_absentry
                                          	 left join tasa t2 on t0.cfc_currency = t2.tsa_curro and t0.cfc_createat = t2.tsa_date
                                             WHERE t0.cfc_cardcode = :cardcode 

                                             UNION ALL

                                             SELECT 16 AS tipo,
                                           	  t0.cnd_cardcode codigo_proveedor,
                                              t0.cnd_docentry AS id_origen,
                                              t0.cnd_docdate AS fecha_doc,
																							t0.cnd_duedate fecha_ven,
                                              CURRENT_DATE - t0.cnd_duedate AS dias_atrasado,
                                              t0.cnd_doctotal AS total_doc,
                                              t1.ret_vlret AS retencion,
                                              0 AS saldo_venc,
                                             t2.tsa_value tasa_dia,
                                          	 t0.cnd_currency,
																						 t0.cnd_comment comentario
                                             FROM dcnd t0
                                             LEFT JOIN dret t1 ON t0.cnd_docentry = t1.ret_absentry
                                          	 left join tasa t2 on t0.cnd_currency = t2.tsa_curro and t0.cnd_createat = t2.tsa_date
                                             WHERE t0.cnd_cardcode = :cardcode
                                             UNION ALL

                                             SELECT 17 AS tipo,
                                           	 t0.cnc_cardcode codigo_proveedor,
                                             t0.cnc_docentry AS id_origen,
                                             t0.cnc_docdate AS fecha_doc,
																						 t0.cnc_duedate fecha_ven,
                                             CURRENT_DATE - t0.cnc_duedate AS dias_atrasado,
                                             t0.cnc_doctotal AS total_doc,
                                             t1.ret_vlret AS retencion,
                                             0 AS saldo_venc,
                                          	 t2.tsa_value tasa_dia,
                                           	 t0.cnc_currency,
																						 t0.cnc_comment comentario
                                             FROM dcnc t0
                                             LEFT JOIN dret t1 ON t0.cnc_docentry = t1.ret_absentry
                                          	 left join tasa t2 on t0.cnc_currency = t2.tsa_curro and t0.cnc_createat = t2.tsa_date
                                             WHERE t0.cnc_cardcode = :cardcode",
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
