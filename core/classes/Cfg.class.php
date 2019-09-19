<?php

class Cfg {

    private static $cfg = array();

    private function __construct() {
        
    }

    static function get($key = null) {
        if ($key) {
            return isset(self::$cfg[$key]) ? self::$cfg[$key] : null;
        }
        return self::$cfg;
    }

    static function add($cfg) {
        self::$cfg+=$cfg;
    }

    static function saveCfgData($data, $file) {
        $custom_settings_file = Cfg::get('custom_settings_file');
        $custom_settings_dir = dirname($custom_settings_file);
        $file_path = $custom_settings_dir . "/" . $file . ".json";
        return file_put_contents($file_path, json_encode($data));
    }

    static function getCfgData($file, $alt = array()) {
        $custom_settings_file = Cfg::get('custom_settings_file');
        $custom_settings_dir = dirname($custom_settings_file);
        $file_path = $custom_settings_dir . "/" . $file . ".json";
        if (file_exists($file_path)) {
            $json = file_get_contents($file_path);
            return json_decode($json, true);
        }
        return $alt;
    }

}

?>