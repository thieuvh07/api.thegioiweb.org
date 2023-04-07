<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Product extends REST_Controller {

	protected $module;
	protected $fieldKeywordArray;
	function __construct() {
		parent::__construct();
		$this->module = 'product';
		$this->fieldKeywordArray =  array('title');
	}
	
	public function create_multi_post(){
		try {
			// get data
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}
			if(!check_permission($this->auth['id'] ?? '','product/backend/product/create_multi')){
				return $this->response(json_encode(array('code' => 200, 'result' => false, 'message' => 'Bạn không có quyền truy cập vào đây')), 200);
			}
			


			$data = $this->rest_api->api_input('post');

			$this->load->library('form_validation');
			$this->form_validation->CI =& $this;
			$this->form_validation->set_data($data);

			$this->form_validation->set_rules('catalogueid[]','Nhóm Sản phẩm','trim|required');
			$this->form_validation->set_rules('supplierid[]','Nhà cung cấp','trim|required');

			if(!$this->form_validation->run($this)){
				return $this->response(json_encode(array('code' => 202, 'result' => false, 'message' => validation_errors(),)), 202);
			}else{
				$detailCat = $this->autoload_model->_get_where(array(
					'table' => 'product_catalogue',
					'select'=>'id, title',
					'query' => 'trash = 0',
					'where'=> array('id'=>$data['catalogueid'][0]),
				));
				
				foreach ($data['product']['image'] as $key => $val) {
					$insert[] = array(
						'title' => $detailCat['title'].' Mã '.$data['product']['code'][$key],
						'image' => $val,
						'code' => $data['product']['code'][$key],
						'measure' => (int)$this->input->post('measure'),
						'catalogueid' => json_encode($data['catalogueid']),
						'quantity_opening_stock' => $data['quantity_opening_stock'],
						'supplierid' => json_encode($data['supplierid']),
						'price_input' => (int)str_replace('.','',$data['price_input']),
						'price_output' => (int)str_replace('.','',$data['price_output']),
						'publish' => 1,
						'trash' => 0,
						'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
						'userid_created' => $this->auth['id'],
					);
					$title = ($title ?? '').', '.$data['product']['title'][$key];
					$title = substr($title,0,2);
				}
				
				$_insert_remake = [];
				if(isset($insert) && is_array($insert) && count($insert)){
					foreach($insert as $key => $val){
						$flagCode = $this->autoload_model->_get_where(array(
							'select' => 'id',
							'table' => 'product',
							'query' => 'trash = 0',
							'where' => array('code' => $val['code']),
							'count' => TRUE
						));
						if($flagCode > 0) continue;
						
						$_insert_remake[] = array(
							'title' => $val['title'],
							'image' => $val['image'],
							'code' => $val['code'],
							'measure' => $val['measure'],
							'catalogueid' => $val['catalogueid'],
							'quantity_opening_stock' => $val['quantity_opening_stock'],
							'supplierid' => $val['supplierid'],
							'price_input' => $val['price_input'],
							'price_output' => $val['price_output'],
							'publish' => $val['publish'],
							'created' => $val['created'],
							'userid_created' => $val['userid_created'],
						);
					}
					
					if(isset($_insert_remake) && is_array($_insert_remake) && count($_insert_remake)){
						foreach($_insert_remake as $key => $val){
							$response = $this->crud->insert(array('table' => $this->module, 'data' => $val));
						}
					}
				}

				// processing
				
				return $this->response(json_encode($response), $response['code']);
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
		$query = $query.$queryData['query'];

		if(isset($queryList['trash'])){
			$query = $query.' AND product.trash = '.$queryList['trash'];
		}
		if(isset($queryList['catalogue'])){
			$join[] = array('product_relationship as tb_rela_1', $this->module.'.id = tb_rela_1.productid AND tb_rela_1.module ="product" AND tb_rela_1.trash = 0 AND tb_rela_1.catalogueid='.$queryList['catalogue'], 'inner');
		}
		if(isset($queryList['supplier'])){
			$join[] = array('product_relationship as tb_rela_2', $this->module.'.id = tb_rela_2.productid AND tb_rela_2.module ="supplier" AND tb_rela_2.trash = 0 AND tb_rela_2.catalogueid='.$queryList['supplier'], 'inner');
		}
		
		$data['query'] = substr($query, 4, strlen($query));
		$data['join'] = $join ?? '';
		$order_by = 'product.created ASC';
		$data['order_by'] = (!empty($queryData['order_by'])) ? $queryData['order_by1'].', '.$order_by : $order_by;

		// $data['join'] = array(array('customer', 'customer.id = product.customerid', 'left'));
		return $data;
	}
	private function process_select($field = ''){
		if(strrpos($field, 'user_created')){
			$temp = '(SELECT fullname FROM user WHERE user.id = '.$this->module.'.userid_created AND user.trash = 0) as user_created';
			$field = str_replace ( 'user_created', $temp, $field );
		}
		
		if(strrpos($field, 'product_count')){
			$temp = '(SELECT COUNT(id) FROM product WHERE product.id = '.$this->module.'.userid_created AND product.trash = 0) as product_count';
			$field = str_replace ( 'product_count', $temp, $field );
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
				if(isset($val['image'])){
					$list[$key]['image'] = $val['image'];
				}
				if(isset($val['quantity_opening_stock'])){
					$list[$key]['quantity_opening_stock'] = round($val['quantity_opening_stock'],2);
				}
				if(isset($val['quantity_closing_stock'])){
					$list[$key]['quantity_closing_stock'] = round($val['quantity_closing_stock'], 2);
				}
				if(isset($val['total_price'])){
					$list[$key]['total_price'] = round($val['total_price'], 2);
				}
				if(isset($val['catalogueid'])){
					$catalogueid = json_decode($val['catalogueid'], true);
					$product_catalogue = $this->autoload_model->_get_where(array(
						'table' => 'product_catalogue',
						'select' => 'title',
						'where_in' => $catalogueid,
						'where_in_field' => 'id',
						'query' => 'trash = 0',
					), true);
					$list[$key]['catalogueid'] = $product_catalogue;
				}
				if(isset($val['supplierid'])){
					$supplierid = json_decode($val['supplierid'], true);
					$supplier = $this->autoload_model->_get_where(array(
						'table' => 'supplier',
						'select' => 'title',
						'where_in_field' => 'id',
						'where_in' => $supplierid,
						'query' => 'trash = 0',
					), true);
					$list[$key]['supplierid'] = $supplier;
				}
			}
		}
		$response['data']['list'] = $list;
		return $response;
	}
	

	protected function process_response_get($response){
		$list = $response['data']['list'];
		$list = quantity_closing_stock($list);
		$this->load->library(array('configbie'));
		if(isset($list) && check_array($list)){
			foreach ($list as $key => $val) {
				if(isset($val['measure'])){
					$val['measure'] = $this->configbie->data('measure',$val['measure']);
				}	
				if(check_array($val)){
					$list[$key]['data-info'] = base64_encode(json_encode($val));
				}	
			}
		}
		$response['data']['list'] = $list;
		return $response;
	}

	protected function set_data($data, $method){
		$field = array('id','title','code','price_input','price_output','catalogueid','supplierid','image','quantity_opening_stock','measure','publish');
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

		$this->form_validation->set_rules('title','Tên Sản phẩm','trim|required');
		$this->form_validation->set_rules('code','Mã Sản phẩm','trim|required|callback__CheckCode['.base64_encode(json_encode($data)).']');
		$this->form_validation->set_rules('catalogueid[]','Nhóm Sản phẩm','trim|required');
		$this->form_validation->set_rules('supplierid[]','Nhà cung cấp','trim|required');


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
				$this->form_validation->set_message('_CheckCode','Mã SP đã tồn tại');
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
			'base_url' => 'product/backend/product/view'
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
			if(isset($queryList['supplier'])){
				$join[] = array('product_relationship as tb_rela_2', $this->module.'.id = tb_rela_2.productid AND tb_rela_2.module ="supplier"  AND tb_rela_2.trash = 0 AND tb_rela_2.catalogueid='.$queryList['supplier'], 'inner');
			}
			$data['join'] = $join;
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
			
			$response = $this->process_response_get($response);
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
			// return pre($data);

			// count record
			$response = $this->crud->count(array( 'table' => $this->module, 'join' => $data['join'],'query' => $data['query'], 'count' => TRUE,));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}else{
				$total_rows = $response['data']['count'];
			}
			if($total_rows <= 0){
				return $this->response(json_encode(array('code' => 200, 'result' => true, 'message' => 'Không có bản ghi nào', 'data' => array('list' => [], 'from' =>  0, 'to' => 0, 'total_rows' => 0, 'pagination' => ''))), REST_Controller::HTTP_OK);
			}else{
				$periodicid = $this->common->last_id('periodic');
				// lấy thời gian trong kì
				$periodic = $this->autoload_model->_get_where(array(
					'table' => 'periodic',
					'select' => 'id, date_start, date_end',
					'query' => 'trash = 0 AND id = '.$periodicid,
				));
				$response = $this->crud->get(array(
					'table' =>'product',
					'start' => $data['start'],
					'limit' => $data['limit'],
					'join' => $data['join'],
					'query' => $data['query'],
					'order_by' => $data['order_by'],
					'select' => $data['select'].',price_input,
						((
							CASE WHEN 
								(	
									SELECT sum(tb1.quantity) FROM import_relationship as tb1  
									WHERE 	tb1.trash = 0 AND 
											(SELECT date_start FROM import as tb4 WHERE tb4.id = tb1.importid) <= "'.$periodic['date_end'].'" AND 
											(SELECT date_start FROM import as tb4 WHERE tb4.id = tb1.importid) >= "'.$periodic['date_start'].'" AND 
											tb1.productid = product.id AND 
											(SELECT trash FROM import as tb2 WHERE tb2.id = tb1.importid) = 0 
									GROUP BY tb1.productid
								) 
							IS NULL 
							    THEN 0
							    ELSE 
							    	(	
										SELECT sum(tb1.quantity) FROM import_relationship as tb1  
										WHERE 	tb1.trash = 0 AND 
												(SELECT date_start FROM import as tb4 WHERE tb4.id = tb1.importid) <= "'.$periodic['date_end'].'" AND 
												(SELECT date_start FROM import as tb4 WHERE tb4.id = tb1.importid) >= "'.$periodic['date_start'].'" AND 
												tb1.productid = product.id AND 
												(SELECT trash FROM import as tb2 WHERE tb2.id = tb1.importid) = 0 
										GROUP BY tb1.productid
									) 
							END 
							
							+

							CASE WHEN 
								(	
									SELECT sum(tb1.quantity) FROM repay_relationship as tb1  
									WHERE 	tb1.trash = 0 AND 
											(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb1.repayid) <= "'.$periodic['date_end'].'" AND 
											(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb1.repayid) >= "'.$periodic['date_start'].'" AND 
											tb1.productid = product.id AND 
											(SELECT trash FROM repay as tb2 WHERE tb2.id = tb1.repayid) = 0 
									GROUP BY tb1.productid
								)
							IS NULL 
							    THEN 0 
							    ELSE
							    	(	
										SELECT sum(tb1.quantity) FROM repay_relationship as tb1  
										WHERE 	tb1.trash = 0 AND 
												(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb1.repayid) <= "'.$periodic['date_end'].'" AND 
												(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb1.repayid) >= "'.$periodic['date_start'].'" AND 
												tb1.productid = product.id AND 
												(SELECT trash FROM repay as tb2 WHERE tb2.id = tb1.repayid) = 0 
										GROUP BY tb1.productid
									)
							END

							-

							CASE WHEN
								(	
									SELECT sum(tb3.thucdan) FROM construction_relationship as tb3  
									WHERE 	tb3.trash = 0 AND 
											(SELECT date_start FROM construction as tb4 WHERE tb4.id = tb3.constructionid) <= "'.$periodic['date_end'].'" AND 
											(SELECT date_start FROM construction as tb4 WHERE tb4.id = tb3.constructionid) >= "'.$periodic['date_start'].'" AND 
											tb3.productid = product.id AND 
											(SELECT trash FROM construction as tb4 WHERE tb4.id = tb3.constructionid) = 0 
									GROUP BY tb3.productid
								) 
							IS NULL 
							    THEN 0 
							    ELSE
								    (	
										SELECT sum(tb3.thucdan) FROM construction_relationship as tb3  
										WHERE 	tb3.trash = 0 AND 
												(SELECT date_start FROM construction as tb4 WHERE tb4.id = tb3.constructionid) <= "'.$periodic['date_end'].'" AND 
												(SELECT date_start FROM construction as tb4 WHERE tb4.id = tb3.constructionid) >= "'.$periodic['date_start'].'" AND 
												tb3.productid = product.id AND 
												(SELECT trash FROM construction as tb4 WHERE tb4.id = tb3.constructionid) = 0 
										GROUP BY tb3.productid
									) 
						    END
						)+quantity_opening_stock) as quantity_closing_stock,

						(((
							CASE WHEN 
								(	
									SELECT sum(ROUND(tb1.quantity,2)) FROM import_relationship as tb1  
									WHERE 	tb1.trash = 0 AND 
											(SELECT date_start FROM import as tb4 WHERE tb4.id = tb1.importid) <= "'.$periodic['date_end'].'" AND 
											(SELECT date_start FROM import as tb4 WHERE tb4.id = tb1.importid) >= "'.$periodic['date_start'].'" AND 
											tb1.productid = product.id AND 
											(SELECT trash FROM import as tb2 WHERE tb2.id = tb1.importid) = 0 
									GROUP BY tb1.productid
								) 
							IS NULL 
							    THEN 0
							    ELSE 
							    	(	
										SELECT sum(ROUND(tb1.quantity,2)) FROM import_relationship as tb1  
										WHERE 	tb1.trash = 0 AND 
												(SELECT date_start FROM import as tb4 WHERE tb4.id = tb1.importid) <= "'.$periodic['date_end'].'" AND 
												(SELECT date_start FROM import as tb4 WHERE tb4.id = tb1.importid) >= "'.$periodic['date_start'].'" AND 
												tb1.productid = product.id AND 
												(SELECT trash FROM import as tb2 WHERE tb2.id = tb1.importid) = 0 
										GROUP BY tb1.productid
									) 
							END 
							
							+

							CASE WHEN 
								(	
									SELECT sum(ROUND(tb1.quantity,2)) FROM repay_relationship as tb1  
									WHERE 	tb1.trash = 0 AND 
											(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb1.repayid) <= "'.$periodic['date_end'].'" AND 
											(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb1.repayid) >= "'.$periodic['date_start'].'" AND 
											tb1.productid = product.id AND 
											(SELECT trash FROM repay as tb2 WHERE tb2.id = tb1.repayid) = 0 
									GROUP BY tb1.productid
								)
							IS NULL 
							    THEN 0 
							    ELSE
							    	(	
										SELECT sum(ROUND(tb1.quantity,2)) FROM repay_relationship as tb1  
										WHERE 	tb1.trash = 0 AND 
												(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb1.repayid) <= "'.$periodic['date_end'].'" AND 
												(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb1.repayid) >= "'.$periodic['date_start'].'" AND 
												tb1.productid = product.id AND 
												(SELECT trash FROM repay as tb2 WHERE tb2.id = tb1.repayid) = 0 
										GROUP BY tb1.productid
									)
							END

							-

							CASE WHEN
								(	
									SELECT sum(ROUND(tb3.thucdan,2)) FROM construction_relationship as tb3  
									WHERE 	tb3.trash = 0 AND 
											(SELECT date_start FROM construction as tb4 WHERE tb4.id = tb3.constructionid) <= "'.$periodic['date_end'].'" AND 
											(SELECT date_start FROM construction as tb4 WHERE tb4.id = tb3.constructionid) >= "'.$periodic['date_start'].'" AND 
											tb3.productid = product.id AND 
											(SELECT trash FROM construction as tb4 WHERE tb4.id = tb3.constructionid) = 0 
									GROUP BY tb3.productid
								) 
							IS NULL 
							    THEN 0 
							    ELSE
								    (	
										SELECT sum(ROUND(tb3.thucdan,2)) FROM construction_relationship as tb3  
										WHERE 	tb3.trash = 0 AND 
												(SELECT date_start FROM construction as tb4 WHERE tb4.id = tb3.constructionid) <= "'.$periodic['date_end'].'" AND 
												(SELECT date_start FROM construction as tb4 WHERE tb4.id = tb3.constructionid) >= "'.$periodic['date_start'].'" AND 
												tb3.productid = product.id AND 
												(SELECT trash FROM construction as tb4 WHERE tb4.id = tb3.constructionid) = 0 
										GROUP BY tb3.productid
									) 
						    END
						)+quantity_opening_stock)*price_input) as total_price

					',
				), true);

				
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
				// return pre($response);
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
