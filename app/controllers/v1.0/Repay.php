<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Repay extends REST_Controller {

	protected $module;
	protected $fieldKeywordArray;
	function __construct() {
		parent::__construct();
		$this->module = 'repay';
		$this->fieldKeywordArray =  array();
	}

	protected function convert_data($data){
		$data['table'] = $this->module;
		$data['fields'] = $this->process_select(($data['fields'] ?? ''));
		$data['select'] = !empty($data['fields']) ? $data['fields'] : $this->module.'id';

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
		if(isset($queryList['code'])){
			$query = $query.' AND '.$this->module.'.code LIKE \'%'.$queryList['code'].'%\'';
		}
		if(isset($queryList['supplierid'])){
			$query = $query.' AND '.$this->module.'.supplierid = '.$queryList['supplierid'];
		}
		$data['query'] = substr($query, 4, strlen($query));
		$order_by = $this->module.'.date_start DESC';
		$data['order_by'] = (!empty($queryData['order_by'])) ? $queryData['order_by'].', '.$order_by : $order_by;

		// $data['join'] = array(array('customer', 'customer.id = repay.customerid', 'left'));
		return $data;
	}
	private function process_select($field = ''){
		$field = explode( ',' , $field);
		$field = $field ?? [];
		$array = array(
			'supplier' => '(SELECT title FROM supplier WHERE supplier.id = repay.supplierid AND supplier.trash = 0) as supplier',
			'user_created' => '(SELECT fullname FROM user WHERE user.id = repay.userid_created AND user.trash = 0) as user_created',
			'total_money' => '(SELECT SUM(quantity*price) FROM repay_relationship WHERE repay_relationship.repayid = repay.id AND repay_relationship.trash = 0) as total_money',
		);
		foreach ($array as $key => $value) {
			if(in_array($key, $field)){
				$field[array_search($key,$field)] = $value;
			}
		}
		return implode(',', $field);
	}

	protected function process_response($response, $config){

		$list = $response['data']['list'];

		$list_id = get_colum_in_array($list, 'id');
			
		$repay_relationship = $this->autoload_model->_get_where(array(
			'table'=>'repay_relationship',
			'select'=>'measure_repay, price, quantity, repayid,
				(SELECT title FROM product WHERE product.id = repay_relationship.productid  AND product.trash = 0) as title_product, ',
			'query' => 'repay_relationship.trash = 0',
			'where_in' => $list_id,
			'where_in_field' => 'repayid',
			'order_by' => 'created desc',
		),true);


		if(isset($list) && check_array($list)){
			foreach ($list as $key => $val) {
				if(isset($val['date_start'])){
					$list[$key]['date_start'] = gettime($val['date_start'], 'micro');
				}
				if(isset($val['id'])){
					$detail = ''; 
					foreach ($repay_relationship as $sub => $subs) {
						if($subs['repayid'] == $val['id']){
							$detail = $detail.($subs['quantity']).' '.$subs['measure_repay'].' '.$subs['title_product'].',';
						}
					}
					$list[$key]['detail'] = $detail;
				}
			}
		}
		$response['data']['list'] = $list;
		return $response;
	}
	
	protected function set_data($data, $method){
		$field = array('id','title','code','price_input','price_output','catalogueid','supplierid','measure','publish');
		foreach ($data as $key => $val) {
			if(in_array($key, $field )){
				switch ($key) {
				    case 'title': $val = htmlspecialchars_decode(html_entity_decode($val));break;
				    case 'measure': $val = (int)$val;break;
				    case 'catalogueid': $val = json_encode($val);break;
				    case 'supplierid': $val = json_encode($val);break;
				    case 'price_input': $val = (int)str_replace('.','',$val);break;
				    case 'price_output': $val = (int)str_replace('.','',$val);break;
				}
				$temp[$key] = $val;
			}
		}
		switch ($method) {
		    case 'insert':
		        $temp['created'] = gmdate('Y-m-d H:i:s', time() + 7*3600);
				$temp['userid_created'] = $this->auth['id'];
				$temp['trash'] = 0;
				$temp['publish'] = 1;
				break;
		    default:
			    $temp['updated'] = gmdate('Y-m-d H:i:s', time() + 7*3600);
				$temp['userid_updated'] = $this->auth['id'];
				$temp['trash'] = 0;
				$temp['publish'] = 1;
				break;
		}
		return $temp;
	}

	

	protected function validate($data){
		$this->load->library('form_validation');
		$this->form_validation->CI =& $this;
		$this->form_validation->set_data($data);

		$this->form_validation->set_rules('code','Mã đơn hàng','trim|required|callback__CheckCode');
		$this->form_validation->set_rules('supplierid','Nhà cung cấp','trim|required');

		if(!$this->form_validation->run($this)){
			return $this->response(json_encode(array('code' => 202, 'result' => false, 'message' => validation_errors(),)), 202);
		}else{
			return true;
		}
	}
	

	
	function _CheckCode($code, $data){
		$data = json_decode(base64_decode($data), true);
		$code_original = $data['code_original'] ?? '';

		if($code != $code_original){
			$count = $this->autoload_model->_get_where(array( 'table' => $this->module, 'query' => 'code = "'.$code.'"', 'count' => TRUE,));

			if($count >= 1){
				$this->form_validation->set_message('_CheckCode','Mã đơn nhập đã tồn tại');
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
			'base_url' => 'repay/backend/repay/view'
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
			$data = $this->convert_data($data);


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
			if(isset($queryList['code'])){
				$query = $query.' AND '.$this->module.'.code LIKE \'%'.$queryList['code'].'%\'';
			}
			if(isset($queryList['supplierid'])){
				$query = $query.' AND '.$this->module.'.supplierid = '.$queryList['supplierid'];
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

			//lấy dữ liệu sp từ danh sách sp được chon
			$product = $data['product'];
			if(isset($product['id']) && is_array($product['id']) && count($product['id'])){
				foreach ($product['id'] as $key => $val) {
					$list_product[$key]['productid'] = $val; 
					$list_product[$key]['id'] = $val; 
					$list_product[$key]['quantity'] = $product['quantity'][$key]; 
					$list_product[$key]['quantity_repay'] = $product['quantity_repay'][$key]; 
					$list_product[$key]['measure_repay'] = $product['measure_repay'][$key]; 
					$list_product[$key]['quantity'] = $product['quantity'][$key]; 
					$list_product[$key]['price'] = (int)str_replace('.','',$product['price'][$key]); 
				}
			}
			if($this->validate($data)){
				if(isset($list_product) && check_array($list_product) ){
					foreach ($list_product as $keyPrd => $valPrd) {
						// lấy số lượng hiện tại
						if($valPrd['quantity'] != 0){
							$product = $this->autoload_model->_get_where(array(
		                    	'table'=>'product',
		                    	'where'=>array('id'=>$valPrd['id']),
		                    	'query' => 'trash = 0',
		                    	'select'=>'id, quantity_opening_stock'
		                    ));
		                    $product = quantity_closing_stock($product);

		                    $quantity_closing_stock = $product['quantity_closing_stock'];
		                    // lấy số lượng sản phẩm thay đổi khi cập nhật
		                    $quantity_change = $valPrd['quantity'] - ($valPrd['quantity_old'] ?? 0);
		                    if($quantity_change > $quantity_closing_stock){
		                    	return $this->response(json_encode(array('code' => '202', 'result' => false, 'message' => 'Số lượng trong kho không đủ')), 202);
		                    }
						}
					}
				}

				$_insert = array(
					'code' => $data['code'],
					'date_start' => convert_time($data['date_start']),
					'data_json'=> base64_encode(json_encode($list_product ?? '')),
					'supplierid' => $data['supplierid'],
					'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
					'userid_created' => $this->auth['id'],
					'note'=>$data['note'],
				);

				// processing
				$response = $this->crud->insert(array('table' => $this->module, 'data' => $_insert));
				// return pre($response);
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

			//lấy dữ liệu sp từ danh sách sp được chon
			$product = $data['product'];
			if(isset($product['id']) && is_array($product['id']) && count($product['id'])){
				foreach ($product['id'] as $key => $val) {
					$list_product[$key]['productid'] = $val; 
					$list_product[$key]['quantity'] = $product['quantity'][$key]; 
					$list_product[$key]['quantity_repay'] = $product['quantity_repay'][$key]; 
					$list_product[$key]['measure_repay'] = $product['measure_repay'][$key]; 
					$list_product[$key]['quantity'] = $product['quantity'][$key]; 
					$list_product[$key]['price'] = (int)str_replace('.','',$product['price'][$key]); 
				}
			}
			if($this->validate($data)){
				$_update = array(
					'data_json'=> base64_encode(json_encode($list_product ?? '')),
					'date_start' => convert_time($data['date_start']),
					'supplierid' => $data['supplierid'],
					'updated' => gmdate('Y-m-d H:i:s', time() + 7*3600),
					'userid_updated' => $this->auth['id'],
					'note'=>$data['note'],
				);
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
