<?php
// FACTURA DE COMPRAS
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
    $sqlSelect = "SELECT * from tmpc
		inner join mpc1 m on tmpc.mpc_id = m.mpc_id";

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
									inner join mpc1 m on tmpc.mpc_id = m.mpc_id
									WHERE tmpc.mpc_id= :mpc_id";

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
        !isset($Data['mpc_cuenta_cont']) OR
        !isset($Data['mpc_debito']) OR
        !isset($Data['mpc_credito']) OR
        !isset($Data['mpc_saldo']) OR
        !isset($Data['mpc_centro_costo']) OR
        !isset($Data['mpc_unidad']) OR
        !isset($Data['mpc_proyecto'])OR
        !isset($Data['mpc_nombre_presupuesto'])
      ){
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

		$this->pedeo->trans_begin();

    $sqlInsert = "INSERT INTO tmpc
    (mpc_fecha_inicial, mpc_fecha_final, mpc_nombre_presupuesto)
     VALUES (:mpc_fecha_inicial, :mpc_fecha_final, :mpc_nombre_presupuesto)";

    $resInsert = $this->pedeo->insertRow($sqlInsert,array(
      ':mpc_fecha_inicial'=> $Data['mpc_fecha_inicial'],
      ':mpc_fecha_final' =>$Data['mpc_fecha_final'],
      ':mpc_nombre_presupuesto' =>$Data['mpc_nombre_presupuesto']
    ));

    if(is_numeric($resInsert) && $resInsert > 0){

      $sqlInsert2 ="INSERT INTO mpc1 (mpc1_cuenta_cont, mpc1_debito, mpc1_credito, mpc1_saldo, mpc1_centro_costo, mpc1_unidad, mpc1_proyecto, mpc_id)
										VALUES(:mpc1_cuenta_cont, :mpc1_debito, :mpc1_credito, :mpc1_saldo, :mpc1_centro_costo, :mpc1_unidad, :mpc1_proyecto, :mpc_id)";

			$resInsert2 = $this->pedeo->insertRow($sqlInsert2,array(
								      ':mpc1_cuenta_cont' =>$Data['mpc_cuenta_cont'],
								      ':mpc1_debito' =>$Data['mpc_debito'],
								      ':mpc1_credito' =>$Data['mpc_credito'],
								      ':mpc1_saldo' =>$Data['mpc_saldo'],
								      ':mpc1_centro_costo' =>$Data['mpc_centro_costo'],
								      ':mpc1_unidad' =>$Data['mpc_unidad'],
								      ':mpc1_proyecto' =>$Data['mpc_proyecto'],
											':mpc_id' => $resInsert
								    ));

			if(is_numeric($resInsert2) && $resInsert2 > 0){

				$respuesta = array(
	        'error' => false,
	        'data'  => $resInsert,
	        'mensaje' => 'Presupuesto creado con exito');
			}else{

				$this->pedeo->trans_rollback();
			}
				$this->pedeo->trans_commit();

    }else{
      $respuesta = array(
        'error' => true,
        'data'  => $resInsert,
        'mensaje' => 'No se pudo crear presupuesto');
				$this->pedeo->trans_rollback();


    }

    $this->response($respuesta);

  }
	// METODO PARA ACTUALIZAR PRESUPUESTO
  // public function updateBudget_post(){
  //   $Data = $this->post();
  //   if( !isset($Data['mpc_fecha_inicial']) OR
  //       !isset($Data['mpc_fecha_final']) OR
  //       !isset($Data['mpc_cuenta_cont']) OR
  //       !isset($Data['mpc_debito']) OR
  //       !isset($Data['mpc_credito']) OR
  //       !isset($Data['mpc_saldo']) OR
  //       !isset($Data['mpc_centro_costo']) OR
  //       !isset($Data['mpc_unidad']) OR
  //       !isset($Data['mpc_proyecto'])OR
  //       !isset($Data['mpc_nombre_presupuesto'])
  //     ){
  //     $this->response(array(
  //       'error'  => true,
  //       'data'   => [],
  //       'mensaje'=>'La informacion enviada no es valida'
  //     ), REST_Controller::HTTP_BAD_REQUEST);
	//
  //     return ;
  //   }
	//
	//
  //   $sqlUpdate = "UPDATE tmpc
  //           set mpc_fecha_inicial = :mpc_fecha_inicial,
  //               mpc_fecha_final = :mpc_fecha_final,
  //               mpc_cuenta_cont = :mpc_cuenta_cont,
  //               mpc_debito = :mpc_debito,
  //               mpc_credito = :mpc_credito,
  //               mpc_saldo = :mpc_saldo,
  //               mpc_centro_costo = :mpc_centro_costo,
  //               mpc_unidad = :mpc_unidad,
  //               mpc_proyecto = :mpc_proyecto,
  //               mpc_nombre_presupuesto = :mpc_nombre_presupuesto
  //               where mpc_id = :mpc_id";
	//
  //     $resUpdate = $this->pedeo->updateRow($sqlUpdate,array(
  //       ':mpc_fecha_inicial'=> $Data['mpc_fecha_inicial'],
  //       ':mpc_fecha_final' =>$Data['mpc_fecha_final'],
  //       ':mpc_cuenta_cont' =>$Data['mpc_cuenta_cont'],
  //       ':mpc_debito' =>$Data['mpc_debito'],
  //       ':mpc_credito' =>$Data['mpc_credito'],
  //       ':mpc_saldo' =>$Data['mpc_saldo'],
  //       ':mpc_centro_costo' =>$Data['mpc_centro_costo'],
  //       ':mpc_unidad' =>$Data['mpc_unidad'],
  //       ':mpc_proyecto' =>$Data['mpc_proyecto'],
  //       ':mpc_nombre_presupuesto' =>$Data['mpc_nombre_presupuesto'],
  //       ':mpc_id' => $Data['mpc_id']
  //     ));
	//
  //     if(is_numeric($resUpdate) && $resUpdate == 1){
  //       $respuesta = array(
  //         'error' => false,
  //         'data'  => $resUpdate,
  //         'mensaje' => '');
  //     }else{
  //       $respuesta = array(
  //         'error' => true,
  //         'data'  => $resUpdate,
  //         'mensaje' => 'No se pudo crear presupuesto');
  //     }
	//
  //     $this->response($respuesta);
  //     }
	//
  //   // FIN DE PROCEDIMIENTOS PARA LA TABLA DE PRESUPUESTOS
	//
  //   // INICIO DE PROCEDIMIENTOS PARA TABLA DE PERIODOS PRESUPUESTALES
	//

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
	// METODO PARA CREAR INSERTAR LOS DATOS EN LOS PERIODOS PRESUPUESTALES
  public function createBudgetPeriod_post(){
    $Data = $this->post();
    if( !isset($Data['mpp_mpc_id']) OR
        !isset($Data['mpp_fecha_inicial']) OR
        !isset($Data['mpp_fecha_final']) OR
        !isset($Data['mpp_profit'])){

      $this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'La informacion enviada no es valida'
      ), REST_Controller::HTTP_BAD_REQUEST);

      return ;
    }
		$info = $this->getBudgetPeriodBytmc($Data['mpp_mpc_id'],$Data['mpp_fecha_inicial'],$Data['mpp_fecha_final']);

		if(empty($info[0])){
			$this->response(array(
        'error'  => true,
        'data'   => [],
        'mensaje'=>'El presupuesto no existe'
      ), REST_Controller::HTTP_BAD_REQUEST);
      return;
		}


		if($info[0]['daysfi'] < 0 OR $info[0]['daysff'] > 0){
			$this->response(array(
				'error'  => true,
				'data'   => [],
				'mensaje'=>'Fecha invalida para la accion'
			), REST_Controller::HTTP_BAD_REQUEST);
			return;
		}

		// PARA CONSULTAR LOS DATOS PARA VALIDACION
    $sqlSelect = "SELECT round(sum(mpp_profit)) suma from tmpp
									join mpc1 on mpp_mpc_id = mpc_id
									where mpp_mpc_id = :mpp_mpc_id
									group by mpc1_saldo";

      $resSelect = $this->pedeo->queryTable($sqlSelect,array(
        ':mpp_mpc_id' => $Data['mpp_mpc_id']
      ));

			$porcentaje = round(((100/$info[0]['mpc1_saldo'])*$Data['mpp_profit']),2);
			$resSelect[0]['suma'] = (empty($resSelect))? 0 : $resSelect[0]['suma'];


      //si la sumatoria de los profits es mayor al dato ingresado se retorna un error
			$sumProfit =	round($resSelect[0]['suma'] + $porcentaje,2);

			if($sumProfit > 100){
				$this->response(array(
	          'error'  => true,
	          'data'   => [],
	          'mensaje'=>'Excendente del monto asignado.'
	        ), REST_Controller::HTTP_BAD_REQUEST);
					return;
			}


      $sqlInsert = "INSERT INTO tmpp (mpp_mpc_id,mpp_fecha_inicial, mpp_fecha_final, mpp_profit)
       VALUES (:mpp_mpc_id,:mpp_fecha_inicial, :mpp_fecha_final, :mpp_profit)";

      $resInsert = $this->pedeo->insertRow($sqlInsert,array(
        ':mpp_mpc_id' => $Data['mpp_mpc_id'],
        ':mpp_fecha_inicial'=> $Data['mpp_fecha_inicial'],
        ':mpp_fecha_final' =>$Data['mpp_fecha_final'],
        ':mpp_profit' =>$porcentaje
      ));

      if(is_numeric($resInsert) && $resInsert > 0){
        $respuesta = array(
          'error' => false,
          'data'  => $resInsert,
          'mensaje' => 'Operacion exitosa');
      }else{
        $respuesta = array(
          'error' => true,
          'data'  => [],
          'mensaje' => 'No se pudo crear presupuesto');
      }
    $this->response($respuesta);
  }
	// METODO ACTUALIZAR EL REGISTRO DE PERIODO PRESUPUESTAL
	// METODO PARA BUSCAR LAS FECHAS Y DATOS NECESARIOS PARA LAS VALIDACIONES
  private function getBudgetPeriodBytmc($id,$fechaI,$fechaF){
			$sqlSelect = "SELECT *,(:mpp_fecha_inicial::date - mpc_fecha_inicial) daysFi,(:mpp_fecha_final::date - mpc_fecha_final) daysFF
														FROM tmpc
														inner join mpc1 m on tmpc.mpc_id = m.mpc_id
														where tmpc.mpc_id = :mpc_id";

    	$resSelect = $this->pedeo->queryTable($sqlSelect, array(':mpc_id'=>$id , ':mpp_fecha_inicial'=>$fechaI,':mpp_fecha_final'=>$fechaF));

	    if(isset($resSelect[0])){
	      return $resSelect;
	    }else{

	      return [];
	    }
  }


}
