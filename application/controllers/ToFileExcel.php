<?php
// GENERA UN ARCHIVO DE EXCEL CON LA CONSULTA DADA
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH . '/libraries/REST_Controller.php');


use Restserver\libraries\REST_Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ToFileExcel extends REST_Controller
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


    public function createExcel_post(){

        $COLUMNS = [
                    'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
                    'AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ'
                   ];

        $EQUIVALE = array(
            'ote_id'         => 'ID',	
            'ote_code'       => 'CODIGO',
            'ote_createdate' => 'FECHA CREACION',	
            'ote_duedate'    => 'FECHA VENCIMIENTO',	
            'ote_createby'   => 'CREADO POR',	
            'ote_date'	     => 'FECHA',
            'ote_baseentry'	 => 'CODIGO DOCUMENTO',
            'ote_basetype'   => 'TIPO DOCUMENTO',
            'ote_docnum'     => 'NUMERO DOCUMENTO'
        );

        $Data = $this->post();

        $respuesta = array( 
            'error'  => true,
            'data'   => [],
            'mensaje'=>'Busqueda sin resultados'
        );


        $resSelect = $this->pedeo->queryTable("SELECT * FROM Lote", array());


        $campos = array_keys($resSelect[0]);


        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        
        for ($i=0; $i < count($campos) ; $i++) { 
            $sheet->setCellValue($COLUMNS[$i].'1', $EQUIVALE[$campos[$i]]);    
        }
        


        // $sqlSelect = $Data['sql'];


        // $resSelect = $this->pedeo->queryTable($sqlSelect, array());

        // if ( isset($resSelect[0])){

        // }else{

        //     $respuesta = array( 
        //         'error'=>true,
        //         'data'=>$resSelect,
        //         'mensaje'=>'Busqueda sin resultados'
        //     );
        // }


        $filename ="doc.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
    
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

    }
 
}
