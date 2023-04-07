<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Common {
	

	function last_id($module = ''){
		$CI =& get_instance();
		$moduleLast = $CI->autoload_model->_get_where(array(
			'table'=> $module,
			'select'=>'id',
			'order_by'=>'id DESC',
		),false);
		if(!isset($moduleLast) || !check_array($moduleLast) ){
		    return 0;
		}else{
			return $moduleLast['id'];
		}

	}


}
