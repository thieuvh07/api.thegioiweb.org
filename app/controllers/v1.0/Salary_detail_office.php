<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Salary_detail_office extends REST_Controller {

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

			$queryData = render_search_in_query('salary' , $this->input->get(), array('fieldKeywordArray' => ''), false);
			$queryList = $queryData['queryList'];
			$id = $queryList['id'];
			if($id <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);


			// lấy thời gian trong kì
			$periodic = $this->autoload_model->_get_where(array(
				'table' => 'periodic',
				'select' => 'id, date_start, date_end',
				'query' => 'trash = 0 AND id = '.$periodicid,
			));
			// lấy lương của kinh doanh
			$data['office'] = $this->office($periodic, $id);

			// lấy ra danh sách công trình mà kinh doanh đó phụ trách

			// lấy lợi nhuận KDVP trong kì
			$construction = $this->autoload_model->_get_where(array(
				'table' => 'construction as tb1',
				'select' => 'tb1.gross_revenue_real, tb1.profit_real, tb1.userid_charge as userid, tb5.title as type_business, tb1.sales_real, tb1.note, tb1.data_json, tb1.date_start, tb1.fullname, tb1.phone,
					SUM(tb2.thucdan*tb2.price_output + (tb2.trenphieu - tb2.thucdan) * (tb2.price_output - tb2.price_input) ) as gross_revenue , 
					SUM(tb2.thucdan*tb2.price_output + (tb2.trenphieu - tb2.thucdan) * (tb2.price_output - tb2.price_input) - tb2.thucdan*tb2.price_input) as profit , 
					',
				'group_by' => 'tb2.constructionid',
				'join' => array(
					array('construction_relationship as tb2' , 'tb1.id = tb2.constructionid AND tb2.trash = 0', 'left'),
					array('type_business as tb5' , 'tb5.id = tb1.type_business AND tb5.trash = 0', 'left'),
				),
				'query' => 'tb1.trash = 0 AND tb1.userid_charge= "'.$id.'" AND tb1.date_start <= "'.$periodic['date_end'].'" AND tb1.date_start >= "'.$periodic['date_start'].'" ',
			), true);
			if(isset($construction) && check_array($construction)){
				foreach ($construction as $key => $val) {
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
			$data['construction'] = $construction;
			//  bảng ứng lương trong kì
			$cash = $this->autoload_model->_get_where(array(
				'table' => 'cash as tb1',
				'select' => '(SELECT fullname FROM user WHERE user.id = tb1.userid) as fullname, tb1.output ,tb1.input, tb1.title, tb1.time, tb1.note',
				'join' => array(
					array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.userid AND tb2.trash = 0', 'left'),
					array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				),
				'query' => 'tb1.trash = 0 AND tb1.userid = '.$id.' AND (tb3.slug="ke-toan-van-phong" OR tb3.slug="kinh-doanh") AND tb1.catalogueid=2 AND tb1.trash = 0 AND tb1.time <= "'.$periodic['date_end'].'" AND tb1.time >= "'.$periodic['date_start'].'"',
			), true);


			$data['cash'] = $cash;

			// processing
			return $this->response(json_encode(array('code' => '200', 'result' => true , 'message' => 'Lấy dũ liệ thành công', 'data' => array('list' => $data))), 200);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}
	public function office($periodic = '', $id = ''){
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
			'query' => 'tb1.trash = 0 AND tb1.id='.$id.' AND (tb3.slug="ke-toan-van-phong" OR tb3.slug="kinh-doanh" OR tb3.slug="ke-toan-kho")',
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
			'query' => 'tb1.trash = 0  AND tb5.title = "KDVP" AND tb1.date_start <= "'.$periodic['date_end'].'" AND tb1.date_start >= "'.$periodic['date_start'].'" ',
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



}	
