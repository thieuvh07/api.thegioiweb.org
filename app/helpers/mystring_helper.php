<?php
function pre($data){
	$CI =& get_instance();
	$CI->load->library(array('Check'));
	return $CI->check->pre($data);
}
function panigation($data = []){
	$CI =& get_instance();

	$data['limit'] = $data['limit'] ?? 30;
	$data['start'] = $data['start'] ?? 0;
	$data['base_url'] = $data['base_url'] ?? 'user/backend/auth/login';

	$config['suffix'] = $CI->config->item('url_suffix').(!empty($_SERVER['QUERY_STRING'])?('?'.$_SERVER['QUERY_STRING']):'');
	$config['base_url'] = $data['base_url'];
	$config['first_url'] = $config['base_url'].$config['suffix'];
	$config['per_page'] = $data['limit'];
	$config['cur_page'] = (!empty($data['limit'])) ? (1 + $data['start']/$data['limit']) : 1;
	$config['uri_segment'] = 5;
	$config['use_page_numbers'] = TRUE;
	$config['full_tag_open'] = '<ul class="pagination no-margin">';
	$config['full_tag_close'] = '</ul>';
	$config['first_tag_open'] = '<li>';
	$config['first_tag_close'] = '</li>';
	$config['last_tag_open'] = '<li>';
	$config['last_tag_close'] = '</li>';
	$config['cur_tag_open'] = '<li class="active"><a class="btn-primary">';
	$config['cur_tag_close'] = '</a></li>';
	$config['next_tag_open'] = '<li>';
	$config['next_tag_close'] = '</li>';
	$config['prev_tag_open'] = '<li>';
	$config['prev_tag_close'] = '</li>';
	$config['num_tag_open'] = '<li>';
	$config['num_tag_close'] = '</li>';
	return $config;
}
// ------------------------------------XỬ LÍ DỮ LIỆU API------------------------------------
/**
 * 
 * @param tên bảng, dữ liệu được gửi lên,  dữ liệu mở rộng, như quy định search theo field nào
 * @return json: query( điều kiện để search) order by ( xắp xếp dữ liệu trả về)
 */
if(!function_exists('render_query_in_search')){
	function render_search_in_query($module, $param, $paramExtend = [], $default = true){
		$CI =& get_instance();
		$data = [];
		$queryStr = $param['query'] ?? '';
		$queryArray = array_filter(explode(",",$queryStr));
		if(isset($queryArray) && check_array($queryArray)){
			foreach ($queryArray as $key => $val) {
				$temp = explode("=",$val);
				if(count($temp) < 2){continue;}
				$queryList[$temp[0]] = $temp[1].(isset($temp[2]) ? '='.$temp[2] : ''); 
			}
		}

		$query = '';
		if(isset($queryList) && check_array($queryList) ){
		    foreach ($queryList as $field => $val) {
		    	$temp = convert_field_val($field, $val);
		    	$field = $temp['field'];
		    	$operator = $temp['operator'];
		    	$val = $temp['val'];

		    	switch ($field){
					case 'perpage':
						break;
					case 'keyword':
						$keyword = $CI->db->escape_like_str($val);
						if(isset($paramExtend['fieldKeywordArray'])){
							$fieldKeywordArray = $paramExtend['fieldKeywordArray'];
						}else{
							$fieldKeywordArray = array('title');
						}
						if(isset($fieldKeywordArray) && check_array($fieldKeywordArray) ){
							$temp = '';
							foreach ($fieldKeywordArray as $keyKey => $valKey) {
								$temp = $temp.' OR '.$valKey.' LIKE \'%'.$keyword.'%\'';
							}
							$temp = substr( $temp, 4, strlen($temp));
							$query = $query.' AND ( '.$temp.' ) ';
						}
						break;

					case 'date_start':
						$date_start = $val;
						$date_end = $queryList['date_end'];
						if(!empty($date_start) && !empty($date_end)){
							$date_start = substr( $date_start, 6, 4).'-'.substr( $date_start, 3, 2).'-'.substr( $date_start, 0, 2).' 00:00:00';

							$date_end = substr( $date_end, 6, 4).'-'.substr( $date_end, 3, 2).'-'.substr( $date_end, 0, 2).' 23:59:59';
							$query = $query.' AND '.$module.'.created >= "'.$date_start.'" AND '.$module.'.created <= "'.$date_end.'"';
						}
						break;

					case 'date_end':
						break;

					case 'radio_time':
						$time = get_time_of();
						switch ($val){
							case 'time_day':
								$query = $query.' AND '.$module.'.created >= "'.$time['first_day_of_day'].'" AND '.$module.'.created <= "'.$time['last_day_of_day'].'"';
								break;
							case 'time_week':
								$query = $query.' AND '.$module.'.created >= "'.$time['first_day_of_week'].'" AND '.$module.'.created <= "'.$time['last_day_of_week'].'"';
								break;
							case 'time_month':
								$query = $query.' AND '.$module.'.created >= "'.$time['first_day_of_month'].'" AND '.$module.'.created <= "'.$time['last_day_of_month'].'"';
								break;
							default:
								break;
						}
						break;

					case 'order_by':
						if($val != -1){
							$val = str_replace('=', ' ', $val);
							$order_by1 = str_replace(',', ',' , $val);
							$val = $module.'.'.$val;
							$order_by = str_replace(',', ','.$module.'.', $val);
						}
						break;
					case 'catalogueid':
						$field = $module.'.'.$field;
						$query = $query.' AND '.$field.' = '.$val;
						break;
					case 'userid_created':
						$field = $module.'.'.$field;
						$query = $query.' AND '.$field.' = '.$val;
						break;
					case 'userid_updated':
						$field = $module.'.'.$field;
						$query = $query.' AND '.$field.' = '.$val;
						break;
					case 'userid_charge':
						$field = $module.'.'.$field;
						$query = $query.' AND '.$field.' = '.$val;
						break;

					default:
						if($default == true){
							$field = $module.'.'.$field;
							$query = $query.' AND '.$field.$operator.$val;
						}
						
				}
		    }
		}
		// $data['query'] = isset($query) ? substr($query, 4, strlen($query)) : '';
		$data['query'] = isset($query) ? $query : '';
		$data['queryList'] = $queryList ?? '';
		$data['order_by'] = $order_by ?? '';
		$data['order_by1'] = $order_by1 ?? '';
		return $data;
	}
}

/**
 * 
 * @param 
 * @return 
 */
if(!function_exists('convert_field_val')){
	function convert_field_val($field = '' , $val = ''){
		$matching = array(
			'[in]' => ' like ',
		);

		if(strpos($field, '[')){
	    	$index = strpos($field, '[');
			$operator = substr($field, $index, strlen($field));
	    	$field = substr($field, 0, $index);
	    	if(isset($matching[$operator])){
	    		if($operator == '[in]'){
				    $val =  '\'%'.$val.'%\'';
				}
	    		$operator = $matching[$operator];
	    	}
    	}
    	$operator = $operator ?? '=';

    	return array('field' => $field, 'operator' => $operator, 'val' => $val, 'full' => $field.' '.$operator.' \'%'.$val.'%\' ');

	}
}

//  định dạng văn bản lưu log lỗi
if(!function_exists('log_data')){
	function log_data($param, $method){
        // open the log file for appending
        if ($fp = fopen('log_data.txt','a')) {
  			// construct the log message
            $log_msg = date("[Y-m-d H:i:s]") .PHP_EOL.
                'Method: '.$method.PHP_EOL.
                'Json: '.json_encode($param).PHP_EOL.PHP_EOL;
            fwrite($fp, $log_msg);
            fclose($fp);
        }
    }
}
//  jwt
if(!function_exists('create_jwt')){
	function create_jwt($param){
		$secret = $param['secret'] ?? '';
		$payload = $param['payload'] ?? '';
		if(!empty($secret) && !empty($payload)){
			// Create token header as a JSON string
			$header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
			// Create token payload as a JSON string
			$payload = json_encode($payload);

			// Encode Header to Base64Url String
			$base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
			// Encode Payload to Base64Url String
			$base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

			$signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret , true);
			// Encode Signature to Base64Url String
			$base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

			// Create JWT
			$jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
			return $jwt;
		}else{
			return false;
		}
	}
}




// ---------------------------------------XỬ LÍ BIẾN---------------------------------------


//trả về: như hàm number_format
//đầu vào: $data
if(!function_exists('addCommas')){
	function addCommas($number = ''): string{
		$number = $number ?? 0;
		if(!empty($number)){
			return number_format($number,'0',',','.');
		}
		return 0;
	}
}

// chuyển định dạng obj sang array
if(!function_exists('convert_obj_to_array')){
	function convert_obj_to_array($obj, &$arr = '') {
		$arr = [];
	    if(!is_object($obj) && !is_array($obj)){
	        $arr = $obj;
	        return $arr;
	    }
	    foreach ($obj as $key => $val){
	        if (!empty($val)){

	            $arr[$key] = array();
	            convert_obj_to_array($val, $arr[$key]);
	        }else{
	            $arr[$key] = $val;
	        }
	    }
	    return $arr;
	}
}


//  mã hóa mật khẩu
if(!function_exists('password_encode')){
	function password_encode($password = '', $salt = ''){
		if(PRE_PASS == true){
			pre(md5(md5(md5($password).$salt)));
		};
		return md5(md5(md5($password).$salt));
	}
}

//  kiểm tra định dạng 
if(!function_exists('check_array')){
	function check_array($param = ''): bool{
		if(isset($param) && is_array($param) && count($param)){
			return true;
		}else{
			return false;
		}
	}
}

// laoij bỏ tiêng việt
if(!function_exists('removeutf8')){
	function removeutf8($value = NULL){
		$chars = array(
			'a'	=>	array('ấ','ầ','ẩ','ẫ','ậ','Ấ','Ầ','Ẩ','Ẫ','Ậ','ắ','ằ','ẳ','ẵ','ặ','Ắ','Ằ','Ẳ','Ẵ','Ặ','á','à','ả','ã','ạ','â','ă','Á','À','Ả','Ã','Ạ','Â','Ă'),
			'e' =>	array('ế','ề','ể','ễ','ệ','Ế','Ề','Ể','Ễ','Ệ','é','è','ẻ','ẽ','ẹ','ê','É','È','Ẻ','Ẽ','Ẹ','Ê'),
			'i'	=>	array('í','ì','ỉ','ĩ','ị','Í','Ì','Ỉ','Ĩ','Ị'),
			'o'	=>	array('ố','ồ','ổ','ỗ','ộ','Ố','Ồ','Ổ','Ô','Ộ','ớ','ờ','ở','ỡ','ợ','Ớ','Ờ','Ở','Ỡ','Ợ','ó','ò','ỏ','õ','ọ','ô','ơ','Ó','Ò','Ỏ','Õ','Ọ','Ô','Ơ'),
			'u'	=>	array('ứ','ừ','ử','ữ','ự','Ứ','Ừ','Ử','Ữ','Ự','ú','ù','ủ','ũ','ụ','ư','Ú','Ù','Ủ','Ũ','Ụ','Ư'),
			'y'	=>	array('ý','ỳ','ỷ','ỹ','ỵ','Ý','Ỳ','Ỷ','Ỹ','Ỵ'),
			'd'	=>	array('đ','Đ'),
		);
		foreach ($chars as $key => $arr)
			foreach ($arr as $val)
				$value = str_replace($val, $key, $value);
		return $value;
	}
}

// chyển kiểu string về slug
if(!function_exists('slug')){
	function slug($value = NULL){
		$value = removeutf8($value);
		$value = str_replace('-', ' ', trim($value));
		$value = preg_replace('/[^a-z0-9-]+/i', ' ', $value);
		$value = trim(preg_replace('/\s\s+/', ' ', $value));
		return strtolower(str_replace(' ', '-', trim($value)));
	}
}

if(!function_exists('random')){
	function random($leng = 168, $char = FALSE){
		if($char == FALSE) $s = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
		else $s = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		mt_srand((double)microtime() * 1000000);
		$salt = '';
		for ($i=0; $i<$leng; $i++){
			$salt = $salt . substr($s, (mt_rand()%(strlen($s))), 1);
		}
		return $salt;
	}
}



// ---------------------------------------XỬ THỜI GIAN---------------------------------------


// cộng trừ thời gian ngày tháng
if(!function_exists('operator_time')){
	function operator_time($time, $val = 0, $type = 'd', $return = 'd/m/Y'){
		$time = (isset($time)) ? $time : $this->currentTime;
		$data['H'] = 0;
		$data['i'] = 0;
		$data['s'] = 0;
		$data['d'] = 0;
		$data['m'] = 0;
		$data['Y'] = 0;
		$data[$type] = $val;
		$dateint = mktime(gettime($time, 'H') - $data['H'], gettime($time, 'i') - $data['i'], gettime($time, 's') - $data['s'], gettime($time, 'm') - $data['m'], gettime($time, 'd') - $data['d'], gettime($time, 'Y') - $data['Y']);
		return date($return, $dateint); // 02/12/2016
	}
}

//  lấy cách môc thời gian như : đầu tuần, cuối tuần, đầu tháng, cuối tháng
if(!function_exists('get_time_of')){
	function get_time_of($date = ''){
		$date = new DateTime('now');

		$date->modify('first day of this month');
		$param['first_day_of_month'] = $date->format('Y-m-d').' 00:00:00';

		$date->modify('last day of this month');
		$param['last_day_of_month'] = $date->format('Y-m-d').' 23:59:59';


		$day = date('w');
		$param['first_day_of_week'] = date('Y-m-d', strtotime('-'.$day.' days')).' 00:00:00';
		$param['last_day_of_week'] = date('Y-m-d', strtotime('+'.(6-$day).' days')).' 23:59:59';

		$day = date('Y-m-d', time());
		$param['first_day_of_day'] = $day.' 00:00:00';
		$param['last_day_of_day'] = $day.' 23:59:59';
	    return $param;
	}
}

//  lấy kiểu thời gian theo yêu cầu
if(!function_exists('gettime')){
	function gettime($time, $type = 'H:i - d/m/Y'){
		if($time == '0000-00-00 00:00:00'){
			return false;
		}
		if($type == 'micro'){
			return strtotime($time)*1000;
		}
		return gmdate($type, strtotime($time) + 7*3600);
	}
}

// éo kiểu thười gian
if(!function_exists('convert_time')){
	function convert_time($time = '', $type = '-'){
		if($time == ''){
			return '0000-00-00 00:00:00';
		};
		$time = str_replace( '/', '-', $time );
		$current = explode('-', $time);
		$time_stamp = $current[2].'-'.$current[1].'-'.$current[0].' 00:00:00';
		return $time_stamp;
	}
}

//  convert định dạng đường dẫn ảnh
if(!function_exists('getthumb')){
	function getthumb($image = '' , $thumb = TRUE){
		$image = !empty($image) ? $image :  IMG_NOT_FOUND;

		return $image;
		if(!file_exists(dirname(dirname(dirname(__FILE__))).$image) ){
			$image = IMG_NOT_FOUND;
		}
		if($thumb == TRUE){
			$image_thumb = str_replace(SRC_IMG, SRC_THUMB, $image);
			if (file_exists(dirname(dirname(dirname(__FILE__))).$image_thumb)){
				return $image_thumb;
			}
		}
		return $image;
	}
}
if(!function_exists('get_colum_in_array')){
	function get_colum_in_array($data=array(), $field= 'id' ){
	    if(empty($field) || empty($data) ){
	        return false ;
	    }
	    if(isset($data) && is_array($data) && count($data)){
	    	foreach ($data as $key => $val) {
	    		if(isset($val[$field])){
		    		$result[] = $val[$field];
	    		}
	    	}
	    }
	    return (isset($result)) ? $result : '' ;
	}
}

function compareByTimeStamp($time1, $time2) 
{ 
    if (strtotime($time1) < strtotime($time2)) 
        return 1; 
    else if (strtotime($time1) > strtotime($time2))  
        return -1; 
    else
        return 0; 
} 