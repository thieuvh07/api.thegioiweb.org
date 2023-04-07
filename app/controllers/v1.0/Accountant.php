<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Accountant extends REST_Controller {

	function __construct() {
		parent::__construct();
	}

	public function worker_put(){
		try {
			// get data
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}
			$data = $this->rest_api->api_input('put');

			if(isset($data) && check_array($data) ){
				foreach ($data as $key => $val) {
					$_update = array(
						'money' => (int)str_replace('.','',$val['money']),
					);
					if(isset($_update) && check_array($_update) ){
						$this->autoload_model->_update(array('table' => 'accountant', 'data' => $_update, 'where' => array('constructionid' => $val['constructionid'], 'userid' => $val['userid'], )));
					}	
				}
			}
			
			// processing
			// return pre($response);
			return $this->response(json_encode(array('code' => '201', 'result' => true, 'message' => 'Cập nhật dữ liệu thành công')), 201);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
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
			$data = $this->rest_api->api_input('put');

			
			$_update = array(
				'sales_real' => (int)str_replace('.','',$data['sales_real'] ?? 0),
				'gross_revenue_real' => (int)str_replace('.','',$data['gross_revenue_real'] ?? 0),
				'profit_real' => (int)str_replace('.','',$data['profit_real'] ?? 0),
			);	
			// processing
			$response = $this->crud->update(array('table' => 'construction', 'data' => $_update, 'where' => array('id' => $id)));
			// return pre($response);
			return $this->response(json_encode($response), $response['code']);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}

	public function search_get(){
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
			$queryData = render_search_in_query('tb1', $this->input->get(), array('fieldKeywordArray' => array()), false);
			$queryList = $queryData['queryList'];
			$query = '';

			$keyword = $queryList['keyword'] ?? '';
			$fieldKeywordArray = array('tb1.fullname', 'tb1.phone');
			if(isset($fieldKeywordArray) && check_array($fieldKeywordArray) ){
				$temp = '';
				foreach ($fieldKeywordArray as $keyKey => $valKey) {
					$temp = $temp.' OR '.$valKey.' LIKE \'%'.$keyword.'%\'';
				}
				$temp = substr( $temp, 4, strlen($temp));
				$query = $query.' AND ( '.$temp.' ) ';
			}

			$date_start = $queryList['date_start'] ?? '';
			$date_end = $queryList['date_end'] ?? '';
			if(!empty($date_start) && !empty($date_end)){
				$date_start = substr( $date_start, 6, 4).'-'.substr( $date_start, 3, 2).'-'.substr( $date_start, 0, 2).' 00:00:00';

				$date_end = substr( $date_end, 6, 4).'-'.substr( $date_end, 3, 2).'-'.substr( $date_end, 0, 2).' 23:59:59';
				$query = $query.' AND tb1.date_start >= "'.$date_start.'" AND tb1.date_start <= "'.$date_end.'"';
			}

			if(isset($queryList['construction_catalogue'])){
				$query = $query.' AND '.'tb1'.'.catalogueid = '.$queryList['construction_catalogue'];
			}
			if(isset($queryList['type_business'])){
				$query = $query.' AND '.'tb1'.'.type_business = '.$queryList['type_business'];
			}
			if(isset($queryList['userid_charge'])){
				$query = $query.' AND '.'tb1'.'.userid_charge = '.$queryList['userid_charge'];
			}
			
			if(isset($queryList['trash'])){
				$query = $query.' AND '.'tb1'.'.trash = '.$queryList['trash'];
			}else{
				$query = $query.' AND '.'tb1'.'.trash = 0';
			}

			// lấy công trình trong kì
			$construction = $this->autoload_model->_get_where(array(
				'table' => 'construction as tb1',
				'select' => 'tb1.id, tb1.fullname, tb1.phone, tb1.sales_real, tb1.gross_revenue_real, tb1.profit_real, tb1.data_json,
					(SELECT SUM(money) FROM accountant WHERE accountant.constructionid=tb1.id AND accountant.trash = 0) as total_money_worker_detail,
					(SELECT SUM(input - output) FROM cash WHERE cash.constructionid=tb1.id AND cash.trash = 0) as sales_cash,
					(SELECT title FROM type_business WHERE type_business.id=tb1.type_business AND type_business.trash = 0) as type_business,
					(SELECT fullname FROM user WHERE user.id=tb1.userid_charge AND user.trash = 0) as user_charge,

					(SELECT SUM( (thucdan*price_output) + ((trenphieu - thucdan) * (price_output - price_input)) )  FROM construction_relationship WHERE construction_relationship.constructionid=tb1.id AND construction_relationship.trash = 0) as gross_revenue,

					(SELECT SUM(thucdan)  FROM construction_relationship WHERE construction_relationship.constructionid=tb1.id AND construction_relationship.trash = 0) as thucdan_all,
					(SELECT SUM(thucdan*price_output + (trenphieu - thucdan) * (price_output - price_input) - thucdan*price_input)  FROM construction_relationship WHERE construction_relationship.constructionid=tb1.id AND construction_relationship.trash = 0) as profit,
					',
				'query' => 'tb1.status = 0 AND tb1.trash = 0 AND tb1.date_start <= "'.$periodic['date_end'].'" AND tb1.date_start >= "'.$periodic['date_start'].'" '.$query,
			), true);
			$list_id = get_colum_in_array($construction, 'id');
			
			if(isset($construction) && check_array($construction)){
				foreach ($construction as $key => $val) {

					$gross_revenue = !empty($val['gross_revenue_real']) ? $val['gross_revenue_real'] :  $val['gross_revenue'];
					$sales = !empty($val['sales_real']) ? $val['sales_real'] :  0;
					$construction[$key]['total_money_worker'] = $sales - $gross_revenue ;

					$construction[$key]['gross_revenue_real'] =!empty($val['gross_revenue_real']) ? $val['gross_revenue_real'] :  $val['gross_revenue'];
					$construction[$key]['profit_real'] =!empty($val['profit_real']) ? $val['profit_real'] :  $val['profit'];

					$detail = ''; 
					$construction_relationship = json_decode(base64_decode($val['data_json']), true);
					if(isset($construction_relationship) && check_array($construction_relationship)){
						foreach ($construction_relationship as $sub => $subs) {
							$detail = $detail.($subs['quantity']).' '.$subs['title'].'('.($subs['trenphieu'] ?? 0).'),<br>';
						}
					}
					$construction[$key]['detail'] = $detail;
				}
			}

			
			// processing
			$count = $this->autoload_model->_get_where(array(
				'table' => 'construction as tb1',
				'select' => 'tb1.id',
				'query' => 'tb1.trash = 0 AND tb1.date_start <= "'.$periodic['date_end'].'" AND tb1.date_start >= "'.$periodic['date_start'].'"',
				'count' => true,
			), true);
			$data = array(
				'list' => $construction,
				'form' => 1,
				'to' => $count,
				'total_rows' => $count,
			);
			return $this->response(json_encode(array('code' => '200', 'result' => true , 'message' => 'Lấy dũ liệ thành công', 'data' => $data)), 200);

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}

	public function worker_get($constructionid){
		try {
			$constructionid = (int) $constructionid;

			// lấy công trình trong kì
			$accountant = $this->autoload_model->_get_where(array(
				'table' => 'accountant as tb1',
				'select' => 'tb1.money, tb2.fullname, tb1.userid',
				'join' => array(
					array('user as tb2' , 'tb2.id = tb1.userid AND tb2.trash = 0', 'left'),
				),
				'query' => 'tb1.trash = 0 AND tb1.constructionid = '.$constructionid,
			), true);

			//  kiểm tra xem đã có thiếu bản ghi  thợ mới nòa không
			// lấy danh sách thợ
			$user = $this->autoload_model->_get_where(array(
				'table' => 'user as tb1',
				'select' => 'tb1.id',
				'join' => array(
					array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.id AND tb2.trash = 0', 'left'),
					array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				),
				'group_by' => 'tb1.id',	
				'query' => ' tb1.trash = 0 AND (tb3.slug="tho" OR  tb3.slug="tho-ngoai")',
			), true);
			if(isset($user) && check_array($user)){
				foreach ($user as $key => $val) {
					if(isset($accountant) && check_array($accountant)){
						foreach ($accountant as $sub => $subs) {
							if($val['id'] == $subs['userid']){
								unset($user[$key]);
							}
						}
					}
				}
			}
			// Tiến hành thêm thợ mới chưa có trong bảng account
			if(isset($user) && check_array($user)){
				foreach ($user as $key => $val) {
					$this->autoload_model->_create(array(
						'table' => 'accountant',
						'data' => array(
							'userid' => $val['id'],
							'constructionid' => $constructionid,
						),
					));
				}
			}

			// lấy công trình trong kì
			$accountant = $this->autoload_model->_get_where(array(
				'table' => 'accountant as tb1',
				'select' => 'tb1.money, tb2.fullname, tb1.userid',
				'join' => array(
					array('user as tb2' , 'tb2.id = tb1.userid AND tb2.trash = 0', 'left'),
				),
				'query' => 'tb1.trash = 0 AND tb1.constructionid = '.$constructionid,
			), true);

			$data['money'] = get_colum_in_array($accountant, 'money');
			$data['userid'] = get_colum_in_array($accountant, 'userid');
			$data['fullname'] = get_colum_in_array($accountant, 'fullname');
			
			// processing
			return $this->response(json_encode(array('code' => '200', 'result' => true , 'message' => 'Lấy dũ liệ thành công', 'data' => array('list' => $data))), 200);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}
}
