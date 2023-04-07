<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Cash_detail extends REST_Controller {

	protected $module;
	protected $fieldKeywordArray;
	function __construct() {
		parent::__construct();
		$this->module = 'cash';
		$this->fieldKeywordArray =  array('title');
	}


	protected function validate($data){
		$this->load->library('form_validation');
		$this->form_validation->CI =& $this;
		$this->form_validation->set_data($data);

		$this->form_validation->set_rules('title','Diễn giải','trim|required');

		if(!$this->form_validation->run($this)){
			return $this->response(json_encode(array('code' => 202, 'result' => false, 'message' => validation_errors(),)), 202);
		}else{
			return true;
		}
	}


	protected function set_data($data, $method){
		$field = array('catalogueid', 'input','output','title','time','constructionid','supplierid','userid', 'note');
		foreach ($data as $key => $val) {
			if(in_array($key, $field )){
				switch ($key) {
				    case 'time': $val = convert_time(trim($val));break;
				    case 'input': $val = (int)str_replace('.','',$val);break;
				    case 'output': $val = (int)str_replace('.','',$val);break;
				}
				$temp[$key] = $val;
			}
		}
		switch ($method) {
		    case 'insert':
		        $temp['created'] = gmdate('Y-m-d H:i:s', time() + 7*3600);
				$temp['userid_created'] = $this->auth['id'];
				$temp['trash'] = 0;
				break;
		    default:
			    $temp['updated'] = gmdate('Y-m-d H:i:s', time() + 7*3600);
				$temp['userid_updated'] = $this->auth['id'];
				$temp['trash'] = 0;
				break;
		}
		return $temp;
	}

	public function index_post(){
		try {
			// get data
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}
			$data = $this->rest_api->api_input('post');

			if($this->validate($data)){
				$_insert = $this->set_data($data, 'insert');

				// processing
				$response = $this->crud->insert(array('table' => $this->module, 'data' => $_insert));
				return $this->response(json_encode($response), $response['code']);
			}

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}

	public function index_put($id){
		try {
			// get data
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}
			$data = $this->rest_api->api_input('put');

			if($this->validate($data)){
				$_update = $this->set_data($data, 'update');

				// processing
				$response = $this->crud->update(array('table' => $this->module, 'data' => $_update, 'where' => array('id' => $id)));
				return $this->response(json_encode($response), $response['code']);
			}

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}

	public function view_get(){
		try {
			$periodicid = $this->input->get('periodicid');
			if($periodicid == "undefined" || $periodicid == ""){
				$periodicid = $this->common->last_id('periodic');
			}
			if($periodicid > 0){
				$periodic = $this->autoload_model->_get_where(array(
					'table' => 'periodic',
					'select' => 'money_opening',
					'query' => 'trash = 0 AND id = '.$periodicid,
				));
			}
			// if($periodicid <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);
			$data = $this->rest_api->api_input('get');
			$data = $this->convert_data($data);


			
			$response = $this->crud->get(array(
				'table'=>'cash',
				'join'=> array(
					array('cash_catalogue as tb2', 'cash.catalogueid = tb2.id AND tb2.trash = 0', 'left'),
					array('construction as tb3', 'cash.constructionid = tb3.id AND tb3.trash = 0', 'left'),
					array('supplier as tb4', 'cash.supplierid = tb4.id AND tb4.trash = 0', 'left'),
					array('user as tb5', 'cash.userid = tb5.id AND tb5.trash = 0', 'left'),
				),
				'select' => 'cash.time,cash.id, cash.time, cash.title, cash.input, cash.output, cash.note, tb3.fullname as fullname, tb3.phone as phone, 
					tb2.title as catalogue, tb4.title as supplier, tb5.fullname as user,
					tb2.id as catalogueid, tb4.id as supplierid, tb5.id as userid, tb3.id as constructionid
					',
				'query'=> $data['query'],
				'group_by' => 'cash.id',
				'order_by' => 'cash.created DESC, id',
			),true);
			if(isset($response['data']['list']) && check_array($response['data']['list'])){
				foreach ($response['data']['list'] as $key => $val) {
					$response['data']['list'][$key]['extend']= '';
					if(!empty($val['supplier'])){
						$response['data']['list'][$key]['extend'] = $val['supplier'];
						$response['data']['list'][$key]['extendid'] = $val['supplierid'];
					}
					if(!empty($val['user'])){
						$response['data']['list'][$key]['extend'] = $val['user'];
						$response['data']['list'][$key]['extendid'] = $val['userid'];
					}
					if(!empty($val['fullname'])){
						$response['data']['list'][$key]['extend'] = $val['fullname'].' '.$val['phone'];
						$response['data']['list'][$key]['extendid'] = $val['constructionid'];
					}
				}
			}
			$response = $this->process_response($response);
			$response['data']['money_opening'] = $periodic['money_opening'] ?? 0;
			return $this->response(json_encode($response), $response['code']);

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}


	

	protected function convert_data($data){
		$data['table'] = $this->module;
		$data['fields'] = $this->process_select(($data['fields'] ?? ''));
		$data['select'] = !empty($data['fields']) ? $data['fields'] : $this->module.'.id';

		$data['start'] = $data['offset'] ?? 0;
		$data['limit'] = $data['limit'] ?? 20;

		$queryData = render_search_in_query($this->module, $this->input->get(), array('fieldKeywordArray' => $this->fieldKeywordArray), false);

		$queryList = $queryData['queryList'];
		$join = [];
		$query = 'AND cash.trash = 0 ';

		$keyword = $queryList['keyword'] ?? '';
		$fieldKeywordArray = array('cash.title');
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
			$query = $query.' AND '.$this->module.'.time >= "'.$date_start.'" AND '.$this->module.'.time <= "'.$date_end.'"';
		}
		if(isset($queryList['periodicid'])){
			// lấy thời gian trong kì
			if($queryList['periodicid'] == "undefined"){
				$queryList['periodicid'] = $this->common->last_id('periodic');
			}
			$periodic = $this->autoload_model->_get_where(array(
				'table' => 'periodic',
				'select' => 'date_start, date_end',
				'query' => 'trash = 0 AND id = '.$queryList['periodicid'],
			));
			$query = $query.' AND cash.time<= "'.$periodic['date_end'].'" AND cash.time>= "'.$periodic['date_start'].'"';
		}
		if(isset($queryList['catalogueid'])){
			$query = $query.' AND cash.catalogueid = '.$queryList['catalogueid'];
		}
		if(isset($queryList['constructionid'])){
			$query = $query.' AND cash.constructionid = '.$queryList['constructionid'];
		}
		$query = substr( $query, 4, strlen($query));
		$data['query'] = $query;
		$order_by = 'cash.created ASC';
		$data['order_by'] = (!empty($queryData['order_by'])) ? $queryData['order_by'].', '.$order_by : $order_by;
		return $data;
	}
	private function process_select($field = ''){
		if(strrpos($field, 'user_created')){
			$temp = '(SELECT fullname FROM user WHERE user.id = '.$this->module.'.userid_created AND user.trash = 0) as user_created';
			$field = str_replace ( 'user_created', $temp, $field );
		}
		if(strrpos($field, 'cash_count')){
			$temp = '(SELECT COUNT(id) FROM cash WHERE cash.id = '.$this->module.'.userid_created AND cash.trash = 0) as cash_count';
			$field = str_replace ( 'cash_count', $temp, $field );
		}
		return $field;
	}


	protected function process_response($response){

		$list = $response['data']['list'];
		if(isset($list) && check_array($list)){
			foreach ($list as $key => $val) {
				if(isset($val['time'])){
					$list[$key]['time'] = gettime($val['time'], 'micro');
				}
			}
		}
		$response['data']['list'] = $list;
		return $response;
	}

	/**
	 * search_get: Thực hiện search với điều kiện
	 * 
	 * @param $data = array('fields' => 'id, title','offset' => (($page-1)*$perpage),x'limit' => $perpage,'order_by' => 'id DESC',)
	 * @return json
	 */
	public function search_get(){

		try {
			// get data
			$data = $this->rest_api->api_input('get');

			$data = $this->convert_data($data);
			// count record
			$response = $this->crud->count(array( 'table' => $this->module, 'join' => $data['join'] ?? [],'query' => $data['query'], 'count' => TRUE,));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}else{
				$total_rows = $response['data']['count'];
			}
			if($total_rows <= 0){
				return $this->response(json_encode(array('code' => 200, 'result' => true, 'message' => 'Không có bản ghi nào', 'data' => array('list' => [], 'from' =>  0, 'to' => 0, 'total_rows' => 0, 'pagination' => ''))), REST_Controller::HTTP_OK);
			}else{
				$response = $this->crud->get($data);
				
				$config = panigation($this->config_panigation($data)); 
				$config['total_rows'] = $total_rows;
				$this->load->library('pagination');
				$this->pagination->initialize($config);

				$totalPage = ceil($total_rows/$config['per_page']);
				$page = $config['cur_page'];
				$page = ($page <= 0)?1:$page;
				$page = ($page > $totalPage)?$totalPage:$page;
				$page = $page - 1;
				$response['data']['from'] =  ($page * $config['per_page']) + 1;
				$response['data']['to'] = ($config['per_page']*($page+1) > $total_rows) ? $total_rows  : $config['per_page']*($page+1);
				$response['data']['pagination'] = $this->pagination->create_links($config);
				$response['data']['total_rows'] = $total_rows;
				$response = $this->process_response($response,$config);
				return $this->response(json_encode($response), $response['code']);
			}

		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}


	/**
	 * delete_post: Thực hiện xóa
	 * 
	 * @return json
	 */
	public function delete_post($id = ''){

		try {
			// get data
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}
			$id = (int) $id;
			if($id <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);
			if(!check_permission($this->auth['id'] ?? '', 'cash/backend/detail/delete')){
				return $this->response(json_encode(array('code' => 200, 'result' => false, 'message' => 'Bạn không có quyền thực hiện chức năng này')), 200);
			}
			
			$data = $this->rest_api->api_input('put');

			$_update = array(
				'trash' => 1,
				'updated' => gmdate('Y-m-d H:i:s', time() + 7*3600),
				'userid_updated' => $this->auth['id'],
			);

			// processing
			$response = $this->crud->update(array('table' => $this->module, 'data' => $_update, 'where' => array('id' => $id)));
			return $this->response(json_encode($response), $response['code']);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}
}
