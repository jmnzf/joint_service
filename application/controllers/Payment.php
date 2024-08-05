<?php
// CONTROLADOR DE PASARELA DE PAGOS PLACETOPAY
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');


use Restserver\libraries\REST_Controller;


class Payment extends REST_Controller
{

    private $pdo;
    private $endpoint;
    private $login;
    private $secretkey;

    public function __construct()
    {
        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        parent::__construct();
        $this->load->database();
        $this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
        $this->load->library('generic');

        $this->endpoint  = 'https://checkout-test.placetopay.com';
        $this->login     = '2add7dd1a28e0eff31945f976e571763';
        $this->secretkey = 'A4qcCLvCe39zm1k3';
    }


    // GENERA UNA SESION DE PAGO QUE EXPIRA EN 15 MIN
    public function createSesionPayment_post()
    {
        $Data = $this->post();


        if ( !isset( $Data['cardcode'] ) OR !isset($Data['cardtype']) OR !isset($Data['detail']) OR !isset($Data['total'])){
            $respuesta = array(
                'error'   => true,
                'data'    => [],
                'mensaje' => 'Faltan datos para procesar'
            );

            return $this->response($respuesta);
        }


        if ( is_array($Data['detail']) ) {

            $ContenidoDetalle = $Data['detail'];

        } else {

            $ContenidoDetalle = json_decode($Data['detail'], true);

        }

        if ( !is_array($ContenidoDetalle) && count($ContenidoDetalle) == 0 ) {

            $respuesta = array(
                'error'   => true,
                'data'    => [],
                'mensaje' => 'Sin detalle para procesar'
            );

            return $this->response($respuesta);
        }

        // VALIDAR SESION ACTIVA
        $resValidate = $this->pedeo->queryTable("SELECT * FROM tbpp WHERE bpp_cardcode = :bpp_cardcode AND bpp_cardtype = :bpp_cardtype AND bpp_status = :bpp_status", array(
            ":bpp_cardcode" => $Data['cardcode'],
            ":bpp_cardtype" => $Data['cardtype'],
            ":bpp_status"   => 1
        ));

        if ( isset($resValidate[0]) ) {

            $respuesta = array(
                'error'   => true,
                'data'    => [],
                'mensaje' => 'Existe una sesión de pago activa'
            );

            return $this->response($respuesta);
        }
  
        // SE INICIA LA TRANSACCION
        $this->pedeo->trans_begin();
        // CREDENCIALES
        $credential = self::generateAuth($this->login, $this->secretkey);
    
        // NUMERO DE REFERENCIA DEL PAGO
        $reference = self::generateUniqueReference();

        // DATOS PARA LA SOLICITUD DEL PAGO
        $request = [
            'locale'=> 'es_CO',
            'auth' => $credential,
            'payment' => [
                'reference' => $reference,
                'description' => 'Pago de Factura',
                'amount' => [
                    'currency' => 'COP',
                    'total' => $Data['total'],
                ],
            ],
            'expiration' => date('c', strtotime('+15 minutes')),
            'returnUrl'  => 'https://joint.jointerp.com/?c=PayMasive&a=Index&ref=' . $reference,
            'ipAddress'  => self::getClientIP(),
            'userAgent'  => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36',
        ];

        $curl = curl_init();

        curl_setopt_array(
          $curl,
          array(
          CURLOPT_URL => $this->endpoint.'/api/session',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => json_encode($request),
          CURLOPT_HTTPHEADER => array(
              'Content-Type: application/json'
            ),
          )
        );
    
        $response = curl_exec($curl);
        curl_close($curl);


        $result = json_decode($response, true);

        if ( isset( $result['status'] ) &&  $result['status']['status'] == 'OK' ) {

            if ( !empty($result['requestId'] ) && !empty($result['processUrl']) ) {


                $sqlInsert = "INSERT INTO tbpp(bpp_cardcode,bpp_cardtype,bpp_doctotal,bpp_reference,bpp_requestid,
                            bpp_date,bpp_url,bpp_status,business,branch,bpp_cardname)VALUES(:bpp_cardcode,:bpp_cardtype,
                            :bpp_doctotal,:bpp_reference,:bpp_requestid,:bpp_date,:bpp_url,:bpp_status,:business,:branch,:bpp_cardname)";
                
                $resInsert = $this->pedeo->insertRow($sqlInsert, array (
                    
                    ':bpp_cardcode'  => $Data['cardcode'],
                    ':bpp_cardtype'  => $Data['cardtype'],
                    ':bpp_doctotal'  => $Data['total'],
                    ':bpp_reference' => $reference,
                    ':bpp_requestid' => $result['requestId'],
                    ':bpp_date'      => date("Y-m-d"),
                    ':bpp_url'       => $result['processUrl'],
                    ':bpp_status'    => 1,
                    ':business'      => isset($Data['business']) ? $Data['business'] : 1,
                    ':branch'        => isset($Data['branch']) ? $Data['branch'] : 1,
                    ':bpp_cardname'  => $Data['cardname']
                ));


                if ( is_numeric($resInsert) && $resInsert > 0 ) {

                    $validateMonto = 0;
                    foreach ($ContenidoDetalle as $key => $value) {

                        $sqlInsertDetail = "INSERT INTO bpp1(pp1_docid,pp1_docentry,pp1_doctype,pp1_monto,pp1_vlrtotal,
                                            pp1_docduedate,pp1_daysbackw,pp1_cuenta,pp1_line_num,pp1_docnum)
                                            VALUES(:pp1_docid,:pp1_docentry,:pp1_doctype,:pp1_monto,:pp1_vlrtotal,
                                            :pp1_docduedate,:pp1_daysbackw,:pp1_cuenta,:pp1_line_num,:pp1_docnum)";

                        $resInsertDetail = $this->pedeo->insertRow($sqlInsertDetail, array(
                            ':pp1_docid'     => $resInsert,
                            ':pp1_docentry'  => $value['docentry'],
                            ':pp1_doctype'   => $value['pr1_doctype'],
                            ':pp1_monto'     => $value['monto'],
                            ':pp1_vlrtotal'  => $value['pr1_vlrtotal'],
                            ':pp1_docduedate'=> $value['pr1_docduedate'],
                            ':pp1_daysbackw' => $value['pr1_daysbackw'],
                            ':pp1_cuenta'    => $value['pr1_cuenta'],
                            ':pp1_line_num'  => $value['ac1_line_num'],
                            ':pp1_docnum'    => $value['pr1_docnum']
                        ));

                        $validateMonto = $validateMonto + $value['monto'];

                        if ( isset($resInsertDetail[0]) && $resInsertDetail > 0 ){

                        }else{

                            $this->pedeo->trans_rollback();

                            $respuesta = array(
                                'error'   => true,
                                'data'    => $resInsertDetail,
                                'mensaje' => 'No fue posible almacenar la información del detalle del pago'
                            );

                            return $this->response($respuesta);
                        }

                        
                    }


                    // SE VALIDA QUE EL MONTO TOTAL A PAGAR
                    // SEA IGUAL A LA SUMA DE LOS DETALLES
                    if ( $validateMonto == $Data['total'] ){

                        $respuesta = array(
                            "error"   => false,
                            "data"    => array("referencia" => $reference, "url" => $result['processUrl']),
                            "mensaje" => ''
                        );

                    } else {

                        $this->pedeo->trans_rollback();

                        $respuesta = array(
                            "error"   => true,
                            "data"    => $validateMonto,
                            "mensaje" => 'El monto total a pagar no es igual al monto de la suma de los detalles'
                        );

                        return $this->response($respuesta);
                    }

                 

                } else {

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error'   => true,
                        'data'    => $resInsert,
                        'mensaje' => 'No fue posible almacenar la información del pago'
                    );
                    
                    return $this->response($respuesta);
                }

            }else{

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data'    => $result,
                    'mensaje' => 'No se pudo generar la sesión de pago, intente nuevamente por favor'
                );

                return $this->response($respuesta);
            }

        } else {

            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error'   => true,
                'data'    => $result,
                'mensaje' => 'No se pudo generar la sesión de pago, intente nuevamente por favor'
            );

            return $this->response($respuesta);

        }

        // FINALIZA LA TRANSACCION SI TODO ESTA OK
        $this->pedeo->trans_commit();

        $this->response($respuesta);
    }


    // CONSULTA EL ESTADO DE LA SESION DE PAGO
    // EN CASO DE QUE EL PAGO YA ESTE APROVADO 
    // SE ENVIA HACER EL PAGO RECIBIDO EN LA PLATAFORMA
    public function getSesionPayment_post(){

        $Data = $this->post();


        $PARAMS =  $this->generic->getParams();



        $sqlSelect = "SELECT * FROM tbpp WHERE bpp_reference = :bpp_reference";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(
            ":bpp_reference" => $Data['reference']
        ));


        if ( isset($resSelect[0]) ){


            // CREDENCIALES
            $credential = self::generateAuth($this->login, $this->secretkey);

            // DATOS PARA LA SOLICITUD DEL PAGO
            $request = [
                'auth' => $credential,
            ];
        

            $curl = curl_init();

            curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => $this->endpoint.'/api/session/'.$resSelect[0]['bpp_requestid'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($request),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                    ),
            ));
    
            $response = curl_exec($curl);
            curl_close($curl);


            $result = json_decode($response, true);


            if ( isset($result['requestId']) && $result['requestId'] == $resSelect[0]['bpp_requestid'] ) {

                $respuesta = array(
                    'error'   => false,
                    'data'    => $result,
                    'mensaje' => ''
                );


                $est = $result['status']['status'];
                $val = 0;
                if ( $est == 'PENDING' ) {
                    $val = 1;
                }

                $ressUpdate = $this->pedeo->updateRow("UPDATE tbpp SET bpp_status = :bpp_status, bpp_status2 = :bpp_status2 WHERE bpp_reference = :bpp_reference",
                                                        array(
                                                        ":bpp_reference" => $Data['reference'],
                                                        ":bpp_status"    => $val,
                                                        ":bpp_status2"   => $est
                                                    ));

                $curl2 = curl_init();

                curl_setopt_array(
                $curl2,
                array(
                    CURLOPT_URL => "https://localhost/".$PARAMS['apirest']."/index.php/PaymentsReceived/automaticPayment",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode(["data" => '']),
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json'
                        ),
                ));
        
                $response2 = curl_exec($curl2);
                curl_close($curl2);

                return $this->response($respuesta);

            } else {

                $respuesta = array(
                    'error'   => true,
                    'data'    => [],
                    'mensaje' => 'No se pudo confirmar la información, intente nuevamente'
                );

                return $this->response($respuesta);
            }

        
        } else {

            $respuesta = array(
                'error'   => true,
                'data'    => [],
                'mensaje' => 'No existe la referencia solicitada'
            );

            return $this->response($respuesta);
        }
    }


    // RECIBE CALLBACK DESDE LA PASARELA CON INFORMACION DEL ESTADO
    // DEL PAGO
    public function signOff_post() {

  
        $Data = $this->post();

        $PARAMS =  $this->generic->getParams();

        if ( is_array($Data) ){

        }else{
            $Data = json_decode($Data);
        }


        if ( isset($Data['status']) && !empty($Data['status']['status'])) {

            $auth = self::generateAuth2($Data['requestId'], $Data['status']['status'], $Data['status']['date'], $this->secretkey);

            if ( $auth == $Data['signature'] ) {

                $sqlOperacion = "SELECT * FROM tbpp WHERE bpp_reference = :bpp_reference AND bpp_requestid = :bpp_requestid";

                $resOperacion = $this->pedeo->queryTable($sqlOperacion, array(
                    ":bpp_reference" => $Data['reference'],
                    ":bpp_requestid" => $Data['requestId']
                ));

                if ( isset($resOperacion[0]) ) {

                    $sqlUpdate = "UPDATE tbpp set bpp_status = :bpp_status, bpp_status2 = :bpp_status2 WHERE bpp_id = :bpp_id";

                    $est = $Data['status']['status'];
                    $val = 0;
                    if ( $est == 'PENDING' ) {
                        $val = 1;
                    }

                    $resUpdate  = $this->pedeo->updateRow($sqlUpdate, array(
                        ':bpp_status'  => $val,
                        ':bpp_status2' => $Data['status']['status'],
                        ':bpp_id'      => $resOperacion[0]['bpp_id']
                    ));


                    $curl2 = curl_init();

                    curl_setopt_array(
                    $curl2,
                    array(
                        CURLOPT_URL => "https://localhost/".$PARAMS['apirest']."/index.php/PaymentsReceived/automaticPayment",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => json_encode(["data" => '']),
                        CURLOPT_HTTPHEADER => array(
                            'Content-Type: application/json'
                            ),
                    ));
            
                    $response2 = curl_exec($curl2);
                    curl_close($curl2);

                    if ( is_numeric($resUpdate) && $resUpdate == 1 ) {

                        $respuesta = array(
                            "error"   => false,
                            "data"    => [],
                            "mensaje" => "Sesión cerrada con exito"
                        );

                        return $this->response($respuesta);

                    } else {

                        $respuesta = array(
                            "error"   => true,
                            "data"    => $resUpdate,
                            "mensaje" => "No fue posible cerrar la sesión"
                        );

                        return $this->response($respuesta);
                    }

                }else{

                    $respuesta = array(
                        "error"   => true,
                        "data"    => [],
                        "mensaje" => "No existe una operación relacionada con la referencia: ".$Data['reference'].' y el id: '.$Data['requestId']
                    );
    
                    return $this->response($respuesta);
                }

            } else {

                $respuesta = array(
                    "error"   => true,
                    "data"    => [],
                    "mensaje" => "No coinciden las firmas"
                );

                return $this->response($respuesta);
            }

        } else {

            $respuesta = array(
                "error"   => true,
                "data"    => [],
                "mensaje" => "No se pudo comprobar el estado en la respuesta"
            );

            return $this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
        }
       
    }


    // METODO CREAR LA AUTENTICACION DE CREACION Y CONSULTA DE SESION DE PAGO
    private static function generateAuth(string $login, string $tranKey) {
        $nonce = random_bytes(16);
        $seed = date('c');
        $digest = base64_encode(hash('sha256', $nonce . $seed . $tranKey, true));
        return [
            'login' => $login,
            'tranKey' => $digest,
            'nonce' => base64_encode($nonce),
            'seed' => $seed,
        ];
    }

    // METODO CREAR LA AUTENTICACION DE UNA NOTIFICACION DE ESTADO DE PAGO
    private static function generateAuth2($requestId, $status, $date, $tranKey) {

        $dataToHash = $requestId . $status . $date . $tranKey;

        $hash = sha1($dataToHash);


        return $hash;
       
    }

    // METODO PARA GENERAR UNA REFERENCIA DE PAGO UNICA 
    // POR SESION DE PAGO 
    private static function  generateUniqueReference() {
        // Obtener la fecha actual en el formato yyyymmdd
        $date = date('Ymd');
    
        // Obtener los microsegundos actuales y asegurar que sean 6 dígitos
        $microseconds = sprintf("%06d", microtime(true) * 1000000);
    
        // Tomar los últimos 7 dígitos de los microsegundos para asegurar que no exceda los 15 dígitos en total
        $uniquePart = substr($microseconds, -7);
    
        // Combinar la fecha y la parte única
        $reference = $date . $uniquePart;
    
        return $reference;
    }


    // METODO PARA OBTENER LA DIRECION IP PUBLICA DEL CLIENTE
    private static function getClientIP(){
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED']) && !empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && !empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR']) && !empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED']) && !empty($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }
        return $ipaddress;
    }
}
