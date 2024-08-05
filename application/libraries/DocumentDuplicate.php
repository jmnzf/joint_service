<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class DocumentDuplicate {

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

	}

    // CARGA LA CABECERA DEL DOCUMENTO
    public function getDuplicate( $table_enc, $prefijo_enc, $cardcode, $business, $doctype = 0 ) {
        $detalle = "";
        if ($doctype == 34){
            $detalle = " AND t0.dvf_doctype = 34";
        }

        $DECI_MALES =  $this->getDecimals();

        $sql = "SELECT
					t0.*,
					concat(trim(t0.{prefijo}_currency),' ',trim(to_char(t0.{prefijo}_doctotal,'{format}'))) as {prefijo}_doctotal_new, 
					t3.mev_names
				FROM {table_enc} t0
				INNER JOIN dmev t3 ON t0.{prefijo}_slpcode = t3.mev_id
				WHERE t0.business = :business
                ".$detalle."
                AND t0.{prefijo}_cardcode =:{prefijo}_cardcode";

        $sql = str_replace("{table_enc}",$table_enc,$sql);//ASIGNAR TABLA CABECERA	
        $sql = str_replace("{prefijo}",$prefijo_enc,$sql);//ASIGNAR PREFIJO CABECERA
        $sql = str_replace("{format}","999,999,999,999.".$DECI_MALES,$sql);//ASIGNAR FORMATO CAMPO NUMERICO

        return $this->ci->pedeo->queryTable($sql,array(":".$prefijo_enc."_cardcode" => $cardcode,":business" => $business));
	}

    //DETALLE DEL DOCUMENTO AL DUPLICAR
    public function getDuplicateDt($docentry,$table_enc,$table_det,$prefijo_enc,$prefijo_det,$campos = ""){

			$rows = "";
			$addFields = "";
			if(isset($campos) && !empty($campos)) {
				
				$row = explode(",",$campos);
				foreach ($row as $key => $r) {
					if($r == "detalle_modular::jsonb" OR $r == "detalle_anuncio::jsonb"){
						$addFields .= ",".$r; 
					}else if ($r == "imponible" OR $r == "deducible"){
						$addFields .= ",".$r; 
					}else{
						$addFields .= ",".$prefijo_det."_".$r; 
					}
					
				}
				
			}

			$sql = "SELECT
			t1.{prefijo_det}_linenum,
			t1.{prefijo_det}_acciva,
			t1.{prefijo_det}_acctcode,
			t1.{prefijo_det}_avprice,
			t1.{prefijo_det}_basetype,
			t1.{prefijo_det}_costcode,
			t1.{prefijo_det}_discount,
			t1.{prefijo_det}_docentry,
			t1.{prefijo_det}_doctype,
			t1.{prefijo_det}_id,
			t1.{prefijo_det}_inventory,
			t1.{prefijo_det}_itemcode,
			t1.{prefijo_det}_itemname,
			t1.{prefijo_det}_linetotal,
			t1.{prefijo_det}_price,
			t1.{prefijo_det}_project,
			t1.{prefijo_det}_quantity,
			t1.{prefijo_det}_ubusiness,
			t1.{prefijo_det}_uom,
			t1.{prefijo_det}_vat,
			t1.{prefijo_det}_vatsum vatsum_real,
			t1.{prefijo_det}_vatsum,
			t1.{prefijo_det}_accimp_ad,
			t1.{prefijo_det}_codimp_ad,
			t1.{prefijo_det}_vat_ad,
			t1.{prefijo_det}_vatsum_ad,
			t1.{prefijo_det}_whscode,
			t1.{prefijo_det}_tax_base,
			dmar.dma_series_code,
			t1.{prefijo_det}_ubication,
			t1.{prefijo_det}_codimp,
			get_ubication(t1.{prefijo_det}_whscode, t0.business) as fun_ubication,
			get_lote(t1.{prefijo_det}_itemcode) as fun_lote,
			CASE WHEN coalesce(dmar.dma_advertisement,0) = 0 THEN 0 ELSE 1 END AS dma_advertisement,
			CASE WHEN coalesce(dmar.dma_modular,0) = 0 THEN 0 ELSE 1 END AS dma_modular,
			dmtx.dmi_use_fc,
			dmtx.dmi_rate_fc,
			t1.ote_code,
			lote.ote_date,
			lote.ote_duedate,
			t1.{prefijo_det}_whscode_dest,
			t1.{prefijo_det}_ubication2,
			dmar.dma_use_tbase,
			dmar.dma_tasa_base
			{addFields}
			FROM {table_enc} t0
			INNER JOIN {table_det} t1 ON t0.{prejijo_enc}_docentry = t1.{prefijo_det}_docentry
			LEFT JOIN dmar ON t1.{prefijo_det}_itemcode = dmar.dma_item_code
			LEFT JOIN dmtx ON t1.{prefijo_det}_codimp = dmtx.dmi_code
			LEFT JOIN lote ON t1.ote_code = lote.ote_code
			WHERE t1.{prefijo_det}_docentry = :{prefijo_det}_docentry
			GROUP BY
			t1.{prefijo_det}_linenum,
			t1.{prefijo_det}_acciva,
			t1.{prefijo_det}_acctcode,
			t1.{prefijo_det}_avprice,
			t1.{prefijo_det}_basetype,
			t1.{prefijo_det}_costcode,
			t1.{prefijo_det}_discount,
			t1.{prefijo_det}_docentry,
			t1.{prefijo_det}_doctype,
			t1.{prefijo_det}_id,
			t1.{prefijo_det}_inventory,
			t1.{prefijo_det}_itemcode,
			t1.{prefijo_det}_itemname,
			t1.{prefijo_det}_linetotal,
			t1.{prefijo_det}_price,
			t1.{prefijo_det}_project,
			t1.{prefijo_det}_ubusiness,
			t1.{prefijo_det}_uom,
			t1.{prefijo_det}_vat,
			t1.{prefijo_det}_vatsum,
			t1.{prefijo_det}_whscode,
			t1.{prefijo_det}_quantity,
			t1.{prefijo_det}_tax_base,
			t1.{prefijo_det}_accimp_ad,
			t1.{prefijo_det}_codimp_ad,
			t1.{prefijo_det}_vat_ad,
			t1.{prefijo_det}_vatsum_ad,
			dmar.dma_series_code,
			t1.{prefijo_det}_ubication,
			t1.{prefijo_det}_codimp,
			t0.business,
			t0.{prejijo_enc}_doctype,
			t0.{prejijo_enc}_docentry,
			dmar.dma_advertisement,
			dmar.dma_modular,
			dmtx.dmi_use_fc,
			dmtx.dmi_rate_fc,
			t1.ote_code,
			lote.ote_date,
			lote.ote_duedate,
			dmar.dma_use_tbase,
			dmar.dma_tasa_base,
			t1.{prefijo_det}_whscode_dest,
			t1.{prefijo_det}_ubication2
			{addFields}";
			// 
			//REEMPLAZAR DATOS DE QUERY
			$sql = str_replace("{table_enc}",$table_enc,$sql);//ASIGNAR TABLA CABECERA	
			$sql = str_replace("{prejijo_enc}",$prefijo_enc,$sql);//ASIGNAR PREFIJO CABECERA
			$sql = str_replace("{table_det}",$table_det,$sql);//ASIGNAR TABLA DETALLE
			$sql = str_replace("{prefijo_det}",$prefijo_det,$sql);//ASIGNAR PREFIJO DETALLE
			$sql = str_replace("{addFields}",$addFields,$sql);//ASIGNAR CAMPOS FIELDS
			//
			// print_r($sql);exit;
			return $this->ci->pedeo->queryTable($sql, array(':'.$prefijo_det.'_docentry' => $docentry));

    }



    // SE OBTIENE LA CANTIDAD DE DECIMALES QUE MANEJA EL SISTEMA
    //
    public function getDecimals(){

        $sqlSelect = "SELECT decimals FROM params";

        $resSelect = $this->ci->pedeo->queryTable($sqlSelect, array());

        if ( isset( $resSelect[0] ) ) {

        $resultado = $resSelect[0]['decimals'];

        } else {

        $resultado = 2;

        }

        return $resultado;

    }

}

