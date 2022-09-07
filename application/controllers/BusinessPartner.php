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

             ':dms_card_code' => isset($Data['dms_card_code'])?$Data['dms_card_code']:NULL,
             ':dms_card_name' => isset($Data['dms_card_name'])?$Data['dms_card_name']:NULL,
             ':dms_card_type' => isset($Data['dms_card_type'])?$Data['dms_card_type']:0,
             ':dms_short_name' => isset($Data['dms_short_name'])?$Data['dms_short_name']:NULL,
             ':dms_phone1' => isset($Data['dms_phone1'])?$Data['dms_phone1']:NULL,
             ':dms_phone2' => isset($Data['dms_phone2'])?$Data['dms_phone2']:NULL,
             ':dms_cel' => isset($Data['dms_cel'])?$Data['dms_cel']:NULL,
             ':dms_email' => isset($Data['dms_email'])?$Data['dms_email']:NULL,
             ':dms_inv_mail' => isset($Data['dms_inv_mail'])?$Data['dms_inv_mail']:NULL,
             ':dms_group_num' => isset($Data['dms_group_num'])?$Data['dms_group_num']:NULL,
             ':dms_web_site' => isset($Data['dms_web_site'])?$Data['dms_web_site']:NULL,
             ':dms_sip_code' => isset($Data['dms_sip_code'])?$Data['dms_sip_code']:NULL,
             ':dms_agent' => isset($Data['dms_agent'])?$Data['dms_agent']:NULL,
             ':dms_pay_type' => isset($Data['dms_pay_type'])?$Data['dms_pay_type']:NULL,
             ':dms_limit_cred' => is_numeric($Data['dms_limit_cred'])?$this->ValidarN($Data['dms_limit_cred']):NULL,
             ':dms_inter' => isset($Data['dms_inter'])?$Data['dms_inter']:NULL,
             ':dms_price_list' => isset($Data['dms_price_list'])?$Data['dms_price_list']:0,
             ':dms_acct_sn' => NULL,
             ':dms_acct_asn' => NULL,
             ':dms_card_last_name' => isset($Data['dms_card_last_name'])?$Data['dms_card_last_name']:NULL,
             ':dms_enabled' => isset($Data['dms_enabled'])?$Data['dms_enabled']:NULL,
						 ':dms_rtype' => isset($Data['dms_rtype'])?$Data['dms_rtype']:NULL,
						 ':dms_classtype' => isset($Data['dms_classtype'])?$Data['dms_classtype']:NULL



      ));

      if(is_numeric($resInsert) && $resInsert > 0){


						//SE VERIFCA SI TIENE RETECIONES Y SE AGREGAN A LA TABLA
						if(isset($Data['dms_rte'])){
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

      if(!isset($Data['dms_id'])){

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

            ':dms_card_code' => isset($Data['dms_card_code'])?$Data['dms_card_code']:NULL,
            ':dms_card_name' => isset($Data['dms_card_name'])?$Data['dms_card_name']:NULL,
            ':dms_card_type' => isset($Data['dms_card_type'])?$Data['dms_card_type']:0,
            ':dms_short_name' => isset($Data['dms_short_name'])?$Data['dms_short_name']:NULL,
            ':dms_phone1' => isset($Data['dms_phone1'])?$Data['dms_phone1']:NULL,
            ':dms_phone2' => isset($Data['dms_phone2'])?$Data['dms_phone2']:NULL,
            ':dms_cel' => isset($Data['dms_cel'])?$Data['dms_cel']:NULL,
            ':dms_email' => isset($Data['dms_email'])?$Data['dms_email']:NULL,
            ':dms_inv_mail' => isset($Data['dms_inv_mail'])?$Data['dms_inv_mail']:NULL,
            ':dms_group_num' => isset($Data['dms_group_num'])?$Data['dms_group_num']:NULL,
            ':dms_web_site' => isset($Data['dms_web_site'])?$Data['dms_web_site']:NULL,
            ':dms_sip_code' => isset($Data['dms_sip_code'])?$Data['dms_sip_code']:NULL,
            ':dms_agent' => isset($Data['dms_agent'])?$Data['dms_agent']:NULL,
            ':dms_pay_type' => isset($Data['dms_pay_type'])?$Data['dms_pay_type']:NULL,
            ':dms_limit_cred' => isset($Data['dms_limit_cred'])?$this->ValidarN($Data['dms_limit_cred']):NULL,
            ':dms_inter' => isset($Data['dms_inter'])?$Data['dms_inter']:NULL,
            ':dms_price_list' => isset($Data['dms_price_list'])?$Data['dms_price_list']:NULL,
            ':dms_acct_sn' => NULL,
            ':dms_acct_asn' => NULL,
            ':dms_card_last_name' => isset($Data['dms_card_last_name'])?$Data['dms_card_last_name']:NULL,
            ':dms_id' => $Data['dms_id'],
            ':dms_enabled' => isset($Data['dms_enabled'])?$Data['dms_enabled']:NULL,
						':dms_rtype' => isset($Data['dms_rtype'])?$Data['dms_rtype']:NULL,
						':dms_classtype' => isset($Data['dms_classtype'])?$Data['dms_classtype']:NULL
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

	//ACTUALIZAR DIRECCION DE SOCIO DE NEGOCIO
	//COMO LA PRINCIPAL
	public function updateAddress_post()
	{
		$Data = $this->post();

		if(!isset($Data['dmd_card_code']) OR !isset($Data['dmd_id'])){

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
				);

				$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
				return;
		}

    $resUpdate = NULL;

    $update = $this->pedeo->updateRow('UPDATE dmsd SET dmd_ppal = 0 WHERE dmd_card_code = :dmd_card_code',[':dmd_card_code' => $Data['dmd_card_code']]);

    if(is_numeric($update) && $update > 0){
      //
			$resUpdate = $this->pedeo->updateRow("UPDATE dmsd SET dmd_ppal = :dmd_ppal WHERE dmd_id = :dmd_id",
        array(
          ':dmd_ppal' => 1,
          ':dmd_id' => $Data['dmd_id']
        )
      );
		}

    $respuesta = array(
      'error'   => true,
      'data'    => $update,
      'mensaje'	=> 'No se pudo actualizar la dirreccion del socio de negocio'
    );

		if(is_numeric($resUpdate) && $resUpdate == 1){
      $respuesta = array(
        'error'   => false,
        'data'    => $resUpdate,
        'mensaje' =>'Se actualizó la dirreccion del socio de negocio'
      );
		}

		$this->response($respuesta);
	}
	//ACTUALIZAR DIRECCION DE SOCIO DE NEGOCIO
	//HABILITAR/DESABILITAR
	public function updateAddressStatus_post()
	{
		$Data = $this->post();

		if(!isset($Data['dmd_status']) OR !isset($Data['dmd_id'])){

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
				);

				$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
				return;
		}

    $update = $this->pedeo->updateRow('UPDATE dmsd SET dmd_status = :dmd_status WHERE dmd_id = :dmd_id',[':dmd_status' => $Data['dmd_status'], ':dmd_id' => $Data['dmd_id']]);

    $respuesta = array(
      'error'   => true,
      'data'    => $update,
      'mensaje'	=> 'No se pudo actualizar la dirreccion del socio de negocio'
    );

		if(is_numeric($update) && $update == 1){
      $respuesta = array(
        'error'   => false,
        'data'    => $update,
        'mensaje' =>'Se actualizó el estado de la dirreccion del socio de negocio'
      );
		}

		$this->response($respuesta);
	}
	//ACTUALIZAR CONTACTO DE SOCIO DE NEGOCIO
	//HABILITAR/DESABILITAR
	public function updateContactStatus_post()
	{
		$Data = $this->post();

		if(!isset($Data['dmc_status']) OR !isset($Data['dmc_id'])){

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
				);

				$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
				return;
		}

    $update = $this->pedeo->updateRow('UPDATE dmsc SET dmc_status = :dmc_status WHERE dmc_id = :dmc_id',[':dmc_status' => $Data['dmc_status'], ':dmc_id' => $Data['dmc_id']]);

    $respuesta = array(
      'error'   => true,
      'data'    => $update,
      'mensaje'	=> 'No se pudo actualizar el contacto del socio de negocio'
    );

		if(is_numeric($update) && $update == 1){
      $respuesta = array(
        'error'   => false,
        'data'    => $update,
        'mensaje' =>'Se actualizó el estado del contacto del socio de negocio'
      );
		}

		$this->response($respuesta);
	}

  //Crear nuevo socio de negocio
	public function createCompleteBusinessPartner_post(){

    $Data = $this->post();

    if(!isset($Data['dms_card_code']) OR
       !isset($Data['dms_card_name']) OR
       !isset($Data['dms_card_last_name']) OR
       !isset($Data['dms_card_type']) OR
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

           ':dms_card_code' => isset($Data['dms_card_code'])?$Data['dms_card_code']:NULL,
           ':dms_card_name' => isset($Data['dms_card_name'])?$Data['dms_card_name']:NULL,
           ':dms_card_type' => isset($Data['dms_card_type'])?$Data['dms_card_type']:0,
           ':dms_short_name' => isset($Data['dms_short_name'])?$Data['dms_short_name']:NULL,
           ':dms_phone1' => isset($Data['dms_phone1'])?$Data['dms_phone1']:NULL,
           ':dms_phone2' => isset($Data['dms_phone2'])?$Data['dms_phone2']:NULL,
           ':dms_cel' => isset($Data['dms_cel'])?$Data['dms_cel']:NULL,
           ':dms_email' => isset($Data['dms_email'])?$Data['dms_email']:NULL,
           ':dms_inv_mail' => isset($Data['dms_inv_mail'])?$Data['dms_inv_mail']:NULL,
           ':dms_group_num' => isset($Data['dms_group_num'])?$Data['dms_group_num']:NULL,
           ':dms_web_site' => isset($Data['dms_web_site'])?$Data['dms_web_site']:NULL,
           ':dms_sip_code' => isset($Data['dms_sip_code'])?$Data['dms_sip_code']:NULL,
           ':dms_agent' => isset($Data['dms_agent'])?$Data['dms_agent']:NULL,
           ':dms_pay_type' => isset($Data['dms_pay_type'])?$Data['dms_pay_type']:NULL,
           ':dms_limit_cred' => is_numeric($Data['dms_limit_cred'])?$this->ValidarN($Data['dms_limit_cred']):NULL,
           ':dms_inter' => isset($Data['dms_inter'])?$Data['dms_inter']:NULL,
           ':dms_price_list' => isset($Data['dms_price_list'])?$Data['dms_price_list']:0,
           ':dms_acct_sn' => NULL,
           ':dms_acct_asn' => NULL,
           ':dms_card_last_name' => isset($Data['dms_card_last_name'])?$Data['dms_card_last_name']:NULL,
           ':dms_enabled' => isset($Data['dms_enabled'])?$Data['dms_enabled']:NULL,
           ':dms_rtype' => isset($Data['dms_rtype'])?$Data['dms_rtype']:NULL,
           ':dms_classtype' => isset($Data['dms_classtype'])?$Data['dms_classtype']:NULL



    ));

    if(is_numeric($resInsert) && $resInsert > 0){


          //SE VERIFCA SI TIENE RETECIONES Y SE AGREGAN A LA TABLA
          if(isset($Data['dms_rte'])){
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
          }

          $sqlInsertAdd = "INSERT INTO dmsd(dmd_state_mm, dmd_state, dmd_id_add, dmd_delivery_add, dmd_country, dmd_city,
          dmd_card_code, dmd_adress, dmd_tonw)VALUES(:dmd_state_mm, :dmd_state, :dmd_id_add, :dmd_delivery_add,
          :dmd_country, :dmd_city, :dmd_card_code, :dmd_adress, :dmd_tonw)";

          $resInsertAdd = $this->pedeo->insertRow($sqlInsertAdd, array(

            ':dmd_state_mm' => $Data['dmd_state_mm'],
            ':dmd_state' => $Data['dmd_state'],
            ':dmd_id_add' => $Data['dmd_id_add'],
            ':dmd_delivery_add' => $Data['dmd_delivery_add'],
            ':dmd_country' => $Data['dmd_country'],
            ':dmd_city' => $Data['dmd_city'],
            ':dmd_card_code' => $Data['dms_card_code'],
            ':dmd_adress' => $Data['dmd_adress'],
            ':dmd_tonw' => $Data['dmd_tonw']

          ));

          if (is_numeric($resInsertAdd) && $resInsertAdd > 0) {
            # code...
          }else{
            $this->pedeo->trans_rollback();

            $respuesta = array(
              'error'   => true,
              'data' => $resInsertAdd,
              'mensaje'	=> 'No se pudo registrar la direccion para el tercero actual'
            );


            $this->response($respuesta);

            return;
          }
          //FIN DEL PROCEDIMIENTO PARA AGREGAR LAS RETENCIONES

          $this->pedeo->trans_commit();

          $fixrate = $this->pedeo->queryTable("SELECT getfixrate() AS fixrate",array());

          $respuesta = array(
            'error' => false,
            'data' => $fixrate,
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

	private function ValidarN($dato){

		if( is_numeric( $dato ) ){
			return $dato;
		}else{
			return 0;
		}
	}


}
