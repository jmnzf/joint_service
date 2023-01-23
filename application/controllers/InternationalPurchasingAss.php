<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');
require_once(APPPATH . '/asset/vendor/autoload.php');
use Restserver\libraries\REST_Controller;

class InternationalPurchasingAss extends REST_Controller
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

    public function getPurchaseEcBySN_get()
	{

		$Data = $this->get();
		if (!isset($Data['dms_card_code']) OR !isset($Data['business']) OR !isset($Data['branch'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT
					t0.*
				FROM dcec t0
				left join estado_doc t1 on t0.cec_docentry = t1.entry and t0.cec_doctype = t1.tipo
				left join responsestatus t2 on t1.entry = t2.id and t1.tipo = t2.tipo
				where t2.estado = 'Abierto' and t0.cec_cardcode in ({providers})
				and t0.business = :business and t0.branch = :branch";
		$sqlSelect = str_replace("{providers}", $Data['dms_card_code'],$sqlSelect);
		
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(":business" => $Data['business'],":branch" => $Data['branch']));

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}

	public function getPurchaseEcDetailCI_get()
	{

		$Data = $this->get();

		if (!isset($Data['ec1_docentry'])) {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'La informacion enviada no es valida'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

		$sqlSelect = "SELECT
					t1.ec1_acciva,
					t1.ec1_acctcode,
					t1.ec1_avprice,
					t1.ec1_basetype,
					t1.ec1_costcode,
					t1.ec1_discount,
					t1.ec1_docentry,
					t1.ec1_doctype,
					t1.ec1_id,
					t1.ec1_inventory,
					t1.ec1_itemcode,
					t1.ec1_itemname,
					t1.ec1_linenum,
					t1.ec1_linetotal,
					t1.ec1_price,
					t1.ec1_project,
					t1.ec1_quantity - (coalesce(SUM(t3.dc1_quantity),0) + coalesce(SUM(t5.fc1_quantity),0)) ec1_quantity,
					t1.ec1_ubusiness,
					t1.ec1_uom,
					t1.ec1_vat,
					t1.ec1_vatsum,
					t1.ec1_whscode,
					t6.dma_uom_weight as peso,
					t6.dma_uom_vqty as metrocubico,
					t0.cec_cardcode,
					t0.cec_cardname
					from dcec t0
					left join cec1 t1 on t0.cec_docentry = t1.ec1_docentry
					left join dcdc t2 on t0.cec_docentry = t2.cdc_baseentry and t0.cec_doctype = t2.cdc_basetype
					left join cdc1 t3 on t2.cdc_docentry = t3.dc1_docentry and t1.ec1_itemcode = t3.dc1_itemcode
					left join dcfc t4 on t0.cec_docentry = t4.cfc_baseentry and t0.cec_doctype = t4.cfc_basetype
					left join cfc1 t5 on t4.cfc_docentry = t5.fc1_docentry and t1.ec1_itemcode = t5.fc1_itemcode
					inner join dmar t6 on t1.ec1_itemcode = t6.dma_item_code
					WHERE t1.ec1_docentry in ({entradas})
					GROUP BY
					t1.ec1_acciva,
					t1.ec1_acctcode,
					t1.ec1_avprice,
					t1.ec1_basetype,
					t1.ec1_costcode,
					t1.ec1_discount,
					t1.ec1_docentry,
					t1.ec1_doctype,
					t1.ec1_id,
					t1.ec1_inventory,
					t1.ec1_itemcode,
					t1.ec1_itemname,
					t1.ec1_linenum,
					t1.ec1_linetotal,
					t1.ec1_price,
					t1.ec1_project,
					t1.ec1_ubusiness,
					t1.ec1_uom,
					t1.ec1_vat,
					t1.ec1_vatsum,
					t1.ec1_whscode,
					t1.ec1_quantity,
					t6.dma_uom_weight,
					t6.dma_uom_vqty,
					t0.cec_cardcode,
					t0.cec_cardname";

		$sqlSelect = str_replace("{entradas}", $Data['ec1_docentry'],$sqlSelect);
		
		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		if (isset($resSelect[0])) {

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' => ''
			);
		} else {

			$respuesta = array(
				'error'   => true,
				'data' => array(),
				'mensaje'	=> 'busqueda sin resultados'
			);
		}

		$this->response($respuesta);
	}
}