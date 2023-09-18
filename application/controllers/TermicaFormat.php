<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class TermicaFormat extends REST_Controller {

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


	public function TermicaFormat_post()
    {

		$DECI_MALES =  $this->generic->getDecimals();

        $Data = $this->post();

        $formatter = new NumeroALetras();

        //'setAutoTopMargin' => 'stretch','setAutoBottomMargin' => 'stretch',

        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8',
            'format' => [58,210],
            'default_font_size' => 0,
            'margin_left' => 2,
            'margin_right' => 4,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 2,
            'margin_footer' => 2,
            'default_font' => 'arial'
        ]);
        

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

				$sqlcotizacion = "SELECT
									concat(T0.dvf_cardname,' ',T2.dms_card_last_name) Cliente,
									T0.dvf_cardcode Nit,
									T3.dmd_adress Direccion,
									T3.dmd_state_mm ciudad,
									t3.dmd_state estado,
									T2.dms_phone1 Telefono,
									T2.dms_email Email,
									t0.dvf_docnum,
									ConCAT(T6.pgs_pref_num,' ',T0.dvf_docnum) NumeroDocumento,
									to_char(T0.dvf_docdate,'DD-MM-YYYY') FechaDocumento,
									to_char(T0.dvf_duedate,'DD-MM-YYYY') FechaVenDocumento,
									t0.dvf_currency as MonedaDocumento,
									T7.pgm_name_moneda NOMBREMonEDA,
									T5.mev_names Vendedor,
									t8.mpf_name CondPago,
                                    t1.fv1_linenum linea, 
									T1.fv1_itemcode Referencia,
									T1.fv1_itemname descripcion,
									T1.fv1_whscode Almacen,
									T1.fv1_uom UM,
									T1.fv1_quantity Cantidad,
									T1.fv1_price VrUnit,
									T1.fv1_discount PrcDes,
									T1.fv1_vatsum IVAP,
									T1.fv1_linetotal ValorTotalL,
									T0.dvf_baseamnt base,
									T0.dvf_discount Descuento,
									(T0.dvf_baseamnt - T0.dvf_discount) subtotal,
									T0.dvf_taxtotal Iva,
									T0.dvf_doctotal TotalDoc,
									T0.dvf_comment Comentarios,
									t0.dvf_ref oc,
									0 peso,
									t10.dmu_code um,
									T0.dvf_precinto precintos,
									t0.dvf_placav placa,
									t0.dvf_docdate,
									t6.pgs_mpfn,
									t6.pgs_mde,
									t1.fv1_quantity,
									t2.dms_rtype regimen,
									T0.dvf_taxigtf,
									T0.dvf_igtf,
									t0.dvf_igtfapplyed,
									get_dynamic_conversion(get_localcur(),t0.dvf_igtfcurrency,t0.dvf_docdate,t0.dvf_igtfapplyed,get_localcur()) as base_igtf,
									get_dynamic_conversion(get_localcur(),t0.dvf_igtfcurrency,t0.dvf_docdate,t0.dvf_igtf,get_localcur()) as imp_igtf
								from dvfv t0
								inner join vfv1 T1 on t0.dvf_docentry = t1.fv1_docentry
								left join dmsn T2 on t0.dvf_cardcode = t2.dms_card_code
								left join dmsd T3 on T0.dvf_cardcode = t3.dmd_card_code AND t3.dmd_ppal = 1
								left join dmsc T4 on T0.dvf_cardcode = t4.dmc_card_code
								left join dmev T5 on T0.dvf_slpcode = T5.mev_id
								left join pgdn T6 on T0.dvf_doctype = T6.pgs_id_doc_type and T0.dvf_series = T6.pgs_id
								left join pgec T7 on T0.dvf_currency = T7.pgm_symbol
								left join dmpf t8 on t2.dms_pay_type = cast(t8.mpf_id as varchar)
								left join dmar t9 on t1.fv1_itemcode = t9.dma_item_code
								left join dmum t10 on t9.dma_uom_umweight = t10.dmu_id
								where T0.dvf_docentry = :DVF_DOCENTRY AND t0.business = :business
								and t2.dms_card_type = '1'";

				$contenidoFV = $this->pedeo->queryTable($sqlcotizacion,array(
					':DVF_DOCENTRY'=> $Data['DVF_DOCENTRY'],
					':business' => $Data['business']
				));
                // print_r($sqlcotizacion);exit;

				if(!isset($contenidoFV[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $empresa,
							 'mensaje' =>'no se encontro el documento'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				//
			

				// PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
				// SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
				$sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
				$resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

				if(isset($resMonedaLoc[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la moneda local.'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				$MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

				// SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
				$sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
				$resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

				if(isset($resMonedaSys[0])){

				}else{

						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encontro la moneda de sistema.'
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}


				$MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

				//SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
				// Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
				$sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $contenidoFV[0]['monedadocumento'], ':tsa_currd' => $MONEDASYS, ':tsa_date' => $contenidoFV[0]['dvf_docdate']));
                // print_r($contenidoFV[0]['monedadocumento']);exit;
				if(isset($resBusTasa[0])){

				}else{

						if(trim($contenidoFV[0]['monedadocumento']) != $MONEDALOCAL ){

							$respuesta = array(
								'error' => true,
								'data'  => array(),
								'mensaje' =>'No se encrontro la tasa de cambio para la moneda: '.$contenidoFV[0]['monedadocumento'].' en la actual fecha del documento: '.$contenidoFV[0]['dvf_docdate'].' y la moneda local: '.$resMonedaLoc[0]['pgm_symbol']
							);

							$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

							return;
						}
				}


				$sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
				$resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $contenidoFV[0]['dvf_docdate']));

				if(isset($resBusTasa2[0])){

				}else{
						$respuesta = array(
							'error' => true,
							'data'  => array(),
							'mensaje' =>'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :'.$contenidoFV[0]['dvf_docdate']
						);

						$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

						return;
				}

				$TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
				$TasaLocSys = $resBusTasa2[0]['tsa_value'];

				// FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO


				//OBTENER RELACION DE DOCUMENTOS
				$relacionsql = "SELECT dvct.dvc_docnum AS cotizacion, dvov.vov_docnum AS pedido, dvem.vem_docnum AS entrega
										FROM dvfv
										LEFT JOIN dvem
										ON dvfv.dvf_baseentry = dvem.vem_docentry
										LEFT JOIN dvov
										ON dvem.vem_baseentry = dvov.vov_docentry
										LEFT JOIN dvct
										ON dvov.vov_baseentry = dvct.dvc_docentry
										WHERE dvfv.dvf_docentry = :DVF_DOCENTRY";

			  $resrelacionsql = $this->pedeo->queryTable($relacionsql, array(':DVF_DOCENTRY' => $Data['DVF_DOCENTRY']));

				$VieneEntrega = 0;
				$VienePedido = 0;
				$VieneCotizacion = 0;

				if(isset($resrelacionsql[0])){
						$VieneEntrega = $resrelacionsql[0]['entrega'];
						$VienePedido = $resrelacionsql[0]['pedido'];
						$VieneCotizacion = $resrelacionsql[0]['cotizacion'];

				}
				//FIN BUSQUEDA REALAZION DE DOCUMENTOS


				//INFORMACION DE LA DESCRIPCION FINAL DEL FORMATO
				$comentarioFinal = "";
				$SqlCommentFinal = "SELECT t0.*
												 FROM cfdm t0
												 LEFT JOIN dvfv t1 ON t0.cdm_type = CAST(t1.dvf_doctype AS VARCHAR)
												 WHERE t1.dvf_docentry = :DVF_DOCENTRY";
				$CommentFinal = $this->pedeo->queryTable($SqlCommentFinal,array(':DVF_DOCENTRY' => $Data['DVF_DOCENTRY']));

				if(isset($CommentFinal[0])){
					$CommentFinal[0]['cdm_comments'];
				}

				$totaldetalle = '';
				$TotalCantidad = 0;
				$TotalPeso = 0;
                $count = 1; 
				foreach ($contenidoFV as $key => $value) {
                    $valorUnitario = $value['vrunit'];
					$valortotalLinea = $value['valortotall'];
					if( $value['monedadocumento'] != $MONEDALOCAL ){
                        $valorUnitario = ($valorUnitario  * $TasaDocLoc);
						$valortotalLinea = ($valortotalLinea  * $TasaDocLoc);

					};
                    $detalle = '
                        <td>'.$value['linea'].'</td>
						<td>'.$value['cantidad'].'</td>
						<td>unidad</td>
						<td>'.$value['monedadocumento']." ".number_format($valorUnitario , $DECI_MALES, ',', '.').'</td>';
    					 $totaldetalle = $totaldetalle.'<tr>'.$detalle.'</tr>';
						 $TotalCantidad = ($TotalCantidad + ($value['cantidad']));
						 $TotalPeso = ($TotalPeso + ($value['peso'] * $value['cantidad']));

                         $count++;
	    		}

				$valorTotalBase = $contenidoFV[0]['base'];
				$valorTotalSubtotal = $contenidoFV[0]['subtotal'];
				$valorTotalIva = $contenidoFV[0]['iva'];
				$valorTotalDoc = $contenidoFV[0]['totaldoc'];

				if( $value['monedadocumento'] != $MONEDALOCAL ){

						$valorTotalBase = ($valorTotalBase * $TasaDocLoc);
						$valorTotalSubtotal = ($valorTotalSubtotal * $TasaDocLoc);
						$valorTotalIva = ($valorTotalIva * $TasaDocLoc);
						$valorTotalDoc = ($valorTotalDoc * $TasaDocLoc);

				};

				$consecutivo = '';

				if($contenidoFV[0]['pgs_mpfn'] == 1){
					$consecutivo = $contenidoFV[0]['numerodocumento'];
				}else{
					$consecutivo = $contenidoFV[0]['dvf_docnum'];
				}

        $header = '
        <table width="100%">
        <tr>
            <th>
                <p>'.$empresa[0]['pge_name_soc'].'</p>
                <p>'.$empresa[0]['pge_id_type'].'</p>
                <p>'.$empresa[0]['pge_add_soc'].'</p>
                <p>'.$empresa[0]['pge_phone1'].'</p>
                <p>'.$empresa[0]['pge_web_site'].'</p>
                <p>'.$empresa[0]['pge_mail'].'</p>
                <hr>
                <p>Este ocumento no es ni reemplaza una factura de venta ni un documento equivalente.</p>
                <br>
                <p>La factura electronica ha sido enviada al correo suministrado en el momento de la compra.</p>
            </th>
            <hr>
        </tr>

        </table>';

        $footer = '
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th style="text-align: center;">

                </th>
            </tr>
        </table>
        <table width="100%" style="vertical-align: bottom; font-family: serif;
            font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="" width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';


        $html = '
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
            <table width="100%">
                <tr>
                    <th>
                        <p>No.: '.$consecutivo.'
                        <p style="text-align: left;">Fecha: '.$contenidoFV[0]['fechadocumento'].'</p>
                        <hr>
                    </th>
                </tr>
            </table>
            <table width="100%">
                <tr>
                    <th>
                        <p style="text-align: left;">Cliente: '.$contenidoFV[0]['cliente'].'
                        <p style="text-align: left;">CC/NIT: '.$contenidoFV[0]['nit'].'</p>
                        <p style="text-align: left;">Dirección: '.$contenidoFV[0]['direccion'].'</p>
                        <p style="text-align: left;">Teléfono: '.$contenidoFV[0]['telefono'].'</p>
                        <p style="text-align: left;">Vendedor: '.$contenidoFV[0]['vendedor'].'</p>
                        <hr>
                    </th>
                </tr>
            </table>
            <table width="100%">
                <tr class="">
                    <th>Ít.</th>
                    <hr>
                    <th>Cant.</th>
                    <hr>
                    <th>Und.</th>
                    <hr>
                    <th>Valor</th>
                    <hr>
                </tr>
      	        '.$totaldetalle.'
            </table>
            <hr>
            <table width="100%">
                <tr>
                    <td style="text-align: left;">Total Bruto: <span >'.$contenidoFV[0]['monedadocumento']." ".number_format($valorTotalBase, $DECI_MALES, ',', '.').'</span></td>
                </tr>
                <tr>
                    <td style="text-align: left;">Subtotal: <span >'.$contenidoFV[0]['monedadocumento']." ".number_format($valorTotalSubtotal, $DECI_MALES, ',', '.').'</span></td>
                </tr>
            </table>
            <hr>
            <table width="100%">
                <tr>
                    <td style="text-align: left;">Total a pagar: <span >'.$contenidoFV[0]['monedadocumento']." ".number_format($valorTotalDoc, $DECI_MALES, ',', '.').'</span></td>
                </tr>
            </table>
            <hr>
            <table width="100%">
                <tr>
                    <th style="text-align: left;">Medios de pago:</th>
                </tr>
                <tr>
                    <td style="text-align: left;">Efectivo</td>
                    <td style="text-align: left;">'.$contenidoFV[0]['monedadocumento']." ".number_format($valorTotalDoc, $DECI_MALES, ',', '.').'</td>
                </tr>
            </table>
            <hr>
            <p style="text-align: center;">¡Gracias por su compra!</p>
        ';
				// print_r($html);exit();die(); 
        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);

        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html);

        $mpdf->Output('Doc.pdf', 'D');

		header('Content-type: application/force-download');
		header('Content-Disposition: attachment; filename='.$filename);


	}




}
