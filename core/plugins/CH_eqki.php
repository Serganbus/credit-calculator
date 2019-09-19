<?php

/**
 * Class CH_eqki
 * Эквифакс
 */
class CH_eqki extends CH implements iCH {

	public static $cred_type = array(
		'00' => 'Неизвестный тип кредита',
		'01' => 'Кредит на автомобиль',
		'02' => 'Лизинг',
		'03' => 'Ипотека',
		'04' => 'Кредитная карта',
		'05' => 'Потребительский кредит',
		'06' => 'Кредит на развитие бизнеса',
		'07' => 'Кредит на пополнение оборотных средств',
		'08' => 'Кредит на покупку оборудования',
		'09' => 'Кредит на строительство недвижимости',
		'10' => 'Кредит на покупку акций (маржинальное кредитование)',
		'11' => 'Межбанковский кредит',
		'12' => 'Кредит мобильного оператора',
		'13' => 'Кредит на обучение',
		'14' => 'Дебетовая карта с овердрафтом',
		'15' => 'Ипотека (первичный рынок)',
		'16' => 'Ипотека (вторичный рынок)',
		'17' => 'Ипотека (ломбардный кредит)',
		'18' => 'Кредит наличными (нецелевой)',
		'19' => 'Микрозайм',
		'99' => 'Другой тип кредита',
	);

	public static $cred_active = array(
		'закрыт',
		'активен',
		'продан',
		'списан с баланса',
		'реструктурирован/ рефинансирован',
		'передан коллекторам',
		'заблокирован',
		'отменен',
	);

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

        $row = $this->db->GetRow("SELECT * FROM eqki_credit_history WHERE order_id=%s", array($order_id));
        if (count($row) == 0) {
            $this->db->Run("INSERT INTO eqki_credit_history (date, order_id) VALUES(%s, %s)", array(date('Y-m-d H:i:s'), $order_id));
            $result['result'] = true;
        } else {
            $result['desc'] = 'Запрос на получение КИ уже был сформирован';
        }

        return $result;
    }

    function getCH($order) {
		$xmlTxt = db()->scalar("SELECT `xml` FROM eqki_credit_history WHERE order_id=:order_id", [':order_id' =>$order]);

		$ch = json_decode(json_encode(simplexml_load_string(iconv('UTF-8', 'windows-1251', $xmlTxt), null, LIBXML_NOCDATA)), true);

        // ФИО и рождение		
        $xml['surname'] = $ch['response']['title_part']['private']['lastname'];
        $xml['name'] = $ch['response']['title_part']['private']['firstname'];
        $xml['second_name'] = $ch['response']['title_part']['private']['middlename'];
        $xml['birth_day'] = $ch['response']['title_part']['private']['birthday'];
        $xml['birth_place'] = $ch['response']['title_part']['private']['birthplace'];

        // Идентификация
        // - Паспорт
        $xml['identification'][0]['type'] = 'Паспорт';
        $xml['identification'][0]['number'] = $ch['response']['title_part']['private']['doc']['docno'];
        $xml['identification'][0]['desc'] = $ch['response']['title_part']['private']['doc']['docdate'] . ' ' . $ch['response']['title_part']['private']['doc']['docplace'];
        // - ИНН
        if ($ch['response']['title_part']['private']['inn'] != '') {
            array_push($xml['identification'], array('type' => 'ИНН', 'number' => $ch['response']['title_part']['private']['inn']));
        }
        // - ПФР
        if ($ch['response']['title_part']['private']['pfno'] != '') {
            array_push($xml['identification'], array('type' => 'ПФР', 'number' => $ch['response']['title_part']['private']['pfno']));
        }
        // - Водител
        if ($ch['response']['title_part']['private']['driverno'] != '') {
            array_push($xml['identification'], array('type' => 'Водител', 'number' => $ch['response']['title_part']['private']['driverno']));
        }
        // - Мед.полис
        if ($ch['response']['title_part']['private']['medical'] != '') {
            array_push($xml['identification'], array('type' => 'Мед.полис', 'number' => $ch['response']['title_part']['private']['medical']));
        }

        // Адреса
        $xml['address'][0]['type'] = 'Прописка';
        $xml['address'][0]['address'] = $ch['response']['base_part']['addr_reg'];
        $xml['address'][1]['type'] = 'Проживание';
        $xml['address'][1]['address'] = $ch['response']['base_part']['addr_fact'];
        if (count($ch['response']['base_part']['history_addr']['addr_reg']) < 2) {
            array_push($xml['address'], array('type' => 'Пред.прописка', 'address' => $ch['response']['base_part']['history_addr']['addr_reg']));
        } else {
            foreach ($ch['response']['base_part']['history_addr']['addr_reg'] as $addr_reg) {
                array_push($xml['address'], array('type' => 'Пред.прописка', 'address' => $addr_reg));
            }
        }
        if (count($ch['response']['base_part']['history_addr']['addr_fact']) < 2) {
            array_push($xml['address'], array('type' => 'Пред.проживание', 'address' => $ch['response']['base_part']['history_addr']['addr_fact']));
        } else {
            foreach ($ch['response']['base_part']['history_addr']['addr_fact'] as $addr_fact) {
                array_push($xml['address'], array('type' => 'Пред.проживание', 'address' => $addr_fact));
            }
        }
        // Телефоны
        $PhoneReply = $ch['response']['base_part']['phones']['phone_number'];
        for ($i = 0; $i < count($PhoneReply); $i++) {
            $xml['phone'][$i]['type'] = "";
            $xml['phone'][$i]['number'] = $PhoneReply[$i];
            $xml['phone'][$i]['date'] = "";
        }
        $payline_for = $ch['response']['add_part']['attrs']['package']['section']['period']['attr'];

        // Счета
        $credits = [];
        if (isset($ch['response']['base_part']['credit']['cred_id'])) {
            $credits[] = $ch['response']['base_part']['credit'];
        } else {
            $credits = $ch['response']['base_part']['credit'];
        }

		// Счета
		foreach ($credits as $account) {
			$accountArray = [];
			$currencyCode = (string) $account['cred_currency'];
			$currencyCode = self::normalizeCurrency($currencyCode);

			$accountArray['cred_id'] = $account['cred_id'];
			$accountArray['cred_type_text'] = self::$cred_type[$account['cred_type']];
			$accountArray['cred_sum'] = $account['cred_sum'];
			$accountArray['cred_enddate'] = $account['cred_enddate'];

			$accountArray['status'] = $account['cred_active'];
			$accountArray['cred_status_text'] = self::$cred_active[$account['cred_active']];
			$accountArray['cred_first_load'] = $account['cred_first_load'];
			$accountArray['cred_date'] = $account['cred_date'];
			$accountArray['cred_update'] = $account['cred_update'];

			$accountArray['cred_sum_overdue'] = $account['cred_sum_overdue'];
			$accountArray['cred_day_overdue'] = $account['cred_day_overdue'];
			$accountArray['cred_max_overdue'] = $account['cred_max_overdue'];

			$accountArray['delay5'] = $account['delay5'];
			$accountArray['delay30'] = $account['delay30'];
			$accountArray['delay60'] = $account['delay60'];
			$accountArray['delay90'] = $account['delay90'];
			$accountArray['delay_more'] = $account['delay_more'];

			for ($j = 0; $j < count($payline_for); $j++) {
				if ($payline_for[$j]["@attributes"]["c_i"] == $accountArray['cred_id']) {

					if ($payline_for[$j]["@attributes"]["n"] == "831")
						$accountArray['payline'] = $payline_for[$j]["@attributes"]["v"];

					if ($payline_for[$j]["@attributes"]["n"] == "706")
						$accountArray['cred_sum_debt'] = $payline_for[$j]["@attributes"]["v"];
				}
			}

			$xml['account'][$currencyCode][] = $accountArray;
		}

        $xml['add_part']['scoring']['equ'] = $ch['response']['add_part']['scorings']['scoring']['score'];
        return $xml;
    }

    function getStatus($order_id) {
		$check = db()->scalar("SELECT `check` FROM eqki_credit_history WHERE order_id=:order_id", [':order_id' =>$order_id]);

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
