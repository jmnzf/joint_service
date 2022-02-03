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
		$table = $tables[$Data['dvf_doctype']]['table'];
		$prefix = $tables[$Data['dvf_doctype']]['prefix'];
    $detailTable =  $tables[$Data['dvf_doctype']]['detailTable'];
		$detailPrefix = $tables[$Data['dvf_doctype']]['detailPrefix'];

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
				
        $sqlSelect = "SELECT
               mdt_docname tipo_doc_name,
               {$prefix}_cardcode cliente,
               mgs_name,
               {$prefix}_docnum,
               {$prefix}_cardname cliente_name,
               concat(dmd_adress,' ',dmd_city) Direccion,
               bdc_clasify ,
               bdc_concept,
               min({$prefix}_docdate) fecha_inicio,
               min({$prefix}_duedate) fecha_fin,
               min({$prefix}_duedev) fecha_doc,
               min({$prefix}_docdate) fecha_cont,
               sum({$detailPrefix}_quantity) cant_docs,
               concat({CURR},round(sum(({$prefix}_baseamnt) / {USD}),2)) val_factura,
               concat({CURR},round(sum(({$prefix}_taxtotal) / {USD}),2)) val_impuesto,
               concat({CURR},round(sum(({$prefix}_doctotal) / {USD}),2)) total_docums,
               round(avg(tsa_value),2) tasa

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
        GROUP BY {$prefix}_cardcode, mgs_name, {$prefix}_cardname,{$prefix}_docnum, mdt_docname, bdc_clasify, bdc_concept,dmd_adress, dmd_city";



				if( isset( $Data['dvf_currency'] ) && $Data['dvf_currency'] == 1 ){
					$sqlSelect =	str_replace("{USD}","tsa_value",$sqlSelect);
					$sqlSelect =	str_replace("{CURR}","'USD '",$sqlSelect);
				}else{
					$sqlSelect =	str_replace("{USD}",1,$sqlSelect);
					$sqlSelect =	str_replace("{CURR}","'BS '",$sqlSelect);
				}

				unset($campos[':'.$prefix.'_currency']);
				unset($campos[':dvf_currency']);
// print_r($sqlSelect);exit;
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


}
