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

      //VALIDAR QUE EL NUMERO INICIAL NO ESTE EN UN RANGO DE NUMERACION YA CREADO
      $validDoc = "SELECT * FROM pgdn WHERE pgs_id_doc_type = :doctype AND pgs_enabled = :status AND pgs_cancel = :cancel AND business = :business AND branch = :branch AND pgs_pref_num = :pgs_pref_num";
      $resValidDoc = $this->pedeo->queryTable($validDoc,array(
        ':status'       => 1,
        ':cancel'       => $Data['pgs_cancel'],
        ':doctype'      => $Data['pgs_id_doc_type'],
        ':business'     => $Data['business'],
        ':branch'       => $Data['branch'],
        ':pgs_pref_num' => $Data['pgs_pref_num']
      ));

      if( isset( $resValidDoc[0] ) ){

          foreach ($resValidDoc as $key => $doc) {
            if($doc['pgs_cancel']){
              if( $Data['pgs_first_num'] >= $doc['pgs_first_num'] && $Data['pgs_first_num'] <= $doc['pgs_last_num'] ){
                $respuesta = array(
                  'error' => true,
                  'data'  => array(),
                  'mensaje' =>'Ya existe una numeración para este tipo de documento con este rango'
                );
  
                $this->response($respuesta);
  
                return;
              }
            }else {
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

      $sqlInsert = "INSERT INTO pgdn(pgs_id_doc_type, pgs_num_name, pgs_first_num, pgs_last_num, pgs_pref_num, pgs_cancel, pgs_is_due, pgs_doc_date,  pgs_doc_due_date, pgs_enabled, pgs_nextnum, pgs_doctype, pgs_mpfn, pgs_mde, business, branch,pgs_doc_pre, pgs_enable_fe)
                    VALUES(:Pgs_IdDocType,  :Pgs_NumName,  :Pgs_FirstNum,  :Pgs_LastNum,  :Pgs_PrefNum,  :Pgs_Cancel,  :Pgs_IsDue,  :Pgs_DocDate,  :Pgs_DocDueDate,  :Pgs_Enabled, :pgs_nextnum, :pgs_doctype, :pgs_mpfn, :pgs_mde, :business, :branch,:pgs_doc_pre, :pgs_enable_fe)";


      $resInsert = $this->pedeo->insertRow($sqlInsert, array(

            ':Pgs_IdDocType'  => $Data['pgs_id_doc_type'],
            ':Pgs_NumName'    => $Data['pgs_num_name'],
            ':Pgs_FirstNum'   => $Data['pgs_first_num'],
            ':Pgs_LastNum'    => $Data['pgs_last_num'],
            ':Pgs_PrefNum'    => $Data['pgs_pref_num'],
            ':Pgs_Cancel'     => $Data['pgs_cancel'],
            ':Pgs_IsDue'      => $Data['pgs_is_due'],
            ':Pgs_DocDate'    => $Data['pgs_doc_date'],
            ':Pgs_DocDueDate' => isset($Data['pgs_doc_due_date']) && !empty($Data['pgs_doc_due_date']) ? $Data['pgs_doc_due_date'] : NULL,
            ':Pgs_Enabled'    => $Data['pgs_enabled'],
						':pgs_nextnum'	  => ($Data['pgs_nextnum'] - 1),
						':pgs_doctype'		=> $Data['pgs_id_doc_type'],
						':pgs_mpfn'       => $Data['pgs_mpfn'],
						':pgs_mde'        => $Data['pgs_mde'],
            ':business'       => $Data['business'],
            ':branch'         => $Data['branch'],
						':pgs_doc_pre'    => $Data['pgs_doc_pre'],
						':pgs_enable_fe'    => $Data['pgs_enable_fe']

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
        $sqlSelect = "SELECT pgs_id, pgs_doc_pre FROM pgdn WHERE pgs_id_doc_type = :pgs_id_doc_type AND business = :business AND branch = :branch AND pgs_pref_num = :pgs_pref_num";
        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':pgs_id_doc_type' => $Data['pgs_id_doc_type'],':business' => $Data['business'], ':branch' => $Data['branch'], 'pgs_pref_num' => $Data['pgs_pref_num']));
        if (isset($resSelect[0])) {
          foreach ($resSelect as $key => $value) {
              $sqlUpdatePre = "UPDATE pgdn SET pgs_doc_pre = 0 WHERE pgs_id = :pgs_id";
              $resUpdate = $this->pedeo->updateRow($sqlUpdatePre, array(
                ':pgs_id'  => $value['pgs_id'],
              ));
          }
        }
      }

      //VALIDAR QUE LA NUMERACION NO SE PUEDA EDITAR SI YA SE CONSUMIO EL NUMERO INICIAL O FINAL
      $nuevosDatos = array(
        "pgs_id"=> intval($Data['pgs_id']),
        "pgs_id_doc_type"=>intval($Data['pgs_id_doc_type']),
        "pgs_num_name"=>$Data['pgs_num_name'],
        "pgs_first_num"=>intval($Data['pgs_first_num']),
        "pgs_last_num"=>intval(trim($Data['pgs_last_num'])),
        "pgs_pref_num"=>$Data['pgs_pref_num'],
        "pgs_doc_date"=>$Data['pgs_doc_date'],
        "pgs_doc_due_date"=>$Data['pgs_doc_due_date'],
        "pgs_enabled"=>intval($Data['pgs_enabled']),
        "pgs_nextnum"=>intval($Data['pgs_nextnum']),
        "pgs_doctype"=>intval($Data['pgs_id_doc_type']),
        "pgs_cancel"=>intval($Data['pgs_cancel']),
        "pgs_is_due"=>intval($Data['pgs_is_due']),
        "pgs_mpfn"=>intval($Data['pgs_mpfn']),
        "pgs_mde"=>intval($Data['pgs_mde']),
        "business"=>intval($Data['business']),
        "branch"=>intval($Data['branch']),
        "pgs_doc_pre"=>intval($Data['pgs_doc_pre']),
        "pgs_enable_fe"=>intval($Data['pgs_enable_fe'])
      );

      $jsonNewData = json_encode($nuevosDatos);
      $numInicial = "SELECT verificar_cambios({tabla_name},{campo_id},{registro_id},{campos_excluir},{nuevos_datos},{valid}) as bool";
      $numInicial = str_replace("{tabla_name}","'pgdn'",$numInicial);
      $numInicial = str_replace("{campo_id}","'pgs_id'",$numInicial);
      $numInicial = str_replace("{registro_id}",$Data['pgs_id'],$numInicial);
      $numInicial = str_replace("{campos_excluir}","array['pgs_doc_pre', 'pgs_enabled','pgs_id','pgs_nextnum']",$numInicial);
      $numInicial = str_replace("{nuevos_datos}","'".$jsonNewData."'",$numInicial);
      $numInicial = str_replace("{valid}","'AND pgs_nextnum > pgs_first_num'",$numInicial);

      $resNumInicial = $this->pedeo->queryTable($numInicial,array());
      if($resNumInicial[0]['bool']){
        $respuesta = array(
          'error' => true,
          'data'  => array(),
          'mensaje' =>'La numeración no se puede actualizar porque está en uso o porque se ha agotado el rango de numeración. Para solucionar este problema, debe solicitar una nueva numeración'
        );

        $this->response($respuesta);

        return;
      }
      //FIN DE LA VALIDACION
      $sqlUpdate = "UPDATE pgdn SET pgs_id_doc_type = :Pgs_IdDocType, pgs_num_name = :Pgs_NumName, pgs_first_num = :Pgs_FirstNum,
                    pgs_last_num = :Pgs_LastNum, pgs_pref_num = :Pgs_PrefNum, pgs_cancel = :Pgs_Cancel, pgs_is_due = :Pgs_IsDue,
                    pgs_doc_date = :Pgs_DocDate, pgs_doc_due_date = :Pgs_DocDueDate, pgs_enabled = :Pgs_Enabled, pgs_doctype = :pgs_doctype,
										pgs_mpfn = :pgs_mpfn, pgs_mde = :pgs_mde, pgs_doc_pre = :pgs_doc_pre,pgs_enable_fe = :pgs_enable_fe, pgs_nextnum = :pgs_nextnum
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
            ':Pgs_DocDueDate' => isset($Data['pgs_doc_due_date']) && !empty($Data['pgs_doc_due_date']) ? $Data['pgs_doc_due_date'] : NULL,
            ':Pgs_Enabled'    => $Data['pgs_enabled'],
            ':Pgs_Id'         => $Data['pgs_id'],
						':pgs_doctype'		=> $Data['pgs_id_doc_type'],
						':pgs_mpfn'       => $Data['pgs_mpfn'],
						':pgs_doc_pre'    => $Data['pgs_doc_pre'],
						':pgs_enable_fe'    => $Data['pgs_enable_fe'],
						':pgs_mde'        => is_numeric($Data['pgs_mde']) ? $Data['pgs_mde'] : NULL,
            ':pgs_nextnum'    => $Data['pgs_nextnum'] - 1

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
        $sqlSelect = "SELECT pgs_id, pgs_id_doc_type, pgs_doc_pre, pgs_num_name, to_char(pgs_first_num, '999G999G999G999G999G999') as pgs_first_num, to_char(pgs_last_num, '999G999G999G999G999G999') as pgs_last_num, pgs_pref_num, pgs_cancel,
        pgs_is_due, pgs_doc_date, pgs_doc_due_date, pgs_enabled, to_char(pgs_nextnum +1, '999G999G999G999G999G999') as ultimo_numero, pgs_mpfn, pgs_mde, dmdt.mdt_docname as pgs_docname, pgs_enable_fe
        FROM pgdn inner join dmdt on pgdn.pgs_id_doc_type = dmdt.mdt_doctype  WHERE pgdn.business = :business AND pgdn.branch = :branch";

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
            pgs_doc_due_date,
            pgs_enable_fe
          FROM pgdn
          WHERE pgs_id_doc_type = :doctype AND business = :business AND branch = :branch
          and (extract(month from pgs_doc_date) <= extract(month from current_date) or extract(month from pgs_doc_due_date) >= extract(month from current_date))
          and (extract(year from pgs_doc_date) <= extract(year from current_date) or extract(year from pgs_doc_due_date) >= extract(year from current_date))
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
