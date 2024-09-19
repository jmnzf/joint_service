<?php
// TRANSFERENCIA DE STOCKS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;

class Pagination extends REST_Controller
{

	private $pdo;

	public function __construct()
	{

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
		$this->load->library('pedeo', [$this->pdo]);
		$this->load->library('generic');
		$this->load->library('account');
		$this->load->library('aprobacion');
		$this->load->library('DocumentCopy');
		$this->load->library('DocumentNumbering');
		$this->load->library('Tasa');
		
	}
	//OBTENER  TRASLADO
	public function documentPagination_post()
	{
		
		$Data = $this->post();

		if (!isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$DECI_MALES =  $this->generic->getDecimals();
		$campos = ",T4.dms_phone1, T4.dms_phone2, T4.dms_cel";
		$innerjoin = "";
		$variableSql = "";

		$table = $Data['table'];
		$prefix = $Data['prefix'];
		$tableException = ['dvnc', 'dvfv','dcfc', 'dcnc','dcnd','dcec','dcdc', 'tcsn','dcsa'];
		$doctype = (isset($Data['doctype']))? $Data['doctype']: 0;
		$cardtype = $Data['cardtype'];
		$isdefault = true;
		if(in_array($table, $tableException)){
			switch ($table) {
				case 'dvnc':
					$campos.= ",{prefix}_cufe";
					break;
				case 'dvfv':
					$campos.= ",{prefix}_cufe, {prefix}_correl, {prefix}_response_dian";
				break;
				case 'dcfc':
					$campos.= ",t0.{prefix}_tax_control_num,CONCAT(T0.{prefix}_CURRENCY,' ',TRIM(TO_CHAR(t0.{prefix}_totalret,'999,999,999,999.00'))) {prefix}_totalret,
					CONCAT(T0.{prefix}_CURRENCY,' ',TRIM(TO_CHAR(t0.{prefix}_totalretiva,'999,999,999,999.00'))) {prefix}_totalretiva , t0.{prefix}_duedev";
				break;
				case "dcnc":
					$campos = ",CONCAT(T0.{prefix}_CURRENCY,' ',TRIM(TO_CHAR(t0.{prefix}_totalret,'999,999,999,999.00'))) {prefix}_totalret,
						CONCAT(T0.{prefix}_CURRENCY,' ',TRIM(TO_CHAR(t0.{prefix}_totalretiva,'999,999,999,999.00'))) {prefix}_totalretiva";
				break;
				case "tcsn":
					$campos = ", coalesce(get_geoubicaciones(t0.csn_docentry, t0.csn_doctype), 0) as ubicado, t0.csn_contacid, t0.csn_idadd, t0.csn_duedev, t0.csn_enddate, t0.csn_signaturedate, t0.csn_prjcode, t0.csn_description, t0.csn_billingday, t0.csn_cleantotal, t0.csn_weeklytoilets, t0.csn_totalret, t0.csn_totalretiva";
					if(isset($Data['slt_paycondition']) && !empty($Data['slt_paycondition'])){
						$campos .= ", d.csn_paymentcondition";
						$variableSql .= "AND d.csn_paymentcondition = ".$Data['slt_paycondition'];
					}
			
					if(isset($Data['slt_typeagreement']) && !empty($Data['slt_typeagreement'])){
						$campos .= ", d.csn_typeagreement";
						$variableSql .= "AND d.csn_typeagreement = ".$Data['slt_typeagreement'];
					}

					if(!empty($variableSql)){
						$innerjoin = "INNER JOIN dcsn d ON t0.csn_docentry = d.csn_docentry";
					}
				break;
				case 'dcsa':
					$campos.= ",CONCAT(T0.csa_CURRENCY,' ',TRIM(TO_CHAR(csa_anticipate_total,'{format}'))) as csa_anticipate_total ";
				break;
			}
		
			$campos = str_replace("{prefix}",$prefix,$campos);
		}

		$sqlSelect = self::getColumn($table, $prefix, $campos, $innerjoin, $DECI_MALES, $Data['business'], $Data['branch'],$doctype,0,0,"",$cardtype);
		// NUMERO DE REGISTROS EN LA TABLA
		$numRows = $this->pedeo->queryTable("SELECT get_numrows('$table') as numrows", []);
		$columns = array(
			$prefix."_docnum",
			$prefix."_cufe",
			$prefix."_docdate",
			$prefix."_cardname",
			$prefix."_comment",
			$prefix."_doctotal",
			$prefix."_slpcode"
		);

		if (isset($Data['search']['value']) && !empty($Data['search']['value'])) {
			// OBTENER CONDICIONALES.
			$variableSql .= " AND  " . self::get_Filter($columns, strtoupper($Data['search']['value']));
		}

		$sqlSelect .= $variableSql;
    

		$sqlSelect .=" ORDER BY ".$columns[$Data['order'][0]['column']]." ".$Data['order'][0]['dir']." LIMIT ".$Data['length']." OFFSET ".$Data['start'];
		

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'rows'  => $numRows[0]['numrows'],
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	public function inventoryPagination_post(){

		$Data = $this->post();

		$table = $Data['table'];
		$prefix = $Data['prefix'];

		$variableSql = "";

		$sqlSelect = "SELECT
					t0.{prefix}_docentry,
					t0.{prefix}_currency,
					t2.mdt_docname,
					t0.{prefix}_docnum,
					t0.{prefix}_docdate,
					t0.{prefix}_cardname,
					t0.{prefix}_comment,
					CONCAT(T0.{prefix}_currency,' ',to_char(t0.{prefix}_baseamnt,'999,999,999,999.00')) {prefix}_baseamnt,
					CONCAT(T0.{prefix}_currency,' ',to_char(t0.{prefix}_doctotal,'999,999,999,999.00')) {prefix}_doctotal,
					t1.mev_names {prefix}_slpcode
					FROM {table} t0
					LEFT JOIN dmev t1 on t0.{prefix}_slpcode = t1.mev_id
					LEFT JOIN dmdt t2 on t0.{prefix}_doctype = t2.mdt_doctype
					WHERE t0.business = :business";

		$numRows = $this->pedeo->queryTable("SELECT get_numrows('$table') as numrows", []);

		$columns = array(
			't0.'.$prefix.'_docnum',
			't0.'.$prefix.'_cardname',
			't1.mev_names',
		 	't0.'.$prefix.'_comment'
		);

		if (isset($Data['search']['value']) && !empty($Data['search']['value'])) {
			// OBTENER CONDICIONALES.
			$variableSql .= " AND  " . self::get_Filter($columns, strtoupper($Data['search']['value']));
		}

		$sqlSelect .= $variableSql;
    

		$sqlSelect .=" ORDER BY ".$columns[$Data['order'][0]['column']]." ".$Data['order'][0]['dir']." LIMIT ".$Data['length']." OFFSET ".$Data['start'];
		$sqlSelect = str_replace("{table}",$table,$sqlSelect);
		$sqlSelect = str_replace("{prefix}",$prefix,$sqlSelect);
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':business' => $Data['business']));
		
		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'rows'  => $numRows[0]['numrows'],
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);

	}

	public function paymentPagination_post(){
		$sqlSelect = "";
		$variableSql = "";

		$Data = $this->post();
		$table = $Data['table'];
		$prefix = $Data['prefix'];

		switch ($table) {
			case 'gbpe':
				$sqlSelect = "SELECT 
						coalesce(r.estado,'Cerrado') as estado  ,
						concat(bpe_currency,' ',trim(to_char(bpe_vlrpaid, '999,999,999.00'))) as bpe_vlrpaid,
						bpe_docentry,
						bpe_cardcode,
						bpe_cardname,
						bpe_address,
						bpe_perscontact,
						bpe_series,
						bpe_docnum,
						bpe_docdate,
						bpe_taxdate,
						bpe_ref,
						bpe_transid,
						bpe_comments,
						bpe_memo,
						bpe_acctransfer,
						bpe_datetransfer,
						bpe_reftransfer,
						bpe_doctotal,
						bpe_project,
						bpe_createby,
						bpe_createat,
						bpe_payment,
						bpe_doctype,
						bpe_currency,
						bpe_paytoday,
						business,
						branch,
						bpe_revalued
					from
						gbpe
					left join
						responsestatus r on gbpe.bpe_docentry = r.id and gbpe.bpe_doctype = r.tipo 
					where
						1 = 1
						and business = :business
						and branch = :branch ";
				break;
				case 'gbpr':
				$sqlSelect = "SELECT 
						coalesce(r.estado,'Cerrado') as estado ,
						concat(bpr_currency,' ',trim(to_char(bpr_vlrpaid, '999,999,999.00'))) as bpr_vlrpaid,
						bpr_docentry,
						bpr_cardcode,
						bpr_cardname,
						bpr_address,
						bpr_perscontact,
						bpr_series,
						bpr_docnum,
						bpr_docdate,
						bpr_taxdate,
						bpr_ref,
						bpr_transid,
						bpr_comments,
						bpr_memo,
						bpr_acctransfer,
						bpr_datetransfer,
						bpr_reftransfer,
						bpr_doctotal,
						bpr_project,
						bpr_createby,
						bpr_createat,
						bpr_payment,
						bpr_doctype,
						bpr_currency,
						bpr_paytoday,
						business,
						branch
						from
							gbpr
						left join
							responsestatus r on gbpr.bpr_docentry = r.id and gbpr.bpr_doctype = r.tipo 
						where
							1 = 1
							and business = :business
							and branch = :branch ";
					break;
			
			default:
				# code...
				break;
		}

		// NUMERO DE REGISTROS EN LA TABLA
		$numRows = $this->pedeo->queryTable("SELECT get_numrows('$table') as numrows", []);

		$columns = array(
			$prefix."_cardcode",
			$prefix."_docdate",
			$prefix."_createby"
		);
		if (isset($Data['search']['value']) && !empty($Data['search']['value'])) {
			// OBTENER CONDICIONALES.
			$variableSql .= " AND  " . self::get_Filter($columns, strtoupper($Data['search']['value']));
		}

		$sqlSelect.= $variableSql;

		$sqlSelect .=" ORDER BY ".$columns[$Data['order'][0]['column']]." ".$Data['order'][0]['dir']." LIMIT ".$Data['length']." OFFSET ".$Data['start'];


		$resSelect = $this->pedeo->queryTable($sqlSelect, array(
			':business' => $Data['business'],
			':branch' => $Data['branch']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'rows'  => $numRows[0]['numrows'],
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	public function cashRegister_post(){
		$Data = $this->post();
		$variableSql = "";
		$sqlSelect = " SELECT  
                        vrc_docdate,
                        vrc_docnum,
                        vrc_cardname,
                        vrc_cardcode,
                        vrc_docentry,
                        vrc_currency,
                        vrc_monto_dv,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_total_c,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_total_c,
                        vrc_createby,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_monto_dv,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_monto_dv,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_monto_v,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_monto_v,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_monto_a,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_monto_a,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_pasanaku,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_pasanaku,
                        concat(vrc_currency,to_char(round(get_dynamic_conversion(vrc_currency,vrc_currency,vrc_docdate,vrc_total_d,get_localcur()), get_decimals()), '999,999,999,999.00' )) vrc_total_d,
                        estado,
                        mdp_name  
                        FROM dvrc
                        left join tmdp on mdp_id = vrc_paymentmethod
                        INNER JOIN responsestatus  ON vrc_docentry = responsestatus.id and vrc_doctype = responsestatus.tipo
						WHERE 1 = 1 
						";

						if(!empty($Data['estado'])){
							$sqlSelect .= " AND estado = '{$Data['estado']}'";
						}

		// NUMERO DE REGISTROS EN LA TABLA
		$numRows = $this->pedeo->queryTable("SELECT get_numrows('dvrc') as numrows", []);

		$columns = array(
			"vrc_docnum",
			"vrc_docdate",
			"vrc_cardname"
		);

		if (isset($Data['search']['value']) && !empty($Data['search']['value'])) {
			// OBTENER CONDICIONALES.
			$variableSql .= " AND  " . self::get_Filter($columns, strtoupper($Data['search']['value']));
		}

		$sqlSelect .= $variableSql;
    

		$sqlSelect .=" ORDER BY ".$columns[$Data['order'][0]['column']]." ".$Data['order'][0]['dir']." LIMIT ".$Data['length']." OFFSET ".$Data['start'];

        $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        if(isset($resSelect[0])){

            $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
			'rows'  => $numRows[0]['numrows'],
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

	public function get_Filter($columns, $value)
	{
		//
		$resultSet = "";
		// CONDICIONAL.
		$where = " UPPER({campo}::text) LIKE '%" . $value . "%' OR";
		//
		try {
			//
			foreach ($columns as $column) {
				// REEMPLAZAR CAMPO.
				$resultSet .= str_replace('{campo}', $column, $where);
			}
			// REMOVER ULTIMO OR DE LA CADENA.
			$resultSet = substr($resultSet, 0, -2);
		} catch (Exception $e) {
			$resultSet = $e->getMessage();
		}
		//
		return $resultSet;
	}
	
}
