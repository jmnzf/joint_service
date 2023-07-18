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

	}


    //CREAR COMPLEMENTO DE ACTIVO FIJO
    public function createFixedAsset_post()
    {
        $Data = $this->post();
        //CALIDAR QUE VENGAN LOS CAMPOS REQUERIDOS
        if(!isset($Data['mac_item_code']) or
        !isset($Data['mac_status']) or
        !isset($Data['mac_class_af']) or 
        !isset($Data['business']) or
        !isset($Data['branch'])){

            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Información enviada invalida'
            );
            $this->response($respuesta,REST_Controller::HTTP_BAD_REQUEST);
            return;
        }
        //INSERT
        $insert = "INSERT INTO dmac (mac_item_code, mac_status, mac_class_af, mac_group_af, 
        mac_group_amortization, mac_ubication, mac_num_serie, mac_location, mac_technical, 
        mac_employee, mac_area_valoration, mac_period, mac_historical_cap, 
        mac_acquisition_production_cost, mac_contab_net_value, mac_net_value_contab, mac_n_amortization, 
        mac_amortization_no_plan, mac_special_amortization, mac_revalorization, mac_salvage_value, mac_quantity, mac_createby, 
        business, branch)
        VALUES (:mac_item_code, :mac_status, :mac_class_af, :mac_group_af, :mac_group_amortization, :mac_ubication, :mac_num_serie, 
        :mac_location, :mac_technical, :mac_employee, :mac_area_valoration, :mac_period, :mac_historical_cap, 
        :mac_acquisition_production_cost, :mac_contab_net_value, :mac_net_value_contab, :mac_n_amortization, :mac_amortization_no_plan, 
        :mac_special_amortization, :mac_revalorization, :mac_salvage_value, :mac_quantity, :mac_createby, :business, :branch)";

        $resInsert = $this->pedeo->insertRow($insert,array(
            ':mac_item_code' => isset($Data['mac_item_code']) ? $Data['mac_item_code'] : NULL, 
            ':mac_status' => is_numeric($Data['mac_status']) ? $Data['mac_status'] : 0, 
            ':mac_class_af' => isset($Data['mac_class_af']) ? $Data['mac_class_af'] : NULL, 
            ':mac_group_af' => is_numeric($Data['mac_group_af']) ? $Data['mac_group_af'] : 0, 
            ':mac_group_amortization' => is_numeric($Data['mac_group_amortization']) ? $Data['mac_group_amortization'] : 0, 
            ':mac_ubication' => isset($Data['mac_ubication']) ? $Data['mac_ubication'] : NULL, 
            ':mac_num_serie' => is_numeric($Data['mac_num_serie']) ? $Data['mac_num_serie'] : 0, 
            ':mac_location' => is_numeric($Data['mac_location']) ? $Data['mac_location'] : 0, 
            ':mac_technical' => isset($Data['mac_technical']) ? $Data['mac_technical'] : NULL, 
            ':mac_employee' => is_numeric($Data['mac_employee']) ? $Data['mac_employee'] : 0, 
            //':mac_date_capitalization' => is_numeric($Data['mac_date_capitalization']) ? $Data['mac_date_capitalization'] : 0, 
            ':mac_area_valoration' => is_numeric($Data['mac_area_valoration']) ? $Data['mac_area_valoration'] : 0, 
            ':mac_period' => is_numeric($Data['mac_period']) ? $Data['mac_period'] : 0, 
            ':mac_historical_cap' => is_numeric($Data['mac_historical_cap']) ? $Data['mac_historical_cap'] : 0, 
            ':mac_acquisition_production_cost' => is_numeric($Data['mac_acquisition_production_cost']) ? $Data['mac_acquisition_production_cost'] : 0, 
            ':mac_contab_net_value' => is_numeric($Data['mac_contab_net_value']) ? $Data['mac_contab_net_value'] : 0, 
            ':mac_net_value_contab' => is_numeric($Data['mac_net_value_contab']) ? $Data['mac_net_value_contab'] : 0, 
            ':mac_n_amortization' => is_numeric($Data['mac_n_amortization']) ? $Data['mac_n_amortization'] : 0, 
            ':mac_amortization_no_plan' => is_numeric($Data['mac_amortization_no_plan']) ? $Data['mac_amortization_no_plan'] : 0, 
            ':mac_special_amortization' => is_numeric($Data['mac_special_amortization']) ? $Data['mac_special_amortization'] : 0, 
            ':mac_revalorization' => is_numeric($Data['mac_revalorization']) ? $Data['mac_revalorization'] : 0, 
            ':mac_salvage_value' => is_numeric($Data['mac_salvage_value']) ? $Data['mac_salvage_value'] : 0, 
            ':mac_quantity' => is_numeric($Data['mac_quantity']) ? $Data['mac_quantity'] : 0, 
            ':mac_createby' => isset($Data['mac_createby']) ? $Data['mac_createby'] : NULL, 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch']
        ));

        if(is_numeric($resInsert) && $resInsert > 0){

        }else{
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo guardar el complemento del activo fijo'
            );
            $this->response($respuesta);
            return;
        }

        $respuesta = array(
            'error' => true,
            'data' => $resInsert,
            'mensaje' => 'Complemento de activo fijo registrado con exito'
        );

        $this->response($respuesta);
    }
    //OBTENER DATOS DE COMPLEMENTO DE ACTIVO FIJO
    public function getFixedAsset_get()
    {
        $Data = $this->get();
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );
        //
        if(!isset($Data['mac_item_code'])){

            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion enviada invalida'
            );

            $this->response($respuesta,REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $sql = "SELECT * FROM dmac WHERE mac_item_code = :mac_item_code";
        $resSql = $this->pedeo->queryTable($sql,array(
            ':mac_item_code' => $Data['mac_item_code']
        ));

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }

        $this->response($respuesta);
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
        $insert = "INSERT INTO afca (fca_code,fca_description, fca_type, fca_avaloration, fca_camortization, fca_lifetime, business, branch)
                    VALUES (:fca_code,:fca_description,:fca_type,:fca_avaloration,:fca_camortization,:fca_lifetime,:business,:branch)";

        $resInsert = $this->pedeo->insertRow($insert,array(
            ':fca_code' => $Data['fca_code'],
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
    //

}
