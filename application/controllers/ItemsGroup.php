<?php
// DATOS LISTA DE PRECIOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class ItemsGroup extends REST_Controller {

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

  //CREAR NUEVA LISTA DE PRECIO
	public function createItemsGroup_post(){

      $Data = $this->post();

      if(!isset($Data['mga_code']) OR
         !isset($Data['mga_name']) OR
         !isset($Data['mga_acctin']) OR
         !isset($Data['mga_acct_out']) OR
         !isset($Data['mga_acct_inv']) OR
         !isset($Data['mga_acct_stockp']) OR
         !isset($Data['mga_acct_stockn']) OR
         !isset($Data['mga_acct_redu']) OR
         !isset($Data['mga_acct_amp']) OR
         !isset($Data['mga_acct_cost']) OR
         !isset($Data['mga_enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }




      $sqlSelect = "SELECT mga_code FROM dmga WHERE mga_code = :mga_code";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(

          ':mga_code' => $Data['mga_code']

      ));


      if(isset($resSelect[0])){

        $respuesta = array(
          'error' => true,
          'data'  => array($Data['mga_code'], $Data['mga_code']),
          'mensaje' => 'ya existe un grupo con ese código');

        $this->response($respuesta);

        return;

    }


        $sqlInsert = "INSERT INTO dmga (mga_code, mga_name, mga_acctin, mga_acct_out, mga_acct_inv, mga_acct_stockn, mga_acct_stockp, mga_acct_redu, mga_acct_amp, mga_acct_cost, mga_enabled)
                      VALUES(:mga_code, :mga_name, :mga_acctin, :mga_acct_out, :mga_acct_inv, :mga_acct_stockn, :mga_acct_stockp, :mga_acct_redu, :mga_acct_amp, :mga_acct_cost, :mga_enabled)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(

              ':mga_code'    => $Data['mga_code'],
              ':mga_name'    => $Data['mga_name'],
              ':mga_acctin'    => $Data['mga_acctin'],
              ':mga_acct_out'    => $Data['mga_acct_out'],
              ':mga_acct_inv'    => $Data['mga_acct_inv'],
              ':mga_acct_stockn'    => $Data['mga_acct_stockn'],
              ':mga_acct_stockp'    => $Data['mga_acct_stockp'],
              ':mga_acct_redu'    => $Data['mga_acct_redu'],
              ':mga_acct_amp'  => $Data['mga_acct_amp'],
              ':mga_acct_cost'  => $Data['mga_acct_cost'],
              ':mga_enabled'  => $Data['mga_enabled']
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

            $respuesta = array(
              'error'		=> false,
              'data' 		=> $resInsert,
              'mensaje' =>'Grupo de articulo registrado con exito'
            );

         }else{

           $respuesta = array(
             'error'   => true,
             'data' 	 => $resInsert,
             'mensaje' => 'No se pudo registrar el grupo de articulo'
           );

         }

         $this->response($respuesta);

 }

 //crear familia de articulo
 public function createItemsFamily_post(){

		 $Data = $this->post();

		 if(!isset($Data['mfa_pref']) OR
				!isset($Data['mfa_name']) or
				!isset($Data['mfa_gid'])){

			 $respuesta = array(
				 'error' => true,
				 'data'  => array(),
				 'mensaje' =>'La informacion enviada no es valida'
			 );

			 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			 return;
		 }




		 $sqlSelect = "SELECT mfa_pref FROM dmfa WHERE mfa_pref = :mfa_pref";

		 $resSelect = $this->pedeo->queryTable($sqlSelect, array(

				 ':mfa_pref' => $Data['mfa_pref']

		 ));


		 if(isset($resSelect[0])){

			 $respuesta = array(
				 'error' => true,
				 'data'  => array($Data['mfa_pref'], $Data['mfa_pref']),
				 'mensaje' => 'ya existe un grupo con ese código');

			 $this->response($respuesta);

			 return;

	 }


			 $sqlInsert = "INSERT INTO dmfa (mfa_pref, mfa_name,mfa_gcode)
										 VALUES(:mfa_pref, :mfa_name,:mfa_gcode)";

			 $resInsert = $this->pedeo->insertRow($sqlInsert, array(

						 ':mfa_pref'    => trim($Data['mfa_pref']),
						 ':mfa_name'    => trim($Data['mfa_name']),
						 ':mfa_gcode'			=> trim($Data['mfa_gid'])

			 ));

			 if(is_numeric($resInsert) && $resInsert > 0){

					 $respuesta = array(
						 'error'		=> false,
						 'data' 		=> $resInsert,
						 'mensaje' =>'Familia de articulo registrado con exito'
					 );

				}else{

					$respuesta = array(
						'error'   => true,
						'data' 	 => $resInsert,
						'mensaje' => 'No se pudo registrar la familia de articulo'
					);

				}

				$this->response($respuesta);

}




  //ACTUALIZAR LISTA DE PRECIOS
  public function updateItemGroup_post(){

      $Data = $this->post();

      if(!isset($Data['mga_code']) OR
         !isset($Data['mga_name']) OR
         !isset($Data['mga_acctin']) OR
         !isset($Data['mga_acct_out']) OR
         !isset($Data['mga_acct_inv']) OR
         !isset($Data['mga_acct_stockp']) OR
         !isset($Data['mga_acct_stockn']) OR
         !isset($Data['mga_acct_redu']) OR
         !isset($Data['mga_acct_amp']) OR
         !isset($Data['mga_acct_cost']) OR
         !isset($Data['mga_id']) OR
         !isset($Data['mga_enabled'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


      $sqlUpdate = "UPDATE dmga SET mga_code = :mga_code,
                                    mga_name = :mga_name,
                                    mga_acctin = :mga_acctin,
                                    mga_acct_out = :mga_acct_out,
                                    mga_acct_inv = :mga_acct_inv,
                                    mga_acct_stockn = :mga_acct_stockn,
                                    mga_acct_stockp = :mga_acct_stockp,
                                    mga_acct_redu = :mga_acct_redu,
                                    mga_acct_amp = :mga_acct_amp,
                                    mga_acct_cost = :mga_acct_cost,
                                    mga_enabled = :mga_enabled
                                    WHERE mga_id = :mga_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(


        ':mga_code'    => $Data['mga_code'],
        ':mga_name'    => $Data['mga_name'],
        ':mga_acctin'    => $Data['mga_acctin'],
        ':mga_acct_out'    => $Data['mga_acct_out'],
        ':mga_acct_inv'    => $Data['mga_acct_inv'],
        ':mga_acct_stockn'    => $Data['mga_acct_stockn'],
        ':mga_acct_stockp'    => $Data['mga_acct_stockp'],
        ':mga_acct_redu'    => $Data['mga_acct_redu'],
        ':mga_acct_amp'  => $Data['mga_acct_amp'],
        ':mga_acct_cost'  => $Data['mga_acct_cost'],
        ':mga_enabled'  => $Data['mga_enabled'],
        ':mga_id'  => $Data['mga_id']
      ));


      if(is_numeric($resUpdate) && $resUpdate == 1){

        $respuesta = array(
          'error' => false,
          'data' => $resUpdate,
          'mensaje' =>'Grupo de artículo actualizado con exito'
        );


  }else{

        $respuesta = array(
          'error'   => true,
          'data' => $resUpdate,
          'mensaje'	=> 'No se pudo actualizar el Grupo de artículo'
        );

  }

   $this->response($respuesta);

  }


  // OBTENER LISTA DE PRECIOS
  public function getItemsGroup_get(){

        $sqlSelect = " SELECT * FROM dmga";

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
	//obteniendo familia de articulos
	public function getItemsFamily_post(){

				$Data = $this->post();

				$sqlSelect = " SELECT * FROM dmfa where mfa_gcode = :mfa_id";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mfa_id' => $Data['mfa_id']));

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

  // OBTENER LISTA DE PRECIOS POR ID
  public function getItemsGroupById_get(){

        $Data = $this->get();

        if(!isset($Data['mga_id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmga WHERE mga_id = :mga_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':mga_id' => $Data['mga_id']));

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

	// OBTENER LISTA DE SUBGRUPOS
	public function getItemsSubgrupo_get(){

				$Data = $this->get();

				if(!isset($Data['mfa_id'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'La informacion enviada no es valida'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = " SELECT * FROM dmsg WHERE msg_id = :mfa_id";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mfa_id' => $Data['mfa_id']));

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


	//crear familia de articulo
  public function createItemsSubGroup_post(){

 		 $Data = $this->post();

 		 if(!isset($Data['msg_pref']) OR
 				!isset($Data['msg_name']) or
 				!isset($Data['msg_id']) OR
			  !isset($Data['msg_fid'])){

 			 $respuesta = array(
 				 'error' => true,
 				 'data'  => array(),
 				 'mensaje' =>'La informacion enviada no es valida'
 			 );

 			 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

 			 return;
 		 }




 		 $sqlSelect = "SELECT msg_pref FROM dmsg WHERE msg_pref = :msg_pref";

 		 $resSelect = $this->pedeo->queryTable($sqlSelect, array(

 				 ':msg_pref' => $Data['msg_pref']

 		 ));


 		 if(isset($resSelect[0])){

 			 $respuesta = array(
 				 'error' => true,
 				 'data'  => array($Data['msg_pref'], $Data['msg_pref']),
 				 'mensaje' => 'ya existe un grupo con ese código');

 			 $this->response($respuesta);

 			 return;

 	 }


 			 $sqlInsert = "INSERT INTO dmsg (msg_pref, msg_name,msg_fcode)
 										 VALUES(:msg_pref, :msg_name,:msg_fcode)";

 			 $resInsert = $this->pedeo->insertRow($sqlInsert, array(

 						 ':msg_pref'    => $Data['msg_pref'],
 						 ':msg_name'    => $Data['msg_name'],
						 ':msg_fcode'   => $Data['msg_fid']

 			 ));

 			 if(is_numeric($resInsert) && $resInsert > 0){

 					 $respuesta = array(
 						 'error'		=> false,
 						 'data' 		=> $resInsert,
 						 'mensaje' =>'Familia de articulo registrado con exito'
 					 );

 				}else{

 					$respuesta = array(
 						'error'   => true,
 						'data' 	 => $resInsert,
 						'mensaje' => 'No se pudo registrar la familia de articulo'
 					);

 				}

 				$this->response($respuesta);

 }
 //ACTUALIZAR SUBGRUPOS
 public function updateItemSubGroup_post(){

		 $Data = $this->post();

		 if(!isset($Data['mgs_pref']) OR
				!isset($Data['mgs_name']) OR
				!isset($Data['mgs_fcode'])){

			 $respuesta = array(
				 'error' => true,
				 'data'  => array(),
				 'mensaje' =>'La informacion enviada no es valida'
			 );

			 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			 return;
		 }


		 $sqlUpdate = "UPDATE dmsg SET mgs_pref = :mgs_pref,
																	 mgs_name = :mgs_name,
																	 mgs_fcode = :mgs_fcode,
																	 WHERE mgs_pref = :mgs_pref";


		 $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(


			 ':mgs_pref'    => $Data['mgs_pref'],
			 ':mgs_name'    => $Data['mgs_name'],
			 ':mgs_fcode'    => $Data['mgs_fcode'],
			  ));


		 if(is_numeric($resUpdate) && $resUpdate == 1){

			 $respuesta = array(
				 'error' => false,
				 'data' => $resUpdate,
				 'mensaje' =>'Grupo de artículo actualizado con exito'
			 );


 }else{

			 $respuesta = array(
				 'error'   => true,
				 'data' => $resUpdate,
				 'mensaje'	=> 'No se pudo actualizar el Grupo de artículo'
			 );

 }

	$this->response($respuesta);

 }


}
