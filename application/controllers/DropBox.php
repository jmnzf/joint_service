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
  private $pass_secret = "Serpent$$2023@!";
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

      if (!empty($Data['docentry'])) {
        // AGREGAR TYPE
        $fields .= ",docentry";
        $values .= ",:docentry";
        // 
        $insert[":docentry"] = $Data['docentry'];
      }

      if(isset($Data['cardtype'])){
         // AGREGAR TYPE
         $fields .= ",cardtype";
         $values .= ",:cardtype";
         // 
         $insert[":cardtype"] = $Data['cardtype'];
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
      $res = $client->request("POST", "https://{$key}:{$secret}@api.dropbox.com/  /token", [
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

  private function getToken() {


    $this->pedeo->queryTable('CREATE EXTENSION IF NOT EXISTS pgcrypto', array());
    
    $sqlSelect = "SELECT pgp_sym_decrypt(rft_client_id::bytea, :data) AS rft_client_id, 
                  pgp_sym_decrypt(rft_client_secret::bytea, :data) AS rft_client_secret,
                  rft_token
                  FROM trft";

    $refeshToken = null;
    $token = null;

    $resSelect = $this->pedeo->queryTable($sqlSelect, array(':data' => $this->pass_secret));

    if(isset($resSelect[0])){

      $refeshToken = $resSelect[0]['rft_token'];
      $client = $resSelect[0]['rft_client_id'];
      $secret = $resSelect[0]['rft_client_secret'];

      $url = 'https://api.dropbox.com/oauth2/token';

      // Datos que deseas enviar en la solicitud POST
      $data = array(
        "grant_type" => "refresh_token",
        "refresh_token" => $refeshToken,
        "client_id" => $client,
        "client_secret" => $secret
      );

      // Inicializar cURL
      $ch = curl_init($url);

      // Configurar las opciones de cURL para una solicitud POST
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      // Ejecutar la solicitud y obtener la respuesta
      $response = json_decode(curl_exec($ch), true);

      // Verificar si ocurrieron errores
      if (curl_errno($ch)) {
          echo 'Error en la solicitud cURL: ' . curl_error($ch);
      }

      // Cerrar la sesión cURL
      curl_close($ch);
      $token = $response['access_token'];
    }

    return $token;
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

  public function deleteFile_post(){
    $Data = $this->post();

    if(!isset($Data["table"]) or 
       !isset($Data["id"])){

    }

    $token = $this->getToken();
    $client = new GuzzleHttp\Client([
      'base_uri' => 'https://api.dropboxapi.com/2/',
      'headers' => [
          'Authorization' => 'Bearer '.$token,
          'Content-Type' => 'application/json',
      ]
    ]);

    try {
      $this->pedeo->trans_begin();
      //  CONSULTA DE ARCHIVO 
      $sqlAnnexe = "SELECT * from {table} where id = :id";
      $sqlAnnexe = str_replace("{table}", $Data['table'], $sqlAnnexe);
      $resSelect = $this->pedeo->queryTable($sqlAnnexe, array(':id' => $Data['id']));

      if(!isset($resSelect[0])){
        $respuesta = array(
          'error' => true,
          'data' => [],
          'mensaje' => "Anexo no encontrado"
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;
      }
      // BUSQUEDA DE ARCHIVO POR URL COMPARTIDA
      $searchFileIntoDropBox = $client->post('sharing/get_shared_link_metadata', [
        'json' => [
            "url" => $resSelect[0]['attach'],  // URL compartida
          ]
        ]);
  
        $data = json_decode($searchFileIntoDropBox->getBody(), true);

        if(isset($data['error'])){
          $respuesta = array(
            'error' => true,
            'data' => [],
            'mensaje' => "Anexo no encontrado"
          );

          $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

					return;

        }

        $deleteSql = "DELETE  FROM ".$Data['table']." WHERE id = :id";
        $resDelete = $this->pedeo->deleteRow($deleteSql, [':id' => $Data['id']]);

        $deleteResponse = $client->post('files/delete_v2', [
            'json' => [
                "path" => $data['path_lower']
            ]
        ]);           
        
       
      $deleteResponse = json_decode($deleteResponse->getBody(), true);
      if(isset($deleteResponse['error'])){

				$this->pedeo->trans_rollback();
        $respuesta = array(
          'error' => true,
          'data' => [],
          'mensaje' => "Anexo no encontrado"
        );

        $this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

        return;

      }
      $respuesta = array(
        'error' => false,
        'data' => [],
        'mensaje' => "Anexo ha sido eliminado"
      );
      $this->pedeo->trans_commit();      
  
  } catch (\GuzzleHttp\Exception\RequestException $e) {
    $respuesta = array(
      'error' => true,
      'data' => [],
      'mensaje' => $e->getMessage()
    );

  }

  $this->response($respuesta);

  }
}

?>