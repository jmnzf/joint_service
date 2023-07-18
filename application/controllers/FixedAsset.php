<?php
// DATOS FORMA DE PAGO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class FixedAsset extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
        $this->load->library('session');

	}

    //OBTENER REGISTROS DE CLASES DE AF
    public function getFixedAssetClass_get()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM afca";
        $resSql = $this->pedeo->queryTable($sql,array());

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }

        $this->response($respuesta);
    }
    //CREAR CLASE DE ACTIVO FIJO
    public function createFixedAssetClass_post()
    {

        $Data = $this->post();

        if(!isset($Data['business']) OR !isset($Data['branch'])){

            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Información enviada inválida'
            );

            return $this->response($respuesta);
        }

        //
        $insert = "INSERT INTO afca (fca_description, fca_type, fca_avaloration, fca_camortization, fca_lifetime, business, branch)
                    VALUES (:fca_description,:fca_type,:fca_avaloration,:fca_camortization,:fca_lifetime,:business,:branch)";

        $resInsert = $this->pedeo->insertRow($insert,array(
            ':fca_description' => $Data['fca_description'],
            ':fca_type' => $Data['fca_type'],
            ':fca_avaloration' => $Data['fca_avaloration'],
            ':fca_camortization' => $Data['fca_camortization'],
            ':fca_lifetime' => $Data['fca_lifetime'],
            ':business' => $Data['business'],
            ':branch' => $Data['branch']
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

        }else{
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se puedo registrar la clase de activo fijo'
            );

        }
        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Clase de activo fijo registrada con exito'
        );

        $this->response($respuesta);
    }
    //CREAR AREAS DE VALORACION ACTIVO FIJO
    public function createValuationArea_post()
    {

        $Data = $this->post();

        if(!isset($Data['business']) OR !isset($Data['branch'])){

            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Información enviada inválida'
            );

            return $this->response($respuesta);
        }

        //
        $insert = "INSERT INTO afav (fav_code, fav_description, fav_type, fav_dvalarea, fav_lowaccounting, business, branch,fav_pdepreciation)
                    VALUES (:fav_code,:fav_description,:fav_type,:fav_dvalarea,:fav_lowaccounting,:business,:branch,:fav_pdepreciation)";

        $resInsert = $this->pedeo->insertRow($insert,array(
            ':fav_code' => isset($Data['fav_code']) ? $Data['fav_code'] : NULL,
            ':fav_description' => isset($Data['fav_description']) ? $Data['fav_description'] : NULL,
            ':fav_type' => is_numeric($Data['fav_type']) ? $Data['fav_type'] : 0,
            ':fav_dvalarea' => is_numeric($Data['fav_dvalarea']) ? $Data['fav_dvalarea'] : 0,
            ':fav_lowaccounting' => is_numeric($Data['fav_lowaccounting']) ? $Data['fav_lowaccounting'] : 0,
            ':business' => $Data['business'],
            ':branch' => $Data['branch'],
            ':fav_pdepreciation' => is_numeric($Data['fav_pdepreciation']) ? $Data['fav_pdepreciation'] : 0
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

        }else{
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se puedo registrar el area de valoracion'
            );

        }
        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Area de Valoracion de activo fijo registrada con exito'
        );

        $this->response($respuesta);
    }
    //OBTENER REGISTROS DE AREAS DE VALORACION DE AF
    public function getValuationArea_get()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM afav";
        $resSql = $this->pedeo->queryTable($sql,array());

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }
        
        $this->response($respuesta);
    }
    //OBTENER GRUPO DE ACTIVO
    public function getActiveGroup_get()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM afga";
        $resSql = $this->pedeo->queryTable($sql,array());

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }
        
        $this->response($respuesta);
    }
    //CREAR GRUPO ACTIVO
    public function createActiveGroup_post()
    {

        $Data = $this->post();

        if(!isset($Data['business']) OR !isset($Data['branch'])){

            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Información enviada inválida'
            );

            return $this->response($respuesta);
        }

        //
        $insert = "INSERT INTO afga (fga_code, fga_description, business, branch)
                    VALUES (:fga_code,:fga_description,:business,:branch)";

        $resInsert = $this->pedeo->insertRow($insert,array(
            ':fga_code' => isset($Data['fga_code']) ? $Data['fga_code'] : NULL,
            ':fga_description' => isset($Data['fga_description']) ? $Data['fga_description'] : NULL,
            ':business' => is_numeric($Data['business']) ? $Data['business'] : 0,
            ':branch' => is_numeric($Data['branch']) ? $Data['branch'] : 0,
            //':fga_createby' => isset($Data['fga_createby']) ? $Data['fga_createby'] : NULL
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

        }else{
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se puedo registrar el grupo de activo'
            );

        }
        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Grupo de activo fijo registrado con exito'
        );

        $this->response($respuesta);
    }
    //OBTENER GRUPO DE AMOTIZACION
    public function getAmortizationGroup_get()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM afag";
        $resSql = $this->pedeo->queryTable($sql,array());

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }
        
        $this->response($respuesta);
    }
    //CREAR GRUPO AMORTIZACION
    public function createAmortizationGroup_post()
    {

        $Data = $this->post();

        if(!isset($Data['business']) OR !isset($Data['branch'])){

            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Información enviada inválida'
            );

            return $this->response($respuesta);
        }

        //
        $insert = "INSERT INTO afag (fag_code, fag_description, fag_group, business, branch)
                    VALUES (:fag_code,:fag_description,:fag_group,:business,:branch)";

        $resInsert = $this->pedeo->insertRow($insert,array(
            ':fag_code' => isset($Data['fag_code']) ? $Data['fag_code'] : NULL,
            ':fag_description' => isset($Data['fag_description']) ? $Data['fag_description'] : NULL,
            ':fag_group' => is_numeric($Data['fag_group']) ? $Data['fag_group'] : 0,
            //':fag_createby' => is_numeric($Data['fag_createby']) ? $Data['fag_createby'] : 0,
            ':business' => isset($Data['business']) ? $Data['business'] : NULL,
            ':branch' => isset($Data['branch']) ? $Data['branch'] : NULL
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

        }else{
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se puedo registrar el grupo de activo'
            );

        }
        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Grupo de activo fijo registrado con exito'
        );

        $this->response($respuesta);
    }

}
