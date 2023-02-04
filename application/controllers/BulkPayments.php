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
                "table" => "dcsa",
                "prefix" => "csa",
                "rpltarget" => "dcsa_where"
            ],
            [
                "table" => "tmac",
                "prefix" => "mac",
                "rpltarget" => "tmac_where"
            ]
        );

        $sqlSelect = "SELECT distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigocliente,
        dcfc.cfc_cardcode as nombrecliente,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - cfc_duedate dias,
        dcfc.cfc_comment,
        dcfc.cfc_currency,
        mac1.ac1_font_key as dvf_docentry,
        dcfc.cfc_docnum,
        dcfc.cfc_docdate as fechadocumento,
        dcfc.cfc_duedate as fechavencimiento,
        cfc_docnum as numerodocumento,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
        case
        when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,mac1.ac1_credit, get_localcur())
        else get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,mac1.ac1_debit, get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),cfc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_credit) , get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(dcfc.cfc_currency,dcfc.cfc_docdate) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcfc
        on dcfc.cfc_doctype = mac1.ac1_font_type
        and dcfc.cfc_docentry = mac1.ac1_font_key
        where 1 = 1 {{dcfc_where}}
        and ABS((mac1.ac1_ven_credit) - (mac1.ac1_ven_debit)) > 0
        --PAGO EFECTUADO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigocliente,
        gbpe.bpe_cardcode as nombrecliente,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - gbpe.bpe_docdate as dias,
        gbpe.bpe_comments as bpr_comment,
        gbpe.bpe_currency,
        mac1.ac1_font_key as dvf_docentry,
        gbpe.bpe_docnum,
        gbpe.bpe_docdate as fechadocumento,
        gbpe.bpe_docdate as fechavencimiento,
        gbpe.bpe_docnum as numerodocumento,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
        case
        when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,mac1.ac1_debit, get_localcur())
        else get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,mac1.ac1_debit, get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),bpe_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) , get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(gbpe.bpe_currency,gbpe.bpe_docdate) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join gbpe
        on gbpe.bpe_doctype = mac1.ac1_font_type
        and gbpe.bpe_docentry = mac1.ac1_font_key
        where 1 = 1 {{gbpe_where}}
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --NOTA CREDITO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigocliente,
        dcnc.cnc_cardname as nombrecliente,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - dcnc.cnc_docdate as dias,
        dcnc.cnc_comment as bpr_comment,
        dcnc.cnc_currency,
        mac1.ac1_font_key as dvf_docentry,
        dcnc.cnc_docnum,
        dcnc.cnc_docdate as fechadocumento,
        dcnc.cnc_duedate as fechavencimiento,
        dcnc.cnc_docnum as numerodocumento,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
        case
        when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,mac1.ac1_debit, get_localcur())
        else get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,mac1.ac1_debit, get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),cnc_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) , get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(dcnc.cnc_currency,dcnc.cnc_docdate ) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcnc
        on dcnc.cnc_doctype = mac1.ac1_font_type
        and dcnc.cnc_docentry = mac1.ac1_font_key
        where 1 = 1 {{dcnc_where}}
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --NOTA DEBITO
        union all
        select distinct
        mac1.ac1_font_key,
        mac1.ac1_legal_num as codigocliente,
        dcnd.cnd_cardname as nombrecliente,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - dcnd.cnd_docdate as dias,
        dcnd.cnd_comment as bpr_comment,
        dcnd.cnd_currency,
        mac1.ac1_font_key as dvf_docentry,
        dcnd.cnd_docnum,
        dcnd.cnd_docdate as fechadocumento,
        dcnd.cnd_duedate as fechavencimiento,
        dcnd.cnd_docnum as numerodocumento,
        mac1.ac1_font_type as numtype,
        mdt_docname as tipo,
        case
        when mac1.ac1_font_type = 15 then get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,mac1.ac1_debit, get_localcur())
        else get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,mac1.ac1_credit, get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),cnd_docdate,(mac1.ac1_ven_credit) - (mac1.ac1_debit), get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(dcnd.cnd_currency, dcnd.cnd_docdate) as tasa_dia,
        ac1_line_num,
        ac1_cord
        from  mac1
        inner join dacc
        on mac1.ac1_account = dacc.acc_code
        and acc_businessp = '1'
        inner join dmdt
        on mac1.ac1_font_type = dmdt.mdt_doctype
        inner join dcnd
        on dcnd.cnd_doctype = mac1.ac1_font_type
        and dcnd.cnd_docentry = mac1.ac1_font_key
        where 1 = 1 {{dcnd_where}}
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --ASIENTOS MANUALES
        union all
        select distinct
        mac1.ac1_font_key,
        case
            when ac1_card_type = '1' then mac1.ac1_legal_num
            when ac1_card_type = '2' then mac1.ac1_legal_num
        end as codigocliente,
       '' as nombrecliente,
        mac1.ac1_account as cuenta,
        CURRENT_DATE - tmac.mac_doc_duedate dias,
        tmac.mac_comments,
        tmac.mac_currency,
        0 as dvf_docentry,
        0 as docnum,
        tmac.mac_doc_date as fechadocumento,
        tmac.mac_doc_duedate as fechavencimiento,
        0 as numerodocumento,
        18 as numtype,
        mdt_docname as tipo,
        case
            when mac1.ac1_cord = 0 then get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_debit, get_localcur())
            when mac1.ac1_cord = 1 then get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,mac1.ac1_credit, get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion(:currency,get_localcur(),mac_doc_date,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit), get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(tmac.mac_currency, tmac.mac_doc_date) as tasa_dia,
        ac1_line_num,
        ac1_cord
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
        where 1 = 1 {{tmac_where}}
        and ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)) > 0
        --FACTURA ANTICIPADA DE COMPRAS
        UNION ALL
        SELECT  
        dcsa.csa_docentry as ac1_font_key,
        dcsa.csa_cardcode as codigocliente,
        dcsa.csa_cardname as nombrecliente,
        csa1.sa1_acctcode as cuenta,
        CURRENT_DATE - dcsa.csa_duedate as dias,
        dcsa.csa_comment,
        dcsa.csa_currency,
        csa1.sa1_docentry as cfc_docentry,
        dcsa.csa_docnum as numerodocumento,
        dcsa.csa_docdate as fechadocumento,
        dcsa.csa_duedate as fechavencimiento,
        dcsa.csa_docnum as id_origen,
        csa1.sa1_doctype as numtype,
        mdt_docname as tipo,
        case
        when dcsa.csa_doctype = 36 then get_dynamic_conversion('VES',get_localcur(),dcsa.csa_docdate,dcsa.csa_anticipate_total, get_localcur())
        else get_dynamic_conversion('VES',get_localcur(),dcsa.csa_docdate,dcsa.csa_doctotal, get_localcur())
        end	 as totalfactura,
        get_dynamic_conversion('VES',get_localcur(),dcsa.csa_docdate,(dcsa.csa_anticipate_total) - (dcsa.csa_paytoday) , get_localcur()) as saldo,
        '' retencion,
        get_tax_currency(dcsa.csa_currency,dcsa.csa_docdate) as tasa_dia,
        sa1_linenum,
        0 as ac1_cord
        from dcsa
        inner join csa1 on dcsa.csa_docentry = csa1.sa1_docentry
        inner join dmdt on dmdt.mdt_doctype = dcsa.csa_doctype
        where 1 =1 {{dcsa_where}}";

        foreach ($config as $key => $value) {
            $where = "";
            $dateField = "";
            switch ($value['table']) {
                case 'tmac':
                    $dateField = ($Data['doc_filter'] == 1) ? "_doc_date" : "_doc_duedate";
                    $where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
                    $where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND ac1_legal_num in({$Data['doc_cardcode']})" : "";
                    $where .= " AND {$value['table']}.business = :business AND {$value['table']}.branch = :branch";
                    break;
                case 'gbpe':
                    $dateField = ($Data['doc_filter'] == 1) ? "_docdate" : "_taxdate";
                    $where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
                    $where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND {$value['table']}.{$value['prefix']}_cardcode in ({$Data['doc_cardcode']})" : "";
                    $where .= " AND {$value['table']}.business = :business AND {$value['table']}.branch = :branch";
                    break;
                    case 'dcsa':
                    $dateField = ($Data['doc_filter'] == 1) ? "_docdate" : "_taxdate";
                    $where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
                    $where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND {$value['table']}.{$value['prefix']}_cardcode in ({$Data['doc_cardcode']})" : "";
                    $where .= " AND {$value['table']}.business = :business AND {$value['table']}.branch = :branch";
                    break;
                default:
                    $dateField = ($Data['doc_filter'] == 1) ? "_docdate" : "_duedate";
                    $where = "AND {$value['table']}.{$value['prefix']}{$dateField} BETWEEN '{$Data['doc_startdate']}'AND '{$Data['doc_enddate']}'";
                    $where .= (isset($Data['doc_cardcode']) && !empty($Data['doc_cardcode'])) ? " AND {$value['table']}.{$value['prefix']}_cardcode in ({$Data['doc_cardcode']})" : "";
                    $where .= " AND {$value['table']}.business = :business AND {$value['table']}.branch = :branch";
                    break;
            }

            $sqlSelect = str_replace("{{" . $value['rpltarget'] . "}}", $where, $sqlSelect);
        }

        $fecha = $Data['doc_enddate'] ?? date("Y-m-d");
        $sqlSelect = str_replace('{fecha}', $fecha, $sqlSelect);

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(":currency" => $Data['doc_currency'], ":business" => $Data['business'], ":branch"=> $Data['branch']));
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

    public function createBulkPayments_post(){
        $Data = $this->post();
    }
}