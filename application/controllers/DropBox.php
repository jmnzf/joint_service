<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . '/libraries/REST_Controller.php');
require_once(APPPATH . '/asset/vendor/autoload.php');
use Restserver\libraries\REST_Controller;
// use GuzzleHttp\Client;

class DropBox extends REST_Controller
{

  private $pdo;
  private $api_key = "sl.BVTBaAPcFXeE22ygH51X8he5DdyTp6g5vkcFZYph8tsyyKqNIpMkjajZZ2z1y52iFIZQwv8tl8hIXdRNXdu6_Xv3zuVyzk5WrPUkn4Fq35yuFWQOfd-n5_GiGiBgVdn3uAhB2R_F";
  public function __construct()
  {

    header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
    header("Access-Control-Allow-Origin: *");

    parent::__construct();
    $this->load->database();
    $this->pdo = $this->load->database('pdo', true)->conn_id;
    $this->load->library('pedeo', [$this->pdo]);

  }


  public function folder_post()
  {
    $Data = $this->post();
    $path = $Data['path'] ?? '';
    $mode = $Data['mode'];
    $config = array(
      "create" => [
        "dropURL" => "https://api.dropboxapi.com/2/files/create_folder_v2",
        "fields" => '{"autorename":false,"path":"' . $path . '"}'
      ],
      "search" => [
        "dropURL" => "https://api.dropboxapi.com/2/files/list_folder",
        "fields" => '{
                              "include_deleted": false,
                              "include_has_explicit_shared_members": false,
                              "include_media_info": false,
                              "include_mounted_folders": true,
                              "include_non_downloadable_files": true,
                              "path": "/' . $path . '/",
                              "recursive": false
                          }'
      ]
    );

    $curl = curl_init();

    curl_setopt_array(
      $curl,
      array(
      CURLOPT_URL => $config[$mode]['dropURL'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $config[$mode]['fields'],
      CURLOPT_HTTPHEADER => array(
          'Authorization: Bearer ' . $this->api_key,
          'Content-Type: application/json'
        ),
      )
    );

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($http_code == 200) {
      $respuesta = array(
        'error' => false,
        'data' => [],
        'mensaje' => 'Operacion exitosa'
      );
    } else {
      $respuesta = array(
        'error' => true,
        'data' => json_decode($response, true),
        'mensaje' => 'Error'
      );
    }
    $this->response($respuesta);
  }

  public function uploadFile_post()
  {
    $Data = $this->post();

    if (
      !isset($Data['code']) or
      !isset($Data['table']) or
      !isset($Data['description'])
    ) {

      $respuesta = array(
        'error' => true,
        'data' => array(),
        'mensaje' => 'La informacion enviada no es valida'
      );

      $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

      return;
    }
    
    $resultSet = $this->createShareLink("{$Data['path']}/{$Data['name']}"); // 
    
    $respuesta = array(
      'error' => true,
      'data' => [],
      'mensaje' => 'No se pudo agreagar anexo'
    );

    if (isset($resultSet['url'])) {
      $table = $Data['table'];
      // 
      $fields = "code, attach, description";
      $values = ":code, :attach, :description";
      // 
      $insert = array(
          ':code' => $Data['code'],
          ':attach' => $resultSet['url'],
          ':description' => $Data['description']
      );
      // 
      if (!empty($Data['type'])) {
        // AGREGAR TYPE
        $fields .= ",type";
        $values .= ",:type";
        // 
        $insert[":type"] = $Data['type'];
      }
      // 
      $sqlInsert = "INSERT INTO {$table} ({$fields}) VALUES ({$values})";
      // 
      $resInsert = $this->pedeo->insertRow($sqlInsert, $insert);

      if (is_numeric($resInsert) && $resInsert > 0) {
        $respuesta = array(
          'error' => false,
          'data' => $resInsert,
          'mensaje' => 'Anexo agregado'
        );
      } else {

        $respuesta = array(
          'error' => true,
          'data' => $resInsert,
          'mensaje' => 'No se pudo agreagar anexo'
        );
      }
    }

    $this->response($respuesta);
  }

  private function createShareLink($path)
  {
    $curl = curl_init();
    $token = $this->getToken();
    curl_setopt_array(
      $curl,
      array(
      CURLOPT_URL => "https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => '{
          "path": "' . $path . '",
          "settings": {
            "audience": "public",
            "access": "viewer",
            "requested_visibility": "public",
            "allow_download": true
          }
        }',
      CURLOPT_HTTPHEADER => array(
          'Authorization: Bearer ' . $token,
          'Content-Type: application/json'
        ),
      )
    );

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $response = json_decode($response, true);
    $resp = [];

    if ($http_code == 200) {
      $url = $response['url'];
      $url = str_replace("dl=0", "raw=1", $url);
      $resp = ["path_lower" => $response['path_lower'], 'url' => $url];
    }
    
    return $response;
  }

  public function index_get()
  {
    $key = DROPBOX_APP_KEY; //env('DROPBOX_APP_KEY');
    $secret = DROPBOX_APP_SECRET; //env('DROPBOX_APP_SECRET');
    $respuesta = array(
      'error' => true,
      'data' => [],
      'mensaje' => 'No se pudo registrar el token'
    );
    try {
      $client = new GuzzleHttp\Client();
      $res = $client->request("POST", "https://{$key}:{$secret}@api.dropbox.com/oauth2/token", [
        'verify' => false,
        'form_params' => [
          'grant_type' => 'refresh_token',
          'refresh_token' => DROPBOX_REFRESH_TOKEN
        ]
      ]);
      if ($res->getStatusCode() == 200) {
        $result = json_decode($res->getBody(), TRUE);

        $sqlInsert = "INSERT INTO trft (rft_token, rft_created) VALUES(:rft_token, :rft_created)";

        $resInsert = $this->pedeo->insertRow($sqlInsert, array(
          ':rft_token' => $result['access_token'],
          ':rft_created' => date('Y-m-d')
        ));

        if (is_numeric($resInsert) && $resInsert > 0) {

          $respuesta = array(
            'error' => false,
            'data' => $resInsert,
            'mensaje' => 'Token registrado con exito'
          );

        }
      }
    } catch (Exception $e) {
    }

    $this->response($respuesta);
  }

  private function getToken(){
    

    $sqlSelect = "SELECT * from trft order by rft_id DESC LIMIT 1";

    $respuesta = null;

    $resSelect = $this->pedeo->queryTable($sqlSelect, array());

    if(isset($resSelect[0])){

      $respuesta = $resSelect[0]['rft_token'];

    }

    return $respuesta;
  }

  public function getAccessToken_get(){
    $respuesta = array(
      'error' => true,
      'data' => [],
      'mensaje' => 'Busqueda sin resultados'
    );
    $token = $this->getToken();

    if(!is_null($token)){
      $respuesta = array(
        'error' => false,
        'data' => ['token' => $token],
        'mensaje' => '  '
      );
    }

    $this->response($respuesta);
  }
}

?>