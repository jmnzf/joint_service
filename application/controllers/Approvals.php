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
			$cm = 0;

  		if( !isset($Data['Documento']) ){

  			$respuesta = array(
  				'error' => true,
  				'data'  => array(),
  				'mensaje' =>'La informacion enviada no es valida'
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


						$sqlInsertDetail = "INSERT INTO public.mau1(au1_doctotal, au1_sn, au1_c1, au1_is_query, au1_query,au1_docentry,au1_doctotal2)
																VALUES (:au1_doctotal, :au1_sn, :au1_c1, :au1_is_query, :au1_query,:au1_docentry,:au1_doctotal2)";


						if( $Data['CondicionMonto'] == 1 || $Data['CondicionMonto'] == '1' ){
									$cm = '>';
						}else{
								  $cm = 'BETWEEN';
						}

						$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

										':au1_doctotal' => is_numeric($Data['Monto'])?$Data['Monto']:0,
										':au1_doctotal2' => is_numeric($Data['Monto2'])?$Data['Monto2']:0,
										':au1_sn' 			=> NULL,
										':au1_c1' 			=> $cm,
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

			$this->response($respuesta);
	}


	//OBTENER MODELO DE APROBACIONES -> por usuario
	public function getApprovalModel_get(){

				$Data = $this->get();

				$sqlSelect = "SELECT
																t0.mau_docentry,
																t1.mdt_docname,
																t0.mau_quantity,
																t0.mau_decription,
																t0.mau_date,
																CASE
																	WHEN t0.mau_status = 1 THEN 'Activo'
																	ELSE 'Inactivo'
																END AS estado
														FROM tmau t0
														INNER JOIN dmdt t1
														ON t0.mau_doctype = t1.mdt_doctype";

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

				$Data = $this->get();

			   $sqlApprovers = "SELECT
																t1.mau_approvers
													from dpap t0
													inner join tmau t1 on t0.pap_model = t1.mau_docentry";
				$resSqlApprovers = $this->pedeo->queryTable($sqlApprovers, array());

				$UserModel = " WHERE t10.pgu_id_usuario in (".$resSqlApprovers[0]['mau_approvers'].")";
				//
				// if(!isset($Data['card_code'])){
				//
				// 	$respuesta = array(
				// 		'error' => true,
				// 		'data'  => array(),
				// 		'mensaje' =>'La informacion enviada no es valida'
				// 	);
				//
				// 	$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
				//
				// 	return;
				// }

				$sqlSelect = "SELECT
											t3.mdt_docname,
											t8.mdt_docname as origen,
											t0.pap_docentry,
											t0.pap_doctype,
											t0.pap_docnum,
											t0.pap_docdate,
											t0.pap_duedate,
											t0.pap_cardname,
											t0.pap_comment,
											t0.pap_createby,
											t0.pap_origen AS origin,
											CASE
											WHEN COALESCE(TRIM(CONCAT(T5.DMD_ADRESS,' ',T5.DMD_CITY)),'') = ''
											THEN TRIM(CONCAT(T7.DMD_ADRESS,' ',T7.DMD_CITY))
											ELSE TRIM(CONCAT(T5.DMD_ADRESS,' ',T5.DMD_CITY))
											END direccion,
											concat(t6.dmc_name,' ',t6.dmc_last_name) contacto,
											CONCAT(T0.pap_CURRENCY,' ',TRIM(TO_CHAR(pap_baseamnt,'999,999,999,999.00'))) base ,
											CONCAT(T0.pap_CURRENCY,' ',TRIM(TO_CHAR(pap_DISCOUNT,'999,999,999,999.00'))) descuento,
											CONCAT(T0.pap_CURRENCY,' ',TRIM(TO_CHAR(pap_doctotal,'999,999,999,999.00'))) as pap_doctotal,
											CONCAT(T0.pap_CURRENCY,' ',TRIM(TO_CHAR(pap_TAXTOTAl,'999,999,999,999.00'))) iva,
											CONCAT(T0.pap_CURRENCY,' ',TRIM(TO_CHAR((T0.pap_baseamnt - T0.pap_DISCOUNT),'999,999,999,999.00'))) subtotal,
											case
											    when coalesce(cast(t11.bad_estado as varchar),'0') = '1' then 'Aprobado Por mi'
											    when coalesce(cast(t11.bad_estado as varchar),'0') = '2' then 'Rechazado Por mi'
											    else t1.estado
											end estado,
											t2.mev_names as pap_slpcode
											FROM dpap t0
											INNER JOIN responsestatus t1 ON t0.pap_docentry = t1.id and t0.pap_doctype = t1.tipo
											INNER JOIN dmev t2 on t0.pap_slpcode = t2.mev_id
											INNER JOIN dmdt t3 on t0.pap_doctype = t3.mdt_doctype
											LEFT JOIN DMSN T4 ON t0.pap_cardcode = t4.dms_card_code
											LEFT JOIN DMSD T5 ON T0.pap_ADRESS = CAST(T5.DMD_ID AS VARCHAR)
											LEFT JOIN DMSC T6 ON T0.pap_CONTACID = CAST(T6.DMC_ID AS VARCHAR)
											LEFT JOIN DMSD T7 ON T4.DMS_CARD_CODE = T7.DMD_CARD_CODE
											LEFT JOIN dmdt t8 on pap_origen = t8.mdt_doctype
											inner join tmau t9 on t9.mau_docentry = t0.pap_model
											inner join pgus t10 on t10.pgu_code_user = :pgu_code_user
											left join tbad t11 on  t11.bad_origen = t0.pap_origen and t11.bad_docentry = t0.pap_docentry
											and t11.bad_createby = :pgu_code_user".$UserModel;

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(':pgu_code_user' => $Data['pgu_code_user']));
// print_r($sqlSelect);exit();die();
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
//obtener aprobaciones solicitadas por usuario
	public function getApprovalCb_get(){

				$Data = $this->get();

				$sqlSelect = "SELECT
											t3.mdt_docname,
											t8.mdt_docname as origen,
											t0.pap_docentry,
											t0.pap_doctype,
											t0.pap_docnum,
											t0.pap_docdate,
											t0.pap_duedate,
											t0.pap_cardname,
											t0.pap_comment,
											t0.pap_createby,
											t0.pap_origen AS origin,
											CASE
											WHEN COALESCE(TRIM(CONCAT(T5.DMD_ADRESS,' ',T5.DMD_CITY)),'') = ''
											THEN TRIM(CONCAT(T7.DMD_ADRESS,' ',T7.DMD_CITY))
											ELSE TRIM(CONCAT(T5.DMD_ADRESS,' ',T5.DMD_CITY))
											END direccion,
											concat(t6.dmc_name,' ',t6.dmc_last_name) contacto,
											CONCAT(T0.pap_CURRENCY,' ',TRIM(TO_CHAR(pap_baseamnt,'999,999,999,999.00'))) base ,
											CONCAT(T0.pap_CURRENCY,' ',TRIM(TO_CHAR(pap_DISCOUNT,'999,999,999,999.00'))) descuento,
											CONCAT(T0.pap_CURRENCY,' ',TRIM(TO_CHAR(pap_doctotal,'999,999,999,999.00'))) as pap_doctotal,
											CONCAT(T0.pap_CURRENCY,' ',TRIM(TO_CHAR(pap_TAXTOTAl,'999,999,999,999.00'))) iva,
											CONCAT(T0.pap_CURRENCY,' ',TRIM(TO_CHAR((T0.pap_baseamnt - T0.pap_DISCOUNT),'999,999,999,999.00'))) subtotal,
											case
											    when coalesce(cast(t11.bad_estado as varchar),'0') = '1' then 'Aprobado Por mi'
											    when coalesce(cast(t11.bad_estado as varchar),'0') = '2' then 'Rechazado Por mi'
											    else t1.estado
											end estado,
											t2.mev_names as pap_slpcode,
											t0.pap_createby
											FROM dpap t0
											INNER JOIN responsestatus t1 ON t0.pap_docentry = t1.id and t0.pap_doctype = t1.tipo
											INNER JOIN dmev t2 on t0.pap_slpcode = t2.mev_id
											INNER JOIN dmdt t3 on t0.pap_doctype = t3.mdt_doctype
											LEFT JOIN DMSN T4 ON t0.pap_cardcode = t4.dms_card_code
											LEFT JOIN DMSD T5 ON T0.pap_ADRESS = CAST(T5.DMD_ID AS VARCHAR)
											LEFT JOIN DMSC T6 ON T0.pap_CONTACID = CAST(T6.DMC_ID AS VARCHAR)
											LEFT JOIN DMSD T7 ON T4.DMS_CARD_CODE = T7.DMD_CARD_CODE
											LEFT JOIN dmdt t8 on pap_origen = t8.mdt_doctype
											inner join tmau t9 on t9.mau_docentry = t0.pap_model
											inner join pgus t10 on t10.pgu_code_user = t0.pap_createby
											left join tbad t11 on  t11.bad_origen = t0.pap_origen and t11.bad_docentry = t0.pap_docentry
											WHERE t0.pap_createby = :pap_createby";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(':pap_createby' => $Data['pap_createby']));
// print_r($sqlSelect);exit();die();
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


		public function setApprovalDoc_post(){
			  		$Data = $this->post();

			  		if(!isset($Data['bad_doctype']) OR
				      !isset($Data['bad_docentry'])  OR
				      !isset($Data['bad_origen']) OR
				      !isset($Data['bad_estado']) OR
				      !isset($Data['bad_createby'])
				    ){

			  			$respuesta = array(
			  				'error' => true,
			  				'data'  => array(),
			  				'mensaje' =>'La informacion enviada no es valida'
			  			);

			  			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			  			return;
			  		}

						$this->pedeo->trans_begin();

						$sqlInsert = "INSERT INTO tbad(bad_doctype,bad_docentry,bad_origen,bad_estado,bad_createby,bad_createdate)
												  VALUES (:bad_doctype, :bad_docentry, :bad_origen, :bad_estado, :bad_createby , :bad_createdate)";


						$resInsert = $this->pedeo->insertRow($sqlInsert, array(
									':bad_doctype'    => $Data['bad_doctype'],
									':bad_docentry'   => $Data['bad_docentry'],
									':bad_origen'  	  => $Data['bad_origen'],
									':bad_estado'  		=> $Data['bad_estado'],
									':bad_createby' 	=> $Data['bad_createby'],
									':bad_createdate' => date('Y-m-d H:i:s')

						));


						if(is_numeric($resInsert) && $resInsert > 0){
									//VALIDACION DE CANTIDADES DE REGISTRO PARA APROBAR == CARIDAD DE APROBACIONES POR MODELO DE DOCUMENTO
									$sqlComp = "SELECT COALESCE(case when t2.bad_estado = 1 then count(t2.bad_estado) end, 0) aprobados,
															 COALESCE(case when t2.bad_estado = 2 then count(t2.bad_estado) end, 0 ) rechazados,
															  COALESCE(case when t2.bad_estado = 2 then count(t2.bad_estado) end, 0) +  COALESCE(case when t2.bad_estado = 1 then count(t2.bad_estado) end, 0) total_respuestas,
															  cast(avg(t1.pap_qtyrq) as int)  requeridas, cast (avg(t1.pap_qtyap) as integer) aprobadores
															FROM dpap t1
															left join tbad t2
															on t2.bad_docentry = t1.pap_docentry and t2.bad_origen = t1.pap_origen
															WHERE t2.bad_estado = 1
															AND t1.pap_doctype = :pap_doctype
															AND t1.pap_docentry = :pap_docentry
															AND t1.pap_origen = :pap_origen
															GROUP BY t2.bad_estado";

									$resComp = $this->pedeo->queryTable($sqlComp, array(':pap_doctype' => $Data['bad_doctype'], ':pap_docentry' => $Data['bad_docentry'],':pap_origen' => $Data['bad_origen']));

									if( isset($resComp[0]) ) {

											$valorEstado = 0;
											$CambiarEstado = 1;

											$aprobados   = $resComp[0]['aprobados'];
											$rechazados  = $resComp[0]['rechazados'];
											$respuestas  = $resComp[0]['total_respuestas'];
											$requeridas  = $resComp[0]['requeridas'];
											$aprobadores = $resComp[0]['aprobadores'];

											if( $aprobados == $requeridas OR $aprobados > $requeridas){

													$valorEstado = 4; // APROBADO


											}else if( $rechazados == $aprobadores OR $rechazados > $aprobadores ){

													$valorEstado = 6; // RECHAZADO

											}else if( ($aprobados + $rechazados) == $aprobadores OR ($aprobados + $rechazados) > $aprobadores){

													$valorEstado = 6; // RECHAZADO

											}else{
													$CambiarEstado = 0;

											}



											if( $CambiarEstado == 1 ){

														//SE INSERTA EL ESTADO DEL DOCUMENTO

														$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																								VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

														$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


																			':bed_docentry'  =>  $Data['bad_docentry'],
																			':bed_doctype'   => $Data['bad_doctype'],
																			':bed_status'    => $valorEstado, //ESTADO CERRADO
																			':bed_createby'  => $Data['bad_createby'],
																			':bed_date'      => date('Y-m-d'),
																			':bed_baseentry' => NULL,
																			':bed_basetype'  => NULL
														));

														if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

																	$respuesta = array(
																		'error'   => false,
																		'data' => $resInsertEstado,
																		'mensaje'	=> 'Operacion exitosa'
																	);

														}else{

																 $this->pedeo->trans_rollback();

																	$respuesta = array(
																		'error'   => true,
																		'data' => $resInsertEstado,
																		'mensaje'	=> 'No se pudo cambiar el estado del documento'
																	);


																	$this->response($respuesta);

																	return;
														}

														//FIN PROCESO ESTADO DEL DOCUMENTO
											}

									}


									$this->pedeo->trans_commit();

									$respuesta = array(
										'error'   => false,
										'data'    => [],
										'mensaje' =>'Operacion exitosa'
									);

						}else{

									$this->pedeo->trans_rollback();
									$respuesta = array(
										'error'   => true,
										'data' 		=> $resInsert,
										'mensaje'	=> 'No se pudo completar la operacion'
									);

						}

						$this->response($respuesta);
		}


		//SE ACTUALIZA EL ESTADO DEL USUARIO SEGUN EL MODELO
		public function updateStatusUserModel_get(){

					$Data = $this->get();
					$estado = 0;

					if(!isset($Data['aus_id'])){

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'La informacion enviada no es valida'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}

					$Sql = " SELECT aus_status FROM taus WHERE aus_id = :aus_id";

					$resSql = $this->pedeo->queryTable($Sql, array(

								':aus_id' => $Data['aus_id']
					));

					if(isset($resSql[0])){

							if( is_null($resSql[0]['aus_status']) ){

										$estado = 0;

							}else if($resSql[0]['aus_status'] == 0){

										$estado = 1;

							}else if( $resSql[0]['aus_status'] == 1 ) {

										$estado = 0;
							}

					}else{

										$estado = 0;
					}

					$sqlUpdate = "UPDATE taus SET aus_status = :aus_status	WHERE aus_id = :aus_id";


					$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

								':aus_status' => $estado,
								':aus_id' 		=> $Data['aus_id']
					));


					if(is_numeric($resUpdate) && $resUpdate == 1){

								$respuesta = array(
									'error'   => false,
									'data'    => $resUpdate,
									'mensaje' =>'Operacion exitosa'
								);


					}else{

								$respuesta = array(
									'error'   => true,
									'data'    => $resUpdate,
									'mensaje'	=> 'No se puedo actualizar el estado del usaurio'
								);

					}

					 $this->response($respuesta);
		}


		//SE ACTUALIZA EL ESTADO DEL MODELO
		public function updateStatusModel_get(){

					$Data = $this->get();
					$estado = 0;

					if(!isset($Data['mau_docentry'])){

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'La informacion enviada no es valida'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}

					$Sql = " SELECT mau_status FROM tmau WHERE mau_docentry = :mau_docentry";

					$resSql = $this->pedeo->queryTable($Sql, array(

								':mau_docentry' => $Data['mau_docentry']
					));

					if(isset($resSql[0])){

							if( is_null($resSql[0]['mau_status']) ){

										$estado = 0;

							}else if($resSql[0]['mau_status'] == 0){

										$estado = 1;

							}else if( $resSql[0]['mau_status'] == 1 ) {

										$estado = 0;
							}
					}else{

										$estado = 0;
					}

					$sqlUpdate = "UPDATE tmau SET mau_status = :mau_status	WHERE mau_docentry = :mau_docentry";


					$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

								':mau_status' 	=> $estado,
								':mau_docentry' => $Data['mau_docentry']
					));


					if(is_numeric($resUpdate) && $resUpdate == 1){

								$respuesta = array(
									'error'   => false,
									'data'    => $resUpdate,
									'mensaje' =>'Operacion exitosa'
								);


					}else{

								$respuesta = array(
									'error'   => true,
									'data'    => $resUpdate,
									'mensaje'	=> 'No se puedo actualizar el estado del usaurio'
								);

					}

					 $this->response($respuesta);
		}


}
