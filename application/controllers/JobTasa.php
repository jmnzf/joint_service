<?php
// TRABAJADOR JobTasa : SE ENCARGA DE COLOCAR LA TASA AUTOMATICA
// SEGUN EL DIA ANTERIOR
// BUSCA TODAS LAS TASAS Y LA INSERTA CON LA FECHA DE ACTUAL
defined('BASEPATH') OR exit('No direct script access allowed');
// require_once(APPPATH."/controllers/Lote.php");
require_once(APPPATH.'/controllers/Tasa.php');
// use Restserver\libraries\REST_Controller;


class JobTasa extends Tasa {

	private $pdo;
	private $controller;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    	$this->load->library('pedeo', [$this->pdo]);
		// $this->load->controller('Tasa');

	}

	public function setTasa_post(){

		// $data = $this->post();
		$curl = curl_init();

		curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://www.superfinanciera.gov.co/SuperfinancieraWebServiceTRM/TCRMServicesWebService/TCRMServicesWebService?wsdl',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS =>'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
		xmlns:act="http://action.trm.services.generic.action.superfinanciera.nexura.sc.com.co/">
		<soapenv:Header/>
		<soapenv:Body>
		<act:queryTCRM>
		</act:queryTCRM>
		</soapenv:Body>
		</soapenv:Envelope>',
		CURLOPT_HTTPHEADER => array(
			'Content-Type: text/xml'
		),
		));

		$response = curl_exec($curl);

		$xml = preg_replace("/(<\/?)(\w+):([^>]*>)/", '$1$2$3', $response);
		$xml = simplexml_load_string($xml);
		$json = json_encode($xml);
		$responseArray = json_decode($json,true);

		curl_close($curl);

		if(isset($responseArray) && !empty($responseArray)){

			foreach ($responseArray as $key => $value) {

				$data  = array(
					'tsa_eq' => 1,
					'tsa_curro' => $value['ns2queryTCRMResponse']['return']['unit'],
					'tsa_currd' => 'USD',
					'tsa_value' => $value['ns2queryTCRMResponse']['return']['value'],
					'tsa_createby' => 'system',
					'tsa_date' => date('Y-m-d'),
					'tsa_enabled' => 1
				);

				$_POST = $data;
				
				self::createTasa_post();

			}
		}
	}

	public function getMetodos_post(){

		// $controller = new lote();

		// $respuesta = $controller->validateDate('2022-11-01');

		$this->response($respuesta);
	}

	public function setTasaMasive($fecha){

		// $data = $this->post();
		$curl = curl_init();

		curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://www.superfinanciera.gov.co/SuperfinancieraWebServiceTRM/TCRMServicesWebService/TCRMServicesWebService?wsdl',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS =>'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:act="http://action.trm.services.generic.action.superfinanciera.nexura.sc.com.co/"> <soapenv:Header/> <soapenv:Body> <act:queryTCRM> <tcrmQueryAssociatedDate>'.$fecha.'</tcrmQueryAssociatedDate> </act:queryTCRM> </soapenv:Body> </soapenv:Envelope>',
		CURLOPT_HTTPHEADER => array('Content-Type: text/xml'),
		));

		$response = curl_exec($curl);

		$xml = preg_replace("/(<\/?)(\w+):([^>]*>)/", '$1$2$3', $response);
		$xml = simplexml_load_string($xml);
		$json = json_encode($xml);
		$responseArray = json_decode($json,true);

		curl_close($curl);

		if(isset($responseArray) && !empty($responseArray)){

			foreach ($responseArray as $key => $value) {

				$Data  = array(
					'tsa_eq' => 1,
					'tsa_curro' => $value['ns2queryTCRMResponse']['return']['unit'],
					'tsa_currd' => 'USD',
					'tsa_value' => $value['ns2queryTCRMResponse']['return']['value'],
					'tsa_createby' => 'manager',
					'tsa_date' => $fecha,
					'tsa_enabled' => 1
				);

				$sqlInsert = "INSERT INTO tasa(tsa_eq, tsa_curro, tsa_value, tsa_currd, tsa_date, tsa_createby,tsa_enabled)
				VALUES (:tsa_eq, :tsa_curro, :tsa_value, :tsa_currd, :tsa_date, :tsa_createby,:tsa_enabled)";


				$resInsert = $this->pedeo->insertRow($sqlInsert, array(
						':tsa_eq'    => isset($Data['tsa_eq']) ? $Data['tsa_eq'] : 0,
						':tsa_curro' => isset($Data['tsa_curro']) ? $Data['tsa_curro'] : NULL,
						':tsa_value' => isset($Data['tsa_value']) ? $Data['tsa_value'] : 0,
						':tsa_currd' => isset($Data['tsa_currd']) ? $Data['tsa_currd'] : NULL,
						':tsa_createby' => isset($Data['tsa_createby']) ? $Data['tsa_createby'] : NULL,
						':tsa_date'  => isset($Data['tsa_date']) ? $Data['tsa_date']:NULL,
						'tsa_enabled' => 1
				));
				
			}
		}
	}

	public function setTasaAnual_post() {

		$annos = [2023];
		$meses = [1,2,3,4,5,6,7,8,9,10,11,12];
		$fechas = [];

		foreach ($annos as $key => $ano) {

			foreach ($meses as $key => $mes) {

				array_push($fechas, self::obtenerFechasMes($ano, $mes)); 
				
			}
		}


		foreach ($fechas as $key => $fecha) {

			foreach ($fecha as $key => $val) {

				self::setTasaMasive($val);
				
			}
			
		}

		
		$respuesta = array(
			"error" => false,
			"data" => [],
			"mensaje" => "Proceso finalizado"
		);
		
		$this->response($respuesta);
	}

	private static function obtenerFechasMes($ano, $mes) {
		$fechas = array();
		
		// Obtener el número de días en el mes y año dados
		$numDias = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
		
		// Iterar sobre cada día del mes y crear la fecha correspondiente
		for ($dia = 1; $dia <= $numDias; $dia++) {
			$fecha = new DateTime("$ano-$mes-$dia");
			$fechas[] = $fecha->format('Y-m-d');
		}
		
		return $fechas;
	}
	
}
