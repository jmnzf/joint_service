<?php
// NOTA CREDITO DE COMPRAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PurchaseNc extends REST_Controller {

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

  //CREAR NUEVA nota credito
	public function createPurchaseNc_post(){


			$DECI_MALES =  $this->generic->getDecimals();
      $Data = $this->post();
			$DetalleAsientoIngreso = new stdClass(); // Cada objeto de las linea del detalle consolidado
			$DetalleAsientoIva = new stdClass();
			$DetalleCostoInventario = new stdClass();
			$DetalleCostoCosto = new stdClass();
			$DetalleRetencion = new stdClass();
			$DetalleItemNoInventariable = new stdClass();
			$DetalleConsolidadoIngreso = []; // Array Final con los datos del asiento solo ingreso
			$DetalleConsolidadoCostoInventario = [];
			$DetalleConsolidadoCostoCosto = [];
			$DetalleConsolidadoRetencion = [];
			$DetalleConsolidadoIva = []; // Array Final con los datos del asiento segun el iva
			$DetalleConsolidadoItemNoInventariable = [];
			$inArrayIngreso = array(); // Array para mantener el indice de las llaves para ingreso
			$inArrayIva = array(); // Array para mantener el indice de las llaves para iva
			$inArrayCostoInventario = array();
			$inArrayCostoCosto = array();
			$inArrayRetencion = array();
			$inArrayItemNoInventariable = array();
		  $llave = ""; // la comnbinacion entre la cuenta contable,proyecto, unidad de negocio y centro de costo
			$llaveIva = ""; //segun tipo de iva
			$llaveCostoInventario = "";
			$llaveCostoCosto = "";
			$llaveRetencion = "";
			$llaveItemNoInventariable = "";
			$posicion = 0;// contiene la posicion con que se creara en el array DetalleConsolidado
			$posicionIva = 0;
			$posicionCostoInventario = 0;
			$posicionCostoCosto = 0;
			$posicionRetencion = 0;
			$posicionItemNoInventariable = 0;
			$codigoCuenta = ""; //para saber la naturaleza
			$grantotalCostoInventario = 0;
			$DocNumVerificado = 0;
			$ManejaInvetario  = 0;
			$ManejaLote = 0;
			$ManejaSerial = 0;
			$TasaDocLoc = 0; // MANTIENE EL VALOR DE LA TASA DE CONVERSION ENTRE LA MONEDA LOCAL Y LA MONEDA DEL DOCUMENTO
			$TasaLocSys = 0; // MANTIENE EL VALOR DE LA TASA DE CONVERSION ENTRE LA MONEDA LOCAL Y LA MONEDA DEL SISTEMA
			$MONEDALOCAL='';
		// DIFERENECIA DE
			$SumaCreditosSYS = 0;
			$SumaDebitosSYS = 0;
			$AC1LINE = 1;
			$TotalAcuRentencion = 0;
			$CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA


			// Se globaliza la variable sqlDetalleAsiento
			$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, ac1_base_tax, ac1_codret)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref,:ac1_line,:ac1_base_tax, :ac1_codret)";


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
            'mensaje' =>'No se encontro el detalle  de nota credito'
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
			//
			//VALIDANDO PERIODO CONTABLE
			$periodo = $this->generic->ValidatePeriod($Data['cnc_duedev'], $Data['cnc_docdate'],$Data['cnc_duedate'],0);

			if( isset($periodo['error']) && $periodo['error'] == false){

			}else{
				$respuesta = array(
					'error'   => true,
					'data'    => [],
					'mensaje' => isset($periodo['mensaje'])?$periodo['mensaje']:'no se pudo validar el periodo contable'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//PERIODO CONTABLE
			//
				//BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

				$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['cnc_series']));

				if(isset($resNumeracion[0])){

						$numeroActual = $resNumeracion[0]['pgs_nextnum'];
						$numeroFinal  = $resNumeracion[0]['pgs_last_num'];
						$numeroSiguiente = ($numeroActual + 1);

						if( $numeroSiguiente <= $numeroFinal ){

								$DocNumVerificado = $numeroSiguiente;

						}	else {

								$respuesta = array(
									'error' => true,
									'data'  => array(),
									'mensaje' =>'La serie de la numeración esta llena'
								);

								$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

								return;
						}

				}else{

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la serie de numeración para el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

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

				// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
				// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
				$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
				$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

				if(isset($resMonedaLoc[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la moneda local.'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				//

				$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

				// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
				$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
				$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

				if(isset($resMonedaSys[0])){

				}else{

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la moneda de sistema.'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}
				$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);
				//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
				// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO


				$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['cnc_currency'], ':tsa_date' => $Data['cnc_docdate']));

				if(isset($resBusTasa[0])){

				}else{

						if(trim($Data['cnc_currency']) != $MONEDALOCAL ){

								$respuesta = array(
									'error' => true,
									'data'  => array(),
									'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['cnc_currency'].' en la actual fecha del documento: '.$Data['cnc_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
								);

								$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

								return;
						}
				}

				$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['cnc_docdate']));

				if(isset($resBusTasa2[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['cnc_docdate']
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
				$TasaLocSys = $resBusTasa2[0]['tsa_value'];

        $sqlInsert = "INSERT INTO dcnc(cnc_series, cnc_docnum, cnc_docdate, cnc_duedate, cnc_duedev, cnc_pricelist, cnc_cardcode,
                      cnc_cardname, cnc_currency, cnc_contacid, cnc_slpcode, cnc_empid, cnc_comment, cnc_doctotal, cnc_baseamnt, cnc_taxtotal,
                      cnc_discprofit, cnc_discount, cnc_createat, cnc_baseentry, cnc_basetype, cnc_doctype, cnc_idadd, cnc_adress, cnc_paytype,
                      cnc_attch,cnc_createby)VALUES(:cnc_series, :cnc_docnum, :cnc_docdate, :cnc_duedate, :cnc_duedev, :cnc_pricelist, :cnc_cardcode, :cnc_cardname,
                      :cnc_currency, :cnc_contacid, :cnc_slpcode, :cnc_empid, :cnc_comment, :cnc_doctotal, :cnc_baseamnt, :cnc_taxtotal, :cnc_discprofit, :cnc_discount,
                      :cnc_createat, :cnc_baseentry, :cnc_basetype, :cnc_doctype, :cnc_idadd, :cnc_adress, :cnc_paytype, :cnc_attch,:cnc_createby)";


				// Se Inicia la transaccion,
				// Todas las consultas de modificacion siguientes
				// aplicaran solo despues que se confirme la transaccion,
				// de lo contrario no se aplicaran los cambios y se devolvera
				// la base de datos a su estado original.

			  $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':cnc_docnum' => $DocNumVerificado,
              ':cnc_series' => is_numeric($Data['cnc_series'])?$Data['cnc_series']:0,
              ':cnc_docdate' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
              ':cnc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
              ':cnc_duedev' => $this->validateDate($Data['cnc_duedev'])?$Data['cnc_duedev']:NULL,
              ':cnc_pricelist' => is_numeric($Data['cnc_pricelist'])?$Data['cnc_pricelist']:0,
              ':cnc_cardcode' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
              ':cnc_cardname' => isset($Data['cnc_cardname'])?$Data['cnc_cardname']:NULL,
              ':cnc_currency' => isset($Data['cnc_currency'])?$Data['cnc_currency']:NULL,
              ':cnc_contacid' => isset($Data['cnc_contacid'])?$Data['cnc_contacid']:NULL,
              ':cnc_slpcode' => is_numeric($Data['cnc_slpcode'])?$Data['cnc_slpcode']:0,
              ':cnc_empid' => is_numeric($Data['cnc_empid'])?$Data['cnc_empid']:0,
              ':cnc_comment' => isset($Data['cnc_comment'])?$Data['cnc_comment']:NULL,
              ':cnc_doctotal' => is_numeric($Data['cnc_doctotal'])?$Data['cnc_doctotal']:0,
              ':cnc_baseamnt' => is_numeric($Data['cnc_baseamnt'])?$Data['cnc_baseamnt']:0,
              ':cnc_taxtotal' => is_numeric($Data['cnc_taxtotal'])?$Data['cnc_taxtotal']:0,
              ':cnc_discprofit' => is_numeric($Data['cnc_discprofit'])?$Data['cnc_discprofit']:0,
              ':cnc_discount' => is_numeric($Data['cnc_discount'])?$Data['cnc_discount']:0,
              ':cnc_createat' => $this->validateDate($Data['cnc_createat'])?$Data['cnc_createat']:NULL,
              ':cnc_baseentry' => is_numeric($Data['cnc_baseentry'])?$Data['cnc_baseentry']:0,
              ':cnc_basetype' => is_numeric($Data['cnc_basetype'])?$Data['cnc_basetype']:0,
              ':cnc_doctype' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
              ':cnc_idadd' => isset($Data['cnc_idadd'])?$Data['cnc_idadd']:NULL,
              ':cnc_adress' => isset($Data['cnc_adress'])?$Data['cnc_adress']:NULL,
              ':cnc_paytype' => is_numeric($Data['cnc_paytype'])?$Data['cnc_paytype']:0,
							':cnc_createby' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
              ':cnc_attch' => $this->getUrl(count(trim(($Data['cnc_attch']))) > 0 ? $Data['cnc_attch']:NULL, $resMainFolder[0]['main_folder'])
						));

        if(is_numeric($resInsert) && $resInsert > 0){

					// Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['cnc_series']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear la nota credito'
								);

								$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

								return;
					}
					// Fin de la actualizacion de la numeracion del documento



					//SE INSERTA EL ESTADO DEL DOCUMENTO

					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
															VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


										':bed_docentry' => $resInsert,
										':bed_doctype' => $Data['cnc_doctype'],
										':bed_status' => 1, //ESTADO ABIERTO
										':bed_createby' => $Data['cnc_createby'],
										':bed_date' => date('Y-m-d'),
										':bed_baseentry' => NULL,
										':bed_basetype' => NULL
					));


					if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

					}else{

							 $this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resInsertEstado,
									'mensaje'	=> 'No se pudo registrar la nota credito'
								);


								$this->response($respuesta);

								return;
					}

					//FIN PROCESO ESTADO DEL DOCUMENTO


					//Se agregan los asientos contables*/*******

					$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user)
															 VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user)";


					$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

							':mac_doc_num' => 1,
							':mac_status' => 1,
							':mac_base_type' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
							':mac_base_entry' => $resInsert,
							':mac_doc_date' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
							':mac_doc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
							':mac_legal_date' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
							':mac_ref1' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
							':mac_ref2' => "",
							':mac_ref3' => "",
							':mac_loc_total' => is_numeric($Data['cnc_doctotal'])?$Data['cnc_doctotal']:0,
							':mac_fc_total' => is_numeric($Data['cnc_doctotal'])?$Data['cnc_doctotal']:0,
							':mac_sys_total' => is_numeric($Data['cnc_doctotal'])?$Data['cnc_doctotal']:0,
							':mac_trans_dode' => 1,
							':mac_beline_nume' => 1,
							':mac_vat_date' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
							':mac_serie' => 1,
							':mac_number' => 1,
							':mac_bammntsys' => is_numeric($Data['cnc_baseamnt'])?$Data['cnc_baseamnt']:0,
							':mac_bammnt' => is_numeric($Data['cnc_baseamnt'])?$Data['cnc_baseamnt']:0,
							':mac_wtsum' => 1,
							':mac_vatsum' => is_numeric($Data['cnc_taxtotal'])?$Data['cnc_taxtotal']:0,
							':mac_comments' => isset($Data['cnc_comment'])?$Data['cnc_comment']:NULL,
							':mac_create_date' => $this->validateDate($Data['cnc_createat'])?$Data['cnc_createat']:NULL,
							':mac_made_usuer' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
							':mac_update_date' => date("Y-m-d"),
							':mac_update_user' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL
					));


					if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
							// Se verifica que el detalle no de error insertando //
					}else{

							// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $resInsertAsiento,
									'mensaje'	=> 'No se pudo registrar la nota credito'
								);

								 $this->response($respuesta);

								 return;
					}


					//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
					if( isset($Data['cnc_baseentry']) && is_numeric($Data['cnc_baseentry']) && isset($Data['cnc_basetype']) && is_numeric($Data['cnc_basetype']) ){

						$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
						$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
							 ':bmd_doctype' => $Data['cnc_basetype'],
							 ':bmd_docentry' => $Data['cnc_baseentry']
						));


						if ( isset(	$resDocInicio[0] ) ) {

							$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
															bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
															VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
															:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

							$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

								':bmd_doctype' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
								':bmd_docentry' => $resInsert,
								':bmd_createat' => $this->validateDate($Data['cnc_createat'])?$Data['cnc_createat']:NULL,
								':bmd_doctypeo' => is_numeric($Data['cnc_basetype'])?$Data['cnc_basetype']:0, //ORIGEN
								':bmd_docentryo' => is_numeric($Data['cnc_baseentry'])?$Data['cnc_baseentry']:0,  //ORIGEN
								':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
								':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
								':bmd_docnum' => $DocNumVerificado,
								':bmd_doctotal' => is_numeric($Data['cnc_doctotal'])?$Data['cnc_doctotal']:0,
								':bmd_cardcode' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
								':bmd_cardtype' => 2
							));

							if( is_numeric($resInsertMD) && $resInsertMD > 0 ){

							}else{

								$this->pedeo->trans_rollback();

								 $respuesta = array(
									 'error'   => true,
									 'data' => $resInsertEstado,
									 'mensaje'	=> 'No se pudo registrar el movimiento del documento'
								 );


								 $this->response($respuesta);

								 return;
							}

						}else{

							$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
															bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
															VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
															:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

							$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

								':bmd_doctype' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
								':bmd_docentry' => $resInsert,
								':bmd_createat' => $this->validateDate($Data['cnc_createat'])?$Data['cnc_createat']:NULL,
								':bmd_doctypeo' => is_numeric($Data['cnc_basetype'])?$Data['cnc_basetype']:0, //ORIGEN
								':bmd_docentryo' => is_numeric($Data['cnc_baseentry'])?$Data['cnc_baseentry']:0,  //ORIGEN
								':bmd_tdi' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0, // DOCUMENTO INICIAL
								':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
								':bmd_docnum' => $DocNumVerificado,
								':bmd_doctotal' => is_numeric($Data['cnc_doctotal'])?$Data['cnc_doctotal']:0,
								':bmd_cardcode' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
								':bmd_cardtype' => 2
							));

							if( is_numeric($resInsertMD) && $resInsertMD > 0 ){

							}else{

								$this->pedeo->trans_rollback();

								 $respuesta = array(
									 'error'   => true,
									 'data' => $resInsertEstado,
									 'mensaje'	=> 'No se pudo registrar el movimiento del documento'
								 );


								 $this->response($respuesta);

								 return;
							}
						}

					}else{

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

							':bmd_doctype' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['cnc_createat'])?$Data['cnc_createat']:NULL,
							':bmd_doctypeo' => is_numeric($Data['cnc_basetype'])?$Data['cnc_basetype']:0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['cnc_baseentry'])?$Data['cnc_baseentry']:0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['cnc_doctotal'])?$Data['cnc_doctotal']:0,
							':bmd_cardcode' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
							':bmd_cardtype' => 2
						));

						if( is_numeric($resInsertMD) && $resInsertMD > 0 ){

						}else{

							$this->pedeo->trans_rollback();

							 $respuesta = array(
								 'error'   => true,
								 'data' => $resInsertEstado,
								 'mensaje'	=> 'No se pudo registrar el movimiento del documento'
							 );


							 $this->response($respuesta);

							 return;
						}
					}
					//FIN PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS




          foreach ($ContenidoDetalle as $key => $detail) {

								$CANTUOMPURCHASE = $this->generic->getUomPurchase( $detail['nc1_itemcode'] );

								if( $CANTUOMPURCHASE == 0 ){

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data' 		=> $detail['nc1_itemcode'],
										'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: '.$detail['nc1_itemcode']
									);

									 $this->response($respuesta);

									 return;
								}

                $sqlInsertDetail = "INSERT INTO cnc1(nc1_docentry, nc1_itemcode, nc1_itemname, nc1_quantity, nc1_uom, nc1_whscode,
                                    nc1_price, nc1_vat, nc1_vatsum, nc1_discount, nc1_linetotal, nc1_costcode, nc1_ubusiness, nc1_project,
                                    nc1_acctcode, nc1_basetype, nc1_doctype, nc1_avprice, nc1_inventory, nc1_acciva, nc1_linenum,nc1_codimp)VALUES(:nc1_docentry, :nc1_itemcode, :nc1_itemname, :nc1_quantity,
                                    :nc1_uom, :nc1_whscode,:nc1_price, :nc1_vat, :nc1_vatsum, :nc1_discount, :nc1_linetotal, :nc1_costcode, :nc1_ubusiness, :nc1_project,
                                    :nc1_acctcode, :nc1_basetype, :nc1_doctype, :nc1_avprice, :nc1_inventory, :nc1_acciva, :nc1_linenum,:nc1_codimp)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                        ':nc1_docentry' => $resInsert,
                        ':nc1_itemcode' => isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL,
                        ':nc1_itemname' => isset($detail['nc1_itemname'])?$detail['nc1_itemname']:NULL,
                        ':nc1_quantity' => is_numeric($detail['nc1_quantity']) ? $detail['nc1_quantity'] : 0,
                        ':nc1_uom' => isset($detail['nc1_uom'])?$detail['nc1_uom']:NULL,
                        ':nc1_whscode' => isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL,
                        ':nc1_price' => is_numeric($detail['nc1_price'])?$detail['nc1_price']:0,
                        ':nc1_vat' => is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0,
                        ':nc1_vatsum' => is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0,
                        ':nc1_discount' => is_numeric($detail['nc1_discount'])?$detail['nc1_discount']:0,
                        ':nc1_linetotal' => is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0,
                        ':nc1_costcode' => isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL,
                        ':nc1_ubusiness' => isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL,
                        ':nc1_project' => isset($detail['nc1_project'])?$detail['nc1_project']:NULL,
                        ':nc1_acctcode' => is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']:0,
                        ':nc1_basetype' => is_numeric($detail['nc1_basetype'])?$detail['nc1_basetype']:0,
                        ':nc1_doctype' => is_numeric($detail['nc1_doctype'])?$detail['nc1_doctype']:0,
                        ':nc1_avprice' => is_numeric($detail['nc1_avprice'])?$detail['nc1_avprice']:0,
                        ':nc1_inventory' => is_numeric($detail['nc1_inventory'])?$detail['nc1_inventory']:NULL,
												':nc1_acciva'  => is_numeric($detail['nc1_cuentaIva'])?$detail['nc1_cuentaIva']:0,
												':nc1_linenum' => is_numeric($detail['nc1_linenum'])?$detail['nc1_linenum']:0,
												':nc1_codimp'  => isset($detail['nc1_codimp'])?$detail['nc1_codimp']:NULL
                ));

								if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data' => $resInsertDetail,
												'mensaje'	=> 'No se pudo registrar la nota credito'
											);

											 $this->response($respuesta);

											 return;
								}

								// PROCESO PARA INSERTAR RETENCIONES
										if( isset($detail['detail']) ){



											$ContenidoRentencion = $detail['detail'];

											if(is_array($ContenidoRentencion)){
												if(intval(count($ContenidoRentencion)) > 0 ){

													foreach ($ContenidoRentencion as $key => $value) {

															$DetalleRetencion = new stdClass();

															$sqlInsertRetenciones = "INSERT INTO fcrt(crt_baseentry, crt_basetype, crt_typert, crt_basert, crt_profitrt, crt_totalrt,crt_base, crt_type, crt_linenum, crt_codret)
																											 VALUES (:crt_baseentry, :crt_basetype, :crt_typert, :crt_basert, :crt_profitrt, :crt_totalrt, :crt_base, :crt_type, :crt_linenum, :crt_codret)";

															$resInsertRetenciones = $this->pedeo->insertRow($sqlInsertRetenciones, array(

																		':crt_baseentry' => $resInsert,
																		':crt_basetype'  => $Data['cnc_doctype'],
																		':crt_typert'    => $value['crt_typert'],
																		':crt_basert'    => $value['crt_basert'],
																		':crt_profitrt'  => $value['crt_profitrt'],
																		':crt_totalrt'   => $value['crt_totalrt'],
																		':crt_base'			 => $value['crt_base'],
																		':crt_type'			 => $value['crt_type'],
																		':crt_linenum'   => $detail['nc1_linenum'],
																		':crt_codret'	   => $value['crt_typert']
															));



															if(is_numeric($resInsertRetenciones) && $resInsertRetenciones > 0){

																		$TotalAcuRentencion = $TotalAcuRentencion + $value['crt_totalrt'];

																		$DetalleRetencion->crt_typert   = $value['crt_typert'];
																		$DetalleRetencion->crt_basert   = $value['crt_totalrt'];
																		$DetalleRetencion->crt_profitrt = $value['crt_profitrt'];
																		$DetalleRetencion->crt_totalrt  = $value['crt_totalrt'];
																		$DetalleRetencion->crt_codret   = $value['crt_typert'];


																		$llaveRetencion = $DetalleRetencion->crt_typert.$DetalleRetencion->crt_profitrt;

																		if(in_array( $llaveRetencion, $inArrayRetencion )){

																				$posicionRetencion = $this->buscarPosicion( $llaveRetencion, $inArrayRetencion );

																		}else{

																				array_push( $inArrayRetencion, $llaveRetencion );
																				$posicionRetencion = $this->buscarPosicion( $llaveRetencion, $inArrayRetencion );

																		}

																		if( isset($DetalleConsolidadoRetencion[$posicionRetencion])){

																			if(!is_array($DetalleConsolidadoRetencion[$posicionRetencion])){
																				$DetalleConsolidadoRetencion[$posicionRetencion] = array();
																			}

																		}else{
																			$DetalleConsolidadoRetencion[$posicionRetencion] = array();
																		}

																		array_push( $DetalleConsolidadoRetencion[$posicionRetencion], $DetalleRetencion);



															}else{
																	// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
																	// se retorna el error y se detiene la ejecucion del codigo restante.
																		$this->pedeo->trans_rollback();
																		$respuesta = array(
																			'error'   => true,
																			'data' => $resInsertDetail,
																			'mensaje'	=> 'No se pudo registrar la factura de compras, fallo el proceso para insertar las retenciones'
																		);
																		 $this->response($respuesta);
																		 return;
															}

													}

												}

											}
										}
										// SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO Y LOTE
										$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
										$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

														':dma_item_code' => $detail['nc1_itemcode'],
														':dma_item_inv'  => 1
										));

										if(isset($resItemINV[0])){

											$ManejaInvetario = 1;

										}else{
											$ManejaInvetario = 0;
										}

										$sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
										$resLote = $this->pedeo->queryTable($sqlLote, array(

														':dma_item_code' => $detail['nc1_itemcode'],
														':dma_lotes_code'  => 1
										));

										if(isset($resLote[0])){
											$ManejaLote = 1;
										}else{
											$ManejaLote = 0;
										}

										// FIN PROCESO ITEM MANEJA INVENTARIO Y LOTE

										$exc_inv = is_numeric($detail['nc1_exc_inv'])?$detail['nc1_exc_inv']:0;



										// SI LA NOTA APLICA MOVIENDO INVENTARIO
										if( $exc_inv == 1 ){
											// si el item es inventariable
											if( $ManejaInvetario == 1 ){

														//SE VERIFICA SI EL ARTICULO MANEJA SERIAL
														$sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
														$resItemSerial = $this->pedeo->queryTable($sqlItemSerial, array(

																':dma_item_code' => $detail['nc1_itemcode'],
																':dma_series_code'  => 1
														));

														if(isset($resItemSerial[0])){
															$ManejaSerial = 1;

															$AddSerial = $this->generic->addSerial( $detail['serials'], $detail['nc1_itemcode'], $Data['cnc_doctype'], $resInsert, $DocNumVerificado, $Data['cnc_docdate'], 2, $Data['cnc_comment'], $detail['nc1_whscode'], $detail['nc1_quantity'], $Data['cnc_createby'] );

															if( isset($AddSerial['error']) && $AddSerial['error'] == false){

															}else{
																$respuesta = array(
																	'error'   => true,
																	'data'    => $AddSerial['data'],
																	'mensaje' => $AddSerial['mensaje']
																);

																$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

																return;
															}

														} else {
															$ManejaSerial = 0;
														}

														//

														//se busca el costo del item en el momento de la creacion del documento de venta
														// para almacenar en el movimiento de inventario

														$sqlCostoMomentoRegistro = '';
														$resCostoMomentoRegistro = [];

														//SI MANEJA LOTE
														if ( $ManejaLote == 1 ) {
															$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote";
															$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
																':bdi_whscode' => $detail['nc1_whscode'],
																':bdi_itemcode' => $detail['nc1_itemcode'],
																':bdi_lote' => $detail['ote_code']));

														}else{
															$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode";
															$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['nc1_whscode'], ':bdi_itemcode' => $detail['nc1_itemcode']));

														}


														if(isset($resCostoMomentoRegistro[0])){

															$sqlInserMovimiento = '';
															$resInserMovimiento = [];
															//SI EL ARTICULO MANEJA LOTE
															if ( $ManejaLote == 1 ) {
																//Se aplica el movimiento de inventario
																$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_lote)
																											 VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote)";

																$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

																		 ':bmi_itemcode' => isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL,
																		 ':bmi_quantity' => is_numeric($detail['nc1_quantity']) ? ( $detail['nc1_quantity'] * $Data['invtype'] ) : 0,
																		 ':bmi_whscode'  => isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL,
																		 ':bmi_createat' => $this->validateDate($Data['cnc_createat'])?$Data['cnc_createat']:NULL,
																		 ':bmi_createby' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
																		 ':bmy_doctype'  => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
																		 ':bmy_baseentry' => $resInsert,
																		 ':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
																		 ':bmi_currequantity' 	=> $resCostoMomentoRegistro[0]['bdi_quantity'],
																		 ':bmi_basenum'			=> $DocNumVerificado,
																		 ':bmi_docdate' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
																		 ':bmi_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
																		 ':bmi_duedev'  => $this->validateDate($Data['cnc_duedev'])?$Data['cnc_duedev']:NULL,
																		 ':bmi_comment' => isset($Data['cnc_comment'])?$Data['cnc_comment']:NULL,
																		 ':bmi_lote' => isset($detail['ote_code'])?$detail['ote_code']:NULL


																));
															}else{
																//Se aplica el movimiento de inventario
																$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment)
																											 VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment)";

																$sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

																		 ':bmi_itemcode' => isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL,
																		 ':bmi_quantity' => is_numeric($detail['nc1_quantity']) ? ( ( $detail['nc1_quantity'] * $CANTUOMPURCHASE ) * $Data['invtype'] ) : 0,
																		 ':bmi_whscode'  => isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL,
																		 ':bmi_createat' => $this->validateDate($Data['cnc_createat'])?$Data['cnc_createat']:NULL,
																		 ':bmi_createby' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
																		 ':bmy_doctype'  => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
																		 ':bmy_baseentry' => $resInsert,
																		 ':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
																		 ':bmi_currequantity' 	=> $resCostoMomentoRegistro[0]['bdi_quantity'],
																		 ':bmi_basenum'			=> $DocNumVerificado,
																		 ':bmi_docdate' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
																		 ':bmi_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
																		 ':bmi_duedev'  => $this->validateDate($Data['cnc_duedev'])?$Data['cnc_duedev']:NULL,
																		 ':bmi_comment' => isset($Data['cnc_comment'])?$Data['cnc_comment']:NULL


																));
															}


															if(is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0){
																	// Se verifica que el detalle no de error insertando //
															}else{

																	// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
																	// se retorna el error y se detiene la ejecucion del codigo restante.
																		$this->pedeo->trans_rollback();

																		$respuesta = array(
																			'error'   => true,
																			'data' => $sqlInserMovimiento,
																			'mensaje'	=> 'No se pudo registrar la nota credito'
																		);

																		 $this->response($respuesta);

																		 return;
															}



														}else{

																$this->pedeo->trans_rollback();

																$respuesta = array(
																	'error'   => true,
																	'data' => $resCostoMomentoRegistro,
																	'mensaje'	=> 'No se pudo registrar la nota credito, no se encontro el costo del articulo'
																);

																 $this->response($respuesta);

																 return;
														}

														//FIN aplicacion de movimiento de inventario


														//Se Aplica el movimiento en stock ***************
														// Buscando item en el stock
														$sqlCostoCantidad = '';
														$resCostoCantidad = [];
														//SE VALIDA SI EL ARTICULO MANEJA LOTE
														if ( $ManejaLote == 1 ) {
															$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
																									FROM tbdi
																									WHERE bdi_itemcode = :bdi_itemcode
																									AND bdi_whscode = :bdi_whscode
																									AND bdi_lote = :bdi_lote";

															$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

																			':bdi_itemcode' => $detail['nc1_itemcode'],
																			':bdi_whscode'  => $detail['nc1_whscode'],
																			':bdi_lote' 		=> $detail['ote_code']
															));
														}else{
															$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
																									FROM tbdi
																									WHERE bdi_itemcode = :bdi_itemcode
																									AND bdi_whscode = :bdi_whscode";

															$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

																			':bdi_itemcode' => $detail['nc1_itemcode'],
																			':bdi_whscode'  => $detail['nc1_whscode']
															));
														}


														if(isset($resCostoCantidad[0])){

															if($resCostoCantidad[0]['bdi_quantity'] > 0){

																		$CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
																		$CantidadNueva = ( $detail['nc1_quantity'] * $CANTUOMPURCHASE );


																		$CantidadTotal = ($CantidadActual - $CantidadNueva);

																		$sqlUpdateCostoCantidad =  "UPDATE tbdi
																																SET bdi_quantity = :bdi_quantity
																																WHERE  bdi_id = :bdi_id";

																		$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

																					 ':bdi_quantity' => $CantidadTotal,
																					 ':bdi_id' 			 => $resCostoCantidad[0]['bdi_id']
																		));

																		if(is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1){

																		}else{

																				 $this->pedeo->trans_rollback();

																				 $respuesta = array(
																					 'error'   => true,
																					 'data'    => $resUpdateCostoCantidad,
																					 'mensaje'	=> 'No se pudo crear la nota credito'
																				 );
																		}

															}else{

																				 $this->pedeo->trans_rollback();

																				 $respuesta = array(
																					 'error'   => true,
																					 'data'    => $resUpdateCostoCantidad,
																					 'mensaje' => 'No hay existencia para el item: '.$detail['nc1_itemcode']
																				 );
																}

														}else{

																		$this->pedeo->trans_rollback();

																		$respuesta = array(
																			'error'   => true,
																			'data' 		=> $resInsertCostoCantidad,
																			'mensaje'	=> 'El item no existe en el stock '.$detail['nc1_itemcode']
																		);

																		 $this->response($respuesta);

																		 return;
														}
														//FIN de  Aplicacion del movimiento en stock
														//SE VALIDA SI EXISTE EL LOTE

														if ( $ManejaLote == 1 ){
															$sqlFindLote = "SELECT ote_code FROM lote WHERE ote_code = :ote_code";
															$resFindLote = $this->pedeo->queryTable($sqlFindLote, array(':ote_code' => $detail['ote_code']));

															if( !isset($resFindLote[0]) ){
																// SI NO SE HA CREADO EL LOTE SE INGRESA
																$sqlInsertLote = "INSERT INTO lote(ote_code, ote_createdate, ote_duedate, ote_createby, ote_date, ote_baseentry, ote_basetype, ote_docnum)
																								VALUES(:ote_code, :ote_createdate, :ote_duedate, :ote_createby, :ote_date, :ote_baseentry, :ote_basetype, :ote_docnum)";
																$resInsertLote = $this->pedeo->insertRow($sqlInsertLote, array(

																	':ote_code' => $detail['ote_code'],
																	':ote_createdate' => $detail['ote_createdate'],
																	':ote_duedate' => $detail['ote_duedate'],
																	':ote_createby' => $Data['cnc_createby'],
																	':ote_date' => date('Y-m-d'),
																	':ote_baseentry' => $resInsert,
																	':ote_basetype' => $Data['cnc_doctype'],
																	':ote_docnum' => $DocNumVerificado
																));


																if( is_numeric($resInsertLote) && $resInsertLote > 0){

																}else{
																	$this->pedeo->trans_rollback();

																	$respuesta = array(
																		'error'   => true,
																		'data' 		=> $resInsertLote,
																		'mensaje'	=> 'No se pudo registrar la factura de compras'
																	);

																	 $this->response($respuesta);

																	 return;
																}
															}
														}
														//FIN VALIDACION DEL LOTE


											}


										}
										//FIN SI LA NOTA APLICA SIN MOVER INVENTARIO


									//LLENANDO DETALLE ASIENTO CONTABLES
									$DetalleAsientoIngreso = new stdClass();
									$DetalleAsientoIva = new stdClass();
									$DetalleCostoInventario = new stdClass();
									$DetalleCostoCosto = new stdClass();
									$DetalleItemNoInventariable = new stdClass();


									$DetalleAsientoIngreso->ac1_account = is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']: 0;
									$DetalleAsientoIngreso->ac1_prc_code = isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL;
									$DetalleAsientoIngreso->ac1_uncode = isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL;
									$DetalleAsientoIngreso->ac1_prj_code = isset($detail['nc1_project'])?$detail['nc1_project']:NULL;
									$DetalleAsientoIngreso->nc1_linetotal = is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0;
									$DetalleAsientoIngreso->nc1_vat = is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0;
									$DetalleAsientoIngreso->nc1_vatsum = is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0;
									$DetalleAsientoIngreso->nc1_price = is_numeric($detail['nc1_price'])?$detail['nc1_price']:0;
									$DetalleAsientoIngreso->nc1_itemcode = isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL;
									$DetalleAsientoIngreso->nc1_quantity = is_numeric($detail['nc1_quantity']) ? ( $detail['nc1_quantity'] * $CANTUOMPURCHASE ) : 0;
									$DetalleAsientoIngreso->em1_whscode = isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL;



									$DetalleAsientoIva->ac1_account = is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']: 0;
									$DetalleAsientoIva->ac1_prc_code = isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL;
									$DetalleAsientoIva->ac1_uncode = isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL;
									$DetalleAsientoIva->ac1_prj_code = isset($detail['nc1_project'])?$detail['nc1_project']:NULL;
									$DetalleAsientoIva->nc1_linetotal = is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0;
									$DetalleAsientoIva->nc1_vat = is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0;
									$DetalleAsientoIva->nc1_vatsum = is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0;
									$DetalleAsientoIva->nc1_price = is_numeric($detail['nc1_price'])?$detail['nc1_price']:0;
									$DetalleAsientoIva->nc1_itemcode = isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL;
									$DetalleAsientoIva->nc1_quantity = is_numeric($detail['nc1_quantity']) ? ( $detail['nc1_quantity'] * $CANTUOMPURCHASE ) : 0;
									$DetalleAsientoIva->nc1_cuentaIva = is_numeric($detail['nc1_cuentaIva'])?$detail['nc1_cuentaIva']:NULL;
									$DetalleAsientoIva->em1_whscode = isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL;
									$DetalleAsientoIva->codimp = isset($detail['nc1_codimp'])?$detail['nc1_codimp']:NULL;

									// se busca la cuenta contable del costoInventario y costoCosto
									// $sqlArticulo = "SELECT f2.dma_item_code,  f1.mga_acct_inv, f1.mga_acct_cost, f1.mga_accretpurch FROM dmga f1 JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code WHERE dma_item_code = :dma_item_code";
									//
									// $resArticulo = $this->pedeo->queryTable($sqlArticulo, array(":dma_item_code" => $detail['nc1_itemcode']));
									//
									// if(!isset($resArticulo[0])){
									//
									// 			$this->pedeo->trans_rollback();
									//
									// 			$respuesta = array(
									// 				'error'   => true,
									// 				'data' => $resArticulo,
									// 				'mensaje'	=> 'No se pudo registrar la nota credito'
									// 			);
									//
									// 			 $this->response($respuesta);
									//
									// 			 return;
									// }



									if ( $exc_inv == 1 ){
										// VALIDANDO ITEM INVENTARIABLE
										if( $ManejaInvetario == 1 ){


											$DetalleCostoInventario->ac1_account =  is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']: 0;
											$DetalleCostoInventario->ac1_prc_code = isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL;
											$DetalleCostoInventario->ac1_uncode = isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL;
											$DetalleCostoInventario->ac1_prj_code = isset($detail['nc1_project'])?$detail['nc1_project']:NULL;
											$DetalleCostoInventario->nc1_linetotal = is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0;
											$DetalleCostoInventario->nc1_vat = is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0;
											$DetalleCostoInventario->nc1_vatsum = is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0;
											$DetalleCostoInventario->nc1_price = is_numeric($detail['nc1_price'])?$detail['nc1_price']:0;
											$DetalleCostoInventario->nc1_itemcode = isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL;
											$DetalleCostoInventario->nc1_quantity = is_numeric($detail['nc1_quantity']) ? ( $detail['nc1_quantity'] * $CANTUOMPURCHASE ) : 0;
											$DetalleCostoInventario->em1_whscode = isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL;
											$DetalleCostoInventario->ac1_inventory = $ManejaInvetario;


											$DetalleCostoCosto->ac1_account =  is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']: 0;
											$DetalleCostoCosto->ac1_prc_code = isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL;
											$DetalleCostoCosto->ac1_uncode = isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL;
											$DetalleCostoCosto->ac1_prj_code = isset($detail['nc1_project'])?$detail['nc1_project']:NULL;
											$DetalleCostoCosto->nc1_linetotal = is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0;
											$DetalleCostoCosto->nc1_vat = is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0;
											$DetalleCostoCosto->nc1_vatsum = is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0;
											$DetalleCostoCosto->nc1_price = is_numeric($detail['nc1_price'])?$detail['nc1_price']:0;
											$DetalleCostoCosto->nc1_itemcode = isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL;
											$DetalleCostoCosto->nc1_quantity = is_numeric($detail['nc1_quantity']) ? ( $detail['nc1_quantity'] * $CANTUOMPURCHASE ) : 0;
											$DetalleCostoCosto->em1_whscode = isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL;
											$DetalleCostoInventario->ac1_inventory = $ManejaInvetario;

										}else{
											$DetalleItemNoInventariable->ac1_account =  is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']: 0;
											$DetalleItemNoInventariable->ac1_prc_code = isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL;
											$DetalleItemNoInventariable->ac1_uncode = isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL;
											$DetalleItemNoInventariable->ac1_prj_code = isset($detail['nc1_project'])?$detail['nc1_project']:NULL;
											$DetalleItemNoInventariable->nc1_linetotal = is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0;
											$DetalleItemNoInventariable->nc1_vat = is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0;
											$DetalleItemNoInventariable->nc1_vatsum = is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0;
											$DetalleItemNoInventariable->nc1_price = is_numeric($detail['nc1_price'])?$detail['nc1_price']:0;
											$DetalleItemNoInventariable->nc1_itemcode = isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL;
											$DetalleItemNoInventariable->nc1_quantity = is_numeric($detail['nc1_quantity']) ? ( $detail['nc1_quantity'] * $CANTUOMPURCHASE ) : 0;
											$DetalleItemNoInventariable->em1_whscode = isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL;
										}
									}else{

										if( $ManejaInvetario == 1 ){
											$DetalleCostoCosto->ac1_account =  is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']: 0;
											$DetalleCostoCosto->ac1_prc_code = isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL;
											$DetalleCostoCosto->ac1_uncode = isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL;
											$DetalleCostoCosto->ac1_prj_code = isset($detail['nc1_project'])?$detail['nc1_project']:NULL;
											$DetalleCostoCosto->nc1_linetotal = is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0;
											$DetalleCostoCosto->nc1_vat = is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0;
											$DetalleCostoCosto->nc1_vatsum = is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0;
											$DetalleCostoCosto->nc1_price = is_numeric($detail['nc1_price'])?$detail['nc1_price']:0;
											$DetalleCostoCosto->nc1_itemcode = isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL;
											$DetalleCostoCosto->nc1_quantity = is_numeric($detail['nc1_quantity']) ? ( $detail['nc1_quantity'] * $CANTUOMPURCHASE ) : 0;
											$DetalleCostoCosto->em1_whscode = isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL;
											$DetalleCostoCosto->ac1_inventory = $ManejaInvetario;
										}else{
											$DetalleItemNoInventariable->ac1_account =  is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']: 0;
											$DetalleItemNoInventariable->ac1_prc_code = isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL;
											$DetalleItemNoInventariable->ac1_uncode = isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL;
											$DetalleItemNoInventariable->ac1_prj_code = isset($detail['nc1_project'])?$detail['nc1_project']:NULL;
											$DetalleItemNoInventariable->nc1_linetotal = is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0;
											$DetalleItemNoInventariable->nc1_vat = is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0;
											$DetalleItemNoInventariable->nc1_vatsum = is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0;
											$DetalleItemNoInventariable->nc1_price = is_numeric($detail['nc1_price'])?$detail['nc1_price']:0;
											$DetalleItemNoInventariable->nc1_itemcode = isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL;
											$DetalleItemNoInventariable->nc1_quantity = is_numeric($detail['nc1_quantity']) ? ( $detail['nc1_quantity'] * $CANTUOMPURCHASE ) : 0;
											$DetalleItemNoInventariable->em1_whscode = isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL;
										}
									}






									$codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
									$DetalleAsientoIngreso->codigoCuenta = $codigoCuenta;
									$DetalleAsientoIva->codigoCuenta = $codigoCuenta;

									$llave = $DetalleAsientoIngreso->ac1_uncode.$DetalleAsientoIngreso->ac1_prc_code.$DetalleAsientoIngreso->ac1_prj_code.$DetalleAsientoIngreso->ac1_account;
									$llaveIva = $DetalleAsientoIva->nc1_vat;


									if ( $exc_inv == 1 ){
										// VALIDANDO ITEM INVENTARIABLE
										if( $ManejaInvetario == 1 ){

											$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
											$DetalleCostoInventario->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);

											$llaveCostoInventario = $DetalleCostoInventario->ac1_account;
											$llaveCostoCosto = $DetalleCostoCosto->ac1_account;
										}else{
											$llaveItemNoInventariable = $DetalleItemNoInventariable->ac1_uncode.$DetalleItemNoInventariable->ac1_prc_code.$DetalleItemNoInventariable->ac1_prj_code.$DetalleItemNoInventariable->ac1_account;
										}
									}else{

											if( $ManejaInvetario == 1 ){
												$DetalleCostoCosto->codigoCuenta = substr($DetalleAsientoIngreso->ac1_account, 0, 1);
												$llaveCostoCosto = $DetalleCostoCosto->ac1_account;
											}else{
												$llaveItemNoInventariable = $DetalleItemNoInventariable->ac1_uncode.$DetalleItemNoInventariable->ac1_prc_code.$DetalleItemNoInventariable->ac1_prj_code.$DetalleItemNoInventariable->ac1_account;
											}
									}


									if(in_array( $llave, $inArrayIngreso )){

											$posicion = $this->buscarPosicion( $llave, $inArrayIngreso );

									}else{

											array_push( $inArrayIngreso, $llave );
											$posicion = $this->buscarPosicion( $llave, $inArrayIngreso );

									}


									if(in_array( $llaveIva, $inArrayIva )){

											$posicionIva = $this->buscarPosicion( $llaveIva, $inArrayIva );

									}else{

											array_push( $inArrayIva, $llaveIva );
											$posicionIva = $this->buscarPosicion( $llaveIva, $inArrayIva );

									}



									if ( $exc_inv == 1 ){
										// VALIDANDO ITEM INVENTARIABLE
										if( $ManejaInvetario == 1 ){

											if(in_array( $llaveCostoInventario, $inArrayCostoInventario )){

													$posicionCostoInventario = $this->buscarPosicion( $llaveCostoInventario, $inArrayCostoInventario );

											}else{

													array_push( $inArrayCostoInventario, $llaveCostoInventario );
													$posicionCostoInventario = $this->buscarPosicion( $llaveCostoInventario, $inArrayCostoInventario );

											}


											if(in_array( $llaveCostoCosto, $inArrayCostoCosto )){

													$posicionCostoCosto = $this->buscarPosicion( $llaveCostoCosto, $inArrayCostoCosto );

											}else{

													array_push( $inArrayCostoCosto, $llaveCostoCosto );
													$posicionCostoCosto = $this->buscarPosicion( $llaveCostoCosto, $inArrayCostoCosto );

											}
										}else{
											if(in_array( $llaveItemNoInventariable, $inArrayItemNoInventariable )){

													$posicionItemNoInventariable = $this->buscarPosicion( $llaveItemNoInventariable, $inArrayItemNoInventariable );

											}else{

													array_push( $inArrayItemNoInventariable, $llaveItemNoInventariable );
													$posicionItemNoInventariable = $this->buscarPosicion( $llaveItemNoInventariable, $inArrayItemNoInventariable );

											}
										}
									}else{
										if( $ManejaInvetario == 1 ){
											if(in_array( $llaveCostoCosto, $inArrayCostoCosto )){

													$posicionCostoCosto = $this->buscarPosicion( $llaveCostoCosto, $inArrayCostoCosto );

											}else{

													array_push( $inArrayCostoCosto, $llaveCostoCosto );
													$posicionCostoCosto = $this->buscarPosicion( $llaveCostoCosto, $inArrayCostoCosto );

											}
											// ITEM NO INVENTARIABLE
										}else{

											if(in_array( $llaveItemNoInventariable, $inArrayItemNoInventariable )){

													$posicionItemNoInventariable = $this->buscarPosicion( $llaveItemNoInventariable, $inArrayItemNoInventariable );

											}else{

													array_push( $inArrayItemNoInventariable, $llaveItemNoInventariable );
													$posicionItemNoInventariable = $this->buscarPosicion( $llaveItemNoInventariable, $inArrayItemNoInventariable );

											}
										}
									}


									if( isset($DetalleConsolidadoIva[$posicionIva])){

										if(!is_array($DetalleConsolidadoIva[$posicionIva])){
											$DetalleConsolidadoIva[$posicionIva] = array();
										}

									}else{
										$DetalleConsolidadoIva[$posicionIva] = array();
									}

									array_push( $DetalleConsolidadoIva[$posicionIva], $DetalleAsientoIva);


									if( isset($DetalleConsolidadoIngreso[$posicion])){

										if(!is_array($DetalleConsolidadoIngreso[$posicion])){
											$DetalleConsolidadoIngreso[$posicion] = array();
										}

									}else{
										$DetalleConsolidadoIngreso[$posicion] = array();
									}

									array_push( $DetalleConsolidadoIngreso[$posicion], $DetalleAsientoIngreso);


									if ( $exc_inv == 1 ){
										// VALIDANDO ITEM INVENTARIABLE
										if( $ManejaInvetario == 1 ){
											if( isset($DetalleConsolidadoCostoInventario[$posicionCostoInventario])){

												if(!is_array($DetalleConsolidadoCostoInventario[$posicionCostoInventario])){
													$DetalleConsolidadoCostoInventario[$posicionCostoInventario] = array();
												}

											}else{
												$DetalleConsolidadoCostoInventario[$posicionCostoInventario] = array();
											}

											array_push( $DetalleConsolidadoCostoInventario[$posicionCostoInventario], $DetalleCostoInventario );


											if( isset($DetalleConsolidadoCostoCosto[$posicionCostoCosto])){

												if(!is_array($DetalleConsolidadoCostoCosto[$posicionCostoCosto])){
													$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
												}

											}else{
												$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
											}

											array_push( $DetalleConsolidadoCostoCosto[$posicionCostoCosto], $DetalleCostoCosto );
										}else{

											if( isset($DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable])){

												if(!is_array($DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable])){
													$DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable] = array();
												}

											}else{
												$DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable] = array();
											}

											array_push( $DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable], $DetalleItemNoInventariable );

										}
									}else{
										if( $ManejaInvetario == 1 ){
											if( isset($DetalleConsolidadoCostoCosto[$posicionCostoCosto])){

												if(!is_array($DetalleConsolidadoCostoCosto[$posicionCostoCosto])){
													$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
												}

											}else{
												$DetalleConsolidadoCostoCosto[$posicionCostoCosto] = array();
											}

											array_push( $DetalleConsolidadoCostoCosto[$posicionCostoCosto], $DetalleCostoCosto );
										}else{

											if( isset($DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable])){

												if(!is_array($DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable])){
													$DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable] = array();
												}

											}else{
												$DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable] = array();
											}

											array_push( $DetalleConsolidadoItemNoInventariable[$posicionItemNoInventariable], $DetalleItemNoInventariable );
										}
									}
          }

					//Procedimiento para llenar Impuestos
					$granTotalIva = 0;
					$MontoSysCR = 0;
					$MontoSysDB = 0;
					foreach ($DetalleConsolidadoIva as $key => $posicion) {
							$granTotalIva = 0;
							$granTotalIvaOriginal = 0;
							$CodigoImp = 0;
							$LineTotal = 0;
							$Vat = 0;

							foreach ($posicion as $key => $value) {
								$granTotalIva = $granTotalIva + $value->nc1_vatsum;
								$Vat = $value->nc1_vat;
								// if( $Vat > 0 ){
								$LineTotal = ($LineTotal + $value->nc1_linetotal);
								// }
								$CodigoImp = $value->codimp;
							}

							$granTotalIvaOriginal = $granTotalIva;

							if(trim($Data['cnc_currency']) != $MONEDALOCAL ){
								$granTotalIva = ($granTotalIva * $TasaDocLoc);
								$LineTotal = ($LineTotal * $TasaDocLoc);
							}

							if(trim($Data['cnc_currency']) != $MONEDASYS ){

								$MontoSysCR = ($granTotalIva / $TasaLocSys);
								}else{
								$MontoSysCR = $granTotalIvaOriginal;
							}

							$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
							$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

							$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

									':ac1_trans_id' => $resInsertAsiento,
									':ac1_account' => $value->nc1_cuentaIva,
									':ac1_debit' => 0,
									':ac1_credit' => round($granTotalIva, $DECI_MALES),
									':ac1_debit_sys' => 0,
									':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
									':ac1_currex' => 0,
									':ac1_doc_date' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
									':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
									':ac1_debit_import' => 0,
									':ac1_credit_import' => 0,
									':ac1_debit_importsys' => 0,
									':ac1_credit_importsys' => 0,
									':ac1_font_key' => $resInsert,
									':ac1_font_line' => 1,
									':ac1_font_type' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
									':ac1_accountvs' => 1,
									':ac1_doctype' => 18,
									':ac1_ref1' => "",
									':ac1_ref2' => "",
									':ac1_ref3' => "",
									':ac1_prc_code' => NULL,
									':ac1_uncode' => NULL,
									':ac1_prj_code' => NULL,
									':ac1_rescon_date' => NULL,
									':ac1_recon_total' => 0,
									':ac1_made_user' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
									':ac1_accperiod' => 1,
									':ac1_close' => 0,
									':ac1_cord' => 0,
									':ac1_ven_debit' => 0,
									':ac1_ven_credit' => 0,
									':ac1_fiscal_acct' => 0,
									':ac1_taxid' => $CodigoImp,
									':ac1_isrti' => $Vat,
									':ac1_basert' => 0,
									':ac1_mmcode' => 0,
									':ac1_legal_num' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
									':ac1_codref' => 1,
									':ac1_line'   => 	$AC1LINE,
									':ac1_base_tax' => round($LineTotal, $DECI_MALES),
									':ac1_codret' => 0
						));

						if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
								// Se verifica que el detalle no de error insertando //
						}else{

								// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la nota credito'
									);

									 $this->response($respuesta);

									 return;
						}
					}

					//FIN Procedimiento para llenar Impuestos

						if($Data['cnc_basetype'] != 13){ // solo si el documento no viene de una entrada
								//Procedimiento para llenar costo inventario
								foreach ($DetalleConsolidadoCostoInventario as $key => $posicion) {
										$grantotalCostoInventario = 0 ;
										$grantotalCostoInventarioOriginal = 0;
										$cuentaInventario = "";
										$cdito = 0;
										$dbito = 0;
										$MontoSysDB = 0;
										$MontoSysCR = 0;
										$sinDatos = 0;

										foreach ($posicion as $key => $value) {

														if( $value->ac1_inventory == 1 || $value->ac1_inventory  == '1' ){
															$sinDatos++;
															$cuentaInventario = $value->ac1_account;
															$grantotalCostoInventario = ($grantotalCostoInventario + $value->nc1_linetotal );
														}
												}



												$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

												if(trim($Data['cnc_currency']) != $MONEDALOCAL ){
														$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
												}

												$cdito = $grantotalCostoInventario;

												if(trim($Data['cnc_currency']) != $MONEDASYS ){
														$MontoSysCR = ($cdito / $TasaLocSys);
												}else{
														$MontoSysCR = $grantotalCostoInventarioOriginal;
												}

											$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
											$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));
											$AC1LINE = $AC1LINE+1;
										$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

												':ac1_trans_id' => $resInsertAsiento,
												':ac1_account' => $cuentaInventario,
												':ac1_debit' => 0,
												':ac1_credit' => round($cdito, $DECI_MALES),
												':ac1_debit_sys' =>0,
												':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
												':ac1_currex' => 0,
												':ac1_doc_date' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
												':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
												':ac1_debit_import' => 0,
												':ac1_credit_import' => 0,
												':ac1_debit_importsys' => 0,
												':ac1_credit_importsys' => 0,
												':ac1_font_key' => $resInsert,
												':ac1_font_line' => 1,
												':ac1_font_type' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
												':ac1_accountvs' => 1,
												':ac1_doctype' => 18,
												':ac1_ref1' => "",
												':ac1_ref2' => "",
												':ac1_ref3' => "",
												':ac1_prc_code' => NULL,
												':ac1_uncode' => NULL,
												':ac1_prj_code' => NULL,
												':ac1_rescon_date' => NULL,
												':ac1_recon_total' => 0,
												':ac1_made_user' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
												':ac1_accperiod' => 1,
												':ac1_close' => 0,
												':ac1_cord' => 0,
												':ac1_ven_debit' => 0,
												':ac1_ven_credit' => 0,
												':ac1_fiscal_acct' => 0,
												':ac1_taxid' => 0,
												':ac1_isrti' => 0,
												':ac1_basert' => 0,
												':ac1_mmcode' => 0,
												':ac1_legal_num' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
												':ac1_codref' => 1,
												':ac1_line'   => 	$AC1LINE,
												':ac1_base_tax' => 0,
												':ac1_codret' => 0
									));

									if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

											// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data'	  => $resDetalleAsiento,
													'mensaje'	=> 'No se pudo registrar la nota credito'
												);

												 $this->response($respuesta);

												 return;
									}

								}

						}	//FIN Procedimiento para llenar costo inventario
						// PROCEDIMIENTO PARA LLENAR ASIENTO DELVOLUCION COMPRA ARTICULO NO INVENTARIABLE


						// ASIENTO PARA GASTO CUANDO NO MUEVE INVENTARIO
						if(!isset($DetalleConsolidadoCostoInventario[0])){
							foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
									$grantotalCostoInventario = 0 ;
									$grantotalCostoInventarioOriginal = 0;
									$cuentaInventario = "";
									$cdito = 0;
									$dbito = 0;
									$MontoSysDB = 0;
									$MontoSysCR = 0;
									$sinDatos = 0;

									foreach ($posicion as $key => $value) {

													if( $value->ac1_inventory == 1 || $value->ac1_inventory  == '1' ){
														$sinDatos++;
														$cuentaInventario = $value->ac1_account;
														$grantotalCostoInventario = ($grantotalCostoInventario + $value->nc1_linetotal );
													}
											}

											$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

											if(trim($Data['cnc_currency']) != $MONEDALOCAL ){
													$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
											}

											$cdito = $grantotalCostoInventario;

											if(trim($Data['cnc_currency']) != $MONEDASYS ){
													$MontoSysCR = ($cdito / $TasaLocSys);
											}else{
													$MontoSysCR = $grantotalCostoInventarioOriginal;
											}

										$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
										$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));
										$AC1LINE = $AC1LINE+1;
									$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

											':ac1_trans_id' => $resInsertAsiento,
											':ac1_account' => $cuentaInventario,
											':ac1_debit' => 0,
											':ac1_credit' => round($cdito, $DECI_MALES),
											':ac1_debit_sys' =>0,
											':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
											':ac1_currex' => 0,
											':ac1_doc_date' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
											':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
											':ac1_debit_import' => 0,
											':ac1_credit_import' => 0,
											':ac1_debit_importsys' => 0,
											':ac1_credit_importsys' => 0,
											':ac1_font_key' => $resInsert,
											':ac1_font_line' => 1,
											':ac1_font_type' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
											':ac1_accountvs' => 1,
											':ac1_doctype' => 18,
											':ac1_ref1' => "",
											':ac1_ref2' => "",
											':ac1_ref3' => "",
											':ac1_prc_code' => NULL,
											':ac1_uncode' => NULL,
											':ac1_prj_code' => NULL,
											':ac1_rescon_date' => NULL,
											':ac1_recon_total' => 0,
											':ac1_made_user' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
											':ac1_accperiod' => 1,
											':ac1_close' => 0,
											':ac1_cord' => 0,
											':ac1_ven_debit' => 0,
											':ac1_ven_credit' => 0,
											':ac1_fiscal_acct' => 0,
											':ac1_taxid' => 1,
											':ac1_isrti' => 0,
											':ac1_basert' => 0,
											':ac1_mmcode' => 0,
											':ac1_legal_num' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
											':ac1_codref' => 1,
											':ac1_line'   => 	$AC1LINE,
											':ac1_base_tax' => 0,
											':ac1_codret' => 0
								));

								if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data'	  => $resDetalleAsiento,
												'mensaje'	=> 'No se pudo registrar la nota credito'
											);

											 $this->response($respuesta);

											 return;
								}

							}
						}
						//

						foreach ($DetalleConsolidadoItemNoInventariable as $key => $posicion) {
								$grantotalItemNoInventariable = 0 ;
								$grantotalItemNoInventariableOriginal = 0;
								$CuentaItemNoInventariable = "";
								$cdito = 0;
								$dbito = 0;
								$MontoSysDB = 0;
								$MontoSysCR = 0;
								$sinDatos = 0;

								foreach ($posicion as $key => $value) {


													$sinDatos++;
													$CuentaItemNoInventariable = $value->ac1_account;
													$grantotalItemNoInventariable = ($grantotalItemNoInventariable + $value->nc1_linetotal );

								}



										$grantotalItemNoInventariableOriginal = $grantotalItemNoInventariable;

										if(trim($Data['cnc_currency']) != $MONEDALOCAL ){
												$grantotalItemNoInventariable = ($grantotalItemNoInventariable * $TasaDocLoc);
										}

										$cdito = $grantotalItemNoInventariable;

										if(trim($Data['cnc_currency']) != $MONEDASYS ){
												$MontoSysCR = ($cdito / $TasaLocSys);
										}else{
												$MontoSysCR = $grantotalItemNoInventariableOriginal;
										}

									$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
									$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));
									$AC1LINE = $AC1LINE+1;
								$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

										':ac1_trans_id' => $resInsertAsiento,
										':ac1_account' => $CuentaItemNoInventariable,
										':ac1_debit' => 0,
										':ac1_credit' => round($cdito, $DECI_MALES),
										':ac1_debit_sys' => 0,
										':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
										':ac1_currex' => 0,
										':ac1_doc_date' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
										':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
										':ac1_debit_import' => 0,
										':ac1_credit_import' => 0,
										':ac1_debit_importsys' => 0,
										':ac1_credit_importsys' => 0,
										':ac1_font_key' => $resInsert,
										':ac1_font_line' => 1,
										':ac1_font_type' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
										':ac1_accountvs' => 1,
										':ac1_doctype' => 18,
										':ac1_ref1' => "",
										':ac1_ref2' => "",
										':ac1_ref3' => "",
										':ac1_prc_code' => NULL,
										':ac1_uncode' => NULL,
										':ac1_prj_code' => NULL,
										':ac1_rescon_date' => NULL,
										':ac1_recon_total' => 0,
										':ac1_made_user' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
										':ac1_accperiod' => 1,
										':ac1_close' => 0,
										':ac1_cord' => 0,
										':ac1_ven_debit' => 0,
										':ac1_ven_credit' => 0,
										':ac1_fiscal_acct' => 0,
										':ac1_taxid' => 0,
										':ac1_isrti' => 0,
										':ac1_basert' => 0,
										':ac1_mmcode' => 0,
										':ac1_legal_num' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
										':ac1_codref' => 1,
										':ac1_line'   => 	$AC1LINE,
										':ac1_base_tax' => 0,
										':ac1_codret' => 0
							));

							if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
									// Se verifica que el detalle no de error insertando //
							}else{

									// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
									// se retorna el error y se detiene la ejecucion del codigo restante.
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'	  => $resDetalleAsiento,
											'mensaje'	=> 'No se pudo registrar la nota credito'
										);

										 $this->response($respuesta);

										 return;
							}

						}
					//FIN PROCEDIMIENTO PARA LLENAR ASIENTO DELVOLUCION COMPRA ARTICULO NO INVENTARIABLE


					if($Data['cnc_basetype'] == 13){

							 //CUENTA PUENTE DE INVENTARIO

								$sqlcuentainventario = "SELECT coalesce(pge_bridge_inv_purch, 0) as pge_bridge_inv_purch, coalesce(pge_bridge_purch_int, 0) as pge_bridge_purch_int FROM pgem";
								$rescuentainventario = $this->pedeo->queryTable($sqlcuentainventario, array());

								if ( isset($rescuentainventario[0]) && $rescuentainventario[0]['pge_bridge_inv_purch'] != 0 ){

								}else{
									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'	  => $resDetalleAsiento,
										'mensaje'	=> 'No se pudo registrar la factura de compras'
									);

									 $this->response($respuesta);

									 return;
								}

								foreach ($DetalleConsolidadoCostoCosto as $key => $posicion) {
										$grantotalCostoInventario = 0 ;
										$grantotalCostoInventarioOriginal = 0;
										$dbito = 0;
										$cdito = 0;
										$sinDatos = 0;
										$MontoSysDB = 0;
										$MontoSysCR = 0;
										$cuentaInventario = "";
										foreach ($posicion as $key => $value) {

											if( $value->ac1_inventory == 1 || $value->ac1_inventory  == '1' ){

												$sinDatos++;

												if ( isset( $Data['cnc_api'] ) && $Data['cnc_api'] == 1 ){
													$cuentaInventario = $rescuentainventario[0]['pge_bridge_purch_int'];
												}else{
													$cuentaInventario = $rescuentainventario[0]['pge_bridge_inv_purch'];
												}

												$grantotalCostoInventario = ($grantotalCostoInventario + $value->nc1_linetotal);

											}

										}


											if ($sinDatos > 0 ){

												$grantotalCostoInventarioOriginal = $grantotalCostoInventario;

												if(trim($Data['cnc_currency']) != $MONEDALOCAL ){

														$grantotalCostoInventario = ($grantotalCostoInventario * $TasaDocLoc);
												}

												$cdito = $grantotalCostoInventario;

												if(trim($Data['cnc_currency']) != $MONEDASYS ){
														$MontoSysCR = ($cdito / $TasaLocSys);
												}else{
														$MontoSysCR = $grantotalCostoInventarioOriginal;
												}
												$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
												$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));
												$AC1LINE = $AC1LINE+1;
												$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

												':ac1_trans_id' => $resInsertAsiento,
												':ac1_account' => $cuentaInventario,
												':ac1_debit' => 0,
												':ac1_credit' => round($cdito, $DECI_MALES),
												':ac1_debit_sys' => 0,
												':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
												':ac1_currex' => 0,
												':ac1_doc_date' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
												':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
												':ac1_debit_import' => 0,
												':ac1_credit_import' => 0,
												':ac1_debit_importsys' => 0,
												':ac1_credit_importsys' => 0,
												':ac1_font_key' => $resInsert,
												':ac1_font_line' => 1,
												':ac1_font_type' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
												':ac1_accountvs' => 1,
												':ac1_doctype' => 18,
												':ac1_ref1' => "",
												':ac1_ref2' => "",
												':ac1_ref3' => "",
												':ac1_prc_code' => $value->ac1_prc_code,
												':ac1_uncode' => $value->ac1_uncode,
												':ac1_prj_code' => $value->ac1_prj_code,
												':ac1_rescon_date' => NULL,
												':ac1_recon_total' => 0,
												':ac1_made_user' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
												':ac1_accperiod' => 1,
												':ac1_close' => 0,
												':ac1_cord' => 0,
												':ac1_ven_debit' => 0,
												':ac1_ven_credit' => 0,
												':ac1_fiscal_acct' => 0,
												':ac1_taxid' => 0,
												':ac1_isrti' => 0,
												':ac1_basert' => 0,
												':ac1_mmcode' => 0,
												':ac1_legal_num' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
												':ac1_codref' => 1,
												':ac1_line'   => 	$AC1LINE,
												':ac1_base_tax' => 0,
												':ac1_codret' => 0
												));

												if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
												// Se verifica que el detalle no de error insertando //
												}else{

													// si falla algun insert del detalle de la nota debito de compras se devuelven los cambios realizados por la transaccion,
													// se retorna el error y se detiene la ejecucion del codigo restante.
													$this->pedeo->trans_rollback();

													$respuesta = array(
														'error'   => true,
														'data'	  => $resDetalleAsiento,
														'mensaje'	=> 'No se pudo registrar la nota debito de compras 7'
													);

													 $this->response($respuesta);

													 return;
												}

											}//aaa

						 		}
							}

				 //FIN Procedimiento para llenar costo costo

				//Procedimiento para llenar cuentas por cobrar

					$sqlcuentaCxP = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
													 JOIN dmgs  AS f2
													 ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
													 WHERE  f1.dms_card_code = :dms_card_code
													 AND f1.dms_card_type = '2'";//2 para proveedores";


					$rescuentaCxP = $this->pedeo->queryTable($sqlcuentaCxP, array(":dms_card_code" => $Data['cnc_cardcode']));

					if(isset( $rescuentaCxP[0] )){

									$debitoo = 0;
									$creditoo = 0;
									$MontoSysDB = 0;
									$MontoSysCR = 0;
									$TotalDoc = $Data['cnc_doctotal'];
									$TotalDoc2 = 0;
									$TotalDocOri = $TotalDoc;

									$cuentaCxP = $rescuentaCxP[0]['mgs_acct'];

									if(trim($Data['cnc_currency']) != $MONEDALOCAL ){
										$TotalDoc = ($TotalDoc * $TasaDocLoc);
									}


									if(trim($Data['cnc_currency']) != $MONEDASYS ){
											$MontoSysDB = ($TotalDoc / $TasaLocSys);
									}else{
											$MontoSysDB = $TotalDocOri;
									}

								$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
								$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));

								$AC1LINE = $AC1LINE+1;


								if($Data['cnc_basetype'] == 15){
									$TotalDoc2 = $TotalDoc;
								}else{
									$TotalDoc2 = 0;
								}


								$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

										':ac1_trans_id' => $resInsertAsiento,
										':ac1_account' => $cuentaCxP,
										':ac1_debit' => round($TotalDoc, $DECI_MALES),
										':ac1_credit' => 0,
										':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
										':ac1_credit_sys' => 0,
										':ac1_currex' => 0,
										':ac1_doc_date' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
										':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
										':ac1_debit_import' => 0,
										':ac1_credit_import' => 0,
										':ac1_debit_importsys' => 0,
										':ac1_credit_importsys' => 0,
										':ac1_font_key' => $resInsert,
										':ac1_font_line' => 1,
										':ac1_font_type' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
										':ac1_accountvs' => 1,
										':ac1_doctype' => 18,
										':ac1_ref1' => "",
										':ac1_ref2' => "",
										':ac1_ref3' => "",
										':ac1_prc_code' => NULL,
										':ac1_uncode' => NULL,
										':ac1_prj_code' => NULL,
										':ac1_rescon_date' => NULL,
										':ac1_recon_total' => 0,
										':ac1_made_user' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
										':ac1_accperiod' => 1,
										':ac1_close' => 0,
										':ac1_cord' => 0,
										':ac1_ven_debit' => round($TotalDoc, $DECI_MALES),
										':ac1_ven_credit' => round($TotalDoc2, $DECI_MALES),
										':ac1_fiscal_acct' => 0,
										':ac1_taxid' => 0,
										':ac1_isrti' => 0,
										':ac1_basert' => 0,
										':ac1_mmcode' => 0,
										':ac1_legal_num' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
										':ac1_codref' => 1,
										':ac1_line'   => 	$AC1LINE,
										':ac1_base_tax' => 0,
										':ac1_codret' => 0
							));

							if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
									// Se verifica que el detalle no de error insertando //
							}else{

									// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
									// se retorna el error y se detiene la ejecucion del codigo restante.
										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'	  => $resDetalleAsiento,
											'mensaje'	=> 'No se pudo registrar la nota credito'
										);

										 $this->response($respuesta);

										 return;
							}

					}else{

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'	  => $rescuentaCxP,
									'mensaje'	=> 'No se pudo registrar la nota credito, el tercero no tiene cuenta asociada'
								);

								 $this->response($respuesta);

								 return;
					}
					//FIN Procedimiento para llenar cuentas por cobrar


					//PROCEDIMIENTO PARA LLENAR ASIENTO DE RETENCIONES
					foreach ($DetalleConsolidadoRetencion as $key => $posicion) {
									$totalRetencion = 0;
									$totalRetencionOriginal = 0;
									$dbito = 0;
									$cdito = 0;
									$MontoSysDB = 0;
									$MontoSysCR = 0;
									$cuenta = '';
									$Basert = 0;
									$Profitrt = 0;
									$CodRet = 0;
									foreach ($posicion as $key => $value) {

										$sqlcuentaretencion = "SELECT mrt_acctcode FROM dmrt WHERE mrt_id = :mrt_id";
										$rescuentaretencion = $this->pedeo->queryTable($sqlcuentaretencion, array(
														'mrt_id' => $value->crt_typert
										));

										if( isset($rescuentaretencion[0])){

											$cuenta = $rescuentaretencion[0]['mrt_acctcode'];
											$totalRetencion = $totalRetencion + $value->crt_basert;
											$Profitrt =  $value->crt_profitrt;
											$CodRet = $value->crt_codret;

										}else{

											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data'	  => $rescuentaretencion,
												'mensaje'	=> 'No se pudo registrar la factura de compras, no se encontro la cuenta para la retencion '.$value->crt_typert
											);

											 $this->response($respuesta);

											 return;
										}

									}

									$Basert = $totalRetencion;
									$totalRetencionOriginal = $totalRetencion;

									if(trim($Data['cnc_currency']) != $MONEDALOCAL ){
										$totalRetencion = ($totalRetencion * $TasaDocLoc);
										$Basert = $totalRetencion;
									}


									if(trim($Data['cnc_currency']) != $MONEDASYS ){
											$MontoSysDB = ($totalRetencion / $TasaLocSys);
									}else{
											$MontoSysDB = 	$totalRetencionOriginal;
									}

									$SumaCreditosSYS = ($SumaCreditosSYS + round($MontoSysCR, $DECI_MALES));
									$SumaDebitosSYS  = ($SumaDebitosSYS + round($MontoSysDB, $DECI_MALES));
									$AC1LINE = $AC1LINE+1;
									$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

											':ac1_trans_id' => $resInsertAsiento,
											':ac1_account' => $cuenta,
											':ac1_debit' => round($totalRetencion, $DECI_MALES),
											':ac1_credit' => 0,
											':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
											':ac1_credit_sys' => 0,
											':ac1_currex' => 0,
											':ac1_doc_date' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
											':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
											':ac1_debit_import' => 0,
											':ac1_credit_import' => 0,
											':ac1_debit_importsys' => 0,
											':ac1_credit_importsys' => 0,
											':ac1_font_key' => $resInsert,
											':ac1_font_line' => 1,
											':ac1_font_type' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
											':ac1_accountvs' => 1,
											':ac1_doctype' => 18,
											':ac1_ref1' => "",
											':ac1_ref2' => "",
											':ac1_ref3' => "",
											':ac1_prc_code' => NULL,
											':ac1_uncode' => NULL,
											':ac1_prj_code' => NULL,
											':ac1_rescon_date' => NULL,
											':ac1_recon_total' => 0,
											':ac1_made_user' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
											':ac1_accperiod' => 1,
											':ac1_close' => 0,
											':ac1_cord' => 0,
											':ac1_ven_debit' => 0,
											':ac1_ven_credit' => 0,
											':ac1_fiscal_acct' => 0,
											':ac1_taxid' => 0,
											':ac1_isrti' => $Profitrt,
											':ac1_basert' => round($Basert, $DECI_MALES),
											':ac1_mmcode' => 0,
											':ac1_legal_num' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
											':ac1_codref' => 1,
											':ac1_line'   => 	$AC1LINE,
											':ac1_base_tax' => 0,
											':ac1_codret' => $CodRet
								));



								if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
										// Se verifica que el detalle no de error insertando //
								}else{

										// si falla algun insert del detalle de la factura de compras se devuelven los cambios realizados por la transaccion,
										// se retorna el error y se detiene la ejecucion del codigo restante.
											$this->pedeo->trans_rollback();

											$respuesta = array(
												'error'   => true,
												'data'	  => $resDetalleAsiento,
												'mensaje'	=> 'No se pudo registrar la factura de compras'
											);

											 $this->response($respuesta);

											 return;
								}
							}


					//FIN DE OPERACIONES VITALES


					// SE VALIDA DIFERENCIA POR DECIMALES
					// Y SE AGREGA UN ASIENTO DE DIFERENCIA EN DECIMALES
					// SEGUN SEA EL CASO
					$credito = 0;
					$debito = 0;

					if($SumaCreditosSYS > $SumaDebitosSYS || $SumaDebitosSYS > $SumaCreditosSYS){

								$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem";
								$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array());

								if(isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])){

											if( $SumaCreditosSYS > $SumaDebitosSYS ){ // DIFERENCIA EN CREDITO EL VALOR SE COLOCA EN DEBITO

														$debito = ($SumaCreditosSYS - $SumaDebitosSYS);

											}else{ // DIFERENCIA EN DEBITO EL VALOR SE COLOCA EN CREDITO

														$credito = ($SumaDebitosSYS - $SumaCreditosSYS);
											}


											if(round($debito+$credito, 2) > 0){
														$AC1LINE = $AC1LINE+1;
														$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

																':ac1_trans_id' => $resInsertAsiento,
																':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
																':ac1_debit' => 0,
																':ac1_credit' => 0,
																':ac1_debit_sys' => round($debito, $DECI_MALES),
																':ac1_credit_sys' => round($credito, $DECI_MALES),
																':ac1_currex' => 0,
																':ac1_doc_date' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
																':ac1_doc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
																':ac1_debit_import' => 0,
																':ac1_credit_import' => 0,
																':ac1_debit_importsys' => 0,
																':ac1_credit_importsys' => 0,
																':ac1_font_key' => $resInsert,
																':ac1_font_line' => 1,
																':ac1_font_type' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
																':ac1_accountvs' => 1,
																':ac1_doctype' => 18,
																':ac1_ref1' => "",
																':ac1_ref2' => "",
																':ac1_ref3' => "",
																':ac1_prc_code' => NULL,
																':ac1_uncode' => NULL,
																':ac1_prj_code' => NULL,
																':ac1_rescon_date' => NULL,
																':ac1_recon_total' => 0,
																':ac1_made_user' => isset($Data['cnc_createby'])?$Data['cnc_createby']:NULL,
																':ac1_accperiod' => 1,
																':ac1_close' => 0,
																':ac1_cord' => 0,
																':ac1_ven_debit' => 0,
																':ac1_ven_credit' => 0,
																':ac1_fiscal_acct' => 0,
																':ac1_taxid' => 0,
																':ac1_isrti' => 0,
																':ac1_basert' => 0,
																':ac1_mmcode' => 0,
																':ac1_legal_num' => isset($Data['cnc_cardcode'])?$Data['cnc_cardcode']:NULL,
																':ac1_codref' => 1,
																':ac1_line'   => 	$AC1LINE,
																':ac1_base_tax' => 0,
																':ac1_codret' => 0
													));

													if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
															// Se verifica que el detalle no de error insertando //
													}else{

															// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
															// se retorna el error y se detiene la ejecucion del codigo restante.
																$this->pedeo->trans_rollback();

																$respuesta = array(
																	'error'   => true,
																	'data'	  => $resDetalleAsiento,
																	'mensaje'	=> 'No se pudo registrar la factura de ventas'
																);

																 $this->response($respuesta);

																 return;
													}

											}

								}else{

										$this->pedeo->trans_rollback();

										$respuesta = array(
											'error'   => true,
											'data'	  => $resCuentaDiferenciaDecimal,
											'mensaje'	=> 'No se encontro la cuenta para adicionar la diferencia en decimales'
										);

										 $this->response($respuesta);

										 return;
								}
					}


					// VALIDANDO ESTADOS DE DOCUMENTOS

					//SE VALIDA QUE EL PAY TO DAY DE LA FACTURA
					if($Data['cnc_basetype'] == 15) { // SOLO CUANDO ES UNA FACTURA


						$valorAppl =  $Data['cnc_doctotal'];

						if(trim($Data['cnc_currency']) != $MONEDALOCAL ){
								$valorAppl = $Data['cnc_doctotal'] * $TasaDocLoc;
						}


						$sqlUpdateFactPay = "UPDATE  dcfc  SET cfc_paytoday = COALESCE(cfc_paytoday,0)+:cfc_paytoday WHERE cfc_docentry = :cfc_docentry and cfc_doctype = :cfc_doctype";

						$resUpdateFactPay = $this->pedeo->updateRow($sqlUpdateFactPay,array(

							':cfc_paytoday' => $valorAppl,
							':cfc_docentry' => $Data['cnc_baseentry'],
							':cfc_doctype'  => $Data['cnc_basetype']

						));

						if(is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1){

						}else{
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resUpdateFactPay,
								'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '.$Data['cnc_baseentry']
							);

							 $this->response($respuesta);

							 return;
						}
					}

					// SE ACTUALIZA VALOR EN EL ASIENTO CONTABLE
					// GENERADO EN LA FACTURA
					if($Data['cnc_basetype'] == 15) { // SOLO CUANDO ES UNA FACTURA

						$valorAppl =  $Data['cnc_doctotal'];

						if(trim($Data['cnc_currency']) != $MONEDALOCAL ){
								$valorAppl = $Data['cnc_doctotal'] * $TasaDocLoc;
						}


						$sqlcuentaCxP = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
														 JOIN dmgs  AS f2
														 ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
														 WHERE  f1.dms_card_code = :dms_card_code
														 AND f1.dms_card_type = '2'";//2 para proveedores";


						$rescuentaCxP = $this->pedeo->queryTable($sqlcuentaCxP, array(":dms_card_code" => $Data['cnc_cardcode']));

						if(!isset( $rescuentaCxP[0] )){
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $rescuentaCxP,
								'mensaje'	=> 'No se pudo registrar la factura de ventas, el socio de negocio no tiene cuenta asociada'
							);

							 $this->response($respuesta);

							 return;
						}

						$cuentaCxP = $rescuentaCxP[0]['mgs_acct'];

						$slqUpdateVenDebit = "UPDATE mac1
																	SET ac1_ven_debit = ac1_ven_debit + :ac1_ven_debit
																	WHERE ac1_legal_num = :ac1_legal_num
																	AND ac1_font_key = :ac1_font_key
																	AND ac1_font_type = :ac1_font_type
																	AND ac1_account = :ac1_account";
						$resUpdateVenDebit = $this->pedeo->updateRow($slqUpdateVenDebit, array(

							':ac1_ven_debit'  => $valorAppl,
							':ac1_legal_num'  => $Data['cnc_cardcode'],
							':ac1_font_key'   => $Data['cnc_baseentry'],
							':ac1_font_type'  => $Data['cnc_basetype'],
							':ac1_account'    => $cuentaCxP

						));

						if(is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1){

						}else{
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resUpdateVenDebit,
								'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura '. $Data['cnc_baseentry']
							);

							 $this->response($respuesta);

							 return;
						}
					}
					//
					//SE CIERRA LA NOTA CREADA
					if($Data['cnc_basetype'] == 15) { // SOLO CUANDO ES UNA FACTURA

						$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

						$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


											':bed_docentry' => $resInsert,
											':bed_doctype' =>  $Data['cnc_doctype'],
											':bed_status' => 3, //ESTADO CERRADO
											':bed_createby' => $Data['cnc_createby'],
											':bed_date' => date('Y-m-d'),
											':bed_baseentry' => $Data['cnc_baseentry'],
											':bed_basetype' => $Data['cnc_basetype']
						));

						if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

						}else{
							 $this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resInsertEstado,
									'mensaje'	=> 'No se pudo registrar la nota debito'
								);


								$this->response($respuesta);

								return;
						}
					}

					// SE VALIDA SALDOS PARA CERRAR FACTURA
					if($Data['cnc_basetype'] == 15) {

									$resEstado = $this->generic->validateBalanceAndClose($Data['cnc_baseentry'],$Data['cnc_basetype'],'dcfc','cfc');

									if(isset($resEstado['error']) && $resEstado['error'] === true){
												$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																						VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

												$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


																	':bed_docentry' => $Data['cnc_baseentry'],
																	':bed_doctype' => $Data['cnc_basetype'],
																	':bed_status' => 3, //ESTADO CERRADO
																	':bed_createby' => $Data['cnc_createby'],
																	':bed_date' => date('Y-m-d'),
																	':bed_baseentry' => $resInsert,
																	':bed_basetype' => $Data['cnc_doctype']
												));


												if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

												}else{

														 $this->pedeo->trans_rollback();

															$respuesta = array(
																'error'   => true,
																'data' => $resInsertEstado,
																'mensaje'	=> 'No se pudo registrar el pago'
															);


															$this->response($respuesta);

															return;
												}

									}

					 }

					// FIN VALIDACION DE ESTADOS

					//SE VALIDA LA CONTABILIDAD CREADA
					 $validateCont = $this->generic->validateAccountingAccent($resInsertAsiento);


					 if( isset($validateCont['error']) && $validateCont['error'] == false ){

					 }else{

							 $this->pedeo->trans_rollback();

							 $respuesta = array(
								 'error'   => true,
								 'data' 	 => '',
								 'mensaje' => $validateCont['mensaje']
							 );

							 $this->response($respuesta);

							 return;
					 }
					//


					// Si todo sale bien despues de insertar el detalle  de nota credito
					// se confirma la trasaccion  para que los cambios apliquen permanentemente
					// en la base de datos y se confirma la operacion exitosa.
					$this->pedeo->trans_commit();

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' =>'Nota credito registrada con exito'
          );


        }else{
					// Se devuelven los cambios realizados en la transaccion
					// si occurre un error  y se muestra devuelve el error.
							$this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsert,
                'mensaje'	=> 'No se pudo registrar la nota credito'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR  de nota credito
  public function updatePurchaseNc_post(){

      $Data = $this->post();

			if(!isset($Data['cnc_docentry']) OR !isset($Data['cnc_docnum']) OR
				 !isset($Data['cnc_docdate']) OR !isset($Data['cnc_duedate']) OR
				 !isset($Data['cnc_duedev']) OR !isset($Data['cnc_pricelist']) OR
				 !isset($Data['cnc_cardcode']) OR !isset($Data['cnc_cardname']) OR
				 !isset($Data['cnc_currency']) OR !isset($Data['cnc_contacid']) OR
				 !isset($Data['cnc_slpcode']) OR !isset($Data['cnc_empid']) OR
				 !isset($Data['cnc_comment']) OR !isset($Data['cnc_doctotal']) OR
				 !isset($Data['cnc_baseamnt']) OR !isset($Data['cnc_taxtotal']) OR
				 !isset($Data['cnc_discprofit']) OR !isset($Data['cnc_discount']) OR
				 !isset($Data['cnc_createat']) OR !isset($Data['cnc_baseentry']) OR
				 !isset($Data['cnc_basetype']) OR !isset($Data['cnc_doctype']) OR
				 !isset($Data['cnc_idadd']) OR !isset($Data['cnc_adress']) OR
				 !isset($Data['cnc_paytype']) OR !isset($Data['cnc_attch']) OR
				 !isset($Data['detail'])){

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
            'mensaje' =>'No se encontro el detalle  de nota credito'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      $sqlUpdate = "UPDATE dcnc	SET cnc_docdate=:cnc_docdate,cnc_duedate=:cnc_duedate, cnc_duedev=:cnc_duedev, cnc_pricelist=:cnc_pricelist, cnc_cardcode=:cnc_cardcode,
			  						cnc_cardname=:cnc_cardname, cnc_currency=:cnc_currency, cnc_contacid=:cnc_contacid, cnc_slpcode=:cnc_slpcode,
										cnc_empid=:cnc_empid, cnc_comment=:cnc_comment, cnc_doctotal=:cnc_doctotal, cnc_baseamnt=:cnc_baseamnt,
										cnc_taxtotal=:cnc_taxtotal, cnc_discprofit=:cnc_discprofit, cnc_discount=:cnc_discount, cnc_createat=:cnc_createat,
										cnc_baseentry=:cnc_baseentry, cnc_basetype=:cnc_basetype, cnc_doctype=:cnc_doctype, cnc_idadd=:cnc_idadd,
										cnc_adress=:cnc_adress, cnc_paytype=:cnc_paytype, cnc_attch=:cnc_attch WHERE cnc_docentry=:cnc_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
							':cnc_docnum' => is_numeric($Data['cnc_docnum'])?$Data['cnc_docnum']:0,
							':cnc_docdate' => $this->validateDate($Data['cnc_docdate'])?$Data['cnc_docdate']:NULL,
							':cnc_duedate' => $this->validateDate($Data['cnc_duedate'])?$Data['cnc_duedate']:NULL,
							':cnc_duedev' => $this->validateDate($Data['cnc_duedev'])?$Data['cnc_duedev']:NULL,
							':cnc_pricelist' => is_numeric($Data['cnc_pricelist'])?$Data['cnc_pricelist']:0,
							':cnc_cardcode' => isset($Data['cnc_pricelist'])?$Data['cnc_pricelist']:NULL,
							':cnc_cardname' => isset($Data['cnc_cardname'])?$Data['cnc_cardname']:NULL,
							':cnc_currency' => isset($Data['cnc_currency'])?$Data['cnc_currency']:NULL,
							':cnc_contacid' => isset($Data['cnc_contacid'])?$Data['cnc_contacid']:NULL,
							':cnc_slpcode' => is_numeric($Data['cnc_slpcode'])?$Data['cnc_slpcode']:0,
							':cnc_empid' => is_numeric($Data['cnc_empid'])?$Data['cnc_empid']:0,
							':cnc_comment' => isset($Data['cnc_comment'])?$Data['cnc_comment']:NULL,
							':cnc_doctotal' => is_numeric($Data['cnc_doctotal'])?$Data['cnc_doctotal']:0,
							':cnc_baseamnt' => is_numeric($Data['cnc_baseamnt'])?$Data['cnc_baseamnt']:0,
							':cnc_taxtotal' => is_numeric($Data['cnc_taxtotal'])?$Data['cnc_taxtotal']:0,
							':cnc_discprofit' => is_numeric($Data['cnc_discprofit'])?$Data['cnc_discprofit']:0,
							':cnc_discount' => is_numeric($Data['cnc_discount'])?$Data['cnc_discount']:0,
							':cnc_createat' => $this->validateDate($Data['cnc_createat'])?$Data['cnc_createat']:NULL,
							':cnc_baseentry' => is_numeric($Data['cnc_baseentry'])?$Data['cnc_baseentry']:0,
							':cnc_basetype' => is_numeric($Data['cnc_basetype'])?$Data['cnc_basetype']:0,
							':cnc_doctype' => is_numeric($Data['cnc_doctype'])?$Data['cnc_doctype']:0,
							':cnc_idadd' => isset($Data['cnc_idadd'])?$Data['cnc_idadd']:NULL,
							':cnc_adress' => isset($Data['cnc_adress'])?$Data['cnc_adress']:NULL,
							':cnc_paytype' => is_numeric($Data['cnc_paytype'])?$Data['cnc_paytype']:0,
							':cnc_attch' => $this->getUrl(count(trim(($Data['cnc_attch']))) > 0 ? $Data['cnc_attch']:NULL, $resMainFolder[0]['main_folder']),
							':cnc_docentry' => $Data['cnc_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

						$this->pedeo->queryTable("DELETE FROM cnc1 WHERE nc1_docentry=:nc1_docentry", array(':nc1_docentry' => $Data['cnc_docentry']));

						foreach ($ContenidoDetalle as $key => $detail) {

									$sqlInsertDetail = "INSERT INTO cnc1(nc1_docentry, nc1_itemcode, nc1_itemname, nc1_quantity, nc1_uom, nc1_whscode,
																			nc1_price, nc1_vat, nc1_vatsum, nc1_discount, nc1_linetotal, nc1_costcode, nc1_ubusiness, nc1_project,
																			nc1_acctcode, nc1_basetype, nc1_doctype, nc1_avprice, nc1_inventory, nc1_acciva)VALUES(:nc1_docentry, :nc1_itemcode, :nc1_itemname, :nc1_quantity,
																			:nc1_uom, :nc1_whscode,:nc1_price, :nc1_vat, :nc1_vatsum, :nc1_discount, :nc1_linetotal, :nc1_costcode, :nc1_ubusiness, :nc1_project,
																			:nc1_acctcode, :nc1_basetype, :nc1_doctype, :nc1_avprice, :nc1_inventory, :nc1_acciva)";

									$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
											':nc1_docentry' => $Data['cnc_docentry'],
											':nc1_itemcode' => isset($detail['nc1_itemcode'])?$detail['nc1_itemcode']:NULL,
											':nc1_itemname' => isset($detail['nc1_itemname'])?$detail['nc1_itemname']:NULL,
											':nc1_quantity' => is_numeric($detail['nc1_quantity'])?$detail['nc1_quantity']:0,
											':nc1_uom' => isset($detail['nc1_uom'])?$detail['nc1_uom']:NULL,
											':nc1_whscode' => isset($detail['nc1_whscode'])?$detail['nc1_whscode']:NULL,
											':nc1_price' => is_numeric($detail['nc1_price'])?$detail['nc1_price']:0,
											':nc1_vat' => is_numeric($detail['nc1_vat'])?$detail['nc1_vat']:0,
											':nc1_vatsum' => is_numeric($detail['nc1_vatsum'])?$detail['nc1_vatsum']:0,
											':nc1_discount' => is_numeric($detail['nc1_discount'])?$detail['nc1_discount']:0,
											':nc1_linetotal' => is_numeric($detail['nc1_linetotal'])?$detail['nc1_linetotal']:0,
											':nc1_costcode' => isset($detail['nc1_costcode'])?$detail['nc1_costcode']:NULL,
											':nc1_ubusiness' => isset($detail['nc1_ubusiness'])?$detail['nc1_ubusiness']:NULL,
											':nc1_project' => isset($detail['nc1_project'])?$detail['nc1_project']:NULL,
											':nc1_acctcode' => is_numeric($detail['nc1_acctcode'])?$detail['nc1_acctcode']:0,
											':nc1_basetype' => is_numeric($detail['nc1_basetype'])?$detail['nc1_basetype']:0,
											':nc1_doctype' => is_numeric($detail['nc1_doctype'])?$detail['nc1_doctype']:0,
											':nc1_avprice' => is_numeric($detail['nc1_avprice'])?$detail['nc1_avprice']:0,
											':nc1_inventory' => is_numeric($detail['nc1_inventory'])?$detail['nc1_inventory']:NULL,
											':nc1_acciva' => is_numeric($detail['nc1_cuentaIva'])?$detail['nc1_cuentaIva']:0
									));

									if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
											// Se verifica que el detalle no de error insertando //
									}else{

											// si falla algun insert del detalle  de nota credito se devuelven los cambios realizados por la transaccion,
											// se retorna el error y se detiene la ejecucion del codigo restante.
												$this->pedeo->trans_rollback();

												$respuesta = array(
													'error'   => true,
													'data' => $resInsert,
													'mensaje'	=> 'No se pudo registrar la nota credito'
												);

												 $this->response($respuesta);

												 return;
									}
						}


						$this->pedeo->trans_commit();

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Nota credito actualizada con exito'
            );


      }else{

						$this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la nota credito'
            );

      }

       $this->response($respuesta);
  }


  //OBTENER  de nota credito
  public function getPurchaseNc_get(){

				$DECI_MALES =  $this->generic->getDecimals();

        $sqlSelect = self::getColumn('dcnc','cnc','','',$DECI_MALES);


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


	//OBTENER  de nota credito POR ID
	public function getPurchaseNcById_get(){

				$Data = $this->get();

				if(!isset($Data['cnc_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dcnc WHERE cnc_docentry =:cnc_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cnc_docentry" => $Data['cnc_docentry']));

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


	//OBTENER  de nota credito DETALLE POR ID
	public function getPurchaseNcDetail_get(){

				$Data = $this->get();

				if(!isset($Data['nc1_docentry'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT cnc1.*,dmar.dma_series_code
											 FROM cnc1
											 INNER JOIN dmar
											 ON cnc1.nc1_itemcode = dmar.dma_item_code
											 WHERE nc1_docentry =:nc1_docentry";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":nc1_docentry" => $Data['nc1_docentry']));

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




	//OBTENER  DE VENTA POR ID SOCIO DE NEGOCIO
	public function getPurchaseNcBySN_get(){

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

				$sqlSelect = " SELECT * FROM dcnc WHERE cnc_cardcode =:cnc_cardcode";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cnc_cardcode" => $Data['dms_card_code']));

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







	private function getUrl($data, $caperta){
	  $url = "";

	  if ($data == NULL){

		return $url;

	  }

		$ruta = '/var/www/html/'.$caperta.'/assets/img/anexos/';

	  $milliseconds = round(microtime(true) * 1000);


	  $nombreArchivo = $milliseconds.".pdf";

	  touch($ruta.$nombreArchivo);

	  $file = fopen($ruta.$nombreArchivo,"wb");

	  if(!empty($data)){

			fwrite($file, base64_decode($data));

			fclose($file);

			$url = "assets/img/anexos/".$nombreArchivo;
	  }

	  return $url;
	}

	private function buscarPosicion($llave, $inArray){
			$res = 0;
	  	for($i = 0; $i < count($inArray); $i++) {
					if($inArray[$i] == "$llave"){
								$res =  $i;
								break;
					}
			}

			return $res;
	}

	private function validateDate($fecha){
			if(strlen($fecha) == 10 OR strlen($fecha) > 10){
				return true;
			}else{
				return false;
			}
	}
}
