<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Emision {

    private $ci;
    private $pdo;

	public function __construct() {

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->pdo = $this->ci->load->database('pdo', true)->conn_id;
        $this->ci->load->library('pedeo', [$this->pdo]);
        $this->ci->load->library('generic');
        $this->ci->load->library('account');
        $this->ci->load->library('DocumentNumbering');

	}

    
    // REALIZA MOVIMIENTO DE INVENTARIO PARA ARTICULOS QUE 
    // ESTAN MARCADOS COMO EMISION AUTOMATICA
    // PROCESO RECEPCION DE FABRICACION
    public function setEmission($Items, $Data, $periodo, $resInsert, $DocNumVerificado, $TasaDocLoc, $TasaLocSys, $MONEDALOCAL, $MONEDASYS) {
        // $Items =  array de articulos para sacar  cantidades de inventario 
        // $Data =  contiene los datos de cabecera para la recepcion de fabricacion
        // $Periodo = contiene informacion del periodo contable
        // $resInsert = docentry de la recepcion de fabricacion
        // $DocNumVerificado =  NUMERO DE LA NUMERACION DE LA RECEPCION DE FABRICACION
        // $TasaDocLoc = Tasa de moneda local
        // $TasaLocSys  = tasa de moneda del sistema
        // $MONEDALOCAL = simbolo de la moneda local
        // $MONEDASYS = simbolo de la moneda del sistema
        
        $DECI_MALES = $this->ci->generic->getDecimals();

        $ManejaInvetario = 0;
        $ManejaLote = 0;
        $ManejaUbicacion = 0;
        $ManejaSerial = 0;

        $CANTUOMPURCHASE = 0; //CANTIDAD EN UNIDAD DE MEDIDA COMPRAS
		$CANTUOMSALE = 0; // CANTIDAD EN UNIDAD DE MEDIDA VENTAS

        $AC1LINE = 1;

        $DetalleMateriales = new stdClass();
        $DetalleRecursos = new stdClass();
        $DetalleConsolidadoMateriales = [];
        $DetalleConsolidadoRecursos = [];
        $inArrayMateriales = [];
        $inArrayRecursos = array();
        $llaveMateriales = "";
        $llaveRecursos = "";
        $posicionMateriales = 0;
        $posicionRecursos = 0;
        $totalCP2 = 0; 


        // BUSCANDO SERIE DE LA EMISION
        $sqlSerie = "SELECT pgs_id FROM pgdn WHERE pgs_enabled = 1 AND pgs_id_doc_type = 27";
        $resSerie = $this->ci->pedeo->queryTable($sqlSerie, array());

        if (!isset($resSerie[0])){
            return array(
                'error'   => true,
                'data' 	  => $resSerie,
                'mensaje' => 'No hay una serie valida para la realizar la emisión automática'
            );
        }
        //

        // //BUSCANDO LA NUMERACION DEL DOCUMENTO
        $DocNumAuto = $this->ci->documentnumbering->NumberDoc($resSerie[0]['pgs_id'], $Data['brp_docdate'], $Data['brp_docdate']);

        if (isset($DocNumAuto) && is_numeric($DocNumAuto) && $DocNumAuto > 0) {

        } else if ($DocNumAuto['error']) {

            return array(
                'error'   => true,
                'data' 	  => $DocNumAuto,
                'mensaje' => 'No hay numeración disponible para realizar la emisión automática'
            );
        }
        //



        // GENERANDO CABECERA DE LA EMISION
        $sqlInsertEmision = "INSERT INTO tbep ( bep_doctype, bep_docnum, bep_cardcode, bep_cardname, bep_duedev, bep_docdate, bep_ref, bep_serie, bep_baseentry, bep_basetype, bep_description, bep_createat, bep_createby, bep_status, business) VALUES(:bep_doctype, :bep_docnum, :bep_cardcode, :bep_cardname, :bep_duedev, :bep_docdate, :bep_ref, :bep_serie, :bep_baseentry, :bep_basetype, :bep_description, :bep_createat, :bep_createby, :bep_status, :business)";

        $resInsertEmision = $this->ci->pedeo->insertRow($sqlInsertEmision, array (
            ":bep_doctype" => 27,
            ":bep_docnum" => $DocNumAuto,
            ":bep_cardcode" => $Data['brp_cardcode'],
            ":bep_cardname" => $Data['brp_cardname'],
            ":bep_duedev" => $Data['brp_docdate'],
            ":bep_docdate" => $Data['brp_docdate'],
            ":bep_ref" => $Data['brp_ref'],
            ":bep_serie" => $resSerie[0]['pgs_id'],
            ":bep_baseentry" => is_numeric($Data['brp_baseentry']) ? $Data['brp_baseentry'] : 0,
            ":bep_basetype" => is_numeric($Data['brp_basetype']) ? $Data['brp_basetype'] : 0,
            ":bep_description" => "Emisión automática",
            ":bep_createat" => isset($Data['brp_createat']) ? $Data['brp_createat'] : null,
            ":bep_createby" => isset($Data['brp_createby']) ? $Data['brp_createby'] : null,
            ":bep_status" => isset($Data['brp_status']) ? $Data['brp_status'] : 0,
            ":business" => $Data['business']
        ));

        if (is_numeric($resInsertEmision) && $resInsertEmision > 0 ){

        }else{
            return array(
                'error'   => true,
                'data' 	  => $resInsertEmision,
                'mensaje' => 'No se pudo crear la emisión automática'
            );
        }
        //

        // SE ACTUALIZA LA NUMERACION
        $sqlActualizarNumeracion = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum WHERE pgs_id = :pgs_id";
        $resActualizarNumeracion = $this->ci->pedeo->updateRow($sqlActualizarNumeracion, array(
            ':pgs_nextnum' => $DocNumAuto,
            ':pgs_id' => $resSerie[0]['pgs_id'],
        ));

        if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {

        } else {
           
            return array(
                'error' => true,
                'data' => $resActualizarNumeracion,
                'mensaje' => 'No se pudo actualizar la numeración',
            );
        }
        //

        
        //SE INSERTA EL ESTADO DE LA EMISION AUTOMATICA

        $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
        VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

        $resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

            ':bed_docentry' => $resInsertEmision,
            ':bed_doctype' => 27,
            ':bed_status' => 3, // Estado cerrado
            ':bed_createby' => $Data['brp_createby'],
            ':bed_date' => date('Y-m-d'),
            ':bed_baseentry' => null,
            ':bed_basetype' => null,
        ));

        if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {

        } else {
           return  array(
                'error' => true,
                'data' => $resInsertEstado,
                'mensaje' => 'No se pudo registrar la emisión de fabricación automática',
            );

        }
        //

        // Se globaliza la variable sqlDetalleAsiento
        $sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
        ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
        ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
        ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, business, branch)VALUES (:ac1_trans_id, :ac1_account,
        :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
        :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
        :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
        :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_line, :business, :branch)";


        // SE AGREGA LA CABECERA DEL ASIENTO CONTABLE
        $sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch, mac_accperiod)
        VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch, :mac_accperiod)";


        $resInsertAsiento = $this->ci->pedeo->insertRow($sqlInsertAsiento, array(

            ':mac_doc_num' => 1,
            ':mac_status' => 1,
            ':mac_base_type' => 27,
            ':mac_base_entry' => $resInsertEmision,
            ':mac_doc_date' => $this->validateDate($Data['brp_docdate'])?$Data['brp_docdate']:NULL,
            ':mac_doc_duedate' => $this->validateDate($Data['brp_docdate'])?$Data['brp_docdate']:NULL,
            ':mac_legal_date' => $this->validateDate($Data['brp_docdate'])?$Data['brp_docdate']:NULL,
            ':mac_ref1' => 27,
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
            ':mac_comments' => "Asiento generado por el proceso de  emisión automática",
            ':mac_create_date' => date("Y-m-d"),
            ':mac_made_usuer' => isset($Data['brp_createby'])?$Data['brp_createby']:NULL,
            ':mac_update_date' => date("Y-m-d"),
            ':mac_update_user' => isset($Data['brp_createby'])?$Data['brp_createby']:NULL,
            ':business'	  => $Data['business'],
            ':branch' 	  => $Data['branch'],
            ':mac_accperiod' => $periodo['data'],
        ));


        if(is_numeric($resInsertAsiento) && $resInsertAsiento > 0){
        }else{

            $respuesta = array(
                'error'  => true,
                'data'   => $resInsertAsiento,
                'mensaje'=> 'No se pudo registrar el documento contable'
            );

            return $respuesta;
        }

        // PROCESO PARA BUSCAR CANTIDADES POR LOTES Y UBICACIONES SI APLICA

        $ManejaInvetario = 0;
        $ManejaUbicacion = 0;
        $ManejaLote = 0;
        $ItemAcumulado = '';
        //
        for ( $i=0; $i < count($Items); $i++ ) { 

            if ( $Items[$i]['lm1_type'] == 2 ) {

                $sqlItemINV = "SELECT coalesce(dma_item_inv, '0') as dma_item_inv,coalesce(dma_use_tbase,0) as dma_use_tbase, coalesce(dma_tasa_base,0) as dma_tasa_base FROM dmar WHERE dma_item_code = :dma_item_code";
                $resItemINV = $this->ci->pedeo->queryTable($sqlItemINV, array(
                    ':dma_item_code' => $Items[$i]['lm1_itemcode']
                ));
    
                if ( isset($resItemINV[0]) && $resItemINV[0]['dma_item_inv'] == '1' ) {
    
                    $ManejaInvetario = 1;
                    
                    // CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
                    $sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
                    $resubicacion = $this->ci->pedeo->queryTable($sqlubicacion, array(
                        ':dws_ubication' => 1,
                        ':dws_code' => $Items[$i]['bof_whscode'],
                        ':business' => $Data['business']
                    ));
    
    
                    if ( isset($resubicacion[0]) ){
                        $ManejaUbicacion = 1;
                    }else{
                        $ManejaUbicacion = 0;
                    }
    
                    // SI MANEJA LOTE
                    $sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
                    $resLote = $this->ci->pedeo->queryTable($sqlLote, array(
    
                        ':dma_item_code'  => $Items[$i]['lm1_itemcode'],
                        ':dma_lotes_code' => 1
                    ));
    
                    if (isset($resLote[0])) {
                        $ManejaLote = 1;
                    } else {
                        $ManejaLote = 0;
                    }
    
                } else {
                    $ManejaInvetario = 0;
                }
    
    
                if ($ManejaUbicacion == 1) {
    
                    if ($ManejaLote == 1) {
    
                        $ItemAcumulado = $ItemAcumulado .' El articulo: '. $Items[$i]['lm1_itemcode'] . ' esta marcado para manejar lote, y el almacen: '.$Items[$i]['bof_whscode'].' requiere ubicación ';
                    }else{
                        $ItemAcumulado = $ItemAcumulado .' El almacen: '.$Items[$i]['bof_whscode'].' requiere ubicación ';
                    }
    
                } else if ( $ManejaLote == 1 ) {
    
                    $ItemAcumulado = $ItemAcumulado .' El articulo: '. $Items[$i]['lm1_itemcode'] . ' esta marcado para manejar lote';
                }
            }
        }

        if (  !empty($ItemAcumulado)  ) {

            $respuesta = array(
                'error'  => true,
                'data'   => [],
                'mensaje'=> 'No es posible completar la emisión automática de los materiales, no se pueden marcar con emisión automática aquellos articulos cuyo almacen maneje ubicación o que el mismo material maneje lotes: '.$ItemAcumulado
            );

            return $respuesta;
        }


        // REALIZANDO MOVIMIENTO DE INVENTARIO
        foreach ($Items as $key => $detail) {

            // DETALLE DE LA EMISON AUTOMATICA
            $sqlInsertDetalleEmision = "INSERT INTO bep1 (ep1_item_description, ep1_quantity, ep1_itemcost, ep1_im, ep1_ccost, ep1_ubusiness, ep1_item_code, ep1_listmat, ep1_baseentry, ep1_plan, ep1_basenum, ep1_item_cost, ep1_type, ep1_uom, ep1_whscode, ote_code, ep1_ubication) values (:ep1_item_description, :ep1_quantity, :ep1_itemcost, :ep1_im, :ep1_ccost, :ep1_ubusiness, :ep1_item_code, :ep1_listmat, :ep1_baseentry, :ep1_plan, :ep1_basenum, :ep1_item_cost, :ep1_type, :ep1_uom, :ep1_whscode, :ote_code, :ep1_ubication)";
            
            $resInsertDetalleEmision = $this->ci->pedeo->insertRow($sqlInsertDetalleEmision, array(

                ":ep1_item_description" => $detail['of1_description'],
                ":ep1_quantity" => $detail['lm1_quantity'],
                ":ep1_itemcost" => $detail['lm1_price'],
                ":ep1_im" => $detail['of1_ing'],
                ":ep1_ccost" => $detail['of1_costcode'],
                ":ep1_ubusiness" => $detail['of1_uom_code'],
                ":ep1_item_code" => $detail['lm1_itemcode'],
                ":ep1_listmat" => $detail['bof_baseentry'],
                ":ep1_baseentry" => $resInsertEmision,
                ":ep1_plan" => $detail['lm1_quantity'],
                ":ep1_item_cost" => null,
                ":ep1_basenum" => $Data['brp_baseentry'],
                ":ep1_type" => isset($detail['lm1_type']) ? $detail['lm1_type'] : null,
                ":ep1_uom"  => isset($detail['of1_uom']) ? $detail['of1_uom'] : null,
                ":ep1_whscode" => isset($detail['bof_whscode']) ? $detail['bof_whscode'] : null,
                ":ote_code" => isset($detail['ote_code']) ? $detail['ote_code'] : null,
                ":ep1_ubication" => isset($detail['ep1_ubication']) ? $detail['ep1_ubication'] : null

            ));
            //

            if ( is_numeric( $resInsertDetalleEmision ) && $resInsertDetalleEmision > 0 ){

            }else{
                $respuesta = array(
                    'error'   => true,
                    'data' 	  => $resInsertDetalleEmision,
                    'mensaje' => 'No se pudo ingresar el detalle de la emisión automática'
                );

                return $respuesta;
            }


            // SE VALIDA QUE NO SEA UN RECURSO
            if ($detail['lm1_type'] == 2) {

                $CANTUOMPURCHASE = $this->ci->generic->getUomPurchase($detail['lm1_itemcode']);
                $CANTUOMSALE = $this->ci->generic->getUomSale($detail['lm1_itemcode']);
    
                if ($CANTUOMPURCHASE == 0 || $CANTUOMSALE == 0) {
    
                    $respuesta = array(
                        'error'   => true,
                        'data' 		=> $detail['lm1_itemcode'],
                        'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: ' . $detail['lm1_itemcode']
                    );
    
                   return $respuesta;
                }
    
                // MOVIMIENTO DE INVENTARIO
                $sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
                $resItemINV = $this->ci->pedeo->queryTable($sqlItemINV, array(
    
                    ':dma_item_code' => $detail['lm1_itemcode'],
                    ':dma_item_inv'  => 1
                ));

                if (isset($resItemINV[0])) {


                    // CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION
                    $sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
                    $resubicacion = $this->ci->pedeo->queryTable($sqlubicacion, array(
                        ':dws_ubication' => 1,
                        ':dws_code' => $detail['lm1_itemcode'],
                        ':business' => $Data['business']
                    ));
    
    
                    if ( isset($resubicacion[0]) ){
                        $ManejaUbicacion = 1;
                    }else{
                        $ManejaUbicacion = 0;
                    }
    
                    
    
                    // SI EL ARTICULO MANEJA LOTE
                    $sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
                    $resLote = $this->ci->pedeo->queryTable($sqlLote, array(
    
                        ':dma_item_code' => $detail['lm1_itemcode'],
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
                    // $sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
                    // $resItemSerial = $this->ci->pedeo->queryTable($sqlItemSerial, array(
    
                    //     ':dma_item_code'   => $Data['lm1_itemcode'],
                    //     ':dma_series_code' => 1
                    // ));
    
                    // if (isset($resItemSerial[0])) {
                    //     $ManejaSerial = 1;
    
                    //     if (!isset($detail['serials'])) {
    
                    //       
    
                    //         $respuesta = array(
                    //             'error'   => true,
                    //             'data'    => [],
                    //             'mensaje' => 'No se encontraron los seriales para el articulo: ' . $detail['ep1_item_code']
                    //         );
    
                    //         return $respuesta;
                    //     }
    
                    //     $AddSerial = $this->ci->generic->addSerial($detail['serials'], $detail['ep1_item_code'], $Data['bep_doctype'], $resInsert, $DocNumVerificado, $Data['isi_docdate'], 2, $Data['bep_description'], $detail['ep1_whscode'], $detail['ep1_quantity'], $Data['bep_createby'], $Data['business']);
    
                    //     if (isset($AddSerial['error']) && $AddSerial['error'] == false) {
                    //     } else {
    
                    //         
    
                    //         $respuesta = array(
                    //             'error'   => true,
                    //             'data'    => $AddSerial['data'],
                    //             'mensaje' => $AddSerial['mensaje']
                    //         );
    
                            // return $respuesta;
                    //     }
                    // } else {
                    //     $ManejaSerial = 0;
                    // }
    
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
                            $resCostoMomentoRegistro = $this->ci->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                                ':bdi_whscode'   => $detail['bof_whscode'],
                                ':bdi_itemcode'  => $detail['lm1_itemcode'],
                                ':bdi_lote'      => $detail['ote_code'],
                                ':bdi_ubication' => $detail['bof_ubication'],
                                ':business' 	 => $Data['business']
                            ));
                        } else {
                            $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
                            $resCostoMomentoRegistro = $this->ci->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                                ':bdi_whscode'   => $detail['bof_whscode'],
                                ':bdi_itemcode'  => $detail['lm1_itemcode'],
                                ':bdi_ubication' => $detail['bof_ubication'],
                                ':business' 	 => $Data['business']
                            ));
                        }
    
                    } else {
                        // SI EL ARTICULO MANEJA LOTE
                        if ($ManejaLote == 1) {
                            $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
                            $resCostoMomentoRegistro = $this->ci->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                                ':bdi_whscode'  => $detail['bof_whscode'],
                                ':bdi_itemcode' => $detail['lm1_itemcode'],
                                ':bdi_lote' 	=> $detail['ote_code'],
                                ':business' 	=> $Data['business']
                            ));
                        } else {
                            $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
                            $resCostoMomentoRegistro = $this->ci->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                                ':bdi_whscode' 	=> $detail['bof_whscode'],
                                ':bdi_itemcode' => $detail['lm1_itemcode'],
                                ':business' 	=> $Data['business']
                            ));
                        }
                    }
    
                    if (isset($resCostoMomentoRegistro[0])) {
    
    
                        //VALIDANDO CANTIDAD DE ARTICULOS
    
                        $CANT_ARTICULOEX = $resCostoMomentoRegistro[0]['bdi_quantity'];
                        $CANT_ARTICULOLN = $detail['lm1_quantity'] * $CANTUOMSALE;
    
                        if (($CANT_ARTICULOEX - $CANT_ARTICULOLN) < 0) {
    
                            $respuesta = array(
                                'error'   => true,
                                'data'    => [],
                                'mensaje' => 'no puede crear el documento porque el articulo ' . $detail['lm1_itemcode'] . ' recae en inventario negativo (' . ($CANT_ARTICULOEX - $CANT_ARTICULOLN) . ')'
                            );
    
                            return $respuesta;
    
                        }
    
                        //VALIDANDO CANTIDAD DE ARTICULOS
    
                        $sqlInserMovimiento = '';
                        $resInserMovimiento = [];
    
                    
                        //Se aplica el movimiento de inventario
                        $sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment, bmi_lote, bmi_ubication,business)
                                                VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote, :bmi_ubication,:business)";
    
                        $resInserMovimiento = $this->ci->pedeo->insertRow($sqlInserMovimiento, array(
    
                            ':bmi_itemcode' => isset($detail['lm1_itemcode']) ? $detail['lm1_itemcode'] : NULL,
                            ':bmi_quantity' => ($detail['lm1_quantity'] * $CANTUOMSALE) * -1,
                            ':bmi_whscode'  => isset($detail['bof_whscode']) ? $detail['bof_whscode'] : NULL,
                            ':bmi_createat' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
                            ':bmi_createby' => isset($Data['brp_createby']) ? $Data['brp_createby'] : NULL,
                            ':bmy_doctype'  => is_numeric($Data['brp_doctype']) ? $Data['brp_doctype'] : 0,
                            ':bmy_baseentry' => $resInsert,
                            ':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
                            ':bmi_currequantity' => $resCostoMomentoRegistro[0]['bdi_quantity'],
                            ':bmi_basenum'	=> $DocNumVerificado,
                            ':bmi_docdate' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
                            ':bmi_duedate' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
                            ':bmi_duedev'  => $this->validateDate($Data['brp_duedev']) ? $Data['brp_duedev'] : NULL,
                            ':bmi_comment' => "Emisión generada automáticamente por el proceso de fabricación",
                            ':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
                            ':bmi_ubication' => isset($detail['bof_ubication']) ? $detail['bof_ubication'] : NULL,
                            ':business' => isset($Data['business']) ? $Data['business'] : NULL
                        ));
                    
    
    
    
                        if (is_numeric($resInserMovimiento) && $resInserMovimiento > 0) {
                        } else {
    
    
                            $respuesta = array(
                                'error'   => true,
                                'data'    => $resInserMovimiento,
                                'mensaje' => 'No se pudo registra la salida de inventario'
                            );
    
                            return $respuesta;
    
                            
                        }
                    } else {
    
                        $respuesta = array(
                            'error'   => true,
                            'data' => $resCostoMomentoRegistro,
                            'mensaje'	=> 'No se pudo relizar el movimiento, no se encontro el costo del articulo #'.$detail['lm1_itemcode']
                        );
    
                        return $respuesta;
    
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
    
                            $resCostoCantidad = $this->ci->pedeo->queryTable($sqlCostoCantidad, array(
    
                                ':bdi_itemcode'  => $detail['lm1_itemcode'],
                                ':bdi_whscode'   => $detail['bof_whscode'],
                                ':bdi_lote'		 => $detail['ote_code'],
                                ':bdi_ubication' => $detail['bof_ubication'],
                                ':business' 	 => $Data['business']
                            ));
                        } else {
                            $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                            FROM tbdi
                            WHERE bdi_itemcode = :bdi_itemcode
                            AND bdi_whscode = :bdi_whscode
                            AND bdi_ubication = :bdi_ubication
                            AND business = :business";
    
                            $resCostoCantidad = $this->ci->pedeo->queryTable($sqlCostoCantidad, array(
    
                                ':bdi_itemcode'  => $detail['lm1_itemcode'],
                                ':bdi_whscode'   => $detail['bof_whscode'],
                                ':bdi_ubication' => $detail['bof_ubication'],
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
    
                            $resCostoCantidad = $this->ci->pedeo->queryTable($sqlCostoCantidad, array(
    
                                ':bdi_itemcode' => $detail['lm1_itemcode'],
                                ':bdi_whscode'  => $detail['bof_whscode'],
                                ':bdi_lote'		=> $detail['ote_code'],
                                ':business' 	=> $Data['business']
                            ));
                        } else {
                            $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                                            FROM tbdi
                                            WHERE bdi_itemcode = :bdi_itemcode
                                            AND bdi_whscode = :bdi_whscode
                                            AND business = :business";
    
                            $resCostoCantidad = $this->ci->pedeo->queryTable($sqlCostoCantidad, array(
    
                                ':bdi_itemcode' => $detail['lm1_itemcode'],
                                ':bdi_whscode'  => $detail['bof_whscode'],
                                ':business' => $Data['business']
                            ));
                        }
                    }
    
    
                    if (isset($resCostoCantidad[0])) {
    
                        $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
                        $CostoActual    = $resCostoCantidad[0]['bdi_avgprice'];
    
                        $CantidadDevolucion = $detail['lm1_quantity'] * $CANTUOMSALE;
                        $CostoDevolucion = $detail['lm1_price'];
    
                        $CantidadTotal = ($CantidadActual - $CantidadDevolucion);
    
                        // $CostoPonderado = (($CostoActual * $CantidadActual) + ($CostoDevolucion * $CantidadDevolucion)) / $CantidadTotal;
                        // NO SE MUEVE EL COSTO PONDERADO
                        $sqlUpdateCostoCantidad =  "UPDATE tbdi
                                                SET bdi_quantity = :bdi_quantity
                                                WHERE  bdi_id = :bdi_id";
    
                        $resUpdateCostoCantidad = $this->ci->pedeo->updateRow($sqlUpdateCostoCantidad, array(
    
                            ':bdi_quantity' => $CantidadTotal,
                            ':bdi_id' 		=> $resCostoCantidad[0]['bdi_id']
                        ));
    
                        if (is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1) {
                        } else {
    
                            $respuesta = array(
                                'error'   => true,
                                'data'    => $resUpdateCostoCantidad,
                                'mensaje'	=> 'No se pudo registrar el movimiento en el stock'
                            );
    
                            return $respuesta;
                           
                        }
                    } else {
    
                        $respuesta = array(
                            'error'   => true,
                            'data' 		=> $resCostoCantidad,
                            'mensaje'	=> 'El item no existe en el stock ' . $detail['lm1_itemcode']
                        );
    
                        return $respuesta;
                       
                    }
                }
            }

            // AGRUPANDO DATOS PARA LAS LINEAS DE ASIENTOS

            if ( isset($detail['lm1_type']) && !empty($detail['lm1_type']) && $detail['lm1_type'] == 2 ) {

                $acct_invproc = 0;

                $DetalleMateriales = new stdClass();
                       
                $CUENTASINV = $this->ci->account->getAccountItem($detail['lm1_itemcode'], $detail['bof_whscode']);
    
                if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {
                }else{
                    
    
                    $respuesta = array(
                        'error'   => true,
                        'data'	  => $CUENTASINV,
                        'mensaje'	=> 'No se encontro la cuenta para el item ' . $detail['lm1_itemcode']
                    );
    
                    return $respuesta;
    
                    
                }
            
                $DetalleMateriales->ccosto = isset($detail['bof_ccost']) ? $detail['bof_ccost'] : NULL;
                $DetalleMateriales->proyecto = isset($detail['bof_project']) ? $detail['bof_project'] : NULL;
                $DetalleMateriales->totallinea = ( ( $detail['lm1_quantity'] * $CANTUOMSALE ) * $resCostoCantidad[0]['bdi_avgprice'] );
                $DetalleMateriales->itemcode = isset($detail['lm1_itemcode']) ? $detail['lm1_itemcode'] : NULL;
                $DetalleMateriales->whscode = isset($detail['bof_whscode']) ? $detail['bof_whscode'] : NULL;
                $DetalleMateriales->cuenta = $CUENTASINV['data']['acct_inv'];
    
                $acct_invproc = $CUENTASINV['data']['acct_invproc'];
    
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

            } else {

                $sqlConcepto = "SELECT cdrs.*, dcpr.*
                                FROM dmrp
                                INNER JOIN dcpr ON dmrp.mrp_id = dcpr.crp_id_recurso
                                INNER JOIN cdrs ON dcpr.crp_id_concepto = cdrs.drs_id WHERE dmrp.business = :business AND mrp_id = :mrp_id";

                $resConcepto = $this->ci->pedeo->queryTable($sqlConcepto, array(
                    'business'  => $Data['business'],
                    'mrp_id'	=> $detail['lm1_itemcode']
                ));

                if ( isset($resConcepto[0]) ) {

                    foreach ($resConcepto as $key => $conc) {

                        $DetalleRecursos = new stdClass();

                        $DetalleRecursos->ccosto = isset($detail['bof_ccost']) ? $detail['bof_ccost'] : NULL;
                        $DetalleRecursos->proyecto = isset($detail['bof_project']) ? $detail['bof_project'] : NULL;
                        $DetalleRecursos->totallinea = ( $detail['lm1_quantity'] * $conc['crp_valor'] );
                        $DetalleRecursos->itemcode = isset($detail['lm1_itemcode']) ? $detail['lm1_itemcode'] : NULL;
                        $DetalleRecursos->whscode = isset($detail['bof_whscode']) ? $detail['bof_whscode'] : NULL;
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

                    $this->ci->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'	  => $resConcepto,
                        'mensaje' => 'No se encontro el concepto para el recurso: '. $detail['lm1_itemcode']
                    );

                    $this->response($respuesta);

                    return;
                }
            }

        }

        // ASIENTO PARA MATERIALES
        //    
        foreach ($DetalleConsolidadoMateriales as $key => $posicion) {
            $monto = 0;
            $montoSys = 0;
            $cuenta = 0;
            $centroCosto = '';
            $proyecto = '';
            foreach ($posicion as $key => $value) {

             
                $cuenta = $value->cuenta;
                $monto = ($monto + $value->totallinea);
                $centroCosto = $value->ccosto;
                $proyecto = $value->proyecto;


            }


            $montoSys = ($monto / $TasaLocSys);
            $AC1LINE = $AC1LINE + 1;

            // SE AGREGA AL BALANCE

            $BALANCE = $this->ci->account->addBalance($periodo['data'], round($monto, $DECI_MALES), $cuenta, 2, $Data['brp_docdate'], $Data['business'], $Data['branch']);
            if (isset($BALANCE['error']) && $BALANCE['error'] == true){


                $respuesta = array(
                    'error' => true,
                    'data' => $BALANCE,
                    'mensaje' => $BALANCE['mensaje']
                );

                return $respuesta;
            }
            //
            

            $resDetalleAsiento = $this->ci->pedeo->insertRow($sqlDetalleAsiento, array(

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
                ':ac1_font_key' => $resInsertEmision,
                ':ac1_font_line' => 1,
                ':ac1_font_type' => 27,
                ':ac1_accountvs' => 1,
                ':ac1_doctype' => 18,
                ':ac1_ref1' => "",
                ':ac1_ref2' => "",
                ':ac1_ref3' => "",
                ':ac1_prc_code' => $centroCosto,
                ':ac1_uncode' =>  NULL,
                ':ac1_prj_code' => $proyecto,
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
                ':ac1_legal_num' => NULL,
                ':ac1_codref' => 0,
                ':ac1_line' => $AC1LINE,
                ':business' => $Data['business'],
                ':branch' => $Data['branch']
            ));



            if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
            } else {
               

                $respuesta = array(
                    'error'   => true,
                    'data'	  => $resDetalleAsiento,
                    'mensaje' => 'No se pudo registrar la recepción de fabricación'
                );

                return $respuesta;
            }
        }

    
        // ASIENTO DE RECURSOS
        // 
        $totalCP = 0;
        foreach ($DetalleConsolidadoRecursos as $key => $posicion) {
            $monto = 0;
            $montoSys = 0;
            $cuenta = 0;
            $centroCosto = '';
            $proyecto = '';
            foreach ($posicion as $key => $value) {
            
                $cuenta = $value->cuenta;
                $monto = ($monto + $value->totallinea);
                $centroCosto = $value->ccosto;
                $proyecto = $value->proyecto;

                $totalCP = ($totalCP + $value->totallinea);
            }


            $montoSys = ($monto / $TasaLocSys);

            // SE AGREGA AL BALANCE
            
            $BALANCE = $this->ci->account->addBalance($periodo['data'], round($monto, $DECI_MALES), $cuenta, 2, $Data['brp_docdate'], $Data['business'], $Data['branch']);
            if (isset($BALANCE['error']) && $BALANCE['error'] == true){

                $this->ci->pedeo->trans_rollback();

                $respuesta = array(
                    'error' => true,
                    'data' => $BALANCE,
                    'mensaje' => $BALANCE['mensaje']
                );

                return $this->response($respuesta);
            }	
            //

            $AC1LINE = $AC1LINE + 1;

            $resDetalleAsiento = $this->ci->pedeo->insertRow($sqlDetalleAsiento, array(

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
                ':ac1_font_key' => $resInsertEmision,
                ':ac1_font_line' => 1,
                ':ac1_font_type' => 27,
                ':ac1_accountvs' => 1,
                ':ac1_doctype' => 18,
                ':ac1_ref1' => "",
                ':ac1_ref2' => "",
                ':ac1_ref3' => "",
                ':ac1_prc_code' => $centroCosto,
                ':ac1_uncode' => $proyecto,
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
                ':ac1_legal_num' => NULL,
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
                $this->ci->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'	  => $resDetalleAsiento,
                    'mensaje'	=> 'No se pudo registrar la recepción de producción'
                );

                $this->response($respuesta);

                return;
            }
        }

        // CONTRA PARTIDA DE RECURSOS Y MATERIALES
        if ($totalCP2 + $totalCP > 0){
            $totalCPSYS2 =  ($totalCP2 + $totalCP) / $TasaLocSys;
            $AC1LINE = $AC1LINE + 1;

            
            // SE AGREGA AL BALANCE

            $BALANCE = $this->ci->account->addBalance($periodo['data'], round( ($totalCP2 + $totalCP), $DECI_MALES), $acct_invproc, 1, $Data['brp_docdate'], $Data['business'], $Data['branch']);
            if (isset($BALANCE['error']) && $BALANCE['error'] == true){

                $respuesta = array(
                    'error' => true,
                    'data' => $BALANCE,
                    'mensaje' => $BALANCE['mensaje']
                );

                return $respuesta;
            }	

            //
            $resDetalleAsiento = $this->ci->pedeo->insertRow($sqlDetalleAsiento, array(

                ':ac1_trans_id' => $resInsertAsiento,
                ':ac1_account' => $acct_invproc,
                ':ac1_debit' => round( ($totalCP2 + $totalCP), $DECI_MALES),
                ':ac1_credit' => 0,
                ':ac1_debit_sys' => round($totalCPSYS2, $DECI_MALES),
                ':ac1_credit_sys' => 0,
                ':ac1_currex' => 0,
                ':ac1_doc_date' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
                ':ac1_doc_duedate' => $this->validateDate($Data['brp_docdate']) ? $Data['brp_docdate'] : NULL,
                ':ac1_debit_import' => 0,
                ':ac1_credit_import' => 0,
                ':ac1_debit_importsys' => 0,
                ':ac1_credit_importsys' => 0,
                ':ac1_font_key' => $resInsertEmision,
                ':ac1_font_line' => 1,
                ':ac1_font_type' => 27,
                ':ac1_accountvs' => 1,
                ':ac1_doctype' => 18,
                ':ac1_ref1' => "",
                ':ac1_ref2' => "",
                ':ac1_ref3' => "",
                ':ac1_prc_code' => $centroCosto,
                ':ac1_uncode' => $proyecto,
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
                ':ac1_legal_num' => NULL,
                ':ac1_codref' => 0,
                ':ac1_line' => $AC1LINE,
                ':business' => $Data['business'],
                ':branch' => $Data['branch']
            ));



            if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
                
            } else {

                $respuesta = array(
                    'error'   => true,
                    'data'	  => $resDetalleAsiento,
                    'mensaje'	=> 'No se pudo registrar la recepción de fabricación'
                );

                return $respuesta;

            }
        }

        // SE VALIDA DIFERENCIA POR DECIMALES
        // Y SE AGREGA UN ASIENTO DE DIFERENCIA EN DECIMALES
        // AJUSTE AL PESO
        // SEGUN SEA EL CASO
        //SE VALIDA LA CONTABILIDAD CREADA
        $validateCont = $this->ci->generic->validateAccountingAccent($resInsertAsiento);

        if (isset($validateCont['error']) && $validateCont['error'] == false) {

        }else{


            $debito  = 0;
            $credito = 0;
            if ( $validateCont['data'][0]['debit_sys'] != $validateCont['data'][0]['credit_sys'] ) {

                $sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
                $resCuentaDiferenciaDecimal = $this->ci->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array( ':business' => $Data['business'] ));

                if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {

                    if ($validateCont['data'][0]['credit_sys'] > $validateCont['data'][0]['debit_sys']) { // DIFERENCIA EN CREDITO EL VALOR SE COLOCA EN DEBITO

                        $debito = ($validateCont['data'][0]['credit_sys'] - $validateCont['data'][0]['debit_sys']);
                    } else { // DIFERENCIA EN DEBITO EL VALOR SE COLOCA EN CREDITO

                        $credito = ($validateCont['data'][0]['debit_sys'] - $validateCont['data'][0]['credit_sys']);
                    }

                    if (round($debito + $credito, $DECI_MALES) > 0) {
                        $AC1LINE = $AC1LINE + 1;
                        $resDetalleAsiento = $this->ci->pedeo->insertRow($sqlDetalleAsiento, array(

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
                            ':ac1_font_key' => $resInsertEmision,
                            ':ac1_font_line' => 1,
                            ':ac1_font_type' => 27,
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
                            ':ac1_legal_num' => NULL,
                            ':ac1_codref' => 1,
                            ':ac1_line'   => $AC1LINE,
                            ':business' => $Data['business'],
                            ':branch' 	=> $Data['branch']
                        ));

                        if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
                        } else {
                            $respuesta = array(
                                'error'   => true,
                                'data'	  => $resDetalleAsiento,
                                'mensaje'	=> 'No se pudo registrar la recepción de fabricación'
                            );

                            return $respuesta;
                        }
                    }
                } else {

                  

                    $respuesta = array(
                        'error'   => true,
                        'data'	  => $resCuentaDiferenciaDecimal,
                        'mensaje'	=> 'No se encontro la cuenta para adicionar la diferencia en decimales'
                    );

                   return $respuesta;
                }
            }

        }

        // $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
        // $ressqlmac1 = $this->ci->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
        // print_r(json_encode($ressqlmac1));
        // exit;

        // FIN VALIDACION DIFERENCIA EN DECIMALES

        // SE VALIDA LA CONTABILIDAD CREADA
        $validateCont2 = $this->ci->generic->validateAccountingAccent2($resInsertAsiento);
        

        if (isset($validateCont2['error']) && $validateCont2['error'] == false) {
        } else {

            $ressqlmac1 = [];
            $sqlmac1 = "SELECT acc_name, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
            $ressqlmac1['contabilidad'] = $this->ci->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));

           

            $respuesta = array(
                'error'   => true,
                'data' 	  => $ressqlmac1,
                'mensaje' => $validateCont['mensaje'],
                
            );

            return $respuesta;
        }
        //


        return array(
            'error'   => false,
            'data' 	  => [],
            'mensaje' => 'Es posible continuar con el proceso'
        );
    }


    private function validateDate($fecha)
    {
        if (strlen($fecha) == 10 or strlen($fecha) > 10) {
            return true;
        } else {
            return false;
        }
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

}