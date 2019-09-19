<?php

/**
 * Class Scorista
 *
 * @author    efureev<efureev@yandex.ru>
 */
class Scorista {

    private $_data = array(),
            /** @var Orders|null */
            $order = null,
            /** @var User|null */
            $user = null,
            /** @var User|null */
            $_userName = null,
            $_secret = null,
            $_requestId = null,
            $_autoSend = false,
            $_lastHttpRequestId = null;
    public $test = false;
    private static $_url = 'https://api.scorista.ru/mixed/json';

    public function __construct($orderId) {
        if (!$this->order = Orders::one($orderId))
            throw new Exception('Нет такого заказа');

        if (!$this->user = User::one($this->order->user_id))
            throw new Exception('Нет такого пользователя');

        global $settings;

        if (!isset($settings['adminPanel']['scorista']['username']) || empty($settings['adminPanel']['scorista']['username']))
            throw new Exception('Не определен пользователь для Scorista');

        if (!isset($settings['adminPanel']['scorista']) || empty($settings['adminPanel']['scorista']['username']) || empty($settings['adminPanel']['scorista']['secret_key']))
            throw new Exception('Не определен пользователь для Scorista');

        $this->_userName = $settings['adminPanel']['scorista']['username'];
        $this->_secret = $settings['adminPanel']['scorista']['secret_key'];
        if (!empty($settings['adminPanel']['scorista']['autoSend'])) {
            $this->_autoSend = true;
        }
    }

    /**
     * Обработка бизнес-логики
     */
    public function process() {
        $scAnswer = db()->one("SELECT * FROM scorista_ansver WHERE order_id=:order_id ", array(':order_id' => $this->order->id));
        if ($scAnswer !== null) {
            $result = null;

            if ($scAnswer['decision'] === 'NONE') {
                $response = $this->getCreditDecisionRequest($scAnswer);
                switch ($response->status) {
                    case ScoristaResponse::STATUS_DONE :
                        db()->query("UPDATE scorista_ansver SET `decision`=:decision, `desc`=:desc WHERE order_id=:order_id", array(
                            ':decision' => $response->getDecision('decisionName'),
                            ':desc' => $response->getRaw(),
                            ':order_id' => $this->order->id
                        ));
                        $result = $response;
                        break;
                    case ScoristaResponse::STATUS_CHECK :
                    case ScoristaResponse::STATUS_WAIT :
                        $result = '<h2>Анализ заемщика в процессе. Это может занять несколько минут</h2><h3><button type="button" id="refresh-scorista-answer" class="button">Обновить</button></h3>';
                        break;
                    case ScoristaResponse::STATUS_ERROR :
                        $result = "<h2>Произошла ошибка в процессе запроса. Обратитесь к специалистам</h2>" . $response->getError();
                        db()->query("DELETE FROM scorista_ansver WHERE order_id=:order_id", array(
                            ':order_id' => $this->order->id
                        ));
                        break;
                }
            } else {
                $requestBody = self::getRequestBody($scAnswer['httpRequestId']);

                $response = new DecisionScoristaResponse($scAnswer['desc'], $requestBody);
                $result = $response;
            }
        } else {
            $result = $this->sendDataOnExamination();
        }

        return $result;
    }

    /**
     * Получение тела запроса в Скористу
     *
     * @param int $httpRequestId
     * @return array|null
     * @throws Exception
     */
    private static function getRequestBody($httpRequestId) {
        $httpRequestId = (int) $httpRequestId;
        $requestBody = [];

        if ($httpRequestId > 0 && ($requestBody = HttpRequest::scalar('body', 'id = :id', [':id' => $httpRequestId]))) {
            $requestBody = json_decode($requestBody, 1);
        }

        if (count($requestBody))
            return $requestBody;

        return null;
    }

    
    private function getStaffMember(){
        if(!empty($_SESSION[$_SERVER['HTTP_HOST']]['admin']['name'])){
            return $_SESSION[$_SERVER['HTTP_HOST']]['admin']['name'];
        }
        $rs=DB::select("SELECT * FROM adm_users WHERE login='admin'");
        if($rs->next()){
            return $rs->get('name');
        }
    }
    /**
     * Формирование стуктуры данных «ТРЕБОВАНИЕ ЭКСПЕРТИЗЫ»
     */
    private function formingDataOnExamination() {
        $dateBack = date('d.m.Y', strtotime(date("d.m.Y") . " + {$this->order->days} days"));
        $personBirthDay = preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$3.$2.$1', $this->user->birthday);
        $maritalStatus = !empty(ScoristaTranslater::$maritalStatus[$this->user->semiapol]) ? ScoristaTranslater::$maritalStatus[$this->user->semiapol] : false;

        $personGender = $this->user->genderDetect();

        $pasport = preg_replace('/^(\d+)-(\d+)$/', '$1 $2', $this->user->pasport);
        $pasportDate = preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$3.$2.$1', $this->user->pasportdate);
        $pasportDivision = preg_replace('/[^\d]+/', '', $this->user->pasportdepartmentcode);
        $pasportDivision = preg_replace('/^(\d{3})(\d{3})$/', '$1-$2', $pasportDivision);

        $phone = preg_replace('/[^\d]/', '', $this->user->phone);
        $homePhone = preg_replace('/[^\d]/', '', $this->user->home_phone);
        if (strlen($homePhone) < 10)
            $homePhone = 'нет';

        $allLoansRepaid = db()->getList("SELECT * FROM orders WHERE id != :id AND user_id = :uid AND `status` =:status ORDER BY date DESC, time DESC", array(':id' => $this->order->id, ':uid' => $this->user->id, ':status' => 'О'));

        if (count($allLoansRepaid)) {
            $lastLoan = $allLoansRepaid[0];
            $dateBackLastLoan = date('d.m.Y', strtotime($lastLoan['date'] . " + {$lastLoan['days']} days"));

            if (!$lastLoan['is_paid'])
                throw new Exception('Ошибка! Предыдущий кредит не погашен: #' . $lastLoan['order_num']);

            $paymentLastLoan = db()->one("SELECT * FROM payments WHERE order_id = :order_id ORDER BY date DESC, time DESC", array(':order_id' => $lastLoan['id']));
            $previousLoanRepaymentA = (int) db()->scalar("SELECT SUM(`paysum`) FROM payments WHERE order_id = :order_id", array(':order_id' => $lastLoan['id']));
            $cardLastLoan = db()->one("SELECT * FROM `cards` WHERE `id`=:id", array(':id' => $lastLoan["take_type"]));
            if ($cardLastLoan) {
                if ($cardLastLoan['is_ym_ewallet'] == 1)
                    $cardLastLoan = 3;
                else
                    $cardLastLoan = 1;
            } else
                $cardLastLoan = 6;

            $previousLoanProlongationNumber = db()->scalar("SELECT COUNT(*) FROM prolongation WHERE order_id = :order_id", array(':order_id' => $lastLoan['id']));

            $numberLoansRepaid = array(
                'numberLoansRepaid' => count($allLoansRepaid),
                'previousLoanDate' => preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$3.$2.$1', $lastLoan['date']),
                'previousLoanPlanRepaidDate' => $dateBackLastLoan,
                'previousLoanFactRepaidDate' => preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$3.$2.$1', $paymentLastLoan['date']),
                'previousLoanAmount' => $lastLoan['sum_request'],
                'previousLoanRepaymentAmount' => $previousLoanRepaymentA,
                'previousLoanReceivingMethod' => $cardLastLoan,
                'previousLoanRepaymentMethod' => 1,
                'previousLoanProlongationNumber' => $previousLoanProlongationNumber ? $previousLoanProlongationNumber : '0',
                'softCollectionFlag' => $this->order->unload_in_nsv
            );
        } else {
            $numberLoansRepaid = array(
                'numberLoansRepaid' => 0,
                'softCollectionFlag' => 0
            );
        }

        $card = db()->one("SELECT * FROM `cards` WHERE `id`=:id", array(':id' => $this->order->take_type));

        $data = array(
            'form' => array(
                'persona' => array(
                    'personalInfo' => array(
                        'personaID' => $this->user->id, //id в нашей системе
                        'lastName' => trim($this->user->surname),
                        'firstName' => trim($this->user->name),
                        'patronimic' => trim($this->user->second_name),
                        'gender' => $personGender,
                        'birthDate' => $personBirthDay,
                        'placeOfBirth' => !empty($this->user->birthplace) ? $this->user->birthplace : 'нет',
                        'passportSN' => $pasport,
                        'issueDate' => $pasportDate,
                        'issueAuthority' => $this->user->pasportissued,
                        'subCode' => $pasportDivision,
                        'dependents' => $this->user->ijdivencev
                    ),
                    'addressRegistration' => array(
                        'postIndex' => strlen($this->user->prop_zip) < 6 ? '000000' : $this->user->prop_zip,
                        'region' => strlen($this->user->prop_region) < 2 ? 'нет' : $this->user->prop_region,
                        'city' => strlen($this->user->city) < 2 ? 'нет' : $this->user->city,
                        'street' => $this->user->street,
                        'house' => $this->user->building,
                        'building' => $this->user->building_add,
                        'flat' => $this->user->flat_add
                    ),
                    'addressResidential' => array(
                        'postIndex' => strlen($this->user->prog_zip) < 6 ? '000000' : $this->user->prog_zip,
                        'region' => strlen($this->user->prog_region) < 2 ? 'нет' : $this->user->prog_region,
                        'city' => strlen($this->user->city_prog) < 2 ? 'нет' : $this->user->city_prog,
                        'street' => $this->user->street_prog,
                        'house' => $this->user->building_prog,
                        'building' => $this->user->building_add_prog,
                        'flat' => $this->user->flat_add_prog
                    ),
                    'contactInfo' => array(
                        'cellular' => $phone,
                        'cellularState' => 2,
                        'cellularMethod' => 2,
                        'phone' => $homePhone,
                        'phoneState' => 1,
                        'phoneMethod' => 4,
                        'email' => empty($this->user->email) ? 'нет' : $this->user->email,
                        'emailState' => 2,
                        'emailMethod' => 1
                    ),
                    'employment' => array(
                        'jobCategory' => 8,
                        'employer' => trim($this->user->rab_mesto)
                    ),
                ),
                'info' => array(
                    'loan' => array(
                        'loanID' => $this->order->id,
                        'staffMember' => $this->getStaffMember(),
                        'loanPeriod' => $this->order->days,
                        'loanSum' => $this->order->sum_request,
                        'dayRate' => $this->order->persents,
                        'loanCurrency' => 'RUB',
                        'fullRepaymentAmount' => $this->order->back_sum,
                        'day30DelayRepaymentAmount' => ceil($this->order->back_sum + ceil($this->order->back_sum * 0.04) * 8),
                        'applicationSourceType' => 1,
                        'applicationSourceMethod' => 2,
                        'agreementSignatureMethod' => 2,
//						'loanReceivingMethod' => !empty($card) ? '1' : '5',
                        'loanRepaymentMethod' => 1
                    ),
                    'repaymentSchedule' => array(
                        'repaymentDate' => $dateBack,
                        'repaymentAmount' => $this->order->back_sum
                    ),
                    'borrowingHistory' => $numberLoansRepaid,
                ),
                'loanReceivingMethod' => array(),
                'truthQuestions' => array(),
            )
        );

        if ($maritalStatus)
            $data['form']['persona']['personalInfo']['maritalStatus'] = $maritalStatus;

        if (!empty($this->user->tin))
            $data['form']['persona']['personalInfo']['INN'] = $this->user->tin;

        if (!empty($this->user->drivers_licence))
            $data['form']['persona']['personalInfo']['drivingLicense'] = $this->user->drivers_licence;

        if (!empty($this->user->snils))
            $data['form']['persona']['personalInfo']['SNILS'] = $this->user->snils;

        if (!empty($this->user->carOwning))
            $data['form']['persona']['personalInfo']['carOwning'] = $this->user->carOwning;

        if (!empty($this->user->houseOwning))
            $data['form']['persona']['personalInfo']['houseOwning'] = $this->user->houseOwning;

        if (!empty($this->user->prop_okato))
            $data['form']['persona']['addressRegistration']['kladrID'] = $this->user->prop_okato;

        if (!empty($this->user->prog_okato))
            $data['form']['persona']['addressResidential']['kladrID'] = $this->user->prog_okato;

        if (!empty($this->user->work_phone_buh))
            $data['form']['persona']['employment']['employerPhone'] = $this->user->work_phone_buh;

        if (!empty($this->user->work_url))
            $data['form']['persona']['employment']['employerSite'] = $this->user->work_url;

        if (!empty($this->user->phone_mesto)) {
            $data['form']['persona']['employment']['workPhone'] = $this->user->phone_mesto;
            $data['form']['persona']['employment']['workPhoneState'] = 1;
            $data['form']['persona']['employment']['workPhoneMethod'] = 4;
        }

        if (!empty($this->user->work_email)) {
            $data['form']['persona']['employment']['workEmail'] = $this->user->work_email;
            $data['form']['persona']['employment']['workEmailState'] = 1;
            $data['form']['persona']['employment']['workEmailMethod'] = 4;
        }

        if (!empty($this->user->dohod_official))
            $data['form']['persona']['employment']['salaryOfficial'] = $this->user->dohod_official;

        if (!empty($this->user->dohod))
            $data['form']['persona']['employment']['salaryActual'] = $this->user->dohod;

        if (!empty($this->userdolgn_mesto))
            $data['form']['persona']['employment']['occupation'] = $this->user->dolgn_mesto;

        if (!empty($this->user->work_type))
            $data['form']['persona']['employment']['employmentType'] = $this->user->work_type;

        if (!empty($this->user->stag_mesto))
            $data['form']['persona']['employment']['employmentTime'] = $this->user->stag_mesto;

        if (!empty($this->user->stag_common))
            $data['form']['persona']['employment']['jobExpirience'] = $this->user->stag_common;

        if (!empty($this->user->pred_mesto))
            $data['form']['persona']['employment']['previousEmployment'] = $this->user->pred_mesto;


        // родственники
        $family = db()->getList("SELECT * FROM rodstv r WHERE r.user_id = :uid", array(':uid' => $this->user->id));
        if (count($family)) {
            foreach ($family as $people) {
                //супруг
                if ($people['otnosh'] == 4) {
                    $type = 'spouse';
                } else {
                    $type = 'relative';
                }

                $data['form']['persona']['contactInfo'][$type . 'Phone'] = !empty($people["phone"]) ? $people["phone"] : 'нет';

                if (!empty($people["fio"])) {
                    list($f, $i, $o) = explode(' ', $people["fio"]);

                    if (!empty($f))
                        $data['form']['persona']['contactInfo'][$type . 'LastName'] = $f;

                    if (!empty($i))
                        $data['form']['persona']['contactInfo'][$type . 'FirstName'] = $i;

                    if (!empty($o))
                        $data['form']['persona']['contactInfo'][$type . 'Patronimic'] = $o;
                }

                $data['form']['persona']['contactInfo'][$type . 'PhoneState'] = 1;
                $data['form']['persona']['contactInfo'][$type . 'PhoneMethod'] = 4;
            }
        }

        if (!empty($this->user->jobCategory))
            $data['form']['persona']['employment']['jobCategory'] = $this->user->jobCategory;

        if ($card) {
            // электронный кошелек (яндекс)
            if ($card['is_ym_ewallet'] == 1) {
                $loanReceivingMethod = array(
                    'ewallet' => array(
                        'ewalletName' => 'Яндекс',
                        'ewalletNumber' => $card["number"],
                        'BIN' => substr($card["number"], 0, 6),
                        'PAN' => str_replace('x', '0', substr($card["number"], -4)),
                        'recurrent' => 0,
                    )
                );
                $loanReceivingMethodShort = 3;
            }

            // пластиковая карта
            else {
                if (self::verifyCard($card)) {
                    $loanReceivingMethod = array(
                        'bankCard' => array(
                            'cardHolderName' => $card["owner"],
                            'expirationDate' => str_pad($card["month_end"], 2, '0', STR_PAD_LEFT) . "/" . $card["year_end"],
                            'BIN' => str_replace(['x', 'X'], '0', substr($card["number"], 0, 6)),
                            'PAN' => str_replace('x', '0', substr($card["number"], -4)),
                            'recurrent' => 0,
                        )
                    );
                    $loanReceivingMethodShort = 1;
                } else {
                    $loanReceivingMethod = array(
                        'cash' => array(
                            'cash' => 1
                        )
                    );
                    $loanReceivingMethodShort = 5;
                }
            }
        } else {
            $loanReceivingMethod = array(
                'cash' => array(
                    'cash' => 1
                )
            );
            $loanReceivingMethodShort = 5;
        }

        $data['form']['loanReceivingMethod'] = $loanReceivingMethod;
        $data['form']['info']['loan']['loanReceivingMethod'] = $loanReceivingMethodShort;
        $user_ip=$this->user->ip_address;
        $data['form']['deviceInfo']['ipAddress'] = empty($user_ip) ? $_SERVER['SERVER_ADDR'] : $user_ip;

        $nbkiXML = db()->scalar("SELECT xml FROM nbki_credit_history WHERE order_id=:order_id", array(':order_id' => $this->order->id));
        $kbkiXML = db()->scalar("SELECT xml FROM eqki_credit_history WHERE order_id=:order_id", array(':order_id' => $this->order->id));
        $cronosXML = db()->scalar("SELECT xml FROM cronos_history WHERE order_id=:order_id", array(':order_id' => $this->order->id));

        if (!empty($nbkiXML)) {
            $nbkiXML = preg_replace('/encoding="windows-1251"/', 'encoding="utf-8"', $nbkiXML);
            $data['form']['NBKI'] = base64_encode($nbkiXML);
        }

        if (!empty($kbkiXML)) {
            $kbkiXML = preg_replace('/encoding="windows-1251"/', 'encoding="utf-8"', $kbkiXML);
            $data['form']['EQUIFAX'] = base64_encode($kbkiXML);
        }


        if (!empty($cronosXML)) {
            $cronosXML = preg_replace('/encoding="windows-1251"/', 'encoding="utf-8"', $cronosXML);
            $data['form']['CRONOS'] = base64_encode($cronosXML);
        }


        if (!empty($this->user->external_uid) && in_array($this->user->external_account, array('vk', 'ok', 'fb'))) {
            $key = null;
            switch ($this->user->external_account) {
                case 'vk':
                    $key = $this->user->external_account;
                    break;
                case 'fb':
                    $key = 'facebook';
                    break;
                case 'ok':
                    $key = 'odnoklassniki';
                    break;
            }

            if ($key)
                $data['form']['socialNetwork'] = array($key => $this->user->external_uid);
        }

        // Вопросы правды
        if (count($truthQuestions = $this->user->getScoristaTruthQuestions())) {
            $data['form']['truthQuestions'] = $truthQuestions;
        }
        $this->_data = $data;
    }

    /**
     * Проверка карты
     *
     * @param $card
     * @return bool
     */
    public static function verifyCard($card) {
        if (!(strlen($card["owner"]) > 0 && preg_replace('/[^A-Za-z ]/m', '', $card['owner']) === $card['owner']))
            return false;
        $card["month_end"] = (int) $card["month_end"];

        if ($card["month_end"] <= 0 && $card["month_end"] > 12)
            return false;

        $card['year_end'] = (int) $card['year_end'];

        if ($card['year_end'] < date('Y'))
            return false;

        if (empty($card['number']))
            return false;

        $pan = substr($card["number"], -4);

        if (!(int) $pan)
            return false;

        return true;
    }

    /**
     * ЗАПРОС «ТРЕБОВАНИЕ ЭКСПЕРТИЗЫ»
     */
    public function sendDataOnExamination() {
        if ($this->test) {
            $this->setTestData();
        } else
            $this->formingDataOnExamination();

        $response = null;

        if ($resp = $this->request())
            $response = new ExaminationScoristaResponse($resp, $this->_data);

        if ($response->isOk()) {
            db()->query("INSERT INTO `scorista_ansver` SET `requestid`=:requestid,`ansver`=:answer,`order_id`=:order_id, httpRequestId = :httpRequestId", array(
                ':requestid' => $response->getRequestId(),
                ':answer' => $response->status,
                ':httpRequestId' => $this->_lastHttpRequestId,
                ':order_id' => $this->order->id
            ));
            $result = '<h2>По клиенту успешно направлена заявка на скоринг.</h2><h3>Нажмите <button type="button" id="refresh-scorista-answer" class="button">Обновить</button> для получения решения по клиенту</h3>';
        } else {
            $result = '<h2>При отправке запроса по клиенту возникли ошибки</h2><br>' . $response->getError();
        }
        return $result;
    }

    /**
     * Формируем тестовые данные
     */
    public function setTestData() {
        $this->_data['form'] = json_decode(file_get_contents(ROOT . '/data/json_2.txt'), 1);
        $this->_data['form']['persona']['contactInfo']['cellularState'] = 2;
        $this->_data['form']['persona']['contactInfo']['cellularMethod'] = 2;
        $this->_data['form']['persona']['contactInfo']['phoneState'] = 1;
        $this->_data['form']['persona']['contactInfo']['phoneMethod'] = 4;
        $this->_data['form']['persona']['contactInfo']['email'] = 'example@mail.ru';
        $this->_data['form']['persona']['contactInfo']['emailState'] = 4;
        $this->_data['form']['persona']['contactInfo']['emailMethod'] = 4;
        $this->_data['form']['info']['repaymentSchedule'][0]['repaymentDate'] = '29.10.2016';
        $this->_data['form']['info']['loan']['loanID'] = $this->order->id;
    }

    /**
     * Запрос к Scorista API
     * @param string $url адрес для запроса
     * @param array $data данные в XML
     * @return mixed
     * @throws Exception
     */
    private function request(array $data = null, $url = null) {
        if ($url === null)
            $url = self::$_url;

        if ($data !== null)
            $this->_data = $data;

        if (empty($this->_data) || !is_array($this->_data))
            throw new Exception('Нет данных для передачи');

        $data = json_encode($this->_data);
        $nonce = sha1(uniqid(true));
        $password = sha1($nonce . $this->_secret);

        $ch = curl_init();

        $opts = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                "username: " . $this->_userName,
                "nonce: " . $nonce,
                "password: " . $password,
                "Content-type: application/json"
            ),
        );
        curl_setopt_array($ch, $opts);

        $this->_lastHttpRequestId = HttpRequest::add('scorista', $opts, $this->order->id);

        $response = curl_exec($ch);

        HttpResponse::add($this->_lastHttpRequestId, $response);

        curl_close($ch);
        return empty($response) ? null : $response;
    }

    /**
     * ЗАПРОС «КРЕДИТНОЕ РЕШЕНИЕ»
     * @param array $request - Запроса на Скористе
     * @return DecisionScoristaResponse|null
     * @throws Exception
     */
    public function getCreditDecisionRequest($request) {
        $requestBody = self::getRequestBody($request['httpRequestId']);

        if ($requestBody && ($response = $this->request(['requestID' => $request['requestid']]))) {
            return new DecisionScoristaResponse($response, $requestBody);
        }
        return null;
    }

    /**
     * Посылка статуса в Скористу
     * Если оплачен - отправляем запрос "Отчет о погашении займа"
     * Если не оплачен - отправляем запрос "Запрос о выдаче займа"
     *
     * @return bool|IssueScoristaResponse|null|RepaymentScoristaResponse|string
     */
    public function sendStatuses() {
        if ($this->order->isClosed) {
            return $this->sendStatusRepayment();
        }
//        elseif($this->order->getHasFines()){
//            return $this->sendStatusFine();
//        }
        return $this->sendStatusIssue();
    }

    /**
     * Автоотправка
     * @return type
     */
    public function sendStatusesAuto() {
        if ($this->_autoSend && $this->getRequestId()) {
            return $this->sendStatuses();
        }
    }

    /**
     * Автоотправка
     * @param type $order_id
     * @return string
     */
    static function autoSend($order_id) {
        $scorista = new self($order_id);
        try {
            $out=$scorista->sendStatusesAuto();
            Log::write($order_id.'-Scorista::autoSend');
            return $out;
            
        } catch (Exception $ex) {
            
        }
    }

    function logSndStatus($status,$resp=''){
        if($resp){
            $resp=  print_r(json_decode($resp,true),true);
        }
        $data=array(
            'order_id'=>$this->order->id,
            'time'=>date('Y-m-d H:i:s'),
            'status'=>$status,
            'resp'=>$resp,
        );
        DB::insert('scorista_ansver_snd_status', $data);
    }
    /**
     * Получаем requestId для Скористы
     * @return mixed|null
     */
    public function getRequestId() {
        if (!$this->_requestId)
            $this->_requestId = db()->scalar("SELECT requestid FROM `scorista_ansver` WHERE `order_id`=:order_id", array(
                ':order_id' => $this->order->id
            ));

        return $this->_requestId;
    }

    /**
     * ЗАПРОС К СЕРВИСУ «ОТЧЕТ О ПОГАШЕНИИ ЗАЙМА»
     *
     * @return bool|null|RepaymentScoristaResponse
     * @throws Exception
     */
    public function sendStatusRepayment() {
        if (!$this->order->isClosed)
            return false;

        $url = 'https://api.scorista.ru/report/repayment/json';
        $lastPayment = $this->order->getLastPayment();

        $data = array(
            'requestID' => $this->getRequestId(),
            'repaymentDate' => preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$3.$2.$1', $lastPayment->date), // дата погашения
            'repaymentAmount' => $this->order->getPaymentsTotalSum(), // сумма погашенного кредита
            'remainingAmount' => $this->order->getRemainingAmount(), // сумма, оставшаяся к погашению
            'repaymentMethod' => 1, // способ погашения
        );

        if ($response = $this->request($data, $url)) {
            $this->logSndStatus('is_paid=1',$response);
            return new RepaymentScoristaResponse($response, $data);
        }
        return null;
    }

    function hasFines(){
        if ($this->order->isClosed) return false;
        return $this->order->getPaymentsTotalSum();
        return $this->order->getHasFines();
    }
    /**
     * ЗАПРОС К СЕРВИСУ «ОТЧЕТ О НЕПОГАШЕНИИ ЗАЙМА»
     *
     * @return bool|null|RepaymentScoristaResponse
     * @throws Exception
     */
    public function sendStatusFine($remainingAmount=null) {
//        if (!$this->hasFines())
//            return false;

        $url = 'https://api.scorista.ru/report/repayment/json';
        
        $data = array(
            'requestID' => $this->getRequestId(),
            'repaymentDate' => preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$3.$2.$1', date('Y-m-d')), // дата погашения
            'repaymentAmount' => 0, // сумма погашенного кредита
            'remainingAmount' => ($remainingAmount!==null)?$remainingAmount:$this->order->back_sum, // сумма, оставшаяся к погашению
            'repaymentMethod' => 1, // способ погашения
        );

        if ($response = $this->request($data, $url)) {
            $this->logSndStatus('is_paid=0',$response);
            return new RepaymentScoristaResponse($response, $data);
        }
        return null;
    }

    /**
     * ПОРЯДОК ТИПА ЗАПРОСА «ОТЧЕТ О ВЫДАЧЕ ЗАЙМА»
     *
     * @return bool|IssueScoristaResponse|null|string
     * @throws Exception
     */
    public function sendStatusIssue() {
        if ($this->order->isClosed)//Тут если не отправлено то и нельзя запрос об оплате отправить
            return false;

        $value = ScoristaTranslater::$orderStatus[$this->order->status];

        if ($value === null)
            return 'Не возможно отправить данные в Скориту, т.к. статус заказа: "Требует рассмотрения"';

        $data = array(
            'requestID' => $this->getRequestId(),
            'loanDelivery' => $value,
            'issueDate' => $this->order->date,
        );

        if ($response = $this->request($data, 'https://api.scorista.ru/report/issue/json')) {
            $this->logSndStatus('status='.$value,$response);
            return new IssueScoristaResponse($response, $data);
        }

        return null;
    }

}

class ScoristaResponse {

    const STATUS_DONE = 'DONE';
    const STATUS_OK = 'OK';
    const STATUS_ERROR = 'ERROR';
    const STATUS_WAIT = 'WAIT';
    const STATUS_CHECK = 'CHECK';

    public $status;
    protected $_data;
    protected $_raw;
    private $requestData;

    public function __construct($json, $requestData = array()) {
        $this->_raw = $json;

        if (!is_array($requestData)) {
            if (self::isJson($requestData))
                $requestData = json_decode($json, true);
            else
                throw new Exception('Ошибка RequestData');
        }

        $this->requestData = $requestData;

        if (!$array = json_decode($json, true))
            throw new Exception('Вообще не правильный ответ от Скористы');

        $this->status = $array['status'];
        $this->_data = isset($array['data']) ? $array['data'] : null;
    }

    /**
     * Запрос вернул данные
     * @return bool
     */
    public function isDone() {
        return $this->status === self::STATUS_DONE;
    }

    /**
     * Запрос в обработке
     * @return bool
     */
    public function isWait() {
        return $this->status === self::STATUS_WAIT;
    }

    /**
     * Запрос принят без ошибок
     * @return bool
     */
    public function isOk() {
        return $this->status === self::STATUS_OK;
    }

    /**
     * Ошибка в запросе
     * @return bool
     */
    public function isError() {
        return $this->status === self::STATUS_ERROR;
    }

    public function getError() {
        $array = $this->getArray();
        if (!isset($array['error']))
            return null;

        return new ErrorScoristaResponse($array['error']);
    }

    /**
     * Возвращает данные запроса
     * @return null|array
     */
    public function getData() {
        return $this->_data;
    }

    /**
     * Возвращает сырые данные ответа
     * @return string
     */
    public function getRaw() {
        return $this->_raw;
    }

    /**
     * Возвращает данные в виде массива
     * @return mixed
     */
    protected function getArray() {
        return json_decode($this->_raw, true);
    }

    public function getRequest() {
        return $this->requestData;
    }

    private static function isJson($string) {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

}

/**
 * Class DecisionScoristaResponse
 * Ответ "Кредитное решение"
 */
class DecisionScoristaResponse extends ScoristaResponse {

    /**
     * Кредитное решение
     * @param null|string $name
     * @return null|array|string
     */
    public function getDecision($name = null) {
        if (!isset($this->_data['decision']))
            return null;

        if ($name !== null) {
            if (isset($this->_data['decision'][$name]))
                return $this->_data['decision'][$name];
            return null;
        }
        return $this->_data['decision'];
    }

}

/**
 * Class ExaminationScoristaResponse
 * Ответ "ТРЕБОВАНИЕ ЭКСПЕРТИЗЫ"
 */
class ExaminationScoristaResponse extends ScoristaResponse {

    private $_requestId;

    public function __construct($json, $data = array()) {
        parent::__construct($json, $data);
        $array = $this->getArray();
        if (isset($array['requestid']))
            $this->_requestId = $array['requestid'];
    }

    public function getRequestId() {
        return $this->_requestId;
    }

}

/**
 * Class IssueScoristaResponse
 *
 * Ответ на запрос "отчет о выдаче займа"
 */
class IssueScoristaResponse extends ScoristaResponse {
    
}

/**
 * Class RepaymentScoristaResponse
 * ОТВЕТ СЕРВИСА «ОТЧЕТ О ПОГАШЕНИИ ЗАЙМА»
 */
class RepaymentScoristaResponse extends ScoristaResponse {
    
}

/**
 * Class ErrorScoristaResponse
 * Ошибка Скористы
 */
class ErrorScoristaResponse {

    public $code;
    public $message;
    public $details;
    public $other;
    public static $divider = '<br>';

    public function __construct(array $error) {
        $this->other = $error;
        $this->code = isset($this->other['code']) ? $this->other['code'] : null;
        $this->message = $this->other['message'];
        $this->details = isset($this->other['details']) ? $this->other['details'] : null;

        unset($this->other['code'], $this->other['message'], $this->other['details']);
    }

    public function __toString() {
        $details = '';
        if ($this->details)
            $details = self::toString($this->details);
        $other = empty($this->other) ? '' : json_encode($this->other);
        return $this->code . '> ' . $this->message . '. <br><hr>' . $details . '<br>' . $other;
    }

    public static function toString(array $array, $path = '') {
        $html = '';
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if ($key != '0') {
                    $p = empty($path) ? $key : $path . '.' . $key;
                } else
                    $p = empty($path) ? '' : $path;

                $html .= self::toString($value, $p);
            } else {
                $html .= $path . ' > ' . $value;
                $html .= self::$divider;
            }
        }

        return $html;
    }

}

class ScoristaTranslater {

    /** @var array Семейный статус */
    public static $maritalStatus = array(
        0 => null, //Не указано
        1 => 1, //'Холост/незамужем',
        2 => 2, //'Женат/замужем',
        3 => 1, //'Вдовец/вдова -> Гражданский брак',
        4 => 3 //'Разведен/разведена'
    );

    /** @var array Семейное отношение */
    public static $relativePerson = array(
        0 => null, //Не указано
        1 => 'Отец',
        2 => 'Мать',
        3 => 'Брат/Сестра',
        4 => 'Супруг/Супруга',
        5 => 'Сын/Дочь',
        6 => 'Гражданский брак'
    );
    public static $orderStatus = array(
        'О' => 1, //'Одобрено',
        'Н' => 0, //'Не одобрено',
        'З' => 0, //'Заблокировано',
        'Т' => null, //'Требует рассмотрения',
        'К' => 2 //'Клиент отказался'
    );

}

class ScoristaTruthQuestions {

    private static $_questionsListName = array(
        // кредит
        'activeCreditsSum' => 'Какова сумма кредитов (в рублях), по которым Вы сейчас платите?',
        'activeCreditsNumber' => 'Сколько всего у Вас кредитов, по которым Вы сейчас платите?',
        'newCreditSumLastMonth' => 'Какова сумма кредитов (в рублях), полученных в предыдущем месяце, по которым Вы сейчас платите?',
        'newCreditNumberLastMonths' => 'Сколько всего кредитов Вы брали в прошлом месяце?',
        'creditPaymentSumlastMonth' => 'Какую сумму (в рублях) Вы потратили на погашение кредитов в прошлом месяце?',
        'lastCreditBorrowingTime' => 'Когда Вы получили последний кредит?',
        'maxCreditSum' => 'Кредит на какую максимальную сумму (в рублях) Вы брали?',
        'minCreditSum' => 'Кредит на какую минимальную сумму (в рублях) Вы брали?',
        'numberCreditRequestLastMonth' => 'Сколько раз вы обращались за кредитом за последний месяц?',
        //просрочка
        'maxPaymentDelay6month' => 'На какой максимальный срок Вы задерживали выплату по кредитам за последние полгода?',
        'paymentDelayFact' => 'Были ли у Вас задержки в погашении кредитов?',
        'paymentDelayRepeat' => 'Как часто Вы допускали задержку в погашении кредита?',
        'methodPaymentDelayElimination' => 'Какие способы устранения задержки в погашении кредита Вы использовали?',
        'collectorExperience' => 'Опишите Ваш опыт общения с коллекторами.',
        'paymentDelayReason' => 'Какая причина задержки в погашении кредита является для Вас допустимой?',
        'paymentDelayAccept' => 'Насколько для Вас приемлема задержка оплаты по кредиту?',
        // психология
        'materialStatus' => 'Как бы вы охарактеризова ли своё материальное положение?',
        'jobChanging' => 'Как давно Вы меняли место работы?',
        'mobileChanging' => 'Как давно Вы меняли номер сотового телефона?',
        'creditMethod' => 'Какие источники привлечения заёмных средств для Вас приоритетные?',
        'paymentDelayReaction' => 'Как бы Вы отнеслись к должнику, просрочившему погашение кредита, если бы были на месте кредитной организации?',
        'spontaneousPurchaseSum' => 'Спонтанную покупку на какую максимальную сумму (в рублях) Вы считаете для себя приемлемой?',
        'debitorFraudReaction' => 'Как бы Вы отнеслись к знакомому или родственнику, не вернувшему Вам долг?'
    );

    /**
     * Возвращает название вопроса по ключу
     * @param $key
     * @return mixed
     */
    public static function getQuestionNameByKey($key) {
        if (isset(self::$_questionsListName[$key]))
            return self::$_questionsListName[$key];
        return $key;
    }

}
