<?php
// CONTROLADOR DE PASARELA DE PAGOS PLACETOPAY
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . '/libraries/REST_Controller.php');
require_once(APPPATH . '/asset/vendor/autoload.php');


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
        $this->load->library('EmailSend');

        $this->login     = '';
        $this->secretkey = '';
        $this->endpoint = '';
      
    }


    // GENERA UNA SESION DE PAGO QUE EXPIRA EN 15 MIN
    public function createSesionPayment_post()
    {
        $Data = $this->post();

        $PARAMS =  $this->generic->getParams();

        $this->login     = self::decryptCredential($PARAMS['pp_login'], base64_decode($_ENV['PP_KEY']));
        $this->secretkey = self::decryptCredential($PARAMS['pp_secret_key'], base64_decode($_ENV['PP_KEY']));
        $this->endpoint  = $_ENV['PP_ENDPOINT'];


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

        //
        $resTercero = $this->pedeo->queryTable( "SELECT dms_card_code,dms_card_name,dms_card_last_name,dms_cel,dms_email FROM dmsn WHERE dms_card_code = :dms_card_code AND dms_card_type = :dms_card_type", array(":dms_card_code" => $Data['cardcode'], ":dms_card_type" => '1') );
        if (!isset($resTercero[0])){
            $respuesta = array(
                'error'   => true,
                'data'    => $resTercero,
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
            'buyer' => [
                'name'    => $resTercero[0]['dms_card_name'],
                'surname' => $resTercero[0]['dms_card_last_name'],
                'email'   => $resTercero[0]['dms_email'],
                'mobile'  => '+57'.$resTercero[0]['dms_cel']
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

        $this->login     = self::decryptCredential($PARAMS['pp_login'], base64_decode($_ENV['PP_KEY']));
        $this->secretkey = self::decryptCredential($PARAMS['pp_secret_key'], base64_decode($_ENV['PP_KEY']));
        $this->endpoint  = $_ENV['PP_ENDPOINT'];



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


                $Array =  array(
                    ":bpp_reference" => $Data['reference'],
                    ":bpp_cusemail"  => (isset($result['request']['payer']['email'])) ? $result['request']['payer']['email']: 'N/A',
                );

        

                $est = $result['status']['status'];
                $val = 0;

                if ( $est == 'PENDING' ) {
                    $val = 1;


                  $Array[":bpp_status"] = $val;
                  $Array[":bpp_status2"] = $est;

                } else {

                    $Array[":bpp_status"] = $val;
                    $Array[":bpp_status2"] = $est;
                }

                $add = "";

                
                if ( $resSelect[0]['bpp_status2'] != $est ) {
                    $val1 = 0;
                    $Array[":bpp_email"] = $val1;
                    $add = ", bpp_email = :bpp_email ";
                }

                if(isset($result['request']['payer'])){
                    $payerInfo = $result['request']['payer'];

                    $add .= ", bpp_payer_idnumber = :bpp_payer_idnumber";
                    $add .= ", bpp_payer_phone = :bpp_payer_phone";
                    $add .= ", bpp_payer_idType = :bpp_payer_idType";
                    $add .= ", bpp_payer_name = :bpp_payer_name";

                    $Array[':bpp_payer_idnumber'] = $payerInfo['document'] ?: 'N/A';    
                    $Array[':bpp_payer_phone'] = $payerInfo['mobile'] ?: 'N/A';
                    $Array[':bpp_payer_idType'] = $payerInfo['documentType'] ?: 'N/A';
                    $Array[':bpp_payer_name'] = isset($payerInfo['name']) ? "{$payerInfo['name']} {$payerInfo['surname']}" : 'N/A';
                }
                

                $ressUpdate = $this->pedeo->updateRow("UPDATE tbpp SET bpp_status = :bpp_status, bpp_status2 = :bpp_status2, bpp_cusemail = :bpp_cusemail ".$add." WHERE bpp_reference = :bpp_reference", $Array);

               
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

    // METODO QUE ESTAN POR FUERA DE LA AUTENTICACION

    // RECIBE CALLBACK DESDE LA PASARELA DE PAGOS CON INFORMACION DEL ESTADO
    // DEL PAGO
    public function signOff_post() {

  
        $Data = $this->post();

        $PARAMS =  $this->generic->getParams();

        $this->login     = self::decryptCredential($PARAMS['pp_login'], base64_decode($_ENV['PP_KEY']));
        $this->secretkey = self::decryptCredential($PARAMS['pp_secret_key'], base64_decode($_ENV['PP_KEY']));
        $this->endpoint  = $_ENV['PP_ENDPOINT'];

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

    // LISTAR LOS PAGOS REALIZADOS
    public function getPayment_get() {
        $Data = $this->get();
        if( !isset($Data['cardcode'])){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
                );
    
            return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
        
        }

        $sqlSelect = "SELECT * FROM tbpp where bpp_cardcode = :cardcode";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":cardcode" => $Data['cardcode']));

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

    // METODO PARA ENVIAR CORREOS
    public function sendMail_post(){
    
        $sqlSelect = "SELECT bpp_reference, bpp_cusemail from tbpp where bpp_email = 0 and bpp_cusemail is not null";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array());
        $respuesta = array();
        $client = new GuzzleHttp\Client();

        $PARAMS =  $this->generic->getParams();

            $info = [];
        try {
        if(isset($resSelect[0])){
            foreach ($resSelect as $key => $payment) {                  

                $service = $client->request("POST", "https://localhost/".$PARAMS['apirest']."/index.php/Payment/getSesionPayment", [
                    'verify' => false,
                    'headers' => [
                        'x-api-key' => 'c960a0f3bf871d7da2a8413ae78f7b5f'
                    ],
                    'auth' => [
                        'serpent',
                        'serpent'
                    ],
                    'form_params' => [
                    'reference' => $payment['bpp_reference'],
                    ]
                ]);

                $response = json_decode($service->getBody()->getContents(),true);
                
                    if(!$response['error']){
                        
                        $html = $this->getBody($response['data']);
                        // print_r($html);exit;
                        
                        $statusInfo = $this->getStatus($response['data']['status']['status']);
                        
                        $result = $this->emailsend->send2($payment['bpp_cusemail'],'',"Pago en estado ".$statusInfo['text']." Ref. [".$payment['bpp_reference']."]", $html);
                        // $result = $this->emailsend->send2("hernandezcda@gmail.com",'',"Solicitud de transferencia en estado ".$statusInfo['text']." Ref. [".$payment['bpp_reference']."]", $html);
                        // $result = $this->emailsend->send2("amimitolag@gmail.com",'',"Solicitud de transferencia en estado ".$statusInfo['text']." Ref. [".$payment['bpp_reference']."]", $html);
                        if(isset($result) AND !$result['error']){
                            $ressUpdate = $this->pedeo->updateRow("UPDATE tbpp SET bpp_email = 1 WHERE bpp_reference = :bpp_reference", array(":bpp_reference"=>$payment['bpp_reference']));

                        }
                    }
            }   
            $respuesta = array(
                'error' => false,
                'data' => [],
                'mensaje' => 'Correo enviado con exito'
            );
        }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => $e->getMessage()
            );
        }

        $this->response($respuesta);       

    }

    // METODO PARA VERIFICAR EL ESTADO PENDIENTE DE LOS PAGOS
    public function checkStatus_post() {


        $client = new GuzzleHttp\Client();

        $PARAMS =  $this->generic->getParams();


        $resData = $this->pedeo->queryTable("SELECT * FROM tbpp WHERE bpp_status2 = :bpp_status2 AND bpp_try < :bpp_try", array(':bpp_status2' => 'PENDING', ':bpp_try' => 7));

        if ( isset($resData[0]) ) {

            foreach ($resData as $key => $payment) {    
                
                $service = $client->request("POST", "https://localhost/".$PARAMS['apirest']."/index.php/Payment/getSesionPayment", [
                    'verify' => false,
                    'headers' => [
                        'x-api-key' => 'c960a0f3bf871d7da2a8413ae78f7b5f'
                    ],
                    'auth' => [
                        'serpent',
                        'serpent'
                    ],
                    'form_params' => [
                    'reference' => $payment['bpp_reference'],
                    ]
                ]);


                $this->pedeo->updateRow('UPDATE tbpp set bpp_try = bpp_try + 1 WHERE bpp_id = :bpp_id', array(':bpp_id' => $payment['bpp_id']));
            }

            $respuesta = array(

                'error' => false,
                'data' => [],
                'mensaje' => 'Proceso finalizado'
            );

        } else {

            $respuesta = array(

                'error' => false,
                'data' => [],
                'mensaje' => 'Sin datos para procesar'
            );
        }

        $this->response($respuesta);  
    }

    // METODOS LOCALES
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

    private function getBody($data) {

        $sqlSelect = "SELECT * from pgem where pge_id = 1";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if(!isset($resSelect[0])){
            $respuesta = array(
              'error' => true,
              'data' => [],
              'mensaje' => "Anexo no encontrado"
            );
    
            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
    
            return;
        }

        $company = $this->pedeo->queryTable("SELECT main_folder company, url_contacto FROM PARAMS", array());

        $imageUrl = 'https://joint.'.$company[0]['company'].'.com/'.$resSelect[0]['pge_logo'];
        $imageUrlBussiness = 'https://joint.'.$company[0]['company'].'.com/assets/img/logo_joint_blanco.png';

        // // Obtener el contenido de la imagen
        // $imageData = file_get_contents($imageUrl);

        // // Convertir la imagen a base64
        // $base64Image = base64_encode($imageData);

        // // Obtener el tipo MIME de la imagen
        // $imageType = pathinfo($imageUrl, PATHINFO_EXTENSION);
        $template = '<!doctype html>
        <html lang="es">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Hello, world!</title>
            
            <style>
                .container2 {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    border: 1px solid #ccc;
                    border-radius: 10px;
                    background-color: #f9f9f9;
                }
                #logo-container{
                    border-top-right-radius: 10px;
                    border-top-left-radius: 10px;
                    border-bottom-right-radius: 0;
                    border-bottom-left-radius: 0;
                }
        
                .title {
                    text-align: center;
                    margin-bottom: 20px;
                }
        
                .info {
                    margin-bottom: 10px;
                }

                .text-yellow {
                    color: #ffd900 !important;
                }
                .text-green {
                    color: #32a932 !important;
                }
                .text-red {
                    color: #ff5b57 !important;
                }
                .text-red-darker {
                    color: #cc4946 !important;
                }

                .text-orange {
                    color: #f59c1a !important;
                }

                .text-grey {
                    color: #b6c2c9 !important;
                }

                .text-grey-darker {
                    color: #929ba1 !important;
                }

                .text-blue {
                    color: #348fe2 !important;
                }

                .text-grey-lighter {
                    color: #c5ced4 !important;
                }

                .text-red-lighter {
                    color: #ff7c79 !important;
                }
                
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;        
                    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                    border-radius: 8px;
                    margin-top: 50px;
                    background-color: #f9f9f9;
                    border: 1px solid #ccc;
                    overflow: hidden;
                }

                .container2 {
                    max-width: 600px;
                    margin: 0 auto;    
                    margin-top: 50px;
                }

                .header {
                    text-align: center;
                    border-bottom: 2px solid #eeeeee;
                    padding-bottom: 10px;
                    margin-bottom: 20px;
                }

                .image-container {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 50px; /* Espacio entre los divs */
                    padding: 20px;
                    background: #828386;
                }

                .image-container > .small-container {
                    flex: 1;
                    max-width: 100px;
                }

                .image-container > .large-container {
                    flex: 2;
                    max-width: 60%;           
                }

                .image-container div{
                align-self:center;
                }
                
                .image-container div img {
                    width: 100%;
                    height: auto;
                    background-color: transparent;
                    filter: drop-shadow(2px 2px 2px rgba(255, 255, 255, 0.5));            
                }

                .status {
                    font-size: 18px;
                    margin-top: 10px;
                }

                .content, .section {
                    padding-left: 20px;
                    padding-right: 20px;
                    margin-bottom: 20px;
                }

                .section h2 {
                    color: #555555;
                    padding-bottom: 5px;
                    margin-bottom: 10px;
                    font-weight:300;
                }

                .section p {
                    margin: 5px 0;
                    color: #333333;
                }     
                
                .section strong, .section strong a{
                text-align:justify;
                }
                .header h2 span{
                font-weight:300;
                font-size:30px;
                } 

                .welcome{
                text-align:center;
                }
                
                </style>
        </head>
        <body>
                    <div class="container">
                        <!-- Imagen antes del header -->
                        <div class="image-container">                            
                            <div class="large-container">
                                <img src="{imglogo}" alt="Logo o imagen relacionada" style="filter: drop-shadow(2px 2px 2px rgba(255, 255, 255, 0.5));  ">
                            </div>    
                            <div class="small-container">
                                <img src="{imglogo_bussines}" alt="Logo o imagen relacionada" >
                            </div>         
                        </div>
                        
                        <div class="header">    
                            <h2>Hola <span>{name} {surname}</span></h2>       
                            <h4>La transacción en <strong>{business_name}</strong> por un valor de <strong>{total}</strong> se encuentra en estado: <h3 class="status"><span class="{class}" style="text-align:justify;">{status}</span></h3></h4>
                        </div>
                        <div class="content">
                            <p><strong>ID de Solicitud:</strong> {solId}</p>
                            <p><strong>Estado:</strong> {status}</p>
                            <p><strong>Fecha:</strong> {paydate}</p>
                        </div>
                        <div class="section">
                            <h2>Información del Pagador</h2>
                            <p><strong>Documento:</strong> {document}</p>
                            <p><strong>Tipo de Documento:</strong> {documentType}</p>
                            <p><strong>Nombre:</strong> {name}</p>
                            <p><strong>Apellido:</strong> {surname}</p>
                            <p><strong>Email:</strong> {email}</p>
                            <p><strong>Móvil:</strong> {mobile}</p>
                        </div>
                        <div class="section">
                            <h2>Información del Pago</h2>
                            <p><strong>Referencia:</strong> {paymentRef}</p>
                            <p><strong>Descripción:</strong> {description}</p>
                            <p><strong>Moneda:</strong> {currency}</p>
                            <p><strong>Total:</strong> {total}</p>
                            <p><strong>Nombre del Emisor:</strong> {issuerName}</p>
                            <p><strong>Método de Pago:</strong> {paymentMethod}</p>
                        </div>
                        
                        <div class="section">
                        <hr>
                        <p style="text-align:center; color: #555555;"><strong>{footerText1} <a href="{contactUrl}">contactenos</a> . </strong></p>
                        </div>                
                    </div>

                    <footer style=" max-width: 600px; margin: 0 auto; margin-top: 50px;text-align:center;">
                        {footerText2}
                    </footer>
        </body>
        </html>';

        $DECI_MALES =  $this->generic->getDecimals();

        $statusDate = new DateTime($data['status']['date']);

        $statusInfo = $this->getStatus(isset($data['status']['status'])?$data['status']['status']: '');
        $template = str_replace("{status}",isset($statusInfo['text'])?$statusInfo['text']:'',$template);
        $template = str_replace("{business_name}",isset($resSelect[0]['pge_small_name'])? $resSelect[0]['pge_small_name']:'',$template);
        $template = str_replace("{solId}",isset($data['requestId'])? $data['requestId']: '',$template);
        $template = str_replace("{status}",isset($statusInfo['text'])?$statusInfo['text']:'',$template);
        $template = str_replace("{class}",isset($statusInfo['colorClass'])?$statusInfo['colorClass']:'',$template);
        $template = str_replace("{paydate}",$statusDate->format('d-m-Y - g:i a'),$template);
        $template = str_replace("{document}",isset($data['request']['payer']['document'])? $data['request']['payer']['document']:'',$template);
        $template = str_replace("{documentType}",isset($data['request']['payer']['documentType'])? $data['request']['payer']['documentType']:'',$template);
        $template = str_replace("{name}",isset($data['request']['payer']['name'])? $data['request']['payer']['name']:'',$template);
        $template = str_replace("{surname}",isset($data['request']['payer']['surname'])? $data['request']['payer']['surname']:'',$template);
        $template = str_replace("{email}",isset($data['request']['payer']['email'])? $data['request']['payer']['email']:'',$template);
        $template = str_replace("{mobile}",isset($data['request']['payer']['mobile'])? $data['request']['payer']['mobile']:'',$template);
        $template = str_replace("{paymentRef}",isset($data['request']['payment']['reference'])? $data['request']['payment']['reference']: '',$template);
        $template = str_replace("{description}",isset($data['request']['payment']['description'])? $data['request']['payment']['description']: '',$template);
        $template = str_replace("{currency}",isset($data['request']['payment']['amount']['currency'])? $data['request']['payment']['amount']['currency']: '',$template);
        $template = str_replace("{total}",number_format(isset($data['request']['payment']['amount']['total'])? $data['request']['payment']['amount']['total']: 0, $DECI_MALES, ',', '.'),$template);
        $template = str_replace("{issuerName}",isset($data['payment'][0]['issuerName'] ) ? $data['payment'][0]['issuerName'] : 'N/A',$template);
        $template = str_replace("{paymentMethod}",isset($data['payment'][0]['paymentMethod']) ? $data['payment'][0]['paymentMethod'] : 'N/A',$template);
        $template = str_replace("{imglogo}",$imageUrl,$template);
        $template = str_replace("{imglogo_bussines}",$imageUrlBussiness,$template);
        $template = str_replace("{contactUrl}",trim($company[0]['url_contacto']),$template);
        $template = str_replace("{footerText1}",trim('Si no realizo esta operación o tiene dudas puede contactarnos por el siguiente enlace:'),$template);
        $template = str_replace("{footerText2}",trim('Este correo electrónico ha sido enviado desde una dirección exclusiva para notificaciones, la cual no puede recibir respuestas. Por favor, evite responder a este mensaje.'),$template);

        $template = utf8_encode($template);
        $template = utf8_decode($template);
        return $template;
    }

    private function getStatus($status){
        $arrayVar = [
            "PENDING" => ["colorClass" => "text-yellow", "text" => "PENDIENTE"],
            "APPROVED" => ["colorClass" => "text-green", "text" => "APROBADO"],
            "REJECTED" => ["colorClass" => "text-red", "text" => "RECHAZADO"],
            "FAILED" => ["colorClass" => "text-red-darker", "text" => "FALLIDO"],
            "DECLINED" => ["colorClass" => "text-orange", "text" => "DECLINADO"],
            "VOIDED" => ["colorClass" => "text-grey", "text" => "ANULADO"],
            "ERROR" => ["colorClass" => "text-grey-darker", "text" => "ERROR"],
            "PARTIAL" => ["colorClass" => "text-blue", "text" => "PARCIAL"],
            "ABANDONED" => ["colorClass" => "text-grey-lighter", "text" => "ABANDONADO"],
            "CANCELLED" => ["colorClass" => "text-red-lighter", "text" => "CANCELADO"],
        ];
        

        return $arrayVar[!empty($status) ? $status : 'ERROR'];
    }

    private function decryptCredential($encryptedCredential, $key) {
        $data = base64_decode($encryptedCredential);
        $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }
}


