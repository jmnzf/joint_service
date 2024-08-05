<?php
// EMISION DE PRODUCCION
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/libraries/REST_Controller.php';

use Restserver\libraries\REST_Controller;

class ProductionEmission extends REST_Controller
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
        
    }
    public function getProductionEmission_get()
    {

        $Data = $this->get();

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'busqueda sin resultados'
        );

        $sqlSelect = "SELECT * from tbep WHERE business = :business";

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

    public function getProductionEmissionDetailById_get()
    {

        $Data = $this->get();

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'busqueda sin resultados'
        );

        $sqlSelect = "SELECT * from bep1 WHERE ep1_baseentry = :ep1_baseentry";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":ep1_baseentry" => $Data['docentry']));

        if (isset($resSelect[0])) {
            $respuesta = array(
                'error' => false,
                'data' => $resSelect,
                'mensaje' => '',
            );
        }

        $this->response($respuesta);
    }
    
    public function setProductionEmission_post()
    {
        $Data = $this->post();

        $DocNumVerificado = 0;


        $DECI_MALES =  $this->generic->getDecimals();

        $DetalleMateriales = new stdClass();
        $DetalleRecursos = new stdClass();
        $DetalleConsolidadoRecursos = [];
        $DetalleConsolidadoMateriales = [];

        $inArrayRecursos = array();
        $inArrayMateriales = array();

        $llaveRecursos = "";
        $llaveMateriales = "";

        $posicionRecursos = 0;
        $posicionMateriales = 0;
        $AC1LINE = 1;

        $ManejaInvetario = 0;
        $ManejaUbicacion = 0;
        $ManejaSerial = 0;
        $ManejaLote = 0;

        $totalCP2 = 0; 

        $CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA COMPRAS
		$CANTUOMSALE = 0; // CANTIDAD EN UNIDAD DE MEDIDA VENTAS

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
            !isset($Data['bep_doctype']) or
            !isset($Data['bep_docnum']) or
            !isset($Data['bep_cardcode']) or
            !isset($Data['bep_cardname']) or
            !isset($Data['bep_duedev']) or
            !isset($Data['bep_docdate']) or
            !isset($Data['bep_ref'])
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
                'mensaje' => 'No se encontro el detalle de la entrada de la emisión',
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
        $periodo = $this->generic->ValidatePeriod($Data['bep_duedev'], $Data['bep_docdate'],$Data['bep_duedev'],1);

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
		$dataTasa = $this->tasa->Tasa($currency, $Data['bep_docdate']);

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
        $DocNumVerificado = $this->documentnumbering->NumberDoc($Data['bep_serie'], $Data['bep_docdate'], $Data['bep_docdate']);

        if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0) {

        } else if ($DocNumVerificado['error']) {

            return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
        }

        $sqlInsert = "INSERT INTO tbep ( bep_doctype, bep_docnum, bep_cardcode, bep_cardname, bep_duedev, bep_docdate, bep_ref, bep_serie, bep_baseentry, bep_basetype, bep_description, bep_createat, bep_createby, bep_status, business) VALUES(:bep_doctype, :bep_docnum, :bep_cardcode, :bep_cardname, :bep_duedev, :bep_docdate, :bep_ref, :bep_serie, :bep_baseentry, :bep_basetype, :bep_description, :bep_createat, :bep_createby, :bep_status, :business)";

        $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array (
            ":bep_doctype" => $Data['bep_doctype'],
            ":bep_docnum" => $DocNumVerificado,
            ":bep_cardcode" => $Data['bep_cardcode'],
            ":bep_cardname" => $Data['bep_cardname'],
            ":bep_duedev" => $Data['bep_duedev'],
            ":bep_docdate" => $Data['bep_docdate'],
            ":bep_ref" => $Data['bep_ref'],
            ":bep_serie" => $Data['bep_serie'],
            ":bep_baseentry" => is_numeric($Data['bep_baseentry']) ? $Data['bep_baseentry'] : 0,
            ":bep_basetype" => is_numeric($Data['bep_basetype']) ? $Data['bep_basetype'] : 0,
            ":bep_description" => isset($Data['bep_description']) ? $Data['bep_description'] : null,
            ":bep_createat" => isset($Data['bep_createat']) ? $Data['bep_createat'] : null,
            ":bep_createby" => isset($Data['bep_createby']) ? $Data['bep_createby'] : null,
            ":bep_status" => isset($Data['bep_status']) ? $Data['bep_status'] : 0,
            ":business" => $Data['business']
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {

            // Se actualiza la serie de la numeracion del documento

            $sqlActualizarNumeracion = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum WHERE pgs_id = :pgs_id";
            $resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
                ':pgs_nextnum' => $DocNumVerificado,
                ':pgs_id' => $Data['bep_serie'],
            ));

            if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {

            } else {
                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error' => true,
                    'data' => $resActualizarNumeracion,
                    'mensaje' => 'No se pudo crear la factura de compras',
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
                ':bed_doctype' => $Data['bep_doctype'],
                ':bed_status' => 3, // Estado cerrado
                ':bed_createby' => $Data['bep_createby'],
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
                    'mensaje' => 'No se pudo registrar la emisión de fabricación',
                );

                return $this->response($respuesta);

            }

            // SE AGREGA LA CABECERA DEL ASIENTO CONTABLE
            $sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch, mac_accperiod)
                                VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch, :mac_accperiod)";


            $resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

                ':mac_doc_num' => 1,
                ':mac_status' => 1,
                ':mac_base_type' => is_numeric($Data['bep_doctype'])?$Data['bep_doctype']:0,
                ':mac_base_entry' => $resInsert,
                ':mac_doc_date' => $this->validateDate($Data['bep_docdate'])?$Data['bep_docdate']:NULL,
                ':mac_doc_duedate' => $this->validateDate($Data['bep_docdate'])?$Data['bep_docdate']:NULL,
                ':mac_legal_date' => $this->validateDate($Data['bep_duedev'])?$Data['bep_duedev']:NULL,
                ':mac_ref1' => is_numeric($Data['bep_doctype'])?$Data['bep_doctype']:0,
                ':mac_ref2' => "",
                ':mac_ref3' => "",
                ':mac_loc_total' => 0,
                ':mac_fc_total' => 0,
                ':mac_sys_total' => 0,
                ':mac_trans_dode' => 1,
                ':mac_beline_nume' => 1,
                ':mac_vat_date' => $this->validateDate($Data['bep_docdate'])?$Data['bep_docdate']:NULL,
                ':mac_serie' => 1,
                ':mac_number' => 1,
                ':mac_bammntsys' => 0,
                ':mac_bammnt' => 0,
                ':mac_wtsum' => 1,
                ':mac_vatsum' => 0,
                ':mac_comments' => isset($Data['bep_description'])?$Data['bep_description']:NULL,
                ':mac_create_date' => $this->validateDate($Data['bep_createat'])?$Data['bep_createat']:NULL,
                ':mac_made_usuer' => isset($Data['bep_createby'])?$Data['bep_createby']:NULL,
                ':mac_update_date' => date("Y-m-d"),
                ':mac_update_user' => isset($Data['bep_createby'])?$Data['bep_createby']:NULL,
                ':business'	  => $Data['business'],
                ':branch' 	  => $Data['branch'],
                ':mac_accperiod' => $periodo['data'],
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




            $sqlInsert2 = "INSERT INTO bep1 (ep1_item_description, ep1_quantity, ep1_itemcost, ep1_im, ep1_ccost, ep1_ubusiness, ep1_item_code, ep1_listmat, ep1_baseentry, ep1_plan, ep1_basenum, ep1_item_cost, ep1_type, ep1_uom, ep1_whscode) values (:ep1_item_description, :ep1_quantity, :ep1_itemcost, :ep1_im, :ep1_ccost, :ep1_ubusiness, :ep1_item_code, :ep1_listmat, :ep1_baseentry, :ep1_plan, :ep1_basenum, :ep1_item_cost, :ep1_type, :ep1_uom, :ep1_whscode)";

            foreach ($ContenidoDetalle as $key => $detail) {

                if ( isset($detail['ep1_type']) && !empty($detail['ep1_type']) && $detail['ep1_type'] == 2 ) {

                    $CANTUOMPURCHASE = $this->generic->getUomPurchase($detail['ep1_item_code']);
                    $CANTUOMSALE = $this->generic->getUomSale($detail['ep1_item_code']);
    
    
                    if ($CANTUOMPURCHASE == 0 || $CANTUOMSALE == 0) {
    
                        $this->pedeo->trans_rollback();
    
                        $respuesta = array(
                            'error'   => true,
                            'data' 		=> $detail['ep1_item_code'],
                            'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['ep1_item_code']
                        );
    
                        $this->response($respuesta);
    
                        return;
                    }

                }else{
                    $CANTUOMPURCHASE = 0;
                    $CANTUOMSALE =  0;
                }

                

                $resInsert2 = $this->pedeo->insertRow($sqlInsert2, array(
                    ":ep1_item_description" => $detail['ep1_item_description'],
                    ":ep1_quantity" => $detail['ep1_quantity'],
                    ":ep1_itemcost" => is_numeric($detail['ep1_itemcost']) ? $detail['ep1_itemcost'] : 0,
                    ":ep1_im" => is_numeric($detail['ep1_im']) ? $detail['ep1_im'] : 0,
                    ":ep1_ccost" => $detail['ep1_ccost'],
                    ":ep1_ubusiness" => $detail['ep1_ubusiness'],
                    ":ep1_item_code" => $detail['ep1_item_code'],
                    ":ep1_listmat" => $detail['ep1_listmat'],
                    ":ep1_baseentry" => $resInsert,
                    ":ep1_plan" => is_numeric($detail['ep1_plan']) ? $detail['ep1_plan'] : 0,
                    ":ep1_item_cost" => (isset($detail['ep1_item_cost'])) ? $detail['ep1_item_cost'] : null,
                    ":ep1_basenum" => $detail['ep1_basenum'],
                    ":ep1_type" => isset($detail['ep1_type']) ? $detail['ep1_type'] : null,
                    ":ep1_uom"  => isset($detail['ep1_uom']) ? $detail['ep1_uom'] : null,
                    ":ep1_whscode" => isset($detail['ep1_whscode']) ? $detail['ep1_whscode'] : null
                ));

                if (is_numeric($resInsert2) and $resInsert2 > 0) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsert2,
                        'mensaje' => 'No se pudo realizar operacion',
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }

                if ( isset($detail['ep1_type']) && !empty($detail['ep1_type']) && $detail['ep1_type'] == 2 ) {
                    // MOVIMIENTO DE INVENTARIO
                    $sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
                    $resItemINV = $this->pedeo->queryTable($sqlItemINV, array(
    
                        ':dma_item_code' => $detail['ep1_item_code'],
                        ':dma_item_inv'  => 1
                    ));
    
                    if (isset($resItemINV[0])) {
    
    
                        // CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
                        $sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
                        $resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
                            ':dws_ubication' => 1,
                            ':dws_code' => $detail['ep1_item_code'],
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
        
                            ':dma_item_code' => $detail['ep1_item_code'],
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

                            ':dma_item_code' => $detail['ep1_item_code'],
                            ':dma_series_code'  => 1
                        ));

                        if (isset($resItemSerial[0])) {
                            $ManejaSerial = 1;

                            if (!isset($detail['serials'])) {

                                $this->pedeo->trans_rollback();

                                $respuesta = array(
                                    'error'   => true,
                                    'data'    => [],
                                    'mensaje' => 'No se encontraron los seriales para el articulo: ' . $detail['ep1_item_code']
                                );

                                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                                return;
                            }

                            $AddSerial = $this->generic->addSerial($detail['serials'], $detail['ep1_item_code'], $Data['bep_doctype'], $resInsert, $DocNumVerificado, $Data['isi_docdate'], 2, $Data['bep_description'], $detail['ep1_whscode'], $detail['ep1_quantity'], $Data['bep_createby'], $Data['business']);

                            if (isset($AddSerial['error']) && $AddSerial['error'] == false) {
                            } else {

                                $this->pedeo->trans_rollback();

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

                        // se busca el costo del item en el momento de la creacion del documento de venta
                        // para almacenar en el movimiento de inventario
                        // SI EL ARTICULO MANEJA LOTE SE BUSCA POR LOTE Y ALMACEN
                        $sqlCostoMomentoRegistro = '';
                        $resCostoMomentoRegistro = [];

                        // SI EL ALMACEN MANEJA UBICACION
                        if ( $ManejaUbicacion == 1 ){
                            // SI EL ARTICULO MANEJA LOTE
                            if ($ManejaLote == 1) {
                                $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND bdi_ubication = :bdi_ubication AND business = :business";
                                $resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                                    ':bdi_whscode'   => $detail['ep1_whscode'],
                                    ':bdi_itemcode'  => $detail['ep1_item_code'],
                                    ':bdi_lote'      => $detail['ote_code'],
                                    ':bdi_ubication' => $detail['ep1_ubication'],
                                    ':business' 	 => $Data['business']
                                ));
                            } else {
                                $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
                                $resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                                    ':bdi_whscode'   => $detail['ep1_whscode'],
                                    ':bdi_itemcode'  => $detail['ep1_item_code'],
                                    ':bdi_ubication' => $detail['ep1_ubication'],
                                    ':business' 	 => $Data['business']
                                ));
                            }
                        }else{
                            // SI EL ARTICULO MANEJA LOTE
                            if ($ManejaLote == 1) {
                                $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
                                $resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                                    ':bdi_whscode'  => $detail['ep1_whscode'],
                                    ':bdi_itemcode' => $detail['ep1_item_code'],
                                    ':bdi_lote' 	=> $detail['ote_code'],
                                    ':business' 	=> $Data['business']
                                ));
                            } else {
                                $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
                                $resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                                    ':bdi_whscode' 	=> $detail['ep1_whscode'],
                                    ':bdi_itemcode' => $detail['ep1_item_code'],
                                    ':business' 	=> $Data['business']
                                ));
                            }
                        }

                        if (isset($resCostoMomentoRegistro[0])) {


                            //VALIDANDO CANTIDAD DE ARTICULOS
    
                            $CANT_ARTICULOEX = $resCostoMomentoRegistro[0]['bdi_quantity'];
                            $CANT_ARTICULOLN = $detail['ep1_quantity'] * $CANTUOMSALE;
    
                            if (($CANT_ARTICULOEX - $CANT_ARTICULOLN) < 0) {
    
                                $this->pedeo->trans_rollback();
    
                                $respuesta = array(
                                    'error'   => true,
                                    'data' => [],
                                    'mensaje'	=> 'no puede crear el documento porque el articulo ' . $detail['ep1_item_code'] . ' recae en inventario negativo (' . ($CANT_ARTICULOEX - $CANT_ARTICULOLN) . ')'
                                );
    
                                $this->response($respuesta);
    
                                return;
                            }
    
                            //VALIDANDO CANTIDAD DE ARTICULOS
    
                            $sqlInserMovimiento = '';
                            $resInserMovimiento = [];
    
                        
                            //Se aplica el movimiento de inventario
                            $sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment, bmi_lote, bmi_ubication,business)
                                                    VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote, :bmi_ubication,:business)";
    
                            $resInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(
    
                                ':bmi_itemcode' => isset($detail['ep1_item_code']) ? $detail['ep1_item_code'] : NULL,
                                ':bmi_quantity' => ($detail['ep1_quantity'] * $CANTUOMSALE) * -1,
                                ':bmi_whscode'  => isset($detail['ep1_whscode']) ? $detail['ep1_whscode'] : NULL,
                                ':bmi_createat' => $this->validateDate($Data['bep_createat']) ? $Data['bep_createat'] : NULL,
                                ':bmi_createby' => isset($Data['bep_createby']) ? $Data['bep_createby'] : NULL,
                                ':bmy_doctype'  => is_numeric($Data['bep_doctype']) ? $Data['bep_doctype'] : 0,
                                ':bmy_baseentry' => $resInsert,
                                ':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
                                ':bmi_currequantity' => $resCostoMomentoRegistro[0]['bdi_quantity'],
                                ':bmi_basenum'	=> $DocNumVerificado,
                                ':bmi_docdate' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
                                ':bmi_duedate' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
                                ':bmi_duedev'  => $this->validateDate($Data['bep_duedev']) ? $Data['bep_duedev'] : NULL,
                                ':bmi_comment' => isset($Data['bep_description']) ? $Data['bep_description'] : NULL,
                                ':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
                                ':bmi_ubication' => isset($detail['bep_ubication']) ? $detail['bep_ubication'] : NULL,
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
                                    'mensaje'	=> 'No se pudo registra la salida de inventario'
                                );
    
                                $this->response($respuesta);
    
                                return;
                            }
                        } else {
    
                            $this->pedeo->trans_rollback();
    
                            $respuesta = array(
                                'error'   => true,
                                'data' => $resCostoMomentoRegistro,
                                'mensaje'	=> 'No se pudo relizar el movimiento, no se encontro el costo del articulo #'.$detail['ep1_item_code']
                            );
    
                            $this->response($respuesta);
    
                            return;
                        }

                        //Se Aplica el movimiento en stock ***************
                        // Buscando item en el stock
                        //SE VALIDA SI EL ARTICULO MANEJA LOTE
                        $sqlCostoCantidad = '';
                        $resCostoCantidad = [];

                        // SI EL ALMACEN MANEJA UBICACION
                        if ( $ManejaUbicacion == 1 ){
                            // SI EL ARTICULO MANEJA LOTE
                            if ($ManejaLote == 1) {
                                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                                                FROM tbdi
                                                WHERE bdi_itemcode = :bdi_itemcode
                                                AND bdi_whscode = :bdi_whscode
                                                AND bdi_lote = :bdi_lote
                                                AND bdi_ubication = :bdi_ubication
                                                AND business = :business";
        
                                $resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
        
                                    ':bdi_itemcode'  => $detail['ep1_item_code'],
                                    ':bdi_whscode'   => $detail['ep1_whscode'],
                                    ':bdi_lote'		 => $detail['ote_code'],
                                    ':bdi_ubication' => $detail['ep1_ubication'],
                                    ':business' 	 => $Data['business']
                                ));
                            } else {
                                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                                FROM tbdi
                                WHERE bdi_itemcode = :bdi_itemcode
                                AND bdi_whscode = :bdi_whscode
                                AND bdi_ubication = :bdi_ubication
                                AND business = :business";

                                $resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

                                    ':bdi_itemcode'  => $detail['ep1_item_code'],
                                    ':bdi_whscode'   => $detail['ep1_whscode'],
                                    ':bdi_ubication' => $detail['ep1_ubication'],
                                    ':business' 	 => $Data['business']
                                ));
                            }
                        }else{
                            // SI EL ARTICULO MANEJA LOTE
                            if ($ManejaLote == 1) {
                                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                                                FROM tbdi
                                                WHERE bdi_itemcode = :bdi_itemcode
                                                AND bdi_whscode = :bdi_whscode
                                                AND bdi_lote = :bdi_lote
                                                AND business = :business";
        
                                $resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
        
                                    ':bdi_itemcode' => $detail['ep1_item_code'],
                                    ':bdi_whscode'  => $detail['ep1_whscode'],
                                    ':bdi_lote'		=> $detail['ote_code'],
                                    ':business' 	=> $Data['business']
                                ));
                            } else {
                                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                                                FROM tbdi
                                                WHERE bdi_itemcode = :bdi_itemcode
                                                AND bdi_whscode = :bdi_whscode
                                                AND business = :business";
        
                                $resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(
        
                                    ':bdi_itemcode' => $detail['ep1_item_code'],
                                    ':bdi_whscode'  => $detail['ep1_whscode'],
                                    ':business' => $Data['business']
                                ));
                            }
                        }


                        if (isset($resCostoCantidad[0])) {

                            $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
                            $CostoActual    = $resCostoCantidad[0]['bdi_avgprice'];

                            $CantidadDevolucion = $detail['ep1_quantity'] * $CANTUOMSALE;
                            $CostoDevolucion = $detail['ep1_itemcost'];

                            $CantidadTotal = ($CantidadActual - $CantidadDevolucion);

                            // $CostoPonderado = (($CostoActual * $CantidadActual) + ($CostoDevolucion * $CantidadDevolucion)) / $CantidadTotal;
                            // NO SE MUEVE EL COSTO PONDERADO
                            $sqlUpdateCostoCantidad =  "UPDATE tbdi
                                                    SET bdi_quantity = :bdi_quantity
                                                    WHERE  bdi_id = :bdi_id";

                            $resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

                                ':bdi_quantity' => $CantidadTotal,
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
                        } else {

                            $this->pedeo->trans_rollback();

                            $respuesta = array(
                                'error'   => true,
                                'data' 		=> $resCostoCantidad,
                                'mensaje'	=> 'El item no existe en el stock ' . $detail['ep1_item_code']
                            );

                            $this->response($respuesta);

                            return;
                        }


                    }
    
                }
                
                // AGRUPANDO DATOS PARA LAS LINEAS DE ASIENTOS
                //
                $acct_invproc = 0;


                $sqlItemLm = "SELECT rlm_item_code, rlm_whscode FROM prlm WHERE rlm_id = :rlm_id";
                $resItemLm = $this->pedeo->queryTable($sqlItemLm, array(":rlm_id" => $detail['ep1_listmat']));

                if ( isset($resItemLm[0]) ) {

                }else{
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'	  => $resItemLm,
                        'mensaje' => 'No se encontro la lista de material #: '.$detail['ep1_listmat']
                    );

                    $this->response($respuesta);

                    return;
                }



                $CUENTASINV = $this->account->getAccountItem($resItemLm[0]['rlm_item_code'], $resItemLm[0]['rlm_whscode']);

                if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

                    $acct_invproc = $CUENTASINV['data']['acct_invproc'];

                } else {

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'	  => $CUENTASINV,
                        'mensaje'	=> 'No se encontro la cuenta para el item ' . $detail['ep1_item_code']
                    );

                    $this->response($respuesta);

                    return;
                }


                if ( isset($detail['ep1_type']) && !empty($detail['ep1_type']) && $detail['ep1_type'] == 2 ) {

                    $DetalleMateriales = new stdClass();

                    $cuentaInv = 0;

                    $CUENTASINV = $this->account->getAccountItem($detail['ep1_item_code'], $detail['ep1_whscode']);

                    if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {
    
                        $cuentaInv = $CUENTASINV['data']['acct_inv'];
    
                    } else {
    
                        $this->pedeo->trans_rollback();
    
                        $respuesta = array(
                            'error'   => true,
                            'data'	  => $CUENTASINV,
                            'mensaje'	=> 'No se encontro la cuenta para el item ' . $detail['ep1_item_code']
                        );
    
                        $this->response($respuesta);
    
                        return;
                    }
    
                   
                  

                    $DetalleMateriales->ccosto = isset($detail['ep1_ccost']) ? $detail['ep1_ccost'] : NULL;
                    $DetalleMateriales->unegocio = isset($detail['ep1_ubusiness']) ? $detail['ep1_ubusiness'] : NULL;
                    $DetalleMateriales->totallinea = ( ( $detail['ep1_quantity'] * $CANTUOMSALE ) * $detail['ep1_itemcost'] );
                    $DetalleMateriales->itemcode = isset($detail['ep1_item_code']) ? $detail['ep1_item_code'] : NULL;
                    $DetalleMateriales->whscode = isset($detail['ep1_whscode']) ? $detail['ep1_whscode'] : NULL;
                    $DetalleMateriales->cuenta = $cuentaInv;

                  

                    $totalCP2 = $totalCP2 + $DetalleMateriales->totallinea;

                    $llaveMateriales = $DetalleMateriales->cuenta;

                    if (in_array($llaveMateriales, $inArrayMateriales)) {

                        $posicionMateriales = $this->buscarPosicion($llaveMateriales, $inArrayMateriales);
                    } else {
    
                        array_push($inArrayMateriales, $llaveMateriales);
                        $posicionMateriales = $this->buscarPosicion($llaveMateriales, $inArrayMateriales);
                    }

                    if (isset($DetalleConsolidadoMateriales[$posicionMateriales])) {

                        if (!is_array($DetalleConsolidadoMateriales[$posicionMateriales])) {
                            $DetalleConsolidadoMateriales[$posicionMateriales] = array();
                        }
                    } else {
                        $DetalleConsolidadoMateriales[$posicionMateriales] = array();
                    }
    
                    array_push($DetalleConsolidadoMateriales[$posicionMateriales], $DetalleMateriales);
                   

                } else if ( isset($detail['ep1_type']) && !empty($detail['ep1_type']) && $detail['ep1_type'] == 1 ) {
                    
                    $sqlConcepto = "SELECT cdrs.*, dcpr.*
                                FROM dmrp
                                INNER JOIN dcpr ON dmrp.mrp_id = dcpr.crp_id_recurso
                                INNER JOIN cdrs ON dcpr.crp_id_concepto = cdrs.drs_id WHERE dmrp.business = :business AND mrp_id = :mrp_id";

                    $resConcepto = $this->pedeo->queryTable($sqlConcepto, array(
                        'business'  => $Data['business'],
                        'mrp_id'	=> $detail['ep1_item_code']
                    ));

                    if ( isset($resConcepto[0]) ) {

                        foreach ($resConcepto as $key => $conc) {

                            $DetalleRecursos = new stdClass();

                            $DetalleRecursos->ccosto = isset($detail['ep1_ccost']) ? $detail['ep1_ccost'] : NULL;
                            $DetalleRecursos->unegocio = isset($detail['ep1_ubusiness']) ? $detail['ep1_ubusiness'] : NULL;
                            $DetalleRecursos->totallinea = ( $detail['ep1_quantity'] * $conc['crp_valor'] );
                            $DetalleRecursos->itemcode = isset($detail['ep1_item_code']) ? $detail['ep1_item_code'] : NULL;
                            $DetalleRecursos->whscode = isset($detail['ep1_whscode']) ? $detail['ep1_whscode'] : NULL;
                            $DetalleRecursos->cuenta =  $conc['drs_cuentacontable'];
        
                            $llaveRecursos =  $DetalleRecursos->cuenta;
        
                            if (in_array($llaveRecursos, $inArrayRecursos)) {
        
                                $posicionRecursos = $this->buscarPosicion($llaveRecursos, $inArrayRecursos);
                            } else {
            
                                array_push($inArrayRecursos, $llaveRecursos);
                                $posicionRecursos = $this->buscarPosicion($llaveRecursos, $inArrayRecursos);
                            }
        
                            if (isset($DetalleConsolidadoRecursos[$posicionRecursos])) {
        
                                if (!is_array($DetalleConsolidadoRecursos[$posicionRecursos])) {
                                    $DetalleConsolidadoRecursos[$posicionRecursos] = array();
                                }
                            } else {
                                $DetalleConsolidadoRecursos[$posicionRecursos] = array();
                            }
            
                            array_push($DetalleConsolidadoRecursos[$posicionRecursos], $DetalleRecursos);    
                        }

                    } else {

                        $this->pedeo->trans_rollback();

                        $respuesta = array(
                            'error'   => true,
                            'data'	  => $resConcepto,
                            'mensaje' => 'No se encontro el concepto para el recurso: '. $detail['ep1_item_code']
                        );

                        $this->response($respuesta);

                        return;
                    }
                }

            }


            // APLICANDO LAS LINEAS DE ASIENTO
            // ASIENTO DE RECURSOS
            // 
            $totalCP = 0;
            foreach ($DetalleConsolidadoRecursos as $key => $posicion) {
				$monto = 0;
                $montoSys = 0;
                $cuenta = 0;
                $centroCosto = '';
                $unidadNegocio = '';
				foreach ($posicion as $key => $value) {
                
					$cuenta = $value->cuenta;
					$monto = ($monto + $value->totallinea);
                    $centroCosto = $value->ccosto;
                    $unidadNegocio = $value->unegocio;

                    $totalCP = ($totalCP + $value->totallinea);
				}


                $montoSys = ($monto / $TasaLocSys);

                // SE AGREGA AL BALANCE
              
                $BALANCE = $this->account->addBalance($periodo['data'], round($monto, $DECI_MALES), $cuenta, 2, $Data['bep_docdate'], $Data['business'], $Data['branch']);
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

                $AC1LINE = $AC1LINE + 1;

                $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $cuenta,
					':ac1_debit' => 0,
					':ac1_credit' => round($monto, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => round($montoSys, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['bep_doctype']) ? $Data['bep_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['bep_createby']) ? $Data['bep_createby'] : NULL,
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
					':ac1_legal_num' => isset($Data['bep_cardcode']) ? $Data['bep_cardcode'] : NULL,
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
						'mensaje'	=> 'No se pudo registrar la emisión de producción'
					);

					$this->response($respuesta);

					return;
				}
            }

            // ASIENTO PARA LA CONTRA PARTIDA DE RECURSOS
            // if ($totalCP > 0) {

            //     $totalCPSYS = ($totalCP / $TasaLocSys);
            //     $AC1LINE = $AC1LINE + 1;

            //     // SE AGREGA AL BALANCE
              
            //     $BALANCE = $this->account->addBalance($periodo['data'], round($totalCP, $DECI_MALES), $acct_invproc, 1, $Data['bep_docdate'], $Data['business'], $Data['branch']);
            //     if (isset($BALANCE['error']) && $BALANCE['error'] == true){

            //         $this->pedeo->trans_rollback();

            //         $respuesta = array(
            //             'error' => true,
            //             'data' => $BALANCE,
            //             'mensaje' => $BALANCE['mensaje']
            //         );

            //         return $this->response($respuesta);
            //     }
            //     //

            //     $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

            //         ':ac1_trans_id' => $resInsertAsiento,
            //         ':ac1_account' =>  $acct_invproc,
            //         ':ac1_debit' => round($totalCP, $DECI_MALES),
            //         ':ac1_credit' => 0,
            //         ':ac1_debit_sys' => round($totalCPSYS, $DECI_MALES),
            //         ':ac1_credit_sys' => 0,
            //         ':ac1_currex' => 0,
            //         ':ac1_doc_date' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
            //         ':ac1_doc_duedate' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
            //         ':ac1_debit_import' => 0,
            //         ':ac1_credit_import' => 0,
            //         ':ac1_debit_importsys' => 0,
            //         ':ac1_credit_importsys' => 0,
            //         ':ac1_font_key' => $resInsert,
            //         ':ac1_font_line' => 1,
            //         ':ac1_font_type' => is_numeric($Data['bep_doctype']) ? $Data['bep_doctype'] : 0,
            //         ':ac1_accountvs' => 1,
            //         ':ac1_doctype' => 18,
            //         ':ac1_ref1' => "",
            //         ':ac1_ref2' => "",
            //         ':ac1_ref3' => "",
            //         ':ac1_prc_code' => $centroCosto,
            //         ':ac1_uncode' => $unidadNegocio,
            //         ':ac1_prj_code' => NULL,
            //         ':ac1_rescon_date' => NULL,
            //         ':ac1_recon_total' => 0,
            //         ':ac1_made_user' => isset($Data['bep_createby']) ? $Data['bep_createby'] : NULL,
            //         ':ac1_accperiod' => $periodo['data'],
            //         ':ac1_close' => 0,
            //         ':ac1_cord' => 0,
            //         ':ac1_ven_debit' => 0,
            //         ':ac1_ven_credit' => 0,
            //         ':ac1_fiscal_acct' => 0,
            //         ':ac1_taxid' => 0,
            //         ':ac1_isrti' => 0,
            //         ':ac1_basert' => 0,
            //         ':ac1_mmcode' => 0,
            //         ':ac1_legal_num' => isset($Data['bep_cardcode']) ? $Data['bep_cardcode'] : NULL,
            //         ':ac1_codref' => 0,
            //         ':ac1_line' => $AC1LINE,
            //         ':business' => $Data['business'],
            //         ':branch' => $Data['branch']
            //     ));
    
    
    
            //     if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
            //         // Se verifica que el detalle no de error insertando //
            //     } else {
            //         // si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
            //         // se retorna el error y se detiene la ejecucion del codigo restante.
            //         $this->pedeo->trans_rollback();
    
            //         $respuesta = array(
            //             'error'   => true,
            //             'data'	  => $resDetalleAsiento,
            //             'mensaje'	=> 'No se pudo registrar la emisión de producción'
            //         );
    
            //         $this->response($respuesta);
    
            //         return;
            //     }
            // }


            // ASIENTO PARA MATERIALES
            
            foreach ($DetalleConsolidadoMateriales as $key => $posicion) {
				$monto = 0;
                $montoSys = 0;
                $cuenta = 0;
                $centroCosto = '';
                $unidadNegocio = '';
				foreach ($posicion as $key => $value) {

                 
                    $cuenta = $value->cuenta;
                    $monto = ($monto + $value->totallinea);
                    $centroCosto = $value->ccosto;
                    $unidadNegocio = $value->unegocio;


				}


                $montoSys = ($monto / $TasaLocSys);
                $AC1LINE = $AC1LINE + 1;

                // SE AGREGA AL BALANCE

                $BALANCE = $this->account->addBalance($periodo['data'], round($monto, $DECI_MALES), $cuenta, 2, $Data['bep_docdate'], $Data['business'], $Data['branch']);
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
					':ac1_doc_date' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
					':ac1_doc_duedate' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resInsert,
					':ac1_font_line' => 1,
					':ac1_font_type' => is_numeric($Data['bep_doctype']) ? $Data['bep_doctype'] : 0,
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
					':ac1_made_user' => isset($Data['bep_createby']) ? $Data['bep_createby'] : NULL,
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
					':ac1_legal_num' => isset($Data['bep_cardcode']) ? $Data['bep_cardcode'] : NULL,
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
						'mensaje'	=> 'No se pudo registrar la emisión de producción'
					);

					$this->response($respuesta);

					return;
				}
            }

            // ASIENTO PARA INVENTARIO EN PROCESO CONTRA PARTIDA DE MATERIALES Y RECURSOS
            if ($totalCP2 + $totalCP > 0){

                $totalCPSYS2 =  ($totalCP2 + $totalCP) / $TasaLocSys;
                $AC1LINE = $AC1LINE + 1;

                
                // SE AGREGA AL BALANCE

                $BALANCE = $this->account->addBalance($periodo['data'], round( ($totalCP2 + $totalCP), $DECI_MALES), $acct_invproc, 1, $Data['bep_docdate'], $Data['business'], $Data['branch']);
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
                    ':ac1_account' => $acct_invproc,
                    ':ac1_debit' => round( ($totalCP2 + $totalCP), $DECI_MALES),
                    ':ac1_credit' => 0,
                    ':ac1_debit_sys' => round($totalCPSYS2, $DECI_MALES),
                    ':ac1_credit_sys' => 0,
                    ':ac1_currex' => 0,
                    ':ac1_doc_date' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
                    ':ac1_doc_duedate' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
                    ':ac1_debit_import' => 0,
                    ':ac1_credit_import' => 0,
                    ':ac1_debit_importsys' => 0,
                    ':ac1_credit_importsys' => 0,
                    ':ac1_font_key' => $resInsert,
                    ':ac1_font_line' => 1,
                    ':ac1_font_type' => is_numeric($Data['bep_doctype']) ? $Data['bep_doctype'] : 0,
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
                    ':ac1_made_user' => isset($Data['bep_createby']) ? $Data['bep_createby'] : NULL,
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
                    ':ac1_legal_num' => isset($Data['bep_cardcode']) ? $Data['bep_cardcode'] : NULL,
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
                        'mensaje'	=> 'No se pudo registrar la emisión de producción'
                    );
    
                    $this->response($respuesta);
    
                    return;
                }
            }


            // $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
            // $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
            // print_r(json_encode($ressqlmac1));
            // exit;


            // SE VALIDA DIFERENCIA POR DECIMALES
            // Y SE AGREGA UN ASIENTO DE DIFERENCIA EN DECIMALES
            // AJUSTE AL PESO
            // SEGUN SEA EL CASO
            //SE VALIDA LA CONTABILIDAD CREADA
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
                                ':ac1_doc_date' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
                                ':ac1_doc_duedate' => $this->validateDate($Data['bep_docdate']) ? $Data['bep_docdate'] : NULL,
                                ':ac1_debit_import' => 0,
                                ':ac1_credit_import' => 0,
                                ':ac1_debit_importsys' => 0,
                                ':ac1_credit_importsys' => 0,
                                ':ac1_font_key' => $resInsert,
                                ':ac1_font_line' => 1,
                                ':ac1_font_type' => is_numeric($Data['bep_doctype']) ? $Data['bep_doctype'] : 0,
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
                                ':ac1_made_user' => isset($Data['bep_createby']) ? $Data['bep_createby'] : NULL,
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
                                ':ac1_legal_num' => isset($Data['bep_cardcode']) ? $Data['bep_cardcode'] : NULL,
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

            // FIN VALIDACION DIFERENCIA EN DECIMALES

 

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


            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' => 'Emision de producción registrada con exito',
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

    public function updateProductionEmission_post()
    {
        $Data = $this->post();
        $respuesta = array();

        if (
            !isset($Data['bep_docentry']) or
            !isset($Data['bep_doctype']) or
            !isset($Data['bep_docnum']) or
            !isset($Data['bep_cardcode']) or
            !isset($Data['bep_cardname']) or
            !isset($Data['bep_duedev']) or
            !isset($Data['bep_docdate']) or
            !isset($Data['bep_serie']) or
            !isset($Data['bep_cardname']) or
            !isset($Data['bep_ref'])
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

        $sqlUpdate = "UPDATE tbep SET
                    bep_doctype = :bep_doctype,
                    bep_docnum = :bep_docnum,
                    bep_cardcode = :bep_cardcode,
                    bep_duedev = :bep_duedev,
                    bep_docdate = :bep_docdate,
                    bep_ref = :bep_ref,
                    bep_serie = :bep_serie,
                    bep_cardname = :bep_cardname,
                    bep_baseentry = :bep_baseentry,
                    bep_basetype = :bep_basetype,
                    bep_description = :bep_description,
                    bep_createat = :bep_createat,
                    bep_createby = :bep_createby,
                    bep_status = :bep_status
                    WHERE bep_docentry = :bep_docentry";

        $this->pedeo->trans_begin();

        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ":bep_doctype" => $Data['bep_doctype'],
            ":bep_docnum" => $Data['bep_docnum'],
            ":bep_cardcode" => $Data['bep_cardcode'],
            ":bep_cardname" => $Data['bep_cardname'],
            ":bep_duedev" => $Data['bep_duedev'],
            ":bep_docdate" => $Data['bep_docdate'],
            ":bep_ref" => $Data['bep_ref'],
            ":bep_serie" => $Data['bep_serie'],
            ":bep_baseentry" => isset($Data['bep_baseentry']) ? $Data['bep_baseentry'] : 0,
            ":bep_basetype" => isset($Data['bep_basetype']) ? $Data['bep_basetype'] : 0,
            ":bep_description" => isset($Data['bep_description']) ? $Data['bep_description'] : null,
            ":bep_createat" => isset($Data['bep_createat']) ? $Data['bep_createat'] : null,
            ":bep_createby" => isset($Data['bep_createby']) ? $Data['bep_createby'] : null,
            ":bep_status" => isset($Data['bep_status']) ? $Data['bep_status'] : null,
            ":bep_docentry" => $Data['bep_docentry'],
        ));

        if (is_numeric($resUpdate) and $resUpdate > 0) {

            $this->pedeo->queryTable("DELETE FROM bep1 WHERE ep1_baseentry = :bep_docentry", array(':bep_docentry' => $Data['bep_docentry']));

            $sqlInsert2 = "INSERT INTO bep1 (ep1_item_description, ep1_quantity, ep1_itemcost, ep1_im, ep1_ccost, ep1_ubusiness, ep1_item_code, ep1_listmat, ep1_baseentry, ep1_plan, ep1_basenum, ep1_item_cost) values (:ep1_item_description, :ep1_quantity, :ep1_itemcost, :ep1_im, :ep1_ccost, :ep1_ubusiness, :ep1_item_code, :ep1_listmat, :ep1_baseentry, :ep1_plan, :ep1_basenum, :ep1_item_cost)";

            foreach ($ContenidoDetalle as $key => $detail) {
                $resInsert2 = $this->pedeo->insertRow($sqlInsert2, array(
                    ":ep1_item_description" => $detail['ep1_item_description'],
                    ":ep1_quantity" => $detail['ep1_quantity'],
                    ":ep1_itemcost" => $detail['ep1_itemcost'],
                    ":ep1_im" => $detail['ep1_im'],
                    ":ep1_ccost" => $detail['ep1_ccost'],
                    ":ep1_ubusiness" => $detail['ep1_ubusiness'],
                    ":ep1_item_code" => $detail['ep1_item_code'],
                    ":ep1_listmat" => $detail['ep1_listmat'],
                    ":ep1_baseentry" => $Data['bep_docentry'],
                    ":ep1_plan" => $detail['ep1_plan'],
                    ":ep1_item_cost" => (isset($detail['ep1_item_cost'])) ? $detail['ep1_item_cost'] : null,
                    ":ep1_basenum" => isset($detail['ep1_basenum']) ? $detail['ep1_basenum'] : null,
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
                'mensaje' => 'Emision de producción actualizada con exito',
            );
        } else {
            $respuesta = array(
                'error' => true,
                'data' => $resUpdate,
                'mensaje' => 'No se pudo realizar operacion',
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
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
