<?php

//CREACION DE CRUD DINAMICOS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
use Restserver\libraries\REST_Controller;

class DinamicService extends REST_Controller {

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

  //CREAR NUEVO CRUD
	public function createDinamicService_post(){

        $Data = $this->post();


		

        if( !isset( $Data['data'] ) ){

            $this->response(array("error" => true, "data" =>'',"mensaje" => "Falta el parametro data"));

            exit;
        }

        // $Data = json_decode($Data['data'], true);
        $Data = json_decode($Data['data'], true);
	
        $Controlador = 'DynamicPage';// CONTROLADOR PREDETERMINADO

        // $this->pedeo->trans_begin();

        // $NombreSecuencia   = $this->createSequence( $Data['form_table'], $Data['primary_key'] );
        // $NombreTablaFinal  = $this->createTable($Data['form'], $Data['form_table'], $Data['primary_key'], $NombreSecuencia, $Data['form_desctable']);
        // $NombreControlador = $this->createHeader($Data['form_name'], $Data['form'],  $NombreTablaFinal, $Data['primary_key']);

        //INICIA TRANSACCION EN LA BD


        // $idMenu =  $this->createMenu($Data['form_name'], $Controlador);


		$this->createConfigFile(100, 'Nomina',$Data['form'],$Data['primary_key']);

		print_r($idMenu);





        $this->response(array("error" => false, "data" =>[],"mensaje" => "Proceso finalizado"));
	}



	private function createConfigFile($idMenu, $CtlrService, $form, $llave){

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


		$contenidoDataTable->columns = [];
		$contenidoDataTable->buttons = [];
		$contenidoButtons->text = "Editar";
		$contenidoButtons->action = "fun_index.edit_docById";
		array_push($contenidoDataTable->buttons, $contenidoButtons);

		foreach ($form as $key => $value) {

			$contenidoColumns = new \stdClass();

			if ( $value['colum_status'] == 1 ) {
				$contenidoColumns->data  = $value['field_name'];
				$contenidoColumns->name  = $value['field_name'];
				$contenidoColumns->title = $value['colum_title'];
			}


			array_push($contenidoDataTable->columns, $contenidoColumns);
		}



		$contenidoDataTable->order=[[0, "desc"]];
		$contenidoDataTable->statusField = $form[0]['colum_status'];

		$json->datatable = $contenidoDataTable;

		$contenidoForm->field = [];
		$contenidoForm->rules = [];

		foreach ($form as $key => $value) {

			$contenidoField = new \stdClass();
			$contenidoRules = new \stdClass();

			switch($value['field_type']){

				case 'text':
					$contenidoField->type  = $value['field_type'];
					$contenidoField->title = $value['colum_title'];
					$contenidoField->id    = $value['field_name'];
					$contenidoField->col   = $value['column_size'];

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
					$contenidoField->type  = $value['field_type'];
					$contenidoField->title = $value['colum_title'];
					$contenidoField->id    = $value['field_name'];
					$contenidoField->col   = $value['column_size'];
					$contenidoField->data  = [];
					$contenidoField->api   = $value['api_service'];

					array_push($contenidoForm->field, $contenidoField);
					break;

			}

			if ( $value['field_required'] == 1 ||  $value['field_required'] == '1' ){
				$requerido = new \stdClass();
				$requerido->required = 'true';
				$campo = $value['field_name'];
				$contenidoRules->$campo = $requerido;

				array_push($contenidoForm->rules, $contenidoRules);
			}

		}

		$contenidoForm->fieldId = $llave;
		$contenidoForm->mdSize = null;

		$json->form = $contenidoForm;





		print_r(json_encode($json));exit;

	}

	private function createMenu($NombreMenu, $Controlador){

		$sqlInsert = "INSERT INTO menu( men_nombre, men_icon, men_controller, men_action, men_sub_menu, men_id_menu, men_id_estado )
									VALUES (:men_nombre, :men_icon, :men_controller, :men_action, :men_sub_menu, :men_id_menu, :men_id_estado)";

		$resInsert = $this->pedeo->insertRow($sqlInsert, array(
			':men_nombre' => $NombreMenu,
			':men_icon' => "",
			':men_controller' => $Controlador,
			':men_action' => "",
			':men_sub_menu' => 0,
			':men_id_menu' => 161,
			':men_id_estado' => 1
		));

		if ( is_numeric($resInsert) && $resInsert > 0 ){

		}else{

			$this->pedeo->trans_rollback();

			$respuesta = array(
				"error"   => true,
				"data"    => $resInsert,
				"mensaje" => "No se pudo crear el menu"
			);

			$this->response($respuesta);

			exit;
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

					$this->pedeo->trans_rollback();

					$respuesta = array(
						"error"   => true,
						"data"    => $resSecuencia,
						"mensaje" => "No se pudo crear la secuencia"
					);

					$this->response($respuesta);

					exit;
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
		$ac = 0;

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

					$this->pedeo->trans_rollback();

					$respuesta = array(
						"error"   => true,
						"data"    => $resTabla,
						"mensaje" => "No se pudo crear la tabla"
					);

					$this->response($respuesta);

					exit;
				}



			}else{
				$acc++;
				$table = $table.$ac;
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
			$campos .= "\n";
			switch ($element['date_type']) {
				case 'text':
					$campos.= $element["field_name"].' '.'character varying('.$element["length_max"].') COLLATE pg_catalog.'."default ,";
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
		$campos.= "\n";
		$campos.= $PrimaryKey." integer PRIMARY KEY NOT NULL DEFAULT nextval('".$Secuencia."'::regclass)";

		return $campos;

	}

	private function createInsert($data, $table){


		$insert = "INSERT INTO ".$table;
		$campos = "";
		$values = "";
		$arrayInsert = "";
		$arrayValidateInsert = "";

		foreach ( $data as $key => $element ) {
			$campos.= trim($element['field_name']).', ';
			$values.= trim(':'.$element['field_name']).', ';
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
			$campos.= trim($element['field_name']).' = '. trim(':'.$element['field_name']) .' , ';

			$soloCampos.= trim($element['field_name']).', ';
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

	private function createGet($table){
		$result  = "";
		$result .= "public function index_get(){";
		$result .= "\n";
		$result .= "\$resSelect = \$this->pedeo->queryTable('SELECT * FROM ".$table."',array());";
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
        $insert = $this->createInsert($form, $table);
        $get    = $this->createGet($table);

        $controller = ucfirst($controller);
		$controller = str_replace(" ", "",trim($controller));
        $ac=0;
        $hacer = true;
        $file = $controller;
        $ruta = getcwd().'/application/controllers/';

        // while($hacer){

        //     if( file_exists($ruta.$file.'.php') ){
        //         $ac++;
        //         $file = $controller.$ac;
        //     }else{
        //         $hacer = false;
        //     }
        // }

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

        $archivo = fopen($ruta.$file.'.php', "w+b");

        if ( $archivo ) {


            file_put_contents($ruta.$file.'.php', "<?php \n".$result);
            fclose($archivo);

        }else{

            $respuesta = array(
				"error"   => true,
				"data"    => [],
				"mensaje" => "No se pudo crear el archivo controlador"
			);

			$this->response($respuesta);

			exit;

        }



        return $file;


	}



}
