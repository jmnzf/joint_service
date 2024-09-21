<?php

// OPERACIONES DE AYUDA

defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Helpers extends REST_Controller {

	private $pdo;

	public function __construct() {

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);
    $this->load->library('CancelPay');

	}

	public function getFiels_post() {

    $Data = $this->post();

    if(!isset($Data['table_name']) OR
        !isset($Data['table_camps']) OR
        !isset($Data['camps_order']) OR
        !isset($Data['order'])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $filtro = "";
    $limite = "";
		if(isset($Data['filter'])){$filtro = $Data['filter'];}

    if(isset($Data['limit'])){$limite = $Data['limit'];}

    $sqlSelect = "SELECT ".$Data['table_camps']." FROM ".$Data['table_name']." WHERE 1=1 ".$filtro." ORDER BY ".$Data['camps_order']." ".$Data['order']." ".$limite."";

    // print_r($sqlSelect);exit();die();


    $resSelect = $this->pedeo->queryTable($sqlSelect, array());

    if(isset($resSelect[0])){

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    }else{

        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );

    }

    $this->response($respuesta);
	}

  public function get_Query_post() {

    $Data = $this->post();

    if(!isset($Data['tabla']) OR
       !isset($Data['campos']) OR
       !isset($Data['where'])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = " SELECT ".$Data['campos']." FROM ".$Data['tabla']." WHERE ".$Data['where']." ";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array());

    if(isset($resSelect[0])){

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    }else{

        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );

    }

    $this->response($respuesta);
  }

  public function getVendorData_post() {

    $Data = $this->post();

    if(!isset($Data['mev_id']) or
      !isset($Data['business'])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'El empleado del departamento de venta NO EXISTE'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

      $sqlSelect = "SELECT distinct
                        v.mev_id,
                        v.mev_prc_code,
                        v.mev_dpj_pj_code,
                        v.mev_dun_un_code,
                        v.mev_whs_code,
                        w.dws_code,
                        d.dpj_pj_code,
                        d2.dun_un_code,
                        d3.dcc_prc_code,
                        v.mev_card_code,
                        v.mev_id_type
                    FROM treu u
                    inner join dmev v on u.reu_employed = v.mev_id and v.business = :business
                    inner join dmws w on v.mev_whs_code = w.dws_code and w.business = :business
                    inner join dmpj d on v.mev_dpj_pj_code = d.dpj_pj_code and d.business = :business
                    inner join dmun d2 on v.mev_dun_un_code = d2.dun_un_code and d2.business = :business
                    inner join dmcc d3 on v.mev_prc_code = d3.dcc_prc_code and d3.business = :business
                    where u.reu_user = :iduservendor and u.reu_status = 1
                    and u.business = :business";
                    
    $resSelect = $this->pedeo->queryTable($sqlSelect, array(
      ':iduservendor'=> $Data['mev_id'],
      ':business' => $Data['business']
    ));

    if(isset($resSelect[0])){

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    }else{

        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );

    }

    $this->response($respuesta);
  }
 
  public function getVendorSerie_post() {

    $Data = $this->post();

    if(!isset($Data['pgu_id_usuario']) OR !isset($Data['dnd_doctype'])){

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'Informacion enviada no valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = "SELECT v.mev_id, v.mev_prc_code, v.mev_dpj_pj_code, v.mev_dun_un_code,  v.mev_whs_code,
                  d.dnd_serie, pg.pgs_nextnum + 1 as pgs_nextnum, pg.pgs_last_num
                  FROM pgus u
                  inner join dmev v
                  on u.pgu_id_vendor = v.mev_id
                  inner join  dmnd d
                  on u.pgu_code_user = d.dnd_user
                  inner join pgdn pg
                  on pg.pgs_id = d.dnd_serie
                  where u.pgu_code_user = :pgu_code_user
                  and d.dnd_doctype = :dnd_doctype
                  group by v.mev_id, v.mev_prc_code, v.mev_dpj_pj_code, v.mev_dun_un_code,  v.mev_whs_code,
                  d.dnd_serie, pg.pgs_nextnum, pg.pgs_last_num
                  having((pgs_nextnum + 1) < pgs_last_num)";

    // $sqlSelect = "SELECT v.mev_id, v.mev_prc_code, v.mev_dpj_pj_code, v.mev_dun_un_code,  v.mev_whs_code,
    // 							d.dnd_serie
    // 							FROM pgus u
    // 							inner join dmev v
    // 							on u.pgu_id_vendor = v.mev_id
    // 							inner join  dmnd d
    // 							on u.pgu_code_user = d.dnd_user
    // 							where u.pgu_id_usuario = :pgu_id_usuario
    // 							and d.dnd_doctype = :dnd_doctype";


    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':pgu_code_user'=> $Data['pgu_id_usuario'],':dnd_doctype'=> $Data['dnd_doctype']));

    if(isset($resSelect[0])){

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    }else{

        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );

    }

    $this->response($respuesta);
  }

  public function construirUpdate_post() {

    $Data = $this->post();

    $campos = explode(',', $Data['data']);

    $nuevos_campos = array_map(function($campo) {
        return trim($campo).'=:'.trim($campo);
    }, $campos);

    $resultado = implode(',', $nuevos_campos);


    $this->response($resultado);

  
  }

  public function cancelPay_post() {

    $Data = $this->post();


    if (!isset($Data['doctype']) or !isset($Data['docentry']) or !isset($Data['is_fechadoc']) or !isset($Data['comments']) or !isset($Data['series']) or !isset($Data['createby'])) {

        $respuesta = array(
            'error' => true,
            'data' => array(),
            'mensaje' => 'Informacion enviada no valida',
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
    }

    // SE BUSCA EL DOCUMENTO  PARA VERIFICAR QUE SEA UN PAGO A CUENTA DE TERCERO
    $sql = '';
    $op = 0;
    $type = 0;
    $pfd = '';
    $pfc = '';
    $tabla = '';
    $pc1 = '';
    $tabla2 = '';
    $pc2  = '';

    if ( $Data['doctype'] == 19 ){

        $sql = 'SELECT * FROM bpe1 WHERE pe1_docnum = :docnum';
        $op = 0;
        $type = 19;
        $pfd = 'pe1_doctype';
        $pfc = 'pe1_docnum';
        $tabla = 'bpe1';
        $tabla2 = 'gbpe';
        $pc1 = 'pe1';
        $pc2 = 'bpe';

    } else if ( $Data['doctype'] == 20 ) {

        $sql = 'SELECT * FROM bpr1 WHERE pr1_docnum = :docnum';
        $op = 1;
        $type = 20;
        $pfd = 'pr1_doctype';
        $pfc = 'pr1_docnum';
        $tabla = 'bpr1';
        $tabla2 = 'gbpr';
        $pc1 = 'pr1';
        $pc2 = 'bpr';

    } else if ( $Data['doctype'] == 22 ){

        $sql = 'SELECT * FROM crc1 WHERE rc1_docentry = :docnum';
        $op = 1;
        $type = 22;
        $pfd = 'rc1_doctype';
    }

    $document = $this->pedeo->queryTable($sql, array(
        ':docnum' => $Data['docentry']
    ));

    // VALIDAR SI YA ESTA ANULADO
    $validateStatus = $this->pedeo->queryTable("SELECT * FROM tbed where bed_docentry = :bed_docentry AND bed_doctype = :bed_doctype AND bed_status = :bed_status", array(":bed_docentry" => $Data['docentry'], ":bed_doctype" => $Data['doctype'], ":bed_status" => 2));

    if ( isset($validateStatus[0]) ){

      $respuesta = array(
        'error' => true,
        'data' => array(),
        'mensaje' => 'El documento ya esta anulado',
      );

      return $this->response($respuesta);  
    }
    //


    if ( isset($document[0]) ){


        if ($type == 19 || $type == 20) {

            if ( count($document) == 1 && $type == $document[0][$pfd] ){

                // SE VERIFICA QUE NO ESTE EN USO ESE PAGO EN OTRA OPERACIÓN
                // DE UN PAGO
                $verificar = 0;
                $sqlUso = "SELECT * 
                        FROM ".$tabla." 
                        WHERE ".$pc1."_docentry =".$document[0][$pfc]." 
                        AND ".$pc1."_doctype =".$document[0][$pfd];

                $resUso = $this->pedeo->queryTable($sqlUso, array());        

              

                if ( isset($resUso[0]) ) {
                    // SE VERIFICA QUE EL PAGO NO ESTE ANULADO
                    $verificar = 1;
                    $sqlDoc = "SELECT * FROM ".$tabla2." INNER JOIN responsestatus r ON ".$pc2."_docentry = r.id AND ".$pc2."_doctype = r.tipo  WHERE ".$pc2."_docentry = ".$resUso[0][$pfc]." AND r.estado != 'Anulado'";
                    $resDoc = $this->pedeo->queryTable($sqlDoc, array());
                
                    $operacion = '';

                    if ( $type == 20 ){
                        $operacion = 'Pago Recibido';
                    }else if ($type == 19){
                        $operacion = 'Pago Efectuado';
                    }

                    if ( isset($resDoc[0]) ) {

                        $respuesta = array(
                            'error'  => true,
                            'data'   => $resDoc,
                            'mensaje' => 'No se puede anular el pago, porque el mismo fue utilizado en el '.$operacion.' #'.$resDoc[0][$pc2.'_docnum']
                        );
        
                        return $this->response($respuesta); 
                    }
                }

                if ($verificar == 0) {
                    // SE VERIFICA QUE EL DOCUMENTO NO SE USO EN UNA COMPENSACION DE TERCEROS
                    $sqlUso2 = "SELECT * from crc1
                                where rc1_basetype = :rc1_basetype
                                and rc1_baseentry = :rc1_baseentry";

                    $resUso2 = $this->pedeo->queryTable($sqlUso2, array(
                        ':rc1_basetype'  => $document[0][$pfd],
                        ':rc1_baseentry' => $document[0][$pfc]
                    ));      
                    

                    if( isset($resUso2[0]) ) {

                        $sqlDoc2 = "SELECT * FROM dcrc INNER JOIN responsestatus r ON crc_docentry = r.id AND crc_doctype = r.tipo  WHERE crc_docentry = :crc_docentry AND r.estado != 'Anulado'";

                        $resDoc2 = $this->pedeo->queryTable($sqlDoc2, array (
                            ':crc_docentry' => $resUso2[0]['rc1_docentry']
                        ));

                        if (isset($resDoc2[0])){

                            $respuesta = array(
                                'error'  => true,
                                'data'   => $resDoc2,
                                'mensaje' => 'No se puede anular el pago, porque el mismo fue utilizado en la compensación de cuentas de terceros #'.$resDoc2[0]['crc_docnum']
                            );
            
                            return $this->response($respuesta); 
                        }
                    }
                }
            }
        }

        $sqlmac = "SELECT * FROM mac1 where ac1_font_key  = :keey and ac1_font_type = :typee";
        $resMac = $this->pedeo->queryTable($sqlmac, array(
            ':keey'  => $Data['docentry'],
            ':typee' => $Data['doctype']
        ));

        if (!isset($resMac[0])){
            $respuesta = array(
                'error' => true,
                'data' => array(),
                'mensaje' => 'No se encontro el id de la transacción',
            );

            return $this->response($respuesta);  
        }


        $obj = array(
            "Op" => $op,
            "trans_id" => $resMac[0]['ac1_trans_id'],
            "is_fechadoc" => $Data['is_fechadoc'],
            "comments" => $Data['comments'],
            "series" => $Data['series'],
            "currency" => $Data['currency'],
            "business" => $Data['business'],
            "branch" => $Data['branch'],
            "createby" => $Data['createby'],
            "docentry" => $Data['docentry'],
            "doctype" => $Data['doctype'],
            "fecha_select" => date("Y-m-d"),
            "fecha_doc" => $resMac[0]['ac1_doc_date']
        );


        $this->pedeo->trans_begin();


        $res = $this->cancelpay->cancelPayment($obj);


        if (isset($res['error']) && $res['error'] == false) {

            $this->pedeo->trans_commit();

            $respuesta = array(
                'error' => false,
                'data' =>  $res,
                'mensaje' => $res['mensaje'],
            );

        } else {

            $this->pedeo->trans_rollback();

            $respuesta = array(
                'error' => true,
                'data' => $res,
                'mensaje' => 'Error en el proceso de anulación',
            );

            return $this->response($respuesta); 
        }

    }else{

        $respuesta = array(
            'error' => true,
            'data' => array(),
            'mensaje' => 'No existe el documento',
        );

        return $this->response($respuesta);  
    }

    $this->response($respuesta);

  }

  public function getRutaGestion_post(){

    // $sqlSelect = "SELECT distinct on (csn_docnum,bdg_dia)
    //               csn_docnum AS \"NumOrden\",
    //               csn_cardcode AS \"CodigoCliente\",
    //               csn_cardname AS \"NombreCliente\",
    //               (json_array_elements(dsc_rutas::json)->>'geo_id')::int AS \"CodigoRuta\",
    //               (json_array_elements(dsc_rutas::json)->>'geo_nombre')::text AS \"NombreRuta\",
    //               csn1.sn1_itemcode AS \"CodigoServ\",
    //               dmar.dma_item_name AS \"NomServicio\",
    //               csn_cleantotal AS \"CantidadServ\",
    //               dmsn.dms_cel AS \"TelContacto\",
    //               dmsn.dms_email AS \"E_Mail\",
    //               csn_comment AS \"Comentarios\",
    //               csn_adress AS \"DireccionUbicacion\",
    //               csn_comment AS \"DirUB\",
    //               coalesce(bdg_dia,'7') AS \"U_WT_Dia_Ruta\",
    //               '0' AS \"U_WT_BODEGA\",
    //               '0' AS \"dia_ruta\",
    //               '0' AS \"codigo_serial\",
    //               csn_contacid AS \"Contacto\",
    //               coalesce(bdg_dia,'7') AS \"dia_semana\",
    //               coalesce(dbg_id,0) as dbg_id,
    //               csn_docentry,
    //               csn_weeklytoilets as aseos_semanales,
    //               case when string_agg(dmsc.dmc_email,';') is not null then string_agg(dmsc.dmc_email,';') else dms_email end as \"emailContact\"
    //           FROM tcsn 
    //           INNER JOIN responsestatus ON tcsn.csn_docentry = responsestatus.id AND tcsn.csn_doctype = responsestatus.tipo 
    //           INNER JOIN csn1 ON csn_docentry = sn1_docentry
    //           INNER JOIN dmar ON sn1_itemcode = dma_item_code AND dma_clean = 1
    //           INNER JOIN dmsn ON TRIM(tcsn.csn_cardcode) = TRIM(dmsn.dms_card_code) AND dmsn.dms_card_type = '1'
    //           INNER JOIN tdsc ON dsc_docentry = csn_docentry AND csn_doctype = 32 
    //           LEFT JOIN tbdg ON csn_docentry = bdg_docentry AND csn_doctype = 32
    //           left join dmsc on dmsc.dmc_card_code  = dmsn.dms_card_code and dmsc.dmc_uso = 'RECURRENTE' and dmsc.dmc_status = '1'
    //           WHERE responsestatus.estado = 'Abierto'
    //           GROUP BY csn_docnum, csn_cardcode, csn_cardname, sn1_itemcode, dma_item_name,
    //           dms_cel, dms_email, csn_comment, csn_adress, csn_contacid, bdg_dia,(json_array_elements(dsc_rutas::json)->>'geo_id')::int,
    //           (json_array_elements(dsc_rutas::json)->>'geo_nombre')::text,dbg_id,csn_docentry";

    $sqlSelect = " SELECT distinct on (csn_docnum,bdg_dia)
                csn_docnum AS \"NumOrden\",
                csn_cardcode AS \"CodigoCliente\",
                csn_cardname AS \"NombreCliente\",
                rutas.codigoruta AS \"CodigoRuta\",
                rutas.nombreruta as \"NombreRuta\",
                csn1.sn1_itemcode AS \"CodigoServ\",
                dmar.dma_item_name AS \"NomServicio\",
                csn_cleantotal AS \"CantidadServ\",
                dmsn.dms_cel AS \"TelContacto\",
                dmsn.dms_email AS \"E_Mail\",
                csn_comment AS \"Comentarios\",
                csn_adress AS \"DireccionUbicacion\",
                csn_comment AS \"DirUB\",
                coalesce(bdg_dia,'7') AS \"U_WT_Dia_Ruta\",
                '0' AS \"U_WT_BODEGA\",
                '0' AS \"dia_ruta\",
                '0' AS \"codigo_serial\",
                csn_contacid AS \"Contacto\",
                coalesce(bdg_dia,'7') AS \"dia_semana\",
                coalesce(dbg_id,0) as dbg_id,
                csn_docentry,
                csn_weeklytoilets as aseos_semanales,
                case when string_agg(dmsc.dmc_email,';') is not null then string_agg(dmsc.dmc_email,';') else dms_email end as \"emailContact\"
            FROM tcsn 
            INNER JOIN responsestatus ON tcsn.csn_docentry = responsestatus.id AND tcsn.csn_doctype = responsestatus.tipo 
            INNER JOIN csn1 ON csn_docentry = sn1_docentry
            INNER JOIN dmar ON sn1_itemcode = dma_item_code AND dma_clean = 1
            INNER JOIN dmsn ON TRIM(tcsn.csn_cardcode) = TRIM(dmsn.dms_card_code) AND dmsn.dms_card_type = '1'
            INNER JOIN tdsc ON dsc_docentry = csn_docentry AND csn_doctype = 32 
            LEFT JOIN tbdg ON csn_docentry = bdg_docentry AND csn_doctype = 32
            LEFT JOIN dmsc on dmsc.dmc_card_code  = dmsn.dms_card_code and dmsc.dmc_uso = 'RECURRENTE' and dmsc.dmc_status = '1'
            LEFT JOIN LATERAL (
                SELECT 
                    (json_array_elements(dsc_rutas::json)->>'geo_id')::int AS codigoruta,
                    (json_array_elements(dsc_rutas::json)->>'geo_nombre')::text AS nombreruta
            ) AS rutas ON true
            WHERE responsestatus.estado = 'Abierto'
            GROUP BY csn_docnum, csn_cardcode, csn_cardname, sn1_itemcode, dma_item_name,
                dms_cel, dms_email, csn_comment, csn_adress, csn_contacid, bdg_dia, 
                rutas.codigoruta, rutas.nombreruta, dbg_id, csn_docentry";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array());


    if ( isset($resSelect[0]) ) {

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    } else {

        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );
    }

    $this->response($respuesta);
  
  }

  public function setDiaGestion_post(){
    $Data = $this->post();

    if ( isset($Data['bdg_id']) && $Data['bdg_id'] > 0 ) {

      if ($Data['bdg_dia'] == 7){
        $del = $this->pedeo->deleteRow("DELETE FROM tbdg WHERE dbg_id = :dbg_id", array(":dbg_id" => $Data['bdg_id']));

        if (is_numeric($del) && $del == 1){
          $respuesta = array(
            'error'   => false,
            'data'    => array(),
            'mensaje'	=> 'Se elimino la orden de servicio'
          );
  
          return $this->response($respuesta);

        }else{
          $respuesta = array(
            'error'   => true,
            'data'    => array(),
            'mensaje'	=> 'No se pudo liberar la orden de servicio'
          );
  
          return $this->response($respuesta);
        }
      }

      $sql = "SELECT * FROM tbdg WHERE bdg_docentry = :bdg_docentry AND bdg_dia = :bdg_dia AND dbg_id <> :dbg_id";

      $resSql = $this->pedeo->queryTable($sql, array(
        ':bdg_docentry' => $Data['bdg_docentry'],
        ':bdg_dia'      => $Data['bdg_dia'],
        ':dbg_id'       => $Data['bdg_id']
      ));

      if (isset($resSql[0])){
        $respuesta = array(
          'error'   => true,
          'data'    => array(),
          'mensaje'	=> 'La orden de servicio no se puede repetir en el mismo dia'
        );

        return $this->response($respuesta);
      }

      $sqlUpdate = "UPDATE tbdg set bdg_dia = :bdg_dia WHERE dbg_id = :dbg_id";

      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
        ':dbg_id' => $Data['bdg_id'],
        ':bdg_dia' => $Data['bdg_dia']
      ));

      if (is_numeric($resUpdate) && $resUpdate == 1){
        $respuesta = array(
          'error'   => false,
          'data' => array(),
          'mensaje'	=> 'Se estableció el día con exito'
        );

      }else{
        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'No se pudo cambiar el dia de la orden'
        );
      }

    }else{

      $slqInsert = "INSERT INTO tbdg(bdg_dia,bdg_docentry,bdg_doctype)VALUES(:bdg_dia,:bdg_docentry,:bdg_doctype)";

      $resInsert = $this->pedeo->insertRow($slqInsert, array(
        ':bdg_dia'      => $Data['bdg_dia'],
        ':bdg_docentry' => $Data['bdg_docentry'],
        ':bdg_doctype'  => 32
      ));

      if (is_numeric($resInsert) && $resInsert > 0){
        $respuesta = array(
          'error'   => false,
          'data' => array(),
          'mensaje'	=> 'Se estableció el día con exito'
        );

      }else{
        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'No se pudo cambiar el dia de la orden'
        );
      }
    }

    $this->response($respuesta);
  }
  
  public function getStockItem_post() {

    $Data = $this->post();

    $sqlSelect = "SELECT 
              case when lm1_quantity = 0 then round((bdi_quantity/1), get_decimals()) else round(bdi_quantity/lm1_quantity, get_decimals())::numeric end as cantidad    
              from prlm 
              inner join rlm1 on prlm.rlm_id = lm1_iddoc
              inner join tbdi on lm1_itemcode  = bdi_itemcode and tbdi.bdi_whscode = :whscode
              where rlm_bom_type = 3
              and rlm_item_code = :itemcode
              order by cantidad asc limit 1";



    $resSelect = $this->pedeo->queryTable($sqlSelect, array(":whscode" => $Data['whscode'], ":itemcode" => $Data['itemcode']));

    if(isset($resSelect[0])){

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    }else{

        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );

    }

    $this->response($respuesta);

  }

  public function getOrdenes_post(){

    $sqlSelect = "SELECT distinct on (ist_docnum)
      ist_docnum AS \"Documento\",
      ist_cardcode AS \"CodigoCliente\",
      ist_cardname AS \"NombreCliente\",
      st1_itemcode AS \"CodigoArticulo\",
      dma_item_name AS \"DescripcionArticulo\",
      csn_cleantotal AS \"Cantidad\",
      dmsc.dmc_cel AS \"TelContacto\",
      csn_comment AS \"DireccionUbicacion\",
      coalesce(bdg_dia,'7') AS \"U_WT_Dia_Ruta\",
      st1_whscode \"BodegaOrigen\",
      org.dws_name \"NombreBodegaOrigen\",
      st1_whscode_dest as \"BodegaDestino\",
      dest.dws_name as \"NombreBodegaDestino\",
      '0' AS \"dia_ruta\",
      '' as \"NombreOp\",
      csn_cleantotal as \"Quantity\",
      csn_duedev AS \"FechaInicioServicio\",
      csn_duedev AS \"FechaFinServicio\",
      ist_docdate as \"FechaDocumento\",
      ist_activity_type AS \"TipoActividad\",
      string_agg(st1_serials, ',') as \"codigo_serial\",
      ist_user_m as \"Usuario\",
      concat(dmsc.dmc_name, ' ' , coalesce(dmsc.dmc_last_name, ''))  AS \"Contacto\",
      concat('[',string_agg(json_build_object(
      'itemCode', st1_itemcode,
	    'itemName', st1_itemname,
	    'quantity', st1_quantity
	    )::text,','  ),']')  as detail,
      case when string_agg(info.dmc_email,';') is not null then string_agg(info.dmc_email,';') else dms_email end as \"emailContact\"
      FROM dist
      INNER JOIN responsestatus ON dist.ist_docentry = responsestatus.id AND ist_doctype = responsestatus.tipo 
      inner join tcsn on dist.ist_baseentry  = csn_docentry and ist_basetype  = csn_doctype
      INNER JOIN ist1 ON ist_docentry = st1_docentry
      INNER JOIN dmar ON st1_itemcode = dma_item_code AND dma_clean = 1
      INNER JOIN dmsn ON TRIM(ist_cardcode) = TRIM(dmsn.dms_card_code) AND dmsn.dms_card_type = '1'
      LEFT JOIN tbdg ON csn_docentry = bdg_docentry AND csn_doctype = 32
      inner join dmws org on org.dws_code  = st1_whscode
      inner join dmws dest on dest.dws_code  = st1_whscode_dest
      inner join dmsc on dmsc.dmc_id::text = ist_contacid
      left join dmsc as  info on dmsc.dmc_card_code::text = dmsn.dms_card_code  and info.dmc_uso = 'RECURRENTE'
      WHERE responsestatus.estado = 'Abierto'
      GROUP BY ist_docnum, ist_cardcode, ist_cardname, st1_itemcode, dma_item_name,csn_cleantotal,org.dws_code,dest.dws_code,csn_docnum,
      dms_cel, dms_email,dmsc.dmc_name,dmsc.dmc_cel,dmsc.dmc_last_name,dmsc.dmc_uso,  dmsc.dmc_email,csn_comment,ist_comment, ist_adress, ist_contacid, bdg_dia,dbg_id,ist_docentry, st1_whscode,org.dws_name,dest.dws_name,csn_duedev, st1_whscode_dest,st1_serials";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array());
    if ( isset($resSelect[0]) ) {

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    } else {

        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );
    }

    $this->response($respuesta);
  
  }

  public function getCarteraFaltanDias_get() {

    $Data = $this->get();

    $sqlSelect = "SELECT distinct
                  mac1.ac1_legal_num as \"CardCode\",
                  dmsn.dms_card_name as \"CardName\",
                  current_date - dvf_duedate dias,
                  dvfv.dvf_comment as comments,
                  dvfv.dvf_docdate as \"FechaDoc\",
                  dvfv.dvf_duedate as \"FechaVencimiento\",
                  dvf_docnum as \"DocNum\",
                  case
                  when mac1.ac1_font_type = 5 then get_dynamic_conversion('COP',get_localcur(),dvf_docdate,mac1.ac1_debit
                  ,get_localcur())
                  else get_dynamic_conversion('COP',get_localcur(),dvf_docdate,mac1.ac1_credit ,get_localcur())
                  end as \"Total\",
                  get_dynamic_conversion('COP',get_localcur(),dvf_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
                  ,get_localcur()) as \"Saldo\",
                  '' as comentario_asiento
                  from mac1
                  inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
                  inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
                  inner join dvfv on dvfv.dvf_doctype = mac1.ac1_font_type and dvfv.dvf_docentry = mac1.ac1_font_key
                  inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
                  where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ) > 0 and dmsn.dms_card_type = '1'
                  and dvf_docdate <= current_date
                  and current_date - dvf_duedate = :dias";
    
    $resSelect = $this->pedeo->queryTable($sqlSelect, array(":dias" => $Data['dias']));


    if ( isset($resSelect[0]) ) {

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    } else {
        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );
    }

    $this->response($respuesta);
  
  }

  public function getCarteraPasadoDias_get() {

    $Data = $this->get();

    $sqlSelect = "SELECT distinct
                  mac1.ac1_legal_num as \"CardCode\",
                  dmsn.dms_card_name as \"CardName\",
                  current_date - dvf_duedate dias,
                  dvfv.dvf_comment as comments,
                  dvfv.dvf_docdate as \"FechaDoc\",
                  dvfv.dvf_duedate as \"FechaVencimiento\",
                  dvf_docnum as \"DocNum\",
                  case
                  when mac1.ac1_font_type = 5 then get_dynamic_conversion('COP',get_localcur(),dvf_docdate,mac1.ac1_debit
                  ,get_localcur())
                  else get_dynamic_conversion('COP',get_localcur(),dvf_docdate,mac1.ac1_credit ,get_localcur())
                  end as \"Total\",
                  get_dynamic_conversion('COP',get_localcur(),dvf_docdate,(mac1.ac1_ven_debit) - (mac1.ac1_ven_credit)
                  ,get_localcur()) as \"Saldo\",
                  '' as comentario_asiento
                  from mac1
                  inner join dacc on mac1.ac1_account = dacc.acc_code and acc_businessp = '1'
                  inner join dmdt on mac1.ac1_font_type = dmdt.mdt_doctype
                  inner join dvfv on dvfv.dvf_doctype = mac1.ac1_font_type and dvfv.dvf_docentry = mac1.ac1_font_key
                  inner join dmsn on mac1.ac1_legal_num = dmsn.dms_card_code
                  where ABS((mac1.ac1_ven_debit) - (mac1.ac1_ven_credit) ) > 0 and dmsn.dms_card_type = '1'
                  and dvf_docdate <= current_date
                  and current_date - dvf_duedate > :dias";
    
    $resSelect = $this->pedeo->queryTable($sqlSelect, array(":dias" => $Data['dias']));


    if ( isset($resSelect[0]) ) {

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    } else {
        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );
    }

    $this->response($respuesta);
  
  }

  public function getCarteraClientes_get() {


    $sqlSelect = "SELECT dms_card_code as \"CardCode\",
                  dms_card_name as \"CardName\",
                  dms_email as \"E_Mail\"
                  from dmsn 
                  where dmsn.dms_card_type = '1'
                  and dms_enabled = 1";
    
    $resSelect = $this->pedeo->queryTable($sqlSelect, array());


    if ( isset($resSelect[0]) ) {

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    } else {
        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );
    }

    $this->response($respuesta);
  
  }

  
  public function getCompanyData_get() {

    $sqlSelect = "SELECT pge_logo,
                  t2.url_terminos_condiciones as pge_url_terminos_condiciones ,
                  t2.url_preguntas_frecuentes as pge_url_preguntas_frecuentes, 
                  t2.url_contacto as pge_url_contacto 
                  FROM pgem p
                  left JOIN params t2 on t2.business = p.pge_id where pge_branch = true";
    
    $resSelect = $this->pedeo->queryTable($sqlSelect, array());


    if ( isset($resSelect[0]) ) {

      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');

    } else {
        $respuesta = array(
          'error'   => true,
          'data' => array(),
          'mensaje'	=> 'busqueda sin resultados'
        );
    }

    $this->response($respuesta);
  
  }

  public function getFormRules_post(){
    
    $Data = $this->post();

    if(!isset($Data['table'])){
      $respuesta = array(
        'error' => true,
        'data' => array(),
        'mensaje' => 'Informacion enviada no valida',
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = "SELECT 
                  concat('[',string_agg(json_build_object(
                      'doc'||substring(column_name from 4) , json_build_object('maxlength', character_maximum_length)      
                      )::text,','  ),']') as rules
                FROM 
                    information_schema.columns 
                WHERE 
                table_name = :table and character_maximum_length  > 0";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(":table" => $Data['table']));

    if ( isset($resSelect[0]) ) {
      $respuesta = array(
        'error' => false,
        'data'  => json_decode($resSelect[0]['rules'],true),
        'mensaje' => '');

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
