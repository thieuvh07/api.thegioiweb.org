<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Construction extends REST_Controller {

	protected $module;
	protected $fieldKeywordArray;
	function __construct() {
		parent::__construct();
		$this->module = 'construction';
		$this->fieldKeywordArray =  array('fullname', 'phone', 'code');
	}

	public function update_put($id = 0){

		try {
			// get data
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}
			$id = (int) $id;
			if($id <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);

			// kiểm tra công trình có đủ điều kiện hủy không (sales_real = 0, thucdan = 0
			$construction = $this->autoload_model->_get_where(array(
				'table' => 'construction ',
				'select' => 'sales_real,
					(SELECT SUM(thucdan)  FROM construction_relationship WHERE construction_relationship.constructionid=construction.id AND construction_relationship.trash = 0) as thucdan_all,
					',
				'query' => 'construction.trash = 0 AND id = '.$id,
			));
			if(check_array($construction) && isset($construction['sales_real']) && isset($construction['thucdan_all']) && $construction['sales_real'] == 0 && $construction['thucdan_all'] == 0){
				$_update = array(
					'status' => 1,
					'updated' => gmdate('Y-m-d H:i:s', time() + 7*3600),
					'userid_updated' => $this->auth['id'],
				);	
				// processing
				$response = $this->crud->update(array('table' => $this->module, 'data' => $_update, 'where' => array('id' => $id)));
				return $this->response(json_encode($response), $response['code']);
			}else{
				return $this->response(json_encode(array('code' => '202', 'result' => false, 'message' => 'công trình không đủ điều kiện hủy')), 202);
			}
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
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

		if(isset($queryList['status_export']) && $queryList['status_export'] == 1){
			$query = $query.' AND (SELECT SUM(thucdan)  FROM construction_relationship WHERE construction_relationship.constructionid=construction.id AND construction_relationship.trash = 0) > 0';
		}
		if(isset($queryList['status_export']) && $queryList['status_export'] == 0){
			$query = $query.' AND (SELECT SUM(thucdan)  FROM construction_relationship WHERE construction_relationship.constructionid=construction.id AND construction_relationship.trash = 0) <= 0';
		}
		if(isset($queryList['status_construction']) && $queryList['status_construction'] == 1){
			$query = $query.' AND '.$this->module.'.sales_real > 0';
		}else{
			$query = $query.' AND '.$this->module.'.sales_real <= 0';
		}


		$data['query'] = substr($query, 4, strlen($query));
		$order_by = $this->module.'.created DESC';

		$data['order_by'] = (!empty($queryData['order_by'])) ? $queryData['order_by'].', '.$order_by : $order_by;

		// $data['join'] = array(array('customer', 'customer.id = construction.customerid', 'left'));
		return $data;
	}

	private function process_select($field = ''){
		$field = explode( ',' , $field);
		$field = $field ?? [];
		$array = array(
			'title_cata' => '(SELECT title FROM construction_catalogue WHERE construction_catalogue.id = construction.catalogueid AND construction_catalogue.trash = 0) as title_cata',
			'user_charge' => '(SELECT fullname FROM user WHERE user.id = construction.userid_charge AND user.trash = 0) as user_charge',
			'status_extend' => '(SELECT SUM(thucdan)  FROM construction_relationship WHERE construction_relationship.constructionid=construction.id AND construction_relationship.trash = 0) as thucdan_all, sales_real',
			'status_export' => '(SELECT SUM(thucdan)  FROM construction_relationship WHERE construction_relationship.constructionid=construction.id AND construction_relationship.trash = 0) as thucdan_all, sales_real',
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
		if(isset($list) && check_array($list)){
			foreach ($list as $key => $val) {
				if(isset($val['date_start'])){
					$list[$key]['date_start'] = gettime($val['date_start'], 'micro');
				}
				// kiểm tra trạng thái công trình
				// nếu đã có tổng thu là công trình hoàn thành
				if(!empty($val['sales_real'])){
					$list[$key]['status_construction'] = 1;
				}else{
					$list[$key]['status_construction'] = 0;
				}
				// nếu đơn xuất có số lượng thì là đã xuất hàng và ngược lại
				if(!empty($val['thucdan_all']) && $val['thucdan_all'] > 0){
					$list[$key]['status_export'] = 1;
				}else{
					$list[$key]['status_export'] = 0;
				}
				// kiểm tra trạng thái công trình hủy: khi chưa xuất hàng thucdan_all = 0,sales_real = 0	
				if(isset($val['sales_real']) && isset($val['thucdan_all']) && $val['sales_real'] == 0 && $val['thucdan_all'] == 0){
					$list[$key]['status_done'] = 1;
				}else{
					$list[$key]['status_done'] = 0;
				}

				if(isset($val['data_json'])){

					$detail = '';
					$data_json = json_decode(base64_decode($val['data_json']),true);
					if(isset($data_json) && check_array($data_json)){
						foreach ($data_json as $sub => $subs) {
							$detail = $detail.'- '.($subs['title'] ?? '').'(SL: '.($subs['quantity'] ?? '').')'.'('.($subs['trenphieu'] ?? '').'), <br>';
						}
					}
					$list[$key]['detail'] = $detail;
				}
			}
		}
		$response['data']['list'] = $list;
		return $response;
	}
	

	protected function validate($data){
		$this->load->library('form_validation');
		$this->form_validation->CI =& $this;
		$this->form_validation->set_data($data);

		$this->form_validation->set_rules('fullname','Tên khách hàng','trim|required');
		$this->form_validation->set_rules('product','Sản phẩm','callback__CheckProduct['.base64_encode(json_encode($data)).']');
		$this->form_validation->set_rules('catalogueid','Nhóm công trình','trim|required');
		$this->form_validation->set_rules('userid_charge','Nhân viên kinh doanh','trim|required');
		$this->form_validation->set_rules('fullname','Tên khách hàng','trim|required');


		if(!$this->form_validation->run($this)){
			return $this->response(json_encode(array('code' => 202, 'result' => false, 'message' => validation_errors(),)), 202);
		}else{
			return true;
		}
	}
	public function _CheckProduct($code, $data){
		$data = json_decode(base64_decode($data), true);
		if(isset($data['product']) && check_array($data['product'])){
			return true;
		}
		$this->form_validation->set_message('_CheckProduct','Bảng phải cho ít nhất 1 sản phẩm');
		return false;	
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
			'base_url' => 'construction/backend/construction/view'
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
			if(isset($queryList['status'])){
				$query = $query.' AND '.$this->module.'.status = '.$queryList['status'];
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
			$data['fields'] = $this->process_select($data['fields']);
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
					$list_product[$key]['id'] = $val; 
					$list_product[$key]['title'] = $product['title'][$key]; 
					$list_product[$key]['code'] = $product['code'][$key]; 
					$list_product[$key]['measure'] = $product['measure'][$key]; 
					$list_product[$key]['quantity'] = $product['quantity'][$key]; 
					$list_product[$key]['price_output'] = (int)str_replace('.','',$product['price_output'][$key]); 
					$list_product[$key]['trenphieu'] = $product['quantity'][$key];
				}
			}
			// return pre($list_product);
			if($this->validate($data)){
				$construction_catalogue = $this->autoload_model->_get_where(array(
					'table' => 'construction_catalogue',
					'select'=>'percent',
					'query' => 'trash = 0',
					'where'=>array('id'=>$this->input->post('catalogueid')),
				));
				$_insert = array(
					'catalogueid' => $data['catalogueid'],
					'construction_cata_percent' => $construction_catalogue['percent'] ?? 0,
					'code' => $data['code'],
					'fullname' => $data['fullname'],
					'phone' => $data['phone'],
					'export_code' => CODE_EXPORT.str_pad(($this->common->last_id('construction') +1 ), 4, '0', STR_PAD_LEFT),
					'date_start' => convert_time($data['date_start']),
					'sales_real' => (int)str_replace('.','',$data['sales_real'] ?? 0),
					'type_business' => $data['type_business'],
					'userid_charge' => $data['userid_charge'],
					'note' => $data['note'],
					'data_json'=> base64_encode(json_encode($list_product ?? [])),
					'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
					'userid_created' => $this->auth['id'],
				);	
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

			//lấy dữ liệu sp từ danh sách sp được chon
			$product = $data['product'];
			if(isset($product['id']) && is_array($product['id']) && count($product['id'])){
				foreach ($product['id'] as $key => $val) {
					$list_product[$key]['id'] = $val; 
					$list_product[$key]['title'] = $product['title'][$key]; 
					$list_product[$key]['code'] = $product['code'][$key]; 
					$list_product[$key]['measure'] = $product['measure'][$key]; 
					$list_product[$key]['quantity'] = $product['quantity'][$key]; 
					$list_product[$key]['quantity_old'] = $product['quantity_old'][$key] ?? 0; 
					$list_product[$key]['price_output'] = (int)str_replace('.','',$product['price_output'][$key]);
					$list_product[$key]['trenphieu'] = $product['quantity'][$key]; 
				}
			}
			// return pre($list_product);
			if($this->validate($data)){
				// kiểm tra số lượng trong kho có đủ không
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
		                    $quantity_change = $valPrd['quantity'] - $valPrd['quantity_old'];
		                    if($quantity_change > $quantity_closing_stock){
		                    	return $this->response(json_encode(array('code' => '202', 'result' => false, 'message' => 'Số lượng trong kho không đủ')), 202);
		                    }
						}
					}
				}
				
				$construction_catalogue = $this->autoload_model->_get_where(array(
					'table' => 'construction_catalogue',
					'select'=>'percent',
					'query' => 'trash = 0',
					'where'=>array('id'=>$this->input->post('catalogueid')),
				));

				// lấy ra data_json cũ
                $construction = $this->autoload_model->_get_where(array(
                    'query' => 'trash = 0 AND id = '.$id,
                    'table' => 'construction',
                    'select' => 'data_json',
                ));
                $data_json_old = json_decode(base64_decode($construction['data_json']), true);
                $data_json_new = $list_product;

                if(isset($data_json_new) && check_array($data_json_new)){
	                $data_json = $data_json_new;
	                if(isset($data_json_old) && check_array($data_json_old)){
	                	foreach ($data_json_old as $keyOld => $valOld) {
		                    foreach ($data_json_new as $keyNew => $valNew) {
		                        if($valOld['id'] == $valNew['id']){
		                            $aggregate = array_merge($valOld, $data_json[$keyNew]);
		                            unset($data_json[$keyNew]);
		                            $data_json[] = $aggregate;
		                        }
		                    }
		                }
		            }
                }
				$_update = array(
					'catalogueid' => $data['catalogueid'],
					'construction_cata_percent' => $construction_catalogue['percent'] ?? 0,
					'code' => $data['code'],
					'fullname' => $data['fullname'],
					'phone' => $data['phone'],
					'export_code' => CODE_EXPORT.str_pad(($this->common->last_id('construction') +1 ), 4, '0', STR_PAD_LEFT),
					'date_start' => convert_time($data['date_start']),
					'sales_real' => (int)str_replace('.','',$data['sales_real'] ?? 0),
					'type_business' => $data['type_business'],
					'userid_charge' => $data['userid_charge'],
					'note' => $data['note'],
					'data_json'=> base64_encode(json_encode($data_json ?? [])),
					'updated' => gmdate('Y-m-d H:i:s', time() + 7*3600),
					'userid_updated' => $this->auth['id'],
				);	
				// processing
				$response = $this->crud->update(array('table' => $this->module, 'data' => $_update, 'where' => array('id' => $id)));
				// return pre($response);
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
