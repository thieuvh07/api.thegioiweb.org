<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Rest_api extends REST_Controller {
    function api_input($method = ''){
        switch ($method) {
            case 'post':
                $data = $this->post();
                break;
            case 'get':
                $data = $this->get();
                break;
            case 'put':
                $data = $this->put();
                break;
            case 'delete':
                $data = $this->delete();
                break;
        }
        return $data ?? [];
    }
}
