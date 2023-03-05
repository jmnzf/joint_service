<?php
// REPORTES DE INVENTARIO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Kpi extends REST_Controller {

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

	public function payments_post(){
        
        $request = $this->post();

        if(!isset($request['business'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'No esta enviando el parametro de la empresa'
            );
            return $this->response($respuesta);
        }

        //CONSULTA
        $sql = "SELECT
                    'pr' as tipo,
                    extract(month from bpr_docdate) as mes ,
                    extract(year from bpr_docdate) as year,
                    sum(bpr_doctotal) / 1000000 as total
                from gbpr
                where business = :business
                group by mes,year
                union all
                select
                    'pe' as tipo,
                    extract(month from bpe_docdate) as mes ,
                    extract(year from bpe_docdate) as year,
                    sum(bpe_doctotal) / 1000000 as total
                from gbpe where business = :business
                group by mes,year
                UNION ALL
                select
                    'df' as tipo,
                    pr.mes,
                    pr.year,
                    coalesce((pr.total - pe.total),0) as total
                from (SELECT
                        'pr' as tipo,
                        extract(month from gbpr.bpr_docdate) as mes ,
                        extract(year from gbpr.bpr_docdate) as year,
                        sum(gbpr.bpr_doctotal) / 1000000 as total
                    from gbpr
                    where business = :business
                    group by mes,year) pr
                inner join (select
                                'pe' as tipo,
                                extract(month from bpe_docdate) as mes ,
                                extract(year from bpe_docdate) as year,
                                sum(bpe_doctotal) / 1000000 as total
                            from gbpe where business = :business
                            group by mes,year) pe on pr.mes = pe.mes and pr.year = pe.year
                order by mes,year asc;";

        $ResSql = $this->pedeo->queryTable($sql,array(
            ':business' => $request['business']
        ));
                

        if(isset($ResSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $ResSql,
                'mensaje' => 'OK'
            );
        }

        $this->response($respuesta);
    }

    public function invoices_post(){
        
        $request = $this->post();

        if(!isset($request['business'])){
            $respuesta = array(
                'error' => true,
                'data' => [],
                'mensaje' => 'No esta enviando el parametro de la empresa'
            );
            return $this->response($respuesta);
        }

        //CONSULTA
        $sql = "SELECT
                    'cl' as tipo,
                    extract(month from dvfv.dvf_docdate) as mes,
                    extract(year from dvfv.dvf_docdate) as año,
                    sum(dvfv.dvf_doctotal) as valor
                from dvfv
                where extract(month from dvfv.dvf_docdate) <= extract(month from current_date) and dvfv.business = :business
                group by mes,año
                union all
                select
                    'pr' as tipo,
                    extract(month from dcfc.cfc_docdate) as mes,
                    extract(year from dcfc.cfc_docdate) as año,
                    sum(dcfc.cfc_doctotal)  as valor
                from dcfc
                where extract(month from dcfc.cfc_docdate) <= extract(month from current_date) and dcfc.business = :business
                group by mes,año";

        $ResSql = $this->pedeo->queryTable($sql,array(
            ':business' => $request['business']
        ));
                

        if(isset($ResSql[0])){
            $respuesta = array(
                'error' => false,
                'data' => $ResSql,
                'mensaje' => 'OK'
            );
        }

        $this->response($respuesta);
    }

}
