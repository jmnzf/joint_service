<?php
// DATOS MAESTROS ACIENTOS CONTABLES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Indicators extends REST_Controller {

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
    // OBTENER VALOR DE PEDIDO DE VENTAS, MES Y AÑO PRESENTE
    public function getSalesOrder_get(){

      $sqlSelect = "SELECT
                  	   SUM(vov_doctotal) suma_venta_pedido
                    FROM dvov
                    WHERE EXTRACT(MONTH FROM vov_docdate) = EXTRACT(MONTH FROM CURRENT_DATE)
                    AND EXTRACT(YEAR FROM vov_createat) = EXTRACT(YEAR FROM CURRENT_DATE)";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array());

      if(isset($resSelect[0])){

        $respuesta = array(
          'error' => false,
          'data'  => $resSelect,
          'mensaje' => '');

      }else{

          $respuesta = array(
            'error'   => true,
            'data' => array(),
            'mensaje'	=> 'busqueda sin resultados'
          );

      }

       $this->response($respuesta);
     }

     // OBTENER VALOR DE COMPRAS, MES Y AÑO PRESENTE
     public function getPurchaseOrder_get(){

       $sqlSelect = "SELECT
                   	   SUM(cpo_doctotal) suma_compra
                     FROM dcpo
                     WHERE EXTRACT(MONTH FROM cpo_docdate) = EXTRACT(MONTH FROM CURRENT_DATE)
                     AND EXTRACT(YEAR FROM cpo_createat) = EXTRACT(YEAR FROM CURRENT_DATE)";

       $resSelect = $this->pedeo->queryTable($sqlSelect, array());

       if(isset($resSelect[0])){

         $respuesta = array(
           'error' => false,
           'data'  => $resSelect,
           'mensaje' => '');

       }else{

           $respuesta = array(
             'error'   => true,
             'data' => array(),
             'mensaje'	=> 'busqueda sin resultados'
           );

       }

        $this->response($respuesta);
      }

      // OBTENER UNIDADES VENDIDAS, MES Y AÑO PRESENTE
      public function getSoldUnits_get(){

        $sqlSelect = "SELECT
                          SUM(t0.ov1_quantity) suma_und_vendidas
                      FROM vov1 t0
                      LEFT JOIN dvov t1 ON t0.ov1_docentry = t1.vov_docentry
                      WHERE EXTRACT(MONTH FROM t1.vov_docdate) = EXTRACT(MONTH FROM CURRENT_DATE)
                      AND EXTRACT(YEAR FROM t1.vov_createat) = EXTRACT(YEAR FROM CURRENT_DATE)";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if(isset($resSelect[0])){

          $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => '');

        }else{

            $respuesta = array(
              'error'   => true,
              'data' => array(),
              'mensaje'	=> 'busqueda sin resultados'
            );

        }

         $this->response($respuesta);
       }

       // OBTENER UNIDADES VENDIDAS, MES Y AÑO PRESENTE
       public function getAvgU_get(){

         $sqlSelect = "SELECT
                          ROUND((sum(t2.bdi_avgprice) / sum(t0.ov1_linetotal)) * 100,0) utilidad
                       FROM vov1 t0
                       LEFT JOIN dvov t1 ON t0.ov1_docentry = t1.vov_docentry
                       LEFT JOIN tbdi t2 ON t0.ov1_itemcode = t2.bdi_itemcode
                       WHERE EXTRACT(MONTH FROM t1.vov_docdate) = EXTRACT(MONTH FROM CURRENT_DATE)
                       AND EXTRACT(YEAR FROM t1.vov_createat) = EXTRACT(YEAR FROM CURRENT_DATE)";

         $resSelect = $this->pedeo->queryTable($sqlSelect, array());

         if(isset($resSelect[0])){

           $respuesta = array(
             'error' => false,
             'data'  => $resSelect,
             'mensaje' => '');

         }else{

             $respuesta = array(
               'error'   => true,
               'data' => array(),
               'mensaje'	=> 'busqueda sin resultados'
             );

         }

          $this->response($respuesta);
        }

}
