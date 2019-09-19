<?

class FinKarta {

    private function __construct() {
        if ($cfg = Cfg::get('finkarta')) {
            foreach ($cfg as $k => $v) {
                $this->$k = $v;
            }
        }
    }

    private static $instance = array();

    /**
     * $instance
     *
     * @return FinKarta
     */
    static function getInstance() {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private $url = 'https://request.f-karta.ru/fincard-0.1/main/xmlRequest';
//    private $url = 'https://request.f-karta.ru/';

    private $debug = 1;
    private $no_hash = false;

    function hash($str) {
        if ($this->no_hash) {
            return $str;
        }
        if (DIRECTORY_SEPARATOR == '/') {//unix
            exec("/var/scripts/stribog/stribog -s \"{$str}\"", $out);
        } else {//win
            exec("z:\\stribog\\stribog.exe -s \"{$str}\"", $out);
        }
        return implode('', $out);
    }

    function getUrl() {
        return $this->url;
    }

    function pack_request($xml) {
        $t = time();
        $req_name = "fk_" . $t . "_req.xml"; // имя файла
        $req_path = ROOT . "/log/$req_name"; // имя файла
        file_put_contents($req_path, $xml);
        $zip = new ZipArchive(); // подгружаем библиотеку zip

        $zip_name = ROOT . "/log/fk_" . $t . "_req.zip"; // имя файла
        if ($zip->open($zip_name, ZIPARCHIVE::CREATE) !== TRUE) {
            echo "* Sorry ZIP creation failed at this time";
            exit;
        } else {

            $zip->addFile($req_path, $req_name);

            // добавляем файлы в zip архив
            $zip->close();
            unlink($req_path);
            return $zip_name;
        }
        exit;
    }

    private $ssl_cert_path = 'storage/cert/finkarta/afcom_cert.pem';
    private $ssl_key_path = 'storage/cert/finkarta/afcom_priv.key';

    function send_request($xml) {
        if (!$req_name = $this->pack_request($xml)) {
            return false;
        }

        $url = $this->getUrl();
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSLVERSION, 4); // Force SSLv3 to fix Unknown SSL Protocol error
        curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'SSLv3');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HEADER, 0);

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_SSLCERT, ROOT . "/{$this->ssl_cert_path}");
        curl_setopt($ch, CURLOPT_SSLKEY, ROOT . "/{$this->ssl_key_path}");

        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, "");
        curl_setopt($ch, CURLOPT_SSLKEYPASSWD, "");

        curl_setopt($ch, CURLOPT_POSTFIELDS, array('file' => '@' . $req_name));

        $response = curl_exec($ch);
        //unlink($req_name);

        $e = curl_error($ch);

        curl_close($ch);

        $out_zip = $req_name;
        file_put_contents($out_zip, $response);

        $zip = new ZipArchive;
        if ($zip->open($out_zip) === TRUE) {
            $out = $zip->getFromIndex(0);
            $zip->close();
            unlink($out_zip);
            return $out;
            // удача
        } else {
            // неудача
        }
    }

    function getXmlRequest($data = array()) {
        //print_r($rs);exit;
        $pasport = $data['pasport'];
        $pasport_arr = explode('-', $pasport);
        $gender = "1";
        if ($data['sex'] == "female") {
            $gender = "2";
        }
        $xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
<request id="1" date="' . date('Y-m-d') . 'T' . date('H:i:s') . '" request_type="1">
	<person_data>
		<person record_id="' . $data['u_id'] . '" 
			type="1" 
			hash_last_name="' . $this->hash($data['surname']) . '" 
			hash_first_name="' . $this->hash($data['name']) . '" 
			hash_middle_name="' . $this->hash($data['second_name']) . '" 
			hash_birth_date="' . $this->hash($data['birthday']) . '" 
			hash_birth_place="' . $this->hash('') . '"
			sex="' . $gender . '"
			reason_request="2">
			<person_docs>
				<doc record_id="' . $data['u_id'] . '" 
					doc_type="1" 
					hash_doc_serial="' . $this->hash($pasport_arr[0]) . '"
					hash_doc_number="' . $this->hash($pasport_arr[1]) . '"
					hash_doc_issue_date="' . $this->hash($data['pasportdate']) . '"
					doc_issue_auth="' . $data['pasportissued'] . '" />
			</person_docs>';
        if($data['card-ref-id']){
            $xmlRequest .= '<person_cards>
				<card record_id="' . $data['c_id'] . '"
					card_number="' . preg_replace('/^(\d{6})\d{6}/', '\1XXXXXX', $data['number']) . '"
					card_exp_date="' . date('Y-m-d', strtotime($data['year_end'] . '-' . $data['month_end'] . '-01')) . '"
					card_ref_id="' . $data['card-ref-id'] . '" />
			</person_cards>';
        }else{
            $xmlRequest .= '<person_cards>
				<card record_id="' . $data['c_id'] . '"
					card_number="' . preg_replace('/^(\d{6})\d{6}/', '\1XXXXXX', $data['number']) . '"
					card_exp_date="' . date('Y-m-d', strtotime($data['year_end'] . '-' . $data['month_end'] . '-'.  date('t',  strtotime($data['year_end'] . '-' . $data['month_end'].'-01')))) . '"
					 />
			</person_cards>';
        }
			
		$xmlRequest .= '</person>
	</person_data>
</request>'; //$data['card-ref-id']
        return $xmlRequest;
    }

    function getOrderCard($order_id){
        $q = "SELECT u.surname, u.name, u.second_name, DATE_FORMAT(u.birthday,'%Y-%m-%d') as birthday, u.pasport, 
                    DATE_FORMAT(u.pasportdate,'%Y-%m-%d') as pasportdate, DATE_FORMAT(o.`date`,'%Y-%m-%d') as date, o.sum_request, u.phone, u.city,
                    u.sex, u.pasportissued , o.order_num,
                    u.id AS u_id,
                    c.id AS c_id,
                    c.number,
                    c.`card-ref-id`,
                    c.month_end,
                    c.year_end
                    FROM orders o 
                    INNER JOIN users u ON (o.user_id = u.id) 
                    INNER JOIN cards AS c ON (u.id = c.user_id) 
                    
                    WHERE o.id = $order_id ORDER BY c.id DESC LIMIT 1";
//INNER JOIN cards AS c ON (o.id = c.order_id)
        $rs = DB::select($q);
        if ($rs->next()) {
            return $rs->getRow();
        }
        
    }
    function getRequest($order_id = 0) {
        $xmlRequest = "";

        if ($data=$this->getOrderCard($order_id)) {

            $xmlRequest = $this->getXmlRequest($data);
            if ($this->debug) {
                $log_path = ROOT . "/log/finkarta";
                if (!file_exists($log_path)) {
                    mkdir($log_path, 0777, true);
                }
                $this->no_hash = true;
                $log = $xmlRequest;
                file_put_contents($log_path . "/{$order_id}_" . date('YmdHis') . ".xml", $log);
            }
            if ($xmlResponse = $this->send_request($xmlRequest)) {
                $data = array(
                    'check' => 1,
                    'xml' => $xmlResponse,
                    'date' => date('Y-m-d H:i:s'),
                );
                $cond = "order_id={$order_id}";
                $rs = DB::select("SELECT * FROM fk_credit_history WHERE $cond");
                if ($rs->next()) {
                    DB::update('fk_credit_history', $data, $cond);
                } else {
                    $data['order_id'] = $order_id;
                    DB::insert('fk_credit_history', $data);
                }
                return $xmlResponse;
            }
        }
    }

    static function send($order_id) {
        return self::getInstance()->getRequest($order_id);
    }
    static function exists($order_id) {
        return self::getInstance()->getOrderCard($order_id);
    }

    static function getRespDataRef() {
        $ref = array(
            'sa' => 'Наличие клиента в системе',
            'cardfound' => 'Карта найдена в системе',
            'countissuedfor30days' => 'Количество транзакций по выдаче займов МФО за последние 30 дней',
            'countissuedfor90days' => 'Количество транзакций по выдаче займов МФО за последние 90 дней',
            'countissuedfor180days' => 'Количество транзакций по выдаче займов МФО за последние 180 дней',
            'firsttransferfrommfodate' => 'Дата первой выдачи от МФО',
            'lastdischargeamount' => 'Сумма последней  попытки погашения займа клиентом. Rule_date - Дата последней  попытки погашения займа клиентом.',
            'lastrecurrentamount' => 'Сумма последней  попытки списания средств с карты клиента. Rule_date - Дата последней попытки списания средств с карты клиента.',
            'lastsuccessfuldischargeamount' => 'Сумма последнего «успешного» погашения платежа по займу клиентом. Rule_date - Дата последнего «успешного» погашения платежа по займу клиентом.',
            'lastsuccessfulrecurrentamount' => 'Сумма последней  «успешной» попытки списания средств с карты клиента. Rule_date - Дата последней  «успешной» попытки списания средств с карты клиента.',
            'lasttransferfrommfodate' => 'Дата последней выдачи МФО',
            'mfocountfor30days' => 'Число уникальных МФО, проводящих транзакции за последние 30 дней',
            'mfocountfor90days' => 'Число уникальных МФО, проводящих транзакции за последние 90 дней',
            'mfocountfor180days' => 'Число уникальных МФО, проводящих транзакции за последние 180 дней',
            'totaldischargeamount' => 'Сумма всех погашенных средств',
            'totalissuedamount' => 'Cумма всех выданных средств',
            'totalrecurrentamount' => 'Сумма  попыток списания средств с карты клиента по инициативе МФО',
            'transfersfrommfo' => 'Признак выдачи займов МФО',
            'mfoissuedfor30days' => 'Количество МФО, выдававших займ за последние 30 дней',
            'mfoissuedfor90days' => 'Количество МФО, выдававших займ за последние 90 дней',
            'mfoissuedfor180days' => 'Количество МФО, выдававших займ за последние 180 дней',
            'cntcardusers' => 'Кол-во заемщиков по данной карте',
        );
       

        $ref = array(
            'A1' => 'Наличие клиента в системе',
            'M1' => 'Карта найдена в системе',
            'M2' => 'Количество транзакций по выдаче займов МФО за последние 30 дней',
            'M3' => 'Количество транзакций по выдаче займов МФО за последние 90 дней',
            'M4' => 'Количество транзакций по выдаче займов МФО за последние 180 дней',
            'M5' => 'Дата первой выдачи от МФО',
            'M6' => 'Сумма последней  попытки погашения займа клиентом. Rule_date - Дата последней  попытки погашения займа клиентом.',
            'M7' => 'Сумма последней  попытки списания средств с карты клиента. Rule_date - Дата последней попытки списания средств с карты клиента.',
            'M8' => 'Сумма последнего «успешного» погашения платежа по займу клиентом. Rule_date - Дата последнего «успешного» погашения платежа по займу клиентом.',
            'M9' => 'Сумма последней  «успешной» попытки списания средств с карты клиента. Rule_date - Дата последней  «успешной» попытки списания средств с карты клиента.',
            'M10' => 'Дата последней выдачи МФО',
            'M11' => 'Число уникальных МФО, проводящих транзакции за последние 30 дней',
            'M12' => 'Число уникальных МФО, проводящих транзакции за последние 90 дней',
            'M13' => 'Число уникальных МФО, проводящих транзакции за последние 180 дней',
            'M14' => 'Сумма всех погашенных средств',
            'M15' => 'Cумма всех выданных средств',
            'M16' => 'Сумма  попыток списания средств с карты клиента по инициативе МФО',
            'M17' => 'Признак выдачи займов МФО',
            'M18' => 'Количество МФО, выдававших займ за последние 30 дней',
            'M19' => 'Количество МФО, выдававших займ за последние 90 дней',
            'M20' => 'Количество МФО, выдававших займ за последние 180 дней',
            'M21' => 'Кол-во заемщиков по данной карте',
        );
        return $ref;
    }

}
