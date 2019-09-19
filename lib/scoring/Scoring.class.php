<?php

class Scoring {

    static function isProp($str) {
        return in_array($str, array('Прописка', 'Адрес прописки'));
    }

    static function isMFO($str) {
        return in_array($str, array('Микрозайм', 'Микрокредит'));
    }

    static function isActiveLoan($str) {
        return in_array($str, array('активен', 'открыт'));
    }

    static function isFineLoan($str) {
        return in_array($str, array('просрочен', 'продан'));
    }

    static function fixPassport($str) {
        return preg_replace('/\D/', '', $str);
    }

    protected $order_id;
    protected $order;
    /* Не отправлять запросы */
    public $no_snd = true;
    public $set_status = true;

    function __construct($order_id) {
        $this->order_id = $order_id;
        $q = "SELECT * FROM orders o,users u WHERE u.id=o.user_id AND o.id=$order_id";
        $rs = DB::select($q);
        if ($rs->next()) {
            $this->order = $rs->getRow();
        } else {
            throw new Exception('Не найден договор ' . $order_id);
        }
        $this->no_snd = false;
    }

    /**
     * Проверка на плохой регион
     * @return type
     */
    function checkYearsOldPasp() {

        $cfg = Cfg::get('scoring');


        $age = floor((time() - strtotime($this->order['birthday'])) / (60 * 60 * 24 * 365.25));
        $min_age = 18;
        if (!empty($cfg['borrower_years_old']['from'])) {
            $min_age = $cfg['borrower_years_old']['from'];
        }
        $max_age = 75;
        if (!empty($cfg['borrower_years_old']['to'])) {
            $max_age = $cfg['borrower_years_old']['to'];
        }
        if ($age < $min_age) {
            return "мы не выдаем займы лицам младше $min_age лет.";
        } elseif ($age > $max_age) {
            return "мы не выдаем займы лицам старше $max_age лет.";
        }

        if ($e = $this->verifyPasportdate($this->order)) {
            return $e;
        }
    }

    private function verifyPasportdate($data) {
        $err = '';

        $pasport_date_array = explode('-', $data['pasportdate']);
        if (dte($data['pasportdate'], DTE_FORMAT_SQL) > date('Y-m-d')) {
            $err = "некорректная ДАТА ВЫДАЧИ ПАСПОРТА";
        } elseif (!checkdate((int) $pasport_date_array[1], (int) $pasport_date_array[2], (int) $pasport_date_array[0])) {
            $err = "некорректная ДАТА ВЫДАЧИ ПАСПОРТА";
        } else {
            $yearsOld = 0;
            $birthday_array = explode('-', $data['birthday']);
            $bdDay = $birthday_array[2];
            $bdMonth = $birthday_array[1];
            $bdYear = $birthday_array[0];
            if ($bdMonth >= date('m') && $bdDay > date('d')) {
                $yearsOld = date('Y') - $bdYear - 1;
            } else {
                $yearsOld = date('Y') - $bdYear;
            }
            if ($yearsOld > 20) {
                if ($yearsOld > 45) {
                    $red_line_pasp_date_mktime = mktime(0, 0, 0, $bdMonth, $bdDay, $bdYear + 45);
                } else {
                    $red_line_pasp_date_mktime = mktime(0, 0, 0, $bdMonth, $bdDay, $bdYear + 20);
                }
                //$passport_mktime = mktime(0, 0, 0, $pasport_date_array[1], $pasport_date_array[0], $pasport_date_array[2]);
                $passport_mktime=  strtotime($data['pasportdate']);
                if ($passport_mktime < $red_line_pasp_date_mktime) {
                    $err = "Похоже, что просрочен паспорт.";
                }
            }
        }
        return $err;
    }

    /**
     * Проверка на плохой регион
     * @return type
     */
    function checkDenyRegion() {
        $out = '';
        $cfg = Cfg::get('scoring');
        if (!empty($cfg['deny_regions'])) {
            foreach ($cfg['deny_regions'] as $reg) {
                foreach (array('region', 'prop_region', 'prog_region') as $k) {
                    if (mb_strpos(mb_strtolower($this->order[$k]), mb_strtolower($reg)) !== false) {
                        $out = "Регион $reg";
                        break;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Проверка на совпадения по прописке
     * @return type
     */
    function checkCoincidence() {
        $out = '';
        if (!$this->order['street']) {
//return;
            return 'Не указана улица прописки';
        }
        if (!$this->order['building']) {
//return;
            return 'Не указана дом прописки';
        }
        $q = "SELECT * FROM orders o,users u WHERE o.hide IS NULL AND u.id=o.user_id AND o.status<>'Т'";
        $q.=" AND o.user_id<>{$this->order['user_id']}";
        $q.=" AND u.city='{$this->order['city']}' AND u.street='{$this->order['street']}'";
        $q.=" AND u.building='{$this->order['building']}' AND u.street='{$this->order['flat_add']}'";
        $rs = DB::select($q);
        if ($rs->next()) {
            $out = "Совпадает прописка с договором {$rs->get('order_num')}";
        }
        return $out;
    }

    function checkEqki() {
        $cfg = Cfg::get('scoring');

        require_once ROOT . '/admin/core/class.plugin.php';
        $class_pugin = new Plugin();
        $ch = $class_pugin->load('CH');
        $eqki = $ch->select('eqki');
        $data = $eqki->getCH($this->order_id);
//        if (empty($data['phone'])) {
//            return 'Отсутствуют телефоны';
//        }
//        $pasp = '';
//        foreach ($data['identification'] as $p) {
//            if (mb_stripos($p['type'], 'Паспорт') !== null) {
//                if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $p['desc'], $res)) {
//                    $dte = "{$res[3]}-{$res[2]}-{$res[1]}";
//                    $n = $p['number'];
//                    if (self::fixPassport($this->order['pasport']) == $n && $dte == $this->order['pasportdate']) {
//                        $pasp = $n;
//                    }
//                }
//            }
//        }
//        if (empty($pasp)) {
//            return 'Номер паспорта или дата выдачи не совпадает с заявкой';
//        }
//        if ($e = $this->checkProp($data)) {
//            return $e;
//        }
        if ($e = $this->checkLoan($data)) {
            return $e;
        }
    }

    function checkLoan($data) {
        $cfg = Cfg::get('scoring');
        if (!empty($data['account']['RUB'])) {
            if ($cfg['max_open_loan_mfo'] && ($c = $this->countMFO($data['account']['RUB'])) > $cfg['max_open_loan_mfo']) {
                return "Открытых займов МФО $c > {$cfg['max_open_loan_mfo']}";
            }
            if ($cfg['max_open_loan_bank'] && ($c = $this->countBANK($data['account']['RUB'])) > $cfg['max_open_loan_bank']) {
                return "Открытых займов банков $c > {$cfg['max_open_loan_bank']}";
            }
            if ($cfg['max_fine_loan_mfo'] && ($c = $this->countMFOFine($data['account']['RUB'])) > $cfg['max_fine_loan_mfo']) {
                return "Просрочек МФО $c > {$cfg['max_fine_loan_mfo']}";
            }
            if ($cfg['max_month_sum'] && ($c = $this->sumMonthLoan($data['account']['RUB'])) > $cfg['max_month_sum']) {
                return "Сумма платежей по текущим кредитам $c > {$cfg['max_month_sum']}";
            }
        }
    }

    function checkNBKI() {
        $cfg = Cfg::get('scoring');
        $rs = DB::select("SELECT id FROM nbki_credit_history WHERE order_id={$this->order_id}");
        if (!$rs->next()) {
            require_once ROOT . '/admin/lib/extended/NBKI.php';
            $nbki = NBKI::getNBKI($this->order_id);
            if ($this->no_snd || !$nbki->send()) {
                throw new Exception("Не удалось запросить НБКИ {$this->order_id}");
            }
        }
        require_once ROOT . '/admin/core/class.plugin.php';
        $class_pugin = new Plugin();
        $ch = $class_pugin->load('CH');
        $nbki = $ch->select('nbki');
        $data = $nbki->getCH($this->order_id);
        if (empty($data['phone'])) {
            return 'Отсутствуют телефоны';
        }

        $mob = array();
        foreach ($data['phone'] as $i) {
            if ($i['type'] == 'Сотовый') {
                if ($cfg['mob_period']) {
                    if (time() - strtotime($i['date']) > $cfg['mob_period'] * 3600 * 24) {
                        $mob[] = $i;
                    }
                } else {
                    $mob[] = $i;
                }
            }
        }
        if (!$mob) {
            return 'Отсутствуют сотовый телефон или время жизни <' . $cfg['mob_period'] . ' дн.';
        }

        $pasp = '';
        foreach ($data['identification'] as $p) {
            if (mb_stripos($p['type'], 'Паспорт') !== null) {
                if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $p['desc'], $res)) {
                    $dte = "{$res[3]}-{$res[2]}-{$res[1]}";
                    $n = CH_nbki::fixPasport($p['number']);
                    if ($this->order['pasport'] == $n && $dte == $this->order['pasportdate']) {
                        $pasp = $n;
                    }
                }
            }
        }
        if (empty($pasp)) {
            return 'Номер паспорта или дата выдачи не совпадает с заявкой';
        }

        if ($e = $this->checkProp($data)) {
            return $e;
        }


        if ($e = $this->checkLoan($data)) {
            return $e;
        }
    }

    /**
     * Проверка совпадения прописки c БКИ
     * @param type $data
     * @return string
     */
    function checkProp($data) {
        $prop = false;
        $prop_check = false;
        $prop_check_msg = '';
        if (!empty($data['address'])) {
            foreach ($data['address'] as $itm) {
                if (self::isProp($itm['type'])) {
                    $prop = true;
                    $addr = mb_strtolower(preg_replace('/[^\w\d]/u', '', $itm['address']));
                    $city = mb_strtolower(preg_replace('/[^\w\d]/u', '', $this->order['city']));
                    $street = mb_strtolower(preg_replace('/[^\w\d]/u', '', $this->order['street']));
                    $building = mb_strtolower(preg_replace('/[^\w\d]/u', '', $this->order['building']));
                    $flat_add = mb_strtolower(preg_replace('/[^\w\d]/u', '', $this->order['flat_add']));
                    if (mb_strpos($addr, $city) !== false && mb_strpos($addr, $street) !== false && mb_strpos($addr, $building) !== false && (mb_strpos($addr, $flat_add) !== false || !$flat_add)) {
                        $prop_check = true;
                    } else {
                        $prop_check_msg[] = $itm['address'];
                    }
                }
            }
        }
        if (!$prop) {
            return 'Не найден адрес прописки';
        } elseif (!$prop_check) {
            return 'Адрес прописки не совпадает ' . "{$this->order['city']}, {$this->order['street']}  {$this->order['building']}-{$this->order['flat_add']} и " . implode(' | ', $prop_check_msg);
        }
    }

    function sumMonthLoan($data = array()) {
        $smm = 0;
        foreach ($data as $acc) {
            if ($acc['status'] != 0) {
                $smm+=$acc['cred_sum_debt'];
            }
        }
        return $smm;
    }

    function countMFO($data = array()) {
        $cnt = 0;
        foreach ($data as $acc) {
            if (self::isMFO($acc['cred_type_text']) && self::isActiveLoan($acc['cred_status_text'])) {
                $cnt++;
            }
        }
        return $cnt;
    }

    function countMFOFine($data = array()) {
        $cnt = 0;
        foreach ($data as $acc) {
            if (self::isMFO($acc['cred_type_text']) && self::isFineLoan($acc['cred_status_text'])) {
                $cnt++;
            }
        }
        return $cnt;
    }

    function countBANK($data = array()) {
        $cnt = 0;
        foreach ($data as $acc) {
            if (!self::isMFO($acc['cred_type_text']) && self::isActiveLoan($acc['cred_status_text'])) {//&& mb_strpos($acc['cred_type_text'], 'Кредитная')!==false
                $cnt++;
            }
        }
        return $cnt;
    }

    function scorista() {
        global $settings;
        require_once ROOT . '/admin/lib/scoring/scorista/Scorista.php';
        $scorista = new Scorista($this->order_id);
        $scorista->test = $settings['adminPanel']['scorista']['test'];

        if (!$this->no_snd) {
            if ($requestBody = $scorista->process()) {
                //return $requestBody;
            }
        }

        $rs = DB::select("SELECT decision FROM scorista_ansver WHERE order_id={$this->order_id}");
        if ($rs->next()) {
            if ($rs->get('decision') == 'Одобрено') {
                return $this->approveOrder();
            }
            if ($rs->get('decision') == 'Отказ') {
                return $this->denyOrder('Отказ скористы');
            }
            return "Не известный ответ скористы";
        }
        return "Ожидание скористы";
    }

    function score() {

        $rs = DB::select("SELECT * FROM eqki_credit_history WHERE order_id={$this->order_id}");
        if ($rs->next()) {
            if ($rs->getInt('check') == 1) {
                if ($e = $this->checkEqki()) {
                    return $this->denyOrder($e);
                }
                //Тут чото дальше делает, отправляем в скористу
                return $this->scorista();
            } else {
                return 'Ожидание эквифакса';
            }
        }
        if ($e = $this->checkYearsOldPasp()) {
            return $this->denyOrder($e);
        }
        if ($e = $this->checkDenyRegion()) {
            return $this->denyOrder($e);
        }
        if ($e = $this->checkCoincidence()) {
            return $this->denyOrder($e);
        }
        if ($e = $this->checkNBKI()) {
            return $this->denyOrder($e);
        }
        // тут запрос еквифакса
        DB::insert('eqki_credit_history', array('check' => 0, 'order_id' => $this->order_id));
        return 'Отправили запрос эквифакс';
    }

    /**
     * Отказ по займу
     * @param type $e
     * @return type
     */
    function denyOrder($e) {
        if ($this->set_status) {
            DB::update('orders', array('status' => 'Н'), "id={$this->order_id} AND status='Т'");
            DB::insert('orders_comments', array('comment_text' => $e, 'comment_date' => date('Y-m-d H:i:s'), 'order_id' => $this->order_id, 'adm_users_id' => 1));

            $this->notice(noticemng::ORDER_STATUS_N_TPL);
        }

        return $e;
    }

    function notice($tpl) {
        noticemng::sendMail($this->order['email'], $tpl, $this->order);
    }

    function approveOrder() {
        if ($this->set_status) {
            DB::update('orders', array('status' => 'П'), "id={$this->order_id} AND status='Т'");
            DB::insert('orders_comments', array('comment_text' => 'Одобрено скористой', 'comment_date' => date('Y-m-d H:i:s'), 'order_id' => $this->order_id, 'adm_users_id' => 1));
            $this->notice(noticemng::ORDER_STATUS_P_TPL);
        }
        return 'Одобрено скористой';
    }

    static function run($order_id) {
        try {
            $scoring = new self($order_id);
            return $scoring->score();
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

}
