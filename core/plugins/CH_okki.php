<?php

/**
 * Class CH_okki
 */
class CH_okki extends CH implements iCH {

	public static $cred_type = array(
		'01' => 'Кредит под обеспечение (под залог)',
		'02' => 'Персональный кредит',
		'03' => 'Кредит на недвижимость, в т.ч. Ипотека',
		'04' => 'Продажа в рассрочку (в точках продаж)',
		'05' => 'Кредит на приобретение автомобиля',
		'11' => 'Государственное кредитование',
		'12' => 'Синдицированный кредит',
		'21' => 'Возобновляемый кредит/Кредитная линия',
		'22' => 'Карта продавца',
		'23' => 'Кредитная карта',
		'24' => 'Платежная карта',
		'25' => 'Дебетовая карта',
		'26' => 'Карта с овердрафтом',
		'27' => 'Счет с овердрафтом',
		'41' => 'Лизинг',
		'42' => 'Мобильный счет',
		'43' => 'Договор аренды',
		'44' => 'Текущий счет / МБК',
		'45' => 'Почтовый перевод',
		'46' => 'Услуги он-лайн',
		'47' => 'Счет телекоммуникационных услуг',
		'48' => 'Коммунальный счет',
		'49' => 'Страховой полис',
		'50' => 'Факторинг',
	);


	public static $cred_active = array(
		'0' => 'Своевременно',
		'A' => '1 - 30 дней просроченной задолженности',
		'1' => '31 - 60 дней просроченной задолженности (1 месяц)',
		'2' => '61 - 90 дней просроченной задолженности (2 месяца)',
		'3' => '91 - 120 дней просроченной задолженности (3 месяца)',
		'4' => '121 - 150 дней просроченной задолженности (4 месяца)',
		'5' => '151 - 180 дней просроченной задолженности (5 месяцев)',
		'6' => '181 и более дней просроченной задолженности (6+ месяцев)',
		'8' => 'Неуплата',
		'L' => 'Тяжба',
		'W' => 'Списан',
	);

    private function getDate($str) {
        return preg_replace('/(\d{4})(\d{2})(\d{2}).*/', '\3.\2.\1', $str);
    }

    function newCH($order_id) {
        $result = array('result' => false, 'desc' => '');

        $row = $this->db->GetRow("SELECT * FROM okki_credit_history WHERE order_id=%s", array($order_id));
        if (count($row) == 0) {
            $this->db->Run("INSERT INTO okki_credit_history (date, order_id) VALUES(%s, %s)", array(date('Y-m-d H:i:s'), $order_id));
            $result['result'] = true;
        } else {
            $result['desc'] = 'Запрос на получение КИ уже был сформирован';
        }

        return $result;
    }

    function parseNode($node) {
        $out = array();
        $j = 0;
        foreach ($node as $i => $n) {
            if ($n->attributes()->n) {
//    			$out[(string)$n->attributes()->n]=$n;
                if (count($n->children())) {
                    $out[(string) $n->attributes()->n] = $this->parseNode($n);
                } else {
                    $out[(string) $n->attributes()->n] = (string) $n;
                }
            } else {
                $out[$j++] = $this->parseNode($n);
            }
        }
        return $out;
    }

    function getCH($order) {

        $row = $this->db->GetRow("SELECT `check`, `xml` FROM okki_credit_history WHERE order_id=%s", array($order));

        $xml_in = simplexml_load_string($row['xml'], null, LIBXML_NOCDATA);
        $ch = $this->parseNode($xml_in);

        $Consumer = $ch['Consumer'][0]['CAIS'][0]['Consumer'][0];
        // ФИО и рождение		
        $xml['surname'] = $Consumer['surname'];
        $xml['name'] = $Consumer['name1'];
        $xml['second_name'] = $Consumer['name2'];
        $xml['birth_day'] = $this->getDate($Consumer['dateOfBirth']);
        $xml['birth_place'] = $Consumer['placeOfBirth'];

        // Идентификация
        // - Паспорт
        $xml['identification'][0]['type'] = 'Паспорт';
        $xml['identification'][0]['number'] = $Consumer['primaryID'];
        $xml['identification'][0]['desc'] = $this->getDate($Consumer['primaryIDIssueDate']) . ' ' . $Consumer['primaryIDAuthority'];
        // - ИНН
//        if ($ch['response']['title_part']['private']['inn'] != '') {
//            array_push($xml['identification'], array('type' => 'ИНН', 'number' => $ch['response']['title_part']['private']['inn']));
//        }
        // - ПФР
//        if ($ch['response']['title_part']['private']['pfno'] != '') {
//            array_push($xml['identification'], array('type' => 'ПФР', 'number' => $ch['response']['title_part']['private']['pfno']));
//        }
        // - Водител
//        if ($ch['response']['title_part']['private']['driverno'] != '') {
//            array_push($xml['identification'], array('type' => 'Водител', 'number' => $ch['response']['title_part']['private']['driverno']));
//        }
        // - Мед.полис
//        if ($ch['response']['title_part']['private']['medical'] != '') {
//            array_push($xml['identification'], array('type' => 'Мед.полис', 'number' => $ch['response']['title_part']['private']['medical']));
//        }
        $Address = $Consumer['Address'][0];
        // Адреса
        $xml['address'][0]['type'] = 'Прописка';
        $xml['address'][0]['address'] = "{$Address['line4']} {$Address['line3']} {$Address['line2']} {$Address['line1']}  {$Address['houseNbr']} {$Address['flatNbr']} ";

        $Address = $Consumer['Address'][1];
        $xml['address'][1]['type'] = 'Проживание';
        $xml['address'][1]['address'] = "{$Address['line4']} {$Address['line3']} {$Address['line2']} {$Address['line1']}  {$Address['houseNbr']} {$Address['flatNbr']} ";
//        if (count($ch['response']['base_part']['history_addr']['addr_reg']) < 2) {
//            array_push($xml['address'], array('type' => 'Пред.прописка', 'address' => $ch['response']['base_part']['history_addr']['addr_reg']));
//        } else {
//            foreach ($ch['response']['base_part']['history_addr']['addr_reg'] as $addr_reg) {
//                array_push($xml['address'], array('type' => 'Пред.прописка', 'address' => $addr_reg));
//            }
//        }
//        if (count($ch['response']['base_part']['history_addr']['addr_fact']) < 2) {
//            array_push($xml['address'], array('type' => 'Пред.проживание', 'address' => $ch['response']['base_part']['history_addr']['addr_fact']));
//        } else {
//            foreach ($ch['response']['base_part']['history_addr']['addr_fact'] as $addr_fact) {
//                array_push($xml['address'], array('type' => 'Пред.проживание', 'address' => $addr_fact));
//            }
//        }
        // Телефоны
        //$credit_report = $ch['response']['add_part']['credit_report'];
        //$ch2 = json_decode(json_encode(simplexml_load_string(iconv('UTF-8', 'windows-1251', $credit_report))), true);
//        $PhoneReply = $ch['response']['base_part']['phones']['phone_number'];
        $xml['phone'][0]['type'] = "";
        $xml['phone'][0]['number'] = $Consumer['mobileTelNbr'];
        $xml['phone'][0]['date'] = "";
//        for ($i = 1; $i < count($PhoneReply); $i++) {
//            $xml['phone'][$i]['type'] = "";
//            $xml['phone'][$i]['number'] = $PhoneReply[$i];
//            $xml['phone'][$i]['date'] = "";
//        }
        //$payline_for = $ch['response']['add_part']['attrs']['package']['section']['period']['attr'];
        // Счета
        $accounts = $ch['Consumer'][0]['CAIS'];

        foreach ($accounts as $i => $account) {
			$accountArray = [];
			$currencyCode = (string) $account['currency'];
			$currencyCode = self::normalizeCurrency($currencyCode);


            $accountArray['cred_id'] = $i;

            $accountArray['cred_type_text'] = self::$cred_type[$account['financeType']];

			$sumCredit = $account['amountOfFinance'];
			if (empty($sumCredit)) {
				if (is_array($account['MonthlyHistory']) && count($account['MonthlyHistory'])) {
					$first = reset($account['MonthlyHistory']);
					$sumCredit = $first['creditLimit'];
				}
			}

            $accountArray['cred_sum'] = $sumCredit;
            $accountArray['cred_enddate'] = $this->getDate($account['fulfilmentDueDate']);
            $accountArray['cred_sum_debt'] = $account['instalment'];

            $accountArray['status'] = $account['accountPaymentStatus'];
            $accountArray['cred_status_text'] = self::$cred_active[$account['accountPaymentStatus']];
            $accountArray['cred_first_load'] = $this->getDate($account['lastUpdateTS']);
            $accountArray['cred_date'] = $this->getDate($account['dateAccountAdded']);
            $accountArray['cred_update'] = $this->getDate($account['monthOfLastUpdate']);

            $accountArray['cred_sum_overdue'] = $account['outstandingBalance'];
//            $accountArray['cred_day_overdue'] = $ch['response']['base_part']['credit'][$i]['cred_day_overdue'];
            $accountArray['cred_max_overdue'] = $account['outstandingBalance'];

            /**
             * 'A' => '1 - 30 дней просроченной задолженности',
              '1' => '31 - 60 дней просроченной задолженности (1 месяц)',
              '2' => '61 - 90 дней просроченной задолженности (2 месяца)',
              '3' => '91 - 120 дней просроченной задолженности (3 месяца)',
              '4' => '121 - 150 дней просроченной задолженности (4 месяца)',
              '5' => '151 - 180 дней просроченной задолженности (5 месяцев)',
              '6' => '181 и более дней просроченной задолженности (6+ месяцев)',
             */

            $accountArray['delay5'] = in_array($account['accountPaymentStatus'], array('A'));
            $accountArray['delay30'] = in_array($account['accountPaymentStatus'], array('1'));
            $accountArray['delay60'] = in_array($account['accountPaymentStatus'], array('2'));
            $accountArray['delay90'] = in_array($account['accountPaymentStatus'], array('3'));
            $accountArray['delay_more'] = in_array($account['accountPaymentStatus'], array('4', '5', '6',));
            if (!empty($account['MonthlyHistory']) && is_array($account['MonthlyHistory'])) {
                foreach ($account['MonthlyHistory'] as $n => $his) {
					$accountArray['payline'] = $this->getDate($his['historyDate']);
                }
            }

			$xml['account'][$currencyCode][] = $accountArray;
        }

        return $xml;
    }

	function getStatus($order_id) {
		$check = db()->scalar("SELECT `check` FROM okki_credit_history WHERE order_id=:order_id", [':order_id' =>$order_id]);

		if (!$check) {
			$status = 0;
		} elseif ($check == '0') {
			$status = 1;
		} else {
			$status = 2;
		}
		return $status;
	}
}