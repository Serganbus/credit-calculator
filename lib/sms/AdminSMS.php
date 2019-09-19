<?php

require_once ROOT . '/core/plugins/sms.php';

class AdminSMS extends SMS {
    
    function __construct() {
        parent::__construct();
    }

    function send($phone, $message) {
        $phone = preg_replace('/\D/', '', $phone);
        
        $sms = array('send' => false, 'desc' => '');
        $sms['send'] = true;
        
        global $debug_mode;
        if ($debug_mode) {
            $sms['desc'] = " Дебаг режим. ({$message})";
            Log::write("{$phone}, {$message}");
        } else {
            $sms['desc'] = $this->smsprovider->send($phone, $message);
        }

        return $sms;
    }
}

?>