<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Salary_timekeeping extends REST_Controller {

	function __construct() {
		parent::__construct();
	}

	
	public function index_put($periodicid){
		try {
			// get data
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}

			$periodicid = (int) $periodicid;
			if($periodicid <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);
			$data = $this->rest_api->api_input('put');


			$data = $this->rest_api->api_input('put');

			// kiếm tra nếu mà đã có trong bảng rồi thì cập nhật, ko thì thêm mới
			if(isset($data) && check_array($data) ){
				foreach ($data as $key => $val) {
					// đếm số bản ghi trong database
					$count = $this->autoload_model->_get_where(array(
						'table' => 'salary_timekeeping',
						'query' => 'time = "'.$val['date'].' 00:00:00" AND userid = '.$val['userid']
					));
					if($count == 0){
						// tiến hành insert
						$this->autoload_model->_create(array(
							'table' => 'salary_timekeeping',
							'data' => array(
								'time' => $val['date'].' 00:00:00',
								'status' => $val['status'],
								'userid' => $val['userid'],
								'periodicid' => $periodicid,
								'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
								'userid_created' => $this->auth['id'],
							)
						));
					}else{
						$this->autoload_model->_update(array(
							'table' => 'salary_timekeeping',
							'data' => array(
								'status' => $val['status'],
								'updated' => gmdate('Y-m-d H:i:s', time() + 7*3600),
								'userid_updated' => $this->auth['id'],
							),
							'query' => 'userid = '.$val['userid'].' AND time = "'.$val['date'].' 00:00:00"'
						));
					}
				}
			}
			return $this->response(json_encode(array('code' => '201', 'result' => true, 'message' => 'Thêm dữ liệu thành công')), 201);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}

	public function search_get(){
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
				'select' => 'date_start, date_end',
				'query' => 'trash = 0 AND id = '.$periodicid,
			));
			$date_start = gettime($periodic['date_start'], 'Y-m-d');
			$date_end = gettime($periodic['date_end'], 'Y-m-d');
			$period = new DatePeriod(
			     new DateTime($date_start),
			     new DateInterval('P1D'),
			     new DateTime($date_end)
			);
			foreach ($period as $key => $value) {
			    $temp[$key]['date'] = $value->format('Y-m-d')  ;     
			    $temp[$key]['dayMonth'] = $value->format('d')  ;     
			}
			$list['time'] = $temp;

			// lấy ra danh sách thợ
			$user = $this->autoload_model->_get_where(array(
				'table' => 'user as tb1',
				'select' => 'tb1.id, tb1.fullname',
				'join' => array(
					array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.id AND tb2.trash = 0', 'left'),
					array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				),
				'group_by' => 'tb1.id',
				'query' => 'tb1.trash = 0 AND tb3.slug="tho" OR tb3.slug="tho-ngoai"',
			), true);

			// lấy ra danh sách đã chấm công trong kì
			$timekeeping = $this->autoload_model->_get_where(array(
				'table' => 'salary_timekeeping',
				'select' => 'time, userid, status',
				'query' => 'periodicid = '.$periodicid,
			),true);


			if(isset($user) && check_array($user) && isset($timekeeping) && check_array($timekeeping)){
				foreach ($user as $keyUser => $valUser) {
					foreach ($timekeeping as $keyTime => $valTime) {
						$valTime['time'] = gettime($valTime['time'], 'Y-m-d');
						if($valUser['id'] == $valTime['userid']){
							$user[$keyUser]['timekeeping'][] = $valTime;
						}
					}
				}
			}
			$list['user'] = $user;

			// processing
			return $this->response(json_encode(array('code' => '200', 'result' => true , 'message' => 'Lấy dũ liệu thành công', 'data' => array('list' => $list))), 200);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}
}
