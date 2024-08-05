<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Generic {

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

}


?>