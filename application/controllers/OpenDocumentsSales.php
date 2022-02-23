<?php
// Entrega de Ventas
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class OpenDocumentsSales extends REST_Controller {

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
	public function OpenQuotation_post(){


            $sqlSelect = "select
                                    a.*
                                from dvct a
                                join responsestatus b on a.dvc_docentry = b.id and a.dvc_doctype = b.tipo
                                where b.estado = 'Abierto' order by a.dvc_docentry asc";


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
                                      from dvov a
                                      join responsestatus b on a.vov_docentry = b.id and a.vov_doctype = b.tipo
                                      where b.estado = 'Abierto'
                                      order by a.vov_docentry asc";


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


          public function OpenDelivery_post(){


                    $sqlSelect = "select
                                              a.*
                                          from dvem a
                                          join responsestatus b on a.vem_docentry = b.id and a.vem_doctype = b.tipo
                                          where b.estado = 'Abierto'
                                          order by a.vem_docentry asc";


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



              public function OpenInvoices_post(){


                        $sqlSelect = "select
                                                  a.*
                                              from dvfv a
                                              join responsestatus b on a.dvf_docentry = b.id and a.dvf_doctype = b.tipo
                                              where b.estado = 'Abierto'
                                              order by a.dvf_docentry asc";


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
                                                  from dvnc a
                                                  join responsestatus b on a.vnc_docentry = b.id and a.vnc_doctype = b.tipo
                                                  where b.estado = 'Abierto'
                                                  order by a.vnc_docentry asc";


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
                                                      from dvnd a
                                                      join responsestatus b on a.vnd_docentry = b.id and a.vnd_doctype = b.tipo
                                                      where b.estado = 'Abierto'
                                                      order by a.vnd_docentry asc";


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
                                                          from dvdv a
                                                          join responsestatus b on a.vdv_docentry = b.id and a.vdv_doctype = b.tipo
                                                          where b.estado = 'Abierto'
                                                          order by a.vdv_docentry asc";


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
