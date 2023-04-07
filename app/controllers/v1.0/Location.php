<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Location extends REST_Controller {

	function __construct() {
		parent::__construct();
	}

	/**
	 * index_get: Lấy tất cả dữ liệu hoặc lấy 1 bản ghi với id = ...
	 * 
	 * @param int $id || null
	 * @return json
	 */
	public function index_get($id = 0){

		try {
			// get data
			$data = $this->rest_api->api_input('get');
			$data = convert_obj_to_array($data);

			$locationList = $this->get_location(array(
				'select' => $data['select'].', name',
				'table' => $data['table'],
				'where' => array($data['parentField'] => $data['parentid']),
				'field' => $data['select'],
				'text' => $data['text'],
			));
			$temp = '';
			if(isset($locationList) && is_array($locationList) && count($locationList)){
				foreach($locationList as $key => $val){
					$temp = $temp.'<option value="'.$key.'">'.$val.'</option>';
				}
			}
			return $this->response(json_encode(array('code' => '200', 'result' => true, 'message' => 'Lấy dữ liệu thành công', 'data' => array('html' => $temp))), 200);

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}

	}

	public function get_location($param = ''){
		$CI =& get_instance();
		$cityList = $CI->autoload_model->_get_where(array(
			'select' => $param['select'],
			'table' => $param['table'],
			'where' => $param['where'] ?? '',
			'order_by' => 'name asc'
		), TRUE);

		$temp[0] = $param['text'];
		if(isset($cityList) && is_array($cityList) && count($cityList)){
			foreach($cityList as $key => $val){
				$temp[$val[$param['field']]] = $val['name'];
			}
		}
		return $temp;
	}



}
