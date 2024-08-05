<?php
// PRESUPUESTOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Budget extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    	$this->load->library('pedeo', [$this->pdo]);
		$this->load->library('account');
	}
	// METODO PARA OBTENER LOS PRESUPUESTOS
  	public function getBudget_get(){
		$Data = $this->get();
    	$sqlSelect = "select mpc_id, mpc_name,
						case 
							when mpc_type::int = 1 then 'Mensual'
							when mpc_type::int = 2 then 'Trimestral'
							when mpc_type::int = 3 then 'Bimestral'
							when mpc_type::int = 4 then 'Quatrimestral'
						end as mpc_type,
						case 
							when mpc_check = 1 then 'Controlado'
							when mpc_check = 0 then 'No Controlado'
						end as mpc_check,
						case 
							when mpc_linear = 1 then 'Presupuesto Lineal'
							when mpc_linear = 0 then 'Presupuesto no Lineal'
						end as mpc_linear,
						mpc_year
						from tmpc 
						where business = :business";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":business" => $Data['business']));

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
	// METODO PARA OBTENER LOS PRESUPUESTOS POR ID
 	public function getBudgetByid_get() {

    	$Data = $this->get();

		if(!isset($Data['mpc_id'])){
		$this->response(array(
			'error'  => true,
			'data'   => [],
			'mensaje'=>'La informacion enviada no es valida'
		), REST_Controller::HTTP_BAD_REQUEST);

		return ;
		}

		$sqlSelect = "SELECT * FROM tmpc
					INNER JOIN mpc1 m ON tmpc.mpc_id = m.pc1_mpcid 
					WHERE tmpc.mpc_id = :mpc_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mpc_id'=>$Data['mpc_id']));

		if(isset($resSelect[0])){

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);

		}else{

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);

		}

		$this->response($respuesta);
  	}
    // METODO PARA CREAR PRESUPUESTO
  	public function createBudget_post(){
		// OBTENIENDO LOS DATOS PARA CREAR EL PRESUPUESTO
		$Data = $this->post();

		$ContenidoDetalle = json_decode($Data['detail'], true);

		// SE VALIDA QUE EL DOCUMENTO SEA UN ARRAY
		if(!is_array($ContenidoDetalle)){
		  $respuesta = array(
		  'error' => true,
		  'data'  => array(),
		  'mensaje' =>'No se encontro el detalle del presupuesto'
		  );
  
		  $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
  
		  return;
		}
		//

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
		
		$this->pedeo->trans_begin();

		$sqlInsert = "INSERT INTO tmpc(mpc_name,business,branch,mpc_type,mpc_check,mpc_linear,mpc_year)VALUES(:mpc_name,:business,:branch,:mpc_type,:mpc_check,:mpc_linear,:mpc_year)";
		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			
			':mpc_name' => isset($Data['mpc_name']) ? $Data['mpc_name'] : NULL,
			':business' => $Data['business'],
			':branch' => $Data['branch'],
			':mpc_type' => $Data['mpc_type'],
			':mpc_check' => isset($Data['mpc_check']) ? $Data['mpc_check'] : 0,
			':mpc_linear' => isset($Data['mpc_linear']) ? $Data['mpc_linear'] : 0,
			':mpc_year' => isset($Data['mpc_year']) ? $Data['mpc_year'] : 0
		));


		if( is_numeric($resInsert) && $resInsert > 0 ){

			for ($i=0; $i < count($ContenidoDetalle); $i++) { 
			

				$sqlInsertDetail = "INSERT INTO mpc1(pc1_account,pc1_account_name,pc1_bunit,pc1_ccenter,pc1_project,pc1_total,pc1_jan,pc1_feb,pc1_mar,pc1_apr,pc1_may,pc1_jun,
				pc1_jul,pc1_aug,pc1_sep,pc1_oct,pc1_nov,pc1_dec,pc1_mpcid)VALUES(:pc1_account,:pc1_account_name,:pc1_bunit,:pc1_ccenter,:pc1_project,:pc1_total,:pc1_jan,:pc1_feb,
				:pc1_mar,:pc1_apr,:pc1_may,:pc1_jun,:pc1_jul,:pc1_aug,:pc1_sep,:pc1_oct,:pc1_nov,:pc1_dec,:pc1_mpcid)";

				if( $Data['mpc_type'] == 1 ) {

					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resInserDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
	
						':pc1_account' => $cuenta,
						':pc1_account_name' => '',
						':pc1_bunit' => $ContenidoDetalle[$i][1],
						':pc1_ccenter' => $ContenidoDetalle[$i][2],
						':pc1_project' => $ContenidoDetalle[$i][3],
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]),
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][6]),
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][7]),
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][8]),
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][9]),
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][10]),
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][11]),
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][12]),
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][13]),
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][14]),
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][15]),
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][16]),
						':pc1_mpcid' => $resInsert
					));

					if ( is_numeric($resInserDetail) && $resInserDetail > 0 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resInserDetail,
							'mensaje' => 'No se pudo registrar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}

				}else if ( $Data['mpc_type'] == 2  ){

					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resInserDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
	
						':pc1_account' => $cuenta,
						':pc1_account_name' => '',
						':pc1_bunit' => $ContenidoDetalle[$i][1],
						':pc1_ccenter' => $ContenidoDetalle[$i][2],
						':pc1_project' => $ContenidoDetalle[$i][3],
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]) / 3,
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][5]) / 3,
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][5]) / 3,
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][6]) / 3,
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][6]) / 3,
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][6]) / 3,
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][7]) / 3,
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][7]) / 3,
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][7]) / 3,
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][8]) / 3,
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][8]) / 3,
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][8]) / 3,
						':pc1_mpcid' => $resInsert
					));

					if ( is_numeric($resInserDetail) && $resInserDetail > 0 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resInserDetail,
							'mensaje' => 'No se pudo registrar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}

				}else if ( $Data['mpc_type'] == 3 ){
					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resInserDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
	
						':pc1_account' => $cuenta,
						':pc1_account_name' => '',
						':pc1_bunit' => $ContenidoDetalle[$i][1],
						':pc1_ccenter' => $ContenidoDetalle[$i][2],
						':pc1_project' => $ContenidoDetalle[$i][3],
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]) / 2,
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][5]) / 2,
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][6]) / 2,
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][6]) / 2,
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][7]) / 2,
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][7]) / 2,
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][8]) / 2,
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][8]) / 2,
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][9]) / 2,
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][9]) / 2,
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][10]) / 2,
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][10]) / 2,
						':pc1_mpcid' => $resInsert
					));

					if ( is_numeric($resInserDetail) && $resInserDetail > 0 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resInserDetail,
							'mensaje' => 'No se pudo registrar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}

				}else if ( $Data['mpc_type'] == 4 ) {

					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resInserDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
	
						':pc1_account' => $cuenta,
						':pc1_account_name' => '',
						':pc1_bunit' => $ContenidoDetalle[$i][1],
						':pc1_ccenter' => $ContenidoDetalle[$i][2],
						':pc1_project' => $ContenidoDetalle[$i][3],
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_mpcid' => $resInsert
					));

					if ( is_numeric($resInserDetail) && $resInserDetail > 0 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resInserDetail,
							'mensaje' => 'No se pudo registrar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}

				}
				
			}

			$respuesta = array(
				'error'   => false,
				'data'    => [],
				'mensaje' => 'Presupuesto registrado con exito'
			);


			$this->pedeo->trans_commit();

		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resInsert,
				'mensaje' => 'No se pudo registrar el presupuesto'
			);


			$this->response($respuesta);

			return;
		}
		
		
		

		$this->response($respuesta);

  	}
	//METODO PARA CREAR DETALLE DE PRESUPUESTO
	public function updateBudget_post() {

		$Data = $this->post();


		if (!isset($Data['mpc_id'])){
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'No se encontro el detalle del presupuesto'
			);
		
			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
		
			return;
		}

		$ContenidoDetalle = json_decode($Data['detail'], true);

		// SE VALIDA QUE EL DOCUMENTO SEA UN ARRAY
		if(!is_array($ContenidoDetalle)){
		  $respuesta = array(
		  'error' => true,
		  'data'  => array(),
		  'mensaje' =>'No se encontro el detalle del presupuesto'
		  );
  
		  $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
  
		  return;
		}
		//

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

		$modificar = $this->account->validateBudget($Data['mpc_id']);

		$this->pedeo->trans_begin();

		if (isset($modificar['error']) && $modificar['error'] == false){
			// SE PUEDE MODIFICAR TODOS LOS CAMPOS HASTA EL DETALLE

			$sqlUpdate = "UPDATE tmpc set mpc_name = :mpc_name, mpc_type = :mpc_type, mpc_check = :mpc_check,
						mpc_linear = :mpc_linear, mpc_year = :mpc_year WHERE mpc_id = :mpc_id";
			
			$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
				':mpc_name' => isset($Data['mpc_name']) ? $Data['mpc_name'] : NULL,
				':mpc_type' => $Data['mpc_type'],
				':mpc_check' => isset($Data['mpc_check']) ? $Data['mpc_check'] : 0,
				':mpc_linear' => isset($Data['mpc_linear']) ? $Data['mpc_linear'] : 0,
				':mpc_year' => isset($Data['mpc_year']) ? $Data['mpc_year'] : 0,
				':mpc_id' => $Data['mpc_id']
			));

			if (is_numeric($resUpdate) && $resUpdate == 1){

			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resUpdate,
					'mensaje'	=> 'No se pudo actualizar el presupuesto'
				);

				return $this->response($respuesta);
			}


			// SE QUITA EL DETALLE ACTUAL

			$this->pedeo->deleteRow("DELETE FROM mpc1 WHERE pc1_mpcid = :pc1_mpcid", array($Data['mpc_id']));

			for ($i=0; $i < count($ContenidoDetalle); $i++) { 

				$sqlInsertDetail = "INSERT INTO mpc1(pc1_account,pc1_account_name,pc1_bunit,pc1_ccenter,pc1_project,pc1_total,pc1_jan,pc1_feb,pc1_mar,pc1_apr,pc1_may,pc1_jun,
				pc1_jul,pc1_aug,pc1_sep,pc1_oct,pc1_nov,pc1_dec,pc1_mpcid)VALUES(:pc1_account,:pc1_account_name,:pc1_bunit,:pc1_ccenter,:pc1_project,:pc1_total,:pc1_jan,:pc1_feb,
				:pc1_mar,:pc1_apr,:pc1_may,:pc1_jun,:pc1_jul,:pc1_aug,:pc1_sep,:pc1_oct,:pc1_nov,:pc1_dec,:pc1_mpcid)";
				
				if( $Data['mpc_type'] == 1 ) {

					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resInserDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
	
						':pc1_account' => $cuenta,
						':pc1_account_name' => '',
						':pc1_bunit' => $ContenidoDetalle[$i][1],
						':pc1_ccenter' => $ContenidoDetalle[$i][2],
						':pc1_project' => $ContenidoDetalle[$i][3],
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]),
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][6]),
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][7]),
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][8]),
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][9]),
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][10]),
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][11]),
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][12]),
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][13]),
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][14]),
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][15]),
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][16]),
						':pc1_mpcid' => $Data['mpc_id']
					));

					if ( is_numeric($resInserDetail) && $resInserDetail > 0 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resInserDetail,
							'mensaje' => 'No se pudo registrar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}

				}else if ( $Data['mpc_type'] == 2  ){

					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resInserDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
	
						':pc1_account' => $cuenta,
						':pc1_account_name' => '',
						':pc1_bunit' => $ContenidoDetalle[$i][1],
						':pc1_ccenter' => $ContenidoDetalle[$i][2],
						':pc1_project' => $ContenidoDetalle[$i][3],
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]) / 3,
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][5]) / 3,
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][5]) / 3,
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][6]) / 3,
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][6]) / 3,
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][6]) / 3,
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][7]) / 3,
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][7]) / 3,
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][7]) / 3,
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][8]) / 3,
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][8]) / 3,
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][8]) / 3,
						':pc1_mpcid' => $Data['mpc_id']
					));

					if ( is_numeric($resInserDetail) && $resInserDetail > 0 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resInserDetail,
							'mensaje' => 'No se pudo registrar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}

				}else if ( $Data['mpc_type'] == 3 ){
					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resInserDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
	
						':pc1_account' => $cuenta,
						':pc1_account_name' => '',
						':pc1_bunit' => $ContenidoDetalle[$i][1],
						':pc1_ccenter' => $ContenidoDetalle[$i][2],
						':pc1_project' => $ContenidoDetalle[$i][3],
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]) / 2,
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][5]) / 2,
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][6]) / 2,
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][6]) / 2,
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][7]) / 2,
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][7]) / 2,
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][8]) / 2,
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][8]) / 2,
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][9]) / 2,
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][9]) / 2,
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][10]) / 2,
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][10]) / 2,
						':pc1_mpcid' => $Data['mpc_id']
					));

					if ( is_numeric($resInserDetail) && $resInserDetail > 0 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resInserDetail,
							'mensaje' => 'No se pudo registrar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}

				}else if ( $Data['mpc_type'] == 4 ) {

					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resInserDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
	
						':pc1_account' => $cuenta,
						':pc1_account_name' => '',
						':pc1_bunit' => $ContenidoDetalle[$i][1],
						':pc1_ccenter' => $ContenidoDetalle[$i][2],
						':pc1_project' => $ContenidoDetalle[$i][3],
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_mpcid' => $Data['mpc_id']
					));

					if ( is_numeric($resInserDetail) && $resInserDetail > 0 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resInserDetail,
							'mensaje' => 'No se pudo registrar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}

				}
			}

		}else{
			// SOLO SE PUEDEN MODIFICAR LOS CAMPOS ESPECIFICOS QUE NO AFECTEN EL PROGRESO DEL PRESUPUESTO ACTUAL
			// SE VERIFICA LA CANTIDAD DE LINEAS DEL PRESUPUESTO
			// $lineas = $this->pedeo->queryTable("SELECT * FROM mpc1 WHERE pc1_mpcid = :pc1_mpcid", array(':pc1_mpcid' => $Data['mpc_id']));

			// if (isset($lineas[0])) {
				
			// 	if ( count($lineas) == count($ContenidoDetalle) ){

			// 	}else{
			// 		$this->pedeo->trans_rollback();

			// 		$respuesta = array(
			// 			'error'   => true,
			// 			'data'    => $lineas,
			// 			'mensaje'	=> 'No se pudo actualizar el presupuesto. No se puede cambiar la cantidad de líneas del detalle del mismo ya que presenta movimientos a la fecha.'
			// 		);
	
			// 		return $this->response($respuesta);
			// 	}
			// }

			// SE VALIDA QUE NO SE QUIERA INGRESAR UNA LINEA DISTINTA
			// DE LA CUENTA CONTABLE, CC, UN, Y PROYECTO DESDE LA CREACION DEL PRESUPUESTO

			// for ($i=0; $i < count($ContenidoDetalle); $i++) { 

			// 	$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

			// 	$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  


			// 	$validate = $this->account->validateLineBudget($Data['mpc_id'], $cuenta, $ContenidoDetalle[$i][2], $ContenidoDetalle[$i][1], $ContenidoDetalle[$i][3]);

			// 	if (isset($validate['error']) && $validate['error'] == false){

			// 	}else{

			// 		$this->pedeo->trans_rollback();

			// 		$respuesta = array(
			// 			'error'   => true,
			// 			'data'    => $validate,
			// 			'mensaje' => $validate['mensaje']
			// 		);
	
			// 		return $this->response($respuesta);
			// 	}
			// }
			//
			$sqlUpdate = "UPDATE tmpc set mpc_name = :mpc_name, mpc_check = :mpc_check,
						mpc_linear = :mpc_linear WHERE mpc_id = :mpc_id";

			$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
				':mpc_name' => isset($Data['mpc_name']) ? $Data['mpc_name'] : NULL,
				':mpc_check' => isset($Data['mpc_check']) ? $Data['mpc_check'] : 0,
				':mpc_linear' => isset($Data['mpc_linear']) ? $Data['mpc_linear'] : 0,
				':mpc_id' => $Data['mpc_id']
			));
			

			if (is_numeric($resUpdate) && $resUpdate == 1) {

			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resUpdate,
					'mensaje'	=> 'No se pudo actualizar el presupuesto'
				);

				return $this->response($respuesta);
			}

			for ($i=0; $i < count($ContenidoDetalle); $i++) { 

				$sqlUpdateDetail = "UPDATE mpc1 set pc1_total = :pc1_total, pc1_jan = :pc1_jan, pc1_feb = :pc1_feb, pc1_mar = :pc1_mar, pc1_apr = :pc1_apr, pc1_may = :pc1_may, pc1_jun = :pc1_jun, pc1_jul = :pc1_jul,
			    pc1_aug = :pc1_aug, pc1_sep = :pc1_sep, pc1_oct = :pc1_oct, pc1_nov = :pc1_nov, pc1_dec = :pc1_dec WHERE pc1_id = :pc1_id";

				if( $Data['mpc_type'] == 1 ) {

					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resUpdateDetail = $this->pedeo->updateRow($sqlUpdateDetail, array(
	
						
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]),
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][6]),
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][7]),
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][8]),
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][9]),
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][10]),
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][11]),
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][12]),
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][13]),
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][14]),
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][15]),
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][16]),
						':pc1_id' => $ContenidoDetalle[$i][18],
					));

					if ( is_numeric($resUpdateDetail) && $resUpdateDetail == 1 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resUpdateDetail,
							'mensaje' => 'No se pudo actualizar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}

				}else if ( $Data['mpc_type'] == 2  ){

					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resUpdateDetail = $this->pedeo->updateRow($sqlUpdateDetail, array(
	
						
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]) / 3,
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][5]) / 3,
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][5]) / 3,
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][6]) / 3,
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][6]) / 3,
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][6]) / 3,
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][7]) / 3,
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][7]) / 3,
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][7]) / 3,
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][8]) / 3,
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][8]) / 3,
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][8]) / 3,
						':pc1_id' => $ContenidoDetalle[$i][10],
					));

					if ( is_numeric($resUpdateDetail) && $resUpdateDetail == 1 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resUpdateDetail,
							'mensaje' => 'No se pudo actualizar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}

				}else if ( $Data['mpc_type'] == 3 ){

					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resUpdateDetail = $this->pedeo->updateRow($sqlUpdateDetail, array(
	
						
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]) / 2,
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][5]) / 2,
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][6]) / 2,
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][6]) / 2,
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][7]) / 2,
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][7]) / 2,
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][8]) / 2,
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][8]) / 2,
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][9]) / 2,
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][9]) / 2,
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][10]) / 2,
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][10]) / 2,
						':pc1_id' => $ContenidoDetalle[$i][12],
					));

					if ( is_numeric($resUpdateDetail) && $resUpdateDetail > 0 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resUpdateDetail,
							'mensaje' => 'No se pudo actualizar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}

				}else if ( $Data['mpc_type'] == 4 ) {

					$cuentaArray = explode('-', $ContenidoDetalle[$i][0]);

					$cuenta = isset($cuentaArray[1]) ? $cuentaArray[0] : $ContenidoDetalle[$i][0];  

					$resUpdateDetail = $this->pedeo->updateRow($sqlUpdateDetail, array(
	
						
						':pc1_total' => str_replace(',','',$ContenidoDetalle[$i][4]),
						':pc1_jan' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_feb' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_mar' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_apr' => str_replace(',','',$ContenidoDetalle[$i][5]) / 4,
						':pc1_may' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_jun' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_jul' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_aug' => str_replace(',','',$ContenidoDetalle[$i][6]) / 4,
						':pc1_sep' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_oct' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_nov' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_dec' => str_replace(',','',$ContenidoDetalle[$i][7]) / 4,
						':pc1_id' => $ContenidoDetalle[$i][9],
					));

					if ( is_numeric($resUpdateDetail) && $resUpdateDetail > 0 ){

					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => $resUpdateDetail,
							'mensaje' => 'No se pudo actualizar el presupuesto'
						);


						$this->response($respuesta);

						return;
					}
				}
			}
		}

		$respuesta = array(
			'error'   => false,
			'data'    => [],
			'mensaje' => 'Presupuesto actualizado con exito'
		);


		$this->pedeo->trans_commit();


		$this->response($respuesta);
	}








	

	// METODO PARA OBTENER EL PERIODO EN UN PERIODO
	public function getBudgetPeriod_get(){
        $sqlSelect = "SELECT * FROM tmpp";

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
	// METODO PARA OBTENER LOS DATOS DEL PERIODO PRESUPUESTAL POR EL ID DEL PRESUPUESTO
	public function getBudgetPeriodById_get(){
			$Data = $this->get();
			if(!isset($Data['mpp_mpc_id'])){
				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return ;
			}
        $sqlSelect = "SELECT * FROM tmpp WHERE mpp_mpc_id = :mpp_mpc_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':mpp_mpc_id' => $Data['mpp_mpc_id']));

        if(isset($resSelect[0])){

          $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => 'Operacion exitosa');

        }else{

            $respuesta = array(
              'error'   => true,
              'data' => array(),
              'mensaje'	=> 'busqueda sin resultados'
            );

        }

         $this->response($respuesta);
    }
	// METODO PARA CREAR INSERTAR LOS DATOS EN LOS PERIODOS PRESUPUESTALES
	public function createBugetPeriod_post(){
		$Data = $this->post();
		if(!isset($Data['mpp_mpc_id']) OR
			!isset($Data['mpp_profit']) OR
			!isset($Data['mpp_fecha_inicial']) OR
			!isset($Data['mpp_fecha_final'])){
					$this->response(array(
		        'error'  => true,
		        'data'   => [],
		        'mensaje'=>'La informacion enviada no es valida'
		      ), REST_Controller::HTTP_BAD_REQUEST);

		      return ;
		}
		// BUSCA EL ACUMULADO DEL PRESUPUESTO
		$sqlSelect = "SELECT COALESCE(sum(mpp_profit), 0) as current_profit from tmpp where mpp_mpc_id = :mpp_mpc_id ";
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mpp_mpc_id'=>$Data['mpp_mpc_id']));

		// VERIFICA QUE LA SUMATORIA DE LOS PROFITS Y EL VALOR INGRESADO NO SEA SUPERIOR AL 100%
		$sqlDetailsum = "SELECT sum(mpc1_saldo) as mpc1_saldo from mpc1 where mpc1_mpc_id = :mpp_mpc_id";
		$resDetailSum = $this->pedeo->queryTable($sqlDetailsum, array(':mpp_mpc_id'=>$Data['mpp_mpc_id']));

		//VERIFICA QUE LAS FECHAS SE CUMPLAN
		$fiPP =  date($Data['mpp_fecha_inicial']);
		$ffpp =  date($Data['mpp_fecha_final']);
		$fp =  date($Data['pre_date']);
		$ff =  date($Data['pre_enddate']);
		
		if(!($fiPP >= $fp AND
		 	$ffpp <= $ff)){
			
				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'Fechas inconsistentes'
				  ), REST_Controller::HTTP_BAD_REQUEST);
	
				  return ;
		}

		$currentprofit = $resSelect[0]['current_profit'];
		$porcent = $currentprofit + $Data['mpp_profit'] ;
		
		// 
		if($porcent > 100){
		$this->response(array(
			'error'  => true,
			'data'   => [],
			'mensaje'=>'El valor excede el limite'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
		}	
			// SI TODO ESTA CORRECTO INSERTA EL REGISTRO EN LA TABLA
		$sqlInsert ="INSERT INTO tmpp (mpp_mpc_id, mpp_profit, mpp_fecha_inicial, mpp_fecha_final)
									VALUES(:mpp_mpc_id,:mpp_profit,:mpp_fecha_inicial,:mpp_fecha_final)";

		$resInsert = $this->pedeo->insertRow($sqlInsert,array(
										':mpp_mpc_id' =>$Data['mpp_mpc_id'],
										':mpp_profit' =>$porcent,
										':mpp_fecha_inicial' =>$Data['mpp_fecha_inicial'],
										':mpp_fecha_final' =>$Data['mpp_fecha_final']
									));

		if(is_numeric($resInsert) && $resInsert > 0){

			$respuesta = array(
				'error' => false,
				'data'  => $resInsert,
				'mensaje' => 'Presupuesto creado con exito');
		}else{

			$respuesta = array(
				'error' => true,
				'data'  => $resInsert,
				'mensaje' => 'No se pudo crear el detalle de presupuesto');
		}


		 $this->response($respuesta);
	}

	private function getBudget($id){
		$sqlSelect = "SELECT COALESCE(sum(mpc1_saldo),0) as mpc1_saldo from tmpc
					join mpc1 on mpc_id = mpc1_mpc_id
					where mpc_id = :mpp_mpc_id ";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mpp_mpc_id'=>$id));
		
		return $resSelect[0];
	}




}
