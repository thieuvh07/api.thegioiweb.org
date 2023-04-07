<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Salary_get_data extends MY_Controller {
	function __construct($params = NULL){
		$this->params = $params;
		$this->load->library('salary_combie');
	}
	public function worker($periodic = '', $id = 1){
		// lấy danh sách thợ, lương ứng, thưởng, phạt
		$user = $this->autoload_model->_get_where(array(
			'table' => 'user as tb1',
			'select' => 'tb1.id, tb1.fullname, tb5.bonus, tb5.fine, tb5.salary,
				SUM(tb4.output - tb4.input ) as ung_luong,
				(SELECT SUM(status) FROM salary_timekeeping WHERE salary_timekeeping.userid = tb1.id AND salary_timekeeping.periodicid = '.$periodic['id'].') as timekeeping ,
			',
			'join' => array(
				array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.id AND tb2.trash = 0', 'left'),
				array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				array('cash as tb4' , '(tb4.userid = tb1.id) AND tb4.trash = 0 AND (tb4.time <= "'.$periodic['date_end'].'") AND (tb4.time >= "'.$periodic['date_start'].'")', 'left'),
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

	public function office($periodic = '', $id = false){
		// lấy danh sách thợ, lương ứng, thưởng, phạt
		$user = $this->autoload_model->_get_where(array(
			'table' => 'user as tb1',
			'select' => 'tb1.id, tb1.fullname, SUM(tb4.output - tb4.input ) as ung_luong, tb5.bonus, tb5.fine, tb5.salary,
			',
			'join' => array(
				array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.id AND tb2.trash = 0', 'left'),
				array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				array('cash as tb4' , '(tb4.userid = tb1.id) AND tb4.trash = 0 AND (tb4.time <= "'.$periodic['date_end'].'") AND (tb4.time >= "'.$periodic['date_start'].'")', 'left'),
				array('salary as tb5' , 'tb5.userid = tb1.id AND tb5.trash = 0 AND tb5.periodicid='.$periodic['id'], 'left'),
			),
			'group_by' => 'tb1.id',
			'query' => 'tb1.trash = 0 AND (tb3.slug="ke-toan-van-phong" OR tb3.slug="kinh-doanh" OR tb3.slug="ke-toan-kho")',
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
		return $constructionVP;

		// lấy lợi nhuận KDCT trong kì
		$constructionCT = $this->autoload_model->_get_where(array(
			'table' => 'construction as tb1',
			'select' => 'tb1.gross_revenue_real, tb1.profit_real, tb1.userid_charge as userid,
				SUM(tb2.thucdan*tb2.price_output + (tb2.trenphieu - tb2.thucdan) * (tb2.price_output - tb2.price_input) ) as gross_revenue , 
				SUM(tb2.thucdan*tb2.price_output + (tb2.trenphieu - tb2.thucdan) * (tb2.price_output - tb2.price_input) - tb2.thucdan*tb2.price_input) as profit , 
				',
			'group_by' => 'tb1.userid_charge',
			'join' => array(
				array('construction_relationship as tb2' , 'tb1.id = tb2.constructionid  AND tb2.trash = 0', 'left'),
				array('type_business as tb5' , 'tb5.id = tb1.type_business  AND tb5.trash = 0', 'left'),
			),
			'query' => 'tb5.title = "KDCT" AND tb1.date_start <= "'.$periodic['date_end'].'" AND tb1.date_start >= "'.$periodic['date_start'].'" ',
		), true);
		if(isset($constructionCT) && check_array($constructionCT)){
			foreach ($constructionCT as $key => $val) {
				$constructionCT[$key]['gross_revenue_real'] =!empty($val['gross_revenue_real']) ? $val['gross_revenue_real'] :  $val['gross_revenue'];
				$constructionCT[$key]['profit_real'] =!empty($val['profit_real']) ? $val['profit_real'] :  $val['profit'];
			}
		}
		return $constructionCT;

		// kinh doanh vs kế toán: lương cứng 6% KDVP + x% * KDCT - Ửng + Thưởng - Phạt
		if(isset($user) && check_array($user) ){
			foreach ($user as $keyUser => $valUser) {
				$profitCT = 0;
				if(isset($constructionCT) && check_array($constructionCT) ){
					foreach ($constructionCT as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$profitCT = $profitCT + $val['profit_real'];
						}
					}
				}
				$valUser['profitCT'] = $profitCT;
				$profitVP = 0;
				if(isset($constructionVP) && check_array($constructionVP) ){
					foreach ($constructionVP as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$profitVP = $profitVP+ $val['profit_real'];
						}
					}
				}
				$valUser['profitVP'] = $profitVP;


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


	public function worker_outside($periodic = ''){
		// lấy danh sách thợ, lương ứng, thưởng, phạt
		$user = $this->autoload_model->_get_where(array(
			'table' => 'user as tb1',
			'select' => 'tb1.id, tb1.fullname, SUM(tb4.output - tb4.input ) as ung_luong, tb5.bonus, tb5.fine, tb5.salary,
				(SELECT SUM(status) FROM salary_timekeeping WHERE salary_timekeeping.userid = tb1.id AND salary_timekeeping.periodicid = '.$periodic['id'].') as timekeeping ,

			',
			'join' => array(
				array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.id AND tb2.trash = 0', 'left'),
				array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				array('cash as tb4' , '(tb4.userid = tb1.id) AND tb4.trash = 0 AND (tb4.time <= "'.$periodic['date_end'].'") AND (tb4.time >= "'.$periodic['date_start'].'")', 'left'),
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


	public function design($periodic = ''){

		// lấy danh sách thợ, lương ứng, thưởng, phạt
		$user = $this->autoload_model->_get_where(array(
			'table' => 'user as tb1',
			'select' => 'tb1.id, tb1.fullname, SUM(tb4.output - tb4.input ) as ung_luong, tb5.bonus, tb5.fine, tb5.salary,
			',
			'join' => array(
				array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.id AND tb2.trash = 0', 'left'),
				array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				array('cash as tb4' , '(tb4.userid = tb1.id) AND tb4.trash = 0 AND (tb4.time <= "'.$periodic['date_end'].'") AND (tb4.time >= "'.$periodic['date_start'].'")', 'left'),
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

				$profitVP = 0;
				if(isset($constructionVP) && check_array($constructionVP) ){
					foreach ($constructionVP as $key => $val) {
						if($val['userid'] == $valUser['id']){
							$profitVP = $profitVP + $val['profit_real'];
						}
					}
				}
				$valUser['profitVP'] = $profitVP;
				
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
