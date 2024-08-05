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

	public function CodeGenAprovals_get()
	{
		$respuesta = array(
			'error' => true,
			'data' => [],
			'mensaje' => 'No se encontraron datos en la busqueda'
		);
		$code = "";
		//
		$sql = "SELECT 
				d.pap_origen ,
				d.pap_docentry,
				d2.mdt_docname ,
				p.pgu_code_user ,
				p.pgu_email 
			from dpap d 
			inner join tmau t on d.pap_origen = t.mau_doctype 
			inner join dmdt d2 on d.pap_origen = d2.mdt_doctype 
			inner join pgus p on p.pgu_id_usuario::text in (t.mau_approvers) ";

		$resSql = $this->pedeo->queryTable($sql);
		// print_r($resSql[0]);exit;die;
		if ( isset($resSql[0]) && !empty($resSql[0]) ){
			//INICIO DE CODIGO PARA INSERTAR CODIGO DE AUTORIZACION
			foreach ($resSql as $key => $value) {
				$code = $this->code();
				// print_r($code);exit;
				//INSERT
				$insert = "INSERT INTO trne(rne_doctype,rne_docentry,rne_code,rne_email,created_by,created_at)
				VALUES (:rne_doctype,:rne_docentry,:rne_code,:rne_email,:created_by,:created_at)";

				$resInsert = $this->pedeo->insertRow($insert,array(
					':rne_doctype' => $value["pap_origen"],
					':rne_docentry' => $value["pap_docentry"],
					':rne_code' => $code,
					':rne_email' => $value["pgu_email"],
					':created_by' => "system",
					':created_at' => Date("Y-m-d h:m:s")
					
				));
				// print_r($resInsert);exit;
				if (is_numeric($resInsert) && $resInsert > 0){
					$respuesta = array(
						'error' => false,
						'data' => $code,
						'mensaje' => 'Codigo de autorizacion generado con exito'
					);
				}else {
					$respuesta = array(
						'error' => true,
						'data' => $resInsert,
						'mensaje' => 'No se puedo insertar el registro'
					);
				}
			}
		}

		$this->response($respuesta);
	}

	public function Notification_post()
	{
		$respuesta = array(
			'error' => true,
			'data' => [],
			'mensaje' => 'No se encontraron registros para envio'
		);

		$asunto = "";
		$mensaje = "";
		$detalle_html = "";
		$sql = "SELECT 
				t.*, 
				d.mdt_docname ,
				d2.pap_docdate ,
				d2.pap_doctotal ,
				d2.pap_createby ,
				concat(p.pgu_name_user,' ',p.pgu_lname_user) as name
			from trne t 
			inner join dmdt d on t.rne_doctype = d.mdt_doctype 
			inner join dpap d2 on t.rne_doctype = d2.pap_origen 
			inner join pgus p on d2.pap_createby  = p.pgu_code_user 
			where t.rne_status = 0";
		$resSql = $this->pedeo->queryTable($sql);

		if (isset($resSql[0]) && !empty($resSql[0])){
			foreach ($resSql as $key => $value) {
				$detalle = "select * from pap1 p where p.ap1_docentry = ".$value["rne_docentry"];
				$resDetalle = $this->pedeo->queryTable($detalle);

				if(isset($resDetalle[0]) && !empty($resDetalle[0])){
					foreach ($resDetalle as $key => $detail) {
						$detalle_html .='<tr>
						<td>'.$detail["ap1_itemcode"].'</td>
						<td>'.$detail["ap1_itemname"].' A</td>
						<td>'.$detail["ap1_quantity"].'</td>
					</tr>';
					}
				}
				//
				$asunto = 'Solicitud de aprobacion - '.$value["mdt_docname"];
				$mensaje = '<!DOCTYPE html>
				<html lang="es">
				
				<head>
					<meta charset="UTF-8">
					<meta name="viewport" content="width=device-width, initial-scale=1.0">
					<title>Documento de Aprobación</title>
					<style>
						body {
							font-family: Arial, sans-serif;
							margin: 0;
							padding: 0;
							background-color: #f9f9f9;
						}
				
						.container {
							width: 80%;
							margin: 20px auto;
							background-color: #fff;
							padding: 20px;
							border-radius: 8px;
							box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
						}
				
						h2 {
							color: #333;
							text-align: center;
						}
				
						table {
							width: 100%;
							border-collapse: collapse;
							margin-bottom: 20px;
						}
				
						th,
						td {
							border: 1px solid #ddd;
							padding: 10px;
							text-align: left;
						}
				
						th {
							background-color: #f2f2f2;
						}
				
						.btn-group {
							display: flex;
							justify-content: center;
							margin-top: 20px;
						}
				
						.btn {
							padding: 10px 20px;
							margin: 0 10px;
							border: none;
							border-radius: 5px;
							cursor: pointer;
							text-decoration: none; /* Evitar subrayado */
							color: white; /* Color del texto negro */
							display: inline-block;
							text-align: center;
							background-color: #ccc; /* Color de fondo gris por defecto */
						}
				
						.btn-approve {
							background-color: green;
						}
				
						.btn-reject {
							background-color: red;
						}
				
						/* Estilos para los enlaces */
						a {
							text-decoration: none; /* Evitar subrayado */
							color: inherit; /* Heredar el color del texto */
						}
					</style>
				</head>
				
				<body>
					<div class="container">
						<h2>Solicitud de Aprobación</h2>
						<p>El usuario '.$value["name"].', solicita la aprobación del siguiente documento:</p>
						<ul>
							<li>Documento: '.$value["mdt_docname"].'</li>
							<li>Total: $'.number_format($value["pap_doctotal"]).'</li>
						</ul>
				
						<table>
							<thead>
								<tr>
									<th>Código</th>
									<th>Descripción</th>
									<th>Cantidad</th>
								</tr>
							</thead>
							<tbody>
							'.$detalle_html.'
							</tbody>
						</table>
				
						<div class="btn-group">
							<a href="https://joint.jointerp.com/?c=Aprob&a=Index&res=1&token='.$value["rne_code"].'" class="btn btn-approve">APROBAR</a>
							<a href="https://joint.jointerp.com/?c=Rechaz&a=Index&res=0&token='.$value["rne_code"].'" class="btn btn-reject">RECHAZAR</a>
						</div>
				
						<p>No responder a este correo.</p>
					</div>
				</body>
				
				</html>
				';

				// print_r($mensaje);exit;
				if($this->emailsend->send($value["rne_email"],"",$asunto,$mensaje)){
					
				};
			}

			$respuesta = array(
				'error' => false,
				'data' => [],
				'mensaje' => 'Correos enviados con exito'
			);

			$this->response($respuesta);
		}

		// print_r($this->emailsend->send($resSql[0]["rne_email"]));exit;
	}

	private function Code()
	{
		$longitud = 20; // Longitud del código
		$caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$codigo_aleatorio = '';
		$longitud_caracteres = strlen($caracteres);

		for ($i = 0; $i < $longitud; $i++) {
			$indice_aleatorio = mt_rand(0, $longitud_caracteres - 1);
			$codigo_aleatorio .= $caracteres[$indice_aleatorio];
		}

		return $codigo_aleatorio;
	}
}

// <p>Estimado(a) ".strtolower($type)." ".$cardname."</p>
// 					<p>&nbsp;</p>
// 					<p>Se adjunta documento de ".$document." # ".$docnum.", por el valor de  ".$total."</p>
// $type,$cardname,$document,$docnum,$total
// $value['data']['type'],$value['data']['cardname'],$resSql[0]['mdt_docname'],$value['data']['docnum'],$value['data']['total']


