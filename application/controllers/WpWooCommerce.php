<?php
// RECIBE LOS PEDIDOS DESDE WOOCOMMERCE DE WORDPRESS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class WpWooCommerce extends REST_Controller {

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

    //CREAR NUEVA FORMA DE PAGO
	public function createInvoice_post(){

        $Data = $this->post();

        // Ruta del archivo donde se guardarán los datos
        $file_path = 'invoice_data.txt';  // Puedes cambiar la ruta según tu estructura de carpetas

        // Convertir los datos a formato JSON (o puedes adaptarlo a otro formato si prefieres)
        $data_to_save = json_encode($Data, JSON_PRETTY_PRINT);

        // Guardar los datos en el archivo
        file_put_contents($file_path, $data_to_save, FILE_APPEND | LOCK_EX);



        $respuesta = array(
            'error'   => false,
            'data' 	  => [],
            'mensaje' => 'Proceso finalizado con exito'
        );

        

        $this->response($respuesta);
	}










}
