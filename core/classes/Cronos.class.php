<?

// /opt/cprocsp/bin/amd64/curl --data "Type=Login&Login=testAutomatUser&Password=testAutomatUser" https://ssl.croinform.ru:450/api.test
class Cronos {

    private function __construct() {
        $cfg = Cfg::get('cronos');
        foreach ($cfg as $k => $v) {
            $this->$k = $v;
        }
    }

    private static $instance = array();

    /**
     * $instance
     *
     * @return Cronos
     */
    static function getInstance() {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private $useProxy = false;
    private $useGost = true;
    private $test_url = 'https://ssl.croinform.ru:450/api.test';
    private $url = 'https://ssl.croinform.ru:450/api';

    function getUrl() {
        if ($this->useProxy) {
            return $this->getProxyUrl();
        }
        if ($this->test) {
            return $this->test_url;
        }
        return $this->url;
    }

    private $proxyServerUrl = '';

    function getProxyUrl() {
        $protocol = 'http';
        if (!preg_match('/^(127|192)/', $_SERVER['REMOTE_ADDR'])) {
            $protocol = 'https';
        }

        $server = "$protocol://{$_SERVER['HTTP_HOST']}";
        if ($this->proxyServerUrl) {
            $server = $this->proxyServerUrl;
        }

        $url = "$server/Cronos/cronos_proxy.php";
        if ($this->test) {
            return "$url?test";
        }
        return "$url";
    }

    private $cmd_path = "/opt/cprocsp/bin/amd64/curl";

    private function requestDataGost($data = array()) {
        $cmd=$this->cmd_path;
        $url=$this->getUrl();
        $fname='';
        if (!empty($data)) {

            $tmp_arr = array();
            foreach ($data as $k => $v) {
                $tmp_arr[] = $k . "=" . $v;
            }
            $FILE_DATA = implode('&', $tmp_arr);
            $dir=$_SERVER['DOCUMENT_ROOT'].'/log';
            if(!file_exists($dir)){
                $dir=dirname(__FILE__);
            }
            
            $fname = $dir . "/" . time() . "_" . md5($FILE_DATA);
            file_put_contents($fname, $FILE_DATA);
            $cmd.= " -d @$fname";
        }

        $cmd.=" $url";
        exec($cmd, $output);
        if(file_exists($fname)){
            unlink($fname);
        }
        return implode("\n", $output);
    }

    private function requestData($data = array()) {

        if ($this->useGost) {
            return $this->requestDataGost($data);
        }

        $url = $this->getUrl();
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);

//        Log::write(print_r($data,true));
//        Log::write($response);
        curl_close($ch);
        return $response;
    }

    static function send($order_id = 0) {
        $instance = Cronos::getInstance();
        $instance->cronos_request($order_id);
    }

    private $WorkingDirectory = null;

    function auth() {
        if (empty($this->WorkingDirectory)) {
            $out = $this->requestData(array('Type' => 'Login', 'Login' => $this->login, 'Password' => $this->password));
            $out = simplexml_load_string($out);
            $this->WorkingDirectory = (string) $out->WorkingDirectory;
        }
        return $this->WorkingDirectory;
    }

    private function requestFL($data = array()) {
        $data['WorkingDirectory'] = $this->auth();
        $data['Type'] = 'Request';
        $data['Event'] = '3';
//        $data['GIBDD']='1';
//        $data['CASBR']='1';
//        $data['Raiting']='1';
//        $data['Raiting_2']='1';
//        $data['AFF']='1';//Необходимость обращения в сервис «Аффилированность»
//        $data['CKKI']='1';//Необходимость обращения в сервис «Центральный каталог кредитных историй (ЦККИ)
//        $data['SVI']='1';//Необходимость обращения в Сервис взаимного информирования:
        $data['Exp'] = '1'; //Необходимость обращения в сервис «Экспертиза заемщика»
//        $data['ExtSource']='1';//Необходимость обращения в сервис «Внешние источники»

        $out = $this->requestData($data);



        $out = simplexml_load_string($out);

        $xml = '';
        if (!empty($out->RequestNumber)) {
            //Type=Answer&WorkingDirectory=U688517272&RequestNumber=2FL123456&TypeAnswer=HV
            $data = array(
                'Type' => 'Answer',
                'TypeAnswer' => 'HV',
                'WorkingDirectory' => $this->auth(),
                'RequestNumber' => (string) $out->RequestNumber,
            );

            while (true) {
                $xml_out = $this->requestData($data);
                $out = simplexml_load_string($xml_out);
                if ((int) $out->StatusRequest == 3) {//ищется, подожите!
                    sleep(10);
                    continue;
                }
                if ((int) $out->StatusRequest == 1) {//Ура нашлось
                    $xml = $xml_out;
                }

                break;
            }
        }


        //Type=Logout&WorkingDirectory=U688517272
        $data = array(
            'Type' => 'Logout',
            'WorkingDirectory' => $this->auth(),
        );
        $out = $this->requestData($data);
        $this->WorkingDirectory = '';
        return $xml;
    }

    function cronos_request($order_id = 0) {
//        Log::write($order_id);
        $data = array();

        $fld = "u.surname, u.name, u.second_name, DATE_FORMAT(u.birthday,'%Y-%m-%d') as birthday, u.pasport, 
                    DATE_FORMAT(u.pasportdate,'%Y-%m-%d') as pasportdate, DATE_FORMAT(o.`date`,'%Y-%m-%d') as date, o.sum_request, u.phone, u.city,
                    u.sex,u.pasportissued,
                    prop_okato,city,street,building,building_add,flat_add,home_phone,
                    prog_okato,city_prog,street_prog,building_prog,building_add_prog,flat_add_prog
        ";
        if ($order_id) {
            $rs = DB::select("SELECT $fld
                    FROM orders o INNER JOIN users u ON (o.user_id = u.id) WHERE o.id = " . $order_id);
        }

        if ($rs->next()) {
            $data['SurName'] = $rs->get('surname');
            $data['FirstName'] = $rs->get('name');
            $data['MiddleName'] = $rs->get('second_name');
            $data['DateOfBirth'] = dte($rs->get('birthday'));
            $pasport = $rs->get('pasport');
            $pasport_arr = explode('-', $pasport);

            $data['Seria'] = $pasport_arr[0];
            $data['Number'] = $pasport_arr[1];

            $data['RegionExp'] = substr($rs->get('prop_okato'), 0, 2);
            $data['CityExp'] = $rs->get('city');
            $data['StreetExp'] = $rs->get('street');
            $data['HouseExp'] = $rs->get('building');
            $data['BuildExp'] = $rs->get('building_add');
//            $data['BuildingExp']=  $rs->get('building_add');
            $data['FlatExp'] = $rs->get('flat_add');
            $data['PhoneExp'] = $rs->get('home_phone');

            $data['RegionExpTmp'] = substr($rs->get('prog_okato'), 0, 2);
            $data['CityExpTmp'] = $rs->get('city_prog');
            $data['StreetExpTmp'] = $rs->get('street_prog');
            $data['HouseExpTmp'] = $rs->get('building_prog');
            $data['BuildExpTmp'] = $rs->get('building_add_prog');
//            $data['BuildingExp']=  $rs->get('building_add');
            $data['FlatExpTmp'] = $rs->get('flat_add_prog');


            //CKKI=1
            $data['IssueDate'] = dte($rs->get('pasportdate'));
            //SVI=1
            $data['InfoType'] = 1; //кредит
//            if ($city) {
//                $city = "Москва";
//            }
            $bsend = true;
        }

        if ($bsend) {
            $xmlResponse = $this->requestFL($data);

            if ($xmlResponse) {
                $data = array(
                    'check' => 1,
                    'xml' => $xmlResponse,
                );

                $cond = " order_id={$order_id}";
                
                $rs = DB::select("SELECT * FROM cronos_history WHERE $cond");
                if ($rs->next()) {
                    DB::update('cronos_history', $data, $cond);
                } else {
                    $data['order_id'] = $order_id;
                    DB::insert('cronos_history', $data);
                }
            }
        }
    }

}

?>