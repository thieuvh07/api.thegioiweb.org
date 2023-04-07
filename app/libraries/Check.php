<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check extends REST_Controller {

	function __construct() {
		parent::__construct();
	}

	public function pre($data){
		return $this->response($data, 200);
	}
	
}
