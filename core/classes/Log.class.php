<?php

class Log {

    private static $instance;
    private $log_file;

    private function __construct() {
        $log_dir = ROOT . '/log';
        $log_file = $log_dir . '/' . date('Y_m_d') . '_log.txt';
        if (!file_exists($log_dir)) {
            mkdir($log_dir);
        }
        $this->setLogFile($log_file);
    }

    function getLogFile() {
        return $this->log_file;
    }

    function setLogFile($log_file) {
        $this->log_file = $log_file;
    }

    function writeStr($str) {
        $data = date('Y-m-d H:i:s') . " " . $str . "\r\n";
        file_put_contents($this->getLogFile(), $data, FILE_APPEND);
    }

    /**
     * @return Log
     */
    static function getInstance() {
        if (empty(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    static function write($str) {
        self::getInstance()->writeStr($str);
    }

}

?>