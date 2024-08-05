<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory {

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

    }
  

    public function InventoryOutput($Encabezado, $Detalle, $resInsert, $DocNumVerificado) {

        $ManejaLote = 0; // SI EL ARTICULO MANEJA LOTE

        // SI EL ARTICULO MANEJA LOTE
        $sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
        $resLote = $this->ci->pedeo->queryTable($sqlLote, array(
            ':dma_item_code'   => $Detalle['rc1_itemcode'],
            ':dma_lotes_code'  => 1
        ));

        if (isset($resLote[0])) {
            $ManejaLote = 1;
        } else {
            $ManejaLote = 0;
        }


        // SI EL ARTICULO MANEJA LOTE SE BUSCA POR LOTE Y ALMACEN
        $sqlCostoMomentoRegistro = '';
        $resCostoMomentoRegistro = [];


        // SI EL ARTICULO MANEJA LOTE
        if ($ManejaLote == 1) {

            $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
            $resCostoMomentoRegistro = $this->ci->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                ':bdi_whscode'  => $Detalle['rc1_whscode'],
                ':bdi_itemcode' => $Detalle['rc1_itemcode'],
                ':bdi_lote' 	=> $Detalle['ote_code'],
                ':business' 	=> $Encabezado['business']
            ));

        } else {

            $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
            $resCostoMomentoRegistro = $this->ci->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                ':bdi_whscode' 	=> $Detalle['rc1_whscode'],
                ':bdi_itemcode' => $Detalle['rc1_itemcode'],
                ':business' 	=> $Encabezado['business']
            ));
        }

        if (isset($resCostoMomentoRegistro[0])) {
            //VALIDANDO CANTIDAD DE ARTICULOS
            $CANT_ARTICULOEX = $resCostoMomentoRegistro[0]['bdi_quantity'];
            $CANT_ARTICULOLN = is_numeric($Detalle['rc1_quantity']) ? $Detalle['rc1_quantity'] : 0;

            if (($CANT_ARTICULOEX - $CANT_ARTICULOLN) < 0) {
                return array('error' => true, 'mensaje'	=> 'no puede crear el documento porque el articulo ' . $Detalle['rc1_itemcode'] . ' recae en inventario negativo (' . ($CANT_ARTICULOEX - $CANT_ARTICULOLN) . ')');
            }

            //VALIDANDO CANTIDAD DE ARTICULOS
            $sqlInserMovimiento = '';
            $resInserMovimiento = [];

            //Se aplica el movimiento de inventario
            $sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment, bmi_lote, bmi_ubication,business)
            VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote, :bmi_ubication,:business)";

            $resInserMovimiento = $this->ci->pedeo->insertRow($sqlInserMovimiento, array (

                ':bmi_itemcode' => isset($Detalle['rc1_itemcode']) ? $Detalle['rc1_itemcode'] : NULL,
                ':bmi_quantity' => is_numeric($Detalle['rc1_quantity']) ? ($Detalle['rc1_quantity'] * -1) : 0,
                ':bmi_whscode'  => isset($Detalle['rc1_whscode']) ? $Detalle['rc1_whscode'] : NULL,
                ':bmi_createat' => $this->validateDate($Encabezado['vrc_createat']) ? $Encabezado['vrc_createat'] : NULL,
                ':bmi_createby' => isset($Encabezado['vrc_createby']) ? $Encabezado['vrc_createby'] : NULL,
                ':bmy_doctype'  => is_numeric($Encabezado['vrc_doctype']) ? $Encabezado['vrc_doctype'] : 0,
                ':bmy_baseentry' => $resInsert,
                ':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
                ':bmi_currequantity' => $resCostoMomentoRegistro[0]['bdi_quantity'],
                ':bmi_basenum'	=> $DocNumVerificado,
                ':bmi_docdate' => $this->validateDate($Encabezado['vrc_docdate']) ? $Encabezado['vrc_docdate'] : NULL,
                ':bmi_duedate' => $this->validateDate($Encabezado['vrc_duedate']) ? $Encabezado['vrc_duedate'] : NULL,
                ':bmi_duedev'  => $this->validateDate($Encabezado['vrc_duedev']) ? $Encabezado['vrc_duedev'] : NULL,
                ':bmi_comment' => isset($Encabezado['vrc_comment']) ? $Encabezado['vrc_comment'] : NULL,
                ':bmi_lote' => isset($Detalle['ote_code']) ? $Detalle['ote_code'] : NULL,
                ':bmi_ubication' => isset($Detalle['rc1_ubication']) ? $Detalle['rc1_ubication'] : NULL,
                ':business' => isset($Encabezado['business']) ? $Encabezado['business'] : NULL
            ));

            if (is_numeric($resInserMovimiento) && $resInserMovimiento > 0) {
                // Se verifica que el detalle no de error insertando //
            } else {
                return array('error' => true, 'mensaje' => 'No se pudo registrar el movimiento de inventario');                
            }


            $sqlCostoCantidad = '';
            $resCostoCantidad = [];
            // SI EL ARTICULO MANEJA LOTE
            if ($ManejaLote == 1) {
                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                                FROM tbdi
                                WHERE bdi_itemcode = :bdi_itemcode
                                AND bdi_whscode = :bdi_whscode
                                AND bdi_lote = :bdi_lote
                                AND business = :business";

                $resCostoCantidad =  $this->ci->pedeo->queryTable($sqlCostoCantidad, array(

                    ':bdi_itemcode' => $Detalle['rc1_itemcode'],
                    ':bdi_whscode'  => $Detalle['rc1_whscode'],
                    ':bdi_lote'		=> $Detalle['ote_code'],
                    ':business' 	=> $Encabezado['business']
                ));
            } else {
                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                                FROM tbdi
                                WHERE bdi_itemcode = :bdi_itemcode
                                AND bdi_whscode = :bdi_whscode
                                AND business = :business";

                $resCostoCantidad =  $this->ci->pedeo->queryTable($sqlCostoCantidad, array(

                    ':bdi_itemcode' => $Detalle['rc1_itemcode'],
                    ':bdi_whscode'  => $Detalle['rc1_whscode'],
                    ':business' => $Encabezado['business']
                ));
            }


            if (isset($resCostoCantidad[0])) {

                $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
                $CostoActual    = $resCostoCantidad[0]['bdi_avgprice'];

                $CantidadDevolucion = $Detalle['rc1_quantity'];
                $CostoDevolucion = $Detalle['rc1_price'];

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

                    return array('error'=> false, 'mensaje' => "ok");

                } else {
                    return array('error'   => true, 'mensaje'	=> 'No se pudo registrar el movimiento en el stock');
                }                        

            }else{
                return array( 'error'   => true, 'mensaje'	=> 'El item no existe en el stock ' . $Detalle['rc1_itemcode']);
            }

        }else{
            return array("error" => true, "mensaje" => "No se encontro el costo del articulo ".$Detalle['rc1_itemcode']);
        }
    }

    public function InventoryEntry($Encabezado, $Detalle, $resInsert, $DocNumVerificado){
        $ManejaLote = 0; // SI EL ARTICULO MANEJA LOTE
        // SI MANEJA LOTE
        $sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
        $resLote = $this->ci->pedeo->queryTable($sqlLote, array(
            ':dma_item_code' => $Detalle['rc1_itemcode'],
            ':dma_lotes_code'  => 1
        ));

        if (isset($resLote[0])) {
            $ManejaLote = 1;
        } else {
            $ManejaLote = 0;
        }

        if ( $ManejaLote == 1 ) {

            $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_lote = :bdi_lote AND business = :business";
            $resCostoMomentoRegistro = $this->ci->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                ':bdi_whscode' => $Detalle['rc1_whscode'],
                ':bdi_itemcode' => $Detalle['rc1_itemcode'],
                ':bdi_lote' => $Detalle['ote_code'],
                ':business' => $Encabezado['business']
            ));
        } else {

            $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
            $resCostoMomentoRegistro = $this->ci->pedeo->queryTable($sqlCostoMomentoRegistro, array(
                ':bdi_whscode' => $Detalle['rc1_whscode'],
                ':bdi_itemcode' => $Detalle['rc1_itemcode'],
                ':business' => $Encabezado['business']
            ));
        }

        if (isset($resCostoMomentoRegistro[0])) {

            $sqlInserMovimiento = '';
            $resInserMovimiento = [];
            
        
            $sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_lote,bmi_ubication,business)
                                    VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_lote,:bmi_ubication,:business)";
            $resInserMovimiento = $this->ci->pedeo->insertRow($sqlInserMovimiento, array(

                ':bmi_itemcode'  => isset($Detalle['rc1_itemcode']) ? $Detalle['rc1_itemcode'] : NULL,
                ':bmi_quantity'  => is_numeric($Detalle['rc1_quantity']) ? ($Detalle['rc1_quantity'] * 1) : 0,
                ':bmi_whscode'   => isset($Detalle['rc1_whscode']) ? $Detalle['rc1_whscode'] : NULL,
                ':bmi_createat'  => $this->validateDate($Encabezado['vrc_createat']) ? $Encabezado['vrc_createat'] : NULL,
                ':bmi_createby'  => isset($Encabezado['vrc_createby']) ? $Encabezado['vrc_createby'] : NULL,
                ':bmy_doctype'   => is_numeric($Encabezado['vrc_doctype']) ? $Encabezado['vrc_doctype'] : 0,
                ':bmy_baseentry' => $resInsert,
                ':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
                ':bmi_currequantity' 	=> $resCostoMomentoRegistro[0]['bdi_quantity'],
                ':bmi_basenum'			=> $DocNumVerificado,
                ':bmi_docdate' => $this->validateDate($Encabezado['vrc_docdate']) ? $Encabezado['vrc_docdate'] : NULL,
                ':bmi_duedate' => $this->validateDate($Encabezado['vrc_duedate']) ? $Encabezado['vrc_duedate'] : NULL,
                ':bmi_duedev'  => $this->validateDate($Encabezado['vrc_duedev']) ? $Encabezado['vrc_duedev'] : NULL,
                ':bmi_comment' => isset($Encabezado['vrc_comment']) ? $Encabezado['vrc_comment'] : NULL,
                ':bmi_lote' => isset($Detalle['ote_code']) ? $Detalle['ote_code'] : NULL,
                ':bmi_ubication' => isset($Detalle['rc1_ubication']) ? $Detalle['rc1_ubication'] : NULL,
                ':business' => isset($Encabezado['business']) ? $Encabezado['business'] : NULL

            ));

            
            if (is_numeric($resInserMovimiento) && $resInserMovimiento > 0) {
                
            } else {
               return array('error' => true, 'mensaje' => 'No se pudo registrar el movimiento de inventario');
            }


            $sqlCostoCantidad = '';
            $resCostoCantidad = [];
            // SI EL ARTICULO MANEJA LOTE
            if ($ManejaLote == 1) {
                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                                FROM tbdi
                                WHERE bdi_itemcode = :bdi_itemcode
                                AND bdi_whscode = :bdi_whscode
                                AND bdi_lote = :bdi_lote
                                AND business = :business";

                $resCostoCantidad =  $this->ci->pedeo->queryTable($sqlCostoCantidad, array(

                    ':bdi_itemcode' => $Detalle['rc1_itemcode'],
                    ':bdi_whscode'  => $Detalle['rc1_whscode'],
                    ':bdi_lote'		=> $Detalle['ote_code'],
                    ':business' 	=> $Encabezado['business']
                ));
            } else {
                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                                FROM tbdi
                                WHERE bdi_itemcode = :bdi_itemcode
                                AND bdi_whscode = :bdi_whscode
                                AND business = :business";

                $resCostoCantidad =  $this->ci->pedeo->queryTable($sqlCostoCantidad, array(

                    ':bdi_itemcode' => $Detalle['rc1_itemcode'],
                    ':bdi_whscode'  => $Detalle['rc1_whscode'],
                    ':business' => $Encabezado['business']
                ));
            }

            if (isset($resCostoCantidad[0])) {

                $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
                $CostoActual    = $resCostoCantidad[0]['bdi_avgprice'];

                $CantidadNueva = $Detalle['rc1_quantity'];
                $CostoDevolucion = $Detalle['rc1_price'];

                $CantidadTotal = ($CantidadActual + $CantidadNueva);

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

                    return array('error'=> false, 'mensaje' => "ok");

                } else {
                    return array('error'   => true, 'mensaje'	=> 'No se pudo registrar el movimiento en el stock');
                }                        

            }else{
                return array( 'error'   => true, 'mensaje'	=> 'El item no existe en el stock ' . $Detalle['rc1_itemcode']);
            }

        }else{
            return array("error" => true, "mensaje" => "No se encontro el costo del articulo ".$Detalle['rc1_itemcode']);
        }
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


?>