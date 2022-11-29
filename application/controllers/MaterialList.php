<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class MaterialList extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);
		$this->load->library('generic');

	}

	public function createMaterialList_post(){

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


		if(!is_array($ContenidoDetalle)){
				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'No se encontro el detalle de la lista'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
		}

		// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
		if(!intval(count($ContenidoDetalle)) > 0 ){
				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'Documento sin detalle'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
		}
		//

		//Obtener Carpeta Principal del Proyecto
		$sqlMainFolder = " SELECT * FROM params";
		$resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());

		if(!isset($resMainFolder[0])){
				$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'No se encontro la caperta principal del proyecto'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
		}
		//

		$sqlInsert = "INSERT INTO prlm(rlm_item_code, rlm_quantity, rlm_bom_type, rlm_whscode, rlm_pricelist, rlm_nom_dist,
			 												rlm_project, rlm_comment, rlm_costoprod, rlm_total, rlm_createat, rlm_createby, rlm_item_name, rlm_doctype)
															VALUES(:rlm_item_code, :rlm_quantity, :rlm_bom_type, :rlm_whscode, :rlm_pricelist, :rlm_nom_dist,
															:rlm_project, :rlm_comment, :rlm_costoprod, :rlm_total, :rlm_createat, :rlm_createby, :rlm_item_name, :rlm_doctype)";

		$resInsert = $this->pedeo->insertRow(	$sqlInsert, array(
			':rlm_item_code' => isset($Data['rlm_item_code'])?$Data['rlm_item_code']:NULL,
			':rlm_quantity' => is_numeric($Data['rlm_quantity'])?$Data['rlm_quantity']:0,
			':rlm_bom_type' => is_numeric($Data['rlm_bom_type'])?$Data['rlm_bom_type']:NULL,
			':rlm_whscode' => isset($Data['rlm_whscode'])?$Data['rlm_whscode']:NULL,
			':rlm_pricelist' => is_numeric($Data['rlm_pricelist'])?$Data['rlm_pricelist']:0,
			':rlm_nom_dist' => isset($Data['rlm_nom_dist'])?$Data['rlm_nom_dist']:NULL,
			':rlm_project' => isset($Data['rlm_project'])?$Data['rlm_project']:NULL,
			':rlm_comment' => isset($Data['rlm_comment'])?$Data['rlm_comment']:NULL,
			':rlm_costoprod' => is_numeric($Data['rlm_costoprod'])?$Data['rlm_costoprod']:0,
			':rlm_total' => is_numeric($Data['rlm_total'])?$Data['rlm_total']:0,
			':rlm_createat' => $this->generic->validateDate($Data['rlm_createat'])?$Data['rlm_createat']:NULL,
			':rlm_createby' => isset($Data['rlm_createby'])?$Data['rlm_createby']:NULL,
			':rlm_item_name' => isset($Data['rlm_item_name'])?$Data['rlm_item_name']:NULL,
			':rlm_doctype' => isset($Data['rlm_doctype'])?$Data['rlm_doctype']:NULL

		));

		if( is_numeric($resInsert) && $resInsert > 0 ){

			 $this->pedeo->trans_begin();

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO public.rlm1(lm1_iddoc, lm1_linenum, lm1_type, lm1_itemcode, lm1_itemname, lm1_quantity,
					 									lm1_uom, lm1_whscode, lm1_emission_method, lm1_standard_cost, lm1_standard_costt, lm1_pricelist,
														lm1_price, lm1_total, lm1_comment)VALUES(:lm1_iddoc, :lm1_linenum, :lm1_type, :lm1_itemcode, :lm1_itemname, :lm1_quantity,
														:lm1_uom, :lm1_whscode, :lm1_emission_method, :lm1_standard_cost, :lm1_standard_costt, :lm1_pricelist,
														:lm1_price, :lm1_total, :lm1_comment)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

					':lm1_iddoc' => $resInsert,
					':lm1_linenum' => is_numeric($detail['lm1_linenum'])?$detail['lm1_linenum']:0,
					':lm1_type' => is_numeric($detail['lm1_type'])?$detail['lm1_type']:0,
					':lm1_itemcode' => isset($detail['lm1_itemcode'])?$detail['lm1_itemcode']:NULL,
					':lm1_itemname' => isset($detail['lm1_itemname'])?$detail['lm1_itemname']:NULL,
					':lm1_quantity' => is_numeric($detail['lm1_quantity'])?$detail['lm1_quantity']:NULL,
					':lm1_uom' => isset($detail['lm1_uom'])?$detail['lm1_uom']:NULL,
					':lm1_whscode' =>  isset($detail['lm1_whscode'])?$detail['lm1_whscode']:NULL,
					':lm1_emission_method' => is_numeric($detail['lm1_emission_method'])?$detail['lm1_emission_method']:NULL,
					':lm1_standard_cost' => is_numeric($detail['lm1_standard_cost'])?$detail['lm1_standard_cost']:NULL,
					':lm1_standard_costt' => is_numeric($detail['lm1_standard_costt'])?$detail['lm1_standard_costt']:NULL,
					':lm1_pricelist' =>is_numeric($detail['lm1_pricelist'])?$detail['lm1_pricelist']:NULL,
					':lm1_price' => is_numeric($detail['lm1_price'])?$detail['lm1_price']:NULL,
					':lm1_total' => is_numeric($detail['lm1_total'])?$detail['lm1_total']:NULL,
					':lm1_comment' =>isset($detail['lm1_comment'])?$detail['lm1_comment']:NULL
				));


				if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

				}else{
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la lista'
					);

					 $this->response($respuesta);

					 return;
				}
			}

			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' =>'Lista registrada con exito'
			);

		}else{

			$respuesta = array(
				'error'   => true,
				'data'    => $resInsert,
				'mensaje'	=> 'No se pudo crear la cotización'
			);
		}
		$this->response($respuesta);
	}



	//OBTENER LISTA DE MATERIALES POR ID
	public function getMaterialListById_get(){

				$Data = $this->get();

				if(!isset($Data['rlm_id'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM prlm WHERE rlm_id =:rlm_id";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":rlm_id" => $Data['rlm_id']));

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

	//OBTENER LISTA DE MATERIALES DETALLE POR ID
	public function getMaterialListDetail_get(){

				$Data = $this->get();

				if(!isset($Data['lm1_iddoc'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = "SELECT * FROM rlm1 WHERE lm1_iddoc =:lm1_iddoc";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":lm1_iddoc" => $Data['lm1_iddoc']));

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

	//OBTENER LISTA DE MATERIALES
	public function getMaterialList_get(){

				$Data = $this->get();



				$sqlSelect = " SELECT * FROM prlm";

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

	// OBTENER LISTA DE MATERIALES POR CODIGO DE ITEM
	public function getMaterialListByItemCode_get()
	{
		$Data = $this->get();

		if (!isset($Data['rlm_item_code'])) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT * FROM prlm WHERE rlm_item_code = :rlm_item_code";
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":rlm_item_code" => $Data['rlm_item_code']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	public function getMaterialListByDoc_post(){
		$Data = $this->post();

		$sqlSelect = "SELECT prlm.* from  dmar
		inner join {$Data['table']}  on dmar.dma_item_code = {$Data['itemfield']}
		left join prlm on rlm_item_code = dma_item_code
		where {$Data['filter']} =  :docentry";

		
		$resSelect =  $this->pedeo->queryTable($sqlSelect, array(":docentry" => $Data['docentry']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}



}
