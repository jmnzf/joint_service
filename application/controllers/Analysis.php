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
		$this->load->library('generic');

	}

  public function getAnalysis_post()
  {

		$DECI_MALES =  $this->generic->getDecimals();
		$MONEDA_LOCAL = $this->generic->getLocalCurrency();
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

    // print_r($Data);exit;

    if (empty($Data['dvf_currency'])){
      $Data['dvf_currency'] = $Data['main_currency'];
    }

    if (empty($Data['symbol']) || !isset($Data['symbol']) ){
      $Data['symbol'] = $Data['main_currency'];
    }

    $main_currency = $Data['main_currency'];

    $original  = $Data['original'];
    unset($Data['main_currency']);
    unset($Data['original']);

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
    $campos = [];
    $campos = array(
      ':business' => $Data['business'],
      ':branch' => $Data['branch']
    );

    $req = array('dvf_doctype','dvf_docdate','dvf_duedate','date_filter');
    $diff = array_diff($options,$req);

    foreach ($diff as $key => $value) {
      if($Data[$value]!='' and $Data[$value]!=null and $value != $Data['symbol']){
          $conditions .='AND '.$table.'.'.str_replace("dvf",$prefix,$value).' = :'.$value.' ';
          $campos[':'.$value] = $Data[$value];
      }
    }
    $conditions = str_replace("AND {$table}.symbol = :symbol","",$conditions);
		$conditions = str_replace("AND ".$prefix."_currency = :".$prefix."_currency","",$conditions);
		$conditions = str_replace("AND ".$prefix."_currency = :dvf_currency","",$conditions);
		// $conditions = str_replace("AND symbol = :symbol","",$conditions);
    // print_r($conditions);exit;
    $sqlSelect = " ";
    $cardcode = (isset( $Data['dvf_cardcode']) and $Data['dvf_cardcode'] !=null) ?  true: false;

    if($tables[$tipo]['table'] == 'dvnc' or $tables[$tipo]['table'] == 'dcnc'){
      $neg = -1;
    }else{
      $neg = 1;
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
        $campos[':dvf_docdate'] = $Data['dvf_docdate'];
        $campos[':dvf_duedate'] = $Data['dvf_duedate'];
        unset($campos[':dvf_currency']);
      break;
      case '0':
        $sqlSelect = $this->generalQuery($tables,['5','6','7'],$cardcode,0,$original);
        $campos[':dvf_docdate'] = $Data['dvf_docdate'];
        $campos[':dvf_duedate'] = $Data['dvf_duedate'];
        unset($campos[':dvf_currency']);
      break;

      default:
          $sqlSelect = "SELECT
          mdt_docname tipo_doc_name,
          {$prefix}_cardcode cliente,
          mgs_name,
          {$prefix}_docnum docnum,
          {$prefix}_cardname cliente_name,
          concat(dmd_adress,' ',dmd_city) Direccion,
          coalesce(pdm_municipality, 'Sin direccion establecida') as ciudad,
          bdc_clasify ,
          {$prefix}_comment bdc_concept,
          to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_inicio,
          to_char(min({$prefix}_duedate),'DD-MM-YYYY') fecha_fin,
          to_char(min({$prefix}_duedev),'DD-MM-YYYY') fecha_doc,
          to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_cont,
          to_char(min({$prefix}_createat),'DD-MM-YYYY') created,
          ".(($table =="dvnc")?" CASE when({$detailPrefix}_exc_inv =  0 ) then 0 else  (sum({$detailPrefix}_quantity) * {$neg}) end cant_docs,":(($table == 'dvnd') ? "0 cant_docs," : "sum({$detailPrefix}_quantity) cant_docs,") )."
        ".(($original == 0) ? "concat({$prefix}_currency, ' ', to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_baseamnt,{MAIN}), get_decimals()), '999,999,999,999.00')) " : "concat({CURR},to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_baseamnt,{MAIN}), get_decimals()), '999,999,999,999.00' ))")." val_factura,
        ".(($original == 0) ? "concat({$prefix}_currency, ' ', to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_taxtotal,{MAIN}), get_decimals()), '999,999,999,999.00')) " : "concat({CURR},to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_taxtotal,{MAIN}), get_decimals()), '999,999,999,999.00' ))")."  val_impuesto,
        ".(($original == 0) ? "concat({$prefix}_currency, ' ', to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_doctotal,{MAIN}), get_decimals()), '999,999,999,999.00')) " : "concat({CURR},to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_doctotal,{MAIN}), get_decimals()), '999,999,999,999.00' ))")."  total_docums,
          get_tax_currency({CURRD}, {$prefix}_docdate) tasa,
          {$prefix}_createby createby,
          ".(($table =="dvnc")?"{$detailPrefix}_exc_inv invent ,": "'' invent,")."
          (SELECT concat(pgu_name_user,' ',pgu_lname_user) from pgus where pgu_code_user  = {$prefix}_createby) us_name,
          (SELECT {$prefix}_docnum FROM {$table} WHERE {$prefix}_docentry  = {$prefix}_baseentry AND {$prefix}_doctype  = {$prefix}_basetype) doc_afectado,
          {$detailPrefix}_uom  unidad
          ".(($table =="dcfc")? "
      ,concat({CURR},to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_totalret,{MAIN}), get_decimals()), '999,999,999,999.00' )) total_ret,
      concat({CURR},to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_totalretiva,{MAIN}), get_decimals()), '999,999,999,999.00' )) totalretiva":",concat({CURR},to_char(round(0,2), '999,999,999,999.00')) total_ret,
      concat({CURR},to_char(round(0,2), '999,999,999,999.00')) totalretiva")."
        from
        {$table}
        join dmsn on {$prefix}_cardcode  = dms_card_code  AND dms_card_type = '{$cardType}'
        join dmgs on dms_group_num::int = mgs_id
        join {$detailTable} on {$prefix}_docentry = {$detailPrefix}_docentry
        join dmdt on {$prefix}_doctype = mdt_doctype
        full join tbdc  on dms_classtype = bdc_clasify
        left join dmsd on {$prefix}_cardcode = dmd_card_code AND dmd_ppal = 1
        left join tpdm on dmsd.dmd_city  = pdm_codmunicipality
        where  ({$prefix}_{$Data['date_filter']} BETWEEN '".$Data['dvf_docdate']."' and  '".$Data['dvf_duedate']."') ".$conditions."
        GROUP BY {$prefix}_cardcode, mgs_name, {$prefix}_cardname,{$prefix}_docnum, mdt_docname, bdc_clasify, {$prefix}_comment,dmd_adress,{$prefix}_doctotal,pdm_municipality,
        {$prefix}_baseamnt,{$prefix}_taxtotal,{$prefix}_doctotal,{$prefix}_docdate,dmd_city,{$prefix}_baseentry,{$prefix}_basetype ".(($table =="dcfc")? ",cfc_totalret, cfc_totalretiva":"").",{$prefix}_currency,{$detailPrefix}_uom,{$prefix}_createby".(($table =="dvnc")?",{$detailPrefix}_exc_inv": "");
        
        break;
      }

				if( isset( $Data['dvf_currency'] ) && isset($Data['symbol'])){
					$sqlSelect =	str_replace("{USD}","tsa_value",$sqlSelect);
					$sqlSelect =	str_replace("{CURR}","'".$Data['symbol']." '",$sqlSelect);
					$sqlSelect =	str_replace("{CURRD}","'".$Data['symbol']."'",$sqlSelect);
					$sqlSelect =	str_replace("{MAIN}","'".$main_currency."'",$sqlSelect);
				}else{
					$sqlSelect =	str_replace("{USD}",1,$sqlSelect);
					$sqlSelect =	str_replace("{CURR}","'".$MONEDA_LOCAL."'",$sqlSelect);
					$sqlSelect =	str_replace("{CURRD}","'".$MONEDA_LOCAL."'",$sqlSelect);
					$sqlSelect =	str_replace("{MAIN}","'".$main_currency."'",$sqlSelect);

				}
       
				// unset($campos[':'.$prefix.'_currency']);
        // print_r($sqlSelect);exit;
        unset($campos[':symbol']);

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
  private function generalQuery($tables,$sets,$cardcode,$type,$org){
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

    $cardType = 1;
    if($tables[$value]['table'] == 'dvnc' or
      $tables[$value]['table'] =='dvnd' OR $tables[$value]['table'] == 'dvfv'){
      $cardType = 1;

    }else{
      $cardType = 2;
    }

      $all .= "SELECT
      mdt_docname tipo_doc_name,
      {$prefix}_cardcode cliente,
      mgs_name,
      {$prefix}_docnum docnum,
      {$prefix}_cardname cliente_name,
      concat(dmd_adress,' ',dmd_city) Direccion,
      coalesce(pdm_municipality, 'Sin direccion establecida') as ciudad,
      bdc_clasify ,
      {$prefix}_comment bdc_concept,
      to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_inicio,
      to_char(min({$prefix}_duedate),'DD-MM-YYYY') fecha_fin,
      to_char(min({$prefix}_duedev),'DD-MM-YYYY') fecha_doc,
      to_char(min({$prefix}_docdate),'DD-MM-YYYY') fecha_cont,
      to_char(min({$prefix}_createat),'DD-MM-YYYY') created,
      ".(($table =="dvnc")?" CASE when({$detailPrefix}_exc_inv =  0 ) then 0 else  (sum({$detailPrefix}_quantity) * {$neg}) end cant_docs,":(($table == 'dvnd') ? "0 cant_docs," : "sum({$detailPrefix}_quantity) cant_docs,") )."
      ".(($org == 0) ? "concat({$prefix}_currency, ' ',  to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_baseamnt,{MAIN}), get_decimals() ) * {$neg} , '999,999,999,999.00')) " : "concat({CURR}, to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_baseamnt,{MAIN}), get_decimals() ) * {$neg}, '999,999,999,999.00'))")." val_factura,
      ".(($org == 0) ? "concat({$prefix}_currency, ' ',  to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_taxtotal,{MAIN}), get_decimals() ) , '999,999,999,999.00')) " : "concat({CURR}, to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_taxtotal,{MAIN}), get_decimals() ) * {$neg}, '999,999,999,999.00'))")."  val_impuesto,
      ".(($org == 0) ? "concat({$prefix}_currency, ' ',  to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_doctotal,{MAIN}), get_decimals() ), '999,999,999,999.00')) " : "concat({CURR}, to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_doctotal,{MAIN}), get_decimals() ) * {$neg}, '999,999,999,999.00'))")."  total_docums,
      get_tax_currency({CURRD}, {$prefix}_docdate) tasa,
      {$prefix}_createby createby,
      ".(($table =="dvnc")?"{$detailPrefix}_exc_inv::text": "'' invent").",
      (SELECT concat(pgu_name_user,' ',pgu_lname_user) from pgus where pgu_code_user  = {$prefix}_createby) us_name,
		  (SELECT {$originPre}_docnum FROM {$origin} WHERE {$originPre}_docentry  = {$prefix}_baseentry AND {$originPre}_doctype  = {$prefix}_basetype) doc_afectado,
      {$detailPrefix}_uom  unidad
      ".(($table =="dcfc")? "
      ,
      concat({CURR},to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_totalret,{MAIN}), get_decimals() ), '999,999,999,999.00')) total_ret,
      concat({CURR},to_char(round(get_dynamic_conversion({CURRD},{$prefix}_currency,{$prefix}_docdate,{$prefix}_totalretiva,{MAIN}), get_decimals() ), '999,999,999,999.00')) totalretiva":",concat({CURR},to_char(round(0, get_decimals() ), '999,999,999,999.00')) total_ret,
      concat({CURR},round(0,2)) totalretiva")."
      from
      {$table}
      JOIN dmsn on {$prefix}_cardcode = dms_card_code AND dms_card_type = '{$cardType}'
      join dmgs on dms_group_num::int = mgs_id
      join {$detailTable} on {$prefix}_docentry = {$detailPrefix}_docentry
      join dmdt on {$prefix}_doctype = mdt_doctype
      left join tbdc on dms_classtype = bdc_clasify
      left join dmsd on {$prefix}_cardcode = dmd_card_code AND dmd_ppal = 1
      left join tpdm on dmsd.dmd_city  = pdm_codmunicipality
      where ({$prefix}_docdate BETWEEN :dvf_docdate and :dvf_duedate) {$card} AND {$table}.business = :business AND {$table}.branch = :branch
      GROUP BY {$prefix}_cardcode, mgs_name, {$prefix}_cardname,{$prefix}_docnum,pdm_municipality,
      {$prefix}_docdate,{$prefix}_doctotal,{$prefix}_taxtotal,{$prefix}_baseamnt, mdt_docname, bdc_clasify".(($table =="dcfc")? ",{$prefix}_totalret, {$prefix}_totalretiva":"").", {$prefix}_comment,dmd_adress,{$prefix}_currency, dmd_city,{$prefix}_baseentry,{$prefix}_basetype,{$detailPrefix}_uom,{$prefix}_createby".(($table =="dvnc")?",{$detailPrefix}_exc_inv": "")."
      UNION ALL
      ";
    }
    $all = substr($all, 0, -18);

    return $all;

  }

  public function getAnalysisDist_get()
  {
    $request = $this->get();
    $DECI_MALES =  $this->generic->getDecimals();

    $moneda = $this->pedeo->queryTable("select get_localcur() as moneda", array())[0]['moneda'];

    if(!isset($request['date_start']) OR !isset($request['date_end']) OR !isset($request['business']) OR !isset($request['branch'])){
      $respuesta = array(
        'error' => true,
        'data' => [],
        'mensaje' => 'Informacion enviada invalida'
      );

      $this->response($respuesta);
      return;
    }
    $where = "";
    if(isset($request['user']) && !empty($request['user'])){
      $where .= " AND dvrc.vrc_createby = '".$request['user']."'";
    }
    $sql = "SELECT
            mdt_docname tipo_doc_name,
            vrc_cardcode cliente,
            mgs_name,
            vrc_docnum docnum,
            vrc_cardname cliente_name,
            concat(dmd_adress,' ',dmd_city) Direccion,
            bdc_clasify ,
            vrc_comment bdc_concept,
            to_char(min(vrc_docdate),'DD-MM-YYYY') fecha_inicio,
            to_char(min(vrc_duedate),'DD-MM-YYYY') fecha_fin,
            to_char(min(vrc_duedev),'DD-MM-YYYY') fecha_doc,
            to_char(min(vrc_docdate),'DD-MM-YYYY') fecha_cont,
            to_char(min(vrc_createat),'DD-MM-YYYY') created,
            sum(rc1_quantity) cant_docs,
            round(get_dynamic_conversion( get_localcur(),vrc_currency,vrc_docdate,vrc_monto_v, get_localcur()), get_decimals() ) * 1  total,
            round(get_dynamic_conversion( get_localcur(),vrc_currency,vrc_docdate,vrc_total_c, get_localcur()), get_decimals() ) * 1  val_factura,
            /*round(get_dynamic_conversion( get_localcur(),vrc_currency,vrc_docdate,vrc_total_c, get_localcur()), get_decimals() )    val_impuesto, 
            round(get_dynamic_conversion( get_localcur(),vrc_currency,vrc_docdate,vrc_total_c, get_localcur()), get_decimals() ) total_docums,*/
            get_tax_currency( get_localcur(), vrc_docdate) tasa,
            vrc_createby createby,
            '' invent,
            (SELECT concat(pgu_name_user,' ',pgu_lname_user) from pgus where pgu_code_user  = vrc_createby) us_name,
            (SELECT vrc_docnum FROM dvrc WHERE vrc_docentry  = vrc_baseentry AND vrc_doctype  = vrc_basetype) doc_afectado,
            rc1_uom  unidad
            ,round(0, get_decimals() ) total_ret,
            round(0,2) totalretiva,
            dvrc.vrc_pasanaku 
            from dvrc
            JOIN dmsn on vrc_cardcode = dms_card_code AND dms_card_type = '1'
            join dmgs on dms_group_num::int = mgs_id
            join vrc1 on vrc_docentry = rc1_docentry and coalesce(rc1_itemdev,0) = 0
            join dmdt on vrc_doctype = mdt_doctype
            left join tbdc on dms_classtype = bdc_clasify
            left join dmsd on vrc_cardcode = dmd_card_code AND dmd_ppal = 1
            where (vrc_docdate BETWEEN :vrc_docdate and :vrc_duedate)  AND dvrc.business = :business AND dvrc.branch = :branch and coalesce(rc1_itemdev,0) = 0 {where}
            GROUP BY vrc_cardcode, mgs_name, vrc_cardname,vrc_docnum,
            vrc_docdate,vrc_total_c, mdt_docname, bdc_clasify, vrc_comment,dmd_adress,vrc_currency, dmd_city,vrc_baseentry,vrc_basetype,rc1_uom,vrc_createby,
            dvrc.vrc_pasanaku ,vrc_monto_v
          
            union all 
            SELECT
            concat(mdt_docname,' - Devolución') tipo_doc_name,
            vrc_cardcode cliente,
            mgs_name,
            vrc_docnum docnum,
            vrc_cardname cliente_name,
            concat(dmd_adress,' ',dmd_city) Direccion,
            bdc_clasify ,
            vrc_comment bdc_concept,
            to_char(min(vrc_docdate),'DD-MM-YYYY') fecha_inicio,
            to_char(min(vrc_duedate),'DD-MM-YYYY') fecha_fin,
            to_char(min(vrc_duedev),'DD-MM-YYYY') fecha_doc,
            to_char(min(vrc_docdate),'DD-MM-YYYY') fecha_cont,
            to_char(min(vrc_createat),'DD-MM-YYYY') created,
            sum(rc1_quantity) cant_docs,
            round(get_dynamic_conversion( get_localcur(),vrc_currency,vrc_docdate,vrc_monto_dv, get_localcur()), get_decimals() ) * -1  total,
            round(get_dynamic_conversion( get_localcur(),vrc_currency,vrc_docdate,vrc_total_d, get_localcur()), get_decimals() ) * -1  val_factura,
          /* round(get_dynamic_conversion( get_localcur(),vrc_currency,vrc_docdate,vrc_monto_dv, get_localcur()), get_decimals()) * -1   val_impuesto,
            round(get_dynamic_conversion( get_localcur(),vrc_currency,vrc_docdate,vrc_monto_dv, get_localcur()), get_decimals() ) * -1 total_docums,*/
            get_tax_currency( get_localcur(), vrc_docdate) tasa,
            vrc_createby createby,
            '' invent,
            (SELECT concat(pgu_name_user,' ',pgu_lname_user) from pgus where pgu_code_user  = vrc_createby) us_name,
            (SELECT vrc_docnum FROM dvrc WHERE vrc_docentry  = vrc_baseentry AND vrc_doctype  = vrc_basetype) doc_afectado,
            rc1_uom  unidad
            ,round(0, get_decimals() ) total_ret,
            round(0,2) totalretiva,
            dvrc.vrc_pasanaku 
            from dvrc
            JOIN dmsn on vrc_cardcode = dms_card_code AND dms_card_type = '1'
            join dmgs on dms_group_num::int = mgs_id
            join vrc1 on vrc_docentry = rc1_docentry and coalesce(rc1_itemdev,0) = 1
            join dmdt on vrc_doctype = mdt_doctype
            left join tbdc on dms_classtype = bdc_clasify
            left join dmsd on vrc_cardcode = dmd_card_code AND dmd_ppal = 1
            where (vrc_docdate BETWEEN :vrc_docdate and :vrc_duedate)  AND dvrc.business = :business AND dvrc.branch = :branch and coalesce(rc1_itemdev,0) = 1 {where}
            GROUP BY vrc_cardcode, mgs_name, vrc_cardname,vrc_docnum,
            vrc_docdate,vrc_monto_dv, mdt_docname, bdc_clasify, vrc_comment,dmd_adress,vrc_currency, dmd_city,vrc_baseentry,vrc_basetype,rc1_uom,vrc_createby,
            dvrc.vrc_pasanaku,vrc_total_d
            order by docnum asc";

            $sql = str_replace("{where}",$where,$sql);
        // print_r($sql);exit;
            $resSql = $this->pedeo->queryTable($sql,array(
              ':vrc_docdate' => $request['date_start'],
              ':vrc_duedate' => $request['date_end'],
              ':business' => $request['business'],
              ':branch' => $request['branch']
            ));
            $base = 0;
            $pasanaku = 0;
            $total = 0;
            foreach ($resSql as $key => $value) {

              $value['total'] = $value['total'];
              $value['vrc_pasanaku'] = $value['vrc_pasanaku'];
              $value['val_factura'] = $value['val_factura'];

              $base += floatval($value['total']);
              $pasanaku += floatval($value['vrc_pasanaku']);
              $total += floatval($value['val_factura']);
            }

            if(isset($resSql[0])){

              $respuesta = array(
                'error' => false,
                'data' => $resSql,
                'base' => $base, $DECI_MALES,
                'pasanaku' =>  $pasanaku, $DECI_MALES,
                'total' =>  $total,
                'mensaje' => 'OK'
              );

            }else{

              $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'No se encontraron datos en la busqueda'
              );
              
            }

            $this->response($respuesta);
          
  }

}
