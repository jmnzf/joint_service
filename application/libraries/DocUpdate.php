<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class DocUpdate {

    private $ci;
    private $pdo;

	public function __construct(){

        header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
        header("Access-Control-Allow-Origin: *");

        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->pdo = $this->ci->load->database('pdo', true)->conn_id;
        $this->ci->load->library('pedeo', [$this->pdo]);

	}
    //FUNCION PARA ACTUALIZAR REGISTRO DE DOCUMENTOS ACTUALIZADOS
	public function updatedDoc($doctype,$docentry,$before,$after,$user){
        $resp = [];
        $date = date('Y-m-d');
        $time = date("H:i:s");
        // print_r($date);exit;
		$sql = "INSERT INTO tbdu (bdu_doctype,bdu_docentry,bdu_before,bdu_after,bdu_update_by,bdu_date,bdu_time)
                VALUES (:bdu_doctype,:bdu_docentry,:bdu_before,:bdu_after,:bdu_update_by,:bdu_date,:bdu_time)";

		$resSql = $this->ci->pedeo->insertRow($sql,array(
            ':bdu_doctype' => $doctype,
            ':bdu_docentry' => $docentry,
            ':bdu_before' => $before,
            ':bdu_after' => $after,
            ':bdu_update_by' => $user,
            ':bdu_date' => $date,
            ':bdu_time' => $time
        ));
        
        if(is_numeric($resSql) && $resSql > 0){

			$resp = array(
				'error'   => false,
				'data'    => $resSql,
				'mensaje'	=> 'Dato insertado con exito'
			);

        }else{



			$resp = array(
				'error'   => true,
				'data'    => $resSql,
				'mensaje'	=> 'No se puedo realizar la inserccion del dato actuaizado'
			);

			return $resp;
        }


        return $resp;

	}
}