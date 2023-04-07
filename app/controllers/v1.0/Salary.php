<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Salary extends REST_Controller {

	function __construct() {
		parent::__construct();
		$this->load->library('salary_get_data');
	}

	public function view_get(){
		try {
			// get data
			$periodicid = $this->input->get('periodicid');
			if($periodicid == "undefined"){
				$periodicid = $this->common->last_id('periodic');
			}

			if($periodicid <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);

			// lấy thời gian trong kì
			$periodic = $this->autoload_model->_get_where(array(
				'table' => 'periodic',
				'select' => 'id, date_start, date_end',
				'query' => 'trash = 0 AND id = '.$periodicid,
			));
			// lấy lương của thợ
			$data['office'] = $this->salary_get_data->office($periodic);
			return pre($data['office']);
			$data['worker'] = $this->salary_get_data->worker($periodic);
			$data['design'] = $this->salary_get_data->design($periodic);
			$data['worker_outside'] = $this->worker_outside($periodic);

			// processing
			return $this->response(json_encode(array('code' => '200', 'result' => true , 'message' => 'Lấy dũ liệ thành công', 'data' => array('list' => $data))), 200);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}



	// cập nhật lại lương
	public function index_put(){
		try {
			// get data
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}

			$periodicid = $this->input->get('periodicid');
			if($periodicid == "undefined"){
				$periodicid = $this->common->last_id('periodic');
			}


			$data = $this->rest_api->api_input('put');
			$user = $data['user'] ?? [];
			if(isset($user) && check_array($user) ){
				foreach ($user as $key => $val) {
					$val['userid'] = $val['id'];
					$val['salary'] = (int)str_replace('.','',$val['salary'] ?? 0);
					$val['ung_luong'] = (int)str_replace('.','',$val['ung_luong'] ?? 0);
					$val['bonus'] = (int)str_replace('.','',$val['bonus'] ?? 0);
					$val['fine'] = (int)str_replace('.','',$val['fine'] ?? 0);
					$val['periodicid'] = $periodicid;
					unset($val['id']);
					// kiểm tra xem trong databse có chưa
					$count = $this->autoload_model->_get_where(array('table' => 'salary', 'query' => 'periodicid='.$periodicid.' AND userid ='.$val['userid'], 'count' => true));
					if($count == 0){
						// tiến hành inser
						$this->autoload_model->_create(array('table' => 'salary', 'data' => $val));
					}else{
						$this->autoload_model->_update(array('table' => 'salary', 'data' => $val, 'query' => 'periodicid='.$periodicid.' AND userid ='.$val['userid'] ));
					}
				}
			}
			return $this->response(json_encode(array('code' => '201', 'result' => true, 'message' => 'Cập nhật dữ liệu thành công')), 201);
			
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}

}	
