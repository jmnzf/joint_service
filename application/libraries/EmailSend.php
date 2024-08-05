<?php

// MONEDAS
defined('BASEPATH') OR exit('No direct script access allowed');

// require_once(APPPATH.'/libraries/REST_Controller.php');
// use Restserver\libraries\REST_Controller;

class EmailSend {

	private $pdo;
    private $ci;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		// parent::__construct();
        $this->ci =& get_instance();
		$this->ci->load->database();
		$this->pdo = $this->ci->load->database('pdo', true)->conn_id;
        $this->ci->load->library('pedeo', [$this->pdo]);
        $this->ci->load->library('email');

	}

  //Crear nueva moneda
	public function send($to,$attach = "",$subject = "",$message = ""){
        // Configuración del correo electrónico
        $config['protocol'] = 'smtp';
        $config['smtp_host'] = SMTP_EMAIL;
        $config['smtp_port'] = '465';
        $config['smtp_crypto'] = 'ssl';
        $config['smtp_user'] = USER_EMAIL;
        $config['smtp_pass'] = PWD_EMAIL;
        $config['charset'] = 'UTF-8';
        $config['mailtype'] = 'html';
        $config['newline'] = "\r\n";
        $config['max_size'] = 2048; // Tamaño máximo en kilobytes (2MB)
        $this->ci->email->initialize($config);
        $this->ci->email->from(USER_EMAIL, 'no-reply@maken.com.co');
        $this->ci->email->to($to);
        $this->ci->email->attach($attach);

        $this->ci->email->subject($subject);
        $this->ci->email->message($message);

        if ($this->ci->email->send()) {

            $respuesta = array(
                'error' => false,
                'data' => $to,
                'mensaje' => 'Correo electrónico enviado correctamente'
            );
        } else {
            $respuesta = array(
                'error' => false,
                'data' => $this->ci->email->print_debugger(),
                'mensaje' => 'Error al enviar el correo electrónico:'
            );
        }
        
        return $respuesta;

    }
}
