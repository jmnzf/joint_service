<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class DocumentCopy {

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

	//CABECERA DEL DOCUMENTO AL COPIAR
	public function CopyData($table_enc,$prefijo_enc,$cardcode,$business,$branch,$doctype = 0,$decimals = 2,$campos = "",$where = ""){
		//OPERACION DECIMALES
		$decimals = str_pad(0, $decimals, "0", STR_PAD_LEFT);
		$row = "";
		$addRows = "";
		if(isset($campos) && !empty($campos)){
			$row = explode(",",$campos);
			foreach ($row as $key => $r) {
				$addRows .= ",t0.{prefijo}_".$r;
			}

			$addRows = str_replace("{prefijo}",$prefijo_enc,$addRows);
		}
		//VALIDAR WHERE
		$where_sql = $where;
		if(isset($where) && !empty($where)){
			$where_sql = $where_sql;
		}
		
		if(isset($doctype) && $doctype == 36){
			$sql = "SELECT
					t0.*,
					concat(trim(t0.{prefijo}_currency),' ',trim(to_char(t0.{prefijo}_doctotal,'{format}'))) as {prefijo}_doctotal_new, 
					t3.mev_names
					$addRows
				FROM {table_enc} t0
				left join estado_doc t1 on t0.{prefijo}_docentry = t1.entry and t0.{prefijo}_doctype = t1.tipo
				left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
				inner join dmev t3 on t0.{prefijo}_slpcode = t3.mev_id
				left join dcsa t4 on t0.{prefijo}_docentry = t4.csa_baseentry and t0.{prefijo}_doctype = t4.csa_basetype
				where t2.estado = 'Abierto' and t0.{prefijo}_cardcode =:{prefijo}_cardcode
				AND t0.business = :business AND t0.branch = :branch
				group by t0.{prefijo}_docnum, t0.{prefijo}_docdate, t0.{prefijo}_duedate, t0.{prefijo}_duedev, t0.{prefijo}_pricelist, t0.{prefijo}_cardcode, t0.{prefijo}_cardname, t0.{prefijo}_contacid, t0.{prefijo}_slpcode, t0.{prefijo}_empid, t0.{prefijo}_comment, t0.{prefijo}_doctotal, t0.{prefijo}_baseamnt, t0.{prefijo}_taxtotal, t0.{prefijo}_discprofit, t0.{prefijo}_discount, t0.{prefijo}_createat, t0.{prefijo}_baseentry, t0.{prefijo}_basetype, t0.{prefijo}_doctype, t0.{prefijo}_idadd, t0.{prefijo}_adress, t0.{prefijo}_paytype, t0.{prefijo}_attch, t0.{prefijo}_docentry, t0.{prefijo}_series, t0.{prefijo}_createby, t0.{prefijo}_currency, t0.{prefijo}_origen, t0.{prefijo}_correl, t0.{prefijo}_date_inv, t0.{prefijo}_date_del, t0.{prefijo}_place_del, t0.{prefijo}_canceled, t0.business, t0.branch,t3.mev_names $addRows
				having abs(t0.{prefijo}_doctotal - coalesce(sum(t4.csa_anticipate_total),0)) > 0";
		}else if (isset($doctype) && $doctype == 32) {
			$sql = "SELECT
					t0.*,
					concat(trim(t0.{prefijo}_currency),' ',trim(to_char(t0.{prefijo}_doctotal,'{format}'))) as {prefijo}_doctotal_new, 
					t3.mev_names,
					dcsn.csn_typeagreement
					$addRows
				FROM {table_enc} t0
				left join estado_doc t1 on t0.{prefijo}_docentry = t1.entry and t0.{prefijo}_doctype = t1.tipo
				left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
				inner join dmev t3 on t0.{prefijo}_slpcode = t3.mev_id
				inner join dcsn on t0.{prefijo}_docentry = dcsn.csn_docentry
				where t2.estado = 'Abierto' and trim(t0.{prefijo}_cardcode) =:{prefijo}_cardcode
				AND t0.business = :business AND t0.branch = :branch";
		}else if(isset($doctype) && $doctype == 34){
			$sql = "SELECT
					t0.*,
					concat(trim(t0.{prefijo}_currency),' ',trim(to_char(t0.{prefijo}_doctotal,'{format}'))) as {prefijo}_doctotal_new, 
					t3.mev_names,
					dcsn.csn_typeagreement
					$addRows
				FROM {table_enc} t0
				left join estado_doc t1 on t0.{prefijo}_docentry = t1.entry and t0.{prefijo}_doctype = t1.tipo
				left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
				inner join dmev t3 on t0.{prefijo}_slpcode = t3.mev_id
				inner join dcsn on t0.{prefijo}_docentry = dcsn.csn_docentry
				where t2.estado = 'Abierto' and t0.{prefijo}_cardcode =:{prefijo}_cardcode
				AND t0.business = :business AND t0.branch = :branch and t0.{prefijo}_doctype = {$doctype}";
		}else if(isset($doctype) && $doctype == 46){
			$sql = "SELECT
					t0.*,
					concat(trim(t0.{prefijo}_currency),' ',trim(to_char(t0.{prefijo}_doctotal,'{format}'))) as {prefijo}_doctotal_new, 
					t3.mev_names
					$addRows
				FROM {table_enc} t0
				left join estado_doc t1 on t0.{prefijo}_docentry = t1.entry and t0.{prefijo}_doctype = t1.tipo
				left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
				inner join dmev t3 on t0.{prefijo}_slpcode = t3.mev_id
				where t2.estado = 'Abierto' and t0.{prefijo}_cardcode =:{prefijo}_cardcode
				AND t0.business = :business AND t0.branch = :branch and t0.{prefijo}_doctype = {$doctype}";
		}else{
			$sql = "SELECT
					t0.*,
					concat(trim(t0.{prefijo}_currency),' ',trim(to_char(t0.{prefijo}_doctotal,'{format}'))) as {prefijo}_doctotal_new, 
					t3.mev_names
					$addRows
				FROM {table_enc} t0
				left join estado_doc t1 on t0.{prefijo}_docentry = t1.entry and t0.{prefijo}_doctype = t1.tipo
				left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
				inner join dmev t3 on t0.{prefijo}_slpcode = t3.mev_id
				where t2.estado = 'Abierto' and t0.{prefijo}_cardcode =:{prefijo}_cardcode
				AND t0.business = :business AND t0.branch = :branch {where}";
		}

		$sql = str_replace("{table_enc}",$table_enc,$sql);//ASIGNAR TABLA CABECERA	
		$sql = str_replace("{prefijo}",$prefijo_enc,$sql);//ASIGNAR PREFIJO CABECERA
		$sql = str_replace("{format}","999,999,999,999.".$decimals,$sql);//ASIGNAR FORMATO CAMPO NUMERICO
		$sql = str_replace("{where}",$where,$sql);//ADICIONAR WHERE
		// print_r($sql);exit;
		return $this->ci->pedeo->queryTable($sql,array(":".$prefijo_enc."_cardcode" => $cardcode,":business" => $business,":branch" => $branch));
	}
	//DETALLE DEL DOCUMENTO AL COPIAR
    public function Copy($docentry,$table_enc,$table_det,$prefijo_enc,$prefijo_det,$campos = "",$doctype = 0){

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

			$having = "";
			if($doctype == 10){
				$having = $having;
			}else {
				$having = "HAVING abs((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum)))) > 0";
			}
			//CONSULTAR DATO EN PARAMS
			$params = "SELECT * FROM params WHERE use_tax_art = 1";
			$resParams = $this->ci->pedeo->queryTable($params,array());
			//
			if(isset($resParams[0])){
				$sql = "SELECT
							t1.{prefijo_det}_linenum,
							t1.{prefijo_det}_acciva,
							t1.{prefijo_det}_acctcode,
							t1.{prefijo_det}_avprice,
							t1.{prefijo_det}_basetype,
							t1.{prefijo_det}_costcode,
							round((t1.{prefijo_det}_discount / t1.{prefijo_det}_quantity) * ABS(t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum))), get_decimals()) as {prefijo_det}_discount,
							t1.{prefijo_det}_docentry,
							t1.{prefijo_det}_doctype,
							t1.{prefijo_det}_id,
							t1.{prefijo_det}_inventory,
							t1.{prefijo_det}_itemcode,
							t1.{prefijo_det}_itemname,
							ABS((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum)))) *
							t1.{prefijo_det}_price::decimal(15,2) AS {prefijo_det}_linetotal_1,
							(abs((((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum)))* t1.{prefijo_det}_price)
							- t1.{prefijo_det}_discount)))
							as {prefijo_det}_linetotal,
							t1.{prefijo_det}_price,
							t1.{prefijo_det}_project,
							ABS(t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum))) AS {prefijo_det}_quantity,
							t1.{prefijo_det}_ubusiness,
							t1.{prefijo_det}_uom,
							t1.{prefijo_det}_vat,
							t1.{prefijo_det}_vatsum vatsum_real,
							ABS(((((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum)))) *
							(t1.{prefijo_det}_price - ((t1.{prefijo_det}_price * t1.{prefijo_det}_vat) / 100))) * t1.{prefijo_det}_vat) / 100)::decimal(15,2) AS {prefijo_det}_vatsum_1,
							abs(((((abs((((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum)))*
							t1.{prefijo_det}_price) - (round((t1.{prefijo_det}_discount / t1.{prefijo_det}_quantity) * ABS(t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum))), get_decimals())))) *
							(case when coalesce(dmar.dma_use_tbase,0) = 1 then dmar.dma_tasa_base else 100 end)) / 100) * t1.{prefijo_det}_vat) / 100)) as
							{prefijo_det}_vatsum,
							t1.{prefijo_det}_whscode,
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
							dmar.dma_tasa_base,
							dmar.dma_lotes_code
							,t1.{prefijo_det}_tax_base as {prefijo_det}_tax_base1,
							(abs((((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum)))* t1.{prefijo_det}_price)
							- (round((t1.{prefijo_det}_discount / t1.{prefijo_det}_quantity) * ABS(t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum))), get_decimals())))) *
							(case when coalesce(dmar.dma_use_tbase,0) = 1 then dmar.dma_tasa_base else 100 end) / 100) as {prefijo_det}_tax_base
							{addFields}
							FROM {table_enc} t0
							INNER JOIN {table_det} t1 ON t0.{prefijo_enc}_docentry = t1.{prefijo_det}_docentry
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
							dmar.dma_series_code,
							t1.{prefijo_det}_ubication,
							t1.{prefijo_det}_codimp,
							t0.business,
							t0.{prefijo_enc}_doctype,
							t0.{prefijo_enc}_docentry,
							dmar.dma_advertisement,
							dmar.dma_modular,
							dmtx.dmi_use_fc,
							dmtx.dmi_rate_fc,
							t1.ote_code,
							lote.ote_date,
							lote.ote_duedate,
							t1.{prefijo_det}_whscode_dest,
							t1.{prefijo_det}_ubication2,
							dmar.dma_use_tbase,
							dmar.dma_tasa_base,
							dmar.dma_lotes_code,
							t1.{prefijo_det}_tax_base
							{addFields}
							{having}";
			}else{
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
						--ABS((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum)))) * t1.{prefijo_det}_price::decimal(15,2) AS {prefijo_det}_linetotal,
						ABS((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum)))) * round((t1.{prefijo_det}_price::decimal(15,2) - (t1.{prefijo_det}_price::decimal(15,2) * t1.{prefijo_det}_discount / 100)), get_decimals()) AS {prefijo_det}_linetotal,
						t1.{prefijo_det}_price,
						t1.{prefijo_det}_project,
						ABS(t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum))) AS {prefijo_det}_quantity,
						t1.{prefijo_det}_ubusiness,
						t1.{prefijo_det}_uom,
						t1.{prefijo_det}_vat,
						t1.{prefijo_det}_vatsum vatsum_real,
						t1.{prefijo_det}_accimp_ad,
						t1.{prefijo_det}_codimp_ad,
						t1.{prefijo_det}_vat_ad,
						t1.{prefijo_det}_tax_base as {prefijo_det}_tax_base_org,
						ABS(((((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum))) * t1.{prefijo_det}_price::decimal(15,2) - (t1.{prefijo_det}_price::decimal(15,2) * t1.{prefijo_det}_discount / 100) )))) as {prefijo_det}_tax_base,
						--ABS(((((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum)))) * t1.{prefijo_det}_price) * t1.{prefijo_det}_vat) / 100)::decimal(15,2) AS {prefijo_det}_vatsum,
						ABS(((((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum)))) * round((t1.{prefijo_det}_price::decimal(15,2) - (t1.{prefijo_det}_price::decimal(15,2) * t1.{prefijo_det}_discount / 100)), get_decimals()) * t1.{prefijo_det}_vat) / 100)::decimal(15,2)) AS {prefijo_det}_vatsum,
						ABS(((((t1.{prefijo_det}_quantity - (get_quantity(t0.{prefijo_enc}_doctype,t0.{prefijo_enc}_docentry,t1.{prefijo_det}_itemcode,t1.{prefijo_det}_linenum)))) * round((t1.{prefijo_det}_price::decimal(15,2) - (t1.{prefijo_det}_price::decimal(15,2) * t1.{prefijo_det}_discount / 100)), get_decimals()) * t1.{prefijo_det}_vat_ad) / 100)::decimal(15,2)) AS {prefijo_det}_vatsum_ad,
						t1.{prefijo_det}_whscode,
						dmar.dma_series_code,
						t1.{prefijo_det}_ubication,
						t1.{prefijo_det}_codimp,
						get_ubication(t1.{prefijo_det}_whscode, t0.business) as fun_ubication,
						get_lote(t1.{prefijo_det}_itemcode) as fun_lote,
						CASE WHEN coalesce(dmar.dma_advertisement,0) = 0 THEN 0 ELSE 1 END AS dma_advertisement,
						CASE WHEN coalesce(dmar.dma_modular,0) = 0 THEN 0 ELSE 1 END AS dma_modular,
						dma_item_mat,
						dma_sup_set,
						dmtx.dmi_use_fc,
						dmtx.dmi_rate_fc,
						t1.ote_code,
						lote.ote_date,
						lote.ote_duedate,
						t1.{prefijo_det}_whscode_dest,
						t1.{prefijo_det}_ubication2,
						dmar.dma_use_tbase,
						dmar.dma_tasa_base,
						dmar.dma_clean,
						dma_asset
						{addFields}
						FROM {table_enc} t0
						INNER JOIN {table_det} t1 ON t0.{prefijo_enc}_docentry = t1.{prefijo_det}_docentry
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
						t1.{prefijo_det}_accimp_ad,
						t1.{prefijo_det}_codimp_ad,
						t1.{prefijo_det}_vat_ad,
						t1.{prefijo_det}_whscode,
						t1.{prefijo_det}_quantity,
						dmar.dma_series_code,
						t1.{prefijo_det}_ubication,
						t1.{prefijo_det}_codimp,
						t1.{prefijo_det}_tax_base,
						t0.business,
						t0.{prefijo_enc}_doctype,
						t0.{prefijo_enc}_docentry,
						dmar.dma_advertisement,
						dmar.dma_modular,
						dma_item_mat,
						dma_asset,
						dma_sup_set,
						dmtx.dmi_use_fc,
						dmtx.dmi_rate_fc,
						t1.ote_code,
						lote.ote_date,
						lote.ote_duedate,
						t1.{prefijo_det}_whscode_dest,
						t1.{prefijo_det}_ubication2,
						dmar.dma_use_tbase,
						dmar.dma_tasa_base,
						dmar.dma_clean
						{addFields}
						{having}";
			}
			//
			//REEMPLAZAR DATOS DE QUERY
			$sql = str_replace("{having}",$having,$sql);//AGREGAR EL HAVING 
			$sql = str_replace("{table_enc}",$table_enc,$sql);//ASIGNAR TABLA CABECERA	
			$sql = str_replace("{prefijo_enc}",$prefijo_enc,$sql);//ASIGNAR PREFIJO CABECERA
			$sql = str_replace("{table_det}",$table_det,$sql);//ASIGNAR TABLA DETALLE
			$sql = str_replace("{prefijo_det}",$prefijo_det,$sql);//ASIGNAR PREFIJO DETALLE
			$sql = str_replace("{addFields}",$addFields,$sql);//ASIGNAR CAMPOS FIELDS
			// print_r($sql);exit;
			//
			return $this->ci->pedeo->queryTable($sql, array(':'.$prefijo_det.'_docentry' => $docentry));

    }

	private function getEntry($cardcode){

		$sql = "SELECT dcpo.cpo_docentry,dcpo.cpo_doctype,dcpo.cpo_cardcode FROM dcpo WHERE dcpo.cpo_cardcode = :cpo_cardcode";

		return $this->ci->pedeo->queryTable($sql,array(':cpo_cardcode' => $cardcode));
	}
}