<?php
defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PdfPyg extends REST_Controller{

    private $pdo;

	public function __construct(){

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    	$this->load->library('pedeo', [$this->pdo]);
		$this->load->library('DateFormat');
		$this->load->library('generic');

	}
    public function PdfPyg_post(){

		$formatter = new NumeroALetras();
		$DECI_MALES =  $this->generic->getDecimals();
        $request = $this->post();
		// $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch']);
        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch','default_font' => 'dejavusans']);

		$Type = "";
		//RUTA DE CARPETA EMPRESA
        $company = $this->pedeo->queryTable("SELECT main_folder company FROM PARAMS",array());

        if(!isset($company[0])){
			$respuesta = array(
		         'error' => true,
		        'data'  => $company,
		        'mensaje' =>'no esta registrada la ruta de la empresa'
		    );

	        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	        return;
		}

        //INFORMACION DE LA EMPRESA
		$sqlEmpresa = "SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
		pge_cou_soc, pge_id_soc AS pge_id_type , pge_web_site, pge_logo,
		CONCAT(pge_cel) AS pge_phone1, pge_branch, pge_mail,
		pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type,pge_id_soc,pge_phone2,pge_page_social
		FROM pgem WHERE pge_id = :pge_id";

		$empresa = $this->pedeo->queryTable($sqlEmpresa, array(':pge_id' => $request['business']));

		if(!isset($empresa[0])){
			$respuesta = array(
				'error' => true,
		        'data'  => $empresa,
	       		'mensaje' =>'no esta registrada la información de la empresa'
			);

	         $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	        return;
		}

		//CONSULTA PARA OBTENER NOMBRE DE DOCUMENTO
		$sql = "SELECT
                    'adm' AS tipo,
                    t0.acc_code AS cuenta,
                    t0.acc_name AS name_cuenta,
                    COALESCE(ABS(SUM(t1.ac1_debit - t1.ac1_credit)),0) AS total
                from dacc t0
                LEFT JOIN mac1 t1 on t0.acc_code = t1.ac1_account
                WHERE CAST(t0.acc_code AS VARCHAR) LIKE '5105%'
                GROUP BY t0.acc_code,t0.acc_name
                union all
                SELECT
                    'venta' AS tipo,
                    t0.acc_code AS cuenta,
                    t0.acc_name AS name_cuenta,
                    COALESCE(ABS(SUM(t1.ac1_debit - t1.ac1_credit)),0) AS total
                from dacc t0
                LEFT JOIN mac1 t1 on t0.acc_code = t1.ac1_account
                WHERE CAST(t0.acc_code AS VARCHAR) LIKE '5205%'
                GROUP BY t0.acc_code,t0.acc_name
                union all
                SELECT
                    'financiero' AS tipo,
                    t0.acc_code AS cuenta,
                    t0.acc_name AS name_cuenta,
                    COALESCE(ABS(SUM(t1.ac1_debit - t1.ac1_credit)),0) AS total
                from dacc t0
                LEFT JOIN mac1 t1 on t0.acc_code = t1.ac1_account
                WHERE CAST(t0.acc_code AS VARCHAR) LIKE '5305%'
                GROUP BY t0.acc_code,t0.acc_name
                --INGRESOS
                union all
                SELECT
                    'c_venta' AS tipo,
                    t0.acc_code AS cuenta,
                    t0.acc_name AS name_cuenta,
                    COALESCE(ABS(SUM(t1.ac1_debit - t1.ac1_credit)),0) AS total
                from dacc t0
                LEFT JOIN mac1 t1 on t0.acc_code = t1.ac1_account
                WHERE CAST(t0.acc_code AS VARCHAR) LIKE '1435%'
                GROUP BY t0.acc_code,t0.acc_name
                union all
                SELECT
                    'i_operativos' AS tipo,
                    t0.acc_code AS cuenta,
                    t0.acc_name AS name_cuenta,
                    COALESCE(ABS(SUM(t1.ac1_debit - t1.ac1_credit)),0) AS total
                from dacc t0
                LEFT JOIN mac1 t1 on t0.acc_code = t1.ac1_account
                WHERE CAST(t0.acc_code AS VARCHAR) LIKE '5305%'
                GROUP BY t0.acc_code,t0.acc_name
                union all
                --OTROS INGRESOS
                SELECT
                    'i_no_operativos' AS tipo,
                    t0.acc_code AS cuenta,
                    t0.acc_name AS name_cuenta,
                    COALESCE(ABS(SUM(t1.ac1_debit - t1.ac1_credit)),0) AS total
                from dacc t0
                LEFT JOIN mac1 t1 on t0.acc_code = t1.ac1_account
                WHERE CAST(t0.acc_code AS VARCHAR) LIKE '5305%'
                GROUP BY t0.acc_code,t0.acc_name";

				$resSql = $this->pedeo->queryTable($sql,array());

				if(!isset($resSql[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $resSql,
							 'mensaje' =>'no se encontro el documento'
						);

						return $respuesta;
				}


				$mostrar = '';
				foreach ($resSql as $key => $value) {
					
                    if($value['tipo'] == "adm"){
                        $ingresos = '<table  width="100%" font-family: serif>
                        <tr>
                            <th style="text-align: left;"><b>GASTOS DE OPERACION</b><th>
                        </tr>
                        <tr>
                            <th style="text-align: left;"><b>DE ADMINISTRACION</b><th>
                        </tr>
                        </table>
                        <table  width="100%" font-family: serif>
                        <tr width="60" width="60">
                            <th style="text-align: center;" ><b>'.$value['cuenta'].'</b><span> </span></p></th>
                            <th style="text-align: center;"><b>nombre :</b> <span></span></p></th>
                        </tr>
                    </table>
                    <table width="100%" style="vertical-align: bottom; font-family: serif;font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
                        <tr>
                            <th class="fondo">
                                <p></p>
                            </th>
                        </tr>
                    </table>
                    <table class="borde" style="width:100%">				
                    </table>';
                    }

                    $mostrar = $mostrar.$ingresos;
				}


        $header = '
				<table width="100%" style="text-align: left;">
        			<tr>
            			<th style="text-align: left;" width ="50">
							<img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" 
							width ="185" height ="120"></img>
						</th>
            			<th>
							<p><b>'.$empresa[0]['pge_name_soc'].'</b></p>
							<p><b>'.$empresa[0]['pge_id_type'].'</b></p>
							<p><b>'.$empresa[0]['pge_add_soc'].'</b></p>
						</th>
					/tr>
				</table>';

		$footer = '
        	<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th  width="33%" style="font-size: 8px;"> Documento generado por <span style="color: orange; font-weight: bolder;"> Joint ERP  </span> para: '.$empresa[0]['pge_small_name'].'. Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            	</tr>
        	</table>';


        $html = ''.$ingresos.'</html>';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');
		$mpdf->SetDefaultBodyCSS('background', "url('/var/www/html/".$company[0]['company']."/assets/img/W-background.png')");
        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);

        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

		$filename = 'PYG.pdf';
        $mpdf->Output($filename,'D');

		header('Content-type: application/force-download');
		header('Content-Disposition: attachment; filename='.$filename);
	}
}