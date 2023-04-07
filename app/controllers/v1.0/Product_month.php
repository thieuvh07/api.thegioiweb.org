<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Product_month extends REST_Controller {
	protected $module;
	protected $fieldKeywordArray;
	function __construct() {
		parent::__construct();
		$this->module = 'product';
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

	public function search_get(){
		try {
			// get data
			// get data
			$data = $this->rest_api->api_input('get');

			$data = $this->convert_data($data);


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
			// lấy sản phẩm nhập về trong kì( important_relationship)
			$import_relationship = $this->autoload_model->_get_where(array(
				'table' => 'import_relationship as tb2',
				'select' => 'SUM(tb2.quantity) as quantity, tb2.productid',
				'group_by' => 'tb2.productid',
				'join' => array(array('import as tb1' , 'tb1.id = tb2.importid AND tb1.trash = 0', 'left')),
				'query' => 'tb1.created <= "'.$periodic['date_end'].'" AND tb1.created >= "'.$periodic['date_start'].'"',
			), true);

			// lấy sản phẩm nhập về trong kì( important_relationship)
			$repay_relationship = $this->autoload_model->_get_where(array(
				'table' => 'repay_relationship as tb2',
				'select' => 'SUM(tb2.quantity) as quantity, tb2.productid',
				'group_by' => 'tb2.productid',
				'join' => array(array('repay as tb1' , 'tb1.id = tb2.repayid AND tb1.trash = 0', 'left')),
				'query' => 'tb1.created <= "'.$periodic['date_end'].'" AND tb1.created >= "'.$periodic['date_start'].'"',
			), true);


			// lấy sản phẩm xuất về trong kì( repayant_relationship)
			$construction_relationship = $this->autoload_model->_get_where(array(
				'table' => 'construction_relationship as tb2',
				'select' => 'SUM(tb2.thucdan) as quantity, tb2.productid',
				'group_by' => 'tb2.productid',
				'join' => array(array('construction as tb1' , 'tb1.id = tb2.constructionid AND tb1.trash = 0', 'left')),
				'query' => 'tb1.date_start <= "'.$periodic['date_end'].'" AND tb1.date_start >= "'.$periodic['date_start'].'"',
			), true);

			// lấy số lượng tồn đầu kì
			
			$join = check_array($data['join']) ? $data['join'] : array();
			$join[] = array('product_month as tb2000' , 'product.id = tb2000.productid AND tb2000.periodicid = '.$periodicid, 'left');
			$product_month = $this->autoload_model->_get_where(array(
				'table' => 'product ',
				'select' => 'product.id, tb2000.quantity_opening_stock, product.title, product.image, product.code',
				'join' => $join,
				'query' => 'product.trash = 0 AND '.$data['query'],
				'order_by' => $data['order_by'],
			), true);
			if(isset($import_relationship) && check_array($import_relationship)){
				foreach ($import_relationship as $sub => $subs) {
					if(isset($product_month) && check_array($product_month)){
						foreach ($product_month as $key => $val) {
							if($subs['productid'] == $val['id']){
								$product_month[$key]['import'] = round($subs['quantity'],2);
							}
						}
					}
					
				}
			}

			if(isset($repay_relationship) && check_array($repay_relationship)){
				foreach ($repay_relationship as $sub => $subs) {
					if(isset($product_month) && check_array($product_month)){
						foreach ($product_month as $key => $val) {
							if($subs['productid'] == $val['id']){
								$product_month[$key]['repay'] = round($subs['quantity'],2);
							}
						}
					}
					
				}
			}
			if(isset($construction_relationship) && check_array($construction_relationship)){
				foreach ($construction_relationship as $sub => $subs) {
					if(isset($product_month) && check_array($product_month)){
						foreach ($product_month as $key => $val) {
							if($subs['productid'] == $val['id']){
								$product_month[$key]['export'] = round($subs['quantity'],2);
							}
						}
					}
				}
			}
			if(isset($product_month) && check_array($product_month)){
				foreach ($product_month as $key => $val) {
					if(empty($val['import']) && empty($val['export']) && empty($val['repay']) ){
						unset($product_month[$key]);
					}
				}
			}
			$product_month = array_values($product_month);
			$product_month = quantity_closing_stock($product_month);
			// processing
			return $this->response(json_encode(array('code' => '200', 'result' => true , 'message' => 'Lấy dũ liệ thành công', 'data' => array('list' => $product_month))), 200);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}
}
