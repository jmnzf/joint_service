<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class DropBox extends REST_Controller {

	private $pdo;
  private $api_key = "HfeWgQQMzC4AAAAAAAAAAVWMFAaPCQSnaAIv7UvQS_7VS0Np6rH3UlXzQWao7AWl";
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
        $curl = curl_init();
        $Data = $this->post();
        $path = $Data['path'];

        $filePath = "{$path}/{$_FILES['file']['name']}";
        move_uploaded_file($_FILES['file']['tmp_name'], "serpentservice/application/attachment/".$_FILES['file']['name']);
   
        // $cheaders = array('Authorization: Bearer '.$this->api_key	,
        //                     'Content-Type: application/octet-stream',
        //                     'Dropbox-API-Arg: {"path":"'.$filePath.'", "mode":"add"}');
        
        //   $ch = curl_init('https://content.dropboxapi.com/2/files/upload');
        //   curl_setopt($ch, CURLOPT_HTTPHEADER, $cheaders);
        //   curl_setopt($ch, CURLOPT_PUT, true);
        //   curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        //   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //   curl_setopt($ch, CURLOPT_INFILE, $fp);
        //   curl_setopt($ch, CURLOPT_INFILESIZE, $size);
        //   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        //   $response = curl_exec($ch);       
        
        // 	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //   curl_close($ch);
        //   fclose($fp);
        if($http_code == 200){
          $respuesta = array(
            'error' => true,
            'data' => $this->createShareLink($path),
            'mensaje' => 'Error'
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
          "path":"'.'{
            "path":"'.$path.'",
            "settings":{
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

			$url = $response[0]['url'];
			$url = str_replace("dl=0","raw=1",$url);
			$resp =  ["path_lower"=> $response[0]['path'],'url'=>$url];
      if($http_code == 200){
        $respuesta = array(
          'error' => false,
          'data' => $resp,
          'mensaje' => 'Operacion exitosa'
      );
      }else{
        $respuesta = array(
          'error' => true,
          'data' => json_decode($response,true),
          'mensaje' => 'Error'
        );
      }
      return $respuesta;
    }
}