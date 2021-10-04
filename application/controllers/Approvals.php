<?php
// MODELO DE APROBACIONES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Approvals extends REST_Controller {

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

	public function setApprovalModel_post(){
  		$Data = $this->post();

  		if( !isset($Data['Documento']) ){

  			$respuesta = array(
  				'error' => true,
  				'data'  => array(),
  				'mensaje' =>'La informacion enviada no es valida'
  			);

  			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

  			return;
  		}


			$sql = " SELECT * FROM tmau WHERE mau_doctype = :mau_doctype ";
			$ressql = $this->pedeo->queryTable($sql, array(
					':mau_doctype' => $Data['Documento']
			));


			if(isset($ressql[0])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'Ya existe un modelo para ese documento'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}


			// Se Inicia la transaccion,
			// Todas las consultas de modificacion siguientes
			// aplicaran solo despues que se confirme la transaccion,
			// de lo contrario no se aplicaran los cambios y se devolvera
			// la base de datos a su estado original.

			$this->pedeo->trans_begin();


			$sqlInsert = "INSERT INTO tmau(mau_doctype, mau_quantity, mau_status, mau_approvers, mau_decription, mau_createby, mau_date)
									  VALUES (:mau_doctype, :mau_quantity, :mau_status, :mau_approvers, :mau_decription , :mau_createby, :mau_date)";


			$resInsert = $this->pedeo->insertRow($sqlInsert, array(
						':mau_doctype'    => $Data['Documento'],
						':mau_quantity'   => $Data['CantidadAprovadores'],
						':mau_status'  	  => 1,
						':mau_approvers'  => $Data['UsuariosAprobadores'],
						':mau_decription' => $Data['Descripcion'],
						':mau_createby' 	=> $Data['user'],
						':mau_date' 			=> date('Y-m-d')

			));


			if(is_numeric($resInsert) && $resInsert > 0){


						$sqlInsertDetail = "INSERT INTO public.mau1(au1_doctotal, au1_sn, au1_c1, au1_is_query, au1_query,au1_docentry)
																VALUES (:au1_doctotal, :au1_sn, :au1_c1, :au1_is_query, :au1_query,:au1_docentry)";


						$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

										':au1_doctotal' => is_numeric($Data['Monto'])?$Data['Monto']:0,
										':au1_sn' 			=> $Data['SocioNegocio'],
										':au1_c1' 			=> $Data['CondicionMonto'],
										':au1_is_query' => 0,
										':au1_query'    => '',
										':au1_docentry' => $resInsert
						));


						if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

						}else{

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 		=> $resInsertDetail,
									'mensaje'	=> 'No se pudo registrar el modelo'
								);
								$this->response($respuesta);

								return;
						}


						// Si todo sale bien despues de insertar el detalle
						// se confirma la trasaccion  para que los cambios apliquen permanentemente
						// en la base de datos y se confirma la operacion exitosa.
						$this->pedeo->trans_commit();


						$respuesta = array(
							'error' => false,
							'data' => $resInsert,
							'mensaje' =>'Modelo registrado con exito'
						);


			}else{

						$respuesta = array(
							'error'   => true,
							'data' 		=> $resInsert,
							'mensaje'	=> 'No se pudo registrar el modelo'
						);

			}
// INICIO DEL PROCESO
//VALIDACION PARA APROBACION
	// 		public function setApprovalDoc_post(){
	// 	  		$Data = $this->post();
	//
	// 	  		if( !isset($Data['Documento']) ) or
	// 		      !isset($Data['bad_docentry'])  or
	// 		      !isset($Data['bad_origen']) or
	// 		      !isset($Data['bad_estado']) or
	// 		      !isset($Data['bad_createby']) or
	// 					!isset($Data['bad_createdate'])
	// 		    ){
	//
	// 	  			$respuesta = array(
	// 	  				'error' => true,
	// 	  				'data'  => array(),
	// 	  				'mensaje' =>'La informacion enviada no es valida'
	// 	  			);
	//
	// 	  			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
	//
	// 	  			return;
	// 	  		}
	//
	//
	//
	// 				$sqlInsert = "INSERT INTO tbad(bad_doctype,bad_docentry,bad_origen,bad_estado,bad_createby,bad_createdate)
	// 										  VALUES (:bad_doctype, :bad_docentry, :bad_origen, :bad_estado, :bad_createby , :bad_createdate)";
	//
	//
	// 				$resInsert = $this->pedeo->insertRow($sqlInsert, array(
	// 							':bad_doctype'    => $Data['Documento'],
	// 							':bad_docentry'   => $Data['bad_docentry'],
	// 							':bad_origen'  	  => $Data['bad_origen'],
	// 							':bad_estado'  => $Data['bad_estado'],
	// 							':bad_createby' => $Data['Descripcion'],
	// 							':bad_createdate' 	=> $Data['bad_createby'],
	// 							':bad_createdate' 			=> date('Y-m-d H:i:s')
	//
	// 				));
	//
	//
	// 				if(is_numeric($resInsert) && $resInsert > 0){
	// 					//VALIDACION DE CANTIDADES DE REGISTRO PARA APROBAR == CARIDAD DE APROBACIONES POR MODELO DE DOCUMENTO
	// 					$sqlComp = "SELECT count(t0.*) conteo
	// 											FROM tbad t0
	// 											INNER JOIN tmau t1 on t0.bad_doctype = t1.mau_doctype
	// 											WHERE t0.bad_estado = 1 and t1.mau_doctype = :mau_doctype";
	// 					$resPostUpdate = $this->pedeo->queryTable($sqlComp, array(':mau_doctype' => $Data['Documento']));
	//
	// 					if(isset($resPostUpdate[0] && $resPostUpdate[0]['conteo'] == 1)){
	// 						$sqlUpdate = "UPDATE tbed SET bed_estado = 6 WHERE bed_docentry = :bed_docentry AND bed_doctype = :bed_doctype";
	// 						$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
	// 							':bed_docentry' => $Data['bad_docentry'],
	// 							':bed_doctype' => $Data['Documento']
	// 						));
	// 					}else{
	// 						$respuesta = array(
	// 							'error' => false,
	// 							'data' => $resInsert,
	// 							'mensaje' =>'Modelo registrado con exito'
	// 					}
	//
	// 					$respuesta = array(
	// 						'error' => false,
	// 						'data' => $resInsert,
	// 						'mensaje' =>'Modelo registrado con exito'
	// 					);
	//
	//
	// 				}else{
	//
	// 							$respuesta = array(
	// 								'error'   => true,
	// 								'data' 		=> $resInsert,
	// 								'mensaje'	=> 'No se pudo registrar el modelo'
	// 							);
	//
	// 				}
	//
	//
	//
	//
  // 		$this->response($respuesta);
	// }
//FIN DEL PROCESO

	//OBTENER MODELO DE APROBACIONES
	public function getApprovalModel_get(){

				$sqlSelect = "SELECT mau_docentry, mdt_docname, mau_quantity, mau_decription, mau_date
											FROM tmau
											INNER JOIN dmdt
											ON tmau.mau_doctype = dmdt.mdt_doctype";

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

	//OBTENER APROBACIONES
	public function getApproval_get(){

				$sqlSelect = self::getColumn('dpap','pap');

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
			//OBTENER APROBACIONES
			public function getApprovalBySN_get(){

				$Data = $this->get();

				if(!isset($Data['dms_card_code'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;

				}

				$sqlSelect = "SELECT distinct t0.*,t1.estado FROM dpap t0
											left JOIN responsestatus t1 on t0.pap_doctype = t1.tipo
											where pap_cardcode = :pap_cardcode
											";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":pap_cardcode" => $Data['dms_card_code']));

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

		//OBTENER Factura de Ventas DETALLE POR ID
		public function getApprovalDetail_get(){

					$Data = $this->get();

					if(!isset($Data['ap1_docentry'])){

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'La informacion enviada no es valida'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}

					$sqlSelect = " SELECT * FROM pap1 WHERE ap1_docentry = :ap1_docentry";

					$resSelect = $this->pedeo->queryTable($sqlSelect, array(":ap1_docentry" => $Data['ap1_docentry']));

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
