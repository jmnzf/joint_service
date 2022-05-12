<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Business extends REST_Controller {

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

   //Crear nueva empresa
	public function createCompany_post(){

      $DataCompany = $this->post();

      if(!isset($DataCompany['Pge_NameSoc']) OR
         !isset($DataCompany['Pge_SmallName']) OR
         !isset($DataCompany['Pge_AddSoc']) OR
         !isset($DataCompany['Pge_StateSoc']) OR
         !isset($DataCompany['Pge_CitySoc']) OR
         !isset($DataCompany['Pge_CouSoc']) OR
         !isset($DataCompany['Pge_IdType']) OR
         !isset($DataCompany['Pge_IdSoc']) OR
         !isset($DataCompany['Pge_WebSite']) OR
         !isset($DataCompany['Pge_Phone1']) OR
         !isset($DataCompany['Pge_Phone2']) OR
         !isset($DataCompany['Pge_Cel']) OR
         !isset($DataCompany['Pge_Branch']) OR
         !isset($DataCompany['Pge_Mail']) OR
         !isset($DataCompany['Pge_CouBank']) OR
         !isset($DataCompany['Pge_BankDef']) OR
         !isset($DataCompany['Pge_BankAcct']) OR
         !isset($DataCompany['Pge_AccType'])){

         $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
         );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlInsert = "INSERT INTO pgem(pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc, pge_cou_soc, pge_id_type, pge_id_soc, pge_web_site, pge_phone1, pge_phone2, pge_cel, pge_branch, pge_mail, pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def, pge_bank_acct, pge_acc_type, pge_bridge_inv,pge_acc_dcp, pge_acc_dcn,pge_acc_ajp)
                   VALUES(:Pge_NameSoc, :Pge_SmallName, :Pge_AddSoc, :Pge_StateSoc, :Pge_CitySoc, :Pge_CouSoc, :Pge_IdType, :Pge_IdSoc, :Pge_WebSite, :Pge_Phone1, :Pge_Phone2, :Pge_Cel, :Pge_Branch, :Pge_Mail, :Pge_CurrFirst, :Pge_CurrSys, :Pge_CouBank, :Pge_BankDef, :Pge_BankAcct, :Pge_AccType, :pge_bridge_inv,:pge_acc_dcp, :pge_acc_dcn,:pge_acc_ajp)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(
         ':Pge_NameSoc' => $DataCompany['Pge_NameSoc'],
         ':Pge_SmallName' => $DataCompany['Pge_SmallName'],
         ':Pge_AddSoc' => $DataCompany['Pge_AddSoc'],
         ':Pge_StateSoc' => $DataCompany['Pge_StateSoc'],
         ':Pge_CitySoc' => $DataCompany['Pge_CitySoc'],
         ':Pge_CouSoc' => $DataCompany['Pge_CouSoc'],
         ':Pge_IdType' => $DataCompany['Pge_IdType'],
         ':Pge_IdSoc' => $DataCompany['Pge_IdSoc'],
         ':Pge_WebSite' => $DataCompany['Pge_WebSite'],
         ':Pge_Phone1' => $DataCompany['Pge_Phone1'],
         ':Pge_Phone2' => $DataCompany['Pge_Phone2'],
         ':Pge_Cel' => $DataCompany['Pge_Cel'],
         ':Pge_Branch' => $DataCompany['Pge_Branch'],
         ':Pge_Mail' => $DataCompany['Pge_Mail'],
         ':Pge_CurrFirst' => $DataCompany['Pge_CurrFirst'],
         ':Pge_CurrSys' => $DataCompany['Pge_CurrSys'],
         ':Pge_CouBank' => $DataCompany['Pge_CouBank'],
         ':Pge_BankDef' => $DataCompany['Pge_BankDef'],
         ':Pge_BankAcct' => $DataCompany['Pge_BankAcct'],
         ':Pge_AccType' => $DataCompany['Pge_AccType'],
				 ':pge_bridge_inv' => $DataCompany['pge_bridge_inv'],
				 ':pge_acc_dcp' => $DataCompany['pge_acc_dcp'],
				 ':pge_acc_dcn' => $DataCompany['pge_acc_dcn'],
				 ':pge_acc_ajp' => is_numeric($DataCompany['pge_acc_ajp'])?$DataCompany['pge_acc_ajp']:NULL
      ));


      if(is_numeric($resInsert) && $resInsert > 0){
         $respuesta = array(
           'error'	 => false,
           'data' 	 => $resInsert,
           'mensaje' =>'Empresa registrada con exito'
         );
      }else{

				$respuesta = array(
					'error'   => true,
					'data' 		=> $resInsert,
					'mensaje' => 'No se pudo registrar la empresa'
				);

			}

      $this->response($respuesta);
	}

  //Actualizar Datos de Empresa
  public function updateCompany_post(){

      $DataCompany = $this->post();

      if(!isset($DataCompany['Pge_Id']) OR
         !isset($DataCompany['Pge_NameSoc']) OR
         !isset($DataCompany['Pge_SmallName']) OR
         !isset($DataCompany['Pge_AddSoc']) OR
         !isset($DataCompany['Pge_StateSoc']) OR
         !isset($DataCompany['Pge_CitySoc']) OR
         !isset($DataCompany['Pge_CouSoc']) OR
         !isset($DataCompany['Pge_IdType']) OR
         !isset($DataCompany['Pge_IdSoc']) OR
         !isset($DataCompany['Pge_WebSite']) OR
         !isset($DataCompany['Pge_Phone1']) OR
         !isset($DataCompany['Pge_Phone2']) OR
         !isset($DataCompany['Pge_Cel']) OR
         !isset($DataCompany['Pge_Branch']) OR
         !isset($DataCompany['Pge_Mail']) OR
         !isset($DataCompany['Pge_CouBank']) OR
         !isset($DataCompany['Pge_BankDef']) OR
         !isset($DataCompany['Pge_BankAcct']) OR
         !isset($DataCompany['Pge_AccType'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE pgem SET pge_name_soc = :Pge_NameSoc, pge_small_name = :Pge_SmallName, pge_add_soc = :Pge_AddSoc,
                    pge_state_soc = :Pge_StateSoc, pge_city_soc = :Pge_CitySoc, pge_cou_soc = :Pge_CouSoc, pge_id_type = :Pge_IdType,
                    pge_id_soc = :Pge_IdSoc,  pge_web_site = :Pge_WebSite, pge_phone1 = :Pge_Phone1,
                    pge_phone2 = :Pge_Phone2, pge_cel = :Pge_Cel, pge_branch = :Pge_Branch, pge_mail = :Pge_Mail,pge_cou_bank = :Pge_CouBank, pge_bank_def = :Pge_BankDef, pge_bank_acct = :Pge_BankAcct,
                    pge_acc_type = :Pge_AccType, pge_bridge_inv = :pge_bridge_inv, pge_acc_dcp = :pge_acc_dcp, pge_acc_dcn = :pge_acc_dcn, pge_acc_ajp = :pge_acc_ajp WHERE pge_id = :Pge_Id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':Pge_NameSoc' => $DataCompany['Pge_NameSoc'],
            ':Pge_SmallName' => $DataCompany['Pge_SmallName'],
            ':Pge_AddSoc' => $DataCompany['Pge_AddSoc'],
            ':Pge_StateSoc' => $DataCompany['Pge_StateSoc'],
            ':Pge_CitySoc' => $DataCompany['Pge_CitySoc'],
            ':Pge_CouSoc' => $DataCompany['Pge_CouSoc'],
            ':Pge_IdType' => $DataCompany['Pge_IdType'],
            ':Pge_IdSoc' => $DataCompany['Pge_IdSoc'],
            ':Pge_WebSite' => $DataCompany['Pge_WebSite'],
            ':Pge_Phone1' => $DataCompany['Pge_Phone1'],
            ':Pge_Phone2' => $DataCompany['Pge_Phone2'],
            ':Pge_Cel' => $DataCompany['Pge_Cel'],
            ':Pge_Branch' => $DataCompany['Pge_Branch'],
            ':Pge_Mail' => $DataCompany['Pge_Mail'],
            ':Pge_CouBank' => $DataCompany['Pge_CouBank'],
            ':Pge_BankDef' => $DataCompany['Pge_BankDef'],
            ':Pge_BankAcct' => $DataCompany['Pge_BankAcct'],
            ':Pge_AccType' => $DataCompany['Pge_AccType'],
            ':Pge_Id'  => $DataCompany['Pge_Id'],
						':pge_bridge_inv'  => $DataCompany['pge_bridge_inv'],
						':pge_acc_dcp' => $DataCompany['pge_acc_dcp'],
						':pge_acc_dcn' => $DataCompany['pge_acc_dcn'],
						':pge_acc_ajp' => is_numeric($DataCompany['pge_acc_ajp'])?$DataCompany['pge_acc_ajp']:NULL
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Empresa actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la empresa'
            );

      }

       $this->response($respuesta);
  }

  // Obtener empresas
  public function getCompany_get(){

        $sqlSelect = "SELECT * FROM pgem";

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

//Obtener datos empresa por id de la empresa
  public function getCompanyById_get(){

        $Data = $this->get();

        if(!isset($Data['Pge_Id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM pgem WHERE pge_id = :Pge_Id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':Pge_Id' => $Data['Pge_Id']));

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


	//Actualizar Logo Empresa
  public function updateLogo_post(){

        $Data = $this->post();

        if(!isset($Data['pge_logo']) || !isset($Data['pge_id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
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


				try {
					//
					$sqlUpdate = "UPDATE pgem SET pge_logo = :pge_logo WHERE pge_id = :pge_id";

					$carpeta = $resMainFolder[0]['main_folder'];

					$ruta = '/var/www/html/'.$carpeta.'/assets/img/';

					$nombreArchivo = "logo".".jpeg";

					$data = $Data['pge_logo'];

					if ($data == NULL){

						$respuesta = array(
							'error'   => true,
							'data'    => [],
							'mensaje' => 'String Base64 no valido'
						);

						 $this->response($respuesta);
						 return;


					}

					if (!base64_decode($data, true) ){
						$respuesta = array(
							'error'   => true,
							'data'    => [],
							'mensaje' => 'String Base64 no valido'
						);

						 $this->response($respuesta);
						 return;
					}


					touch($ruta.$nombreArchivo);

					$file = fopen($ruta.$nombreArchivo,"wb");

				  if(!empty($Data['pge_logo'])){

						fwrite($file, base64_decode($Data['pge_logo']));

						fclose($file);

						$url = "assets/img/".$nombreArchivo;

						$resUpdate = $this->pedeo->updateRow($sqlUpdate,array(

								":pge_logo" => $url,
								":pge_id"   => $Data['pge_id']

						));

						if(is_numeric($resUpdate) && $resUpdate == 1){

							$respuesta = array(
								'error'   => false,
								'data'    => $resUpdate,
								'mensaje' => "actualización exitosa" );

						}else{

								$respuesta = array(
									'error'   => true,
									'data'    => $resUpdate,
									'mensaje'	=> "no se pudo actualizar la información"
								);

						}

					}else{

						$respuesta = array(
							'error'   => true,
							'data'    => [],
							'mensaje' => 'no se encontro una imagen valida'
						);

						 $this->response($respuesta);
						 return;
					}

				} catch (Exception $e) {
					$respuesta = array(
						'error'   => true,
						'data'    => [],
						'mensaje' => $e->getMessage()
					);

					 $this->response($respuesta);
					 return;

				}

         $this->response($respuesta);
  }


}
