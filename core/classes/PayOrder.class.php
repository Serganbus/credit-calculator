<?php

require_once ROOT . '/admin/core/functions.php';
require_once ROOT . '/admin/lib/scoring/scorista/Scorista.php';
require_once ROOT . '/core/orders/UserOrder.php';

/**
 * оплата через процессинг
 */
class PayOrder {

    private static function sendSms($userData, $text) {
        global $db;
        $email = "";
        $phone = "";
        if (is_array($userData)) {
            $phone = preg_replace('/\D/', '', $userData['phone']);
            $email = $userData['email'];
        } else {
            $phone = $userData;
        }
        if ($email) {
            $varList = array('TEXT' => $text);
            XMail::send($email, 'Сообщение по оплате', 'simple', $varList);
        }
        plugin::getInstance()->load('sms')->send($phone, -1, $text);
    }

    static function getCollectorByOrder($order) {
        $collector = 0;
        if (($order['collector'] == '1') || ($order['collector_n'] == '1')) {
            $collector = 1;
        } elseif ($order['collector_n'] == '2') {
            $collector = 3;
        } else {//Вниутренний коллектор, можно смотреть по частичной оплате
            if ($order['occurred_status'] == 'Ч') {
                $collector = 2;
            }
        }
        return $collector;
    }

    /**
     * Оплата договора
     * @param int $order_id
     * @param float $sum
     * @param int $pay_type : 1-полная оплата, 2-частичная оплата, 3-простая пролонгация, 5-оплата + суд изд
     * @param int $pay_from : 1-робокасса, 2-киви, 3-ариус, 4-елекснет
     */
    static function pay($order_id, $sum, $pay_type = 1, $pay_from = 1) {
        //self::close($order_id);
        global $db;
        $out_summ = $sum;
        $uo = new UserOrder($order_id);
        $order = $uo->getInfo();

        $userRow = $db->GetRow("SELECT *, CONCAT(name, ' ', surname) as user_io FROM `users` WHERE `id` = %s LIMIT 1", array($order['user_id']));

        $message = '';
        // order properties	 
        $curr_inv_id = $order['id']; // shop's invoice number (unique for shop's lifetime)
        $curr_out_summ = $order['back_sum']; // invoice summ

        $collector = self::getCollectorByOrder($order);

        if (in_array($pay_type, array(1, 5))) {//Тут всё понятно
            // осталось выплатить
            $curr_out_summ = $order['current_total_debt'];
            //$curr_out_summ = $uo->getTotalDebtAtTodaysDate();
            if ($curr_out_summ == $out_summ and $order['is_paid'] == '0') {
                DB::insert('payments', array(
                    'date' => date("Y-m-d"),
                    'time' => date("H:i:s"),
                    'paysum' => $out_summ,
                    'order_id' => $order_id,
                    'pay_from' => $pay_from,
                    'pay_type' => $pay_type,
                    'collector' => $collector,
                ));

                $prosr_text = '';
                if ($order['fine_days'] > 0) {
                    $prosr_text = " и просрочки дней: " . $order['fine_days'];
                }
                $message = "Оплата по займу" . $prosr_text . " №" . $order['order_num'] . " в сумме " . $curr_out_summ . " руб. зачтена. Спасибо, что стали нашим клиентом.";


                // начисляем РД
                //$trust_rating = ceil($order['back_sum'] * (1 - $order['fine_days'] / (3 + $order['collector_rating'])));
                //$db->Run("UPDATE `orders` SET `is_paid`='1',`pay_from`='1', `trust`='%s' WHERE `id`=%s", array($trust_rating, $order['id']));
                self::close($order_id);

                // с такими людьми больше не дружим (от 27.01.2015)
                if ($collector == '1' or $collector == '3') {
                    $db->Run("UPDATE `users` SET `banned`='1' WHERE `id`='%s'", array($userRow['id']));
                }
            } else {
                XMail::log(http_build_query($_REQUEST) . " - " . $pay_type . ' - ' . $curr_out_summ);
            }
        }
        if ($pay_type == 3) {//Простая Пролонгация
            $orderProlongationSumm = $order['percents_debt'] + $order['fine_debt'];
            if ($orderProlongationSumm == $out_summ) {
                $row_prol = $db->GetRow("SELECT quantity, date FROM `prolongation` WHERE `order_id`='%s'", array($order['id']));
                $b_prolongation = false;
                if ($row_prol != NULL) {
                    $date_response = date('Y-m-d 00:00:00', strtotime($row_prol['date'] . ' + 1 days'));
                    $ts1 = strtotime($date_response);
                    $now = Date('Y-m-d 00:00:00');
                    $ts2 = strtotime($now);
                    if ($ts1 <= $ts2) {
                        $db->Run("UPDATE `prolongation` SET `quantity`=quantity+1,date=%s  WHERE `order_id`='%s'", array($now, $order['id']));
                        $db->Run("commit;");
                        $b_prolongation = true;
                    }
                } else {
                    $date = Date('Y-m-d H:i:s');
                    $db->Run("INSERT INTO `prolongation` SET `order_id` = '%s',date=%s", array($order['id'], $date));
                    $db->Run("commit;");
                    $b_prolongation = true;
                }
                if ($b_prolongation) {
                    DB::insert('payments', array(
                        'date' => date("Y-m-d"),
                        'time' => date("H:i:s"),
                        'paysum' => $out_summ,
                        'order_id' => $order_id,
                        'pay_from' => $pay_from,
                        'pay_type' => $pay_type,
                        'collector' => $collector,
                    ));


                    $uo = new UserOrder($order_id);
                    $order = $uo->getInfo();

                    $months = array('0', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
                    $tmpArray = explode("-", $order['back_date_with_prolong']);
                    $month = $months[intval($tmpArray[1])];
                    $year = $tmpArray[0];
                    $day = $tmpArray[2];
                    $day = preg_replace("/^0/", "", $day);
                    $message = "Уважаем(ый)ая " . $userRow['user_io'] . ", Ваш займ №" . $order['order_num'] . " успешно продлен до " . $day . " " . $month . " " . $year . " включительно, к погашению " . $order['back_sum'] . " руб.";
                }
            } else {

                XMail::log(http_build_query($_REQUEST) . " - " . $pay_type . ' - ' . $curr_out_summ.' - '.$orderProlongationSumm);
            }
        }
        if ($pay_type == 2) {//Частичная оплата
            $b_pay = true;
            if ($collector) {//Тут какая то страшная логика с оплатой по коллектингу
                $row_collector_pay = $db->GetRow("SELECT `date` FROM `collector_pay` WHERE `order_id`='%s'", array($curr_inv_id));
                
                if ($row_collector_pay != NULL) {
                    $now = Date('Y-m-d H:i:s');
                    $ts1 = strtotime($row_collector_pay['date']);
                    $ts2 = strtotime($now);
                    //if ($ts2 - $ts1 > 360) {
                        $db->Run("UPDATE `collector_pay` SET date=%s  WHERE `order_id`='%s'", array($now, $curr_inv_id));
                        $db->Run("commit;");
                       
                    //}
                } else {
                    $date = Date('Y-m-d H:i:s');
                    $db->Run("INSERT INTO `collector_pay` SET `order_id` = '%s',date=%s", array($curr_inv_id, $date));
                    $db->Run("commit;");
                    
                }
            }
            if ($b_pay) {
                DB::insert('payments', array(
                    'date' => date("Y-m-d"),
                    'time' => date("H:i:s"),
                    'paysum' => $out_summ,
                    'order_id' => $order_id,
                    'pay_from' => $pay_from,
                    'pay_type' => $pay_type,
                    'collector' => $collector,
                ));
                $uo = new UserOrder($order_id);
                if (!$uo->getTotalDebtAtTodaysDate() > 0) {
                    self::close($order_id);
                }
                $message = "Уважаем(ый)ая " . $userRow['user_io'] . ", была зачтена частичная оплата в " . intval($out_summ) . " руб. по займу №" . $order['order_num'];
            }
        }

        if ($message) {
            self::sendSms($userRow, $message);
        }
    }

    static function close($order_id) {
        DB::update('orders', array('is_paid' => 1), "id=$order_id AND is_paid='0'");
        Scorista::autoSend($order_id);
    }

}
