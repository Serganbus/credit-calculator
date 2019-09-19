<?

include_once('PS.class.php');

class PSRobokassa extends PS {

    public $MrchLogin = '';
    public $MrchPass1 = '';
    public $MrchPass2;
    public $OutSum = '';
    public $InvId = '0';
    public $Desc = '';
    public $Email = '';
    public $shpa = '';
    public $test = '';
    private $Culture = 'ru';
//	public $IncCurrLabel='PCR';
    public $IncCurrLabel = '';
//	private $Encoding='windows-1251';
    private $Encoding = 'utf-8';
    private $psName = 'Робокасса';
    private $psDesc = 'Оплатить с помощью электронных денег РОБОКАССА';

    function getPsName() {
        return $this->psName;
    }

    function getPsDesc() {
        return $this->psDesc;
    }

    public $config = array(
        'MrchLogin', 'MrchPass1', 'MrchPass2', 'IncCurrLabel', 'test'
    );
    public $params = array();

    /**
     * Проверка на странице SUCCESS
     * @param type $hash
     * @param type $opt
     * @return bool
     */
    function checkSignature2($hash, $opt = array()) {
        $this->setOpt($opt);
        //$str=$this->OutSum.":".$this->InvId.":".$this->MrchPass2;
        //$this->OutSum=(float)$this->OutSum;
        $str = $this->OutSum . ":" . $this->InvId . ":" . $this->MrchPass1;
        //$str=$this->MrchLogin.":".$this->OutSum.":".$this->InvId.":".$this->MrchPass1;
        $params = $this->params;
        if ($params && ksort($params)) {
            foreach ($params as $key => $val) {
                $str.=":shp_" . $key . "=" . $val;
            }
        }
        if ($this->shpa) {
            $str.=":shpa={$this->shpa}";
        }
        return strtoupper($hash) == strtoupper(md5($str));
    }

    /**
     * Проверка на result (back url)
     * @param type $hash
     * @param type $opt
     * @return true
     */
    function checkSignature($hash, $opt = array()) {
        $this->setOpt($opt);
        $str = $this->OutSum . ":" . $this->InvId . ":" . $this->MrchPass2;
//		$str=$this->MrchLogin.":".$this->OutSum.":".$this->InvId.":".$this->MrchPass1;
        $params = $this->params;
        if ($params && ksort($params)) {
            foreach ($params as $key => $val) {
                $str.=":shp_" . $key . "=" . $val;
            }
        }
        if ($this->shpa) {
            $str.=":shpa={$this->shpa}";
        }
        return $hash == strtoupper(md5($str));
    }

//	private $url='https://merchant.roboxchange.com/Index.aspx';
    private $url = 'https://auth.robokassa.ru/Merchant/Index.aspx';
    private $url_test = 'http://test.robokassa.ru/Index.aspx';

    function getSignatureValue() {
        $str = $this->MrchLogin . ":" . $this->OutSum . ":" . $this->InvId . ":" . $this->MrchPass1;
        $params = $this->params;
        if ($params && ksort($params)) {
            foreach ($params as $key => $val) {
                $str.=":shp_" . $key . "=" . $val;
            }
        }
        if ($this->shpa) {
            $str.=":shpa={$this->shpa}";
        }
        return md5($str);
//		$SignatureValue=strtoupper(md5("$MrchLogin:$OutSum:$InvId:$MrchPass1:Shp_demo=$Shp_demo:Shp_item=$Shp_item"));
    }

    function getAddr() {
        if ($this->test) {
            return $this->url_test;
        }
        return $this->url;
    }

    function setOpt($opt = array()) {
        if (isset($opt['uid'])) {
            $this->shpa = $opt['uid'];
        }

        if (isset($opt['InvId'])) {
            $this->InvId = $opt['InvId'];
        }

        if (isset($opt['order_id'])) {
            $this->params['order_id'] = $opt['order_id'];
        }

        if (isset($opt['order_type'])) {
            $this->params['order_type'] = $opt['order_type'];
        }

        if (isset($opt['sum'])) {
            $this->OutSum = $opt['sum'];
        }

        if (isset($opt['desc'])) {
            $this->Desc = $opt['desc'];
        }

        $this->params['pmv'] = 0;
    }

    function getUrl($opt = array()) {
        $this->setOpt($opt);

        $result = $this->getAddr() . '?' .
                "MrchLogin=" . $this->MrchLogin . '&' .
                "OutSum=" . $this->OutSum . '&' .
                "InvId=" . $this->InvId . '&' .
                "Desc=" . $this->Desc . '&' .
                "Culture=" . $this->Culture;

        if ($this->Email) {
            $result.="&Email=" . $this->Email;
        }
        if ($this->IncCurrLabel) {
            $result.="&IncCurrLabel=" . $this->IncCurrLabel;
        }

        $result.="&Encoding=" . $this->Encoding .
                "&SignatureValue=" . $this->getSignatureValue();
        $params = $this->params;
        if ($params) {
            foreach ($params as $key => $val) {
                $result.="&shp_" . $key . "=" . $val;
            }
        }
        if ($this->shpa) {
            $result.="&shpa=" . $this->shpa;
        }
        return $result;
    }

    function renderPsForm($opt = array()) {
        $this->setOpt($opt);
        $out = "<form action='{$this->getAddr()}' method='post' target='_blank'>
                        <input type='hidden' name='MrchLogin' value='{$this->MrchLogin}'>
                        <input type='hidden' name='OutSum' value='{$this->OutSum}'>
                        <input type='hidden' name='InvId' value='{$this->InvId}'>
                        <input type='hidden' name='Desc' value='{$this->Desc}'>
                        <input type='hidden' name='SignatureValue' value='{$this->getSignatureValue()}'>	
                        <input type='hidden' name='shp_pmv' value='{$this->params['pmv']}'>	
                        <input type='hidden' name='shpa' value='{$this->shpa}'>				
                        <input type='hidden' name='shp_order_id' value='{$this->params['order_id']}'>				
                        <input type='hidden' name='shp_order_type' value='{$this->params['order_type']}'>
                        <button>Продлить займ</button>
                    </form>";
        return $out;
    }

    function setEmail($val) {
        $this->Email = $val;
    }

    function setSumm($val) {
        $this->OutSum = $val;
    }

    function setOrderNum($val) {
        $this->params['OrderId'] = $val;
    }

    function setType($val) {
        $this->params['Type'] = $val;
    }

    function setUserId($val) {
        $this->params['UserId'] = $val;
    }

    function setPhone($val) {
        
    }

    function setDesc($val) {
        $this->Desc = $val;
    }

//	public $disabled=false;
}
