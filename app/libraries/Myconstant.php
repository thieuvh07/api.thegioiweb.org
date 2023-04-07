<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Myconstant{
	
	function __construct($params = NULL){
		$this->CI =& get_instance();
	}
	
	public function list_data($field = ''){
		$data = array(
			'module' => array(
				0 => 'Chọn Module',
				'article' => 'Bài viết',
				'product' => 'Sản phẩm',
				'media' => 'Thư viện',	
			),
			
			'perpage' => array(
				10 => 'Hiển thị 10 bản ghi',
				20 => 'Hiển thị 20 bản ghi',
				30 => 'Hiển thị 30 bản ghi',
				40 => 'Hiển thị 40 bản ghi',
				50 => 'Hiển thị 50 bản ghi',
				60 => 'Hiển thị 60 bản ghi',
				70 => 'Hiển thị 70 bản ghi',
				80 => 'Hiển thị 80 bản ghi',
				90 => 'Hiển thị 90 bản ghi',
				100 => 'Hiển thị 100 bản ghi',
			),
		);
		return $data[$field];
	}
	
	public function get_data($field = ''){
		$data = array(
			'article' => 'Bài viết',
			'article_catalogue' => 'Danh mục bài viết',
			'product' => 'Sản phẩm',
			'product_catalogue' => 'Danh mục sản phẩm',
			'media' => 'Thư viện',
		);
		
		return $data[$field];
	}
}
