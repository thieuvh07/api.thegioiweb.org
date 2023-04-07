<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Salary_combie{

	function __construct($params = NULL){
		$this->params = $params;
	}
	public function office($param){
		// // thiết lập giá trị thay đổi
		// // phần trăm lợi nhuận từ KDCT
		// $arrayPercentCT = array(
		// 	'0' => 0.1,
		// 	'50000000' => 0.12,
		// 	'100000000' => 0.14,
		// 	'150000000' => 0.16,
		// );
		// // phần trăm lợi nhuận từ kDVP
		// $percentVP = 0.06;

		// lấy các thiết lập lương trong csdl
		$CI =& get_instance();
		$default = $CI->autoload_model->_get_where(array(
			'table' => 'general',
			'select' => 'content',
			'query' => 'keyword ="office_arrayPercentCT"'
		));
		$arrayPercentCT = explode(',', $default['content']);
		$temp = [];
		if(isset($arrayPercentCT) && is_array($arrayPercentCT)){
			$index = 1;
			foreach ($arrayPercentCT as $key => $value) {
				$index ++;
				if($index%2 == 0){
					$temp[$value] = $arrayPercentCT[$key+1];
				}
			}
		}
		$arrayPercentCT = $temp ?? [];

		$default = $CI->autoload_model->_get_where(array(
			'table' => 'general',
			'select' => 'content',
			'query' => 'keyword ="office_percentVP"'
		));
		$percentVP = $default['content'] ?? 0;


		// kinh doanh vs kế toán: lương cứng 6% KDVP + x% * KDCT - Ửng + Thưởng - Phạt
		$param['salary'] = $param['salary'] ?? 0;
		$param['ung_luong'] = $param['ung_luong'] ?? 0;
		$param['bonus'] = $param['bonus'] ?? 0;
		$param['fine'] = $param['fine'] ?? 0;

		$param['profitVP'] = $param['profitVP'] ?? 0;
		$param['profitCT'] = $param['profitCT'] ?? 0;

		$percentCT = 0;
		foreach ($arrayPercentCT as $key => $value) {
			if($param['profitCT'] <= $key){
				$percentCT = $value;
				break;
			}
		}

		$totalSalary = $param['salary'] + $param['profitVP']*$percentVP + $param['profitCT']*$percentCT - $param['ung_luong'] + $param['bonus'] - $param['fine'];
		return array(
			'salaryCT' => $param['profitCT']*$percentCT,
			'salaryVP' => $param['profitVP']*$percentVP,
			'totalSalary' => $totalSalary,
			'percentVP' => $percentVP,
			'percentCT' => $percentCT,
		);
	}
	public function worker($param){
		// // thiết lập giá trị thay đổi
		// // phần trăm công thợ từ CT
		// $arrayPercentCT = array(
		// 	'0' => 0.8,
		// 	'30000000' => 0.81,
		// 	'35000000' => 0.82,
		// 	'40000000' => 0.83,
		// 	'45000000' => 0.84,
		// 	'50000000' => 0.85,
		// );
		// // phần trăm công thợ từ LG
		// $percentLG = 0.6;


		// lấy các thiết lập lương trong csdl
		$CI =& get_instance();
		$default = $CI->autoload_model->_get_where(array(
			'table' => 'general',
			'select' => 'content',
			'query' => 'keyword ="work_arrayPercentCT"'
		));
		$arrayPercentCT = explode(',', $default['content']);
		$temp = [];
		if(isset($arrayPercentCT) && is_array($arrayPercentCT)){
			$index = 1;
			foreach ($arrayPercentCT as $key => $value) {
				$index ++;
				if($index%2 == 0){
					$temp[$value] = $arrayPercentCT[$key+1];
				}
			}
		}
		$arrayPercentCT = $temp ?? 0;

		$default = $CI->autoload_model->_get_where(array(
			'table' => 'general',
			'select' => 'content',
			'query' => 'keyword ="work_percentLG"'
		));
		$percentLG = $default['content'] ?? 0;



		// thợ: tổng lương từ CT* x% + LG*100(60) - Ửng + Thưởng - Phạt
		$param['ung_luong'] = $param['ung_luong'] ?? 0;
		$param['bonus'] = $param['bonus'] ?? 0;
		$param['fine'] = $param['fine'] ?? 0;

		$param['totalWorkLG'] = $param['totalWorkLG'] ?? 0;
		$param['totalWorkCT'] = $param['totalWorkCT'] ?? 0;
		$percentCT = 0;
		foreach ($arrayPercentCT as $key => $value) {
			if($param['totalWorkCT'] <= $key){
				$percentCT = $value;
				break;
			}
		}

		$totalSalary = $param['totalWorkLG']*$percentLG + $param['totalWorkCT']*$percentCT - $param['ung_luong'] + $param['bonus'] - $param['fine'];
		return array(
			'salary' => $param['totalWorkLG']*$percentLG + $param['totalWorkCT']*$percentCT,
			'totalSalary' => $totalSalary,
			'totalWorker' => $param['totalWorkLG']*$percentLG + $param['totalWorkCT']*$percentCT,
			'percentLG' => $percentLG,
			'percentCT' => $percentCT,
		);
	}

	public function design($param){
		// // phần trăm lợi nhuận KDVP
		// $percentVP = 0.06;
		// // phần trăm công thợ CT
		// $percentCT = 0.8;
		// // phần trăm công thợ CT
		// $percentLG = 1;


		// lấy các thiết lập lương trong csdl
		$CI =& get_instance();
		$default = $CI->autoload_model->_get_where(array(
			'table' => 'general',
			'select' => 'content',
			'query' => 'keyword ="design_percentVP"'
		));
		$percentVP = $default['content'] ?? 0;

		$default = $CI->autoload_model->_get_where(array(
			'table' => 'general',
			'select' => 'content',
			'query' => 'keyword ="design_percentCT"'
		));
		$percentCT = $default['content'] ?? 0;

		$default = $CI->autoload_model->_get_where(array(
			'table' => 'general',
			'select' => 'content',
			'query' => 'keyword ="design_percentLG"'
		));
		$percentLG = $default['content'] ?? 1;



		// thiết kế: lương cứng lợi nhuận KDVP 6% của NV đó bán + 80% all thợ có hoạch tính CT + 100% công thợ LG - Ửng + Thưởng - Phạt
		$param['salary'] = $param['salary'] ?? 0;
		$param['ung_luong'] = $param['ung_luong'] ?? 0;
		$param['bonus'] = $param['bonus'] ?? 0;
		$param['fine'] = $param['fine'] ?? 0;

		$param['profitVP'] = $param['profitVP'] ?? 0;
		$param['totalWorkCT'] = $param['totalWorkCT'] ?? 0;
		$param['totalWorkLG'] = $param['totalWorkLG'] ?? 0;

		$totalSalary = $param['salary'] + $param['profitVP']*$percentVP + $param['totalWorkCT']*$percentCT + $param['totalWorkLG']*$percentLG - $param['ung_luong'] + $param['bonus'] - $param['fine'];
		return array(
			'salary' => $param['profitVP']*$percentVP + $param['totalWorkCT']*$percentCT + $param['totalWorkLG']*$percentLG,
			'totalSalary' => $totalSalary,
			'percentLG' => $percentLG,
			'percentCT' => $percentCT,
			'percentVP' => $percentVP,
		);
	}


	public function worker_outside($param){
		// // thiết lập giá trị thay đổi
		// // phần trăm công thợ từ CT
		// $percentCT = 1;
		// // phần trăm công thợ từ LG
		// $percentLG = 1;


		$CI =& get_instance();
		$default = $CI->autoload_model->_get_where(array(
			'table' => 'general',
			'select' => 'content',
			'query' => 'keyword ="woker_outside_percentCT"'
		));
		$percentCT = $default['content'] ?? 1;

		$default = $CI->autoload_model->_get_where(array(
			'table' => 'general',
			'select' => 'content',
			'query' => 'keyword ="woker_outside_percentLG"'
		));
		$percentLG = $default['content'] ?? 1;


		// thợ ngoài: all công thợ KTVP- Ửng + Thưởng - Phạt
		$param['ung_luong'] = $param['ung_luong'] ?? 0;
		$param['bonus'] = $param['bonus'] ?? 0;
		$param['fine'] = $param['fine'] ?? 0;

		$param['totalWorkLG'] = $param['totalWorkLG'] ?? 0;
		$param['totalWorkCT'] = $param['totalWorkCT'] ?? 0;


		$totalSalary = $param['totalWorkLG']*$percentLG + $param['totalWorkCT']*$percentCT - $param['ung_luong'] + $param['bonus'] - $param['fine'];
		return array(
			'salary' => $param['totalWorkLG']*$percentLG + $param['totalWorkCT']*$percentCT,
			'totalSalary' => $totalSalary,
			'percentLG' => $percentLG,
			'percentCT' => $percentCT,
		);
	}
}
