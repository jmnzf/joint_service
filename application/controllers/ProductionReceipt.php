<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/libraries/REST_Controller.php';

use Restserver\libraries\REST_Controller;

class ProductionReceipt extends REST_Controller
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
        $this->load->library('DocumentNumbering');
        $this->load->library('account');
        $this->load->library('Tasa');
        $this->load->library('generic');
		$this->load->library('emision');
    }
    public function getProductionReceipt_get()
    {

        $Data = $this->get();

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'busqueda sin resultados'
        );

        $sqlSelect = "SELECT * from tbrp WHERE business = :business";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":business" => $Data['business']));

        if (isset($resSelect[0])) {
            $respuesta = array(
                'error' => false,
                'data' => $resSelect,
                'mensaje' => '',
            );
        }

        $this->response($respuesta);
    }

	public function getProductionReceiptDetailById_get()
    {

        $Data = $this->get();

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'busqueda sin resultados'
        );

        $sqlSelect = "SELECT * from brp1 WHERE rp1_baseentry = :rp1_baseentry";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":rp1_baseentry" => $Data['docentry']));

        if (isset($resSelect[0])) {
            $respuesta = array(
                'error' => false,
                'data' => $resSelect,
                'mensaje' => '',
            );
        }

        $this->response($respuesta);
    }

    public function setProductionReceipt_post()
    {
        $DocNumVerificado = 0;
        $Data = $this->post();
		$DECI_MALES =  $this->generic->getDecimals();

        $ManejaInvetario = 0;
        $ManejaUbicacion = 0;
        $ManejaSerial = 0;
        $ManejaLote = 0;

        $DetalleInventarioGrupo = new stdClass();
        $DetalleInventarioProceso = new stdClass();
		$DetalleConsolidadoInventarioGrupo = [];
        $DetalleConsolidadoInventarioProceso = [];

		
        $inArrayInventarioGrupo = array();
        $inArrayInventarioProceso = array();

        $llaveInventarioGrupo = "";
        $llaveInventarioProceso = "";

		$posicionInventarioGrupo = 0;
        $posicionInventarioProceso = 0;

		$AC1LINE = 1;

		$CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA COMPRAS
		$CANTUOMSALE = 0; // CANTIDAD EN UNIDAD DE MEDIDA VENTAS

		$fullEmisionAutomatica = 0; // DEFINE SI ES POSIBLE HACER VARIAS RECEPCIONES CON LA MISMA ORDEN DE FABRICACION

		// Se globaliza la variable sqlDetalleAsiento
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
		ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
		ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
		ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, business, branch)VALUES (:ac1_trans_id, :ac1_account,
		:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
		:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
		:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
		:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_line, :business, :branch)";



        if (
            !isset($Data['brp_doctype']) or
            !isset($Data['brp_docnum']) or
            !isset($Data['brp_cardcode']) or
            !isset($Data['brp_duedev']) or
            !isset($Data['brp_docdate']) or
            !isset($Data['brp_ref'])
        ) {
            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'La informacion enviada no es valida',
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        // SE VERIFCA QUE EL DOCUMENTO TENGA DETALLE
        $ContenidoDetalle = json_decode($Data['detail'], true);

        if (!is_array($ContenidoDetalle)) {
            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'No se encontro el detalle',
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        // SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
        if (!intval(count($ContenidoDetalle)) > 0) {
            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'Documento sin detalle',
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

		//VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['brp_duedev'], $Data['brp_docdate'],$Data['brp_duedev'],1);

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
		$currency = $this->generic->getLocalCurrency();

		//PROCESO DE TASA
		$dataTasa = $this->tasa->Tasa($currency, $Data['brp_docdate']);

		if(isset($dataTasa['tasaLocal'])){

			$TasaDocLoc = $dataTasa['tasaLocal'];
			$TasaLocSys = $dataTasa['tasaSys'];
            $MONEDALOCAL = $dataTasa['curLocal'];
            $MONEDASYS = $dataTasa['curSys'];
                    
        }else if($dataTasa['error'] == true){

            $this->response($dataTasa, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        //FIN DE PROCESO DE TASA

        // //BUSCANDO LA NUMERACION DEL DOCUMENTO
        $DocNumVerificado = $this->documentnumbering->NumberDoc($Data['brp_serie'], $Data['brp_docdate'], $Data['brp_docdate']);

        if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0) {

        } else if ($DocNumVerificado['error']) {

            return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
        }

        $sqlInsert = "INSERT INTO tbrp ( brp_doctype, brp_docnum, brp_cardcode, brp_cardname, brp_duedev, brp_docdate, brp_ref, brp_baseentry, brp_basetype, brp_description, brp_createby, business, branch, brp_currency) VALUES(:brp_doctype, :brp_docnum, :brp_cardcode, :brp_cardname, :brp_duedev, :brp_docdate, :brp_ref, :brp_baseentry, :brp_basetype, :brp_description, :brp_createby, :business, :branch, :brp_currency)";

		// INICIA LA TRANSACCION
        $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":brp_doctype" => $Data['brp_doctype'],
            ":brp_docnum" => $DocNumVerificado,
            ":brp_cardcode" => $Data['brp_cardcode'],
            ":brp_cardname" => $Data['brp_cardname'],
            ":brp_duedev" => $Data['brp_duedev'],
            ":brp_docdate" => $Data['brp_docdate'],
            ":brp_ref" => $Data['brp_ref'],
            ":brp_baseentry" => isset($Data['brp_baseentry']) ? $Data['brp_baseentry'] : 0,
            ":brp_basetype" => is_numeric($Data['brp_basetype']) ? $Data['brp_basetype'] : 0,
            ":brp_description" => isset($Data['brp_description']) ? $Data['brp_description'] : null,
            ":brp_createby" => $Data['brp_createby'],
            ":business" => $Data['business'],
            ":branch" => $Data['branch'],
			":brp_currency" => $currency
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {

            // Se actualiza la serie de la numeracion del documento

            $sqlActualizarNumeracion = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
									WHERE pgs_id = :pgs_id";
            $resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
                ':pgs_nextnum' => $DocNumVerificado,
                ':pgs_id' => $Data['brp_serie'],
            ));

            if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {

            } else {
                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error' => true,
                    'data' => $resActualizarNumeracion,
                    'mensaje' => 'No se pudo crear la recepción de fabricación',
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
                ':bed_doctype' => $Data['brp_doctype'],
                ':bed_status' => 3, // Estado cerrado
                ':bed_createby' => $Data['brp_createby'],
                ':bed_date' => date('Y-m-d'),
                ':bed_baseentry' => null,
                ':bed_basetype' => null,
            ));

            if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {

            } else {

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error' => true,
                    'data' => $resInsertEstado,
                    'mensaje' => 'No se pudo registrar la recepción de fabricación',
                );

                $this->response($respuesta);

            }

			// SE AGREGA LA CABECERA DEL ASIENTO CONTABLE
			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch, mac_accperiod)
			VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch, :mac_accperiod)";


			$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

				':mac_doc_num' => 1,
				':mac_status' => 1,
				':mac_base_type' => is_numeric($Data['brp_doctype'])?$Data['brp_doctype']:0,
				':mac_base_entry' => $resInsert,
				':mac_doc_date' => $this->validateDate($Data['brp_docdate'])?$Data['brp_docdate']:NULL,
				':mac_doc_duedate' => $this->validateDate($Data['brp_docdate'])?$Data['brp_docdate']:NULL,
				':mac_legal_date' => $this->validateDate($Data['brp_docdate'])?$Data['brp_docdate']:NULL,
				':mac_ref1' => is_numeric($Data['brp_doctype'])?$Data['brp_doctype']:0,
				':mac_ref2' => "",
				':mac_ref3' => "",
				':mac_loc_total' => 0,
				':mac_fc_total' => 0,
				':mac_sys_total' => 0,
				':mac_trans_dode' => 1,
				':mac_beline_nume' => 1,
				':mac_vat_date' => $this->validateDate($Data['brp_docdate'])?$Data['brp_docdate']:NULL,
				':mac_serie' => 1,
				':mac_number' => 1,
				':mac_bammntsys' => 0,
				':mac_bammnt' => 0,
				':mac_wtsum' => 1,
				':mac_vatsum' => 0,
				':mac_comments' => isset($Data['brp_description'])?$Data['brp_description']:NULL,
				':mac_create_date' => date("Y-m-d"),
				':mac_made_usuer' => isset($Data['brp_createby'])?$Data['brp_createby']:NULL,
				':mac_update_date' => date("Y-m-d"),
				':mac_update_user' => isset($Data['brp_createby'])?$Data['brp_createby']:NULL,
				':business'	  => $Data['business'],
				':branch' 	  => $Data['branch'],
				':mac_accperiod' => $periodo['data']
			));


			if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
				$AgregarAsiento = false;
			}else{

				// si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
				// se retorna el error y se detiene la ejecucion del codigo restante.
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'  => true,
					'data'   => $resInsertAsiento,
					'mensaje'=> 'No se pudo registrar la Devolución de clientes'
				);

				$this->response($respuesta);

				return;
			}

			// BUSCANDO ARTICULOS DE LA LISTA PARA VALIDAR
			// SI TODOS SON DE EMISION AUTOMATICA 
			// O SI SON DE EMISION MANUAL COMPROBAR QUE
			// YA SE REALIZO LA MISMA
			
			
			$sqlItemsListaM = "SELECT bof1.of1_item_code as lm1_itemcode, 
							bof1.of1_type as lm1_type, 
							case 
								when of1_type = 2 then dma_emisionmethod
								when of1_type = 1 then of1_emimet::int
							end as dma_emisionmethod, 
							of1_whscode as bof_whscode, 
							of1_required_quantity as lm1_quantity, 
							bof_ccost, 
							bof_project,
							case 
								when of1_type = 2 then coalesce(costo_por_almacen(of1_item_code, of1_whscode), 0) 
								when of1_type = 1 then coalesce(costo_recurso(of1_item_code::int), 0) 
							end as lm1_price,
							of1_uom,
							of1_description,
							of1_costcode,
							of1_uom_code,
							of1_ing,
							bof_baseentry
							FROM tbof
							INNER JOIN bof1 ON tbof.bof_docentry = bof1.of1_docentry
							LEFT JOIN dmar ON of1_item_code = dma_item_code 
							WHERE bof_docentry  = :bof_docentry
							group by of1_item_code, lm1_type, dma_emisionmethod, of1_whscode, 
							of1_required_quantity, lm1_price, bof_ccost, bof_project,of1_uom,
							of1_description, of1_costcode, of1_uom_code, of1_ing, bof_baseentry,of1_emimet"; 

			$resItemsListaM = $this->pedeo->queryTable($sqlItemsListaM, array(
				":bof_docentry" => $Data['brp_baseentry']
			));

			if ( !isset($resItemsListaM[0]) ) {

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'  => true,
					'data'   => $resItemsListaM,
					'mensaje'=> 'No se encontraron, los articulos de la lista de materiales'
				);

				$this->response($respuesta);

				return;
			}

			//
			$itemMan = [];
			$itemAuto = [];
			$itemGen = [];
			foreach ($resItemsListaM as $key => $itemLista) {
				
				if ( $itemLista['dma_emisionmethod'] == 1 ) {
					array_push($itemAuto, $itemLista);
				} else if ( $itemLista['dma_emisionmethod'] == 2 ) {
					array_push($itemMan, $itemLista);
					array_push($itemGen, $itemLista);
				}
			}

			if ( count($itemMan) > 0 ) {
				$fullEmisionAutomatica  = 0;
			} else {
				$fullEmisionAutomatica  = 1;
			}

			// VERIFICAR SI LA ORDEN TIENE EMISIONES REALIZADAS
			if ( $fullEmisionAutomatica == 0 ) {

				$sqlVerificarEmision = "SELECT bep_baseentry FROM tbep WHERE bep_baseentry = :bep_baseentry";

				$resVerificarEmision = $this->pedeo->queryTable($sqlVerificarEmision, array(
					":bep_baseentry" => $Data['brp_baseentry']
				));

				if ( !isset($resVerificarEmision[0]) ) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'  => true,
						'data'   => $resItemsListaM,
						'mensaje'=> 'No se ha realizado la emisión de los artículos cuyo método de emisión es manual'
					);
	
					$this->response($respuesta);
	
					return;
				}


				// CERRAR LA ORDEN DE FABRICACION SI LA EMISION TIENE ACTICULOS DE EMISION MANUAL
				$sqlCerrarOdenF = "UPDATE tbof set bof_status = 3 WHERE bof_docentry = :bof_docentry";
				$resCerrarOdenF = $this->pedeo->updateRow($sqlCerrarOdenF, array(":bof_docentry" => $Data['brp_baseentry']));

				if (is_numeric($resCerrarOdenF) && $resCerrarOdenF == 1) {

				}else{
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'  => true,
						'data'   => $resCerrarOdenF,
						'mensaje'=> 'No se pudo actualizar la orden de fabricación'
					);
	
					$this->response($respuesta);
	
					return;
				}
				//
			}

			if (count($itemAuto) > 0) {
				//REALIZAR LAS EMISIONES DE LOS ARTICULOS MARCADOS CON EMISION AUTOMATICA
				$resEmission = $this->emision->setEmission($itemAuto, $Data, $periodo, $resInsert, $DocNumVerificado, $TasaDocLoc, $TasaLocSys, $MONEDALOCAL, $MONEDASYS);

				if ( isset($resEmission['error']) && $resEmission['error'] == false ) {
				}else{

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 	  => $resEmission,
						'mensaje' => $resEmission['mensaje'],
						
					);

					$this->response($respuesta);

					return;
				}
			}
			//

			$sqlCostoContabilizado = "SELECT sum(ac1_debit) AS costo FROM (SELECT ac1_debit, ac1_credit
								FROM tbof 
								INNER JOIN tbep ON tbof.bof_docentry = tbep.bep_baseentry
								INNER JOIN mac1 ON bep_docentry = ac1_font_key AND bep_doctype = mac1.ac1_font_type 
								WHERE bof_docentry = :bof_docentry) AS data";

			$resCostoContabilizado = $this->pedeo->queryTable($sqlCostoContabilizado, array(
				":bof_docentry" => $Data['brp_baseentry']
			));


			$CostoContabilizado = 0;

			if ( isset($resCostoContabilizado[0]) && $resCostoContabilizado[0]['costo'] > 0 ) {
				$CostoContabilizado = $resCostoContabilizado[0]['costo'];
			}else{

				$this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data' 	  => $resCostoContabilizado,
                    'mensaje' => 'No se encontro el costo generado por las emisiones de fabricación',
                );

                $this->response($respuesta);

                return;
			}
			//

            $sqlInsert2 = "INSERT INTO brp1 (rp1_item_description, rp1_quantity, rp1_itemcost, rp1_im, rp1_ccost, rp1_ubusiness, rp1_item_code, rp1_listmat, rp1_whscode, rp1_baseentry, rp1_plan) values (:rp1_item_description, :rp1_quantity, :rp1_itemcost, :rp1_im, :rp1_ccost, :rp1_ubusiness, :rp1_item_code, :rp1_listmat, :rp1_whscode, :rp1_baseentry, :rp1_plan)";

            foreach ($ContenidoDetalle as $key => $detail) {

				$CANTUOMPURCHASE = $this->generic->getUomPurchase($detail['rp1_item_code']);
				$CANTUOMSALE = $this->generic->getUomSale($detail['rp1_item_code']);



				if ($CANTUOMPURCHASE == 0 || $CANTUOMSALE == 0) {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 		=> $detail['rp1_item_code'],
						'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['rp1_item_code']
					);

					$this->response($respuesta);

					return;
				}

				if ( isset($detail['rp1_whscode']) && !empty($detail['rp1_whscode']) ){
				}else{

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 	  => $detail['rp1_item_code'],
						'mensaje' => 'No se encontro el almacen destino del producto: ' . $detail['rp1_item_code']
					);

					$this->response($respuesta);

					return;
				}


				$CostoDefinitivo = round($CostoContabilizado / $detail['rp1_quantity'] , $DECI_MALES);

                $resInsert2 = $this->pedeo->insertRow($sqlInsert2, array(
                    ":rp1_item_description" => $detail['rp1_item_description'],
                    ":rp1_quantity" => $detail['rp1_quantity'],
                    ":rp1_itemcost" => $CostoDefinitivo,
                    ":rp1_im" => $detail['rp1_im'],
                    ":rp1_ccost" => $detail['rp1_ccost'],
                    ":rp1_ubusiness" => $detail['rp1_ubusiness'],
                    ":rp1_item_code" => $detail['rp1_item_code'],
                    ":rp1_listmat" => $detail['rp1_listmat'],
                    ":rp1_whscode" => isset($detail['rp1_whscode']) ? $detail['rp1_whscode'] : '',
                    ":rp1_baseentry" => $resInsert,
                    ":rp1_plan" => is_numeric($detail['rp1_plan']) ? $detail['rp1_plan'] : 0
                ));

                if (is_numeric($resInsert2) and $resInsert2 > 0) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsert2,
                        'mensaje' => 'No se pudo realizar operación',
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }



                // SI EL ITEM ES INVETARIABLE
				// SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO
				// Y A SU VES SI MANEJA LOTE
				$sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
				$resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

					':dma_item_code' => $detail['rp1_item_code'],
					':dma_item_inv'  => 1
				));

				if (isset($resItemINV[0])) {


					// CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
					$sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
					$resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
						':dws_ubication' => 1,
						':dws_code' => $detail['rp1_whscode'],
						':business' => $Data['business']
					));


					if ( isset($resubicacion[0]) ){
						$ManejaUbicacion = 1;
					}else{
						$ManejaUbicacion = 0;
					}

					// SI EL ARTICULO MANEJA LOTE
					$sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
					$resLote = $this->pedeo->queryTable($sqlLote, array(
	
						':dma_item_code' => $detail['rp1_item_code'],
						':dma_lotes_code'  => 1
					));
	
					if (isset($resLote[0])) {
						$ManejaLote = 1;
					} else {
						$ManejaLote = 0;
					}

					$ManejaInvetario = 1;
				} else {
					$ManejaInvetario = 0;
				}

                // FIN PROCESO ITEM MANEJA INVENTARIO Y LOTE
				// si el item es inventariable
				if ($ManejaInvetario == 1) {

                    //SE VERIFICA SI EL ARTICULO MANEJA SERIAL
					$sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
					$resItemSerial = $this->pedeo->queryTable($sqlItemSerial, array(

						':dma_item_code' => $detail['rp1_item_code'],
						':dma_series_code'  => 1
					));

					if (isset($resItemSerial[0])) {
						$ManejaSerial = 1;

						if (!isset($detail['serials'])) {
							$respuesta = array(
								'error'   => true,
								'data'    => [],
								'mensaje' => 'No se encontraron los seriales para el articulo: ' . $detail['rp1_item_code']
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}

						$AddSerial = $this->generic->addSerial($detail['serials'], $detail['rp1_item_code'], $Data['brp_doctype'], $resInsert, $DocNumVerificado, $Data['brp_docdate'], 1, $Data['brp_description'], $detail['rp1_whscode'], $detail['rp1_quantity'], $Data['brp_createby'], $resInsertDetail, $Data['business']);

						if (isset($AddSerial['error']) && $AddSerial['error'] == false) {
						} else {
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
					//para almacenar en el movimiento de inventario
					//SI EL ARTICULO MANEJA LOTE SE BUSCA POR LOTE Y ALMACEN

					$sqlCostoMomentoRegistro = '';
					$resCostoMomentoRegistro = [];
					// SI EL ALMACEN MANEJA UBICACION
					if ( $ManejaUbicacion == 1 ){
						if ($ManejaLote == 1) {

							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND bdi_ubication = :bdi_ubication AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode'   => $detail['rp1_whscode'],
								':bdi_itemcode'  => $detail['rp1_item_code'],
								':bdi_lote'      => $detail['ote_code'],
								':bdi_ubication' => $detail['rp1_ubication'],
								':business' 	 => $Data['business']
							));
						} else {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode'   => $detail['rp1_whscode'],
								':bdi_itemcode'  => $detail['rp1_item_code'],
								':bdi_ubication' => $detail['rp1_ubication'],
								':business' 	 => $Data['business']
							));
						}
					}else{

						if ($ManejaLote == 1) {

							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' 	=> $detail['rp1_whscode'],
								':bdi_itemcode' => $detail['rp1_item_code'],
								':bdi_lote'	 	=> $detail['ote_code'],
								':business' 	=> $Data['business']
							));
						} else {
							$sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
							$resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
								':bdi_whscode' 	=> $detail['rp1_whscode'],
								':bdi_itemcode' => $detail['rp1_item_code'],
								':business' 	=> $Data['business']
							));
						}
	
					}
                    // 
                    if (isset($resCostoMomentoRegistro[0])) {

						$NuevoCostoPonderado = 0;
						// se aplica costo ponderado
						if ($resCostoMomentoRegistro[0]['bdi_quantity'] > 0) {

							
							$CantidadActual = $resCostoMomentoRegistro[0]['bdi_quantity'];
							$CostoActual = 	$resCostoMomentoRegistro[0]['bdi_avgprice'];

				
							$CantidadNueva = $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
							$CostoNuevo = $CostoDefinitivo;

							$CantidadTotal = ($CantidadActual + $CantidadNueva);


							$NuevoCostoPonderado = ($CantidadActual  *  $CostoActual) + ($CantidadNueva * $CostoNuevo);
							$NuevoCostoPonderado = round(($NuevoCostoPonderado / $CantidadTotal), $DECI_MALES);
						} else {

							$CostoNuevo = $CostoDefinitivo;

							$NuevoCostoPonderado = $CostoNuevo;
						}

						$sqlInserMovimiento = '';
						$resInserMovimiento = [];
						
						//Se aplica el movimiento de inventario
						$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment, bmi_lote, bmi_ubication,business)
											VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote, :bmi_ubication,:business)";

						$resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

							':bmi_itemcode' => isset($detail['rp1_item_code']) ? $detail['rp1_item_code'] : NULL,
							':bmi_quantity' =>  ($this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE) * 1),
							':bmi_whscode'  => isset($detail['rp1_whscode']) ? $detail['rp1_whscode'] : NULL,
							':bmi_createat' => date("Y-m-d").' '.date("H:i:s"),
							':bmi_createby' => isset($Data['brp_createby']) ? $Data['brp_createby'] : NULL,
							':bmy_doctype'  => is_numeric($Data['brp_doctype']) ? $Data['brp_doctype'] : 0,
							':bmy_baseentry' => $resInsert,
							':bmi_cost'      => $NuevoCostoPonderado,
							':bmi_currequantity' => $resCostoMomentoRegistro[0]['bdi_quantity'],
							':bmi_basenum' => $DocNumVerificado,
							':bmi_docdate' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
							':bmi_duedate' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
							':bmi_duedev'  => $this->validateDate($Data['brp_duedev']) ? $Data['brp_duedev'] : NULL,
							':bmi_comment' => isset($Data['brp_description']) ? $Data['brp_description'] : NULL,
							':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
							':bmi_ubication' => isset($detail['rp1_ubication']) ? $detail['rp1_ubication'] : NULL,
							':business' => isset($Data['business']) ? $Data['business'] : NULL
						));
						
					

						if (is_numeric($resInserMovimiento) && $resInserMovimiento > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {

							// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInserMovimiento,
								'mensaje'	=> 'No se pudo registrar el recibo de producción'
							);

							$this->response($respuesta);

							return;
						}
					} else {

						// SE COLOCA EL PRECIO DE LA LINEA COMO EL COSTO
						//Se aplica el movimiento de inventario
						//SE VALIDA SI EL ARTICULO MANEJA LOTE
						$sqlInserMovimiento = '';
						$resInserMovimiento = [];

						
						$sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_lote, bmi_ubication,business)
															VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote, :bmi_ubication,:business)";
						$resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

							':bmi_itemcode'  => isset($detail['rp1_item_code']) ? $detail['rp1_item_code'] : NULL,
							':bmi_quantity'  =>  ($this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE) * 1),
							':bmi_whscode'   => isset($detail['rp1_whscode']) ? $detail['rp1_whscode'] : NULL,
							':bmi_createat'  => date("Y-m-d"),
							':bmi_createby'  => isset($Data['brp_createby']) ? $Data['brp_createby'] : NULL,
							':bmy_doctype'   => is_numeric($Data['brp_doctype']) ? $Data['brp_doctype'] : 0,
							':bmy_baseentry' => $resInsert,
							':bmi_cost'      => $CostoDefinitivo,
							':bmi_currequantity' =>  0,
							':bmi_basenum'	=> $DocNumVerificado,
							':bmi_docdate' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
							':bmi_duedate' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
							':bmi_duedev'  => $this->validateDate($Data['brp_duedev']) ? $Data['brp_duedev'] : NULL,
							':bmi_comment' => isset($Data['brp_description']) ? $Data['brp_description'] : NULL,
							':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
							':bmi_ubication' => isset($detail['rp1_ubication']) ? $detail['rp1_ubication'] : NULL,
							':business' => isset($Data['business']) ? $Data['business'] : NULL

						));
						


						if (is_numeric($resInserMovimiento) && $resInserMovimiento > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {

							// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data' => $resInserMovimiento,
								'mensaje'	=> 'No se pudo registrar el recibo de producción'
							);

							$this->response($respuesta);

							return;
						}
					}
					//FIN aplicacion de movimiento de inventario



                    //Se Aplica el movimiento en stock ***************
					//Buscando item en el stock
					//SE VALIDA SI EL ARTICULO MANEJA LOTE
					$sqlCostoCantidad = '';
					$resCostoCantidad = [];
					$CantidadPorAlmacen = 0;
					$CostoPorAlmacen = 0;

					// SI EL ALMACEN MANEJA UBICACION

					if ( $ManejaUbicacion == 1 ){
						if ($ManejaLote == 1) {

							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND bdi_lote = :bdi_lote
											AND bdi_ubication = :bdi_ubication
											AND business = :business";
	
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode'  => $detail['rp1_item_code'],
								':bdi_whscode'   => $detail['rp1_whscode'],
								':bdi_lote' 	 => $detail['ote_code'],
								':bdi_ubication' => $detail['rp1_ubication'],
								':business' 	 => $Data['business']
							));
							// se busca la cantidad general del articulo agrupando todos los almacenes y lotes
							$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
							$resCGA = $this->pedeo->queryTable($sqlCGA, array(
								':bdi_itemcode' => $detail['rp1_item_code'],
								':bdi_whscode'  => $detail['rp1_whscode'],
								':business' 	=> $Data['business']
							));
	
							if (isset($resCGA[0]['bdi_quantity']) && is_numeric($resCGA[0]['bdi_quantity'])) {
	
								$CantidadPorAlmacen = $resCGA[0]['bdi_quantity'];
								$CostoPorAlmacen = $resCGA[0]['bdi_avgprice'];
							} else {
	
								$CantidadPorAlmacen = 0;
								$CostoPorAlmacen = 0;
							}
						} else {

							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND bdi_ubication = :bdi_ubication
											AND business = :business";
	
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode'  => $detail['rp1_item_code'],
								':bdi_whscode'   => $detail['rp1_whscode'],
								':bdi_ubication' => $detail['rp1_ubication'],
								':business' 	 => $Data['business']
							));
							// se busca la cantidad general del articulo agrupando todos los almacenes y lotes
							$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
							$resCGA = $this->pedeo->queryTable($sqlCGA, array(
								':bdi_itemcode' => $detail['rp1_item_code'],
								':bdi_whscode'  => $detail['rp1_whscode'],
								':business' 	=> $Data['business']
							));
	
							if (isset($resCGA[0]['bdi_quantity']) && is_numeric($resCGA[0]['bdi_quantity'])) {
	
								$CantidadPorAlmacen = $resCGA[0]['bdi_quantity'];
								$CostoPorAlmacen = $resCGA[0]['bdi_avgprice'];
							} else {
	
								$CantidadPorAlmacen = 0;
								$CostoPorAlmacen = 0;
							}
						}

					}else{
						if ($ManejaLote == 1) {

							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND bdi_lote = :bdi_lote
											AND business = :business";
	
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode' => $detail['rp1_item_code'],
								':bdi_whscode'  => $detail['rp1_whscode'],
								':bdi_lote' 	=> $detail['ote_code'],
								':business'	 	=> $Data['business']
							));
							// se busca la cantidad general del articulo agrupando todos los almacenes y lotes
							$sqlCGA = "SELECT sum(COALESCE(bdi_quantity, 0)) as bdi_quantity, bdi_avgprice FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode AND business = :business GROUP BY bdi_whscode, bdi_avgprice";
							$resCGA = $this->pedeo->queryTable($sqlCGA, array(
								':bdi_itemcode' => $detail['rp1_item_code'],
								':bdi_whscode'  => $detail['rp1_whscode'],
								':business' 	=> $Data['business']
							));
	
							if (isset($resCGA[0]['bdi_quantity']) && is_numeric($resCGA[0]['bdi_quantity'])) {
	
								$CantidadPorAlmacen = $resCGA[0]['bdi_quantity'];
								$CostoPorAlmacen = $resCGA[0]['bdi_avgprice'];
							} else {
	
								$CantidadPorAlmacen = 0;
								$CostoPorAlmacen = 0;
							}
						} else {
							$sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
											FROM tbdi
											WHERE bdi_itemcode = :bdi_itemcode
											AND bdi_whscode = :bdi_whscode
											AND business = :business";
	
							$resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
	
								':bdi_itemcode' => $detail['rp1_item_code'],
								':bdi_whscode'  => $detail['rp1_whscode'],
								':business' 	=> $Data['business']

							));
	
							$CantidadPorAlmacen = isset($resCostoCantidad[0]['bdi_quantity']) ? $resCostoCantidad[0]['bdi_quantity'] : 0;
							$CostoPorAlmacen = isset($resCostoCantidad[0]['bdi_avgprice']) ? $resCostoCantidad[0]['bdi_avgprice'] : 0;
						}
					}
                    //
                    // SI EXISTE EN EL STOCK
					if (isset($resCostoCantidad[0])) {
						//SI TIENE CANTIDAD POSITIVA
						if ($resCostoCantidad[0]['bdi_quantity'] > 0 && $CantidadPorAlmacen > 0) {

							$CantidadItem = $resCostoCantidad[0]['bdi_quantity'];
							$CantidadActual = $CantidadPorAlmacen;
							$CostoActual = $CostoPorAlmacen;


							$CantidadNueva =  $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
							$CostoNuevo = $CostoDefinitivo;


							$CantidadTotal = ($CantidadActual + $CantidadNueva);
							$CantidadTotalItemSolo = ($CantidadItem + $CantidadNueva);

						

							$NuevoCostoPonderado = ($CantidadActual  *  $CostoActual) + ($CantidadNueva * $CostoNuevo);
							$NuevoCostoPonderado = round(($NuevoCostoPonderado / $CantidadTotal), $DECI_MALES);

							$sqlUpdateCostoCantidad = "UPDATE tbdi
													SET bdi_quantity = :bdi_quantity
													,bdi_avgprice = :bdi_avgprice
													WHERE  bdi_id = :bdi_id";

							$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

								':bdi_quantity' => $CantidadTotalItemSolo,
								':bdi_avgprice' => $NuevoCostoPonderado,
								':bdi_id' 		=> $resCostoCantidad[0]['bdi_id']
							));

							if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resUpdateCostoCantidad,
									'mensaje'	=> 'No se pudo registrar el documento'
								);


								$this->response($respuesta);

								return;
							}

							// SE ACTUALIZA EL COSTO PONDERADO EN EL ALMACEN DEL ARTICULO
							// SIN MIRAR LA UBICACION O LOTE
							$sqlAlmacenMasivo = "UPDATE tbdi
												SET bdi_avgprice = :bdi_avgprice
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND business = :business";
							
							$resAlmacenMasivo = $this->pedeo->updateRow($sqlAlmacenMasivo, array(
								':bdi_avgprice' => $NuevoCostoPonderado,
								':bdi_itemcode' => $detail['rp1_item_code'],
								':bdi_whscode'  => $detail['rp1_whscode'],
								':business' 	=> $Data['business']
							));		
							
							if (is_numeric($resAlmacenMasivo) && $resAlmacenMasivo > 0 || $resAlmacenMasivo == 0) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resAlmacenMasivo,
									'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
								);


								$this->response($respuesta);

								return;
							}

							// SE ACTUALZAN TODOS LOS COSTOS PONDERADOS DE LOS ARTICULOS EN EL ALMACEN
							if ($ManejaLote == 1) {
								$sqlUpdateCostoCantidad = "UPDATE tbdi
													SET bdi_avgprice = :bdi_avgprice
													WHERE bdi_itemcode = :bdi_itemcode
													AND bdi_whscode = :bdi_whscode
													AND business = :business";

								$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

									':bdi_avgprice' => $NuevoCostoPonderado,
									':bdi_itemcode' => $detail['rp1_item_code'],
									':bdi_whscode'  => $detail['rp1_whscode'],
									':business' 	=> $Data['business']
								));

								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear el documento'
									);

									$this->response($respuesta);

									return;
								}
							}
						} else {

							$CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
							$CantidadNueva = $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
							$CostoNuevo = $CostoDefinitivo;

							

							$CantidadTotal = ($CantidadActual + $CantidadNueva);

							$sqlUpdateCostoCantidad = "UPDATE tbdi
													SET bdi_quantity = :bdi_quantity
													,bdi_avgprice = :bdi_avgprice
													WHERE  bdi_id = :bdi_id";

							$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

								':bdi_quantity' => $CantidadTotal,
								':bdi_avgprice' => $CostoNuevo,
								':bdi_id' 		=> $resCostoCantidad[0]['bdi_id']
							));

							if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resUpdateCostoCantidad,
									'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
								);


								$this->response($respuesta);

								return;
							}

							// SE ACTUALIZA EL COSTO PONDERADO EN EL ALMACEN DEL ARTICULO
							// SIN MIRAR LA UBICACION O LOTE
							$sqlAlmacenMasivo = "UPDATE tbdi
												SET bdi_avgprice = :bdi_avgprice
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND business = :business";
							
							$resAlmacenMasivo = $this->pedeo->updateRow($sqlAlmacenMasivo, array(
								':bdi_avgprice' => $NuevoCostoPonderado,
								':bdi_itemcode' => $detail['rp1_item_code'],
								':bdi_whscode'  => $detail['rp1_whscode'],
								':business' 	=> $Data['business']
							));		
							
							if (is_numeric($resAlmacenMasivo) && $resAlmacenMasivo > 0 || $resAlmacenMasivo == 0) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resAlmacenMasivo,
									'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
								);


								$this->response($respuesta);

								return;
							}

							// SE ACTUALZAN TODOS LOS COSTOS PONDERADOS DE LOS ARTICULOS EN EL ALMACEN
							if ($ManejaLote == 1) {
								$sqlUpdateCostoCantidad = "UPDATE tbdi
														SET bdi_avgprice = :bdi_avgprice
														WHERE bdi_itemcode = :bdi_itemcode
														AND bdi_whscode = :bdi_whscode
														AND business = :business";

								$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

									':bdi_avgprice' => $CostoNuevo,
									':bdi_itemcode' => $detail['rp1_item_code'],
									':bdi_whscode'  => $detail['rp1_whscode'],
									':business' 	=> $Data['business']
								));

								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad  > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear el documento'
									);

									$this->response($respuesta);

									return;
								}
							}
						}

						// En caso de que no exista el item en el stock
						// Se inserta en el stock con el precio de compra

					} else {

						if ($CantidadPorAlmacen > 0) {
							$CantidadItem = 0;
							$CantidadActual = $CantidadPorAlmacen;
							$CostoActual = $CostoPorAlmacen;

							$CantidadNueva = $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
							$CostoNuevo = $CostoDefinitivo;

							$CantidadTotal = ($CantidadActual + $CantidadNueva);
							$CantidadTotalItemSolo = ($CantidadItem + $CantidadNueva);


							$NuevoCostoPonderado = ($CantidadActual  *  $CostoActual) + ($CantidadNueva * $CostoNuevo);
							$NuevoCostoPonderado = round(($NuevoCostoPonderado / $CantidadTotal), $DECI_MALES);


							$sqlInsertCostoCantidad = '';
							$resInsertCostoCantidad =	[];

							// SI EL ALMACEN MANEJA UBICACION
							if ( $ManejaUbicacion == 1 ){
								// SI EL ARTICULO MANEJA LOTE
								if ($ManejaLote == 1) {
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, bdi_ubication, business)
															VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :bdi_ubication, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode'  => $detail['rp1_item_code'],
										':bdi_whscode'   => $detail['rp1_whscode'],
										':bdi_quantity'  =>  $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
										':bdi_avgprice'  => $NuevoCostoPonderado,
										':bdi_lote' 	 => $detail['ote_code'],
										':bdi_ubication' => $detail['rp1_ubication'],
										':business' 	 => $Data['business']
									));
								} else {
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_ubication, business)
															VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_ubication, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode'  => $detail['rp1_item_code'],
										':bdi_whscode'   => $detail['rp1_whscode'],
										':bdi_quantity'  =>  $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
										':bdi_avgprice'  => $NuevoCostoPonderado,
										':bdi_ubication' => $detail['rp1_ubication'],
										':business' 	 => $Data['business']
									));
								}
							}else{
								// SI EL ALMACEN MANEJA UBICACION 
								if ( $ManejaUbicacion == 1 ) {
									// SI EL ARTICULO MANEJA LOTE
									if ($ManejaLote == 1) {
										$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, bdi_ubication, business)
																VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :bdi_ubication, :business)";


										$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

											':bdi_itemcode' => $detail['rp1_item_code'],
											':bdi_whscode'  => $detail['rp1_whscode'],
											':bdi_quantity' =>  $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
											':bdi_avgprice' => $NuevoCostoPonderado,
											':bdi_lote' 	=> $detail['ote_code'],
											':bdi_ubication' => $detail['rp1_ubication'],
											':business'		 => $Data['business']
										));
									} else {
										$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_ubication, business)
										VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_ubication, :business)";


										$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

											':bdi_itemcode' => $detail['rp1_item_code'],
											':bdi_whscode'  => $detail['rp1_whscode'],
											':bdi_quantity' =>  $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
											':bdi_avgprice' => $NuevoCostoPonderado,
											':bdi_ubication' => $detail['rp1_ubication'],
											':business'		 => $Data['business']
										));
									}
								}else{
									// SI EL ARTICULO MANEJA LOTE
									if ($ManejaLote == 1) {
										$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, business)
																VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :business)";
		
		
										$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(
		
											':bdi_itemcode' => $detail['rp1_item_code'],
											':bdi_whscode'  => $detail['rp1_whscode'],
											':bdi_quantity' =>  $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
											':bdi_avgprice' => $NuevoCostoPonderado,
											':bdi_lote' 	=> $detail['ote_code'],
											':business' 	=> $Data['business']
										));
									} else {
		
										$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, business)
																VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :business)";
		
		
										$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(
		
											':bdi_itemcode' => $detail['rp1_item_code'],
											':bdi_whscode'  => $detail['rp1_whscode'],
											':bdi_quantity' =>  $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
											':bdi_avgprice' => $NuevoCostoPonderado,
											':business' 	=> $Data['business']
										));
									}
								}
							}



							if (is_numeric($resInsertCostoCantidad) && $resInsertCostoCantidad > 0) {
								// Se verifica que el detalle no de error insertando //
							} else {

								// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 	  => $resInsertCostoCantidad,
									'mensaje' => 'No se pudo registrar el documento'
								);

								$this->response($respuesta);

								return;
							}

							// SE ACTUALIZA EL COSTO PONDERADO EN EL ALMACEN DEL ARTICULO
							// SIN MIRAR LA UBICACION O LOTE
							$sqlAlmacenMasivo = "UPDATE tbdi
												SET bdi_avgprice = :bdi_avgprice
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND business = :business";
							
							$resAlmacenMasivo = $this->pedeo->updateRow($sqlAlmacenMasivo, array(
								':bdi_avgprice' => $NuevoCostoPonderado,
								':bdi_itemcode' => $detail['rp1_item_code'],
								':bdi_whscode'  => $detail['rp1_whscode'],
								':business' 	=> $Data['business']
							));		
							
							if (is_numeric($resAlmacenMasivo) && $resAlmacenMasivo > 0 || $resAlmacenMasivo == 0) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resAlmacenMasivo,
									'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
								);


								$this->response($respuesta);

								return;
							}

							$sqlInsertCostoCantidad = '';
							$resInsertCostoCantidad = [];
							// SE ACTUALZAN TODOS LOS COSTOS PONDERADOS DE LOS ARTICULOS EN EL ALMACEN
							if ($ManejaLote == 1) {
								$sqlUpdateCostoCantidad = "UPDATE tbdi
														SET bdi_avgprice = :bdi_avgprice
														WHERE bdi_itemcode = :bdi_itemcode
														AND bdi_whscode = :bdi_whscode
														AND business = :business";

								$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

									':bdi_avgprice' => $NuevoCostoPonderado,
									':bdi_itemcode' => $detail['rp1_item_code'],
									':bdi_whscode'  => $detail['rp1_whscode'],
									':business' 	=> $Data['business']
								));



								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear el documento'
									);

									$this->response($respuesta);

									return;
								}
							}
						} else {
							$CostoNuevo = $CostoDefinitivo;

		
							
							$sqlInsertCostoCantidad = '';
							$resInsertCostoCantidad =	[];

							// SI EL ALMACEN MANEJA UBICACION
							if ( $ManejaUbicacion == 1 ){
								//SE VALIDA SI EL ARTICULO MANEJA LOTE
								if ($ManejaLote == 1) {

									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, bdi_ubication, business)
															VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :bdi_ubication, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode'  => $detail['rp1_item_code'],
										':bdi_whscode'   => $detail['rp1_whscode'],
										':bdi_quantity'  => $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
										':bdi_avgprice'  => $CostoNuevo,
										':bdi_lote' 	 => $detail['ote_code'],
										':bdi_ubication' => $detail['rp1_ubication'],
										':business'		 => $Data['business']
									));
								} else {
									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_ubication, business)
									VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_ubication, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode'  => $detail['rp1_item_code'],
										':bdi_whscode'   => $detail['rp1_whscode'],
										':bdi_quantity'  => $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
										':bdi_avgprice'  => $CostoNuevo,
										':bdi_ubication' => $detail['ei1_ubication'],
										':business' 	 => $Data['business']
									));
								}
							}else{
								//SE VALIDA SI EL ARTICULO MANEJA LOTE
								if ($ManejaLote == 1) {

									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, bdi_lote, business)
															VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :bdi_lote, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode' => $detail['rp1_item_code'],
										':bdi_whscode'  => $detail['rp1_whscode'],
										':bdi_quantity' => $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
										':bdi_avgprice' => $CostoNuevo,
										':bdi_lote' 	=> $detail['ote_code'],
										':business' 	=> $Data['business']
									));
								} else {

									$sqlInsertCostoCantidad = "INSERT INTO tbdi(bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice, business)
														VALUES (:bdi_itemcode, :bdi_whscode, :bdi_quantity, :bdi_avgprice, :business)";


									$resInsertCostoCantidad	= $this->pedeo->insertRow($sqlInsertCostoCantidad, array(

										':bdi_itemcode' => $detail['rp1_item_code'],
										':bdi_whscode'  => $detail['rp1_whscode'],
										':bdi_quantity' => $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE),
										':bdi_avgprice' => $CostoNuevo,
										':business'		=> $Data['business']
									));
								}

							}
					


							if (is_numeric($resInsertCostoCantidad) && $resInsertCostoCantidad > 0) {
								// Se verifica que el detalle no de error insertando //
							} else {

								// si falla algun insert del detalle de la orden de compra se devuelven los cambios realizados por la transaccion,
								// se retorna el error y se detiene la ejecucion del codigo restante.
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 		=> $resInsertCostoCantidad,
									'mensaje'	=> 'No se pudo registrar el documento'
								);

								$this->response($respuesta);

								return;
							}

							// SE ACTUALIZA EL COSTO PONDERADO EN EL ALMACEN DEL ARTICULO
							// SIN MIRAR LA UBICACION O LOTE
							$sqlAlmacenMasivo = "UPDATE tbdi
												SET bdi_avgprice = :bdi_avgprice
												WHERE bdi_itemcode = :bdi_itemcode
												AND bdi_whscode = :bdi_whscode
												AND business = :business";
							
							$resAlmacenMasivo = $this->pedeo->updateRow($sqlAlmacenMasivo, array(
								':bdi_avgprice' => $CostoNuevo,
								':bdi_itemcode' => $detail['rp1_item_code'],
								':bdi_whscode'  => $detail['rp1_whscode'],
								':business' 	=> $Data['business']
							));		
							
							if (is_numeric($resAlmacenMasivo) && $resAlmacenMasivo > 0 || $resAlmacenMasivo == 0) {
							} else {

								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resAlmacenMasivo,
									'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
								);


								$this->response($respuesta);

								return;
							}

							$sqlInsertCostoCantidad = '';
							$resInsertCostoCantidad =	[];
							// SE ACTUALZAN TODOS LOS COSTOS PONDERADOS DE LOS ARTICULOS EN EL ALMACEN
							if ($ManejaLote == 1) {
								$sqlUpdateCostoCantidad = "UPDATE tbdi
														SET bdi_avgprice = :bdi_avgprice
														WHERE bdi_itemcode = :bdi_itemcode
														AND bdi_whscode = :bdi_whscode
														AND business = :business";


								$resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

									':bdi_avgprice' => $CostoNuevo,
									':bdi_itemcode' => $detail['rp1_item_code'],
									':bdi_whscode'  => $detail['rp1_whscode'],
									':business' => $Data['business']
									
								));



								if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad > 0) {
								} else {

									$this->pedeo->trans_rollback();

									$respuesta = array(
										'error'   => true,
										'data'    => $resUpdateCostoCantidad,
										'mensaje'	=> 'No se pudo crear el documento'
									);

									$this->response($respuesta);

									return;
								}
							}
						}
					}

					//SE VALIDA SI EXISTE EL LOTE

					if ($ManejaLote == 1) {
						$sqlFindLote = "SELECT ote_code FROM lote WHERE ote_code = :ote_code";
						$resFindLote = $this->pedeo->queryTable($sqlFindLote, array(':ote_code' => $detail['ote_code']));

						if (!isset($resFindLote[0])) {
							// SI NO SE HA CREADO EL LOTE SE INGRESA
							$sqlInsertLote = "INSERT INTO lote(ote_code, ote_createdate, ote_duedate, ote_createby, ote_date, ote_baseentry, ote_basetype, ote_docnum)
										VALUES(:ote_code, :ote_createdate, :ote_duedate, :ote_createby, :ote_date, :ote_baseentry, :ote_basetype, :ote_docnum)";
							$resInsertLote = $this->pedeo->insertRow($sqlInsertLote, array(

								':ote_code' => $detail['ote_code'],
								':ote_createdate' => $detail['ote_createdate'],
								':ote_duedate' => $detail['ote_duedate'],
								':ote_createby' => $Data['brp_createby'],
								':ote_date' => date('Y-m-d'),
								':ote_baseentry' => $resInsert,
								':ote_basetype' => $Data['brp_doctype'],
								':ote_docnum' => $DocNumVerificado
							));


							if (is_numeric($resInsertLote) && $resInsertLote > 0) {
							} else {
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' 		=> $resInsertLote,
									'mensaje'	=> 'No se pudo registrar el documento'
								);

								$this->response($respuesta);

								return;
							}
						}
					}
					//FIN VALIDACION DEL LOTE
					
                }
                // FIN MOVIMIENTO DE STOCK


				


				// INICIO DEL PROCESO CONTABLE

				$DetalleInventarioGrupo = new stdClass();
				$DetalleInventarioProceso = new stdClass();

				$CUENTASINV = $this->account->getAccountItem($detail['rp1_item_code'], $detail['rp1_whscode']);

				if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {
				}else{
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $CUENTASINV,
						'mensaje'	=> 'No se encontro la cuenta para el item ' . $detail['rp1_item_code']
					);

					$this->response($respuesta);

					return;
				}

				$DetalleInventarioGrupo->ccosto = isset($detail['rp1_ccost']) ? $detail['rp1_ccost'] : NULL;
				$DetalleInventarioGrupo->unegocio = isset($detail['rp1_ubusiness']) ? $detail['rp1_ubusiness'] : NULL;
				$DetalleInventarioGrupo->costo = $CostoDefinitivo;
				$DetalleInventarioGrupo->cantidad = $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
				$DetalleInventarioGrupo->cuenta = $CUENTASINV['data']['acct_inv'];

				$DetalleInventarioProceso->ccosto = isset($detail['rp1_ccost']) ? $detail['rp1_ccost'] : NULL;
				$DetalleInventarioProceso->unegocio = isset($detail['rp1_ubusiness']) ? $detail['rp1_ubusiness'] : NULL;
				$DetalleInventarioProceso->costo = $CostoDefinitivo;
				$DetalleInventarioProceso->cantidad = $this->generic->getCantInv($detail['rp1_quantity'], $CANTUOMPURCHASE, $CANTUOMSALE);
				$DetalleInventarioProceso->cuenta = $CUENTASINV['data']['acct_invproc'];

				$llaveInventarioGrupo = $DetalleInventarioGrupo->cuenta;
				$llaveInventarioProceso = $DetalleInventarioProceso->cuenta;

				// INVENTARIO DEL GRUPO
				if (in_array($llaveInventarioGrupo, $inArrayInventarioGrupo)) {

					$posicionInventarioGrupo = $this->buscarPosicion($llaveInventarioGrupo, $inArrayInventarioGrupo);
				} else {

					array_push($inArrayInventarioGrupo, $llaveInventarioGrupo);
					$posicionInventarioGrupo = $this->buscarPosicion($llaveInventarioGrupo, $inArrayInventarioGrupo);
				}

				// INVENTARIO EN PROCESO
				if (in_array($llaveInventarioProceso, $inArrayInventarioProceso)) {

					$posicionInventarioProceso = $this->buscarPosicion($llaveInventarioProceso, $inArrayInventarioProceso);
				} else {

					array_push($inArrayInventarioProceso, $llaveInventarioProceso);
					$posicionInventarioProceso = $this->buscarPosicion($llaveInventarioProceso, $inArrayInventarioProceso);
				}

				// INVENTARIO DEL GRUPO
				if (isset($DetalleConsolidadoInventarioGrupo[$posicionInventarioGrupo])) {

					if (!is_array($DetalleConsolidadoInventarioGrupo[$posicionInventarioGrupo])) {
						$DetalleConsolidadoInventarioGrupo[$posicionInventarioGrupo] = array();
					}
				} else {
					$DetalleConsolidadoInventarioGrupo[$posicionInventarioGrupo] = array();
				}

				array_push($DetalleConsolidadoInventarioGrupo[$posicionInventarioGrupo], $DetalleInventarioGrupo);
				
				// INVENTARIO DEL PROCESO
				if (isset($DetalleConsolidadoInventarioProceso[$posicionInventarioProceso])) {

					if (!is_array($DetalleConsolidadoInventarioProceso[$posicionInventarioProceso])) {
						$DetalleConsolidadoInventarioProceso[$posicionInventarioProceso] = array();
					}
				} else {
					$DetalleConsolidadoInventarioProceso[$posicionInventarioProceso] = array();
				}

				array_push($DetalleConsolidadoInventarioProceso[$posicionInventarioProceso], $DetalleInventarioProceso);
				//
            }

          	// APLICANDO LAS LINEAS DE ASIENTO

			// CUENTA INVENTARIO EN PROCESO
			foreach ($DetalleConsolidadoInventarioProceso as $key => $posicion) {
				$monto = 0;
                $montoSys = 0;
                $cuenta = 0;
                $centroCosto = '';
                $unidadNegocio = '';

				foreach ($posicion as $key => $value) {
					$centroCosto = $value->ccosto;
					$unidadNegocio = $value->unegocio;
					$monto = $monto + ( $value->costo * $value->cantidad ) ;
					$cuenta = $value->cuenta;
					
				}

				$montoSys = ($monto / $TasaLocSys);

				$AC1LINE = $AC1LINE + 1;

				// SE AGREGA AL BALANCE
				
				$BALANCE = $this->account->addBalance($periodo['data'], round($monto, $DECI_MALES), $cuenta, 2, $Data['brp_docdate'], $Data['business'], $Data['branch']);
				if (isset($BALANCE['error']) && $BALANCE['error'] == true){

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BALANCE,
						'mensaje' => $BALANCE['mensaje']
					);

					return $this->response($respuesta);
				}	

				//

				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $cuenta,
					':ac1_debit' => 0,
					':ac1_credit' => round($monto, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => round($montoSys, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['brp_doctype']) ? $Data['brp_doctype'] : 0,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => $centroCosto,
					':ac1_uncode' => $unidadNegocio,
					':ac1_prj_code' => NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['brp_createby']) ? $Data['brp_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 0,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['brp_cardcode']) ? $Data['brp_cardcode'] : NULL,
					':ac1_codref' => 0,
                    ':ac1_line' => $AC1LINE,
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));



				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {
					// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar la recepción de producción'
					);

					$this->response($respuesta);

					return;
				}
			}

			// CUENTA INVENTARIO 
			foreach ($DetalleConsolidadoInventarioProceso as $key => $posicion) {
				$monto = 0;
                $montoSys = 0;
                $cuenta = 0;
                $centroCosto = '';
                $unidadNegocio = '';

				foreach ($posicion as $key => $value) {
					$centroCosto = $value->ccosto;
					$unidadNegocio = $value->unegocio;
					$monto = $monto + ( $value->costo * $value->cantidad ) ;
					$cuenta = $value->cuenta;
					
				}

				$montoSys = ($monto / $TasaLocSys);

				$AC1LINE = $AC1LINE + 1;

				// SE AGREGA AL BALANCE

				$BALANCE = $this->account->addBalance($periodo['data'], round($monto, $DECI_MALES), $cuenta, 1, $Data['brp_docdate'], $Data['business'], $Data['branch']);
				if (isset($BALANCE['error']) && $BALANCE['error'] == true){

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error' => true,
						'data' => $BALANCE,
						'mensaje' => $BALANCE['mensaje']
					);

					return $this->response($respuesta);
				}	
				//

				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $cuenta,
					':ac1_debit' => round($monto, $DECI_MALES),
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($montoSys, $DECI_MALES),
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['brp_doctype']) ? $Data['brp_doctype'] : 0,
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => $centroCosto,
					':ac1_uncode' => $unidadNegocio,
					':ac1_prj_code' => NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['brp_createby']) ? $Data['brp_createby'] : NULL,
					':ac1_accperiod' => $periodo['data'],
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 0,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => isset($Data['brp_cardcode']) ? $Data['brp_cardcode'] : NULL,
					':ac1_codref' => 0,
                    ':ac1_line' => $AC1LINE,
					':business' => $Data['business'],
					':branch' => $Data['branch']
				));



				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {
					// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar la recepción de producción'
					);

					$this->response($respuesta);

					return;
				}
			}




			// $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
			// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(":ac1_trans_id" => $resInsertAsiento));
			// print_r(json_encode($ressqlmac1));
			// exit;



			// SE VALIDA LA CONTABILIDAD CREADA
			$validateCont = $this->generic->validateAccountingAccent($resInsertAsiento);

			
            if (isset($validateCont['error']) && $validateCont['error'] == false) {

            }else{


                $debito  = 0;
                $credito = 0;
                if ( $validateCont['data'][0]['debit_sys'] != $validateCont['data'][0]['credit_sys'] ) {

                    $sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
                    $resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array( ':business' => $Data['business'] ));

                    if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {

                        if ($validateCont['data'][0]['credit_sys'] > $validateCont['data'][0]['debit_sys']) { // DIFERENCIA EN CREDITO EL VALOR SE COLOCA EN DEBITO

                            $debito = ($validateCont['data'][0]['credit_sys'] - $validateCont['data'][0]['debit_sys']);
                        } else { // DIFERENCIA EN DEBITO EL VALOR SE COLOCA EN CREDITO

                            $credito = ($validateCont['data'][0]['debit_sys'] - $validateCont['data'][0]['credit_sys']);
                        }

                        if (round($debito + $credito, $DECI_MALES) > 0) {
                            $AC1LINE = $AC1LINE + 1;
                            $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

                                ':ac1_trans_id' => $resInsertAsiento,
                                ':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
                                ':ac1_debit' => 0,
                                ':ac1_credit' => 0,
                                ':ac1_debit_sys' => round($debito, $DECI_MALES),
                                ':ac1_credit_sys' => round($credito, $DECI_MALES),
                                ':ac1_currex' => 0,
                                ':ac1_doc_date' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
                                ':ac1_doc_duedate' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
                                ':ac1_debit_import' => 0,
                                ':ac1_credit_import' => 0,
                                ':ac1_debit_importsys' => 0,
                                ':ac1_credit_importsys' => 0,
                                ':ac1_font_key' => $resInsert,
                                ':ac1_font_line' => 1,
                                ':ac1_font_type' => is_numeric($Data['brp_doctype']) ? $Data['brp_doctype'] : 0,
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
                                ':ac1_made_user' => isset($Data['brp_createby']) ? $Data['brp_createby'] : NULL,
                                ':ac1_accperiod' => $periodo['data'],
                                ':ac1_close' => 0,
                                ':ac1_cord' => 0,
                                ':ac1_ven_debit' => 0,
                                ':ac1_ven_credit' => 0,
                                ':ac1_fiscal_acct' => 0,
                                ':ac1_taxid' => 0,
                                ':ac1_isrti' => 0,
                                ':ac1_basert' => 0,
                                ':ac1_mmcode' => 0,
                                ':ac1_legal_num' => isset($Data['brp_cardcode']) ? $Data['brp_cardcode'] : NULL,
                                ':ac1_codref' => 1,
                                ':ac1_line'   => $AC1LINE,
                                ':business' => $Data['business'],
                                ':branch' 	=> $Data['branch']
                            ));

                            if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
                                // Se verifica que el detalle no de error insertando //
                            } else {

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
                    } else {

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

            }




			// SE VALIDA LA CONTABILIDAD CREADA
			$validateCont2 = $this->generic->validateAccountingAccent2($resInsertAsiento);

	

			if (isset($validateCont2['error']) && $validateCont2['error'] == false) {
			} else {

				$ressqlmac1 = [];
				$sqlmac1 = "SELECT acc_name, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
				$ressqlmac1['contabilidad'] = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));

				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data' 	  => $ressqlmac1,
					'mensaje' => $validateCont['mensaje'],
					
				);

				$this->response($respuesta);

				return;
			}
			//
			
            // FIN VALIDACION DIFERENCIA EN DECIMALES

            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' => 'Recepción de producción registrada con exito #'.$DocNumVerificado,
            );
        } else {
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo realizar operación',
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $this->response($respuesta);
    }

    public function updateProductionReceipt_post()
    {
        $Data = $this->post();
        $respuesta = array();
        if (
            !isset($Data['brp_doctype']) or
            !isset($Data['brp_docnum']) or
            !isset($Data['brp_cardcode']) or
            !isset($Data['brp_cardname']) or
            !isset($Data['brp_duedev']) or
            !isset($Data['brp_docdate']) or
            !isset($Data['brp_ref'])
        ) {
            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'La informacion enviada no es valida',
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        // SE VERIFCA QUE EL DOCUMENTO TENGA DETALLE
        $ContenidoDetalle = json_decode($Data['detail'], true);

        if (!is_array($ContenidoDetalle)) {
            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'No se encontro el detalle de la recepción',
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        // SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
        if (!intval(count($ContenidoDetalle)) > 0) {
            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'Documento sin detalle',
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlUpdate = "UPDATE tbrp SET
         brp_doctype = :brp_doctype,
         brp_docnum = :brp_docnum,
         brp_cardcode = :brp_cardcode,
         brp_cardname = :brp_cardname,
         brp_duedev = :brp_duedev,
         brp_docdate = :brp_docdate,
         brp_ref = :brp_ref,
         brp_baseentry = :brp_baseentry,
         brp_basetype = :brp_basetype,
         brp_description = :brp_description
         WHERE brp_docentry = :brp_docentry ";

        $this->pedeo->trans_begin();

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ":brp_doctype" => $Data['brp_doctype'],
            ":brp_docnum" => $Data['brp_docnum'],
            ":brp_cardcode" => $Data['brp_cardcode'],
            ":brp_cardname" => $Data['brp_cardname'],
            ":brp_duedev" => $Data['brp_duedev'],
            ":brp_docdate" => $Data['brp_docdate'],
            ":brp_ref" => $Data['brp_ref'],
            ":brp_baseentry" => isset($Data['brp_baseentry']) ? $Data['brp_baseentry'] : 0,
            ":brp_basetype" => isset($Data['brp_basetype']) ? $Data['brp_basetype'] : 0,
            ":brp_description" => isset($Data['brp_description']) ? $Data['brp_description'] : null,
            ":brp_docentry" => $Data['brp_docentry']));

        if (is_numeric($resUpdate) and $resUpdate > 0) {

            $this->pedeo->queryTable("DELETE FROM brp1 WHERE rp1_baseentry = :brp_docentry", array(':brp_docentry' => $Data['brp_docentry']));

            $sqlInsert2 = "INSERT INTO brp1 (rp1_item_description, rp1_quantity, rp1_itemcost, rp1_im, rp1_ccost, rp1_ubusiness, rp1_item_code, rp1_listmat, rp1_baseentry, rp1_plan) values (:rp1_item_description, :rp1_quantity, :rp1_itemcost, :rp1_im, :rp1_ccost, :rp1_ubusiness, :rp1_item_code, :rp1_listmat, :rp1_baseentry, :rp1_plan)";

            foreach ($ContenidoDetalle as $key => $detail) {
                $resInsert2 = $this->pedeo->insertRow($sqlInsert2, array(
                    ":rp1_item_description" => $detail['rp1_item_description'],
                    ":rp1_quantity" => $detail['rp1_quantity'],
                    ":rp1_itemcost" => $detail['rp1_itemcost'],
                    ":rp1_im" => $detail['rp1_im'],
                    ":rp1_ccost" => $detail['rp1_ccost'],
                    ":rp1_ubusiness" => $detail['rp1_ubusiness'],
                    ":rp1_item_code" => $detail['rp1_item_code'],
                    ":rp1_listmat" => $detail['rp1_listmat'],
                    ":rp1_plan" => $detail['rp1_plan'],
                    ":rp1_baseentry" => $Data['brp_docentry'],
                ));

                if (is_numeric($resInsert2) and $resInsert2 > 0) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsert2,
                        'mensaje' => 'No se pudo realizar operación',
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }

            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resUpdate,
                'mensaje' => 'Recepción de producción actualizada con exito',
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'La informacion enviada no es valida',
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $this->response($respuesta);
    }
    //
    public function getCostPT_post()
    {

        $Data = $this->post();

        if (
            !isset($Data['bep_docentry']) or
            !isset($Data['dma_item_code'])
        ) {
            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'La informacion enviada no es valida',
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }


		$costoGeneral = 0;
		$unidaMedida = '';


		$sqlCosto = "SELECT coalesce( sum(ac1_debit) - sum(ac1_credit),0 ) AS costo FROM (SELECT ac1_debit, ac1_credit
					FROM tbof 
					INNER JOIN tbep ON tbof.bof_docentry = tbep.bep_baseentry
					INNER JOIN mac1 ON bep_docentry = ac1_font_key AND bep_doctype = mac1.ac1_font_type 
					WHERE bof_docentry = :bep_docentry) as costo";

		$sqlCosto2 = "SELECT coalesce (sum(costo2), 0) costo2 from (SELECT bof1.of1_item_code as lm1_itemcode, 
					of1_emimet as dma_emisionmethod, 
					of1_whscode, 
					coalesce(dma_uom_semb, 0) as dma_uom_semb,
					case 
						when of1_type = 2 then coalesce((of1_required_quantity * dma_uom_semb) * costo_por_almacen(of1_item_code, of1_whscode),0) 
						when of1_type = 1 then coalesce(of1_required_quantity * costo_recurso(of1_item_code::int), 0)
					end as costo2
					FROM tbof
					INNER JOIN bof1 ON tbof.bof_docentry = bof1.of1_docentry
					LEFT JOIN dmar ON of1_item_code = dma_item_code 
					WHERE bof_docentry  = :bep_docentry
					and of1_emimet = :dma_emisionmethod
					group by of1_item_code, of1_emimet, of1_whscode, 
					of1_required_quantity, bof_ccost, bof_project,of1_uom,
					of1_description, of1_costcode, of1_uom_code, of1_ing, bof_baseentry,dma_uom_semb,of1_type,of1_item_cost) as data";


		$sqlConsulta3 = "SELECT dmu_code::text as result
						from dmar
						inner join dmum on dma_uom_sale = dmu_id
						where dma_item_code = :dma_item_code";

		$resConsulta3 = $this->pedeo->queryTable($sqlConsulta3, array(
			':dma_item_code' => $Data['dma_item_code']
		));

		$resSelect1 = $this->pedeo->queryTable($sqlCosto, array(
			':bep_docentry' => $Data['bep_docentry'],
		));
			

        $resSelect2 = $this->pedeo->queryTable($sqlCosto2, array(
            ':bep_docentry' => $Data['bep_docentry'],
            ':dma_emisionmethod' => '1'
        ));


        if (isset($resSelect1[0])) {

			$costoGeneral = $resSelect1[0]['costo'];

        } 

		if (isset($resSelect2[0])) {

			$costoGeneral = $costoGeneral + $resSelect2[0]['costo2'];

        } 

		if (isset($resConsulta3[0])){
			$unidaMedida = $resConsulta3[0]['result'];
		}

		$array = [];
		$clase = new stdClass();
		$clase->result = $costoGeneral;
		array_push($array, $clase);

		$clase = new stdClass();
		$clase->result = $unidaMedida;
		array_push($array, $clase);
		
		if ( $costoGeneral > 0 ) {

			$respuesta = array(
                'error'   => false,
                'data'    => $array,
                'mensaje' => 'Busqueda sin resultados',
            );

		} else {

            $respuesta = array(
                'error'   => true,
                'data'    => '',
                'mensaje' => 'Busqueda sin resultados',
            );
        }

        $this->response($respuesta);

    }

	private function buscarPosicion($llave, $inArray)
	{
		$res = 0;
		for ($i = 0; $i < count($inArray); $i++) {
			if ($inArray[$i] == "$llave") {
				$res =  $i;
				break;
			}
		}

		return $res;
	}

	private function validateDate($fecha)
	{
		if (strlen($fecha) == 10 or strlen($fecha) > 10) {
			return true;
		} else {
			return false;
		}
	}
}
