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

        if(!isset($Data['lcm_series']) or
            !isset($Data['lcm_docnum']) or
            !isset($Data['lcm_acc']) or
            !isset($Data['lcm_docdate']) or
            !isset($Data['lcm_duedate']) or
            !isset($Data['lcm_duedev']) or
            !isset($Data['lcm_currency']) or
            !isset($Data['lcm_cardcode']) or
            !isset($Data['lcm_cardname']) ){

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'La información enviada no es valida'
        );

        $this->response($respuesta,REST_Controller::HTTP_BAD_REQUEST);
        return;
      }

        //VALIDAR NUMERACION DE DOCUMENTO
        $DocNumVerificado = $this->documentnumbering->NumberDoc($Data['lcm_series'],$Data['lcm_docdate'],$Data['lcm_duedate']);
		
		if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

		}else if ($DocNumVerificado['error']){

			return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);
		}
        //VALIDAR TASA
        $dataTasa = $this->tasa->Tasa($Data['lcm_currency'],$Data['lcm_docdate']);

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

        $sqlInsert = "INSERT INTO tblg(lcm_doctype,lcm_series,lcm_docnum,lcm_acc,lcm_docdate,lcm_duedate,lcm_duedev,lcm_currency,lcm_cardcode,lcm_cardname,
		business,branch,lcm_status,lcm_comments)
        VALUES(:lcm_doctype,:lcm_series,:lcm_docnum,:lcm_acc,:lcm_docdate,:lcm_duedate,:lcm_duedev,:lcm_currency,:lcm_cardcode,:lcm_cardname,:business,
		:branch,:lcm_status,lcm_comments)";

        $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert,array(
            ':lcm_doctype' => isset($Data['lcm_doctype']) ? $Data['lcm_doctype'] : NULL,
            ':lcm_series' => isset($Data['lcm_series']) ? $Data['lcm_series'] : NULL,
            ':lcm_docnum' => isset($DocNumVerificado) ? $DocNumVerificado : 0,
            ':lcm_acc' => isset($Data['lcm_acc']) ? $Data['lcm_acc'] : NULL,
            ':lcm_docdate' => isset($Data['lcm_docdate']) ? $Data['lcm_docdate'] : NULL,
            ':lcm_duedate' => isset($Data['lcm_duedate']) ? $Data['lcm_duedate'] : NULL,
            ':lcm_duedev' => isset($Data['lcm_duedev']) ? $Data['lcm_duedev'] : NULL,
            ':lcm_currency' => isset($Data['lcm_currency']) ? $Data['lcm_currency'] : NULL,
            ':lcm_cardcode' => isset($Data['lcm_cardcode']) ? $Data['lcm_cardcode'] : NULL,
            ':lcm_cardname' => isset($Data['lcm_cardname']) ? $Data['lcm_cardname'] : NULL,
            ':business' => isset($Data['business']) ? $Data['business'] : NULL,
            ':branch' => isset($Data['branch']) ? $Data['branch'] : NULL,
            ':lcm_status' => 1,
			':lcm_comments' => isset($Data['lcm_comment']) ? $Data['lcm_comment'] : NULL,
        ));

        if(is_numeric($resInsert) && $resInsert > 0){
            //INSERTAR DETALLE
            $sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum WHERE pgs_id = :pgs_id";
			$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
				':pgs_nextnum' => $DocNumVerificado,
				':pgs_id'      => $Data['lcm_series']
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
                $sqlInsertDetail = "INSERT INTO blg1(cm1_docentry,cm1_linenum,cm1_cardcode,cm1_cardname,cm1_docdate,cm1_price,cm1_currency,cm1_account,cm1_ccost,
                cm1_ubussiness,cm1_proyect,cm1_vatsum,cm1_total)
                VALUES(:cm1_docentry,:cm1_linenum,:cm1_cardcode,:cm1_cardname,:cm1_docdate,:cm1_price,:cm1_currency,:cm1_account,:cm1_ccost,
                :cm1_ubussiness,:cm1_proyect,:cm1_vatsum,:cm1_total)";
                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail,array(
                    ':cm1_docentry' => $resInsert,
                    ':cm1_linenum' => is_numeric($detail['cm1_linenum']) ? $detail['cm1_linenum'] : 0,
                    ':cm1_cardcode' => isset($detail['cm1_cardcode']) ? $detail['cm1_cardcode'] : NULL,
                    ':cm1_cardname' => isset($detail['cm1_cardname']) ? $detail['cm1_cardname'] : NULL,
                    ':cm1_docdate' => isset($detail['cm1_docdate']) ? $detail['cm1_docdate'] : NULL,
                    ':cm1_price' => is_numeric($detail['cm1_price']) ? $detail['cm1_price'] : 0,
                    ':cm1_currency' => isset($detail['cm1_currency']) ? $detail['cm1_currency'] : NULL,
                    ':cm1_account' => isset($detail['cm1_account']) ? $detail['cm1_account'] : NULL,
                    ':cm1_ccost' => isset($detail['cm1_ccost']) ? $detail['cm1_ccost'] : NULL,
                    ':cm1_ubussiness' => isset($detail['cm1_ubussiness']) ? $detail['cm1_ubussiness'] : NULL,
                    ':cm1_proyect' => isset($detail['cm1_proyect']) ? $detail['cm1_proyect'] : NULL,
                    ':cm1_vatsum' => isset($detail['cm1_vatsum']) ? $detail['cm1_vatsum'] : NULL,
                    ':cm1_total' => is_numeric($detail['cm1_total']) ? $detail['cm1_total'] : 0
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

		if (!isset($Data['lcm_docentry']) or !isset($Data['business']) or !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = " SELECT * FROM tblg WHERE lcm_docentry = :lcm_docentry AND business = :business AND branch = :branch";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ":lcm_docentry" => $Data['lcm_docentry'],
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

		if (!isset($Data['cm1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT * FROM blg1 WHERE cm1_docentry = :cm1_docentry";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":cm1_docentry" => $Data['cm1_docentry']));

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
