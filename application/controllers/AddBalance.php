<?php
// CONTROLADOR GENERICO
// defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class AddBalance extends REST_Controller {

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

    // ACUMULA EL SALDO DE LOS DEBITOS O CREDITOS SEGUN EL MOVIMIENTO DE LA CUENTA
    // PARA EFECTO DEL CIERRE CONTABLE
    public function addBalance_post() {
        // $periodo ==  ID DEL PERIODO
        // $monto = VALOR A INCREMENTAR EN EL DEBITO O EL CREDITO
        // $cuenta = NUMERO DE CUENTA CONTABLE
        // $lado = INDICA SI VA EN EL CREDITO O EN EL DEBITO  1 PARA DEBITO 2 PARA CREDITO
        // $fecha = FECHA DE LA CONTABILIDAD DEL DOCUMENTO
        // $business =  EMPRESA
        // $branch = sucursal
        $this->pedeo->trans_begin();

        $contabilidad = "SELECT ac1_line_num,
                        ac1_debit,
                        ac1_credit,
                        ac1_doc_date,
                        ac1_account,
                        business,
                        branch,
                        case 
                            when ac1_debit > 0 then 1
                            when ac1_credit > 0 then 2
                            else 2
                        end as lado
                        from mac1
                        where sync = :sync";
        
        $resContabilidad = $this->pedeo->queryTable($contabilidad, array(":sync" => 1));

        if (!isset($resContabilidad[0])){


            $respuesta = array(
                "error"   => true,
                "data"    => [],
                "mensaje" => "No hay cuentas para actualizar"
                
            );

            return $this->response($respuesta);
        }

        //

        foreach ($resContabilidad as $key => $value) {

            // SE BUSCA EL SUBPERIODO EN BASE AL PERIODO CONTABLE

               
                 $monto =  $value['lado'] == 1 ? $value['ac1_debit'] :  $value['ac1_credit'];
                 $cuenta = $value['ac1_account'];
                 $lado = $value['lado'];
                 $fecha = $value['ac1_doc_date'];
                 $business =  $value['business'];
                 $branch = $value['branch'];

            $sqlSubPeriod = "SELECT pc1_id, pc1_period_id FROM bpc1 WHERE pc1_fic <= :fecha AND pc1_ffc >= :fecha";

            $resSubPeriod = $this->pedeo->queryTable($sqlSubPeriod, array(
                ':fecha'   => $fecha,
            ));


            if ( isset($resSubPeriod[0]) ) {

                $periodo = $resSubPeriod[0]['pc1_period_id'];

                // SE VERIFICA QUE EXISTA LA CUENTA EN EL REGISTRO CON EL PERIODO Y EL SUB PERIODO

                $sqlAcct = "SELECT * FROM abap  WHERE bap_period = :periodo and bap_subperiod = :subperiodo and bap_acount = :cuenta";
                $resAcct = $this->pedeo->queryTable($sqlAcct, array(
                    ':periodo' => $periodo,
                    ':subperiodo' => $resSubPeriod[0]['pc1_id'],
                    ':cuenta' => $cuenta
                ));

                $sqlUpdate = '';
                $resUpdate = 0;
                $sqlInsert = '';
                $resInsert = 0;
                $debito = 0;
                $credito = 0;

                

                // SI YA EXISTE SE ACTUALIZA
                if ( isset($resAcct[0]) ){

                    if ( $lado == 1 ){ // SE ACTUALIZA EL DEBITO
                        $sqlUpdate = "UPDATE abap set bap_debit = (bap_debit + :monto) WHERE bap_period = :periodo and bap_subperiod = :subperiodo and bap_acount = :cuenta";
                    } else { // SE ACTUALIZA EL CREDITO
                        $sqlUpdate = "UPDATE abap set bap_credit = (bap_credit + :monto) WHERE bap_period = :periodo and bap_subperiod = :subperiodo and bap_acount = :cuenta";
                    }

                    
                    $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
                        ':monto' => $monto,
                        ':periodo' => $periodo,
                        ':subperiodo' => $resSubPeriod[0]['pc1_id'],
                        ':cuenta' => $cuenta
                    ));

                    if ( is_numeric($resUpdate) && $resUpdate == 1){

                    }else{

                        $this->pedeo->trans_rollback();

                        $respuesta = array(
                            "error" => true,
                            "data" => $resUpdate,
                            "mensaje" => "No se pudo actualizar la cuenta: ".$cuenta
                            
                        );

                        $this->response($respuesta);
                        
                    }


                // SI NO EXISTE SE CREA
                } else {


                    if ( $lado == 1 ) { // SE INSERTA EN EL DEBITO
                        $debito = $monto;
                        $credito = 0;
                    } else { // SE INSERTA EN EL CREDITO
                        $credito = $monto;
                        $debito = 0;
                    }


                    $sqlInsert = "INSERT INTO abap(bap_period,bap_subperiod,bap_debit,bap_credit,bap_closed,bap_closureid,business,branch,bap_acount)VALUES(:bap_period,:bap_subperiod,:bap_debit,:bap_credit,:bap_closed,:bap_closureid,:business,:branch,:bap_acount)";
                    $resInsert = $this->pedeo->insertRow($sqlInsert, array(
                        
                        ':bap_period' => $periodo,
                        ':bap_subperiod' => $resSubPeriod[0]['pc1_id'],
                        ':bap_debit' => $debito,
                        ':bap_credit' => $credito,
                        ':bap_closed' => 0,
                        ':bap_closureid' => 0,
                        ':business' => $business,
                        ':branch' => $branch,
                        ':bap_acount' => $cuenta
                    ));

                    if (is_numeric($resInsert) && $resInsert > 0 ){
                        

                    }else{

                        $this->pedeo->trans_rollback();
                        
                        $respuesta = array(
                            "error" => true,
                            "data" => $resInsert,
                            "mensaje" => "No se pudo actualizar la cuenta: ".$cuenta
                        );

                        $this->response($respuesta);
                    }
                }

                

            } else {

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    "error" => true,
                    "data" => [],
                    "mensaje" => "No se encontro el sub periodo en el que se esta aplicando la contabilidad del documento actual"
                    
                );

                $this->response($respuesta);

            }

            //

            $updateMac = $this->pedeo->updateRow("UPDATE mac1 set sync = 2 WHERE ac1_line_num = :ac1_line_num", array(":ac1_line_num" => $value['ac1_line_num']));            
            
            if (is_numeric($updateMac) && $updateMac == 1){

            }else{

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    "error" => true,
                    "data" => [],
                    "mensaje" => "No se pudo actualizar la linea contable"
                    
                );

                $this->response($respuesta);
            }
        }


        $this->pedeo->trans_commit();

        $respuesta = array(
            'error' => false,
            'data' => [],
            'mensaje' => 'Proceso finalizado con exito'
        );

        $this->response($respuesta);
    }



}    
    
    
    
    
    
    
