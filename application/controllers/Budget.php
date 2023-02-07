<?php
// PRESUPUESTOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class Budget extends REST_Controller {

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
	// METODO PARA OBTENER LOS PRESUPUESTOS
  public function getBudget_get(){
		$Data = $this->get();
    $sqlSelect = "SELECT 
					mpc_id,
					mpc_nombre_presupuesto, 
					mpc_fecha_inicial,
					mpc_fecha_final,
					COALESCE(sum(mpc1_saldo), 0) as mpc1_saldo
				from tmpc
				left join mpc1 m on tmpc.mpc_id = m.mpc1_mpc_id
				where tmpc.business = :business and tmpc.branch = :branch
				group by mpc_id,
					mpc_nombre_presupuesto, 
					mpc_fecha_inicial,
					mpc_fecha_final";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(":business" => $Data['business'], ':branch' => $Data['branch']));

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
	// METODO PARA OBTENER LOS PRESUPUESTOS POR ID
  public function getBudgetByid_get(){
    $Data = $this->get();

    if(!isset($Data['mpc_id'])){
      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }

    $sqlSelect = "SELECT * from tmpc
									inner join mpc1 m on tmpc.mpc_id = m.mpc1_mpc_id
									WHERE tmpc.mpc_id = :mpc_id";

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':mpc_id'=>$Data['mpc_id']));

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
  // METODO PARA CREAR PRESUPUESTO
  public function createBudget_post(){
    // OBTENIENDO LOS DATOS PARA CREAR EL PRESUPUESTO
    $Data = $this->post();
    if( !isset($Data['mpc_fecha_inicial']) OR
        !isset($Data['mpc_fecha_final']) OR
        !isset($Data['mpc_nombre_presupuesto'])){
      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }

		$sqlSelect = "SELECT * FROM tmpc
									INNER JOIN mpc1 m ON tmpc.mpc_id = m.mpc_id
									WHERE lower(trim(mpc_nombre_presupuesto)) = lower(trim(:mpc_nombre_presupuesto))";

		$resSelect = $this->pedeo->queryTable($sqlSelect,array(
				':mpc_nombre_presupuesto' => $Data['mpc_nombre_presupuesto']
		));

		if(isset($resSelect[0])){
			$this->response(
				array(
					'error' => true,
					'data'  => [],
					'mensaje' => 'Este presupuesto  ya existe')
			);
				$this->pedeo->trans_rollback();
				return;
		}

    $sqlInsert = "INSERT INTO tmpc
    (mpc_fecha_inicial, mpc_fecha_final, mpc_nombre_presupuesto, business, branch)
     VALUES (:mpc_fecha_inicial, :mpc_fecha_final, :mpc_nombre_presupuesto, :business, :branch)";

    $resInsert = $this->pedeo->insertRow($sqlInsert,array(
		      ':mpc_fecha_inicial'=> $Data['mpc_fecha_inicial'],
		      ':mpc_fecha_final' =>$Data['mpc_fecha_final'],
		      ':mpc_nombre_presupuesto' =>$Data['mpc_nombre_presupuesto'],
			  ':business' => $Data['business'], 
			  ':branch' => $Data['branch']
    	));

    if(is_numeric($resInsert) && $resInsert > 0){
				$respuesta = array(
	        'error' => false,
	        'data'  => $resInsert,
	        'mensaje' => 'Presupuesto creado con exito');
	    }else{
	      $respuesta = array(
	        'error' => true,
	        'data'  => $resInsert,
	        'mensaje' => 'No se pudo crear presupuesto');
    	}

    $this->response($respuesta);

  }
	//METODO PARA CREAR DETALLE DE PRESUPUESTO
	public function createBudgetDetail_post(){
		$Data = $this->post();
		if( !isset($Data['mpc1_cuenta_cont']) OR
        !isset($Data['mpc1_debito']) OR
        !isset($Data['mpc1_credito']) OR
        !isset($Data['mpc1_centro_costo']) OR
        !isset($Data['mpc1_unidad']) OR
        !isset($Data['mpc1_proyecto'])OR
				!isset($Data['mpc1_mpc_id'])
      ){
      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }

		$saldo = $Data['mpc1_debito'] - $Data['mpc1_credito'];

		$sqlInsert2 ="INSERT INTO mpc1 (mpc1_cuenta_cont, mpc1_debito, mpc1_credito, mpc1_saldo, mpc1_centro_costo, mpc1_unidad, mpc1_proyecto, mpc1_mpc_id)
									VALUES(:mpc1_cuenta_cont, :mpc1_debito, :mpc1_credito, :mpc1_saldo, :mpc1_centro_costo, :mpc1_unidad, :mpc1_proyecto, :mpc1_mpc_id)";

		$resInsert2 = $this->pedeo->insertRow($sqlInsert2,array(
										':mpc1_cuenta_cont' =>$Data['mpc1_cuenta_cont'],
										':mpc1_debito' =>$Data['mpc1_debito'],
										':mpc1_credito' =>$Data['mpc1_credito'],
										':mpc1_saldo' =>$saldo,
										':mpc1_centro_costo' =>$Data['mpc1_centro_costo'],
										':mpc1_unidad' =>$Data['mpc1_unidad'],
										':mpc1_proyecto' =>$Data['mpc1_proyecto'],
										':mpc1_mpc_id' => $Data['mpc1_mpc_id']
									));

		if(is_numeric($resInsert2) && $resInsert2 > 0){

			$respuesta = array(
				'error' => false,
				'data'  => $resInsert2,
				'mensaje' => 'Presupuesto creado con exito');
		}else{

			$respuesta = array(
				'error' => true,
				'data'  => $resInsert2,
				'mensaje' => 'No se pudo crear el detalle de presupuesto');
		}
		$this->response($respuesta);
	}
	// METODO PARA OBTENER EL PERIODO EN UN PERIODO
	public function getBudgetPeriod_get(){
        $sqlSelect = "SELECT * FROM tmpp";

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
	// METODO PARA OBTENER LOS DATOS DEL PERIODO PRESUPUESTAL POR EL ID DEL PRESUPUESTO
	public function getBudgetPeriodById_get(){
			$Data = $this->get();
			if(!isset($Data['mpp_mpc_id'])){
				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'La informacion enviada no es valida'
				), REST_Controller::HTTP_BAD_REQUEST);

				return ;
			}
        $sqlSelect = "SELECT * FROM tmpp WHERE mpp_mpc_id = :mpp_mpc_id";

        $resSelect = $this->pedeo->queryTable($sqlSelect, array(':mpp_mpc_id' => $Data['mpp_mpc_id']));

        if(isset($resSelect[0])){

          $respuesta = array(
            'error' => false,
            'data'  => $resSelect,
            'mensaje' => 'Operacion exitosa');

        }else{

            $respuesta = array(
              'error'   => true,
              'data' => array(),
              'mensaje'	=> 'busqueda sin resultados'
            );

        }

         $this->response($respuesta);
  }
	// METODO PARA CREAR INSERTAR LOS DATOS EN LOS PERIODOS PRESUPUESTALES
	public function createBugetPeriod_post(){
		$Data = $this->post();
		if(!isset($Data['mpp_mpc_id']) OR
			!isset($Data['mpp_profit']) OR
			!isset($Data['mpp_fecha_inicial']) OR
			!isset($Data['mpp_fecha_final'])){
					$this->response(array(
		        'error'  => true,
		        'data'   => [],
		        'mensaje'=>'La informacion enviada no es valida'
		      ), REST_Controller::HTTP_BAD_REQUEST);

		      return ;
		}
		// BUSCA EL ACUMULADO DEL PRESUPUESTO
		$sqlSelect = "SELECT COALESCE(sum(mpp_profit), 0) as current_profit from tmpp where mpp_mpc_id = :mpp_mpc_id ";
		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mpp_mpc_id'=>$Data['mpp_mpc_id']));

		// VERIFICA QUE LA SUMATORIA DE LOS PROFITS Y EL VALOR INGRESADO NO SEA SUPERIOR AL 100%
		$sqlDetailsum = "SELECT sum(mpc1_saldo) as mpc1_saldo from mpc1 where mpc1_mpc_id = :mpp_mpc_id";
		$resDetailSum = $this->pedeo->queryTable($sqlDetailsum, array(':mpp_mpc_id'=>$Data['mpp_mpc_id']));

		//VERIFICA QUE LAS FECHAS SE CUMPLAN
		$fiPP =  date($Data['mpp_fecha_inicial']);
		$ffpp =  date($Data['mpp_fecha_final']);
		$fp =  date($Data['pre_date']);
		$ff =  date($Data['pre_enddate']);
		
		if(!($fiPP >= $fp AND
		 	$ffpp <= $ff)){
			
				$this->response(array(
					'error'  => true,
					'data'   => [],
					'mensaje'=>'Fechas inconsistentes'
				  ), REST_Controller::HTTP_BAD_REQUEST);
	
				  return ;
		}

		$currentprofit = $resSelect[0]['current_profit'];
		$porcent = $currentprofit + $Data['mpp_profit'] ;
		
		// 
		if($porcent > 100){
		$this->response(array(
			'error'  => true,
			'data'   => [],
			'mensaje'=>'El valor excede el limite'
			), REST_Controller::HTTP_BAD_REQUEST);

			return ;
		}	
			// SI TODO ESTA CORRECTO INSERTA EL REGISTRO EN LA TABLA
		$sqlInsert ="INSERT INTO tmpp (mpp_mpc_id, mpp_profit, mpp_fecha_inicial, mpp_fecha_final)
									VALUES(:mpp_mpc_id,:mpp_profit,:mpp_fecha_inicial,:mpp_fecha_final)";

		$resInsert = $this->pedeo->insertRow($sqlInsert,array(
										':mpp_mpc_id' =>$Data['mpp_mpc_id'],
										':mpp_profit' =>$porcent,
										':mpp_fecha_inicial' =>$Data['mpp_fecha_inicial'],
										':mpp_fecha_final' =>$Data['mpp_fecha_final']
									));

		if(is_numeric($resInsert) && $resInsert > 0){

			$respuesta = array(
				'error' => false,
				'data'  => $resInsert,
				'mensaje' => 'Presupuesto creado con exito');
		}else{

			$respuesta = array(
				'error' => true,
				'data'  => $resInsert,
				'mensaje' => 'No se pudo crear el detalle de presupuesto');
		}


		 $this->response($respuesta);
	}

	private function getBudget($id){
		$sqlSelect = "SELECT COALESCE(sum(mpc1_saldo),0) as mpc1_saldo from tmpc
					join mpc1 on mpc_id = mpc1_mpc_id
					where mpc_id = :mpp_mpc_id ";

		$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mpp_mpc_id'=>$id));
		
		return $resSelect[0];
	}




}
