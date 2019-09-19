<?

class AdminComponent {

    function actDefault() {
        echo 'AdminComponent';
    }

    function run() {
        $def_method = 'actDefault';
        $method = 'actDefault';
        if (!empty($_GET['act'])) {
            $method = "act" . $_GET['act'];
        }
        if (!empty($_POST['action'])) {
            $method = "act" . $_POST['action'];
        }
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $this->$def_method();
        }
    }

    function render($data = array(), $tpl = '') {
        if (!$tpl)
            return;
        foreach ($data as $k => $v) {
            $$k = is_string($v) ? htmlspecialchars($v) : $v;
        }
        $settings=Cfg::get();
        ob_start();
        include($tpl);
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    function display($data = array(), $tpl = '') {
        echo $this->render($data, $tpl);
    }

    function getUserId() {
        return $_SESSION[$_SERVER['HTTP_HOST']]['admin']['id'];
    }

    protected function getDivisionList() {
        
        $cond="hide IS NULL";
        if(!is_sadmin()){
            $cond.=" AND id=".get_user('division_id');
        }
        
        return DB::select("SELECT * FROM adm_users_division WHERE $cond")->toArray();
    }
}