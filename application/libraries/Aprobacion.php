<?php
defined('BASEPATH') or exit('No direct script access allowed');


class Aprobacion
{

    private $ci;
    private $pdo;

    public function __construct()
    {

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        $this->ci = &get_instance();
        $this->ci->load->database();
        $this->pdo = $this->ci->load->database('pdo', true)->conn_id;
        $this->ci->load->library('pedeo', [$this->pdo]);
    }


    public function ValidModelAprobacion( $Data, $Detail, $prefijoe, $prefijod, $Business, $Branch ) {

        // Cantidad de modelos participantes
        $ModelPart = [];
        $MauQ = 0;
        $MauQA = 0;



        $sqlDocModelo = "SELECT mau_docentry as modelo, mau_doctype as doctype, mau_quantity as cantidad,
                        au1_doctotal as doctotal,au1_doctotal2 as doctotal2, au1_c1 as condicion,au1_is_query as consulta,au1_query as query,mau_currency
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
                        AND tmau.business = :business
                        AND aus_status = :aus_status";

        $resDocModelo = $this->ci->pedeo->queryTable($sqlDocModelo, array(

            ':mau_doctype'   => $Data[$prefijoe.'_doctype'],
            ':pgu_code_user' => $Data[$prefijoe.'_createby'],
            ':mau_status' 	 => 1,
            ':aus_status' 	 => 1,
            ':business'		 => $Data['business']

        ));


        if (isset($resDocModelo[0])) {

            foreach ($resDocModelo as $key => $value) {

                //VERIFICAR MODELO DE APROBACION
                $query = $value['consulta'];
                $condicion = $value['condicion'];
                $valorDocTotal1 = $value['doctotal'];
                $valorDocTotal2 = $value['doctotal2'];
                $TotalDocumento = $Data[$prefijoe.'_doctotal'];
                $doctype =  $value['doctype'];
                $modelo = $value['modelo'];

                $sqlTasaMonedaModelo = "SELECT COALESCE(get_dynamic_conversion(:mau_currency,:doc_currency,:doc_date,:doc_total,get_localcur()), 0) AS monto"; 
                $resTasaMonedaModelo = $this->ci->pedeo->queryTable($sqlTasaMonedaModelo, array(
                    ':mau_currency' => $value['mau_currency'],
                    ':doc_currency' => $Data[$prefijoe.'_currency'],
                    ':doc_date' 	=> $Data[$prefijoe.'_docdate'],
                    ':doc_total' 	=> $TotalDocumento
                ));

                if ( $resTasaMonedaModelo[0]['monto'] == 0 ){
                    
                }else if ($resTasaMonedaModelo[0]['monto'] <> 0 ){
                    
                }else{
                    $respuesta = array(
                        'error' => true,
                        'data'  => array(),
                        'mensaje' => 'No se encrontro la tasa de cambio para la moneda del modelo :'. $value['mau_currency'].'en la fecha del documento '.$Data['csc_docdate']
                    );
        
        
                    return $respuesta;
                }

                $TotalDocumento =  $resTasaMonedaModelo[0]['monto'];

                if ($condicion == ">" && $query != 1) {

                    $sq = " SELECT mau_quantity,mau_approvers,mau_docentry
                            FROM tmau
                            INNER JOIN  mau1
                            on mau_docentry =  au1_docentry
                            AND :au1_doctotal > au1_doctotal
                            AND mau_doctype = :mau_doctype
                            AND mau_docentry = :mau_docentry";

                    $ressq = $this->ci->pedeo->queryTable($sq, array(

                        ':au1_doctotal' => $TotalDocumento,
                        ':mau_doctype'  => $doctype,
                        ':mau_docentry' => $modelo
                    ));

                    if (isset($ressq[0])) {

                        array_push( $ModelPart, $ressq[0]['mau_docentry'] );
                        $MauQ = $MauQ + $ressq[0]['mau_quantity'];
                        $MauQA = ($MauQA + count(explode(',', $ressq[0]['mau_approvers'])));
                        
                    }

                } else if ($condicion == "BETWEEN" && $query != 1) {

                    $sq = " SELECT mau_quantity,mau_approvers,mau_docentry
                            FROM tmau
                            INNER JOIN  mau1
                            on mau_docentry =  au1_docentry
                            AND cast(:doctotal as numeric) between au1_doctotal AND au1_doctotal2
                            AND mau_doctype = :mau_doctype
                            AND mau_docentry = :mau_docentry";

                    $ressq = $this->ci->pedeo->queryTable($sq, array(

                        ':doctotal' 	=> $TotalDocumento,
                        ':mau_doctype'  => $doctype,
                        ':mau_docentry' => $modelo
                    ));

                    if (isset($ressq[0])) {
                        
                        array_push( $ModelPart, $ressq[0]['mau_docentry'] );
                        $MauQ = $MauQ + $ressq[0]['mau_quantity'];
                        $MauQA = ($MauQA + count(explode(',', $ressq[0]['mau_approvers'])));
                    }

                }else if ($query == 1){
                    $sq = " SELECT mau_quantity,mau_approvers,mau_docentry
                            FROM tmau
                            INNER JOIN  mau1
                            on mau_docentry =  au1_docentry
                            AND mau_doctype = :mau_doctype
                            AND mau_docentry = :mau_docentry";

                    $ressq = $this->ci->pedeo->queryTable($sq, array(
                        ':mau_doctype'  => $doctype,
                        ':mau_docentry' => $modelo
                    ));

                    $queryValid = explode(";;",$value['query']);
                    $sql = $queryValid[0];
                    $campos = $queryValid[1];
                    $arrayCampos = explode(",",$campos);						

                    foreach ($arrayCampos as $key => $string) {
                        if(substr($string,0,2) == "d_"){
                            foreach ($Detail as $key => $detail_string) {
                                $dstring = str_replace("d_","",$string);
                                
                                $sql = str_replace("{d_".$string."}",$detail_string[$dstring],$sql);
                            }
                            
                        }else{
                            $sql = str_replace("{".$string."}",$Data[$string],$sql);
                        }

                    }

                    $resSqlValid = $this->ci->pedeo->queryTable($sql,array());
                    
                    if(isset($resSqlValid[0])){

                        array_push( $ModelPart, $ressq[0]['mau_docentry'] );
                        $MauQ = $MauQ + $ressq[0]['mau_quantity'];
                        $MauQA = ($MauQA + count(explode(',', $ressq[0]['mau_approvers'])));
                    }
                }
                //VERIFICAR MODELO DE PROBACION
            }
            // SI EXISTE UN MODELO PARTICIPANTE
            if ( isset( $ModelPart[0] ) ) {

                $resAprobacion = $this->setAprobacion($Data, $Detail, $prefijoe, $prefijod, $MauQ, $MauQA, $ModelPart, $Data['business'], $Data['branch']);
                if ($resAprobacion['error'] == false){
    
                    $respuesta = array(
                        'error'   => false,
                        'data'    => 1,
                        'mensaje' => $resAprobacion['mensaje'],
                        
                    );
    
                    return $respuesta;
    
                }else{
    
                    $respuesta = array(
                        'error'   => true,
                        'data'    => $resAprobacion,
                        'mensaje' => $resAprobacion['mensaje'],
                        
                    );
    
                    return $respuesta;
    
                }
            }

        }else{

            return $respuesta = array(
                'error'   => false,
                'data'    => 0,
                'mensaje' => "",
                
            );
        }
    }

    private function setAprobacion($Encabezado, $Detalle, $prefijoe, $prefijod, $Cantidad, $CantidadAP, $Model, $Business, $Branch)
    {


        $sqlInsert = "INSERT INTO dpap(pap_series, pap_docnum, pap_docdate, pap_duedate, pap_duedev, pap_pricelist, pap_cardcode,
        pap_cardname, pap_currency, pap_contacid, pap_slpcode, pap_empid, pap_comment, pap_doctotal, pap_baseamnt, pap_taxtotal,
        pap_discprofit, pap_discount, pap_createat, pap_baseentry, pap_basetype, pap_doctype, pap_idadd, pap_adress, pap_paytype,
        pap_createby,pap_origen,pap_qtyrq,pap_qtyap,pap_model,pap_correl, pap_date_inv, pap_date_del, pap_place_del, business, branch,
        pap_totalret, pap_totalretiva, pap_tax_control_num,pap_anticipate_value,pap_anticipate_total,pap_anticipate_type,pap_internal_comments,pap_correl2,
        pap_taxtotal_ad)
        VALUES(:pap_series, :pap_docnum, :pap_docdate, :pap_duedate, :pap_duedev, :pap_pricelist, :pap_cardcode, :pap_cardname,
        :pap_currency, :pap_contacid, :pap_slpcode, :pap_empid, :pap_comment, :pap_doctotal, :pap_baseamnt, :pap_taxtotal, :pap_discprofit, :pap_discount,
        :pap_createat, :pap_baseentry, :pap_basetype, :pap_doctype, :pap_idadd, :pap_adress, :pap_paytype, :pap_createby,:pap_origen,:pap_qtyrq,:pap_qtyap,
        :pap_model,:pap_correl, :pap_date_inv, :pap_date_del, :pap_place_del, :business, :branch, :pap_totalret, :pap_totalretiva, :pap_tax_control_num,
        :pap_anticipate_value,:pap_anticipate_total,:pap_anticipate_type,:pap_internal_comments,:pap_correl2,:pap_taxtotal_ad)";

        // Se Inicia la transaccion,
        // Todas las consultas de modificacion siguientes
        // aplicaran solo despues que se confirme la transaccion,
        // de lo contrario no se aplicaran los cambios y se devolvera
        // la base de datos a su estado original.
        $this->ci->pedeo->trans_begin();

        $resInsert = $this->ci->pedeo->insertRow($sqlInsert, array(
            ':pap_docnum' => 0,
            ':pap_series' => isset($Encabezado[$prefijoe . '_series']) && is_numeric($Encabezado[$prefijoe . '_series']) ? $Encabezado[$prefijoe . '_series'] : 0,
            ':pap_docdate' => isset($Encabezado[$prefijoe . '_docdate']) && $this->validateDate($Encabezado[$prefijoe . '_docdate']) ? $Encabezado[$prefijoe . '_docdate'] : NULL,
            ':pap_duedate' => isset($Encabezado[$prefijoe . '_duedate']) && $this->validateDate($Encabezado[$prefijoe . '_duedate']) ? $Encabezado[$prefijoe . '_duedate'] : NULL,
            ':pap_duedev' => isset($Encabezado[$prefijoe . '_duedev']) && $this->validateDate($Encabezado[$prefijoe . '_duedev']) ? $Encabezado[$prefijoe . '_duedev'] : NULL,
            ':pap_pricelist' => isset($Encabezado[$prefijoe . '_pricelist']) && is_numeric($Encabezado[$prefijoe . '_pricelist']) ? $Encabezado[$prefijoe . '_pricelist'] : 0,
            ':pap_cardcode' => isset($Encabezado[$prefijoe . '_cardcode']) ? $Encabezado[$prefijoe . '_cardcode'] : NULL,
            ':pap_cardname' => isset($Encabezado[$prefijoe . '_cardname']) ? $Encabezado[$prefijoe . '_cardname'] : NULL,
            ':pap_currency' => isset($Encabezado[$prefijoe . '_currency']) ? $Encabezado[$prefijoe . '_currency'] : NULL,
            ':pap_contacid' => isset($Encabezado[$prefijoe . '_contacid']) ? $Encabezado[$prefijoe . '_contacid'] : NULL,
            ':pap_slpcode' => isset($Encabezado[$prefijoe . '_slpcode']) && is_numeric($Encabezado[$prefijoe . '_slpcode']) ? $Encabezado[$prefijoe . '_slpcode'] : 0,
            ':pap_empid' => isset($Encabezado[$prefijoe . '_empid']) && is_numeric($Encabezado[$prefijoe . '_empid']) ? $Encabezado[$prefijoe . '_empid'] : 0,
            ':pap_comment' => isset($Encabezado[$prefijoe . '_comment']) ? $Encabezado[$prefijoe . '_comment'] : NULL,
            ':pap_doctotal' => is_numeric($Encabezado[$prefijoe . '_doctotal']) ? $Encabezado[$prefijoe . '_doctotal'] : 0,
            ':pap_baseamnt' => is_numeric($Encabezado[$prefijoe . '_baseamnt']) ? $Encabezado[$prefijoe . '_baseamnt'] : 0,
            ':pap_taxtotal' => is_numeric($Encabezado[$prefijoe . '_taxtotal']) ? $Encabezado[$prefijoe . '_taxtotal'] : 0,
            ':pap_discprofit' => is_numeric($Encabezado[$prefijoe . '_discprofit']) ? $Encabezado[$prefijoe . '_discprofit'] : 0,
            ':pap_discount' => is_numeric($Encabezado[$prefijoe . '_discount']) ? $Encabezado[$prefijoe . '_discount'] : 0,
            ':pap_createat' => isset($Encabezado[$prefijoe . '_createat']) && $this->validateDate($Encabezado[$prefijoe . '_createat']) ? $Encabezado[$prefijoe . '_createat'] : NULL,
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
            ':pap_model' => json_encode($Model),
            ':pap_correl' => isset($Encabezado[$prefijoe . '_correl']) ? $Encabezado[$prefijoe . '_correl'] : NULL,
            ':pap_date_inv' => isset($Encabezado[$prefijoe . '_date_inv']) && $this->validateDate($Encabezado[$prefijoe . '_date_inv']) ? $Encabezado[$prefijoe . '_date_inv'] : NULL,
            ':pap_date_del' => isset($Encabezado[$prefijoe . '_date_del']) && $this->validateDate($Encabezado[$prefijoe . '_date_del']) ? $Encabezado[$prefijoe . '_date_del'] : NULL,
            ':pap_place_del' => isset($Encabezado[$prefijoe . '_place_del']) ? $Encabezado[$prefijoe . '_place_del'] : NULL,
            ':business' => $Business,
            ':branch' => $Branch,
            ':pap_totalret' => isset($Encabezado[$prefijoe . '_totalret']) && is_numeric($Encabezado[$prefijoe . '_totalret'])  ? $Encabezado[$prefijoe . '_totalret'] : NULL,
            ':pap_totalretiva' => isset($Encabezado[$prefijoe . '_totalretiva']) && is_numeric($Encabezado[$prefijoe . '_totalretiva'])  ? $Encabezado[$prefijoe . '_totalretiva'] : NULL,
            ':pap_tax_control_num' => isset($Encabezado[$prefijoe . '_tax_control_num']) ? $Encabezado[$prefijoe . '_tax_control_num'] : NULL,
            ':pap_anticipate_value' => isset($Encabezado[$prefijoe . '_anticipate_value']) && is_numeric($Encabezado[$prefijoe . '_anticipate_value']) ? $Encabezado[$prefijoe . '_anticipate_value'] : 0,
            ':pap_anticipate_total' => isset($Encabezado[$prefijoe . '_anticipate_total']) && is_numeric($Encabezado[$prefijoe . '_anticipate_value']) ? $Encabezado[$prefijoe . '_anticipate_total'] : 0,
            ':pap_anticipate_type' => isset($Encabezado[$prefijoe . '_anticipate_type']) && is_numeric($Encabezado[$prefijoe . '_anticipate_value']) ? $Encabezado[$prefijoe . '_anticipate_type'] : 0,
            ':pap_internal_comments' => isset($Encabezado[$prefijoe . '_internal_comments']) ? $Encabezado[$prefijoe . '_internal_comments'] : NULL,
            ':pap_correl2' => isset($Encabezado[$prefijoe . '_correl2']) ? $Encabezado[$prefijoe . '_correl2'] : NULL,

            ':pap_taxtotal_ad' => is_numeric($Encabezado[$prefijoe . '_taxtotal_ad']) ? $Encabezado[$prefijoe . '_taxtotal_ad'] : 0

        ));


        if (is_numeric($resInsert) && $resInsert > 0) {

            //SE INSERTA EL ESTADO DEL DOCUMENTO

            $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                                VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

            $resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(


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

                $this->ci->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data' => $resInsertEstado,
                    'mensaje'    => 'No se pudo registrar el documento'
                );


                $this->response($respuesta);

                return;
            }

            //FIN PROCESO ESTADO DEL DOCUMENTO

            foreach ($Detalle as $key => $detail) {

                $sqlInsertDetail = "INSERT INTO pap1(ap1_docentry, ap1_itemcode, ap1_itemname, ap1_quantity, ap1_uom, ap1_whscode,
                    ap1_price, ap1_vat, ap1_vatsum, ap1_discount, ap1_linetotal, ap1_costcode, ap1_ubusiness, ap1_project,
                    ap1_acctcode, ap1_basetype, ap1_doctype, ap1_avprice, ap1_inventory, ap1_linenum, ap1_acciva, ap1_codimp,
                    business, branch, ap1_whscode_dest, ap1_ubication, ap1_baseline, ote_code,detalle_modular,ap1_tax_base,detalle_anuncio,
                    ap1_clean_quantity,ap1_vat_ad,ap1_vatsum_ad,ap1_accimp_ad,ap1_codimp_ad)
                    VALUES(:ap1_docentry, :ap1_itemcode, :ap1_itemname, :ap1_quantity,:ap1_uom, :ap1_whscode,:ap1_price, :ap1_vat,
                    :ap1_vatsum, :ap1_discount, :ap1_linetotal, :ap1_costcode, :ap1_ubusiness, :ap1_project, :ap1_acctcode, :ap1_basetype,
                    :ap1_doctype, :ap1_avprice, :ap1_inventory,:ap1_linenum,:ap1_acciva,:ap1_codimp,:business,:branch,:ap1_whscode_dest,
                    :ap1_ubication, :ap1_baseline, :ote_code,:detalle_modular,:ap1_tax_base,:detalle_anuncio,:ap1_clean_quantity,
                    :ap1_vat_ad,:ap1_vatsum_ad,:ap1_accimp_ad,:ap1_codimp_ad)";

                $resInsertDetail = $this->ci->pedeo->insertRow($sqlInsertDetail, array(
                    ':ap1_docentry' => $resInsert,
                    ':ap1_itemcode' => isset($detail[$prefijod . '_itemcode']) ? $detail[$prefijod . '_itemcode'] : NULL,
                    ':ap1_itemname' => isset($detail[$prefijod . '_itemname']) ? $detail[$prefijod . '_itemname'] : NULL,
                    ':ap1_quantity' => isset($detail[$prefijod . '_quantity']) && is_numeric($detail[$prefijod . '_quantity']) ? $detail[$prefijod . '_quantity'] : 0,
                    ':ap1_uom' => isset($detail[$prefijod . '_uom']) ? $detail[$prefijod . '_uom'] : NULL,
                    ':ap1_whscode' => isset($detail[$prefijod . '_whscode']) ? $detail[$prefijod . '_whscode'] : NULL,
                    ':ap1_price' => isset($detail[$prefijod . '_price']) && is_numeric($detail[$prefijod . '_price']) ? $detail[$prefijod . '_price'] : 0,
                    ':ap1_vat' => isset($detail[$prefijod . '_vat']) && is_numeric($detail[$prefijod . '_vat']) ? $detail[$prefijod . '_vat'] : 0,
                    ':ap1_vatsum' => isset($detail[$prefijod . '_vatsum']) && is_numeric($detail[$prefijod . '_vatsum']) ? $detail[$prefijod . '_vatsum'] : 0,
                    ':ap1_discount' => isset($detail[$prefijod . '_discount']) && is_numeric($detail[$prefijod . '_discount']) ? $detail[$prefijod . '_discount'] : 0,
                    ':ap1_linetotal' => isset($detail[$prefijod . '_linetotal']) && is_numeric($detail[$prefijod . '_linetotal']) ? $detail[$prefijod . '_linetotal'] : 0,
                    ':ap1_costcode' => isset($detail[$prefijod . '_costcode']) ? $detail[$prefijod . '_costcode'] : NULL,
                    ':ap1_ubusiness' => isset($detail[$prefijod . '_ubusiness']) ? $detail[$prefijod . '_ubusiness'] : NULL,
                    ':ap1_project' => isset($detail[$prefijod . '_project']) ? $detail[$prefijod . '_project'] : NULL,
                    ':ap1_acctcode' => isset($detail[$prefijod . '_acctcode']) && is_numeric($detail[$prefijod . '_acctcode']) ? $detail[$prefijod . '_acctcode'] : 0,
                    ':ap1_basetype' => isset($detail[$prefijod . '_basetype']) && is_numeric($detail[$prefijod . '_basetype']) ? $detail[$prefijod . '_basetype'] : 0,
                    ':ap1_doctype' => isset($detail[$prefijod . '_doctype']) && is_numeric($detail[$prefijod . '_doctype']) ? $detail[$prefijod . '_doctype'] : 0,
                    ':ap1_avprice' => isset($detail[$prefijod . '_avprice']) && is_numeric($detail[$prefijod . '_avprice']) ? $detail[$prefijod . '_avprice'] : 0,
                    ':ap1_inventory' => isset($detail[$prefijod . '_inventory']) && is_numeric($detail[$prefijod . '_inventory']) ? $detail[$prefijod . '_inventory'] : NULL,
                    ':ap1_linenum' => isset($detail[$prefijod . '_linenum']) && is_numeric($detail[$prefijod . '_linenum']) ? $detail[$prefijod . '_linenum'] : NULL,
                    ':ap1_acciva' => isset($detail[$prefijod . '_acciva']) && is_numeric($detail[$prefijod . '_acciva']) ? $detail[$prefijod . '_acciva'] : NULL,
                    ':ap1_codimp' => isset($detail[$prefijod . '_codimp']) ? $detail[$prefijod . '_codimp'] : NULL,
                    ':business' => $Business,
                    ':branch' => $Branch,
                    ':ap1_whscode_dest' => isset($detail[$prefijod . '_whscode_dest']) ? $detail[$prefijod . '_whscode_dest'] : NULL,
                    ':ap1_ubication' => isset($detail[$prefijod . '_ubication']) ? $detail[$prefijod . '_ubication'] : NULL,
                    ':ap1_baseline' => isset($detail[$prefijod . '_baseline']) && is_numeric($detail[$prefijod . '_baseline']) ? $detail[$prefijod . '_baseline'] : NULL,
                    ':ote_code' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL,
                    ':detalle_modular' => (isset($detail['detalle_modular']) && !empty($detail['detalle_modular'])) ? json_encode($detail['detalle_modular']) : NULL,
                    ':ap1_tax_base' => is_numeric($detail[$prefijod . '_tax_base']) ? $detail[$prefijod . '_tax_base'] : 0,
                    ':detalle_anuncio' => (isset($detail['detalle_anuncio']) && !empty($detail['detalle_anuncio'])) ? json_encode($detail['detalle_anuncio']) : NULL,
                    ':ap1_clean_quantity' => isset($detail[$prefijod . '_clean_quantity']) && is_numeric($detail[$prefijod . '_clean_quantity']) ? $detail[$prefijod . '_clean_quantity'] : 0,

                    ':ap1_vat_ad' => isset($detail[$prefijod . '_vat_ad']) && is_numeric($detail[$prefijod . '_vat_ad']) ? $detail[$prefijod . '_vat_ad'] : 0,
                    ':ap1_vatsum_ad' => isset($detail[$prefijod . '_vatsum_ad']) && is_numeric($detail[$prefijod . '_vatsum_ad']) ? $detail[$prefijod . '_vatsum_ad'] : 0,
                    ':ap1_accimp_ad' => isset($detail[$prefijod . '_accimp_ad']) && is_numeric($detail[$prefijod . '_accimp_ad']) ? $detail[$prefijod . '_accimp_ad'] : NULL,
                    ':ap1_codimp_ad' => isset($detail[$prefijod . '_codimp_ad']) ? $detail[$prefijod . '_codimp_ad'] : NULL
                ));

                if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {
                    // Se verifica que el detalle no de error insertando //
                } else {

                    // si falla algun insert del detalle de la cotizacion se devuelven los cambios realizados por la transaccion,
                    // se retorna el error y se detiene la ejecucion del codigo restante.
                    $this->ci->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data' => $resInsertDetail,
                        'mensaje'    => 'No se pudo registrar el documento'
                    );

                    return $respuesta;
                }
            }


            // Si todo sale bien despues de insertar el detalle de la cotizacion
            // se confirma la trasaccion  para que los cambios apliquen permanentemente
            // en la base de datos y se confirma la operacion exitosa.
            $this->ci->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' => 'El documento fue creado, pero es necesario que sea aprobado'
            );

            return $respuesta;

        } else {

            $this->ci->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $resInsert,
                'mensaje'    => 'No se pudeo crear el documento'
            );

            return $respuesta;
        }
    }

    public function CambiarAnexos($Data, $prefijoe, $Docnum)
    {

        // $Data = DATOS DE LA CABECERA DEL DOCUMENTO
        // $prefijoe =  pregfijo del encabezado de la tabla
        // $Donum = numeracion del documento actual creando

        // PROCESO PARA CAMBIAR LOS ANEXOS AGREGADOS A UN DOCUMENTO PRELIMINAR

        $respuesta = array(
            "error"   => false,
            "data"    => [],
            "mensage" => 'ok'
        );

        $anexo = $this->ci->pedeo->queryTable("SELECT * FROM axsl WHERE code = :code AND type = :type", array(":code" => $Data[$prefijoe.'_baseentry'], ":type" => $Data[$prefijoe.'_basetype']));

        if ( isset($anexo[0]) ) {

            $update = $this->ci->pedeo->updateRow("UPDATE axsl SET code = :code, type = :type WHERE code = :cod AND type = :typ", array(
                ":cod" => $Data[$prefijoe.'_baseentry'],
                ":typ" => $Data[$prefijoe.'_basetype'],
                ":code" => $Docnum,
                ":type" => $Data[$prefijoe.'_doctype']
            )); 

            if (is_numeric($update) && $update > 0) {
            }else{
                $respuesta = array(
                    "error"   => true,
                    "data"    => $update,
                    "mensage" => 'Error al actualizar el anexo'
                );
            }

        } else {

            $anexo = $this->ci->pedeo->queryTable("SELECT * FROM axpc WHERE code = :code AND type = :type", array(":code" => $Data[$prefijoe.'_baseentry'], ":type" => $Data[$prefijoe.'_basetype']));
            
            if ( isset($anexo[0]) ) {

                $update = $this->ci->pedeo->updateRow("UPDATE axpc SET code = :code, type = :type WHERE code = :cod AND type = :typ", array(
                    ":cod" => $Data[$prefijoe.'_baseentry'],
                    ":typ" => $Data[$prefijoe.'_basetype'],
                    ":code" => $Docnum,
                    ":type" => $Data[$prefijoe.'_doctype']
                )); 
    
                if (is_numeric($update) && $update > 0){
                }else{
                    $respuesta = array(
                        "error"   => true,
                        "data"    => $update,
                        "mensage" => 'Error al actualizar el anexo'
                    );
                }
            } 
        }

        return $respuesta;
        // FIN DE PROCESO
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
