<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class ParModule extends REST_Controller {

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

   //Crear Tipo de Color Modular
	public function createTypeColorModule_post(){

      $Data = $this->post();

      if(!isset($Data['mtc_name'])){

         $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
         );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlInsert = "INSERT INTO tmtc(mtc_name, mtc_status)
                   VALUES(:mtc_name, :mtc_status)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(
         ':mtc_name' => $Data['mtc_name'],
         ':mtc_status' => $Data['mtc_status']
      ));


      if(is_numeric($resInsert) && $resInsert > 0){
         $respuesta = array(
           'error'	 => false,
           'data' 	 => $resInsert,
           'mensaje' =>'Tipo de color modular registrado con exito'
         );
      }else{

				$respuesta = array(
					'error'   => true,
					'data' 		=> $resInsert,
					'mensaje' => 'No se pudo registrar el tipo de color modular'
				);

			}

      $this->response($respuesta);
	}

  //Actualizar Datos de Tipo de Color Modular
  public function updateTypeColorModule_post(){

      $Data = $this->post();

      if(!isset($Data['mtc_id']) OR
         !isset($Data['mtc_name']) OR
         !isset($Data['mtc_status'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE tmtc SET mtc_name = :mtc_name, mtc_status = :mtc_status
                    WHERE mtc_id = :mtc_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':mtc_name' => $Data['mtc_name'],
            ':mtc_status' => $Data['mtc_status'],
						':mtc_id' => $Data['mtc_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Tipo de color modular actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el tipo de color modular'
            );

      }

       $this->response($respuesta);
  }

  // Obtener Tipos de color MOdular
  public function getTypeColorModule_get(){

        $sqlSelect = "SELECT * FROM tmtc";

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

//Obtener datos Tipo de Color Modular por id
  public function getTypeColorModuleById_get(){

        $Data = $this->get();

        if(!isset($Data['mtc_id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM tmtc WHERE mtc_id = :mtc_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':mtc_id' => $Data['mtc_id']));

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

	//
	//
	//
	//
	//

	//Crear Tipo de Cuerpo Modular
 public function createTypeBodyModule_post(){

		 $Data = $this->post();

		 if(!isset($Data['tdc_name'])){

				$respuesta = array(
					 'error' => true,
					 'data'  => array(),
					 'mensaje' =>'La informacion enviada no es valida'
				);

			 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			 return;
		 }

		 $sqlInsert = "INSERT INTO mtdc(tdc_name, tdc_status)
									VALUES(:tdc_name, :tdc_status)";


		 $resInsert = $this->pedeo->insertRow($sqlInsert, array(
				':tdc_name' => $Data['tdc_name'],
				':tdc_status' => $Data['tdc_status']
		 ));


		 if(is_numeric($resInsert) && $resInsert > 0){
				$respuesta = array(
					'error'	 => false,
					'data' 	 => $resInsert,
					'mensaje' =>'Tipo de cuerpo modular registrado con exito'
				);
		 }else{

			 $respuesta = array(
				 'error'   => true,
				 'data' 		=> $resInsert,
				 'mensaje' => 'No se pudo registrar el tipo de cuerpo modular'
			 );

		 }

		 $this->response($respuesta);
 }

 //Actualizar Datos de Tipo de Cuerpo Modular
 public function updateTypeBodyModule_post(){

		 $Data = $this->post();

		 if(!isset($Data['tdc_id']) OR
				!isset($Data['tdc_name']) OR
				!isset($Data['tdc_status'])){

			 $respuesta = array(
				 'error' => true,
				 'data'  => array(),
				 'mensaje' =>'La informacion enviada no es valida'
			 );

			 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			 return;
		 }

		 $sqlUpdate = "UPDATE mtdc SET tdc_name = :tdc_name, tdc_status = :tdc_status
									 WHERE tdc_id = :tdc_id";


		 $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

					 ':tdc_name' => $Data['tdc_name'],
					 ':tdc_status' => $Data['tdc_status'],
					 ':tdc_id' => $Data['tdc_id']
		 ));

		 if(is_numeric($resUpdate) && $resUpdate == 1){

					 $respuesta = array(
						 'error' => false,
						 'data' => $resUpdate,
						 'mensaje' =>'Tipo de cuerpo modular actualizado con exito'
					 );


		 }else{

					 $respuesta = array(
						 'error'   => true,
						 'data' => $resUpdate,
						 'mensaje'	=> 'No se pudo actualizar el tipo de cuerpo modular'
					 );

		 }

			$this->response($respuesta);
 }

 // Obtener Tipos de Cuerpo MOdular
 public function getTypeBodyModule_get(){

			 $sqlSelect = "SELECT * FROM mtdc";

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

//Obtener datos Tipo de Cuerpo Modular por id
 public function getTypeBodyModuleById_get(){

			 $Data = $this->get();

			 if(!isset($Data['tdc_id'])){

				 $respuesta = array(
					 'error' => true,
					 'data'  => array(),
					 'mensaje' =>'La informacion enviada no es valida'
				 );

				 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				 return;
			 }

			 $sqlSelect = " SELECT * FROM mtdc WHERE tdc_id = :tdc_id";

			 $resSelect = $this->pedeo->queryTable($sqlSelect, array(':tdc_id' => $Data['tdc_id']));

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

 //
 //
 //
 //
 //

 //Crear Tipo de Pagina Modular
 public function createTypePageModule_post(){

		$Data = $this->post();

		if(!isset($Data['tdp_name'])){

			 $respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
			 );

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlInsert = "INSERT INTO mtdp(tdp_name, tdp_status)
								 VALUES(:tdp_name, :tdp_status)";


		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			 ':tdp_name' => $Data['tdp_name'],
			 ':tdp_status' => $Data['tdp_status']
		));


		if(is_numeric($resInsert) && $resInsert > 0){
			 $respuesta = array(
				 'error'	 => false,
				 'data' 	 => $resInsert,
				 'mensaje' =>'Tipo de pagina modular registrado con exito'
			 );
		}else{

			$respuesta = array(
				'error'   => true,
				'data' 		=> $resInsert,
				'mensaje' => 'No se pudo registrar el tipo de pagina modular'
			);

		}

		$this->response($respuesta);
 }

 //Actualizar Datos de Tipo de Pagina Modular
 public function updateTypePageModule_post(){

		$Data = $this->post();

		if(!isset($Data['tdp_id']) OR
			 !isset($Data['tdp_name']) OR
			 !isset($Data['tdp_status'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE mtdp SET tdp_name = :tdp_name, tdp_status = :tdp_status
									WHERE tdp_id = :tdp_id";


		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

					':tdp_name' => $Data['tdp_name'],
					':tdp_status' => $Data['tdp_status'],
					':tdp_id' => $Data['tdp_id']
		));

		if(is_numeric($resUpdate) && $resUpdate == 1){

					$respuesta = array(
						'error' => false,
						'data' => $resUpdate,
						'mensaje' =>'Tipo de pagina modular actualizada con exito'
					);


		}else{

					$respuesta = array(
						'error'   => true,
						'data' => $resUpdate,
						'mensaje'	=> 'No se pudo actualizar el tipo de pagina modular'
					);

		}

		 $this->response($respuesta);
 }

 // Obtener Tipos de Pagina MOdular
 public function getTypePageModule_get(){

			$sqlSelect = "SELECT * FROM mtdp";

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

//Obtener datos Tipo de Pagina Modular por id
 public function getTypePageModuleById_get(){

			$Data = $this->get();

			if(!isset($Data['tdp_id'])){

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}

			$sqlSelect = " SELECT * FROM mtdp WHERE tdp_id = :tdp_id";

			$resSelect = $this->pedeo->queryTable($sqlSelect, array(':tdp_id' => $Data['tdp_id']));

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

 //
 //
 //
 //
 //

 //Crear Tipo de clasificacion Pagina Modular
 public function createClassPageModule_post(){

		$Data = $this->post();

		if(!isset($Data['cdp_name'])){

			 $respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
			 );

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlInsert = "INSERT INTO mcdp(cdp_name, cdp_status)
								 VALUES(:cdp_name, :cdp_status)";


		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			 ':cdp_name' => $Data['cdp_name'],
			 ':cdp_status' => $Data['cdp_status']
		));


		if(is_numeric($resInsert) && $resInsert > 0){
			 $respuesta = array(
				 'error'	 => false,
				 'data' 	 => $resInsert,
				 'mensaje' =>'Clasificacion de pagina modular registrado con exito'
			 );
		}else{

			$respuesta = array(
				'error'   => true,
				'data' 		=> $resInsert,
				'mensaje' => 'No se pudo registrar la clasificacion de pagina modular'
			);

		}

		$this->response($respuesta);
 }

 //Actualizar Datos de Tipo de clasificacion Pagina Modular
 public function updateClassPageModule_post(){

		$Data = $this->post();

		if(!isset($Data['cdp_id']) OR
			 !isset($Data['cdp_name']) OR
			 !isset($Data['cdp_status'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE mcdp SET cdp_name = :cdp_name, cdp_status = :cdp_status
									WHERE cdp_id = :cdp_id";


		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

					':cdp_name' => $Data['cdp_name'],
					':cdp_status' => $Data['cdp_status'],
					':cdp_id' => $Data['cdp_id']
		));

		if(is_numeric($resUpdate) && $resUpdate == 1){

					$respuesta = array(
						'error' => false,
						'data' => $resUpdate,
						'mensaje' =>'Clasificacion de pagina modular actualizada con exito'
					);


		}else{

					$respuesta = array(
						'error'   => true,
						'data' => $resUpdate,
						'mensaje'	=> 'No se pudo actualizar la clasificacion de pagina modular'
					);

		}

		 $this->response($respuesta);
 }

 // Obtener Tipos de clasificacion Pagina MOdular
 public function getClassPageModule_get(){

			$sqlSelect = "SELECT * FROM mcdp";

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

//Obtener datos Tipo de clasificacion Pagina Modular por id
 public function getClassPageModuleById_get(){

			$Data = $this->get();

			if(!isset($Data['cdp_id'])){

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}

			$sqlSelect = " SELECT * FROM mcdp WHERE cdp_id = :cdp_id";

			$resSelect = $this->pedeo->queryTable($sqlSelect, array(':cdp_id' => $Data['cdp_id']));

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

 //
 //
 //
 //
 //

 //Crear Tipo de calsificado Modular
 public function createTypeClassModule_post(){

		$Data = $this->post();

		if(!isset($Data['tpc_name'])){

			 $respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
			 );

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlInsert = "INSERT INTO mtpc(tpc_name, tpc_status)
								 VALUES(:tpc_name, :tpc_status)";


		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			 ':tpc_name' => $Data['tpc_name'],
			 ':tpc_status' => $Data['tpc_status']
		));


		if(is_numeric($resInsert) && $resInsert > 0){
			 $respuesta = array(
				 'error'	 => false,
				 'data' 	 => $resInsert,
				 'mensaje' =>'Tipo de clasificado pagina modular registrado con exito'
			 );
		}else{

			$respuesta = array(
				'error'   => true,
				'data' 		=> $resInsert,
				'mensaje' => 'No se pudo registrar el tipo de clasificado pagina modular'
			);

		}

		$this->response($respuesta);
 }

 //Actualizar Datos de Tipo de clasificado Modular
 public function updateTypeClassModule_post(){

		$Data = $this->post();

		if(!isset($Data['tpc_id']) OR
			 !isset($Data['tpc_name']) OR
			 !isset($Data['tpc_status'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlUpdate = "UPDATE mtpc SET tpc_name = :tpc_name, tpc_status = :tpc_status
									WHERE tpc_id = :tpc_id";


		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

					':tpc_name' => $Data['tpc_name'],
					':tpc_status' => $Data['tpc_status'],
					':tpc_id' => $Data['tpc_id']
		));

		if(is_numeric($resUpdate) && $resUpdate == 1){

					$respuesta = array(
						'error' => false,
						'data' => $resUpdate,
						'mensaje' =>'Tipo de clasificado pagina modular actualizada con exito'
					);


		}else{

					$respuesta = array(
						'error'   => true,
						'data' => $resUpdate,
						'mensaje'	=> 'No se pudo actualizar el tipo de clasificado pagina modular'
					);

		}

		 $this->response($respuesta);
 }

 // Obtener Tipos de clasificado MOdular
 public function getTypeClassModule_get(){

			$sqlSelect = "SELECT * FROM mtpc";

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

//Obtener datos Tipo de calsificado Pagina Modular por id
 public function getTypeClassModuleById_get(){

			$Data = $this->get();

			if(!isset($Data['tpc_id'])){

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'La informacion enviada no es valida'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}

			$sqlSelect = " SELECT * FROM mtpc WHERE tpc_id = :tpc_id";

			$resSelect = $this->pedeo->queryTable($sqlSelect, array(':tpc_id' => $Data['tpc_id']));

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

 //
 //
 //
 //
 //

 //Crear Tipo de recargo Modular
 public function createTypeSurchargeModule_post(){

	 $Data = $this->post();

	 if(!isset($Data['tdr_name'])){

			$respuesta = array(
				 'error' => true,
				 'data'  => array(),
				 'mensaje' =>'La informacion enviada no es valida'
			);

		 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		 return;
	 }

	 $sqlInsert = "INSERT INTO mtdr(tdr_name, tdr_status)
								VALUES(:tdr_name, :tdr_status)";


	 $resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':tdr_name' => $Data['tdr_name'],
			':tdr_status' => $Data['tdr_status']
	 ));


	 if(is_numeric($resInsert) && $resInsert > 0){
			$respuesta = array(
				'error'	 => false,
				'data' 	 => $resInsert,
				'mensaje' =>'Tipo de recargo modular registrado con exito'
			);
	 }else{

		 $respuesta = array(
			 'error'   => true,
			 'data' 		=> $resInsert,
			 'mensaje' => 'No se pudo registrar el tipo de recargo modular'
		 );

	 }

	 $this->response($respuesta);
 }

 //Actualizar Datos de Tipo de recargo Modular
 public function updateTypeSurchargeModule_post(){

	 $Data = $this->post();

	 if(!isset($Data['tdr_id']) OR
			!isset($Data['tdr_name']) OR
			!isset($Data['tdr_status'])){

		 $respuesta = array(
			 'error' => true,
			 'data'  => array(),
			 'mensaje' =>'La informacion enviada no es valida'
		 );

		 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		 return;
	 }

	 $sqlUpdate = "UPDATE mtdr SET tdr_name = :tdr_name, tdr_status = :tdr_status
								 WHERE tdr_id = :tdr_id";


	 $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

				 ':tdr_name' => $Data['tdr_name'],
				 ':tdr_status' => $Data['tdr_status'],
				 ':tdr_id' => $Data['tdr_id']
	 ));

	 if(is_numeric($resUpdate) && $resUpdate == 1){

				 $respuesta = array(
					 'error' => false,
					 'data' => $resUpdate,
					 'mensaje' =>'Tipo de recargo modular actualizado con exito'
				 );


	 }else{

				 $respuesta = array(
					 'error'   => true,
					 'data' => $resUpdate,
					 'mensaje'	=> 'No se pudo actualizar el tipo de recargo modular'
				 );

	 }

		$this->response($respuesta);
 }

 // Obtener Tipos de recargo MOdular
 public function getTypeSurchargeModule_get(){

		 $sqlSelect = "SELECT * FROM mtdr";

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

//Obtener datos Tipo de recargo Modular por id
 public function getTypeSurchargeModuleById_get(){

		 $Data = $this->get();

		 if(!isset($Data['tdr_id'])){

			 $respuesta = array(
				 'error' => true,
				 'data'  => array(),
				 'mensaje' =>'La informacion enviada no es valida'
			 );

			 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			 return;
		 }

		 $sqlSelect = " SELECT * FROM mtdr WHERE tdr_id = :tdr_id";

		 $resSelect = $this->pedeo->queryTable($sqlSelect, array(':tdr_id' => $Data['tdr_id']));

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

 //
 //
 //
 //
 //

 //Crear Tipo de distribucion Modular
 public function createTypeDistributionModule_post(){

	$Data = $this->post();

	if(!isset($Data['tdd_name'])){

		 $respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
		 );

		$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		return;
	}

	$sqlInsert = "INSERT INTO mtdd(tdd_name, tdd_status)
							 VALUES(:tdd_name, :tdd_status)";


	$resInsert = $this->pedeo->insertRow($sqlInsert, array(
		 ':tdd_name' => $Data['tdd_name'],
		 ':tdd_status' => $Data['tdd_status']
	));


	if(is_numeric($resInsert) && $resInsert > 0){
		 $respuesta = array(
			 'error'	 => false,
			 'data' 	 => $resInsert,
			 'mensaje' =>'Tipo de distribucion modular registrado con exito'
		 );
	}else{

		$respuesta = array(
			'error'   => true,
			'data' 		=> $resInsert,
			'mensaje' => 'No se pudo registrar el tipo de distribucion modular'
		);

	}

	$this->response($respuesta);
 }

 //Actualizar Datos de Tipo de distribucion Modular
 public function updateTypeDistributionModule_post(){

	$Data = $this->post();

	if(!isset($Data['tdd_id']) OR
		 !isset($Data['tdd_name']) OR
		 !isset($Data['tdd_status'])){

		$respuesta = array(
			'error' => true,
			'data'  => array(),
			'mensaje' =>'La informacion enviada no es valida'
		);

		$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		return;
	}

	$sqlUpdate = "UPDATE mtdd SET tdd_name = :tdd_name, tdd_status = :tdd_status
								WHERE tdd_id = :tdd_id";


	$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

				':tdd_name' => $Data['tdd_name'],
				':tdd_status' => $Data['tdd_status'],
				':tdd_id' => $Data['tdd_id']
	));

	if(is_numeric($resUpdate) && $resUpdate == 1){

				$respuesta = array(
					'error' => false,
					'data' => $resUpdate,
					'mensaje' =>'Tipo de distribucion modular actualizado con exito'
				);


	}else{

				$respuesta = array(
					'error'   => true,
					'data' => $resUpdate,
					'mensaje'	=> 'No se pudo actualizar el tipo de distribucion modular'
				);

	}

	 $this->response($respuesta);
 }

 // Obtener Tipos de distribucion MOdular
 public function getTypeDistributionModule_get(){

		$sqlSelect = "SELECT * FROM mtdd";

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

//Obtener datos Tipo de distribucion Modular por id
 public function getTypeDistributionModuleById_get(){

		$Data = $this->get();

		if(!isset($Data['tdd_id'])){

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' =>'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM mtdd WHERE tdd_id = :tdd_id";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':tdd_id' => $Data['tdd_id']));

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

 //
 //
 //
 //
 //

 //Crear Tipo de negociacion Modular
 public function createTypeNegotiationModule_post(){

 $Data = $this->post();

 if(!isset($Data['tdn_name'])){

		$respuesta = array(
			 'error' => true,
			 'data'  => array(),
			 'mensaje' =>'La informacion enviada no es valida'
		);

	 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	 return;
 }

 $sqlInsert = "INSERT INTO mtdn(tdn_name, tdn_status)
							VALUES(:tdn_name, :tdn_status)";


 $resInsert = $this->pedeo->insertRow($sqlInsert, array(
		':tdn_name' => $Data['tdn_name'],
		':tdn_status' => $Data['tdn_status']
 ));


 if(is_numeric($resInsert) && $resInsert > 0){
		$respuesta = array(
			'error'	 => false,
			'data' 	 => $resInsert,
			'mensaje' =>'Tipo de negociacion modular registrado con exito'
		);
 }else{

	 $respuesta = array(
		 'error'   => true,
		 'data' 		=> $resInsert,
		 'mensaje' => 'No se pudo registrar el tipo de negociacion modular'
	 );

 }

 $this->response($respuesta);
 }

 //Actualizar Datos de Tipo de negociacion Modular
 public function updateTypeNegotiationModule_post(){

 $Data = $this->post();

 if(!isset($Data['tdn_id']) OR
		!isset($Data['tdn_name']) OR
		!isset($Data['tdn_status'])){

	 $respuesta = array(
		 'error' => true,
		 'data'  => array(),
		 'mensaje' =>'La informacion enviada no es valida'
	 );

	 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	 return;
 }

 $sqlUpdate = "UPDATE mtdn SET tdn_name = :tdn_name, tdn_status = :tdn_status
							 WHERE tdn_id = :tdn_id";


 $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

			 ':tdn_name' => $Data['tdn_name'],
			 ':tdn_status' => $Data['tdn_status'],
			 ':tdn_id' => $Data['tdn_id']
 ));

 if(is_numeric($resUpdate) && $resUpdate == 1){

			 $respuesta = array(
				 'error' => false,
				 'data' => $resUpdate,
				 'mensaje' =>'Tipo de negociacion modular actualizado con exito'
			 );


 }else{

			 $respuesta = array(
				 'error'   => true,
				 'data' => $resUpdate,
				 'mensaje'	=> 'No se pudo actualizar el tipo de negociacion modular'
			 );

 }

	$this->response($respuesta);
 }

 // Obtener Tipos de negociacion MOdular
 public function getTypeNegotiationModule_get(){

	 $sqlSelect = "SELECT * FROM mtdn";

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

//Obtener datos Tipo de negociacion Modular por id
 public function getTypeNegotiationModuleById_get(){

	 $Data = $this->get();

	 if(!isset($Data['tdn_id'])){

		 $respuesta = array(
			 'error' => true,
			 'data'  => array(),
			 'mensaje' =>'La informacion enviada no es valida'
		 );

		 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		 return;
	 }

	 $sqlSelect = " SELECT * FROM mtdn WHERE tdn_id = :tdn_id";

	 $resSelect = $this->pedeo->queryTable($sqlSelect, array(':tdn_id' => $Data['tdn_id']));

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

 //
 //
 //
 //
 //

 //Crear rango Modular
 public function createRangeModule_post(){

 $Data = $this->post();

 if(!isset($Data['rdm_name'])){

		$respuesta = array(
			 'error' => true,
			 'data'  => array(),
			 'mensaje' =>'La informacion enviada no es valida'
		);

	 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	 return;
 }

 $sqlInsert = "INSERT INTO mrdm(rdm_name, rdm_status)
							VALUES(:rdm_name, :rdm_status)";


 $resInsert = $this->pedeo->insertRow($sqlInsert, array(
		':rdm_name' => $Data['rdm_name'],
		':rdm_status' => $Data['rdm_status']
 ));


 if(is_numeric($resInsert) && $resInsert > 0){
		$respuesta = array(
			'error'	 => false,
			'data' 	 => $resInsert,
			'mensaje' =>'Rango modular registrado con exito'
		);
 }else{

	 $respuesta = array(
		 'error'   => true,
		 'data' 		=> $resInsert,
		 'mensaje' => 'No se pudo registrar el rango modular'
	 );

 }

 $this->response($respuesta);
 }

 //Actualizar Datos de rango Modular
 public function updateRangeModule_post(){

 $Data = $this->post();

 if(!isset($Data['rdm_id']) OR
		!isset($Data['rdm_name']) OR
		!isset($Data['rdm_status'])){

	 $respuesta = array(
		 'error' => true,
		 'data'  => array(),
		 'mensaje' =>'La informacion enviada no es valida'
	 );

	 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	 return;
 }

 $sqlUpdate = "UPDATE mrdm SET rdm_name = :rdm_name, rdm_status = :rdm_status
							 WHERE rdm_id = :rdm_id";


 $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

			 ':rdm_name' => $Data['rdm_name'],
			 ':rdm_status' => $Data['rdm_status'],
			 ':rdm_id' => $Data['rdm_id']
 ));

 if(is_numeric($resUpdate) && $resUpdate == 1){

			 $respuesta = array(
				 'error' => false,
				 'data' => $resUpdate,
				 'mensaje' =>'Rango modular actualizado con exito'
			 );


 }else{

			 $respuesta = array(
				 'error'   => true,
				 'data' => $resUpdate,
				 'mensaje'	=> 'No se pudo actualizar el rango modular'
			 );

 }

	$this->response($respuesta);
 }

 // Obtener rango MOdular
 public function getRangeModule_get(){

	 $sqlSelect = "SELECT * FROM mrdm";

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

//Obtener datos rango Modular por id
 public function getRangeModuleById_get(){

	 $Data = $this->get();

	 if(!isset($Data['rdm_id'])){

		 $respuesta = array(
			 'error' => true,
			 'data'  => array(),
			 'mensaje' =>'La informacion enviada no es valida'
		 );

		 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		 return;
	 }

	 $sqlSelect = " SELECT * FROM mrdm WHERE rdm_id = :rdm_id";

	 $resSelect = $this->pedeo->queryTable($sqlSelect, array(':rdm_id' => $Data['rdm_id']));

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

 //
 //
 //
 //
 //

 //Crear tipo de pago Modular
 public function createTypePayModule_post(){

 $Data = $this->post();

 if(!isset($Data['mtp_name'])){

	 $respuesta = array(
			'error' => true,
			'data'  => array(),
			'mensaje' =>'La informacion enviada no es valida'
	 );

	$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	return;
 }

 $sqlInsert = "INSERT INTO tmtp(mtp_name, mtp_status)
						 VALUES(:mtp_name, :mtp_status)";


 $resInsert = $this->pedeo->insertRow($sqlInsert, array(
	 ':mtp_name' => $Data['mtp_name'],
	 ':mtp_status' => $Data['mtp_status']
 ));


 if(is_numeric($resInsert) && $resInsert > 0){
	 $respuesta = array(
		 'error'	 => false,
		 'data' 	 => $resInsert,
		 'mensaje' =>'Rango modular registrado con exito'
	 );
 }else{

	$respuesta = array(
		'error'   => true,
		'data' 		=> $resInsert,
		'mensaje' => 'No se pudo registrar el rango modular'
	);

 }

 $this->response($respuesta);
 }

 //Actualizar Datos de tipo de pago Modular
 public function updateTypePayModule_post(){

 $Data = $this->post();

 if(!isset($Data['mtp_id']) OR
	 !isset($Data['mtp_name']) OR
	 !isset($Data['mtp_status'])){

	$respuesta = array(
		'error' => true,
		'data'  => array(),
		'mensaje' =>'La informacion enviada no es valida'
	);

	$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	return;
 }

 $sqlUpdate = "UPDATE tmtp SET mtp_name = :mtp_name, mtp_status = :mtp_status
							WHERE mtp_id = :mtp_id";


 $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

			':mtp_name' => $Data['mtp_name'],
			':mtp_status' => $Data['mtp_status'],
			':mtp_id' => $Data['mtp_id']
 ));

 if(is_numeric($resUpdate) && $resUpdate == 1){

			$respuesta = array(
				'error' => false,
				'data' => $resUpdate,
				'mensaje' =>'Rango modular actualizado con exito'
			);


 }else{

			$respuesta = array(
				'error'   => true,
				'data' => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar el rango modular'
			);

 }

 $this->response($respuesta);
 }

 // Obtener tipo de pago MOdular
 public function getTypePayModule_get(){

	$sqlSelect = "SELECT * FROM tmtp";

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

//Obtener datos tipo de pago Modular por id
 public function getTypePayModuleById_get(){

	$Data = $this->get();

	if(!isset($Data['mtp_id'])){

		$respuesta = array(
			'error' => true,
			'data'  => array(),
			'mensaje' =>'La informacion enviada no es valida'
		);

		$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		return;
	}

	$sqlSelect = " SELECT * FROM tmtp WHERE mtp_id = :mtp_id";

	$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mtp_id' => $Data['mtp_id']));

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

 //
 //
 //
 //
 //

 //Crear impuestos maestros o globales Modular
 public function createTaxMasterModule_post(){

 $Data = $this->post();

 if(!isset($Data['imm_code']) or
	 	!isset($Data['imm_name_tax']) or
		!isset($Data['imm_rate_tax']) or
		!isset($Data['imm_type']) or
		!isset($Data['imm_acctcode'])
	){

	$respuesta = array(
		 'error' => true,
		 'data'  => array(),
		 'mensaje' =>'La informacion enviada no es valida'
	);

 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

 return;
 }

 $sqlInsert = "INSERT INTO timm(imm_code, imm_name_tax,imm_rate_tax,imm_type,imm_acctcode,imm_enable)
						VALUES(:imm_code, :imm_name_tax,:imm_rate_tax,:imm_type,:imm_acctcode,:imm_enable)";


 $resInsert = $this->pedeo->insertRow($sqlInsert, array(
	':imm_code' => $Data['imm_code'],
	':imm_name_tax' => $Data['imm_name_tax'],
	':imm_rate_tax' => $Data['imm_rate_tax'],
	':imm_type' => $Data['imm_type'],
	':imm_acctcode' => $Data['imm_acctcode'],
	':imm_enable' => $Data['imm_enable']
 ));


 if(is_numeric($resInsert) && $resInsert > 0){
	$respuesta = array(
		'error'	 => false,
		'data' 	 => $resInsert,
		'mensaje' =>'impuesto maestro modular registrado con exito'
	);
 }else{

 $respuesta = array(
	 'error'   => true,
	 'data' 		=> $resInsert,
	 'mensaje' => 'No se pudo registrar el impuesto maestro modular'
 );

 }

 $this->response($respuesta);
 }

 //Actualizar Datos de impuestos maestros o globales Modular
 public function updateTaxMasterModule_post(){

 $Data = $this->post();

 if(!isset($Data['imm_id']) or
 		!isset($Data['imm_code']) or
	 	!isset($Data['imm_name_tax']) or
		!isset($Data['imm_rate_tax']) or
		!isset($Data['imm_type']) or
		!isset($Data['imm_acctcode'])){

 $respuesta = array(
	 'error' => true,
	 'data'  => array(),
	 'mensaje' =>'La informacion enviada no es valida'
 );

 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

 return;
 }

 $sqlUpdate = "UPDATE timm
 								SET imm_code = :imm_code
 									,	imm_name_tax = :imm_name_tax
 									, imm_rate_tax = :imm_rate_tax
									, imm_type = :imm_type
									, imm_acctcode = :imm_acctcode
									, imm_enable = :imm_enable
						 	WHERE imm_id = :imm_id";


 $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

		 ':imm_code' => $Data['imm_code'],
		 ':imm_name_tax' => $Data['imm_name_tax'],
		 ':imm_rate_tax' => $Data['imm_rate_tax'],
		 ':imm_type' => $Data['imm_type'],
		 ':imm_acctcode' => $Data['imm_acctcode'],
		 ':imm_enable' => $Data['imm_enable'],
		 ':imm_id' => $Data['imm_id']
 ));

 if(is_numeric($resUpdate) && $resUpdate == 1){

		 $respuesta = array(
			 'error' => false,
			 'data' => $resUpdate,
			 'mensaje' =>'Maestro modular modular actualizado con exito'
		 );


 }else{

		 $respuesta = array(
			 'error'   => true,
			 'data' => $resUpdate,
			 'mensaje'	=> 'No se pudo actualizar el maestro modular modular'
		 );

 }

 $this->response($respuesta);
 }

 // Obtener impuestos maestros o globales MOdular
 public function getTaxMasterModule_get(){

 $sqlSelect = "SELECT * FROM timm";

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

//Obtener datos impuestos maestros o globales Modular por id
 public function getTaxMasterModuleById_get(){

 $Data = $this->get();

 if(!isset($Data['mtp_id'])){

	 $respuesta = array(
		 'error' => true,
		 'data'  => array(),
		 'mensaje' =>'La informacion enviada no es valida'
	 );

	 $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	 return;
 }

 $sqlSelect = " SELECT * FROM timm WHERE imm_id = :imm_id";

 $resSelect = $this->pedeo->queryTable($sqlSelect, array(':imm_id' => $Data['imm_id']));

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

 //listar cuentas contables

 // Obtener impuestos maestros o globales MOdular
 public function getAccountModule_get(){

 $sqlSelect = "SELECT acc_code as id,acc_name as text FROM dacc where acc_level >= 6 order by acc_code asc";

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

}
