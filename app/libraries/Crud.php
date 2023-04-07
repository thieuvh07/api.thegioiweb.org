<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Crud extends MY_Controller {
    function get($data = array(), $flag = true){
       if(DEBUG_DATABASE == 0){
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            try {
                $list = $this->autoload_model->_get_where($data, $flag);
            } catch (mysqli_sql_exception $e) {
                return array('code' => 202, 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ');
            }
        }else{
            $list = $this->autoload_model->_get_where($data, $flag);
        }
        return array('code' => 200, 'result' => true, 'message' => 'Lấy dữ liệu thành công', 'data' => array('list' => $list));
    }

    function count($data = array()){
       if(DEBUG_DATABASE == 0){
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            try {
                $count = $this->autoload_model->_get_where($data);
            } catch (mysqli_sql_exception $e) {
                return array('code' => 202, 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ');
            }
        }else{
            $count = $this->autoload_model->_get_where($data);
        }

        if(isset($count)){
            return array('code' => 200, 'result' => true, 'message' => 'Lấy dữ liệu thành công', 'data' => array('count' => $count));
        }else{
            return array('code' => 202, 'result' => false, 'message' => 'Có lỗi xảy ra');
        }
    }

    function insert($data = array()){
        if(DEBUG_DATABASE == 0){
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            try {
                $resultid = $this->autoload_model->_create($data);
            } catch (mysqli_sql_exception $e) {
                return array('code' => '202', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ');
            }
        }else{
            $resultid = $this->autoload_model->_create($data);
        }
        if(isset($resultid) && $resultid > 0){
            return array('code' => 201, 'result' => true, 'message' => 'Thêm mới thành công', 'data' => array('resultid' => $resultid));
        }else{
            return array('code' => 202, 'result' => false, 'message' => 'Có lỗi xảy ra');
        }
    }
    function update($data = array()){
        if(DEBUG_DATABASE == 0){
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            try {
                $flag = $this->autoload_model->_update($data);
            } catch (mysqli_sql_exception $e) {
                return array('code' => '202', 'result' => false, 'message' => 'Tham số truyền vào không hợp lệ');
            }
        }else{
            $flag = $this->autoload_model->_update($data);
        }
        if(isset($flag) && $flag >= 0){
            return array('code' => 201, 'result' => true, 'message' => 'Cập nhật thành công', 'data' => array('flag' => $flag));
        }else{
            return array('code' => 202, 'result' => false, 'message' => 'Có lỗi xảy ra');
        }
    }
}
