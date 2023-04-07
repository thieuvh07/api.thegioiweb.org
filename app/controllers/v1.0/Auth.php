<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Auth extends REST_Controller {

	private $module;
	function __construct() {
		parent::__construct();
		$this->module = 'user';
	}


	/**
	 * index_post: Thực hiện thêm mới bản ghi
	 * 
	 * @param 
	 * @return json
	 */
	public function check_permission_get(){
		try {
			// get data
			$data = $this->rest_api->api_input('get');

			$user = $this->crud->get(array(
				'select' => 'permission',
				'table' => 'user',
				'query' => 'trash = 0',
				'where' => array('id' => $data['id']),
				'flag' => false
			));
			$user = $user['data']['list'];
			$permission=json_decode($user['permission'], true);
			if($permission != null && in_array($data['permission'], $permission) == true ){
				return $this->response(json_encode(array('code' => '200', 'result' => true, 'message' => 'Bạn có quyền truy cập vào đây')), 200);
			}else{
				return $this->response(json_encode(array('code' => '202', 'result' => false, 'message' => 'Bạn không có quyền truy cập vào đây')), 202);
			}

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}


	}
	public function check_login_post(){

		try {
			// get data
			$data = $this->rest_api->api_input('post');

			$this->load->library('form_validation');
			$this->form_validation->CI =& $this;
			$this->form_validation->set_data($data);

			$this->form_validation->set_rules('account','Tài khoản','trim|required');
			$this->form_validation->set_rules('password','Mật khẩu','trim|required');

			if(!$this->form_validation->run($this)){
				return $this->response(json_encode(array('code' => 202, 'result' => false, 'message' => validation_errors(),)), 202);
			}else{
				// processing
				$post = $this->rest_api->api_input('post');
				$response = $this->check_auth($post['account'], $post['password']);
				return $this->response($response, 200);

			}

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}

	}
	protected function check_auth($account = '' , $password = '' ){
		$CI =& get_instance();
		//Kiểm tra xem cơ sở dữ liệu có tài khoản nào phù hợp không.
		$auth = $CI->autoload_model->_get_where(array(
			'select' => 'id, password, salt',
			'table' => 'user',
			'query' => 'trash = 0',
			'where' => array(
				'account' => $account,
			),
		));
		if(!isset($auth) || is_array($auth) == FALSE || count($auth) == 0){
			return json_encode(array(
				'code' => 200,
				'result' => false,
				'message' => 'Tài khoản hoặc mật khẩu không chính xác',
			));
		}
		//Kiểm tra tiếp là mật khẩu có đúng hay không.
		$passwordCompare = password_encode($password, $auth['salt']);
		// return ($passwordCompare);
		if($passwordCompare != $auth['password']){
			return json_encode(array(
				'code' => 200,
				'result' => false,
				'message' => 'Tài khoản hoặc mật khẩu không chính xác',
			));
		}
		$auth = $CI->autoload_model->_get_where(array(
			'select' => 'id, fullname, avatar',
			'query' => 'trash = 0',
			'table' => 'user',
			'where' => array(
				'account' => $account,
			),
		));
		$user_catalogue = $CI->autoload_model->_get_where(array(
			'select' => 'tb2.title, tb2.slug',
			'table' => 'catalogue_relationship as tb1',
			'join' => array(array('user_catalogue as tb2', 'tb2.id = tb1.catalogueid  AND tb2.trash = 0', 'left')),
			'query' => 'tb1.trash = 0 AND tb1.moduleid = '.$auth['id'].' AND tb1.module = "user"',
			'group_by' => 'tb1.moduleid'
		), true);

		if(isset($user_catalogue) && check_array($user_catalogue)){
			$auth['titleCata'] = get_colum_in_array($user_catalogue, 'title');
			$auth['slugCata'] = get_colum_in_array($user_catalogue, 'slug');
		}

		return json_encode(array(
			'code' => 200,
			'result' => true,
			'message' => 'Đăng nhập thành công',
			'data' => array('auth' => $auth),
		));
	}
}
