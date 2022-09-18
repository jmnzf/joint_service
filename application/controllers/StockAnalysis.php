<?php
//ANALISIS DE VENTAS - TIPO INFORME ANALISIS DE ARTICULOS
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
		$this->load->library('generic');

	}

  public function getStockAnalysis_post(){
		$MONEDA_LOCAL = $this->generic->getLocalCurrency();

    $Data = $this->post();
    if((!isset($Data['dvf_doctype']) or $Data['dvf_doctype'] == '' or $Data['dvf_doctype'] == null) or
      (!isset($Data['dvf_docdate']) or $Data['dvf_docdate'] == '' or $Data['dvf_docdate'] == null) or
      (!isset($Data['date_filter']) or $Data['date_filter'] == '' or $Data['date_filter'] == null) or
      (!isset($Data['dvf_duedate']) or $Data['dvf_docdate'] == '' or $Data['dvf_docdate'] == null)){

      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }

	$main_currency = $Data['main_currency'];

    $original  = $Data['original'];
    unset($Data['main_currency']);
    unset($Data['original']);

		$options = array_keys($Data);

		$conditions = '';
		$tipo = $Data['dvf_doctype'];
		// LISTA DE TABLAS POR DOCTYPE
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
		$detailTable = $tables[$tipo]['detailTable'];
		$detailPrefix = $tables[$tipo]['detailPrefix'];

		$req = array('dvf_doctype','dvf_docdate','dvf_duedate','date_filter');
	  $diff = array_diff($options,$req);

		$campos = array(
      ':dvf_docdate' => $Data['dvf_docdate'],
      ':dvf_duedate'=>$Data['dvf_duedate'],
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




						$conditions = str_replace("AND ".$prefix."_currency = :".$prefix."_currency","",$conditions);
						$conditions = str_replace("AND ".$prefix."_currency = :dvf_currency","",$conditions);
						$conditions = str_replace("AND symbol = :symbol","",$conditions);
						$sqlSelect = " ";
						$cardcode = (isset( $Data['dvf_cardcode']) and $Data['dvf_cardcode'] !=null) ?  true: false;

						if($tables[$tipo]['table'] == 'dvnc' or $tables[$tipo]['table'] == 'dcnc'){
							$neg = -1;
						}else{
							$neg = 1;
						}

						$unidad = ",'' unidad  ";
						if($tables[$tipo]['table'] == 'dvnc' OR
						$tables[$tipo]['table'] == 'dvnd' OR
						$tables[$tipo]['table'] == 'dcnc' OR
						$tables[$tipo]['table'] == 'dcnd'
						){
						$unidad = ",
						{$detailPrefix}_uom unidad";
						}
						$cardType = 1;
						if($tables[$tipo]['table'] == 'dvnc' or
						 $tables[$tipo]['table'] =='dvnd' OR $tables[$tipo]['table'] == 'dvfv'){
							$cardType = 1;

						}else{
							$cardType = 2;
						}
						switch ($Data['dvf_doctype']) {
							case '-1':
							  $sqlSelect = $this->generalQuery($tables,['15','16','17'],$cardcode,-1,$original) ;
							break;
							case '0':
							  $sqlSelect = $this->generalQuery($tables,['5','6','7'],$cardcode,0,$original);
							  break;

							default:

							$sqlSelect = "SELECT distinct
									mdt_docname tipo_doc_name,
									{$detailPrefix}_itemcode item_code,
									to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_inicio,
									to_char(min({$prefix}_duedate),'DD-MM-YYYY') fecha_fin,
									to_char(min({$prefix}_duedev),'DD-MM-YYYY') fecha_doc,
									to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_cont,
									to_char(min({$prefix}_createat),'DD-MM-YYYY') created,
									{$prefix}_docnum docnum,
									{$detailPrefix}_itemname item_name,
									{$prefix}_cardname cliente_name,
									concat({CURR}, get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate, avg({$detailPrefix}_linetotal * {$neg} ), {MAIN} ) )   val_factura,
									concat({CURR}, get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate, avg({$detailPrefix}_price::numeric * {$neg} ), {MAIN} ) )  price,
									concat({CURR}, get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate, avg({$detailPrefix}_vatsum * {$neg}), {MAIN} ) )   val_impuesto,
									concat({CURR}, get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate, (avg({$detailPrefix}_linetotal) + avg({$detailPrefix}_vatsum) * {$neg} - coalesce(get_retperline({$prefix}_doctype,{$prefix}_docentry,{$detailPrefix}_linenum),0) ),{MAIN} ) )  total_docums,
									mga_name,
									get_tax_currency({CURRD}, {$prefix}_docdate) tasa,
									{$prefix}_createby createby,
									".(($table =="dvnc")?" CASE when({$detailPrefix}_exc_inv =  0 ) then 0 else  (sum({$detailPrefix}_quantity) * {$neg}) end cantidad,":(($table == 'dvnd') ? "0 cantidad," : "sum({$detailPrefix}_quantity) cantidad,") )."
									(SELECT concat(pgu_name_user,' ',pgu_lname_user) from pgus where pgu_code_user  = {$prefix}_createby) us_name,
									(SELECT {$prefix}_docnum FROM {$table} WHERE {$prefix}_docentry  = {$prefix}_baseentry AND {$prefix}_doctype  = {$prefix}_basetype) doc_afectado,
									{$detailPrefix}_uom  unidad
									".(($table =="dcfc")? ",get_retnameperline(cfc_doctype,cfc_docentry,fc1_linenum)": ",'N/A'" )." rt_name,
									".(($table =="dcfc")? "case when get_dynamic_conversion({CURR},cfc_currency,cfc_docdate,get_retperline(cfc_doctype,cfc_docentry,fc1_linenum),{CURR}) > 0 then  concat({CURR},get_dynamic_conversion({CURR},{$prefix}_currency,{$prefix}_docdate,get_retperline(cfc_doctype,cfc_docentry,fc1_linenum),{MAIN})) else '' end ": "concat({CURR},round(0,2))" )." rt_total
									from {$table}
									join {$detailTable} on {$prefix}_docentry = {$detailPrefix}_docentry
									join dmdt on {$prefix}_doctype = mdt_doctype
									join dmar on {$detailPrefix}_itemcode = dma_item_code
									join dmsn on {$prefix}_cardcode  = dms_card_code  AND dms_card_type = '{$cardType}'
									join dmga on mga_id = dma_group_code
									full join tasa on {$prefix}_currency = tasa.tsa_curro and {$prefix}_docdate = tsa_date
									".(($table =='dcfc')? "left join fcrt on crt_baseentry = {$detailPrefix}_docentry and crt_linenum = {$detailPrefix}_linenum
									left join dmrt on mrt_id = crt_type" : "")."
									where ({$prefix}_{$Data['date_filter']} BETWEEN :dvf_docdate and  :dvf_duedate) {$conditions}
									group by {$prefix}_docdate,{$detailPrefix}_itemname,{$prefix}_currency,".(($table =="dcfc")? "cfc_doctype,cfc_docentry,fc1_linenum,":"")."mga_name,mdt_docname,mdt_doctype,{$detailPrefix}_itemcode,{$prefix}_cardname, tsa_value,{$prefix}_docnum,{$detailPrefix}_uom,{$prefix}_createby".(($table =="dvnc" )?",{$detailPrefix}_exc_inv": "");
									break;
						}





				if( isset( $Data['dvf_currency'] ) &&  isset($Data['symbol'])){
					$sqlSelect =	str_replace("{USD}","tsa_value",$sqlSelect);
					$sqlSelect =	str_replace("{CURR}","'".$Data['symbol']."   '",$sqlSelect);
					$sqlSelect =	str_replace("{CURRD}","'".$Data['symbol']."'",$sqlSelect);
					$sqlSelect =	str_replace("{MAIN}","'".$main_currency."'",$sqlSelect);
				}else{
					$sqlSelect =	str_replace("{USD}",1,$sqlSelect);
					$sqlSelect =	str_replace("{CURR}","'".$MONEDA_LOCAL."'",$sqlSelect);
					$sqlSelect =	str_replace("{CURRD}","'".$MONEDA_LOCAL."'",$sqlSelect);
					$sqlSelect =	str_replace("{MAIN}","'".$main_currency."'",$sqlSelect);

				}
				unset($campos[':'.$prefix.'_currency']);
				unset($campos[':dvf_currency']);
				unset($campos[':symbol']);

				// print_r($sqlSelect);exit;

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


    // METODO PARA OBTENER LOS DOCUMENTOS DE FACTURA, NOTA DEBITO, NOTA CREDITO
	private function generalQuery($tables,$sets,$cardcode,$type,$org){
		$all = "";
		$card = "";
		$neg = 1;

		foreach($sets as $key => $value){
		$table = $tables[$value]['table'];
		$prefix = $tables[$value]['prefix'];
		$detailTable =  $tables[$value]['detailTable'];
		$detailPrefix = $tables[$value]['detailPrefix'];
		if ($cardcode) {
		  $card = "AND {$prefix}_cardcode = :dvf_cardcode";
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

		$unidad = ",'' unidad  ";
		if($tables[$value]['table'] == 'dvnc' OR
		$tables[$value]['table'] == 'dvnd' OR
		$tables[$value]['table'] == 'dcnc' OR
		$tables[$value]['table'] == 'dcnd'
		){
		$unidad = ",
		{$detailPrefix}_uom unidad";
		}

		if($tables[$value]['table'] == 'dvnc' or $tables[$value]['table'] == 'dcnc'){
			$neg = -1;
		}else{
			$neg = 1;
		}

		$cardType = 1;
		if($tables[$value]['table'] == 'dvnc' or
			$tables[$value]['table'] =='dvnd' OR $tables[$value]['table'] == 'dvfv'){
			$cardType = 1;

		}else{
			$cardType = 2;
		}

		$convertir = ($org == 0 ) ? "{$prefix}_currency  " : "{CURR}";
		// print_r($convertir);
		  $all .= "SELECT distinct
		  mdt_docname tipo_doc_name,
		  {$detailPrefix}_itemcode item_code,
		  to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_inicio,
          to_char(min({$prefix}_duedate),'DD-MM-YYYY') fecha_fin,
          to_char(min({$prefix}_duedev),'DD-MM-YYYY') fecha_doc,
          to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_cont,
		  to_char(min({$prefix}_createat),'DD-MM-YYYY') created,
		  {$prefix}_docnum docnum,
		  {$detailPrefix}_itemname item_name,
		  {$prefix}_cardname cliente_name,
		  concat({$convertir}, get_dynamic_conversion( {CURRD},{$prefix}_currency,{$prefix}_docdate, avg( {$detailPrefix}_linetotal * {$neg} ),{MAIN} ) )  val_factura,
		  concat({$convertir}, get_dynamic_conversion( {CURRD},{$prefix}_currency,{$prefix}_docdate, avg( {$detailPrefix}_price::numeric * {$neg} ),{MAIN} ))  price,
		  concat({$convertir}, get_dynamic_conversion( {CURRD},{$prefix}_currency,{$prefix}_docdate, avg( {$detailPrefix}_vatsum * {$neg} ),{MAIN} ) )  val_impuesto,
		  concat({$convertir}, get_dynamic_conversion( {CURRD},{$prefix}_currency,{$prefix}_docdate, ( avg( {$detailPrefix}_linetotal) + avg({$detailPrefix}_vatsum) * {$neg} - coalesce(get_retperline({$prefix}_doctype,{$prefix}_docentry,{$detailPrefix}_linenum),0) ),{MAIN}) ) total_docums,
		  get_tax_currency({CURRD}, {$prefix}_docdate) tasa,
		  mga_name,
		  {$prefix}_createby createby,
		  ".(($table =="dvnc")?" CASE when({$detailPrefix}_exc_inv =  0 ) then 0 else  (sum({$detailPrefix}_quantity) * {$neg}) end cantidad,":(($table == 'dvnd') ? "0 cantidad," : "sum({$detailPrefix}_quantity) cantidad,") )."
		  (SELECT concat(pgu_name_user,' ',pgu_lname_user) from pgus where pgu_code_user  = {$prefix}_createby) us_name,
		  (SELECT {$originPre}_docnum FROM {$origin} WHERE {$originPre}_docentry  = {$prefix}_baseentry AND {$originPre}_doctype  = {$prefix}_basetype) doc_afectado,
		  {$detailPrefix}_uom  unidad
		  ".(($table =="dcfc")? ",get_retnameperline(cfc_doctype,cfc_docentry,fc1_linenum)": ",'N/A'" )." rt_name,
		  ".(($table =="dcfc")? " case when get_dynamic_conversion({CURR},cfc_currency,cfc_docdate,get_retperline(cfc_doctype,cfc_docentry,fc1_linenum),{CURR}) > 0 then  concat({CURR},get_dynamic_conversion({CURR},{$prefix}_currency,{$prefix}_docdate,get_retperline(cfc_doctype,cfc_docentry,fc1_linenum),{MAIN})) else '' end ": "concat({CURRD},round(0,2))" )." rt_total
		  from {$table}
		  join {$detailTable} on {$prefix}_docentry = {$detailPrefix}_docentry
		  join dmdt on {$prefix}_doctype = mdt_doctype
		  join dmar on {$detailPrefix}_itemcode = dma_item_code
		  join dmsn on {$prefix}_cardcode  = dms_card_code  AND dms_card_type = '{$cardType}'
		  join dmga on mga_id = dma_group_code
		  left join dmsd on {$prefix}_cardcode = dmd_card_code AND dmd_ppal = 1
		  full join tasa on {$prefix}_currency = tasa.tsa_curro and {$prefix}_docdate = tsa_date
		  ".(($table =='dcfc')? "left join fcrt on crt_baseentry = {$detailPrefix}_docentry and crt_linenum = {$detailPrefix}_linenum
			left join dmrt on mrt_id = crt_type" : "")."
		  where ({$prefix}_docdate BETWEEN :dvf_docdate and  :dvf_duedate) {$card}
		  group by {$prefix}_docdate,{$detailPrefix}_itemname,{$prefix}_currency,".(($table =="dcfc")? "cfc_docentry,fc1_linenum,cfc_doctype,":"")."mga_name,mdt_docname,mdt_doctype,{$detailPrefix}_itemcode,{$prefix}_cardname, tsa_value,{$prefix}_docnum,{$prefix}_baseentry,{$prefix}_basetype,{$detailPrefix}_uom,{$prefix}_createby".(($table =="dvnc" )?",{$detailPrefix}_exc_inv": "")."
		  UNION ALL
		  ";
		}
		$all = substr($all, 0, -18);

		return $all;

	  }

}
