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
               
                $det_adm = "";
                $total_det_adm = 0;
                $det_venta = "";
                $total_det_venta = 0;
                $det_financiero = "";
                $total_det_financiero = 0;
                $det_ingresos = "";
                $total_det_ingresos = 0;
                $det_operativos = "";
                $total_det_operativos = 0;
                $det_otros_ingresos = "";
                $total_det_otros_ingresos = 0;
                //MOSTRAR CABECERA
                    $sql_adm = $this->sql("adm");
                    foreach ($sql_adm as $key => $value) {
                        $detail = '
                            <tr >
                                <td>&nbsp;</td>
                                <td  style="text-align: center;">'.$value['name_cuenta'].'</td>
                                <td style="text-align: rigth;">$'.number_format($value['total'],$DECI_MALES,',','.').'</td>
                            </tr>
                        ';
                        $det_adm = $det_adm.$detail;
                        $total_det_adm += $value['total'];
                    }

                    $sql_venta = $this->sql("venta");
                    foreach ($sql_venta as $key => $value) {
                        $detail = '
                            <tr >
                                <td>&nbsp;</td>
                                <td style="text-align: center;">'.$value['name_cuenta'].'</td>
                                <td style="text-align: rigth;">$'.number_format($value['total'],$DECI_MALES,',','.').'</td>
                            </tr>
                        ';
                        $det_venta = $det_venta.$detail;
                        $total_det_venta += $value['total'];
                    }

                    $sql_financiero = $this->sql("financiero");
                    foreach ($sql_financiero as $key => $value) {
                        $detail = '
                            <tr >
                                <td>&nbsp;</td>
                                <td style="text-align: center;">'.$value['name_cuenta'].'</td>
                                <td style="text-align: rigth;">$'.number_format($value['total'],$DECI_MALES,',','.').'</td>
                            </tr>
                        ';
                        $det_financiero = $det_financiero.$detail;
                        $total_det_financiero += $value['total'];
                        
                    }

                    $sql_ingresos = $this->sql("c_venta");
                    foreach ($sql_ingresos as $key => $value) {
                        $detail = '
                            <tr >
                                <td>&nbsp;</td>
                                <td style="text-align: center;">'.$value['name_cuenta'].'</td>
                                <td style="text-align: rigth;">$'.number_format($value['total'],$DECI_MALES,',','.').'</td>
                            </tr>
                        ';
                        $det_ingresos = $det_ingresos.$detail;
                        $total_det_ingresos += $value['total'];
                        
                    }

                    $sql_operativos = $this->sql("i_operativos");
                    foreach ($sql_operativos as $key => $value) {
                        $detail = ' 
                            <tr>
                                <td>&nbsp;</td>
                                <td style="text-align: center;">'.$value['name_cuenta'].'</td>
                                <td style="text-align: rigth;">$'.number_format($value['total'],$DECI_MALES,',','.').'</td>
                            </tr>
                        ';
                        $det_operativos = $det_operativos.$detail;
                        $total_det_operativos+= $value['total'];
                    }

                    $sql_otros = $this->sql("i_no_operativos");
                    foreach ($sql_otros as $key => $value) {
                        $detail = '
                            <tr>
                                <td>&nbsp;</td>
                                <td style="text-align: center;">'.$value['name_cuenta'].'</td>
                                <td style="text-align: rigth;">$'.number_format($value['total'],$DECI_MALES,',','.').'</td>
                            </tr>
                        ';
                        $det_otros_ingresos = $det_otros_ingresos.$detail;
                        $total_det_otros_ingresos += $value['total'];
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
					</tr>

				</table>
                <table width="100%" style="text-align: center;">
        			<tr>
            			<th>
							<p><b>PYG</b></p>
						</th>
					</tr>

				</table>';

		$footer = '
        	<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th  width="33%" style="font-size: 8px;"> Documento generado por <span style="color: orange; font-weight: bolder;"> Joint ERP  </span> para: '.$empresa[0]['pge_small_name'].'. Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            	</tr>
        	</table>';


        $html = '<html>
        <table  width="100%" font-family: serif>
        <tr>
            <th style="text-align: left;"><b>GASTOS DE OPERACION</b></th>
            <th><b>&nbsp;</b></th>
            <th><b>&nbsp;</b></th>
        </tr>
        <tr>
            <th style="text-align: left;"><b>DE ADMINISTRACION</b></th>
            <th><b>&nbsp;</b></th>
            <th><b>&nbsp;</b></th>
        </tr>
        '.$det_adm.'
        <tr>
            <th><b>&nbsp;</b></th>
            <th><b>&nbsp;</b></th>
            <th style="text-align: rigth;"><b>'.$total_det_adm.'</b></th>
        </tr>
        </table>

        
        </html>';
        print_r($html);exit;
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


    private function sql($type)
    {
        $respuesta = "";
        $sql = "SELECT * FROM pyg WHERE tipo = :tipo";

		$resSql = $this->pedeo->queryTable($sql,array(
            ':tipo' => $type
        ));

        if(isset($resSql[0])){
            $respuesta =  $resSql;
        }
        
        return $respuesta;
    }
}