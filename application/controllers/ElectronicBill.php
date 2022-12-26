<?php
// DATOS MAESTROS PROYECTO
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/asset/vendor/autoload.php');
require_once(APPPATH . '/libraries/REST_Controller.php');

use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;

class ElectronicBill extends REST_Controller
{

    private $pdo;
    private $pdo_fe;
    public function __construct()
    {

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

        $this->load->library('DateFormat');
		$this->load->library('generic');
        $this->load->library('ftp');
    }

    //INSERTAR DATOS DE SN EN FE
    public function insertBusiness_post()
    {

        $Data = $this->post();

        //SQL PARA DATOS DE TERCEROS FE
        $sqlBusiness = "SELECT * FROM \"Tercero\"";
        //RETORNO DE DATOS DE EMPRESA
        $resBusiness = $this->fe->queryTable($sqlBusiness, array());

        //SQL VISTA DE TERCEROS
        $sqlTercero = "SELECT * FROM terceros";

        //RETORNO DE DATOS DE FacturaS
        $resSqlTercero = $this->pedeo->queryTable($sqlTercero, array());

        //VARIABLE PARA ALMACENAR REGIMEN
        $regimen = "";

        //VALIDAR SI HAY DATOS DE TERCERO
        if (isset($resBusiness[0])) {

            $this->fe->trans_begin();
            foreach ($resBusiness as $key => $value) {

                if (isset($resSqlTercero[0])) {

                    foreach ($resSqlTercero as $key => $value1) {

                        if ($value1['codigo_regimen'] == 'GC') {

                            $regimen = 'O-13';
                        } else if ($value1['codigo_regimen'] == 'AR') {

                            $regimen = 'O-15';
                        } else if ($value1['codigo_regimen'] == 'RS') {

                            $regimen = 'O-23';
                        } else {

                            $regimen = 'R-99-PN';
                        }

                        if ($value['CodigoTercero'] == $value1['codigo_sn1']) {

                            //INICIAR ACTUALIZACION
                            $updateTercero = "UPDATE \"Tercero\" SET \"CodigoTipoPersona\" = :CodigoTipoPersona,
                            \"CodigoTipoRegimen\" = :CodigoTipoRegimen,
                            \"CodigoTipoIdentificacion\" = :CodigoTipoIdentificacion,
                            \"NumeroIdentificacion\" = :NumeroIdentificacion,
                            \"DV\" = :DV,
                            \"NombreTercero\" = :NombreTercero,
                            \"Email\" = :Email,
                            \"EmailRespuesta\" = :EmailRespuesta,
                            \"CodigoActividadEconomica\" = :CodigoActividadEconomica,
                            \"GranContribuyente\" = :GranContribuyente,
                            \"EsAutoretenedor\" = :EsAutoretenedor,
                            \"EsAgenteRetenedorIVA\" = :EsAgenteRetenedorIVA,
                            \"EsAgenteRetenedorReteFuente\" = :EsAgenteRetenedorReteFuente,
                            \"EsAgenteRetenedorICA\" = :EsAgenteRetenedorICA,
                            \"Usuario\" = 1,
                            \"Estado\" = 1
                            WHERE \"CodigoTercero\" = :CodigoTercero";

                            $resUpdateTercero = $this->fe->updateRow($updateTercero, array(
                                ':CodigoTipoPersona' => isset($value1['codigo_sn1']) ? $value1['codigo_sn1'] : NULL,
                                ':CodigoTipoRegimen' => $regimen,
                                ':CodigoTipoIdentificacion' => $value1['tipo_doc'],
                                ':NumeroIdentificacion' => $value1['codigo_sn1'],
                                ':DV' => isset($value1['codigo_sn1']) ? $this->calcularDigitoV($value1['codigo_sn1']) : 0,
                                ':NombreTercero' => $value1['nombre_sn'],
                                ':Email' => $value1['correo'],
                                ':EmailRespuesta' => $value1['correo'],
                                ':CodigoActividadEconomica' => isset($value1['codigo_regimen']) ? $value1['codigo_regimen'] : NULL,
                                ':GranContribuyente' =>  $value1['codigo_regimen'] == 'GC' ? 1 : 0,
                                ':EsAutoretenedor' =>  $value1['codigo_regimen'] == 'AR'  ? 1 : 0,
                                ':EsAgenteRetenedorIVA' =>  $value1['codigo_regimen'] == 'RS'  ? 1 : 0,
                                ':EsAgenteRetenedorReteFuente' =>  $value1['codigo_regimen'] == 'RS' ? 1 : 0,
                                ':EsAgenteRetenedorICA' =>  $value1['codigo_regimen'] == 'RS' ? 1 : 0,
                                ':CodigoTercero' => $value1['codigo_sn1']
                            ));

                            if (is_numeric($resUpdateTercero) && $resUpdateTercero > 0) {

                                //ACTUALIZAR DATOS DE DIRECCIONES
                                $updateDireccion = "UPDATE \"DireccionCliente\" SET \"EsPrincipal\" = :EsPrincipal,
                                \"EsEntrega\" = :EsEntrega,
                                \"CodigoPais\" = :CodigoPais,
                                \"CodigoDepto\" = :CodigoDepto,
                                \"CodigoCiudad\" = :CodigoCiudad,
                                \"CodigoBarrio\" = :CodigoBarrio,
                                \"CodigoZona\" = :CodigoZona,
                                \"Direccion\" = :Direccion,
                                \"Telefono\" = :Telefono,
                                \"Telefono2\" = :Telefono2,
                                \"Usuario\" = :Usuario,
                                \"Estado\" = :Estado
                                WHERE \"CodigoTercero\" = :CodigoTercero";
                                // print_r($updateDireccion);exit;
                                $resUpdateDireccion = $this->fe->updateRow($updateDireccion, array(

                                    ':EsPrincipal' => isset($value1['principal']) ?  1 : 0,
                                    ':EsEntrega' => isset($value1['principal']) ? 1 : 0,
                                    ':CodigoPais' => isset($value1['pais']) ? $value1['pais'] : NULL,
                                    ':CodigoDepto' => isset($value1['dpto']) ? $value1['dpto'] : NULL,
                                    ':CodigoCiudad' => isset($value1['ciudad']) ? $value1['ciudad'] : NULL,
                                    ':CodigoBarrio' => isset($value1['ciudad']) ? $value1['ciudad'] : NULL,
                                    ':CodigoZona' => isset($value1['cod_postal']) ? $value1['cod_postal'] : NULL,
                                    ':Direccion' => isset($value1['direccion']) ? $value1['direccion'] : NULL,
                                    ':Telefono' => isset($value1['telefono']) ? $value1['telefono'] : NULL,
                                    ':Telefono2' => isset($value1['telefono2']) ? $value1['telefono'] : NULL,
                                    ':Usuario' => 1,
                                    ':Estado' => 1,
                                    ':CodigoTercero' => $value1['codigo_sn1']
                                ));
                                // print_r(is_numeric($resUpdateDireccion));exit;
                                if (is_numeric($resUpdateDireccion) && $resUpdateDireccion > 0) {

                                    // print_r($resUpdateDireccion);exit;

                                } else {

                                    $this->fe->trans_rollback();

                                    $respuesta = array(
                                        'error'   => true,
                                        'Factura'    => $resUpdateDireccion,
                                        'mensaje'    => 'No se pudo actualizar la direccion'
                                    );

                                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                                    return;
                                }
                            } else {

                                $this->fe->trans_rollback();

                                $respuesta = array(
                                    'error'   => true,
                                    'Factura'    => $resUpdateTercero,
                                    'mensaje'    => 'No se pudo actualizar el tercero'
                                );

                                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                                return;
                            }

                            $respuesta = array(
                                'error'   => false,
                                'Factura'    => $resUpdateTercero,
                                'mensaje'    => 'Datos Actualizados correctamente'
                            );
                        } else {
                        }
                    }
                }
            }

            $this->fe->trans_commit();
        } else {

            if (isset($resSqlTercero[0])) {

                $this->fe->trans_begin();

                foreach ($resSqlTercero as $key => $value1) {

                    if ($value1['codigo_regimen'] == 'GC') {

                        $regimen = 'O-13';
                    } else if ($value1['codigo_regimen'] == 'AR') {

                        $regimen = 'O-15';
                    } else if ($value1['codigo_regimen'] == 'RS') {

                        $regimen = 'O-23';
                    } else {

                        $regimen = 'R-99-PN';
                    }

                    //INSERTAR DATOS A TABLA DE TERCEROS BD FE
                    $insertTercero = "INSERT INTO \"Tercero\"(\"CodigoTercero\",\"CodigoTipoPersona\",\"CodigoTipoRegimen\",\"CodigoTipoIdentificacion\",
                    \"NumeroIdentificacion\",\"DV\",\"NombreTercero\",\"Email\",\"EmailRespuesta\",\"CodigoActividadEconomica\",\"GranContribuyente\",
                    \"EsAutoretenedor\",\"EsAgenteRetenedorIVA\",\"EsAgenteRetenedorReteFuente\",\"EsAgenteRetenedorICA\",\"Usuario\",\"Estado\")
                    VALUES (:CodigoTercero,:CodigoTipoPersona,:CodigoTipoRegimen,:CodigoTipoIdentificacion,:NumeroIdentificacion,:DV,:NombreTercero,
                    :Email,:EmailRespuesta,:CodigoActividadEconomica,:GranContribuyente,:EsAutoretenedor,:EsAgenteRetenedorIVA,:EsAgenteRetenedorReteFuente,
                    :EsAgenteRetenedorICA,:Usuario,:Estado)";

                    $resInsertTercero = $this->fe->insertRow($insertTercero, array(
                        ':CodigoTercero' => $value1['codigo_sn1'],
                        ':CodigoTipoPersona' => '1',
                        ':CodigoTipoRegimen' => $regimen,
                        ':CodigoTipoIdentificacion' => $value1['tipo_doc'],
                        ':NumeroIdentificacion' => $value1['codigo_sn1'],
                        ':DV' => isset($value1['codigo_sn1']) ? $this->calcularDigitoV($value1['codigo_sn1']) : 0,
                        ':NombreTercero' => $value1['nombre_sn'],
                        ':Email' => $value1['correo'],
                        ':EmailRespuesta' => $value1['correo'],
                        ':CodigoActividadEconomica' => isset($value1['codigo_regimen']) ? $value1['codigo_regimen'] : NULL,
                        ':GranContribuyente' =>  $value1['codigo_regimen'] == 'GC' ? 1 : 0,
                        ':EsAutoretenedor' =>  $value1['codigo_regimen'] == 'AR'  ? 1 : 0,
                        ':EsAgenteRetenedorIVA' =>  $value1['codigo_regimen'] == 'RS'  ? 1 : 0,
                        ':EsAgenteRetenedorReteFuente' =>  $value1['codigo_regimen'] == 'RS' ? 1 : 0,
                        ':EsAgenteRetenedorICA' =>  $value1['codigo_regimen'] == 'RS' ? 1 : 0,
                        ':Usuario' => 1,
                        ':Estado' => 1
                    ));

                    if (is_numeric($resInsertTercero) && $resInsertTercero > 0) {

                        //INSERTAR DATOS DE DIRECCION BD FE
                        $insertDireccion = "INSERT INTO \"DireccionCliente\" (\"CodigoTercero\",\"EsPrincipal\",\"EsEntrega\",\"CodigoPais\",\"CodigoDepto\",
                        \"CodigoCiudad\",\"CodigoBarrio\",\"CodigoZona\",\"Direccion\",\"Telefono\",\"Telefono2\",\"Usuario\",\"Estado\")
                        VALUES (:CodigoTercero,:EsPrincipal,:EsEntrega,:CodigoPais,:CodigoDepto,:CodigoCiudad,:CodigoBarrio,:CodigoZona,:Direccion,:Telefono,
                        :Telefono2,:Usuario,:Estado)";

                        $resInsertDireccion = $this->fe->insertRow($insertDireccion, array(
                            ':CodigoTercero' => isset($value1['codigo_sn1']) ? $value1['codigo_sn1'] : NULL,
                            ':EsPrincipal' => isset($value1['principal']) ?  1 : 0,
                            ':EsEntrega' => isset($value1['principal']) ? 1 : 0,
                            ':CodigoPais' => isset($value1['pais']) ? $value1['pais'] : NULL,
                            ':CodigoDepto' => isset($value1['dpto']) ? $value1['dpto'] : NULL,
                            ':CodigoCiudad' => isset($value1['ciudad']) ? $value1['ciudad'] : NULL,
                            ':CodigoBarrio' => isset($value1['ciudad']) ? $value1['ciudad'] : NULL,
                            ':CodigoZona' => isset($value1['cod_postal']) ? $value1['cod_postal'] : NULL,
                            ':Direccion' => isset($value1['direccion']) ? $value1['direccion'] : NULL,
                            ':Telefono' => isset($value1['telefono']) ? $value1['telefono'] : NULL,
                            ':Telefono2' => isset($value1['telefono2']) ? $value1['telefono'] : NULL,
                            ':Usuario' => 1,
                            ':Estado' => 1

                        ));

                        if (is_numeric($resInsertDireccion) && $resInsertDireccion > 0) {
                        } else {

                            $this->fe->trans_rollback();

                            $respuesta = array(
                                'error'   => true,
                                'Factura'    => $resInsertDireccion,
                                'mensaje'    => 'No se pudo insertar la direccion'
                            );

                            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                            return;
                        }
                    } else {

                        $this->fe->trans_rollback();

                        $respuesta = array(
                            'error'   => true,
                            'Factura'    => $resInsertTercero,
                            'mensaje'    => 'No se pudo insertar el tercero'
                        );

                        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                        return;
                    }


                    $respuesta = array(
                        'error'   => false,
                        'Factura'    => $resInsertTercero,
                        'mensaje'    => 'Datos Guardados con Exito - Tablas(Tercero,DireccionCliente)'
                    );
                }

                $this->fe->trans_commit();
            }
        }

        $this->response($respuesta);
    }
    //INSERTAR FACTURAS EN FE
    public function insertInvoices_post()
    {

        $Data = $this->post();
        $formatter = new NumeroALetras();
        //DATOS DE EMPRESA FE
        $sqlEmpresa = "SELECT * FROM \"Empresa\"";
        $resEmpresa = $this->fe->queryTable($sqlEmpresa, array());
        //SQL VISTA DE FacturaS
        $sqlInvoices = "SELECT * FROM Factura_cab";

        //RETORNO DE DATOS DE FacturaS
        $resSqlInvoices = $this->pedeo->queryTable($sqlInvoices, array());

        //VALIDAR SI HAY DATOS DE Factura
        if (isset($resSqlInvoices[0]) && !empty($resSqlInvoices[0])) {

            $this->fe->trans_begin();
            foreach ($resSqlInvoices as $key => $value) {
                set_time_limit(300);

                $sqlInvoicesFE = " SELECT \"Numero\" FROM \"Factura\" WHERE \"Numero\" = :Factura";
                $resSqlInvoicesFE = $this->fe->queryTable($sqlInvoicesFE, array(':Factura' => $value['numero_fac']));

                $direccion = "SELECT \"Id_DireccionCliente\",\"Direccion\" FROM \"DireccionCliente\" WHERE \"CodigoTercero\" = :CodigoTercero";
                $resDireccion = $this->fe->queryTable($direccion, array(':CodigoTercero' => $value['codigo_sn1']));
                // print_r($resDireccion[0]['Direccion']);exit;
                // print_r(count($resSqlInvoicesFE));exit;
                if (!count($resSqlInvoicesFE) > 0) {
                    //INICIAR TRANSACCION
                    //INSERTAR DATOS A TABLA DE Factura BD FE
                    $valorLetra = $formatter->toWords($value['total'], 2);
                    //INSERTAR DATOS DE CABECERA DE Factura A BD FE
                    $insertFactura = "INSERT INTO \"Factura\" (\"CodigoEmpresa\",\"Sucursal\",\"CodigoResolucion\",\"Tipo\",\"Numero\",
                    \"FechaFactura\",\"CodigoTercero\",\"CodigoDireccionTercero\",\"DireccionEntrega\",\"CodigoFormaPago\",\"CodigoVendedor\",\"NumeroCuota\",
                    \"Descripcion\",\"FechaEntrega\",\"ValorSubTotal\",\"ValorImpuestos\",\"ValorRetencion\",\"ValorTotal\",\"ValorInicial\",
                    \"ValorLetras\",\"CodigoMoneda\",\"TasaCambio\",\"TipoFactura\",\"NumeroFactura\",\"TipoSoporte\",\"NumeroSoporte\",\"TipoRespaldo\",
                    \"NumeroRespaldo\",\"Usuario\",\"Estado\")
                    VALUES (:CodigoEmpresa,:Sucursal,:CodigoResolucion,:Tipo,:Numero,:FechaFactura,:CodigoTercero,:CodigoDireccionTercero,
                    :DireccionEntrega,:CodigoFormaPago,:CodigoVendedor,:NumeroCuota,:Descripcion,:FechaEntrega,:ValorSubTotal,:ValorImpuestos,:ValorRetencion,
                    :ValorTotal,:ValorInicial,:ValorLetras,:CodigoMoneda,:TasaCambio,:TipoFactura,:NumeroFactura,:TipoSoporte,:NumeroSoporte,
                    :TipoRespaldo,:NumeroRespaldo,:Usuario,:Estado)";

                    $resInsertFactura = $this->fe->insertRow($insertFactura, array(
                        ':CodigoEmpresa' =>  $resEmpresa[0]['CodigoEmpresa'],
                        ':Sucursal' =>  1,
                        ':CodigoResolucion' => '000',
                        ':Tipo' => 'FV',
                        ':Numero' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                        ':FechaFactura' => isset($value['fecha_contab']) ? $value['fecha_contab'] : NULL,
                        ':CodigoTercero' => isset($value['codigo_sn1']) ? $value['codigo_sn1'] : NULL,
                        ':CodigoDireccionTercero' => isset($resDireccion[0]['Id_DireccionCliente']) ? $resDireccion[0]['Id_DireccionCliente'] : NULL,
                        ':DireccionEntrega' => isset($resDireccion[0]['Direccion']) ? $resDireccion[0]['Direccion'] : NULL,
                        ':CodigoFormaPago' => is_numeric($value['tipo_pago']) ? $value['tipo_pago'] : 0,
                        ':CodigoVendedor' => is_numeric($value['id_vendedor']) ? $value['id_vendedor'] : 0,
                        ':NumeroCuota' => '0',
                        ':Descripcion' => isset($value['comentarios']) ? $value['comentarios'] : NULL,
                        ':FechaEntrega' => isset($value['fecha_contab']) ? $value['fecha_contab'] : NULL,
                        ':ValorSubTotal' => is_numeric($value['base']) ? $value['base'] : 0,
                        ':ValorImpuestos' => is_numeric($value['total_iva']) ? $value['total_iva'] : 0,
                        ':ValorRetencion' => is_numeric($value['retencion']) ? $value['retencion'] : 0,
                        ':ValorTotal' => is_numeric($value['total']) ? $value['total'] : 0,
                        ':ValorInicial' => 0,
                        ':ValorLetras' => $valorLetra,
                        ':CodigoMoneda' => isset($value['moneda']) ? $value['moneda'] : NULL,
                        ':TasaCambio' => 0,
                        ':TipoFactura' => isset($value['prefijo']) ? $value['prefijo'] : NULL,
                        ':NumeroFactura' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                        ':TipoSoporte' => '',
                        ':NumeroSoporte' => '',
                        ':TipoRespaldo' => '',
                        ':NumeroRespaldo' => 0,
                        ':Usuario' => 1,
                        ':Estado' => 1
                    ));

                    if (is_numeric($resInsertFactura) && $resInsertFactura > 0) {

                        //INSERTAR INFORMACION TABLA A PROCESAR FacturaCION ELECTRONICA
                        //GUARDAR PDF DE FACTURA
                        //CREAR DIRECTORIO DE FACTURA
                        $dir = $this->createDirFtp($value['codigo_sn1'].'/'.'1'.'/'.'FV'.'/'.date('Y').'/'.date('m').'/'.'FV');
                        //GUARDAR PD EN LA RUTA TEMP
                        $this->invoicesPDF($value['doc_entry'],$this->nameFiles('FV',$value['codigo_sn1'],'000',date('Y'),'1','pdf'));
                        //ENVIAR ARCHIVO A LA RUTA DEL FTP
                        $this->createFileFtp($dir,$this->nameFiles('FV',$value['codigo_sn1'],'000',date('Y'),'1','pdf'));
                        //ELIMINAR ARCHIVO DE LA CARPETA TEMPORAL
                        $this->deleteFileLocal($this->nameFiles('FV',$value['codigo_sn1'],'000',date('Y'),'1','pdf'));

                        $insertFE = "INSERT INTO \"FacturaElectronica\" (\"CodigoEmpresa\",\"Sucursal\",\"Tipo\",\"Numero\",\"CodigoTercero\",
                        \"FechaFactura\",\"TipoSoporte\",\"NumeroSoporte\",\"Usuario\",\"Estado\",\"Clase\",\"RutaPDF\")
                        VALUES (:CodigoEmpresa,:Sucursal,:Tipo,:Numero,:CodigoTercero,:FechaFactura,:TipoSoporte,:NumeroSoporte,:Usuario,
                        :Estado,:Clase,:RutaPDF)";
                        $resInsertFE = $this->fe->insertRow($insertFE, array(
                            ':CodigoEmpresa' => $resEmpresa[0]['CodigoEmpresa'],
                            ':Sucursal' => 1,
                            ':Tipo' => 'FV',
                            ':Numero' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                            ':CodigoTercero' => isset($value['codigo_sn1']) ? $value['codigo_sn1'] : NULL,
                            ':FechaFactura' => date('Y-m-d'),
                            ':TipoSoporte' => isset($value['prefijo']) ? $value['prefijo'] : NULL,
                            ':NumeroSoporte' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                            ':Usuario' => 1,
                            ':Estado' => 0,
                            ':Clase' => 'FV',
                            ':RutaPDF' => $dir.'/'.$this->nameFiles('FV',$value['codigo_sn1'],'000',date('Y'),'1','pdf')
                        ));

                        if (is_numeric($resInsertFE) && $resInsertFE > 0) {

                            //INSERTAR TABLA IMPUESTO

                            $insertImpuesto = "INSERT INTO \"FacturaImpuesto\" (\"CodigoEmpresa\",\"Sucursal\",\"TipoFactura\",\"NumeroFactura\",
                            \"CodigoFactura\",\"ClaseImpuesto\",\"ValorBase\",\"Porcentaje\",\"ValorImpuesto\",\"Usuario\",\"Estado\",\"CodigoImpuesto\")
                            VALUES (:CodigoEmpresa,:Sucursal,:TipoFactura,:NumeroFactura,:CodigoFactura,:ClaseImpuesto,:ValorBase,:Porcentaje,:ValorImpuesto,
                            :Usuario,:Estado,:CodigoImpuesto)";

                            $resInsertImpuesto = $this->fe->insertRow($insertImpuesto, array(
                                ':CodigoEmpresa' => $resEmpresa[0]['CodigoEmpresa'],
                                ':Sucursal' => 1,
                                ':TipoFactura' => 'FV',
                                ':NumeroFactura' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                                ':CodigoFactura' => is_numeric($resInsertFactura) ? $resInsertFactura : 0,
                                ':ClaseImpuesto' => 'IVA',
                                ':ValorBase' => isset($value['base']) ? $value['base'] : NULL,
                                ':Porcentaje' => isset($value['code_iva']) ? $value['code_iva'] : NULL,
                                ':ValorImpuesto' => isset($value['total_iva']) ? $value['total_iva'] : NULL,
                                ':Usuario' => 1,
                                ':Estado' => 1,
                                ':CodigoImpuesto' => '01'
                            ));

                            if (is_numeric($resInsertImpuesto) && $resInsertImpuesto > 0) {

                                //CONSULTA PARA DETALLE
                                $sqlInvoicesDet = "SELECT * FROM Factura_det WHERE numero_fac = :numero_fac";
                                $resSqlInvoicesDet = $this->pedeo->queryTable($sqlInvoicesDet, array(':numero_fac' => $value['numero_fac']));

                                foreach ($resSqlInvoicesDet as $key => $value1) {

                                    if ($value['numero_fac'] == $value1['numero_fac']) {

                                        //INSERTAR DATOS DE DETALLE DE Factura A BD FE
                                        $insertDetalleFactura = "INSERT INTO \"DetalleFactura\" (\"CodigoEmpresa\",\"Sucursal\",\"Tipo\",\"Numero\",\"Conteo\",
                                        \"TipoSoporte\",\"NumeroSoporte\",\"ConteoSoporte\",\"CodigoFactura\",\"CodigoProducto\",\"DescripcionProducto\",
                                        \"CodigoUnidad\",\"ValorUnitario\",\"Cantidad\",\"ValorSubTotal\",\"PorcentajeIva\",\"ValorIva\",\"PorcentajeRetencion\",
                                        \"ValorRetencion\",\"ValorTotal\",\"ValorFinanciado\",\"Usuario\",\"Estado\")
                                        VALUES (:CodigoEmpresa,:Sucursal,:Tipo,:Numero,:Conteo,:TipoSoporte,:NumeroSoporte,:ConteoSoporte,:CodigoFactura,
                                        :CodigoProducto,:DescripcionProducto,:CodigoUnidad,:ValorUnitario,:Cantidad,:ValorSubTotal,:PorcentajeIva,:ValorIva,
                                        :PorcentajeRetencion,:ValorRetencion,:ValorTotal,:ValorFinanciado,:Usuario,:Estado)";

                                        $resInsertDetalleFactura = $this->fe->insertRow($insertDetalleFactura, array(

                                            ':CodigoEmpresa' => $resEmpresa[0]['CodigoEmpresa'],
                                            ':Sucursal' => 1,
                                            ':Tipo' => 'FV',
                                            ':Numero' =>  $value['numero_fac'],
                                            ':Conteo' =>  $key,
                                            ':TipoSoporte' => 0,
                                            ':NumeroSoporte' => 0,
                                            ':ConteoSoporte' => 0,
                                            ':CodigoFactura' => isset($value1['numero_fac']) ? $value1['numero_fac'] : NULL,
                                            ':CodigoProducto' => isset($value1['codigo_item']) ? $value1['codigo_item'] : NULL,
                                            ':DescripcionProducto' => isset($value1['codigo_item']) ? $value1['codigo_item'] : NULL,
                                            ':CodigoUnidad' => isset($value['codigo_und_medida']) ? $value['codigo_und_medida'] : 0,
                                            ':ValorUnitario' => is_numeric($value1['precio_und']) ? $value1['precio_und'] : 0,
                                            ':Cantidad' => is_numeric($value1['cantidad']) ? $value1['cantidad'] : 0,
                                            ':ValorSubTotal' => is_numeric($value1['precio_und']) ? $value1['precio_und'] * $value1['cantidad'] : 0,
                                            ':PorcentajeIva' => is_numeric($value1['porcentaje_iva']) ? $value1['porcentaje_iva'] : 0,
                                            ':ValorIva' => is_numeric($value1['valor_iva']) ? $value1['valor_iva'] : 0,
                                            ':PorcentajeRetencion' => is_numeric($value1['retencion']) ? $value1['retencion'] : 0,
                                            ':ValorRetencion' => is_numeric($value1['retencion']) ? $value1['retencion'] : 0,
                                            ':ValorTotal' => is_numeric($value1['total_linea']) ? $value1['total_linea'] : 0,
                                            ':ValorFinanciado' =>  0,
                                            ':Usuario' => 1,
                                            ':Estado' => 1
                                        ));

                                        
                                        if (is_numeric($resInsertDetalleFactura) && $resInsertDetalleFactura > 0) {
                                            
                                        } else {

                                            $this->fe->trans_rollback();

                                            $respuesta = array(
                                                'error'   => true,
                                                'Factura'    => $resInsertDetalleFactura,
                                                'mensaje'    => 'No se pudo insertar el detalle de la Factura'
                                            );

                                            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                                            return;
                                        }
                                    }
                                }
                            } else {
                                $this->fe->trans_rollback();

                                $respuesta = array(
                                    'error'   => true,
                                    'Factura'    => $resInsertImpuesto,
                                    'mensaje'    => 'No se pudo insertar el detalle impuesto de la Factura'
                                );

                                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                                return;
                            }

                            
                        } else {

                            $this->fe->trans_rollback();

                            $respuesta = array(
                                'error'   => true,
                                'Factura'    => $resInsertFE,
                                'mensaje'    => 'No se pudo insertar el dato de la tabla Factura electronica'
                            );

                            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                            return;
                        }
                    } else {

                        $this->fe->trans_rollback();

                        $respuesta = array(
                            'error'   => true,
                            'Factura'    => $resInsertFactura,
                            'mensaje'    => 'No se pudo insertar la Factura'
                        );

                        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                        return;
                    }

                    $respuesta = array(
                        'error'   => false,
                        'Factura'    => $resInsertFactura,
                        'mensaje'    => 'Datos Guardados con Exito - Tablas (Factura,FacturaDetalle,FacturaElectronica,FacturaImpuesto)'
                    );

                    $this->fe->trans_commit();
                }else {

                    $respuesta = array(
                        'error'   => true,
                        'Factura'    => [],
                        'mensaje'    => 'No se encotraron facturas para insertar'
                    );
                }
                
            }

            
        }

        $this->response($respuesta);
    }
    //CALCULAR EL DIGITO DE VERIFICACION DEL NIT O CC
    private function calcularDigitoV($nit)
    {
        if (!is_numeric($nit)) {
            return false;
        }

        $arr = array(
            1 => 3, 4 => 17, 7 => 29, 10 => 43, 13 => 59, 2 => 7, 5 => 19,
            8 => 37, 11 => 47, 14 => 67, 3 => 13, 6 => 23, 9 => 41, 12 => 53, 15 => 71
        );
        $x = 0;
        $y = 0;
        $z = strlen($nit);
        $dv = '';

        for ($i = 0; $i < $z; $i++) {
            $y = substr($nit, $i, 1);
            $x += ($y * $arr[$z - $i]);
        }

        $y = $x % 11;

        if ($y > 1) {
            $dv = 11 - $y;
            return $dv;
        } else {
            $dv = $y;
            return $dv;
        }
    }
    //GUARDAR PDF DE FACTURA
    private function invoicesPDF($Factura,$name_file)
    {
        // print_r($ruta_file);exit;
        $DECI_MALES =  $this->generic->getDecimals();
        $formatter = new NumeroALetras();

        //'setAutoTopMargin' => 'stretch','setAutoBottomMargin' => 'stretch',

        $mpdf = new \Mpdf\Mpdf(['margin-top' => 500, 'default_font' => 'arial']);

        //RUTA DE CARPETA EMPRESA
        $company = $this->pedeo->queryTable("SELECT main_folder company FROM PARAMS", array());

        if (!isset($company[0])) {
            $respuesta = array(
                'error' => true,
                'Factura'  => $company,
                'mensaje' => 'no esta registrada la ruta de la empresa'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        //INFORMACION DE LA EMPRESA

        $sql = "SELECT
                    pge_id,
                    pge_name_soc,
                    pge_small_name,
                    pge_add_soc,
                    pge_state_soc,
                    pge_city_soc,
                    pge_cou_soc,
                    CONCAT(pge_id_type,' ',pge_id_soc) AS pge_id_type ,
                    pge_web_site, pge_logo,
                    CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1,
                    pge_branch, pge_mail,
                    pge_curr_first,
                    pge_curr_sys,
                    pge_cou_bank,
                    pge_bank_def,
                    pge_bank_acct,
                    pge_acc_type
                FROM pgem";

        $empresa = $this->pedeo->queryTable($sql, array());

        if (!isset($empresa[0])) {
            $respuesta = array(
                'error' => true,
                'Factura'  => $empresa,
                'mensaje' => 'no esta registrada la información de la empresa'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $sqlcotizacion = "SELECT
                            concat(T0.dvf_cardname,' ',T2.dms_card_last_name) Cliente,
                            T0.dvf_cardcode Nit,
                            concat(T3.dmd_adress,' ',T3.dmd_city) Direccion,
                            T3.dmd_state_mm ciudad,
                            t3.dmd_state estado,
                            T2.dms_phone1 Telefono,
                            T2.dms_email Email,
                            t0.dvf_docnum,
                            ConCAT(T6.pgs_pref_num,' ',T0.dvf_docnum) NumeroDocumento,
                            to_char(T0.dvf_docdate,'DD-MM-YYYY') FechaDocumento,
                            to_char(T0.dvf_duedate,'DD-MM-YYYY') FechaVenDocumento,
                            trim('COP' from t0.dvf_currency) MonedaDocumento,
                            T7.pgm_name_moneda NOMBREMonEDA,
                            T5.mev_names Vendedor,
                            t8.mpf_name CondPago,
                            T1.fv1_itemcode Referencia,
                            T1.fv1_itemname descripcion,
                            T1.fv1_whscode Almacen,
                            T1.fv1_uom UM,
                            T1.fv1_quantity Cantidad,
                            T1.fv1_price VrUnit,
                            T1.fv1_discount PrcDes,
                            T1.fv1_vatsum IVAP,
                            T1.fv1_linetotal ValorTotalL,
                            T0.dvf_baseamnt base,
                            T0.dvf_discount Descuento,
                            (T0.dvf_baseamnt - T0.dvf_discount) subtotal,
                            T0.dvf_taxtotal Iva,
                            T0.dvf_doctotal TotalDoc,
                            T0.dvf_comment Comentarios,
                            t0.dvf_ref oc,
                            0 peso,
                            t10.dmu_code um,
                            T0.dvf_precinto precintos,
                            t0.dvf_placav placa,
                            t0.dvf_docdate,
                            t6.pgs_mpfn,
                            t6.pgs_mde,
                            t1.fv1_quantity,
                            t2.dms_rtype regimen,
                            T0.dvf_taxigtf,
                            T0.dvf_igtf,
                            t0.dvf_igtfapplyed,
                            get_dynamic_conversion(get_localcur(),t0.dvf_igtfcurrency,t0.dvf_docdate,t0.dvf_igtfapplyed,get_localcur()) as base_igtf,
                            get_dynamic_conversion(get_localcur(),t0.dvf_igtfcurrency,t0.dvf_docdate,t0.dvf_igtf,get_localcur()) as imp_igtf
                        from dvfv t0
                        inner join vfv1 T1 on t0.dvf_docentry = t1.fv1_docentry/*  */
                        left join dmsn T2 on t0.dvf_cardcode = t2.dms_card_code
                        left join dmsd T3 on T0.dvf_cardcode = t3.dmd_card_code AND t3.dmd_ppal = 1
                        left join dmsc T4 on T0.dvf_cardcode = t4.dmc_card_code
                        left join dmev T5 on T0.dvf_slpcode = T5.mev_id
                        left join pgdn T6 on T0.dvf_doctype = T6.pgs_id_doc_type and T0.dvf_series = T6.pgs_id
                        left join pgec T7 on T0.dvf_currency = T7.pgm_symbol
                        left join dmpf t8 on t2.dms_pay_type = cast(t8.mpf_id as varchar)
                        left join dmar t9 on t1.fv1_itemcode = t9.dma_item_code
                        left join dmum t10 on t9.dma_uom_umweight = t10.dmu_id
                        where T0.dvf_docentry = :DVF_DOCENTRY
                        and t2.dms_card_type = '1'";

        $contenidoFV = $this->pedeo->queryTable($sqlcotizacion, array(':DVF_DOCENTRY' => $Factura));

        if (!isset($contenidoFV[0])) {
            $respuesta = array(
                'error' => true,
                'Factura'  => $empresa,
                'mensaje' => 'no se encontro el documento'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }
        // PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
        // SE BUSCA LA MONEDA LOCAL PARAMETRIZADA
        $sqlMonedaLoc = "SELECT pgm_symbol FROM pgec WHERE pgm_principal = :pgm_principal";
        $resMonedaLoc = $this->pedeo->queryTable($sqlMonedaLoc, array(':pgm_principal' => 1));

        if (isset($resMonedaLoc[0])) {
        } else {
            $respuesta = array(
                'error' => true,
                'Factura'  => array(),
                'mensaje' => 'No se encontro la moneda local.'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $MONEDALOCAL = trim($resMonedaLoc[0]['pgm_symbol']);

        // SE BUSCA LA MONEDA DE SISTEMA PARAMETRIZADA
        $sqlMonedaSys = "SELECT pgm_symbol FROM pgec WHERE pgm_system = :pgm_system";
        $resMonedaSys = $this->pedeo->queryTable($sqlMonedaSys, array(':pgm_system' => 1));

        if (isset($resMonedaSys[0])) {
        } else {

            $respuesta = array(
                'error' => true,
                'Factura'  => array(),
                'mensaje' => 'No se encontro la moneda de sistema.'
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }


        $MONEDASYS = trim($resMonedaSys[0]['pgm_symbol']);

        //SE BUSCA LA TASA DE CAMBIO CON RESPECTO A LA MONEDA QUE TRAE EL DOCUMENTO A CREAR CON LA MONEDA LOCAL
        // Y EN LA MISMA FECHA QUE TRAE EL DOCUMENTO
        $sqlBusTasa = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
        $resBusTasa = $this->pedeo->queryTable($sqlBusTasa, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $contenidoFV[0]['monedadocumento'], ':tsa_date' => $contenidoFV[0]['dvf_docdate']));

        if (isset($resBusTasa[0])) {
        } else {

            if (trim($contenidoFV[0]['monedadocumento']) != $MONEDALOCAL) {

                $respuesta = array(
                    'error' => true,
                    'Factura'  => array(),
                    'mensaje' => 'No se encrontro la tasa de cambio para la moneda: ' . $contenidoFV[0]['monedadocumento'] . ' en la actual fecha del documento: ' . $contenidoFV[0]['dvf_docdate'] . ' y la moneda local: ' . $resMonedaLoc[0]['pgm_symbol']
                );

                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                return;
            }
        }


        $sqlBusTasa2 = "SELECT tsa_value FROM tasa WHERE TRIM(tsa_curro) = TRIM(:tsa_curro) AND tsa_currd = TRIM(:tsa_currd) AND tsa_date = :tsa_date";
        $resBusTasa2 = $this->pedeo->queryTable($sqlBusTasa2, array(':tsa_curro' => $resMonedaLoc[0]['pgm_symbol'], ':tsa_currd' => $resMonedaSys[0]['pgm_symbol'], ':tsa_date' => $contenidoFV[0]['dvf_docdate']));

        if (isset($resBusTasa2[0])) {
        } else {
            $respuesta = array(
                'error' => true,
                'Factura'  => array(),
                'mensaje' => 'No se encrontro la tasa de cambio para la moneda local contra la moneda del sistema, en la fecha del documento actual :' . $contenidoFV[0]['dvf_docdate']
            );

            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

            return;
        }

        $TasaDocLoc = isset($resBusTasa[0]['tsa_value']) ? $resBusTasa[0]['tsa_value'] : 1;
        $TasaLocSys = $resBusTasa2[0]['tsa_value'];

        // FIN DEL PROCEDIMIENTO PARA USAR LA TASA DE LA MONEDA DEL DOCUMENTO
        //OBTENER RELACION DE DOCUMENTOS
        $relacionsql = "SELECT dvct.dvc_docnum AS cotizacion, dvov.vov_docnum AS pedido, dvem.vem_docnum AS entrega
                                FROM dvfv
                                LEFT JOIN dvem
                                ON dvfv.dvf_baseentry = dvem.vem_docentry
                                LEFT JOIN dvov
                                ON dvem.vem_baseentry = dvov.vov_docentry
                                LEFT JOIN dvct
                                ON dvov.vov_baseentry = dvct.dvc_docentry
                                WHERE dvfv.dvf_docentry = :DVF_DOCENTRY";

        $resrelacionsql = $this->pedeo->queryTable($relacionsql, array(':DVF_DOCENTRY' => $Factura));

        $VieneEntrega = 0;
        $VienePedido = 0;
        $VieneCotizacion = 0;

        if (isset($resrelacionsql[0])) {
            $VieneEntrega = $resrelacionsql[0]['entrega'];
            $VienePedido = $resrelacionsql[0]['pedido'];
            $VieneCotizacion = $resrelacionsql[0]['cotizacion'];
        }
        //FIN BUSQUEDA REALAZION DE DOCUMENTOS
        //INFORMACION DE LA DESCRIPCION FINAL DEL FORMATO
        $CommentFinal = "SELECT t0.*
                                         FROM cfdm t0
                                         LEFT JOIN dvfv t1 ON t0.cdm_type = CAST(t1.dvf_doctype AS VARCHAR)
                                         WHERE t1.dvf_docentry = :DVF_DOCENTRY";
        $CommentFinal = $this->pedeo->queryTable($CommentFinal, array(':DVF_DOCENTRY' => $Factura));


        $totaldetalle = '';
        $TotalCantidad = 0;
        $TotalPeso = 0;

        foreach ($contenidoFV as $key => $value) {

            $valorUnitario = $value['vrunit'];
            $valortotalLinea = $value['valortotall'];

            if ($value['monedadocumento'] != $MONEDALOCAL) {

                $valorUnitario = ($valorUnitario  * $TasaDocLoc);
                $valortotalLinea = ($valortotalLinea  * $TasaDocLoc);
            }

            $detalle = '<td>' . $value['cantidad'] . '</td>
                        <td>' . $value['referencia'] . '</td>
                        <td>' . $value['descripcion'] . '</td>
                        <td>' . $value['monedadocumento'] . " " . number_format($valorUnitario, $DECI_MALES, ',', '.') . '</td>
                        <td>' . $value['monedadocumento'] . " " . number_format($valortotalLinea, $DECI_MALES, ',', '.') . '</td>';

            $totaldetalle = $totaldetalle . '<tr>' . $detalle . '</tr>';
            $TotalCantidad = ($TotalCantidad + ($value['cantidad']));
            $TotalPeso = ($TotalPeso + ($value['peso'] * $value['cantidad']));
        }

        $valorTotalBase = $contenidoFV[0]['base'];
        $valorTotalSubtotal = $contenidoFV[0]['subtotal'];
        $valorTotalIva = $contenidoFV[0]['iva'];
        $valorTotalDoc = $contenidoFV[0]['totaldoc'];

        if ($value['monedadocumento'] != $MONEDALOCAL) {

            $valorTotalBase = ($valorTotalBase * $TasaDocLoc);
            $valorTotalSubtotal = ($valorTotalSubtotal * $TasaDocLoc);
            $valorTotalIva = ($valorTotalIva * $TasaDocLoc);
            $valorTotalDoc = ($valorTotalDoc * $TasaDocLoc);
        }


        $sqlIgtf = "SELECT 
                        gtf_value,
                        tsa_value,
                        igtf.*,
                        round((gtf_taxdivisa * tsa_value),get_decimals()) as imp_value
                    from dvfv
                    inner join igtf on igtf.gtf_docentry  = dvf_docentry and igtf.gtf_doctype = dvf_doctype
                    inner join tasa on tsa_date = dvf_docdate and tsa_currd = gtf_currency and tsa_curro = get_localcur()
                    where dvf_docentry = :DVF_DOCENTRY and dvf_doctype =  5 order by gtf_balancer desc";

        $resIgtf = $this->pedeo->queryTable($sqlIgtf, array(':DVF_DOCENTRY' => $Factura));
        $igtfTable = "<table width='50%' style='vertical-align: bottom;'>
        <tr style='border-bottom:1px solid #000; text-align: center;'>
            <th style='border-bottom:1px solid #000; text-align: center;'>Divisa</th>
            <th style='border-bottom:1px solid #000; text-align: center;'>Tasa</th>
            <th style='border-bottom:1px solid #000; text-align: center;'>Monto Divisa</th>
            <th style='border-bottom:1px solid #000; text-align: center;'>Monto Recibido</th>
            <th style='border-bottom:1px solid #000; text-align: center;'>V. impuesto</th>
            <th style='border-bottom:1px solid #000; text-align: center;'>Saldo de Factura</th>
        </tr>";

        $igtfTotal = 0;
        $impIgtf = 0;

        if (isset($resIgtf[0])) {
            $restante = $contenidoFV[0]['totaldoc'];
            foreach ($resIgtf as $key => $value) {
                $restante -= $value['gtf_value'];
                $igtfTable .= "<tr >
                                <td>{$value['gtf_currency']}</td>
                                <td>{$value['tsa_value']}</td>
                                <td>{$value['gtf_value']}</td>
                                <td>{$value['gtf_collected']}</td>
                                <td>{$value['imp_value']}</td>
                                <td>{$value['gtf_balancer']}</td>
                            </tr>";

                $igtfTotal += $value['gtf_collected'];
                $impIgtf += $value['gtf_taxdivisa'];
            }
            // print_r($contenidoFV[0]);exit;
            $igtfTable .= "<tr style=\"border-top:1px solid #000;\">
                            <td colspan=\"5\" style='border-top:1px solid #000; text-align: left;'><p>monto recibido en BS</p></td>
                            <td  style=\"text-align: center; border-top:1px solid #000;\"> " . round(($contenidoFV[0]['totaldoc'] - $igtfTotal), $DECI_MALES) . "</td>
                        </tr>";
        }

        $igtfTable .= "</table>";

        $consecutivo = '';

        if ($contenidoFV[0]['pgs_mpfn'] == 1) {
            $consecutivo = $contenidoFV[0]['numerodocumento'];
        } else {
            $consecutivo = $contenidoFV[0]['dvf_docnum'];
        }


        $DatosExportacion = '';

        if ($contenidoFV[0]['pgs_mde'] == 1) {

            $DatosExportacion = '<table width="100%">
                                    <tr>
                                        <th style="text-align: center;">Sello Nro.: <span>0</span></th>
                                        <th style="text-align: center;">Total Peso Bruto: <span>0</span></th>
                                    </tr>
                                    <tr>
                                        <th style="text-align: center;">Container Nro.: <span>0</span></th>
                                        <th style="text-align: center;">Contenedor: <span>0</span></th>
                                    </tr>
                                    <tr>
                                        <th style="text-align: center;">Naviera Buque: <span>0</span></th>
                                        <th style="text-align: center;">Fecha de Embarque: <span>0</span></th>
                                    </tr>
                                </table>';
        }

        $regimen = $igtfTable;

        $header = '<table width="100%">
                        <tr>
                            <th style="text-align: left;"><img src="/var/www/html/' . $company[0]['company'] . '/' . $empresa[0]['pge_logo'] . '" width ="100" height ="40"></img></th>
                            <th>
                                <p>' . $empresa[0]['pge_name_soc'] . '</p>
                                <p>' . $empresa[0]['pge_id_type'] . '</p>
                                <p>' . $empresa[0]['pge_add_soc'] . '</p>
                                <p>' . $empresa[0]['pge_phone1'] . '</p>
                                <p>' . $empresa[0]['pge_web_site'] . '</p>
                                <p>' . $empresa[0]['pge_mail'] . '</p>
                            </th>
                            <th>
                                <p>Factura DE VENTA</p>
                                <p class="">' . $consecutivo . '</p>
                            </th>
                        </tr>
                    </table>';

        $footer = '<table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
                        <tr>
                            <th style="text-align: center;"></th>
                        </tr>
                    </table>
                    <table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
                        <tr>
                            <th class="" width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
                        </tr>
                    </table>';


        $html = '<br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>

        <table class="" style="width:100%">
            <tr>
                <th style="text-align: left;">
                    <p class="">RIF: </p>
                </th>
                <th style="text-align: left;">
                    <p> ' . $contenidoFV[0]['nit'] . '</p>
                </th>
                <th style="text-align: right;">
                    <p class="">Factura: </p>
                </th>
                <th style="text-align: right;">
                    <p> ' . $consecutivo . '</p>
                </th>
            </tr>
            <tr>
                <th style="text-align: left;">
                    <p class="">NOMBRE: </p>
                </th>
                <th style="text-align: left;">
                    <p> ' . $contenidoFV[0]['cliente'] . '</p>
                </th>
                <th style="text-align: right;">
                    <p class="">CLIENTE: </p>
                </th>
                <th style="text-align: right;">
                    <p> ' . $contenidoFV[0]['nit'] . '</p>
                </th>
            </tr>
            <tr>
                <th style="text-align: left;">
                    <p class="">DIRECCIÓN: </p>
                </th>
                <th style="text-align: left;">
                    <p> ' . $contenidoFV[0]['direccion'] . '</p>
                </th>
                <th style="text-align: right;">
                    <p class=""></p>
                </th>
                <th style="text-align: right;">
                    <p></p>
                </th>
            </tr>
            <tr>
                <th style="text-align: left;">
                    <p class="">CIUDAD: </p>
                </th>
                <th style="text-align: left;">
                    <p> ' . $contenidoFV[0]['ciudad'] . '</p>
                </th>
                <th style="text-align: right;">
                    <p class="">OC: </p>
                </th>
                <th style="text-align: right;">
                    <p> ' . $VieneCotizacion . '</p>
                </th>
            </tr>
            <tr>
                <th style="text-align: left;">
                    <p class="">ESTADO: </p>
                </th>
                <th style="text-align: left;">
                    <p> ' . $contenidoFV[0]['estado'] . '</p>
                </th>
                <th style="text-align: right;">
                    <p class="">PEDIDO: </p>
                </th>
                <th style="text-align: right;">
                    <p>' . $VienePedido . '</p>
                </th>
            </tr>
            <tr>
                <th style="text-align: left;">
                    <p class=""></p>
                </th>
                <th style="text-align: left;">
                    <p></p>
                </th>
                <th style="text-align: right;">
                    <p class="">ENTREGA: </p>
                </th>
                <th style="text-align: right;">
                    <p>' . $VieneEntrega . '</p>
                </th>
            </tr>
            <tr>
                <th style="text-align: left;">
                    <p class=""></p>
                </th>
                <th style="text-align: left;">
                    <p></p>
                </th>
                <th style="text-align: right;">
                    <p class="">COND. PAGO: </p>
                </th>
                <th style="text-align: right;">
                    <p>' . $contenidoFV[0]['condpago'] . '</p>
                </th>
            </tr>
            <tr>
                <th style="text-align: left;">
                    <p class=""></p>
                </th>
                <th style="text-align: left;">
                    <p></p>
                </th>
                <th style="text-align: right;">
                    <p class="">FECHA DE EMISIÓN: </p>
                </th>
                <th style="text-align: right;">
                    <p>' . $this->dateformat->Date($contenidoFV[0]['fechadocumento']) . '</p>
                </th>
            </tr>
        </table>
        <br>
        <table width="100%">
            <tr class="">
                <th class="border_bottom" >CANT.</th>
                <th class="border_bottom">artículo</th>
                <th class="border_bottom">descrición</th>
                <th class="border_bottom">PRECIO UNITARIO</th>
                <th class="border_bottom">TOTAL</th>
            </tr>
            ' . $totaldetalle . '

        </table>
        <br>
        <table width="100%" style="vertical-align: bottom; font-family: serif; font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
            <tr>
                <th class="">
                    <p></p>
                </th>
            </tr>
        </table>
        <br>
        ' . $DatosExportacion . '
        <br>
        <br>
        <table width="100%">
            <tr>
                <th style="text-align: left;">Total Cantidad: <span>' . $TotalCantidad . '</span></th>
                <th style="text-align: left;">Total Peso: <span>' . $TotalPeso . " " . $contenidoFV[0]['um'] . '</span></th>
            </tr>
        </table>
        <table width="100%" style="border-bottom: solid 1px black;">
            <tr>
                <th style="text-align: left;"></span></th>
            </tr>
        </table>
        <table width="100%">
            <tr>
                <th>
                    <table width="100%" style="vertical-align: bottom;">
                        <tr><td>&nbsp;</td></tr>
                        <tr><td>&nbsp;</td></tr>
                        <tr><td>&nbsp;</td></tr>
                        <tr><td>&nbsp;</td></tr>
                        <tr><td>&nbsp;</td></tr>
                        <tr><td>&nbsp;</td></tr>
                        <tr><td>&nbsp;</td></tr>
                        <tr><td>&nbsp;</td></tr>
                        <tr>
                            <td style="text-align: left;" class="">
                                <p>' . $formatter->toWords($valorTotalDoc, 2) . " " . $contenidoFV[0]['nombremoneda'] . '</p>
                            </td>
                        </tr>
                        <br>
                        <tr>
                            <td style="text-align: left;" class="">
                                <p>COMENTARIOS:  ' . $contenidoFV[0]['comentarios'] . '</p>
                            </td>
                        </tr>
                        <br>
                    </table>
                </th>
                <th>
                    <table width="100%">
                        <tr>
                            <td style="text-align: right;">Sub Total: <span>' . $contenidoFV[0]['monedadocumento'] . " " . number_format($valorTotalSubtotal, $DECI_MALES, ',', '.') . '</span></td>
                        </tr>
                        <tr>
                            <td style="text-align: right;">Flete (E): <span>' . $contenidoFV[0]['monedadocumento'] . " 0" . '</span></td>
                        </tr>
                        <tr>
                            <td style="text-align: right;">Base Imponible: <span>' . $contenidoFV[0]['monedadocumento'] . " " . number_format($valorTotalBase, $DECI_MALES, ',', '.') . '</span></td>
                        </tr>
                        <tr>
                            <td style="text-align: right;">Monto total excento o exonerado:<span>' . $contenidoFV[0]['monedadocumento'] . " 0" . '</span></td>
                        </tr>
                        <tr>
                            <td style="text-align: right;">IVA 16% Sobre ' . number_format($contenidoFV[0]['base'], 2, ',', '.') . ': <span>' . $contenidoFV[0]['monedadocumento'] . " " . number_format($valorTotalIva, 2, ',', '.') . '</span></td>
                        </tr>
                        <tr>
                            <td style="text-align: right;">Valor Factura: <span>' . $contenidoFV[0]['monedadocumento'] . " " . number_format(($contenidoFV[0]['base'] + $valorTotalIva), 2, ',', '.') . '</span></td>
                        </tr>
                        <tr>
                            <td style="text-align: right;">IGTF: <span>' . $contenidoFV[0]['monedadocumento'] . ' ' . $contenidoFV[0]['dvf_igtf'] . '</span></td>
                        </tr>
                        <tr>
                            <td style="text-align: right;">Valor Total: <span>' . $contenidoFV[0]['monedadocumento'] . " " . number_format($valorTotalDoc, $DECI_MALES, ',', '.') . '</span></td>
                        </tr>
                    </table>
                </th>
            </tr>
        </table>
        <br>
        <table width="100%" style="vertical-align: bottom;">
        <tr>
            <th style="text-align: justify;">
            <br>
            <p>NOTA: EL PAGO, RECIBIDO DE ESTE DOCUMENTO, EN MONEDA DISTINTA A LA DE CURSO LEGAL EN EL PAIS Y FUERA DEL SISTEMA
                BANCARIO, GENERA UN 3% POR CONCEPTO DE IMPUESTOS A LAS GRANDES TRANSACCIONES FINANCIERAS (IGTF) CONSIDERANDO
                LO ESTABLECIDO EN LOS ART. 4 NUMERAL 6 , ART. 24, AMBOS DE LA GACETA 6.687 Y ART.1 DE LA GACETA 42.339.
                </p>
            </th>
        </tr>
        </table>
        <br>
        ' . $regimen . '
        <br>
        <br>
        <table width="100%" style="vertical-align: bottom;">
            <tr>
                <th style="text-align: justify;">
                    <span>' . $CommentFinal[0]['cdm_comments'] . '</span>
                </th>
            </tr>
            <br>
            <br>
        </table>';
        // print_r($html);exit();die(); 
        $stylesheet = file_get_contents(APPPATH . '/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);
        //print_r($html);exit();die();

        $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
        $mpdf->Output('/var/www/html/Api/RestFullSerpent/application/controllers/temp/'.$name_file,'F');

        return;

    }
    //NOMBRE DE ARCHIVO DE PDF
    private function nameFiles($prefijo,$nit,$code_dian,$año,$num_envio,$ext)
    {
        $name_file = "";
        $cant_ceros = 10 - strlen($nit);
        $ceros = 0;
        for ($i=0; $i < strlen($cant_ceros); $i++) { 
            # code...
            $ceros += strlen($cant_ceros).$ceros;
        }

        $cant_ceros = 8 - strlen($num_envio);
        $ceros1 = 0;
        for ($i=0; $i < strlen($num_envio); $i++) { 
            # code...
            $ceros1 += strlen($num_envio).$ceros1;
        }

        $name_file = $prefijo.$ceros.$nit.$code_dian.$año.$ceros1.$num_envio.'.'.$ext;
        return $name_file;
    }
    //CREA DIRECCTORIO EN FTP FE
    private function createDirFtp($fpath)
    {
        
        $connect = @ftp_connect(FTP_IP);
        $login = @ftp_login($connect,FTP_USER,FTP_PASSWORD);
        @ftp_pasv($connect, true);
        $raiz = "";

        if ($login){

            @ftp_chdir($connect,ROUTE_REMOTE);

            $parts = explode('/',$fpath);

            foreach ($parts as $key => $value) {

                if(!@ftp_chdir($connect,$value)){

                    $raiz = @ftp_mkdir($connect,$value);
                    @ftp_chdir($connect,$value);
                    @ftp_chmod($connect,0777,$raiz);
                }
            }

        }

        return $raiz;
    }
    //ENVIA ARCHIVO PDF
    private function createFileFtp($ruta,$file)
    {

        $connect = @ftp_connect(FTP_IP);
        $login = @ftp_login($connect,FTP_USER,FTP_PASSWORD);
        $file_remote = $ruta.'/'.$file;
        $file_local = ROUTE_LOCAL.$file;
        @ftp_pasv($connect, true);

        if ($login){

            @ftp_put($connect,$file_remote,$file_local,FTP_BINARY,0);
            @ftp_chmod($connect,0777,$file_remote);
            @ftp_close($connect);

        }

        return;
    }
    //ELIMINA ARCHIVO CARPETA TEMPORAL
    private function deleteFileLocal($file)
    {
        unlink(ROUTE_LOCAL.$file);
    }
}
