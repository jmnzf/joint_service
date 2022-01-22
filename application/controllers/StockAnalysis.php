<?php
// FACTURA DE COMPRAS
defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class StockAnalysis extends REST_Controller {

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

  public function getStockAnalysis_post(){
    $Data = $this->post();
    if((!isset($Data['dvf_doctype']) or $Data['dvf_doctype'] == '' or $Data['dvf_doctype'] == null) or
      (!isset($Data['dvf_docdate']) or $Data['dvf_docdate'] == '' or $Data['dvf_docdate'] == null) or
      (!isset($Data['dvf_duedate']) or $Data['dvf_docdate'] == '' or $Data['dvf_docdate'] == null)){

      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }
		$options = array_keys($Data);

		$conditions = '';
		$tipo = $Data['dvf_doctype'];
		// LISTA DE TABLAS POR DOCTYPE
		$tables = array(
			'5' =>  array('table' =>'dvfv','prefix'=>'dvf','detailTable'=>'vfv1','detailPrefix'=>'fv1'),
			'2' =>  array('table' =>'dvov','prefix'=>'vov','detailTable'=>'vov1','detailPrefix'=>'ov1'),
			'12' =>  array('table' =>'dcpo','prefix'=>'cpo','detailTable'=>'cpo1','detailPrefix'=>'po1'),
			'15' =>  array('table' =>'dcfc','prefix'=>'cfc','detailTable'=>'cfc1','detailPrefix'=>'fc1')
		);
		$table = $tables[$tipo]['table'];
		$prefix = $tables[$tipo]['prefix'];
		$detailTable = $tables[$tipo]['detailTable'];
		$detailPrefix = $tables[$tipo]['detailPrefix'];

		$req = array('dvf_doctype','dvf_docdate','dvf_duedate');
	  $diff = array_diff($options,$req);

		$campos = array(
      ':dvf_doctype' => $Data['dvf_doctype'],
      ':dvf_docdate' => $Data['dvf_docdate'],
      ':dvf_duedate'=>$Data['dvf_duedate']
  	);
		$fechaI = new DateTime($Data['dvf_docdate']);
		$FFi = $fechaI->format('Y-m-d');
		$fechaF = new DateTime($Data['dvf_duedate']);
		$FFf = $fechaF->format('Y-m-d');
		// Agrega los datos que envia en el metodo
	    foreach ($diff as $key => $value) {
	      if($Data[$value]!='' and $Data[$value]!=null){
	          $conditions .='AND '.str_replace("dvf",$prefix,$value).' = :'.$value.' ';
	          $campos[':'.$value] = $Data[$value];
	        }
	      }

				// crea consulta dinamica con las tablas
        // $sqlSelect = "SELECT
        //               mdt_docname tipo_doc_name,
        //               mdt_doctype tipo_doc,
				// 							{$detailPrefix}_itemcode item_code,
				// 							{$detailPrefix}_itemname item_name,
        //               {$prefix}_docdate fecha_inicio,
        //               {$prefix}_docdate fecha_fin,
        //               {$prefix}_cardname cliente_name,
				// 							{$detailPrefix}_quantity cantidad,
				// 							sum({$detailPrefix}_price) price,
        //               sum({$prefix}_baseamnt) val_factura,
				// 							sum({$prefix}_taxtotal) val_impuesto,
        //               sum({$prefix}_doctotal) total_docums,
        //               mga_name
        //               from $table
        //               join {$detailTable} on {$prefix}_docentry = {$detailPrefix}_docentry
        //               join tbdi on {$detailPrefix}_itemcode = bdi_itemcode
        //               join dmdt on {$prefix}_doctype = mdt_doctype
        //               join dmar on {$detailPrefix}_itemcode = dma_item_code
        //               join dmga on mga_id = dma_group_code
        //               where {$prefix}_doctype = :dvf_doctype and {$prefix}_docdate >= :dvf_docdate and {$prefix}_duedate <= :dvf_duedate {$conditions}
        //               GROUP by {$prefix}_docdate, {$prefix}_docdate, {$prefix}_cardname, {$detailPrefix}_itemname,
				// 							 mdt_doctype, mdt_docname,{$detailPrefix}_itemcode, {$prefix}_baseamnt,{$detailPrefix}_quantity, mga_name";
											 // print_r($sqlSelect);

											 $sqlSelect = "SELECT
												mdt_docname tipo_doc_name,
												{$detailPrefix}_itemcode item_code,
												'{$FFi}' fecha_inicio,
												'{$FFf}' fecha_fin,
												{$detailPrefix}_itemname item_name,
												{$prefix}_cardname cliente_name,
												round((avg({$detailPrefix}_price)::numeric * sum({$detailPrefix}_quantity)),2) val_factura,
												sum({$detailPrefix}_quantity) cantidad,
												round(avg({$detailPrefix}_price)::numeric ,2) price,
												round((avg({$detailPrefix}_price)::numeric *round(avg({$detailPrefix}_vat)))/100) val_impuesto,
												round(sum({$detailPrefix}_linetotal),2) total_docums,
												mga_name
												from {$table}
												join {$detailTable} on {$prefix}_docentry = {$detailPrefix}_docentry
												join dmdt on {$prefix}_doctype = mdt_doctype
												join dmar on {$detailPrefix}_itemcode = dma_item_code
												join dmga on mga_id = dma_group_code
												where {$prefix}_doctype = :dvf_doctype and {$prefix}_docdate >= :dvf_docdate and {$prefix}_duedate <= :dvf_duedate {$conditions}
												group by {$detailPrefix}_itemname, mga_name,mdt_docname,mdt_doctype,{$detailPrefix}_itemcode,{$prefix}_cardname";
        $resSelect = $this->pedeo->queryTable($sqlSelect,$campos);

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


}