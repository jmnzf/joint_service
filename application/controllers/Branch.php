<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Branch extends REST_Controller {

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

  //Crear nueva sucursal
	public function createBranch_post(){

      $DataBranch = $this->post();

      if(!isset($DataBranch['Pgs_CompanyID']) OR
         !isset($DataBranch['Pgs_NameSoc']) OR
         !isset($DataBranch['Pgs_SmallName']) OR
         !isset($DataBranch['Pgs_AddSoc']) OR
         !isset($DataBranch['Pgs_StateSoc']) OR
         !isset($DataBranch['Pgs_CitySoc']) OR
         !isset($DataBranch['Pgs_CouSoc']) OR
         !isset($DataBranch['Pgs_IdSoc']) OR
				 !isset($DataBranch['Pgs_IdType']) OR
         !isset($DataBranch['Pgs_WebSite']) OR
         !isset($DataBranch['Pgs_Phone1']) OR
         !isset($DataBranch['Pgs_Phone2']) OR
         !isset($DataBranch['Pgs_Cel']) OR
         !isset($DataBranch['Pgs_Mail'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }
      
      $sqlInsert = "INSERT INTO pges(pgs_company_id, pgs_name_soc, pgs_small_name, pgs_add_soc, pgs_state_soc, pgs_city_soc, pgs_cou_soc, pgs_id_soc, pgs_id_type, pgs_web_site, pgs_phone1, pgs_phone2, pgs_cel, pgs_mail)
                   VALUES(:Pgs_CompanyID,  :Pgs_NameSoc,  :Pgs_SmallName,  :Pgs_AddSoc,  :Pgs_StateSoc,  :Pgs_CitySoc,  :Pgs_CouSoc,  :Pgs_IdSoc, :Pgs_IdType,  :Pgs_WebSite, :Pgs_Phone1,  :Pgs_Phone2,  :Pgs_Cel,  :Pgs_Mail)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ':Pgs_CompanyID' => $DataBranch['Pgs_CompanyID'],
            ':Pgs_NameSoc'  => $DataBranch['Pgs_NameSoc'],
            ':Pgs_SmallName'  => $DataBranch['Pgs_SmallName'],
            ':Pgs_AddSoc'  => $DataBranch['Pgs_AddSoc'],
            ':Pgs_StateSoc'  => $DataBranch['Pgs_StateSoc'],
            ':Pgs_CitySoc'  => $DataBranch['Pgs_CitySoc'],
            ':Pgs_CouSoc'  => $DataBranch['Pgs_CouSoc'],
            ':Pgs_IdSoc'  => $DataBranch['Pgs_IdSoc'],
						':Pgs_IdType' => $DataBranch['Pgs_IdType'],
            ':Pgs_WebSite'  => $DataBranch['Pgs_WebSite'],
            ':Pgs_Phone1'  => $DataBranch['Pgs_Phone1'],
            ':Pgs_Phone2'  => $DataBranch['Pgs_Phone2'],
            ':Pgs_Cel'  => $DataBranch['Pgs_Cel'],
            ':Pgs_Mail'  => $DataBranch['Pgs_Mail']
      ));

      if($resInsert > 0 ){

            $respuesta = array(
              'error' => false,
              'data' => $resInsert,
              'mensaje' =>'Sucursal registrada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' => array(),
              'mensaje'	=> 'No se pudo registrar la sucursal'
            );

      }

       $this->response($respuesta);
	}

  //Actualizar sucursal
  public function updateBranch_post(){

      $DataBranch = $this->post();

      if(!isset($DataBranch['Pgs_CompanyID']) OR
         !isset($DataBranch['Pgs_NameSoc']) OR
         !isset($DataBranch['Pgs_SmallName']) OR
         !isset($DataBranch['Pgs_StateSoc']) OR
         !isset($DataBranch['Pgs_CitySoc']) OR
         !isset($DataBranch['Pgs_CouSoc']) OR
         !isset($DataBranch['Pgs_IdSoc']) OR
				 !isset($DataBranch['Pgs_IdType']) OR
         !isset($DataBranch['Pgs_WebSite']) OR
         !isset($DataBranch['Pgs_Phone1']) OR
         !isset($DataBranch['Pgs_Phone2']) OR
         !isset($DataBranch['Pgs_Cel']) OR
         !isset($DataBranch['Pgs_Mail']) OR
         !isset($DataBranch['Pgs_AddSoc']) OR
         !isset($DataBranch['Pgs_Id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE pges SET pgs_company_id = :Pgs_CompanyID, pgs_name_soc =  :Pgs_NameSoc, pgs_small_name = :Pgs_SmallName,
                    pgs_add_soc = :Pgs_AddSoc, pgs_state_soc = :Pgs_StateSoc, pgs_city_soc = :Pgs_CitySoc, pgs_cou_soc = :Pgs_CouSoc,
                    pgs_id_soc = :Pgs_IdSoc, pgs_id_type = :Pgs_IdType, pgs_web_site = :Pgs_WebSite, pgs_phone1 = :Pgs_Phone1, pgs_phone2 = :Pgs_Phone2,
                    pgs_cel = :Pgs_Cel, pgs_mail = :Pgs_Mail WHERE pgs_id = :Pgs_Id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':Pgs_CompanyID' => $DataBranch['Pgs_CompanyID'],
            ':Pgs_NameSoc'  => $DataBranch['Pgs_NameSoc'],
            ':Pgs_SmallName'  => $DataBranch['Pgs_SmallName'],
            ':Pgs_AddSoc'  => $DataBranch['Pgs_AddSoc'],
            ':Pgs_StateSoc'  => $DataBranch['Pgs_StateSoc'],
            ':Pgs_CitySoc'  => $DataBranch['Pgs_CitySoc'],
            ':Pgs_CouSoc'  => $DataBranch['Pgs_CouSoc'],
            ':Pgs_IdSoc'  => $DataBranch['Pgs_IdSoc'],
						':Pgs_IdType'  => $DataBranch['Pgs_IdType'],
            ':Pgs_WebSite'  => $DataBranch['Pgs_WebSite'],
            ':Pgs_Phone1'  => $DataBranch['Pgs_Phone1'],
            ':Pgs_Phone2'  => $DataBranch['Pgs_Phone2'],
            ':Pgs_Cel'  => $DataBranch['Pgs_Cel'],
            ':Pgs_Mail'  => $DataBranch['Pgs_Mail'],
            ':Pgs_Id' => $DataBranch['Pgs_Id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Sucursal actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la sucursal'
            );

      }

       $this->response($respuesta);
  }

  // Obtener Sucursales
  public function getBranch_get(){

        $sqlSelect = " SELECT * FROM pges";

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

  // Obtener Sucursal por Id
  public function getBranchById_get(){

        $Data = $this->get();

        if(!isset($Data['Pgs_Id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM pges WHERE pgs_company_id = :Pgs_Id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':Pgs_Id' => $Data['Pgs_Id']));

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

  //Actualiza el estado de una sucursal
   public function updateStatus_post(){

  	 			$Data = $this->post();

  				if(!isset($Data['Pgs_Id']) OR !isset($Data['Pgs_Enabled'])){

  						$respuesta = array(
  							'error' => true,
  							'data'  => array(),
  							'mensaje' =>'La informacion enviada no es valida'
  						);

  						$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
  						return;

  				}

  				$sqlUpdate = "UPDATE pges SET pgs_enabled = :Pgs_Enabled WHERE pgs_id = :Pgs_Id";


  				$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

  											':Pgs_Enabled' => $Data['Pgs_Enabled'],
  											':Pgs_Id' => $Data['Pgs_Id']

  				));


  				if(is_numeric($resUpdate) && $resUpdate == 1){

  							$respuesta = array(
  								'error'   => false,
  								'data'    => $resUpdate,
  								'mensaje' =>'Sucursal actualizada con exito'
  							);


  				}else{

  							$respuesta = array(
  								'error'   => true,
  								'data'    => $resUpdate,
  								'mensaje'	=> 'No se pudo actualizar la sucursal'
  							);

  				}

  				 $this->response($respuesta);
   }
}
