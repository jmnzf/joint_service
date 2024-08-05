<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class DateFormat {


	public function Date($params) {

    $date = date('d-m-Y',strtotime($params));
		return $date;
	}

}
