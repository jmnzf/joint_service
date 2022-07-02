<?php

// RECONCIALIACION BANCARIA
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class BankReconciliation extends REST_Controller {

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

  //CREAR NUEVA RECONCIALIACION BANCARIA
	public function createBankReconciliation_post(){

      $Data = $this->post();

			$SYMBOL = 'BS';

			if(!isset($Data['detail'])){

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'No se econtro el detalle'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}


			$ContenidoDetalle = json_decode($Data['detail'], true);


			if(!is_array($ContenidoDetalle)){
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se encontro el detalle de la cotización'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

			// SE VALIDA QUE EL DOCUMENTO TENGA CONTENIDO
			if(!intval(count($ContenidoDetalle)) > 0 ){
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'Documento sin detalle'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}
			//


			//Obtener Carpeta Principal del Proyecto
			$sqlMainFolder = " SELECT * FROM params";
			$resMainFolder = $this->pedeo->queryTable($sqlMainFolder, array());

			if(!isset($resMainFolder[0])){
					$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' =>'No se encontro la caperta principal del proyecto'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}


			// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
			// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
			$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
			$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

			if(isset($resMonedaLoc[0])){

			}else{
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se encontro la moneda local.'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

			$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

			// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
			$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
			$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

			if(isset($resMonedaSys[0])){

			}else{

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se encontro la moneda de sistema.'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}


			$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

			//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
			// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
			$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
			$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $SYMBOL, ':tsa_date' => $Data['crb_docdate']));

			if(isset($resBusTasa[0])){

			}else{

					if(trim($SYMBOL) != $MONEDALOCAL ){

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$SYMBOL.' en la actual fecha del documento: '.$Data['crb_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
					}
			}


			$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
			$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $Data['crb_docdate']));

			if(isset($resBusTasa2[0])){

			}else{
					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$Data['crb_docdate']
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
			}

			$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
			$TasaLocSys = $resBusTasa2[0]['tsa_value'];

			// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO

			$sqlInsert = "INSERT INTO dcrb(crb_description, crb_docdate, crb_createby, crb_startdate, crb_enddate, crb_account, crb_gbaccount, crb_ivaccount, crb_cost, crb_tax, crb_posting_stardate, crb_posting_enddate)
										VALUES (:crb_description, :crb_docdate, :crb_createby, :crb_startdate, :crb_enddate, :crb_account, :crb_gbaccount, :crb_ivaccount, :crb_cost, :crb_tax, :crb_posting_stardate, :crb_posting_enddate)";


			// Se Inicia la transaccion,
			// Todas las consultas de modificacion siguientes
			// aplicaran solo despues que se confirme la transaccion,
			// de lo contrario no se aplicaran los cambios y se devolvera
			// la base de datos a su estado original.

			$this->pedeo->trans_begin();


			$resInsert = $this->pedeo->insertRow($sqlInsert, array(

				':crb_description' => $Data['crb_description'],
				':crb_docdate'		 => $Data['crb_docdate'],
				':crb_createby' 	 => $Data['crb_createby'],
				':crb_startdate'   => $Data['crb_startdate'],
				':crb_enddate'     => $Data['crb_enddate'],
				':crb_account'     => $Data['crb_account'],
				':crb_gbaccount'     => $Data['crb_gbaccount'],
				':crb_ivaccount'     => $Data['crb_ivaccount'],
				':crb_cost'     => $Data['crb_cost'],
				':crb_tax'     => $Data['crb_tax'],
				':crb_posting_stardate'     => $Data['crb_posting_stardate'],
				':crb_posting_enddate'     => $Data['crb_posting_enddate']
			));

			if(is_numeric($resInsert) && $resInsert > 0){

			  foreach ($ContenidoDetalle as $key => $detail) {



					$sqlInsertDetail = "INSERT INTO crb1(rb1_crbid, rb1_date, rb1_ref, rb1_debit, rb1_credit, rb1_gastob, rb1_imp)
														 VALUES (:rb1_crbid, :rb1_date, :rb1_ref, :rb1_debit, :rb1_credit, :rb1_gastob, :rb1_imp)";


					$resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(

						':rb1_crbid' => $resInsert,
						':rb1_date' => $detail['rb1_date'],
						':rb1_ref' => $detail['rb1_ref'],
						':rb1_debit' => $detail['rb1_debit'],
						':rb1_credit' => $detail['rb1_credit'],
						':rb1_gastob' => $detail['rb1_gastob'],
						':rb1_imp' => $detail['rb1_imp']
					));


					if(is_numeric($resInsertDetail) && $resInsertDetail > 0){
						// Se verifica que el detalle no de error insertando //
					}else{
						$this->pedeo->trans_rollback();

						$respuesta = array(
							'error'   => true,
							'data' => $resInsertDetail,
							'mensaje'	=> 'No se pudo registrar la reconciliación de bancos'
						);

						 $this->response($respuesta);

						 return;
					}
				}


				$this->pedeo->trans_commit();

				$respuesta = array(
					'error' => false,
					'data' => $resInsert,
					'mensaje' =>'Reconciliación registrada con exito'
				);

			}else{
				$this->pedeo->trans_rollback();

				$respuesta = array(
					'error'   => true,
					'data'    => $resInsert,
					'mensaje'	=> 'No se pudo crear la reconciliación de bancos'
				);

				$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				return;
			}



      $this->response($respuesta);
	}

	//OBTENER RECONCIALIACIONES REALIZADAS
	public function getBankReconciliation_get(){

		$sqlSelect = "SELECT *
									FROM dcrb";

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




  // Obtener Cuentas de Banco
  public function getBankAccounts_get(){

        $sqlSelect = "SELECT * FROM dacc WHERE acc_cash = 1 AND acc_digit = 10 AND cast(acc_code AS VARCHAR)  LIKE '1101021%' ORDER BY acc_code ASC";

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

	// OBTENER MOVIMIENTO DE CUENTAS POR RANGO DE FECHAS
	public function getAccountRange_get(){

				$Data = $this->get();

				if(!isset($Data['startdate']) OR ! isset($Data['enddate']) OR !isset($Data['account'])){

					$respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' =>'Faltan parametros'
					);

					$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;
				}

				$sqlSelect = "SELECT mac1.ac1_trans_id as id,
											mac1.ac1_doc_date as fecha,
											mac1.ac1_debit as debit,
											mac1.ac1_credit as credit,
											tmac.mac_base_type as basetype,
											tmac.mac_base_entry as baseentry,
											gbpr.bpr_reftransfer as ref
											FROM gbpr
											INNER JOIN tmac
											ON tmac.mac_base_type = gbpr.bpr_doctype AND tmac.mac_base_entry = gbpr.bpr_docentry
											INNER JOIN mac1
											ON mac1.ac1_trans_id = tmac.mac_trans_id
											WHERE ac1_account = :ac1_account AND ac1_doc_date BETWEEN  :startdate AND :enddate
											and ac1_line_num not in (select distinct rb1_linenumcod from  crb1 where rb1_linenumcod != 0)
											UNION ALL
											SELECT mac1.ac1_trans_id as id,
											mac1.ac1_doc_date as fecha,
											mac1.ac1_debit as debit,s
											mac1.ac1_credit as credit,
											tmac.mac_base_type as basetype,
											tmac.mac_base_entry as baseentry,
											gbpe.bpe_reftransfer as ref
											FROM gbpe
											INNER JOIN tmac
											ON tmac.mac_base_type = gbpe.bpe_doctype AND tmac.mac_base_entry = gbpe.bpe_docentry
											INNER JOIN mac1
											ON mac1.ac1_trans_id = tmac.mac_trans_id
											WHERE ac1_account = :ac1_account AND ac1_doc_date BETWEEN  :startdate AND :enddate
											and ac1_line_num not in (select distinct rb1_linenumcod from  crb1 where rb1_linenumcod != 0)";

				// $sqlSelect = "SELECT * FROM mac1 WHERE ac1_account = :ac1_account AND ac1_doc_date BETWEEN :startdate AND :enddate ";

				$resSelect = $this->pedeo->queryTable($sqlSelect, array(
					':ac1_account' => $Data['account'],
					':startdate'   => $Data['startdate'],
					':enddate' 		 => $Data['enddate']
				));

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


	public function getExpenseCostAcct_get(){

		$sqlSelect = "SELECT *
									FROM dacc
									WHERE acc_cash = 0
									AND acc_digit = 10
									AND acc_code > 3999999999
									ORDER BY acc_code ASC";

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
