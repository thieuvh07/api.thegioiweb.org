<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Periodic extends REST_Controller {

	protected $module;
	protected $fieldKeywordArray;
	function __construct() {
		parent::__construct();
		$this->module = 'periodic';
		$this->load->library('salary_combie');
		$this->fieldKeywordArray =  array('title');
	}
	protected function process_response($response){

		$list = $response['data']['list'];
		if(isset($list) && check_array($list)){
			foreach ($list as $key => $val) {
				if(isset($val['date_start'])){
					$list[$key]['date_start'] = gettime($val['date_start'], 'micro');
				}
				if(isset($val['date_end'])){
					$list[$key]['date_end'] = gettime($val['date_end'], 'micro');
				}
			}
		}
		$response['data']['list'] = $list;
		return $response;
	}

	public function index_get(){
		try {
			// get data
			$data = $this->rest_api->api_input('get');
			$data = array_filter($data);


			$data['table'] = $this->module;
			$data['order_by'] = 'id DESC';
			$data['select'] = 'id, title, note, date_start, date_end';

			// processing
			$response = $this->crud->get($data);
			$response = $this->process_response($response);
			return $this->response(json_encode($response), $response['code']);

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}

	}
	
	protected function validate($data){
		$this->load->library('form_validation');
		$this->form_validation->CI =& $this;
		$this->form_validation->set_data($data);

		$this->form_validation->set_rules('title','Tên kì','trim|required');
		$this->form_validation->set_rules('date_end','ngày kết thúc','trim|required');

		if(!$this->form_validation->run($this)){
			return $this->response(json_encode(array('code' => 202, 'result' => false, 'message' => validation_errors(),)), 202);
		}else{
			return true;
		}
	}


	/**
	 * index_post: Thực hiện thêm mới bản ghi
	 * 
	 * @param 
	 * @return json
	 */
	// ____________________________________thực hiện kết chuyển kì____________________________________
	public function index_post(){

		try {
			// get data
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}
			$data = $this->rest_api->api_input('post');
			if($this->validate($data)){
				$periodic = $this->autoload_model->_get_where(array( 
					'select' => 'id, date_end, date_start',
					'table' => $this->module, 
					'query' => 'trash = 0',
					'order_by' => 'id DESC',
				));
				if(!check_array($periodic)){
					$date_start = '-';
				}else{
					$date_start = $periodic['date_end'];
				}
				if($date_start > convert_time($data['date_end'])){
					return $this->response(json_encode(array('code' => '200', 'result' => false, 'message' => 'Vui lòng nhâp ngày kết thúc lớn hơn ngày bắt đầu')), 200);
				}
				// kết chuyển sản phẩm ( tồn đầu kì)
				$this->periodic_product($date_start);
				// kết chuyển tiền mặt
				$money_opening = $this->periodic_cash($date_start);
				// kết chuyển lương
				$this->periodic_salary($date_start, $periodic);
				// kết chuyển công trình
				$this->periodic_construction($date_start);

				// kết chuyển kì
				$_insert = array(
					'title' => htmlspecialchars_decode(html_entity_decode($data['title'])),
					'money_opening' => $money_opening,
					'date_start' => $date_start,
					'date_end' => convert_time($data['date_end']),
					'note' => $data['note'],
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

	protected function periodic_product($date_start){
		// lấy ra kì hiện tại
		$periodicid = $this->common->last_id('periodic');
		$periodic = $this->autoload_model->_get_where(array(
			'table' => 'periodic',
			'select' => 'date_end, date_start,money_opening',
			'query' => 'trash = 0 AND id = '.$periodicid,
		));
		// lấy số lượng tồn đầu kì
		$product = $this->autoload_model->_get_where(array(
			'table' => 'product',
			'select' => 'id , quantity_opening_stock,
				((
					CASE WHEN 
						(	
							SELECT sum(tb1.quantity) FROM import_relationship as tb1  
							WHERE 	tb1.trash = 0 AND 
									(SELECT date_start FROM import as tb4 WHERE tb4.id = tb3.importid) <= "'.$periodic['date_end'].'" AND 
									(SELECT date_start FROM import as tb4 WHERE tb4.id = tb3.importid) >= "'.$periodic['date_start'].'" AND 
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
										(SELECT date_start FROM import as tb4 WHERE tb4.id = tb3.importid) <= "'.$periodic['date_end'].'" AND 
										(SELECT date_start FROM import as tb4 WHERE tb4.id = tb3.importid) >= "'.$periodic['date_start'].'" AND 
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
									(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb3.repayid) <= "'.$periodic['date_end'].'" AND 
									(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb3.repayid) >= "'.$periodic['date_start'].'" AND 
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
										(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb3.repayid) <= "'.$periodic['date_end'].'" AND 
										(SELECT date_start FROM repay as tb4 WHERE tb4.id = tb3.repayid) >= "'.$periodic['date_start'].'" AND 
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
			',
			'query' => 'trash = 0',
		), true);

		// thêm vào bảng product_month
		if(isset($product) && check_array($product) ){
			$_insert_cash = [];
			foreach ($product as $key => $val) {
				$_update = [];
				$_update['quantity_opening_stock'] = $val['quantity_closing_stock'];
				$response = $this->autoload_model->_update(array('table' => 'product', 'data' => $_update, 'where' => array('id' => $val['id'])));

				$_insert_cash[$key]['productid'] = $val['id'];
				$_insert_cash[$key]['quantity_opening_stock'] = $val['quantity_opening_stock'];
				$_insert_cash[$key]['periodicid'] = $periodicid;
				$_insert_cash[$key]['trash'] = 0;
				$_insert_cash[$key]['created'] = gmdate('Y-m-d H:i:s', time() + 7*3600);
				$_insert_cash[$key]['userid_created'] = $this->auth['id'];
			}
			$response = $this->autoload_model->_create_batch(array('table' => 'product_month', 'data' => $_insert_cash));
		}
		return true;
	}


	protected function periodic_construction($date_start){
		//  Kết chuyển những công trình chưa hoàn thành hoặc đang đợi sẽ được sáng kỳ sau
		$construction = $this->autoload_model->_get_where(array(
			'table' => 'construction ',
			'select' => 'sales_real, status, id, 
				(SELECT SUM(thucdan)  FROM construction_relationship WHERE construction_relationship.constructionid=construction.id  AND construction_relationship.trash = 0) as thucdan_all,
				',
			'query' => 'trash = 0 AND status = 0',
		), true);
		if(isset($construction) && check_array($construction) ){
			foreach ($construction as $key => $val) {
				if(isset($val['sales_real']) && isset($val['thucdan_all']) && ($val['sales_real'] == 0 || $val['thucdan_all'] == 0)){
					$_update = array(
						'date_start' => $date_start,
						'updated' => gmdate('Y-m-d H:i:s', time() + 7*3600),
						'userid_updated' => $this->auth['id'],
					);	
					$response = $this->crud->update(array('table' => 'construction', 'data' => $_update, 'where' => array('id' => $val['id'])));
				}
			}
		}
		return true;
		
	}
	protected function periodic_cash($date_start){
		$periodicid = $this->common->last_id('periodic');
		// lấy tổng tiền cuối kì trước
		$periodic = $this->autoload_model->_get_where(array(
			'table' => 'periodic',
			'select' => 'date_end, date_start,money_opening',
			'query' => 'trash = 0 AND id = '.$periodicid,
		));
		// lấy ra tổng tiền cuối kì
		$cash = $this->autoload_model->_get_where(array(
			'table'=>'cash',
			'select' => '(SUM(input) - SUM(output)) as price',
			'order_by' => 'time DESC',
			'query' => 'trash = 0 AND time <= "'.$periodic['date_end'].'" AND time >= "'.$periodic['date_start'].'"',
		));
		$money_opening = $cash['price'] + $periodic['money_opening'];

		$_insert_cash = [];
		// lấy ra danh sách thu chi mặc đinh
		$cash_common = $this->autoload_model->_get_where(array(
			'table' => 'cash_common',
			'select' => 'catalogueid, title, input, output, note, supplierid, constructionid, userid',
			'query' => 'trash = 0',
		), true);
		if(isset($cash_common) && check_array($cash_common) ){
			foreach ($cash_common as $key => $val) {
				$val['time'] = $date_start;
				$val['created'] = gmdate('Y-m-d H:i:s', time() + 7*3600);
				$val['userid_created'] = $this->auth['id'];
				$val['trash'] = 0;
				$_insert_cash[] = $val;
			}
		}
		$response = $this->autoload_model->_create_batch(array('table' => 'cash', 'data' => $_insert_cash));
		return $money_opening;
	}

	protected function periodic_salary($date_start, $periodic){
		// lấy ra danh sách lương các loại nhân viên
		$salary['design'] = $this->design($periodic);
		$salary['office'] = $this->office($periodic);
		$salary['worker_outside'] = $this->worker_outside($periodic);
		$salary['worker'] = $this->worker($periodic);
		$index = 0;
		if(isset($salary) && check_array($salary) ){
			foreach ($salary as $key => $val) {
				if(isset($val) && check_array($val) ){
					foreach ($val as $sub => $subs) {
						$temp[$index]['catalogueid'] = 2;
						$temp[$index]['title'] = 'Kết chuyển lương tháng trước';
						$temp[$index]['input'] = $subs['totalSalary'];
						$temp[$index]['output'] = 0;
						$temp[$index]['note'] = '';
						$temp[$index]['supplierid'] = 0;
						$temp[$index]['constructionid'] = 0;
						$temp[$index]['userid'] = $subs['id'];
						$temp[$index]['time'] = $date_start;
						$temp[$index]['created'] = gmdate('Y-m-d H:i:s', time() + 7*3600);
						$temp[$index]['userid_created'] = $this->auth['id'];
						$temp[$index]['trash'] = 0;
						$index = $index +1;
					}
				}
			}
		}
		$response = $this->autoload_model->_create_batch(array('table' => 'cash', 'data' => $temp));
		return true;
	}

	protected function worker($periodic = ''){
		// lấy danh sách thợ, lương ứng, thưởng, phạt
		$user = $this->autoload_model->_get_where(array(
			'table' => 'user as tb1',
			'select' => 'tb1.id, tb1.fullname,  tb5.bonus, tb5.fine, tb5.salary,
				(SELECT SUM(output - input ) FROM  cash WHERE (cash.userid = tb1.id) AND (cash.time <= "'.$periodic['date_end'].'") AND (cash.time >= "'.$periodic['date_start'].'") AND cash.trash = 0 ) as ungluong,
			',
			'join' => array(
				array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.id AND tb2.trash = 0', 'left'),
				array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				array('salary as tb5' , 'tb5.userid = tb1.id AND tb5.trash = 0 AND tb5.periodicid='.$periodic['id'], 'left'),
			),
			'group_by' => 'tb1.id',
			'query' => 'tb1.trash = 0 AND tb3.slug="tho"',
		), true);

		// lấy công thợ từ công trình CT
		$constructionCT = $this->autoload_model->_get_where(array(
			'table' => 'accountant as tb1',
			'select' => 'sum(tb1.money) as totalWorkCT, userid', 
			'group_by' => 'tb1.userid',
			'join' => array(
				array('construction as tb2' , 'tb1.constructionid = tb2.id AND tb2.trash = 0', 'left'),
				array('construction_catalogue as tb3' , 'tb3.id = tb2.catalogueid AND tb3.trash = 0', 'left'),
			),
			'query' => 'tb1.trash = 0 AND tb3.slug="cong-trinh" AND tb2.date_start <= "'.$periodic['date_end'].'" AND tb2.date_start >= "'.$periodic['date_start'].'"',
		), true);

		// lấy công thợ từ công trình LG
		$constructionLG = $this->autoload_model->_get_where(array(
			'table' => 'accountant as tb1',
			'select' => 'sum(tb1.money) as totalWorkLG, userid', 
			'group_by' => 'tb1.userid',
			'join' => array(
				array('construction as tb2' , 'tb1.constructionid = tb2.id AND tb2.trash = 0', 'left'),
				array('construction_catalogue as tb3' , 'tb3.id = tb2.catalogueid AND tb3.trash = 0', 'left'),
			),
			'query' => 'tb1.trash = 0 AND tb3.slug="logo" AND tb2.date_start <= "'.$periodic['date_end'].'" AND tb2.date_start >= "'.$periodic['date_start'].'"',
		), true);

		
		if(isset($user) && check_array($user) ){
			foreach ($user as $keyUser => $valUser) {
				$totalWorkCT = 0;
				if(isset($constructionCT) && check_array($constructionCT) ){
					foreach ($constructionCT as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$totalWorkCT = $totalWorkCT + $val['totalWorkCT'];
						}
					}
				}
				$valUser['totalWorkCT'] = $totalWorkCT;
				$totalWorkLG = 0;
				if(isset($constructionLG) && check_array($constructionLG) ){
					foreach ($constructionLG as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$totalWorkLG = $totalWorkLG + $val['totalWorkLG'];
						}
					}
				}
				$valUser['totalWorkLG'] = $totalWorkLG;
				$salary_combie = $this->salary_combie->worker(array(
					'salary' => $valUser['salary'] ?? 0,
					'ung_luong' => $valUser['ung_luong'] ?? 0,
					'bonus' => $valUser['bonus'] ?? 0,
					'fine' => $valUser['fine'] ?? 0,
					'totalWorkLG' => $totalWorkLG ?? 0,
					'totalWorkCT' => $totalWorkCT ?? 0,
				));
				$user[$keyUser] = array_merge($valUser, $salary_combie);
				
			}
		}
		return $user ?? [];
	}

	protected function office($periodic = ''){
		// lấy danh sách thợ, lương ứng, thưởng, phạt
		$user = $this->autoload_model->_get_where(array(
			'table' => 'user as tb1',
			'select' => 'tb1.id, tb1.fullname, tb5.bonus, tb5.fine, tb5.salary,
				(SELECT SUM(output - input ) FROM  cash WHERE (cash.userid = tb1.id) AND (cash.time <= "'.$periodic['date_end'].'") AND (cash.time >= "'.$periodic['date_start'].'") AND cash.trash = 0 ) as ungluong,
			',
			'join' => array(
				array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.id AND tb2.trash = 0', 'left'),
				array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				array('salary as tb5' , 'tb5.userid = tb1.id  AND tb5.trash = 0 AND tb5.periodicid='.$periodic['id'], 'left'),
			),
			'group_by' => 'tb1.id',
			'query' => 'tb1.trash = 0 AND tb3.slug="ke-toan-van-phong" OR tb3.slug="kinh-doanh" OR tb3.slug="ke-toan-kho"',
		), true);

		// kinh doanh vs kế toán: lương cứng 6% KDVP + x% * KDCT - Ửng + Thưởng - Phạt

		// lấy lợi nhuận KDVP trong kì
		$constructionVP = $this->autoload_model->_get_where(array(
			'table' => 'construction as tb1',
			'select' => 'tb1.gross_revenue_real, tb1.profit_real, tb1.userid_charge as userid,
				SUM(tb2.thucdan*tb2.price_output + (tb2.trenphieu - tb2.thucdan) * (tb2.price_output - tb2.price_input) ) as gross_revenue , 
				SUM(tb2.thucdan*tb2.price_output + (tb2.trenphieu - tb2.thucdan) * (tb2.price_output - tb2.price_input) - tb2.thucdan*tb2.price_input) as profit , 
				',
			'group_by' => 'tb1.userid_charge',
			'join' => array(
				array('construction_relationship as tb2' , 'tb1.id = tb2.constructionid AND tb2.trash = 0', 'left'),
				array('type_business as tb5' , 'tb5.id = tb1.type_business AND tb5.trash = 0', 'left'),
			),
			'query' => 'tb1.trash = 0 AND tb5.title = "KDVP" AND tb1.date_start <= "'.$periodic['date_end'].'" AND tb1.date_start >= "'.$periodic['date_start'].'" ',
		), true);
		if(isset($constructionVP) && check_array($constructionVP)){
			foreach ($constructionVP as $key => $val) {
				$constructionVP[$key]['gross_revenue_real'] =!empty($val['gross_revenue_real']) ? $val['gross_revenue_real'] :  $val['gross_revenue'];
				$constructionVP[$key]['profit_real'] =!empty($val['profit_real']) ? $val['profit_real'] :  $val['profit'];
			}
		}

		// lấy lợi nhuận KDCT trong kì
		$constructionCT = $this->autoload_model->_get_where(array(
			'table' => 'construction as tb1',
			'select' => 'tb1.gross_revenue_real, tb1.profit_real, tb1.userid_charge as userid,
				SUM(tb2.thucdan*tb2.price_output + (tb2.trenphieu - tb2.thucdan) * (tb2.price_output - tb2.price_input) ) as gross_revenue , 
				SUM(tb2.thucdan*tb2.price_output + (tb2.trenphieu - tb2.thucdan) * (tb2.price_output - tb2.price_input) - tb2.thucdan*tb2.price_input) as profit , 
				',
			'group_by' => 'tb1.userid_charge',
			'join' => array(
				array('construction_relationship as tb2' , 'tb1.id = tb2.constructionid AND tb2.trash = 0', 'left'),
				array('type_business as tb5' , 'tb5.id = tb1.type_business AND tb5.trash = 0', 'left'),
			),
			'query' => 'tb1.trash = 0 AND tb5.title = "KDCT" AND tb1.date_start <= "'.$periodic['date_end'].'" AND tb1.date_start >= "'.$periodic['date_start'].'" ',
		), true);
		if(isset($constructionCT) && check_array($constructionCT)){
			foreach ($constructionCT as $key => $val) {
				$constructionCT[$key]['gross_revenue_real'] =!empty($val['gross_revenue_real']) ? $val['gross_revenue_real'] :  $val['gross_revenue'];
				$constructionCT[$key]['profit_real'] =!empty($val['profit_real']) ? $val['profit_real'] :  $val['profit'];
			}
		}

		// kinh doanh vs kế toán: lương cứng 6% KDVP + x% * KDCT - Ửng + Thưởng - Phạt
		if(isset($user) && check_array($user) ){
			foreach ($user as $keyUser => $valUser) {
				if(isset($constructionCT) && check_array($constructionCT) ){
					foreach ($constructionCT as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$profitCT = $val['profit_real'];
							$user[$keyUser]['profitCT'] = $profitCT;
							break;
						}
					}
				}
				if(isset($constructionVP) && check_array($constructionVP) ){
					foreach ($constructionVP as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$profitVP = $val['profit_real'];
							$user[$keyUser]['profitVP'] = $profitVP;
							break;
						}
					}
				}
				$salary_combie = $this->salary_combie->office(array(
					'salary' => $valUser['salary'] ?? 0,
					'ung_luong' => $valUser['ung_luong'] ?? 0,
					'bonus' => $valUser['bonus'] ?? 0,
					'fine' => $valUser['fine'] ?? 0,
					'profitVP' => $profitVP ?? 0,
					'profitVP' => $profitVP ?? 0,
				));
				$user[$keyUser] = array_merge($valUser, $salary_combie);
			}
		}
		return $user ?? [];
	}


	protected function worker_outside($periodic = ''){
		// lấy danh sách thợ, lương ứng, thưởng, phạt
		$user = $this->autoload_model->_get_where(array(
			'table' => 'user as tb1',
			'select' => 'tb1.id, tb1.fullname, tb5.bonus, tb5.fine, tb5.salary,
				(SELECT SUM(output - input ) FROM  cash WHERE (cash.userid = tb1.id) AND (cash.time <= "'.$periodic['date_end'].'") AND (cash.time >= "'.$periodic['date_start'].'") AND cash.trash = 0 ) as ungluong,
			',
			'join' => array(
				array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.id AND tb2.trash = 0', 'left'),
				array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				array('salary as tb5' , 'tb5.userid = tb1.id AND tb5.trash = 0 AND tb5.periodicid='.$periodic['id'], 'left'),
			),
			'group_by' => 'tb1.id',
			'query' => 'tb1.trash = 0 AND tb3.slug="tho-ngoai"',
		), true);


		

		// lấy công thợ từ công trình CT
		$constructionCT = $this->autoload_model->_get_where(array(
			'table' => 'accountant as tb1',
			'select' => 'sum(tb1.money) as totalWorkCT, userid', 
			'group_by' => 'tb1.userid',
			'join' => array(
				array('construction as tb2' , 'tb1.constructionid = tb2.id AND tb2.trash = 0', 'left'),
				array('construction_catalogue as tb3' , 'tb3.id = tb2.catalogueid AND tb3.trash = 0', 'left'),
			),
			'query' => 'tb1.trash = 0 AND tb3.slug="cong-trinh" AND tb2.date_start <= "'.$periodic['date_end'].'" AND tb2.date_start >= "'.$periodic['date_start'].'"',
		), true);

		// lấy công thợ từ công trình LG
		$constructionLG = $this->autoload_model->_get_where(array(
			'table' => 'accountant as tb1',
			'select' => 'sum(tb1.money) as totalWorkLG, userid', 
			'group_by' => 'tb1.userid',
			'join' => array(
				array('construction as tb2' , 'tb1.constructionid = tb2.id AND tb2.trash = 0', 'left'),
				array('construction_catalogue as tb3' , 'tb3.id = tb2.catalogueid AND tb3.trash = 0', 'left'),
			),
			'query' => 'tb1.trash = 0 AND tb3.slug="logo" AND tb2.date_start <= "'.$periodic['date_end'].'" AND tb2.date_start >= "'.$periodic['date_start'].'"',
		), true);

		
		if(isset($user) && check_array($user) ){
			foreach ($user as $keyUser => $valUser) {
				if(isset($constructionCT) && check_array($constructionCT) ){
					foreach ($constructionCT as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$totalWorkCT = $val['totalWorkCT'];
							$user[$keyUser]['totalWorkCT'] = $val['totalWorkCT'];
							break;
						}
					}
				}
				if(isset($constructionLG) && check_array($constructionLG) ){
					foreach ($constructionLG as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$totalWorkLG = $val['totalWorkLG'];
							$user[$keyUser]['totalWorkLG'] = $val['totalWorkLG'];
							break;
						}
					}
				}
				$salary_combie = $this->salary_combie->worker_outside(array(
					'salary' => $valUser['salary'] ?? 0,
					'ung_luong' => $valUser['ung_luong'] ?? 0,
					'bonus' => $valUser['bonus'] ?? 0,
					'fine' => $valUser['fine'] ?? 0,
					'totalWorkLG' => $totalWorkLG ?? 0,
					'totalWorkCT' => $totalWorkCT ?? 0,
				));
				$user[$keyUser] = array_merge($valUser, $salary_combie);
			}
		}
		return $user ?? [];
	}


	protected function design($periodic = ''){

		// lấy danh sách thợ, lương ứng, thưởng, phạt
		$user = $this->autoload_model->_get_where(array(
			'table' => 'user as tb1',
			'select' => 'tb1.id, tb1.fullname, tb5.bonus, tb5.fine, tb5.salary,
				(SELECT SUM(output - input ) FROM  cash WHERE (cash.userid = tb1.id) AND (cash.time <= "'.$periodic['date_end'].'") AND (cash.time >= "'.$periodic['date_start'].'") AND cash.trash = 0 ) as ungluong,
			',
			'join' => array(
				array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.id AND tb2.trash = 0', 'left'),
				array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				array('salary as tb5' , 'tb5.userid = tb1.id AND tb5.trash = 0 AND tb5.periodicid='.$periodic['id'], 'left'),
			),
			'group_by' => 'tb1.id',
			'query' => 'tb1.trash = 0 AND tb3.slug="thiet-ke"',
		), true);

		// lấy công thợ từ công trình CT
		$constructionCT = $this->autoload_model->_get_where(array(
			'table' => 'accountant as tb1',
			'select' => 'sum(tb1.money) as totalWorkCT, tb2.userid_charge as userid', 
			'group_by' => 'tb2.userid_charge',
			'join' => array(
				array('construction as tb2' , 'tb1.constructionid = tb2.id AND tb2.trash = 0', 'left'),
				array('construction_catalogue as tb3' , 'tb3.id = tb2.catalogueid AND tb3.trash = 0', 'left'),
			),
			'query' => 'tb1.trash = 0 AND tb3.slug="cong-trinh" AND tb2.date_start <= "'.$periodic['date_end'].'" AND tb2.date_start >= "'.$periodic['date_start'].'"',
		), true);

		// lấy công thợ từ công trình LG
		$constructionLG = $this->autoload_model->_get_where(array(
			'table' => 'accountant as tb1',
			'select' => 'sum(tb1.money) as totalWorkLG, tb2.userid_charge as userid', 
			'group_by' => 'tb2.userid_charge',
			'join' => array(
				array('construction as tb2' , 'tb1.constructionid = tb2.id AND tb2.trash = 0', 'left'),
				array('construction_catalogue as tb3' , 'tb3.id = tb2.catalogueid AND tb3.trash = 0', 'left'),
			),
			'query' => 'tb1.trash = 0 AND tb3.slug="logo" AND tb2.date_start <= "'.$periodic['date_end'].'" AND tb2.date_start >= "'.$periodic['date_start'].'"',
		), true);


		// lấy lợi nhuận KDVP trong kì
		$constructionVP = $this->autoload_model->_get_where(array(
			'table' => 'construction as tb1',
			'select' => 'tb1.gross_revenue_real, tb1.profit_real, tb1.userid_charge as userid,
				SUM(tb2.thucdan*tb2.price_output + (tb2.trenphieu - tb2.thucdan) * (tb2.price_output - tb2.price_input) ) as gross_revenue , 
				SUM(tb2.thucdan*tb2.price_output + (tb2.trenphieu - tb2.thucdan) * (tb2.price_output - tb2.price_input) - tb2.thucdan*tb2.price_input) as profit , 
				',
			'group_by' => 'tb1.userid_charge',
			'join' => array(
				array('construction_relationship as tb2' , 'tb1.id = tb2.constructionid AND tb2.trash = 0', 'left'),
				array('type_business as tb5' , 'tb5.id = tb1.type_business AND tb5.trash = 0', 'left'),
			),
			'query' => 'tb1.trash = 0 AND tb5.title = "KDVP" AND tb1.date_start <= "'.$periodic['date_end'].'" AND tb1.date_start >= "'.$periodic['date_start'].'" ',
		), true);
		if(isset($constructionVP) && check_array($constructionVP)){
			foreach ($constructionVP as $key => $val) {
				$constructionVP[$key]['gross_revenue_real'] =!empty($val['gross_revenue_real']) ? $val['gross_revenue_real'] :  $val['gross_revenue'];
				$constructionVP[$key]['profit_real'] =!empty($val['profit_real']) ? $val['profit_real'] :  $val['profit'];
			}
		}


		
		if(isset($user) && check_array($user) ){
			foreach ($user as $keyUser => $valUser) {
				if(isset($constructionCT) && check_array($constructionCT) ){
					foreach ($constructionCT as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$totalWorkCT = $val['totalWorkCT'];
							$user[$keyUser]['totalWorkCT'] = $val['totalWorkCT'];
							break;
						}
					}
				}
				if(isset($constructionLG) && check_array($constructionLG) ){
					foreach ($constructionLG as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$totalWorkLG = $val['totalWorkLG'];
							$user[$keyUser]['totalWorkLG'] = $val['totalWorkLG'];
							break;
						}
					}
				}
				if(isset($constructionVP) && check_array($constructionVP) ){
					foreach ($constructionVP as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$profitVP = $val['profit_real'];
							$user[$keyUser]['profitVP'] = $profitVP;
							break;
						}
					}
				}
				
				$salary_combie = $this->salary_combie->design(array(
					'salary' => $valUser['salary'] ?? 0,
					'ung_luong' => $valUser['ung_luong'] ?? 0,
					'bonus' => $valUser['bonus'] ?? 0,
					'fine' => $valUser['fine'] ?? 0,
					'profitVP' => $profitVP ?? 0,
					'totalWorkLG' => $totalWorkLG ?? 0,
					'totalWorkCT' => $totalWorkCT ?? 0,
				));
				$user[$keyUser] = array_merge($valUser, $salary_combie);
				
			}
		}
		return $user ?? [];
	}


}
