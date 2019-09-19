<?

abstract class PS {

    private $psName = '';
    private $psDesc = '';

    function getPsName() {
        return $this->psName;
    }

    function getPsDesc() {
        return $this->psDesc;
    }

    public $config = array();

    function __construct($config = array()) {
        if ($config) {
            if (!is_array($config)) {
                $config = unserialize($config);
            }
            foreach ($config as $name => $value) {
                $this->$name = $value;
            }
        }
    }

    abstract function getUrl($opt = array());

    abstract function setEmail($val);

    abstract function setPhone($val);

    abstract function setSumm($val);

    abstract function setOrderNum($val);

    abstract function setDesc($val);

    private static $ps = array();

    /**
     * @return array
     */
    static function getInstance() {
        if (empty(self::$ps)) {
            $cfg = Cfg::get('pay_system');
            foreach ($cfg as $pay_system => $conf) {
                $class_name = "PS" . ucfirst($pay_system);
                $filePath = dirname(__FILE__) . "/{$class_name}.class.php";
                if (file_exists($filePath)) {
                    include_once($filePath);
                    self::$ps[$pay_system] = new $class_name($conf);
                }
            }
        }
        return self::$ps;
    }

    /**
     * 
     * @param string $name
     * @return PS
     */
    static function getPs($name) {
        $ps = self::getInstance();
        if (isset($ps[$name])) {
            return $ps[$name];
        }
    }

    /**
     * @return PS
     */
    static function setData($order, $summ, $phone = null, $mail = null, $desc = null) {
        self::getInstance()->setOrderNum($order);
        self::getInstance()->setSumm($summ);
        self::getInstance()->setEmail($mail);
        self::getInstance()->setPhone($phone);
        self::getInstance()->setDesc($desc);
        return self::getInstance();
    }

    static function renderForm($opt = array()) {
        $out = '';
        foreach (self::getInstance() as $name => $ps) {
            $out.=$ps->renderPsForm($opt);
        }
        return $out;
    }

    abstract function renderPsForm($opt = array());
}
