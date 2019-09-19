<?
require_once('PS.class.php');
class PSArius extends PS {

    public $login = '';
    public $key = '';
    public $test = 0;

    /**
     * Тестовая карта
Номер: 4444555566661111
· если CVV=123, то approved,
· если CVV=321, то платеж пойдет по 3DSecure
· любой другой CVV - это отказ в проведении платежа.
Expiry date: любая дата в будущем.
Имя держателя: любое.
     * 
     * http://doc.ariuspay.ru/doc/sha-1.htm
     *
     * @param array $opt
     * @return string
     */
    function cbSign($opt = array()) {
        foreach ($opt as $k => $v) {
            $this->$k = $v;
        }
        return sha1($this->status . $this->orderid . $this->client_orderid . $this->key);
    }

    function statusSign($opt = array()) {
        foreach ($opt as $k => $v) {
            $this->$k = $v;
        }
        //Тут какая то ерунда, где то что то пеерпутано
        return sha1($this->login . $this->client_orderid . $this->orderid . $this->key);
//		return sha1($this->login.$this->orderid.$this->client_orderid.$this->key);
    }

    
    private function send($method,$endpoint,$data=array()){
    	$arr=array();
    	if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, "{$this->getAddr()}/paynet/api/v2/$method/" . $endpoint);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            $out = curl_exec($curl);
            parse_str($out, $arr);
            curl_close($curl);
        }
        return $arr;
    }
    
    
    function returnRes($opt = array()) {
        foreach ($opt as $k => $v) {
            $this->$k = $v;
        }
        $ENDPOINTID = $this->ep_preauth;
        $control = sha1($this->login.$this->client_orderid.$this->orderid.($this->code*100).'RUB'.$this->key);
        $sendarius = "login={$this->login}&client_orderid={$this->client_orderid}&orderid={$this->orderid}&amount={$this->code}&currency=RUB&comment=CardOkAndRegistred&control=$control";
        $arr = $this->send('return',$ENDPOINTID,$sendarius);
        return $arr;
    }
    
    function saleForm($opt = array()) {

        $this->order_desc = "Оплата договору № {$opt['client_orderid']}.";
        $this->redirect = get_protocol() . "://{$_SERVER['HTTP_HOST']}/cabinet/";
        foreach ($opt as $k => $v) {
            $this->$k = $v;
        }
        $ENDPOINTID = $this->ep_sale;
        $control = sha1($ENDPOINTID . $this->client_orderid . ($this->amount * 100) . $this->getEmail() . $this->key);
        $sendarius=array(
            'login'=>$this->login,
            'client_orderid'=>$this->client_orderid,
            'amount'=>$this->amount,
            'ipaddress'=>$this->ipaddress,
            'phone'=>$this->phone,
            'zip_code'=>$this->zip_code,
            'order_desc'=>$this->order_desc,
            'email'=>$this->getEmail(),
            'country'=>$this->country,
            'city'=>$this->city,
            'address1'=>$this->city,
            'REDIRECT_URL'=>$this->redirect,
            'control'=>$control,
            'currency'=>'RUB',
            'state'=>'',
        );
        if(!empty($this->pay_type)){
            $sendarius['purpose']=$this->pay_type;
        }
        $arr = $this->send('sale-form',$ENDPOINTID,http_build_query($sendarius));
        return $arr;
    }
    
    function preauthForm($opt = array(), $aOptRedirectURL = null) {

        $order_num=$opt['client_orderid'];
        if(!empty($opt['client_order_num'])){
            $order_num=$opt['client_order_num'];
        }
        $this->order_desc = "Регистрация карты по договору № {$order_num}.";
        $this->redirect = get_protocol() . "://{$_SERVER['HTTP_HOST']}/cabinet/paytype/";
        if ($aOptRedirectURL !== null) {
            $this->redirect = get_protocol() . "://{$_SERVER['HTTP_HOST']}/{$aOptRedirectURL}";
        }
        foreach ($opt as $k => $v) {
            $this->$k = $v;
        }
        $ENDPOINTID = $this->ep_preauth;
        $control = sha1($ENDPOINTID . $this->client_orderid . ($this->amount * 100) . $this->getEmail() . $this->key);
        $sendarius = "login={$this->login}&client_orderid=$this->client_orderid&amount=$this->amount" .
                "&ipaddress=$this->ipaddress&phone=$this->phone&zip_code=$this->zip_code" .
                "&order_desc=" . urlencode($this->order_desc) . "&email=" . urlencode($this->getEmail()) .
                "&country=" . urlencode($this->country) . "&city=" . urlencode($this->city) .
                "&address1=" . urlencode($this->city) . "&REDIRECT_URL=" . urlencode($this->redirect) .
                "&control=$control&currency=RUB&state=";
        $arr = $this->send('preauth-form',$ENDPOINTID,$sendarius);
        return $arr;
    }

    
    function getEmail(){
        if(!empty($this->email)){
            return $this->email;
        }
        return Cfg::get('site_email');
    }
    
    /**
     * http://doc.ariuspay.ru/doc/transfer-transactions.htm
     *
     */
    function transferByRef($opt = array()) {
        $this->order_desc = "Выдача кредита по договору № {$opt['client_orderid']}.";
        foreach ($opt as $k => $v) {
            $this->$k = $v;
        }
        $ENDPOINTID = $this->ep_transfer;
        $control = sha1($this->login . $this->client_orderid . $this->cardrefid . ($this->amount*100) .'RUB'. $this->key);
        $sendarius = "login={$this->login}&client_orderid={$this->client_orderid}&destination-card-ref-id={$this->cardrefid}&amount={$this->amount}&currency=RUB&order_desc=" . urlencode($this->order_desc)."&control=$control";
        
        $arr = $this->send('transfer-by-ref',$ENDPOINTID,$sendarius);
        
        return $arr;
    }
    function makeRebill($opt = array()) {
        $this->order_desc="Списание по дог. № {$opt['client_orderid']}.";
        foreach ($opt as $k => $v) {
            $this->$k = $v;
        }
        $ENDPOINTID = $this->ep_rebill;
//        $this->cardrefid=910591;
        
        $control = sha1($this->login . $this->client_orderid . $this->cardrefid . ($this->amount*100) .'RUB'. $this->key);
        
//        $sendarius = "login={$this->login}&client_orderid={$this->client_orderid}&destination-card-ref-id={$this->cardrefid}&amount={$this->amount}&currency=RUB&order_desc=" . urlencode($this->order_desc)."&control=$control";
        
        $sendarius = "login={$this->login}&client_orderid={$this->client_orderid}&cardrefid={$this->cardrefid}&amount=$this->amount&ipaddress={$this->ipaddress}&currency=RUB&order_desc=".urlencode($this->order_desc)."&country=".urlencode($this->country)."&control=$control";
        
        $arr = $this->send('make-rebill',$ENDPOINTID,$sendarius);
        
        return $arr;
    }

    function createCardRef($opt = array()) {
        foreach ($opt as $k => $v) {
            $this->$k = $v;
        }

        $ENDPOINTID = $this->ep_preauth;
        $control = $this->statusSign($opt);
        $sendarius = "login={$this->login}&client_orderid={$this->client_orderid}&orderid={$this->orderid}&control=$control";
        
        $arr = $this->send('create-card-ref',$ENDPOINTID,$sendarius);
        return $arr;
    }

    private $url = 'https://gate.ariuspay.ru';
    private $url_test = 'https://sandbox.ariuspay.ru';

    function getAddr() {
        if ($this->test) {
            return $this->url_test;
        }
        return $this->url;
    }

    function setOpt($opt = array()) {
        
    }

    function getUrl($opt = array()) {
        
    }

    function renderPsForm($opt = array()) {
        
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

}

?>