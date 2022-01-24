<?php
// FACTURA DE COMPRAS
defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class FinanceReport extends REST_Controller {

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


	// METODO PARA OBTENER TODOS LOS REGISTROS
  public function getFinanceReport_get(){

    $sqlSelect = "SELECT * FROM tmif";

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

  public function getFinanceReportGroup_post(){
	$Data = $this->post();
    $sqlSelect = "SELECT * FROM mif1 WHERE if1_mif_id = :mif_docentry";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(":mif_docentry" =>$Data['mif_docentry']));

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

	// METODO PARA CREAR CABECERA
  public function createFinanceReport_post(){
    $Data = $this->post();
    if( (!isset($Data['mif_name']) AND empty($Data['mif_name'])) OR
        !isset($Data['mif_create_by']) OR
        !isset($Data['mif_status'])
      ){
      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }

    $sqlInsert ="INSERT INTO tmif (mif_name, mif_create_by, mif_status)
                  VALUES(:mif_name, :mif_create_by, :mif_status)";

    $resInsert = $this->pedeo->insertRow($sqlInsert,array(
                    ':mif_name' =>$Data['mif_name'],
                    ':mif_create_by' =>$Data['mif_create_by'],
                    ':mif_status' =>$Data['mif_status']
                  ));

    if(is_numeric($resInsert) && $resInsert > 0){

      $respuesta = array(
        'error' => false,
        'data'  => $resInsert,
        'mensaje' => 'Informe creado con exito');
    }else{

      $respuesta = array(
        'error' => true,
        'data'  => $resInsert,
        'mensaje' => 'No se pudo crear el informe');
    }
    $this->response($respuesta);
  }

	//METODO PARA FILTRAR CABECERA
  public function getFinanceReportById_get(){
    $Data = $this->get();

		if( !isset($Data['mif_docentry'])){
      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }

    $sqlSelect = "SELECT * FROM tmif WHERE mif_docentry = :mif_docentry";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':mif_docentry' =>$Data['mif_docentry']));

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

	// METODO PARA CREAR EL DETALLE
  public function  createFinanceReportGroup_post(){
    $Data = $this->post();
    if( (!isset($Data['if1_group_name']) OR empty($Data['if1_group_name'])) OR
        !isset($Data['if1_mif_id']) OR
        (!isset($Data['if1_order']) OR empty($Data['if1_order'])) 
      ){
      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }


	$sqlInsert ="INSERT INTO mif1 (if1_group_name, if1_mif_id,if1_order) VALUES(:if1_group_name,:if1_mif_id,:if1_order)";
	$resInsert = $this->pedeo->insertRow($sqlInsert,array(":if1_group_name"=>$Data['if1_group_name'],
												":if1_mif_id"=>$Data['if1_mif_id'],
												"if1_order" =>$Data['if1_order']
											));


    if(is_numeric($resInsert) && $resInsert > 0){

      $respuesta = array(
        'error' => false,
        'data'  => $resInsert,
        'mensaje' => 'informe financiero creado con exito');

    }else{
			// $this->pedeo->trans_rollback();
      $respuesta = array(
        'error' => true,
        'data'  => $resInsert,
        'mensaje' => 'No se pudo crear el informe financiero');
    }
    $this->response($respuesta);
  }

	// METODO PARA FILTRAR LOS DETALLES
	public function getFinanceReportDetailsById_get(){
		$Data = $this->get();

		$sqlSelect = "SELECT * from tmif
										join mif1 on mif_docentry = if1_mif_id
										join fif2 on if1_docentry = fi2_fi1_id
										where mif_docentry = :mif_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mif_docentry' =>$Data['mif_docentry']));

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

	// METODO PARA actualizar CABECERA
	public function updateFinanceReport_post(){
		$Data = $this->post();
		if( !isset($Data['mif_name']) OR
				!isset($Data['mif_create_by']) OR
				!isset($Data['mif_status']) OR
				!isset($Data['mif_docentry'])
			){
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
		}

		$sqlUpdate ="UPDATE tmif set mif_name = :mif_name,
												mif_create_by = :mif_create_by,
												mif_status = :mif_status
												WHERE mif_docentry = :mif_docentry";

		$resUpdate = $this->pedeo->updateRow($sqlUpdate,array(
										':mif_name' =>$Data['mif_name'],
										':mif_create_by' =>$Data['mif_create_by'],
										':mif_status' =>$Data['mif_status'],
										':mif_docentry' =>$Data['mif_docentry']
									));

		if(is_numeric($resUpdate) && $resUpdate > 0){

			$respuesta = array(
				'error' => false,
				'data'  => $resUpdate,
				'mensaje' => 'Informe financiero actualizado con exito');
		}else{

			$respuesta = array(
				'error' => true,
				'data'  => $resUpdate,
				'mensaje' => 'No se pudo actualizar el informe financiero');
		}
		$this->response($respuesta);
	}

	// METODO PARA ACTUALIZAR DETALLE
	public function updateFinanceReportDetail_post(){
		$Data = $this->post();

		if( !isset($Data['if1_group_name']) OR
        !isset($Data['if1_mif_id']) OR
        !isset($Data['Details'])
		){
		$this->response(array(
			'error'  => true,
			'data'   => [],
			'mensaje'=>'La informacion enviada no es valida'
		), REST_Controller::HTTP_BAD_REQUEST);

		return ;
		}
		$sqlUpdate = "UPDATE mif1 set if1_group_name = :if1_group_name,
																	if1_mif_id = :if1_mif_id
																	WHERE if1_docentry = :if1_docentry";

		$this->pedeo->trans_begin();

		$resUpdate = $this->pedeo->updateRow($sqlUpdate,array(":if1_group_name" => $Data['if1_group_name'],
																													"if1_mif_id" => $Data['if1_mif_id'],
																													"if1_docentry" => $Data['if1_docentry']));


		if(is_numeric($resUpdate) && $resUpdate > 0){

			$detalles = $Data['Details'];
			$detalles = json_decode($detalles,true);

			$sqlUpdate2 = "UPDATE fif2 set fi2_subgroup_name = :fi2_subgroup_name,
																		fi2_account = :fi2_account,
																		fi2_conduct = :fi2_conduct,
																		fi2_fi1_id =:fi2_fi1_id
																		WHERE fi2_docentry = :fi2_docentry";
			foreach ($detalles as $key => $detalle) {

					$resUpdate2 = $this->pedeo->updateRow($sqlUpdate2,array(":fi2_subgroup_name" =>$detalle['fi2_subgroup_name'],
																																	":fi2_account" =>$detalle['fi2_account'],
																																	":fi2_conduct" =>$detalle['fi2_conduct'],
																																	":fi2_docentry" =>$detalle['fi2_docentry']));
				if(is_numeric($resUpdate2) && $resUpdate2 = 0){
					$this->pedeo->trans_rollback();
					$respuesta = array(
						'error' => true,
						'data'  => $resUpdate2,
						'mensaje' => 'No se pudo crear el informe financiero');
						$this->response($respuesta);
						return ;

				}
				$this->pedeo->trans_commit();
				$respuesta = array(
	        'error' => false,
	        'data'  => $resInsert,
	        'mensaje' => 'informe financiero creado con exito');
			}


		}else{
			$this->pedeo->trans_rollback();
			$respuesta = array(
				'error' => true,
				'data'  => $resUpdate,
				'mensaje' => 'No se pudo actualizar el informe financiero');
		}
		$this->response($respuesta);
	}

	// METODO PAR ACTUALIZAR GRUPO
	public function updateGroup_post()	{
		$Data = $this->post();
		if( (!isset($Data['if1_group_name']) OR empty($Data['if1_group_name'])) OR
			!isset($Data['if1_docentry']) OR
			(!isset($Data['if1_order']) OR empty($Data['if1_order'])) 

		){
		$this->response(array(
			'error'  => true,
			'data'   => [],
			'mensaje'=>'La informacion enviada no es valida'
		), REST_Controller::HTTP_BAD_REQUEST);

		return ;
		}

		$sqlUpdate ="UPDATE mif1 set if1_group_name = :if1_group_name,
									if1_order = :if1_order
									WHERE if1_docentry = :if1_docentry";

		$resUpdate = $this->pedeo->updateRow($sqlUpdate,array(
										':if1_group_name' =>$Data['if1_group_name'],
										':if1_order' =>$Data['if1_order'],
										':if1_docentry' =>$Data['if1_docentry']
									));

		if(is_numeric($resUpdate) && $resUpdate > 0){

			$respuesta = array(
				'error' => false,
				'data'  => $resUpdate,
				'mensaje' => 'Grupo actualizado con exito');
		}else{

			$respuesta = array(
				'error' => true,
				'data'  => $resUpdate,
				'mensaje' => 'No se pudo actualizar el grupo');
		}
		$this->response($respuesta);
	}

	// METODO PARA CREAR SUBGRUPO
	public function createSubgroup_post(){
		$Data = $this->post();
		if( (!isset($Data['if2_subgroup_name']) OR empty($Data['if2_subgroup_name'])) OR
			(!isset($Data['if2_conduct']) OR empty($Data['if2_conduct'])) OR
				!isset($Data['if2_fi1_id']) OR
				(!isset($Data['if2_order']) OR empty($Data['if2_order'])) 
			){
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
			}


			$sqlInsert ="INSERT INTO mif2 (if2_subgroup_name,if2_conduct, if2_fi1_id,if2_order)
			VALUES(:if2_subgroup_name,:if2_conduct,:if2_fi1_id,:if2_order)";

			$resInsert = $this->pedeo->insertRow($sqlInsert,array(
				":if2_subgroup_name"=>$Data['if2_subgroup_name'],
				":if2_conduct"=>$Data['if2_conduct'],
				":if2_fi1_id"=>$Data['if2_fi1_id'],
				':if2_order'=> $Data['if2_order'])
			);


			if(is_numeric($resInsert) && $resInsert > 0){

			$respuesta = array(
				'error' => false,
				'data'  => $resInsert,
				'mensaje' => 'Subgrupo creado con exito');

			}else{
					$this->pedeo->trans_rollback();
			$respuesta = array(
				'error' => true,
				'data'  => $resInsert,
				'mensaje' => 'No se pudo crear el subgrupo');
			}
			$this->response($respuesta);
	}
	// METODO PARA ACTUALIZAR SUBGRUPO
	public function updateSubgroup_post(){
			$Data = $this->post();
			if( (!isset($Data['if2_subgroup_name']) OR empty($Data['if2_subgroup_name'])) OR
			(!isset($Data['if2_conduct']) OR empty($Data['if2_conduct'])) OR
				!isset($Data['if2_fi1_id']) OR
				(!isset($Data['if2_order']) OR empty($Data['if2_order'])) 
			){
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
			}


			$sqlUpdate ="UPDATE mif2 SET if2_subgroup_name = :if2_subgroup_name,
										if2_conduct = :if2_conduct,
										if2_order =:if2_order
										WHERE if2_docentry = :if2_docentry";

			$resUpdate = $this->pedeo->updateRow($sqlUpdate,array(
				":if2_subgroup_name"=>$Data['if2_subgroup_name'],
				":if2_conduct"=>$Data['if2_conduct'],
				":if2_order"=>$Data['if2_order'],
				":if2_docentry"=>$Data['if2_docentry']
				));


			if(is_numeric($resUpdate) && $resUpdate > 0){

			$respuesta = array(
				'error' => false,
				'data'  => $resUpdate,
				'mensaje' => 'Subgrupo creado con exito');

			}else{
					// $this->pedeo->trans_rollback();
			$respuesta = array(
				'error' => true,
				'data'  => [],
				'mensaje' => 'No se pudo crear el subgrupo');
			}
			$this->response($respuesta);
	}

	public function getFinanceReportSGroup_post(){
		$Data = $this->post();

		$sqlSelect = "SELECT * FROM mif2 WHERE if2_fi1_id = :if2_fi1_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":if2_fi1_id" =>$Data['if2_fi1_id']));

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

	public function createAccount_post(){
		$Data = $this->post();
		if( !isset($Data['if3_if2_id']) OR
			!isset($Data['if3_account'])
		){
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
		}

		$cuentas = explode(',',$Data['if3_account']);
		$sqlInsert = "INSERT INTO mif3 (if3_account,if3_if2_id) values (:if3_account,:if3_if2_id)";
		$this->pedeo->trans_begin();
		foreach ($cuentas as $key => $cuenta){
			$resInsert = $this->pedeo->insertRow($sqlInsert,array(
				":if3_account" => $cuenta,
				"if3_if2_id" => $Data['if3_if2_id']
			));
			if(is_numeric($resInsert) && $resInsert > 0){

			}else{
				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'No se pudo crear'
				), REST_Controller::HTTP_BAD_REQUEST);

				return ;
				$this->pedeo->trans_rollback();
			}
		}

		$respuesta = array(
			'error' => false,
			'data'  => $resInsert,
			'mensaje' => 'Cuentas agregadas con exito');
			$this->pedeo->trans_commit();

		$this->response($respuesta);

	}

	public function getAccountSubgroup_get(){
		$Data = $this->get();

		if(!isset($Data['if3_if2_id'])){
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
		}

		$sqlSelect = "SELECT * from mif3  where if3_if2_id = :if3_if2_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect,[":if3_if2_id" => $Data['if3_if2_id']]);

		if(isset($resSelect[0])){
			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => '');
		}else{
			$respuesta = array(
				'error' => true,
				'data'  => [],
				'mensaje' => 'busqueda sin resultados');
		}

		$this->response($respuesta);
	}

	public function getComplete_get(){
		$Data = $this->get();


		$sqlSelect2 = "SELECT * from mif1 where if1_mif_id = :if1_mif_id";
		$resSelect2 = $this->pedeo->queryTable($sqlSelect2,array(":if1_mif_id"=>$Data['mif_docentry']));

		if(isset($resSelect2[0])){

			foreach ($resSelect2 as $key => $sub){
				$sqlSelect3 = "SELECT * from mif2 where if2_fi1_id = :if2_fi1_id";
				$resSelect3 = $this->pedeo->queryTable($sqlSelect3,array(":if2_fi1_id"=>$sub['if1_docentry']));
				if(isset($resSelect3[0])){
					foreach ($resSelect3 as $ke => $value){
						$resSelect3[$ke]['accounts'] = self::getAccountsSub($value['if2_docentry']);
						// $resSelect2[$key]['ac'] = $value['if2_docentry'];
						// print_r($resSelect2);
						// die;
						//
					}


				}
				$resSelect2[$key]['sub'] = $resSelect3;


			}

		  }


		$this->response(['error' => false,
		'data'  => $resSelect2,
		'mensaje' => '']);
	}

	public function editAccounts_post(){
		$Data = $this->post();

		if( !isset($Data['if3_if2_id']) OR
			!isset($Data['if3_account']) OR
			!isset($Data['edit'])
		){
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
		}

		if($Data['edit'] == 1){
			$sqlDelete = "DELETE FROM mif3 WHERE if3_if2_id = :if3_if2_id";
			$resDelete = $this->pedeo->deleteRow($sqlDelete, array(":if3_if2_id" => $Data['if3_if2_id']));
		}

		// if(is_numeric($resDelete) && $resDelete > 0){

		$cuentas = explode(',',$Data['if3_account']);
		$sqlInsert = "INSERT INTO mif3 (if3_account,if3_if2_id) values (:if3_account,:if3_if2_id)";
		$this->pedeo->trans_begin();
		foreach ($cuentas as $key => $cuenta){
			$resInsert = $this->pedeo->insertRow($sqlInsert,array(
				":if3_account" => $cuenta,
				"if3_if2_id" => $Data['if3_if2_id']
			));
			if(is_numeric($resInsert) && $resInsert > 0){

			}else{
				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'No se pudo crear'
				), REST_Controller::HTTP_BAD_REQUEST);

				return ;
				$this->pedeo->trans_rollback();
			}
		}
		$respuesta = array(
			'error' => false,
			'data'  => $resInsert,
			'mensaje' => 'Cuentas agregadas con exito');
			$this->pedeo->trans_commit();
	// } else{
	// 	$this->response(array(
	// 		'error'  => false,
	// 		'data'   => $resDelete,
	// 		'mensaje'=>'No se pudo crear'
	// 	), REST_Controller::HTTP_BAD_REQUEST);

	// 	return ;
	// 	$this->pedeo->trans_rollback();
	// }


		$this->response($respuesta);
	}

	private function getAccountsSub($subId){
		$sqlSelect = "SELECT concat(mif3.if3_account,' - ' ,acc_name) account
		from mif3
		join dacc on dacc.acc_code = cast(mif3.if3_account as bigint)
		where if3_if2_id = :if3_if2_id";
		$resSelect = $this->pedeo->queryTable($sqlSelect,array(":if3_if2_id"=>$subId));
		if(isset($resSelect[0])){
			return $resSelect;
		}
		return [];
	}
}
