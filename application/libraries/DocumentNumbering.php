<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class DocumentNumbering {

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

	//CABECERA DEL DOCUMENTO AL COPIAR
	public function NumberDoc($series,$date_start,$date_end){

        $DocNumVerificado = 0;
		$sqlNumeracion = " SELECT * FROM  pgdn WHERE pgs_id = :pgs_id";

		$resNumeracion = $this->ci->pedeo->queryTable($sqlNumeracion, array(':pgs_id' => $series));

		if (isset($resNumeracion[0])) {
            //ASIGNAR SI EL CAMPO TIENE MARCADO QUE VENCE
            $isdue = is_bool($resNumeracion[0]['pgs_is_due']) ? 1 : 0;
			$numeroActual = $resNumeracion[0]['pgs_nextnum'];
			$numeroFinal  = $resNumeracion[0]['pgs_last_num'];
			$numeroSiguiente = ($numeroActual + 1);
            //ASIGNACION DE FECHAS
            $fechaInicial = $resNumeracion[0]['pgs_doc_date'];
            $fechaFinal = $resNumeracion[0]['pgs_doc_due_date'];
            $isDue = isset($resNumeracion[0]['pgs_is_due']) && !empty($resNumeracion[0]['pgs_is_due']) ? 1 : 0; 
            // print_r($isdue);exit;
            if($date_start >= $fechaInicial && $isdue === 1 && $date_end >= $fechaFinal){
                $respuesta = array(
                    'error' => true,
                    'data'  => array(),
                    'mensaje' => 'La fecha del documento, difiere del rango de la numeracion actual'
                );

                return $respuesta;
            }

			if ($numeroSiguiente <= $numeroFinal) {

                if($isDue){
                    if($date_start < $fechaInicial){

                        $respuesta = array(
                            'error' => true,
                            'data'  => array(),
                            'mensaje' => 'La fecha de inicio de la numeración es menor a la del documento'
                        );
        
                        return $respuesta;
                    }else if ($date_end > $fechaFinal){
    
                        $respuesta = array(
                            'error' => true,
                            'data'  => array(),
                            'mensaje' => 'La fecha de la numeración está vencida'
                        );
        
                        return $respuesta;
                        
                    }else {
    
                        $DocNumVerificado = $numeroSiguiente;
                    }
                }else {
                    $DocNumVerificado = $numeroSiguiente;
                }
                
				
			} else {

				$respuesta = array(
					'error' => true,
					'data'  => array(),
					'mensaje' => 'La serie de la numeración está llena'
				);

				return $respuesta;
			}
		} else {

			$respuesta = array(
				'error' => true,
				'data'  => array(),
				'mensaje' => 'No se encontro la serie de numeración para el documento'
			);

			return $respuesta;
		}

        if(is_numeric($DocNumVerificado) && $DocNumVerificado > 0){

            return $DocNumVerificado;

        }else{

            return $respuesta;
        }

	}
}