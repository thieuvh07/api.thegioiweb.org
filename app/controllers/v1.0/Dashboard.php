<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Dashboard extends REST_Controller {

	function __construct() {
		parent::__construct();
	}
	
	public function quantity_closing_stock_get(){
		$id = $this->input->get('id');
		$product = $this->autoload_model->_get_where(array(
			'table' => 'product',
			'select' => 'id , quantity_opening_stock,',
			'where' => array('id' => $id),
		));
		$product = quantity_closing_stock($product);
		$product['quantity_closing_stock'] = ROUND($product['quantity_closing_stock'],2);
		return $this->response(json_encode(array('code' => '200', 'result' => true , 'message' => 'Lấy dũ liệu thành công', 'data' => $product)), 200);
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
				'select' => 'date_start, date_end',
				'query' => 'trash = 0 AND id = '.$periodicid,
			));
			$date_start = gettime($periodic['date_start'], 'Y-m-d');
			$date_end = gettime($periodic['date_end'], 'Y-m-d');
			$period = new DatePeriod(
			     new DateTime($date_start),
			     new DateInterval('P1D'),
			     new DateTime($date_end)
			);
			foreach ($period as $key => $value) {
			    $time[$key]['date'] = $value->format('Y-m-d')  ;     
			    $time[$key]['day'] = $value->format('d')  ;     
			      
			}
			
			$construction = $this->autoload_model->_get_where(array(
				'table' => 'construction as tb1',
				'select' => 'cast(date_start as date) as date_start, gross_revenue_real, sales_real,
					(SELECT SUM(thucdan*price_output + (trenphieu - thucdan) * (price_output - price_input) )  FROM construction_relationship WHERE construction_relationship.constructionid=tb1.id AND construction_relationship.trash = 0) as gross_revenue,
					(SELECT SUM(thucdan*price_output + (trenphieu - thucdan) * (price_output - price_input) - thucdan*price_input)  FROM construction_relationship WHERE construction_relationship.constructionid=tb1.id AND construction_relationship.trash = 0) as profit,
					',
				'query' => 'tb1.trash = 0 AND tb1.date_start <= "'.$periodic['date_end'].'" AND tb1.date_start >= "'.$periodic['date_start'].'" ',
			), true);

			if(isset($construction) && check_array($construction)){
				foreach ($construction as $key => $val) {
					$construction[$key]['gross_revenue_real'] =!empty($val['gross_revenue_real']) ? $val['gross_revenue_real'] :  $val['gross_revenue'];
					$construction[$key]['profit_real'] =!empty($val['profit_real']) ? $val['profit_real'] :  $val['profit'];
				}
			}
			if(isset($construction) && check_array($construction) && isset($time) && check_array($time)){
				foreach ($time as $keyTime => $valTime) {
					$time[$keyTime]['profit_real'] = 0  ;   
					$time[$keyTime]['gross_revenue_real'] = 0  ;  
					$gross_revenue_real = 0; 
					$profit_real = 0; 
					foreach ($construction as $keyCon => $valCon) {
						if($valTime['date'] == $valCon['date_start'] ){
							$gross_revenue_real = $gross_revenue_real + $valCon['gross_revenue_real'];
							$profit_real = $profit_real + $valCon['profit_real'];
						}
					}
					$time[$keyTime]['gross_revenue_real'] = $gross_revenue_real  ;   
					$time[$keyTime]['profit_real'] = $profit_real ;   
					unset($valTime['date']) ;	
				}
			}
			// ghép ngày trong kì với doanh thu lợi nhuận

			return $this->response(json_encode(array('code' => '200', 'result' => true , 'message' => 'Lấy dũ liệu thành công', 'data' => $time)), 200);

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}
}
