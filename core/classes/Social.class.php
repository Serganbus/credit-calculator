<?

abstract class Social {

    protected $access_token;

	public $redirectUrl;

    abstract function getUser($code);
    abstract function getRedirectUrl($params=null);
    
    function getRedirect(){
    	if(isset($_GET['code'])){
    		parse_str($_SERVER['QUERY_STRING'],$arr);
    		unset($arr['code']);
    		return $this->getProtocol()."://".$_SERVER['HTTP_HOST']."/?".http_build_query($arr);
    	}
    	return $this->getRedirectUrl();
    }

    abstract function getHref($params=null);

    abstract function getMainPhoto($uid);

    abstract function getScoring($uid, $access_token);

//	function getScoring($uid,$access_token){
//		return array();
//	}

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

    private static $social = array();

    /**
     * @return array
     */
    static function getInstance() {
        if (empty(self::$social)) {
            foreach (array('fb', 'ok', 'vk') as $social) {
                $cfg = Cfg::get($social);
                $class_name = "Social" . strtoupper($social);
                require_once(dirname(__FILE__) . "/{$class_name}.class.php");
                self::$social[$social] = new $class_name($cfg);
            }
        }
        return self::$social;
    }

    /**
     * 
     * @param string $name
     * @return Social
     */
    static function getSocial($name) {
        $social = self::getInstance();
        if (isset($social[$name])) {
            return $social[$name];
        }
    }

    protected function request($url, $dataArray = array()) {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        if(in_array($_SERVER['HTTP_HOST'], array('finnal.ru'))){
            curl_setopt($handle, CURLOPT_SSLVERSION, 4); // Force SSLv3 to fix Unknown SSL Protocol error
        }
        
        
        //curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        if ($dataArray) {
            curl_setopt($handle, CURLOPT_POST, true);
//            curl_setopt($handle, CURLOPT_POSTFIELDS, urldecode(http_build_query($dataArray)));
            curl_setopt($handle, CURLOPT_POSTFIELDS, $dataArray);
        }


        $response = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        if (curl_errno($handle)) {
            echo curl_error($handle);
            die();
        }
        $array = json_decode($response, true);
        $array['code'] = $code;
        return $array;
    }

    protected function getProtocol(){
        $p='http';
	if($_SERVER['SERVER_PORT']!=80){
		$p='https';
	}
	return $p;
    }
}