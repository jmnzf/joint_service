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
          $query = $Data['sql'].' LIMIT 10';
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
		$sqlSelect = "SELECT * FROM csql";

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



}
