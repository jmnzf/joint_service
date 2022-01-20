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
    if( !isset($Data['mif_name']) OR
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
    if( !isset($Data['if1_group_name']) OR
        !isset($Data['if1_mif_id'])
      ){
      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }


	$sqlInsert ="INSERT INTO mif1 (if1_group_name, if1_mif_id) VALUES(:if1_group_name,:if1_mif_id)";
	$resInsert = $this->pedeo->insertRow($sqlInsert,array(":if1_group_name"=>$Data['if1_group_name'],
	":if1_mif_id"=>$Data['if1_mif_id']));


    if(is_numeric($resInsert) && $resInsert > 0){

      $respuesta = array(
        'error' => false,
        'data'  => $resInsert,
        'mensaje' => 'informe financiero creado con exito');

    }else{
			$this->pedeo->trans_rollback();
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
		if( !isset($Data['if1_group_name']) OR
			!isset($Data['if1_docentry'])
		){
		$this->response(array(
			'error'  => true,
			'data'   => [],
			'mensaje'=>'La informacion enviada no es valida'
		), REST_Controller::HTTP_BAD_REQUEST);

		return ;
		}

		$sqlUpdate ="UPDATE mif1 set if1_group_name = :if1_group_name
					WHERE if1_docentry = :if1_docentry";

		$resUpdate = $this->pedeo->updateRow($sqlUpdate,array(
										':if1_group_name' =>$Data['if1_group_name'],
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
			if( !isset($Data['if2_subgroup_name']) OR
				!isset($Data['if2_conduct']) OR
				!isset($Data['if2_fi1_id'])
			){
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
			}


			$sqlInsert ="INSERT INTO mif2 (if2_subgroup_name,if2_conduct, if2_fi1_id)
			VALUES(:if2_subgroup_name,:if2_conduct,:if2_fi1_id)";

			$resInsert = $this->pedeo->insertRow($sqlInsert,array(
				":if2_subgroup_name"=>$Data['if2_subgroup_name'],
				":if2_conduct"=>$Data['if2_conduct'],
				":if2_fi1_id"=>$Data['if2_fi1_id'])
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
			if( !isset($Data['if2_subgroup_name']) OR
				!isset($Data['if2_conduct']) OR
				!isset($Data['if2_docentry'])
			){
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'La informacion enviada no es valida'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
			}


			$sqlUpdate ="UPDATE mif2 SET if2_subgroup_name = :if2_subgroup_name,
										if2_conduct = :if2_conduct
										WHERE if2_docentry = :if2_docentry";

			$resUpdate = $this->pedeo->updateRow($sqlUpdate,array(
				":if2_subgroup_name"=>$Data['if2_subgroup_name'],
				":if2_conduct"=>$Data['if2_conduct'],
				":if2_docentry"=>$Data['if2_docentry']
				));


			if(is_numeric($resUpdate) && $resUpdate > 0){

			$respuesta = array(
				'error' => false,
				'data'  => $resUpdate,
				'mensaje' => 'Subgrupo creado con exito');

			}else{
					$this->pedeo->trans_rollback();
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

		$cuentas = $Data['if3_account'];
		$sqlInsert = "INSERT INTO mif3 (if_account,if3_if2_id) values (:if_account,:if3_if2_id)";
		$this->trans_begin();
		foreach ($cuentas as $key => $cuenta){
			$resInsert = $this->pedeo->insertRow($sqlInsert,array(
				":if3_account" => $Data['if3_account'],
				"if3_if2_id" => $cuenta
			));
			if(is_numeric($resInsert) && $resInsert > 0){

			}else{
				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);
	
				return ;
				$this->trans_rollback();
			}
		}

		$respuesta = array(
			'error' => false,
			'data'  => $resInsert,
			'mensaje' => 'Cuentas agregadas con exito');
			$this->trans_commit();

		$this->response($respuesta);

	}
}
