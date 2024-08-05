<?php
defined('BASEPATH') OR exit('No direct script access allowed');
use Luecano\NumeroALetras\NumeroALetras;

class DocumentMarketing {

    private $ci;
    private $pdo;

	public function __construct(){

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->pdo = $this->ci->load->database('pdo', true)->conn_id;
        $this->ci->load->library('pedeo', [$this->pdo]);
		$this->ci->load->library('generic');
		$this->ci->load->library('DateFormat');

	}
    public function format($Data,$type_mpdf = "D")
	{

		$formatter = new NumeroALetras();
		$DECI_MALES =  $this->ci->generic->getDecimals();

		// $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch']);
        $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch','default_font' => 'dejavusans']);

		$Type = "";
		$aver = 0;
		//RUTA DE CARPETA EMPRESA
        $company = $this->ci->pedeo->queryTable("SELECT main_folder company FROM PARAMS",array());

        if(!isset($company[0])){
						$respuesta = array(
		           'error' => true,
		           'data'  => $company,
		           'mensaje' =>'no esta registrada la ruta de la empresa'
		        );

				return $respuesta;
				}

        //INFORMACION DE LA EMPRESA
		$sqlEmpresa = "SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
		pge_cou_soc, pge_id_soc AS pge_id_type , pge_web_site, pge_logo,
		CONCAT(pge_cel) AS pge_phone1, pge_branch, pge_mail,
		pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type,pge_id_soc,pge_phone2,pge_page_social
		FROM pgem WHERE pge_id = :pge_id";

		$empresa = $this->ci->pedeo->queryTable($sqlEmpresa, array(':pge_id' => $Data['business']));

		if(!isset($empresa[0])){
			$respuesta = array(
				'error' => true,
		        'data'  => $empresa,
	       		'mensaje' =>'no esta registrada la información de la empresa'
			);

			return $respuesta;
		}

		//CONSULTA PARA OBTENER NOMBRE DE DOCUMENTO
		$sql = "SELECT
					CONCAT(t0.{prefijo}_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
					t0.{prefijo}_CARDCODE Nit,
					CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) AS Direccion,
					T11.pdm_states ciudad,
					t11.pdm_municipality estado,
					T2.dms_phone1 Telefono,
					T2.dms_email Email,
					t0.{prefijo}_DOCNUM,
					t0.{prefijo}_DOCNUM NumeroDocumento,
					t0.{prefijo}_DOCDATE FechaDocumento,
					t0.{prefijo}_DUEDATE FechaVenDocumento,
					t0.{prefijo}_duedev fechaentrga,
					t0.{prefijo}_CURRENCY MonedaDocumento,
					T7.PGM_NAME_MONEDA NOMBREMONEDA,
					T5.MEV_NAMES Vendedor,
					'' MedioPago,
					'' CondPago,
					t1.{prefijo1}_ITEMCODE Referencia,
					t1.{prefijo1}_ITEMNAME descripcion,
					t1.{prefijo1}_WHSCODE Almacen,
					t1.{prefijo1}_UOM UM,
					t1.{prefijo1}_QUANTITY Cantidad,
					t1.{prefijo1}_PRICE VrUnit,
					t1.{prefijo1}_DISCOUNT PrcDes,
					t1.{prefijo1}_VATSUM IVAP,
					t1.{prefijo1}_LINETOTAL ValorTotalL,
					t0.{prefijo}_BASEAMNT base,
					t0.{prefijo}_DISCOUNT Descuento,
					(t0.{prefijo}_BASEAMNT - t0.{prefijo}_DISCOUNT) subtotal,
					t0.{prefijo}_TAXTOTAL Iva,
					t0.{prefijo}_DOCTOTAL TotalDoc,
					t0.{prefijo}_COMMENT Comentarios,
					t6.pgs_mde,
					t6.pgs_mpfn,
					T8.MPF_NAME cond_pago,
					t3.dmd_tonw lugar_entrega,
					t5.mev_names nombre_contacto,
					t5.mev_mail correo_contacto,
					t5.mev_phone telefono_contacto,
					t9.dmc_contac_id as idcontact,
					concat(t9.dmc_name,' ',t9.dmc_last_name) nombre_contacto_p,
					t9.dmc_email correo_contacto_p,
					t9.dmc_cel telefono_contacto_p,
					t10.mdt_docname
					{campos}
				FROM {table}  t0
				INNER JOIN {table1} T1 ON t0.{prefijo}_docentry = t1.{prefijo1}_docentry
				LEFT JOIN DMSN T2 ON t0.{prefijo}_cardcode = t2.dms_card_code
				LEFT JOIN DMSD T3 ON t2.dms_card_code = t3.dmd_card_code AND t3.dmd_ppal = 1
				LEFT JOIN DMSC T4 ON t0.{prefijo}_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
				LEFT JOIN DMEV T5 ON t0.{prefijo}_SLPCODE = T5.MEV_ID
				LEFT JOIN PGDN T6 ON t0.{prefijo}_DOCTYPE = T6.PGS_ID_DOC_TYPE AND t0.{prefijo}_SERIES = T6.PGS_ID
				LEFT JOIN PGEC T7 ON t0.{prefijo}_CURRENCY = T7.PGM_SYMBOL
				LEFT JOIN DMPF T8 ON T2.DMS_PAY_TYPE = cast(T8.MPF_ID as  varchar)
				LEFT JOIN dmsc t9 ON t0.{prefijo}_cardcode = t9.dmc_card_code and T9.dmc_ppal = 1
				INNER JOIN dmdt t10 ON t0.{prefijo}_doctype = t10.mdt_doctype
				left join tpdm t11 on t3.dmd_state = t11.pdm_codstates::text and t3.dmd_city = t11.pdm_codmunicipality
				WHERE t0.{prefijo}_docentry= :docentry  {where} and t0.business = :business";

				//REEMPLAZAR TABLAS DEPENDIENTO EL TIPO DEL DOCUMENTO
				if($Data['doctype'] == 1){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dvct',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','vct1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','dvc',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','vc1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '1'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',",t1.detalle_modular->>0 as detalle_modular,t1.detalle_anuncio->>0 as detalle_anuncio",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "CLIENTE";
				}else if($Data['doctype'] == 2){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dvov',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','vov1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','vov',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','ov1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '1'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',",t1.detalle_modular->>0 as detalle_modular,t1.detalle_anuncio->>0 as detalle_anuncio",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "CLIENTE";
				}else if($Data['doctype'] == 3){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dvem',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','vem1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','vem',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','em1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '1'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',",t1.detalle_modular->>0 as detalle_modular,t1.detalle_anuncio->>0 as detalle_anuncio",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "CLIENTE";
					$detalleSerial = "";
					$serialAct = "";
					$detalleS = "";
					$aver = 0;
					$sqlSerial = "SELECT msn_itemcode,msn_whscode,msn_sn,msn_quantity FROM tmsn WHERE business = :business AND msn_basetype = :msn_basetype AND msn_baseentry = :msn_baseentry  ORDER BY msn_itemcode ASC";
					$tablasSerial = "";

					$resSerial = $this->ci->pedeo->queryTable($sqlSerial, array(
						':business' 	 => $Data['business'],
						':msn_basetype'  => 3,
						':msn_baseentry' => $Data['docentry']
					));

					if ( isset($resSerial[0]) ) {
						
						foreach( $resSerial as $key => $element){
						
							if ($serialAct == "" ) {
								
								$serialAct = $element['msn_itemcode'];

								$detalleS = '<td>'.$element['msn_whscode'].'</td>
												<td>'.$element['msn_sn'].'</td>
												<td>'.$element['msn_quantity'].'</td>';

								$detalleSerial.= '<tr>'.$detalleS.'</tr>';				
											
							} else {


								if ( $serialAct == $element['msn_itemcode'] ){

									$detalleS = '<td>'.$element['msn_whscode'].'</td>
												<td>'.$element['msn_sn'].'</td>
												<td>'.$element['msn_quantity'].'</td>';

									$detalleSerial.= '<tr>'.$detalleS.'</tr>';

									$aver = 1;

								}else{
							
									$tablasSerial.= '<table  width="100%"><tr><th><b>CODIGO ITEM '.$serialAct.'</b></th><hr></tr></table>';
									$tablasSerial.= '<table class="borde" width="100%"><tr><th  style="text-align: center;">ALMACEN</th><th  style="text-align: center;">SERIAL</th><th  style="text-align: center;">CANTIDAD</th></tr>'.$detalleSerial.'</table>';

									$detalleS = "";

									$detalleSerial = "";

									$serialAct = $element['msn_itemcode'];
								}

							} 

						}

						if ($aver == 1 && $tablasSerial == ""){
							$tablasSerial.= '<table width="100%"><tr><th><b>CODIGO ITEM '.$serialAct.'</b></th><hr></tr></table>';
							$tablasSerial.= '<table class="borde" width="100%"><tr><th  style="text-align: center;">ALMACEN</th><th  style="text-align: center;">SERIAL</th><th  style="text-align: center;">CANTIDAD</th></tr>'.$detalleSerial.'</table>';
						}
					}
				}else if($Data['doctype'] == 4){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dvdv',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','vdv1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','vdv',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','dv1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '1'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',",t1.detalle_modular->>0 as detalle_modular,t1.detalle_anuncio->>0 as detalle_anuncio",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "CLIENTE";
				}else if($Data['doctype'] == 5){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dvfv',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','vfv1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','dvf',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','fv1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '1'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',",t1.detalle_modular->>0 as detalle_modular,t1.detalle_anuncio->>0 as detalle_anuncio",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "CLIENTE";
				}else if($Data['doctype'] == 6){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dvnc',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','vnc1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','vnc',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','nc1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '1'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',",t1.detalle_modular->>0 as detalle_modular,t1.detalle_anuncio->>0 as detalle_anuncio",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "CLIENTE";
				}else if($Data['doctype'] == 7){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dvnd',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','vnd1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','vnd',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','nd1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '1'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',",t1.detalle_modular->>0 as detalle_modular,t1.detalle_anuncio->>0 as detalle_anuncio",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "CLIENTE";
				}else if($Data['doctype'] == 34){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dvfv',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','vfv1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','dvf',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','fv1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '1'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',",t1.detalle_modular->>0 as detalle_modular,t1.detalle_anuncio->>0 as detalle_anuncio",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "CLIENTE";
				}else if($Data['doctype'] == 10){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dcsc',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','csc1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','csc',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','sc1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',"",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "PROVEEDOR";
				}else if($Data['doctype'] == 11){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dcoc',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','coc1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','coc',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','oc1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '2'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',"",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "PROVEEDOR";
				}else if($Data['doctype'] == 12){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dcpo',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','cpo1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','cpo',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','po1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '2'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',"",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "PROVEEDOR";
				}else if($Data['doctype'] == 13){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dcec',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','cec1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','cec',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','ec1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '2'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',"",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "PROVEEDOR";
				}else if($Data['doctype'] == 14){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dcdc',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','cdc1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','cdc',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','dc1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '2'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',"",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "PROVEEDOR";
				}else if($Data['doctype'] == 15){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dcfc',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','cfc1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','cfc',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','fc1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '2'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',"",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "PROVEEDOR";
				}else if($Data['doctype'] == 16){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dcnc',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','cnc1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','cnc',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','nc1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '2'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',"",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "PROVEEDOR";
				}else if($Data['doctype'] == 17){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dcnd',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','cnd1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','cnd',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','nd1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '2'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',"",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "PROVEEDOR";
				}else if($Data['doctype'] == 36){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dcsa',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','csa1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','csa',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','sa1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '2'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',"",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "PROVEEDOR";
				}else if($Data['doctype'] == 46){

					//REEMPLAZAR PARAMETROS DE CONSULTA
					$sql = str_replace('{table}','dcfc',$sql);//TABLA CABECERA
					$sql = str_replace('{table1}','cfc1',$sql);//TABLA DETALLE
					$sql = str_replace('{prefijo}','cfc',$sql);//PREFIJO CABECERA
					$sql = str_replace('{prefijo1}','fc1',$sql);//PREFIJO DETALLE
					$sql = str_replace('{where}',"and t2.dms_card_type = '2'",$sql);//WHERE PARA EL TIPO
					$sql = str_replace('{campos}',"",$sql);//CAMPOS JSON
					//ASIGNAR TIPO PROVEEDOR O CLIENTE
					$Type = "PROVEEDOR";
				}
				// print_r($sql);exit;
				$resSql = $this->ci->pedeo->queryTable($sql,array(
                    ':docentry'=>$Data['docentry'], 
                    ':business' => $Data['business']));

				if(!isset($resSql[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $resSql,
							 'mensaje' =>'no se encontro el documento'
						);

						return $respuesta;
				}
				// print_r($resSql);exit;
				$consecutivo = '';

				if($resSql[0]['pgs_mpfn'] == 1){
					$consecutivo = $resSql[0]['numerodocumento'];
				}else{
					$consecutivo = $resSql[0]['numerodocumento'];
				}


				$totaldetalle = '';
				foreach ($resSql as $key => $value) {
					// code...
					$detalle = '<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['referencia'].'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['descripcion'].'</td>
											<td style="border-bottom: dotted;padding-top: 10px;">'.$value['monedadocumento']." ".number_format($value['vrunit'], $DECI_MALES, ',', '.').'</td>
											<td style="border-bottom: dotted;padding-top: 10px;">'.$value['cantidad'].'</td>
											<td style="border-bottom: dotted;padding-top: 10px;">'.$value['monedadocumento']." ".number_format($value['prcdes'], $DECI_MALES, ',', '.').'</td>
											<td style="border-bottom: dotted;padding-top: 10px;">'.$value['monedadocumento']." ".number_format($value['ivap'], $DECI_MALES, ',', '.').'</td>
											<td style="border-bottom: dotted;padding-top: 10px;">'.$value['monedadocumento']." ".number_format($value['valortotall'], $DECI_MALES, ',', '.').'</td>';
				 $totaldetalle = $totaldetalle.'<tr style="margin-bottom: 50px;">'.$detalle.'</tr>';
				}

		// print_r(json_decode($resSql[0]['detalle_anuncio'],true));exit;
		//ARMAR PARTE DE ANUNCIONS
		$anuncios = "";
		$th_a = "";
		$td_a = "";
		$sql_anuncio = "SELECT 
							t.bta_name as tipo,
							t1.bts_name as suplemento,
							t2.tav_name as tipoaviso,
							get_recargos_name(get_recargos(v.vc1_docentry)) as recargo, -- RECARGOS
							a.epe_name as opcion,
							t3.bca_name as categoria,
							jsonb_set(
								jsonb_set(
									jsonb_set(
										jsonb_set(
											jsonb_set(
												(v.detalle_anuncio->0)::jsonb,
												'{tipo}', to_jsonb(t.bta_name) 
											),
											'{suplemento}', to_jsonb(t1.bts_name)
										),
										'{tipoaviso}', to_jsonb(t2.tav_name)
									),
									'{recargos}', to_jsonb(get_recargos_name(get_recargos(v.vc1_docentry)))
								),
								'{categoria}','null'::jsonb
							) as detalle_anuncio1,
							jsonb_set(
								jsonb_set(
									(v.detalle_anuncio->0)::jsonb,
									'{tipo}',to_jsonb(t.bta_name) 
								),
								'{opcion}',to_jsonb(a.epe_name) 
							)as detalle_anuncio2,
							jsonb_set(
								jsonb_set(
									jsonb_set(
										jsonb_set(
											jsonb_set(
												(v.detalle_anuncio->0)::jsonb,
												'{tipo}', to_jsonb(t.bta_name) 
											),
											'{suplemento}', to_jsonb(t1.bts_name)
										),
										'{tipoaviso}', to_jsonb(t2.tav_name)
									),
									'{recargos}', to_jsonb(get_recargos_name(get_recargos(v.vc1_docentry)))
								),
								'{categoria}',to_jsonb(t3.bca_name)
							) as detalle_anuncio3
						FROM {table1} v
						LEFT JOIN tbta t ON v.detalle_anuncio->0->>'tipo' = t.bta_id::text -- TIPO DE ANUNCIO
						LEFT JOIN tbts12 t1 ON v.detalle_anuncio->0->>'suplemento' = t1.bts_id::text -- TIPO DE SUPLEMENTO
						LEFT JOIN ttav t2 ON v.detalle_anuncio->0->>'tipoaviso' = t2.tav_id::text -- TIPO DE AVISO
						left join aepe a on v.detalle_anuncio->0->>'opcion' = a.epe_id::text --OPCION
						left join tbca t3 on v.detalle_anuncio->0->>'categoria' = t3.bca_id::text --CATEGORIA
						WHERE v.{prefijo1}_docentry= :docentry";

		if($Data['doctype'] == 1){

			//REEMPLAZAR PARAMETROS DE CONSULTA
			$sql_anuncio = str_replace('{table1}','vct1',$sql_anuncio);//TABLA DETALLE
			$sql_anuncio = str_replace('{prefijo1}','vc1',$sql_anuncio);//PREFIJO DETALLE
		}else if($Data['doctype'] == 2){

			//REEMPLAZAR PARAMETROS DE CONSULTA
			$sql_anuncio = str_replace('{table1}','vov1',$sql_anuncio);//TABLA DETALLE
			$sql_anuncio = str_replace('{prefijo1}','ov1',$sql_anuncio);//PREFIJO DETALLE
		}else if($Data['doctype'] == 3){

			//REEMPLAZAR PARAMETROS DE CONSULTA
			$sql_anuncio = str_replace('{table1}','vem1',$sql_anuncio);//TABLA DETALLE
			$sql_anuncio = str_replace('{prefijo1}','em1',$sql_anuncio);//PREFIJO DETALLE
		}else if($Data['doctype'] == 4){

			//REEMPLAZAR PARAMETROS DE CONSULTA
			$sql_anuncio = str_replace('{table1}','vdv1',$sql_anuncio);//TABLA DETALLE
			$sql_anuncio = str_replace('{prefijo1}','dv1',$sql_anuncio);//PREFIJO DETALLE
		}else if($Data['doctype'] == 5){
			//REEMPLAZAR PARAMETROS DE CONSULTA
			$sql_anuncio = str_replace('{table1}','vfv1',$sql_anuncio);//TABLA DETALLE
			$sql_anuncio = str_replace('{prefijo1}','fv1',$sql_anuncio);//PREFIJO DETALLE
		}else if($Data['doctype'] == 6){

			//REEMPLAZAR PARAMETROS DE CONSULTA
			$sql_anuncio = str_replace('{table1}','vnc1',$sql_anuncio);//TABLA DETALLE
			$sql_anuncio = str_replace('{prefijo1}','nc1',$sql_anuncio);//PREFIJO DETALLE

		}else if($Data['doctype'] == 7){

			//REEMPLAZAR PARAMETROS DE CONSULTA
			$sql_anuncio = str_replace('{table1}','vnd1',$sql_anuncio);//TABLA DETALLE
			$sql_anuncio = str_replace('{prefijo1}','nd1',$sql_anuncio);//PREFIJO DETALLE

		}else if($Data['doctype'] == 34){

			//REEMPLAZAR PARAMETROS DE CONSULTA
			$sql_anuncio = str_replace('{table1}','vfv1',$sql_anuncio);//TABLA DETALLE
			$sql_anuncio = str_replace('{prefijo1}','fv1',$sql_anuncio);//PREFIJO DETALLE

		}

		$resSqlAnuncio = $this->ci->pedeo->queryTable($sql_anuncio,array(
			':docentry'=>$Data['docentry']
		));
		// print_r($resSqlAnuncio[0]);exit;
		$detalle_anuncio = "";
		$title_a = "";
		if(isset($resSqlAnuncio[0]['tipo']) && !empty($resSqlAnuncio[0]['tipo'])){
			if(isset($resSqlAnuncio[0]['detalle_anuncio1']) && json_decode($resSqlAnuncio[0]['detalle_anuncio1'])->categoria == null &&
			json_decode($resSqlAnuncio[0]['detalle_anuncio1'])->tipo != "Libre"){
				$detalle_anuncio = json_decode($resSqlAnuncio[0]['detalle_anuncio1'],true);
				unset($detalle_anuncio['categoria']);
				unset($detalle_anuncio['recargo']);
			}else if (isset($resSqlAnuncio[0]['detalle_anuncio2'])){
				$detalle_anuncio = json_decode($resSqlAnuncio[0]['detalle_anuncio2'],true);
			}else if(isset($resSqlAnuncio[0]['detalle_anuncio3']) && json_decode($resSqlAnuncio[0]['detalle_anuncio3'])->categoria != null){
				$detalle_anuncio = json_decode($resSqlAnuncio[0]['detalle_anuncio3'],true);
			}
			$v_d = "";
			foreach ($detalle_anuncio as $key => $value) {
				$th_a .= '<th style="padding-top: 10px;"><b>'.$key.'</b></th>';
			}
			foreach ($detalle_anuncio as $key => $value) {
				if(isset($value) && is_array($value) ) {
					$v_d = implode(",",$value);
				}else {
					$v_d = $value;
				}
				$td_a .= '<td style="padding-top: 10px;">'.$v_d.'</td>';
			}
			$title_a = '<h4 style="text-align: left;">Información de anuncio</h4>';
			$anuncios = $title_a.'<table class="borde" style="width:100%"> <tr> '.$th_a.'</tr><tr>'.$td_a.'</tr> </table>';
		}

		// print_r($anuncios);exit;
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
                	<th  width="33%" style="font-size: 8px;"> Documento generado por <span style="color: #fcc038; font-weight: bolder;"> JOINTERP </span> para: '.$empresa[0]['pge_small_name'].'. Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            	</tr>
        	</table>';


        $html = '
			<table  width="100%" >
        		<tr>
		            <th style="text-align: left;">
						<p><b>'.$empresa[0]['pge_small_name'].'</b></p>
						<p>'.$empresa[0]['pge_add_soc'].'</p>
						<p>'.$empresa[0]['pge_id_type'].'</p>
						<p>'.$empresa[0]['pge_state_soc'].'</p>
						<p>TELEFONO: '.$empresa[0]['pge_phone1'].'</p>
						<p>website: '.$empresa[0]['pge_web_site'].'</p>
						<p>Instagram: '.$empresa[0]['pge_page_social'].'</p>
            		</th>
            		<th style="text-align: right;">
						<p><b>'.$resSql[0]['mdt_docname'].' #: </b></p>
					</th>
					<th style="text-align: left;">
                		<p >'.$resSql[0]['numerodocumento'].'</p>
					</th>
					<th style="text-align: right;">
						<th style="text-align: right;">
							<p><b>FECHA DE EMISIÓN: </b></p>
						</th>
					<th style="text-align: left;">
						<p>'.$this->ci->dateformat->Date($resSql[0]['fechadocumento']).'</p>
            		</th>
        		</tr>
        	</table>
			<table width="100%" style="vertical-align: bottom; font-family: serif;font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th class="fondo">
                    	<p></p>
                	</th>
            	</tr>
        	</table>
			
			<table  width="100%" font-family: serif>
				<tr>
					<!-- Columna Izquierda DATOS DEL CLIENTE-->
					<td style="width: 50%; heigth="100%"; vertical-align: top;">

						<table>
							<tr>
								<td style="text-align: rigth;"><b>DATOS DEL '.$Type.'</b><th>
							</tr>
							<tr>
								<td style="text-align: rigth;" ><b>NIT/CC:</b><span> '.$resSql[0]['nit'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;"><b>nombre '.$Type.':</b> <span>'.$resSql[0]['cliente'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;"><b>ciudad '.$Type.':</b> <span>'.$resSql[0]['ciudad'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;"><b>Municipio '.$Type.':</b> <span>'.$resSql[0]['estado'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;"><b>Telefono del  '.$Type.':</b> <span>'.$resSql[0]['telefono'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;"><b>Correo del '.$Type.':</b> <span>'.$resSql[0]['email'].'</span></p></td>
							</tr>
						</table>
					</td>
					<!-- Columna dERECHA DATOS DEL CONTACTO-->
					<td style="width: 40%; vertical-align: top;">
						<table>
							<tr>
								<td style="text-align: rigth;"><b>DATOS DE PERSONA DE CONTACTO</b><th>
							</tr>
							<tr>
								<td style="text-align: rigth;" ><b>Id Contacto:</b><span> '.$resSql[0]['idcontact'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;" ><b>nombre contacto:</b><span> '.$resSql[0]['nombre_contacto_p'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;" ><b>telefono contacto:</b><span> '.$resSql[0]['telefono_contacto_p'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;" ><b>correo contacto:</b><span> '.$resSql[0]['correo_contacto_p'].'</span></p></td>
							</tr>
						</table>
					</td>
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
				<tr >
					<th style="padding-top: 10px;"><b>ITEM</b></th>
					<th style="padding-top: 10px;"><b>REFERENCIA</b></th>
					<th style="padding-top: 10px;"><b>PRECIO</b></th>
					<th style="padding-top: 10px;"><b>CANT</b></th>
					<th style="padding-top: 10px;"><b>DTO</b></th>
					<th style="padding-top: 10px;"><b>IVA</b></th>
					<th style="padding-top: 10px;"><b>TOTAL</b></th>
				</tr>
				'.$totaldetalle.'
			</table>
        	<br>
			<table width="100%" style="vertical-align: bottom; font-family: serif;font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
				<tr>
					<th class="fondo">
						<p></p>
					</th>
				</tr>
        	</table>
        	<br>
       		<table width="100%">
				<tr>
					<!-- Columna Izquierda DATOS DEL CLIENTE-->
					<td style="width: 50%; heigth="100%"; vertical-align: top;">
					
					'.$anuncios.'
					</td>
					<!-- Columna dERECHA DATOS DEL CONTACTO-->
					<td style="width: 40%; vertical-align: top;">
						<table style="width:100%">
							<tr>
								<td style="text-align: right;"><b>Base Documento:</b>  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['base'], $DECI_MALES, ',', '.').'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: right;"><b>Descuento:</b>  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['descuento'], $DECI_MALES, ',', '.').'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: right;"><b>Sub Total:</b>  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['subtotal'], $DECI_MALES, ',', '.').'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: right;"><b>Impuestos:</b>  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['iva'], $DECI_MALES, ',', '.').'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: right;"><b>Total:</b>  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['totaldoc'], $DECI_MALES, ',', '.').'</span></p></td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<table  width="100%">
				<tr>
            		<td style="text-align: left;"><b>comentarios:</b><br><p>'.$resSql[0]['comentarios'].'</p></td>
        		</tr>
        	</table>
			<br>
			<br>
        	<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th style="text-align: left;" >
						<p><b>VALOR EN LETRAS:</b></p><br>
                    	<p>'.$formatter->toWords($resSql[0]['totaldoc'],$DECI_MALES)." ".$resSql[0]['nombremoneda'].'</p>
                	</th>
            	</tr>
        	</table>
		</html>';

        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');
		$mpdf->SetDefaultBodyCSS('background', "url('/var/www/html/".$company[0]['company']."/assets/img/background.png')");
        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);

        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);
		// print_r($aver);exit;
		if ($aver){

            $mpdf->AddPage();
            $mpdf->WriteHTML($tablasSerial, \Mpdf\HTMLParserMode::HTML_BODY);
        }

		$filename = $resSql[0]['mdt_docname'].'_'.$resSql[0]['numerodocumento'].'.pdf';
		if($type_mpdf == "D"){
			$mpdf->Output($filename,$type_mpdf);
			header('Content-type: application/force-download');
			header('Content-Disposition: attachment; filename='.$filename);
		}else if($type_mpdf == "F"){
			$mpdf->Output(ROUTE_LOCAL . $filename, \Mpdf\Output\Destination::FILE);
			return array(
				'file' => ROUTE_LOCAL.$filename,
				'data' => array(
					'docnum' => $consecutivo,
					'cardname' => $resSql[0]['cliente'],
					'total' => $resSql[0]['monedadocumento']." ".number_format($resSql[0]['totaldoc'], $DECI_MALES, ',', '.'),
					'type' => $Type
				)
			);
		}
	}

	public function formatTermicaDelivery($Data)
    {

		$DECI_MALES =  $this->ci->generic->getDecimals();

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
        $company = $this->ci->pedeo->queryTable("SELECT main_folder company FROM PARAMS",array());

        if(!isset($company[0])){
			$respuesta = array(
					'error' => true,
					'data'  => $company,
					'mensaje' =>'no esta registrada la ruta de la empresa'
			);

			return $respuesta;
        }

		//INFORMACION DE LA EMPRESA

		$empresa = $this->ci->pedeo->queryTable("SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
		pge_cou_soc, pge_id_soc AS pge_id_type , pge_web_site, pge_logo,
		CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
		pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type
		FROM pgem WHERE pge_id = :pge_id", array(':pge_id' => $Data['business']));

		if(!isset($empresa[0])){
				$respuesta = array(
				'error' => true,
				'data'  => $empresa,
				'mensaje' =>'no esta registrada la información de la empresa'
			);

			return $respuesta;
		}

		$sqlcotizacion = "SELECT distinct
							t0.vrc_docentry,
							t0.business ,
							t2.dms_poscode,
							t0.vrc_createby ,
							concat(T2.dms_card_name,' ',T2.dms_card_last_name) Cliente,
							T0.vrc_cardcode Nit,
							T3.dmd_adress Direccion,
							T3.dmd_state_mm ciudad,
							t3.dmd_state estado,
							T2.dms_phone1 Telefono,
							T2.dms_email Email,
							t0.vrc_docnum,
							ConCAT(T6.pgs_pref_num,' ',T0.vrc_docnum) NumeroDocumento,
							to_char(T0.vrc_docdate,'DD-MM-YYYY') FechaDocumento,
							to_char(T0.vrc_duedate,'DD-MM-YYYY') FechaVenDocumento,
							t0.vrc_currency as MonedaDocumento,
							T7.pgm_name_moneda NOMBREMonEDA,
							T5.mev_names Vendedor,
							t8.mpf_name CondPago,
							t1.rc1_linenum linea,
							coalesce(t1.rc1_lote,T1.rc1_itemcode) as lote,
							T1.rc1_itemcode Referencia,
							T1.rc1_itemname descripcion,
							T1.rc1_whscode Almacen,
							T1.rc1_uom UM,
							T1.rc1_quantity Cantidad,
							T1.rc1_price VrUnit,
							T1.rc1_discount PrcDes,
							T1.rc1_vatsum IVAP,
							T1.rc1_linetotal ValorTotalL,
							T0.vrc_baseamnt base,
							T0.vrc_discount Descuento,
							(T0.vrc_baseamnt - T0.vrc_discount) subtotal,
							T0.vrc_taxtotal Iva,
							T0.vrc_total_c  TotalDoc,
							T0.vrc_comment Comentarios,
							0 peso,
							t10.dmu_nameum  um_name,
							t0.vrc_docdate,
							t6.pgs_mpfn,
							t6.pgs_mde,
							t1.rc1_quantity,
							t2.dms_rtype regimen
							from dvrc t0
							inner join vrc1 T1 on t0.vrc_docentry = t1.rc1_docentry
							left join dmsn T2 on t0.vrc_cardcode = t2.dms_card_code
							left join dmsd T3 on T0.vrc_cardcode = t3.dmd_card_code AND t3.dmd_ppal = 1
							left join dmsc T4 on T0.vrc_cardcode = t4.dmc_card_code
							left join dmev T5 on T0.vrc_slpcode = T5.mev_id
							left join pgdn T6 on T0.vrc_doctype = T6.pgs_id_doc_type and T0.vrc_series = T6.pgs_id
							left join pgec T7 on T0.vrc_currency = T7.pgm_symbol
							left join dmpf t8 on t2.dms_pay_type = cast(t8.mpf_id as varchar)
							left join dmar t9 on t1.rc1_itemcode = t9.dma_item_code
							left join dmum t10 on t1.rc1_uom  = t10.dmu_code  
							where T0.vrc_docentry = :docentry AND t0.business = :business";
				// print_r($sqlcotizacion);exit;
		$contenidoFV = $this->ci->pedeo->queryTable($sqlcotizacion,array(
			':docentry'=> $Data['docentry'],
			':business' => $Data['business']
		));
        // print_r($sqlcotizacion);exit;

		if(!isset($contenidoFV[0])){
			$respuesta = array(
					'error' => true,
					'data'  => $empresa,
					'mensaje' =>'no se encontro el documento'
			);

			return $respuesta;
		}

		$totaldetalle = '';
		$items_name = "";
		$TotalCantidad = 0;
		$TotalPeso = 0;
		$count = 1; 
		$detailHeader = "";
		foreach ($contenidoFV as $key => $value) {
			$detailHeader .= '
			<table width="100%">
				<tr>
					<th>
						<p>'.$value['dms_poscode'].' - '.$value['cliente'].'</p>
						<p>'.$value['referencia'].'</p>
						<p>'.$value['lote'].'</p>
						<p>'.$value['fechadocumento'].'</p>
						<br>
						<h1 style="font-size: 65px;">'.round($value['cantidad']).'</h1>
						<br>
						<br>
					</th>
				</tr>
				<tr>
					<th style="text-align: left;">
						<p>'.$value['fechadocumento'].'</p>
						<p>CAJERO-'.$value['vrc_createby'].'</p>
						<p>'.date("H:i:s").'</p>
					</th>
				</tr>
			</table>
			<br>';
		}

        $header = $detailHeader;

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
                <th class="" width="33%">Factura generada por JOINTERP Para LA RAZON <br> Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';


        
		// print_r($html);exit();die(); 
        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        // $mpdf->SetHTMLFooter($footer);

        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        // $mpdf->WriteHTML($html);
        $mpdf->Output('Doc.pdf', 'D');

		header('Content-type: application/force-download');
		header('Content-Disposition: attachment; filename='.$filename);


	}

	public function formatTermicaVenta($Data)
    {

		$DECI_MALES =  $this->ci->generic->getDecimals();

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
        $company = $this->ci->pedeo->queryTable("SELECT main_folder company FROM PARAMS",array());

        if(!isset($company[0])){
                $respuesta = array(
                        'error' => true,
                        'data'  => $company,
                        'mensaje' =>'no esta registrada la ruta de la empresa'
                );

                return $respuesta;
        }

				//INFORMACION DE LA EMPRESA

				$empresa = $this->ci->pedeo->queryTable("SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
				pge_cou_soc, pge_id_soc AS pge_id_type , pge_web_site, pge_logo,
				CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
				pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type
				FROM pgem WHERE pge_id = :pge_id", array(':pge_id' => $Data['business']));

				if(!isset($empresa[0])){
						$respuesta = array(
		           'error' => true,
		           'data'  => $empresa,
		           'mensaje' =>'no esta registrada la información de la empresa'
		        );

				return $respuesta;
				}

				$sqlcotizacion = "SELECT distinct
									t0.vrc_docentry,
									t0.business ,
									t2.dms_poscode,
									t0.vrc_createby ,
									concat(T2.dms_card_name,' ',T2.dms_card_last_name) Cliente,
									T0.vrc_cardcode Nit,
									T3.dmd_adress Direccion,
									T3.dmd_state_mm ciudad,
									t3.dmd_state estado,
									T2.dms_phone1 Telefono,
									T2.dms_email Email,
									t0.vrc_docnum,
									ConCAT(T6.pgs_pref_num,' ',T0.vrc_docnum) NumeroDocumento,
									to_char(T0.vrc_docdate,'DD-MM-YYYY') FechaDocumento,
									to_char(T0.vrc_duedate,'DD-MM-YYYY') FechaVenDocumento,
									t0.vrc_currency as MonedaDocumento,
									T7.pgm_name_moneda NOMBREMonEDA,
									T5.mev_names Vendedor,
									t8.mpf_name CondPago,
									t1.rc1_linenum linea,
									coalesce(t1.rc1_lote,T1.rc1_itemcode) as lote,
									T1.rc1_itemcode Referencia,
									T1.rc1_itemname descripcion,
									T1.rc1_whscode Almacen,
									T1.rc1_uom UM,
									T1.rc1_quantity Cantidad,
									T1.rc1_price VrUnit,
									T1.rc1_discount PrcDes,
									T1.rc1_vatsum IVAP,
									T1.rc1_linetotal ValorTotalL,
									T0.vrc_baseamnt base,
									T0.vrc_discount Descuento,
									(T0.vrc_baseamnt - T0.vrc_discount) subtotal,
									T0.vrc_taxtotal Iva,
									T0.vrc_total_c  TotalDoc,
									T0.vrc_comment Comentarios,
									0 peso,
									t10.dmu_nameum  um_name,
									t0.vrc_docdate,
									t6.pgs_mpfn,
									t6.pgs_mde,
									t1.rc1_quantity,
									t2.dms_rtype regimen,
									t0.vrc_pasanaku
									from dvrc t0
									inner join vrc1 T1 on t0.vrc_docentry = t1.rc1_docentry
									left join dmsn T2 on t0.vrc_cardcode = t2.dms_card_code
									left join dmsd T3 on T0.vrc_cardcode = t3.dmd_card_code AND t3.dmd_ppal = 1
									left join dmsc T4 on T0.vrc_cardcode = t4.dmc_card_code
									left join dmev T5 on T0.vrc_slpcode = T5.mev_id
									left join pgdn T6 on T0.vrc_doctype = T6.pgs_id_doc_type and T0.vrc_series = T6.pgs_id
									left join pgec T7 on T0.vrc_currency = T7.pgm_symbol
									left join dmpf t8 on t2.dms_pay_type = cast(t8.mpf_id as varchar)
									left join dmar t9 on t1.rc1_itemcode = t9.dma_item_code
									left join dmum t10 on t1.rc1_uom  = t10.dmu_code  
									where T0.vrc_docentry = :docentry AND t0.business = :business";
				// print_r($sqlcotizacion);exit;
				$contenidoFV = $this->ci->pedeo->queryTable($sqlcotizacion,array(
					':docentry'=> $Data['docentry'],
					':business' => $Data['business']
				));
                // print_r($sqlcotizacion);exit;

				if(!isset($contenidoFV[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $empresa,
							 'mensaje' =>'no se encontro el documento'
						);

						return $respuesta;
				}

				$totaldetalle = '';
				$items_name = "";
				$TotalCantidad = 0;
				$TotalPeso = 0;
                $count = 1; 
				$total_linea = 0;
				foreach ($contenidoFV as $key => $value) {
                    $detalle = '
							<td>'.$value['referencia'].'</td>
							<td>'.$value['cantidad'].'</td>
							<td>'.$value['monedadocumento']." ".number_format($value['vrunit'], $DECI_MALES, ',', '.').'</td>
							<td>'.$value['monedadocumento']." ".number_format($value['valortotall'], $DECI_MALES, ',', '.').'</td>';
					$totaldetalle = $totaldetalle.'<tr style="margin-bottom: 50px;">'.$detalle.'</tr>';
					$total_linea += $value['valortotall'];


	    		}

				$valorTotalBase = $contenidoFV[0]['base'];
				$valorTotalSubtotal = $contenidoFV[0]['subtotal'];
				$valorTotalIva = $contenidoFV[0]['iva'];
				$valorTotalDoc = $contenidoFV[0]['totaldoc'];

				$consecutivo = '';

				if($contenidoFV[0]['pgs_mpfn'] == 1){
					$consecutivo = $contenidoFV[0]['numerodocumento'];
				}else{
					$consecutivo = $contenidoFV[0]['vrc_docnum'];
				}

        $header = '
        <table width="100%">
        <tr>
            <th>
				<p>RESUMEN</p>
				<p>'.$contenidoFV[0]['fechadocumento'].'</p>
                <p>'.$contenidoFV[0]['dms_poscode'].'-'.$contenidoFV[0]['cliente'].'</p>
				<br>
            </th>
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
                <th class="" width="33%">Factura generada por JOINTERP Para LA RAZON <br> Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';


        $html = '
        <br>
        <br>
        <br>
		<br>
		<h5>VENTA</h5>
            <table width="100%">
                <tr class="">
                    <th>Lote/Item</th>
					<th>Cant.</th>
                    <th>Precio</th>
                    <th>Total</th>
                </tr>
				<br>
      	        '.$totaldetalle.'
            </table>
			<br>
			<table width="100%">
				<tr>
					<!-- Columna Izquierda DATOS DEL CLIENTE-->
					<td style="width: 50%; heigth="100%"; vertical-align: top;">
						<table style="width:100%">
							<tr>
								<td style="text-align: left;">SUB TOTAL:</td>
							</tr>
							<tr>
								<td style="text-align: left;">TOTAL A PAGAR:</td>
							</tr>
							<tr>
								<td style="text-align: left;">A CTA. EJEMPLAR:</td>
							</tr>
							<tr>
								<td style="text-align: left;">PASANAKU:</td>
							</tr>
							<tr>
								<td style="text-align: left;">TOTAL PAGADO:</td>
							</tr>
							<tr>
								<td style="text-align: left;">EFECTIVO RECIBIDO:</td>
							</tr>
							<tr>
								<td style="text-align: left;">CAMBIO:</td>
							</tr>
							
						</table>
					</td>
					<!-- Columna dERECHA DATOS DEL CONTACTO-->
					<td style="width: 40%; vertical-align: top;">
						<table style="width:100%">
							<tr>
								<td style="text-align: right;">'.$contenidoFV[0]['monedadocumento']." ".number_format($total_linea, $DECI_MALES, ',', '.').'</td>
							</tr>
							<tr>
								<td style="text-align: right;">'.$contenidoFV[0]['monedadocumento']." ".number_format($total_linea, $DECI_MALES, ',', '.').'</td>
							</tr>
							<tr>
								<td style="text-align: right;">'.$contenidoFV[0]['monedadocumento']." ".number_format($total_linea + $contenidoFV[0]['vrc_pasanaku'], $DECI_MALES, ',', '.').'</td>
							</tr>
							<tr>
								<td style="text-align: right;">'.$contenidoFV[0]['monedadocumento']." ".number_format($contenidoFV[0]['vrc_pasanaku'], $DECI_MALES, ',', '.').'</td>
							</tr>
							<tr>
								<td style="text-align: right;">'.$contenidoFV[0]['monedadocumento']." ".number_format($total_linea + $contenidoFV[0]['vrc_pasanaku'], $DECI_MALES, ',', '.').'</td>
							</tr>
							<tr>
								<td style="text-align: right;">'.$contenidoFV[0]['monedadocumento']." ".number_format($total_linea + $contenidoFV[0]['vrc_pasanaku'], $DECI_MALES, ',', '.').'</td>
							</tr>
							<tr>
								<td style="text-align: right;">'.$contenidoFV[0]['monedadocumento']." ".number_format(0, $DECI_MALES, ',', '.').'</td>
							</tr>
							
						</table>
					</td>
				</tr>
			</table>
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


	public function formatLegalExpenses($Data)
    {

		$DECI_MALES =  $this->ci->generic->getDecimals();

        $formatter = new NumeroALetras();

		$mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch','default_font' => 'dejavusans']);        

        //RUTA DE CARPETA EMPRESA
        $company = $this->ci->pedeo->queryTable("SELECT main_folder company FROM PARAMS",array());

        if(!isset($company[0])){
                $respuesta = array(
                        'error' => true,
                        'data'  => $company,
                        'mensaje' =>'no esta registrada la ruta de la empresa'
                );

                return $respuesta;
        }

				//INFORMACION DE LA EMPRESA

				$empresa = $this->ci->pedeo->queryTable("SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
				pge_cou_soc, pge_id_soc AS pge_id_type , pge_web_site, pge_logo,
				CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
				pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type, pge_page_social
				FROM pgem WHERE pge_id = :pge_id", array(':pge_id' => $Data['business']));

				if(!isset($empresa[0])){
						$respuesta = array(
		           'error' => true,
		           'data'  => $empresa,
		           'mensaje' =>'no esta registrada la información de la empresa'
		        );

				return $respuesta;
				}
				$Type = "Cliente";

				$sqllegalizacion= "SELECT
				CONCAT(t0.blg_CARDNAME,' ',T2.DMS_CARD_LAST_NAME) Cliente,
				t0.blg_CARDCODE Nit,
				CONCAT(T3.DMD_ADRESS,' ',T3.DMD_CITY) AS Direccion,
				T11.pdm_states ciudad,
				t11.pdm_municipality estado,
				T2.dms_phone1 Telefono,
				T2.dms_email Email,
				t0.blg_DOCNUM,
				t0.blg_DOCNUM NumeroDocumento,
				t0.blg_DOCDATE FechaDocumento,
				t0.blg_DUEDATE FechaVenDocumento,
				t0.blg_duedev fechaentrga,
				t0.blg_CURRENCY MonedaDocumento,
				T7.PGM_NAME_MONEDA NOMBREMONEDA,
				T5.MEV_NAMES Vendedor,
				'' MedioPago,
				'' CondPago,
				t1.lg1_cardcode Referencia,
				t1.lg1_cardname descripcion,
				'' Almacen,
				0 UM,
				0 Cantidad,
				t1.lg1_price VrUnit,
				0 PrcDes,
				t1.lg1_vat IVAP,
				t1.lg1_total ValorTotalL,
				t0.blg_BASEAMNT base,
				t0.blg_DISCOUNT Descuento,
				(t0.blg_BASEAMNT - t0.blg_DISCOUNT) subtotal,
				t0.blg_TAXTOTAL Iva,
				t0.blg_DOCTOTAL TotalDoc,
				t0.blg_COMMENT Comentarios,
				t6.pgs_mde,
				t6.pgs_mpfn,
				T8.MPF_NAME cond_pago,
				t3.dmd_tonw lugar_entrega,
				t5.mev_names nombre_contacto,
				t5.mev_mail correo_contacto,
				t5.mev_phone telefono_contacto,
				t9.dmc_contac_id as idcontact,
				concat(t9.dmc_name,' ',t9.dmc_last_name) nombre_contacto_p,
				t9.dmc_email correo_contacto_p,
				t9.dmc_cel telefono_contacto_p,
				t10.mdt_docname
				FROM tblg t0
				INNER JOIN blg1 T1 ON t0.blg_docentry = t1.lg1_docentry
				LEFT JOIN DMSN T2 ON t0.blg_cardcode = t2.dms_card_code
				LEFT JOIN DMSD T3 ON t2.dms_card_code = t3.dmd_card_code AND t3.dmd_ppal = 1
				LEFT JOIN DMSC T4 ON t0.blg_CONTACID = CAST(T4.DMC_ID AS VARCHAR)
				LEFT JOIN DMEV T5 ON t0.blg_SLPCODE = T5.MEV_ID
				LEFT JOIN PGDN T6 ON t0.blg_DOCTYPE = T6.PGS_ID_DOC_TYPE AND t0.blg_SERIES = T6.PGS_ID
				LEFT JOIN PGEC T7 ON t0.blg_CURRENCY = T7.PGM_SYMBOL
				LEFT JOIN DMPF T8 ON T2.DMS_PAY_TYPE = cast(T8.MPF_ID as varchar)
				LEFT JOIN dmsc t9 ON t0.blg_cardcode = t9.dmc_card_code and T9.dmc_ppal = 1
				INNER JOIN dmdt t10 ON t0.blg_doctype = t10.mdt_doctype
				left join tpdm t11 on t3.dmd_state = t11.pdm_codstates::text and t3.dmd_city = t11.pdm_codmunicipality
				WHERE t0.blg_docentry= :docentry and t2.dms_card_type = '2' and t0.business = :business";
				$resSql = $this->ci->pedeo->queryTable($sqllegalizacion,array(
					':docentry'=> $Data['docentry'],
					':business' => $Data['business']
				));
				
				if(!isset($resSql[0])){
						$respuesta = array(
							 'error' => true,
							 'data'  => $empresa,
							 'mensaje' =>'no se encontro el documento'
						);

						return $respuesta;
				}

				$totaldetalle = '';
				$items_name = "";
				$TotalCantidad = 0;
				$TotalPeso = 0;
                $count = 1; 
				$total_linea = 0;
				$sqlDetalle = "SELECT * from blg1 where lg1_docentry = :docentry";
				$resSelectDetalle = $this->ci->pedeo->queryTable($sqlDetalle, array(":docentry" => $Data['docentry']));
				foreach ($resSelectDetalle as $key => $value) {
					// code...
					$detalle = '<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['lg1_linenum'].'</td>
											<td style="text-align: center; padding-left: 10px; padding-right: 10px;padding-top: 10px;border-bottom: dotted">'.$value['lg1_cardcode'].'</td>
											<td style="border-bottom: dotted;padding-top: 10px; text-align: justify; ">'.$value['lg1_cardname'].'</td>
											<td style="border-bottom: dotted;padding-top: 10px; text-align: justify;">'.$value['lg1_concept'].'</td>
											<td style="border-bottom: dotted;padding-top: 10px; text-align: center;">'.$value['lg1_docdate'].'</td>
											<td style="border-bottom: dotted;padding-top: 10px; text-align: center;">'.$value['lg1_account'].'</td>
											<td style="border-bottom: dotted;padding-top: 10px; text-align: center;">'.$value['lg1_currency']." ".number_format($value['lg1_price'], $DECI_MALES, ',', '.').'</td>
											<td style="border-bottom: dotted;padding-top: 10px; text-align: center;">'.$value['lg1_currency']." ".number_format($value['lg1_vatsum'], $DECI_MALES, ',', '.').'</td>
											<td style="border-bottom: dotted;padding-top: 10px; text-align: center;">'.$value['lg1_currency']." ".number_format($value['lg1_total'], $DECI_MALES, ',', '.').'</td>';				 $totaldetalle = $totaldetalle.'<tr style="margin-bottom: 50px;">'.$detalle.'</tr>';
				}

				$valorTotalBase = $resSql[0]['base'];
				$valorTotalSubtotal = $resSql[0]['subtotal'];
				$valorTotalIva = $resSql[0]['iva'];
				$valorTotalDoc = $resSql[0]['totaldoc'];

				$consecutivo = '';

				if($resSql[0]['pgs_mpfn'] == 1){
					$consecutivo = $resSql[0]['numerodocumento'];
				}else{
					$consecutivo = $resSql[0]['blg_docnum'];
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
                <th class="" width="33%">Factura generada por JOINTERP Para LA RAZON <br> Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
            </tr>
        </table>';


        $html = '
		<!DOCTYPE html>
			<html lang="es">
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<title>Document</title>
			</head>
			<body>
			<table  width="100%" >
        		<tr>
		            <th style="text-align: left;">
						<p><b>'.$empresa[0]['pge_small_name'].'</b></p>
						<p>'.$empresa[0]['pge_add_soc'].'</p>
						<p>'.$empresa[0]['pge_id_type'].'</p>
						<p>'.$empresa[0]['pge_state_soc'].'</p>
						<p>TELEFONO: '.$empresa[0]['pge_phone1'].'</p>
						<p>website: '.$empresa[0]['pge_web_site'].'</p>
						<p>Instagram: '.$empresa[0]['pge_page_social'].'</p>
            		</th>
            		<th style="text-align: right;">
						<p><b>'.$resSql[0]['mdt_docname'].' #: </b></p>
					</th>
					<th style="text-align: left;">
                		<p >'.$resSql[0]['numerodocumento'].'</p>
					</th>
					<th style="text-align: right;">
						<th style="text-align: right;">
							<p><b>FECHA DE EMISIÓN: </b></p>
						</th>
					<th style="text-align: left;">
						<p>'.$this->ci->dateformat->Date($resSql[0]['fechadocumento']).'</p>
            		</th>
        		</tr>
        	</table>
			<table width="100%" style="vertical-align: bottom; font-family: serif;font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th class="fondo">
                    	<p></p>
                	</th>
            	</tr>
        	</table>
			
			<table  width="100%" font-family: serif>
				<tr>
					<!-- Columna Izquierda DATOS DEL CLIENTE-->
					<td style="width: 50%; heigth="100%"; vertical-align: top;">

						<table>
							<tr>
								<td style="text-align: rigth;"><b>DATOS DEL '.$Type.'</b><th>
							</tr>
							<tr>
								<td style="text-align: rigth;" ><b>NIT/CC:</b><span> '.$resSql[0]['nit'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;"><b>nombre '.$Type.':</b> <span>'.$resSql[0]['cliente'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;"><b>ciudad '.$Type.':</b> <span>'.$resSql[0]['ciudad'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;"><b>Municipio '.$Type.':</b> <span>'.$resSql[0]['estado'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;"><b>Telefono del  '.$Type.':</b> <span>'.$resSql[0]['telefono'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;"><b>Correo del '.$Type.':</b> <span>'.$resSql[0]['email'].'</span></p></td>
							</tr>
						</table>
					</td>
					<!-- Columna dERECHA DATOS DEL CONTACTO-->
					<td style="width: 40%; vertical-align: top;">
						<table>
							<tr>
								<td style="text-align: rigth;"><b>DATOS DE PERSONA DE CONTACTO</b><th>
							</tr>
							<tr>
								<td style="text-align: rigth;" ><b>Id Contacto:</b><span> '.$resSql[0]['idcontact'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;" ><b>nombre contacto:</b><span> '.$resSql[0]['nombre_contacto_p'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;" ><b>telefono contacto:</b><span> '.$resSql[0]['telefono_contacto_p'].'</span></p></td>
							</tr>
							<tr>
								<td style="text-align: rigth;" ><b>correo contacto:</b><span> '.$resSql[0]['correo_contacto_p'].'</span></p></td>
							</tr>
						</table>
					</td>
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
				<tr >
					<th style="padding-top: 10px;"><b>ITEM</b></th>
					<th style="padding-top: 10px;"><b>CODIGO</b></th>
					<th style="padding-top: 10px;"><b>TERCERO</b></th>
					<th style="padding-top: 10px;"><b>CONCEPTO</b></th>
					<th style="padding-top: 10px;"><b>FECHA DOC.</b></th>
					<th style="padding-top: 10px;"><b>CUENTA</b></th>
					<th style="padding-top: 10px;"><b>GASTO</b></th>
					<th style="padding-top: 10px;"><b>IMPUESTO</b></th>
					<th style="padding-top: 10px;"><b>TOTAL</b></th>
				</tr>
				'.$totaldetalle.'
			</table>
        	<br>
			<table width="100%" style="vertical-align: bottom; font-family: serif;font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
				<tr>
					<th class="fondo">
						<p></p>
					</th>
				</tr>
        	</table>
        	<br>
			<div class="right-table" style="float: right;width: 40%; ">
				<table width="100%" >					
				<tr>
					<td style="text-align: right; width:70%;"><b>Base Documento:</b></td>
					<td style="text-align: left; width:30%;">  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['base'], $DECI_MALES, ',', '.').'</span></p></td>
				</tr>
				<tr>
					<td style="text-align: right;"><b>Impuestos:</b></td>
					<td style="text-align: left;">  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['iva'], $DECI_MALES, ',', '.').'</span></p></td>
				</tr>
				<tr>
					<td style="text-align: right;"><b>Total:</b></td>
					<td style="text-align: left;">  <span>'.$resSql[0]['monedadocumento']." ".number_format($resSql[0]['totaldoc'], $DECI_MALES, ',', '.').'</span></p></td>
				</tr>
			</table>
			</div>
			<div style="margin-bottom: 50px;"></div>
       		
			<table  width="100%">
				<tr>
            		<td style="text-align: left;"><b>comentarios:</b><br><p>'.$resSql[0]['comentarios'].'</p></td>
        		</tr>
        	</table>
			<br>
			<br>
        	<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            	<tr>
                	<th style="text-align: left;" >
						<p><b>VALOR EN LETRAS:</b></p><br>
                    	<p>'.$formatter->toWords($resSql[0]['totaldoc'],$DECI_MALES)." ".$resSql[0]['nombremoneda'].'</p>
                	</th>
            	</tr>
        	</table>
			</body>
		</html>';
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