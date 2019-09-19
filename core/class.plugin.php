<?php

class Plugin {

    public $db;

    function __construct() {
        global $db;
        $this->db = $db;
    }

    function load($pl_name) {
        require_once ROOT . "/admin/core/plugins/" . $pl_name . ".php";
        return new $pl_name($this->db);
    }

    function getPlugins() {
        return $this->db->GetTable('SELECT * FROM plugins');
    }

    function getPlugin($pl_name) {
        return $this->db->GetRow('SELECT * FROM plugins WHERE plugin_name="' . $pl_name . '"');
    }

}

?>