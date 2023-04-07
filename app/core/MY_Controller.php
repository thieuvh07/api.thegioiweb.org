<?php
(defined('BASEPATH')) or exit('No direct script access allowed');

class MY_Controller extends MX_Controller {

	public $auth;
	public $currentTime;
	public $search;
	public $replace;

	public function __construct(){
		parent::__construct();
		$this->config->load('rest');
		$this->load->library(array('Rest_api', 'Crud', 'Model_rela','common'));
		$this->load->model(array('autoload_model'));

		$authid = $this->input->get('authid');
		if(isset($authid)){
			$this->auth = json_decode(base64_decode($authid), true);
		}


        $this->currentTime =  gmdate('Y-m-d H:i:s', time() + 7*3600);
		$this->search = array('/\n/', // replace end of line by a space
			'/\>[^\S ]+/s', // strip whitespaces after tags, except space
			'/[^\S ]+\</s', // strip whitespaces before tags, except space
			'/(\s)+/s' // shorten multiple whitespace sequences
		);
		$this->replace = array(
			' ',
			'>',
			'<',
			'\\1'
		);
	}

}
