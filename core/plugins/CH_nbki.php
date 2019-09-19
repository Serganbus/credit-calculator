<?php

/**
 * Class CH_nbki
 * НБКИ
 */
class CH_nbki extends CH implements iCH {

    public static $status_array = [
        '0' => 'открыт',
        '12' => 'под залогом',
        '13' => 'закрыт',
        '14' => 'закрыт',
        '21' => 'оспаривается',
        '52' => 'просрочен',
        '61' => 'мошенничество',
        '70' => 'представление данных остановлено',
        '85' => 'обязательный платеж',
        '90' => 'списан'
    ];

    private function dateFormat($str) {
        $result = '';
        if ($str != '') {
            $date = explode('-', $str);
            $result = $date[2] . '.' . $date[1] . '.' . $date[0];
        }
        return $result;
    }

    private function getDate($str) {
        $date_mas = explode('+', $str);
        return $this->dateFormat($date_mas[0]);
    }

    function newCH($order_id) {
        $result = array('result' => false, 'desc' => '');
        $row = db()->query("SELECT * FROM nbki_credit_history WHERE order_id=:order_id", [':order_id' => $order_id]);

        if (!$row) {
            $result['result'] = db()->query("INSERT INTO nbki_credit_history (date, order_id) VALUES(:date, :orderId)", [':date' => date('Y-m-d H:i:s'), ':orderId' => $order_id]);
        } else {
            $result['desc'] = 'Запрос на получение КИ уже был сформирован';
        }

        return $result;
    }

    function getStatus($order_id) {
        $check = db()->scalar("SELECT `check` FROM nbki_credit_history WHERE order_id=:order_id", [':order_id' => $order_id]);

        if (!$check) {
            $status = 0;
        } elseif ($check == '0') {
            $status = 1;
        } else {
            $status = 2;
        }
        return $status;
    }

    static function getSARef($ref_key, $value) {
        return $value;
    }

    private function parceSA($input_xml) {
        $out = array();
        $xml = $this->findServiceByCode($input_xml, 'SA');
        $xml = $xml->reply->sa_response;
        for ($i = 0; $i < 51; $i++) {
            if ($xml->{"sa_" . $i}) {
                $out[$i] = (string)$xml->{"sa_" . $i};
            }
        }
        return $out;
    }
    private function parceSALink($input_xml) {
        $xml = $this->findServiceByCode($input_xml, 'SA');
        return (string)$xml->reply->sa_response->sl_link;
    }

    private function findServiceByCode($input_xml, $code) {
        foreach ($input_xml->service as $node) {
            if ($node->code == $code) {//
                return $node;
            }
        }
    }

    function getCH($order) {
        $xmlTxt = db()->scalar("SELECT `xml` FROM nbki_credit_history WHERE order_id=:order_id", [':order_id' => $order]);
        $input_xml = new SimpleXMLElement(iconv('UTF-8', 'windows-1251', $xmlTxt));
        $ch_new = $input_xml;
        if (!empty($ch_new->product)) {
            $ch_new = $ch_new->product;
            $xml['SA'] = $this->parceSA($input_xml);
            $xml['SA_SL_LINK'] = $this->parceSALink($input_xml);
        }
        if (isset($ch_new->preply->err)) {
            $xml['error'] = 1;
            return $xml;
        }
        $report = $ch_new->preply->report;
        $personal_reply_xml = $report->PersonReply;
        $personal_reply = is_array($personal_reply_xml) ? $personal_reply_xml[0] : $personal_reply_xml;
        $xml['surname'] = (string) $personal_reply->name1;
        $xml['name'] = (string) $personal_reply->first;
        $xml['second_name'] = (string) $personal_reply->paternal;
        $xml['birth_day'] = $this->getDate((string) $personal_reply->birthDt);
        $xml['birth_place'] = (string) $personal_reply->placeOfBirth;

        // Идентификация
        $IdReply = array();
        foreach ($report->IdReply as $idr) {
            $IdReply[] = $idr;
        }
        for ($i = 0; $i < count($IdReply); $i++) {
            $seriesNumber = '';
            if (!is_array($IdReply[$i]->seriesNumber)) {
                $seriesNumber = (string) $IdReply[$i]->seriesNumber;
            }
            $issueAuthority = '';
            if (!is_array($IdReply[$i]->issueAuthority)) {
                $issueAuthority = (string) $IdReply[$i]->issueAuthority;
            }
            $xml['identification'][$i]['type'] = (string) $IdReply[$i]->idTypeText;
            $xml['identification'][$i]['number'] = (string) $IdReply[$i]->idNum . ' ' . $seriesNumber;
            $xml['identification'][$i]['desc'] = $issueAuthority . ' ' . $this->getDate((string) $IdReply[$i]->issueDate);
        }

        // Адреса
        $AddressReply = array();
        foreach ($report->AddressReply as $addrdr) {
            $AddressReply[] = $addrdr;
        }

        for ($i = 0; $i < count($AddressReply); $i++) {
            $xml['address'][$i]['type'] = (string) $AddressReply[$i]->addressTypeText;
            $city = '';
            $street = '';
            $houseNumber = '';
            $block = '';
            $flat = '';
            if (!is_array($AddressReply[$i]->city)) {
                $city = (string) $AddressReply[$i]->city;
            }
            if (!is_array($AddressReply[$i]->street)) {
                $street = ', ' . (string) $AddressReply[$i]->street;
            }
            if (!is_array($AddressReply[$i]->houseNumber)) {
                $houseNumber = ', ' . (string) $AddressReply[$i]->houseNumber;
            }
            if (!is_array($AddressReply[$i]->block)) {
                $block = (string) $AddressReply[$i]->block;
                if (!empty($block))
                    $block = '/' . $block;
            }
            if (!is_array($AddressReply[$i]->apartment)) {
                $flat = ', ' . (string) $AddressReply[$i]->apartment;
            }
            $xml['address'][$i]['address'] = $city . $street . $houseNumber . $block . $flat;
            $xml['address'][$i]['date'] = $this->getDate((string) $AddressReply[$i]->lastUpdatedDt);
            //$xml['address'][$i]['address'] = $AddressReply[$i]['city'].', '.$AddressReply[$i]['street'].', '.$AddressReply[$i]['houseNumber'].', '.$AddressReply[$i]['apartment'];
        }

        // Телефоны
        $PhoneReply = array();
        foreach ($report->PhoneReply as $phr) {
            $PhoneReply[] = $phr;
        }
        for ($i = 0; $i < count($PhoneReply); $i++) {
            $xml['phone'][$i]['type'] = (string) $PhoneReply[$i]->phoneTypeText;
            $xml['phone'][$i]['number'] = (string) $PhoneReply[$i]->number;
            $xml['phone'][$i]['date'] = $this->getDate((string) $PhoneReply[$i]->lastUpdatedDt);
        }

        // Счета
        foreach ($report->AccountReply as $account) {
            $accountArray = [];

            $currencyCode = (string) $account->currencyCode;
            $currencyCode = self::normalizeCurrency($currencyCode);

            $accountArray['cred_id'] = (string) $account->serialNum;
            $accountArray['cred_type_text'] = (string) $account->acctTypeText;
            $accountArray['cred_sum'] = (string) $account->creditLimit;

            $endDate = (string) $account->closedDt;
            if (empty($endDate))
                $endDate = (string) $account->paymentDueDate;

            $accountArray['cred_enddate'] = $this->getDate($endDate);

            //Сумма причитающихся за следующей даты платежа.
            $cred_sum_debt = (string) $account->termsAmt;
            if (!empty($cred_sum_debt)) {
                $accountArray['cred_sum_debt'] = $cred_sum_debt;
            } elseif ((string) $account->acctType == '16') { //Microcredit
                $accountArray['cred_sum_debt'] = 0;
                if ((string) $account->accountRating != '13') { //Account Closed
                    //Общая сумма задолженности, включая любые интересы или штрафы, начисленные по состоянию на отчетную дату поле.
                    $accountArray['cred_sum_debt'] = (string) $account->amtOutstanding;
                }
            }

            $accountRating = (string) $account->accountRating;
            $accountArray['status'] = $accountRating == '13' || $accountRating == '14' ? 0 : 1; // Account Closed OR Account Closed - transfered to another bank
            $accountArray['cred_status_text'] = self::$status_array[$accountRating];

            //Дата появления в системе
//			$accountArray['cred_first_load'] = $this->getDate((string) $account->fileSinceDt);
            // Дата отчета
//			$reportingDt = $this->getDate((string) $account->reportingDt);

            $accountArray['cred_date'] = $this->getDate((string) $account->openedDt);
            $accountArray['cred_update'] = $this->getDate((string) $account->lastUpdatedDt);

            // Баланс счета на дату запроса
            $accountArray['cred_sum_overdue'] = (string) $account->curBalanceAmt;

            $accountArray['cred_day_overdue'] = 0;
            if ((string) $account->accountRating == '52') {
                $d1 = strtotime($this->getDate((string) $account->paymentDueDate));
                $d2 = strtotime($this->getDate((string) $report->SubjectReply->lastUpdatedDt));
                $accountArray['cred_day_overdue'] = floor(($d2 - $d1) / 86400);
            }

            $accountArray['cred_max_overdue'] = (string) $account->amtOutstanding;

            $accountArray['delay5'] = '';
            $accountArray['delay30'] = '';
            $accountArray['delay60'] = (string) $account->numDays30;
            $accountArray['delay90'] = (string) $account->numDays60;
            $accountArray['delay_more'] = (string) $account->numDays90;

            $accountArray['payline'] = str_replace(
                    ['0', '1', '2', '3', '4', '5', '7', '8', '9', 'X', 'A'], ['-', '0', '3', '4', '5', '6', '3', 'R', 'B', '-', '2'], (string) $account->paymtPat);


            $xml['account'][$currencyCode][] = $accountArray;
        }

        return $xml;
    }

    static function fixPasport($number){
        return preg_replace('/(\d{6})\s(\d{2})\s?(\d{2})/','\2\3-\1',$number);
    }
}
