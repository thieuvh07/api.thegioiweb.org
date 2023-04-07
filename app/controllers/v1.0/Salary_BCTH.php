<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Salary_BCTH extends REST_Controller {

	function __construct() {
		parent::__construct();
		 $this->load->library('salary_combie');
	}
	
	public function view_get(){
		try {
			// get data
			$periodicid = $this->input->get('periodicid');
			if($periodicid == "undefined"){
				$periodicid = $this->common->last_id('periodic');
			}

			if($periodicid <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);

			// lấy thời gian trong kì
			$periodic = $this->autoload_model->_get_where(array(
				'table' => 'periodic',
				'select' => 'id, date_start, date_end',
				'query' => 'trash = 0 AND id = '.$periodicid,
			));

			// lấy ra tổng tiền hàng tồn trong kho
			$product = $this->autoload_model->_get_where(array(
				'table' =>'product',
				'select' =>'
					SUM(((
						CASE WHEN 
							(	
								SELECT sum(tb1.quantity) FROM import_relationship as tb1  
								WHERE 	tb1.trash = 0 AND 
										tb1.created <= "'.$periodic['date_end'].'" AND 
										tb1.created >= "'.$periodic['date_start'].'" AND 
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
											tb1.created <= "'.$periodic['date_end'].'" AND 
											tb1.created >= "'.$periodic['date_start'].'" AND 
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
										tb1.created <= "'.$periodic['date_end'].'" AND 
										tb1.created >= "'.$periodic['date_start'].'" AND 
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
											tb1.created <= "'.$periodic['date_end'].'" AND 
											tb1.created >= "'.$periodic['date_start'].'" AND 
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
										tb3.created <= "'.$periodic['date_end'].'" AND 
										tb3.created >= "'.$periodic['date_start'].'" AND 
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
											tb3.created <= "'.$periodic['date_end'].'" AND 
											tb3.created >= "'.$periodic['date_start'].'" AND 
											tb3.productid = product.id AND 
											(SELECT trash FROM construction as tb4 WHERE tb4.id = tb3.constructionid) = 0 
									GROUP BY tb3.productid
								) 
					    END
					)+quantity_opening_stock)*price_input) as total_price_in_stock
				',
				'query' => 'trash = 0',
			));

			
			
			$data['total_price_in_stock'] =round($product['total_price_in_stock']);

			// tổng lợi nhuận từ bán hàng 
			$construction= $this->autoload_model->_get_where(array(
				'table'=>'construction',
				'select' => 'sum(profit_real) as profit_real',
				'query' => 'trash = 0 AND date_start <= "'.$periodic['date_end'].'" AND date_start >= "'.$periodic['date_start'].'"',
			));
			$data['profit_real'] = $construction['profit_real'];


			// tổng lợi nhuận từ công thợ = tổng công thợ -  tổng công thợ thực nhận
			// tổng công thợ thực nhận
			$workerReal = $this->worker($periodic);
			$total_money_work_real = 0;

			if(isset($workerReal) && check_array($workerReal) ){
				foreach ($workerReal as $key => $val) {
					$total_money_work_real = $total_money_work_real + $val['totalWorker'] ;
				}
			}
			// tổng công thợ
			$total_money_work = $this->autoload_model->_get_where(array(
				'table' => 'accountant as tb1',
				'select' => 'sum(tb1.money) as totalWork', 
				'join' => array(
					array('construction as tb2' , 'tb1.constructionid = tb2.id AND tb2.trash = 0', 'left'),
				),
				'query' => ' 13 IN (SELECT catalogueid FROM catalogue_relationship WHERE catalogue_relationship.moduleid = tb1.userid AND module="user")  AND tb1.trash = 0 AND tb2.date_start <= "'.$periodic['date_end'].'" AND tb2.date_start >= "'.$periodic['date_start'].'"',
			));
			$data['total_money_worker_profit'] = $total_money_work['totalWork'] - $total_money_work_real;

			// tổng chi phí lương
			$salary = $this->autoload_model->_get_where(array(
				'table'=>'salary',
				'select' => 'sum(salary + fine - bonus) as total_salary',
				'query' => 'trash = 0',
				'where'=>array('periodicid' => $periodicid),
			));
			$data['total_salary'] = $salary['total_salary'];
			// lấy tổng thu chi Hàng tháng
			$cash = $this->autoload_model->_get_where(array(
				'table'=>'cash',
				'select' => 'SUM(output - input) as total_HT',
				'query' => 'trash = 0 AND catalogueid = 8 AND time <= "'.$periodic['date_end'].'" AND time >= "'.$periodic['date_start'].'"',
			));
			$data['total_HT'] = $cash['total_HT'];
			// lấy tổng thu chi Phát sinh
			$cash = $this->autoload_model->_get_where(array(
				'table'=>'cash',
				'select' => 'SUM(output - input) as total_PS',
				'query' => 'trash = 0 AND catalogueid = 6 AND time <= "'.$periodic['date_end'].'" AND time >= "'.$periodic['date_start'].'"',
			));
			$data['total_PS'] = $cash['total_PS'];
			
			// processing
			return $this->response(json_encode(array('code' => '200', 'result' => true , 'message' => 'Lấy dũ liệ thành công', 'data' => array('list' => $data))), 200);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}

	// tổng công thợ thực nhận
	public function worker($periodic = ''){
		// lấy danh sách thợ, lương ứng, thưởng, phạt
		$user = $this->autoload_model->_get_where(array(
			'table' => 'user as tb1',
			'select' => 'tb1.id, tb1.fullname, SUM(tb4.output - tb4.input ) as ung_luong, tb5.bonus, tb5.fine, tb5.salary',
			'join' => array(
				array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.id AND tb2.trash = 0', 'left'),
				array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				array('cash as tb4' , '(tb4.userid = tb1.id) AND tb4.trash = 0 AND (tb4.time <= "'.$periodic['date_end'].'") AND (tb4.time >= "'.$periodic['date_start'].'")', 'left'),
				array('salary as tb5' , 'tb5.userid = tb1.id AND tb5.trash = 0 AND tb5.periodicid='.$periodic['id'], 'left'),
			),
			'group_by' => 'tb1.id',
			'query' => 'tb1.trash = 0 AND (tb3.slug="tho")',
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
							$totalWorkCT = $totalWorkCT+ $val['totalWorkCT'];
						}
					}
				}
				$totalWorkLG = 0;
				if(isset($constructionLG) && check_array($constructionLG) ){
					foreach ($constructionLG as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$totalWorkLG = $totalWorkCT+ $val['totalWorkLG'];
						}
					}
				}
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



	
}	
