<?php
	defined('BASEPATH') OR exit('No direct script access allowed');

	require_once(APPPATH.'/libraries/REST_Controller.php');
	Use Restserver\Libraries\REST_Controller;

	class ContractAddresses extends REST_Controller
	{
	
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

		public function getContractAddresses_get() {
			
            $sql = "SELECT * FROM tdsc";

			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
            );

	        $result = $this->pedeo->queryTable($sql, array());
	      
	        if(isset($result[0])){
	        	//
	          	$response = array(
		            'error'  => false,
		            'data'   => $result,
		            'mensaje'=> ''
	        	);
	        }
	        
         	$this->response($response);
		}

		public function createContractAddresses_post() {
			
			$Data = $this->post();

			if( !isset($Data['dsc_docentry']) OR  
				!isset($Data['dsc_doctype']) OR
                !isset($Data['dsc_rutas']) OR
                !isset($Data['dsc_lat']) OR
                !isset($Data['dsc_lng']) OR
				!isset($Data['dias'])
            ){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			

			$ComplementoDias = $Data['dias'];

			$this->pedeo->trans_begin();

            $sqlInsert = "INSERT INTO tdsc (dsc_docentry,dsc_doctype,dsc_rutas, dsc_lat, dsc_lng)VALUES(:dsc_docentry,:dsc_doctype,:dsc_rutas, :dsc_lat, :dsc_lng)";
		
			
			$resInsert = $this->pedeo->insertRow($sqlInsert,
				array(

					':dsc_docentry' => $Data['dsc_docentry'], 
					':dsc_doctype'  => $Data['dsc_doctype'],
                    ':dsc_rutas'    => $Data['dsc_rutas'], 
                    ':dsc_lat'      => $Data['dsc_lat'], 
                    ':dsc_lng'      => $Data['dsc_lng'], 
				)
			);
			
			if (is_numeric($resInsert) && $resInsert > 0 ) {
			

			} else {

				$this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'    => [],
                    'mensaje' => 'No se pudo guardar el registro'
                );


				return $this->response($respuesta);
            }


			if ( is_array($ComplementoDias) && count($ComplementoDias) > 0 ) {

				$sqlDias = "INSERT INTO tbdg(bdg_dia, bdg_docentry, bdg_doctype)VALUES(:bdg_dia, :bdg_docentry, :bdg_doctype)";

				foreach ($ComplementoDias as $key => $value) {

					$resDias = $this->pedeo->insertRow($sqlDias, array(
						':bdg_dia'      => $value, 
						':bdg_docentry' => $Data['dsc_docentry'], 
						':bdg_doctype'  => $Data['dsc_doctype']
					));

					if( is_numeric($resDias) && $resDias > 0 ){

					}else{

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => [],
							'mensaje' => 'No se pudo guardar el registro'
						);

						return $this->response($respuesta);
					}
				}
			}

			$respuesta = array(
				'error'   => false,
				'data'    => [],
				'mensaje' => 'Datos guardados exitosamente'
			);

			$this->pedeo->trans_commit();

	        
         	$this->response($respuesta);
		}

		public function updateContractAddresses_post() {
			
			$Data = $this->post();

			if( !isset($Data['dsc_docentry']) OR  
				!isset($Data['dsc_doctype']) OR
                !isset($Data['dsc_rutas']) OR
                !isset($Data['dsc_lat']) OR
                !isset($Data['dsc_lng']) OR
                !isset($Data['dsc_id']) OR
				!isset($Data['dias'])
            ){

				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return;
			}

			$ComplementoDias = $Data['dias'];

			$this->pedeo->trans_begin();



            $sqlUpdate = "UPDATE tdsc 
                    SET dsc_docentry = :dsc_docentry,
						dsc_doctype = :dsc_doctype,
                        dsc_rutas = :dsc_rutas,
                        dsc_lat = :dsc_lat,
                        dsc_lng = :dsc_lng
                    WHERE dsc_id = :dsc_id";

		
			$resUpdate = $this->pedeo->updateRow($sqlUpdate,
                array(

                    ':dsc_docentry' => $Data['dsc_docentry'], 
					':dsc_doctype'  => $Data['dsc_doctype'],
                    ':dsc_rutas'    => $Data['dsc_rutas'], 
                    ':dsc_lat'      => $Data['dsc_lat'], 
                    ':dsc_lng'      => $Data['dsc_lng'], 
                    ':dsc_id'       => $Data['dsc_id']
                )
			);
		
			if (is_numeric($resUpdate) && $resUpdate == 1) {

			} else {

				$this->pedeo->trans_rollback();

                $response = array(
					'error'   => true,
					'data'    => [],
					'mensaje' => 'No se pudo actualizar el registro'
				);

				return $this->response($respuesta);
            }


			
			if ( is_array($ComplementoDias) && count($ComplementoDias) > 0 ) {

				$this->pedeo->deleteRow("DELETE FROM tbdg WHERE  bdg_docentry = :bdg_docentry AND bdg_doctype = :bdg_doctype", array(
					":bdg_docentry" => $Data['dsc_docentry'],
					":bdg_doctype"  => $Data['dsc_doctype'],
				));

				$sqlDias = "INSERT INTO tbdg(bdg_dia, bdg_docentry, bdg_doctype)VALUES(:bdg_dia, :bdg_docentry, :bdg_doctype)";

				foreach ($ComplementoDias as $key => $value) {

					$resDias = $this->pedeo->insertRow($sqlDias, array(
						':bdg_dia'      => $value, 
						':bdg_docentry' => $Data['dsc_docentry'], 
						':bdg_doctype'  => $Data['dsc_doctype']
					));

					if( is_numeric($resDias) && $resDias > 0 ){

					}else{

						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'    => [],
							'mensaje' => 'No se pudo guardar el registro'
						);

						return $this->response($respuesta);
					}
				}
			}

			$response = array(
				'error'   => false,
				'data'    => [],
				'mensaje' => 'Datos guardados exitosamente'
			);

			$this->pedeo->trans_commit();
	        
         	$this->response($response);
		}

        public function getContractAddressesByDoc_post() {

            $Data = $this->post();

            $sql = "SELECT tdsc.*, string_agg(bdg_dia, ',')  as dias
					FROM tdsc 
					left join tbdg on bdg_docentry = dsc_docentry and bdg_doctype = dsc_doctype 
					WHERE dsc_docentry =:dsc_docentry AND dsc_doctype = :dsc_doctype
					GROUP BY dsc_id,dsc_docentry,dsc_doctype,dsc_lat,dsc_lng";

			$response = array(
				'error'   => true,
				'data'    => [],
				'mensaje' => 'busqueda sin resultados'
            );

	        $result = $this->pedeo->queryTable($sql, array(
                ':dsc_docentry' => $Data['dsc_docentry'],
                ':dsc_doctype' => $Data['dsc_doctype']
            ));
	      
	        if(isset($result[0])){
	        	//
	          	$response = array(
		            'error'  => false,
		            'data'   => $result,
		            'mensaje'=> ''
	        	);
	        }
	        
         	$this->response($response);
        }
	}
