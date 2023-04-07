<?php if(!defined('BASEPATH')) exit('No direct script access allowed'); 

class Mailbie{

	public $CI;

    public function __construct(){
		$this->CI =& get_instance();
    }
	
	public function sent($param = array()){
		$config = Array(
			'protocol' => 'smtp',
			'smtp_host' => 'ssl://smtp.googlemail.com',
			'smtp_port' => '465',
			'smtp_user' => 'noreply.system.cms@gmail.com',
			'smtp_pass' => 'rcmbjjgtwegrofvx', // mật khẩu ứng dung
			'charset' => 'utf-8',
			'newline' => "\r\n",
			'mailtype' => 'html',
		);
		$this->CI->load->library('email', $config); //GỌi thư viện gửi mail Của CI
		$this->CI->email->set_newline("\r\n"); // Cấu hình newline
		$this->CI->email->from('system.cms.noreply@gmail.com', 'HT CMS SYSTEM!');
		$this->CI->email->to($param['to']);
		$this->CI->email->cc($param['cc']);
		$this->CI->email->subject($param['subject']);
		$this->CI->email->message($param['message']);
		if(isset($param['attach']) && is_array($param['attach']) && count($param['attach'])){
			foreach($param['attach'] as $key => $val){
				$this->CI->email->attach($val);
			}
		} 
		if (!$this->CI->email->send()) show_error($this->CI->email->print_debugger());
		// else echo 'Your e-mail has been sent!';
		// $this->CI->email->send();
	}
}
