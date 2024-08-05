<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PdfDocument extends REST_Controller {

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
		$this->load->library('DateFormat');
		$this->load->library('DocumentMarketing');
		$this->load->library('DocumentAccSeat');
		$this->load->library('DocumentPayment');
		$this->load->library('DocumentContract');
		$this->load->library('DocumentContract1');
		$this->load->library('DocumentCash');

	}
	
	public function PdfMarketing_post()
	{
		$Data = $this->post();
		$value = $this->documentmarketing->format($Data);
		return $this->response($value);
	}

	public function PdfAccSeat_post()
	{
		$Data = $this->post();
		$value = $this->documentaccseat->format($Data);
		return $this->response($value);
	}

	public function PdfPayments_post()
	{
		$Data = $this->post();
		$value = $this->documentpayment->format($Data);
		return $this->response($value);
	}

	public function PdfContract_post()
	{
		$Data = $this->post();

		$sql = "SELECT * FROM csn2 WHERE sn2_docentry = :sn2_docentry";
		$resSql = $this->pedeo->queryTable($sql,array(':sn2_docentry' => $Data['docentry']));
		if(isset($resSql[0])){
			$value = $this->documentcontract1->format($Data);
		}else {
			$value = $this->documentcontract->format($Data);
		}
		
		return $this->response($value);
	}

	public function PdfTermicaD_post()
	{
		$Data = $this->post();
		$value = $this->documentmarketing->formatTermicaDelivery($Data);
		return $this->response($value);
	}

	public function PdfTermicaV_post()
	{
		$Data = $this->post();
		$value = $this->documentmarketing->formatTermicaVenta($Data);
		return $this->response($value);
	}

	public function PdfCash_post()
	{
		$Data = $this->post();
		$value = $this->documentcash->formatCash($Data);
		return $this->response($value);
	}

	public function PdfLegalExpenses_post()
	{
		$Data = $this->post();
		$value = $this->documentmarketing->formatLegalExpenses($Data);
		return $this->response($value);
	}

}
