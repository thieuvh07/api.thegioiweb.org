<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Model_rela extends MY_Controller {

    // 2 tác dụng: dùng để ghi log, phân luồng xử lí đế các bảng 

    function process($param, $method){
        // ghi log quá trình cập nhật dữ liệu chỉ đối với bảng có filed id
        $param['data']['id'] = $param['data']['id'] ?? $param['where']['id'] ?? '';
        // phân luông xử lí đến từng table
        if(!empty($param['table'])){
            switch ($param['table']) {
                case 'product':
                    return $this->product($param, $method);
                case 'user':
                    return $this->user($param, $method);
                case 'import':
                    return $this->import($param, $method);
                case 'repay':
                    return $this->repay($param, $method);
                case 'construction':
                    return $this->construction($param, $method);
            }
        }
        return true;
    }


    // kết nối với bảng product_relatioship thông qua filed module vs id
    // tác dụng để tìm kiếm theo nhóm
    function product($param, $method){
        // nếu cập nhật lại nhóm danh mục
        if(isset($param['data']['catalogueid'])){
            $catalogueid = json_decode($param['data']['catalogueid'], true);
            if(isset($catalogueid) && check_array($catalogueid)){
                $this->autoload_model->_delete(array(
                    'query' => 'productid = '.$param['data']['id'].' AND module = "product"',
                    'table' => 'product_relationship'
                ));

                $_insert_rela = [];
                foreach ($catalogueid as $key => $val) {
                    $_insert_rela[] = array(
                        'module' =>'product' ,
                        'productid'=> $param['data']['id'],
                        'catalogueid'=> $val,
                        'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                    );
                }
                if(isset($_insert_rela) && check_array($_insert_rela)){
                    $this->autoload_model->_create_batch(array(
                        'table' => 'product_relationship',
                        'data' => $_insert_rela,
                    ));
                }
            }
        }

        // nếu cập nhật lại nhà cung cấp
        if(isset($param['data']['supplierid'])){
            $supplierid = json_decode($param['data']['supplierid'], true);
            if(isset($supplierid) && check_array($supplierid)){
                $this->autoload_model->_delete(array(
                    'query' => 'productid = '.$param['data']['id'].' AND module ="supplier"',
                    'table' => 'product_relationship'
                ));

                $_insert_rela = [];
                foreach ($supplierid as $key => $val) {
                    $_insert_rela[] = array(
                        'module' =>'supplier' ,
                        'productid'=> $param['data']['id'],
                        'catalogueid'=> $val,
                        'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                    );
                }
                if(isset($_insert_rela) && check_array($_insert_rela)){
                    $this->autoload_model->_create_batch(array(
                        'table' => 'product_relationship',
                        'data' => $_insert_rela,
                    ));
                }
            }
        }

        // nếu câp nhật lại trash
        // nếu xóa thì xóa hết hết bản ghi trong catalogueid_relationship
        if(isset($param['data']['trash']) && $param['data']['trash'] == 1){
            $this->autoload_model->_delete(array(
                'query' => 'productid = '.$param['data']['id'],
                'table' => 'product_relationship'
            ));
        }

        // nếu từ quay lại không xóa nữa thì lấy lại json ở table product để tạo catalogueid_relationship
        if(isset($param['data']['trash']) && $param['data']['trash'] == 0){
            $product = $this->autoload_model->_get_where(array(
                'query' => 'trash = 0 AND id = '.$param['data']['id'],
                'table' => 'product',
                'select' => 'catalogueid, supplierid, trash'
            ));
            if($product['trash'] == 1){
                $supplierid = json_decode($product['supplierid'], true);
                if(isset($supplierid) && check_array($supplierid)){
                    $_insert_rela = [];
                    foreach ($supplierid as $key => $val) {
                        $_insert_rela[] = array(
                            'module' =>'supplier' ,
                            'productid'=> $param['data']['id'],
                            'catalogueid'=> $val,
                            'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                        );
                    }
                    if(isset($_insert_rela) && check_array($_insert_rela)){
                        $this->autoload_model->_create_batch(array(
                            'table' => 'product_relationship',
                            'data' => $_insert_rela,
                        ));
                    }
                }
                $catalogueid = json_decode($product['catalogueid'], true);
                if(isset($catalogueid) && check_array($catalogueid)){
                    $_insert_rela = [];
                    foreach ($catalogueid as $key => $val) {
                        $_insert_rela[] = array(
                            'module' =>'product' ,
                            'productid'=> $param['data']['id'],
                            'catalogueid'=> $val,
                            'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                        );
                    }
                    if(isset($_insert_rela) && check_array($_insert_rela)){
                        $this->autoload_model->_create_batch(array(
                            'table' => 'product_relationship',
                            'data' => $_insert_rela,
                        ));
                    }
                }
            }
        }
        return true;
        
    }

    // user liên kết với bảng catalogue_rela thông qua field module="user" vs modueid=user.id
    function user($param, $method){
        // nếu cập nhật lại nhóm danh mục
        if(isset($param['data']['catalogue'])){
            $catalogue = json_decode($param['data']['catalogue'], true);
            if(isset($catalogue) && check_array($catalogue)){
                $this->autoload_model->_delete(array(
                    'query' => 'moduleid = '.$param['data']['id'].' AND module = "user"',
                    'table' => 'catalogue_relationship'
                ));

                $_insert_rela = [];
                foreach ($catalogue as $key => $val) {
                    $_insert_rela[] = array(
                        'module' =>'user' ,
                        'moduleid'=> $param['data']['id'],
                        'catalogueid'=> $val,
                        'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                    );
                }
                if(isset($_insert_rela) && check_array($_insert_rela)){
                    $this->autoload_model->_create_batch(array(
                        'table' => 'catalogue_relationship',
                        'data' => $_insert_rela,
                    ));
                }
            }
        }
        // nếu câp nhật lại trash
        // nếu xóa thì xóa hết hết bản ghi trong catalogue_relationship
        if(isset($param['data']['trash']) && $param['data']['trash'] == 1){
            $this->autoload_model->_delete(array(
                'query' => 'moduleid = '.$param['data']['id'],
                'table' => 'catalogue_relationship'
            ));
        }
        // nếu từ quay lại không xóa nữa thì lấy lại json ở table product để tạo catalogue_relationship
        if(isset($param['data']['trash']) && $param['data']['trash'] == 0){
            $user = $this->autoload_model->_get_where(array(
                'query' => 'trash = 0 AND id = '.$param['data']['id'],
                'table' => 'user',
                'select' => 'catalogue, trash'
            ));
            if($user['trash'] == 1){
              
                $catalogue = json_decode($user['catalogue'], true);
                if(isset($catalogue) && check_array($catalogue)){
                    $_insert_rela = [];
                    foreach ($catalogue as $key => $val) {
                        $_insert_rela[] = array(
                            'module' =>'user' ,
                            'moduleid'=> $param['data']['id'],
                            'catalogueid'=> $val,
                            'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                        );
                    }
                    if(isset($_insert_rela) && check_array($_insert_rela)){
                        $this->autoload_model->_create_batch(array(
                            'table' => 'catalogue_relationship',
                            'data' => $_insert_rela,
                        ));
                    }
                }

            }
        }
        return true;
        
    }

     // import liên kết với bảng import_rela thông qua field data_json
    function import($param, $method){
        // nếu cập nhật lại nhóm danh mục
        if(isset($param['data']['data_json'])){
            $data_json = json_decode(base64_decode($param['data']['data_json']), true);
            if(isset($data_json) && check_array($data_json)){
                $this->autoload_model->_update(array(
                    'query' => 'importid = '.$param['data']['id'],
                    'table' => 'import_relationship',
                    'data' => array('trash' => 1),
                ));
                $_insert_rela = [];
                foreach ($data_json as $key => $val) {
                    $_insert_rela[] = array(
                        'importid' => $param['data']['id'] ,
                        'quantity_import' => $val['quantity_import'] ,
                        'measure_import' => $val['measure_import'] ,
                        'productid' => $val['productid'] ,
                        'quantity' => $val['quantity'] ,
                        'price' => $val['price'] ,
                        'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                    );
                }
                if(isset($_insert_rela) && check_array($_insert_rela)){
                    $this->autoload_model->_create_batch(array(
                        'table' => 'import_relationship',
                        'data' => $_insert_rela,
                    ));
                }
            }
        }
        // nếu câp nhật lại trash
        // nếu xóa thì xóa hết hết bản ghi trong catalogue_relationship
        if(isset($param['data']['trash']) && $param['data']['trash'] == 1){
            $this->autoload_model->_update(array(
                'query' => 'importid = '.$param['data']['id'],
                'table' => 'import_relationship',
                'data' => array('trash' => 1),
            ));
        }
        // nếu từ quay lại không xóa nữa thì lấy lại json ở table product để tạo import_relationship
        if(isset($param['data']['trash']) && $param['data']['trash'] == 0){
            $import = $this->autoload_model->_get_where(array(
                'query' => 'trash = 0 AND id = '.$param['data']['id'],
                'table' => 'import',
                'select' => 'data_json, trash'
            ));
            if($import['trash'] == 1){
              
                $data_json = json_decode(base64_decode($import['data_json']), true);
                if(isset($data_json) && check_array($data_json)){
                    $_insert_rela = [];
                    foreach ($data_json as $key => $val) {
                        $_insert_rela[] = array(
                            'importid' => $param['data']['id'] ,
                            'quantity_import' => $val['quantity_import'] ,
                            'measure_import' => $val['measure_import'] ,
                            'productid' => $val['productid'] ,
                            'quantity' => $val['quantity'] ,
                            'price' => $val['price'] ,
                            'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                        );
                    }
                    if(isset($_insert_rela) && check_array($_insert_rela)){
                        return $this->autoload_model->_create_batch(array(
                            'table' => 'import_relationship',
                            'data' => $_insert_rela,
                        ));
                    }
                }
            }
        }
        return true;
        
    }
     // import liên kết với bảng import_rela thông qua field data_json
    function repay($param, $method){
        // nếu cập nhật lại nhóm danh mục
        if(isset($param['data']['data_json'])){
            $data_json = json_decode(base64_decode($param['data']['data_json']), true);
            if(isset($data_json) && check_array($data_json)){
                $this->autoload_model->_update(array(
                    'query' => 'repayid = '.$param['data']['id'],
                    'table' => 'repay_relationship',
                    'data' => array('trash' => 1),
                ));
                $_insert_rela = [];
                foreach ($data_json as $key => $val) {
                    $_insert_rela[] = array(
                        'repayid' => $param['data']['id'] ,
                        'quantity_repay' => $val['quantity_repay'] ,
                        'measure_repay' => $val['measure_repay'] ,
                        'productid' => $val['productid'] ,
                        'quantity' => $val['quantity'] ,
                        'price' => $val['price'] ,
                        'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                    );
                }
                if(isset($_insert_rela) && check_array($_insert_rela)){
                    $this->autoload_model->_create_batch(array(
                        'table' => 'repay_relationship',
                        'data' => $_insert_rela,
                    ));
                }
            }
        }
        // nếu câp nhật lại trash
        // nếu xóa thì xóa hết hết bản ghi trong catalogue_relationship
        if(isset($param['data']['trash']) && $param['data']['trash'] == 1){
            $this->autoload_model->_update(array(
                'query' => 'repayid = '.$param['data']['id'],
                'table' => 'repay_relationship',
                'data' => array('trash' => 1),
            ));
        }
        // nếu từ quay lại không xóa nữa thì lấy lại json ở table product để tạo repay_relationship
        if(isset($param['data']['trash']) && $param['data']['trash'] == 0){
            $repay = $this->autoload_model->_get_where(array(
                'query' => 'trash = 0 AND id = '.$param['data']['id'],
                'table' => 'repay',
                'select' => 'data_json, trash'
            ));
            if($repay['trash'] == 1){
              
                $data_json = json_decode(base64_decode($repay['data_json']), true);
                if(isset($data_json) && check_array($data_json)){
                    $_insert_rela = [];
                    foreach ($data_json as $key => $val) {
                        $_insert_rela[] = array(
                            'repayid' => $param['data']['id'] ,
                            'quantity_repay' => $val['quantity_repay'] ,
                            'measure_repay' => $val['measure_repay'] ,
                            'productid' => $val['productid'] ,
                            'quantity' => $val['quantity'] ,
                            'price' => $val['price'] ,
                            'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                        );
                    }
                    if(isset($_insert_rela) && check_array($_insert_rela)){
                        return $this->autoload_model->_create_batch(array(
                            'table' => 'repay_relationship',
                            'data' => $_insert_rela,
                        ));
                    }
                }
            }
        }
        return true;
        
    }

     // construction liên kết với bảng construction_rela
    function construction($param, $method){
        // nếu cập nhật lại nhóm danh mục
        if(isset($param['data']['data_json'])){
            // nếu method là update, kiểm tra sự thay đổi của data_json
            if($method == 'update'){
                $construction_relationship = $this->autoload_model->_get_where(array(
                    'where'=> array('constructionid'=>$param['data']['id']),
                    'table'=>'construction_relationship',
                    'select'=>'productid, id',
                    'query' => 'trash = 0',
                ), true);
                $data_json_new = json_decode(base64_decode($param['data']['data_json']), true);
                // lấy ra sản phẩm mới thêm
                foreach($data_json_new as $key =>$product){
                    foreach ($construction_relationship as $sub => $subs) {
                        if($product['id'] == $subs['productid']){
                            unset($data_json_new[$key]);
                        }
                    }
                };
                if(isset($data_json_new) && check_array($data_json_new)){
                    $_insert_rela = [];
                    foreach ($data_json_new as $key => $val) {
                        $product = $this->autoload_model->_get_where(array(
                            'table' => 'product',
                            'select' => 'price_input',
                            'query' => 'trash = 0 AND id ='.$val['id'],
                        ));
                        $_insert_rela[] = array(
                            'constructionid' => $param['data']['id'] ,
                            'productid' => $val['id'] ,
                            'price_output' => $val['price_output'] ?? 0,
                            'price_input' => $product['price_input'] ?? 0,
                            'trenphieu' => $val['trenphieu'] ?? $val['quantity'],
                            'thucdan' => $val['quantity'] ?? 0,
                            'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                        );
                    }
                    if(isset($_insert_rela) && check_array($_insert_rela)){
                        $this->autoload_model->_create_batch(array(
                            'table' => 'construction_relationship',
                            'data' => $_insert_rela,
                        ));
                    }
                }

                $list_product = json_decode(base64_decode($param['data']['data_json']), true);
                if(isset($list_product) && check_array($list_product) ){
                    foreach ($list_product as $keyPrd => $valPrd) {
                        // cập nhật lại bảng construct_relationship
                        $_update_rela = array(
                            'thucdan' => $valPrd['quantity'],
                        );
                        $this->crud->update(array('data' => $_update_rela ,'table' => 'construction_relationship', 'query' => 'productid = '.$valPrd['id'].' AND constructionid = '.$param['data']['id']));
                    }
                }
            }else{
                $data_json = json_decode(base64_decode($param['data']['data_json']), true);
                if(isset($data_json) && check_array($data_json)){
                    $_insert_rela = [];
                    foreach ($data_json as $key => $val) {
                        $product = $this->autoload_model->_get_where(array(
                            'table' => 'product',
                            'select' => 'price_input',
                            'query' => 'trash = 0 AND id ='.$val['id'],
                        ));
                            
                        $_insert_rela[] = array(
                            'constructionid' => $param['data']['id'] ,
                            'productid' => $val['id'] ,
                            'price_output' => $val['price_output'] ?? 0,
                            'price_input' => $product['price_input'] ?? 0,
                            'trenphieu' => $val['trenphieu'] ?? $val['quantity'],
                            'thucdan' => $val['quantity'] ?? 0,
                            'created' => gmdate('Y-m-d H:i:s', time() + 7*3600),
                        );
                    }
                    if(isset($_insert_rela) && check_array($_insert_rela)){
                        $this->autoload_model->_create_batch(array(
                            'table' => 'construction_relationship',
                            'data' => $_insert_rela,
                        ));
                    }
                }
            }
        }
        return true;
    }
}
