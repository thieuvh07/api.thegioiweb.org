<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Supplier_detail extends REST_Controller {

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
			
			// lấy ra danh sách đơn nhập trong kì
			$import = $this->autoload_model->_get_where(array(
				'table' => 'import',
				'select' => 'import.code, import.created, import.id, import.data_json,
					(SELECT fullname FROM user WHERE user.id = import.userid_created AND import.trash = 0) as user_created,
					(SELECT SUM(price*quantity_import) FROM import_relationship WHERE import_relationship.importid = import.id AND import_relationship.trash = 0) as total_money,
					',
				'query' => 'trash = 0 AND import.supplierid = '.$id.' AND import.created <= "'.$periodic['date_end'].'" AND import.created >= "'.$periodic['date_start'].'" ',
			), true);
			$data['import'] = $import;

			// lấy ra danh sách đơn trả trong kì
			$repay = $this->autoload_model->_get_where(array(
				'table' => 'repay',
				'select' => 'repay.code, repay.created, repay.id, repay.data_json,
					(SELECT fullname FROM user WHERE user.id = repay.userid_created AND user.trash = 0) as user_created,
					(SELECT SUM(price*quantity_repay) FROM repay_relationship WHERE repay_relationship.repayid = repay.id AND repay_relationship.trash = 0) as total_money,
					',
				'query' => 'trash = 0 AND repay.supplierid = '.$id.' AND repay.created <= "'.$periodic['date_end'].'" AND repay.created >= "'.$periodic['date_start'].'" ',
			), true);
			$data['repay'] = $repay;

			//  bảng ứng lương trong kì
			$cash = $this->autoload_model->_get_where(array(
				'table' => 'cash as tb1',
				'select' => 'tb1.output ,tb1.input, tb1.title, tb1.time, tb1.note',
				'join' => array(
					array('catalogue_relationship as tb2' , 'tb2.moduleid = tb1.userid AND tb2.trash = 0', 'left'),
					array('user_catalogue as tb3' , 'tb2.catalogueid = tb3.id AND tb3.trash = 0', 'left'),
				),
				'query' => 'tb1.trash = 0 AND tb1.supplierid = '.$id.' AND tb1.catalogueid=7 AND tb1.trash = 0 AND tb1.time <= "'.$periodic['date_end'].'" AND tb1.time >= "'.$periodic['date_start'].'"',
			), true);
			$data['cash'] = $cash;

			// processing
			return $this->response(json_encode(array('code' => '200', 'result' => true , 'message' => 'Lấy dũ liệ thành công', 'data' => array('list' => $data))), 200);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}


}	
