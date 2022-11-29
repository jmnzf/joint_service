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

        $Data = $this->post();

      

        if ( !isset($Data['sql']) OR !isset($Data['equivalence'])){
            
            $respuesta = array( 
                'error'   => true,
                'data'    => [],
                'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);



        }

        $COLUMNS = [
                    'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
                    'AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ'
                   ];
        
                   
        $EQUIVALE = json_decode($Data['equivalence'], true);   


        $USEDCOLUMNS = [];

        

        $respuesta = array( 
            'error'  => true,
            'data'   => [],
            'mensaje'=>'Busqueda sin resultados'
        );


        $resSelect = $this->pedeo->queryTable($Data['sql'], array());

        if ( !isset($resSelect[0]) ){
                
            $respuesta = array( 
                'error'   => true,
                'data'    => [],
                'mensaje' => 'No hay resultados para la consulta enviada'
            );

            return $this->response($respuesta);

        }


        $campos = array_keys($resSelect[0]);


        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        
        for ($i=0; $i < count($campos) ; $i++) { 
            $sheet->setCellValue($COLUMNS[$i].'1', $EQUIVALE[$campos[$i]]);    
            array_push($USEDCOLUMNS, $COLUMNS[$i]);
        }

        $acc = 2; // SEGUNDA FILA YA LA PRIMERA SE RESERVA PARA LOS ENCABEZADOS
        for ($i=0; $i < count($resSelect); $i++) { 

            for ($a=0; $a < count($USEDCOLUMNS); $a++) { 

                $sheet->setCellValue($USEDCOLUMNS[$a].$acc, $resSelect[$i][$campos[$a]]);  

            }

            $acc++;
        }
        
        $filename ="doc.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
    
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

    }
 
}
