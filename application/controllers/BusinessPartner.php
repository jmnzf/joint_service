<?php
//  SOCIOS DE NEGOCIO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class BusinessPartner extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);

	}

  //Crear nuevo socio de negocio
	public function createBusinessPartner_post(){

      $Data = $this->post();

      if(!isset($Data['dms_card_code']) OR
         !isset($Data['dms_card_name']) OR
         !isset($Data['dms_card_last_name']) OR
         !isset($Data['dms_card_type']) OR
         !isset($Data['dms_short_name']) OR
         !isset($Data['dms_phone1']) OR
         !isset($Data['dms_phone2']) OR
         !isset($Data['dms_cel']) OR
         !isset($Data['dms_email']) OR
				 !isset($Data['dms_inv_mail']) OR
         !isset($Data['dms_group_num']) OR
         !isset($Data['dms_web_site']) OR
         !isset($Data['dms_sip_code']) OR
         !isset($Data['dms_agent']) OR
         !isset($Data['dms_pay_type']) OR
         !isset($Data['dms_limit_cred']) OR
         !isset($Data['dms_inter']) OR
         !isset($Data['dms_price_list']) OR
         !isset($Data['dms_enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlSelect = "SELECT dms_card_code, dms_card_type FROM dmsn WHERE dms_card_code = :dms_card_code AND dms_card_type = :dms_card_type";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(

          ':dms_card_code' => $Data['dms_card_code'],
					':dms_card_type' => $Data['dms_card_type']

      ));

      if(isset($resSelect[0])){

          $respuesta = array(
            'error' => true,
            'data'  => array($Data['dms_card_code'], $Data['dms_card_type']),
            'mensaje' => 'ya existe un socio negocio con la combinación del codigo y tipo de codigo');

          $this->response($respuesta);

          return;

      }



			$this->pedeo->trans_begin();


      $sqlInsert = "INSERT INTO dmsn(dms_card_code, dms_card_name, dms_card_type, dms_short_name, dms_phone1, dms_phone2,
                    dms_cel, dms_email, dms_inv_mail, dms_group_num, dms_web_site, dms_sip_code, dms_agent, dms_pay_type,
                    dms_limit_cred, dms_inter, dms_price_list, dms_acct_sn, dms_acct_asn, dms_card_last_name, dms_enabled,
										dms_rtype,dms_classtype)
	                  VALUES (:dms_card_code, :dms_card_name, :dms_card_type, :dms_short_name, :dms_phone1, :dms_phone2,
                    :dms_cel, :dms_email, :dms_inv_mail, :dms_group_num, :dms_web_site, :dms_sip_code, :dms_agent, :dms_pay_type,
                    :dms_limit_cred, :dms_inter, :dms_price_list, :dms_acct_sn, :dms_acct_asn, :dms_card_last_name, :dms_enabled,
										:dms_rtype,:dms_classtype)";

      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

             ':dms_card_code' => $Data['dms_card_code'],
             ':dms_card_name' => $Data['dms_card_name'],
             ':dms_card_type' => $Data['dms_card_type'],
             ':dms_short_name' => $Data['dms_short_name'],
             ':dms_phone1' => $Data['dms_phone1'],
             ':dms_phone2' => $Data['dms_phone2'],
             ':dms_cel' => $Data['dms_cel'],
             ':dms_email' => $Data['dms_email'],
             ':dms_inv_mail' => $Data['dms_inv_mail'],
             ':dms_group_num' => $Data['dms_group_num'],
             ':dms_web_site' => $Data['dms_web_site'],
             ':dms_sip_code' => $Data['dms_sip_code'],
             ':dms_agent' => $Data['dms_agent'],
             ':dms_pay_type' => $Data['dms_pay_type'],
             ':dms_limit_cred' => $Data['dms_limit_cred'],
             ':dms_inter' => $Data['dms_inter'],
             ':dms_price_list' => $Data['dms_price_list'],
             ':dms_acct_sn' => NULL,
             ':dms_acct_asn' => NULL,
             ':dms_card_last_name' => $Data['dms_card_last_name'],
             ':dms_enabled' => $Data['dms_enabled'],
						 ':dms_rtype' => $Data['dms_rtype'],
						 ':dms_classtype' => $Data['dms_classtype']



      ));

      if(is_numeric($resInsert) && $resInsert > 0){


						//SE VERIFCA SI TIENE RETECIONES Y SE AGREGAN A LA TABLA

						$retenciones = is_array( $Data['dms_rte'] ) ?$Data['dms_rte'] : array();


						if( count($retenciones) > 0){

								foreach ($retenciones as $key => $value) {

											$resInsertRetenciones = $this->pedeo->insertRow('INSERT INTO rtsn(tsn_cardcode, tsn_type, tsn_rtid)VALUES(:tsn_cardcode, :tsn_type, :tsn_rtid)',array(
														':tsn_cardcode' => $Data['dms_card_code'],
														':tsn_type'     => $Data['dms_card_type'],
														':tsn_rtid' 		=> $value
											));

											if(is_numeric($resInsertRetenciones) && $resInsertRetenciones > 0){

											}else{

													  $this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data' => $resInsertRetenciones,
															'mensaje'	=> 'No se pudo registrar la retencion para el tercero actual'
														);


														$this->response($respuesta);

														return;
											}

								}
						}

						//FIN DEL PROCEDIMIENTO PARA AGREGAR LAS RETENCIONES

						$this->pedeo->trans_commit();


            $respuesta = array(
              'error' => false,
              'data' => $resInsert,
              'mensaje' =>'Socio de negocio registrado con exito'
            );


      }else{

						$this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data' 		=> $resInsert,
              'mensaje'	=> 'No se pudo registrar el socio de negocio'
            );

      }

       $this->response($respuesta);
	}

  //Actualizar socio de negocio
  public function updateBusinessPartner_post(){

      $Data = $this->post();

      if(!isset($Data['dms_card_code']) OR
         !isset($Data['dms_card_name']) OR
         !isset($Data['dms_card_last_name']) OR
         !isset($Data['dms_card_type']) OR
         !isset($Data['dms_short_name']) OR
         !isset($Data['dms_phone1']) OR
         !isset($Data['dms_phone2']) OR
         !isset($Data['dms_cel']) OR
         !isset($Data['dms_email']) OR
         !isset($Data['dms_inv_mail']) OR
         !isset($Data['dms_group_num']) OR
         !isset($Data['dms_web_site']) OR
         !isset($Data['dms_sip_code']) OR
         !isset($Data['dms_agent']) OR
         !isset($Data['dms_pay_type']) OR
         !isset($Data['dms_limit_cred']) OR
         !isset($Data['dms_inter']) OR
         !isset($Data['dms_price_list']) OR
         !isset($Data['dms_enabled']) OR
         !isset($Data['dms_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
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


      if(isset($resSelect[0])){

          $respuesta = array(
            'error' => true,
            'data'  => array($Data['dms_card_code'], $Data['dms_card_type']),
            'mensaje' => 'ya existe un socio negocio con la combinación del codigo y tipo de codigo, no es posible actualizar ');

          $this->response($respuesta);

          return;

      }

      $sqlUpdate = "UPDATE dmsn SET dms_enabled = :dms_enabled, dms_card_code = :dms_card_code, dms_card_name = :dms_card_name, dms_card_type = :dms_card_type,
                    dms_short_name = :dms_short_name, dms_phone1 = :dms_phone1, dms_phone2 = :dms_phone2, dms_cel = :dms_cel,
                    dms_email = :dms_email, dms_inv_mail = :dms_inv_mail, dms_group_num = :dms_group_num, dms_web_site = :dms_web_site,
                    dms_sip_code = :dms_sip_code, dms_agent = :dms_agent, dms_pay_type = :dms_pay_type, dms_limit_cred = :dms_limit_cred,
                    dms_inter = :dms_inter, dms_price_list = :dms_price_list, dms_acct_sn = :dms_acct_sn, dms_acct_asn = :dms_acct_asn , dms_card_last_name = :dms_card_last_name,
									  dms_rtype = :dms_rtype, dms_classtype = :dms_classtype  WHERE dms_id = :dms_id";

			$this->pedeo->trans_begin();

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':dms_card_code' => $Data['dms_card_code'],
            ':dms_card_name' => $Data['dms_card_name'],
            ':dms_card_type' => $Data['dms_card_type'],
            ':dms_short_name' => $Data['dms_short_name'],
            ':dms_phone1' => $Data['dms_phone1'],
            ':dms_phone2' => $Data['dms_phone2'],
            ':dms_cel' => $Data['dms_cel'],
            ':dms_email' => $Data['dms_email'],
            ':dms_inv_mail' => $Data['dms_inv_mail'],
            ':dms_group_num' => $Data['dms_group_num'],
            ':dms_web_site' => $Data['dms_web_site'],
            ':dms_sip_code' => $Data['dms_sip_code'],
            ':dms_agent' => $Data['dms_agent'],
            ':dms_pay_type' => $Data['dms_pay_type'],
            ':dms_limit_cred' => $Data['dms_limit_cred'],
            ':dms_inter' => $Data['dms_inter'],
            ':dms_price_list' => $Data['dms_price_list'],
            ':dms_acct_sn' => NULL,
            ':dms_acct_asn' => NULL,
            ':dms_card_last_name' => $Data['dms_card_last_name'],
            ':dms_id' => $Data['dms_id'],
            ':dms_enabled' => $Data['dms_enabled'],
						':dms_rtype' => $Data['dms_rtype'],
						':dms_classtype' => $Data['dms_classtype']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){


						//SE VERIFCA SI TIENE RETECIONES Y SE AGREGAN A LA TABLA

						$retenciones = (isset($Data['dms_rte']) && is_array( $Data['dms_rte'] )) ?$Data['dms_rte'] : array();


						if( count($retenciones) > 0){

								$this->pedeo->queryTable('DELETE FROM rtsn WHERE tsn_cardcode = :tsn_cardcode AND  tsn_type = :tsn_type', array(':tsn_cardcode' => $Data['dms_card_code'] , ':tsn_type' => $Data['dms_card_type']));

								foreach ($retenciones as $key => $value) {

											$resInsertRetenciones = $this->pedeo->insertRow('INSERT INTO rtsn(tsn_cardcode, tsn_type, tsn_rtid)VALUES(:tsn_cardcode, :tsn_type, :tsn_rtid)',array(
														':tsn_cardcode' => $Data['dms_card_code'],
														':tsn_type'     => $Data['dms_card_type'],
														':tsn_rtid' 		=> $value
											));

											if(is_numeric($resInsertRetenciones) && $resInsertRetenciones > 0){

											}else{

														$this->pedeo->trans_rollback();

														$respuesta = array(
															'error'   => true,
															'data' => $resInsertRetenciones,
															'mensaje'	=> 'No se pudo registrar la retencion para el tercero actual'
														);


														$this->response($respuesta);

														return;
											}

								}
						}

						//FIN DEL PROCEDIMIENTO PARA AGREGAR LAS RETENCIONES

						$this->pedeo->trans_commit();

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Socio de negocio actualizado con exito'
            );


      }else{

						$this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el socio de negocio '
            );

      }

       $this->response($respuesta);
  }

	//Actualiza el estado de un socio de negocio
	 public function updateStatus_post(){

					$Data = $this->post();

					if(!isset($Data['dms_id']) OR !isset($Data['dms_enabled'])){

							$respuesta = array(
								'error' => true,
								'data'  => array(),
								'mensaje' =>'La informacion enviada no es valida'
							);

							$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
							return;
					}

					$sqlUpdate = "UPDATE dmsn SET dms_enabled = :dms_enabled WHERE dms_id = :dms_id";


					$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

												':dms_enabled' => $Data['dms_enabled'],
												':dms_id' => $Data['dms_id']

					));


					if(is_numeric($resUpdate) && $resUpdate == 1){

								$respuesta = array(
									'error'   => false,
									'data'    => $resUpdate,
									'mensaje' =>'Se actualizó el estado del socio de negocio'
								);


					}else{

								$respuesta = array(
									'error'   => true,
									'data'    => $resUpdate,
									'mensaje'	=> 'No se pudo actualizar el estado del socio de negocio'
								);

					}

					 $this->response($respuesta);

	 }

  // Obtener Socios de negocio
  public function getBusinessPartner_get(){

        $sqlSelect = " SELECT * FROM dmsn";

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
              'mensaje'	=> 'busqueda sin resultados'
            );

        }

         $this->response($respuesta);
  }

  // Obtener Socio de negocio por Id
  public function getBusinessPartnerById_get(){

        $Data = $this->get();

        if(!isset($Data['dms_id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmsn WHERE dms_id = :dms_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dms_id' => $Data['dms_id']));

        if(isset($resSelect[0])){

          $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => '');

        }else{

            $respuesta = array(
              'error'   => true,
              'data' => array(),
              'mensaje'	=> 'busqueda sin resultados'
            );

        }

         $this->response($respuesta);
  }

	// OBTENER RETENCIONES POR SOCIO DE NEGOCIO
	public function getRetencionesBYSN_get(){

			$Data = $this->get();
			$ret = array();

			if(!isset($Data['tsn_cardcode']) OR !isset($Data['tsn_type'])){

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}

			$sqlSelect = "SELECT *
										FROM rtsn
										WHERE tsn_cardcode = :tsn_cardcode
										AND tsn_type  = :tsn_type";

			$resSelect = $this->pedeo->queryTable($sqlSelect, array(':tsn_cardcode' => $Data['tsn_cardcode'], ':tsn_type' => $Data['tsn_type']));

			if(isset($resSelect[0])){

				foreach ($resSelect as $key => $value) {

					 	array_push($ret, $value['tsn_rtid']);
				}

				$respuesta = array(
					'error' => false,
					'data'  => $ret,
					'mensaje' => '');

			}else{

					$respuesta = array(
						'error'   => true,
						'data' => array(),
						'mensaje'	=> 'busqueda sin resultados'
					);

			}

			 $this->response($respuesta);
	}




}
