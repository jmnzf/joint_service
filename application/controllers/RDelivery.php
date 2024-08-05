<?php
// DATOS MAESTROS ALMACEN
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class RDelivery extends REST_Controller {

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

  //CREAR NUEVA RELACION
	public function createRDelivery_post()
    {

        $Data = $this->post();

        if(!isset($Data['ded_smdid']) OR
            !isset($Data['ded_sdeid']) OR
            !isset($Data['ded_price']) OR
            !isset($Data['business'])OR
            !isset($Data['user'])){
                
                $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
                );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sql = "SELECT * FROM rded WHERE ded_smdid = :ded_smdid AND ded_sdeid = :ded_sdeid AND ded_status = 1 AND business = :business";

        $resSql = $this->pedeo->queryTable($sql,array(
            ':ded_smdid' => $Data['ded_smdid'],
            ':ded_sdeid' => $Data['ded_sdeid'],
            ':business' => $Data['business']
        ));

        if(isset($resSql[0])){

            $update = "UPDATE rded SET ded_status = :ded_status,ded_user = :ded_user,ded_date = :ded_date WHERE ded_id = :ded_id";

            $resUpdate = $this->pedeo->updateRow($update,array(
                ':ded_status' => 0,
                ':ded_user' => $Data['user'],
                ':ded_date' => date('Y-m-d'),
                ':ded_id' => $resSql[0]['ded_id']
            ));

            if(is_numeric($resUpdate) && $resUpdate > 0){

                $sqlInsert = "INSERT INTO rded(ded_smdid,ded_sdeid,ded_price,ded_status,business,ded_user,ded_date) 
                VALUES(:ded_smdid,:ded_sdeid,:ded_price,:ded_status,:business,:ded_user,:ded_date)";

                $resInsert = $this->pedeo->insertRow($sqlInsert, array(

                    ':ded_smdid' => $Data['ded_smdid'],
                    ':ded_sdeid' => $Data['ded_sdeid'],
                    ':ded_price' => $Data['ded_price'],
                    ':business' => $Data['business'],
                    ':ded_user' => $Data['user'],
                    ':ded_date' => date('Y-m-d'),
                    ':ded_status' => 1
                ));

                if(is_numeric($resInsert) && $resInsert > 0){

                    $respuesta = array(
                        'error' 	=> false,
                        'data' 		=> $resInsert,
                        'mensaje' =>'Relacion registrada con exito'
                    );


                }else{


                    $respuesta = array(
                        'error'   => true,
                        'data' 		=> $resInsert,
                        'mensaje'	=> 'No se pudo registrar la relacion'
                    );
                }


            }
        }else {
            $sqlInsert = "INSERT INTO rded(ded_smdid,ded_sdeid,ded_price,ded_status,business,ded_user,ded_date) 
                VALUES(:ded_smdid,:ded_sdeid,:ded_price,:ded_status,:business,:ded_user,:ded_date)";

            $resInsert = $this->pedeo->insertRow($sqlInsert, array(

                ':ded_smdid' => $Data['ded_smdid'],
                ':ded_sdeid' => $Data['ded_sdeid'],
                ':ded_price' => $Data['ded_price'],
                ':business' => $Data['business'],
                ':ded_user' => $Data['user'],
                ':ded_date' => date('Y-m-d'),
                ':ded_status' => 1,
            ));

            if(is_numeric($resInsert) && $resInsert > 0){

                $respuesta = array(
                    'error' 	=> false,
                    'data' 		=> $resInsert,
                    'mensaje' =>'Relacion registrada con exito'
                );


            }else{


                $respuesta = array(
                    'error'   => true,
                    'data' 		=> $resInsert,
                    'mensaje'	=> 'No se pudo registrar la relacion'
                );

                

            }
        }

        

         $this->response($respuesta);
	}


  // OBTENER RELACIONES
  public function getRDelivery_get(){

        $Data = $this->get();

        if(!isset($Data['business'])){
          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = "SELECT
                        ded_id, ded_smdid, ded_sdeid, 
                        get_localcur()||' '||to_char(ded_price, '999G999G999G999G999D'||lpad('9',get_decimals(),'9')) as ded_price, 
                        rded.business, rded.branch, ded_status, ded_user, ded_date,
                        csmd.smd_description AS name_dia,
                        csde.sde_description AS name_descp
                    FROM rded
                    INNER JOIN csmd ON rded.ded_smdid = csmd.smd_id
                    INNER JOIN csde ON rded.ded_sdeid = csde.sde_id
                    WHERE rded.business = :business AND csde.sde_status = 1";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':business' => $Data['business']));

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
