<?php
// CONTROLADOR DEFINIDOM PARA VALIDAR TODO EL PRECESO REFERENTE A
// PERIODOS CONTABLES


//ESTADOS DE PERIODOS
// 1 Desbloqueado
// 2 Bloqueado Exepto para ventas
// 3 Cerrado

defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

// class DFValidateAccountingPeriods extends REST_Controller {
class awdawdawd extends REST_Controller {

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

  //Crear nueva numeracion
	public function ValidatePeriod($DateDoc,$DateCon,$DateVen,$TipoDoc){

    //SE QUEMA TIPO DOCUMENTO PARA VENTAS Y compras
    // 1 = VENTAS
    // 2 = COMPRAS

    $respuesta = array();

    $sql = "SELECT * FROM tbpc WHERE bpc_fip >= :bpc_fip AND bpc_ffp <= :bpc_ffp
            AND bpc_fic >= :bpc_fic AND bpc_ffc <= :bpc_ffc
            AND bpc_fiv >= :bpc_fiv AND bpc_ffv <= :bpc_ffv";


    $ressql = $this->pedeo->queryTable($sql, array(
      ':bpc_fip' => $DateCon,
      ':bpc_ffp' => $DateCon,
      ':bpc_fic' => $DateDoc,
      ':bpc_ffc' => $DateDoc,
      ':bpc_fiv' => $DateVen,
      ':bpc_fiv' => $DateVen
    ));

    if( isset($ressql[0]) ){

      $sql2 = "SELECT * FROM bpc1 WHERE pc1_fid >= :pc1_fid AND pc1_ffd <= :pc1_ffd
              AND pc1_fic >= :pc1_fic AND pc1_ffc <= :pc1_ffc
              AND pc1_fiv >= :pc1_fiv AND pc1_ffv <= :pc1_ffv";

      $ressql2 = $this->pedeo->queryTable($sql2, array(
        ':bpc_fid' => $DateDoc,
        ':bpc_ffd' => $DateDoc,
        ':bpc_fic' => $DateCon,
        ':bpc_ffc' => $DateCon,
        ':bpc_fiv' => $DateVen,
        ':bpc_fiv' => $DateVen
      ));


      if( isset($ressql2[0]) ){

        if( $ressql2[0]['pc1_status'] === 1){
          return $respuesta = array(
            'error' => false,
            'data'  => [],
            'mensaje' =>''
          );
        }else if( $ressql2[0]['pc1_status'] === 2 && $TipoDoc === 1){
          return  $respuesta = array(
            'error' => false,
            'data'  => [],
            'mensaje' =>''
          );
        }else if( $ressql2[0]['pc1_status'] === 3 ){
          return  $respuesta = array(
            'error' => true,
            'data'  => [],
            'mensaje' =>'Validar periodo contable'
          );
        }

      }else{
        $respuesta = array(
          'error' => true,
          'data'  => [],
          'mensaje' =>'Validar periodo contable'
        );
      }


    }else{
      $respuesta = array(
        'error' => true,
        'data'  => [],
        'mensaje' =>'Validar periodo contable'
      );
    }

    return $respuesta;
	}
}
