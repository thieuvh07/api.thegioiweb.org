<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Vn_province extends REST_Controller {

	private $module;
	private $fieldKeywordArray;
	function __construct() {
		parent::__construct();
		$this->module = 'vn_province';
		$this->fieldKeywordArray =  array('fullname', 'email', 'phone', 'address');
	}

	/**
	 * process_select: xử lí tham số lấy vào
	 * 
	 * @param $select(định dạng string)
	 * @return $select(định dạng string)
	 */
	private function process_select($select = ''){
		if(strrpos($select, 'user_created')){
			$str = '(SELECT fullname FROM user WHERE user.id = '.$this->module.'.userid_created) as user_created';
			$select = str_replace ( 'user_created', $str, $select );
		}
		return $select;
	}

	/**
	 * process_response: xứ lí dữ liệu trả về: như định dạng lại đường dẫn ảnh, định dạng lại thời gian
	 * 
	 * @param $response
	 * @return $response
	 */
	private function process_response($response, $config){
		$list = $response['data']['list'];
		if(isset($list) && check_array($list)){
			foreach ($list as $key => $val) {
				if(isset($val['avatar'])){
					$list[$key]['avatar'] = getthumb($val['avatar'], false);
				}
				if(isset($val['created'])){
					$list[$key]['created'] = gettime($val['created'], 'd-m-Y');
				}
			}

			if(isset($config) && check_array($config)){
				$totalPage = ceil($config['total_rows']/$config['per_page']);
				$page = $config['cur_page'];
				$page = ($page <= 0)?1:$page;
				$page = ($page > $totalPage)?$totalPage:$page;
				$page = $page - 1;
				$response['data']['pagination'] = $this->pagination->create_links();
				$response['data']['to'] = ($config['per_page']*($page+1) > $config['total_rows']) ? $config['total_rows']  : $config['per_page']*($page+1);
						$response['data']['from'] =  ($page * $config['per_page']) + 1;
			}
		}
		$response['data']['list'] = $list;
		return $response;
	}

	/**
	 * _CheckCondition: callback validate định dạng tài khoản
	 * 
	 * @param $email, $data(định dạng base64)
	 * @return boolean
	 */
	public function _CheckCondition($title, $data){
		$data = json_decode(base64_decode($data), true);
		$slug = slug($title);
		$slug_original = $data['title_original'] ?? '';

		if($slug != $slug_original){
			$count = $this->autoload_model->_get_where(array( 'table' => $this->module, 'query' => 'slug = "'.$slug.'"', 'count' => TRUE,));

			if($count >= 1){
				$this->form_validation->set_message('_CheckCondition','Tên phòng ban đã tồn tại');
				return false;
			}
			return true;
		}
		return true;
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
			// return $this->response($data, 200);
			$data = $this->rest_api->api_input('get');
			$data = array_filter($data);
			$id = (int) $id;
			if($id > 0){
				$data['where'] = array('id' => $id);
				$data['flag'] = false;
			}
			if($id < 0){
				return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);
			}
			$data['table'] = $this->module;
			$data['select'] = $data['fields'] ?? 'id';
			$data['limit'] = $data['limit'] ?? 20;

			// processing
			// return $this->response($data, 200);
			$response = $this->crud->get($data);
			return $this->response(json_encode($response), $response['code']);

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}

	}


	/**
	 * index_post: Thực hiện thêm mới bản ghi
	 * 
	 * @param 
	 * @return json
	 */
	public function index_post(){

		try {
			// get data
			$data = $this->rest_api->api_input('post');
			// return $this->response($data, 200);

			// validate
			$this->load->library('form_validation');
			$this->form_validation->CI =& $this;
			$this->form_validation->set_data($data);

			$this->form_validation->set_rules('no','no','trim|required');
			$this->form_validation->set_rules('email','Email','trim|required|callback__CheckCondition['.base64_encode(json_encode($data)).']');

			if(!$this->form_validation->run($this)){
				return $this->response(json_encode(array('code' => 202, 'result' => false, 'message' => validation_errors())), 202);
			}else{
				
				$_insert = array(
					'incident_id' => $data['incident_id'],
					'fullname' => htmlspecialchars_decode(html_entity_decode($data['fullname'])),
					'birthday' => convert_time($data['birthday']),
					'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
					'userid_created' => $this->auth['id'],
					'trash' => 0,
				);
				// processing
				// return $this->response($data, 200);
				$response = $this->crud->insert(array('table' => $this->module, 'data' => $_insert));
				return $this->response(json_encode($response), $response['code']);
			}

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}

	}


	/**
	 * index_post: Thực hiện cập nhật 1 bản ghi
	 * 
	 * @param 
	 * @return json
	 */
	public function index_put($id = 0){

		try {
			// get data
			$id = (int) $id;
			if($id <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);
			$data = $this->rest_api->api_input('put');
			// return $this->response($data, 200);

			// validate
			$this->load->library('form_validation');
			$this->form_validation->CI =& $this;
			$this->form_validation->set_data($data);

			$this->form_validation->set_rules('no','no','trim|required');
			$this->form_validation->set_rules('email','Email','trim|required|callback__CheckCondition['.base64_encode(json_encode($data)).']');

			if(!$this->form_validation->run($this)){
				return $this->response(json_encode(array('code' => 202, 'result' => false, 'message' => validation_errors(),)), 202);
			}else{

				$_update = array(
					'incident_id' => $data['incident_id'],
					'fullname' => htmlspecialchars_decode(html_entity_decode($data['fullname'])),
					'birthday' => convert_time($data['birthday']),
					'updated' => gmdate('Y-m-d H:i:s', time() + 7*3600),
					'userid_updated' => $this->auth['id'],
				);

				// processing
				// return $this->response($_update, 200);
				$response = $this->crud->update(array('table' => $this->module, 'data' => $_update, 'where' => array('id' => $id)));
				return $this->response(json_encode($response), $response['code']);
			}
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}


	/**
	 * search_get: Thực hiện search với điều kiện
	 * 
	 * @param $data = array('fields' => 'id, title','offset' => (($page-1)*$perpage),x'limit' => $perpage,'order_by' => 'id DESC',)
	 * @return json
	 */
	public function search_get(){

		try {
			// get data
			$data = $this->rest_api->api_input('get');
			// return $this->response($data, 200);

			$data['table'] = $this->module;
			$data['select'] = $data['fields'] ?? 'id';
			$data['select'] = $this->process_select($data['select']);
			$data['start'] = $data['offset'] ?? 0;
			$data['limit'] = $data['limit'] ?? 10;

			$queryData = render_search_in_query($this->module, $this->input->get(), array('fieldKeywordArray' => $this->fieldKeywordArray));
			$data['query'] = $queryData['query'] ?? '';
			$data['order_by'] = (!empty($data['order_by'])) ? $data['order_by'].' AND ' : ''.$queryData['order_by'];

			// count record
			$response = $this->crud->count(array( 'table' => $this->module, 'query' => $data['query'], 'count' => TRUE,));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}else{
				$config['total_rows'] = $response['data']['count'];
			}
			if($config['total_rows'] <= 0){
				return $this->response(json_encode(array('code' => 200, 'result' => true, 'message' => 'Không có bản ghi nào', 'data' => array('list' => [], 'from' =>  0, 'to' => 0, 'total_rows' => 0, 'pagination' => ''))), REST_Controller::HTTP_OK);
			}else{

				// pagination
				$this->load->library('pagination');
				$page = (!empty($data['limit'])) ? (1 + $data['start']/$data['limit']) : 1;
				$config['suffix'] = $this->config->item('url_suffix').(!empty($_SERVER['QUERY_STRING'])?('?'.$_SERVER['QUERY_STRING']):'');
				$config['base_url'] = 'vn_province/backend/vn_province/view';
				$config['first_url'] = $config['base_url'].$config['suffix'];
				$config['per_page'] = $data['limit'];
				$config['cur_page'] = $page;
				$config['uri_segment'] = 5;
				$config['use_page_numbers'] = TRUE;
				$config['full_tag_open'] = '<ul class="pagination no-margin">';
				$config['full_tag_close'] = '</ul>';
				$config['first_tag_open'] = '<li>';
				$config['first_tag_close'] = '</li>';
				$config['last_tag_open'] = '<li>';
				$config['last_tag_close'] = '</li>';
				$config['cur_tag_open'] = '<li class="active"><a class="btn-primary">';
				$config['cur_tag_close'] = '</a></li>';
				$config['next_tag_open'] = '<li>';
				$config['next_tag_close'] = '</li>';
				$config['prev_tag_open'] = '<li>';
				$config['prev_tag_close'] = '</li>';
				$config['num_tag_open'] = '<li>';
				$config['num_tag_close'] = '</li>';
				$this->pagination->initialize($config);

				// processing
				// return $this->response($data, 200);
				$response = $this->crud->get($data);
				$response['data']['total_rows'] = $config['total_rows'];
				$response = $this->process_response($response,$config); 
				return $this->response(json_encode($response), $response['code']);
			}

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}



	/**
	 * delete_post: Thực hiện xóa
	 * 
	 * @return json
	 */
	public function delete_post($id = ''){

		try {
			// get data
			$id = (int) $id;
			if($id <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);
			$data = $this->rest_api->api_input('put');
			// return $this->response($data, 200);

			$_update = array(
				'trash' => 1,
				'updated' => gmdate('Y-m-d H:i:s', time() + 7*3600),
				'userid_updated' => $this->auth['id'],
			);

			// processing
			// return $this->response($_update, 200);
			$response = $this->crud->update(array('table' => $this->module, 'data' => $_update, 'where' => array('id' => $id)));
			return $this->response(json_encode($response), $response['code']);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}

	
}
