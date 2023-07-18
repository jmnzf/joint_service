<?php
// MODELO DE APROBACIONES
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class ProductionEmission extends REST_Controller
{

    private $pdo;

    public function __construct()
    {

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        parent::__construct();
        $this->load->database();
        $this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
        $this->load->library('DocumentNumbering');
    }
    public function getProductionEmission_get()
    {

        $respuesta = array(
            'error'  => true,
            'data'   => [],
            'mensaje' => 'busqueda sin resultados'
        );

        $sqlSelect = "SELECT * from tbep";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if (isset($resSelect[0])) {
            $respuesta = array(
                'error'  => false,
                'data'   => $resSelect,
                'mensaje' => ''
            );
        }

        $this->response($respuesta);
    }

    public function setProductionEmission_post()
    {
        $Data = $this->post();

        $DocNumVerificado = 0;

        if (
            !isset($Data['bep_doctype']) or
            !isset($Data['bep_docnum']) or
            !isset($Data['bep_cardcode']) or
            !isset($Data['bep_cardname']) or
            !isset($Data['bep_duedev']) or
            !isset($Data['bep_docdate']) or
            !isset($Data['bep_ref'])
        ) {
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
                'mensaje' => 'No se encontro el detalle de la entrada de la emisión'
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

		// //BUSCANDO LA NUMERACION DEL DOCUMENTO
        $DocNumVerificado = $this->documentnumbering->NumberDoc($Data['bep_serie'],$Data['bep_docdate'],$Data['bep_docdate']);
		
	    if (isset($DocNumVerificado) && is_numeric($DocNumVerificado) && $DocNumVerificado > 0){
	
		}else if ($DocNumVerificado['error']){
	
		    return $this->response($DocNumVerificado, REST_Controller::HTTP_BAD_REQUEST);;
		}

        $sqlInsert = "INSERT INTO tbep ( bep_doctype, bep_docnum, bep_cardcode, bep_cardname, bep_duedev, bep_docdate, bep_ref, bep_serie, bep_baseentry, bep_basetype, bep_description, bep_createat, bep_createby, bep_status) VALUES(:bep_doctype, :bep_docnum, :bep_cardcode, :bep_cardname, :bep_duedev, :bep_docdate, :bep_ref, :bep_serie, :bep_baseentry, :bep_basetype, :bep_description, :bep_createat, :bep_createby, :bep_status)";

        $this->pedeo->trans_begin();

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
            ":bep_doctype" => $Data['bep_doctype'],
            ":bep_docnum" => $DocNumVerificado,
            ":bep_cardcode" => $Data['bep_cardcode'],
            ":bep_cardname" => $Data['bep_cardname'],
            ":bep_duedev" => $Data['bep_duedev'],
            ":bep_docdate" => $Data['bep_docdate'],
            ":bep_ref" => $Data['bep_ref'],
            ":bep_serie" => $Data['bep_serie'],
            ":bep_baseentry" => is_numeric($Data['bep_baseentry']) ? $Data['bep_baseentry'] :0,
            ":bep_basetype" => is_numeric($Data['bep_basetype']) ? $Data['bep_basetype'] :0,
            ":bep_description" => isset($Data['bep_description']) ? $Data['bep_description'] :null,
            ":bep_createat" => isset($Data['bep_createat']) ? $Data['bep_createat'] :null,
            ":bep_createby" => isset($Data['bep_createby']) ? $Data['bep_createby'] :null,
            ":bep_status" => isset($Data['bep_status']) ? $Data['bep_status'] :0
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {

            

            // Se actualiza la serie de la numeracion del documento

					$sqlActualizarNumeracion  = "UPDATE pgdn SET pgs_nextnum = :pgs_nextnum
																			 WHERE pgs_id = :pgs_id";
					$resActualizarNumeracion = $this->pedeo->updateRow($sqlActualizarNumeracion, array(
							':pgs_nextnum' => $DocNumVerificado,
							':pgs_id'      => $Data['bep_serie']
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
										':bed_doctype' => $Data['bep_doctype'],
										':bed_status' => 3, // Estado cerrado
										':bed_createby' => $Data['bep_createby'],
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
									'mensaje'	=> 'No se pudo registrar la emisión de fabricación'
								);


								$this->response($respuesta);

                }


            $sqlInsert2 = "INSERT INTO bep1 (ep1_item_description, ep1_quantity, ep1_itemcost, ep1_im, ep1_ccost, ep1_ubusiness, ep1_item_code, ep1_listmat, ep1_baseentry, ep1_plan, ep1_basenum, ep1_item_cost) values (:ep1_item_description, :ep1_quantity, :ep1_itemcost, :ep1_im, :ep1_ccost, :ep1_ubusiness, :ep1_item_code, :ep1_listmat, :ep1_baseentry, :ep1_plan, :ep1_basenum, :ep1_item_cost)";

            foreach ($ContenidoDetalle as $key => $detail) {
                $resInsert2 = $this->pedeo->insertRow($sqlInsert2, array(
                    ":ep1_item_description" => $detail['ep1_item_description'],
                    ":ep1_quantity" => $detail['ep1_quantity'],
                    ":ep1_itemcost" => is_numeric($detail['ep1_itemcost'])?$detail['ep1_itemcost']:0,
                    ":ep1_im" => is_numeric($detail['ep1_im'])?$detail['ep1_im']:0,
                    ":ep1_ccost" => $detail['ep1_ccost'],
                    ":ep1_ubusiness" => $detail['ep1_ubusiness'],
                    ":ep1_item_code" => $detail['ep1_item_code'],
                    ":ep1_listmat" => $detail['ep1_listmat'],
                    ":ep1_baseentry" => $resInsert,
                    ":ep1_plan" => is_numeric($detail['ep1_plan'])?$detail['ep1_plan']:0,
                    ":ep1_item_cost" => (isset($detail['ep1_item_cost']))?  $detail['ep1_item_cost'] : NULL,
                    ":ep1_basenum" => $detail['ep1_basenum']
                ));

                if (is_numeric($resInsert2) and $resInsert2 > 0) {
                } else {
                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $resInsert2,
                        'mensaje' => 'No se pudo realizar operacion'
                    );

                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }
            }

            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resInsert,
                'mensaje' => 'Emision de producción registrada con exito'
            );
        } else {
            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error' => true,
                'data' => $resInsert,
                'mensaje' => 'No se pudo realizar operación'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $this->response($respuesta);
    }

    public function updateProductionEmission_post(){
        $Data = $this->post();
        $respuesta = array();

        if (
            !isset($Data['bep_docentry']) or
            !isset($Data['bep_doctype']) or
            !isset($Data['bep_docnum']) or
            !isset($Data['bep_cardcode']) or
            !isset($Data['bep_cardname']) or
            !isset($Data['bep_duedev']) or
            !isset($Data['bep_docdate']) or
            !isset($Data['bep_serie']) or
            !isset($Data['bep_cardname']) or
            !isset($Data['bep_ref'])
        ) {
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
                'mensaje' => 'No se encontro el detalle'
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

        $sqlUpdate = "UPDATE tbep SET 
                    bep_doctype = :bep_doctype,
                    bep_docnum = :bep_docnum,
                    bep_cardcode = :bep_cardcode,
                    bep_duedev = :bep_duedev,
                    bep_docdate = :bep_docdate,
                    bep_ref = :bep_ref,
                    bep_serie = :bep_serie,
                    bep_cardname = :bep_cardname,
                    bep_baseentry = :bep_baseentry,
                    bep_basetype = :bep_basetype,
                    bep_description = :bep_description,
                    bep_createat = :bep_createat,
                    bep_createby = :bep_createby,
                    bep_status = :bep_status
                    WHERE bep_docentry = :bep_docentry";

         $this->pedeo->trans_begin();

         $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
         ":bep_doctype" => $Data['bep_doctype'],
         ":bep_docnum" => $Data['bep_docnum'],
         ":bep_cardcode" => $Data['bep_cardcode'],
         ":bep_cardname" => $Data['bep_cardname'],
         ":bep_duedev" => $Data['bep_duedev'],
         ":bep_docdate" => $Data['bep_docdate'],
         ":bep_ref" => $Data['bep_ref'],
         ":bep_serie" => $Data['bep_serie'],
         ":bep_baseentry" => isset($Data['bep_baseentry']) ? $Data['bep_baseentry'] :0,
         ":bep_basetype" => isset($Data['bep_basetype']) ? $Data['bep_basetype'] :0,
         ":bep_description" => isset($Data['bep_description']) ? $Data['bep_description'] :null,
         ":bep_createat" => isset($Data['bep_createat']) ? $Data['bep_createat'] :null,
         ":bep_createby" => isset($Data['bep_createby']) ? $Data['bep_createby'] :null,
         ":bep_status" => isset($Data['bep_status']) ? $Data['bep_status'] :null,
         ":bep_docentry" => $Data['bep_docentry']
        ));

         if(is_numeric($resUpdate) and $resUpdate > 0){

            $this->pedeo->queryTable("DELETE FROM bep1 WHERE ep1_baseentry = :bep_docentry", array(':bep_docentry' => $Data['bep_docentry']));


            $sqlInsert2 = "INSERT INTO bep1 (ep1_item_description, ep1_quantity, ep1_itemcost, ep1_im, ep1_ccost, ep1_ubusiness, ep1_item_code, ep1_listmat, ep1_baseentry, ep1_plan, ep1_basenum, ep1_item_cost) values (:ep1_item_description, :ep1_quantity, :ep1_itemcost, :ep1_im, :ep1_ccost, :ep1_ubusiness, :ep1_item_code, :ep1_listmat, :ep1_baseentry, :ep1_plan, :ep1_basenum, :ep1_item_cost)";

            foreach ($ContenidoDetalle as $key => $detail) {
                $resInsert2 = $this->pedeo->insertRow($sqlInsert2, array(
                    ":ep1_item_description" => $detail['ep1_item_description'],
                    ":ep1_quantity" => $detail['ep1_quantity'],
                    ":ep1_itemcost" => $detail['ep1_itemcost'],
                    ":ep1_im" => $detail['ep1_im'],
                    ":ep1_ccost" => $detail['ep1_ccost'],
                    ":ep1_ubusiness" => $detail['ep1_ubusiness'],
                    ":ep1_item_code" => $detail['ep1_item_code'],
                    ":ep1_listmat" => $detail['ep1_listmat'],
                    ":ep1_baseentry" => $Data['bep_docentry'],
                    ":ep1_plan" => $detail['ep1_plan'],
                    ":ep1_item_cost" => (isset($detail['ep1_item_cost']))?  $detail['ep1_item_cost'] : NULL,
                    ":ep1_basenum" => isset($detail['ep1_basenum']) ? $detail['ep1_basenum'] :null                    
                ));

                if (is_numeric($resInsert2) and $resInsert2 > 0) {
                } else {
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

            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' => $resUpdate,
                'mensaje' => 'Emision de producción actualizada con exito'
            );
         }else{
            $respuesta = array(
                'error' => true,
                'data'  => $resUpdate,
                'mensaje' => 'No se pudo realizar operacion'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
         }

         $this->response($respuesta);
    }
}
