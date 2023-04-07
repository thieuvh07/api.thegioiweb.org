
<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH . 'third_party/REST_Controller.php';

class Construction_export extends REST_Controller {

	protected $module;
	protected $fieldKeywordArray;
	function __construct() {
		parent::__construct();
		$this->module = 'construction_export';
	}

	/**
	 * index_post: Thực hiện cập nhật 1 bản ghi
	 * 
	 * @param 
	 * @return json
	 */
	public function index_put($id = 0){

		try {
			// get data
			$response = check_authid($this->input->get('authid'));
			if($response['result'] == false){
				return $this->response(json_encode($response), $response['code']);
			}
			$id = (int) $id;
			if($id <= 0) return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ')), 404);
			$data = $this->rest_api->api_input('put');
			//lấy dữ liệu sp từ danh sách sp được chon
			$product = $data['product'];
			if(isset($product['id']) && is_array($product['id']) && count($product['id'])){
				foreach ($product['id'] as $key => $val) {
					$list_product[$key]['id'] = $val; 
					$list_product[$key]['title'] = $product['title'][$key]; 
					$list_product[$key]['code'] = $product['code'][$key]; 
					$list_product[$key]['price_output'] = (int)str_replace('.','',$product['price_output'][$key]); 
					$list_product[$key]['measure'] = $product['measure'][$key]; 
					$list_product[$key]['quantity'] = $product['quantity'][$key]; 
					$list_product[$key]['quantity_old'] = $product['quantity_old'][$key]; 
					$list_product[$key]['trenphieu'] = $product['trenphieu'][$key]; 
				}
			}
			// return pre($list_product);
			// kiểm tra số lượng trong kho có đủ không
			if(isset($list_product) && check_array($list_product) ){
				foreach ($list_product as $keyPrd => $valPrd) {
					// lấy số lượng hiện tại
					if($valPrd['quantity'] != 0){
						$product = $this->autoload_model->_get_where(array(
	                    	'table'=>'product',
	                    	'where'=>array('id'=>$valPrd['id']),
	                    	'query' => 'trash = 0',
	                    	'select'=>'id, quantity_opening_stock'
	                    ));
	                    $product = quantity_closing_stock($product);

	                    $quantity_closing_stock = $product['quantity_closing_stock'];
	                    // lấy số lượng sản phẩm thay đổi khi cập nhật
	                    $quantity_change = $valPrd['quantity'] - $valPrd['quantity_old'];
	                    if($quantity_change > $quantity_closing_stock){
	                    	return $this->response(json_encode(array('code' => '202', 'result' => false, 'message' => 'Số lượng trong kho không đủ')), 202);
	                    }
					}
				}
			}
			
			if(isset($list_product) && check_array($list_product) ){
				foreach ($list_product as $keyPrd => $valPrd) {
                    // cập nhật lại bảng construct_relationship
                    $_update_rela = array(
						'thucdan' => $valPrd['quantity'],
						'trenphieu' => $valPrd['trenphieu'],
					);

                    $this->crud->update(array('data' => $_update_rela ,'table' => 'construction_relationship', 'query' => 'productid = '.$valPrd['id'].' AND constructionid = '.$id));
				}

                if(isset($list_product) && check_array($list_product) ){
                	$data_json = base64_encode(json_encode($list_product));

                	$this->db->where('id = '.$id);
                	$this->db->update('construction', array('data_json' => $data_json));
					$result = $this->db->affected_rows(); // Sô dòng thay đổi trong database khi thực hiện câu update.
					$this->db->flush_cache();

                }
                // cập nhật ghi chú
                $_update = array(
					'export_note' => $data['note'] ?? '',
				);	
				// processing
				$response = $this->crud->update(array('table' => 'construction', 'data' => $_update, 'where' => array('id' => $id)));


				return $this->response(json_encode(array('code' => '201', 'result' => true, 'message' => 'Cập nhật thành công')), 201);
			}
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => 'Có lỗi sảy ra vui lòng thử lại')), 404);
		} catch (SignatureInvalidException $e) {
			return $this->response(json_encode(array('code' => '404', 'result' => false, 'message' => $e->getmessage())), 404);
		}
	}

	public function excel_get(){
		$url = substr(APPPATH, 0, -4);
		$excel_path = $url.'plugin/PHPExcel/Classes/PHPExcel.php';
		require($excel_path);

		$objPHPExcel = new PHPExcel();
		$objPHPExcel->setActiveSheetIndex(0); 
		
		
		$listExport = $this->autoload_model->_get_where(array(
			'table' => 'construction',
			'query' => 'trash = 0',
			'select' => 'id, code, phone, fullname, data_json',
		),true);
		
		if(isset($listExport) && check_array($listExport)){
			foreach ($listExport as $key => $val) {
				if(isset($val['data_json'])){
					$detail = '';
					$data_json = json_decode(base64_decode($val['data_json']),true);
					if(isset($data_json) && check_array($data_json)){
						foreach ($data_json as $sub => $subs) {
							$detail = $detail.'- '.($subs['title'] ?? '').'(SL: '.($subs['quantity'] ?? '').')'.'('.($subs['trenphieu'] ?? '').'),';
						}
					}
					$listExport[$key]['detail'] = $detail;
				}
			}
		}
		// echo '<pre>';
		// print_r($listExport);die();
	
		$columnArray = array("A", "B", "C", "D", "E");
		$titlecolumnArray = array('STT','ID','MÃ công trình','Tên công trình','Tên sản phẩm');
		$row_count = 1;
		 $styleArray = array(
			  'borders' => array(
				  'allborders' => array(
					  'style' => PHPExcel_Style_Border::BORDER_THIN
				  )
			  )
		  );
		$objPHPExcel->getDefaultStyle()->applyFromArray($styleArray);
		foreach($columnArray as $key => $val){
			$objPHPExcel->getActiveSheet()->SetCellValue($val.$row_count, $titlecolumnArray[$key]);  // lấy ra tiêu đề của từng cột	
			 $objPHPExcel->getActiveSheet()->getColumnDimension($val)->setAutoSize(true);
			$objPHPExcel->getActiveSheet()->getStyle($val.$row_count)->applyFromArray(
				array(
					'fill' => array(
						'type' => PHPExcel_Style_Fill::FILL_SOLID,
						'color' => array('rgb' => 'F28A8C')
					)
				)
			);
		}
		$i = 2;
		$total_row = $i + count($listExport);
		$total = 0;
		
		if(isset($listExport) && is_array($listExport) && count($listExport)){
			foreach($listExport as $key => $val){
				$objPHPExcel->getActiveSheet()->getRowDimension($i)->setRowHeight(50);
				$objPHPExcel->getActiveSheet()->SetCellValue('A'.$i, $i); 
				$objPHPExcel->getActiveSheet()->SetCellValue('B'.$i, $val['id']); 
				$objPHPExcel->getActiveSheet()->SetCellValue('C'.$i, $val['code']); 
				$objPHPExcel->getActiveSheet()->SetCellValue('D'.$i, $val['fullname'].' '.$val['phone']); 
				$objPHPExcel->getActiveSheet()->SetCellValue('E'.$i, $val['detail']); 
				$i++;
			}
		}
		$random = random(6,true);
		
		$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel); 
		$objWriter->save(''.$url.'upload/files/excel/export_'.$random.str_replace('/','_',date("Y/m/d")).'.xlsx'); 
		$data['filename'] = 'upload/files/excel/export_'.$random.str_replace('/','_',date("Y/m/d")).'.xlsx';
		return $this->response(json_encode(array('code' => '200', 'result' => true, 'message' => 'Đã gửi file exel tới mail', 'data' => array('list' => BASE_URL.$data['filename']))), 200);
	}
}