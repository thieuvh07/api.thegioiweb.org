<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class ConfigBie{

	function __construct($params = NULL){
		$this->params = $params;
	}

	public function data($field = 'process', $value = -1){
		$data['type_business'] = array(
			0 =>'VP',
			1 => 'KDCT',
			2 => 'KDVP',
		);
		$data['KDCT'] = array(
			'0' =>'10',
			'50' => '12',
			'100' => '14',
			'150' => '16',
		);
		$data['measure'] = array(
			0 =>'Cuốn',
			1 => 'Tấm',
			2 => 'Mét dài',
		);
		$data['order_by_user'] = array(
			-1 => '- Sắp xếp theo -',
			'fullname=ASC' => 'Tiêu đề (A-Z)',
			'fullname=DESC' => 'Tiêu đề (Z-A)',
			'created=DESC' => 'Ngày tạo (Mới nhất)',
			'created=ASC' => 'Ngày tạo (Cũ nhất)',
			'updated=DESC'=> 'Ngày cập nhật (Mới dần)',
			'updated=ASC' => 'Ngày cập nhật (Cũ dần)',
		);
		$data['perpage'] = array(
			30 => '30 bản ghi',
			40 => '40 bản ghi',
			50 => '50 bản ghi',
			60 => '60 bản ghi',
			70 => '70 bản ghi',
			80 => '80 bản ghi',
			90 => '90 bản ghi',
			100 => '100 bản ghi',
		);
		if($value == -1){
			return $data[$field];
		}
		else{
			return $data[$field][$value];
		}
	}
}
