<?php

// GENERAR CERTIFICADO DE RETENCIONES POR TERCERO

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class CertificadoRetencion extENDs REST_Controller {

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


	public function getCertifcado_post(){

		$DECI_MALES =  $this->generic->getDecimals();

        $Data = $this->post();
       
        $formatter = new NumeroALetras();



        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch','orientation' => 'P']);

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
                        pge_cou_soc, CONCAT(pge_id_type,' ',pge_id_soc) AS pge_id_type , pge_web_site, pge_logo,
                        CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
                        pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type,
                        pge_id_soc, tpdm.pdm_municipality 
                        FROM pgem 
                        inner join  tpdm on pdm_codmunicipality = pge_city_soc
                        WHERE pge_id = :pge_id";

		$empresa = $this->pedeo->queryTable( $sqlEmpresa, array(':pge_id' => $Data['business']));

        if(!isset($empresa[0])) {

            $respuesta = array(
                'error' => true,
                'data'  => $empresa,
                'mensaje' =>'no esta registrada la información de la empresa'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        // DIRECCION DEL SOCIO
        $sqlDireccion = "SELECT * 
                        from dmsd 
                        inner join tpdm on dmsd.dmd_city  = pdm_codmunicipality 
                        where dmd_card_code = :cardcode 
                        and dmd_ppal = 1
                        limit 1";


        $resDireccion = $this->pedeo->queryTable($sqlDireccion, array(
            "cardcode" => $Data['cardcode']
        ));


        if ( !isset($resDireccion[0]) ){

            $respuesta = array(
                'error' => true,
                'data'  => $empresa,
                'mensaje' =>'No se encontro la direccion del tercero'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlDatosRetenciones = "";

        if ( $Data['cardtype'] == '2' ) {

            $sqlDatosRetenciones = "select 
            mrt_code as codigo_retencion, 
            mac1.ac1_legal_num as codigo_tercero, 
            (sum(ac1_credit) - sum(ac1_debit)) as total_retencion, 
            mrt_name as nombre_retencion,
            mrt_tasa as tasa_retencion,
            get_localcur() as currency,
            dms_card_name as nombre_tercero,
            round(((sum(ac1_credit) - sum(ac1_debit))  / (mrt_tasa / 100)), get_decimals()) as base_documento
            from dmrt
            inner join mac1 on mrt_code = ac1_codret
            inner join dmsn on ac1_legal_num = dms_card_code and dms_card_type = '2'
            where ac1_font_type in (15,16,17,18)
            and ac1_legal_num = :cardcode
            and ac1_doc_date  between :fi and :ff
            and mrt_type =  :mrt_type
            group by dmrt.mrt_type, mrt_acctcode, ac1_legal_num, mrt_name, mrt_tasa, dms_card_name, mrt_code";

        } else if ($Data['cardtype'] == '1') {

            $sqlDatosRetenciones = "select 
            mrt_code as codigo_retencion, 
            mac1.ac1_legal_num as codigo_tercero, 
            (sum(ac1_debit) - sum(ac1_credit)) as total_retencion, 
            mrt_name as nombre_retencion,
            mrt_tasa as tasa_retencion,
            get_localcur() as currency,
            dms_card_name as nombre_tercero,
            round((((sum(ac1_debit) - sum(ac1_credit)))  / (mrt_tasa / 100)), get_decimals()) as base_documento
            from dmrt
            inner join mac1 on mrt_code = ac1_codret
            inner join dmsn on ac1_legal_num = dms_card_code and dms_card_type = '1'
            where ac1_font_type in (5,6,7,18)
            and ac1_legal_num = :cardcode
            and ac1_doc_date  between :fi and :ff
            and mrt_type =  :mrt_type
            group by dmrt.mrt_type, mrt_acctcode, ac1_legal_num, mrt_name, mrt_tasa, dms_card_name, mrt_code";

        }

       

        

		$contenido = $this->pedeo->queryTable($sqlDatosRetenciones,array(
            ":fi" => $Data['fi'],
            ":ff" => $Data['ff'],
            ":cardcode" => $Data['cardcode'],
            ":mrt_type" => $Data['mrt_type']
        ));

        if (!isset( $contenido[0] ) ) {

            $respuesta = array(
                'error' => false,
                'data'  => $contenido,
                'mensaje' =>'Sin información para procesar'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        // print_r($empresa);exit();die();

    
        $totaldetalle = '';
        $total = 0;
      
        foreach ($contenido as $key => $value) {
            // code...
            $detalle = '
                <td style="padding-top: 10px;">'.$value['codigo_retencion'].'</td>
                <td style="padding-top: 10px;">'.$value['nombre_retencion'].'</td>
                <td style="padding-top: 10px;">'.$value['tasa_retencion']." %".'</td>
                <td style="padding-top: 10px;">'.$empresa[0]['pge_curr_first'].' '.number_format($value['base_documento'], $DECI_MALES, ',', '.').'</td>
                <td style="padding-top: 10px;">'.$empresa[0]['pge_curr_first'].' '.number_format($value['total_retencion'], $DECI_MALES, ',', '.').'</td>';
            $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';

            $total =  $total + $value['total_retencion'];
        
        }


        $header = '
        <table width="100%" style="text-align: left;">
            <tr>
                <th style="text-align: left;" width ="60"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="185" height ="120"></img></th>
                <th style="text-align: center; margin-left: 600px;">
                    <p><b>CERTIFICADO DE '.$Data['text'].'</b></p>

                </th>
                <th>
                    &nbsp;
                    &nbsp;
                </th>

            </tr>

        </table>
        <table width="100%" style="text-align: right;">
            <tr>

                <th style="text-align: right;">
                    <p><b> FECHA DE EXPEDICIÓN: '.date("d-m-Y").'</b></p>
                </th>

            </tr>

        </table>';

        // $footer = '

        // <table width="100%" style="vertical-align: bottom; font-family: serif;
        //     font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
        //     <tr>
        //         <th class="" width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
        //     </tr>
        // </table>';


		$html = '

			<table class="" style="width:100%">
			    <tr>
                    <th style="text-align: left; ">
					    <p class="" style="text-align: left;"><b>DOCUMENTO DEL RETENEDOR:</b> '.$empresa[0]['pge_id_soc'].'</p>
				    </th>
				</tr>
				<tr>
                    <th style="text-align: left; ">
                        <p class="" ><b>NOMBRE DEL RETENEDOR:</b> '.$empresa[0]['pge_name_soc'].'</p>
                    </th>
			 	</tr>

                <tr>
                    <th>
                        <p class="" style="text-align: left;"><b></b></p>
                    </th>
                    <th style="text-align: left;">
                        <p>&nbsp;</p>
                    </th>
                </tr>

                <tr>
                    <th style="text-align: left; ">
                        <p class="" style="text-align: left;"><b>AÑO GRAVABLE:</b> '.date("Y", strtotime($Data['fi'])).'</p>
                    </th>
                </tr>

                <tr>
                    <th style="text-align: left; ">
                        <p class="" style="text-align: left;"><b>FECHA INICIAL:</b> '.$Data['fi'].'</p>
                    </th>
                </tr>


                <tr>
                    <th style="text-align: left; ">
                        <p class="" style="text-align: left;"><b>FECHA FINAL:</b> '.$Data['ff'].'</p>
                    </th>
                </tr>
                
                <tr>
                    <th style="text-align: left; ">
                        <p class="" style="text-align: left;"><b>SE RETUVO A:</b> '.$contenido[0]['nombre_tercero'].'</p>
                    </th>
                </tr>

                <tr>
                    <th style="text-align: left; ">
                        <p class="" style="text-align: left;"><b>NIT:</b> '.$contenido[0]['codigo_tercero'].'</p>
                    </th>
                </tr>

                <tr>
                    <th style="text-align: left; ">
                        <p class="" style="text-align: left;"><b>DIRECCIÓN:</b> '.$resDireccion[0]['dmd_adress'].'</p>
                    </th>
                </tr>

                <tr>
                    <th style="text-align: left; ">
                        <p class="" style="text-align: left;"><b>CIUDAD:</b> '.$resDireccion[0]['pdm_municipality'].'</p>
                    </th>
                </tr>

                <tr>
                    <th style="text-align: left; ">
                        <p class="" style="text-align: left;"><b>ÁREA DE VALORIZACIÓN:</b> TODAS</p>
                    </th>
                </tr>
               
			</table>

            <br>

            <table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
                <tr>
                    <th style="text-align: center;">
                            <p>Certificamos que hemos practicado las siguientes retenciones:</p>
                    </th>
                </tr>
            </table>

            <br>

			<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
                    <tr>
                        <th class="fondo">
                                <p></p>
                        </th>
                    </tr>
			</table>

            <br>



            <table class="borde" style="width:100%">
            <tr>
                <th class=""><b>CÓDIGO IMPUESTO</b></th>
                <th class=""><b>CONCEPTO DE RETENCIÓN</b></th>
                <th class=""><b>TARIFA (%)</b></th>
                <th class=""><b>BASE IMPUESTO</b></th>
                <th class=""><b>VALOR IMPUESTO</b></th>
            </tr>
            '.$totaldetalle.'
            </table>
            <br>
            <table width="100%" style="vertical-align: bottom; font-family: serif;
                font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
                <tr>
                    <th class="fondo">
                        <p></p>
                    </th>
                </tr>
            </table>

            <br>
            <table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
                <tr>
                    <th style="text-align: right;">
                        <p><b>TOTAL: '.$empresa[0]['pge_curr_first'].' '.number_format($total , $DECI_MALES, ',', '.').'</b></p>
                    </th>
                </tr>
            </table>
        
            <br>
            <table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
                <tr>
                    <th style="text-align: justify;">
                        <p>Ciudad donde se Consignó la retención: BARRANQUILLA.</p>
                        <p>Este certificado no requiere de firma Autógrafa de acuerdo con el Artículo 7 del decreto reglamentario 0380 del 27 de febrero de 1996. Las personas jurídicas podrán entregar los certificados de retención en la Fuente en forma continua, impresa por computador, sin necesidad de firma autógrafa. Artículo 10 Decreto 0836 de 1991.</p>
                    </th>
                </tr>
            </table>
            <br>
        ';


        $footer = '
        	<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th  width="33%" style="font-size: 8px;"> Documento generado por <span style="color: orange; font-weight: bolder;"> Joint ERP  </span> para: '.$empresa[0]['pge_small_name'].'. Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            	</tr>
        	</table>';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);
		// $mpdf->SetDefaultBodyCSS('background', "url('/var/www/html/".$company[0]['company']."/assets/img/W-background.png')");

        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);


        $mpdf->Output('EstadoCuenta_'."estado de ".'-'."cuenta".'.pdf', 'D');

        $filename = 'EstadoCuenta_'."estado de ".'-'."cuenta".'.pdf';

        header('Content-type: application/force-download');
        header('Content-Disposition: attachment; filename='.$filename);


	}

	


}
