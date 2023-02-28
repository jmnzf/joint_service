<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class DocumentNumbering extends REST_Controller {

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
	public function createDocumentNumbering_post(){

      $Data = $this->post();

      if(!isset($Data['pgs_num_name']) OR
         !isset($Data['pgs_first_num']) OR
         !isset($Data['pgs_last_num']) OR
         !isset($Data['pgs_pref_num']) OR
         !isset($Data['pgs_cancel']) OR
         !isset($Data['pgs_is_due']) OR
         !isset($Data['pgs_doc_date']) OR
         !isset($Data['pgs_doc_due_date']) OR
         !isset($Data['pgs_enabled']) OR
         !isset($Data['business']) OR
         !isset($Data['branch'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      //VALIDAR QUE EL NUMERO INICIAL NO ESTE EN UN RAGO DE NUMERACION YA CREADO
      $validDoc = "SELECT * FROM pgdn WHERE pgs_id_doc_type = :doctype AND pgs_enabled = :status";
      $resValidDoc = $this->pedeo->queryTable($validDoc,array(
        ':status' => 1,
        ':doctype' => $Data['pgs_id_doc_type']
      ));
      if( isset( $resValidDoc[0] ) ){

          foreach ($resValidDoc as $key => $doc) {
            if( $Data['pgs_first_num'] >= $doc['pgs_first_num'] && $Data['pgs_first_num'] <= $doc['pgs_last_num'] ){
              $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'Ya existe una numeración para este tipo de documento con este rango'
              );

              $this->response($respuesta);

              return;
            }
          }

          
      }

      if(is_numeric($Data['pgs_first_num']) && is_numeric($Data['pgs_last_num'])){

        if($Data['pgs_first_num'] > $Data['pgs_last_num']){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'El numero inicial no puede ser mayor que el numero final'
              );

              $this->response($respuesta);

              return;

        }

      }else{
        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'Se requiere que el numero inicial y final sean validos'
          );

          $this->response($respuesta);

          return;
      }

      $sqlInsert = "INSERT INTO pgdn(pgs_id_doc_type, pgs_num_name, pgs_first_num, pgs_last_num, pgs_pref_num, pgs_cancel, pgs_is_due, pgs_doc_date,  pgs_doc_due_date, pgs_enabled, pgs_nextnum, pgs_doctype, pgs_mpfn, pgs_mde, business, branch)
                    VALUES(:Pgs_IdDocType,  :Pgs_NumName,  :Pgs_FirstNum,  :Pgs_LastNum,  :Pgs_PrefNum,  :Pgs_Cancel,  :Pgs_IsDue,  :Pgs_DocDate,  :Pgs_DocDueDate,  :Pgs_Enabled, :pgs_nextnum, :pgs_doctype, :pgs_mpfn, :pgs_mde, :business, :branch)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':Pgs_IdDocType'  => $Data['pgs_id_doc_type'],
            ':Pgs_NumName'    => $Data['pgs_num_name'],
            ':Pgs_FirstNum'   => $Data['pgs_first_num'],
            ':Pgs_LastNum'    => $Data['pgs_last_num'],
            ':Pgs_PrefNum'    => $Data['pgs_pref_num'],
            ':Pgs_Cancel'     => $Data['pgs_cancel'],
            ':Pgs_IsDue'      => $Data['pgs_is_due'],
            ':Pgs_DocDate'    => $Data['pgs_doc_date'],
            ':Pgs_DocDueDate' => $Data['pgs_doc_due_date'],
            ':Pgs_Enabled'    => $Data['pgs_enabled'],
						':pgs_nextnum'	  => ($Data['pgs_nextnum'] - 1),
						':pgs_doctype'		=> $Data['pgs_id_doc_type'],
						':pgs_mpfn'       => $Data['pgs_mpfn'],
						':pgs_mde'        => $Data['pgs_mde'],
            ':business'       => $Data['business'],
            ':branch'         => $Data['branch']

      ));

      if(is_numeric($resInsert) && $resInsert > 0){

            $respuesta = array(
              'error' 	=> false,
              'data' 		=> $resInsert,
              'mensaje' =>'Numeracion registrada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data' 		=> $resInsert,
              'mensaje'	=> 'No se pudo registrar la numeracion'
            );

      }

       $this->response($respuesta);
	}

  //Actualizar numeracion de documento
  public function updateDocumentNumbering_post(){

      $Data = $this->post();

      if(!isset($Data['pgs_id_doc_type']) OR
         !isset($Data['pgs_num_name']) OR
         !isset($Data['pgs_first_num']) OR
         !isset($Data['pgs_last_num']) OR
         !isset($Data['pgs_pref_num']) OR
         !isset($Data['pgs_cancel']) OR
         !isset($Data['pgs_is_due']) OR
         !isset($Data['pgs_doc_date']) OR
         !isset($Data['pgs_doc_due_date']) OR
         !isset($Data['pgs_enabled']) OR
         !isset($Data['pgs_id'])){

        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La informacion enviada no es valida'
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }

      if(is_numeric($Data['pgs_first_num']) && is_numeric($Data['pgs_last_num'])){

        if($Data['pgs_first_num'] > $Data['pgs_last_num']){

            $respuesta = array(
                'error' => true,
                'data'  => array(),
                'mensaje' =>'El numero inicial no puede ser mayor que el numero final'
              );

              $this->response($respuesta);

              return;

        }

      }else{
        $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'Se requiere que el numero inicial y final sean validos'
          );

          $this->response($respuesta);

          return;
      }
      if ($Data['pgs_doc_pre'] == 1) {
        $sqlSelect = "SELECT pgs_id, pgs_doc_pre FROM pgdn WHERE pgs_id_doc_type = :pgs_id_doc_type AND business = :business AND branch = :branch";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':pgs_id_doc_type' => $Data['pgs_id_doc_type'],':business' => $Data['business'], ':branch' => $Data['branch']));
        if (isset($resSelect[0])) {
          foreach ($resSelect as $key => $value) {
              $sqlUpdatePre = "UPDATE pgdn SET pgs_doc_pre = 0 WHERE pgs_id = :pgs_id";
              $resUpdate = $this->pedeo->updateRow($sqlUpdatePre, array(
                ':pgs_id'  => $value['pgs_id'],
              ));
          }
        }
      }

      $sqlUpdate = "UPDATE pgdn SET pgs_id_doc_type = :Pgs_IdDocType, pgs_num_name = :Pgs_NumName, pgs_first_num = :Pgs_FirstNum,
                    pgs_last_num = :Pgs_LastNum, pgs_pref_num = :Pgs_PrefNum, pgs_cancel = :Pgs_Cancel, pgs_is_due = :Pgs_IsDue,
                    pgs_doc_date = :Pgs_DocDate, pgs_doc_due_date = :Pgs_DocDueDate, pgs_enabled = :Pgs_Enabled, pgs_doctype = :pgs_doctype,
										pgs_mpfn = :pgs_mpfn, pgs_mde = :pgs_mde, pgs_doc_pre = :pgs_doc_pre
                    WHERE pgs_id = :Pgs_Id";


      $resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

            ':Pgs_IdDocType'  => $Data['pgs_id_doc_type'],
            ':Pgs_NumName'    => $Data['pgs_num_name'],
            ':Pgs_FirstNum'   => $Data['pgs_first_num'],
            ':Pgs_LastNum'    => $Data['pgs_last_num'],
            ':Pgs_PrefNum'    => $Data['pgs_pref_num'],
            ':Pgs_Cancel'     => $Data['pgs_cancel'],
            ':Pgs_IsDue'      => $Data['pgs_is_due'],
            ':Pgs_DocDate'    => $Data['pgs_doc_date'],
            ':Pgs_DocDueDate' => $Data['pgs_doc_due_date'],
            ':Pgs_Enabled'    => $Data['pgs_enabled'],
            ':Pgs_Id'         => $Data['pgs_id'],
						':pgs_doctype'		=> $Data['pgs_id_doc_type'],
						':pgs_mpfn'       => $Data['pgs_mpfn'],
						':pgs_doc_pre' => $Data['pgs_doc_pre'],
						':pgs_mde'        => is_numeric($Data['pgs_mde']) ? $Data['pgs_mde'] : NULL

      ));

      if(is_numeric($resUpdate) && $resUpdate == 1){

            $respuesta = array(
              'error' => false,
              'data' => $resUpdate,
              'mensaje' =>'Numeracion actualizada con exito'
            );


      }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resUpdate,
              'mensaje'	=> 'No se pudo actualizar la informacion de la numeracion'
            );

      }

       $this->response($respuesta);
  }

  // Obtener numeracion de documento
  public function getDocumentNumbering_get(){

        $Data = $this->get();

        if ( !isset($Data['business']) OR !isset($Data['branch']) ) {

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' => 'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }
        // $sqlSelect = " SELECT * FROM pgdn";
        $sqlSelect = "SELECT pgs_id, pgs_id_doc_type, pgs_doc_pre, pgs_num_name, pgs_first_num, pgs_last_num, pgs_pref_num, pgs_cancel,
        pgs_is_due, pgs_doc_date, pgs_doc_due_date, pgs_enabled, coalesce((SELECT max(dvc_docnum)+1 ultimo_numero FROM dvct t0 WHERE t0.dvc_series = pgs_id), pgs_first_num) AS ultimo_numero, pgs_mpfn, pgs_mde FROM pgdn WHERE business = :business AND branch = :branch";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':business' => $Data['business'], ':branch' => $Data['branch']));

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

  // Obtener Tipo de Documento por Id
  public function getDocumentNumberingById_get(){

        $Data = $this->get();

        if(!isset($Data['pgs_id'])){

          $respuesta = array(
            'error' => true,
            'data'  => array(),
            'mensaje' =>'La informacion enviada no es valida'
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          return;
        }

        $sqlSelect = " SELECT * FROM pgdn WHERE pgs_id = :Pgs_Id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':Pgs_Id' => $Data['Pgs_Id']));

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

  //Actualiza el estado de una moneda
   public function updateStatus_post(){

  	 			$Data = $this->post();

  				if(!isset($Data['pgs_id']) OR !isset($Data['pgs_enabled'])){

  						$respuesta = array(
  							'error' => true,
  							'data'  => array(),
  							'mensaje' =>'La informacion enviada no es valida'
  						);

  						$this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
  						return;

  				}

  				$sqlUpdate = "UPDATE pgdn SET pgs_enabled = :Pgs_Enabled WHERE pgs_id = :Pgs_Id";


  				$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(

  											':Pgs_Enabled' => $Data['pgs_enabled'],
  											':Pgs_Id'      => $Data['pgs_id']

  				));


  				if(is_numeric($resUpdate) && $resUpdate == 1){

  							$respuesta = array(
  								'error'   => false,
  								'data'    => $resUpdate,
  								'mensaje' =>'Numeracion actualizada con exito'
  							);


  				}else{

  							$respuesta = array(
  								'error'   => true,
  								'data'    => $resUpdate,
  								'mensaje'	=> 'No se pudo actualizar la informacion de la numeracion'
  							);

  				}

  				 $this->response($respuesta);

   }

   public function updateUsersDocNum_post(){
    $Data = $this->post();
    if(!isset($Data['dnu_docnum_id'])){
      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' =>'La informacion enviada no es valida'
      );
      $this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
      return;
    }
      //ELIMINAR REGISTROS POR NUMERO DE NUMERACION DOCUMENTO
      $this->pedeo->queryTable('DELETE FROM rdnu WHERE dnu_docnum_id = :dnu_docnum_id', array(':dnu_docnum_id' => $Data['dnu_docnum_id']));
      $this->pedeo->trans_begin();
      foreach ($Data['dnu_user'] as $key => $value) {
        $resInsert = $this->pedeo->insertRow('INSERT INTO rdnu(dnu_docnum_id, dnu_user)VALUES(:dnu_docnum_id, :dnu_user)', array(
          ':dnu_docnum_id' => $Data['dnu_docnum_id'],
          ':dnu_user'     => $value
        ));
        if (is_numeric($resInsert) && $resInsert > 0) {
          
        } else {
          $this->pedeo->trans_rollback();
          $respuesta = array(
            'error'   => true,
            'data' => $resInsert,
            'mensaje'  => 'No se pudo registrar los usuarios para la numeracion actual'
          );
          $this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
        return;
        }
      }
      $this->pedeo->trans_commit();
      $respuesta = array(
        'error'   => false,
        'data'    => $resInsert,
        'mensaje' =>'Usuarios actualizada con exito'
      );
      $this->response($respuesta);

  }

  public function getListDocumentType_get(){

    $Data = $this->get();

    $sql = "SELECT
            pgs_id,
            pgs_num_name,
            (pgs_nextnum + 1) AS ultimo_numero,
            CASE WHEN coalesce(pgs_is_due,0) = 1 THEN 1 ELSE 0 END AS is_due,
            CASE WHEN coalesce(pgs_doc_due_date,'1999-01-01') > current_date THEN 1 ELSE 0 END AS valid_date,
            pgs_doc_pre,
            pgs_doc_date,
            pgs_doc_due_date
          FROM pgdn
          WHERE pgs_id_doc_type = :doctype AND business = :business AND branch = :branch
          and (extract(month from pgs_doc_date) <= extract(month from current_date) or extract(month from pgs_doc_due_date) <= extract(month from current_date))
          and (extract(year from pgs_doc_date) <= extract(year from current_date) or extract(year from pgs_doc_due_date) <= extract(year from current_date))
          ORDER BY pgs_num_name ASC";

    $resSql = $this->pedeo->queryTable($sql,array(
      ':doctype' => $Data['doctype'],
      ':business' => $Data['business'],
      ':branch' => $Data['branch']
    ));

    if(isset($resSql[0])){
      $return_data = [];

      foreach ($resSql as $key => $value) {
        $isDue = $value['is_due'];
        $isDate = $value['valid_date'];
        if($isDue){
          if($isDate){
            array_push($return_data,$value);
          }
          
        }else{
          array_push($return_data,$value);
        }
      }

      
      if(isset($return_data) && !empty($return_data)){
        $respuesta = array(
          'error' => false,
          'data' => $return_data,
          'mensaje' => 'OK'
        );
      }else{
        $respuesta = array(
          'error' => true,
          'data' => [],
          'mensaje' => 'No se encontraron datos en la busqueda'
        );
  
        $this->response($respuesta,  REST_Controller::HTTP_BAD_REQUEST);
        return;  
      }
      
    }else{
      $respuesta = array(
        'error' => true,
        'data' => [],
        'mensaje' => 'No se encontraron datos en la busqueda'
      );
    }

    $this->response($respuesta);

  }

	 private function validateDate($fecha){
			 if(strlen($fecha) == 10 OR strlen($fecha) > 10){
				 return true;
			 }else{
				 return false;
			 }
	 }





}
