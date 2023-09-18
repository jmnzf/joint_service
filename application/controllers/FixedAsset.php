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
    /**
     * AREAS DE VALORACION ACTIVO FIJO
     */
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
    //CREAR CAPITALIZACION
    public function createCapitalization_post()
    {
        $Data = $this->post();
        //
        $contenidoDetalle = json_decode($Data['detail'],true);
        //
        if (!isset($Data['business']) or 
             !isset($Data['branch'])){
                
                $respuesta = array(
                    'error' => true,
                    'data' => [],
                    'mensaje' => 'Informacion enviada no valida'
                );

                $this->response($respuesta);
                return;
        }

        //
        if (!isset($contenidoDetalle[0])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Documento sin detalle'
            );

            $this->response($respuesta);
            return;
        }
        //
        $insert = "INSERT INTO afcp (fcp_docnum, fcp_status, fcp_docdate, fcp_taxdate, fcp_dateval, fcp_areaval, fcp_ref, 
        fcp_comments, fcp_doctotal, business, branch,fcp_doctype)
        VALUES (:fcp_docnum, :fcp_status, :fcp_docdate, :fcp_taxdate, :fcp_dateval, :fcp_areaval, :fcp_ref, :fcp_comments, :fcp_doctotal, 
        :business, :branch,:fcp_doctype)";
        //
        $this->pedeo->trans_begin();
        //
        $resInsert = $this->pedeo->insertRow($insert,array(
            ':fcp_docnum' => is_numeric($Data['fcp_docnum']) ? $Data['fcp_docnum'] : 0, 
            ':fcp_status' => is_numeric($Data['fcp_status']) ? $Data['fcp_status'] : 0, 
            ':fcp_docdate' => $this->validateDate($Data['fcp_docdate']) ? $Data['fcp_docdate'] : NULL, 
            ':fcp_taxdate' => $this->validateDate($Data['fcp_taxdate']) ? $Data['fcp_taxdate'] : NULL, 
            ':fcp_dateval' => $this->validateDate($Data['fcp_dateval']) ? $Data['fcp_dateval'] : NULL, 
            ':fcp_areaval' => is_numeric($Data['fcp_areaval']) ? $Data['fcp_areaval'] : 0,  
            ':fcp_ref' => isset($Data['fcp_ref']) ? $Data['fcp_ref'] : NULL, 
            ':fcp_comments' => isset($Data['fcp_comments']) ? $Data['fcp_comments'] : NULL, 
            ':fcp_doctotal' => is_numeric($Data['fcp_doctotal']) ? $Data['fcp_doctotal'] : 0, 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'],
            ':fcp_doctype' => is_numeric($Data['fcp_doctype']) ? $Data['fcp_doctype'] : 0, 
        ));
        //
        if (is_numeric($resInsert) && $resInsert > 0){
            $insertDetail = "INSERT INTO fcp1 (cp1_docentry, cp1_itemcode, cp1_description, cp1_total, cp1_quantity, cp1_comments)
            VALUES(:cp1_docentry, :cp1_itemcode, :cp1_description, :cp1_total, :cp1_quantity, :cp1_comments)";

            foreach ($contenidoDetalle as $key => $detail) {
                $resInsertDetail = $this->pedeo->insertRow($insertDetail,array(
                    ':cp1_docentry' => $resInsert, 
                    ':cp1_itemcode' => isset($detail['cp1_itemcode']) ? $detail['cp1_itemcode'] : NULL, 
                    ':cp1_description' => isset($detail['cp1_description']) ? $detail['cp1_description'] : NULL, 
                    ':cp1_total' => is_numeric($detail['cp1_total']) ? $detail['cp1_total'] : 0, 
                    ':cp1_quantity' => is_numeric($detail['cp1_quantity']) ? $detail['cp1_quantity'] : 0, 
                    ':cp1_comments' => isset($detail['cp1_comments']) ? $detail['cp1_comments'] : NULL
                ));

                if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

                }else {
                    $this->pedeo->trans_rollback();
                    //
                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsertDetail,
                        'mensaje' => 'No se pudo registrar el detalle de capitalizacion'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }
            

        }else{
            $this->pedeo->trans_rollback();
            //
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo registrar la capitalizacion del activo fijo'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			return;
        }
        $this->pedeo->trans_commit();
        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Se registro con exito la capitalizacion'
        );

        $this->response($respuesta);        

    }
        //OBTENER POR ID
	public function getCapitalizationById_get()
	{

		$Data = $this->get();

		if (!isset($Data['fcp_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM afcp WHERE fcp_docentry =:fcp_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":fcp_docentry" => $Data['fcp_docentry']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

    //OBTENER DATOS DE CAPITALIZACION
    public function getCapitalization_get()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM afcp";
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
    public function getDetailCapitalization_get()
    {
        $Data = $this->get();
        if(!isset($Data['cp1_docentry'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion enviada invalida'
            );
        }

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM fcp1 WHERE cp1_docentry = :cp1_docentry";
        $resSql = $this->pedeo->queryTable($sql,array(':cp1_docentry' => $Data['cp1_docentry']));

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }
        
        $this->response($respuesta);
    }
    //CREAR NC DE CAPITALIZACION
    public function createNoteCreditCap_post()
    {
        $Data = $this->post();
        //
        $contenidoDetalle = json_decode($Data['detail'],true);
        //
        if (!isset($Data['business']) or 
             !isset($Data['branch'])){
                
                $respuesta = array(
                    'error' => true,
                    'data' => [],
                    'mensaje' => 'Informacion enviada no valida'
                );

                $this->response($respuesta);
                return;
        }

        //
        if (!isset($contenidoDetalle[0])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Documento sin detalle'
            );

            $this->response($respuesta);
            return;
        }
        //
        $insert = "INSERT INTO afnc (fnc_docnum, fnc_status, fnc_docdate, fnc_taxdate, fnc_dateval, fnc_areaval, fnc_ref, 
        fnc_comments, fnc_doctotal, business, branch,fnc_doctype)
        VALUES (:fnc_docnum, :fnc_status, :fnc_docdate, :fnc_taxdate, :fnc_dateval, :fnc_areaval, :fnc_ref, :fnc_comments, :fnc_doctotal, 
        :business, :branch,:fnc_doctype)";
        //
        $this->pedeo->trans_begin();
        //
        $resInsert = $this->pedeo->insertRow($insert,array(
            ':fnc_docnum' => is_numeric($Data['fnc_docnum']) ? $Data['fnc_docnum'] : 0, 
            ':fnc_status' => is_numeric($Data['fnc_status']) ? $Data['fnc_status'] : 0, 
            ':fnc_docdate' => $this->validateDate($Data['fnc_docdate']) ? $Data['fnc_docdate'] : NULL, 
            ':fnc_taxdate' => $this->validateDate($Data['fnc_taxdate']) ? $Data['fnc_taxdate'] : NULL, 
            ':fnc_dateval' => $this->validateDate($Data['fnc_dateval']) ? $Data['fnc_dateval'] : NULL, 
            ':fnc_areaval' => is_numeric($Data['fnc_areaval']) ? $Data['fnc_areaval'] : 0,  
            ':fnc_ref' => isset($Data['fnc_ref']) ? $Data['fnc_ref'] : NULL, 
            ':fnc_comments' => isset($Data['fnc_comments']) ? $Data['fnc_comments'] : NULL, 
            ':fnc_doctotal' => is_numeric($Data['fnc_doctotal']) ? $Data['fnc_doctotal'] : 0, 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'],
            ':fnc_doctype' => is_numeric($Data['fnc_doctype']) ? $Data['fnc_doctype'] : 0, 
        ));
        //
        if (is_numeric($resInsert) && $resInsert > 0){
            $insertDetail = "INSERT INTO fnc1 (nc1_docentry, nc1_itemcode, nc1_description, nc1_total, nc1_quantity, nc1_comments)
            VALUES(:nc1_docentry, :nc1_itemcode, :nc1_description, :nc1_total, :nc1_quantity, :nc1_comments)";

            foreach ($contenidoDetalle as $key => $detail) {
                $resInsertDetail = $this->pedeo->insertRow($insertDetail,array(
                    ':nc1_docentry' => $resInsert, 
                    ':nc1_itemcode' => isset($detail['nc1_itemcode']) ? $detail['nc1_itemcode'] : NULL, 
                    ':nc1_description' => isset($detail['nc1_description']) ? $detail['nc1_description'] : NULL, 
                    ':nc1_total' => is_numeric($detail['nc1_total']) ? $detail['nc1_total'] : 0, 
                    ':nc1_quantity' => is_numeric($detail['nc1_quantity']) ? $detail['nc1_quantity'] : 0, 
                    ':nc1_comments' => isset($detail['nc1_comments']) ? $detail['nc1_comments'] : NULL
                ));

                if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

                }else {
                    $this->pedeo->trans_rollback();
                    //
                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsertDetail,
                        'mensaje' => 'No se pudo registrar el detalle de capitalizacion'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }
            

        }else{
            $this->pedeo->trans_rollback();
            //
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo registrar la capitalizacion del activo fijo'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			return;
        }
        $this->pedeo->trans_commit();
        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Se registro con exito la nota credito de capitalizacion'
        );

        $this->response($respuesta);        

    }
    //
    public function getNoteCreditCap_get()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM afnc";
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
    public function getNoteCreditCapById_get()
	{

		$Data = $this->get();

		if (!isset($Data['fnc_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM afnc WHERE fnc_docentry =:fnc_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":fnc_docentry" => $Data['fnc_docentry']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}
    public function getDetailNoteCreditCap_get()
    {
        $Data = $this->get();
        if(!isset($Data['nc1_docentry'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion enviada invalida'
            );
        }

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM fnc1 WHERE nc1_docentry = :nc1_docentry";
        $resSql = $this->pedeo->queryTable($sql,array(':nc1_docentry' => $Data['nc1_docentry']));

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }
        
        $this->response($respuesta);
    }
    //CREAR BAJA DE ACTIVO FIJO
    public function createLow_post()
    {
        $Data = $this->post();
        //
        $contenidoDetalle = json_decode($Data['detail'],true);
        //
        if (!isset($Data['business']) or 
             !isset($Data['branch'])){
                
                $respuesta = array(
                    'error' => true,
                    'data' => [],
                    'mensaje' => 'Informacion enviada no valida'
                );

                $this->response($respuesta);
                return;
        }

        //
        if (!isset($contenidoDetalle[0])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Documento sin detalle'
            );

            $this->response($respuesta);
            return;
        }
        //
        $insert = "INSERT INTO afba (fba_docnum, fba_status, fba_docdate, fba_taxdate, fba_dateval, fba_areaval, fba_ref, 
        fba_comments, fba_doctotal, business, branch,fba_trans_type,fba_cardcode,fba_doctype)
        VALUES (:fba_docnum, :fba_status, :fba_docdate, :fba_taxdate, :fba_dateval, :fba_areaval, :fba_ref, :fba_comments, :fba_doctotal, 
        :business, :branch,:fba_trans_type,:fba_cardcode,:fba_doctype)";
        //
        $this->pedeo->trans_begin();
        //
        $resInsert = $this->pedeo->insertRow($insert,array(
            ':fba_docnum' => is_numeric($Data['fba_docnum']) ? $Data['fba_docnum'] : 0, 
            ':fba_status' => is_numeric($Data['fba_status']) ? $Data['fba_status'] : 0, 
            ':fba_docdate' => $this->validateDate($Data['fba_docdate']) ? $Data['fba_docdate'] : NULL, 
            ':fba_taxdate' => $this->validateDate($Data['fba_taxdate']) ? $Data['fba_taxdate'] : NULL, 
            ':fba_dateval' => $this->validateDate($Data['fba_dateval']) ? $Data['fba_dateval'] : NULL, 
            ':fba_areaval' => is_numeric($Data['fba_areaval']) ? $Data['fba_areaval'] : 0,  
            ':fba_ref' => isset($Data['fba_ref']) ? $Data['fba_ref'] : NULL, 
            ':fba_comments' => isset($Data['fba_comments']) ? $Data['fba_comments'] : NULL, 
            ':fba_doctotal' => is_numeric($Data['fba_doctotal']) ? $Data['fba_doctotal'] : 0, 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'],
            ':fba_trans_type' => isset($Data['fba_trans_type']) ? $Data['fba_trans_type'] : NULL, 
            ':fba_cardcode' => isset($Data['fba_cardcode']) ? $Data['fba_cardcode'] : NULL,
            ':fba_doctype' => is_numeric($Data['fba_doctype']) ? $Data['fba_doctype'] : 0, 
        ));
        //
        if (is_numeric($resInsert) && $resInsert > 0){
            $insertDetail = "INSERT INTO fba1 (ba1_docentry, ba1_itemcode, ba1_description, ba1_total, ba1_parcial, ba1_quantity, ba1_cap, 
            ba1_activity, ba1_depto, ba1_ccost, ba1_project, ba1_comments)
            VALUES(:ba1_docentry, :ba1_itemcode, :ba1_description, :ba1_total, :ba1_parcial, :ba1_quantity, :ba1_cap, :ba1_activity, :ba1_depto, 
            :ba1_ccost, :ba1_project, :ba1_comments)";

            foreach ($contenidoDetalle as $key => $detail) {
                $resInsertDetail = $this->pedeo->insertRow($insertDetail,array(
                    ':ba1_docentry' => $resInsert, 
                    ':ba1_itemcode' => isset($detail['ba1_itemcode']) ? $detail['ba1_itemcode'] : NULL, 
                    ':ba1_description' => isset($detail['ba1_description']) ? $detail['ba1_description'] : NULL, 
                    ':ba1_total' => is_numeric($detail['ba1_total']) ? $detail['ba1_total'] : 0, 
                    ':ba1_parcial' => is_numeric($detail['ba1_parcial']) ? $detail['ba1_parcial'] : 0, 
                    ':ba1_quantity' => is_numeric($detail['ba1_quantity']) ? $detail['ba1_quantity'] : 0, 
                    ':ba1_cap' => isset($detail['ba1_cap']) ? $detail['ba1_cap'] : NULL, 
                    ':ba1_activity' => isset($detail['ba1_activity']) ? $detail['ba1_activity'] : NULL, 
                    ':ba1_depto' => isset($detail['ba1_depto']) ? $detail['ba1_depto'] : NULL, 
                    ':ba1_ccost' => isset($detail['ba1_ccost']) ? $detail['ba1_ccost'] : NULL, 
                    ':ba1_project' => isset($detail['ba1_project']) ? $detail['ba1_project'] : NULL, 
                    ':ba1_comments' => isset($detail['ba1_comments']) ? $detail['ba1_comments'] : NULL
                ));

                if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

                }else {
                    $this->pedeo->trans_rollback();
                    //
                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsertDetail,
                        'mensaje' => 'No se pudo registrar el detalle de la baja'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }
            

        }else{
            $this->pedeo->trans_rollback();
            //
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo registrar la baja del activo fijo'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			return;
        }
        $this->pedeo->trans_commit();
        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Se registro con exito la baja del activo fijo'
        );

        $this->response($respuesta);        

    }
    //
    public function getLow_get()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM afba";
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
    public function getLowById_get()
	{

		$Data = $this->get();

		if (!isset($Data['fba_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM afba WHERE fba_docentry =:fba_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":fba_docentry" => $Data['fba_docentry']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}
    public function getDetailLow_get()
    {
        $Data = $this->get();
        if(!isset($Data['ba1_docentry'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion enviada invalida'
            );
        }

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM fba1 WHERE ba1_docentry = :ba1_docentry";
        $resSql = $this->pedeo->queryTable($sql,array(':ba1_docentry' => $Data['ba1_docentry']));

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }
        
        $this->response($respuesta);
    }
    //CREAR DEPRECIOACION MANUAL
    public function createManualDepreciation_post()
    {
        $Data = $this->post();
        //
        $contenidoDetalle = json_decode($Data['detail'],true);
        //
        if (!isset($Data['business']) or 
             !isset($Data['branch'])){
                
                $respuesta = array(
                    'error' => true,
                    'data' => [],
                    'mensaje' => 'Informacion enviada no valida'
                );

                $this->response($respuesta);
                return;
        }

        //
        if (!isset($contenidoDetalle[0])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Documento sin detalle'
            );

            $this->response($respuesta);
            return;
        }
        //
        $insert = "INSERT INTO afma (fma_docnum, fma_status, fma_docdate, fma_taxdate, fma_dateval, fma_areaval, fma_ref, 
        fma_comments, fma_doctotal, business, branch,fma_doctype)
        VALUES (:fma_docnum, :fma_status, :fma_docdate, :fma_taxdate, :fma_dateval, :fma_areaval, :fma_ref, :fma_comments, :fma_doctotal, 
        :business, :branch,:fma_doctype)";
        //
        $this->pedeo->trans_begin();
        //
        $resInsert = $this->pedeo->insertRow($insert,array(
            ':fma_docnum' => is_numeric($Data['fma_docnum']) ? $Data['fma_docnum'] : 0, 
            ':fma_status' => is_numeric($Data['fma_status']) ? $Data['fma_status'] : 0, 
            ':fma_docdate' => $this->validateDate($Data['fma_docdate']) ? $Data['fma_docdate'] : NULL, 
            ':fma_taxdate' => $this->validateDate($Data['fma_taxdate']) ? $Data['fma_taxdate'] : NULL, 
            ':fma_dateval' => $this->validateDate($Data['fma_dateval']) ? $Data['fma_dateval'] : NULL, 
            ':fma_areaval' => is_numeric($Data['fma_areaval']) ? $Data['fma_areaval'] : 0,  
            ':fma_ref' => isset($Data['fma_ref']) ? $Data['fma_ref'] : NULL, 
            ':fma_comments' => isset($Data['fma_comments']) ? $Data['fma_comments'] : NULL, 
            ':fma_doctotal' => is_numeric($Data['fma_doctotal']) ? $Data['fma_doctotal'] : 0, 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'],
            ':fma_doctype' => is_numeric($Data['fma_doctype']) ? $Data['fma_doctype'] : 0, 
        ));
        //
        if (is_numeric($resInsert) && $resInsert > 0){
            $insertDetail = "INSERT INTO fma1 (ma1_docentry, ma1_itemcode, ma1_description, ma1_total, ma1_activity, ma1_depto, ma1_ccost, 
            ma1_project, ma1_comments)
            VALUES(:ma1_docentry, :ma1_itemcode, :ma1_description, :ma1_total, :ma1_activity, :ma1_depto, :ma1_ccost, :ma1_project, :ma1_comments)";

            foreach ($contenidoDetalle as $key => $detail) {
                $resInsertDetail = $this->pedeo->insertRow($insertDetail,array(
                    ':ma1_docentry' => $resInsert, 
                    ':ma1_itemcode' => isset($detail['ma1_itemcode']) ? $detail['ma1_itemcode'] : NULL, 
                    ':ma1_description' => isset($detail['ma1_description']) ? $detail['ma1_description'] : NULL, 
                    ':ma1_total' => is_numeric($detail['ma1_total']) ? $detail['ma1_total'] : 0, 
                    ':ma1_activity' => isset($detail['ma1_activity']) ? $detail['ma1_activity'] : NULL, 
                    ':ma1_depto' => isset($detail['ma1_depto']) ? $detail['ma1_depto'] : NULL, 
                    ':ma1_ccost' => isset($detail['ma1_ccost']) ? $detail['ma1_ccost'] : NULL, 
                    ':ma1_project' => isset($detail['ma1_project']) ? $detail['ma1_project'] : NULL, 
                    ':ma1_comments' => isset($detail['ma1_comments']) ? $detail['ma1_comments'] : NULL
                ));

                if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

                }else {
                    $this->pedeo->trans_rollback();
                    //
                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsertDetail,
                        'mensaje' => 'No se pudo registrar el detalle de la amortizacion manual'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }
            

        }else{
            $this->pedeo->trans_rollback();
            //
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo registrar la amortizacion manual'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			return;
        }
        $this->pedeo->trans_commit();
        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Se registro con exito la amortizacion manual'
        );

        $this->response($respuesta);        

    }
    //
    public function getManualDepreciation_get()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM afma";
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
    public function getManualDepreciationyById_get()
	{

		$Data = $this->get();

		if (!isset($Data['fma_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM afma WHERE fma_docentry =:fma_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":fma_docentry" => $Data['fma_docentry']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}
    public function getDetailManualDepreciation_get()
    {
        $Data = $this->get();
        if(!isset($Data['ma1_docentry'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion enviada invalida'
            );
        }

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM fma1 WHERE ma1_docentry = :ma1_docentry";
        $resSql = $this->pedeo->queryTable($sql,array(':ma1_docentry' => $Data['ma1_docentry']));

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }
        
        $this->response($respuesta);
    }
    //CREAR TRASPASO
    public function createTransfer_post()
    {
        $Data = $this->post();
        //
        $contenidoDetalle = json_decode($Data['detail'],true);
        //
        if (!isset($Data['business']) or 
             !isset($Data['branch'])){
                
                $respuesta = array(
                    'error' => true,
                    'data' => [],
                    'mensaje' => 'Informacion enviada no valida'
                );

                $this->response($respuesta);
                return;
        }

        //
        if (!isset($contenidoDetalle[0])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Documento sin detalle'
            );

            $this->response($respuesta);
            return;
        }
        //
        $insert = "INSERT INTO aftf (ftf_docnum, ftf_status, ftf_docdate, ftf_taxdate, ftf_dateval, ftf_areaval, ftf_ref, 
        ftf_comments, ftf_doctotal, business, branch,ftf_trans_type,ftf_cardcode,ftf_doctype)
        VALUES (:ftf_docnum, :ftf_status, :ftf_docdate, :ftf_taxdate, :ftf_dateval, :ftf_areaval, :ftf_ref, :ftf_comments, :ftf_doctotal, 
        :business, :branch,:ftf_trans_type,:ftf_cardcode,:ftf_doctype)";
        //
        $this->pedeo->trans_begin();
        //
        $resInsert = $this->pedeo->insertRow($insert,array(
            ':ftf_docnum' => is_numeric($Data['ftf_docnum']) ? $Data['ftf_docnum'] : 0, 
            ':ftf_status' => is_numeric($Data['ftf_status']) ? $Data['ftf_status'] : 0, 
            ':ftf_docdate' => $this->validateDate($Data['ftf_docdate']) ? $Data['ftf_docdate'] : NULL, 
            ':ftf_taxdate' => $this->validateDate($Data['ftf_taxdate']) ? $Data['ftf_taxdate'] : NULL, 
            ':ftf_dateval' => $this->validateDate($Data['ftf_dateval']) ? $Data['ftf_dateval'] : NULL, 
            ':ftf_areaval' => is_numeric($Data['ftf_areaval']) ? $Data['ftf_areaval'] : 0,  
            ':ftf_ref' => isset($Data['ftf_ref']) ? $Data['ftf_ref'] : NULL, 
            ':ftf_comments' => isset($Data['ftf_comments']) ? $Data['ftf_comments'] : NULL, 
            ':ftf_doctotal' => is_numeric($Data['ftf_doctotal']) ? $Data['ftf_doctotal'] : 0, 
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'],
            ':ftf_trans_type' => isset($Data['ftf_trans_type']) ? $Data['ftf_trans_type'] : NULL, 
            ':ftf_cardcode' => isset($Data['ftf_cardcode']) ? $Data['ftf_cardcode'] : NULL,
            ':ftf_doctype' => is_numeric($Data['ftf_doctype']) ? $Data['ftf_doctype'] : 0, 
        ));
        //
        if (is_numeric($resInsert) && $resInsert > 0){
            $insertDetail = "INSERT INTO ftf1 (tf1_docentry, tf1_itemcode, tf1_description, tf1_itemcode_dest, tf1_description_dest, tf1_total, tf1_parcial, tf1_quantity, tf1_cap, 
            tf1_comments)
            VALUES(:tf1_docentry, :tf1_itemcode, :tf1_description, :tf1_itemcode_dest, :tf1_description_dest, :tf1_total, :tf1_parcial, :tf1_quantity, :tf1_cap, :tf1_comments)";

            foreach ($contenidoDetalle as $key => $detail) {
                $resInsertDetail = $this->pedeo->insertRow($insertDetail,array(
                    ':tf1_docentry' => $resInsert, 
                    ':tf1_itemcode' => isset($detail['tf1_itemcode']) ? $detail['tf1_itemcode'] : NULL, 
                    ':tf1_description' => isset($detail['tf1_description']) ? $detail['tf1_description'] : NULL, 
                    ':tf1_itemcode_dest' => isset($detail['tf1_itemcode_dest']) ? $detail['tf1_itemcode_dest'] : NULL, 
                    ':tf1_description_dest' => isset($detail['tf1_description_dest']) ? $detail['tf1_description_dest'] : NULL, 
                    ':tf1_total' => is_numeric($detail['tf1_total']) ? $detail['tf1_total'] : 0, 
                    ':tf1_parcial' => is_numeric($detail['tf1_parcial']) ? $detail['tf1_parcial'] : 0, 
                    ':tf1_quantity' => is_numeric($detail['tf1_quantity']) ? $detail['tf1_quantity'] : 0, 
                    ':tf1_cap' => isset($detail['tf1_cap']) ? $detail['tf1_cap'] : NULL,  
                    ':tf1_comments' => isset($detail['tf1_comments']) ? $detail['tf1_comments'] : NULL
                ));

                if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

                }else {
                    $this->pedeo->trans_rollback();
                    //
                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsertDetail,
                        'mensaje' => 'No se pudo registrar el detalle del traspaso'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }
            

        }else{
            $this->pedeo->trans_rollback();
            //
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo registrar el traspaso'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			return;
        }
        $this->pedeo->trans_commit();
        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Se registro con exito el traspaso'
        );

        $this->response($respuesta);        

    }
    //
    public function getTransfer_get()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM aftf";
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
    public function getTransferById_get()
	{

		$Data = $this->get();

		if (!isset($Data['ftf_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM aftf WHERE ftf_docentry =:ftf_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":ftf_docentry" => $Data['ftf_docentry']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}
    public function getDetailTransfer_get()
    {
        $Data = $this->get();
        if(!isset($Data['tf1_docentry'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion enviada invalida'
            );
        }

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM ftf1 WHERE tf1_docentry = :tf1_docentry";
        $resSql = $this->pedeo->queryTable($sql,array(':tf1_docentry' => $Data['tf1_docentry']));

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }
        
        $this->response($respuesta);
    }
    //CREAR REVALORIZACION
    public function createRevalorization_post()
    {
        $Data = $this->post();
        //
        $contenidoDetalle = json_decode($Data['detail'],true);
        //
        if (!isset($Data['business']) or 
             !isset($Data['branch'])){
                
                $respuesta = array(
                    'error' => true,
                    'data' => [],
                    'mensaje' => 'Informacion enviada no valida'
                );

                $this->response($respuesta);
                return;
        }

        //
        if (!isset($contenidoDetalle[0])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Documento sin detalle'
            );

            $this->response($respuesta);
            return;
        }
        //
        $insert = "INSERT INTO afrv (frv_docnum, frv_docdate, frv_taxdate, frv_dateval, frv_ref, frv_comments, business, branch, frv_createby, 
        frv_areaval, frv_cardcode, frv_porcent,frv_doctype)
        VALUES (:frv_docnum, :frv_docdate, :frv_taxdate, :frv_dateval, :frv_ref, :frv_comments, :business, :branch, :frv_createby, 
        :frv_areaval, :frv_cardcode,:frv_porcent,:frv_doctype)";
        //
        $this->pedeo->trans_begin();
        //
        $resInsert = $this->pedeo->insertRow($insert,array(
            ':frv_docnum' => is_numeric($Data['frv_docnum']) ? $Data['frv_docnum'] : 0, 
            ':frv_docdate' => $this->validateDate($Data['frv_docdate']) ? $Data['frv_docdate'] : NULL, 
            ':frv_taxdate' => $this->validateDate($Data['frv_taxdate']) ? $Data['frv_taxdate'] : NULL, 
            ':frv_dateval' => $this->validateDate($Data['frv_dateval']) ? $Data['frv_dateval'] : NULL, 
            ':frv_ref' => isset($Data['frv_ref']) ? $Data['frv_ref'] : NULL, 
            ':frv_comments' => isset($Data['frv_comments']) ? $Data['frv_comments'] : NULL,  
            ':business' => $Data['business'], 
            ':branch' => $Data['branch'],
            ':frv_createby' => isset($Data['frv_createby']) ? $Data['frv_createby'] : NULL, 
            ':frv_areaval' => isset($Data['frv_areaval']) ? $Data['frv_areaval'] : NULL,
            ':frv_cardcode' => isset($Data['frv_cardcode']) ? $Data['frv_cardcode'] : NULL,
            ':frv_porcent' => isset($Data['frv_porcent']) ? $Data['frv_porcent'] : NULL,
            ':frv_doctype' => is_numeric($Data['frv_doctype']) ? $Data['frv_doctype'] : 0, 
        ));
        //
        if (is_numeric($resInsert) && $resInsert > 0){
            $insertDetail = "INSERT INTO frv1 (rv1_docentry, rv1_itemcode, rv1_description, rv1_vnc, rv1_porcent, rv1_vncnew, rv1_dif, rv1_comments)
            VALUES(:rv1_docentry, :rv1_itemcode, :rv1_description, :rv1_vnc, :rv1_porcent, :rv1_vncnew, :rv1_dif, :rv1_comments)";

            foreach ($contenidoDetalle as $key => $detail) {
                $resInsertDetail = $this->pedeo->insertRow($insertDetail,array(
                    ':rv1_docentry' => $resInsert, 
                    ':rv1_itemcode' => isset($detail['rv1_itemcode']) ? $detail['rv1_itemcode'] : NULL, 
                    ':rv1_description' => isset($detail['rv1_description']) ? $detail['rv1_description'] : NULL, 
                    ':rv1_vnc' => is_numeric($detail['rv1_vnc']) ? $detail['rv1_vnc'] : 0, 
                    ':rv1_porcent' => isset($detail['rv1_porcent']) ? $detail['rv1_porcent'] : NULL, 
                    ':rv1_vncnew' => is_numeric($detail['rv1_vncnew']) ? $detail['rv1_vncnew'] : 0, 
                    ':rv1_dif' => is_numeric($detail['rv1_dif']) ? $detail['rv1_dif'] : 0,  
                    ':rv1_comments' => isset($detail['rv1_comments']) ? $detail['rv1_comments'] : NULL
                ));

                if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

                }else {
                    $this->pedeo->trans_rollback();
                    //
                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsertDetail,
                        'mensaje' => 'No se pudo registrar el detalle de la revalorizacion'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }
            

        }else{
            $this->pedeo->trans_rollback();
            //
            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo registrar la revalorizacion'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			return;
        }
        $this->pedeo->trans_commit();
        $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Se registro con exito la revalorizacion'
        );

        $this->response($respuesta);        

    }
    //
    public function getRevalorization_get()
    {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM afrv";
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
    public function getRevalorizationById_get()
	{

		$Data = $this->get();

		if (!isset($Data['frv_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM afrv WHERE frv_docentry =:frv_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":frv_docentry" => $Data['frv_docentry']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}
    public function getDetailRevalorization_get()
    {
        $Data = $this->get();
        if(!isset($Data['rv1_docentry'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion enviada invalida'
            );
        }

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $sql = "SELECT * FROM frv1 WHERE rv1_docentry = :rv1_docentry";
        $resSql = $this->pedeo->queryTable($sql,array(':rv1_docentry' => $Data['rv1_docentry']));

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }
        
        $this->response($respuesta);
    }
    //VALIDACION DE FECHA
    private function validateDate($fecha)
	{
		if (strlen($fecha) == 10 or strlen($fecha) > 10) {
			return true;
		} else {
			return false;
		}
	}

}
