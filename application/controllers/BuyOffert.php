<?php
// OFERTA DE COMPRAS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class BuyOffert extends REST_Controller
{

	private $pdo;

	public function __construct()
	{

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
		$this->load->library('pedeo', [$this->pdo]);
		$this->load->library('generic');
	}

	public function getOffert_get()
	{

		$Data = $this->get();

		if (!isset($Data['business']) or !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$DECI_MALES =  $this->generic->getDecimals();

		$sqlSelect = self::getColumn('dcoc', 'coc', '', '', $DECI_MALES, $Data['business'], $Data['branch']);

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

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

	public function getOffertDetail_get()
	{
		$Data = $this->get();

		if (!isset($Data['oc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM coc1 WHERE oc1_docentry =:oc1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":oc1_docentry" => $Data['oc1_docentry']));

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

	public function getOffertDetailCopy_get()
	{
		$Data = $this->get();

		if (!isset($Data['oc1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		//VALIDAR CANTIDADES DOCUMENTOS DE DESTINO DIRECTOS
		$CantOrder = 0;
		$CantEntry = 0;
		$CantInv = 0;
		//CONSULTA PARA OBTENER CANTIDADES DE PEDIDO DE PROVEEDORES
		$sqlOrder = "SELECT 
						 cpo1.po1_itemcode,
						 sum(cpo1.po1_quantity) as po1_quantity
					FROM dcoc 
					INNER JOIN coc1 ON dcoc.coc_docentry = coc1.oc1_docentry 
					LEFT JOIN dcpo ON dcoc.coc_docentry = dcpo.cpo_baseentry AND dcoc.coc_doctype = dcpo.cpo_basetype
					LEFT JOIN cpo1 ON dcpo.cpo_docentry = cpo1.po1_docentry AND coc1.oc1_itemcode = cpo1.po1_itemcode
					WHERE dcoc.coc_docentry = :coc_docentry
					GROUP BY cpo1.po1_itemcode";
		$resSqlOrder = $this->pedeo->queryTable($sqlOrder,array(':coc_docentry' => $Data['oc1_docentry']));
		$CantOrder = isset($resSqlOrder['po1_quantity']) ? $resSqlOrder['po1_quantity'] : $CantOrder;
		//CONSULTA PARA OBTENER CANTIDADES DE ENTRADA  DE PROVEEDORES
		$sqlEntry = "SELECT 
						cec1.ec1_itemcode,
						sum(cec1.ec1_quantity) as ec1_quantity
					FROM dcoc 
					INNER JOIN coc1 ON dcoc.coc_docentry = coc1.oc1_docentry 
					LEFT JOIN dcec ON dcoc.coc_docentry = dcec.cec_baseentry AND dcoc.cec_doctype = dcec.cec_basetype
					LEFT JOIN cec1 ON dcec.cec_docentry = cec1.ec1_docentry AND coc1.oc1_itemcode = cec1.ec1_itemcode
					WHERE dcoc.coc_docentry = :coc_docentry
					GROUP BY cec1.ec1_itemcode";
		$resSqlEntry = $this->pedeo->queryTable($sqlEntry,array(':coc_docentry' => $Data['oc1_docentry']));
		$CantEntry = isset($resSqlEntry['ec1_quantity']) ? $resSqlEntry['ec1_quantity'] : $CantEntry;
		//CONSULTA PARA OBTENER CANTIDADES DE FACTURA DE PROVEEDORES
		$sqlInv = "SELECT 
						cfc1.fc1_itemcode,
						sum(cfc1.fc1_quantity) as fc1_quantity
					FROM dcoc 
					INNER JOIN coc1 ON dcoc.coc_docentry = coc1.oc1_docentry 
					LEFT JOIN dcfc ON dcoc.coc_docentry = dcfc.cfc_baseentry AND dcoc.cec_doctype = dcfc.cfc_basetype
					LEFT JOIN cfc1 ON dcfc.cfc_docentry = cfc1.fc1_docentry AND coc1.oc1_itemcode = cfc1.fc1_itemcode
					WHERE dcoc.coc_docentry = :coc_docentry
					GROUP BY cfc1.fc1_itemcode";
		$resSqlInv = $this->pedeo->queryTable($sqlInv,array(':coc_docentry' => $Data['oc1_docentry']));
		$CantInv = isset($resSqlInv['fc1_quantity']) ? $resSqlInv['fc1_quantity'] : $CantInv;

		//CONSULTA GLOBAL PARA EL COPIAR
		$sqlSelect = "SELECT
					t1.oc1_acciva,
					t1.oc1_acctcode,
					t1.oc1_avprice,
					t1.oc1_basetype,
					t1.oc1_costcode,
					t1.oc1_discount,
					t1.oc1_docentry,
					t1.oc1_doctype,
					t1.oc1_id,
					t1.oc1_inventory,
					t1.oc1_itemcode,
					t1.oc1_itemname,
					t1.oc1_linenum,
					t1.oc1_linetotal line_total_real,
					(t1.oc1_quantity - ($CantOrder + $CantEntry + $CantInv)) * t1.oc1_price oc1_linetotal,
					t1.oc1_price,
					t1.oc1_project,
					t1.oc1_quantity - ($CantOrder + $CantEntry + $CantInv) as oc1_quantity,
					t1.oc1_ubusiness,
					t1.oc1_uom,
					t1.oc1_vat,
					t1.oc1_vatsum,
					t1.oc1_quantity cant_real,
					(((t1.oc1_quantity - ($CantOrder + $CantEntry + $CantInv)) * t1.oc1_price) * t1.oc1_vat) / 100 oc1_vatsum,
					t1.oc1_whscode,
					dmar.dma_series_code
					from dcoc t0
					left join coc1 t1 on t0.coc_docentry = t1.oc1_docentry
					INNER JOIN dmar ON oc1_itemcode = dmar.dma_item_code
					WHERE t1.oc1_docentry = :oc1_docentry
					GROUP BY
					t1.oc1_acciva,
					t1.oc1_acctcode,
					t1.oc1_avprice,
					t1.oc1_basetype,
					t1.oc1_costcode,
					t1.oc1_discount,
					t1.oc1_docentry,
					t1.oc1_doctype,
					t1.oc1_id,
					t1.oc1_inventory,
					t1.oc1_itemcode,
					t1.oc1_itemname,
					t1.oc1_linenum,
					t1.oc1_linetotal,
					t1.oc1_price,
					t1.oc1_project,
					t1.oc1_ubusiness,
					t1.oc1_uom,
					t1.oc1_vat,
					t1.oc1_vatsum,
					t1.oc1_whscode,
					t1.oc1_quantity,
					dmar.dma_series_code
					HAVING (t1.oc1_quantity - ($CantOrder + $CantEntry + $CantInv)) <> 0";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":oc1_docentry" => $Data['oc1_docentry']));

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

	


	public function getOffertDetailBySN_get()
	{
		$Data = $this->get();

		if (!isset($Data['dms_card_code']) OR !isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT * from dcoc where coc_cardcode = :dms_card_code and business = :business and branch = :branch";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":dms_card_code" => $Data['dms_card_code'], ":business" => $Data['business'], ":branch" => $Data['branch']));
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
	//CREAR NUEVA SOLICUTUD DE OFERTA DE COMPRA
	public function createBuyOffert_post()
	{

		$DECI_MALES =  $this->generic->getDecimals();
		$Data = $this->post();

		$DetalleAsientoIngreso = new stdClass(); // Cada objeto de las linea del detalle consolidado
		$DetalleAsientoIva = new stdClass();
		$DetalleCostoInventario = new stdClass();
		$DetalleCostoCosto = new stdClass();
		$DetalleConsolidadoIngreso = []; // Array Final con los datos del asiento solo ingreso
		$DetalleConsolidadoCostoInventario = [];
		$DetalleConsolidadoCostoCosto = [];
		$DetalleConsolidadoIva = []; // Array Final con los datos del asiento segun el iva
		$inArrayIngreso = array(); // Array para mantener el indice de las llaves para ingreso
		$inArrayIva = array(); // Array para mantener el indice de las llaves para iva
		$inArrayCostoInventario = array();
		$inArrayCostoCosto = array();
		$llave = ""; // la comnbinacion entre la cuenta contable,proyecto, unidad de negocio y centro de costo
		$llaveIva = ""; //segun tipo de iva
		$llaveCostoInventario = "";
		$llaveCostoCosto = "";
		$posicion = 0; // contiene la posicion con que se creara en el array DetalleConsolidado
		$posicionIva = 0;
		$posicionCostoInventario = 0;
		$posicionCostoCosto = 0;
		$codigoCuenta = ""; //para saber la naturaleza
		$grantotalCostoInventario = 0;
		$DocNumVerificado = 0;
		$CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA


		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref)";


		if (!isset($Data['detail'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$ContenidoDetalle = json_decode($Data['detail'], true);


		if (!is_array($ContenidoDetalle)) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro el detalle de la oferta de compras'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//BUSCANDO LA NUMERACION DEL DOCUMENTO
		$sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

		$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['coc_series']));

		if (isset($resNumeracion[0])) {

			$numeroActual = $resNumeracion[0]['pgs_nextnum'];
			$numeroFinal  = $resNumeracion[0]['pgs_last_num'];
			$numeroSiguiente = ($numeroActual + 1);

			if ($numeroSiguiente <= $numeroFinal) {

				$DocNumVerificado = $numeroSiguiente;
			} else {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'La serie de la numeración esta llena'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la serie de numeración para el documento'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		//Obtener Carpeta Principal del Proyecto
		$sqlMainFolder = " SELECT * FROM params";
		$resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());

		if (!isset($resMainFolder[0])) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la caperta principal del proyecto'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		// FIN PROCESO PARA OBTENRE LA CARPETA Principal

		// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
		// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
		$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
		$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

		if (isset($resMonedaLoc[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda local.'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

		// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
		$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
		$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

		if (isset($resMonedaSys[0])) {
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la moneda de sistema.'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}


		$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

		//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
		// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
		$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['coc_currency'], ':tsa_date' => $Data['coc_docdate']));

		if (isset($resBusTasa[0])) {
		} else {

			if (trim($Data['coc_currency']) != $MONEDALOCAL) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encrontro la tasa de cambio para la moneda: ' . $Data['coc_currency'] . ' en la actual fecha del documento: ' . $Data['coc_docdate'] . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}


		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['coc_docdate']));

		if (isset($resBusTasa2[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $Data['coc_docdate']
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
		$TasaLocSys = $resBusTasa2[0]['tsa_value'];

		// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO




		// SE VERIFICA SI EL DOCUMENTO A CREAR NO  VIENE DE UN PROCESO DE APROBACION Y NO ESTE APROBADO

		$sqlVerificarAprobacion = "SELECT * FROM tbed WHERE bed_docentry =:bed_docentry AND bed_doctype =:bed_doctype AND bed_status =:bed_status";
		$resVerificarAprobacion = $this->pedeo->queryTable($sqlVerificarAprobacion, array(

			':bed_docentry' => $Data['coc_baseentry'],
			':bed_doctype'  => $Data['coc_basetype'],
			':bed_status'   => 4 // 4 APROBADO SEGUN MODELO DE APROBACION
		));



		if (!isset($resVerificarAprobacion[0])) {

			// BUSCANDO MODELO PARA EL DOCUMENTO

			$Emmpleado = $Data['coc_createby'];

			$sqlModeloDocumentoEmpleado = "SELECT * FROM tmau	WHERE mau_doctype = :mau_doctype AND mau_emp = :mau_emp AND mau_status = :mau_status";
			$resModeloDocumentoEmpleado = $this->pedeo->queryTable($sqlModeloDocumentoEmpleado, array(
				':mau_doctype' => $Data['coc_doctype'],
				':mau_emp' => 1,
				':mau_status' => 1
			));

			if (isset($resModeloDocumentoEmpleado[0])) {
				// SE BUSCA EL USUARIO DEL EMPLEADO DE VENTAS
				$sqlUsuarioEmpleadoVentas = "SELECT pgu_code_user FROM pgus WHERE pgu_id_vendor = :pgu_id_vendor";
				$resUsuarioEmpleadoVentas = $this->pedeo->queryTable($sqlUsuarioEmpleadoVentas, array(
					':pgu_id_vendor' => $Data['coc_slpcode']
				));

				if (isset($resUsuarioEmpleadoVentas[0])) {
					$Emmpleado = $resUsuarioEmpleadoVentas[0]['pgu_code_user'];
				} else {
					$Emmpleado = $Data['coc_createby'];
				}
			}



			$sqlDocModelo = "SELECT mau_docentry as modelo, mau_doctype as doctype, mau_quantity as cantidad,
																au1_doctotal as doctotal,au1_doctotal2 as doctotal2, au1_c1 as condicion
																FROM tmau
																INNER JOIN mau1
																ON mau_docentry =  au1_docentry
																INNER JOIN taus
																ON mau_docentry  = aus_id_model
																INNER JOIN pgus
																ON aus_id_usuario = pgu_id_usuario
																WHERE mau_doctype = :mau_doctype
																AND pgu_code_user = :pgu_code_user
																AND mau_status = :mau_status
																AND aus_status = :aus_status";

			$resDocModelo = $this->pedeo->queryTable($sqlDocModelo, array(

				':mau_doctype'   => $Data['coc_doctype'],
				':pgu_code_user' => $Emmpleado,
				':mau_status' 	 => 1,
				':aus_status' 	 => 1

			));

			if (isset($resDocModelo[0])) {

				foreach ($resDocModelo as $key => $value) {

					//VERIFICAR MODELO DE APROBACION
					$condicion = $value['condicion'];
					$valorDocTotal1 = $value['doctotal'];
					$valorDocTotal2 = $value['doctotal2'];
					$TotalDocumento = $Data['coc_doctotal'];
					$doctype =  $value['doctype'];
					$modelo = $value['modelo'];

					if (trim($Data['coc_currency']) != $MONEDASYS) {

						if (trim($Data['coc_currency']) != $MONEDALOCAL) {

							$TotalDocumento = round(($TotalDocumento * $TasaDocLoc), $DECI_MALES);
							$TotalDocumento = round(($TotalDocumento / $TasaLocSys), $DECI_MALES);
						} else {

							$TotalDocumento = round(($TotalDocumento / $TasaLocSys), $DECI_MALES);
						}
					}

					if ($condicion == ">") {

						$sq = " SELECT mau_quantity,mau_approvers,mau_docentry
																				FROM tmau
																				INNER JOIN  mau1
																				on mau_docentry =  au1_docentry
																				AND :au1_doctotal > au1_doctotal
																				AND mau_doctype = :mau_doctype
																				AND mau_docentry = :mau_docentry";

						$ressq = $this->pedeo->queryTable($sq, array(

							':au1_doctotal' => $TotalDocumento,
							':mau_doctype'  => $doctype,
							':mau_docentry' => $modelo
						));

						if (isset($ressq[0])) {
							$this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'], 'coc', 'oc1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);
						}
					} else if ($condicion == "BETWEEN") {

						$sq = " SELECT mau_quantity,mau_approvers,mau_docentry
																				FROM tmau
																				INNER JOIN  mau1
																				on mau_docentry =  au1_docentry
																				AND cast(:doctotal as numeric) between au1_doctotal AND au1_doctotal2
																				AND mau_doctype = :mau_doctype
																				AND mau_docentry = :mau_docentry";

						$ressq = $this->pedeo->queryTable($sq, array(

							':doctotal' 	  => $TotalDocumento,
							':mau_doctype'  => $doctype,
							':mau_docentry' => $modelo
						));

						if (isset($ressq[0])) {
							$this->setAprobacion($Data, $ContenidoDetalle, $resMainFolder[0]['main_folder'], 'coc', 'oc1', $ressq[0]['mau_quantity'], count(explode(',', $ressq[0]['mau_approvers'])), $ressq[0]['mau_docentry'], $Data['business'], $Data['branch']);
						}
					}
					//VERIFICAR MODELO DE PROBACION
				}
			}
		}
		// FIN PROESO DE VERIFICAR SI EL DOCUMENTO A CREAR NO  VIENE DE UN PROCESO DE APROBACION Y NO ESTE APROBADO

		$sqlInsert = "INSERT INTO dcoc(coc_series, coc_docnum, coc_docdate, coc_duedate, coc_duedev, coc_pricelist, coc_cardcode,
					coc_cardname, coc_currency, coc_contacid, coc_slpcode, coc_empid, coc_comment, coc_doctotal, coc_baseamnt, coc_taxtotal,
					coc_discprofit, coc_discount, coc_createat, coc_baseentry, coc_basetype, coc_doctype, coc_idadd, coc_adress, coc_paytype,
					coc_createby, business, branch)VALUES(:coc_series, :coc_docnum, :coc_docdate, :coc_duedate, :coc_duedev, :coc_pricelist, :coc_cardcode, :coc_cardname,
					:coc_currency, :coc_contacid, :coc_slpcode, :coc_empid, :coc_comment, :coc_doctotal, :coc_baseamnt, :coc_taxtotal, :coc_discprofit, :coc_discount,
					:coc_createat, :coc_baseentry, :coc_basetype, :coc_doctype, :coc_idadd, :coc_adress, :coc_paytype,:coc_createby, :business, :branch)";


		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':coc_docnum' => $DocNumVerificado,
			':coc_series' => is_numeric($Data['coc_series']) ? $Data['coc_series'] : 0,
			':coc_docdate' => $this->validateDate($Data['coc_docdate']) ? $Data['coc_docdate'] : NULL,
			':coc_duedate' => $this->validateDate($Data['coc_duedate']) ? $Data['coc_duedate'] : NULL,
			':coc_duedev' => $this->validateDate($Data['coc_duedev']) ? $Data['coc_duedev'] : NULL,
			':coc_pricelist' => is_numeric($Data['coc_pricelist']) ? $Data['coc_pricelist'] : 0,
			':coc_cardcode' => isset($Data['coc_cardcode']) ? $Data['coc_cardcode'] : NULL,
			':coc_cardname' => isset($Data['coc_cardname']) ? $Data['coc_cardname'] : NULL,
			':coc_currency' => isset($Data['coc_currency']) ? $Data['coc_currency'] : NULL,
			':coc_contacid' => isset($Data['coc_contacid']) ? $Data['coc_contacid'] : NULL,
			':coc_slpcode' => is_numeric($Data['coc_slpcode']) ? $Data['coc_slpcode'] : 0,
			':coc_empid' => is_numeric($Data['coc_empid']) ? $Data['coc_empid'] : 0,
			':coc_comment' => isset($Data['coc_comment']) ? $Data['coc_comment'] : NULL,
			':coc_doctotal' => is_numeric($Data['coc_doctotal']) ? $Data['coc_doctotal'] : 0,
			':coc_baseamnt' => is_numeric($Data['coc_baseamnt']) ? $Data['coc_baseamnt'] : 0,
			':coc_taxtotal' => is_numeric($Data['coc_taxtotal']) ? $Data['coc_taxtotal'] : 0,
			':coc_discprofit' => is_numeric($Data['coc_discprofit']) ? $Data['coc_discprofit'] : 0,
			':coc_discount' => is_numeric($Data['coc_discount']) ? $Data['coc_discount'] : 0,
			':coc_createat' => $this->validateDate($Data['coc_createat']) ? $Data['coc_createat'] : NULL,
			':coc_baseentry' => is_numeric($Data['coc_baseentry']) ? $Data['coc_baseentry'] : 0,
			':coc_basetype' => is_numeric($Data['coc_basetype']) ? $Data['coc_basetype'] : 0,
			':coc_doctype' => is_numeric($Data['coc_doctype']) ? $Data['coc_doctype'] : 0,
			':coc_idadd' => isset($Data['coc_idadd']) ? $Data['coc_idadd'] : NULL,
			':coc_adress' => isset($Data['coc_adress']) ? $Data['coc_adress'] : NULL,
			':coc_paytype' => is_numeric($Data['coc_paytype']) ? $Data['coc_paytype'] : 0,
			':coc_createby' => isset($Data['coc_createby']) ? $Data['coc_createby'] : NULL,
			':business' => $Data['business'],
			':branch' => $Data['branch']
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
										 WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['coc_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la solicitud de compras',
					'proceso' => 'Actualización de serie'
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
				':bed_doctype' => $Data['coc_doctype'],
				':bed_status' => 1, //ESTADO ABIERTO
				':bed_createby' => $Data['coc_createby'],
				':bed_date' => date('Y-m-d'),
				':bed_baseentry' => NULL,
				':bed_basetype' => NULL
			));


			if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' => $resInsertEstado,
					'mensaje'	=> 'No se pudo registrar la solicitud de compras',
					'proceso' => 'Insertar estado documento'
				);


				$this->response($respuesta);

				return;
			}


			// SE CIERRA EL DOCUMENTO PRELIMINAR SI VIENE DE UN MODELO DE APROBACION
			// SI EL DOCTYPE = 21
			if ($Data['coc_basetype'] == 21) {

				$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

				$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


					':bed_docentry' => $Data['coc_baseentry'],
					':bed_doctype' => $Data['coc_basetype'],
					':bed_status' => 3, //ESTADO CERRADO
					':bed_createby' => $Data['coc_createby'],
					':bed_date' => date('Y-m-d'),
					':bed_baseentry' => $resInsert,
					':bed_basetype' => $Data['coc_doctype']
				));


				if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertEstado,
						'mensaje'	=> 'No se pudo registrar la orden de compras',
						'proceso' => 'Insertar estado documento'
					);


					$this->response($respuesta);

					return;
				}
			}
			//FIN SE CIERRA EL DOCUMENTO PRELIMINAR SI VIENE DE UN MODELO DE APROBACION

			//FIN PROCESO GUARDAR ESTADO DEL DOCUMENTO


			//SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
			if (isset($Data['coc_baseentry']) && is_numeric($Data['coc_baseentry']) && isset($Data['coc_basetype']) && is_numeric($Data['coc_basetype'])) {

				if ($Data['coc_basetype']  == 21) {

					$sqlDOrigen = "SELECT *
														 FROM dpap
														 WHERE pap_doctype = :pap_doctype AND pap_docentry = :pap_docentry";

					$resDOrigen = $this->pedeo->queryTable($sqlDOrigen, array(
						':pap_doctype'  => $Data['coc_basetype'],
						':pap_docentry' => $Data['coc_baseentry']
					));


					if (isset($resDOrigen[0])) {

						$sqlDInicio = "SELECT *
															 FROM tbmd
															 WHERE bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";

						$resDInicio = $this->pedeo->queryTable($sqlDInicio, array(
							':bmd_doctype'  => $resDOrigen[0]['pap_basetype'],
							':bmd_docentry' => $resDOrigen[0]['pap_baseentry']

						));

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
																bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
																VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
																:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";
						$bmd_tdi = 0;
						$bmd_ndi = 0;
						$bmd_doctypeo = 0;
						$bmd_docentryo = 0;

						if (!isset($resDInicio[0]['bmd_tdi'])) {

							$bmd_tdi = $Data['coc_basetype'];
							$bmd_ndi = $Data['coc_baseentry'];
							$bmd_doctypeo = 0;
							$bmd_docentryo = 0;
						} else {

							$bmd_doctypeo  = $resDOrigen[0]['pap_basetype']; //ORIGEN
							$bmd_docentryo = $resDOrigen[0]['pap_baseentry'];  //ORIGEN
							$bmd_tdi = $resDInicio[0]['bmd_tdi']; // DOCUMENTO INICIAL
							$bmd_ndi = $resDInicio[0]['bmd_ndi']; // DOCUMENTO INICIAL

						}


						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

							':bmd_doctype' => is_numeric($Data['coc_doctype']) ? $Data['coc_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['coc_createat']) ? $Data['coc_createat'] : NULL,
							':bmd_doctypeo' => $bmd_doctypeo, //ORIGEN
							':bmd_docentryo' => $bmd_docentryo,  //ORIGEN
							':bmd_tdi' => $bmd_tdi, // DOCUMENTO INICIAL
							':bmd_ndi' => $bmd_ndi, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['coc_doctotal']) ? $Data['coc_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['coc_cardcode']) ? $Data['coc_cardcode'] : NULL,
							':bmd_cardtype' => 2
						));

						if (is_numeric($resInsertMD) && $resInsertMD > 0) {
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInsertMD,
								'mensaje'	=> 'No se pudo registrar el movimiento del documento'
							);


							$this->response($respuesta);

							return;
						}
					} else {
						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
																bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
																VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
																:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

							':bmd_doctype' => is_numeric($Data['coc_doctype']) ? $Data['coc_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['coc_createat']) ? $Data['coc_createat'] : NULL,
							':bmd_doctypeo' => 0, //ORIGEN
							':bmd_docentryo' => 0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['coc_doctype']) ? $Data['coc_doctype'] : 0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['coc_doctotal']) ? $Data['coc_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['coc_cardcode']) ? $Data['coc_cardcode'] : NULL,
							':bmd_cardtype' => 2
						));

						if (is_numeric($resInsertMD) && $resInsertMD > 0) {
						} else {

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
				} else {

					$sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
					$resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
						':bmd_doctype' => $Data['coc_basetype'],
						':bmd_docentry' => $Data['coc_baseentry']
					));


					if (isset($resDocInicio[0])) {

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
																bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
																VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
																:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

							':bmd_doctype' => is_numeric($Data['coc_doctype']) ? $Data['coc_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['coc_createat']) ? $Data['coc_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['coc_basetype']) ? $Data['coc_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['coc_baseentry']) ? $Data['coc_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
							':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['coc_doctotal']) ? $Data['coc_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['coc_cardcode']) ? $Data['coc_cardcode'] : NULL,
							':bmd_cardtype' => 2
						));

						if (is_numeric($resInsertMD) && $resInsertMD > 0) {
						} else {

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInsertEstado,
								'mensaje'	=> 'No se pudo registrar el movimiento del documento'
							);


							$this->response($respuesta);

							return;
						}
					} else {

						$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
																bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
																VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
																:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

						$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

							':bmd_doctype' => is_numeric($Data['coc_doctype']) ? $Data['coc_doctype'] : 0,
							':bmd_docentry' => $resInsert,
							':bmd_createat' => $this->validateDate($Data['coc_createat']) ? $Data['coc_createat'] : NULL,
							':bmd_doctypeo' => is_numeric($Data['coc_basetype']) ? $Data['coc_basetype'] : 0, //ORIGEN
							':bmd_docentryo' => is_numeric($Data['coc_baseentry']) ? $Data['coc_baseentry'] : 0,  //ORIGEN
							':bmd_tdi' => is_numeric($Data['coc_doctype']) ? $Data['coc_doctype'] : 0, // DOCUMENTO INICIAL
							':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
							':bmd_docnum' => $DocNumVerificado,
							':bmd_doctotal' => is_numeric($Data['coc_doctotal']) ? $Data['coc_doctotal'] : 0,
							':bmd_cardcode' => isset($Data['coc_cardcode']) ? $Data['coc_cardcode'] : NULL,
							':bmd_cardtype' => 2
						));

						if (is_numeric($resInsertMD) && $resInsertMD > 0) {
						} else {

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
				}
			} else {

				$sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

				$resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

					':bmd_doctype' => is_numeric($Data['coc_doctype']) ? $Data['coc_doctype'] : 0,
					':bmd_docentry' => $resInsert,
					':bmd_createat' => $this->validateDate($Data['coc_createat']) ? $Data['coc_createat'] : NULL,
					':bmd_doctypeo' => is_numeric($Data['coc_basetype']) ? $Data['coc_basetype'] : 0, //ORIGEN
					':bmd_docentryo' => is_numeric($Data['coc_baseentry']) ? $Data['coc_baseentry'] : 0,  //ORIGEN
					':bmd_tdi' => is_numeric($Data['coc_doctype']) ? $Data['coc_doctype'] : 0, // DOCUMENTO INICIAL
					':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
					':bmd_docnum' => $DocNumVerificado,
					':bmd_doctotal' => is_numeric($Data['coc_doctotal']) ? $Data['coc_doctotal'] : 0,
					':bmd_cardcode' => isset($Data['coc_cardcode']) ? $Data['coc_cardcode'] : NULL,
					':bmd_cardtype' => 2
				));

				if (is_numeric($resInsertMD) && $resInsertMD > 0) {
				} else {

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


				$CANTUOMPURCHASE = $this->generic->getUomPurchase($detail['oc1_itemcode']);

				if ($CANTUOMPURCHASE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['oc1_itemcode'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['oc1_itemcode']
					);

					$this->response($respuesta);

					return;
				}


				$sqlInsertDetail = "INSERT INTO coc1(oc1_docentry, oc1_itemcode, oc1_itemname, oc1_quantity, oc1_uom, oc1_whscode,
																		oc1_price, oc1_vat, oc1_vatsum, oc1_discount, oc1_linetotal, oc1_costcode, oc1_ubusiness, oc1_project,
																		oc1_acctcode, oc1_basetype, oc1_doctype, oc1_avprice, oc1_inventory, oc1_acciva, oc1_codimp)VALUES(:oc1_docentry, :oc1_itemcode, :oc1_itemname, :oc1_quantity,
																		:oc1_uom, :oc1_whscode,:oc1_price, :oc1_vat, :oc1_vatsum, :oc1_discount, :oc1_linetotal, :oc1_costcode, :oc1_ubusiness, :oc1_project,
																		:oc1_acctcode, :oc1_basetype, :oc1_doctype, :oc1_avprice, :oc1_inventory, :oc1_acciva, :oc1_codimp)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':oc1_docentry' => $resInsert,
					':oc1_itemcode' => isset($detail['oc1_itemcode']) ? $detail['oc1_itemcode'] : NULL,
					':oc1_itemname' => isset($detail['oc1_itemname']) ? $detail['oc1_itemname'] : NULL,
					':oc1_quantity' => is_numeric($detail['oc1_quantity']) ? $detail['oc1_quantity'] : 0,
					':oc1_uom' => isset($detail['oc1_uom']) ? $detail['oc1_uom'] : NULL,
					':oc1_whscode' => isset($detail['oc1_whscode']) ? $detail['oc1_whscode'] : NULL,
					':oc1_price' => is_numeric($detail['oc1_price']) ? $detail['oc1_price'] : 0,
					':oc1_vat' => is_numeric($detail['oc1_vat']) ? $detail['oc1_vat'] : 0,
					':oc1_vatsum' => is_numeric($detail['oc1_vatsum']) ? $detail['oc1_vatsum'] : 0,
					':oc1_discount' => is_numeric($detail['oc1_discount']) ? $detail['oc1_discount'] : 0,
					':oc1_linetotal' => is_numeric($detail['oc1_linetotal']) ? $detail['oc1_linetotal'] : 0,
					':oc1_costcode' => isset($detail['oc1_costcode']) ? $detail['oc1_costcode'] : NULL,
					':oc1_ubusiness' => isset($detail['oc1_ubusiness']) ? $detail['oc1_ubusiness'] : NULL,
					':oc1_project' => isset($detail['oc1_project']) ? $detail['oc1_project'] : NULL,
					':oc1_acctcode' => is_numeric($detail['oc1_acctcode']) ? $detail['oc1_acctcode'] : 0,
					':oc1_basetype' => is_numeric($detail['oc1_basetype']) ? $detail['oc1_basetype'] : 0,
					':oc1_doctype' => is_numeric($detail['oc1_doctype']) ? $detail['oc1_doctype'] : 0,
					':oc1_avprice' => is_numeric($detail['oc1_avprice']) ? $detail['oc1_avprice'] : 0,
					':oc1_inventory' => is_numeric($detail['oc1_inventory']) ? $detail['oc1_inventory'] : NULL,
					':oc1_acciva'  => is_numeric($detail['oc1_cuentaIva']) ? $detail['oc1_cuentaIva'] : 0,
					':oc1_codimp'  => isset($detail['oc1_codimp']) ? $detail['oc1_codimp'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la solicitud de compras se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la orden de oferta de compras',
						'proceso' => 'Insert detalle solicitud'
					);

					$this->response($respuesta);

					return;
				}
			}

			if ($Data['coc_basetype'] == 10) {


				$sqlEstado1 = "SELECT
											count(t1.sc1_itemcode) item,
											sum(t1.sc1_quantity) cantidad
										from dcsc t0
										inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
										where t0.csc_docentry = :csc_docentry and t0.csc_doctype = :csc_doctype";


				$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
					':csc_docentry' => $Data['coc_baseentry'],
					':csc_doctype' => $Data['coc_basetype']
					// ':vc1_itemcode' => $detail['ov1_itemcode']
				));

				$sqlEstado2 = "SELECT
											coalesce(count(distinct t3.oc1_itemcode),0) item,
											coalesce(sum(t3.oc1_quantity),0) cantidad
										from dcsc t0
										inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
										left join dcoc t2 on t0.csc_docentry = t2.coc_baseentry and t0.csc_doctype = t2.coc_basetype
										left join coc1 t3 on t2.coc_docentry = t3.oc1_docentry and t1.sc1_itemcode = t3.oc1_itemcode
										where t0.csc_docentry = :csc_docentry and t0.csc_doctype = :csc_doctype";


				$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
					':csc_docentry' => $Data['coc_baseentry'],
					':csc_doctype' => $Data['coc_basetype']

				));

				$item_cot = $resEstado1[0]['item'];
				$cantidad_cot = $resEstado1[0]['cantidad'];
				$item_ord = $resEstado2[0]['item'];
				$cantidad_ord = $resEstado2[0]['cantidad'];

				// print_r($item_cot);
				// print_r($item_ord);
				// print_r($cantidad_cot);
				// print_r($cantidad_ord);exit();die();


				if ($item_cot == $item_ord  &&  $cantidad_cot == $cantidad_ord) {

					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																			VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


						':bed_docentry' => $Data['coc_baseentry'],
						':bed_doctype' => $Data['coc_basetype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['coc_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => $resInsert,
						':bed_basetype' => $Data['coc_doctype']
					));


					if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo registrar la oferta de compra'
						);


						$this->response($respuesta);

						return;
					}
				}
			} else if ($Data['coc_basetype'] == 21) {

				//BUSCAR EL DOCENTRY Y DOCTYPE DEL COD ORIGEN
				$sql_aprov = "SELECT
										pap_doctype,
										pap_basetype,
										pap_baseentry
									FROM dpap
									WHERE pap_origen = " . $Data['coc_doctype'] . " and pap_doctype = :pap_doctype
									and pap_docentry = :pap_docentry";

				$result_aprov = $this->pedeo->queryTable($sql_aprov, array(
					':pap_doctype' => $Data['coc_basetype'],
					':pap_docentry' => $Data['coc_baseentry']
				));

				// print_r($result_aprov);exit();die();


				$sqlEstado1 = "SELECT
											count(t1.sc1_itemcode) item,
											sum(t1.sc1_quantity) cantidad
										from dcsc t0
										inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
										where t0.csc_docentry = :csc_docentry and t0.csc_doctype = :csc_doctype";


				$resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
					':csc_docentry' => $result_aprov[0]['pap_baseentry'],
					':csc_doctype' => $result_aprov[0]['pap_basetype']
					// ':vc1_itemcode' => $detail['ov1_itemcode']
				));

				$sqlEstado2 = "SELECT
											coalesce(count(distinct t3.oc1_itemcode),0) item,
											coalesce(sum(t3.oc1_quantity),0) cantidad
										from dcsc t0
										inner join csc1 t1 on t0.csc_docentry = t1.sc1_docentry
										left join dcoc t2 on t0.csc_docentry = " . $result_aprov[0]['pap_baseentry'] . " and t0.csc_doctype = " . $result_aprov[0]['pap_basetype'] . "
										left join coc1 t3 on t2.coc_docentry = t3.oc1_docentry and t1.sc1_itemcode = t3.oc1_itemcode
										where t0.csc_docentry = :csc_docentry and t0.csc_doctype = :csc_doctype";


				$resEstado2 = $this->pedeo->queryTable($sqlEstado2, array(
					':csc_docentry' => $result_aprov[0]['pap_baseentry'],
					':csc_doctype' => $result_aprov[0]['pap_basetype']

				));

				$item_cot = $resEstado1[0]['item'];
				$cantidad_cot = $resEstado1[0]['cantidad'];
				$item_ord = $resEstado2[0]['item'];
				$cantidad_ord = $resEstado2[0]['cantidad'];

				// print_r($item_cot);
				// print_r($item_ord);
				// print_r($cantidad_cot);
				// print_r($cantidad_ord);exit();die();


				if ($item_cot == $item_ord  &&  $cantidad_cot == $cantidad_ord) {

					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																			VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


						':bed_docentry' => $result_aprov[0]['pap_baseentry'],
						':bed_doctype' => $result_aprov[0]['pap_basetype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['coc_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => $resInsert,
						':bed_basetype' => $Data['coc_doctype']
					));


					if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertEstado,
							'mensaje'	=> 'No se pudo registrar la oferta de compra'
						);


						$this->response($respuesta);

						return;
					}
				}
			}
			//FIN DE OPERACIONES VITALES

			// Si todo sale bien despues de insertar el detalle de la solicitud de compras
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Oferta de compras registrada con exito'
			);
		} else {
			// Se devuelven los cambios realizados en la transaccion
			// si occurre un error  y se muestra devuelve el error.
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data' => $resInsert,
				'mensaje'	=> 'No se pudo registrar la solicitud de oferta de compra',
				'proceso' => 'Insert --- 8'
			);
		}

		$this->response($respuesta);
	}

	private function validateDate($fecha)
	{
		if (strlen($fecha) == 10 or strlen($fecha) > 10) {
			return true;
		} else {
			return false;
		}
	}


	private function setAprobacion($Encabezado, $Detalle, $Carpeta, $prefijoe, $prefijod, $Cantidad, $CantidadAP, $Model, $Business, $Branch)
	{

		$sqlInsert = "INSERT INTO dpap(pap_series, pap_docnum, pap_docdate, pap_duedate, pap_duedev, pap_pricelist, pap_cardcode,
									pap_cardname, pap_currency, pap_contacid, pap_slpcode, pap_empid, pap_comment, pap_doctotal, pap_baseamnt, pap_taxtotal,
									pap_discprofit, pap_discount, pap_createat, pap_baseentry, pap_basetype, pap_doctype, pap_idadd, pap_adress, pap_paytype,
									pap_createby,pap_origen,pap_qtyrq,pap_qtyap,pap_model, business, branch)VALUES(:pap_series, :pap_docnum, :pap_docdate, :pap_duedate, :pap_duedev, :pap_pricelist, :pap_cardcode, :pap_cardname,
									:pap_currency, :pap_contacid, :pap_slpcode, :pap_empid, :pap_comment, :pap_doctotal, :pap_baseamnt, :pap_taxtotal, :pap_discprofit, :pap_discount,
									:pap_createat, :pap_baseentry, :pap_basetype, :pap_doctype, :pap_idadd, :pap_adress, :pap_paytype,:pap_createby,:pap_origen,:pap_qtyrq,:pap_qtyap,:pap_model, :business, :branch)";

		// Se Inicia la transaccion,
		// Todas las consultas de modificacion siguientes
		// aplicaran solo despues que se confirme la transaccion,
		// de lo contrario no se aplicaran los cambios y se devolvera
		// la base de datos a su estado original.

		$this->pedeo->trans_begin();

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':pap_docnum' => 0,
			':pap_series' => is_numeric($Encabezado[$prefijoe . '_series']) ? $Encabezado[$prefijoe . '_series'] : 0,
			':pap_docdate' => $this->validateDate($Encabezado[$prefijoe . '_docdate']) ? $Encabezado[$prefijoe . '_docdate'] : NULL,
			':pap_duedate' => $this->validateDate($Encabezado[$prefijoe . '_duedate']) ? $Encabezado[$prefijoe . '_duedate'] : NULL,
			':pap_duedev' => $this->validateDate($Encabezado[$prefijoe . '_duedev']) ? $Encabezado[$prefijoe . '_duedev'] : NULL,
			':pap_pricelist' => is_numeric($Encabezado[$prefijoe . '_pricelist']) ? $Encabezado[$prefijoe . '_pricelist'] : 0,
			':pap_cardcode' => isset($Encabezado[$prefijoe . '_cardcode']) ? $Encabezado[$prefijoe . '_cardcode'] : NULL,
			':pap_cardname' => isset($Encabezado[$prefijoe . '_cardname']) ? $Encabezado[$prefijoe . '_cardname'] : NULL,
			':pap_currency' => isset($Encabezado[$prefijoe . '_currency']) ? $Encabezado[$prefijoe . '_currency'] : NULL,
			':pap_contacid' => isset($Encabezado[$prefijoe . '_contacid']) ? $Encabezado[$prefijoe . '_contacid'] : NULL,
			':pap_slpcode' => is_numeric($Encabezado[$prefijoe . '_slpcode']) ? $Encabezado[$prefijoe . '_slpcode'] : 0,
			':pap_empid' => is_numeric($Encabezado[$prefijoe . '_empid']) ? $Encabezado[$prefijoe . '_empid'] : 0,
			':pap_comment' => isset($Encabezado[$prefijoe . '_comment']) ? $Encabezado[$prefijoe . '_comment'] : NULL,
			':pap_doctotal' => is_numeric($Encabezado[$prefijoe . '_doctotal']) ? $Encabezado[$prefijoe . '_doctotal'] : 0,
			':pap_baseamnt' => is_numeric($Encabezado[$prefijoe . '_baseamnt']) ? $Encabezado[$prefijoe . '_baseamnt'] : 0,
			':pap_taxtotal' => is_numeric($Encabezado[$prefijoe . '_taxtotal']) ? $Encabezado[$prefijoe . '_taxtotal'] : 0,
			':pap_discprofit' => is_numeric($Encabezado[$prefijoe . '_discprofit']) ? $Encabezado[$prefijoe . '_discprofit'] : 0,
			':pap_discount' => is_numeric($Encabezado[$prefijoe . '_discount']) ? $Encabezado[$prefijoe . '_discount'] : 0,
			':pap_createat' => $this->validateDate($Encabezado[$prefijoe . '_createat']) ? $Encabezado[$prefijoe . '_createat'] : NULL,
			':pap_baseentry' => is_numeric($Encabezado[$prefijoe . '_baseentry']) ? $Encabezado[$prefijoe . '_baseentry'] : 0,
			':pap_basetype' => is_numeric($Encabezado[$prefijoe . '_basetype']) ? $Encabezado[$prefijoe . '_basetype'] : 0,
			':pap_doctype' => 21,
			':pap_idadd' => isset($Encabezado[$prefijoe . '_idadd']) ? $Encabezado[$prefijoe . '_idadd'] : NULL,
			':pap_adress' => isset($Encabezado[$prefijoe . '_adress']) ? $Encabezado[$prefijoe . '_adress'] : NULL,
			':pap_paytype' => is_numeric($Encabezado[$prefijoe . '_paytype']) ? $Encabezado[$prefijoe . '_paytype'] : 0,
			':pap_createby' => isset($Encabezado[$prefijoe . '_createby']) ? $Encabezado[$prefijoe . '_createby'] : NULL,
			':pap_origen' => is_numeric($Encabezado[$prefijoe . '_doctype']) ? $Encabezado[$prefijoe . '_doctype'] : 0,
			':pap_qtyrq' => $Cantidad,
			':pap_qtyap' => $CantidadAP,
			':pap_model' => $Model,
			':business' => $Business,
			':branch' 	=> $Branch

		));


		if (is_numeric($resInsert) && $resInsert > 0) {

			//SE INSERTA EL ESTADO DEL DOCUMENTO

			$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
																VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

			$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


				':bed_docentry' => $resInsert,
				':bed_doctype' =>  21,
				':bed_status' => 5, //ESTADO CERRADO
				':bed_createby' => $Encabezado[$prefijoe . '_createby'],
				':bed_date' => date('Y-m-d'),
				':bed_baseentry' => NULL,
				':bed_basetype' => NULL
			));


			if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' => $resInsertEstado,
					'mensaje'	=> 'No se pudo registrar la cotizacion de ventas'
				);


				$this->response($respuesta);

				return;
			}

			//FIN PROCESO ESTADO DEL DOCUMENTO

			foreach ($Detalle as $key => $detail) {

				$sqlInsertDetail = "INSERT INTO pap1(ap1_docentry, ap1_itemcode, ap1_itemname, ap1_quantity, ap1_uom, ap1_whscode,
																			ap1_price, ap1_vat, ap1_vatsum, ap1_discount, ap1_linetotal, ap1_costcode, ap1_ubusiness, ap1_project,
																			ap1_acctcode, ap1_basetype, ap1_doctype, ap1_avprice, ap1_inventory, ap1_linenum, ap1_acciva, ap1_codimp)VALUES(:ap1_docentry, :ap1_itemcode, :ap1_itemname, :ap1_quantity,
																			:ap1_uom, :ap1_whscode,:ap1_price, :ap1_vat, :ap1_vatsum, :ap1_discount, :ap1_linetotal, :ap1_costcode, :ap1_ubusiness, :ap1_project,
																			:ap1_acctcode, :ap1_basetype, :ap1_doctype, :ap1_avprice, :ap1_inventory,:ap1_linenum,:ap1_acciva, :ap1_codimp)";

				$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
					':ap1_docentry' => $resInsert,
					':ap1_itemcode' => isset($detail[$prefijod . '_itemcode']) ? $detail[$prefijod . '_itemcode'] : NULL,
					':ap1_itemname' => isset($detail[$prefijod . '_itemname']) ? $detail[$prefijod . '_itemname'] : NULL,
					':ap1_quantity' => is_numeric($detail[$prefijod . '_quantity']) ? $detail[$prefijod . '_quantity'] : 0,
					':ap1_uom' => isset($detail[$prefijod . '_uom']) ? $detail[$prefijod . '_uom'] : NULL,
					':ap1_whscode' => isset($detail[$prefijod . '_whscode']) ? $detail[$prefijod . '_whscode'] : NULL,
					':ap1_price' => is_numeric($detail[$prefijod . '_price']) ? $detail[$prefijod . '_price'] : 0,
					':ap1_vat' => is_numeric($detail[$prefijod . '_vat']) ? $detail[$prefijod . '_vat'] : 0,
					':ap1_vatsum' => is_numeric($detail[$prefijod . '_vatsum']) ? $detail[$prefijod . '_vatsum'] : 0,
					':ap1_discount' => is_numeric($detail[$prefijod . '_discount']) ? $detail[$prefijod . '_discount'] : 0,
					':ap1_linetotal' => is_numeric($detail[$prefijod . '_linetotal']) ? $detail[$prefijod . '_linetotal'] : 0,
					':ap1_costcode' => isset($detail[$prefijod . '_costcode']) ? $detail[$prefijod . '_costcode'] : NULL,
					':ap1_ubusiness' => isset($detail[$prefijod . '_ubusiness']) ? $detail[$prefijod . '_ubusiness'] : NULL,
					':ap1_project' => isset($detail[$prefijod . '_project']) ? $detail[$prefijod . '_project'] : NULL,
					':ap1_acctcode' => is_numeric($detail[$prefijod . '_acctcode']) ? $detail[$prefijod . '_acctcode'] : 0,
					':ap1_basetype' => is_numeric($detail[$prefijod . '_basetype']) ? $detail[$prefijod . '_basetype'] : 0,
					':ap1_doctype' => is_numeric($detail[$prefijod . '_doctype']) ? $detail[$prefijod . '_doctype'] : 0,
					':ap1_avprice' => is_numeric($detail[$prefijod . '_avprice']) ? $detail[$prefijod . '_avprice'] : 0,
					':ap1_inventory' => is_numeric($detail[$prefijod . '_inventory']) ? $detail[$prefijod . '_inventory'] : NULL,
					':ap1_linenum' => is_numeric($detail[$prefijod . '_linenum']) ? $detail[$prefijod . '_linenum'] : NULL,
					':ap1_acciva' => is_numeric($detail[$prefijod . '_acciva']) ? $detail[$prefijod . '_acciva'] : NULL,
					':ap1_codimp' => isset($detail[$prefijod . '_codimp']) ? $detail[$prefijod . '_codimp'] : NULL
				));

				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' => $resInsertDetail,
						'mensaje'	=> 'No se pudo registrar la cotización'
					);

					$this->response($respuesta);

					return;
				}
			}


			// Si todo sale bien despues de insertar el detalle de la cotizacion
			// se confirma la trasaccion  para que los cambios apliquen permanentemente
			// en la base de datos y se confirma la operacion exitosa.
			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'El documento fue creado, pero es necesario que sea aprobado'
			);

			$this->response($respuesta);

			return;
		} else {

			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resInsert,
				'mensaje'	=> 'No se pudo crear la cotización'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
	}

	public function getBuyOffertById_get()
	{

		$Data = $this->get();

		if (!isset($Data['coc_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM dcoc WHERE coc_docentry =:coc_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":coc_docentry" => $Data['coc_docentry']));

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
