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
        $sqlTercero = "SELECT * FROM terceros";

        //RETORNO DE DATOS DE FACTURAS
        $resSqlTercero = $this->pedeo->queryTable($sqlTercero, array());

        //VALIDAR SI HAY DATOS DE FACTURA
        if(isset($resSqlTercero[0])){

            //INICIAR TRANSACCION
            $this->fe->trans_begin();

            //RECORRER RETORNO DE FACTURAS
            // foreach ($resSql as $key => $sql) {
                # code...

                print_r($resSqlTercero[0]);exit;

                //INSERTAR DATOS A TABLA DE TERCEROS BD FE
                $insertTercero = "INSERT INTO \"Tercero\"(\"CodigoTercero\",\"CodigoTipoPersona\",\"CodigoTipoRegimen\",\"CodigoTipoIdentificacion\",
                \"NumeroIdentificacion\",\"DV\",\"NombreTercero\",\"Email\",\"EmailRespuesta\",\"CodigoActividadEconomica\",\"GranContribuyente\",
                \"EsAutoretenedor\",\"EsAgenteRetenedorIVA\",\"EsAgenteRetenedorReteFuente\",\"EsAgenteRetenedorICA\",\"Usuario\",\"Estado\")
                VALUES (:CodigoTercero,:CodigoTipoPersona,:CodigoTipoRegimen,:CodigoTipoIdentificacion,:NumeroIdentificacion,:DV,:NombreTercero,
                :Email,:EmailRespuesta,:CodigoActividadEconomica,:GranContribuyente,:EsAutoretenedor,:EsAgenteRetenedorIVA,:EsAgenteRetenedorReteFuente,
                :EsAgenteRetenedorICA,:Usuario,:Estado)";

                $resInsertTercero = $this->fe->insertRow($insertTercero,array(
                    ':CodigoTercero' => $resSqlTercero[0]['codigo_sn1'],
                    ':CodigoTipoPersona' => isset($resSqlTercero[0]['codigo_sn1']) ? $resSqlTercero[0]['codigo_sn1'] : NULL ,
                    ':CodigoTipoRegimen' => $resSqlTercero[0]['codigo_regimen'],
                    ':CodigoTipoIdentificacion' => $resSqlTercero[0]['tipo_doc'],
                    ':NumeroIdentificacion' => $resSqlTercero[0]['codigo_sn1'],
                    ':DV' => isset($resSqlTercero[0]['codigo_sn1']) ? 1 : 0,
                    ':NombreTercero' => $resSqlTercero[0]['nombre_sn'],
                    ':Email' => $resSqlTercero[0]['correo'],
                    ':EmailRespuesta' => $resSqlTercero[0]['correo'],
                    ':CodigoActividadEconomica' => isset($resSqlTercero[0]['codigo_regimen']) ? $resSqlTercero[0]['codigo_regimen'] : NULL,
                    ':GranContribuyente' =>  $resSqlTercero[0]['codigo_regimen'] == 'GC' ? 1 : 0 ,
                    ':EsAutoretenedor' =>  $resSqlTercero[0]['codigo_regimen'] == 'AR'  ? 1 : 0 ,
                    ':EsAgenteRetenedorIVA' =>  $resSqlTercero[0]['codigo_regimen'] == 'RS'  ? 1 : 0 ,
                    ':EsAgenteRetenedorReteFuente' =>  $resSqlTercero[0]['codigo_regimen'] == 'RS' ? 1 : 0 ,
                    ':EsAgenteRetenedorICA' =>  $resSqlTercero[0]['codigo_regimen'] == 'RS' ? 1 : 0 ,
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
                        ':CodigoTercero' => isset($resSqlTercero[0]['codigo_sn1']) ? $resSqlTercero[0]['codigo_sn1'] : NULL,
                        ':EsPrincipal' => isset($resSqlTercero[0]['principal']) ?  1 : 0,
                        ':EsEntrega' => isset($resSqlTercero[0]['principal']) ? 1 : 0,
                        ':CodigoPais' => isset($resSqlTercero[0]['pais']) ? $resSqlTercero[0]['pais'] : NULL,
                        ':CodigoDepto' => isset($resSqlTercero[0]['dpto']) ? $resSqlTercero[0]['dpto'] : NULL,
                        ':CodigoCiudad' => isset($resSqlTercero[0]['ciudad']) ? $resSqlTercero[0]['ciudad']: NULL,
                        ':CodigoBarrio' => isset($resSqlTercero[0]['ciudad']) ? $resSqlTercero[0]['ciudad']: NULL,
                        ':CodigoZona' => isset($resSqlTercero[0]['ciudad']) ? $resSqlTercero[0]['ciudad']: NULL,
                        ':Direccion' => isset($resSqlTercero[0]['direccion']) ? $resSqlTercero[0]['direccion']:NULL ,
                        ':Telefono' => isset($resSqlTercero[0]['telefono']) ? $resSqlTercero[0]['telefono']:NULL ,
                        ':Telefono2' => isset($resSqlTercero[0]['telefono2']) ? $resSqlTercero[0]['telefono']: NULL,
                        ':Usuario' => 1,
                        ':Estado' => 1

                    ));

                    if(is_numeric($resInsertDireccion) && $resInsertDireccion > 0){

                        foreach ($resSqlTercero as $key => $value1) {
                            # code...
                            //PASAR VALOR A LETRAS
                        $valorLetra = $formatter->toWords($value1['total'],2);
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
                            ':Tipo' => isset($value1['prefijo']) ? $value1['prefijo'] : NULL,
                            ':Numero' => isset($value1['numero_fac']) ? $value1['numero_fac'] : NULL,
                            ':CodigoConcepto' => isset($value1['prefijo']) ? $value1['prefijo'] : NULL,
                            ':FechaFactura' => isset($value1['fecha_contab']) ? $value1['fecha_contab'] : NULL,
                            ':CodigoTercero' => isset($value1['codigo_sn1']) ? $value1['codigo_sn1'] : NULL,
                            ':CodigoDireccionTercero' => isset($value1['codigo_direccion']) ? $value1['codigo_direccion'] : NULL,
                            ':DireccionEntrega' => isset($value1['direccion']) ? $value1['direccion'] : NULL,
                            ':CodigoFormaPago' => is_numeric($value1['tipo_pago']) ? $value1['tipo_pago'] : 0,
                            ':CodigoVendedor' => is_numeric($value1['id_vendedor']) ? $value1['id_vendedor'] : 0,
                            ':NumeroCuota' => '0',
                            ':Descripcion' => isset($value1['comentarios']) ? $value1['comentarios'] : NULL,
                            ':FechaEntrega' => isset($value1['fecha_contab']) ? $value1['fecha_contab'] : NULL,
                            ':ValorSubTotal' => is_numeric($value1['base']) ? $value1['base'] : 0,
                            ':ValorImpuestos' => is_numeric($value1['total_iva']) ? $value1['total_iva'] : 0,
                            ':ValorRetencion' => is_numeric($value1['retencion']) ? $value1['retencion'] : 0,
                            ':ValorDescuento' => is_numeric($value1['descuento']) ? $value1['descuento'] : 0,
                            ':ValorTotal' => is_numeric($value1['total']) ? $value1['total'] : 0,
                            ':ValorInicial' => 0,
                            ':ValorLetras' => $valorLetra,
                            ':CodigoMoneda' => isset($value1['moneda']) ? $value1['moneda'] : NULL,
                            ':TasaCambio' => 0,
                            ':TipoFactura' => isset($value1['prefijo']) ? $value1['prefijo'] : NULL,
                            ':NumeroFactura' => isset($value1['numero_fac']) ? $value1['numero_fac'] : NULL,
                            ':TipoSoporte' => '',
                            ':NumeroSoporte' => '',
                            ':TipoRespaldo' => '',
                            ':NumeroRespaldo' => 0,
                            ':Usuario' => 1,
                            ':Estado' => 1
                        ));

                        if (is_numeric($resInsertFactura) && $resInsertFactura > 0){

                            foreach ($resSqlTercero as $key => $value) {
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
                                    ':CodigoUnidad' => isset($value['codigo_und_medida']) ? $value['codigo_und_medida'] : 0,
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
                'data'    => $resSqlTercero,
                'mensaje'	=> 'Factura ingresada exitosamente'
                );

            $this->fe->trans_commit();
        }
         


         $this->response($respuesta);
        
	}
}
