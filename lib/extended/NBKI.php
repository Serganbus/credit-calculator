<?php

/**
 * Class NBKI
 *
 * @property $url
 */
class NBKI extends Object {

    private static $_testUrl = 'http://icrs.demo.nbki.ru/products/B2BRequestServlet';
    private static $_url = 'https://icrs.nbki.ru/products/B2BRequestServlet';
    private $useGost = true;
    protected $MemberCode = '';
    protected $UserID = '';
    protected $Password = '';
    protected $ExpUserID = '';
    protected $ExpPassword = '';
    protected $no_take_loan = '';
    protected $test = false;
    protected $curlPath = '/opt/cprocsp/bin/amd64/curl';
    protected $logPath = '/log';
    public $errors = array();
    public $orderId = null;

    /** @var null|array */
    protected $order = null;

    public function __construct($orderId) {
        $cfg = Cfg::get('nbci');
        if (!is_array($cfg))
            $cfg = array();
        $cfg['orderId'] = $orderId;
        $this->orderId = $orderId;
        parent::__construct($cfg);
    }

    public function init() {
        parent::init();

        $this->logPath = ROOT . $this->logPath;

        if (!file_exists($this->logPath) && !is_dir($this->logPath))
            mkdir($this->logPath, 0775);

        $this->order = db()->one("SELECT u.surname, u.name, u.second_name, DATE_FORMAT(u.birthday,'%Y-%m-%d') AS birthday, u.pasport,
                    DATE_FORMAT(u.pasportdate,'%Y-%m-%d') AS pasportdate, DATE_FORMAT(o.`date`,'%Y-%m-%d') AS date, o.sum_request, u.phone, u.city, u.city_prog,
                    u.sex,u.pasportissued , o.order_num, u.prop_zip, u.prog_zip, u.street, u.street_prog, u.building, u.building_prog,
                    u.flat_add, u.flat_add_prog
                    FROM orders o INNER JOIN users u ON (o.user_id = u.id) WHERE o.id =:orderId", array(':orderId' => $this->orderId));
    }

    protected function getUrl() {
        if ($this->test) {
            return self::$_testUrl;
        }
        return self::$_url;
    }

    /**
     * Генерация XML с запросом
     * @return string
     */
    protected function generateXML() {
        list($pasportSerial, $pasportNum) = explode('-', $this->order['pasport']);

        $gender = $this->order['sex'] === "female" ? 2 : 1;

        if (!$this->order['city']) {
            $this->order['city'] = "Москва";
        }

        $xml = new DOMDocument('1.0', 'UTF-8');

        $productElement = $xml->createElement('product');
        $xml->appendChild($productElement);

        $prequestElement = $xml->createElement('prequest');
        $productElement->appendChild($prequestElement);

        $reqElement = $xml->createElement('req');
        $prequestElement->appendChild($reqElement);

        // адрес регистрации
        $AddressReqElement = $xml->createElement('AddressReq');
        $streetElement = $xml->createElement('street', $this->order['street']);
        $AddressReqElement->appendChild($streetElement);
        $houseNumberElement = $xml->createElement('houseNumber', $this->order['building']);
        $AddressReqElement->appendChild($houseNumberElement);
        $apartmentElement = $xml->createElement('apartment', $this->order['flat_add']);
        $AddressReqElement->appendChild($apartmentElement);
        $cityElement = $xml->createElement('city', $this->order['city']);
        $AddressReqElement->appendChild($cityElement);
        $prop_zip = empty($this->order['prop_zip']) || strlen($this->order['prop_zip']) < 6 ? '000000' : $this->order['prop_zip'];
        $postalElement = $xml->createElement('postal', $prop_zip);
        $AddressReqElement->appendChild($postalElement);
        $addressTypeElement = $xml->createElement('addressType', 1);
        $AddressReqElement->appendChild($addressTypeElement);

        $reqElement->appendChild($AddressReqElement);

        // адрес фактического местожительства
        $AddressReq2Element = $xml->createElement('AddressReq');
        $streetElement = $xml->createElement('street', $this->order['street_prog']);
        $AddressReq2Element->appendChild($streetElement);
        $houseNumberElement = $xml->createElement('houseNumber', $this->order['building_prog']);
        $AddressReq2Element->appendChild($houseNumberElement);
        $apartmentElement = $xml->createElement('apartment', $this->order['flat_add_prog']);
        $AddressReq2Element->appendChild($apartmentElement);
        $cityElement = $xml->createElement('city', $this->order['city_prog']);
        $AddressReq2Element->appendChild($cityElement);
        $prog_zip = empty($this->order['prog_zip']) || strlen($this->order['prog_zip']) < 6 ? '000000' : $this->order['prog_zip'];
        $postalElement = $xml->createElement('postal', $prog_zip);
        $AddressReq2Element->appendChild($postalElement);
        $addressTypeElement = $xml->createElement('addressType', 2);
        $AddressReq2Element->appendChild($addressTypeElement);

        $reqElement->appendChild($AddressReq2Element);

        $IdReqElement = $xml->createElement('IdReq');
        $idNumElement = $xml->createElement('idNum', $pasportNum);
        $IdReqElement->appendChild($idNumElement);
        $idTypeElement = $xml->createElement('idType', 21);
        $IdReqElement->appendChild($idTypeElement);
        $seriesNumberElement = $xml->createElement('seriesNumber', $pasportSerial);
        $IdReqElement->appendChild($seriesNumberElement);
        $issueCountryElement = $xml->createElement('issueCountry', 'Москва');
        $IdReqElement->appendChild($issueCountryElement);
        $issueDateElement = $xml->createElement('issueDate', $this->order['pasportdate']);
        $IdReqElement->appendChild($issueDateElement);
        $issueAuthorityElement = $xml->createElement('issueAuthority', $this->order['pasportissued']);
        $IdReqElement->appendChild($issueAuthorityElement);

        $reqElement->appendChild($IdReqElement);

        $InquiryReqElement = $xml->createElement('InquiryReq');
        $inqPurposeElement = $xml->createElement('inqPurpose', 16);
        $InquiryReqElement->appendChild($inqPurposeElement);
        $inqAmountElement = $xml->createElement('inqAmount', $this->order['sum_request']);
        $InquiryReqElement->appendChild($inqAmountElement);
        $currencyCodeElement = $xml->createElement('currencyCode', 'RUB');
        $InquiryReqElement->appendChild($currencyCodeElement);

        $reqElement->appendChild($InquiryReqElement);

        $PersonReqElement = $xml->createElement('PersonReq');
        $name1Element = $xml->createElement('name1', $this->order['surname']);
        $PersonReqElement->appendChild($name1Element);
        $firstElement = $xml->createElement('first', $this->order['name']);
        $PersonReqElement->appendChild($firstElement);
        $paternalElement = $xml->createElement('paternal', $this->order['second_name']);
        $PersonReqElement->appendChild($paternalElement);
        $genderElement = $xml->createElement('gender', $gender);
        $PersonReqElement->appendChild($genderElement);
        $birthDtElement = $xml->createElement('birthDt', $this->order['birthday']);
        $PersonReqElement->appendChild($birthDtElement);
        $placeOfBirthElement = $xml->createElement('placeOfBirth', '-');
        $PersonReqElement->appendChild($placeOfBirthElement);

        $reqElement->appendChild($PersonReqElement);

        $RequestorReqElement = $xml->createElement('RequestorReq');
        $MemberCodeElement = $xml->createElement('MemberCode', $this->MemberCode);
        $RequestorReqElement->appendChild($MemberCodeElement);
        $UserIDElement = $xml->createElement('UserID', $this->UserID);
        $RequestorReqElement->appendChild($UserIDElement);
        $PasswordElement = $xml->createElement('Password', $this->Password);
        $RequestorReqElement->appendChild($PasswordElement);

        $reqElement->appendChild($RequestorReqElement);

        $RefReqElement = $xml->createElement('RefReq');
        $productElement = $xml->createElement('product', 'CHST');
        $RefReqElement->appendChild($productElement);

        $reqElement->appendChild($RefReqElement);

        $IOTypeElement = $xml->createElement('IOType', 'B2B');
        $reqElement->appendChild($IOTypeElement);

        $OutputFormatElement = $xml->createElement('OutputFormat', 'XML');
        $reqElement->appendChild($OutputFormatElement);

        $langElement = $xml->createElement('lang', 'ru');
        $reqElement->appendChild($langElement);

        return $xml->saveXML();
    }

    public function send() {
        if ($this->order === null)
            return false;

        // Забьём типа отправляется запрос
        if (db()->one("SELECT * FROM nbki_credit_history WHERE order_id=:order_id", array(':order_id' => $this->orderId))) {
            //db()->query("UPDATE nbki_credit_history SET(check = 1,xml=:xml) WHERE order_id=:order_id", array(':xml' => $xmlResponse, ':order_id' => $this->orderId));
        } else {
            db()->query("INSERT INTO nbki_credit_history (`check`,`order_id`) VALUES (0,:order_id)", array(':order_id' => $this->orderId));
        }

        $xml = $this->generateXML();

        $xmlResponse = $this->request($xml);
        DB::delete('nbki_credit_history', "order_id={$this->orderId} AND `check`=0");
        if (!$xmlResponse)
            return false;


        $str = '<?xml version="1.0" encoding="windows-1251"?>';
        $startPos = strpos($xmlResponse, $str) + 1;
        $l = strlen($xmlResponse);
        $xmlTxt = substr($xmlResponse, $startPos + strlen($str), $l - strlen($str));

        if (!empty($xmlTxt)) {
            try {
                $xml = new Xml2Array($xmlTxt, null);
            } catch (CommonException $e) {
                
            }
        }

        if (!is_array($xml->arr))
            throw new Exception('Ошибка парсинга XML');

        if (isset($xml->arr['preply'][0]['err'][0]['ctErr']) && count($xml->arr['preply'][0]['err'][0]['ctErr'])) {
            foreach ($xml->arr['preply'][0]['err'][0]['ctErr'] as $er) {
                $this->errors[$er['Code'][0]] = $er['Text'][0];
            }
        }

        if (!$this->hasErrors()) {
            if (db()->one("SELECT * FROM nbki_credit_history WHERE order_id=:order_id", array(':order_id' => $this->orderId))) {
				DB::update('nbki_credit_history',array('check'=>1,'xml' => $xmlResponse),"order_id={$this->orderId}");
                //db()->query("UPDATE nbki_credit_history SET check = 1, xml=:xml WHERE order_id=:order_id", array('xml' => $xmlResponse, 'order_id' => $this->orderId));
            } else {
                db()->query("INSERT INTO nbki_credit_history (`check`,`xml`,`order_id`) VALUES (1,:xml,:order_id)", array(':xml' => $xmlResponse, ':order_id' => $this->orderId));
            }
        }
        return true;
    }

    public function hasErrors() {
        return count($this->errors);
    }

    protected function requestDataGost($data) {
        $fileName = $this->logPath . "/" . time() . "_" . md5($data) . ".nbci";

        file_put_contents($fileName, $data);

        $curl = new CustomCurl($this->getUrl());

        $requestId = HttpRequest::add('nbci', $data, $this->orderId, 'POST', $this->getUrl());

        $resp = $curl
                ->setCurl($this->curlPath)
                ->setHeaders(array(
                    'Content-Type' => 'text/xml',
                ))
                ->sendFile($fileName)
                ->complete(
                        // clear digital sign
                        function ($cCurl) {
                    /** @var CustomCurl $cCurl */
                    $str = '<?xml version="1.0" encoding="windows-1251"?>';
                    $response = $cCurl->getResponse();
                    $startPos = strpos($response, $str);
//					$startPos += strlen($str) + 1;

                    $endPos = strpos($response, "</product>" . chr(160) . chr(130)); //{ ‚}
                    $l = strlen($response);

                    $strLen = $l - $startPos - ($l - ($endPos + strlen("</product>")));
                    $response = substr($response, $startPos, $strLen);
                    $response = iconv('windows-1251', 'utf-8', $response);

                    $cCurl->setResponse($response);
                })
                ->request()
                ->getResponse();

        @unlink($fileName);
//                file_put_contents($fileName.".resp", $resp);

        HttpResponse::add($requestId, $resp);


        return $resp;
    }

    protected function request($xml) {
        if ($this->useGost) {
            return $this->requestDataGost($xml);
        } else {
            $url = $this->getUrl();
            $ch = curl_init($url);
            $headers = array(
                'Content-Type' => 'text/xml'
            );

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
        }

        $str = '<?xml version="1.0" encoding="windows-1251"?>';
        $start_pos = strpos($response, $str);
        $start_pos += strlen($str) + 1;
        $end_pos = strpos($response, "</product>" . chr(160) . chr(130)); //{ ‚}
        $len = strlen($response);
        $strLen = $len - $start_pos - ($len - ($end_pos + 10));
        $response = substr($response, $start_pos, $strLen);
        $response = iconv('windows-1251', 'utf-8', $response);

        return $response;
    }

    /**
     * 
     * @param int $order_id
     * @return \self|\NBKIUS
     */
    static function getNBKI($order_id) {
        $cfg = Cfg::get('nbci');
        if (!empty($cfg['US'])) {
            require_once ROOT . '/admin/lib/extended/NBKIUS.php';
            return new NBKIUS($order_id);
        } else {
            return new self($order_id);
        }
    }

}
