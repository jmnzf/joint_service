<?php

// OPERACIONES DE AYUDA

defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Helpers extends REST_Controller {

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

  //Obtener Campos para Llenar Input Select
	public function getFiels_post(){

        $Data = $this->post();

        if(!isset($Data['table_name']) OR
           !isset($Data['table_camps']) OR
           !isset($Data['camps_order']) OR
           !isset($Data['order'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

				$filtro = "";
        $limite = "";
				if(isset($Data['filter'])){$filtro = $Data['filter'];}

        if(isset($Data['limit'])){$limite = $Data['limit'];}

        $sqlSelect = " SELECT ".$Data['table_camps']." FROM ".$Data['table_name']." WHERE 1=1 ".$filtro. " ORDER BY ".$Data['camps_order']." ".$Data['order']." ".$limite." ";

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

  public function get_Query_post(){

    $Data = $this->post();

    if(!isset($Data['tabla']) OR
       !isset($Data['campos']) OR
       !isset($Data['where'])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = " SELECT ".$Data['campos']." FROM ".$Data['tabla']." WHERE ".$Data['where']." ";

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

  // OBTENER DATOS DE EMPLEADO DE DEPARTAMENTO DE VENTA
  public function getVendorData_post(){

    $Data = $this->post();

    if(!isset($Data['mev_id'])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'El empleado del departamento de venta NO EXISTE'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }
		
				$sqlSelect = "SELECT v.mev_id, v.mev_prc_code, v.mev_dpj_pj_code, v.mev_dun_un_code,  v.mev_whs_code, w.dws_code,
								d.dpj_pj_code, d2.dun_un_code,d3.dcc_prc_code
								FROM pgus u
								inner join dmev v
								on u.pgu_id_vendor = v.mev_id
								inner join dmws w
								on v.mev_whs_code = w.dws_code
								inner join dmpj d
								on v.mev_dpj_pj_code = d.dpj_pj_code
								inner join dmun d2
								on v.mev_dun_un_code = d2.dun_un_code
								inner join dmcc d3
								on v.mev_prc_code = d3.dcc_prc_code
								where u.pgu_id_usuario = :iduservendor";
    // $sqlSelect = "SELECT v.mev_id, v.mev_prc_code, v.mev_dpj_pj_code, v.mev_dun_un_code,  v.mev_whs_code, w.dws_code,
		// 							d.dpj_pj_code, d2.dun_un_code,d3.dcc_prc_code
		// 							FROM pgus u
		// 							inner join dmev v
		// 							on u.pgu_id_vendor = v.mev_id
		// 							inner join dmws w
		// 							on v.mev_whs_code = cast (w.dws_id as varchar)
		// 							inner join dmpj d
		// 							on v.mev_dpj_pj_code = cast(d.dpj_id as varchar)
		// 							inner join dmun d2
		// 							on v.mev_dun_un_code = cast (d2.dun_id as varchar)
		// 							inner join dmcc d3
		// 							on v.mev_prc_code = cast(d3.dcc_id as varchar)
		// 							where u.pgu_id_usuario = :iduservendor";


    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':iduservendor'=> $Data['mev_id']));

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

// OBTENER SERIE VENDEDOR
public function getVendorSerie_post(){

	$Data = $this->post();

	if(!isset($Data['pgu_id_usuario']) OR !isset($Data['dnd_doctype'])){

		$respuesta = array(
			'error' => true,
			'data'  => array(),
			'mensaje' =>'Informacion enviada no valida'
		);

		$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		return;
	}

	$sqlSelect = "SELECT v.mev_id, v.mev_prc_code, v.mev_dpj_pj_code, v.mev_dun_un_code,  v.mev_whs_code,
								d.dnd_serie, pg.pgs_nextnum + 1 as pgs_nextnum, pg.pgs_last_num
								FROM pgus u
								inner join dmev v
								on u.pgu_id_vendor = v.mev_id
								inner join  dmnd d
								on u.pgu_code_user = d.dnd_user
								inner join pgdn pg
								on pg.pgs_id = d.dnd_serie
								where u.pgu_code_user = :pgu_code_user
								and d.dnd_doctype = :dnd_doctype
								group by v.mev_id, v.mev_prc_code, v.mev_dpj_pj_code, v.mev_dun_un_code,  v.mev_whs_code,
								d.dnd_serie, pg.pgs_nextnum, pg.pgs_last_num
								having((pgs_nextnum + 1) < pgs_last_num)";

	// $sqlSelect = "SELECT v.mev_id, v.mev_prc_code, v.mev_dpj_pj_code, v.mev_dun_un_code,  v.mev_whs_code,
	// 							d.dnd_serie
	// 							FROM pgus u
	// 							inner join dmev v
	// 							on u.pgu_id_vendor = v.mev_id
	// 							inner join  dmnd d
	// 							on u.pgu_code_user = d.dnd_user
	// 							where u.pgu_id_usuario = :pgu_id_usuario
	// 							and d.dnd_doctype = :dnd_doctype";


	$resSelect = $this->pedeo->queryTable($sqlSelect, array(':pgu_code_user'=> $Data['pgu_id_usuario'],':dnd_doctype'=> $Data['dnd_doctype']));

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
