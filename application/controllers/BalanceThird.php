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
      $sqlSelect = "SELECT
										'' tercero,
                    t0.acc_level,t1.levels,
                    t0.acc_code,
                    t0.acc_name,
										0 saldo_i,
                    t1.cc,
                    t1.un,
                    t1.pr,
                   t1.td,
                    t1.numero_doc,
                    case
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
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
                        when t0.acc_level = 6
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code )
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
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
                                 and a.l3 = t0.acc_l3)
                        when t0.acc_level = 4
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.l1 = t0.acc_l1 and a.l2 = t0.acc_l2
                                 and a.l3 = t0.acc_l3 and a.l4 = t0.acc_l4 )
                        when t0.acc_level = 5
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
                        when t0.acc_level = 6
                            then (select sum(a.cre) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code )
                    end credito
                from dacc t0
				left join (select  a.* from balancess(:from_date,:to_date,'b') a ) t1 on cast(t0.acc_level as varchar) = t1.levels
                where t0.acc_level <= :level
                GROUP by t0.acc_level,t0.acc_code,t0.acc_name,t0.acc_l1,t0.acc_level,t0.acc_l2,t0.acc_l3,t0.acc_l4,t0.acc_l5,
                         t1.cc,tercero,
                    t1.un,
                    t1.pr,
                    t1.td,
                    t1.numero_doc,
                    t1.levels
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
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
                        when t0.acc_level = 6
                            then (select sum(a.sal) from balancess(:from_date,:to_date,'b')  a where a.account = t0.acc_code )
                    end) <> 0

                order by cast(t0.acc_code as varchar) asc";

      $resSelect = $this->pedeo->queryTable($sqlSelect, array(
        ':from_date' => $Data['from_date'],
        ':to_date' => $Data['to_date'],
        ':level' => $Data['level']
      ));
		}
		else if($Data['tipo'] == 'T' && (is_null($Data['tercero']) OR (isset($Data['tercero'])))){

			$where = '';
			if(!empty($Data['tercero'])){
				$where = "AND T1.tercero = '{$Data['tercero']}'";
			}

			$sqlSelect = "SELECT
										t1.tercero,
										t2.dms_card_name,
										t0.acc_level,
										t0.acc_code,
										t0.acc_name,
										case
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
										then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
										when t0.acc_level = 6
										then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
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
										then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
										when t0.acc_level = 6
										then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
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
										then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
										when t0.acc_level = 6
										then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
										end credito
										from dacc t0
										left join (select tercero,account from balancess(:from_date,:to_date,'b')) t1 on t0.acc_code = t1.account
										left join dmsn t2 on t1.tercero = t2.dms_card_code
										where t0.acc_level in ('5','6') $where
										GROUP by t1.tercero,t2.dms_card_name,
											t0.acc_level,t0.acc_code,t0.acc_name,t0.acc_l1,t0.acc_level,t0.acc_l2,t0.acc_l3,t0.acc_l4,t0.acc_l5 having (case
											when t0.acc_level=1 then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1=t0.acc_l1) when
											t0.acc_level=2 then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1=t0.acc_l1 and
											a.l2=t0.acc_l2) when t0.acc_level=3 then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where
											a.l1=t0.acc_l1 and a.l2=t0.acc_l2 and a.l3=t0.acc_l3) when t0.acc_level=4 then (select sum(a.sal) from
											balancess(:from_date,:to_date,'b') a where a.l1=t0.acc_l1 and a.l2=t0.acc_l2 and a.l3=t0.acc_l3 and a.l4=t0.acc_l4 )
											when t0.acc_level=5 then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account=t0.acc_code )
											when t0.acc_level=6 then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account=t0.acc_code )
											end) <> 0

											order by cast(t0.acc_code as varchar) asc";

			$resSelect = $this->pedeo->queryTable($sqlSelect, array(
				':from_date' => $Data['from_date'],
				':to_date' => $Data['to_date']
			));
// print_r($sqlSelect);exit();
}else if ($Data['tipo'] == 'T' && (is_null($Data['cuenta']) OR (isset($Data['cuenta'])))){

	$where = '';
	if(!empty($Data['cuenta'])){
		$where = "AND T0.acc_code = '{$Data['cuenta']}'";
	}

	$sqlSelect = "SELECT
								t1.tercero,
								t2.dms_card_name,
								t0.acc_level,
								t0.acc_code,
								t0.acc_name,
								case
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
								then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
								when t0.acc_level = 6
								then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
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
								then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
								when t0.acc_level = 6
								then (select sum(a.deb) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
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
								then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
								when t0.acc_level = 6
								then (select sum(a.cre) from balancess(:from_date,:to_date,'b') a where a.account = t0.acc_code )
								end credito
								from dacc t0
								left join (select tercero,account from balancess(:from_date,:to_date,'b')) t1 on t0.acc_code = t1.account
								left join dmsn t2 on t1.tercero = t2.dms_card_code
								where t0.acc_level in ('5','6') $where
								GROUP by t1.tercero,t2.dms_card_name,
									t0.acc_level,t0.acc_code,t0.acc_name,t0.acc_l1,t0.acc_level,t0.acc_l2,t0.acc_l3,t0.acc_l4,t0.acc_l5 having (case
									when t0.acc_level=1 then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1=t0.acc_l1) when
									t0.acc_level=2 then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.l1=t0.acc_l1 and
									a.l2=t0.acc_l2) when t0.acc_level=3 then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where
									a.l1=t0.acc_l1 and a.l2=t0.acc_l2 and a.l3=t0.acc_l3) when t0.acc_level=4 then (select sum(a.sal) from
									balancess(:from_date,:to_date,'b') a where a.l1=t0.acc_l1 and a.l2=t0.acc_l2 and a.l3=t0.acc_l3 and a.l4=t0.acc_l4 )
									when t0.acc_level=5 then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account=t0.acc_code )
									when t0.acc_level=6 then (select sum(a.sal) from balancess(:from_date,:to_date,'b') a where a.account=t0.acc_code )
									end) <> 0

									order by cast(t0.acc_code as varchar) asc";

	$resSelect = $this->pedeo->queryTable($sqlSelect, array(
		':from_date' => $Data['from_date'],
		':to_date' => $Data['to_date']
	));


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
