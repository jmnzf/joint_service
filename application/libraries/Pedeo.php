<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
	Envoltorio para PDO (Clase de acceso a base de datos de PHP)
	CodeIgniter library

	Los parámetros a los métodos se pueden pasar de 3 formas distintas:
	- Parámetros variables. Adecuado para parámetros SQL de posición "?"
	- Un array indexado. Ídem.
	- Un array asociativo. Adecuado para parámetros SQL por nombre ":"

*/
class Pedeo {

	private $pdo;

	public function __construct($params) {
		$this->pdo = $params[0];
	}

	//-------------------------------------------
	// CONSULTA

	/*
		Retorna un array indexado de arrays asociativos
		[
			[field1=>value1, field2=>value2, ...],
			[field1=>value1, field2=>value2, ...],
			...
		]
		Ejemplo1:
			$sql = "SELECT * FROM clientes WHERE pais=? AND empresa LIKE ?";
			$result = $pdo->queryTable($sql, 'grecia', 'A%')
		Ejemplo2:
			$sql = "SELECT * FROM productos WHERE precio BETWEEN :minimo AND :maximo;
			$result = $pdo->queryTable($sql, ['minimo'=>20, 'maximo'=>50]);
	*/
	public function queryTable($sql, ...$params) {
		$params = self::scan_params($params);
		$result = $this->pdo->prepare($sql);
		$result->execute($params);
		$table = $result->fetchAll(PDO::FETCH_ASSOC);
		return $table;
	}

	/*
		Retorna un array asociativo
		La consulta SQL debe tener exactamente 2 campos:
		- El primer campo es la clave
		- El segundo campo es el valor
		[
			value1=> value2,
			value1=> value2,
			...
		]
	*/
	public function queryAssoc($sql, ...$params) {
		$params = self::scan_params($params);
		$result = $this->pdo->prepare($sql);
		$result->execute($params);
		$assoc = $result->fetchAll(PDO::FETCH_KEY_PAIR);
		return $assoc;
	}

	/*
		Array asociativo del primer campo de arrays indexados de arrays asociativos
		[
			value1=> [
				[field2=>value2, field3=>value3, ...],
				..
			],
			value1=> [
				...
			],
			...
		]
	*/
	public function queryGroup($sql, ...$params) {
		$params = self::scan_params($params);
		$result = $this->pdo->prepare($sql);
		$result->execute($params);
		$groups = $result->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
		return $groups;
	}

	/*
		Retorna la primera fila en un array asociativo
		- El SQL debería retorna una sóla fila.
		[
			field1=>value1, field2=>value2, ...
		]
	*/
	public function queryRow($sql, ...$params) {
		$params = self::scan_params($params);
		$result = $this->pdo->prepare($sql);
		$result->execute($params);
		$row = $result->fetch(PDO::FETCH_ASSOC);
		return $row;
	}

	/*
		Retorna un valor: la primera columna de la primera fila
		- El SQL debería retorna un campo y una fila.
	*/
	public function queryValue($sql, ...$params) {
		$params = self::scan_params($params);
		$result = $this->pdo->prepare($sql);
		$result->execute($params);
		$row = $result->fetch(PDO::FETCH_NUM); // Array indexado
		$value = $row[0];
		return $value;
	}


	//-------------------------------------------
	// CRUD

	/*
		Insertar un registro en una tabla
	*/
	public function insertRow($sql, ...$params) {
		$params = self::scan_params($params);
		$result = $this->pdo->prepare($sql);
		if ($result->execute($params)){
			return $this->pdo->lastInsertId();
		}else{
			return $result->errorInfo();
		}
	}

	/*
		Actualizar un registro en una tabla
	*/

	public function updateRow($sql, ...$params) {
		$params = self::scan_params($params);
		$result = $this->pdo->prepare($sql);
		if ($result->execute($params)){
				return $result->rowCount();
		}else{

			 return $result->errorInfo();
		}


	}

	/*
		Borrar un registro en una tabla
	*/
	public function deleteRow($sql, $id) {
		$result = $this->pdo->prepare($sql);
		if ($result->execute(array($id)))
		return $result->rowCount();
		else return 0;
	}



	public function trans_begin(){
		return $this->pdo->beginTransaction();
	}

	public function trans_rollback(){
		return $this->pdo->rollBack();
	}

	public function trans_commit(){
		return $this->pdo->commit();
	}



	//----------------------------------------------
	// STATIC

	/*
		Si el array params tiene un sólo elemento y este es un array lo retorna.
		Esto posibilita el paso de parámetros a los métodos:
		- por array indexado. Para parámetros SQL posicionales "?"
		- por array asociativo. Para parámetros SQL por nombre ":"
	*/
	private static function scan_params($params) {
		if (count($params) == 1 && is_array($params[0])) return $params[0];
		else return $params;
	}

}
