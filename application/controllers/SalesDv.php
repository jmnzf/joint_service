<?php
// Devolución de VENTAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class SalesDv extends REST_Controller {

    private $pdo;

    public function __construct(){

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

  //CREAR NUEVA Devolución de clientes
    public function createSalesDv_post()
    {

      $Data = $this->post();

      if (!isset($Data['business']) OR
				!isset($Data['branch'])) {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'La informacion enviada no es valida'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
      $DECI_MALES =  $this->generic->getDecimals();
   
      $DetalleCuentaPuente = new stdClass();
      $DetalleCuentaInvetario = new stdClass();
      $DetalleConsolidadoCuentaPuente = [];
      $DetalleConsolidadoCuentaInventario = [];
      $inArrayCuentaPuente = array();
      $inArrayCuentaInvetario = array();
      $llaveCuentaPuente = "";
      $llaveCuentaInvetario = "";
      $posicionCuentaPuente = 0;
      $posicionCuentaInvetario = 0;
      $codigoCuenta = ""; //para saber la naturaleza
      $DocNumVerificado = 0;
      $ManejaInvetario = 0;
      $ManejaUbicacion = 0;
      $ManejaSerial = 0;
      $ManejaLote = 0;
      $AC1LINE = 1;
      $AgregarAsiento = true;
      $resInsertAsiento = "";
      $ResultadoInv = 0; // INDICA SI EXISTE AL MENOS UN ITEM QUE MANEJA INVENTARIO
      $CANTUOMSALE = 0; //CANTIDAD DE LA EQUIVALENCIA SEGUN LA UNIDAD DE MEDIDA DEL ITEM PARA VENTA



      // Se globaliza la variable sqlDetalleAsiento
      $sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
                            ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
                            ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
                            ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, business, branch)VALUES (:ac1_trans_id, :ac1_account,
                            :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
                            :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
                            :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
                            :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_line, :business, :branch)";



      if(!isset($Data['detail'])){

        $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $ContenidoDetalle = json_decode($Data['detail'], true);

      // SE VALIDA QUE EL DOCUMENTO SEA UN ARRAY
      if(!is_array($ContenidoDetalle)){
        $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'No se encontro el detalle de la Devolución de clientes'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }
      //

      // SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
      if(!intval(count($ContenidoDetalle)) > 0 ){
        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'Documento sin detalle'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }
      //

      //VALIDANDO PERIODO CONTABLE
      $periodo = $this->generic->ValidatePeriod($Data['vdv_duedev'], $Data['vdv_docdate'],$Data['vdv_duedate'],1);

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
      //BUSCANDO LA NUMERACION DEL DOCUMENTO
      $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

      $resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['vdv_series']));

      if(isset($resNumeracion[0])){

        $numeroActual = $resNumeracion[0]['pgs_nextnum'];
        $numeroFinal  = $resNumeracion[0]['pgs_last_num'];
        $numeroSiguiente = ($numeroActual + 1);

        if( $numeroSiguiente <= $numeroFinal ){

          $DocNumVerificado = $numeroSiguiente;

        } else {

          $respuesta = array(
              'error' => true,
              'data'  => array(),
              'mensaje' =>'La serie de la numeración esta llena'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

      }else{

          $respuesta = array(
              'error' => true,
              'data'  => array(),
              'mensaje' =>'No se encontro la serie de numeración para el documento'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
      }

      // PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
      // SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
      $sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
      $resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

      if(isset($resMonedaLoc[0])){

      }else{
        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'No se encontro la moneda local.'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

      // SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
      $sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
      $resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

      if(isset($resMonedaSys[0])){

      }else{

        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'No se encontro la moneda de sistema.'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

      //SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
      // Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
      $sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
      $resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $Data['vdv_currency'], ':tsa_date' => $Data['vdv_docdate']));

      if(isset($resBusTasa[0])){

      }else{

        if(trim($Data['vdv_currency']) != $MONEDALOCAL ){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$Data['vdv_currency'].' en la actual fecha del documento: '.$Data['vdv_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }
      }


      $sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
      $resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['vdv_docdate']));

      if(isset($resBusTasa2[0])){

      }else{
        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['vdv_docdate']
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
      $TasaLocSys = $resBusTasa2[0]['tsa_value'];

      // FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO


      //Obtener Carpeta Principal del Proyecto
      $sqlMainFolder = " SELECT * FROM params";
      $resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());

      if(!isset($resMainFolder[0])){
        $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'No se encontro la caperta principal del proyecto'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }
      // FIN PROCESO PARA OBTENER LA CARPETA PRINCIPAL DEL PROYECTO



      $sqlInsert = "INSERT INTO dvdv(vdv_series, vdv_docnum, vdv_docdate, vdv_duedate, vdv_duedev, vdv_pricelist, vdv_cardcode,
          vdv_cardname, vdv_currency, vdv_contacid, vdv_slpcode, vdv_empid, vdv_comment, vdv_doctotal, vdv_baseamnt, vdv_taxtotal,
          vdv_discprofit, vdv_discount, vdv_createat, vdv_baseentry, vdv_basetype, vdv_doctype, vdv_idadd, vdv_adress, vdv_paytype,
          vdv_createby,business,branch)VALUES(:vdv_series, :vdv_docnum, :vdv_docdate, :vdv_duedate, :vdv_duedev, :vdv_pricelist, :vdv_cardcode, :vdv_cardname,
          :vdv_currency, :vdv_contacid, :vdv_slpcode, :vdv_empid, :vdv_comment, :vdv_doctotal, :vdv_baseamnt, :vdv_taxtotal, :vdv_discprofit, :vdv_discount,
          :vdv_createat, :vdv_baseentry, :vdv_basetype, :vdv_doctype, :vdv_idadd, :vdv_adress, :vdv_paytype,:vdv_createby,:business,:branch)";


      // Se Inicia la transaccion,
      // Todas las consultas de modificacion siguientes
      // aplicaran solo despues que se confirme la transaccion,
      // de lo contrario no se aplicaran los cambios y se devolvera
      // la base de datos a su estado original.

      $this->pedeo->trans_begin();

      $resInsert = $this->pedeo->insertRow($sqlInsert, array(
        ':vdv_docnum' => $DocNumVerificado,
        ':vdv_series' => is_numeric($Data['vdv_series'])?$Data['vdv_series']:0,
        ':vdv_docdate' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
        ':vdv_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
        ':vdv_duedev' => $this->validateDate($Data['vdv_duedev'])?$Data['vdv_duedev']:NULL,
        ':vdv_pricelist' => is_numeric($Data['vdv_pricelist'])?$Data['vdv_pricelist']:0,
        ':vdv_cardcode' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
        ':vdv_cardname' => isset($Data['vdv_cardname'])?$Data['vdv_cardname']:NULL,
        ':vdv_currency' => isset($Data['vdv_currency'])?$Data['vdv_currency']:NULL,
        ':vdv_contacid' => isset($Data['vdv_contacid'])?$Data['vdv_contacid']:NULL,
        ':vdv_slpcode' => is_numeric($Data['vdv_slpcode'])?$Data['vdv_slpcode']:0,
        ':vdv_empid' => is_numeric($Data['vdv_empid'])?$Data['vdv_empid']:0,
        ':vdv_comment' => isset($Data['vdv_comment'])?$Data['vdv_comment']:NULL,
        ':vdv_doctotal' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
        ':vdv_baseamnt' => is_numeric($Data['vdv_baseamnt'])?$Data['vdv_baseamnt']:0,
        ':vdv_taxtotal' => is_numeric($Data['vdv_taxtotal'])?$Data['vdv_taxtotal']:0,
        ':vdv_discprofit' => is_numeric($Data['vdv_discprofit'])?$Data['vdv_discprofit']:0,
        ':vdv_discount' => is_numeric($Data['vdv_discount'])?$Data['vdv_discount']:0,
        ':vdv_createat' => $this->validateDate($Data['vdv_createat'])?$Data['vdv_createat']:NULL,
        ':vdv_baseentry' => is_numeric($Data['vdv_baseentry'])?$Data['vdv_baseentry']:0,
        ':vdv_basetype' => is_numeric($Data['vdv_basetype'])?$Data['vdv_basetype']:0,
        ':vdv_doctype' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
        ':vdv_idadd' => isset($Data['vdv_idadd'])?$Data['vdv_idadd']:NULL,
        ':vdv_adress' => isset($Data['vdv_adress'])?$Data['vdv_adress']:NULL,
        ':vdv_paytype' => is_numeric($Data['vdv_paytype'])?$Data['vdv_paytype']:0,
        ':vdv_createby' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
        ':business' => isset($Data['business'])?$Data['business']:NULL,
        ':branch' => isset($Data['branch'])?$Data['branch']:NULL
      ));

      if(is_numeric($resInsert) && $resInsert > 0){

        // Se actualiza la serie de la numeracion del documento

        $sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
                                                                                                                                                                                                                                        WHERE pgs_id = :pgs_id";
        $resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
            ':pgs_nextnum' => $DocNumVerificado,
            ':pgs_id'      => $Data['vdv_series']
        ));


        if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

        }else{
              $this->pedeo->trans_rollback();

              $respuesta = array(
                              'error'   => true,
                              'data'    => $resActualizarNumeracion,
                              'mensaje' => 'No se pudo crear la Devolución de clientes'
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
          ':bed_doctype' => $Data['vdv_doctype'],
          ':bed_status' => 1, //ESTADO ABIERTO
          ':bed_createby' => $Data['vdv_createby'],
          ':bed_date' => date('Y-m-d'),
          ':bed_baseentry' => NULL,
          ':bed_basetype' => NULL
        ));


        if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

        }else{

          $this->pedeo->trans_rollback();

              $respuesta = array(
                  'error'   => true,
                  'data'    => $resInsertEstado,
                  'mensaje' => 'No se pudo registrar la cotizacion de ventas'
              );


              $this->response($respuesta);

              return;
        }

        //FIN PROCESO ESTADO DEL DOCUMENTO


        //SE APLICA PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS
        if( isset($Data['vdv_baseentry']) && is_numeric($Data['vdv_baseentry']) && isset($Data['vdv_basetype']) && is_numeric($Data['vdv_basetype']) ){

              $sqlDocInicio = "SELECT bmd_tdi, bmd_ndi FROM tbmd WHERE  bmd_doctype = :bmd_doctype AND bmd_docentry = :bmd_docentry";
              $resDocInicio = $this->pedeo->queryTable($sqlDocInicio, array(
                              ':bmd_doctype' => $Data['vdv_basetype'],
                              ':bmd_docentry' => $Data['vdv_baseentry']
              ));


              if ( isset( $resDocInicio[0] ) ){

                  $sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
                                  bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
                                  VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
                                  :bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

                  $resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

                                  ':bmd_doctype' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
                                  ':bmd_docentry' => $resInsert,
                                  ':bmd_createat' => $this->validateDate($Data['vdv_createat'])?$Data['vdv_createat']:NULL,
                                  ':bmd_doctypeo' => is_numeric($Data['vdv_basetype'])?$Data['vdv_basetype']:0, //ORIGEN
                                  ':bmd_docentryo' => is_numeric($Data['vdv_baseentry'])?$Data['vdv_baseentry']:0,  //ORIGEN
                                  ':bmd_tdi' => $resDocInicio[0]['bmd_tdi'], // DOCUMENTO INICIAL
                                  ':bmd_ndi' => $resDocInicio[0]['bmd_ndi'], // DOCUMENTO INICIAL
                                  ':bmd_docnum' => $DocNumVerificado,
                                  ':bmd_doctotal' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
                                  ':bmd_cardcode' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
                                  ':bmd_cardtype' => 1
                  ));

                  if( is_numeric($resInsertMD) && $resInsertMD > 0 ){

                  }else{

                      $this->pedeo->trans_rollback();

                      $respuesta = array(
                                      'error'   => true,
                                      'data'    => $resInsertEstado,
                                      'mensaje' => 'No se pudo registrar el movimiento del documento'
                      );


                      $this->response($respuesta);

                      return;
                  }

              }else{

                  $sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
                                  bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
                                  VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
                                  :bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

                  $resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

                                  ':bmd_doctype' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
                                  ':bmd_docentry' => $resInsert,
                                  ':bmd_createat' => $this->validateDate($Data['vdv_createat'])?$Data['vdv_createat']:NULL,
                                  ':bmd_doctypeo' => is_numeric($Data['vdv_basetype'])?$Data['vdv_basetype']:0, //ORIGEN
                                  ':bmd_docentryo' => is_numeric($Data['vdv_baseentry'])?$Data['vdv_baseentry']:0,  //ORIGEN
                                  ':bmd_tdi' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0, // DOCUMENTO INICIAL
                                  ':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
                                  ':bmd_docnum' => $DocNumVerificado,
                                  ':bmd_doctotal' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
                                  ':bmd_cardcode' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
                                  ':bmd_cardtype' => 1
                  ));

                  if( is_numeric($resInsertMD) && $resInsertMD > 0 ){

                  }else{

                      $this->pedeo->trans_rollback();

                      $respuesta = array(
                          'error'   => true,
                          'data'    => $resInsertEstado,
                          'mensaje' => 'No se pudo registrar el movimiento del documento'
                      );


                      $this->response($respuesta);

                      return;
                  }
              }

        }else{

          $sqlInsertMD = "INSERT INTO tbmd(bmd_doctype, bmd_docentry, bmd_createat, bmd_doctypeo,
                          bmd_docentryo, bmd_tdi, bmd_ndi, bmd_docnum, bmd_doctotal, bmd_cardcode, bmd_cardtype)
                          VALUES (:bmd_doctype, :bmd_docentry, :bmd_createat, :bmd_doctypeo,
                          :bmd_docentryo, :bmd_tdi, :bmd_ndi, :bmd_docnum, :bmd_doctotal, :bmd_cardcode, :bmd_cardtype)";

          $resInsertMD = $this->pedeo->insertRow($sqlInsertMD, array(

                          ':bmd_doctype' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
                          ':bmd_docentry' => $resInsert,
                          ':bmd_createat' => $this->validateDate($Data['vdv_createat'])?$Data['vdv_createat']:NULL,
                          ':bmd_doctypeo' => is_numeric($Data['vdv_basetype'])?$Data['vdv_basetype']:0, //ORIGEN
                          ':bmd_docentryo' => is_numeric($Data['vdv_baseentry'])?$Data['vdv_baseentry']:0,  //ORIGEN
                          ':bmd_tdi' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0, // DOCUMENTO INICIAL
                          ':bmd_ndi' => $resInsert, // DOCUMENTO INICIAL
                          ':bmd_docnum' => $DocNumVerificado,
                          ':bmd_doctotal' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
                          ':bmd_cardcode' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
                          ':bmd_cardtype' => 1
          ));

          if( is_numeric($resInsertMD) && $resInsertMD > 0 ){

          }else{

              $this->pedeo->trans_rollback();

              $respuesta = array(
                  'error'   => true,
                  'data'    => $resInsertEstado,
                  'mensaje' => 'No se pudo registrar el movimiento del documento'
              );


              $this->response($respuesta);

              return;
          }
        }
        //FIN PROCEDIMIENTO MOVIMIENTO DE DOCUMENTOS



        foreach ($ContenidoDetalle as $key => $detail) {

          $CANTUOMSALE = $this->generic->getUomSale( $detail['dv1_itemcode'] );

          if( $CANTUOMSALE == 0 ){

            $this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data' 		=> $detail['dv1_itemcode'],
              'mensaje'	=> 'No se encontro la equivalencia de la unidad de medida para el item: '.$detail['dv1_itemcode']
            );

             $this->response($respuesta);

             return;
          }

          $sqlInsertDetail = "INSERT INTO vdv1(dv1_docentry, dv1_itemcode, dv1_itemname, dv1_quantity, dv1_uom, dv1_whscode,
                              dv1_price, dv1_vat, dv1_vatsum, dv1_discount, dv1_linetotal, dv1_costcode, dv1_ubusiness, dv1_project,
                              dv1_acctcode, dv1_basetype, dv1_doctype, dv1_avprice, dv1_inventory, dv1_linenum, dv1_acciva, dv1_codimp, dv1_ubication, dv1_lote)VALUES(:dv1_docentry, :dv1_itemcode, :dv1_itemname, :dv1_quantity,
                              :dv1_uom, :dv1_whscode,:dv1_price, :dv1_vat, :dv1_vatsum, :dv1_discount, :dv1_linetotal, :dv1_costcode, :dv1_ubusiness, :dv1_project,
                              :dv1_acctcode, :dv1_basetype, :dv1_doctype, :dv1_avprice, :dv1_inventory, :dv1_linenum, :dv1_acciva, :dv1_codimp, :dv1_ubication, :dv1_lote)";

          $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail,array(
            ':dv1_docentry' => $resInsert,
            ':dv1_itemcode' => isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL,
            ':dv1_itemname' => isset($detail['dv1_itemname'])?$detail['dv1_itemname']:NULL,
            ':dv1_quantity' => is_numeric($detail['dv1_quantity']) ? $detail['dv1_quantity'] : 0,
            ':dv1_uom' => isset($detail['dv1_uom'])?$detail['dv1_uom']:NULL,
            ':dv1_whscode' => isset($detail['dv1_whscode'])?$detail['dv1_whscode']:NULL,
            ':dv1_price' => is_numeric($detail['dv1_price'])?$detail['dv1_price']:0,
            ':dv1_vat' => is_numeric($detail['dv1_vat'])?$detail['dv1_vat']:0,
            ':dv1_vatsum' => is_numeric($detail['dv1_vatsum'])?$detail['dv1_vatsum']:0,
            ':dv1_discount' => is_numeric($detail['dv1_discount'])?$detail['dv1_discount']:0,
            ':dv1_linetotal' => is_numeric($detail['dv1_linetotal'])?$detail['dv1_linetotal']:0,
            ':dv1_costcode' => isset($detail['dv1_costcode'])?$detail['dv1_costcode']:NULL,
            ':dv1_ubusiness' => isset($detail['dv1_ubusiness'])?$detail['dv1_ubusiness']:NULL,
            ':dv1_project' => isset($detail['dv1_project'])?$detail['dv1_project']:NULL,
            ':dv1_acctcode' => is_numeric($detail['dv1_acctcode'])?$detail['dv1_acctcode']:0,
            ':dv1_basetype' => is_numeric($detail['dv1_basetype'])?$detail['dv1_basetype']:0,
            ':dv1_doctype' => is_numeric($detail['dv1_doctype'])?$detail['dv1_doctype']:0,
            ':dv1_avprice' => is_numeric($detail['dv1_avprice'])?$detail['dv1_avprice']:0,
            ':dv1_inventory' => is_numeric($detail['dv1_inventory'])?$detail['dv1_inventory']:NULL,
            ':dv1_linenum' => is_numeric($detail['dv1_linenum'])?$detail['dv1_linenum']:0,
            ':dv1_acciva' => is_numeric($detail['dv1_acciva'])?$detail['dv1_acciva']:0,
            ':dv1_codimp' => isset($detail['dv1_codimp'])?$detail['dv1_codimp']:NULL,
            ':dv1_ubication' => isset($detail['dv1_ubication'])?$detail['dv1_ubication']:NULL,
            ':dv1_lote' => isset($detail['ote_code'])?$detail['ote_code']:NULL
          ));

          if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
            // Se verifica que el detalle no de error insertando //

            //VALIDACION DE CANTIDAD A DEVOLVER NO SEA MAYOR A LA ENTREGADA
            if($Data['vdv_basetype'] == 3 ){
                $sqlValidationQty = "SELECT coalesce(t1.dv1_quantity,0) qty_dev,coalesce(t3.em1_quantity,0) qty_entr,
                                        case
                                            when COALESCE(t1.dv1_quantity,0) > COALESCE(t3.em1_quantity,0)
                                                then 1
                                            else 0
                                        end estado
                                    FROM dvdv t0
                                    left join vdv1 t1 on t0.vdv_docentry = t1.dv1_docentry
                                    left join dvem t2 on t0.vdv_baseentry = t2.vem_docentry
                                    left join vem1 t3 on t2.vem_docentry = t3.em1_docentry and t1.dv1_itemcode = t3.em1_itemcode
                                    where t0.vdv_docentry = :vdv_docentry ";
                $resSqlValidationQty = $this->pedeo->queryTable($sqlValidationQty,array(':vdv_docentry' => $resInsert));

                if(is_numeric($resSqlValidationQty[0]['estado'])  &&  $resSqlValidationQty[0]['estado'] == 0){

                }else{
                  $this->pedeo->trans_rollback();

                  $respuesta = array(
                                  'error'   => true,
                                  'data'    => $resSqlValidationQty,
                                  'mensaje' => 'La cantidad a devolver no puede ser mayor a la entregada'
                  );
                  $this->response($respuesta);

                  return;
                }
            }

          }else{

            // si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
            // se retorna el error y se detiene la ejecucion del codigo restante.
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $resInsertDetail,
                'mensaje' => 'No se pudo registrar la Devolución de clientes'
            );

            $this->response($respuesta);

            return;
          }


          // SE VERIFICA SI EL ARTICULO ESTA MARCADO PARA MANEJARSE EN INVENTARIO
          $sqlItemINV = "SELECT dma_item_inv FROM dmar WHERE dma_item_code = :dma_item_code AND dma_item_inv = :dma_item_inv";
          $resItemINV = $this->pedeo->queryTable($sqlItemINV, array(

              ':dma_item_code' => $detail['dv1_itemcode'],
              ':dma_item_inv'  => 1
          ));

          if(isset($resItemINV[0])){

            // CONSULTA PARA VERIFICAR SI EL ALMACEN MANEJA UBICACION

						$sqlubicacion = "SELECT * FROM dmws WHERE dws_ubication = :dws_ubication AND dws_code = :dws_code AND business = :business";
						$resubicacion = $this->pedeo->queryTable($sqlubicacion, array(
							':dws_ubication' => 1,
							':dws_code' => $detail['dv1_whscode'],
						  ':business' => $Data['business']
						));


						if ( isset($resubicacion[0]) ){
							$ManejaUbicacion = 1;
						}else{
							$ManejaUbicacion = 0;
						}

            // SE VERIFICA SI EL ARTICULO MANEJA LOTE

            $sqlLote = "SELECT dma_lotes_code FROM dmar WHERE dma_item_code = :dma_item_code AND dma_lotes_code = :dma_lotes_code";
            $resLote = $this->pedeo->queryTable($sqlLote, array(

              ':dma_item_code' => $detail['dv1_itemcode'],
              ':dma_lotes_code'  => 1
            ));

            if (isset($resLote[0])) {
              $ManejaLote = 1;
            } else {
              $ManejaLote = 0;
            }

            $ManejaInvetario = 1;
            $ResultadoInv  = 1;

          }else{

            $ManejaInvetario = 0;
          }

          // FIN PROCESO ITEM MANEJA INVENTARIO


          // si el item es inventariable
          if( $ManejaInvetario == 1 ){

            //SE VERIFICA SI EL ARTICULO MANEJA SERIAL
            $sqlItemSerial = "SELECT dma_series_code FROM dmar WHERE  dma_item_code = :dma_item_code AND dma_series_code = :dma_series_code";
            $resItemSerial = $this->pedeo->queryTable($sqlItemSerial, array(

                ':dma_item_code' => $detail['dv1_itemcode'],
                ':dma_series_code'  => 1
            ));

            if(isset($resItemSerial[0])){
              $ManejaSerial = 1;

              $AddSerial = $this->generic->addSerial( $detail['serials'], $detail['dv1_itemcode'], $Data['vdv_doctype'], $resInsert, $DocNumVerificado, $Data['vdv_docdate'], 1, $Data['vdv_comment'], $detail['dv1_whscode'], $detail['dv1_quantity'], $Data['vdv_createby'] );

              if( isset($AddSerial['error']) && $AddSerial['error'] == false){

              }else{
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

            if ( $AgregarAsiento ){

                //Se agregan los asientos contables
                // SI EXISTE AL MENOS UN ITEM INVENTARIABLE

                $sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch)
                                    VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch)";


                $resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(

                  ':mac_doc_num' => 1,
                  ':mac_status' => 1,
                  ':mac_base_type' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
                  ':mac_base_entry' => $resInsert,
                  ':mac_doc_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
                  ':mac_doc_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
                  ':mac_legal_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
                  ':mac_ref1' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
                  ':mac_ref2' => "",
                  ':mac_ref3' => "",
                  ':mac_loc_total' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
                  ':mac_fc_total' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
                  ':mac_sys_total' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
                  ':mac_trans_dode' => 1,
                  ':mac_beline_nume' => 1,
                  ':mac_vat_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
                  ':mac_serie' => 1,
                  ':mac_number' => 1,
                  ':mac_bammntsys' => is_numeric($Data['vdv_baseamnt'])?$Data['vdv_baseamnt']:0,
                  ':mac_bammnt' => is_numeric($Data['vdv_baseamnt'])?$Data['vdv_baseamnt']:0,
                  ':mac_wtsum' => 1,
                  ':mac_vatsum' => is_numeric($Data['vdv_taxtotal'])?$Data['vdv_taxtotal']:0,
                  ':mac_comments' => isset($Data['vdv_comment'])?$Data['vdv_comment']:NULL,
                  ':mac_create_date' => $this->validateDate($Data['vdv_createat'])?$Data['vdv_createat']:NULL,
                  ':mac_made_usuer' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
                  ':mac_update_date' => date("Y-m-d"),
                  ':mac_update_user' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
                  ':business'	  => $Data['business'],
					        ':branch' 	  => $Data['branch']
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

            }

            //se busca el costo del item en el momento de la creacion del documento de venta
            // para almacenar en el movimiento de inventario

            // SI EL ALMACEN MANEJA UBICACION

						$sqlCostoMomentoRegistro = "";
						$resCostoMomentoRegistro = [];
            if ( $ManejaUbicacion == 1 ) {
              if( $ManejaLote == 1 ){
                $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND bdi_lote = :bdi_lote AND business = :business";
                $resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['dv1_whscode'], ':bdi_itemcode' => $detail['dv1_itemcode'], ':bdi_ubication' => $detail['dv1_ubication'],':bdi_lote' => $detail['ote_code'], ':business' => $Data['business']));
              }else{
                $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND bdi_ubication = :bdi_ubication AND business = :business";
                $resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['dv1_whscode'], ':bdi_itemcode' => $detail['dv1_itemcode'], ':bdi_ubication' => $detail['dv1_ubication'], ':business' => $Data['business']));
              }
              
            }else{
              if( $ManejaLote == 1 ){
                $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode  AND bdi_lote = :bdi_lote AND business = :business";
                $resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['dv1_whscode'], ':bdi_itemcode' => $detail['dv1_itemcode'], ':bdi_lote' => $detail['ote_code'], ':business' => $Data['business']));
              }else{
                $sqlCostoMomentoRegistro = "SELECT * FROM tbdi WHERE bdi_whscode = :bdi_whscode  AND bdi_itemcode = :bdi_itemcode AND business = :business";
                $resCostoMomentoRegistro = $this->pedeo->queryTable($sqlCostoMomentoRegistro, array(':bdi_whscode' => $detail['dv1_whscode'], ':bdi_itemcode' => $detail['dv1_itemcode'], ':business' => $Data['business']));
              }
              
            }
           


            if(isset($resCostoMomentoRegistro[0])){

              //Se aplica el movimiento de inventario
              $sqlInserMovimiento = "INSERT INTO tbmi(bmi_itemcode,bmi_quantity,bmi_whscode,bmi_createat,bmi_createby,bmy_doctype,bmy_baseentry,bmi_cost,bmi_currequantity,bmi_basenum,bmi_docdate,bmi_duedate,bmi_duedev,bmi_comment,bmi_ubication, bmi_lote)
                                    VALUES (:bmi_itemcode,:bmi_quantity, :bmi_whscode,:bmi_createat,:bmi_createby,:bmy_doctype,:bmy_baseentry,:bmi_cost,:bmi_currequantity,:bmi_basenum,:bmi_docdate,:bmi_duedate,:bmi_duedev,:bmi_comment,:bmi_ubication, :bmi_lote)";

              $sqlInserMovimiento = $this->pedeo->insertRow($sqlInserMovimiento, array(

              ':bmi_itemcode'  => isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL,
              ':bmi_quantity'  => is_numeric($detail['dv1_quantity']) ? ( ( $detail['dv1_quantity'] * $CANTUOMSALE ) * $Data['invtype'] ) : 0,
              ':bmi_whscode'   => isset($detail['dv1_whscode'])?$detail['dv1_whscode']:NULL,
              ':bmi_createat'  => $this->validateDate($Data['vdv_createat'])?$Data['vdv_createat']:NULL,
              ':bmi_createby'  => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
              ':bmy_doctype'   => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
              ':bmy_baseentry' => $resInsert,
              ':bmi_cost'      => $resCostoMomentoRegistro[0]['bdi_avgprice'],
              ':bmi_currequantity' => $resCostoMomentoRegistro[0]['bdi_quantity'],
              ':bmi_basenum' => $DocNumVerificado,
              ':bmi_docdate' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
              ':bmi_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
              ':bmi_duedev'  => $this->validateDate($Data['vdv_duedev'])?$Data['vdv_duedev']:NULL,
              ':bmi_comment' => isset($Data['vdv_comment'])?$Data['vdv_comment']:NULL,
              ':bmi_ubication' => isset($detail['dv1_ubication']) ? $detail['dv1_ubication'] : NULL,
              ':bmi_lote' => isset($detail['ote_code']) ? $detail['ote_code'] : NULL


              ));

              if(is_numeric($sqlInserMovimiento) && $sqlInserMovimiento > 0){
              // Se verifica que el detalle no de error insertando //
              }else{

              // si falla algun insert del detalle de la devolucion de ventas se devuelven los cambios realizados por la transaccion,
              // se retorna el error y se detiene la ejecucion del codigo restante.
                  $this->pedeo->trans_rollback();

                  $respuesta = array(
                      'error'   => true,
                      'data'    => $sqlInserMovimiento,
                      'mensaje' => 'No se pudo registrar la devolucion de ventas'
                  );

                  $this->response($respuesta);

                  return;
              }



            }else{

              $this->pedeo->trans_rollback();

              $respuesta = array(
                'error'    => true,
                'data'     => $resCostoMomentoRegistro,
                'mensaje'  => 'No se pudo registrar la devolucion de ventas, no se encontro el costo del articulo'
              );

              $this->response($respuesta);

              return;
            }

            //FIN aplicacion de movimiento de inventario


            //Se Aplica el movimiento en stock ***************
            // Buscando item en el stock

            $sqlCostoCantidad = "";
					  $resCostoCantidad = [];

            // SI EL ALMACEN MANEJA UBICACION

            if ( $ManejaUbicacion == 1 ){

              if ( $ManejaLote == 1 ) {
                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                FROM tbdi
                WHERE bdi_itemcode = :bdi_itemcode
                AND bdi_whscode = :bdi_whscode
                AND bdi_ubication = :bdi_ubication
                AND bdi_lote = :bdi_lote
                AND business = :business";


                $resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

                ':bdi_itemcode'   => $detail['dv1_itemcode'],
                ':bdi_whscode'    => $detail['dv1_whscode'],
                ':bdi_ubication'  => $detail['dv1_ubication'],
                ':bdi_lote'       => $detail['ote_code'],
                ':business'       => $Data['business']
                ));

              }else{

                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                FROM tbdi
                WHERE bdi_itemcode = :bdi_itemcode
                AND bdi_whscode = :bdi_whscode
                AND bdi_ubication = :bdi_ubication
                AND business = :business";

                $resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

                ':bdi_itemcode'   => $detail['dv1_itemcode'],
                ':bdi_whscode'    => $detail['dv1_whscode'],
                ':bdi_ubication'  => $detail['dv1_ubication'],
                ':business'       => $Data['business']
                ));

              }


            }else{
              if ( $ManejaLote == 1 ) {
                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                FROM tbdi
                WHERE bdi_itemcode = :bdi_itemcode
                AND bdi_whscode = :bdi_whscode
                AND bdi_lote = :bdi_lote
                AND business = :business";


                $resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

                ':bdi_itemcode' => $detail['dv1_itemcode'],
                ':bdi_whscode'  => $detail['dv1_whscode'],
                ':bdi_lote'     => $detail['ote_code'],
                ':business'     => $Data['business']
                ));
              }else{
                $sqlCostoCantidad = "SELECT bdi_id, bdi_itemcode, bdi_whscode, bdi_quantity, bdi_avgprice
                FROM tbdi
                WHERE bdi_itemcode = :bdi_itemcode
                AND bdi_whscode = :bdi_whscode
                AND business = :business";


                $resCostoCantidad = $this->pedeo->queryTable($sqlCostoCantidad, array(

                ':bdi_itemcode' => $detail['dv1_itemcode'],
                ':bdi_whscode'  => $detail['dv1_whscode'],
                ':business'     => $Data['business']
                ));
              }

            }
          

            if(isset($resCostoCantidad[0])){

 

                $CantidadActual = $resCostoCantidad[0]['bdi_quantity'];
                $CantidadNueva = ( $detail['dv1_quantity'] * $CANTUOMSALE );


                $CantidadTotal = ($CantidadActual + $CantidadNueva);

                $sqlUpdateCostoCantidad =  "UPDATE tbdi
                                            SET bdi_quantity = :bdi_quantity
                                            WHERE  bdi_id = :bdi_id";

                $resUpdateCostoCantidad = $this->pedeo->updateRow($sqlUpdateCostoCantidad, array(

                    ':bdi_quantity' => $CantidadTotal,
                    ':bdi_id'       => $resCostoCantidad[0]['bdi_id']
                ));

                if(is_numeric($resUpdateCostoCantidad) && $resUpdateCostoCantidad == 1){

                }else{

                  $this->pedeo->trans_rollback();

                  $respuesta = array(
                      'error'   => true,
                      'data'    => $resUpdateCostoCantidad,
                      'mensaje' => 'No se pudo crear la devolucion de ventas'
                  );

                  return;
                }


            }else{

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'    => $resCostoCantidad,
                    'mensaje' => 'El item no existe en el stock '.$detail['dv1_itemcode']
                );

                $this->response($respuesta);

                return;
            }

            //FIN de  Aplicacion del movimiento en stock

          }

          //LLENANDO DETALLE ASIENTO CONTABLES
          $DetalleCuentaPuente = new stdClass();
          $DetalleCuentaInvetario = new stdClass();

          // ITEM INVENTARIABLE
          if( $ManejaInvetario == 1 ){

            $DetalleCuentaPuente->dv1_account = is_numeric($detail['dv1_acctcode'])?$detail['dv1_acctcode']:0;
            $DetalleCuentaPuente->dv1_prc_code = isset($detail['dv1_costcode'])?$detail['dv1_costcode']:NULL;
            $DetalleCuentaPuente->dv1_uncode = isset($detail['dv1_ubusiness'])?$detail['dv1_ubusiness']:NULL;
            $DetalleCuentaPuente->dv1_prj_code = isset($detail['dv1_project'])?$detail['dv1_project']:NULL;
            $DetalleCuentaPuente->dv1_linetotal = is_numeric($detail['dv1_linetotal'])?$detail['dv1_linetotal']:0;
            $DetalleCuentaPuente->dv1_vat = is_numeric($detail['dv1_vat'])?$detail['dv1_vat']:0;
            $DetalleCuentaPuente->dv1_vatsum = is_numeric($detail['dv1_vatsum'])?$detail['dv1_vatsum']:0;
            $DetalleCuentaPuente->dv1_price = is_numeric($detail['dv1_price'])?$detail['dv1_price']:0;
            $DetalleCuentaPuente->dv1_itemcode = isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL;
            $DetalleCuentaPuente->dv1_quantity = is_numeric($detail['dv1_quantity']) ? ( $detail['dv1_quantity'] * $CANTUOMSALE ) : 0;
            $DetalleCuentaPuente->dv1_whscode = isset($detail['dv1_whscode'])?$detail['dv1_whscode']:NULL;


            $DetalleCuentaInvetario->dv1_account = is_numeric($detail['dv1_acctcode'])?$detail['dv1_acctcode']:0;
            $DetalleCuentaInvetario->dv1_prc_code = isset($detail['dv1_costcode'])?$detail['dv1_costcode']:NULL;
            $DetalleCuentaInvetario->dv1_uncode = isset($detail['dv1_ubusiness'])?$detail['dv1_ubusiness']:NULL;
            $DetalleCuentaInvetario->dv1_prj_code = isset($detail['dv1_project'])?$detail['dv1_project']:NULL;
            $DetalleCuentaInvetario->dv1_linetotal = is_numeric($detail['dv1_linetotal'])?$detail['dv1_linetotal']:0;
            $DetalleCuentaInvetario->dv1_vat = is_numeric($detail['dv1_vat'])?$detail['dv1_vat']:0;
            $DetalleCuentaInvetario->dv1_vatsum = is_numeric($detail['dv1_vatsum'])?$detail['dv1_vatsum']:0;
            $DetalleCuentaInvetario->dv1_price = is_numeric($detail['dv1_price'])?$detail['dv1_price']:0;
            $DetalleCuentaInvetario->dv1_itemcode = isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL;
            $DetalleCuentaInvetario->dv1_quantity = is_numeric($detail['dv1_quantity']) ? ( $detail['dv1_quantity'] * $CANTUOMSALE ) : 0;
            $DetalleCuentaInvetario->dv1_whscode = isset($detail['dv1_whscode'])?$detail['dv1_whscode']:NULL;



            $llaveCuentaPuente = $DetalleCuentaPuente->dv1_uncode.$DetalleCuentaPuente->dv1_prc_code.$DetalleCuentaPuente->dv1_prj_code.$DetalleCuentaPuente->dv1_account;
            $llaveCuentaInvetario = $DetalleCuentaInvetario->dv1_uncode.$DetalleCuentaInvetario->dv1_prc_code.$DetalleCuentaInvetario->dv1_prj_code.$DetalleCuentaInvetario->dv1_account;


            if(in_array( $llaveCuentaPuente, $inArrayCuentaPuente )){

              $posicionCuentaPuente = $this->buscarPosicion( $llaveCuentaPuente, $inArrayCuentaPuente );

            }else{

              array_push( $inArrayCuentaPuente, $llaveCuentaPuente );
              $posicionCuentaPuente = $this->buscarPosicion( $llaveCuentaPuente, $inArrayCuentaPuente );

            }


            if(in_array( $llaveCuentaInvetario, $inArrayCuentaInvetario )){

              $posicionCuentaInvetario = $this->buscarPosicion( $llaveCuentaInvetario, $inArrayCuentaInvetario );

            }else{

              array_push( $inArrayCuentaInvetario, $llaveCuentaInvetario );
              $posicionCuentaInvetario = $this->buscarPosicion( $llaveCuentaInvetario, $inArrayCuentaInvetario );

            }

            if( isset($DetalleConsolidadoCuentaPuente[$posicionCuentaPuente])){

              if(!is_array($DetalleConsolidadoCuentaPuente[$posicionCuentaPuente])){
                $DetalleConsolidadoCuentaPuente[$posicionCuentaPuente] = array();
              }

            }else{
                $DetalleConsolidadoCuentaPuente[$posicionCuentaPuente] = array();
            }

            array_push( $DetalleConsolidadoCuentaPuente[$posicionCuentaPuente], $DetalleCuentaPuente );


            if( isset($DetalleConsolidadoCuentaInventario[$posicionCuentaInvetario])){

              if(!is_array($DetalleConsolidadoCuentaInventario[$posicionCuentaInvetario])){
                $DetalleConsolidadoCuentaInventario[$posicionCuentaInvetario] = array();
              }

            }else{
              $DetalleConsolidadoCuentaInventario[$posicionCuentaInvetario] = array();
            }

            array_push( $DetalleConsolidadoCuentaInventario[$posicionCuentaInvetario], $DetalleCuentaInvetario );
          } // ITEM INVENTARIABLE


      }


      // PROCEDIMEINTO PARA LLENAR LA CUENTA PUENTE INVENTARIO
      foreach ($DetalleConsolidadoCuentaInventario as $key => $posicion) {
                $grantotalCuentaPuente = 0 ;
                $grantotalCuentaPuenteOriginal = 0;
                $cuentaPuente = "";
                $dbito = 0;
                $cdito = 0;
                $MontoSysDB = 0;
                $MontoSysCR = 0;
                $centroCosto = '';
                $unidadNegocio = '';
                $codigoProyecto = '';
                foreach ($posicion as $key => $value) {

                  $sqlArticulo = "SELECT pge_bridge_inv FROM pgem WHERE pge_id = :business"; // Cuenta  puente inventario
                  $resArticulo = $this->pedeo->queryTable($sqlArticulo, array( ':business' => $Data['business'] ));// Cuenta costo puente

                  $centroCosto = $value->dv1_prc_code;
                  $unidadNegocio = $value->dv1_uncode;
                  $codigoProyecto = $value->dv1_prj_code;

                  if(isset($resArticulo[0])){

                      $dbito = 0;
                      $cdito = 0;
                      $MontoSysDB = 0;
                      $MontoSysCR = 0;

                      $cuentaPuente  = $resArticulo[0]['pge_bridge_inv'];

                      // BUSCANDO COSTO CON EL QUE SE REALIZO LA ENTREGA
                      $sqlcostoentrega = "SELECT bmi_cost,bmy_baseentry,bmy_doctype
                                          FROM tbmi
                                          WHERE bmy_doctype = :bmy_doctype
                                          AND bmy_baseentry = :bmy_baseentry
                                          AND bmi_itemcode  = :bmi_itemcode
                                          AND bmi_whscode   = :bmi_whscode";

                      $rescostoentrega = $this->pedeo->queryTable($sqlcostoentrega, array(

                                                      ':bmy_doctype'   => $Data['vdv_basetype'],
                                                      ':bmy_baseentry' => $Data['vdv_baseentry'],
                                                      ':bmi_itemcode'  => $value->dv1_itemcode,
                                                      ':bmi_whscode'   => $value->dv1_whscode
                      ));

                      if(!isset($rescostoentrega[0])){

                        $this->pedeo->trans_rollback();

                        $respuesta = array(
                          'error'   => true,
                          'data'    => $rescostoentrega,
                          'mensaje' => 'No se encontro el costo del item en el documento de origen'
                        );

                        $this->response($respuesta);

                        return;
                      }
                      // FIN BUSQUEDA DEL COSTO ARTICULO

                      $costoArticulo = $rescostoentrega[0]['bmi_cost'];
                      $cantidadArticulo = $value->dv1_quantity;
                      $grantotalCuentaPuente = ($grantotalCuentaPuente + ($costoArticulo * $cantidadArticulo));

                    }else{
                      // si falla algun insert del detalle de la devolucion de ventas se devuelven los cambios realizados por la transaccion,
                      // se retorna el error y se detiene la ejecucion del codigo restante.
                      $this->pedeo->trans_rollback();

                      $respuesta = array(
                        'error'   => true,
                        'data'    => $resArticulo,
                        'mensaje' => 'No se encontro la cuenta puente para inventario'
                      );

                      $this->response($respuesta);

                      return;
                    }

                }

                $codigo3 = substr($cuentaPuente, 0, 1);

                $grantotalCuentaPuenteOriginal = $grantotalCuentaPuente;

                if(trim($Data['vdv_currency']) != $MONEDALOCAL ){
                  $grantotalCuentaPuente = ($grantotalCuentaPuente * $TasaDocLoc);
                }

                if( $codigo3 == 1 || $codigo3 == "1" ){
                  $cdito = $grantotalCuentaPuente;
                  if(trim($Data['vdv_currency']) != $MONEDASYS ){
                    $MontoSysCR = ($cdito / $TasaLocSys);
                  }else{
                    $MontoSysCR = $grantotalCuentaPuenteOriginal;
                  }
                }else if( $codigo3 == 2 || $codigo3 == "2" ){
                  $cdito = $grantotalCuentaPuente;
                  if(trim($Data['vdv_currency']) != $MONEDASYS ){
                    $MontoSysCR = ($cdito / $TasaLocSys);
                  }else{
                    $MontoSysCR = $grantotalCuentaPuenteOriginal;
                  }
                }else if( $codigo3 == 3 || $codigo3 == "3" ){
                  $cdito =  $grantotalCuentaPuente;
                  if(trim($Data['vdv_currency']) != $MONEDASYS ){
                    $MontoSysCR = ($cdito / $TasaLocSys);
                  }else{
                    $MontoSysCR = $grantotalCuentaPuenteOriginal;
                  }
                }else if( $codigo3 == 4 || $codigo3 == "4" ){
                  $cdito =  $grantotalCuentaPuente;
                  if(trim($Data['vdv_currency']) != $MONEDASYS ){
                    $MontoSysCR = ($cdito / $TasaLocSys);
                  }else{
                    $MontoSysCR = $grantotalCuentaPuenteOriginal;
                  }
                }else if( $codigo3 == 5  || $codigo3 == "5" ){
                  $cdito = $grantotalCuentaPuente;
                  if(trim($Data['vdv_currency']) != $MONEDASYS ){
                    $MontoSysCR = ($cdito / $TasaLocSys);
                  }else{
                    $MontoSysCR = $grantotalCuentaPuenteOriginal;
                  }
                }else if( $codigo3 == 6 || $codigo3 == "6" ){
                  $cdito = $grantotalCuentaPuente;
                  if(trim($Data['vdv_currency']) != $MONEDASYS ){
                    $MontoSysCR = ($cdito / $TasaLocSys);
                  }else{
                    $MontoSysCR = $grantotalCuentaPuenteOriginal;
                  }
                }else if( $codigo3 == 7 || $codigo3 == "7" ){
                  $cdito = $grantotalCuentaPuente;
                  if(trim($Data['vdv_currency']) != $MONEDASYS ){
                    $MontoSysCR = ($cdito / $TasaLocSys);
                  }else{
                    $MontoSysCR = $grantotalCuentaPuenteOriginal;
                  }
                }

                $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

                ':ac1_trans_id' => $resInsertAsiento,
                ':ac1_account' => $cuentaPuente,
                ':ac1_debit' => round($dbito, $DECI_MALES),
                ':ac1_credit' => round($cdito, $DECI_MALES),
                ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
                ':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
                ':ac1_currex' => 0,
                ':ac1_doc_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
                ':ac1_doc_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
                ':ac1_debit_import' => 0,
                ':ac1_credit_import' => 0,
                ':ac1_debit_importsys' => 0,
                ':ac1_credit_importsys' => 0,
                ':ac1_font_key' => $resInsert,
                ':ac1_font_line' => 1,
                ':ac1_font_type' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
                ':ac1_accountvs' => 1,
                ':ac1_doctype' => 18,
                ':ac1_ref1' => "",
                ':ac1_ref2' => "",
                ':ac1_ref3' => "",
                ':ac1_prc_code' => $centroCosto,
                ':ac1_uncode' => $unidadNegocio,
                ':ac1_prj_code' => $codigoProyecto,
                ':ac1_rescon_date' => NULL,
                ':ac1_recon_total' => 0,
                ':ac1_made_user' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
                ':ac1_accperiod' => 1,
                ':ac1_close' => 0,
                ':ac1_cord' => 0,
                ':ac1_ven_debit' => 1,
                ':ac1_ven_credit' => 1,
                ':ac1_fiscal_acct' => 0,
                ':ac1_taxid' => 0,
                ':ac1_isrti' => 0,
                ':ac1_basert' => 0,
                ':ac1_mmcode' => 0,
                ':ac1_legal_num' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
                ':ac1_codref' => 1,
                ':ac1_line'   => $AC1LINE,
                ':business' => $Data['business'],
                ':branch'   => $Data['branch']
                ));


                if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
                // Se verifica que el detalle no de error insertando //

                }else{

                  // si falla algun insert del detalle de la devolucion de ventas se devuelven los cambios realizados por la transaccion,
                  // se retorna el error y se detiene la ejecucion del codigo restante.
                  $this->pedeo->trans_rollback();

                  $respuesta = array(
                                  'error'   => true,
                                  'data'       => $resDetalleAsiento,
                                  'mensaje'            => 'No se pudo registrar la devolucion de venta'
                  );

                  $this->response($respuesta);

                  return;
                }
                // print_r($dbito."\n");print_r($cdito);
      }

      // FIN DEL PROCEDIEMIENTO PARA LLENAR LA CUENTA PUENTE


      //PROCEDIMIENTO PARA LLENAR CUENTA INVENTARIO

      foreach ($DetalleConsolidadoCuentaInventario as $key => $posicion) {
                        $grantotalCuentaIventario = 0;
                        $grantotalCuentaIventarioOriginal = 0;
                        $cuentaInventario = "";
                        $dbito = 0;
                        $cdito = 0;
                        $MontoSysDB = 0;
                        $MontoSysCR = 0;
                        $centroCosto = '';
                        $unidadNegocio = '';
                        $codigoProyecto = '';
                        foreach ($posicion as $key => $value) {

                          $CUENTASINV = $this->account->getAccountItem($value->dv1_itemcode, $value->dv1_whscode);

                          $centroCosto = $value->dv1_prc_code;
                          $unidadNegocio = $value->dv1_uncode;
                          $codigoProyecto = $value->dv1_prj_code;

                          if ( isset($CUENTASINV['error']) && $CUENTASINV['error'] == false ) {

                              $dbito = 0;
                              $cdito = 0;
                              $MontoSysDB = 0;
                              $MontoSysCR = 0;

                              $cuentaInventario = $CUENTASINV['data']['acct_inv'];

                              // BUSCANDO COSTO CON EL QUE SE REALIZO LA ENTREGA
                              $sqlcostoentrega = "SELECT bmi_cost,bmy_baseentry,bmy_doctype
                                                  FROM tbmi
                                                  WHERE bmy_doctype = :bmy_doctype
                                                  AND bmy_baseentry = :bmy_baseentry
                                                  AND bmi_itemcode  = :bmi_itemcode
                                                  AND bmi_whscode   = :bmi_whscode";

                              $rescostoentrega = $this->pedeo->queryTable($sqlcostoentrega, array(

                                ':bmy_doctype'   => $Data['vdv_basetype'],
                                ':bmy_baseentry' => $Data['vdv_baseentry'],
                                ':bmi_itemcode'  => $value->dv1_itemcode,
                                ':bmi_whscode'   => $value->dv1_whscode
                              ));

                              if(!isset($rescostoentrega[0])){

                                $this->pedeo->trans_rollback();

                                $respuesta = array(
                                  'error'   => true,
                                  'data'    => $rescostoentrega,
                                  'mensaje' => 'No se encontro el costo del item en el documento de origen'
                                );

                                $this->response($respuesta);

                                return;
                              }
                              // FIN BUSQUEDA DEL COSTO ARTICULO

                              $costoArticulo =  $rescostoentrega[0]['bmi_cost'];
                              $cantidadArticulo = $value->dv1_quantity;
                              $grantotalCuentaIventario = ($grantotalCuentaIventario + ($costoArticulo * $cantidadArticulo));

                          }else{
                                // si falla algun insert del detalle de la devolucion de ventas se devuelven los cambios realizados por la transaccion,
                                // se retorna el error y se detiene la ejecucion del codigo restante.
                                $this->pedeo->trans_rollback();

                                $respuesta = array(
                                    'error'   => true,
                                    'data'    => $CUENTASINV,
                                    'mensaje' => 'No se encontro la cuenta de inventario'
                                );

                                $this->response($respuesta);

                                return;
                            }

                        }


                        $codigo3 = substr($cuentaInventario, 0, 1);

                        $grantotalCuentaIventarioOriginal = $grantotalCuentaIventario;

                        if(trim($Data['vdv_currency']) != $MONEDALOCAL ){

                          $grantotalCuentaIventario = ($grantotalCuentaIventario * $TasaDocLoc);
                        }

                        if( $codigo3 == 1 || $codigo3 == "1" ){
                          $dbito = $grantotalCuentaIventario;
                          if(trim($Data['vdv_currency']) != $MONEDASYS ){
                            $MontoSysDB = ($dbito / $TasaLocSys);
                          }else{
                            $MontoSysDB = $grantotalCuentaIventarioOriginal;
                          }
                        }else if( $codigo3 == 2 || $codigo3 == "2" ){
                          $dbito = $grantotalCuentaIventario;
                          if(trim($Data['vdv_currency']) != $MONEDASYS ){
                            $MontoSysDB = ($dbito / $TasaLocSys);
                          }else{
                            $MontoSysDB = $grantotalCuentaIventarioOriginal;
                          }
                        }else if( $codigo3 == 3 || $codigo3 == "3" ){
                            $dbito = $grantotalCuentaIventario;
                            if(trim($Data['vdv_currency']) != $MONEDASYS ){
                                $MontoSysDB = ($dbito / $TasaLocSys);
                            }else{
                                $MontoSysDB = $grantotalCuentaIventarioOriginal;
                            }
                        }else if( $codigo3 == 4 || $codigo3 == "4" ){
                            $dbito =  $grantotalCuentaIventario;
                            if(trim($Data['vdv_currency']) != $MONEDASYS ){
                                $MontoSysDB = ($dbito / $TasaLocSys);
                            }else{
                                $MontoSysDB = $grantotalCuentaIventarioOriginal;
                            }
                        }else if( $codigo3 == 5  || $codigo3 == "5" ){
                            $dbito =  $grantotalCuentaIventario;
                            if(trim($Data['vdv_currency']) != $MONEDASYS ){
                                $MontoSysDB = ($dbito / $TasaLocSys);
                            }else{
                                $MontoSysDB = $grantotalCuentaIventarioOriginal;
                            }
                        }else if( $codigo3 == 6 || $codigo3 == "6" ){
                            $dbito =  $grantotalCuentaIventario;
                            if(trim($Data['vdv_currency']) != $MONEDASYS ){
                                $MontoSysDB = ($dbito / $TasaLocSys);
                            }else{
                                $MontoSysDB = $grantotalCuentaIventarioOriginal;
                            }
                        }else if( $codigo3 == 7 || $codigo3 == "7" ){
                            $dbito = $grantotalCuentaIventario;
                            if(trim($Data['vdv_currency']) != $MONEDASYS ){
                                $MontoSysDB = ($dbito / $TasaLocSys);
                            }else{
                                $MontoSysDB = $grantotalCuentaIventarioOriginal;
                            }
                        }
                        $AC1LINE = $AC1LINE+1;
                        $resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

                        ':ac1_trans_id' => $resInsertAsiento,
                        ':ac1_account' => $cuentaInventario,
                        ':ac1_debit' => round ($dbito, $DECI_MALES),
                        ':ac1_credit' => round($cdito, $DECI_MALES),
                        ':ac1_debit_sys' => round($MontoSysDB, $DECI_MALES),
                        ':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
                        ':ac1_currex' => 0,
                        ':ac1_doc_date' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
                        ':ac1_doc_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
                        ':ac1_debit_import' => 0,
                        ':ac1_credit_import' => 0,
                        ':ac1_debit_importsys' => 0,
                        ':ac1_credit_importsys' => 0,
                        ':ac1_font_key' => $resInsert,
                        ':ac1_font_line' => 1,
                        ':ac1_font_type' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
                        ':ac1_accountvs' => 1,
                        ':ac1_doctype' => 18,
                        ':ac1_ref1' => "",
                        ':ac1_ref2' => "",
                        ':ac1_ref3' => "",
                        ':ac1_prc_code' => $centroCosto,
                        ':ac1_uncode' => $unidadNegocio,
                        ':ac1_prj_code' => $codigoProyecto,
                        ':ac1_rescon_date' => NULL,
                        ':ac1_recon_total' => 0,
                        ':ac1_made_user' => isset($Data['vdv_createby'])?$Data['vdv_createby']:NULL,
                        ':ac1_accperiod' => 1,
                        ':ac1_close' => 0,
                        ':ac1_cord' => 0,
                        ':ac1_ven_debit' => 1,
                        ':ac1_ven_credit' => 1,
                        ':ac1_fiscal_acct' => 0,
                        ':ac1_taxid' => 0,
                        ':ac1_isrti' => 0,
                        ':ac1_basert' => 0,
                        ':ac1_mmcode' => 0,
                        ':ac1_legal_num' => isset($Data['vdv_cardcode'])?$Data['vdv_cardcode']:NULL,
                        ':ac1_codref' => 1,
                        ':ac1_line'   => $AC1LINE,
                        ':business' => $Data['business'],
                        ':branch'   => $Data['branch']
                        ));

                        if(is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0){
                        // Se verifica que el detalle no de error insertando //
                        }else{

                          // si falla algun insert del detalle de la devolucion de ventas se devuelven los cambios realizados por la transaccion,
                          // se retorna el error y se detiene la ejecucion del codigo restante.
                          $this->pedeo->trans_rollback();

                          $respuesta = array(
                              'error'   => true,
                              'data'    => $resDetalleAsiento,
                              'mensaje' => 'No se pudo registrar la devolucion de ventas'
                          );

                          $this->response($respuesta);

                          return;
                        }

                        // print_r($dbito."\n");print_r($cdito);
      }

      // exit;

      // //FIN PROCEDIMIENTO PARA ACTUALIZAR ESTADO DOCUMENTO

      if ($Data['vdv_basetype'] == 3) {


          $sqlEstado1 = "SELECT
                              count(t1.em1_itemcode) item,
                              sum(t1.em1_quantity) cantidad
                              from dvem t0
                              inner join vem1 t1 on t0.vem_docentry = t1.em1_docentry
                              where t0.vem_docentry = :vem_docentry and t0.vem_doctype = :vem_doctype";


          $resEstado1 = $this->pedeo->queryTable($sqlEstado1, array(
                          ':vem_docentry' => $Data['vdv_baseentry'],
                          ':vem_doctype' => $Data['vdv_basetype']
          ));


          $sqlEstado2 = "SELECT
                              coalesce(count(distinct t3.dv1_itemcode),0) item,
                              coalesce(sum(t3.dv1_quantity),0) cantidad
                              from dvem t0
                              left join vem1 t1 on t0.vem_docentry = t1.em1_docentry
                              left join dvdv t2 on t0.vem_docentry = t2.vdv_baseentry
                              left join vdv1 t3 on t2.vdv_docentry = t3.dv1_docentry and t1.em1_itemcode = t3.dv1_itemcode
                              where t0.vem_docentry = :vem_docentry and t0.vem_doctype = :vem_doctype";
          $resEstado2 = $this->pedeo->queryTable($sqlEstado2,array(
                          ':vem_docentry' => $Data['vdv_baseentry'],
                          ':vem_doctype' => $Data['vdv_basetype']
          ));

          $resta_item = $resEstado1[0]['item'] - $resEstado2[0]['item'];
          $resta_cantidad = $resEstado1[0]['cantidad'] - $resEstado2[0]['cantidad'];

          $item_del = $resEstado1[0]['item'];
          $item_dev = $resEstado2[0]['item'];
          $cantidad_del = $resEstado1[0]['cantidad'];
          $cantidad_dev = $resEstado2[0]['cantidad'];

// print_r($resta_item);print_r($resta_cantidad);exit();die();
          if($item_del == $item_dev  &&  $cantidad_del == $cantidad_dev){


              $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
              VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

              $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
              ':bed_docentry' => $Data['vdv_baseentry'],
              ':bed_doctype' => $Data['vdv_basetype'],
              ':bed_status' => 3, //ESTADO CERRADO
              ':bed_createby' => $Data['vdv_createby'],
              ':bed_date' => date('Y-m-d'),
              ':bed_baseentry' => $resInsert,
              ':bed_basetype' => $Data['vdv_doctype']
              ));

              if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

                if(is_numeric($resta_item) && $resta_item == 0
                  && is_numeric($resta_cantidad) && $resta_cantidad == 0){

                  $sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
                  VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

                      $resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
                      ':bed_docentry' => $Data['vdv_baseentry'],
                      ':bed_doctype' => $Data['vdv_basetype'],
                      ':bed_status' => 3, //ESTADO CERRADO
                      ':bed_createby' => $Data['vdv_createby'],
                      ':bed_date' => date('Y-m-d'),
                      ':bed_baseentry' => $resInsert,
                      ':bed_basetype' => $Data['vdv_doctype']
                      ));
                  }

              }else{


              $this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data'    => $resInsertEstado,
                'mensaje' => 'No se pudo registrar la devolucion de venta'
                );
              $this->response($respuesta);

              return;
            }

          }
      }

      	// $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
				// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
				// print_r(json_encode($ressqlmac1));
				// exit;

      //SE VALIDA LA CONTABILIDAD CREADA
      if ($ResultadoInv == 1){
        $validateCont = $this->generic->validateAccountingAccent($resInsertAsiento);


        if( isset($validateCont['error']) && $validateCont['error'] == false ){

        }else{

            $ressqlmac1 = [];
            $sqlmac1 = "SELECT acc_name,ac1_account,ac1_debit,ac1_credit FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
            $ressqlmac1['contabilidad'] = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));


            $this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data' 	  => $ressqlmac1,
              'mensaje' => $validateCont['mensaje']
            );

            $this->response($respuesta);

            return;
        }
      }
      //



      // Si todo sale bien despues de insertar el detalle de la Devolución de clientes
      // se confirma la trasaccion  para que los cambios apliquen permanentemente
      // en la base de datos y se confirma la operacion exitosa.
      $this->pedeo->trans_commit();

      $respuesta = array(
      'error' => false,
      'data' => $resInsert,
      'mensaje' =>'Devolución de clientes registrada con exito'
      );


    }else{
    // Se devuelven los cambios realizados en la transaccion
    // si occurre un error  y se muestra devuelve el error.
    $this->pedeo->trans_rollback();

    $respuesta = array(
      'error'   => true,
      'data' => $resInsert,
      'mensaje'  => 'No se pudo registrar la Devolución de clientes'
    );

    }

    $this->response($respuesta);
  }

    //ACTUALIZAR Devolución de clientes
    public function updateSalesDv_post(){

        $Data = $this->post();

        if(!isset($Data['vdv_docentry']) OR !isset($Data['vdv_docnum']) OR
                        !isset($Data['vdv_docdate']) OR !isset($Data['vdv_duedate']) OR
                        !isset($Data['vdv_duedev']) OR !isset($Data['vdv_pricelist']) OR
                        !isset($Data['vdv_cardcode']) OR !isset($Data['vdv_cardname']) OR
                        !isset($Data['vdv_currency']) OR !isset($Data['vdv_contacid']) OR
                        !isset($Data['vdv_slpcode']) OR !isset($Data['vdv_empid']) OR
                        !isset($Data['vdv_comment']) OR !isset($Data['vdv_doctotal']) OR
                        !isset($Data['vdv_baseamnt']) OR !isset($Data['vdv_taxtotal']) OR
                        !isset($Data['vdv_discprofit']) OR !isset($Data['vdv_discount']) OR
                        !isset($Data['vdv_createat']) OR !isset($Data['vdv_baseentry']) OR
                        !isset($Data['vdv_basetype']) OR !isset($Data['vdv_doctype']) OR
                        !isset($Data['vdv_idadd']) OR !isset($Data['vdv_adress']) OR
                        !isset($Data['vdv_paytype']) OR !isset($Data['vdv_attch']) OR
                        !isset($Data['detail'])){

        $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


        $ContenidoDetalle = json_decode($Data['detail'], true);


      if(!is_array($ContenidoDetalle)){
        $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'No se encontro el detalle de la Devolución de clientes'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


        //Obtener Carpeta Principal del Proyecto
        $sqlMainFolder = " SELECT * FROM params";
        $resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());

        if(!isset($resMainFolder[0])){
                                        $respuesta = array(
                                        'error' => true,
                                        'data'  => array(),
                                        'mensaje' =>'No se encontro la caperta principal del proyecto'
                                        );

                                        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                                        return;
        }
        // FIN PROCESO PARA OBTENER LA CARPETA PRINCIPAL DEL PROYECTO

      $sqlUpdate = "UPDATE dvdv SET vdv_docdate=:vdv_docdate,vdv_duedate=:vdv_duedate, vdv_duedev=:vdv_duedev, vdv_pricelist=:vdv_pricelist, vdv_cardcode=:vdv_cardcode,
                    vdv_cardname=:vdv_cardname, vdv_currency=:vdv_currency, vdv_contacid=:vdv_contacid, vdv_slpcode=:vdv_slpcode,
                    vdv_empid=:vdv_empid, vdv_comment=:vdv_comment, vdv_doctotal=:vdv_doctotal, vdv_baseamnt=:vdv_baseamnt,
                    vdv_taxtotal=:vdv_taxtotal, vdv_discprofit=:vdv_discprofit, vdv_discount=:vdv_discount, vdv_createat=:vdv_createat,
                    vdv_baseentry=:vdv_baseentry, vdv_basetype=:vdv_basetype, vdv_doctype=:vdv_doctype, vdv_idadd=:vdv_idadd,
                    vdv_adress=:vdv_adress, vdv_paytype=:vdv_paytype ,business = :business,branch = :branch
                    WHERE vdv_docentry=:vdv_docentry";

      $this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
        ':vdv_docnum' => is_numeric($Data['vdv_docnum'])?$Data['vdv_docnum']:0,
        ':vdv_docdate' => $this->validateDate($Data['vdv_docdate'])?$Data['vdv_docdate']:NULL,
        ':vdv_duedate' => $this->validateDate($Data['vdv_duedate'])?$Data['vdv_duedate']:NULL,
        ':vdv_duedev' => $this->validateDate($Data['vdv_duedev'])?$Data['vdv_duedev']:NULL,
        ':vdv_pricelist' => is_numeric($Data['vdv_pricelist'])?$Data['vdv_pricelist']:0,
        ':vdv_cardcode' => isset($Data['vdv_pricelist'])?$Data['vdv_pricelist']:NULL,
        ':vdv_cardname' => isset($Data['vdv_cardname'])?$Data['vdv_cardname']:NULL,
        ':vdv_currency' => isset($Data['vdv_currency'])?$Data['vdv_currency']:NULL,
        ':vdv_contacid' => isset($Data['vdv_contacid'])?$Data['vdv_contacid']:NULL,
        ':vdv_slpcode' => is_numeric($Data['vdv_slpcode'])?$Data['vdv_slpcode']:0,
        ':vdv_empid' => is_numeric($Data['vdv_empid'])?$Data['vdv_empid']:0,
        ':vdv_comment' => isset($Data['vdv_comment'])?$Data['vdv_comment']:NULL,
        ':vdv_doctotal' => is_numeric($Data['vdv_doctotal'])?$Data['vdv_doctotal']:0,
        ':vdv_baseamnt' => is_numeric($Data['vdv_baseamnt'])?$Data['vdv_baseamnt']:0,
        ':vdv_taxtotal' => is_numeric($Data['vdv_taxtotal'])?$Data['vdv_taxtotal']:0,
        ':vdv_discprofit' => is_numeric($Data['vdv_discprofit'])?$Data['vdv_discprofit']:0,
        ':vdv_discount' => is_numeric($Data['vdv_discount'])?$Data['vdv_discount']:0,
        ':vdv_createat' => $this->validateDate($Data['vdv_createat'])?$Data['vdv_createat']:NULL,
        ':vdv_baseentry' => is_numeric($Data['vdv_baseentry'])?$Data['vdv_baseentry']:0,
        ':vdv_basetype' => is_numeric($Data['vdv_basetype'])?$Data['vdv_basetype']:0,
        ':vdv_doctype' => is_numeric($Data['vdv_doctype'])?$Data['vdv_doctype']:0,
        ':vdv_idadd' => isset($Data['vdv_idadd'])?$Data['vdv_idadd']:NULL,
        ':vdv_adress' => isset($Data['vdv_adress'])?$Data['vdv_adress']:NULL,
        ':vdv_paytype' => is_numeric($Data['vdv_paytype'])?$Data['vdv_paytype']:0,
        ':business' => isset($Data['business'])?$Data['business']:NULL,
        ':branch' => isset($Data['branch'])?$Data['branch']:NULL,
        ':vdv_docentry' => $Data['vdv_docentry']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

      $this->pedeo->queryTable("DELETE FROM vdv1 WHERE dv1_docentry=:dv1_docentry", array(':dv1_docentry' => $Data['vdv_docentry']));

      foreach ($ContenidoDetalle as $key => $detail) {

        $sqlInsertDetail = "INSERT INTO vdv1(dv1_docentry, dv1_itemcode, dv1_itemname, dv1_quantity, dv1_uom, dv1_whscode,
                            dv1_price, dv1_vat, dv1_vatsum, dv1_discount, dv1_linetotal, dv1_costcode, dv1_ubusiness, dv1_project,
                            dv1_acctcode, dv1_basetype, dv1_doctype, dv1_avprice, dv1_inventory, dv1_linenum, dv1_acciva)VALUES(:dv1_docentry, :dv1_itemcode, :dv1_itemname, :dv1_quantity,
                            :dv1_uom, :dv1_whscode,:dv1_price, :dv1_vat, :dv1_vatsum, :dv1_discount, :dv1_linetotal, :dv1_costcode, :dv1_ubusiness, :dv1_project,
                            :dv1_acctcode, :dv1_basetype, :dv1_doctype, :dv1_avprice, :dv1_inventory, :dv1_linenum :dv1_acciva)";

        $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
          ':dv1_docentry' => $Data['vdv_docentry'],
          ':dv1_itemcode' => isset($detail['dv1_itemcode'])?$detail['dv1_itemcode']:NULL,
          ':dv1_itemname' => isset($detail['dv1_itemname'])?$detail['dv1_itemname']:NULL,
          ':dv1_quantity' => is_numeric($detail['dv1_quantity'])?$detail['dv1_quantity']:0,
          ':dv1_uom' => isset($detail['dv1_uom'])?$detail['dv1_uom']:NULL,
          ':dv1_whscode' => isset($detail['dv1_whscode'])?$detail['dv1_whscode']:NULL,
          ':dv1_price' => is_numeric($detail['dv1_price'])?$detail['dv1_price']:0,
          ':dv1_vat' => is_numeric($detail['dv1_vat'])?$detail['dv1_vat']:0,
          ':dv1_vatsum' => is_numeric($detail['dv1_vatsum'])?$detail['dv1_vatsum']:0,
          ':dv1_discount' => is_numeric($detail['dv1_discount'])?$detail['dv1_discount']:0,
          ':dv1_linetotal' => is_numeric($detail['dv1_linetotal'])?$detail['dv1_linetotal']:0,
          ':dv1_costcode' => isset($detail['dv1_costcode'])?$detail['dv1_costcode']:NULL,
          ':dv1_ubusiness' => isset($detail['dv1_ubusiness'])?$detail['dv1_ubusiness']:NULL,
          ':dv1_project' => isset($detail['dv1_project'])?$detail['dv1_project']:NULL,
          ':dv1_acctcode' => is_numeric($detail['dv1_acctcode'])?$detail['dv1_acctcode']:0,
          ':dv1_basetype' => is_numeric($detail['dv1_basetype'])?$detail['dv1_basetype']:0,
          ':dv1_doctype' => is_numeric($detail['dv1_doctype'])?$detail['dv1_doctype']:0,
          ':dv1_avprice' => is_numeric($detail['dv1_avprice'])?$detail['dv1_avprice']:0,
          ':dv1_inventory' => is_numeric($detail['dv1_inventory'])?$detail['dv1_inventory']:NULL,
          ':dv1_linenum' => is_numeric($detail['dv1_linenum'])?$detail['dv1_linenum']:0,
          ':dv1_acciva' => is_numeric($detail['dv1_acciva'])?$detail['dv1_acciva']:0
        ));

        if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
                                        // Se verifica que el detalle no de error insertando //
        }else{

        // si falla algun insert del detalle de la Devolución de clientes se devuelven los cambios realizados por la transaccion,
        // se retorna el error y se detiene la ejecucion del codigo restante.
          $this->pedeo->trans_rollback();

          $respuesta = array(
            'error'   => true,
            'data'    => $resInsertDetail,
            'mensaje' => 'No se pudo registrar la Devolución de clientes 11'
          );

          $this->response($respuesta);

          return;
        }
      }


      $this->pedeo->trans_commit();

      $respuesta = array(
      'error' => false,
      'data' => $resUpdate,
      'mensaje' =>'Devolución de clientes actualizada con exito'
      );


      }else{

        $this->pedeo->trans_rollback();

        $respuesta = array(
        'error'   => true,
        'data'    => $resUpdate,
        'mensaje' => 'No se pudo actualizar la Devolución de clientes'
        );

      }

      $this->response($respuesta);
    }


    //OBTENER Devolución de clientesES
    public function getSalesDv_get(){

      $Data = $this->get();

      if ( !isset($Data['business']) OR !isset($Data['branch']) ) {
  
        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' => 'La informacion enviada no es valida'
        );
  
        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
  
        return;
      }

      $DECI_MALES =  $this->generic->getDecimals();

      $sqlSelect = self::getColumn('dvdv','vdv','','',$DECI_MALES, $Data['business'], $Data['branch']);


      $resSelect = $this->pedeo->queryTable($sqlSelect, array());

      if(isset($resSelect[0])){

        $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

        }else{

        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje' => 'busqueda sin resultados'
        );

      }

      $this->response($respuesta);
    }


    //OBTENER Devolución de clientes POR ID
    public function getSalesDvById_get(){

      $Data = $this->get();

      if(!isset($Data['vdv_docentry'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlSelect = " SELECT * FROM dvdv WHERE vdv_docentry =:vdv_docentry";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(":vdv_docentry" => $Data['vdv_docentry']));

      if(isset($resSelect[0])){

        $respuesta = array(
                        'error'  => false,
                        'data'   => $resSelect,
                        'mensaje'=> '');

      }else{

        $respuesta = array(
                        'error'   => true,
                        'data'    => array(),
                        'mensaje' => 'busqueda sin resultados'
        );

      }

      $this->response($respuesta);
    }


    //OBTENER Devolución de clientes DETALLE POR ID
    public function getSalesDvDetail_get(){

      $Data = $this->get();

      if(!isset($Data['dv1_docentry'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlSelect = " SELECT vdv1.*, dma_series_code
                     FROM vdv1
                     INNER JOIN dmar
                     ON cfc1.fc1_itemcode = dmar.dma_item_code
                     WHERE dv1_docentry =:dv1_docentry";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(":dv1_docentry" => $Data['dv1_docentry']));

      if(isset($resSelect[0])){

        $respuesta = array(
                        'error' => false,
                        'data'  => $resSelect,
                        'mensaje' => '');

      }else{

        $respuesta = array(
                        'error'  => true,
                        'data'   => array(),
                        'mensaje'=> 'busqueda sin resultados'
        );

      }

      $this->response($respuesta);
    }





    //OBTENER DEVOLUCION DE VENTAS POR ID SOCIO DE NEGOCIO
    public function getSalesDvBySN_get(){

      $Data = $this->get();

      if(!isset($Data['dms_card_code']) OR !isset($Data['business']) OR !isset($Data['branch'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlSelect = "SELECT * 
                    FROM dvdv 
                    left join estado_doc t1 
                    on t0.vdv_docentry = t1.entry and t0.vdv_doctype = t1.tipo
					          left join responsestatus t2 
                    on t1.entry = t2.id and t1.tipo = t2.tipo
					          WHERE t2.estado = 'Abierto' and t0.vdv_cardcode =:vdv_cardcode
                    AND business = :business 
                    AND branch = :branch";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(":vdv_cardcode" => $Data['dms_card_code'], ':business' => $Data['business'], ':branch' => $Data['branch']));

      if(isset($resSelect[0])){

                      $respuesta = array(
                                      'error' => false,
                                      'data'  => $resSelect,
                                      'mensaje' => '');

      }else{

                                      $respuesta = array(
                                                      'error'   => true,
                                                      'data' => array(),
                                                      'mensaje'            => 'busqueda sin resultados'
                                      );

      }

      $this->response($respuesta);
    }






    private function getUrl($data, $caperta){
      $url = "";

      if ($data == NULL){

        return $url;

      }

      if (!base64_decode($data, true) ){
        return $url;
      }

      $ruta = '/var/www/html/'.$caperta.'/assets/img/anexos/';

      $milliseconds = round(microtime(true) * 1000);


      $nombreArchivo = $milliseconds.".pdf";

      touch($ruta.$nombreArchivo);

      $file = fopen($ruta.$nombreArchivo,"wb");

      if(!empty($data)){

        fwrite($file, base64_decode($data));

        fclose($file);

        $url = "assets/img/anexos/".$nombreArchivo;
      }

      return $url;
    }

    private function buscarPosicion($llave, $inArray){
      $res = 0;
      for($i = 0; $i < count($inArray); $i++) {
        if($inArray[$i] == "$llave"){
          $res =  $i;
          break;
        }
      }

      return $res;
    }

    private function validateDate($fecha){
      if(strlen($fecha) == 10 OR strlen($fecha) > 10){
          return true;
      }else{
          return false;
      }
    }




}