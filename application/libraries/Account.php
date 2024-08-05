<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Account {

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


    // OBTIENE LA CUENTA CONTABLE PARA INVENTARIO Y COSTO SEGUN 
    // LA CLASIFICACION QUE TENGA EL ARTICULO
    public function getAccountItem($ItemCode, $WsCode){

        // $ItemCode = CODIGO DE ARTICULO
        // $WsCode = CODIGO DE ALMACEN

        // ALMACEN
        // GRUPODEARTICULO
        // ARTICULO

        // acct_cost = CUENTA DE COSTO
        // acct_inv = CUENTA DE INVENTARIO
        // acct_out = CUENTA DE GASTO
        // acct_in =  CUENTA DE INGRESO
        // acct_despent = CUENTA DE GASTO O COSTO DEDUCIBLE
        // acct_invproc = CUENTA INVENTARIO EN PROCESO
        // acct_income =  CUENTA PARA INGRESO IMPONIBLE

        $sqlItem =  "SELECT dma_accounting FROM dmar WHERE dma_item_code = :dma_item_code";

        $array = [];

        $resItem = $this->ci->pedeo->queryTable($sqlItem, array(":dma_item_code" => $ItemCode ));

        if ( isset($resItem[0]) ) {

            $sqlAccount = "";

            switch($resItem[0]['dma_accounting']) {

                case 'ALMACEN':
                    $sqlAccount = "SELECT coalesce(dws_acct_cost, 0) AS acct_cost , coalesce(dws_acct_inv, 0) AS acct_inv, 
                                coalesce(dws_acct_out, 0) AS acct_out, coalesce(dws_acctin, 0) AS acct_in,
                                coalesce(deductible_spent, 0) as acct_despent,
                                coalesce(dws_acct_invproc, 0) as acct_invproc,
                                coalesce(taxable_income, 0) as acct_income,
                                coalesce(dws_acct_return, 0) as acct_return
                                FROM dmws WHERE dws_code = :dws_code ";
                    $array = array(":dws_code" => $WsCode);
                    break;

                case 'GRUPODEARTICULO':
                    $sqlAccount = "SELECT f2.dma_item_code, coalesce(f1.mga_acct_inv,0) AS acct_inv, 
                                coalesce(f1.mga_acct_cost, 0) AS acct_cost, coalesce(mga_acct_out,0) AS acct_out, 
                                coalesce(mga_acctin, 0) AS acct_in, 
                                coalesce(f1.deductible_spent, 0) as acct_despent,
                                coalesce(mga_acct_invproc, 0) as acct_invproc,
                                coalesce(f1.taxable_income, 0) as acct_income,
                                coalesce(mga_acct_return, 0) as acct_return   
                                FROM dmga f1 
                                JOIN dmar f2 ON f1.mga_id  = f2.dma_group_code 
                                WHERE dma_item_code = :dma_item_code";
                    $array = array(":dma_item_code" => $ItemCode);
                    break;

                case 'ARTICULO':
                    $sqlAccount = "SELECT coalesce(dma_acct_inv,0) AS acct_inv , coalesce(dma_acct_cost,0) AS acct_cost,
                                coalesce(dma_acct_out,0) AS  acct_out, coalesce(dma_acctin, 0) AS acct_in,
                                coalesce(deductible_spent, 0) as acct_despent,
                                coalesce(dma_acct_invproc, 0) as acct_invproc,
                                coalesce(taxable_income, 0) as acct_income,
                                coalesce(dma_acct_return, 0) as acct_return    
                                FROM dmar 
                                WHERE dma_item_code = :dma_item_code";
                    $array = array(":dma_item_code" => $ItemCode);
                    break;        
            }

            $resAccount = $this->ci->pedeo->queryTable($sqlAccount, $array);
  
            if ( isset($resAccount[0]) ){


                if ( $resAccount[0]['acct_inv'] == 0 ||  $resAccount[0]['acct_cost'] == 0 || 
                     $resAccount[0]['acct_out'] == 0 || $resAccount[0]['acct_in'] == 0 || 
                     $resAccount[0]['acct_despent'] == 0 || $resAccount[0]['acct_income'] == 0 
                     || $resAccount[0]['acct_return'] == 0) {

                    $respuesta = array(
                        'error' => true,
                        'data'  => [],
                        'mensaje' =>'Las cuentas del articulo '.$ItemCode.' no estan establecidas'
                    );

                }else{

                    $respuesta = array(
                        'error'   => false,
                        'data'    => $resAccount[0],
                        'mensaje' => ''
                    );
                }

              

            }else{

                $respuesta = array(
                    'error' => true,
                    'data'  => $resItem,
                    'mensaje' =>'No hay resultados para el articulo '.$ItemCode
                );
            }
            

        }else{

            $respuesta = array(
                'error' => true,
                'data'  => [],
                'mensaje' =>'No se encontro el articulo '.$ItemCode
            );

        }


        return $respuesta;

    }

    // MUESTRA UNA VISTA PRELIMINAR DE LA CONTABILIDAD DE UN 
    // DOCUMENTO 
    public function getAcounting($asiento){

        //$asiento  = id del asiento contable en curso

        $ressqlmac1 = [];
        $respuesta = [];
        $sqlmac1 = "SELECT acc_name, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
        $ressqlmac1['contabilidad'] = $this->ci->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $asiento ));


        if ( isset($ressqlmac1['contabilidad'][0]) ){

            $respuesta = array(
                'error'   => false,
                'data' 	  => $ressqlmac1,
                'mensaje' => 'Vista previa de registros contables',
                'icon'    => 'info'
                
            );

        }else{

            $respuesta = array(
                'error'   => true,
                'data' 	  => $ressqlmac1,
                'mensaje' => 'Busqueda sin resultados',
                'icon'    => 'warning'
            );
        }

        return $respuesta;

    }

    // ACUMULA EL SALDO DE LOS DEBITOS O CREDITOS SEGUN EL MOVIMIENTO DE LA CUENTA
    // PARA EFECTO DEL CIERRE CONTABLE
    public function addBalance($periodo, $monto, $cuenta, $lado, $fecha, $business, $branch) {
        // $periodo ==  ID DEL PERIODO
        // $monto = VALOR A INCREMENTAR EN EL DEBITO O EL CREDITO
        // $cuenta = NUMERO DE CUENTA CONTABLE
        // $lado = INDICA SI VA EN EL CREDITO O EN EL DEBITO  1 PARA DEBITO 2 PARA CREDITO
        // $fecha = FECHA DE LA CONTABILIDAD DEL DOCUMENTO
        // $business =  EMPRESA
        // $branch = sucursal


        // SE BUSCA EL SUBPERIODO EN BASE AL PERIODO CONTABLE

        $sqlSubPeriod = "SELECT pc1_id FROM bpc1 WHERE pc1_period_id = :periodo AND pc1_fic <= :fecha AND pc1_ffc >= :fecha";

        $resSubPeriod = $this->ci->pedeo->queryTable($sqlSubPeriod, array(
            ':fecha' => $fecha,
            ':periodo' => $periodo
        ));


        if ( isset($resSubPeriod[0]) ) {

            // SE VERIFICA QUE EXISTA LA CUENTA EN EL REGISTRO CON EL PERIODO Y EL SUB PERIODO

            $sqlAcct = "SELECT * FROM abap  WHERE bap_period = :periodo and bap_subperiod = :subperiodo and bap_acount = :cuenta";
            $resAcct = $this->ci->pedeo->queryTable($sqlAcct, array(
                ':periodo' => $periodo,
                ':subperiodo' => $resSubPeriod[0]['pc1_id'],
                ':cuenta' => $cuenta
            ));

            $sqlUpdate = '';
            $resUpdate = 0;
            $sqlInsert = '';
            $resInsert = 0;
            $debito = 0;
            $credito = 0;

           
            // SI YA EXISTE SE ACTUALIZA
            if ( isset($resAcct[0]) ){

                if ( $lado == 1 ){ // SE ACTUALIZA EL DEBITO
                    $sqlUpdate = "UPDATE abap set bap_debit = (bap_debit + :monto) WHERE bap_period = :periodo and bap_subperiod = :subperiodo and bap_acount = :cuenta";
                } else { // SE ACTUALIZA EL CREDITO
                    $sqlUpdate = "UPDATE abap set bap_credit = (bap_credit + :monto) WHERE bap_period = :periodo and bap_subperiod = :subperiodo and bap_acount = :cuenta";
                }

                
                $resUpdate = $this->ci->pedeo->updateRow($sqlUpdate, array(
                    ':monto' => $monto,
                    ':periodo' => $periodo,
                    ':subperiodo' => $resSubPeriod[0]['pc1_id'],
                    ':cuenta' => $cuenta
                ));

                if ( is_numeric($resUpdate) && $resUpdate == 1){

                    return array(
                        "error"   => false,
                        "data"    => [],
                        "mensaje" => ""
                        
                    );

                }else{
                    return array(
                        "error" => true,
                        "data" => $resUpdate,
                        "mensaje" => "No se pudo actualizar la cuenta: ".$cuenta
                        
                    );
                }


            // SI NO EXISTE SE CREA
            } else {


                if ( $lado == 1 ) { // SE INSERTA EN EL DEBITO
                    $debito = $monto;
                    $credito = 0;
                } else { // SE INSERTA EN EL CREDITO
                    $credito = $monto;
                    $debito = 0;
                }


                $sqlInsert = "INSERT INTO abap(bap_period,bap_subperiod,bap_debit,bap_credit,bap_closed,bap_closureid,business,branch,bap_acount)VALUES(:bap_period,:bap_subperiod,:bap_debit,:bap_credit,:bap_closed,:bap_closureid,:business,:branch,:bap_acount)";
                $resInsert = $this->ci->pedeo->insertRow($sqlInsert, array(
                    
                    ':bap_period' => $periodo,
                    ':bap_subperiod' => $resSubPeriod[0]['pc1_id'],
                    ':bap_debit' => $debito,
                    ':bap_credit' => $credito,
                    ':bap_closed' => 0,
                    ':bap_closureid' => 0,
                    ':business' => $business,
                    ':branch' => $branch,
                    ':bap_acount' => $cuenta
                ));

                if (is_numeric($resInsert) && $resInsert > 0 ){

                    return array(
                        "error"   => false,
                        "data"    => [],
                        "mensaje" => ""
                        
                    );

                }else{
                    return array(
                        "error" => true,
                        "data" => $resInsert,
                        "mensaje" => "No se pudo actualizar la cuenta: ".$cuenta
                        
                    );
                }
            }

           

        } else {


            return array(
                "error" => true,
                "data" => [],
                "mensaje" => "No se encontro el sub periodo en el que se esta aplicando la contabilidad del documento actual"
                
            );

        }




    }

    // VALIDA EL PRESUPUESTO CONTABLE PARA SABER SI ES POSIBLE EDITAR EL DETALLE AL 100%
    public function validateBudget($codigo){

        // $codigo = CODIGO DEL PRESUPUESTO FINANCIERO

        $sqlDetallePresupuesto = "SELECT sum( coalesce(pc1_executed_debit_jan, 0) - coalesce(pc1_executed_credit_jan,0)) as ene, 
        sum( coalesce(pc1_executed_debit_feb, 0) - coalesce(pc1_executed_credit_feb,0)) as feb,
        sum( coalesce(pc1_executed_debit_mar, 0) - coalesce(pc1_executed_credit_mar,0)) as mar,
        sum( coalesce(pc1_executed_debit_apr, 0) - coalesce(pc1_executed_credit_apr,0)) as apr,
        sum( coalesce(pc1_executed_debit_may, 0) - coalesce(pc1_executed_credit_may,0)) as may,
        sum( coalesce(pc1_executed_debit_jun, 0) - coalesce(pc1_executed_credit_jun,0)) as jun,
        sum( coalesce(pc1_executed_debit_jul, 0) - coalesce(pc1_executed_credit_jul,0)) as jul,
        sum( coalesce(pc1_executed_debit_aug, 0) - coalesce(pc1_executed_credit_aug,0)) as aug,
        sum( coalesce(pc1_executed_debit_sep, 0) - coalesce(pc1_executed_credit_sep,0)) as sep,
        sum( coalesce(pc1_executed_debit_oct, 0) - coalesce(pc1_executed_credit_oct,0)) as oct,
        sum( coalesce(pc1_executed_debit_nov, 0) - coalesce(pc1_executed_credit_nov,0)) as nov,
        sum( coalesce(pc1_executed_debit_dec, 0) - coalesce(pc1_executed_credit_dec,0)) as dec
        FROM mpc1
        WHERE pc1_mpcid = :pc1_mpcid
        GROUP BY pc1_mpcid";

        $resDetellaPresupuesto = $this->ci->pedeo->queryTable($sqlDetallePresupuesto, array(
            ':pc1_mpcid' => $codigo
        ));

        if (isset($resDetellaPresupuesto[0])){

            foreach ($resDetellaPresupuesto as $key => $linea) {

                if ( $linea['ene'] > 0 || $linea['ene'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }

                if ( $linea['feb'] > 0 || $linea['feb'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }

                if ( $linea['mar'] > 0 || $linea['mar'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }

                if ( $linea['apr'] > 0 || $linea['apr'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }

                if ( $linea['may'] > 0 || $linea['may'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }

                if ( $linea['jun'] > 0 || $linea['jun'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }

                if ( $linea['jul'] > 0 || $linea['jul'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }

                if ( $linea['aug'] > 0 || $linea['aug'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }

                if ( $linea['sep'] > 0 || $linea['sep'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }

                if ( $linea['oct'] > 0 || $linea['oct'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }

                if ( $linea['nov'] > 0 || $linea['nov'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }

                if ( $linea['dec'] > 0 || $linea['dec'] < 0 ){
                    return array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => 'No es posible modificar el detalle del presupuesto'
                    );
                }
               
            }

            return array(
                "error"   => false,
                "data"    => [],
                "mensaje" => 'Es posible editar el presupuesto'
            );

        }else{

            return array(
                "error"   => false,
                "data"    => [],
                "mensaje" => ''
            );
        }

    }

    // VALIDA LA EXISTENCIA DE LA COMBINACION DE CUENTA CONTABLE CON EL PROYECTO CENTRO DE COSTO Y UNIDAD DE NEGOCIO
    public function validateLineBudget($codigo, $cuenta, $cc, $un, $pj) {

        // $codigo = ID DEL PRESUPUESTO
        // $cuenta = cuenta contable
        // $cc = centro de costo
        // $un = unidad de negocio
        // $pj = proyecto

        $array = [];

        $array['pc1_account'] = $cuenta;
        $array['pc1_mpcid'] = $codigo;

        $sqlSelect = " SELECT * FROM mpc1 WHERE pc1_account = :pc1_account AND pc1_mpcid = :pc1_mpcid ";

        if (!empty($cc)){
            $array['pc1_ccenter'] = $cc;
            $sqlSelect.=" AND pc1_ccenter = :pc1_ccenter ";
        }

        if (!empty($un)){
            $array['pc1_bunit'] = $un;
            $sqlSelect.=" AND pc1_bunit = :pc1_bunit ";
        }

        if (!empty($pj)){
            $array['pc1_project'] = $pj;
            $sqlSelect.=" AND pc1_project = :pc1_project ";
        }


        $resSelect = $this->ci->pedeo->queryTable($sqlSelect, $array);     
        
        if (isset($resSelect[0])){
            return array(
                "error"   => false,
                "data"    => [],
                "mensaje" => 'Si es posible colocar esa combinacion'
            );
        }else{
            return array(
                "error"   => true,
                "data"    => [],
                "mensaje" => 'No es posible modificar el presupuesto en su totalidad porque ya se encuentra afectado, la combinación de la cuenta:'.$cuenta.' mas el centro de costo:'.$cc.' mas la unidad de negocio:'.$un.' y el proyecto:'.$pj.' no existe en el presupuesto actual'
            );
        }

        

    }

    // VALIDACION DEL PRESUPUESTO CONTABLE
    // CONTROLAR QUE LAS OPERACIONES NO SUPEREN LOS
    // MONTOS ESTABLECIDOS EN EL PRESUPUESTO
    public  function validateBudgetAmount( $cuenta, $fecha, $cc, $un, $pj, $monto, $posicion, $business) {

        // $cuenta = cuenta contable
        // $cc = centro de costo
        // $un = unidad de negocio
        // $pj = proyecto
        // $fecha = la fecha de la contabilidad del documento
        // $monto = el valor que va en el asiento sea credito o debito
        // $posicion = 1 para debito 2 para credito indica que lado se suma el monto al presupuesto
        // $business = la empresa donde se aplica el movimiento

        
        $anio = date("Y", strtotime($fecha));
        $mes =  date('m', strtotime($fecha));

        $sqlPresupuestos = "SELECT * FROM tmpc WHERE mpc_year = :mpc_year AND business = :business";
        $resPresupuestos = $this->ci->pedeo->queryTable($sqlPresupuestos, array(
            ':mpc_year'  => $anio,
            ':business'  => $business
        ));

        if (isset($resPresupuestos[0])) {

            foreach ($resPresupuestos as $key => $data) {

                $sqlDetalle = "SELECT * FROM mpc1 WHERE pc1_account = :pc1_account AND pc1_mpcid = :pc1_mpcid";

                $array[':pc1_account'] = $cuenta;
                $array[':pc1_mpcid'] = $data['mpc_id'];
    
    
                if (!empty($cc)) {
                    $sqlDetalle .= " AND pc1_ccenter = :pc1_ccenter";
                    $array[':pc1_ccenter'] = $cc;
                }else{
                    $sqlDetalle .= " AND pc1_ccenter is null";
                }

                if (!empty($un)) {
                    $sqlDetalle .= " AND pc1_bunit = :pc1_bunit";
                    $array[':pc1_bunit'] = $un;
                }else{
                    $sqlDetalle .= " AND pc1_bunit is null";
                }

                if (!empty($pj)) {
                    $sqlDetalle .= " AND pc1_project = :pc1_project";
                    $array[':pc1_project'] = $pj;
                }else{
                    $sqlDetalle .= " AND pc1_project is null";
                }

                $resDetalle = $this->ci->pedeo->queryTable($sqlDetalle, $array);

                if (isset($resDetalle[0])) {
                    
                    foreach ($resDetalle as $key => $detail) {

                        // PROCESO PARA VALIDAR MONTOS
                        // SE VALIDAN LOS MONTOS SOLO SI EL PRESUPUESTO 
                        // ESTA MARCADO COMO CONTROLADO
                        if($data['mpc_check'] == 1){

                            if ( $data['mpc_type'] == 1 ) { // CASO ANUAL

                                switch($mes){
    
                                    case '01':
                                        
                                        $menec = $detail['pc1_executed_credit_jan'];
                                        $mened = $detail['pc1_executed_debit_jan']; 
    
                                        $tenero = $detail['pc1_jan'];
    
                                        if ( $posicion = 1 ){
                                            $mened = ($mened + $monto);
                                        }else if ($posicion = 2 ){
                                            $menec = ($menec + $monto);
                                        }
    
                                        $total = ($menec - $mened);
    
                                        if ($total > $tenero){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Enero'
                                            );
                                        }
                                        break;
                                    case '02':
                                        $mfebc = $detail['pc1_executed_credit_feb'];
                                        $mfebd = $detail['pc1_executed_debit_feb']; 
    
                                        $tfebrero = $detail['pc1_feb'];
    
                                        if ( $posicion = 1 ){
                                            $mfebd = ($mfebd + $monto);
                                        }else if ($posicion = 2 ){
                                            $mfebc = ($mfebc + $monto);
                                        }
    
                                        $total = ($mfebc - $mfebd);
    
                                        if ($total > $tfebrero){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Febrero'
                                            );
                                        }
                                        break;
                                    case '03':
                                        $mmarc = $detail['pc1_executed_credit_mar'];
                                        $mmard = $detail['pc1_executed_debit_mar']; 
    
                                        $tmarzo = $detail['pc1_mar'];
    
                                        if ( $posicion = 1 ){
                                            $mmard = ($mmard + $monto);
                                        }else if ($posicion = 2 ){
                                            $mmarc = ($mmarc + $monto);
                                        }
    
                                        $total = ($mmarc - $mmard);
    
                                        if ($total > $tmarzo){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Marzo'
                                            );
                                        }
                                        break;    
                                    case '04':
                                        $maprc = $detail['pc1_executed_credit_apr'];
                                        $maprd = $detail['pc1_executed_debit_apr']; 
    
                                        $tabril = $detail['pc1_apr'];
    
                                        if ( $posicion = 1 ){
                                            $maprd = ($maprd + $monto);
                                        }else if ($posicion = 2 ){
                                            $maprc = ($maprc + $monto);
                                        }
    
                                        $total = ($maprc - $maprd);
    
                                        if ($total > $tabril){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Abril'
                                            );
                                        }
                                        break;       
                                    case '05':
    
                                        $mmayc = $detail['pc1_executed_credit_may'];
                                        $mmayd = $detail['pc1_executed_debit_may']; 
    
                                        $tmayo = $detail['pc1_may'];
    
                                        if ( $posicion = 1 ){
                                            $mmayd = ($mmayd + $monto);
                                        }else if ($posicion = 2 ){
                                            $mmayc = ($mmayc + $monto);
                                        }
    
                                        $total = ($mmayc - $mmayd);
    
                                        if ($total > $tmayo){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Mayo'
                                            );
                                        }
                                        break;      
                                    case '06':
                                        $mjunc = $detail['pc1_executed_credit_jun'];
                                        $mjund = $detail['pc1_executed_debit_jun']; 
    
                                        $tjunio = $detail['pc1_jun'];
    
                                        if ( $posicion = 1 ){
                                            $mjund = ($mjund + $monto);
                                        }else if ($posicion = 2 ){
                                            $mjunc = ($mjunc + $monto);
                                        }
    
                                        $total = ($mjunc - $mjund);
    
                                        if ($total > $tjunio){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Junio'
                                            );
                                        }
                                        break;      
                                    case '07':
                                        $mjulc = $detail['pc1_executed_credit_jul'];
                                        $mjuld = $detail['pc1_executed_debit_jul']; 
    
                                        $tjulio = $detail['pc1_jul'];
    
                                        if ( $posicion = 1 ){
                                            $mjuld = ($mjuld + $monto);
                                        }else if ($posicion = 2 ){
                                            $mjulc = ($mjulc + $monto);
                                        }
    
                                        $total = ($mjulc - $mjuld);
    
                                        if ($total > $tjulio){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Julio'
                                            );
                                        }
                                        break;   
    
                                    case '08':
                                        $maugc = $detail['pc1_executed_credit_aug'];
                                        $maugd = $detail['pc1_executed_debit_aug']; 
    
                                        $tagosto = $detail['pc1_aug'];
    
                                        if ( $posicion = 1 ){
                                            $maugd = ($maugd + $monto);
                                        }else if ($posicion = 2 ){
                                            $maugc = ($maugc + $monto);
                                        }
    
                                        $total = ($maugc - $maugd);
    
                                        if ($total > $tagosto){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Agosto'
                                            );
                                        }
                                        break;    
                                        
                                    case '09':
                                        $msepc = $detail['pc1_executed_credit_sep'];
                                        $msepd = $detail['pc1_executed_debit_sep']; 
    
                                        $tseptiembre = $detail['pc1_sep'];
    
                                        if ( $posicion = 1 ){
                                            $msepd = ($msepd + $monto);
                                        }else if ($posicion = 2 ){
                                            $msepc = ($msepc + $monto);
                                        }
    
                                        $total = ($msepc - $msepd);
    
                                        if ($total > $tseptiembre){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Septiembre'
                                            );
                                        }
                                        break;  
    
                                    case '10':
                                        $moctc = $detail['pc1_executed_credit_oct'];
                                        $moctd = $detail['pc1_executed_debit_oct']; 
    
                                        $toctubre = $detail['pc1_oct'];
    
                                        if ( $posicion = 1 ){
                                            $moctd = ($moctd + $monto);
                                        }else if ($posicion = 2 ){
                                            $moctc = ($moctc + $monto);
                                        }
    
                                        $total = ($moctc - $moctd);
    
                                        if ($total > $toctubre){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Octubre'
                                            );
                                        }
                                        break;     
    
                                    case '11':
                                        $mnovc = $detail['pc1_executed_credit_nov'];
                                        $mnovd = $detail['pc1_executed_debit_nov']; 
    
                                        $tnoviembre = $detail['pc1_nov'];
    
                                        if ( $posicion = 1 ){
                                            $mnovd = ($mnovd + $monto);
                                        }else if ($posicion = 2 ){
                                            $mnovc = ($mnovc + $monto);
                                        }
    
                                        $total = ($mnovc - $mnovd);
    
                                        if ($total > $tnoviembre){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Noviembre'
                                            );
                                        }
                                        break; 
                                        
                                    case '12':
                                        $mdecc = $detail['pc1_executed_credit_dec'];
                                        $mdecd = $detail['pc1_executed_debit_dec']; 
    
                                        $tdiciembre = $detail['pc1_dec'];
    
                                        if ( $posicion = 1 ){
                                            $mdecd = ($mdecd + $monto);
                                        }else if ($posicion = 2 ){
                                            $mdecc = ($mdecc + $monto);
                                        }
    
                                        $total = ($mdecc - $mdecd);
    
                                        if ($total > $tdiciembre){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el mes de Diciembre'
                                            );
                                        }
                                        break;                                   
    
                                }
    
                            } else if ($data['mpc_type'] == 2) { // CASO TRIMESTRAL
    
                                switch($mes){
                                    case '01':
                                    case '02':
                                    case '03':
    
                                        $t1c = $detail['pc1_executed_credit_jan'];
                                        $t1d = $detail['pc1_executed_debit_jan']; 
    
                                        $t1c = ($t1c + $detail['pc1_executed_credit_feb']);
                                        $t1d = ($t1d + $detail['pc1_executed_debit_feb']); 
    
                                        $t1c = ($t1c + $detail['pc1_executed_credit_mar']);
                                        $t1d = ($t1d + $detail['pc1_executed_debit_mar']); 
    
    
                                        $tt1 = ($detail['pc1_jan'] + $detail['pc1_feb'] + $detail['pc1_mar']); // TOTAL TRIMESTE 1
    
                                        if ( $posicion = 1 ) {
                                            $t1c = ($t1c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $t1d = ($t1d + $monto);
                                        }
    
                                        $total = ($t1c - $t1d); // credito de debito
    
                                        if ($total > $tt1){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el trimestre 1 del año'
                                            );
                                        }
    
                                        break;
    
                                    case '04':    
                                    case '05':
                                    case '06':
                                        $t2c = $detail['pc1_executed_credit_apr'];
                                        $t2d = $detail['pc1_executed_debit_apr']; 
    
                                        $t2c = ($t2c + $detail['pc1_executed_credit_may']);
                                        $t2d = ($t2c + $detail['pc1_executed_debit_may']); 
    
                                        $t2c = ($t2c + $detail['pc1_executed_credit_jun']);
                                        $t2d = ($t2c + $detail['pc1_executed_debit_jun']); 
    
                                        $tt2 = ($detail['pc1_apr'] + $detail['pc1_may'] + $detail['pc1_jun']); // TOTAL TRIMESTE 2
    
                                        if ( $posicion = 1 ) {
                                            $t2c = ($t2c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $t2d = ($t2d + $monto);
                                        }
    
                                        $total = ($t2c - $t2d); // credito de debito
    
                                        if ($total > $tt2) {
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el trimestre 2 del año'
                                            );
                                        }
    
    
                                        break;
    
                                    case '07':
                                    case '08':
                                    case '09':
                                        
                                        $t3c = $detail['pc1_executed_credit_jul'];
                                        $t3d = $detail['pc1_executed_debit_jul']; 
    
                                        $t3c = ($t3c + $detail['pc1_executed_credit_aug']);
                                        $t3d = ($t3c + $detail['pc1_executed_debit_aug']); 
    
                                        $t3c = ($t3c + $detail['pc1_executed_credit_sep']);
                                        $t3d = ($t3c + $detail['pc1_executed_debit_sep']); 
    
                                        $tt3 = ($detail['pc1_jul'] + $detail['pc1_aug'] + $detail['pc1_sep']); // TOTAL TRIMESTE 3
    
                                        if ( $posicion = 1 ) {
                                            $t3c = ($t3c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $t3d = ($t3d + $monto);
                                        }
    
                                        $total = ($t3c - $t3d); // credito de debito
    
                                        if ($total > $tt3) {
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el trimestre 3 del año'
                                            );
                                        }
    
    
                                        break;
                                    case '10':
                                    case '11':
                                    case '12':
    
    
                                        $t4c = $detail['pc1_executed_credit_oct'];
                                        $t4d = $detail['pc1_executed_debit_oct']; 
    
                                        $t4c = ($t4c + $detail['pc1_executed_credit_nov']);
                                        $t4d = ($t4c + $detail['pc1_executed_debit_nov']); 
    
                                        $t4c = ($t4c + $detail['pc1_executed_credit_dec']);
                                        $t4d = ($t4c + $detail['pc1_executed_debit_dec']); 
    
                                        $tt4 = ($detail['pc1_oct'] + $detail['pc1_nov'] + $detail['pc1_dec']); // TOTAL TRIMESTE 4
    
                                        if ( $posicion = 1 ) {
                                            $t4c = ($t4c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $t4d = ($t4d + $monto);
                                        }
    
                                        $total = ($t4c - $t4d); // credito de debito
    
                                        if ($total > $tt4) {
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el trimestre 4 del año'
                                            );
                                        }
    
                                        
                                        break;
    
                                }
    
                            } else if ($data['mpc_type'] == 3){  // CASO BIMESTRAL
    
                                switch($mes){
                                    case '01':
                                    case '02':
                                        $b1c = $detail['pc1_executed_credit_jan'];
                                        $b1d = $detail['pc1_executed_debit_jan']; 
    
                                        $b1c = ($b1c + $detail['pc1_executed_credit_feb']);
                                        $b1d = ($b1d + $detail['pc1_executed_debit_feb']); 
    
    
                                        $tb1 = ($detail['pc1_jan'] + $detail['pc1_feb']); // TOTAL BIMESTRE 1
    
                                        if ( $posicion = 1 ) {
                                            $b1c = ($b1c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $b1d = ($b1d + $monto);
                                        }
    
                                        $total = ($b1c - $b1d); // credito de debito
    
                                        if ($total > $tb1){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el 1er bimestre del año'
                                            );
                                        }
    
                                        break;
                                    case '03':
                                    case '04':
                                        $b2c = $detail['pc1_executed_credit_mar'];
                                        $b2d = $detail['pc1_executed_debit_mar']; 
    
                                        $b2c = ($b2c + $detail['pc1_executed_credit_apr']);
                                        $b2d = ($b2d + $detail['pc1_executed_debit_apr']); 
    
    
                                        $tb2 = ($detail['pc1_mar'] + $detail['pc1_apr']); // TOTAL BIMESTRE 2
    
                                        if ( $posicion = 1 ) {
                                            $b2c = ($b2c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $b2d = ($b2d + $monto);
                                        }
    
                                        $total = ($b2c - $b2d); // credito de debito
    
                                        if ($total > $tb2){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el 2do bimestre del año'
                                            );
                                        }
    
                                        break;        
    
                                    case '05':
                                    case '06':
                                        $b3c = $detail['pc1_executed_credit_may'];
                                        $b3d = $detail['pc1_executed_debit_may']; 
    
                                        $b3c = ($b3c + $detail['pc1_executed_credit_jun']);
                                        $b3d = ($b3d + $detail['pc1_executed_debit_jun']); 
    
    
                                        $tb3 = ($detail['pc1_may'] + $detail['pc1_jun']); // TOTAL BIMESTRE 3
    
                                        if ( $posicion = 1 ) {
                                            $b3c = ($b3c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $b3d = ($b3d + $monto);
                                        }
    
                                        $total = ($b3c - $b3d); // credito de debito
    
                                        if ($total > $tb3){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el 3er bimestre del año'
                                            );
                                        }
    
                                        break;    
    
                                    case '07':
                                    case '08':
                                        $b4c = $detail['pc1_executed_credit_jul'];
                                        $b4d = $detail['pc1_executed_debit_jul']; 
    
                                        $b4c = ($b4c + $detail['pc1_executed_credit_aug']);
                                        $b4d = ($b4d + $detail['pc1_executed_debit_aug']); 
    
    
                                        $tb4 = ($detail['pc1_jul'] + $detail['pc1_aug']); // TOTAL BIMESTRE 4
    
                                        if ( $posicion = 1 ) {
                                            $b4c = ($b4c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $b4d = ($b4d + $monto);
                                        }
    
                                        $total = ($b4c - $b4d); // credito de debito
    
                                        if ($total > $tb4){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el 4to bimestre del año'
                                            );
                                        }
    
                                        break;  
                                    case '09':
                                    case '10':
                                        $b5c = $detail['pc1_executed_credit_sep'];
                                        $b5d = $detail['pc1_executed_debit_sep']; 
    
                                        $b5c = ($b5c + $detail['pc1_executed_credit_oct']);
                                        $b5d = ($b5d + $detail['pc1_executed_debit_oct']); 
    
    
                                        $tb5 = ($detail['pc1_sep'] + $detail['pc1_oct']); // TOTAL BIMESTRE 5
    
                                        if ( $posicion = 1 ) {
                                            $b5c = ($b5c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $b5d = ($b5d + $monto);
                                        }
    
                                        $total = ($b5c - $b5d); // credito de debito
    
                                        if ($total > $tb5){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el 5to bimestre del año'
                                            );
                                        }
    
                                        break;      
                                    case '11':
                                    case '12':
                                        $b6c = $detail['pc1_executed_credit_nov'];
                                        $b6d = $detail['pc1_executed_debit_nov']; 
    
                                        $b6c = ($b6c + $detail['pc1_executed_credit_dec']);
                                        $b6d = ($b6d + $detail['pc1_executed_debit_dec']); 
    
    
                                        $tb6 = ($detail['pc1_nov'] + $detail['pc1_dec']); // TOTAL BIMESTRE 6
    
                                        if ( $posicion = 1 ) {
                                            $b6c = ($b6c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $b6d = ($b6d + $monto);
                                        }
    
                                        $total = ($b6c - $b6d); // credito de debito
    
                                        if ($total > $tb6){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el 6to bimestre del año'
                                            );
                                        }
    
                                        break;  
    
                                }
    
                            } else if ($data['mpc_type'] == 4){ // CASO CUATRIMESTRAL
    
                                switch($mes){
                                    case '01':
                                    case '02':
                                    case '03':
                                    case '04':
                                        $c1c = $detail['pc1_executed_credit_jan'];
                                        $c1d = $detail['pc1_executed_debit_jan']; 
    
                                        $c1c = ($c1c + $detail['pc1_executed_credit_feb']);
                                        $c1d = ($c1d + $detail['pc1_executed_debit_feb']); 
    
                                        $c1c = ($c1c + $detail['pc1_executed_credit_mar']);
                                        $c1d = ($c1d + $detail['pc1_executed_debit_mar']); 
    
                                        $c1c = ($c1c + $detail['pc1_executed_credit_apr']);
                                        $c1d = ($c1d + $detail['pc1_executed_debit_apr']); 
    
    
                                        $tc1 = ($detail['pc1_jan'] + $detail['pc1_feb'] + $detail['pc1_mar'] + $detail['pc1_apr']); // TOTAL CUATRIMESTRE 1
    
                                        if ( $posicion = 1 ) {
                                            $c1c = ($c1c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $c1d = ($c1d + $monto);
                                        }
    
                                        $total = ($c1c - $c1d); // credito de debito
    
                                        if ($total > $tc1){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el 1er cuatrimestre del año'
                                            );
                                        }
    
                                        break;
                        
    
                                    case '05':
                                    case '06':
                                    case '07':
                                    case '08':
                                        $c2c = $detail['pc1_executed_credit_may'];
                                        $c2d = $detail['pc1_executed_debit_may']; 
    
                                        $c2c = ($b2c + $detail['pc1_executed_credit_jun']);
                                        $c2d = ($b2d + $detail['pc1_executed_debit_jun']); 
    
                                        $c2c = ($c2c + $detail['pc1_executed_credit_jul']);
                                        $c2d = ($c2d + $detail['pc1_executed_debit_jul']); 
    
                                        $c2c = ($b2c + $detail['pc1_executed_credit_aug']);
                                        $c2d = ($b2d + $detail['pc1_executed_debit_aug']); 
    
    
                                        $tc2 = ($detail['pc1_may'] + $detail['pc1_jun'] + $detail['pc1_jul'] + $detail['pc1_aug']); // TOTAL CUATRIMESTRE 2
    
                                        if ( $posicion = 1 ) {
                                            $b3c = ($b3c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $b3d = ($b3d + $monto);
                                        }
    
                                        $total = ($b3c - $b3d); // credito de debito
    
                                        if ($total > $tb3){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el 2do cuatrimestre del año'
                                            );
                                        }
    
                                        break;    
    
                                    case '09':
                                    case '10':
                                    case '11':
                                    case '12':
                                        $c3c = $detail['pc1_executed_credit_sep'];
                                        $c3d = $detail['pc1_executed_debit_sep']; 
    
                                        $c3c = ($c3c + $detail['pc1_executed_credit_oct']);
                                        $c3d = ($c3d + $detail['pc1_executed_debit_oct']); 
    
                                        $c3c = ($c3c + $detail['pc1_executed_credit_nov']);
                                        $c3d = ($c3d + $detail['pc1_executed_debit_nov']); 
    
                                        $c3c = ($c3c + $detail['pc1_executed_credit_dec']);
                                        $c3d = ($c3d + $detail['pc1_executed_debit_dec']); 
    
    
                                        $tb5 = ($detail['pc1_sep'] + $detail['pc1_oct'] + $detail['pc1_nov'] + $detail['pc1_dec']); // TOTAL CUATRIMESTRE 3
    
                                        if ( $posicion = 1 ) {
                                            $b5c = ($b5c + $monto);
                                        }else if ($posicion = 2 ) {
                                            $b5d = ($b5d + $monto);
                                        }
    
                                        $total = ($b5c - $b5d); // credito de debito
    
                                        if ($total > $tb5){
    
                                            return array(
                                                'error'   => true,
                                                'data'    => [],
                                                'mensaje' => 'No es posible crear el documento, se esta superando el monto presupuestado para el presupuesto '.$data['mpc_name'].' en el 3er cuatrimestre del año'
                                            );
                                        }
    
                                        break;      
                                }
                            }
       
                        }

                        // PROCESO PARA ACTUALIZAR LOS MONTOS
                        switch($mes){

                            case '01':

                                $tenec = 0;
                                $tened = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_jan = coalesce(pc1_executed_credit_jan, 0) + :pc1_executed_credit_jan, pc1_executed_debit_jan = coalesce(pc1_executed_debit_jan, 0) + :pc1_executed_debit_jan WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $tened = $monto;
                                } else if ( $posicion == 2 ){
                                    $tenec = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_jan' => $tenec, 
                                    ':pc1_executed_debit_jan'  => $tened,
                                    ':pc1_id' => $detail['pc1_id']
                                ));

                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{

                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break;
                            case '02':

                                $tfebc = 0;
                                $tfebd = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_feb = coalesce(pc1_executed_credit_feb, 0) + :pc1_executed_credit_feb, pc1_executed_debit_feb = coalesce(pc1_executed_debit_feb, 0) + :pc1_executed_debit_feb WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $tfebd = $monto;
                                } else if ( $posicion == 2 ){
                                    $tfebc = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_feb' => $tfebc, 
                                    ':pc1_executed_debit_feb'  => $tfebd,
                                    ':pc1_id' => $detail['pc1_id']
                                ));

                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{
                                    
                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break;    
                            case '03':

                                $tmarc = 0;
                                $tmard = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_mar = coalesce(pc1_executed_credit_mar, 0) + :pc1_executed_credit_mar, pc1_executed_debit_mar = coalesce(pc1_executed_debit_mar, 0) + :pc1_executed_debit_mar WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $tmard = $monto;
                                } else if ( $posicion == 2 ){
                                    $tmarc = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_mar' => $tmarc, 
                                    ':pc1_executed_debit_mar'  => $tmard,
                                    ':pc1_id' => $detail['pc1_id']
                                ));

                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{
                                    
                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break;  

                            case '04':

                                $taprc = 0;
                                $taprd = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_apr = coalesce(pc1_executed_credit_apr, 0) + :pc1_executed_credit_apr, pc1_executed_debit_apr = coalesce(pc1_executed_debit_apr, 0) + :pc1_executed_debit_apr WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $taprd = $monto;
                                } else if ( $posicion == 2 ){
                                    $taprc = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_apr' => $taprc, 
                                    ':pc1_executed_debit_apr'  => $taprd,
                                    ':pc1_id' => $detail['pc1_id']
                                ));

                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{
                                    
                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break;     

                            case '05':

                                $tmayc = 0;
                                $tmayd = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_may = coalesce(pc1_executed_credit_may, 0) + :pc1_executed_credit_may, pc1_executed_debit_may = coalesce(pc1_executed_debit_may, 0) + :pc1_executed_debit_may WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $tmayd = $monto;
                                } else if ( $posicion == 2 ){
                                    $tmayc = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_may' => $tmayc, 
                                    ':pc1_executed_debit_may'  => $tmayd,
                                    ':pc1_id' => $detail['pc1_id']
                                ));

                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{
                                    
                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break; 

                            case '06':

                                $tjunc = 0;
                                $tjund = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_jun = coalesce(pc1_executed_credit_jun, 0) + :pc1_executed_credit_jun, pc1_executed_debit_jun = coalesce(pc1_executed_debit_jun, 0) + :pc1_executed_debit_jun WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $tjund = $monto;
                                } else if ( $posicion == 2 ){
                                    $tjunc = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_jun' => $tjunc, 
                                    ':pc1_executed_debit_jun'  => $tjund,
                                    ':pc1_id' => $detail['pc1_id']
                                ));

                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{
                                    
                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break; 

                            case '07':

                                $tjulc = 0;
                                $tjuld = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_jul = coalesce(pc1_executed_credit_jul, 0) + :pc1_executed_credit_jul, pc1_executed_debit_jul = coalesce(pc1_executed_debit_jul, 0) + :pc1_executed_debit_jul WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $tjuld = $monto;
                                } else if ( $posicion == 2 ){
                                    $tjulc = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_jul' => $tjulc, 
                                    ':pc1_executed_debit_jul'  => $tjuld,
                                    ':pc1_id' => $detail['pc1_id']
                                ));

                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{
                                    
                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break; 
                            case '08':

                                $taugc = 0;
                                $taugd = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_aug = coalesce(pc1_executed_credit_aug, 0) + :pc1_executed_credit_aug, pc1_executed_debit_aug = coalesce(pc1_executed_debit_aug, 0) + :pc1_executed_debit_aug WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $taugd = $monto;
                                } else if ( $posicion == 2 ){
                                    $taugc = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_aug' => $taugc, 
                                    ':pc1_executed_debit_aug'  => $taugd,
                                    ':pc1_id' => $detail['pc1_id']
                                ));

                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{
                                    
                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break; 

                            case '09':

                                $tsepc = 0;
                                $tsepd = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_sep = coalesce(pc1_executed_credit_sep, 0) + :pc1_executed_credit_sep, pc1_executed_debit_sep = coalesce(pc1_executed_debit_sep, 0) + :pc1_executed_debit_sep WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $tsepd = $monto;
                                } else if ( $posicion == 2 ){
                                    $tsepc = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_sep' => $tsepc, 
                                    ':pc1_executed_debit_sep'  => $tsepd,
                                    ':pc1_id' => $detail['pc1_id']
                                ));

                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{
                                    
                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break; 

                            case '10':

                                $toctc = 0;
                                $toctd = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_oct = coalesce(pc1_executed_credit_oct, 0) + :pc1_executed_credit_oct, pc1_executed_debit_oct = coalesce(pc1_executed_debit_oct, 0) + :pc1_executed_debit_oct WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $toctd = $monto;
                                } else if ( $posicion == 2 ){
                                    $toctc = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_oct' => $toctc, 
                                    ':pc1_executed_debit_oct'  => $toctd,
                                    ':pc1_id' => $detail['pc1_id']
                                ));

                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{
                                    
                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break; 

                            case '11':

                                $tnovc = 0;
                                $tnovd = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_nov = coalesce(pc1_executed_credit_nov, 0) + :pc1_executed_credit_nov, pc1_executed_debit_nov = coalesce(pc1_executed_debit_nov, 0) + :pc1_executed_debit_nov WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $tnovd = $monto;
                                } else if ( $posicion == 2 ){
                                    $tnovc = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_nov' => $tnovc, 
                                    ':pc1_executed_debit_nov'  => $tnovd,
                                    ':pc1_id' => $detail['pc1_id']
                                ));
         
                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{
                                    
                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break; 

                            case '12':

                                $tdecc = 0;
                                $tdecd = 0;

                                $sqlUpdateDetalle = " UPDATE mpc1 set pc1_executed_credit_dec = coalesce(pc1_executed_credit_dec, 0) + :pc1_executed_credit_dec, pc1_executed_debit_dec = coalesce(pc1_executed_debit_dec, 0) + :pc1_executed_debit_dec WHERE pc1_id = :pc1_id";

                                if ( $posicion == 1 ){
                                    $tdecd = $monto;
                                } else if ( $posicion == 2 ){
                                    $tdecc = $monto;
                                }

                                $resUpdateDetalle = $this->ci->pedeo->updateRow($sqlUpdateDetalle, array(
                                    ':pc1_executed_credit_dec' => $tdecc, 
                                    ':pc1_executed_debit_dec'  => $tdecd,
                                    ':pc1_id' => $detail['pc1_id']
                                ));

                                if (is_numeric($resUpdateDetalle) && $resUpdateDetalle == 1){

                                }else{
                                    
                                    return array(
                                        'error'   => true,
                                        'data'    => $resUpdateDetalle,
                                        'mensaje' => 'No se pudo actualiza el detalle del presupuesto'
                                    );
                                }

                                break; 
    

                        }
                      
                    }
                }
            }

            // SI LLEGO HASTA ACA SE PASARON LAS VALIDACION DE SUPERAR EL PRESUPUESTO
    
            return array (
                'error'   => false,
                'data'    => [],
                'mensaje' => 'Todo ok'
            );

        } else {

            return array(
                'error'   => false,
                'data'    => [],
                'mensaje' => 'No hay presupuestos para controlar'
            );
        }


    }




}