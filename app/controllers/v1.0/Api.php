<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'third_party/REST_Controller.php';
class Api extends REST_Controller {
	function __construct() {
		parent::__construct();
	}
	public function index_get(){
		$data = $this->rest_api->api_input('get');
		$return = $this->autoload_model->_get_where($data, true);
		return $this->response(json_encode($return), REST_Controller::HTTP_OK);
	}

	public function index_post(){
		$data = $this->rest_api->api_input('post');
		$flag = $this->autoload_model->_create(array(
			'table' => $data['table'],
			'data' => $data['data'],
		));
		if(isset($flag) && $flag > 0){
			return $this->response($flag, REST_Controller::HTTP_OK);
		}else{
			return $this->response(false, REST_Controller::HTTP_OK);
		}
	}
	public function batch_post(){
		$data = $this->rest_api->api_input('post');
		$flag = $this->autoload_model->_create_batch(array(
			'table' => $data['table'],
			'data' => $data['data'],
		));
		if(isset($flag) && $flag > 0){
			return $this->response($flag, REST_Controller::HTTP_OK);
		}else{
			return $this->response(false, REST_Controller::HTTP_OK);
		}
	}




	public function index_put(){
		$data = $this->rest_api->api_input('put');
		$flag = $this->autoload_model->_update(array(
			'table' => $data['table'],
			'where' => $data['where'],
			'data' => $data['data'],
		));
		if(isset($flag) && $flag > 0){
			return $this->response($flag, REST_Controller::HTTP_OK);
		}else{
			return $this->response(false, REST_Controller::HTTP_OK);
		}
	}

	public function index_delete(){
		$data = $this->rest_api->api_input('delete');
		$flag = $this->autoload_model->_delete(array(
			'table' => $data['table'],
			'where' => $data['where'],
		));
		if(isset($flag) && $flag > 0){
			return $this->response($flag, REST_Controller::HTTP_OK);
		}else{
			return $this->response(false, REST_Controller::HTTP_OK);
		}
	}

	public function count_get(){
		$data = $this->rest_api->api_input('get');
		$count = $this->autoload_model->_get_where(array_merge($data , array('count' => TRUE)));
		return $this->response($count, REST_Controller::HTTP_OK);
	}


	public function view_get(){
		$data = $this->rest_api->api_input('get');

		$return['count'] = $this->autoload_model->_get_where(array_merge($data , array('count' => TRUE)));
		if($return['count'] == 0){
			$return['from'] = 0;
			$return['to'] = 0;
			$return['list'] = '';
			return $this->response($return, REST_Controller::HTTP_OK);
		};
		$page = $data['page'];
		$perpage = $data['perpage'];

		$totalPage = ceil($return['count']/$perpage);
		$page = ($page <= 0)?1:$page;
		$page = ($page > $totalPage)?$totalPage:$page;
		$page = $page - 1;
		$return['from'] = ($page * $perpage) + 1;
		$return['to'] = ($perpage*($page+1) > $return['count']) ? $return['count']  : $perpage*($page+1);

		$data['limit'] = $perpage;
		$data['start'] = $page * $perpage;
		$return['list'] = $this->autoload_model->_get_where($data, true);
		return $this->response(json_encode($return), REST_Controller::HTTP_OK);
	}
}
