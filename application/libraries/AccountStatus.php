<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class AccountStatus {

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


	public function getECC($cliente,$fecha,$currency,$business){
		
        $sql = "SELECT distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num,
        mac1.ac1_legal_num as codigoproveedor,
        mac1.ac1_account as cuenta,
        mac1.ac1_account,
        CURRENT_DATE - dvf_duedate dias,
        CURRENT_DATE - dvf_duedate dias_atrasado,
        dvfv.dvf_comment,
        get_localcur() as currency,
        mac1.ac1_font_key as dvf_docentry,
        mac1.ac1_font_key as docentry,
        dvfv.dvf_docnum,
        dvfv.dvf_docnum as numerodocumento,
        dvfv.dvf_docdate as fecha_doc,
        dvfv.dvf_docdate as fechadocumento,
        dvfv.dvf_duedate as fecha_ven,
        dvfv.dvf_duedate as fechavencimiento,
        dvf_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mac1.ac1_font_type as doctype,
        mdt_docname as tipo,
        '' as refFiscal,
        case
            when mac1.ac1_font_type = 5 OR mac1.ac1_font_type = 34 then  get_dynamic_conversion(:currency, dvf_currency,dvf_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, dvf_currency,dvf_docdate,mac1.ac1_credit ,get_localcur())
        end	 as total_doc,
        case
            when mac1.ac1_font_type = 5 OR mac1.ac1_font_type = 34 then get_dynamic_conversion(:currency, dvf_currency,dvf_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, dvf_currency,dvf_docdate,mac1.ac1_credit ,get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency, dvf_currency,dvf_docdate,(mac1.ac1_debit) - (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(dvfv.dvf_currency, dvfv.dvf_docdate) as tasa_dia,
        dvfv.dvf_cardname as nombreproveedor,
        get_localcur() as monedadocumento,
        :fecha as  fechacorte,
        ac1_line_num,
        ac1_cord,
        1 as cardtype
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dvfv
        on dvfv.dvf_doctype = mac1.ac1_font_type
        and dvfv.dvf_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        and mac1.business = :business
        --ANTICIPO CLIENTE
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num,
        mac1.ac1_legal_num as codigoproveedor,
        mac1.ac1_account as cuenta,
        mac1.ac1_account,
        0 as dias,
        0 as dias_atrasado,
        gbpr.bpr_comments,
        get_localcur() as currency,
        mac1.ac1_font_key as dvf_docentry,
        mac1.ac1_font_key as docentry,
        gbpr.bpr_docnum,
        gbpr.bpr_docnum as numerodocumento,
        gbpr.bpr_docdate as fecha_doc,
        gbpr.bpr_docdate as fechadocumento,
        gbpr.bpr_docdate as fecha_ven,
        gbpr.bpr_docdate as fechavencimiento,
        bpr_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mac1.ac1_font_type as doctype,
        mdt_docname as tipo,
        '' as refFiscal,
        case
            when mac1.ac1_font_type = 5 then  get_dynamic_conversion(:currency, get_localcur(),bpr_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),bpr_docdate,mac1.ac1_credit ,get_localcur())
        end	 as total_doc,
        case
            when mac1.ac1_font_type = 5 then get_dynamic_conversion(:currency, get_localcur(),bpr_docdate,mac1.ac1_debit ,get_localcur()) * -1
            else  get_dynamic_conversion(:currency, get_localcur(),bpr_docdate,mac1.ac1_credit ,get_localcur()) * -1
        end	 as totalfactura,
         get_dynamic_conversion(:currency, get_localcur(),bpr_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(gbpr.bpr_currency, gbpr.bpr_docdate) as tasa_dia,
        gbpr.bpr_cardname as nombreproveedor,
        get_localcur() as monedadocumento,
        :fecha as  fechacorte,
        ac1_line_num,
        ac1_cord,
        1 as cardtype
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join gbpr
        on gbpr.bpr_doctype = mac1.ac1_font_type
        and gbpr.bpr_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        and mac1.business = :business
        --NOTA CREDITO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num,
        mac1.ac1_legal_num as codigoproveedor,
        mac1.ac1_account as cuenta,
        mac1.ac1_account,
        CURRENT_DATE - vnc_duedate dias,
        CURRENT_DATE - vnc_duedate dias_atrasado,
        dvnc.vnc_comment,
        get_localcur() as currency,
        mac1.ac1_font_key as dvf_docentry,
        mac1.ac1_font_key as docentry,
        dvnc.vnc_docnum,
        dvnc.vnc_docnum as numerodocumento,
        dvnc.vnc_docdate as fecha_doc,
        dvnc.vnc_docdate as fechadocumento,
        dvnc.vnc_duedate as fecha_ven,
        dvnc.vnc_duedate as fechavencimiento,
        vnc_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mac1.ac1_font_type as doctype,
        mdt_docname as tipo,
        '' as refFiscal,
        case
            when mac1.ac1_font_type = 5 then get_dynamic_conversion(:currency, get_localcur(),vnc_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),vnc_docdate,mac1.ac1_credit ,get_localcur())
        end	 as total_doc,
        case
            when mac1.ac1_font_type = 5 then  get_dynamic_conversion(:currency, get_localcur(),vnc_docdate,mac1.ac1_debit,get_localcur()) * -1
            else  get_dynamic_conversion(:currency, get_localcur(),vnc_docdate,mac1.ac1_credit ,get_localcur()) * -1
        end	 as totalfactura,
        get_dynamic_conversion(:currency, get_localcur(),vnc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ,get_localcur())  as saldo_venc,
        '' retencion,
        get_tax_currency(dvnc.vnc_currency,	dvnc.vnc_docdate) as tasa_dia,
        dvnc.vnc_cardname as nombreproveedor,
        get_localcur() as monedadocumento,
        :fecha as  fechacorte,
        ac1_line_num,
        ac1_cord,
        1 as cardtype
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dvnc
        on dvnc.vnc_doctype = mac1.ac1_font_type
        and dvnc.vnc_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        and mac1.business = :business
        --NOTA DEBITO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num,
        mac1.ac1_legal_num as codigoproveedor,
        mac1.ac1_account as cuenta,
        mac1.ac1_account,
        CURRENT_DATE - vnd_duedate dias,
        CURRENT_DATE - vnd_duedate dias_atrasado,
        dvnd.vnd_comment,
        get_localcur() as currency,
        mac1.ac1_font_key as dvf_docentry,
        mac1.ac1_font_key as docentry,
        dvnd.vnd_docnum,
        dvnd.vnd_docnum as numerodocumento,
        dvnd.vnd_docdate as fecha_doc,
        dvnd.vnd_docdate as fechadocumento,
        dvnd.vnd_duedate as fecha_ven,
        dvnd.vnd_duedate as fechavencimiento,
        vnd_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mac1.ac1_font_type as doctype,
        mdt_docname as tipo,
        '' as refFiscal,
        case
            when mac1.ac1_font_type = 5 then  get_dynamic_conversion(:currency, get_localcur(),vnd_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),vnd_docdate,mac1.ac1_credit ,get_localcur())
        end	 as total_doc,
        case
            when mac1.ac1_font_type = 5 then  get_dynamic_conversion(:currency, get_localcur(),vnd_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),vnd_docdate,mac1.ac1_credit ,get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency, get_localcur(),vnd_docdate,(mac1.ac1_debit) - (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(dvnd.vnd_currency, dvnd.vnd_docdate) as tasa_dia,
        dvnd.vnd_cardname as nombreproveedor,
        get_localcur() as monedadocumento,
        :fecha as  fechacorte,
        ac1_line_num,
        ac1_cord,
        1 as cardtype
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dvnd
        on dvnd.vnd_doctype = mac1.ac1_font_type
        and dvnd.vnd_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        and mac1.business = :business
        --ASIENTOS MANUALES
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num,
        mac1.ac1_legal_num as codigoproveedor,
        mac1.ac1_account as cuenta,
        mac1.ac1_account,
        CURRENT_DATE - tmac.mac_doc_duedate dias,
        CURRENT_DATE - tmac.mac_doc_duedate dias_atrasado,
        tmac.mac_comments,
        get_localcur() as currency,
        0 as dvf_docentry,
        0 as docentry,
        0 as docnum,
        0 as numerodocumento,
        tmac.mac_doc_date as fecha_doc,
        tmac.mac_doc_date as fechadocumento,
        tmac.mac_doc_duedate as fecha_ven,
        tmac.mac_doc_duedate as fechavencimiento,
        0 as id_origen,
        18 as numtype,
        18 as doctype,
        mdt_docname as tipo,
        '' as refFiscal,
        case
            when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date, mac1.ac1_debit ,get_localcur())
            when mac1.ac1_cord = 1 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date, mac1.ac1_credit ,get_localcur())
        end	 as total_doc,
        case
            when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date, mac1.ac1_debit ,get_localcur())
            when mac1.ac1_cord = 1 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date, mac1.ac1_credit ,get_localcur())
        end	 as totalfactura,
         get_dynamic_conversion(:currency, get_localcur(),mac_doc_date, (mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia,
        dmsn.dms_card_name as nombreproveedor,
        get_localcur() as monedadocumento,
        :fecha as  fechacorte,
        ac1_line_num,
        ac1_cord,
        1 as cardtype
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join tmac
        on tmac.mac_trans_id = mac1.ac1_font_key
        and tmac.mac_doctype = mac1.ac1_font_type
        inner join dmsn
        on mac1.ac1_card_type = dmsn.dms_card_type
        and mac1.ac1_legal_num = dmsn.dms_card_code
        where mac1.ac1_legal_num = :cardcode
        and mac1.ac1_card_type = '1'
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        and mac1.business = :business
        -- RECIBOS DE CAJA DE DISTRIBUCION
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num,
        mac1.ac1_legal_num as codigoproveedor,
        mac1.ac1_account as cuenta,
        mac1.ac1_account,
        CURRENT_DATE - vrc_duedate dias,
        CURRENT_DATE - vrc_duedate dias_atrasado,
        dvrc.vrc_comment,
        get_localcur() as currency,
        mac1.ac1_font_key as dvf_docentry,
        mac1.ac1_font_key as docentry,
        dvrc.vrc_docnum,
        dvrc.vrc_docnum as numerodocumento,
        dvrc.vrc_docdate as fecha_doc,
        dvrc.vrc_docdate as fechadocumento,
        dvrc.vrc_duedate as fecha_ven,
        dvrc.vrc_duedate as fechavencimiento,
        vrc_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mac1.ac1_font_type as doctype,
        '' as refFiscal,
        case 
	  	 when mac1.ac1_font_type = 47 AND mac1.ac1_credit = 0 then  mdt_docname 
	  	 when mac1.ac1_font_type = 47 AND mac1.ac1_debit = 0 then 'Anticipo Cliente (Pasanaku)'
	    end as tipo,
        case
            when mac1.ac1_font_type = 47 and ac1_debit > 0 then get_dynamic_conversion('BS', get_localcur(),vrc_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion('BS', get_localcur(),vrc_docdate,mac1.ac1_credit ,get_localcur())
        end	 as total_doc,
        case
            when mac1.ac1_font_type = 47 and ac1_debit > 0 then get_dynamic_conversion('BS', get_localcur(),vrc_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion('BS', get_localcur(),vrc_docdate,mac1.ac1_credit ,get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency, get_localcur(),vrc_docdate,(mac1.ac1_debit) - (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(dvrc.vrc_currency, dvrc.vrc_docdate) as tasa_dia,
        dvrc.vrc_cardname as nombreproveedor,
        get_localcur() as monedadocumento,
        :fecha as  fechacorte,
        ac1_line_num,
        ac1_cord,
        1 as cardtype
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dvrc
        on dvrc.vrc_doctype = mac1.ac1_font_type
        and dvrc.vrc_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        and mac1.business = :business";


        return $this->ci->pedeo->queryTable($sql,array(
            ":cardcode" => $cliente,
            ":fecha"    => $fecha,
            ":currency" => $currency,
            ":business" => $business
        ));

    
    }

    public function getECP($proveedor,$fecha,$currency,$business){


        $sql = "SELECT distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num,
        mac1.ac1_legal_num as codigoproveedor,
        mac1.ac1_account as cuenta,
        mac1.ac1_account,
        CURRENT_DATE - cfc_duedate dias,
        CURRENT_DATE - cfc_duedate dias_atrasado,
        dcfc.cfc_comment,
        get_localcur() as currency,
        mac1.ac1_font_key as dvf_docentry,
        mac1.ac1_font_key as docentry,
        dcfc.cfc_docnum,
        dcfc.cfc_docnum as numerodocumento,
        dcfc.cfc_docdate as fecha_doc,
        dcfc.cfc_docdate as fechadocumento,
        dcfc.cfc_duedate as fecha_ven,
        dcfc.cfc_duedate as fechavencimiento,
        cfc_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mac1.ac1_font_type as doctype,
        mdt_docname as tipo,
        dcfc.cfc_tax_control_num as refFiscal,
        case 
            when mac1.ac1_font_type = 15 OR mac1.ac1_font_type = 46 then get_dynamic_conversion(:currency, get_localcur(),cfc_docdate,mac1.ac1_credit,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),cfc_docdate,mac1.ac1_debit,get_localcur())
        end	 as total_doc,
        case
            when mac1.ac1_font_type = 15 OR mac1.ac1_font_type = 46 then get_dynamic_conversion(:currency, get_localcur(),cfc_docdate,mac1.ac1_credit,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),cfc_docdate,mac1.ac1_debit,get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency, get_localcur(),cfc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_credit) ,get_localcur())  as saldo_venc,
        '' retencion,
        get_tax_currency(dcfc.cfc_currency,dcfc.cfc_docdate) as tasa_dia,
        dcfc.cfc_cardname as nombreproveedor,
        get_localcur() as monedadocumento,
        :fecha as  fechacorte,
        ac1_line_num,
        ac1_cord,
        2 as cardtype
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcfc
        on dcfc.cfc_doctype = mac1.ac1_font_type
        and dcfc.cfc_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and ABS((mac1.ac1_ven_credit) - (mac1.ac1_ven_debit)) > 0
        and mac1.business = :business
        --PAGO EFECTUADO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num,
        mac1.ac1_legal_num as codigoproveedor,
        mac1.ac1_account as cuenta,
        mac1.ac1_account,
        CURRENT_DATE - gbpe.bpe_docdate as dias,
        CURRENT_DATE - gbpe.bpe_docdate as dias_atrasado,
        gbpe.bpe_comments as bpr_comment,
        get_localcur() as currency,
        mac1.ac1_font_key as dvf_docentry,
        mac1.ac1_font_key as docentry,
        gbpe.bpe_docnum,
        gbpe.bpe_docnum as numerodocumento,
        gbpe.bpe_docdate as fecha_doc,
        gbpe.bpe_docdate as fechadocumento,
        gbpe.bpe_docdate as fecha_ven,
        gbpe.bpe_docdate as fechavencimiento,
        gbpe.bpe_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mac1.ac1_font_type as doctype,
        mdt_docname as tipo,
        '' as refFiscal,
        case
            when mac1.ac1_font_type = 15 then  get_dynamic_conversion(:currency, get_localcur(),bpe_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),bpe_docdate,mac1.ac1_debit ,get_localcur())
        end	 as total_doc,
        case
            when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),bpe_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),bpe_docdate,mac1.ac1_debit ,get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency, get_localcur(),bpe_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ,get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(gbpe.bpe_currency, gbpe.bpe_docdate) as tasa_dia,
        gbpe.bpe_cardname as nombreproveedor,
        get_localcur() as monedadocumento,
        :fecha as  fechacorte,
        ac1_line_num,
        ac1_cord,
        2 as cardtype
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join gbpe
        on gbpe.bpe_doctype = mac1.ac1_font_type
        and gbpe.bpe_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        and mac1.business = :business
        --NOTA CREDITO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num,
        mac1.ac1_legal_num as codigoproveedor,
        mac1.ac1_account as cuenta,
        mac1.ac1_account,
        CURRENT_DATE - dcnc.cnc_docdate dias,
        CURRENT_DATE - dcnc.cnc_docdate as dias_atrasado,
        dcnc.cnc_comment as bpr_comment,
        get_localcur() as currency,
        mac1.ac1_font_key as dvf_docentry,
        mac1.ac1_font_key as docentry,
        dcnc.cnc_docnum,
        dcnc.cnc_docnum as numerodocumento,
        dcnc.cnc_docdate as fecha_doc,
        dcnc.cnc_docdate as fechadocumento,
        dcnc.cnc_duedate as fecha_ven,
        dcnc.cnc_duedate as fechavencimiento,
        dcnc.cnc_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mac1.ac1_font_type as doctype,
        mdt_docname as tipo,
        '' as refFiscal,
        case
            when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),cnc_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),cnc_docdate,mac1.ac1_debit ,get_localcur())
        end	 as total_doc,
        case
            when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),cnc_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),cnc_docdate,mac1.ac1_debit ,get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency, get_localcur(),cnc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)  ,get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(dcnc.cnc_currency, dcnc.cnc_docdate) as tasa_dia,
        dcnc.cnc_cardname as nombreproveedor,
        get_localcur() as monedadocumento,
        :fecha as  fechacorte,
        ac1_line_num,
        ac1_cord,
        2 as cardtype
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcnc
        on dcnc.cnc_doctype = mac1.ac1_font_type
        and dcnc.cnc_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        and mac1.business = :business
        --NOTA DEBITO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num,
        mac1.ac1_legal_num as codigoproveedor,
        mac1.ac1_account as cuenta,
        mac1.ac1_account,
        CURRENT_DATE - dcnd.cnd_docdate as dias,
        CURRENT_DATE - dcnd.cnd_docdate as dias_atrasado,
        dcnd.cnd_comment as bpr_comment,
        get_localcur() as currency,
        mac1.ac1_font_key as dvf_docentry,
        mac1.ac1_font_key as docentry,
        dcnd.cnd_docnum,
        dcnd.cnd_docnum as numerodocumento,
        dcnd.cnd_docdate as fecha_doc,
        dcnd.cnd_docdate as fechadocumento,
        dcnd.cnd_duedate as fecha_ven,
        dcnd.cnd_duedate as fechavencimiento,
        dcnd.cnd_docnum as id_origen,
        mac1.ac1_font_type as numtype,
        mac1.ac1_font_type as doctype,
        mdt_docname as tipo,
        '' as refFiscal,
        case
            when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),cnd_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),cnd_docdate,mac1.ac1_credit ,get_localcur())
        end	 as total_doc,
        case
            when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency, get_localcur(),cnd_docdate,mac1.ac1_debit ,get_localcur())
            else get_dynamic_conversion(:currency, get_localcur(),cnd_docdate,mac1.ac1_credit ,get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency, get_localcur(),cnd_docdate,(mac1.ac1_ven_credit) - (mac1.ac1_debit) ,get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(dcnd.cnd_currency, dcnd.cnd_docdate) as tasa_dia,
        dcnd.cnd_cardname as nombreproveedor,
        get_localcur() as monedadocumento,
        :fecha as  fechacorte,
        ac1_line_num,
        ac1_cord,
        2 as cardtype
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcnd
        on dcnd.cnd_doctype = mac1.ac1_font_type
        and dcnd.cnd_docentry = mac1.ac1_font_key
        where mac1.ac1_legal_num = :cardcode
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        and mac1.business = :business
        --ASIENTOS MANUALES
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num,
        mac1.ac1_legal_num as codigoproveedor,
        mac1.ac1_account as cuenta,
        mac1.ac1_account,
        CURRENT_DATE - tmac.mac_doc_duedate dias,
        CURRENT_DATE - tmac.mac_doc_duedate dias_atrasado,
        tmac.mac_comments,
        get_localcur() as currency,
        0 as dvf_docentry,
        0 as docentry,
        0 as docnum,
        0 as numerodocumento,
        tmac.mac_doc_date as fecha_doc,
        tmac.mac_doc_date as fechadocumento,
        tmac.mac_doc_duedate as fecha_ven,
        tmac.mac_doc_duedate as fechavencimiento,
        0 as id_origen,
        18 as numtype,
        18 as doctype,
        mdt_docname as tipo,
        '' as refFiscal,
        case
            when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date,mac1.ac1_debit ,get_localcur())
            when mac1.ac1_cord = 1 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date,mac1.ac1_credit ,get_localcur())
        end	 as total_doc,
        case
            when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date,mac1.ac1_debit ,get_localcur())
            when mac1.ac1_cord  = 1 then get_dynamic_conversion(:currency, get_localcur(),mac_doc_date,mac1.ac1_credit ,get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency, get_localcur(),mac_doc_date,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)  ,get_localcur()) as saldo_venc,
        '' retencion,
        get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia,
        dmsn.dms_card_name as nombreproveedor,
        get_localcur() as monedadocumento,
        :fecha as  fechacorte,
        ac1_line_num,
        ac1_cord,
        2 as cardtype
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join tmac
        on tmac.mac_trans_id = mac1.ac1_font_key
        and tmac.mac_doctype = mac1.ac1_font_type
        inner join dmsn
        on mac1.ac1_card_type = dmsn.dms_card_type
        and mac1.ac1_legal_num = dmsn.dms_card_code
        where mac1.ac1_legal_num = :cardcode
        and mac1.ac1_card_type = '2'
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        and mac1.business = :business";

        return $this->ci->pedeo->queryTable($sql,array(
            ":cardcode" => $proveedor,
            ":fecha"    => $fecha,
            ":currency" => $currency,
            ":business" => $business
        ));
    }






}