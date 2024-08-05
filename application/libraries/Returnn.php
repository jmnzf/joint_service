<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Returnn {

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
        $this->ci->load->library('DocumentNumbering');
        $this->ci->load->library('Generic');

	}


    // REALIZA LA ANULACION DE UN PAGO 
    // EFECTUADO O RECIBIDO
    public function Return( $doctype, $docentry, $business, $branch, $isfechadoc, $createby ) {

        // $DOCTYPE = TIPO DE DOCUMENTO 
        // $DOCENTRY = ID DEL DOCUMENTO
        // $business = EMPRESA 
        // $branch = SUCURSAL
        // $isfechadoc =  1 Ó 0 INDICA SI SE USA LA FECHA DEL DOCUMENTO SELECCIONADO O LA FECHA ACTUAL  

        // ESTABLECIENDO PREFIJOS DE CABECERAS Y DETALLES
        $prefijoC = ""; // PREFIJO DE LA TABLA  DE CABECERA
        $tablaC   = ""; // TABLA DE CABECERA
        $prefijoD = ""; // PREFIJO DE LA TABLA DETALLE
        $tablaD   = ""; // TABLA DETALLE
        $fechaDOC = date("Y-m-d");


        switch($doctype) {
            case 19:
                $prefijoC = "bpe";
                $tablaC   = "gbpe";
                $prefijoD = "pe1";
                $tablaD   = "bpe1";
                break;
            case 20:
                $prefijoC = "bpr";
                $tablaC   = "gbpr";
                $prefijoD = "pr1";
                $tablaD   = "bpr1";
                break;        
        }

        //
        $sqlCabecera = "SELECT * FROM ".$tablaC." WHERE ".$prefijoC."_doctype = :doctype AND ".$prefijoC."_docentry = :docentry";
        $resCabecera = $this->ci->pedeo->queryTable($sqlCabecera, array(

            ':doctype'  => $doctype,
            ':docentry' => $docentry
        ));

        
        if ( !isset( $resCabecera[0] ) ) {

            return $respuesta = array(
                'error'   => true,
                'data'    => $resCabecera,
                'mensaje' => 'No se encontro la cabecera del documento'
            );
        }

        // SE VALIDA DE DONDE TOMA LA FECHA PARA ESTABLECER LA CONTABILIDAD
        if ( $isfechadoc == 1 ){
            $fechaDOC = $resCabecera[0][$prefijoC."_docdate"];
        }

        //
        $sqlDetalle = "SELECT * FROM ".$tablaD." WHERE ".$prefijoD."_docnum = :docnum ";
        $resDetalle = $this->ci->pedeo->queryTable($sqlDetalle, array(

            ':docnum' => $docentry
        ));

        
        if ( !isset( $resDetalle[0] ) ) {

            return $respuesta = array(
                'error'   => true,
                'data'    => $resDetalle,
                'mensaje' => 'No se encontro el detalle del documento'
            );
        }

        // SE BUSCA LA SERIE DEL DOCUMENTO
        // PARA ANULAR (NUMERACION DEL DOCUMENTO ORIGINAL MARCADA PARA CANCELAR)
        $sqlSerie = "SELECT * FROM  pgdn WHERE pgs_cancel = :pgs_cancel AND pgs_doctype = :pgs_doctype AND business = :business AND branch = :branch AND pgs_enabled  = :pgs_enabled ";
        $resSerie = $this->ci->pedeo->queryTable($sqlSerie, array(
            ':pgs_cancel'  => 1,
            ':pgs_doctype' => $doctype,
            ':business'    => $business,
            ':branch'      => $branch,
            ':pgs_enabled' => 1
        ));


        if ( !isset( $resSerie[0] ) ) {

            return $respuesta = array(
                'error'   => true,
                'data'    => [],
                'mensaje' => 'No se encontro la numeracion predeterminada'
            );
        }

        // BUSCANDO LA NUMERACION DEL DOCUMENTO
        $DocNumVerificado = $this->ci->documentnumbering->NumberDoc( $resSerie[0]['pgs_id'], $fechaDOC, $fechaDOC );
        
        if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0) {

        } else if ($DocNumVerificado['error']) {

            return $this->response($DocNumVerificado);
        }


        // SE INGRESA LA CABECERA DE LA DEVOLUCION DEL PAGO

        $sqlInsert = "INSERT INTO gbdp(bdp_cardcode, bdp_cardname, bdp_address, bdp_perscontact, bdp_series, bdp_docnum, bdp_docdate, bdp_taxdate, bdp_ref, bdp_transid, bdp_comments, bdp_memo, bdp_acctransfer, bdp_datetransfer, bdp_reftransfer, bdp_doctotal, bdp_vlrpaid, bdp_project, bdp_createby, bdp_createat, bdp_payment, bdp_doctype, bdp_currency, bdp_paytoday, business, branch)
                    VALUES (:bdp_cardcode, :bdp_cardname, :bdp_address, :bdp_perscontact, :bdp_series, :bdp_docnum, :bdp_docdate, :bdp_taxdate, :bdp_ref, :bdp_transid, :bdp_comments, :bdp_memo, :bdp_acctransfer, :bdp_datetransfer, :bdp_reftransfer, :bdp_doctotal, :bdp_vlrpaid, :bdp_project, :bdp_createby, :bdp_createat, :bdp_payment, :bdp_doctype, :bdp_currency, :bdp_paytoday, :business, :branch)";

        $resInsert = $this->ci->pedeo->insertRow($sqlInsert, array(
            
            ':bdp_cardcode'     => $resCabecera[0][$prefijoC."_cardcode"], 
            ':bdp_cardname'     => $resCabecera[0][$prefijoC."_cardname"], 
            ':bdp_address'      => $resCabecera[0][$prefijoC."_address"], 
            ':bdp_perscontact'  => $resCabecera[0][$prefijoC."_perscontact"], 
            ':bdp_series'       => $resSerie[0]['pgs_id'], 
            ':bdp_docnum'       => $DocNumVerificado, 
            ':bdp_docdate'      => $fechaDOC, 
            ':bdp_taxdate'      => $fechaDOC, 
            ':bdp_ref'          => $resCabecera[0][$prefijoC."_ref"], 
            ':bdp_transid'      => $resCabecera[0][$prefijoC."_transid"], 
            ':bdp_comments'     => $resCabecera[0][$prefijoC."_comments"], 
            ':bdp_memo'         => $resCabecera[0][$prefijoC."_memo"], 
            ':bdp_acctransfer'  => $resCabecera[0][$prefijoC."_acctransfer"], 
            ':bdp_datetransfer' => $resCabecera[0][$prefijoC."_datetransfer"], 
            ':bdp_reftransfer'  => $resCabecera[0][$prefijoC."_reftransfer"], 
            ':bdp_doctotal'     => $resCabecera[0][$prefijoC."_doctotal"], 
            ':bdp_vlrpaid'      => $resCabecera[0][$prefijoC."_vlrpaid"], 
            ':bdp_project'      => $resCabecera[0][$prefijoC."_project"], 
            ':bdp_createby'     => $createby, 
            ':bdp_createat'     => date("Y-m-d"), 
            ':bdp_payment'      => $resCabecera[0][$prefijoC."_payment"], 
            ':bdp_doctype'      => 39, 
            ':bdp_currency'     => $resCabecera[0][$prefijoC."_currency"], 
            ':bdp_paytoday'     => $resCabecera[0][$prefijoC."_paytoday"], 
            ':business'         => $resCabecera[0]["business"], 
            ':branch'           => $resCabecera[0]["branch"]
        ));


        if ( is_numeric($resInsert) && $resInsert > 0 ){

        }else{
            return $respuesta = array(
                'error'   => true,
                'data'    => $resInsert,
                'mensaje' => 'No se pudo crear la devolución del pago'
            );
        }


        // SE INGRESA EL DETALLE DEL DOCUMENTO
        foreach ( $resDetalle as $key => $detail ) {

            $sqlDetail = "INSERT INTO bdp1(dp1_docnum, dp1_docentry, dp1_numref, dp1_docdate, dp1_vlrtotal, dp1_vlrpaid, dp1_comments, dp1_porcdiscount, dp1_doctype, dp1_docduedate, dp1_daysbackw, dp1_vlrdiscount, dp1_ocrcode, dp1_ocrcode1, business, branch)
                        VALUES (:dp1_docnum, :dp1_docentry, :dp1_numref, :dp1_docdate, :dp1_vlrtotal, :dp1_vlrpaid, :dp1_comments, :dp1_porcdiscount, :dp1_doctype, :dp1_docduedate, :dp1_daysbackw, :dp1_vlrdiscount, :dp1_ocrcode, :dp1_ocrcode1, :business, :branch)";

            $resInsertDetail = $this->ci->pedeo->insertRow($sqlDetail, array(
                
                ':dp1_docnum'       => $resInsert, 
                ':dp1_docentry'     => $detail[$prefijoD."_docentry"], 
                ':dp1_numref'       => $detail[$prefijoD."_numref"], 
                ':dp1_docdate'      => $detail[$prefijoD."_docdate"], 
                ':dp1_vlrtotal'     => $detail[$prefijoD."_vlrtotal"], 
                ':dp1_vlrpaid'      => $detail[$prefijoD."_vlrpaid"], 
                ':dp1_comments'     => $detail[$prefijoD."_comments"], 
                ':dp1_porcdiscount' => $detail[$prefijoD."_porcdiscount"], 
                ':dp1_doctype'      => $detail[$prefijoD."_doctype"], 
                ':dp1_docduedate'   => $detail[$prefijoD."_docduedate"], 
                ':dp1_daysbackw'    => $detail[$prefijoD."_daysbackw"], 
                ':dp1_vlrdiscount'  => $detail[$prefijoD."_vlrdiscount"], 
                ':dp1_ocrcode'      => $detail[$prefijoD."_ocrcode"], 
                ':dp1_ocrcode1'     => $detail[$prefijoD."_ocrcode1"], 
                ':business'         => $detail["business"], 
                ':branch'           => $detail["branch"]
            ));

            if ( is_numeric($resInsertDetail) && $resInsertDetail > 0 ){

            }else{
                return $respuesta = array(
                    'error'   => true,
                    'data'    => $resInsertDetail,
                    'mensaje' => 'No se pudo crear la devolución del pago'
                );
            }


            // CAMBIANDO AFECTACION DE DOCUMENTOS
            // SEGUN EL TIPO DE DOCUMENTO
            $TipoDoc = $detail[$prefijoD."_doctype"];
            $DocEntry = $detail[$prefijoD."_docentry"];

            $valuess = []; 
            $pf = "dvf";
            $tb = "dvfv";
            $op = 0;
            
          
            switch ( $TipoDoc ) {

                case 5: 
                    $pf = "dvf";
                    $tb = "dvfv";
                    $op = 1;
                    break;
                case 6: 
                    $pf = "vnc";
                    $tb = "dvnc";
                    $op = 1;
                    break;
                case 7: 

                    $op = 1;
                    break;

                case 20:
                    $pf = "bpr";
                    $tb = "gbpr";
                    $op = 1;
                    $DocEntry = $detail[$prefijoD."_docnum"];
                    break;
                case 15: 
                    $pf = "cfc";
                    $tb = "dcfc";
                    $op = 2;
                    break;
                case 16: 
                    $pf = "cnc";
                    $tb = "dcnc";
                    $op = 2;
                    break;
                case 17:

                    $op = 2;
                    break;

                case 19: 
                    $pf = "bpe";
                    $tb = "gbpe";
                    $op = 2;
                    $DocEntry = $detail[$prefijoD."_docnum"];
                    break;
                case 36:
                    $pf = "csa";
                    $tb = "dcsa";
                    $op = 2;
                    break;
            }

            $valuess = $this->ci->generic->getBalance($DocEntry, $TipoDoc, $tb, $pf, $detail[$prefijoD."_vlrpaid"],$resCabecera[0][$prefijoC."_currency"], $resCabecera[0][$prefijoC."_docdate"], $op, 0 );

            if ( isset($valuess["error"]) && $valuess["error"] == false ){

            }else{

                return $respuesta = array(
                    'error'   => true,
                    'data'    => $valuess,
                    'mensaje' => 'No se pudo crear la devolución del pago'
                );
            }


            if ( $TipoDoc == 19 ) {
                // ACTUALIZAR CONCILIACIÓN CONTABLE
                // CUANDO ES ANTICIPO CLIENTE
                $vlrop = $valuess['vlrop'];
                $sql = "";
                if ( isset($resDetalle[1]) ) { // SI HAY MAS DE UN DETALLE
                    // HAY QUE VALIDAR QUE EL ANTICIPO NO SE USO EN OTRO DOCUMENTO
                    // SE DEBE CERRAR EL DOCUMENTO

                }

                $sql = " UPDATE mac1 SET ac1_ven_credit = ac1_ven_credit - :ac1_ven_credit WHERE ac1_font_key = :ac1_font_key AND ac1_font_type = :ac1_font_type AND ac1_debit != :ac1_debit";
                $resSql = $this->ci->pedeo->updateRow($sql, array(
                    ':ac1_font_key'   => $DocEntry,
                    ':ac1_font_type'  => $TipoDoc,
                    ':ac1_debit'      => 0,
                    ':ac1_ven_credit' => $vlrop
                ));


            }


            if ( $TipoDoc == 20 ) {
                // ACTUALIZAR CONCILIACIÓN CONTABLE
                // CUANDO ES ANTICIPO CLIENTE
                $vlrop = $valuess['vlrop'];
                $sql = "";
                if ( isset($resDetalle[1]) ) { // SI HAY MAS DE UN DETALLE
                    // HAY QUE VALIDAR QUE EL ANTICIPO NO SE USO EN OTRO DOCUMENTO
                    // SE DEBE CERRAR EL DOCUMENTO

                }

                $sql = " UPDATE mac1 SET ac1_ven_debit = ac1_ven_debit - :ac1_ven_debit WHERE ac1_font_key = :ac1_font_key AND ac1_font_type = :ac1_font_type AND ac1_debit != :ac1_credit";
                $resSql = $this->ci->pedeo->updateRow($sql, array(
                    ':ac1_font_key'   => $DocEntry,
                    ':ac1_font_type'  => $TipoDoc,
                    ':ac1_credit'      => 0,
                    ':ac1_ven_debit' => $vlrop
                ));


            }

            if ( $TipoDoc == 5 ) {

                $sql = " UPDATE mac1 SET ac1_ven_credit = ac1_ven_credit - :ac1_ven_credit WHERE ac1_font_key = :ac1_font_key AND ac1_font_type = :ac1_font_type AND ac1_debit != :ac1_debit  AND associated_account(ac1_account) = :numberr";
                $resSql = $this->ci->pedeo->updateRow($sql, array(
                    ':ac1_font_key'   => $DocEntry,
                    ':ac1_font_type'  => $TipoDoc,
                    ':ac1_debit'      => 0,
                    ':ac1_ven_credit' => $vlrop,
                    ':numberr'        => 1
                ));

               

            }











            return print_r($valuess);



               
                
            



    




        }

        print_r($resSerie);exit;


       

        return $respuesta;

    }

}