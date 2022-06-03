<?php
// MODELO DE APROBACIONES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class ManufacturingOrder extends REST_Controller {

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

    public function setManufacturingOrder_post(){
        $Data = $this->post();
        $DocNumVerificado = 0;

        if(
        !isset($Data['bof_docnum']) OR
        !isset($Data['bof_item_code']) OR
        !isset($Data['bof_item_description']) OR
        !isset($Data['bof_quantity']) OR
        !isset($Data['bof_cardcode']) OR
        !isset($Data['bof_fatorydate']) OR
        !isset($Data['bof_date']) OR
        !isset($Data['bof_duedate']) OR
        !isset($Data['bof_user']) OR
        !isset($Data['bof_cust_order']) OR
        !isset($Data['bof_ccost']) OR
        !isset($Data['bof_status']) OR
        !isset($Data['bof_project'])){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'La informacion enviada no es valida'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }



        // SE VERIFCA QUE EL DOCUMENTO TENGA DETALLE
        $ContenidoDetalle = json_decode($Data['detail'], true);

        if (!is_array($ContenidoDetalle)) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'No se encontro el detalle de la orden de facturacion'
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

        //BUSCANDO LA NUMERACION DEL DOCUMENTO
			  $sqlNumeracion = " SELECT pgs_nextnum,pgs_last_num FROM  pgdn WHERE pgs_id = :pgs_id";

              $resNumeracion = $this->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $Data['bof_docnum']));

              if(isset($resNumeracion[0])){

                      $numeroActual = $resNumeracion[0]['pgs_nextnum'];
                      $numeroFinal  = $resNumeracion[0]['pgs_last_num'];
                      $numeroSiguiente = ($numeroActual + 1);

                      if( $numeroSiguiente <= $numeroFinal ){

                              $DocNumVerificado = $numeroSiguiente;

                      }	else {

                              $respuesta = array(
                                  'error' => true,
                                  'data'  => array(),
                                  'mensaje' =>'La serie de la numeración esta llena'
                              );

                              $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                              return;
                      }

              }else{

                      $respuesta = array(
                          'error' => true,
                          'data'  => array(),
                          'mensaje' =>'No se encontro la serie de numeración para el documento'
                      );

                      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                      return;
              }
            //   FIN DE NUMERACION DEL DOCUMENTO

        $sqlInsert = "INSERT INTO tbof(bof_docnum, bof_doctype,bof_item_code ,bof_item_description ,bof_quantity ,bof_cardcode, bof_fatorydate, bof_date, bof_duedate, bof_user, bof_cust_order, bof_ccost, bof_project, bof_type, bof_baseentry, bof_basetype, bof_status, bof_createat, bof_createby, bof_docnum_order, bof_docentry_order) VALUES (:bof_docnum, :bof_doctype, :bof_item_code,:bof_item_description,:bof_quantity,:bof_cardcode,:bof_fatorydate,:bof_date,:bof_duedate,:bof_user, :bof_cust_order, :bof_ccost, :bof_project, :bof_type, :bof_baseentry, :bof_basetype, :bof_status, :bof_createat, :bof_createby, :bof_docnum_order, :bof_docentry_order)";
        
        $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":bof_docnum" => $DocNumVerificado,
            ":bof_doctype" => $Data['bof_doctype'],
            ":bof_item_code" => $Data['bof_item_code'],
            ":bof_item_description" => $Data['bof_item_description'],
            ":bof_quantity" => $Data['bof_quantity'],
            ":bof_cardcode" => $Data['bof_cardcode'],
            ":bof_fatorydate" => $Data['bof_fatorydate'],
            ":bof_date" => $Data['bof_date'],
            ":bof_duedate" => $Data['bof_duedate'],
            ":bof_user" => $Data['bof_user'],
            ":bof_cust_order" => $Data['bof_cust_order'],
            ":bof_ccost" => $Data['bof_ccost'],
            ":bof_project" => $Data['bof_project'],
            ":bof_type" => $Data['bof_type'],
			":bof_status" => $Data['bof_status'],
            ":bof_baseentry" => isset($Data['bof_baseentry']) ? $Data['bof_baseentry'] :0,
            ":bof_basetype" => isset($Data['bof_basetype']) ? $Data['bof_basetype'] :0,
            ":bof_createat" => isset($Data['bof_createat']) ? $Data['bof_createat'] :null,
            ":bof_createby" =>  isset($Data['bof_createby']) ? $Data['bof_createby'] :null,
            ":bof_docnum_order" =>  isset($Data['bof_docnum_order']) ? $Data['bof_docnum_order'] :0,
            ":bof_docentry_order" =>  isset($Data['bof_docentry_order']) ? $Data['bof_docentry_order'] :0
        ));

        if( is_numeric($resInsert) AND $resInsert > 0){


            // Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['bof_docnum']
					));


					if(is_numeric($resActualizarNumeracion) && $resActualizarNumeracion == 1){

					}else{
								$this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data'    => $resActualizarNumeracion,
									'mensaje'	=> 'No se pudo crear la factura de compras'
								);

								$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

								return;
					}
					// Fin de la actualizacion de la numeracion del documento



					//SE INSERTA EL ESTADO DEL DOCUMENTO

					$sqlInsertEstado = "INSERT INTO tbed(bed_docentry, bed_doctype, bed_status, bed_createby, bed_date, bed_baseentry, bed_basetype)
															VALUES (:bed_docentry, :bed_doctype, :bed_status, :bed_createby, :bed_date, :bed_baseentry, :bed_basetype)";

					$resInsertEstado = $this->pedeo->insertRow($sqlInsertEstado, array(


										':bed_docentry' => $resInsert,
										':bed_doctype' => $Data['bof_doctype'],
										':bed_status' => $Data['bof_status'], // Estado planificado
										':bed_createby' => $Data['bof_createby'],
										':bed_date' => date('Y-m-d'),
										':bed_baseentry' => NULL,
										':bed_basetype' => NULL
					));


					if(is_numeric($resInsertEstado) && $resInsertEstado > 0){

					}else{

							 $this->pedeo->trans_rollback();

								$respuesta = array(
									'error'   => true,
									'data' => $resInsertEstado,
									'mensaje'	=> 'No se pudo registrar la orden de fabricación'
								);


								$this->response($respuesta);

								return;
					}

            $sqlInsert2 = "INSERT INTO bof1 (of1_type, of1_description, of1_quantitybase, of1_ratiobase, of1_uom, of1_whscode, of1_emimet, of1_costcode, of1_unity, of1_docentry, of1_acc, of1_ing, of1_uom_code, of1_listmat, of1_basenum, of1_item_code, of1_item_cost) VALUES (:of1_type, :of1_description, :of1_quantitybase, :of1_ratiobase, :of1_uom, :of1_whscode, :of1_emimet, :of1_costcode, :of1_unity, :of1_docentry, :of1_acc, :of1_ing, :of1_uom_code, :of1_listmat, :of1_basenum, :of1_item_code, :of1_item_cost)";
            foreach ($ContenidoDetalle as $key => $detail){
                $resInsert2 = $this->pedeo->insertRow($sqlInsert2,
                array(":of1_type" =>$detail['of1_type'],
                ":of1_description" =>$detail['of1_description'],
                ":of1_quantitybase" =>$detail['of1_quantitybase'],
                ":of1_ratiobase" =>$detail['of1_ratiobase'],
                ":of1_uom" =>$detail['of1_uom'],
                ":of1_whscode" =>$detail['of1_whscode'],
                ":of1_emimet" =>$detail['of1_emimet'],
                ":of1_costcode" =>$detail['of1_costcode'],
                ":of1_unity" =>$detail['of1_unity'],
                ":of1_acc" =>$detail['of1_acc'],
                ":of1_ing" =>$detail['of1_ing'],
                ":of1_uom_code" =>$detail['of1_uom_code'],
                ":of1_listmat" =>isset($detail['of1_listmat']) ? $detail['of1_listmat'] : null,
                ":of1_basenum" =>  isset($detail['of1_basenum']) ? $detail['of1_basenum'] : null,
                ":of1_item_code" =>  isset($detail['of1_item_code']) ? $detail['of1_item_code'] : null,
                ":of1_item_cost" =>  isset($detail['of1_item_cost']) ? $detail['of1_item_cost'] : null,
                ":of1_docentry" => $resInsert));

                if(is_numeric($resInsert2) AND $resInsert2 > 0){

                }else{
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsert2,
                        'mensaje' => 'No se pudo realizar operación'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }

            // GUARDA LOS CAMBIOS SI TODO VA BIEN
            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' => 'Orden de fabricación registrada con exito'
            );

        }else{
            $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsert,
                        'mensaje' => 'No se pudo realizar operacion'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
        }

        $this->response($respuesta);

    }

    public function getManufacturingOrder_get()
    {
        $sqlSelect = "SELECT * from tbof";

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
                'mensaje'    => 'busqueda sin resultados'
            );
        }

        $this->response($respuesta);
    }

    public function updateManufacturingOrder_post()
    {
        $Data = $this->post();
        if(
        !isset($Data['bof_docentry']) OR
        !isset($Data['bof_docnum']) OR
        !isset($Data['bof_item_code']) OR
        !isset($Data['bof_item_description']) OR
        !isset($Data['bof_quantity']) OR
        !isset($Data['bof_cardcode']) OR
        !isset($Data['bof_fatorydate']) OR
        !isset($Data['bof_date']) OR
        !isset($Data['bof_duedate']) OR
        !isset($Data['bof_user']) OR
        !isset($Data['bof_cust_order']) OR
        !isset($Data['bof_ccost']) OR
        !isset($Data['bof_project'])){
        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' => 'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
        }

        // SE VERIFCA QUE EL DOCUMENTO TENGA DETALLE
        $ContenidoDetalle = json_decode($Data['detail'], true);

        if (!is_array($ContenidoDetalle)) {
            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' => 'No se encontro el detalle de orden de fabricación'
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

        $sqlUpdate = "UPDATE tbof set
                        bof_type = :bof_type,
                        bof_docnum = :bof_docnum,
                        bof_item_code = :bof_item_code,
                        bof_item_description = :bof_item_description,
                        bof_quantity = :bof_quantity,
                        bof_cardcode = :bof_cardcode,
                        bof_fatorydate = :bof_fatorydate,
                        bof_date = :bof_date,
                        bof_duedate = :bof_duedate,
                        bof_user = :bof_user,
                        bof_cust_order = :bof_cust_order,
                        bof_ccost = :bof_ccost,
                        bof_project = :bof_project,
                        bof_baseentry = :bof_baseentry,
                        bof_basetype = :bof_basetype,
                        bof_status = :bof_status,
                        bof_status = :bof_status,
                        bof_status = :bof_status,
                        bof_docnum_order = :bof_docnum_order,
                        bof_docentry_order =:bof_docentry_order
                        where bof_docentry = :bof_docentry";


        $this->pedeo->trans_begin();


        $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
        ":bof_type" =>$Data['bof_type'],
        ":bof_docnum" =>$Data['bof_docnum'],
        ":bof_item_code" =>$Data['bof_item_code'],
        ":bof_item_description" =>$Data['bof_item_description'],
        ":bof_quantity" =>$Data['bof_quantity'],
        ":bof_cardcode" =>$Data['bof_cardcode'],
        ":bof_fatorydate" =>$Data['bof_fatorydate'],
        ":bof_date" =>$Data['bof_date'],
        ":bof_duedate" =>$Data['bof_duedate'],
        ":bof_user" =>$Data['bof_user'],
        ":bof_cust_order" =>$Data['bof_cust_order'],
        ":bof_ccost" =>$Data['bof_ccost'],
        ":bof_project" =>$Data['bof_project'],
        ":bof_docentry" => $Data['bof_docentry'],
        ":bof_baseentry" => isset($Data['bof_baseentry']) ? $Data['bof_baseentry'] :0,
        ":bof_basetype" => isset($Data['bof_basetype']) ? $Data['bof_basetype'] :0,
        ":bof_docnum_order" =>  isset($Data['bof_docnum_order']) ? $Data['bof_docnum_order'] :0,
        ":bof_docentry_order" =>  isset($Data['bof_docentry_order']) ? $Data['bof_docentry_order'] :0,
        ":bof_status" => $Data['bof_status']));

        if( is_numeric($resUpdate) AND $resUpdate > 0){

            $this->pedeo->queryTable("DELETE FROM bof1 WHERE of1_docentry = :of1_docentry", array(':of1_docentry' => $Data['bof_docentry']));


            $sqlInsert2 = "INSERT INTO bof1 (of1_type, of1_description, of1_quantitybase, of1_ratiobase, of1_uom, of1_whscode, of1_emimet, of1_costcode, of1_unity, of1_docentry, of1_acc, of1_ing, of1_uom_code, of1_listmat, of1_basenum, of1_item_code, of1_item_cost) VALUES (:of1_type, :of1_description, :of1_quantitybase, :of1_ratiobase, :of1_uom, :of1_whscode, :of1_emimet, :of1_costcode, :of1_unity, :of1_docentry, :of1_acc, :of1_ing, :of1_uom_code, :of1_listmat, :of1_basenum, :of1_item_code, :of1_item_cost)";
            foreach ($ContenidoDetalle as $key => $detail){
                $resInsert2 = $this->pedeo->insertRow($sqlInsert2,
                array(":of1_type" =>$detail['of1_type'],
                ":of1_description" =>$detail['of1_description'],
                ":of1_quantitybase" =>$detail['of1_quantitybase'],
                ":of1_ratiobase" =>$detail['of1_ratiobase'],
                ":of1_uom" =>$detail['of1_uom'],
                ":of1_whscode" =>$detail['of1_whscode'],
                ":of1_emimet" =>$detail['of1_emimet'],
                ":of1_costcode" =>$detail['of1_costcode'],
                ":of1_unity" =>$detail['of1_unity'],
                ":of1_acc" =>$detail['of1_acc'],
                ":of1_ing" =>$detail['of1_ing'],
                ":of1_uom_code" =>$detail['of1_uom_code'],
                ":of1_listmat" =>isset($detail['of1_listmat']) ? $detail['of1_listmat'] :null,
                ":of1_basenum" =>isset($detail['of1_basenum']) ? $detail['of1_basenum'] :null,
                ":of1_item_code" =>  isset($Data['of1_item_code']) ? $detail['of1_item_code'] : null,
                ":of1_item_cost" =>  isset($Data['of1_item_cost']) ? $detail['of1_item_cost'] : null,
                ":of1_docentry" => $Data['bof_docentry']));

                if(is_numeric($resInsert2) AND $resInsert2 > 0){

                }else{
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsert2,
                        'mensaje' => 'No se pudo realizar operación'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }

            // GUARDA LOS CAMBIOS SI TODO VA BIEN
            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resUpdate,
                'mensaje' => 'Orden de fabricación actualizada con exito'
            );

        }else{
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error' => true,
                'data' => $resUpdate,
                'mensaje' => 'No se pudo realizar operación'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $this->response($respuesta);


    }

    public function getManufacturingOrderById_get(){

        $Data = $this->get();

        if (!isset($Data['bof_docentry'])) {
            $respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
        }

        $sqlSelect = "SELECT * FROM tbof WHERE bof_docentry = :bof_docentry";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":bof_docentry" => $Data['bof_docentry']));

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

    public function getManufacturingOrderDetailById_get(){

        $Data = $this->get();

				if (!isset($Data['of1_docentry'])) {
				    $respuesta = array(
						'error' => true,
						'data'  => array(),
						'mensaje' => 'La informacion enviada no es valida'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

        $sqlSelect = "SELECT * FROM bof1 WHERE of1_docentry = :of1_docentry";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":of1_docentry" => $Data['of1_docentry']));

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

		//OBTENER ORDENES DE FABRICACION CON ESTADO LIBERADAS
		public function getManufacturingOrderReleased_get(){

			$sqlSelect = "SELECT * from tbof WHERE bof_status = :bof_status";

			$resSelect = $this->pedeo->queryTable($sqlSelect, array(':bof_status' => 1));

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
							'mensaje'    => 'busqueda sin resultados'
					);
			}

			$this->response($respuesta);

		}
}
