<?php
// KPI MODULO DE HOME
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class HomeKpi extends REST_Controller {

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

    // REPORTE DE VENTAS
    public function salesKpi_post(){

        $Data = $this->post();

        if ( !isset($Data['business']) ){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
                );
    
            return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
        }
 
        // INGRESOS
        $sqlDMA = "SELECT coalesce(ROUND(SUM(dvf_baseamnt/1000000),2),0.00)::TEXT||' M' as monto,
                    coalesce(Round(((SUM(dvf_baseamnt) / (SELECT SUM(dvf_baseamnt) FROM dvfv WHERE business = :business  AND dvf_docdate = current_date - INTERVAL '1 day')) - 1) * 100, 2),0.00) as diff
                    FROM dvfv
                    WHERE dvf_docdate = current_date
                    AND business = :business 
                    
                    UNION ALL
                    
                    SELECT coalesce(ROUND(SUM(dvf_baseamnt/1000000),2),0.00)::TEXT||' M' as monto,
                    coalesce(round((SUM(dvf_baseamnt) / (SELECT SUM(dvf_baseamnt) FROM dvfv
                        WHERE business = :business  AND date_trunc('month', dvf_docdate) = date_trunc('month', current_date - interval '1 month')
                        AND EXTRACT(YEAR FROM dvf_docdate) = EXTRACT(YEAR FROM current_date)) -1) * 100, 2),0.00) as diff
                    FROM dvfv
                    WHERE EXTRACT(MONTH FROM dvf_docdate) = EXTRACT(MONTH FROM current_date) 
                    AND EXTRACT(YEAR FROM dvf_docdate) = EXTRACT(YEAR FROM current_date) 
                    AND business = :business 
                    
                    UNION ALL
                    
                    SELECT coalesce(ROUND(SUM(dvf_baseamnt/1000000),2),0.00)::TEXT||' M' as monto,
                        coalesce(round(((SUM(dvf_baseamnt) / (SELECT SUM(dvf_baseamnt) FROM dvfv
                        WHERE business = :business  AND date_trunc('year', dvf_docdate) = date_trunc('year', current_date - interval '1 year'))) -1) * 100, 2), 100) as diff 
                    FROM dvfv
                    WHERE EXTRACT(YEAR FROM dvf_docdate) = EXTRACT(YEAR FROM current_date) 
                    AND business = :business ";
        //
        $sqlANO =   "SELECT COALESCE(ROUND(SUM(dvf_baseamnt/1000000),2), 0.00)::numeric AS monto, nombres_meses.nombre_mes
                    FROM generate_series(1, EXTRACT(MONTH FROM current_date)::INT) AS meses(mes)
                    LEFT JOIN (
                        SELECT 1 AS mes_numero, 'Enero' AS nombre_mes UNION ALL
                        SELECT 2, 'Febrero' UNION ALL
                        SELECT 3, 'Marzo' UNION ALL
                        SELECT 4, 'Abril' UNION ALL
                        SELECT 5, 'Mayo' UNION ALL
                        SELECT 6, 'Junio' UNION ALL
                        SELECT 7, 'Julio' UNION ALL
                        SELECT 8, 'Agosto' UNION ALL
                        SELECT 9, 'Septiembre' UNION ALL
                        SELECT 10, 'Octubre' UNION ALL
                        SELECT 11, 'Noviembre' UNION ALL
                        SELECT 12, 'Diciembre'
                        ) AS nombres_meses ON meses.mes = nombres_meses.mes_numero
                        LEFT JOIN (
                            SELECT EXTRACT(MONTH FROM dvf_docdate) AS mes, dvf_baseamnt
                            FROM dvfv
                            WHERE business = :business
                        ) AS subconsulta ON meses.mes = subconsulta.mes
                        GROUP BY meses.mes, nombres_meses.nombre_mes
                        ORDER BY meses.mes";


        //
        $sqlDM = "SELECT ROUND(SUM(dvf_baseamnt/1000000),2) AS MONTO, (EXTRACT(DAY FROM dvf_docdate)::TEXT ||'/'|| EXTRACT(MONTH FROM dvf_docdate))::TEXT AS DIA 
                    FROM dvfv
                    WHERE business = :business
                    AND EXTRACT(MONTH FROM dvf_docdate) = EXTRACT(MONTH FROM current_date) 
                    AND EXTRACT(YEAR FROM dvf_docdate) = EXTRACT(YEAR FROM current_date) 
                    GROUP BY dvf_docdate";

        //
        // DEVOLUCIONES
        $sqlDMAD = "SELECT coalesce(ROUND(SUM(vnc_baseamnt/1000000),2),0.00)::TEXT||' M' as monto,
            coalesce(Round(((SUM(vnc_baseamnt) / (SELECT SUM(vnc_baseamnt) FROM dvnc WHERE business = :business  AND vnc_docdate = current_date - INTERVAL '1 day')) - 1) * 100, 2),0.00) as diff
            FROM dvnc
            WHERE vnc_docdate = current_date
            AND business = :business

            UNION ALL

            SELECT coalesce(ROUND(SUM(vnc_baseamnt/1000000),2),0.00)::TEXT||' M' as monto,
            coalesce(round((SUM(vnc_baseamnt) / (SELECT SUM(vnc_baseamnt) FROM dvnc
                WHERE business = :business  AND date_trunc('month', vnc_docdate) = date_trunc('month', current_date - interval '1 month')
                AND EXTRACT(YEAR FROM vnc_docdate) = EXTRACT(YEAR FROM current_date)) -1) * 100, 2),0.00) as diff
            FROM dvnc
            WHERE EXTRACT(MONTH FROM vnc_docdate) = EXTRACT(MONTH FROM current_date) 
            AND EXTRACT(YEAR FROM vnc_docdate) = EXTRACT(YEAR FROM current_date) 
            AND business = :business

            UNION ALL

            SELECT coalesce(ROUND(SUM(vnc_baseamnt/1000000),2),0.00)::TEXT||' M' as monto,
                coalesce(round(((SUM(vnc_baseamnt) / (SELECT SUM(vnc_baseamnt) FROM dvnc
                WHERE business = :business  AND date_trunc('year', vnc_docdate) = date_trunc('year', current_date - interval '1 year'))) -1) * 100, 2), 100) as diff 
            FROM dvnc
            WHERE EXTRACT(YEAR FROM vnc_docdate) = EXTRACT(YEAR FROM current_date) 
            AND business = :business";
        //
        $sqlANOD = "SELECT COALESCE(ROUND(SUM(vnc_baseamnt/1000000),2), 0.00)::numeric AS monto, nombres_meses.nombre_mes
                    FROM generate_series(1, EXTRACT(MONTH FROM current_date)::INT) AS meses(mes)
                    LEFT JOIN (
                        SELECT 1 AS mes_numero, 'Enero' AS nombre_mes UNION ALL
                        SELECT 2, 'Febrero' UNION ALL
                        SELECT 3, 'Marzo' UNION ALL
                        SELECT 4, 'Abril' UNION ALL
                        SELECT 5, 'Mayo' UNION ALL
                        SELECT 6, 'Junio' UNION ALL
                        SELECT 7, 'Julio' UNION ALL
                        SELECT 8, 'Agosto' UNION ALL
                        SELECT 9, 'Septiembre' UNION ALL
                        SELECT 10, 'Octubre' UNION ALL
                        SELECT 11, 'Noviembre' UNION ALL
                        SELECT 12, 'Diciembre'
                        ) AS nombres_meses ON meses.mes = nombres_meses.mes_numero
                        LEFT JOIN (
                            SELECT EXTRACT(MONTH FROM vnc_docdate) AS mes, vnc_baseamnt
                            FROM dvnc
                            WHERE business = :business
                        ) AS subconsulta ON meses.mes = subconsulta.mes
                        GROUP BY meses.mes, nombres_meses.nombre_mes
                        ORDER BY meses.mes";
        //
        $sqlDMD = "SELECT ROUND(SUM(vnc_baseamnt/1000000),2) AS MONTO, (EXTRACT(DAY FROM vnc_docdate)::TEXT ||'/'|| EXTRACT(MONTH FROM vnc_docdate))::TEXT AS DIA 
                    FROM dvnc
                    WHERE business = :business
                    AND EXTRACT(MONTH FROM vnc_docdate) = EXTRACT(MONTH FROM current_date) 
                    AND EXTRACT(YEAR FROM vnc_docdate) = EXTRACT(YEAR FROM current_date) 
                    GROUP BY vnc_docdate";
        //

        // INGRESOS
        $resDMA = $this->pedeo->queryTable($sqlDMA, array(':business' => $Data['business']));
        $resANO = $this->pedeo->queryTable($sqlANO, array(':business' => $Data['business']));
        $resDM  = $this->pedeo->queryTable($sqlDM,  array(':business'  => $Data['business']));

        // DEVOLUCIONES
        $resDMAD = $this->pedeo->queryTable($sqlDMAD, array(':business' => $Data['business']));
        $resANOD = $this->pedeo->queryTable($sqlANOD, array(':business' => $Data['business']));
        $resDMD  = $this->pedeo->queryTable($sqlDMD,  array(':business'  => $Data['business']));


        $respuesta = array(
            'error'     => false,
            'data'      => array([$resDMA, $resANO, $resDM], [$resDMAD,$resANOD,$resDMD]),
            'mensaje'   => ''
            );


        $this->response($respuesta);

    }

    // REPORTE DE COMPRAS
    public function purchKpi_post(){

        $Data = $this->post();

        if ( !isset($Data['business']) ){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'La informacion enviada no es valida'
                );
    
            return $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
        }

        $sqlDMA = "SELECT coalesce(ROUND(SUM(cfc_baseamnt/1000000),2),0.00)::TEXT||' M' as monto,
                    coalesce(Round(((SUM(cfc_baseamnt) / (SELECT SUM(cfc_baseamnt) FROM dcfc WHERE business = :business  AND cfc_docdate = current_date - INTERVAL '1 day')) - 1) * 100, 2),0.00) as diff
                    FROM dcfc
                    WHERE cfc_docdate = current_date
                    AND business = :business 
                    
                    UNION ALL
                    
                    SELECT coalesce(ROUND(SUM(cfc_baseamnt/1000000),2),0.00)::TEXT||' M' as monto,
                    coalesce(round((SUM(cfc_baseamnt) / (SELECT SUM(cfc_baseamnt) FROM dcfc
                        WHERE business = :business  AND date_trunc('month', cfc_docdate) = date_trunc('month', current_date - interval '1 month')
                        AND EXTRACT(YEAR FROM cfc_docdate) = EXTRACT(YEAR FROM current_date)) -1) * 100, 2),0.00) as diff
                    FROM dcfc
                    WHERE EXTRACT(MONTH FROM cfc_docdate) = EXTRACT(MONTH FROM current_date) 
                    AND EXTRACT(YEAR FROM cfc_docdate) = EXTRACT(YEAR FROM current_date) 
                    AND business = :business 
                    
                    UNION ALL
                    
                    SELECT coalesce(ROUND(SUM(cfc_baseamnt/1000000),2),0.00)::TEXT||' M' as monto,
                        coalesce(round(((SUM(cfc_baseamnt) / (SELECT SUM(cfc_baseamnt) FROM dcfc
                        WHERE business = :business  AND date_trunc('year', cfc_docdate) = date_trunc('year', current_date - interval '1 year'))) -1) * 100, 2), 100) as diff 
                    FROM dcfc
                    WHERE EXTRACT(YEAR FROM cfc_docdate) = EXTRACT(YEAR FROM current_date) 
                    AND business = :business ";

        $sqlANO =   "SELECT COALESCE(ROUND(SUM(cfc_baseamnt/1000000),2), 0.00)::numeric AS monto, nombres_meses.nombre_mes
                        FROM generate_series(1, EXTRACT(MONTH FROM current_date)::INT) AS meses(mes)
                        LEFT JOIN (
                          SELECT 1 AS mes_numero, 'Enero' AS nombre_mes UNION ALL
                          SELECT 2, 'Febrero' UNION ALL
                          SELECT 3, 'Marzo' UNION ALL
                          SELECT 4, 'Abril' UNION ALL
                          SELECT 5, 'Mayo' UNION ALL
                          SELECT 6, 'Junio' UNION ALL
                          SELECT 7, 'Julio' UNION ALL
                          SELECT 8, 'Agosto' UNION ALL
                          SELECT 9, 'Septiembre' UNION ALL
                          SELECT 10, 'Octubre' UNION ALL
                          SELECT 11, 'Noviembre' UNION ALL
                          SELECT 12, 'Diciembre'
                        ) AS nombres_meses ON meses.mes = nombres_meses.mes_numero
                        LEFT JOIN (
                          SELECT EXTRACT(MONTH FROM cfc_docdate) AS mes, cfc_baseamnt
                          FROM dcfc
                          WHERE business = :business
                        ) AS subconsulta ON meses.mes = subconsulta.mes
                        GROUP BY meses.mes, nombres_meses.nombre_mes
                        ORDER BY meses.mes";

        $sqlDM = "SELECT ROUND(SUM(cfc_baseamnt/1000000),2) AS MONTO, (EXTRACT(DAY FROM cfc_docdate)::TEXT ||'/'|| EXTRACT(MONTH FROM cfc_docdate))::TEXT AS DIA 
                    FROM dcfc
                    WHERE business = :business
                    AND EXTRACT(MONTH FROM cfc_docdate) = EXTRACT(MONTH FROM current_date) 
                    AND EXTRACT(YEAR FROM cfc_docdate) = EXTRACT(YEAR FROM current_date) 
                    GROUP BY cfc_docdate";


        $resDMA = $this->pedeo->queryTable($sqlDMA, array(':business' => $Data['business']));
        $resANO = $this->pedeo->queryTable($sqlANO, array(':business' => $Data['business']));
        $resDM  = $this->pedeo->queryTable($sqlDM,  array(':business'  => $Data['business']));

      

        $respuesta = array(
            'error'     => false,
            'data'      => [$resDMA, $resANO, $resDM],
            'mensaje'   => ''
            );


        $this->response($respuesta);

    }
 
}
