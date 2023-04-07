<?php
(defined('BASEPATH')) or exit('No direct script access allowed');
class MY_Model extends CI_Model {
	public function __construct(){
		parent::__construct();
	}
	public function _get_where($param = '', $flag = FALSE){
		$param['table'] = isset($param['table']) ? $param['table'] : '';
		$param['select'] = isset($param['select']) ? $param['select'] : '';
		$param['start'] = isset($param['start']) ? $param['start'] : 0;
		$param['order_by'] = isset($param['order_by']) ? $param['order_by'] : '';
		$param['where_in_field'] = isset($param['where_in_field']) ? $param['where_in_field'] : '';

		$this->db->select($param['select']);
		$this->db->from($param['table']);

		if(isset($param['keyword']) && !empty($param['keyword'])){
			$keyword = $this->db->escape_like_str($param['keyword']);
			$this->db->where($param['keyword']);
		}
		if(isset($param['group_by']) && !empty($param['group_by'])){
			$this->db->group_by($param['group_by']);
		}
		if(isset($param['having']) && !empty($param['having'])){
			$this->db->having($param['having']);
		}
		if(isset($param['search']) && !empty($param['search'])){
			$this->db->where($param['search']);
			// $this->db->where('(title LIKE \'%'.$keyword.'%\' OR description LIKE \'%'.$keyword.'%\')');
		}
		if(isset($param['query']) && !empty($param['query'])){
			$this->db->where($param['query']);
			// $this->db->where('(title LIKE \'%'.$keyword.'%\' OR description LIKE \'%'.$keyword.'%\')');
		}
		if(isset($param['join']) && is_array($param['join']) && count($param['join'])){
			foreach ($param['join'] as $key => $value) {
				$this->db->join($value[0],$value[1],$value[2]);
			}
		}

		if(isset($param['distinct']) && !empty($param['distinct'])){
			$this->db->distinct();
		}
		if(isset($param['where']) && is_array($param['where']) && count($param['where'])){
			$this->db->where($param['where']);
		}
		if(isset($param['limit']) && $param['limit'] > 0){
			$this->db->limit($param['limit'], $param['start']);
		}
		if(isset($param['order_by']) && !empty($param['order_by'])){
			$this->db->order_by($param['order_by']);
		}
		if(isset($param['where_in']) && is_array($param['where_in']) && count($param['where_in']) && isset($param['where_in_field']) && $param['where_in_field'] != ''){
			$this->db->where_in($param['where_in_field'], $param['where_in']);
		}
		if(isset($param['where_not_in']) && is_array($param['where_not_in']) && count($param['where_not_in']) && isset($param['where_in_field']) && $param['where_in_field'] != ''){
			$this->db->where_not_in($param['where_in_field'], $param['where_not_in']);
		}

		
		if(isset($param['count']) && $param['count'] == TRUE){
			$result = $this->db->count_all_results();
		}else{
			if($flag == FALSE || (isset($param['flag'])  &&$param['flag'] == false )){
				$result = $this->db->get()->row_array();
			}else{
				$result = $this->db->get()->result_array();
			}
		}
		$this->db->flush_cache();
		return $result;


	}
	public function _update($param = ''){
		if(!($this->model_rela->process($param, 'update'))){
			return false;
		}
		$param['where_in_field'] = isset($param['where_in_field']) ? $param['where_in_field'] : 'id';
		if(isset($param['where']) && is_array($param['where']) && count($param['where'])){
			$this->db->where($param['where']);
		}
		if(isset($param['query']) && !empty($param['query'])){
			$this->db->where($param['query']);
		}
		if(isset($param['where_in']) && is_array($param['where_in']) && count($param['where_in']) && isset($param['where_in_field']) && $param['where_in_field'] != ''){
			$this->db->where_in($param['where_in_field'], $param['where_in']);
		}

		$this->db->update($param['table'], $param['data']);
		$result = $this->db->affected_rows(); // Sô dòng thay đổi trong database khi thực hiện câu update.
		$this->db->flush_cache();
		log_data($param, 'update');
		return $result;
	}
	// public function _update_batch($param = ''){
	// 	if(isset($param['update']) && is_array($param['update']) && count($param['update'])){
	// 		$this->db->update_batch($param['table'], $param['update'],$param['field']);
	// 	}
	// 	$result = $this->db->affected_rows(); // Số dòng thay đổi trong database.
	// 	$this->db->flush_cache();
	// 	log_data($param, 'update');
	// 	return $result;
	// }

	public function _create($param = ''){

		$this->db->insert($param['table'], $param['data']);
		$result = $this->db->affected_rows();
		if($result > 0){
			$resultid = $this->db->insert_id();
		}

		$param['data']['id'] = $resultid;

		// $param['data']['id'] = 292;
		// return $this->model_rela->process($param, 'create');
		if(!($this->model_rela->process($param, 'create'))){
			return false;
		}

		$this->db->flush_cache();
		return $result;
	}
	public function _create_batch($param = ''){
		$this->db->insert_batch($param['table'], $param['data']);
		$result = $this->db->affected_rows();
		$this->db->flush_cache();
		log_data($param, 'create');

		foreach ($param['data'] as $key => $val) {
			$temp = [];
			$temp['table'] =  $param['table'];
			$temp['data'] =  $val;
			if(!($this->model_rela->process($param, 'create'))){
				return false;
			}

		}

		return $result;
	}

	public function _delete($param = ''){
		if(isset($param['where']) && is_array($param['where']) && count($param['where'])){
			$this->db->where($param['where']);
		}
		if(isset($param['query']) && !empty($param['query'])){
			$this->db->where($param['query']);
		}
		if(isset($param['where_in']) && is_array($param['where_in']) && count($param['where_in']) && isset($param['where_in_field']) && $param['where_in_field'] != ''){
			$this->db->where_in($param['where_in_field'], $param['where_in']);
		}
		$this->db->delete($param['table']);
		$result = $this->db->affected_rows(); // Sô dòng thay đổi trong database khi thực hiện câu update.
		$this->db->flush_cache();
		log_data($param, 'delete');

		if(!($this->model_rela->process($param, 'create'))){
			return false;
		}



		return $result;
	}

	
}
