<?php
// DATOS MAESTROS ACIENTOS CONTABLES
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class BalanceThird extends REST_Controller {

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
    // OBTENER VALOR DE PEDIDO DE VENTAS, MES Y AÑO PRESENTE
    public function BalanceThird_post(){

		$Data = $this->post();

		if($Data['tipo'] == 'L'){
			$where = '';
			if(!empty($Data['account'])){
				$array = array_map(function($acc){
				return "'{$acc}'";
			},$Data['account']);

			$account = implode(',',$array);
			$where = "AND t0.acc_code IN ({$account})";
		}

		$saldo_inicial = 0;
		$saldo_cero = 0;

		// $Data['saldo_inicial'] = '';
		if(!empty($Data['saldo_inicial']) && $Data['saldo_inicial'] == 'N'){
			$saldo_inicial = "coalesce(case
			when t0.acc_level = 1
				then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1)
			when t0.acc_level = 2
				then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
			when t0.acc_level = 3
				then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3)
			when t0.acc_level = 4
				then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2	and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
			when t0.acc_level = 5
				then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.account = t0.acc_code )
			when t0.acc_level = 6
				then (select sum(a.sal_i) from balance_si(:from_date,'a')  a where a.account = t0.acc_code ) end,0) saldo_inicial,";

			$saldo_cero = "(coalesce(case
							when t0.acc_level = 1
									then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1)
							when t0.acc_level = 2
									then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
							when t0.acc_level = 3
									then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3)
							when t0.acc_level = 4
									then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
							when t0.acc_level = 5
									then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.account = t0.acc_code )
							when t0.acc_level = 6
									then (select sum(a.sal_i) from balance_si(:from_date,'a')  a where a.account = t0.acc_code )
							end,0))";
				}else if(!empty($Data['saldo_inicial']) && $Data['saldo_inicial'] == 'S'){

					$saldo_inicial = '0 saldo_inicial,';
					$saldo_cero = 0;
				}

      	$sqlSelect = "SELECT
                    t0.acc_level,
                    t0.acc_code,
                    t0.acc_name,
					$saldo_inicial
                    case
                        when t0.acc_level = 1
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1) + $saldo_cero
                        when t0.acc_level = 2
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2) + $saldo_cero
                        when t0.acc_level = 3
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3) + $saldo_cero
                        when t0.acc_level = 4
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 ) + $saldo_cero
                        when t0.acc_level = 5
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code ) + $saldo_cero
                        when t0.acc_level = 6
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code ) + $saldo_cero
                    end saldo,
                    case
                        when t0.acc_level = 1
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
                        when t0.acc_level = 2
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
                        when t0.acc_level = 3
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3)
                        when t0.acc_level = 4
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
                        when t0.acc_level = 5
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
                        when t0.acc_level = 6
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code )
                    end debito,
                    case
                        when t0.acc_level = 1
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
                        when t0.acc_level = 2
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
                        when t0.acc_level = 3
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3)
                        when t0.acc_level = 4
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
                        when t0.acc_level = 5
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code   )
                        when t0.acc_level = 6
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code )
                    end credito
                from dacc t0
                where t0.acc_level <= :level $where
                GROUP by
					t0.acc_level,t0.acc_code,t0.acc_name,t0.acc_l1,t0.acc_level,t0.acc_l2,t0.acc_l3,t0.acc_l4,t0.acc_l5
                having (case
                        when t0.acc_level = 1
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
                        when t0.acc_level = 2
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
                        when t0.acc_level = 3
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3)
                        when t0.acc_level = 4
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
                        when t0.acc_level = 5
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code  )
                        when t0.acc_level = 6
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code  )
                    	end) <> 0
                order by cast(t0.acc_code as varchar) asc";

      	$resSelect = $this->pedeo->queryTable($sqlSelect, array(
        	':from_date' => $Data['from_date'],
        	':to_date' => $Data['to_date'],
        	':level' => $Data['level']
      	));


		}
		else if($Data['tipo'] == 'T'){

			$where_tercero = "";
		  	$tercero_sub = "";
			$tercero = "";
			$join_2 = "";
			if(!empty($Data['tercero'])){
				$where_tercero = "AND T1.tercero = '{$Data['tercero']}'";
				$tercero_sub = "AND a.tercero = '{$Data['tercero']}'";
				$join_2 = "left join dmsn t2 on t1.tercero = t2.dms_card_code";
				$tercero = "t1.tercero,t2.dms_card_name,";
			}

			$ver_doc = '';
			$where_sub_doc = '';
			$join_1 = "";
			if(!empty($Data['ver_doc']) && $Data['ver_doc'] == 'S'){
				$where_sub_doc = "AND a.num_doc = t1.num_doc";
				$join_1 = "left join (select * from balancess(:from_date,:to_date,'b')) t1 on t0.acc_code = t1.account";
				$ver_doc = "t1.td,t1.num_doc,";
			}
			$join_3 = "";
			if(empty($join_1)){
				$join_3 = "left join (select * from balancess(:from_date,:to_date,'b')) t1 on t0.acc_code = t1.account";
			}

			$saldo_inicial = 0;
			$saldo_cero = 0;
			if(!empty($Data['saldo_inicial']) && $Data['saldo_inicial'] != 'S'){
				$saldo_inicial = "coalesce(case
						when t0.acc_level = 1
								then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1)
						when t0.acc_level = 2
								then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
						when t0.acc_level = 3
								then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3)
						when t0.acc_level = 4
								then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
						when t0.acc_level = 5
								then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
						when t0.acc_level = 6
								then (select sum(a.sal_i) from balance_si(:from_date,'a')  a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
						end,0) saldo_inicial,";

				$saldo_cero = "(coalesce(case
						when t0.acc_level = 1
								then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1)
						when t0.acc_level = 2
								then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
						when t0.acc_level = 3
								then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3)
						when t0.acc_level = 4
								then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
						when t0.acc_level = 5
								then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
						when t0.acc_level = 6
								then (select sum(a.sal_i) from balance_si(:from_date,'a')  a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
				end,0))";
			}else if(!empty($Data['saldo_inicial']) && $Data['saldo_inicial'] == 'S'){
				$saldo_inicial = '0 saldo_inicial,';
				$saldo_cero = 0;
			}

			//SELECT CC,UN,PR
			$cc = '';
			$un = '';
			$pr = '';

			if(!empty($Data['cc']) && $Data['cc'] == 'S'){
				$cc = 't1.cc,';

			}
			//where cc
			$where_cc = '';

			if(!empty($Data['filter_cc'])){
				$where_cc = "AND t1.cc = '{$Data['filter_cc']}'";
			}


			if(!empty($Data['un']) && $Data['un'] == 'S'){
				$un = 't1.un,';

			}
			//where un
			$where_un = '';

			if(!empty($Data['filter_un'])){
				$where_un = "AND t1.cc = '{$Data['filter_un']}'";
			}

			if(!empty($Data['pr']) && $Data['pr'] == 'S'){
				$pr = 't1.pr,';

			}
			//where pr
			$where_pr = '';

			if(!empty($Data['filter_pr'])){
				$where_pr = "AND t1.cc = '{$Data['filter_pr']}'";
			}


			$sqlSelect = "SELECT
                    t0.acc_level,
                    t0.acc_code,
                    t0.acc_name,
                    t1.tercero,
                    t2.dms_card_name,
					$ver_doc
                    $cc
                    $un
                    $pr
					$saldo_inicial
                    case
                        when t0.acc_level = 1
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1) + $saldo_cero
                        when t0.acc_level = 2
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2) + $saldo_cero
                        when t0.acc_level = 3
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3) + $saldo_cero
                        when t0.acc_level = 4
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 ) + $saldo_cero
                        when t0.acc_level = 5
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code $tercero_sub $where_sub_doc) + $saldo_cero
                        when t0.acc_level = 6
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code $tercero_sub $where_sub_doc) + $saldo_cero
                    end saldo,
                    case
                        when t0.acc_level = 1
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
                        when t0.acc_level = 2
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
                        when t0.acc_level = 3
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3)
                        when t0.acc_level = 4
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
                        when t0.acc_level = 5
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                        when t0.acc_level = 6
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                    end debito,
                    case
                        when t0.acc_level = 1
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
                        when t0.acc_level = 2
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
                        when t0.acc_level = 3
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3)
                        when t0.acc_level = 4
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
                        when t0.acc_level = 5
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                        when t0.acc_level = 6
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                    end credito
                from dacc t0
				left join (select * from balancess(:from_date,:to_date,'b')) t1 on t0.acc_code = t1.account
				left join dmsn t2 on t1.tercero = t2.dms_card_code
                where t0.acc_level in ('5','6')
				$where_tercero
				$where_cc
				$where_un
				$where_pr
                GROUP by
					t0.acc_level,
					t0.acc_code,
					t0.acc_name,
					t1.tercero,
					t2.dms_card_name,
					$ver_doc
					$cc
					$un
					$pr
                	t0.acc_l1,
                	t0.acc_level,
					t0.acc_l2,
					t0.acc_l3,
					t0.acc_l4,
					t0.acc_l5
                having (case
                        when t0.acc_level = 1
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
                        when t0.acc_level = 2
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
                        when t0.acc_level = 3
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3)
                        when t0.acc_level = 4
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
                        when t0.acc_level = 5
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code $tercero_sub )
                        when t0.acc_level = 6
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code $tercero_sub )
                    	end) <> 0

                order by cast(t0.acc_code as varchar) asc";

			$resSelect = $this->pedeo->queryTable($sqlSelect, array(
				':from_date' => $Data['from_date'],
				':to_date' => $Data['to_date']
			));
// print_r($sqlSelect);exit();
}else if($Data['tipo'] == 'A'  && $Data['level'] <= 4){


	$saldo_inicial = 0;
	$saldo_cero = 0;
	if(!empty($Data['saldo_inicial']) && $Data['saldo_inicial'] != 'S'){
		$saldo_inicial = "coalesce(case
				when t0.acc_level = 1
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1)
				when t0.acc_level = 2
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
				when t0.acc_level = 3
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
								 and a.l3 = t0.acc_l3)
				when t0.acc_level = 4
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
								 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
				when t0.acc_level = 5
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.account = t0.acc_code  )
				when t0.acc_level = 6
						then (select sum(a.sal_i) from balance_si(:from_date,'a')  a where a.account = t0.acc_code  )
		end,0) saldo_inicial,";

		$saldo_cero = "(coalesce(case
				when t0.acc_level = 1
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1)
				when t0.acc_level = 2
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
				when t0.acc_level = 3
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
								 and a.l3 = t0.acc_l3)
				when t0.acc_level = 4
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
								 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
				when t0.acc_level = 5
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.account = t0.acc_code )
				when t0.acc_level = 6
						then (select sum(a.sal_i) from balance_si(:from_date,'a')  a where a.account = t0.acc_code  )
		end,0))";
	}else if(!empty($Data['saldo_inicial']) && $Data['saldo_inicial'] == 'S'){

		$saldo_inicial = '0 saldo_inicial,';
		$saldo_cero = 0;
	}


	$where = '';
	if(!empty($Data['account'])){
		$array = array_map(function($acc){
			return "'{$acc}'";
		}
			,$Data['account']);

		 $account = implode(',',$array);
		$where = "AND t0.acc_code IN ({$account})";
	}



	$sqlSelect = "SELECT
								t0.acc_level,
								t0.acc_code,
								t0.acc_name,
								$saldo_inicial
								case
										when t0.acc_level = 1
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
												+ $saldo_cero
										when t0.acc_level = 2
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
												+ $saldo_cero
										when t0.acc_level = 3
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
														 and a.l3 = t0.acc_l3)
												+ $saldo_cero
										when t0.acc_level = 4
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
														 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
												+ $saldo_cero
										when t0.acc_level = 5
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code   )
												+ $saldo_cero
										when t0.acc_level = 6
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code   )
												+ $saldo_cero
								end saldo,
								case
										when t0.acc_level = 1
												then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
										when t0.acc_level = 2
												then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
										when t0.acc_level = 3
												then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
														 and a.l3 = t0.acc_l3)
										when t0.acc_level = 4
												then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
														 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
										when t0.acc_level = 5
												then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code  )
										when t0.acc_level = 6
												then (select sum(a.deb) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code  )
								end debito,
								case
										when t0.acc_level = 1
												then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
										when t0.acc_level = 2
												then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
										when t0.acc_level = 3
												then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
														 and a.l3 = t0.acc_l3)
										when t0.acc_level = 4
												then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
														 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
										when t0.acc_level = 5
												then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code  )
										when t0.acc_level = 6
												then (select sum(a.cre) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code  )
								end credito
						from dacc t0
						where t0.acc_level <= :level $where
						GROUP by
						t0.acc_level,t0.acc_code,t0.acc_name,t0.acc_l1,t0.acc_level,t0.acc_l2,t0.acc_l3,t0.acc_l4,t0.acc_l5
						having (case
										when t0.acc_level = 1
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
										when t0.acc_level = 2
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
										when t0.acc_level = 3
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
														 and a.l3 = t0.acc_l3)
										when t0.acc_level = 4
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
														 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
										when t0.acc_level = 5
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code  )
										when t0.acc_level = 6
												then (select sum(a.sal) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code  )
								end) <> 0

						order by cast(t0.acc_code as varchar) asc";

	$resSelect = $this->pedeo->queryTable($sqlSelect, array(
		':from_date' => $Data['from_date'],
		':to_date' => $Data['to_date'],
		':level' => $Data['level']
	));


}else if($Data['tipo'] == 'A' && $Data['level'] >= 5){



		$where = '';
		$account_sub = '';
		if(!empty($Data['account'])){
			$array = array_map(function($acc){
				return "'{$acc}'";
			}
				,$Data['account']);
			 $account = implode(',',$array);
			$where = "AND t0.acc_code IN ({$account})";
			$account_sub = "AND a.account = ({$account})";
		}

		$where_tercero = "";
	  $tercero_sub = "";
		$tercero = "";
		$join_2 = "";
		if(!empty($Data['tercero'])){
			$where_tercero = "AND T1.tercero = '{$Data['tercero']}'";
			$tercero_sub = "AND a.tercero = '{$Data['tercero']}'";
			$join_2 = "left join dmsn t2 on t1.tercero = t2.dms_card_code";
			$tercero = "t1.tercero,t2.dms_card_name,";
		}

		$ver_doc = '';
		$where_sub_doc = '';
		$join_1 = "";
		if(!empty($Data['ver_doc']) && $Data['ver_doc'] == 'S'){
			$where_sub_doc = "AND a.num_doc = t1.num_doc";
			$join_1 = "left join (select * from balancess(:from_date,:to_date,'b')) t1 on t0.acc_code = t1.account";
			$ver_doc = "t1.td,t1.num_doc,";
		}
		$join_3 = "";
		if(empty($join_1)){
			$join_3 = "left join (select * from balancess(:from_date,:to_date,'b')) t1 on t0.acc_code = t1.account";
		}

	$saldo_inicial = 0;
	$saldo_cero = 0;
	if(!empty($Data['saldo_inicial']) && $Data['saldo_inicial'] != 'S'){
		$saldo_inicial = "coalesce(case
				when t0.acc_level = 1
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1)
				when t0.acc_level = 2
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
				when t0.acc_level = 3
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
								 and a.l3 = t0.acc_l3)
				when t0.acc_level = 4
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
								 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
				when t0.acc_level = 5
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
				when t0.acc_level = 6
						then (select sum(a.sal_i) from balance_si(:from_date,'a')  a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
		end,0) saldo_inicial,";

		$saldo_cero = "(coalesce(case
				when t0.acc_level = 1
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1)
				when t0.acc_level = 2
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
				when t0.acc_level = 3
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
								 and a.l3 = t0.acc_l3)
				when t0.acc_level = 4
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
								 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
				when t0.acc_level = 5
						then (select sum(a.sal_i) from balance_si(:from_date,'a') a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
				when t0.acc_level = 6
						then (select sum(a.sal_i) from balance_si(:from_date,'a')  a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
		end,0))";
	}else if(!empty($Data['saldo_inicial']) && $Data['saldo_inicial'] == 'S'){

		$saldo_inicial = '0 saldo_inicial,';
		$saldo_cero = 0;
	}

	//SELECT CC,UN,PR
	$cc = '';
	$un = '';
	$pr = '';

	if(!empty($Data['cc']) && $Data['cc'] == 'S'){
			$cc = 't1.cc,';

	}
	//where cc
	$where_cc = '';

	if(!empty($Data['filter_cc'])){
		$array_cc = implode(',',array($Data['filter_cc']));
		$where_cc = "AND t1.cc IN ({$array_cc})";
	}


	if(!empty($Data['un']) && $Data['un'] == 'S'){
		$un = 't1.un,';

	}
	//where un
	$where_un = '';

	if(!empty($Data['filter_un'])){
		$array_un = implode(',',array($Data['filter_un']));
		$where_un = "AND t1.cc IN ({$array_un})";
	}

	if(!empty($Data['pr']) && $Data['pr'] == 'S'){
		$pr = 't1.pr,';

	}
	//where pr
	$where_pr = '';

	if(!empty($Data['filter_pr'])){
		$array_pr = implode(',',array($Data['filter_pr']));
		$where_pr = "AND t1.cc IN ({$array_pr})";
	}
// print_r($where);exit();
	$sqlSelect = "SELECT
                    t0.acc_level,
                    t0.acc_code,
                    t0.acc_name,
                    $tercero
										$cc
										$un
										$pr
                    $ver_doc
										$saldo_inicial
                    case
                        when t0.acc_level = 1
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
                            + $saldo_cero
                        when t0.acc_level = 2
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
                            + $saldo_cero
                        when t0.acc_level = 3
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
                                 and a.l3 = t0.acc_l3)
                            + $saldo_cero
                        when t0.acc_level = 4
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
                                 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
                            + $saldo_cero
                        when t0.acc_level = 5
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                            + $saldo_cero
                        when t0.acc_level = 6
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                            + $saldo_cero
                    end saldo,
                    case
                        when t0.acc_level = 1
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
                        when t0.acc_level = 2
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
                        when t0.acc_level = 3
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
                                 and a.l3 = t0.acc_l3)
                        when t0.acc_level = 4
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
                                 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
                        when t0.acc_level = 5
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                        when t0.acc_level = 6
                            then (select sum(a.deb) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                    end debito,
                    case
                        when t0.acc_level = 1
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
                        when t0.acc_level = 2
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
                        when t0.acc_level = 3
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
                                 and a.l3 = t0.acc_l3)
                        when t0.acc_level = 4
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
                                 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
                        when t0.acc_level = 5
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                        when t0.acc_level = 6
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                    end credito
                from dacc t0
								$join_1
								$join_3
								$join_2
                where t0.acc_level  >= :level
								$where
								$where_tercero
								$where_cc
								$where_un
								$where_pr
                GROUP by
								t0.acc_level,
								t0.acc_code,
								t0.acc_name,
								$tercero
								$cc
								$un
								$pr
                $ver_doc
                t0.acc_l1,
                t0.acc_level,
                t0.acc_l2,
                t0.acc_l3,
                t0.acc_l4,
                t0.acc_l5
                having (case
                        when t0.acc_level = 1
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1)
                        when t0.acc_level = 2
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2)
                        when t0.acc_level = 3
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
                                 and a.l3 = t0.acc_l3)
                        when t0.acc_level = 4
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
                                 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
                        when t0.acc_level = 5
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                        when t0.acc_level = 6
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code $tercero_sub $where_sub_doc)
                    end) <> 0

                order by cast(t0.acc_code as varchar) asc";

	$resSelect = $this->pedeo->queryTable($sqlSelect, array(
		':from_date' => $Data['from_date'],
		':to_date' => $Data['to_date'],
		':level' => $Data['level']
	));

// print_r($sqlSelect);exit;
}
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
