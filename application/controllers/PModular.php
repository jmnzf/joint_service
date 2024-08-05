<?php
// PARAMETRIZACION DE MODULARES CLASIFICACION DE PAGINA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PModular extends REST_Controller {

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


	// AGREGA UN PARAMETRO GENERAL A UNA CLASIFICACION
	public function createGeneralParameters_post(){

		$Data = $this->post();

		if(!isset($Data['detail'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$ContenidoDetalle = json_decode($Data['detail'], true);


		$this->pedeo->trans_begin();

		$this->pedeo->queryTable("DELETE FROM mrcp WHERE rcp_classid = :rcp_classid", array(":rcp_classid" => $Data['classification']));

		foreach ($ContenidoDetalle as $key => $value) {

			$sqlInsert = "INSERT INTO mrcp(rcp_paramid, rcp_classid)VALUES(:rcp_paramid, :rcp_classid)";


			$resInsert = $this->pedeo->insertRow($sqlInsert, array(
				':rcp_paramid' =>  $value ,
				':rcp_classid' =>  $Data['classification']
			));



			if(is_numeric($resInsert) && $resInsert > 0){

			}else{

				$this->pedeo->trans_rollback();

				$respuesta = array(
				'error'   => true,
				'data' 		=> $resInsert,
				'mensaje'	=> 'No se pudo registrar la relación de  parametros'
				);
			}
		}

		$this->pedeo->trans_commit();

		$respuesta = array(
			'error' => false,
			'data' => $resInsert,
			'mensaje' =>'Relación de parametros registrada con exito'
		);

		$this->response($respuesta);
	}


	// se crea una lista de parametros especificos para una clasificacion
	public function createSpecificParameters_post(){

		$Data = $this->post();

		if(!isset($Data['detail'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$ContenidoDetalle = json_decode($Data['detail'], true);

		// INICIA lA TRANSACCION

		$this->pedeo->trans_begin();

		$sqlInsertListaPrecio = "INSERT INTO mlpc(lpc_description, lpc_price, lpc_status)VALUES(:lpc_description, :lpc_price, :lpc_status)";

		$resInsertListaPrecio = $this->pedeo->insertRow($sqlInsertListaPrecio, array(

			':lpc_description' => $Data['descripcion'],
			':lpc_price' 			 => $Data['precio'],
			':lpc_status'			 => 1

		));

		if(is_numeric($resInsertListaPrecio) && $resInsertListaPrecio > 0){

			$sqlInsertEncabezado = "INSERT INTO mepe(epe_name, epe_docdate, epe_duedate, epe_createby, epe_status,epe_clascode,epe_listpcode)VALUES(:epe_name, :epe_docdate, :epe_duedate, :epe_createby, :epe_status,:epe_clascode,:epe_listpcode)";
			$resInsertEncabezado = $this->pedeo->insertRow($sqlInsertEncabezado, array(

				':epe_name' 	    => $Data['descripcion'],
				':epe_docdate'    => date('Y-m-d'),
				':epe_duedate'    => $Data['fechav'],
				':epe_createby'   => $Data['user'],
				':epe_clascode'   => $Data['clasificacion'],
				':epe_listpcode' => $resInsertListaPrecio,
				':epe_status'     => 1
			));

			if(is_numeric($resInsertEncabezado) && $resInsertEncabezado > 0){

				foreach ($ContenidoDetalle as $key => $value) {
					 foreach ($value as $key => $detalle) {
					 		$sqlInsertDetalleP = "INSERT INTO epe1(pe1_paramcode,pe1_vparamcode,pe1_epeid)VALUES(:pe1_paramcode,:pe1_vparamcode,:pe1_epeid)";
							$resInsertDetalleP = $this->pedeo->insertRow($sqlInsertDetalleP, array(

								':pe1_paramcode' => $detalle['CodigoParametro'],
								':pe1_vparamcode' => $detalle['ValorParametro'],
								':pe1_epeid'     => $resInsertEncabezado
							));
					 }
				}

				if(is_numeric($resInsertDetalleP) && $resInsertDetalleP > 0){

				}else{

					$this->pedeo->trans_rollback();

					$respuesta = array(
					'error'   => true,
					'data' 		=> $resInsertDetalleP,
					'mensaje'	=> 'No se pudo registrar la relación de  parametros especificos'
					);

					return $this->response($respuesta);
				}

			}else{

				$this->pedeo->trans_rollback();

				$respuesta = array(
				'error'   => true,
				'data' 		=> $resInsertEncabezado,
				'mensaje'	=> 'No se pudo registrar la relación de  parametros especificos'
				);

				return $this->response($respuesta);
			}

		}else{

			$this->pedeo->trans_rollback();

			$respuesta = array(
			'error'   => true,
			'data' 		=> $resInsertListaPrecio,
			'mensaje'	=> 'No se pudo registrar la relación de  parametros especificos'
			);

			return $this->response($respuesta);
		}

		$this->pedeo->trans_commit();

		$respuesta = array(
			'error'   => false,
			'data'    =>  [],
			'mensaje' => 'Relación de parametros especificos registrada con exito'
		);

		$this->response($respuesta);

	}
	//

  // se obtienen las clasificaciones de las paginas
  public function getPageClassification_get(){

    $sqlSelect = "SELECT cdp_id, cdp_name,
            CASE
            	WHEN cdp_status = 1 THEN 'Activo'
            	ELSE 'Inactivo'
            END AS cdp_status
            FROM mcdp";

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

  // se obtienen los parametros generales de la clasificacion
  public function getGeneralParameters_get(){

    $sqlSelect = "SELECT pgn_id,pgn_name,pgn_table,pgn_prefix,
                  CASE
                  	WHEN pgn_status = 1 THEN 'Activo'
                  	ELSE 'Inactivo'
                  END AS 	pgn_status
                  FROM mpgn";

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

	// se obtienen los parametros generales de la clasificacion
	// por Id de clasificacion
	public function getGeneralParametersById_get(){

		$Data = $this->get();

		$sqlSelect = "SELECT pgn_id,pgn_name,pgn_table,pgn_prefix,
									CASE
										WHEN pgn_status = 1 THEN 'Activo'
										ELSE 'Inactivo'
									END AS 	pgn_status
									FROM mrcp
									INNER JOIN mpgn
									ON pgn_id = rcp_paramid
									where rcp_classid = :rcp_classid";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':rcp_classid' => $Data['rcp_classid']));

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

	// se obtienen los parametros especificos de la clasificacion
	// por Id de clasificacion
	public function getSpecificParametersById_get(){

		$Data = $this->get();

		if(isset($Data['validated']) && $Data['validated'] == 1){
			$sqlSelect = "SELECT epe_id,epe_name,epe_docdate,epe_duedate,epe_createby, case when epe_status = 1 then 'Activo' else 'Inactivo' end as epe_status ,epe_status as status
			FROM mepe WHERE epe_clascode = :epe_clascode and mepe.epe_status = 1 and mepe.epe_duedate >= current_date";
		}else{
			$sqlSelect = "SELECT epe_id,epe_name,epe_docdate,epe_duedate,epe_createby, case when epe_status = 1 then 'Activo' else 'Inactivo' end as epe_status ,epe_status as status
			FROM mepe WHERE epe_clascode = :epe_clascode";
		}
		

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':epe_clascode' => $Data['rcp_classid']));

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

	// SE OBTIENE EL DETALLE DE LOS PARAMETROS
	public function getSpecificParametersDetail_get(){

		$Data = $this->get();

		$sqlSelect = "SELECT * FROM mpgn WHERE pgn_id = :pgn_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':pgn_id' => $Data['rcp_idp']));

		if(isset($resSelect[0])){

			$sqlDetalle = "SELECT * FROM ".$resSelect[0]['pgn_table']." WHERE ".$resSelect[0]['pgn_prefix']."_status = :status";

			$resDetalle = $this->pedeo->queryTable($sqlDetalle, array(':status' => 1));

			if(isset($resDetalle[0])){

				$respuesta = array(
					'error' => false,
					'data'  => $resDetalle,
					'mensaje' => '');

			}else{

				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'busqueda sin resultados'
				);
			}

		}else{

				$respuesta = array(
					'error'   => true,
					'data' => array(),
					'mensaje'	=> 'busqueda sin resultados'
				);

		}

		$this->response($respuesta);
	}
	// OBTIENE LAS OPCIONES Y EL PRECIOS PARA UN PARAMETRO ESPECIFICO
	public function getOptions_get(){

		$Data = $this->get();

		$sqlSelect = "SELECT lpc_price, 
					pgn_name,
					get_params(pgn_table, pgn_prefix, pe1_vparamcode) AS parametro
					FROM mepe
					INNER JOIN mlpc
					ON epe_listpcode = lpc_id
					INNER JOIN epe1
					ON epe_id = pe1_epeid
					INNER JOIN mpgn
					ON pe1_paramcode = pgn_id
					WHERE epe_id = :epe_id
					ORDER BY pgn_name";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':epe_id' => $Data['epe_id']));

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

	public function update_post()
	{
		$Data = $this->post();

		if(!isset($Data['epe_id'])){
			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'Información enviada invalida'
			);

			$this->response($respuesta);
			return;
		}

		$update = "UPDATE mepe SET epe_status = :epe_status WHERE epe_id = :epe_id";
		$resUpdate = $this->pedeo->updateRow($update,array(
			':epe_id' => $Data['epe_id'],
			':epe_status' => $Data['epe_status']
		));

		if(is_numeric($resUpdate) && $resUpdate > 0){
			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' => 'Actualización realizada con exito'
			);
		}else{
			$respuesta = array(
				'error' => true,
				'data' => $resUpdate,
				'mensaje' => 'No se puedo realizar la actualización'
			);
		}

		$this->response($respuesta);

	}

}
