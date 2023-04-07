<?php
class SignatureInvalidException extends Exception
{
    private $log_file = 'log_sign.txt'; 
    public function __construct($message = null, $code = 0) { 
        // open the log file for appending 
        if ($fp = fopen($this->log_file,'a')) { 
 			
 			// construct the log message 
            $log_msg = date("[Y-m-d H:i:s]") . 
                ' Message: '.$message.PHP_EOL. 
                ' File: '.$this->file.PHP_EOL . 
                ' Line: '.$this->line.PHP_EOL . 
                ' getTraceAsString: '.$this->getTraceAsString().PHP_EOL  ;
 
            fwrite($fp, $log_msg); 
            fclose($fp); 
        } 
        parent::__construct($message, $code); 
    } 
}
