<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;



class Mediosdepago extends REST_Controller {

    private $pdo;

    public function __construct(){
        header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding');
        header('Access-Control-Allow-Origin: *');
        parent::__construct();$this->load->database();
        $this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);
    }



    public function create_post(){

        $Data = $this->post();

        if( !isset($Data['mdp_name']) OR 
            !isset($Data['mdp_account']) OR 
            !isset($Data['mdp_local']) OR 
            !isset($Data['mdp_multiple']) OR
            !isset($Data['mdp_status'])
        ){

            $respuesta = array( 
                'error'=>true,'data'=>array(),'mensaje'=>'Faltan parametros'
            );

            return $this->response($respuesta);
        }
        
        
        $resInsert = $this->pedeo->insertRow('INSERT INTO tmdp(mdp_name, mdp_status, mdp_account, mdp_local, mdp_multiple, mdp_fe_reference)VALUES(:mdp_name, :mdp_status, :mdp_account, :mdp_local, :mdp_multiple, :mdp_fe_reference)', array(
            ':mdp_name'     => $Data['mdp_name'], 
            ':mdp_status'   => $Data['mdp_status'],
            ':mdp_account'  => $Data['mdp_account'],
            ':mdp_local'    => $Data['mdp_local'],
            ':mdp_multiple' => $Data['mdp_multiple'],
            ':mdp_fe_reference' => $Data['mdp_fe_reference'] ?: ""
        ));

        if ( is_numeric($resInsert) && $resInsert > 0) { 

            $respuesta = array( 
                'error'  => false,
                'data'   => $resInsert,
                'mensaje'=> 'Registro insertado con exito'
            );

            return $this->response($respuesta);

        } else { 

            $respuesta = array( 
                'error'   => true,
                'data'    => $resInsert,
                'mensaje' => 'Error al insertar el registro'
            );

            return $this->response($respuesta);

        }


    }



    public function update_post(){

        $Data = $this->post();

        if( !isset($Data['mdp_name']) OR 
            !isset($Data['mdp_status']) OR 
            !isset($Data['mdp_account']) OR 
            !isset($Data['mdp_local']) OR 
            !isset($Data['mdp_multiple'])OR
            !isset($Data['mdp_id'])
        ){

            $respuesta = array( 
                'error'   => true,
                'data'    => array(),
                'mensaje' => 'Faltan parametros'
            );

            return $this->response($respuesta);
        }

        $resUpdate = $this->pedeo->updateRow('UPDATE tmdp SET mdp_name = :mdp_name , mdp_status = :mdp_status, mdp_account = :mdp_account, mdp_local = :mdp_local, mdp_multiple =:mdp_multiple, mdp_fe_reference = :mdp_fe_reference WHERE mdp_id = :mdp_id', array(
            ':mdp_name'    => $Data['mdp_name'], 
            ':mdp_status'  => $Data['mdp_status'], 
            ':mdp_account' => $Data['mdp_account'],
            ':mdp_local'   => $Data['mdp_local'],
            ':mdp_multiple' => $Data['mdp_multiple'],
            ':mdp_fe_reference' => $Data['mdp_fe_reference'] ?: "",
            ':mdp_id'      => $Data['mdp_id']
        ));

        if ( is_numeric($resUpdate) && $resUpdate == 1 ) { 

            $respuesta = array( 
            'error'=>false,'data'=>$resUpdate,'mensaje'=>'Registro actualizado con exito'
            );

            return $this->response($respuesta);

        } else { 

            $respuesta = array( 
            'error'=>true,'data'=>$resUpdate,'mensaje'=>'Error al actualizar el registro'
            );

            return $this->response($respuesta);

        }


    }



    public function index_get(){

        $resSelect = $this->pedeo->queryTable("SELECT * FROM tmdp ",array());

        if ( isset($resSelect[0]) ) { 

            $respuesta = array( 
            'error'=>false,'data'=>$resSelect,'mensaje'=>''
            );

            return $this->response($respuesta);
        } else { 

            $respuesta = array( 
            'error'=>true,'data'=>$resSelect,'mensaje'=>'Busqueda sin resultados'
            );

            return $this->response($respuesta);    
        }
    }


}



