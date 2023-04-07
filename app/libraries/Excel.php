<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once substr( APPPATH, 0, strlen(APPPATH) - 4 )."/plugin/PHPExcel/Classes/PHPExcel.php";
class Excel extends PHPExcel {
    public function __construct() {
        parent::__construct();
    }
}