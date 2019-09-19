<?php

require_once 'NBKI.php';

/**
 * Class NBKIUS Универсальный сарвис
 *
 * @property $url
 */
class NBKIUS extends NBKI {

    //Ответ с анализом социальных связей
    private static $_urlSna = 'https://icrs.nbki.ru/universalService';

    protected function getUrl() {
        return self::$_urlSna;
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

        $universalServiceElement = $xml->createElement('universalService');
        $xml->appendChild($universalServiceElement);

        $productElement = $xml->createElement('product');
        $universalServiceElement->appendChild($productElement);

        $prequestElement = $xml->createElement('prequest');
        $productElement->appendChild($prequestElement);

        $reqElement = $xml->createElement('req');
        $prequestElement->appendChild($reqElement);

        // адрес регистрации
        $AddressReqElement = $xml->createElement('AddressReq');

        $houseNumberElement = $xml->createElement('houseNumber', $this->order['building']);
        $AddressReqElement->appendChild($houseNumberElement);

        $streetElement = $xml->createElement('street', $this->order['street']);
        $AddressReqElement->appendChild($streetElement);

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

        $houseNumberElement = $xml->createElement('houseNumber', $this->order['building_prog']);
        $AddressReq2Element->appendChild($houseNumberElement);
        $streetElement = $xml->createElement('street', $this->order['street_prog']);
        $AddressReq2Element->appendChild($streetElement);

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

        /*    <service>
          <code>FICO2</code>
          </service>
          <service>
          <code>FICO3</code>
          </service>
          <service>
          <code>CHHS</code>
          </service>
         */

//        $serviceElement = $xml->createElement('service');
//        $codeElement = $xml->createElement('code', 'FICO2');
//        $serviceElement->appendChild($codeElement);
//        $universalServiceElement->appendChild($serviceElement);
//        $serviceElement = $xml->createElement('service');
//        $codeElement = $xml->createElement('code', 'FICO3');
//        $serviceElement->appendChild($codeElement);
//        $universalServiceElement->appendChild($serviceElement);
//        $serviceElement = $xml->createElement('service');
//        $codeElement = $xml->createElement('code', 'CHHS');
//        $serviceElement->appendChild($codeElement);
//        $universalServiceElement->appendChild($serviceElement);
//        $serviceElement = $xml->createElement('service');
//        $codeElement = $xml->createElement('code', 'SNA');
//        $serviceElement->appendChild($codeElement);
//        $universalServiceElement->appendChild($serviceElement);


        $serviceElement = $xml->createElement('service');
        $codeElement = $xml->createElement('code', 'SA');
        $serviceElement->appendChild($codeElement);
        $requestElement = $xml->createElement('request');
        $serviceElement->appendChild($requestElement);
        $typeElement = $xml->createElement('type', '2');
        $requestElement->appendChild($typeElement);

        $universalServiceElement->appendChild($serviceElement);

        /* <service>
          <code>SA</code>
          <request>
          <type>2</type>
          </request>
          </service>
         */

        return $xml->saveXML();
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
                    $response = $cCurl->getResponse();
                    //file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/log/" . time() . ".resp", $response);
                    $response = NBKIUS::cds($response);
                    file_put_contents(ROOT . "/log/" . time() . ".resp", $response);
                    $cCurl->setResponse($response);
                })
                ->request()
                ->getResponse();

        //@unlink($fileName);
        file_put_contents($fileName . ".resp", $resp);

        HttpResponse::add($requestId, $resp);
        return $resp;
    }

    // clear digital sign
    static function cds($response) {
        $str = '<?xml version="1.0" encoding="windows-1251"?>';
        //file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/log/" . time() . ".resp", $response);
        $startPos = strpos($response, $str);
        $endPos = strpos($response, "</universalService>"); //{ ‚}
        $l = strlen($response);
        $strLen = $l - $startPos - ($l - ($endPos + strlen("</universalService>")));
        $response = substr($response, $startPos, $strLen);
//        $response = str_replace(chr(4) . chr(130) . chr(3) . chr(232), '', $response);
//        $response = str_replace(chr(4) . chr(130) . chr(3), chr(184), $response);
//
//        $response = str_replace(chr(4) . chr(130) . chr(1) . chr(122), '', $response);
//        $response = str_replace(chr(4) . chr(130) . chr(1), '', $response);


        $response = preg_replace('/' . chr(4) . chr(130) . '.{2}/', '', $response);
        $response = preg_replace('/' . chr(4) . '/', '', $response);
        $response = preg_replace('|</+|', '</', $response);


        //$response=  str_replace(chr(4).chr(226).chr(128).chr(154).chr(1).chr(122), '', $response);

        $response = iconv('windows-1251', 'utf-8', $response);
        return $response;
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

//        if (isset($xml->arr['preply'][0]['err'][0]['ctErr']) && count($xml->arr['preply'][0]['err'][0]['ctErr'])) {
//            foreach ($xml->arr['preply'][0]['err'][0]['ctErr'] as $er) {
//                $this->errors[$er['Code'][0]] = $er['Text'][0];
//            }
//        }

        if (!$this->hasErrors()) {
            if (db()->one("SELECT * FROM nbki_credit_history WHERE order_id=:order_id", array(':order_id' => $this->orderId))) {
//                db()->query("UPDATE nbki_credit_history SET(check = 1,xml=:xml) WHERE order_id=:order_id", array(':xml' => $xmlResponse, ':order_id' => $this->orderId));

                DB::update('nbki_credit_history', array('check' => 1, 'xml' => $xmlResponse), "order_id={$this->orderId}");
            } else {
                db()->query("INSERT INTO nbki_credit_history (`check`,`xml`,`order_id`) VALUES (1,:xml,:order_id)", array(':xml' => $xmlResponse, ':order_id' => $this->orderId));
            }
        }

        return true;
    }

}
