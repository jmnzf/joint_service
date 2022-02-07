<?php
// FACTURA DE COMPRAS
defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Analysis extends REST_Controller {

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

  public function getAnalysis_post(){
    $Data = $this->post();
    if((!isset($Data['dvf_doctype']) or $Data['dvf_doctype'] == '' or $Data['dvf_doctype'] == null) or
      (!isset($Data['dvf_docdate']) or $Data['dvf_docdate'] == '' or $Data['dvf_docdate'] == null) or
      // (!isset($Data['date_filter']) or $Data['date_filter'] == '' or $Data['date_filter'] == null) or
      (!isset($Data['dvf_duedate']) or $Data['dvf_docdate'] == '' or $Data['dvf_docdate'] == null)){

      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }

    $options = array_keys($Data);

		//LISTA DE TABLAS POR DOCTYPE
    $table = '';
    $prefix ='';


		$tables = array(
			'5' =>  array('table' =>'dvfv','prefix'=>'dvf','detailTable'=>'vfv1','detailPrefix'=>'fv1'),
			'2' =>  array('table' =>'dvov','prefix'=>'vov','detailTable'=>'vov1','detailPrefix'=>'ov1'),
			'6' =>  array('table' =>'dvnc','prefix'=>'vnc','detailTable'=>'vnc1','detailPrefix'=>'nc1'),
			'7' =>  array('table' =>'dvnd','prefix'=>'vnd','detailTable'=>'vnd1','detailPrefix'=>'nd1'),
			'12' =>  array('table' =>'dcpo','prefix'=>'cpo','detailTable'=>'cpo1','detailPrefix'=>'po1'),
			'15' =>  array('table' =>'dcfc','prefix'=>'cfc','detailTable'=>'cfc1','detailPrefix'=>'fc1'),
			'16' =>  array('table' =>'dcnc','prefix'=>'cnc','detailTable'=>'cnc1','detailPrefix'=>'nc1'),
			'17' =>  array('table' =>'dcnd','prefix'=>'cnd','detailTable'=>'cnd1','detailPrefix'=>'nd1')
		);


    $tipo = ($Data['dvf_doctype'] > 0)? $Data['dvf_doctype'] : 5;

		$table = $tables[$tipo]['table'];
		$prefix = $tables[$tipo]['prefix'];
    $detailTable =  $tables[$tipo]['detailTable'];
		$detailPrefix = $tables[$tipo]['detailPrefix'];

    $conditions = '';
    $campos = array(
      ':dvf_docdate' => $Data['dvf_docdate'],
      ':dvf_duedate'=>$Data['dvf_duedate']
  );

  $req = array('dvf_doctype','dvf_docdate','dvf_duedate','date_filter');
  $diff = array_diff($options,$req);

    foreach ($diff as $key => $value) {
      if($Data[$value]!='' and $Data[$value]!=null){
          $conditions .='AND '.str_replace("dvf",$prefix,$value).' = :'.$value.' ';
          $campos[':'.$value] = $Data[$value];
      }
    }
		$conditions = str_replace("AND ".$prefix."_currency = :".$prefix."_currency","",$conditions);
		$conditions = str_replace("AND ".$prefix."_currency = :dvf_currency","",$conditions);
    $sqlSelect = " ";
    $cardcode = (isset( $Data['dvf_cardcode']) and $Data['dvf_cardcode'] !=null) ?  true: false;
    switch ($Data['dvf_doctype']) {
      case '-1':
        $sqlSelect = $this->generalQuery($tables,['15','16','17'],$cardcode,-1) ;
      break;
      case '0':
        $sqlSelect = $this->generalQuery($tables,['5','6','7'],$cardcode,0);
        break;

      default:
          $sqlSelect = "SELECT
          mdt_docname tipo_doc_name,
          {$prefix}_cardcode cliente,
          mgs_name,
          {$prefix}_docnum docnum,
          {$prefix}_cardname cliente_name,
          concat(dmd_adress,' ',dmd_city) Direccion,
          bdc_clasify ,
          bdc_concept,
          to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_inicio,
          to_char(min({$prefix}_duedate),'DD-MM-YYYY') fecha_fin,
          to_char(min({$prefix}_duedev),'DD-MM-YYYY') fecha_doc,
          to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_cont,
          sum({$detailPrefix}_quantity) cant_docs,
          concat({CURR},round(sum(({$prefix}_baseamnt) / {USD}),2)) val_factura,
          concat({CURR},round(sum(({$prefix}_taxtotal) / {USD}),2)) val_impuesto,
          concat({CURR},round(sum(({$prefix}_doctotal) / {USD}),2)) total_docums,
          round(avg(tsa_value),2) tasa,
          (SELECT {$prefix}_docnum FROM {$table} WHERE {$prefix}_docentry  = {$prefix}_baseentry AND {$prefix}_doctype  = {$prefix}_basetype) doc_afectado
        from
        {$table}
        full join dmsn on {$prefix}_cardcode  = dms_card_code
        full join dmgs on dms_rtype = mgs_id
        join {$detailTable} on {$prefix}_docentry = {$detailPrefix}_docentry
        full join dmdt on {$prefix}_doctype = mdt_doctype
        full join tbdc  on dms_classtype = bdc_clasify
        left join dmsd on {$prefix}_cardcode = dmd_card_code AND dmd_ppal = 1
        full join tasa on {$prefix}_currency = tasa.tsa_curro and {$prefix}_docdate = tsa_date
        where ({$prefix}_{$Data['date_filter']} BETWEEN :dvf_docdate and  :dvf_duedate) ".$conditions."
        GROUP BY {$prefix}_cardcode, mgs_name, {$prefix}_cardname,{$prefix}_docnum, mdt_docname, bdc_clasify, bdc_concept,dmd_adress, dmd_city,{$prefix}_baseentry,{$prefix}_basetype";
        break;
    }

				if( isset( $Data['dvf_currency'] ) && $Data['dvf_currency'] == 1 ){
					$sqlSelect =	str_replace("{USD}","tsa_value",$sqlSelect);
					$sqlSelect =	str_replace("{CURR}","'USD '",$sqlSelect);
				}else{
					$sqlSelect =	str_replace("{USD}",1,$sqlSelect);
					$sqlSelect =	str_replace("{CURR}","'BS '",$sqlSelect);
				}

				unset($campos[':'.$prefix.'_currency']);
				unset($campos[':dvf_currency']);

        $resSelect = $this->pedeo->queryTable($sqlSelect, $campos);


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
  // METODO PARA OBTENER LOS DOCUMENTOS DE FACTURA, NOTA DEBITO, NOTA CREDITO
  private function generalQuery($tables,$sets,$cardcode,$type){
    $all = "";
    $card = "";

    foreach($sets as $key => $value){
    $table = $tables[$value]['table'];
		$prefix = $tables[$value]['prefix'];
    $detailTable =  $tables[$value]['detailTable'];
		$detailPrefix = $tables[$value]['detailPrefix'];
    if ($cardcode) {
      $card = "AND {$prefix}_cardcode = :dvf_cardcode";
    }

    $neg = 1;

    if($tables[$value]['table'] == 'dvnc' or $tables[$value]['table'] == 'dcnc'){
			$neg = -1;
		}

    $origin = '';
		$originPre = '';

    if($type == 0){
			$origin = 'dvfv';
			$originPre = 'dvf';
		}else if($type == -1){
			$origin = 'dcfc';
			$originPre = 'cfc';
		}

      $all .= "SELECT 
      mdt_docname tipo_doc_name,
      {$prefix}_cardcode cliente,
      mgs_name,
      {$prefix}_docnum docnum,
      {$prefix}_cardname cliente_name,
      concat(dmd_adress,' ',dmd_city) Direccion,
      bdc_clasify ,
      bdc_concept,
      to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_inicio,
      to_char(min({$prefix}_duedate),'DD-MM-YYYY') fecha_fin,
      to_char(min({$prefix}_duedev),'DD-MM-YYYY') fecha_doc,
      to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_cont,
      sum({$detailPrefix}_quantity) cant_docs,
      concat({CURR},round(sum(({$prefix}_baseamnt) / {USD}),2) * {$neg}) val_factura,
      concat({CURR},round(sum(({$prefix}_taxtotal) / {USD}),2) * {$neg}) val_impuesto,
      concat({CURR},round(sum(({$prefix}_doctotal) / {USD}),2) * {$neg})  total_docums,
      round(avg(tsa_value),2) tasa,
		  (SELECT {$originPre}_docnum FROM {$origin} WHERE {$originPre}_docentry  = {$prefix}_baseentry AND {$originPre}_doctype  = {$prefix}_basetype) doc_afectado
      from
      {$table}
      full join dmsn on {$prefix}_cardcode = dms_card_code
      full join dmgs on dms_rtype = mgs_id
      join {$detailTable} on {$prefix}_docentry = {$detailPrefix}_docentry
      full join dmdt on {$prefix}_doctype = mdt_doctype
      full join tbdc on dms_classtype = bdc_clasify
      left join dmsd on {$prefix}_cardcode = dmd_card_code AND dmd_ppal = 1
      full join tasa on {$prefix}_currency = tasa.tsa_curro and {$prefix}_docdate = tsa_date
      where ({$prefix}_docdate BETWEEN :dvf_docdate and :dvf_duedate) {$card}
      GROUP BY {$prefix}_cardcode, mgs_name, {$prefix}_cardname,{$prefix}_docnum,
       mdt_docname, bdc_clasify, bdc_concept,dmd_adress, dmd_city,{$prefix}_baseentry,{$prefix}_basetype
      UNION ALL
      ";
    }
    $all = substr($all, 0, -18);

    return $all;

  }


}
