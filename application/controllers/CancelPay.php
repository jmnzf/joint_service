<?php
// INFORME PARA BALANCE Y ESTADO DE RESULTADOS

defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class CancelPay extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
        $this->load->library('CancelPay');
	}

    public function cancelPay_post() {

        $Data = $this->post();
    
    
        if (!isset($Data['doctype']) or !isset($Data['docentry']) or !isset($Data['is_fechadoc']) or !isset($Data['comments']) or !isset($Data['series']) or !isset($Data['createby'])) {
    
            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'Informacion enviada no valida',
            );
    
            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
    
            return;
        }
    
        // SE BUSCA EL DOCUMENTO  PARA VERIFICAR QUE SEA UN PAGO A CUENTA DE TERCERO
        $sql = '';
        $op = 0;
        $type = 0;
        $pfd = '';
        $pfc = '';
        $tabla = '';
        $pc1 = '';
        $tabla2 = '';
        $pc2  = '';
    
        if ( $Data['doctype'] == 19 ){
    
            $sql = 'SELECT * FROM bpe1 WHERE pe1_docnum = :docnum';
            $op = 0;
            $type = 19;
            $pfd = 'pe1_doctype';
            $pfc = 'pe1_docnum';
            $tabla = 'bpe1';
            $tabla2 = 'gbpe';
            $pc1 = 'pe1';
            $pc2 = 'bpe';
    
        } else if ( $Data['doctype'] == 20 ) {
    
            $sql = 'SELECT * FROM bpr1 WHERE pr1_docnum = :docnum';
            $op = 1;
            $type = 20;
            $pfd = 'pr1_doctype';
            $pfc = 'pr1_docnum';
            $tabla = 'bpr1';
            $tabla2 = 'gbpr';
            $pc1 = 'pr1';
            $pc2 = 'bpr';
    
        } else if ( $Data['doctype'] == 22 ){
    
            $sql = 'SELECT * FROM crc1 WHERE rc1_docentry = :docnum';
            $op = 1;
            $type = 22;
            $pfd = 'rc1_doctype';
        }
    
        $document = $this->pedeo->queryTable($sql, array(
            ':docnum' => $Data['docentry']
        ));
    
    
        if ( isset($document[0]) ){
    
    
            if ($type == 19 || $type == 20){
    
                if ( count($document) == 1 && $type == $document[0][$pfd] ){
    
                    // SE VERIFICA QUE NO ESTE EN USO ESE PAGO EN OTRA OPERACIÓN
                    // DE UN PAGO
                    $verificar = 0;
                    $sqlUso = "SELECT * 
                            FROM ".$tabla." 
                            WHERE ".$pc1."_docentry =".$document[0][$pfc]." 
                            AND ".$pc1."_doctype =".$document[0][$pfd];
    
                    $resUso = $this->pedeo->queryTable($sqlUso, array());        
    
                  
    
                    if ( isset($resUso[0]) ) {
                        // SE VERIFICA QUE EL PAGO NO ESTE ANULADO
                        $verificar = 1;
                        $sqlDoc = "SELECT * FROM ".$tabla2." INNER JOIN responsestatus r ON ".$pc2."_docentry = r.id AND ".$pc2."_doctype = r.tipo  WHERE ".$pc2."_docentry = ".$resUso[0][$pfc]." AND r.estado != 'Anulado'";
                        $resDoc = $this->pedeo->queryTable($sqlDoc, array());
                    
                        $operacion = '';
    
                        if ( $type == 20 ){
                            $operacion = 'Pago Recibido';
                        }else if ($type == 19){
                            $operacion = 'Pago Efectuado';
                        }
    
                        if ( isset($resDoc[0]) ) {
    
                            $respuesta = array(
                                'error'  => true,
                                'data'   => $resDoc,
                                'mensaje' => 'No se puede anular el pago, porque el mismo fue utilizado en el '.$operacion.' #'.$resDoc[0][$pc2.'_docnum']
                            );
            
                            return $this->response($respuesta); 
                        }
                    }
    
                    if ($verificar == 0) {
                        // SE VERIFICA QUE EL DOCUMENTO NO SE USO EN UNA COMPENSACION DE TERCEROS
                        $sqlUso2 = "SELECT * from crc1
                                    where rc1_basetype = :rc1_basetype
                                    and rc1_baseentry = :rc1_baseentry";
    
                        $resUso2 = $this->pedeo->queryTable($sqlUso2, array(
                            ':rc1_basetype'  => $document[0][$pfd],
                            ':rc1_baseentry' => $document[0][$pfc]
                        ));        
                        
                        if( isset($resUso2[0]) ) {
    
                            $sqlDoc2 = "SELECT * FROM dcrc INNER JOIN responsestatus r ON crc_docentry = r.id AND crc_doctype = r.tipo  WHERE crc_docentry = :crc_docentry AND r.estado != 'Anulado'";
    
                            $resDoc2 = $this->pedeo->queryTable($sqlDoc2, array (
                                ':crc_docentry' => $resUso2[0]['rc1_docentry']
                            ));
    
                            if (isset($resDoc2[0])){
    
                                $respuesta = array(
                                    'error'  => true,
                                    'data'   => $resDoc2,
                                    'mensaje' => 'No se puede anular el pago, porque el mismo fue utilizado en la compensación de cuentas de terceros #'.$resDoc2[0]['crc_docnum']
                                );
                
                                return $this->response($respuesta); 
                            }
                        }
                    }
                }
            }
    
            $sqlmac = "SELECT * FROM mac1 where ac1_font_key  = :keey and ac1_font_type = :typee";
            $resMac = $this->pedeo->queryTable($sqlmac, array(
                ':keey'  => $Data['docentry'],
                ':typee' => $Data['doctype']
            ));
    
            if (!isset($resMac[0])){
                $respuesta = array(
                    'error' => true,
                    'data' => array(),
                    'mensaje' => 'No se encontro el id de la transacción',
                );
    
                return $this->response($respuesta);  
            }
    
    
            $obj = array(
                "Op" => $op,
                "trans_id" => $resMac[0]['ac1_trans_id'],
                "is_fechadoc" => $Data['is_fechadoc'],
                "comments" => $Data['comments'],
                "series" => $Data['series'],
                "currency" => $Data['currency'],
                "business" => $Data['business'],
                "branch" => $Data['branch'],
                "createby" => $Data['createby'],
                "docentry" => $Data['docentry'],
                "doctype" => $Data['doctype'],
                "fecha_select" => date("Y-m-d"),
                "fecha_doc" => $resMac[0]['ac1_doc_date']
            );
    
    
            $this->pedeo->trans_begin();
    
    
            $res = $this->cancelpay->cancelPayment($obj);
    
    
            if (isset($res['error']) && $res['error'] == false) {
    
                $this->pedeo->trans_commit();
    
                $respuesta = array(
                    'error' => false,
                    'data' =>  $res,
                    'mensaje' => $res['mensaje'],
                );
    
            } else {
    
                $this->pedeo->trans_rollback();
    
                $respuesta = array(
                    'error' => true,
                    'data' => $res,
                    'mensaje' => 'Error en el proceso de anulación',
                );
    
                return $this->response($respuesta); 
            }
    
        }else{
    
            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'No existe el documento',
            );
    
            return $this->response($respuesta);  
        }
    
        $this->response($respuesta);
    
    }


 
   
}
