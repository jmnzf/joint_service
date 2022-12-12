<?php
// PRESUPUESTOS
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class bulkPayments extends REST_Controller
{

    private $pdo;

    public function __construct()
    {

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        parent::__construct();
        $this->load->database();
        $this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);

    }

    public function getbulkPaymentsByFilters_post()
    {
        $Data = $this->post();

        if (!isset($Data['doc_startdate'], $Data['doc_enddate'])) {
            $this->response(
                array(
                    'error' => true,
                    'data' => [],
                    'mensaje' => 'La informacion enviada no es valida'
                ), REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $config = array(
            [
                "table" => "dcfc",
                "prefix" => "cfc",
                "rpltarget" => "dcfc_where"
            ],
            [
                "table" => "gbpe",
                "prefix" => "bpe",
                "rpltarget" => "gbpe_where"
            ],
            [
                "table" => "dcnc",
                "prefix" => "cnc",
                "rpltarget" => "dcnc_where"
            ],
            [
                "table" => "dcnd",
                "prefix" => "cnd",
                "rpltarget" => "dcnd_where"
            ],
            [
                "table" => "tmac",
                "prefix" => "mac",
                "rpltarget" => "tmac_where"
            ]
        );

        $sqlSelect = "SELECT distinct dmdt.mdt_docname,
        mac1.ac1_font_key,
        mac1.ac1_legal_num                                    as codigocliente,
        dmsn.dms_card_name                                       nombrecliente,

        dcfc.cfc_currency                                        monedadocumento,
        '{fecha}'                                            fechacorte,
        '{fecha}'- cfc_duedate                               dias,
        dcfc.cfc_comment,
        dcfc.cfc_currency,
        mac1.ac1_font_key                                     as cfc_docentry,
        dcfc.cfc_docnum,
        dcfc.cfc_docdate                                      as FechaDocumento,
        dcfc.cfc_duedate                                      as FechaVencimiento,
        dcfc.cfc_docnum                                       as NumeroDocumento,
        mac1.ac1_font_type                                    as numtype,
        mdt_docname                                           as tipo,
        get_dynamic_conversion(:currency,cfc_currency,cfc_docdate,dcfc.cfc_doctotal ,get_localcur())  as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) ,get_localcur())  as saldo,
        ''                                                       retencion,
        get_tax_currency(dcfc.cfc_currency, dcfc.cfc_docdate) as tasa_dia,
        CASE
            WHEN ('{fecha}'- dcfc.cfc_duedate) >= 0 and ('{fecha}'- dcfc.cfc_duedate) <= 30
                then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))  ,get_localcur())
            ELSE 0 END                                           uno_treinta,
        CASE
            WHEN ('{fecha}'- dcfc.cfc_duedate) >= 31 and ('{fecha}'-
                                                              dcfc.cfc_duedate) <= 60
                then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))  ,get_localcur())
            ELSE 0 END                                           treinta_uno_secenta,
        CASE
            WHEN
                        ('{fecha}'- dcfc.cfc_duedate) >= 61 and ('{fecha}'- dcfc.cfc_duedate) <= 90
                then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))  ,get_localcur())
            ELSE 0 END                                           secenta_uno_noventa,
        CASE
            WHEN ('{fecha}'- dcfc.cfc_duedate) >= 91
                then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))  ,get_localcur())
            ELSE 0
            END                                                  mayor_noventa,
            '' as comentario_asiento
from mac1
 inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
 inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
 inner join dcfc on dcfc.cfc_doctype = mac1.ac1_font_type and dcfc.cfc_docentry = mac1.ac1_font_key
 inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
where dmsn.dms_card_type = '2' {{dcfc_where}}
GROUP BY dmdt.mdt_docname,
 mac1.ac1_font_key,
 mac1.ac1_legal_num,
 dmsn.dms_card_name,

 dcfc.cfc_currency,
 dcfc.cfc_comment,
 dcfc.cfc_currency,
 mac1.ac1_font_key,
 dcfc.cfc_docnum,
 dcfc.cfc_docdate,
 dcfc.cfc_duedate,
 dcfc.cfc_docnum,
 mac1.ac1_font_type,
 mdt_docname,
 dcfc.cfc_doctotal
HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0

union all
select distinct dmdt.mdt_docname,
        mac1.ac1_font_key,
        mac1.ac1_legal_num                                    as codigoproveedor,
        dmsn.dms_card_name                                       nombreproveedor,

        gbpe.bpe_currency                                        monedadocumento,
        '{fecha}'                                            fechacorte,
        '{fecha}'- gbpe.bpe_docdate                       as dias,
        gbpe.bpe_comments                                     as bpe_comment,
        gbpe.bpe_currency,
        mac1.ac1_font_key                                     as cfc_docentry,
        gbpe.bpe_docnum,
        gbpe.bpe_docdate                                      as FechaDocumento,
        gbpe.bpe_docdate                                      as FechaVencimiento,
        gbpe.bpe_docnum                                       as NumeroDocumento,
        mac1.ac1_font_type                                    as numtype,
        'ANTICIPO'                                            as tipo,
        case
            when mac1.ac1_font_type = gbpe.bpe_doctype then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,sum(mac1.ac1_debit)  ,get_localcur())
            else get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,sum(mac1.ac1_debit),get_localcur())
            end                                               as totalfactura,
         get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())     as saldo,
        ''                                                       retencion,
        get_tax_currency(gbpe.bpe_currency, gbpe.bpe_docdate) as tasa_dia,
        CASE
            WHEN ('{fecha}'- gbpe.bpe_docdate) >= 0 and ('{fecha}'- gbpe.bpe_docdate) <= 30 then
               get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0 END                                           uno_treinta,
        CASE
            WHEN ('{fecha}'- gbpe.bpe_docdate) >= 31 and ('{fecha}'- gbpe.bpe_docdate) <= 60
                then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0 END                                           treinta_uno_secenta,
        CASE
            WHEN ('{fecha}'- gbpe.bpe_docdate) >= 61 and ('{fecha}'- gbpe.bpe_docdate) <= 90
                then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0 END                                           secenta_uno_noventa,
        CASE
            WHEN ('{fecha}'- gbpe.bpe_docdate) >= 91
                then get_dynamic_conversion(:currency,get_localcur(),gbpe.bpe_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0
            END                                                  mayor_noventa,
            '' as comentario_asiento

from mac1
 inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
 inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
 inner join gbpe on gbpe.bpe_doctype = mac1.ac1_font_type and gbpe.bpe_docentry =
                                                              mac1.ac1_font_key
 inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
where  dmsn.dms_card_type = '2' {{gbpe_where}}
GROUP BY dmdt.mdt_docname,
 mac1.ac1_font_key,
 mac1.ac1_legal_num,
 dmsn.dms_card_name,
 gbpe.bpe_currency,
 gbpe.bpe_comments,
 gbpe.bpe_currency,
 mac1.ac1_font_key,
 gbpe.bpe_docnum,
 gbpe.bpe_docdate,
 gbpe.bpe_docdate,
 gbpe.bpe_docnum,
 mac1.ac1_font_type,
         gbpe.bpe_doctype
HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0
union all
select distinct dmdt.mdt_docname,
        mac1.ac1_font_key,
        mac1.ac1_legal_num                                    as codigoproveedor,
        dmsn.dms_card_name                                       nombreproveedor,

        dcnc.cnc_currency                                        monedadocumento,
        '{fecha}'                                            fechacorte,
        '{fecha}'- dcnc.cnc_docdate                       as dias,
        dcnc.cnc_comment                                      as bpe_comment,
        dcnc.cnc_currency,
        mac1.ac1_font_key                                     as cfc_docentry,
        dcnc.cnc_docnum,
        dcnc.cnc_docdate                                      as FechaDocumento,
        dcnc.cnc_duedate                                      as FechaVencimiento,
        dcnc.cnc_docnum                                       as NumeroDocumento,
        mac1.ac1_font_type                                    as numtype,
        mdt_docname                                           as tipo,
        case
            when mac1.ac1_font_type = dcnc.cnc_doctype then get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,sum(mac1.ac1_debit),get_localcur())
            else get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,sum(mac1.ac1_debit),get_localcur())
            end                                               as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())    as saldo,
        ''                                                       retencion,
        get_tax_currency(dcnc.cnc_currency, dcnc.cnc_docdate) as tasa_dia,
        CASE
            WHEN ('{fecha}'- dcnc.cnc_duedate) >= 0 and ('{fecha}'- dcnc.cnc_duedate) <= 30 then
                get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0 END                                           uno_treinta,
        CASE
            WHEN
                        ('{fecha}'- dcnc.cnc_duedate) >= 31 and ('{fecha}'- dcnc.cnc_duedate) <= 60 then
                get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0 END                                           treinta_uno_secenta,
        CASE
            WHEN
                        ('{fecha}'- dcnc.cnc_duedate) >= 61 and ('{fecha}'- dcnc.cnc_duedate) <= 90 then
                get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0 END                                           secenta_uno_noventa,
        CASE
            WHEN ('{fecha}'- dcnc.cnc_duedate) >= 91
                then get_dynamic_conversion(:currency,get_localcur(),dcnc.cnc_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0
            END                                                  mayor_noventa,
            '' as comentario_asiento

from mac1
 inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
 inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
 inner join dcnc on dcnc.cnc_doctype = mac1.ac1_font_type and dcnc.cnc_docentry = mac1.ac1_font_key
 inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
where dmsn.dms_card_type = '2' {{dcnc_where}}
GROUP BY dmdt.mdt_docname,
 mac1.ac1_font_key,
 mac1.ac1_legal_num,
 dmsn.dms_card_name,
 dcnc.cnc_currency,
 dcnc.cnc_comment,
 dcnc.cnc_currency,
 mac1.ac1_font_key,
 dcnc.cnc_docnum,
 dcnc.cnc_docdate,
 dcnc.cnc_duedate,
 dcnc.cnc_docnum,
 mac1.ac1_font_type,
 mdt_docname,
         dcnc.cnc_doctype
HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0

union all

select distinct dmdt.mdt_docname,
        mac1.ac1_font_key,
        mac1.ac1_legal_num                                    as CodigoCliente,
        dmsn.dms_card_name                                       NombreCliente,

        dcnd.cnd_currency                                        monedadocumento,
        '{fecha}'                                            fechacorte,
        '{fecha}'- dcnd.cnd_docdate                       as dias,
        dcnd.cnd_comment                                      as bpe_comment,
        dcnd.cnd_currency,
        mac1.ac1_font_key                                     as cfc_docentry,
        dcnd.cnd_docnum,
        dcnd.cnd_docdate                                      as FechaDocumento,
        dcnd.cnd_duedate                                      as FechaVencimiento,
        dcnd.cnd_docnum                                       as NumeroDocumento,
        mac1.ac1_font_type                                    as numtype,
        mdt_docname                                           as tipo,
        case
            when mac1.ac1_font_type = dcnd.cnd_doctype then  get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,sum(mac1.ac1_debit),get_localcur())
            else get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,sum(mac1.ac1_credit),get_localcur())
            end                                               as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,sum((mac1.ac1_ven_credit) - (mac1.ac1_credit)),get_localcur())     as saldo,
        ''                                                       retencion,
        get_tax_currency(dcnd.cnd_currency, dcnd.cnd_docdate) as tasa_dia,
        CASE
            WHEN ('{fecha}'- dcnd.cnd_duedate) >= 0 and ('{fecha}'- dcnd.cnd_duedate)
                <= 30 then get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0 END                                           uno_treinta,
        CASE
            WHEN ('{fecha}'- dcnd.cnd_duedate) >= 31 and ('{fecha}'-
                                                              dcnd.cnd_duedate) <= 60
                then get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0
            END                                                  treinta_uno_secenta,
        CASE
            WHEN ('{fecha}'- dcnd.cnd_duedate) >= 61
                and ('{fecha}'- dcnd.cnd_duedate) <= 90
                then get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0 END                                           secenta_uno_noventa,
        CASE
            WHEN
                ('{fecha}'- dcnd.cnd_duedate) >= 91
                then get_dynamic_conversion(:currency,get_localcur(),dcnd.cnd_docdate,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0
            END                                                  mayor_noventa,
            '' as comentario_asiento
from mac1
 inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
 inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
 inner join dcnd on dcnd.cnd_doctype = mac1.ac1_font_type and
                    dcnd.cnd_docentry = mac1.ac1_font_key
 inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
where dmsn.dms_card_type = '2' {{dcnd_where}}
group by dmdt.mdt_docname,
 mac1.ac1_font_key,
 mac1.ac1_legal_num,
 dmsn.dms_card_name,
 dcnd.cnd_currency,
 dcnd.cnd_comment,
 dcnd.cnd_currency,
 mac1.ac1_font_key,
 dcnd.cnd_docnum,
 dcnd.cnd_docdate,
 dcnd.cnd_duedate,
 dcnd.cnd_docnum,
 mac1.ac1_font_type,
 mdt_docname,
         dcnd.cnd_doctype
HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0

union all
select  dmdt.mdt_docname,
        mac1.ac1_font_key,
        case
            when ac1_card_type = '1'
                then mac1.ac1_legal_num
            when ac1_card_type = '2'
                then mac1.ac1_legal_num
            end                                                as codigoproveedor,
        dmsn.dms_card_name                                        NombreCliente,

        tmac.mac_currency,
        '{fecha}'                                             fechacorte,
        CURRENT_DATE - tmac.mac_doc_duedate                       dias_atrasado,
        tmac.mac_comments,
        tmac.mac_currency,
        mac_trans_id                                           as dvf_docentry,
        0                                                      as docnum,
        tmac.mac_doc_date                                      as fecha_doc,
        tmac.mac_doc_duedate                                   as fecha_ven,
        mac_trans_id                                           as id_origen,
        18                                                     as numtype,
        mdt_docname                                            as tipo,
        case
            when mac1.ac1_cord = 0
                then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,sum(mac1.ac1_debit),get_localcur())
            when mac1.ac1_cord = 1
                then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,sum(mac1.ac1_credit),get_localcur())
            end                                                as total_doc,
        get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())  as saldo_venc,
        ''                                                        retencion,
        get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia,
        CASE
            WHEN ('{fecha}'- tmac.mac_doc_duedate) >= 0 and ('{fecha}'-
                                                                 tmac.mac_doc_duedate) <= 30
                then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0 END                                            uno_treinta,
        CASE
            WHEN
                        ('{fecha}'- tmac.mac_doc_duedate) >= 31 and ('{fecha}'-
                                                                         tmac.mac_doc_duedate) <= 60
                then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0 END                                            treinta_uno_secenta,
        CASE
            WHEN
                        ('{fecha}'- tmac.mac_doc_duedate) >= 61 and ('{fecha}'-
                                                                         tmac.mac_doc_duedate) <= 90
                then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0 END                                            secenta_uno_noventa,
        CASE
            WHEN ('{fecha}'- tmac.mac_doc_duedate) >= 91
                then get_dynamic_conversion(:currency,get_localcur(),tmac.mac_doc_duedate ,SUM((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)),get_localcur())
            ELSE 0
            END                                                   mayor_noventa,
            ac1_comments as comentario_asiento
from mac1
 inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
 inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
 inner join tmac on tmac.mac_trans_id = mac1.ac1_font_key and
 tmac.mac_doctype = mac1.ac1_font_type
 inner join dmsn on mac1.ac1_card_type = dmsn.dms_card_type
and mac1.ac1_legal_num = dmsn.dms_card_code
where dmsn.dms_card_type = '2' {{tmac_where}}
group by dmdt.mdt_docname,
 mac1.ac1_font_key,
 case
     when ac1_card_type = '1'
         then mac1.ac1_legal_num
     when ac1_card_type = '2'
         then mac1.ac1_legal_num
     end,
 dmsn.dms_card_name,
 tmac.mac_currency,
 tmac.mac_comments,
 tmac.mac_currency,
 mac_trans_id,
 tmac.mac_doc_date,
 tmac.mac_doc_duedate,
 mac_trans_id,
 mdt_docname, mac1.ac1_cord,ac1_comments
 HAVING ABS(sum((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit))) > 0";

        foreach ($config as $key => $value) {
            $where = "";
            $dateField = "";
            switch ($value['table']) {
                case 'tmac':
                    $dateField = ($Data['doc_filter'] == 1) ? "_doc_date" : "_doc_duedate";
                    $where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
                    $where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND ac1_legal_num in({$Data['doc_cardcode']})" : "";
                    break;
                case 'gbpe':
                    $dateField = ($Data['doc_filter'] == 1) ? "_docdate" : "_taxdate";
                    $where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
                    $where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND {$value['table']}.{$value['prefix']}_cardcode in ({$Data['doc_cardcode']})" : "";
                    break;
                default:
                    $dateField = ($Data['doc_filter'] == 1) ? "_docdate" : "_duedate";
                    $where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
                    $where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND {$value['table']}.{$value['prefix']}_cardcode in ({$Data['doc_cardcode']})" : "";
                    break;
            }

            $sqlSelect = str_replace("{{" . $value['rpltarget'] . "}}", $where, $sqlSelect);
        }

        $fecha = $Data['doc_enddate'] ?? date("Y-m-d");
        $sqlSelect = str_replace('{fecha}', $fecha, $sqlSelect);
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":currency" => "BS"));
        if (isset($resSelect[0])) {

            $respuesta = array(
                'error' => false,
                'data' => $resSelect,
                'mensaje' => ''
            );

        } else {

            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'busqueda sin resultados'
            );

        }

        $this->response($respuesta);

    }
}