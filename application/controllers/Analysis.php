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
			'5' =>  array('table' =>'dvfv','prefix'=>'dvf'),
			'6' =>  array('table' =>'dvnc','prefix'=>'vnc'),
			'7' =>  array('table' =>'dvnd','prefix'=>'vnd'),
			'2' =>  array('table' =>'dvov','prefix'=>'vov'),
			'12' =>  array('table' =>'dcpo','prefix'=>'cpo'),
			'15' =>  array('table' =>'dcfc','prefix'=>'cfc')
		);
		$table = $tables[$Data['dvf_doctype']]['table'];
		$prefix = $tables[$Data['dvf_doctype']]['prefix'];


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

        $sqlSelect = "SELECT
               mdt_docname tipo_doc_name,
               {$prefix}_cardcode cliente,
               mgs_name ,
               {$prefix}_cardname cliente_name,
               concat(dmd_adress,' ',dmd_city) Direccion,
               bdc_clasify ,
               bdc_concept,
               min({$prefix}_docdate) fecha_inicio,
               min({$prefix}_duedate) fecha_fin,
               count(1) cant_docs,
               sum({$prefix}_baseamnt) val_factura,
               sum({$prefix}_taxtotal) val_impuesto,
               sum({$prefix}_doctotal) total_docums,
               round(avg(tsa_value),2) tasa
        from
        {$table}
        full join dmsn on {$prefix}_cardcode  = dms_card_code
        full join dmgs on dms_rtype = mgs_id
        full join dmdt on {$prefix}_doctype = mdt_doctype
        full join tbdc  on dms_classtype = bdc_clasify
        left join dmsd on {$prefix}_cardcode = dmd_card_code AND dmd_ppal = 1
        full join tasa on {$prefix}_currency = tasa.tsa_curro and {$prefix}_docdate = tsa_date
        where ({$prefix}_{$Data['date_filter']} BETWEEN :dvf_docdate and  :dvf_duedate) ".$conditions." 
        GROUP by {$prefix}_cardcode, mgs_name, {$prefix}_cardname, mdt_docname, bdc_clasify, bdc_concept,dmd_adress, dmd_city";
				// if($Data['dvf_doctype'] == 2){
				// 	$conditions = str_replace("dvf",$prefix,$conditions);
				// }
        $resSelect = $this->pedeo->queryTable($sqlSelect, $campos);

				// print_r($sqlSelect);
				// exit;

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
