<?php
//
defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;
//
class Correo extends REST_Controller {
	//
	private $pdo;
	//
	public function __construct(){
		//
		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");
		//
		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
        $this->load->library('EmailSend');
		$this->load->library('DocumentMarketing');
		$this->load->library('DocumentAccSeat');
		$this->load->library('DocumentPayment');
		$this->load->library('DocumentContract');
		// $this->load->library('DocumentContract1');
	}
	//
	public function send_post()
	{
		//GENERAR PDF POR TIPO DE DOCUMENTO
		$subject = "";
		$value = "";
        $request = $this->post();
		// print_r($this->bodyHtml($request,$request['cde_text']));exit;die;
		//CONSULTA PARA EL SUBJEC
		$sql = "SELECT mdt_docname FROM dmdt WHERE mdt_doctype = :mdt_doctype";
		$resSql = $this->pedeo->queryTable($sql,array(':mdt_doctype' => $request['doctype']));
		if($request['type'] == 'M'){
			$value = $this->documentmarketing->format($request,"F");
			$subject = $resSql[0]['mdt_docname']." - #".$value['data']['docnum'];
		}else if(($request['type'] == 'P')){
			$value = $this->documentpayment->format($request,"F");
			$subject = $resSql[0]['mdt_docname']." - #".$value['data']['docnum'];
		}else if(($request['type'] == 'A')){
			$value = $this->documentaccseat->format($request,"F");
			$subject = $resSql[0]['mdt_docname']." - #".$value['data']['docnum'];
		}else if(($request['type'] == 'S')){
			$value = $this->documentcontract1->format($request,"F");
			$subject = $resSql[0]['mdt_docname']." - #".$value['data']['docnum'];
		}else if(($request['type'] == 'C')){
			$value = $this->documentcontract1->format($request,"F");
			$subject = $resSql[0]['mdt_docname']." - #".$value['data']['docnum'];
		}
		// print_r($value);exit;
		//CONVERTIR EN ARRAY LOS CORREOS Y SEPARARLOS POR ;$request['cde_text']
		$email = explode(";",$request['cde_email']);
		$array = array_map('trim',$email);
		//ENVIAR CORREOS
		$result = $this->emailsend->send($array,$value['file'],$subject,$this->bodyHtml($request,$request['cde_text']));
		// var_dump($result);exit;die;
		if($result){
			//INSERTAR EN LA BASE DE DATOS
			$insert = "INSERT INTO hcde (cde_docentry,cde_doctype,cde_createby,cde_date,cde_time,cde_email,cde_text) 
			VALUES (:cde_docentry,:cde_doctype,:cde_createby,:cde_date,:cde_time,:cde_email,:cde_text)";

			$resInsert = $this->pedeo->insertRow($insert,array(
				':cde_docentry' => $request['docentry'],
				':cde_doctype' => $request['doctype'],
				':cde_createby' => $request['cde_createby'],
				':cde_date' => date('Y-m-d'),
				':cde_time' => date('H:i:s'),
				':cde_email' => $request['cde_email'],
				':cde_text' => $request['cde_text']
			));

			if(is_numeric($resInsert) && $resInsert > 0){
				//
			}else{

			}
		}
		//RESPUESTA DEL ENVIO DEL CORREO
        $this->response($result);
		// //ELIMINAR PDF GENERADO
		// if (file_exists($value)) {
		// 	if (unlink($value)) {
		// 		echo 'El archivo ha sido eliminado correctamente.';
		// 	} else {
		// 		echo 'No se pudo eliminar el archivo.';
		// 	}
		// } else {
		// 	echo 'El archivo no existe.';
		// }
    }

	private function bodyHtml($Data,$text){
		$sqlEmpresa = "SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
		pge_cou_soc, pge_id_soc AS pge_id_type , pge_web_site, pge_logo,
		CONCAT(pge_cel) AS pge_phone1, pge_branch, pge_mail,
		pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type,pge_id_soc,pge_phone2,pge_page_social
		FROM pgem WHERE pge_id = :pge_id";
		$empresa = $this->pedeo->queryTable($sqlEmpresa, array(':pge_id' => $Data['business']));
		//
		$company = $this->pedeo->queryTable("SELECT main_folder company FROM PARAMS",array());
		$html = "
			<html>
				<body>
					<p><img src='https://zupordesk.com/".$company[0]['company']."/".$empresa[0]['pge_logo']."' width='10%'/></p>
					<p>".$text."</p>
					<p>Cordialmente,</p>
					<p>".$company[0]['company']." - ".date('Y')."</p>
					<p><img src='https://zupordesk.com/".$company[0]['company']."/".$empresa[0]['pge_logo']."' style='width: 50%' /></p>
				</body>
			</html>
		";
		return $html;
	}
}

// <p>Estimado(a) ".strtolower($type)." ".$cardname."</p>
// 					<p>&nbsp;</p>
// 					<p>Se adjunta documento de ".$document." # ".$docnum.", por el valor de  ".$total."</p>
// $type,$cardname,$document,$docnum,$total
// $value['data']['type'],$value['data']['cardname'],$resSql[0]['mdt_docname'],$value['data']['docnum'],$value['data']['total']
