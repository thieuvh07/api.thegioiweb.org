<?php 
// giá trị truyền vào là mảng product ( YÊU CẦU PHẢI CÓ ID VS QUANTITY_OENING_STOCK)
function quantity_closing_stock($productList = [], $periodicid = ''){
	$CI =& get_instance();

	if(isset($productList) && check_array($productList)){
		if($periodicid == ''){
			$periodicid = $CI->common->last_id('periodic');
		}
		// lấy thời gian trong kì
		$periodic = $CI->autoload_model->_get_where(array(
			'table' => 'periodic',
			'select' => 'date_start, date_end',
			'query' => 'trash = 0 AND id = '.$periodicid,
		));

		if(isset($productList['id'])){
			// lấy ra danh sách số lượng nhập trong kì
			$import_relationship = $CI->autoload_model->_get_where(array(
				'table' =>'import_relationship as tb1',
				'select' =>'sum(tb1.quantity) as quantity, tb1.productid',
				'group_by' => 'tb1.productid',
				'join' => array(array(
					'import as tb2', 'tb2.id = tb1.importid', 'left'
				)),
				'query' => 'tb1.productid = '.$productList['id'].' AND tb1.trash = 0 AND tb2.trash = 0 AND  tb2.date_start <= "'.$periodic['date_end'].'" AND tb2.date_start >= "'.$periodic['date_start'].'" ',
			));
			// lấy ra danh sách số lượng trả hàng trong kì
			$repay_relationship = $CI->autoload_model->_get_where(array(
				'table' =>'repay_relationship as tb1',
				'select' =>'sum(tb1.quantity) as quantity, tb1.productid',
				'group_by' => 'tb1.productid',	
				'join' => array(array(
					'repay as tb2', 'tb2.id = tb1.repayid', 'left'
				)),
				'query' => 'tb1.productid = '.$productList['id'].' AND tb1.trash = 0 AND tb2.trash = 0 AND  tb2.date_start <= "'.$periodic['date_end'].'" AND tb2.date_start >= "'.$periodic['date_start'].'" ',
			));

			// lấy ra danh sách số lượng xuất
			$construction_relationship = $CI->autoload_model->_get_where(array(
				'table' =>'construction_relationship',
				'select' =>'sum(thucdan) as quantity, productid',
				'group_by' => 'productid',
				'join' => array(array(
					'construction', 'construction.id = construction_relationship.constructionid', 'left'
				)),
				'query' => 'productid = '.$productList['id'].' AND construction.trash = 0 AND construction_relationship.trash = 0 AND date_start <= "'.$periodic['date_end'].'" AND date_start >= "'.$periodic['date_start'].'" ',
			));

			// tiến hành cập nhật lại số lượng quantity_closing_stock
			$productList['quantity_closing_stock'] = ($productList['quantity_opening_stock'] ?? 0) + $import_relationship['quantity'] + $repay_relationship['quantity'] - $construction_relationship['quantity'];
			$productList['quantity_closing_stock']  = round($productList['quantity_closing_stock'] ,2);
			return $productList;
		}else{
			foreach ($productList as $keyPrd => $valPrd) {
				//kiểm tra điều kiện mảng phải có id vs quantity_opening_stock
				if(!isset($valPrd['id'])){
					return $productList;
				}
			}
			// lấy danh sách id sản phẩm 
			$idList = get_colum_in_array($productList, 'id');

			// lấy ra danh sách số lượng nhập trong kì
			$import_relationship = $CI->autoload_model->_get_where(array(
				'table' =>'import_relationship as tb1',
				'select' =>'sum(tb1.quantity) as quantity, tb1.productid',
				'group_by' => 'tb1.productid',
				'where_in' => $idList,
				'where_in_field' => 'tb1.productid',
				'join' => array(array(
					'import as tb2', 'tb2.id = tb1.importid', 'left'
				)),
				'query' => 'tb1.trash = 0 AND tb2.trash = 0 AND  tb2.date_start <= "'.$periodic['date_end'].'" AND tb2.date_start >= "'.$periodic['date_start'].'" ',
			), true);

			// lấy ra danh sách số lượng trả hàng trong kì
			$repay_relationship = $CI->autoload_model->_get_where(array(
				'table' =>'repay_relationship as tb1',
				'select' =>'sum(tb1.quantity) as quantity, tb1.productid',
				'group_by' => 'tb1.productid',
				'where_in' => $idList,
				'where_in_field' => 'tb1.productid',
				'join' => array(array(
					'repay as tb2', 'tb2.id = tb1.repayid', 'left'
				)),
				'query' => 'tb1.trash = 0 AND tb2.trash = 0 AND  tb2.date_start <= "'.$periodic['date_end'].'" AND tb2.date_start >= "'.$periodic['date_start'].'" ',
			), true);

			// lấy ra danh sách số lượng xuất
			$construction_relationship = $CI->autoload_model->_get_where(array(
				'table' =>'construction_relationship',
				'select' =>'sum(thucdan) as quantity, productid',
				'group_by' => 'productid',
				'where_in' => $idList,
				'where_in_field' => 'productid',
				'join' => array(array(
					'construction', 'construction.id = construction_relationship.constructionid', 'left'
				)),
				'query' => 'construction.trash = 0 AND construction_relationship.trash = 0 AND date_start <= "'.$periodic['date_end'].'" AND date_start >= "'.$periodic['date_start'].'" ',
			), true);

			// tiến hành cập nhật lại số lượng quantity_closing_stock
			foreach ($productList as $keyPrd => $valPrd) {
				// lặp qua từng sản phẩm
				$quantity_change = 0;
				if(isset($import_relationship) && check_array($import_relationship)){
					foreach ($import_relationship as $keyIm => $valIm) {
						if($valIm['productid'] == $valPrd['id']){
							$quantity_change = $quantity_change + $valIm['quantity'];
						}
					}
				}
				if(isset($repay_relationship) && check_array($repay_relationship)){
					foreach ($repay_relationship as $keyRe => $valRe) {
						if($valRe['productid'] == $valPrd['id']){
							$quantity_change = $quantity_change + $valRe['quantity'];
						}
					}
				}
				if(isset($construction_relationship) && check_array($construction_relationship)){
					foreach ($construction_relationship as $keyCon => $valCon) {
						if($valCon['productid'] == $valPrd['id']){
							$quantity_change = $quantity_change - $valCon['quantity'];
						}
					}
				}
				$productList[$keyPrd]['quantity_closing_stock'] = ($valPrd['quantity_opening_stock'] ?? 0) + $quantity_change;
				$productList[$keyPrd]['quantity_closing_stock'] = round($productList[$keyPrd]['quantity_closing_stock'],2);
			}
			return $productList;
		}

		
	}else{
		return $productList;
	}
}

function check_permission($id, $permission){
	$CI =& get_instance();
	// get data
	if(!empty($id)){
		$user = $CI->autoload_model->_get_where(array(
			'select' => 'permission',
			'table' => 'user',
			'where' => array('id' => $id),
			'flag' => false
		));
		$permissionList=json_decode($user['permission'], true);
		if($permissionList == null || $permissionList == ''){
			return false;
		}
		if(in_array($permission, $permissionList) == true){
			return true;
		}else{
			return false;
		}
	}
	
}
function check_authid($authid = ''){
	$authid = json_decode(base64_decode($authid), true);
	if(isset($authid['id'])){
		return array('code' => 200, 'result' => true, 'message' => 'Dữ liệu truyền vào hợp lệ');
	}else{
		return array('code' => 214, 'result' => false, 'message' => 'Dữ liệu truyền vào không hợp lệ');
	}
}

