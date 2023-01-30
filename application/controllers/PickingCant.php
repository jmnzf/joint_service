<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class PickingCant extends REST_Controller {

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


	public function PickingCant_post(){

        $Data = $this->post();
				$Data = $Data['VEM_DOCENTRY'];

				$formatter = new NumeroALetras();

				$totalpeso = 0;
				$totalcantidad = 0;


        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch']);

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

		$empresa = $this->pedeo->queryTable("SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
		pge_cou_soc, CONCAT(pge_id_type,' ',pge_id_soc) AS pge_id_type , pge_web_site, pge_logo,
		CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
		pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type
		FROM pgem WHERE pge_id = :pge_id", array(':pge_id' => $Data['business']));

				if(!isset($empresa[0])){
						$respuesta = array(
		           'error' => true,
		           'data'  => $empresa,
		           'mensaje' =>'no esta registrada la información de la empresa'
		        );

	          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	          return;
				}

				$sqlPickingCiego = "SELECT
														CONCAT(T0.VEM_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
														TRIM('CN' FROM T0.VEM_CARDCODE) Nit,
														CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) Direccion,
														T4.DMC_PHONE1 Telefono,
														T4.DMC_EMAIL Email,
														CONCAT(T6.PGS_PREF_NUM,' ',T0.VEM_DOCNUM) NumeroDocumento,
														T0.VEM_DOCDATE FechaDocumento,
														T0.VEM_DUEDATE FechaVenDocumento,
														trim('COP' FROM t0.VEM_CURRENCY) MonedaDocumento,
														T7.PGM_NAME_MONEDA NOMBREMONEDA,
														T5.MEV_NAMES Vendedor,
														'' MedioPago,
														'' CondPago,
														T1.EM1_ITEMCODE Referencia,
														T1.EM1_ITEMNAME descripcion,
														T1.EM1_WHSCODE Almacen,
														T1.EM1_UOM UM,
														T1.EM1_QUANTITY Cantidad,
														T8.DMA_UOM_WEIGHT peso,
														T1.EM1_PRICE VrUnit,
														T1.EM1_DISCOUNT PrcDes,
														T1.EM1_VATSUM IVAP,
														T1.EM1_LINETOTAL ValorTotalL,
														T0.VEM_BASEAMNT base,
														T0.VEM_DISCOUNT Descuento,
														(T0.VEM_BASEAMNT - T0.VEM_DISCOUNT) subtotal,
														T0.VEM_TAXTOTAL Iva,
														T0.VEM_DOCTOTAL TotalDoc,
														T0.VEM_COMMENT Comentarios,
													    T0.vem_modelv modelo,
													    T0.VEM_DRIVERV conductor,
													    T0.VEM_PLACAV placa,
													    T0.VEM_PRECINTO precinto,
													    T0.VEM_DRIVERID c_conductor,
															T0.VEM_SUP_TRANSPORT
													FROM DVEM t0
													INNER JOIN VEM1 T1 ON t0.VEM_docentry = t1.EM1_docentry
													LEFT JOIN DMSN T2 ON t0.VEM_cardcode = t2.dms_card_code
													LEFT JOIN DMSD T3 ON T0.VEM_ADRESS = CAST(T3.DMD_ID AS VARCHAR) AND T3.dmd_ppal = 1
													LEFT JOIN DMSC T4 ON T0.VEM_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
													LEFT JOIN DMEV T5 ON T0.VEM_SLPCODE = T5.MEV_ID
													LEFT JOIN PGDN T6 ON T0.VEM_DOCTYPE = T6.PGS_ID_DOC_TYPE AND T0.VEM_SERIES = T6.PGS_ID
													LEFT JOIN PGEC T7 ON T0.VEM_CURRENCY = T7.PGM_SYMBOL
													LEFT JOIN DMAR T8 ON T1.EM1_ITEMCODE = T8.DMA_ITEM_CODE
													WHERE T0.VEM_DOCENTRY = :VEM_DOCENTRY
													and t2.dms_card_type = '1'";

				$contenidoEV = $this->pedeo->queryTable($sqlPickingCiego,array(':VEM_DOCENTRY'=>$Data));

				if(!isset($contenidoEV[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $contenidoEV,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta);

						return;
				}
				// print_r($contenidoEV);exit();die();

				$totaldetalle = '';
				foreach ($contenidoEV as $key => $value) {
					// code...
					$detalle = '<td>'.$value['referencia'].'</td>
											<td>'.$value['descripcion'].'</td>
											<td>'.$value['um'].'</td>
                      <td>'.$value['cantidad'].'</td>
											<td>'.$value['peso'] * $value['cantidad'].'</td>';
				 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
				 $totalpeso	= ($totalpeso	+ ($value['peso'] * $value['cantidad']));
				 $totalcantidad =  ($totalcantidad + $value['cantidad']);
				}


        $header = '
        <table width="100%">
        <tr>
            <th style="text-align: left;"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="100" height ="40"></img></th>
            <th>
                <p>'.$empresa[0]['pge_name_soc'].'</p>
                <p>'.$empresa[0]['pge_id_type'].'</p>
                <p>'.$empresa[0]['pge_add_soc'].'</p>
                <p>'.$empresa[0]['pge_phone1'].'</p>
                <p>'.$empresa[0]['pge_web_site'].'</p>
                <p>'.$empresa[0]['pge_mail'].'</p>
            </th>
            <th>
                <p>LISTA DE PICKING</p>
                <p class="fondo">'.$contenidoEV[0]['numerodocumento'].'</p>

            </th>
        </tr>

        </table>';

        $footer = '

				<table width="100%">
					<tr>
										<th>__________________________________________________</th><spam> </spam><th>__________________________________________________</th><spam> </spam><th>__________________________________________________</th><spam> </spam>
				 </tr>
				 <tr>
									 <td>OPERADOR</td><spam> </spam><td>ENTREGADO POR</td><spam> </spam><td>RECIBIDO POR</td>
				</tr>
				</table>
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="fondo" width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';


        $html = '

        <table class="bordew" style="width:100%">
			 <tr>
				 <th>
					 <p class="fondo" style="text-align: left;">RIF:</p>
				 </th>
				 <th style="text-align: left;">
					 <p>'.$contenidoEV[0]['nit'].'</p>
				 </th>
				</tr>
				<tr>
				 <th >
					 <p class="fondo" >Nombre Cliente</p>
	 			 <th style="text-align: left;">

					 <p style="text-align: left;">'.$contenidoEV[0]['cliente'].'</p>

	 			 </th>
			 	</tr>


			 </table>

        <br>

        <table class="borde" style="width:100%">
        <tr>
          <th class="fondo">ITEM</th>
          <th class="fondo">REFERENCIA</th>
          <th class="fondo">UNIDAD</th>
          <th class="fondo">CANTIDAD</th>
					<th class="fondo">PESO</th>
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
        <table width="100%">

				<tr>
           <td style="text-align: auto;">PESO TOTAL: <span class="sub_ray";>'.$totalpeso.'</span></p></td>
        </tr>
				<tr>
					  <td style="text-align: auto;">TOTAL CANTIDAD: <span class="sub_ray";>'.$totalcantidad.'</span></p></td>
				</tr>
        <tr>
            <td style="text-align: auto;">PLACA: <span class="sub_ray";>'.$contenidoEV[0]['placa'].'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: auto;">MODELO: <span class="sub_ray";>'.$contenidoEV[0]['modelo'].'</span></p></td>
        </tr>
				<tr>
            <td style="text-align: auto;">PRECINTO: <span class="sub_ray";>'.$contenidoEV[0]['precinto'].'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: auto;">CONDUCTOR: <span class="sub_ray";>'.$contenidoEV[0]['conductor'].'</span></p></td>
        </tr>
        <tr>
            <td style="text-align: auto;">IDENTIFICACION: <span class="sub_ray";>'.$contenidoEV[0]['c_conductor'].'</span></p></td>
        </tr>
				<tr>
						<td style="text-align: auto;">TRANSPORTADORA: <span class="sub_ray";>'.$contenidoEV[0]['vem_sup_transport'].'</span></p></td>
				</tr>
        </table>
				<br>
				<br>
				<br>

        <br><br>';
// print_r($html);exit();die();
        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);


        $mpdf->Output('Doc.pdf', 'D');

				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);


	}




}
