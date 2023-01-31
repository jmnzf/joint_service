<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');
require_once(APPPATH . '/asset/vendor/autoload.php');
use Restserver\libraries\REST_Controller;

class IntPurchaseAssistant extends REST_Controller
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
		$this->load->library('account');

    }

    public function getPurchaseEcBySN_get()
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

		$sqlSelect = "SELECT
					t0.*
				FROM dcec t0
				left join estado_doc t1 on t0.cec_docentry = t1.entry and t0.cec_doctype = t1.tipo
				left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
				where t2.estado = 'Abierto' and t0.cec_cardcode in ({providers})
				and t0.business = :business and t0.branch = :branch";
		$sqlSelect = str_replace("{providers}", $Data['dms_card_code'],$sqlSelect);
		
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":business" => $Data['business'],":branch" => $Data['branch']));

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

	public function getPurchaseEcDetailCI_get()
	{

		$Data = $this->get();

		if (!isset($Data['ec1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT
						t1.ec1_acciva,
						t1.ec1_acctcode,
						t1.ec1_avprice,
						t1.ec1_basetype,
						t1.ec1_costcode,
						t1.ec1_discount,
						t1.ec1_docentry,
						t1.ec1_doctype,
						t1.ec1_id,
						t1.ec1_inventory,
						t1.ec1_itemcode,
						t1.ec1_itemname,
						t1.ec1_linenum,
						get_dynamic_conversion(:currency, t0.cec_currency, t0.cec_docdate, t1.ec1_linetotal, get_localcur()) as ec1_linetotal,
						t1.ec1_project,
						t1.ec1_quantity - (coalesce(SUM(t3.dc1_quantity),0) + coalesce(SUM(t5.fc1_quantity),0)) ec1_quantity,
						t1.ec1_ubusiness,
						t1.ec1_uom,
						t1.ec1_vat,
						get_dynamic_conversion(:currency, t0.cec_currency, t0.cec_docdate, t1.ec1_vatsum, get_localcur()) as ec1_vatsum ,
						t1.ec1_whscode,
						t6.dma_uom_weight as peso,
						t6.dma_uom_vqty as metrocubico,
						t0.cec_cardcode,
						t0.cec_cardname,
						get_dynamic_conversion(:currency, t0.cec_currency, t0.cec_docdate, t1.ec1_linetotal, get_localcur()) as ec1_price 
						from dcec t0
						left join cec1 t1 on t0.cec_docentry = t1.ec1_docentry
						left join dcdc t2 on t0.cec_docentry = t2.cdc_baseentry and t0.cec_doctype = t2.cdc_basetype
						left join cdc1 t3 on t2.cdc_docentry = t3.dc1_docentry and t1.ec1_itemcode = t3.dc1_itemcode
						left join dcfc t4 on t0.cec_docentry = t4.cfc_baseentry and t0.cec_doctype = t4.cfc_basetype
						left join cfc1 t5 on t4.cfc_docentry = t5.fc1_docentry and t1.ec1_itemcode = t5.fc1_itemcode
						inner join dmar t6 on t1.ec1_itemcode = t6.dma_item_code
						WHERE t1.ec1_docentry in ({entradas})
						GROUP BY
						t1.ec1_acciva,
						t1.ec1_acctcode,
						t1.ec1_avprice,
						t1.ec1_basetype,
						t1.ec1_costcode,
						t1.ec1_discount,
						t1.ec1_docentry,
						t1.ec1_doctype,
						t1.ec1_id,
						t1.ec1_inventory,
						t1.ec1_itemcode,
						t1.ec1_itemname,
						t1.ec1_linenum,
						t1.ec1_linetotal,
						t1.ec1_project,
						t1.ec1_ubusiness,
						t1.ec1_uom,
						t1.ec1_vat,
						t1.ec1_vatsum,
						t1.ec1_whscode,
						t1.ec1_quantity,
						t6.dma_uom_weight,
						t6.dma_uom_vqty,
						t0.cec_cardcode,
						t0.cec_cardname,
						t0.cec_currency,
						t0.cec_docdate";

		$sqlSelect = str_replace("{entradas}", $Data['ec1_docentry'],$sqlSelect);
		
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":currency" => $Data['currency']));

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

	public function createInternationalPurchasing_post()
	{
		$DECI_MALES =  $this->generic->getDecimals();
		$Data = $this->post();
		$DocNumVerificado = 0;

	
		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
													ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
													ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
													ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, business, branch)VALUES (:ac1_trans_id, :ac1_account,
													:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
													:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
													:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
													:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :business, :branch)";

		if (!isset($Data['detail']) OR !isset($Data['business']) OR !isset($Data['branch'])) {

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
				'mensaje' => 'No se encontro el detalle de la entrada'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//
		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['cci_docdate'], $Data['cci_docdate'], $Data['cci_docdate'], 0);

		if (isset($periodo['error']) && $periodo['error'] == false) {
		} else {
			$respuesta = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => isset($periodo['mensaje']) ? $periodo['mensaje'] : 'no se pudo validar el periodo contable'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		//PERIODO CONTABLE
		//
		//BUSCANDO LA NUMERACION DEL DOCUMENTO
		$sqlNumeracion = " SELECT pgs_nextnum, pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

		$resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['cci_series']));

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

		//OBTENER CARPETA PRINCIPAL DEL PROYECTO
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

		//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
		// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO


		$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['cci_currency'], ':tsa_date' => $Data['cci_docdate']));

		if (isset($resBusTasa[0])) {
		} else {

			if (trim($Data['cci_currency']) != $MONEDALOCAL) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'No se encrontro la tasa de cambio para la moneda: ' . $Data['cci_currency'] . ' en la actual fecha del documento: ' . $Data['cci_docdate'] . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
		}

		$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
		$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['cci_docdate']));

		if (isset($resBusTasa2[0])) {
		} else {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $Data['cci_docdate']
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
		$TasaLocSys = $resBusTasa2[0]['tsa_value'];

		$sqlInset = "INSERT INTO dcci (cci_docnum,cci_doctype, cci_series, cci_cardcode, cci_cardname, cci_docdate, cci_duedate, cci_duedev, cci_comment, 
									   cci_currency, cci_slpcode, cci_empid, cci_doctotal, business, branch)
									   VALUES
									  (:cci_docnum, :cci_doctype, :cci_series, :cci_cardcode, :cci_cardname, :cci_docdate, :cci_duedate, :cci_duedev, :cci_comment,
									  :cci_currency, :cci_slpcode, :cci_empid, :cci_doctotal, :business, :branch)";

		$this->pedeo->trans_begin();


		$resInsert = $this->pedeo->insertRow($sqlInset, array(
			":cci_docnum" => $DocNumVerificado,
			":cci_doctype" => $Data['cci_doctype'],
			":cci_series" => $Data['cci_series'],
			":cci_cardcode" => isset($Data['cci_cardcode']) ? $Data['cci_cardcode'] : NULL,
			":cci_cardname" =>  isset($Data['cci_cardname']) ? $Data['cci_cardname'] : NULL,
			":cci_docdate" => $Data['cci_docdate'],
			":cci_duedate" => $Data['cci_duedate'],
			":cci_duedev" => $Data['cci_duedev'],
			":cci_comment" =>  isset($Data['cci_comment']) ? $Data['cci_comment'] : NULL,
			":cci_currency" => $Data['cci_currency'],
			":cci_slpcode" => isset($Data['cci_slpcode']) ? $Data['cci_slpcode'] : NULL,
			":cci_empid" => isset($Data['cci_empid']) ? $Data['cci_empid'] : NULL,
			":cci_doctotal" => isset($Data['cci_doctotal']) ? $Data['cci_doctotal'] : NULL,
			":business" => $Data['business'],
			":branch" => $Data['branch']
			)
		);

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento
			$sqlActualizarNumeracion = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
				WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id' => $Data['cci_series']
			)
			);


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error' => true,
					'data' => $resActualizarNumeracion,
					'mensaje' => 'No se pudo crear la entrada  '
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			// Fin de la actualizacion de la numeracion del documento

			foreach ($ContenidoDetalle as $key => $detail) {

				$sqlDetail = "INSERT INTO cci1(ci1_itemcode,ci1_itemname,ci1_whscode,ci1_quantity,ci1_actualcost,ci1_newcost,ci1_costcode,ci1_ubusiness,ci1_project, ci1_docentry, ci1_ubication, ci1_linetotal) VALUES
						(:ci1_itemcode,:ci1_itemname,:ci1_whscode,:ci1_quantity,:ci1_actualcost,:ci1_newcost,:ci1_costcode,:ci1_ubusiness,:ci1_project, :ci1_docentry , :ci1_ubication, :ci1_linetotal)";

				$resInsertDetail = $this->pedeo->insertRow($sqlDetail, array(
					":ci1_itemcode" => $detail['ci1_itemcode'],
					":ci1_itemname" => $detail['ci1_itemname'],
					":ci1_whscode" => isset($Data['ci1_whscode']) ? $Data['ci1_whscode'] : NULL,
					":ci1_quantity" => $detail['ci1_quantity'],
					":ci1_actualcost" => $detail['ci1_cost'],
					":ci1_newcost" => $detail['ci1_newcost'],
					":ci1_costcode" => $detail['ci1_ccost'],
					":ci1_ubusiness" => $detail['ci1_ubussines'],
					":ci1_project" => $detail['ci1_project'],
					":ci1_docentry" => $resInsert,
					":ci1_ubication" => isset($detail['ci1_ubication']) ? $detail['ci1_ubication'] : NULL,
					":ci1_linetotal" => $detail['ci1_total']
				)
				);


				if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {

					// si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $resInsertDetail,
						'mensaje' => 'No se pudo registrar la compra internacional'

					);

					$this->response($respuesta);

					return;
				}
			}

			$this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Operacion exitosa'
			);

			$this->response($respuesta);
		}
	}

	public function getInternationalPurchasing_get(){
		$Data = $this->get();
		if ( !isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT
				t0.*
				FROM dcci t0
				where t0.business = :business and t0.branch = :branch";
		
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":business" => $Data['business'],":branch" => $Data['branch']));

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