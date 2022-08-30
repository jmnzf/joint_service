<?php
// NOTIFICACIONES PUSH
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class PushNotification extends REST_Controller {

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

  // ENVIAR NOTIFICACION PUSH
	public function sendPush_post(){


    $content = array(
           "en" => 'Hola mundo',

           );

       $fields = array(
           'app_id' => "8999f0b9-1df0-4f9d-b09f-9ed81850936f",
           'include_external_user_ids' => array("1chernandez"),
           'channel_for_external_user_ids' => 'push',
           'data' => array("data" => "esto es una prueba"),
           'contents' => $content
       );

       $fields = json_encode($fields);


       $ch = curl_init();
       curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
       curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
                                                  'Authorization: Basic NTlhMjY4Y2MtNTBjNC00YTUxLWFkOWUtMjI4MWRjZmY4YmJj'));
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
       curl_setopt($ch, CURLOPT_HEADER, FALSE);
       curl_setopt($ch, CURLOPT_POST, TRUE);
       curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
       curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

       $response = curl_exec($ch);
       curl_close($ch);





     $return["allresponses"] = $response;
     $return = json_encode( $return);



    $this->response($return);


	}






}
