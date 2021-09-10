<?php
// ANEXOS DE SOCIOS DE NEGOCIO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Attached extends REST_Controller {

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

  //Crear nuevo anexo
	public function createAttached_post(){

      $Data = $this->post();

      if(!isset($Data['dma_card_code']) OR
         !isset($Data['dma_attach']) OR
         !isset($Data['dma_description'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }


			try {
				//
				$sqlUpdate = "UPDATE pgem SET pge_logo = :pge_logo WHERE pge_id = :pge_id";

				$ruta = '/var/www/html/serpent/assets/img/anexos/';
        $milliseconds = round(microtime(true) * 1000);

				$nombreArchivo = $milliseconds.".pdf";

				touch($ruta.$nombreArchivo);

				$file = fopen($ruta.$nombreArchivo,"wb");

			  if(!empty($Data['dma_attach'])){

					fwrite($file, base64_decode($Data['dma_attach']));

					fclose($file);

					$url = "assets/img/anexos/".$nombreArchivo;

          $sqlInsert = "INSERT INTO dmsa(dma_card_code, dma_attach, dma_description)
                        VALUES (:dma_card_code, :dma_attach, :dma_description)";


          $resInsert = $this->pedeo->insertRow($sqlInsert, array(
                ':dma_card_code' => $Data['dma_card_code'],
                ':dma_attach'  => $url,
                ':dma_description'  => $Data['dma_description']
          ));

          if( is_numeric($resInsert) && $resInsert > 0 ){

                $respuesta = array(
                  'error' => false,
                  'data' => $resInsert,
                  'mensaje' =>'Anexo registrado con exito'
                );


          }else{

                $respuesta = array(
                  'error'   => true,
                  'data' 		=> $resInsert,
                  'mensaje'	=> 'No se pudo registrar el anexo'
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

  //Eliminar Attached
  public function deleteAttached_post(){

      $Data = $this->post();

      if(!isset($Data['dma_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlSelect = " SELECT dma_attach FROM dmsa WHERE dma_id = :dma_id";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(

            ':dma_id' => $Data['dma_id']
      ));

      if(isset($resSelect[0])){

        $ruta = '/var/www/html/serpent/';

        if(unlink($ruta.$resSelect[0]['dma_attach'])){

          $sqlDelete = "DELETE FROM dmsa
                        WHERE dma_id = :dma_id";


          $resDelete = $this->pedeo->updateRow($sqlDelete, array(

                ':dma_id' => $Data['dma_id']
          ));

          if(is_numeric($resDelete) && $resDelete == 1){

                $respuesta = array(
                  'error' => false,
                  'data' => $resDelete,
                  'mensaje' =>'Anexo eliminado con exito'
                );


          }else{

                $respuesta = array(
                  'error'   => true,
                  'data'    => $resDelete,
                  'mensaje'	=> 'No se pudo eliminar el anexo'
                );

          }


        }else{

              $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' =>'No se pudo eliminar el archivo anexo'
              );

              $this->response($respuesta);

              return;
        }

      }else{

        $respuesta = array(
          'error' => true,
          'data' => [],
          'mensaje' =>'No se encontro el anexo'
        );

        $this->response($respuesta);

        return;
      }

       $this->response($respuesta);
  }



  // Obtener anexos por id socio de negocio
  public function getAttachedById_get(){

        $Data = $this->get();

        if(!isset($Data['dma_card_code'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM dmsa WHERE dma_card_code = :dma_card_code";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':dma_card_code' => $Data['dma_card_code']));

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
