<?php
// DATOS MAESTROS DE IMPUESTOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class TaxMasterData extends REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);

	}

  //CREAR NUEVO IMPUESTO
	public function createTaxMasterData_post(){

      $Data = $this->post();

      if(!isset($Data['dmi_code']) OR
         !isset($Data['dmi_name_tax']) OR
         !isset($Data['dmi_rate_tax']) OR
         !isset($Data['dmi_type']) OR
         !isset($Data['dmi_acctcode']) OR
         !isset($Data['dmi_multiple']) OR
         !isset($Data['dmi_enable'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

        $sqlInsert = "INSERT INTO dmtx(dmi_code, dmi_name_tax, dmi_rate_tax, dmi_type, dmi_enable, dmi_acctcode, dmi_use_fc, dmi_rate_fc, dmi_multiple)
                      VALUES(:dmi_code, :dmi_name_tax, :dmi_rate_tax, :dmi_type, :dmi_enable, :dmi_acctcode, :dmi_use_fc, :dmi_rate_fc, :dmi_multiple)";


        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
              ':dmi_code' => $Data['dmi_code'],
              ':dmi_name_tax' => $Data['dmi_name_tax'],
              ':dmi_rate_tax' => $Data['dmi_rate_tax'],
              ':dmi_type' => $Data['dmi_type'],
              ':dmi_enable' => $Data['dmi_enable'],
              ':dmi_use_fc' => $Data['dmi_use_fc'],
              ':dmi_rate_fc' => is_numeric($Data['dmi_rate_fc']) ? $Data['dmi_rate_fc'] : 0,
              ':dmi_acctcode' => $Data['dmi_acctcode'],
              ':dmi_multiple' => $Data['dmi_multiple']

        ));

        if(is_numeric($resInsert) && $resInsert > 0){

              $respuesta = array(
                'error' 	=> false,
                'data' 		=> $resInsert,
                'mensaje' =>'Impuesto registrado con exito'
              );


        }else{

              $respuesta = array(
                'error'   => true,
                'data' 		=> $resInsert,
                'mensaje'	=> 'No se pudo registrar el impuesto'
              );

        }

         $this->response($respuesta);
	}

  //ACTUALIZAR IMPUESTO
  public function updateTaxMasterData_post(){

      $Data = $this->post();

      if(!isset($Data['dmi_code']) OR
         !isset($Data['dmi_name_tax']) OR
         !isset($Data['dmi_rate_tax']) OR
         !isset($Data['dmi_type']) OR
         !isset($Data['dmi_enable']) OR
         !isset($Data['dmi_acctcode']) OR
         !isset($Data['dmi_multiple']) OR
         !isset($Data['dmi_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      $sqlUpdate = "UPDATE dmtx SET  dmi_acctcode = :dmi_acctcode, dmi_code = :dmi_code, dmi_name_tax = :dmi_name_tax, dmi_rate_tax = :dmi_rate_tax, dmi_type = :dmi_type,
            dmi_enable = :dmi_enable, dmi_rate_fc = :dmi_rate_fc, dmi_use_fc = :dmi_use_fc, dmi_multiple = :dmi_multiple WHERE dmi_id = :dmi_id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
            ':dmi_code' => $Data['dmi_code'],
            ':dmi_name_tax' => $Data['dmi_name_tax'],
            ':dmi_rate_tax' => $Data['dmi_rate_tax'],
            ':dmi_type' => $Data['dmi_type'],
            ':dmi_enable' => $Data['dmi_enable'],
            ':dmi_acctcode' => $Data['dmi_acctcode'],
            ':dmi_use_fc' => $Data['dmi_use_fc'],
            ':dmi_rate_fc' => is_numeric($Data['dmi_rate_fc']) ? $Data['dmi_rate_fc'] : 0,
            ':dmi_multiple' => $Data['dmi_multiple'],
            ':dmi_id' => $Data['dmi_id']
      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Impuesto actualizado con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar el impuesto'
            );

      }

       $this->response($respuesta);
  }


  // OBTENER IMPUESTOS
  public function getTaxMasterData_get(){

        $sqlSelect = " SELECT * FROM dmtx";

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


  // OBTENER INGRESOS TOTALES
  public function getIgresos_get(){

    $Data = $this->get();


    $sqlSelect = "SELECT 
              dvfv.dvf_docnum as \"Documento\",
              dvfv.dvf_cardcode as \"Identificacion\",
              dvfv.dvf_cardname as \"Nombre\",
              coalesce(sum( case when data.impuesto > 0 then base end  ), 0) as \"Ingresograbado\",
              coalesce(sum( case when data.impuesto = 0 then base end  ), 0) as \"Ingresonograbado\",
              sum(data.impuesto) as \"Impuesto\",
              coalesce(pdm_municipality, 'Sin direccion establecida') as \"Ciudad\"
              from (
              select 
              fv1_docentry,
              case 
                when fv1_discount = 0 then ( fv1_price * fv1_quantity )
                when fv1_discount > 0 and fv1_discount <= 100 then ((fv1_price - ( fv1_price * fv1_discount / 100 )) * fv1_quantity)
                else  fv1_price * fv1_quantity
              end as base,
              case 
                when fv1_discount = 0 and fv1_vat > 0 then ( fv1_price * fv1_quantity ) * fv1_vat / 100
                when fv1_discount > 0 and fv1_discount <= 100 and fv1_vat > 0 then ((fv1_price - ( fv1_price * fv1_discount / 100 )) * fv1_quantity) * fv1_vat / 100
                when fv1_discount > 100 and fv1_vat > 0 then (fv1_price * fv1_quantity) * fv1_vat / 100
                else 0 
              end as impuesto 
              from vfv1) as data 
              inner join dvfv on data.fv1_docentry = dvfv.dvf_docentry 
              left join dmsd on  dvf_cardcode = dmd_card_code and dmd_ppal = 1
              left join tpdm on dmsd.dmd_city  = pdm_codmunicipality
              where dvf_docdate between :fi and :ff
              group by data.fv1_docentry, dvf_docnum, dvf_cardcode, dvf_cardname,pdm_municipality";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(

      ':fi' => isset($Data['fi']) && !empty($Data['fi']) ? $Data['fi'] : null,
      ':ff' => isset($Data['ff']) && !empty($Data['ff']) ? $Data['ff'] : null
 
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


  // OBTENER DEVOLUCIONES TOTALES
  public function getDevoluciones_get(){

    $Data = $this->get();


    $sqlSelect = "SELECT 
              dvnc.vnc_docnum as \"Documento\",
              dvnc.vnc_cardcode as \"Identificacion\",
              dvnc.vnc_cardname as \"Nombre\",
              coalesce(sum( case when data.impuesto > 0 then base end  ), 0) as \"Devolucioningresograbado\",
              coalesce(sum( case when data.impuesto = 0 then base end  ), 0) as \"Devolucioningresonograbado\",
              sum(data.impuesto) as \"Devolucionimpuesto\",
              coalesce(pdm_municipality, 'Sin direccion establecida') as \"Ciudad\"
              from (
              select 
              nc1_docentry,
              case 
                when nc1_discount = 0 then ( nc1_price * nc1_quantity )
                when nc1_discount > 0 and nc1_discount <= 100 then ((nc1_price - ( nc1_price * nc1_discount / 100 )) * nc1_quantity)
                else  nc1_price * nc1_quantity
              end as base,
              case 
                when nc1_discount = 0 and nc1_vat > 0 then ( nc1_price * nc1_quantity ) * nc1_vat / 100
                when nc1_discount > 0 and nc1_discount <= 100 and nc1_vat > 0 then ((nc1_price - ( nc1_price * nc1_discount / 100 )) * nc1_quantity) * nc1_vat / 100
                when nc1_discount > 100 and nc1_vat > 0 then (nc1_price * nc1_quantity) * nc1_vat / 100
                else 0 
              end as impuesto 
              from vnc1) as data 
              inner join dvnc on data.nc1_docentry = dvnc.vnc_docentry 
              left join dmsd on  vnc_cardcode = dmd_card_code and dmd_ppal = 1
              left join tpdm on dmsd.dmd_city  = pdm_codmunicipality
              where vnc_docdate between :fi and :ff
              group by data.nc1_docentry, vnc_docnum, vnc_cardcode, vnc_cardname,pdm_municipality";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(

      ':fi' => isset($Data['fi']) && !empty($Data['fi']) ? $Data['fi'] : null,
      ':ff' => isset($Data['ff']) && !empty($Data['ff']) ? $Data['ff'] : null
  
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

  public function getDevolucionesCompras_get(){

    $Data = $this->get();
  
  
    $sqlSelect = "SELECT 
              dcnc.cnc_docnum as \"Documento\",
              dcnc.cnc_cardcode as \"Identificacion\",
              dcnc.cnc_cardname as \"Nombre\",
              coalesce(sum( case when data.impuesto > 0 then base end  ), 0) as \"Gastograbado\",
              coalesce(sum( case when data.impuesto = 0 then base end  ), 0) as \"Gastonograbado\",
              sum(data.impuesto) as \"Gastoimpuesto\",
              coalesce(pdm_municipality, 'Sin direccion establecida') as \"Ciudad\"
              from (
              select 
              nc1_docentry,
              case 
                when nc1_discount = 0 then ( nc1_price * nc1_quantity )
                when nc1_discount > 0 and nc1_discount <= 100 then ((nc1_price - ( nc1_price * nc1_discount / 100 )) * nc1_quantity)
                else  nc1_price * nc1_quantity
              end as base,
              case 
                when nc1_discount = 0 and nc1_vat > 0 then ( nc1_price * nc1_quantity ) * nc1_vat / 100
                when nc1_discount > 0 and nc1_discount <= 100 and nc1_vat > 0 then ((nc1_price - ( nc1_price * nc1_discount / 100 )) * nc1_quantity) * nc1_vat / 100
                when nc1_discount > 100 and nc1_vat > 0 then (nc1_price * nc1_quantity) * nc1_vat / 100
                else 0 
              end as impuesto 
              from cnc1) as data 
              inner join dcnc on data.nc1_docentry = dcnc.cnc_docentry 
              left join dmsd on  cnc_cardcode = dmd_card_code and dmd_ppal = 1
              left join tpdm on dmsd.dmd_city  = pdm_codmunicipality
              where cnc_docdate between :fi and :ff
              group by data.nc1_docentry, cnc_docnum, cnc_cardcode, cnc_cardname,pdm_municipality";
  
    $resSelect = $this->pedeo->queryTable($sqlSelect, array(
  
      ':fi' => isset($Data['fi']) && !empty($Data['fi']) ? $Data['fi'] : null,
      ':ff' => isset($Data['ff']) && !empty($Data['ff']) ? $Data['ff'] : null
  
    ));
  
    if(isset($resSelect[0])){
  
      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');
  
    }else{
  
        $respuesta = array(
          'error'   => true,
          'data' => $resSelect,
          'mensaje'	=> 'busqueda sin resultados'
        );
  
    }
  
    $this->response($respuesta);
  }


  public function getGastos_get(){

    $Data = $this->get();
  
  
    $sqlSelect = "SELECT 
              dcfc.cfc_docnum as \"Documento\",
              dcfc.cfc_cardcode as \"Identificacion\",
              dcfc.cfc_cardname as \"Nombre\",
              coalesce(sum( case when data.impuesto > 0 then base end  ), 0) as \"Ingresograbado\",
              coalesce(sum( case when data.impuesto = 0 then base end  ), 0) as \"Ingresonograbado\",
              sum(data.impuesto) as \"Impuesto\",
              coalesce(pdm_municipality, 'Sin direccion establecida') as \"Ciudad\"
              from (
              select 
              fc1_docentry,
              case 
                when fc1_discount = 0 then ( fc1_price * fc1_quantity )
                when fc1_discount > 0 and fc1_discount <= 100 then ((fc1_price - ( fc1_price * fc1_discount / 100 )) * fc1_quantity)
                else  fc1_price * fc1_quantity
              end as base,
              case 
                when fc1_discount = 0 and fc1_vat > 0 then ( fc1_price * fc1_quantity ) * fc1_vat / 100
                when fc1_discount > 0 and fc1_discount <= 100 and fc1_vat > 0 then ((fc1_price - ( fc1_price * fc1_discount / 100 )) * fc1_quantity) * fc1_vat / 100
                when fc1_discount > 100 and fc1_vat > 0 then (fc1_price * fc1_quantity) * fc1_vat / 100
                else 0 
              end as impuesto 
              from cfc1) as data 
              inner join dcfc on data.fc1_docentry = dcfc.cfc_docentry 
              left join dmsd on  cfc_cardcode = dmd_card_code and dmd_ppal = 1
              left join tpdm on dmsd.dmd_city  = pdm_codmunicipality
              where cfc_docdate between :fi and :ff
              group by data.fc1_docentry, cfc_docnum, cfc_cardcode, cfc_cardname,pdm_municipality";
  
    $resSelect = $this->pedeo->queryTable($sqlSelect, array(
  
      ':fi' => isset($Data['fi']) && !empty($Data['fi']) ? $Data['fi'] : null,
      ':ff' => isset($Data['ff']) && !empty($Data['ff']) ? $Data['ff'] : null
  
    ));
  
    if(isset($resSelect[0])){
  
      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' => '');
  
    }else{
  
        $respuesta = array(
          'error'   => true,
          'data' => $resSelect,
          'mensaje'	=> 'busqueda sin resultados'
        );
  
    }
  
    $this->response($respuesta);
  }



}
