<?php
// CONSULTAS DINAMICAS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Querys extends REST_Controller {

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

  //OBTENER INFO CONSULTA
	public function getData_post(){

		      $Data = $this->post();


		      if(!isset($Data['sql'])){

		        $respuesta = array(
		          'error' => true,
		          'data'  => array(),
		          'mensaje' =>'La informacion enviada no es valida'
		        );

		        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

		        return;
		      }
			 $pos = strpos($Data['sql'], 'limit');
          	$query =($pos === false) ? $Data['sql'].' LIMIT 500': $Data['sql'];

          $resultQuery = $this->pedeo->queryTable($query, array());


          if( isset($resultQuery[0]) ){

            $respuesta = array(
              'error'   => false,
              'data'    => $resultQuery,
              'mensaje'	=> array_keys($resultQuery[0])
            );

          }else{

            $respuesta = array(
              'error'   => true,
              'data'    => $resultQuery,
              'mensaje'	=> 'Busqueda sin resultados'
            );
          }


          $this->response($respuesta);


	}

  public function setDataSql_post(){
    $Data = $this->post();

    if(!isset($Data['sql_description']) OR !isset($Data['sql_query'])){

      $respuesta = array(
        'error' => true,
        'data'  => $Data,
        'mensaje' =>'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }

    $sqlInsert = "INSERT INTO csql(sql_description, sql_query, sql_date, sql_createby,sql_query_type, sql_query_model)
	                 VALUES (:sql_description, :sql_query, :sql_date, :sql_createby,:sql_query_type, :sql_query_model)";

    $resInsert = $this->pedeo->insertRow($sqlInsert, array(
      ':sql_description' =>  $Data['sql_description'],
      ':sql_query'       =>  $Data['sql_query'],
      ':sql_date'        =>  date('Y-m-d'),
      ':sql_createby'    =>  $Data['sql_createby'],
	  ':sql_query_type'  =>	 $Data['tipo'],
	  ':sql_query_model' =>  $Data['modelo'],
    ));

		$this->pedeo->trans_begin();

    if( is_numeric($resInsert)  && $resInsert > 0 ){

			foreach ( json_decode($Data['users'], true) as $key => $value ) {

				$sqlInsertUser = "INSERT INTO public.truc(ruc_user, ruc_query, ruc_enabled)
													VALUES (:ruc_user, :ruc_query, :ruc_enabled)";

				$resInsertUser = $this->pedeo->insertRow($sqlInsertUser, array(
					':ruc_user' 		 =>  $value,
					':ruc_query'     =>  $resInsert,
					':ruc_enabled'   =>  1
				));


				if( is_numeric($resInsertUser)  && $resInsertUser > 0 ){

				}else{
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'    => $resInsertUser,
						'mensaje'	=> 'No se pudo insertar el query'
					);

					$this->response($respuesta);
				}


			}

			$this->pedeo->trans_commit();

			$respuesta = array(
				'error'   => false,
				'data'    => $resInsert,
				'mensaje'	=> 'Operacion exitosa'
			);

    }else{

			$this->pedeo->trans_rollback();

      $respuesta = array(
        'error'   => true,
        'data'    => $resInsert,
        'mensaje'	=> 'No se pudo insertar el query'
      );

      $this->response($respuesta);
    }

		$this->response($respuesta);

  }


	//OBETENER CONSULTAS GUARDADAS
	public function getQuerys_get(){

		$Data = $this->get();
		
		if(!empty($Data)){
			$sqlSelect = "SELECT csql.*,ruc_user as id FROM
		csql
		join truc on ruc_query = sql_id
		where ruc_user = :ruc_user";
		}else{
		$sqlSelect = "SELECT csql.*, string_agg(truc.ruc_user::text, ',') as id from csql
		left join truc on ruc_query = sql_id
		group by sql_id, sql_description, sql_query, sql_date, sql_createby, sql_query_type, sql_query_model";
		}
		

		$resSelect = $this->pedeo->queryTable($sqlSelect,(!empty($Data))?array(":ruc_user" => $Data['pgu_id_usuario']):array());

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

	//OBETENER TIPOS DE CONSULTAS
	public function getQueryType_get(){
		$sqlSelect = "SELECT * FROM ttcd";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

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

	//OBETENER MODELO DE CONSULTAS
	public function getQueryModel_get(){
		$sqlSelect = "SELECT * FROM tmcd";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

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

	public function updateQuery_post()
	{
		$Data = $this->post();

		// print_r($Data);exit;
		if(!isset($Data['sql_description']) OR !isset($Data['sql_query'])){

			$respuesta = array(
			  'error' => true,
			  'data'  => $Data,
			  'mensaje' =>'La informacion enviada no es valida'
			);
	  
			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
	  
			return;
		  }
		
		$sqlUpdate = "UPDATE csql set sql_description = :sql_description,
		 sql_query = :sql_query,
		sql_createby = :sql_createby,
		sql_query_type = :sql_query_type,
		sql_query_model = :sql_query_model
		WHERE sql_id = :sql_id";

		$resUpdate = $this->pedeo->updateRow($sqlUpdate, array(
			':sql_description' =>  $Data['sql_description'],
			':sql_query'       =>  $Data['sql_query'],
			':sql_createby'    =>  $Data['sql_createby'],
			':sql_query_type'  =>	 $Data['tipo'],
			':sql_query_model' =>  $Data['modelo'],
			':sql_id' =>  $Data['sql_id']
		  ));

		  if(is_numeric($resUpdate) AND $resUpdate > 0){
			
			$delete = $this->pedeo->deleteRow("DELETE FROM truc WHERE ruc_query = :ruc_query",
			array(":ruc_query" => $Data['sql_id']));

			$this->pedeo->trans_begin();
			foreach (json_decode($Data['users'], true) as $key => $value) {

				$sqlInsertUser = "INSERT INTO public.truc(ruc_user, ruc_query, ruc_enabled)
													VALUES (:ruc_user, :ruc_query, :ruc_enabled)";

				$resInsertUser = $this->pedeo->insertRow($sqlInsertUser, array(
					':ruc_user' 		 =>  $value,
					':ruc_query'     =>  $Data['sql_id'],
					':ruc_enabled'   =>  1
				));


				if (is_numeric($resInsertUser)  && $resInsertUser > 0) {
				} else {
					$this->pedeo->trans_rollback();

					$respuesta = array(
						'error'   => true,
						'data'    => $resInsertUser,
						'mensaje'	=> 'No se pudo Actualizar el query'
					);

					$this->response($respuesta);
				}
			}

			$this->pedeo->trans_commit();

			$respuesta = array(
				'error'   => false,
				'data'    => $resUpdate,
				'mensaje'	=> 'Operacion exitosa'
			);
		  }else{
			$respuesta = array(
				'error' => true,
				'data'  => $resUpdate,
				'mensaje' => 'No se pudo actualizar el registro');
		  }
		
		 $this->response($respuesta);
	}

	public function deleteQuery_post(){
		$Data = $this->post();

		if(!isset($Data['sql_id'])){

			$respuesta = array(
			  'error' => true,
			  'data'  => $Data,
			  'mensaje' =>'La informacion enviada no es valida'
			);
	  
			$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);
	  
			return;
		  }

		 
		  
		  $sqlDelete = "DELETE FROM csql WHERE sql_id = :sql_id";

		  $resDelete = $this->pedeo->deleteRow($sqlDelete, array(":sql_id" => $Data['sql_id']));


		  if(is_numeric($resDelete) AND $resDelete > 0){
			$delete = $this->pedeo->deleteRow("DELETE FROM truc WHERE ruc_query = :ruc_query",
			array(":ruc_query" => $Data['sql_id']));

			$respuesta = array(
				'error' => false,
				'data'  => '',
				'mensaje' => 'Operacion exitosa');
		  }else{
			$respuesta = array(
				'error' => true,
				'data'  => $resDelete,
				'mensaje' => 'No se pudo eliminar el registro');
		  }

		  $this->response($respuesta);
	}

}
