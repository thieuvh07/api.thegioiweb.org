<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Import extends REST_Controller {

	protected $module;
	protected $fieldKeywordArray;
	function __construct() {
		parent::__construct();
		$this->module = 'import';
		$this->fieldKeywordArray =  array('code', 'id');
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

		// $data['join'] = array(array('customer', 'customer.id = import.customerid', 'left'));
		return $data;
	}
	private function process_select($field = ''){
		$field = explode( ',' , $field);
		$field = $field ?? [];
		$array = array(
			'supplier' => '(SELECT title FROM supplier WHERE supplier.id = import.supplierid) as supplier',
			'user_created' => '(SELECT fullname FROM user WHERE user.id = import.userid_created) as user_created',
			'total_money' => '(SELECT SUM(quantity*price) FROM import_relationship WHERE import_relationship.importid = import.id AND import_relationship.trash = 0) as total_money',
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
			
		$import_relationship = $this->autoload_model->_get_where(array(
			'table'=>'import_relationship',
			'select'=>'measure_import, price, quantity,(SELECT title FROM product WHERE product.id = import_relationship.productid ) as title_product, importid',
			'query' => 'import_relationship.trash = 0',
			'where_in' => $list_id,
			'where_in_field' => 'importid',
		),true);


		if(isset($list) && check_array($list)){
			foreach ($list as $key => $val) {
				if(isset($val['date_start'])){
					$list[$key]['date_start'] = gettime($val['date_start'], 'micro');
				}
				if(isset($val['id'])){
					$detail = ''; 
					foreach ($import_relationship as $sub => $subs) {
						if($subs['importid'] == $val['id']){
							$detail = $detail.($subs['quantity']).' '.$subs['measure_import'].' '.$subs['title_product'].',';
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
			'base_url' => 'import/backend/import/view'
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
					$list_product[$key]['quantity'] = $product['quantity'][$key]; 
					$list_product[$key]['quantity_import'] = $product['quantity_import'][$key]; 
					$list_product[$key]['measure_import'] = $product['measure_import'][$key]; 
					$list_product[$key]['quantity'] = $product['quantity'][$key]; 
					$list_product[$key]['price'] = (int)str_replace('.','',$product['price'][$key]); 
				}
			}
			if($this->validate($data)){
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
					$list_product[$key]['quantity_import'] = $product['quantity_import'][$key]; 
					$list_product[$key]['measure_import'] = $product['measure_import'][$key]; 
					$list_product[$key]['quantity'] = $product['quantity'][$key]; 
					$list_product[$key]['price'] = (int)str_replace('.','',$product['price'][$key]); 
				}
			}
			if($this->validate($data)){
				$_update = array(
					'date_start' => convert_time($data['date_start']),
					'data_json'=> base64_encode(json_encode($list_product ?? '')),
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
	
	public function excel_get(){
		$url = substr(APPPATH, 0, -4);
		$excel_path = $url.'plugin/PHPExcel/Classes/PHPExcel.php';
		require($excel_path);
		$supplierid = $this->input->get('supplierid');
		$data['detailSupplier'] = $this->autoload_model->_get_where(array(
			'table' => 'supplier',
			'select' => 'id, code, title,phone,bank,email, website,fax, mst, publish, address, user_charge,object', 
			'query' => 'trash = 0',
			'where'=> array('id'=>$supplierid),
		));
		if(!isset($data['detailSupplier']) || is_array($data['detailSupplier']) == false || count($data['detailSupplier']) == 0){
			return $this->response(json_encode(array('code' => '200', 'result' => false, 'message' => 'Nhà cung cấp không tồn tại')), 200);
		}
		$supplierWhere = ($supplierid > 0) ? 'supplierid = "'.$supplierid.'"' :'' ;
		
		$objPHPExcel = new PHPExcel();
		$objPHPExcel->setActiveSheetIndex(0); 
		$listImport = $this->autoload_model->_get_where(array(
			'table'=>$this->module,
			'query'=> $supplierWhere,
			'where' => array('trash' => 0),
			'select'=>'id, code, supplierid,  status,
				(SELECT SUM(quantity*price) FROM import_relationship WHERE import_relationship.importid = import.id AND import_relationship.trash = 0) as total_money,
				(SELECT title FROM supplier WHERE supplier.id = import.supplierid AND supplier.trash = 0) as supplier,
				(SELECT fullname FROM user WHERE user.id = import.userid_created AND user.trash = 0) as userid_created',
			'order_by' => 'created desc',
		),true);
		
	
		$columnArray = array("A", "B", "C", "D", "E", "F", "G", "H","I","J");
		$titlecolumnArray = array('STT','ID','MÃ Đơn nhập','Nhà cung cấp','Trạng thái','Kho nhập','Mã hàng','Số lượng','Đơn giá','Tổng tiền');
		$row_count = 1;
		 $styleArray = array(
			  'borders' => array(
				  'allborders' => array(
					  'style' => PHPExcel_Style_Border::BORDER_THIN
				  )
			  )
		  );
		$objPHPExcel->getDefaultStyle()->applyFromArray($styleArray);
		foreach($columnArray as $key => $val){
			$objPHPExcel->getActiveSheet()->SetCellValue($val.$row_count, $titlecolumnArray[$key]);  // lấy ra tiêu đề của từng cột	
			 $objPHPExcel->getActiveSheet()->getColumnDimension($val)->setAutoSize(true);
			$objPHPExcel->getActiveSheet()->getStyle($val.$row_count)->applyFromArray(
				array(
					'fill' => array(
						'type' => PHPExcel_Style_Fill::FILL_SOLID,
						'color' => array('rgb' => 'F28A8C')
					)
				)
			);
		}
		$i = 2;
		$total_row = $i + count($listImport);
		$total = 0;
		
		if(isset($listImport) && is_array($listImport) && count($listImport)){
			foreach($listImport as $key => $val){
				
				$product = $this->autoload_model->_get_where(array(
					'select' => '*, 
						(SELECT title FROM product WHERE import_relationship.productid = product.id AND product.trash = 0) as product_title, 
						(SELECT code FROM product WHERE import_relationship.productid = product.id AND product.trash = 0) as product_code',
					'table' => 'import_relationship',
					'query' => 'trash = 0',
					'where' => array('importid' => $val['id']),
				));
				
			
				$objPHPExcel->getActiveSheet()->getRowDimension($i)->setRowHeight(50);
				$objPHPExcel->getActiveSheet()->SetCellValue('A'.$i, $i); 
				$objPHPExcel->getActiveSheet()->SetCellValue('B'.$i, $val['id']); 
				$objPHPExcel->getActiveSheet()->SetCellValue('C'.$i, $val['code']); 
				$objPHPExcel->getActiveSheet()->SetCellValue('D'.$i, $val['supplier']); 
				$objPHPExcel->getActiveSheet()->SetCellValue('E'.$i, (($val['status']==0) ? 'Chờ nhận hàng' : 'Đã nhận hàng')); 
				$objPHPExcel->getActiveSheet()->SetCellValue('F'.$i, 'Hàng trong kho'); 
				$objPHPExcel->getActiveSheet()->SetCellValue('G'.$i, $product['product_code']); 
				$objPHPExcel->getActiveSheet()->SetCellValue('H'.$i, $product['quantity_import']); 
				$objPHPExcel->getActiveSheet()->SetCellValue('I'.$i, $product['price']); 
				$objPHPExcel->getActiveSheet()->SetCellValue('J'.$i, $val['total_money']); 
				
				
				
				$i++;
			}
		}
		$random = random(6, true);
		$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel); 
		$objWriter->save(''.$url.'upload/files/excel/import'.$random.str_replace('/','_',date("Y/m/d")).'.xlsx'); 
		$data['filename'] = 'upload/files/excel/import'.$random.str_replace('/','_',date("Y/m/d")).'.xlsx';
		// return pre(site_url($data['filename']));
		return $this->response(json_encode(array('code' => '200', 'result' => true, 'message' => 'Đã gửi file exel tới mail', 'data' => array('list' => BASE_URL.$data['filename']))), 200);
	}
}
