<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class ComparationOffert extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
        $this->load->library('generic');

	}
    
    //OBTENER SOLICITUDES DE COMPRA EN ESTADO ABIERTO
    public function getPurchRquest_get()
    {
        $request = $this->get();
        $DECI_MALES =  $this->generic->getDecimals();
        //VALIDAR QUE VENGA EL DATO DE LA EMPRESA Y DEL LA SUCURSAL
        if(!isset($request['business']) OR !isset($request['branch'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion envidada invalida'
            );

            return $this->response($respuesta);
        }
        $where = "";
        if(isset($request['csc_cardcode']) && !empty($request['csc_cardcode'])){
            $where = " AND csc_cardcode = '".$request['csc_cardcode']."'";
        }
        // print_r($request);exit;
        $campos = ",T4.dms_phone1, T4.dms_phone2, T4.dms_cel";
		$sql = self::getColumn('dcsc', 'csc', $campos, '', $DECI_MALES,$request['business'], $request['branch'],0,0,0," AND t1.estado = 'Abierto' {where}");
        $sql = str_replace("{where}",$where,$sql);
        // print_r($sql);exit;
        $resSql = $this->pedeo->queryTable($sql,array());

        if(isset($resSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'mensaje' => 'OK'
            );
        }else{
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'No se encontraron datos en la busqueda'
            );
        }

        $this->response($respuesta);
    }

    public function getComparation_get()
    {
        $request = $this->get();
        $DECI_MALES =  $this->generic->getDecimals();
        //VALIDAR EL CODIGO DE LA EMPRESA Y LA SUCURSAL
        if(!isset($request['business']) OR !isset($request['branch']) OR !isset($request['csc_docentry'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion envidada invalida'
            );

            return $this->response($respuesta);
        }

        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );

        $arrayNew = [];
        $arrayOffert = [];
        $campos = ",T4.dms_phone1, T4.dms_phone2, T4.dms_cel";
        $sql = self::getColumn('dcsc', 'csc', $campos, '', $DECI_MALES,$request['business'], $request['branch'],0,0,0," AND t1.estado = 'Abierto' AND t0.csc_docentry = :csc_docentry");
        $resSql = $this->pedeo->queryTable($sql,array(
            ':csc_docentry' => $request['csc_docentry']
        ));

        if(isset($resSql[0])){

            // BUSCAR DETALLE DE LA SOLICITUD

            $detalle = $this->pedeo->queryTable('SELECT * FROM csc1 WHERE sc1_docentry = :sc1_docentry', array("sc1_docentry" => $request['csc_docentry']));

            if (isset($detalle[0])){

                // SE BUSCAN TODAS LAS OFERTAS EN BASE A LA SOLICITUD
                $dcoc = $this->pedeo->queryTable('SELECT dcoc.*, estado FROM dcoc INNER JOIN responsestatus ON coc_docentry = responsestatus.id AND coc_doctype = responsestatus.tipo WHERE coc_baseentry = :coc_baseentry AND coc_basetype = :coc_basetype ', array(":coc_basetype" => 10, ":coc_baseentry" => $request['csc_docentry']));


                if ( isset($dcoc[0]) ) {

                    foreach ($dcoc as $key => $value) {

                        $detalleOferta = $this->pedeo->queryTable("SELECT * FROM coc1 WHERE oc1_docentry = :oc1_docentry", array(":oc1_docentry" => $value['coc_docentry']));


                        $ponderacion = 0;

                        if (isset($detalleOferta[0])){

                            $ponderacion = self::ObtenerPonderacion( $detalle, $detalleOferta);

                            $dcoc[$key]['ponderacion'] = $ponderacion;

                        }else{
                            $dcoc[$key]['ponderacion'] = $ponderacion;
                        }

                    }

                    $respuesta = array(
                        'error'   => false,
                        'data'    => $dcoc,
                        'mensaje' => ''
                    );

                } else { 
                    $respuesta = array(
                        'error' => true,
                        'data' => [],
                        'mensaje' => 'No se encontraron ofertas para comparar'
                    );
                }
            }else{

                $respuesta = array(
                    'error' => true,
                    'data' => [],
                    'mensaje' => 'No se encontraron datos en la busqueda'
                );
            }
        }else{
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'No se encontraron datos en la busqueda'
            );
        }
     
        $this->response($respuesta);        
    }

    public function updateOffert_post()
    {
        $post = $this->post();
        //VARIABLES
        $value = 0;
        if(!isset($post['business']) OR !isset($post['branch']) OR !isset($post['coc_docentry']) OR !isset($post['coc_baseentry']) OR !isset($post['coc_basetype'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'Informacion envidada invalida'
            );

            return $this->response($respuesta);
        }
        $this->pedeo->trans_begin();
        $select = "SELECT * FROM dcoc WHERE coc_moffert = :coc_moffert AND coc_baseentry = :coc_baseentry AND coc_basetype = :coc_basetype";
        $resSelect = $this->pedeo->queryTable($select,array(
            ':coc_moffert' => 1,
            ':coc_baseentry' => $post['coc_baseentry'],
            ':coc_basetype' => $post['coc_basetype']
        ));
        // print_r($resSelect);exit;
        if(isset($resSelect[0])){
            $updateOld = "UPDATE dcoc SET coc_moffert = :coc_moffert WHERE business = :business AND branch = :branch AND coc_docentry = :coc_docentry";
            $resUpdateOld = $this->pedeo->updateRow($updateOld,array(
                ':coc_moffert' => 0,
                ':business' => $resSelect[0]['business'],
                ':branch' => $resSelect[0]['branch'],
                ':coc_docentry' => $resSelect[0]['coc_docentry']
            ));

            if(is_numeric($resUpdateOld) && $resUpdateOld > 0){

            }else {
                $this->pedeo->trans_rollback();
                $respuesta = array(
                    'error' => true,
                    'data' => [],
                    'mensaje' => 'No se pudo actualizar la oferta de compra'
                );

                return $this->response($respuesta);
            }
        }

        $updateNew = "UPDATE dcoc SET coc_moffert = :coc_moffert WHERE business = :business AND branch = :branch AND coc_docentry = :coc_docentry";
        $resUpdateNew = $this->pedeo->updateRow($updateNew,array(
            ':coc_moffert' => 1,
            ':business' => $post['business'],
            ':branch' => $post['branch'],
            ':coc_docentry' => $post['coc_docentry']
        ));

        if(is_numeric($resUpdateNew) && $resUpdateNew > 0){
            $respuesta = array(
                'error' => false,
                'data' => $resUpdateNew,
                'mensaje' => 'Oferta Actualizada'
            );
        }else{
            $this->pedeo->trans_rollback();
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'No se pudo actualizar la oferta de compra'
            );

            return $this->response($respuesta);
        }

        $this->pedeo->trans_commit();
        $this->response($respuesta);
    }

    function ObtenerPonderacion( $DetalleSolicitud, $DetalleOferta ) {

        $pesoCantidad = 3;
        $constante1 = 1;
        $pesoFecha = 4;
        $pesoPrecio = 3;

        $puntuacion = 0;

        foreach ($DetalleOferta as $key => $oferta) {

            $solOriginal = self::ObtenerItemByCode($DetalleSolicitud, $oferta['oc1_itemcode']);

            if (isset($solOriginal['sc1_quantity'])) {

                $ResPrecioReferencia = $this->pedeo->queryTable("SELECT * FROM tbdi WHERE bdi_itemcode = :bdi_itemcode AND bdi_whscode = :bdi_whscode", array(":bdi_itemcode" => $oferta['oc1_itemcode'], ":bdi_whscode" => $oferta['oc1_whscode'] ));
            
                $precioReferencia = isset($ResPrecioReferencia[0]) ?  $ResPrecioReferencia[0]['bdi_avgprice'] : 1;
                $cantidadOfrecida = $oferta['oc1_quantity'];
                $cantidadSolicitada =  $solOriginal['sc1_quantity'];
                $fechaOfrecida = new DateTime($oferta['oc1_fechaentrega']);
                $fechaSolicitada = new DateTime($solOriginal['sc1_fechaentrega']);
                $precioOferta = $oferta['oc1_price'];

                $vCnt = 0;
                if ( $cantidadOfrecida > $cantidadSolicitada ) {
                    $cantidadOfrecida = $cantidadSolicitada;
                    $cnt = ( ($cantidadOfrecida / $cantidadSolicitada) * $pesoCantidad );
                    $vCnt = $cnt;
                } else if ( $cantidadOfrecida == 0 ) {
                    $vCnt = ($cantidadSolicitada * $pesoCantidad) * -1;
                }else if ( $cantidadOfrecida < $cantidadSolicitada ) {
                    $cnt = $cantidadOfrecida;
                    $vCnt = $cnt;
                }else{
                    $cnt = ($cantidadOfrecida * $pesoCantidad );
                    $vCnt = $cnt;
                }

                $diff = $fechaOfrecida->diff($fechaSolicitada);

                $dias = $diff->d + round($diff->h / 24, 2);




                $vF = 0;
                if ( $dias > 0 ) {
                    $vF = ($dias * $pesoFecha);
                }else if ( $dias == 0 ) {
                    $vF = ( 1 * $pesoFecha );
                }else if ($dias < 0 ) {
                    $vF = ($dias * $pesoFecha) * -1;
                }


                

                $vP = ((($precioReferencia / $precioOferta) * $precioReferencia) * $pesoPrecio);

                // print_r( $vCnt );
                // echo "\n";
                // print_r( $vF );
                // echo "\n";
                // print_r( $vP  );
                // echo "\n";
            
              

                $puntuacion = $puntuacion + ( $vCnt + $vF + $vP );

            }


 
        }


        return $puntuacion;

    }


    function ObtenerItemByCode( $Data, $Item ) {

        $array = [];

        foreach ($Data as $key => $value) {

            if ($value['sc1_itemcode'] == $Item) {

                $array = $value;

            }
        }

        return $array;
    }
}
