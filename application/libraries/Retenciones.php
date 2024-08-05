<?php
defined('BASEPATH') OR exit('No direct script access allowed');



class Retenciones {

    private $ci;
    private $pdo;

	public function __construct() {

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->pdo = $this->ci->load->database('pdo', true)->conn_id;
        $this->ci->load->library('pedeo', [$this->pdo]);

	}

    // PROCESO PARA CORREGIR LA RETENCION APLIDACA A UNA FACTURA 
    // DE VENTA O COMPRA SIEMPRE Y CUANDO EL DOCUMENTO ESTE ABIERTO 
    // Y TENGA SALDO SUFICIENTE EN LA CUENTA DEL TERCERO
    public function UpdateRetencion( $Data, $ContenidoDetalle, $prf_c, $prf_d, $MONEDALOCAL, $TasaDocLoc, $TasaLocSys, $MONEDASYS, $DECI_MALES, $periodo ) {

        // $Data = Contiene toda la informacion del documento que se esta tratando de editar
        // $ContenidoDetalle =  Contiene el detalle del documento que se esta editando

        // PROCESO PARA INSERTAR RETENCIONES
        $DetalleRetencion = new stdClass();
        $DetalleConsolidadoRetencion = [];
        $inArrayRetencion = array();
        $llaveRetencion = "";

        $TotalAcuRentencion = 0;
        	
		$sqlDetalleAsiento = "INSERT INTO mac1(ac1_trans_id, ac1_account, ac1_debit, ac1_credit, ac1_debit_sys, ac1_credit_sys, ac1_currex, ac1_doc_date, ac1_doc_duedate,
        ac1_debit_import, ac1_credit_import, ac1_debit_importsys, ac1_credit_importsys, ac1_font_key, ac1_font_line, ac1_font_type, ac1_accountvs, ac1_doctype,
        ac1_ref1, ac1_ref2, ac1_ref3, ac1_prc_code, ac1_uncode, ac1_prj_code, ac1_rescon_date, ac1_recon_total, ac1_made_user, ac1_accperiod, ac1_close, ac1_cord,
        ac1_ven_debit,ac1_ven_credit, ac1_fiscal_acct, ac1_taxid, ac1_isrti, ac1_basert, ac1_mmcode, ac1_legal_num, ac1_codref, ac1_line, ac1_base_tax, business, branch,ac1_codret)
        VALUES (:ac1_trans_id, :ac1_account, :ac1_debit, :ac1_credit, :ac1_debit_sys, :ac1_credit_sys, :ac1_currex, :ac1_doc_date, :ac1_doc_duedate, :ac1_debit_import, :ac1_credit_import,
        :ac1_debit_importsys, :ac1_credit_importsys, :ac1_font_key, :ac1_font_line, :ac1_font_type, :ac1_accountvs, :ac1_doctype, :ac1_ref1, :ac1_ref2, :ac1_ref3, :ac1_prc_code, :ac1_uncode,
        :ac1_prj_code, :ac1_rescon_date, :ac1_recon_total, :ac1_made_user, :ac1_accperiod, :ac1_close, :ac1_cord, :ac1_ven_debit, :ac1_ven_credit, :ac1_fiscal_acct,
        :ac1_taxid, :ac1_isrti, :ac1_basert, :ac1_mmcode, :ac1_legal_num, :ac1_codref,:ac1_line, :ac1_base_tax, :business, :branch, :ac1_codret)";



        // SE VALIDA QUE EL DOCUMENTO NO TENGA RETENCIONES APLICADAS
        $sqlFcrt = "SELECT * FROM fcrt WHERE crt_baseentry = :crt_baseentry AND crt_basetype = :crt_basetype";
        $resFcrt = $this->ci->pedeo->queryTable($sqlFcrt, array(
            ':crt_baseentry' => $Data[ $prf_c.'_docentry' ],
            ':crt_basetype'  => $Data[ $prf_c.'_doctype' ],
        ));

        if (isset($resFcrt[0])) {

            $respuesta = array(
                'error'   => true,
                'data'    => $resFcrt,
                'mensaje' => 'Ya se han aplicado retenciones a este documento'
            );

            return $respuesta;
        }
        //
        // SE APLICAN LA RETENCIONES
        foreach ($ContenidoDetalle as $key => $detail) {

            if (isset($detail['detail'])) {

                $ContenidoRentencion = $detail['detail'];
    
                if (is_array($ContenidoRentencion)) {
                    if (intval(count($ContenidoRentencion)) > 0) {
    
                        foreach ($ContenidoRentencion as $key => $value) {
    
                            $DetalleRetencion = new stdClass();
    
                            $sqlInsertRetenciones = "INSERT INTO fcrt(crt_baseentry, crt_basetype, crt_typert, crt_basert, crt_profitrt, crt_totalrt, crt_base, crt_type, crt_linenum, crt_codret)
                                                    VALUES (:crt_baseentry, :crt_basetype, :crt_typert, :crt_basert, :crt_profitrt, :crt_totalrt, :crt_base, :crt_type, :crt_linenum, :crt_codret)";
    
                            $resInsertRetenciones = $this->pedeo->insertRow($sqlInsertRetenciones, array(
    
                                ':crt_baseentry' => $resInsert,
                                ':crt_basetype'  => $Data[ $prf_c.'_doctype' ],
                                ':crt_typert'    => $value['crt_typert'],
                                ':crt_basert'    => $value['crt_basert'],
                                ':crt_profitrt'  => $value['crt_profitrt'],
                                ':crt_totalrt'   => $value['crt_totalrt'],
                                ':crt_base'		 => $value['crt_base'],
                                ':crt_type'		 => $value['crt_type'],
                                ':crt_linenum'   => $detail[ $prf_d.'_linenum' ],
                                ':crt_codret'	 => $value['crt_codret']
                            ));
    
    
                            if (is_numeric($resInsertRetenciones) && $resInsertRetenciones > 0) {
    
                                $TotalAcuRentencion = $TotalAcuRentencion + $value['crt_totalrt'];
    
                                $DetalleRetencion->crt_typert   = $value['crt_typert'];
                                $DetalleRetencion->crt_basert   = $value['crt_totalrt'];
                                $DetalleRetencion->crt_profitrt = $value['crt_profitrt'];
                                $DetalleRetencion->crt_totalrt  = $value['crt_totalrt'];
                                $DetalleRetencion->crt_codret   = $value['crt_typert'];
                                $DetalleRetencion->crt_baseln 	= $value['crt_basert'];
    
    
                                
                                $DetalleRetencion->ac1_prc_code = $detail[ $prf_d.'_costcode' ];
                                $DetalleRetencion->ac1_uncode   = $detail[ $prf_d.'_ubusiness' ];
                                $DetalleRetencion->ac1_prj_code = $detail[ $prf_d.'_project' ];
    
    
                                $llaveRetencion = $DetalleRetencion->crt_typert . $DetalleRetencion->crt_profitrt;
    
                                if (in_array($llaveRetencion, $inArrayRetencion)) {
    
                                    $posicionRetencion = $this->buscarPosicion($llaveRetencion, $inArrayRetencion);
                                } else {
    
                                    array_push($inArrayRetencion, $llaveRetencion);
                                    $posicionRetencion = $this->buscarPosicion($llaveRetencion, $inArrayRetencion);
                                }
    
                                if (isset($DetalleConsolidadoRetencion[$posicionRetencion])) {
    
                                    if (!is_array($DetalleConsolidadoRetencion[$posicionRetencion])) {
                                        $DetalleConsolidadoRetencion[$posicionRetencion] = array();
                                    }

                                } else {
                                    $DetalleConsolidadoRetencion[$posicionRetencion] = array();
                                }
    
                                array_push($DetalleConsolidadoRetencion[$posicionRetencion], $DetalleRetencion);

                            } else {
                               
                                $respuesta = array(
                                    'error'   => true,
                                    'data'    => $resInsertRetenciones,
                                    'mensaje' => 'No se pudieron actualizar las retenciones del documento'
                                );
                             
                                return $respuesta;
                            }
                        }
                    }
                }
            }
        }
        // FIN DE APLICACION DE RENTENCIONES

        // SE BUSCA EL ASIENTO ANTERIOR DEL DOCUMENTO 
        $sqlAsiento = "SELECT * 
                    FROM mac1 
                    INNER JOIN dacc on mac1.ac1_account = dacc.acc_code and dacc.acc_businessp = :acc_businessp
                    WHERE ac1_font_key = :ac1_font_key  and ac1_font_type = :ac1_font_type";

        $resAsiento = $this->ci->pedeo->queryTable( $sqlAsiento, array() );

        if ( isset($resAsiento[0]) && count($resAsiento) == 1 ) {
        } else {

            $respuesta = array(
                'error'   => true,
                'data'    => $resInsertRetenciones,
                'mensaje' => 'Existe mas de una cuenta asociada en el asiento anterior'
            );
            return $respuesta;
        }
        //

        // PROCEDIMIENTO PARA LLENAR LINEAS DEL ASIENTO

        foreach ($DetalleConsolidadoRetencion as $key => $posicion) {

            $totalRetencion = 0;
            $BaseLineaRet = 0;
            $totalRetencionOriginal = 0;
            $dbito = 0;
            $cdito = 0;
            $MontoSysDB = 0;
            $MontoSysCR = 0;
            $cuenta = '';
            $Basert = 0;
            $Profitrt = 0;
            $CodRet = "";

            $prc_code = '';
            $uncode   = '';
            $prj_code = '';

            foreach ($posicion as $key => $value) {

                $sqlcuentaretencion = "SELECT mrt_acctcode, mrt_code FROM dmrt WHERE mrt_id = :mrt_id";

                $rescuentaretencion = $this->ci->pedeo->queryTable($sqlcuentaretencion, array(
                    'mrt_id' => $value->crt_typert
                ));

                if (isset($rescuentaretencion[0])) {

                    $cuenta = $rescuentaretencion[0]['mrt_acctcode'];
                    $totalRetencion = $totalRetencion + $value->crt_basert;
                    $Profitrt =  $value->crt_profitrt;
                    $CodRet = $rescuentaretencion[0]['mrt_code'];
                    $BaseLineaRet = $BaseLineaRet + $value->crt_baseln;

                    $prc_code = $value->ac1_prc_code;
                    $uncode   = $value->ac1_uncode;
                    $prj_code = $value->ac1_prj_code;

                } else {

                    $respuesta = array (
                        'error'   => true,
                        'data'	  => $rescuentaretencion,
                        'mensaje'	=> 'No se pudo ingregar el asiento de la rentención'
                    );

                    return $respuesta;
                }
            }

            $Basert = $BaseLineaRet;
            $totalRetencionOriginal = $totalRetencion;

            if (trim($Data[$prf_c.'_currency']) != $MONEDALOCAL) {
                $totalRetencion = ($totalRetencion * $TasaDocLoc);
                $BaseLineaRet = ($BaseLineaRet * $TasaDocLoc);
                $Basert = $BaseLineaRet;
            }


            if (trim($Data[$prf_c.'_currency']) != $MONEDASYS) {
                $MontoSysCR = ($totalRetencion / $TasaLocSys);
            } else {
                $MontoSysCR = 	$totalRetencionOriginal;
            }


          
            $resDetalleAsiento = $this->ci->pedeo->insertRow($sqlDetalleAsiento, array(

                ':ac1_trans_id' => $resAsiento[0]['ac1_trans_id'],
                ':ac1_account' => $cuenta,
                ':ac1_debit' => 0,
                ':ac1_credit' => round($totalRetencion, $DECI_MALES),
                ':ac1_debit_sys' => 0,
                ':ac1_credit_sys' => round($MontoSysCR, $DECI_MALES),
                ':ac1_currex' => 0,
                ':ac1_doc_date' => $this->validateDate($Data[$prf_c.'_docdate']) ? $Data[$prf_c.'_docdate'] : NULL,
                ':ac1_doc_duedate' => $this->validateDate($Data[$prf_c.'_duedate']) ? $Data[$prf_c.'_duedate'] : NULL,
                ':ac1_debit_import' => 0,
                ':ac1_credit_import' => 0,
                ':ac1_debit_importsys' => 0,
                ':ac1_credit_importsys' => 0,
                ':ac1_font_key' => $Data[$prf_c.'_docentry'],
                ':ac1_font_line' => 1,
                ':ac1_font_type' => $Data[$prf_d.'_doctype'],
                ':ac1_accountvs' => 1,
                ':ac1_doctype' => 18,
                ':ac1_ref1' => "",
                ':ac1_ref2' => "",
                ':ac1_ref3' => "",
                ':ac1_prc_code' =>$prc_code,
                ':ac1_uncode' => $uncode,
                ':ac1_prj_code' => $prj_code,
                ':ac1_rescon_date' => NULL,
                ':ac1_recon_total' => 0,
                ':ac1_made_user' => isset($Data[$prf_c.'_createby']) ? $Data[$prf_c.'_createby'] : NULL,
                ':ac1_accperiod' => $periodo['data'],
                ':ac1_close' => 0,
                ':ac1_cord' => 0,
                ':ac1_ven_debit' => 0,
                ':ac1_ven_credit' => round($totalRetencion, $DECI_MALES),
                ':ac1_fiscal_acct' => 0,
                ':ac1_taxid' => 0,
                ':ac1_isrti' => $Profitrt,
                ':ac1_basert' => round($Basert, $DECI_MALES),
                ':ac1_mmcode' => 0,
                ':ac1_legal_num' => isset($Data[$prf_c.'_cardcode']) ? $Data[$prf_c.'_cardcode'] : NULL,
                ':ac1_codref' => 1,
                ":ac1_line" => NULL,
                ':ac1_base_tax' => 0,
                ':business' => $Data['business'],
                ':branch' 	=> $Data['branch'],
                ':ac1_codret' => $CodRet
            ));



            if (is_numeric($resDetalleAsiento) && $resDetalleAsiento > 0) {
              
            } else {

                $respuesta = array(
                    'error'   => true,
                    'data'	  => $resDetalleAsiento,
                    'mensaje' => 'No se pudo registrar el asiento de retención'
                );

                return $respuesta;
            }
        }

		// FIN PROCEDIMIENTO PARA LLENAR LINEAS DEL ASIENTO



       

    }


    private function buscarPosicion($llave, $inArray)
	{
		$res = 0;
		for ($i = 0; $i < count($inArray); $i++) {
			if ($inArray[$i] == "$llave") {
				$res =  $i;
				break;
			}
		}

		return $res;
	}

	private function validateDate($fecha)
	{
		if (strlen($fecha) == 10 or strlen($fecha) > 10) {
			return true;
		} else {
			return false;
		}
	}
}