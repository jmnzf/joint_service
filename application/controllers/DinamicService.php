<?php

//CREACION DE CRUD DINAMICOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');

use LDAP\Result;
use Restserver\libraries\REST_Controller;

class DinamicService extends REST_Controller {

	private $pdo;
	private $NombreSecuencia;
	private $NombreTablaFinal;
	private $NombreControlador;
	private $idMenu;
	private $FileConfig;
	private $ConfigOri;
	private $Json;
	private $ArrSelect2;

	public function __construct(){

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);

		$this->ArrSelect2 = [];

	}

  	//CREAR NUEVO CRUD
	public function createDinamicService_post(){

        $Data = $this->post();


        if( !isset( $Data['data'] ) ){
            $this->response(array("error" => true, "data" =>'',"mensaje" => "Falta el parametro data"));
            exit;
        }


        $Data = json_decode($Data['data'], true);

		// $Data = $Data['data'];
// print_r($Data);exit;
		if( !isset( $Data['method_static'] ) ){
            $this->response(array("error" => true, "data" =>'',"mensaje" => "Falta el parametro metodo"));
            exit;
        }


        $Controlador = 'DynamicPage';// CONTROLADOR PREDETERMINADO

		$company = $this->pedeo->queryTable("SELECT main_folder FROM PARAMS",array());

        if(!isset($company[0])){
			$respuesta = array(
	           'error' => true,
	           'data'  => $company,
	           'mensaje' =>'no esta registrada la ruta de la empresa'
		    );

          	$this->response($respuesta, REST_Controller::HTTP_BAD_REQUEST);

          	return;
		}

		$JSON = "/var/www/html/".$company[0]['main_folder']."/assets/json/config_dinamic.json";

		$dataJson = @file_get_contents($JSON);
		$this->ConfigOri = $dataJson;
		$this->Json = $JSON;


		// $this->createGet( $Data['form_table'], $Data['form'], $Data['primary_key'] );


        $this->NombreSecuencia   = $this->createSequence( $Data['form_table'], $Data['primary_key'] );

        $this->NombreTablaFinal  = $this->createTable($Data['form'], $Data['form_table'], $Data['primary_key'], $this->NombreSecuencia, $Data['form_desctable']);

		if ( $Data['method_static'] == 0 ){
 			$this->NombreControlador = $this->createHeader($Data['nombre_controlador'], $Data['form'],  $this->NombreTablaFinal, $Data['primary_key']);
		}else{
			$this->NombreControlador = "DinamicCrud";
		}

        $this->idMenu =  $this->createMenu($Data['form_name'], $Controlador);

		$this->FileConfig = $this->createConfigFile($this->idMenu,$this->NombreControlador,$Data['form'],$Data['primary_key'],$Data['colum_status']);
		// $this->FileConfig = $this->createConfigFile(100,"DinamicCrud",$Data['form'],$Data['primary_key']);


		$this->mergeConfig($this->FileConfig, $dataJson, $JSON);


		$this->insertConfig();
		$this->response(array("error" => false, "data" =>[],"mensaje" => "Proceso finalizado"));
		// $this->response( $this->FileConfig );
	}

	public function getForms_get(){

		$sqlSelect = " SELECT * FROM cffd ";
		$resSelect = $this->pedeo->queryTable($sqlSelect, array());

		$respuesta = array(
			'error' => true,
			'data'  => $resSelect,
			'mensaje' =>'Busqueda sin resultados'
		 );

		if( isset($resSelect[0]) ){

			$respuesta = array(
				'error' => false,
				'data'  => $resSelect,
				'mensaje' =>''
			);
		}

		$this->response( $respuesta );
	}


	private function mergeConfig($FileConfig, $dataJson, $JSON){

		$obj = json_decode($dataJson, true);
		$obj2 = json_encode($FileConfig);

		array_push($obj ,  json_decode($obj2, true));

		$archivo = fopen($JSON, "w+b");


        if ( $archivo ) {

            file_put_contents($JSON, json_encode($obj));
            fclose($archivo);

        }else{

           $this->cancelProcess("No se pudo añadir la configuracion al archivo ".json_encode($archivo));

        }

	}

	private function insertConfig(){

		$sqlInsert = "INSERT INTO cffd(ffd_table, ffd_config, ffd_controller)VALUES(:ffd_table, :ffd_config, :ffd_controller)";

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(

			':ffd_table'      => $this->NombreTablaFinal,
			':ffd_config'     => json_encode($this->FileConfig),
			':ffd_controller' => $this->NombreControlador
		));

		if (is_numeric($resInsert) && $resInsert > 0 ){

		}else{
			$this->cancelProcess("No se pudo guardar la configuración ".json_encode($resInsert));
		}
	}



	private function createConfigFile($idMenu, $CtlrService, $form, $llave, $status=""){

		$json = new \stdClass();
		$contenidoDataTable =  new \stdClass();
		$contenidoButtons = new \stdClass();
		$contenidoForm = new \stdClass();


		$json->ctrl = $idMenu;
		$json->get = $CtlrService;
		$json->create = $CtlrService."/create";
		$json->update = $CtlrService."/update";
		$json->delete = "";
		$json->apiById = "";
		$json->apiDetailById = "";
		$json->apiDownload = "";
		$json->table = $this->NombreTablaFinal;
		$json->pkey  = $llave;


		$contenidoDataTable->columns = [];
		$contenidoDataTable->buttons = [];
		$contenidoButtons->text = "Editar";
		$contenidoButtons->action = "fun_index.edit_docById";
		array_push($contenidoDataTable->buttons, $contenidoButtons);

		foreach ($form as $key => $value) {

			$contenidoColumns = new \stdClass();

			if ( isset($value['colum_status']) && $value['colum_status'] == 1 ) {
				$contenidoColumns->data  = $value['field_name'];
				$contenidoColumns->name  = $value['field_name'];
				$contenidoColumns->title = $value['colum_title'];


				array_push($contenidoDataTable->columns, $contenidoColumns);
			}
		}



		$contenidoDataTable->order=[[0, "desc"]];
		$contenidoDataTable->statusField = !empty($status) ? $status: "";

		$json->datatable = $contenidoDataTable;

		$contenidoForm->field = [];
		$contenidoForm->rules = [];

		foreach ($form as $key => $value) {

			$contenidoField = new \stdClass();
			$contenidoRules = new \stdClass();

			switch($value['field_type']){

				case 'input':
					$contenidoField->type    = $value['input_type'];
					$contenidoField->title   = $value['colum_title'];
					$contenidoField->id      = $value['field_name'];
					$contenidoField->col     = $value['column_size'];


					array_push($contenidoForm->field, $contenidoField);
					break;

				case 'textarea':
					$contenidoField->type  = $value['field_type'];
					$contenidoField->title = $value['colum_title'];
					$contenidoField->id    = $value['field_name'];
					$contenidoField->col   = $value['column_size'];
					$contenidoField->col   = $value['column_size'];
					$contenidoField->rows  = $value['texarea_rows'];

					array_push($contenidoForm->field, $contenidoField);
					break;

				case 'select':
					$contenidoField->type  = $value['field_type'];
					$contenidoField->title = $value['colum_title'];
					$contenidoField->id    = $value['field_name'];
					$contenidoField->col   = $value['column_size'];
					$contenidoField->data  = $value['fill_select'];

					array_push($contenidoForm->field, $contenidoField);
					break;

				case 'hidden':
					$contenidoField->type  = $value['field_type'];
					$contenidoField->title = $value['colum_title'];
					$contenidoField->id    = $value['field_name'];

					array_push($contenidoForm->field, $contenidoField);
					break;

				case 'select2':

					foreach ($this->ArrSelect2 as $key => $select) {
						if (trim($select['name']) == trim($value['field_name']) ){
							$get = "?id=".$select['id']."&text=".$select['text']."&table=".$select['table'];

							$contenidoField->type     = $value['field_type'];
							$contenidoField->title    = $value['colum_title'];
							$contenidoField->id       = $value['field_name'];
							$contenidoField->col      = $value['column_size'];
							$contenidoField->data     = [];
							$contenidoField->api      = "DinamicCrud/select".$get;
						}
					}

					array_push($contenidoForm->field, $contenidoField);
					break;

			}

			if ( isset( $value['field_required'] )){

				if ($value['field_required'] == 1 ||  $value['field_required'] == '1' ){
					$requerido = new \stdClass();
					$requerido->required = 'true';
					$campo = $value['field_name'];
					$contenidoRules->$campo = $requerido;

					array_push($contenidoForm->rules, $contenidoRules);
				}
			}

		}

		$contenidoForm->fieldId = $llave;
		$contenidoForm->mdSize = null;

		$json->form = $contenidoForm;


		return $json;

	}

	private function createMenu($NombreMenu, $Controlador){

		$sqlInsert = "INSERT INTO menu( men_nombre, men_icon, men_controller, men_action, men_sub_menu, men_id_menu, men_id_estado )
					 VALUES (:men_nombre, :men_icon, :men_controller, :men_action, :men_sub_menu, :men_id_menu, :men_id_estado)";

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':men_nombre' => $NombreMenu,
			':men_icon' => "",
			':men_controller' => $Controlador,
			':men_action' => "Index",
			':men_sub_menu' => 0,
			':men_id_menu' => 161,
			':men_id_estado' => 1
		));

		if ( is_numeric($resInsert) && $resInsert > 0 ){

		}else{

			$this->cancelProcess("No se pudo insertar el menu ".json_encode($resInsert));
		}

		return $resInsert;

	}

	//CREA LA SECUENCIA DE LA TABLA
	private function createSequence($NombreTabla, $PrimaryKey){

		$prefijo = "seq";
		$ac = 0;
		$hacer = true;
		$nombre = $NombreTabla.'_'.$PrimaryKey.'_'.$prefijo;

		while ( $hacer ) {

		  $resSequence = $this->pedeo->queryTable("select currval('".$nombre."')");

			if ( isset($resSequence['error']) && $resSequence['error'][0] == '42P01' ){
				$hacer = false;


				$sqlSecuencia = "CREATE SEQUENCE IF NOT EXISTS public.".$nombre."
												INCREMENT 1
												START 1
												MINVALUE 1
												MAXVALUE 2147483647
												CACHE 1";

				$sqlPropietarioSecuencia =	"ALTER SEQUENCE public.".$nombre." OWNER TO postgres";

				$sqlPermisosSecuencia = "GRANT ALL ON SEQUENCE public.".$nombre." TO postgres WITH GRANT OPTION";

				$resSecuencia = $this->pedeo->queryTable($sqlSecuencia, array());
				$this->pedeo->queryTable($sqlPropietarioSecuencia, array());
				$this->pedeo->queryTable($sqlPermisosSecuencia, array());

				if ( count($resSecuencia) == 0 ){

				}else{

					$this->cancelProcess("No se puedo crear la secuencia ".json_encode($resSecuencia));

				}


			}else{
				$ac++;
				$nombre = $NombreTabla.'_'.$PrimaryKey.$ac.'_'.$prefijo;
			}
		}

		return $nombre;
	}

	// CREA LA TABLA DINAMICAMENTE
	private function createTable($form,$table,$primarykey,$secuencia,$Comentario){

		$hacer = true;
		$acc = 0;

		while ( $hacer ) {

			$resSearch = $this->pedeo->queryTable("SELECT * FROM ".$table, array());

			if ( isset($resSearch['error']) && $resSearch['error'][0] == '42P01' ){

				$hacer = false;
				$CamposCreateTable = $this->getCreateField($form,$table,$primarykey,$secuencia);
				$sqlTabla = "CREATE TABLE IF NOT EXISTS public.".$table." (".$CamposCreateTable.")";


				$sqlTableSpace = "TABLESPACE pg_default";

				$sqlPropietarioTabla = "ALTER TABLE IF EXISTS public.".$table." OWNER to postgres";

				$sqlPermisosTabla = "GRANT ALL ON TABLE public.".$table." TO postgres WITH GRANT OPTION";

				$sqlComentariosTabla = "COMMENT ON TABLE public.".$table." IS '".$Comentario."'";

				$resTabla = $this->pedeo->queryTable($sqlTabla, array());
				$this->pedeo->queryTable($sqlTableSpace);
				$this->pedeo->queryTable($sqlPropietarioTabla);
				$this->pedeo->queryTable($sqlPermisosTabla);
				$this->pedeo->queryTable($sqlComentariosTabla);



				if ( count($resTabla) == 0 ){

				}else{

					$this->cancelProcess("No se pudo crear la tabla ".json_encode($resTabla));
				}



			}else{
				$acc++;
				$table = $table.$acc;
			}

		}


		return $table;

	}

  // OBTIENE LOS CAMPOS DEL FORMULARIO SEPARADOS POR COMA
	private function getCreateField($data, $NombreTabla, $PrimaryKey, $Secuencia = 'Prueba'){

		$array = [];
		if (!is_array( $data )){
			$respuesta = array(
				"error"   => true,
				"data"    => [],
				"mensaje" => "No se encontraron los datos del formulario"
			);
			$this->response($respuesta);
			exit;
		}
		$campos = "";
		foreach ($data as $key => $element) {

			if ( $element["field_name"] != $PrimaryKey ){

				$campos .= "\n";
				switch ($element['date_type']) {
					case 'text':
						$campos.= $element["field_name"].' '.'character varying('.$element["length_field"].') COLLATE pg_catalog.'."default ,";
						break;
					case 'date':
						$campos.= $element["field_name"].' '."date ,";
						break;
					case 'datetime':
						$campos.= $element["field_name"].' '."timestamp without time zone ,";
						break;
					case 'numeric':
						$campos.= $element["field_name"].' '."numeric ,";
						break;
					case 'longtext':
						$campos.= $element["field_name"].' '.'text  COLLATE pg_catalog.'."default ,";
						break;
					default:
						// code...
						break;
				}
			}
		}
		$campos.= "\n";
		$campos.= $PrimaryKey." integer PRIMARY KEY NOT NULL DEFAULT nextval('".$Secuencia."'::regclass)";

		return $campos;

	}

	private function createInsert($data, $table, $primarykey){


		$insert = "INSERT INTO ".$table;
		$campos = "";
		$values = "";
		$arrayInsert = "";
		$arrayValidateInsert = "";

		foreach ( $data as $key => $element ) {

			if ($element['field_name'] != $primarykey ){

				$campos.= trim($element['field_name']).', ';
				$values.= trim(':'.$element['field_name']).', ';
			}

		}

		$campos = substr(trim($campos), 0, -1);
		$values = substr(trim($values), 0, -1);

		$arryCampos = explode(",", $values);

		foreach ($arryCampos as $key => $field) {
			$arrayInsert.= "'".trim($field)."' => "."\$Data['".str_replace(":", "",trim($field))."'], \n";

			$arrayValidateInsert.= "!isset(\$Data['".str_replace(":", "",trim($field))."']) OR \n";

		}

		$arrayInsert = substr(trim($arrayInsert), 0, -1);
		$arrayValidateInsert = substr(trim($arrayValidateInsert), 0, -2);


		$insert.= '('.$campos.')';
		$insert.= ' VALUES('.$values.')';
		$arrayInsert ='array('."\n".$arrayInsert.')';

		$result = "";
		$result .= "public function create_post(){";
		$result .= "\n";
		$result .= "\n";
		$result .= "\$Data = \$this->post();";
		$result .= "\n";
		$result .= "\n";
		$result .= "if( ".trim($arrayValidateInsert)."){";
		$result .= "\n";
		$result .= "\n";
		$result .= $this->res('true','array()','Faltan parametros');
		$result .= "}";
		$result .= "\n";
		$result .= "\n";
		$result .= "\$resInsert = \$this->pedeo->insertRow('".$insert."', ".$arrayInsert.");";
		$result .= "\n";
		$result .= "\n";
		$result .= "if ( is_numeric(\$resInsert) && \$resInsert > 0) { ";
		$result .= "\n";
		$result .= "\n";
		$result .= $this->res('false', '$resInsert', 'Registro insertado con exito');
		$result .= "} else { ";
		$result .= "\n";
		$result .= "\n";
		$result .= $this->res('true', '$resInsert', 'Error al insertar el registro');
		$result .= "\n";
		$result .= "}";
		$result .= "\n";
		$result .= "\n";
		$result .= "\n";
		$result .= "}";

		return $result;

	}

	private function createUpdate($data, $table, $primaryKey){
		$update = "UPDATE ".$table." SET ";
		$campos = "";
		$soloCampos = "";
		$arrayUpdate = "";
		$arrayValidateUpdate = "";

		foreach ( $data as $key => $element ) {

			if ( $element['field_name'] != $primaryKey ){

				$campos.= trim($element['field_name']).' = '. trim(':'.$element['field_name']) .' , ';

				$soloCampos.= trim($element['field_name']).', ';
			}

		}

		$campos = substr(trim($campos), 0, -1);
		$soloCampos = substr(trim($soloCampos), 0, -1);

		$arryCampos = explode(",",$soloCampos);


		foreach ($arryCampos as $key => $field) {
			$arrayUpdate.= "'".trim($field)."' => "."\$Data['".str_replace(":", "",trim($field))."'], \n";
			$arrayValidateUpdate.= "!isset(\$Data['".str_replace(":", "",trim($field))."']) OR \n";
		}

		$arrayValidateUpdate.= "!isset(\$Data['".str_replace(":", "",trim($primaryKey))."']) OR \n";
		$arrayUpdate.= "'".trim($primaryKey)."' => "."\$Data['".str_replace(":", "",trim($primaryKey))."'], \n";

		$arrayValidateUpdate = substr(trim($arrayValidateUpdate), 0, -2);
		$arrayUpdate = substr(trim($arrayUpdate), 0, -1);

		$campos.= ' WHERE '. trim($primaryKey).' = '. trim(':'.$primaryKey);

		$arrayUpdate ='array('."\n".$arrayUpdate.')';

		$update = $update.$campos;

		$result = "";
		$result .= "public function update_post(){";
		$result .= "\n";
		$result .= "\n";
		$result .= "\$Data = \$this->post();";
		$result .= "\n";
		$result .= "\n";
		$result .= "if( ".trim($arrayValidateUpdate)."){";
		$result .= "\n";
		$result .= "\n";
		$result .= $this->res('true','array()','Faltan parametros');
		$result .= "}";
		$result .= "\n";
		$result .= "\n";
		$result .= "\$resUpdate = \$this->pedeo->updateRow('".$update."', ".$arrayUpdate.");";
		$result .= "\n";
		$result .= "\n";
		$result .= "if ( is_numeric(\$resUpdate) && \$resUpdate == 1 ) { ";
		$result .= "\n";
		$result .= "\n";
		$result .= $this->res('false', '$resUpdate', 'Registro actualizado con exito');
		$result .= "} else { ";
		$result .= "\n";
		$result .= "\n";
		$result .= $this->res('true', '$resUpdate', 'Error al actualizar el registro');
		$result .= "\n";
		$result .= "}";
		$result .= "\n";
		$result .= "\n";
		$result .= "\n";
		$result .= "}";

		return $result;
	}

	private function createGet($table, $data, $pkey){
		$campos ="";
		$complemento = "";
		$values ="";
		foreach ( $data as $key => $element ) {

			switch ($element['field_type']) {
				case 'select':
					$campos.=$this->infoSelect($element);
					break;

				case 'select2':
					$res = $this->infoSelect2($element,$table,$pkey);
					if(isset($res[0])){
						$campos.=$res[0];
						$complemento.=$res[1];
					}
					break;

				default:
					$campos.= trim($element['field_name']).' , ';
					break;
			}

		}

		if ( substr(trim($campos), -1) == ',' ){

			$campos = substr(trim($campos), 0, -1);
		}

		$sql = "SELECT ".$campos." FROM ".$table." ".$complemento;

		$result  = "";
		$result .= "public function index_get(){";
		$result .= "\n";
		$result .= "\$resSelect = \$this->pedeo->queryTable(\"".$sql."\",array());";
		$result .= "\n";
		$result .= "\n";
		$result .= "if ( isset(\$resSelect[0]) ) { ";
		$result .= "\n";
		$result .= "\n";
		$result .= $this->res('false', '$resSelect', '');
		$result .= "} else { ";
		$result .= "\n";
		$result .= "\n";
		$result .= $this->res('true', '$resSelect', 'Busqueda sin resultados');
		$result .= "\n";
		$result .= "}";
		$result .= "\n";
		$result .= "\n";
		$result .= "\n";
		$result .= "}";


		return $result;
	}

	private function infoSelect($element){

		$result = "";
		$campos = "CASE ";
		foreach ($element['fill_select'] as $key => $item) {
			if ( is_numeric($item['id']) ){
				$campos.=" WHEN ".$element['field_name']."::numeric = ".$item['id']." THEN '".$item['text']."'";
			}else{
				$campos.=" WHEN ".$element['field_name']." = '".$item['id']."' THEN '".$item['text']."'";
			}

		}
		if ( isset($element['fill_select'][0]) ){
			$campos.=" END AS ".$element['field_name'].", ";

			$result = $campos;
		}

		return $result;
	}

	private function infoSelect2($element,$table,$pkey){

		$result = [];
		$campos = "";
		$left  = "LEFT JOIN ";
		$sql    = "SELECT * FROM cffd WHERE ffd_id = :ffd_id";
		$ressql = $this->pedeo->queryTable( $sql, array( ':ffd_id' => $element['id_formulario'] ) );

		if ( isset($ressql[0]) ){
			$config = json_decode($ressql[0]['ffd_config']);

			$campos.= $config->table.".".$element['campo_formulario']." AS ".$element['field_name'].", ";


			$left.= $config->table." ON ".$table.".".($element['date_type'] == 'numeric' ? $element['field_name']."::numeric" : $element['field_name'])." = ".$config->table.".".$element['campoid_formulario']." ";

			array_push($result, $campos);
			array_push($result, $left);

			array_push($this->ArrSelect2, array( "id" =>$element['campoid_formulario'], "text" => $element['campo_formulario'], "table" => $config->table, "name" => $element['field_name'] ));

		}else{

			$this->cancelProcess('No se encontro el id del formulario # '.$element['id_formulario']."" );
		}

		return $result;
	}




	private function res($code,$data,$string){

		$result = "";

		$result .= "\$respuesta = array( \n";
		$result .= "'error'=>".$code.",'data'=>".$data.",'mensaje'=>'".$string."'";
		$result .= "\n";
		$result .= ");";
		$result .= "\n";
		$result .= "\n";
		$result .= "return \$this->response(\$respuesta);";
		$result .= "\n";


		return $result;
	}

	private function createHeader($controller, $form, $table, $primarik){



        $update = $this->createUpdate($form, $table, $primarik);
        $insert = $this->createInsert($form, $table, $primarik);
        $get    = $this->createGet($table,$form,$primarik);

        $controller = ucfirst($controller);
		$controller = str_replace(" ", "",trim($controller));
        $ac=0;
        $hacer = true;
        $file = $controller;
        $ruta = getcwd().'/application/controllers/';

        while($hacer){

            if( file_exists($ruta.$file.'.php') ){
                $ac++;
                $file = $controller.$ac;
            }else{
                $hacer = false;
            }
        }

        $result  = "";
		$result .= "\n";
        $result .= "\n";
        $result  = "defined('BASEPATH') OR exit('No direct script access allowed');";
        $result .= "\n";
        $result .= "require_once(APPPATH.'/libraries/REST_Controller.php');";
        $result .= "\n";
        $result .= "use Restserver\libraries\REST_Controller;";
        $result .= "\n";
        $result .= "\n";
        $result .= "\n";
        $result .= "\n";
        $result .= "class ".$file." extends REST_Controller {";
        $result .= "\n";
        $result .= "\n";
        $result .= "private \$pdo;";
        $result .= "\n";
        $result .= "public function __construct(){";
        $result .= "\n";
        $result .= "header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');";
        $result .= "\n";
        $result .= "header('Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding');";
        $result .= "\n";
        $result .= "header('Access-Control-Allow-Origin: *');";
        $result .= "\n";
        $result .= "parent::__construct();";
        $result .= "\$this->load->database();";
        $result .= "\n";
        $result .= "\$this->pdo = \$this->load->database('pdo', true)->conn_id;";
        $result .= "\n";
        $result .= "\$this->load->library('pedeo', [\$this->pdo]);";
        $result .= "\n";
        $result .= "}";
        $result .= "\n";
        $result .= "\n";
        $result .= "\n";
        $result .= "\n";
        $result .= $insert;
        $result .= "\n";
        $result .= "\n";
        $result .= "\n";
        $result .= "\n";
        $result .= $update;
        $result .= "\n";
        $result .= "\n";
        $result .= "\n";
        $result .= "\n";
        $result .= $get;
        $result .= "\n";
        $result .= "\n";
        $result .= "\n";
        $result .= "\n";
        $result .= "}";

		$archivo = fopen($ruta.$file.'.php', "w");

        if ($archivo) {

            file_put_contents($ruta.$file.'.php', "<?php \n".$result);
            fclose($archivo);

			chmod($ruta.$file.'.php', 0777);


        }else{

           $this->cancelProcess("No se puedo crear el controlador");
        }



        return $file;


	}

	private function cancelProcess($mensaje){


		$this->pedeo->queryTable("DROP SEQUENCE IF EXISTS ".$this->NombreSecuencia." CASCADE ", array());
		$this->pedeo->queryTable("DROP TABLE IF EXISTS ".$this->NombreTablaFinal, array());
		$this->pedeo->deleteRow("DELETE FROM menu WHERE men_id = :men_id ", array(':men_id' => $this->idMenu));

		$ruta = getcwd().'/application/controllers/';

      	if( file_exists( $ruta.$this->NombreControlador.'.php' ) ){
			unlink($ruta.$this->NombreControlador.'.php');
		}

		$archivo = fopen($this->Json, "w+b");

        if ( $archivo ) {
            file_put_contents($this->Json, $this->ConfigOri);
            fclose($archivo);
        }

		$this->response(array("error" => true, "data" =>[],"mensaje" => $mensaje));

		exit;

	}




}
