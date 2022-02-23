<?php
// Entrega de Ventas
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class OpenDocumentsPurchase extends REST_Controller {

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

  //CREAR NUEVA Entrega de Ventas
	public function OpenRequest_post(){


            $sqlSelect = "select
                                    a.*
                                from dcsc a
                                join responsestatus b on a.csc_docentry = b.id and a.csc_doctype = b.tipo
                                where b.estado = 'Abierto' order by a.csc_docentry asc";


            $resSelect = $this->pedeo->queryTable($sqlSelect, array());

            if(isset($resSelect[0])){

              $respuesta = array(
                'error' => false,
                'data'  => $resSelect,
                'mensaje' => 'OK');

            }else{

                $respuesta = array(
                  'error'   => true,
                  'data' => array(),
                  'mensaje'	=> 'busqueda sin resultados'
                );

            }

             $this->response($respuesta);
      }

      public function OpenOrder_post(){


                $sqlSelect = "select
                                          a.*
                                      from dcpo a
                                      join responsestatus b on a.cpo_docentry = b.id and a.cpo_doctype = b.tipo
                                      where b.estado = 'Abierto'
                                      order by a.cpo_docentry asc";


                $resSelect = $this->pedeo->queryTable($sqlSelect, array());

                if(isset($resSelect[0])){

                  $respuesta = array(
                    'error' => false,
                    'data'  => $resSelect,
                    'mensaje' => 'OK');

                }else{

                    $respuesta = array(
                      'error'   => true,
                      'data' => array(),
                      'mensaje'	=> 'busqueda sin resultados'
                    );

                }

                 $this->response($respuesta);
          }


          public function OpenEntry_post(){


                    $sqlSelect = "select
                                              a.*
                                          from dcec a
                                          join responsestatus b on a.cec_docentry = b.id and a.cec_doctype = b.tipo
                                          where b.estado = 'Abierto'
                                          order by a.cec_docentry asc";


                    $resSelect = $this->pedeo->queryTable($sqlSelect, array());

                    if(isset($resSelect[0])){

                      $respuesta = array(
                        'error' => false,
                        'data'  => $resSelect,
                        'mensaje' => 'OK');

                    }else{

                        $respuesta = array(
                          'error'   => true,
                          'data' => array(),
                          'mensaje'	=> 'busqueda sin resultados'
                        );

                    }

                     $this->response($respuesta);
              }



              public function OpenInvoice_post(){


                        $sqlSelect = "select
                                                  a.*
                                              from dcfc a
                                              join responsestatus b on a.cfc_docentry = b.id and a.cfc_doctype = b.tipo
                                              where b.estado = 'Abierto'
                                              order by a.cfc_docentry asc";


                        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

                        if(isset($resSelect[0])){

                          $respuesta = array(
                            'error' => false,
                            'data'  => $resSelect,
                            'mensaje' => 'OK');

                        }else{

                            $respuesta = array(
                              'error'   => true,
                              'data' => array(),
                              'mensaje'	=> 'busqueda sin resultados'
                            );

                        }

                         $this->response($respuesta);
                  }

                  public function OpenCreditNote_post(){


                            $sqlSelect = "select
                                                      a.*
                                                  from dcnc a
                                                  join responsestatus b on a.cnc_docentry = b.id and a.cnc_doctype = b.tipo
                                                  where b.estado = 'Abierto'
                                                  order by a.cnc_docentry asc";


                            $resSelect = $this->pedeo->queryTable($sqlSelect, array());

                            if(isset($resSelect[0])){

                              $respuesta = array(
                                'error' => false,
                                'data'  => $resSelect,
                                'mensaje' => 'OK');

                            }else{

                                $respuesta = array(
                                  'error'   => true,
                                  'data' => array(),
                                  'mensaje'	=> 'busqueda sin resultados'
                                );

                            }

                             $this->response($respuesta);
                      }


                      public function OpenDebitNote_post(){


                                $sqlSelect = "select
                                                          a.*
                                                      from dcnd a
                                                      join responsestatus b on a.cnd_docentry = b.id and a.cnd_doctype = b.tipo
                                                      where b.estado = 'Abierto'
                                                      order by a.cnd_docentry asc";


                                $resSelect = $this->pedeo->queryTable($sqlSelect, array());

                                if(isset($resSelect[0])){

                                  $respuesta = array(
                                    'error' => false,
                                    'data'  => $resSelect,
                                    'mensaje' => 'OK');

                                }else{

                                    $respuesta = array(
                                      'error'   => true,
                                      'data' => array(),
                                      'mensaje'	=> 'busqueda sin resultados'
                                    );

                                }

                                 $this->response($respuesta);
                          }

                          public function OpenDevo_post(){


                                    $sqlSelect = "select
                                                              a.*
                                                          from dcdc a
                                                          join responsestatus b on a.cdc_docentry = b.id and a.cdc_doctype = b.tipo
                                                          where b.estado = 'Abierto'
                                                          order by a.cdc_docentry asc";


                                    $resSelect = $this->pedeo->queryTable($sqlSelect, array());

                                    if(isset($resSelect[0])){

                                      $respuesta = array(
                                        'error' => false,
                                        'data'  => $resSelect,
                                        'mensaje' => 'OK');

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
