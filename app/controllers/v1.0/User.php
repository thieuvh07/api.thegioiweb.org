<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class User extends REST_Controller {

	protected $module;
	protected $fieldKeywordArray;
	function __construct() {
		parent::__construct();
		$this->module = 'user';
		$this->fieldKeywordArray =  array('fullname', 'email', 'phone', 'address', 'account');
	}

	protected function convert_data($data){
		$data['table'] = $this->module;
		$data['fields'] = $this->process_select($data['fields'] ?? '');
		$data['select'] = !empty($data['fields']) ? $data['fields'] : $this->module.'.id,customerid,customer_type,price,service_cataloguekey,serviceid,date_expiration,date_sign,date_innitiated,'.$this->module.'.created,
			(SELECT fullname FROM user WHERE user.id = user.userid_created AND user.trash = 0) as user_created,
			(SELECT price FROM service_hosting	 WHERE service_hosting.id = user.serviceid AND service_hosting.trash = 0) as price_hosting';

		$data['start'] = $data['offset'] ?? 0;
		$data['limit'] = $data['limit'] ?? 10;

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
		if(isset($queryList['catalogue'])){
			$join = array(
				array('catalogue_relationship as tb2' , 'tb2.moduleid = user.id AND tb2.trash = 0', 'left'),
				array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
			);
			$query = $query.' AND tb3.id = '.$queryList['catalogue'];
		}



		$data['query'] = substr($query, 4, strlen($query));
		$data['join'] = $join;

		$order_by = $this->module.'.updated DESC';
		$data['order_by'] = (!empty($queryData['order_by'])) ? $queryData['order_by'].', '.$order_by : $order_by;

		return $data;
	}
	private function process_select($field = ''){
		if(strrpos($field, 'user_cata')){
			$temp = '(SELECT title FROM user_catalogue WHERE incident.usercataid_charge = user_catalogue.id AND user_catalogue.trash = 0) as user_cata';
			$field = str_replace ( 'user_cata', $temp, $field );
		}
		// if(strrpos($field, 'catalogue')){
		// 	$temp = '';
		// 	$field = str_replace ( 'catalogue', $temp, $field );
		// }
		return $field;
	}
	
	protected function process_response($response, $config){

		$list = $response['data']['list'];
		if(isset($list) && check_array($list)){
			foreach ($list as $key => $val) {
				if(isset($val['avatar'])){
					$list[$key]['avatar'] = getthumb($val['avatar'], false);
				}
				if(isset($val['created'])){
					$list[$key]['created'] = gettime($val['created'], 'micro');
				}
				if(isset($val['catalogue'])){
					$user_catalogue = $this->autoload_model->_get_where(array(
						'table' => 'user_catalogue',
						'select' => 'title',
						'where_in' => json_decode($val['catalogue']),
						'where_in_field' => 'id',
						'query' => 'trash = 0',
					), true);
					$str = '';
					if(isset($user_catalogue) && check_array($user_catalogue)){
						foreach ($user_catalogue as $keyCata => $valCata) {
							$str = $str.(($keyCata == 0) ? '' : ', ').$valCata['title'];
						}
					}
					$list[$key]['catalogue'] = $str;
				}

			}
		}
		$response['data']['list'] = $list;
		return $response;
	}
	
	protected function set_data($data, $method){
		$salt = random();
		if(isset($_FILES['avatar']['name'])){
			$avatar = "/upload/image/".gettime($this->currentTime, "d_m_Y").'/'.str_replace(" ","_",$_FILES['avatar']['name']);
		}

		$field = array('positionid', 'account', 'permission', 'fullname', 'email', 'birthday', 'avatar', 'salt', 'password', 'gender', 'phone', 'cityid', 'districtid', 'wardid', 'description');
		foreach ($data as $key => $val) {
			if(in_array($key, $field )){
				switch ($key) {
				    case 'fullname': $val = htmlspecialchars_decode(html_entity_decode($val));break;
				    case 'birthday': $val = convert_time($val);break;
				    case 'avatar': $val = $avatar;break;
				}
				$temp[$key] = $val;
			}
		}

		switch ($method) {
		    case 'insert':
		        $temp['catalogue'] = json_encode(($data['catalogue'] ?? []));
		        $temp['permission'] = json_encode($data['permission']);
		        $temp['password'] = password_encode($data['password'], $salt);
		        $temp['salt'] = $salt;
		        $temp['created'] = gmdate('Y-m-d H:i:s', time() + 7*3600);
				$temp['userid_created'] = $this->auth['id'];
				$temp['trash'] = 0;
				break;
		    default:
		        $temp['catalogue'] = json_encode($data['catalogue'] ?? []);
		        $temp['permission'] = json_encode($data['permission']);
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

		$this->form_validation->set_rules('account','Tài khoản','trim|required');
		$this->form_validation->set_rules('fullname','Họ tên','trim|required');
		$this->form_validation->set_rules('email','Email','trim|required|callback__CheckRegister['.base64_encode(json_encode($data)).']');
		$this->form_validation->set_rules('catalogue[]','Nhóm thành viên','trim|required');
		$this->form_validation->set_rules('permission[]','Quyền của thành viên','trim|required');

		if(!$this->form_validation->run($this)){
			return $this->response(json_encode(array('code' => 202, 'result' => false, 'message' => validation_errors(),)), 202);
		}else{
			return true;
		}
	}
	
	public function _CheckAvatar(){
		// get data
		$time = gettime($this->currentTime, "d_m_Y");
		if (!empty($_FILES['avatar']['name'])) {
			$path  =  "upload/image/".$time;
			if (!file_exists($path)) {
	            mkdir($path, 0700, true);
	        }

			$config = array(
				'upload_path' => $path,
				'allowed_types' => "jpg|png|jpeg",
				'overwrite' => TRUE,
				'max_size' => "2048000",
				'max_height' => "768",
				'max_width' => "1024",
				'file_name' => $_FILES['avatar']['name'],
			);
			$this->load->library('upload', $config);
			$this->upload->initialize($config);
			if($this->upload->do_upload('avatar')){
				$data = array('upload_data' => $this->upload->data());
				return true;
			}else{
				$error = array('error' => $this->upload->display_errors());
				$this->form_validation->set_message('_CheckAvatar', $error['error']);
				return false;
			}
		}
		$this->form_validation->set_message('_CheckAvatar', 'Vui lòng chọn ảnh đại diện');
		return false;

	}


	public function _CheckRegister($email, $data){
		// get data
		$data = json_decode(base64_decode($data), true);
		$account = $data['account'];
		$account_original = $data['account_original'] ?? '';

		// processing
		// check account
		if($account != $account_original){
			if(!preg_match('/^[A-Za-z][A-Za-z0-9]{5,31}$/', $account) ){
				$this->form_validation->set_message('_CheckRegister','Tài khoản không đúng định dạng. Tài khoản từ 6-32 ký tự, bắt đầu bằng chữ, và không chứa ký tự đặc biệt');
				return false;
			}
			$count = $this->autoload_model->_get_where(array( 'table' => $this->module, 'query' => 'account = "'.$account.'"', 'count' => TRUE,));
			if($count >= 1){
				$this->form_validation->set_message('_CheckRegister','Account đã tồn tại');
				return false;
			}
		}
		// email account
		$email_original = $data['email_original'] ?? '';
		if($email != $email_original){
			$count = $this->autoload_model->_get_where(array( 'table' => $this->module, 'query' => 'email = "'.$email.'"', 'count' => TRUE,));
			if($count >= 1){
				$this->form_validation->set_message('_CheckRegister','Email đã tồn tại');
				return false;
			}
		}
		return true;
	}

	protected function config_panigation($data){
		return array(
			'start' => $data['start'], 
			'limit' => $data['limit'], 
			'base_url' => 'user/backend/user/view'
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
			if(isset($queryList['catalogue'])){
				$join = array(
					array('catalogue_relationship as tb2' , 'tb2.moduleid = user.id AND tb2.trash = 0', 'left'),
					array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				);
				$query = $query.' AND tb3.id = '.$queryList['catalogue'];
			}
			if(isset($queryList['cataloguelike'])){
				$fieldKeywordArray = explode('.', $queryList['cataloguelike']);
				if(isset($fieldKeywordArray) && check_array($fieldKeywordArray) ){
					$temp = '';
					foreach ($fieldKeywordArray as $keyKey => $valKey) {
						$temp = $temp.' OR catalogue LIKE \'%'.$valKey.'%\'';
					}
					$temp = substr( $temp, 4, strlen($temp));
					$query = $query.' AND ( '.$temp.' ) ';
				}
			}

			$data['query'] = substr($query, 4, strlen($query));
			$data['join'] = $join;

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

			if($this->validate($data)){
				$_update = $this->set_data($data, 'update');

				// processing
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

			$data = $this->convert_data($data);
			// count record
			$response = $this->crud->count(array( 'select' => 'user.id' ,'table' => $this->module, 'query' => $data['query'], 'join' => $data['join'] ?? [], 'count' => TRUE,));
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

			// get data
			if(!check_permission($this->auth['id'] ?? '',$this->module.'/backend/'.$this->module.'/delete')){
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
