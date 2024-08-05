<?php
//  SOCIOS DE NEGOCIO
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class BusinessPartner extends REST_Controller
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
    $this->load->library('Dv');
  }

  //Crear nuevo socio de negocio
  public function createBusinessPartner_post()
  {

    $Data = $this->post();

    if (
      !isset($Data['dms_card_code']) or
      !isset($Data['dms_card_name']) or
      !isset($Data['dms_card_type']) or
      !isset($Data['dms_enabled'])
    ) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = "SELECT dms_card_code, dms_card_type FROM dmsn WHERE dms_card_code = :dms_card_code AND dms_card_type = :dms_card_type";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(

      ':dms_card_code' => $Data['dms_card_code'],
      ':dms_card_type' => $Data['dms_card_type']

    ));

    if (isset($resSelect[0])) {

      $respuesta = array(
        'error' => true,
        'data'  => array($Data['dms_card_code'], $Data['dms_card_type']),
        'mensaje' => 'ya existe un socio negocio con la combinación del codigo y tipo de codigo'
      );

      $this->response($respuesta);

      return;
    }

    $this->pedeo->trans_begin();

    // VALIDAR CODIGO POS
    if ( isset($Data['dms_poscode']) && !empty($Data['dms_poscode']) ){

      $sqlPOS = "SELECT * FROM dmsn WHERE dms_poscode =:dms_poscode AND dms_card_type =:dms_card_type";

      $ressqlPOS = $this->pedeo->queryTable($sqlPOS, array(
        ':dms_poscode' => $Data['dms_poscode'],
        ':dms_card_type' => '1'
      ));
  
      if (isset($ressqlPOS[0])){
  
        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' => 'Existe otro cliente con el codigo POS: '.$Data['dms_poscode']
        );
  
        return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
      }
      //
    }
    //



    $sqlInsert = "INSERT INTO dmsn(dms_card_code, dms_card_name, dms_card_type, dms_short_name, dms_phone1, dms_phone2,
                    dms_cel, dms_email, dms_inv_mail, dms_group_num, dms_web_site, dms_sip_code, dms_agent, dms_pay_type,
                    dms_limit_cred, dms_inter, dms_price_list, dms_acct_sn, dms_acct_asn, dms_card_last_name, dms_enabled,
										dms_rtype,dms_classtype,dms_tax_regime, dms_reg_merc, dms_id_type, dv, dms_poscode, dms_taxregimecode,
                    dms_fiscalresponsabilitycode,dms_paymentmethod)
	                  VALUES (:dms_card_code, :dms_card_name, :dms_card_type, :dms_short_name, :dms_phone1, :dms_phone2,
                    :dms_cel, :dms_email, :dms_inv_mail, :dms_group_num, :dms_web_site, :dms_sip_code, :dms_agent, :dms_pay_type,
                    :dms_limit_cred, :dms_inter, :dms_price_list, :dms_acct_sn, :dms_acct_asn, :dms_card_last_name, :dms_enabled,
										:dms_rtype,:dms_classtype,:dms_tax_regime, :dms_reg_merc, :dms_id_type, :dv, :dms_poscode,:dms_taxregimecode,
                    :dms_fiscalresponsabilitycode,:dms_paymentmethod)";

    $resInsert = $this->pedeo->insertRow($sqlInsert, array(

      ':dms_card_code' => isset($Data['dms_card_code']) ? $Data['dms_card_code'] : NULL,
      ':dms_card_name' => isset($Data['dms_card_name']) ? $Data['dms_card_name'] : NULL,
      ':dms_card_type' => isset($Data['dms_card_type']) ? $Data['dms_card_type'] : 0,
      ':dms_short_name' => isset($Data['dms_short_name']) ? $Data['dms_short_name'] : NULL,
      ':dms_phone1' => isset($Data['dms_phone1']) ? $Data['dms_phone1'] : NULL,
      ':dms_phone2' => isset($Data['dms_phone2']) ? $Data['dms_phone2'] : NULL,
      ':dms_cel' => isset($Data['dms_cel']) ? $Data['dms_cel'] : NULL,
      ':dms_email' => isset($Data['dms_email']) ? $Data['dms_email'] : NULL,
      ':dms_inv_mail' => isset($Data['dms_inv_mail']) ? $Data['dms_inv_mail'] : NULL,
      ':dms_group_num' => isset($Data['dms_group_num']) ? $Data['dms_group_num'] : NULL,
      ':dms_web_site' => isset($Data['dms_web_site']) ? $Data['dms_web_site'] : NULL,
      ':dms_sip_code' => isset($Data['dms_sip_code']) ? $Data['dms_sip_code'] : NULL,
      ':dms_agent' => isset($Data['dms_agent']) ? $Data['dms_agent'] : NULL,
      ':dms_pay_type' => isset($Data['dms_pay_type']) ? $Data['dms_pay_type'] : NULL,
      ':dms_limit_cred' => is_numeric($Data['dms_limit_cred']) ? $this->ValidarN($Data['dms_limit_cred']) : NULL,
      ':dms_inter' => isset($Data['dms_inter']) && is_numeric($Data['dms_inter']) ? $Data['dms_inter'] : 0,
      ':dms_price_list' => isset($Data['dms_price_list']) ? $Data['dms_price_list'] : 0,
      ':dms_acct_sn' => NULL,
      ':dms_acct_asn' => NULL,
      ':dms_card_last_name' => isset($Data['dms_card_last_name']) ? $Data['dms_card_last_name'] : NULL,
      ':dms_enabled' => isset($Data['dms_enabled']) ? $Data['dms_enabled'] : NULL,
      ':dms_rtype' => isset($Data['dms_rtype']) ? $Data['dms_rtype'] : NULL,
      ':dms_classtype' => isset($Data['dms_classtype']) ? $Data['dms_classtype'] : NULL,
      ':dms_reg_merc' => isset($Data['dms_reg_merc']) ? $Data['dms_reg_merc'] : NULL,
      ':dms_tax_regime' => isset($Data['dms_tax_regime']) ? $Data['dms_tax_regime'] : NULL,
      ':dms_id_type' => is_numeric($Data['dms_id_type']) ? $Data['dms_id_type'] : 0,
      ':dv' => $this->dv->calcularDigitoV($Data['dms_card_code']),
      ':dms_poscode' => isset($Data['dms_poscode']) ? $Data['dms_poscode'] : NULL,

      ':dms_taxregimecode' => isset($Data['dms_taxregimecode']) ? $Data['dms_taxregimecode'] : NULL,
      ':dms_fiscalresponsabilitycode' => isset($Data['dms_fiscalresponsabilitycode']) ? $Data['dms_fiscalresponsabilitycode'] : NULL,
      ':dms_paymentmethod' => isset($Data['dms_paymentmethod']) ? $Data['dms_paymentmethod'] : NULL

      

    ));

    if (is_numeric($resInsert) && $resInsert > 0) {


      //SE VERIFCA SI TIENE RETECIONES Y SE AGREGAN A LA TABLA
      if (isset($Data['dms_rte'])) {
        $retenciones = is_array($Data['dms_rte']) ? $Data['dms_rte'] : array();


        if (count($retenciones) > 0) {

          foreach ($retenciones as $key => $value) {

            $resInsertRetenciones = $this->pedeo->insertRow('INSERT INTO rtsn(tsn_cardcode, tsn_type, tsn_rtid)VALUES(:tsn_cardcode, :tsn_type, :tsn_rtid)', array(
              ':tsn_cardcode' => $Data['dms_card_code'],
              ':tsn_type'     => $Data['dms_card_type'],
              ':tsn_rtid'     => $value
            ));

            if (is_numeric($resInsertRetenciones) && $resInsertRetenciones > 0) {
            } else {

              $this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsertRetenciones,
                'mensaje'  => 'No se pudo registrar la retencion para el tercero actual'
              );


              $this->response($respuesta);

              return;
            }
          }
        }
      }
      //FIN DEL PROCEDIMIENTO PARA AGREGAR LAS RETENCIONES

      //SE VERIFCA SI TIENE RESPONSABILIDAD FISCAL
      if (isset($Data['dms_resp_fiscal'])) {
        $responsabilidad = is_array($Data['dms_resp_fiscal']) ? $Data['dms_resp_fiscal'] : array();
        if (count($responsabilidad) > 0) {

          foreach ($responsabilidad as $key => $value) {
            $resInsertResponsabilidades = $this->pedeo->insertRow('INSERT INTO rfsn(fsn_cardcode, fsn_type, fsn_rfid)VALUES(:fsn_cardcode, :fsn_type, :fsn_rfid)', array(
              ':fsn_cardcode' => $Data['dms_card_code'],
              ':fsn_type'     => $Data['dms_card_type'],
              ':fsn_rfid'     => $value
            ));
            if (is_numeric($resInsertResponsabilidades) && $resInsertResponsabilidades > 0) {
            } else {
              $this->pedeo->trans_rollback();
              $respuesta = array(
                'error'   => true,
                'data' => $resInsertResponsabilidades,
                'mensaje'  => 'No se pudo registrar la responsabilidad fiscal actual'
              );
              $this->response($respuesta);
              return;
            }
          }
        }
      }
      //FIN DEL PROCEDIMIENTO PARA AGREGAR RESPONSABILIDAD FISCAL

      $this->pedeo->trans_commit();


      $respuesta = array(
        'error' => false,
        'data' => $resInsert,
        'mensaje' => 'Socio de negocio registrado con exito'
      );
    } else {

      $this->pedeo->trans_rollback();

      $respuesta = array(
        'error'   => true,
        'data'     => $resInsert,
        'mensaje'  => 'No se pudo registrar el socio de negocio'
      );
    }

    $this->response($respuesta);
  }

  //Actualizar socio de negocio
  public function updateBusinessPartner_post()
  {

    $Data = $this->post();

    if (!isset($Data['dms_id'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = "SELECT dms_card_code, dms_card_type FROM dmsn WHERE dms_card_code = :dms_card_code
                    AND dms_id != :dms_id AND dms_card_type = :dms_card_type";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(

      ':dms_card_code' => $Data['dms_card_code'],
      ':dms_id'        => $Data['dms_id'],
      ':dms_card_type' => $Data['dms_card_type']

    ));


    if (isset($resSelect[0])) {

      $respuesta = array(
        'error' => true,
        'data'  => array($Data['dms_card_code'], $Data['dms_card_type']),
        'mensaje' => 'ya existe un socio negocio con la combinación del codigo y tipo de codigo, no es posible actualizar '
      );

      $this->response($respuesta);

      return;
    }

    // VALIDAR CODIGO POS
    if ( isset($Data['dms_poscode']) && !empty($Data['dms_poscode']) ){

      $sqlPOS = "SELECT * FROM dmsn WHERE dms_poscode =:dms_poscode AND dms_card_code !=:dms_card_code AND dms_card_type =:dms_card_type";

      $ressqlPOS = $this->pedeo->queryTable($sqlPOS, array(
        ':dms_poscode' => $Data['dms_poscode'],
        ':dms_card_code' => $Data['dms_card_code'],
        ':dms_card_type' => '1'
      ));
  
      if (isset($ressqlPOS[0])){
  
        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' => 'Existe otro cliente con el codigo POS: '.$Data['dms_poscode']
        );
  
        return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
      }
      //
    }
    //
  

    $sqlUpdate = "UPDATE dmsn SET dms_enabled = :dms_enabled, dms_card_name = :dms_card_name, dms_card_type = :dms_card_type,
                    dms_short_name = :dms_short_name, dms_phone1 = :dms_phone1, dms_phone2 = :dms_phone2, dms_cel = :dms_cel,
                    dms_email = :dms_email, dms_inv_mail = :dms_inv_mail, dms_group_num = :dms_group_num, dms_web_site = :dms_web_site,
                    dms_sip_code = :dms_sip_code, dms_agent = :dms_agent, dms_pay_type = :dms_pay_type, dms_limit_cred = :dms_limit_cred,
                    dms_inter = :dms_inter, dms_price_list = :dms_price_list, dms_acct_sn = :dms_acct_sn, dms_acct_asn = :dms_acct_asn , dms_card_last_name = :dms_card_last_name,
									  dms_rtype = :dms_rtype, dms_classtype = :dms_classtype, dms_tax_regime = :dms_tax_regime, dms_reg_merc =:dms_reg_merc,
                    dms_id_type = :dms_id_type, dms_poscode =:dms_poscode,   
                    dms_taxregimecode = :dms_taxregimecode, 
                    dms_fiscalresponsabilitycode = :dms_fiscalresponsabilitycode,
                    dms_paymentmethod = :dms_paymentmethod
                    WHERE dms_id = :dms_id";

    $this->pedeo->trans_begin();

    $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
      ':dms_card_name' => isset($Data['dms_card_name']) ? $Data['dms_card_name'] : NULL,
      ':dms_card_type' => isset($Data['dms_card_type']) ? $Data['dms_card_type'] : 0,
      ':dms_short_name' => isset($Data['dms_short_name']) ? $Data['dms_short_name'] : NULL,
      ':dms_phone1' => isset($Data['dms_phone1']) ? $Data['dms_phone1'] : NULL,
      ':dms_phone2' => isset($Data['dms_phone2']) ? $Data['dms_phone2'] : NULL,
      ':dms_cel' => isset($Data['dms_cel']) ? $Data['dms_cel'] : NULL,
      ':dms_email' => isset($Data['dms_email']) ? $Data['dms_email'] : NULL,
      ':dms_inv_mail' => isset($Data['dms_inv_mail']) ? $Data['dms_inv_mail'] : NULL,
      ':dms_group_num' => isset($Data['dms_group_num']) ? $Data['dms_group_num'] : NULL,
      ':dms_web_site' => isset($Data['dms_web_site']) ? $Data['dms_web_site'] : NULL,
      ':dms_sip_code' => isset($Data['dms_sip_code']) ? $Data['dms_sip_code'] : NULL,
      ':dms_agent' => isset($Data['dms_agent']) ? $Data['dms_agent'] : NULL,
      ':dms_pay_type' => isset($Data['dms_pay_type']) ? $Data['dms_pay_type'] : NULL,
      ':dms_limit_cred' => isset($Data['dms_limit_cred']) ? $this->ValidarN($Data['dms_limit_cred']) : NULL,
      ':dms_inter' => isset($Data['dms_inter']) && is_numeric($Data['dms_inter']) ? $Data['dms_inter'] : 0,
      ':dms_price_list' => isset($Data['dms_price_list']) ? $Data['dms_price_list'] : NULL,
      ':dms_acct_sn' => NULL,
      ':dms_acct_asn' => NULL,
      ':dms_card_last_name' => isset($Data['dms_card_last_name']) ? $Data['dms_card_last_name'] : NULL,
      ':dms_id' => $Data['dms_id'],
      ':dms_enabled' => isset($Data['dms_enabled']) ? $Data['dms_enabled'] : NULL,
      ':dms_rtype' => isset($Data['dms_rtype']) ? $Data['dms_rtype'] : NULL,
      ':dms_classtype' => isset($Data['dms_classtype']) ? $Data['dms_classtype'] : NULL,
      ':dms_reg_merc' => isset($Data['dms_reg_merc']) ? $Data['dms_reg_merc'] : NULL,
      ':dms_tax_regime' => isset($Data['dms_tax_regime']) ? $Data['dms_tax_regime'] : NULL,
      ':dms_id_type' => is_numeric($Data['dms_id_type']) ? $Data['dms_id_type'] : 0,
      ':dms_poscode' => isset($Data['dms_poscode']) ? $Data['dms_poscode'] : NULL,

      ':dms_taxregimecode' => isset($Data['dms_taxregimecode']) ? $Data['dms_taxregimecode'] : NULL,
      ':dms_fiscalresponsabilitycode' => isset($Data['dms_fiscalresponsabilitycode']) ? $Data['dms_fiscalresponsabilitycode'] : NULL,
      ':dms_paymentmethod' => isset($Data['dms_paymentmethod']) ? $Data['dms_paymentmethod'] : NULL
    ));

    if (is_numeric($resUpdate) && $resUpdate == 1) {


      //SE VERIFCA SI TIENE RETECIONES Y SE AGREGAN A LA TABLA
      $retenciones = (isset($Data['dms_rte']) && is_array($Data['dms_rte'])) ? $Data['dms_rte'] : array();
      if (count($retenciones) > 0) {
        $this->pedeo->queryTable('DELETE FROM rtsn WHERE tsn_cardcode = :tsn_cardcode AND  tsn_type = :tsn_type', array(':tsn_cardcode' => $Data['dms_card_code'], ':tsn_type' => $Data['dms_card_type']));
        foreach ($retenciones as $key => $value) {
          $resInsertRetenciones = $this->pedeo->insertRow('INSERT INTO rtsn(tsn_cardcode, tsn_type, tsn_rtid)VALUES(:tsn_cardcode, :tsn_type, :tsn_rtid)', array(
            ':tsn_cardcode' => $Data['dms_card_code'],
            ':tsn_type'     => $Data['dms_card_type'],
            ':tsn_rtid'     => $value
          ));
          if (is_numeric($resInsertRetenciones) && $resInsertRetenciones > 0) {
          } else {
            $this->pedeo->trans_rollback();
            $respuesta = array(
              'error'   => true,
              'data' => $resInsertRetenciones,
              'mensaje'  => 'No se pudo registrar la retencion para el tercero actual'
            );
            $this->response($respuesta);
            return;
          }
        }
      }else{
        $this->pedeo->queryTable('DELETE FROM rtsn WHERE tsn_cardcode = :tsn_cardcode AND  tsn_type = :tsn_type', array(':tsn_cardcode' => $Data['dms_card_code'], ':tsn_type' => $Data['dms_card_type']));
      }
      //FIN DEL PROCEDIMIENTO PARA AGREGAR LAS RETENCIONES

      //SE VERIFCA SI TIENE RESPONSABILIDAD FISCAL
      $responsabilidad = (isset($Data['dms_resp_fiscal']) && is_array($Data['dms_resp_fiscal'])) ? $Data['dms_resp_fiscal'] : array();
      if (count($responsabilidad) == 0) {
        $this->pedeo->queryTable('DELETE FROM rfsn WHERE fsn_cardcode = :fsn_cardcode AND  fsn_type = :fsn_type', array(':fsn_cardcode' => $Data['dms_card_code'], ':fsn_type' => $Data['dms_card_type']));
      }else if(count($responsabilidad) > 0) {
        $this->pedeo->queryTable('DELETE FROM rfsn WHERE fsn_cardcode = :fsn_cardcode AND  fsn_type = :fsn_type', array(':fsn_cardcode' => $Data['dms_card_code'], ':fsn_type' => $Data['dms_card_type']));
        foreach ($responsabilidad as $key => $value) {
          $resInsertResponsabilidades = $this->pedeo->insertRow('INSERT INTO rfsn(fsn_cardcode, fsn_type, fsn_rfid)VALUES(:fsn_cardcode, :fsn_type, :fsn_rfid)', array(
            ':fsn_cardcode' => $Data['dms_card_code'],
            ':fsn_type'     => $Data['dms_card_type'],
            ':fsn_rfid'     => $value
          ));
          if (is_numeric($resInsertResponsabilidades) && $resInsertResponsabilidades > 0) {
          } else {
            $this->pedeo->trans_rollback();
            $respuesta = array(
              'error'   => true,
              'data' => $resInsertResponsabilidades,
              'mensaje'  => 'No se pudo actualizar la responsabilidad fiscal actual'
            );
            $this->response($respuesta);
            return;
          }
        }
      }
      //FIN DEL PROCEDIMIENTO PARA AGREGAR RESPONSABILIDAD FISCAL

      $this->pedeo->trans_commit();

      $respuesta = array(
        'error' => false,
        'data' => $resUpdate,
        'mensaje' => 'Socio de negocio actualizado con exito'
      );
    } else {

      $this->pedeo->trans_rollback();

      $respuesta = array(
        'error'   => true,
        'data'    => $resUpdate,
        'mensaje'  => 'No se pudo actualizar el socio de negocio '
      );
    }

    $this->response($respuesta);
  }

  //Actualiza el estado de un socio de negocio
  public function updateStatus_post()
  {

    $Data = $this->post();

    if (!isset($Data['dms_id']) or !isset($Data['dms_enabled'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
      return;
    }

    $sqlUpdate = "UPDATE dmsn SET dms_enabled = :dms_enabled WHERE dms_id = :dms_id";


    $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

      ':dms_enabled' => $Data['dms_enabled'],
      ':dms_id' => $Data['dms_id']

    ));


    if (is_numeric($resUpdate) && $resUpdate == 1) {

      $respuesta = array(
        'error'   => false,
        'data'    => $resUpdate,
        'mensaje' => 'Se actualizó el estado del socio de negocio'
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data'    => $resUpdate,
        'mensaje'  => 'No se pudo actualizar el estado del socio de negocio'
      );
    }

    $this->response($respuesta);
  }

  // Obtener Socios de negocio
  public function getBusinessPartner_get()
  {

    $sqlSelect = "SELECT concat(dms_card_name, ' ', dms_card_last_name) AS nombreyapellido,* FROM dmsn ";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array());

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
        'mensaje'  => 'busqueda sin resultados'
      );
    }

    $this->response($respuesta);
  }
  // Obtener Socios de negocio PROVEEDOR
  public function getBusinessPartnerProvider_get()
  {

    $sqlSelect = "SELECT concat(dms_card_name, ' ', dms_card_last_name) AS nombreyapellido,* FROM dmsn WHERE dms_card_type = '2'";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array());

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
        'mensaje'  => 'busqueda sin resultados'
      );
    }

    $this->response($respuesta);
  }

  // Obtener Socio de negocio por Id
  public function getBusinessPartnerById_get()
  {

    $Data = $this->get();

    if (!isset($Data['dms_id'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = " SELECT * FROM dmsn WHERE dms_id = :dms_id";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dms_id' => $Data['dms_id']));

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
        'mensaje'  => 'busqueda sin resultados'
      );
    }

    $this->response($respuesta);
  }

  // OBTENER RESPONSABILIDADES FISCALES POR SOCIO DE NEGOCIO
  public function getRespFiscalesBYSN_get()
  {

    $Data = $this->get();
    $ret = array();

    if (!isset($Data['tsn_cardcode']) or !isset($Data['tsn_type'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = "SELECT * FROM rfsn WHERE fsn_cardcode = :fsn_cardcode AND fsn_type  = :fsn_type";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':fsn_cardcode' => $Data['tsn_cardcode'], ':fsn_type' => $Data['tsn_type']));

    if (isset($resSelect[0])) {

      foreach ($resSelect as $key => $value) {

        array_push($ret, $value['fsn_rfid']);
      }
      $respuesta = array(
        'error' => false,
        'data'  => $ret,
        'mensaje' => ''
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data' => array(),
        'mensaje'  => 'busqueda sin resultados'
      );
    }

    $this->response($respuesta);
  }
  // OBTENER RETENCIONES POR SOCIO DE NEGOCIO
  public function getRetencionesBYSN_get()
  {

    $Data = $this->get();
    $ret = array();

    if (!isset($Data['tsn_cardcode']) or !isset($Data['tsn_type'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = "SELECT *
										FROM rtsn
										WHERE tsn_cardcode = :tsn_cardcode
										AND tsn_type  = :tsn_type";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':tsn_cardcode' => $Data['tsn_cardcode'], ':tsn_type' => $Data['tsn_type']));

    if (isset($resSelect[0])) {

      foreach ($resSelect as $key => $value) {

        array_push($ret, $value['tsn_rtid']);
      }

      $respuesta = array(
        'error' => false,
        'data'  => $ret,
        'mensaje' => ''
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data' => array(),
        'mensaje'  => 'busqueda sin resultados'
      );
    }

    $this->response($respuesta);
  }

  //ACTUALIZAR CUENTA BANCARIA DE SOCIO DE NEGOCIO
  //COMO LA PRINCIPAL
  public function updateAccBank_post()
  {
    $Data = $this->post();
    if (!isset($Data['dmb_card_code']) or !isset($Data['dmb_id'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
      return;
    }

    $resUpdate = NULL;

    $update = $this->pedeo->updateRow('UPDATE dmsb SET dmb_major = 0 WHERE dmb_card_code = :dmb_card_code', [':dmb_card_code' => $Data['dmb_card_code']]);
    if (is_numeric($update) && $update > 0) {
      //
      $resUpdate = $this->pedeo->updateRow(
        "UPDATE dmsb SET dmb_major = :dmb_major WHERE dmb_id = :dmb_id",
        array(
          ':dmb_major' => 1,
          ':dmb_id' => $Data['dmb_id']
        )
      );
    }

    $respuesta = array(
      'error'   => true,
      'data'    => $update,
      'mensaje'  => 'No se pudo actualizar la dirreccion del socio de negocio'
    );

    if (is_numeric($resUpdate) && $resUpdate == 1) {
      $respuesta = array(
        'error'   => false,
        'data'    => $resUpdate,
        'mensaje' => 'Se actualizó la dirreccion del socio de negocio'
      );
    }

    $this->response($respuesta);
  }


    //ACTUALIZAR DIRECCION DE SOCIO DE NEGOCIO
  //HABILITAR/DESABILITAR
  public function updateAccBankStatus_post()
  {
    $Data = $this->post();

    if (!isset($Data['dmb_status']) or !isset($Data['dmb_id'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
      return;
    }

    $update = $this->pedeo->updateRow('UPDATE dmsb SET dmb_status = :dmb_status WHERE dmb_id = :dmb_id', [':dmb_status' => $Data['dmb_status'], ':dmb_id' => $Data['dmb_id']]);

    $respuesta = array(
      'error'   => true,
      'data'    => $update,
      'mensaje'  => 'No se pudo actualizar la cuenta de banco del socio de negocio'
    );

    if (is_numeric($update) && $update == 1) {
      $respuesta = array(
        'error'   => false,
        'data'    => $update,
        'mensaje' => 'Se actualizó el estado de la cuenta de banco del socio de negocio'
      );
    }

    $this->response($respuesta);
  }


  //ACTUALIZAR DIRECCION DE SOCIO DE NEGOCIO
  //COMO LA PRINCIPAL
  public function updateAddress_post()
  {
    $Data = $this->post();

    if (!isset($Data['dmd_card_code']) or !isset($Data['dmd_id'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
      return;
    }

    $resUpdate = NULL;

    $update = $this->pedeo->updateRow('UPDATE dmsd SET dmd_ppal = 0 WHERE dmd_card_code = :dmd_card_code', [':dmd_card_code' => $Data['dmd_card_code']]);

    if (is_numeric($update) && $update > 0) {
      //
      $resUpdate = $this->pedeo->updateRow(
        "UPDATE dmsd SET dmd_ppal = :dmd_ppal WHERE dmd_id = :dmd_id",
        array(
          ':dmd_ppal' => 1,
          ':dmd_id' => $Data['dmd_id']
        )
      );
    }

    $respuesta = array(
      'error'   => true,
      'data'    => $update,
      'mensaje'  => 'No se pudo actualizar la dirreccion del socio de negocio'
    );

    if (is_numeric($resUpdate) && $resUpdate == 1) {
      $respuesta = array(
        'error'   => false,
        'data'    => $resUpdate,
        'mensaje' => 'Se actualizó la dirreccion del socio de negocio'
      );
    }

    $this->response($respuesta);
  }


  //ACTUALIZAR DIRECCION DE SOCIO DE NEGOCIO
  //HABILITAR/DESABILITAR
  public function updateAddressStatus_post()
  {
    $Data = $this->post();

    if (!isset($Data['dmd_status']) or !isset($Data['dmd_id'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
      return;
    }

    $update = $this->pedeo->updateRow('UPDATE dmsd SET dmd_status = :dmd_status WHERE dmd_id = :dmd_id', [':dmd_status' => $Data['dmd_status'], ':dmd_id' => $Data['dmd_id']]);

    $respuesta = array(
      'error'   => true,
      'data'    => $update,
      'mensaje'  => 'No se pudo actualizar la dirreccion del socio de negocio'
    );

    if (is_numeric($update) && $update == 1) {
      $respuesta = array(
        'error'   => false,
        'data'    => $update,
        'mensaje' => 'Se actualizó el estado de la dirreccion del socio de negocio'
      );
    }

    $this->response($respuesta);
  }


  //ACTUALIZAR CONTACTO DE SOCIO DE NEGOCIO
  //HABILITAR/DESABILITAR
  public function updateContactStatus_post()
  {
    $Data = $this->post();

    if (!isset($Data['dmc_status']) or !isset($Data['dmc_id'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
      return;
    }

    $update = $this->pedeo->updateRow('UPDATE dmsc SET dmc_status = :dmc_status WHERE dmc_id = :dmc_id', [':dmc_status' => $Data['dmc_status'], ':dmc_id' => $Data['dmc_id']]);

    $respuesta = array(
      'error'   => true,
      'data'    => $update,
      'mensaje'  => 'No se pudo actualizar el contacto del socio de negocio'
    );

    if (is_numeric($update) && $update == 1) {
      $respuesta = array(
        'error'   => false,
        'data'    => $update,
        'mensaje' => 'Se actualizó el estado del contacto del socio de negocio'
      );
    }

    $this->response($respuesta);
  }

   // Obtener Socios de negocio con filtros
  public function getFilterBusinessPartner_post()
  {

    $Data = $this->post();

    $filtro = "";



    if (!empty($Data['slt_group_num'])){
      $filtro .= " AND  dmsn.dms_group_num::int = ".$Data['slt_group_num'];
    }

    if (!empty($Data['slt_sales_num'])){
      $filtro .= " AND dmsn.dms_sip_code = ".$Data['slt_sales_num'];
    }

    if (!empty($Data['slt_owner_num'])){
      $filtro .= " AND dmsn.dms_agent = ".$Data['slt_owner_num'];
    }

    if (isset($Data['slt_state_num']) && is_numeric($Data['slt_state_num'])){
      $filtro .= " AND dmsn.dms_enabled = ".$Data['slt_state_num'];
    }


 
    $sqlSelect = "SELECT concat(dms_card_name, ' ', dms_card_last_name) AS nombreyapellido, dmsn.*,
                mpf_name, mgs_acct, mgs_acctp, dmlp_name_list, a.mev_names as vendedor, b.mev_names as propietario,dmgs.mgs_name as name_group,
                ttre.tre_name as regimen_fiscal ,tprf.prf_name as respon_fiscal, tmdp.mdp_name as medio_pago 
                FROM dmsn
                LEFT JOIN dmpf on mpf_id::text = dms_pay_type
                LEFT JOIN dmgs  on mgs_id::text = dms_group_num
                LEFT JOIN dmpl on dmlp_id = dms_price_list
                LEFT JOIN dmev a on a.mev_id = dms_sip_code
                LEFT JOIN dmev b on b.mev_id = dms_agent
                LEFT JOIN ttre on ttre.tre_code = dmsn.dms_taxregimecode 
                LEFT JOIN tprf on tprf.prf_code = dmsn.dms_fiscalresponsabilitycode 
                LEFT JOIN tmdp on tmdp.mdp_id  = dmsn.dms_paymentmethod
                WHERE 1 = 1 ".$filtro;


    $resSelect = $this->pedeo->queryTable($sqlSelect, array());

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
         'mensaje'  => 'busqueda sin resultados'
      );
    }

     $this->response($respuesta);
  }

  //Crear nuevo socio de negocio
  public function createCompleteBusinessPartner_post()
  {

    $Data = $this->post();

    if (
      !isset($Data['dms_card_code']) or
      !isset($Data['dms_card_name']) or
      !isset($Data['dms_card_type']) or
      !isset($Data['dms_enabled'])
    ) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = "SELECT dms_card_code, dms_card_type FROM dmsn WHERE dms_card_code = :dms_card_code AND dms_card_type = :dms_card_type";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(

      ':dms_card_code' => $Data['dms_card_code'],
      ':dms_card_type' => $Data['dms_card_type']

    ));

    if (isset($resSelect[0])) {

      $respuesta = array(
        'error' => true,
        'data'  => array($Data['dms_card_code'], $Data['dms_card_type']),
        'mensaje' => 'ya existe un socio negocio con la combinación del codigo y tipo de codigo'
      );

      $this->response($respuesta);

      return;
    }


    $sql = "SELECT * FROM dmsn WHERE dms_poscode=:dms_poscode AND dms_card_code !=:dms_card_code AND dms_card_type =:dms_card_type";

    $resSql = $this->pedeo->queryTable($sql, array(
      ':dms_poscode' => $Data['dms_poscode'],
      ':dms_card_code' => $Data['dms_card_code'],
      ':dms_card_type' => '1'
    ));

    if (isset($resSql[0])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'Existe otro cliente con esa referencia'
      );

      return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
    }


    $this->pedeo->trans_begin();


    $sqlInsert = "INSERT INTO dmsn(dms_card_code, dms_card_name, dms_card_type, dms_short_name, dms_phone1, dms_phone2,
                  dms_cel, dms_email, dms_inv_mail, dms_group_num, dms_web_site, dms_sip_code, dms_agent, dms_pay_type,
                  dms_limit_cred, dms_inter, dms_price_list, dms_acct_sn, dms_acct_asn, dms_card_last_name, dms_enabled,
                  dms_rtype,dms_classtype,dms_poscode,dms_paymentmethod,dms_id_type,dv)
                  VALUES (:dms_card_code, :dms_card_name, :dms_card_type, :dms_short_name, :dms_phone1, :dms_phone2,
                  :dms_cel, :dms_email, :dms_inv_mail, :dms_group_num, :dms_web_site, :dms_sip_code, :dms_agent, :dms_pay_type,
                  :dms_limit_cred, :dms_inter, :dms_price_list, :dms_acct_sn, :dms_acct_asn, :dms_card_last_name, :dms_enabled,
                  :dms_rtype,:dms_classtype,:dms_poscode,:dms_paymentmethod,:dms_id_type,:dv)";

    $resInsert = $this->pedeo->insertRow($sqlInsert, array(

      ':dms_card_code' => isset($Data['dms_card_code']) ? $Data['dms_card_code'] : NULL,
      ':dms_card_name' => isset($Data['dms_card_name']) ? $Data['dms_card_name'] : NULL,
      ':dms_card_type' => isset($Data['dms_card_type']) ? $Data['dms_card_type'] : 0,
      ':dms_short_name' => isset($Data['dms_short_name']) ? $Data['dms_short_name'] : NULL,
      ':dms_phone1' => isset($Data['dms_phone1']) ? $Data['dms_phone1'] : NULL,
      ':dms_phone2' => isset($Data['dms_phone2']) ? $Data['dms_phone2'] : NULL,
      ':dms_cel' => isset($Data['dms_cel']) ? $Data['dms_cel'] : NULL,
      ':dms_email' => isset($Data['dms_email']) ? $Data['dms_email'] : NULL,
      ':dms_inv_mail' => isset($Data['dms_inv_mail']) ? $Data['dms_inv_mail'] : NULL,
      ':dms_group_num' => isset($Data['dms_group_num']) ? $Data['dms_group_num'] : NULL,
      ':dms_web_site' => isset($Data['dms_web_site']) ? $Data['dms_web_site'] : NULL,
      ':dms_sip_code' => isset($Data['dms_sip_code']) ? $Data['dms_sip_code'] : NULL,
      ':dms_agent' => isset($Data['dms_agent']) ? $Data['dms_agent'] : NULL,
      ':dms_pay_type' => isset($Data['dms_pay_type']) ? $Data['dms_pay_type'] : NULL,
      ':dms_limit_cred' => is_numeric($Data['dms_limit_cred']) ? $this->ValidarN($Data['dms_limit_cred']) : NULL,
      ':dms_inter' => isset($Data['dms_inter']) ? $Data['dms_inter'] : NULL,
      ':dms_price_list' => isset($Data['dms_price_list']) ? $Data['dms_price_list'] : 0,
      ':dms_acct_sn' => NULL,
      ':dms_acct_asn' => NULL,
      ':dms_card_last_name' => isset($Data['dms_card_last_name']) ? $Data['dms_card_last_name'] : NULL,
      ':dms_enabled' => isset($Data['dms_enabled']) ? $Data['dms_enabled'] : NULL,
      ':dms_rtype' => isset($Data['dms_rtype']) ? $Data['dms_rtype'] : NULL,
      ':dms_classtype' => isset($Data['dms_classtype']) ? $Data['dms_classtype'] : NULL,
      ':dms_poscode' => isset($Data['dms_poscode']) ? $Data['dms_poscode'] : NULL,
      ':dms_paymentmethod' => isset($Data['dms_paymentmethod']) && is_numeric($Data['dms_paymentmethod']) ? $Data['dms_paymentmethod'] : NULL,
      ':dms_id_type' => is_numeric($Data['dms_id_type']) ? $Data['dms_id_type'] : 0,
      ':dv' => $this->dv->calcularDigitoV($Data['dms_card_code'])
    ));

    if (is_numeric($resInsert) && $resInsert > 0) {


      //SE VERIFCA SI TIENE RETECIONES Y SE AGREGAN A LA TABLA
      if (isset($Data['dms_rte'])) {
        $retenciones = is_array($Data['dms_rte']) ? $Data['dms_rte'] : array();


        if (count($retenciones) > 0) {

          foreach ($retenciones as $key => $value) {

            $resInsertRetenciones = $this->pedeo->insertRow('INSERT INTO rtsn(tsn_cardcode, tsn_type, tsn_rtid)VALUES(:tsn_cardcode, :tsn_type, :tsn_rtid)', array(
              ':tsn_cardcode' => $Data['dms_card_code'],
              ':tsn_type'     => $Data['dms_card_type'],
              ':tsn_rtid'     => $value
            ));

            if (is_numeric($resInsertRetenciones) && $resInsertRetenciones > 0) {
            } else {

              $this->pedeo->trans_rollback();

              $respuesta = array(
                'error'   => true,
                'data' => $resInsertRetenciones,
                'mensaje'  => 'No se pudo registrar la retencion para el tercero actual'
              );


              $this->response($respuesta);

              return;
            }
          }
        }
      }

      $sqlInsertAdd = "INSERT INTO dmsd(dmd_state_mm, dmd_state, dmd_id_add, dmd_delivery_add, dmd_country, dmd_city,
          dmd_card_code, dmd_adress, dmd_tonw, dmd_ppal)VALUES(:dmd_state_mm, :dmd_state, :dmd_id_add, :dmd_delivery_add,
          :dmd_country, :dmd_city, :dmd_card_code, :dmd_adress, :dmd_tonw, :dmd_ppal)";

      $resInsertAdd = $this->pedeo->insertRow($sqlInsertAdd, array(
        ':dmd_state_mm' => $Data['dmd_state_mm'],
        ':dmd_state' => $Data['dmd_state'],
        ':dmd_id_add' => $Data['dmd_id_add'],
        ':dmd_delivery_add' => 1,
        ':dmd_country' => $Data['dmd_country'],
        ':dmd_city' => $Data['dmd_city'],
        ':dmd_card_code' => $Data['dms_card_code'],
        ':dmd_adress' => $Data['dmd_adress'],
        ':dmd_tonw' => $Data['dmd_tonw'],
        ':dmd_ppal' => 1
      ));

      if (is_numeric($resInsertAdd) && $resInsertAdd > 0) {
        # code...
      } else {
        $this->pedeo->trans_rollback();

        $respuesta = array(
          'error'   => true,
          'data' => $resInsertAdd,
          'mensaje'  => 'No se pudo registrar la direccion para el tercero actual'
        );


        $this->response($respuesta);

        return;
      }
      //FIN DEL PROCEDIMIENTO PARA AGREGAR LAS RETENCIONES

      $this->pedeo->trans_commit();

      $fixrate = $this->pedeo->queryTable("SELECT tbdc.*,getfixrate() as fixrate from tbdc where  tbdc.bdc_clasify = :dms_classtype ", array(":dms_classtype" => $Data['dms_classtype']));

      $respuesta = array(
        'error' => false,
        'data' => $fixrate,
        'mensaje' => 'Socio de negocio registrado con exito'
      );
    } else {

      $this->pedeo->trans_rollback();

      $respuesta = array(
        'error'   => true,
        'data'     => $resInsert,
        'mensaje'  => 'No se pudo registrar el socio de negocio'
      );
    }

    $this->response($respuesta);
  }

  private function ValidarN($dato)
  {

    if (is_numeric($dato)) {
      return $dato;
    } else {
      return 0;
    }
  }

  //FUNCION PARA OBTENER SALDO DEL SN CLIENTE/PROVEEDOR
  // NO SE USA
  public function getBalance_get()
  {
    //DECLARR VARIABLES
    $saldo = 0;
    //RESPUESTA POR DEFAULT
    $respuesta = array(
      'error' => true,
      'data' => [],
      'mensaje' => 'No se encontraron datos en la busqueda'
    );
    //
    $Data = $this->get();
    //CONSULTA PARA OBTENER EL SALDO
    $moneda = "SELECT get_localcur() as moneda";
    $resMoneda = $this->pedeo->queryTable($moneda,array());
    $sql = "SELECT
              COALESCE(SUM(mac1.ac1_ven_debit  - mac1.ac1_ven_credit) 
              +
              COALESCE((select 
           		            sum({table_e}.{prefijo}_doctotal) as saldo
           	            from {table_e} 
           	            inner join responsestatus r on {table_e}.{prefijo}_doctype = r.tipo and {table_e}.{prefijo}_docentry = r.id
           	            where {table_e}.{prefijo}_cardcode = :cardcode and r.estado = 'Abierto'),0),0) as saldo
            FROM mac1
            INNER JOIN dmsn ON mac1.ac1_legal_num  = dmsn.dms_card_code
            WHERE mac1.ac1_legal_num = :cardcode AND mac1.business = :business AND mac1.branch = :branch AND dmsn.dms_card_type = '{card_type}'";
    //REEMPLAZAR DATOS DE LA CONSULTA
    $sql = str_replace("{moneda}",$resMoneda[0]['moneda'],$sql);
    if($Data['doctype'] == 1 ){
      $sql = str_replace("{card_type}",1,$sql);
      $sql = str_replace("{table_e}","dvct",$sql);
      $sql = str_replace("{prefijo}","dvc",$sql);
    }else if($Data['doctype'] == 2 ){
      $sql = str_replace("{card_type}",1,$sql);
      $sql = str_replace("{table_e}","dvov",$sql);
      $sql = str_replace("{prefijo}","vov",$sql);
    }else if ($Data['doctype'] == 3){
      $sql = str_replace("{card_type}",1,$sql);
      $sql = str_replace("{table_e}","dvem",$sql);
      $sql = str_replace("{prefijo}","vem",$sql);
    }else if ($Data['doctype'] == 4){
      $sql = str_replace("{card_type}",1,$sql);
      $sql = str_replace("{table_e}","dvdv",$sql);
      $sql = str_replace("{prefijo}","vdv",$sql);
    }else if ($Data['doctype'] == 5){
        $sql = str_replace("{card_type}",1,$sql);
        $sql = str_replace("{table_e}","dvfv",$sql);
        $sql = str_replace("{prefijo}","dvf",$sql);
    }else if ($Data['doctype'] == 6){
        $sql = str_replace("{card_type}",1,$sql);
        $sql = str_replace("{table_e}","dvnc",$sql);
        $sql = str_replace("{prefijo}","vnc",$sql);
    }else if ($Data['doctype'] == 7){
        $sql = str_replace("{card_type}",1,$sql);
        $sql = str_replace("{table_e}","dvnd",$sql);
        $sql = str_replace("{prefijo}","vnd",$sql);
    }else if ($Data['doctype'] == 10){
      $sql = str_replace("{card_type}",2,$sql);
      $sql = str_replace("{table_e}","dcpo",$sql);
      $sql = str_replace("{prefijo}","cpo",$sql);
    }else if ($Data['doctype'] == 11){
      $sql = str_replace("{card_type}",2,$sql);
      $sql = str_replace("{table_e}","dcoc",$sql);
      $sql = str_replace("{prefijo}","coc",$sql);
    }else if ($Data['doctype'] == 12){
      $sql = str_replace("{card_type}",2,$sql);
      $sql = str_replace("{table_e}","dcpo",$sql);
      $sql = str_replace("{prefijo}","cpo",$sql);
    }else if ($Data['doctype'] == 13){
      $sql = str_replace("{card_type}",2,$sql);
      $sql = str_replace("{table_e}","dcec",$sql);
      $sql = str_replace("{prefijo}","cec",$sql);
    }else if ($Data['doctype'] == 14){
      $sql = str_replace("{card_type}",2,$sql);
      $sql = str_replace("{table_e}","dcdc",$sql);
      $sql = str_replace("{prefijo}","cdc",$sql);
    }else if ($Data['doctype'] == 15){
      $sql = str_replace("{card_type}",2,$sql);
      $sql = str_replace("{table_e}","dcfc",$sql);
      $sql = str_replace("{prefijo}","cfc",$sql);
    }else if ($Data['doctype'] == 16){
      $sql = str_replace("{card_type}",2,$sql);
      $sql = str_replace("{table_e}","dcnc",$sql);
      $sql = str_replace("{prefijo}","cnc",$sql);
    }else if ($Data['doctype'] == 17){
      $sql = str_replace("{card_type}",2,$sql);
      $sql = str_replace("{table_e}","dcnd",$sql);
      $sql = str_replace("{prefijo}","cnd",$sql);
    }
    print_r($sql);exit;
    //RESULTADO DE LA CONSULTA
    $resSql = $this->pedeo->queryTable($sql,array(
      ':cardcode' => $Data['cardcode'],
      ':business' => $Data['business'],
      ':branch' => $Data['branch']
    ));
    //
    //$saldo = isset($resSql[0]['saldo']) && is_numeric($resSql[0]['saldo']) ? number_format($resSql[0]['saldo'],2,',','.') : number_format(0,2,',','.');
    //VALIDAR SI EL RESULTADO TRAE DATOS
    if(isset($resSql[0])){
      $respuesta = array(
        'error' => false,
        'data' => $resSql,
        'mensaje' => 'OK'
      );
    }

    $this->response($respuesta);
  }

  //OBTENER ANEXOS DE SN
  public function getAttachSN_get()
  {
    $Data = $this->get();

    $respuesta = array(
      'error' => true,
      'data' => [],
      'mensaje' => 'No se encontraron datos en la busqueda'
    );

    if(!isset($Data['dma_card_code']) && empty($Data['dma_card_code'])){
      $respuesta = array(
        'error' => true,
        'data' => [],
        'mensaje' => 'Informacion enviada invalida'
      );
    }

    $sql = "SELECT code AS cardcode, attach,description FROM dmsa WHERE code = :cardcode";
    $resSql = $this->pedeo->queryTable($sql,array(
      ':cardcode' => $Data['dma_card_code']
    ));

    if(isset($resSql[0])){
      $respuesta = array(
        'error' => false,
        'data' => $resSql,
        'mensaje' => 'OK'
      );
    }
    $this->response($respuesta);
  }

  // ACTUALIZAR CODIGO DE REFERENCIA
  public function setPosCode_post(){

    $Data = $this->post();

    if (
      !isset($Data['dms_card_code']) OR !isset($Data['dms_poscode'])
    ) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

    }

    $sql = "SELECT * FROM dmsn WHERE dms_poscode=:dms_poscode AND dms_card_code !=:dms_card_code AND dms_card_type =:dms_card_type";

    $resSql = $this->pedeo->queryTable($sql, array(
      ':dms_poscode' => $Data['dms_poscode'],
      ':dms_card_code' => $Data['dms_card_code'],
      ':dms_card_type' => '1'
    ));

    if (isset($resSql[0])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'Existe otro cliente con esa referencia'
      );

      return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
    }

    $sqlUpdate = "UPDATE dmsn SET dms_poscode=:dms_poscode WHERE dms_card_code =:dms_card_code AND dms_card_type =:dms_card_type";

    $resSqlUpdate = $this->pedeo->updateRow($sqlUpdate, array(
      ':dms_poscode' => $Data['dms_poscode'],
      ':dms_card_code' => $Data['dms_card_code'],
      ':dms_card_type' => '1'
    ));


    if (is_numeric($resSqlUpdate) && $resSqlUpdate == 1){
      $respuesta = array(
        'error' => false,
        'data'  => array(),
        'mensaje' => 'Referencia actualizada con exito'
      );
    }else{
      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'No se pudo actualizar la referencia'
      );
    }


    $this->response($respuesta);
  }


  // OBTENER EL SALDO DE LA CARTERA
  // PARA LOS SOCIOS DE NEGOCIO
  public function getBalanceW_post() {

    $Data = $this->post();

    if ( !isset($Data['business']) OR !isset($Data['cardcode']) OR !isset($Data['currency']) OR !isset($Data['order']) OR !isset($Data['cardtype'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'Faltan parametros para procesar la información'
      );

      return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
    }


    if ( $Data['cardtype'] == 1 ) {

      $sql = "select sum(saldo_venc)as total_saldo from (SELECT distinct
              mac1.ac1_font_key,
              mac1.ac1_legal_num as codigo_proveedor,
              mac1.ac1_account as cuenta,
              CURRENT_DATE - dvf_duedate dias_atrasado,
              dvfv.dvf_comment,
              dvfv.dvf_currency,
              mac1.ac1_font_key as dvf_docentry,
              dvfv.dvf_docnum,
              dvfv.dvf_docdate as fecha_doc,
              dvfv.dvf_duedate as fecha_ven,
              dvf_docnum as id_origen,
              mac1.ac1_font_type as numtype,
              mdt_docname as tipo,
              case
              when mac1.ac1_font_type = 5 OR mac1.ac1_font_type = 34  then get_dynamic_conversion(:currency,get_localcur(),dvf_docdate,mac1.ac1_debit, get_localcur())
              else get_dynamic_conversion(:currency,get_localcur(),dvf_docdate,mac1.ac1_credit, get_localcur())
              end	 as total_doc,
              get_dynamic_conversion(:currency,get_localcur(),dvf_docdate,(mac1.ac1_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
              '' retencion,
              get_tax_currency(dvfv.dvf_currency, dvfv.dvf_docdate) as tasa_dia,
              ac1_line_num,
              ac1_cord
              from  mac1
              inner join dacc
              on mac1.ac1_account = dacc.acc_code
              and acc_businessp = '1'
              inner join dmdt
              on mac1.ac1_font_type = dmdt.mdt_doctype
              inner join dvfv
              on dvfv.dvf_doctype = mac1.ac1_font_type
              and dvfv.dvf_docentry = mac1.ac1_font_key
              where mac1.ac1_legal_num = :cardcode
              and mac1.business = :business
              and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
              -- primer q
              union all
              select distinct
              mac1.ac1_font_key,
              mac1.ac1_legal_num as codigo_proveedor,
              mac1.ac1_account as cuenta,
              CURRENT_DATE - gbpr.bpr_docdate as dias_atrasado,
              gbpr.bpr_comments as bpr_comment,
              gbpr.bpr_currency,
              mac1.ac1_font_key as dvf_docentry,
              gbpr.bpr_docnum,
              gbpr.bpr_docdate as fecha_doc,
              gbpr.bpr_docdate as fecha_ven,
              gbpr.bpr_docnum as id_origen,
              mac1.ac1_font_type as numtype,
              mdt_docname as tipo,
              case
                when mac1.ac1_font_type = 5 OR mac1.ac1_font_type = 34 then get_dynamic_conversion(:currency,get_localcur(),bpr_docdate,mac1.ac1_debit, get_localcur())
                else get_dynamic_conversion(:currency,get_localcur(),bpr_docdate,mac1.ac1_credit, get_localcur())
              end	 as total_doc,
              get_dynamic_conversion(:currency,get_localcur(),bpr_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
              '' retencion,
              get_tax_currency(gbpr.bpr_currency,gbpr.bpr_docdate) as tasa_dia,
              ac1_line_num,
              ac1_cord
              from  mac1
              inner join dacc
              on mac1.ac1_account = dacc.acc_code
              and acc_businessp = '1'
              inner join dmdt
              on mac1.ac1_font_type = dmdt.mdt_doctype
              inner join gbpr
              on gbpr.bpr_doctype = mac1.ac1_font_type
              and gbpr.bpr_docentry = mac1.ac1_font_key
              where mac1.ac1_legal_num = :cardcode
              and mac1.business = :business
              and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
              -- 2 query
              union all
              select distinct
              mac1.ac1_font_key,
              mac1.ac1_legal_num as codigo_proveedor,
              mac1.ac1_account as cuenta,
              CURRENT_DATE - dvnc.vnc_docdate as dias_atrasado,
              dvnc.vnc_comment as bpr_comment,
              dvnc.vnc_currency,
              mac1.ac1_font_key as dvf_docentry,
              dvnc.vnc_docnum,
              dvnc.vnc_docdate as fecha_doc,
              dvnc.vnc_duedate as fecha_ven,
              dvnc.vnc_docnum as id_origen,
              mac1.ac1_font_type as numtype,
              mdt_docname as tipo,
              case
                when mac1.ac1_font_type = 5 OR mac1.ac1_font_type = 34 then get_dynamic_conversion(:currency,get_localcur(),vnc_docdate,mac1.ac1_debit, get_localcur())
                else get_dynamic_conversion(:currency,get_localcur(),vnc_docdate,mac1.ac1_credit, get_localcur())
              end	 as total_doc,
              get_dynamic_conversion(:currency,get_localcur(),vnc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
              '' retencion,
              get_tax_currency(dvnc.vnc_currency, dvnc.vnc_docdate) as tasa_dia,
              ac1_line_num,
              ac1_cord
              from  mac1
              inner join dacc
              on mac1.ac1_account = dacc.acc_code
              and acc_businessp = '1'
              inner join dmdt
              on mac1.ac1_font_type = dmdt.mdt_doctype
              inner join dvnc
              on dvnc.vnc_doctype = mac1.ac1_font_type
              and dvnc.vnc_docentry = mac1.ac1_font_key
              where mac1.ac1_legal_num = :cardcode
              and mac1.business = :business
              and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
              -- 3 query
              union all
              select distinct
              mac1.ac1_font_key,
              mac1.ac1_legal_num as codigo_proveedor,
              mac1.ac1_account as cuenta,
              CURRENT_DATE - dvnd.vnd_docdate as dias_atrasado,
              dvnd.vnd_comment as bpr_comment,
              dvnd.vnd_currency,
              mac1.ac1_font_key as dvf_docentry,
              dvnd.vnd_docnum,
              dvnd.vnd_docdate as fecha_doc,
              dvnd.vnd_duedate as fecha_ven,
              dvnd.vnd_docnum as id_origen,
              mac1.ac1_font_type as numtype,
              mdt_docname as tipo,
              case
                when mac1.ac1_font_type = 5 OR mac1.ac1_font_type = 34 then get_dynamic_conversion(:currency,get_localcur(),vnd_docdate,mac1.ac1_debit, get_localcur())
                else get_dynamic_conversion(:currency,get_localcur(),vnd_docdate,mac1.ac1_credit, get_localcur())
              end	 as total_doc,
              get_dynamic_conversion(:currency,get_localcur(),vnd_docdate,(mac1.ac1_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
              '' retencion,
              get_tax_currency(dvnd.vnd_currency, dvnd.vnd_docdate) as tasa_dia,
              ac1_line_num,
              ac1_cord
              from  mac1
              inner join dacc
              on mac1.ac1_account = dacc.acc_code
              and acc_businessp = '1'
              inner join dmdt
              on mac1.ac1_font_type = dmdt.mdt_doctype
              inner join dvnd
              on dvnd.vnd_doctype = mac1.ac1_font_type
              and dvnd.vnd_docentry = mac1.ac1_font_key
              where mac1.ac1_legal_num = :cardcode
              and mac1.business = :business
              and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
              --ASIENTOS MANUALES
              union all
              select distinct
              mac1.ac1_font_key,
              case
                when ac1_card_type = '1' then concat('C',mac1.ac1_legal_num)
                when ac1_card_type = '2' then concat('P',mac1.ac1_legal_num)
              end as codigoproveedor,
              mac1.ac1_account as cuenta,
              CURRENT_DATE - tmac.mac_doc_duedate dias_atrasado,
              tmac.mac_comments,
              tmac.mac_currency,
              0 as dvf_docentry,
              0 as docnum,
              tmac.mac_doc_date as fecha_doc,
              tmac.mac_doc_duedate as fecha_ven,
              0 as id_origen,
              18 as numtype,
              mdt_docname as tipo,
              case
                when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_debit, get_localcur())
                when mac1.ac1_cord = 1 then get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_credit, get_localcur())
              end	 as total_doc,
              get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
              '' retencion,
              get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia,
              ac1_line_num,
              ac1_cord
              from  mac1
              inner join dacc
              on mac1.ac1_account = dacc.acc_code
              and acc_businessp = '1'
              inner join dmdt
              on mac1.ac1_font_type = dmdt.mdt_doctype
              inner join tmac
              on tmac.mac_trans_id = mac1.ac1_font_key
              and tmac.mac_doctype = mac1.ac1_font_type
              inner join dmsn
              on mac1.ac1_card_type = dmsn.dms_card_type
              and mac1.ac1_legal_num = dmsn.dms_card_code
              where mac1.ac1_legal_num = :cardcode
              and mac1.business = :business
              and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
              -- RECIBOS DE CAJA DISTRIBUCION
              union all
              select distinct
              mac1.ac1_font_key,
              mac1.ac1_legal_num as codigo_proveedor,
              mac1.ac1_account as cuenta,
              CURRENT_DATE - dvrc.vrc_docdate as dias_atrasado,
              dvrc.vrc_comment as bpr_comment,
              dvrc.vrc_currency,
              mac1.ac1_font_key as dvf_docentry,
              dvrc.vrc_docnum,
              dvrc.vrc_docdate as fecha_doc,
              dvrc.vrc_duedate as fecha_ven,
              dvrc.vrc_docnum as id_origen,
              mac1.ac1_font_type as numtype,
              case 
                when mac1.ac1_font_type = 47 AND mac1.ac1_credit = 0 then  mdt_docname 
                when mac1.ac1_font_type = 47 AND mac1.ac1_debit = 0 then 'Anticipo Cliente (Pasanaku)'
              end as tipo,
              case
                      when mac1.ac1_font_type = 47 and ac1_debit > 0 then get_dynamic_conversion(:currency, get_localcur(),vrc_docdate,mac1.ac1_debit ,get_localcur())
                      else get_dynamic_conversion(:currency, get_localcur(),vrc_docdate,mac1.ac1_credit ,get_localcur())
                end	 as total_doc,
              get_dynamic_conversion(:currency,get_localcur(),vrc_docdate,(mac1.ac1_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
              '' retencion,
              get_tax_currency(dvrc.vrc_currency, dvrc.vrc_docdate) as tasa_dia,
              ac1_line_num,
              ac1_cord
              from  mac1
              inner join dacc
              on mac1.ac1_account = dacc.acc_code
              and acc_businessp = '1'
              inner join dmdt
              on mac1.ac1_font_type = dmdt.mdt_doctype
              inner join dvrc
              on dvrc.vrc_doctype = mac1.ac1_font_type
              and dvrc.vrc_docentry = mac1.ac1_font_key
              where mac1.ac1_legal_num = :cardcode
              and mac1.business = :business
              and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
              {INCLUDE_ORDER}) as total";

          $complemento = " -- PEDIDO DE VENTAS
                          union all
                          select 
                          dvov.vov_docentry as ac1_font_key,
                          dvov.vov_cardcode as codigo_proveedor,
                          0 as cuenta,
                          CURRENT_DATE - dvov.vov_duedate as dias_atrasado,
                          dvov.vov_comment,
                          dvov.vov_currency,
                          dvov.vov_docentry as dvf_docentry,
                          dvov.vov_docnum,
                          dvov.vov_docdate as fecha_doc,
                          dvov.vov_duedate as fecha_ven,
                          vov_docnum as id_origen,
                          dvov.vov_doctype  as numtype,
                          mdt_docname as tipo,
                          vov_doctotal as total_doc,
                          vov_doctotal as saldo_venc,
                          '' retencion,
                          get_tax_currency(dvov.vov_currency, dvov.vov_docdate) as tasa_dia,
                          0 as ac1_line_num,
                          0 as ac1_cord
                          from  dvov
                          inner join dmdt on dvov.vov_doctype  = dmdt.mdt_doctype 
                          inner join responsestatus on vov_doctype = responsestatus.tipo  and vov_docentry = responsestatus.id 
                          where dvov.vov_cardcode  = :cardcode
                          and dvov.business = :business
                          and responsestatus.estado = 'Abierto'";

          if ( $Data['order'] == 1 ) {
            $sql = str_replace('{INCLUDE_ORDER}', $complemento, $sql);
          }else{
            $sql = str_replace('{INCLUDE_ORDER}', '', $sql);
          }

          $resSql = $this->pedeo->queryTable($sql, array(":cardcode" => $Data['cardcode'], ":business" => $Data['business'], ":currency" => $Data['currency'] ));

          if ( isset($resSql[0])){
            $respuesta = array(
              'error' => false,
              'data' => $resSql,
              'mensaje' => 'OK'
            );
          }else{
            $respuesta = array(
              'error' => false,
              'data' => $resSql,
              'mensaje' => 'Busqueda sin resultados'
            );
          }

    }else if ($Data['cardtype'] == 2){


      $sql = "select sum ( saldo_venc ) as total_saldo from (SELECT distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigo_proveedor,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - cfc_duedate dias_atrasado,
        dcfc.cfc_comment,
        dcfc.cfc_currency,
        mac1.ac1_font_key as dvf_docentry,
        dcfc.cfc_docnum,
        dcfc.cfc_docdate as fecha_doc,
        dcfc.cfc_duedate as fecha_ven,
        cfc_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
        case
        when mac1.ac1_font_type = 15 OR mac1.ac1_font_type = 46 then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,mac1.ac1_credit, get_localcur())
        else get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,mac1.ac1_debit, get_localcur())
        end	 as total_doc,
        get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_credit) , get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(dcfc.cfc_currency,dcfc.cfc_docdate) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcfc
        on dcfc.cfc_doctype = mac1.ac1_font_type
        and dcfc.cfc_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and mac1.business = :business
        and ABS((mac1.ac1_ven_credit) - (mac1.ac1_ven_debit)) > 0
        --PAGO EFECTUADO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigo_proveedor,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - gbpe.bpe_docdate as dias_atrasado,
        gbpe.bpe_comments as bpr_comment,
        gbpe.bpe_currency,
        mac1.ac1_font_key as dvf_docentry,
        gbpe.bpe_docnum,
        gbpe.bpe_docdate as fecha_doc,
        gbpe.bpe_docdate as fecha_ven,
        gbpe.bpe_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
        case
        when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,mac1.ac1_debit, get_localcur())
        else get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,mac1.ac1_debit, get_localcur())
        end	 as total_doc,
        get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) , get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(gbpe.bpe_currency,gbpe.bpe_docdate) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join gbpe
        on gbpe.bpe_doctype = mac1.ac1_font_type
        and gbpe.bpe_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and mac1.business = :business
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --NOTA CREDITO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigo_proveedor,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - dcnc.cnc_docdate as dias_atrasado,
        dcnc.cnc_comment as bpr_comment,
        dcnc.cnc_currency,
        mac1.ac1_font_key as dvf_docentry,
        dcnc.cnc_docnum,
        dcnc.cnc_docdate as fecha_doc,
        dcnc.cnc_duedate as fecha_ven,
        dcnc.cnc_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
        case
        when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,mac1.ac1_debit, get_localcur())
        else get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,mac1.ac1_debit, get_localcur())
        end	 as total_doc,
        get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) , get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(dcnc.cnc_currency,dcnc.cnc_docdate ) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcnc
        on dcnc.cnc_doctype = mac1.ac1_font_type
        and dcnc.cnc_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and mac1.business = :business
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --NOTA DEBITO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigo_proveedor,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - dcnd.cnd_docdate as dias_atrasado,
        dcnd.cnd_comment as bpr_comment,
        dcnd.cnd_currency,
        mac1.ac1_font_key as dvf_docentry,
        dcnd.cnd_docnum,
        dcnd.cnd_docdate as fecha_doc,
        dcnd.cnd_duedate as fecha_ven,
        dcnd.cnd_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
        case
        when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,mac1.ac1_debit, get_localcur())
        else get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,mac1.ac1_credit, get_localcur())
        end	 as total_doc,
        get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,(mac1.ac1_ven_credit) - (mac1.ac1_debit), get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(dcnd.cnd_currency, dcnd.cnd_docdate) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcnd
        on dcnd.cnd_doctype = mac1.ac1_font_type
        and dcnd.cnd_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and mac1.business = :business
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --ASIENTOS MANUALES
        union all
        select distinct
        mac1.ac1_font_key,
        case
          when ac1_card_type = '1' then mac1.ac1_legal_num
          when ac1_card_type = '2' then mac1.ac1_legal_num
        end as codigoproveedor,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - tmac.mac_doc_duedate dias_atrasado,
        tmac.mac_comments,
        tmac.mac_currency,
        0 as dvf_docentry,
        0 as docnum,
        tmac.mac_doc_date as fecha_doc,
        tmac.mac_doc_duedate as fecha_ven,
        0 as id_origen,
        18 as numtype,
        mdt_docname as tipo,
        case
          when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_debit, get_localcur())
          when mac1.ac1_cord = 1 then get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_credit, get_localcur())
        end	 as total_doc,
        get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join tmac
        on tmac.mac_trans_id = mac1.ac1_font_key
        and tmac.mac_doctype = mac1.ac1_font_type
        inner join dmsn
        on mac1.ac1_card_type = dmsn.dms_card_type
        and mac1.ac1_legal_num = dmsn.dms_card_code
        where mac1.ac1_legal_num = :cardcode
        and mac1.business = :business
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --SOLICITUD DE ANTICIPO DE COMPRAS
        UNION ALL
        SELECT  
        dcsa.csa_docentry as ac1_font_key,
        dcsa.csa_cardcode as codigo_proveedor,
        get_acctp(:cardcode,2) as cuenta,
        CURRENT_DATE - dcsa.csa_duedate as dias_atrasado,
        dcsa.csa_comment as bpr_comment,
        dcsa.csa_currency,
        csa1.sa1_docentry as dvf_docentry,
        dcsa.csa_docnum ,
        dcsa.csa_docdate as fecha_doc,
        dcsa.csa_duedate as fecha_ven,
        dcsa.csa_docnum as id_origen,
        dcsa.csa_doctype as numtype,
        mdt_docname as tipo,
        get_dynamic_conversion(:currency,dcsa.csa_currency,dcsa.csa_docdate,dcsa.csa_anticipate_total, get_localcur()) as total_doc,
        get_dynamic_conversion(:currency,dcsa.csa_currency,dcsa.csa_docdate,(dcsa.csa_anticipate_total) - (dcsa.csa_paytoday) , get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(dcsa.csa_currency,dcsa.csa_docdate) as tasa_dia,
        sa1_linenum,
        0 as ac1_cord
        from dcsa
        inner join csa1 on dcsa.csa_docentry = csa1.sa1_docentry
        inner join dmdt on dmdt.mdt_doctype = dcsa.csa_doctype
        where csa_cardcode = :cardcode
        and dcsa.business = :business
        AND abs(get_dynamic_conversion(:currency,dcsa.csa_currency,dcsa.csa_docdate,(dcsa.csa_anticipate_total) - (dcsa.csa_paytoday) , get_localcur()  )) > 0 
        {INCLUDE_ORDER} ) as saldo";

        $complemento = " -- ORDENES DE COMPRA 
                          UNION ALL
                          SELECT  
                          dcpo.cpo_docentry as ac1_font_key,
                          dcpo.cpo_cardcode as codigo_proveedor,
                          get_acctp(:cardcode,2) as cuenta,
                          CURRENT_DATE - dcpo.cpo_duedate as dias_atrasado,
                          dcpo.cpo_comment as bpr_comment,
                          dcpo.cpo_currency,
                          dcpo.cpo_docentry as dvf_docentry,
                          dcpo.cpo_docnum ,
                          dcpo.cpo_docdate as fecha_doc,
                          dcpo.cpo_duedate as fecha_ven,
                          dcpo.cpo_docnum as id_origen,
                          dcpo.cpo_doctype as numtype,
                          mdt_docname as tipo,
                          dcpo.cpo_doctotal as total_doc,
                          dcpo.cpo_doctotal *-1 as saldo_venc,
                          '' retencion,
                          get_tax_currency(dcpo.cpo_currency,dcpo.cpo_docdate) as tasa_dia,
                          0 as sa1_linenum,
                          0 as ac1_cord
                          from dcpo
                          inner join dmdt on dmdt.mdt_doctype = dcpo.cpo_doctype
                          inner join responsestatus  on dcpo.cpo_doctype = responsestatus.tipo and dcpo.cpo_docentry = responsestatus.id 
                          where cpo_cardcode = :cardcode
                          and dcpo.business = :business
                          and responsestatus.estado = 'Abierto'";


        if ( $Data['order'] == 1 ) {
          $sql = str_replace('{INCLUDE_ORDER}', $complemento, $sql);
        }else{
          $sql = str_replace('{INCLUDE_ORDER}', '', $sql);
        }


        $resSql = $this->pedeo->queryTable($sql, array(":cardcode" => $Data['cardcode'], ":business" => $Data['business'], ":currency" => $Data['currency'] ));

        if ( isset($resSql[0])){
          $respuesta = array(
            'error' => false,
            'data' => $resSql,
            'mensaje' => 'OK'
          );
        }else{
          $respuesta = array(
            'error' => false,
            'data' => $resSql,
            'mensaje' => 'Busqueda sin resultados'
          );
        }
    
    }


    $this->response($respuesta);

  }

  // Obtener Socio de negocio por Id
  public function getBusinessPartnerByCardCode_get()
  {

    $Data = $this->get();

    if (!isset($Data['dms_card_code']) OR !isset($Data['dms_card_type'])) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = " SELECT * FROM dmsn WHERE dms_card_code = :dms_card_code AND dms_card_type = :dms_card_type";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dms_card_type' => $Data['dms_card_type'], "dms_card_code" => $Data['dms_card_code']));

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
        'mensaje'  => 'busqueda sin resultados'
      );
    }

    $this->response($respuesta);
  }
  
  // Socios de negocio paginados
  public function getBusinessPPaginate_post() {


    $request = $this->post();

    $statusCodes = ["0","1"];

    $variableSql = 'WHERE 1=1';

		if (isset($request['dms_card_code']) && !empty($request['dms_card_code'])) {

			$variableSql .= " AND dms_card_code LIKE '%" . $request['dms_card_code'] . "%'";
		}

		if (isset($request['dms_card_name']) &&  !empty($request['dms_card_name'])) {

			$variableSql .= " AND dms_card_name LIKE '%" . $request['dms_card_name'] . "%'";
		}   

    /**filtros del datatable */
    if(isset($request['slt_group_num']) &&  !empty($request['slt_group_num'])){
      $variableSql .= " AND dms_group_num = '".$request['slt_group_num']."'" ;
    }
    
    if(isset($request['slt_sales_num']) &&  !empty($request['slt_sales_num'])){
      $variableSql .= " AND dms_sip_code = ".$request['slt_sales_num'] ;
    }

    if(isset($request['slt_owner_num']) &&  !empty($request['slt_owner_num'])){
      $variableSql .= " AND dms_agent = ".$request['slt_owner_num'] ;
    }

    if(isset($request['slt_state_num']) && in_array($request['slt_state_num'],$statusCodes)){
        $variableSql .= " AND dms_enabled = ".$request['slt_state_num'] ;
       
    }

		// OBTENER NÚMERO DE REGISTROS DE LA TABLA.
		$numRows = $this->pedeo->queryTable("SELECT get_numrows('dmsn') as numrows", []);

		//COLUMNAS DEL DATATABLE
		$columns = array(
			'dms_card_code',
			'UPPER(dms_card_name)',
		);

		//
		if (!empty($request['search']['value'])) {
			// OBTENER CONDICIONALES.
			$variableSql .= " AND  " . self::get_Filter($columns, strtoupper($request['search']['value']));
		}
    
		$sqlSelect = "SELECT dmsn.*, dms_card_name ||' '|| concat(dms_card_last_name,'') as dms_na  FROM dmsn ".$variableSql;
		//
		$sqlSelect .=" LIMIT ".$request['length']." OFFSET ".$request['start'];
    
    $resSelect = $this->pedeo->queryTable($sqlSelect, array());


		$respuesta = array(
			'error' => false,
			'data'  => $resSelect,
			'rows'  => $numRows[0]['numrows'],
			'mensaje' => ''
		);

		$this->response($respuesta);

  }

  public function get_Filter($columns, $value)
	{
		//
		$resultSet = "";
		// CONDICIONAL.
		$where = " {campo} LIKE '%" . $value . "%' OR";
		//
		try {
			//
			foreach ($columns as $column) {
				// REEMPLAZAR CAMPO.
				$resultSet .= str_replace('{campo}', $column, $where);
			}
			// REMOVER ULTIMO OR DE LA CADENA.
			$resultSet = substr($resultSet, 0, -2);
		} catch (Exception $e) {
			$resultSet = $e->getMessage();
		}
		//
		return $resultSet;
	}


}
