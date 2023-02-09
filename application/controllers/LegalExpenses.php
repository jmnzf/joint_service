<?php
// DATOS MAESTROS ALMACEN
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class LegalExpenses extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
        $this->load->library('DocumentNumbering');
		$this->load->library('Tasa');

	}

    //CREAR
	public function createLegalExpense_post(){


        $Data = $this->post();

        if(!isset($Data['blg_series']) or
            !isset($Data['blg_docnum']) or
            !isset($Data['blg_acc']) or
            !isset($Data['blg_docdate']) or
            !isset($Data['blg_duedate']) or
            !isset($Data['blg_duedev']) or
            !isset($Data['blg_currency']) or
            !isset($Data['blg_cardcode']) or
            !isset($Data['blg_cardname']) ){

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'La información enviada no es valida'
        );

        $this->response($respuesta,REST_Controller::HTTP_BAD_REQUEST);
        return;
      }

        //VALIDAR NUMERACION DE DOCUMENTO
        $DocNumVerificado = $this->documentnumbering->NumberDoc($Data['blg_series'],$Data['blg_docdate'],$Data['blg_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}
        //VALIDAR TASA
        $dataTasa = $this->tasa->Tasa($Data['blg_currency'],$Data['blg_docdate']);

		if(isset($dataTasa['tasaLocal'])){

			$TasaDocLoc = $dataTasa['tasaLocal'];
			$TasaLocSys = $dataTasa['tasaSys'];
			$MONEDALOCAL = $dataTasa['curLocal'];
			$MONEDASYS = $dataTasa['curSys'];
			
		}else if($dataTasa['error'] == true){

			$this->response($dataTasa, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

        if (!isset($Data['detail'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$ContenidoDetalle = json_decode($Data['detail'], true);


		if (!is_array($ContenidoDetalle)) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro el detalle de la legalización de gastos'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}
		// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
		if (!intval(count($ContenidoDetalle)) > 0) {
			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'Documento sin detalle'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

        $sqlInsert = "INSERT INTO tblg(blg_doctype,blg_series,blg_docnum,blg_acc,blg_docdate,blg_duedate,blg_duedev,blg_currency,blg_cardcode,blg_cardname,
		business,branch,blg_status,blg_comment, blg_doctotal,blg_taxtotal,blg_baseamnt, blg_createby)
        VALUES(:blg_doctype,:blg_series,:blg_docnum,:blg_acc,:blg_docdate,:blg_duedate,:blg_duedev,:blg_currency,:blg_cardcode,:blg_cardname,:business,
		:branch,:blg_status, :blg_comment ,  :blg_doctotal, :blg_taxtotal, :blg_baseamnt, :blg_createby)";

        $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert,array(
            ':blg_doctype' => isset($Data['blg_doctype']) ? $Data['blg_doctype'] : NULL,
            ':blg_series' => isset($Data['blg_series']) ? $Data['blg_series'] : NULL,
            ':blg_docnum' => isset($DocNumVerificado) ? $DocNumVerificado : 0,
            ':blg_acc' => isset($Data['blg_acc']) ? $Data['blg_acc'] : NULL,
            ':blg_docdate' => isset($Data['blg_docdate']) ? $Data['blg_docdate'] : NULL,
            ':blg_duedate' => isset($Data['blg_duedate']) ? $Data['blg_duedate'] : NULL,
            ':blg_duedev' => isset($Data['blg_duedev']) ? $Data['blg_duedev'] : NULL,
            ':blg_currency' => isset($Data['blg_currency']) ? $Data['blg_currency'] : NULL,
            ':blg_cardcode' => isset($Data['blg_cardcode']) ? $Data['blg_cardcode'] : NULL,
            ':blg_cardname' => isset($Data['blg_cardname']) ? $Data['blg_cardname'] : NULL,
            ':business' => isset($Data['business']) ? $Data['business'] : NULL,
            ':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
            ':blg_status' => 1,
			':blg_comment' => isset($Data['blg_comment']) ? $Data['blg_comment'] : NULL,
			':blg_doctotal' => $Data['blg_doctotal'],
			':blg_taxtotal' => $Data['blg_taxtotal'],
			':blg_baseamnt' => $Data['blg_baseamnt'],
			':blg_createby' => $Data['blg_createby']
		));

        if(is_numeric($resInsert) && $resInsert > 0){
            //INSERTAR DETALLE
            $sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['blg_series']
			));


			if (is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1) {
			} else {
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resActualizarNumeracion,
					'mensaje'	=> 'No se pudo crear la legalizacion de gastos'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
            foreach ($ContenidoDetalle as $key => $detail) {
                $sqlInsertDetail = "INSERT INTO blg1(lg1_docentry,lg1_linenum,lg1_cardcode,lg1_cardname,lg1_docdate,lg1_price,lg1_currency,lg1_account,lg1_ccost,
                lg1_ubussiness,lg1_proyect,lg1_vatsum,lg1_total,lg1_concept, lg1_codimp)
                VALUES(:lg1_docentry,:lg1_linenum,:lg1_cardcode,:lg1_cardname,:lg1_docdate,:lg1_price,:lg1_currency,:lg1_account,:lg1_ccost,
                :lg1_ubussiness,:lg1_proyect,:lg1_vatsum,:lg1_total, :lg1_concept, :lg1_codimp)";
                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail,array(
                    ':lg1_docentry' => $resInsert,
                    ':lg1_linenum' => is_numeric($detail['lg1_linenum']) ? $detail['lg1_linenum'] : 0,
                    ':lg1_cardcode' => isset($detail['lg1_cardcode']) ? $detail['lg1_cardcode'] : NULL,
                    ':lg1_cardname' => isset($detail['lg1_cardname']) ? $detail['lg1_cardname'] : NULL,
                    ':lg1_docdate' => isset($detail['lg1_docdate']) ? $detail['lg1_docdate'] : NULL,
                    ':lg1_price' => is_numeric($detail['lg1_price']) ? $detail['lg1_price'] : 0,
                    ':lg1_currency' => isset($detail['lg1_currency']) ? $detail['lg1_currency'] : NULL,
                    ':lg1_account' => isset($detail['lg1_account']) ? $detail['lg1_account'] : NULL,
                    ':lg1_ccost' => isset($detail['lg1_ccost']) ? $detail['lg1_ccost'] : NULL,
                    ':lg1_ubussiness' => isset($detail['lg1_ubussiness']) ? $detail['lg1_ubussiness'] : NULL,
                    ':lg1_proyect' => isset($detail['lg1_proyect']) ? $detail['lg1_proyect'] : NULL,
                    ':lg1_vatsum' => isset($detail['lg1_vatsum']) ? $detail['lg1_vatsum'] : NULL,
                    ':lg1_total' => is_numeric($detail['lg1_total']) ? $detail['lg1_total'] : 0,
                    ':lg1_concept' => isset($detail['lg1_concept']) ? $detail['lg1_concept'] : null,
                    ':lg1_codimp' => isset($detail['lg1_codimp']) ? $detail['lg1_codimp'] : null
                ));

                if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

                }else{
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'    => $resInsertDetail,
                        'mensaje'	=> 'No se puedo crear la legalizacion de gastos'
                    );
                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }

            }

            $this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Legalizacion de gastos, creada con exitó'
			);
        }else{

            $this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resInsert,
				'mensaje'	=> 'No se puedo la legalizacion de gastos'
			);
			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
			return;
        }

        $this->response($respuesta);

    }

    //UPDATE

    //OBTENER
    public function getLegalExpense_get(){
        $Data = $this->get();

		if ( !isset($Data['business']) OR !isset($Data['branch']) ) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT * FROM tblg WHERE business = :business AND branch = :branch";
        $resSelect = $this->pedeo->queryTable($sqlSelect,array(
            ':business' => $Data['business'],
            ':branch' => $Data['branch']
        ));

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

    //OBTENER POR ID
    public function getLegalExpenseById_get(){
        $Data = $this->get();

		if (!isset($Data['blg_docentry']) or !isset($Data['business']) or !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM tblg WHERE blg_docentry = :blg_docentry AND business = :business AND branch = :branch";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ":blg_docentry" => $Data['blg_docentry'],
            ":business" => $Data['business'],
            ":branch" => $Data['branch']
        ));

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

    //OBTENER DETALLE POR ID
    public function getLegalExpenseDetail_get(){
        $Data = $this->get();

		if (!isset($Data['lg1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT * FROM blg1 WHERE lg1_docentry = :lg1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":lg1_docentry" => $Data['lg1_docentry']));

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

    //OBTENER SALDO DE CUENTA
    public function getBalance_get(){

        $Data = $this->get();

        if(!isset($Data['business']) or !isset($Data['branch']) or !isset($Data['account']) or !isset($Data['cardcode'])){
            $respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
        }

        $sql = "SELECT  sum(ac1_debit-mac1.ac1_credit) AS saldo FROM mac1 WHERE ac1_legal_num = :cardcode AND ac1_account = :account AND business = :business AND branch = :branch";
        $resSql = $this->pedeo->queryTable($sql,array(
            ':business' => $Data['business'],
            ':branch' => $Data['branch'],
            ':account' => $Data['account'],
            ':cardcode' => $Data['cardcode']
        ));

        if (isset($resSql[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSql,
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

}
