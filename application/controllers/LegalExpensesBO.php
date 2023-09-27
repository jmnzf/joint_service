<?php
// DATOS MAESTROS ALMACEN
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class LegalExpensesBO extends REST_Controller {

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
		$this->load->library('generic');

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
            !isset($Data['blg_cardname'])OR
			!isset($Data['blg_lineacct']) ){

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
				'mensaje' => 'La informacion enviada no es válida'
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
		business,branch,blg_status,blg_comment, blg_doctotal,blg_taxtotal,blg_baseamnt, blg_createby, blg_lineacct)
        VALUES(:blg_doctype,:blg_series,:blg_docnum,:blg_acc,:blg_docdate,:blg_duedate,:blg_duedev,:blg_currency,:blg_cardcode,:blg_cardname,:business,
		:branch,:blg_status, :blg_comment ,  :blg_doctotal, :blg_taxtotal, :blg_baseamnt, :blg_createby, :blg_lineacct)";

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
			':blg_comment' => isset($Data['blg_comments']) ? $Data['blg_comments'] : NULL,
			':blg_doctotal' => $Data['blg_doctotal'],
			':blg_taxtotal' => $Data['blg_taxtotal'],
			':blg_baseamnt' => $Data['blg_baseamnt'],
			':blg_createby' => $Data['blg_createby'],
			':blg_lineacct' => $Data['blg_lineacct']
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

			$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
								VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

			$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
				':bed_docentry' => $resInsert,
				':bed_doctype' => 37,
				':bed_status' => 1, //ESTADO CERRADO
				':bed_createby' => $Data['blg_createby'],
				':bed_date' => date('Y-m-d'),
				':bed_baseentry' => NULL,
				':bed_basetype' => NULL
			));


			if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
			} else {

				$this->pedeo->trans_rollback();

				$respuesta = array(
				'error'   => true,
				'data' => $resInsertEstado,
				'mensaje'	=> 'No se pudo registrar la legalizacion de gastos'
				);


				$this->response($respuesta);

				return;
			}



            foreach ($ContenidoDetalle as $key => $detail) {
				
                $sqlInsertDetail = "INSERT INTO blg1(lg1_docentry,lg1_linenum,lg1_cardcode,lg1_cardname,lg1_docdate,lg1_price,lg1_currency,lg1_account,lg1_ccost,
                lg1_ubussiness,lg1_proyect,lg1_vatsum,lg1_total,lg1_concept, lg1_codimp, lg1_vat, lg1_comment)
                VALUES(:lg1_docentry,:lg1_linenum,:lg1_cardcode,:lg1_cardname,:lg1_docdate,:lg1_price,:lg1_currency,:lg1_account,:lg1_ccost,
                :lg1_ubussiness,:lg1_proyect,:lg1_vatsum,:lg1_total, :lg1_concept, :lg1_codimp, :lg1_vat, :lg1_comment)";

                $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail,array(
                    ':lg1_docentry'   => $resInsert,
                    ':lg1_linenum'    => is_numeric($detail['lg1_linenum']) ? $detail['lg1_linenum'] : 0,
                    ':lg1_cardcode'   => isset($detail['lg1_cardcode']) ? $detail['lg1_cardcode'] : NULL,
                    ':lg1_cardname'   => isset($detail['lg1_cardname']) ? $detail['lg1_cardname'] : NULL,
                    ':lg1_docdate'    => isset($detail['lg1_docdate']) ? $detail['lg1_docdate'] : NULL,
                    ':lg1_price'      => is_numeric($detail['lg1_price']) ? $detail['lg1_price'] : 0,
                    ':lg1_currency'   => isset($detail['lg1_currency']) ? $detail['lg1_currency'] : NULL,
                    ':lg1_account' 	  => isset($detail['lg1_account']) ? $detail['lg1_account'] : NULL,
                    ':lg1_ccost' 	  => isset($detail['lg1_ccost']) ? $detail['lg1_ccost'] : NULL,
                    ':lg1_ubussiness' => isset($detail['lg1_ubussiness']) ? $detail['lg1_ubussiness'] : NULL,
                    ':lg1_proyect'    => isset($detail['lg1_proyect']) ? $detail['lg1_proyect'] : NULL,
                    ':lg1_vatsum' 	  => isset($detail['lg1_vatsum']) ? $detail['lg1_vatsum'] : NULL,
                    ':lg1_total' 	  => is_numeric($detail['lg1_total']) ? $detail['lg1_total'] : 0,
                    ':lg1_concept'    => isset($detail['lg1_concept']) ? $detail['lg1_concept'] : null,
                    ':lg1_codimp'     => isset($detail['lg1_codimp']) ? $detail['lg1_codimp'] : null,
					':lg1_vat'        => isset($detail['lg1_vat']) && is_numeric($detail['lg1_vat']) ? $detail['lg1_vat'] : null,
					':lg1_comment'    => isset($detail['lg1_comment']) ? $detail['lg1_comment'] : null
                ));

                if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

                }else{
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'    => $resInsertDetail,
                        'mensaje'	=> 'No se pudo crear la legalizacion de gastos'
                    );
                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }

            }

            $this->pedeo->trans_commit();

			$respuesta = array(
				'error' => false,
				'data' => $resInsert,
				'mensaje' => 'Legalizacion de gastos, creada con éxito'
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

	public function updateLegalExpense_post(){

		$Data = $this->post();

		if( !isset($Data['lg1_docentry']) OR
			!isset($Data['detail']) ){

				$respuesta = array(
					'error' => true,
					'data' => [],
					'mensaje' => 'La información enviada no es valida'
				);

				$this->response($respuesta,REST_Controller::HTTP_BAD_REQUEST);
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

		$this->pedeo->trans_begin();

		// BORRAR DATOS EXISTENTES EN EL DETALLE
		$sqlDelete = "DELETE FROM blg1 WHERE lg1_docentry = :lg1_docentry";
		$resDelete = $this->pedeo->deleteRow($sqlDelete, array(':lg1_docentry' => $Data['lg1_docentry']));

		if ( is_numeric($resDelete) && $resDelete > 0 ){

		}else{
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resDelete,
				'mensaje'	=> 'No se pudo actualizar la legalización de gastos'
			);
			return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		}

		//ACTUALIZA LOS VALORES DEL ENCABEZADO
		$sqlUpdate = "UPDATE tblg set blg_doctotal =:blg_doctotal, blg_taxtotal =:blg_taxtotal, blg_baseamnt=:blg_baseamnt WHERE blg_docentry =:blg_docentry";
		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':blg_doctotal' => $Data['blg_doctotal'],
			':blg_taxtotal' => $Data['blg_taxtotal'],
			':blg_baseamnt' => $Data['blg_baseamnt'],
			':blg_docentry' => $Data['lg1_docentry']
		));

		if (is_numeric($resUpdate) && $resUpdate == 1){

		}else{
			$this->pedeo->trans_rollback();

			$respuesta = array(
				'error'   => true,
				'data'    => $resUpdate,
				'mensaje'	=> 'No se pudo actualizar la legalización de gastos'
			);
			return $this->response($respuesta);
		}


		foreach ($ContenidoDetalle as $key => $detail) {

				
			$sqlInsertDetail = "INSERT INTO blg1(lg1_docentry,lg1_linenum,lg1_cardcode,lg1_cardname,lg1_docdate,lg1_price,lg1_currency,lg1_account,lg1_ccost,
			lg1_ubussiness,lg1_proyect,lg1_vatsum,lg1_total,lg1_concept, lg1_codimp, lg1_vat, lg1_comment)
			VALUES(:lg1_docentry,:lg1_linenum,:lg1_cardcode,:lg1_cardname,:lg1_docdate,:lg1_price,:lg1_currency,:lg1_account,:lg1_ccost,
			:lg1_ubussiness,:lg1_proyect,:lg1_vatsum,:lg1_total, :lg1_concept, :lg1_codimp, :lg1_vat, :lg1_comment)";

			$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail,array(
				':lg1_docentry'   => $Data['lg1_docentry'],
				':lg1_linenum' 	  => is_numeric($detail['lg1_linenum']) ? $detail['lg1_linenum'] : 0,
				':lg1_cardcode'   => isset($detail['lg1_cardcode']) ? $detail['lg1_cardcode'] : NULL,
				':lg1_cardname'   => isset($detail['lg1_cardname']) ? $detail['lg1_cardname'] : NULL,
				':lg1_docdate' 	  => isset($detail['lg1_docdate']) ? $detail['lg1_docdate'] : NULL,
				':lg1_price' 	  => is_numeric($detail['lg1_price']) ? $detail['lg1_price'] : 0,
				':lg1_currency'   => isset($detail['lg1_currency']) ? $detail['lg1_currency'] : NULL,
				':lg1_account' 	  => isset($detail['lg1_account']) ? $detail['lg1_account'] : NULL,
				':lg1_ccost' 	  => isset($detail['lg1_ccost']) ? $detail['lg1_ccost'] : NULL,
				':lg1_ubussiness' => isset($detail['lg1_ubussiness']) ? $detail['lg1_ubussiness'] : NULL,
				':lg1_proyect' 	  => isset($detail['lg1_proyect']) ? $detail['lg1_proyect'] : NULL,
				':lg1_vatsum'  	  => isset($detail['lg1_vatsum']) ? $detail['lg1_vatsum'] : NULL,
				':lg1_total' 	  => is_numeric($detail['lg1_total']) ? $detail['lg1_total'] : 0,
				':lg1_concept'    => isset($detail['lg1_concept']) ? $detail['lg1_concept'] : null,
				':lg1_codimp'     => isset($detail['lg1_codimp']) ? $detail['lg1_codimp'] : null,
				':lg1_vat'        => isset($detail['lg1_vat']) && is_numeric($detail['lg1_vat']) ? $detail['lg1_vat'] : null,
				':lg1_comment'    => isset($detail['lg1_comment']) ? $detail['lg1_comment'] : null
			));
		
			if(is_numeric($resInsertDetail) && $resInsertDetail > 0){

			}else{
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resInsertDetail,
					'mensaje'	=> 'No se pudo actualizar la legalización de gastos'
				);
				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

		}

		$this->pedeo->trans_commit();

		$respuesta = array(
			'error' => false,
			'data' => $resInsertDetail,
			'mensaje' => 'Legalización de gastos, actualizada con éxito'
		);

		$this->response($respuesta);

	}
	//

    //OBTENER
    public function getLegalExpense_get(){
        $Data = $this->get();
		$DECI_MALES = $this->generic->getDecimals();

		if ( !isset($Data['business']) OR !isset($Data['branch']) ) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		// $sqlSelect = "SELECT tblg.*,  CONCAT(tblg.blg_currency,' ',TRIM(TO_CHAR(blg_doctotal,'999,999,999,999.'))) as blg_doctotal FROM tblg WHERE business = :business AND branch = :branch";
        // $resSelect = $this->pedeo->queryTable($sqlSelect,array(
        //     ':business' => $Data['business'],
        //     ':branch' => $Data['branch']
        // ));

		$sqlSelect = self::getColumn('tblg', 'blg', '', '', $DECI_MALES, $Data['business'], $Data['branch'], 37);

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

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

        $sql = "SELECT  abs(sum(ac1_ven_debit-ac1_ven_credit)) AS saldo FROM mac1 WHERE ac1_legal_num = :cardcode AND ac1_account = :account AND business = :business AND branch = :branch";
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


	// CONTABILIZAR LEGALIZACIÓN
	public function setAccounting_post() {

		$Data = $this->post();

		$DECI_MALES =  $this->generic->getDecimals();

		$DetalleAsientoIva = new stdClass();
		$DetalleConsolidadoIva = [];
		$inArrayIva = [];
		$llaveIva = "";
		$posicionIva = 0;
		

		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
								ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
								ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
								ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, ac1_base_tax, business, branch, ac1_codret)
								VALUES (:ac1_trans_id, :ac1_account,:ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import, :ac1_debit_importsys,
								:ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
								:ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
								:ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref, :ac1_line, :ac1_base_tax, :business, :branch, :ac1_codret)";

		if(!isset($Data['business']) or !isset($Data['branch']) or !isset($Data['blg_docentry'])){
            $respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
        }

	

		$sqlLegalizacion = "SELECT * FROM tblg WHERE blg_docentry = :blg_docentry AND business =:business AND branch =:branch AND blg_status = :blg_status";
		$resLegalizacion = $this->pedeo->queryTable($sqlLegalizacion, array(
			':blg_docentry' => $Data['blg_docentry'], 
			':business' 	=> $Data['business'],
			':branch' 		=> $Data['branch'],
			':blg_status'   => 1
		));



		if ( isset($resLegalizacion[0]) ){


			//VALIDANDO PERIODO CONTABLE
			$periodo = $this->generic->ValidatePeriod(date('Y-m-d'), date('Y-m-d'), date('Y-m-d'), 0);

			if (isset($periodo['error']) && $periodo['error'] == false) {
			} else {
				$respuesta = array(
					'error'   => true,
					'data'    => [],
					'mensaje' => isset($periodo['mensaje']) ? $periodo['mensaje'] : 'no se pudo validar el periodo contable'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//PERIODO CONTABLE
			//

			//PROCESO DE TASA
			$dataTasa = $this->tasa->Tasa($resLegalizacion[0]['blg_currency'],$resLegalizacion[0]['blg_docdate']);

			if(isset($dataTasa['tasaLocal'])){

				$TasaDocLoc  = $dataTasa['tasaLocal'];
				$TasaLocSys  = $dataTasa['tasaSys'];
				$MONEDALOCAL = $dataTasa['curLocal'];
				$MONEDASYS   = $dataTasa['curSys'];
				
			}else if($dataTasa['error'] == true){

				$this->response($dataTasa, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}
			//FIN DE PROCESO DE TASA

			$sqlDetalle = "SELECT * FROM blg1 WHERE lg1_docentry = :lg1_docentry";
			$resDetalle = $this->pedeo->queryTable($sqlDetalle, array(

				':lg1_docentry'=> $Data['blg_docentry']

			));

			if ( isset($resDetalle[0]) ){

				$this->pedeo->trans_begin();

				$sqlInsertAsiento = "INSERT INTO tmac(mac_doc_num, mac_status, mac_base_type, mac_base_entry, mac_doc_date, mac_doc_duedate, mac_legal_date, mac_ref1, mac_ref2, mac_ref3, mac_loc_total, mac_fc_total, mac_sys_total, mac_trans_dode, mac_beline_nume, mac_vat_date, mac_serie, mac_number, mac_bammntsys, mac_bammnt, mac_wtsum, mac_vatsum, mac_comments, mac_create_date, mac_made_usuer, mac_update_date, mac_update_user, business, branch)
									VALUES (:mac_doc_num, :mac_status, :mac_base_type, :mac_base_entry, :mac_doc_date, :mac_doc_duedate, :mac_legal_date, :mac_ref1, :mac_ref2, :mac_ref3, :mac_loc_total, :mac_fc_total, :mac_sys_total, :mac_trans_dode, :mac_beline_nume, :mac_vat_date, :mac_serie, :mac_number, :mac_bammntsys, :mac_bammnt, :mac_wtsum, :mac_vatsum, :mac_comments, :mac_create_date, :mac_made_usuer, :mac_update_date, :mac_update_user, :business, :branch)";


				$resInsertAsiento = $this->pedeo->insertRow($sqlInsertAsiento, array(
					':mac_doc_num' => 1,
					':mac_status' => 1,
					':mac_base_type' => $resLegalizacion[0]['blg_doctype'],
					':mac_base_entry' => $resLegalizacion[0]['blg_docentry'],
					':mac_doc_date' => date('Y-m-d'),
					':mac_doc_duedate' => date('Y-m-d'),
					':mac_legal_date' => date('Y-m-d'),
					':mac_ref1' => $resLegalizacion[0]['blg_doctype'],
					':mac_ref2' => "",
					':mac_ref3' => "",
					':mac_loc_total' => $resLegalizacion[0]['blg_doctotal'],
					':mac_fc_total' => $resLegalizacion[0]['blg_doctotal'],
					':mac_sys_total' => $resLegalizacion[0]['blg_doctotal'],
					':mac_trans_dode' => 1,
					':mac_beline_nume' => 1,
					':mac_vat_date' => date('Y-m-d'),
					':mac_serie' => 1,
					':mac_number' => 1,
					':mac_bammntsys' =>  $resLegalizacion[0]['blg_baseamnt'],
					':mac_bammnt' => $resLegalizacion[0]['blg_baseamnt'],
					':mac_wtsum' => 1,
					':mac_vatsum' => $resLegalizacion[0]['blg_taxtotal'],
					':mac_comments' => $resLegalizacion[0]['blg_comment'],
					':mac_create_date' => date("Y-m-d H:i:s"),
					':mac_made_usuer' => isset($Data['blg_createby']) ? $Data['blg_createby'] : NULL,
					':mac_update_date' => date("Y-m-d"),
					':mac_update_user' => isset($Data['blg_createby']) ? $Data['blg_createby'] : NULL,
					':business' => $Data['business'],
					':branch' 	=> $Data['branch']
				));


				if (is_numeric($resInsertAsiento) && $resInsertAsiento > 0) {

					
			
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resInsertAsiento,
						'mensaje'	=> 'No se pudo contabilizar el documento'
					);

					$this->response($respuesta);

					return;
				} 

				

				foreach( $resDetalle as $key => $detail ){


				
					$DetalleAsientoIva->ccosto   = isset($detail['lg1_ccost']) ? $detail['lg1_ccost'] : NULL;
					$DetalleAsientoIva->unidadn  = isset($detail['lg1_ubusiness']) ? $detail['lg1_ubusiness'] : NULL;
					$DetalleAsientoIva->proyecto = isset($detail['lg1_proyect']) ? $detail['lg1_proyect'] : NULL;
					$DetalleAsientoIva->total    = is_numeric($detail['lg1_total']) ? $detail['lg1_total'] : 0;
					$DetalleAsientoIva->tasa     = is_numeric($detail['lg1_vat']) ? $detail['lg1_vat'] : 0;
					$DetalleAsientoIva->impuesto = is_numeric($detail['lg1_vatsum']) ? $detail['lg1_vatsum'] : 0;
					$DetalleAsientoIva->codimp   = isset($detail['lg1_codimp']) ? $detail['lg1_codimp'] : NULL;
					$DetalleAsientoIva->cardcode = isset($detail['lg1_cardcode']) ? $detail['lg1_cardcode'] : NULL;


					$llaveIva = $DetalleAsientoIva->tasa;

					if (in_array($llaveIva, $inArrayIva)) {

						$posicionIva = $this->buscarPosicion($llaveIva, $inArrayIva);
					} else {

						array_push($inArrayIva, $llaveIva);
						$posicionIva = $this->buscarPosicion($llaveIva, $inArrayIva);
					}

					if (isset($DetalleConsolidadoIva[$posicionIva])) {

						if (!is_array($DetalleConsolidadoIva[$posicionIva])) {
							$DetalleConsolidadoIva[$posicionIva] = array();
						}
					} else {
						$DetalleConsolidadoIva[$posicionIva] = array();
					}

					array_push($DetalleConsolidadoIva[$posicionIva], $DetalleAsientoIva);


					$monto = ($detail['lg1_price'] - $detail['lg1_vatsum']);
					$cuenta = $detail['lg1_account'];
					$montosys = ($monto/$TasaLocSys);



					// ASIENTOS DETALLE
					$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

						':ac1_trans_id' => $resInsertAsiento,
						':ac1_account' => $cuenta,
						':ac1_debit' => round($monto, $DECI_MALES),
						':ac1_credit' => 0,
						':ac1_debit_sys' => round($montosys, $DECI_MALES),
						':ac1_credit_sys' => 0,
						':ac1_currex' => 0,
						':ac1_doc_date' => date('Y-m-d'),
						':ac1_doc_duedate' => date('Y-m-d'),
						':ac1_debit_import' => 0,
						':ac1_credit_import' => 0,
						':ac1_debit_importsys' => 0,
						':ac1_credit_importsys' => 0,
						':ac1_font_key' => $resLegalizacion[0]['blg_docentry'],
						':ac1_font_line' => 1,
						':ac1_font_type' =>  $resLegalizacion[0]['blg_doctype'],
						':ac1_accountvs' => 1,
						':ac1_doctype' => 18,
						':ac1_ref1' => "",
						':ac1_ref2' => "",
						':ac1_ref3' => "",
						':ac1_prc_code' => $DetalleAsientoIva->ccosto ,
						':ac1_uncode' => $DetalleAsientoIva->unidadn,
						':ac1_prj_code' => $DetalleAsientoIva->proyecto,
						':ac1_rescon_date' => NULL,
						':ac1_recon_total' => 0,
						':ac1_made_user' => isset($Data['blg_createby']) ? $Data['blg_createby'] : NULL,
						':ac1_accperiod' => 1,
						':ac1_close' => 0,
						':ac1_cord' => 0,
						':ac1_ven_debit' => 0,
						':ac1_ven_credit' => 0,
						':ac1_fiscal_acct' => 0,
						':ac1_taxid' => 0,
						':ac1_isrti' => 0,
						':ac1_basert' => 0,
						':ac1_mmcode' => 0,
						':ac1_legal_num' => $DetalleAsientoIva->cardcode,
						':ac1_codref' => 0,
						':ac1_line'   => $detail['lg1_linenum'],
						':ac1_base_tax' => 0,
						':business' => $Data['business'],
						':branch' 	=> $Data['branch'],
						':ac1_codret' => 0
					));


					if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
						// Se verifica que el detalle no de error insertando //
					} else {
						// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resDetalleAsiento,
							'mensaje'	=> 'No se pudo registrar contabilizar legalización de seleccionada'
						);

						$this->response($respuesta);

						return;
					}
					//

					//ASIENTO DE IVA
					

					if ( is_numeric($detail['lg1_vatsum']) && $detail['lg1_vatsum'] > 0 ){

						$granTotalIva = $detail['lg1_vatsum'];
						$Vat = $detail['lg1_vat'];
						$CodigoImp = $detail['lg1_codimp'];
						$tercero = $detail['lg1_cardcode'];
						$MontoSysCR = ( $granTotalIva / $TasaLocSys );

						$sqliva = "SELECT coalesce(dmi_acctcode, 0) AS dmi_acctcode FROM dmtx WHERE dmi_code = :dmi_code"; 
						$resiva = $this->pedeo->queryTable($sqliva, array(
							':dmi_code' => $detail['lg1_codimp']
						));

						if ( isset($resiva[0]) && $resiva[0]['dmi_acctcode'] > 0 ){

						}else{

							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $resiva,
								'mensaje'	=> 'No se encontro la cuenta contable del iva: '.$CodigoImp
							);
	
							$this->response($respuesta);
	
							return;
						}

						$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

							':ac1_trans_id' => $resInsertAsiento,
							':ac1_account' => $resiva[0]['dmi_acctcode'],
							':ac1_debit' => round($granTotalIva, $DECI_MALES),
							':ac1_credit' => 0,
							':ac1_debit_sys' => round($MontoSysCR, $DECI_MALES),
							':ac1_credit_sys' => 0,
							':ac1_currex' => 0,
							':ac1_doc_date' => date('Y-m-d'),
							':ac1_doc_duedate' => date('Y-m-d'),
							':ac1_debit_import' => 0,
							':ac1_credit_import' => 0,
							':ac1_debit_importsys' => 0,
							':ac1_credit_importsys' => 0,
							':ac1_font_key' => $resLegalizacion[0]['blg_docentry'],
							':ac1_font_line' => 1,
							':ac1_font_type' => $resLegalizacion[0]['blg_doctype'],
							':ac1_accountvs' => 1,
							':ac1_doctype' => 18,
							':ac1_ref1' => "",
							':ac1_ref2' => "",
							':ac1_ref3' => "",
							':ac1_prc_code' => NULL,
							':ac1_uncode' => NULL,
							':ac1_prj_code' => NULL,
							':ac1_rescon_date' => NULL,
							':ac1_recon_total' => 0,
							':ac1_made_user' => isset($Data['blg_createby']) ? $Data['blg_createby'] : NULL,
							':ac1_accperiod' => 1,
							':ac1_close' => 0,
							':ac1_cord' => 0,
							':ac1_ven_debit' => 0,
							':ac1_ven_credit' => 0,
							':ac1_fiscal_acct' => 0,
							':ac1_taxid' => $CodigoImp,
							':ac1_isrti' => $Vat,
							':ac1_basert' => 0,
							':ac1_mmcode' => 0,
							':ac1_legal_num' => $tercero,
							':ac1_codref' => 1,
							':ac1_line'   =>  0,
							':ac1_base_tax' => 0,
							':business' => $Data['business'],
							':branch' 	=> $Data['branch'],
							':ac1_codret' => 0 
						));



						if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
							// Se verifica que el detalle no de error insertando //
						} else {

							// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
							// se retorna el error y se detiene la ejecucion del codigo restante.
							$this->pedeo->trans_rollback();

							$respuesta = array(
								'error'   => true,
								'data'	  => $resDetalleAsiento,
								'mensaje'	=> 'No se pudo contabilizar la legalización seleccionada'
							);

							$this->response($respuesta);

							return;
						}

						
					}

					
				}
				
				//CABECERA DE ASIENTO

				$monto =  $resLegalizacion[0]['blg_doctotal'];
				$montosys = ($monto / $TasaLocSys);
				$cuenta = $resLegalizacion[0]['blg_acc'];

				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $cuenta,
					':ac1_debit' => 0,
					':ac1_credit' => round($monto, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => round($montosys, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => date('Y-m-d'),
					':ac1_doc_duedate' => date('Y-m-d'),
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resLegalizacion[0]['blg_docentry'],
					':ac1_font_line' => 1,
					':ac1_font_type' =>  $resLegalizacion[0]['blg_doctype'],
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => NULL,
					':ac1_uncode' => NULL,
					':ac1_prj_code' => NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['blg_createby']) ? $Data['blg_createby'] : NULL,
					':ac1_accperiod' => 1,
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 0,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => $resLegalizacion[0]['blg_cardcode'],
					':ac1_codref' => 0,
					':ac1_line'  => 0,
					':ac1_base_tax' => 0,
					':business' => $Data['business'],
					':branch' 	=> $Data['branch'],
					':ac1_codret' => 0
				));


				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {
					// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar contabilizar legalización de seleccionada'
					);

					$this->response($respuesta);

					return;
				}
				//


				
				//VALIDANDDO DIFERENCIA EN PESO DE MONEDA DE SISTEMA

				$sqlDiffPeso = "SELECT sum(coalesce(ac1_debit_sys,0)) as debito, sum(coalesce(ac1_credit_sys,0)) as credito,
				sum(coalesce(ac1_debit,0)) as ldebito, sum(coalesce(ac1_credit,0)) as lcredito
				from mac1
				where ac1_trans_id = :ac1_trans_id";

				$resDiffPeso = $this->pedeo->queryTable($sqlDiffPeso, array(
				':ac1_trans_id' => $resInsertAsiento
				));

				if (isset($resDiffPeso[0]['debito']) && abs(($resDiffPeso[0]['debito'] - $resDiffPeso[0]['credito'])) > 0) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array(':business' => $Data['business']));

				if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resCuentaDiferenciaDecimal,
						'mensaje'	=> 'No se encontro la cuenta para adicionar la diferencia en decimales'
					);

					$this->response($respuesta);

					return;
				}

				$debito  = $resDiffPeso[0]['debito'];
				$credito = $resDiffPeso[0]['credito'];

				if ($debito > $credito) {
					$credito = abs(($debito - $credito));
					$debito = 0;
				} else {
					$debito = abs(($credito - $debito));
					$credito = 0;
				}

				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
					':ac1_debit' => 0,
					':ac1_credit' => 0,
					':ac1_debit_sys' => round($debito, $DECI_MALES),
					':ac1_credit_sys' => round($credito, $DECI_MALES),
					':ac1_currex' => 0,
					':ac1_doc_date' => date('Y-m-d'),
					':ac1_doc_duedate' => date('Y-m-d'),
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resLegalizacion[0]['blg_docentry'],
					':ac1_font_line' => 1,
					':ac1_font_type' =>  $resLegalizacion[0]['blg_doctype'],
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => NULL,
					':ac1_uncode' => NULL,
					':ac1_prj_code' => NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['blg_createby']) ? $Data['blg_createby'] : NULL,
					':ac1_accperiod' => 1,
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 0,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => $resLegalizacion[0]['blg_cardcode'],
					':ac1_codref' => 0,
					':ac1_line'  => 0,
					':ac1_base_tax' => 0,
					':business' => $Data['business'],
					':branch' 	=> $Data['branch'],
					':ac1_codret' => 0
				));



				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {
					// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
					// se retorna el error y se detiene la ejecucion del codigo restante.
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resDetalleAsiento,
						'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento diferencia en cambio'
					);

					$this->response($respuesta);

					return;
				}
				} else if (isset($resDiffPeso[0]['ldebito']) && abs(($resDiffPeso[0]['ldebito'] - $resDiffPeso[0]['lcredito'])) > 0) {

				$sqlCuentaDiferenciaDecimal = "SELECT pge_acc_ajp FROM pgem WHERE pge_id = :business";
				$resCuentaDiferenciaDecimal = $this->pedeo->queryTable($sqlCuentaDiferenciaDecimal, array(':business' => $Data['business']));

				if (isset($resCuentaDiferenciaDecimal[0]) && is_numeric($resCuentaDiferenciaDecimal[0]['pge_acc_ajp'])) {
				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'	  => $resCuentaDiferenciaDecimal,
						'mensaje'	=> 'No se encontro la cuenta para adicionar la diferencia en decimales'
					);

					$this->response($respuesta);

					return;
				}

				$ldebito  = $resDiffPeso[0]['ldebito'];
				$lcredito = $resDiffPeso[0]['lcredito'];

				if ($ldebito > $lcredito) {
					$lcredito = abs(($ldebito - $lcredito));
					$ldebito = 0;
				} else {
					$ldebito = abs(($lcredito - $ldebito));
					$lcredito = 0;
				}

				$resDetalleAsiento = $this->pedeo->insertRow($sqlDetalleAsiento, array(

					':ac1_trans_id' => $resInsertAsiento,
					':ac1_account' => $resCuentaDiferenciaDecimal[0]['pge_acc_ajp'],
					':ac1_debit' => round($ldebito, $DECI_MALES),
					':ac1_credit' => round($lcredito, $DECI_MALES),
					':ac1_debit_sys' => 0,
					':ac1_credit_sys' => 0,
					':ac1_currex' => 0,
					':ac1_doc_date' => date('Y-m-d'),
					':ac1_doc_duedate' => date('Y-m-d'),
					':ac1_debit_import' => 0,
					':ac1_credit_import' => 0,
					':ac1_debit_importsys' => 0,
					':ac1_credit_importsys' => 0,
					':ac1_font_key' => $resLegalizacion[0]['blg_docentry'],
					':ac1_font_line' => 1,
					':ac1_font_type' =>  $resLegalizacion[0]['blg_doctype'],
					':ac1_accountvs' => 1,
					':ac1_doctype' => 18,
					':ac1_ref1' => "",
					':ac1_ref2' => "",
					':ac1_ref3' => "",
					':ac1_prc_code' => NULL,
					':ac1_uncode' => NULL,
					':ac1_prj_code' => NULL,
					':ac1_rescon_date' => NULL,
					':ac1_recon_total' => 0,
					':ac1_made_user' => isset($Data['blg_createby']) ? $Data['blg_createby'] : NULL,
					':ac1_accperiod' => 1,
					':ac1_close' => 0,
					':ac1_cord' => 0,
					':ac1_ven_debit' => 0,
					':ac1_ven_credit' => 0,
					':ac1_fiscal_acct' => 0,
					':ac1_taxid' => 0,
					':ac1_isrti' => 0,
					':ac1_basert' => 0,
					':ac1_mmcode' => 0,
					':ac1_legal_num' => $resLegalizacion[0]['blg_cardcode'],
					':ac1_codref' => 0,
					':ac1_line'  => 0,
					':ac1_base_tax' => 0,
					':business' => $Data['business'],
					':branch' 	=> $Data['branch'],
					':ac1_codret' => 0
				));



				if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
					// Se verifica que el detalle no de error insertando //
				} else {
						// si falla algun insert del detalle de la factura de Ventas se devuelven los cambios realizados por la transaccion,
						// se retorna el error y se detiene la ejecucion del codigo restante.
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data'	  => $resDetalleAsiento,
							'mensaje'	=> 'No se pudo registrar el pago recibido, occurio un error al insertar el detalle del asiento diferencia en cambio'
						);

						$this->response($respuesta);

						return;
					}
				}



				// ACTUALIZAR VALOR ANTICIPO

				$slqUpdateVenCredit = "UPDATE mac1
									SET ac1_ven_credit = ac1_ven_credit + :ac1_ven_credit
									WHERE ac1_line_num = :ac1_line_num";
				$resUpdateVenCredit = $this->pedeo->updateRow($slqUpdateVenCredit, array(

					':ac1_ven_credit' => round($monto, $DECI_MALES),
					':ac1_line_num'   => $resLegalizacion[0]['blg_lineacct']

				));

				if (is_numeric($resUpdateVenCredit) && $resUpdateVenCredit == 1) {

				} else {

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'     => true,
						'data' 		=> $resUpdateVenCredit,
						'mensaje'	=> 'No se pudo actualizar el valor del anticipo '
					);

					$this->response($respuesta);

					return;
				}

				//



				$validateCont = $this->generic->validateAccountingAccent($resInsertAsiento);
			
				

				if (isset($validateCont['error']) && $validateCont['error'] == false) {
					//SE INSERTA EL ESTADO DEL DOCUMENTO

					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
					VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(
						':bed_docentry' => $resLegalizacion[0]['blg_docentry'],
						':bed_doctype' => $resLegalizacion[0]['blg_doctype'],
						':bed_status' => 3, //ESTADO CERRADO
						':bed_createby' => $Data['blg_createby'],
						':bed_date' => date('Y-m-d'),
						':bed_baseentry' => NULL,
						':bed_basetype' => NULL
					));


					if (is_numeric($resInsertEstado) && $resInsertEstado > 0) {
					} else {

						$this->pedeo->trans_rollback();

						$respuesta = array(
						'error'   => true,
						'data' => $resInsertEstado,
						'mensaje'	=> 'No se pudo registrar la legalizacion de gastos'
						);


						$this->response($respuesta);

						return;
					}

				//FIN PROCESO ESTADO DEL DOCUMENTO
				} else {

					$ressqlmac1 = [];
					$sqlmac1 = "SELECT acc_name, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys FROM  mac1 inner join dacc on ac1_account = acc_code WHERE ac1_trans_id = :ac1_trans_id";
					$ressqlmac1['contabilidad'] = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));

					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data' 	  => $ressqlmac1,
						'mensaje' => $validateCont['mensaje'],
						
					);

					$this->response($respuesta);

					return;
				}


				// $sqlmac1 = "SELECT * FROM  mac1 WHERE ac1_trans_id = :ac1_trans_id";
				// $ressqlmac1 = $this->pedeo->queryTable($sqlmac1, array(':ac1_trans_id' => $resInsertAsiento ));
				// print_r(json_encode($ressqlmac1));
				// exit;


				$this->pedeo->trans_commit();
				// $this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => false,
					'data'    =>  [],
					'mensaje' => 'Proceso finalizado con éxito'
				);

			}else{

				$respuesta = array(
					'error'   => true,
					'data'    => array(),
					'mensaje' => 'No se encontró el detalle de la legalización seleccionada'
				);
	
				$this->response($respuesta);
	
				return;
			}

		}else{

			$respuesta = array(
				'error'   => true,
				'data'    => array(),
				'mensaje' => 'No se encontraron datos de la legalización seleccionada'
			);

			$this->response($respuesta);

			return;
		}



		$this->response($respuesta);


	}

	public  function getGbpe_post(){

		$Data = $this->post();
// print_r($Data);exit;
		if( !isset($Data['business']) OR
			!isset($Data['branch']) OR
			!isset($Data['cardcode'])) {

			$respuesta = array(
				'error' => true,
				'data' => [],
				'mensaje' => 'La información enviada no es valida'
			);

			return $this->response($respuesta,REST_Controller::HTTP_BAD_REQUEST);
			
		}

		$fecha = date('Y-m-d');

		$sql = "SELECT DISTINCT 
				get_legalacct2(mac1.ac1_line_num, mac1.ac1_trans_id) as cuentabanco,
				upper('ANTICIPO  - ' || gbpe.bpe_comments || ' # ' || gbpe.bpe_docnum) as tipo, 
				case
				when mac1.ac1_font_type = 15 OR mac1.ac1_font_type = 46 then get_dynamic_conversion(get_localcur(),get_localcur(),gbpe.bpe_docdate,sum(mac1.ac1_debit)
				,get_localcur())
				else get_dynamic_conversion(get_localcur(),get_localcur(),gbpe.bpe_docdate,sum(mac1.ac1_debit) ,get_localcur())
				end as totalfactura,
				round(get_dynamic_conversion(get_localcur(),get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))
				,get_localcur()),0) as saldo,
				mac1.ac1_line_num as asiento
				from mac1
				inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
				inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
				inner join gbpe on gbpe.bpe_doctype = mac1.ac1_font_type and gbpe.bpe_docentry =
				mac1.ac1_font_key
				inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
				where mac1.ac1_legal_num = '".$Data['cardcode']."'
				and dmsn.dms_card_type = '2'
				and gbpe.business = ".$Data['business']."
				GROUP BY dmdt.mdt_docname,
				mac1.ac1_font_key,
				mac1.ac1_legal_num,
				dmsn.dms_card_name,
				gbpe.bpe_currency,
				gbpe.bpe_comments,
				gbpe.bpe_currency,
				mac1.ac1_font_key,
				gbpe.bpe_docnum,
				gbpe.bpe_docdate,
				gbpe.bpe_docdate,
				gbpe.bpe_docnum,
				mac1.ac1_font_type,
				mac1.ac1_line_num 
				HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0";
		
		$res = $this->pedeo->queryTable($sql, array());

		if (isset($res[0])){

			$respuesta = array(
				'error' => false,
				'data' => $res,
				'mensaje' => ''
			);

		

		}else{

			$respuesta = array(
				'error' => true,
				'data' => $res,
				'mensaje' => 'Busqueda sin resultados'
			);
		}

		$this->response($respuesta);


	}



	private function buscarPosicion($llave, $inArray)
	{
		$res = 0;
		for ($i = 0; $i < count($inArray); $i++) {
			if ($inArray[$i] == "$llave") {
				$res =  $i;
				break;
			}
		}

		return $res;
	}



}
