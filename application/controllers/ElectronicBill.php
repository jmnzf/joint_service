<?php
// DATOS MAESTROS PROYECTO
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class ElectronicBill extends REST_Controller {

	private $pdo;
    private $pdo_fe;
	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo_fe = $this->load->database('fe', true)->conn_id;
        $this->load->library('fe', [$this->pdo_fe]);

        //
        $this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);

	}

  //ENVIAR DATOS DE FACTURA A TABLA FE
	public function sendFE_post(){

        $Data = $this->post();

        //VARIABLE PARA CONVERTIR A LESTRAS ALGUN VALOR
        $formatter = new NumeroALetras();

        //SQL PARA DATOS DE EMPRESA
        $sqlBusiness = "SELECT * FROM pgem";
        //RETORNO DE DATOS DE EMPRESA
        $resBusiness = $this->pedeo->queryTable($sqlBusiness,array());

        //SQL VISTA DE FACTURAS
        $sql = "SELECT * FROM invoices WHERE numero_fac = 357";

        //RETORNO DE DATOS DE FACTURAS
        $resSql = $this->pedeo->queryTable($sql, array());

        //VALIDAR SI HAY DATOS DE FACTURA
        if(isset($resSql[0])){

            //INICIAR TRANSACCION
            $this->fe->trans_begin();

            //RECORRER RETORNO DE FACTURAS
            // foreach ($resSql as $key => $resSql[0]) {
                # code...

                // print_r($resSql[0]);exit;

                //INSERTAR DATOS A TABLA DE TERCEROS BD FE
                $insertTercero = "INSERT INTO \"Tercero\"(\"CodigoTercero\",\"CodigoTipoPersona\",\"CodigoTipoRegimen\",\"CodigoTipoIdentificacion\",
                \"NumeroIdentificacion\",\"DV\",\"NombreTercero\",\"Email\",\"EmailRespuesta\",\"CodigoActividadEconomica\",\"GranContribuyente\",
                \"EsAutoretenedor\",\"EsAgenteRetenedorIVA\",\"EsAgenteRetenedorReteFuente\",\"EsAgenteRetenedorICA\",\"Usuario\",\"Estado\")
                VALUES (:CodigoTercero,:CodigoTipoPersona,:CodigoTipoRegimen,:CodigoTipoIdentificacion,:NumeroIdentificacion,:DV,:NombreTercero,
                :Email,:EmailRespuesta,:CodigoActividadEconomica,:GranContribuyente,:EsAutoretenedor,:EsAgenteRetenedorIVA,:EsAgenteRetenedorReteFuente,
                :EsAgenteRetenedorICA,:Usuario,:Estado)";

                $resInsertTercero = $this->fe->insertRow($insertTercero,array(
                    ':CodigoTercero' => $resSql[0]['codigo_sn1'],
                    ':CodigoTipoPersona' => isset($resSql[0]['codigo_sn1']) ? $resSql[0]['codigo_sn1'] : NULL ,
                    ':CodigoTipoRegimen' => $resSql[0]['codigo_regimen'],
                    ':CodigoTipoIdentificacion' => $resSql[0]['tipo_doc'],
                    ':NumeroIdentificacion' => $resSql[0]['codigo_sn1'],
                    ':DV' => isset($resSql[0]['codigo_sn1']) ? 1 : 0,
                    ':NombreTercero' => $resSql[0]['nombre_sn'],
                    ':Email' => $resSql[0]['correo'],
                    ':EmailRespuesta' => $resSql[0]['correo'],
                    ':CodigoActividadEconomica' => isset($resSql[0]['codigo_regimen']) ? $resSql[0]['codigo_regimen'] : NULL,
                    ':GranContribuyente' =>  $resSql[0]['codigo_regimen'] == 'GC' ? 1 : 0 ,
                    ':EsAutoretenedor' =>  $resSql[0]['codigo_regimen'] == 'AR'  ? 1 : 0 ,
                    ':EsAgenteRetenedorIVA' =>  $resSql[0]['codigo_regimen'] == 'RS'  ? 1 : 0 ,
                    ':EsAgenteRetenedorReteFuente' =>  $resSql[0]['codigo_regimen'] == 'RS' ? 1 : 0 ,
                    ':EsAgenteRetenedorICA' =>  $resSql[0]['codigo_regimen'] == 'RS' ? 1 : 0 ,
                    ':Usuario' => 1,
                    ':Estado' => 1
                ));

                

                if (is_numeric($resInsertTercero) && $resInsertTercero > 0){

                    //INSERTAR DATOS DE DIRECCION BD FE
                    $insertDireccion = "INSERT INTO \"DireccionCliente\" (\"CodigoTercero\",\"EsPrincipal\",\"EsEntrega\",\"CodigoPais\",\"CodigoDepto\",
                    \"CodigoCiudad\",\"CodigoBarrio\",\"CodigoZona\",\"Direccion\",\"Telefono\",\"Telefono2\",\"Usuario\",\"Estado\")
                    VALUES (:CodigoTercero,:EsPrincipal,:EsEntrega,:CodigoPais,:CodigoDepto,:CodigoCiudad,:CodigoBarrio,:CodigoZona,:Direccion,:Telefono,
                    :Telefono2,:Usuario,:Estado)";

                    $resInsertDireccion = $this->fe->insertRow($insertDireccion,array(
                        ':CodigoTercero' => isset($resSql[0]['codigo_sn1']) ? $resSql[0]['codigo_sn1'] : NULL,
                        ':EsPrincipal' => isset($resSql[0]['principal']) ?  1 : 0,
                        ':EsEntrega' => isset($resSql[0]['principal']) ? 1 : 0,
                        ':CodigoPais' => isset($resSql[0]['pais']) ? $resSql[0]['pais'] : NULL,
                        ':CodigoDepto' => isset($resSql[0]['dpto']) ? $resSql[0]['dpto'] : NULL,
                        ':CodigoCiudad' => isset($resSql[0]['ciudad']) ? $resSql[0]['ciudad']: NULL,
                        ':CodigoBarrio' => isset($resSql[0]['ciudad']) ? $resSql[0]['ciudad']: NULL,
                        ':CodigoZona' => isset($resSql[0]['ciudad']) ? $resSql[0]['ciudad']: NULL,
                        ':Direccion' => isset($resSql[0]['direccion']) ? $resSql[0]['direccion']:NULL ,
                        ':Telefono' => isset($resSql[0]['telefono']) ? $resSql[0]['telefono']:NULL ,
                        ':Telefono2' => isset($resSql[0]['telefono2']) ? $resSql[0]['telefono']: NULL,
                        ':Usuario' => 1,
                        ':Estado' => 1

                    ));

                    if(is_numeric($resInsertDireccion) && $resInsertDireccion > 0){

                        //PASAR VALOR A LETRAS
                        $valorLetra = $formatter->toWords($resSql[0]['total'],2);
                        //INSERTAR DATOS DE CABECERA DE FACTURA A BD FE
                        $insertFactura = "INSERT INTO \"Factura\" (\"CodigoEmpresa\",\"Sucursal\",\"CodigoResolucion\",\"Tipo\",\"Numero\",\"CodigoConcepto\",
                        \"FechaFactura\",\"CodigoTercero\",\"CodigoDireccionTercero\",\"DireccionEntrega\",\"CodigoFormaPago\",\"CodigoVendedor\",\"NumeroCuota\",
                        \"Descripcion\",\"FechaEntrega\",\"ValorSubTotal\",\"ValorImpuestos\",\"ValorRetencion\",\"ValorDescuento\",\"ValorTotal\",\"ValorInicial\",
                        \"ValorLetras\",\"CodigoMoneda\",\"TasaCambio\",\"TipoFactura\",\"NumeroFactura\",\"TipoSoporte\",\"NumeroSoporte\",\"TipoRespaldo\",
                        \"NumeroRespaldo\",\"Usuario\",\"Estado\")
                        VALUES (:CodigoEmpresa,:Sucursal,:CodigoResolucion,:Tipo,:Numero,:CodigoConcepto,:FechaFactura,:CodigoTercero,:CodigoDireccionTercero,
                        :DireccionEntrega,:CodigoFormaPago,:CodigoVendedor,:NumeroCuota,:Descripcion,:FechaEntrega,:ValorSubTotal,:ValorImpuestos,:ValorRetencion,
                        :ValorDescuento,:ValorTotal,:ValorInicial,:ValorLetras,:CodigoMoneda,:TasaCambio,:TipoFactura,:NumeroFactura,:TipoSoporte,:NumeroSoporte,
                        :TipoRespaldo,:NumeroRespaldo,:Usuario,:Estado)";

                        $resInsertFactura = $this->fe->insertRow($insertFactura,array(
                            ':CodigoEmpresa' => $resBusiness[0]['pge_name_soc'],
                            ':Sucursal' => '000',
                            ':CodigoResolucion' => '000',
                            ':Tipo' => isset($resSql[0]['prefijo']) ? $resSql[0]['prefijo'] : NULL,
                            ':Numero' => isset($resSql[0]['numero_fac']) ? $resSql[0]['numero_fac'] : NULL,
                            ':CodigoConcepto' => isset($resSql[0]['prefijo']) ? $resSql[0]['prefijo'] : NULL,
                            ':FechaFactura' => isset($resSql[0]['fecha_contab']) ? $resSql[0]['fecha_contab'] : NULL,
                            ':CodigoTercero' => isset($resSql[0]['codigo_sn1']) ? $resSql[0]['codigo_sn1'] : NULL,
                            ':CodigoDireccionTercero' => isset($resSql[0]['codigo_direccion']) ? $resSql[0]['codigo_direccion'] : NULL,
                            ':DireccionEntrega' => isset($resSql[0]['direccion']) ? $resSql[0]['direccion'] : NULL,
                            ':CodigoFormaPago' => is_numeric($resSql[0]['tipo_pago']) ? $resSql[0]['tipo_pago'] : 0,
                            ':CodigoVendedor' => is_numeric($resSql[0]['id_vendedor']) ? $resSql[0]['id_vendedor'] : 0,
                            ':NumeroCuota' => '0',
                            ':Descripcion' => isset($resSql[0]['comentarios']) ? $resSql[0]['comentarios'] : NULL,
                            ':FechaEntrega' => isset($resSql[0]['fecha_contab']) ? $resSql[0]['fecha_contab'] : NULL,
                            ':ValorSubTotal' => is_numeric($resSql[0]['base']) ? $resSql[0]['base'] : 0,
                            ':ValorImpuestos' => is_numeric($resSql[0]['total_iva']) ? $resSql[0]['total_iva'] : 0,
                            ':ValorRetencion' => is_numeric($resSql[0]['retencion']) ? $resSql[0]['retencion'] : 0,
                            ':ValorDescuento' => is_numeric($resSql[0]['descuento']) ? $resSql[0]['descuento'] : 0,
                            ':ValorTotal' => is_numeric($resSql[0]['total']) ? $resSql[0]['total'] : 0,
                            ':ValorInicial' => 0,
                            ':ValorLetras' => $valorLetra,
                            ':CodigoMoneda' => isset($resSql[0]['moneda']) ? $resSql[0]['moneda'] : NULL,
                            ':TasaCambio' => 0,
                            ':TipoFactura' => isset($resSql[0]['prefijo']) ? $resSql[0]['prefijo'] : NULL,
                            ':NumeroFactura' => isset($resSql[0]['numero_fac']) ? $resSql[0]['numero_fac'] : NULL,
                            ':TipoSoporte' => '',
                            ':NumeroSoporte' => '',
                            ':TipoRespaldo' => '',
                            ':NumeroRespaldo' => 0,
                            ':Usuario' => 1,
                            ':Estado' => 1
                        ));

                        if (is_numeric($resInsertFactura) && $resInsertFactura > 0){

                            foreach ($resSql as $key => $value) {
                                // print_r($value['codigo_item']);exit;
                                # code...
                                //INSERTAR DATOS DE DETALLE DE FACTURA A BD FE
                                $insertDetalleFactura = "INSERT INTO \"DetalleFactura\" (\"CodigoEmpresa\",\"Sucursal\",\"Tipo\",\"Numero\",\"Conteo\",
                                \"TipoSoporte\",\"NumeroSoporte\",\"ConteoSoporte\",\"CodigoFactura\",\"CodigoProducto\",\"DescripcionProducto\",
                                \"CodigoUnidad\",\"ValorUnitario\",\"Cantidad\",\"ValorSubTotal\",\"PorcentajeDescuento\",\"ValorDescuento\",
                                \"PorcentajeIva\",\"ValorIva\",\"PorcentajeRetencion\",\"ValorRetencion\",\"ValorTotal\",\"ValorFinanciado\",
                                \"Usuario\",\"Estado\")
                                VALUES (:CodigoEmpresa,:Sucursal,:Tipo,:Numero,:Conteo,:TipoSoporte,:NumeroSoporte,:ConteoSoporte,:CodigoFactura,
                                :CodigoProducto,:DescripcionProducto,:CodigoUnidad,:ValorUnitario,:Cantidad,:ValorSubTotal,:PorcentajeDescuento,
                                :ValorDescuento,:PorcentajeIva,:ValorIva,:PorcentajeRetencion,:ValorRetencion,:ValorTotal,:ValorFinanciado,:Usuario,:Estado)";

                                $resInsertDetalleFactura = $this->fe->insertRow($insertDetalleFactura,array(

                                    ':CodigoEmpresa' => $resBusiness[0]['pge_name_soc'],
                                    ':Sucursal' => '000',
                                    ':Tipo' => isset($value['prefijo']) ? $value['prefijo'] : NULL,
                                    ':Numero' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                                    ':Conteo' => $key += 1,
                                    ':TipoSoporte' => 0,
                                    ':NumeroSoporte' => 0,
                                    ':ConteoSoporte' => 0,
                                    ':CodigoFactura' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                                    ':CodigoProducto' => isset($value['codigo_item']) ? $value['codigo_item'] : NULL,
                                    ':DescripcionProducto' => isset($value['codigo_item']) ? $value['codigo_item'] : NULL,
                                    ':CodigoUnidad' => '1',
                                    ':ValorUnitario' => is_numeric($value['precio_und']) ? $value['precio_und'] : 0,
                                    ':Cantidad' => is_numeric($value['cantidad']) ? $value['cantidad'] : 0,
                                    ':ValorSubTotal' => is_numeric($value['precio_und']) ? $value['precio_und'] * $value['cantidad']: 0,
                                    ':PorcentajeDescuento' => is_numeric($value['descuento_unid']) ? $value['descuento_unid'] : 0,
                                    ':ValorDescuento' => is_numeric($value['descuento_unid']) ? $value['descuento_unid'] : 0,
                                    ':PorcentajeIva' => is_numeric($value['porcentaje_iva']) ? $value['porcentaje_iva'] : 0,
                                    ':ValorIva' => is_numeric($value['valor_iva']) ? $value['valor_iva'] : 0,
                                    ':PorcentajeRetencion' => is_numeric($value['retencion']) ? $value['retencion'] : 0,
                                    ':ValorRetencion' => is_numeric($value['retencion']) ? $value['retencion'] : 0,
                                    ':ValorTotal' => is_numeric($value['total_linea']) ? $value['total_linea'] : 0,
                                    ':ValorFinanciado' =>  0,
                                    ':Usuario' => 1,
                                    ':Estado' => 1
                                ));

                                if (is_numeric($resInsertDetalleFactura) && $resInsertDetalleFactura > 0){

                                }else{

                                    $this->fe->trans_rollback();

                                    $respuesta = array(
                                        'error'   => true,
                                        'data'    => $resInsertDetalleFactura,
                                        'mensaje'	=> 'No se pudo insertar el detalle de la factura'
                                    );

                                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                                    return;
                                }
                            }
                            

                        }else{

                            $this->fe->trans_rollback();

                            $respuesta = array(
                                'error'   => true,
                                'data'    => $resInsertFactura,
                                'mensaje'	=> 'No se pudo insertar la factura'
                            );

                            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                            return;

                        }


                    }else{

                        $this->fe->trans_rollback();

                        $respuesta = array(
                        'error'   => true,
                        'data'    => $resInsertDireccion,
                        'mensaje'	=> 'No se pudo insertar la direccion'
                        );

                        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                        return;
                    }


                }else {

                    $this->fe->trans_rollback();

				    $respuesta = array(
					'error'   => true,
					'data'    => $resInsertTercero,
					'mensaje'	=> 'No se pudo insertar el tercero'
				    );

				    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

				    return;
                }
            // }

            $respuesta = array(
                'error'   => true,
                'data'    => $resInsertTercero,
                'mensaje'	=> 'Factura ingresada exitosamente'
                );

            $this->fe->trans_commit();
        }
         


         $this->response($respuesta);
        
	}
}
