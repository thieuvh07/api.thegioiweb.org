<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Construction_catalogue extends REST_Controller {

	protected $module;
	protected $fieldKeywordArray;
	function __construct() {
		parent::__construct();
		$this->module = 'construction_catalogue';
		$this->fieldKeywordArray =  array('title');
	}

	protected function convert_data($data){
		$data['table'] = $this->module;
		$data['fields'] = $this->process_select(($data['fields'] ?? ''));
		$data['select'] = !empty($data['fields']) ? $data['fields'] : $this->module.'.id';

		$data['start'] = $data['offset'] ?? 0;
		$data['limit'] = $data['limit'] ?? 20;

		$queryData = render_search_in_query($this->module, $this->input->get(), array('fieldKeywordArray' => $this->fieldKeywordArray), false);
		$queryList = $queryData['queryList'];
		$query = '';
		$join = [];
		$query = $query.$queryData['query'];

		if(isset($queryList['trash'])){
			$query = $query.' AND '.$this->module.'.trash = '.$queryList['trash'];
		}else{
			$query = $query.' AND '.$this->module.'.trash = 0';
		}
		$data['query'] = substr($query, 4, strlen($query));
		$order_by = $this->module.'.created DESC';
		$data['order_by'] = (!empty($queryData['order_by'])) ? $queryData['order_by'].', '.$order_by : $order_by;

		// $data['join'] = array(array('customer', 'customer.id = construction_catalogue.customerid', 'left'));
		return $data;
	}
	private function process_select($field = ''){
		if(strrpos($field, 'user_created')){
			$temp = '(SELECT fullname FROM user WHERE user.id = '.$this->module.'.userid_created AND user.trash = 0) as user_created';
			$field = str_replace ( 'user_created', $temp, $field );
		}
		if(strrpos($field, 'construction_count')){
			$temp = '(SELECT COUNT(id) FROM construction WHERE construction.catalogueid = '.$this->module.'.id AND construction.trash = 0) as construction_count';
			$field = str_replace ( 'construction_count', $temp, $field );
		}
		return $field;
	}

	protected function process_response($response, $config){

		$list = $response['data']['list'];
		if(isset($list) && check_array($list)){
			foreach ($list as $key => $val) {
				if(isset($val['created'])){
					$list[$key]['created'] = gettime($val['created'], 'micro');
				}
			}
		}
		$response['data']['list'] = $list;
		return $response;
	}
	
	protected function set_data($data, $method){
		$field = array('title', 'percent');
		foreach ($data as $key => $val) {
			if(in_array($key, $field )){
				switch ($key) {
				    case 'birthday': $val = convert_time($val);break;
				}
				$temp[$key] = $val;
			}
		}
		switch ($method) {
		    case 'insert':
			    $temp['slug'] = slug($temp['title'] ?? '');
		        $temp['created'] = gmdate('Y-m-d H:i:s', time() + 7*3600);
				$temp['userid_created'] = $this->auth['id'];
				$temp['trash'] = 0;
				break;
		    default:
			    $temp['slug'] = slug($temp['title'] ?? '');
			    $temp['updated'] = gmdate('Y-m-d H:i:s', time() + 7*3600);
				$temp['userid_updated'] = $this->auth['id'];
				$temp['trash'] = 0;
				break;
		}
		return $temp;
	}

	

	protected function validate($data){
		$this->load->library('form_validation');
		$this->form_validation->CI =& $this;
		$this->form_validation->set_data($data);

		$this->form_validation->set_rules('title','Tên nhóm công trình','trim|required');

		if(!$this->form_validation->run($this)){
			return $this->response(json_encode(array('code' => 202, 'result' => false, 'message' => validation_errors(),)), 202);
		}else{
			return true;
		}
	}
	
	function _CheckCondition($title, $data){
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
	protected function config_panigation($data){
		return array(
			'start' => $data['start'], 
			'limit' => $data['limit'], 
			'base_url' => 'construction/backend/catalogue/view'
		);
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
			$data = array_filter($data);

			$queryData = render_search_in_query($this->module, $this->input->get(), array('fieldKeywordArray' => $this->fieldKeywordArray), false);
			$queryList = $queryData['queryList'];
			$query = '';
			$join = [];
			$query = $query.$queryData['query'];

			if(isset($queryList['trash'])){
				$query = $query.' AND '.$this->module.'.trash = '.$queryList['trash'];
			}else{
				$query = $query.' AND '.$this->module.'.trash = 0';
			}
			$data['query'] = substr($query, 4, strlen($query));
			$order_by = $this->module.'.updated DESC';
			$data['order_by'] = (!empty($queryData['order_by'])) ? $queryData['order_by'].', '.$order_by : $order_by;

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
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}
			$data = $this->rest_api->api_input('post');

			if($this->validate($data)){
				$_insert = $this->set_data($data, 'insert');

				// processing
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
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}
			$id = (int) $id;
			if($id <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);
			$data = $this->rest_api->api_input('put');

			// if($this->validate($data)){
				$_update = $this->set_data($data, 'update');

				// processing
				$response = $this->crud->update(array('table' => $this->module, 'data' => $_update, 'where' => array('id' => $id)));
				return $this->response(json_encode($response), $response['code']);
			// }
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

			$data = $this->convert_data($data);
			// return pre($data);

			// count record
			$response = $this->crud->count(array( 'table' => $this->module, 'join' => $data['join'] ?? [],'query' => $data['query'], 'count' => TRUE,));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}else{
				$total_rows = $response['data']['count'];
			}
			if($total_rows <= 0){
				return $this->response(json_encode(array('code' => 200, 'result' => true, 'message' => 'Không có bản ghi nào', 'data' => array('list' => [], 'from' =>  0, 'to' => 0, 'total_rows' => 0, 'pagination' => ''))), REST_Controller::HTTP_OK);
			}else{
				$response = $this->crud->get($data);
				
				$config = panigation($this->config_panigation($data)); 
				$config['total_rows'] = $total_rows;
				$this->load->library('pagination');
				$this->pagination->initialize($config);

				$totalPage = ceil($total_rows/$config['per_page']);
				$page = $config['cur_page'];
				$page = ($page <= 0)?1:$page;
				$page = ($page > $totalPage)?$totalPage:$page;
				$page = $page - 1;
				$response['data']['from'] =  ($page * $config['per_page']) + 1;
				$response['data']['to'] = ($config['per_page']*($page+1) > $total_rows) ? $total_rows  : $config['per_page']*($page+1);
				$response['data']['pagination'] = $this->pagination->create_links($config);
				$response['data']['total_rows'] = $total_rows;
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
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}
			$id = (int) $id;
			if($id <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);
			if(!check_permission($this->auth['id'] ?? '', 'construction/backend/catalogue/delete')){
				return $this->response(json_encode(array('code' => 200, 'result' => false, 'message' => 'Bạn không có quyền thực hiện chức năng này')), 200);
			}
			
			$data = $this->rest_api->api_input('put');

			$_update = array(
				'trash' => 1,
				'updated' => gmdate('Y-m-d H:i:s', time() + 7*3600),
				'userid_updated' => $this->auth['id'],
			);

			// processing
			$response = $this->crud->update(array('table' => $this->module, 'data' => $_update, 'where' => array('id' => $id)));
			return $this->response(json_encode($response), $response['code']);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}
}
