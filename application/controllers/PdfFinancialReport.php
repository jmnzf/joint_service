<?php

// GENERACION DE DOCUMENTOS EN PDF

defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('America/Bogota');
require_once(APPPATH.'/asset/vendor/autoload.php');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;
use Luecano\NumeroALetras\NumeroALetras;
use \PhpOffice\PhpSpreadsheet\Reader\Html;
use \PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PdfFinancialReport extENDs REST_Controller {

	private $pdo;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);
		$this->load->library('generic');

	}


	public function getFinancialReport_post(){

		$DECI_MALES =  $this->generic->getDecimals();

    $Data = $this->post();


    if(!isset($Data['informe']) OR !isset($Data['fi']) OR  !isset($Data['ff']) ){

      $respuesta = array(
         'error' => true,
         'data'  => [],
         'mensaje' =>'faltan parametros'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }



	  $formatter = new NumeroALetras();

    $mpdf = new \Mpdf\Mpdf(['setAutoBottomMargin' => 'stretch','setAutoTopMargin' => 'stretch']);

    //RUTA DE CARPETA EMPRESA
    $company = $this->pedeo->queryTable("SELECT main_folder company FROM PARAMS",array());

    if(!isset($company[0])){
	    $respuesta = array(
         'error' => true,
         'data'  => $company,
         'mensaje' =>'no esta registrada la ruta de la empresa'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

        //INFORMACION DE LA EMPRESA

        $empresa = $this->pedeo->queryTable("SELECT pge_id, pge_name_soc, pge_small_name, pge_add_soc, pge_state_soc, pge_city_soc,
        pge_cou_soc, CONCAT(pge_id_type,' ',pge_id_soc) AS pge_id_type , pge_web_site, pge_logo,
        CONCAT(pge_phone1,' ',pge_phone2,' ',pge_cel) AS pge_phone1, pge_branch, pge_mail,
        pge_curr_first, pge_curr_sys, pge_cou_bank, pge_bank_def,pge_bank_acct, pge_acc_type
        FROM pgem WHERE pge_id = :pge_id", array(':pge_id' => $Data['business']));

		if(!isset($empresa[0])){
			$respuesta = array(
	           'error' => true,
	           'data'  => $empresa,
	           'mensaje' =>'no esta registrada la información de la empresa'
	        );

          	$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

	        return;
		}

    $sqlSelect = " SELECT * from tmif where mif_docentry = :mif_docentry";
    $resSelect = $this->pedeo->queryTable($sqlSelect,array(":mif_docentry"=>$Data['informe']));

    if(!isset($resSelect[0])){
      $respuesta = array(
        'error' => true,
        'data'  => $resSelect,
        'mensaje' =>'Se encuentran datos relacionados a la busqueda'
     );

       $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

     return;
    }

		$sqlinforme = "SELECT * FROM mif1 WHERE if1_mif_id = :if1_mif_id";

		$resinforme = $this->pedeo->queryTable($sqlinforme,array(':if1_mif_id' => $Data['informe']));

		if(!isset($resinforme[0])){
			$respuesta = array(
				 'error' => true,
				 'data'  => $resinforme,
				 'mensaje' =>'No hay datos para procesar'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

    $grupo = '';
    $subgrupo = '';
    $cuentas = '';
    $bloque = '';
    $content = '';
    $acumuladoTotal = 0;
    foreach ($resinforme as $key => $value) {
      $acumuladoGrupo = 0;
			$grupo ='<table width="100%"><tr><th style="text-align: left;">'.$value['if1_group_name'].'</th></tr></table>';

      $groupName = $value['if1_group_name'];
      $content .= $grupo;


      $sqlsubgrupo = "SELECT * FROM mif2 WHERE mif2.if2_fi1_id = :if2_fi1_id";
      $ressubgrupo = $this->pedeo->queryTable($sqlsubgrupo,array(':if2_fi1_id' => $value['if1_docentry']));


      if(isset($ressubgrupo[0])){

        foreach ($ressubgrupo as $key => $value) {

          $subgrupo = '<table width="100%"><tr ><th class="fondo" style ="text-align: left;">'.$value['if2_subgroup_name'].'</th></table>';

          $sqlcuentas = 'SELECT mif3.* , dacc.acc_name FROM mif3 INNER JOIN dacc ON mif3.if3_account = cast(dacc.acc_code as varchar) WHERE if3_if2_id = :if3_if2_id';
          $rescuentas = $this->pedeo->queryTable($sqlcuentas, array(':if3_if2_id' => $value['if2_docentry']));
          $comportamiento = $value['if2_conduct'];
          if( isset($rescuentas[0]) ){
            $tablacuentas = '<table width="100%">';
            $cuentas = '';
            $totalmonto = '';
            $acumulado = 0;
            foreach ($rescuentas as $key => $value) {

                $sqlmontocuenta = 'SELECT ABS(SUM(ac1_debit - ac1_credit)) as totalcuenta FROM mac1 WHERE ac1_doc_date BETWEEN :fi AND :ff AND ac1_account = :ac1_account HAVING (SUM(ac1_debit) -  SUM(ac1_credit))  IS NOT NULL';
                $resmontocuenta = $this->pedeo->queryTable($sqlmontocuenta, array( ':fi' => $Data['fi'], ':ff' =>$Data['ff'], ':ac1_account' => $value['if3_account'] ));

                $cuentas .='<tr><td style="text-align: left;" width="50%">'.$value['acc_name'].'</td>';
                if(isset($resmontocuenta[0])){

                  $cuentas .='<td style="text-align: right;" width="50%">'.number_format($resmontocuenta[0]['totalcuenta'], $DECI_MALES, ',', '.').'</td></tr>';
                  $acumulado+=(abs($resmontocuenta[0]['totalcuenta'])*$comportamiento);
                }else{
                  $cuentas.='<td style="text-align: right;">'.number_format(0, $DECI_MALES, ',', '.').'</td></tr>';
                }

            }

            $tablacuentas .= $cuentas;

            $content.= $subgrupo.$tablacuentas.'</table>'.'
            <table width="100%"><tr style="border-top:1px solid #000;">
            <td style="text-align: right;" width="50%">TOTAL </td>
            <td style="border-top:1px solid #000; text-align: right;" width="50%">'.number_format($acumulado, $DECI_MALES, ',', '.').'</td>
            </tr>
            </table>';
          }
          $acumuladoGrupo += $acumulado;

        }
        $acumuladoTotal += $acumuladoGrupo;
      }
      $content.= '<table width="100%">
      <tr >
      <td style="text-align: right;" width="50%">TOTAL '.$groupName.'</td>
      <td  width="50%" style="text-align: right;">'.number_format($acumuladoGrupo, $DECI_MALES, ',', '.').'</td>
      </tr>
      </table>';
    }

    $content.= '<br><table width="100%">
    <tr >
    <td style="text-align: right;" width="50%">TOTAL </td>
    <td style="text-align: right; border-top:1px solid #000;" width="50%">'.number_format($acumuladoTotal, $DECI_MALES, ',', '.').'</td>
    </tr>
    </table>';



        $header = '
        <table width="100%">
	        <tr>
	            <th style="text-align: left;"><img src="/var/www/html/'.$company[0]['company'].'/'.$empresa[0]['pge_logo'].'" width ="100" height ="40"></img></th>
	            <th style="text-align: right; margin-left: 600px;">
	                <p>'.$resSelect[0]['mif_name'].'</p>
	            </th>
				<th>
					&nbsp;
					&nbsp;
				</th>
	        </tr>
        </table>';

        $footer = '
		    <table width="100%" style="vertical-align: bottom; font-family: serif;
		        font-size: 8pt; color: #000000; font-weight: bold; font-style: italic;">
		        <tr>
		            <th class="fondo" width="33%">Pagina: {PAGENO}/{nbpg}  Fecha: {DATE j-m-Y}  </th>
		        </tr>
		    </table>';

		    $html = $content;
        // print_r($html);
        // exit;


        $stylesheet = file_get_contents(APPPATH.'/asset/vendor/style.css');

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);


        $mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);


        $mpdf->Output('Doc.pdf', 'D');

  			$filename = 'Doc.pdf';

				header('Content-type: application/force-download');
				header('Content-Disposition: attachment; filename='.$filename);
	}

  private function getFinancialReportData($informe,$fi,$ff){

    $respuesta = array();

    $arrayobj = new ArrayObject();

    $sqlSelect = " SELECT * from tmif where mif_docentry = :mif_docentry";
    $resSelect = $this->pedeo->queryTable($sqlSelect,array(":mif_docentry"=>$informe));

    if(!isset($resSelect[0])){
      $respuesta = array(
        'error' => true,
        'data'  => $resSelect,
        'mensaje' =>'Se encuentran datos relacionados a la busqueda'
     );

       $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

     return;
    }



		$sqlinforme = "SELECT * FROM mif1 WHERE if1_mif_id = :if1_mif_id";

		$resinforme = $this->pedeo->queryTable($sqlinforme,array(':if1_mif_id' => $informe));
    $res2 = [];
		if(!isset($resinforme[0])){
			$respuesta = array(
				 'error' => true,
				 'data'  => $resinforme,
				 'mensaje' =>'No hay datos para procesar'
			);

			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

			return;
		}

    $acumuladoTotal = 0;
    foreach ($resinforme as $key => $value) {

      $id_group = $value['if1_docentry'];

      $grupo  = $value['if1_group_name'];

      $sqlsubgrupo = "SELECT * FROM mif2 WHERE mif2.if2_fi1_id = :if2_fi1_id";
      $ressubgrupo = $this->pedeo->queryTable($sqlsubgrupo,array(':if2_fi1_id' => $value['if1_docentry']));



      if(isset($ressubgrupo[0])){


        foreach ($ressubgrupo as $key => $value) {
          $sqlcuentas = 'SELECT mif3.* , dacc.acc_name,
                        coalesce((select coalesce(SUM(ac1_debit - ac1_credit),0)
                        from mac1
                        WHERE ac1_doc_date BETWEEN :fi AND :ff AND ac1_account  = if3_account::bigint
                        HAVING (SUM(ac1_debit) -  SUM(ac1_credit))  IS NOT NULL)::integer ,0)  as totalcuenta
                        from mif3
                        left JOIN dacc ON mif3.if3_account = cast(dacc.acc_code as varchar)
                        where if3_if2_id = :if3_if2_id';


          $rescuentas = $this->pedeo->queryTable($sqlcuentas, array(':if3_if2_id' => $value['if2_docentry'],":fi" =>$fi,":ff" =>$ff));

          if( isset($rescuentas[0]) ){
            $ressubgrupo[$key]['cuentas'] = $rescuentas;

            $totalcuentas = array_map(function($cuenta){
              return $cuenta['totalcuenta'];
            },$rescuentas);

            $acumuladoTotal += array_sum($totalcuentas);

          }
          $acumuladoTotal = $acumuladoTotal;
        }
        // print_r($acumuladoTotal);exit;
        $arrayobj->append(["grupo" => $grupo,"saldo"=>$acumuladoTotal, "subgrupos"=> $ressubgrupo]);
        // $acumuladoTotal += $acumuladoGrupo;
      }
      $acumuladoTotal = 0;
      $respuesta = array(
        'error' => false,
        'data'  => $arrayobj,
        'mensaje' =>''
     );
    }

    return $arrayobj;
  }

  public function finantialReport_post(){

    $Data = $this->post();

    if(!isset($Data['informe']) OR !isset($Data['fi']) OR  !isset($Data['ff']) ){

      $respuesta = array(
         'error' => true,
         'data'  => [],
         'mensaje' =>'faltan parametros'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlSelect = "SELECT mif_name,if1_group_name, if2_subgroup_name,concat(acc_code,' - ',acc_name) account,
                    coalesce(SUM(ac1_debit - ac1_credit),0) total_account
                from tmif
                join mif1 on if1_mif_id = mif_docentry
                join mif2 on if2_fi1_id = if1_docentry
                join mif3 on if3_if2_id = if2_docentry
                left join mac1 on if3_account = ac1_account::text and ac1_doc_date between :fi and :ff
                join dacc on acc_code  = if3_account::bigint
                where mif_docentry = :informe
                group by mif_name,if1_group_name,if2_subgroup_name,acc_code,acc_name
                order by if1_group_name";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(":informe"=>$Data['informe'], ":fi" =>$Data['fi'], ":ff" =>$Data['ff']));

    if(isset($resSelect[0])){
      $respuesta = array(
        'error' => false,
        'data'  => $resSelect,
        'mensaje' =>''
     );
    }else{
      $respuesta = array(
        'error' => true,
        'data'  => [],
        'mensaje' =>'Datos no encontrados'
     );
    }

    $this->response($respuesta);
  }

  public function exportExcel_post(){

		$DECI_MALES =  $this->generic->getDecimals();

    $Data = $this->post();
    $data = $this->getFinancialReportData($Data['informe'],$Data['fi'],$Data['ff']);
    // $data = json_encode($data);
    $htmlString = '
    <table>
      <tr ><th style="background: #9bc2e6;"><b>Grupo</b></th><th align="right" style="background: #9bc2e6;"><b>Saldo</b></th></tr>';
      $count = 0;
    foreach ($data as $key => $grupo) {
      $count++;
      $htmlString .= "<tr ><td style=\"background: #bdd7ee;\"><b>{$grupo['grupo']}</b></td><td align=\"right\"  style=\"background: #bdd7ee;\">".number_format($grupo['saldo'], $DECI_MALES, '.', ',')."</td></tr>";

      foreach ($grupo['subgrupos'] as $key => $subgrupo){
        $htmlString .= "<tr><td colspan=\"2\" style=\"background: #ddebf7;\"><b>{$subgrupo['if2_subgroup_name']}</b></td></tr>";
        $count++;
        foreach ($subgrupo['cuentas'] as $key => $cuenta){

          $total = ($cuenta['totalcuenta'] != 0 ) ? $cuenta['totalcuenta'] : number_format(0, $DECI_MALES, '.', ',');

          // var_dump($cuenta['totalcuenta']);
          $htmlString .= "<tr><td width=\"54\" align=\"left\">{$cuenta['if3_account']} - {$cuenta['acc_name']}</td><td width=\"20\" align=\"right\">{$total}</td></tr>";
          $count++;
        }
      }
    }
    $htmlString .= '</table>';
    $reader = new Html();

    $spreadsheet = $reader->loadFromString($htmlString);
    $spreadsheet->getActiveSheet()->getStyle('A1:B'.($count+1))->applyFromArray(
      array('borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
    ));

    $spreadsheet->getActiveSheet()->getStyle('B2:B'.($count))->getNumberFormat()->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);

    $filename ="doc.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
  }

}
