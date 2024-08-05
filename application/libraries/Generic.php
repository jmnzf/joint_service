<?php
defined('BASEPATH') OR exit('No direct script access allowed');





class Generic {

  private $ci;
  private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

    $this->ci =& get_instance();
		$this->ci->load->database();
		$this->pdo = $this->ci->load->database('pdo', true)->conn_id;
    $this->ci->load->library('pedeo', [$this->pdo]);
    $this->ci->load->library('DocumentNumbering');
    $this->ci->load->library('Tasa');
    $this->ci->load->library('account');
	}


  // VALIDA SI EXISTE UN PERIODO CONTABLE
  // Y QUE EL DOCUMENTO CUMPLA CON las
  // politicas
	public function ValidatePeriod($DateDoc,$DateCon,$DateVen,$TipoDoc){

    //SE QUEMA TIPO DOCUMENTO PARA VENTAS Y COMPRAS
    // 1 = VENTAS
    // 0 = COMPRAS
    $respuesta = array();

    // return  $respuesta = array(
    //   'error' => false,
    //   'data'  => [],
    //   'mensaje' =>''
    // );

    $sql = "SELECT * FROM tbpc WHERE bpc_fip <= :bpc_fip AND bpc_ffp >= :bpc_ffp
            AND bpc_fic <= :bpc_fic AND bpc_ffc >= :bpc_ffc
            AND bpc_fiv <= :bpc_fiv AND bpc_ffv >= :bpc_ffv";


    $ressql = $this->ci->pedeo->queryTable($sql, array(
      ':bpc_fip' => $DateCon,
      ':bpc_ffp' => $DateCon,
      ':bpc_fic' => $DateDoc,
      ':bpc_ffc' => $DateDoc,
      ':bpc_fiv' => $DateVen,
      ':bpc_ffv' => $DateVen
    ));

    if( isset($ressql[0]) ){

      $sql2 = "SELECT * FROM bpc1 WHERE (pc1_fid <= :pc1_fid AND pc1_ffd >= :pc1_ffd
              AND pc1_fic <= :pc1_fic AND pc1_ffc >= :pc1_ffc) AND pc1_period_id = :pc1_period_id";

      $ressql2 = $this->ci->pedeo->queryTable($sql2, array(
        ':pc1_fid' => $DateDoc,
        ':pc1_ffd' => $DateDoc,
        ':pc1_fic' => $DateCon,
        ':pc1_ffc' => $DateCon,
        ':pc1_period_id' => $ressql[0]['bpc_id']
      ));


      if( isset($ressql2[0]) ){

        if( $ressql2[0]['pc1_status'] === 1){
          return $respuesta = array(
            'error' => false,
            'data'  => $ressql[0]['bpc_id'],
            'mensaje' =>''
          );
        }else if( $ressql2[0]['pc1_status'] === 2 && $TipoDoc === 1){
          return  $respuesta = array(
            'error' => true,
            'data'  => [],
            'mensaje' =>'El periodo contable se encuentra bloqueado y no se pueden realizar transacciones.'
          );
        }else if( $ressql2[0]['pc1_status'] === 2 && $TipoDoc === 0){
          return  $respuesta = array(
            'error' => false,
            'data'  => $ressql[0]['bpc_id'],
            'mensaje' =>''
          );
        }else if( $ressql2[0]['pc1_status'] === 3 ){
          return  $respuesta = array(
            'error' => true,
            'data'  => [],
            'mensaje' =>'El periodo contable se encuentra bloqueado y no se pueden realizar transacciones.'
          );
        }

      }else{
        $respuesta = array(
          'error' => true,
          'data'  => [],
          'mensaje' =>'No se encontró un periodo contable en la fecha del documento.'
        );
      }
    }else{
      $respuesta = array(
        'error' => true,
        'data'  => [],
        'mensaje' =>'No se encontró un periodo contable en la fecha del documento.'
      );
    }

    return $respuesta;
	}

  // VALIDA EN PAGOS EFECTUADOS Y RECIBIDOS EL MONTO DE LAS FACTURAS
  // MONTO DE LAS NOTAS CREDITO Y debito
  // VALIDA EL MONTO DE IGUAL MANERA EN RECONCILICACION DE CUENTAS
  public function validateBalance($CodDoc, $TypeDoc, $table, $pref, $value, $paycurrency, $datepay,$op,$linenum){

    //$CodDoc = DocEntry  del documento
    //$TypeDoc = DocType del documento
    //$table = nombre de la tabla
    //$pref = prefijo de la tabla
    //$value = monto a pagar
    //$op = tipo de operacion (1) ventas o (2) compra
    //$linenum Codigo  de la linea de la tabla mac1
    $DECI_MALES =  $this->getDecimals();
    $DiffP = 0;
    $DiffN = 0;
    $TotalAccm = 0;
    $Equiv = 0; //Equivalencia por decimales

    $eqvSql = "SELECT get_tax_currency(:currency, :fecha) as tasaparapagoactual";
    $resEqv = $this->ci->pedeo->queryTable($eqvSql, array(
      ':currency' => $paycurrency,
      ':fecha'    => $datepay
    ));

    if ( isset($resEqv[0]) ){

    }else{

      return $respuesta = array(
        'error'   => true,
        'data'    => '',
        'mensaje'	=> 'No se encuentra la tasa para la moneda de la transaccion'
      );

    }

    $TasaCurrent = $resEqv[0]['tasaparapagoactual'];// TASA DE PAGO ACTUAL CON LA MONEDA ACTUAL

    $VlrPay = "";

    $resVlrPay = [];

    if ( $TypeDoc == 18 ) {

      $VlrPay = "SELECT 0 as paytoday,
                  case
                    when ac1_cord = 1 then ac1_credit
                    when ac1_cord = 0 then ac1_debit  
                  end as doctotal,
                  get_localcur() as currency,
                  get_localcur() as monedaloc,
                  get_tax_currency(get_localcur(), ac1_doc_date) as tasadoc
                  FROM mac1
                  WHERE ac1_line_num = :ac1_line_num";

      $resVlrPay = $this->ci->pedeo->queryTable($VlrPay, array('ac1_line_num' => $linenum));

    } else if ( $TypeDoc == 47 ){

      if ( $pref == "" && $table == "" ){

        $VlrPay =  "SELECT ac1_ven_debit as paytoday,
        ac1_ven_credit as doctotal,
        get_localcur() as currency,
        get_localcur() as monedaloc,
        get_tax_currency(get_localcur(), ac1_doc_date) as tasadoc
        FROM mac1
        WHERE ac1_line_num = :ac1_line_num";

        $resVlrPay = $this->ci->pedeo->queryTable($VlrPay, array('ac1_line_num' => $linenum));

      }else{

        $VlrPay = "SELECT COALESCE(".$pref."_paytoday,0) as paytoday,
        ".$pref."_total_c as doctotal,
        ".$pref."_currency as currency,
        get_localcur() as monedaloc,
        get_tax_currency(".$pref."_currency, ".$pref."_docdate) as tasadoc
        FROM ".$table."
        WHERE ".$pref."_docentry = :docentry
        AND ".$pref."_doctype = :doctype";

        $resVlrPay = $this->ci->pedeo->queryTable($VlrPay, array(
        ':docentry' => $CodDoc,
        ':doctype'  => $TypeDoc
        ));

      }

    } else {

      $VlrPay = "SELECT COALESCE(".$pref."_paytoday,0) as paytoday,
              ".$pref."_doctotal as doctotal,
              ".$pref."_currency as currency,
              get_localcur() as monedaloc,
              get_tax_currency(".$pref."_currency, ".$pref."_docdate) as tasadoc
              FROM ".$table."
              WHERE ".$pref."_docentry = :docentry
              AND ".$pref."_doctype = :doctype";


      $resVlrPay = $this->ci->pedeo->queryTable($VlrPay, array(
        ':docentry' => $CodDoc,
        ':doctype'  => $TypeDoc
      ));
    }


  

    if ( isset($resVlrPay[0]) ){

      if( $resVlrPay[0]['monedaloc'] == $paycurrency ) { // si la moneda del pago es igual a la moneda local

        $VlrAPagar = $value; // valor a pagar
        $VlrActual = $resVlrPay[0]['paytoday']; // monto pagado hasta la fecha
        $DocTotal = 0;
        $SumVlr =  0;

        $SumVlr =  round($VlrActual + $VlrAPagar, $DECI_MALES); // suma de entre el valor a pagar y valor acumulado de pagos

        if( $resVlrPay[0]['monedaloc'] == $resVlrPay[0]['currency'] ){
          $DocTotal = $resVlrPay[0]['doctotal'];
        }else{
          $DocTotal = round( ( $resVlrPay[0]['doctotal'] * $resVlrPay[0]['tasadoc'] ), $DECI_MALES );
        }


        if( $SumVlr > $DocTotal ){

          $respuesta = array(
            'error'   => true,
            'data'    => '',
            'mensaje'	=> 'El valor a pagar no puede ser mayor al saldo del documento '.$TypeDoc.' -- '.$CodDoc
          );

        }else{

          $respuesta = array(
            'error'      => false,
            'data'       => '',
            'vlrdiff'    => 0,
            'vlrop'      => $VlrAPagar,
            'tasadoc'    => $resVlrPay[0]['tasadoc'],
            'equiv'      => 0,
            'mensaje'	   => ''
          );
          
        }

        return $respuesta;

      }else{ // si la moneda del documento es igual a la moneda local

        $VlrTotal = 0;
        $VlrAPagar = $value;

        $VlrDoc = 0;


        if ( $paycurrency ==  $resVlrPay[0]["monedaloc"] ){
          $VlrDoc = $VlrAPagar;
        }else{
          $VlrDoc = round(($VlrAPagar * $resVlrPay[0]['tasadoc']), $DECI_MALES); // VALOR A PAGAR CON LA TASA VIEJA
          $VlrDocCur = round(($VlrAPagar * $TasaCurrent), $DECI_MALES); // VALOR DEL DOCUMENTO CON LA TASA NUEVA
        }


        // print_r($VlrDoc);
        // echo "\n";
        // print_r($VlrDocCur);exit;

      // Valor del pago  en la moneda local con la tasa del dia del documento
        $VlrDiff = 0;

        $VlrSinDiff = 0;

        switch (intval($TypeDoc)) {

          case 15:
            $VlrDiff = ( $VlrDoc * -1 );
            break;
          case 16:
            $VlrDiff = $VlrDoc;
            break;
          case 19:
            $VlrDiff = $VlrDoc;
            break;
          case 5:
            $VlrDiff = $VlrDoc;
            break;
          case 6:
            $VlrDiff = ( $VlrDoc * -1 );
            break;
          case 20:
            $VlrDiff = ( $VlrDoc * -1 );
            break;
          case 18:
            $VlrDiff = ($op == 1) ? ($VlrDoc * -1) : $VlrDoc;
            break;
        }


        $DiffP = 0;
        $DiffN = 0;
        $SumVlr =  0;

        $VlrTotal =$VlrDoc;

        $VlrActual = $resVlrPay[0]['paytoday'];

        $SumVlr =  round($VlrActual + $VlrTotal, $DECI_MALES);

        $VlrOrig = $SumVlr;
        $DocTotal = 0;

        //EL doctotal es el monto del documento en la moneda local
        //recordando que la cartera siempre esta en moneda local
        //por ende cuando se paga a sea en otra moneda se transforma a moneda local


        if( $resVlrPay[0]['monedaloc'] == $resVlrPay[0]['currency'] ){ // Si la moneda del documento es igual a la moneda local
          $DocTotal = $resVlrPay[0]['doctotal'];
        }else{
          $DocTotal = round( ( $resVlrPay[0]['doctotal'] * $resVlrPay[0]['tasadoc'] ), $DECI_MALES ); // si el documento esta en otra moneda
        }

        

        $Equiv = abs( round( ($VlrOrig - $DocTotal) , $DECI_MALES ) );

       

        if( $Equiv == 0.01 || $Equiv == 0.02 ){// por diferencia en decimales
          $VlrDoc  = ($VlrDoc - $Equiv);
          $VlrOrig = ($VlrOrig - $Equiv);
        }else{
          $Equiv = 0;
        }

        if( $VlrOrig > $DocTotal ) {
          $diffCambio = ($VlrOrig - $DocTotal);
          $VlrOrig = ($VlrOrig - $diffCambio);
          $VlrDoc = ($VlrDoc - $diffCambio);
        }


        if( $VlrOrig > $DocTotal ) {

          return $respuesta = array(
            'error'   => true,
            'data'    => '',
            'mensaje'	=> 'El valor a pagar no puede ser mayor al saldo del documento '.$CodDoc." -- ".$TypeDoc." -- ".$linenum." -- ".$VlrDoc
          );

        }else{

          $VlrDiff = ($VlrDoc - $VlrDocCur);

          return $respuesta = array(
            'error'      => false,
            'data'       => '',
            'vlrdiff'    => $VlrDiff,
            'vlrop'      => $VlrDoc,
            'tasadoc'    => $resVlrPay[0]['tasadoc'],
            'equiv'      => $Equiv,
            'mensaje'	   => ''
          );

        }

      }

    }else{

      return $respuesta = array(
        'error'   => true,
        'data'    => '',
        'mensaje'	=> 'No tiene valor para realizar la operacion '.$CodDoc." -- ".$TypeDoc." ".$linenum);
    }

  }


  //VERIFICA SI EL CAMPO PAYTODAY DE UN DOCUMENTO ES 0
  //PARA CERRARLO
  public function validateBalanceAndClose($CodDoc, $TypeDoc, $table, $pref){

    //$CodDoc = DocEntry  del documento
    //$TypeDoc = DocType del documento
    //$table = nombre de la tabla
    //$pref = prefijo de la tabla
    //$value = monto a pagar
    $DECI_MALES = $this->getDecimals();

    $VlrPay = "SELECT COALESCE(".$pref."_paytoday,0) as paytoday,
                   ".$pref."_doctotal as doctotal,
                   ".$pref."_currency as currency,
                   get_localcur() as monedaloc,
                   get_tax_currency(".$pref."_currency, ".$pref."_docdate) as tasadoc
                   FROM ".$table."
                   WHERE ".$pref."_docentry = :docentry
                   AND ".$pref."_doctype = :doctype";

    $resVlrPay = $this->ci->pedeo->queryTable($VlrPay, array(
      ':docentry' => $CodDoc,
      ':doctype'  => $TypeDoc
    ));

 

    if ( isset($resVlrPay[0]) ){

      if( $resVlrPay[0]['currency'] == $resVlrPay[0]['monedaloc']) { // si la moneda del documento es igual a la del pago que por el momento esta definida como la moneda principal

        $VlrTotal  = $resVlrPay[0]['doctotal']; // valor a pagar
        $VlrActual = $resVlrPay[0]['paytoday']; // monto pagado hasta la fecha


        $RestVlr =  round($VlrTotal  -  $VlrActual, $DECI_MALES); // resta de entre el valores total documento total pagos realizados

        if( $RestVlr == 0 ){

          return $respuesta = array(
            'error'   => true,
            'data'    => '',
            'mensaje'	=> 'hay que cerrar el documento'
          );

        }else{

          return $respuesta = array(
            'error'   => false,
            'data'    => '',
            'mensaje'	=> 'Aun no se puede cerrar el documento '.$CodDoc." -- ".$TypeDoc
          );

        }

      }else{ // en caso de que sea otra moneda
        $VlrDoc = round( ( $resVlrPay[0]['doctotal'] * $resVlrPay[0]['tasadoc'] ), $DECI_MALES ); // valor del documento transforma a la tasa del dia del documento
        $VlrActual = $resVlrPay[0]['paytoday'];

        $RestVlr =  round($VlrDoc - $VlrActual, $DECI_MALES);

        if($RestVlr == 0 ){

          return $respuesta = array(
            'error'   => true,
            'data'    => '',
            'mensaje'	=> 'hay que cerrar el documento'
          );

        }else{

          return $respuesta = array(
            'error'   => false,
            'data'    => '',
            'mensaje'	=> 'Aun no se puede cerrar el documento'
          );

        }

      }

    }else{

      return $respuesta = array(
        'error'   => false,
        'data'    => '',
        'mensaje'	=> 'No tiene valor para realizar la operacion '.$CodDoc." -- ".$TypeDoc);
    }

  }


  //Verifica antes de crear un documento que genera contabilidad
  //que dicho asiento creado no este descuadrado
  //osea que la suma de sus debitos y creditos
  //sea igual
  public function validateAccountingAccent($TransId){

    //Validar el resultado del movimiento contable

    $sqlmac1 = "SELECT coalesce(sum(ac1_debit),0) as debit,
                coalesce(sum(ac1_credit),0) as credit,
                coalesce(sum(ac1_debit_sys),0) as debit_sys,
                coalesce(sum(ac1_credit_sys),0) as credit_sys,
                case
                when coalesce(sum(ac1_debit),0) = 0 then 0
                when coalesce(sum(ac1_credit),0) = 0 then 0
                when (coalesce(sum(ac1_debit),0) - coalesce(sum(ac1_credit),0)) = 0 then 1
                else  0
                end as result
                FROM mac1
                WHERE ac1_trans_id = :ac1_trans_id";

    $ressqlmac1 = $this->ci->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $TransId));

    $sqlmac2 = "SELECT coalesce(sum(ac1_debit_sys),0) as debit,
    coalesce(sum(ac1_credit_sys),0) as credit,
    coalesce(sum(ac1_debit_sys),0) as debit_sys,
    coalesce(sum(ac1_credit_sys),0) as credit_sys,
    case
    when coalesce(sum(ac1_debit_sys),0) = 0 then 0
    when coalesce(sum(ac1_credit_sys),0) = 0 then 0
    when (coalesce(sum(ac1_debit_sys),0) - coalesce(sum(ac1_credit_sys),0)) = 0 then 1
    else  0
    end as result
    FROM mac1
    WHERE ac1_trans_id = :ac1_trans_id";

    $ressqlmac2 = $this->ci->pedeo->queryTable($sqlmac2, array(':ac1_trans_id' => $TransId));

    if( isset( $ressqlmac1[0]['result'] ) &&  $ressqlmac1[0]['result'] == 1 ){

      $resultado = array(
        'error'   => false,
        'data'    => $ressqlmac1,
        'mensaje'	=> '');

      if( isset( $ressqlmac2[0]['result'] ) &&  $ressqlmac2[0]['result'] == 1 ){

        $resultado = array(
          'error'   => false,
          'data'    => $ressqlmac2,
          'mensaje'	=> '');

      }else{

        $resultado = array(
          'error'   => true,
          'data'    => $ressqlmac2,
          'mensaje'	=> 'No se pudo generar el documento debido a que no coincide la suma de los  débitos y créditos de sistema en el asiento contable que se intentó generar '.$TransId);

      }

    }else{

      $resultado = array(
        'error'   => true,
        'data'    => $ressqlmac1,
        'mensaje'	=> 'No se pudo generar el documento debido a que no coincide la suma de los  débitos y créditos en el asiento contable que se intentó generar '.$TransId);
    }

    return $resultado;

  }

  // ESTA FUNCION SOLO VALIDA LOS DEBITOS Y CREDITOS
  public function validateAccountingAccent2($TransId){

    //Validar el resultado del movimiento contable

    $sqlmac1 = "SELECT coalesce(sum(ac1_debit),0) as debit,
                coalesce(sum(ac1_credit),0) as credit,
                case
                when coalesce(sum(ac1_debit),0) = 0 then 0
                when coalesce(sum(ac1_credit),0) = 0 then 0
                when (coalesce(sum(ac1_debit),0) - coalesce(sum(ac1_credit),0)) = 0 then 1
                else  0
                end as result
                FROM mac1
                WHERE ac1_trans_id = :ac1_trans_id";

    $ressqlmac1 = $this->ci->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $TransId));

    if( isset( $ressqlmac1[0]['result'] ) &&  $ressqlmac1[0]['result'] == 1 ){

      $resultado = array(
        'error'   => false,
        'data'    => $ressqlmac1,
        'mensaje'	=> '');

    }else{

      $resultado = array(
        'error'   => true,
        'data'    => $ressqlmac1,
        'mensaje'	=> 'No se pudo generar el documento debido a que no coincide la suma de los  débitos y créditos en el asiento contable que se intentó generar '.$TransId);
    }

    return $resultado;

  }

  // SE OBTIENE CANTIDAD EN LA UNIDAD DE MEDIDA PARA LA VENTA DE UN ARTICULO
  public function getUomSale($Item) {
    $sqlSelect = "SELECT coalesce(dma_uom_semb, 0) as dma_uom_semb FROM dmar WHERE dma_item_code = :dma_item_code";
    $resSelect = $this->ci->pedeo->queryTable($sqlSelect, array(':dma_item_code' => $Item));

    if ( isset( $resSelect[0] ) && $resSelect[0]['dma_uom_semb'] > 0 ) {

      $resultado = $resSelect[0]['dma_uom_semb'];

    } else {

      $resultado = 0;

    }

    return $resultado;

  }


  // SE OBTIENE CANTIDAD EN LA UNIDAD DE MEDIDA PARA LA COMPRA UN ARTICULO
  public function getUomPurchase($Item) {
    $sqlSelect = "SELECT coalesce(dma_uom_pemb, 0) as dma_uom_pemb FROM dmar WHERE dma_item_code = :dma_item_code";
    $resSelect = $this->ci->pedeo->queryTable($sqlSelect, array(':dma_item_code' => $Item));

    if ( isset( $resSelect[0] ) && $resSelect[0]['dma_uom_pemb'] > 0 ) {

      $resultado = $resSelect[0]['dma_uom_pemb'];

    } else {

      $resultado = 0;

    }

    return $resultado;

  }


  // AGREGAR SERIALES POR CUALQUIER DOCUMENTO QUE LO REQUIERA
  public function addSerial($Seriales,$Item,$Doctype,$DocEntry,$DocNum,$DocDate,$Status,$Comment,$Whscode,$Quantity,$Createby,$IdLinea,$Business) {
    // $Seriales = ARRAY CON LOS SERRIALES DEL ARTICULO
    // $Item = CODIGO DEL ARTICULO
    // $Doctype = TIPO DE DOCUMENTO
    // $DocEntry = ID DEL DEL DOCUMENTO
    // $DocDate FECHA DEL DOCUMENTO
    // $Status = INDICA SI SE INGRESA AL IVENTARIO O SI SE SACA DEL INVENTARIO, EL ESTADO INDICA EN EL MOVIMIENTO DE SERIALES SI EL SERIAL ESTA DENTRO O FUERA 
    // DEL ALMACEN PARA EL CASO DE 1 DENTRO DEL ALMACEN 2 FUERA DEL ALMACEN
    // $Comment COMENTARIO EL DOCUMENTO
    // $Whscode = CODIGO DEL ALMACEN
    // $Quantity = CANTIDAD DE ARTICULOS O SERIALES
    // $Createby = USUARIO QUIEN ESTA HACIENDO LA OPERACION
    // $IdLinea = ID DE LA LINEA DEL DOCUMENTO
    // $Business = CODIGO DE LA EMPRESA


    $ContenidoDetalle = $Seriales;
    if(!is_array($ContenidoDetalle)){


      $respuesta = array(
      'error'   => true,
      'data'    => array(),
      'mensaje' =>'No se encontraron los seriales para el item: '.$Item
      );


      return $respuesta;
    }

    if(!intval(count($ContenidoDetalle)) > 0 ){

      $respuesta = array(
      	'error' => true,
      	'data'  => array(),
        'mensaje' =>'No se encontraron los seriales para el item: '.$Item
      );



      return $respuesta;
    }

    // SE VALIDA QUE LA CANTIDAD DE SERIALES SEA IGUAL A LA CANTIDA DE ITEMS
    if( count($ContenidoDetalle) != $Quantity ){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La cantidad de seriales del item: '.$Item.' no es igual a la cantidad de articulos'
      );



      return $respuesta;
    }



    foreach ($ContenidoDetalle as $key => $value) {
      // SE VERIFICAR QUE LOS SERIALES NO EXISTAN

      if ( $Status == 2 || $Status == "2" ){

        $sqlVerificar = "SELECT * FROM tmsn where  msn_sn = :msn_sn and msn_whscode = :msn_whscode and business = :business limit 1";
        $resVerificar = $this->ci->pedeo->queryTable($sqlVerificar, array(
          ':msn_sn'       => $value,
          ':msn_whscode'  => $Whscode,
          ':business'     => $Business
        ));

        if ( !isset( $resVerificar[0] ) ){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'Serial no registrado: '.$value
          );

          return $respuesta;

        }

      }


      $sqlVerificar = "SELECT * FROM tmsn where msn_itemcode = :msn_itemcode and msn_sn =  :msn_sn and msn_whscode = :msn_whscode and msn_status = :msn_status and msn_basetype = :msn_basetype and business = :business
                      order by msn_id desc limit 1";
      $resVerificar = $this->ci->pedeo->queryTable($sqlVerificar, array(
        ':msn_itemcode' => $Item,
        ':msn_whscode'  => $Whscode,
        ':msn_status'   => $Status,
        ':msn_sn'       => $value,
        ':msn_basetype' => $Doctype,
        ':business'     => $Business
      ));

      if ( isset( $resVerificar[0] ) ){


        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'El serial: '.$value.' ya esta registrado'
        );

        return $respuesta;

      }else{

          $sqlInsertMS = "INSERT INTO tmsn(msn_itemcode, msn_quantity, msn_sn, msn_createat, msn_createby, msn_basetype, msn_baseentry, msn_docnum, msn_docdate, msn_comment, msn_status, msn_whscode, msn_line, business)
	                        VALUES (:msn_itemcode, :msn_quantity, :msn_sn, :msn_createat, :msn_createby, :msn_basetype, :msn_baseentry, :msn_docnum, :msn_docdate, :msn_comment, :msn_status, :msn_whscode, :msn_line, :business)";

          $resInsertMS = $this->ci->pedeo->insertRow($sqlInsertMS, array(

            ':msn_itemcode'  => $Item,
            ':msn_quantity'  => 1,
            ':msn_sn'        => $value,
            ':msn_createat'  => date('Y-m-d'),
            ':msn_createby'  => $Createby,
            ':msn_basetype'  => $Doctype,
            ':msn_baseentry' => $DocEntry,
            ':msn_docnum'    => $DocNum,
            ':msn_docdate'   => $DocDate,
            ':msn_comment'   => $Comment,
            ':msn_status'    => $Status,
            ':msn_whscode'   => $Whscode,
            ':msn_line'      => $IdLinea,
            ':business'      => $Business
          ));

          if ( is_numeric($resInsertMS) && $resInsertMS > 0 ){

          } else {


            $respuesta = array(
              'error' => true,
              'data'  => $resInsertMS,
              'mensaje' =>'Error al insertar el movimiento del serial: '.$value
            );


            return $respuesta;
          }
      }

    }


    $respuesta = array(
      'error'   => false,
      'data'    => '',
      'mensaje' =>'success'
    );

    return $respuesta;
  }


  // SE OBTIENE LA CANTIDAD DE DECIMALES QUE MANEJA EL SISTEMA
  //
  public function getDecimals(){

    $sqlSelect = "SELECT decimals FROM params";

    $resSelect = $this->ci->pedeo->queryTable($sqlSelect, array());

    if ( isset( $resSelect[0] ) ) {

      $resultado = $resSelect[0]['decimals'];

    } else {

      $resultado = 2;

    }

    return $resultado;

  }
  //

  // SE OBTIENE LA CANTIDAD DE DECIMALES QUE MANEJA EL SISTEMA
  //
  public function getParams() {

    $sqlSelect = "SELECT * FROM params";

    $resSelect = $this->ci->pedeo->queryTable($sqlSelect, array());

    $resultado = [];

    if ( isset( $resSelect[0] ) ) {

      $resultado = $resSelect[0];

    } 

    return $resultado;

  }
  //


  // SE OBTIENE LA CANTAIDAD DE DECIMALES QUE MANEJA EL SISTEMA
  //
  public function getLocalCurrency(){

    $sqlSelect = "SELECT coalesce(get_localcur(),'') AS localcur";

    $resSelect = $this->ci->pedeo->queryTable($sqlSelect, array());

    if ( isset( $resSelect[0] ) ) {

      $resultado = $resSelect[0]['localcur'];

    } else {

      $resultado = 'NA';

    }

    return $resultado;

  }
  
  // CALCULA Y DEVUELVE LA CANTIDAD DE ARTICULO PARA EL INVENTARIO
  // ESTE METODO SOLO SE USA EN LOS MODULOS DE COMPRA
  public function getCantInv($cantDoc,$unmP,$unmS){
    
    // $cantDoc = CANTIDAD DE ARTICULOS EN LA LINEA
    // $unmP  = CANTIDAD DE EMBALAJE PARA LA UNIDAD DE MEDIDA PARA COMPRAS
    // $unm   = CANTIDAD DE EMBALAJE PARA LA UNIDAD DE MEDIDA DE VENTAS

    $response = 0;
   
    if ( $unmP > 0 ) { // EVADIENDO DIVICION ENTRE 0

      $response = ( ( $cantDoc * $unmP ) / $unmS );

    }

    return $response;

  }
  //

  // VALIDA lA LONGITUD DEL CAMPO FECHA
	public function validateDate($fecha){
			if(strlen($fecha) == 10 OR strlen($fecha) > 10){
				return true;
			}else{
				return false;
			}
	}



  // GENERA UN PAGO RECIBIDO A PARTIR DE UNA FACTURA ANTICIPADA
  // O FACTURA DE VENTAS SI EL TIPO DE PAGO ES CONTADO
  public function createPaymentReceived($Data, $Factura, $LineCXC, $AcctLine){

   
    // $Data contiene el payload del $_POST enviado en la factura
    // $Factura : es el docentry de la factura creada
    // $LineCXC : es el ac1_line_num de la cuenta por cobrar del socio de negocio
    // $AcctLine : Cuenta contable por cobrar del tercero
    // factura anticipada
    // factura de ventas
		$DECI_MALES =  $this->getDecimals();
		$DocNumVerificado = 0;
		$DetalleAsientoCuentaTercero = new stdClass();
		$DetalleConsolidadoAsientoCuentaTercero = [];
		$llaveAsientoCuentaTercero = "";
		$posicionAsientoCuentaTercero = 0;
		$cuentaTercero = 0;
		$inArrayAsientoCuentaTercero = array();
		$VlrTotalOpc = 0;
		$VlrDiff = 0;
		$VlrPagoEfectuado = 0;
		$TasaOrg = 0;
		$VlrDiffN = 0;
		$VlrDiffP = 0;
		$DFPC = 0; // diferencia en peso credito
		$DFPD = 0; // diferencia en peso debito
		$DFPCS = 0; // del sistema en credito
		$DFPDS = 0; // del sistema en debito
    $ContenidoPago = json_decode($Data['pay_detail'], true);

    if (!is_array($ContenidoPago)) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontró el detalle del pago'
			);

			return $respuesta;
		}

    // Se globaliza la variable sqlDetalleAsiento
    $sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
    ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
    ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
    ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, business, branch)VALUES (:ac1_trans_id, :ac1_account,
    :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
    :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
    :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
    :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :business, :branch)";


    //VALIDANDO PERIODO CONTABLE
		$periodo = $this->generic->ValidatePeriod($Data['bpr_docdate'], $Data['bpr_docdate'], $Data['bpr_docduedate'], 0);

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

		// //BUSCANDO LA NUMERACION DEL DOCUMENTO
		$DocNumVerificado = $this->ci->documentnumbering->NumberDoc($Data['dt1_series'],$Data['dt1_docdate'],$Data['dt1_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $DocNumVerificado;
		}


    //PROCESO DE TASA
		$dataTasa = $this->tasa->Tasa($Data['bpr_currency'],$Data['bpr_docdate']);

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

		// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO


    // BUSCANDO LA CUENTA DEL TERCERO

		$sqlCuentaTercero = "SELECT  f1.dms_card_code, f2.mgs_acct FROM dmsn AS f1
    JOIN dmgs  AS f2
    ON CAST(f2.mgs_id AS varchar(100)) = f1.dms_group_num
    WHERE  f1.dms_card_code = :dms_card_code
    AND f1.dms_card_type = '1'"; // 1 para clientes

    $resCuentaTercero = $this->ci->pedeo->queryTable($sqlCuentaTercero, array(":dms_card_code" => $Data['dvf_cardcode']));

    if (isset($resCuentaTercero[0])) {
      $cuentaTercero = $resCuentaTercero[0]['mgs_acct'];
    } else {


      $respuesta = array(
      'error'   => true,
      'data' => $resCuentaTercero,
      'mensaje'	=> 'No se pudo registrar el pago, el tercero no tiene la cuenta asociada (' . $Data['dvf_cardcode'] . ')'
      );

      return $respuesta;
    }

    $sqlInsert = "INSERT INTO
                      gbpr (bpr_cardcode,bpr_doctype,bpr_cardname,bpr_address,bpr_perscontact,bpr_series,bpr_docnum,bpr_docdate,bpr_taxdate,bpr_ref,bpr_transid,
                      bpr_comments,bpr_memo,bpr_acctransfer,bpr_datetransfer,bpr_reftransfer,bpr_doctotal,bpr_vlrpaid,bpr_project,bpr_createby,
                      bpr_createat,bpr_payment,bpr_currency, business, branch)
                VALUES (:bpr_cardcode,:bpr_doctype,:bpr_cardname,:bpr_address,:bpr_perscontact,:bpr_series,:bpr_docnum,:bpr_docdate,:bpr_taxdate,:bpr_ref,:bpr_transid,
                      :bpr_comments,:bpr_memo,:bpr_acctransfer,:bpr_datetransfer,:bpr_reftransfer,:bpr_doctotal,:bpr_vlrpaid,:bpr_project,:bpr_createby,
                      :bpr_createat,:bpr_payment,:bpr_currency, :business, :branch)";

 

    $resInsert = $this->ci->pedeo->insertRow($sqlInsert, array(
			':bpr_cardcode' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
			':bpr_doctype' => 20,
			':bpr_cardname' => isset($Data['dvf_cardname']) ? $Data['dvf_cardname'] : NULL,
			':bpr_address' => isset($Data['dvf_address']) ? $Data['dvf_address'] : NULL,
			':bpr_perscontact' => is_numeric($Data['dvf_contacid']) ? $Data['dvf_contacid'] : 0,
			':bpr_series' => is_numeric($ContenidoPago['dtl_series']) ? $ContenidoPago['dtl_series'] : 0,
			':bpr_docnum' => $DocNumVerificado,
			':bpr_docdate' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
			':bpr_taxdate' => $this->validateDate($Data['dvf_duedev']) ? $Data['dvf_duedev'] : NULL,
			':bpr_ref' => isset($Data['dvf_ref']) ? $Data['dvf_ref'] : NULL,
			':bpr_transid' => isset($Data['dvf_transid']) && is_numeric($Data['dvf_transid']) ? $Data['dvf_transid'] : 0,
			':bpr_comments' => isset($Data['dvf_comment']) ? $Data['dvf_comment'] : NULL,
			':bpr_memo' =>  'Pago recibido - '. $Data['dvf_cardcode'].' - '. $Data['dvf_cardname'],
			':bpr_acctransfer' => isset($ContenidoPago['dtl_accountId']) ? $ContenidoPago['dtl_accountId']:NULL,
			':bpr_datetransfer' => isset($ContenidoPago['dtl_datetransfer']) ? $ContenidoPago['dtl_datetransfer']:NULL,
			':bpr_reftransfer' => isset($ContenidoPago['dtl_reftransfer']) ? $ContenidoPago['dtl_reftransfer']:NULL,
			':bpr_doctotal' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
			':bpr_vlrpaid' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
			':bpr_project' => isset($Data['dvf_project']) ? $Data['dvf_project'] : NULL,
			':bpr_createby' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
			':bpr_createat' => $this->validateDate($Data['dvf_createat']) ? $Data['dvf_createat'] : NULL,
			':bpr_payment' => isset($Data['dvf_payment']) ? $Data['dvf_payment'] : NULL,
			':bpr_currency' => isset($Data['dvf_currency']) ? $Data['dvf_currency'] : NULL,
			':business' => $Data['business'],
			':branch' => $Data['branch']
		));

		if (is_numeric($resInsert) && $resInsert > 0) {

			// Se actualiza la serie de la numeracion del documento

			$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
										            WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->ci->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $ContenidoPago['dtl_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
			
				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear el pago'
				);

        return $respuesta;
			}
			// Fin de la actualizacion de la numeracion del documento

      //Se agregan los asientos contables*/*******

			$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch, mac_accperiod)
      VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch, :mac_accperiod)";


      $resInsertAsiento = $this->ci->pedeo->insertRow($sqlInsertAsiento, array(

      ':mac_doc_num' => 1,
      ':mac_status' => 1,
      ':mac_base_type' => 20,
      ':mac_base_entry' => $resInsert,
      ':mac_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
      ':mac_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
      ':mac_legal_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
      ':mac_ref1' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
      ':mac_ref2' => "",
      ':mac_ref3' => "",
      ':mac_loc_total' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
      ':mac_fc_total' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
      ':mac_sys_total' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
      ':mac_trans_dode' => 1,
      ':mac_beline_nume' => 1,
      ':mac_vat_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
      ':mac_serie' => 1,
      ':mac_number' => 1,
      ':mac_bammntsys' => 0,
      ':mac_bammnt' => 0,
      ':mac_wtsum' => 1,
      ':mac_vatsum' => 0,
      ':mac_comments' => isset($Data['dvf_comments']) ? $Data['dvf_comments'] : NULL,
      ':mac_create_date' => $this->validateDate($Data['dvf_createat']) ? $Data['dvf_createat'] : NULL,
      ':mac_made_usuer' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
      ':mac_update_date' => date("Y-m-d"),
      ':mac_update_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
      ':business' => $Data['business'],
      ':branch' => $Data['branch'],
      ':mac_accperiod' => $periodo['data']
      ));


      if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {
        // Se verifica que el detalle no de error insertando //
      } else {

        // si falla algun insert del detalle de la Entrega de Ventas se devuelven los cambios realizados por la transaccion,
        // se retorna el error y se detiene la ejecucion del codigo restante.

        $respuesta = array(
        'error'   => true,
        'data'	  => $resInsertAsiento,
        'mensaje'	=> 'No se pudo registrar el pago'
        );

        return $respuesta;
      }

      
      $VrlPagoDetalleNormal  = 0;
      $Equiv = 0;

      //VALIDAR EL VALOR QUE SE ESTA PAGANDO NO SEA MAYOR AL SALDO DEL DOCUMENTO
      if ( $Data['dvf_doctype'] == 5  || $Data['dvf_doctype'] == 34 ) {

        $pf = "dvf";
        $tb  = "dvfv";


  
        $resVlrPay = $this->validateBalance($Factura, $Data['dvf_doctype'], $tb, $pf, $ContenidoPago['dtl_total'], $Data['dvf_currency'], $Data['dvf_docdate'], 1, 1);

        if (isset($resVlrPay['error'])) {

          if ($resVlrPay['error'] === false) {

            $VlrTotalOpc = $resVlrPay['vlrop'];
            $VlrDiff     = ($VlrDiff + $resVlrPay['vlrdiff']);
            $TasaOrg     = $resVlrPay['tasadoc'];
            $Equiv       = $resVlrPay['equiv'];

            $VlrTotalOpc = ($VlrTotalOpc + $Equiv);
          } else {

            $respuesta = array(
              'error'   => true,
              'data'    => [],
              'mensaje'	=> $resVlrPay['mensaje']
            );

            return $respuesta;

            
          }
        } else {

          $respuesta = array(
            'error'   => true,
            'data'    => [],
            'mensaje'	=> 'No se pudo validar el saldo actual del documento ' . $Factura
          );

          return $respuesta;

        }
      }

      $sqlInsertDetail = "INSERT INTO
                          bpr1 (pr1_docnum,pr1_docentry,pr1_numref,pr1_docdate,pr1_vlrtotal,pr1_vlrpaid,pr1_comments,pr1_porcdiscount,pr1_doctype,
                          pr1_docduedate,pr1_daysbackw,pr1_vlrdiscount,pr1_ocrcode, pr1_accountid)
                          VALUES (:pr1_docnum,:pr1_docentry,:pr1_numref,:pr1_docdate,:pr1_vlrtotal,:pr1_vlrpaid,:pr1_comments,:pr1_porcdiscount,
                          :pr1_doctype,:pr1_docduedate,:pr1_daysbackw,:pr1_vlrdiscount,:pr1_ocrcode, :pr1_accountid)";

      $resInsertDetail = $this->ci->pedeo->insertRow($sqlInsertDetail, array(
        ':pr1_docnum' => $resInsert,
        ':pr1_docentry' => $Factura,
        ':pr1_numref' => isset($Data['dvf_numref']) ? $Data['dvf_numref'] : NULL,
        ':pr1_docdate' =>  $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
        ':pr1_vlrtotal' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
        ':pr1_vlrpaid' => is_numeric($Data['dvf_doctotal']) ? $Data['dvf_doctotal'] : 0,
        ':pr1_comments' => isset($Data['dvf_comments']) ? $Data['dvf_comments'] : NULL,
        ':pr1_porcdiscount' => isset($Data['dvf_porcdiscount']) && is_numeric($Data['dvf_porcdiscount']) ? $Data['dvf_porcdiscount'] : 0,
        ':pr1_doctype' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0,
        ':pr1_docduedate' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
        ':pr1_daysbackw' => isset($Data['dvf_daysbackw']) && is_numeric($Data['dvf_daysbackw']) ? $Data['dvf_daysbackw'] : 0,
        ':pr1_vlrdiscount' => isset($Data['dvf_vlrdiscount']) && is_numeric($Data['dvf_vlrdiscount']) ? $Data['dvf_vlrdiscount'] : 0,
        ':pr1_ocrcode' => isset($Data['dvf_ocrcode']) ? $Data['dvf_ocrcode'] : NULL,
        ':pr1_accountid' => $AcctLine

      ));

      // Se verifica que el detalle no de error insertando //
      if (is_numeric($resInsertDetail) && $resInsertDetail > 0) {

          //MOVIMIENTO DE DOCUMENTOS
          if ( $Data['dvf_doctype'] == 5 || $Data['dvf_doctype'] == 34 ) {
            //SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
            if (isset($Factura) && is_numeric($Factura) && isset($Data['dvf_doctype']) && is_numeric($Data['dvf_doctype'])) {

              $sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
              $resDocInicio = $this->ci->pedeo->queryTable($sqlDocInicio, array(
                ':bmd_doctype' => $Data['dvf_doctype'],
                ':bmd_docentry' => $Factura
              ));


              if (isset($resDocInicio[0])) {

                $sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
														bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype, bmd_currency)
														VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
														:bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype, :bmd_currency)";

                $resInsertMD = $this->ci->pedeo->insertRow($sqlInsertMD, array(

                  ':bmd_doctype' =>  20,
                  ':bmd_docentry' => $resInsert,
                  ':bmd_createat' => $this->validateDate($Data['dvf_createat']) ? $Data['dvf_createat'] : NULL,
                  ':bmd_doctypeo' => is_numeric($Data['dvf_doctype']) ? $Data['dvf_doctype'] : 0, //ORIGEN
                  ':bmd_docentryo' => $Factura,  //ORIGEN
                  ':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
                  ':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
                  ':bmd_docnum' => $DocNumVerificado,
                  ':bmd_doctotal' => $VlrTotalOpc,
                  ':bmd_cardcode' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
                  ':bmd_cardtype' => 1,
					        ':bmd_currency' => isset($Data['dvf_currency'])?$Data['dvf_currency']:NULL,
                ));

                if (is_numeric($resInsertMD) && $resInsertMD > 0) {
                } else {

                  $respuesta = array(
                    'error'   => true,
                    'data' => $resInsertMD,
                    'mensaje'	=> 'No se pudo registrar el movimiento del documento'
                  );


                  return $respuesta;

                }

              }
            }
            //FIN PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS

            //
            if ( $Data['dvf_doctype'] == 5 || $Data['dvf_doctype'] == 34) { // SOLO CUANDO ES UNA FACTURA

              $sqlUpdateFactPay = "UPDATE dvfv SET dvf_paytoday = COALESCE(dvf_paytoday,0)+:dvf_paytoday WHERE dvf_docentry = :dvf_docentry and dvf_doctype = :dvf_doctype";

              $resUpdateFactPay = $this->ci->pedeo->updateRow($sqlUpdateFactPay, array(

                ':dvf_paytoday' => round($VlrTotalOpc, $DECI_MALES),
                ':dvf_docentry' => $Factura,
                ':dvf_doctype'  => $Data['dvf_doctype']


              ));

              if (is_numeric($resUpdateFactPay) && $resUpdateFactPay == 1) {
              } else {

                $respuesta = array(
                  'error'   => true,
                  'data' => $resUpdateFactPay,
                  'mensaje'	=> 'No se pudo actualizar el valor del pago (pago hoy) en la factura ' . $Factura
                );

                return $respuesta;
              }
            }

            // ACTUALIZAR REFERENCIA DE PAGO EN ASIENTO CONTABLE DE LA FACTURA
            if ( $Data['dvf_doctype'] == 5 || $Data['dvf_doctype'] == 34 ) { // SOLO CUANDO ES UNA FACTURA
  

              $slqUpdateVenDebit = "UPDATE mac1
                        SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
                        WHERE ac1_line_num = :ac1_line_num";
              $resUpdateVenDebit = $this->ci->pedeo->updateRow($slqUpdateVenDebit, array(

                ':ac1_ven_credit' => round($VlrTotalOpc, $DECI_MALES),
                ':ac1_line_num'   => $LineCXC
              ));

            
              if (is_numeric($resUpdateVenDebit) && $resUpdateVenDebit == 1) {
              } else {

                $respuesta = array(
                  'error'   => true,
                  'data' => $resUpdateVenDebit,
                  'mensaje'	=> 'No se pudo actualizar el valor del pago en la factura ' . $Factura
                );

                return $respuesta;
              }

            }
            // SE VALIDA SALDOS PARA CERRAR FACTURA
            if ( $Data['dvf_doctype'] == 5 || $Data['dvf_doctype'] == 34 ) {

              $resEstado = $this->validateBalanceAndClose($Factura, $Data['dvf_doctype'], 'dvfv', 'dvf');

              if (isset($resEstado['error']) && $resEstado['error'] === true) {

                $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                                    VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

                $resInsertEstado = $this->ci->pedeo->insertRow($sqlInsertEstado, array(

                  ':bed_docentry'  => $Factura ,
                  ':bed_doctype'   => $Data['dvf_doctype'],
                  ':bed_status'    => 3, //ESTADO CERRADO
                  ':bed_createby'  => $Data['dvf_createby'],
                  ':bed_date'      => date('Y-m-d'),
                  ':bed_baseentry' => $resInsert,
                  ':bed_basetype'  => 20

                ));

              
                if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
                } else {

                  $respuesta = array(
                    'error'   => true,
                    'data' => $resInsertEstado,
                    'mensaje'	=> 'No se pudo registrar el pago'
                  );


                  return $respuesta;

                }
              }
            }

          }
      }

      // SE INSERTA ASIENTO CUENTA BANCO
      $debito = 0;
      $credito = 0;
      $MontoSysDB = 0;
      $MontoSysCR = 0;
      $cuenta = $ContenidoPago['dtl_accountId'];
      $codigoCuentaIngreso = substr($cuenta, 0, 1);
      $granTotalIngreso = $Data['dvf_doctotal'];
      $granTotalIngresoOriginal = $granTotalIngreso;

      if (trim($Data['dvf_currency']) != $MONEDALOCAL) {
        $granTotalIngreso = ($granTotalIngreso * $TasaDocLoc);
      }

      switch ($codigoCuentaIngreso) {
        case 1:
          $debito = $granTotalIngreso;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysDB = ($debito / $TasaLocSys);
          } else {

            $MontoSysDB = $granTotalIngresoOriginal;
          }
          break;

        case 2:
          $debito = $granTotalIngreso;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysDB = ($debito / $TasaLocSys);
          } else {

            $MontoSysDB = $granTotalIngresoOriginal;
          }
          break;

        case 3:
          $debito = $granTotalIngreso;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysDB = ($debito / $TasaLocSys);
          } else {

            $MontoSysDB = $granTotalIngresoOriginal;
          }
          break;

        case 4:
          $debito = $granTotalIngreso;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysDB = ($debito / $TasaLocSys);
          } else {

            $MontoSysDB = $granTotalIngresoOriginal;
          }
          break;

        case 5:
          $debito = $granTotalIngreso;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysDB = ($debito / $TasaLocSys);
          } else {

            $MontoSysDB = $granTotalIngresoOriginal;
          }
          break;

        case 6:
          $debito = $granTotalIngreso;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysDB = ($debito / $TasaLocSys);
          } else {

            $MontoSysDB = $granTotalIngresoOriginal;
          }
          break;

        case 7:
          $debito = $granTotalIngreso;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysDB = ($debito / $TasaLocSys);
          } else {

            $MontoSysDB = $granTotalIngresoOriginal;
          }
          break;
      }

      // SE AGREGA AL BALANCE
      $BALANCE = [];
      if ( $debito > 0 ){
        $BALANCE = $this->ci->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuenta, 1, $Data['dvf_docdate'], $Data['business'], $Data['branch']);
      }else{
        $BALANCE = $this->ci->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuenta, 2, $Data['dvf_docdate'], $Data['business'], $Data['branch']);
      }

      if (isset($BALANCE['error']) && $BALANCE['error'] == true){
        $respuesta = array(
          'error' => true,
          'data' => $resSqlInsertHeader,
          'mensaje' => 'No se pudo aumentar el balance de la cuenta'
        );

        return $respuesta;
      }
      //

      $resDetalleAsiento = $this->ci->pedeo->insertRow($sqlDetalleAsiento, array(

        ':ac1_trans_id' => $resInsertAsiento,
        ':ac1_account' => $cuenta,
        ':ac1_debit' => round($debito, $DECI_MALES),
        ':ac1_credit' => round($credito, $DECI_MALES),
        ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
        ':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
        ':ac1_currex' => 0,
        ':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
        ':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
        ':ac1_debit_import' => 0,
        ':ac1_credit_import' => 0,
        ':ac1_debit_importsys' => 0,
        ':ac1_credit_importsys' => 0,
        ':ac1_font_key' => $resInsert,
        ':ac1_font_line' => 1,
        ':ac1_font_type' => 20,
        ':ac1_accountvs' => 1,
        ':ac1_doctype' => 18,
        ':ac1_ref1' => "",
        ':ac1_ref2' => "",
        ':ac1_ref3' => "",
        ':ac1_prc_code' => 0,
        ':ac1_uncode' => 0,
        ':ac1_prj_code' => 0,
        ':ac1_rescon_date' => NULL,
        ':ac1_recon_total' => 0,
        ':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
        ':ac1_accperiod' => $periodo['data'],
        ':ac1_close' => 0,
        ':ac1_cord' => 0,
        ':ac1_ven_debit' => 0,
        ':ac1_ven_credit' => 0,
        ':ac1_fiscal_acct' => 0,
        ':ac1_taxid' => 1,
        ':ac1_isrti' => 0,
        ':ac1_basert' => 0,
        ':ac1_mmcode' => 0,
        ':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
        ':ac1_codref' => 1,
        ':business' => $Data['business'],
        ':branch' => $Data['branch']
      ));

      if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
        // Se verifica que el detalle no de error insertando //
      } else {

        $respuesta = array(
          'error'   => true,
          'data'	  => $resDetalleAsiento,
          'mensaje'	=> 'No se pudo registrar el pago recibido, ocurrio un error al ingresar el asiento del ingreso'
        );

        return $respuesta;
      }

    
      $codigoCuenta = substr($cuentaTercero, 0, 1);
      $debito = 0;
      $credito = 0;
      $MontoSysDB = 0;
      $MontoSysCR = 0;
      $TotalPagoRecibido = $Data['dvf_doctotal'];
      $TotalPagoRecibidoOriginal = $TotalPagoRecibido;
      
      if (trim($Data['dvf_currency']) != $MONEDALOCAL) {
        $TotalPagoRecibido = ($TotalPagoRecibido * $TasaDocLoc);
      }

      switch ($codigoCuenta) {
        case 1:
          $credito = $TotalPagoRecibido;

          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysCR = ($credito / $TasaLocSys);
          } else {

            $MontoSysCR = $TotalPagoRecibidoOriginal;
          }

          break;

        case 2:
          $credito = $TotalPagoRecibido;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysCR = ($credito / $TasaLocSys);
          } else {

            $MontoSysCR = $TotalPagoRecibidoOriginal;
          }
          break;

        case 3:
          $credito = $TotalPagoRecibido;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysCR = ($credito / $TasaLocSys);
          } else {

            $MontoSysCR = $TotalPagoRecibidoOriginal;
          }
          break;

        case 4:
          $credito = $TotalPagoRecibido;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysCR = ($credito / $TasaLocSys);
          } else {

            $MontoSysCR = $TotalPagoRecibidoOriginal;
          }
          break;

        case 5:
          $credito = $TotalPagoRecibido;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysCR = ($credito / $TasaLocSys);
          } else {

            $MontoSysCR = $TotalPagoRecibidoOriginal;
          }
          break;

        case 6:
          $credito = $TotalPagoRecibido;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysCR = ($credito / $TasaLocSys);
          } else {

            $MontoSysCR = $TotalPagoRecibidoOriginal;
          }
          break;

        case 7:
          $credito = $TotalPagoRecibido;
          if (trim($Data['dvf_currency']) != $MONEDASYS) {

            $MontoSysCR = ($credito / $TasaLocSys);
          } else {

            $MontoSysCR = $TotalPagoRecibidoOriginal;
          }
          break;
      }

      // SE AGREGA AL BALANCE
      $BALANCE = [];
      if ( $debito > 0 ){
        $BALANCE = $this->ci->account->addBalance($periodo['data'], round($debito, $DECI_MALES), $cuentaTercero, 1, $Data['dvf_docdate'], $Data['business'], $Data['branch']);
      }else{
        $BALANCE = $this->ci->account->addBalance($periodo['data'], round($credito, $DECI_MALES), $cuentaTercero, 2, $Data['dvf_docdate'], $Data['business'], $Data['branch']);
      }

      if (isset($BALANCE['error']) && $BALANCE['error'] == true){
        $respuesta = array(
          'error' => true,
          'data' => $resSqlInsertHeader,
          'mensaje' => 'No se pudo aumentar el balance de la cuenta'
        );

        return $respuesta;
      }
      //

      $resDetalleAsiento = $this->ci->pedeo->insertRow($sqlDetalleAsiento, array(

        ':ac1_trans_id' => $resInsertAsiento,
        ':ac1_account' => $cuentaTercero,
        ':ac1_debit' => round($debito, $DECI_MALES),
        ':ac1_credit' => round($credito, $DECI_MALES),
        ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
        ':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
        ':ac1_currex' => 0,
        ':ac1_doc_date' => $this->validateDate($Data['dvf_docdate']) ? $Data['dvf_docdate'] : NULL,
        ':ac1_doc_duedate' => $this->validateDate($Data['dvf_duedate']) ? $Data['dvf_duedate'] : NULL,
        ':ac1_debit_import' => 0,
        ':ac1_credit_import' => 0,
        ':ac1_debit_importsys' => 0,
        ':ac1_credit_importsys' => 0,
        ':ac1_font_key' => $resInsert,
        ':ac1_font_line' => 1,
        ':ac1_font_type' => 20,
        ':ac1_accountvs' => 1,
        ':ac1_doctype' => 18,
        ':ac1_ref1' => "",
        ':ac1_ref2' => "",
        ':ac1_ref3' => "",
        ':ac1_prc_code' => 0,
        ':ac1_uncode' => 0,
        ':ac1_prj_code' => isset($Data['dvf_project']) ? $Data['dvf_project'] : NULL,
        ':ac1_rescon_date' => NULL,
        ':ac1_recon_total' => 0,
        ':ac1_made_user' => isset($Data['dvf_createby']) ? $Data['dvf_createby'] : NULL,
        ':ac1_accperiod' => $periodo['data'],
        ':ac1_close' => 0,
        ':ac1_cord' => 0,
        ':ac1_ven_debit' => $credito == 0 ? round($debito, $DECI_MALES) : round($credito, $DECI_MALES),
        ':ac1_ven_credit' => round($credito, $DECI_MALES),
        ':ac1_fiscal_acct' => 0,
        ':ac1_taxid' => 1,
        ':ac1_isrti' => 0,
        ':ac1_basert' => 0,
        ':ac1_mmcode' => 0,
        ':ac1_legal_num' => isset($Data['dvf_cardcode']) ? $Data['dvf_cardcode'] : NULL,
        ':ac1_codref' => 1,
        ':business' => $Data['business'],
        ':branch' => $Data['branch']
      ));

      if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
        // Se verifica que el detalle no de error insertando //
      } else {

        $respuesta = array(
          'error'   => true,
          'data'	  => $resDetalleAsiento,
          'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento cuenta tercero'
        );

        return $respuesta;

      }

      

      //SE VALIDA LA CONTABILIDAD CREADA
      $validateCont = $this->validateAccountingAccent($resInsertAsiento);
    
      

      if (isset($validateCont['error']) && $validateCont['error'] == false) {
      } else {

        $ressqlmac1 = [];
        $sqlmac1 = "SELECT acc_name,ac1_account,ac1_debit,ac1_credit FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
        $ressqlmac1['contabilidad'] = $this->ci->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));


        $respuesta = array(
          'error'   => true,
          'data' 	  => $ressqlmac1,
          'mensaje' => $validateCont['mensaje'],
          
        );

        return $respuesta;
      }


			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Pago registrado con exito'
			);

    }else{

      $respuesta = array(
        'error'   => true,
        'data'	  => $resInsert,
        'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el pago'
      );

      return $respuesta;
    }


    return $respuesta;

  }


  // TRANSFORMA EL VALOR A PAGAR DE UN DOCUMENTO 
  // AL MONTO SEGUN LA MONEDA DE CREACION DEL MISMO
  public function getBalance($CodDoc, $TypeDoc, $table, $pref, $value, $paycurrency, $datepay, $op, $linenum){

    //$CodDoc = DocEntry  del documento
    //$TypeDoc = DocType del documento
    //$table = nombre de la tabla
    //$pref = prefijo de la tabla
    //$value = monto a pagar
    //$op = tipo de operacion (1) ventas o (2) compra
    //$linenum Codigo  de la linea de la tabla mac1
    $DECI_MALES =  $this->getDecimals();
    $DiffP = 0;
    $DiffN = 0;
    $TotalAccm = 0;
    $Equiv = 0; //Equivalencia por decimales

    $eqvSql = "SELECT get_tax_currency(:currency, :fecha) as tasaparapagoactual";
    $resEqv = $this->ci->pedeo->queryTable($eqvSql, array(
      ':currency' => $paycurrency,
      ':fecha'    => $datepay
    ));

    if ( isset($resEqv[0]) ){

    }else{

      return $respuesta = array(
        'error'   => true,
        'data'    => '',
        'mensaje'	=> 'No se encuentra la tasa para la moneda de la transaccion'
      );

    }

    $TasaCurrent = $resEqv[0]['tasaparapagoactual'];// TASA DE PAGO ACTUAL CON LA MONEDA ACTUAL

    $VlrPay = "";

    $resVlrPay = [];

    if ( $TypeDoc == 18 ){
      
      $VlrPay = "SELECT 0 as paytoday,
                  case
                    when :op = 0 then ac1_debit
                    else ac1_credit
                  end as doctotal,
                  get_localcur() as currency,
                  get_localcur() as monedaloc,
                  get_tax_currency(get_localcur(), ac1_doc_date) as tasadoc
                  FROM mac1
                  WHERE ac1_line_num = :ac1_line_num";

      $resVlrPay = $this->ci->pedeo->queryTable($VlrPay, array('ac1_line_num' => $linenum, ':op' => $op));

    }else{

      $VlrPay = "SELECT COALESCE(".$pref."_paytoday,0) as paytoday,
                     ".$pref."_doctotal as doctotal,
                     ".$pref."_currency as currency,
                     get_localcur() as monedaloc,
                     get_tax_currency(".$pref."_currency, ".$pref."_docdate) as tasadoc
                     FROM ".$table."
                     WHERE ".$pref."_docentry = :docentry
                     AND ".$pref."_doctype = :doctype";

      $resVlrPay = $this->ci->pedeo->queryTable($VlrPay, array(
         ':docentry' => $CodDoc,
         ':doctype'  => $TypeDoc
      ));

    }

    if ( isset($resVlrPay[0]) ){

      if( $resVlrPay[0]['monedaloc'] == $paycurrency ) { // si la moneda del pago es igual a la moneda local

        $VlrAPagar = $value; // valor a pagar
       


      

        return $respuesta = array(
            'error'      => false,
            'data'       => '',
            'vlrdiff'    => 0,
            'vlrop'      => $VlrAPagar,
            'tasadoc'    => $resVlrPay[0]['tasadoc'],
            'equiv'      => 0,
            'mensaje'	   => ''
          );
          

      }else{ // si la moneda del documento es igual a la moneda local

        $VlrTotal = 0;
        $VlrAPagar = $value;

        $VlrDoc = 0;


        if ( $paycurrency ==  $resVlrPay[0]["monedaloc"] ){
          $VlrDoc = $VlrAPagar;
        }else{
          $VlrDoc = round(($VlrAPagar * $resVlrPay[0]['tasadoc']), $DECI_MALES); // VALOR A PAGAR CON LA TASA VIEJA
        }

        return $respuesta = array(
          'error'      => false,
          'data'       => '',
          'vlrdiff'    => $VlrDiff,
          'vlrop'      => $VlrDoc,
          'tasadoc'    => $resVlrPay[0]['tasadoc'],
          'equiv'      => $Equiv,
          'mensaje'	   => ''
        );

      }

    }else{

      return $respuesta = array(
        'error'   => true,
        'data'    => $resVlrPay,
        'mensaje'	=> 'No es posible realizar la operación');
    }

  }


  /**
   * 
   */
  public static function insertMG($params) {
    // 
    try {
      //code...
    } catch (\Exception $e) {
      //throw $th;
    }
  }

}


?>