<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class DropBox extends REST_Controller {

	private $pdo;
  private $api_key = "sl.BUTg1ke8PkLWMGDrz_Y0aSupmHlZIGxrOKxo7Y9cYzqwy8g8xWSs0hbZCHXXqsgg4FREiwMcWX9iiyoqvGj72NuxC3B0pkuBJEEmicK6MUitT6JejCkUFbE2WAxcuf1AKmhuUCZQ";
	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);

	}


    public function folder_post(){
      $Data = $this->post();
      $path = $Data['path'] ?? '';
      $mode = $Data['mode'];
      $config = array(
        "create" => ["dropURL" => "https://api.dropboxapi.com/2/files/create_folder_v2",
                            "fields" => '{"autorename":false,"path":"'.$path.'"}'],
        "search" => ["dropURL" => "https://api.dropboxapi.com/2/files/list_folder",
                            "fields" => '{
                              "include_deleted": false,
                              "include_has_explicit_shared_members": false,
                              "include_media_info": false,
                              "include_mounted_folders": true,
                              "include_non_downloadable_files": true,
                              "path": "/'.$path.'/",
                              "recursive": false
                          }']
      );

      $curl = curl_init();
      
      curl_setopt_array($curl, array(
        CURLOPT_URL => $config[$mode]['dropURL'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>$config[$mode]['fields'],
        CURLOPT_HTTPHEADER => array(
          'Authorization: Bearer '.$this->api_key,
          'Content-Type: application/json'
        ),
      ));

      $response = curl_exec($curl);
      $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      curl_close($curl);
      if($http_code == 200){
        $respuesta = array(
          'error' => false,
          'data' => [],
          'mensaje' => 'Operacion exitosa'
      );
      }else{
        $respuesta = array(
          'error' => true,
          'data' => json_decode($response,true),
          'mensaje' => 'Error'
        );
      }
      $this->response($respuesta);
    }

    public function uploadFile_post(){
    $Data = $this->post();
    if (
      !isset($Data['dma_card_code'])
    ) {

      $respuesta = array(
        'error' => true,
        'data'  => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }
    
    $url = $this->createShareLink("{$Data['path']}/{$Data['name']}")['url'];


    $sqlInsert = "INSERT INTO dmsa( dma_card_code, dma_attach, dma_description)
      VALUES (:dma_card_code, :dma_attach, :dma_description)";


    $resInsert = $this->pedeo->insertRow($sqlInsert, array(
      ':dma_card_code' => $Data['dma_card_code'],
      ':dma_attach' => $url,
      ':dma_description' => $Data['dma_description']
    ));


    if (is_numeric($resInsert) && $resInsert > 0) {
      $respuesta = array(
        'error'   => false,
        'data'    => $resInsert,
        'mensaje' => 'Anexo agregado'
      );
    } else {

      $respuesta = array(
        'error'   => true,
        'data'     => $resInsert,
        'mensaje' => 'No se pudo agreagar anexo'
      );
    }
      $this->response($respuesta);
    }

    private function createShareLink($path){
      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
          "path": "'.$path.'",
          "settings": {
            "audience": "public",
            "access": "viewer",
            "requested_visibility": "public",
            "allow_download": true
          }
        }',
        CURLOPT_HTTPHEADER => array(
          'Authorization: Bearer '.$this->api_key,
          'Content-Type: application/json'
        ),
      ));

      $response = curl_exec($curl);
      $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      curl_close($curl);

      $response = json_decode($response,true);
      $resp = [];
			
      if($http_code == 200){
        $url = $response['url'];
			  $url = str_replace("dl=0","raw=1",$url);
			  $resp =  ["path_lower"=> $response['path_lower'],'url'=>$url];
      }
      return $resp;
    }
}