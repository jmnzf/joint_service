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

    }

    //INSERTAR DATOS DE SN EN FE
    public function insertBusiness_post()
    {

        $Data = $this->post();

        //SQL VISTA DE TERCEROS
        $sqlTercero = "SELECT * FROM terceros";

        //RETORNO DE DATOS DE FacturaS
        $resSqlTercero = $this->pedeo->queryTable($sqlTercero, array());

        //VARIABLE PARA ALMACENAR REGIMEN
        $regimen = "";

            

                if (isset($resSqlTercero[0])) {
                    $this->fe->trans_begin();
                    foreach ($resSqlTercero as $key => $value1) {

                        //SQL PARA DATOS DE TERCEROS FE
                        $sqlBusiness = "SELECT * FROM \"Tercero\" WHERE \"CodigoTercero\" = :CodigoTercero";
                        //RETORNO DE DATOS DE EMPRESA
                        $resBusiness = $this->fe->queryTable($sqlBusiness, array(':CodigoTercero' => $value1['codigo_sn1']));

                        if(count($resBusiness) > 0){
                            if ($value1['codigo_regimen'] == 'GC') {

                                $regimen = 'O-13';
                            } else if ($value1['codigo_regimen'] == 'AR') {
    
                                $regimen = 'O-15';
                            } else if ($value1['codigo_regimen'] == 'RS') {
    
                                $regimen = 'O-23';
                            } else {
    
                                $regimen = 'R-99-PN';
                            }

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
                                            'data'   => $resUpdateDireccion,
                                            'mensaje'    => 'No se pudo actualizar la direccion'
                                        );
    
                                        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
    
                                        return;
                                    }
                                
                            } else {
    
                                    $this->fe->trans_rollback();
    
                                    $respuesta = array(
                                        'error'   => true,
                                        'data'   => $resUpdateTercero,
                                        'mensaje'    => 'No se pudo actualizar el tercero'
                                    );
    
                                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
    
                                    return;
                                }
    
                                $respuesta = array(
                                    'error'   => false,
                                    'data'   => $resUpdateTercero,
                                    'mensaje'    => 'Datos Actualizados correctamente'
                                );
                            } else {
                                      
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
                                    ':CodigoTipoRegimen' => isset($regimen) ? $regimen : NULL,
                                    ':CodigoTipoIdentificacion' => isset($value1['tipo_doc']) ? $value1['tipo_doc'] : NULL,
                                    ':NumeroIdentificacion' => isset($value1['codigo_sn1']) ? $value1['codigo_sn1'] : NULL,
                                    ':DV' => isset($value1['codigo_sn1']) ? $this->calcularDigitoV($value1['codigo_sn1']) : 0,
                                    ':NombreTercero' => isset($value1['nombre_sn']) ? $value1['nombre_sn'] :NULL,
                                    ':Email' => isset($value1['correo']) ? $value1['correo'] : NULL,
                                    ':EmailRespuesta' => isset($value1['correo']) ? $value1['correo'] : NULL,
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
                                            'data'   => $resInsertDireccion,
                                            'mensaje'    => 'No se pudo insertar la direccion'
                                        );
            
                                        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
            
                                        return;
                                    }
                                } else {
            
                                    $this->fe->trans_rollback();
            
                                    $respuesta = array(
                                        'error'   => true,
                                        'data'   => $resInsertTercero,
                                        'mensaje'    => 'No se pudo insertar el tercero'
                                    );
            
                                    $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
            
                                    return;
                                }
            
            
                                $respuesta = array(
                                    'error'   => false,
                                    'data'   => $resInsertTercero,
                                    'mensaje'    => 'Datos Guardados con Exito - Tablas(Tercero,DireccionCliente)'
                                );
                            }

                        }

                        

                        
                    }
                
            

            $this->fe->trans_commit();
        

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
        $sqlInvoices = "SELECT * FROM factura_cab";
        //RETORNO DE DATOS DE FacturaS
        $resSqlInvoices = $this->pedeo->queryTable($sqlInvoices, array());
        //VALIDAR SI HAY DATOS DE Factura
        if (isset($resSqlInvoices[0]) && !empty($resSqlInvoices[0])) {

            foreach ($resSqlInvoices as $key => $value) {
                set_time_limit(300);

                $sqlInvoicesFE = " SELECT \"Numero\" FROM \"Factura\" WHERE \"Numero\" = :Factura";
                $resSqlInvoicesFE = $this->fe->queryTable($sqlInvoicesFE, array(':Factura' => $value['numero_fac']));

                $direccion = "SELECT \"Id_DireccionCliente\",\"Direccion\" FROM \"DireccionCliente\" WHERE \"CodigoTercero\" = :CodigoTercero";
                $resDireccion = $this->fe->queryTable($direccion, array(':CodigoTercero' => $value['codigo_sn1']));

                if (!count($resSqlInvoicesFE) > 0) {

                    $this->fe->trans_begin();
                    //VALIDAR MONEDA DIAN
                    $moneda = "";
                    if(isset($value['moneda']) && !empty($value['moneda']) && $value['moneda'] == 'BS'){
                        //BOLIVAR FUERTE DE VENEZUELA
                        $moneda = 'VEF';
                    }else if (isset($value['moneda']) && !empty($value['moneda']) && $value['moneda'] == 'USD'){
                        //DOLAR AMERICANO
                        $moneda = 'USD';
                    }else if (isset($value['moneda']) && !empty($value['moneda']) && $value['moneda'] == 'COP $'){
                        //PESOS COLOMBIANOS
                        $moneda = 'COP';
                    }
                    //INICIAR TRANSACCION
                    //INSERTAR DATOS A TABLA DE Factura BD FE
                    $valorLetra = $formatter->toWords($value['total'], 2);
                    //INSERTAR DATOS DE CABECERA DE Factura A BD FE
                    $insertFactura = "INSERT INTO \"Factura\" (\"CodigoEmpresa\",\"Sucursal\",\"CodigoResolucion\",\"Tipo\",\"Numero\",
                    \"FechaFactura\",\"CodigoTercero\",\"CodigoDireccionTercero\",\"DireccionEntrega\",\"CodigoFormaPago\",\"CodigoVendedor\",\"NumeroCuota\",
                    \"Descripcion\",\"FechaEntrega\",\"ValorSubTotal\",\"ValorImpuestos\",\"ValorRetencion\",\"ValorTotal\",\"ValorInicial\",
                    \"ValorLetras\",\"CodigoMoneda\",\"TasaCambio\",\"TipoFactura\",\"NumeroFactura\",\"TipoSoporte\",\"NumeroSoporte\",\"TipoRespaldo\",
                    \"NumeroRespaldo\",\"Usuario\",\"Estado\",\"Clase\")
                    VALUES (:CodigoEmpresa,:Sucursal,:CodigoResolucion,:Tipo,:Numero,:FechaFactura,:CodigoTercero,:CodigoDireccionTercero,
                    :DireccionEntrega,:CodigoFormaPago,:CodigoVendedor,:NumeroCuota,:Descripcion,:FechaEntrega,:ValorSubTotal,:ValorImpuestos,:ValorRetencion,
                    :ValorTotal,:ValorInicial,:ValorLetras,:CodigoMoneda,:TasaCambio,:TipoFactura,:NumeroFactura,:TipoSoporte,:NumeroSoporte,
                    :TipoRespaldo,:NumeroRespaldo,:Usuario,:Estado,:Clase)";

                    $resInsertFactura = $this->fe->insertRow($insertFactura, array(
                        ':CodigoEmpresa' =>  $resEmpresa[0]['CodigoEmpresa'],
                        ':Sucursal' =>  1,
                        ':CodigoResolucion' => '000',
                        ':Tipo' => isset($value['prefijo']) ? $value['prefijo'] : NULL,
                        ':Numero' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                        ':FechaFactura' => isset($value['fecha_contab']) ? $value['fecha_contab'] : NULL,
                        ':CodigoTercero' => isset($value['codigo_sn1']) ? $value['codigo_sn1'] : NULL,
                        ':CodigoDireccionTercero' => isset($resDireccion[0]['Id_DireccionCliente']) ? $resDireccion[0]['Id_DireccionCliente'] : NULL,
                        ':DireccionEntrega' => isset($resDireccion[0]['Direccion']) ? $resDireccion[0]['Direccion'] : NULL,
                        ':CodigoFormaPago' => is_numeric($value['tipo_pago']) ? $value['tipo_pago'] : 0,
                        ':CodigoVendedor' => isset($value['id_vendedor']) && $value['id_vendedor'] != '0' ? $value['id_vendedor'] : $resEmpresa[0]['NitEmpresa'],
                        ':NumeroCuota' => '0',
                        ':Descripcion' => isset($value['comentarios']) ? $value['comentarios'] : NULL,
                        ':FechaEntrega' => isset($value['fecha_contab']) ? $value['fecha_contab'] : NULL,
                        ':ValorSubTotal' => is_numeric($value['base']) ? $value['base'] : 0,
                        ':ValorImpuestos' => is_numeric($value['total_iva']) ? $value['total_iva'] : 0,
                        ':ValorRetencion' => is_numeric($value['valor_ret']) ? $value['valor_ret'] : 0,
                        ':ValorTotal' => is_numeric($value['total']) ? $value['total'] : 0,
                        ':ValorInicial' => 0,
                        ':ValorLetras' => $valorLetra,
                        ':CodigoMoneda' => isset($moneda) ? $moneda : NULL,
                        ':TasaCambio' => 0,
                        ':TipoFactura' => isset($value['prefijo']) ? $value['prefijo'] : NULL,
                        ':NumeroFactura' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                        ':TipoSoporte' => '',
                        ':NumeroSoporte' => '',
                        ':TipoRespaldo' => '',
                        ':NumeroRespaldo' => 0,
                        ':Usuario' => 1,
                        ':Estado' => 1,
                        ':Clase' => isset($value['clase']) ? $value['clase'] : NULL
                    ));

                    if (is_numeric($resInsertFactura) && $resInsertFactura > 0) {

                        //INSERTAR INFORMACION TABLA A PROCESAR FACTURACION ELECTRONICA
                        $insertFE = "INSERT INTO \"FacturaElectronica\" (\"CodigoEmpresa\",\"Sucursal\",\"Tipo\",\"Numero\",\"CodigoTercero\",
                        \"FechaFactura\",\"TipoSoporte\",\"NumeroSoporte\",\"Usuario\",\"Estado\",\"Clase\")
                        VALUES (:CodigoEmpresa,:Sucursal,:Tipo,:Numero,:CodigoTercero,:FechaFactura,:TipoSoporte,:NumeroSoporte,:Usuario,
                        :Estado,:Clase)";
                        $resInsertFE = $this->fe->insertRow($insertFE, array(
                            ':CodigoEmpresa' => $resEmpresa[0]['CodigoEmpresa'],
                            ':Sucursal' => 1,
                            ':Tipo' => isset($value['prefijo']) ? $value['prefijo'] : NULL,
                            ':Numero' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                            ':CodigoTercero' => isset($value['codigo_sn1']) ? $value['codigo_sn1'] : NULL,
                            ':FechaFactura' => date('Y-m-d'),
                            ':TipoSoporte' => isset($value['prefijo']) ? $value['prefijo'] : NULL,
                            ':NumeroSoporte' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                            ':Usuario' => 1,
                            ':Estado' => 0,
                            ':Clase' => isset($value['clase']) ? $value['clase'] : NULL
                        ));

                        if (is_numeric($resInsertFE) && $resInsertFE > 0) {

                            //INSERTAR TABLA IMPUESTO
                            $traeIva = 0;
                            $traeRetencion = 0;
                            $codigoIva = 0;

                            if (is_numeric($value['total_iva']) && $value['total_iva'] >= 0){
                                $traeIva = 1;

                                if(is_numeric($value['total_iva']) && $value['total_iva'] == 0){
                                    $codigoIva = 0;
                                }else {
                                    $codigoIva = $value['code_iva'];
                                }
                                
                                if(is_numeric($value['valor_ret']) && $value['valor_ret'] > 0){
                                    $traeRetencion = 1;
                                }
                            }
                            if($traeIva > 0){
                                $insertImpuesto = "INSERT INTO \"FacturaImpuesto\" (\"CodigoEmpresa\",\"Sucursal\",\"TipoFactura\",\"NumeroFactura\",
                                \"CodigoFactura\",\"ClaseImpuesto\",\"ValorBase\",\"Porcentaje\",\"ValorImpuesto\",\"Usuario\",\"Estado\",\"CodigoImpuesto\")
                                VALUES (:CodigoEmpresa,:Sucursal,:TipoFactura,:NumeroFactura,:CodigoFactura,:ClaseImpuesto,:ValorBase,:Porcentaje,:ValorImpuesto,
                                :Usuario,:Estado,:CodigoImpuesto)";

                                $resInsertImpuesto = $this->fe->insertRow($insertImpuesto, array(
                                    ':CodigoEmpresa' => $resEmpresa[0]['CodigoEmpresa'],
                                    ':Sucursal' => 1,
                                    ':TipoFactura' => $value['prefijo'],
                                    ':NumeroFactura' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                                    ':CodigoFactura' => is_numeric($resInsertFactura) ? $resInsertFactura : 0,
                                    ':ClaseImpuesto' => 'IVA',
                                    ':ValorBase' => is_numeric($value['base_iva']) ? $value['base_iva'] : 0,
                                    ':Porcentaje' => is_numeric($codigoIva) ? $codigoIva : 0,
                                    ':ValorImpuesto' => is_numeric($value['total_iva']) ? $value['total_iva'] : 0,
                                    ':Usuario' => 1,
                                    ':Estado' => 1,
                                    ':CodigoImpuesto' => '01'
                                ));

                                if($traeRetencion >= 0){

                                    $insertImpuesto = "INSERT INTO \"FacturaImpuesto\" (\"CodigoEmpresa\",\"Sucursal\",\"TipoFactura\",\"NumeroFactura\",
                                    \"CodigoFactura\",\"ClaseImpuesto\",\"ValorBase\",\"Porcentaje\",\"ValorImpuesto\",\"Usuario\",\"Estado\",\"CodigoImpuesto\")
                                    VALUES (:CodigoEmpresa,:Sucursal,:TipoFactura,:NumeroFactura,:CodigoFactura,:ClaseImpuesto,:ValorBase,:Porcentaje,:ValorImpuesto,
                                    :Usuario,:Estado,:CodigoImpuesto)";
    
                                    $resInsertImpuesto = $this->fe->insertRow($insertImpuesto, array(
                                        ':CodigoEmpresa' => $resEmpresa[0]['CodigoEmpresa'],
                                        ':Sucursal' => 1,
                                        ':TipoFactura' => $value['prefijo'],
                                        ':NumeroFactura' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                                        ':CodigoFactura' => is_numeric($resInsertFactura) ? $resInsertFactura : 0,
                                        ':ClaseImpuesto' => 'ReteFuente',
                                        ':ValorBase' => is_numeric($value['base_ret']) ? $value['base_ret'] : 0,
                                        ':Porcentaje' => is_numeric($value['porcentaje_ret']) ? $value['porcentaje_ret'] : 0,
                                        ':ValorImpuesto' => is_numeric($value['valor_ret']) ? $value['valor_ret'] : 0,
                                        ':Usuario' => 1,
                                        ':Estado' => 1,
                                        ':CodigoImpuesto' => '02'
                                    ));
                                }
                            }

                            if (is_numeric($resInsertImpuesto) && $resInsertImpuesto > 0) {

                                //CONSULTA PARA DETALLE
                                $sqlInvoicesDet = "SELECT * FROM Factura_det WHERE numero_fac = :numero_fac";
                                $resSqlInvoicesDet = $this->pedeo->queryTable($sqlInvoicesDet, array(':numero_fac' => $value['numero_fac']));

                                foreach ($resSqlInvoicesDet as $key => $value1) {

                                    if ($value['numero_fac'] == $value1['numero_fac']) {

                                        //INSERTAR TABLA IMPUESTO
                                        $traeIva = 0;
                                        $codigoIva = 0;

                                        if (is_numeric($value1['valor_iva']) && $value1['valor_iva'] > 0){
                                            $traeIva = 1;
                                        }

                                        if($traeIva > 0){
                                            $codigoIva = $value['code_iva'];
                                        }
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
                                            ':Tipo' => isset($value['prefijo']) ? $value['prefijo'] : NULL,
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
                                            ':PorcentajeIva' => is_numeric($codigoIva) ? $codigoIva : 0,
                                            ':ValorIva' => is_numeric($value1['valor_iva']) ? $value1['valor_iva'] : 0,
                                            ':PorcentajeRetencion' => is_numeric($value1['porcentaje_ret']) ? $value1['porcentaje_ret'] : 0,
                                            ':ValorRetencion' => is_numeric($value1['valor_ret']) ? $value1['valor_ret'] : 0,
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
                                                'data'   => $resInsertDetalleFactura,
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
                                    'data'   => $resInsertImpuesto,
                                    'mensaje'    => 'No se pudo insertar el detalle impuesto de la Factura'
                                );

                                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                                return;
                            }

                            
                        } else {

                            $this->fe->trans_rollback();

                            $respuesta = array(
                                'error'   => true,
                                'data'   => $resInsertFE,
                                'mensaje'    => 'No se pudo insertar el dato de la tabla Factura electronica'
                            );

                            $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                            return;
                        }
                    } else {

                        $this->fe->trans_rollback();

                        $respuesta = array(
                            'error'   => true,
                            'data'   => $resInsertFactura,
                            'mensaje'    => 'No se pudo insertar la Factura'
                        );

                        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

                        return;
                    }

                    $respuesta = array(
                        'error'   => false,
                        'data'   => $resInsertFactura,
                        'mensaje'    => 'Datos Guardados con Exito - Tablas (Factura,FacturaDetalle,FacturaElectronica,FacturaImpuesto)'
                    );

                    $this->fe->trans_commit();
                }else {

                    $respuesta = array(
                        'error'   => true,
                        'data'   => [],
                        'mensaje'    => 'No se encotraron facturas para insertar'
                    );
                }
                
            }

            
        }else {
            $respuesta = array(
                'error'   => true,
                'data'   => [],
                'mensaje'    => 'No se encontraron facturas para insertar'
            );

        }

        $this->response($respuesta);
    }
    //INSERTAR DATOS DE NC PARA FACTURA DEVOLUCION
    public function insertInvoicesNc_post(){
        //DATOS DE EMPRESA FE
        $sqlEmpresa = "SELECT * FROM \"Empresa\"";
        $resEmpresa = $this->fe->queryTable($sqlEmpresa, array());
        //CONSULTA PARA TRAER FACTURAS
        $invoice = "SELECT * FROM factura_cab";
        $resInvoice = $this->pedeo->queryTable($invoice,array());

        if(isset($resInvoice[0])){
            foreach ($resInvoice as $key => $value) {
                //INSERTAR EN TABLA FACTURA DEVOLUCION SI EL TIPO DE LA CLASE ES NC
                if($value['clase'] == "NC"){
                            
                    //CONSULTAR CUFE DE FACTURA
                    $sqlCufe = "SELECT * FROM \"FacturaElectronica\" WHERE \"Numero\" = :Numero AND coalesce(\"CUFE\",'') != ''";
                    $resCufe = $this->fe->queryTable($sqlCufe,array(':Numero' => $value['numero_fac']));

                    if(!count($resCufe) && $resCufe > 0){

                        $sqlFD = "SELECT * FROM \"FacturaDevolucion\" WHERE \"Numero\" = :Numero";
                        $resSqlFD = $this->fe->queryTable($sqlFD,array(':Numero' => $value['numero_fac']));

                        if(!count($resSqlFD) > 0){
                            $this->fe->trans_begin();
                            //INSERT 
                            $insertFD = "INSERT INTO \"FacturaDevolucion\" (\"CodigoEmpresa\",\"Sucursal\",\"Tipo\",\"Numero\",\"CodigoDevolucion\",\"Tipofactura\",
                            \"Numerofactura\",\"CUFEFactura\",\"TipoRespaldo\",\"NumeroRespaldo\",\"Usuario\",\"Estado\")
                            VALUES(:CodigoEmpresa,:Sucursal,:Tipo,:Numero,:CodigoDevolucion,:Tipofactura,:Numerofactura,:CUFEFactura,:TipoRespaldo,:NumeroRespaldo,
                            :Usuario,:Estado)";

                            $resinsertFD = $this->fe->insertRow($insertFD,array(
                                ':CodigoEmpresa' => $resEmpresa[0]['CodigoEmpresa'],
                                ':Sucursal' => 1,
                                ':Tipo' => isset($value['prefijo']) ? $value['prefijo'] : NULL,
                                ':Numero' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                                ':CodigoDevolucion' => isset($value['numero_fac']) ? $value['numero_fac'] : NULL,
                                ':Tipofactura' => isset($value['clase']) ? $value['clase']: NULL,
                                ':Numerofactura' => isset($value['factura_r']) ? $value['factura_r'] :NULL,
                                ':CUFEFactura' => isset($resCufe[0]['CUFE']) ? $resCufe[0]['CUFE']: NULL,
                                ':TipoRespaldo' => isset($value['factura_r']) ? $value['factura_r'] : NULL,
                                ':TipoRespaldo' => isset($value['factura_r']) ? $value['factura_r'] : NULL,
                                ':NumeroRespaldo' => isset($value['factura_r']) ? $value['factura_r'] : NULL,
                                ':Usuario' => 1,
                                ':Estado' => 1
                            ));

                            if(is_numeric($resinsertFD) && $resinsertFD > 0){

                            }else {
                                $this->fe->trans_rollback();

                                $respuesta = array(
                                    'error'   => true,
                                    'data'   => $resinsertFD,
                                    'mensaje'    => 'No se pudo insertar la factura devolucion de la Factura'
                                );
                                $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
                                return;
                            }
                            $this->fe->trans_commit();
                            $respuesta = array(
                                'error'   => true,
                                'data'   => [],
                                'mensaje'    => 'Datos insertados en la factura devolucion'
                            );
                        }
                        
                    }else {
                        $respuesta = array(
                            'error'   => true,
                            'data'   => [],
                            'mensaje'    => 'No se encontraron notas creditos'
                        );
                    }
                    
                }
            }
        }
        $this->response($respuesta);
        
    }
    //INSERTAR CUFE Y RESPUESTA DE LA DIAN EN SERPENT
    public function responseInvoice_post()
    {
        //DECLARAR VARIABLE DE RESPUESTA
        $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => 'No se encontraron datos en la busqueda'
        );
        //INICIO DE CONSULTA EN BD FE
        $sql = "SELECT * FROM \"FacturaElectronica\"";
        $resSql = $this->fe->queryTable($sql,array());

        if(isset($resSql[0])){
            
            $this->pedeo->trans_begin();
            foreach ($resSql as $key => $value) {
                //INICIO DE UPDATE RESPUESTA A SERPENT
                $update = "UPDATE dvfv SET dvf_cufe = :dvf_cufe, dvf_response_dian = :dvf_response_dian, dvf_send_dian = :dvf_send_dian WHERE dvf_docnum = :dvf_docnum";
                $resUpdate = $this->pedeo->updateRow($update,array(
                    ':dvf_cufe' => $value['CUFE'],
                    ':dvf_response_dian' => $value['Respuesta'],
                    ':dvf_send_dian' => 1,
                    ':dvf_docnum' => $value['Numero']
                ));

                if(is_numeric($resUpdate) && $resUpdate > 0){

                }else{

                    $this->pedeo->trans_rollback();

                    $respuesta = array(
                        'error' => true,
                        'data' => $resUpdate,
                        'mensaje' => 'No se encontraron datos en la busqueda'
                    );
                }

                $respuesta = array(
                    'error' => false,
                    'data' => $resUpdate,
                    'mensaje' => 'Facturas actualizadas'
                );

            }

            $this->pedeo->trans_commit();

            
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
}
